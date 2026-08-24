#!/bin/bash
# STRAŻNIK A6-F2: materiał audytowy nie może twierdzić, że istniejące funkcje botów
# nie istnieją. Audytor czyta `audyt/boty/material/02-fakty-srodowiska.md` PRZED
# startem — fałszywa pozycja na liście „TEGO NIE ZGŁASZAJ" buduje mu model produktu
# bez dwóch największych funkcji (IMAP Segregatora, operator ChatBota) i wyprowadza
# audyt w pole (RAPORT-A6-ODWROTNY.md, znalezisko A6-2; omal nie wycięło celów A4/Łamacza).
#
# Użycie: testy/material-audytu-zgodnosc.sh [ścieżka-materiału]   (z katalogu repo)
#   Parametr służy TYLKO kalibracji strażnika (podanie starej wersji pliku → EXIT=1).
#
# ⚠️ ZAKRES (uczciwie, po recenzji PR #168): strażnik sprawdza JEDEN plik materiału
# (02-fakty-srodowiska.md) i TRZY klasy z listy „TEGO NIE ZGŁASZAJ": pozycję o IMAP,
# pozycję o operatorze i zamrożone numery wersji — przez negację sformułowań PLUS
# wymóg wskazania prawdziwych plików (przeredagowana nieprawda bez wskazania pliku
# nadal padnie na sprawdzeniu pozytywnym). NIE czyta pozostałych plików materiału.
# EXIT 0 = te trzy klasy zgodne z kodem, EXIT 1 = materiał kłamie albo odsyła do niebytu.
set -u
MAT="${1:-audyt/boty/material/02-fakty-srodowiska.md}"
IMAP=wtyczki/segregator-maila/includes/class-segm-imap.php
PRZEJECIE=wtyczki/chatbot-live/includes/przejecie.php
BLEDY=0

spr() { # spr "opis" "0|1" (wynik testu: 0 = OK)
  if [ "$2" -eq 0 ]; then printf '  OK   %s\n' "$1"; else printf '  BLAD %s\n' "$1"; BLEDY=$((BLEDY+1)); fi
}

[ -f "$MAT" ] || { echo "BLAD srodowisko: brak $MAT"; exit 1; }

echo "=== A. Skrzynka IMAP Segregatora: kod istnieje, materiał nie przeczy ==="
[ -f "$IMAP" ]; spr "kod IMAP istnieje ($IMAP)" $?
! grep -qiE 'brak podpi[eę]cia (prawdziwej )?skrzynki' "$MAT"; spr "materiał NIE twierdzi, że skrzynki brak" $?
grep -q 'class-segm-imap.php' "$MAT"; spr "materiał wskazuje prawdziwy plik IMAP" $?

echo "=== B. Przejęcie przez operatora w ChatBocie: kod istnieje, materiał nie przeczy ==="
[ -f "$PRZEJECIE" ]; spr "kod przejęcia istnieje ($PRZEJECIE)" $?
! grep -qiE 'brak czatu z [żz]ywym operatorem' "$MAT"; spr "materiał NIE twierdzi, że operatora brak" $?
grep -q 'przejecie.php' "$MAT"; spr "materiał wskazuje prawdziwy plik przejęcia" $?

echo "=== C. Bez zamrozonych numerow wersji (klasa gnicia: 1.2.1-idzie-1.2.2) ==="
! grep -qE 'Wersja wtyczek .?[0-9]+\.[0-9]+\.[0-9]+' "$MAT"; spr "materiał nie przybija wersji paczek na sztywno" $?

echo ""
if [ "$BLEDY" -eq 0 ]; then echo "02-fakty-srodowiska: pozycje IMAP / operator / wersje ZGODNE z kodem (zakres: te trzy klasy)"; exit 0; fi
echo "PORAZEK: $BLEDY — materiał audytowy rozjechany z kodem (A6-F2 wraca)"; exit 1
