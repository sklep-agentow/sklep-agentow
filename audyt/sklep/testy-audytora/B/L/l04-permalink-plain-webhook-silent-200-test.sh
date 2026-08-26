#!/usr/bin/env bash
# ⛔ TEST NISZCZY STAN STOSU :8891 NA CZAS TRWANIA (zmienia permalink_structure).
# Przywraca sam na końcu (trap), ale jeśli przerwiesz go Ctrl+C w środku, odtwórz ręcznie:
#   docker exec -u www-data probny_wp wp --path=/var/www/html option update permalink_structure "/%postname%/"
#   docker exec -u www-data probny_wp wp --path=/var/www/html rewrite flush
#
# Pada NA KODZIE 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (sklep-agentow 0.9.13).
# Powód: includes/admin.php:26 twierdzi, że przy permalinkach "Zwykłe" webhook zwraca
# 404 (rest_no_route). W rzeczywistości: żądanie w ogóle NIE trafia do handlera wtyczki,
# WordPress oddaje stronę główną motywu z HTTP 200. Stripe traktuje 2xx jako "dostarczono"
# i NIE ponawia — płatność ginie bez śladu w wp_sklepag_zdarzenia.
#
# Kontrola w tym samym teście: to samo żądanie NA PRAWIDŁOWYCH permalinkach musi dać 400
# (zły podpis) + wpis zdarzenia webhook_zly_podpis — to dowodzi, że handler wtyczki
# normalnie DZIAŁA i że różnica jest spowodowana wyłącznie strukturą permalinków.

set -uo pipefail
BASE="http://localhost:8891"
DB_EXEC() { docker exec strona_wp_db sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb -N wp_probny -e \"$1\""; }
WP() { docker exec -u www-data probny_wp wp --path=/var/www/html "$@"; }

trap 'WP option update permalink_structure "/%postname%/" >/dev/null 2>&1; WP rewrite flush >/dev/null 2>&1' EXIT

fail=0

echo "== KONTROLA: webhook na prawidlowych permalinkach ma dac 400 + zdarzenie =="
przed=$(DB_EXEC "SELECT COALESCE(MAX(id),0) FROM wp_sklepag_zdarzenia")
kod_kontrola=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/wp-json/sklepag/v1/webhook")
po=$(DB_EXEC "SELECT COALESCE(MAX(id),0) FROM wp_sklepag_zdarzenia")
echo "HTTP kontrola=$kod_kontrola  zdarzenia przed=$przed po=$po"
if [ "$kod_kontrola" != "400" ] || [ "$po" -le "$przed" ]; then
  echo "PROBA KONTROLNA PADLA — test niemiarodajny (handler i tak nie dziala normalnie)"
  exit 2
fi

echo "== USTAWIAM permalinki na Zwykle =="
WP option update permalink_structure "" >/dev/null
WP rewrite flush >/dev/null 2>&1

przed2=$(DB_EXEC "SELECT COALESCE(MAX(id),0) FROM wp_sklepag_zdarzenia")
kod_plain=$(curl -s -o /tmp/l04-body.html -w '%{http_code}' -X POST "$BASE/wp-json/sklepag/v1/webhook")
po2=$(DB_EXEC "SELECT COALESCE(MAX(id),0) FROM wp_sklepag_zdarzenia")
tytul=$(grep -o '<title>[^<]*' /tmp/l04-body.html | head -1)

echo "HTTP na Zwyklych=$kod_plain  tytul_odpowiedzi=$tytul  zdarzenia przed=$przed2 po=$po2"

# Asercja OCZEKIWANA (naprawiony kod): odpowiedz NIE powinna byc 2xx bez wpisu zdarzenia —
# albo handler musi zadzialac (400+zdarzenie jak w kontroli), albo trasa musi dac blad
# rozpoznawalny jako blad (4xx/5xx), NIGDY cichy 200 ze strona glowna.
if [[ "$kod_plain" =~ ^2 ]] && [ "$po2" -le "$przed2" ]; then
  echo "PADA: webhook pod permalinkami Zwykle zwrocil HTTP $kod_plain (sukces dla Stripe) i NIE zostawil zadnego zdarzenia — cicha utrata platnosci."
  fail=1
else
  echo "OK: nie odtworzono cichej awarii (albo handler zadzialal, albo blad byl widoczny)."
fi

exit $fail
