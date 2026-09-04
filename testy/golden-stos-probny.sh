#!/usr/bin/env bash
# golden-stos-probny.sh — JEDEN bieg goldena „kupujący dostaje to, co kupił” na kodzie
# z bieżącego drzewa, na stosie próbnym od zera. To jest SPUST goldena (I-24): woła go
# bramka `golden` w narzedzia-qa/bramki.sh (tryb pełny, gdy konto widzi Dockera).
#
# Użycie: bash testy/golden-stos-probny.sh [katalog-wtyczki]   (domyślnie wtyczki/sklep-agentow)
# Kody wyjścia: 0 = kupujący dostał swoje · 1 = WADA (golden padł) · 2 = przyrząd/środowisko.
# Kalibracja tego biegu (czy golden w ogóle widzi wadę): testy/golden-migawka-proba-kontrolna.sh.
set -u
# shellcheck source=testy/lib-golden-stos.sh
. "$(dirname "$0")/lib-golden-stos.sh" || exit 2
WTYCZKA="${1:-wtyczki/sklep-agentow}"
[ -f "$WTYCZKA/sklep-agentow.php" ] || padnij "w [$WTYCZKA] nie ma sklep-agentow.php"

# Zamek stosu próbnego bierze `postaw_stos` (w golden_przygotuj_stos niżej) — patrz
# narzedzia-qa/lib-stos-probny.sh: `wez_zamek_probny`. Tutaj go NIE dublujemy.
trap 'zwin_stos' EXIT INT TERM

golden_przygotuj_stos
naglowek "GOLDEN na kodzie z [$WTYCZKA] — $(grep -m1 -oE 'Version: *[0-9.]+' "$WTYCZKA/sklep-agentow.php")"
golden_wdroz "$WTYCZKA" || padnij "nie udało się wdrożyć wtyczki z [$WTYCZKA]"
WY="$(golden_odpal)"; KOD=$?
printf '%s\n' "$WY" | sed 's/^/    | /'
case "$KOD" in
	0) echo "GOLDEN: kupujący dostaje DOKŁADNIE to, co kupił (EXIT=0)" ;;
	1) echo "GOLDEN: WADA — migawka produktu nie chroni kupującego (EXIT=1)" >&2 ;;
	*) echo "GOLDEN: przyrząd/środowisko (EXIT=$KOD) — nic nie zmierzone" >&2 ;;
esac
exit "$KOD"
