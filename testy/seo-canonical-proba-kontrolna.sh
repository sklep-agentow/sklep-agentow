#!/bin/bash
# Próba kontrolna strażnika L6 canonical (zasada D14: kontrola, która nie znajdzie
# PODŁOŻONEGO błędu, jest ślepa). Mutant 1 = DOSŁOWNIE stan sprzed naprawy (0.9.25):
# wtyczka nie drukuje własnego canonicala. Mutant 2 = lekarstwo gorsze od choroby:
# drukujemy zawsze, także gdy WP drukuje swój (dwa znaczniki).
# Użycie: bash testy/seo-canonical-proba-kontrolna.sh   (na końcu wdraża czysty kod)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
PLIK=$CEL/includes/front-seo.php
PLIK_GLOWNY=$CEL/sklep-agentow.php
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
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/seo-canonical-test.php "$KONTENER:/tmp/canon.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/canon.php 2>&1
}
probuj() {
  local opis="$1" mutacja="$2" krok="$3" plik="${4:-$PLIK}"
  wdroz_czysty
  local przed po
  przed=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): %s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
  fi
  local wyjscie kod
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %s (EXIT=%d)\n' "$opis" "$kod"
    echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
  else
    printf '  BLAD straznik NIE ZLAPAL: %s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
  fi
}

echo "== Mutant 1: stan sprzed naprawy (brak własnego canonicala) =="
probuj "wyciety add_action sklepag_seo_canonical_wlasny" \
  "docker exec $KONTENER sed -i \"s/^add_action( 'wp_head', 'sklepag_seo_canonical_wlasny', 3 );/\\/\\/ MUTANT/\" $PLIK" "A\\."
echo "== Mutant 2: drukuje zawsze (dwa znaczniki, gdy WP ma swój) =="
probuj "wyciety warunek has_action" \
  "docker exec $KONTENER sed -i \"s/if ( false !== has_action( 'wp_head', 'rel_canonical' ) ) {/if ( false ) {/\" $PLIK" "C\\."

echo "== Mutant 3: is_page( 0 ) bez bramki (stan sprzed naprawy klasy) =="
probuj "wycieta bramka \$id > 0 w sklepag_jest_strona_z_opcji (plik glowny)" \
  "docker exec $KONTENER sed -i 's/return \$id > 0 \&\& is_page( \$id );/return is_page( \$id );/' $PLIK_GLOWNY" "F\\." "$PLIK_GLOWNY"
echo "== Czysty kod: strażnik ma PRZEJŚĆ =="
wdroz_czysty
wyjscie=$(odpal); kod=$?
if [ "$kod" -eq 0 ]; then echo "  OK   czysty kod EXIT=0"; else echo "  BLAD czysty kod EXIT=$kod"; echo "$wyjscie" | tail -8; ODWROTNA=$((ODWROTNA+1)); fi
podsumowanie_proby "PROBA KONTROLNA ZALICZONA — kazdy podlozony blad zlapany"
