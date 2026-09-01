#!/bin/bash
# Proba kontrolna naprawy P-4: anonimizacja szuka adresu w KAZDEJ postaci, w jakiej
# moze on lezec w dzienniku zdarzen (surowej i zakodowanej przez `wp_json_encode`).
#
# Straznikiem jest `testy/rodo-anonimizacja-pomiar-skutku-test.sh` (przypadek C).
# Ten skrypt przywraca wade po kolei, na trzech warstwach naprawy, i wymaga, zeby
# straznik za kazdym razem PADL — a na czystym kodzie przeszedl.
#
# ⛔ Licznik bledow NIE MOZE mieszkac w podpowloce `$( … )` — inkrementacja ginie
# razem z nia, a awaria konczy sie EXIT=0 (klasa zlapana w tym repo wczesniej).
#
# Uzycie: bash testy/rodo-anonimizacja-postacie-proba-kontrolna.sh   (na koncu wdraza czysty kod)
# Kody wyjscia: 0 = kalibracja przeszla · 1 = straznik czegos nie widzi.
#
# ⛔ MUTANT IDZIE DO PLIKU W REPO, NIE DO KONTENERA. Straznik odmawia pomiaru, gdy suma
# wtyczki na stosie rozni sie od repo („na stosie stoi INNA wersja") — i slusznie, bo
# mierzylby mieszanke. Mutujemy wiec zrodlo, wdrazamy je i PRZYWRACAMY z kopii; `trap`
# oddaje plik takze przy przerwaniu. Zadnej operacji gita, ktora cofa stan.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
PLIK=wtyczki/sklep-agentow/includes/prywatnosc.php
KOPIA=$(mktemp -t prywatnosc-proba-XXXXXX.php)
BLEDY=0

cp "$PLIK" "$KOPIA"
przywroc_zrodlo() {
  cp "$KOPIA" "$PLIK"
}
trap 'przywroc_zrodlo; rm -f "$KOPIA"' EXIT INT TERM

wdroz_czysty() {
  przywroc_zrodlo
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}

odpal() {
  bash testy/rodo-anonimizacja-pomiar-skutku-test.sh 2>&1
}

# $1 = opis mutanta, $2 = polecenie mutacji, $3 = fragment asercji, ktora MA paść.
probuj() {
  local opis="$1" mutacja="$2" asercja="$3"
  wdroz_czysty
  local przed po wyjscie kod
  przed=$(md5sum "$PLIK" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(md5sum "$PLIK" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla (straznik nie zostal wystawiony na probe): %s\n' "$opis"
    BLEDY=$((BLEDY+1)); return
  fi
  if ! php -l "$PLIK" >/dev/null 2>&1; then
    printf '  BLAD mutacja rozwalila skladnie: %s\n' "$opis"
    BLEDY=$((BLEDY+1)); return
  fi
  # Mutant na stos — suma repo i stosu MUSI byc rowna, inaczej straznik odmowi pomiaru.
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -eq 1 ] && printf '%s' "$wyjscie" | grep -q "BLAD .*$asercja"; then
    printf '  OK   straznik PADL na: %s (EXIT=%d, asercja: %s)\n' "$opis" "$kod" "$asercja"
  else
    printf '  BLAD straznik NIE ZLAPAL: %s (EXIT=%d)\n' "$opis" "$kod"
    printf '%s\n' "$wyjscie" | grep -E '^  BLAD|^PRZYRZAD' | sed 's/^/       /'
    BLEDY=$((BLEDY+1))
  fi
}

echo "== M1: postac ZAKODOWANA nieznana (doslownie stan sprzed naprawy) =="
probuj "sklepag_postacie_w_dzienniku() oddaje wylacznie postac surowa" \
  "sed -i 's|\$postacie\[\] = substr( \$json, 1, -1 );|\$postacie[] = (string) \$wartosc;|' \"$PLIK\"" \
  "adres ZOSTAL w dzienniku"

echo "== M2: druga postac szukana BEZ KOTWICY (naprawa zabierajaca cudze dane) =="
probuj "sklepag_wzorce_w_dzienniku() bez lookbehind" \
  "sed -i \"s|'/(?<!' . SKLEPAG_GRANICA_ADRESU . ')' . preg_quote|'/' . preg_quote|\" \"$PLIK\"" \
  "przepisano cudzy wpis w postaci zakodowanej"

echo "== M3: kandydaci znalezieni, ale podmiana tylko dla pierwszej postaci =="
probuj "sklepag_wzorce_w_dzienniku() obciete do jednego wzorca" \
  "sed -i 's|^\treturn \$wzory;$|\treturn array_slice( \$wzory, 0, 1 );|' \"$PLIK\"" \
  "adres ZOSTAL w dzienniku"

echo "== Czysty kod =="
wdroz_czysty
wyjscie=$(odpal); kod=$?
if [ "$kod" -eq 0 ]; then
  echo "  OK   czysty kod EXIT=0"
else
  echo "  BLAD czysty kod EXIT=$kod"
  printf '%s\n' "$wyjscie" | grep -E '^  BLAD|^PRZYRZAD' | sed 's/^/       /'
  BLEDY=$((BLEDY+1))
fi

echo
echo "BLEDY PROBY KONTROLNEJ: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
