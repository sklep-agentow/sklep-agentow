#!/usr/bin/env bash
# PADA na kodzie z commit_head=5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d.
# Powod: paczka klienta zawiera pliki graficzne, do ktorych nie ma ANI JEDNEGO
# odwolania w PHP/CSS/JS wtyczki -- balast w paczce klienta (klasa 5 sciagi:
# "co klient dostaje, a czego nie powinien"). Znane i zaakceptowane w
# 03-zalozenia.md ("kasowanie zatwierdzone na runde napraw") -- test i tak
# istnieje, zeby runda napraw miala czym sie potwierdzic. Tylko-odczyt.
set -euo pipefail
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"

PLUGIN_DIR="${1:-$KORZEN/wtyczki/sklep-agentow}"
cd "$PLUGIN_DIR"

NIEUZYWANE=()
for f in $(find assets -type f -printf "%f\n"); do
  case "$f" in
    *.css|*.js) continue ;;
  esac
  cnt=$(grep -rl -- "$f" includes/ sklep-agentow.php assets/*.css assets/*.js 2>/dev/null | wc -l) || true
  if [ "$cnt" -eq 0 ]; then
    NIEUZYWANE+=("$f")
  fi
done

if [ "${#NIEUZYWANE[@]}" -gt 0 ]; then
  echo "PADA: ${#NIEUZYWANE[@]} plikow medialnych bez ZADNEGO odwolania w kodzie:"
  printf ' - %s\n' "${NIEUZYWANE[@]}"
  exit 1
fi

echo "OK: kazdy plik medialny jest gdzies uzyty."
exit 0
