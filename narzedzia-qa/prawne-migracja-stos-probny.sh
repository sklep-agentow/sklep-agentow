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
#                                        sekcji do strony-szablonu (musi paść przypadek C2).
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
	""|blok-sprzedawcy|sekcje) : ;;
	*) padnij "nieznana proba kontrolna: $WADA (znam: blok-sprzedawcy, sekcje)" ;;
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
if grep -q 'SZABLON' /tmp/prawne-w-przygotowaniu.html; then
	printf '  ⛔ NIE WYSZLO  kupujacy widzi zaslepke SZABLON\n'; KOD=1
else
	printf '  ✅ WYSZLO      zadnej zaslepki SZABLON w odpowiedzi\n'
fi

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
