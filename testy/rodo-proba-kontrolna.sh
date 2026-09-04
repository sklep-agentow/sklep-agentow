#!/bin/bash
# Proba kontrolna straznika RODO (zasada D14: kontrola, ktora nie znajdzie
# PODLOZONEGO bledu, jest slepa).
#
# Mutant nr 1 to DOSLOWNIE stan sprzed zmiany: wtyczka nie jest wpieta w kreator
# WordPressa. Mutant nr 4 podklada blad NAJGROZNIEJSZY z calej tej funkcji —
# eksport z dziura, ktorego nie zauwazy ani wlasciciel, ani osoba skladajaca wniosek.
#
# Uzycie: bash testy/rodo-proba-kontrolna.sh
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK=$CEL/includes/rodo.php
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
PROB=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/rodo-test.php "$KONTENER:/tmp/rodo.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/rodo.php 2>&1
}

probuj() { # probuj "opis" "mutacja" "krok, ktory ma padac"
  PROB=$((PROB+1))
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po wynik kod
  przed=$(docker exec "$KONTENER" md5sum "$PLIK" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-42s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  if ! docker exec "$KONTENER" php -l "$PLIK" >/dev/null 2>&1; then
    printf '  BLAD mutacja rozwalila skladnie: %-43s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  wynik=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wynik" | grep -q "BLAD $krok"; then
    printf '  OK   straznik PADL na: %-54s (EXIT=%d)\n' "$opis" "$kod"
    printf '           BLAD %s\n' "$krok"
  else
    printf '  BLAD straznik NIE zlapal: %-51s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik RODO widzi podlozone bledy ==="

# 1. STAN SPRZED ZMIANY: wtyczka nie jest wpieta w kreator WordPressa.
probuj "eksporter niezarejestrowany (stan sprzed zmiany)" \
  "docker exec $KONTENER sed -i \"s|add_filter( 'wp_privacy_personal_data_exporters', 'chatl_rejestruj_eksporter' );|// zdjete przez probe|\" $PLIK" \
  "eksporter zarejestrowany w kreatorze"

# 2. Kasownik niezarejestrowany — wlasciciel moze wydac dane, ale nie skasowac.
probuj "kasownik niezarejestrowany" \
  "docker exec $KONTENER sed -i \"s|add_filter( 'wp_privacy_personal_data_erasers', 'chatl_rejestruj_kasownik' );|// zdjete przez probe|\" $PLIK" \
  "kasownik zarejestrowany w kreatorze"

# 3. NAJGORSZE, co moze zrobic kasownik: skasowac dane NIE TEJ osoby.
probuj "kasownik kasuje NIE TE osobe (odwrocony warunek)" \
  "docker exec $KONTENER sed -i \"s|WHERE email = %s\\\", \\\$email|WHERE email <> %s\\\", \\\$email|\" $PLIK" \
  "zgloszenie osoby B NIETKNIETE"

# 4. Eksport z DZIURA: przesuniecie rozmow liczone wzgledem strony zamiast
#    wzgledem liczby zgloszen. Przy dokladnie pelnej stronie zgloszen znika
#    pierwsza porcja rozmow — i nikt tego nie zauwazy.
probuj "eksport gubi wiersze przy pelnej stronie zgloszen" \
  "docker exec $KONTENER sed -i 's|\$offset_rozmow = max( 0, \$offset - \$ile_zgloszen );|\$offset_rozmow = \$offset;|' $PLIK" \
  "zaden wiersz nie wypadl ze stronicowania"

# 5. Eksport pomija rozmowy — wniosek dostaje same zgloszenia.
#    UWAGA: kotwica tego mutanta juz raz zgnila po zmianie nazwy zmiennej
#    ($miejsc -> $wolne_sloty). Proba kontrolna zlapala to sama, meldujac
#    "mutacja NIE weszla do pliku" — po to jest ten warunek w probuj().
probuj "eksport pomija historie rozmow" \
  "docker exec $KONTENER sed -i 's|if ( \$wolne_sloty > 0 \&\& \$sesje ) {|if ( false ) {|' $PLIK" \
  "eksport zawiera pytanie goscia z rozmowy"

# 6. Kasownik kasuje zgloszenia, ale zostawia rozmowy — po ich skasowaniu
#    nie ma juz czym powiazac rozmow z adresem, wiec zostana na zawsze.
probuj "kasownik zostawia rozmowy (sierota nie do powiazania)" \
  "docker exec $KONTENER sed -i 's|DELETE FROM {\$tr} WHERE sesja IN|DELETE FROM {\$tr} WHERE 1=0 AND sesja IN|' $PLIK" \
  "rozmowa osoby A skasowana"

# 7. Komunikat kasownika wraca do trybu BEZWARUNKOWEGO: pisze o usunieciu takze
#    wtedy, gdy nie znikl ani jeden wiersz. Wlasciciel odpowiada tym tekstem
#    osobie skladajacej wniosek RODO, wiec potwierdza kasowanie, ktorego nie bylo.
#    To ta sama klasa bledu co falszywe "Mail zapisany".
probuj "komunikat potwierdza kasowanie, ktorego nie bylo" \
  "docker exec $KONTENER sed -i 's|if ( 0 === \$usuniete ) {|if ( false ) {|' $PLIK" \
  "komunikat NIE potwierdza kasowania, ktorego nie bylo"

# 8. Sesja znowu traktowana jako wlasnosc JEDNEGO adresu. To najgrozniejszy blad
#    w tym module i dziala w obie strony: eksport WYDAJE wnioskodawcy tresc
#    rozmowy drugiej osoby, a kasowanie usuwa jej dane na wniosek osoby trzeciej.
probuj "sesja wspoldzielona liczona jako wlasna (dane osoby trzeciej)" \
  "docker exec $KONTENER sed -i 's|array_values( array_diff( \$sesje, \$obce ) )|\$sesje|' $PLIK" \
  "EKSPORT nie wydaje tresci rozmowy dwoch osob"

# 9. Trwaly slad "ta sesja byla wspoldzielona" przestaje byc czytany. Wykrywanie
#    wraca do BIEZACEGO stanu tabeli zgloszen — czyli dziala tylko dla wnioskow
#    rozpatrywanych ROWNOCZESNIE. Kreator obsluguje je po kolei, wiec wniosek
#    osoby B zlozony PO skasowaniu danych osoby A dostalby cala rozmowe.
probuj "slad wspoldzielenia nie przezywa kasowania (wnioski po kolei)" \
  "docker exec $KONTENER sed -i 's|array_intersect( \$sesje, chatl_rodo_slad_wspolnych() )|array()|' $PLIK" \
  "po skasowaniu danych A sesja NADAL jest wspoldzielona dla B"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
wynik=$(odpal); kod=$?
if [ "$kod" -eq 0 ]; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; ODWROTNA=$((ODWROTNA+1))
  echo "$wynik" | grep BLAD | head -5
fi

echo
podsumowanie_proby "STRAZNIK WIDZI WSZYSTKIE $PROB PODLOZONE BLEDY"
