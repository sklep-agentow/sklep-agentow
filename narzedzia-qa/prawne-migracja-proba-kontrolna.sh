#!/bin/bash
# prawne-migracja-proba-kontrolna.sh — próba kontrolna strażnika F5-F1/F5-F3/F5-F4 (link do regulaminu,
# blok sprzedawcy, kompensacja stron prawnych w migracji) — OPAKOWANIE spustu
# `narzedzia-qa/prawne-migracja-stos-probny.sh`, żeby przelot prób (`*proba-kontrolna*.sh`) ją widział.
#
# Spust sam stawia STOS JEDNORAZOWY (lib-stos-probny, zamek stosu) i ma wbudowane dwie próby kontrolne,
# z których każda psuje DOKŁADNIE JEDEN warunek w kopii kodu WEWNĄTRZ kontenera (drzewo nietknięte).
# Ten plik uruchamia trzy biegi i liczy je trzema licznikami (klasa 138 ściągi):
#   BLEDY    — strażnik NIE zauważył podłożonej wady (wada PRODUKTU pomiaru)   → EXIT 1
#   KOTWICE  — spust nie umiał podłożyć wady / brak warunków (kod 2)             → EXIT 2
#   ODWROTNA — czysty kod nie przechodzi (przebieg niewiarygodny)               → EXIT 2
# EXIT 0 = czysty kod przechodzi, obie wady złapane.
# Wymaga Dockera i obrazu WP — na wykonawcy bez stosu WP przelot pomija ją JAWNIE (WYMAGA_DOCKERA).
set -u
cd "$(dirname "$0")/.." || exit 2
SPUST=narzedzia-qa/prawne-migracja-stos-probny.sh
[ -f "$SPUST" ] || { echo "brak $SPUST" >&2; exit 2; }
command -v docker > /dev/null 2>&1 || { echo "brak dockera — nie zmierzono" >&2; exit 2; }

BLEDY=0; KOTWICE=0; ODWROTNA=0
bieg() { # nazwa, oczekiwany kod, argumenty...
	local nazwa="$1" ocz="$2"; shift 2
	local kod
	bash "$SPUST" "$@" > /tmp/prawne-migracja-proba.$$.txt 2>&1; kod=$?
	if [ "$kod" -eq 2 ]; then
		echo "  KOTWICA  $nazwa — spust oddal 2 (brak warunkow / wada nie weszla)"; KOTWICE=$((KOTWICE+1))
		grep -m3 -iE 'padnij|brak|nie umiem|kotwic' /tmp/prawne-migracja-proba.$$.txt | sed 's/^/           /'
	elif [ "$kod" -eq "$ocz" ]; then
		echo "  OK       $nazwa (EXIT=$kod)"
	else
		echo "  ZLE      $nazwa — EXIT=$kod, mialo byc $ocz"
		grep -m3 -E 'ZLE|WYNIK' /tmp/prawne-migracja-proba.$$.txt | sed 's/^/           /'
		if [ "$nazwa" = "czysty kod" ]; then ODWROTNA=$((ODWROTNA+1)); else BLEDY=$((BLEDY+1)); fi
	fi
	rm -f /tmp/prawne-migracja-proba.$$.txt
}

echo "=== CZYSTY KOD — straznik ma PRZEJSC ==="
bieg "czysty kod" 0
echo "=== WADY W KOPII W KONTENERZE — straznik ma PASC (spust odwraca kod: 0 = zlapana) ==="
bieg "wada: blok sprzedawcy wyciety z formularza" 0 --proba-kontrolna blok-sprzedawcy
bieg "wada: krok (b) kompensacji (dopisywanie sekcji) wylaczony" 0 --proba-kontrolna sekcje

echo
if [ "$ODWROTNA" -gt 0 ]; then
	echo "PRAWNE-MIGRACJA-PROBA: kontrola odwrotna PADLA (czysty kod nie przechodzi) — licznik BLEDY=$BLEDY jest NIEWIARYGODNY i NIE jest werdyktem o produkcie. EXIT=2"; exit 2
fi
if [ "$KOTWICE" -gt 0 ]; then
	echo "PRAWNE-MIGRACJA-PROBA: $KOTWICE bieg(i) NIE ZMIERZONE (spust oddal 2). EXIT=2"; exit 2
fi
if [ "$BLEDY" -gt 0 ]; then
	echo "PRAWNE-MIGRACJA-PROBA: STRAZNIK SLEPY NA $BLEDY wad(y). EXIT=1"; exit 1
fi
echo "PRAWNE-MIGRACJA-PROBA: czysty kod przechodzi, 2/2 wady zlapane. EXIT=0"; exit 0
