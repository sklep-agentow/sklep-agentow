#!/bin/bash
# Proba kontrolna straznikow L4 (zasada D14: kontrola, ktora nie znajdzie
# PODLOZONEGO bledu, jest slepa).
#
# Naprawa L4 ma piec ruchomych czesci: zapis znacznika przy porazce, zdjecie go
# przy sukcesie, WARUNEK przy zdejmowaniu (zeby nie pisac do bazy przy kazdym
# udanym pytaniu) i widoczny pasek w panelu. Kazda da sie zepsuc osobno.
#
# Mutant nr 1 to DOSLOWNIE stan sprzed naprawy: blad leci tylko do debug.log.
#
# Uzycie: WP_ADMIN_USER=... WP_ADMIN_PASS=... bash testy/blad-klucza-proba-kontrolna.sh
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
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

wdroz_czysty() {
  docker cp wtyczki/chatbot-live/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal_php() {
  docker cp testy/blad-klucza-test.php "$KONTENER:/tmp/bk.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/bk.php 2>&1
}
odpal_panel() { node testy/blad-klucza-panel.js 2>&1; }

# probuj "opis" "mutacja" "fragment-kroku" "plik" "php|panel"
probuj() {
  local opis="$1" mutacja="$2" krok="$3" plik="$4" ktory="$5"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-36s\n' "$opis"
    KOTWICE=$((KOTWICE+1)); return
  fi
  local wyjscie kod
  if [ "$ktory" = "php" ]; then wyjscie=$(odpal_php); kod=$?; else wyjscie=$(odpal_panel); kod=$?; fi
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-48s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-45s (EXIT=%d)\n' "$opis" "$kod"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik L4 widzi podlozone bledy ==="

# 1. STAN SPRZED NAPRAWY: blad idzie tylko do debug.log, ktory na produkcji
#    jest wylaczony — czyli awaria znowu jest calkowicie cicha.
probuj "porazka nieodnotowana (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|chatl_odnotuj_blad_ai( \$odp, \$kod );|// zdjete przez probe|g' $CEL/includes/ai.php" \
  "znacznik awarii zapisany" "$CEL/includes/ai.php" php

# 1b. Uwaga recenzenta do PR #79: HTTP 200 ze zniekształconym cialem wracalo
#     bez sladu. Mutant przywraca "return null" w tej galezi — czyli awarie
#     znowu cicha, dokladnie ta, ktora ten PR mial uwidocznic.
probuj "HTTP 200 ze smieciem w ciele bez sladu (uwaga do PR 79)" \
  "docker exec $KONTENER perl -0pi -e 's/(\\!\\s*is_array\\(\\s*\\\$dane\\s*\\)[^\\n]*\\n\\t\\t)return chatl_ai_niepowodzenie\\( \\\$odp, \\\$kod \\);/\\1return null;/s' $CEL/includes/ai.php" \
  "ZOSTAJE ślad dla właściciela" "$CEL/includes/ai.php" php

# 2. Znacznik zostaje na zawsze: wlasciciel widzi czerwony pasek mimo dzialajacego API.
probuj "sukces nie zdejmuje znacznika" \
  "docker exec $KONTENER sed -i 's|chatl_odnotuj_sukces_ai();|// zdjete przez probe|g' $CEL/includes/ai.php" \
  "znacznik awarii zdjęty" "$CEL/includes/ai.php" php

# 3. Zdejmowanie bez warunku: KAZDE udane pytanie goscia pisze do bazy.
probuj "zdejmowanie znacznika bez warunku (zapis przy kazdym pytaniu)" \
  "docker exec $KONTENER sed -i 's|if ( get_option( .chatl_blad_ai. ) ) {|if ( true ) {|' $CEL/includes/ai.php" \
  "zero zapytań do bazy" "$CEL/includes/ai.php" php

# 4. Wlasciciel nie widzi paska w panelu.
probuj "brak czerwonego paska w panelu" \
  "docker exec $KONTENER sed -i 's|chatl_notice_bledu_ai();|// zdjete przez probe|' $CEL/includes/admin.php" \
  "jest pasek" "$CEL/includes/admin.php" panel

# 5. Nie ma jak sprawdzic klucza na zadanie.
probuj "brak przycisku Przetestuj klucz" \
  "docker exec $KONTENER sed -i \"s|action=chatl_test_klucza|action=chatl_nie_ma|\" $CEL/includes/admin.php" \
  "jest przycisk testu klucza" "$CEL/includes/admin.php" panel

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal_php > /dev/null 2>&1 && odpal_panel > /dev/null 2>&1; then
  echo "  OK   oba strazniki przechodza na czystym kodzie (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"
  ODWROTNA=$((ODWROTNA+1))
fi

echo
podsumowanie_proby "STRAZNIKI WIDZA WSZYSTKIE SZESC PODLOZONYCH BLEDOW"
