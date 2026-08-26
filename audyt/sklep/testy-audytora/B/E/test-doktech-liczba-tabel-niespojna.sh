#!/usr/bin/env bash
# PADA na kodzie z commit_head=5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d.
# Powod: DOKUMENTACJA-TECHNICZNA.md w jednym miejscu (SS3, schemat bazy) mowi
# "7 tabel", a w innym (SS8, instalacja/deinstalacja) mowi "utworzenie 6 tabel"
# -- to dokladnie klasa bledu, ktora u tego recenzenta bola podwojnie
# ("nie poprawiles tego xd", PR #87 w SCIAGA-EGZAMINATORA.md).
# Test NIE niszczy stosu -- tylko czyta plik z rozpakowanej paczki (tylko-odczyt).
set -euo pipefail

DOC="/root/praca/zlecenia/wymiana/k7-runda-B/sklep-agentow/DOKUMENTACJA-TECHNICZNA.md"

if [ ! -f "$DOC" ]; then
  echo "BLAD TESTU: brak pliku $DOC" >&2
  exit 2
fi

SIEDEM=$(grep -c "7 tabel" "$DOC" || true)
SZESC=$(grep -c "6 tabel" "$DOC" || true)

if [ "$SIEDEM" -gt 0 ] && [ "$SZESC" -gt 0 ]; then
  echo "PADA: dokument jednoczesnie mowi '7 tabel' i '6 tabel' o tym samym schemacie."
  grep -n "7 tabel\|6 tabel" "$DOC"
  exit 1
fi

echo "OK: liczba tabel spojna w calym dokumencie."
exit 0
