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
KOTWICE=0
ODWROTNA=0
# ⛔ KLASA 138: TRZY rozne rzeczy = TRZY liczniki. Wspolny licznik kaze skryptowi
#    oglosic "STRAZNIK SLEPY", czyli orzec o PRODUKCIE rzecz, ktorej NIE ZMIERZYL.
#      BLEDY    straznik nie zlapal mutanta  -> EXIT=1, wada produktu
#      KOTWICE  kotwica sed zgnila           -> EXIT=2, proba nic nie zmierzyla
#      ODWROTNA czysty kod nie przechodzi    -> EXIT=2, proba nic nie znaczy
#    EXIT=2 to konwencja przelotu: "nie zmierzylam" (NIE porazka produktu).
podsumowanie_proby() {           # $1 = komunikat, gdy wszystko zmierzone i zielone
  # ⛔ KOLEJNOSC MA ZNACZENIE. Padnieta kontrola odwrotna UNIEWAZNIA caly przebieg:
  #    straznik pada wtedy ZAWSZE, wiec „nie zlapal mutanta" nic nie znaczy i licznik
  #    BLEDY jest smieciem. Ogloszenie „STRAZNIK SLEPY NA N" byloby wtedy orzeczeniem
  #    o PRODUKCIE na podstawie pomiaru, ktory sie nie odbyl. Najczestsza przyczyna to
  #    BRAK WARUNKU WSTEPNEGO srodowiska (np. swiezy worktree bez `vendor/` albo
  #    `node_modules/`), a to wada SRODOWISKA, nie kodu klienta.
  if [ "$ODWROTNA" -gt 0 ]; then
    echo "KONTROLA ODWROTNA PADLA ($ODWROTNA) — czysty kod NIE przechodzi, wiec proba NIC nie znaczy."
    echo "  Licznik BLEDY=$BLEDY jest NIEWIARYGODNY i NIE jest werdyktem o produkcie."
    echo "  Sprawdz warunki wstepne (zaleznosci, stos, dane logowania), potem powtorz."
    exit 2
  fi
  if [ "$KOTWICE" -gt 0 ]; then
    echo "PROBA NIC NIE ZMIERZYLA W $KOTWICE PRZYPADKACH — kotwica sed zgnila po refaktorze."
    echo "  To wada PRZYRZADU, nie straznika: zanim oskarzysz produkt, uruchom straznika BEZPOSREDNIO."
    exit 2
  fi
  if [ "$BLEDY" -gt 0 ]; then
    echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"
    exit 1
  fi
  echo "$1"
  exit 0
}
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
  if [ "$przed" = "$po" ]; then printf '  BLAD mutacja NIE weszla (ZGNILA KOTWICA sed): %s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return; fi
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
if [ "$kod" -eq 0 ]; then echo "  OK   czysty kod EXIT=0"; else echo "  BLAD czysty kod EXIT=$kod"; echo "$wyjscie" | tail -6; ODWROTNA=$((ODWROTNA+1)); fi
podsumowanie_proby "PROBA KONTROLNA ZALICZONA — kazdy podlozony blad zlapany"
