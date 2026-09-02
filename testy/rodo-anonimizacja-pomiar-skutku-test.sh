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
#      treść maila i `dane_faktury` puste (treść niosła pełną kopię danych do faktury:
#      nazwa firmy, NIP, adres — WAŻNA 1 recenzji #234 r5),
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

# ⛔ WAZNA 4 recenzji #207: test na wspolnym stosie moze mierzyc STARA kopie wtyczki,
#    a wtedy zielone nie znaczy nic. Rozstrzygamy PRZED pomiarem: suma pliku handlera
#    w kontenerze musi byc rowna sumie pliku z repo.
# ⛔ Sprawdzamy CALA wtyczke, nie jeden plik. Zgodnosc jednego pliku znaczylaby tylko,
#    ze handler jest swiezy — a mierzylibysmy mieszanke: nowy handler i STARA reszta.
#    Nazwa kontroli musi pokrywac to, co naprawde mierzy.
#    ⛔ LC_ALL=C przy OBU sortowaniach: bez tego kontener i host ustawiaja pliki w innej
#    kolejnosci (inne locale) i suma sum sie rozjezdza mimo IDENTYCZNEJ tresci — przyrzad
#    dawal wtedy falszywy alarm. Zlapane na sobie.
KAT_REPO=wtyczki/sklep-agentow
KAT_STOS=/var/www/html/wp-content/plugins/sklep-agentow
SUMA_REPO=$(cd "$KAT_REPO" && find . -type f -name '*.php' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | LC_ALL=C sort | sha256sum | cut -d" " -f1)
SUMA_STOS=$(docker exec "$KONTENER" sh -c "cd $KAT_STOS && find . -type f -name '*.php' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | LC_ALL=C sort | sha256sum" 2>/dev/null | cut -d" " -f1)
LICZ_REPO=$(cd "$KAT_REPO" && find . -type f -name '*.php' | wc -l)
LICZ_STOS=$(docker exec "$KONTENER" sh -c "cd $KAT_STOS && find . -type f -name '*.php' | wc -l" 2>/dev/null | tr -d '\r')
echo "PLIKI_PHP_REPO=$LICZ_REPO · PLIKI_PHP_STOS=$LICZ_STOS"
echo "SUMA_REPO=$SUMA_REPO"
echo "SUMA_STOS=$SUMA_STOS"
if [ -z "$SUMA_STOS" ]; then
  echo "PRZYRZAD: nie umiem policzyc sumy wtyczki na stosie ($KONTENER)"; exit 2
fi
if [ "$SUMA_REPO" != "$SUMA_STOS" ]; then
  echo "PRZYRZAD: na stosie stoi INNA wersja wtyczki niz w repo — pomiar nic nie znaczy."
  echo "PRZYRZAD: wgraj CALA wtyczke, np.:"
  echo "PRZYRZAD:   docker cp $KAT_REPO/. $KONTENER:$KAT_STOS/"
  exit 2
fi
echo "  OK   przyrzad mierzy TE wtyczke, ktora jest w repo ($LICZ_REPO plikow PHP, sha256 zgodne)"
echo

echo "=== A. sciezka szczesliwa: zapis sie udaje ==="
WY_A=$($WP eval-file - < testy/kroki-rodo/rodo-anonimizacja-krok-szczesliwa.php 2>&1)
printf '%s\n' "$WY_A" | sed 's/^/    | /'
ID_A=$(pole "$WY_A" ID)
[ -n "$ID_A" ] || { echo "PRZYRZAD: krok A nie oddal ID zamowienia"; exit 2; }

# ⛔ WAZNA 1 #234 r6: render panelu PRZED krokiem stanu (tamten sprzata zamowienie).
WY_R=$($WP eval-file - "$ID_A" < testy/kroki-rodo/rodo-anonimizacja-krok-render.php 2>&1)
printf '%s\n' "$WY_R" | sed 's/^/    | /'
case "$WY_R" in *"RENDER_PRZYRZAD=OK"*) : ;; *) echo "PRZYRZAD: render panelu nie doszedl do skutku"; $WP eval-file - "$ID_A" < testy/kroki-rodo/rodo-anonimizacja-krok-stan.php >/dev/null 2>&1; exit 2 ;; esac

STAN_A=$($WP eval-file - "$ID_A" < testy/kroki-rodo/rodo-anonimizacja-krok-stan.php 2>&1)
printf '%s\n' "$STAN_A" | sed 's/^/    | /'

case "$WY_A" in *"REDIRECT="*"sklepag_anonimizacja=ok"*) ok "A: wlasciciel dostal potwierdzenie" ;; *) zle "A: brak przekierowania z potwierdzeniem" ;; esac
[ "$(pole "$STAN_A" MAIL_PO)" = "PUSTY" ] && ok "A: e-mail kupujacego zniknal" || zle "A: e-mail ZOSTAL w bazie"
[ "$(pole "$STAN_A" USUNIETO_DNIA)" = "JEST" ] && ok "A: usunieto_dnia ustawione" || zle "A: brak usunieto_dnia"
[ "$(pole "$STAN_A" TOKEN_POBRANIA)" = "PUSTY" ] && ok "A: token pobrania uniewazniony" || zle "A: token pobrania ZOSTAL"
# ⛔ WAZNA 1 recenzji #234 r5: tresc maila w zamowieniu niesie PELNA kopie danych do faktury
#   (nazwa firmy, NIP, adres). Anonimizacja kasowala kolumne dane_faktury i KOPIE dor_mail_tresc,
#   a ORYGINAL mail_tresc zostawal — panel wypisywal go po anonimizacji, pomiar meldowal sukces.
case "$WY_A" in *"MAIL_TRESC_PRZED=ZAWIERA-FAKTURE"*) ok "A: warunek wstepny — tresc maila zawiera dane faktury (jest co czyscic)" ;; *) zle "A: zasiew BEZ danych faktury w tresci maila — przypadek nic nie mierzy" ;; esac
[ "$(pole "$STAN_A" MAIL_TRESC)" = "PUSTY" ] && ok "A: tresc maila (kopia danych faktury: NIP, adres) zniknela" || zle "A: tresc maila ZOSTALA — dane do faktury do odczytania po anonimizacji"
[ "$(pole "$STAN_A" DANE_FAKTURY)" = "PUSTY" ] && ok "A: dane_faktury puste" || zle "A: dane_faktury ZOSTALY"
# ⛔ WAZNA 1 #234 r6: panel zanonimizowanego zamowienia nie moze pisac „zamowienie niedomkniete"
#   obok statusu mail_wyslany — ma mowic wprost, ze tresc usunieto przy anonimizacji.
[ "$(pole "$WY_R" RENDER_FRAZA_NIEDOMKNIETE)" = "0" ] && ok "A: panel NIE pisze o niedomknietym zamowieniu po anonimizacji" || zle "A: panel KLAMIE — po anonimizacji pisze o zamowieniu niedomknietym przy statusie mail_wyslany"
[ "$(pole "$WY_R" RENDER_FRAZA_USUNIETA)" = "1" ] && ok "A: panel mowi wprost o usunieciu tresci przy anonimizacji" || zle "A: panel nie tlumaczy pustej tresci maila anonimizacja"
[ "$(pole "$STAN_A" ZDARZENIE_ANONIMIZACJA)" = "JEST" ] && ok "A: zdarzenie anonimizacja zapisane" || zle "A: brak zdarzenia anonimizacja"
[ "$(pole "$STAN_A" ZDARZENIE_ANONIMIZACJA_BLAD)" = "BRAK" ] && ok "A: brak falszywego zdarzenia bledu" || zle "A: zapisano anonimizacja_blad mimo powodzenia"

echo
echo "=== B. PODLOZONA AWARIA zapisu — handler NIE moze potwierdzic usuniecia ==="
WY_B=$($WP eval-file - < testy/kroki-rodo/rodo-anonimizacja-krok-awaria.php 2>&1)
printf '%s\n' "$WY_B" | sed 's/^/    | /'
ID_B=$(pole "$WY_B" ID)
[ -n "$ID_B" ] || { echo "PRZYRZAD: krok B nie oddal ID zamowienia"; exit 2; }

# ⛔ WARUNEK WSTEPNY WLASNEGO PRZYRZADU: jesli podlozenie nie trafilo w zapytanie,
#    test nie mierzy awarii — a wtedy „przeszlo" znaczyloby cisze, nie zgodnosc.
TRAF=$(pole "$WY_B" PODLOZENIE_TRAFILO)
if [ "${TRAF:-0}" = "0" ]; then
  echo "PRZYRZAD: podlozona wada NIE trafila w zapytanie czyszczace e-mail — test nic nie zmierzyl"
  $WP eval-file - "$ID_B" < testy/kroki-rodo/rodo-anonimizacja-krok-stan.php >/dev/null 2>&1
  exit 2
fi
ok "B: podlozenie trafilo w zapytanie ($TRAF x) — jest co mierzyc"

STAN_B=$($WP eval-file - "$ID_B" < testy/kroki-rodo/rodo-anonimizacja-krok-stan.php 2>&1)
printf '%s\n' "$STAN_B" | sed 's/^/    | /'

case "$WY_B" in *"REDIRECT="*"sklepag_anonimizacja=ok"*) zle "B: handler POTWIERDZIL usuniecie mimo awarii zapisu" ;; *) ok "B: brak falszywego potwierdzenia" ;; esac
case "$WY_B" in *"WPDIE="*) ok "B: wlasciciel dostal ostrzezenie zamiast potwierdzenia" ;; *) zle "B: brak ostrzezenia dla wlasciciela" ;; esac
[ "$(pole "$STAN_B" MAIL_PO)" = "ZOSTAL" ] && ok "B: e-mail faktycznie zostal (awaria byla prawdziwa)" || zle "B: e-mail zniknal — podlozenie nie zadzialalo, wynik nic nie znaczy"
[ "$(pole "$STAN_B" ZDARZENIE_ANONIMIZACJA)" = "BRAK" ] && ok "B: NIE zapisano falszywego zdarzenia anonimizacja" || zle "B: dziennik KLAMIE — zapisano anonimizacja mimo ze dane zostaly"
[ "$(pole "$STAN_B" ZDARZENIE_ANONIMIZACJA_BLAD)" = "JEST" ] && ok "B: zapisano anonimizacja_blad" || zle "B: brak sladu bledu w dzienniku"

echo
echo "=== C. POWTORZENIE po awarii — czy druga proba domyka WSZYSTKIE miejsca ==="
WY_C=$($WP eval-file - < testy/kroki-rodo/rodo-anonimizacja-krok-powtorka.php 2>&1)
printf '%s\n' "$WY_C" | sed 's/^/    | /'
ID_C=$(pole "$WY_C" ID)
[ -n "$ID_C" ] || { echo "PRZYRZAD: krok C nie oddal ID zamowienia"; exit 2; }

TRAF_C=$(pole "$WY_C" PROBA1_PODLOZENIE_TRAFILO)
if [ "${TRAF_C:-0}" = "0" ]; then
  echo "PRZYRZAD: podlozona wada NIE trafila w zapytanie czyszczace zrodlo — przypadek C nic nie zmierzyl"
  $WP eval-file - "$ID_C" < testy/kroki-rodo/rodo-anonimizacja-krok-stan.php >/dev/null 2>&1
  exit 2
fi
ok "C: podlozenie trafilo w zapytanie ($TRAF_C x) — jest co mierzyc"
[ "$(pole "$WY_C" ADRES_LUZEM_PRZED)" != "0" ] && ok "C: warunek wstepny — sa slady BEZ zamowienie_id ($(pole "$WY_C" ADRES_LUZEM_PRZED)): nasz z eksportu + wpis obcego" || zle "C: brak sladow luzem — przypadek nic by nie zmierzyl"

STAN_C=$($WP eval-file - "$ID_C" < testy/kroki-rodo/rodo-anonimizacja-krok-stan.php 2>&1)
printf '%s\n' "$STAN_C" | sed 's/^/    | /'

case "$WY_C" in *"PROBA1_WPDIE="*) ok "C: pierwsza proba ODMOWILA (awaria zrodla zlapana)" ;; *) zle "C: pierwsza proba nie odmowila mimo awarii zapisu" ;; esac
case "$WY_C" in *"PROBA1_REDIRECT="*"sklepag_anonimizacja=ok"*) zle "C: pierwsza proba POTWIERDZILA mimo awarii" ;; *) ok "C: brak falszywego potwierdzenia w probie 1" ;; esac
[ "$(pole "$WY_C" ADRES_W_ZRODLE_PO_PROBIE1)" = "ZOSTAL" ] && ok "C: po nieudanej probie zrodlo NIETKNIETE (jest z czego powtorzyc)" || zle "C: zrodlo wyczyszczone mimo odmowy — powtorka nie ma jak domknac dziennika"
case "$WY_C" in *"PROBA2_REDIRECT="*"sklepag_anonimizacja=ok"*) ok "C: druga proba domknela i potwierdzila" ;; *) zle "C: druga proba NIE domknela anonimizacji" ;; esac
[ "$(pole "$STAN_C" MAIL_PO)" = "PUSTY" ] && ok "C: adres zniknal ze zrodla" || zle "C: adres ZOSTAL w zrodle po powtorce"
[ "$(pole "$STAN_C" WPIS_OBCEGO_RFC_CALY)" = "1" ] && ok "C: wpis obcego z prefiksem RFC (!) NIETKNIETY — kotwica pokrywa pelny zbior znakow adresu" || zle "C: przepisano wpis obcego z prefiksem RFC — kotwica stoi na za waskim zbiorze znakow"
[ "$(pole "$STAN_C" WPIS_OBCEGO_CALY)" = "1" ] && ok "C: wpis INNEGO kupujacego (adres z naszym jako koncowka) NIETKNIETY" || zle "C: przepisano wpis INNEGO kupujacego — niezakotwiczone dopasowanie psuje cudze dane"
[ "$(pole "$STAN_C" WPIS_OBCEGO_JSON_CALY)" = "1" ] && ok "C: wpis obcego w postaci ZAKODOWANEJ NIETKNIETY — kotwica trzyma na obu postaciach" || zle "C: przepisano cudzy wpis w postaci zakodowanej — druga postac szuka bez kotwicy"
[ "$(pole "$STAN_C" SLADY_W_DZIENNIKU)" = "0" ] && ok "C: adres zniknal TAKZE z dziennika zdarzen" || zle "C: adres ZOSTAL w dzienniku — to jest wada z WAZNEJ 2 recenzji #207"

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "WYNIK=PASS — potwierdzenie usuniecia idzie wylacznie za faktycznym usunieciem"
  exit 0
fi
echo "WYNIK=FAIL — $BLEDY asercji nie przeszlo"
exit 1
