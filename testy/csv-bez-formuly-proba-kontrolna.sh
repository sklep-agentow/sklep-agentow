#!/bin/bash
# Próba kontrolna strażnika CSV — KALIBRACJA: pada na kodzie sprzed naprawy, przechodzi po niej.
#
# ⛔ NIE DOTYKA STOSU WARSZTATOWEGO — kalibracja wdraża dwie wersje wtyczki pod rząd, a warsztat
# jest wspólny (1.09 mierzył na nim audyt). Stawia jednorazowy `probny_wp` przez
# `narzedzia-qa/lib-stos-probny.sh` i tylko jego kasuje.
#
# Użycie: bash testy/csv-bez-formuly-proba-kontrolna.sh      (ZOSTAW=tak = nie zwijaj stosu)
# Kody wyjścia: 0 = kalibracja przeszła · 1 = strażnik ślepy · 2 = przyrząd/środowisko
set -u

cd "$(dirname "$0")/.." || { echo "PRZYRZAD: nie umiem wejsc do korzenia repo" >&2; exit 2; }
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "PRZYRZAD: brak lib-stos-probny.sh" >&2; exit 2; }

STARY_REF="${STARY_REF:-origin/main}"
CEL_WTYCZKI=/var/www/html/wp-content/plugins/sklep-agentow
ROBOCZY="$(mktemp -d -t csv-kalibracja-XXXXXX)"
trap 'rm -rf "$ROBOCZY"; zwin_stos' EXIT INT TERM

wymagaj_narzedzi
git rev-parse --verify "$STARY_REF" > /dev/null 2>&1 || padnij "nie znam referencji $STARY_REF"

wdroz() {
	docker exec "$KONTENER" rm -rf "$CEL_WTYCZKI" > /dev/null 2>&1
	docker exec "$KONTENER" mkdir -p "$CEL_WTYCZKI" > /dev/null 2>&1 || return 1
	docker cp "$1/." "$KONTENER:$CEL_WTYCZKI/" > /dev/null || return 1
	docker exec "$KONTENER" chown -R www-data:www-data "$CEL_WTYCZKI"
	wp plugin activate sklep-agentow > /dev/null 2>&1 || return 1
	sleep 3   # opcache w obrazie odświeża się co 2 s
}

odpal_straznika() {
	docker cp testy/csv-bez-formuly-test.php "$KONTENER:/tmp/csv-test.php" > /dev/null
	docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/csv-test.php 2>&1
}

postaw_stos

naglowek "KOD SPRZED NAPRAWY ($STARY_REF) — strażnik MA PADAĆ"
mkdir -p "$ROBOCZY/stary"
git archive "$STARY_REF" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROBOCZY/stary" \
	|| padnij "nie umiem wyciągnąć wtyczki z $STARY_REF"
STARA="$ROBOCZY/stary/wtyczki/sklep-agentow"
# ⛔ Bez tego sprawdzenia kalibracja uruchomiona na JUŻ naprawionym mainie meldowałaby
# „strażnik ślepy" zamiast prawdy o tym, co bada.
if grep -q 'sklepag_csv_komorka' "$STARA/includes/admin-dashboard.php"; then
	padnij "$STARY_REF ZAWIERA już naprawę — to nie jest kod sprzed niej"
fi
wdroz "$STARA" || padnij "nie udało się wdrożyć wtyczki z $STARY_REF"
WY1="$(odpal_straznika)"; KOD1=$?
printf '%s\n' "$WY1" | sed 's/^/    | /'
if [ "$KOD1" -eq 2 ]; then
	zglos "strażnik oddał 2 (przyrząd/środowisko) — kalibracja nic nie zmierzyła"
elif [ "$KOD1" -eq 1 ] \
	&& printf '%s' "$WY1" | grep -q 'NIE WYSZLO  A: adres kupującego wychodzi do arkusza JAKO FORMUŁA' \
	&& printf '%s' "$WY1" | grep -q 'NIE WYSZLO  B: nazwa produktu wychodzi do arkusza JAKO FORMUŁA' \
	&& printf '%s' "$WY1" | grep -q 'WYSZLO      C:' \
	&& printf '%s' "$WY1" | grep -q 'WYSZLO      D:'; then
	# ⛔ Wymagamy KSZTAŁTU: padają OBA źródła (kupujący i właściciel), a zwykły wiersz i kolumna
	# Kwota przechodzą. Sam czerwony wynik mógłby znaczyć, że zepsuliśmy eksport w ogóle.
	potwierdz "strażnik PADŁ na kodzie sprzed naprawy (EXIT=1): padają A i B (oba źródła), C i D przechodzą"
else
	zglos "strażnik nie padł w oczekiwanym kształcie (EXIT=$KOD1) — nie mierzy tej wady"
fi

naglowek "KOD Z DRZEWA — strażnik MA PRZEJŚĆ"
wdroz wtyczki/sklep-agentow || padnij "nie udało się wdrożyć wtyczki z drzewa"
WY2="$(odpal_straznika)"; KOD2=$?
printf '%s\n' "$WY2" | sed 's/^/    | /'
if [ "$KOD2" -eq 0 ]; then
	potwierdz "strażnik PRZESZEDŁ na kodzie z drzewa (EXIT=0)"
else
	zglos "strażnik NIE przeszedł na kodzie z drzewa (EXIT=$KOD2) — naprawa nie zamyka wady"
fi

naglowek "WYNIK KALIBRACJI"
if [ "$BLEDY" -eq 0 ]; then
	echo "KALIBRACJA: OK — straznik pada przed naprawa (EXIT=1) i przechodzi po niej (EXIT=0)"
	exit 0
fi
echo "KALIBRACJA: NIE WYSZLA — $BLEDY potkniec"
exit 1
