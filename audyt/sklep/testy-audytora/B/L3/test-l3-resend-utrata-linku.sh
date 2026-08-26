#!/bin/bash
# PADAJĄCY TEST — L3 runda B — commit_head 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d
#
# Znalezisko: includes/admin-zamowienie.php:73-92 (sklepag_obsluz_resend) nadpisuje
# token_pobrania (linie 73-82) PRZED sprawdzeniem wyniku sklepag_wyslij_mail_sklepu()
# (linia 84). Skutek: gdy resend NIE wysyła maila (wp_mail zwraca false), STARY,
# dotąd działający link pobrania zostaje unieważniony, a NOWY nigdy nie dotarł do
# kupującego — utrata dostępu do zakupionego produktu bez działania kupującego.
#
# Test wywołuje NA ŻYWO: logowanie jako audytor-admin, prawdziwy zakup przez webhook
# (sesja testowa), prawdziwe wywołanie admin-post.php?action=sklepag_resend z
# poprawnym nonce z HTML panelu — DOKŁADNIE tak, jak zrobiłby to administrator w
# przeglądarce. Jedyna symulacja: funkcja sklepag_wyslij_mail_sklepu() w
# includes/zakup.php dostaje na chwilę `return false;` (docker cp + sed + docker cp
# z powrotem), żeby wymusić awarię wysyłki BEZ ruszania core wp_mail (nie wpływa na
# maile innych wtyczek/audytorów na współdzielonym stosie :8890).
#
# ⚠️ Stos WARSZTATOWY :8890, kontenery strona_wp_wordpress / strona_wp_db.
#    Przed uruchomieniem zrób migawkę bazy `wordpress` (DOPISEK-KOORDYNATORKI-B.md).
#    Skrypt sam sprząta (trap EXIT): przywraca oryginalny plik, kasuje wstawiony
#    wiersz testowy, czyści sekret webhooka. Hasło administratora czytane WYŁĄCZNIE
#    z /root/.claude/sekrety-audyt-admin.txt, nigdy nie jest wypisywane.
#
# PASS (exit 0) = po nieudanym resendzie STARY link nadal działa (200) — naprawione.
# FAIL (exit 1) = po nieudanym resendzie STARY link zwraca 403 — błąd nadal jest.
#
# Uruchomienie: bash test-l3-resend-utrata-linku.sh

set -u
WP=strona_wp_wordpress
DB=strona_wp_db
HASLO_ADMIN_PLIK=/root/.claude/sekrety-audyt-admin.txt
SEKRET="whsec_TEST_$$_regres"
SID="cs_test_l3_regres_$$"
TMPD=$(mktemp -d)
BACKUP="$TMPD/zakup.php.orig"
CKJAR="$TMPD/ck-admin.txt"

if [ ! -r "$HASLO_ADMIN_PLIK" ]; then
  echo "NIE DA SIĘ SPRAWDZIĆ: brak $HASLO_ADMIN_PLIK (hasło audytor-admin)"; exit 2
fi

sprzataj() {
  [ -f "$BACKUP" ] && docker cp "$BACKUP" "$WP":/var/www/html/wp-content/plugins/sklep-agentow/includes/zakup.php >/dev/null 2>&1
  docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -e \"
    DELETE FROM wp_sklepag_zdarzenia WHERE zamowienie_id IN (SELECT id FROM wp_sklepag_zamowienia WHERE stripe_session_id='${SID}');
    DELETE FROM wp_sklepag_zamowienia WHERE stripe_session_id='${SID}';
    DELETE FROM wp_sklepag_sesje_checkout WHERE stripe_session_id='${SID}';
  \"" >/dev/null 2>&1
  docker exec -u www-data "$WP" wp --path=/var/www/html option update sklepag_stripe_webhook_sekret "" >/dev/null 2>&1
  rm -rf "$TMPD"
}
trap sprzataj EXIT

docker cp "$WP":/var/www/html/wp-content/plugins/sklep-agentow/includes/zakup.php "$BACKUP"
docker exec -u www-data "$WP" wp --path=/var/www/html option update sklepag_stripe_webhook_sekret "$SEKRET" >/dev/null

# 1) Zamówienie testowe przez prawdziwy webhook (mail wychodzi normalnie pierwszy raz).
cat > "$TMPD/insert.php" <<EOF
<?php
global \$wpdb;
\$wpdb->query(\$wpdb->prepare("INSERT INTO {\$wpdb->prefix}sklepag_sesje_checkout (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'utworzona',%s)", '${SID}', 1, current_time('mysql')));
EOF
docker cp "$TMPD/insert.php" "$WP":/tmp/insert_regres.php
docker exec -u www-data "$WP" wp --path=/var/www/html eval-file /tmp/insert_regres.php >/dev/null
docker exec "$WP" rm -f /tmp/insert_regres.php

BODY="{\"type\":\"checkout.session.completed\",\"data\":{\"object\":{\"id\":\"${SID}\",\"payment_status\":\"paid\",\"amount_total\":14900,\"currency\":\"pln\",\"customer_details\":{\"email\":\"l3regres-${SID}@example.com\"},\"metadata\":{\"produkt_id\":\"1\",\"zgoda_czas\":\"2026-08-26 00:00:00\"}}}}"
t=$(date +%s); sig="${t}.${BODY}"
v1=$(printf '%s' "$sig" | openssl dgst -sha256 -hmac "$SEKRET" | sed 's/^.* //')
curl -s -o /dev/null -w 'webhook poczatkowy: HTTP %{http_code}\n' \
  -H "Content-Type: application/json" -H "Stripe-Signature: t=${t},v1=${v1}" \
  -X POST --data "$BODY" http://localhost:8890/wp-json/sklepag/v1/webhook

ZAM_ID=$(docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT id FROM wp_sklepag_zamowienia WHERE stripe_session_id='${SID}'\"")
docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT mail_tresc FROM wp_sklepag_zamowienia WHERE id=${ZAM_ID:-0}\"" > "$TMPD/mail.txt" 2>/dev/null
TOKEN=$(grep -o 'action=sklepag_pobierz[^ ]*t=[a-f0-9]\{64\}' "$TMPD/mail.txt" | grep -o '[a-f0-9]\{64\}$')
if [ -z "${ZAM_ID:-}" ] || [ -z "${TOKEN:-}" ]; then
  echo "NIE DA SIĘ SPRAWDZIĆ: webhook poczatkowy nie domknal zakupu (brak id/tokenu) — sprawdz klucz Stripe/sekret webhooka"
  exit 2
fi
OLD_URL="http://localhost:8890/wp-admin/admin-post.php?action=sklepag_pobierz&z=${ZAM_ID}&t=${TOKEN}"
KOD_PRZED=$(curl -s -o /dev/null -w '%{http_code}' -I "$OLD_URL")
echo "stary link PRZED resendem: HTTP $KOD_PRZED (oczekiwane 200)"

# 2) Logowanie jako audytor-admin + pobranie nonce resendu z prawdziwego HTML panelu.
HASLO_ADMIN="$(tr -d '\n' < "$HASLO_ADMIN_PLIK")"
curl -s -c "$CKJAR" -d "log=audytor-admin&pwd=${HASLO_ADMIN}&wp-submit=1&testcookie=1" http://localhost:8890/wp-login.php -o /dev/null
curl -s -b "$CKJAR" "http://localhost:8890/wp-admin/admin.php?page=sklepag-zamowienie&zamowienie=${ZAM_ID}" -o "$TMPD/zamowienie.html"
RESEND_PATH=$(grep -o 'admin-post.php?action=sklepag_resend[^"]*' "$TMPD/zamowienie.html" | sed 's/&#0\?38;/\&/g' | head -1)
if [ -z "$RESEND_PATH" ]; then
  echo "NIE DA SIĘ SPRAWDZIĆ: nie znaleziono linku resendu w panelu (logowanie audytor-admin nieudane?)"
  exit 2
fi
RESEND_URL="http://localhost:8890/wp-admin/${RESEND_PATH}"

# 3) Symulacja awarii wysyłki TYLKO w naszym pluginie (nie rusza core wp_mail).
sed "/^function sklepag_wyslij_mail_sklepu( \$adresat, \$temat, \$tresc ) {/a\\
\\treturn false; // TEST: symulacja awarii wysylki (usuwane po teście)" "$BACKUP" > "$TMPD/zakup.php.fail"
docker cp "$TMPD/zakup.php.fail" "$WP":/var/www/html/wp-content/plugins/sklep-agentow/includes/zakup.php
docker exec "$WP" php -l /var/www/html/wp-content/plugins/sklep-agentow/includes/zakup.php >/dev/null || { echo "BLAD PATCHOWANIA PLIKU"; exit 2; }
# opcache.validate_timestamps=On + revalidate_freq=2s: bez ręcznego resetu PHP-FPM/Apache
# potrafi przez chwilę serwować STARY bajtkod po podmianie pliku (zmierzone w tej rundzie).
docker exec -u www-data "$WP" wp --path=/var/www/html eval 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache zresetowany\n"; } else { echo "brak opcache\n"; }'
sleep 3  # opcache.revalidate_freq=2s na tym stosie (fakt zmierzony) — bez odczekania web SAPI zna stary bajtkod

echo "wywoluje resend (mail zasymulowany jako niedostarczony)..."
curl -s -b "$CKJAR" -o /dev/null "$RESEND_URL"

# Przywróć oryginalny kod NATYCHMIAST po akcji.
docker cp "$BACKUP" "$WP":/var/www/html/wp-content/plugins/sklep-agentow/includes/zakup.php

STATUS_PO=$(docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT status FROM wp_sklepag_zamowienia WHERE id=${ZAM_ID}\"")
KOD_PO=$(curl -s -o /dev/null -w '%{http_code}' -I "$OLD_URL")
echo "status zamowienia po nieudanym resendzie: $STATUS_PO"
echo "stary link PO nieudanym resendzie: HTTP $KOD_PO (PASS gdy 200, FAIL gdy 403)"

if [ "$KOD_PO" = "403" ]; then
  echo "FAIL: resend nadpisal token PRZED potwierdzeniem dostarczenia maila —"
  echo "kupujący stracił dostęp: stary link martwy (403), nowy mail nigdy nie dotarł."
  echo "Miejsce: includes/admin-zamowienie.php:73-84 (UPDATE token_pobrania przed sprawdzeniem \$wyslano)."
  exit 1
fi
echo "PASS: stary link nadal działa mimo nieudanej wysyłki — naprawione."
exit 0
