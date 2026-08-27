#!/usr/bin/env bash
# Lighthouse dla 3 podstron sklepu × desktop/telefon — 4 kategorie (Performance, Accessibility,
# Best Practices, SEO). Wynik: tabela na stdout + JSON per przebieg w katalogu wyjściowym.
#
# UWAGA o SEO na stosie dev: opcja WordPressa „Zniechęcaj wyszukiwarki" (`blog_public=0`) wstawia
# `noindex` i Lighthouse tnie SEO do 58–66 (kontrola `is-crawlable`). Żeby zmierzyć SEO kodu, na czas
# pomiaru włącz indeksowanie (`wp option update blog_public 1`) i PRZYWRÓĆ 0 po pomiarze.
#
# Wymaga: lighthouse (npm -g), Chromium (rejestr Playwrighta przez testy/lib/chromium.js), python3.
# Użycie: bash testy/e2e/lighthouse-3-podstrony.sh [adres-bazy] [katalog-wyjściowy] [próg=95]
# EXIT: 0 = wszystkie 4 kategorie >= próg na wszystkich stronach.
#       N > 0 = tyle PRZEBIEGOW sie nie powiodlo, z DWOCH roznych powodow: wynik ponizej progu
#       ALBO lighthouse w ogole nie wstal. Ktory to przypadek, mowi wyjscie: przebieg ponizej
#       progu drukuje wiersz z dopiskiem PONIZEJ <prog>, a padniecie narzedzia wiersz
#       zaczynajacy sie od "! lighthouse ... padl" i wskazuje plik .err.
set -u
BAZA="${1:-http://localhost:8890}"
OUT="${2:-/tmp/lighthouse-sklep}"
PROG="${3:-95}"   # prog celu L6-01.
# ⛔ WYDAJNOSC (perf) na tej maszynie JEST SZUMNA i pojedynczy przebieg nie jest wyrocznia.
# Zmierzone 27.08: dwa pelne przebiegi, DWIE ROZNE komorki ponizej progu (segregator/mobile 93,
# potem sklep/mobile 87), a powtorki tych samych komorek dawaly 97-99. Serwer ma 2 vCPU, a tryb
# telefonu dodatkowo spowalnia procesor 4x, wiec wynik zalezy od chwilowego obciazenia.
# ⭐ METODA: komorke ponizej progu POWTORZ 3x i wez MEDIANE. Progu NIE obnizaj.
# Pozostale kategorie (a11y, bp, seo) sa stabilne — tam pojedynczy przebieg wystarcza.
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
    # PROG: cel L6-01 mapy wymaga >= 95 w KAZDEJ z 4 kategorii. Bez tego skrypt liczyl
    # wylacznie, czy lighthouse wstal (klasa 11/12 ze SCIAGA-EGZAMINATORA: bramka o zakresie
    # wezszym niz nazwa / zero jako sukces) — wynik 93 przechodzil jako zielony.
    if ! python3 - "$plik" "$nazwa" "$tryb" "$PROG" <<'PY'
import json, sys
d = json.load(open(sys.argv[1])); c = d['categories']
prog = int(sys.argv[4])
s = lambda k: round((c[k]['score'] or 0) * 100)
w = [s('performance'), s('accessibility'), s('best-practices'), s('seo')]
ponizej = [n for n, v in zip(('perf', 'a11y', 'bp', 'seo'), w) if v < prog]
ogon = ('   PONIZEJ %d: %s' % (prog, ','.join(ponizej))) if ponizej else ''
print('%-11s %-8s %5d %5d %5d %5d%s' % (sys.argv[2], sys.argv[3], w[0], w[1], w[2], w[3], ogon))
sys.exit(1 if ponizej else 0)
PY
    then bledy=$((bledy+1)); fi
  done
done
echo "JSON: $OUT"
exit $bledy
