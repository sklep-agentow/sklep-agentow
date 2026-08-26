#!/bin/bash
# PADA na obecnym kodzie 0.9.13: aktualizacja W MIEJSCU (force-install nadpisujacy
# pliki AKTYWNEJ wtyczki, bez deaktywacji/reaktywacji - tak dziala WP "Aktualizuj
# teraz") NIGDY nie tworzy sklepag_katalog_chroniony dla instalacji ktore go jeszcze
# nie maja (schemat 3/4/5), bo sklepag_utworz_katalog_chroniony() jest wolana
# WYLACZNIE z sklepag_aktywacja() (includes/aktywacja.php:139), a migracja przez
# plugins_loaded->sklepag_migruj_schemat_jesli_trzeba() (sklep-agentow.php:57) jej
# nie wywoluje. Skutek: admin-produkty.php:281 czyta pusty katalog_chroniony.
set -e
docker exec -u www-data probny_wp wp --path=/var/www/html plugin install /tmp/v020.zip --force --activate
docker exec -u www-data probny_wp wp --path=/var/www/html option delete sklepag_katalog_chroniony 2>/dev/null || true
docker exec -u www-data probny_wp wp --path=/var/www/html plugin install /tmp/v0913.zip --force
if docker exec -u www-data probny_wp wp --path=/var/www/html option get sklepag_katalog_chroniony >/dev/null 2>&1; then
  echo "PASS: katalog_chroniony istnieje po aktualizacji w miejscu"
  exit 0
fi
echo "FAIL: sklepag_katalog_chroniony NIE powstal po aktualizacji w miejscu bez reaktywacji"
exit 1
