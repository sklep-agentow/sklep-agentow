#!/bin/bash
# Proba kontrolna testu importu CSV (zasada D14: test bez kalibracji nic nie
# znaczy — trzeba pokazac, ze w ogole POTRAFI paść).
#
# Podklada po jednym celowym bledzie w KAZDEJ rzeczy, ktora ten modul obiecuje,
# i sprawdza, ze testy/import-csv-test.php za kazdym razem PADA na wlasciwym
# kroku. Na koniec sprawdza, ze czysty kod przechodzi — inaczej "pada zawsze"
# tez byloby bezuzyteczne.
#
# Uzycie: bash testy/import-csv-proba-kontrolna.sh     (z katalogu repo)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_I=$CEL/includes/import.php
BLEDY=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/import-csv-test.php "$KONTENER:/tmp/import-csv-test.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/import-csv-test.php 2>&1
}

# probuj "opis" "sed-podkladajacy-blad" "fragment-kroku-ktory-ma-paść"
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  docker exec "$KONTENER" sed -i "$mutacja" "$PLIK_I"
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-52s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-48s (EXIT=%d)\n' "$opis" "$kod"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik importu widzi podlozone bledy ==="

# 1. STAN SPRZED ZMIANY: modulu importu nie ma w ogole. Tak wygladala wtyczka
#    przed tym PR-em — wlasciciel z lista 40 pytan w Excelu przepisywal je recznie.
wdroz_czysty
docker exec "$KONTENER" rm -f "$PLIK_I"
wyjscie=$(odpal); kod=$?
if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "Fatal error"; then
  printf '  OK   straznik PADL na: %-52s (EXIT=%d)\n' "modulu importu NIE MA (stan sprzed zmiany)" "$kod"
else
  printf '  BLAD straznik NIE zlapal: %-48s (EXIT=%d)\n' "brak modulu importu" "$kod"
  BLEDY=$((BLEDY+1))
fi

# 2. Zapis PRZESTAJE brac to, co pokazal podglad — liczy slowa kluczowe od nowa.
#    To jest dokladnie ta awaria, przed ktora chroni podglad: ekran pokazuje
#    jedno, do bazy idzie drugie, a wlasciciel niczego nie zauwazy.
probuj "zapis liczy slowa OD NOWA zamiast wziac podglad" \
  "s/\$slowa = isset( \$poprawione\[ \$indeks \] ).*\$wiersz\['slowa'\];/\$slowa = chatl_sugerowane_slowa( \$wiersz['pytanie'] );/" \
  "slowa POPRAWIONE"

# 3. Wykrywanie duplikatow wylaczone — import zaczyna dublowac wpisy, ktore
#    juz sa w bazie (bot dostaje dwie odpowiedzi na to samo pytanie).
probuj "duplikaty wobec bazy przestaja byc wykrywane" \
  "s/if ( isset( \$istniejace\[ \$znormalizowane \] ) ) {/if ( false ) {/" \
  "duplikat wobec bazy rozpoznany"

# 4. Ostrzezenie o wpisie, w ktory bot NIGDY nie trafi, zdjete. Wpis wchodzi
#    do bazy cicho i martwy — wlasciciel dowie sie tygodnie pozniej z raportu luk.
probuj "ostrzezenie o martwym wpisie zdjete" \
  "s/if ( ! \$warianty ) {/if ( false ) {/" \
  "ostrzezenie \"bot NIGDY nie trafi\""

# 5. Separator na sztywno przecinek — plik z POLSKIEGO Excela (srednik)
#    przestaje sie wczytywac, a to najczestszy plik u klienta w Polsce.
probuj "separator zaszyty na przecinek (polski Excel odpada)" \
  "s/return substr_count( \$naglowek, ';' ) > substr_count( \$naglowek, ',' ) ? ';' : ',';/return ',';/" \
  "plik ze srednikiem"

# 6. Konwersja kodowania wraca na mb_convert_encoding. To NIE jest teoria:
#    mbstring nie zna CP1250, wiec plik z polskiego Excela konczy sie fatalem.
#    Ten mutant odtwarza blad, ktory ten straznik naprawde wykryl w kodzie.
probuj "konwersja przez mb_convert_encoding (mbstring nie zna CP1250)" \
  "s/\$przekonwertowane = iconv( 'CP1250', 'UTF-8\/\/IGNORE', \$tresc );/\$przekonwertowane = mb_convert_encoding( \$tresc, 'UTF-8', 'Windows-1250' );/" \
  "windows-1250\|Fatal"

# 7. BOM przestaje byc zdejmowany. UWAGA: pierwsza wersja tego mutanta NIE
#    zostala zlapana i to bylo cenniejsze niz zielony wynik — pokazalo, ze
#    naglowek przezywa BOM takze bez tej funkcji (normalizator nazw kolumn
#    zamienia jej bajty na "_"), a komentarz w kodzie twierdzil inaczej.
#    Komentarz poprawiono, a kontrakt funkcji przypieto w strazniku wprost.
probuj "BOM z wlasnego eksportu nie jest zdejmowany" \
  "s/if ( 0 === strncmp( \$tresc, \"\\\\xEF\\\\xBB\\\\xBF\", 3 ) ) {/if ( false ) {/" \
  "chatl_import_bez_bom zdejmuje BOM"

# 8. Apostrof neutralizacji formul zostaje w tresci — po kazdym okrazeniu
#    eksport->import odpowiedz dostaje kolejny apostrof.
probuj "apostrof z neutralizacji formul nie jest zdejmowany" \
  "s/\t\treturn substr( \$wartosc, 1 );/\t\treturn \$wartosc;/" \
  "apostrof neutralizacji formul zdjety\|tresc wraca IDENTYCZNA"

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
if [ "$BLEDY" -eq 0 ]; then
  echo "STRAZNIK WIDZI WSZYSTKIE 8 PODLOZONYCH BLEDOW"
else
  echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"
fi
exit $((BLEDY > 0))
