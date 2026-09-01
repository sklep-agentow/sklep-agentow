#!/bin/bash
# Proba kontrolna straznika idempotencji — KALIBRACJA OD ZERA, nie przepisana z #199.
#
# ⛔ DLACZEGO OD ZERA. Straznik `testy/idem-nie-oddaje-oplaconej-sesji-test.php` skladal klucz
# okna U SIEBIE, drugi raz obok produkcji. Gdy 0.9.25 doloczylo do klucza trzeci czlon (tryb
# platnosci: osadzona/hostowana), straznik zaczal podkladac okno pod adresem, ktorego handler
# nigdy nie czytal. Od tamtej pory NIE MIERZYL NICZEGO — a kalibracja z PR #199 opisywala
# zachowanie sprzed rozjazdu, wiec przestala byc dowodem czegokolwiek.
#
# CO ROBI: bierze wtyczke z commita SPRZED naprawy 0.9.18 (`13b45c1^`), wdraza ja na stos
# i wymaga, zeby naprawiony straznik na niej PADL. Potem wdraza kod z drzewa i wymaga zieleni.
#
# ⭐ SZYM ARCHEOLOGICZNY: kod sprzed 0.9.18 nie ma `sklepag_klucz_idem()` — sklada klucz w linii
# uzycia jako `md5( IP|produkt )` (dwa czlony; trzeci doszedl dopiero w 0.9.25). Doklejamy do
# KOPII tamtej wtyczki funkcje oddajaca DOKLADNIE ten klucz. Szym niczego nie zmienia
# w zachowaniu starego kodu — tylko UDOSTEPNIA klucz, ktory tamten kod i tak liczy u siebie.
# Bez niego straznik padlby na braku funkcji, czyli z powodu przyrzadu, a nie z powodu wady.
#
# ⛔ Licznik bledow poza podpowloka. Kody: 0 = kalibracja przeszla · 1 = straznik slepy · 2 = przyrzad.
set -u
KONTENER=strona_wp_wordpress
CEL=/var/www/html/wp-content/plugins/sklep-agentow
STARY=13b45c1^          # ostatni commit PRZED naprawa SK-L3-005 (0.9.18, PR #199)
BLEDY=0
ROBOCZY=$(mktemp -d -t idem-proba-XXXXXX)
trap 'rm -rf "$ROBOCZY"' EXIT INT TERM

cd "$(dirname "$0")/.." || { echo "PRZYRZAD: nie umiem wejsc do korzenia repo" >&2; exit 2; }

wdroz() { # $1 = katalog ze wtyczka
  docker cp "$1/." "$KONTENER:$CEL/" >/dev/null || return 1
  docker exec "$KONTENER" chown -R www-data:www-data "$CEL"
}
odpal() {
  docker cp testy/idem-nie-oddaje-oplaconej-sesji-test.php "$KONTENER:/tmp/idem.php" >/dev/null
  docker exec -u www-data "$KONTENER" wp eval-file /tmp/idem.php 2>&1
}

echo "== KOD SPRZED NAPRAWY 0.9.18 ($STARY) — straznik MA PADAC =="
mkdir -p "$ROBOCZY/stary"
if ! git archive "$STARY" wtyczki/sklep-agentow 2>/dev/null | tar -x -C "$ROBOCZY/stary"; then
  echo "PRZYRZAD: nie umiem wyciagnac wtyczki z $STARY" >&2; exit 2
fi
STARA_WTYCZKA="$ROBOCZY/stary/wtyczki/sklep-agentow"
if ! grep -q "md5( sklepag_ip() . '|' . \$produkt_id )" "$STARA_WTYCZKA/includes/zakup.php"; then
  echo "PRZYRZAD: w kodzie z $STARY nie ma spodziewanego klucza dwuczlonowego — szym bylby zgadywaniem" >&2
  exit 2
fi
cat >> "$STARA_WTYCZKA/includes/zakup.php" <<'PHP'

/* SZYM PROBY KONTROLNEJ — oddaje DOKLADNIE ten klucz, ktory ten (stary) kod sklada w linii
   uzycia. Nie zmienia zachowania; istnieje po to, zeby straznik padl na WADZIE, a nie na
   braku funkcji wprowadzonej dopiero w 0.9.33. */
function sklepag_klucz_idem( $produkt_id, $osadzona ) {
	return 'sklepag_idem_' . md5( sklepag_ip() . '|' . (int) $produkt_id );
}
PHP
wdroz "$STARA_WTYCZKA" || { echo "PRZYRZAD: nie udalo sie wdrozyc starej wtyczki" >&2; exit 2; }
WY=$(odpal); KOD=$?
printf '%s\n' "$WY" | sed 's/^/    | /'
if [ "$KOD" -eq 2 ]; then
  echo "PRZYRZAD: straznik oddal 2 (srodowisko) — kalibracja nic nie zmierzyla" >&2
  BLEDY=$((BLEDY+1))
elif [ "$KOD" -eq 1 ] \
  && printf '%s' "$WY" | grep -q 'WYNIK=FAIL - nie powstal nowy checkout (oddal oplacona sesje' \
  && printf '%s' "$WY" | grep -q 'podwojny klik nie tworzy drugiej platnosci)$' ; then
  # ⛔ Nie wystarczy „cos padlo". Wymagamy DOKLADNIE tego ksztaltu: pada przypadek A (stary kod
  # oddaje OPLACONA sesje) i JEDNOCZESNIE przechodzi B (ochrona przed podwojnym klikiem istniala
  # juz wtedy). Sam czerwony wynik moglby znaczyc, ze zepsulismy sciezke zakupu w ogole.
  echo "  OK   straznik PADL na kodzie sprzed naprawy (EXIT=1): A pada, B nadal przechodzi"
else
  echo "  BLAD straznik NIE PADL na kodzie sprzed naprawy (EXIT=$KOD) — nie mierzy tej wady"
  BLEDY=$((BLEDY+1))
fi

echo
echo "== KOD Z DRZEWA — straznik MA PRZEJSC =="
wdroz wtyczki/sklep-agentow || { echo "PRZYRZAD: nie udalo sie wdrozyc biezacej wtyczki" >&2; exit 2; }
WY2=$(odpal); KOD2=$?
printf '%s\n' "$WY2" | sed 's/^/    | /'
if [ "$KOD2" -eq 0 ]; then
  echo "  OK   czysty kod EXIT=0"
else
  echo "  BLAD czysty kod EXIT=$KOD2"
  BLEDY=$((BLEDY+1))
fi

echo
echo "BLEDY PROBY KONTROLNEJ: $BLEDY"
exit $((BLEDY > 0 ? 1 : 0))
