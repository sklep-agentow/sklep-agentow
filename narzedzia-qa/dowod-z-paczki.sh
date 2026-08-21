#!/bin/bash
# dowod-z-paczki.sh — POWTARZALNY DOWÓD Z PACZKI, nie z repozytorium.  WERSJA 1.0.1
#
# PO CO TO POWSTAŁO: kupujący nie dostaje repozytorium — dostaje ZIP. Wszystko,
# co sprawdzamy na drzewie roboczym, sprawdza INNY artefakt niż ten sprzedawany.
# Ten skrypt bierze paczkę, stawia CZYSTĄ instalację WordPressa z włączonym
# WP_DEBUG i przechodzi pełne życie wtyczki: wgranie z zipa → aktywacja → praca
# w trybie regułowym BEZ klucza API → deinstalacja → porównanie bazy z odciskiem
# sprzed instalacji.
#
# Cztery pytania, na które odpowiada — każde kodem wyjścia, nie zdaniem:
#   1. Czy wgranie i aktywacja przechodzą BEZ notice, warning i fatal?
#   2. Czy tryb regułowy działa, gdy klucza API nie ma wcale?
#   3. Czy po deinstalacji z pełnym czyszczeniem baza wraca do stanu sprzed
#      instalacji — RÓŻNICA MA BYĆ PUSTA, i jest pokazywana, nie streszczana?
#   4. Czy sekrety właściciela znikają ZAWSZE — także wtedy, gdy właściciel
#      NIE zaznaczył „usuń wszystkie dane"?
#
# ⛔ NIE DOTYKA STOSU WARSZTATOWEGO. Stawia własną, osobną instalację
#    (kontener `probny_wp`, baza `wp_probny`) i tylko ją kasuje. Warsztat, na
#    którym pracują builderzy, zostaje nietknięty — wgrywanie i kasowanie wtyczek
#    na wspólnym stosie zniszczyłoby cudzą robotę w toku.
#
# ⛔ ODCZYTY IDĄ PRZEZ narzedzia-qa/stos.sh --stos probny. Drugiego dostępu do
#    stosu tu nie ma i nie ma go być: jedno okno na WordPressa, jedna biała lista.
#    Operacje ZMIENIAJĄCE (instalacja, aktywacja, deinstalacja) żyją tutaj, bo
#    stos.sh jest z założenia wyłącznie do odczytu i taki ma zostać.
#
# Użycie:
#   bash narzedzia-qa/dowod-z-paczki.sh boty/paczki/chatbot-live-1.5.0.zip
#   bash narzedzia-qa/dowod-z-paczki.sh chatbot-live          # weźmie paczkę z wersji w repo
#   bash narzedzia-qa/dowod-z-paczki.sh <zip> --zostaw        # nie kasuje stosu próbnego po przebiegu
#
# Kody wyjścia: 0 = wszystkie cztery dowody wyszły
#               1 = choć jeden NIE wyszedł (wypisane, który i czym się objawił)
#               2 = nie ma czego sprawdzać (brak paczki, brak dockera, stos nie wstaje)
set -u

WERSJA=1.0.1

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
STOS_SH="$KORZEN/narzedzia-qa/stos.sh"

# Wspólny warsztat (stawianie i sprzątanie czystej instalacji, odciski, log
# błędów) siedzi w bibliotece — ten sam kod obsługuje dowód aktualizacji.
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. "$KORZEN/narzedzia-qa/lib-stos-probny.sh"

# --- argumenty ----------------------------------------------------------------

PACZKA="${1:-}"
[ -n "$PACZKA" ] || padnij "podaj paczkę: bash narzedzia-qa/dowod-z-paczki.sh <zip albo nazwa-wtyczki>"
[ "${2:-}" = "--zostaw" ] && ZOSTAW=tak

# Sama nazwa wtyczki => szukamy paczki w wersji, którą deklaruje nagłówek.
if [ ! -f "$PACZKA" ]; then
	if [ -d "wtyczki/$PACZKA" ]; then
		_w="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' "wtyczki/$PACZKA/$PACZKA.php" | head -1)"
		PACZKA="boty/paczki/$PACZKA-$_w.zip"
	fi
fi
[ -f "$PACZKA" ] || padnij "nie ma paczki [$PACZKA]"

WTYCZKA="$(basename "$PACZKA" .zip | sed 's/-[0-9][0-9.]*$//')"

# --- co wiemy o KAŻDYM z botów (wpisane tutaj, nie zgadywane) -----------------
#
# SEKRETY  — opcje, które uninstall.php ma kasować ZAWSZE, niezależnie od
#            „usuń wszystkie dane". To poświadczenia właściciela, nie jego dane.
# OPCJA_UST — opcja-tablica, w której siedzi przełącznik `usun_dane`.
# PROBA_REGULOWA — fragment PHP wołający SILNIK REGUŁOWY wtyczki i wypisujący
#            wynik. Wpisany tutaj, nie przyjmowany z zewnątrz.
case "$WTYCZKA" in
	chatbot-live)
		SEKRETY=(chatl_klucz_api)
		OPCJA_UST=chatl_ustawienia
		PROBA_REGULOWA='$o = chatl_dopasuj( "Jak dlugo trwa wysylka?" ); echo is_array($o) ? "ODPOWIEDZ:" . substr( (string) ( $o["odpowiedz"] ?? "" ), 0, 40 ) : "ODPOWIEDZ:" . substr( (string) $o, 0, 40 );'
		;;
	segregator-maila)
		SEKRETY=(segm_klucz_api segm_imap_haslo)
		OPCJA_UST=segm_ustawienia
		PROBA_REGULOWA='global $wpdb; $r = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_reguly", ARRAY_A ); $m = array( "nadawca" => "faktury@example.com", "temat" => "Faktura 1/2026", "tresc" => "W zalaczniku faktura." ); echo "ODPOWIEDZ:" . (string) segm_klasyfikuj_mail( $m, $r );'
		;;
	*)
		padnij "nie znam wtyczki [$WTYCZKA] — dopisz ją do tablicy w tym skrypcie, nie obchodź jej"
		;;
esac

wymagaj_narzedzi
trap zwin_stos EXIT

# --- dowody --------------------------------------------------------------------

wgraj_z_zipa() {
	docker cp "$PACZKA" "$KONTENER:/tmp/paczka.zip" > /dev/null || padnij "nie umiem wgrać zipa do kontenera"
	wp plugin install /tmp/paczka.zip --force --activate
}

ustaw_sekrety() {
	local s
	for s in "${SEKRETY[@]}"; do
		wp option update "$s" "PROBNY-SEKRET-$s" > /dev/null
	done
}

ustaw_czyszczenie() {
	# $1 = 1 (pełne czyszczenie) albo 0 (właściciel go NIE chce)
	wp eval "\$u = get_option( '$OPCJA_UST', array() ); if ( ! is_array( \$u ) ) { \$u = array(); } \$u['usun_dane'] = $1; update_option( '$OPCJA_UST', \$u );" > /dev/null
}

sekrety_ktore_zostaly() {
	local s zostaly=()
	for s in "${SEKRETY[@]}"; do
		if wp option get "$s" > /dev/null 2>&1; then zostaly+=("$s"); fi
	done
	printf '%s' "${zostaly[*]:-}"
}

# ==============================================================================

printf '=== DOWOD Z PACZKI %s ===\n' "$WERSJA"
printf '  paczka:  %s\n' "$PACZKA"
printf '  suma:    %s\n' "$(sha256sum "$PACZKA" | cut -d' ' -f1)"
printf '  wtyczka: %s\n' "$WTYCZKA"
printf '  sekrety kasowane ZAWSZE: %s\n' "${SEKRETY[*]}"

postaw_stos

# ---- ODCISK PRZED ----
#
# ⚠️ NAJPIERW JEDNO ŻĄDANIE, POTEM ODCISK. WordPress dopisuje część opcji dopiero
# przy pierwszym wyświetleniu strony (np. `theme_mods_<motyw>`). Odcisk zdjęty
# przed tym żądaniem pokazywałby je później jako „ślad po wtyczce" — pierwszy
# przebieg tego skryptu tak właśnie oskarżył ChatBota o `theme_mods_twentytwentyfive`.
# To był błąd POMIARU, nie wtyczki, i naprawia się go kolejnością, a nie
# dopisaniem kolejnego wyjątku do listy pominięć.
bash "$STOS_SH" --stos probny strona / > /dev/null 2>&1 || true
wyczysc_log

ODCISK_PRZED="$(mktemp)"; ODCISK_PO="$(mktemp)"
odcisk > "$ODCISK_PRZED"
printf '  odcisk bazy PRZED instalacja: %s pozycji\n' "$(wc -l < "$ODCISK_PRZED")"

# ---- DOWOD 1: wgranie z ZIPA i aktywacja bez ani jednego notice ----
naglowek "DOWOD 1 — wgranie z PACZKI i aktywacja bez notice i fatal"
wyczysc_log
WYNIK_INSTALACJI="$(wgraj_z_zipa)"
printf '%s\n' "$WYNIK_INSTALACJI" | sed 's/^/    /'
if ! printf '%s' "$WYNIK_INSTALACJI" | grep -q 'Plugin installed successfully\|Plugin updated successfully'; then
	zglos "wgranie wtyczki z paczki nie powiodło się"
elif ! wp plugin is-active "$WTYCZKA" > /dev/null 2>&1; then
	zglos "wtyczka wgrała się, ale NIE JEST AKTYWNA"
else
	potwierdz "wtyczka wgrana Z PACZKI i aktywna"
fi

# Sama aktywacja to za mało: część kodu odpala się dopiero przy żądaniu HTTP.
KOD_STRONY="$(bash "$STOS_SH" --stos probny strona / 2>&1 | sed -n 's/^ *HTTP \([0-9]*\).*/\1/p' | head -1)"
[ "$KOD_STRONY" = "200" ] && potwierdz "strona główna z wtyczką odpowiada HTTP 200" \
	|| zglos "strona główna z wtyczką odpowiada HTTP ${KOD_STRONY:-brak odpowiedzi}"

BLEDY_PHP="$(log_bledow)"
if [ -n "$BLEDY_PHP" ]; then
	zglos "PHP zgłosił błędy przy instalacji/aktywacji/żądaniu — pełna treść niżej"
	printf '%s\n' "$BLEDY_PHP" | sed 's/^/       /'
else
	potwierdz "ani jeden notice, warning, deprecated ani fatal (WP_DEBUG włączony)"
fi

# ---- DOWOD 2: tryb regułowy BEZ klucza API ----
naglowek "DOWOD 2 — tryb regulowy dziala BEZ klucza API"
for s in "${SEKRETY[@]}"; do wp option delete "$s" > /dev/null 2>&1 || true; done
POZOSTALE="$(sekrety_ktore_zostaly)"
[ -z "$POZOSTALE" ] && potwierdz "żaden sekret nie jest ustawiony — startujemy z pustym kluczem" \
	|| zglos "mimo skasowania w bazie siedzą: $POZOSTALE"

wyczysc_log
ODPOWIEDZ="$(wp eval "$PROBA_REGULOWA")"
printf '    %s\n' "$ODPOWIEDZ"
TRESC="$(printf '%s' "$ODPOWIEDZ" | sed -n 's/.*ODPOWIEDZ://p' | tr -d '\r')"
if [ -z "$TRESC" ]; then
	zglos "silnik regułowy nic nie zwrócił bez klucza API"
else
	potwierdz "silnik regułowy odpowiedział bez klucza API: [$TRESC]"
fi
BLEDY_PHP="$(log_bledow)"
[ -z "$BLEDY_PHP" ] && potwierdz "praca w trybie regułowym bez ani jednego błędu PHP" \
	|| { zglos "tryb regułowy zgłosił błędy PHP"; printf '%s\n' "$BLEDY_PHP" | sed 's/^/       /'; }

# ---- DOWOD 3: sekrety znikaja ZAWSZE, takze przy WYLACZONYM czyszczeniu ----
naglowek "DOWOD 3 — sekrety znikaja ZAWSZE (czyszczenie WYLACZONE)"
ustaw_sekrety
ustaw_czyszczenie 0
printf '    ustawione sekrety: %s ; usun_dane = 0 (wlasciciel NIE chce czyszczenia)\n' "${SEKRETY[*]}"
wp plugin uninstall "$WTYCZKA" --deactivate > /dev/null 2>&1
POZOSTALE="$(sekrety_ktore_zostaly)"
if [ -n "$POZOSTALE" ]; then
	zglos "po deinstalacji BEZ czyszczenia w bazie ZOSTAŁY sekrety: $POZOSTALE"
else
	potwierdz "sekrety skasowane mimo wyłączonego czyszczenia"
fi
# Prefiks opcji bierzemy z NAZWY SEKRETU (chatl_klucz_api → chatl_), nie ze slugu
# wtyczki: slug to `chatbot-live`, a opcje mają `chatl_` — liczenie po slugu dawało
# zawsze zero i wyglądało jak dowód, nie będąc nim.
PREFIKS="${SEKRETY[0]%%_*}_"
# `_` w SQL LIKE to METAZNAK „dowolny jeden znak", nie literał: wzorzec `chatl_%`
# policzyłby też `chatlX...`. To odpowiednik esc_like() z WordPressa — klasa #2 ze
# ściągi egzaminatora. Uciekamy przez jawne ESCAPE, nie przez odwrotny ukośnik:
# ukośnik przeżyłby dzisiejszą drogę do bazy, ale nie musi przeżyć następnej.
PREFIKS_LIKE="${PREFIKS//_/!_}"
ILE_ZOSTALO="$(sql_bazy "SELECT COUNT(*) FROM \`$BAZA\`.wp_options WHERE option_name LIKE '${PREFIKS_LIKE}%' ESCAPE '!';" | tr -d '\r')"
printf '    dane uzytkowe z prefiksem %s, ktore MIALY zostac: %s opcji\n' "$PREFIKS" "$ILE_ZOSTALO"

# ---- DOWOD 4: pelne czyszczenie zwraca baze do stanu sprzed instalacji ----
naglowek "DOWOD 4 — pelne czyszczenie: ROZNICA MA BYC PUSTA"
wyczysc_log
wgraj_z_zipa > /dev/null
ustaw_sekrety
ustaw_czyszczenie 1
printf '    wtyczka wgrana ponownie, usun_dane = 1 (pelne czyszczenie)\n'
wp plugin uninstall "$WTYCZKA" --deactivate > /dev/null 2>&1
odcisk > "$ODCISK_PO"

printf '    odcisk PRZED: %s pozycji · odcisk PO: %s pozycji\n' \
	"$(wc -l < "$ODCISK_PRZED")" "$(wc -l < "$ODCISK_PO")"
printf '    swiadomie pominiete w odcisku: %s\n' "$POMIJANE_WZORCE"

ROZNICA="$(diff "$ODCISK_PRZED" "$ODCISK_PO" || true)"
if [ -z "$ROZNICA" ]; then
	potwierdz "ROZNICA PUSTA — baza wrociła do stanu sprzed instalacji"
else
	zglos "ROZNICA NIE JEST PUSTA — po wtyczce zostal slad:"
	printf '%s\n' "$ROZNICA" | sed 's/^/       /'
fi

POZOSTALE="$(sekrety_ktore_zostaly)"
[ -z "$POZOSTALE" ] && potwierdz "po pelnym czyszczeniu nie ma ani jednego sekretu" \
	|| zglos "po pelnym czyszczeniu ZOSTALY sekrety: $POZOSTALE"

BLEDY_PHP="$(log_bledow)"
[ -z "$BLEDY_PHP" ] && potwierdz "deinstalacja bez ani jednego bledu PHP" \
	|| { zglos "deinstalacja zglosila bledy PHP"; printf '%s\n' "$BLEDY_PHP" | sed 's/^/       /'; }

rm -f "$ODCISK_PRZED" "$ODCISK_PO"

# ==============================================================================

naglowek "WYNIK"
if [ "$BLEDY" -eq 0 ]; then
	printf 'PACZKA %s PRZESZLA WSZYSTKIE CZTERY DOWODY.\n' "$(basename "$PACZKA")"
	exit 0
fi
printf 'PACZKA %s NIE PRZESZLA — %s dowod(ow) nie wyszlo (szczegoly wyzej).\n' "$(basename "$PACZKA")" "$BLEDY"
exit 1
