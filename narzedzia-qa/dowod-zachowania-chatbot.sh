#!/bin/bash
# dowod-zachowania-chatbot.sh — DOWÓD ZACHOWANIA ChatBota Z PACZKI.        WERSJA 1.0.0
#
# `dowod-z-paczki.sh` odpowiada na pytania o CYKL ŻYCIA wtyczki: instalacja,
# tryb regułowy bez klucza, deinstalacja, sekrety. Ten skrypt odpowiada na trzy
# pytania o ZACHOWANIE, których tamten nie zadaje, a które są w punkcie D listy
# domknięcia:
#
#   D3. Klucz API przestaje działać W TRAKCIE — bot ma zjechać do reguł
#       i pokazać właścicielowi znacznik awarii w panelu.
#   D4. Strona przychodzi gościowi z CACHE, więc nonce jest starszy, niż żyje
#       (luka L1) — czat ma dalej działać, a nie odmawiać przy każdym pytaniu.
#   D6. Retencja ma NAPRAWDĘ kasować, nie tylko obiecywać w instrukcji.
#
# ⛔ NIE DOTYKA STOSU WARSZTATOWEGO. Stawia własną instalację z paczki
#    (kontener probny_wp, baza wp_probny) przez lib-stos-probny.sh i tylko ją kasuje.
#
# ⛔ ZERO INTERNETU W DOWODZIE. „Odcięcie klucza" udajemy WŁASNYM punktem
#    końcowym w kontenerze (filtr `chatl_ai_url`), nie prawdziwym API: dowód
#    zależny od cudzego serwera nie jest dowodem, tylko prognozą pogody.
#
# Kody wyjścia: 0 = wszystkie trzy dowody wyszły
#               1 = co najmniej jeden nie wyszedł
#               2 = brak narzędzia, stos nie wstał, zła paczka
set -u

WERSJA=1.0.0
KATALOG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-stos-probny.sh
. "$KATALOG/lib-stos-probny.sh"

KORZEN_REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# ⛔ Domyslna paczka BIERZE SIE Z NAGLOWKA WTYCZKI, nie z wpisanego numeru.
# Wpisany numer starzeje sie przy pierwszym podbiciu wersji i wtedy dowod cicho
# bada PORZEDNIA paczke, ktora dalej lezy obok nowej (zlapane przy zamrozeniu
# 1.5.8: domyslna wartosc wskazywala 1.5.7).
domyslna_paczka() {
	local w wersja
	w="$1"
	wersja="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' "$KORZEN_REPO/wtyczki/$w/$w.php" | head -1)"
	printf 'boty/paczki/%s-%s.zip' "$w" "$wersja"
}
PACZKA="${1:-$(domyslna_paczka chatbot-live)}"
[ -f "$PACZKA" ] || padnij "nie ma paczki [$PACZKA]"
WTYCZKA=chatbot-live
# Pytanie z bazy demo. Spacje MUSZA isc przez --data-urlencode: surowe -d rozbija
# je na osobne parametry i serwer widzi pytanie "ile" — pierwsza wersja tej proby
# mierzyla wlasnie to, a nie zachowanie bota.
PYTANIE="ile trwa realizacja"

printf '=== DOWOD ZACHOWANIA CHATBOTA %s ===\n' "$WERSJA"
printf '  paczka:  %s\n' "$PACZKA"
printf '  suma:    %s\n' "$(sha256sum "$PACZKA" | cut -d' ' -f1)"

wymagaj_narzedzi
postaw_stos

wgraj_z_zipa() {
	docker cp "$PACZKA" "$KONTENER:/tmp/paczka.zip" > /dev/null
	wp plugin install /tmp/paczka.zip --force --activate
}
wgraj_z_zipa > /dev/null
wp plugin is-active "$WTYCZKA" > /dev/null || padnij "wtyczka z paczki nie jest aktywna"

# Punkty końcowe udające API modelu — w KONTENERZE, bez wyjścia w internet.
docker exec "$KONTENER" sh -c 'cat > /var/www/html/udaj-ai-401.php <<"P"
<?php http_response_code( 401 ); header( "Content-Type: application/json" );
echo "{\"error\":{\"type\":\"authentication_error\",\"message\":\"invalid x-api-key\"}}";
P'
docker exec "$KONTENER" sh -c 'cat > /var/www/html/udaj-ai-ok.php <<"P"
<?php header( "Content-Type: application/json" );
echo "{\"content\":[{\"type\":\"text\",\"text\":\"ODPOWIEDZ-Z-UDAWANEGO-AI\"}]}";
P'
# Przełącznik adresu modelu przez filtr wtyczki — to sama wtyczka daje ten filtr
# integratorom (`chatl_ai_url`), więc nie obchodzimy niczego, tylko z niego korzystamy.
docker exec "$KONTENER" sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cat > /var/www/html/wp-content/mu-plugins/probny-ai.php <<"P"
<?php
add_filter( "chatl_ai_url", function ( $adres ) {
	$wlasny = get_option( "probny_ai_url" );
	return $wlasny ? $wlasny : $adres;
} );
P'
docker exec "$KONTENER" chown -R www-data:www-data /var/www/html/wp-content/mu-plugins

# ------------------------------------------------------------------------
naglowek "DOWOD D3 — klucz API odciety W TRAKCIE: zjazd do regul + znacznik"
# ------------------------------------------------------------------------
wyczysc_log
wp option update chatl_klucz_api 'sk-ant-PROBNY-KLUCZ-NIE-JEST-PRAWDZIWY' > /dev/null
wp option update probny_ai_url "http://localhost/udaj-ai-ok.php" > /dev/null
wp option delete chatl_blad_ai > /dev/null 2>&1

# a) najpierw stan BEZ wady: model odpowiada, znacznika NIE MA
AJAX="$ADRES/wp-admin/admin-ajax.php"
# Pytamy DOKLADNIE TAK, JAK PYTA GOSC — przez punkt AJAX, nie funkcja wewnetrzna.
# Zrodlo odpowiedzi bierzemy z kolumny `silnik` w bazie, bo to jest to, co bot
# SAM o sobie zapisal; deklaracja w JSON-ie byloby slowem, nie dowodem.
# Sesji NIE WYMYSLAMY. Wydaje ja serwer (chatl_nowa_sesja), a do zapisu potrzebny
# jest jeszcze sekret z ciastka HttpOnly — pierwsza wersja tej proby podawala
# wlasny identyfikator i dostawala kod `sesja`, czyli mierzyla wlasny blad
# zamiast zachowania bota. Trzymamy ciastka w pliku, dokladnie jak przegladarka.
CIASTKA="$(mktemp)"
# ⛔ PROG PULAPKI NA BOTY. Wtyczka traktuje pytanie zadane szybciej niz
# CHATL_PULAPKA_SEKUND od wydania sesji jako bota: odpowiada tekstem awaryjnym,
# NIE wola modelu i NIE zapisuje ani jednego wiersza rozmowy. Skrypt pytal
# natychmiast po wydaniu sesji, wiec caly dowod D3 mierzyl PULAPKE, a nie tryb AI —
# stad szesc pozycji „nie wyszlo" i dziura D3 zostawiona jako otwarta.
# Prog czytamy Z KODU, nie wpisujemy liczby: zmiana stalej ma przestawic te przerwe
# sama, a nie zostawic kolejna kotwice, ktora zgnije po cichu.
PROG_PULAPKI="$(wp eval 'echo (int) CHATL_PULAPKA_SEKUND;' 2>&1 | tail -1)"
case "$PROG_PULAPKI" in ''|*[!0-9]*) PROG_PULAPKI=2 ;; esac

nowa_sesja() {
	local s
	s="$(curl -s -c "$CIASTKA" -b "$CIASTKA" -X POST "$AJAX" -d "action=chatl_sesja" \
		| sed -n 's/.*"sesja":"\([^"]*\)".*/\1/p')"
	sleep $(( PROG_PULAPKI + 1 ))
	printf '%s' "$s"
}
pytanie_goscia() {
	local sesja="$1"
	local n; n="$(wp eval 'echo wp_create_nonce( "chatl_front" );' 2>&1 | tail -1)"
	curl -s -c "$CIASTKA" -b "$CIASTKA" -X POST "$AJAX" \
		-d "action=chatl_pytanie" -d "nonce=$n" -d "sesja=$sesja" \
		--data-urlencode "pytanie=$PYTANIE"
}
silnik_ostatniej() {
	sql_bazy "SELECT silnik FROM \`$BAZA\`.wp_chatl_rozmowy WHERE sesja='$1' AND rola='bot' ORDER BY id DESC LIMIT 1;" | tr -d '\r'
}
# Diagnostyka PRZED pomiarem: bez niej "bot nie odpowiedzial z modelu" nie
# odroznia wady bota od zle ustawionego stanowiska.
printf '    baza Q&A: %s par · klucz ustawiony: %s · AI dostepne: %s · adres modelu: %s\n' \
	"$(sql_bazy "SELECT COUNT(*) FROM \`$BAZA\`.wp_chatl_qa;" | tr -d '\r')" \
	"$( [ -n "$(opcja chatl_klucz_api)" ] && echo TAK || echo NIE )" \
	"$(wp eval 'echo chatl_ai_dostepny() ? "TAK" : "NIE";' 2>&1 | tail -1)" \
	"$(opcja probny_ai_url)"
printf '    surowa odpowiedz modelu przez wtyczke: [%s]\n' \
	"$(wp eval 'echo (string) chatl_ai_odpowiedz( "ile trwa realizacja", array() );' 2>&1 | tail -1)"
SES_A="$(nowa_sesja)"
ODP_AI="$(pytanie_goscia "$SES_A")"
SILNIK_AI="$(silnik_ostatniej "$SES_A")"
if printf '%s' "$ODP_AI" | grep -q 'ODPOWIEDZ-Z-UDAWANEGO-AI' && [ "$SILNIK_AI" = "ai" ]; then
	potwierdz "przy dzialajacym API odpowiedz idzie z MODELU (silnik zapisany w bazie: $SILNIK_AI)"
else
	zglos "przy dzialajacym API bot nie odpowiedzial z modelu (silnik=$SILNIK_AI): $(printf '%s' "$ODP_AI" | head -c 160)"
fi
if [ -z "$(opcja chatl_blad_ai)" ]; then
	potwierdz "przy dzialajacym API znacznika awarii NIE MA (przypadek bez wady)"
else
	zglos "znacznik awarii jest, choc API odpowiedzialo poprawnie"
fi

# b) ODCIĘCIE: to samo pytanie, ale API od tej chwili odrzuca klucz (401)
wp option update probny_ai_url "http://localhost/udaj-ai-401.php" > /dev/null
SES_B="$(nowa_sesja)"
ODP_PO="$(pytanie_goscia "$SES_B")"
SILNIK_PO="$(silnik_ostatniej "$SES_B")"
if [ "$SILNIK_PO" = "reguly" ] && printf '%s' "$ODP_PO" | grep -q '"success":true'; then
	potwierdz "po odcieciu klucza bot ODPOWIEDZIAL Z REGUL (silnik zapisany w bazie: $SILNIK_PO)"
elif printf '%s' "$ODP_PO" | grep -q 'ODPOWIEDZ-Z-UDAWANEGO-AI'; then
	zglos "po odcieciu klucza bot NADAL zwraca odpowiedz modelu — zjazdu do regul nie ma"
else
	zglos "po odcieciu klucza gosc nie dostal odpowiedzi (silnik=$SILNIK_PO): $(printf '%s' "$ODP_PO" | head -c 160)"
fi

ZNACZNIK="$(opcja chatl_blad_ai)"
if printf '%s' "$ZNACZNIK" | grep -q '"kod":401'; then
	potwierdz "znacznik awarii zapisany z kodem 401: $ZNACZNIK"
else
	zglos "znacznika awarii AI nie ma albo nie niesie kodu 401: [$ZNACZNIK]"
fi
# Znacznik ma być WIDOCZNY w panelu, nie tylko w bazie — inaczej właściciel
# nadal nie wie, że gościom odpowiada sam silnik regułowy.
if wp eval 'require_once WP_PLUGIN_DIR . "/chatbot-live/includes/admin.php"; chatl_notice_bledu_ai();' 2>&1 | grep -q 'Tryb AI nie działa'; then
	potwierdz "panel POKAZUJE wlascicielowi ostrzezenie o nieudanym wywolaniu API"
else
	W_PANELU="$(wp eval 'require_once WP_PLUGIN_DIR . "/chatbot-live/includes/admin.php"; chatl_notice_bledu_ai();' 2>&1 | head -3)"
	zglos "panel nie pokazuje ostrzezenia o awarii AI: [$W_PANELU]"
fi
# Powrót API do sprawności ma znacznik ZDJĄĆ — inaczej ostrzeżenie zostaje na zawsze.
wp option update probny_ai_url "http://localhost/udaj-ai-ok.php" > /dev/null
SES_C="$(nowa_sesja)"
ODP_C="$(pytanie_goscia "$SES_C")"
printf '    powrot API — odpowiedz: [%s] silnik: [%s]\n' "$(printf '%s' "$ODP_C" | head -c 90)" "$(silnik_ostatniej "$SES_C")"
if [ -z "$(opcja chatl_blad_ai)" ]; then
	potwierdz "po powrocie API znacznik zostaje ZDJETY (nie zostaje na zawsze)"
else
	zglos "znacznik awarii zostal, choc API znowu odpowiada"
fi
[ -z "$(log_bledow)" ] && potwierdz "D3 bez ani jednego bledu PHP" || zglos "D3 zostawil bledy PHP: $(log_bledow | head -3)"

# ------------------------------------------------------------------------
naglowek "DOWOD D4 — strona z CACHE: nonce starszy niz zycie (luka L1)"
# ------------------------------------------------------------------------
wyczysc_log
# Od schematu 8 odrzucone nonce'y sa wierszami w chatl_zdarzenia (rodzaj 'nonce'),
# nie licznikiem w opcji chatl_blad_nonce — czyscimy wiec tabele, nie opcje.
wp eval 'global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"nonce\"" );' > /dev/null 2>&1

# a) przypadek BEZ wady: swiezy nonce ma dzialac za pierwszym razem
NONCE_OK="$(wp eval 'echo wp_create_nonce( "chatl_front" );' 2>&1 | tail -1)"
SES_D4="$(nowa_sesja)"
ODP_OK="$(curl -s -c "$CIASTKA" -b "$CIASTKA" -X POST "$AJAX" -d "action=chatl_pytanie" -d "nonce=$NONCE_OK" -d "sesja=$SES_D4" --data-urlencode "pytanie=$PYTANIE")"
if printf '%s' "$ODP_OK" | grep -q '"success":true'; then
	potwierdz "ze SWIEZYM noncem czat odpowiada normalnie (przypadek bez wady)"
else
	zglos "swiezy nonce zostal odrzucony: $(printf '%s' "$ODP_OK" | head -c 160)"
fi

# b) nonce z cache'owanej strony — dla wp_verify_nonce wyglada jak wygasly
ODP_ZLY="$(curl -s -c "$CIASTKA" -b "$CIASTKA" -X POST "$AJAX" -d "action=chatl_pytanie" -d "nonce=0000000000" -d "sesja=$SES_D4" --data-urlencode "pytanie=$PYTANIE")"
if printf '%s' "$ODP_ZLY" | grep -q '"kod":"nonce"'; then
	potwierdz "wygasly nonce daje WLASNY blad z kodem 'nonce', nie gole -1"
else
	zglos "odpowiedz na wygasly nonce nie ma kodu 'nonce': $(printf '%s' "$ODP_ZLY" | head -c 160)"
fi
if printf '%s' "$ODP_ZLY" | grep -qx -- '-1'; then
	zglos "odpowiedz to gole -1 — widget nie ma z czego rozpoznac przyczyny (to jest L1)"
else
	potwierdz "odpowiedz jest JSON-em wtyczki, nie golym -1"
fi
ZDARZEN_NONCE="$(wp eval 'global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"nonce\"" );' 2>&1 | tail -1)"
if [ "${ZDARZEN_NONCE:-0}" -gt 0 ] 2>/dev/null; then
	potwierdz "odrzucony nonce ZOSTAL ODNOTOWANY dla wlasciciela (zdarzen: $ZDARZEN_NONCE)"
else
	zglos "odrzucony nonce nie zostal nigdzie odnotowany — wlasciciel sie nie dowie"
fi

# c) sedno L1: punkt „daj swiezy nonce" MA dzialac BEZ nonce'a
SWIEZY="$(curl -s -c "$CIASTKA" -b "$CIASTKA" -X POST "$AJAX" -d "action=chatl_nonce")"
NOWY="$(printf '%s' "$SWIEZY" | sed -n 's/.*"nonce":"\([^"]*\)".*/\1/p')"
if [ -n "$NOWY" ]; then
	potwierdz "punkt 'daj swiezy nonce' wydal token BEZ nonce'a: ${NOWY:0:6}…"
else
	zglos "punkt 'daj swiezy nonce' nie wydal tokenu: $(printf '%s' "$SWIEZY" | head -c 160)"
fi
# d) i ponowienie z nowym tokenem ma przejsc — to jest cala obietnica dla goscia
ODP_PON="$(curl -s -c "$CIASTKA" -b "$CIASTKA" -X POST "$AJAX" -d "action=chatl_pytanie" -d "nonce=$NOWY" -d "sesja=$SES_D4" --data-urlencode "pytanie=$PYTANIE")"
if printf '%s' "$ODP_PON" | grep -q '"success":true'; then
	potwierdz "ponowienie ze swiezym noncem PRZECHODZI — gosc z cache dostaje odpowiedz"
else
	zglos "ponowienie ze swiezym noncem nie przeszlo: $(printf '%s' "$ODP_PON" | head -c 160)"
fi
[ -z "$(log_bledow)" ] && potwierdz "D4 bez ani jednego bledu PHP" || zglos "D4 zostawil bledy PHP: $(log_bledow | head -3)"

# ------------------------------------------------------------------------
naglowek "DOWOD D6 — retencja NAPRAWDE kasuje (wpis sprzed 91 dni)"
# ------------------------------------------------------------------------
wyczysc_log
DNI="$(wp eval 'echo CHATL_RETENCJA_DNI;' 2>&1 | tail -1)"
printf '    retencja z kodu: %s dni (stala CHATL_RETENCJA_DNI)\n' "$DNI"
# Wpis STARY (91 dni) ma zniknąć, wpis ŚWIEŻY (1 dzień) ma przeżyć. Drugi jest
# tu po to, żeby „naprawa" polegająca na skasowaniu całej tabeli nie świeciła
# na zielono.
wp eval '
global $wpdb; $t = $wpdb->prefix . "chatl_rozmowy";
$wpdb->insert( $t, array( "sesja" => "proba-d6-stara",  "rola" => "gosc", "tresc" => "PROBA-RETENCJA-STARA",
	"utworzono" => gmdate( "Y-m-d H:i:s", time() - 91 * DAY_IN_SECONDS ) ) );
$wpdb->insert( $t, array( "sesja" => "proba-d6-swieza", "rola" => "gosc", "tresc" => "PROBA-RETENCJA-SWIEZA",
	"utworzono" => gmdate( "Y-m-d H:i:s", time() - 1 * DAY_IN_SECONDS ) ) );
' > /dev/null 2>&1

PRZED_S="$(sql_bazy "SELECT COUNT(*) FROM \`$BAZA\`.wp_chatl_rozmowy WHERE sesja='proba-d6-stara';"  | tr -d '\r')"
PRZED_N="$(sql_bazy "SELECT COUNT(*) FROM \`$BAZA\`.wp_chatl_rozmowy WHERE sesja='proba-d6-swieza';" | tr -d '\r')"
printf '    przed sprzataniem — stary: %s · swiezy: %s\n' "$PRZED_S" "$PRZED_N"
if [ "$PRZED_S" = "1" ] && [ "$PRZED_N" = "1" ]; then
	potwierdz "oba wpisy podlozone (bez tego dalszy pomiar nie znaczylby nic)"
else
	zglos "nie udalo sie podlozyc wpisow probnych (stary=$PRZED_S swiezy=$PRZED_N)"
fi

wp eval 'chatl_czyszczenie_historii();' > /dev/null 2>&1
PO_S="$(sql_bazy "SELECT COUNT(*) FROM \`$BAZA\`.wp_chatl_rozmowy WHERE sesja='proba-d6-stara';"  | tr -d '\r')"
PO_N="$(sql_bazy "SELECT COUNT(*) FROM \`$BAZA\`.wp_chatl_rozmowy WHERE sesja='proba-d6-swieza';" | tr -d '\r')"
printf '    po sprzataniu   — stary: %s · swiezy: %s\n' "$PO_S" "$PO_N"
[ "$PO_S" = "0" ] && potwierdz "wpis sprzed 91 dni ZNIKNAL (1 → 0)" || zglos "wpis sprzed 91 dni ZOSTAL — retencja nie kasuje"
[ "$PO_N" = "1" ] && potwierdz "wpis sprzed 1 dnia PRZEZYL (przypadek bez wady)" || zglos "retencja skasowala TAKZE swiezy wpis — to utrata danych klienta"
[ -z "$(log_bledow)" ] && potwierdz "D6 bez ani jednego bledu PHP" || zglos "D6 zostawil bledy PHP: $(log_bledow | head -3)"

# ------------------------------------------------------------------------
rm -f "$CIASTKA"
zwin_stos
naglowek "WYNIK"
if [ "$BLEDY" -eq 0 ]; then
	printf 'CHATBOT %s PRZESZEDL WSZYSTKIE TRZY DOWODY ZACHOWANIA (D3, D4, D6).\n' "$(basename "$PACZKA")"
	exit 0
fi
printf 'CHATBOT %s NIE PRZESZEDL — %s pozycji nie wyszlo (szczegoly wyzej).\n' "$(basename "$PACZKA")" "$BLEDY"
exit 1
