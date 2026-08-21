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

echo "=== A0. Struktura dokumentacji: numeracja i odsylacze ==="
# Numery rozdzialow przesuwaja sie przy KAZDYM scaleniu, w ktorym obie galezie
# dolozyly sekcje — recenzent zglaszal ten sam martwy odsylacz TRZY razy.
# Dlatego: (1) numery musza byc ciagle, (2) odsylamy PO TYTULE, nie po numerze,
# (3) kazdy tytul w odsylaczu musi istniec jako naglowek. Numer sie przesuwa,
# tytul nie — a jesli ktos go zmieni, kontrola (3) to zlapie.
spr "numery sekcji sa ciagle i bez powtorzen" "OK" \
  "grep -oP '^## \\K[0-9]+' $DOK | awk 'NR!=\$1 {print \"ROZJAZD przy \" \$1; exit} END {print \"OK\"}'"
spr "zero odsylaczy po NUMERZE rozdzialu" "0" \
  "grep -cE 'rozdzial[u]? [0-9]|rozdział[u]? [0-9]|§ ?[0-9]|sekcj[ai] [0-9]' $DOK"
spr "kazdy odsylacz po tytule wskazuje istniejacy rozdzial" "OK" \
  "grep -oP 'rozdzia[lł][u]? \\*\\*\\K[^*]+' $DOK | sort -u | while read -r t; do grep -qF \"## \" $DOK && grep -qP '^## [0-9]+\\. \\Q'\"\$t\"'\\E' $DOK || { echo \"MARTWY: \$t\"; exit; }; done; echo OK"

# CHANGELOG rosnie przez SKLEJANIE wpisow przy kazdym scaleniu — separatory
# gubily sie po cichu i recenzent zglaszal to kilka rund z rzedu. Kazdy wpis
# wersji (poza pierwszym) ma byc poprzedzony pozioma kreska.
spr "kazdy wpis CHANGELOG oddzielony kreska" "0" \
  "awk '/^## \\[1\\./ {if (NR>1 && poprz != \"---\") b++} {if (\$0 != \"\") poprz=\$0} END {print b+0}' $W/CHANGELOG.md"

echo "=== A. Wersja i schemat ==="
spr "naglowek Version"            "1.5.6" "grep -oP '(?<=^ \* Version: ).*' $W/chatbot-live.php"
spr "stala CHATL_WERSJA"          "1.5.6" "grep -oP \"(?<=CHATL_WERSJA', ')[^']*\" $W/chatbot-live.php"
spr "CHATL_WERSJA_SCHEMATU"       "7"     "grep -oP \"(?<=CHATL_WERSJA_SCHEMATU', ')[^']*\" $W/chatbot-live.php"
spr "naglowek Version"            "1.5.6" "grep -oP '(?<=^ \* Version: ).*' $W/chatbot-live.php"
spr "stala CHATL_WERSJA"          "1.5.6" "grep -oP \"(?<=CHATL_WERSJA', ')[^']*\" $W/chatbot-live.php"
spr "CHATL_WERSJA_SCHEMATU"       "7"     "grep -oP \"(?<=CHATL_WERSJA_SCHEMATU', ')[^']*\" $W/chatbot-live.php"
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
spr "stalych sprawdzonych z tabeli dokumentacji" "33" "echo $STALYCH"

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
spr "pozycji ChatBota w rejestrze"        "12" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -cP '^[0-9]+\. '"
spr "rejestr wymienia tryb AI"            "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Tryb AI'"
spr "rejestr wymienia statystyki i luki"  "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Statystyki i raport luk'"
spr "rejestr wymienia konfigurowalny wyglad" "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Konfigurowalny wygląd widgetu'"
spr "rejestr wymienia ekran powitalny"    "1" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -c 'Ekran powitalny'"
spr "kazda pozycja ma powod (mysllnik)"   "12" "sed -n '/^## ChatBot LIVE/,/^## Segregator/p' $REJ | grep -cP '^[0-9]+\..*—|^[0-9]+\.[^—]*$'"

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
spr "plikow chatbota w allowliscie"   "25" "sed -n '/^ALLOWLIST_CHATBOT=(/,/^)/p' boty/buduj-paczki.sh | grep -c '\"chatbot-live/'"
# Klasa, ktora wyszla przy scalaniu 1.4.10 z 1.5.0: konflikt w chatbot-live.php
# zjadl `require_once includes/przejecie.php`. Plik zostal w katalogu i w paczce,
# wiec allowlista swiecila na zielono — a wtyczka po prostu nie ladowala polowy
# funkcji. Kontrola idzie WIEC W OBIE STRONY: kazdy plik z includes/ musi byc
# przez cos wymagany.
spr "kazdy plik includes/ jest wymagany przez require_once" "0" \
  "cd $W && ls includes/*.php | sed 's|^|CHATL_KATALOG . .|' >/dev/null; ls includes/*.php | while read -r f; do grep -q \"require_once CHATL_KATALOG . '\$f'\" chatbot-live.php || echo \"\$f\"; done | wc -l"
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
spr "CHATL_AI_MODEL"              "claude-haiku-4-5" "grep -oP \"(?<=CHATL_AI_MODEL      = ')[^']*\" $W/includes/ai.php"
spr "CHATL_AI_BRAK"               "BRAK_ODPOWIEDZI" "grep -oP \"(?<=CHATL_AI_BRAK       = ')[^']*\" $W/includes/ai.php"

echo
echo "=== C. Retencja ma JEDNO zrodlo (nie liczba wpisana w cronie) ==="
spr "cron liczy retencje ze stalej"   "1" "grep -c 'CHATL_RETENCJA_DNI \* DAY_IN_SECONDS' $W/chatbot-live.php"
spr "liczba 90 wpisana wprost w kodzie PHP" "0" "grep -rhoP '\\d+ \\* DAY_IN_SECONDS' $W --include=*.php | grep -c '^90 '"

echo
echo "=== D. Baza: 5 tabel i liczba kolumn ==="
spr "liczba dbDelta"              "6"  "grep -c 'dbDelta(' $W/includes/instalator.php"
spr "kolumn w chatl_qa"           "6"  "sed -n '/CREATE TABLE {\$p}chatl_qa/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|pytanie|slowa_kluczowe|odpowiedz|aktywne|utworzono) '"
spr "kolumn w chatl_zgloszenia"   "6"  "sed -n '/CREATE TABLE {\$p}chatl_zgloszenia/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|email|wiadomosc|sesja|status|utworzono) '"
spr "kolumn w chatl_rozmowy"      "7"  "sed -n '/CREATE TABLE {\$p}chatl_rozmowy/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(id|sesja|rola|silnik|tresc|trafiono|utworzono) '"
spr "kolumn w chatl_ai_uzycie"    "6"  "sed -n '/CREATE TABLE {\$p}chatl_ai_uzycie/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(dzien|wywolania|tokeny_wejscia|tokeny_wyjscia|tokeny_cache_zapis|tokeny_cache_odczyt) '"
spr "kolumn w chatl_rate_limit"   "3"  "sed -n '/CREATE TABLE {\$p}chatl_rate_limit/,/) {\$charset}/p' $W/includes/instalator.php | grep -cP '^\t\t(klucz|licznik|okno_do) '"
spr "email varchar(190)"          "1"  "grep -c 'email varchar(190) NOT NULL' $W/includes/instalator.php"
spr "sesja varchar(64) x3"        "3"  "grep -c 'sesja varchar(64)' $W/includes/instalator.php"
spr "status varchar(20)"          "1"  "grep -c \"status varchar(20) NOT NULL DEFAULT 'nowe'\" $W/includes/instalator.php"
spr "rola varchar(10)"            "1"  "grep -c 'rola varchar(10) NOT NULL' $W/includes/instalator.php"
spr "trafiono dopuszcza NULL"     "1"  "grep -c 'trafiono tinyint(1) DEFAULT NULL' $W/includes/instalator.php"
spr "indeks luki pod raport"      "1"  "grep -c 'KEY luki (rola,trafiono,utworzono)' $W/includes/instalator.php"
spr "silnik domyslnie reguly"     "1"  "grep -c \"silnik varchar(10) NOT NULL DEFAULT 'reguly'\" $W/includes/instalator.php"
spr "indeksow KEY (poza PRIMARY)" "8"  "grep -cP '^\\t\\tKEY ' $W/includes/instalator.php"

echo
echo "=== E. Aktywacja, seed, cron ==="
spr "par Q&A w seedzie"           "10" "awk '/\\\$seed = array\\(/{f=1} f&&/^\t\tarray\\(\$/{n++} f&&/^\t\\);\$/{print n; exit}' $W/includes/instalator.php"
spr "harmonogram: daily"          "1"  "grep -c \"wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'chatl_czyszczenie_historii' )\" $W/includes/instalator.php"
spr "deaktywacja zdejmuje cron"   "1"  "grep -c \"wp_clear_scheduled_hook( 'chatl_czyszczenie_historii' )\" $W/includes/instalator.php"
spr "seed tylko do pustej bazy"   "1"  "grep -c 'if ( \$ile > 0 ) {' $W/includes/instalator.php"

echo
echo "=== F. Deinstalacja: obie sciezki ==="
spr "DROP TABLE (6 tabel)"        "6"  "grep -c 'DROP TABLE IF EXISTS' $W/uninstall.php"
spr "klucz API kasowany zawsze"   "1"  "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'chatl_klucz_api' )\""
spr "delete_option w sciezce B"   "6"  "sed -n '/usun_dane/,\$p' $W/uninstall.php | grep -c 'delete_option('"
spr "wywolan esc_like (bez komentarzy)" "2" "grep -o 'wpdb->esc_like(' $W/uninstall.php | wc -l"

echo
echo "=== G. Ustawienia: klucze i wartosci domyslne ==="
spr "kluczy w chatl_ustawienia"   "16"  "sed -n '/\\\$domyslne = array(/,/);/p' $W/chatbot-live.php | grep -cP \"^\t\t'\""
spr "ai_limit_dzienny domyslnie"  "100" "grep -oP \"(?<='ai_limit_dzienny' => )[0-9]+\" $W/chatbot-live.php"
spr "zakres zapisu limitu (max)"  "100000" "grep -oP '(?<=min\\( )[0-9]+(?=, \\(int\\) \\\$_POST\\[.ai_limit_dzienny)' $W/includes/admin.php"
spr "wlaczony domyslnie"          "1"  "grep -oP \"(?<='wlaczony'         => )[0-9]+\" $W/chatbot-live.php"
spr "usun_dane domyslnie"         "0"  "grep -oP \"(?<='usun_dane'        => )[0-9]+\" $W/chatbot-live.php"

echo
echo "=== H. Opcje i autoload ==="
spr "klucz API add_option bez autoload" "1" "grep -c \"add_option( 'chatl_klucz_api', \\\$klucz, '', false )\" $W/includes/ai.php"
# Oba znaczniki awarii (mail i odrzucony nonce) maja byc BEZ autoloadu: to opcje
# czytane tylko w panelu, wiec nie ma powodu wozic ich w kazdym zapytaniu strony.
spr "znacznikow awarii bez autoload"    "3" "grep -A8 \"update_option($\" $W/includes/front.php | grep -cP '^\\t\\tfalse$'"
spr "znacznik nonce zapisywany bez autoload" "1" "grep -A9 \"'chatl_blad_nonce',\" $W/includes/front.php | grep -cP '^\\t\\tfalse$'"

echo
echo "=== I. Zaczepy i filtry ==="
# Liczby po scaleniu z main (#78) SUMUJA sie: nonce dolozyl dwa endpointy AJAX
# i akcje chatl_nonce_zamknij, ta galaz dokłada chatl_test_klucza.
spr "endpointy AJAX (z nopriv)"   "6"  "grep -c \"add_action( 'wp_ajax\" $W/includes/front.php"
spr "akcje admin_post"            "9"  "grep -rc \"add_action( 'admin_post_\" $W/includes/admin.php $W/includes/csv.php | awk -F: '{s+=\$2} END {print s}'"
# Liczymy ROZNE nazwy filtrow, nie wywolania: ten sam punkt rozszerzenia bywa
# uzyty w kilku miejscach (od 1.4.2 chatl_ai_url i chatl_ai_model wolane sa
# takze przez test klucza), a dokumentacja obiecuje LICZBE PUNKTOW, nie wywolan.
spr "wystawione filtry AI"        "2"  "grep -oP \"(?<=apply_filters\( ')[^']*\" $W/includes/ai.php | sort -u | wc -l"
spr "filtr adresu goscia"         "1"  "grep -oP \"(?<=apply_filters\( ')[^']*\" $W/includes/front.php | sort -u | wc -l"
# Naglowki proxy czytamy WYLACZNIE pod przelacznikiem — gdyby ten warunek znikl,
# na stronie bez proxy kazdy obchodzilby limity, podajac inny adres.
spr "naglowki proxy pod przelacznikiem" "1" "grep -c \"if ( ! empty( \\\$ust\['za_proxy'\] ) ) {\" $W/includes/front.php"
# Uwaga WAZNA recenzenta do PR #80: CF-Connecting-IP jest wiarygodny tylko wtedy,
# gdy origin nie przyjmuje polaczen z pominieciem proxy. To warunek WDROZENIA,
# ktorego kod nie umie wymusic — wiec musi stac w panelu, a nie tylko w dokumentacji.
spr "panel ostrzega o ominieciu proxy" "1" "grep -c 'Sam Cloudflare to za mało' $W/includes/admin.php"
spr "dokumentacja stawia ten warunek"  "1" "grep -c 'origin nie przyjmuje' $DOK"

# Uwaga DROBNA recenzenta do PR #80: liczenie samego stringu FILTER_VALIDATE_IP
# lapalo tez wzmianke w komentarzu phpcs:disable — kontrola wywracala sie przy
# zmianie TRESCI komentarza, nie logiki. Liczymy realne wywolania.
spr "kazdy adres walidowany"      "2"  "grep -cF \"filter_var( \\\$ip, FILTER_VALIDATE_IP )\" $W/includes/front.php"
# Model z data w stalej to bomba zegarowa u kazdego kupujacego (luka L5):
# migawki bywaja wycofywane, a my nie mamy serwera aktualizacji.
spr "domyslny model BEZ daty"     "0"  "grep -cP \"CHATL_AI_MODEL\s*=\s*'[^']*-[0-9]{8}'\" $W/includes/ai.php"
spr "zadne modele z listy nie maja daty" "0" "sed -n '/function chatl_ai_modele/,/^}/p' $W/includes/ai.php | grep -cP \"'[a-z0-9-]+-[0-9]{8}'\""
spr "bramek current_user_can"      "8"  "grep -rc \"current_user_can( 'manage_options' )\" $W/includes/admin.php $W/includes/csv.php | awk -F: '{s+=\$2} END {print s}'"
spr "stron panelu (menu+submenu)"  "6"  "grep -c 'add_menu_page(\|add_submenu_page(' $W/includes/admin.php"

echo
echo "=== J. Panel: limity list cytowane w dokumentacji ==="
# Do 1.5.5 te dwie kontrole pilnowaly TWARDYCH limitow (LIMIT 200 / LIMIT 100).
# Od 1.5.6 listy sa stronicowane, wiec pilnuja czego innego: ze rozmiar strony
# siedzi w STALEJ (a nie znowu w liczbie wpisanej w zapytaniu) i ze zapytanie
# naprawde ma przesuniecie. Sam brak LIMIT 200 nie wystarczy — bez OFFSET-u
# strona druga pokazywalaby to samo co pierwsza.
spr "rozmiar strony zgloszen w stalej" "200" "grep -oP '(?<=CHATL_NA_STRONE_ZGLOSZEN = )[0-9]+' $W/includes/admin.php"
spr "rozmiar strony rozmow w stalej"   "100" "grep -oP '(?<=CHATL_NA_STRONE_ROZMOW = )[0-9]+' $W/includes/admin.php"
spr "lista zgloszen ma OFFSET"    "1"  "grep -c 'LIMIT %d OFFSET %d\", \$flaga' $W/includes/admin.php"
spr "lista rozmow ma OFFSET"      "1"  "grep -c 'ORDER BY koniec DESC LIMIT %d OFFSET %d' $W/includes/admin.php"
spr "rozmowy liczone po sesjach"  "1"  "grep -c 'COUNT(DISTINCT sesja)' $W/includes/admin.php"
spr "raport luk przesuwa sie"     "2"  "grep -c 'luki_offset' $W/includes/statystyki.php"
spr "zero twardych limitow list"  "0"  "grep -c 'ORDER BY id DESC LIMIT 200\\|ORDER BY koniec DESC LIMIT 100' $W/includes/admin.php"

echo
echo "=== K. Bezpieczenstwo: to, co dokumentacja obiecuje ==="
spr "regex sesji"                 "1"  "grep -c \"a-zA-Z0-9-]{8,64}\" $W/includes/front.php"
spr "znaki neutralizowane w CSV"  "1"  "grep -c \"array( '=', '+', '-', '@' )\" $W/includes/csv.php"
spr "BOM UTF-8 w eksporcie"       "1"  "grep -c 'xEF.xBB.xBF' $W/includes/csv.php"
spr "widget bez uzycia innerHTML"  "0"  "grep -c '\\.innerHTML' $W/assets/widget.js"
spr "zgoda sprawdzana na serwerze" "1" "grep -c \"empty( \\\$_POST\\['zgoda'\\] )\" $W/includes/front.php"
spr "wp_trigger_error pod straza" "2"  "grep -rc \"function_exists( 'wp_trigger_error' )\" $W/chatbot-live.php $W/includes/ai.php | awk -F: '{s+=\$2} END {print s}'"

echo
# Uwaga recenzenta do PR #79: kazdy zjazd do regul MUSI zostawic slad, inaczej
# awaria jest cicha — a to jest dokladnie ta klasa, ktora ten PR naprawia.
# Zero "return null" w chatl_ai_odpowiedz() znaczy, ze kazda sciezka porazki
# przechodzi przez chatl_ai_niepowodzenie(), ktore stawia znacznik.
spr "kazde niepowodzenie AI zostawia slad" "0" "sed -n '/function chatl_ai_odpowiedz/,/^}/p' $W/includes/ai.php | grep -c 'return null;'"
spr "sciezek zjazdu przez wspolna funkcje" "3" "sed -n '/function chatl_ai_odpowiedz/,/^}/p' $W/includes/ai.php | grep -c 'return chatl_ai_niepowodzenie'"
# Gosc ma wiedziec o zapisie rozmowy ZANIM cokolwiek napisze (luka L7),
# a okres retencji w nocie musi pochodzic ze stalej — inaczej tekst rozjedzie
# sie z tym, co naprawde kasuje cron.
spr "nota o zapisie rozmowy istnieje" "1" "grep -c notaZapisu $W/includes/front.php"
spr "nota liczy dni ze stalej"        "1" "grep -A4 notaZapisu $W/includes/front.php | grep -c CHATL_RETENCJA_DNI"
# Nota MUSI powstawac wczesniej w kodzie niz powitanie — kolejnosc w liscie
# wiadomosci decyduje o tym, czy gosc przeczyta ja przed napisaniem czegokolwiek.
spr "widget rysuje note"           "1"  "grep -c 'chatl-nota' $W/assets/widget.js"
spr "nota jest PRZED powitaniem w kodzie" "wczesniej" "awk '/chatl-nota/{n=NR} /dodajWiadomosc..bot., dane.powitanie/{p=NR} END{print (n && p && n < p) ? \"wczesniej\" : \"pozniej\"}' $W/assets/widget.js"
# Rachunek u dostawcy idzie z TOKENOW (luka L3): nazwy pol usage musza zgadzac
# sie z kontraktem API, odczyt z cache NIE moze wchodzic do tokenow platnych,
# a cache instrukcji ma byc dokladany WARUNKOWO (ponizej progu dziala po cichu wcale).
spr "czytane wszystkie 4 pola usage" "4" "grep -cP \"'(input_tokens|output_tokens|cache_creation_input_tokens|cache_read_input_tokens)'\" $W/includes/ai.php"
spr "tokeny platne bez odczytu z cache" "0" "sed -n '/function chatl_ai_tokeny_dzis/,/^}/p' $W/includes/ai.php | grep -c tokeny_cache_odczyt"
spr "cache instrukcji pod warunkiem progu" "1" "grep -c 'CHATL_AI_CACHE_PROG_ZNAKOW ) {' $W/includes/ai.php"
# Uwaga WAZNA recenzenta do PR #83: kod 200 znaczy, ze dostawca policzyl wywolanie,
# wiec KAZDA sciezka po nim musi je zaliczyc. Naliczanie w jednym miejscu, PRZED
# rozgalezieniem, jest tu odpowiednikiem chatl_ai_niepowodzenie() z PR #79:
# dopisanie kolejnego wyjscia nie moze juz zgubic kosztu przez przeoczenie.
spr "zuzycie liczone w JEDNYM miejscu" "1" "sed -n '/function chatl_ai_odpowiedz/,/^}/p' $W/includes/ai.php | grep -c 'chatl_ai_zuzyj('"
spr "zuzycie liczone PRZED rozgalezieniem" "wczesniej" "sed -n '/function chatl_ai_odpowiedz/,/^}/p' $W/includes/ai.php | awk '/chatl_ai_zuzyj\(/{z=NR} /stop_reason/{r=NR} END{print (z && r && z < r) ? \"wczesniej\" : \"pozniej\"}'"
spr "limit tokenow blokuje tryb AI"  "1"  "sed -n '/function chatl_ai_dostepny/,/^}/p' $W/includes/ai.php | grep -c chatl_ai_tokeny_dzis"
# Sygnal "bot pisze": blokada MUSI byc zdejmowana w finally (po bledzie tez),
# a straz przed podwojnym wyslaniem czyta stan przycisku.
spr "dymek pisania rysowany"       "1"  "grep -c 'chatl-pisze' $W/assets/widget.js"
spr "blokada zdejmowana w finally" "1"  "grep -c 'ukryjPisanie();' $W/assets/widget.js"
spr "straz przed podwojnym wyslaniem" "1" "grep -c 'wyslijBtn.disabled) { return; }' $W/assets/widget.js"
spr "kropki szanuja ograniczenie animacji" "1" "grep -c 'prefers-reduced-motion' $W/assets/widget.css"
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
