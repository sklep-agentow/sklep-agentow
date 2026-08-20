#!/bin/bash
# Proba kontrolna straznika wskaznika pisania. Mutant nr 1 to DOSLOWNIE stan
# sprzed naprawy: brak blokady i brak dymka.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_W=$CEL/assets/widget.js
BLEDY=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK_W" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK_W" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku: %-44s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  local wyjscie kod
  wyjscie=$(node testy/wskaznik-pisania-test.js 2>&1); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-52s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-49s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik wskaznika widzi podlozone bledy ==="

probuj "brak dymka pisania (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|^\t\t\tpokazPisanie();|\t\t\t// zdjete przez probe|' $PLIK_W" \
  "pojawil sie dymek"

probuj "pole i przycisk NIE blokowane" \
  "docker exec $KONTENER sed -i 's|^\t\t\tpolePytania.disabled = true;|\t\t\t// zdjete przez probe|' $PLIK_W" \
  "pole pytania ZABLOKOWANE"

probuj "brak strazy przed podwojnym wyslaniem" \
  "docker exec $KONTENER sed -i 's|if (!pytanie \|\| wyslijBtn.disabled) { return; }|if (!pytanie) { return; }|' $PLIK_W" \
  "licznik zadan nie wzrosl"

# UWAGA: probowalem tu mutanta "finally -> then" i NIE jest to realna usterka —
# .then() po .catch() wykonuje sie na obu sciezkach, wiec zachowanie nie zmienia
# sie ani o jotę. Zamiast udawac, ze straznik czegos nie lapie, podkladam blad,
# ktory naprawde psuje produkt: dymek zostaje na ekranie po odpowiedzi.
probuj "dymek nie jest sprzatany po odpowiedzi" \
  "docker exec $KONTENER sed -i 's|^\t\t\t\t\tukryjPisanie();|\t\t\t\t\t// zdjete przez probe|' $PLIK_W" \
  "dymek pisania zniknal"

probuj "dymek bez etykiety dla czytnika ekranu" \
  "docker exec $KONTENER sed -i \"s|d.setAttribute('aria-label', 'Bot pisze odpowiedź');||\" $PLIK_W" \
  "dymek ma etykiete dla czytnika ekranu"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if node testy/wskaznik-pisania-test.js > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; BLEDY=$((BLEDY+1))
fi
echo
if [ "$BLEDY" -eq 0 ]; then echo "STRAZNIK WIDZI WSZYSTKIE PIEC PODLOZONYCH BLEDOW"; else echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"; fi
exit $((BLEDY > 0))
