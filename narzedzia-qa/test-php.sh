#!/bin/bash
# test-php.sh — WĄSKIE okno recenzenta na URUCHOMIENIE testu PHP.     WERSJA 1.0.0
#
# PO CO TO POWSTAŁO: testy w `testy/*.php` nie są zwykłymi skryptami PHP —
# każdy zaczyna się od `WP_PLUGIN_DIR` i `require` plików wtyczki, więc bez
# WordPressa pada natychmiast:
#
#   $ php testy/import-csv-test.php
#   PHP Fatal error: Uncaught Error: Undefined constant "WP_PLUGIN_DIR"   EXIT=255
#
# Uruchamia się je wyłącznie wewnątrz kontenera, przez wp-cli:
#   docker cp testy/X.php strona_wp_wordpress:/tmp/X.php
#   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/X.php
#
# Dlatego dopisanie recenzentowi wzorca `Bash(php testy/:*)` NICZEGO by nie
# otworzyło — recenzent dostałby prawo do polecenia, które i tak zawsze pada.
# A dopisanie `Bash(docker exec:*)` oddałoby całą maszynę. Stąd ten skrypt:
# jedna nazwana operacja zamiast dockera, dokładnie jak `stos.sh`.
#
# ⛔ ZASADA, KTÓRA RZĄDZI TYM PLIKIEM: dzwoniący podaje WYŁĄCZNIE NAZWĘ
#    testu — nigdy ścieżki, nigdy flagi, nigdy kodu. Katalog `testy/` jest
#    wpisany tutaj na sztywno. Nazwa musi przejść wzorzec bez `/` i bez
#    kropek, więc `../`, ścieżka bezwzględna i podstawienie polecenia
#    odpadają zanim cokolwiek dotknie dockera. Nie ma operacji „uruchom
#    dowolny plik". Nie ma `wp eval` (kod z wiersza poleceń) — jest
#    wyłącznie `wp eval-file` na pliku, który LEŻY W TYM REPOZYTORIUM.
#
# ⚠️ To NIE jest okno czysto odczytowe, w odróżnieniu od `stos.sh`: testy
#    piszą do bazy warsztatowej (wierszami z własnym przedrostkiem, które
#    same po sobie kasują). Uruchamiamy je na stosie warsztatowym, bo po to
#    on jest — ale nie mylmy tego z czytaniem stanu.
#
# Kody wyjścia: kod testu (0 = przeszedł, 1 = nie przeszedł)
#               2 = jedno z CZTERECH, zawsze nazwane w komunikacie: (a) NIE WIEM — to konto
#                   nie widzi Dockera (o dostępie, nie o kontenerze), (b) Docker odpowiedział
#                   i kontener nie jest uruchomiony, (c) brak testu albo brak narzędzia,
#                   (d) odmowa wzorca nazwy. ⛔ (a) i (b) to RÓŻNE rzeczy.
set -u

WERSJA=1.0.0

# ⛔ Stałe, nie parametry. Dzwoniący nie wybiera ani kontenera, ani katalogu.
KONTENER_WP=strona_wp_wordpress
KATALOG_TESTOW=testy

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

odmow() {
	printf 'TEST-PHP: ODMOWA — %s\n' "$1" >&2
	exit 2
}

lista_testow() {
	local plik
	for plik in "$KORZEN/$KATALOG_TESTOW"/*.php; do
		[ -e "$plik" ] || continue
		basename "$plik"
	done | sort
}

pomoc() {
	cat <<POMOC
test-php.sh $WERSJA — uruchomienie testu PHP z katalogu $KATALOG_TESTOW/ na stosie warsztatowym.

  lista                    wypisz nazwy testow, ktore wolno uruchomic
  <nazwa-testu>            uruchom ten test (nazwa pliku, BEZ sciezki)

Przyklad:
  bash narzedzia-qa/test-php.sh import-csv-test.php
  bash narzedzia-qa/test-php.sh import-csv-test        (koncowka .php domyslna)

Nazwa musi byc golym plikiem z $KATALOG_TESTOW/: bez ukosnikow, bez '..',
bez flag. Katalog jest wpisany w skrypcie i nie da sie go podmienic.
POMOC
}

# ------------------------------------------------------------------------

case "${1-}" in
	''|pomoc|-h|--help) pomoc; exit 0 ;;
	lista)              lista_testow; exit 0 ;;
esac

NAZWA="$1"
shift
# Żadnych dalszych argumentów: gdyby wolno je było doklejać, `wp eval-file`
# dostałby flagi od dzwoniącego i cała wąskość tego okna byłaby pozorna.
[ "$#" -eq 0 ] || odmow "ten skrypt bierze DOKLADNIE jeden argument — nazwe testu, bez flag"

NAZWA="${NAZWA%.php}"
# Wzorzec jest twardy CELOWO: same male litery, cyfry i myslniki. Nie ma
# tu ukosnika ani kropki, wiec '../', sciezka bezwzgledna, '$(...)' i ';'
# odpadaja tutaj — zanim nazwa dotknie dockera.
case "$NAZWA" in
	*[!a-z0-9-]*|'') odmow "nazwa testu [$NAZWA] ma znaki spoza [a-z0-9-] — podaj sam plik z $KATALOG_TESTOW/, nie sciezke" ;;
esac

PLIK="$KORZEN/$KATALOG_TESTOW/$NAZWA.php"
if [ ! -f "$PLIK" ]; then
	printf 'TEST-PHP: ODMOWA — nie znam testu [%s.php].\n' "$NAZWA" >&2
	printf 'Wolno uruchomic:\n' >&2
	lista_testow | sed 's/^/  /' >&2
	exit 2
fi

command -v docker >/dev/null 2>&1 || odmow "nie ma polecenia docker — nie zgaduje wyniku testu"
# ⛔ „NIE WIEM” TO NIE TO SAMO CO „NIE STOI” — ten sam rozdział, co w narzedzia-qa/stos.sh.
#    `2>/dev/null` połykało `permission denied while trying to connect to the docker API`,
#    więc konto bez dostępu do `docker.sock` dostawało zdanie „stos warsztatowy nie stoi”.
#    To JEDYNA droga uruchomienia strażników PHP, więc tu kłamstwo o przyczynie kosztuje
#    najwięcej: recenzent przepisuje je do recenzji jako fakt (recenzja #268 r7).
DOCKER_WY="$( docker inspect -f '{{.State.Running}}' "$KONTENER_WP" 2>&1 )"
DOCKER_KOD=$?
if [ "$DOCKER_KOD" -ne 0 ]; then
	odmow "NIE WIEM, czy kontener $KONTENER_WP stoi — samo \`docker inspect\` padło (kod $DOCKER_KOD). To informacja o dostępie do Dockera na tym koncie, NIE o stosie. Wyjście: $DOCKER_WY"
fi
printf '%s\n' "$DOCKER_WY" | grep -qx true \
	|| odmow "kontener $KONTENER_WP nie jest uruchomiony — \`docker inspect\` odpowiedziało i podało: $DOCKER_WY"

W_KONTENERZE="/tmp/test-php-$NAZWA.php"
docker cp "$PLIK" "$KONTENER_WP:$W_KONTENERZE" >/dev/null \
	|| odmow "nie udalo sie wgrac $NAZWA.php do kontenera"

printf '=== %s.php — na stosie warsztatowym, przez wp eval-file ===\n' "$NAZWA"
KOD=0
docker exec -u www-data "$KONTENER_WP" wp eval-file "$W_KONTENERZE" || KOD=$?

# Sprzątamy ZAWSZE, także gdy test padł: plik testowy nie ma prawa zostać
# w kontenerze i mylić następnego przebiegu.
docker exec "$KONTENER_WP" rm -f "$W_KONTENERZE" >/dev/null 2>&1 || true

printf '=== %s.php — EXIT=%s ===\n' "$NAZWA" "$KOD"
exit "$KOD"
