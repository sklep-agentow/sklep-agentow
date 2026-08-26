#!/bin/bash
# ⛔ NISZCZY STOS `probny_wp`: wgrywa 0.2.0 `--force --activate`, kasuje opcję katalogu chronionego, wgrywa 0.9.13 `--force`
#    i NIC nie przywraca (recenzja #191 W3). Po uruchomieniu: `bash narzedzia-qa/k7-stos-rundy.sh <zip>` (albo migawka bazy).
# ZIPY (recenzja #191 W2): bierze je z repo/warsztatu i sam kopiuje do kontenera — zmienne ZIP_STARA / ZIP_NOWA:
#    0.2.0  = /root/praca/zlecenia/wymiana/k7-stare-wersje/sklep-agentow-0.2.0.zip (paczka starej wersji zbudowana z historii repo, PLAN-K7 krok 1)
#    0.9.13 = boty/paczki/sklep-agentow-0.9.13.zip (ZAMROZENIE.md, sha256 7e0cf726…)
# PADA na obecnym kodzie 0.9.13 (5d3dcf9): aktualizacja W MIEJSCU (force-install nadpisujacy
# pliki AKTYWNEJ wtyczki, bez deaktywacji/reaktywacji - tak dziala WP "Aktualizuj
# teraz") NIGDY nie tworzy sklepag_katalog_chroniony dla instalacji ktore go jeszcze
# nie maja (schemat 3/4/5), bo sklepag_utworz_katalog_chroniony() jest wolana
# WYLACZNIE z sklepag_aktywacja() (includes/aktywacja.php:139), a migracja przez
# plugins_loaded->sklepag_migruj_schemat_jesli_trzeba() (sklep-agentow.php:57) jej
# nie wywoluje. Skutek: admin-produkty.php:281 czyta pusty katalog_chroniony.
set -e
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
ZIP_STARA="${ZIP_STARA:-/root/praca/zlecenia/wymiana/k7-stare-wersje/sklep-agentow-0.2.0.zip}"
ZIP_NOWA="${ZIP_NOWA:-$KORZEN/boty/paczki/sklep-agentow-0.9.13.zip}"
for z in "$ZIP_STARA" "$ZIP_NOWA"; do [ -f "$z" ] || { echo "BRAK ZIPA: $z (patrz nagłówek)"; exit 2; }; done
docker cp "$ZIP_STARA" probny_wp:/tmp/v020.zip
docker cp "$ZIP_NOWA"  probny_wp:/tmp/v0913.zip
docker exec -u www-data probny_wp wp --path=/var/www/html plugin install /tmp/v020.zip --force --activate
docker exec -u www-data probny_wp wp --path=/var/www/html option delete sklepag_katalog_chroniony 2>/dev/null || true
docker exec -u www-data probny_wp wp --path=/var/www/html plugin install /tmp/v0913.zip --force
if docker exec -u www-data probny_wp wp --path=/var/www/html option get sklepag_katalog_chroniony >/dev/null 2>&1; then
  echo "PASS: katalog_chroniony istnieje po aktualizacji w miejscu"
  exit 0
fi
echo "FAIL: sklepag_katalog_chroniony NIE powstal po aktualizacji w miejscu bez reaktywacji"
exit 1
