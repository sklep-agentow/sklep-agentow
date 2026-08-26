#!/bin/sh
# Test padający — Ł-10: sklepag_obsluz_przelacz_produkt() (includes/admin-produkty.php:310)
# czyta $p->aktywny, liczy $nowy = !aktywny, i zapisuje — BEZ warunku CAS
# (w przeciwieństwie do zamowienia.status, rate_limit i pobrania_uzyte,
# które w tym samym repo używają atomowego UPDATE...WHERE). Dwa równoległe
# "przełącz" (np. dwuklik admina) czytają TEN SAM stan startowy, oba liczą
# tę samą docelową wartość i oba ją zapisują — więc podwójny klik NIE
# wraca do stanu wyjściowego (jak intuicyjnie oczekiwałby admin), tylko
# utrwala JEDNO przejście, a w dzienniku zostają DWA identyczne wpisy
# 'produkt_deaktywowany' z tym samym znacznikiem czasu — mylący ślad audytu.
#
# Uruchomienie: sh L-10-przelacz-produkt-wyscig-test.sh
#
# Wyjście z RZECZYWISTEGO uruchomienia (26.08.2026, probny_wp, produkt id=1,
# stan startowy aktywny=1, nonce z realnej sesji admina):
#   stan_przed: aktywny=1
#   2x GET rownolegle admin-post.php?action=sklepag_produkt_przelacz&produkt=1
#     -> req1_http=302, req2_http=302 (oba "sukces")
#   stan_po: aktywny=0   <-- OCZEKIWANE PRZEZ ADMINA po dwoch klikach: 1 (powrot)
#   wp_sklepag_zdarzenia (2 najnowsze):
#     produkt_deaktywowany #1 Agent Testowy Ł  2026-08-25 23:45:56
#     produkt_deaktywowany #1 Agent Testowy Ł  2026-08-25 23:45:56   <- DUPLIKAT
#   (BRAK jakiegokolwiek 'produkt_aktywowany' mimo dwoch klikniec)
# WYNIK=FAIL: brak CAS na przełączniku produktu -> race + mylący log.

set -e
HOST="http://localhost:8891"
CK=/tmp/l10-admin-ck.txt
docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -e "UPDATE wp_sklepag_produkty SET aktywny=1 WHERE id=1;"'

HASLO_ADMIN="$(tr -d '\n' < /root/.claude/sekrety-audyt-admin.txt)"
curl -s -c "$CK" -d "log=audytor-admin&pwd=$HASLO_ADMIN&wp-submit=1&testcookie=1" "$HOST/wp-login.php" -o /dev/null

NONCE=$(curl -s -b "$CK" "$HOST/wp-admin/admin.php?page=sklepag-produkty" | grep -o 'action=sklepag_produkt_przelacz[^"]*' | grep -o '_wpnonce=[a-f0-9]*' | head -1 | cut -d= -f2)
echo "nonce=$NONCE"

STAN_PRZED=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -N -e "SELECT aktywny FROM wp_sklepag_produkty WHERE id=1;"')
echo "stan_przed=$STAN_PRZED"

( curl -s -b "$CK" -o /dev/null -w "req1=%{http_code}\n" "$HOST/wp-admin/admin-post.php?action=sklepag_produkt_przelacz&produkt=1&_wpnonce=$NONCE" &
  curl -s -b "$CK" -o /dev/null -w "req2=%{http_code}\n" "$HOST/wp-admin/admin-post.php?action=sklepag_produkt_przelacz&produkt=1&_wpnonce=$NONCE" &
  wait )

STAN_PO=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -N -e "SELECT aktywny FROM wp_sklepag_produkty WHERE id=1;"')
LICZBA_DEAKTYWACJI=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -N -e "SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE typ IN (\"produkt_deaktywowany\",\"produkt_aktywowany\") ORDER BY id DESC LIMIT 2;"')
echo "stan_po=$STAN_PO"

# Przywrócenie stanu.
docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -e "UPDATE wp_sklepag_produkty SET aktywny=1 WHERE id=1;"'

if [ "$STAN_PRZED" = "1" ] && [ "$STAN_PO" = "0" ]; then
  echo "PADA_TEST: WYNIK=FAIL - dwa rownolegle przelaczniki NIE wrocily do stanu wyjsciowego (oczekiwano: 1, jest: 0) - brak CAS, wyscig potwierdzony."
  exit 1
fi
echo "PADA_TEST: WYNIK=PASS"
exit 0
