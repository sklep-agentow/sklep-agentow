#!/usr/bin/env bash
# lib-golden-stos.sh — GOLDEN „kupujący dostaje to, co kupił” na STOSIE PRÓBNYM.   WERSJA 1.0.1
#
# PO CO (znalezisko I-24, 1.09): golden był jedynym strażnikiem naprawy migawki produktu
# (PR #258) i NIC go nie wołało. Wpięcie w bramki było zablokowane pułapką: jedyny sposób
# uruchomienia wgrywał kod GAŁĘZI na WSPÓLNY stos warsztatu i go tam zostawiał — zmierzone
# 1.09: stos cofnięty z 0.9.32 na 0.9.31, cudza robota pod stopami. Ten plik zdejmuje pułapkę:
# golden biegnie na `probny_wp` (lib-stos-probny.sh), które stawia się od zera i znika.
#
# ⛔ NIE DOTYKA `strona_wp_wordpress`. Jedyne, co zostaje po biegu, to wypis na ekranie.
# ⛔ Klucze Stripe na stosie próbnym to ATRAPY. Golden podstawia odpowiedź Stripe filtrem
#    `pre_http_request` (bez sieci); klucz publikowalny musi być NIEPUSTY tylko po to, żeby
#    handler poszedł ścieżką osadzoną zamiast `exit` na hostowaną (golden sam to sprawdza).
#
# Dołączają: testy/golden-stos-probny.sh (bramka `golden` w bramki.sh — jeden bieg na kodzie
# z drzewa) i testy/golden-migawka-proba-kontrolna.sh (kalibracja: 4 mutanty + czysty kod).
set -u
KORZEN_GOLDEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN_GOLDEN" || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "GOLDEN: brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }

GOLDEN_TEST=testy/golden-kupujacy-dostaje-to-co-kupil-test.php
GOLDEN_CEL=/var/www/html/wp-content/plugins/sklep-agentow
# ⛔ ZAMKA NIE BIERZEMY TUTAJ. Bierze go `postaw_stos` z narzedzia-qa/lib-stos-probny.sh
# (zamek `wymiana/stos-probny.lock`, WŁASNY dla `probny_wp`) — dzięki temu chroni WSZYSTKICH
# ośmiu użytkowników stosu próbnego, a nie tylko goldena. Do 2.09 golden brał tu
# `wymiana/stos.lock`, czyli zamek stosu WARSZTATOWEGO, który `linia-bazowa.sh` trzyma
# już nad każdym `testy/*.sh` — drugie wzięcie tego samego pliku zakleszczało bieg
# na własnej blokadzie aż do `timeout 300` (EXIT=124, fałszywe czerwone w linii bazowej).

golden_przygotuj_stos() {
	wymagaj_narzedzi
	[ -f "$GOLDEN_TEST" ] || padnij "brak $GOLDEN_TEST"
	postaw_stos
	wp option update sklepag_stripe_klucz_publikowalny pk_test_atrapa_golden > /dev/null || padnij "nie ustawiłam klucza publikowalnego (atrapy)"
	wp option update sklepag_stripe_klucz_sekretny    sk_test_atrapa_golden > /dev/null || padnij "nie ustawiłam klucza sekretnego (atrapy)"
	docker cp "$GOLDEN_TEST" "$KONTENER:/tmp/gold.php" > /dev/null || padnij "nie wgrałam goldena do kontenera"
}

# $1 = katalog wtyczki do wdrożenia (drzewo albo kopia z mutantem)
golden_wdroz() {
	docker exec "$KONTENER" rm -rf "$GOLDEN_CEL" > /dev/null 2>&1
	docker exec "$KONTENER" mkdir -p "$GOLDEN_CEL" > /dev/null 2>&1 || return 1
	docker cp "$1/." "$KONTENER:$GOLDEN_CEL/" > /dev/null || return 1
	docker exec "$KONTENER" chown -R www-data:www-data "$GOLDEN_CEL" > /dev/null 2>&1 || return 1
	wp plugin activate sklep-agentow > /dev/null 2>&1 || return 1
	sleep 3   # opcache w obrazie odświeża się co 2 s
}

# Wypisuje wyjście goldena; kod wyjścia = kod goldena (0 = swoje · 1 = WADA · 2 = przyrząd).
golden_odpal() {
	docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/gold.php 2>&1
}
