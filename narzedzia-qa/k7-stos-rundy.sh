#!/bin/bash
# k7-stos-rundy.sh — CZYSTY stos pod rundę audytu K7 (PLAN-K7 §9): wordpress:6.7-php8.1 + paczka.  WERSJA 1.0.0
#
# Stawia `probny_wp` (:8891, baza `wp_probny` na wspólnym MariaDB) z `lib-stos-probny.sh`, wgrywa PODANY zip
# (runda A = paczka z mutantami, runda B = zamrożona 0.9.13), ustawia permalinki, zakłada konta audytorów
# z haseł w /root/.claude/sekrety-audyt-{admin,sub}.txt i DOWODZI, że w kontenerze jest ta paczka (sha256
# zipa + odcisk podanego wzorca w kodzie). Stos ZOSTAJE (nie zwija) — zwija `zwin_stos` z lib albo
# `docker rm -f probny_wp`.
# Użycie: bash narzedzia-qa/k7-stos-rundy.sh <zip> [wzorzec-w-kodzie-do-potwierdzenia]
#   EXIT 0 = stos stoi, wtyczka aktywna, konta są, debug.log pusty po aktywacji · 1 = coś nie wyszło · 2 = zła komenda
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP="${1:-}"; WZORZEC="${2:-}"
[ -n "$ZIP" ] && [ -f "$ZIP" ] || { echo "Użycie: bash narzedzia-qa/k7-stos-rundy.sh <zip> [wzorzec]" >&2; exit 2; }
export STOS_PROBNY_OBRAZ="${STOS_PROBNY_OBRAZ:-wordpress:6.7-php8.1-apache}"
export ZOSTAW=tak
# shellcheck source=lib-stos-probny.sh
. "$KORZEN/narzedzia-qa/lib-stos-probny.sh"

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
if [ -n "$WZORZEC" ]; then
	N=$(docker exec "$KONTENER" grep -rF "$WZORZEC" /var/www/html/wp-content/plugins/sklep-agentow/includes/ | wc -l)
	[ "$N" -ge 1 ] && potwierdz "wzorzec „$WZORZEC” w kodzie kontenera ($N)" || zglos "wzorca „$WZORZEC” NIE MA w kontenerze"
fi
for u in audytor-admin audytor-sub; do
	r=$(wp user get "$u" --field=roles 2>/dev/null | tr -d '\r'); [ -n "$r" ] && potwierdz "konto $u ($r)" || zglos "brak konta $u"
done
LOG=$(docker exec "$KONTENER" sh -c 'cat /var/www/html/debug.log 2>/dev/null' | grep -cE 'Notice|Warning|Deprecated|Fatal')
[ "$LOG" -eq 0 ] && potwierdz "debug.log po aktywacji: 0 notice/warning/fatal" || { zglos "debug.log: $LOG wpisów"; docker exec "$KONTENER" tail -5 /var/www/html/debug.log; }
KOD=$(curl -s -o /dev/null -w '%{http_code}' "$ADRES/"); [ "$KOD" = 200 ] && potwierdz "strona główna HTTP $KOD ($ADRES)" || zglos "strona główna HTTP $KOD"

echo
if [ "$BLEDY" -eq 0 ]; then echo "STOS RUNDY STOI: $KONTENER $ADRES · paczka $SHA_ZIP"; exit 0; fi
echo "STOS RUNDY: $BLEDY problemów"; exit 1
