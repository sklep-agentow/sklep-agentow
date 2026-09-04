#!/bin/bash
# k7-stos-rundy.sh — CZYSTY stos pod rundę audytu K7 (PLAN-K7 §9): wordpress:6.7-php8.1 + paczka.  WERSJA 1.0.2
#
# Stawia `probny_wp` (:8891, baza `wp_probny` na wspólnym MariaDB) z `lib-stos-probny.sh`, wgrywa PODANY zip
# (runda A = paczka z mutantami, runda B = zamrożona 0.9.13), ustawia permalinki, zakłada konta audytorów
# z haseł w /root/.claude/sekrety-audyt-{admin,sub}.txt i DOWODZI, że w kontenerze jest ta paczka (sha256
# zipa + odcisk podanego wzorca w kodzie). Stos ZOSTAJE (nie zwija).
# ⛔ SPRZATANIE: `zwin_stos` z lib dziala WYLACZNIE w procesie, ktory stos postawil
#    (`lib-stos-probny.sh:151` wychodzi wczesniej, gdy STOS_PROBNY_POSTAWILEM != tak),
#    a ten skrypt konczy sie z ZOSTAW=tak — wiec z innego procesu nie zrobi nic.
#    Recznie potrzebne sa DWIE komendy, bo `zwin_stos` robi dwie rzeczy
#    (`lib-stos-probny.sh:159-160`), a sam `docker rm` zostawia baze:
#      docker rm -f -v probny_wp
#      docker exec <kontener-bazy> mysql -uroot -p<haslo> -e "DROP DATABASE IF EXISTS \`wp_probny\`;"
#    (nazwe kontenera bazy i haslo czytaj z `narzedzia-qa/lib-stos-probny.sh` — nie przepisuj
#    z pamieci; ta sciezka to jedyne zrodlo prawdy). ⛔ Do 3.09 stala tu SAMA pierwsza komenda
#    i podpis „sprzatanie", co bylo nieprawda: baza `wp_probny` zostawala (recenzja #272 r5,
#    DROBNA 5). ⛔ Flaga `-v` jest OBOWIAZKOWA: obraz WordPressa deklaruje VOLUME
#    /var/www/html, wiec `docker rm -f` bez niej zostawia wolumen (zmierzone 2.09: 83 wiszace
#    wolumeny, 6,5 GB, dysk 100 %).
# Użycie: bash narzedzia-qa/k7-stos-rundy.sh <zip> [wzorzec-w-kodzie-do-potwierdzenia]
#   EXIT 0 = stos stoi, wtyczka aktywna w podanej wersji, wzorzec (jeśli podany) w kodzie kontenera, oba konta z OCZEKIWANĄ rolą
#            i DZIAŁAJĄCYM hasłem (`wp user check-password`), strona główna HTTP 200, debug.log pusty PO instalacji, aktywacji
#            i renderze strony · 1 = coś nie wyszło · 2 = zła komenda / brak pliku zip / brak sekretu `/root/.claude/sekrety-audyt-*.txt`
#            / brak narzędzia z `wymagaj_narzedzi` (docker, unzip; brak `curl` = EXIT 1 przy teście HTTP)
#            / NIE WIEM (to konto nie widzi Dockera — komunikat zaczyna się od „NIE WIEM”) — to nie jest „stos nie stoi”
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP="${1:-}"; WZORZEC="${2:-}"
[ -n "$ZIP" ] && [ -f "$ZIP" ] || { echo "Użycie: bash narzedzia-qa/k7-stos-rundy.sh <zip> [wzorzec]" >&2; exit 2; }
export STOS_PROBNY_OBRAZ="${STOS_PROBNY_OBRAZ:-wordpress:6.7-php8.1-apache}"
export ZOSTAW=tak
# shellcheck source=lib-stos-probny.sh
. "$KORZEN/narzedzia-qa/lib-stos-probny.sh"

for konto in admin sub; do [ -s "/root/.claude/sekrety-audyt-$konto.txt" ] || { echo "k7-stos-rundy: brak/pusty sekret /root/.claude/sekrety-audyt-$konto.txt" >&2; exit 2; }; done
wymagaj_narzedzi
postaw_stos
docker cp "$ZIP" "$KONTENER:/tmp/paczka.zip"
wp plugin install /tmp/paczka.zip --force --activate | tail -2
wp rewrite structure '/%postname%/' --hard > /dev/null 2>&1
wp option update blog_public 0 > /dev/null 2>&1
for konto in admin sub; do
	haslo="$(tr -d '\n' < "/root/.claude/sekrety-audyt-$konto.txt")"
	rola=administrator; [ "$konto" = sub ] && rola=subscriber
	wp user create "audytor-$konto" "audytor-$konto@example.com" --role="$rola" --user_pass="$haslo" > /dev/null 2>&1 \
		|| wp user update "audytor-$konto" --user_pass="$haslo" > /dev/null 2>&1
done

naglowek "DOWODY"
SHA_ZIP=$(sha256sum "$ZIP" | cut -d' ' -f1)
printf '  paczka: %s\n  sha256: %s\n' "$ZIP" "$SHA_ZIP"
printf '  WP %s · PHP %s · obraz %s\n' "$(wp core version | tr -d '\r')" "$(docker exec "$KONTENER" php -r 'echo PHP_VERSION;')" "$OBRAZ"
AKT=$(wp plugin list --status=active --field=name | tr -d '\r' | grep -c '^sklep-agentow$')
[ "$AKT" -eq 1 ] && potwierdz "sklep-agentow AKTYWNA" || zglos "sklep-agentow nieaktywna"
WER=$(wp plugin get sklep-agentow --field=version | tr -d '\r'); printf '  wersja wtyczki w kontenerze: %s\n' "$WER"
# Bramka „aktywna w PODANEJ wersji” (recenzja #187 r2): wersja z nagłówka pliku głównego w zipie == wersja w kontenerze.
WER_ZIP=$(unzip -p "$ZIP" 'sklep-agentow/sklep-agentow.php' 2>/dev/null | grep -m1 -E '^[[:space:]*]*Version:' | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')
[ -n "$WER_ZIP" ] && [ "$WER_ZIP" = "$WER" ] && potwierdz "wersja w kontenerze = wersja z zipa ($WER_ZIP)" || zglos "wersja: zip „${WER_ZIP:-?}” vs kontener „$WER”"
if [ -n "$WZORZEC" ]; then
	N=$(docker exec "$KONTENER" grep -rF "$WZORZEC" /var/www/html/wp-content/plugins/sklep-agentow/includes/ | wc -l)
	[ "$N" -ge 1 ] && potwierdz "wzorzec „$WZORZEC” w kodzie kontenera ($N)" || zglos "wzorca „$WZORZEC” NIE MA w kontenerze"
fi
# ⛔ wrapper wp() z lib scala stderr do stdout — „niepuste” nie znaczy „jest” (recenzja #187 WAŻNA): porównujemy z OCZEKIWANĄ rolą
# i dowodzimy hasła `wp user check-password` (kod wyjścia), nie samą obecnością konta.
for konto in admin sub; do
	u="audytor-$konto"; ocz=administrator; [ "$konto" = sub ] && ocz=subscriber
	r=$(wp user get "$u" --field=roles | tr -d '\r')
	[ "$r" = "$ocz" ] && potwierdz "konto $u = $ocz" || zglos "konto $u: oczekiwano $ocz, jest „$r”"
	if wp user check-password "$u" "$(tr -d '\n' < "/root/.claude/sekrety-audyt-$konto.txt")" > /dev/null; then potwierdz "hasło $u z sekretu DZIAŁA"; else zglos "hasło $u z sekretu NIE działa"; fi
done
KOD=$(curl -s -o /dev/null -w '%{http_code}' "$ADRES/"); [ "$KOD" = 200 ] && potwierdz "strona główna HTTP $KOD ($ADRES)" || zglos "strona główna HTTP $KOD"
# debug.log PO renderze strony (front też może rzucić notice — recenzja #187)
LOG=$(docker exec "$KONTENER" sh -c 'cat /var/www/html/debug.log 2>/dev/null' | grep -cE 'Notice|Warning|Deprecated|Fatal')
[ "$LOG" -eq 0 ] && potwierdz "debug.log po instalacji+aktywacji+renderze: 0 notice/warning/fatal" || { zglos "debug.log: $LOG wpisów"; docker exec "$KONTENER" tail -5 /var/www/html/debug.log; }

echo
if [ "$BLEDY" -eq 0 ]; then echo "STOS RUNDY STOI: $KONTENER $ADRES · paczka $SHA_ZIP"; exit 0; fi
echo "STOS RUNDY: $BLEDY problemów"; exit 1
