#!/bin/bash
# pakuj-slady-proba-kontrolna.sh — czy pakowanie paczki klienta sprawdza ślady w DOKUMENTACH paczki
# KANONICZNYM skanerem (slady-wewnetrzne.sh, 5 klas), a nie własnym grepem (znalezisko Q-3: własny wzorzec
# z 9 alternatyw przepuszczał to, co skaner łapie — imię właściciela, numer PR-a, „recenzent”, „koordynator”).
# Dwa pomiary, bez Dockera, bez budowania paczki:
#   (1) WPIĘCIE: pakuj-dla-klienta.sh woła slady-wewnetrzne.sh z etykietą `paczka-klienta` (strażnik niewpięty = brak strażnika);
#   (2) SKUTEK: skaner z tą etykietą na kopii dokumentów paczki — czysta → EXIT 0; z podłożonym śladem z klasy,
#       której stary grep NIE znał („koordynatorka”, „PR #123”) → EXIT 1.
# Liczniki (klasa 138): BLEDY (ślepy) → 1 · KOTWICE (mutacja nie weszła / brak plików) → 2 · ODWROTNA (czysto pada) → 2.
set -u
cd "$(dirname "$0")/.." || exit 2
PAKUJ=narzedzia-qa/pakuj-dla-klienta.sh
SKANER=narzedzia-qa/slady-wewnetrzne.sh
for f in "$PAKUJ" "$SKANER" dla-klienta/PRZECZYTAJ-MNIE.txt dokumentacja/INSTRUKCJA-INSTALACJI.md; do
	[ -f "$f" ] || { echo "brak $f — nie zmierzono" >&2; exit 2; }
done
BLEDY=0; KOTWICE=0; ODWROTNA=0
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT

echo "=== 1. WPIECIE: pakowanie wola kanoniczny skaner na dokumentach paczki ==="
if grep -qF "slady-wewnetrzne.sh" "$PAKUJ" && grep -qF "paczka-klienta" "$PAKUJ"; then
	echo "  OK   $PAKUJ wola slady-wewnetrzne.sh z etykieta paczka-klienta"
else
	echo "  ZLE  $PAKUJ NIE wola kanonicznego skanera (wlasny grep = Q-3 wraca)"; BLEDY=$((BLEDY+1))
fi
if grep -qE "grep -lE 'localhost:\[0-9\]" "$PAKUJ"; then
	echo "  ZLE  stary wlasny wzorzec nadal w $PAKUJ (dwa zrodla prawdy o sladach)"; BLEDY=$((BLEDY+1))
else
	echo "  OK   stary wlasny wzorzec usuniety — jedno zrodlo wzorcow"
fi

echo "=== 2. SKUTEK: skaner na kopii dokumentow paczki ==="
mkdir -p "$T/czysta" "$T/wada"
cp dla-klienta/PRZECZYTAJ-MNIE.txt dokumentacja/INSTRUKCJA-INSTALACJI.md "$T/czysta/"
bash "$SKANER" paczka-klienta "$T/czysta" > "$T/czysta.txt" 2>&1; k=$?
if [ "$k" -eq 0 ]; then echo "  OK   czyste dokumenty paczki przechodza (EXIT=0)"; else echo "  ZLE  czyste dokumenty NIE przechodza (EXIT=$k) — kontrola odwrotna padla"; tail -3 "$T/czysta.txt" | sed 's/^/         /'; ODWROTNA=$((ODWROTNA+1)); fi
cp "$T/czysta/"* "$T/wada/"
printf '\nUwaga od koordynatorki po recenzji PR #123: ten akapit dopisal recenzent.\n' >> "$T/wada/PRZECZYTAJ-MNIE.txt"
if cmp -s "$T/czysta/PRZECZYTAJ-MNIE.txt" "$T/wada/PRZECZYTAJ-MNIE.txt"; then echo "  KOTWICA  wada nie weszla do kopii"; KOTWICE=$((KOTWICE+1)); fi
bash "$SKANER" paczka-klienta "$T/wada" > "$T/wada.txt" 2>&1; k=$?
if [ "$k" -eq 1 ]; then echo "  OK   podlozony slad (koordynatorka, PR #123, recenzent) ZLAPANY (EXIT=1)"; grep -m2 'SLAD' "$T/wada.txt" | sed 's/^/         /'
elif [ "$k" -eq 2 ]; then echo "  KOTWICA  skaner nie zmierzyl (EXIT=2)"; KOTWICE=$((KOTWICE+1))
else echo "  ZLE  podlozony slad PRZESZEDL (EXIT=$k) — skaner slepy na klase, dla ktorej powstal Q-3"; BLEDY=$((BLEDY+1)); fi

echo
if [ "$ODWROTNA" -gt 0 ]; then echo "PAKUJ-SLADY-PROBA: kontrola odwrotna PADLA — BLEDY=$BLEDY NIEWIARYGODNE. EXIT=2"; exit 2; fi
if [ "$KOTWICE" -gt 0 ]; then echo "PAKUJ-SLADY-PROBA: $KOTWICE pomiar(y) NIE WESZLY. EXIT=2"; exit 2; fi
if [ "$BLEDY" -gt 0 ]; then echo "PAKUJ-SLADY-PROBA: STRAZNIK SLEPY NA $BLEDY. EXIT=1"; exit 1; fi
echo "PAKUJ-SLADY-PROBA: wpiecie jest, czysto 0, slad zlapany. EXIT=0"; exit 0
