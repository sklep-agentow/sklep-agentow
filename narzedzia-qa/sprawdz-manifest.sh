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
# (1a) bramka biegnie w bramki.sh, ale nie ma jej w manifeście (spis został w tyle),
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

# 0) PODŁOGA BEZPIECZEŃSTWA — bramki, których NIE WOLNO usunąć nawet PORZĄDNIE.
#    POWÓD (polowanie 31.08, znalezisko B-2): kontrole 1–6 pilnują WZAJEMNEJ ZGODNOŚCI
#    trzech miejsc (manifest, rejestr BRAMKI=(), próg). Łapią więc ciche pominięcie
#    bramki — ale przepuszczają jej JAWNE, spójne wykreślenie z wszystkich trzech naraz.
#    Odtworzone: usunięcie `gitleaks` z rejestru + próg 10→9 + wiersz z manifestu
#    => "MANIFEST-GUARD: zgodne", kod 0. Paczka mogła wyjść bez skanu sekretów przy
#    zielonych kontrolach.
#    ⛔ Ta lista jest ostatnią rzeczą, która nie wynika z innych plików. Usunięcie
#    bramki stąd wymaga edycji SAMEGO STRAŻNIKA — czyli widocznej zmiany w PR-ze,
#    obstawionej dodatkowo próbą kontrolną (sprawdz-manifest-proba-kontrolna).
#    To NIE jest ochrona przed kimś złośliwym z prawem zapisu (tej bez ochrony gałęzi
#    nie da się zbudować) — to ochrona przed CICHĄ EROZJĄ i pomyłką pod presją.
PODLOGA=(gitleaks poswiadczenia slady)
for OBOWIAZKOWA in "${PODLOGA[@]}"; do
  if ! grep -qE "^${OBOWIAZKOWA}$" "$MANIFEST"; then
    echo "MANIFEST-GUARD: bramka '$OBOWIAZKOWA' to PODŁOGA BEZPIECZEŃSTWA i musi być w manifeście — nie ma jej." >&2
    BLAD=1
  fi
  if ! grep -qE "^BRAMKI=\([^)]*\b${OBOWIAZKOWA}\b" "$BRAMKI"; then
    echo "MANIFEST-GUARD: bramka '$OBOWIAZKOWA' to PODŁOGA BEZPIECZEŃSTWA i musi być w rejestrze BRAMKI=() — nie ma jej." >&2
    BLAD=1
  fi
done

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

# 1a) KIERUNEK ODWROTNY: bramka, która jest w rejestrze i realnie biegnie, musi być też
#     w manifeście. Bez tego manifest da się cicho zostawić w tyle — i tak się stało DWA razy
#     w ciągu doby (`poswiadczenia` w #193, `znaleziska` w #198). Manifest jest spisem tego,
#     co MUSI biec; bramka poza spisem to bramka, którą wolno usunąć bez śladu.
while IFS= read -r W_REJESTRZE; do
  [ -n "$W_REJESTRZE" ] || continue
  if ! grep -vE '^\s*#' "$MANIFEST" | grep -qx "$W_REJESTRZE"; then
    echo "MANIFEST-GUARD: bramka '$W_REJESTRZE' biegnie w bramki.sh, ale NIE MA jej w manifescie — spis został w tyle." >&2
    BLAD=1
  fi
done < <(printf '%s\n' $REJESTR)

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

# 1b2) Sufit NIEZMIERZONYCH: musi być LITERAŁEM równym zeru, nie odczytem ze środowiska.
#      Powód zmierzony (recenzja #272, runda 11): zapis `MAX_NIEZMIERZONYCH="${MAX_NIEZMIERZONYCH:-0}"`
#      pozwalał podnieść sufit prefiksem przed komendą — `MAX_NIEZMIERZONYCH=2 bash narzedzia-qa/bramki.sh`
#      kończyło się „WSZYSTKIE WYKONANE BRAMKI ZIELONE" przy dwóch bramkach, które nic nie zmierzyły,
#      i nie zostawiało po sobie ani jednego bajtu w repozytorium. Precedens, na który powoływał się
#      komentarz — `MAX_POMINIETYCH` w proby-kontrolne-przelot.sh:69 — jest literałem właśnie dlatego.
#      ⛔ Tę kontrolę dodano, bo sufit był JEDYNYM progiem w bramki.sh bez własnego strażnika:
#      PROG_PELNY pilnuje 1b, a sufitu nie pilnowało NIC (grep po całym repo: same wystąpienia
#      w bramki.sh i jedno zdanie w CHANGELOG-u).
SUFIT_LINIA=$(grep -nE '^MAX_NIEZMIERZONYCH=' "$BRAMKI" | head -1)
if [ -z "$SUFIT_LINIA" ]; then
  echo "MANIFEST-GUARD: nie znalazłem MAX_NIEZMIERZONYCH w bramki.sh — sufit niezmierzonych zniknął." >&2
  BLAD=1
elif printf '%s' "$SUFIT_LINIA" | grep -q '\${'; then
  echo "MANIFEST-GUARD: MAX_NIEZMIERZONYCH czyta ze ŚRODOWISKA ($SUFIT_LINIA) — sufit da się podnieść" >&2
  echo "                prefiksem przed komendą, bez commita i bez śladu w diffie. Ma być literałem." >&2
  BLAD=1
else
  SUFIT=$(printf '%s' "$SUFIT_LINIA" | grep -oE '[0-9]+$')
  # ⛔ TWARDE ZERO, bez furtki „wystarczy wspomnieć w CHANGELOG-u”. Pierwsza wersja tej kontroli
  #    przepuszczała podniesiony sufit, jeśli nazwa `MAX_NIEZMIERZONYCH` padała gdziekolwiek
  #    w CHANGELOG-u — a padała, bo dopisał ją commit wprowadzający sam sufit. Raz wpisane słowo
  #    otwierałoby drogę na zawsze. Złapane własną kalibracją: mutant „sufit=3” przeszedł na zielono.
  #    Podniesienie sufitu jest możliwe, ale wymaga zmiany DWÓCH miejsc — literału w bramki.sh
  #    ORAZ tej kontroli — i oba są widoczne w diffie.
  if [ -z "$SUFIT" ] || [ "$SUFIT" -ne 0 ]; then
    echo "MANIFEST-GUARD: MAX_NIEZMIERZONYCH=$SUFIT — sufit podniesiony ponad zero." >&2
    echo "                Bramka, która nic nie zmierzyła, nie może uchodzić za zieloną." >&2
    BLAD=1
  fi
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
