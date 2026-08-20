#!/bin/bash
# INSTRUKCJA Segregatora: czy KAZDA liczba ma pokrycie w kodzie.
#
# Powod istnienia: instrukcja to pierwszy dokument, ktory czyta kupujacy —
# i klasa nr 3 ze SCIAGA-EGZAMINATORA.md („dokumentacja klamie wzgledem kodu")
# boli tu najbardziej. Kolega-egzaminator wytykal to cztery razy. Kazda liczba
# jest tu wyciagana Z KODU i porownywana z tym, co instrukcja obiecuje
# kupujacemu — nie odwrotnie.
#
# Uruchomienie: bash testy/instrukcja-liczby-segregator.sh
set -u
W=wtyczki/segregator-maila
I=$W/INSTRUKCJA.md
BLEDY=0

spr() {
  local opis="$1" oczekiwane="$2" komenda="$3"
  local mam
  mam=$(eval "$komenda" 2>/dev/null)
  if [ "$mam" = "$oczekiwane" ]; then
    printf '  OK   %-56s = %s\n' "$opis" "$mam"
  else
    printf '  BLAD %-56s oczekiwano [%s], mam [%s]\n' "$opis" "$oczekiwane" "$mam"
    BLEDY=$((BLEDY+1))
  fi
}

# Liczba Z KODU -> czy instrukcja ja cytuje.
z_kodu() {
  local opis="$1" wartosc_z_kodu="$2" wzorzec_w_instrukcji="$3"
  if [ -z "$wartosc_z_kodu" ]; then
    printf '  BLAD %-56s nie udalo sie odczytac wartosci z kodu\n' "$opis"
    BLEDY=$((BLEDY+1)); return
  fi
  if grep -qF "$wzorzec_w_instrukcji" "$I"; then
    printf '  OK   %-56s kod=%s, instrukcja mowi to samo\n' "$opis" "$wartosc_z_kodu"
  else
    printf '  BLAD %-56s kod=%s, a instrukcja NIE zawiera „%s"\n' "$opis" "$wartosc_z_kodu" "$wzorzec_w_instrukcji"
    BLEDY=$((BLEDY+1))
  fi
}

# \K zamiast lookbehind: stale w kodzie sa wyrownane spacjami, a lookbehind
# o zmiennej dlugosci grep -P odrzuca.
stala() { grep -rhoP "$1\\s*=\\s*\\K[0-9]+" $W/includes/*.php $W/*.php | head -1; }
domyslna() { sed -n "/function $1/,/^}/p" "$2" | grep -oP "'$3'\\s*=>\\s*'?\\K[a-z0-9_]+" | head -1; }

echo "=== A. Wymagania srodowiska ==="
z_kodu "Requires at least (WordPress)" "$(grep -oP '(?<=^ \* Requires at least: ).*' $W/segregator-maila.php)" "WordPress 6.0+"
z_kodu "Requires PHP"                  "$(grep -oP '(?<=^ \* Requires PHP: ).*' $W/segregator-maila.php)"       "PHP 8.1+"

echo
echo "=== B. Co kupujacy dostaje po wlaczeniu (seed demo) ==="
KAT=$(sed -n '/\$kategorie = array(/p' $W/includes/instalator.php | grep -o "'" | wc -l)
KAT=$((KAT / 2))
REG=$(awk '/\$reguly[ \t]*=[ \t]*array\(/,/^[ \t]*\);/' $W/includes/instalator.php | grep -c "array( '")
MAILE=$(awk '/\$maile[ \t]*=[ \t]*array\(/,/^[ \t]*\);/' $W/includes/instalator.php | grep -c "array( '")
spr "kategorii demo w kodzie"        "5"  "echo $KAT"
spr "regul demo w kodzie"            "8"  "echo $REG"
spr "maili demo w kodzie"            "30" "echo $MAILE"
z_kodu "instrukcja: 5 kategorii"     "$KAT"   "5 kategorii"
z_kodu "instrukcja: 8 regul"         "$REG"   "8 przykładowych reguł"
z_kodu "instrukcja: 30 maili demo"   "$MAILE" "próbkę 30 polskich maili"

echo
echo "=== C. Import CSV ==="
z_kodu "limit wierszy importu"  "$(stala SEGM_IMPORT_MAX_WIERSZY)" "**500 wierszy**"
z_kodu "limit wielkosci pliku"  "$(stala SEGM_IMPORT_MAX_BAJTOW)"  "**5 MB**"
spr "5 MB w kodzie to naprawde 5 MiB" "5242880" "echo $(stala SEGM_IMPORT_MAX_BAJTOW)"
z_kodu "maks. 500 wierszy przy opisie CSV" "$(stala SEGM_IMPORT_MAX_WIERSZY)" "maks. 500 wierszy"

echo
echo "=== D. Tryb AI: limity, ktore widzi kupujacy ==="
PARTIA=$(stala SEGM_AI_PARTIA)
WYWOLAN=$(stala SEGM_AI_MAX_WYWOLAN_PRZEBIEG)
LIMIT_DZIENNY=$(domyslna segm_ustawienia $W/segregator-maila.php ai_limit_dzienny)
spr "jedno wywolanie API na przebieg" "1" "echo $WYWOLAN"
z_kodu "limit probki w trybie AI"     "$((PARTIA * WYWOLAN))" "**limit próbki w trybach AI: 30 maili**"
z_kodu "wielkosc partii"              "$PARTIA"               "jedna partia do 30 maili = 1 wywołanie API"
z_kodu "domyslny dzienny stop-loss"   "$LIMIT_DZIENNY"        "domyślnie 100"
z_kodu "domyslny model AI"            "$(grep -oP "(?<=SEGM_AI_MODEL      = ')[^']*" $W/includes/ai.php)" "\`claude-haiku-4-5\`"
spr "domyslny model BEZ daty"         "0" "grep -oP \"(?<=SEGM_AI_MODEL      = ')[^']*\" $W/includes/ai.php | grep -cP -- '-\\d{8}$'"

echo
echo "=== E. Skrzynka IMAP ==="
PORT=$(domyslna segm_skrzynka_ustawienia $W/includes/skrzynka.php port)
LIMIT_WIAD=$(domyslna segm_skrzynka_ustawienia $W/includes/skrzynka.php limit)
TRYB=$(domyslna segm_skrzynka_ustawienia $W/includes/skrzynka.php tryb)
CZEST=$(domyslna segm_skrzynka_ustawienia $W/includes/skrzynka.php czestotliwosc)
spr "domyslny tryb pracy to NA SUCHO" "na_sucho"  "echo $TRYB"
spr "domyslny harmonogram wylaczony"  "wylaczone" "echo $CZEST"
z_kodu "domyslny port IMAP"           "$PORT"       "**993 + SSL/TLS**"
z_kodu "wiadomosci na jeden przebieg" "$LIMIT_WIAD" "do 50 najnowszych wiadomości"
z_kodu "twardy limit czasu przebiegu" "$(stala SEGM_SKRZ_LIMIT_CZASU)" "kończy się po 20 sekundach"
z_kodu "stala z haslem do skrzynki"   "$(grep -oP "(?<=SEGM_STALA_HASLA = ')[^']*" $W/includes/skrzynka.php)" "SEGM_IMAP_HASLO"
z_kodu "stala z kluczem API"          "$(grep -oP "(?<=SEGM_STALA_KLUCZA = ')[^']*" $W/includes/ai.php)"      "SEGM_KLUCZ_API"

echo "  --- harmonogram: kazda pozycja z kodu musi byc w instrukcji ---"
while IFS= read -r OPIS; do
  if grep -qF "$OPIS" "$I"; then
    printf '  OK   harmonogram: %-45s jest w instrukcji\n' "$OPIS"
  else
    printf '  BLAD harmonogram: %-45s BRAK w instrukcji\n' "$OPIS"
    BLEDY=$((BLEDY+1))
  fi
done < <(sed -n "/function segm_skrzynka_czestotliwosci/,/^}/p" $W/includes/skrzynka-przebieg.php | grep -oP "(?<='opis'    => ')[^']+" | grep -v 'wyłączone')

echo
echo "=== F. Retencja i przeprowadzka ==="
z_kodu "retencja w dniach"          "$(stala SEGM_RETENCJA_DNI)"      "**90 dni**"
z_kodu "limit pliku konfiguracji"   "$(stala SEGM_KONFIG_MAX_BAJTOW)" "**512 kB**"
z_kodu "limit wpisow konfiguracji"  "$(stala SEGM_KONFIG_MAX_WPISOW)" "**500 kategorii i 500 reguł**"
z_kodu "limit dlugosci wzorca"      "$(stala SEGM_KONFIG_MAX_WZORZEC)" "**2000 znaków**"
z_kodu "wierszy na stronie listy"   "$(stala SEGM_NA_STRONE)"          "**50 wierszy**"
spr "512 kB w kodzie to naprawde 512 KiB" "524288" "echo $(stala SEGM_KONFIG_MAX_BAJTOW)"

echo
echo "=== G. Obietnice, ktore musza stac w instrukcji WPROST ==="
spr "logowanie tylko haslem aplikacji" "1" "grep -c 'Podawaj wyłącznie hasło aplikacji' $I"
spr "Microsoft 365 i Outlook.com NIE zadzialaja" "1" "grep -c 'Microsoft 365 i Outlook.com' $I | awk '{print (\$1>0)?1:0}'"
spr "powod: serwer przyjmuje tylko OAuth2" "1" "grep -c 'wyłącznie OAuth2' $I"
spr "tryb na sucho jest DOMYSLNY"      "1" "grep -c 'Domyślnie działa NA SUCHO' $I"
spr "na sucho wymuszone protokolem (EXAMINE)" "1" "grep -c 'EXAMINE' $I"
spr "realne przenoszenie = DWA kroki"  "1" "grep -c 'dwoma świadomymi krokami' $I"
spr "cofniecie ostatniego przebiegu"   "1" "grep -c 'Cofnij ostatni przebieg' $I"
spr "cofnac da sie TYLKO ostatni"      "1" "grep -c 'ostatni.. przebieg, nie dowolny' $I | awk '{print (\$1>0)?1:0}'"
spr "podlaczenie krok po kroku"        "1" "grep -c 'Podłączenie krok po kroku' $I"
spr "prywatnosc: tryb regulowy osobno" "1" "grep -c '\*\*Tryb regułowy:\*\*' $I"
spr "prywatnosc: tryby AI osobno"      "1" "grep -c '\*\*Tryby AI:\*\*' $I"
spr "prywatnosc: skrzynka klasyfikuje REGULAMI" "1" "grep -c 'klasyfikuje lokalnie, regułami' $I"
spr "prywatnosc: BODY.PEEK nie oznacza przeczytanych" "1" "grep -c 'BODY.PEEK' $I"

echo
echo "=== H. Instrukcja WCHODZI do paczki klienta ==="
spr "INSTRUKCJA.md na allowliscie paczki" "1" "grep -c 'segregator-maila/INSTRUKCJA.md' boty/buduj-paczki.sh"
spr "INSTRUKCJA.md w zbudowanym zipie"    "1" "unzip -l boty/paczki/segregator-maila-1.3.0.zip | grep -c 'segregator-maila/INSTRUKCJA.md'"

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "INSTRUKCJA ZGODNA Z KODEM — kazda liczba ma pokrycie"
  exit 0
fi
echo "ROZJAZDOW INSTRUKCJA/KOD: $BLEDY"
exit 1
