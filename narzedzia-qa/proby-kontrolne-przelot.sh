#!/bin/bash
# proby-kontrolne-przelot.sh — URUCHAMIA wszystkie próby kontrolne i SAM pisze log.
#
# PO CO (recenzja PR #198, runda 3, uwaga 3): kontrola „6 próby kontrolne" w k7-koniec.sh
# nie uruchamiała żadnej próby — grepowała plik pisany RĘCZNIE. Skutek zmierzony przez
# recenzentkę: log mówił `bramki-proba-kontrolna.sh EXIT=1`, a uruchomienie tej samej próby
# na HEAD dawało EXIT=0. Artefakt dowodowy rozjechał się z rzeczywistością i nikt tego nie
# widział, bo nikt go nie odtwarzał. Ten skrypt odbiera człowiekowi pióro: log jest WYNIKIEM
# przebiegu, nie notatką o przebiegu.
#
# DATA WAŻNOŚCI. Nagłówek niesie COMMIT, na którym przelot się odbył. k7-koniec.sh porównuje
# go z HEAD i odmawia zaliczenia dowodu z innego stanu kodu — dowód bez daty ważności to
# dowód o nieznanym wieku.
#
# Wywołanie: bash narzedzia-qa/proby-kontrolne-przelot.sh [plik-logu] [katalog]
#            (domyślnie: audyt/sklep/PROBY-KONTROLNE.log, katalog `.` = całe repo)
#
# ⛔ DRUGI ARGUMENT — ZAKRES, dodany 31.08. Powód zmierzony: przelot po CAŁYM repo bierze
# 38 prób, z czego 11 to testy PRODUKTU wymagające żywego stosu WordPressa. W CI stosu
# nie ma i manifest bramek mówi o tym wprost („w CI nie ma WP+Stripe"), więc wołanie
# całości w kroku per-PR kończyłoby się jedenastoma porażkami, które nie są wadami kodu.
# Bramka wydania (k7-koniec.sh) woła NADAL bez zakresu i to jest właściwe — tam stos stoi.
# Zakres zawęża TYLKO to, co przelatujemy; nie zmienia progu ani sposobu liczenia.
# Kody wyjścia: 0 = przelot wykonany i WSZYSTKIE próby dały zero ·
#               1 = przelot wykonany, ale któraś próba nie przeszła (log i tak zapisany) ·
#               2 = nie ma czego uruchamiać / drzewo brudne.
set -u

# ⛔ Bez danych logowania do panelu proby `nonce` i `blad-klucza` padaja na BRAKU LOGOWANIA,
# a raportuja to jako wade produktu. Wzorzec wziety z `narzedzia-qa/linia-bazowa.sh`.
# Bez galezi else pominiecie jest CICHE: na wykonawcy CI (ciuser od 31.08, naprawa B-3)
# plik jest NIECZYTELNY, warunek zawsze falszywy, a proby nonce i blad-klucza znowu
# raportuja BRAK LOGOWANIA jako wade produktu. Pominiecie MUSI zostawiac slad.
# ⛔ ZADNEGO ZEWNETRZNEGO POLECENIA W TEJ GALEZI. Proba `przelot-brak-zip-proba-kontrolna.sh`
#    uruchamia ten skrypt z OKROJONYM PATH (atrapa bez `zip`) i asertuje, ze stderr NIE zawiera
#    `command not found`. Pierwsza wersja wolala tu `$(whoami)` — polecenia nie ma w atrapie,
#    wiec CI padalo, a lokalnie u roota galaz `else` nie wykonywala sie ANI RAZU. Uzywamy
#    wylacznie tego, co daje sama powloka: $USER i $EUID.
# ⛔ SCIEZKA JEST LITERALEM (recenzja #280 r3 W3). Poprzednia runda czytala ja ze SRODOWISKA
#    jako `${ENV_ADMIN:-...}` — a wartosc jest potem SOURCOWANA, wiec `ENV_ADMIN=/dev/null`
#    przelaczalo wykonanie na galaz „wczytane" i CISZYLO ostrzezenie dodane w W5, nie ruszajac
#    jednego bajtu w repozytorium. Ta sama pulapka co sufit czytany ze srodowiska (bramki.sh:490).
# Proba kontrolna potrzebuje wejscia w galaz `else`, a NIE prawa podstawienia pliku do
# zesourcowania — dlatego osobna zmienna, honorowana WYLACZNIE gdy wskazuje sciezke, ktorej
# NIE DA SIE odczytac. Plik czytelny podany tedy jest ignorowany.
ENV_ADMIN=/root/.claude/sklep-admin-roboczy.env
if [ -n "${ENV_ADMIN_PROBA:-}" ] && [ ! -r "${ENV_ADMIN_PROBA}" ]; then
	ENV_ADMIN="$ENV_ADMIN_PROBA"
fi
if [ -r "$ENV_ADMIN" ]; then
	set -a; . "$ENV_ADMIN"; set +a
	printf 'DANE LOGOWANIA: wczytane z %s\n' "$ENV_ADMIN" >&2
else
	printf 'UWAGA DANE LOGOWANIA NIEWCZYTANE (%s nieczytelny dla %s) — proby wymagajace\n' \
		"$ENV_ADMIN" "${USER:-UID $EUID}" >&2
	printf '      panelu zaraportuja BRAK LOGOWANIA; to wada SRODOWISKA, nie produktu.\n' >&2
fi

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
LOG="${1:-audyt/sklep/PROBY-KONTROLNE.log}"
ZNACZNIK='WYGENEROWANE-PRZEZ: narzedzia-qa/proby-kontrolne-przelot.sh'

# Próby, których NIE uruchamiamy maszynowo — z powodem. Cicha redukcja zakresu czyta się
# jak „sprawdzono wszystko", dlatego pominięcia lądują w logu, nie w komentarzu w kodzie.
declare -A POMIJANE=(
	[dowod-zachowania-segregator-proba-kontrolna.sh]="deinstaluje wtyczkę ze stosu — do przelotu ręcznego przed wydaniem"
)

# ⛔ PRÓBY WYMAGAJĄCE DOCKERA (1.09; od 2.09 jest ich PIĘĆ). Te próby stawiają stos próbny albo wołają
# PHP w kontenerze. Wykonawca CI od 31.08 chodzi jako `ciuser` (naprawa B-3) i NIE
# widzi `docker.sock` — zmierzone: `permission denied while trying to connect to
# the docker API at unix:///var/run/docker.sock`. Dostępu dać NIE MOŻNA: grupa
# `docker` jest równoważnikiem roota i unieważniłaby całą tamtą naprawę.
#
# ⭐ Dlaczego nie na sztywno w POMIJANE: bo lokalnie (i na każdym koncie z Dockerem)
# te próby DZIAŁAJĄ i mają biec. Pomijamy je wyłącznie tam, gdzie Dockera faktycznie
# nie ma — inaczej sami sobie zabralibyśmy pokrycie na maszynach, gdzie jest.
#
# ⚠️ Dlaczego to nie jest zamiecenie pod dywan: pominięcie ląduje w logu z powodem,
# jest policzone, a niżej stoi SUFIT — lista pominięć, która rośnie, wywala przelot.
# ⛔ DŁUG: te próby MUSZĄ przejść ręcznie przed wydaniem. CI ich nie sprawdzi.
WYMAGA_DOCKERA=(
	dowod-aktualizacji-proba-kontrolna.sh
	dowod-z-paczki-proba-kontrolna.sh
	stos-proba-kontrolna.sh
	test-php-proba-kontrolna.sh
	golden-migawka-proba-kontrolna.sh
	strony-prawne-proba-kontrolna.sh
	# 5.09: doszla ze scaleniem #274 (A6-F1). Stawia WLASNY stos probny, wiec bez
	# Dockera nie ma czym mierzyc. Nie dalo sie jej dopisac wczesniej: w #272 plik
	# jeszcze nie istnial, a w #274 ten plik byl zmieniany przez #272 (konflikt).
	schemat-wersja-po-sprawdzeniu-proba-kontrolna.sh
)
# 5.09 (D41): wykonawca GitHuba Dockera MA, ale stosu WP (kontenera bazy) nie ma — workflow
# deklaruje to jawnie przez `BEZ_STOSU_WP=1`. Ta sama sciezka pominiecia co bez Dockera:
# z powodem w logu, policzona, pod sufitem. Bez deklaracji te proby ruszalyby i padaly na
# braku bazy, udajac wade produktu.
if ! docker ps >/dev/null 2>&1; then
	for N in "${WYMAGA_DOCKERA[@]}"; do
		POMIJANE[$N]="wymaga Dockera, a to konto go nie widzi (wykonawca CI bez roota, B-3) — przelot ręczny przed wydaniem"
	done
elif [ "${BEZ_STOSU_WP:-}" = 1 ]; then
	for N in "${WYMAGA_DOCKERA[@]}"; do
		POMIJANE[$N]="wymaga stosu WP, a ten wykonawca go nie ma (BEZ_STOSU_WP=1, wykonawca GitHuba, D41) — przelot ręczny przed wydaniem"
	done
fi

# ⛔ PRÓBY WYMAGAJĄCE `zip` (3.09). Powód zmierzony, nie przewidziany z góry: do 5.09 mieliśmy
# DWA własne runnery (`hetzner-gosia`, `oracle-micro`) i one NIE BYŁY IDENTYCZNE (od D41 biegnie
# jeden obraz GitHuba, który `zip` ma — warunek zostaje, bo lokalne maszyny dalej się różnią):
#   zip     hetzner TAK · micro NIE      <- to
#   docker  hetzner TAK · micro NIE      <- to było przewidziane wyżej
#   unzip, jq, git, curl, php, composer, node — na obu tak samo
# Skutek, zanim to dopisano: ten sam commit #272 miał bramki ZIELONE (11 min, Hetzner)
# i CZERWONE (50 min, Oracle) — bo `k7-koniec-proba-kontrolna.sh:118` i
# `paczka-zgodnosc-proba-kontrolna.sh:293` odmawiają wtedy pomiaru kodem 2, a przelot
# liczył każdy niezerowy kod jako PORAŻKĘ. CI było więc NIEDETERMINISTYCZNE: wynik zależał
# od tego, który wykonawca złapał zadanie.
#
# ⭐ Ta sama zasada co przy Dockerze: nie na sztywno, tylko tam, gdzie `zip` faktycznie
# nie istnieje; pominięcie ląduje w logu z powodem, jest policzone i podlega SUFITOWI.
# ⛔ DŁUG: te dwie próby MUSZĄ przejść ręcznie przed wydaniem — na maszynie, która ma `zip`.
WYMAGA_ZIP=(
	k7-koniec-proba-kontrolna.sh
	paczka-zgodnosc-proba-kontrolna.sh
	# 4.09 (recenzja #272 r13, WAZNA 3): te dwie tez koncza sie kodem 2 bez `zip`,
	# a byly WYLACZNIE w WYMAGA_DOCKERA — czyli na wykonawcy Z Dockerem i BEZ zip
	# ruszaly i lądowaly w liczniku PORAZEK. Ta sama niedeterministyczna czerwien,
	# ktora ten commit naprawia, tylko z innego wiersza logu. Naprawiam KLASE.
	dowod-z-paczki-proba-kontrolna.sh
	dowod-aktualizacji-proba-kontrolna.sh
)
if ! command -v zip > /dev/null 2>&1; then
	for N in "${WYMAGA_ZIP[@]}"; do
		# ⛔ SCALAMY POWODY, NIE NADPISUJEMY (recenzja #272 r14, DROBNA 6). Dwie nazwy
		# stoja w OBU tablicach, a POMIJANE to mapa — ta petla biegnie DRUGA, wiec proste
		# przypisanie kasowalo powod dockerowy i log mowil o jednym braku z dwoch.
		if [ -n "${POMIJANE[$N]:-}" ]; then
			POMIJANE[$N]="${POMIJANE[$N]} ORAZ wymaga 'zip', ktorego ten wykonawca tez nie ma"
		else
			POMIJANE[$N]="wymaga 'zip', a ten wykonawca go nie ma (runnery nie są identyczne) — przelot ręczny przed wydaniem"
		fi
	done
fi

# ⛔ SUFIT NIEZMIERZONYCH. Kod 2 („nie mam warunkow wstepnych") NIE jest porazka —
# ale gdyby rosl bez sufitu, zestaw kurczylby sie po cichu, a przelot dalej swiecilby
# zielono. To ta sama klasa co sufit ponizej i ten sam mechanizm, co `MAX_NIEZMIERZONYCH`
# w `bramki.sh` (klasa 128) — siostrzane narzedzia maja mowic jednym glosem.
MAX_NIEZMIERZONYCH=0   # 4.09: TWARDE ZERO. Podniesienie = swiadoma decyzja w commicie,
                       # z nazwa proby i powodem, dla ktorego nie da sie jej zmierzyc.

# ⛔ SUFIT POMINIĘĆ. Bez niego lista pominięć rośnie po cichu i przelot świeci
# zielono, sprawdzając coraz mniej — dokładnie ta klasa błędu, dla której ten
# skrypt w ogóle powstał. Podniesienie sufitu ma być świadomą decyzją w commicie.
# ⛔ SUFIT POMINIEC JEST PER ZAKRES — jedna liczba dla obu sciezek NIE dziala.
#    Recenzja #272 r17 WAZNA 2: sufit 10 (maksimum z obu) w CI nie zapali sie NIGDY,
#    bo CI wola przelot Z ZAKRESEM `narzedzia-qa`, gdzie wiecej niz 7 pominiec jest
#    NIEMOZLIWE. Przy jednej liczbie mozna dopisac TRZY nazwy do POMIJANE i przejdzie
#    bez sladu — dokladnie ta klasa, dla ktorej ten sufit powstal.
MAX_POMINIETYCH_ZAKRES=7   # 5.09: POLICZONE na scalonej tresci (#272 + #274 + #278), nie przepisane.
                    # ⛔ Rachunek na OBU zakresach, bo sufit obowiazuje oba, a CI liczy inaczej
                    #    niz wydanie. Scenariusz, ktorego sufit dotyczy: wykonawca BEZ Dockera
                    #    I BEZ `zip` (na maszynie z obydwoma pominiecie jest tylko 1 — dlatego
                    #    sam przelot tutaj NIE mierzy tego, przed czym sufit chroni):
                    #      WYMAGA_DOCKERA 7 + WYMAGA_ZIP 4, wspolne 2  ⇒ unia 9
                    #        z tego w `narzedzia-qa/` 6, w `testy/` 3
                    #      + 1 pomijana BEZWARUNKOWO (`dowod-zachowania-segregator`,
                    #        deinstaluje wtyczke ze stosu; lezy w `narzedzia-qa/`)
                    #      zakres `narzedzia-qa` (tak wola CI):     6 + 1 =  7
                    #      BEZ zakresu (tak wola `k7-koniec`):      9 + 1 = 10
                    #    Sufit = maksimum z obu = 10. Przy 9 przelot bez zakresu — czyli ten,
                    #    ktorego zada bramka wydania — wywalilby sie na WLASNEJ poprawce.
                    # ⛔ NIE przepisuj tej liczby przy nastepnym scaleniu: kazda nowa proba
                    #    dockerowa albo zipowa ja przesuwa. Policz komenda na scalonym drzewie.
MAX_POMINIETYCH_PELNY=10   # ta sama arytmetyka, sciezka BEZ zakresu (tak wola `k7-koniec`)

# ⛔ PODLOGA URUCHOMIONYCH (recenzja #287 WAZNA 2, druga polowa tej samej wady). Sufit
# pominiec sam w sobie NIE chroni: gdyby ktos podniosl go "swiadomie" nie licząc, ile prob
# w ogole jest w zakresie, POMINIETO moglby zjesc caly zestaw i przelot dalej konczylby sie
# zielono, nie zmierzywszy niczego. Podloga jest DRUGA, NIEZALEZNA brama na tej samej
# liczbie: minimalna liczba prob, ktore MUSZA sie faktycznie wykonac (ILE = N - POMINIETO).
# Literaly, ta sama arytmetyka co MAX_POMINIETYCH_* wyzej (N minus maksymalne pominiecie).
MIN_URUCHOMIONYCH_PELNY=49    # 59 prob w calym repo minus MAX_POMINIETYCH_PELNY(10) (5.09: +rejestr-liczniki)  (5.09: +1 przelot-bramy-proba-kontrolna)
MIN_URUCHOMIONYCH_ZAKRES=16   # 23 prob w narzedzia-qa minus MAX_POMINIETYCH_ZAKRES(7) (5.09: +rejestr-liczniki)  (5.09: +1 przelot-bramy-proba-kontrolna)

# Tryb LISTY (PRZELOT_LISTA=1) niczego nie uruchamia i nie pisze logu — nie jest dowodem, więc brudne
# drzewo mu nie przeszkadza (próba kontrolna odłamków woła go na kopii przelotu w trakcie pracy).
if [ "${PRZELOT_LISTA:-}" != 1 ] && [ -n "$(git status --porcelain -uno 2>/dev/null)" ]; then
	echo "PRZELOT: drzewo robocze BRUDNE — dowód powstałby na kodzie, którego nie ma w historii." >&2
	echo "         Zacommituj zmiany i powtórz (log niesie COMMIT i jest po nim sprawdzany)." >&2
	exit 2
fi

ZAKRES="${2:-.}"
[ -d "$ZAKRES" ] || { echo "PRZELOT: katalog zakresu [$ZAKRES] nie istnieje." >&2; exit 2; }
mapfile -t PROBY < <(find "$ZAKRES" -name '*proba-kontrolna*.sh' -not -path './.git/*' | sed 's|^\./||' | sort)

# ⛔ ODŁAMKI (D41 c.d., 5.09): CI dzieli przelot na joby RÓWNOLEGŁE, bo dwie próby zjadają ~80 % czasu
# (`bramki-proba-kontrolna.sh` ~590 s, `bramka-poswiadczenia-proba-kontrolna.sh` ~310 s; pozostałe
# trzynaście razem ~30 s — zmierzone 5.09). Czekanie spada do najdłuższej próby, koszt minut bez zmian.
# Odłamki są NAZWANE TUTAJ, nie w workflow — jedno źródło prawdy, które próba kontrolna
# (`przelot-odlamki-proba-kontrolna.sh`) sprawdza: suma trzech odłamków MUSI równać się pełnej liście.
# Bez nazwy odłamka przelot biegnie w całości, jak dotąd (`k7-koniec`, przelot ręczny).
ODLAMEK="${PRZELOT_ODLAMEK:-}"
FILTR_TYLKO=""; FILTR_BEZ=""
case "$ODLAMEK" in
	"") ;;
	bramki)        FILTR_TYLKO='^bramki-proba-kontrolna[.]sh$' ;;
	poswiadczenia) FILTR_TYLKO='^bramka-poswiadczenia-proba-kontrolna[.]sh$' ;;
	reszta)        FILTR_BEZ='^(bramki|bramka-poswiadczenia)-proba-kontrolna[.]sh$' ;;
	*) echo "PRZELOT: nieznany odłamek [$ODLAMEK] — znam: bramki, poswiadczenia, reszta." >&2; exit 2 ;;
esac

# ⛔ SUFIT I PODLOGA PER ODLAMEK (recenzja #287 WAZNA 2). MAX_POMINIETYCH_ZAKRES(7) byl
# policzony dla JEDNEGO przelotu po calym `narzedzia-qa` — odlamki `bramki` i `poswiadczenia`
# maja po JEDNEJ probie, wiec ten sufit nie zapali sie u nich NIGDY. Kazdy odlamek dostaje
# wiec WLASNY literal (ta sama arytmetyka — N prob w odlamku minus prawdopodobne pominiecia
# Dockera/zip/bezwarunkowe — patrz komentarz przy MAX_POMINIETYCH_ZAKRES) i WLASNA podloge
# na liczbe faktycznie URUCHOMIONYCH (ILE = N - POMINIETO) — druga, niezalezna brama: gdyby
# ktos kiedys podniosl sufit pominiec nie licząc N, podloga dalej lapie zestaw, ktory nic
# nie zmierzyl. Odlamki istnieja WYLACZNIE dla zakresu `narzedzia-qa` (tak wola quality.yml);
# z innym zakresem te literaly znacza co innego, wiec odmawiamy zamiast zgadywac.
if [ -n "$ODLAMEK" ] && [ "$ZAKRES" != "narzedzia-qa" ]; then
	echo "PRZELOT: odlamek [$ODLAMEK] jest policzony wylacznie dla zakresu 'narzedzia-qa' (tak wola quality.yml) — zakres [$ZAKRES] dalby bledny sufit/podloge." >&2
	exit 2
fi
case "$ODLAMEK" in
	bramki)
		MAX_POMINIETYCH=0        # 1 proba w odlamku (bramki-proba-kontrolna.sh) — zaden Docker/zip/bezwarunkowe jej nie dotyczy
		MIN_URUCHOMIONYCH=1      # 1 - 0
		;;
	poswiadczenia)
		MAX_POMINIETYCH=0        # 1 proba w odlamku (bramka-poswiadczenia-proba-kontrolna.sh), jw.
		MIN_URUCHOMIONYCH=1      # 1 - 0
		;;
	reszta)
		MAX_POMINIETYCH=7        # 19 prob w odlamku (21 w narzedzia-qa minus bramki/poswiadczenia);
		                          # bramki/poswiadczenia nie wnosza pominiec, wiec to ta sama liczba co MAX_POMINIETYCH_ZAKRES
		MIN_URUCHOMIONYCH=12     # 19 - 7
		;;
	"")
		# ⛔ PODLOGA TYLKO DLA ZAKRESOW, DLA KTORYCH ZOSTALA POLICZONA (recenzja #290 WAZNA 1).
		# MIN_URUCHOMIONYCH_ZAKRES=15 jest policzone dla JEDNEGO konkretnego katalogu
		# (`narzedzia-qa`, 21 prob). Stosowana wczesniej do KAZDEGO zakresu != "." — czyli
		# takze do zagniezdzonych przelotow na prowizorycznych katalogach (np.
		# `przelot-brak-zip-proba-kontrolna.sh`, ktory kopiuje 2 pliki do ROBOCZY) — dawala
		# tam ILE=0 < 14 i EXIT=1 na CZYSTYM kodzie. Ta sama logika co odmowa odlamkow dla
		# nieznanego zakresu wyzej (linie 224-228), ale TU NIE ODMAWIAMY: zagniezdzone
		# przeloty na dowolnym katalogu sa legalne, tylko podloga dla nich nie ma sensu —
		# sufit pominiec (MAX_POMINIETYCH_ZAKRES) zostaje, bo on jest gorna granica, nie
		# policzonym literalem specyficznym dla `narzedzia-qa`.
		if [ "$ZAKRES" = "." ]; then
			MAX_POMINIETYCH="$MAX_POMINIETYCH_PELNY"; MIN_URUCHOMIONYCH="$MIN_URUCHOMIONYCH_PELNY"
		elif [ "$ZAKRES" = "narzedzia-qa" ]; then
			MAX_POMINIETYCH="$MAX_POMINIETYCH_ZAKRES"; MIN_URUCHOMIONYCH="$MIN_URUCHOMIONYCH_ZAKRES"
		else
			MAX_POMINIETYCH="$MAX_POMINIETYCH_ZAKRES"; MIN_URUCHOMIONYCH=0
			echo "PODLOGA: brak (zakres $ZAKRES nie jest policzony)" >&2
		fi
		;;
esac
if [ -n "$FILTR_TYLKO" ]; then
	mapfile -t PROBY < <(printf '%s\n' "${PROBY[@]}" | awk -F/ -v re="$FILTR_TYLKO" '$NF ~ re')
fi
if [ -n "$FILTR_BEZ" ]; then
	mapfile -t PROBY < <(printf '%s\n' "${PROBY[@]}" | awk -F/ -v re="$FILTR_BEZ" '$NF !~ re')
fi
# ⛔ Zero prób to ODMOWA, nie sukces: zawężony zakres (albo odłamek z literówką), który nic nie
# łapie, wyglądałby dokładnie jak przelot bez uwag. Ta sama klasa co mianownik zero w I-2.
[ "${#PROBY[@]}" -ge 1 ] || { echo "PRZELOT: nie znalazłem żadnej próby kontrolnej w [$ZAKRES] (odłamek: [${ODLAMEK:-pełny}])." >&2; exit 2; }
# Tryb LISTY (dla próby kontrolnej odłamków): same nazwy, nic nie uruchamia.
if [ "${PRZELOT_LISTA:-}" = 1 ]; then printf '%s\n' "${PROBY[@]##*/}"; exit 0; fi

COMMIT=$(git rev-parse --short=7 HEAD)
GALAZ=$(git rev-parse --abbrev-ref HEAD)
START=$(date '+%Y-%m-%d %H:%M')

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
ZLE=0; ILE=0; POMINIETO=0; NIEZMIERZONE=0; ZLE_NAZWY=(); NIEZMIERZONE_NAZWY=()

for SCIEZKA in "${PROBY[@]}"; do
	NAZWA="${SCIEZKA##*/}"
	if [ -n "${POMIJANE[$NAZWA]:-}" ]; then
		printf 'POMINIETO %s — %s\n' "$NAZWA" "${POMIJANE[$NAZWA]}" >> "$TMP"
		POMINIETO=$((POMINIETO + 1))
		echo "  pomijam  $NAZWA (${POMIJANE[$NAZWA]})"
		continue
	fi
	echo "  uruchamiam  $NAZWA"
	T0=$SECONDS
	bash "$SCIEZKA" > /dev/null 2>&1
	KOD=$?
	CZAS=$((SECONDS - T0))
	printf '%s EXIT=%s %ss\n' "$NAZWA" "$KOD" "$CZAS" >> "$TMP"
	ILE=$((ILE + 1))
	# ⛔ NAZWA PADAJACEJ PROBY NA STDOUT, nie tylko do logu. Log ladowal w katalogu
	# tymczasowym wykonawcy, wiec w CI widac bylo wylacznie „z EXIT≠0: 2" — bez ani jednej
	# nazwy. Kazda porazka wymagala wtedy recznego dochodzenia u siebie, a odtworzenie
	# zestawu CI nie jest darmowe (konto z Dockerem uruchamia INNY zestaw prob).
	# ⛔ KOD 2 TO NIE PORAZKA (recenzja #272 r15, WAZNA 1). Proba, ktora ODMAWIA pomiaru
	# („nie mam warunkow wstepnych"), nie jest wada produktu — a do tej rundy kazdy
	# niezerowy kod ladowal w liczniku PADLA. To ta sama klasa (I-12), ktora ten PR
	# naprawia w `bramki.sh`: przyrzad orzekal o produkcie, nie ustaliwszy niczego.
	# ⭐ Niezmierzone SA LICZONE i maja wlasny sufit — inaczej zestaw kurczylby sie po cichu.
	if [ "$KOD" -eq 0 ]; then
		:
	elif [ "$KOD" -eq 2 ]; then
		NIEZMIERZONE=$((NIEZMIERZONE + 1)); NIEZMIERZONE_NAZWY+=( "$NAZWA" )
		printf '  NIE ZMIERZONO %s (EXIT=2 — brak warunkow wstepnych, NIE wada)\n' "$NAZWA"
	else
		ZLE=$((ZLE + 1)); ZLE_NAZWY+=( "$NAZWA (EXIT=$KOD)" )
		printf '  PADLA       %s (EXIT=%s)\n' "$NAZWA" "$KOD"
	fi
done

mkdir -p "$(dirname "$LOG")"
{
	printf '# PRÓBY KONTROLNE NARZĘDZI — przelot %s\n' "$START"
	printf '#\n'
	printf '# Każda pozycja: skrypt podkłada narzędziu znaną wadę i sprawdza, czy narzędzie ją WIDZI.\n'
	printf '# Kod wyjścia zero znaczy: narzędzie zareagowało na podłożoną wadę i przepuściło czysty kod.\n'
	printf '#\n'
	printf '%s\n' "$ZNACZNIK"
	printf 'COMMIT: %s\n' "$COMMIT"
	printf 'GALAZ: %s\n' "$GALAZ"
	printf 'ODLAMEK: %s\n' "${ODLAMEK:-pelny}"
	printf 'PODSUMOWANIE: uruchomionych %s, PADŁO: %s, NIE ZMIERZONO (EXIT=2): %s, pominiętych: %s\n' "$ILE" "$ZLE" "$NIEZMIERZONE" "$POMINIETO"
	[ "$NIEZMIERZONE" -gt 0 ] && printf 'NIE ZMIERZONO: %s\n' "${NIEZMIERZONE_NAZWY[*]}"
	printf '#\n'
	cat "$TMP"
} > "$LOG"

printf '\nPRZELOT: uruchomionych %s, z EXIT≠0: %s, pominiętych %s — zapisane w %s\n' "$ILE" "$ZLE" "$POMINIETO" "$LOG"
[ "$ZLE" -eq 0 ] || printf 'PRZELOT: %s prób nie przeszło — log niesie stan faktyczny, nie wycinamy pozycji.\n' "$ZLE"
# Nazwy jeszcze raz, na koncu — kto czyta sam ogon dlugiego logu CI, tez ma je zobaczyc.
# Po jednej w linii: przecinkami sklejona lista dwudziestu nazw jest nieczytelna dokladnie tam,
# gdzie ma pomoc.
[ "$ZLE" -eq 0 ] || printf 'PRZELOT: nie przeszly:\n'
[ "$ZLE" -eq 0 ] || printf '         %s\n' "${ZLE_NAZWY[@]}"

# ⛔ SUFIT NIEZMIERZONYCH — sprawdzany PRZED sufitem pominiec, bo „nie zmierzylam" jest
# stanem gorszym niz „pominelam z powodem": pominiecie ma nazwany powod w liscie, a kod 2
# przychodzi z proby i nikt go z gory nie zaplanowal.
if [ "$NIEZMIERZONE" -gt "$MAX_NIEZMIERZONYCH" ]; then
	printf 'PRZELOT: NIE ZMIERZONO %s prob przy suficie %s — zestaw kurczy sie po cichu.\n' \
		"$NIEZMIERZONE" "$MAX_NIEZMIERZONYCH" >&2
	printf '         %s\n' "${NIEZMIERZONE_NAZWY[*]}" >&2
	printf '         Kod 2 NIE jest wada produktu, ale te proby NICZEGO NIE SPRAWDZILY.\n' >&2
	exit 1
fi

# ⛔ SUFIT POMINIĘĆ — zestaw, który się kurczy, wygląda dokładnie jak zdany.
if [ "$POMINIETO" -gt "$MAX_POMINIETYCH" ]; then
	printf 'PRZELOT: pominiętych %s przy suficie %s — lista pominięć ROŚNIE.\n' "$POMINIETO" "$MAX_POMINIETYCH" >&2
	printf '         To awaria pokrycia, nie sukces. Podnieś sufit ŚWIADOMIE w commicie albo napraw próbę.\n' >&2
	exit 1
fi

# ⛔ PODLOGA URUCHOMIONYCH — druga, NIEZALEZNA brama (recenzja #287 WAZNA 2). Sufit wyzej
# pilnuje POMINIETO; ta brama pilnuje ILE wprost, wiec gdyby ktos kiedys podniosl sufit
# nie licząc N prob w zakresie/odlamku, zestaw, ktory nic nie zmierzyl (ILE=0), dalej pada TU.
if [ "$ILE" -lt "$MIN_URUCHOMIONYCH" ]; then
	printf 'PRZELOT: uruchomiono %s prob przy podlodze %s — zestaw zmierzyl za malo, zeby cokolwiek powiedziec.\n' "$ILE" "$MIN_URUCHOMIONYCH" >&2
	printf '         To awaria pokrycia (mozliwe: pominiecia zjadly caly odlamek), nie sukces.\n' >&2
	exit 1
fi

exit $([ "$ZLE" -eq 0 ] && echo 0 || echo 1)
