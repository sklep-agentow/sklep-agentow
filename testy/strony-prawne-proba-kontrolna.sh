#!/bin/bash
# Kalibracja strażnika stron prawnych: każdy mutant psuje DOKŁADNIE JEDEN warunek i strażnik MUSI paść.
#
# Użycie: bash testy/strony-prawne-proba-kontrolna.sh
#   EXIT 0 = strażnik złapał wszystkie mutanty i przepuścił czysty kod · 1 = ślepy na którąś wadę · 2 = brak warunków.
#
# ⛔ MUTANT IDZIE DO KOPII W KATALOGU TYMCZASOWYM, NIGDY DO REPO ANI NA WSPÓLNY STOS.
#    Stos próbny stawiamy RAZ i podmieniamy w nim sam plik panelu — inaczej sześć instalacji
#    od zera to sześć minut czekania zamiast kilkunastu sekund.
# ⛔ Zieleń strażnika bez tej kalibracji nic nie znaczy: strażnik, który nie umie paść,
#    świeci na zielono także wtedy, gdy naprawy nie ma wcale.
set -u

cd "$(dirname "$0")/.." || exit 2
# shellcheck source=narzedzia-qa/lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh || { echo "brak narzedzia-qa/lib-stos-probny.sh" >&2; exit 2; }

ZRODLO=wtyczki/sklep-agentow
PANEL="$ZRODLO/includes/admin.php"
TEST=testy/strony-prawne-ostrzezenie-test.php
CEL=/var/www/html/wp-content/plugins/sklep-agentow
[ -f "$PANEL" ] || padnij "nie ma $PANEL"
[ -f "$TEST" ]  || padnij "nie ma $TEST"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"; docker rm -f -v "$KONTENER" > /dev/null 2>&1 || true' EXIT

wymagaj_narzedzi
postaw_stos
docker cp "$ZRODLO" "$KONTENER:$CEL" > /dev/null || padnij "nie umiem wgrac wtyczki"
docker cp "$TEST" "$KONTENER:/var/www/html/proba.php" > /dev/null || padnij "nie umiem wgrac testu"
wp plugin activate sklep-agentow > /dev/null 2>&1 || padnij "wtyczka nie chce sie aktywowac"

BLEDY=0

# $1 = etykieta, $2 = oczekiwany kod (0 = ma przejsc, 1 = ma PASC), $3 = plik panelu do wgrania
zmierz() {
	docker cp "$3" "$KONTENER:$CEL/includes/admin.php" > /dev/null 2>&1 || { zglos "$1 — nie wgralam pliku"; return; }
	docker exec "$KONTENER" wp --allow-root --path=/var/www/html eval-file /var/www/html/proba.php > "$TMP/wyj.txt" 2>&1
	local kod=$?
	if [ "$kod" -eq "$2" ]; then
		potwierdz "$1 (EXIT=$kod, oczekiwane $2)"
	else
		zglos "$1 — EXIT=$kod, a mialo byc $2"
		grep -m2 '  ZLE\|PROBA:' "$TMP/wyj.txt" | sed 's/^/         /'
	fi
}

naglowek "CZYSTY KOD — straznik ma PRZEJSC"
cp "$PANEL" "$TMP/czysty.php"
zmierz "czysty kod" 0 "$TMP/czysty.php"

naglowek "MUTANTY — kazdy psuje DOKLADNIE JEDEN warunek, straznik ma PASC"

# 1. ostrzezenie zdefiniowane, ale NIEWPIETE — dokladnie ta wada, ktora naprawiamy
sed "s/^add_action( 'admin_notices', 'sklepag_ostrzez_strony_prawne' );$//" "$PANEL" > "$TMP/m1.php"
[ "$(cmp -s "$PANEL" "$TMP/m1.php"; echo $?)" -ne 0 ] || padnij "mutant 1 nie zmienil pliku — wzorzec nie trafil"
zmierz "M1: funkcja istnieje, ale nikt jej nie wola" 1 "$TMP/m1.php"

# 2. nie patrzy na tresc — opublikowany, niewypelniony szablon uchodzi za gotowy
sed "s/|| false !== strpos( \$wpis->post_content, 'SZABLON' ) ) {/) {/" "$PANEL" > "$TMP/m2.php"
[ "$(cmp -s "$PANEL" "$TMP/m2.php"; echo $?)" -ne 0 ] || padnij "mutant 2 nie zmienil pliku — wzorzec nie trafil"
zmierz "M2: slepy na zaslepke w tresci opublikowanej strony" 1 "$TMP/m2.php"

# 3. brak kontroli uprawnien — ostrzezenie panelu wyciekaloby poza panel
sed "s/if ( ! current_user_can( 'manage_options' ) ) {/if ( false ) {/" "$PANEL" > "$TMP/m3.php"
[ "$(cmp -s "$PANEL" "$TMP/m3.php"; echo $?)" -ne 0 ] || padnij "mutant 3 nie zmienil pliku — wzorzec nie trafil"
zmierz "M3: brak sprawdzenia uprawnien" 1 "$TMP/m3.php"

# 4. pouczanie o stronie skasowanej do kosza
sed "s/if ( ! \$wpis || 'trash' === \$wpis->post_status ) {/if ( ! \$wpis ) {/" "$PANEL" > "$TMP/m4.php"
[ "$(cmp -s "$PANEL" "$TMP/m4.php"; echo $?)" -ne 0 ] || padnij "mutant 4 nie zmienil pliku — wzorzec nie trafil"
zmierz "M4: poucza o stronie wyrzuconej do kosza" 1 "$TMP/m4.php"

# 5. krzyczy zawsze — takze na dokumenty gotowe
sed "s/^		\$powod = '';$/		\$powod = 'nieopublikowana';/" "$PANEL" > "$TMP/m5.php"
[ "$(cmp -s "$PANEL" "$TMP/m5.php"; echo $?)" -ne 0 ] || padnij "mutant 5 nie zmienil pliku — wzorzec nie trafil"
zmierz "M5: krzyczy takze na gotowe dokumenty" 1 "$TMP/m5.php"

# 6. komunikat WRACA do stanu sprzed rundy 2: jedno zdanie o wersji roboczej dla KAZDEGO powodu
#    (recenzja #278 WAZNA 1 — opis stanu, w ktorym strona nie jest). Powod przestaje byc mierzony.
sed "s|\. '</a> ('  *\. esc_html( \$powod ) \. ')'|. '</a>'|" "$PANEL" > "$TMP/m6.php"
[ "$(cmp -s "$PANEL" "$TMP/m6.php"; echo $?)" -ne 0 ] || padnij "mutant 6 nie zmienil pliku — wzorzec nie trafil"
zmierz "M6: komunikat bez ZMIERZONEGO powodu przy stronie" 1 "$TMP/m6.php"

# 7. zdanie koncowe WRACA do przelacznika po CALEJ liscie: obecnosc jednej strony roboczej
#    kaze „opublikowac te strony” takze o stronie opublikowanej z zaslepka (recenzja #278, WAZNA 1
#    rundy 5 — galaz mieszana nie miala ani przypadku, ani mutanta).
sed "s/if ( \$ile_roboczych > 0 \&\& \$ile_zaslepek > 0 ) {/if ( false ) {/" "$PANEL" > "$TMP/m7.php"
[ "$(cmp -s "$PANEL" "$TMP/m7.php"; echo $?)" -ne 0 ] || padnij "mutant 7 nie zmienil pliku — wzorzec nie trafil"
zmierz "M7: stan mieszany znowu kaze publikowac strone opublikowana" 1 "$TMP/m7.php"

naglowek "POWROT DO CZYSTEGO KODU — zielone musi wrocic"
zmierz "czysty kod po mutantach" 0 "$TMP/czysty.php"

printf '\n'
if [ "$BLEDY" -eq 0 ]; then
	# ⛔ Liczba NIE na sztywno: '5/5' zostalo po dolozeniu M6 i klamalo o wlasnym przebiegu
	#    (dokladnie ta klasa bledu, ktora ta kalibracja tropi). Zrodlo: wywolania `zmierz "M...`.
	MUTANTOW=$(grep -c '^zmierz "M' "$0")
	printf 'KALIBRACJA STRAZNIKA STRON PRAWNYCH: %s/%s mutantow zlapanych, czysty kod 2x zielony (EXIT=0)\n' "$MUTANTOW" "$MUTANTOW"
	exit 0
fi
printf 'KALIBRACJA: %s NIE WYSZLO (EXIT=1)\n' "$BLEDY"
exit 1
