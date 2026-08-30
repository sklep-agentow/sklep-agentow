#!/bin/bash
# Strażnik + próba kontrolna: nieistniejący produkt oddaje 404 na GET **I NA HEAD**
# (do 0.9.26: HEAD dostawał 200, bo status ustawiał dopiero render treści).
# Mutant = stan sprzed naprawy: odpięty template_redirect. Kontrola bez wady:
# istniejący produkt 200/200, naturalne 404 WP nietknięte.
# Użycie: bash testy/produkt-404-proba-kontrolna.sh
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
PLIK=$CEL/includes/front-produkt.php
B=http://localhost:8890
BLEDY=0
wdroz_czysty() {
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
kod() { curl -s -o /dev/null -w '%{http_code}' "$2" -X "$1"; }
zmierz() {
  local etykieta="$1" g h g2 h2 n
  g=$(kod GET  "$B/sklep/produkt/nie-ma-takiego/"); h=$(kod HEAD "$B/sklep/produkt/nie-ma-takiego/")
  g2=$(kod GET "$B/sklep/produkt/chatbot-live/");   h2=$(kod HEAD "$B/sklep/produkt/chatbot-live/")
  n=$(kod HEAD "$B/nie-ma-wcale-takiej-strony/")
  echo "  [$etykieta] 404: GET=$g HEAD=$h · produkt: GET=$g2 HEAD=$h2 · naturalne404 HEAD=$n"
  echo "$g $h $g2 $h2 $n"
}
echo "== Czysty kod: 404/404, 200/200, naturalne 404 =="
wdroz_czysty
w=$(zmierz czysty); echo "$w" | head -1
set -- $(echo "$w" | tail -1)
if [ "$1" = 404 ] && [ "$2" = 404 ] && [ "$3" = 200 ] && [ "$4" = 200 ] && [ "$5" = 404 ]; then echo "  OK   czysty kod"; else echo "  BLAD czysty kod"; BLEDY=$((BLEDY+1)); fi
echo "== Krok C: PUSTA opcja strony sklepu (klasa 61) — cudza strona z ?sklepag_produkt NIE dostaje 404 =="
OPT=$(docker exec "$KONTENER" wp option get sklepag_strona_sklep --allow-root)
docker exec "$KONTENER" wp option update sklepag_strona_sklep 0 --allow-root >/dev/null
C1=$(kod HEAD "$B/uslugi/?sklepag_produkt=nie-ma")
docker exec "$KONTENER" wp option update sklepag_strona_sklep "$OPT" --allow-root >/dev/null
OPT2=$(docker exec "$KONTENER" wp option get sklepag_strona_sklep --allow-root)
echo "  [pusta opcja] HEAD /uslugi/?sklepag_produkt=... = $C1 (oczekiwane 200) · opcja przywrocona: $OPT2"
if [ "$C1" = 200 ] && [ "$OPT2" = "$OPT" ]; then echo "  OK   pusta opcja nie przejmuje cudzych stron (opcja wrocila)"; else echo "  BLAD krok C (kod=$C1, opcja=$OPT2)"; BLEDY=$((BLEDY+1)); fi
echo "== Mutant C: wycieta bramka id>0 w pomocniku — krok C ma PASC =="
docker exec "$KONTENER" sed -i 's/return \$id > 0 \&\& is_page( \$id );/return is_page( \$id );/' "$CEL/sklep-agentow.php"
docker exec "$KONTENER" wp option update sklepag_strona_sklep 0 --allow-root >/dev/null
C2=$(kod HEAD "$B/uslugi/?sklepag_produkt=nie-ma")
docker exec "$KONTENER" wp option update sklepag_strona_sklep "$OPT" --allow-root >/dev/null
if [ "$C2" = 404 ]; then echo "  OK   mutant C WIDOCZNY (cudza strona dostala 404 przy pustej opcji)"; else echo "  BLAD mutant C niewidoczny (kod=$C2)"; BLEDY=$((BLEDY+1)); fi
wdroz_czysty
echo "== Mutant: odpiety template_redirect (stan 0.9.26) — HEAD ma wrocic do 200 =="
docker exec "$KONTENER" sed -i "s/^add_action( 'template_redirect', 'sklepag_status_404_wczesnie' );/\/\/ MUTANT/" "$PLIK"
w=$(zmierz mutant); echo "$w" | head -1
set -- $(echo "$w" | tail -1)
if [ "$2" = 200 ]; then echo "  OK   mutant WIDOCZNY (HEAD=200) — strażnik odróżnia naprawę od braku"; else echo "  BLAD mutant niewidoczny (HEAD=$2)"; BLEDY=$((BLEDY+1)); fi
wdroz_czysty
echo; echo "BŁĘDY: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
