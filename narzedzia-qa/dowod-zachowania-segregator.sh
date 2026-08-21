#!/bin/bash
# dowod-zachowania-segregator.sh — DOWÓD ZACHOWANIA Segregatora Z PACZKI.   WERSJA 1.0.0
#
# `dowod-z-paczki.sh` odpowiada na pytania o CYKL ŻYCIA wtyczki: instalacja,
# tryb regułowy bez klucza, deinstalacja obiema ścieżkami, sekrety. Ten skrypt
# odpowiada na trzy pytania o ZACHOWANIE NA SKRZYNCE, których tamten nie zadaje,
# a które są w punkcie D listy domknięcia (`boty/NA-OSTATNI-GUZIK-BOTY.md`):
#
#   D1. Przebieg NA SUCHO ma pokazać, co zrobiłby, i NIE RUSZYĆ ani jednej
#       wiadomości. To jest wymóg produktu, nie preferencja — domyślny tryb.
#   D2. Przebieg NA ŻYWO ma przenieść wiadomość do folderu kategorii, a cofnięcie
#       ma ją wrócić do folderu źródłowego.
#   D3. Retencja ma NAPRAWDĘ kasować (wpis sprzed 91 dni znika), a wpisu
#       świeższego niż próg RUSZYĆ NIE MOŻE — inaczej „naprawa" kasuje wszystkim.
#
# ⛔ ZERO ZAUFANIA DO WŁASNEGO KLIENTA IMAP. Stan skrzynki mierzymy NIEZALEŻNIE,
#    Pythonem (`imaplib`) z zewnątrz, a nie klasą `Segm_Imap` z wtyczki. Test,
#    który mierzy tym samym kodem, który sprawdza, potwierdza sam siebie.
#
# ⛔ NIE DOTYKA STOSU WARSZTATOWEGO ANI JEGO SKRZYNKI. Stawia własną instalację
#    (kontener `probny_wp`, baza `wp_probny`) przez lib-stos-probny.sh ORAZ własny
#    serwer poczty (`probny_imap`, greenmail) i kasuje oba. Skrzynka `segm_imap`,
#    na której pracuje builder 2, zostaje nietknięta.
#
# Użycie:
#   bash narzedzia-qa/dowod-zachowania-segregator.sh
#   bash narzedzia-qa/dowod-zachowania-segregator.sh boty/paczki/segregator-maila-1.3.0.zip
#
# Kody wyjścia: 0 = wszystkie trzy dowody wyszły
#               1 = co najmniej jeden nie wyszedł
#               2 = brak narzędzia, stos nie wstał, zła paczka
set -u

WERSJA=1.0.0
KATALOG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-stos-probny.sh
. "$KATALOG/lib-stos-probny.sh"

PACZKA="${1:-boty/paczki/segregator-maila-1.3.0.zip}"
[ -f "$PACZKA" ] || padnij "nie ma paczki [$PACZKA]"
WTYCZKA=segregator-maila

IMAP_KONTENER=probny_imap
IMAP_OBRAZ=greenmail/standalone:2.1.3
IMAP_PORT_HOST=31430
SMTP_PORT_HOST=30250
SKRZYNKA_LOGIN=jan@example.com
SKRZYNKA_HASLO=tajne-haslo-probne

printf '=== DOWOD ZACHOWANIA SEGREGATORA %s ===\n' "$WERSJA"
printf '  paczka:  %s\n' "$PACZKA"
printf '  suma:    %s\n' "$(sha256sum "$PACZKA" | cut -d' ' -f1)"

command -v python3 > /dev/null || padnij "brak python3 — nim mierzę skrzynkę NIEZALEŻNIE od wtyczki"
wymagaj_narzedzi

# --- serwer poczty próbny ------------------------------------------------------

zwin_poczte() { docker rm -f "$IMAP_KONTENER" > /dev/null 2>&1 || true; }
posprzataj() { zwin_poczte; zwin_stos; rm -rf "$ROBOCZY"; }

# ⛔ Skrypty Pythona ida do PLIKOW, nie do heredoków: `<<-` zjada wiodace tabulatory
# RAZEM z wcieciem kodu, a Python bez wciecia to skladniowo inny program. Pierwsza
# wersja tej proby wywalala sie na IndentationError, pomiar wracal PUSTY — i puste
# porownanie "przed == po" wychodzilo na ZIELONO. Dowod, ktory przechodzi, bo nic
# nie zmierzyl, jest gorszy niz brak dowodu.
ROBOCZY="$(mktemp -d)"
trap posprzataj EXIT

cat > "$ROBOCZY/zmierz.py" << 'PY'
import imaplib, sys
port, login, haslo = int(sys.argv[1]), sys.argv[2], sys.argv[3]
m = imaplib.IMAP4("127.0.0.1", port)
m.login(login, haslo)
ok, dane = m.list()
for wiersz in dane:
    nazwa = wiersz.decode().split(' "." ')[-1].strip('"')
    ok, ile = m.select('"%s"' % nazwa, readonly=True)
    print("%s|%s" % (nazwa, int(ile[0]) if ok == "OK" else "?"))
m.logout()
PY

cat > "$ROBOCZY/wyslij.py" << 'PY'
import smtplib, sys
from email.message import EmailMessage
port, do = int(sys.argv[1]), sys.argv[2]
maile = [
    ("faktury@hurtownia-omega.pl", "Faktura VAT FV/2026/08/311", "W zalaczeniu faktura za zamowienie 4581."),
    ("kontakt@klient-abc.pl", "Zapytanie o wspolprace", "Dzien dobry, chcielibysmy poznac oferte."),
    ("newsletter@sklep-xyz.pl", "Nowosci tygodnia", "Zobacz nasze promocje."),
]
s = smtplib.SMTP("127.0.0.1", port)
for od, temat, tresc in maile:
    w = EmailMessage()
    w["From"], w["To"], w["Subject"] = od, do, temat
    w.set_content(tresc)
    s.send_message(w)
s.quit()
print(len(maile))
PY

postaw_poczte() {
	zwin_poczte
	docker run -d --name "$IMAP_KONTENER" --network "$SIEC" \
		-p "$IMAP_PORT_HOST:3143" -p "$SMTP_PORT_HOST:3025" \
		-e GREENMAIL_OPTS='-Dgreenmail.setup.test.all -Dgreenmail.hostname=0.0.0.0 -Dgreenmail.auth.disabled -Dgreenmail.verbose' \
		"$IMAP_OBRAZ" > /dev/null 2>&1 || padnij "nie umiem postawic serwera poczty $IMAP_OBRAZ"
	local i
	for i in $(seq 1 40); do
		# ⛔ Samo TCP connect NIE WYSTARCZA: docker-proxy przyjmuje polaczenie, zanim
		# greenmail zdazy sie podpiac pod port, i zwraca EOF. Czekamy na POWITANIE
		# protokolu (SMTP 220) — pierwsza wersja tej petli wpuszczala skrypt za wczesnie
		# i wysylka maili padala na "Connection unexpectedly closed".
		if python3 -c "
import socket
s = socket.create_connection(('127.0.0.1', $SMTP_PORT_HOST), 2)
s.settimeout(2)
raise SystemExit(0 if s.recv(4).startswith(b'220') else 1)
" > /dev/null 2>&1; then
			return 0
		fi
		sleep 1
	done
	padnij "serwer poczty nie wstal w 40 s"
}

zmierz_skrzynke() { python3 "$ROBOCZY/zmierz.py" "$IMAP_PORT_HOST" "$SKRZYNKA_LOGIN" "$SKRZYNKA_HASLO"; }
wyslij_maile()    { python3 "$ROBOCZY/wyslij.py" "$SMTP_PORT_HOST" "$SKRZYNKA_LOGIN"; }

ile_w_folderze() { printf '%s\n' "$1" | awk -F'|' -v f="$2" '$1 == f { print $2 }'; }

# --- stos i wtyczka ------------------------------------------------------------

postaw_stos
docker cp "$PACZKA" "$KONTENER:/tmp/paczka.zip" > /dev/null || padnij "nie umiem wgrac zipa do kontenera"
wp plugin install /tmp/paczka.zip --force --activate > /dev/null || padnij "wtyczka nie wstala z paczki"
postaw_poczte
ILE_WYSLANYCH="$(wyslij_maile)"
printf '  poczta:  %s (%s), wyslanych wiadomosci: %s\n' "$IMAP_KONTENER" "$IMAP_OBRAZ" "$ILE_WYSLANYCH"

# Konfiguracja skrzynki + JEDNA regula: nadawca zawiera "faktury@" => kategoria Faktury.
wp eval '
	$u = segm_skrzynka_ustawienia();
	$u["host"] = "probny_imap"; $u["port"] = 3143; $u["szyfrowanie"] = "brak";
	$u["login"] = "'"$SKRZYNKA_LOGIN"'"; $u["folder_zrodlowy"] = "INBOX";
	$u["prefiks"] = "Segregator"; $u["tryb"] = "na_sucho"; $u["limit"] = 50;
	update_option( "segm_skrzynka", $u );
	segm_skrzynka_zapisz_haslo( "'"$SKRZYNKA_HASLO"'" );
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}segm_reguly" );
	$k = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = \"Faktury\"" );
	if ( ! $k ) { $wpdb->insert( $wpdb->prefix . "segm_kategorie", array( "nazwa" => "Faktury", "kolejnosc" => 1 ) ); $k = $wpdb->insert_id; }
	$wpdb->insert( $wpdb->prefix . "segm_reguly", array( "kategoria_id" => $k, "pole" => "od", "wzorzec" => "faktury@", "kolejnosc" => 1 ) );
	echo "konfiguracja zapisana, kategoria=", (int) $k;
' > /dev/null || padnij "nie umiem skonfigurowac skrzynki w probnej instalacji"

# --- DOWOD 1: NA SUCHO nie rusza poczty ---------------------------------------

naglowek "DOWOD 1 — przebieg NA SUCHO nie rusza ani jednej wiadomosci"
PRZED="$(zmierz_skrzynke)"
# Bez tej bramki puste porownanie "" = "" wychodzi na zielono i dowod klamie.
ILE_INBOX="$(ile_w_folderze "$PRZED" INBOX)"
case "${ILE_INBOX:-x}" in
	''|*[!0-9]*) padnij "pomiar skrzynki nic nie zwrocil — nie ma czego porownywac (INBOX=[${ILE_INBOX:-brak}])" ;;
esac
[ "$ILE_INBOX" -ge 3 ] || padnij "w INBOX jest $ILE_INBOX wiadomosci, a wyslalem $ILE_WYSLANYCH — poczta probna nie przyjela maili"
printf '    skrzynka PRZED: %s\n' "$(printf '%s' "$PRZED" | tr '\n' ' ')"
wyczysc_log
WYNIK_SUCHO="$(wp eval '$p = segm_skrzynka_przebieg( "dowod" ); echo wp_json_encode( array( "id" => $p["id"] ?? null, "tryb" => $p["tryb"] ?? "", "przejrzanych" => $p["przejrzanych"] ?? null, "dopasowanych" => $p["dopasowanych"] ?? null ) );')"
printf '    przebieg: %s\n' "$WYNIK_SUCHO"
PO_SUCHO="$(zmierz_skrzynke)"
printf '    skrzynka PO:    %s\n' "$(printf '%s' "$PO_SUCHO" | tr '\n' ' ')"

if [ "$PRZED" = "$PO_SUCHO" ]; then
	potwierdz "skrzynka NIETKNIETA — ten sam zestaw folderow i ta sama liczba wiadomosci w kazdym"
else
	zglos "przebieg na sucho ZMIENIL skrzynke"
	diff <(printf '%s\n' "$PRZED") <(printf '%s\n' "$PO_SUCHO") | sed 's/^/       /'
fi
case "$WYNIK_SUCHO" in
	*'"tryb":"na_sucho"'*) potwierdz "przebieg zapisal sie jako na_sucho (tryb domyslny, bez przelaczania)" ;;
	*) zglos "przebieg NIE jest oznaczony jako na_sucho: $WYNIK_SUCHO" ;;
esac
BLEDY_PHP="$(log_bledow)"
[ -z "$BLEDY_PHP" ] && potwierdz "przebieg na sucho bez ani jednego bledu PHP" \
	|| { zglos "przebieg na sucho zglosil bledy PHP"; printf '%s\n' "$BLEDY_PHP" | sed 's/^/       /'; }

# --- DOWOD 2: NA ZYWO przenosi i daje sie cofnac ------------------------------

naglowek "DOWOD 2 — przebieg NA ZYWO przenosi, a cofniecie wraca"
wp eval '
	$u = segm_skrzynka_ustawienia(); $u["tryb"] = "na_zywo"; update_option( "segm_skrzynka", $u );
	update_option( "segm_zgoda_przenoszenie", array( "kiedy" => current_time( "mysql" ), "kto" => "dowod-zachowania" ) );
	echo segm_przenoszenie_dozwolone() ? "na_zywo=TAK" : "na_zywo=NIE";
' > /dev/null
wyczysc_log
WYNIK_ZYWO="$(wp eval '$p = segm_skrzynka_przebieg( "dowod" ); echo wp_json_encode( array( "id" => $p["id"] ?? null, "tryb" => $p["tryb"] ?? "", "przeniesionych" => $p["przeniesionych"] ?? null, "dopasowanych" => $p["dopasowanych"] ?? null ) );')"
printf '    przebieg: %s\n' "$WYNIK_ZYWO"
PO_ZYWO="$(zmierz_skrzynke)"
printf '    skrzynka PO przebiegu: %s\n' "$(printf '%s' "$PO_ZYWO" | tr '\n' ' ')"

INBOX_PRZED="$(ile_w_folderze "$PRZED" INBOX)"
INBOX_PO="$(ile_w_folderze "$PO_ZYWO" INBOX)"
FOLDER_CEL="$(printf '%s\n' "$PO_ZYWO" | awk -F'|' '/Faktury/ { print $1; exit }')"
ILE_W_CELU="$(ile_w_folderze "$PO_ZYWO" "$FOLDER_CEL")"

if [ -n "$FOLDER_CEL" ] && [ "${ILE_W_CELU:-0}" -ge 1 ] && [ "${INBOX_PO:-0}" -lt "${INBOX_PRZED:-0}" ]; then
	potwierdz "wiadomosc przeniesiona: INBOX $INBOX_PRZED -> $INBOX_PO, w [$FOLDER_CEL] jest $ILE_W_CELU"
else
	zglos "przeniesienia NIE widac na skrzynce (INBOX $INBOX_PRZED -> ${INBOX_PO:-?}, folder celu [${FOLDER_CEL:-brak}] = ${ILE_W_CELU:-0})"
fi

ID_PRZEBIEGU="$(printf '%s' "$WYNIK_ZYWO" | sed -n 's/.*"id":\([0-9]*\).*/\1/p')"
if [ -n "$ID_PRZEBIEGU" ]; then
	WYNIK_COFAM="$(wp eval "\$c = segm_skrzynka_cofnij( $ID_PRZEBIEGU ); echo wp_json_encode( is_array( \$c ) ? array_intersect_key( \$c, array_flip( array( 'cofnietych', 'bledow' ) ) ) : \$c );")"
	printf '    cofniecie: %s\n' "$WYNIK_COFAM"
	PO_COFNIECIU="$(zmierz_skrzynke)"
	printf '    skrzynka PO cofnieciu: %s\n' "$(printf '%s' "$PO_COFNIECIU" | tr '\n' ' ')"
	if [ "$(ile_w_folderze "$PO_COFNIECIU" INBOX)" = "$INBOX_PRZED" ]; then
		potwierdz "cofniecie wrocilo wiadomosc do INBOX — znowu $INBOX_PRZED"
	else
		zglos "po cofnieciu INBOX ma $(ile_w_folderze "$PO_COFNIECIU" INBOX), a przed przebiegiem mial $INBOX_PRZED"
	fi
else
	zglos "przebieg na zywo nie zwrocil id — nie ma czego cofac"
fi
BLEDY_PHP="$(log_bledow)"
[ -z "$BLEDY_PHP" ] && potwierdz "przebieg na zywo i cofniecie bez ani jednego bledu PHP" \
	|| { zglos "przebieg na zywo zglosil bledy PHP"; printf '%s\n' "$BLEDY_PHP" | sed 's/^/       /'; }

# --- DOWOD 3: retencja naprawde kasuje ----------------------------------------

naglowek "DOWOD 3 — retencja kasuje wpis sprzed 91 dni, a swiezego NIE RUSZA"
LICZBY="$(wp eval '
	global $wpdb;
	$stary  = gmdate( "Y-m-d H:i:s", time() - 91 * DAY_IN_SECONDS );
	$swiezy = gmdate( "Y-m-d H:i:s", time() - 3 * DAY_IN_SECONDS );
	$wpdb->insert( $wpdb->prefix . "segm_maile", array( "nadawca" => "stary@example.com", "temat" => "sprzed 91 dni", "tresc" => "x", "utworzono" => $stary ) );
	$wpdb->insert( $wpdb->prefix . "segm_maile", array( "nadawca" => "swiezy@example.com", "temat" => "sprzed 3 dni", "tresc" => "x", "utworzono" => $swiezy ) );
	$przed_s = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile WHERE nadawca = \"stary@example.com\"" );
	$przed_n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile WHERE nadawca = \"swiezy@example.com\"" );
	segm_czyszczenie_probki();
	$po_s = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile WHERE nadawca = \"stary@example.com\"" );
	$po_n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile WHERE nadawca = \"swiezy@example.com\"" );
	echo wp_json_encode( array( "prog_dni" => SEGM_RETENCJA_DNI, "stary_przed" => $przed_s, "stary_po" => $po_s, "swiezy_przed" => $przed_n, "swiezy_po" => $po_n ) );
')"
printf '    %s\n' "$LICZBY"
case "$LICZBY" in
	*'"stary_przed":1'*'"stary_po":0'*) potwierdz "wpis sprzed 91 dni ZNIKNAL (1 -> 0)" ;;
	*) zglos "wpis sprzed 91 dni NIE zostal skasowany: $LICZBY" ;;
esac
case "$LICZBY" in
	*'"swiezy_przed":1'*'"swiezy_po":1'*) potwierdz "wpis sprzed 3 dni ZOSTAL (1 -> 1) — retencja nie kasuje wszystkiego" ;;
	*) zglos "retencja ruszyla wpis SWIEZSZY niz prog: $LICZBY" ;;
esac

# --- werdykt -------------------------------------------------------------------

naglowek "WYNIK"
if [ "$BLEDY" -eq 0 ]; then
	printf 'PACZKA %s: ZACHOWANIE NA SKRZYNCE POTWIERDZONE (na sucho, na zywo, cofniecie, retencja).\n' "$(basename "$PACZKA")"
	exit 0
fi
printf 'PACZKA %s: %d dowod(ow) NIE WYSZLO — szczegoly wyzej.\n' "$(basename "$PACZKA")" "$BLEDY"
exit 1
