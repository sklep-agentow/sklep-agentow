#!/usr/bin/env bash
# PADA na stanie repo w commit_head=5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d.
# Powod: D27 (DECYZJE.md) zapowiada plik PONAD-SPECYFIKACJE.md dla rozszerzen
# ponad litere zamowienia (SEO itp.) -- dla botow taki plik istnieje
# (boty/PONAD-SPECYFIKACJE-BOTY.md), dla SKLEPU nie ma odpowiednika.
# Test tylko czyta repo (grep po dokumentacja/ i wtyczki/ -- dozwolone), zero zmian.
set -euo pipefail

REPO="/root/praca/zlecenia/sklep-agentow"

if find "$REPO" -iname "PONAD-SPECYFIKACJE.md" -o -iname "PONAD-SPECYFIKACJE-SKLEP.md" | grep -q .; then
  echo "OK: plik PONAD-SPECYFIKACJE dla sklepu istnieje."
  exit 0
fi

echo "PADA: D27 zapowiada PONAD-SPECYFIKACJE.md dla rozszerzen sklepu (SEO i inne),"
echo "plik dla botow istnieje (boty/PONAD-SPECYFIKACJE-BOTY.md), dla sklepu -- brak."
exit 1
