#!/bin/bash
# instalacja-przez-panel-probny.sh — I-26: KROK 1 instrukcji klienta przechodzony PRZEZ PANEL WordPressa, nie wp-cli.
#
# PO CO: `dowod-z-paczki.sh` i K6/K9/K10 instalowały paczkę przez `wp plugin install`, omijając formularz
# „Wtyczki → Dodaj nową → Wyślij na serwer” i limity uploadu PHP. Krok 1 instrukcji (`dokumentacja/INSTRUKCJA-INSTALACJI.md`)
# każe klientowi iść właśnie przez ten formularz — więc ta droga musi być ZMIERZONA żądaniami HTTP jak robi to
# przeglądarka: logowanie → nonce z plugin-install.php?tab=upload → POST zipa do update.php?action=upload-plugin →
# aktywacja linkiem z odpowiedzi → strony „Sklep” i „Dziękujemy” istnieją.
#
# DWA POMIARY, bo instrukcja mówi dwie rzeczy i obie mają być prawdą:
#   A. domyślny hosting (upload_max_filesize=2M, obraz WordPressa bez zmian): panel MA ODRZUCIĆ zip
#      dokładnie tym komunikatem, który instrukcja zapowiada („przekracza dyrektywę upload_max_filesize”);
#   B. hosting spełniający „Wymagania” instrukcji (limit co najmniej 8 MB): Krok 1 MA PRZEJŚĆ do końca.
#
# Użycie: bash narzedzia-qa/stanowisko/instalacja-przez-panel-probny.sh [zip=boty/paczki/sklep-agentow-<wersja>.zip]
#   Stawia stos próbny od zera (lib-stos-probny.sh, port 8891, admin: probny / probne-haslo-QA) i zwija go po sobie.
#   EXIT 0 = A odrzucony zapowiedzianym komunikatem I B wgrany+aktywny+strony · 1 = któryś pomiar nie wyszedł · 2 = brak narzędzi/stosu.
set -u
cd "$(dirname "$0")/../.." || exit 2
# shellcheck source=../lib-stos-probny.sh
. narzedzia-qa/lib-stos-probny.sh
ZIP="${1:-$(ls boty/paczki/sklep-agentow-*.zip | sort -V | tail -1)}"
[ -f "$ZIP" ] || { echo "PANEL: brak zipa $ZIP" >&2; exit 2; }
command -v curl > /dev/null || { echo "PANEL: brak curl" >&2; exit 2; }
CJ="$(mktemp)"; ODP="$(mktemp)"; BLEDY=0
sprzataj() { rm -f "$CJ" "$ODP"; zwin_stos 2> /dev/null || true; }
trap sprzataj EXIT
ok()  { printf '  OK   %s\n' "$1"; }
zle() { printf '  ZLE  %s\n' "$1"; BLEDY=$((BLEDY + 1)); }

zaloguj() {
	: > "$CJ"
	curl -s -c "$CJ" -b "$CJ" -o /dev/null "$ADRES/wp-login.php"
	local kod
	kod="$(curl -s -c "$CJ" -b "$CJ" -o "$ODP" -w '%{http_code}' --data-urlencode "log=probny" --data-urlencode "pwd=probne-haslo-QA" \
		--data-urlencode "wp-submit=Zaloguj" --data-urlencode "redirect_to=$ADRES/wp-admin/" --data-urlencode "testcookie=1" "$ADRES/wp-login.php")"
	grep -q "wordpress_logged_in" "$CJ" && ok "zalogowano do panelu (HTTP $kod, ciastko sesji)" || zle "logowanie padło (HTTP $kod)"
}
nonce_uploadu() {
	curl -s -c "$CJ" -b "$CJ" -o "$ODP" "$ADRES/wp-admin/plugin-install.php?tab=upload"
	NONCE="$(grep -oE 'name="_wpnonce" value="[a-f0-9]+"' "$ODP" | head -1 | grep -oE '[a-f0-9]{6,}')"
	[ -n "$NONCE" ] && ok "formularz Wtyczki → Dodaj nową → Wyślij na serwer ma nonce" || zle "brak nonce w formularzu uploadu"
}
wyslij_zip() {  # ustawia WYNIK (ODRZUCONY | ZAINSTALOWANY | INNE) i KOD (HTTP) — bez podpowloki, zeby KOD byl widoczny
	KOD="$(curl -s -c "$CJ" -b "$CJ" -o "$ODP" -w '%{http_code}' -F "_wpnonce=$NONCE" -F "_wp_http_referer=/wp-admin/plugin-install.php?tab=upload" \
		-F "pluginzip=@$ZIP;type=application/zip" -F "install-plugin-submit=Zainstaluj" "$ADRES/wp-admin/update.php?action=upload-plugin")"
	if grep -qiE "przekracza|exceeds|upload_max_filesize" "$ODP"; then WYNIK=ODRZUCONY
	elif grep -qiE "zainstalowan|installed successfully|Plugin installed" "$ODP"; then WYNIK=ZAINSTALOWANY
	else WYNIK=INNE; fi
}
KOD=0; WYNIK=INNE
limity() { docker exec "$KONTENER" php -r 'echo ini_get("upload_max_filesize"), "/", ini_get("post_max_size");' 2>/dev/null; }

echo "=== I-26 KROK 1 PRZEZ PANEL: stos probny od zera ($ZIP, $(stat -c %s "$ZIP") B) ==="
postaw_stos || { echo "PANEL: stos probny nie wstal" >&2; exit 2; }
# Stos próbny stawia pliki jako root (docker cp), a prawdziwy hosting daje PHP zapisywalny wp-content/ — bez tego
# WordPress kończy „The uploaded file could not be moved to wp-content/uploads/…” (zmierzone 5.09: wada ŚRODOWISKA, nie produktu).
docker exec "$KONTENER" chown -R www-data:www-data /var/www/html/wp-content

echo "=== A. domyslny hosting: limity PHP $(limity) — panel MA odrzucic zip komunikatem z instrukcji ==="
zaloguj; nonce_uploadu
wyslij_zip
case "$WYNIK" in
	ODRZUCONY) ok "panel odrzucił zip (HTTP $KOD): komunikat o upload_max_filesize — dokładnie to, co zapowiada instrukcja (Wymagania + Najczęstsze problemy)" ;;
	ZAINSTALOWANY) zle "przy limicie $(limity) panel PRZYJĄŁ zip — instrukcja opisuje odmowę, pomiar jej nie widzi" ;;
	*) zle "nieoczekiwana odpowiedź przy limicie 2M (HTTP $KOD): $(grep -oiE '<p>[^<]{0,120}' "$ODP" | head -1)" ;;
esac

echo "=== B. hosting wg Wymagan instrukcji (limit >= 8 MB): Krok 1 MA przejsc do konca ==="
docker exec "$KONTENER" sh -c 'printf "upload_max_filesize=8M\npost_max_size=8M\n" > /usr/local/etc/php/conf.d/zz-limity-i26.ini' \
	&& docker restart "$KONTENER" > /dev/null && for _ in $(seq 1 30); do curl -s -o /dev/null "$ADRES/wp-login.php" && break; sleep 1; done
echo "  limity PHP po zmianie: $(limity)"
zaloguj; nonce_uploadu
wyslij_zip
case "$WYNIK" in
	ZAINSTALOWANY) ok "panel zainstalował wtyczkę z zipa (HTTP $KOD) przy limicie $(limity)" ;;
	ODRZUCONY) zle "przy limicie $(limity) panel nadal odrzuca zip (HTTP $KOD)" ;;
	*) zle "nieoczekiwana odpowiedź przy limicie 8M (HTTP $KOD): $(grep -oiE '<p>[^<]{0,120}' "$ODP" | head -1)" ;;
esac
AKT="$(grep -oE 'plugins\.php\?[^"]*action=activate[^"]*' "$ODP" | head -1 | sed 's/&amp;/\&/g')"
if [ -n "$AKT" ]; then
	KOD="$(curl -s -c "$CJ" -b "$CJ" -o "$ODP" -w '%{http_code}' -L "$ADRES/wp-admin/$AKT")"
	STAN="$(docker exec -u www-data "$KONTENER" wp --path=/var/www/html plugin list --name=sklep-agentow --field=status 2>/dev/null)"
	[ "$STAN" = "active" ] && ok "wtyczka aktywna po kliknieciu linku Wlacz wtyczke (HTTP $KOD)" || zle "po aktywacji linkiem status=$STAN (HTTP $KOD)"
else
	zle "odpowiedź panelu nie ma linku aktywacji"
fi
# Strony zakładane przez aktywację: wtyczka pamięta ich ID w opcjach sklepag_strona_* — mierzymy TO, co wtyczka sama uznaje
# za swoje strony (slug bywa inny niż nazwa, np. „Dziękujemy za zakup”), i czy każdy wpis istnieje.
ILE_STRON=0
for opcja in sklepag_strona_sklep sklepag_strona_dziekujemy sklepag_strona_regulamin sklepag_strona_polityka; do
	id="$(docker exec -u www-data "$KONTENER" wp --path=/var/www/html option get "$opcja" 2>/dev/null)"
	TYT="$( [ -n "$id" ] && docker exec -u www-data "$KONTENER" wp --path=/var/www/html post get "$id" --field=post_title 2>/dev/null )"
	if [ -n "$TYT" ]; then ok "strona z aktywacji: $opcja -> ID $id ($TYT)"; ILE_STRON=$((ILE_STRON + 1)); else zle "$opcja: brak strony (id=${id:-puste})"; fi
done
[ "$ILE_STRON" -ge 2 ] && ok "aktywacja założyła $ILE_STRON stron (>= 2: Sklep i Dziękujemy)" || zle "aktywacja założyła $ILE_STRON stron (oczekiwano >= 2)"
LOG="$(docker exec "$KONTENER" sh -c 'test -f /var/www/html/wp-content/debug.log && grep -c "PHP " /var/www/html/wp-content/debug.log || echo 0' 2>/dev/null)"
[ "${LOG:-0}" = "0" ] && ok "debug.log bez wpisów PHP" || zle "debug.log: $LOG wpisów PHP"

echo
if [ "$BLEDY" -eq 0 ]; then echo "I-26 KROK 1 PRZEZ PANEL: A (2M) odrzucony zapowiedzianym komunikatem, B (8M) wgrany, aktywny, strony sa. EXIT=0"; exit 0; fi
echo "I-26 KROK 1 PRZEZ PANEL: $BLEDY punktow NIE WYSZLO. EXIT=1" >&2; exit 1
