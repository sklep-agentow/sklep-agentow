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

# ⛔ ATRAPA DOKUMENTU TEZ MA BYC TEGO SAMEGO RODZAJU CO PRODUKCJA. Do 3.09 atrapy niosly
# sam wiersz z wersjami — bez daty stanu i bez CHANGELOG-u, ktory ta date rozstrzyga. Gdy
# straznik dostal kontrole daty, trzy przypadki zaczely padac na kod 2 („nie mam z czym
# porownac") i wygladalo to jak regresja straznika, a bylo brakiem w MATERIALE proby.
# $1 katalog, $2 tresc wiersza wersji, $3 data stanu dokumentu, $4 data wydania w CHANGELOG-u
zbuduj_dokument() {
	printf '# SECURITY.md — atrapa\n\n> Stan na %s. %s\n' "$3" "$2" > "$1/SECURITY.md"
	mkdir -p "$1/wtyczki/sklep-agentow"
	printf '# Historia zmian\n\n## [%s] — %s · atrapa\n' "0.9.28" "$4" > "$1/wtyczki/sklep-agentow/CHANGELOG.md"
}

echo "=== PROBA KONTROLNA: security-wersje.sh ==="

# --- 1. stan ZGODNY (kontrola dodatnia) --------------------------------------
A="$PIASKOWNICA/a"
mkdir -p "$A"
zbuduj_wtyczke "$A" sklep-agentow 0.9.28
zbuduj_wtyczke "$A" chatbot-live 1.5.13
zbuduj_dokument "$A" 'Trzy wtyczki: `sklep-agentow` (0.9.28), `chatbot-live` (1.5.13).' 2026-09-03 2026-09-02
(cd "$A" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "1. stan zgodny przechodzi" 0 $?

# --- 2. ROZJAZD numeru --------------------------------------------------------
B="$PIASKOWNICA/b"
mkdir -p "$B"
zbuduj_wtyczke "$B" sklep-agentow 0.9.28
zbuduj_dokument "$B" '`sklep-agentow` (0.9.25) — tak bylo naprawde 31.08.' 2026-09-03 2026-09-02
(cd "$B" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "2. rozjazd numeru zlapany" 1 $?

# --- 3. wtyczka BEZ wpisu w dokumencie ----------------------------------------
C="$PIASKOWNICA/c"
mkdir -p "$C"
zbuduj_wtyczke "$C" sklep-agentow 0.9.28
zbuduj_wtyczke "$C" segregator-maila 1.3.6
zbuduj_dokument "$C" '`sklep-agentow` (0.9.28) i nic o segregatorze.' 2026-09-03 2026-09-02
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

# --- 8. DATA STANU STARSZA NIZ WYDANIE, ktore dokument opisuje ----------------
# Znalezione dwa razy w kolejce PR-ow (#274): scalenie rozstrzyga konflikt w SECURITY.md
# regula „wygrywa WYZSZY numer" i przeciaga CALY wiersz — razem z data starsza niz to,
# co plik opisuje. Numery sie wtedy zgadzaja, wiec straznik pilnujacy samych numerow milczy.
F="$PIASKOWNICA/f"
mkdir -p "$F"
zbuduj_wtyczke "$F" sklep-agentow 0.9.28
zbuduj_dokument "$F" '`sklep-agentow` (0.9.28).' 2026-09-01 2026-09-02
(cd "$F" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "8. data stanu starsza niz wydanie zlapana" 1 $?

# --- 9. dokument BEZ wiersza „Stan na" = NIE ZMIERZONE, nie sukces -------------
# Ta sama regula co podloga w przypadku 5: brak materialu to odmowa werdyktu, nie „czysto".
G="$PIASKOWNICA/g"
mkdir -p "$G"
zbuduj_wtyczke "$G" sklep-agentow 0.9.28
printf '> `sklep-agentow` (0.9.28) bez wiersza o stanie.\n' > "$G/SECURITY.md"
mkdir -p "$G/wtyczki/sklep-agentow"
printf '# Historia zmian\n\n## [0.9.28] — 2026-09-02 · atrapa\n' > "$G/wtyczki/sklep-agentow/CHANGELOG.md"
(cd "$G" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "9. dokument bez daty stanu = kod 2" 2 $?

# --- 10. data mierzona wobec NAJNOWSZEGO wydania ZE WSZYSTKICH wtyczek ---------
# Recenzja #274, runda 8, WAZNA 2: pierwsza wersja kontroli daty porownywala date stanu
# z CHANGELOG-iem SAMEGO sklepu, a `SECURITY.md` opisuje TRZY wtyczki naraz i ChatBot ma
# osobny rytm wydan. Wydanie ChatBota nowsze niz ostatnie wydanie sklepu przechodzilo wiec
# niezauwazone — przy bramce meldujacej „sprawdzonych wtyczek 3".
# Tu: sklep wydany 2026-09-01, ChatBot 2026-09-05, dokument deklaruje stan na 2026-09-02.
# Wobec sklepu data JEST swiezsza (przeszloby), wobec ChatBota jest STARSZA (ma paść).
H="$PIASKOWNICA/h"
mkdir -p "$H"
zbuduj_wtyczke "$H" sklep-agentow 0.9.28
zbuduj_wtyczke "$H" chatbot-live 1.5.15
zbuduj_dokument "$H" '`sklep-agentow` (0.9.28), `chatbot-live` (1.5.15).' 2026-09-02 2026-09-01
mkdir -p "$H/wtyczki/chatbot-live"
printf '# Historia zmian\n\n## [1.5.15] — 2026-09-05 · atrapa\n' > "$H/wtyczki/chatbot-live/CHANGELOG.md"
(cd "$H" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "10. data starsza niz wydanie INNEJ wtyczki zlapana" 1 $?

# --- 11. kontrola dodatnia do 10: ta sama para dat, dokument swiezszy od OBU -----
# Bez tego przypadek 10 nie odrozniałby dzialajacej kontroli od kontroli, ktora pada zawsze.
I="$PIASKOWNICA/i"
mkdir -p "$I"
zbuduj_wtyczke "$I" sklep-agentow 0.9.28
zbuduj_wtyczke "$I" chatbot-live 1.5.15
zbuduj_dokument "$I" '`sklep-agentow` (0.9.28), `chatbot-live` (1.5.15).' 2026-09-06 2026-09-01
mkdir -p "$I/wtyczki/chatbot-live"
printf '# Historia zmian\n\n## [1.5.15] — 2026-09-05 · atrapa\n' > "$I/wtyczki/chatbot-live/CHANGELOG.md"
(cd "$I" && bash "$STRAZNIK" SECURITY.md wtyczki > /dev/null 2>&1)
sprawdz "11. dokument swiezszy od obu wtyczek przechodzi" 0 $?

echo "=== WYNIK: zdane $ZDANE, oblane $OBLANE ==="
[ "$OBLANE" -eq 0 ]
