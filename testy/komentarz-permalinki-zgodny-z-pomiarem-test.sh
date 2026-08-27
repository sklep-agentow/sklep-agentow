#!/bin/bash
# STRAŻNIK — SK-L-005 (komentarz w kodzie kłamał o zachowaniu webhooka).
#
# Zmierzone na stosie (audyt/sklep/testy-audytora/B/L/l04-permalink-plain-webhook-silent-200-test.sh):
# przy odnośnikach „Zwykłe" żądanie na /wp-json/sklepag/v1/webhook NIE trafia do wtyczki —
# WordPress oddaje stronę główną motywu z kodem 200. Komentarz w includes/admin.php twierdził,
# że jest to 404 (rest_no_route). Samego zachowania WordPressa wtyczka nie zmieni; wadą był
# OPIS niezgodny z pomiarem, bo prowadził diagnozę w złą stronę.
#
# Ten strażnik pilnuje, żeby opis nie wrócił do nieprawdziwej wersji.
#
# PASS (exit 0) = komentarz mówi o kodzie 200 i nie twierdzi już, że trasa zwraca 404.
# FAIL (exit 1) = wrócił opis niezgodny z pomiarem.
set -u
PLIK="${1:-wtyczki/sklep-agentow/includes/admin.php}"
[ -f "$PLIK" ] || { echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak $PLIK)"; exit 2; }

BLOK=$(sed -n '/Świeży WordPress ma bezpośrednie odnośniki/,/^function sklepag_ostrzez_permalinki/p' "$PLIK")
[ -n "$BLOK" ] || { echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (nie znaleziono opisu ostrzeżenia)"; exit 2; }

KLAMIE=0
printf '%s' "$BLOK" | grep -q "zwraca 404" && KLAMIE=1
MOWI_200=0
printf '%s' "$BLOK" | grep -q "kodem 200" && MOWI_200=1

echo "STRAZNIK: opis_twierdzi_404=$( [ "$KLAMIE" = 1 ] && echo TAK || echo NIE ) (OCZEKIWANE: NIE)"
echo "STRAZNIK: opis_podaje_zmierzone_200=$( [ "$MOWI_200" = 1 ] && echo TAK || echo NIE ) (OCZEKIWANE: TAK)"

if [ "$KLAMIE" = 0 ] && [ "$MOWI_200" = 1 ]; then
  echo "STRAZNIK: WYNIK=PASS"; exit 0
fi
echo "STRAZNIK: WYNIK=FAIL - opis zachowania webhooka niezgodny z pomiarem"
exit 1
