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
# ⛔ WZORZEC WŁASNY, NIE MIERZONY PLIK (recenzja #280 r5 W2). Pierwsza wersja brała
#    `head -1` z listy mierzonych plików i sprawdzała na nim DOKŁADNIE TEN SAM predykat,
#    co pętla pomiarowa niżej. Skutek: jedyny stan świata, po który ten strażnik został
#    napisany — prawdziwy martwy licznik w PIERWSZYM pliku listy — kończył się `EXIT=2`
#    („awaria przyrządu") i zdaniem odsyłającym czytelnika OD właściwego miejsca.
#    To klasa 138 odwrócona, w pliku, który tę klasę domyka.
#    Teraz przyrząd sprawdzam na ciągu, który sama zapisuję — pomiar i jego kontrola
#    nie mają wspólnego wejścia.
PROBNY=$(mktemp) || exit 2
trap 'rm -f "$PROBNY"' EXIT INT TERM
{
	printf 'KOTWICE=$((KOTWICE+1))\n'
	printf 'ODWROTNA=$(( ODWROTNA + 1 ))\n'
} > "$PROBNY"
if ! grep -qF 'KOTWICE=$((KOTWICE+1))' "$PROBNY" || \
   ! grep -qF 'ODWROTNA=$(( ODWROTNA + 1 ))' "$PROBNY"; then
	echo "NIE ZMIERZONO: próba kontrolna PRZYRZĄDU padła — mój własny grep nie widzi wzorca," >&2
	echo "               który sama przed chwilą zapisałam. To wada TEGO skryptu, nie prób." >&2
	exit 2
fi

# Gdy biegnę na KOPII drzewa (kierunek negatywny wyżej), pomijam własny przypadek —
# inaczej wpadłabym w rekurencję bez końca.
if [ -n "${LICZNIKI_BEZ_KIERUNKU_UJEMNEGO:-}" ]; then
	BEZ_UJEMNEGO=1
else
	BEZ_UJEMNEGO=0
fi
MARTWE=0
ILE=0
printf '=== LICZNIKI KOTWICE i ODWROTNA: czy któryś jest MARTWY — %s prób z podsumowanie_proby() ===\n' \
	"$(printf '%s\n' "$PLIKI" | wc -l)"
# ⛔ Zakres jest WĘŻSZY, niż sugerowałaby nazwa (recenzja #280 r5 D5): sprawdzam DWA liczniki
#    (`KOTWICE`, `ODWROTNA`) i tylko te próby, które definiują `podsumowanie_proby()` — dziś 19
#    z 56 plików pasujących do wzorca przelotu. Pozostałe mają własne finały i własne liczniki;
#    objęcie ich wymaga najpierw ujednolicenia, nie poszerzenia tego grepa.
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

# --- KIERUNEK NEGATYWNY (recenzja #280 r5 W1) --------------------------------
# ⛔ PO CO. Bez tego w repozytorium nie ma NICZEGO, co pokazuje, że ten strażnik potrafi
#    zrobić się czerwony. Zieleń, której nikt nie umie wywrócić, nie jest dowodem — to
#    ta sama reguła, którą ten strażnik egzekwuje wobec cudzych liczników.
# Mierzę na KOPII w katalogu tymczasowym: oryginalne drzewo zostaje nietknięte.
if [ "$BEZ_UJEMNEGO" -eq 1 ]; then
	echo
	echo "(kierunek negatywny pominięty — biegnę jako podprzebieg własnej próby)"
	if [ "$MARTWE" -eq 0 ]; then exit 0; fi
	exit 1
fi
KOPIA=$(mktemp -d) || exit 2
trap 'rm -f "$PROBNY"; rm -rf "$KOPIA"' EXIT INT TERM
# ⛔ SKRYPT LICZY KORZEŃ ZE ŚCIEŻKI WŁASNEGO PLIKU, więc uruchomiony „z kopii", ale
#    z oryginalnej lokalizacji, mierzyłby ORYGINAŁ — pierwsze podejście dało przez to
#    fałszywe „strażnik nie zareagował". Kopiuję więc CAŁE mierzone drzewo RAZEM ze
#    skryptem i uruchamiam KOPIĘ skryptu, żeby jej korzeniem była kopia.
OFIARA=$(printf '%s\n' "$PLIKI" | head -1)
for f in $PLIKI "$JA"; do
	mkdir -p "$KOPIA/$(dirname "$f")"
	cp "$f" "$KOPIA/$f"
done
PRZED=$(cksum < "$KOPIA/$OFIARA")
# ⛔ Kasuję WSZYSTKIE inkrementacje ODWROTNA w tym pliku, nie pierwszą z brzegu: licznik jest
#    martwy dopiero wtedy, gdy NIC go nie podnosi. Pierwsza wersja kasowała jedną z trzech,
#    licznik dalej żył, strażnik słusznie milczał — i wyglądało to na jego ślepotę.
#    Atrapa musi TWORZYĆ stan, którego szukam, a nie zbliżać się do niego.
sed -i 's/ODWROTNA=\$((\s*ODWROTNA\s*+\s*1\s*))/BLEDY=$((BLEDY+1))/g' "$KOPIA/$OFIARA"
PO=$(cksum < "$KOPIA/$OFIARA")

echo
echo "--- kierunek negatywny: podkładam martwy licznik w kopii $OFIARA ---"
if [ "$PRZED" = "$PO" ]; then
	# ⛔ Ten warunek jest tu z tego samego powodu, co w każdej innej próbie tego repo:
	#    bez niego „strażnik nie zareagował" i „nic nie podłożyłam" wyglądają identycznie.
	echo "  ⛔ NIE ZMIERZONO: sed nie zmienił kopii — kotwica zgniła, kierunek negatywny NIC nie mierzy." >&2
	exit 2
fi
WY=$( cd "$KOPIA" && LICZNIKI_BEZ_KIERUNKU_UJEMNEGO=1 bash "$KOPIA/$JA" 2>&1 )
KOD=$?
if [ "$KOD" -eq 1 ] && printf '%s' "$WY" | grep -qF "$OFIARA"; then
	echo "  OK   strażnik PADŁ na podłożonym martwym liczniku (EXIT=1) i wskazał właściwy plik"
else
	echo "  BŁĄD strażnik NIE zareagował na martwy licznik (EXIT=$KOD) — jego zieleń nic nie znaczy" >&2
	printf '%s\n' "$WY" | head -4 | sed 's/^/         /' >&2
	exit 1
fi

echo
if [ "$MARTWE" -eq 0 ]; then
	echo "KOTWICE i ODWROTNA ŻYWE w $ILE próbach z podsumowanie_proby() — każdy licznik ma"
	echo "  co najmniej jedno miejsce, które go podnosi. ⛔ To NIE jest werdykt o pozostałych"
	echo "  próbach w repozytorium ani o innych licznikach."
	exit 0
fi
echo "MARTWYCH LICZNIKÓW: $MARTWE — licznik, którego nic nie podnosi, niczego nie chroni."
echo "  Napraw miejsce, które ma go podnosić (kontrola odwrotna / nieudana mutacja),"
echo "  albo usuń licznik — ale nie zostawiaj go jako obietnicy bez pomiaru."
exit 1
