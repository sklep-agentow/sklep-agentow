#!/bin/bash
# plugin-check-paczki.sh — RAPORT (nie bramka) z oficjalnego Plugin Check WordPress.org, liczony NA PACZKACH.  WERSJA 1.1.0
#
# PO CO TO POWSTAŁO (30.08): Plugin Check stał w naszym szablonie CI od 9.08, a w tym repo nie było go
# nigdzie — ani w CI (wykonawca własny, 3,8 GB RAM, drugi stos WP w CI = OOM), ani w narzędziach.
# Ten skrypt uruchamia go w STOSIE PRÓBNYM (`probny_wp`, stawia `k7-stos-rundy.sh`), na zipach,
# które dostaje klient — nie na repo. Wynik jest RAPORTEM: Plugin Check mierzy reguły katalogu
# WordPress.org (readme po angielsku, pliki w korzeniu), których wtyczka dla jednego klienta nie musi
# spełniać, i nie czyta naszego `phpcs.xml.dist` (własne sanitizery, brama nonce, wyłączone sniffy
# DirectDatabaseQuery dla botów). Dlatego liczby trzeba PRZECZYTAĆ, nie porównać z zerem —
# triage z 30.08 leży w `audyt/PLUGIN-CHECK-30-08.md`.
#
# 1.1.0 (recenzja PR o tym raporcie, W3): raport jest PRZYWIĄZANY do wersji paczki — wersja zainstalowana
# w kontenerze musi równać się wersji z nazwy zipa, inaczej wtyczka jest pomijana z błędem (bez tego
# nieudana instalacja dawała cichy raport z tego, co akurat siedziało pod slugiem). Każdy raport zaczyna się
# wierszem `# PLUGIN CHECK: <slug> <wersja> · zip <nazwa> · WP <x> / PHP <y> · <data>`.
#
# Użycie: bash narzedzia-qa/plugin-check-paczki.sh <zip> [<zip> ...]
#   Wymaga stojącego `probny_wp` — najpierw (obraz jak w triage 30.08; domyślny obraz k7-stos-rundy to 6.7/8.1):
#     STOS_PROBNY_OBRAZ=wordpress:6.9.4-php8.4-apache bash narzedzia-qa/k7-stos-rundy.sh <pierwszy-zip>
#   Zapisuje surowe wyniki do audyt/plugin-check/<slug>.txt (nadpisuje) i drukuje podsumowanie.
#   EXIT 0 = wszystkie raporty zapisane · 1 = któraś paczka nie została zainstalowana w podanej wersji albo
#            Plugin Check nie zwrócił wyniku (raport tej paczki NIE jest zapisywany) · 2 = zła komenda / brak zipa / nie stoi probny_wp
set -u
KORZEN="$(cd "$(dirname "$0")/.." && pwd)"
KONTENER=probny_wp
WYJ="$KORZEN/audyt/plugin-check"
[ $# -ge 1 ] || { echo "użycie: $0 <zip> [<zip> ...]" >&2; exit 2; }
docker ps --format '{{.Names}}' | grep -qx "$KONTENER" || { echo "PLUGIN CHECK: nie stoi $KONTENER — najpierw: STOS_PROBNY_OBRAZ=wordpress:6.9.4-php8.4-apache bash narzedzia-qa/k7-stos-rundy.sh <zip>" >&2; exit 2; }
mkdir -p "$WYJ"

wp() { docker exec "$KONTENER" wp --allow-root --path=/var/www/html "$@" 2>&1; }

if ! wp plugin is-active plugin-check > /dev/null; then
	wp plugin install plugin-check --activate | tail -1
fi
WP_WER="$(wp core version)"; PHP_WER="$(docker exec "$KONTENER" php -r 'echo PHP_VERSION;')"
echo "Plugin Check $(wp plugin get plugin-check --field=version) · stos: WP $WP_WER / PHP $PHP_WER"

BLEDY=0
for zip in "$@"; do
	[ -f "$zip" ] || { echo "PLUGIN CHECK: brak pliku $zip" >&2; exit 2; }
	nazwa="$(basename "$zip" .zip)"
	slug="$(printf '%s' "$nazwa" | sed -E 's/-[0-9][0-9.]*$//')"
	wersja_zip="${nazwa##*-}"
	if ! docker cp "$zip" "$KONTENER:/tmp/$nazwa.zip"; then
		echo "  ⛔ $slug: docker cp nie wyszło — pomijam"; BLEDY=$(( BLEDY + 1 )); continue
	fi
	inst="$(wp plugin install "/tmp/$nazwa.zip" --force --activate)"; kod=$?
	wersja_kont="$(wp plugin get "$slug" --field=version 2>/dev/null | tr -d '[:space:]')"
	if [ "$kod" -ne 0 ] || [ "$wersja_kont" != "$wersja_zip" ]; then
		echo "  ⛔ $slug: instalacja EXIT=$kod, w kontenerze wersja '${wersja_kont:-brak}', zip obiecuje '$wersja_zip' — raport NIE zapisany"
		printf '%s\n' "$inst" | tail -3 | sed 's/^/      /'
		BLEDY=$(( BLEDY + 1 )); continue
	fi
	tmp="$(mktemp)"
	wp plugin check "$slug" --format=table > "$tmp"
	if ! grep -qE '^(FILE:|line)' "$tmp"; then
		echo "  ⛔ $slug: Plugin Check nie zwrócił tabeli — raport NIE zapisany"; rm -f "$tmp"; BLEDY=$(( BLEDY + 1 )); continue
	fi
	{
		printf '# PLUGIN CHECK: %s %s · zip %s.zip · WP %s / PHP %s · %s\n' "$slug" "$wersja_kont" "$nazwa" "$WP_WER" "$PHP_WER" "$(date '+%Y-%m-%d %H:%M')"
		cat "$tmp"
	} > "$WYJ/$slug.txt"
	rm -f "$tmp"
	e=$(grep -c $'\tERROR\t' "$WYJ/$slug.txt"); w=$(grep -c $'\tWARNING\t' "$WYJ/$slug.txt")
	echo "  $slug $wersja_kont ($nazwa.zip): ERROR=$e WARNING=$w → audyt/plugin-check/$slug.txt"
	grep $'\tERROR\t' "$WYJ/$slug.txt" | cut -f4 | sort | uniq -c | sort -rn | sed 's/^/      ERROR /'
done
echo "PLUGIN CHECK: raporty zapisane dla $(( $# - BLEDY )) z $# paczek, EXIT=$([ "$BLEDY" -eq 0 ] && echo 0 || echo 1) — liczby czytaj z triage, nie porównuj z zerem"
exit $([ "$BLEDY" -eq 0 ] && echo 0 || echo 1)
