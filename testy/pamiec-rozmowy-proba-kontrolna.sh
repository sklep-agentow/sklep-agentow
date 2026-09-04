#!/bin/bash
# Proba kontrolna straznika pamieci rozmowy. Mutant nr 1 to DOSLOWNIE stan
# sprzed naprawy: do modelu leci samo pytanie, bez historii.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_A=$CEL/includes/ai.php
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
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/pamiec-rozmowy-test.php "$KONTENER:/tmp/pr.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/pr.php 2>&1
}
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK_A" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK_A" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-44s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-54s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-51s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik pamieci widzi podlozone bledy ==="

probuj "do modelu leci samo pytanie (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|(array) \$historia,|array(),|' $PLIK_A" \
  "messages ma WIĘCEJ niż jeden element"

probuj "historia bez ograniczenia dlugosci (rachunek rosnie bez konca)" \
  "docker exec $KONTENER sed -i 's|\$limit  = CHATL_AI_PAMIEC_WYMIAN \* 2;|\$limit = 1000;|' $PLIK_A" \
  "historia nie rośnie bez końca"

# Mutacja MUSI zostawic poprawny SQL (ta sama liczba i kolejnosc placeholderow),
# inaczej zapytanie pada w calosci i test wywraca sie gdzie indziej — wtedy proba
# meldowalaby "straznik slepy", choc slepa bylaby sama mutacja. Zmierzone.
probuj "historia z CUDZEJ sesji (przeciek miedzy rozmowami)" \
  "docker exec $KONTENER sed -i 's|WHERE sesja = %s AND id < %d ORDER BY|WHERE (sesja = %s OR 1=1) AND id < %d ORDER BY|' $PLIK_A" \
  "wymiany z cudzej sesji nie wchodzą do historii"

probuj "brak przycinania dlugiej wiadomosci" \
  "docker exec $KONTENER sed -i 's|if ( mb_strlen( \$tresc ) > CHATL_AI_PAMIEC_ZNAKOW ) {|if ( false ) {|' $PLIK_A" \
  "wiadomość przycięta do limitu"

probuj "rozmowa zaczyna sie od bota (zly ksztalt dla API)" \
  "docker exec $KONTENER sed -i \"s|while ( \\\$historia \&\& 'assistant' === \\\$historia\\[0\\]\\['role'\\] ) {|while ( false ) {|\" $PLIK_A" \
  "historia zaczyna się od gościa"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; ODWROTNA=$((ODWROTNA+1))
fi
echo
podsumowanie_proby "STRAZNIK WIDZI WSZYSTKIE PIEC PODLOZONYCH BLEDOW"
