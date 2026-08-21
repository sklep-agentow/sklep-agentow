#!/bin/bash
# Proba kontrolna strażnika skrzynki Segregatora.
#
# Sam fakt, że test przechodzi, nie znaczy NIC, dopóki nie wiadomo, czy potrafi
# paść. Ta próba podkłada po jednym celowym błędzie w KAŻDEJ z pięciu ścieżek,
# za które płaci klient, i sprawdza, że `testy/segregator-skrzynka-test.php`
# za każdym razem PADA — i to na właściwym kroku, nie gdziekolwiek.
#
# ⭐ Ta próba złapała już jedną prawdziwą wadę SAMEGO TESTU: pierwsza wersja
#    liczyła błędy w zmiennej z góry pliku, a `wp eval-file` uruchamia plik
#    w ZASIĘGU FUNKCJI — licznik nigdy nie rósł i test kończył się zerem nawet
#    przy dwunastu wypisanych „BLAD". Test, który zawsze przechodzi, jest
#    gorszy niż brak testu, bo daje fałszywy spokój.
#
# Uzycie: bash testy/segregator-skrzynka-proba-kontrolna.sh   (z katalogu repo)
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/segregator-maila
BLEDY=0

wdroz_czysty() {
	docker cp wtyczki/segregator-maila/. "$KONTENER:$CEL/" > /dev/null
	docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
	docker cp testy/segregator-skrzynka-test.php "$KONTENER:/tmp/segm-skrz-test.php" > /dev/null
	docker exec -u www-data "$KONTENER" wp eval-file /tmp/segm-skrz-test.php 2>&1
}

# probuj "opis" "plik" "sed-podkladajacy-blad" "fragment kroku, ktory ma paść"
probuj() {
	local opis="$1" plik="$2" mutacja="$3" krok="$4"
	wdroz_czysty
	docker exec "$KONTENER" sed -i "$mutacja" "$CEL/$plik"
	local wyjscie kod=0
	wyjscie="$(odpal)" || kod=$?
	if [ "$kod" -ne 0 ] && printf '%s' "$wyjscie" | grep -q "BLAD.*$krok"; then
		printf '  OK   straznik PADL na: %-46s (EXIT=%s)\n' "$opis" "$kod"
		printf '%s' "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
	else
		printf '  BLAD straznik NIE zlapal: %-43s (EXIT=%s)\n' "$opis" "$kod"
		printf '%s' "$wyjscie" | grep -E "^  BLAD" | head -2 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

echo "=== PROBA KONTROLNA: czy straznik skrzynki widzi podlozone bledy ==="
echo

# A. POŁĄCZENIE — odmowa przestaje nazywać przyczynę. Klient dostaje wtedy
#    „nie działa" bez jednego słowa o tym, co poprawić w ustawieniach.
probuj "A. odmowa polaczenia bez podania przyczyny" \
	"includes/skrzynka.php" \
	"s/'blad'     => \$klient->blad(),/'blad'     => '',/" \
	"odmowa NAZYWA przyczyne"

# B. PRZEBIEG REGUŁ — silnik przestaje dopasowywać cokolwiek. Wtyczka nadal
#    „działa": łączy się, czyta, raportuje zera. Sprzedajemy segregację, która
#    niczego nie segreguje.
probuj "B. silnik nie dopasowuje ZADNEJ reguly" \
	"includes/silnik.php" \
	"s/^function segm_klasyfikuj_mail( \$mail, \$reguly ) {/function segm_klasyfikuj_mail( \$mail, \$reguly ) {\n\treturn null;/" \
	"regula DOPASOWALA"

# C. TRYB NA SUCHO — przebieg na sucho zaczyna naprawdę ruszać pocztę.
#    To najgorszy możliwy błąd tej wtyczki: obietnica nr 1 ze strony produktu
#    („domyślnie chodzi NA SUCHO i nie rusza ani jednej wiadomości").
probuj "C. tryb NA SUCHO jednak rusza poczte" \
	"includes/skrzynka-przebieg.php" \
	"s/\$na_zywo = segm_przenoszenie_dozwolone();/\$na_zywo = true;/" \
	"DALEJ lezy w folderze zrodlowym"

# D. PRZENOSZENIE — klient IMAP melduje sukces, nie ruszywszy wiadomości.
#    Panel pokazuje „przeniesiono 12", a w skrzynce nie zmienia się nic.
probuj "D. przenoszenie melduje sukces, nie ruszajac maila" \
	"includes/class-segm-imap.php" \
	"s/\tpublic function przenies( \$uid, \$cel ) {/\tpublic function przenies( \$uid, \$cel ) {\n\t\treturn true;/" \
	"JEST w folderze docelowym"

# E. COFNIĘCIE — cofanie liczy cofnięte ruchy, ale nic nie przenosi z powrotem.
#    „Cofnij jednym kliknięciem" jest w instrukcji i na stronie produktu.
probuj "E. cofniecie liczy, ale nie przenosi z powrotem" \
	"includes/skrzynka-przebieg.php" \
	"s/if ( null === \$klient->przenies( \$uid, \$folder_z ) ) {/if ( false ) {/" \
	"WROCILA do folderu zrodlowego"

echo
echo "=== KONTROLA ODWROTNA — czysty kod MA przejsc ==="
wdroz_czysty
kod=0
wyjscie="$(odpal)" || kod=$?
if [ "$kod" -eq 0 ] && printf '%s' "$wyjscie" | grep -q "WSZYSTKIE KROKI PRZESZLY"; then
	printf '  OK   %-56s (EXIT=0)\n' "wtyczka z repo przechodzi wszystkie piec sciezek"
	printf '%s' "$wyjscie" | grep -cE "^  OK" | sed 's/^/         krokow OK: /'
else
	printf '  BLAD %-56s (EXIT=%s)\n' "czysty kod NIE przechodzi — proba nic nie znaczy" "$kod"
	printf '%s' "$wyjscie" | grep -E "^  BLAD" | head -3 | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
fi

wdroz_czysty
echo
if [ "$BLEDY" -eq 0 ]; then
	echo "STRAZNIK SKRZYNKI JEST SKALIBROWANY — kazdy z pieciu podlozonych bledow zlapany, czysty kod przechodzi"
	exit 0
fi
echo "STRAZNIK NIE ZLAPAL $BLEDY PRZYPADK(OW) — nie oddawaj go jako dowodu w tym stanie"
exit 1
