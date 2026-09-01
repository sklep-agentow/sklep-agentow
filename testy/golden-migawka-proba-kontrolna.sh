#!/bin/bash
# Proba kontrolna GOLDEN-a „kupujacy dostaje to, co kupil" (zasada D14).
#
# GOLDEN pada na kodzie sprzed naprawy — to juz wiemy. Ten skrypt sprawdza czegos wiecej:
# czy pada na KAZDEJ z warstw naprawy z osobna. Naprawa ma trzy czesci (zapis migawki przy
# sesji, odczyt z migawki przy domknieciu, zapas dla sesji sprzed migracji) i kazda z nich
# moze zostac cofnieta osobno przy przyszlym refaktorze.
#
# ⛔ Mutant idzie do pliku W REPO i jest wdrazany, a plik wraca z kopii (`trap` oddaje go
# takze przy przerwaniu). Zadnej operacji gita, ktora cofa stan.
# ⛔ Licznik bledow NIE MOZE mieszkac w podpowloce `$( … )`.
#
# Uzycie: bash testy/golden-migawka-proba-kontrolna.sh   (na koncu wdraza czysty kod)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
PLIK=wtyczki/sklep-agentow/includes/zakup.php
KOPIA=$(mktemp -t zakup-golden-XXXXXX.php)
BLEDY=0

cp "$PLIK" "$KOPIA"
przywroc() { cp "$KOPIA" "$PLIK"; }
trap 'przywroc; rm -f "$KOPIA"' EXIT INT TERM

wdroz() {
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
  docker cp testy/golden-kupujacy-dostaje-to-co-kupil-test.php "$KONTENER:/tmp/gold.php" >/dev/null
}
odpal() { docker exec -u www-data "$KONTENER" wp eval-file /tmp/gold.php 2>&1; }

# $1 = opis, $2 = mutacja (sed na pliku w repo), $3 = fragment asercji, ktora MA paść.
probuj() {
  local opis="$1" mutacja="$2" asercja="$3" przed po wyjscie kod
  przywroc
  przed=$(md5sum "$PLIK" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(md5sum "$PLIK" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla: %s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  if ! php -l "$PLIK" >/dev/null 2>&1; then
    printf '  BLAD mutacja rozwalila skladnie: %s\n' "$opis"; BLEDY=$((BLEDY+1)); return
  fi
  wdroz
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -eq 1 ] && printf '%s' "$wyjscie" | grep -q "BLAD .*$asercja"; then
    printf '  OK   GOLDEN PADL na: %s (EXIT=%d, asercja %s)\n' "$opis" "$kod" "$asercja"
  else
    printf '  BLAD GOLDEN NIE ZLAPAL: %s (EXIT=%d)\n' "$opis" "$kod"
    printf '%s\n' "$wyjscie" | grep -E '^  (BLAD|PRZYRZAD)' | sed 's/^/       /'
    BLEDY=$((BLEDY+1))
  fi
}

echo "== M1: domkniecie znowu czyta TABELE PRODUKTOW (doslownie stan sprzed naprawy) =="
probuj "warunek migawki zawsze falszywy" \
  "sed -i \"s/if ( '' !== (string) \\\$nasza->produkt_nazwa ) {/if ( false ) {/\" \"\$PLIK\"" \
  "G1 nazwa w zamowieniu"

echo "== M2: migawka NIE ZAPISUJE nazwy przy tworzeniu sesji =="
probuj "przy INSERT sesji nazwa produktu pusta" \
  "sed -i \"s/^\\t\\t\\t(string) \\\$produkt->nazwa,$/\\t\\t\\t'',/\" \"\$PLIK\"" \
  "G1 nazwa w zamowieniu"

echo "== M3: migawka zapisuje BIEZACY plik zamiast kupionego =="
probuj "sciezka pliku w migawce podmieniona na pusta" \
  "sed -i \"s/^\\t\\t\\t(string) \\\$produkt->plik_sciezka,$/\\t\\t\\t'',/\" \"\$PLIK\"" \
  "G3 PLIK w zamowieniu"

echo "== M4: zapas dla sesji sprzed migracji usuniety =="
probuj "brak odczytu z tabeli produktow dla starej sesji" \
  "sed -i \"s|\\\$produkt = \\\$wpdb->get_row( \\\$wpdb->prepare( \\\"SELECT \\* FROM {\\\$pp} WHERE id = %d\\\", (int) \\\$nasza->produkt_id ) );|\\\$produkt = null;|\" \"\$PLIK\"" \
  "F1 zamowienie z sesji bez migawki"

echo "== Czysty kod =="
przywroc
wdroz
wyjscie=$(odpal); kod=$?
if [ "$kod" -eq 0 ]; then
  echo "  OK   czysty kod EXIT=0"
else
  echo "  BLAD czysty kod EXIT=$kod"
  printf '%s\n' "$wyjscie" | grep -E '^  (BLAD|PRZYRZAD)' | sed 's/^/       /'
  BLEDY=$((BLEDY+1))
fi

echo
echo "BLEDY PROBY KONTROLNEJ: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
