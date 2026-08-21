#!/usr/bin/env bash
# Uruchamia DK-znaki-pola.php, wcześniej przepychając ten sam ciąg przez
# PRAWDZIWE endpointy gościa (HTTP), żeby pola frontu też były zmierzone.
set -u
AJAX="http://localhost:8890/wp-admin/admin-ajax.php"
JAR=$(mktemp); trap 'rm -f "$JAR"' EXIT
PROBA='Zażółć gęślą jaźń ĄĆĘŁŃÓŚŹŻ 😀 <script>alert("XSS")</script> <b>gruby</b> "cudzysłów" & ampersand — półpauza'
MAIL="dk3-proba@example.invalid"

N=$(curl -s -c "$JAR" -X POST "$AJAX" -d "action=chatl_nonce" | sed 's/.*"nonce":"\([^"]*\)".*/\1/')
S=$(curl -s -b "$JAR" -c "$JAR" -X POST "$AJAX" -d "action=chatl_sesja" | sed 's/.*"sesja":"\([^"]*\)".*/\1/')
echo "sesja gościa: $S"
# >2 s od wydania sesji: inaczej wpadamy we WŁASNĄ pułapkę czasową (CHATL_PULAPKA_SEKUND)
sleep 3
echo -n "odpowiedź na pytanie:  "; curl -s -b "$JAR" -X POST "$AJAX" --data-urlencode "pytanie=$PROBA" -d "action=chatl_pytanie" -d "nonce=$N" -d "sesja=$S" | head -c 90; echo ""
echo -n "odpowiedź na zgłoszenie: "; curl -s -b "$JAR" -X POST "$AJAX" --data-urlencode "wiadomosc=$PROBA" -d "action=chatl_zgloszenie" -d "nonce=$N" -d "sesja=$S" -d "email=$MAIL" -d "zgoda=1" | head -c 90; echo ""
echo ""
docker cp "$(dirname "$0")/DK-znaki-pola.php" strona_wp_wordpress:/tmp/DK-znaki.php >/dev/null
docker exec strona_wp_wordpress wp eval-file /tmp/DK-znaki.php --allow-root \
  --exec="\$GLOBALS['dk_sesja']='$S'; \$GLOBALS['dk_mail']='$MAIL';"
