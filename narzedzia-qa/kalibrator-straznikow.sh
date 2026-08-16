#!/bin/bash
# kalibrator-straznikow.sh — MUTANT TESTUJE TESTY (rdzeń planu audytów v3).
#
# PO CO: kalibracja „audyt znalazł mutanta" była egzaminem, który uczeń sam układa i ocenia
# (werdykt krytyki 16.08). Ta maszyna pyta MASZYNOWO: czy po wstrzyknięciu znanego błędu
# zestaw testów robi się CZERWONY? Zielone CI na mutancie = klasa NIEOBSTAWIONA = dopisz
# strażnika. Model nie bierze udziału w ocenie.
#
# Wywołanie: kalibrator-straznikow.sh <katalog-repo> <plik-mutanta.patch> <komenda testowa...>
#   np.: kalibrator-straznikow.sh . mutanty/c-token-2x.patch bash testy/uruchom-wszystkie.sh
# Kody wyjścia: 0 = strażnik ZŁAPAŁ mutanta (czerwone — dobrze) ·
#               1 = DZIURA: testy zielone na mutancie ·
#               2 = błąd rozruchu (brudne drzewo, zły patch itp.) — NIE zielony.
# BEZPIECZEŃSTWO: wymaga czystego drzewa gita; mutant zawsze wycofywany (też przy przerwaniu).

REPO="$1"; PATCH="$2"; shift 2
[ -d "$REPO/.git" ] || { echo "KALIBRATOR: $REPO to nie repo gita." >&2; exit 2; }
[ -f "$PATCH" ] || { echo "KALIBRATOR: brak pliku mutanta $PATCH." >&2; exit 2; }
[ $# -ge 1 ] || { echo "KALIBRATOR: podaj komendę testową." >&2; exit 2; }
PATCH=$(realpath "$PATCH")
cd "$REPO" || exit 2

if [ -n "$(git status --porcelain -uno)" ]; then
  echo "KALIBRATOR: drzewo robocze BRUDNE — nie wstrzykuję mutanta w niezacommitowane zmiany." >&2
  exit 2
fi

WYCOFAJ() { git apply -R "$PATCH" 2>/dev/null; }
trap WYCOFAJ EXIT INT TERM

if ! git apply "$PATCH" 2>/dev/null; then
  trap - EXIT INT TERM
  echo "KALIBRATOR: mutant nie pasuje do kodu (git apply padł) — mutant przeterminowany?" >&2
  exit 2
fi

"$@" >/dev/null 2>&1
WYNIK_TESTOW=$?

WYCOFAJ
trap - EXIT INT TERM
if [ -n "$(git status --porcelain -uno)" ]; then
  echo "KALIBRATOR: ⛔ wycofanie mutanta NIE przywróciło czystego drzewa — sprawdź ręcznie!" >&2
  exit 2
fi

if [ "$WYNIK_TESTOW" -eq 0 ]; then
  echo "KALIBRATOR: 🔴 DZIURA — testy ZIELONE na mutancie $(basename "$PATCH"). Klasa nieobstawiona: dopisz strażnika."
  exit 1
else
  echo "KALIBRATOR: ✅ strażnik złapał mutanta $(basename "$PATCH") (testy czerwone, jak trzeba)."
  exit 0
fi
