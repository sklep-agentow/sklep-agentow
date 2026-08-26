#!/bin/bash
# STRAŻNIK REGRESJI — SK-L4-001 (katalog chroniony nie wracał po aktualizacji w miejscu).
#
# Wada: sklepag_utworz_katalog_chroniony() była wołana WYŁĄCZNIE z sklepag_aktywacja().
# Aktualizacja wtyczki „w miejscu" aktywacji nie odpala, więc instalacja bez opcji
# `sklepag_katalog_chroniony` nie odzyskiwała jej niczym — cron miał samonaprawę, katalog nie.
#
# Test kasuje opcję i puka w wp-cron.php (prawdziwa ścieżka serwera, bez logowania),
# a potem sprawdza, czy opcja wróciła.
#
# PASS (exit 0) = opcja odtworzona przez samonaprawę.
# FAIL (exit 1) = opcji nadal brak — aktualizacja w miejscu zostawia sklep bez chronionego katalogu.
#
# Uruchomienie: bash testy/katalog-chroniony-samonaprawa-test.sh [kontener] [url]
set -u
KONTENER="${1:-probny_wp}"
URL="${2:-http://localhost/wp-cron.php?doing_wp_cron=1}"
WP="docker exec -u www-data $KONTENER wp --path=/var/www/html"

PRZED=$($WP option get sklepag_katalog_chroniony 2>/dev/null || echo '')
echo "STRAZNIK: opcja_przed=${PRZED:-BRAK}"

$WP option delete sklepag_katalog_chroniony >/dev/null 2>&1
PO_KASACJI=$($WP option get sklepag_katalog_chroniony 2>/dev/null || echo '')
if [ -n "$PO_KASACJI" ]; then
  echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (nie udalo sie skasowac opcji)"
  exit 2
fi
echo "STRAZNIK: opcja_po_skasowaniu=BRAK (warunek wstepny OK)"

docker exec "$KONTENER" curl -s -o /dev/null -w '' "$URL" 2>/dev/null || true

PO=$($WP option get sklepag_katalog_chroniony 2>/dev/null || echo '')
echo "STRAZNIK: opcja_po_cronie=${PO:-BRAK} (OCZEKIWANE: sciezka katalogu)"

if [ -z "$PO" ]; then
  [ -n "$PRZED" ] && $WP option update sklepag_katalog_chroniony "$PRZED" >/dev/null 2>&1
  echo "STRAZNIK: WYNIK=FAIL - katalog chroniony nie wraca bez ponownej aktywacji"
  exit 1
fi

# --- PRÓBA KONTROLNA: opcja JEST, ale ścieżka nieosiągalna (przeniesiony hosting,
# odpięty dysk). Samonaprawa NIE MOŻE wtedy utworzyć nowego katalogu i nadpisać opcji —
# to osierociłoby pliki wszystkich produktów i zgasiłoby ostrzeżenie na Pulpicie.
NIEISTNIEJACA="/var/www/html/wp-content/uploads/sklepag-chronione-NIEOSIAGALNA-$$"
$WP option update sklepag_katalog_chroniony "$NIEISTNIEJACA" >/dev/null 2>&1
docker exec "$KONTENER" curl -s -o /dev/null "$URL" 2>/dev/null || true
PO_NIEOSIAGALNEJ=$($WP option get sklepag_katalog_chroniony 2>/dev/null || echo '')
echo "STRAZNIK: sciezka_po_nieosiagalnej=$PO_NIEOSIAGALNEJ"
echo "STRAZNIK: (OCZEKIWANE: bez zmian — $NIEISTNIEJACA)"
$WP option update sklepag_katalog_chroniony "$PO" >/dev/null 2>&1   # przywrócenie stanu

if [ "$PO_NIEOSIAGALNEJ" != "$NIEISTNIEJACA" ]; then
  echo "STRAZNIK: WYNIK=FAIL - samonaprawa PODMIENILA sciezke, ktora byla tylko nieosiagalna (pliki produktow osierocone)"
  exit 1
fi

echo "STRAZNIK: WYNIK=PASS"
exit 0
