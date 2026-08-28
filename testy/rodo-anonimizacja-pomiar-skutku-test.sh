#!/usr/bin/env bash
# Anonimizacja RODO potwierdza usunięcie TYLKO wtedy, gdy dane naprawdę zniknęły.
#
# ⛔ DLACZEGO SĘDZIĄ JEST POWŁOKA, A NIE PLIK PHP: `sklepag_obsluz_anonimizacje()`
#    kończy się `exit` — po przekierowaniu przy powodzeniu, po `wp_die()` przy awarii.
#    Pod `wp eval-file` znaczy to, że asercje postawione PO wywołaniu nigdy się nie
#    wykonają, a kod wyjścia i tak będzie ZEROWY (klasa 17 rejestru). Kroki PHP tylko
#    DRUKUJĄ, werdykt liczy ten skrypt — z treści, nie z kodu wyjścia kroków.
#
# KLASA (23 i 26 rejestru, przyczyna-matka H): zapisujemy skutek operacji, nie
# sprawdziwszy, czy operacja się udała. Kod pisał do dziennika „kupujący
# zanonimizowany" i meldował właścicielowi „ok", nie patrząc na wynik zapisu.
# Przy porażce właściciel odpowiadał człowiekowi, że jego dane usunięto, a e-mail
# zostawał w bazie — niezrealizowane żądanie RODO z fałszywym dowodem wykonania.
#
# Sprawdza:
#   A. ścieżka szczęśliwa — e-mail znika, `usunieto_dnia` jest, token pobrania pusty,
#      zdarzenie `anonimizacja` zapisane, brak `anonimizacja_blad`;
#   B. PODŁOŻONA AWARIA zapisu (filtr `query` psuje dokładnie to jedno zapytanie) —
#      handler NIE potwierdza usunięcia: `wp_die` zamiast przekierowania,
#      zdarzenie `anonimizacja_blad` zamiast `anonimizacja`, e-mail NIETKNIĘTY.
#
# ⭐ KALIBRACJA: na kodzie sprzed naprawy przypadek B PADA — handler przekierowuje
#    z `sklepag_anonimizacja=ok` i zapisuje zdarzenie `anonimizacja`, mimo że dane
#    zostały. To jest cała różnica, którą ten test mierzy.
#
# Użycie: bash testy/rodo-anonimizacja-pomiar-skutku-test.sh [kontener]
# Kody wyjścia: 0 = przeszło · 1 = wada · 2 = błąd przyrządu.
set -u

cd "$(dirname "$0")/.." || { echo "PRZYRZAD: nie umiem wejsc do korzenia repo" >&2; exit 2; }

KONTENER="${1:-strona_wp_wordpress}"
WP="docker exec -i -u www-data $KONTENER wp --path=/var/www/html --skip-themes"

BLEDY=0
ok()  { printf '  OK   %s\n' "$1"; }
zle() { printf '  BLAD %s\n' "$1"; BLEDY=$(( BLEDY + 1 )); }

pole() { printf '%s\n' "$1" | sed -n "s/^$2=//p" | head -1; }

echo "=== A. sciezka szczesliwa: zapis sie udaje ==="
WY_A=$($WP eval-file - < testy/rodo-anonimizacja-krok-szczesliwa.php 2>&1)
printf '%s\n' "$WY_A" | sed 's/^/    | /'
ID_A=$(pole "$WY_A" ID)
[ -n "$ID_A" ] || { echo "PRZYRZAD: krok A nie oddal ID zamowienia"; exit 2; }

STAN_A=$($WP eval-file - "$ID_A" < testy/rodo-anonimizacja-krok-stan.php 2>&1)
printf '%s\n' "$STAN_A" | sed 's/^/    | /'

case "$WY_A" in *"REDIRECT="*"sklepag_anonimizacja=ok"*) ok "A: wlasciciel dostal potwierdzenie" ;; *) zle "A: brak przekierowania z potwierdzeniem" ;; esac
[ "$(pole "$STAN_A" MAIL_PO)" = "PUSTY" ] && ok "A: e-mail kupujacego zniknal" || zle "A: e-mail ZOSTAL w bazie"
[ "$(pole "$STAN_A" USUNIETO_DNIA)" = "JEST" ] && ok "A: usunieto_dnia ustawione" || zle "A: brak usunieto_dnia"
[ "$(pole "$STAN_A" TOKEN_POBRANIA)" = "PUSTY" ] && ok "A: token pobrania uniewazniony" || zle "A: token pobrania ZOSTAL"
[ "$(pole "$STAN_A" ZDARZENIE_ANONIMIZACJA)" = "JEST" ] && ok "A: zdarzenie anonimizacja zapisane" || zle "A: brak zdarzenia anonimizacja"
[ "$(pole "$STAN_A" ZDARZENIE_ANONIMIZACJA_BLAD)" = "BRAK" ] && ok "A: brak falszywego zdarzenia bledu" || zle "A: zapisano anonimizacja_blad mimo powodzenia"

echo
echo "=== B. PODLOZONA AWARIA zapisu — handler NIE moze potwierdzic usuniecia ==="
WY_B=$($WP eval-file - < testy/rodo-anonimizacja-krok-awaria.php 2>&1)
printf '%s\n' "$WY_B" | sed 's/^/    | /'
ID_B=$(pole "$WY_B" ID)
[ -n "$ID_B" ] || { echo "PRZYRZAD: krok B nie oddal ID zamowienia"; exit 2; }

# ⛔ WARUNEK WSTEPNY WLASNEGO PRZYRZADU: jesli podlozenie nie trafilo w zapytanie,
#    test nie mierzy awarii — a wtedy „przeszlo" znaczyloby cisze, nie zgodnosc.
TRAF=$(pole "$WY_B" PODLOZENIE_TRAFILO)
if [ "${TRAF:-0}" = "0" ]; then
  echo "PRZYRZAD: podlozona wada NIE trafila w zapytanie czyszczace e-mail — test nic nie zmierzyl"
  $WP eval-file - "$ID_B" < testy/rodo-anonimizacja-krok-stan.php >/dev/null 2>&1
  exit 2
fi
ok "B: podlozenie trafilo w zapytanie ($TRAF x) — jest co mierzyc"

STAN_B=$($WP eval-file - "$ID_B" < testy/rodo-anonimizacja-krok-stan.php 2>&1)
printf '%s\n' "$STAN_B" | sed 's/^/    | /'

case "$WY_B" in *"REDIRECT="*"sklepag_anonimizacja=ok"*) zle "B: handler POTWIERDZIL usuniecie mimo awarii zapisu" ;; *) ok "B: brak falszywego potwierdzenia" ;; esac
case "$WY_B" in *"WPDIE="*) ok "B: wlasciciel dostal ostrzezenie zamiast potwierdzenia" ;; *) zle "B: brak ostrzezenia dla wlasciciela" ;; esac
[ "$(pole "$STAN_B" MAIL_PO)" = "ZOSTAL" ] && ok "B: e-mail faktycznie zostal (awaria byla prawdziwa)" || zle "B: e-mail zniknal — podlozenie nie zadzialalo, wynik nic nie znaczy"
[ "$(pole "$STAN_B" ZDARZENIE_ANONIMIZACJA)" = "BRAK" ] && ok "B: NIE zapisano falszywego zdarzenia anonimizacja" || zle "B: dziennik KLAMIE — zapisano anonimizacja mimo ze dane zostaly"
[ "$(pole "$STAN_B" ZDARZENIE_ANONIMIZACJA_BLAD)" = "JEST" ] && ok "B: zapisano anonimizacja_blad" || zle "B: brak sladu bledu w dzienniku"

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "WYNIK=PASS — potwierdzenie usuniecia idzie wylacznie za faktycznym usunieciem"
  exit 0
fi
echo "WYNIK=FAIL — $BLEDY asercji nie przeszlo"
exit 1
