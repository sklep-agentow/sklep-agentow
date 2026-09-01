#!/usr/bin/env bash
# Czy SECURITY.md mowi PRAWDE o wersjach wtyczek.
#
# ⛔ POWOD (znalezisko D-1, 31.08). SECURITY.md pisal „`sklep-agentow` (wydane
# 0.9.25; 0.9.26 w przygotowaniu)", gdy kod stal na 0.9.28, a wydania v0.9.26,
# v0.9.27 i v0.9.28 juz istnialy. To NIE jest kosmetyka: SECURITY.md to dokument,
# do ktorego trafia ktos, kto znalazl u nas luke — i dowiadywal sie z niego,
# ze wspierana jest wersja sprzed trzech wydan. Zmierzone tego samego dnia:
# `grep -rl SECURITY.md narzedzia-qa/ .github/` dawalo ZERO, czyli nie pilnowalo
# go zadne narzedzie ani zaden krok CI.
#
# ⛔ Straznik czyta numery z PROZY dokumentu — z tego samego zdania, ktore czyta
# czlowiek. Osobny znacznik byłby drugim miejscem z tymi samymi liczbami,
# czyli nowym zrodlem rozjazdu zamiast lekarstwa na stary.
#
# Kody wyjscia: 0 zgodne · 1 ROZJAZD · 2 nie da sie zmierzyc (brak plikow).
set -uo pipefail

# ⛔ CELOWO BEZ `cd` do korzenia repozytorium. Pierwsza wersja tego skryptu robila
# `cd "$KORZEN"` na starcie — i przez to IGNOROWALA podlozone atrapy, sprawdzajac
# zawsze prawdziwy plik. Trzy przypadki proby kontrolnej swiecily wtedy na zielono
# BEZ ZASLUGI straznika. Pracujemy w katalogu biezacym; uruchomienie spoza korzenia
# konczy sie kodem 2 („nie zmierzone"), tak samo jak w bramka-poswiadczenia.sh.
DOKUMENT="${1:-SECURITY.md}"
KATALOG_WTYCZEK="${2:-wtyczki}"

if [ ! -f "$DOKUMENT" ]; then
	echo "SECURITY-WERSJE: brak $DOKUMENT — nie ma czego sprawdzac." >&2
	exit 2
fi
if [ ! -d "$KATALOG_WTYCZEK" ]; then
	echo "SECURITY-WERSJE: brak katalogu $KATALOG_WTYCZEK — nie ma z czym porownywac." >&2
	exit 2
fi

SPRAWDZONE=0
ROZJAZDY=0
BRAK_WPISU=0

for glowny in "$KATALOG_WTYCZEK"/*/*.php; do
	[ -f "$glowny" ] || continue
	nazwa="$(basename "$(dirname "$glowny")")"
	# Naglowek wtyczki: liczy sie WYLACZNIE linia komentarza „ * Version: X.Y.Z".
	w_kodzie="$(grep -m1 -oE '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*[0-9][0-9.]*' "$glowny" 2> /dev/null \
		| grep -oE '[0-9][0-9.]*$')"
	[ -n "$w_kodzie" ] || continue
	SPRAWDZONE=$((SPRAWDZONE + 1))

	# W dokumencie szukamy wzorca: `nazwa` (X.Y.Z) — nazwa w grawisach, wersja w nawiasie.
	w_dokumencie="$(grep -oE "\`${nazwa}\`[^)]*\([^)]*\)" "$DOKUMENT" 2> /dev/null | head -1 \
		| grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)"

	if [ -z "$w_dokumencie" ]; then
		echo "ROZJAZD: $nazwa ma w kodzie $w_kodzie, a w $DOKUMENT NIE MA jego wersji w ogole" >&2
		BRAK_WPISU=$((BRAK_WPISU + 1))
	elif [ "$w_dokumencie" != "$w_kodzie" ]; then
		echo "ROZJAZD: $nazwa — kod mowi $w_kodzie, $DOKUMENT mowi $w_dokumencie" >&2
		ROZJAZDY=$((ROZJAZDY + 1))
	else
		echo "  zgodne: $nazwa $w_kodzie"
	fi
done

# ⛔ PODLOGA. Zero sprawdzonych wtyczek znaczy, ze petla nie znalazla ZADNEGO
# naglowka — a wtedy „brak rozjazdow" jest zdaniem o pustym zbiorze, nie o kodzie.
# Ta sama klasa co I-2 (mianownik zero brany za czysto).
if [ "$SPRAWDZONE" -eq 0 ]; then
	echo "SECURITY-WERSJE: nie znalazlem ANI JEDNEGO naglowka Version w $KATALOG_WTYCZEK/*/*.php" >&2
	exit 2
fi

RAZEM=$((ROZJAZDY + BRAK_WPISU))
echo "SECURITY-WERSJE: sprawdzonych wtyczek $SPRAWDZONE, rozjazdow $ROZJAZDY, bez wpisu $BRAK_WPISU"
[ "$RAZEM" -eq 0 ]
