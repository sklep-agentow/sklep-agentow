#!/bin/bash
# stos.sh — WĄSKIE, CZYSTO ODCZYTOWE okno recenzenta na żywy stos.     WERSJA 1.3.0
#
# PO CO TO POWSTAŁO (policzone 20.08): w dzisiejszych recenzjach padło
# 91 zdań „czego nie sprawdziłem". PIĘĆDZIESIĄT JEDEN z nich brzmiało
# „nie mam uruchomionego WordPressa", „nie zweryfikowałem paczki", „nie
# sprawdziłem renderowania" — a warsztatowy WordPress stoi na TEJ SAMEJ
# maszynie co wykonawca CI, pod http://localhost:8890. Dostępu nie było
# wyłącznie dlatego, że nikt go nie dał.
#
# ⛔ ZASADA, KTÓRA RZĄDZI TYM PLIKIEM: recenzent NIE DOSTAJE dockera.
#    Dostaje listę NAZWANYCH operacji. Polecenie jest zawsze wpisane
#    w tym skrypcie; dzwoniący może podać co najwyżej JEDNĄ WARTOŚĆ
#    (ścieżkę, nazwę opcji, nazwę tabeli), i to wyłącznie taką, która
#    przejdzie twarde sprawdzenie wzorca. Nie ma operacji „uruchom".
#    Nie ma przekazywania flag. Nie ma wyboru kontenera.
#
# ⛔ WSZYSTKO TU JEST TYLKO DO ODCZYTU. Żadna operacja nie zapisuje,
#    nie kasuje, nie aktywuje, nie wysyła. `wp` wołane jest wyłącznie
#    z podpoleceniami odczytowymi wypisanymi niżej w tablicy; SQL składa
#    ten skrypt i zawsze jest to SELECT COUNT(*).
#
# Gdy stos nie chodzi — skrypt MÓWI TO WPROST i kończy się kodem 2.
# Nigdy nie udaje, że sprawdził, i nigdy nie kończy się cicho zielono.
#
# Paczek TU NIE MA celowo: od nich jest narzedzia-qa/paczka-zgodnosc.sh
# (PR #101), który porównuje zip z repo sumami SHA-256. Drugi raz tego
# nie piszę.
#
# Kody wyjścia: 0 = operacja wykonana, odpowiedź wypisana
#               2 = stos nie odpowiada, brak narzędzia, nieznana operacja
#                   albo argument odrzucony przez sprawdzenie wzorca
set -u

WERSJA=1.3.0

# ⛔ Stałe, nie parametry. Dzwoniący NIE wybiera kontenera ani adresu — wybiera
#    co najwyżej STOS, i to wyłącznie z tej zamkniętej listy dwóch pozycji:
#      warsztat — wspólny stos warsztatowy (domyślny, na nim pracują builderzy),
#      probny   — czysta instalacja stawiana przez narzedzia-qa/dowod-z-paczki.sh
#                 na osobnej bazie; wolno ją kasować i stawiać od zera.
#    Nazwa spoza listy to odmowa. Kontenery i adresy dalej są wpisane tutaj.
STOS=warsztat
if [ "${1:-}" = "--stos" ]; then
	STOS="${2:-}"
	shift 2 || true
fi
case "$STOS" in
	warsztat)
		KONTENER_WP=strona_wp_wordpress
		KONTENER_DB=strona_wp_db
		ADRES_WP=http://localhost:8890
		;;
	probny)
		KONTENER_WP=probny_wp
		KONTENER_DB=strona_wp_db
		ADRES_WP=http://localhost:8891
		;;
	*)
		printf 'STOS: ODMOWA — nie znam stosu [%s]. Dozwolone: warsztat, probny.\n' "$STOS" >&2
		exit 2
		;;
esac
ADRES_LAPACZ=http://127.0.0.1:8025

# Prefiksy tabel, o które wolno pytać. Tabele rdzenia WordPressa poza tą
# trójką nie są recenzentowi do niczego potrzebne, a im węższe okno, tym
# mniej jest do przemyślenia przy następnej zmianie.
PREFIKSY_TABEL=(wp_segm_ wp_chatl_ wp_sklep_)
TABELE_RDZENIA=(wp_options wp_posts wp_users)

# ------------------------------------------------------------------------
# odmowy — zawsze głośne, zawsze z powodem
# ------------------------------------------------------------------------

odmow() {
	printf 'STOS: ODMOWA — %s\n' "$1" >&2
	exit 2
}

brak_stosu() {
	printf 'STOS NIE ODPOWIADA — %s\n' "$1" >&2
	printf 'Nie zgaduję i nie udaję, że sprawdziłem. Napisz w recenzji, że stos\n' >&2
	printf 'warsztatowy nie odpowiadał, i podaj tę linię jako dowód.\n' >&2
	exit 2
}

wymagaj_narzedzia() {
	command -v "$1" > /dev/null || brak_stosu "nie ma polecenia '$1' na tej maszynie"
}

# ------------------------------------------------------------------------
# sprawdzenia wzorców — jedyne miejsce, gdzie wartość od dzwoniącego
# zamienia się w coś, co wolno podać dalej
# ------------------------------------------------------------------------

sprawdz_sciezke() {
	local s="$1"
	case "$s" in
		/*) ;;
		*) odmow "ścieżka musi zaczynać się od / (dostałam: [$s])" ;;
	esac
	case "$s" in
		*..*) odmow "ścieżka zawiera .. — wyjście poza witrynę jest zabronione" ;;
	esac
	case "$s" in
		*[!A-Za-z0-9/_.?=\&%-]*) odmow "ścieżka ma znak spoza dozwolonego zestawu (dostałam: [$s])" ;;
	esac
}

sprawdz_nazwe() {
	local n="$1" co="$2"
	[ -n "$n" ] || odmow "$co nie może być puste"
	case "$n" in
		*[!a-zA-Z0-9_-]*) odmow "$co ma znak spoza [a-zA-Z0-9_-] (dostałam: [$n])" ;;
	esac
}

sprawdz_tabele() {
	local t="$1" p
	sprawdz_nazwe "$t" "nazwa tabeli"
	for p in "${PREFIKSY_TABEL[@]}"; do
		case "$t" in "$p"*) return 0 ;; esac
	done
	for p in "${TABELE_RDZENIA[@]}"; do
		[ "$t" = "$p" ] && return 0
	done
	odmow "tabela [$t] jest poza białą listą (prefiksy: ${PREFIKSY_TABEL[*]}; rdzeń: ${TABELE_RDZENIA[*]})"
}

# ------------------------------------------------------------------------
# rozmowa ze stosem — polecenia wpisane tutaj, nigdy składane z wejścia
# ------------------------------------------------------------------------

kontener_zyje() {
	wymagaj_narzedzia docker
	docker ps --filter "name=^${KONTENER_WP}$" --filter status=running --format '{{.Names}}' 2> /dev/null \
		| grep -qx "$KONTENER_WP" \
		|| brak_stosu "kontener $KONTENER_WP nie jest uruchomiony (sprawdzone przez docker ps)"
}

# Jedyne wywołanie `wp` w całym pliku. Podpolecenie przychodzi ze stałych
# w gałęziach niżej, NIE od dzwoniącego; wartość leci osobnym argumentem,
# więc nie ma tu składania łańcucha powłoki.
wp_odczyt() {
	kontener_zyje
	docker exec "$KONTENER_WP" wp --allow-root "$@" 2>&1
}

# Wariant, który NIE pozwala kodowi błędu wyciec jako egzotyczny numer.
# Bez tego `wp db query` bez klienta mysql kończył cały skrypt kodem 127 —
# recenzent zobaczyłby liczbę, której nie ma w opisie, zamiast zdania,
# co konkretnie nie działa. Kod 127 nie jest odpowiedzią, tylko awarią.
wp_odczyt_lub_padnij() {
	local wynik status
	wynik="$(wp_odczyt "$@")"; status=$?
	if [ "$status" -ne 0 ]; then
		printf '%s\n' "$wynik" >&2
		brak_stosu "polecenie 'wp $*' skonczylo sie kodem $status (powod wyzej)"
	fi
	printf '%s\n' "$wynik"
}

# Liczenie wierszy idzie do KONTENERA BAZY, bo obraz WordPressa nie ma
# klienta mysql (zmierzone: `wp db query` konczy sie „env: 'mysql': No such
# file or directory"). Poswiadczenia czytane sa z wp-config przez wp-cli
# i nigdy nie sa wypisywane; haslo leci przez MYSQL_PWD, nie w linii polecen.
# JEDNO miejsce, w ktorym ten skrypt rozmawia z baza. Obie operacje liczace ida
# przez nie, zeby nie mnozyc wywolan dockera — proba kontrolna liczy je i pada,
# gdy przybywa nowych (to jej sposob na wykrycie obejscia bialej listy).
licz_sql() {
	local sql="$1" baza uzyt haslo wynik status
	kontener_zyje
	docker ps --filter "name=^${KONTENER_DB}$" --filter status=running --format '{{.Names}}' 2> /dev/null \
		| grep -qx "$KONTENER_DB" || brak_stosu "kontener bazy $KONTENER_DB nie jest uruchomiony"
	baza="$(wp_odczyt_lub_padnij config get DB_NAME | tr -d '\r')"
	uzyt="$(wp_odczyt_lub_padnij config get DB_USER | tr -d '\r')"
	haslo="$(wp_odczyt_lub_padnij config get DB_PASSWORD | tr -d '\r')"
	wynik="$(docker exec -e MYSQL_PWD="$haslo" "$KONTENER_DB" \
		mariadb -u"$uzyt" -N -B -e "$sql" "$baza" 2>&1)"; status=$?
	if [ "$status" -ne 0 ]; then
		brak_stosu "baza nie odpowiedziala na liczenie: $wynik"
	fi
	printf '%s' "$wynik"
}

policz_wiersze() {
	licz_sql "SELECT COUNT(*) FROM \`$1\`;"
}

policz_wiersze_gdzie() {
	licz_sql "SELECT COUNT(*) FROM \`$1\` WHERE \`$2\` = '$3';"
}

# ------------------------------------------------------------------------
# operacje
# ------------------------------------------------------------------------

pomoc() {
	cat <<POMOC
stos.sh $WERSJA — czysto odczytowe okno na stos WordPressa (teraz: $STOS).

  --stos warsztat|probny   ktory stos pytamy (domyslnie warsztat); musi stac PRZED operacja

  stan                     czy WordPress, baza i łapacz maili odpowiadają
  strona <sciezka>         GET $ADRES_WP<sciezka> — kod HTTP, typ, rozmiar, treść
  naglowki <sciezka>       same nagłówki odpowiedzi
  opcja <nazwa>            wartość jednej opcji WordPressa (wp option get);
                           nazwy z klucz/haslo/token/secret/api/key/pass → tylko długość i ogon (1.3.0)
  opcje <prefiks>          nazwy opcji zaczynające się od prefiksu
  wtyczki                  lista wtyczek ze stanem (wp plugin list)
  tabele                   tabele wtyczek widoczne w bazie
  wiersze <tabela>         SELECT COUNT(*) — tabela z białej listy
  wiersze-gdzie <t.k=w>    SELECT COUNT(*) ... WHERE kolumna = wartość
                           (np. chatl_rozmowy.silnik=ai — wszystkie trzy części
                           przez białą listę znaków, SQL składa ten skrypt)
  maile                    ile maili złapał mailpit
  ostatni-mail             nadawca, odbiorca, temat i początek treści
  wersja-wp                wersja rdzenia WordPressa

Paczki sprawdza osobne narzędzie:
  bash narzedzia-qa/paczka-zgodnosc.sh <wtyczka>

Wszystko powyżej jest TYLKO DO ODCZYTU. Nie ma operacji zapisu ani
uruchamiania dowolnych poleceń — i nie będzie: to jest cała lista.
POMOC
}

op_stan() {
	local kod ile
	wymagaj_narzedzia curl
	printf '=== STAN STOSU WARSZTATOWEGO ===\n'
	kod="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$ADRES_WP/" 2> /dev/null || echo 000)"
	printf '  WordPress %s  → HTTP %s\n' "$ADRES_WP" "$kod"
	[ "$kod" = "000" ] && brak_stosu "WordPress pod $ADRES_WP nie odpowiada"
	kontener_zyje
	printf '  kontener %s  → uruchomiony\n' "$KONTENER_WP"
	printf '  wersja rdzenia            → %s\n' "$(wp_odczyt core version | tr -d '\r')"
	ile="$(curl -s --max-time 10 "$ADRES_LAPACZ/api/v1/messages?limit=1" 2> /dev/null | sed -n 's/.*"messages_count":\([0-9]*\).*/\1/p')"
	if [ -n "$ile" ]; then
		printf '  łapacz maili %s → %s wiadomości\n' "$ADRES_LAPACZ" "$ile"
	else
		printf '  łapacz maili %s → NIE ODPOWIADA\n' "$ADRES_LAPACZ"
	fi
}

op_strona() {
	local sciezka="$1" naglowki kod typ rozmiar tresc
	sprawdz_sciezke "$sciezka"
	wymagaj_narzedzia curl
	tresc="$(mktemp)"; trap 'rm -f "$tresc"' RETURN
	naglowki="$(curl -s -D - -o "$tresc" --max-time 20 "$ADRES_WP$sciezka" 2> /dev/null)" \
		|| brak_stosu "curl nie doszedł do $ADRES_WP$sciezka"
	kod="$(printf '%s' "$naglowki" | sed -n 's/^HTTP\/[0-9.]* \([0-9]*\).*/\1/p' | tail -1)"
	[ -n "$kod" ] || brak_stosu "brak odpowiedzi HTTP z $ADRES_WP$sciezka"
	typ="$(printf '%s' "$naglowki" | sed -n 's/^[Cc]ontent-[Tt]ype: *//p' | tr -d '\r' | tail -1)"
	rozmiar="$(wc -c < "$tresc")"
	printf '=== GET %s%s ===\n' "$ADRES_WP" "$sciezka"
	printf '  HTTP %s   typ: %s   rozmiar: %s B\n\n' "$kod" "${typ:-nieznany}" "$rozmiar"
	printf -- '--- treść (pierwsze 80 linii) ---\n'
	head -80 "$tresc"
}

op_naglowki() {
	sprawdz_sciezke "$1"
	wymagaj_narzedzia curl
	printf '=== HEAD %s%s ===\n' "$ADRES_WP" "$1"
	curl -s -I --max-time 20 "$ADRES_WP$1" 2> /dev/null || brak_stosu "curl nie doszedł do $ADRES_WP$1"
}

op_opcja() {
	sprawdz_nazwe "$1" "nazwa opcji"
	printf '=== wp option get %s ===\n' "$1"
	# ⛔ SEKRETY NIE WYCHODZĄ NA STDOUT (1.3.0). Wyjście tego okna ląduje w logach akcji GitHuba
	#    (recenzent) — 23.08 recenzent zobaczył w nich pełny klucz API dostawcy. Opcje, których
	#    NAZWA wskazuje sekret, dostają tylko DŁUGOŚĆ i 2-znakowy ogon (ogon tylko przy ≥ 12 zn.;
	#    krótsze — sama długość). Wystarczy, żeby stwierdzić obecność i zmianę, za mało, żeby użyć.
	# ⚠️ ŚWIADOMA GRANICA: filtr jest PO NAZWIE (lista wzorców niżej), nie po treści — opcja-tablica
	#    (np. ustawienia) może nieść sekret w środku i przejdzie jawnie. Dokładając opcję z sekretem
	#    nazwij ją tak, żeby wzorzec łapał (klucz/haslo/token/secret/api/key/pass).
	case "$1" in
		*klucz*|*haslo*|*token*|*sekret*|*secret*|*password*|*pass*|*api*|*key*)
			# podstawienie $(…) to PODPOWŁOKA: exit 2 z brak_stosu kończy ją, nie skrypt — dlatego
			# kod wyjścia jest sprawdzany i przekazywany dalej (recenzja #157). wp_odczyt scala
			# stderr do stdout, więc komunikat awarii siedzi w WART — przy błędzie wypisujemy go
			# na stderr (nigdy jako „wartość") i wychodzimy z tym samym kodem.
			local WART KOD; WART="$(wp_odczyt option get "$1")"; KOD=$?
			[ "$KOD" -ne 0 ] && { printf '%s\n' "$WART" >&2; exit "$KOD"; }
			if [ "${#WART}" -ge 12 ]; then printf '[SEKRET ZAMASKOWANY] długość=%s ogon=…%s\n' "${#WART}" "${WART: -2}"
			else printf '[SEKRET ZAMASKOWANY] długość=%s\n' "${#WART}"; fi ;;
		*) wp_odczyt option get "$1" ;;
	esac
}

op_opcje() {
	sprawdz_nazwe "$1" "prefiks opcji"
	printf '=== opcje zaczynające się od %s ===\n' "$1"
	wp_odczyt_lub_padnij option list --search="$1*" --field=option_name
}

op_wtyczki() {
	printf '=== wp plugin list ===\n'
	wp_odczyt_lub_padnij plugin list --fields=name,status,version
}

op_tabele() {
	printf '=== tabele wtyczek w bazie ===\n'
	wp_odczyt_lub_padnij db tables --all-tables | grep -E '^wp_(segm|chatl|sklep)_' || echo "(brak tabel wtyczek)"
}

op_wiersze() {
	sprawdz_tabele "$1"
	printf '=== SELECT COUNT(*) FROM %s ===\n' "$1"
	# SQL składa ten skrypt, a nazwa tabeli przeszła białą listę wyżej.
	printf '  wierszy: %s\n' "$(policz_wiersze "$1")"
}

# Liczenie z JEDNYM warunkiem równości. Powstało, bo dowód „tryb AI odpowiadał
# gościowi" opiera się na liczbie wierszy z `silnik = ai`, a przez to wąskie okno
# dało się policzyć tylko CAŁĄ tabelę — recenzent nie mógł odtworzyć liczby i musiał
# wierzyć na słowo. Dowód, którego nie da się powtórzyć cudzą ręką, nie jest dowodem.
#
# Wejście: `tabela.kolumna=wartosc`, wszystkie trzy części przez tę samą białą listę
# znaków co reszta skryptu ([a-zA-Z0-9_-]), tabela dodatkowo przez białą listę tabel.
# SQL składa TEN skrypt, nie dzwoniący; wartość nie może wnieść ani cudzysłowu,
# ani spacji, ani średnika.
op_wiersze_gdzie() {
	local wejscie="$1" tabela reszta kolumna wartosc
	case "$wejscie" in
		*.*=*) ;;
		*) odmow "oczekuję postaci tabela.kolumna=wartosc (dostałam: [$wejscie])" ;;
	esac
	tabela="${wejscie%%.*}"
	reszta="${wejscie#*.}"
	kolumna="${reszta%%=*}"
	wartosc="${reszta#*=}"
	sprawdz_tabele "$tabela"
	sprawdz_nazwe "$kolumna" "nazwa kolumny"
	sprawdz_nazwe "$wartosc" "wartość warunku"
	printf '=== SELECT COUNT(*) FROM %s WHERE %s = %s ===\n' "$tabela" "$kolumna" "$wartosc"
	printf '  wierszy: %s\n' "$(policz_wiersze_gdzie "$tabela" "$kolumna" "$wartosc")"
}

op_maile() {
	wymagaj_narzedzia curl
	local ile
	ile="$(curl -s --max-time 10 "$ADRES_LAPACZ/api/v1/messages?limit=1" 2> /dev/null | sed -n 's/.*"messages_count":\([0-9]*\).*/\1/p')"
	[ -n "$ile" ] || brak_stosu "łapacz maili pod $ADRES_LAPACZ nie odpowiada"
	printf '=== łapacz maili ===\n  wiadomości: %s\n' "$ile"
}

op_ostatni_mail() {
	wymagaj_narzedzia curl
	local lista id
	lista="$(curl -s --max-time 10 "$ADRES_LAPACZ/api/v1/messages?limit=1" 2> /dev/null)" \
		|| brak_stosu "łapacz maili pod $ADRES_LAPACZ nie odpowiada"
	id="$(printf '%s' "$lista" | sed -n 's/.*"messages":\[{"ID":"\([^"]*\)".*/\1/p')"
	[ -n "$id" ] || brak_stosu "łapacz maili nie ma ani jednej wiadomości albo nie odpowiada"
	printf '=== ostatni mail z łapacza ===\n'
	curl -s --max-time 10 "$ADRES_LAPACZ/api/v1/message/$id" 2> /dev/null \
		| sed -e 's/","/"\n"/g' \
		| grep -E '"(From|To|Subject|Date|Text)"|"Address"|"Name"' \
		| head -20
}

op_wersja_wp() {
	printf '=== wersja rdzenia WordPressa ===\n'
	wp_odczyt_lub_padnij core version
}

# ------------------------------------------------------------------------
# rozdzielnik — JEDYNE wejście; nieznana nazwa to odmowa, nie próba
# ------------------------------------------------------------------------

OPERACJA="${1:-pomoc}"
ARGUMENT="${2:-}"
[ $# -le 2 ] || odmow "ta operacja przyjmuje najwyżej jedną wartość (dostałam $# argumentów)"

wymagaj_argumentu() {
	[ -n "$ARGUMENT" ] || odmow "operacja '$OPERACJA' wymaga wartości — patrz: bash narzedzia-qa/stos.sh pomoc"
}

case "$OPERACJA" in
	pomoc|--help|-h) pomoc ;;
	stan)         op_stan ;;
	strona)       wymagaj_argumentu; op_strona "$ARGUMENT" ;;
	naglowki)     wymagaj_argumentu; op_naglowki "$ARGUMENT" ;;
	opcja)        wymagaj_argumentu; op_opcja "$ARGUMENT" ;;
	opcje)        wymagaj_argumentu; op_opcje "$ARGUMENT" ;;
	wtyczki)      op_wtyczki ;;
	tabele)       op_tabele ;;
	wiersze)      wymagaj_argumentu; op_wiersze "$ARGUMENT" ;;
	wiersze-gdzie) wymagaj_argumentu; op_wiersze_gdzie "$ARGUMENT" ;;
	maile)        op_maile ;;
	ostatni-mail) op_ostatni_mail ;;
	wersja-wp)    op_wersja_wp ;;
	*) odmow "nie znam operacji '$OPERACJA'. Lista jest zamknięta: bash narzedzia-qa/stos.sh pomoc" ;;
esac
