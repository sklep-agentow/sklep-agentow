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
	sed -i "s/define( 'SEGM_WERSJA', '1.3.0' )/define( 'SEGM_WERSJA', '1.2.9' )/" "$1/wtyczki/$WTYCZKA/$WTYCZKA.php"
	przepakuj_z_zrodla "$1"
}

psuj_stable_tag() {
	przygotuj_czysty "$1"
	sed -i 's/^Stable tag: 1.3.0/Stable tag: 1.2.0/' "$1/wtyczki/$WTYCZKA/readme.txt"
	przepakuj_z_zrodla "$1"
}

psuj_changelog_md() {
	przygotuj_czysty "$1"
	sed -i 's/^## \[1.3.0\]/## [1.2.5]/' "$1/wtyczki/$WTYCZKA/CHANGELOG.md"
	przepakuj_z_zrodla "$1"
}

psuj_changelog_readme() {
	przygotuj_czysty "$1"
	sed -i 's/^= 1.3.0 =/= 1.2.4 =/' "$1/wtyczki/$WTYCZKA/readme.txt"
	przepakuj_z_zrodla "$1"
}

zostaw_niewydane() { :; }   # niczego nie ruszam — [Niewydane] zostaje niepuste

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
	sed -i "s|\t\"$WTYCZKA/includes/csv.php\"\n||" "$1/boty/buduj-paczki.sh"
	grep -v "\"$WTYCZKA/includes/csv.php\"" "$1/boty/buduj-paczki.sh" > "$1/boty/tmp" && mv "$1/boty/tmp" "$1/boty/buduj-paczki.sh"
}

psuj_brak_paczki() {
	przygotuj_czysty "$1"
	rm -f "$1/boty/paczki/$WTYCZKA"-*.zip
}

# --- przebieg -----------------------------------------------------------------

command -v zip > /dev/null || { echo "PROBA: brak narzedzia zip — nie zbuduje uszkodzonych paczek" >&2; exit 2; }

echo "=== WERSJA ROZJECHANA W JEDNYM Z PIECIU MIEJSC — bramka MA PASC ==="
sprawdz "stala w kodzie zostala w tyle (1.2.9)"        1 psuj_stala            "stała w kodzie mówi 1.2.9"
sprawdz "Stable tag w readme zostal w tyle (1.2.0)"    1 psuj_stable_tag       "Stable tag w readme.txt mówi 1.2.0"
sprawdz "CHANGELOG.md mowi o innym wydaniu (1.2.5)"    1 psuj_changelog_md     "CHANGELOG.md mówi 1.2.5"
sprawdz "sekcja Changelog w readme w tyle (1.2.4)"     1 psuj_changelog_readme "sekcja Changelog w readme.txt mówi 1.2.4"

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

echo
echo "=== KONTROLA ODWROTNA: czysty stan ma PRZECHODZIC ==="
sprawdz "wersja spojna, zip rowny repo co do bajtu"    0 nic_nie_psuj

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "BRAMKA ZAMROZENIA ODRZUCA KAZDY PODLOZONY ROZJAZD — i przepuszcza czysty stan"
	exit 0
fi
echo "BRAMKA ZAMROZENIA POMYLILA SIE $BLEDY RAZ(Y) — nie oddawaj jej w tym stanie"
exit 1
