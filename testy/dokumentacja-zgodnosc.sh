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
spr "naglowek Version"            "1.4.7" "grep -oP '(?<=^ \* Version: ).*' $W/chatbot-live.php"
spr "stala CHATL_WERSJA"          "1.4.7" "grep -oP \"(?<=CHATL_WERSJA', ')[^']*\" $W/chatbot-live.php"
spr "CHATL_WERSJA_SCHEMATU"       "4"     "grep -oP \"(?<=CHATL_WERSJA_SCHEMATU', ')[^']*\" $W/chatbot-live.php"
spr "Requires at least"           "6.0"   "grep -oP '(?<=^ \* Requires at least: ).*' $W/chatbot-live.php"
spr "Requires PHP"                "8.1"   "grep -oP '(?<=^ \* Requires PHP: ).*' $W/chatbot-live.php"
spr "naglowek Author"             "Automatic AI" "grep -oP '(?<=^ \* Author: ).*' $W/chatbot-live.php"
spr "naglowek License"            "GPL-2.0-or-later" "grep -oP '(?<=^ \* License: ).*' $W/chatbot-live.php"
spr "naglowek License URI"        "https://www.gnu.org/licenses/gpl-2.0.html" "grep -oP '(?<=^ \* License URI: ).*' $W/chatbot-live.php"
spr "naglowek Text Domain"        "chatbot-live" "grep -oP '(?<=^ \* Text Domain: ).*' $W/chatbot-live.php"

echo
echo "=== A1. Dokumentacja cytuje TE SAME liczby co kod (DOK <-> KOD) ==="
# Dziura zgloszona w recenzji PR #73 (klasa #3 sciagi: dokumentacja rozjezdza sie
# z kodem). Kontrole w sekcji A porownuja KOD z liczba wpisana w TYM SKRYPCIE —
# wiec gdy ktos zmieni kod i skrypt, a zapomni dokumentacji, bramka dalej swieci
# na zielono. Tak przeszlo "numer schematu, obecnie 3" przy schemacie 4.
# Ta sekcja czyta liczbe Z DOKUMENTACJI i porownuje ja Z KODEM. Zapomniany tekst
# pada tutaj, a nie u recenzenta.
BT=$(printf '\140')   # znak wstecznego apostrofu — nigdy wprost, bo eval by go wykonal.

wartosc_stalej() { # wartosc_stalej NAZWA PLIK  -> wartosc const/define z kodu
  grep -hoP "(?:const\s+$1\s*=\s*|define\(\s*'$1',\s*)'?\K[^';]+" "$2" 2>/dev/null \
    | head -1 | sed 's/[[:space:]]*$//'
}

WERSJA_KOD=$(grep -oP "(?<=CHATL_WERSJA', ')[^']*" $W/chatbot-live.php)
SCHEMAT_KOD=$(grep -oP "(?<=CHATL_WERSJA_SCHEMATU', ')[^']*" $W/chatbot-live.php)

spr "naglowek dokumentacji: wersja = kod"  "$WERSJA_KOD"  "grep -oP '^Wersja wtyczki: \*\*\K[^*]+' $DOK"
spr "naglowek dokumentacji: schemat = kod" "$SCHEMAT_KOD" "grep -oP 'schemat bazy: \*\*\K[^*]+' $DOK"
spr "sekcja 4 (wp_options): schemat = kod" "$SCHEMAT_KOD" "grep -oP 'numer schematu, obecnie \x60\K[^\x60]+' $DOK"

# Tabela stalych z sekcji 5: nazwe, wartosc I PLIK bierzemy WPROST Z DOKUMENTACJI,
# a wartosc sprawdzamy w tym pliku. Nowa stala dopisana do tabeli jest wiec
# sprawdzana automatycznie — nie trzeba pamietac o dopisaniu jej do tego skryptu.
STALYCH=0
while IFS='|' read -r _ NAZWA WART PLIK _; do
  NAZWA=$(echo "$NAZWA" | tr -d " $BT")
  WART=$(echo "$WART" | tr -d "$BT" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
  PLIK=$(echo "$PLIK" | tr -d " $BT")
  [ -f "$W/$PLIK" ] || continue
  STALYCH=$((STALYCH+1))
  spr "DOK=KOD: $NAZWA ($PLIK)" "$WART" "wartosc_stalej $NAZWA $W/$PLIK"
done < <(grep -P "^\| ${BT}CHATL_[A-Z_]+${BT} \| ${BT}[^${BT}]+${BT} \| ${BT}[^${BT}]+${BT} \|" $DOK)
# Sama petla nie wystarczy: gdyby wzorzec wiersza przestal pasowac (zmiana ukladu
# tabeli), petla nie zrobilaby ANI JEDNEGO sprawdzenia i sekcja bylaby cicho pusta.
spr "stalych sprawdzonych z tabeli dokumentacji" "27" "echo $STALYCH"

echo
echo "=== A1b. Rejestr rozszerzen ponad specyfikacje (klasa #6 sciagi) ==="
# Recenzja PR #73: nowa funkcja (statystyki i raport luk) nie byla wpisana do
# boty/PONAD-SPECYFIKACJE-BOTY.md. Egzaminator traktuje niezadeklarowane
# rozszerzenie jako WADE, nie bonus, wiec rejestr musi nadazac za kodem.
#
# Uczciwie o granicy tej kontroli: nie da sie automatycznie stwierdzic, ze
# funkcja X "jest ponad specyfikacje". Kontrola pilnuje dwoch rzeczy, ktore
# da sie sprawdzic: (1) pozycje, ktore juz sa, nie moga zniknac, (2) liczba
# pozycji jest wpisana wprost, wiec dopisanie dziewiatej ZATRZYMUJE bramke,
# dopoki ktos swiadomie nie podniesie tej liczby — tak samo jak allowlista
# paczki zatrzymuje budowe przy nowym pliku.
REJ=boty/PONAD-SPECYFIKACJE-BOTY.md
spr "rejestr istnieje"                    "1" "test -f $REJ && echo 1 || echo 0"
spr "pozycji ChatBota w rejestrze"        "10" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -cP '^[0-9]+\. '"
spr "rejestr wymienia tryb AI"            "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Tryb AI'"
spr "rejestr wymienia statystyki i luki"  "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Statystyki i raport luk'"
spr "rejestr wymienia konfigurowalny wyglad" "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Konfigurowalny wygląd widgetu'"
spr "rejestr wymienia ekran powitalny"    "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Ekran powitalny'"
spr "kazda pozycja ma powod (mysllnik)"   "10" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -cP '^[0-9]+\..*—|^[0-9]+\.[^—]*$'"

echo
echo "=== A2. Plik LICENSE (pelny GPL-2.0, wziety z rdzenia WordPressa) ==="
spr "LICENSE istnieje"            "1"  "test -f $W/LICENSE && echo 1 || echo 0"
spr "zaczyna sie naglowkiem GPL"  "1"  "head -1 $W/LICENSE | grep -c 'GNU GENERAL PUBLIC LICENSE'"
spr "wersja 2, June 1991"         "1"  "head -2 $W/LICENSE | grep -c 'Version 2, June 1991'"
spr "ma sekcje warunkow"          "1"  "grep -c 'TERMS AND CONDITIONS FOR COPYING' $W/LICENSE"
spr "ma sekcje NO WARRANTY"       "1"  "grep -c '^ *NO WARRANTY' $W/LICENSE"
spr "konczy sie na tresci GPL"    "1"  "tail -1 $W/LICENSE | grep -c 'Public License instead of this License.'"
spr "bez naglowka WRITTEN OFFER"  "0"  "grep -c '^WRITTEN OFFER$' $W/LICENSE"
spr "bez wzmianek o WordPressie"  "0"  "grep -ci 'wordpress' $W/LICENSE"

echo
echo "=== A3. Paczka produktowa ==="
spr "LICENSE na liscie plikow paczki" "1" "grep -c 'chatbot-live/LICENSE' boty/buduj-paczki.sh"
spr "csv.php na liscie plikow paczki" "1" "grep -c 'chatbot-live/includes/csv.php' boty/buduj-paczki.sh"
spr "statystyki.php na liscie paczki" "1" "grep -c 'chatbot-live/includes/statystyki.php' boty/buduj-paczki.sh"
spr "CHANGELOG.md na liscie paczki"  "1" "grep -c 'chatbot-live/CHANGELOG.md' boty/buduj-paczki.sh"
spr "readme.txt na liscie paczki"    "1" "grep -c 'chatbot-live/readme.txt' boty/buduj-paczki.sh"
spr "plikow chatbota w allowliscie"   "20" "sed -n '/^ALLOWLIST_CHATBOT=(/,/^)/p' boty/buduj-paczki.sh | grep -c '\"chatbot-live/'"
spr "allowlista = zawartosc katalogu" "0"  "cd wtyczki && find chatbot-live -type f | sort | comm -23 - <(sed -n '/^ALLOWLIST_CHATBOT=(/,/^)/p' ../boty/buduj-paczki.sh | grep -oP '(?<=\")chatbot-live/[^\"]*' | sort) | wc -l"
# Klasa, ktora tu wyszla: includes/csv.php byl wymagany przez require_once, ale
# NIE bylo go na liscie plikow paczki. Zip bez tego pliku = fatal w wp-admin
# u klienta. Kazdy plik z require_once MUSI byc w allowliscie.
spr "kazdy require_once jest w paczce" "0" "grep -oP \"(?<=require_once CHATL_KATALOG \\. ')[^']*\" $W/chatbot-live.php | sed 's|^|chatbot-live/|' | sort -u | comm -23 - <(sed -n '/^ALLOWLIST_CHATBOT=(/,/^)/p' boty/buduj-paczki.sh | grep -oP '(?<=\")chatbot-live/[^\"]*' | sort) | wc -l"

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
spr "CHATL_LUKI_LIMIT"            "2000"  "grep -oP '(?<=CHATL_LUKI_LIMIT = )[0-9]+' $W/includes/statystyki.php"
spr "CHATL_LUKI_POKAZ"            "25"    "grep -oP '(?<=CHATL_LUKI_POKAZ = )[0-9]+' $W/includes/statystyki.php"
spr "CHATL_LUKI_PROG"             "50"    "grep -oP '(?<=CHATL_LUKI_PROG = )[0-9]+' $W/includes/statystyki.php"
spr "CHATL_LUKI_RDZEN"            "4"     "grep -oP '(?<=CHATL_LUKI_RDZEN = )[0-9]+' $W/includes/statystyki.php"
spr "CHATL_SUGESTIA_RDZEN"        "5"     "grep -oP '(?<=CHATL_SUGESTIA_RDZEN = )[0-9]+' $W/includes/statystyki.php"
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
spr "kolumn w chatl_rozmowy"      "7"  "sed -n '/CREATE TABLE {\$p}chatl_rozmowy/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|sesja|rola|silnik|tresc|trafiono|utworzono) '"
spr "kolumn w chatl_ai_uzycie"    "2"  "sed -n '/CREATE TABLE {\$p}chatl_ai_uzycie/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(dzien|wywolania) '"
spr "kolumn w chatl_rate_limit"   "3"  "sed -n '/CREATE TABLE {\$p}chatl_rate_limit/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(klucz|licznik|okno_do) '"
spr "email varchar(190)"          "1"  "grep -c 'email varchar(190) NOT NULL' $W/includes/instalator.php"
spr "sesja varchar(64) x2"        "2"  "grep -c 'sesja varchar(64)' $W/includes/instalator.php"
spr "status varchar(20)"          "1"  "grep -c \"status varchar(20) NOT NULL DEFAULT 'nowe'\" $W/includes/instalator.php"
spr "rola varchar(10)"            "1"  "grep -c 'rola varchar(10) NOT NULL' $W/includes/instalator.php"
spr "trafiono dopuszcza NULL"     "1"  "grep -c 'trafiono tinyint(1) DEFAULT NULL' $W/includes/instalator.php"
spr "indeks luki pod raport"      "1"  "grep -c 'KEY luki (rola,trafiono,utworzono)' $W/includes/instalator.php"
spr "silnik domyslnie reguly"     "1"  "grep -c \"silnik varchar(10) NOT NULL DEFAULT 'reguly'\" $W/includes/instalator.php"
spr "indeksow KEY (poza PRIMARY)" "5"  "grep -cP '^\\t\\tKEY ' $W/includes/instalator.php"

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
spr "delete_option w sciezce B"   "4"  "sed -n '/usun_dane/,\$p' $W/uninstall.php | grep -c 'delete_option('"
spr "wywolan esc_like (bez komentarzy)" "2" "grep -o 'wpdb->esc_like(' $W/uninstall.php | wc -l"

echo
echo "=== G. Ustawienia: klucze i wartosci domyslne ==="
spr "kluczy w chatl_ustawienia"   "13"  "sed -n '/\\\$domyslne = array(/,/);/p' $W/chatbot-live.php | grep -cP \"^\t\t'\""
spr "ai_limit_dzienny domyslnie"  "100" "grep -oP \"(?<='ai_limit_dzienny' => )[0-9]+\" $W/chatbot-live.php"
spr "zakres zapisu limitu (max)"  "100000" "grep -oP '(?<=min\\( )[0-9]+(?=, \\(int\\) \\\$_POST\\[.ai_limit_dzienny)' $W/includes/admin.php"
spr "wlaczony domyslnie"          "1"  "grep -oP \"(?<='wlaczony'         => )[0-9]+\" $W/chatbot-live.php"
spr "usun_dane domyslnie"         "0"  "grep -oP \"(?<='usun_dane'        => )[0-9]+\" $W/chatbot-live.php"

echo
echo "=== H. Opcje i autoload ==="
spr "klucz API add_option bez autoload" "1" "grep -c \"add_option( 'chatl_klucz_api', \\\$klucz, '', false )\" $W/includes/ai.php"
# Oba znaczniki awarii (mail i odrzucony nonce) maja byc BEZ autoloadu: to opcje
# czytane tylko w panelu, wiec nie ma powodu wozic ich w kazdym zapytaniu strony.
spr "znacznikow awarii bez autoload"    "2" "grep -A8 \"update_option($\" $W/includes/front.php | grep -cP '^\\t\\tfalse$'"
spr "znacznik nonce zapisywany bez autoload" "1" "grep -A9 \"'chatl_blad_nonce',\" $W/includes/front.php | grep -cP '^\\t\\tfalse$'"

echo
echo "=== I. Zaczepy i filtry ==="
spr "endpointy AJAX (z nopriv)"   "6"  "grep -c \"add_action( 'wp_ajax\" $W/includes/front.php"
spr "akcje admin_post"            "8"  "grep -rc \"add_action( 'admin_post_\" $W/includes/admin.php $W/includes/csv.php | awk -F: '{s+=\$2} END {print s}'"
spr "wystawione filtry"           "2"  "grep -rc 'apply_filters(' $W/includes/ai.php"
spr "bramek current_user_can"      "7"  "grep -rc \"current_user_can( 'manage_options' )\" $W/includes/admin.php $W/includes/csv.php | awk -F: '{s+=\$2} END {print s}'"
spr "stron panelu (menu+submenu)"  "6"  "grep -c 'add_menu_page(\|add_submenu_page(' $W/includes/admin.php"

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
# Pamiec biezacej rozmowy: historia MUSI byc filtrowana po sesji (granica
# prywatnosci, nie optymalizacja) i MUSI miec sufit — kazda wymiana to tokeny.
spr "historia filtrowana po sesji" "2" "sed -n '/function chatl_ai_historia/,/^}/p' $W/includes/ai.php | grep -c 'sesja = %s'"
spr "historia ma sufit dlugosci"   "1" "sed -n '/function chatl_ai_historia/,/^}/p' $W/includes/ai.php | grep -c 'CHATL_AI_PAMIEC_WYMIAN \* 2'"
spr "biezace pytanie odciete po id" "1" "sed -n '/function chatl_ai_historia/,/^}/p' $W/includes/ai.php | grep -c 'id < %d'"
spr "historia trafia do zadania"   "1" "grep -c '(array) \$historia,' $W/includes/ai.php"

echo
echo "=== L. Reguła dopasowania: jedno zrodlo ==="
spr "definicji chatl_trafione_warianty" "1" "grep -rc 'function chatl_trafione_warianty' $W --include=*.php | awk -F: '{s+=\$2} END {print s}'"
spr "mieszka w silnik.php"        "1"  "grep -c 'function chatl_trafione_warianty' $W/includes/silnik.php"

echo
echo "=== L2. Statystyki: znacznik trafienia i grupowanie ==="
# Znacznik MUSI ladowac na obu wierszach wymiany (pytanie goscia + odpowiedz
# bota): raport luk pokazuje TRESC PYTANIA, wiec sam wiersz bota by nie
# wystarczyl. Cztery sciezki odpowiedzi = cztery wywolania chatl_oznacz_trafienie.
spr "sciezek odpowiedzi oznaczajacych trafienie" "4" "grep -c 'chatl_oznacz_trafienie( \$id_pytania' $W/includes/front.php"
spr "definicji chatl_oznacz_trafienie" "1" "grep -rc 'function chatl_oznacz_trafienie' $W --include=*.php | awk -F: '{s+=\$2} END {print s}'"
spr "definicji chatl_grupuj_pytania"   "1" "grep -rc 'function chatl_grupuj_pytania' $W --include=*.php | awk -F: '{s+=\$2} END {print s}'"
spr "grupowanie mieszka w statystyki.php" "1" "grep -c 'function chatl_grupuj_pytania' $W/includes/statystyki.php"
# Wiersze sprzed 1.3.0 (trafiono IS NULL) NIE moga wchodzic do mianownika
# skutecznosci — inaczej panel karze wlasciciela za dane, ktorych nikt nie zmierzyl.
spr "skutecznosc liczona bez wierszy NULL" "1" "grep -c \"chatl_procent( \\\$s\\['odpowiedziano'\\], \\\$s\\['odpowiedziano'\\] + \\\$s\\['bez_odpowiedzi'\\] )\" $W/includes/statystyki.php"

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
# Sciezki do chromium NIE wpisujemy tutaj wprost: numer rewizji przegladarki
# zmienia sie po kazdej aktualizacji playwrighta, a katalog domowy zalezy od
# tego, kto uruchamia kontrole (uwaga recenzenta z PR #66). Jedno miejsce,
# ktore o tym decyduje, to testy/lib/chromium.js — ten sam wykrywacz, ktorego
# uzywaja same testy, wiec skrypt nie moze "pominac" kontroli, ktora testowi
# by sie odpalila.
CHROMIUM=$(node testy/lib/chromium.js 2>/dev/null)
if [ -z "$CHROMIUM" ] || [ ! -d node_modules/playwright-core ]; then
  echo "  POMINIETE  brak chromium albo playwright-core — kontrola ramek tekstow NIE zostala wykonana"
  echo "             (npm install --no-save playwright-core && npx playwright install chromium;"
  echo "              albo wskaz swoja przegladarke: CHATL_CHROMIUM=/sciezka/do/chrome)"
  BLEDY=$((BLEDY+1))
else
  echo "  chromium: $CHROMIUM"
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
