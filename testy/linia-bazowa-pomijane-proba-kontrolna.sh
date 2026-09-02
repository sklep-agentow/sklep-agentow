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
sprawdz_uruchamiany() {
	case " $POMINIETE_LISTA " in *" $1 "*) echo "  ⛔ POMIJANY, a MUSI być uruchamiany: $1"; BLEDY=$((BLEDY+1)) ;;
	*) echo "  ✅ URUCHAMIANY: $1" ;; esac
}

echo
echo "=== MUSZĄ być pomijane (spust bramki i biblioteka) ==="
sprawdz_pominiety testy/golden-stos-probny.sh
sprawdz_pominiety testy/lib-golden-stos.sh
sprawdz_pominiety testy/golden-migawka-proba-kontrolna.sh   # *-proba-kontrolna.sh, było i zostaje

echo
echo "=== KONTROLA DODATNIA: prawdziwe testy .sh NIE MOGĄ zniknąć z linii bazowej ==="
ILE=0
for t in testy/*.sh; do
	case "$t" in *-proba-kontrolna.sh|*kalibracja-mutantem.sh|*seed-demo.sh|*golden-stos-probny.sh|*lib-golden-stos.sh) continue;; esac
	sprawdz_uruchamiany "$t"; ILE=$((ILE+1))
done
[ "$ILE" -ge 1 ] || { echo "  ⛔ ZERO testów do uruchomienia — kontrola dodatnia pusta, pomiar nic nie znaczy"; BLEDY=$((BLEDY+1)); }
echo "  (uruchamianych testów .sh: $ILE)"

echo
[ "$BLEDY" -eq 0 ] && { echo "LISTA POMIJANYCH: OK (EXIT=0)"; exit 0; }
echo "LISTA POMIJANYCH: $BLEDY NIE WYSZLO (EXIT=1)"; exit 1
