#!/bin/bash
# Czy DOKUMENTACJA-TECHNICZNA.md i DIAGRAM-PRZEPLYWU.svg Segregatora mowia to samo,
# co kod? Wzorowane 1:1 na testy/dokumentacja-zgodnosc.sh (ChatBot).
#
# Najczestsza uwaga egzaminatora w poprzednim projekcie: dokumentacja rozjechana
# z kodem. Ten skrypt sprawdza KAZDA liczbe i nazwe z dokumentacji grepem po
# zrodle. Uruchamiac po kazdej zmianie kodu segregatora.
#
# Uzycie: testy/dokumentacja-zgodnosc-segregator.sh     (z katalogu repo)
set -u
W=wtyczki/segregator-maila
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

echo "=== A. Wersja, naglowek, licencja ==="
spr "naglowek Version"               "1.3.3" "grep -oP '(?<=^ \* Version: ).*' $W/segregator-maila.php"
spr "stala SEGM_WERSJA"              "1.3.3" "grep -oP \"(?<=SEGM_WERSJA', ')[^']*\" $W/segregator-maila.php"
spr "SEGM_WERSJA_SCHEMATU"           "4"     "grep -oP \"(?<=SEGM_WERSJA_SCHEMATU', ')[^']*\" $W/segregator-maila.php"
spr "Requires at least"              "6.0"   "grep -oP '(?<=^ \* Requires at least: ).*' $W/segregator-maila.php"
spr "Requires PHP"                   "8.1"   "grep -oP '(?<=^ \* Requires PHP: ).*' $W/segregator-maila.php"
spr "naglowek Author"                "Automatic AI" "grep -oP '(?<=^ \* Author: ).*' $W/segregator-maila.php"
spr "naglowek License"               "GPL-2.0-or-later" "grep -oP '(?<=^ \* License: ).*' $W/segregator-maila.php"
spr "naglowek License URI"           "https://www.gnu.org/licenses/gpl-2.0.html" "grep -oP '(?<=^ \* License URI: ).*' $W/segregator-maila.php"
spr "naglowek Text Domain"           "segregator-maila" "grep -oP '(?<=^ \* Text Domain: ).*' $W/segregator-maila.php"
spr "dokumentacja podaje te wersje"  "1" "grep -c '\*\*Wersja wtyczki: 1.3.3 · wersja schematu bazy: 4 · WordPress 6.0+ · PHP 8.1+\*\*' $DOK"
spr "LICENSE istnieje"               "1" "test -f $W/LICENSE && echo 1 || echo 0"
spr "LICENSE zaczyna sie GPL"        "1" "head -1 $W/LICENSE | grep -c 'GNU GENERAL PUBLIC LICENSE'"

echo
echo "=== B. Stale konfiguracyjne (kazda liczba z tabeli w par. 6) ==="
spr "SEGM_RETENCJA_DNI"              "90"      "grep -oP '(?<=SEGM_RETENCJA_DNI = )\d+' $W/segregator-maila.php"
spr "SEGM_BLOKADA"                   "segm_operacja_w_toku" "grep -oP \"(?<=SEGM_BLOKADA       = ')[^']*\" $W/segregator-maila.php"
spr "SEGM_BLOKADA_WAZNA"             "120"     "grep -oP '(?<=SEGM_BLOKADA_WAZNA = )\d+' $W/segregator-maila.php"
spr "SEGM_KUBEL_DOMYSLNY"            "Reszta"  "grep -oP \"(?<=SEGM_KUBEL_DOMYSLNY = ')[^']*\" $W/includes/silnik.php"
spr "SEGM_IMPORT_MAX_WIERSZY"        "500"     "grep -oP '(?<=SEGM_IMPORT_MAX_WIERSZY = )\d+' $W/includes/csv.php"
spr "SEGM_IMPORT_MAX_BAJTOW"         "5242880" "grep -oP '(?<=SEGM_IMPORT_MAX_BAJTOW = )\d+' $W/includes/csv.php"
spr "SEGM_MAX_POLE"                  "2000"    "grep -oP '(?<=SEGM_MAX_POLE           = )\d+' $W/includes/csv.php"
spr "SEGM_PROBKA_KODOWANIA"          "65536"   "grep -oP '(?<=SEGM_PROBKA_KODOWANIA = )\d+' $W/includes/csv.php"
spr "SEGM_STALA_HASLA"               "SEGM_IMAP_HASLO" "grep -oP \"(?<=SEGM_STALA_HASLA = ')[^']*\" $W/includes/skrzynka.php"
spr "SEGM_SKRZ_TRESC_BAJTOW"         "4096"    "grep -oP '(?<=SEGM_SKRZ_TRESC_BAJTOW = )\d+' $W/includes/skrzynka.php"
spr "SEGM_SKRZ_LIMIT_CZASU"          "20"      "grep -oP '(?<=SEGM_SKRZ_LIMIT_CZASU = )\d+' $W/includes/skrzynka.php"
spr "SEGM_SKRZ_LIMIT_GNIAZDA"        "15"      "grep -oP '(?<=SEGM_SKRZ_LIMIT_GNIAZDA = )\d+' $W/includes/skrzynka.php"
spr "SEGM_AI_MODEL"                  "claude-haiku-4-5" "grep -oP \"(?<=SEGM_AI_MODEL      = ')[^']*\" $W/includes/ai.php"
spr "domyslny model BEZ daty w identyfikatorze" "0" "grep -oP \"(?<=SEGM_AI_MODEL      = ')[^']*\" $W/includes/ai.php | grep -cP -- '-\\d{8}$'"
spr "zadna podpowiedz w liscie nie ma daty" "0" "sed -n '/function segm_ai_modele/,/^}/p' $W/includes/ai.php | grep -cP \"'[a-z0-9.-]*-\\d{8}'\""
spr "SEGM_AI_TIMEOUT"                "9"       "grep -oP '(?<=SEGM_AI_TIMEOUT    = )\d+' $W/includes/ai.php"
spr "SEGM_AI_PARTIA"                 "30"      "grep -oP '(?<=SEGM_AI_PARTIA     = )\d+' $W/includes/ai.php"
spr "SEGM_AI_MAX_WYWOLAN_PRZEBIEG"   "1"       "grep -oP '(?<=SEGM_AI_MAX_WYWOLAN_PRZEBIEG = )\d+' $W/includes/ai.php"
spr "SEGM_FILTRY_STRONA"             "segm-filtry" "grep -oP \"(?<=SEGM_FILTRY_STRONA = ')[^']*\" $W/includes/filtry.php"
spr "Segm_Imap::DZIENNIK_LINII"      "120"     "grep -oP '(?<=DZIENNIK_LINII = )\d+' $W/includes/class-segm-imap.php"
spr "Segm_Imap::DZIENNIK_ZNAKOW"     "300"     "grep -oP '(?<=DZIENNIK_ZNAKOW = )\d+' $W/includes/class-segm-imap.php"

echo
echo "=== C. Tabele: 7 sztuk, kazda w 3 miejscach (dbDelta + DROP + dokumentacja) ==="
TABELE="segm_kategorie segm_reguly segm_maile segm_przebiegi segm_ai_uzycie segm_skrz_przebiegi segm_skrz_ruchy"
spr "liczba CREATE TABLE w instalatorze" "7" "grep -c 'CREATE TABLE {\$p}segm_' $W/includes/instalator.php"
for t in $TABELE; do
  spr "tabela $t: dbDelta"      "1" "grep -c 'CREATE TABLE {\$p}$t ' $W/includes/instalator.php"
  spr "tabela $t: DROP w uninstall" "1" "grep -c 'DROP TABLE IF EXISTS {\$wpdb->prefix}$t\"' $W/uninstall.php"
  spr "tabela $t: opisana w dokumentacji" "1" "grep -c '\`$t\`' $DOK | awk '{print (\$1>0)?1:0}'"
done

echo
echo "=== D. Punkty wejscia ==="
spr "liczba uchwytow admin_post"     "30" "grep -rhoP 'add_action\( .admin_post_segm_[a-z_]+' $W --include='*.php' | sort -u | wc -l"
spr "dokumentacja podaje te liczbe"  "1"  "grep -c 'admin-post\` — 30 sztuk' $DOK"
spr "ZERO punktow dla niezalogowanych" "0" "grep -rc 'wp_ajax_nopriv' $W --include='*.php' | grep -v ':0' | wc -l"
spr "ZERO tras REST"                 "0"  "grep -rc 'register_rest_route' $W --include='*.php' | grep -v ':0' | wc -l"
spr "ZERO zaczepow frontu"           "0"  "grep -rc 'template_redirect\|wp_enqueue_scripts\|add_shortcode' $W --include='*.php' | grep -v ':0' | wc -l"
spr "liczba RÓŻNYCH stron panelu"    "8"  "{ grep -rhoP \"add_(menu|submenu)_page\(\s*'?[^,]*,?\" $W --include='*.php' >/dev/null; grep -rhoP \"'segm-[a-z]+'(?=, '[a-z_]+' \))|SEGM_FILTRY_STRONA|SEGM_POWITANIE_STRONA\" $W --include='*.php'; } | sort -u | wc -l"

echo "  --- kazdy uchwyt admin_post: capability I nonce ---"
BEZ_CAP=0; BEZ_NONCE=0
for f in $(grep -rhoP "(?<=add_action\( ')admin_post_segm_[a-z_]+" $W --include='*.php' | sort -u); do
  fn=$(grep -rhoP "add_action\( '$f', '\K[a-z_]+" $W --include='*.php')
  cap=$(awk "/^function $fn\(/,/^}/" $W/includes/*.php | grep -c "current_user_can( 'manage_options' )")
  non=$(awk "/^function $fn\(/,/^}/" $W/includes/*.php | grep -c "check_admin_referer(")
  [ "$cap" -ge 1 ] || { BEZ_CAP=$((BEZ_CAP+1)); echo "         BRAK capability w $fn"; }
  [ "$non" -ge 1 ] || { BEZ_NONCE=$((BEZ_NONCE+1)); echo "         BRAK nonce w $fn"; }
done
spr "uchwytow BEZ current_user_can"  "0" "echo $BEZ_CAP"
spr "uchwytow BEZ check_admin_referer" "0" "echo $BEZ_NONCE"

echo
echo "=== E. Harmonogramy WP-Cron ==="
spr "liczba wlasnych harmonogramow"  "5"    "grep -oP \"'segm_(co|raz)_[a-z0-9_]+' *=> array\" $W/includes/skrzynka-przebieg.php | wc -l"
spr "segm_co_15_minut = 900 s"       "900"  "grep -oP '(?<=15 \* MINUTE_IN_SECONDS)' $W/includes/skrzynka-przebieg.php >/dev/null && echo 900"
spr "zaczep retencji"                "1"    "grep -c \"add_action( 'segm_czyszczenie_probki'\" $W/segregator-maila.php"
spr "zaczep skrzynki"                "1"    "grep -c \"add_action( 'segm_skrzynka_cron'\" $W/includes/skrzynka-przebieg.php"
spr "deaktywacja zdejmuje OBA crony" "2"    "grep -c 'wp_clear_scheduled_hook' $W/includes/instalator.php"
spr "uninstall zdejmuje OBA crony"   "2"    "grep -c 'wp_clear_scheduled_hook' $W/uninstall.php"

echo
echo "=== F. Retencja obejmuje WSZYSTKIE tabele z cudza korespondencja ==="
for t in segm_maile segm_przebiegi segm_skrz_ruchy segm_skrz_przebiegi; do
  spr "retencja kasuje $t" "1" "sed -n '/function segm_czyszczenie_probki/,/^}/p' $W/segregator-maila.php | grep -c \"prefix . '$t'\""
done
spr "dokumentacja mowi o czterech tabelach" "1" "grep -c 'z \*\*czterech\*\*' $DOK"

echo
echo "=== G. Haslo do skrzynki — twierdzenia z par. 9.3 ==="
spr "opcja hasla z autoload=no"      "1" "grep -c \"add_option( 'segm_imap_haslo', \\\$haslo, '', false )\" $W/includes/skrzynka.php"
spr "stala ma pierwszenstwo"         "1" "sed -n '/function segm_skrzynka_haslo(/,/^}/p' $W/includes/skrzynka.php | grep -c 'segm_skrzynka_haslo_ze_stalej()'"
spr "stala blokuje zapis do bazy"    "1" "sed -n '/function segm_skrzynka_zapisz_haslo/,/^}/p' $W/includes/skrzynka.php | grep -c 'segm_skrzynka_haslo_ze_stalej()'"
spr "LOGIN maskowany w dzienniku"    "1" "grep -c \"'LOGIN ' . \\\$this->tekst( \\\$login ) . ' \\*\\*\\*\\*'\" $W/includes/class-segm-imap.php"
spr "haslo nie wraca do formularza"  "0" "grep -c 'name=\"haslo\" [^>]*value=\"<?php echo' $W/includes/admin-skrzynka.php"
spr "uninstall kasuje haslo ZAWSZE"  "1" "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'segm_imap_haslo' )\""
spr "uninstall kasuje klucz API ZAWSZE" "1" "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'segm_klucz_api' )\""
spr "uninstall kasuje obie zgody ZAWSZE" "2" "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'segm_zgoda_\""
spr "uninstall kasuje transienty panelu" "1" "grep -c \"'segm_komunikat_', 'segm_liczenie_'\" $W/uninstall.php"
spr "uninstall bierze OBA przedrostki transientow" "1" "grep -c \"'_transient_', '_transient_timeout_'\" $W/uninstall.php"

echo
echo "=== H. Tryb na sucho — twierdzenia z par. 9.2 ==="
spr "EXAMINE dla trybu tylko-odczyt" "1" "grep -c \"tylko_odczyt ? 'EXAMINE ' : 'SELECT '\" $W/includes/class-segm-imap.php"
spr "przebieg otwiera wg trybu"      "1" "grep -c 'otworz( \$zrodlowy, ! \$na_zywo )' $W/includes/skrzynka-przebieg.php"
spr "domyslny tryb w ustawieniach"   "na_sucho" "grep -oP \"(?<='tryb'                 => ')[^']*\" $W/includes/skrzynka.php"
spr "realne przenoszenie: DWA warunki" "1" "sed -n '/function segm_przenoszenie_dozwolone/,/^}/p' $W/includes/skrzynka.php | grep -c \"'na_zywo' === \\\$ust\\['tryb'\\] && null !== segm_zgoda_przenoszenia()\""
spr "wysylane FETCH-e uzywaja BODY.PEEK" "2" "grep -c 'wykonaj( .UID FETCH.*BODY.PEEK' $W/includes/class-segm-imap.php"
spr "ZERO wysylanych FETCH-y z BODY bez PEEK" "0" "grep 'wykonaj( ' $W/includes/class-segm-imap.php | grep -c 'BODY\[' "
spr "cofanie pilnuje OSTATNIEGO"     "1" "sed -n '/function segm_skrzynka_cofnij/,/^}/p' $W/includes/skrzynka-przebieg.php | grep -c 'segm_skrzynka_ostatni_do_cofniecia()'"

echo
echo "=== I. Klient IMAP — polecenia deklarowane w par. 10 ==="
for p in CAPABILITY LOGIN LOGOUT STARTTLS 'LIST ' EXAMINE SELECT CREATE SUBSCRIBE 'UID SEARCH' 'UID FETCH' 'UID MOVE' 'UID COPY' 'UID STORE' 'UID EXPUNGE'; do
  spr "klient wysyla: $p" "1" "grep -c \"'$p\" $W/includes/class-segm-imap.php | awk '{print (\$1>0)?1:0}'"
done
spr "TLS wylacznie 1.2 i 1.3"        "1" "grep -c 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' $W/includes/class-segm-imap.php"
spr "weryfikacja certyfikatu domyslnie ON" "0" "grep -oP \"(?<='ufaj_bez_cert'        => )\d\" $W/includes/skrzynka.php"
spr "wykrywa LOGINDISABLED"          "1" "grep -c \"ma_moznosc( 'LOGINDISABLED' )\" $W/includes/class-segm-imap.php"
spr "BRAK wysylanego AUTHENTICATE/XOAUTH2" "0" "grep 'wykonaj( ' $W/includes/class-segm-imap.php | grep -c 'AUTHENTICATE\|XOAUTH2'"

echo
echo "=== J. Domyslne ustawienia skrzynki (tabela z par. 9.1) ==="
spr "port"                 "993"          "grep -oP \"(?<='port'                 => )\d+\" $W/includes/skrzynka.php"
spr "szyfrowanie"          "ssl"          "grep -oP \"(?<='szyfrowanie'          => ')[^']*\" $W/includes/skrzynka.php"
spr "folder_zrodlowy"      "INBOX"        "grep -oP \"(?<='folder_zrodlowy'      => ')[^']*\" $W/includes/skrzynka.php"
spr "prefiks"              "Segregator"   "grep -oP \"(?<='prefiks'              => ')[^']*\" $W/includes/skrzynka.php"
spr "limit"                "50"           "grep -oP \"(?<='limit'                => )\d+\" $W/includes/skrzynka.php"
spr "dopasuj_tresc"        "1"            "grep -oP \"(?<='dopasuj_tresc'        => )\d\" $W/includes/skrzynka.php"
spr "tylko_nieprzeczytane" "0"            "grep -oP \"(?<='tylko_nieprzeczytane' => )\d\" $W/includes/skrzynka.php"
spr "ruszaj_reszte"        "0"            "grep -oP \"(?<='ruszaj_reszte'        => )\d\" $W/includes/skrzynka.php"
spr "czestotliwosc"        "wylaczone"    "grep -oP \"(?<='czestotliwosc'        => ')[^']*\" $W/includes/skrzynka.php"
spr "ai_limit_dzienny"     "100"          "grep -oP \"(?<='ai_limit_dzienny' => )\d+\" $W/segregator-maila.php"
spr "usun_dane domyslnie wylaczone" "0"   "grep -oP \"(?<='usun_dane'        => )\d\" $W/segregator-maila.php"

echo
echo "=== K. Seed demo (liczby z par. 11.1) ==="
# Liczymy poza spr(), bo wzorce z $ i cudzyslowami nie przezywaja podwojnego eval.
KAT=$(grep -oP "(?<=kategorie = array\( ).*(?=\);)" $W/includes/instalator.php | tr -cd "'" | wc -c)
KAT=$((KAT / 2))
REG=$(awk '/function segm_reguly_demo/,/^}/' $W/includes/instalator.php | grep -cE "array\( '(od|temat|tresc)',")
MAI=$(awk '/function segm_wstaw_probke_demo/,/^}/' $W/includes/instalator.php | grep -cE "array\( '[^']+@")
spr "kategorii demo"  "5"  "echo $KAT"
spr "regul demo"      "8"  "echo $REG"
spr "maili demo"      "30" "echo $MAI"

echo "=== L. Liczby linii z tabeli architektury (par. 3) ==="
# Odwrotne cudzyslowy w markdownie kolidowaly z podstawieniem polecen w eval —
# dlatego wzorzec budujemy tu i podajemy do spr() gotowa liczbe.
BACKTICK=$(printf '\140')
for f in segregator-maila.php uninstall.php includes/admin-powitanie.php includes/admin-probka.php includes/konfiguracja.php includes/instalator.php includes/silnik.php includes/csv.php includes/ai.php includes/admin.php includes/filtry.php includes/class-segm-imap.php includes/skrzynka.php includes/skrzynka-przebieg.php includes/admin-skrzynka.php; do
  ILE=$(wc -l < $W/$f)
  WZOR="${BACKTICK}${f}${BACKTICK} | ${ILE} |"
  TRAF=$(grep -cF "$WZOR" $DOK)
  spr "wiersz tabeli: $f = $ILE linii" "1" "echo $TRAF"
done

echo "=== M. Paczka produktowa i diagram ==="
spr "DOKUMENTACJA na liscie paczki" "1" "grep -c 'segregator-maila/DOKUMENTACJA-TECHNICZNA.md' boty/buduj-paczki.sh"
spr "DIAGRAM na liscie paczki"      "1" "grep -c 'segregator-maila/DIAGRAM-PRZEPLYWU.svg' boty/buduj-paczki.sh"
spr "CHANGELOG na liscie paczki"    "1" "grep -c 'segregator-maila/CHANGELOG.md' boty/buduj-paczki.sh"
spr "readme.txt na liscie paczki"   "1" "grep -c 'segregator-maila/readme.txt' boty/buduj-paczki.sh"
spr "ekran powitalny na liscie paczki" "1" "grep -c 'segregator-maila/includes/admin-powitanie.php' boty/buduj-paczki.sh"
spr "praca na pojedynczym mailu w paczce" "1" "grep -c 'segregator-maila/includes/admin-probka.php' boty/buduj-paczki.sh"
spr "konfiguracja w paczce" "1" "grep -c 'segregator-maila/includes/konfiguracja.php' boty/buduj-paczki.sh"
spr "diagram istnieje"              "1" "test -f $SVG && echo 1 || echo 0"
# xmlns="http://www.w3.org/2000/svg" to WYMAGANA deklaracja przestrzeni nazw XML,
# a nie odwolanie sieciowe — przeglądarka niczego stamtad nie pobiera.
spr "diagram BEZ odwolan zewnetrznych" "0" "grep -n 'https\\?://' $SVG | grep -vc 'xmlns='"
spr "diagram BEZ obrazow, skryptow i importow" "0" "grep -c 'xlink:href\|<image\|<script\|@import\|url(http' $SVG"
spr "diagram: tlo palety sklepu"    "1" "grep -c '#08090b' $SVG | awk '{print (\$1>0)?1:0}'"
spr "diagram: limonka"              "1" "grep -ci '#BFFF38' $SVG | awk '{print (\$1>0)?1:0}'"
spr "diagram: tekst jasny"          "1" "grep -ci '#F5F5F5' $SVG | awk '{print (\$1>0)?1:0}'"
spr "diagram: szary"                "1" "grep -ci '#8F929C' $SVG | awk '{print (\$1>0)?1:0}'"

echo "=== R. Iteracja na pojedynczym mailu i licznik regul ==="
spr "dodanie/edycja jednego maila"   "1" "grep -c \"add_action( 'admin_post_segm_mail_zapisz'\" $W/includes/admin-probka.php"
spr "usuniecie jednego maila"        "1" "grep -c \"add_action( 'admin_post_segm_mail_usun'\" $W/includes/admin-probka.php"
spr "liczenie bez zapisywania"       "1" "grep -c \"add_action( 'admin_post_segm_regula_policz'\" $W/includes/admin-probka.php"
spr "licznik uzywa silnika, nie wlasnej logiki" "1" "sed -n '/function segm_policz_trafienia/,/^}/p' $W/includes/admin-probka.php | grep -c 'segm_klasyfikuj_mail('"
spr "jedna regula dopasowania (silnik + panel)" "1" "grep -c 'function segm_wzorzec_pasuje' $W/includes/silnik.php"
spr "silnik wola te sama funkcje"    "1" "sed -n '/function segm_klasyfikuj_mail/,/^}/p' $W/includes/silnik.php | grep -c 'segm_wzorzec_pasuje('"
spr "maile reczne oznaczone zrodlem" "1" "grep -c \"'zrodlo'    = 'reczny'\|= 'reczny'\" $W/includes/admin-probka.php"
spr "nonce liczenia ma wlasna nazwe" "1" "grep -c \"check_admin_referer( 'segm_regula_policz', '_wpnonce_policz' )\" $W/includes/admin-probka.php"
spr "uninstall kasuje transienty liczenia" "1" "grep -c 'segm_liczenie_' $W/uninstall.php | awk '{print (\$1>0)?1:0}'"
spr "dokumentacja opisuje iteracje" "1" "grep -c '8.1. Iteracja na pojedynczym przypadku' $DOK"
# --- straznicy uwag z recenzji PR #75 (liczone poza spr(), bo wzorce z $ i
# cudzyslowami nie przezywaja podwojnego eval) ---
PROBKA=$W/includes/admin-probka.php
G1=$(awk '/function segm_post_mail_zapisz/,/^}/' $PROBKA | grep -c 'if ( 0 === \$zapis )')
G2=$(awk '/function segm_post_mail_zapisz/,/^}/' $PROBKA | grep -c 'NIE MA już w próbce')
G3=$(awk '/function segm_policz_trafienia/,/^}/' $PROBKA | grep -c "pusty_wzorzec'\] = true")
LIN_MAILI=$(awk '/function segm_policz_trafienia/,/^}/' $PROBKA | grep -n "wynik\['maili'\] = count" | head -1 | cut -d: -f1)
LIN_WZORZEC=$(awk '/function segm_policz_trafienia/,/^}/' $PROBKA | grep -n "wzorzec = trim" | head -1 | cut -d: -f1)
G5=$(awk '/function segm_opis_trafien/,/^}/' $PROBKA | grep -c 'pole wzorca jest puste')
spr "0 z update() sprawdzane w bazie"          "1" "echo $G1"
spr "brak wiersza = komunikat, nie sukces"     "1" "echo $G2"
spr "pusty wzorzec ma wlasny znacznik"         "1" "echo $G3"
spr "liczba maili ustalana PRZED wzorcem"      "1" "test -n \"$LIN_MAILI\" && test -n \"$LIN_WZORZEC\" && test \"$LIN_MAILI\" -lt \"$LIN_WZORZEC\" && echo 1 || echo 0"
spr "pusta probka zglaszana PRZED pustym wzorcem" "1" "awk '/function segm_opis_trafien/,/^}/' $PROBKA | grep -n \"maili.\] )\|pusty_wzorzec\" | head -2 | head -1 | grep -c maili"
spr "opis rozroznia pusty wzorzec od pustej probki" "1" "echo $G5"

echo "=== S. Eksport i import konfiguracji ==="
spr "SEGM_KONFIG_FORMAT"             "1"      "grep -oP '(?<=SEGM_KONFIG_FORMAT = )\d+' $W/includes/konfiguracja.php"
spr "SEGM_KONFIG_MAX_BAJTOW"         "524288" "grep -oP '(?<=SEGM_KONFIG_MAX_BAJTOW = )\d+' $W/includes/konfiguracja.php"
spr "SEGM_KONFIG_MAX_WPISOW"         "500"    "grep -oP '(?<=SEGM_KONFIG_MAX_WPISOW = )\d+' $W/includes/konfiguracja.php"
spr "SEGM_KONFIG_MAX_WZORZEC"        "2000"   "grep -oP '(?<=SEGM_KONFIG_MAX_WZORZEC = )\d+' $W/includes/konfiguracja.php"
spr "eksport konfiguracji"           "1" "grep -c \"add_action( 'admin_post_segm_konfig_eksport'\" $W/includes/konfiguracja.php"
spr "import konfiguracji"            "1" "grep -c \"add_action( 'admin_post_segm_konfig_import'\" $W/includes/konfiguracja.php"
spr "regula wiazana po NAZWIE kategorii" "1" "sed -n '/function segm_konfig_zbierz/,/^}/p' $W/includes/konfiguracja.php | grep -c \"'kategoria' => (string) \\\$r->kategoria\""
spr "plik BEZ hasla do skrzynki"     "0" "sed -n '/function segm_konfig_zbierz/,/^}/p' $W/includes/konfiguracja.php | grep -c 'segm_skrzynka_haslo\|segm_klucz_api'"
spr "plik BEZ probki i historii"     "0" "sed -n '/function segm_konfig_zbierz/,/^}/p' $W/includes/konfiguracja.php | grep -c 'segm_maile\|segm_skrz_ruchy\|segm_przebiegi'"
spr "plik BEZ zapisanych zgod"       "0" "sed -n '/function segm_konfig_zbierz/,/^}/p' $W/includes/konfiguracja.php | grep -c 'segm_zgoda'"
spr "import sprawdza znacznik wtyczki" "1" "grep -c \"'segregator-maila' !== \\\$dane\\['wtyczka'\\]\" $W/includes/konfiguracja.php"
spr "import sprawdza wersje formatu" "1" "grep -c 'SEGM_KONFIG_FORMAT' $W/includes/konfiguracja.php | awk '{print (\$1>1)?1:0}'"
spr "import bierze blokade operacji" "1" "sed -n '/function segm_post_konfig_import/,/^}/p' $W/includes/konfiguracja.php | grep -c 'segm_zajmij_blokade'"
spr "dokumentacja opisuje przeprowadzke" "1" "grep -c '8.2. Przeprowadzka' $DOK"

echo "=== T. Wybor modelu AI ==="
spr "ustawienie ai_model ma wartosc domyslna" "1" "sed -n '/function segm_ustawienia/,/^}/p' $W/segregator-maila.php | grep -c \"'ai_model'         => ''\""
spr "model czytany z ustawien"       "1" "grep -c \"apply_filters( 'segm_ai_model', segm_ai_model() )\" $W/includes/ai.php"
spr "filtr nadal na wierzchu"        "1" "grep -c \"apply_filters( 'segm_ai_model'\" $W/includes/ai.php"
spr "wzorzec nazwy modelu"           "1" "grep -c 'SEGM_AI_MODEL_WZORZEC' $W/includes/ai.php | awk '{print (\$1>2)?1:0}'"
spr "zla nazwa NIE kasuje ustawienia" "1" "sed -n '/function segm_wybrany_model/,/^}/p' $W/includes/ai.php | grep -c 'return (string) \\\$poprzedni;'"
spr "pole w formularzu ustawien"     "1" "grep -c \"name=.ai_model.\" $W/includes/admin.php | awk '{print (\$1>0)?1:0}'"
spr "dokumentacja opisuje wybor modelu" "1" "grep -c '10.1. Wybór modelu AI' $DOK"

echo "=== U. L6: klucz API ze stalej + L4b: sygnal o zlym kluczu ==="
AI=$W/includes/ai.php
spr "L6 nazwa stalej"                "SEGM_KLUCZ_API" "grep -oP \"(?<=SEGM_STALA_KLUCZA = ')[^']*\" $AI"
spr "L6 stala ma pierwszenstwo w odczycie" "1" "awk '/function segm_klucz_api/,/^}/' $AI | grep -c 'segm_klucz_ze_stalej()'"
spr "L6 stala blokuje zapis z panelu" "1" "awk '/function segm_zapisz_klucz/,/^}/' $AI | grep -c 'segm_klucz_ze_stalej()'"
spr "L6 panel pokazuje stan ze stalej" "1" "grep -c 'Klucz podany w konfiguracji serwera' $W/includes/admin.php"
spr "L6 pole zablokowane przy stalej" "1" "grep -c 'placeholder=.klucz z wp-config.php.' $W/includes/admin.php"
spr "L4b opcja z bledem AI"          "segm_blad_ai" "grep -oP \"(?<=SEGM_OPCJA_BLAD_AI = ')[^']*\" $AI"
spr "L4b nieudane wywolanie zapisuje blad" "1" "awk '/function segm_log_bledu_ai/,/^}/' $AI | grep -c 'segm_zapisz_blad_ai('"
spr "L4b udane wywolanie gasi pasek" "1" "awk '/function segm_ai_zapytaj/,/^}/' $AI | grep -c 'segm_wyczysc_blad_ai()'"
spr "L4b pasek w panelu"             "1" "grep -c \"add_action( 'admin_notices', 'segm_pasek_bledu_ai' )\" $W/includes/admin.php"
spr "L4b uchwyt testu klucza"        "1" "grep -c \"add_action( 'admin_post_segm_test_klucza'\" $W/includes/admin.php"
PING=$(awk '/function segm_ai_przetestuj_klucz/,/^}/' $AI | grep -c "segm_ai_zapytaj(.*'ping'")
spr "L4b test wysyla TYLKO ping"     "1" "echo $PING"
BEZ_LIMITU=$(awk '/function segm_ai_przetestuj_klucz/,/^}/' $AI | grep -c "'ping', false")
spr "L4b test NIE zabiera jednostki z limitu" "1" "echo $BEZ_LIMITU"
TYLKO_KLAS=$(awk '/function segm_ai_zapytaj/,/^}/' $AI | grep -c 'licz_do_limitu ) {')
spr "licznik stop-loss tylko dla klasyfikacji" "1" "echo $TYLKO_KLAS"
spr "L4b test NIE siega do probki"   "0" "awk '/function segm_ai_przetestuj_klucz/,/^}/' $AI | grep -c 'segm_maile\|segm_klasyfikuj\|probke'"
spr "L4b kody HTTP tlumaczone na polski" "1" "awk '/function segm_opis_bledu_ai/,/^}/' $AI | grep -c 'KLUCZ API ZOSTAŁ ODRZUCONY'"
spr "uninstall kasuje wpis o bledzie AI" "1" "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'segm_blad_ai' )\""
spr "dokumentacja opisuje klucz i sygnal" "1" "grep -c '10.2. Klucz API i sygnał o jego odrzuceniu' $DOK"

echo "=== W. Stronicowanie list ==="
spr "SEGM_NA_STRONE"                 "50" "grep -oP '(?<=SEGM_NA_STRONE = )\d+' $W/includes/admin.php"
spr "wspolny pasek stron"            "1" "grep -c 'function segm_pasek_stron' $W/includes/admin.php"
spr "numer strony przycinany do zakresu" "1" "awk '/function segm_biezaca_strona/,/^}/' $W/includes/admin.php | grep -c 'max( 1, min('"
spr "ZERO sztywnych LIMIT-ow w listach" "0" "grep -c 'ORDER BY id DESC LIMIT 50\|ORDER BY id ASC LIMIT 200' $W/includes/admin.php $W/includes/admin-skrzynka.php | grep -v ':0' | wc -l"
spr "lista przebiegow stronicowana"  "1" "awk '/function segm_strona_przebiegi/,/^}/' $W/includes/admin.php | grep -c 'segm_pasek_stron('"
spr "lista probki stronicowana"      "1" "awk '/function segm_strona_probka/,/^}/' $W/includes/admin.php | grep -c 'segm_pasek_stron('"
spr "historia skrzynki stronicowana" "1" "awk '/function segm_skrzynka_tabela_przebiegow/,/^}/' $W/includes/admin-skrzynka.php | grep -c 'segm_pasek_stron('"
spr "wszystkie trzy listy z OFFSET"  "3" "grep -c 'LIMIT %d OFFSET %d' $W/includes/admin.php $W/includes/admin-skrzynka.php | grep -oP '(?<=:)\d+' | paste -sd+ | bc"
spr "pasek podaje zakres i sume"     "1" "awk '/function segm_pasek_stron/,/^}/' $W/includes/admin.php | grep -c 'displaying-num'"
spr "odnosniki w listach niosa numer strony" "3" "grep -c \"segm_strona=' . (int) \\\$strona_nr\" $W/includes/admin.php $W/includes/admin-skrzynka.php | grep -oP '(?<=:)\\d+' | paste -sd+ | bc"
spr "powrot wraca na te sama strone" "3" "grep -c 'segm_param_strony() )' $W/includes/admin.php $W/includes/admin-skrzynka.php $W/includes/admin-probka.php | grep -oP '(?<=:)\\d+' | paste -sd+ | bc"
spr "dokumentacja opisuje stronicowanie" "1" "grep -c '8.3. Stronicowanie list' $DOK"

echo "=== Y. Licznik trafien: dane pobierane RAZ na cala liste ==="
ADMIN=$W/includes/admin.php
PROBKA=$W/includes/admin-probka.php
Y_KONT=$(awk '/function segm_strona_reguly/,/^}/' $ADMIN | grep -n 'segm_kontekst_trafien( \$reguly )' | head -1 | cut -d: -f1)
Y_PETLA=$(awk '/function segm_strona_reguly/,/^}/' $ADMIN | grep -n 'foreach ( \$reguly as \$r )' | head -1 | cut -d: -f1)
Y_WIERSZ=$(awk '/function segm_strona_reguly/,/^}/' $ADMIN | grep -c 'segm_policz_trafienia(.*\$kontekst )')
Y_ZAPYT=$(awk '/function segm_policz_trafienia/,/^}/' $PROBKA | grep -c 'wpdb->get_')
Y_KLON=$(awk '/function segm_policz_trafienia/,/^}/' $PROBKA | grep -c 'clone \$regula')
spr "jest pomocnik pobierajacy dane raz"  "1" "grep -c 'function segm_kontekst_trafien' $PROBKA"
spr "lista regul bierze kontekst"         "1" "test -n \"$Y_KONT\" && echo 1 || echo 0"
spr "kontekst POZA petla wierszy"         "1" "test -n \"$Y_KONT\" && test -n \"$Y_PETLA\" && test \"$Y_KONT\" -lt \"$Y_PETLA\" && echo 1 || echo 0"
spr "wiersz listy liczy Z kontekstem"     "1" "echo $Y_WIERSZ"
spr "ZERO zapytan w samym liczeniu"       "0" "echo $Y_ZAPYT"
spr "regula probna KLONOWANA (kontekst wspolny)" "1" "echo $Y_KLON"
spr "dokumentacja opisuje koszt licznika" "1" "grep -c 'Licznik liczy z jednego kontekstu' $DOK"

echo "=== N. readme.txt w standardzie WordPressa ==="
spr "readme.txt istnieje"            "1" "test -f $W/readme.txt && echo 1 || echo 0"
spr "naglowek === Nazwa ==="         "1" "head -1 $W/readme.txt | grep -c '^=== Segregator maila ===$'"
spr "pole Contributors"              "1" "grep -c '^Contributors: ' $W/readme.txt"
spr "pole Tags"                      "1" "grep -c '^Tags: ' $W/readme.txt"
spr "Requires at least == naglowek wtyczki" "1" "test \"\$(grep -oP '(?<=^Requires at least: ).*' $W/readme.txt)\" = \"\$(grep -oP '(?<=^ \\* Requires at least: ).*' $W/segregator-maila.php)\" && echo 1 || echo 0"
spr "Requires PHP == naglowek wtyczki"      "1" "test \"\$(grep -oP '(?<=^Requires PHP: ).*' $W/readme.txt)\" = \"\$(grep -oP '(?<=^ \\* Requires PHP: ).*' $W/segregator-maila.php)\" && echo 1 || echo 0"
spr "Stable tag == wersja wtyczki"          "1" "test \"\$(grep -oP '(?<=^Stable tag: ).*' $W/readme.txt)\" = \"\$(grep -oP '(?<=^ \\* Version: ).*' $W/segregator-maila.php)\" && echo 1 || echo 0"
spr "Tested up to = wersja WP ze stacku"    "7.0.4" "grep -oP '(?<=^Tested up to: ).*' $W/readme.txt"
spr "License"                        "GPLv2 or later" "grep -oP '(?<=^License: ).*' $W/readme.txt"
spr "License URI == naglowek wtyczki" "1" "test \"\$(grep -oP '(?<=^License URI: ).*' $W/readme.txt)\" = \"\$(grep -oP '(?<=^ \\* License URI: ).*' $W/segregator-maila.php)\" && echo 1 || echo 0"
for sek in Description Installation "Frequently Asked Questions" Changelog; do
  spr "sekcja == $sek ==" "1" "grep -c '^== $sek ==$' $W/readme.txt"
done
KROTKI=$(sed -n '11p' $W/readme.txt | wc -m)
spr "krotki opis <= 150 znakow"      "1" "test $KROTKI -le 150 && echo 1 || echo 0"

echo "=== O. CHANGELOG.md ==="
spr "CHANGELOG istnieje"             "1" "test -f $W/CHANGELOG.md && echo 1 || echo 0"
spr "format Keep a Changelog"        "1" "grep -c 'keepachangelog.com' $W/CHANGELOG.md"
spr "biezaca wersja ma wpis"         "1" "grep -c '^## \\[1.3.3\\]' $W/CHANGELOG.md"
spr "Niewydane jest PIERWSZA sekcja (Keep a Changelog)" "Niewydane" "grep -oP '^## \\[\\K[^\\]]+' $W/CHANGELOG.md | head -1"
spr "liczba wydan opisanych"         "9" "grep -c '^## \\[' $W/CHANGELOG.md"
for v in 1.0.0 1.1.0 1.2.0 1.2.1; do
  spr "wersja $v z historii gita opisana" "1" "grep -c '^## \\[$v\\]' $W/CHANGELOG.md"
done
spr "readme.txt tez ma changelog wersji" "1" "grep -c '^= 1.3.3 =$' $W/readme.txt"

echo "=== P. Ekran powitalny ==="
# Zapis znacznika idzie przez segm_zapisz_opcje() (wynik MIERZONY, A2-F4) —
# asercja pyta o zapalenie znacznika, nie o konkretną funkcję zapisu.
spr "aktywacja zapala znacznik"      "1" "sed -n '/function segm_aktywacja/,/^}/p' $W/includes/instalator.php | grep -c \"segm_zapisz_opcje( 'segm_powitanie_gotowe', 1 )\""
spr "znacznik powitania: wynik zapisu mierzony" "1" "sed -n '/function segm_aktywacja/,/^}/p' $W/includes/instalator.php | grep -c 'znacznik ekranu powitalnego'"
spr "przekierowanie na admin_init"   "1" "grep -c \"add_action( 'admin_init', 'segm_powitanie_przekieruj' )\" $W/includes/admin-powitanie.php"
spr "pamietane PER UZYTKOWNIKA"      "1" "sed -n '/function segm_powitanie_przekieruj/,/^}/p' $W/includes/admin-powitanie.php | grep -c 'update_user_meta( get_current_user_id(), SEGM_POWITANIE_META'"
spr "nie wraca: sprawdzenie znacznika" "1" "sed -n '/function segm_powitanie_przekieruj/,/^}/p' $W/includes/admin-powitanie.php | grep -c 'get_user_meta( get_current_user_id(), SEGM_POWITANIE_META'"
spr "pomija wlaczanie hurtem"        "1" "grep -c \"activate-multi\" $W/includes/admin-powitanie.php"
spr "strona zdjeta z menu"           "1" "grep -c 'remove_submenu_page' $W/includes/admin-powitanie.php"
spr "sprawdza uprawnienia"           "2" "grep -c \"current_user_can( 'manage_options' )\" $W/includes/admin-powitanie.php"
spr "baner ustepuje ekranowi powitalnemu" "1" "sed -n '/function segm_baner_powitalny/,/^}/p' $W/includes/admin.php | grep -c 'SEGM_POWITANIE_META'"
spr "dokumentacja tlumaczy dwa powitania" "1" "grep -c 'Dwa powitania i dlaczego oba zostają' $DOK"
spr "uninstall kasuje znacznik globalny" "1" "grep -c \"delete_option( 'segm_powitanie_gotowe' )\" $W/uninstall.php"
spr "uninstall kasuje metadane wszystkich" "1" "grep -c \"delete_metadata( 'user', 0, 'segm_powitanie_widziane'\" $W/uninstall.php"
spr "kasowane ZAWSZE (przed warunkiem usun_dane)" "1" "sed -n '1,/usun_dane/p' $W/uninstall.php | grep -c \"delete_option( 'segm_powitanie_gotowe' )\""


echo
if [ "$BLEDY" -eq 0 ]; then
  echo "DOKUMENTACJA ZGODNA Z KODEM — 0 rozjazdow"
else
  echo "ROZJAZDOW DOKUMENTACJA/KOD: $BLEDY"
fi
exit $((BLEDY > 0))
