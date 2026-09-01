#!/bin/bash
# paczka-obejmuje-kazdy-plik-wtyczki-proba-kontrolna.sh — dowod, ze bramka pokrycia paczki POTRAFI PASC.
#
# Zasada dowodu (CLAUDE.md): bramka, ktora nigdy nie pada, nie jest dowodem. Kazdy przypadek
# psuje DOKLADNIE JEDNA rzecz w kopii repo w /tmp i zada konkretnego kodu wyjscia ORAZ nazwania
# trafionej wtyczki. Kontrola odwrotna (czysta kopia → EXIT 0) idzie PIERWSZA — inaczej
# „bramka, ktora odrzuca wszystko” tez by zdala.
#
# ⛔ Rozroznia trzy werdykty, nie dwa: 0 = czysto, 1 = WADA allowlisty, 2 = AWARIA PRZYRZADU.
#    Przypadek „znika tryb tylko-sprawdz” musi dac 2, a nie 1: pomiar sie nie odbyl, wiec
#    zameldowanie wady byloby zmysleniem diagnozy.
#
# ⛔ MIESZKA W narzedzia-qa/, NIE W testy/ — i to nie jest kwestia porzadku.
#    `narzedzia-qa/proby-kontrolne-przelot.sh` zbiera proby wzorcem `find "$ZAKRES" -name
#    '*proba-kontrolna*.sh'`, a krok CI wola go z zakresem `narzedzia-qa`. Proba lezaca
#    w testy/ nie byla wiec uruchamiana przez NIC — dokladnie klasa I-3/I-4/I-8 (narzedzie-sierota).
#
# Uzycie: bash narzedzia-qa/paczka-obejmuje-kazdy-plik-wtyczki-proba-kontrolna.sh
# Kody:   0 = bramka rozpoznaje kazdy podlozony przypadek · 1 = nie rozpoznaje
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA=testy/paczka-obejmuje-kazdy-plik-wtyczki-test.sh
BAZA="$(mktemp -d)"; trap 'rm -rf "$BAZA"' EXIT
BLEDY=0
PRZYPADKI=0

zbuduj_czysty() {
	local r="$1"
	rm -rf "$r"; mkdir -p "$r/testy" "$r/boty"
	cp "$KORZEN/$BRAMKA" "$r/testy/"
	cp "$KORZEN/boty/buduj-paczki.sh" "$r/boty/"
	# Kopiujemy CALE drzewo wtyczek: bramka porownuje sciezki, wiec kopia musi miec
	# doklanie te same pliki co repo. Regula: nowy plik czytany przez bramke = nowy plik tutaj.
	cp -r "$KORZEN/wtyczki" "$r/wtyczki"
}

sprawdz() { # sprawdz <opis> <oczekiwany-exit> <funkcja-psujaca> <fragmenty-wyjscia rozdzielone |>
	local opis="$1" ocz="$2" psuj="$3" frag="$4" r="$BAZA/proba" wy kod=0 f
	PRZYPADKI=$(( PRZYPADKI + 1 ))
	zbuduj_czysty "$r"
	"$psuj" "$r"
	wy="$( cd "$r" && bash "$r/$BRAMKA" 2>&1 )" || kod=$?
	if [ "$kod" -ne "$ocz" ]; then
		printf '  BLAD %-52s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$ocz"
		printf '%s\n' "$wy" | head -8 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 )); return
	fi
	while IFS= read -r f; do
		if [ -n "$f" ] && ! printf '%s' "$wy" | grep -qF "$f"; then
			printf '  BLAD %-52s (kod OK, ale nie nazwala: %s)\n' "$opis" "$f"
			printf '%s\n' "$wy" | head -8 | sed 's/^/         /'
			BLEDY=$(( BLEDY + 1 )); return
		fi
	done <<< "$( printf '%s' "$frag" | tr '|' '\n' )"
	printf '  OK   %-52s (EXIT=%s)\n' "$opis" "$kod"
}

nic() { :; }

# ⭐ KALIBRACJA Z ZADANIA: stan SPRZED dopisania linii prywatnosc.php do allowlisty.
# Dokladnie ten stan lezal na gałęzi w commicie 97b5d6c — plik wtyczki byl, wpisu nie bylo.
psuj_prywatnosc() { sed -i '/sklep-agentow\/includes\/prywatnosc.php/d' "$1/boty/buduj-paczki.sh"; }

# Ta sama klasa u sasiada: nowy plik wtyczki, ktorego nikt nie dopisal.
psuj_nowy_plik_bota() { : > "$1/wtyczki/chatbot-live/includes/nowy-modul.php"; }

# Druga strona listy: wpis obiecuje plik, ktorego nie ma.
psuj_martwy_wpis() { rm -f "$1/wtyczki/segregator-maila/uninstall.php"; }

# CALA wtyczka, ktorej nikt nie pakuje — bramka bierze katalogi z dysku, wiec ma to zlapac.
psuj_nowa_wtyczka() { mkdir -p "$1/wtyczki/nowy-bot/includes"; : > "$1/wtyczki/nowy-bot/nowy-bot.php"; }

# ⛔ AWARIA PRZYRZADU, nie wada: znika wczesny powrot trybu tylko-sprawdz, wiec skrypt
# pakujacy idzie budowac naprawde. Bramka NIE MA prawa zameldowac wtedy wady allowlisty.
psuj_tryb_sprawdzania() { sed -i '/BUDUJ_TYLKO_SPRAWDZ:-0/,+3d' "$1/boty/buduj-paczki.sh"; }

echo "=== KONTROLA ODWROTNA: czysta kopia repo = brak wady ==="
sprawdz "czysta kopia repo → EXIT 0" 0 nic "KAZDY PLIK KAZDEJ WTYCZKI JEST NA ALLOWLISCIE|wtyczek sprawdzonych: 3"

echo
echo "=== KAZDY PRZYPADEK PSUJE DOKLADNIE JEDNA RZECZ ==="
sprawdz "KALIBRACJA: allowlista bez prywatnosc.php (stan 97b5d6c)" 1 psuj_prywatnosc      "BLAD sklep-agentow|prywatnosc.php|SPOZA allowlisty"
sprawdz "nowy plik wtyczki bota, nikt go nie dopisal"              1 psuj_nowy_plik_bota  "BLAD chatbot-live|nowy-modul.php"
sprawdz "wpis na liscie obiecuje plik, ktorego nie ma"             1 psuj_martwy_wpis     "BLAD segregator-maila|brak pliku z allowlisty|uninstall.php"
sprawdz "cala wtyczka, ktorej skrypt pakujacy nie zna"             1 psuj_nowa_wtyczka    "BLAD nowy-bot|nieznana wtyczka"

echo
echo "=== AWARIA PRZYRZADU MA DAC 2, NIE 1 — pomiar sie nie odbyl ==="
sprawdz "znika tryb BUDUJ_TYLKO_SPRAWDZ ze skryptu pakujacego"     2 psuj_tryb_sprawdzania "PRZYRZAD|bez wiarygodnego pomiaru"

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "BRAMKA POKRYCIA PACZKI ROZPOZNAJE KAZDY PODLOZONY PRZYPADEK ($PRZYPADKI) — i przepuszcza czysta kopie"
	exit 0
fi
echo "BRAMKA POKRYCIA PACZKI POMYLILA SIE $BLEDY RAZ(Y) NA $PRZYPADKI"
exit 1
