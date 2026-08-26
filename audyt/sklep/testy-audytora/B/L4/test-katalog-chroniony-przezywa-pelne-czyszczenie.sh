#!/usr/bin/env bash
# ⛔ TEST NISZCZĄCY (deinstaluje wtyczkę, przełącznik "usuń wszystkie dane" = ON).
#    PRZYWRÓCENIE: patrz nagłówek test-transient-idem-przezywa-uninstall.sh (ta sama procedura:
#    restore z migawki L4-start.sql + reinstall czystej 0.9.13).
#
# Pada NA KODZIE 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (uninstall.php:43-65, konkretnie
# @unlink()/@rmdir() w liniach 56 i 63): usuwanie plików katalogu chronionego jest
# CELOWO "best-effort" (komentarz w kodzie: "Best-effort: plik innego właściciela (deploy
# FTP userem != PHP) zostaje bez PHP Warning"), ale WYNIK unlink()/rmdir() NIE jest
# nigdzie sprawdzany ani logowany (brak sklepag_zdarzenie(), brak wpisu w opcjach typu
# *_blad analogicznego do sklepag_katalog_chroniony_blad z aktywacji). Admin, który
# kliknął "usuń wszystkie dane" (D22, pełne czyszczenie), NIE MA ŻADNEGO SPOSOBU dowiedzieć
# się, że plik produktu i katalog PRZETRWAŁY na dysku.
#
# Reprodukcja NIE wymaga specjalnego przygotowania w tym środowisku: katalog chroniony
# (i plik w środku) na stosie probny_wp jest od zawsze root:root, a `wp plugin uninstall`
# wykonywany przez wp-cli działa jako www-data — dokładnie scenariusz z
# audyt/UNINSTALL-HANDBOOK.md pkt 2 ("deploy FTP userem X, PHP jako www-data").
set -euo pipefail
docker exec -u www-data probny_wp wp --path=/var/www/html option update sklepag_usun_dane_przy_odinstalowaniu 1
KAT=$(docker exec -u www-data probny_wp wp --path=/var/www/html option get sklepag_katalog_chroniony)
echo "katalog=$KAT"
echo "=== PRZED (wlasciciel plikow) ==="
docker exec probny_wp sh -c "ls -la '$KAT'"

docker exec -u www-data probny_wp wp --path=/var/www/html plugin deactivate sklep-agentow
docker exec -u www-data probny_wp wp --path=/var/www/html plugin uninstall sklep-agentow || true

echo "=== PO (test PADA jesli katalog/plik nadal istnieje) ==="
if docker exec probny_wp sh -c "[ -d '$KAT' ]"; then
  docker exec probny_wp sh -c "ls -la '$KAT'"
  echo "PADA: katalog chroniony przezyl pelne usuwanie danych (przelacznik ON), bez zadnego komunikatu/logu."
  exit 1
else
  echo "OK: katalog usuniety."
  exit 0
fi
