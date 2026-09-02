#!/usr/bin/env bash
# Próba kontrolna ZAMKA STOSU PRÓBNEGO (uwagi 1 i 2 z recenzji #272).   WERSJA 1.0.0
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
for f in $(grep -rlE '^[[:space:]]*\.[[:space:]]+.*lib-(stos-probny|golden-stos)\.sh' narzedzia-qa/*.sh testy/*.sh 2> /dev/null); do
	case "$f" in */lib-stos-probny.sh|*/lib-golden-stos.sh) continue;; esac
	if grep -q 'postaw_stos\|golden_przygotuj_stos' "$f"; then
		printf '  OK   %s\n' "$f"
	else
		printf '  ZLE  %s dolacza biblioteke, ale nie wola postaw_stos — stoi poza zamkiem\n' "$f"; BEZ=$((BEZ+1))
	fi
done
[ "$BEZ" -eq 0 ] || BLEDY=$((BLEDY+1))

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
