#!/usr/bin/env bash
# Formularz OPINII kupującego oddaje napisaną treść po odrzucie walidacji.
#
# ⛔ DLACZEGO SĘDZIĄ JEST POWŁOKA, A NIE PLIK PHP: obie ścieżki tego formularza — zapis
#    i render — kończą się `exit` wewnątrz handlera. Pod `wp eval-file` znaczy to dwie
#    rzeczy naraz: asercje postawione PO wywołaniu nigdy się nie wykonają, a kod wyjścia
#    i tak będzie ZEROWY. Test napisany w jednym pliku PHP świeciłby wtedy na zielono,
#    nie wykonawszy połowy sprawdzeń (złapane na sobie 27.08). Dlatego kroki PHP tylko
#    DRUKUJĄ, a werdykt liczy ten skrypt — z treści, nie z kodu wyjścia kroków.
#
# Sprawdza (klasa: odrzut nie kasuje treści napisanej przez człowieka):
#   1. odrzut odkłada treść, ocenę i tytuł — ale NIE zgodę na publikację,
#   2. powrót z `blad=1` oddaje treść znak w znak, ze zgodą NIEZAZNACZONĄ,
#   3. przechowalnia jest jednorazowa,
#   4. wejście BEZ `blad=1` NIE nakłada porzuconego szkicu.
#
# Użycie: bash testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh
# Kody wyjścia: 0 = wszystko przeszło · 1 = wada · 2 = błąd przyrządu.
set -u

cd "$(dirname "$0")/.." || { echo "PRZYRZAD: nie umiem wejsc do korzenia repo" >&2; exit 2; }

BLEDY=0
ok()  { printf '  OK   %s\n' "$1"; }
zle() { printf '  BLAD %s\n' "$1"; BLEDY=$(( BLEDY + 1 )); }

TRESC='Bot odpisuje moim klientom w nocy i to jest cala roznica.'
KONTROLNA='PORZUCONA TRESC KONTROLNA'

echo "=== opinia-formularz-trzyma-tresc-po-odrzucie — krok 1: odrzut ==="
K1="$(bash narzedzia-qa/test-php.sh opinia-odrzut-krok1 2>&1)" || true
printf '%s\n' "$K1" | grep -q 'KROK1: zasiano opinie' || { echo "PRZYRZAD: krok 1 nie zasial danych"; printf '%s\n' "$K1" | tail -5; exit 2; }
printf '%s\n' "$K1" | grep -q 'Prawie się udało' \
	&& ok 'odrzut pokazal kupujacemu strone powrotu' \
	|| zle 'odrzut NIE pokazal strony powrotu (zmienila sie sciezka walidacji?)'
printf '%s\n' "$K1" | grep -q 'blad=1' \
	&& ok 'link powrotny niesie znacznik powrotu po bledzie' \
	|| zle 'link powrotny BEZ blad=1 — szkic nie zostanie nalozony'

echo "=== krok 2: przechowalnia i powrot na formularz ==="
K2="$(bash narzedzia-qa/test-php.sh opinia-odrzut-krok2 2>&1)" || true
LICZ_OK="$(printf '%s\n' "$K2" | grep -c 'KROK2-OK:')"
LICZ_BLAD="$(printf '%s\n' "$K2" | grep -c 'KROK2-BLAD:')"
[ "$LICZ_OK" = "4" ] && ok "wszystkie 4 sprawdzenia przechowalni zdane" || zle "przechowalnia: OK=$LICZ_OK BLAD=$LICZ_BLAD (oczekiwano 4 i 0)"
[ "$LICZ_BLAD" = "0" ] || printf '%s\n' "$K2" | grep 'KROK2-BLAD:' | sed 's/^/       /'

# ⛔ Próba kontrolna przyrządu: jeśli w wyjściu nie ma w ogóle formularza, to nie znaczy
#    „brak treści" tylko „krok nie doszedł do renderu" — rozstrzygamy to osobno.
printf '%s\n' "$K2" | grep -q '<<<FORMULARZ' || { echo "PRZYRZAD: krok 2 nie doszedl do renderu formularza"; exit 2; }
printf '%s\n' "$K2" | grep -q 'name="sklepag_tresc"' || { echo "PRZYRZAD: render nie zawiera pola tresci — to nie ten formularz"; exit 2; }

printf '%s\n' "$K2" | grep -q "$TRESC" \
	&& ok 'formularz powrotny ODDAJE napisana tresc' \
	|| zle 'formularz powrotny NIE oddaje tresci (kupujacy pisze od nowa)'
printf '%s\n' "$K2" | grep -q 'name="sklepag_tytul" maxlength="150" value="Warto"' \
	&& ok 'formularz powrotny oddaje tytul' \
	|| zle 'formularz powrotny gubi tytul'
printf '%s\n' "$K2" | grep -q 'value="4" checked' \
	&& ok 'formularz powrotny trzyma wybrana ocene' \
	|| zle 'formularz powrotny gubi ocene (wraca domyslna piatka)'
printf '%s\n' "$K2" | grep -q 'name="sklepag_zgoda" value="1" required> ' \
	&& ok 'zgoda wraca NIEZAZNACZONA (nowy gest wymagany)' \
	|| zle 'zgoda wraca zaznaczona albo zniknela z formularza'

echo "=== krok 3: jednorazowosc, wejscie bez bledu, sprzatanie ==="
K3="$(bash narzedzia-qa/test-php.sh opinia-odrzut-krok3 2>&1)" || true
printf '%s\n' "$K3" | grep -q 'KROK3-OK: przechowalnia skasowana' \
	&& ok 'przechowalnia jest jednorazowa' \
	|| zle 'przechowalnia przezyla odczyt — szkic wrocilby drugi raz'
printf '%s\n' "$K3" | grep -q '<<<FORMULARZ-BEZ-BLEDU' || { echo "PRZYRZAD: krok 3 nie doszedl do renderu"; exit 2; }
printf '%s\n' "$K3" | grep -q "$KONTROLNA" \
	&& zle 'wejscie BEZ blad=1 nalozylo porzucony szkic (wlasciciel opinii patrzylby na cudzy stan)' \
	|| ok 'wejscie BEZ blad=1 NIE naklada porzuconego szkicu'
printf '%s\n' "$K3" | grep -q 'KROK3: posprzatane' \
	&& ok 'przebieg posprzatal po sobie' \
	|| zle 'przebieg NIE posprzatal — zostaja wiersze testowe'

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "WYNIK: OK — formularz opinii oddaje tresc po odrzucie, a porzucony szkic nie wraca."
	exit 0
fi
echo "WYNIK: BLEDY = $BLEDY"
exit 1
