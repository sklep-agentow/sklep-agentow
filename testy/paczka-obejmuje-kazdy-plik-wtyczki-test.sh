#!/bin/bash
# paczka-obejmuje-kazdy-plik-wtyczki-test.sh — czy KAZDY plik wtyczki jedzie w paczce klienta.
#
# PO CO: `boty/buduj-paczki.sh` ma jawna allowliste i twardo odmawia budowy, gdy w katalogu
# wtyczki lezy plik spoza listy albo gdy lista obiecuje plik, ktorego nie ma. Ta kontrola jest
# dobra — ale chodzi WYLACZNIE przy budowie paczki, czyli przy WYDANIU. Nowy plik wtyczki
# siedzial wiec w repo tygodniami i wychodzil dopiero wtedy, gdy budowa odmawiala.
#
# Zmierzone 1.09 na PR #244: `includes/prywatnosc.php` wszedl do wtyczki, `sklep-agentow.php`
# laduje go przez `require_once` BEZWARUNKOWO, a na allowliscie pliku nie bylo. Zadna z dziesieciu
# bramek tego nie widziala. Ta bramka przesuwa te sama kontrole z wydania na pull request.
#
# ⛔ NIE PISZE DRUGIEGO POROWNANIA. Wola `BUDUJ_TYLKO_SPRAWDZ=1 bash boty/buduj-paczki.sh`,
#    czyli kroki 1-2 PRAWDZIWEGO skryptu pakujacego. Drugi, wlasny parser allowlisty
#    rozjechalby sie z oryginalem przy pierwszej zmianie ukladu tablic i swiecilby na zielono.
#
# ⛔ Katalogi wtyczek bierze z DYSKU, nie z zaszytej listy trzech nazw — nowa wtyczka,
#    ktorej nikt nie pakuje, ma tu PASC, a nie zostac po cichu pominieta.
#
# Uzycie: bash testy/paczka-obejmuje-kazdy-plik-wtyczki-test.sh
# Kody:   0 = kazdy plik kazdej wtyczki jest na allowliscie · 1 = wada · 2 = blad przyrzadu
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || { echo "PRZYRZAD: nie umiem wejsc do korzenia repo" >&2; exit 2; }

[ -f boty/buduj-paczki.sh ] || { echo "PRZYRZAD: brak boty/buduj-paczki.sh" >&2; exit 2; }
[ -d wtyczki ] || { echo "PRZYRZAD: brak katalogu wtyczki/" >&2; exit 2; }

BLEDY=0
SPRAWDZONYCH=0
PRZYRZAD=0

# Wlasne komunikaty skryptu pakujacego, po ktorych POZNAJE, ze to WADA allowlisty,
# a nie awaria przyrzadu. Wszystko inne = „nie wiem”, a „nie wiem” nie ma prawa
# wygladac ani jak czysto, ani jak wada.
WZOR_WADY='BLAD: brak pliku z allowlisty|BLAD: w katalogu .* SPOZA allowlisty|BLAD: nieznana wtyczka|BLAD: brak katalogu'

for KAT in wtyczki/*/; do
	[ -d "$KAT" ] || continue
	NAZWA="$(basename "$KAT")"
	SPRAWDZONYCH=$(( SPRAWDZONYCH + 1 ))

	KOD=0
	WY="$( BUDUJ_TYLKO_SPRAWDZ=1 bash boty/buduj-paczki.sh "$NAZWA" 2>&1 )" || KOD=$?

	if [ "$KOD" -eq 0 ]; then
		# ⛔ Sam EXIT=0 to za malo. Gdyby wczesny powrot z trybu tylko-sprawdz zniknal ze
		#    skryptu pakujacego, to wywolanie POSZLOBY DALEJ i zbudowalo paczke — bramka
		#    meldowalaby sukces, robiac przy okazji rzecz, ktorej robic nie ma prawa.
		if printf '%s' "$WY" | grep -q "^SPRAWDZONE: $NAZWA "; then
			printf '  OK   %-20s %s\n' "$NAZWA" "$( printf '%s' "$WY" | sed 's/^SPRAWDZONE: [^ ]* — //' )"
			continue
		fi
		printf '  PRZYRZAD %-16s EXIT=0, ale BRAK potwierdzenia kontroli — tryb BUDUJ_TYLKO_SPRAWDZ nie zadzialal\n' "$NAZWA"
		printf '%s\n' "$WY" | sed 's/^/         /'
		PRZYRZAD=$(( PRZYRZAD + 1 ))
		continue
	fi

	if printf '%s' "$WY" | grep -qE "$WZOR_WADY"; then
		printf '  BLAD %-20s allowlista paczki NIE pokrywa katalogu wtyczki (EXIT=%s)\n' "$NAZWA" "$KOD"
		printf '%s\n' "$WY" | sed 's/^/         /'
		printf '       → dopisz plik do allowlisty w boty/buduj-paczki.sh albo usun go z katalogu wtyczki.\n'
		BLEDY=$(( BLEDY + 1 ))
		continue
	fi

	# Padlo z powodu, ktorego nie umiem nazwac — np. tryb tylko-sprawdz zniknal i skrypt
	# poszedl budowac, a zatrzymal go zamek wydanych paczek. To NIE jest wada allowlisty
	# i nie wolno jej tak zameldowac.
	printf '  PRZYRZAD %-16s EXIT=%s z powodu, ktorego nie umiem odroznic od wady allowlisty\n' "$NAZWA" "$KOD"
	printf '%s\n' "$WY" | sed 's/^/         /'
	PRZYRZAD=$(( PRZYRZAD + 1 ))
done

# ⛔ Petla, ktora nie przeczytala ANI JEDNEJ wtyczki, byla by cicho pusta i skonczyla EXIT=0 —
#    czyli „nie zmierzylam” wygladaloby dokladnie jak „czysto”.
if [ "$SPRAWDZONYCH" -lt 3 ]; then
	echo "PRZYRZAD: przeczytalem $SPRAWDZONYCH katalogow wtyczek, a repo ma trzy produkty — nie mam czego mierzyc" >&2
	exit 2
fi

echo
# Awaria przyrzadu ma pierwszenstwo przed werdyktem o produkcie: nie wolno meldowac
# „czysto” ani „wada” na podstawie pomiaru, ktory sie nie odbyl.
if [ "$PRZYRZAD" -ne 0 ]; then
	echo "PRZYRZAD: $PRZYRZAD wtyczka(-i) bez wiarygodnego pomiaru — patrz wyzej. Nie meldowac ani czysto, ani wady." >&2
	exit 2
fi
if [ "$BLEDY" -eq 0 ]; then
	echo "KAZDY PLIK KAZDEJ WTYCZKI JEST NA ALLOWLISCIE PACZKI — wtyczek sprawdzonych: $SPRAWDZONYCH"
	exit 0
fi
echo "PLIKI POZA PACZKA ALBO MARTWE WPISY NA ALLOWLISCIE: $BLEDY wtyczka(-i) — patrz wyzej"
exit 1
