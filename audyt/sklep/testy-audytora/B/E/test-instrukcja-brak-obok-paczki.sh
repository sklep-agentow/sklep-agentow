#!/usr/bin/env bash
# PADA na kodzie z commit_head=5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (paczka sklep-agentow-0.9.13.zip).
# Powód: readme.txt (sekcja "Installation", ostatni akapit) obiecuje:
#   "Pełna instrukcja krok po kroku ... jest dołączona do wydania obok paczek
#    (plik INSTRUKCJA-INSTALACJI.md)"
# ale w katalogu wydania (obok paczki, tam gdzie klient faktycznie ją znajdzie)
# tego pliku nie ma. Test NIE niszczy stosu — tylko czyta pliki, tylko-odczyt.
set -euo pipefail
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"

PACZKA_DIR="${1:-$KORZEN/boty/paczki}"
PACZKA_ZIP="$PACZKA_DIR/sklep-agentow-0.9.13.zip"
INSTRUKCJA_OBOK="$PACZKA_DIR/INSTRUKCJA-INSTALACJI.md"

if [ ! -f "$PACZKA_ZIP" ]; then
  echo "BLAD TESTU: nie ma nawet paczki $PACZKA_ZIP — sprawdz sciezke." >&2
  exit 2
fi

if [ ! -f "$INSTRUKCJA_OBOK" ]; then
  echo "PADA: readme.txt obiecuje plik INSTRUKCJA-INSTALACJI.md 'obok paczek'"
  echo "w katalogu wydania ($PACZKA_DIR), a pliku tam nie ma."
  echo "Klient, ktory dostaje TYLKO zip, nie ma pelnej instrukcji krok po kroku."
  exit 1
fi

echo "OK: instrukcja jest obok paczki."
exit 0
