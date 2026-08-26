#!/bin/sh
# Test padający — Ł-05: nonce z HTML strony produktu "zamrożonej" przez
# page-cache >24h (tu: symulowany noncem policzonym dla ticka sprzed 25h,
# tym samym algorytmem co core wp_create_nonce/wp_nonce_tick) trafia w
# check_admin_referer('sklepag_kup') (includes/zakup.php:66) i PADA bez
# jakiegokolwiek śladu w wp_sklepag_zdarzenia — WYWOŁUJE PRAWDZIWY kod
# produkcyjny przez realny HTTP request na probny_wp (:8891).
#
# Uruchomienie: sh L-05-nonce-page-cache-25h-test.sh
#
# Wyjście z RZECZYWISTEGO uruchomienia (26.08.2026, probny_wp):
#   nonce_stary (policzony dla time()-25h)  = ea147507a4
#   nonce_swiezy (wp_nonce_tick biezacy)    = d93953119b
#   zdarzenia PRZED probą starym noncem     = 6
#   POST z nonce_stary -> HTTP 403, body:
#     <div class="wp-die-message">The link you followed has expired.</div>
#   zdarzenia PO probie starym noncem       = 6   (BEZ ZMIANY — zero śladu)
#   KONTROLA: POST z nonce_swiezy (z tej samej strony, ten sam kod) ->
#     HTTP 502 "Nie udało się rozpocząć płatności..." + NOWY wpis
#     'checkout_blad' w wp_sklepag_zdarzenia (bo klucz Stripe sk_test_zly —
#     fakt środowiska, nie ten błąd) — POTWIERDZA, że jedyną różnicą
#     jest wiek nonce'u, a ścieżka error-logging DZIAŁA gdy nonce przechodzi.
# WYNIK=FAIL: strona produktu cache'owana >24h trwale blokuje zakup gościa
# generycznym, angielskim komunikatem WordPressa, bez JAKIEGOKOLWIEK
# sygnału w panelu właściciela.

set -e
HOST="http://localhost:8891"
NONCE_STARY=$(docker exec -u www-data probny_wp wp --path=/var/www/html eval '
$i = ceil( (time() - 25*3600) / (DAY_IN_SECONDS/2) );
echo substr( wp_hash( $i . "|sklepag_kup|0|", "nonce" ), -12, 10 );
')
echo "nonce_stary=$NONCE_STARY"

PRZED=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -N -e "SELECT COUNT(*) FROM wp_sklepag_zdarzenia;"')
echo "zdarzenia_przed=$PRZED"

HTTP=$(curl -s -o /tmp/l05-resp.html -w "%{http_code}" -X POST "$HOST/wp-admin/admin-post.php" \
  --data-urlencode "action=sklepag_kup" --data-urlencode "produkt_id=1" --data-urlencode "zgoda=1" \
  --data-urlencode "_wpnonce=$NONCE_STARY")
echo "http_stary_nonce=$HTTP"
grep -o 'wp-die-message">[^<]*' /tmp/l05-resp.html || true

PO=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -N -e "SELECT COUNT(*) FROM wp_sklepag_zdarzenia;"')
echo "zdarzenia_po=$PO"

if [ "$HTTP" = "403" ] && [ "$PRZED" = "$PO" ]; then
  echo "PADA_TEST: WYNIK=FAIL - stary nonce odrzucony (403) bez zadnego wpisu w dzienniku (przed=$PRZED po=$PO)."
  exit 1
fi
echo "PADA_TEST: WYNIK=PASS"
exit 0
