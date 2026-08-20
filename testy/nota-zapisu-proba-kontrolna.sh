#!/bin/bash
# Proba kontrolna straznika L7. Mutant nr 1 to DOSLOWNIE stan sprzed naprawy:
# gosc nie widzi nic o zapisie, dopoki nie otworzy formularza kontaktu.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
BLEDY=0

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
probuj() {
  local opis="$1" mutacja="$2" krok="$3" plik="$4"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku: %-40s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  local wyjscie kod
  wyjscie=$(node testy/nota-zapisu-test.js 2>&1); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-50s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-47s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik L7 widzi podlozone bledy ==="

probuj "brak noty w widgecie (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|if (dane.notaZapisu) {|if (false) {|' $CEL/assets/widget.js" \
  "nota istnieje zaraz po otwarciu czatu" "$CEL/assets/widget.js"

probuj "nota dopiero PO powitaniu bota" \
  "docker exec $KONTENER perl -0pi -e 's/(\\t\\tif \\(dane\\.notaZapisu\\) \\{.*?\\n\\t\\t\\}\\n)(\\n\\t\\tdodajWiadomosc\\(.bot., dane\\.powitanie\\);)/\$2\\n\$1/s' $CEL/assets/widget.js" \
  "nota jest pierwszym elementem listy wiadomosci" "$CEL/assets/widget.js"

probuj "nota nie mowi, ile dni trzymamy rozmowe" \
  "docker exec $KONTENER sed -i \"s|'Rozmowa jest zapisywana przez %d dni, żebyśmy mogli wrócić z odpowiedzią.'|'Rozmowa jest zapisywana.'|\" $CEL/includes/front.php" \
  "nota podaje okres retencji z KODU" "$CEL/includes/front.php"

probuj "odnosnik do polityki bez rel=noopener" \
  "docker exec $KONTENER sed -i \"s|link.rel = 'noopener';||\" $CEL/assets/widget.js" \
  "rel=noopener" "$CEL/assets/widget.js"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if node testy/nota-zapisu-test.js > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"; BLEDY=$((BLEDY+1))
fi
echo
if [ "$BLEDY" -eq 0 ]; then echo "STRAZNIK WIDZI WSZYSTKIE CZTERY PODLOZONE BLEDY"; else echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"; fi
exit $((BLEDY > 0))
