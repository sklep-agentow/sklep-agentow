#!/bin/bash
# Proba kontrolna straznika przejecia rozmowy (zasada D14: kontrola, ktora nie
# znajdzie PODLOZONEGO bledu, jest slepa).
#
# Mutant nr 1 to DOSLOWNIE stan sprzed tej zmiany: bot odpowiada zawsze, takze
# wtedy, gdy rozmowe prowadzi juz czlowiek — czyli zachowanie, ktore Crisp ma
# u siebie udokumentowane, a ktore w tym zadaniu jest bledem.
#
# Uzycie: bash testy/przejecie-proba-kontrolna.sh
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_P=$CEL/includes/przejecie.php
PLIK_F=$CEL/includes/front.php
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
  docker cp testy/przejecie-test.php "$KONTENER:/tmp/pj.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/pj.php 2>&1
}

probuj() { # probuj "opis" "mutacja" "krok, ktory ma padac" "plik"
  PROB=$((PROB+1))
  local opis="$1" mutacja="$2" krok="$3" plik="$4"
  wdroz_czysty
  local przed po wynik kod
  przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-40s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  if ! docker exec "$KONTENER" php -l "$plik" >/dev/null 2>&1; then
    printf '  BLAD mutacja rozwalila skladnie: %-41s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  wynik=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wynik" | grep -q "BLAD $krok"; then
    printf '  OK   straznik PADL na: %-52s (EXIT=%d)\n' "$opis" "$kod"
    printf '           BLAD %s\n' "$krok"
  else
    printf '  BLAD straznik NIE zlapal: %-49s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik przejecia widzi podlozone bledy ==="

# 1. STAN SPRZED ZMIANY: bot odpowiada zawsze, takze przy przejetej rozmowie.
probuj "bot NIE milczy po przejeciu (stan sprzed zmiany)" \
  "docker exec $KONTENER sed -i 's|if ( chatl_bot_milczy( \$sesja ) ) {|if ( false ) {|' $PLIK_F" \
  "endpoint melduje, ze rozmowe przejeto" "$PLIK_F"

# 2. Gosc nie dowiaduje sie, ze rozmawia z czlowiekiem — cicha zmiana rozmowcy.
probuj "brak komunikatu dla goscia przy przejeciu" \
  "docker exec $KONTENER sed -i \"s|chatl_komunikat_systemowy( \\\$sesja, 'Do rozmowy|// zdjete przez probe (\\\$sesja, 'Do rozmowy|\" $PLIK_P" \
  "gosc dostaje KOMUNIKAT o czlowieku" "$PLIK_P"

# 3. Publiczny punkt przestaje sprawdzac DRUGI skladnik — sam identyfikator
#    sesji wystarczy, zeby przeczytac cudza rozmowe.
probuj "punkt REST nie sprawdza ciastka sesji" \
  "docker exec $KONTENER sed -i 's|if ( null !== \$w \&\& ! chatl_sesja_uprawniona( \$sesja, \$w ) ) {|if ( false ) {|' $PLIK_P" \
  "napastnik NIE dostaje ani jednej wiadomosci" "$PLIK_P"

# 4. Rozmowa nie wraca do bota po ciszy operatora — gosc zostaje z martwym czatem.
probuj "cisza operatora NIE oddaje rozmowy botowi" \
  "docker exec $KONTENER sed -i 's|if ( ! is_array( \$w ) \|\| .czlowiek. !== \$w\[.stan.\] ) {|if ( true ) {|' $PLIK_P" \
  "cisza operatora oddaje rozmowe botowi" "$PLIK_P"

# 5. Identyfikator sesji znowu slaby i krotki (kwestia bezpieczenstwa, nie estetyki).
probuj "slaby identyfikator sesji" \
  "docker exec $KONTENER sed -i \"s|\\\$sesja  = wp_generate_password( 32, false );|\\\$sesja  = 'chatl' . time();|\" $PLIK_P" \
  "sesja ma 32 znaki" "$PLIK_P"

# 6. Bot nie odzyskuje glosu po zakonczeniu przejecia (drugi kierunek).
probuj "bot NIE odzywa sie po zakonczeniu przejecia" \
  "docker exec $KONTENER sed -i \"s|return 'czlowiek' === \\\$w\\['stan'\\] ? 'czlowiek' : 'bot';|return 'czlowiek';|\" $PLIK_P" \
  "stan wrocil do bota" "$PLIK_P"

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
