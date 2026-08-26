#!/bin/bash
# PADA na obecnym kodzie 0.9.13 (sklep-agentow): opcja bez prefiksu "sklepag_"
# (sag_pulpit_ostatnie_wejscie, includes/admin-dashboard.php:201) PRZEZYWA pelny
# uninstall nawet przy sklepag_usun_dane_przy_odinstalowaniu=1, bo uninstall.php:70
# kasuje tylko DELETE ... WHERE option_name LIKE 'sklepag_%'.
set -e
HASLO_ADMIN="$(tr -d '\n' < /root/.claude/sekrety-audyt-admin.txt)"
curl -s -c /tmp/ck-test.txt -d "log=audytor-admin&pwd=$HASLO_ADMIN&wp-submit=1&testcookie=1" http://localhost:8891/wp-login.php -o /dev/null
curl -s -b /tmp/ck-test.txt http://localhost:8891/wp-admin/admin.php?page=sklepag -o /dev/null
docker exec -u www-data probny_wp wp --path=/var/www/html option update sklepag_usun_dane_przy_odinstalowaniu 1
docker exec -u www-data probny_wp wp --path=/var/www/html plugin uninstall sklep-agentow --deactivate
PO=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -N -e "SELECT COUNT(*) FROM wp_options WHERE option_name=\"sag_pulpit_ostatnie_wejscie\""')
if [ "$PO" != "0" ]; then
  echo "FAIL: sag_pulpit_ostatnie_wejscie PRZEZYL pelny uninstall (liczba=$PO), oczekiwano 0"
  exit 1
fi
echo "PASS"
