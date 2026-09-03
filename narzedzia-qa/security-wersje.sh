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

# ⛔ DATA STANU, NIE TYLKO NUMERY. Znalezione dwa razy w tej samej kolejce PR-ow: scalenie
# rozstrzyga konflikt w SECURITY.md regula "wygrywa WYZSZY numer wersji" — i razem z numerem
# przeciaga CALY wiersz, w tym date. Skutek: dokument publiczny deklarowal stan STARSZY niz
# wydanie, ktore opisuje (#274: "Stan na 2026-09-01" przy wydaniu 0.9.39 z 2026-09-02), a przy
# tym starszy niz to, co stalo na mainie. Ten straznik pilnowal wylacznie numerow, wiec milczal.
# Regula: data stanu NIE MOZE byc starsza niz data najnowszego wydania w CHANGELOG-u sklepu.
DATA_DOK="$(grep -m1 -oE 'Stan na [0-9]{4}-[0-9]{2}-[0-9]{2}' "$DOKUMENT" 2> /dev/null | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}')"
# ⛔ DATA MIERZONA WOBEC WSZYSTKICH WTYCZEK, NIE TYLKO SKLEPU. Pierwsza wersja tej kontroli
# porownywala date stanu z CHANGELOG-iem SAMEGO sklepu, a petla wyzej sprawdza numery WSZYSTKICH
# trzech i wypis melduje "sprawdzonych wtyczek 3". Skutek: wydanie ChatBota albo segregatora
# nowsze niz ostatnie wydanie sklepu dawaloby dokument deklarujacy stan starszy niz tresc,
# ktora niesie — czyli DOKLADNIE te wade, przed ktora ta kontrola stoi — przy bramce swiecacej
# "zgodne". ChatBot ma osobny rytm wydan, wiec to nie jest przypadek teoretyczny.
DATA_WYDANIA=""
WTYCZKA_WYDANIA=""
for _cl in "$KATALOG_WTYCZEK"/*/CHANGELOG.md; do
	[ -f "$_cl" ] || continue
	_d="$(grep -m1 -oE '^## \[[0-9]+\.[0-9]+\.[0-9]+\] — [0-9]{4}-[0-9]{2}-[0-9]{2}' "$_cl" 2> /dev/null | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}')"
	[ -n "$_d" ] || continue
	if [ -z "$DATA_WYDANIA" ] || [ "$_d" \> "$DATA_WYDANIA" ]; then
		DATA_WYDANIA="$_d"
		WTYCZKA_WYDANIA="$(basename "$(dirname "$_cl")")"
	fi
done
if [ -z "$DATA_DOK" ]; then
	echo "SECURITY-WERSJE: w $DOKUMENT nie ma wiersza 'Stan na RRRR-MM-DD' — nie wiem, na kiedy opisuje stan" >&2
	exit 2
fi
if [ -z "$DATA_WYDANIA" ]; then
	echo "SECURITY-WERSJE: w ZADNYM $KATALOG_WTYCZEK/*/CHANGELOG.md nie ma datowanego naglowka wydania" >&2
	echo "                 — nie mam z czym porownac daty stanu" >&2
	exit 2
fi
if [ "$DATA_DOK" \< "$DATA_WYDANIA" ]; then
	echo "ROZJAZD: $DOKUMENT deklaruje stan na $DATA_DOK, a opisuje wydanie $WTYCZKA_WYDANIA z $DATA_WYDANIA (data starsza niz tresc)" >&2
	ROZJAZDY=$((ROZJAZDY + 1))
else
	echo "  zgodne: data stanu $DATA_DOK nie jest starsza niz najnowsze wydanie ($WTYCZKA_WYDANIA, $DATA_WYDANIA)"
fi

RAZEM=$((ROZJAZDY + BRAK_WPISU))
echo "SECURITY-WERSJE: sprawdzonych wtyczek $SPRAWDZONE, rozjazdow $ROZJAZDY, bez wpisu $BRAK_WPISU"
[ "$RAZEM" -eq 0 ]
