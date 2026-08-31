#!/usr/bin/env bash
# Proba kontrolna dla narzedzia-qa/security-wersje.sh (znalezisko D-1, 31.08).
#
# ⛔ Kazdy przypadek psuje DOKLADNIE JEDEN warunek. Bez tego test, ktory przechodzi,
# nie odroznia dzialajacego straznika od straznika, ktory nic nie widzi.
# Przypadek 1 jest kontrola DODATNIA: stan zgodny musi przechodzic, inaczej
# „lapie rozjazd" moglo znaczyc „pada zawsze".
set -uo pipefail

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STRAZNIK="$KORZEN/narzedzia-qa/security-wersje.sh"
ZDANE=0
OBLANE=0

sprawdz() {
	if [ "$2" = "$3" ]; then
		echo "  OK   $1 (kod $3)"
		ZDANE=$((ZDANE + 1))
	else
		echo "  PADL $1 — oczekiwano $2, jest $3" >&2
		OBLANE=$((OBLANE + 1))
	fi
}

PIASKOWNICA="$(mktemp -d)" || exit 2
trap 'rm -rf "$PIASKOWNICA"' EXIT

# Atrapa tego samego RODZAJU co produkcja: katalog wtyczki z naglowkiem WordPressa.
zbuduj_wtyczke() {
	local katalog="$1/wtyczki/$2"
	mkdir -p "$katalog"
	{
		printf '<?php\n/**\n'
		printf ' * Plugin Name: %s\n' "$2"
		printf ' * Version: %s\n' "$3"
		printf ' */\n'
	} > "$katalog/$2.php"
}

echo "=== PROBA KONTROLNA: security-wersje.sh ==="

# --- 1. stan ZGODNY (kontrola dodatnia) --------------------------------------
A="$PIASKOWNICA/a"
mkdir -p "$A"
zbuduj_wtyczke "$A" sklep-agentow 0.9.28
zbuduj_wtyczke "$A" chatbot-live 1.5.13
printf '> Trzy wtyczki: `sklep-agentow` (0.9.28), `chatbot-live` (1.5.13).\n' > "$A/SECURITY.md"
(cd "$A" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "1. stan zgodny przechodzi" 0 $?

# --- 2. ROZJAZD numeru --------------------------------------------------------
B="$PIASKOWNICA/b"
mkdir -p "$B"
zbuduj_wtyczke "$B" sklep-agentow 0.9.28
printf '> `sklep-agentow` (0.9.25) — tak bylo naprawde 31.08.\n' > "$B/SECURITY.md"
(cd "$B" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "2. rozjazd numeru zlapany" 1 $?

# --- 3. wtyczka BEZ wpisu w dokumencie ----------------------------------------
C="$PIASKOWNICA/c"
mkdir -p "$C"
zbuduj_wtyczke "$C" sklep-agentow 0.9.28
zbuduj_wtyczke "$C" segregator-maila 1.3.6
printf '> `sklep-agentow` (0.9.28) i nic o segregatorze.\n' > "$C/SECURITY.md"
(cd "$C" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "3. wtyczka bez wpisu zlapana" 1 $?

# --- 4. brak dokumentu = NIE ZMIERZONE, nie sukces ----------------------------
D="$PIASKOWNICA/d"
mkdir -p "$D/wtyczki"
zbuduj_wtyczke "$D" sklep-agentow 0.9.28
(cd "$D" && bash "$STRAZNIK" NIE-MA-MNIE.md wtyczki > /dev/null 2>&1)
sprawdz "4. brak dokumentu = kod 2" 2 $?

# --- 5. katalog wtyczek BEZ naglowkow Version = NIE ZMIERZONE -----------------
# To podloga: gdyby straznik liczyl to jako „zero rozjazdow", milczalby dokladnie
# wtedy, gdy nie ma z czym porownywac.
E="$PIASKOWNICA/e"
mkdir -p "$E/wtyczki/pusta"
printf '<?php\n// bez naglowka wersji\n' > "$E/wtyczki/pusta/pusta.php"
printf '> `pusta` (1.0.0)\n' > "$E/SECURITY.md"
(cd "$E" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "5. brak naglowkow Version = kod 2" 2 $?

# --- 6. PRAWDZIWY dokument tego repo ------------------------------------------
# Nie atrapa: sprawdzamy, ze SECURITY.md w repozytorium jest zgodny TERAZ.
# ⛔ Jawne `cd` do korzenia, bo straznik czyta katalog BIEZACY — bez tego wynik
# zalezalby od tego, skad ktos odpalil probe kontrolna.
(cd "$KORZEN" && bash "$STRAZNIK" > /dev/null 2>&1)
sprawdz "6. prawdziwy SECURITY.md tego repo jest zgodny" 0 $?

# --- 7. uruchomienie SPOZA repo = NIE ZMIERZONE -------------------------------
# Ta sama regula co bramka-poswiadczenia.sh: brak plikow to odmowa, nie „czysto".
(cd "$PIASKOWNICA" && bash "$STRAZNIK" > /dev/null 2>&1)
sprawdz "7. uruchomiony spoza repo odmawia (kod 2)" 2 $?

echo "=== WYNIK: zdane $ZDANE, oblane $OBLANE ==="
[ "$OBLANE" -eq 0 ]
