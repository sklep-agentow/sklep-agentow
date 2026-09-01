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
# CZTERY KIERUNKI, bo „naprawa", która zaczyna przechwytywać wszystko albo przestaje
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
#
# Mierzy PRODUKT przez HTTP: adres wysyłki bierze z atrybutu `action` PRAWDZIWEGO
# formularza, nie składa go u siebie — inaczej nie zauważyłby, że formularz stracił
# `action` razem z ciałem żądania.
#
# Użycie:
#   set -a; . /root/.claude/sklep-admin-roboczy.env; set +a
#   bash testy/limit-uploadu-mowi-prawde.sh
#   ADRES=http://localhost:8891 KONTENER=probny_wp WP_ADMIN_USER=probny \
#     WP_ADMIN_PASS=probne-haslo-QA bash testy/limit-uploadu-mowi-prawde.sh
#
# Kody wyjścia: 0 = wszystkie cztery kierunki zgodne · 1 = REGRESJA · 2 = środowisko/przyrząd
#
# ⛔ Licznik błędów poza podpowłoką (podpowłoka zjada zarówno licznik, jak i komunikat).
set -u

ADRES="${ADRES:-http://localhost:8890}"
KONTENER="${KONTENER:-strona_wp_wordpress}"
BLEDY=0
ROBOCZY="$(mktemp -d -t limit-uploadu-XXXXXX)"
CIASTKA="$ROBOCZY/ciastka.txt"
ZNACZNIK="STRAZNIK LIMITU $$-$(date +%s)"
trap 'rm -rf "$ROBOCZY"' EXIT INT TERM

padnij()    { printf 'PRZYRZAD: %s\n' "$1" >&2; exit 2; }
zglos()     { printf '  ⛔ NIE WYSZLO  %s\n' "$1"; BLEDY=$(( BLEDY + 1 )); }
potwierdz() { printf '  ✅ WYSZLO      %s\n' "$1"; }

command -v curl > /dev/null || padnij "brak curl"
command -v docker > /dev/null || padnij "brak docker"
[ -n "${WP_ADMIN_USER:-}" ] && [ -n "${WP_ADMIN_PASS:-}" ] \
	|| padnij "brak WP_ADMIN_USER/WP_ADMIN_PASS w środowisku"
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

# --- sprzątanie po sobie: wiersze, plik i przechowalnia szkicu ------------------
wp eval "
\$w = \$GLOBALS['wpdb'];
foreach ( \$w->get_results( \$w->prepare( \"SELECT id, plik_sciezka FROM {\$w->prefix}sklepag_produkty WHERE nazwa LIKE %s\", \$w->esc_like( '$ZNACZNIK' ) . '%' ) ) as \$p ) {
	if ( '' !== \$p->plik_sciezka && file_exists( \$p->plik_sciezka ) ) { wp_delete_file( \$p->plik_sciezka ); }
	\$w->delete( \$w->prefix . 'sklepag_produkty', array( 'id' => (int) \$p->id ), array( '%d' ) );
}
\$w->query( \$w->prepare( \"DELETE FROM {\$w->prefix}sklepag_zdarzenia WHERE typ = 'upload_produktu_blad' AND id > %d\", $ID_PRZED ) );
delete_transient( 'sklepag_szkic_produktu_' . get_current_user_id() );
echo 'posprzatane';
" > /dev/null 2>&1

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "STRAZNIK: WYNIK=OK — A, B, C i D zgodne z oczekiwaniem"
	exit 0
fi
echo "STRAZNIK: WYNIK=FAIL — $BLEDY z 4 kierunkow nie wyszlo (REGRESJA)"
exit 1
