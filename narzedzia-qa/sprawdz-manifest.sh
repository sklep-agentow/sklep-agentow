#!/bin/bash
# sprawdz-manifest.sh — strażnik strażników. Odpalany jako PIERWSZY job w CI.
#
# PO CO (werdykt 11 niezależnych krytyk planu audytów, 2026-08-16): proces eroduje —
# bramki są wyłączane "tymczasowo" pod presją i nie wracają, a CI robi się CORAZ BARDZIEJ
# zielone. Ten skrypt porównuje stan faktyczny quality.yml z MANIFESTEM i świeci na czerwono,
# gdy: (1) job z manifestu ZNIKNĄŁ, (2) ktokolwiek dodał continue-on-error,
# (3) job z manifestu dostał warunek `if:` (furtka do pomijania).
#
# Wywołanie: bash sprawdz-manifest.sh [ścieżka-repo]   (domyślnie: bieżący katalog)
# Kody wyjścia: 0 = zgodne z manifestem · 1 = naruszenie · 2 = brak plików (też blokada:
#               bramka, której nie ma, świeci zielono — dlatego brak = czerwone).

REPO="${1:-.}"
MANIFEST="$REPO/.github/manifest-bramek.txt"
WORKFLOW="$REPO/.github/workflows/quality.yml"
BLAD=0

if [ ! -f "$MANIFEST" ]; then
  echo "MANIFEST-GUARD: BRAK $MANIFEST — repo bez manifestu bramek." >&2
  exit 2
fi
if [ ! -f "$WORKFLOW" ]; then
  echo "MANIFEST-GUARD: BRAK $WORKFLOW — nie ma czego pilnować, ale manifest istnieje." >&2
  exit 2
fi

# 1) Każdy job z manifestu musi istnieć w workflow (definicja joba = "  nazwa:" na 2 spacjach
#    wewnątrz sekcji jobs; łapiemy prościej: linia zaczynająca się od 2 spacji + nazwa + ':').
while IFS= read -r JOB; do
  case "$JOB" in ''|'#'*) continue ;; esac
  if ! grep -qE "^  ${JOB}:" "$WORKFLOW"; then
    echo "MANIFEST-GUARD: job '$JOB' jest w manifescie, a NIE MA go w quality.yml — bramka zniknęła." >&2
    BLAD=1
  fi
done < "$MANIFEST"

# 2) continue-on-error gdziekolwiek w workflow = maskowanie porażek.
if grep -nE '^\s*continue-on-error:\s*true' "$WORKFLOW"; then
  echo "MANIFEST-GUARD: continue-on-error w quality.yml — bramka, która nie może zablokować, nie jest bramką." >&2
  BLAD=1
fi

# 3) Job z manifestu z warunkiem `if:` na poziomie joba = furtka do cichego pomijania.
#    (Sprawdzamy linie "    if:" bezpośrednio pod definicją joba z manifestu.)
while IFS= read -r JOB; do
  case "$JOB" in ''|'#'*) continue ;; esac
  BLOK=$(awk -v job="  ${JOB}:" 'f && /^  [a-zA-Z0-9_-]+:/{exit} $0==job{f=1} f' "$WORKFLOW")
  if printf '%s\n' "$BLOK" | grep -qE '^\s{4}if:'; then
    echo "MANIFEST-GUARD: job '$JOB' ma warunek if: — bramka warunkowa potrafi cicho nie startować." >&2
    BLAD=1
  fi
done < "$MANIFEST"

if [ "$BLAD" -eq 0 ]; then
  echo "MANIFEST-GUARD: zgodne — wszystkie bramki z manifestu aktywne, bez furtek."
fi
exit "$BLAD"
