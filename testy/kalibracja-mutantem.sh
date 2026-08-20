#!/bin/bash
# Kalibracja testu testy/panel-vs-bot.php mutantem (zasada D14: test bez
# kalibracji nic nie znaczy — trzeba pokazać, że w ogóle POTRAFI paść).
#
# MUTANT: w silnik.php reguła dopasowania „wariant występuje gdziekolwiek"
# zmieniana na „wariant na początku pytania". To zmiana JEDNEGO miejsca.
#   - kod SPRZED wydzielenia: panel ma własną kopię reguły → panel i bot
#     rozjeżdżają się → test PADA (o to chodzi w uwadze recenzenta),
#   - kod PO wydzieleniu: jedna funkcja → zmiana dociera wszędzie →
#     panel i bot dalej mówią to samo → test PRZECHODZI.
#
# Użycie: testy/kalibracja-mutantem.sh <katalog-wtyczki> <etykieta>
set -u
ZRODLO="$1"
ETYKIETA="$2"
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/chatbot-live

wdroz() {
  docker cp "$1/." "$KONTENER:$CEL/" >/dev/null
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/panel-vs-bot.php "$KONTENER:/tmp/panel-vs-bot.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/panel-vs-bot.php
  return $?
}

echo "########## $ETYKIETA ##########"
wdroz "$ZRODLO"

echo "--- A) bez mutanta ---"
WYNIK_A=$(odpal); KOD_A=$?
echo "$WYNIK_A" | tail -3
echo "EXIT=$KOD_A"

echo
echo "--- B) z mutantem w silnik.php (regula tylko na poczatku pytania) ---"
docker exec "$KONTENER" sed -i 's/false !== mb_strpos( $szukane, $wariant )/0 === mb_strpos( $szukane, $wariant )/' "$CEL/includes/silnik.php"
echo "zmutowane linie w silnik.php:"
docker exec "$KONTENER" grep -n "mb_strpos( \$szukane, \$wariant )" "$CEL/includes/silnik.php"
echo "te same linie w admin.php (kopia reguly — jesli jest, mutant jej NIE dotknal):"
docker exec "$KONTENER" grep -n "mb_strpos( \$szukane, \$wariant )" "$CEL/includes/admin.php" || echo "  (brak — panel nie ma wlasnej kopii)"
WYNIK_B=$(odpal); KOD_B=$?
echo "$WYNIK_B" | grep -E "BLAD|PRZESZLY|NIEPOWODZENIE" | head -12
echo "EXIT=$KOD_B"

echo
echo "--- przywracam kod bez mutanta ---"
wdroz "$ZRODLO"
echo "WYNIK KALIBRACJI [$ETYKIETA]: bez mutanta EXIT=$KOD_A, z mutantem EXIT=$KOD_B"
