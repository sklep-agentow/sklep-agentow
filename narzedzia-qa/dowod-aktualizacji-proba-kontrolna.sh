#!/bin/bash
# dowod-aktualizacji-proba-kontrolna.sh — dowód, że dowód aktualizacji potrafi PAŚĆ.
#
# Trzy mutanty, każdy psuje dokładnie jedną awarię, która u klienta zdarza się
# naprawdę przy podmianie plików:
#   1. nowa wersja NIE MA kodu migracji — schemat zostaje stary, a kod pyta
#      o kolumnę, której nie ma (klasyczna awaria „u mnie działa, bo miałem
#      czystą instalację"),
#   2. migracja KASUJE starą kolumnę — miejsce na dane klienta znika,
#   3. migracja CZYŚCI tabelę — dane klienta giną przy aktualizacji.
#
# Psucie na kopii zipa w katalogu tymczasowym; paczki w repo nietknięte.
# Każdy przypadek stawia WordPressa od zera, więc przebieg trwa kilkanaście minut.
#
# Kody wyjścia: 0 = każda podłożona awaria wykryta i nazwana, zdrowa para przeszła
#               1 = choć raz dowód nie zauważył awarii albo oskarżył zdrową parę
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
DOWOD="$KORZEN/narzedzia-qa/dowod-aktualizacji.sh"
WTYCZKA=chatbot-live
STARA=1.4.9
NOWA="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' "wtyczki/$WTYCZKA/$WTYCZKA.php" | head -1)"
PACZKA_NOWA="boty/paczki/$WTYCZKA-$NOWA.zip"

[ -f "$PACZKA_NOWA" ] || { echo "PROBA: nie ma paczki $PACZKA_NOWA" >&2; exit 2; }
command -v zip > /dev/null || { echo "PROBA: brak narzedzia zip" >&2; exit 2; }

BLEDY=0
BAZA="$(mktemp -d)"
ORYGINAL="$BAZA/oryginal.zip"
cp "$PACZKA_NOWA" "$ORYGINAL"
# Mutanty muszą wejść pod TĄ SAMĄ nazwą, bo dowód szuka paczki po wersji
# z nagłówka. Podmieniamy plik w repo na czas próby i ZAWSZE przywracamy.
przywroc() { cp "$ORYGINAL" "$PACZKA_NOWA"; rm -rf "$BAZA"; }
trap przywroc EXIT

podloz_mutanta() {
	local psuj="$1" kat="$BAZA/m"
	rm -rf "$kat"; mkdir -p "$kat/r"
	unzip -q -o "$ORYGINAL" -d "$kat/r"
	"$psuj" "$kat/r/$WTYCZKA"
	rm -f "$PACZKA_NOWA"
	( cd "$kat/r" && zip -q -r "$KORZEN/$PACZKA_NOWA" . )
}

# --- mutanty ------------------------------------------------------------------

# Nowa wersja przestaje dogadniać schemat: porównanie wersji wycięte.
# Hook aktywacji i tak się nie odpali, więc tabela nigdy nie powstanie.
psuj_brak_migracji() {
	sed -i "s/if ( get_option( 'chatl_wersja_schematu' ) !== CHATL_WERSJA_SCHEMATU ) {/if ( false ) { \/\/ mutant proby kontrolnej/" \
		"$1/chatbot-live.php"
}

# Migracja dokłada nowe, ale przy okazji kasuje starą kolumnę.
psuj_kasuje_kolumne() {
	printf '\nadd_action( "plugins_loaded", function () { global $wpdb; $wpdb->query( "ALTER TABLE {$wpdb->prefix}chatl_qa DROP COLUMN IF EXISTS aktywne" ); }, 99 );\n' \
		>> "$1/includes/instalator.php"
}

# Migracja „porządkuje" tabelę i przy okazji kasuje dane klienta.
psuj_czysci_dane() {
	printf '\nadd_action( "plugins_loaded", function () { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_qa" ); }, 99 );\n' \
		>> "$1/includes/instalator.php"
}

nie_psuj() { :; }

# --- przebieg ------------------------------------------------------------------

sprawdz() {
	local opis="$1" oczekiwany="$2" psuj="$3" fragment="${4:-}"
	local wyjscie kod=0
	printf '\n--- %s\n' "$opis"
	podloz_mutanta "$psuj"
	wyjscie="$(bash "$DOWOD" "$WTYCZKA" "$STARA" 2>&1)" || kod=$?
	cp "$ORYGINAL" "$PACZKA_NOWA"
	if [ "$kod" -ne "$oczekiwany" ]; then
		printf '  BLAD  %s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany"
		printf '%s\n' "$wyjscie" | grep -E 'WYSZLO|AKTUALIZACJA' | sed 's/^/        /'
		BLEDY=$(( BLEDY + 1 )); return
	fi
	if [ -n "$fragment" ] && ! printf '%s' "$wyjscie" | grep -qF "$fragment"; then
		printf '  BLAD  %s — kod OK, ale dowod NIE NAZWAL przyczyny\n' "$opis"
		printf '        szukalam: %s\n' "$fragment"
		printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO' | sed 's/^/        /'
		BLEDY=$(( BLEDY + 1 )); return
	fi
	printf '  OK    %s (EXIT=%s)\n' "$opis" "$kod"
	printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO' | head -3 | sed 's/^/        /'
}

echo "=== KALIBRACJA DOWODU AKTUALIZACJI ($WTYCZKA $STARA → $NOWA) ==="

sprawdz "nowa wersja nie ma kodu migracji"    1 psuj_brak_migracji  "wersja schematu NIE DRGNELA"
sprawdz "migracja kasuje stara kolumne"       1 psuj_kasuje_kolumne "ze schematu UBYLO"
sprawdz "migracja czysci dane klienta"        1 psuj_czysci_dane    "NIE PRZETRWAL"

echo
echo "=== KONTROLA ODWROTNA: zdrowa para wersji MA PRZEJSC ==="
sprawdz "paczki z repo, nietkniete"           0 nie_psuj

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "DOWOD AKTUALIZACJI JEST SKALIBROWANY — kazda podlozona awaria wykryta i nazwana"
	exit 0
fi
echo "DOWOD AKTUALIZACJI POMYLIL SIE $BLEDY RAZ(Y) — nie oddawaj go w tym stanie"
exit 1
