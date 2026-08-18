#!/bin/bash
# Buduje paczki produktowe sklepu (zip wtyczki + instrukcja w środku).
# Przed spakowaniem SWEEP śladów wewnętrznych (lekcja catsnboard: koperta QA
# w paczce klienta): TODO/FIXME/notatki QA/adresy dev/pliki robocze = STOP.
set -euo pipefail

KATALOG_SKRYPTU="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
KORZEN="$(dirname "$KATALOG_SKRYPTU")"
WYJSCIE="$KATALOG_SKRYPTU/paczki"
mkdir -p "$WYJSCIE"

for WTYCZKA in chatbot-live segregator-maila; do
	ZRODLO="$KORZEN/wtyczki/$WTYCZKA"
	[ -d "$ZRODLO" ] || { echo "BLAD: brak katalogu $ZRODLO" >&2; exit 1; }

	# 1) Sweep plików roboczych.
	SMIECI=$(find "$ZRODLO" -name '*.bak' -o -name '*.orig' -o -name '*.swp' -o -name '.DS_Store' | wc -l)
	[ "$SMIECI" -eq 0 ] || { echo "BLAD: pliki robocze w $WTYCZKA" >&2; exit 1; }

	# 2) Sweep śladów wewnętrznych w treści (TODO/FIXME/notatki QA/adresy dev).
	if grep -rniE 'TODO|FIXME|XXX:|HACK|nie dla klienta|notatka (QA|recenzenck)|localhost:8890|strona_wp|sklep_mailpit' "$ZRODLO"; then
		echo "BLAD: slady wewnetrzne w $WTYCZKA (wyzej)" >&2
		exit 1
	fi

	# 3) Wersja z nagłówka wtyczki — nazwa zipa musi mówić prawdę.
	WERSJA=$(grep -oE 'Version: *[0-9.]+' "$ZRODLO/$WTYCZKA.php" | grep -oE '[0-9.]+')
	[ -n "$WERSJA" ] || { echo "BLAD: brak wersji w naglowku $WTYCZKA" >&2; exit 1; }

	# 4) Zip z katalogiem wtyczki w korzeniu (tego oczekuje instalator WP).
	PACZKA="$WYJSCIE/$WTYCZKA-$WERSJA.zip"
	rm -f "$PACZKA"
	( cd "$KORZEN/wtyczki" && zip -qr "$PACZKA" "$WTYCZKA" )
	echo "OK: $PACZKA ($(du -h "$PACZKA" | cut -f1)) — wersja $WERSJA, plikow: $(unzip -l "$PACZKA" | tail -1 | awk '{print $2}')"
done
