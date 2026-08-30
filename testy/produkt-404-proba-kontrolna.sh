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
echo "== Mutant: odpiety template_redirect (stan 0.9.26) — HEAD ma wrocic do 200 =="
docker exec "$KONTENER" sed -i "s/^add_action( 'template_redirect', 'sklepag_status_404_wczesnie' );/\/\/ MUTANT/" "$PLIK"
w=$(zmierz mutant); echo "$w" | head -1
set -- $(echo "$w" | tail -1)
if [ "$2" = 200 ]; then echo "  OK   mutant WIDOCZNY (HEAD=200) — strażnik odróżnia naprawę od braku"; else echo "  BLAD mutant niewidoczny (HEAD=$2)"; BLEDY=$((BLEDY+1)); fi
wdroz_czysty
echo; echo "BŁĘDY: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
