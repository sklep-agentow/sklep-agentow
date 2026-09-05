#!/bin/bash
# Proba kontrolna gałęzi `brak zip` w narzedzia-qa/proby-kontrolne-przelot.sh.
#
# PO CO POWSTAŁA (recenzja #272 r13, uwaga WAŻNA 1). Naprawa WYMAGA_ZIP wykonuje się
# WYŁĄCZNIE na wykonawcy, który nie ma `zip` — czyli nigdy tam, gdzie ją pisano. Przez
# dwanaście rund dowodem na nią były narzędzia jednorazowe, budowane obok kodu i kasowane
# po użyciu. Skutek zmierzony w r13: w jedynej nowej linii tej gałęzi siedziały odwrotne
# apostrofy (`` `zip` `` w łańcuchu w cudzysłowach = PODSTAWIENIE KOMENDY), więc komunikat
# gubił dokładnie to słowo, dla którego powstał, i dokładał `zip: command not found` do
# stderr. Żaden przebieg CI tego nie zobaczył, bo oba runnery `zip` mają.
#
# CO MIERZY — trzy rzeczy, każda osobno, na PRAWDZIWYM skrypcie:
#   1. komunikat pominięcia NAZYWA brakujące narzędzie (zawiera słowo `zip`),
#   2. stderr NIE zawiera `command not found`,
#   3. próby z WYMAGA_ZIP są POMIJANE, a nie liczone jako PORAŻKA.
# Plus kontrola odwrotna: z podłożonymi odwrotnymi apostrofami asercje 1 i 2 MUSZĄ PAŚĆ.
#
# Użycie: bash narzedzia-qa/przelot-brak-zip-proba-kontrolna.sh
# Kody:   0 = strażnik widzi podłożony błąd · 1 = ślepy · 2 = nie zmierzyłam (brak warunków)
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRZELOT="$KORZEN/narzedzia-qa/proby-kontrolne-przelot.sh"
BLEDY=0
NIETRAFIONE=0

[ -f "$PRZELOT" ] || { echo "PROBA: nie ma $PRZELOT" >&2; exit 2; }

# --- atrapa PATH: wszystko poza `zip` -----------------------------------------
# Nie usuwamy `zip` z systemu — budujemy katalog z dowiązaniami do narzędzi, których
# przelot potrzebuje, i pomijamy w nim `zip`. To jedyny sposób, żeby wejść w gałąź
# `brak zip` na wykonawcy, który `zip` ma.
ATRAPA="$(mktemp -d)" || { echo "PROBA: nie moge zrobic katalogu atrapy" >&2; exit 2; }
ROBOCZY="$(mktemp -d)" || { echo "PROBA: nie moge zrobic katalogu zakresu" >&2; exit 2; }

# ⛔ MUTANT MUSI STAC W TYM SAMYM KORZENIU CO ORYGINAL (recenzja #272 r14, WAZNA 2).
# `proby-kontrolne-przelot.sh` liczy swoj KORZEN ze sciezki wlasnego pliku
# (`dirname "${BASH_SOURCE[0]}"/..`), wiec kopia w /tmp mierzylaby INNE repozytorium —
# padalaby z zupelnie innego powodu niz podlozony blad, a kontrola odwrotna liczylaby
# to jako sukces. Klucz: `git status --porcelain -uno` IGNORUJE pliki niesledzone
# (zmierzone), wiec mutant obok oryginalu NIE brudzi drzewa i przelot nie odmawia.
MUTANT="$KORZEN/narzedzia-qa/.przelot-mutant-proby.tmp.sh"
# ⛔ EXIT INT TERM, nie samo EXIT (recenzja #272 r15, „nieodtworzone przypuszczenia").
# Od tej rundy mutant lezy WEWNATRZ repozytorium, wiec przerwanie z klawiatury zostawiloby
# smiec w drzewie roboczym — a przelot odmawia pracy na brudnym drzewie.
trap 'rm -rf "$ATRAPA" "$ROBOCZY"; rm -f "$MUTANT" "$KORZEN/narzedzia-qa/.przelot-whoami.tmp.sh"' EXIT INT TERM

for N in bash sh git sed awk grep find sort mktemp date printf cat rm cp mv \
         mkdir chmod head tail cut tr wc dirname basename docker php jq unzip; do
	SC="$(command -v "$N" 2> /dev/null)" && ln -sf "$SC" "$ATRAPA/$N"
done
if [ ! -e "$ATRAPA/git" ] || [ ! -e "$ATRAPA/sed" ]; then
	echo "PROBA: atrapa PATH niekompletna (brak git albo sed) — nie mierze." >&2
	exit 2
fi
if PATH="$ATRAPA" command -v zip > /dev/null 2>&1; then
	echo "PROBA: atrapa PATH NADAL widzi zip — nie mierze." >&2
	exit 2
fi

# --- zakres: tylko próby z WYMAGA_ZIP, żeby nic ciężkiego nie ruszyło ----------
# Obie i tak zostaną POMINIĘTE (brak zip), więc ten przelot nic nie uruchamia.
for N in k7-koniec-proba-kontrolna.sh paczka-zgodnosc-proba-kontrolna.sh; do
	[ -f "$KORZEN/narzedzia-qa/$N" ] || { echo "PROBA: brak $N" >&2; exit 2; }
	cp "$KORZEN/narzedzia-qa/$N" "$ROBOCZY/$N"
done

# ⛔ PRZELOT_ODLAMEK JAWNIE WYCZYSZCZONY (recenzja #287 WAZNA 2 c.d.). Ta proba mierzy
# przelot na WLASNYM, prowizorycznym ZAKRESIE ($ROBOCZY, nie „narzedzia-qa"), a od tego PR-a
# odlamek wymaga zakresu „narzedzia-qa" pod rygorem odmowy. W CI zadanie `proby` ustawia
# PRZELOT_ODLAMEK na poziomie JOBA (quality.yml), wiec kazdy proces w tym jobie — w tym ten
# zagniezdzony przelot — dziedziczy je ze srodowiska. Bez czyszczenia ta proba, uruchomiona
# jako CZLONEK odlamka `reszta`, odziedziczylaby PRZELOT_ODLAMEK=reszta i zagniezdzony przelot
# odmawialby pracy (EXIT=2) na WLASNYM, prawidlowym zakresie — zlapane lokalnie PRZED CI.
uruchom() { # uruchom <plik_logu> [skrypt] -> wypelnia WY_OUT, WY_ERR, WY_KOD
	local log="$1" skrypt="${2:-$PRZELOT}"
	WY_OUT="$(cd "$KORZEN" && PATH="$ATRAPA" PRZELOT_ODLAMEK= bash "$skrypt" "$log" "$ROBOCZY" 2> "$ROBOCZY/err.txt")"
	WY_KOD=$?
	WY_ERR="$(cat "$ROBOCZY/err.txt")"
}

sprawdz() { # sprawdz "opis" <0=spelniony|1=nie>
	if [ "$2" -eq 0 ]; then
		printf '  OK   %s\n' "$1"
	else
		printf '  BLAD %s\n' "$1"
		BLEDY=$((BLEDY + 1))
	fi
}

echo "=== PROBA KONTROLNA: galaz 'brak zip' w przelocie ==="
echo

# --- 1. CZYSTY KOD ------------------------------------------------------------
uruchom "$ROBOCZY/czysty.log"
CZYSTY_KOD=$WY_KOD
WSZYSTKO="$WY_OUT
$(cat "$ROBOCZY/czysty.log" 2> /dev/null)"

# ⛔ WARUNEK WSTEPNY, NIE ASERCJA (recenzja #272 r14, WAZNA 1). Przelot konczy sie kodem 2,
# gdy nie ma czego mierzyc — najczesciej przy BRUDNYM DRZEWIE, a ta probe uruchamia sie
# recznie, czyli zwykle przy niezacommitowanej pracy. Bez tego warunku obie asercje
# pozytywne padaly (bo `POMINIETO` w ogole nie powstaje) i skrypt konczyl sie kodem 1
# z werdyktem `STRAZNIK SLEPY` — czyli oskarzal przelot o slepote, niczego nie zmierzywszy.
# To dokladnie ta klasa bledu, ktora ten PR naprawia o poziom wyzej.
if [ "$CZYSTY_KOD" -eq 2 ]; then
	echo "⛔ NIE ZMIERZONO: przelot odmowil pracy (EXIT=2) — brak warunkow wstepnych."
	echo "   Najczestsza przyczyna: BRUDNE DRZEWO ROBOCZE. Zacommituj albo odloz zmiany."
	echo "   Komunikat przelotu:"
	echo "$WY_ERR" | head -3 | sed 's/^/     /'
	exit 2
fi

echo "--- czysty kod (EXIT=$CZYSTY_KOD) ---"
echo "$WSZYSTKO" | grep -i 'pomijam\|POMINIETO' | head -2 | sed 's/^/      /'

# ⛔ ASERCJA NA KOD WYJSCIA, NIE TYLKO NA KOD 2 (recenzja #290 WAZNA 1). Do tej rundy ta
# galaz obslugiwala WYLACZNIE `CZYSTY_KOD -eq 2` (brak warunkow wstepnych) — kod 1 (przelot
# NAPRAWDE PADL) przelatywal bez zadnej asercji, wiec podloga stosowana do zlego zakresu
# (klasa naprawiona w proby-kontrolne-przelot.sh:243-249) dawala tu falszywa zielen: przelot
# konczyl sie EXIT=1 na CZYSTYM kodzie, a ta proba tego NIE WIDZIALA. Kod 1 jest teraz FAIL.
if [ "$CZYSTY_KOD" -ne 0 ]; then
	printf '  BLAD przelot na CZYSTYM kodzie (bez zip) konczy sie EXIT=%s, nie EXIT=0\n' "$CZYSTY_KOD"
	BLEDY=$((BLEDY + 1))
else
	printf '  OK   przelot na CZYSTYM kodzie (bez zip) konczy sie EXIT=0\n'
fi

if echo "$WSZYSTKO" | grep -q 'POMINIETO'; then
	sprawdz "proby z WYMAGA_ZIP sa POMIJANE, nie liczone jako porazka" 0
else
	sprawdz "proby z WYMAGA_ZIP sa POMIJANE, nie liczone jako porazka" 1
fi

if echo "$WSZYSTKO" | grep 'POMINIETO' | grep -q 'zip'; then
	sprawdz "komunikat pominiecia NAZYWA brakujace narzedzie (slowo 'zip')" 0
else
	sprawdz "komunikat pominiecia NAZYWA brakujace narzedzie (slowo 'zip')" 1
fi

if echo "$WY_ERR" | grep -q 'command not found'; then
	sprawdz "stderr BEZ 'command not found'" 1
	echo "$WY_ERR" | grep 'command not found' | head -2 | sed 's/^/         /'
else
	sprawdz "stderr BEZ 'command not found'" 0
fi

# --- 2. KONTROLA ODWROTNA -----------------------------------------------------
echo
echo "--- kontrola odwrotna: podkladam odwrotne apostrofy (stan sprzed naprawy) ---"
cp "$PRZELOT" "$MUTANT"
PRZED="$(cksum < "$MUTANT")"
sed -i "s|wymaga 'zip', a ten wykonawca|wymaga \`zip\`, a ten wykonawca|" "$MUTANT"
PO="$(cksum < "$MUTANT")"

if [ "$PRZED" = "$PO" ]; then
	echo "  BLAD mutacja NIE weszla do pliku — wzorzec sie zestarzal"
	NIETRAFIONE=$((NIETRAFIONE + 1))
else
	uruchom "$ROBOCZY/mutant.log" "$MUTANT"
	M_KOD=$WY_KOD
	M_ERR="$WY_ERR"
	M_WSZYSTKO="$WY_OUT
$(cat "$ROBOCZY/mutant.log" 2> /dev/null)"

	# ⛔ NIEOBECNOSC LINII TO NIE DOWOD (recenzja #272 r14, WAZNA 2). Zanim uznam, ze
	# straznik zlapal wade, musze wiedziec, ze mutant W OGOLE DOSZEDL do miejsca, w ktorym
	# ta linia powstaje. Inaczej „brak `zip` w komunikacie" znaczylby to samo co „przelot
	# padl piec linii wczesniej" — a to drugie nie jest zadnym pomiarem.
	if [ "$M_KOD" -eq 2 ]; then
		echo "  ⛔ NIE ZMIERZONO: mutant odmowil pracy (EXIT=2), nie doszedl do pominiec."
		NIETRAFIONE=$((NIETRAFIONE + 1))
	elif ! echo "$M_WSZYSTKO" | grep -q 'POMINIETO'; then
		echo "  ⛔ NIE ZMIERZONO: mutant nie wyprodukowal ANI JEDNEJ linii POMINIETO —"
		echo "     nie doszedl do galezi, ktora mierze. To nie jest 'straznik zlapal'."
		NIETRAFIONE=$((NIETRAFIONE + 1))
	else
		ZLAPANE=0
		if echo "$M_WSZYSTKO" | grep 'POMINIETO' | grep -q 'zip'; then
			echo "  BLAD straznik NIE zlapal: komunikat NADAL nazywa narzedzie"
			BLEDY=$((BLEDY + 1))
		else
			echo "  OK   straznik PADL na: komunikat gubi slowo 'zip' (linia POMINIETO JEST)"
			ZLAPANE=$((ZLAPANE + 1))
		fi
		if echo "$M_ERR" | grep -q 'command not found'; then
			echo "  OK   straznik PADL na: 'command not found' w stderr"
			ZLAPANE=$((ZLAPANE + 1))
		else
			echo "  BLAD straznik NIE zlapal: stderr bez 'command not found'"
			BLEDY=$((BLEDY + 1))
		fi
		[ "$ZLAPANE" -ge 1 ] || BLEDY=$((BLEDY + 1))
	fi
fi
echo "  (oryginal NIETKNIETY — mutant to osobny plik, kasowany przy wyjsciu)"

# --- 3. GALAZ „BRAK DANYCH LOGOWANIA" -----------------------------------------
# ⛔ POWOD ISTNIENIA TEJ SEKCJI (recenzja #280, runda 1). Przelot wczytuje dane logowania
#    i melduje na stderr, gdy ich NIE MA. Ta druga galaz na maszynie roboczej (root, plik
#    czytelny) NIE WYKONUJE SIE ANI RAZU — a na wykonawcy CI (`ciuser`) wykonuje sie ZAWSZE.
#    Pierwsza wersja komunikatu wolala tam `$(whoami)`, polecenia nie ma w atrapie PATH,
#    wiec stderr dostawal `command not found` i TA PROBA padala — dopiero w CI, po pushu.
#    Lokalne „13 z 13 zielone" nic o tej galezi nie mowilo, bo jej nie odwiedzalo.
# ⭐ Dlatego mierzymy ja WPROST, wskazujac ENV_ADMIN na plik, ktorego nie ma.
echo
echo "--- galaz BRAK DANYCH LOGOWANIA (mierzona wprost, nie przez przypadek) ---"
WY_OUT="$(cd "$KORZEN" && ENV_ADMIN_PROBA="$ROBOCZY/nie-ma-takiego-pliku.env" PATH="$ATRAPA" PRZELOT_ODLAMEK= \
	bash "$PRZELOT" "$ROBOCZY/bezlog.log" "$ROBOCZY" 2> "$ROBOCZY/err-bezlog.txt")"
B_KOD=$?
B_ERR="$(cat "$ROBOCZY/err-bezlog.txt")"

if [ "$B_KOD" -eq 2 ] && ! echo "$B_ERR" | grep -q 'NIEWCZYTANE'; then
	echo "  ⛔ NIE ZMIERZONO: przelot odmowil pracy (EXIT=2) przed dojsciem do tej galezi."
	NIETRAFIONE=$((NIETRAFIONE + 1))
else
	if echo "$B_ERR" | grep -q 'NIEWCZYTANE'; then
		sprawdz "brak danych logowania ZOSTAWIA SLAD na stderr" 0
	else
		sprawdz "brak danych logowania ZOSTAWIA SLAD na stderr" 1
	fi
	if echo "$B_ERR" | grep -q 'command not found'; then
		sprawdz "komunikat o braku logowania NIE wola polecen spoza atrapy PATH" 1
		echo "$B_ERR" | grep 'command not found' | head -2 | sed 's/^/         /'
	else
		sprawdz "komunikat o braku logowania NIE wola polecen spoza atrapy PATH" 0
	fi
fi

# ⛔ ASERCJA BEZ MUTANTA JEST MARTWA. Podkladam stan sprzed naprawy — wywolanie `whoami`
#    w tej samej galezi — i zadam, zeby asercja wyzej PADLA. Inaczej „OK" nic nie znaczy.
# ⛔ MUTANT W TYM SAMYM KORZENIU CO ORYGINAL (regula 140 linii wyzej, recenzja #280 r3 D5):
#    przelot liczy KORZEN ze sciezki wlasnego pliku, wiec kopia w /tmp mierzylaby INNE repo.
MUT_LOG="$KORZEN/narzedzia-qa/.przelot-whoami.tmp.sh"
cp "$PRZELOT" "$MUT_LOG"
M_PRZED="$(cksum < "$MUT_LOG")"
sed -i 's|"${USER:-UID $EUID}"|"$(whoami)"|' "$MUT_LOG"
M_PO="$(cksum < "$MUT_LOG")"
if [ "$M_PRZED" = "$M_PO" ]; then
	echo "  BLAD mutacja NIE weszla do pliku (ZGNILA KOTWICA sed): whoami w galezi bez logowania"
	NIETRAFIONE=$((NIETRAFIONE + 1))
else
	WY_OUT="$(cd "$KORZEN" && ENV_ADMIN_PROBA="$ROBOCZY/nie-ma-takiego-pliku.env" PATH="$ATRAPA" PRZELOT_ODLAMEK= \
		bash "$MUT_LOG" "$ROBOCZY/bezlog-mut.log" "$ROBOCZY" 2> "$ROBOCZY/err-bezlog-mut.txt")"
	MB_ERR="$(cat "$ROBOCZY/err-bezlog-mut.txt")"
	if echo "$MB_ERR" | grep -q 'command not found'; then
		echo "  OK   straznik PADL na: whoami spoza atrapy PATH (stan sprzed naprawy)"
	else
		# ⛔ MARTWA ASERCJA to wada PRZYRZADU, nie slepota straznika — idzie do NIETRAFIONE
		#    (EXIT=2), zgodnie z konwencja, ktora ten PR ustanowil w 17 probach. Do rundy 3
		#    szla do BLEDY i konczyla zdaniem „STRAZNIK SLEPY" o czyms, czego nie zmierzono.
		echo "  BLAD straznik NIE zlapal: whoami spoza atrapy PATH — asercja jest MARTWA"
		NIETRAFIONE=$((NIETRAFIONE + 1))
	fi
fi

echo
if [ "$NIETRAFIONE" -gt 0 ]; then
	echo "⛔ NIE ZMIERZONO ($NIETRAFIONE) — wzorzec sie zestarzal albo mutant nie doszedl do celu."
	echo "   To wada TEJ PROBY, nie przelotu. Popraw i uruchom ponownie."
	exit 2
fi
if [ "$BLEDY" -eq 0 ]; then
	echo "STRAZNIK WIDZI PODLOZONY BLAD (galaz 'brak zip' zmierzona na prawdziwym skrypcie)"
	exit 0
fi
echo "STRAZNIK SLEPY — $BLEDY asercji nie zadzialalo"
exit 1
