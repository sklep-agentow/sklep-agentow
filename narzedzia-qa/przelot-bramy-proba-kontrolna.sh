#!/bin/bash
# PROBA KONTROLNA BRAM PRZELOTU: podloga (MIN_URUCHOMIONYCH) i sufity per odlamek.
#
# PO CO (recenzja #290 WAZNA 2). `grep -rn MIN_URUCHOMIONYCH narzedzia-qa/ testy/` trafial
# WYLACZNIE w sam przelot — zadna *proba-kontrolna*.sh nie dotykala nowej bramy (podloga
# ORAZ sufity per odlamek `bramki`/`poswiadczenia`, oba MAX_POMINIETYCH=0). Do tego bariera
# strukturalna: `przelot-odlamki-proba-kontrolna.sh` wola przelot WYLACZNIE w trybie
# PRZELOT_LISTA=1, ktory konczy sie PRZED petla uruchamiajaca proby i przed obiema bramami
# (`proby-kontrolne-przelot.sh:262`) — te cztery przypadki fizycznie nie mogly zmierzyc
# ani sufitu, ani podlogi. Ta proba mierzy PRAWDZIWE URUCHOMIENIE (bez trybu listy).
#
# SPOSOB — BEZ uruchamiania prawdziwych prob (czas: prawdziwy przelot ~15 min). Kazdy
# przypadek dostaje wlasny FALSZYWY KORZEN repozytorium w /tmp (kopia przelotu lezy w
# "$FALSZYWY_KORZEN/narzedzia-qa/...", bo przelot liczy swoj KORZEN ze SCIEZKI WLASNEGO
# PLIKU — ta sama zasada co mutant w przelot-brak-zip-proba-kontrolna.sh) wypelniony
# ATRAPAMI *proba-kontrolna*.sh (tresc `exit 0`/`exit 2`). Falszywy korzen NIE jest
# repozytorium git, wiec kontrola brudnego drzewa (`git status --porcelain -uno`) milczy
# (polecenie pada poza repo, stderr wyciszony, stdout puste) — nie musimy nic commitowac,
# a mutacje w kopiach nigdy nie dotykaja prawdziwego repo.
#
# Przypadki (katalog-zakres NAZWANY `narzedzia-qa`, zeby podloga/odlamki w ogole
# obowiazywaly — inaczej `proby-kontrolne-przelot.sh:225-228`/`:243-249` odmawiaja albo
# licza podlogo=0):
#   A. 14 atrap exit 0 (rowno podloga MIN_URUCHOMIONYCH_ZAKRES) -> EXIT=0
#   B. 10 atrap exit 0 + 1 atrapa nazwana `dowod-zachowania-segregator-proba-kontrolna.sh`
#      (POMIJANA BEZWARUNKOWO, tresc bez znaczenia) -> uruchomionych 10 < 14 -> EXIT=1
#      z wierszem o podlodze
#   C. odlamek `bramki` (sufit pominiec/niezmierzonych = 0), JEDNA atrapa
#      `bramki-proba-kontrolna.sh` dajaca exit 2 -> EXIT=1
#   D. MUTANT: w kopii przelotu z przypadku B zamieniam `MIN_URUCHOMIONYCH_ZAKRES=<N>` na
#      `=0` (kotwica sprawdzona `grep -cF` PRZED mutacja, klasa 138) -> przypadek B
#      PRZESTAJE PADAC (podloga=0 nigdy nie chwyta) -> to jest OCZEKIWANA slepota,
#      dowod ze podloga realnie chroni; jesli mutacja NIE zmienia wyniku, kalibracja
#      tej proby jest zla.
#
# Trzy liczniki (klasa 138 — BLEDY/KOTWICE/ODWROTNA sa ROZNYMI rzeczami):
#   BLEDY    brama nie zachowala sie jak oczekiwano (przypadek B/C/D)  -> EXIT=1
#   KOTWICE  kotwica sed/grep zgnila, mutacja D nic nie zmienila       -> EXIT=2
#   ODWROTNA przypadek A (kontrola pozytywna) nie przechodzi           -> EXIT=2
set -u

KORZEN_PRAWDZIWY="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRZELOT_ORYGINAL="$KORZEN_PRAWDZIWY/narzedzia-qa/proby-kontrolne-przelot.sh"
[ -f "$PRZELOT_ORYGINAL" ] || { echo "PROBA: nie ma $PRZELOT_ORYGINAL" >&2; exit 2; }

BLEDY=0
KOTWICE=0
ODWROTNA=0

# ⛔ KLASA 138: TRZY rozne rzeczy = TRZY liczniki. Wspolny licznik kaze skryptowi orzec
#    o BRAMIE rzecz, ktorej NIE ZMIERZYL.
podsumowanie_proby() {           # $1 = komunikat, gdy wszystko zmierzone i zielone
	if [ "$ODWROTNA" -gt 0 ]; then
		echo "KONTROLA ODWROTNA PADLA ($ODWROTNA) — przypadek A (czysty, podloga rowna liczbie atrap) NIE przechodzi, wiec proba NIC nie znaczy."
		echo "  Licznik BLEDY=$BLEDY jest NIEWIARYGODNY i NIE jest werdyktem o bramach."
		exit 2
	fi
	if [ "$KOTWICE" -gt 0 ]; then
		echo "PROBA NIC NIE ZMIERZYLA W $KOTWICE PRZYPADKACH — kotwica sed/grep zgnila po refaktorze."
		echo "  To wada PRZYRZADU (tej proby), nie bram: uruchom przelot BEZPOSREDNIO, zanim oskarzysz produkt."
		exit 2
	fi
	if [ "$BLEDY" -gt 0 ]; then
		echo "BRAMY-PROBA: BLEDY=$BLEDY" >&2
		exit 1
	fi
	echo "$1"
	exit 0
}

# katalog_falszywego_korzenia -> tworzy $1/narzedzia-qa i kopiuje tam przelot; drukuje sciezke kopii
zaloz_falszywy_korzen() {
	local korzen="$1"
	mkdir -p "$korzen/narzedzia-qa"
	cp "$PRZELOT_ORYGINAL" "$korzen/narzedzia-qa/proby-kontrolne-przelot-kopia.sh"
	chmod +x "$korzen/narzedzia-qa/proby-kontrolne-przelot-kopia.sh"
	printf '%s\n' "$korzen/narzedzia-qa/proby-kontrolne-przelot-kopia.sh"
}

# atrapa katalog nazwa kod_wyjscia -> zapisuje jeden plik-atrape
atrapa() {
	printf '#!/bin/bash\nexit %s\n' "$3" > "$1/$2"
	chmod +x "$1/$2"
}

# uruchom_kopie skrypt zakres [odlamek] -> zwraca kod w $?, stderr w $URUCHOM_ERR
uruchom_kopie() {
	local skrypt="$1" zakres="$2" odlamek="${3:-}"
	local kor logf
	kor="$(cd "$(dirname "$skrypt")/.." && pwd)"
	logf="$(mktemp)"
	URUCHOM_ERR="$( (cd "$kor" && PRZELOT_ODLAMEK="$odlamek" bash "$skrypt" "$logf" "$zakres" >/dev/null) 2>&1 1>/dev/null)"
	local kod=$?
	rm -f "$logf"
	return "$kod"
}

echo "=== PROBA KONTROLNA: bramy przelotu (podloga + sufity per odlamek) ==="
echo
# ⛔ Podloga czytana Z PRZELOTU, nie wpisana na sztywno: literal zmienia sie z kazda nowa proba
#    (5.09: 14 → 15 tego samego dnia) i sztywna liczba tutaj zestarzalaby sie przy pierwszym
#    przeliczeniu (klasa 138: proba mierzaca stara wersje mierzonego). Brak literalu = kod 2.
PODLOGA=$(grep -oE '^MIN_URUCHOMIONYCH_ZAKRES=[0-9]+' "$PRZELOT_ORYGINAL" | head -1 | cut -d= -f2)
if [ -z "$PODLOGA" ] || [ "$PODLOGA" -lt 5 ]; then
	echo "  BLAD nie umiem odczytac MIN_URUCHOMIONYCH_ZAKRES z przelotu (odczyt: [$PODLOGA]) — nic nie zmierze"; exit 2
fi
ZA_MALO=$((PODLOGA - 4))
echo "podloga zakresu odczytana z przelotu: $PODLOGA (przypadek B uruchamia $ZA_MALO)"

# --- A. KONTROLA POZYTYWNA: dokladnie na podlodze (PODLOGA atrap, wszystkie exit 0) ----
FA="$(mktemp -d)"
SKRYPT_A="$(zaloz_falszywy_korzen "$FA")"
for i in $(seq -w 1 "$PODLOGA"); do atrapa "$FA/narzedzia-qa" "atrapa-$i-proba-kontrolna.sh" 0; done
uruchom_kopie "$SKRYPT_A" narzedzia-qa
KOD_A=$?
echo "--- A. $PODLOGA atrap exit 0 (rowno podloga $PODLOGA) ---"
if [ "$KOD_A" -eq 0 ]; then
	echo "  OK   przelot na komplecie (ILE=$PODLOGA >= podloga $PODLOGA) konczy sie EXIT=0"
else
	echo "  BLAD przelot na komplecie konczy sie EXIT=$KOD_A (ma byc 0) — kontrola pozytywna PADLA"
	echo "$URUCHOM_ERR" | head -5 | sed 's/^/         /'
	ODWROTNA=$((ODWROTNA + 1))
fi

# --- B. za malo atrap (PODLOGA-4 < podloga) ---------------------------------------------
FB="$(mktemp -d)"
SKRYPT_B="$(zaloz_falszywy_korzen "$FB")"
for i in $(seq -w 1 "$ZA_MALO"); do atrapa "$FB/narzedzia-qa" "atrapa-$i-proba-kontrolna.sh" 0; done
# ⛔ POMIJANA BEZWARUNKOWO, nie zaleznie od dockera/zip (klasa: gałąź zależna od środowiska
#    jest niezmierzona). Trescia nie zajmuje sie przelot, bo nazwa jest w POMIJANE dict.
atrapa "$FB/narzedzia-qa" "dowod-zachowania-segregator-proba-kontrolna.sh" 2
uruchom_kopie "$SKRYPT_B" narzedzia-qa
KOD_B=$?
echo "--- B. $ZA_MALO atrap exit 0 + 1 pominieta bezwarunkowo (uruchomionych $ZA_MALO < podloga $PODLOGA) ---"
if [ "$KOD_B" -eq 1 ] && printf '%s' "$URUCHOM_ERR" | grep -qF 'podlodze'; then
	echo "  OK   przelot konczy sie EXIT=1 z wierszem o podlodze"
	printf '%s' "$URUCHOM_ERR" | grep -F 'podlodze' | head -1 | sed 's/^/         /'
else
	echo "  BLAD przelot konczy sie EXIT=$KOD_B (ma byc 1, z wierszem o podlodze) — brama SLEPA"
	echo "$URUCHOM_ERR" | head -5 | sed 's/^/         /'
	BLEDY=$((BLEDY + 1))
fi

# --- C. odlamek `bramki` (sufit 0), jedna atrapa exit 2 ---------------------------------
FC="$(mktemp -d)"
SKRYPT_C="$(zaloz_falszywy_korzen "$FC")"
atrapa "$FC/narzedzia-qa" "bramki-proba-kontrolna.sh" 2
uruchom_kopie "$SKRYPT_C" narzedzia-qa bramki
KOD_C=$?
echo "--- C. odlamek 'bramki', jedyna atrapa dajaca exit 2 (sufit niezmierzonych/pominiec = 0) ---"
if [ "$KOD_C" -eq 1 ]; then
	echo "  OK   przelot na odlamku 'bramki' konczy sie EXIT=1"
else
	echo "  BLAD przelot na odlamku 'bramki' konczy sie EXIT=$KOD_C (ma byc 1) — sufit SLEPY"
	echo "$URUCHOM_ERR" | head -5 | sed 's/^/         /'
	BLEDY=$((BLEDY + 1))
fi

# --- D. MUTANT: MIN_URUCHOMIONYCH_ZAKRES=<N> -> =0 w kopii z przypadku B -----------------
echo "--- D. MUTANT: podloga zakresu przywrocona do 0 — przypadek B ma przestac padac ---"
# ⛔ Kotwica po WZORCU, nie po liczbie: literal podlogi zmienia sie z kazda nowa proba (5.09: 14 → 15
#    tego samego dnia) — kotwica z liczba zgnilaby przy pierwszym przeliczeniu (klasa 138).
KOTWICA='^MIN_URUCHOMIONYCH_ZAKRES=[0-9]+'
PRZED_N=$(grep -cE "$KOTWICA" "$SKRYPT_B")
if [ "$PRZED_N" -eq 0 ]; then
	echo "  BLAD mutacja NIE weszla: kotwica [$KOTWICA] nie istnieje juz w przelocie — wzorzec sie zestarzal"
	KOTWICE=$((KOTWICE + 1))
else
	sed -i -E "s/${KOTWICA}/MIN_URUCHOMIONYCH_ZAKRES=0/" "$SKRYPT_B"
	PO_N=$(grep -cF 'MIN_URUCHOMIONYCH_ZAKRES=0' "$SKRYPT_B")
	if [ "$PO_N" -eq 0 ]; then
		echo "  BLAD mutacja NIE weszla do pliku (kotwica sed zgnila)"
		KOTWICE=$((KOTWICE + 1))
	else
		uruchom_kopie "$SKRYPT_B" narzedzia-qa
		KOD_D=$?
		if [ "$KOD_D" -eq 0 ]; then
			echo "  OK   z podloga=0 przypadek B przestaje padac (EXIT=0) — podloga realnie chroni, mutant zlapany"
		else
			echo "  BLAD z podloga=0 przypadek B DALEJ pada (EXIT=$KOD_D) — ta proba nie mierzy podlogi, ktora twierdzi ze mierzy"
			BLEDY=$((BLEDY + 1))
		fi
	fi
fi

echo
podsumowanie_proby "STRAZNIK WIDZI: podloga obowiazuje dokladnie tam, gdzie policzona, sufity per odlamek lapia niezmierzone/pominiete, mutant podlogi zostaje zlapany jako oczekiwana slepota"
