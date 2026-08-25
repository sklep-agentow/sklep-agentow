#!/bin/bash
# dokumentacja-zgodnosc-sklep-proba-kontrolna.sh — dowód, że bramka dok=kod SKLEPU potrafi PAŚĆ.   WERSJA 1.0.0
#
# Recenzja #182 r2 W4: nowa bramka weszła bez dowodu, że rozpoznaje rozjazd (zasada dowodu CLAUDE.md / D14).
# Kopiuje do /tmp TYLKO to, co test czyta, i w każdym przypadku psuje DOKŁADNIE JEDNĄ rzecz — oczekuje EXIT 1,
# nazwy KAŻDEJ trafionej pozycji i JAWNIE podanej liczby rozjazdów (domyślnie 1; przypadek psujący dwie
# rzeczy naraz maskuje martwy warunek — lekcja W2, a nienazwana trafiona pozycja to niesprawdzona asercja). Czysta kopia → EXIT 0 (kontrola odwrotna pierwsza, inaczej „bramka, która
# wszystko odrzuca" też by zdała).
# Użycie: bash testy/dokumentacja-zgodnosc-sklep-proba-kontrolna.sh    EXIT 0 = bramka rozpoznaje każdy rozjazd · 1 = nie
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
W=wtyczki/sklep-agentow
BAZA="$(mktemp -d)"; trap 'rm -rf "$BAZA"' EXIT
BLEDY=0
WER=$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$KORZEN/$W/sklep-agentow.php" | head -1)

zbuduj_czysty() {
	local r="$1"; rm -rf "$r"; mkdir -p "$r/testy" "$r/$W/includes" "$r/dokumentacja"
	cp "$KORZEN/testy/dokumentacja-zgodnosc-sklep.sh" "$r/testy/"
	cp "$KORZEN/$W/readme.txt" "$KORZEN/$W/CHANGELOG.md" "$KORZEN/$W/DOKUMENTACJA-TECHNICZNA.md" "$KORZEN/$W/sklep-agentow.php" "$r/$W/"
	cp "$KORZEN/$W/includes/admin.php" "$KORZEN/$W/includes/zakup.php" "$KORZEN/$W/includes/aktywacja.php" "$r/$W/includes/"
	cp "$KORZEN/dokumentacja/INSTRUKCJA-INSTALACJI.md" "$r/dokumentacja/"
}

sprawdz() { # sprawdz <opis> <oczekiwany-exit> <funkcja-psujaca> <fragmenty-wyjscia rozdzielone |> [oczekiwana-liczba-rozjazdow=1]
	# Jedno psucie może trafić w więcej niż jedną asercję (np. etykieta menu: kierunek + licznik) — wtedy liczba
	# jest podana JAWNIE i KAŻDA trafiona pozycja musi być nazwana; domyślnie oczekujemy dokładnie jednej.
	local opis="$1" ocz="$2" psuj="$3" frag="$4" ile="${5:-1}" r="$BAZA/proba" wy kod=0 n f
	zbuduj_czysty "$r"; "$psuj" "$r"
	wy="$(bash "$r/testy/dokumentacja-zgodnosc-sklep.sh" 2>&1)" || kod=$?
	if [ "$kod" -ne "$ocz" ]; then printf '  BLAD %-56s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$ocz"; printf '%s\n' "$wy" | grep -E 'ROZJAZD|BRAK|NIEZGODNE' | head -8 | sed 's/^/         /'; BLEDY=$((BLEDY+1)); return; fi
	while IFS= read -r f; do
		[ -n "$f" ] && ! printf '%s' "$wy" | grep -qF "$f" && { printf '  BLAD %-56s (kod OK, nie nazwala pozycji: %s)\n' "$opis" "$f"; BLEDY=$((BLEDY+1)); return; }
	done <<< "$(printf '%s' "$frag" | tr '|' '\n')"
	n=$(printf '%s\n' "$wy" | grep -c 'ROZJAZD')
	if [ "$ocz" -eq 1 ] && [ "$n" -ne "$ile" ]; then printf '  BLAD %-56s (psuje 1 rzecz, oczekiwano ROZJAZDOW: %s, jest %s)\n' "$opis" "$ile" "$n"; printf '%s\n' "$wy" | grep ROZJAZD | sed 's/^/         /'; BLEDY=$((BLEDY+1)); return; fi
	printf '  OK   %-56s (EXIT=%s, rozjazdow: %s)\n' "$opis" "$kod" "$n"
}

nic()               { :; }
psuj_stable_tag()   { sed -i 's/^Stable tag: .*/Stable tag: 0.0.1/' "$1/$W/readme.txt"; }
psuj_licencja()     { sed -i 's/^License: .*/License: MIT/' "$1/$W/readme.txt"; }
psuj_godziny()      { sed -i 's/ważny 72 godzin/ważny 48 godzin/' "$1/$W/readme.txt"; }
psuj_etykieta_rdm() { sed -i 's/\*\*Opinie\*\*/**Recenzje**/' "$1/$W/readme.txt"; }
psuj_menu_kod()     { sed -i "s|^\(\s*\)add_submenu_page( 'sklepag', 'Ustawienia sklepu'|\1add_submenu_page( 'sklepag', 'Raporty', 'Raporty', 'manage_options', 'sklepag-raporty', 'sklepag_admin_raporty' );\n&|" "$1/$W/includes/admin.php"; }
psuj_menu_readme()  { sed -i 's/· \*\*Opinie\*\* —/· **Kupony** — rabaty · **Opinie** —/' "$1/$W/readme.txt"; }
psuj_changelog()    { sed -i "s/^## \[$WER\]/## [0.0.0]/" "$1/$W/CHANGELOG.md"; }
psuj_dok_tech()     { sed -i 's/\(Wersja wtyczki:[* ]*\)[0-9.]*/\10.0.1/' "$1/$W/DOKUMENTACJA-TECHNICZNA.md"; }
psuj_brak_pliku()   { rm -f "$1/dokumentacja/INSTRUKCJA-INSTALACJI.md"; }

echo "=== KONTROLA ODWROTNA: czysta kopia = 0 rozjazdow ==="
sprawdz "czysta kopia repo → EXIT 0"                         0 nic               "0 rozjazdów"
echo; echo "=== KAZDY ROZJAZD PODLOZONY OSOBNO → EXIT 1, nazwa pozycji, DOKLADNIE 1 rozjazd ==="
sprawdz "A: Stable tag ≠ Version"                            1 psuj_stable_tag   "Stable tag == Version"
sprawdz "A: licencja w readme nie GPLv2"                     1 psuj_licencja     "License (forma readme WP)"
sprawdz "B: readme mówi 48 godzin, stała mówi 72"            1 psuj_godziny      "link ważny N godzin"
sprawdz "C: etykieta menu w readme przemianowana"            1 psuj_etykieta_rdm "menu 'Opinie'|readme 'Recenzje'" 2
sprawdz "C: NOWA pozycja menu w admin.php bez readme (W3)"   1 psuj_menu_kod     "menu 'Raporty'|liczba pozycji menu" 2
sprawdz "C: pozycja w readme, której nie ma w kodzie"        1 psuj_menu_readme  "readme 'Kupony'|liczba pozycji menu" 2
sprawdz "D: CHANGELOG bez wpisu bieżącej wersji"             1 psuj_changelog    "CHANGELOG: wpis"
sprawdz "D: DOK-TECH z inną wersją wtyczki"                  1 psuj_dok_tech     "DOK-TECH: Wersja wtyczki"
sprawdz "E: readme obiecuje INSTRUKCJA-INSTALACJI, pliku brak" 1 psuj_brak_pliku "INSTRUKCJA-INSTALACJI.md"
echo
if [ "$BLEDY" -eq 0 ]; then echo "BRAMKA DOK=KOD SKLEPU ROZPOZNAJE KAZDY PODLOZONY ROZJAZD — i przepuszcza czysta kopie"; exit 0; fi
echo "BRAMKA DOK=KOD SKLEPU POMYLILA SIE $BLEDY RAZ(Y)"; exit 1
