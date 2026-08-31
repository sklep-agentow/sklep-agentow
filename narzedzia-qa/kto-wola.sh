#!/bin/bash
# kto-wola.sh — KTO UŻYWA funkcji, które zmieniłeś.                          WERSJA 0.1.0
#
# PO CO: etap napraw po audycie (23.08). Bramka ślepych plam warsztatu pilnuje hooków
# i skilli, a NIE kodu wtyczek — więc naprawa jednej funkcji mogła cicho unieważnić
# trzy inne miejsca. Ten skrypt nie prosi, żebyś o tym pamiętał — wypisuje listę.
#
# CO ROBI: bierze diff bieżącej gałęzi względem bazy (domyślnie origin/main), wyciąga
# nazwy funkcji, których nagłówek albo ciało się zmieniło, i dla każdej wypisuje
# WSZYSTKIE miejsca w repo, gdzie jest wołana (także jako nazwa w cudzysłowie —
# add_action/add_filter/register_*_hook/callable).
#
# UŻYCIE:  bash narzedzia-qa/kto-wola.sh [baza]      (w drzewie gałęzi naprawy)
# WYNIK:   lista do wklejenia w opis PR; przy KAŻDYM miejscu builder dopisuje:
#          „sprawdzone" / „nie dotyczy" / „naprawione razem". Nie ma „nie sprawdzałem".
# KODY:    0 = wypisano (także gdy zero zmienionych funkcji), 2 = nie jestem w repo git
#
# ⛔ Przeszukuje wtyczki/ testy/ narzedzia-qa/ audyt/ — NIE node_modules, NIE vendor.
# ⚠️ To grep po nazwie, nie analiza wywołań: złapie też wywołanie w komentarzu (lepiej
#    za dużo niż za mało — to lista do ODHACZENIA, nie wyrok).
set -u
BAZA="${1:-origin/main}"
KORZEN=$(git rev-parse --show-toplevel 2>/dev/null)
[ -n "$KORZEN" ] || { echo "kto-wola: nie jestem w repozytorium git" >&2; exit 2; }

# BAZA MUSI ISTNIEC. Przy literowce w nazwie galezi `git diff` wypisywal
# "fatal: bad revision" na stderr i ZWRACAL PUSTY wynik, a skrypt melduje wtedy
# "nie ma zmienionych funkcji" i konczy EXIT 0 — czyli "nie zmierzylam" wygladalo
# dokladnie jak "sprawdzone, czysto", i tak trafialo do opisu PR.
# Znalezisko 31.08 (rejestr infra I-7). EXIT 2 = nie zmierzylam.
if ! git rev-parse --verify --quiet "$BAZA" >/dev/null 2>&1; then
	echo "kto-wola: baza porownania [$BAZA] NIE ISTNIEJE w tym repo — nie mam wzgledem czego liczyc. EXIT 2" >&2
	echo "         (literowka w nazwie galezi? sprawdz: git branch -a | grep -i <fragment>)" >&2
	exit 2
fi
cd "$KORZEN" || exit 2

ZAKRES=( 'wtyczki/*.php' 'wtyczki/*.js' )
# 1) funkcje, których nagłówek dodano/usunięto/zmieniono
Z_NAGLOWKOW=$(git diff "$BAZA" -U0 -- "${ZAKRES[@]}" \
	| grep -E '^[-+][^-+].*\bfunction[[:space:]]+[A-Za-z_][A-Za-z0-9_]*[[:space:]]*\(' \
	| sed -E 's/.*function[[:space:]]+([A-Za-z_][A-Za-z0-9_]*).*/\1/')
# 2) funkcje, w których CIELE coś się zmieniło (git wpisuje nazwę w nagłówku hunka @@ … @@)
Z_HUNKOW=$(git diff "$BAZA" -- "${ZAKRES[@]}" \
	| grep -E '^@@.*\bfunction[[:space:]]+[A-Za-z_][A-Za-z0-9_]*' \
	| sed -E 's/.*function[[:space:]]+([A-Za-z_][A-Za-z0-9_]*).*/\1/')
FUNKCJE=$(printf '%s\n%s\n' "$Z_NAGLOWKOW" "$Z_HUNKOW" | grep -v '^$' | sort -u)

if [ -z "$FUNKCJE" ]; then
	echo "kto-wola: w diffie względem $BAZA nie ma zmienionych funkcji w wtyczki/ (zmiany poza funkcjami albo brak zmian)."
	exit 0
fi

ILE=0
for F in $FUNKCJE; do
	ILE=$(( ILE + 1 ))
	printf '\n=== %s ===\n' "$F"
	WYW=$(grep -rn --include='*.php' --include='*.js' -E "\b${F}[[:space:]]*\(" wtyczki/ testy/ narzedzia-qa/ audyt/ 2>/dev/null \
		| grep -vE "function[[:space:]]+${F}[[:space:]]*\(" )
	STR=$(grep -rn --include='*.php' -E "['\"]${F}['\"]" wtyczki/ 2>/dev/null)
	if [ -z "$WYW" ] && [ -z "$STR" ]; then
		echo "  (nikt nie woła po nazwie — sprawdź, czy to callback rejestrowany dynamicznie albo martwy kod)"
	fi
	[ -n "$WYW" ] && printf '%s\n' "$WYW" | sed 's/^/  [wywołanie] /'
	[ -n "$STR" ] && printf '%s\n' "$STR" | sed 's|^|  [jako nazwa w cudzysłowie — hook lub callback] |'
done
printf '\nkto-wola: %d zmienionych funkcji względem %s. Przy każdym miejscu wyżej dopisz: sprawdzone / nie dotyczy / naprawione razem.\n' "$ILE" "$BAZA"
exit 0
