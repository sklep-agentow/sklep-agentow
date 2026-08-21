#!/bin/bash
# BRAMKA: ksiegowosc znalezisk audytu.
#
# Po co powstala (21.08): mielismy zasade "kazdy blad dostaje straznika z exit 1
# wpiety do CI" i NIE BYLA SPELNIONA ANI RAZU. W testy/ lezalo 57 plikow, CI nie
# uruchamialo zadnego, a PR #135 wniosl dwa CELOWO PADAJACE testy i przeszedl
# na zielono. Wszystko bylo doradcze.
#
# Czego ta bramka NIE robi: nie uruchamia testow. Wszystkie wymagaja zywego stosu
# Dockera (psuja kod w kontenerze), wiec w rownoleglym CI deptalyby sobie po stosie.
# Pilnuje KSIEGOWOSCI: ze znalezisko istnieje, ma test albo policzony dlug, ma status
# i nikt go po cichu nie usunal.
#
# Uzycie: bash narzedzia-qa/bramka-znaleziska.sh   (z katalogu repo)
set -u

REJ=audyt/REJESTR-ZNALEZISK.md
BLEDY=0
KONTROLE=0

zglos() { printf '  BLAD %s\n' "$1" >&2; BLEDY=$((BLEDY+1)); }
ok()    { printf '  OK   %s\n' "$1"; }
licz()  { KONTROLE=$((KONTROLE+1)); }

echo "=== BRAMKA ZNALEZISK ==="

# 1. Rejestr musi istniec. Bez niego reszta kontroli nie ma czego czytac.
licz
if [ ! -f "$REJ" ]; then
	zglos "rejestr nie istnieje: $REJ"
	echo "WYKONANE KONTROLE: $KONTROLE, BLEDY: $BLEDY" >&2
	exit 1
fi
ok "rejestr istnieje"

# 1b. WIERSZE ZNALEZISK CZYTAMY WYLACZNIE Z SEKCJI "## Znaleziska".
#     Wzorzec "| + kod w grawisach + |" pasuje do KAZDEJ tabeli w pliku. Rejestr dostal
#     slownik prefiksow soczewek w tabeli i bramka policzyla jego wiersze jako siedem
#     nieistniejacych znalezisk z nieznanym statusem (PR #143). Wada byla w bramce, nie
#     w rejestrze: ksiegowosc znalezisk ma czytac TABELE ZNALEZISK, a nie dowolna tabele.
#     Zawezenie: od naglowka "## Znaleziska" do nastepnego naglowka poziomu "## ".
WIERSZE=$(awk '
	/^## Znaleziska[[:space:]]*$/ { w=1; next }
	w && /^## /                   { w=0 }
	w                             { print }
' "$REJ" | grep -P '^\| `[^`]+` \|' || true)

# 2. Liczba OTWARTYCH z naglowka musi zgadzac sie z liczba wierszy o tym statusie.
#    Dopisanie znaleziska ZATRZYMUJE bramke, dopoki ktos swiadomie nie podniesie liczby.
licz
DEKLAROWANE=$(grep -oP '^## Liczba znalezisk OTWARTYCH: \K[0-9]+' "$REJ" | head -1)
POLICZONE=$(printf '%s\n' "$WIERSZE" | grep -cP '^\| `[^`]+` \|.*\| OTWARTE \|$' || true)
if [ -z "$DEKLAROWANE" ]; then
	zglos "brak naglowka z liczba znalezisk OTWARTYCH"
elif [ "$DEKLAROWANE" != "$POLICZONE" ]; then
	zglos "liczba OTWARTYCH: naglowek mowi [$DEKLAROWANE], wierszy jest [$POLICZONE]"
else
	ok "liczba OTWARTYCH zgodna z naglowkiem = $POLICZONE"
fi

# 3. Kazdy test wymieniony w rejestrze musi ISTNIEC jako plik — ALBO byc uczciwie
#    zadeklarowany jako brak.
#
#    Dwa rozne przypadki, ktore ta bramka wczesniej mylila:
#      a) "BRAK — do dopisania"  -> znalezisko BEZ straznika. To DLUG, nie usterka
#         rejestru. Liczymy go i pilnujemy nizej (kontrola 3c), zeby tylko malal.
#      b) konkretna nazwa pliku, ktorego nie ma -> TWARDY BLAD. Martwy odsylacz
#         UDAJE, ze straznik stoi, i jest grozniejszy niz uczciwe "brak".
SPRAWDZONYCH=0
DLUG=0
while IFS='|' read -r _ KOD _ TEST STATUS _; do
	KOD=$(echo "$KOD" | tr -d ' `')
	TEST=$(echo "$TEST" | tr -d ' `')
	STATUS=$(echo "$STATUS" | tr -d ' ')
	[ -n "$KOD" ] || continue
	SPRAWDZONYCH=$((SPRAWDZONYCH+1))
	licz
	case "$TEST" in
		BRAK*)
			DLUG=$((DLUG+1))
			ok "$KOD -> BEZ STRAZNIKA, policzone do dlugu ($STATUS)"
			;;
		*)
			if [ ! -f "$TEST" ]; then
				zglos "znalezisko $KOD wskazuje test, ktorego NIE MA: $TEST"
			else
				ok "$KOD -> $TEST ($STATUS)"
			fi
			;;
	esac
done < <(printf '%s\n' "$WIERSZE")

# 3b. Sama petla nie wystarczy. Gdyby wzorzec wiersza przestal pasowac (zmiana ukladu
#     tabeli albo zmiana nazwy naglowka "## Znaleziska"), petla nie zrobilaby ANI JEDNEGO
#     sprawdzenia, a bramka swiecilaby zielono. Ta sama pulapka, ktora zlapala nas przy
#     tabeli stalych w dokumentacja-zgodnosc.sh.
#     ⛔ Po zawezeniu do sekcji ta kontrola jest WAZNIEJSZA niz wczesniej: teraz zepsuc
#     odczyt moze samo przemianowanie naglowka.
licz
if [ "$SPRAWDZONYCH" -lt 1 ]; then
	zglos "wzorzec wiersza nie zlapal ANI JEDNEGO znaleziska w sekcji '## Znaleziska' — zmienil sie uklad tabeli albo nazwa naglowka?"
else
	ok "wierszy znalezisk odczytanych z tabeli = $SPRAWDZONYCH"
fi

# 3c. DLUG STRAZNIKOW musi zgadzac sie z liczba zadeklarowana w rejestrze.
#     Prowadzimy go tak samo jak dlug sladow w sklepie: liczba ma prawo TYLKO MALEC,
#     a kazda zmiana ma byc swiadoma. Rozjazd w OBIE strony zatrzymuje bramke:
#       - dlug urosl  -> ktos dopisal znalezisko bez straznika i nie powiedzial tego wprost,
#       - dlug zmalal -> straznik powstal (podnies liczbe w dol swiadomie) ALBO ktos
#                        skasowal wiersz znaleziska i nikt by tego nie zauwazyl.
licz
DLUG_DEKLAROWANY=$(grep -oP '^## Znalezisk BEZ strażnika: \K[0-9]+' "$REJ" | head -1)
if [ -z "$DLUG_DEKLAROWANY" ]; then
	zglos "brak naglowka '## Znalezisk BEZ strażnika: <liczba>' — dlug straznikow musi byc zadeklarowany wprost"
elif [ "$DLUG_DEKLAROWANY" != "$DLUG" ]; then
	zglos "DLUG STRAZNIKOW: naglowek mowi [$DLUG_DEKLAROWANY], a wierszy bez testu jest [$DLUG]"
else
	ok "dlug straznikow zgodny z naglowkiem = $DLUG"
fi

# 4. Kazdy raport audytu musi byc wymieniony w rejestrze. Inaczej nowy raport wnosi
#    znaleziska, ktore nigdzie nie sa policzone — dokladnie to, co sie nam stalo.
for RAPORT in audyt/boty/RAPORT-*.md; do
	[ -e "$RAPORT" ] || continue
	NAZWA=$(basename "$RAPORT")
	licz
	# ⛔ Szukamy TYLKO w sekcji "Raporty objete rejestrem", nie po calym pliku.
	# Recenzja #143: grep po calym pliku przechodzil, bo nazwa raportu pada tez
	# w slowniku kodow — sekcja obiecywala wiecej pokrycia, niz bramka sprawdzala.
	if sed -n '/^## Raporty objęte rejestrem/,/^## /p' "$REJ" | grep -qF "$NAZWA"; then
		ok "raport objety rejestrem: $NAZWA"
	else
		zglos "raport POZA rejestrem: $NAZWA — jego znaleziska nie sa policzone"
	fi
done

# 5. Status musi byc jednym z trzech znanych. Wymyslony status = znalezisko, ktore
#    wypada z ksiegowosci, bo zadna kontrola go nie liczy.
licz
ZLE_STATUSY=$(printf '%s\n' "$WIERSZE" | awk -F'|' 'NF>1 {gsub(/ /,"",$5); print $5}' \
	| grep -vcE '^(OTWARTE|WNAPRAWIE|ZAMKNIETE)$' || true)
if [ "$ZLE_STATUSY" != "0" ]; then
	zglos "znalezisk z nieznanym statusem: $ZLE_STATUSY (dozwolone: OTWARTE, W NAPRAWIE, ZAMKNIETE)"
else
	ok "wszystkie statusy sa ze znanego zbioru"
fi

echo
echo "DLUG STRAZNIKOW: $DLUG znalezisk bez testu (ma prawo tylko malec)"
echo "WYKONANE KONTROLE: $KONTROLE"
if [ "$BLEDY" -gt 0 ]; then
	echo "NIEZALICZONE: $BLEDY" >&2
	exit 1
fi
echo "KSIEGOWOSC ZNALEZISK ZGODNA."
