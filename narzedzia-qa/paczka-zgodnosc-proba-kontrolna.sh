#!/bin/bash
# paczka-zgodnosc-proba-kontrolna.sh — dowód, że bramka zamrożenia nie jest ozdobą.
#
# Bramka, która przy zamrażaniu mówi „wszystko gra", jest warta tyle, ile
# przypadków potrafi ODRZUCIĆ. Ten skrypt podkłada jej po kolei każdą awarię,
# przed którą ma chronić: rozjechaną wersję w każdym z pięciu miejsc, paczkę
# z podmienioną treścią pliku, plik zgubiony przy pakowaniu, plik-pasażera
# na gapę i allowlistę rozjechaną ze zbudowanym zipem.
#
# Pracuje na KOPII drzewa (KORZEN_PROBY), więc prawdziwe pliki repozytorium
# i prawdziwa paczka nie są dotykane ani razu.
#
# DWIE KLASY DOŁOŻONE PRZY ZAMRAŻANIU ChatBota 1.5.7: wydanie z zgodnym numerem,
# ale BEZ ani jednej linii opisu, oraz paczka z naszym śladem wewnętrznym w środku
# (TODO, adres maszyny dev, ścieżka drzewa roboczego, numer PR-a). Obie przechodziły
# przez porównanie numerów i sum kontrolnych, bo numer był zgodny, a ślad leżał
# w repozytorium DOKŁADNIE tak samo jak w paczce.
#
# ⚠️ Te przypadki biegną na chatbot-live, nie na segregatorze, i na nim też bada
# się stan czysty. Powód jest twardy, nie estetyczny: paczka segregatora 1.3.0 ma
# dziś ślady wewnętrzne (ścieżki `audyt/boty/…`, „recenzja PR #27/#37"), więc jako
# wzorzec czystości nie nadaje się do niczego. To pozycja dla właściciela tamtego
# modułu — tu jest odnotowana, nie obchodzona.
#
# Kody wyjścia: 0 = bramka rozpoznała wszystkie podłożone przypadki
#               1 = pomyliła się choć raz
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA="$KORZEN/narzedzia-qa/paczka-zgodnosc.sh"
WTYCZKA=segregator-maila

BLEDY=0
BAZA="$(mktemp -d)"
trap 'rm -rf "$BAZA"' EXIT

# --- kopia drzewa do zabawy ---------------------------------------------------

przygotuj_kopie() {
	local cel="$1"
	rm -rf "$cel"
	mkdir -p "$cel/wtyczki" "$cel/boty/paczki" "$cel/narzedzia-qa"
	cp -r "$KORZEN/wtyczki/$WTYCZKA" "$cel/wtyczki/"
	cp "$KORZEN/boty/paczki/$WTYCZKA"-*.zip "$cel/boty/paczki/"
	cp "$KORZEN/boty/buduj-paczki.sh" "$cel/boty/"
	cp "$BRAMKA" "$cel/narzedzia-qa/"
	# Skaner śladów jest osobnym plikiem (to samo źródło wzorców co bramka `slady`),
	# więc kopia drzewa musi go dostać — inaczej bramka słusznie melduje, że nie ma
	# czym sprawdzić, i próba bada brak narzędzia zamiast tego, co miała badać.
	cp "$KORZEN/narzedzia-qa/slady-wewnetrzne.sh" "$cel/narzedzia-qa/"
	cp "$KORZEN/README.md" "$cel/"  # 7. miejsce sklepu: odznaka wydania czyta README repo
}

# Wersja z nagłówka kopii — do składania nazwy zipa.
wersja_kopii() {
	sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' "$1/wtyczki/$WTYCZKA/$WTYCZKA.php" | head -1
}

# Przepakowuje zip kopii po tym, jak próba namieszała w jego zawartości.
przepakuj() {
	local cel="$1" wersja rozpakowane
	wersja="$(wersja_kopii "$cel")"
	rozpakowane="$cel/rozpakowane"
	rm -rf "$rozpakowane"; mkdir -p "$rozpakowane"
	unzip -q -o "$cel/boty/paczki/$WTYCZKA-$wersja.zip" -d "$rozpakowane"
	printf '%s' "$rozpakowane"
}

spakuj_z_powrotem() {
	local cel="$1" rozpakowane="$2" wersja
	wersja="$(wersja_kopii "$cel")"
	rm -f "$cel/boty/paczki/$WTYCZKA-$wersja.zip"
	( cd "$rozpakowane" && zip -q -r "$cel/boty/paczki/$WTYCZKA-$wersja.zip" . )
}

# sprawdz <opis> <oczekiwany-kod> <funkcja-psujaca> [oczekiwany-fragment-komunikatu]
sprawdz() {
	local opis="$1" oczekiwany="$2" psuj="$3" fragment="${4:-}"
	local cel="$BAZA/proba" wyjscie kod=0
	przygotuj_kopie "$cel"
	"$psuj" "$cel"
	wyjscie="$( KORZEN_PROBY="$cel" bash "$cel/narzedzia-qa/paczka-zgodnosc.sh" "$WTYCZKA" 2>&1 )" || kod=$?
	if [ "$kod" -ne "$oczekiwany" ]; then
		printf '  BLAD %-56s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany"
		printf '%s\n' "$wyjscie" | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	if [ -n "$fragment" ] && ! printf '%s' "$wyjscie" | grep -qF "$fragment"; then
		printf '  BLAD %-56s (kod OK, ale nie powiedziala CZEGO dotyczy)\n' "$opis"
		printf '         szukalam: %s\n' "$fragment"
		printf '%s\n' "$wyjscie" | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	printf '  OK   %-56s (EXIT=%s)\n' "$opis" "$kod"
}

# --- funkcje psujące ----------------------------------------------------------

# Buduje paczkę kopii ZE ŹRÓDŁA kopii. Bez tego każda próba, która rusza plik
# wtyczki, wywracałaby bramkę na kontroli treści zipa — i „OK" pod pozostałymi
# przypadkami byłoby przypadkiem, nie dowodem. Katalog wtyczki pokrywa się
# z allowlistą co do pliku, więc pakowanie całości daje ten sam zestaw.
# Piaskownica próby NIE jest repo gita — zamek wydanych paczek w buduj-paczki
# wymaga wtedy jawnej flagi (fail-closed). Tu jest ona ŚWIADOMA i bezpieczna:
# budujemy kopie w /tmp, żadna wydana paczka nie jest osiągalna.
export BUDUJ_POZA_REPO=1

przepakuj_z_zrodla() {
	local cel="$1" wersja etap
	wersja="$(wersja_kopii "$cel")"
	etap="$cel/etap"
	rm -rf "$etap"; mkdir -p "$etap"
	cp -r "$cel/wtyczki/$WTYCZKA" "$etap/"
	rm -f "$cel/boty/paczki/$WTYCZKA"-*.zip
	( cd "$etap" && zip -q -r "$cel/boty/paczki/$WTYCZKA-$wersja.zip" . )
}

# Punkt wyjścia KAŻDEJ próby: sekcja [Niewydane] opróżniona (żeby badać jeden
# rozjazd naraz, a nie ten, który w repo już siedzi) i paczka zbudowana na nowo.
przygotuj_czysty() {
	local cel="$1" plik="$1/wtyczki/$WTYCZKA/CHANGELOG.md"
	awk '/^## \[Niewydane\]/{print; print ""; pomijaj=1; next} /^## \[[0-9]/{pomijaj=0} !pomijaj' "$plik" > "$plik.nowy"
	mv "$plik.nowy" "$plik"
	przepakuj_z_zrodla "$cel"
}

nic_nie_psuj() { przygotuj_czysty "$1"; }

psuj_stala() {
	przygotuj_czysty "$1"
	# Wersja BIEŻĄCA, nie zaszyta: podstawki z literalnym numerem zgniły cicho
	# przy pierwszym bumpie (1.3.0→1.3.1: sed nie trafiał, mutant nie wchodził,
	# a „bramka złapała" było fałszywe — wykryte przy 1.3.2, recenzja PR #168).
	local w; w="$(wersja_kopii "$1")"
	sed -i "s/define( 'SEGM_WERSJA', '$w' )/define( 'SEGM_WERSJA', '1.2.9' )/" "$1/wtyczki/$WTYCZKA/$WTYCZKA.php"
	kotwica "stala 1.2.9" "$( grep -qF "'1.2.9'" "$1/wtyczki/$WTYCZKA/$WTYCZKA.php" && echo tak )"
	przepakuj_z_zrodla "$1"
}

psuj_stable_tag() {
	przygotuj_czysty "$1"
	local w; w="$(wersja_kopii "$1")"
	sed -i "s/^Stable tag: $w/Stable tag: 1.2.0/" "$1/wtyczki/$WTYCZKA/readme.txt"
	kotwica "Stable tag 1.2.0" "$( grep -q '^Stable tag: 1.2.0' "$1/wtyczki/$WTYCZKA/readme.txt" && echo tak )"
	przepakuj_z_zrodla "$1"
}

psuj_changelog_md() {
	przygotuj_czysty "$1"
	local w; w="$(wersja_kopii "$1")"
	sed -i "s/^## \[$w\]/## [1.2.5]/" "$1/wtyczki/$WTYCZKA/CHANGELOG.md"
	kotwica "CHANGELOG 1.2.5" "$( grep -q '^## \[1.2.5\]' "$1/wtyczki/$WTYCZKA/CHANGELOG.md" && echo tak )"
	przepakuj_z_zrodla "$1"
}

psuj_changelog_readme() {
	przygotuj_czysty "$1"
	local w; w="$(wersja_kopii "$1")"
	sed -i "s/^= $w =/= 1.2.4 =/" "$1/wtyczki/$WTYCZKA/readme.txt"
	kotwica "readme = 1.2.4 =" "$( grep -q '^= 1.2.4 =' "$1/wtyczki/$WTYCZKA/readme.txt" && echo tak )"
	przepakuj_z_zrodla "$1"
}

zostaw_niewydane() {
	# Sama sadzi pozycję ponad wydanie — poleganie na tym, że repo AKURAT ma
	# niepuste [Niewydane], gniło przy każdym zamrożeniu (wtedy jest puste).
	sed -i '/^## \[Niewydane\]/a\
- podstawiona pozycja ponad wydanie (proba kontrolna)' "$1/wtyczki/$WTYCZKA/CHANGELOG.md"
	kotwica "pozycja ponad wydanie" "$( grep -q 'podstawiona pozycja ponad wydanie' "$1/wtyczki/$WTYCZKA/CHANGELOG.md" && echo tak )"
	przepakuj_z_zrodla "$1"
}

psuj_tresc_pliku_w_zipie() {
	local cel="$1" rozpakowane
	przygotuj_czysty "$cel"
	rozpakowane="$(przepakuj "$cel")"
	# Ta sama nazwa, inna treść — dokładnie to, czego lista plików nie wykryje.
	printf '\n// wersja z poprzedniego PR-a\n' >> "$rozpakowane/$WTYCZKA/includes/silnik.php"
	spakuj_z_powrotem "$cel" "$rozpakowane"
}

psuj_brak_pliku_w_zipie() {
	local cel="$1" rozpakowane
	przygotuj_czysty "$cel"
	rozpakowane="$(przepakuj "$cel")"
	rm -f "$rozpakowane/$WTYCZKA/uninstall.php"
	spakuj_z_powrotem "$cel" "$rozpakowane"
}

psuj_pasazer_w_zipie() {
	local cel="$1" rozpakowane
	przygotuj_czysty "$cel"
	rozpakowane="$(przepakuj "$cel")"
	echo "KLUCZ=sekret-ktory-nie-powinien-wyjsc" > "$rozpakowane/$WTYCZKA/.env"
	spakuj_z_powrotem "$cel" "$rozpakowane"
}

psuj_allowliste() {
	przygotuj_czysty "$1"
	# Jedna linia, nie dwie: sed w trybie jednowierszowym nie dopasuje "\n",
	# więc próba usunięcia wpisu sed-em była martwa (recenzja PR #101).
	grep -v "\"$WTYCZKA/includes/csv.php\"" "$1/boty/buduj-paczki.sh" > "$1/boty/tmp" && mv "$1/boty/tmp" "$1/boty/buduj-paczki.sh"
}

psuj_wersja_nie_jest_numerem() {
	przygotuj_czysty "$1"
	# Kropka na końcu: wszystkie miejsca „zgadzają się", a numer jest zepsuty.
	local w; w="$(wersja_kopii "$1")"
	sed -i "s/^ \* Version: $w/ * Version: $w./" "$1/wtyczki/$WTYCZKA/$WTYCZKA.php"
	kotwica "Version z kropka" "$( grep -qE '^ \* Version: [0-9.]+\.$' "$1/wtyczki/$WTYCZKA/$WTYCZKA.php" && echo tak )"
	przepakuj_z_zrodla "$1"
}

psuj_brak_paczki() {
	przygotuj_czysty "$1"
	rm -f "$1/boty/paczki/$WTYCZKA"-*.zip
}

# --- klasa: wydanie bez opisu i ślady wewnętrzne (chatbot-live) ---------------

# Mutacja, która NIE weszła do pliku, daje fałszywe „bramka złapała" — bo nie było
# czego łapać. Kotwice gniją po każdym refaktorze, więc każda próba niżej sprawdza
# najpierw, że podłożyła to, co miała podłożyć.
kotwica() {
	local opis="$1" warunek_ok="$2"
	if [ "$warunek_ok" != "tak" ]; then
		echo "PROBA: mutacja [$opis] NIE weszla do pliku — kotwica zgnila, przypadek nic nie dowodzi" >&2
		exit 2
	fi
}

# Ile linii listy ma sekcja danej wersji w CHANGELOG.md kopii.
ile_pozycji_changelog() {
	awk -v w="$2" 'index($0, "## [" w "]") == 1 { s = 1; next } /^## \[/ { s = 0 } s && /^[-*] / { i++ } END { print i + 0 }' "$1"
}

psuj_pusty_wpis_changelog() {
	local cel="$1" plik wersja
	przygotuj_czysty "$cel"
	wersja="$(wersja_kopii "$cel")"
	plik="$cel/wtyczki/$WTYCZKA/CHANGELOG.md"
	# Nagłówek wydania ZOSTAJE (numer nadal zgodny w pięciu miejscach), znika treść.
	awk -v w="$wersja" 'index($0, "## [" w "]") == 1 { print; s = 1; next } /^## \[/ { s = 0 } !s' "$plik" > "$plik.nowy"
	mv "$plik.nowy" "$plik"
	kotwica "pusty wpis CHANGELOG" "$( [ "$(ile_pozycji_changelog "$plik" "$wersja")" -eq 0 ] && grep -q "## \[$wersja\]" "$plik" && echo tak )"
	przepakuj_z_zrodla "$cel"
}

psuj_pusta_sekcja_readme() {
	local cel="$1" plik wersja ile
	przygotuj_czysty "$cel"
	wersja="$(wersja_kopii "$cel")"
	plik="$cel/wtyczki/$WTYCZKA/readme.txt"
	awk -v w="$wersja" 'index($0, "= " w " =") == 1 { print; s = 1; next } /^= [0-9]/ { s = 0 } /^== / { s = 0 } !s' "$plik" > "$plik.nowy"
	mv "$plik.nowy" "$plik"
	ile="$(awk -v w="$wersja" 'index($0, "= " w " =") == 1 { s = 1; next } /^= [0-9]/ { s = 0 } /^== / { s = 0 } s && NF { i++ } END { print i + 0 }' "$plik")"
	kotwica "pusta sekcja readme" "$( [ "$ile" -eq 0 ] && grep -q "^= $wersja =" "$plik" && echo tak )"
	przepakuj_z_zrodla "$cel"
}

# Ślad wewnętrzny dopisany do PLIKU ŹRÓDŁOWEGO i spakowany razem z resztą — czyli
# zip zgadza się z repo co do bajtu, a mimo to kupujący dostaje naszą notatkę.
dopisz_slad() {
	local cel="$1" tresc="$2" plik
	przygotuj_czysty "$cel"
	plik="$cel/wtyczki/$WTYCZKA/includes/silnik.php"
	printf '%s\n' "$tresc" >> "$plik"
	kotwica "$tresc" "$( grep -qF "$tresc" "$plik" && echo tak )"
	przepakuj_z_zrodla "$cel"
}

psuj_todo_w_paczce()      { dopisz_slad "$1" "// TODO: dopisac obsluge zalacznikow przed oddaniem"; }
psuj_adres_dev()          { dopisz_slad "$1" "// sprawdzone na http://localhost:8890/wp-admin/"; }
psuj_sciezke_robocza()    { dopisz_slad "$1" "// liczbe pilnuje testy/statystyki-test.php"; }
psuj_obieg_pracy()        { dopisz_slad "$1" "// uwaga recenzenta do PR #99 — zostawiamy swiadomie"; }
# Goły numer zgłoszenia, bez słowa „PR" (recenzja #182 — wzorzec 1.1.1 tego nie widział).
psuj_numer_goly()         { dopisz_slad "$1" "// martwy wpis w harmonogramie sypie bledami — #170)"; }
# Kod znaleziska z naszego rejestru audytowego (D-4) — bramka nie znała formatu litera(y)-myślnik-numer.
psuj_kod_znaleziska()     { dopisz_slad "$1" "// naprawiono zgodnie ze zgloszeniem A7b-F2"; }

psuj_doktech() {
	# Szóste miejsce wersji (nagłówek DOKUMENTACJA-TECHNICZNA.md) — dopisane
	# do bramki po recenzji PR #168; ta podstawka pilnuje, żeby zostało w niej na zawsze.
	przygotuj_czysty "$1"
	local w; w="$(wersja_kopii "$1")"
	sed -i "s/Wersja wtyczki: $w/Wersja wtyczki: 1.2.8/" "$1/wtyczki/$WTYCZKA/DOKUMENTACJA-TECHNICZNA.md"
	kotwica "doktech 1.2.8" "$( grep -q 'Wersja wtyczki: 1.2.8' "$1/wtyczki/$WTYCZKA/DOKUMENTACJA-TECHNICZNA.md" && echo tak )"
	przepakuj_z_zrodla "$1"
}

# --- przebieg -----------------------------------------------------------------

command -v zip > /dev/null || { echo "PROBA: brak narzedzia zip — nie zbuduje uszkodzonych paczek" >&2; exit 2; }

echo "=== WERSJA ROZJECHANA W JEDNYM Z SZESCIU MIEJSC — bramka MA PASC ==="
sprawdz "stala w kodzie zostala w tyle (1.2.9)"        1 psuj_stala            "stała w kodzie mówi 1.2.9"
sprawdz "Stable tag w readme zostal w tyle (1.2.0)"    1 psuj_stable_tag       "Stable tag w readme.txt mówi 1.2.0"
sprawdz "CHANGELOG.md mowi o innym wydaniu (1.2.5)"    1 psuj_changelog_md     "CHANGELOG.md mówi 1.2.5"
sprawdz "sekcja Changelog w readme w tyle (1.2.4)"     1 psuj_changelog_readme "sekcja Changelog w readme.txt mówi 1.2.4"
sprawdz "DOKUMENTACJA-TECHNICZNA w tyle (1.2.8)"       1 psuj_doktech          "nagłówek DOKUMENTACJA-TECHNICZNA.md mówi 1.2.8"

echo
echo "=== KOD PONAD WERSJA — bramka MA PASC ==="
sprawdz "niepusta sekcja [Niewydane] przy zamrazaniu"  1 zostaw_niewydane      "kod jest PONAD wersją"

echo
echo "=== PACZKA ROZJECHANA Z REPO — bramka MA PASC ==="
sprawdz "plik w zipie ma TA SAMA NAZWE, INNA TRESC"    1 psuj_tresc_pliku_w_zipie "INNA TREŚĆ"
sprawdz "plik wtyczki zgubiony przy pakowaniu"         1 psuj_brak_pliku_w_zipie  "NIE WCHODZI do paczki"
sprawdz "pasazer na gape w zipie (.env)"               1 psuj_pasazer_w_zipie     "NIE MA GO w repozytorium"
sprawdz "allowlista rozjechana ze zbudowanym zipem"    1 psuj_allowliste          "różni się od allowlisty"
sprawdz "nie ma paczki dla wersji z naglowka"          1 psuj_brak_paczki         "nie ma paczki"
sprawdz "wersja w naglowku nie jest numerem (1.3.)"    1 psuj_wersja_nie_jest_numerem "nie wygląda jak numer wydania"

echo
echo "=== WYDANIE BEZ OPISU I SLADY WEWNETRZNE (chatbot-live) — bramka MA PASC ==="
WTYCZKA=chatbot-live
sprawdz "CHANGELOG: naglowek wydania bez ani jednej pozycji" 1 psuj_pusty_wpis_changelog "ZERO TREŚCI"
sprawdz "readme.txt: sekcja wydania pusta"                   1 psuj_pusta_sekcja_readme   "ZERO TREŚCI"
sprawdz "TODO zapakowane do paczki klienta"                  1 psuj_todo_w_paczce         "znacznik roboczy"
sprawdz "adres maszyny dev w paczce (localhost:8890)"        1 psuj_adres_dev             "adres maszyny dev"
sprawdz "sciezka naszego drzewa roboczego w paczce"          1 psuj_sciezke_robocza       "ścieżka naszego drzewa roboczego"
sprawdz "numer PR-a i recenzent w paczce"                    1 psuj_obieg_pracy           "nasz obieg pracy"
sprawdz "goly numer zgloszenia (— #170) bez slowa PR"        1 psuj_numer_goly            "nasz obieg pracy"
sprawdz "kod znaleziska audytu (A7b-F2) w paczce"            1 psuj_kod_znaleziska        "kod znaleziska audytu"

echo
echo
echo "=== ODZNAKA WERSJI W README (sklep-agentow, 7. miejsce) — dolozona 31.08 ==="
WTYCZKA=sklep-agentow
psuj_badge() { sed -i 's|badge/wydanie-v[0-9.]*-|badge/wydanie-v9.9.9-|' "$1/README.md"; }
sprawdz "odznaka README rozjechana z naglowkiem"       1 psuj_badge   "odznaka wydania w README"
psuj_badge_brak() { sed -i "/badge\/wydanie-v/d" "$1/README.md"; }  # druga sciezka odrzucenia (r1 #238 D5)
sprawdz "odznaki wydania w README w ogole NIE MA"      1 psuj_badge_brak "odznaka wydania w README repo — nie umiem odczytać wersji"
sprawdz "sklep czysty: SIEDEM miejsc + zip + opis"     0 nic_nie_psuj "siedem miejsc zgodnych"

echo "=== KONTROLA ODWROTNA: czysty stan ma PRZECHODZIC ==="
# Bez tego „naprawa" mogłaby odrzucać WSZYSTKO i świecić na zielono.
WTYCZKA=chatbot-live  # przywrocenie po bloku sklepu (r1 #238 W2): kontrola odwrotna MA pokrywac sciezke BEZ odznaki
sprawdz "czysta paczka (chatbot, SZESC miejsc): wersja, sumy, opis, zero sladow" 0 nic_nie_psuj "sześć miejsc zgodnych"

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "BRAMKA ZAMROZENIA ODRZUCA KAZDY PODLOZONY ROZJAZD — i przepuszcza czysty stan"
	exit 0
fi
echo "BRAMKA ZAMROZENIA POMYLILA SIE $BLEDY RAZ(Y) — nie oddawaj jej w tym stanie"
exit 1
