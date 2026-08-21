#!/bin/bash
# stos-proba-kontrolna.sh — dowód, że okno na stos jest WĄSKIE i tylko do odczytu.
#
# stos.sh daje recenzentowi dostęp do żywego WordPressa. Taki dostęp jest wart
# tyle, ile wart jest zamek: jeśli da się przez niego przemycić własne polecenie
# albo cokolwiek zapisać, to nie jest pomocnik, tylko dziura. Ta próba podkłada
# skryptowi po kolei każdą próbę przemycenia i sprawdza, że KAŻDA kończy się
# odmową z kodem 2 — a osobno, że operacje, które mają działać, naprawdę
# odpowiadają z żywego stosu.
#
# Kody wyjścia: 0 = wszystkie odmowy odmówione, wszystkie odczyty odczytane
#               1 = choć raz zachował się nie tak, jak ma
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STOS="$KORZEN/narzedzia-qa/stos.sh"
BLEDY=0

odmowa() {
	local opis="$1"; shift
	local wyjscie kod=0
	wyjscie="$(bash "$STOS" "$@" 2>&1)" || kod=$?
	if [ "$kod" -ne 2 ]; then
		printf '  BLAD %-56s (EXIT=%s, oczekiwano 2)\n' "$opis" "$kod"
		printf '%s\n' "$wyjscie" | head -4 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	printf '  OK   %-56s (EXIT=2)\n' "$opis"
}

odczyt() {
	local opis="$1" fragment="$2"; shift 2
	local wyjscie kod=0
	wyjscie="$(bash "$STOS" "$@" 2>&1)" || kod=$?
	if [ "$kod" -ne 0 ]; then
		printf '  BLAD %-56s (EXIT=%s, oczekiwano 0)\n' "$opis" "$kod"
		printf '%s\n' "$wyjscie" | head -4 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	if ! printf '%s' "$wyjscie" | grep -qF "$fragment"; then
		printf '  BLAD %-56s (kod OK, ale brak: %s)\n' "$opis" "$fragment"
		BLEDY=$(( BLEDY + 1 ))
		return
	fi
	printf '  OK   %-56s (EXIT=0, widzi: %s)\n' "$opis" "$fragment"
}

echo "=== PRZEMYCENIE WLASNEGO POLECENIA — MA BYC ODMOWA ==="
odmowa "nieznana operacja"                       uruchom "id"
odmowa "operacja, ktorej nie ma na liscie"        exec
odmowa "srednik w sciezce"                        strona "/x;whoami"
odmowa "podstawienie polecenia w sciezce"         strona '/x$(id)'
odmowa "wyjscie z witryny przez .."               strona "/../../etc/passwd"
odmowa "sciezka bez wiodacego ukosnika"           strona "etc/passwd"
odmowa "spacja i flaga w nazwie opcji"            opcja "x --path=/"
odmowa "srednik w nazwie opcji"                   opcja "x;id"
odmowa "trzeci argument (przemyt flagi)"          strona "/" "--allow-root"
odmowa "brak wymaganej wartosci"                  strona

echo
echo "=== TABELA SPOZA BIALEJ LISTY — MA BYC ODMOWA ==="
odmowa "tabela rdzenia spoza trojki"              wiersze wp_postmeta
odmowa "tabela z innej bazy (kropka)"             wiersze "mysql.user"
odmowa "wstrzykniecie SQL w nazwie tabeli"        wiersze "wp_segm_reguly; DROP TABLE x"

echo
echo "=== ZERO OPERACJI ZAPISU — sprawdzane w tresci skryptu ==="
# Nie ufam samemu sobie na słowo: jeśli ktoś kiedyś dopisze tu operację
# zapisu, ta kontrola ma zapalić czerwone, zanim trafi to do recenzenta.
ZAKAZANE='wp_odczyt[a-z_]* (option (update|add|delete|set)|plugin (install|activate|deactivate|delete|update)|theme |user (create|update|delete)|post (create|update|delete)|db (drop|reset|import|export)|eval|eval-file|shell|core (install|update)|site |cron event run)'
if grep -nEq "$ZAKAZANE" "$STOS"; then
	echo "  BLAD w stos.sh jest operacja ZAPISU:"
	grep -nE "$ZAKAZANE" "$STOS" | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
else
	echo "  OK   zadne wywolanie wp nie jest operacja zapisu"
fi
# Jedyne wywołania dockera wolno mieć trzem funkcjom. Każde inne to obejście
# całej białej listy i musi zostać zauważone przy czytaniu diffu.
ILE_DOCKER="$(grep -c 'docker ' "$STOS")"
if [ "$ILE_DOCKER" -gt 6 ]; then
	printf '  BLAD wywolan dockera w stos.sh jest %s — przybylo obejscie?\n' "$ILE_DOCKER"
	BLEDY=$(( BLEDY + 1 ))
else
	printf '  OK   wywolania dockera policzone: %s (limit 6)\n' "$ILE_DOCKER"
fi

# Tekst pomocy to JEDYNE wyjscie funkcji pomoc(). Heredok POMOC nie interpretuje
# ucieczek, wiec wpisane w nim `\n` wypisuje sie jako dwa znaki zamiast podzialu
# linii — recenzja PR #105 zlapala to golym okiem. Ta kontrola lapie nawrot.
if bash "$STOS" pomoc | grep -q '\\n'; then
	echo "  BLAD tekst pomocy ma literalne \\n zamiast podzialu linii:"
	bash "$STOS" pomoc | grep -n '\\n' | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
else
	echo "  OK   tekst pomocy bez literalnych sekwencji ucieczki"
fi

echo
echo "=== BRAK NARZEDZIA TO KOD 2, NIE CICHE ZIELONE ==="
kod=0
# Bezwzgledna sciezka do powloki: przy wyzerowanym PATH samo `bash` tez
# przestaje sie znajdowac i proba mierzylaby wtedy wlasny blad (kod 127),
# a nie zachowanie skryptu.
wyjscie="$(PATH=/nie/ma/takiej/sciezki /bin/bash "$STOS" wtyczki 2>&1)" || kod=$?
if [ "$kod" -eq 2 ]; then
	printf '  OK   %-56s (EXIT=2)\n' "bez dockera w PATH mowi wprost i konczy dwojka"
else
	printf '  BLAD %-56s (EXIT=%s, oczekiwano 2)\n' "bez dockera w PATH mowi wprost i konczy dwojka" "$kod"
	BLEDY=$(( BLEDY + 1 ))
fi

echo
echo "=== ZYWY STOS — operacje maja NAPRAWDE odpowiadac ==="
odczyt "stan stosu"                "HTTP 200"          stan
odczyt "strona glowna witryny"     "HTTP 200"          strona "/"
odczyt "naglowki odpowiedzi"       "HTTP/"             naglowki "/"
odczyt "lista wtyczek"             "segregator-maila"  wtyczki
odczyt "tabele wtyczek w bazie"    "wp_segm_reguly"    tabele
odczyt "liczba wierszy w tabeli"   "wierszy:"          wiersze wp_segm_reguly
odczyt "opcja ustawien segregatora" "ai_limit_dzienny" opcja segm_ustawienia
odczyt "licznik maili z lapacza"   "wiadomości:"       maile
odczyt "ostatni mail"              "Subject"           ostatni-mail
odczyt "wersja rdzenia"            "7."                wersja-wp

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "OKNO JEST WASKIE I TYLKO DO ODCZYTU — kazda proba przemytu odrzucona, kazdy odczyt odpowiedzial"
	exit 0
fi
echo "STOS.SH ZACHOWAL SIE ZLE $BLEDY RAZ(Y) — nie oddawaj go recenzentowi w tym stanie"
exit 1
