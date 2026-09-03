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
# ⛔ LICZYMY WYWOŁANIA, NIE WZMIANKI. Do 2.09 stało tu `grep -c 'docker '`, więc do
# licznika wchodziły KOMENTARZE i treści komunikatów mówiące o `docker ps` — dopisanie
# akapitu wyjaśniającego podbijało liczbę i strażnik oskarżał opis zamiast kodu.
# Odrzucamy linie komentarza oraz te, w których `docker` stoi wyłącznie w cudzysłowie
# odwrotnym (cytat w tekście). To ta sama klasa co bramka „liczba tabel” w #274.
ILE_DOCKER="$(grep -vE '^[[:space:]]*#' "$STOS" | grep -vE '\\`docker' | grep -c 'docker ')"
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
# stos.sh 1.3.1 (recenzja #182 r2 D5): okno musi widzieć tabele SKLEPU — bez tego strażnik przepuszczałby
# powrót do białej listy bez wp_sklepag_ i audytor K7 nie zajrzałby do żadnego zamówienia.
odczyt "tabele SKLEPU w bazie"     "wp_sklepag_zamowienia" tabele
odczyt "wiersze tabeli SKLEPU"     "wierszy:"          wiersze wp_sklepag_produkty
odczyt "opcja ustawien segregatora" "ai_limit_dzienny" opcja segm_ustawienia
# 1.3.0: opcja z sekretem w nazwie ma wyjść ZAMASKOWANA — i NIGDY z wartością (poniżej druga kontrola)
odczyt "opcja-sekret zamaskowana"   "[SEKRET ZAMASKOWANY]" opcja chatl_klucz_api
WYJ_SEKRET="$(bash "$STOS" opcja chatl_klucz_api 2>/dev/null)"
if printf '%s' "$WYJ_SEKRET" | grep -qE 'sk-ant-[A-Za-z0-9_-]{10,}'; then
	printf '  BLAD %-56s (pelna wartosc klucza na stdout)\n' "opcja-sekret nie wycieka"; BLEDY=$(( BLEDY + 1 ))
else
	printf '  OK   %-56s\n' "opcja-sekret: w wyjsciu brak wzorca sk-ant- (weziej niz nie-wycieka)"
fi
# 1.3.0 runda 5: kalibracja kontroli wielkich liter. Opcja MUSI ISTNIEC (baza porownuje nazwy
# bez wielkosci liter, wiec CHATL_KLUCZ_API zwraca zywa wartosc) — na ZEPSUTYM filtrze nazwa
# wielkimi poszlaby jawna galezia i wypisala pelny klucz; na dobrym dostajemy maske. Poprzednia
# wersja pytala o opcje nieistniejaca i przechodzila na zielono takze na zepsutym filtrze.
WYJ_DUZE="$(bash "$STOS" opcja CHATL_KLUCZ_API 2>&1)"; KOD_DUZE=$?
if [ "$KOD_DUZE" -eq 0 ] && printf '%s' "$WYJ_DUZE" | grep -q 'SEKRET ZAMASKOWANY' && ! printf '%s' "$WYJ_DUZE" | grep -qE 'sk-ant-[A-Za-z0-9_-]{10,}'; then
	printf '  OK   %-56s\n' "nazwa WIELKIMI literami: zywa opcja zamaskowana, zero sk-ant-"
else
	printf '  BLAD %-56s (EXIT=%s)\n' "nazwa WIELKIMI literami ominela maskowanie zywej opcji" "$KOD_DUZE"; BLEDY=$(( BLEDY + 1 ))
fi
# 1.3.0 runda 4: regresja podpowloki — martwy stos ma dawac EXIT=2 i ZERO [SEKRET ZAMASKOWANY]
# (bez tej kontroli exit 2 z brak_stosu ginal w $(...) i skrypt wypisywal dlugosc KOMUNIKATU jako klucza).
if docker ps --filter name='^probny_wp$' --filter status=running -q | grep -q .; then
	printf '  POMINIETE %-51s\n' "kontrola martwego stosu (probny_wp AKURAT chodzi)"
else
	WYJ_MARTWY="$(bash "$STOS" --stos probny opcja chatl_klucz_api 2>&1)"; KOD_MARTWY=$?
	if [ "$KOD_MARTWY" = "2" ] && ! printf '%s' "$WYJ_MARTWY" | grep -q 'SEKRET ZAMASKOWANY'; then
		printf '  OK   %-56s\n' "martwy stos: EXIT=2 i zadnego udawanego maskowania"
	else
		printf '  BLAD %-56s (EXIT=%s)\n' "martwy stos: exit z podpowloki zginal albo udaje maske" "$KOD_MARTWY"; BLEDY=$(( BLEDY + 1 ))
	fi
fi
odczyt "licznik maili z lapacza"   "wiadomości:"       maile
odczyt "ostatni mail"              "Subject"           ostatni-mail
odczyt "wersja rdzenia"            "7."                wersja-wp

# ------------------------------------------------------------------------
# ⛔ „NIE WIEM, CZY STOS STOI” MUSI WYGLADAC INACZEJ NIZ „STOS NIE STOI”.
# Do 2.09 `kontener_zyje()` mialo `docker ps … 2> /dev/null`, wiec brak dostepu do
# `docker.sock` (tak chodzi wykonawca CI po naprawie B-3) dawal DOKLADNIE ten sam
# komunikat co zatrzymany kontener — i twierdzil „sprawdzone przez docker ps”.
# Skutek zmierzony: przez cztery rundy recenzji #268 obie strony pisaly „stos nie stoi”,
# choc kontener chodzil, wiec NIKT nie uruchomil straznika.
# Podkladamy atrape `docker` na PATH: raz padajaca, raz odpowiadajaca pustka.
# ------------------------------------------------------------------------
echo
echo "--- rozroznianie: brak dostepu do Dockera vs zatrzymany kontener ---"
ATRAPY="$(mktemp -d -t stos-atrapa-XXXXXX)"

printf '#!/bin/sh\necho "permission denied while trying to connect to the docker API at unix:///var/run/docker.sock" >&2\nexit 1\n' > "$ATRAPY/docker"
chmod +x "$ATRAPY/docker"
WY_BRAK="$( PATH="$ATRAPY:$PATH" bash "$STOS" stan 2>&1 )"

printf '#!/bin/sh\nexit 0\n' > "$ATRAPY/docker"
chmod +x "$ATRAPY/docker"
WY_STOP="$( PATH="$ATRAPY:$PATH" bash "$STOS" stan 2>&1 )"

case "$WY_BRAK" in
	*"NIE WIEM"*) printf '  OK   %-56s\n' "brak dostepu do Dockera melduje NIE WIEM" ;;
	*)            printf '  BLAD %-56s\n' "brak dostepu do Dockera NIE melduje NIE WIEM"
	              printf '%s\n' "$WY_BRAK" | head -3 | sed 's/^/         /'
	              BLEDY=$(( BLEDY + 1 )) ;;
esac
case "$WY_STOP" in
	*"nie jest uruchomiony"*) printf '  OK   %-56s\n' "zatrzymany kontener melduje NIE JEST URUCHOMIONY" ;;
	*)                        printf '  BLAD %-56s\n' "zatrzymany kontener meldowany inaczej niz oczekiwano"
	                          printf '%s\n' "$WY_STOP" | head -3 | sed 's/^/         /'
	                          BLEDY=$(( BLEDY + 1 )) ;;
esac
# ⛔ KONTROLA, KTORA LAPIE WSPOLNY KOMUNIKAT: gdyby oba przypadki mowily to samo,
# powyzsze dwa `case` moglyby OBA przejsc na jednym, wspolnym zdaniu.
if [ "$WY_BRAK" = "$WY_STOP" ]; then
	printf '  BLAD %-56s\n' "oba przypadki daja IDENTYCZNE wyjscie — nic nie odrozniamy"
	BLEDY=$(( BLEDY + 1 ))
else
	printf '  OK   %-56s\n' "oba przypadki daja ROZNE wyjscie"
fi

echo
echo "--- rozroznianie: brak POLECENIA docker vs martwy WordPress (recenzja #268 r12) ---"
# ⛔ Dwie wady z rundy 12, obie tej samej klasy: skrypt ORZEKAL przyczyne, ktorej nie zmierzyl.
#    (1) `wymagaj_narzedzia` szla galezia "STOS NIE ODPOWIADA" i KAZALA recenzentowi napisac
#        w recenzji, ze stos nie odpowiada — na maszynie, na ktorej dockera nie ma WCALE.
#    (2) `$kod` z curla przy braku polaczenia mial wartosc `000000` (curl wypisuje `000` I konczy
#        sie niezerowo, wiec `|| echo 000` doklejalo drugie), wiec warunek `= "000"` NIE trafial
#        i skrypt szedl dalej, meldujac np. "wersja rdzenia → 7.1" przy martwym WordPressie.
# Kazdy przypadek psuje DOKLADNIE JEDEN warunek: pierwszy zabiera polecenie, drugi adres uslugi.

# (1) PATH bez `docker` — same podstawowe narzedzia, zeby skrypt w ogole ruszyl
GOLY="$(mktemp -d -t stos-goly-XXXXXX)"
trap 'rm -rf "$ATRAPY" "$GOLY" "$KOPIA"' EXIT INT TERM
for _b in bash sh grep sed awk curl printf cut tr head tail sort uniq wc env test cat; do
	_p="$(command -v "$_b" 2> /dev/null)" && ln -sf "$_p" "$GOLY/$_b"
done
# ⛔ Przypadek psuje DOKLADNIE JEDEN warunek, wiec adres uslugi MUSI byc zywy — inaczej
#    asercja mierzy KONIUNKCJE (brak polecenia ORAZ dzialajacy WordPress) i przy zgaszonym
#    stosie oskarzalaby `stos.sh` o wade, ktorej nie ma. Podkladamy wlasny, zawsze zywy adres.
ZYWY="$(mktemp -t stos-zywy-XXXXXX.sh)"
PORT_ZYWY=$(python3 -c "import socket;s=socket.socket();s.bind(('127.0.0.1',0));print(s.getsockname()[1]);s.close()" 2> /dev/null || echo 8890)
( python3 -m http.server "$PORT_ZYWY" --bind 127.0.0.1 > /dev/null 2>&1 & echo $! > "$GOLY/.pid" ) 2> /dev/null
sleep 1
sed "s#ADRES_WP=http://localhost:8890#ADRES_WP=http://127.0.0.1:$PORT_ZYWY#" "$STOS" > "$ZYWY"
WY_GOLY="$( PATH="$GOLY" bash "$ZYWY" stan 2>&1 )"
[ -f "$GOLY/.pid" ] && kill "$(cat "$GOLY/.pid")" 2> /dev/null
rm -f "$ZYWY"
case "$WY_GOLY" in
	*"nie odpowiada"*) printf '  BLAD %-56s\n' "przypadek (1): adres uslugi NIE ZYJE — pomiar nic nie znaczy"
	              BLEDY=$(( BLEDY + 1 )) ;;
	*"NIE WIEM"*) printf '  OK   %-56s\n' "brak POLECENIA docker melduje NIE WIEM" ;;
	*)            printf '  BLAD %-56s\n' "brak POLECENIA docker NIE melduje NIE WIEM"
	              printf '%s\n' "$WY_GOLY" | head -3 | sed 's/^/         /'
	              BLEDY=$(( BLEDY + 1 )) ;;
esac

# (2) martwy adres uslugi: kopia skryptu z portem, na ktorym nikt nie sluchа
KOPIA="$(mktemp -t stos-martwy-XXXXXX.sh)"
sed 's#ADRES_WP=http://localhost:8890#ADRES_WP=http://127.0.0.1:59999#' "$STOS" > "$KOPIA"
WY_MARTWY="$( bash "$KOPIA" stan 2>&1 )"
case "$WY_MARTWY" in
	*"WordPress pod http://127.0.0.1:59999 nie odpowiada"*)
		printf '  OK   %-56s\n' "martwy WordPress meldowany JAKO WordPress" ;;
	*)
		printf '  BLAD %-56s\n' "martwy WordPress przemilczany albo nazwany inaczej"
		printf '%s\n' "$WY_MARTWY" | head -4 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 )) ;;
esac
# ⛔ KONTROLA, ZE PRZYPADEK (2) NIE PRZECHODZI DALEJ: przed naprawa skrypt po `HTTP 000000`
#    szedl do konca i wypisywal wersje rdzenia, czyli twierdzil cos o dzialajacym stosie.
case "$WY_MARTWY" in
	*"wersja rdzenia"*)
		printf '  BLAD %-56s\n' "po martwym WordPressie skrypt SZEDL DALEJ (wersja rdzenia)"
		BLEDY=$(( BLEDY + 1 )) ;;
	*) printf '  OK   %-56s\n' "po martwym WordPressie skrypt PRZERYWA" ;;
esac

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "OKNO JEST WASKIE I TYLKO DO ODCZYTU — kazda proba przemytu odrzucona, kazdy odczyt odpowiedzial"
	exit 0
fi
echo "STOS.SH ZACHOWAL SIE ZLE $BLEDY RAZ(Y) — nie oddawaj go recenzentowi w tym stanie"
exit 1
