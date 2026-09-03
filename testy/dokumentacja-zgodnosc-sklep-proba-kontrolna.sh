#!/bin/bash
# dokumentacja-zgodnosc-sklep-proba-kontrolna.sh — dowód, że bramka dok=kod SKLEPU potrafi PAŚĆ.   WERSJA 1.1.0
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
BLEDY=0; PRZYPADKI=0; ROZJAZDY=0
WER=$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$KORZEN/$W/sklep-agentow.php" | head -1)

zbuduj_czysty() {
	local r="$1"; rm -rf "$r"; mkdir -p "$r/testy" "$r/$W/includes" "$r/$W/zrzuty" "$r/dokumentacja" "$r/boty"
	cp "$KORZEN/testy/dokumentacja-zgodnosc-sklep.sh" "$r/testy/"
	cp "$KORZEN/$W/readme.txt" "$KORZEN/$W/CHANGELOG.md" "$KORZEN/$W/DOKUMENTACJA-TECHNICZNA.md" "$KORZEN/$W/sklep-agentow.php" "$r/$W/"
	# 1.1.0: sekcja F bramki czyta INSTRUKCJA.md, PONAD-SPECYFIKACJE.md, zrzuty/, admin-produkty.php i allowlistę
	# buduj-paczki.sh — bez nich czysta kopia padała na 12/12 przypadkach (recenzja PR o instrukcji, KRYTYCZNA).
	# Reguła: kopia musi zawierać KAŻDY plik, który bramka otwiera; nowa asercja w bramce = nowy plik tutaj.
	cp "$KORZEN/$W/INSTRUKCJA.md" "$KORZEN/$W/PONAD-SPECYFIKACJE.md" "$r/$W/"
	cp "$KORZEN/$W/zrzuty/"*.png "$r/$W/zrzuty/"
	# ⛔ `front-produkt.php` doszedl 3.09 razem z trzema asercjami znacznika okna. Bez niego
	#    grep w piaskownicy pada, wynik jest PUSTY i kazdy z 21 przypadkow dostaje trzy stale
	#    rozjazdy nadmiarowe — proba spadla wtedy do 0/21, razem z kontrola odwrotna.
	#    Regula z komentarza wyzej dziala tylko wtedy, gdy sie ja WYKONA: dodajesz asercje
	#    czytajaca nowy plik -> dopisujesz ten plik TUTAJ, w tym samym commicie.
	cp "$KORZEN/$W/includes/admin.php" "$KORZEN/$W/includes/zakup.php" "$KORZEN/$W/includes/aktywacja.php" "$KORZEN/$W/includes/admin-produkty.php" "$KORZEN/$W/includes/front-produkt.php" "$r/$W/includes/"
	cp "$KORZEN/dokumentacja/INSTRUKCJA-INSTALACJI.md" "$r/dokumentacja/"
	cp "$KORZEN/boty/buduj-paczki.sh" "$r/boty/"
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
# licznik przypadków (także tych z BLAD): każde wywołanie sprawdz = 1 przypadek
sprawdz_() { PRZYPADKI=$((PRZYPADKI+1)); sprawdz "$@"; [ "$2" -eq 1 ] && ROZJAZDY=$((ROZJAZDY+${5:-1})); return 0; }

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
# Sekcja A1 (DOK=KOD tabeli stalych) doszla w PR #208 i nie miala tu ANI JEDNEGO przypadku,
# a ten skrypt konczy zdaniem "ROZPOZNAJE KAZDY PODLOZONY ROZJAZD" — nazwa szersza niz pomiar.
psuj_tabela_stalych() { sed -i 's/^| `SKLEPAG_LIMIT_POBRAN` | 10 |/| `SKLEPAG_LIMIT_POBRAN` | 11 |/' "$1/$W/DOKUMENTACJA-TECHNICZNA.md"; }
# Druga strona tej samej sekcji: petla, ktora przestaje dopasowywac wiersze, byla by cicho pusta.
psuj_uklad_tabeli()   { sed -i '0,/^| `SKLEPAG_WERSJA` |/s/^| `SKLEPAG_WERSJA` |/| SKLEPAG_WERSJA |/' "$1/$W/DOKUMENTACJA-TECHNICZNA.md"; }
# Sekcja F (INSTRUKCJA.md) — jeden przypadek na KAŻDĄ liczbę i na odsyłacze; bez tego 10 asercji weszło z jedną ręczną kalibracją.
psuj_instr_godziny()  { sed -i 's/ważny \*\*72 godziny\*\*/ważny **48 godziny**/' "$1/$W/INSTRUKCJA.md"; }
psuj_instr_pobran()   { sed -i 's/do \*\*10 pobrań\*\*/do **99 pobrań**/' "$1/$W/INSTRUKCJA.md"; }
psuj_instr_opinia()   { sed -i 's/opinii (ważny 30 dni)/opinii (ważny 14 dni)/' "$1/$W/INSTRUKCJA.md"; }
psuj_instr_mb()       { sed -i 's/ZIP, do 20 MB/ZIP, do 50 MB/' "$1/$W/INSTRUKCJA.md"; }
psuj_instr_minut()    { sed -i 's/co 10 minut/co 15 minut/' "$1/$W/INSTRUKCJA.md"; }
psuj_instr_zrzut()    { rm -f "$1/$W/zrzuty/ustawienia.png"; }
psuj_instr_allow()    { sed -i '/sklep-agentow\/zrzuty\/ustawienia.png/d' "$1/boty/buduj-paczki.sh"; }
psuj_instr_brak()     { rm -f "$1/$W/INSTRUKCJA.md"; }
psuj_ponad_brak()     { rm -f "$1/$W/PONAD-SPECYFIKACJE.md"; }
# ⛔ ZNACZNIK OKNA — trzy asercje dodane 3.09 nie mialy ANI JEDNEGO przypadku, wiec dalo sie
#    usunac wszystkie trzy i proba dalej mowila `21/21 OK` (recenzja #273, runda 7, WAZNA 1).
#    To ta sama klasa co wada, ktora ta runda naprawia: asercja bez wlasnego przypadku jest
#    obietnica, nie pomiarem. Kazda z trzech czesci ochrony psuta OSOBNO.
psuj_okno_pole()      { sed -i 's/<input type="hidden" name="okno"[^>]*>//' "$1/$W/includes/front-produkt.php"; }
psuj_okno_skrypt()    { sed -i "/sklepag-kup-formularz input\[name=okno\]/d" "$1/$W/includes/front-produkt.php"; }
psuj_okno_crypto()    { sed -i 's/c\.getRandomValues(a)/Math.random()/' "$1/$W/includes/front-produkt.php"; }
# ⛔ Mutant, ktory ODSLONIL wade nazwy: wycina SAMO przypisanie `p.value=s;`. Skrypt dalej
#    znajduje pole i dalej losuje bajty — tylko nic z nimi nie robi. Ochrona martwa, selektor
#    na miejscu. Do rundy 8 bramka tego nie widziala.
psuj_okno_nadpisanie(){ sed -i 's/p\.value=s;//' "$1/$W/includes/front-produkt.php"; }
# OGNIWO 3 (recenzja #273 r9, WAZNA 1): wycina SAMA PETLE skladajaca wartosc. Skrypt dalej
#    znajduje pole, dalej losuje 12 bajtow i dalej wykonuje `p.value=s;` — tylko `s` zostaje
#    pusty, wiec KAZDY kupujacy wysyla `okno=` i klucz wraca do postaci sprzed naprawy Q-5.
#    Do rundy 9 oba narzedzia dawaly na to EXIT=0 — zywa i martwa ochrona nie do odroznienia.
psuj_okno_zlozenie()  { sed -i 's/for(var i=0;i<a\.length;i++){s+=(a\[i\]<16?"0":"")+a\[i\]\.toString(16);}//' "$1/$W/includes/front-produkt.php"; }

echo "=== KONTROLA ODWROTNA: czysta kopia = 0 rozjazdow ==="
sprawdz_ "czysta kopia repo → EXIT 0"                         0 nic               "0 rozjazdów"
echo; echo "=== KAZDY ROZJAZD PODLOZONY OSOBNO → EXIT 1, nazwa pozycji, DOKLADNIE 1 rozjazd ==="
sprawdz_ "A: Stable tag ≠ Version"                            1 psuj_stable_tag   "Stable tag == Version"
sprawdz_ "Q5: pole 'okno' znika z formularza zakupu"          1 psuj_okno_pole    "pole 'okno' w formularzu zakupu"
# ⛔ Caly skrypt stoi w JEDNEJ linii front-produkt.php:652, wiec usuniecie tej linii zabija
#    WSZYSTKIE cztery asercje skryptowe. Liczba 3 -> 4 przy dolozeniu asercji ZLOZENIA.
sprawdz_ "Q5: skrypt losujacy znacznik usuniety (4 asercje)"  1 psuj_okno_skrypt  "skrypt ZNAJDUJE pole|ZLOZENIE wartosci|skrypt NADPISUJE wartosc|losowosc z crypto" 4
sprawdz_ "Q5: sama petla skladajaca wycieta (pusty znacznik u wszystkich)" 1 psuj_okno_zlozenie "ZLOZENIE wartosci z wylosowanych bajtow"
sprawdz_ "Q5: crypto podmienione na Math.random"              1 psuj_okno_crypto  "losowosc z crypto, nie z Math.random"
sprawdz_ "Q5: samo przypisanie p.value=s wyciete (ochrona martwa)" 1 psuj_okno_nadpisanie "skrypt NADPISUJE wartosc pola"
sprawdz_ "A: licencja w readme nie GPLv2"                     1 psuj_licencja     "License (forma readme WP)"
sprawdz_ "B: readme mówi 48 godzin, stała mówi 72"            1 psuj_godziny      "link ważny N godzin"
sprawdz_ "C: etykieta menu w readme przemianowana"            1 psuj_etykieta_rdm "menu 'Opinie'|readme 'Recenzje'" 2
sprawdz_ "C: NOWA pozycja menu w admin.php bez readme (W3)"   1 psuj_menu_kod     "menu 'Raporty'|liczba pozycji menu" 2
sprawdz_ "C: pozycja w readme, której nie ma w kodzie"        1 psuj_menu_readme  "readme 'Kupony'|liczba pozycji menu" 2
sprawdz_ "D: CHANGELOG bez wpisu bieżącej wersji"             1 psuj_changelog    "CHANGELOG: wpis"
sprawdz_ "D: DOK-TECH z inną wersją wtyczki"                  1 psuj_dok_tech     "DOK-TECH: Wersja wtyczki"
sprawdz_ "E: readme obiecuje INSTRUKCJA-INSTALACJI, pliku brak" 1 psuj_brak_pliku "INSTRUKCJA-INSTALACJI.md"
sprawdz_ "A1: tabela stalych podaje inna wartosc niz kod"      1 psuj_tabela_stalych "DOK=KOD: SKLEPAG_LIMIT_POBRAN"
# Psucie ukladu trafia w DWIE asercje naraz (licznik wierszy + kontrola DOK-TECH czytajaca
# ten sam wiersz), wiec liczba podana JAWNIE i obie pozycje muszą byc nazwane.
sprawdz_ "A1: rozjechany uklad tabeli — petla widzi mniej"     1 psuj_uklad_tabeli   "wierszy tabeli stalych przeczytanych|DOK-TECH: tabela stalych" 2
sprawdz_ "F: INSTRUKCJA mówi 48 godzin, stała 72"               1 psuj_instr_godziny  "INSTRUKCJA: link ważny N godzin"
sprawdz_ "F: INSTRUKCJA mówi 99 pobrań, stała 10"               1 psuj_instr_pobran   "INSTRUKCJA: do N pobrań"
sprawdz_ "F: INSTRUKCJA mówi opinia 14 dni, stała 30"           1 psuj_instr_opinia   "INSTRUKCJA: opinia ważna N dni"
sprawdz_ "F: INSTRUKCJA mówi ZIP 50 MB, stała 20"               1 psuj_instr_mb       "INSTRUKCJA: ZIP do N MB"
sprawdz_ "F: INSTRUKCJA mówi co 15 minut, cron 10"              1 psuj_instr_minut    "INSTRUKCJA: dopytanie co N minut"
sprawdz_ "F: zrzut z instrukcji nie istnieje w repo"            1 psuj_instr_zrzut    "INSTRUKCJA: odsyłacze do zrzutów"
sprawdz_ "F: zrzut z instrukcji poza allowlistą paczki"         1 psuj_instr_allow    "INSTRUKCJA: zrzuty wpisane do allowlisty"
# Brak pliku instrukcji trafia w asercje, które go CZYTAJĄ (plik + 5 liczb = 6); dwie asercje o odsyłaczach liczą wtedy 0 == 0 i przechodzą — liczba jawnie.
sprawdz_ "F: readme obiecuje INSTRUKCJA.md, pliku brak"         1 psuj_instr_brak     "plik wtyczki/sklep-agentow/INSTRUKCJA.md" 7
sprawdz_ "F: readme obiecuje PONAD-SPECYFIKACJE.md, pliku brak" 1 psuj_ponad_brak     "plik wtyczki/sklep-agentow/PONAD-SPECYFIKACJE.md"
echo
echo "PRZYPADKÓW: $((PRZYPADKI-BLEDY))/$PRZYPADKI OK · rozjazdów podłożonych łącznie: $ROZJAZDY"
if [ "$BLEDY" -eq 0 ]; then echo "BRAMKA DOK=KOD SKLEPU ROZPOZNAJE KAZDY PODLOZONY ROZJAZD — i przepuszcza czysta kopie"; exit 0; fi
echo "BRAMKA DOK=KOD SKLEPU POMYLILA SIE $BLEDY RAZ(Y)"; exit 1
