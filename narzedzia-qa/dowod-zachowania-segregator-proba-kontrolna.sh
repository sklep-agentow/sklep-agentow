#!/bin/bash
# dowod-zachowania-segregator-proba-kontrolna.sh — czy dowod zachowania cokolwiek ODRZUCA.
#
# Dowod, ktory zawsze wychodzi, nie jest dowodem. Ten skrypt buduje z PRAWDZIWEJ
# paczki jej uszkodzone kopie i sprawdza, czy `dowod-zachowania-segregator.sh`
# je odrzuca — a czysta paczka nadal przechodzi.
#
# Mutanty celuja w OBIETNICE SPRZEDAZOWE, nie w losowe linijki:
#   M1  tryb NA SUCHO przestaje chronic poczte (obietnica "nie rusza ani jednej wiadomosci"),
#   M2  cofniecie nic nie cofa (obietnica "da sie cofnac"),
#   M3  retencja kasuje WSZYSTKO, nie tylko starsze niz prog (naprawa, ktora zabiera dane).
#
# ⛔ Kazdy mutant jest sprawdzany dwa razy: czy WSZEDL do pliku (kotwice gnija po
#    refaktorze) i czy dowod PADL Z WLASCIWEGO POWODU, a nie przypadkiem.
#
# Kody wyjscia: 0 = wszystkie mutanty odrzucone i czysta paczka przechodzi
#               1 = ktorys mutant przeszedl albo czysta paczka padla
#               2 = brak narzedzi / brak paczki
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
DOWOD="$KORZEN/narzedzia-qa/dowod-zachowania-segregator.sh"
PACZKA="${1:-boty/paczki/segregator-maila-1.3.0.zip}"
[ -f "$PACZKA" ] || { echo "PROBA: nie ma paczki [$PACZKA]" >&2; exit 2; }
command -v unzip > /dev/null && command -v zip > /dev/null || { echo "PROBA: brak zip/unzip" >&2; exit 2; }

BLEDY=0
KOTWICE=0
ODWROTNA=0
# ⛔ KLASA 138: TRZY rozne rzeczy = TRZY liczniki. Wspolny licznik kaze skryptowi orzec
#    o PRODUKCIE rzecz, ktorej NIE ZMIERZYL.
#      BLEDY    straznik nie zlapal mutanta  -> EXIT=1, wada produktu
#      KOTWICE  kotwica sed zgnila           -> EXIT=2, proba nic nie zmierzyla
#      ODWROTNA czysty kod nie przechodzi    -> EXIT=2, proba nic nie znaczy
podsumowanie_proby() {           # $1 = komunikat, gdy wszystko zmierzone i zielone
  # ⛔ KOLEJNOSC: padnieta kontrola odwrotna UNIEWAZNIA przebieg (straznik pada zawsze),
  #    wiec sprawdzana jest PIERWSZA, a licznik BLEDY nie jest wtedy werdyktem.
  if [ "$ODWROTNA" -gt 0 ]; then
    echo "KONTROLA ODWROTNA PADLA ($ODWROTNA) — czysty kod NIE przechodzi, wiec proba NIC nie znaczy."
    echo "  Licznik BLEDY=$BLEDY jest NIEWIARYGODNY i NIE jest werdyktem o produkcie."
    echo "  Sprawdz warunki wstepne (zaleznosci, stos, dane logowania), potem powtorz."
    exit 2
  fi
  if [ "$KOTWICE" -gt 0 ]; then
    echo "PROBA NIC NIE ZMIERZYLA W $KOTWICE PRZYPADKACH — kotwica sed zgnila po refaktorze."
    echo "  To wada PRZYRZADU, nie straznika: zanim oskarzysz produkt, uruchom straznika BEZPOSREDNIO."
    exit 2
  fi
  if [ "$BLEDY" -gt 0 ]; then
    echo "$2"
    exit 1
  fi
  echo "$1"
  exit 0
}
BAZA="$(mktemp -d)"
trap 'rm -rf "$BAZA"' EXIT

# zmutuj <nazwa> <plik-w-wtyczce> <polecenie-sed> <wzorzec-potwierdzajacy>
zmutuj() {
	local nazwa="$1" plik="$2" polecenie="$3" wzorzec="$4"
	local kat="$BAZA/$nazwa"
	rm -rf "$kat"; mkdir -p "$kat"
	( cd "$kat" && unzip -q "$KORZEN/$PACZKA" )
	sed -i "$polecenie" "$kat/segregator-maila/$plik"
	# ⛔ Diagnostyka na STDERR i ZERO liczenia bledow tutaj: ta funkcja biegnie
	# w PODPOWLOCE (`$(zmutuj …)`), wiec zwiekszony tu licznik ginie razem z nia,
	# a komunikat wypisany na stdout wpadlby do zmiennej z nazwa zipa zamiast na
	# ekran. Pierwsza wersja robila oba te bledy naraz: zgnila kotwica konczyla
	# sie CISZA i wynikiem EXIT=0 — czyli proba kontrolna klamala dokladnie tak,
	# jak mial klamac mutant, ktorego mialaby zlapac.
	if ! grep -q "$wzorzec" "$kat/segregator-maila/$plik"; then
		printf 'kotwica [%s] nie pasuje do pliku %s — nic nie podlozylam\n' "$nazwa" "$plik" >&2
		return 1
	fi
	( cd "$kat" && zip -qr "$BAZA/$nazwa.zip" segregator-maila )
	printf '%s' "$BAZA/$nazwa.zip"
}

# sprawdz <opis> <zip> <oczekiwany-kod> <fraza>
sprawdz() {
	local opis="$1" zip="$2" oczekiwany="$3" fraza="${4:-}" wyjscie kod=0
	wyjscie="$( bash "$DOWOD" "$zip" 2>&1 )" || kod=$?
	if [ "$kod" -ne "$oczekiwany" ]; then
		printf '  BLAD %-58s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany"
		printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO|WYSZLO|WYNIK' | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	if [ -n "$fraza" ] && ! printf '%s' "$wyjscie" | grep -qF "$fraza"; then
		printf '  BLAD %-58s (kod OK, ale padl z INNEGO powodu)\n' "$opis"
		printf '         szukalam: %s\n' "$fraza"
		printf '%s\n' "$wyjscie" | grep -E 'NIE WYSZLO' | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	printf '  OK   %-58s (EXIT=%s)\n' "$opis" "$kod"
}

# Zamyka mutanta i dowod w jednym kroku, ale W POWLOCE GLOWNEJ — dzieki temu
# „kotwica zgnila" jest bledem PROBY, a nie cisza.
mutuj_i_sprawdz() {
	local nazwa="$1" plik="$2" polecenie="$3" wzorzec="$4" opis="$5" fraza="$6" zip
	if ! zip="$(zmutuj "$nazwa" "$plik" "$polecenie" "$wzorzec")"; then
		printf '  BLAD %-58s (mutacja NIE weszla — kotwica zgnila)\n' "$opis"
		KOTWICE=$((KOTWICE+1))
		return
	fi
	sprawdz "$opis" "$zip" 1 "$fraza"
}

# ⛔ NAJPIERW SPRAWDZAM SAMA PROBE. Proba kontrolna, ktora przy zgnilej kotwicy
# konczy sie CISZA i zerem, jest gorsza niz jej brak: mowi „skalibrowane", nie
# sprawdziwszy niczego. Ten przypadek podklada kotwice, ktorej w pliku NIE MA,
# i wymaga, zeby `zmutuj` odmowil KODEM WYJSCIA — nie komunikatem, nie cisza.
# Jest tani (sam sed na rozpakowanej kopii, zero dockera), wiec biegnie zawsze.
echo "=== KONTROLA SAMEJ PROBY — zgnila kotwica MA byc bledem, nie cisza ==="
KOD_ZGNILEJ=0
zmutuj kontrola-zgnilej-kotwicy includes/skrzynka-przebieg.php \
	's|$na_zywo = TEJ_LINII_JUZ_NIE_MA();|$na_zywo = true; // MUTANT|' 'MUTANT' \
	> /dev/null 2>&1 || KOD_ZGNILEJ=$?
if [ "$KOD_ZGNILEJ" -ne 0 ]; then
	printf '  OK   %-58s (EXIT=%s)\n' "kotwica, ktorej nie ma w pliku, jest odrzucana" "$KOD_ZGNILEJ"
else
	printf '  BLAD %-58s (EXIT=0 — proba MILCZY)\n' "kotwica, ktorej nie ma w pliku, przeszla"
	BLEDY=$(( BLEDY + 1 ))
fi

echo
echo "=== MUTANTY — dowod MA PASC ==="

# ⛔ ZAKOTWICZONE WZORCEM, nie numerem linii. Pierwsza wersja podmieniala CALA
# linie 145 (`145s/.*/…/`) — takie podstawienie udaje sie ZAWSZE, wiec kontrola
# „czy mutacja weszla" (grep MUTANT) zawsze mowila TAK. Po refaktorze, gdy prawdziwe
# przypisanie `$na_zywo` przesunie sie na inna linie, podlozylibysmy mutanta
# w PRZYPADKOWYM miejscu, dowod padlby z innego powodu, a my odhaczylibysmy
# kalibracje jako zdana — nie sprawdziwszy tego, co chcielismy sprawdzic.
# Przy wzorcu brak dopasowania = brak podstawienia = grep lapie zgnila kotwice.
mutuj_i_sprawdz m1-na-sucho-nie-chroni includes/skrzynka-przebieg.php \
	's|$na_zywo = segm_przenoszenie_dozwolone();|$na_zywo = true; // MUTANT|' 'MUTANT' \
	"tryb NA SUCHO rusza poczte mimo obietnicy" "przebieg na sucho ZMIENIL skrzynke"

# ⛔ Bez `a\` i bez ukosnika jako separatora: obie te rzeczy wymagaja w tym miejscu
# potrojnego cytowania i pierwsza wersja tej mutacji rozjezdzala sie w podstawieniu —
# plik wychodzil uszkodzony, dowod padal z kodem 2 (stos nie wstal), czyli probowal
# co innego, niz mial probowac.
mutuj_i_sprawdz m2-cofniecie-nic-nie-robi includes/skrzynka-przebieg.php \
	's|^function segm_skrzynka_cofnij( $id_przebiegu ) {|& return array( "cofnietych" => 0, "przywroconych" => 0, "bledow" => 0, "blad" => "" ); // MUTANT|' 'MUTANT' \
	"cofniecie nic nie cofa" "po cofnieciu INBOX ma"

mutuj_i_sprawdz m3-retencja-kasuje-wszystko segregator-maila.php \
	's/- SEGM_RETENCJA_DNI \* DAY_IN_SECONDS/+ SEGM_RETENCJA_DNI * DAY_IN_SECONDS \/\/ MUTANT/' 'MUTANT' \
	"retencja kasuje takze SWIEZE wpisy" "retencja ruszyla wpis SWIEZSZY"

echo
echo "=== KONTROLA ODWROTNA — czysta paczka MA PRZEJSC ==="
sprawdz "paczka z repozytorium, bez zmian" "$KORZEN/$PACZKA" 0

echo
podsumowanie_proby "DOWOD ZACHOWANIA ODRZUCA KAZDA PODLOZONA WADE — i przepuszcza czysta paczke" \
	"DOWOD ZACHOWANIA POMYLIL SIE $BLEDY RAZ(Y) — nie oddawaj go w tym stanie"
