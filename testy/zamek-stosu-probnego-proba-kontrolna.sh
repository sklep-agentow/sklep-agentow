#!/usr/bin/env bash
# Próba kontrolna ZAMKA STOSU PRÓBNEGO (uwagi 1 i 2 z recenzji #272).   WERSJA 1.1.0
#
# PO CO: do 2.09 `probny_wp` miał OŚMIU użytkowników, a zamek brało dwóch — i to zamek
# stosu WARSZTATOWEGO (`wymiana/stos.lock`), czyli ten sam plik, który `linia-bazowa.sh`
# trzyma nad każdym `testy/*.sh`. Dawało to dwie wady naraz: kasowanie kontenera spod
# cudzego biegu (`postaw_stos` zaczyna od `docker rm -f -v`) oraz ZAKLESZCZENIE goldena
# na własnej blokadzie aż do `timeout 300` (EXIT=124, fałszywe czerwone w linii bazowej).
#
# CO MIERZY: woła PRAWDZIWĄ funkcję `wez_zamek_probny` z narzedzia-qa/lib-stos-probny.sh
# (nie jej kopię) i sprawdza DZIEWIĘĆ punktów (A–I), w tym próbę kontrolną samej próby.
# ⛔ Zamki idą do katalogu TYMCZASOWEGO, nie do `/root/praca/zlecenia/wymiana/` — inaczej
#    ten test padałby na koncie CI, które tamtej ścieżki nie ma, i świecił fałszywym czerwonym.
#    Że PRODUKCYJNE ścieżki to dwa RÓŻNE pliki, sprawdza osobno punkt F — czytając je z plików.
# ⛔ Nie wymaga Dockera: mierzy sam zamek, nie stawianie stosu.
#
# Kody wyjścia: 0 = zamek działa · 1 = WADA · 2 = przyrząd/środowisko.
set -u
cd "$(cd "$(dirname "$0")/.." && pwd)" || exit 2
[ -f narzedzia-qa/lib-stos-probny.sh ] || { echo "ZAMEK: brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }
command -v flock > /dev/null || { echo "ZAMEK: brak flock" >&2; exit 2; }

ROBOCZY="$(mktemp -d -t zamek-probny-XXXXXX)"
trap 'rm -rf "$ROBOCZY"' EXIT INT TERM
CUDZY="$ROBOCZY/stos-warsztatu.lock"    # udaje wymiana/stos.lock (bierze go linia-bazowa.sh)
NASZ="$ROBOCZY/stos-probny.lock"        # udaje wymiana/stos-probny.lock (bierze go postaw_stos)

BLEDY=0
sprawdz() { # $1 = opis, $2 = oczekiwane, $3 = otrzymane
	if [ "$2" = "$3" ]; then printf '  OK   %s (oczekiwane=%s)\n' "$1" "$2"
	else printf '  ZLE  %s — oczekiwane=%s otrzymane=%s\n' "$1" "$2" "$3"; BLEDY=$((BLEDY+1)); fi
}
wez() { # $1 = plik zamka, $2 = czekanie w s; wypisuje WZIETY albo ODPRAWIONY
	local wy
	wy=$(timeout 25 env ZAMEK_PROBNY="$1" ZAMEK_PROBNY_CZEKANIE_S="$2" \
		bash -c '. narzedzia-qa/lib-stos-probny.sh; wez_zamek_probny && echo WZIETY' 2>&1 | tail -1)
	case "$wy" in *"zajęty przez inny bieg"*) echo ODPRAWIONY ;; WZIETY) echo WZIETY ;; *) echo "$wy" ;; esac
}

echo "=== A. POD zamkiem WARSZTATU (tak uruchamia linia-bazowa.sh) — zakleszczenia BYC NIE MOZE ==="
WY=$(timeout 25 flock "$CUDZY" env ZAMEK_PROBNY="$NASZ" ZAMEK_PROBNY_CZEKANIE_S=5 \
	bash -c '. narzedzia-qa/lib-stos-probny.sh; wez_zamek_probny && echo WZIETY' 2>&1 | tail -1)
sprawdz "golden pod cudzym zamkiem bierze swoj" "WZIETY" "$WY"

echo "=== B. ZAMEK NAPRAWDE TRZYMA — drugi bieg musi zostac odprawiony ==="
env ZAMEK_PROBNY="$NASZ" ZAMEK_PROBNY_CZEKANIE_S=30 \
	bash -c '. narzedzia-qa/lib-stos-probny.sh; wez_zamek_probny; sleep 12' &
TRZYMA=$!
sleep 2
sprawdz "drugi bieg czeka i pada z powodem" "ODPRAWIONY" "$(wez "$NASZ" 3)"
wait "$TRZYMA" 2> /dev/null

echo "=== C. PO ZWOLNIENIU zamek znowu wolny ==="
sprawdz "po zwolnieniu wchodzi" "WZIETY" "$(wez "$NASZ" 5)"

echo "=== D. ZNACZNIK dziedziczony — potomek NIE bierze zamka drugi raz ==="
WY=$(timeout 25 env ZAMEK_PROBNY="$NASZ" ZAMEK_PROBNY_CZEKANIE_S=3 bash -c \
	'. narzedzia-qa/lib-stos-probny.sh; wez_zamek_probny; bash -c ". narzedzia-qa/lib-stos-probny.sh; wez_zamek_probny && echo WZIETY"' 2>&1 | tail -1)
sprawdz "potomek honoruje zamek przodka" "WZIETY" "$WY"

echo "=== E. PROBA KONTROLNA SAMEJ PROBY: z PODLOZONA wada (ten sam plik zamka) MUSI padnac ==="
WY=$(timeout 25 flock "$CUDZY" env ZAMEK_PROBNY="$CUDZY" ZAMEK_PROBNY_CZEKANIE_S=5 \
	bash -c '. narzedzia-qa/lib-stos-probny.sh; wez_zamek_probny && echo WZIETY' 2>&1 | tail -1)
case "$WY" in *"zajęty przez inny bieg"*) OTRZ=ZAKLESZCZENIE ;; WZIETY) OTRZ=WZIETY ;; *) OTRZ="$WY" ;; esac
sprawdz "podlozony stary ksztalt daje zakleszczenie" "ZAKLESZCZENIE" "$OTRZ"

echo "=== F. PRODUKCYJNE sciezki zamkow sa ROZNE (czytane z plikow, nie z pamieci) ==="
Z_LINII=$(grep -oE 'flock /[^ ]*\.lock' narzedzia-qa/linia-bazowa.sh | head -1 | awk '{print $2}')
Z_LIB=$(grep -oE 'ZAMEK_PROBNY="\$\{ZAMEK_PROBNY:-[^}]*\}"' narzedzia-qa/lib-stos-probny.sh \
	| head -1 | sed 's/.*:-//; s/}"//')
echo "  linia-bazowa.sh : ${Z_LINII:-BRAK}"
echo "  lib-stos-probny : ${Z_LIB:-BRAK}"
if [ -z "$Z_LINII" ] || [ -z "$Z_LIB" ]; then
	echo "  ZLE  nie odczytalam ktorejs sciezki z pliku — to wada przyrzadu, nie wynik"; BLEDY=$((BLEDY+1))
elif [ "$Z_LINII" = "$Z_LIB" ]; then
	echo "  ZLE  OBIE sciezki to ten sam plik — wraca zakleszczenie z uwagi 2"; BLEDY=$((BLEDY+1))
else
	echo "  OK   dwa rozne pliki zamkow (dwa rozne zasoby: stos warsztatowy i probny)"
fi

echo "=== G. KAZDY uzytkownik stosu probnego idzie przez postaw_stos, czyli bierze zamek ==="
BEZ=0
# ⛔ Szukamy plikow, ktore biblioteke NAPRAWDE DOLACZAJA (wiersz `. sciezka`), a nie takich,
# ktore ja tylko WSPOMINAJA w komentarzu albo w grepie — inaczej ta petla oskarza wlasny
# komentarz i test pada bez wady (zlapane 2.09 na linia-bazowa.sh).
UZYTKOWNIKOW=0
for f in $(grep -rlE '^[[:space:]]*\.[[:space:]]+.*lib-(stos-probny|golden-stos)\.sh' narzedzia-qa/*.sh testy/*.sh 2> /dev/null); do
	# ⛔ Biblioteki NIE sa uzytkownikami. Ten plik TEZ nie: dolacza biblioteke wylacznie
	#    w podpowlokach punktow H/I, zeby ja ZMIERZYC, i nigdy nie stawia stosu. Bez tego
	#    wykluczenia petla liczy SAMA SIEBIE (recenzja #272 r4, DROBNA 2: wychodzilo 10 przy
	#    dziewieciu w dokumentach) i przechodzi warunek nizej na WZMIANCE w komentarzu.
	case "$f" in */lib-stos-probny.sh|*/lib-golden-stos.sh|*/zamek-stosu-probnego-proba-kontrolna.sh) continue;; esac
	UZYTKOWNIKOW=$((UZYTKOWNIKOW+1))
	# ⛔ Komentarze i wzorce grepa NIE licza sie jako wywolanie — odsiewam wiersze komentarza.
	if grep -vE '^[[:space:]]*#' "$f" | grep -q 'postaw_stos\|golden_przygotuj_stos'; then
		printf '  OK   %s\n' "$f"
	else
		printf '  ZLE  %s dolacza biblioteke, ale nie wola postaw_stos — stoi poza zamkiem\n' "$f"; BEZ=$((BEZ+1))
	fi
done
[ "$BEZ" -eq 0 ] || BLEDY=$((BLEDY+1))
# ⛔ ASERCJA NA LICZBIE. Bez niej rozjazd miedzy ta komenda a liczba w CHANGELOG-u i w
# lib-golden-stos.sh:27 przechodzi bez sladu — dokladnie tak powstala DROBNA 2 rundy 4.
# ⛔ SAM LITERAL NICZEGO NIE LACZY Z DOKUMENTAMI (recenzja #272 r5, DROBNA 4): do r6 ten wiersz
# porownywal policzona liczbe z WLASNYM literalem, a komentarz obok obiecywal porownanie
# „z liczba w CHANGELOG-u i lib-golden-stos.sh:27". Dokumenty niosa te liczbe SLOWNIE, wiec
# ponizej sprawdzam takze, czy oba naprawde mowia to samo slowo. Zmiana liczby wymaga wtedy
# ruszenia WSZYSTKICH trzech miejsc — inaczej ta proba pada.
OCZEKIWANI_UZYTKOWNICY=9
case "$OCZEKIWANI_UZYTKOWNICY" in
	7) SLOWO_UZYTKOWNIKOW=siedmiu ;;
	8) SLOWO_UZYTKOWNIKOW=osmiu ;;
	9) SLOWO_UZYTKOWNIKOW=dziewięciu ;;
	10) SLOWO_UZYTKOWNIKOW=dziesięciu ;;
	11) SLOWO_UZYTKOWNIKOW=jedenastu ;;
	*) SLOWO_UZYTKOWNIKOW="" ;;
esac
if [ -z "$SLOWO_UZYTKOWNIKOW" ]; then
	printf "  ZLE  nie znam slownej postaci liczby %s — dopisz ja do case, inaczej dokumenty nie sa sprawdzane\n" "$OCZEKIWANI_UZYTKOWNICY"
	BLEDY=$((BLEDY+1))
else
	for _d in testy/lib-golden-stos.sh narzedzia-qa/CHANGELOG.md; do
		if grep -q "$SLOWO_UZYTKOWNIKOW" "$_d"; then
			printf "  OK   %s mowi '%s' — zgodnie z licznikiem\n" "$_d" "$SLOWO_UZYTKOWNIKOW"
		else
			printf "  ZLE  %s NIE mowi '%s' — rozjazd miedzy pomiarem a dokumentem\n" "$_d" "$SLOWO_UZYTKOWNIKOW"
			BLEDY=$((BLEDY+1))
		fi
	done
fi
if [ "$UZYTKOWNIKOW" -ne "$OCZEKIWANI_UZYTKOWNICY" ]; then
	printf '  ZLE  uzytkownikow stosu probnego: %s, a dokumenty mowia %s — rozjazd\n' "$UZYTKOWNIKOW" "$OCZEKIWANI_UZYTKOWNICY"
	BLEDY=$((BLEDY+1))
else
	printf '  OK   uzytkownikow stosu probnego: %s (zgodnie z dokumentami)\n' "$UZYTKOWNIKOW"
fi

echo "=== H. BIEG ODPRAWIONY PRZEZ ZAMEK NIE ZWIJA CUDZEGO STOSU ==="
# ⛔ Pulapka EXIT stoi w skryptach PRZED `postaw_stos`, wiec `zwin_stos` odpala sie takze
# wtedy, gdy zamek ten proces ODPRAWIL. Bez znacznika wlasnosci kasowal wtedy kontener
# i dropowal baze biegowi, KTORY ZAMEK TRZYMA (recenzja #272, runda 3).
# Mierzymy sciezke kodu, nie docker-a: podstawiamy funkcje `docker` i `sql_bazy`,
# ktore tylko MELDUJA wywolanie. Funkcja przeslania komende zewnetrzna.
SLAD="$ROBOCZY/zwijanie.log"
: > "$SLAD"
WY=$(
	. narzedzia-qa/lib-stos-probny.sh
	docker()   { echo "docker $*"   >> "$SLAD"; }
	sql_bazy() { echo "sql_bazy $*" >> "$SLAD"; }
	zwin_stos            # ten proces NIGDY nie wolal postaw_stos
	echo GOTOWE
)
LICZ_BEZ=$(wc -l < "$SLAD")
sprawdz "zwin_stos bez postaw_stos nie rusza niczego (0 wywolan)" "0" "$LICZ_BEZ"

echo "=== I. KONTROLA DODATNIA punktu H: WLASCICIEL stosu MUSI go zwinac ==="
: > "$SLAD"
WY=$(
	. narzedzia-qa/lib-stos-probny.sh
	docker()   { echo "docker $*"   >> "$SLAD"; }
	sql_bazy() { echo "sql_bazy $*" >> "$SLAD"; }
	STOS_PROBNY_POSTAWILEM=tak     # udajemy proces, ktory stos POSTAWIL
	zwin_stos
	echo GOTOWE
)
LICZ_Z=$(wc -l < "$SLAD")
if [ "$LICZ_Z" -ge 1 ]; then
	printf '  OK   wlasciciel stosu zwija (wywolan: %s)\n' "$LICZ_Z"
else
	echo "  ZLE  wlasciciel NIE zwina stosu — znacznik blokuje za duzo, stos zostaje na dysku"; BLEDY=$((BLEDY+1))
fi

echo
[ "$BLEDY" -eq 0 ] && { echo "ZAMEK STOSU PROBNEGO: WSZYSTKO OK (EXIT=0)"; exit 0; }
echo "ZAMEK STOSU PROBNEGO: $BLEDY punktow NIE WYSZLO (EXIT=1)"; exit 1
