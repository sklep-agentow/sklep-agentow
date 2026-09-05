#!/bin/bash
# Spust strażnika A7b-F2 na STOSIE PRÓBNYM od zera (nigdy na wspólnym stanowisku).
# Próba DWUKIERUNKOWA: kod z gałęzi (naprawiony) → EXIT=0; ten sam kod z RĘCZNIE
# podłożoną wadą (powrót do surowego wypisania UTC) → EXIT=1. Mutant wchodzi WYŁĄCZNIE
# w kopii wewnątrz kontenera — worktree zostaje nietknięty.
#
# Użycie: bash narzedzia-qa/panel-link-wygasa-czas-lokalny-proba-kontrolna.sh
#   EXIT 0 = obie strony próby zgodne z oczekiwaniem · 1 = któraś nie wyszła · 2 = brak warunków.
set -u

cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }

WTYCZKA_ZRODLO=wtyczki/sklep-agentow
TEST=testy/panel-link-wygasa-czas-lokalny-test.php
[ -d "$WTYCZKA_ZRODLO" ] || padnij "nie ma katalogu $WTYCZKA_ZRODLO"
[ -f "$TEST" ]           || padnij "nie ma $TEST"

wymagaj_narzedzi
postaw_stos

naglowek "WGRANIE KODU Z DRZEWA (nie z paczki — mierzymy to, co w gałęzi)"
docker cp "$WTYCZKA_ZRODLO" "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow" > /dev/null \
	|| padnij "nie umiem wgrac wtyczki do kontenera"
docker cp "$TEST" "$KONTENER:/var/www/html/panel-czas-test.php" > /dev/null \
	|| padnij "nie umiem wgrac testu do kontenera"
wp plugin activate sklep-agentow > /dev/null 2>&1 || padnij "wtyczka nie chce sie aktywowac"

naglowek "KIERUNEK POZYTYWNY — kod NAPRAWIONY z gałęzi"
WYJ_OK="$(docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/panel-czas-test.php 2>&1)"
KOD_OK=$?
printf '%s\n' "$WYJ_OK"

naglowek "KIERUNEK NEGATYWNY — mutant w KOPII wewnątrz kontenera (worktree nietknięty)"
# Mutant: cofa dokładnie tę jedną naprawę — surowe wypisanie UTC, bez konwersji i bez etykiety.
# ⛔ Podmiana idzie przez plik lokalny (nie `sed`/`perl` w cudzysłowach zagnieżdżonych trzy
#    poziomy głęboko — apostrofy polskie i `$zam->` w tym samym wzorcu psuły escapowanie
#    i dawały ciche "0 podmian" zamiast błędu).
PLIK_MUTANT="$(mktemp)"
docker cp "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow/includes/admin-zamowienie.php" "$PLIK_MUTANT" \
	|| padnij "nie umiem odczytac pliku z kontenera do podlozenia mutanta"
python3 - "$PLIK_MUTANT" <<'PYEOF' || padnij "mutant nie wszedl (wzorzec nie znaleziony dokladnie raz)"
import sys
path = sys.argv[1]
with open(path, encoding="utf-8") as f:
	content = f.read()
naprawiona = "' · link ważny do ' . esc_html( get_date_from_gmt( $zam->link_wygasa, 'd.m.Y H:i' ) ) . ' (czas lokalny)'"
wadliwa   = "' · link ważny do ' . esc_html( $zam->link_wygasa )"
if content.count(naprawiona) != 1:
	sys.exit(1)
with open(path, "w", encoding="utf-8") as f:
	f.write(content.replace(naprawiona, wadliwa))
PYEOF
docker cp "$PLIK_MUTANT" "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow/includes/admin-zamowienie.php" \
	|| padnij "nie umiem wgrac mutanta z powrotem do kontenera"
rm -f "$PLIK_MUTANT"
# ⛔ Dowód, że mutant WSZEDŁ, daje sam krok Pythona wyżej: `sys.exit(1)` → `padnij`, gdy wzorzec
#    naprawionej linii nie wystąpił dokładnie raz. Osobny `grep` po samej frazie "czas lokalny"
#    liczyłby też komentarze i sąsiedni wiersz „Zgoda" — więc nic by nie odróżniał.
WYJ_ZLE="$(docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/panel-czas-test.php 2>&1)"
KOD_ZLE=$?
printf '%s\n' "$WYJ_ZLE"

KOD=0
if [ "$KOD_OK" -ne 0 ]; then
	echo "  ⛔ KIERUNEK POZYTYWNY PADŁ (oczekiwane EXIT=0, jest $KOD_OK) — naprawa nie działa"
	KOD=1
fi
if [ "$KOD_ZLE" -ne 1 ]; then
	echo "  ⛔ KIERUNEK NEGATYWNY NIE ZŁAPAŁ WADY (oczekiwane EXIT=1, jest $KOD_ZLE) — test ślepy albo martwy"
	KOD=1
fi
zwin_stos
docker rm -f -v "$KONTENER" > /dev/null 2>&1 || true

printf '\nSPUST PANEL-CZAS A7b-F2: EXIT=%s (pozytywny=%s, negatywny=%s)\n' "$KOD" "$KOD_OK" "$KOD_ZLE"
exit "$KOD"
