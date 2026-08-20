#!/bin/bash
# Proba kontrolna straznika L5. Mutant nr 1 to DOSLOWNIE stan sprzed naprawy:
# model zaszyty jako migawka z data.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_A=$CEL/includes/ai.php
BLEDY=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/wybor-modelu-test.php "$KONTENER:/tmp/wm.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/wm.php 2>&1
}
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK_A" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK_A" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku: %-38s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-48s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-45s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik L5 widzi podlozone bledy ==="

probuj "model z data w stalej (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i \"s|const CHATL_AI_MODEL      = 'claude-haiku-4-5';|const CHATL_AI_MODEL      = 'claude-haiku-4-5-20251001';|\" $PLIK_A" \
  "CHATL_AI_MODEL bez sufiksu z datą"

probuj "ustawienie wlasciciela ignorowane" \
  "docker exec $KONTENER sed -i \"s|\\\$model = isset( \\\$ust\\['ai_model'\\] ) ? trim( (string) \\\$ust\\['ai_model'\\] ) : '';|\\\$model = '';|\" $PLIK_A" \
  "ustawienie wygrywa ze stałą"

probuj "zadanie do API omija wybor wlasciciela" \
  "docker exec $KONTENER sed -i 's|\$model = chatl_ai_model();|\$model = CHATL_AI_MODEL;|' $PLIK_A" \
  "i jest to model wybrany przez właściciela"

# Ostatnia straz w chatl_ai_model() broni przed FILTREM zwracajacym pustke
# (spacje w ustawieniu lapie wczesniejszy warunek) — i tak ja sprawdzamy.
probuj "filtr moze wyslac do API pusty model" \
  "docker exec $KONTENER sed -i \"s|return '' !== trim( \\\$model ) ? trim( \\\$model ) : CHATL_AI_MODEL;|return trim( \\\$model );|\" $PLIK_A" \
  "filtr zwracający pustkę nie psuje żądania"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; BLEDY=$((BLEDY+1))
fi
echo
if [ "$BLEDY" -eq 0 ]; then echo "STRAZNIK WIDZI WSZYSTKIE CZTERY PODLOZONE BLEDY"; else echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"; fi
exit $((BLEDY > 0))
