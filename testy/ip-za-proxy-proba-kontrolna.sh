#!/bin/bash
# Proba kontrolna straznika L2 (zasada D14: kontrola, ktora nie znajdzie
# PODLOZONEGO bledu, jest slepa).
#
# Mutant nr 1 to DOSLOWNIE stan sprzed naprawy: limit liczony z REMOTE_ADDR.
# Pozostale pilnuja, zeby lekarstwo nie bylo gorsze od choroby — zaufanie
# nagłowkom bez zgody wlasciciela albo bez walidacji otwiera obejscie limitu.
#
# Uzycie: bash testy/ip-za-proxy-proba-kontrolna.sh
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live
PLIK_F=$CEL/includes/front.php
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
odpal() {
  docker cp testy/ip-za-proxy-test.php "$KONTENER:/tmp/ip.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/ip.php 2>&1
}

probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$PLIK_F" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$PLIK_F" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %-38s\n' "$opis"
    KOTWICE=$((KOTWICE+1)); return
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %-50s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE zlapal: %-47s (EXIT=%d)\n' "$opis" "$kod"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy straznik L2 widzi podlozone bledy ==="

# 1. STAN SPRZED NAPRAWY: limit znowu mierzy adres proxy.
probuj "limit liczony z REMOTE_ADDR (stan sprzed naprawy)" \
  "docker exec $KONTENER sed -i 's|: chatl_ip_goscia();|: ( isset( \$_SERVER[\"REMOTE_ADDR\"] ) ? sanitize_text_field( wp_unslash( \$_SERVER[\"REMOTE_ADDR\"] ) ) : \"\" );|' $PLIK_F" \
  "gość B zza tego samego proxy NIE jest ukarany"

# 2. Naglowkom ufamy ZAWSZE — na stronie bez proxy kazdy obchodzi limit,
#    podajac za kazdym razem inny adres.
probuj "zaufanie naglowkom bez zgody wlasciciela" \
  "docker exec $KONTENER sed -i 's|if ( ! empty( \$ust\[.za_proxy.\] ) ) {|if ( true ) {|' $PLIK_F" \
  "liczy się adres połączenia, nie nagłówek"

# 3. Brak walidacji adresu: smiec z naglowka staje sie kluczem kubla,
#    wiec kazde zadanie z innym smieciem ma wlasny limit.
probuj "brak walidacji adresu z naglowka" \
  "docker exec $KONTENER sed -i 's|if ( ! filter_var( \$ip, FILTER_VALIDATE_IP ) ) {|if ( false ) {|' $PLIK_F" \
  "nagłówek ze śmieciem NIE wyłącza limitu"

# 4. Z X-Forwarded-For bierzemy OSTATNI adres zamiast pierwszego — czyli proxy,
#    a nie goscia. Luka wraca w innym przebraniu.
probuj "z X-Forwarded-For brany OSTATNI adres zamiast pierwszego" \
  "docker exec $KONTENER sed -i 's|\$ip    = trim( (string) \$lista\[0\] );|\$ip    = trim( (string) \$lista[ count( \$lista ) - 1 ] );|' $PLIK_F" \
  "bez CF bierzemy PIERWSZY adres"

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
podsumowanie_proby "STRAZNIK WIDZI WSZYSTKIE CZTERY PODLOZONE BLEDY"
