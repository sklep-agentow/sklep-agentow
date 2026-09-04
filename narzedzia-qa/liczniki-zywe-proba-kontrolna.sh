#!/bin/bash
# Strażnik: czy w próbach kontrolnych ŻADEN licznik nie jest MARTWY.
#
# ⛔ PO CO. Klasa 138 mówi, że nieudana mutacja (KOTWICE) i padnięta kontrola odwrotna
#    (ODWROTNA) muszą mieć WŁASNE liczniki, bo wspólny każe skryptowi orzec o PRODUKCIE
#    rzecz, której nie zmierzył. Ale licznik zadeklarowany, opisany w komentarzu
#    i sprawdzany w podsumowaniu, a NIGDY nie podnoszony, wygląda identycznie jak żywy —
#    i nie chroni przed niczym. Recenzja #280 r4 W1 znalazła dokładnie taki przypadek:
#    jeden plik z dziewiętnastu, w którym migracja objęła kształt funkcji, a nie miejsce,
#    w którym ta funkcja ma coś dostać.
#
# ⛔ GREP MUSI BYĆ `-F`. Wzorzec zawiera `$((`, a `grep` bez `-F` daje na nim FAŁSZYWE ZERO
#    — zmierzone 4.09: ten sam plik, `grep -c` → 0, `grep -cF` → 1. Pierwszy pomiar tej
#    wady pokazał 19 martwych liczników z 19; prawdziwa liczba to 1. Przyrząd kłamał,
#    nie dane. Dlatego niżej stoi PRÓBA KONTROLNA SAMEGO PRZYRZĄDU.
#
# Kody wyjścia: 0 = każdy licznik żywy · 1 = jest martwy licznik · 2 = nie zmierzono.
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2

# ⛔ Szukam plików, które funkcję DEFINIUJĄ, nie takich, które ją tylko wspominają —
#    inaczej ten strażnik łapie sam siebie (zmierzone przy pierwszym uruchomieniu).
# ⛔ I wykluczam SAM SIEBIE: ten plik zawiera szukany wzorzec w treści polecenia `grep`,
#    więc bez tego łapie się na własnej liście i melduje martwy licznik u siebie.
JA="narzedzia-qa/$(basename "${BASH_SOURCE[0]}")"
PLIKI=$(grep -rlF 'podsumowanie_proby() {' testy/ narzedzia-qa/ 2>/dev/null | grep -vF "$JA" | sort)
if [ -z "$PLIKI" ]; then
	echo "NIE ZMIERZONO: nie znalazłam ani jednej próby z podsumowanie_proby()" >&2
	exit 2
fi

# --- PRÓBA KONTROLNA PRZYRZĄDU ------------------------------------------------
# Zanim policzę cokolwiek, sprawdzam, że mój własny grep WIDZI wzorzec, którego szukam.
# Bez tego „zero martwych" i „zepsuty grep" wyglądają identycznie.
WZORCOWY=$(printf '%s\n' "$PLIKI" | head -1)
if ! grep -qF 'KOTWICE=$((KOTWICE+1))' "$WZORCOWY" && \
   ! grep -qF 'KOTWICE=$(( KOTWICE + 1 ))' "$WZORCOWY"; then
	echo "NIE ZMIERZONO: próba kontrolna przyrządu padła — w $WZORCOWY nie widzę inkrementacji," >&2
	echo "               choć plik ma podsumowanie_proby(). Sprawdź wzorzec, nie pliki." >&2
	exit 2
fi

MARTWE=0
ILE=0
echo "=== LICZNIKI PRÓB: czy któryś jest MARTWY (zadeklarowany, nigdy nie podnoszony) ==="
for f in $PLIKI; do
	ILE=$((ILE + 1))
	for licznik in KOTWICE ODWROTNA; do
		# dwa zapisy inkrementacji spotykane w repo: bez spacji i ze spacjami
		n=$(grep -cF "$licznik=\$((${licznik}+1))" "$f")
		m=$(grep -cF "$licznik=\$(( ${licznik} + 1 ))" "$f")
		if [ "$((n + m))" -eq 0 ]; then
			printf '  MARTWY  %-58s %s zadeklarowany, NIGDY nie podnoszony\n' "$f" "$licznik"
			MARTWE=$((MARTWE + 1))
		fi
	done
done

echo
if [ "$MARTWE" -eq 0 ]; then
	echo "WSZYSTKIE LICZNIKI ŻYWE w $ILE próbach (KOTWICE i ODWROTNA podnoszone w każdej)"
	exit 0
fi
echo "MARTWYCH LICZNIKÓW: $MARTWE — licznik, którego nic nie podnosi, niczego nie chroni."
echo "  Napraw miejsce, które ma go podnosić (kontrola odwrotna / nieudana mutacja),"
echo "  albo usuń licznik — ale nie zostawiaj go jako obietnicy bez pomiaru."
exit 1
