#!/usr/bin/env bash
# PADAJĄCY test — L2-02 / L2-04 (klasa K1: ten sam błąd w 2 miejscach)
# Kod: 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d
# includes/zakup.php:70 (sklepag_obsluz_kup) i includes/opinie.php:344 (sklepag_obsluz_ocen_zapis)
# wołają sklepag_rate_limit() PRZED walidacją zgody/tokenu — licznik limitu zużywany
# przez żądania, które i tak by się nie powiodły. To narusza sourced regułę z
# 05-reguly-branzy.md §3: „liczniki limitów zużywane PO sukcesie, nie przed"
# (CLAUDE.md „Bezpieczeństwo — minimum podręcznikowe").
#
# PADA na obecnym kodzie: 10 żądań BEZ zgody (nigdy by nie kupiły) wyczerpuje limit
# 10/min i 11. żądanie — również bez zgody — dostaje 429 zamiast 400. Prawdziwy
# kupujący dzielący adres IP w tym oknie (NAT, biuro) dostałby 429 mimo że nikt
# nie wykonał ŻADNEGO udanego zakupu.
#
# Uruchomienie: bash l2-02-limit-zjadany-przed-sukcesem.sh [http://localhost:8890]
# Nieniszczące: nie tworzy zamówień (zgoda=0 zawsze odrzucona wcześniej niż zapis).

set -euo pipefail
BASE="${1:-http://localhost:8890}"
JAR="$(mktemp -d)/ck.txt"

# Nonce sklepag_kup jest PUBLICZNY (renderowany każdemu gościowi na stronie produktu) —
# nie wymaga sesji ani uprawnień, dlatego atak nie wymaga logowania.
PRODUKT_HTML="$(curl -s -c "$JAR" "$BASE/sklep/produkt/chatbot-live/")"
NONCE="$(echo "$PRODUKT_HTML" | grep -o 'name="_wpnonce" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{10\}')"

if [ -z "$NONCE" ]; then
  echo "NIE DA SIĘ SPRAWDZIĆ: nie znaleziono nonce sklepag_kup na stronie produktu (zmieniła się strona?)"
  exit 2
fi

echo "Nonce sklepag_kup: $NONCE"
echo "Wysyłam 10 żądań BEZ zgody (zgoda=0) — żadne nie może się udać:"
for i in $(seq 1 10); do
  code=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" \
    -d "action=sklepag_kup&produkt_id=1&zgoda=0&_wpnonce=${NONCE}" \
    "$BASE/wp-admin/admin-post.php")
  echo "  req#$i -> $code"
done

echo "11. żądanie (nadal zgoda=0, nadal NIE mogło się udać):"
code11=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" \
  -d "action=sklepag_kup&produkt_id=1&zgoda=0&_wpnonce=${NONCE}" \
  "$BASE/wp-admin/admin-post.php")
echo "  req#11 -> $code11"

if [ "$code11" = "429" ]; then
  echo "PADA: limiter zablokował #11 (429) mimo że żadne z 10 poprzednich żądań nie było"
  echo "próbą zakupu, która mogłaby się udać (brak zgody = zawsze 400 w kodzie). Licznik"
  echo "'kup' liczy PORAŻKI, nie sukcesy — sprzeczne z regułą 05-reguly-branzy.md §3."
  exit 1
else
  echo "PRZESZŁO (kod=$code11, oczekiwano 429 jako dowód błędu) — na tym kodzie błąd nie występuje."
  exit 0
fi
