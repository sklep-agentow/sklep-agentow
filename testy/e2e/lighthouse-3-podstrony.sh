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
PROG="${3:-95}"      # prog celu L6-01.
POWTORKI="${4:-1}"   # ile razy zmierzyc komorke, ktora wypadla PONIZEJ progu (mediana rozstrzyga).
                     # 1 = zachowanie jak dotad (jeden przebieg = werdykt).
                     # ⛔ Uzywaj wartosci NIEPARZYSTEJ i co najmniej 3: mediana z dwoch przebiegow
                     # to ich srednia, wiec jeden szumny wynik nadal ja psuje (zmierzone:
                     # 100 i 87 -> mediana 94, czyli ponizej progu, choc 2 z 3 pomiarow byly >=99).
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
zmierz() {  # $1=url $2=preset $3=plik-wyjsciowy $4=plik-bledu ; 0 = zmierzone, 2 = lighthouse nie wstal
  # shellcheck disable=SC2086
  lighthouse "$1" $2 --quiet --output=json --output-path="$3" \
    --only-categories=performance,accessibility,best-practices,seo \
    --chrome-flags="--headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage" >/dev/null 2>"$4" || return 2
  return 0
}

for wpis in $STRONY; do
  nazwa="${wpis%%:*}"; sciezka="${wpis#*:}"
  for tryb in desktop mobile; do
    if [ "$tryb" = desktop ]; then PRESET="--preset=desktop"; else PRESET=""; fi
    plik="$OUT/$nazwa-$tryb.json"
    if ! zmierz "$BAZA$sciezka" "$PRESET" "$plik" "$OUT/$nazwa-$tryb.err"; then
      echo "  ! lighthouse $nazwa $tryb padł (patrz $OUT/$nazwa-$tryb.err)"; bledy=$((bledy+1)); continue
    fi
    PLIKI="$plik"
    # Komorke PONIZEJ progu powtarzamy i orzekamy z MEDIANY — wydajnosc na tej maszynie jest
    # szumna (2 vCPU, tryb telefonu spowalnia procesor 4x), wiec pojedynczy przebieg nie jest
    # werdyktem. Metoda MUSI byc tutaj, nie w komentarzu: notatka bez wyzwalacza w narzedziu
    # wraca (SCIAGA-EGZAMINATORA, meta-lekcja o klasie 10). Prog to NIE rusza.
    if [ "$POWTORKI" -gt 1 ] && ! python3 "$REPO/testy/e2e/lh-werdykt.py" "$PROG" cicho "$plik"; then
      i=2; ile=1
      while [ "$i" -le "$POWTORKI" ]; do
        dodatkowy="$OUT/$nazwa-$tryb-p$i.json"
        if zmierz "$BAZA$sciezka" "$PRESET" "$dodatkowy" "$OUT/$nazwa-$tryb-p$i.err"; then
          PLIKI="$PLIKI $dodatkowy"; ile=$((ile+1))
        else
          # ⛔ Nieudana powtorka NIE moze zniknac po cichu: bez tego zestaw robi sie PARZYSTY,
          # a mediana z parzystej liczby to SREDNIA — czyli dokladnie to, czego zabrania naglowek.
          echo "  ! lighthouse $nazwa $tryb powtórka $i padła (patrz $OUT/$nazwa-$tryb-p$i.err)"
        fi
        i=$((i+1))
      done
      # Do werdyktu nigdy nie trafia zestaw parzysty — odrzucamy ostatni przebieg.
      if [ $(( ile % 2 )) -eq 0 ]; then
        echo "  ! $nazwa $tryb: zebrano $ile przebiegow (parzyscie) — odrzucam ostatni, zeby mediana nie byla srednia"
        PLIKI="${PLIKI% *}"
      fi
    fi
    # shellcheck disable=SC2086
    if ! python3 "$REPO/testy/e2e/lh-werdykt.py" "$PROG" "$nazwa $tryb" $PLIKI; then
      bledy=$((bledy+1))
    fi
  done
done
echo "JSON: $OUT"
exit $bledy
