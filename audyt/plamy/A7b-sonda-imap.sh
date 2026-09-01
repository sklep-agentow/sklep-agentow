#!/usr/bin/env bash
# Przelot sondy: dla kazdego scenariusza wstaje atrapa, potem sonda.
# Limit pamieci celowo NISKI (64M), zeby ewentualny brak ograniczenia
# objawil sie szybko i BEZPIECZNIE, a nie zabral RAM maszyny.
S="$(cd "$(dirname "$0")" && pwd)"
PORT=${PORT:-14300}
for scen in poprawny literal-sama-deklaracja literal-ogromny literal-obciety bez-crlf bez-znacznika linia-ogromna; do
  PORT=$((PORT+1))
  php "$S/A7b-sonda-imap-atrapa.php" "$PORT" "$scen" >/dev/null 2>&1 &
  ATR=$!
  sleep 0.4
  timeout 40 php -d memory_limit=64M "$S/A7b-sonda-imap.php" "$PORT" "$scen" 2>&1 | sed "s/^/  /"
  echo "  KOD_WYJSCIA_SONDY=${PIPESTATUS[0]}"
  kill $ATR 2>/dev/null; wait $ATR 2>/dev/null
  echo "  ---"
done
