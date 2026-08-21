#!/bin/bash
# BRAMKA: ksiegowosc znalezisk audytu.
#
# Po co powstala (21.08): mielismy zasade "kazdy blad dostaje straznika z exit 1
# wpiety do CI" i NIE BYLA SPELNIONA ANI RAZU. W testy/ lezalo 57 plikow, CI nie
# uruchamialo zadnego, a PR #135 wniosl dwa CELOWO PADAJACE testy i przeszedl
# na zielono. Wszystko bylo doradcze.
#
# Czego ta bramka NIE robi: nie uruchamia testow. Wszystkie wymagaja zywego stosu
# Dockera (psuja kod w kontenerze), wiec w rownoleglym CI deptalyby sobie po stosie.
# Pilnuje KSIEGOWOSCI: ze znalezisko istnieje, ma test, ma status i nikt go po cichu
# nie usunal.
#
# Uzycie: bash narzedzia-qa/bramka-znaleziska.sh   (z katalogu repo)
set -u

REJ=audyt/REJESTR-ZNALEZISK.md
BLEDY=0
KONTROLE=0

zglos() { printf '  BLAD %s\n' "$1" >&2; BLEDY=$((BLEDY+1)); }
ok()    { printf '  OK   %s\n' "$1"; }
licz()  { KONTROLE=$((KONTROLE+1)); }

echo "=== BRAMKA ZNALEZISK ==="

# 1. Rejestr musi istniec. Bez niego reszta kontroli nie ma czego czytac.
licz
if [ ! -f "$REJ" ]; then
	zglos "rejestr nie istnieje: $REJ"
	echo "WYKONANE KONTROLE: $KONTROLE, BLEDY: $BLEDY" >&2
	exit 1
fi
ok "rejestr istnieje"

# 2. Liczba OTWARTYCH z naglowka musi zgadzac sie z liczba wierszy o tym statusie.
#    Dopisanie znaleziska ZATRZYMUJE bramke, dopoki ktos swiadomie nie podniesie liczby.
licz
DEKLAROWANE=$(grep -oP '^## Liczba znalezisk OTWARTYCH: \K[0-9]+' "$REJ" | head -1)
POLICZONE=$(grep -cP '^\| `[^`]+` \|.*\| OTWARTE \|$' "$REJ")
if [ -z "$DEKLAROWANE" ]; then
	zglos "brak naglowka z liczba znalezisk OTWARTYCH"
elif [ "$DEKLAROWANE" != "$POLICZONE" ]; then
	zglos "liczba OTWARTYCH: naglowek mowi [$DEKLAROWANE], wierszy jest [$POLICZONE]"
else
	ok "liczba OTWARTYCH zgodna z naglowkiem = $POLICZONE"
fi

# 3. Kazdy test wymieniony w rejestrze musi ISTNIEC jako plik.
#    Martwy odsylacz do testu jest gorszy niz brak testu — udaje, ze straznik stoi.
SPRAWDZONYCH=0
while IFS='|' read -r _ KOD _ TEST STATUS _; do
	KOD=$(echo "$KOD" | tr -d ' `')
	TEST=$(echo "$TEST" | tr -d ' `')
	STATUS=$(echo "$STATUS" | tr -d ' ')
	[ -n "$KOD" ] || continue
	SPRAWDZONYCH=$((SPRAWDZONYCH+1))
	licz
	if [ ! -f "$TEST" ]; then
		zglos "znalezisko $KOD wskazuje test, ktorego NIE MA: $TEST"
	else
		ok "$KOD -> $TEST ($STATUS)"
	fi
done < <(grep -P '^\| `[^`]+` \|' "$REJ")

# 3b. Sama petla nie wystarczy. Gdyby wzorzec wiersza przestal pasowac (zmiana ukladu
#     tabeli), petla nie zrobilaby ANI JEDNEGO sprawdzenia, a bramka swiecilaby zielono.
#     Ta sama pulapka, ktora zlapala nas przy tabeli stalych w dokumentacja-zgodnosc.sh.
licz
if [ "$SPRAWDZONYCH" -lt 1 ]; then
	zglos "wzorzec wiersza nie zlapal ANI JEDNEGO znaleziska — tabela zmienila uklad?"
else
	ok "wierszy znalezisk odczytanych z tabeli = $SPRAWDZONYCH"
fi

# 4. Kazdy raport audytu musi byc wymieniony w rejestrze. Inaczej nowy raport wnosi
#    znaleziska, ktore nigdzie nie sa policzone — dokladnie to, co sie nam stalo.
for RAPORT in audyt/boty/RAPORT-*.md; do
	[ -e "$RAPORT" ] || continue
	NAZWA=$(basename "$RAPORT")
	licz
	if grep -qF "$NAZWA" "$REJ"; then
		ok "raport objety rejestrem: $NAZWA"
	else
		zglos "raport POZA rejestrem: $NAZWA — jego znaleziska nie sa policzone"
	fi
done

# 5. Status musi byc jednym z trzech znanych. Wymyslony status = znalezisko, ktore
#    wypada z ksiegowosci, bo zadna kontrola go nie liczy.
licz
ZLE_STATUSY=$(grep -P '^\| `[^`]+` \|' "$REJ" | awk -F'|' '{gsub(/ /,"",$5); print $5}' \
	| grep -vcE '^(OTWARTE|WNAPRAWIE|ZAMKNIETE)$')
if [ "$ZLE_STATUSY" != "0" ]; then
	zglos "znalezisk z nieznanym statusem: $ZLE_STATUSY (dozwolone: OTWARTE, W NAPRAWIE, ZAMKNIETE)"
else
	ok "wszystkie statusy sa ze znanego zbioru"
fi

echo
echo "WYKONANE KONTROLE: $KONTROLE"
if [ "$BLEDY" -gt 0 ]; then
	echo "NIEZALICZONE: $BLEDY" >&2
	exit 1
fi
echo "KSIEGOWOSC ZNALEZISK ZGODNA."
