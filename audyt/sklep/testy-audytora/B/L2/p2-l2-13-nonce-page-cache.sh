#!/usr/bin/env bash
# ⛔ TEST NA STOSIE WARSZTATOWYM :8890, KOD PRZY SHA 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d.
# Cel L2-13: strona produktu wyrenderowana >24h temu (np. przez full-page-cache/CDN)
# ma wbudowany nonce "sklepag_kup" powiązany z ticzką WP (domyślnie 2x12h = max ~24h
# ważności). Gość, który kliknie "Kup" na TAKIEJ stronie, dostaje surowy WP-owy
# ekran 403 "Are you sure you want to do this?" zamiast przejść do Stripe — a strona
# NIE ma żadnego mechanizmu odświeżania nonce'u (np. heartbeat/AJAX) przed submitem.
# Zdarzenie NIE trafia do wp_sklepag_zdarzenia (die() w check_admin_referer() zabija
# request PRZED jakimkolwiek sklepag_zdarzenie(...)) — właściciel sklepu nie widzi
# w panelu ŚLADU utraconej sprzedaży.
#
# PADA na obecnym kodzie: oczekujemy sukcesu/przekierowania (HTTP != 403), dostajemy 403.
# Test NIE niszczy stanu — czyta stronę produktu (GET) i wykonuje JEDEN POST zakupu
# gościa z celowo martwym noncem; nie modyfikuje bazy (checkout dla gościa z pustym
# kluczem Stripe i tak kończy się 502 przy poprawnym nonce, patrz kontrola niżej).
set -euo pipefail

BASE="http://localhost:8890"

# 1) Nonce "z cache sprzed 25h": liczony wg TEGO SAMEGO algorytmu co wp_create_nonce(),
#    ale dla ticka odpowiadającego chwili sprzed 25 godzin (uid=0 = gość, brak tokenu sesji).
NONCE_STARY=$(docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval '
$i = ceil( ( time() - 25*3600 ) / ( DAY_IN_SECONDS / 2 ) );
echo substr( wp_hash( $i . "|sklepag_kup|0|", "nonce" ), -12, 10 );
')

PRODUKT_ID=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wordpress -e "SELECT id FROM wp_sklepag_produkty WHERE aktywny=1 LIMIT 1"' | tail -1)

echo "=== PRZYPADEK: zakup gościa z noncem sprzed 25h (nonce=$NONCE_STARY, produkt=$PRODUKT_ID) ==="
KOD_STARY=$(curl -s -o /tmp/k7b-L2p2/p2-l2-13-stary.html -w "%{http_code}" -X POST "$BASE/wp-admin/admin-post.php" \
  -d "action=sklepag_kup&produkt_id=${PRODUKT_ID}&zgoda=1&_wpnonce=${NONCE_STARY}")
echo "HTTP=$KOD_STARY"
grep -o "Are you sure you want to do this" /tmp/k7b-L2p2/p2-l2-13-stary.html || true

echo "=== KONTROLA: ten sam zakup ze ŚWIEŻYM noncem z aktualnej strony produktu (oczekiwane: NIE 403 — nonce przechodzi) ==="
SLUG=$(docker exec strona_wp_db sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -e \"SELECT slug FROM wp_sklepag_produkty WHERE id=${PRODUKT_ID}\"" | tail -1)
curl -s "$BASE/sklep/produkt/${SLUG}/" -o /tmp/k7b-L2p2/p2-l2-13-produkt.html
NONCE_SWIEZY=$(grep -o 'name="_wpnonce"[^>]*value="[a-f0-9]*"' /tmp/k7b-L2p2/p2-l2-13-produkt.html | head -1 | grep -o 'value="[a-f0-9]*"' | cut -d'"' -f2)
KOD_SWIEZY=$(curl -s -o /tmp/k7b-L2p2/p2-l2-13-swiezy.html -w "%{http_code}" -X POST "$BASE/wp-admin/admin-post.php" \
  -d "action=sklepag_kup&produkt_id=${PRODUKT_ID}&zgoda=1&_wpnonce=${NONCE_SWIEZY}")
echo "HTTP=$KOD_SWIEZY (502 oczekiwane z powodu PUSTEGO klucza Stripe na tym stosie — fakt środowiska, NIE ten błąd; kluczowe jest RÓŻNE zachowanie: 403 vs nie-403)"

echo ""
if [ "$KOD_STARY" = "403" ] && [ "$KOD_STARY" != "$KOD_SWIEZY" ]; then
  echo "TEST PADA (na obecnym kodzie): nonce sprzed 25h jest odrzucany 403, świeży nonce przechodzi dalej (kod: $KOD_SWIEZY)."
  echo "Oczekiwane PO NAPRAWIE: obie ścieżki kończą się TAK SAMO (np. odświeżenie nonce po stronie klienta przed submitem, albo łagodniejsza odpowiedź z retry zamiast surowego 403 WP)."
  exit 1
else
  echo "TEST PRZECHODZI: nonce sprzed 25h nie jest już martwy albo zachowanie jest identyczne jak ze świeżym noncem."
  exit 0
fi
