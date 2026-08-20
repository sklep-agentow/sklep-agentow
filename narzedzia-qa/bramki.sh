#!/bin/bash
# bramki.sh — WSZYSTKIE bramki jakości, po kolei, w jednym procesie.
#
# PO CO TO POWSTAŁO (oszczędzanie minut CI): GitHub liczy KAŻDE zadanie osobno
# i zaokrągla w górę do pełnej minuty. Sześć zadań po ~7 sekund kosztowało sześć
# minut za czterdzieści sekund liczenia. Jedno zadanie = jedna minuta.
#
# ⛔ NIEBEZPIECZEŃSTWO, KTÓRE TO RODZI: kurczący się zestaw bramek wygląda
# dokładnie jak sukces. Sześć zielonych zadań widać na liście; jedno zadanie,
# które po cichu przestało wołać phpstana, wygląda tak samo zielono jak takie,
# które go wołało. Dlatego ten skrypt LICZY wykonane bramki, wypisuje ich
# liczbę i PADA, gdy spadnie poniżej progu dla danego trybu. Brak narzędzia
# to PORAŻKA bramki, nigdy ciche pominięcie.
#
# Ten sam plik biegnie w CI i na maszynie buildera — gdy Actions stoją (a stały
# 20.08 na wyczerpanym koncie), `bash narzedzia-qa/bramki.sh` daje ten sam wynik
# i ten sam kod wyjścia, który wkleja się do opisu PR-a.
#
# Użycie:
#   bash narzedzia-qa/bramki.sh                # tryb wykrywany z listy zmian
#   bash narzedzia-qa/bramki.sh --tryb pelny   # wymuś komplet
#   bash narzedzia-qa/bramki.sh --tryb tekstowy
#   ZMIENIONE_PLIKI="a.md b.md" bash narzedzia-qa/bramki.sh
#
# Kody wyjścia: 0 = wszystkie wykonane bramki zielone i jest ich tyle, ile ma być
#               1 = któraś bramka czerwona ALBO wykonano ich za mało
set -u

# ⛔ Rejestr bramek. Nazwy MUSZĄ zgadzać się z .github/manifest-bramek.txt —
# pilnuje tego narzedzia-qa/sprawdz-manifest.sh. Kolejność jak w quality.yml
# sprzed zbicia zadań: najpierw tanie i statyczne, na końcu skan historii.
BRAMKI=(actionlint manifest php-lint phpcs phpstan gitleaks)
# Bramki dotykające WYŁĄCZNIE kodu PHP — pomijane, gdy zmiana jest tekstowa.
BRAMKI_PHP=(php-lint phpcs phpstan)
PROG_PELNY=6
PROG_TEKSTOWY=3

# Wersje narzędzi pobieranych, gdy nie ma ich w PATH. Pinowane świadomie:
# „najnowsza" w bramce oznacza, że CI potrafi zmienić zdanie bez naszej zmiany.
WERSJA_ACTIONLINT=1.7.7
WERSJA_GITLEAKS=8.24.3
CACHE="${TMPDIR:-/tmp}/bramki-narzedzia"

TRYB=auto
while [ $# -gt 0 ]; do
	case "$1" in
		--tryb) TRYB="${2:-auto}"; shift 2 ;;
		*) echo "Nieznany argument: $1" >&2; exit 2 ;;
	esac
done

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2

WYKONANE=0
PORAZKI=0
POMINIETE=()
declare -A WYNIKI=()

# --- narzędzia zewnętrzne -----------------------------------------------------

# Pobiera narzędzie do cache, gdy nie ma go w PATH. Nieudane pobranie NIE jest
# pominięciem bramki — bramka zostanie policzona jako porażka.
zapewnij_narzedzie() {
	local nazwa="$1" url="$2" plik_w_archiwum="$3"
	if command -v "$nazwa" > /dev/null 2>&1; then
		command -v "$nazwa"
		return 0
	fi
	if [ -x "$CACHE/$nazwa" ]; then
		echo "$CACHE/$nazwa"
		return 0
	fi
	mkdir -p "$CACHE" || return 1
	if ! curl -sSfL "$url" -o "$CACHE/paczka.tgz" 2> /dev/null; then
		return 1
	fi
	if ! tar xzf "$CACHE/paczka.tgz" -C "$CACHE" "$plik_w_archiwum" 2> /dev/null; then
		return 1
	fi
	chmod +x "$CACHE/$plik_w_archiwum"
	echo "$CACHE/$plik_w_archiwum"
}

# --- tryb: pełny czy tekstowy -------------------------------------------------

lista_zmian() {
	if [ -n "${ZMIENIONE_PLIKI:-}" ]; then
		printf '%s\n' $ZMIENIONE_PLIKI
		return 0
	fi
	local baza=""
	if [ -n "${GITHUB_BASE_REF:-}" ]; then
		baza="origin/$GITHUB_BASE_REF"
	elif git rev-parse --verify -q origin/main > /dev/null; then
		baza="origin/main"
	fi
	[ -n "$baza" ] || return 1
	git diff --name-only "$baza"...HEAD 2> /dev/null
}

# Tekstowa = KAŻDY zmieniony plik to .md albo .txt. Wszystko inne (w tym .php,
# .sh, .js, .yml, pliki bez rozszerzenia) przełącza na komplet bramek. Gdy nie
# da się ustalić listy zmian — komplet. Wątpliwość zawsze na korzyść kontroli.
wykryj_tryb() {
	local zmiany
	zmiany="$(lista_zmian)" || { echo pelny; return; }
	[ -n "$zmiany" ] || { echo pelny; return; }
	local plik
	while IFS= read -r plik; do
		[ -n "$plik" ] || continue
		case "$plik" in
			*.md|*.txt) ;;
			*) echo pelny; return ;;
		esac
	done <<< "$zmiany"
	echo tekstowy
}

if [ "$TRYB" = "auto" ]; then
	TRYB="$(wykryj_tryb)"
fi
case "$TRYB" in
	pelny) PROG=$PROG_PELNY ;;
	tekstowy) PROG=$PROG_TEKSTOWY ;;
	*) echo "Nieznany tryb: $TRYB (dozwolone: pelny, tekstowy, auto)" >&2; exit 2 ;;
esac

# --- pojedyncza bramka --------------------------------------------------------

pomijana() {
	local nazwa="$1"
	[ "$TRYB" = "tekstowy" ] || return 1
	local php
	for php in "${BRAMKI_PHP[@]}"; do
		[ "$php" = "$nazwa" ] && return 0
	done
	return 1
}

bramka() {
	local nazwa="$1"; shift
	if pomijana "$nazwa"; then
		POMINIETE+=("$nazwa")
		printf '\n--- BRAMKA %s: POMINIETA (tryb tekstowy — zmiana nie dotyka PHP) ---\n' "$nazwa"
		return 0
	fi
	printf '\n--- BRAMKA %s ---\n' "$nazwa"
	local kod=0
	"$@" || kod=$?
	WYKONANE=$((WYKONANE + 1))
	WYNIKI[$nazwa]=$kod
	if [ "$kod" -eq 0 ]; then
		printf 'BRAMKA %s: EXIT=0\n' "$nazwa"
	else
		printf 'BRAMKA %s: EXIT=%d — PORAZKA\n' "$nazwa" "$kod"
		PORAZKI=$((PORAZKI + 1))
	fi
}

# --- implementacje bramek (te same polecenia co w sześciu starych zadaniach) ---

uruchom_actionlint() {
	local bin
	bin="$(zapewnij_narzedzie actionlint \
		"https://github.com/rhysd/actionlint/releases/download/v${WERSJA_ACTIONLINT}/actionlint_${WERSJA_ACTIONLINT}_linux_amd64.tar.gz" \
		actionlint)" || { echo "BRAK actionlinta i nie udalo sie go pobrac" >&2; return 1; }
	"$bin" .github/workflows/*.yml
}

uruchom_manifest() {
	bash narzedzia-qa/sprawdz-manifest.sh
}

uruchom_php_lint() {
	find wtyczki -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null || return 1
	echo "Skladnia czysta."
}

uruchom_phpcs() {
	[ -x vendor/bin/phpcs ] || { echo "BRAK vendor/bin/phpcs — czy composer install przeszedl?" >&2; return 1; }
	vendor/bin/phpcs
}

uruchom_phpstan() {
	[ -x vendor/bin/phpstan ] || { echo "BRAK vendor/bin/phpstan — czy composer install przeszedl?" >&2; return 1; }
	vendor/bin/phpstan analyse --memory-limit=1G --no-progress
}

uruchom_gitleaks() {
	local bin
	bin="$(zapewnij_narzedzie gitleaks \
		"https://github.com/gitleaks/gitleaks/releases/download/v${WERSJA_GITLEAKS}/gitleaks_${WERSJA_GITLEAKS}_linux_x64.tar.gz" \
		gitleaks)" || { echo "BRAK gitleaksa i nie udalo sie go pobrac" >&2; return 1; }
	"$bin" git --no-banner --redact .
}

# --- przebieg -----------------------------------------------------------------

echo "=== BRAMKI JAKOSCI — tryb: $TRYB, prog: $PROG z ${#BRAMKI[@]} ==="

bramka actionlint uruchom_actionlint
bramka manifest   uruchom_manifest
bramka php-lint   uruchom_php_lint
bramka phpcs      uruchom_phpcs
bramka phpstan    uruchom_phpstan
bramka gitleaks   uruchom_gitleaks

# --- podsumowanie i strażnik liczby -------------------------------------------

echo
echo "=== PODSUMOWANIE ==="
for nazwa in "${BRAMKI[@]}"; do
	if [ -n "${WYNIKI[$nazwa]:-}" ]; then
		printf '  %-12s EXIT=%s\n' "$nazwa" "${WYNIKI[$nazwa]}"
	else
		printf '  %-12s POMINIETA (tryb %s)\n' "$nazwa" "$TRYB"
	fi
done

echo
echo "WYKONANE BRAMKI: $WYKONANE z ${#BRAMKI[@]} (tryb: $TRYB, wymagane minimum: $PROG)"
[ ${#POMINIETE[@]} -eq 0 ] || echo "POMINIETE SWIADOMIE: ${POMINIETE[*]}"

if [ "$WYKONANE" -lt "$PROG" ]; then
	echo "STOP: wykonano $WYKONANE bramek, a tryb $TRYB wymaga co najmniej $PROG." >&2
	echo "      Kurczacy sie zestaw bramek NIE JEST sukcesem — sprawdz, ktora wypadla." >&2
	exit 1
fi

if [ "$PORAZKI" -gt 0 ]; then
	echo "NIEZALICZONE BRAMKI: $PORAZKI" >&2
	exit 1
fi

echo "WSZYSTKIE WYKONANE BRAMKI ZIELONE."
exit 0
