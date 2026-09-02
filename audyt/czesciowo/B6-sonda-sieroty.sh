#!/usr/bin/env bash
# Sonda B6-F1: ile testów PHP w testy/ nie ma ŻADNEGO wołającego poza linia-bazowa.sh
# (która bierze je globem testy/*.php, więc nigdy nie wymienia nazwy). Liczy WOŁANIA nazwy pliku
# w .github/ .githooks/ narzedzia-qa/ testy/ boty/ — bez samego pliku, bez linii komentarza, bez .md
# (wzmianka w dokumencie to nie spust). Uruchamiaj z korzenia repo. Wypisuje: sieroty / z wołającym / razem.
cd "$(dirname "$0")/../.." || exit 2
sier=0; wol=0; razem=0
for t in testy/*.php; do
  b=$(basename "$t"); razem=$((razem+1))
  n=$(grep -rn -F "$b" --exclude='*.md' .github .githooks narzedzia-qa testy boty 2>/dev/null \
      | grep -v "^$t:" | grep -vE '^[^:]+:[0-9]+:[[:space:]]*(#|//|\*|/\*)' | wc -l)
  if [ "$n" = 0 ]; then sier=$((sier+1)); [ -n "$POKAZ" ] && echo "SIEROTA $b"; else wol=$((wol+1)); fi
done
echo "sieroty=$sier z_wolajacym=$wol razem=$razem"
