#!/usr/bin/env bash
# Próba kontrolna uwagi 2 (druga połowa): czy pętla `for t in testy/*.sh` w linia-bazowa.sh
# NAPRAWDĘ pomija spust bramki golden i bibliotekę — i czy nie pomija niczego więcej.
# ⛔ Wzorce NIE są przepisane z pamięci: wycinam DOSŁOWNE linie `case … esac` z pliku
#    i wykonuję je, więc mierzę kod z repo, a nie własne wyobrażenie o nim.
cd "$(cd "$(dirname "$0")/.." && pwd)" || exit 2
P=narzedzia-qa/linia-bazowa.sh

# blok pętli po testy/*.sh: od linii `for t in testy/*.sh; do` do najbliższego `done`
POCZ=$(grep -n '^for t in testy/\*\.sh; do$' "$P" | head -1 | cut -d: -f1)
[ -n "$POCZ" ] || { echo "⛔ nie znalazłam pętli po testy/*.sh w $P"; exit 2; }
KON=$(awk -v p="$POCZ" 'NR>p && /^done$/ {print NR; exit}' "$P")
CASE_LINIE=$(sed -n "${POCZ},${KON}p" "$P" | grep -E '^\s*case "\$t" in')
LICZBA=$(printf '%s\n' "$CASE_LINIE" | grep -c 'case')
echo "Wyciete z $P (linie $POCZ-$KON): $LICZBA warunkow pomijania"

BLEDY=0
POMINIETE_LISTA=""
for t in testy/*.sh; do
	WY=$(
		set -u
		TSV=/dev/null
		# ⛔ wyjscie z wnetrza `case` (echo "POMINIETE ...") MUSI isc do /dev/null — inaczej
		#    laduje w tej samej zmiennej co wynik i porownanie nigdy sie nie zgadza (zlapane 2.09).
		eval "$(printf '%s\n' "$CASE_LINIE" | sed 's/continue;;/POMINIETO=tak;;/')" > /dev/null 2>&1
		[ "${POMINIETO:-nie}" = tak ] && echo POMINIETE
	)
	[ "$WY" = POMINIETE ] && POMINIETE_LISTA="$POMINIETE_LISTA $t"
done

echo "POMIJANE przez pętlę:"
for f in $POMINIETE_LISTA; do echo "   - $f"; done

sprawdz_pominiety() {
	case " $POMINIETE_LISTA " in *" $1 "*) echo "  ✅ POMIJANY: $1" ;;
	*) echo "  ⛔ NIE JEST POMIJANY, a musi być: $1"; BLEDY=$((BLEDY+1)) ;; esac
}

echo
echo "=== MUSZĄ być pomijane (spust bramki i biblioteka) ==="
sprawdz_pominiety testy/golden-stos-probny.sh
sprawdz_pominiety testy/lib-golden-stos.sh
sprawdz_pominiety testy/golden-migawka-proba-kontrolna.sh   # *-proba-kontrolna.sh, było i zostaje

echo
echo "=== KONTROLA DODATNIA: prawdziwe testy .sh NIE MOGĄ zniknąć z linii bazowej ==="
ILE=0
# ⛔ ŻADNYCH WZORCÓW Z PAMIĘCI TAKŻE TUTAJ. Lista uruchamianych powstaje jako RÓŻNICA
# zbiorów: wszystkie testy/*.sh minus te, które pętla z pliku uznała za pomijane.
# ⛔ Wypis niżej jest TYLKO wypisem. Element z $POMINIETE_LISTA nie ma jak tu trafić,
#    bo ta sama pętla go odsiewa tym samym warunkiem — więc orzekanie o wadzie W ŚRODKU
#    pętli było gałęzią MARTWĄ i próba nie umiała paść (recenzja #272 r4, WAŻNA 1).
#    Asercją jest PRÓG PONIŻEJ, nie ten wypis.
for t in testy/*.sh; do
	case " $POMINIETE_LISTA " in *" $t "*) continue;; esac
	echo "  ✅ URUCHAMIANY: $t"; ILE=$((ILE+1))
done
# ⛔ DWIE ASERCJE, BO JEDNA NIE WYSTARCZA. Dopisanie wzorca pomijania do linia-bazowa.sh wyrzuca
# prawdziwe bramki z linii bazowej — ale prog `-ge` na SAMYCH URUCHAMIANYCH tego nie zlapie,
# gdy jeden commit dokłada wzorzec pomijania I nowy test naraz: liczba uruchamianych stoi w
# miejscu albo rosnie, a pokrycie realnie spada (recenzja #272 r5, DROBNA 3). Dlatego mierzone
# sa OBIE strony podzialu: uruchamiane nie moga spasc PONIZEJ progu, a pomijane nie moga
# przekroczyc sufitu. Kazde przesuniecie ktorejkolwiek liczby ma byc swiadoma decyzja w
# commicie — wzorem MAX_POMINIETYCH w narzedzia-qa/proby-kontrolne-przelot.sh:69.
# ⛔ SKAD LICZBY: policzone tym samym biegiem 3.09 (#272 r6) — `testy/*.sh` = 49 plikow,
# z tego 15 uruchamianych i 34 pomijane. ⚠️ Poprzedni komentarz uzasadnial 15 „stanem po
# dolaczeniu golden-migawka-proba-kontrolna.sh do pomijanych" i BYL NIEPRAWDZIWY: ten plik
# nie zostal nigdzie dolaczony, tylko podpada pod istniejacy od dawna wzorzec
# `*-proba-kontrolna.sh` (linia-bazowa.sh:27). Nic go do listy nie dopisywalo.
MIN_URUCHAMIANYCH=15
MAX_POMIJANYCH=40
[ "$ILE" -ge "$MIN_URUCHAMIANYCH" ] || { echo "  ⛔ URUCHAMIANYCH $ILE, prog $MIN_URUCHAMIANYCH — z linii bazowej UBYLY testy"; BLEDY=$((BLEDY+1)); }
[ "$ILE" -ge 1 ] || { echo "  ⛔ ZERO testów do uruchomienia — kontrola dodatnia pusta, pomiar nic nie znaczy"; BLEDY=$((BLEDY+1)); }
# ⛔ SUFIT POMIJANYCH — druga strona tego samego podzialu (patrz komentarz wyzej).
ILE_POMIJANYCH=0
for _p in $POMINIETE_LISTA; do ILE_POMIJANYCH=$((ILE_POMIJANYCH+1)); done
[ "$ILE_POMIJANYCH" -le "$MAX_POMIJANYCH" ] || { echo "  ⛔ POMIJANYCH $ILE_POMIJANYCH, sufit $MAX_POMIJANYCH — do linia-bazowa.sh DOSZEDL wzorzec pomijania"; BLEDY=$((BLEDY+1)); }
echo "  (uruchamianych testów .sh: $ILE, próg: $MIN_URUCHAMIANYCH · pomijanych: $ILE_POMIJANYCH, sufit: $MAX_POMIJANYCH)"

echo
[ "$BLEDY" -eq 0 ] && { echo "LISTA POMIJANYCH: OK (EXIT=0)"; exit 0; }
echo "LISTA POMIJANYCH: $BLEDY NIE WYSZLO (EXIT=1)"; exit 1
