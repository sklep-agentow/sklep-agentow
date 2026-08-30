#!/bin/bash
# plugin-check-paczki.sh — RAPORT (nie bramka) z oficjalnego Plugin Check WordPress.org, liczony NA PACZKACH.  WERSJA 1.0.0
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
# Użycie: bash narzedzia-qa/plugin-check-paczki.sh <zip> [<zip> ...]
#   Wymaga stojącego `probny_wp` — najpierw:  bash narzedzia-qa/k7-stos-rundy.sh <pierwszy-zip>
#   Zapisuje surowe wyniki do audyt/plugin-check/<slug>.txt (nadpisuje) i drukuje podsumowanie.
#   EXIT 0 = wszystkie raporty zapisane · 1 = któryś przebieg Plugin Check nie zwrócił wyniku
#            · 2 = zła komenda / brak zipa / nie stoi probny_wp
set -u
KORZEN="$(cd "$(dirname "$0")/.." && pwd)"
KONTENER=probny_wp
WYJ="$KORZEN/audyt/plugin-check"
[ $# -ge 1 ] || { echo "użycie: $0 <zip> [<zip> ...]" >&2; exit 2; }
docker ps --format '{{.Names}}' | grep -qx "$KONTENER" || { echo "PLUGIN CHECK: nie stoi $KONTENER — najpierw: bash narzedzia-qa/k7-stos-rundy.sh <zip>" >&2; exit 2; }
mkdir -p "$WYJ"

wp() { docker exec "$KONTENER" wp --allow-root --path=/var/www/html "$@" 2>&1; }

if ! wp plugin is-active plugin-check > /dev/null; then
	wp plugin install plugin-check --activate | tail -1
fi
echo "Plugin Check $(wp plugin get plugin-check --field=version) · stos: WP $(wp core version) / PHP $(docker exec "$KONTENER" php -r 'echo PHP_VERSION;')"

BLEDY=0
for zip in "$@"; do
	[ -f "$zip" ] || { echo "PLUGIN CHECK: brak pliku $zip" >&2; exit 2; }
	nazwa="$(basename "$zip" .zip)"
	slug="$(printf '%s' "$nazwa" | sed -E 's/-[0-9][0-9.]*$//')"
	docker cp "$zip" "$KONTENER:/tmp/$nazwa.zip"
	wp plugin install "/tmp/$nazwa.zip" --force --activate | tail -1
	wp plugin check "$slug" --format=table > "$WYJ/$slug.txt"
	if ! grep -qE '^(FILE:|line)' "$WYJ/$slug.txt"; then
		echo "  ⛔ $slug: Plugin Check nie zwrócił tabeli — patrz $WYJ/$slug.txt"; BLEDY=$(( BLEDY + 1 )); continue
	fi
	e=$(grep -c $'\tERROR\t' "$WYJ/$slug.txt"); w=$(grep -c $'\tWARNING\t' "$WYJ/$slug.txt")
	echo "  $slug ($nazwa): ERROR=$e WARNING=$w → audyt/plugin-check/$slug.txt"
	grep $'\tERROR\t' "$WYJ/$slug.txt" | cut -f4 | sort | uniq -c | sort -rn | sed 's/^/      ERROR /'
done
echo "PLUGIN CHECK: raporty zapisane, EXIT=$([ "$BLEDY" -eq 0 ] && echo 0 || echo 1) — liczby czytaj z triage, nie porównuj z zerem"
exit $([ "$BLEDY" -eq 0 ] && echo 0 || echo 1)
