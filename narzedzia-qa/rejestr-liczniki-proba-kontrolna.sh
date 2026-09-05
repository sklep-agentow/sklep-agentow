#!/bin/bash
# rejestr-liczniki-proba-kontrolna.sh — rejestr znalezisk INFRA ma NAGŁÓWEK „Liczba znalezisk OTWARTYCH: N”
# i wiersze ze statusem; znalezisko I-23: nagłówek kłamał (10 vs 16, potem 43 vs 35) i NIC go nie pilnowało.
# Ta próba liczy komendą z samego pliku (sekcja „POMIAR BEZPOSREDNI”) i porównuje z nagłówkiem;
# do tego dwa warunki spójności: suma statusów = liczba wierszy, zero przecięć (wiersz z dwoma statusami).
# Dwukierunkowo: na prawdziwym pliku → 0; na KOPII z podłożonym rozjazdem nagłówka → 1; z wierszem o dwóch
# statusach → 1. Bez Dockera. Liczniki klasy 138: BLEDY → 1 · KOTWICE → 2 · ODWROTNA → 2.
# Krok 4 (#296 r1 KRYT 1): DRUGI rejestr — sekcja „ŚWIADOMY DŁUG NA WYDANIE” w audyt/CO-WISI.md ma wymieniać KAŻDY kod OTWARTY
# z rejestru INFRA (klasa I-23 w drugą stronę: sekcja mówiła „~20”, pomiar dawał 30 i gubiła jedyne KRYT). Mutant C: kopia
# CO-WISI z wyciętym jednym kodem → 1. Uruchamiana z bramka-znaleziska.sh (#296 r1 WAZNA 4: przelot prób omija PR-y ruszające
# wyłącznie audyt/, bramka biegnie na każdym).
# shellcheck disable=SC2016  # odwrotne apostrofy w wzorcach grep sa CELOWO w apostrofach (to znaki tabeli, nie podstawienia)
set -u
cd "$(dirname "$0")/.." || exit 2
R=audyt/REJESTR-ZNALEZISK-INFRA.md
CW=audyt/CO-WISI.md
[ -f "$R" ] || { echo "brak $R" >&2; exit 2; }
[ -f "$CW" ] || { echo "brak $CW" >&2; exit 2; }
BLEDY=0; KOTWICE=0; ODWROTNA=0
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT

zmierz() { # plik → drukuje "naglowek otwarte zamkniete inne razem przeciecia"; kod 0 = spojny, 1 = rozjazd
	local f="$1" n o z w r p
	n=$(grep -oE '^## Liczba znalezisk OTWARTYCH: [0-9]+' "$f" | grep -oE '[0-9]+$' | head -1)
	o=$(grep -cE '^\| `[^`]+` \|.*OTWARTE' "$f"); z=$(grep -cE '^\| `[^`]+` \|.*ZAMKNI[EĘ]TE' "$f")
	w=$(grep -cE '^\| `[^`]+` \|.*(W NAPRAWIE|ZAPARKOWANE|WYCOFANE)' "$f"); r=$(grep -cE '^\| `[^`]+` \|' "$f")
	p=$(grep -E '^\| `[^`]+` \|.*OTWARTE' "$f" | grep -cE 'ZAMKNI[EĘ]TE|W NAPRAWIE|ZAPARKOWANE|WYCOFANE')
	echo "naglowek=${n:-BRAK} OTWARTE=$o ZAMKNIETE=$z inne=$w wierszy=$r przeciecia=$p"
	[ -n "$n" ] && [ "$n" -eq "$o" ] && [ $((o + z + w)) -eq "$r" ] && [ "$p" -eq 0 ]
}

echo "=== 1. PRAWDZIWY rejestr — ma byc spojny ==="
if W=$(zmierz "$R"); then echo "  OK   $W"; else echo "  ZLE  $W — rejestr NIESPOJNY (to nie jest wada proby: napraw naglowek/wiersze)"; ODWROTNA=$((ODWROTNA+1)); fi

echo "=== 2. MUTANT A: naglowek zawyzony o 1 w KOPII ==="
cp "$R" "$T/a.md"; N=$(grep -oE '^## Liczba znalezisk OTWARTYCH: [0-9]+' "$T/a.md" | grep -oE '[0-9]+$' | head -1)
if [ -z "$N" ]; then echo "  KOTWICA  brak naglowka w kopii"; KOTWICE=$((KOTWICE+1)); else
	sed -i -E "s/^(## Liczba znalezisk OTWARTYCH: )[0-9]+/\1$((N + 1))/" "$T/a.md"
	if zmierz "$T/a.md" > /dev/null; then echo "  ZLE  naglowek $((N+1)) przy $N otwartych PRZESZEDL — proba slepa"; BLEDY=$((BLEDY+1)); else echo "  OK   rozjazd naglowka ($((N+1)) vs $N) ZLAPANY"; fi
fi

echo "=== 3. MUTANT B: wiersz z DWOMA statusami (OTWARTE i ZAMKNIETE) w KOPII ==="
cp "$R" "$T/b.md"; L=$(grep -nE '^\| `[^`]+` \|.*\| OTWARTE \|' "$T/b.md" | head -1 | cut -d: -f1)
[ -z "$L" ] && L=$(grep -nE '^\| `[^`]+` \|.*OTWARTE' "$T/b.md" | head -1 | cut -d: -f1)
if [ -z "$L" ]; then echo "  KOTWICA  brak wiersza OTWARTE do zmutowania"; KOTWICE=$((KOTWICE+1)); else
	sed -i "${L}s/OTWARTE/OTWARTE (ZAMKNIETE w połowie)/" "$T/b.md"
	if cmp -s "$R" "$T/b.md"; then echo "  KOTWICA  sed nie zmienil kopii"; KOTWICE=$((KOTWICE+1));
	elif zmierz "$T/b.md" > /dev/null; then echo "  ZLE  wiersz z dwoma statusami PRZESZEDL — proba slepa"; BLEDY=$((BLEDY+1)); else echo "  OK   przeciecie statusow ZLAPANE"; fi
fi

# WSZYSTKIE sekcje „ŚWIADOMY DŁUG NA WYDANIE …” (nie tylko pierwsza): druga sekcja pod obecną, dla następnego wydania,
# też liczy się do pokrycia (#296 r2 DROBNA 1). Inny nagłówek `## ` zamyka sekcję, kolejna sekcja długu otwiera ją znowu.
sekcja_dlugu() { awk '/^## .*ŚWIADOMY DŁUG NA WYDANIE/{f=1; print; next} /^## /{f=0} f' "$1"; }
pokrycie() { # rejestr, co-wisi → drukuje brakujace kody; kod 0 = kazdy kod OTWARTY z rejestru jest w sekcji dlugu
	local r="$1" c="$2" s k brak=0
	s=$(sekcja_dlugu "$c")
	[ -n "$s" ] || { echo "BRAK SEKCJI dlugu w $c"; return 1; }
	for k in $(grep -oE '^\| `[^`]+` \|.*OTWARTE' "$r" | grep -oE '^\| `[^`]+`' | tr -d '|` '); do
		printf '%s' "$s" | grep -qF "\`$k\`" || { printf 'BRAK %s\n' "$k"; brak=$((brak+1)); }
	done
	[ "$brak" -eq 0 ]
}

echo "=== 4. DRUGI rejestr: kazdy kod OTWARTY wymieniony w sekcji dlugu CO-WISI ==="
OTW=$(grep -cE '^\| `[^`]+` \|.*OTWARTE' "$R")
if P=$(pokrycie "$R" "$CW"); then echo "  OK   $OTW kodow OTWARTYCH, kazdy w sekcji dlugu"; else echo "  ZLE  sekcja dlugu w $CW nie wymienia: $(printf '%s' "$P" | tr '\n' ' ') — dopisz albo zamknij w rejestrze"; ODWROTNA=$((ODWROTNA+1)); fi

echo "=== 5. MUTANT C: jeden kod OTWARTY wyciety z KOPII sekcji dlugu ==="
K=$(grep -oE '^\| `[^`]+` \|.*OTWARTE' "$R" | grep -oE '^\| `[^`]+`' | tr -d '|` ' | grep -vE '[*]' | head -1)
cp "$CW" "$T/c.md"
if [ -z "$K" ]; then echo "  KOTWICA  brak kodu OTWARTE bez gwiazdki"; KOTWICE=$((KOTWICE+1)); else
	sed -i "s/\`$K\`/$K/g" "$T/c.md"
	if cmp -s "$CW" "$T/c.md"; then echo "  KOTWICA  sed nie zmienil kopii ($K nie bylo w apostrofach)"; KOTWICE=$((KOTWICE+1));
	elif pokrycie "$R" "$T/c.md" > /dev/null; then echo "  ZLE  wyciety kod $K PRZESZEDL — proba slepa"; BLEDY=$((BLEDY+1)); else echo "  OK   wyciety kod $K ZLAPANY"; fi
fi

echo
if [ "$ODWROTNA" -gt 0 ]; then echo "REJESTR-LICZNIKI-PROBA: prawdziwy rejestr/CO-WISI NIESPOJNE — mutanty niewiarygodne. EXIT=2"; exit 2; fi
if [ "$KOTWICE" -gt 0 ]; then echo "REJESTR-LICZNIKI-PROBA: $KOTWICE mutacji nie weszlo. EXIT=2"; exit 2; fi
if [ "$BLEDY" -gt 0 ]; then echo "REJESTR-LICZNIKI-PROBA: SLEPA NA $BLEDY. EXIT=1"; exit 1; fi
echo "REJESTR-LICZNIKI-PROBA: rejestr spojny, CO-WISI pokrywa OTWARTE, trzy mutanty zlapane. EXIT=0"; exit 0
