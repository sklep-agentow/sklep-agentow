#!/bin/bash
# Proba kontrolna straznika L3. Mutant nr 1 to DOSLOWNIE stan sprzed naprawy:
# licznik zlicza wywolania i nic nie wie o tokenach.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_A=$CEL/includes/ai.php
BLEDY=0
PROB=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/tokeny-koszt-test.php "$KONTENER:/tmp/tk.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/tk.php 2>&1
}
probuj() {
  PROB=$((PROB+1))
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK_A" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK_A" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku: %-42s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-52s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-49s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik L3 widzi podlozone bledy ==="

probuj "licznik nie zapisuje tokenow (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|chatl_ai_zuzyj( chatl_ai_usage( is_array( \$dane ) ? \$dane : array() ) );|chatl_ai_zuzyj();|' $PLIK_A" \
  "tokeny wejścia zapisane"

# 1b. Uwaga WAZNA recenzenta do PR #83: przed poprawka zuzycie liczylo sie tylko
#     przy udanej odpowiedzi, wiec odmowa modelu i pusty tekst kosztowaly
#     wlasciciela, a byly niewidoczne dla OBU stop-lossow. Mutant przywraca
#     dokladnie tamten uklad: naliczanie wraca na sciezke sukcesu.
probuj "zuzycie liczone dopiero przy udanej odpowiedzi (uwaga do PR 83)" \
  "docker exec $KONTENER sed -i '/chatl_ai_zuzyj( chatl_ai_usage( is_array/d; /function chatl_ai_odpowiedz/,/^}/s|chatl_odnotuj_sukces_ai();|chatl_ai_zuzyj( chatl_ai_usage( \$dane ) );\n\tchatl_odnotuj_sukces_ai();|' $PLIK_A" \
  "odmowa LICZY tokeny (rachunek przyszedl)" "$PLIK_A" php

# Mutant nr 3 celuje w warunek DRUGIEGO stop-lossu. Po naprawie T-1 (C2)
# chatl_ai_dostepny() czyta licznik RAZ i sumuje tokeny z odczytanej tablicy,
# wiec warunek brzmi `$tokeny >= $limit_tokenow`, a nie `chatl_ai_tokeny_dzis()`.
# Stary wzorzec przestal pasowac i proba melduje „mutacja NIE weszla do pliku"
# (glosno, bo pilnuje md5) — przepisany na aktualna postac warunku.
probuj "limit tokenow nie blokuje AI" \
  "docker exec $KONTENER sed -i 's|if ( \$limit_tokenow > 0 \&\& \$tokeny >= \$limit_tokenow ) {|if ( false ) {|' $PLIK_A" \
  "tryb AI jest zablokowany"

probuj "odczyt z cache wliczany do tokenow platnych" \
  "docker exec $KONTENER sed -i \"s|return \\\$d\\['tokeny_wejscia'\\] + \\\$d\\['tokeny_wyjscia'\\] + \\\$d\\['tokeny_cache_zapis'\\];|return \\\$d['tokeny_wejscia'] + \\\$d['tokeny_wyjscia'] + \\\$d['tokeny_cache_zapis'] + \\\$d['tokeny_cache_odczyt'];|\" $PLIK_A" \
  "odczyt z cache NIE wchodzi do tokenów płatnych"

probuj "cache dokladany NA SLEPO (ponizej progu dziala po cichu wcale)" \
  "docker exec $KONTENER sed -i 's|if ( mb_strlen( \$instrukcja ) >= CHATL_AI_CACHE_PROG_ZNAKOW ) {|if ( true ) {|' $PLIK_A" \
  "krótka instrukcja BEZ cache_control"

probuj "zle nazwy pol usage (literowka w kontrakcie API)" \
  "docker exec $KONTENER sed -i \"s|'cache_creation_input_tokens'|'cache_creation_tokens'|g\" $PLIK_A" \
  "cache_creation_input_tokens"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; BLEDY=$((BLEDY+1))
fi
echo
if [ "$BLEDY" -eq 0 ]; then echo "STRAZNIK WIDZI WSZYSTKIE $PROB PODLOZONE BLEDY"; else echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"; fi
exit $((BLEDY > 0))
