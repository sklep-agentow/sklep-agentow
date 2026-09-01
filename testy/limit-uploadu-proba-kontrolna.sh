#!/bin/bash
# Próba kontrolna strażnika limitu wgrania — KALIBRACJA: pokazuje, że strażnik PADA
# na kodzie sprzed naprawy i PRZECHODZI na kodzie z drzewa.
#
# ⛔ NIE DOTYKA STOSU WARSZTATOWEGO. Stawia własną, jednorazową instalację
# (`probny_wp`, baza `wp_probny`, port 8891) przez `narzedzia-qa/lib-stos-probny.sh`
# i tylko ją kasuje. Powód jest konkretny: 2026-09-01 na stosie warsztatowym mierzył
# audyt (H1-H3), a kalibracja wymaga WDROŻENIA dwóch różnych wersji wtyczki pod rząd —
# to zepsułoby cudzy pomiar. Kalibracja nie ma prawa być droższa niż wada, którą bada.
#
# ⭐ DLACZEGO KALIBRACJA JEST WIARYGODNA: strażnik bierze adres wysyłki z atrybutu
# `action` PRAWDZIWEGO formularza. Na kodzie sprzed naprawy formularz wysyła na goły
# `admin-post.php`, a `action` jedzie wyłącznie w ciele — które PHP kasuje. Dlatego
# kierunek A pada tam nie „bo tak napisaliśmy", tylko z DOKŁADNIE tego powodu, który
# opisuje wada. Kierunki B, C i D mają przejść PO OBU STRONACH — gdyby padły na starym
# kodzie, znaczyłoby to, że strażnik mierzy zepsucie ścieżki zapisu, a nie tę wadę.
#
# Użycie: bash testy/limit-uploadu-proba-kontrolna.sh        (ZOSTAW=tak = nie zwijaj stosu)
# Kody wyjścia: 0 = kalibracja przeszła · 1 = strażnik ślepy · 2 = przyrząd/środowisko
set -u

cd "$(dirname "$0")/.." || { echo "PRZYRZAD: nie umiem wejsc do korzenia repo" >&2; exit 2; }
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "PRZYRZAD: brak lib-stos-probny.sh" >&2; exit 2; }

STARY_REF="${STARY_REF:-origin/main}"   # kod SPRZED naprawy
CEL_WTYCZKI=/var/www/html/wp-content/plugins/sklep-agentow
ROBOCZY="$(mktemp -d -t limit-kalibracja-XXXXXX)"
trap 'rm -rf "$ROBOCZY"; zwin_stos' EXIT INT TERM

wymagaj_narzedzi
command -v python3 > /dev/null || padnij "brak python3 (strażnik buduje nim zipy)"
git rev-parse --verify "$STARY_REF" > /dev/null 2>&1 || padnij "nie znam referencji $STARY_REF"

wdroz() { # $1 = katalog ze wtyczką
	docker exec "$KONTENER" rm -rf "$CEL_WTYCZKI" > /dev/null 2>&1
	docker exec "$KONTENER" mkdir -p "$CEL_WTYCZKI" > /dev/null 2>&1 || return 1
	docker cp "$1/." "$KONTENER:$CEL_WTYCZKI/" > /dev/null || return 1
	docker exec "$KONTENER" chown -R www-data:www-data "$CEL_WTYCZKI"
	wp plugin activate sklep-agentow > /dev/null 2>&1 || return 1
	# ⛔ WP-CLI aktywuje wtyczkę jako ROOT, więc katalog chroniony na `uploads/` powstaje
	# z właścicielem root — a wgranie przez HTTP robi Apache jako www-data i `move_uploaded_file()`
	# odbija się o uprawnienia. Bez tej linii kierunek D (poprawne wgranie) padał na OBU wersjach
	# wtyczki z komunikatem „Zapis do katalogu chronionego nie powiódł się (uprawnienia uploads?)" —
	# czyli z powodu PRZYRZĄDU, a nie kodu. Na prawdziwym hostingu katalog zakłada ten sam proces,
	# który potem zapisuje pliki, i problemu nie ma.
	docker exec "$KONTENER" chown -R www-data:www-data /var/www/html/wp-content/uploads
	# opcache w obrazie odświeża się co 2 s — bez tego pierwszy strzał HTTP trafia w stary kod
	sleep 3
}

odpal_straznika() {
	ADRES="$ADRES" KONTENER="$KONTENER" WP_ADMIN_USER=probny WP_ADMIN_PASS=probne-haslo-QA \
		bash testy/limit-uploadu-mowi-prawde.sh 2>&1
}

postaw_stos

naglowek "KOD SPRZED NAPRAWY ($STARY_REF) — strażnik MA PADAĆ"
mkdir -p "$ROBOCZY/stary"
git archive "$STARY_REF" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROBOCZY/stary" \
	|| padnij "nie umiem wyciągnąć wtyczki z $STARY_REF"
STARA="$ROBOCZY/stary/wtyczki/sklep-agentow"
# ⛔ Sprawdzenie, że stary kod NAPRAWDĘ jest sprzed naprawy. Bez tego kalibracja
# uruchomiona na już naprawionym mainie meldowałaby „strażnik ślepy" zamiast prawdy.
if grep -q 'sklepag_zadanie_ucieto_przez_php' "$STARA/includes/admin-produkty.php"; then
	padnij "$STARY_REF ZAWIERA już naprawę — to nie jest kod sprzed niej, kalibracja nic by nie zmierzyła"
fi
wdroz "$STARA" || padnij "nie udało się wdrożyć wtyczki z $STARY_REF"
WY1="$(odpal_straznika)"; KOD1=$?
printf '%s\n' "$WY1" | sed 's/^/    | /'
if [ "$KOD1" -eq 2 ]; then
	zglos "strażnik oddał 2 (przyrząd/środowisko) — kalibracja nic nie zmierzyła"
elif [ "$KOD1" -eq 1 ] \
	&& printf '%s' "$WY1" | grep -q '⛔ NIE WYSZLO  A: odpowiedź PUSTA' \
	&& printf '%s' "$WY1" | grep -q '✅ WYSZLO      B:' \
	&& printf '%s' "$WY1" | grep -q '✅ WYSZLO      C:' \
	&& printf '%s' "$WY1" | grep -q '✅ WYSZLO      D:'; then
	# ⛔ Nie wystarczy „coś padło". Wymagamy DOKŁADNIE tego kształtu: pada A (pusta biała
	# strona), a B, C i D przechodzą — czyli strażnik pokazuje TĘ wadę, a nie ogólne
	# zepsucie ścieżki zapisu produktu.
	potwierdz "strażnik PADŁ na kodzie sprzed naprawy (EXIT=1): pada wyłącznie A (pusta odpowiedź), B/C/D przechodzą"
else
	zglos "strażnik nie padł w oczekiwanym kształcie (EXIT=$KOD1) — nie mierzy tej wady"
fi

naglowek "KOD Z DRZEWA — strażnik MA PRZEJŚĆ"
wdroz wtyczki/sklep-agentow || padnij "nie udało się wdrożyć wtyczki z drzewa"
WY2="$(odpal_straznika)"; KOD2=$?
printf '%s\n' "$WY2" | sed 's/^/    | /'
if [ "$KOD2" -eq 0 ]; then
	potwierdz "strażnik PRZESZEDŁ na kodzie z drzewa (EXIT=0)"
else
	zglos "strażnik NIE przeszedł na kodzie z drzewa (EXIT=$KOD2) — naprawa nie zamyka wady"
fi

naglowek "WYNIK KALIBRACJI"
if [ "$BLEDY" -eq 0 ]; then
	echo "KALIBRACJA: OK — straznik pada przed naprawa (EXIT=1) i przechodzi po niej (EXIT=0)"
	exit 0
fi
echo "KALIBRACJA: NIE WYSZLA — $BLEDY potkniec"
exit 1
