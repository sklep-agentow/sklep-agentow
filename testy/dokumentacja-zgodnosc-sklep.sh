#!/bin/bash
# dokumentacja-zgodnosc-sklep.sh — czy dokumenty SKLEPU (readme.txt, CHANGELOG.md, DOKUMENTACJA-TECHNICZNA.md)
# mówią to samo, co kod. Wzór: testy/dokumentacja-zgodnosc-segregator.sh (recenzja #182, WAŻNA 3:
# 350 linii dokumentacji sklepu weszło bez żadnej bramki dok=kod, którą mają oba boty).
# Każda LICZBA z readme jest porównywana ze STAŁĄ w kodzie — nie z drugą liczbą w innym dokumencie.
# Użycie: bash testy/dokumentacja-zgodnosc-sklep.sh      EXIT 0 = zgodne · 1 = rozjazd · 2 = brak plików
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$KORZEN" || exit 2
W=wtyczki/sklep-agentow
for f in $W/readme.txt $W/CHANGELOG.md $W/DOKUMENTACJA-TECHNICZNA.md $W/sklep-agentow.php $W/includes/admin.php $W/includes/zakup.php; do
	[ -f "$f" ] || { echo "BRAK $f" >&2; exit 2; }
done
ZLE=0
spr() { # spr <opis> <oczekiwane> <komenda-dajaca-wartosc>
	local opis="$1" ocz="$2" got; got="$(eval "$3" 2>/dev/null | head -1)"
	if [ "$got" = "$ocz" ]; then printf '  OK   %-46s = %s\n' "$opis" "$got"; else printf '  ROZJAZD %-43s oczekiwano [%s], jest [%s]\n' "$opis" "$ocz" "$got"; ZLE=$((ZLE+1)); fi
}
stala() { sed -n "s/^define( '$1', *'\{0,1\}\([0-9.]*\)'\{0,1\} *);.*/\1/p" $W/sklep-agentow.php | head -1; }
WER=$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' $W/sklep-agentow.php | head -1)

echo "=== A. Nagłówek readme.txt == nagłówek wtyczki ==="
spr "Stable tag == Version"          "$WER" "grep -oP '(?<=^Stable tag: ).*' $W/readme.txt"
spr "Requires at least == nagłówek"  "$(grep -oP '(?<=^ \* Requires at least: ).*' $W/sklep-agentow.php)" "grep -oP '(?<=^Requires at least: ).*' $W/readme.txt"
spr "Requires PHP == nagłówek"       "$(grep -oP '(?<=^ \* Requires PHP: ).*' $W/sklep-agentow.php)" "grep -oP '(?<=^Requires PHP: ).*' $W/readme.txt"
spr "License (forma readme WP)"      "GPLv2 or later" "grep -oP '(?<=^License: ).*' $W/readme.txt"
spr "License URI == nagłówek"        "$(grep -oP '(?<=^ \* License URI: ).*' $W/sklep-agentow.php)" "grep -oP '(?<=^License URI: ).*' $W/readme.txt"
WP_STACK=$(docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html core version 2>/dev/null | grep -oE '^[0-9]+\.[0-9]+')
if [ -n "$WP_STACK" ]; then spr "Tested up to == WP stosu ($WP_STACK)" "$WP_STACK" "grep -oP '(?<=^Tested up to: ).*' $W/readme.txt | grep -oE '^[0-9]+\.[0-9]+'"; else echo "  POMINIETE Tested up to (stos nie odpowiada)"; fi
for sek in Description Installation "Frequently Asked Questions" Changelog; do spr "sekcja == $sek ==" "1" "grep -c '^== $sek ==$' $W/readme.txt"; done
spr "krótki opis <= 150 znaków"      "1" "test \$(sed -n '11p' $W/readme.txt | wc -m) -le 150 && echo 1 || echo 0"
spr "changelog readme: sekcja = $WER =" "1" "grep -c '^= $WER =$' $W/readme.txt"


echo "=== A1. DOK=KOD: tabela stalych w dokumentacji vs kod ==="
# TRZECI egzemplarz tej samej klasy (recenzja r3). ChatBot mial ta kontrole, Segregator dostal
# ja w rundzie 2, a Sklep — najwiekszy z trzech — nie mial jej wcale. Tabela stalych Sklepu ma
# JESZCZE INNY uklad niz oba boty: wartosc stoi bez apostrofow wstecznych, wiec zaden z dwoch
# wczesniejszych wzorcow tu nie pasowal i dlatego brak nie rzucal sie w oczy.
BT=$(printf '\140')   # wsteczny apostrof skladany z kodu — nigdy wprost, bo eval by go wykonal

wartosc_stalej_sklepu() { # NAZWA -> wartosc define z dowolnego pliku PHP wtyczki
	# Stale liczbowe stoja bez apostrofow — `define( 'X', 7 )` — wiec surowe trafienie
	# konczy sie na " )". Ucinamy nawias zamykajacy i biale znaki z obu stron.
	grep -rhoP "define\(\s*'$1',\s*'?\K[^';]+" "$W" --include=*.php 2>/dev/null \
		| head -1 | sed "s/[[:space:]]*)[[:space:]]*$//; s/^[[:space:]]*//; s/[[:space:]]*$//"
}

STALYCH_DOK=0
OPISOWYCH=0
while IFS='|' read -r _ NAZWA WART _; do
	NAZWA=$(echo "$NAZWA" | tr -d " $BT")
	WART=$(echo "$WART" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//;s/\*\*//g')
	[ -n "$NAZWA" ] || continue
	STALYCH_DOK=$((STALYCH_DOK+1))
	# Wiersz, w ktorym dokumentacja podaje OPIS w nawiasie zamiast wartosci (np. "(zdanie zgody)"),
	# nie jest porownywalny wprost — liczymy go jawnie, zeby pomijanie nie roslo po cichu.
	case "$WART" in
		"("*")") OPISOWYCH=$((OPISOWYCH+1))
			printf '  ---  %-58s opis zamiast wartosci: %s\n' "DOK=KOD: $NAZWA" "$WART"
			continue ;;
	esac
	spr "DOK=KOD: $NAZWA" "$WART" "wartosc_stalej_sklepu $NAZWA"
done < <(grep -P "^\| ${BT}SKLEPAG_[A-Z_]+${BT} \| [^|]+ \|" $W/DOKUMENTACJA-TECHNICZNA.md)

# Petla, ktora przestala cokolwiek dopasowywac, bylaby cicho pusta i swiecila na zielono.
spr "wierszy tabeli stalych przeczytanych" "6" "echo $STALYCH_DOK"
spr "wierszy opisowych (pominiete w porownaniu)" "1" "echo $OPISOWYCH"

echo "=== B. Liczby z readme == stałe w kodzie ==="
spr "link ważny N godzin (SKLEPAG_LINK_GODZIN)" "$(stala SKLEPAG_LINK_GODZIN)" "grep -oE 'ważny [0-9]+ godzin' $W/readme.txt | grep -oE '[0-9]+'"
spr "do N pobrań (SKLEPAG_LIMIT_POBRAN)"       "$(stala SKLEPAG_LIMIT_POBRAN)" "grep -oE 'do [0-9]+ pobrań' $W/readme.txt | grep -oE '[0-9]+'"
spr "link opinii N dni (SKLEPAG_OPINIA_DNI)"    "$(stala SKLEPAG_OPINIA_DNI)"  "grep -oE 'ważnego [0-9]+ dni' $W/readme.txt | grep -oE '[0-9]+'"
spr "cron co N minut == interval/60"            "$(( $(grep -oE "'interval' *=> *[0-9]+" $W/sklep-agentow.php | grep -oE '[0-9]+$') / 60 ))" "grep -oE 'co [0-9]+ minut' $W/readme.txt | head -1 | grep -oE '[0-9]+'"
spr "retencja dziennika N dni == kod"           "$(grep -oE '[0-9]+ \* DAY_IN_SECONDS' $W/includes/zakup.php | grep -oE '^[0-9]+' | head -1)" "grep -oE 'starsze niż [0-9]+ dni' $W/readme.txt | grep -oE '[0-9]+'"

echo "=== C. Etykiety menu: admin.php ⇄ readme, W OBIE STRONY i z licznikiem (recenzja #182 r2 W3) ==="
# Etykiety bierzemy z KODU (4. argument add_submenu_page), nie z zaszytej listy — nowa pozycja menu bez opisu
# w readme ma tu paść. Ukryta podstrona zamówienia (sklepag-zamowienie, widok z pulpitu) nie jest pozycją menu;
# readme opisuje ją jako „widok **Zamówienia**", a nazwa menu **Sklep Agentów** to nie podstrona — oba wyjątki jawne.
ETYKIETY_KOD=$(sed -n "s/^[[:space:]]*add_submenu_page( 'sklepag', '[^']*', '\([^']*\)', 'manage_options', '\([^']*\)'.*/\2\t\1/p" $W/includes/admin.php | grep -v "^sklepag-zamowienie	" | cut -f2)
N_KOD=$(printf '%s\n' "$ETYKIETY_KOD" | grep -c .)
AKAPIT=$(awk '/^\* Panel w kokpicie/{f=1; print; next} f && (/^\* / || /^$/){exit} f{print}' $W/readme.txt)
POGRUBIONE=$(printf '%s\n' "$AKAPIT" | grep -oE '\*\*[^*]+\*\*' | sed 's/\*\*//g' | grep -vxE 'Sklep Agentów|Zamówienia')
N_README=$(printf '%s\n' "$POGRUBIONE" | grep -c .)
while IFS= read -r e; do [ -n "$e" ] && spr "menu '$e' (z kodu) pogrubione w readme" "1" "printf '%s\n' \"\$POGRUBIONE\" | grep -cx \"$e\""; done <<< "$ETYKIETY_KOD"
while IFS= read -r e; do [ -n "$e" ] && spr "readme '$e' (pogrubione) jest w admin.php" "1" "printf '%s\n' \"\$ETYKIETY_KOD\" | grep -cx \"$e\""; done <<< "$POGRUBIONE"
spr "liczba pozycji menu: admin.php == readme" "$N_KOD" "echo $N_README"
spr "pozycji menu w kodzie ≥ 5"               "1" "test $N_KOD -ge 5 && echo 1 || echo 0"

echo "=== D. CHANGELOG.md i DOKUMENTACJA-TECHNICZNA.md ==="
spr "CHANGELOG: format Keep a Changelog"  "1" "grep -c 'keepachangelog.com' $W/CHANGELOG.md"
spr "CHANGELOG: wpis [$WER]"              "1" "grep -c '^## \[$WER\]' $W/CHANGELOG.md"
spr "CHANGELOG: [Niewydane] puste"        "0" "awk '/^## \[Niewydane\]/{f=1;next} /^## \[/{f=0} f' $W/CHANGELOG.md | grep -c '^- '"
spr "DOK-TECH: Wersja wtyczki == $WER"    "$WER" "grep -oE 'Wersja wtyczki:[* ]*[0-9.]+' $W/DOKUMENTACJA-TECHNICZNA.md | grep -oE '[0-9.]+$'"
spr "DOK-TECH: tabela stalych — SKLEPAG_WERSJA == $WER" "$WER" "grep -oE '^\\| .SKLEPAG_WERSJA. \\| [0-9.]+' $W/DOKUMENTACJA-TECHNICZNA.md | grep -oE '[0-9.]+$'"
spr "DOK-TECH: schemat == SKLEPAG_WERSJA_SCHEMATU" "$(stala SKLEPAG_WERSJA_SCHEMATU)" "grep -oE 'wersja schematu bazy: [0-9]+' $W/DOKUMENTACJA-TECHNICZNA.md | grep -oE '[0-9]+$'"
spr "DOK-TECH: liczba tabel == CREATE TABLE w aktywacji" "$(grep -c 'CREATE TABLE' $W/includes/aktywacja.php)" "grep -oE 'Schemat bazy — [0-9]+ tabel' $W/DOKUMENTACJA-TECHNICZNA.md | grep -oE '[0-9]+'"

echo "=== E. readme odsyła do plików, które istnieją (w paczce albo obok niej) ==="
# readme mówi wprost: CHANGELOG.md i DOKUMENTACJA-TECHNICZNA.md „w paczce", INSTRUKCJA-INSTALACJI.md „obok paczek"
# (dołączana do wydania z dokumentacja/). Sprawdzamy obietnicę TAK, jak jest sformułowana (recenzja #182, DROBNA 5).
for p in CHANGELOG.md DOKUMENTACJA-TECHNICZNA.md; do spr "readme: '$p' w paczce"  "1" "grep -c '\`$p\`' $W/readme.txt | sed 's/^[1-9][0-9]*$/1/'"; spr "plik w katalogu wtyczki: $p" "1" "test -f $W/$p && echo 1 || echo 0"; done
spr "readme: instrukcja 'obok paczek'"      "1" "grep -c 'obok paczek (plik \`INSTRUKCJA-INSTALACJI.md\`)' $W/readme.txt"
spr "plik dokumentacja/INSTRUKCJA-INSTALACJI.md" "1" "test -f dokumentacja/INSTRUKCJA-INSTALACJI.md && echo 1 || echo 0"

echo "=== F. INSTRUKCJA.md (instrukcja właściciela, w paczce) == kod ==="
# Instrukcja właściciela weszła do paczki w 0.9.23. Każda liczba, którą podaje, ma stałą w kodzie —
# porównujemy z NIĄ, nie z readme (te same zasady co sekcja B). Zrzuty, do których odsyła, muszą
# istnieć w katalogu wtyczki, bo inaczej paczka niesie pusty odsyłacz.
spr "plik $W/INSTRUKCJA.md"                 "1" "test -f $W/INSTRUKCJA.md && echo 1 || echo 0"
spr "INSTRUKCJA: link ważny N godzin == SKLEPAG_LINK_GODZIN" "$(stala SKLEPAG_LINK_GODZIN)" "grep -oE 'ważny \*\*[0-9]+ godzin' $W/INSTRUKCJA.md | head -1 | grep -oE '[0-9]+'"
spr "INSTRUKCJA: do N pobrań == SKLEPAG_LIMIT_POBRAN"       "$(stala SKLEPAG_LIMIT_POBRAN)" "grep -oE 'do \*\*[0-9]+ pobrań' $W/INSTRUKCJA.md | head -1 | grep -oE '[0-9]+'"
spr "INSTRUKCJA: opinia ważna N dni == SKLEPAG_OPINIA_DNI"  "$(stala SKLEPAG_OPINIA_DNI)" "grep -oE 'opinii \(ważny [0-9]+ dni' $W/INSTRUKCJA.md | head -1 | grep -oE '[0-9]+'"
spr "INSTRUKCJA: ZIP do N MB == SKLEPAG_UPLOAD_LIMIT_B"     "$(grep -oP 'const SKLEPAG_UPLOAD_LIMIT_B = \K[0-9]+' $W/includes/admin-produkty.php)" "grep -oE 'ZIP, do [0-9]+ MB' $W/INSTRUKCJA.md | head -1 | grep -oE '[0-9]+'"
spr "INSTRUKCJA: dopytanie co N minut == interwał cron"     "$(( $(grep -oP "'interval' => \K[0-9]+" $W/sklep-agentow.php | head -1) / 60 ))" "grep -oE 'co [0-9]+ minut' $W/INSTRUKCJA.md | head -1 | grep -oE '[0-9]+'"
spr "INSTRUKCJA: odsyłacze do zrzutów"                      "$(grep -oE '\(zrzuty/[a-z0-9-]+\.png\)' $W/INSTRUKCJA.md | sort -u | wc -l)" "n=0; for z in \$(grep -oE 'zrzuty/[a-z0-9-]+\.png' $W/INSTRUKCJA.md | sort -u); do [ -f $W/\$z ] && n=\$((n+1)); done; echo \$n"
spr "INSTRUKCJA: zrzuty wpisane do allowlisty paczki"       "$(grep -oE 'zrzuty/[a-z0-9-]+\.png' $W/INSTRUKCJA.md | sort -u | wc -l)" "n=0; for z in \$(grep -oE 'zrzuty/[a-z0-9-]+\.png' $W/INSTRUKCJA.md | sort -u); do grep -q \"sklep-agentow/\$z\" boty/buduj-paczki.sh && n=\$((n+1)); done; echo \$n"
spr "readme: 'INSTRUKCJA.md' w paczce"                      "1" "grep -c 'w \`INSTRUKCJA.md\`' $W/readme.txt"
spr "readme: 'PONAD-SPECYFIKACJE.md' w paczce"              "1" "grep -c '\`PONAD-SPECYFIKACJE.md\` w paczce' $W/readme.txt"
spr "plik $W/PONAD-SPECYFIKACJE.md"                         "1" "test -f $W/PONAD-SPECYFIKACJE.md && echo 1 || echo 0"

echo
if [ "$ZLE" -eq 0 ]; then echo "DOKUMENTACJA SKLEPU ZGODNA Z KODEM — 0 rozjazdów"; exit 0; fi
echo "NIEZGODNE: $ZLE pozycji"; exit 1
