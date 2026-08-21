#!/bin/bash
# test-php-proba-kontrolna.sh — dowód, że okno na URUCHAMIANIE testów jest wąskie.
#
# test-php.sh daje recenzentowi prawo uruchomienia kodu na warsztatowym
# WordPressie. Takie prawo jest warte tyle, ile wart jest wzorzec nazwy:
# jeśli da się przez nie podać ścieżkę, flagę albo własne polecenie, to nie
# jest pomocnik, tylko `docker exec` w przebraniu. Ta próba podkłada
# skryptowi po kolei każdą próbę wyjścia poza katalog `testy/` i sprawdza,
# że KAŻDA kończy się odmową z kodem 2 — a osobno, że prawdziwy test
# naprawdę się uruchamia (kontrola odwrotna).
#
# Kody wyjścia: 0 = wszystkie odmowy odmówione, kontrola odwrotna przeszła
#               1 = choć raz zachował się nie tak, jak ma
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NARZ="$KORZEN/narzedzia-qa/test-php.sh"
BLEDY=0

odmowa() {
	local opis="$1"; shift
	local kod=0 wyjscie
	wyjscie="$(bash "$NARZ" "$@" 2>&1)" || kod=$?
	if [ "$kod" -eq 2 ] && printf '%s' "$wyjscie" | grep -q 'ODMOWA'; then
		printf '  OK   %-58s (EXIT=2)\n' "$opis"
	else
		printf '  BLAD %-58s (EXIT=%s, oczekiwano 2 z ODMOWA)\n' "$opis" "$kod"
		printf '%s\n' "$wyjscie" | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

# Sam kod 2 to za malo. Skrypt ma odmowic NA WZORCU NAZWY, zanim cokolwiek
# dotknie dockera — inaczej "odmowa" bywa przypadkiem (np. docker cp nie
# znajduje sciezki docelowej) i przestaje odrozniac okno waskie od dziurawego.
# To ta sama zasada, co w pozostalych probach: sprawdzaj kod wyjscia ORAZ to,
# czy dowod nazwal przyczyne.
odmowa_wzorca() {
	local opis="$1"; shift
	local kod=0 wyjscie
	wyjscie="$(bash "$NARZ" "$@" 2>&1)" || kod=$?
	if [ "$kod" -eq 2 ] && printf '%s' "$wyjscie" | grep -q 'ma znaki spoza'; then
		printf '  OK   %-58s (EXIT=2, odrzucone na wzorcu nazwy)\n' "$opis"
	else
		printf '  BLAD %-58s (EXIT=%s, oczekiwano odmowy NA WZORCU)\n' "$opis" "$kod"
		printf '%s\n' "$wyjscie" | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

echo "=== SCIEZKA ZAMIAST NAZWY — MA BYC ODMOWA NA WZORCU ==="
# NAJWAZNIEJSZY z tych przypadkow: sciezka, ktora po doklejeniu '.php'
# TRAFIA W ISTNIEJACY plik poza testy/. Reszta prob wyjscia konczy sie
# odmowa nawet przy poluzowanym wzorcu (bo '/etc/passwd.php' nie istnieje),
# wiec sama by nie odroznila okna waskiego od dziurawego.
odmowa_wzorca "wyjscie na ISTNIEJACY plik .php poza testy/" "../wtyczki/chatbot-live/chatbot-live"
odmowa_wzorca "to samo, plik uninstall drugiej wtyczki"  "../wtyczki/segregator-maila/uninstall"
odmowa_wzorca "wyjscie z katalogu przez ../"            "../narzedzia-qa/bramki.sh"
odmowa_wzorca "wyjscie z katalogu przez ../../"         "../../etc/passwd"
odmowa_wzorca "sciezka bezwzgledna"                     "/etc/passwd"
odmowa_wzorca "podkatalog testow"                       "lib/pomocnik"
odmowa_wzorca "sama kropka"                             "."

echo
echo "=== PRZEMYCENIE POLECENIA W NAZWIE — MA BYC ODMOWA NA WZORCU ==="
odmowa_wzorca "srednik i drugie polecenie"              "import-csv-test; id"
odmowa_wzorca "podstawienie polecenia"                  'import-csv-$(id)-test'
odmowa_wzorca "odwrotny apostrof"                       'import-csv-`id`-test'
odmowa_wzorca "potok"                                   "import-csv-test | id"
odmowa_wzorca "spacja i flaga wp-cli"                   "import-csv-test --allow-root"

echo
echo "=== FLAGI I NADMIAROWE ARGUMENTY — MA BYC ODMOWA ==="
odmowa "flaga zamiast nazwy"                     "--allow-root"
odmowa "drugi argument doklejony do nazwy"       "import-csv-test" "--allow-root"
odmowa "dwie nazwy naraz"                        "import-csv-test" "rodo-test"

echo
echo "=== TEST SPOZA KATALOGU testy/ — MA BYC ODMOWA Z LISTA ==="
kod=0
wyjscie="$(bash "$NARZ" nie-ma-takiego-testu 2>&1)" || kod=$?
if [ "$kod" -eq 2 ] && printf '%s' "$wyjscie" | grep -q 'import-csv-test.php'; then
	printf '  OK   %-58s (EXIT=2, wypisal liste)\n' "nieznany test: odmowa I lista dozwolonych"
else
	printf '  BLAD %-58s (EXIT=%s)\n' "nieznany test: odmowa I lista dozwolonych" "$kod"
	BLEDY=$(( BLEDY + 1 ))
fi

echo
echo "=== ZERO DROGI NA SKROTY — sprawdzane w tresci skryptu ==="
# `wp eval` bierze KOD z wiersza polecen. Gdyby kiedys tu wladowal, caly
# wzorzec nazwy stalby sie ozdoba — dlatego wolno wylacznie `wp eval-file`.
# Komentarze odsiewamy: ten plik SAM tlumaczy, czego tu nie ma, wiec
# grep po calej tresci lapalby wlasne wyjasnienie zamiast kodu.
KOD_SKRYPTU="$(grep -vE '^[[:space:]]*#' "$NARZ")"
if printf '%s\n' "$KOD_SKRYPTU" | grep -nE 'wp eval([^-]|$)'; then
	echo "  BLAD w test-php.sh jest 'wp eval' (kod z wiersza polecen), a wolno tylko 'wp eval-file'"
	BLEDY=$(( BLEDY + 1 ))
else
	echo "  OK   zadnego 'wp eval' — wylacznie 'wp eval-file' na pliku z repozytorium"
fi
# Katalog testow ma byc STALA. Gdyby przyszedl z argumentu, ten skrypt
# przestalby cokolwiek ograniczac.
if grep -qE '^KATALOG_TESTOW=testy$' "$NARZ"; then
	echo "  OK   katalog testow wpisany na sztywno, nie przychodzi z argumentu"
else
	echo "  BLAD katalog testow w test-php.sh nie jest juz stala"
	BLEDY=$(( BLEDY + 1 ))
fi
ILE_DOCKER="$(printf '%s\n' "$KOD_SKRYPTU" | grep -c 'docker ')"
if [ "$ILE_DOCKER" -gt 5 ]; then
	printf '  BLAD wywolan dockera w test-php.sh jest %s — przybylo obejscie?\n' "$ILE_DOCKER"
	BLEDY=$(( BLEDY + 1 ))
else
	printf '  OK   wywolania dockera policzone: %s (limit 5)\n' "$ILE_DOCKER"
fi

echo
echo "=== KONTROLA ODWROTNA — prawdziwy test MA sie uruchomic ==="
# Bez tego cala proba przechodzilaby na skrypcie, ktory odmawia WSZYSTKIEGO.
echo "  lista dozwolonych testow:"
bash "$NARZ" lista | sed 's/^/         /'
kod=0
wyjscie="$(bash "$NARZ" import-csv-test 2>&1)" || kod=$?
if [ "$kod" -eq 0 ] && printf '%s' "$wyjscie" | grep -q 'WSZYSTKIE KROKI PRZESZLY'; then
	printf '  OK   %-58s (EXIT=0)\n' "import-csv-test.php uruchomiony i przeszedl"
else
	printf '  BLAD %-58s (EXIT=%s)\n' "import-csv-test.php uruchomiony i przeszedl" "$kod"
	printf '%s\n' "$wyjscie" | tail -5 | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
fi

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "OKNO NA URUCHAMIANIE TESTOW JEST WASKIE — kazda proba wyjscia poza testy/ odrzucona, prawdziwy test przeszedl"
	exit 0
fi
echo "TEST-PHP.SH ZACHOWAL SIE ZLE $BLEDY RAZ(Y) — nie oddawaj go recenzentowi w tym stanie"
exit 1
