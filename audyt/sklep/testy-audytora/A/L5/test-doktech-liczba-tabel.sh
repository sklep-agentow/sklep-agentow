#!/usr/bin/env bash
# Test PADAJĄCY: DOKUMENTACJA-TECHNICZNA.md rozjeżdża się z SAMĄ SOBĄ (i z kodem)
# w liczbie tabel: §3 mówi "7 tabel", §8 mówi "utworzenie 6 tabel przez dbDelta".
# Kod (CREATE TABLE w aktywacja.php, potwierdzone SHOW TABLES na stosie) tworzy 7.
# PADA na obecnym dokumencie; przejdzie, gdy §8 też powie "7 tabel".
set -euo pipefail
PACZKA="${1:-/root/praca/zlecenia/wymiana/k7-runda-A/sklep-agentow}"
DOK="$PACZKA/DOKUMENTACJA-TECHNICZNA.md"

if [ ! -f "$DOK" ]; then
  echo "FAIL: nie znaleziono $DOK"
  exit 2
fi

SEKCJA3=$(grep -n "^## 3\." "$DOK" | head -1 | grep -o "7 tabel\|[0-9]\+ tabel" || true)
SEKCJA8_ZDANIE=$(grep -n "Przy aktywacji.*tabel przez" "$DOK" || true)
echo "§3 nagłówek: $(grep -n '^## 3\.' "$DOK" | head -1)"
echo "§8 zdanie: $SEKCJA8_ZDANIE"

LICZBA_CREATE=$(grep -c "CREATE TABLE" "$PACZKA/includes/aktywacja.php" || true)
echo "CREATE TABLE w aktywacja.php: $LICZBA_CREATE"

if echo "$SEKCJA8_ZDANIE" | grep -q "6 tabel"; then
  echo "FAIL: §8 mówi '6 tabel' a §3/kod ($LICZBA_CREATE CREATE TABLE) mówią 7."
  exit 1
elif echo "$SEKCJA8_ZDANIE" | grep -q "7 tabel"; then
  echo "PASS: §8 zgodne z §3 i kodem (7 tabel)."
  exit 0
else
  echo "FAIL: nie znaleziono oczekiwanego zdania w §8 — sprawdź ręcznie."
  exit 1
fi
