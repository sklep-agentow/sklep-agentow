#!/bin/bash
# Proba kontrolna straznika MIME Segregatora (`testy/segregator-mime-test.php`).
#
# Sam fakt, ze test przechodzi, nie znaczy NIC, dopoki nie wiadomo, czy potrafi
# pasc. Ta proba podklada po jednym celowym bledzie w KAZDEJ z czterech drog
# znaleziska A7c-F1 (plus piata mutacje na naglowki czesci) i sprawdza, ze test
# za kazdym razem PADA — i to na wlasciwej asercji, nie gdziekolwiek.
#
# ⛔ Mutant idzie do KOPII wtyczki w katalogu tymczasowym, nigdy do repo i nigdy
#    na wspolny stos `strona_wp_wordpress` (klasa 141: `docker cp` kodu na
#    wspolny stos cofa robote drugiemu builderowi, a test mierzy wtedy kod
#    KONTENERA, nie worktree). Test czyta sciezke ze zmiennej
#    SEGM_KATALOG_WTYCZKI, wiec caly przebieg jest w czystym PHP CLI.
#
# ⛔ TRZY LICZNIKI, nie jeden (klasa 138): BLEDY = straznik nie zlapal mutanta
#    (wada PRODUKTU, EXIT=1) · KOTWICE = mutacja w ogole nie weszla, bo kotwica
#    `sed` zgnila (wada PRZYRZADU, EXIT=2) · ODWROTNA = czysty kod nie przechodzi
#    (wada WARUNKOW WSTEPNYCH, EXIT=2, sprawdzana PIERWSZA).
#
# ⛔ Liczby czytamy z MIERZONEGO (`grep -c` po wyjsciu testu), zadna nie jest
#    wpisana na sztywno (klasa 142).
#
# Uzycie: bash testy/segregator-mime-proba-kontrolna.sh   (z katalogu repo)
set -u

ZRODLO="wtyczki/segregator-maila/includes"
TEST="testy/segregator-mime-test.php"
ROBOCZY="$(mktemp -d)"
BLEDY=0
KOTWICE=0
ODWROTNA=0

sprzataj() {
	# `rm -rf` wylacznie na katalogu z mktemp, nigdy na sciezce z repo.
	# shellcheck disable=SC2317  # wolane z trap EXIT
	case "$ROBOCZY" in
		/tmp/*) rm -rf "$ROBOCZY" ;;
	esac
}
trap sprzataj EXIT

podsumowanie_proby() {           # $1 = komunikat, gdy wszystko zmierzone i zielone
	# ⛔ KOLEJNOSC MA ZNACZENIE. Padnieta kontrola odwrotna UNIEWAZNIA caly przebieg:
	#    straznik pada wtedy ZAWSZE, wiec „nie zlapal mutanta” nic nie znaczy i licznik
	#    BLEDY jest smieciem.
	if [ "$ODWROTNA" -gt 0 ]; then
		echo "KONTROLA ODWROTNA PADLA ($ODWROTNA) — czysty kod NIE przechodzi, wiec proba NIC nie znaczy."
		echo "  Licznik BLEDY=$BLEDY jest NIEWIARYGODNY i NIE jest werdyktem o produkcie."
		echo "  Sprawdz warunki wstepne (PHP CLI z mbstring, komplet plikow wtyczki), potem powtorz."
		exit 2
	fi
	if [ "$KOTWICE" -gt 0 ]; then
		echo "PROBA NIC NIE ZMIERZYLA W $KOTWICE PRZYPADKACH — kotwica sed zgnila po refaktorze."
		echo "  To wada PRZYRZADU, nie straznika: zanim oskarzysz produkt, uruchom straznika BEZPOSREDNIO:"
		echo "    php $TEST"
		exit 2
	fi
	if [ "$BLEDY" -gt 0 ]; then
		echo "STRAZNIK SLEPY NA $BLEDY PODLOZONYCH BLEDOW"
		exit 1
	fi
	echo "$1"
	exit 0
}

wgraj_czysty() {
	rm -f "$ROBOCZY"/*.php 2>/dev/null
	cp "$ZRODLO"/*.php "$ROBOCZY/"
}

# probuj "opis" "kotwica (dokladny fragment)" "wyrazenie sed" "fragment asercji, ktora ma paść"
probuj() {
	local opis="$1" kotwica="$2" mutacja="$3" asercja="$4"
	wgraj_czysty
	local przed po wyjscie kod=0 ile

	if [ "$(grep -cF -- "$kotwica" "$ROBOCZY/skrzynka.php")" -eq 0 ]; then
		printf '  KOTW mutacja NIE weszla (kotwica nie ma w co trafic): %s\n' "$opis"
		printf '         szukane: %s\n' "$kotwica"
		KOTWICE=$(( KOTWICE + 1 ))
		return
	fi

	przed="$(cksum < "$ROBOCZY/skrzynka.php")"
	sed -i "$mutacja" "$ROBOCZY/skrzynka.php"
	po="$(cksum < "$ROBOCZY/skrzynka.php")"
	if [ "$przed" = "$po" ]; then
		printf '  KOTW mutacja NIE weszla (plik bez zmian): %s\n' "$opis"
		KOTWICE=$(( KOTWICE + 1 ))
		return
	fi

	wyjscie="$( SEGM_KATALOG_WTYCZKI="$ROBOCZY" php "$TEST" 2>&1 )" || kod=$?
	ile="$( printf '%s\n' "$wyjscie" | grep -c '^  BLAD' )"
	if [ "$kod" -ne 0 ] && printf '%s\n' "$wyjscie" | grep -q "BLAD.*$asercja"; then
		printf '  OK   straznik PADL na: %-52s (EXIT=%s, padnietych asercji: %s)\n' "$opis" "$kod" "$ile"
		printf '%s\n' "$wyjscie" | grep "BLAD.*$asercja" | head -1 | sed 's/^/         /'
	else
		printf '  BLAD straznik NIE zlapal: %-49s (EXIT=%s, padnietych asercji: %s)\n' "$opis" "$kod" "$ile"
		printf '%s\n' "$wyjscie" | grep '^  BLAD' | head -2 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

echo "=== PROBA KONTROLNA: czy straznik MIME widzi podlozone bledy (A7c-F1) ==="
echo

# DROGA 1 — mail wieloczesciowy przestaje byc rozbierany na czesci, wiec do
# reguly wraca surowe MIME: granice, naglowki czesci i base64.
probuj "1. multipart NIE jest rozbierany na czesci" \
	"0 === strpos( \$glowny, 'multipart/' )" \
	"s/0 === strpos( \$glowny, 'multipart\/' )/false/" \
	"multipart/alternative, czesc tekstowa base64"

# DROGA 1b — czesci sa rozbierane, ale ich NAGLOWKI wracaja do tekstu. To druga
# strona tej samej wady: regula „tresc zawiera charset" lapalaby kazdy mail.
probuj "1b. naglowki czesci trafiaja do tekstu" \
	"return array( substr( \$czesc, 0, \$koniec ), substr( \$czesc, \$koniec ) );" \
	"s/return array( substr( \$czesc, 0, \$koniec ), substr( \$czesc, \$koniec ) );/return array( '', \$czesc );/" \
	"naglowki MIME NIE trafiaja do tekstu"

# DROGA 2 — koniec base64 obcinany BEZWARUNKOWO, tak jak robil to stary
# `array_pop()`: krotki mail traci cala tresc.
probuj "2. koniec base64 obcinany bezwarunkowo" \
	"\$reszta = strlen( \$czysty ) % 4;" \
	"s/\$reszta = strlen( \$czysty ) % 4;/\$czysty = substr( \$czysty, 0, max( 0, strlen( \$czysty ) - 4 ) ); \$reszta = strlen( \$czysty ) % 4;/" \
	"base64 w JEDNEJ linii bez CRLF nie znika"

# DROGA 3 — biala lista zestawow znakow znow bierze `mb_list_encodings()`,
# w ktorej siedza kodowania TRANSPORTOWE (BASE64, QUOTED-PRINTABLE, 7BIT).
probuj "3. lista zestawow znow z mb_list_encodings()" \
	"function segm_skrzynka_zestawy_znakow() {" \
	"s/function segm_skrzynka_zestawy_znakow() {/function segm_skrzynka_zestawy_znakow() {\n\treturn array_map( 'strtoupper', mb_list_encodings() );/" \
	"charset=BASE64"

# DROGA 4 — tekst zwykly znow przechodzi przez `strip_tags()`, wiec
# niedomkniety `<` przed litera zjada reszte zdania.
probuj "4. text/plain znow przez wp_strip_all_tags()" \
	"'tekst'  => trim( \$tekst )," \
	"s/'tekst'  => trim( \$tekst ),/'tekst'  => wp_strip_all_tags( \$tekst ),/" \
	"zostaje w calosci"

echo
echo "=== KONTROLA ODWROTNA — czysty kod MA przejsc ==="
wgraj_czysty
kod=0
wyjscie="$( SEGM_KATALOG_WTYCZKI="$ROBOCZY" php "$TEST" 2>&1 )" || kod=$?
ile_ok="$( printf '%s\n' "$wyjscie" | grep -c '^  OK' )"
if [ "$kod" -eq 0 ] && printf '%s\n' "$wyjscie" | grep -q 'WSZYSTKIE ASERCJE PRZESZLY'; then
	printf '  OK   %-56s (EXIT=0, asercji OK: %s)\n' "wtyczka z repo przechodzi wszystkie asercje" "$ile_ok"
else
	printf '  BLAD %-56s (EXIT=%s)\n' "czysty kod NIE przechodzi — proba nic nie znaczy" "$kod"
	printf '%s\n' "$wyjscie" | grep '^  BLAD' | head -3 | sed 's/^/         /'
	ODWROTNA=$(( ODWROTNA + 1 ))
fi

echo
podsumowanie_proby "STRAZNIK MIME JEST SKALIBROWANY — kazdy z pieciu podlozonych bledow zlapany, czysty kod przechodzi"
