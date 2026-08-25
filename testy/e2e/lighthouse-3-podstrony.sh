#!/usr/bin/env bash
# Lighthouse dla 3 podstron sklepu × desktop/telefon — 4 kategorie (Performance, Accessibility,
# Best Practices, SEO). Wynik: tabela na stdout + JSON per przebieg w katalogu wyjściowym.
#
# UWAGA o SEO na stosie dev: opcja WordPressa „Zniechęcaj wyszukiwarki" (`blog_public=0`) wstawia
# `noindex` i Lighthouse tnie SEO do 58–66 (kontrola `is-crawlable`). Żeby zmierzyć SEO kodu, na czas
# pomiaru włącz indeksowanie (`wp option update blog_public 1`) i PRZYWRÓĆ 0 po pomiarze.
#
# Wymaga: lighthouse (npm -g), Chromium (rejestr Playwrighta przez testy/lib/chromium.js), python3.
# Użycie: bash testy/e2e/lighthouse-3-podstrony.sh [adres-bazy] [katalog-wyjściowy]
set -u
BAZA="${1:-http://localhost:8890}"
OUT="${2:-/tmp/lighthouse-sklep}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
mkdir -p "$OUT"
CHROME_PATH="$(node "$REPO/testy/lib/chromium.js")" || { echo "brak chromium"; exit 2; }
export CHROME_PATH
command -v lighthouse >/dev/null || { echo "brak lighthouse (npm install -g lighthouse)"; exit 2; }

STRONY="sklep:/sklep/ chatbot:/sklep/produkt/chatbot-live/ segregator:/sklep/produkt/segregator-maila/"
printf '%-11s %-8s %5s %5s %5s %5s\n' strona tryb perf a11y bp seo
bledy=0
for wpis in $STRONY; do
  nazwa="${wpis%%:*}"; sciezka="${wpis#*:}"
  for tryb in desktop mobile; do
    if [ "$tryb" = desktop ]; then PRESET="--preset=desktop"; else PRESET=""; fi
    plik="$OUT/$nazwa-$tryb.json"
    # shellcheck disable=SC2086
    if ! lighthouse "$BAZA$sciezka" $PRESET --quiet --output=json --output-path="$plik" \
      --only-categories=performance,accessibility,best-practices,seo \
      --chrome-flags="--headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage" >/dev/null 2>"$OUT/$nazwa-$tryb.err"; then
      echo "  ! lighthouse $nazwa $tryb padł (patrz $OUT/$nazwa-$tryb.err)"; bledy=$((bledy+1)); continue
    fi
    python3 - "$plik" "$nazwa" "$tryb" <<'PY'
import json, sys
d = json.load(open(sys.argv[1])); c = d['categories']
s = lambda k: round((c[k]['score'] or 0) * 100)
print('%-11s %-8s %5d %5d %5d %5d' % (sys.argv[2], sys.argv[3], s('performance'), s('accessibility'), s('best-practices'), s('seo')))
PY
  done
done
echo "JSON: $OUT"
exit $bledy
