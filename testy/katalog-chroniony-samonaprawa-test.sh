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

if [ -n "$PO" ]; then
  echo "STRAZNIK: WYNIK=PASS"
  exit 0
fi
# Przywrocenie stanu sprzed testu, zeby nie zostawic stosu bez katalogu.
[ -n "$PRZED" ] && $WP option update sklepag_katalog_chroniony "$PRZED" >/dev/null 2>&1
echo "STRAZNIK: WYNIK=FAIL - katalog chroniony nie wraca bez ponownej aktywacji"
exit 1
