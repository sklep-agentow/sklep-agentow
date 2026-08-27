#!/bin/bash
# STRAŻNIK REGRESJI — SK-L-002 (martwy formularz „Kup teraz" bez śladu i po angielsku).
#
# Wada: check_admin_referer('sklepag_kup') przy nonce sprzed >24 h oddawał komunikat
# rdzenia po angielsku i NIE zostawiał wpisu w dzienniku. Najczęstsza przyczyna to
# pamięć podręczna strony produktu: formularz jest martwy dla WSZYSTKICH kupujących,
# a właściciel nie ma tego skąd zobaczyć — sprzedaż stoi po cichu.
#
# PASS (exit 0) = odmowa 403, komunikat po polsku z instrukcją odświeżenia,
#                 ORAZ nowy wpis 'kup_nonce_niewazny' w dzienniku zdarzeń.
# FAIL (exit 1) = brak śladu w dzienniku albo komunikat rdzenia po angielsku.
#
# Uruchomienie: bash testy/kup-wygasly-token-slad-test.sh [kontener]
set -u
KONTENER="${1:-probny_wp}"
WP="docker exec -u www-data $KONTENER wp --path=/var/www/html"
LICZ="global \$wpdb; echo (int) \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}sklepag_zdarzenia WHERE typ='kup_nonce_niewazny'\");"

PRZED=$($WP eval "$LICZ" 2>/dev/null)
echo "STRAZNIK: wpisow_przed=$PRZED"

ODP=$(docker exec "$KONTENER" curl -s -w '\nHTTP=%{http_code}' \
  -X POST 'http://localhost/wp-admin/admin-post.php' \
  --data 'action=sklepag_kup&produkt_id=1&zgoda=1&_wpnonce=token-sprzed-doby' 2>/dev/null)

KOD=$(printf '%s' "$ODP" | tail -1 | sed 's/HTTP=//')
TRESC=$(printf '%s' "$ODP" | head -n -1)

PO=$($WP eval "$LICZ" 2>/dev/null)
echo "STRAZNIK: kod_http=$KOD (OCZEKIWANE: 403)"
echo "STRAZNIK: wpisow_po=$PO (OCZEKIWANE: wiecej niz przed)"

PL=1
printf '%s' "$TRESC" | grep -q "Odśwież stronę produktu" || PL=0
echo "STRAZNIK: komunikat_po_polsku_z_instrukcja=$( [ "$PL" = 1 ] && echo TAK || echo NIE ) (OCZEKIWANE: TAK)"

# Sprzatanie wpisow testowych.
$WP eval "global \$wpdb; \$wpdb->query(\"DELETE FROM {\$wpdb->prefix}sklepag_zdarzenia WHERE typ='kup_nonce_niewazny'\");" >/dev/null 2>&1

if [ "$KOD" = "403" ] && [ "$PO" -gt "$PRZED" ] && [ "$PL" = 1 ]; then
  echo "STRAZNIK: WYNIK=PASS"; exit 0
fi
echo "STRAZNIK: WYNIK=FAIL - wygasly token zakupu nadal jest cicha awaria"
exit 1
