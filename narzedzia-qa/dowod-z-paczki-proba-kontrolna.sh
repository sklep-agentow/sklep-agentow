#!/bin/bash
# dowod-z-paczki-proba-kontrolna.sh — dowód, że dowód z paczki potrafi PAŚĆ.
#
# `dowod-z-paczki.sh` mówi „paczka przeszła". Takie zdanie jest warte tyle, ile
# warta jest zdolność tego skryptu do powiedzenia „nie przeszła". Ta próba bierze
# PRAWDZIWĄ paczkę, psuje w niej po jednej rzeczy naraz — dokładnie tej, której
# dany dowód pilnuje — i sprawdza, że dowód to wyłapie I NAZWIE.
#
# Psucie odbywa się na KOPII zipa w katalogu tymczasowym. Paczka w repo nie jest
# dotykana ani razu.
#
# ⚠️ Każdy przypadek stawia czystą instalację WordPressa od zera, więc przebieg
#    trwa kilka minut. To nie jest bramka do CI, tylko kalibracja przed oddaniem.
#
# Kody wyjścia: 0 = każda podłożona wada wykryta i nazwana, czysta paczka przeszła
#               1 = choć raz dowód nie zauważył wady albo oskarżył zdrową paczkę
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOWOD="$KORZEN/narzedzia-qa/dowod-z-paczki.sh"
WTYCZKA=chatbot-live

# ⛔ PACZKA BRANA Z KATALOGU, NIE WPISANA NA SZTYWNO (znalezisko I-13, 31.08).
# Do 31.08 stało tu `chatbot-live-1.5.0.zip` — paczki o tej wersji NIE MA w repo od dawna
# (są 1.5.7 i nowsze), więc ta próba kontrolna kończyła się kodem 2 na KAŻDYM przebiegu
# i od miesiąca nie mierzyła niczego. Wyglądało to jak „pominięte", a znaczyło „martwe".
# Klasa: test odwołujący się do nośnika, który zniknął.
# Bierzemy NAJNOWSZĄ paczkę wtyczki — sortowanie wersji, nie alfabetyczne, żeby 1.5.13
# nie przegrało z 1.5.9.
ZRODLO="$(find "$KORZEN/boty/paczki" -maxdepth 1 -name "$WTYCZKA-*.zip" 2> /dev/null \
	| sort -V | tail -1)"
WERSJA_PACZKI="$(basename "${ZRODLO:-brak}" .zip | sed "s/^$WTYCZKA-//")"

if [ -z "$ZRODLO" ] || [ ! -f "$ZRODLO" ]; then
	echo "PROBA: nie znalazlem ZADNEJ paczki $WTYCZKA-*.zip w $KORZEN/boty/paczki" >&2
	exit 2
fi
echo "PROBA: pracuje na paczce $(basename "$ZRODLO")"
command -v zip > /dev/null || { echo "PROBA: brak narzedzia zip" >&2; exit 2; }

BLEDY=0
BAZA="$(mktemp -d)"
trap 'rm -rf "$BAZA"' EXIT

# psuj <nazwa-mutanta> <funkcja-psujaca> -> wypisuje sciezke do zepsutego zipa
zbuduj_mutanta() {
	# ⚠️ Osobne `local`: w jednej deklaracji `kat="$BAZA/$nazwa"` nie widzi jeszcze
	# `nazwa` i pod `set -u` cała budowa mutanta się wysypuje. Skutek był zdradliwy —
	# dowód dostawał nieistniejący plik i kończył kodem 2 („nie ma czego sprawdzać"),
	# co wygląda jak awaria narzędzia, a nie jak niewykryta wada.
	local nazwa="$1" psuj="$2"
	local kat="$BAZA/$nazwa"
	rm -rf "$kat"; mkdir -p "$kat/rozpakowane"
	unzip -q -o "$ZRODLO" -d "$kat/rozpakowane"
	"$psuj" "$kat/rozpakowane/$WTYCZKA"
	( cd "$kat/rozpakowane" && zip -q -r "$kat/$WTYCZKA-$WERSJA_PACZKI.zip" . )
	printf '%s' "$kat/$WTYCZKA-$WERSJA_PACZKI.zip"
}

# --- mutanty: każdy psuje DOKŁADNIE JEDNĄ rzecz ------------------------------

# Uninstall przestaje kasować sekret właściciela — klasa #1 ze ściągi egzaminatora.
psuj_klucz_zostaje() {
	sed -i "/delete_option( 'chatl_klucz_api' );/d" "$1/uninstall.php"
}

# Wtyczka zaczyna hałasować przy starcie: notice, którego WP_DEBUG nie przemilczy.
psuj_notice_przy_starcie() {
	printf '\n// mutant proby kontrolnej\nadd_action( "init", function () { $t = array(); echo $t["nie-ma-takiego-klucza"] ?? ""; trigger_error( "mutant proby kontrolnej", E_USER_NOTICE ); } );\n' \
		>> "$1/includes/front.php"
}

# Pełne czyszczenie przestaje być pełne: jedna opcja zostaje w bazie na zawsze.
psuj_slad_po_czyszczeniu() {
	printf '\nadd_option( "chatl_slad_mutanta", "zostaje na zawsze" );\n' >> "$1/uninstall.php"
}

# Silnik regułowy przestaje odpowiadać bez klucza API — bot „dla AI" i tylko dla AI.
psuj_regulowy_milczy() {
	sed -i 's/^function chatl_dopasuj( $pytanie ) {/function chatl_dopasuj( $pytanie ) {\n\treturn null; \/\/ mutant proby kontrolnej/' \
		"$1/includes/silnik.php"
}

# --- przebieg ------------------------------------------------------------------

sprawdz() {
	local opis="$1" oczekiwany="$2" zip="$3" fragment="${4:-}"
	local wyjscie kod=0
	printf '\n--- %s\n' "$opis"
	wyjscie="$(bash "$DOWOD" "$zip" 2>&1)" || kod=$?
	if [ "$kod" -ne "$oczekiwany" ]; then
		printf '  BLAD  %s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany"
		printf '%s\n' "$wyjscie" | grep -E 'WYSZLO|WYNIK|NIE WYSZLO' | sed 's/^/        /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	if [ -n "$fragment" ] && ! printf '%s' "$wyjscie" | grep -qF "$fragment"; then
		printf '  BLAD  %s — kod OK, ale dowod NIE NAZWAL przyczyny\n' "$opis"
		printf '        szukalam: %s\n' "$fragment"
		printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO' | sed 's/^/        /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	printf '  OK    %s (EXIT=%s)\n' "$opis" "$kod"
	printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO' | head -3 | sed 's/^/        /'
}

echo "=== KALIBRACJA DOWODU Z PACZKI — kazdy mutant psuje JEDNA rzecz ==="

sprawdz "klucz API zostaje po deinstalacji" 1 \
	"$(zbuduj_mutanta klucz psuj_klucz_zostaje)" \
	"ZOSTALY sekrety"

sprawdz "wtyczka rzuca notice przy starcie" 1 \
	"$(zbuduj_mutanta notice psuj_notice_przy_starcie)" \
	"PHP zgłosił błędy"

sprawdz "po pelnym czyszczeniu zostaje slad w bazie" 1 \
	"$(zbuduj_mutanta slad psuj_slad_po_czyszczeniu)" \
	"ROZNICA NIE JEST PUSTA"

sprawdz "silnik regulowy milczy bez klucza API" 1 \
	"$(zbuduj_mutanta regulowy psuj_regulowy_milczy)" \
	"nic nie zwrócił bez klucza API"

echo
echo "=== KONTROLA ODWROTNA: zdrowa paczka MA PRZEJSC ==="
sprawdz "paczka z repo, nietknieta" 0 "$ZRODLO"

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "DOWOD Z PACZKI JEST SKALIBROWANY — kazda podlozona wada wykryta i nazwana"
	exit 0
fi
echo "DOWOD Z PACZKI POMYLIL SIE $BLEDY RAZ(Y) — nie oddawaj go w tym stanie"
exit 1
