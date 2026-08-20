#!/bin/bash
# Buduje paczki produktowe sklepu (zip wtyczki + instrukcja w środku).
#
# ALLOWLIST (recenzja PR #35): do zipa wchodzą WYŁĄCZNIE pliki jawnie
# wymienione poniżej — nowy plik w katalogu wtyczki (env/git/log/bak/cokolwiek)
# ZATRZYMUJE budowę, dopóki nie zostanie świadomie dopisany do listy albo
# usunięty. Ślady wewnętrzne w paczce klienta = klasa 5 ściągi egzaminatora
# (koperta QA u kolegi) — dlatego twarde PADANIE, nie ostrzeżenie.
# Kontrola podwójna: (1) skan katalogu źródłowego przed pakowaniem,
# (2) porównanie faktycznej zawartości zipa z allowlistą po spakowaniu.
set -euo pipefail

KATALOG_SKRYPTU="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
KORZEN="$(dirname "$KATALOG_SKRYPTU")"
WYJSCIE="$KATALOG_SKRYPTU/paczki"
mkdir -p "$WYJSCIE"

# Pełna, jawna lista plików każdej paczki (ścieżki względem wtyczki/).
ALLOWLIST_CHATBOT=(
	"chatbot-live/chatbot-live.php"
	"chatbot-live/uninstall.php"
	"chatbot-live/LICENSE"
	"chatbot-live/INSTRUKCJA.md"
	"chatbot-live/DOKUMENTACJA-TECHNICZNA.md"
	"chatbot-live/DIAGRAM-PRZEPLYWU.svg"
	"chatbot-live/includes/instalator.php"
	"chatbot-live/includes/silnik.php"
	"chatbot-live/includes/ai.php"
	"chatbot-live/includes/front.php"
	"chatbot-live/includes/admin.php"
	"chatbot-live/includes/csv.php"
	"chatbot-live/includes/statystyki.php"
	"chatbot-live/assets/widget.js"
	"chatbot-live/assets/widget.css"
)
ALLOWLIST_SEGREGATOR=(
	"segregator-maila/segregator-maila.php"
	"segregator-maila/uninstall.php"
	"segregator-maila/INSTRUKCJA.md"
	"segregator-maila/includes/instalator.php"
	"segregator-maila/includes/silnik.php"
	"segregator-maila/includes/ai.php"
	"segregator-maila/includes/csv.php"
	"segregator-maila/includes/admin.php"
)

zbuduj() {
	local WTYCZKA="$1"; shift
	local ALLOWLIST=( "$@" )
	local ZRODLO="$KORZEN/wtyczki/$WTYCZKA"
	[ -d "$ZRODLO" ] || { echo "BLAD: brak katalogu $ZRODLO" >&2; exit 1; }

	# 1) Każdy plik z allowlisty musi istnieć.
	local SCIEZKA
	for SCIEZKA in "${ALLOWLIST[@]}"; do
		[ -f "$KORZEN/wtyczki/$SCIEZKA" ] || { echo "BLAD: brak pliku z allowlisty: $SCIEZKA" >&2; exit 1; }
	done

	# 2) Skan źródła: plik spoza allowlisty = STOP (świadomie dopisz albo usuń).
	local INTRUZI
	INTRUZI=$(cd "$KORZEN/wtyczki" && find "$WTYCZKA" -type f | sort | comm -23 - <(printf '%s\n' "${ALLOWLIST[@]}" | sort))
	if [ -n "$INTRUZI" ]; then
		echo "BLAD: w katalogu $WTYCZKA sa pliki SPOZA allowlisty — dopisz je swiadomie do boty/buduj-paczki.sh albo usun:" >&2
		echo "$INTRUZI" >&2
		exit 1
	fi

	# 3) Sweep treści plików z listy (ślady wewnętrzne: TODO/notatki QA/adresy dev).
	# Znaczniki w GRANICACH SŁOWA (\b...\b): bez tego "HACK" trafiało w "James
	# Hacker" z załącznika GPL w pliku LICENSE i budowa padała na tekście licencji.
	if ( cd "$KORZEN/wtyczki" && grep -niE '\bTODO\b|\bFIXME\b|\bXXX\b:|\bHACK\b|nie dla klienta|notatka (QA|recenzenck)|localhost:8890|strona_wp|sklep_mailpit' "${ALLOWLIST[@]}" ); then
		echo "BLAD: slady wewnetrzne w plikach $WTYCZKA (wyzej)" >&2
		exit 1
	fi

	# 4) Wersja z nagłówka wtyczki — nazwa zipa musi mówić prawdę.
	local WERSJA
	WERSJA=$(grep -oE 'Version: *[0-9.]+' "$ZRODLO/$WTYCZKA.php" | grep -oE '[0-9.]+')
	[ -n "$WERSJA" ] || { echo "BLAD: brak wersji w naglowku $WTYCZKA" >&2; exit 1; }

	# 5) Zip TYLKO z plików allowlisty (katalog wtyczki w korzeniu — format WP).
	local PACZKA="$WYJSCIE/$WTYCZKA-$WERSJA.zip"
	rm -f "$PACZKA"
	( cd "$KORZEN/wtyczki" && zip -q "$PACZKA" "${ALLOWLIST[@]}" )

	# 6) Kontrola po fakcie: zawartość zipa == allowlist co do pliku.
	local ROZNICA
	ROZNICA=$(diff <(unzip -Z1 "$PACZKA" | grep -v '/$' | sort) <(printf '%s\n' "${ALLOWLIST[@]}" | sort) || true)
	if [ -n "$ROZNICA" ]; then
		echo "BLAD: zawartosc zipa $WTYCZKA rozni sie od allowlisty:" >&2
		echo "$ROZNICA" >&2
		rm -f "$PACZKA"
		exit 1
	fi

	# 7) Raport: pełna lista spakowanych plików + licznik BEZ katalogów.
	local ILE_PLIKOW
	ILE_PLIKOW=$(unzip -Z1 "$PACZKA" | grep -cv '/$')
	echo "OK: $PACZKA ($(du -h "$PACZKA" | cut -f1)) — wersja $WERSJA, plikow: $ILE_PLIKOW"
	unzip -Z1 "$PACZKA" | grep -v '/$' | sort | sed 's/^/    /'
}

zbuduj chatbot-live "${ALLOWLIST_CHATBOT[@]}"
zbuduj segregator-maila "${ALLOWLIST_SEGREGATOR[@]}"
