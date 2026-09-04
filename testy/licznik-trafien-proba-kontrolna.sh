#!/bin/bash
# Proba kontrolna straznikow licznika trafien. Mutant nr 1 to DOSLOWNIE stan
# sprzed naprawy: kazde liczenie czyta baze na wlasna reke, wiec koszt rosnie
# z liczba regul. Mutant nr 3 to pulapka tej wlasnie naprawy: wspolny kontekst
# modyfikowany w miejscu.
#
# Dwa rodzaje mutantow, bo strzega ich dwa rozne narzedzia:
#   - dzialanie kodu    -> testy/licznik-trafien-test.php (wp eval-file w kontenerze),
#   - miejsce wywolania -> testy/dokumentacja-zgodnosc-segregator.sh (na drzewie).
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/segregator-maila
PLIK_P=$CEL/includes/admin-probka.php
LOKALNY_A=wtyczki/segregator-maila/includes/admin.php
KOPIA=$(mktemp)
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

sprzataj() { [ -f "$KOPIA" ] && cp "$KOPIA" "$LOKALNY_A" && rm -f "$KOPIA"; }
trap sprzataj EXIT

wdroz_czysty() {
  docker cp wtyczki/segregator-maila/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/licznik-trafien-test.php "$KONTENER:/tmp/lt.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/lt.php 2>&1
}

# Mutant w kontenerze, lapany przez test dzialania.
probuj_dzialanie() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK_P" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK_P" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-45s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-55s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-52s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

# Mutant w drzewie, lapany przez skrypt zgodnosci (miejsce wywolania).
probuj_zgodnosc() {
  local opis="$1" mutacja="$2" krok="$3"
  cp "$LOKALNY_A" "$KOPIA"
  local przed po
  przed=$(md5sum "$LOKALNY_A" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(md5sum "$LOKALNY_A" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-45s\n' "$opis"; KOTWICE=$((KOTWICE+1)); cp "$KOPIA" "$LOKALNY_A"; return
  fi
  local wyjscie kod
  wyjscie=$(bash testy/dokumentacja-zgodnosc-segregator.sh 2>&1); kod=$?
  cp "$KOPIA" "$LOKALNY_A"
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-55s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | sed 's/\x1b\[[0-9;]*m//g' | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-52s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznicy licznika trafien widza podlozone bledy ==="

# 1. Stan sprzed naprawy: kontekst przychodzi, ale funkcja i tak czyta baze sama.
probuj_dzialanie "kontekst wyrzucany — kazdy wiersz odpytuje baze (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's#\$kontekst = null ) {#\$kontekst = null ) { \$kontekst = null;#' $PLIK_P" \
  "cała lista reguł z kontekstem = 0 zapytań"

# 2. Sam pomocnik dobry, ale lista regul wola go bez kontekstu — czyli
#    oszczednosc istnieje i nikt z niej nie korzysta.
probuj_zgodnosc "lista regul wola liczenie BEZ kontekstu" \
  "sed -i 's#(int) \$r->id, \$kontekst );#(int) \$r->id );#' $LOKALNY_A" \
  "wiersz listy liczy Z kontekstem"

# 3. Pulapka tej naprawy: podmiana reguly probnej robiona na WSPOLNYM
#    kontekscie zamiast na kopii — pierwszy wiersz psuje liczenie nastepnym.
probuj_dzialanie "brak klonu: podmiana zostaje we wspolnym kontekscie" \
  "docker exec $KONTENER sed -i 's|\$kopia = clone \$regula;|\$kopia = \$regula;|' $PLIK_P" \
  "reguły w kontekście NIE zostały podmienione"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   test dzialania przechodzi na czystym kodzie (EXIT=0)"
else
  echo "  BLAD test dzialania NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; ODWROTNA=$((ODWROTNA+1))
fi
if bash testy/dokumentacja-zgodnosc-segregator.sh > /dev/null 2>&1; then
  echo "  OK   skrypt zgodnosci przechodzi na czystym drzewie (EXIT=0)"
else
  echo "  BLAD skrypt zgodnosci NIE przechodzi na czystym drzewie"; ODWROTNA=$((ODWROTNA+1))
fi
echo
podsumowanie_proby "STRAZNICY WIDZA WSZYSTKIE TRZY PODLOZONE BLEDY"
