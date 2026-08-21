#!/bin/bash
# sprawdz-manifest.sh — strażnik strażników. Odpalany jako PIERWSZY job w CI.
#
# PO CO (werdykt 11 niezależnych krytyk planu audytów, 2026-08-16): proces eroduje —
# bramki są wyłączane "tymczasowo" pod presją i nie wracają, a CI robi się CORAZ BARDZIEJ
# zielone.
#
# 2026-08-20: bramki przestały być osobnymi JOBAMI (sześć zadań = sześć naliczonych
# minut za czterdzieści sekund liczenia) i biegną po kolei w narzedzia-qa/bramki.sh.
# Manifest ZOSTAJE NIETKNIĘTY — nadal wymienia te same sześć bramek. Zmienia się to,
# GDZIE ich szukamy: w rejestrze bramek skryptu zamiast w nazwach jobów.
#
# Skrypt świeci na czerwono, gdy: (1) bramka z manifestu zniknęła z rejestru w bramki.sh,
# (2) bramka jest w rejestrze, ale nikt jej nie woła, (3) próg pełnego przebiegu jest
# niższy niż liczba bramek w manifeście (ciche obniżenie poprzeczki), (4) quality.yml
# przestał wołać bramki.sh, (5) ktokolwiek dodał continue-on-error,
# (6) zadanie albo krok z bramkami dostał warunek `if:` (furtka do pomijania).
#
# Wywołanie: bash sprawdz-manifest.sh [ścieżka-repo]   (domyślnie: bieżący katalog)
# Kody wyjścia: 0 = zgodne z manifestem · 1 = naruszenie · 2 = brak plików (też blokada:
#               bramka, której nie ma, świeci zielono — dlatego brak = czerwone).

REPO="${1:-.}"
MANIFEST="$REPO/.github/manifest-bramek.txt"
WORKFLOW="$REPO/.github/workflows/quality.yml"
BRAMKI="$REPO/narzedzia-qa/bramki.sh"
BLAD=0

if [ ! -f "$MANIFEST" ]; then
  echo "MANIFEST-GUARD: BRAK $MANIFEST — repo bez manifestu bramek." >&2
  exit 2
fi
if [ ! -f "$WORKFLOW" ]; then
  echo "MANIFEST-GUARD: BRAK $WORKFLOW — nie ma czego pilnować, ale manifest istnieje." >&2
  exit 2
fi
if [ ! -f "$BRAMKI" ]; then
  echo "MANIFEST-GUARD: BRAK $BRAMKI — bramki nie maja gdzie sie wykonywac." >&2
  exit 2
fi

# 1) Każda bramka z manifestu musi być W REJESTRZE skryptu ORAZ realnie wołana.
#    Sam wpis w rejestrze nie wystarcza: tablica, po której nikt nie iteruje, to
#    deklaracja, nie kontrola.
REJESTR=$(grep -oP '^BRAMKI=\(\K[^)]*' "$BRAMKI")
LICZBA_MANIFESTU=0
while IFS= read -r BRAMKA; do
  case "$BRAMKA" in ''|'#'*) continue ;; esac
  LICZBA_MANIFESTU=$((LICZBA_MANIFESTU + 1))
  if ! printf '%s\n' $REJESTR | grep -qx "$BRAMKA"; then
    echo "MANIFEST-GUARD: bramka '$BRAMKA' jest w manifescie, a NIE MA jej w rejestrze BRAMKI=() w bramki.sh — bramka zniknęła." >&2
    BLAD=1
  fi
  if ! grep -qE "^bramka $BRAMKA[[:space:]]" "$BRAMKI"; then
    echo "MANIFEST-GUARD: bramka '$BRAMKA' jest w rejestrze, ale NIKT JEJ NIE WOŁA w bramki.sh." >&2
    BLAD=1
  fi
done < "$MANIFEST"

# 1b) Próg pełnego przebiegu nie może być niższy niż liczba bramek z manifestu —
#     inaczej dałoby się po cichu obniżyć poprzeczkę zamiast usuwać bramkę.
PROG=$(grep -oP '^PROG_PELNY=\K[0-9]+' "$BRAMKI")
if [ -z "$PROG" ]; then
  echo "MANIFEST-GUARD: nie znalazłem PROG_PELNY w bramki.sh — nie ma czego pilnować." >&2
  BLAD=1
elif [ "$PROG" -lt "$LICZBA_MANIFESTU" ]; then
  echo "MANIFEST-GUARD: PROG_PELNY=$PROG, a manifest wymienia $LICZBA_MANIFESTU bramek — poprzeczka obniżona." >&2
  BLAD=1
fi

# 1c) quality.yml musi faktycznie WOŁAĆ skrypt z bramkami — w kroku `run:`,
#     nie we wzmiance w komentarzu. Pierwsza wersja tej kontroli łapała własny
#     komentarz nagłówka i przepuszczała workflow, który skryptu nie uruchamiał
#     (złapane przez narzedzia-qa/bramki-proba-kontrolna.sh).
if ! grep -qE '^[[:space:]]*run:.*narzedzia-qa/bramki\.sh' "$WORKFLOW"; then
  echo "MANIFEST-GUARD: quality.yml NIE WOŁA narzedzia-qa/bramki.sh — bramki nie wykonują się w CI." >&2
  BLAD=1
fi

# 2) continue-on-error gdziekolwiek w workflow = maskowanie porażek.
if grep -nE '^\s*continue-on-error:\s*true' "$WORKFLOW"; then
  echo "MANIFEST-GUARD: continue-on-error w quality.yml — bramka, która nie może zablokować, nie jest bramką." >&2
  BLAD=1
fi

# 3) Warunek `if:` na zadaniu z bramkami albo na kroku, który je odpala = furtka
#    do cichego pomijania. Pomijanie bramek PHP przy zmianach tekstowych dzieje się
#    WEWNĄTRZ skryptu, jest wypisywane i policzone — a to co innego niż krok,
#    który po prostu nie startuje.
if grep -qE '^\s{4}if:' "$WORKFLOW"; then
  echo "MANIFEST-GUARD: zadanie w quality.yml ma warunek if: — bramka warunkowa potrafi cicho nie startować." >&2
  BLAD=1
fi
KROK=$(awk '/name: Bramki jakosci/,0' "$WORKFLOW")
if printf '%s\n' "$KROK" | grep -qE '^\s*if:'; then
  echo "MANIFEST-GUARD: krok z bramkami ma warunek if: — mógłby cicho nie wystartować." >&2
  BLAD=1
fi

# 4) Rozjazd wersji WEWNĄTRZ jednego skryptu: komentarz nagłówka mówi co innego niż
#    stała WERSJA w kodzie. Klasa #4 ze ściągi egzaminatora („wersja rozjechana"),
#    złapana w recenzji #105 na dowod-z-paczki.sh (nagłówek 1.0.0, stała 1.0.1 po
#    refaktorze). Egzemplarz naprawiony ręcznie; TU zamykamy klasę, żeby następny
#    refaktor nie przywiózł jej z powrotem. Skrypty bez stałej WERSJA (biblioteki,
#    próby kontrolne) pomijamy — nie mają czego rozjechać.
for SKRYPT in "$(dirname "$0")"/*.sh; do
  NAGLOWEK="$(grep -m1 -oE 'WERSJA [0-9]+\.[0-9]+\.[0-9]+' "$SKRYPT" | awk '{print $2}')"
  STALA="$(grep -m1 -oE '^WERSJA=[0-9]+\.[0-9]+\.[0-9]+' "$SKRYPT" | cut -d= -f2)"
  if [ -n "$NAGLOWEK" ] && [ -n "$STALA" ] && [ "$NAGLOWEK" != "$STALA" ]; then
    echo "MANIFEST-GUARD: $(basename "$SKRYPT") — naglowek mowi WERSJA $NAGLOWEK, stala mowi $STALA. Wersja rozjechana w jednym pliku." >&2
    BLAD=1
  fi
done

if [ "$BLAD" -eq 0 ]; then
  echo "MANIFEST-GUARD: zgodne — wszystkie bramki z manifestu aktywne, bez furtek."
fi
exit "$BLAD"
