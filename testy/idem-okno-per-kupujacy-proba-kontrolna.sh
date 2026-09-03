#!/usr/bin/env bash
# PRÓBA KONTROLNA strażnika `idem-okno-per-kupujacy-test.php` (Q-5) — KALIBRACJA NA KODZIE SPRZED NAPRAWY.
# Strażnik, który nigdy nie widział wady, nie wiadomo, czy ją widzi. Ten skrypt:
#   1) wdraża na STOS PRÓBNY wtyczkę z $STARY_REF (domyślnie origin/main, klucz okna bez znacznika)
#      → strażnik MUSI PAŚĆ dokładnie na A (dwie osoby dzielą okno), a B i C muszą przejść;
#   2) wdraża wtyczkę z bieżącego drzewa → strażnik MUSI PRZEJŚĆ.
# Stos próbny stawia i zwija `narzedzia-qa/lib-stos-probny.sh` (probny_wp) — wspólny stos NIETKNIĘTY.
# Kody wyjścia: 0 = kalibracja udana · 1 = któryś etap nie zachował się jak trzeba · 2 = przyrząd/środowisko.
set -u
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "PRZYRZAD: brak lib-stos-probny.sh" >&2; exit 2; }

STARY_REF="${STARY_REF:-origin/main}"
CEL_WTYCZKI=/var/www/html/wp-content/plugins/sklep-agentow
ROBOCZY="$(mktemp -d -t q5-kalibracja-XXXXXX)"
exec 9> /root/praca/zlecenia/wymiana/stos.lock || padnij "nie mogę otworzyć zamka stosu"
flock -w 900 9 || padnij "stos próbny zajęty dłużej niż 15 min"
trap 'rm -rf "$ROBOCZY"; zwin_stos' EXIT INT TERM

wymagaj_narzedzi
git rev-parse --verify "$STARY_REF" > /dev/null 2>&1 || padnij "nie znam referencji $STARY_REF"

wdroz() {
	docker exec "$KONTENER" rm -rf "$CEL_WTYCZKI" > /dev/null 2>&1
	docker exec "$KONTENER" mkdir -p "$CEL_WTYCZKI" > /dev/null 2>&1 || return 1
	docker cp "$1/." "$KONTENER:$CEL_WTYCZKI/" > /dev/null || return 1
	docker exec "$KONTENER" chown -R www-data:www-data "$CEL_WTYCZKI"
	wp plugin activate sklep-agentow > /dev/null 2>&1 || return 1
	sleep 3   # opcache w obrazie odświeża się co 2 s
}
odpal_straznika() {
	docker cp testy/idem-okno-per-kupujacy-test.php "$KONTENER:/tmp/q5-test.php" > /dev/null
	docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/q5-test.php 2>&1
}

postaw_stos
# Atrapy kluczy: handler zakupu bez klucza sekretnego nie zaczyna sesji; sieć i tak podstawia strażnik.
wp option update sklepag_stripe_klucz_sekretny sk_test_atrapa_q5 > /dev/null || padnij "klucz sekretny (atrapa)"

naglowek "KOD SPRZED NAPRAWY ($STARY_REF) — strażnik MA PAŚĆ na A, przejść na B i C"
mkdir -p "$ROBOCZY/stary"
git archive "$STARY_REF" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROBOCZY/stary" \
	|| padnij "nie umiem wyciągnąć wtyczki z $STARY_REF"
STARA="$ROBOCZY/stary/wtyczki/sklep-agentow"
if grep -q 'sklepag_znacznik_okna_z_post' "$STARA/includes/zakup.php"; then
	padnij "$STARY_REF ZAWIERA już naprawę — to nie jest kod sprzed niej"
fi
wdroz "$STARA" || padnij "nie udało się wdrożyć wtyczki z $STARY_REF"
WY1="$(odpal_straznika)"; KOD1=$?
printf '%s\n' "$WY1" | sed 's/^/    | /'
if [ "$KOD1" -eq 2 ]; then
	zglos "strażnik oddał 2 (przyrząd/środowisko) — kalibracja nic nie zmierzyła"
elif [ "$KOD1" -eq 1 ] \
	&& printf '%s' "$WY1" | grep -q 'WYNIK=FAIL - druga osoba' \
	&& printf '%s' "$WY1" | grep -q 'podwojny klik nie tworzy drugiej platnosci' \
	&& ! printf '%s' "$WY1" | grep -q 'FAIL - ochrona przed podwojnym' \
	&& ! printf '%s' "$WY1" | grep -q 'FAIL - bez znacznika'; then
	potwierdz "strażnik PADŁ na kodzie sprzed naprawy dokładnie na A (EXIT=$KOD1; B i C PASS)"
else
	zglos "strażnik na kodzie sprzed naprawy oddał EXIT=$KOD1 albo padł nie tam, gdzie wada"
fi

# Limiter zakupu liczy 10 prób na adres i MINUTĘ, a obie fazy biegną na tym samym stosie w tej samej
# minucie (6 wywołań handlera na fazę). Bez wyczyszczenia licznika druga faza pada kodem 2 na 11. wywołaniu
# — zmierzone przy pierwszym biegu tej próby. Czyścimy licznik, nie zmieniamy limitu.
sql_bazy "DELETE FROM \`$BAZA\`.wp_sklepag_rate_limit;" > /dev/null 2>&1 || padnij "nie umiem wyczyścić licznika limitera"

naglowek "BIEŻĄCE DRZEWO (naprawa) — strażnik MA PRZEJŚĆ"
wdroz "wtyczki/sklep-agentow" || padnij "nie udało się wdrożyć wtyczki z drzewa"
WY2="$(odpal_straznika)"; KOD2=$?
printf '%s\n' "$WY2" | sed 's/^/    | /'
if [ "$KOD2" -eq 0 ] && printf '%s' "$WY2" | grep -q 'RAZEM: WYNIK=PASS'; then
	potwierdz "strażnik PRZESZEDŁ na naprawionym kodzie (EXIT=0)"
else
	zglos "strażnik na naprawionym kodzie oddał EXIT=$KOD2"
fi

naglowek "PODSUMOWANIE"
if [ "$BLEDY" -eq 0 ]; then
	echo "KALIBRACJA Q-5: OK — strażnik widzi wadę i przepuszcza naprawę"
	exit 0
fi
echo "KALIBRACJA Q-5: $BLEDY problem(y)"
exit 1
