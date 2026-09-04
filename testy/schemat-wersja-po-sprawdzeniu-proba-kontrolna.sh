#!/usr/bin/env bash
# PRÓBA KONTROLNA strażnika `schemat-wersja-po-sprawdzeniu-test.php` (A6-F1) — KALIBRACJA NA KODZIE SPRZED NAPRAWY.
#   1) STOS PRÓBNY + wtyczka z $STARY_REF (domyślnie origin/main): strażnik MUSI PAŚĆ na A (wersja
#      podniesiona mimo braku tabeli, bez komunikatu); B i C na starym kodzie nie mają znaczenia — liczy się A;
#   2) wtyczka z bieżącego drzewa: strażnik MUSI PRZEJŚĆ (A, B, C).
# Kody wyjścia: 0 = kalibracja udana · 1 = któryś etap nie zachował się jak trzeba · 2 = przyrząd/środowisko.
set -u
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "PRZYRZAD: brak lib-stos-probny.sh" >&2; exit 2; }

STARY_REF="${STARY_REF:-origin/main}"
CEL_WTYCZKI=/var/www/html/wp-content/plugins/sklep-agentow
ROBOCZY="$(mktemp -d -t a6-kalibracja-XXXXXX)"
exec 9> /root/praca/zlecenia/wymiana/stos.lock || padnij "nie mogę otworzyć zamka stosu"
flock -w 900 9 || padnij "stos próbny zajęty dłużej niż 15 min"
trap 'rm -rf "$ROBOCZY"; zwin_stos' EXIT INT TERM

wymagaj_narzedzi
git rev-parse --verify "$STARY_REF" > /dev/null 2>&1 || padnij "nie znam referencji $STARY_REF"

wdroz() {
	docker exec "$KONTENER" rm -rf "$CEL_WTYCZKI" > /dev/null 2>&1
	docker exec "$KONTENER" mkdir -p "$CEL_WTYCZKI" > /dev/null 2>&1 || return 1
	docker cp "$1/." "$KONTENER:$CEL_WTYCZKI/" > /dev/null || return 1
	docker exec "$KONTENER" chown -R www-data:www-data "$CEL_WTYCZKI"
	wp plugin activate sklep-agentow > /dev/null 2>&1 || return 1
	sleep 3
}
odpal_straznika() {
	docker cp testy/schemat-wersja-po-sprawdzeniu-test.php "$KONTENER:/tmp/a6-test.php" > /dev/null
	docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/a6-test.php 2>&1
}

postaw_stos

naglowek "KOD SPRZED NAPRAWY ($STARY_REF) — strażnik MA PAŚĆ na A"
mkdir -p "$ROBOCZY/stary"
git archive "$STARY_REF" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROBOCZY/stary" \
	|| padnij "nie umiem wyciągnąć wtyczki z $STARY_REF"
STARA="$ROBOCZY/stary/wtyczki/sklep-agentow"
if grep -q 'sklepag_schemat_braki' "$STARA/includes/aktywacja.php"; then
	padnij "$STARY_REF ZAWIERA już naprawę — to nie jest kod sprzed niej"
fi
wdroz "$STARA" || padnij "nie udało się wdrożyć wtyczki z $STARY_REF"
WY1="$(odpal_straznika)"; KOD1=$?
printf '%s\n' "$WY1" | sed 's/^/    | /'
if [ "$KOD1" -eq 2 ]; then
	zglos "strażnik oddał 2 (przyrząd/środowisko) — kalibracja nic nie zmierzyła"
elif [ "$KOD1" -eq 1 ] && printf '%s' "$WY1" | grep -q 'WYNIK=FAIL - wersja podniesiona mimo braku tabeli'; then
	potwierdz "strażnik PADŁ na kodzie sprzed naprawy na A (EXIT=$KOD1)"
else
	zglos "strażnik na kodzie sprzed naprawy oddał EXIT=$KOD1 albo padł nie tam, gdzie wada"
fi

naglowek "BIEŻĄCE DRZEWO (naprawa) — strażnik MA PRZEJŚĆ"
wdroz "wtyczki/sklep-agentow" || padnij "nie udało się wdrożyć wtyczki z drzewa"
WY2="$(odpal_straznika)"; KOD2=$?
printf '%s\n' "$WY2" | sed 's/^/    | /'
if [ "$KOD2" -eq 0 ] && printf '%s' "$WY2" | grep -q 'RAZEM: WYNIK=PASS'; then
	potwierdz "strażnik PRZESZEDŁ na naprawionym kodzie (EXIT=0)"
else
	zglos "strażnik na naprawionym kodzie oddał EXIT=$KOD2"
fi

naglowek "PODSUMOWANIE"
if [ "$BLEDY" -eq 0 ]; then
	echo "KALIBRACJA A6-F1: OK — strażnik widzi wadę i przepuszcza naprawę"
	exit 0
fi
echo "KALIBRACJA A6-F1: $BLEDY problem(y)"
exit 1
