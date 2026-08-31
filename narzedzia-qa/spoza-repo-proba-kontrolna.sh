#!/usr/bin/env bash
# KLASA 73: narzedzie uruchomione poza repozytorium NIE MOZE meldowac „czysto".
#
# ⛔ POWOD. 31.08 kolega (recenzja zewnetrzna) pokazal wade w `bramka-poswiadczenia.sh`:
#     cd "$(git rev-parse --show-toplevel 2>/dev/null)" || exit 2
# Poza repozytorium `git rev-parse` wypisuje PUSTKE, a `cd ""` w bashu NIC nie robi
# i melduje SUKCES — wiec `exit 2` byl kodem MARTWYM. Bramka leciala dalej w katalogu
# wywolania, skanowala zero plikow i zero commitow i konczyla sie ZEREM, czyli „czysto".
# Ochrona bezpieczenstwa meldowala brak trafien, bo nie miala czego czytac.
#
# ⛔ CZEGO PILNUJE TEN PLIK. Sam egzemplarz naprawiono tego samego dnia, ale regula
# „kazda bramka dostaje przypadek uruchomienia spoza repo" zostala ZAPISANA w sciadze
# (klasa 73) i NIEWDROZONA: 31.08 wieczorem miały ją DWIE z pietnastu prob kontrolnych.
# Ten plik zastepuje trzynascie osobnych latek jednym testem calej rodziny i obejmuje
# takze narzedzia dopisane w przyszlosci, bo liste bierze z katalogu, nie ze spisu.
#
# ⛔ DLACZEGO NIE URUCHAMIAM WSZYSTKIEGO PO KOLEI. Pierwsza wersja tego skryptu (31.08)
# odpalala KAZDY plik z narzedzia-qa/ w pustym katalogu — i dala szesc falszywych alarmow
# oraz jeden skutek uboczny. Falszywe, bo `bramki.sh`, `dowody-do-opisu.sh` i
# `pakuj-dla-klienta.sh` wyznaczaja korzen ze SCIEZKI WLASNEGO PLIKU, nie z gita: poza repo
# pracuja dalej na prawdziwym repozytorium, wiec ich zero jest PRAWDZIWE. Skutek uboczny:
# `pakuj-dla-klienta.sh` naprawde zaczal pakowac wtyczki. Wniosek: test klasy ma pytac
# o KONSTRUKCJE W KODZIE, a uruchamiac wylacznie te narzedzia, ktore korzen biora z gita.
#
# Kody wyjscia: 0 = klasa domknieta · 1 = znaleziona konstrukcja albo narzedzie slepe na
# pustke · 2 = nie da sie zmierzyc.
set -uo pipefail

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
[ -d narzedzia-qa ] || { echo "spoza-repo: nie widze katalogu narzedzi" >&2; exit 2; }

BLEDY=0

# ---------------------------------------------------------------------------
# CZESC 1 (statyczna): sama konstrukcja `cd "$(...)"` bez sprawdzenia wyniku.
# Tu mieszka wada. `cd ""` melduje sukces, wiec `|| exit` po niej jest kodem martwym.
# ---------------------------------------------------------------------------
echo '=== 1. KONSTRUKCJA cd "$(...)" — martwy kod zabezpieczajacy ==='
PLIKI="$(git ls-files '*.sh' 2> /dev/null)"
if [ -z "$PLIKI" ]; then
	echo "spoza-repo: nie widze zadnych skryptow w repo — nie ma czego sprawdzac" >&2
	exit 2
fi
ILE_PLIKOW="$(printf '%s\n' "$PLIKI" | wc -l)"
# ⛔ Odsiewamy linie KOMENTARZA. Bez tego straznik oskarza wlasny opis wady:
# `bramka-poswiadczenia.sh:13` to komentarz zaczynajacy sie od „NIE cd …", czyli
# ostrzezenie przed ta konstrukcja, a nie jej uzycie. Zmierzone 31.08 — pierwszy
# przebieg dal dokladnie to jedno falszywe trafienie.
TRAFIENIA="$(printf '%s\n' "$PLIKI" \
	| xargs grep -HnE 'cd[[:space:]]+"\$\((git|.*rev-parse)' 2> /dev/null \
	| grep -vE ':[0-9]+:[[:space:]]*#' || true)"
# ⛔ `-H` NIE jest ozdoba. Bez niego grep pominalby nazwe pliku, gdyby xargs dostal
# DOKLADNIE JEDEN plik — a wtedy filtr komentarzy (wzorzec z dwoma dwukropkami)
# przestalby trafiac i straznik zaczalby zglaszac wlasne komentarze jako wade.
# Zlapala to proba kontrolna nizej, nie przeglad kodu.
if [ -n "$TRAFIENIA" ]; then
	echo "ZNALEZIONA WADLIWA KONSTRUKCJA (cd na podstawieniu, ktore moze byc puste):" >&2
	printf '%s\n' "$TRAFIENIA" | sed 's/^/    /' >&2
	BLEDY=$((BLEDY + 1))
else
	echo "  czysto — 0 trafien w $ILE_PLIKOW plikach .sh"
fi

# PROBA KONTROLNA CZESCI 1: ten sam grep puszczony na PODLOZONEJ wadzie MUSI ja zobaczyc.
# Bez tego „0 trafien" i „zle szukam" wygladaja identycznie.
# Atrapa ma DWIE linie: wade w kodzie (ma zostac zlapana) i te sama konstrukcje
# w komentarzu (ma zostac odsiana). Testujemy CALY lancuch razem z filtrem — sam wzorzec
# bez filtra nie powiedzialby nam, czy filtr nie wycina za duzo.
# ⛔ WZORZEC ATRAPY SKLEJANY Z KAWALKOW, nie wpisany wprost. Inaczej TEN plik zawiera
# szukana konstrukcje i straznik oskarza samego siebie — zmierzone 31.08: dzialal na
# niesledzonym pliku, a po commicie zaczal padac, bo czesc statyczna czyta `git ls-files`.
# Ta sama klasa co atrapa sekretu wpisana literalnie (klasa 84).
ATRAPA="$(mktemp)" || exit 2
C=cd
P='rev-parse --show-toplevel'
{
	printf '%s "$(git %s 2>/dev/null)" || exit 2\n' "$C" "$P"
	printf '# NIE pisz %s "$(git %s)" — to martwy kod\n' "$C" "$P"
} > "$ATRAPA"
LAPIE="$(grep -HnE 'cd[[:space:]]+"\$\((git|.*rev-parse)' "$ATRAPA" | grep -vcE ':[0-9]+:[[:space:]]*#')"
if [ "$LAPIE" = "1" ]; then
	echo "  proba kontrolna: lancuch widzi wade w kodzie i odsiewa ja w komentarzu (1 z 2)"
else
	echo "  PROBA KONTROLNA PADLA (zlapane: $LAPIE, oczekiwano 1) — wynik czesci 1 bezwartosciowy" >&2
	BLEDY=$((BLEDY + 1))
fi
rm -f "$ATRAPA"

# ---------------------------------------------------------------------------
# CZESC 2 (dynamiczna): narzedzia, ktore korzen biora Z GITA, musza poza repo ODMOWIC.
# Tylko te — narzedzie wyznaczajace korzen ze sciezki wlasnego pliku poza repo pracuje
# dalej na prawdziwym repozytorium i jego zero jest uczciwe.
# ---------------------------------------------------------------------------
echo ""
echo "=== 2. NARZEDZIA BIORACE KORZEN Z GITA — poza repo musza odmowic ==="

# ⛔ POMIJANE z POWODEM w tresci, nie w komentarzu. Cicha redukcja zakresu czyta sie
# dokladnie jak „sprawdzono wszystko". Ten sam chwyt co w proby-kontrolne-przelot.sh.
declare -A POMIJANE=(
	[k7-stare-wersje.sh]="pisze do sciezki BEZWZGLEDNEJ poza repo i nadpisuje bez pytania (znalezisko I-10)"
)

PIASKOWNICA="$(mktemp -d)" || exit 2
trap 'rm -rf "$PIASKOWNICA"' EXIT
if git -C "$PIASKOWNICA" rev-parse --show-toplevel > /dev/null 2>&1; then
	echo "spoza-repo: katalog tymczasowy JEST w repo git — test nie zmierzylby niczego" >&2
	exit 2
fi

SPRAWDZONE=0
SLEPE=0
for SCIEZKA in narzedzia-qa/*.sh testy/*.sh; do
	[ -f "$SCIEZKA" ] || continue
	NAZWA="${SCIEZKA##*/}"
	case "$NAZWA" in *-proba-kontrolna.sh) continue ;; esac
	# Interesuja nas WYLACZNIE te, ktore pytaja gita o korzen.
	grep -q 'rev-parse --show-toplevel' "$SCIEZKA" 2> /dev/null || continue
	if [ -n "${POMIJANE[$NAZWA]:-}" ]; then
		printf '  pomijam   %-42s — %s\n' "$NAZWA" "${POMIJANE[$NAZWA]}"
		continue
	fi
	SPRAWDZONE=$((SPRAWDZONE + 1))
	PELNA="$KORZEN/$SCIEZKA"
	(cd "$PIASKOWNICA" && timeout 60 bash "$PELNA" > /dev/null 2>&1)
	KOD=$?
	if [ "$KOD" -eq 0 ]; then
		printf '  SLEPE     %-42s kod=0 — meldunek „czysto" na pustce\n' "$NAZWA" >&2
		SLEPE=$((SLEPE + 1))
	else
		printf '  OK        %-42s kod=%s (odmowil)\n' "$NAZWA" "$KOD"
	fi
done

# ⛔ PODLOGA. Zero sprawdzonych narzedzi znaczy, ze filtr nic nie wybral — a wtedy
# „wszystkie odmowily" jest zdaniem o pustym zbiorze. Ta sama klasa co I-2.
if [ "$SPRAWDZONE" -eq 0 ]; then
	echo "spoza-repo: ZERO narzedzi pytajacych gita o korzen — filtr chyba przestal trafiac" >&2
	exit 2
fi
echo "  sprawdzonych: $SPRAWDZONE, slepych: $SLEPE"
[ "$SLEPE" -eq 0 ] || BLEDY=$((BLEDY + 1))

echo ""
if [ "$BLEDY" -eq 0 ]; then
	echo "=== KLASA 73 DOMKNIETA: brak wadliwej konstrukcji i zadne narzedzie nie melduje czysto na pustce ==="
	exit 0
fi
echo "=== KLASA 73 NARUSZONA w $BLEDY miejscach — patrz wyzej ===" >&2
exit 1
