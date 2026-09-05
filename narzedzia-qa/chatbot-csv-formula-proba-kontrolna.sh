#!/bin/bash
# chatbot-csv-formula-proba-kontrolna.sh — próba kontrolna strażnika `testy/chatbot-csv-formula-test.php`
# (obrona przed formułą w arkuszu przy eksporcie CSV ChatBota i jej zdjęcie przy imporcie).
#
# Sam fakt, że test przechodzi, nie znaczy NIC, dopóki nie wiadomo, czy potrafi paść. Ta próba podkłada
# po jednym celowym błędzie w KOPII wtyczki (nigdy w repo): A — eksport znów nie zna tabulatora (dokładnie
# ta wada, którą naprawa zamknęła), B — import znów nie zna CR (naprawa tylko po jednej stronie = runda
# eksport→import przestaje być odwracalna). Test ma za każdym razem PAŚĆ, a czysty kod ma PRZEJŚĆ.
# Leży w `narzedzia-qa/`, bo CI woła przelot z tym zakresem — strażnik w `testy/` bez tego pliku nie biegłby nigdzie automatycznie.
#
# ⛔ TRZY LICZNIKI (klasa 138): BLEDY = strażnik nie złapał mutanta (EXIT=1) · KOTWICE = mutacja nie weszła,
#    bo kotwica sed zgniła (EXIT=2) · ODWROTNA = czysty kod nie przechodzi (EXIT=2, sprawdzana pierwsza).
# Bez Dockera i bez WordPressa — czysty PHP CLI.
#
# Użycie: bash narzedzia-qa/chatbot-csv-formula-proba-kontrolna.sh   (z katalogu repo)
set -u
cd "$(dirname "$0")/.." || exit 2
ZRODLO="wtyczki/chatbot-live/includes"
TEST="testy/chatbot-csv-formula-test.php"
[ -f "$TEST" ] || { echo "brak $TEST — nie zmierzono" >&2; exit 2; }
command -v php > /dev/null 2>&1 || { echo "brak php w PATH — nie zmierzono" >&2; exit 2; }
ROBOCZY="$(mktemp -d)"
BLEDY=0; KOTWICE=0; ODWROTNA=0; PODLOZONE=0
sprzataj() {
	# shellcheck disable=SC2317  # wołane z trap EXIT
	case "$ROBOCZY" in /tmp/*) rm -rf "$ROBOCZY" ;; esac
}
trap sprzataj EXIT
wgraj_czysty() { rm -f "$ROBOCZY"/*.php 2> /dev/null; cp "$ZRODLO"/csv.php "$ZRODLO"/import.php "$ROBOCZY/"; }

# probuj "opis" "plik" "kotwica (dokładny fragment)" "wyrażenie sed" "fragment asercji, która ma paść"
probuj() {
	local opis="$1" plik="$2" kotwica="$3" mutacja="$4" asercja="$5" przed po wyjscie kod=0 ile
	PODLOZONE=$(( PODLOZONE + 1 ))
	wgraj_czysty
	if [ "$(grep -cF -- "$kotwica" "$ROBOCZY/$plik")" -eq 0 ]; then
		printf '  KOTW mutacja NIE weszla (kotwica nie ma w co trafic): %s\n' "$opis"; KOTWICE=$(( KOTWICE + 1 )); return
	fi
	przed="$(cksum < "$ROBOCZY/$plik")"; sed -i "$mutacja" "$ROBOCZY/$plik"; po="$(cksum < "$ROBOCZY/$plik")"
	if [ "$przed" = "$po" ]; then
		printf '  KOTW mutacja NIE weszla (plik bez zmian): %s\n' "$opis"; KOTWICE=$(( KOTWICE + 1 )); return
	fi
	wyjscie="$( CHATL_KATALOG_WTYCZKI="$ROBOCZY" php "$TEST" 2>&1 )" || kod=$?
	ile="$( printf '%s\n' "$wyjscie" | grep -c '^  BLAD' )"
	if [ "$kod" -eq 1 ] && printf '%s\n' "$wyjscie" | grep -q "BLAD.*$asercja"; then
		printf '  OK   straznik PADL na: %-58s (EXIT=%s, padnietych asercji: %s)\n' "$opis" "$kod" "$ile"
	else
		printf '  BLAD straznik NIE zlapal: %-55s (EXIT=%s, padnietych asercji: %s)\n' "$opis" "$kod" "$ile"
		printf '%s\n' "$wyjscie" | grep '^  BLAD' | head -2 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

echo "=== PROBA KONTROLNA: czy straznik CSV ChatBota widzi podlozone bledy ==="
# A — eksport znów nie zna tabulatora (stan sprzed naprawy: lista = + - @ tylko)
probuj "A. eksport bez tabulatora w zestawie znakow formuly (stala)" csv.php \
	"array( '=', '+', '-', '@', \"\\t\", \"\\r\", \"'\" )" \
	"s/array( '=', '+', '-', '@', \"\\\\t\", \"\\\\r\", \"'\" )/array( '=', '+', '-', '@', \"\\\\r\", \"'\" )/" \
	'eksport: komorka od "\\t" dostaje apostrof'

probuj "B. import z WLASNA lista bez CR zamiast wspolnej stalej (rozjazd stron)" import.php \
	'in_array( $wartosc[1], CHATL_CSV_ZNAKI_FORMULY, true )' \
	"s/in_array( \\\$wartosc\\[1\\], CHATL_CSV_ZNAKI_FORMULY, true )/in_array( \\\$wartosc[1], array( '=', '+', '-', '@', \"\\\\t\" ), true )/" \
	'odwracalna dla "\\r"'

probuj "C. eksport bez apostrofu w zestawie (runda nieodwracalna dla komorki od apostrofu)" csv.php \
	"\"\\r\", \"'\" )" \
	"s/\"\\\\r\", \"'\" )/\"\\\\r\" )/" \
	'komorka od apostrofu dostaje DRUGI apostrof'

echo "=== KONTROLA ODWROTNA — czysty kod MA przejsc ==="
wgraj_czysty; kod=0
wyjscie="$( CHATL_KATALOG_WTYCZKI="$ROBOCZY" php "$TEST" 2>&1 )" || kod=$?
ile_ok="$( printf '%s\n' "$wyjscie" | grep -c '^  OK' )"
if [ "$kod" -eq 0 ] && printf '%s\n' "$wyjscie" | grep -q 'WSZYSTKIE ASERCJE PRZESZLY'; then
	printf '  OK   wtyczka z repo przechodzi wszystkie asercje (EXIT=0, asercji OK: %s)\n' "$ile_ok"
else
	printf '  BLAD czysty kod NIE przechodzi (EXIT=%s) — proba nic nie znaczy\n' "$kod"
	printf '%s\n' "$wyjscie" | grep '^  BLAD' | head -3 | sed 's/^/         /'; ODWROTNA=$(( ODWROTNA + 1 ))
fi
echo
if [ "$ODWROTNA" -gt 0 ]; then echo "CHATBOT-CSV-PROBA: kontrola odwrotna PADLA — licznik BLEDY=$BLEDY NIEWIARYGODNY. EXIT=2"; exit 2; fi
if [ "$KOTWICE" -gt 0 ]; then echo "CHATBOT-CSV-PROBA: $KOTWICE mutacji nie weszlo (kotwica zgnila). EXIT=2"; exit 2; fi
if [ "$BLEDY" -gt 0 ]; then echo "CHATBOT-CSV-PROBA: STRAZNIK SLEPY NA $BLEDY z $PODLOZONE. EXIT=1"; exit 1; fi
echo "CHATBOT-CSV-PROBA: czysty kod przechodzi, $PODLOZONE/$PODLOZONE mutanty zlapane. EXIT=0"; exit 0
