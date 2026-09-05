#!/bin/bash
# Spust strażnika F5-F1/F5-F3/F5-F4 na STOSIE PRÓBNYM od zera (nigdy na wspólnym stanowisku).
#
# Użycie: bash narzedzia-qa/prawne-migracja-stos-probny.sh [--proba-kontrolna <wada>]
#   EXIT 0 = strażnik widzi wszystkie sytuacje · 1 = któraś nie wyszła · 2 = brak warunków.
#
# ⛔ DLACZEGO STOS PRÓBNY, A NIE WSPÓLNY: żeby zmierzyć strażnika, trzeba mieć na stosie kod
#    Z GAŁĘZI. Wgranie go na wspólne stanowisko `strona_wp_wordpress` cofa robotę innym biegom
#    (zmierzone 1.09: stos cofnięty z 0.9.32 na 0.9.31). Tu stawiamy własny kontener i kasujemy
#    go po sobie; zamek stosu bierze `postaw_stos` z `lib-stos-probny.sh`.
#
# PRÓBA KONTROLNA — wada podkładana WYŁĄCZNIE w kopii kodu WEWNĄTRZ kontenera (drzewo robocze
# zostaje nietknięte), każda psuje DOKŁADNIE JEDEN warunek:
#   --proba-kontrolna blok-sprzedawcy  → wycina wywołanie bloku sprzedawcy w formularzu zakupu
#                                        (musi paść przypadek B);
#   --proba-kontrolna sekcje           → wyłącza krok (b) kompensacji: dopisywanie brakujących
#                                        sekcji do strony-szablonu (musi paść przypadek C2);
#   --proba-kontrolna podmiana-wlasny  → podmiana zdań wchodzi także we WŁASNY dokument właściciela
#                                        (musi paść przypadek C5d);
#   --proba-kontrolna front            → zdejmuje bramę „nie na froncie” (musi paść pomiar HTTP: front robi pracę).
# (Lista mutantów = case niżej; nagłówek i case mają się zgadzać — #299 r1 W3, klasa 8.)
# Przy każdej z nich spust ODWRACA znaczenie kodu wyjścia: EXIT 0 = wada ZOSTAŁA ZŁAPANA.
set -u

cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }

WTYCZKA_ZRODLO=wtyczki/sklep-agentow
TEST=testy/prawne-link-blok-migracja-test.php
WADA=""
while [ "$#" -gt 0 ]; do
	case "$1" in
		--proba-kontrolna) WADA="${2:-}"; shift 2 ;;
		--zostaw) ZOSTAW=tak; shift ;;
		*) echo "nieznany argument: $1" >&2; exit 2 ;;
	esac
done
[ -d "$WTYCZKA_ZRODLO" ] || padnij "nie ma katalogu $WTYCZKA_ZRODLO"
[ -f "$TEST" ]           || padnij "nie ma $TEST"
case "$WADA" in
	""|blok-sprzedawcy|sekcje|podmiana-wlasny|front) : ;;
	*) padnij "nieznana proba kontrolna: $WADA (znam: blok-sprzedawcy, sekcje, podmiana-wlasny, front)" ;;
esac

wymagaj_narzedzi
trap zwin_stos EXIT
postaw_stos

naglowek "WGRANIE KODU Z DRZEWA (nie z paczki — mierzymy to, co w gałęzi)"
docker cp "$WTYCZKA_ZRODLO" "$KONTENER:/var/www/html/wp-content/plugins/sklep-agentow" > /dev/null \
	|| padnij "nie umiem wgrac wtyczki do kontenera"
docker cp "$TEST" "$KONTENER:/var/www/html/prawne-migracja-test.php" > /dev/null \
	|| padnij "nie umiem wgrac testu do kontenera"

if [ -n "$WADA" ]; then
	naglowek "PROBA KONTROLNA — podkladam wade '$WADA' w KOPII w kontenerze"
	case "$WADA" in
		blok-sprzedawcy)
			docker exec "$KONTENER" sh -c "sed -i \"/sklepag-sprzedawca-przed-zakupem/,+1d\" /var/www/html/wp-content/plugins/sklep-agentow/includes/front-produkt.php" \
				|| padnij "nie umiem podlozyc wady blok-sprzedawcy"
			docker exec "$KONTENER" sh -c "grep -c sklepag-sprzedawca-przed-zakupem /var/www/html/wp-content/plugins/sklep-agentow/includes/front-produkt.php" \
				| grep -qx 0 || padnij "wada blok-sprzedawcy NIE zostala podlozona (wywolanie nadal w pliku)"
			;;
		sekcje)
			docker exec "$KONTENER" sh -c "sed -i \"s/if ( \\\$jest_szablonem ) {/if ( false \\&\\& \\\$jest_szablonem ) {/\" /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php" \
				|| padnij "nie umiem podlozyc wady sekcje"
			docker exec "$KONTENER" sh -c "grep -c 'if ( false && \$jest_szablonem )' /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php" \
				| grep -qx 1 || padnij "wada sekcje NIE zostala podlozona (warunek niezmieniony)"
			;;
		podmiana-wlasny)
			# r4 W1: podmiana zdan wchodzi w KAZDYM dokumencie, takze we wlasnym dokumencie wlasciciela (brama szablonu wycieta)
			docker exec "$KONTENER" sh -c "sed -i \"s/if ( \\\$jest_szablonem \\&\\& '' !== \\\$tresc_biezaca ) {/if ( '' !== \\\$tresc_biezaca ) {/\" /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php" \
				|| padnij "nie umiem podlozyc wady podmiana-wlasny"
			docker exec "$KONTENER" sh -c "grep -c \"if ( '' !== \\\$tresc_biezaca ) {\" /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php" \
				| grep -qx 1 || padnij "wada podmiana-wlasny NIE zostala podlozona (warunek niezmieniony)"
			;;
		front)
			# r4 W2: brama frontu wycieta — zadanie goscia (front) wykonuje kompensacje i podbija znacznik
			docker exec "$KONTENER" sh -c "sed -i \"s/if ( ! is_admin() \\&\\& ! wp_doing_cron()/if ( false \\&\\& ! is_admin() \\&\\& ! wp_doing_cron()/\" /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php" \
				|| padnij "nie umiem podlozyc wady front"
			docker exec "$KONTENER" sh -c "grep -c 'if ( false && ! is_admin()' /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php" \
				| grep -qx 1 || padnij "wada front NIE zostala podlozona (warunek niezmieniony)"
			;;
	esac
	docker exec "$KONTENER" php -l /var/www/html/wp-content/plugins/sklep-agentow/includes/front-produkt.php > /dev/null \
		|| padnij "kopia z wada nie parsuje sie — proba kontrolna mierzylaby blad skladni, nie brak funkcji"
	docker exec "$KONTENER" php -l /var/www/html/wp-content/plugins/sklep-agentow/includes/aktywacja.php > /dev/null \
		|| padnij "kopia z wada nie parsuje sie — proba kontrolna mierzylaby blad skladni, nie brak funkcji"
fi

wp plugin activate sklep-agentow > /dev/null 2>&1 || padnij "wtyczka nie chce sie aktywowac"
WERSJA="$(docker exec "$KONTENER" sh -c "grep -m1 'Version:' /var/www/html/wp-content/plugins/sklep-agentow/sklep-agentow.php" | tr -d '\r')"
printf '  wgrana wtyczka: %s\n' "${WERSJA# * }"

KOD=0

naglowek "STRONA 'W PRZYGOTOWANIU' PO HTTP (F5-F1: link nigdy nie prowadzi w 404)"
# ⛔ To musi iść ŻĄDANIEM, nie lekturą: funkcja konczy sie `exit`, wiec pod `wp eval-file`
#    nie da sie jej zmierzyc bez zabicia calego testu. Kod odpowiedzi i tresc czytamy z sieci.
ODP="$(curl -s -o /tmp/prawne-w-przygotowaniu.html -w '%{http_code}' "$ADRES/?sklepag_prawne=regulamin")"
printf '  HTTP %s dla %s\n' "$ODP" "$ADRES/?sklepag_prawne=regulamin"
if [ "$ODP" = 200 ]; then
	printf '  ✅ WYSZLO      kod odpowiedzi 200 (nie 404)\n'
else
	printf '  ⛔ NIE WYSZLO  kupujacy dostaje %s zamiast 200\n' "$ODP"; KOD=1
fi
if grep -q 'w przygotowaniu' /tmp/prawne-w-przygotowaniu.html; then
	printf '  ✅ WYSZLO      tresc mowi po polsku, ze dokument jest w przygotowaniu\n'
else
	printf '  ⛔ NIE WYSZLO  brak zdania o przygotowaniu; poczatek odpowiedzi:\n'; head -c 200 /tmp/prawne-w-przygotowaniu.html; echo; KOD=1
fi
# #294 r5 W2 / r7: zaślepka ma noindex i NIE odpowiada pod cudzym adresem (kotwica na ścieżce strony głównej).
if grep -q 'name="robots" content="noindex' /tmp/prawne-w-przygotowaniu.html; then
	printf '  ✅ WYSZLO      zaslepka ma meta robots noindex\n'
else
	printf '  ⛔ NIE WYSZLO  zaslepka bez noindex — robot zaindeksuje „w przygotowaniu"\n'; KOD=1
fi
ODP_CUDZY="$(curl -s -o /tmp/prawne-cudzy-adres.html -w '%{http_code}' "$ADRES/dowolny-wpis-ktorego-nie-ma/?sklepag_prawne=regulamin")"
# Sciezka z sekwencja procentowa (`/%2F` = zakodowany ukosnik): kotwica porownuje SUROWA sciezke i odrzuca kazda z '%'.
ODP_PROC="$(curl -s -o /tmp/prawne-procent-adres.html -w '%{http_code}' "$ADRES/%2F?sklepag_prawne=regulamin")"
if grep -q 'w przygotowaniu' /tmp/prawne-procent-adres.html; then
	printf '  ⛔ NIE WYSZLO  adres z sekwencja procentowa /%%2F (HTTP %s) dostal zaslepke — kotwice da sie obejsc kodowaniem\n' "$ODP_PROC"; KOD=1
else
	printf '  ✅ WYSZLO      adres z sekwencja procentowa /%%2F nie dostaje zaslepki (HTTP %s)\n' "$ODP_PROC"
fi
if grep -q 'w przygotowaniu' /tmp/prawne-cudzy-adres.html; then
	printf '  ⛔ NIE WYSZLO  cudzy adres (%s) dostal zaslepke — duplikat pod kazdym adresem sklepu\n' "$ODP_CUDZY"; KOD=1
else
	printf '  ✅ WYSZLO      cudzy adres nie dostaje zaslepki (HTTP %s, bez tresci „w przygotowaniu")\n' "$ODP_CUDZY"
fi
if grep -q 'SZABLON' /tmp/prawne-w-przygotowaniu.html; then
	printf '  ⛔ NIE WYSZLO  kupujacy widzi zaslepke SZABLON\n'; KOD=1
else
	printf '  ✅ WYSZLO      zadnej zaslepki SZABLON w odpowiedzi\n'
fi

naglowek "BRAMA FRONTU (F5-F4, r4 W2): zadanie GOSCIA nie wykonuje kompensacji stron prawnych"
# ⛔ WP-Cron WYLACZONY na czas pomiaru: zadanie z frontu spawnuje wp-cron.php (petla zwrotna), a cron jest DOZWOLONA
#    droga kompensacji — bez tego pomiar nie odroznial frontu od crona (5.09: znacznik = 2 po GET goscia, przez cron).
wp config set DISABLE_WP_CRON true --raw > /dev/null 2>&1 || padnij "nie umiem wylaczyc WP-Cron w stosie probnym (wp config set)"
wp config get DISABLE_WP_CRON 2>/dev/null | grep -qx 1 || padnij "DISABLE_WP_CRON nie jest ustawione — pomiar frontu bylby zanieczyszczony cronem"
# Kompensacja wisi na plugins_loaded, wiec front ja WOLA — ma wrocic na bramie (praca tylko z panelu, crona, WP-CLI).
# Pomiar ZACHOWANIA, nie lektury: znacznik skasowany → GET goscia na front → znacznik ma zostac NIEUSTAWIONY;
# potem kontrola dodatnia: to samo z WP-CLI USTAWIA znacznik (inaczej „nieustawiony” nic by nie znaczyl).
# ⛔ --skip-plugins: KAZDE wywolanie wp-cli laduje wtyczke, a WP-CLI jest DOZWOLONA droga kompensacji — zwykle
#    `wp option get` samo ustawialo znacznik, ktory mialo odczytac (5.09: przyrzad mierzyl siebie). Czytamy i kasujemy
#    znacznik BEZ wtyczek; kontrola dodatnia nizej wola kompensacje jawnie (`wp eval`, z wtyczkami).
wp option delete sklepag_wersja_stron --skip-plugins > /dev/null 2>&1 || true
# WARUNEK WSTEPNY pomiaru: znacznik musi byc NIEUSTAWIONY zanim front go dotknie — inaczej „ustawiony po froncie”
# byloby stanem sprzed pomiaru, nie skutkiem frontu (kotwica, nie werdykt).
ZN_PRZED="$(wp option get sklepag_wersja_stron --skip-plugins 2>/dev/null | tr -d '\r')"
case "$ZN_PRZED" in
	""|*"Could not get"*|*"Does not exist"*) : ;;
	*) padnij "znacznik sklepag_wersja_stron nie zszedl po 'wp option delete' (= $ZN_PRZED) — pomiar frontu nie ma warunku wstepnego" ;;
esac
curl -s -o /dev/null "$ADRES/"; curl -s -o /dev/null "$ADRES/?sklepag_prawne=regulamin"
ZN_FRONT="$(wp option get sklepag_wersja_stron --skip-plugins 2>/dev/null | tr -d '\r')"
case "$ZN_FRONT" in
	""|*"Could not get"*|*"Does not exist"*)
		printf '  ✅ WYSZLO      po dwoch zadaniach z frontu znacznik sklepag_wersja_stron NIEUSTAWIONY (front nie robi pracy)\n' ;;
	*)
		printf '  ⛔ NIE WYSZLO  zadanie z frontu wykonalo kompensacje: znacznik = %s\n' "$ZN_FRONT"; KOD=1 ;;
esac
wp eval 'sklepag_kompensuj_strony_prawne();' > /dev/null 2>&1 || true
ZN_CLI="$(wp option get sklepag_wersja_stron --skip-plugins 2>/dev/null | tr -d '\r')"
case "$ZN_CLI" in
	[1-9]*) printf '  ✅ WYSZLO      kontrola dodatnia: to samo z WP-CLI USTAWIA znacznik (= %s)\n' "$ZN_CLI" ;;
	*)      printf '  ⛔ NIE WYSZLO  kontrola dodatnia: WP-CLI nie ustawil znacznika (%s) — pomiar frontu bylby pusty\n' "$ZN_CLI"; KOD=1 ;;
esac

naglowek "STRAZNIK LINK + BLOK SPRZEDAWCY + KOMPENSACJA MIGRACJI"
WYJ="$(docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/prawne-migracja-test.php 2>&1)"
KOD_TESTU=$?
printf '%s\n' "$WYJ"
[ "$KOD_TESTU" = 0 ] || KOD=1

naglowek "LOG BLEDOW PHP (notice/warning psuja panel wlasciciela)"
NOTICE="$(docker exec "$KONTENER" sh -c 'cat /var/www/html/debug.log 2>/dev/null' | grep -cE 'PHP (Notice|Warning|Fatal|Deprecated)')"
printf '  wpisow PHP w debug.log: %s\n' "$NOTICE"
[ "$NOTICE" = 0 ] || { printf '  ⛔ log NIE jest pusty:\n'; docker exec "$KONTENER" sh -c 'tail -5 /var/www/html/debug.log'; KOD=1; }

if [ -n "$WADA" ]; then
	naglowek "WERDYKT PROBY KONTROLNEJ ($WADA)"
	if [ "$KOD" = 0 ]; then
		printf '  ⛔ STRAZNIK NIE ZOBACZYL PODLOZONEJ WADY — test nie mierzy tego, co obiecuje.\n'
		printf '\nPROBA KONTROLNA %s: WADA NIEZLAPANA, EXIT=1\n' "$WADA"
		exit 1
	fi
	printf '  ✅ wada zlapana (straznik padl z kodem 1) — pomiar jest zywy.\n'
	printf '\nPROBA KONTROLNA %s: WADA ZLAPANA, EXIT=0\n' "$WADA"
	exit 0
fi

printf '\nSPUST PRAWNE+MIGRACJA: EXIT=%s\n' "$KOD"
exit "$KOD"
