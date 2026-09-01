#!/bin/bash
# Próba kontrolna strażnika trybu testowego (testy/tryb-testowy-z-klucza-test.php, P-3).
#
# Strażnik, który nie pada na kodzie z wadą, nie jest dowodem — jest ozdobą. Ten skrypt
# przywraca wadę PO KOLEI, w każdym miejscu osobno, i wymaga, żeby strażnik za każdym
# razem PADŁ, a na czystym kodzie przeszedł.
#
# ⛔ Licznik błędów NIE MOŻE mieszkać w podpowłoce `$( … )` — inkrementacja ginie razem
# z podpowłoką i awaria kończy się EXIT=0 (klasa złapana w tym repo wcześniej).
#
# Użycie: bash testy/tryb-testowy-proba-kontrolna.sh   (na końcu wdraża czysty kod)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
BLEDY=0

wdroz_czysty() {
  docker cp wtyczki/sklep-agentow/. "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
  docker cp testy/tryb-testowy-z-klucza-test.php "$KONTENER:/tmp/tt.php" >/dev/null
}

odpal() {
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/tt.php 2>&1
}

# $1 = opis mutanta, $2 = polecenie mutacji, $3 = plik do pomiaru sumy, $4 = wzorzec warunku,
# który MUSI paść (grep na wyjściu strażnika).
probuj() {
  local opis="$1" mutacja="$2" plik="$3" krok="$4"
  wdroz_czysty
  local przed po wyjscie kod
  przed=$(docker exec "$KONTENER" md5sum "$CEL/$plik" | cut -d' ' -f1)
  eval "$mutacja"
  po=$(docker exec "$KONTENER" md5sum "$CEL/$plik" | cut -d' ' -f1)
  if [ "$przed" = "$po" ]; then
    printf '  BLAD mutacja NIE weszla (strażnik nie byłby wystawiony na próbę): %s\n' "$opis"
    BLEDY=$((BLEDY+1)); return
  fi
  wyjscie=$(odpal); kod=$?
  if [ "$kod" -ne 0 ] && printf '%s' "$wyjscie" | grep -q "BLAD.*$krok"; then
    printf '  OK   straznik PADL na: %s (EXIT=%d, warunek %s)\n' "$opis" "$kod" "$krok"
  else
    printf '  BLAD straznik NIE ZLAPAL: %s (EXIT=%d)\n' "$opis" "$kod"
    printf '%s\n' "$wyjscie" | grep -E '^  BLAD' | sed 's/^/       /'
    BLEDY=$((BLEDY+1))
  fi
}

echo "== M1: baner znowu bezwarunkowy (wada wyjsciowa) =="
probuj "sklepag_baner_testowy() bez warunku" \
  "docker exec $KONTENER sed -i 's/if ( ! sklepag_tryb_testowy() ) {/if ( false ) {/' $CEL/sklep-agentow.php" \
  "sklep-agentow.php" "C\\[sklep\\]"

echo "== M2: mail tekstowy znowu bezwarunkowy =="
probuj "zakup.php: dopisek o niepobranej platnosci zawsze" \
  "docker exec $KONTENER sed -i \"s/sklepag_tryb_testowy() ? ' (TRYB/true ? ' (TRYB/\" $CEL/includes/zakup.php" \
  "includes/zakup.php" "C\\[mail-tekst\\]"

echo "== M3: mail HTML znowu bezwarunkowy =="
probuj "mail-html.php: dopisek o niepobranej platnosci zawsze" \
  "docker exec $KONTENER sed -i 's/\\\$dopisek_trybu = sklepag_tryb_testowy()/\\\$dopisek_trybu = true/' $CEL/includes/mail-html.php" \
  "includes/mail-html.php" "C\\[mail-html\\]"

echo "== M4: etykiety CTA znowu na sztywno (wszystkie trzy naraz) =="
probuj "front-produkt.php: 'Kup teraz (platnosc testowa)' zawsze" \
  "docker exec $KONTENER sed -i \"s/sklepag_tryb_testowy() ? 'Kup teraz/true ? 'Kup teraz/g\" $CEL/includes/front-produkt.php" \
  "includes/front-produkt.php" "C\\[produkt\\]"

echo "== M5: Pulpit wlasciciela znowu na sztywno =="
probuj "admin-dashboard.php: znacznik TRYB TESTOWY zawsze" \
  "docker exec $KONTENER sed -i 's/sklepag_tryb_testowy()$/true/' $CEL/includes/admin-dashboard.php" \
  "includes/admin-dashboard.php" "C\\[pulpit\\]"

echo "== M6: PULAPKA A4b-F2 — przegladarka nadpisuje etykiete wlasnym napisem =="
probuj "platnosc.js: powrot do literalu zamiast etykiety z DOM-u" \
  "docker exec $KONTENER sed -i \"s/przycisk.textContent = etykietaKup;/przycisk.textContent = 'Kup teraz (płatność testowa)';/\" $CEL/assets/platnosc.js" \
  "assets/platnosc.js" "E1"

echo "== M7: domyslne OTWARCIE — brak klucza uznany za tryb testowy =="
probuj "sklepag_tryb_testowy() fail-open na pustym kluczu" \
  "docker exec $KONTENER sed -i \"s/return 0 === strpos( \\\$klucz, 'sk_test_' );/return '' === \\\$klucz || 0 === strpos( \\\$klucz, 'sk_test_' );/\" $CEL/sklep-agentow.php" \
  "sklep-agentow.php" "A3"

echo "== Czysty kod =="
wdroz_czysty
wyjscie=$(odpal); kod=$?
if [ "$kod" -eq 0 ]; then
  echo "  OK   czysty kod EXIT=0"
else
  echo "  BLAD czysty kod EXIT=$kod"
  printf '%s\n' "$wyjscie" | grep -E '^  BLAD' | sed 's/^/       /'
  BLEDY=$((BLEDY+1))
fi

echo
echo "BLEDY PROBY KONTROLNEJ: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
