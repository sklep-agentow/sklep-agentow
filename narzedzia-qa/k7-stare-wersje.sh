#!/bin/bash
# k7-stare-wersje.sh — buduje paczki STARYCH wersji sklepu z historii gita,             WERSJA 1.0.0
# do testu aktualizacji W MIEJSCU (mapa K7: L4-01…L4-03).
#
# PO CO: test „migracje schematu" przez podmianę numeru w wp_options dawał fałszywy
# sukces (krytyka planu K7 r1: C1, C2, nasz krytyk D5) — dbDelta uderzał w już nową
# strukturę. Prawdziwy test = stara paczka → instalacja → dane → wgranie nowej „zastąp
# obecną". Stare paczki NIE leżą w repo (artefakt odtwarzalny tym skryptem — lekcja
# „bank poza repo zniknął": w gicie zostaje SKRYPT, nie zip).
#
# Wersje (SHA odczytane z nagłówka wtyczki w każdym commicie, 25.08):
#   0.2.0 = 9bd76e0 (schemat 3: dziennik logowań) · 0.5.0 = 8534bd0 (schemat 4: anonimizacja)
#   0.9.8 = 65a88a5 (schemat 5: opinie)
# Użycie: bash narzedzia-qa/k7-stare-wersje.sh [katalog-wyjściowy]
#         (domyślnie /root/praca/zlecenia/wymiana/k7-stare-wersje — POZA repo)
# Kody: 0 = zbudowane i sprawdzone (zip zawiera sklep-agentow/sklep-agentow.php z oczekiwaną wersją)
#       1 = rozjazd wersji w zbudowanej paczce · 2 = brak narzędzi / zły katalog
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WYJ="${1:-/root/praca/zlecenia/wymiana/k7-stare-wersje}"
mkdir -p "$WYJ" || exit 2
command -v unzip >/dev/null || { echo "brak unzip" >&2; exit 2; }
BLEDY=0
while read -r WERSJA SHA; do
	ZIP="$WYJ/sklep-agentow-$WERSJA.zip"
	git -C "$KORZEN" archive --format=zip --prefix=sklep-agentow/ -o "$ZIP" "$SHA:wtyczki/sklep-agentow" || { echo "archive padł dla $WERSJA ($SHA)" >&2; BLEDY=$((BLEDY+1)); continue; }
	W_ZIP=$(unzip -p "$ZIP" sklep-agentow/sklep-agentow.php | sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' | head -1)
	if [ "$W_ZIP" = "$WERSJA" ]; then
		printf 'OK  %s  %s  %s plikow  %s\n' "$WERSJA" "$SHA" "$(unzip -Z1 "$ZIP" | grep -vc '/$')" "$ZIP"
	else
		printf 'ROZJAZD %s: naglowek w zipie mowi [%s]\n' "$WERSJA" "$W_ZIP"; BLEDY=$((BLEDY+1))
	fi
done <<'LISTA'
0.2.0 9bd76e0
0.5.0 8534bd0
0.9.8 65a88a5
LISTA
[ "$BLEDY" -eq 0 ] && exit 0 || exit 1
