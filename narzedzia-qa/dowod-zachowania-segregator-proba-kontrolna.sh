#!/bin/bash
# dowod-zachowania-segregator-proba-kontrolna.sh — czy dowod zachowania cokolwiek ODRZUCA.
#
# Dowod, ktory zawsze wychodzi, nie jest dowodem. Ten skrypt buduje z PRAWDZIWEJ
# paczki jej uszkodzone kopie i sprawdza, czy `dowod-zachowania-segregator.sh`
# je odrzuca — a czysta paczka nadal przechodzi.
#
# Mutanty celuja w OBIETNICE SPRZEDAZOWE, nie w losowe linijki:
#   M1  tryb NA SUCHO przestaje chronic poczte (obietnica "nie rusza ani jednej wiadomosci"),
#   M2  cofniecie nic nie cofa (obietnica "da sie cofnac"),
#   M3  retencja kasuje WSZYSTKO, nie tylko starsze niz prog (naprawa, ktora zabiera dane).
#
# ⛔ Kazdy mutant jest sprawdzany dwa razy: czy WSZEDL do pliku (kotwice gnija po
#    refaktorze) i czy dowod PADL Z WLASCIWEGO POWODU, a nie przypadkiem.
#
# Kody wyjscia: 0 = wszystkie mutanty odrzucone i czysta paczka przechodzi
#               1 = ktorys mutant przeszedl albo czysta paczka padla
#               2 = brak narzedzi / brak paczki
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
DOWOD="$KORZEN/narzedzia-qa/dowod-zachowania-segregator.sh"
PACZKA="${1:-boty/paczki/segregator-maila-1.3.0.zip}"
[ -f "$PACZKA" ] || { echo "PROBA: nie ma paczki [$PACZKA]" >&2; exit 2; }
command -v unzip > /dev/null && command -v zip > /dev/null || { echo "PROBA: brak zip/unzip" >&2; exit 2; }

BLEDY=0
BAZA="$(mktemp -d)"
trap 'rm -rf "$BAZA"' EXIT

# zmutuj <nazwa> <plik-w-wtyczce> <polecenie-sed> <wzorzec-potwierdzajacy>
zmutuj() {
	local nazwa="$1" plik="$2" polecenie="$3" wzorzec="$4"
	local kat="$BAZA/$nazwa"
	rm -rf "$kat"; mkdir -p "$kat"
	( cd "$kat" && unzip -q "$KORZEN/$PACZKA" )
	sed -i "$polecenie" "$kat/segregator-maila/$plik"
	if ! grep -q "$wzorzec" "$kat/segregator-maila/$plik"; then
		printf '  BLAD mutacja [%s] NIE weszla do pliku — kotwica zgnila\n' "$nazwa"
		BLEDY=$(( BLEDY + 1 ))
		return 1
	fi
	( cd "$kat" && zip -qr "$BAZA/$nazwa.zip" segregator-maila )
	printf '%s' "$BAZA/$nazwa.zip"
}

# sprawdz <opis> <zip> <oczekiwany-kod> <fraza>
sprawdz() {
	local opis="$1" zip="$2" oczekiwany="$3" fraza="${4:-}" wyjscie kod=0
	wyjscie="$( bash "$DOWOD" "$zip" 2>&1 )" || kod=$?
	if [ "$kod" -ne "$oczekiwany" ]; then
		printf '  BLAD %-58s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany"
		printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO|WYSZLO|WYNIK' | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	if [ -n "$fraza" ] && ! printf '%s' "$wyjscie" | grep -qF "$fraza"; then
		printf '  BLAD %-58s (kod OK, ale padl z INNEGO powodu)\n' "$opis"
		printf '         szukalam: %s\n' "$fraza"
		printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO' | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	printf '  OK   %-58s (EXIT=%s)\n' "$opis" "$kod"
}

echo "=== MUTANTY — dowod MA PASC ==="

M1="$(zmutuj m1-na-sucho-nie-chroni includes/skrzynka-przebieg.php \
	'145s/.*/\t$na_zywo = true; \/\/ MUTANT/' 'MUTANT')" \
	&& sprawdz "tryb NA SUCHO rusza poczte mimo obietnicy" "$M1" 1 "przebieg na sucho ZMIENIL skrzynke"

# ⛔ Bez `a\` i bez ukosnika jako separatora: obie te rzeczy wymagaja w tym miejscu
# potrojnego cytowania i pierwsza wersja tej mutacji rozjezdzala sie w podstawieniu —
# plik wychodzil uszkodzony, dowod padal z kodem 2 (stos nie wstal), czyli probowal
# co innego, niz mial probowac.
M2="$(zmutuj m2-cofniecie-nic-nie-robi includes/skrzynka-przebieg.php \
	's|^function segm_skrzynka_cofnij( $id_przebiegu ) {|& return array( "cofnietych" => 0, "przywroconych" => 0, "bledow" => 0, "blad" => "" ); // MUTANT|' 'MUTANT')" \
	&& sprawdz "cofniecie nic nie cofa" "$M2" 1 "po cofnieciu INBOX ma"

M3="$(zmutuj m3-retencja-kasuje-wszystko segregator-maila.php \
	's/- SEGM_RETENCJA_DNI \* DAY_IN_SECONDS/+ SEGM_RETENCJA_DNI * DAY_IN_SECONDS \/\/ MUTANT/' 'MUTANT')" \
	&& sprawdz "retencja kasuje takze SWIEZE wpisy" "$M3" 1 "retencja ruszyla wpis SWIEZSZY"

echo
echo "=== KONTROLA ODWROTNA — czysta paczka MA PRZEJSC ==="
sprawdz "paczka z repozytorium, bez zmian" "$KORZEN/$PACZKA" 0

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "DOWOD ZACHOWANIA ODRZUCA KAZDA PODLOZONA WADE — i przepuszcza czysta paczke"
	exit 0
fi
echo "DOWOD ZACHOWANIA POMYLIL SIE $BLEDY RAZ(Y) — nie oddawaj go w tym stanie"
exit 1
