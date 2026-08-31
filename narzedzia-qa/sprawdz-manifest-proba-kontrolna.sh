#!/bin/bash
# sprawdz-manifest-proba-kontrolna.sh — czy strażnik manifestu UMIE PAŚĆ.
#
# PO CO (polowanie 31.08, znalezisko B-2): kontrole strażnika pilnowały WZAJEMNEJ
# ZGODNOŚCI manifestu, rejestru BRAMKI=() i progu. Łapały ciche pominięcie bramki,
# ale przepuszczały jej JAWNE, spójne wykreślenie ze wszystkich trzech miejsc naraz:
# usunięcie `gitleaks` + próg 10→9 + wiersz z manifestu dawało
# "MANIFEST-GUARD: zgodne", kod 0 — czyli można było wyłączyć skan sekretów
# przy zielonych kontrolach. Odpowiedzią jest PODŁOGA BEZPIECZEŃSTWA w strażniku,
# a ta próba pilnuje, żeby podłoga nie stała się martwym kodem.
#
# ZASADA: każdy przypadek psuje DOKŁADNIE JEDEN warunek i pracuje na KOPII repo
# w katalogu tymczasowym — żywe repo nie jest dotykane ani razu.
#
# Kody wyjścia: 0 = strażnik zachowuje się poprawnie we wszystkich przypadkach
#               1 = strażnik nie widzi wady, którą ma widzieć (albo krzyczy na czysty stan)

set -uo pipefail
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[ -n "$KORZEN" ] || { echo "PROBA: nie umiem ustalic korzenia repo" >&2; exit 2; }
cd "$KORZEN" || exit 2

STRAZNIK="$KORZEN/narzedzia-qa/sprawdz-manifest.sh"
[ -f "$STRAZNIK" ] || { echo "PROBA: brak $STRAZNIK" >&2; exit 2; }

BLEDY=0
PRZYPADKI=0

# Kopia repo w katalogu tymczasowym: tylko trzy pliki, których dotyka strażnik.
przygotuj_kopie() {
	local kat="$1"
	mkdir -p "$kat/.github/workflows" "$kat/narzedzia-qa" || return 1
	cp "$KORZEN/.github/manifest-bramek.txt"   "$kat/.github/manifest-bramek.txt"   || return 1
	cp "$KORZEN/.github/workflows/quality.yml" "$kat/.github/workflows/quality.yml" || return 1
	cp "$KORZEN/narzedzia-qa/bramki.sh"        "$kat/narzedzia-qa/bramki.sh"        || return 1
}

# sprawdz <opis> <oczekiwany-kod> <funkcja-psujaca>
sprawdz() {
	local opis="$1" oczekiwany="$2" psuj="$3" kat kod
	PRZYPADKI=$((PRZYPADKI + 1))
	kat="$(mktemp -d)" || { echo "PROBA: mktemp padl" >&2; exit 2; }
	if ! przygotuj_kopie "$kat"; then
		echo "  BLAD $opis — nie udalo sie przygotowac kopii" >&2
		BLEDY=$((BLEDY + 1)); rm -rf "$kat"; return
	fi
	"$psuj" "$kat"
	bash "$STRAZNIK" "$kat" >/dev/null 2>&1
	kod=$?
	if [ "$kod" -eq "$oczekiwany" ]; then
		printf '  OK   %-58s (EXIT=%s)\n' "$opis" "$kod"
	else
		printf '  BLAD %-58s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany" >&2
		BLEDY=$((BLEDY + 1))
	fi
	rm -rf "$kat"
}

nic() { :; }

# Spójne wyłączenie bramki: znika z rejestru, z manifestu ORAZ próg schodzi o jeden.
# To jest DOKŁADNIE ten scenariusz, który przed 31.08 przechodził na zielono.
wytnij_spojnie() {
	local kat="$1" bramka="$2"
	sed -i "s/ ${bramka} / /; s/ ${bramka})/)/" "$kat/narzedzia-qa/bramki.sh"
	sed -i "/^bramka ${bramka}[[:space:]]/d"    "$kat/narzedzia-qa/bramki.sh"
	sed -i "/^${bramka}$/d"                     "$kat/.github/manifest-bramek.txt"
	local prog
	prog=$(grep -oP '^PROG_PELNY=\K[0-9]+' "$kat/narzedzia-qa/bramki.sh" | head -1)
	[ -n "$prog" ] && sed -i "s/^PROG_PELNY=${prog}$/PROG_PELNY=$((prog - 1))/" "$kat/narzedzia-qa/bramki.sh"
}
wytnij_gitleaks()     { wytnij_spojnie "$1" gitleaks; }
wytnij_poswiadczenia() { wytnij_spojnie "$1" poswiadczenia; }
wytnij_slady()        { wytnij_spojnie "$1" slady; }

echo "=== PROBA KONTROLNA: straznik manifestu ==="
echo "--- kontrola odwrotna: czysty stan MA przejsc ---"
sprawdz "czysty stan repo" 0 nic

echo "--- PODLOGA BEZPIECZENSTWA: spojne wyciecie MA byc zauwazone ---"
sprawdz "gitleaks wyciety spojnie (rejestr+manifest+prog)"      1 wytnij_gitleaks
sprawdz "poswiadczenia wyciete spojnie (rejestr+manifest+prog)" 1 wytnij_poswiadczenia
sprawdz "slady wyciete spojnie (rejestr+manifest+prog)"         1 wytnij_slady

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "PROBA-MANIFEST: $PRZYPADKI/$PRZYPADKI — straznik odroznia czysty stan od wycietej bramki podlogi."
	exit 0
fi
echo "PROBA-MANIFEST: $BLEDY z $PRZYPADKI przypadkow NIEZALICZONE — straznik nie widzi wady, ktora ma widziec." >&2
exit 1
