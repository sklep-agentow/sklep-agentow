#!/bin/bash
# Spust dowodu P-5 na STOSIE PRÓBNYM od zera (nigdy na wspólnym stanowisku) — recenzja #291
# WAŻNA 4: dowód RODO (czyszczenie adresów IP przy anonimizacji kupującego) był nieodtwarzalny,
# bo test nie miał żadnego wołającego. Próba DWUKIERUNKOWA, na wzór A7b-F2
# (panel-link-wygasa-czas-lokalny-proba-kontrolna.sh): kod z gałęzi (naprawiony) → EXIT=0;
# ten sam kod z RĘCZNIE podłożoną wadą (usunięta pętla czyszcząca IP) → EXIT=1. Mutant wchodzi
# WYŁĄCZNIE w kopii wewnątrz kontenera — worktree zostaje nietknięty.
#
# Użycie: bash narzedzia-qa/rodo-anonimizacja-ip-proba-kontrolna.sh
#   EXIT 0 = obie strony próby zgodne z oczekiwaniem · 1 = któraś nie wyszła · 2 = brak warunków.
set -u

cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }

WTYCZKA_ZRODLO=wtyczki/sklep-agentow
TEST=testy/rodo-anonimizacja-ip-w-dzienniku-test.php
[ -d "$WTYCZKA_ZRODLO" ] || padnij "nie ma katalogu $WTYCZKA_ZRODLO"
[ -f "$TEST" ]           || padnij "nie ma $TEST"

wymagaj_narzedzi
postaw_stos

naglowek "WGRANIE KODU Z DRZEWA (nie z paczki — mierzymy to, co w gałęzi)"
docker cp "$WTYCZKA_ZRODLO" "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow" > /dev/null \
	|| padnij "nie umiem wgrac wtyczki do kontenera"
docker cp "$TEST" "$KONTENER:/var/www/html/rodo-ip-test.php" > /dev/null \
	|| padnij "nie umiem wgrac testu do kontenera"
wp plugin activate sklep-agentow > /dev/null 2>&1 || padnij "wtyczka nie chce sie aktywowac"

naglowek "KIERUNEK POZYTYWNY — kod NAPRAWIONY z gałęzi"
WYJ_OK="$(docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/rodo-ip-test.php 2>&1)"
KOD_OK=$?
printf '%s\n' "$WYJ_OK"

naglowek "KIERUNEK NEGATYWNY — mutant w KOPII wewnątrz kontenera (worktree nietknięty)"
# Mutant: usuwa CAŁĄ pętlę czyszczącą ślady adresu IP (prywatnosc.php, gate P-5) —
# dokładnie tę, o którą prosiła recenzja #291 WAŻNA 4 ("usunięcie pętli czyszczącej
# prywatnosc.php:271-291 → EXIT=1").
# ⛔ Podmiana idzie przez plik lokalny + Python (nie `sed` na wieloliniowym bloku z apostrofami
#    i strzałkami `$wpdb->` — te psuły escapowanie i dawały ciche "0 podmian" zamiast błędu,
#    klasa 138 ze ściągi: kotwica musi być SPRAWDZONA `count()==1` PRZED mutacją, inaczej
#    zgnicie kotwicy po refaktorze wygląda jak zielona próba, która niczego nie zmierzyła).
PLIK_MUTANT="$(mktemp)"
docker cp "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow/includes/prywatnosc.php" "$PLIK_MUTANT" \
	|| padnij "nie umiem odczytac pliku z kontenera do podlozenia mutanta"
python3 - "$PLIK_MUTANT" <<'PYEOF' || padnij "mutant nie wszedl (petla czyszcząca IP nie znaleziona dokladnie raz)"
import sys
path = sys.argv[1]
with open(path, encoding="utf-8") as f:
	content = f.read()
petla = """	foreach ( sklepag_kandydaci_ip_w_dzienniku( $id ) as $k ) {
		$nowy = preg_replace( SKLEPAG_WZORZEC_IP, 'ip [usunięto — RODO]', (string) $k->opis );
		if ( null === $nowy ) {
			// ⛔ `null` to AWARIA silnika wyrazen, nie „brak dopasowania\" — patrz analogiczny komentarz wyżej.
			$ok_zdarzenia = false;
			continue;
		}
		if ( $nowy === (string) $k->opis ) {
			continue;
		}
		if ( false === $wpdb->update( $wpdb->prefix . 'sklepag_zdarzenia', array( 'opis' => $nowy ), array( 'id' => (int) $k->id ) ) ) {
			$ok_zdarzenia = false;
		}
	}
"""
if content.count(petla) != 1:
	sys.exit(1)
with open(path, "w", encoding="utf-8") as f:
	f.write(content.replace(petla, ""))
PYEOF
docker cp "$PLIK_MUTANT" "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow/includes/prywatnosc.php" \
	|| padnij "nie umiem wgrac mutanta z powrotem do kontenera"
rm -f "$PLIK_MUTANT"
# ⛔ Dowód, że mutant WSZEDŁ, daje sam krok Pythona wyżej: `sys.exit(1)` → `padnij`, gdy pętla
#    nie wystąpiła dokładnie raz — patrz klasa 138 (kotwica sprawdzona PRZED mutacją).
WYJ_ZLE="$(docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/rodo-ip-test.php 2>&1)"
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

printf '\nSPUST RODO-IP P-5: EXIT=%s (pozytywny=%s, negatywny=%s)\n' "$KOD" "$KOD_OK" "$KOD_ZLE"
exit "$KOD"
