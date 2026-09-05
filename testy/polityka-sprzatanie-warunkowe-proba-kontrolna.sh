#!/bin/bash
# polityka-sprzatanie-warunkowe-proba-kontrolna.sh — czy strażnik F4-F2 (testy/polityka-sprzatanie-warunkowe-test.php)
# NAPRAWDĘ widzi wadę: szablon polityki wraca do bezwarunkowej obietnicy „90 dni” / opis dziennika do „usuwane automatycznie”.
# Wady podkładane WYŁĄCZNIE w KOPII katalogu includes (drzewo robocze nietknięte), każda psuje DOKŁADNIE JEDEN warunek.
# Bez stosu, bez Dockera — czyta literały PHP.
# Trzy liczniki (klasa 138): BLEDY (strażnik ślepy) → EXIT 1 · KOTWICE (mutacja nie weszła) → EXIT 2 · ODWROTNA (czysty pada) → EXIT 2.
set -u
cd "$(dirname "$0")/.." || exit 2
TEST=testy/polityka-sprzatanie-warunkowe-test.php
ZRODLO=wtyczki/sklep-agentow/includes
[ -f "$TEST" ] || { echo "brak $TEST" >&2; exit 2; }
command -v php > /dev/null 2>&1 || { echo "brak php — nie zmierzono" >&2; exit 2; }
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
BLEDY=0; KOTWICE=0; ODWROTNA=0

echo "=== CZYSTY KOD — strażnik ma PRZEJŚĆ ==="
if php "$TEST" > "$T/czysty.txt" 2>&1; then echo "  OK   czysty kod (EXIT=0)"; else echo "  ZLE  czysty kod pada (EXIT=$?)"; grep ZLE "$T/czysty.txt" | sed 's/^/         /'; ODWROTNA=$((ODWROTNA+1)); fi

mutant() { # nazwa, plik, kotwica_sed_szukaj, zamien
	local nazwa="$1" plik="$2" szukaj="$3" zamien="$4"
	rm -rf "$T/m"; cp -r "$ZRODLO" "$T/m"
	if [ "$(grep -cF -- "$szukaj" "$T/m/$plik")" -eq 0 ]; then
		echo "  KOTWICA  $nazwa — kotwica [$szukaj] nie istnieje w $plik (wzorzec się zestarzał)"; KOTWICE=$((KOTWICE+1)); return
	fi
	sed -i "s|$szukaj|$zamien|" "$T/m/$plik"
	if cmp -s "$ZRODLO/$plik" "$T/m/$plik"; then echo "  KOTWICA  $nazwa — sed nie zmienił pliku"; KOTWICE=$((KOTWICE+1)); return; fi
	SKLEPAG_KATALOG_INCLUDES="$T/m" php "$TEST" > "$T/wyj.txt" 2>&1; local kod=$?
	if [ "$kod" -eq 1 ]; then echo "  OK   $nazwa — strażnik pada (EXIT=1)"; else echo "  ZLE  $nazwa — strażnik NIE zauważył (EXIT=$kod)"; BLEDY=$((BLEDY+1)); fi
}
echo "=== MUTANTY (w kopii) — strażnik ma PAŚĆ ==="
mutant "M1: polityka bez słowa WARUNKOWE"                      aktywacja.php     "sprzątanie jest WARUNKOWE:" "sprzątanie jest:"
mutant "M2: polityka bez warunku harmonogramu"                 aktywacja.php     "gdy harmonogram WordPressa działa" "zawsze"
mutant "M3: polityka: zamówienia bez zdania o braku kasowania"  aktywacja.php     "NIE są usuwane po czasie" "są usuwane po czasie"
mutant "M4: opis dziennika wraca do -usuwane automatycznie-"   admin-dziennik.php "usuwa zadanie WP-Cron sklepu (co 10 minut)" "są usuwane automatycznie"
mutant "M5: liczba dni w szablonie inna niż stała"              aktywacja.php     "starsze niż 90 dni usuwa" "starsze niż 30 dni usuwa"

echo
if [ "$ODWROTNA" -gt 0 ]; then echo "POLITYKA-PROBA: kontrola odwrotna PADLA — licznik BLEDY=$BLEDY NIEWIARYGODNY. EXIT=2"; exit 2; fi
if [ "$KOTWICE" -gt 0 ]; then echo "POLITYKA-PROBA: $KOTWICE mutacji NIE WESZLO. EXIT=2"; exit 2; fi
if [ "$BLEDY" -gt 0 ]; then echo "POLITYKA-PROBA: STRAZNIK SLEPY NA $BLEDY. EXIT=1"; exit 1; fi
echo "POLITYKA-PROBA: czysty 0, 5/5 mutantów złapanych. EXIT=0"; exit 0
