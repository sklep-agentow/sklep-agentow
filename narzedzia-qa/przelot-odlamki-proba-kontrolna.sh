#!/usr/bin/env bash
# PRÓBA KONTROLNA ODŁAMKÓW PRZELOTU (D41 c.d., 5.09).
#
# CI dzieli przelot prób na trzy odłamki biegnące równolegle (`PRZELOT_ODLAMEK=bramki|poswiadczenia|reszta`,
# nazwane w `proby-kontrolne-przelot.sh`). Klasa, przed którą ta próba chroni: literówka w JEDNYM filtrze
# odłamka sprawia, że jakaś próba nie biegnie NIGDZIE — a każdy odłamek z osobna świeci zielono
# (klasa I-2: mianownik zero wygląda jak sukces). Dlatego mierzymy SUMĘ, nie części.
#
# Mierzone DWUKIERUNKOWO na KOPII przelotu (nie ruszamy śledzonego pliku; kopia jest nieśledzona,
# a tryb listy i tak omija kontrolę brudnego drzewa, bo niczego nie uruchamia i nie jest dowodem):
#   A. pełna lista ma co najmniej tyle prób, ile odłamków (inaczej podział nie ma sensu),
#   B. suma trzech odłamków == pełna lista (posortowane nazwy, bez duplikatów),
#   C. nieznany odłamek → kod 2 (odmowa, nie zielone),
#   D. MUTANT: literówka w filtrze odłamka `poswiadczenia` → ten odłamek daje kod 2 i suma ≠ pełna — ZŁAPANE.
# ⛔ Kod wyjścia przelotu czytamy z PLIKU, nie z `$(...)` — w podpowłoce zmienna ustawiona w funkcji ginie
#    (pierwsza wersja padła na `KOD: unbound variable`, zanim cokolwiek zmierzyła).
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRZELOT="$KORZEN/narzedzia-qa/proby-kontrolne-przelot.sh"
KOPIA="$KORZEN/narzedzia-qa/.przelot-odlamki-kopia.tmp.sh"
MUTANT="$KORZEN/narzedzia-qa/.przelot-odlamki-mutant.tmp.sh"
BLEDY=0
[ -f "$PRZELOT" ] || { echo "PROBA: nie ma $PRZELOT" >&2; exit 2; }
T="$(mktemp -d)" || { echo "PROBA: nie moge zrobic katalogu roboczego" >&2; exit 2; }
trap 'rm -f "$KOPIA" "$MUTANT"; rm -rf "$T"' EXIT INT TERM
cp "$PRZELOT" "$KOPIA"
cd "$KORZEN" || exit 2

# lista ODLAMEK PLIK_PRZELOTU PLIK_WYJSCIA → zapisuje same nazwy *.sh, zwraca kod przelotu
lista() {
	PRZELOT_LISTA=1 PRZELOT_ODLAMEK="$1" bash "$2" /dev/null narzedzia-qa 2>/dev/null | grep -E '[.]sh$' > "$3"
	return "${PIPESTATUS[0]}"
}

echo "=== A. pełna lista ==="
lista "" "$KOPIA" "$T/pelna.raw"; KOD_PELNA=$?
sort -u "$T/pelna.raw" > "$T/pelna"
ILE_PELNA=$(grep -c . "$T/pelna")
if [ "$KOD_PELNA" -eq 0 ] && [ "$ILE_PELNA" -ge 3 ]; then
	echo "  OK   pełna lista: $ILE_PELNA prób (kod $KOD_PELNA)"
else
	echo "  BLAD pełna lista: kod=$KOD_PELNA, prób=$ILE_PELNA (ma być ≥ 3)"; BLEDY=$((BLEDY+1))
fi

echo "=== B. suma odłamków == pełna lista ==="
: > "$T/suma.raw"
for O in bramki poswiadczenia reszta; do
	lista "$O" "$KOPIA" "$T/$O"; K=$?
	N=$(grep -c . "$T/$O")
	if [ "$K" -ne 0 ] || [ "$N" -lt 1 ]; then echo "  BLAD odłamek $O: kod=$K, prób=$N"; BLEDY=$((BLEDY+1)); fi
	cat "$T/$O" >> "$T/suma.raw"
done
sort -u "$T/suma.raw" > "$T/suma"
if cmp -s "$T/pelna" "$T/suma"; then
	echo "  OK   suma trzech odłamków = pełna lista ($ILE_PELNA; bramki $(grep -c . "$T/bramki") + poswiadczenia $(grep -c . "$T/poswiadczenia") + reszta $(grep -c . "$T/reszta"))"
else
	echo "  BLAD suma odłamków ≠ pełna lista:"; diff "$T/pelna" "$T/suma" | sed 's/^/         /'; BLEDY=$((BLEDY+1))
fi

echo "=== C. nieznany odłamek → kod 2 ==="
lista "nie-ma-takiego" "$KOPIA" "$T/x"; K=$?
if [ "$K" -eq 2 ]; then echo "  OK   nieznany odłamek → kod 2"; else echo "  BLAD nieznany odłamek → kod $K (ma być 2)"; BLEDY=$((BLEDY+1)); fi

echo "=== D. MUTANT: literówka w filtrze odłamka poswiadczenia ==="
KOT="FILTR_TYLKO='^bramka-poswiadczenia-proba-kontrolna[.]sh\$'"
if grep -qF -- "$KOT" "$KOPIA"; then
	sed "s/\^bramka-poswiadczenia-proba-kontrolna\[/^bramka-poswiadczeniaX-proba-kontrolna[/" "$KOPIA" > "$MUTANT"
	lista "poswiadczenia" "$MUTANT" "$T/m-poswiadczenia"; K_M=$?
	: > "$T/suma-m.raw"
	for O in bramki poswiadczenia reszta; do lista "$O" "$MUTANT" "$T/m-$O"; cat "$T/m-$O" >> "$T/suma-m.raw"; done
	sort -u "$T/suma-m.raw" > "$T/suma-m"
	if [ "$K_M" -eq 2 ] && ! cmp -s "$T/pelna" "$T/suma-m"; then
		echo "  OK   złapane: odłamek z literówką → kod $K_M, suma ≠ pełna (brakuje: $(comm -23 "$T/pelna" "$T/suma-m" | tr '\n' ' '))"
	else
		echo "  BLAD PRZESZLO NIEZAUWAZONE: mutant kod=$K_M (ma być 2), suma == pełna: $(cmp -s "$T/pelna" "$T/suma-m" && echo tak || echo nie)"; BLEDY=$((BLEDY+1))
	fi
else
	echo "  BLAD mutacja NIE weszła: w przelocie nie ma filtra [$KOT]"; BLEDY=$((BLEDY+1))
fi

echo
if [ "$BLEDY" -eq 0 ]; then echo "ODLAMKI-PROBA: suma odłamków = pełna lista, odmowy działają, mutant złapany"; exit 0; fi
echo "ODLAMKI-PROBA: BLEDY=$BLEDY" >&2; exit 1
