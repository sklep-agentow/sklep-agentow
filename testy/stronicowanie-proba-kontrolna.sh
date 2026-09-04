#!/bin/bash
# Proba kontrolna straznika stronicowania (CLAUDE.md: test ma byc SKALIBROWANY).
#
# Mutant 1 to DOSLOWNIE stan sprzed zmiany: twardy LIMIT bez przesuniecia, czyli
# lista urywa sie i nie ma strony drugiej. Mutanty 2-3 podkladaja bledy, ktore
# sa GROZNIEJSZE od braku stronicowania, bo wygladaja na dzialajace: przesuniecie
# o jeden (wiersz ginie miedzy stronami) i zgubiony filtr (strona druga pokazuje
# inne dane niz pierwsza).
#
# Uzycie: bash testy/stronicowanie-proba-kontrolna.sh
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_A=$CEL/includes/admin.php
PLIK_S=$CEL/includes/statystyki.php
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
  docker cp testy/stronicowanie-test.php "$KONTENER:/tmp/stronicowanie-test.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/stronicowanie-test.php 2>&1
}

probuj() { # probuj "opis" "plik" "sed" "krok, ktory ma padac"
  local opis="$1" plik="$2" mutacja="$3" krok="$4"
  wdroz_czysty
  local przed po wyjscie kod
  przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  docker exec "$KONTENER" sed -i "$mutacja" "$plik"
  po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-40s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-52s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-48s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik stronicowania widzi podlozone bledy ==="

# 1. STAN SPRZED ZMIANY: twardy limit bez przesuniecia. Lista urywa sie na 200
#    i nie ma jak dojsc do reszty — dokladnie wada zgloszona w kolejce.
probuj "lista zgloszen wraca do twardego LIMIT 200 (stan sprzed zmiany)" "$PLIK_A" \
  "s|ORDER BY id DESC LIMIT %d OFFSET %d\", \$flaga, CHATL_NA_STRONE_ZGLOSZEN, ( \$strona - 1 ) \* CHATL_NA_STRONE_ZGLOSZEN|ORDER BY id DESC LIMIT 200\", \$flaga|" \
  "lista zgloszen ma OFFSET"

# 2. To samo dla rozmow.
probuj "lista rozmow wraca do twardego LIMIT 100" "$PLIK_A" \
  "s|ORDER BY koniec DESC LIMIT %d OFFSET %d|ORDER BY koniec DESC LIMIT 100|" \
  "lista rozmow ma OFFSET"

# 3. Liczba stron rozmow liczona z WIERSZY zamiast z SESJI — lista pokazuje
#    rozmowy, wiec wyszlyby puste strony na koncu i mylaca liczba pozycji.
probuj "rozmowy liczone po wierszach zamiast po sesjach" "$PLIK_A" \
  "s|SELECT COUNT(DISTINCT sesja) FROM|SELECT COUNT(*) FROM|" \
  "lista rozmow liczy DISTINCT sesja"

# 4. Raport luk przestaje sie przesuwac — strona druga pokazuje to samo co pierwsza.
probuj "raport luk zawsze od poczatku (strona 2 = strona 1)" "$PLIK_S" \
  "s|array_slice( \$luki\['grupy'\], \$luki_offset, CHATL_LUKI_POKAZ )|array_slice( \$luki['grupy'], 0, CHATL_LUKI_POKAZ )|" \
  "raport luk przesuwa sie po grupach"

# 5. Numer strony spoza zakresu przestaje byc przycinany — wejscie z zakladki po
#    skasowaniu wpisow daje pusta liste bez zadnego wyjasnienia.
probuj "numer strony nie jest przycinany do zakresu" "$PLIK_A" \
  "s|\$strona           = min( \$strona, \$stron );|// przyciecie zdjete|" \
  "kod przycina numer strony do ostatniej istniejacej"

# 6. Sufit wejsciowy raportu luk zdjety przy okazji stronicowania. To CICHA
#    zmiana zachowania: panel obiecuje w komunikacie limit 2000, a kod robilby co innego.
probuj "sufit wejsciowy raportu luk zdjety po cichu" "$PLIK_S" \
  "s|const CHATL_LUKI_LIMIT = 2000;|const CHATL_LUKI_LIMIT = 999999;|" \
  "sufit wejsciowy nadal wynosi 2000"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"
  ODWROTNA=$((ODWROTNA+1))
fi

echo
podsumowanie_proby "STRAZNIK WIDZI WSZYSTKIE 6 PODLOZONYCH BLEDOW"
