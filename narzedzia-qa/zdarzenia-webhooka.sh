#!/bin/bash
# zdarzenia-webhooka.sh — lista zdarzeń Stripe W INSTRUKCJI musi być równa liście
# zdarzeń OBSŁUGIWANYCH W KODZIE.
#
# PO CO (znalezisko A7-F1, 1.09): instrukcja instalacji kazała zaznaczyć w Dashboardzie
# Stripe **DWA** zdarzenia, a kod obsługiwał **JEDENAŚCIE** w czterech rodzinach. Klient,
# który wykonał instrukcję dosłownie, nie dostawał powiadomienia ani o zwrocie, ani
# o obciążeniu zwrotnym — a Pulpit liczy przychód jako `kwota - zwrocono`, więc oddane
# pieniądze zostawały w statystyce sprzedaży NA ZAWSZE. To pieniądze, nie kosmetyka.
#
# ⛔ DLACZEGO BRAMKA, A NIE SAMA POPRAWKA TEKSTU: dopisanie dziewięciu nazw zamyka
# egzemplarz. Klasa wraca przy KAŻDYM nowym zdarzeniu dołożonym do `zakup.php` — bo nic
# nie zmusza autora, żeby ruszył także instrukcję. Ta bramka zmusza.
#
# CO PORÓWNUJE
#   źródło prawdy : nazwy zdarzeń w `wtyczki/sklep-agentow/includes/zakup.php`
#   dokumenty     : każdy śledzony `*.md`/`*.txt`, który w ogóle wymienia nazwę zdarzenia,
#                   poza wykluczeniami niżej
#
# REGUŁA: dokument wymienia albo ZERO nazw, albo DOKŁADNIE te z kodu. Podzbiór jest
# gorszy niż milczenie — czytelnik uznaje niepełną listę za kompletną i klika dwie pozycje
# zamiast jedenastu. Zapis `rodzina.*` liczy się jako komplet tej rodziny (dokumentacja
# techniczna ma prawo streszczać; instrukcja do klikania nie — tam trzeba nazw).
#
# WYKLUCZENIA (świadome, nie wygodnicze):
#   CHANGELOG.md      — opisuje WYDANIE, w którym doszły akurat te zdarzenia. Zdanie
#                       o przeszłości nie ma obowiązku być dzisiejszą listą.
#   archiwum-procesu/ — świat sprzed zwrotu D16, nieaktualny z definicji.
#   audyt/            — raporty i rejestry opisują stan z chwili pomiaru.
#
# Wywołanie: bash narzedzia-qa/zdarzenia-webhooka.sh [ścieżka-repo]
# Kody wyjścia: 0 = zgodne · 1 = rozjazd · 2 = błąd przyrządu (nie ma czego porównywać —
#               pusty wynik NIE jest zielony).
set -u

REPO="${1:-.}"
cd "$REPO" 2> /dev/null || {
	echo "ZDARZENIA-WEBHOOKA: nie umiem wejsc do $REPO" >&2
	exit 2
}
KOD=wtyczki/sklep-agentow/includes/zakup.php
if [ ! -f "$KOD" ]; then
	echo "ZDARZENIA-WEBHOOKA: BRAK $KOD — nie ma zrodla prawdy, wiec nie ma zgodnosci." >&2
	exit 2
fi

# Kształt nazwy zdarzenia Stripe, jakie ta wtyczka w ogóle obsługuje. Zawężony do trzech
# rodzin świadomie: szerszy wzorzec łapałby w dokumentach zwykłe zdania z kropkami.
WZOR='(checkout\.session|charge|refund)\.[a-z_]+(\.[a-z_]+)*'

# --- 1. ŹRÓDŁO PRAWDY: nazwy z kodu -------------------------------------------
# Literał zakończony kropką (`charge.dispute.`) to PREFIKS RODZINY — kod rozpoznaje
# po nim całą rodzinę, a poszczególne nazwy rozróżnia niżej, w księgowaniu sporu.
SUROWE=$(grep -oE "'${WZOR}\.?'" "$KOD" | tr -d "'" | sort -u)
NAZWY=$(printf '%s\n' "$SUROWE" | grep -v '\.$' | sort -u)
RODZINY=$(printf '%s\n' "$SUROWE" | grep '\.$' | sort -u)

if [ -z "$NAZWY" ]; then
	echo "ZDARZENIA-WEBHOOKA: w $KOD nie znalazlem ANI JEDNEJ nazwy zdarzenia." >&2
	echo "                    To awaria wydobycia, nie czysty stan — bramka odmawia zielonego." >&2
	exit 2
fi

# ⛔ KONTROLA DODATNIA WYDOBYCIA: rodzina bez ani jednej konkretnej nazwy znaczy, że kod
# obsługuje coś, czego ta bramka NIE UMIE wypisać — i cicho porównywalibyśmy zbiór uboższy
# niż rzeczywistość. Takie „zgodne" nic by nie znaczyło.
BRAK_CZLONKOW=""
while IFS= read -r RODZ; do
	[ -n "$RODZ" ] || continue
	if ! printf '%s\n' "$NAZWY" | grep -q "^${RODZ//./\\.}"; then
		BRAK_CZLONKOW="$BRAK_CZLONKOW $RODZ"
	fi
done <<< "$RODZINY"
if [ -n "$BRAK_CZLONKOW" ]; then
	echo "ZDARZENIA-WEBHOOKA: rodzina(y)$BRAK_CZLONKOW obslugiwana prefiksem, ale ANI JEDNA" >&2
	echo "                    jej nazwa nie wystepuje w kodzie — nie umiem wypisac, co porownac." >&2
	exit 2
fi

ILE_KOD=$(printf '%s\n' "$NAZWY" | wc -l | tr -d ' ')
echo "=== ZDARZENIA-WEBHOOKA 1.0.0 ==="
echo "KOD ($KOD): $ILE_KOD nazw"
printf '  %s\n' $NAZWY

# --- 2. DOKUMENTY -------------------------------------------------------------
if command -v git > /dev/null 2>&1 && git rev-parse --git-dir > /dev/null 2>&1; then
	PLIKI=$(git ls-files '*.md' '*.txt')
else
	PLIKI=$(find . -type f \( -name '*.md' -o -name '*.txt' \) | sed 's|^\./||')
fi
PLIKI=$(printf '%s\n' "$PLIKI" | grep -v '^archiwum-procesu/' | grep -v '^audyt/' | grep -v 'CHANGELOG\.md$')

BLAD=0
SPRAWDZONE=0
echo
while IFS= read -r F; do
	[ -n "$F" ] && [ -f "$F" ] || continue
	# `rodzina.*` traktujemy jak komplet rodziny — stąd opcjonalne `\.\*` we wzorcu.
	DOK=$(grep -oE "${WZOR}(\.\*)?" "$F" 2> /dev/null | sort -u)
	[ -n "$DOK" ] || continue
	SPRAWDZONE=$((SPRAWDZONE + 1))

	ROZWINIETE=""
	while IFS= read -r P; do
		[ -n "$P" ] || continue
		case "$P" in
			*'.*')
				PREF="${P%\*}"
				CZLONKOWIE=$(printf '%s\n' "$NAZWY" | grep "^${PREF//./\\.}" || true)
				if [ -z "$CZLONKOWIE" ]; then
					ROZWINIETE="$ROZWINIETE$P"$'\n'
				else
					ROZWINIETE="$ROZWINIETE$CZLONKOWIE"$'\n'
				fi
				;;
			*) ROZWINIETE="$ROZWINIETE$P"$'\n' ;;
		esac
	done <<< "$DOK"
	ROZWINIETE=$(printf '%s' "$ROZWINIETE" | grep -v '^$' | sort -u)

	BRAKI=$(comm -23 <(printf '%s\n' "$NAZWY") <(printf '%s\n' "$ROZWINIETE"))
	NADMIAR=$(comm -13 <(printf '%s\n' "$NAZWY") <(printf '%s\n' "$ROZWINIETE"))

	if [ -z "$BRAKI" ] && [ -z "$NADMIAR" ]; then
		printf '  OK       %s — lista rowna kodowi\n' "$F"
		continue
	fi
	BLAD=$((BLAD + 1))
	printf '  ROZJAZD  %s\n' "$F"
	if [ -n "$BRAKI" ]; then
		printf '    BRAKUJE (kod obsluguje, dokument milczy):\n'
		printf '      %s\n' $BRAKI
	fi
	if [ -n "$NADMIAR" ]; then
		printf '    NADMIAR (dokument kaze, kod nie obsluguje):\n'
		printf '      %s\n' $NADMIAR
	fi
done <<< "$PLIKI"

# ⛔ DRUGA KONTROLA DODATNIA: zero sprawdzonych dokumentow to nie „wszystko zgodne",
# tylko „nie zmierzylem niczego" — np. po przeniesieniu instrukcji albo zmianie wzorca.
if [ "$SPRAWDZONE" -eq 0 ]; then
	echo "ZDARZENIA-WEBHOOKA: zaden dokument nie wymienia zdarzen — nie ma czego porownac." >&2
	echo "                    Instrukcja instalacji MUSI je wymieniac; to awaria, nie czysty stan." >&2
	exit 2
fi

echo
echo "ZDARZENIA-WEBHOOKA: dokumentow z lista: $SPRAWDZONE, rozjazdow: $BLAD"
[ "$BLAD" -eq 0 ] || echo "ZDARZENIA-WEBHOOKA: instrukcja i kod mowia CO INNEGO o pieniadzach — napraw dokument." >&2
exit $((BLAD > 0 ? 1 : 0))
