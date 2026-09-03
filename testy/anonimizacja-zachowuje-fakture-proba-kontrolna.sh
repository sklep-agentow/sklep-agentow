#!/usr/bin/env bash
# PRÓBA KONTROLNA strażnika `sklep-rodo-pomiar-danych-faktury-test.php` po zmianie zasady (0.9.37: panel ZACHOWUJE
# dane faktury, jak kasownik WP). Odwrócony przypadek A musi PADAĆ na kodzie sprzed zmiany i PRZECHODZIĆ na nowym;
# przy okazji na nowym kodzie przechodzi też strażnik kasownika/eksportera WP (`sklep-rodo-narzedzia-wp-test.php`).
# Stos próbny stawia i zwija `narzedzia-qa/lib-stos-probny.sh`. Kody: 0 = kalibracja udana · 1 = problem · 2 = przyrząd.
set -u
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "PRZYRZAD: brak lib-stos-probny.sh" >&2; exit 2; }
STARY_REF="${STARY_REF:-origin/main}"
CEL=/var/www/html/wp-content/plugins/sklep-agentow
ROB="$(mktemp -d -t f4-kalibracja-XXXXXX)"; trap 'rm -rf "$ROB"; zwin_stos' EXIT INT TERM
wymagaj_narzedzi
git rev-parse --verify "$STARY_REF" > /dev/null 2>&1 || padnij "nie znam referencji $STARY_REF"
wdroz() {
	docker exec "$KONTENER" rm -rf "$CEL" > /dev/null 2>&1
	docker exec "$KONTENER" mkdir -p "$CEL" > /dev/null 2>&1 || return 1
	docker cp "$1/." "$KONTENER:$CEL/" > /dev/null || return 1
	docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
	wp plugin activate sklep-agentow > /dev/null 2>&1 || return 1
	sleep 3   # opcache w obrazie odświeża się co 2 s
}
test_na() { docker cp "$1" "$KONTENER:/tmp/t.php" > /dev/null; docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/t.php 2>&1; }
postaw_stos
naglowek "BIEŻĄCE DRZEWO — oba strażniki RODO MAJĄ PRZEJŚĆ"
wdroz wtyczki/sklep-agentow || padnij "nie udało się wdrożyć wtyczki z drzewa"
for t in testy/sklep-rodo-pomiar-danych-faktury-test.php testy/sklep-rodo-narzedzia-wp-test.php; do
	W="$(test_na "$t")"; K=$?
	printf '%s\n' "$W" | grep -E 'WYNIK|BLAD|NIE WYSZLO' | sed 's/^/    | /'
	if [ "$K" -eq 0 ]; then potwierdz "$t EXIT=0"; else zglos "$t EXIT=$K na naprawionym kodzie"; fi
done
naglowek "KOD SPRZED ZMIANY ($STARY_REF) — strażnik faktury MA PAŚĆ na przypadku A"
mkdir -p "$ROB/stary"
git archive "$STARY_REF" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROB/stary" || padnij "nie umiem wyciągnąć wtyczki z $STARY_REF"
grep -q 'zachowaj_dane_faktury = false' "$ROB/stary/wtyczki/sklep-agentow/includes/prywatnosc.php" || padnij "$STARY_REF ZAWIERA już zmianę — to nie jest kod sprzed niej"
wdroz "$ROB/stary/wtyczki/sklep-agentow" || padnij "nie udało się wdrożyć wtyczki z $STARY_REF"
W="$(test_na testy/sklep-rodo-pomiar-danych-faktury-test.php)"; K=$?
printf '%s\n' "$W" | grep -E 'WYNIK|BLAD|ZOSTAJA' | sed 's/^/    | /'
# ⛔ „PADŁ” to za mało. Kontrola kalibracji ma dowieść, że stara wada trafia w DOKŁADNIE
#    JEDEN przypadek — inaczej równie dobrze mógłby paść strażnik zepsuty na wylot i wynik
#    wyglądałby tak samo. Dlatego sprawdzam OBIE strony: że A padło ORAZ że przypadki
#    bez wady (C — kasownik RODO, D — zakup prywatny bez danych do faktury) PRZESZŁY.
A_PADLO=nie;  printf '%s' "$W" | grep -q 'BLAD.*dane do faktury ZOSTAJA' && A_PADLO=tak
C_PRZESZLO=nie; printf '%s' "$W" | grep -qE '^[[:space:]]*OK[[:space:]].*(kasownik|RODO)' && C_PRZESZLO=tak
D_PRZESZLO=nie; printf '%s' "$W" | grep -qE '^[[:space:]]*OK[[:space:]].*pusta kolumna to nie brak skutku' && D_PRZESZLO=tak

if [ "$K" -eq 1 ] && [ "$A_PADLO" = tak ] && [ "$C_PRZESZLO" = tak ] && [ "$D_PRZESZLO" = tak ]; then
	potwierdz "strażnik PADŁ na kodzie sprzed zmiany DOKŁADNIE na przypadku A; C i D przeszły (EXIT=$K)"
else
	zglos "kalibracja nie wyszła: EXIT=$K · A_PADLO=$A_PADLO · C_PRZESZLO=$C_PRZESZLO · D_PRZESZLO=$D_PRZESZLO — stara wada ma trafiać w JEDEN przypadek, nie we wszystkie"
fi
naglowek "PODSUMOWANIE"
if [ "$BLEDY" -eq 0 ]; then echo "KALIBRACJA F4-F4: OK — strażnik widzi stare zachowanie, przepuszcza nowe"; exit 0; fi
echo "KALIBRACJA F4-F4: $BLEDY problem(y)"; exit 1
