#!/usr/bin/env bash
# Proba kontrolna dla DWOCH napraw z 31.08 w narzedzia-qa/bramki.sh:
#   B-5  — narzedzia pobierane curlem szly do wykonania BEZ sprawdzenia sumy,
#          a cache lezy w /tmp (katalog zapisywalny dla kazdego) i byl brany
#          po samej nazwie pliku.
#   B-10 — bramka sekretow nie zagladala do wnetrza sledzonych archiwow .zip.
#
# ⛔ CZEGO TEN TEST PILNUJE. Kazdy przypadek psuje DOKLADNIE JEDEN warunek i ma
# padac na kodzie SPRZED naprawy. Przypadek 4 (podlozona binarka) i przypadek 6
# (archiwum nie do rozpakowania) na starej wersji przechodzily jako sukces —
# i to jest cala tresc obu znalezisk.
#
# ⛔ Test woła PRAWDZIWE funkcje z bramki.sh (przez `source` z
# BRAMKI_TYLKO_FUNKCJE=1), nie ich przepisane kopie. Kopia mierzy sama siebie.
set -uo pipefail

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZDANE=0
OBLANE=0

zdany() {
	echo "  OK   $1"
	ZDANE=$((ZDANE + 1))
}
oblany() {
	echo "  PADL $1" >&2
	OBLANE=$((OBLANE + 1))
}
sprawdz() {
	# sprawdz "opis" oczekiwany_kod faktyczny_kod
	if [ "$2" = "$3" ]; then zdany "$1 (kod $3)"; else oblany "$1 — oczekiwano $2, jest $3"; fi
}

echo "=== PROBA KONTROLNA: sumy narzedzi (B-5) i skan archiwow (B-10) ==="

# Zaladuj SAME funkcje. `source` uruchamia tez `cd "$KORZEN"` z bramki.sh — to
# w porzadku, ale kazdy przypadek nizej sam ustawia sobie katalog roboczy.
# shellcheck source=/dev/null
BRAMKI_TYLKO_FUNKCJE=1 source "$KORZEN/narzedzia-qa/bramki.sh" || {
	echo "BLAD: nie udalo sie zaladowac funkcji z bramki.sh" >&2
	exit 2
}
if ! declare -f suma_zgodna > /dev/null || ! declare -f zapewnij_narzedzie > /dev/null \
	|| ! declare -f skan_archiwow > /dev/null; then
	echo "BLAD: brak oczekiwanych funkcji po zaladowaniu bramki.sh" >&2
	exit 2
fi

PIASKOWNICA="$(mktemp -d)" || exit 2
sprzataj() { rm -rf "$PIASKOWNICA"; }
trap sprzataj EXIT

# --- 1-3: sama weryfikacja sumy ----------------------------------------------
echo "-- suma_zgodna --"
printf 'tresc do zwazenia\n' > "$PIASKOWNICA/plik"
DOBRA="$(sha256sum "$PIASKOWNICA/plik" | cut -d' ' -f1)"
ZLA="0000000000000000000000000000000000000000000000000000000000000000"

suma_zgodna "$PIASKOWNICA/plik" "$DOBRA"
sprawdz "1. zgodna suma przechodzi" 0 $?

suma_zgodna "$PIASKOWNICA/plik" "$ZLA"
sprawdz "2. niezgodna suma pada" 1 $?

suma_zgodna "$PIASKOWNICA/nie-ma-mnie" "$DOBRA"
sprawdz "3. nieistniejacy plik pada" 1 $?

# --- 4: PODLOZONA binarka w cache (serce B-5) --------------------------------
# Na kodzie sprzed naprawy `zapewnij_narzedzie` brala z cache KAZDY wykonywalny
# plik o pasujacej nazwie i zwracala jego sciezke — czyli uruchamiala cudzy kod.
echo "-- podlozona binarka w cache --"
CACHE="$PIASKOWNICA/cache"
mkdir -p "$CACHE"
cat > "$CACHE/gitleaks" <<'PODROBKA'
#!/bin/sh
echo "PODROBIONA BINARKA WYKONANA"
exit 0
PODROBKA
chmod +x "$CACHE/gitleaks"
PODSTAWIONA_SUMA="$(sha256sum "$CACHE/gitleaks" | cut -d' ' -f1)"

# URL celowo nieosiagalny: chcemy zmierzyc SAMA decyzje o cache, bez sieci.
WYNIK="$(zapewnij_narzedzie gitleaks "http://127.0.0.1:9/nie-ma" gitleaks \
	"$ZLA" 2> /dev/null)"
RC=$?
if [ "$RC" -ne 0 ] && [ -z "$WYNIK" ]; then
	zdany "4. podlozona binarka NIE zostala uzyta (funkcja odmowila, kod $RC)"
else
	oblany "4. podlozona binarka zostala zwrocona jako narzedzie: '$WYNIK' (kod $RC)"
fi

# 4b: kontrola dodatnia do przypadku 4 — gdy suma SIE ZGADZA, plik z cache ma byc
# uzyty. Bez tego „nie uzyla" moglo znaczyc „funkcja nie dziala w ogole".
WYNIK2="$(zapewnij_narzedzie gitleaks "http://127.0.0.1:9/nie-ma" gitleaks \
	"$PODSTAWIONA_SUMA" 2> /dev/null)"
RC2=$?
if [ "$RC2" -eq 0 ] && [ "$WYNIK2" = "$CACHE/gitleaks" ]; then
	zdany "4b. kontrola dodatnia: plik o ZGODNEJ sumie jest brany z cache"
else
	oblany "4b. kontrola dodatnia PADLA — funkcja nie bierze nawet zgodnego pliku (kod $RC2, '$WYNIK2')"
fi

# --- 5-6: skan archiwow (B-10) ------------------------------------------------
echo "-- skan archiwow --"
REPO="$PIASKOWNICA/repo"
mkdir -p "$REPO/paczki/src"
cd "$REPO" || exit 2
git init -q . 2> /dev/null
git config user.email "proba@localhost"
git config user.name "proba"

# Falszywe poswiadczenia w formacie, ktory gitleaks rozpoznaje. Wartosci zmyslone.
# ⛔ PREFIKSY SKLEJANE W CZASIE DZIALANIA, nie wpisane literalnie — inaczej TEN
# plik staje sie w repozytorium plikiem z poswiadczeniami i pali `bramka-poswiadczenia.sh`.
# Zmierzone 31.08: pierwsza wersja tego testu wywalila tamta bramke na czystym stanie.
# Ten sam chwyt co `${ZERA}` w narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh.
PRE_STRIPE="sk"
PRE_GH="ghp"
{
	printf '<?php\n'
	printf "\$stripe = '%s_live_51QwErTyUiOpAsDfGhJkLzXc';\n" "$PRE_STRIPE"
	printf "\$gh = '%s_ABcdEFghIJklMNopQRstUVwxYZ0123456789';\n" "$PRE_GH"
} > "$REPO/paczki/src/konfig.php"

if ! command -v zip > /dev/null 2>&1 || ! command -v unzip > /dev/null 2>&1; then
	echo "  POMINIETE 5-6: brak zipa/unzipa na tej maszynie" >&2
else
	(cd "$REPO/paczki" && zip -qr paczka.zip src && rm -rf src)
	git add -A > /dev/null 2>&1
	git commit -qm "paczka z sekretem w srodku" > /dev/null 2>&1

	BIN="$(zapewnij_narzedzie gitleaks \
		"https://github.com/gitleaks/gitleaks/releases/download/v${WERSJA_GITLEAKS}/gitleaks_${WERSJA_GITLEAKS}_linux_x64.tar.gz" \
		gitleaks "$SUMA_GITLEAKS" 2> /dev/null)"
	if [ -z "$BIN" ]; then
		echo "  POMINIETE 5-6: nie udalo sie zapewnic gitleaksa (brak sieci?)" >&2
	else
		# 5. Sekret SCHOWANY W ARCHIWUM ma zostac znaleziony. To kontrola dodatnia
		#    calej naprawy: na kodzie sprzed 31.08 ten przypadek konczyl sie zerem.
		skan_archiwow "$BIN" > /dev/null 2>&1
		RC5=$?
		if [ "$RC5" -ne 0 ]; then
			zdany "5. sekret ukryty w .zip ZOSTAL znaleziony (kod $RC5)"
		else
			oblany "5. sekret w .zip przeszedl niezauwazony — bramka dalej slepa"
		fi

		# 6. Archiwum nie do rozpakowania: skan nie ma czego czytac, wiec „czysto"
		#    byloby zdaniem o pustym katalogu. Ma padac. (Klasa I-2.)
		REPO2="$PIASKOWNICA/repo2"
		mkdir -p "$REPO2"
		cd "$REPO2" || exit 2
		git init -q . 2> /dev/null
		git config user.email "proba@localhost"
		git config user.name "proba"
		printf 'to nie jest archiwum, tylko tekst\n' > "$REPO2/zepsuta.zip"
		git add -A > /dev/null 2>&1
		git commit -qm "uszkodzone archiwum" > /dev/null 2>&1
		skan_archiwow "$BIN" > /dev/null 2>&1
		RC6=$?
		sprawdz "6. archiwum nie do rozpakowania = blad, nie czysto" 1 $RC6

		# 7. Repo BEZ archiwow ma przechodzic — inaczej naprawa blokowalaby
		#    kazde repozytorium bez paczek.
		REPO3="$PIASKOWNICA/repo3"
		mkdir -p "$REPO3"
		cd "$REPO3" || exit 2
		git init -q . 2> /dev/null
		git config user.email "proba@localhost"
		git config user.name "proba"
		printf 'nic tu nie ma\n' > "$REPO3/czytaj.md"
		git add -A > /dev/null 2>&1
		git commit -qm "bez archiwow" > /dev/null 2>&1
		skan_archiwow "$BIN" > /dev/null 2>&1
		sprawdz "7. brak archiwow przechodzi" 0 $?
	fi
fi

cd "$KORZEN" || exit 2
echo "=== WYNIK: zdane $ZDANE, oblane $OBLANE ==="
[ "$OBLANE" -eq 0 ]
