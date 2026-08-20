#!/bin/bash
# Czy DOKUMENTACJA-TECHNICZNA.md i DIAGRAM-PRZEPLYWU.svg mowia to samo, co kod?
#
# Najczestsza uwaga egzaminatora w poprzednim projekcie (~30 pozycji): dokumentacja
# rozjechana z kodem. Ten skrypt sprawdza KAZDA liczbe i nazwe z dokumentacji
# grepem po zrodle. Uruchamiac po kazdej zmianie kodu chatbota.
#
# Uzycie: testy/dokumentacja-zgodnosc.sh     (z katalogu repo)
set -u
W=wtyczki/chatbot-live
DOK=$W/DOKUMENTACJA-TECHNICZNA.md
SVG=$W/DIAGRAM-PRZEPLYWU.svg
BLEDY=0

spr() { # spr "opis" "oczekiwane" "polecenie"
  local opis="$1" ocz="$2" got
  got=$(eval "$3" 2>/dev/null)
  if [ "$got" = "$ocz" ]; then
    printf '  OK   %-58s = %s\n' "$opis" "$got"
  else
    printf '  BLAD %-58s oczekiwano [%s], w kodzie [%s]\n' "$opis" "$ocz" "$got"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== A. Wersja i schemat ==="
spr "naglowek Version"            "1.2.4" "grep -oP '(?<=^ \* Version: ).*' $W/chatbot-live.php"
spr "stala CHATL_WERSJA"          "1.2.4" "grep -oP \"(?<=CHATL_WERSJA', ')[^']*\" $W/chatbot-live.php"
spr "CHATL_WERSJA_SCHEMATU"       "3"     "grep -oP \"(?<=CHATL_WERSJA_SCHEMATU', ')[^']*\" $W/chatbot-live.php"
spr "Requires at least"           "6.0"   "grep -oP '(?<=^ \* Requires at least: ).*' $W/chatbot-live.php"
spr "Requires PHP"                "8.1"   "grep -oP '(?<=^ \* Requires PHP: ).*' $W/chatbot-live.php"

echo
echo "=== B. Stale liczbowe (dokumentacja cytuje te wartosci) ==="
spr "CHATL_MAX_PYTANIE"           "500"   "grep -oP '(?<=CHATL_MAX_PYTANIE   = )[0-9]+' $W/includes/front.php"
spr "CHATL_MAX_WIADOMOSC"         "2000"  "grep -oP '(?<=CHATL_MAX_WIADOMOSC = )[0-9]+' $W/includes/front.php"
spr "CHATL_LIMIT_NA_MIN"          "10"    "grep -oP '(?<=CHATL_LIMIT_NA_MIN  = )[0-9]+' $W/includes/front.php"
spr "CHATL_RETENCJA_DNI"          "90"    "grep -oP '(?<=CHATL_RETENCJA_DNI  = )[0-9]+' $W/includes/front.php"
spr "CHATL_AI_LIMIT_IP_NA_DOBE"   "20"    "grep -oP '(?<=CHATL_AI_LIMIT_IP_NA_DOBE = )[0-9]+' $W/includes/front.php"
spr "CHATL_AI_MAX_TOKENS"         "1024"  "grep -oP '(?<=CHATL_AI_MAX_TOKENS = )[0-9]+' $W/includes/ai.php"
spr "CHATL_AI_TIMEOUT"            "9"     "grep -oP '(?<=CHATL_AI_TIMEOUT    = )[0-9]+' $W/includes/ai.php"
spr "CHATL_CSV_PORCJA"            "500"   "grep -oP '(?<=CHATL_CSV_PORCJA = )[0-9]+' $W/includes/csv.php"
spr "CHATL_AI_MODEL"              "claude-haiku-4-5-20251001" "grep -oP \"(?<=CHATL_AI_MODEL      = ')[^']*\" $W/includes/ai.php"
spr "CHATL_AI_BRAK"               "BRAK_ODPOWIEDZI" "grep -oP \"(?<=CHATL_AI_BRAK       = ')[^']*\" $W/includes/ai.php"

echo
echo "=== C. Retencja ma JEDNO zrodlo (nie liczba wpisana w cronie) ==="
spr "cron liczy retencje ze stalej"   "1" "grep -c 'CHATL_RETENCJA_DNI \* DAY_IN_SECONDS' $W/chatbot-live.php"
spr "liczba 90 wpisana wprost w kodzie PHP" "0" "grep -rhoP '\\d+ \\* DAY_IN_SECONDS' $W --include=*.php | grep -c '^90 '"

echo
echo "=== D. Baza: 5 tabel i liczba kolumn ==="
spr "liczba dbDelta"              "5"  "grep -c 'dbDelta(' $W/includes/instalator.php"
spr "kolumn w chatl_qa"           "6"  "sed -n '/CREATE TABLE {\$p}chatl_qa/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|pytanie|slowa_kluczowe|odpowiedz|aktywne|utworzono) '"
spr "kolumn w chatl_zgloszenia"   "6"  "sed -n '/CREATE TABLE {\$p}chatl_zgloszenia/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|email|wiadomosc|sesja|status|utworzono) '"
spr "kolumn w chatl_rozmowy"      "6"  "sed -n '/CREATE TABLE {\$p}chatl_rozmowy/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|sesja|rola|silnik|tresc|utworzono) '"
spr "kolumn w chatl_ai_uzycie"    "2"  "sed -n '/CREATE TABLE {\$p}chatl_ai_uzycie/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(dzien|wywolania) '"
spr "kolumn w chatl_rate_limit"   "3"  "sed -n '/CREATE TABLE {\$p}chatl_rate_limit/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(klucz|licznik|okno_do) '"
spr "email varchar(190)"          "1"  "grep -c 'email varchar(190) NOT NULL' $W/includes/instalator.php"
spr "sesja varchar(64) x2"        "2"  "grep -c 'sesja varchar(64)' $W/includes/instalator.php"
spr "status varchar(20)"          "1"  "grep -c \"status varchar(20) NOT NULL DEFAULT 'nowe'\" $W/includes/instalator.php"
spr "rola varchar(10)"            "1"  "grep -c 'rola varchar(10) NOT NULL' $W/includes/instalator.php"
spr "silnik domyslnie reguly"     "1"  "grep -c \"silnik varchar(10) NOT NULL DEFAULT 'reguly'\" $W/includes/instalator.php"
spr "indeksow KEY (poza PRIMARY)" "4"  "grep -cP '^\\t\\tKEY ' $W/includes/instalator.php"

echo
echo "=== E. Aktywacja, seed, cron ==="
spr "par Q&A w seedzie"           "10" "awk '/\\\$seed = array\\(/{f=1} f&&/^\t\tarray\\(\$/{n++} f&&/^\t\\);\$/{print n; exit}' $W/includes/instalator.php"
spr "harmonogram: daily"          "1"  "grep -c \"wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'chatl_czyszczenie_historii' )\" $W/includes/instalator.php"
spr "deaktywacja zdejmuje cron"   "1"  "grep -c \"wp_clear_scheduled_hook( 'chatl_czyszczenie_historii' )\" $W/includes/instalator.php"
spr "seed tylko do pustej bazy"   "1"  "grep -c 'if ( \$ile > 0 ) {' $W/includes/instalator.php"

echo
echo "=== F. Deinstalacja: obie sciezki ==="
spr "DROP TABLE (5 tabel)"        "5"  "grep -c 'DROP TABLE IF EXISTS' $W/uninstall.php"
spr "klucz API kasowany zawsze"   "1"  "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'chatl_klucz_api' )\""
spr "delete_option w sciezce B"   "3"  "sed -n '/usun_dane/,\$p' $W/uninstall.php | grep -c 'delete_option('"
spr "wywolan esc_like (bez komentarzy)" "2" "grep -o 'wpdb->esc_like(' $W/uninstall.php | wc -l"

echo
echo "=== G. Ustawienia: klucze i wartosci domyslne ==="
spr "kluczy w chatl_ustawienia"   "9"  "sed -n '/\\\$domyslne = array(/,/);/p' $W/chatbot-live.php | grep -cP \"^\t\t'\""
spr "ai_limit_dzienny domyslnie"  "100" "grep -oP \"(?<='ai_limit_dzienny' => )[0-9]+\" $W/chatbot-live.php"
spr "zakres zapisu limitu (max)"  "100000" "grep -oP '(?<=min\\( )[0-9]+(?=, \\(int\\) \\\$_POST\\[.ai_limit_dzienny)' $W/includes/admin.php"
spr "wlaczony domyslnie"          "1"  "grep -oP \"(?<='wlaczony'         => )[0-9]+\" $W/chatbot-live.php"
spr "usun_dane domyslnie"         "0"  "grep -oP \"(?<='usun_dane'        => )[0-9]+\" $W/chatbot-live.php"

echo
echo "=== H. Opcje i autoload ==="
spr "klucz API add_option bez autoload" "1" "grep -c \"add_option( 'chatl_klucz_api', \\\$klucz, '', false )\" $W/includes/ai.php"
spr "chatl_blad_maila bez autoload"     "1" "grep -A8 \"update_option($\" $W/includes/front.php | grep -cP '^\\t\\tfalse$'"

echo
echo "=== I. Zaczepy i filtry ==="
spr "endpointy AJAX (z nopriv)"   "4"  "grep -c \"add_action( 'wp_ajax\" $W/includes/front.php"
spr "akcje admin_post"            "7"  "grep -rc \"add_action( 'admin_post_\" $W/includes/admin.php $W/includes/csv.php | awk -F: '{s+=\$2} END {print s}'"
spr "wystawione filtry"           "2"  "grep -rc 'apply_filters(' $W/includes/ai.php"
spr "bramek current_user_can"      "6"  "grep -rc \"current_user_can( 'manage_options' )\" $W/includes/admin.php $W/includes/csv.php | awk -F: '{s+=\$2} END {print s}'"
spr "stron panelu (menu+submenu)"  "5"  "grep -c 'add_menu_page(\|add_submenu_page(' $W/includes/admin.php"

echo
echo "=== J. Panel: limity list cytowane w dokumentacji ==="
spr "lista zgloszen LIMIT 200"    "1"  "grep -c 'chatl_zgloszenia} ORDER BY id DESC LIMIT 200\\|ORDER BY id DESC LIMIT 200' $W/includes/admin.php"
spr "lista rozmow LIMIT 100"      "1"  "grep -c 'ORDER BY koniec DESC LIMIT 100' $W/includes/admin.php"

echo
echo "=== K. Bezpieczenstwo: to, co dokumentacja obiecuje ==="
spr "regex sesji"                 "1"  "grep -c \"a-zA-Z0-9-]{8,64}\" $W/includes/front.php"
spr "znaki neutralizowane w CSV"  "1"  "grep -c \"array( '=', '+', '-', '@' )\" $W/includes/csv.php"
spr "BOM UTF-8 w eksporcie"       "1"  "grep -c 'xEF.xBB.xBF' $W/includes/csv.php"
spr "widget bez uzycia innerHTML"  "0"  "grep -c '\\.innerHTML' $W/assets/widget.js"
spr "zgoda sprawdzana na serwerze" "1" "grep -c \"empty( \\\$_POST\\['zgoda'\\] )\" $W/includes/front.php"
spr "wp_trigger_error pod straza" "2"  "grep -rc \"function_exists( 'wp_trigger_error' )\" $W/chatbot-live.php $W/includes/ai.php | awk -F: '{s+=\$2} END {print s}'"

echo
echo "=== L. Reguła dopasowania: jedno zrodlo ==="
spr "definicji chatl_trafione_warianty" "1" "grep -rc 'function chatl_trafione_warianty' $W --include=*.php | awk -F: '{s+=\$2} END {print s}'"
spr "mieszka w silnik.php"        "1"  "grep -c 'function chatl_trafione_warianty' $W/includes/silnik.php"

echo
echo "=== M. Diagram: paleta i samodzielnosc ==="
spr "uzyc limonki BFFF38 w SVG"   "8"  "grep -o 'BFFF38' $SVG | wc -l"
spr "ciemne tlo 08090b"           "1"  "grep -c 'fill: #08090b' $SVG"
spr "brak odwolan zewnetrznych"    "0"  "grep 'https\\?://' $SVG | grep -vc 'www\\.w3\\.org/2000/svg'"
spr "brak font-face/import/script" "0" "grep -cE '@font-face|@import|<script|xlink:href|<image' $SVG"

echo
echo "=== N. Liczby cytowane w SVG ==="
spr "SVG: 500 znakow"             "1"  "grep -c 'maks. 500 znaków' $SVG"
spr "SVG: 10 zapytan/min"         "1"  "grep -c '10 zapytań / min / IP' $SVG"
spr "SVG: 90 dni"                 "1"  "grep -c '90 dni' $SVG"
spr "SVG: timeout 9 s"            "1"  "grep -c 'timeout 9 s' $SVG"

echo
echo "=== O. Ramki tekstow w SVG (dokladny pomiar getBBox, CZTERY krawedzie) ==="
# SVG NIE zawija tekstu: element <text> z poprawnym x rozciaga sie tyle, ile
# zajma glify, i cicho wychodzi poza kadr. Sprawdzenie samego punktu startowego
# tego NIE lapie — tak przeszla urwana stopka w pierwszej wersji diagramu.
# Kontrola patrzy na WSZYSTKIE CZTERY krawedzie (lewa, prawa, gorna, dolna),
# bo pierwsza wersja tej kontroli pilnowala w boksach tylko prawej — a kontrola
# z luka jest grozniejsza niz brak kontroli. Ze naprawde widzi kazda krawedz,
# dowodzi testy/svg-proba-kontrolna.sh (podklada celowy blad przy kazdej).
# Jedyny sposob, zeby zmierzyc to naprawde, to silnik skladu tekstu, czyli
# przegladarka. Estymator szerokosci bez przegladarki byl sprawdzony i ODRZUCONY:
# skalibrowany na 65 prawdziwych pomiarach przeszacowywal do 36%, wiec jako
# bramka falszywie alarmowalby na tekstach, ktore sie mieszcza.
if [ ! -x /root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome ] || [ ! -d node_modules/playwright-core ]; then
  echo "  POMINIETE  brak chromium albo playwright-core — kontrola ramek tekstow NIE zostala wykonana"
  echo "             (npm install --no-save playwright-core; potem: node testy/svg-pomiar-tekstow.js)"
  BLEDY=$((BLEDY+1))
else
  if node testy/svg-pomiar-tekstow.js "$SVG" | sed 's/^/  /'; then
    printf '  OK   %-58s\n' "wszystkie teksty mieszcza sie w kadrze i w swoich ramkach"
  else
    printf '  BLAD %-58s\n' "teksty wychodza poza kadr albo poza swoje ramki"
    BLEDY=$((BLEDY+1))
  fi
fi

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "ZGODNE: dokumentacja i diagram nie rozjezdzaja sie z kodem"
else
  echo "NIEZGODNE: $BLEDY pozycji rozjechanych z kodem"
fi
exit $((BLEDY > 0))
