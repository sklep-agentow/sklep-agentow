#!/bin/bash
# waga-znaleziska.sh — WAGA Z MAPY RYZYKA: grep, nie opinia (plan audytów v3).
#
# PO CO: „waga duża" nie może być osądem modelu (pętla samopotwierdzania w miejscu twardej
# blokady) ani osądem nietechnicznego człowieka (pusty podpis). Jest DETERMINISTYCZNA:
# znalezisko dotyka pozycji z mapy ryzyka = duże. Koniec dyskusji.
#
# Wywołanie: waga-znaleziska.sh <mapa-ryzyka.txt> <ścieżka-znaleziska> [kolejne ścieżki...]
#   ścieżka-znaleziska = plik, którego dotyczy znalezisko (względna w repo), np. includes/Privacy.php
# Wyjście (stdout): "waga:duza" albo "waga:normalna" (gotowa etykieta do GitHub Issues).
# Kody: 0 = policzone · 2 = brak/pusta mapa (BLOKADA zapobiegawcza — bez mapy nie ma wag).

MAPA="$1"; shift
if [ ! -f "$MAPA" ] || ! grep -qE '^[^#[:space:]]' "$MAPA"; then
  echo "WAGA: brak mapy ryzyka albo mapa pusta ($MAPA) — najpierw mapa z dnia 1." >&2
  exit 2
fi
[ $# -ge 1 ] || { echo "WAGA: podaj ścieżkę znaleziska." >&2; exit 2; }

DUZA=0
for SCIEZKA in "$@"; do
  while IFS= read -r WZOR; do
    case "$WZOR" in ''|'#'*) continue ;; esac
    case "$SCIEZKA" in
      "$WZOR"*|*"/$WZOR"|*"/$WZOR"*) DUZA=1 ;;
    esac
  done < "$MAPA"
done

if [ "$DUZA" -eq 1 ]; then echo "waga:duza"; else echo "waga:normalna"; fi
exit 0
