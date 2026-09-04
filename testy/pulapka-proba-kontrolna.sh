#!/bin/bash
# Proba kontrolna straznika pulapki na boty (zasada CLAUDE.md: test ma byc
# SKALIBROWANY — pokaz, ze pada na kodzie sprzed naprawy).
#
# Mutant 1 to DOSLOWNIE stan sprzed zmiany: pulapki nie ma wcale. Mutanty 2-4
# zdejmuja po jednym z TRZECH WARUNKOW, ktore czynia decyzje "zapis z flaga"
# bezpieczna — bo to one, a nie samo wykrywanie, decyduja, czy ta pozycja
# pomaga wlascicielowi, czy mu szkodzi.
#
# Uzycie: bash testy/pulapka-proba-kontrolna.sh     (z katalogu repo)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_F=$CEL/includes/front.php
PLIK_JS=$CEL/assets/widget.js
PLIK_CSS=$CEL/assets/widget.css
BLEDY=0
KOTWICE=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/pulapka-test.php "$KONTENER:/tmp/pulapka-test.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/pulapka-test.php 2>&1
}

# probuj "opis" "plik" "wyrazenie-sed" "fragment-kroku-ktory-ma-padac"
probuj() {
  local opis="$1" plik="$2" mutacja="$3" krok="$4"
  wdroz_czysty
  local przed po wyjscie kod
  przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  docker exec "$KONTENER" sed -i "$mutacja" "$plik"
  po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    # ⛔ Warunek, ktory ratuje probe po refaktorze: kotwica sed potrafi zgnic
    #    przy zmianie nazwy zmiennej i wtedy proba swieci na zielono, NIC nie
    #    sprawdzajac. Zdarzylo sie to juz dwa razy w tym repo.
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-40s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-54s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-50s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik pulapki widzi podlozone bledy ==="

# 1. STAN SPRZED ZMIANY: wykrywanie zawsze mowi "czysto". Tak wygladal bot
#    wczoraj — piec adresow wystarczalo, zeby wypalic dzienny budzet AI.
probuj "pulapka nie lapie NICZEGO (stan sprzed zmiany)" "$PLIK_F" \
  "s|if ( ! empty( \$_POST\[ CHATL_PULAPKA_POLE \] ) ) {|if ( false ) {|" \
  "wypelnione ukryte pole = zlapane"

# 2. Prog czasu wylaczony — zostaje samo ukryte pole, czyli polowa sita.
probuj "prog czasu wylaczony (zostaje samo ukryte pole)" "$PLIK_F" \
  "s|return \$wiek < CHATL_PULAPKA_SEKUND ? 'czas' : '';|return '';|" \
  "zadanie tuz po wydaniu sesji = zlapane"

# 3. WARUNEK 1 zdjety: zlapane zgloszenie znowu wysyla maila do wlasciciela.
#    To byl GLOWNY powod postawienia tej pulapki — ochrona przed mail-bomba.
probuj "zlapane zgloszenie znowu wysyla maila" "$PLIK_F" \
  "s|if ( '' === \$pulapka \&\& \$ust\['powiadomienia'\]|if ( \$ust['powiadomienia']|" \
  "WARUNEK 1"

# 4. WARUNEK 3 zdjety: flaga nie jest stawiana, wiec zlapane zgloszenie wpada
#    na zwykla liste. Wlasciciel dostaje spam tam, gdzie szuka klientow.
probuj "flaga podejrzane nie jest stawiana" "$PLIK_F" \
  "s|'podejrzane' => '' === \$pulapka ? 0 : 1,|'podejrzane' => 0,|" \
  "ma flage podejrzane = 1"

# 5. Licznik przestaje liczyc — pulapka staje sie niesprawdzalna z panelu,
#    a FALSZYWY ALARM przestaje byc widoczny dla wlasciciela.
probuj "licznik pulapki przestaje liczyc" "$PLIK_F" \
  "s|'ile'      => \$ile,|'ile'      => 0,|" \
  "licznik zlicza kazde zadzialanie"

# 6. Sufit na zapisy zlapanych zdjety — pulapka sama staje sie droga do
#    zalania tabeli zgloszen, bo zlapane LADUJA w bazie.
probuj "sufit na zapisy zlapanych zdjety" "$PLIK_F" \
  "s|chatl_rate_limit( 'pulapka', CHATL_LIMIT_PULAPKA )|true|" \
  "sufit na zlapane chroni tabele"

# 7. Widget przestaje wysylac pole — serwer nigdy go nie zobaczy, wiec ukryte
#    pole w DOM jest ozdoba. Klasa "funkcja jest, ale nie jest podlaczona".
probuj "widget przestaje wysylac pole-pulapke" "$PLIK_JS" \
  "s|body.append('chatl_pole_dodatkowe'|body.append('chatl_pole_nieuzywane'|" \
  "i wysyla je w kazdym zadaniu"

# 8. Pole chowane przez display:none — czesc prostych botow pomija pola ukryte
#    tak wprost, wiec sito przestaje lapac wlasnie te, dla ktorych powstalo.
probuj "pole chowane przez display:none zamiast wyniesienia poza kadr" "$PLIK_CSS" \
  "s|left: -9999px !important;|display: none;|" \
  "CSS wynosi pole poza kadr"

# 9. Prog czasu WRACA na formularz kontaktu. To byl prawdziwy falszywy alarm,
#    zmierzony testem przegladarkowym: gosc, ktory otworzy czat i od razu klika
#    "Zostaw wiadomosc", wysyla szybciej niz w dwie sekundy — i wpada we wlasna
#    pulapke. Cena: utracony kontakt, czyli to, po co ten produkt istnieje.
probuj "prog czasu wraca na formularz kontaktu (falszywy alarm)" "$PLIK_F" \
  "s|chatl_pulapka_zlapala( \$sesja_wstepna, false )|chatl_pulapka_zlapala( \$sesja_wstepna )|" \
  "prog czasu go nie zlapal"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"
  BLEDY=$((BLEDY+1))
fi

echo
if [ "$KOTWICE" -gt 0 ]; then
  echo "PROBA NIC NIE ZMIERZYLA W $KOTWICE PRZYPADKACH — kotwica sed zgnila po refaktorze."
  echo "  To wada PRZYRZADU, nie straznika: zanim oskarzysz produkt, uruchom straznika BEZPOSREDNIO."
fi
if [ "$BLEDY" -eq 0 ] && [ "$KOTWICE" -eq 0 ]; then
  echo "STRAZNIK WIDZI WSZYSTKIE 9 PODLOZONYCH BLEDOW"
elif [ "$BLEDY" -gt 0 ]; then
  echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"
fi
exit $(( BLEDY + KOTWICE > 0 ))
