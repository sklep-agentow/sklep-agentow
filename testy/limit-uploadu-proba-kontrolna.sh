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

STARY_REF="${STARY_REF:-origin/main}"   # kod SPRZED naprawy (brak komunikatu w ogole)
RUNDA1_REF="${RUNDA1_REF:-d1dc673}"     # runda 1 PR #263: komunikat JEST, ale bez progu uprawnien
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
		WP_SUB_USER=probny-sub WP_SUB_PASS=probne-haslo-SUB \
		bash testy/limit-uploadu-mowi-prawde.sh 2>&1
}

# ⛔ Konto BEZ uprawnienia `upload_files` — kierunek E strażnika mierzy, czy konfiguracja
# serwera nie wycieka do zwykłego zalogowanego. Subskrybent `upload_files` nie ma.
zaloz_subskrybenta() {
	wp user create probny-sub probny-sub@example.com --role=subscriber \
		--user_pass=probne-haslo-SUB > /dev/null 2>&1
	wp user get probny-sub --field=roles 2>/dev/null | grep -q subscriber \
		|| padnij "nie umiem założyć konta subskrybenta na stosie próbnym"
}

postaw_stos
zaloz_subskrybenta

naglowek "KOD SPRZED NAPRAWY ($STARY_REF) — strażnik MA PADAĆ NA A"
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
	&& printf '%s' "$WY1" | grep -q '✅ WYSZLO      D:' \
	&& printf '%s' "$WY1" | grep -q '✅ WYSZLO      E:' \
	&& printf '%s' "$WY1" | grep -q '✅ WYSZLO      F:'; then
	# ⛔ Nie wystarczy „coś padło". Wymagamy DOKŁADNIE tego kształtu: pada A (pusta biała
	# strona), a B, C, D i E przechodzą — czyli strażnik pokazuje TĘ wadę, a nie ogólne
	# zepsucie ścieżki zapisu produktu. (E przechodzi tu z braku zaczepienia, nie z zasługi —
	# dowodzi tego dopiero etap „runda 1" niżej.)
	potwierdz "strażnik PADŁ na kodzie sprzed naprawy (EXIT=1): pada wyłącznie A (pusta odpowiedź), B/C/D/E przechodzą"
else
	zglos "strażnik nie padł w oczekiwanym kształcie (EXIT=$KOD1) — nie mierzy tej wady"
fi

naglowek "RUNDA 1 PR #263 ($RUNDA1_REF) — strażnik MA PADAĆ NA E, F i G"
# ⛔ DRUGA KALIBRACJA, OSOBNA. Kierunek E (wyciek konfiguracji do subskrybenta) na
# `origin/main` przechodzi, bo tam ogólnego zaczepienia w ogóle NIE MA — czyli tamten
# przebieg NIE JEST dowodem, że E cokolwiek mierzy. Dowodem jest dopiero kod rundy 1,
# w którym zaczepienie już jest, a progu uprawnień jeszcze nie ma (uwaga 2. recenzji).
# Bez tego etapu kierunek E byłby napisem, nie pomiarem.
mkdir -p "$ROBOCZY/runda1"
git archive "$RUNDA1_REF" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROBOCZY/runda1" \
	|| padnij "nie umiem wyciągnąć wtyczki z $RUNDA1_REF"
RUNDA1="$ROBOCZY/runda1/wtyczki/sklep-agentow"
if grep -q "current_user_can( 'upload_files' )" "$RUNDA1/includes/admin-produkty.php"; then
	padnij "$RUNDA1_REF ma już próg uprawnień — to nie jest kod rundy 1, kalibracja E nic by nie zmierzyła"
fi
wdroz "$RUNDA1" || padnij "nie udało się wdrożyć wtyczki z $RUNDA1_REF"
WY15="$(odpal_straznika)"; KOD15=$?
printf '%s\n' "$WY15" | sed 's/^/    | /'
if [ "$KOD15" -eq 2 ]; then
	zglos "strażnik oddał 2 (przyrząd/środowisko) na rundzie 1 — kalibracja E nic nie zmierzyła"
elif [ "$KOD15" -eq 1 ] \
	&& printf '%s' "$WY15" | grep -q '⛔ NIE WYSZLO  E: subskrybent DOSTAŁ konfigurację serwera' \
	&& printf '%s' "$WY15" | grep -q '⛔ NIE WYSZLO  F: żądanie, którego PHP NIE uciął' \
	&& printf '%s' "$WY15" | grep -q '⛔ NIE WYSZLO  G: żądanie NIEFORMULARZOWE ponad post_max_size' \
	&& printf '%s' "$WY15" | grep -q '✅ WYSZLO      A:'; then
	potwierdz "strażnik PADŁ na rundzie 1 (EXIT=1): padają E (wyciek do subskrybenta), F (zmyślona diagnoza) i G (klauzula typu treści), A już przechodzi"
else
	zglos "strażnik nie padł na rundzie 1 w oczekiwanym kształcie (EXIT=$KOD15) — kierunki E/F/G nie mierzą uwag 2. i 4. recenzji"
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
	echo "KALIBRACJA: OK — straznik pada na A przed naprawa (EXIT=1), pada na E na rundzie 1 (EXIT=1) i przechodzi na drzewie (EXIT=0)"
	exit 0
fi
echo "KALIBRACJA: NIE WYSZLA — $BLEDY potkniec"
exit 1
