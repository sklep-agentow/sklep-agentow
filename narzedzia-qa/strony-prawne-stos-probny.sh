#!/bin/bash
# Spust strażnika stron prawnych na STOSIE PRÓBNYM od zera (nigdy na wspólnym stanowisku).
#
# Użycie: bash narzedzia-qa/strony-prawne-stos-probny.sh
#   EXIT 0 = strażnik widzi wszystkie sytuacje · 1 = któraś nie wyszła · 2 = brak warunków.
#
# ⛔ DLACZEGO STOS PRÓBNY, A NIE WSPÓLNY: żeby zmierzyć strażnika, trzeba mieć na stosie kod
#    Z GAŁĘZI. Wgranie go na wspólne stanowisko cofa robotę innym biegom (zmierzone 1.09:
#    stos cofnięty z 0.9.32 na 0.9.31). Tu stawiamy własny kontener i kasujemy go po sobie.
# ⛔ `postaw_stos` z tej rewizji `lib-stos-probny.sh` kasuje kontener BEZ `-v`, więc zostawia
#    anonimowy wolumen (~83 MB) — sprzątamy go jawnie na końcu, aż poprawka biblioteki wejdzie.
set -u

cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }

WTYCZKA_ZRODLO=wtyczki/sklep-agentow
TEST=testy/strony-prawne-ostrzezenie-test.php
[ -d "$WTYCZKA_ZRODLO" ] || padnij "nie ma katalogu $WTYCZKA_ZRODLO"
[ -f "$TEST" ]           || padnij "nie ma $TEST"

wymagaj_narzedzi
postaw_stos

naglowek "WGRANIE KODU Z DRZEWA (nie z paczki — mierzymy to, co w gałęzi)"
docker cp "$WTYCZKA_ZRODLO" "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow" > /dev/null \
	|| padnij "nie umiem wgrac wtyczki do kontenera"
docker cp "$TEST" "$KONTENER:/var/www/html/strony-prawne-test.php" > /dev/null \
	|| padnij "nie umiem wgrac testu do kontenera"
wp plugin activate sklep-agentow > /dev/null 2>&1 || padnij "wtyczka nie chce sie aktywowac"

WERSJA="$(docker exec "$KONTENER" sh -c "grep -m1 'Version:' /var/www/html/wp-content/plugins/sklep-agentow/sklep-agentow.php" | tr -d '\r')"
printf '  wgrana wtyczka: %s\n' "${WERSJA# * }"

naglowek "STRAZNIK STRON PRAWNYCH"
WYJ="$(docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/strony-prawne-test.php 2>&1)"
KOD=$?
printf '%s\n' "$WYJ"

naglowek "LOG BLEDOW PHP (notice/warning psuja panel wlasciciela)"
NOTICE="$(docker exec "$KONTENER" sh -c 'cat /var/www/html/debug.log 2>/dev/null' | grep -cE 'PHP (Notice|Warning|Fatal|Deprecated)')"
printf '  wpisow PHP w debug.log: %s\n' "$NOTICE"
[ "$NOTICE" = 0 ] || { printf '  ⛔ log NIE jest pusty:\n'; docker exec "$KONTENER" sh -c 'tail -5 /var/www/html/debug.log'; KOD=1; }

zwin_stos
docker rm -f -v "$KONTENER" > /dev/null 2>&1 || true   # patrz nagłówek: brak -v w tej rewizji biblioteki

printf '\nSPUST STRON PRAWNYCH: EXIT=%s\n' "$KOD"
exit "$KOD"
