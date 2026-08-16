#!/bin/bash
# licznik-odstepstw.sh — BUDŻET ODSTĘPSTW (plan audytów v3, zasada dowodu).
#
# PO CO: „świadoma decyzja z podpisem" jest najtańszą ścieżką rozstrzygnięcia i bez limitu
# stanie się domyślną (werdykt krytyki: po 5 odbiciach człowiek podpisze wszystko).
# Limit: waga DUŻA = 0 odstępstw · reszta = 3 na projekt. Przekroczenie = wydanie stoi.
#
# KONWENCJA (GitHub Issues): odstępstwo = issue z etykietą `odstepstwo`;
# waga duża = dodatkowa etykieta `waga:duza` (nadawana wg MAPY RYZYKA, nie opinii).
#
# Wywołanie w CI/hooku wydania:  bash licznik-odstepstw.sh [--repo wlasciciel/repo]
# Test bez GitHuba:              ODSTEPSTWA_JSON=/sciezka/atrapa.json bash licznik-odstepstw.sh
# Kody wyjścia: 0 = w budżecie · 1 = budżet przekroczony · 2 = nie udało się policzyć
#               (brak danych NIE jest zielony — licznik, który cicho nie działa, świeci zielono).

LIMIT_DUZE=0
LIMIT_RESZTA=3

if [ -n "$ODSTEPSTWA_JSON" ]; then
  DANE=$(cat "$ODSTEPSTWA_JSON" 2>/dev/null)
else
  REPO_ARG=""
  [ "$1" = "--repo" ] && [ -n "$2" ] && REPO_ARG="--repo $2"
  DANE=$(gh issue list $REPO_ARG --label odstepstwo --state all --limit 200 \
         --json number,labels 2>/dev/null)
fi

if [ -z "$DANE" ] || ! printf '%s' "$DANE" | jq -e 'type=="array"' >/dev/null 2>&1; then
  echo "LICZNIK-ODSTEPSTW: nie udało się pobrać danych (gh/jq/atrapa) — BLOKADA zapobiegawcza." >&2
  exit 2
fi

DUZE=$(printf '%s' "$DANE" | jq '[.[] | select(.labels[]?.name=="waga:duza")] | length')
WSZYSTKIE=$(printf '%s' "$DANE" | jq 'length')
RESZTA=$((WSZYSTKIE - DUZE))

echo "LICZNIK-ODSTEPSTW: duże=$DUZE (limit $LIMIT_DUZE) · pozostałe=$RESZTA (limit $LIMIT_RESZTA)"

BLAD=0
if [ "$DUZE" -gt "$LIMIT_DUZE" ]; then
  echo "  ⛔ PRZEKROCZONY budżet odstępstw wagi DUŻEJ ($DUZE > $LIMIT_DUZE) — wydanie stoi." >&2
  echo "     Odstępstwo od rzeczy z mapy ryzyka nie istnieje: naprawa albo koniec." >&2
  BLAD=1
fi
if [ "$RESZTA" -gt "$LIMIT_RESZTA" ]; then
  echo "  ⛔ PRZEKROCZONY budżet odstępstw pozostałych ($RESZTA > $LIMIT_RESZTA) — wydanie stoi." >&2
  echo "     Za dużo 'świadomych decyzji' = proces przestał decydować. Naprawić najstarsze." >&2
  BLAD=1
fi
exit "$BLAD"
