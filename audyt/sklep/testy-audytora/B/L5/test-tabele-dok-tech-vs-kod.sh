#!/bin/bash
# L5 — runda B — commit_head 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d
# PADA NA OBECNYM KODZIE: DOKUMENTACJA-TECHNICZNA.md §8 mówi "utworzenie 6 tabel przez
# dbDelta", a §3 (i realny kod aktywacja.php) tworzy 7 tabel. Test liczy realne
# CREATE TABLE w aktywacja.php i porównuje z liczbą zadeklarowaną w §8 dokumentu.
# Nieniszczący (tylko odczyt plików paczki, zero zmian na stosie).
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
PACZKA="${1:-$KORZEN/wtyczki/sklep-agentow}"

REALNE=$(grep -c "CREATE TABLE" "$PACZKA/includes/aktywacja.php")
DEKLAROWANE_W_S8=$(sed -n '/## 8\. Instalacja i deinstalacja/,/^---$/p' "$PACZKA/DOKUMENTACJA-TECHNICZNA.md" | grep -oE '[0-9]+ tabel' | grep -oE '[0-9]+' | head -1)

echo "CREATE TABLE w aktywacja.php (realne): $REALNE"
echo "Liczba zadeklarowana w DOK-TECH §8:     $DEKLAROWANE_W_S8"

if [ "$REALNE" != "$DEKLAROWANE_W_S8" ]; then
	echo "PADA: rozjazd $REALNE (kod) vs $DEKLAROWANE_W_S8 (DOK-TECH §8) — dokumentacja kłamie o liczbie tabel tworzonych przy aktywacji."
	exit 1
fi
echo "OK: zgodne."
exit 0
