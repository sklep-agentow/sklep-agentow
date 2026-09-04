#!/bin/bash
# Proba kontrolna straznika L1 (zasada D14: kontrola, ktora nie znajdzie
# PODLOZONEGO bledu, jest slepa).
#
# Naprawa L1 ma TRZY czesci i kazda da sie osobno zepsuc, nie ruszajac
# pozostalych. Ta proba psuje po jednej i sprawdza, ze testy/nonce-cache-test.js
# za kazdym razem PADA — bo inaczej "straznik z exit 1" bylby tylko napisem.
#
# Uzycie: WP_ADMIN_USER=... WP_ADMIN_PASS=... bash testy/nonce-proba-kontrolna.sh
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
odpal() { node testy/nonce-cache-test.js 2>&1; }

# probuj "opis" "polecenie-mutujace" "fragment-kroku-ktory-ma-paść"
probuj() {
  local opis="$1" mutacja="$2" krok="$3" plik="${4:-}"
  wdroz_czysty
  # Odcisk PRZED mutacja: bez tego niedzialajaca mutacja (np. zle wciecie
  # w sed) wyglada dokladnie jak "straznik nie zlapal bledu" — zmierzone.
  local przed=""
  [ -n "$plik" ] && przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d" " -f1)
  eval "$mutacja"
  if [ -n "$plik" ]; then
    local po
    po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d" " -f1)
    if [ "$przed" = "$po" ]; then
      printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-35s\n' "$opis"
      KOTWICE=$((KOTWICE+1))
      return
    fi
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-46s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-43s (EXIT=%d)\n' "$opis" "$kod"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik L1 widzi podlozone bledy ==="

# 1. Stan SPRZED naprawy po stronie widgetu: zadna proba nie jest ponawiana.
#    To dokladnie ten kod, ktory mial klient — gosc widzial komunikat o awarii.
probuj "widget bez ponowienia (kod sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|if (!toZlyNonce(odp)) { return odp; }|if (true) { return odp; }|' $CEL/assets/widget.js" \
  "gosc NIE zobaczyl komunikatu o awarii" \
  "$CEL/assets/widget.js"

# 2. Widget nie dociaga swiezego zabezpieczenia (endpoint jest, ale nieuzywany).
probuj "widget nie dociaga swiezego zabezpieczenia" \
  "docker exec $KONTENER sed -i \"s|body.append('action', 'chatl_nonce');|body.append('action', 'chatl_nieistniejaca');|\" $CEL/assets/widget.js" \
  "widget dociagnal swiezy nonce" \
  "$CEL/assets/widget.js"

# 3. Serwer odmawia, ale BEZ rozpoznawalnego kodu — widget nie ma po czym poznac,
#    ze to wygasle zabezpieczenie, wiec nie ma czego ponawiac.
probuj "odmowa bez kodu 'nonce'" \
  "docker exec $KONTENER sed -i \"s|'kod'       => 'nonce',|'kod'       => 'inny',|\" $CEL/includes/front.php" \
  "odmowa ma kod" \
  "$CEL/includes/front.php"

# 4. Awaria znowu jest CICHA: odrzucenie nie zostaje nigdzie odnotowane.
probuj "odrzucenie nieodnotowane (awaria znowu cicha)" \
  "docker exec $KONTENER sed -i 's|chatl_odnotuj_odrzucony_nonce();|// zdjete przez probe kontrolna|' $CEL/includes/front.php" \
  "odrzucenie zostalo ODNOTOWANE" \
  "$CEL/includes/front.php"

# 5. Wlasciciel nie widzi ostrzezenia w panelu.
probuj "brak ostrzezenia w panelu" \
  "docker exec $KONTENER sed -i 's|chatl_notice_odrzuconych_nonce();|// zdjete przez probe kontrolna|' $CEL/includes/admin.php" \
  "jest czerwone ostrzezenie" \
  "$CEL/includes/admin.php"

echo
echo "=== KONTROLA ODWROTNA: czysty kod ma przechodzic ==="
wdroz_czysty
if odpal > /dev/null 2>&1; then
  echo "  OK   czysty kod przechodzi (EXIT=0)"
else
  echo "  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy"
  ODWROTNA=$((ODWROTNA+1))
fi

echo
podsumowanie_proby "STRAZNIK WIDZI WSZYSTKIE PIEC PODLOZONYCH BLEDOW"
