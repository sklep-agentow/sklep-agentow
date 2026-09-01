#!/bin/bash
# STRAŻNIK REGRESJI — komunikat o za dużym pliku produktu MÓWI PRAWDĘ O PRZYCZYNIE.
#
# WADA (zmierzona 2026-09-01 na stosie warsztatowym: upload_max_filesize=2M, post_max_size=8M):
# gdy CAŁE ciało żądania przekracza `post_max_size`, PHP kasuje `$_POST` I `$_FILES`. Razem
# z nimi znika ukryte pole `action`, więc `admin-post.php` leci na `do_action( 'admin_post' )`,
# którego nikt nie obsługiwał. Właściciel wgrywał ZIP 9 MB i dostawał:
#     http=200 rozmiar_odpowiedzi=0   (pusta biała strona, bez nagłówka Location)
# Ani komunikatu, ani wpisu w dzienniku. Osobna, WCZEŚNIEJSZA wada tej samej klasy —
# komunikat cytujący limit wtyczki (20 MB) przy odrzucie serwera na 2 MB — została
# naprawiona w 0.9.31; kierunek B pilnuje, żeby nie wróciła.
#
# PIĘĆ KIERUNKÓW, bo „naprawa", która zaczyna przechwytywać wszystko albo przestaje
# przyjmować pliki, też usunęłaby objaw:
#   A) ciało PONAD post_max_size          => prawda o przyczynie + REALNY limit serwera,
#                                            nigdy pusta odpowiedź  (PADA przed naprawą)
#   B) pojedynczy plik ponad upload_max_filesize, ciało w limicie
#                                         => komunikat cytuje limit SERWERA, nie 20 MB
#   C) zwykły POST ze ZŁYM nonce'em       => nadal odrzucony przez nonce (nowa gałąź
#                                            NIE przechwytuje zwykłych żądań ani nie
#                                            omija sprawdzenia nonce'a)
#   D) POPRAWNY mały ZIP                  => produkt zapisany i plik POTWIERDZONY
#                                            (naprawa nie odebrała wgrywania nikomu)
#   E) SUBSKRYBENT z ciałem ponad limit    => NIE dostaje konfiguracji serwera
#                                            (PADA na rundzie 1 PR #263)
#   F) MAŁY POST `application/json`        => ŻADNEJ diagnozy o ucięciu — PHP niczego
#                                            nie uciął  (PADA na rundzie 1 PR #263)
#
# ⛔ SKĄD KIERUNEK F. `$_POST` wypełnia się WYŁĄCZNIE dla typów formularzowych — przy
# `application/json` jest puste ZAWSZE, bo tak działa PHP, a ciało leży całe w `php://input`.
# Runda 1 brała za dowód ucięcia samo „puste $_POST + niepuste ciało" i takiemu żądaniu
# wypisywała zmyśloną diagnozę „Serwer odrzucił CAŁE zgłoszenie" (uwaga 4. recenzji).
# Komunikat w tej gałęzi ma jedno zadanie — mówić prawdę o przyczynie.
#
# ⛔ SKĄD KIERUNEK E. Ogólne zaczepienie `admin_post` odpala się dla KAŻDEGO zalogowanego
# użytkownika — dokumentacja WordPressa nazywa je „authenticated admin post request", bez
# progu uprawnień. Runda 1 tego PR-a nie miała tam `current_user_can()` i wypisywała
# `post_max_size`, `upload_max_filesize` oraz rozmiar ciała dowolnemu subskrybentowi
# (uwaga 2. recenzji). Kierunki A-D tego nie widziały, bo mierzą wyłącznie sesję
# właściciela — dziura była poza ich zasięgiem.
#
# Mierzy PRODUKT przez HTTP: adres wysyłki bierze z atrybutu `action` PRAWDZIWEGO
# formularza, nie składa go u siebie — inaczej nie zauważyłby, że formularz stracił
# `action` razem z ciałem żądania.
#
# Użycie:
#   set -a; . /root/.claude/sklep-admin-roboczy.env; set +a
#   bash testy/limit-uploadu-mowi-prawde.sh
#   ADRES=http://localhost:8891 KONTENER=probny_wp WP_ADMIN_USER=probny \
#     WP_ADMIN_PASS=probne-haslo-QA WP_SUB_USER=… WP_SUB_PASS=… \
#     bash testy/limit-uploadu-mowi-prawde.sh
#
# ⛔ WP_SUB_USER/WP_SUB_PASS (konto BEZ uprawnienia `upload_files`, np. subskrybent) są
# WYMAGANE. Brak danych kończy się kodem 2, nie cichym pominięciem kierunku E: strażnik,
# który przy braku danych świeci na zielono, mierzy własną wygodę, nie produkt.
#
# Kody wyjścia: 0 = wszystkie sześć kierunków zgodne · 1 = REGRESJA · 2 = środowisko/przyrząd
#
# ⛔ Licznik błędów poza podpowłoką (podpowłoka zjada zarówno licznik, jak i komunikat).
set -u

ADRES="${ADRES:-http://localhost:8890}"
KONTENER="${KONTENER:-strona_wp_wordpress}"
BLEDY=0
ROBOCZY="$(mktemp -d -t limit-uploadu-XXXXXX)"
CIASTKA="$ROBOCZY/ciastka.txt"
CIASTKA_SUB="$ROBOCZY/ciastka-sub.txt"
ZNACZNIK="STRAZNIK LIMITU $$-$(date +%s)"
# ⛔ SPRZĄTANIE TAKŻE PRZY PRZERWANIU. Bez tego Ctrl-C między kierunkiem D a blokiem
# sprzątania zostawiał produkt „STRAZNIK LIMITU…" w bazie stosu — a domyślnym celem jest
# stos WARSZTATOWY, wspólny z resztą zespołu.
trap 'posprzataj 2>/dev/null; rm -rf "$ROBOCZY"' EXIT INT TERM
posprzataj() {
	wp eval "
	\$w = \$GLOBALS['wpdb'];
	foreach ( \$w->get_results( \$w->prepare( \"SELECT id, plik_sciezka FROM {\$w->prefix}sklepag_produkty WHERE nazwa LIKE %s\", \$w->esc_like( '$ZNACZNIK' ) . '%' ) ) as \$p ) {
		if ( '' !== \$p->plik_sciezka && file_exists( \$p->plik_sciezka ) ) { wp_delete_file( \$p->plik_sciezka ); }
		\$w->delete( \$w->prefix . 'sklepag_produkty', array( 'id' => (int) \$p->id ), array( '%d' ) );
	}
	\$w->query( \$w->prepare( \"DELETE FROM {\$w->prefix}sklepag_zdarzenia WHERE typ = 'upload_produktu_blad' AND id > %d\", ${ID_PRZED:-0} ) );
	\$u = get_user_by( 'login', '$WP_ADMIN_USER' );
	if ( \$u ) { delete_transient( 'sklepag_szkic_produktu_' . (int) \$u->ID ); }
	echo 'posprzatane';
	" > /dev/null 2>&1
}


padnij()    { printf 'PRZYRZAD: %s\n' "$1" >&2; exit 2; }
zglos()     { printf '  ⛔ NIE WYSZLO  %s\n' "$1"; BLEDY=$(( BLEDY + 1 )); }
potwierdz() { printf '  ✅ WYSZLO      %s\n' "$1"; }

command -v curl > /dev/null || padnij "brak curl"
command -v docker > /dev/null || padnij "brak docker"
[ -n "${WP_ADMIN_USER:-}" ] && [ -n "${WP_ADMIN_PASS:-}" ] \
	|| padnij "brak WP_ADMIN_USER/WP_ADMIN_PASS w środowisku"
[ -n "${WP_SUB_USER:-}" ] && [ -n "${WP_SUB_PASS:-}" ] \
	|| padnij "brak WP_SUB_USER/WP_SUB_PASS (konto bez uprawnienia upload_files) — kierunku E nie da się zmierzyć, a milczące pominięcie byłoby fałszywą zielenią"
docker ps --filter "name=^${KONTENER}$" --filter status=running -q | grep -q . \
	|| padnij "kontener $KONTENER nie chodzi"

wp() { docker exec -u www-data "$KONTENER" wp --path=/var/www/html "$@" 2>&1; }

# --- limity SERWERA czytamy z serwera, nie z pamięci ---------------------------
POST_MAX="$(docker exec "$KONTENER" php -r '
$h = ini_get("post_max_size"); $n = (int) $h;
switch ( strtolower( substr( $h, -1 ) ) ) { case "g": $n *= 1024; case "m": $n *= 1024; case "k": $n *= 1024; }
echo $n;' 2>/dev/null)"
PLIK_MAX="$(docker exec "$KONTENER" php -r '
$h = ini_get("upload_max_filesize"); $n = (int) $h;
switch ( strtolower( substr( $h, -1 ) ) ) { case "g": $n *= 1024; case "m": $n *= 1024; case "k": $n *= 1024; }
echo $n;' 2>/dev/null)"
case "$POST_MAX" in ''|*[!0-9]*) padnij "nie umiem odczytać post_max_size z $KONTENER";; esac
case "$PLIK_MAX" in ''|*[!0-9]*) padnij "nie umiem odczytać upload_max_filesize z $KONTENER";; esac
[ "$POST_MAX" -gt "$PLIK_MAX" ] \
	|| padnij "post_max_size ($POST_MAX) nie jest większe od upload_max_filesize ($PLIK_MAX) — kierunku B nie da się na tym serwerze wywołać"

# Napisy porównujemy w formacie, którym posługuje się WordPress (size_format,
# polskie formatowanie liczb) — nie w swoim własnym.
NAPIS_POST="$(wp eval "echo size_format( $POST_MAX );" | tail -1 | tr -d '\r')"
NAPIS_PLIK="$(wp eval "echo size_format( $PLIK_MAX );" | tail -1 | tr -d '\r')"
[ -n "$NAPIS_POST" ] && [ -n "$NAPIS_PLIK" ] || padnij "wp eval nie oddał size_format — czy wtyczka/WP działa?"
printf 'Limity serwera %s: post_max_size=%s (%s B) · upload_max_filesize=%s (%s B)\n\n' \
	"$KONTENER" "$NAPIS_POST" "$POST_MAX" "$NAPIS_PLIK" "$PLIK_MAX"

# --- logowanie ------------------------------------------------------------------
curl -s -c "$CIASTKA" -b "$CIASTKA" -o /dev/null \
	--data-urlencode "log=$WP_ADMIN_USER" --data-urlencode "pwd=$WP_ADMIN_PASS" \
	-d 'wp-submit=Zaloguj&testcookie=1' "$ADRES/wp-login.php" \
	|| padnij "nie umiem wysłać logowania na $ADRES"

curl -s -c "$CIASTKA" -b "$CIASTKA" -o "$ROBOCZY/formularz.html" \
	"$ADRES/wp-admin/admin.php?page=sklepag-produkty&widok=formularz" \
	|| padnij "nie umiem pobrać formularza produktu"
grep -q 'name="plik"' "$ROBOCZY/formularz.html" \
	|| padnij "na $ADRES nie ma formularza produktu (zalogowano? wtyczka aktywna?)"

# ⛔ ADRES WYSYŁKI Z FORMULARZA, nie z pamięci strażnika. Gdyby strażnik dokleił
# `?action=…` sam, przechodziłby także na kodzie, w którym formularz tego nie robi —
# czyli nie mierzyłby tej wady wcale (ta sama pułapka, co druga kopia klucza
# idempotencji, PR #262).
CEL="$(sed -n 's/.*<form method="post" action="\([^"]*\)" enctype="multipart\/form-data".*/\1/p' "$ROBOCZY/formularz.html" | head -1 | sed 's/&#038;/\&/g;s/&amp;/\&/g')"
[ -n "$CEL" ] || padnij "nie umiem odczytać atrybutu action z formularza produktu"
NONCE="$(sed -n 's/.*name="_wpnonce" value="\([A-Za-z0-9]*\)".*/\1/p' "$ROBOCZY/formularz.html" | head -1)"
[ -n "$NONCE" ] || padnij "nie umiem odczytać nonce'a z formularza produktu"
printf 'Formularz wysyła na: %s\n\n' "$CEL"

# ⛔ ZNACZNIK DO SPRZĄTANIA. Strażnik kasuje po sobie WYŁĄCZNIE wpisy młodsze niż ten
# numer — `DELETE ... WHERE typ = 'upload_produktu_blad'` bez tego warunku zabrałby też
# CUDZE wpisy z dziennika (klasa 2. ze ściągi: operacja dotykająca nie swoich danych).
ID_PRZED="$(wp eval "\$w = \$GLOBALS['wpdb']; echo (int) \$w->get_var( \"SELECT COALESCE( MAX( id ), 0 ) FROM {\$w->prefix}sklepag_zdarzenia\" );" | tail -1 | tr -d '\r')"
case "$ID_PRZED" in ''|*[!0-9]*) padnij "nie umiem odczytać stanu dziennika przed przebiegiem";; esac

zbuduj_zip() { # $1 = plik docelowy, $2 = ile bajtów surowych w środku
	head -c "$2" /dev/urandom > "$ROBOCZY/dane.bin" || return 1
	python3 - "$1" "$ROBOCZY/dane.bin" <<'PY' || return 1
import sys, zipfile
z = zipfile.ZipFile(sys.argv[1], 'w', zipfile.ZIP_STORED)
z.write(sys.argv[2], 'dane.bin'); z.close()
PY
}

wyslij() { # $1 = plik zip, $2 = nonce, $3 = nazwa produktu; wypisuje: <http> <bajty> <Location>
	curl -s -b "$CIASTKA" -c "$CIASTKA" -o "$ROBOCZY/odp.html" -D "$ROBOCZY/nag.txt" \
		-w '%{http_code} %{size_download}' \
		-F 'action=sklepag_produkt_zapisz' -F 'produkt_id=0' -F "_wpnonce=$2" \
		-F "nazwa=$3" -F 'opis_krotki=straznik' -F 'opis_pelny=straznik' \
		-F 'cena=1.00' -F 'waluta=PLN' \
		-F "plik=@$1;type=application/zip" "$CEL"
	printf ' '
	tr -d '\r' < "$ROBOCZY/nag.txt" | sed -n 's/^[Ll]ocation: //p' | tail -1
}

tresc_widziana() { # co właściciel NAPRAWDĘ przeczyta: ciało odpowiedzi + rozkodowany komunikat z adresu
	cat "$ROBOCZY/odp.html"
	tr -d '\r' < "$ROBOCZY/nag.txt" | sed -n 's/^[Ll]ocation: //p' | tail -1 \
		| python3 -c 'import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read()))'
}

# ==== A) ciało PONAD post_max_size — PHP wyrzuca całe żądanie ====================
echo "== A) plik ponad post_max_size ($NAPIS_POST) — sklep MA powiedzieć prawdę o przyczynie =="
zbuduj_zip "$ROBOCZY/ponad-post.zip" "$(( POST_MAX + 1024 * 1024 ))" || padnij "nie umiem zbudować zipa dla kierunku A"
WYNIK_A="$(wyslij "$ROBOCZY/ponad-post.zip" "$NONCE" "$ZNACZNIK A")"
HTTP_A="${WYNIK_A%% *}"; RESZTA_A="${WYNIK_A#* }"; BAJTY_A="${RESZTA_A%% *}"
printf '    | http=%s bajtow_odpowiedzi=%s\n' "$HTTP_A" "$BAJTY_A"
WIDZI_A="$(tresc_widziana)"
if [ "$BAJTY_A" = "0" ] && [ -z "$(tr -d '\r' < "$ROBOCZY/nag.txt" | sed -n 's/^[Ll]ocation: //p')" ]; then
	zglos "A: odpowiedź PUSTA (http=$HTTP_A, 0 bajtów, bez Location) — właściciel dostaje białą stronę i ZERO informacji"
elif ! printf '%s' "$WIDZI_A" | grep -qF "$NAPIS_POST"; then
	zglos "A: w tym, co widzi właściciel, NIE MA realnego limitu serwera ($NAPIS_POST)"
elif ! printf '%s' "$WIDZI_A" | grep -qF 'post_max_size'; then
	zglos "A: komunikat nie nazywa ustawienia, które odrzuciło plik (post_max_size) — właściciel nie wie, co podnieść"
else
	potwierdz "A: prawda o przyczynie + realny limit serwera ($NAPIS_POST, post_max_size); http=$HTTP_A"
fi
echo

# ==== B) pojedynczy plik ponad upload_max_filesize, ciało w limicie ==============
echo "== B) plik ponad upload_max_filesize ($NAPIS_PLIK), ciało w limicie — komunikat MA cytować limit serwera =="
ROZMIAR_B=$(( PLIK_MAX + 512 * 1024 ))
[ "$ROZMIAR_B" -lt $(( POST_MAX - 256 * 1024 )) ] || padnij "nie mieszczę kierunku B między upload_max_filesize a post_max_size"
zbuduj_zip "$ROBOCZY/ponad-plik.zip" "$ROZMIAR_B" || padnij "nie umiem zbudować zipa dla kierunku B"
WYNIK_B="$(wyslij "$ROBOCZY/ponad-plik.zip" "$NONCE" "$ZNACZNIK B")"
HTTP_B="${WYNIK_B%% *}"
WIDZI_B="$(tresc_widziana)"
printf '    | http=%s\n' "$HTTP_B"
if ! printf '%s' "$WIDZI_B" | grep -qF "$NAPIS_PLIK"; then
	zglos "B: komunikat nie podaje limitu, który NAPRAWDĘ obowiązuje ($NAPIS_PLIK)"
elif ! printf '%s' "$WIDZI_B" | grep -q 'Wgranie pliku nie powiodło się (kod 1)'; then
	zglos "B: to nie jest ścieżka UPLOAD_ERR_INI_SIZE — strażnik mierzy co innego, niż myśli"
else
	potwierdz "B: komunikat cytuje limit serwera ($NAPIS_PLIK), nie limit wtyczki"
fi
echo

# ==== C) zwykły POST ze ZŁYM nonce'em — nonce nadal rządzi =======================
echo "== C) zwykły POST ze ZŁYM nonce'em — nowa gałąź NIE MOŻE go przepuścić ani przechwycić =="
zbuduj_zip "$ROBOCZY/maly.zip" 4096 || padnij "nie umiem zbudować małego zipa"
WYNIK_C="$(wyslij "$ROBOCZY/maly.zip" 'zepsuty000' "$ZNACZNIK C")"
HTTP_C="${WYNIK_C%% *}"
WIDZI_C="$(tresc_widziana)"
printf '    | http=%s\n' "$HTTP_C"
if printf '%s' "$WIDZI_C" | grep -qF 'post_max_size'; then
	zglos "C: zwykłe żądanie dostało komunikat o limicie serwera — nowa gałąź przechwytuje NIE SWOJE żądania"
elif [ "$HTTP_C" != "403" ]; then
	zglos "C: zły nonce NIE został odrzucony kodem 403 (http=$HTTP_C) — sprawdzenie nonce'a zostało ominięte"
else
	potwierdz "C: zły nonce nadal kończy się odmową 403, bez komunikatu o php.ini"
fi
echo

# ==== D) poprawny mały ZIP — wgrywanie MA dalej działać =========================
echo "== D) poprawny ZIP w limicie — produkt MA się zapisać z POTWIERDZONYM plikiem =="
WYNIK_D="$(wyslij "$ROBOCZY/maly.zip" "$NONCE" "$ZNACZNIK D")"
HTTP_D="${WYNIK_D%% *}"
WIDZI_D="$(tresc_widziana)"
printf '    | http=%s\n' "$HTTP_D"
POTW="$(wp eval "
\$w = \$GLOBALS['wpdb'];
\$p = \$w->get_row( \$w->prepare( \"SELECT * FROM {\$w->prefix}sklepag_produkty WHERE nazwa = %s\", '$ZNACZNIK D' ) );
if ( ! \$p ) { echo 'BRAK-WIERSZA'; }
elseif ( '' === \$p->plik_sciezka || ! file_exists( \$p->plik_sciezka ) ) { echo 'BRAK-PLIKU'; }
else { echo 'POTWIERDZONY ' . (int) \$p->plik_rozmiar; }
" | tail -1 | tr -d '\r')"
printf '    | z bazy: %s\n' "$POTW"
case "$POTW" in
	POTWIERDZONY*) potwierdz "D: produkt zapisany, plik na dysku ($POTW) — naprawa nie odebrała wgrywania" ;;
	*)             printf '    | co odpowiedzial sklep: %s\n' "$( printf '%s' "$WIDZI_D" | tr '\n' ' ' | cut -c1-400 )"
	               zglos "D: poprawny ZIP NIE przeszedł ($POTW, http=$HTTP_D) — naprawa zabrała funkcję wszystkim" ;;
esac

# ==== E) SUBSKRYBENT — konfiguracja serwera NIE MA do niego wyciekać ============
echo "== E) ucięte żądanie od konta BEZ uprawnienia upload_files ($WP_SUB_USER) — MA zostać bez odpowiedzi =="
curl -s -c "$CIASTKA_SUB" -b "$CIASTKA_SUB" -o /dev/null \
	--data-urlencode "log=$WP_SUB_USER" --data-urlencode "pwd=$WP_SUB_PASS" \
	-d 'wp-submit=Zaloguj&testcookie=1' "$ADRES/wp-login.php" \
	|| padnij "nie umiem zalogować konta $WP_SUB_USER"
# ⛔ Sprawdzamy, że sesja NAPRAWDĘ powstała. Niezalogowany curl trafiłby na `admin_post_nopriv`,
# gdzie odpowiedzi i tak nie ma — i kierunek E świeciłby na zielono, nie mierząc niczego.
KIM="$(curl -s -b "$CIASTKA_SUB" "$ADRES/wp-admin/profile.php" | grep -c 'wp-admin-bar-my-account\|profile-page')"
[ "$KIM" -gt 0 ] || padnij "konto $WP_SUB_USER nie zalogowało się — kierunek E nie zmierzyłby niczego"
# ⛔ Wysyłamy na GOŁE `admin-post.php`, bez `action` — to jest DOKŁADNIE ta droga, którą
# obsługuje ogólne zaczepienie `admin_post`. Adres z `action` trafiłby do handlera produktu,
# który ma własne sprawdzenie uprawnień i odpowiedziałby 403 z innego powodu.
WYNIK_E="$(curl -s -b "$CIASTKA_SUB" -c "$CIASTKA_SUB" -o "$ROBOCZY/odp.html" -D "$ROBOCZY/nag.txt" \
	-w '%{http_code} %{size_download}' \
	-F 'produkt_id=0' -F 'nazwa=nieistotne' \
	-F "plik=@$ROBOCZY/ponad-post.zip;type=application/zip" \
	"$ADRES/wp-admin/admin-post.php")"
HTTP_E="${WYNIK_E%% *}"
WIDZI_E="$(tresc_widziana)"
printf '    | http=%s\n' "$HTTP_E"
if printf '%s' "$WIDZI_E" | grep -qF 'post_max_size'; then
	zglos "E: subskrybent DOSTAŁ konfigurację serwera (post_max_size/upload_max_filesize) — wyciek ustawień do konta bez uprawnień do wgrywania"
elif printf '%s' "$WIDZI_E" | grep -qF "$NAPIS_POST"; then
	zglos "E: subskrybent zobaczył limit serwera ($NAPIS_POST) — konfiguracja wycieka niżej, niż deklaruje dokumentacja"
else
	potwierdz "E: konto bez upload_files nie dostaje żadnej informacji o konfiguracji serwera (http=$HTTP_E)"
fi
echo

# ==== F) mały POST application/json — PHP NICZEGO nie uciął ====================
echo "== F) mały POST 'application/json' na gołe admin-post.php — NIE MA prawa dostać diagnozy o ucięciu =="
WYNIK_F="$(curl -s -b "$CIASTKA" -c "$CIASTKA" -o "$ROBOCZY/odp.html" -D "$ROBOCZY/nag.txt" \
	-w '%{http_code} %{size_download}' \
	-H 'Content-Type: application/json' --data-binary '{"cokolwiek":"male ciało, ktore dojechalo w calosci"}' \
	"$ADRES/wp-admin/admin-post.php")"
HTTP_F="${WYNIK_F%% *}"
WIDZI_F="$(tresc_widziana)"
printf '    | http=%s\n' "$HTTP_F"
# ⛔ JEDEN warunek i JEDEN komunikat porażki. Dwa warianty („ogłosił ucięcie" / „wykład
# o post_max_size") opisywały tę samą wadę, a próba kontrolna musiała zgadywać, który
# padnie — i raz się pomyliła. Wystarczy ślad konfiguracji serwera w odpowiedzi.
if printf '%s' "$WIDZI_F" | grep -qF 'post_max_size'; then
	zglos "F: żądanie, którego PHP NIE uciął, dostało diagnozę o ucięciu i konfigurację serwera — ciało dojechało w całości i leży w php://input (zmyślona przyczyna)"
else
	potwierdz "F: nieucięte żądanie nie dostaje zmyślonej diagnozy (http=$HTTP_F)"
fi
echo

# --- sprzątanie po sobie: wiersze, plik i przechowalnia szkicu ------------------
# ⛔ SZKIC KASUJEMY POD IDENTYFIKATOREM WŁAŚCICIELA, NIE POD ZEREM. `wp eval` chodzi tu bez
# `--user`, więc `get_current_user_id()` zwraca 0, a przechowalnię zakłada handler w żądaniu
# HTTP zalogowanego właściciela — pod JEGO numerem. Runda 1 kasowała klucz
# `sklepag_szkic_produktu_0`, czyli nie kasowała niczego, a szkic „STRAZNIK LIMITU…" dożywał
# 5 minut i nakładał się właścicielowi na formularz (uwaga 5. recenzji).
posprzataj

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "STRAZNIK: WYNIK=OK — A, B, C, D, E i F zgodne z oczekiwaniem"
	exit 0
fi
echo "STRAZNIK: WYNIK=FAIL — $BLEDY z 6 kierunkow nie wyszlo (REGRESJA)"
exit 1
