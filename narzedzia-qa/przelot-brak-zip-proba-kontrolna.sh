#!/bin/bash
# Proba kontrolna gałęzi `brak zip` w narzedzia-qa/proby-kontrolne-przelot.sh.
#
# PO CO POWSTAŁA (recenzja #272 r13, uwaga WAŻNA 1). Naprawa WYMAGA_ZIP wykonuje się
# WYŁĄCZNIE na wykonawcy, który nie ma `zip` — czyli nigdy tam, gdzie ją pisano. Przez
# dwanaście rund dowodem na nią były narzędzia jednorazowe, budowane obok kodu i kasowane
# po użyciu. Skutek zmierzony w r13: w jedynej nowej linii tej gałęzi siedziały odwrotne
# apostrofy (`` `zip` `` w łańcuchu w cudzysłowach = PODSTAWIENIE KOMENDY), więc komunikat
# gubił dokładnie to słowo, dla którego powstał, i dokładał `zip: command not found` do
# stderr. Żaden przebieg CI tego nie zobaczył, bo oba runnery `zip` mają.
#
# CO MIERZY — trzy rzeczy, każda osobno, na PRAWDZIWYM skrypcie (nie na jego kopii):
#   1. komunikat pominięcia NAZYWA brakujące narzędzie (zawiera słowo `zip`),
#   2. stderr NIE zawiera `command not found`,
#   3. próby z WYMAGA_ZIP są POMIJANE, a nie liczone jako PORAŻKA.
# Plus kontrola odwrotna: z podłożonymi odwrotnymi apostrofami asercja 1 i 2 MUSZĄ PAŚĆ.
#
# Użycie: bash narzedzia-qa/przelot-brak-zip-proba-kontrolna.sh
# Kody:   0 = strażnik widzi podłożony błąd · 1 = ślepy · 2 = nie zmierzyłam (brak warunków)
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRZELOT="$KORZEN/narzedzia-qa/proby-kontrolne-przelot.sh"
BLEDY=0
NIETRAFIONE=0

[ -f "$PRZELOT" ] || { echo "PROBA: nie ma $PRZELOT" >&2; exit 2; }

# --- atrapa PATH: wszystko poza `zip` -----------------------------------------
# Nie usuwamy `zip` z systemu — budujemy katalog z dowiązaniami do narzędzi, których
# przelot potrzebuje, i pomijamy w nim `zip`. To jedyny sposób, żeby wejść w gałąź
# `:84` na wykonawcy, który `zip` ma.
ATRAPA="$(mktemp -d)" || { echo "PROBA: nie moge zrobic katalogu atrapy" >&2; exit 2; }
ROBOCZY="$(mktemp -d)" || { echo "PROBA: nie moge zrobic katalogu zakresu" >&2; exit 2; }
trap 'rm -rf "$ATRAPA" "$ROBOCZY"' EXIT

for N in bash sh git sed awk grep find sort mktemp date printf cat rm cp mv \
         mkdir chmod head tail cut tr wc dirname basename docker php jq unzip; do
	SC="$(command -v "$N" 2> /dev/null)" && ln -sf "$SC" "$ATRAPA/$N"
done
if [ ! -e "$ATRAPA/git" ] || [ ! -e "$ATRAPA/sed" ]; then
	echo "PROBA: atrapa PATH niekompletna (brak git albo sed) — nie mierze." >&2
	exit 2
fi
if PATH="$ATRAPA" command -v zip > /dev/null 2>&1; then
	echo "PROBA: atrapa PATH NADAL widzi zip — nie mierze." >&2
	exit 2
fi

# --- zakres: tylko próby z WYMAGA_ZIP, żeby nic ciężkiego nie ruszyło ----------
# Obie i tak zostaną POMINIĘTE (brak zip), więc ten przelot nic nie uruchamia.
for N in k7-koniec-proba-kontrolna.sh paczka-zgodnosc-proba-kontrolna.sh; do
	[ -f "$KORZEN/narzedzia-qa/$N" ] || { echo "PROBA: brak $N" >&2; exit 2; }
	cp "$KORZEN/narzedzia-qa/$N" "$ROBOCZY/$N"
done

uruchom() { # uruchom <plik_logu> [skrypt] -> wypelnia WY_OUT, WY_ERR, WY_KOD
	local log="$1" skrypt="${2:-$PRZELOT}"
	WY_OUT="$(cd "$KORZEN" && PATH="$ATRAPA" bash "$skrypt" "$log" "$ROBOCZY" 2> "$ROBOCZY/err.txt")"
	WY_KOD=$?
	WY_ERR="$(cat "$ROBOCZY/err.txt")"
}

sprawdz() { # sprawdz "opis" "warunek-spelniony(0/1)"
	if [ "$2" -eq 0 ]; then
		printf '  OK   %s\n' "$1"
	else
		printf '  BLAD %s\n' "$1"
		BLEDY=$((BLEDY + 1))
	fi
}

echo "=== PROBA KONTROLNA: galaz 'brak zip' w przelocie ==="
echo

# --- 1. CZYSTY KOD: komunikat ma nazwac narzedzie -----------------------------
uruchom "$ROBOCZY/czysty.log"
WSZYSTKO="$WY_OUT
$(cat "$ROBOCZY/czysty.log" 2> /dev/null)"

echo "--- czysty kod (EXIT=$WY_KOD) ---"
echo "$WSZYSTKO" | grep -i 'pomijam\|POMINIETO' | head -2 | sed 's/^/      /'

if echo "$WSZYSTKO" | grep -q 'POMINIETO'; then
	sprawdz "proby z WYMAGA_ZIP sa POMIJANE, nie liczone jako porazka" 0
else
	sprawdz "proby z WYMAGA_ZIP sa POMIJANE, nie liczone jako porazka" 1
fi

if echo "$WSZYSTKO" | grep 'POMINIETO' | grep -q 'zip'; then
	sprawdz "komunikat pominiecia NAZYWA brakujace narzedzie (slowo 'zip')" 0
else
	sprawdz "komunikat pominiecia NAZYWA brakujace narzedzie (slowo 'zip')" 1
fi

if echo "$WY_ERR" | grep -q 'command not found'; then
	sprawdz "stderr BEZ 'command not found'" 1
	echo "$WY_ERR" | grep 'command not found' | head -2 | sed 's/^/         /'
else
	sprawdz "stderr BEZ 'command not found'" 0
fi

# --- 2. KONTROLA ODWROTNA: podkladamy z powrotem odwrotne apostrofy -----------
echo
echo "--- kontrola odwrotna: podkladam odwrotne apostrofy (stan sprzed naprawy) ---"
# ⛔ MUTUJEMY KOPIE, NIE ORYGINAL. Powod nie jest kosmetyczny: te probe uruchamia
# rowniez `proby-kontrolne-przelot.sh` (znajduje ja przez `find`), a bash czyta skrypt
# PRZYROSTOWO — podmiana pliku wykonywanego w tej chwili przez proces nadrzedny potrafi
# go rozsypac w polowie. Asercje POZYTYWNE wyzej mierza prawdziwy plik; mutanta niesie kopia.
MUTANT="$ROBOCZY/przelot-mutant.sh"
cp "$PRZELOT" "$MUTANT"
PRZED="$(cksum < "$MUTANT")"
sed -i "s|wymaga 'zip', a ten wykonawca|wymaga \`zip\`, a ten wykonawca|" "$MUTANT"
PO="$(cksum < "$MUTANT")"

if [ "$PRZED" = "$PO" ]; then
	echo "  BLAD mutacja NIE weszla do pliku — wzorzec sie zestarzal"
	NIETRAFIONE=$((NIETRAFIONE + 1))
else
	uruchom "$ROBOCZY/mutant.log" "$MUTANT"
	M_WSZYSTKO="$WY_OUT
$(cat "$ROBOCZY/mutant.log" 2> /dev/null)"
	ZLAPANE=0
	if echo "$M_WSZYSTKO" | grep 'POMINIETO' | grep -q 'zip'; then
		echo "  BLAD straznik NIE zlapal: komunikat NADAL nazywa narzedzie"
		BLEDY=$((BLEDY + 1))
	else
		echo "  OK   straznik PADL na: komunikat gubi slowo 'zip'"
		ZLAPANE=$((ZLAPANE + 1))
	fi
	if echo "$WY_ERR" | grep -q 'command not found'; then
		echo "  OK   straznik PADL na: 'command not found' w stderr"
		ZLAPANE=$((ZLAPANE + 1))
	else
		echo "  BLAD straznik NIE zlapal: stderr bez 'command not found'"
		BLEDY=$((BLEDY + 1))
	fi
	[ "$ZLAPANE" -ge 1 ] || BLEDY=$((BLEDY + 1))
fi
echo "  (oryginal NIETKNIETY — mutacja szla na kopii $MUTANT)"

echo
if [ "$NIETRAFIONE" -gt 0 ]; then
	echo "⛔ NIE ZMIERZONO: $NIETRAFIONE podmian nie trafilo w kod — wzorzec tej proby sie zestarzal."
	echo "   To wada TEJ PROBY, nie przelotu. Popraw wzorzec i uruchom ponownie."
	exit 2
fi
if [ "$BLEDY" -eq 0 ]; then
	echo "STRAZNIK WIDZI PODLOZONY BLAD (galaz 'brak zip' zmierzona na prawdziwym skrypcie)"
	exit 0
fi
echo "STRAZNIK SLEPY — $BLEDY asercji nie zadzialalo"
exit 1
