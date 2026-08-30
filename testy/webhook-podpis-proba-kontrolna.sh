#!/bin/bash
# Próba kontrolna strażnika podpisu webhooka (zasada D14). Mutant 1 = weryfikacja
# przyjmuje wszystko (hash_equals zastąpione przez true) — klasa „fałszywe opłacono”.
# Mutant 2 = brak sprawdzenia świeżości znacznika czasu (replay starego webhooka).
# Użycie: bash testy/webhook-podpis-proba-kontrolna.sh   (na końcu wdraża czysty kod)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
PLIK=$CEL/includes/stripe.php
BLEDY=0
wdroz_czysty() {
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/webhook-podpis-test.php "$KONTENER:/tmp/wh.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/wh.php 2>&1
}
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then printf '  BLAD mutacja NIE weszla: %s\n' "$opis"; BLEDY=$((BLEDY+1)); return; fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %s (EXIT=%d)\n' "$opis" "$kod"
  else
    printf '  BLAD straznik NIE ZLAPAL: %s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}
echo "== Mutant 1: przyjmuje kazdy podpis =="
probuj "hash_equals -> true" "docker exec $KONTENER sed -i 's/if ( hash_equals( \$oczekiwany, \$v1 ) ) {/if ( true ) {/' $PLIK" "B\\."
echo "== Mutant 2: brak progu swiezosci (replay) =="
probuj "wylaczona tolerancja czasu" "docker exec $KONTENER sed -i 's/abs( time() - (int) \$t ) > \$tolerancja/false/' $PLIK" "D\\."
echo "== Czysty kod =="
wdroz_czysty
wyjscie=$(odpal); kod=$?
if [ "$kod" -eq 0 ]; then echo "  OK   czysty kod EXIT=0"; else echo "  BLAD czysty kod EXIT=$kod"; echo "$wyjscie" | tail -6; BLEDY=$((BLEDY+1)); fi
echo; echo "BŁĘDY PRÓBY KONTROLNEJ: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
