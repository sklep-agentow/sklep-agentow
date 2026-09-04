#!/bin/bash
# lib-stos-probny.sh — wspólny warsztat dla dowodów na CZYSTEJ instalacji.  WERSJA 1.0.0
#
# Nie uruchamia się sama. Dołączają ją `dowod-z-paczki.sh` (dowód z paczki na
# czystej instalacji) i `dowod-aktualizacji.sh` (dowód aktualizacji w miejscu).
#
# PO CO OSOBNY PLIK: obie te robocizny potrzebują dokładnie tego samego —
# postawić WordPressa od zera z włączonym WP_DEBUG, umieć w nim czytać bazę,
# zdjąć odcisk i posprzątać po sobie. Przepisanie tego drugi raz oznaczałoby, że
# poprawka w jednym miejscu (jak naprawa kolejności odcisku) nie trafia do
# drugiego. To ta sama zasada, co „napraw klasę, nie egzemplarz".
#
# ⛔ NIE DOTYKA STOSU WARSZTATOWEGO. Stawia własną instalację (kontener
#    `probny_wp`, baza `wp_probny`) i tylko ją kasuje. Warsztat, na którym
#    pracują builderzy, zostaje nietknięty.
#
# ⛔ ODCZYTY IDĄ PRZEZ narzedzia-qa/stos.sh --stos probny. Operacje ZMIENIAJĄCE
#    (instalacja, aktywacja, deinstalacja) żyją w skryptach wołających, bo
#    stos.sh jest z założenia wyłącznie do odczytu i taki ma zostać.

# --- stos próbny: stałe, nie parametry ----------------------------------------
KONTENER=probny_wp
KONTENER_DB=strona_wp_db
BAZA=wp_probny
SIEC=strona_wp_default
OBRAZ="${STOS_PROBNY_OBRAZ:-wordpress:6.9.4-php8.4-apache}"  # K7: STOS_PROBNY_OBRAZ=wordpress:6.7-php8.1-apache = deklarowane minimum
PORT=8891
ADRES="http://localhost:$PORT"

ZOSTAW="${ZOSTAW:-nie}"
BLEDY=0

zglos()     { printf '  ⛔ NIE WYSZLO  %s\n' "$1"; BLEDY=$(( BLEDY + 1 )); }
potwierdz() { printf '  ✅ WYSZLO      %s\n' "$1"; }
# ⛔ `padnij` konczy sie ZAWSZE kodem 2, a kod 2 ma tu CZTERY znaczenia: NIE WIEM (to konto nie
# widzi Dockera — o DOSTEPIE, nie o kontenerze) · Docker odpowiedzial i kontenera NIE MA · brak
# narzedzia · brak warunku wstepnego. Komunikat MUSI nazywac, ktore z nich zaszlo.
padnij()    { printf 'STOS PROBNY: %s\n' "$1" >&2; exit 2; }
naglowek()  { printf '\n=== %s ===\n' "$1"; }

wymagaj_narzedzi() {
	command -v docker > /dev/null || padnij "brak dockera"
	command -v unzip  > /dev/null || padnij "brak unzip"
	# ⛔ „NIE WIEM" TO NIE TO SAMO CO „NIE MA" — patrz funkcja `brak_stosu` w `narzedzia-qa/stos.sh`. Pytamy NAJPIERW,
	# czy Docker w ogole odpowiada; dopiero potem wolno orzekac o kontenerze.
	docker ps > /dev/null 2>&1 || padnij "NIE WIEM, czy kontener bazy $KONTENER_DB stoi — to konto nie widzi Dockera (brak dostepu do demona, nie brak kontenera)"
	docker ps --filter "name=^${KONTENER_DB}$" --filter status=running -q | grep -q . \
		|| padnij "Docker odpowiedzial: kontenera bazy $KONTENER_DB na liscie NIE MA — nie mam gdzie postawic stosu probnego"
}

wp() { docker exec "$KONTENER" wp --allow-root --path=/var/www/html "$@" 2>&1; }

# ⛔ ODCZYT OPCJI, KTORY NIE KLAMIE PRZY JEJ BRAKU.
# `wp option get` nieistniejacej opcji wypisuje „Error: Could not get 'x' option",
# a wrapper wp() wyzej scala stderr do stdout — wiec `[ -z "$(wp option get x)" ]`
# widzi NIEPUSTY napis i czyta BRAK opcji jako JEJ OBECNOSC. Kazdy straznik oparty
# na tym wzorcu melduje sukces ZAWSZE, takze gdy nic sie nie zapisalo.
# Zlapane 21.08 w trzech miejscach naraz (znacznik awarii AI, znacznik odrzuconego
# nonce'a, wersja schematu przy aktualizacji). PHP na brak opcji zwraca pustke.
opcja() {
	wp eval "\$w = get_option( '$1' ); echo is_array( \$w ) || is_object( \$w ) ? wp_json_encode( \$w ) : (string) \$w;" 2>&1 | tail -1
}

sql_bazy() {
	local haslo
	haslo="$(docker exec "$KONTENER_DB" printenv MARIADB_ROOT_PASSWORD 2> /dev/null \
		|| docker exec "$KONTENER_DB" printenv MYSQL_ROOT_PASSWORD 2> /dev/null)"
	docker exec -e MYSQL_PWD="$haslo" "$KONTENER_DB" mariadb -uroot -N -B -e "$1" 2>&1
}

# --- stos próbny: postaw od zera ----------------------------------------------

postaw_stos() {
	naglowek "STOS PROBNY — czysta instalacja od zera"
	local haslo_root i=0
	haslo_root="$(docker exec "$KONTENER_DB" printenv MARIADB_ROOT_PASSWORD 2> /dev/null \
		|| docker exec "$KONTENER_DB" printenv MYSQL_ROOT_PASSWORD 2> /dev/null)"
	[ -n "$haslo_root" ] || padnij "nie umiem odczytać hasła root bazy z kontenera $KONTENER_DB"

	# ⛔ `-v` TAKZE TUTAJ. To drugie z dwoch miejsc, w ktorych kasujemy ten kontener —
	#    `postaw_stos` zdejmuje POPRZEDNI stos przed postawieniem nowego. Bez `-v` zostawialo
	#    to sierote tak samo jak `zwin_stos`, tylko ciszej: bieg przerwany w polowie (zamek,
	#    Ctrl-C, timeout) nie dochodzi do zwijania, wiec sprzatal go dopiero NASTEPNY bieg —
	#    i wlasnie tu gubil wolumen.
	docker rm -f -v "$KONTENER" > /dev/null 2>&1 || true
	docker exec -e MYSQL_PWD="$haslo_root" "$KONTENER_DB" \
		mariadb -uroot -e "DROP DATABASE IF EXISTS \`$BAZA\`; CREATE DATABASE \`$BAZA\`;" \
		|| padnij "nie umiem założyć bazy $BAZA"
	printf '  baza %s — utworzona od zera\n' "$BAZA"

	# WP_DEBUG WŁĄCZONY i piszący do PLIKU, a nie na stronę: notice wyrzucony
	# na stronę psuje HTML i bywa niewidoczny, w logu jest zawsze i da się go policzyć.
	docker run -d --name "$KONTENER" --network "$SIEC" -p "$PORT:80" \
		-e WORDPRESS_DB_HOST="$KONTENER_DB" \
		-e WORDPRESS_DB_USER=root \
		-e WORDPRESS_DB_PASSWORD="$haslo_root" \
		-e WORDPRESS_DB_NAME="$BAZA" \
		-e WORDPRESS_DEBUG=1 \
		-e WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', '/var/www/html/debug.log'); define('WP_DEBUG_DISPLAY', false); define('SCRIPT_DEBUG', true);" \
		"$OBRAZ" > /dev/null || padnij "nie umiem uruchomić kontenera $KONTENER"

	until docker exec "$KONTENER" test -f /var/www/html/wp-settings.php 2> /dev/null; do
		i=$(( i + 1 )); [ "$i" -gt 60 ] && padnij "WordPress nie rozpakował się w kontenerze"
		sleep 1
	done
	docker exec "$KONTENER" sh -c 'command -v wp >/dev/null || (curl -sSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x /usr/local/bin/wp)' > /dev/null 2>&1
	docker exec "$KONTENER" sh -c 'command -v wp' > /dev/null 2>&1 || padnij "w kontenerze próbnym nie ma wp-cli"

	i=0
	until wp core is-installed > /dev/null 2>&1; do
		wp core install --url="$ADRES" --title="Stos probny" \
			--admin_user=probny --admin_password=probne-haslo-QA \
			--admin_email=probny@example.com --skip-email > /dev/null 2>&1
		i=$(( i + 1 )); [ "$i" -gt 12 ] && padnij "nie udało się zainstalować rdzenia WordPressa"
		sleep 2
	done
	printf '  WordPress %s — zainstalowany, WP_DEBUG włączony\n' "$(wp core version | tr -d '\r')"
	wyczysc_log
}

zwin_stos() {
	if [ "$ZOSTAW" = tak ]; then
		printf '\nStos próbny ZOSTAJE (--zostaw): %s — panel: %s/wp-admin (probny / probne-haslo-QA)\n' "$KONTENER" "$ADRES"
		return
	fi
	# ⛔ `-v` KASUJE ANONIMOWY WOLUMEN. Bez niego kazdy bieg stosu probnego zostawial
	#    sierote ~88 MB, a stos stawia sie od zera przy KAZDYM uzyciu — dziewieciu wolajacych.
	#    Zmierzone 3.09 na maszynie warsztatu: 38 wolumenow Dockera, z tego 35 nieprzypisanych
	#    do zadnego kontenera (takze zatrzymanego), razem 2,7 GB przy dysku na 92 %.
	#    35 x 88 MB zgadza sie co do rzedu — to sa te sieroty.
	#    linia `docker rm -f -v` w `strony-prawne-stos-probny.sh` probowala to lapac wlasnym `docker rm -f -v` PO wywolaniu
	#    `zwin_stos`, ale wtedy kontenera juz nie ma, wiec ta linia nie miala czego usunac,
	#    a `|| true` to przykrywalo (recenzja #278, DROBNA 2, otwarta od rundy 3).
	docker rm -f -v "$KONTENER" > /dev/null 2>&1 || true
	sql_bazy "DROP DATABASE IF EXISTS \`$BAZA\`;" > /dev/null 2>&1 || true
}

# --- log błędów PHP ------------------------------------------------------------

log_bledow() {
	docker exec "$KONTENER" sh -c 'cat /var/www/html/debug.log 2>/dev/null' 2> /dev/null \
		| grep -E 'PHP (Notice|Warning|Fatal error|Parse error|Deprecated|Recoverable)' || true
}
wyczysc_log() { docker exec "$KONTENER" sh -c ': > /var/www/html/debug.log' 2> /dev/null || true; }

# --- odciski -------------------------------------------------------------------
#
# Odcisk bazy = nazwy opcji + nazwy tabel. Świadomie POMIJANE są wpisy, które sam
# WordPress dopisuje w tle niezależnie od naszej wtyczki — inaczej różnica nigdy
# nie byłaby pusta i „pusta różnica" przestałaby cokolwiek znaczyć. Lista pominięć
# jest WYPISYWANA przy każdym przebiegu; nie ma tu cichych wyjątków.
POMIJANE_WZORCE='^_transient_|^_site_transient_|^cron$|^recently_activated$|^wp_force_deactivated_plugins$|^active_plugins$|^uninstall_plugins$|^db_upgraded$|^auto_updater\.lock$|^finished_|^_wp_session'

odcisk() {
	{
		sql_bazy "SELECT option_name FROM \`$BAZA\`.wp_options ORDER BY option_name;" \
			| grep -Ev "$POMIJANE_WZORCE" | sed 's/^/OPCJA /'
		sql_bazy "SELECT table_name FROM information_schema.tables WHERE table_schema='$BAZA' ORDER BY table_name;" \
			| sed 's/^/TABELA /'
	} 2> /dev/null | sort
}

# Odcisk SCHEMATU: tabela + kolumna + typ. Sama lista tabel nie wystarcza —
# aktualizacja, która dokłada KOLUMNĘ do istniejącej tabeli, po liście tabel
# wygląda dokładnie tak samo jak aktualizacja, która nie zrobiła nic.
odcisk_schematu() {
	local prefiks="$1"
	# `_` w SQL LIKE to METAZNAK „dowolny jeden znak", nie literał — bez ucieczki
	# `wp_chatl%` dopasowałoby też `wpXchatl...`. Klasa #2 ze ściągi egzaminatora
	# (esc_like), a to jest WSPÓLNA biblioteka: nawyk stąd rozniósłby się na każdy
	# kolejny dowód, który z niej skorzysta. Ucieczka przez jawne ESCAPE.
	local prefiks_like="wp_${prefiks}"
	prefiks_like="${prefiks_like//_/!_}"
	sql_bazy "SELECT CONCAT(table_name, ' . ', column_name, ' : ', column_type)
	          FROM information_schema.columns
	          WHERE table_schema='$BAZA' AND table_name LIKE '${prefiks_like}%' ESCAPE '!'
	          ORDER BY table_name, column_name;" 2> /dev/null | sort
}

# Jedno żądanie HTTP przez stos.sh — potrzebne w dwóch miejscach: żeby WordPress
# dopisał opcje, które tworzy dopiero przy pierwszym wyświetleniu strony
# (inaczej pokażą się później jako „ślad po wtyczce"), oraz żeby po podmianie
# plików odpalił się kod aktualizacji, który NIE przechodzi przez hook aktywacji.
zapukaj_na_strone() {
	local stos_sh="$1"
	bash "$stos_sh" --stos probny strona / 2>&1 | sed -n 's/^ *HTTP \([0-9]*\).*/\1/p' | head -1
}
