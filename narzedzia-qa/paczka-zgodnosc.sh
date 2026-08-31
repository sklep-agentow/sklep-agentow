#!/bin/bash
# paczka-zgodnosc.sh — bramka ZAMROŻENIA: czy paczka to naprawdę to,        WERSJA 1.1.0
# co leży w repozytorium, i czy wersja mówi wszędzie to samo.
#
# PO CO TO POWSTAŁO: przy zamrażaniu produktu „na oko" sprawdza się dwie rzeczy
# i obie sprawdza się źle. Pierwsza: czy zip zawiera to, co repo — a spojrzenie
# na listę plików NIE MÓWI NIC o ich treści, bo plik o tej samej nazwie może być
# starą wersją sprzed trzech PR-ów. Druga: czy wersja się zgadza — a wersja
# mieszka w SZEŚCIU miejscach (sklep-agentow: SIEDMIU — dochodzi odznaka wydania w README repo) i wystarczy, że jedno zostanie w tyle, żeby kupujący
# dostał paczkę podpisaną cudzym numerem. (Szóste — nagłówek DOKUMENTACJA-TECHNICZNA.md —
# dopisane po recenzji PR #168: bramka mówiła „WERSJA SPÓJNA", a dokument JADĄCY W PACZCE
# niósł stary numer.)
#
# Dlatego: KAŻDY plik porównywany sumą kontrolną SHA-256, w obie strony,
# a wersja czytana ze wszystkich sześciu (sklep: siedmiu) miejsc naraz.
#
# DWIE RZECZY, KTÓRYCH ZGODNY NUMER NIE ŁAPIE (dopisane przy zamrażaniu 1.5.7):
# (C) Numer w CHANGELOG-u zgadza się także wtedy, gdy pod nagłówkiem wydania
#     nie ma ANI JEDNEJ linii treści. Kupujący dostaje wtedy wydanie, o którym
#     nie napisano nic — a bramka wersji świeci na zielono, bo numery pasują.
#     Sprawdzamy więc, że sekcja wydania ma TREŚĆ, w CHANGELOG.md i w readme.txt.
# (D) Zip równy repozytorium co do bajtu jest wciąż zipem, w którym mogą siedzieć
#     nasze ŚLADY WEWNĘTRZNE: TODO, notatki QA, ścieżki drzewa roboczego, numery
#     PR-ów, adresy maszyny dev. Zgodność z repo takiego śladu nie widzi — bo
#     ślad jest w repo TAK SAMO jak w paczce. Skanujemy więc rozpakowaną paczkę.
#
# Użycie:
#   bash narzedzia-qa/paczka-zgodnosc.sh segregator-maila
#   bash narzedzia-qa/paczka-zgodnosc.sh chatbot-live
#
# Kody wyjścia: 0 = paczka zgodna z repo co do bajtu i wersja spójna
#               1 = rozjazd — wypisany, z nazwą pliku albo miejsca
#               2 = nie ma czego sprawdzać (brak wtyczki, brak zipa, brak narzędzia)
set -u

WERSJA_BRAMKI=1.1.0

# KORZEN_PROBY: próba kontrolna podstawia kopię drzewa, żeby móc podkładać
# uszkodzone paczki i rozjechane wersje BEZ dotykania prawdziwych plików repo.
KORZEN="${KORZEN_PROBY:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$KORZEN" || exit 2

WTYCZKA="${1:-}"
if [ -z "$WTYCZKA" ]; then
	echo "Użycie: bash narzedzia-qa/paczka-zgodnosc.sh <katalog-wtyczki>" >&2
	exit 2
fi

ZRODLO="wtyczki/$WTYCZKA"
[ -d "$ZRODLO" ] || { echo "PACZKA-ZGODNOSC: nie ma katalogu $ZRODLO" >&2; exit 2; }
command -v unzip > /dev/null || { echo "PACZKA-ZGODNOSC: brak unzip" >&2; exit 2; }
command -v sha256sum > /dev/null || { echo "PACZKA-ZGODNOSC: brak sha256sum" >&2; exit 2; }

GLOWNY="$ZRODLO/$WTYCZKA.php"
[ -f "$GLOWNY" ] || { echo "PACZKA-ZGODNOSC: nie ma pliku głównego $GLOWNY" >&2; exit 2; }

BLEDY=0
zglos() { printf '  ROZJAZD  %s\n' "$1"; BLEDY=$(( BLEDY + 1 )); }
potwierdz() { printf '  OK       %s\n' "$1"; }

printf '=== PACZKA-ZGODNOSC %s — wtyczka: %s ===\n\n' "$WERSJA_BRAMKI" "$WTYCZKA"

# --- 1. wersja w sześciu miejscach --------------------------------------------

echo "--- A. Wersja mówi wszędzie to samo ---"

# 1) nagłówek wtyczki
W_NAGLOWEK="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9]\+\(\.[0-9]\+\)*\).*/\1/p' "$GLOWNY" | head -1)"
# 2) stała w kodzie (SEGM_WERSJA / CHATL_WERSJA — pierwsza stała *_WERSJA)
W_STALA="$(sed -n "s/^define([[:space:]]*'[A-Z]*_WERSJA'[[:space:]]*,[[:space:]]*'\([0-9]\+\(\.[0-9]\+\)*\)'.*/\1/p" "$GLOWNY" | head -1)"
# 3) Stable tag w readme.txt
W_README="$(sed -n 's/^Stable tag:[[:space:]]*\([0-9]\+\(\.[0-9]\+\)*\).*/\1/p' "$ZRODLO/readme.txt" 2> /dev/null | head -1)"
# 4) najnowszy WYDANY wpis w CHANGELOG.md (pomijamy [Niewydane])
W_CHANGELOG="$(grep -m1 '^## \[[0-9]' "$ZRODLO/CHANGELOG.md" 2> /dev/null | sed 's/^## \[\([0-9]\+\(\.[0-9]\+\)*\)\].*/\1/')"
# 5) najnowszy wpis w sekcji Changelog readme.txt (format WP: „= 1.3.0 =")
W_README_CH="$(grep -m1 '^= [0-9]' "$ZRODLO/readme.txt" 2> /dev/null | sed 's/^= \([0-9]\+\(\.[0-9]\+\)*\) =.*/\1/')"
# Szóste miejsce: nagłówek dokumentacji technicznej (jedzie w paczce do klienta).
W_DOKTECH="$(grep -m1 -oE 'Wersja wtyczki:[* ]*[0-9]+(\.[0-9]+)*' "$ZRODLO/DOKUMENTACJA-TECHNICZNA.md" 2> /dev/null | grep -oE '[0-9]+(\.[0-9]+)*' | head -1)"
# Siódme miejsce (TYLKO sklep-agentow): statyczna odznaka wydania w README repo.
# Klasa 10 + ściąga:18 (catsnboard: badge wersji zapomniany 3×). Odznaka DYNAMICZNA
# shields jest ślepa na repo prywatne („no releases or repo not found" przy istniejącym
# Latest — zmierzone 31.08), więc trzymamy STATYCZNĄ, a świeżość wymusza ta bramka.
W_BADGE=""
if [ "$WTYCZKA" = "sklep-agentow" ]; then
	W_BADGE="$(grep -m1 -oE 'badge/wydanie-v[0-9]+(\.[0-9]+)*-' README.md 2> /dev/null | grep -oE '[0-9]+(\.[0-9]+)*' | head -1)"
fi

printf '  nagłówek wtyczki:      %s\n' "${W_NAGLOWEK:-BRAK}"
printf '  stała w kodzie:        %s\n' "${W_STALA:-BRAK}"
printf '  Stable tag (readme):   %s\n' "${W_README:-BRAK}"
printf '  CHANGELOG.md:          %s\n' "${W_CHANGELOG:-BRAK}"
printf '  Changelog w readme:    %s\n' "${W_README_CH:-BRAK}"
printf '  DOKUMENTACJA-TECHN.:   %s\n' "${W_DOKTECH:-BRAK}"
[ "$WTYCZKA" = "sklep-agentow" ] && printf '  odznaka README (repo): %s\n' "${W_BADGE:-BRAK}"
echo

for para in "nagłówek wtyczki:$W_NAGLOWEK" "stała w kodzie:$W_STALA" \
	"Stable tag w readme.txt:$W_README" "CHANGELOG.md:$W_CHANGELOG" \
	"sekcja Changelog w readme.txt:$W_README_CH" \
	"nagłówek DOKUMENTACJA-TECHNICZNA.md:$W_DOKTECH"; do
	gdzie="${para%%:*}"; ile="${para#*:}"
	if [ -z "$ile" ]; then
		zglos "$gdzie — nie umiem odczytać wersji"
	elif [ "$ile" != "$W_NAGLOWEK" ]; then
		zglos "$gdzie mówi $ile, a nagłówek wtyczki $W_NAGLOWEK"
	fi
done
MIEJSC="sześć"
if [ "$WTYCZKA" = "sklep-agentow" ]; then
	MIEJSC="siedem"
	if [ -z "$W_BADGE" ]; then
		zglos "odznaka wydania w README repo — nie umiem odczytać wersji"
	elif [ "$W_BADGE" != "$W_NAGLOWEK" ]; then
		zglos "odznaka wydania w README repo mówi $W_BADGE, a nagłówek wtyczki $W_NAGLOWEK"
	fi
fi
[ "$BLEDY" -eq 0 ] && potwierdz "$MIEJSC miejsc zgodnych: $W_NAGLOWEK"

# Numer, który nie wygląda jak numer, przeszedłby przez porównanie „wszystkie
# mówią to samo", bo wszystkie mówiłyby to samo źle. Nagłówek jest wzorcem dla
# pozostałych czterech miejsc i dla nazwy paczki, więc jego surowa postać
# sprawdzana jest osobno (uwaga z recenzji PR #101).
SUROWA_WERSJA="$(sed -n 's/^ \* Version:[[:space:]]*\([^[:space:]]\+\).*/\1/p' "$GLOWNY" | head -1)"
case "$SUROWA_WERSJA" in
	*[!0-9.]*|.*|*.|"") zglos "nagłówek wtyczki ma wersję [$SUROWA_WERSJA] — to nie wygląda jak numer wydania" ;;
esac


# ⛔ Wersja spójna w pięciu miejscach to JESZCZE NIE ZNACZY, że jest prawdziwa.
# Niepusty [Niewydane] mówi wprost: w kodzie siedzą zmiany PONAD ostatnie
# wydanie, więc paczka z tym numerem NIE JEST tym, co ten numer opisuje.
# Przy zamrażaniu to jest błąd, nie drobiazg.
NIEWYDANE="$(awk '/^## \[Niewydane\]/{f=1;next} /^## \[/{f=0} f' "$ZRODLO/CHANGELOG.md" 2> /dev/null | grep -c '^- ')"
if [ "${NIEWYDANE:-0}" -gt 0 ]; then
	zglos "CHANGELOG ma niepustą sekcję [Niewydane] ($NIEWYDANE pozycji) — kod jest PONAD wersją $W_NAGLOWEK"
else
	potwierdz "sekcja [Niewydane] jest pusta — kod odpowiada wydanej wersji"
fi

# --- 2. paczka co do bajtu ----------------------------------------------------

echo
echo "--- B. Zip zgadza się z repozytorium co do pliku ---"

ZIP="boty/paczki/$WTYCZKA-$W_NAGLOWEK.zip"
if [ ! -f "$ZIP" ]; then
	zglos "nie ma paczki $ZIP dla wersji z nagłówka"
	echo
	echo "PACZKA-ZGODNOSC: $BLEDY rozjazd(ow) — NIE ZAMRAZAJ w tym stanie"
	exit 1
fi
printf '  paczka: %s\n' "$ZIP"

ROBOCZY="$(mktemp -d)"
trap 'rm -rf "$ROBOCZY"' EXIT
unzip -q -o "$ZIP" -d "$ROBOCZY" || { echo "PACZKA-ZGODNOSC: nie umiem rozpakować $ZIP" >&2; exit 2; }

[ -d "$ROBOCZY/$WTYCZKA" ] || { zglos "zip nie ma katalogu $WTYCZKA/ w środku"; }

W_ZIPIE="$ROBOCZY/$WTYCZKA"
LISTA_ZIP="$ROBOCZY/lista-zip.txt"
LISTA_REPO="$ROBOCZY/lista-repo.txt"
( cd "$W_ZIPIE" 2> /dev/null && find . -type f | sed 's|^\./||' | sort ) > "$LISTA_ZIP"
( cd "$ZRODLO" && find . -type f | sed 's|^\./||' | sort ) > "$LISTA_REPO"

ILE_ZIP="$(wc -l < "$LISTA_ZIP")"
printf '  plików w zipie: %s\n\n' "$ILE_ZIP"

# Kierunek 1: każdy plik z zipa musi istnieć w repo i mieć tę SAMĄ treść.
ROZNE=0
while IFS= read -r plik; do
	[ -n "$plik" ] || continue
	if [ ! -f "$ZRODLO/$plik" ]; then
		zglos "$plik — jest w paczce, NIE MA GO w repozytorium"
		ROZNE=$(( ROZNE + 1 ))
		continue
	fi
	suma_zip="$(sha256sum "$W_ZIPIE/$plik" | cut -d' ' -f1)"
	suma_repo="$(sha256sum "$ZRODLO/$plik" | cut -d' ' -f1)"
	if [ "$suma_zip" != "$suma_repo" ]; then
		zglos "$plik — TA SAMA NAZWA, INNA TREŚĆ (zip ${suma_zip:0:12}… vs repo ${suma_repo:0:12}…)"
		ROZNE=$(( ROZNE + 1 ))
	fi
done < "$LISTA_ZIP"
[ "$ROZNE" -eq 0 ] && potwierdz "wszystkie $ILE_ZIP plików zipa ma sumę SHA-256 równą plikowi z repo"

# Kierunek 2: plik wtyczki, którego NIE MA w paczce, to cichy brak u kupującego.
BRAKUJACE="$(comm -23 "$LISTA_REPO" "$LISTA_ZIP")"
if [ -n "$BRAKUJACE" ]; then
	while IFS= read -r plik; do
		[ -n "$plik" ] || continue
		zglos "$plik — jest w repozytorium, NIE WCHODZI do paczki"
	done <<< "$BRAKUJACE"
else
	potwierdz "żaden plik wtyczki nie został pominięty przy pakowaniu"
fi

# Kierunek 3: zip musi zgadzać się z allowlistą skryptu budującego — inaczej
# „zbudowane" i „zadeklarowane" rozjeżdżają się bez śladu.
ALLOW="$ROBOCZY/allowlist.txt"
sed -n "/^ALLOWLIST_[A-Z]*=(/,/^)/p" boty/buduj-paczki.sh \
	| grep -o "\"$WTYCZKA/[^\"]*\"" | tr -d '"' | sed "s|^$WTYCZKA/||" | sort > "$ALLOW"
if [ ! -s "$ALLOW" ]; then
	zglos "nie znalazłem allowlisty dla $WTYCZKA w boty/buduj-paczki.sh"
elif ! diff -q "$ALLOW" "$LISTA_ZIP" > /dev/null; then
	zglos "zawartość zipa różni się od allowlisty w buduj-paczki.sh:"
	diff "$ALLOW" "$LISTA_ZIP" | sed 's/^/           /'
else
	potwierdz "zip zgadza się z allowlistą buduj-paczki.sh ($(wc -l < "$ALLOW") pozycji)"
fi

# --- 3. CHANGELOG ma WPIS, nie sam zgodny numer ------------------------------

echo
echo "--- C. Wydanie ma opis, nie sam numer ---"

# Sekcja wydawanej wersji w CHANGELOG.md: liczy się TREŚĆ (linie listy), a nie
# sam nagłówek z datą. Wydanie bez ani jednej linii opisu przechodziło przez
# porównanie numerów, bo numer był poprawny — brakowało tego, co ten numer znaczy.
TRESC_MD="$(awk -v w="$W_NAGLOWEK" '
	index($0, "## [" w "]") == 1 { wsekcji = 1; next }
	/^## \[/ { wsekcji = 0 }
	wsekcji && /^[-*] / { ile++ }
	END { print ile + 0 }' "$ZRODLO/CHANGELOG.md" 2> /dev/null)"
if [ "${TRESC_MD:-0}" -gt 0 ]; then
	potwierdz "CHANGELOG.md: wpis [$W_NAGLOWEK] ma treść ($TRESC_MD pozycji)"
else
	zglos "CHANGELOG.md ma nagłówek [$W_NAGLOWEK], ale POD NIM ZERO TREŚCI — wydanie bez opisu"
fi

# To samo w readme.txt (format WP: „= 1.5.7 ="). Sklep pokazuje kupującemu tę
# sekcję, nie CHANGELOG.md, więc pusto TUTAJ boli tak samo.
TRESC_README="$(awk -v w="$W_NAGLOWEK" '
	index($0, "= " w " =") == 1 { wsekcji = 1; next }
	/^= [0-9]/ { wsekcji = 0 }
	/^== / { wsekcji = 0 }
	wsekcji && NF { ile++ }
	END { print ile + 0 }' "$ZRODLO/readme.txt" 2> /dev/null)"
if [ "${TRESC_README:-0}" -gt 0 ]; then
	potwierdz "readme.txt: sekcja = $W_NAGLOWEK = ma treść ($TRESC_README linii)"
else
	zglos "readme.txt ma sekcję = $W_NAGLOWEK =, ale POD NIĄ ZERO TREŚCI — wydanie bez opisu"
fi

# --- 4. ślady wewnętrzne w paczce --------------------------------------------

echo
echo "--- D. Paczka bez śladów wewnętrznych ---"

# Skanujemy ROZPAKOWANĄ PACZKĘ, nie repo: pytanie brzmi „co dostaje kupujący".
# Wzorce siedzą w JEDNYM miejscu — narzedzia-qa/slady-wewnetrzne.sh — bo to samo
# pytanie zadaje bramka `slady` przy każdym PR-ze, a dwie kopie listy wzorców
# rozjechałyby się po pierwszym dopisku.
#
# ⛔ SLADY_ZALEGLOSCI=/dev/null: przy zamrażaniu lista długu NIE OBOWIĄZUJE.
# Paczka oddawana kupującemu ma mieć zero śladów — także tych, z którymi bramka
# codzienna żyje, bo czekają na właściciela modułu.
SKANER="$KORZEN/narzedzia-qa/slady-wewnetrzne.sh"
if [ ! -f "$SKANER" ]; then
	zglos "nie ma $SKANER — nie mam czym sprawdzić śladów wewnętrznych"
else
	WYNIK_SLADY="$( SLADY_ZALEGLOSCI=/dev/null bash "$SKANER" "$WTYCZKA" "$W_ZIPIE" )" || true
	ILE_SLADOW=0
	while IFS= read -r linia; do
		case "$linia" in
			"  SLAD "*)
				zglos "ślad wewnętrzny: $(printf '%s' "$linia" | sed 's/^  SLAD  *//')"
				ILE_SLADOW=$(( ILE_SLADOW + 1 ))
				;;
		esac
	done <<< "$WYNIK_SLADY"
	[ "$ILE_SLADOW" -eq 0 ] && potwierdz "w $ILE_ZIP plikach paczki nie ma śladów wewnętrznych (skaner $(sed -n 's/^WERSJA_NARZEDZIA=//p' "$SKANER"))"
fi

# --- werdykt ------------------------------------------------------------------

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "PACZKA ZGODNA Z REPOZYTORIUM I WERSJA SPOJNA — mozna zamrazac"
	exit 0
fi
echo "PACZKA-ZGODNOSC: $BLEDY rozjazd(ow) — NIE ZAMRAZAJ w tym stanie"
exit 1
