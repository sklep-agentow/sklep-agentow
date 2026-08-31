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

# Bramka `slady` (dołożona 21.08 przy zamrażaniu ChatBota 1.5.7): pilnuje, żeby do
# plików WTYCZEK nie wjeżdżały nasze ślady wewnętrzne — TODO, adres maszyny dev,
# odsyłacz do `testy/…`, numer naszego zgłoszenia. Powód jest z życia: paczka 1.5.7
# wiozła kupującemu 20 takich śladów i ŻADNA bramka tego nie widziała, bo zip był
# równy repozytorium co do bajtu, a ślad leżał po obu stronach tak samo.
# Chodzi też przy zmianach tekstowych — komentarz w dokumentacji to ten sam problem.
#
# ⛔ Rejestr bramek. Nazwy MUSZĄ zgadzać się z .github/manifest-bramek.txt —
# pilnuje tego narzedzia-qa/sprawdz-manifest.sh. Kolejność jak w quality.yml
# sprzed zbicia zadań: najpierw tanie i statyczne, na końcu skan historii.
BRAMKI=(actionlint manifest slady znaleziska status-bez-warunku php-lint phpcs phpstan gitleaks poswiadczenia)
# Bramki dotykające WYŁĄCZNIE kodu PHP — pomijane, gdy zmiana jest tekstowa.
BRAMKI_PHP=(php-lint phpcs phpstan)
PROG_PELNY=10
PROG_TEKSTOWY=6

# Wersje narzędzi pobieranych, gdy nie ma ich w PATH. Pinowane świadomie:
# „najnowsza" w bramce oznacza, że CI potrafi zmienić zdanie bez naszej zmiany.
WERSJA_ACTIONLINT=1.7.7
WERSJA_GITLEAKS=8.24.3
# ⛔ SUMY SHA256 SAMYCH BINAREK (nie archiwow) dla wersji pinowanych wyzej.
# POWOD (znalezisko B-5, 31.08): skrypt pobieral narzedzie curlem i od razu robil
# `chmod +x`, bez jednego sprawdzenia. Do tego cache lezy w /tmp — katalogu
# zapisywalnym dla KAZDEGO uzytkownika maszyny — a plik brany byl po samej nazwie.
# Kto mogl zapisac do /tmp, ten wykonywal swoj kod jako wykonawca CI. Suma binarki,
# a nie archiwum, bo ten sam warunek musi dzialac i przy swiezym pobraniu, i przy
# trafieniu z cache; archiwum po rozpakowaniu juz nie istnieje.
# Jak wyznaczone: archiwum potwierdzone oficjalnym plikiem `*_checksums.txt`
# z wydania, dopiero z niego policzona suma wypakowanej binarki.
# Przy podnoszeniu WERSJA_* trzeba podniesc TE sumy — inaczej bramka padnie.
SUMA_ACTIONLINT=9f7dedb4e23f89f2922073d1a6720405b7b520d4f5832ebb96f0d55a2958886c
SUMA_GITLEAKS=e18325d568268b6efb6cd0cc721f8bea5667f4cd1c8d03618ab61987f41269ad
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
# Zwraca 0, gdy plik ma DOKLADNIE oczekiwana sume SHA256. Kazda inna sytuacja —
# brak pliku, brak sha256sum, suma inna — to porazka. Cisza nie jest zgoda.
suma_zgodna() {
	local plik="$1" oczekiwana="$2" faktyczna=""
	[ -f "$plik" ] || return 1
	command -v sha256sum > /dev/null 2>&1 || return 1
	faktyczna="$(sha256sum "$plik" 2> /dev/null | cut -d' ' -f1)"
	[ -n "$faktyczna" ] && [ "$faktyczna" = "$oczekiwana" ]
}

zapewnij_narzedzie() {
	local nazwa="$1" url="$2" plik_w_archiwum="$3" suma="$4"
	local w_path=""
	# ⛔ Suma decyduje na KAZDEJ z trzech drog (PATH, cache, swieze pobranie).
	# Wersja sprzed 31.08 nie sprawdzala zadnej — patrz komentarz przy SUMA_*.
	if command -v "$nazwa" > /dev/null 2>&1; then
		w_path="$(command -v "$nazwa")"
		if suma_zgodna "$w_path" "$suma"; then
			echo "$w_path"
			return 0
		fi
		# W PATH stoi INNA wersja niz pinowana. Nie uzywamy jej po cichu: pinowanie
		# wersji i branie dowolnej z PATH wykluczaly sie nawzajem. Idziemy dalej,
		# po wlasny, zweryfikowany egzemplarz.
		echo "UWAGA: $nazwa w PATH ($w_path) ma inna sume niz pinowana $suma — pobieram wlasny" >&2
	fi
	if suma_zgodna "$CACHE/$plik_w_archiwum" "$suma" && [ -x "$CACHE/$plik_w_archiwum" ]; then
		echo "$CACHE/$plik_w_archiwum"
		return 0
	fi
	mkdir -p "$CACHE" || return 1
	if ! curl -sSfL "$url" -o "$CACHE/paczka.tgz" 2> /dev/null; then
		return 1
	fi
	if ! tar xzf "$CACHE/paczka.tgz" -C "$CACHE" "$plik_w_archiwum" 2> /dev/null; then
		return 1
	fi
	if ! suma_zgodna "$CACHE/$plik_w_archiwum" "$suma"; then
		echo "BLAD: pobrany $nazwa ma niezgodna sume SHA256 (oczekiwano $suma) — NIE uruchamiam" >&2
		rm -f "$CACHE/$plik_w_archiwum"
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
		actionlint "$SUMA_ACTIONLINT")" || { echo "BRAK actionlinta albo niezgodna suma — bramka pada" >&2; return 1; }
	"$bin" .github/workflows/*.yml
}

uruchom_manifest() {
	bash narzedzia-qa/sprawdz-manifest.sh
}

# Ksiegowosc znalezisk audytu: czy kazde ma test, status i czy zaden raport nie
# wnosi znalezisk poza rejestr. ⛔ NIE uruchamia testow — wszystkie wymagaja zywego
# stosu Dockera, wiec w rownoleglym CI deptalyby sobie po stosie. Pelne uzasadnienie
# i granica tej kontroli: naglowek narzedzia-qa/bramka-znaleziska.sh.
uruchom_znaleziska() {
	bash narzedzia-qa/bramka-znaleziska.sh
}

# Zapis statusu zamowienia MUSI chronic stan poprzedni. Wzorzec zlapany TRZY razy w jednym
# PR-ze (spor, zwrot, resend) — bramka pyta o cala klase naraz. Naglowek z uzasadnieniem:
# narzedzia-qa/bramka-status-bez-warunku.php.
uruchom_status_bez_warunku() {
	php narzedzia-qa/bramka-status-bez-warunku.php
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
	# 1536M + 1 proces (phpstan.neon.dist): przy 1G worker padal deterministycznie
	# po dojsciu plikow mechanizmu opinii (D32), a 2 workery po 1,5G na maszynie
	# 3,8 GB RAM to ryzyko OOM, ktory ubija sesje (zmierzone na tym serwerze).
	vendor/bin/phpstan analyse --memory-limit=1536M --no-progress
}

# Ślady wewnętrzne w plikach wtyczek — czyli w tym, co trafia do paczki klienta.
# Wzorce i lista długu siedzą w narzedzia-qa/slady-wewnetrzne.sh; ta sama funkcja
# obsługuje bramkę zamrożenia, więc nie ma dwóch list, które mogą się rozjechać.
uruchom_slady() {
	local skaner="narzedzia-qa/slady-wewnetrzne.sh" kod=0 katalog wtyczka
	[ -f "$skaner" ] || { echo "BRAK $skaner — nie ma czym sprawdzic sladow" >&2; return 1; }
	for katalog in wtyczki/*/; do
		[ -d "$katalog" ] || continue
		wtyczka="$(basename "$katalog")"
		bash "$skaner" "$wtyczka" "${katalog%/}" || kod=1
	done
	return $kod
}

uruchom_poswiadczenia() {
	bash narzedzia-qa/bramka-poswiadczenia.sh
}

uruchom_gitleaks() {
	local bin
	bin="$(zapewnij_narzedzie gitleaks \
		"https://github.com/gitleaks/gitleaks/releases/download/v${WERSJA_GITLEAKS}/gitleaks_${WERSJA_GITLEAKS}_linux_x64.tar.gz" \
		gitleaks "$SUMA_GITLEAKS")" || { echo "BRAK gitleaksa albo niezgodna suma — bramka pada" >&2; return 1; }
	# Historia od HEAD, nie --all: klon recenzenta/runnera widzi refs/pull/N/* ZAMKNIĘTYCH PR-ów (niezmienialne),
	# więc zatruty commit ze skasowanej gałęzi paliłby bramkę na zawsze (recenzja #195 W1; ta sama lekcja
	# co bramka-poswiadczenia 1.1.1). Próba kontrolna: narzedzia-qa/gitleaks-proba-kontrolna.sh
	"$bin" git --no-banner --redact --log-opts=HEAD . || return 1
	skan_archiwow "$bin"
}

# ⛔ DRUGI PRZELOT TEJ SAMEJ OCHRONY — ZAWARTOSC SLEDZONYCH ARCHIWOW.
# Znalezisko B-10 (31.08). Skan historii gita widzi `.zip` jako JEDEN binarny blob
# i nie zaglada do srodka. ZMIERZONE proba kontrolna: plik z trzema falszywymi
# kluczami lezacy luzem → gitleaks znajduje 2; ten SAM plik spakowany do zipa →
# znajduje 0. W repozytorium jest 29 sledzonych archiwow z paczkami botow, wiec bez
# tego przelotu klucz w paczce przechodzi przy wszystkich bramkach na zielono.
# ⛔ To NIE jest osobna bramka w manifescie: to ta sama ochrona „szukaj sekretow",
# ktora przestaje miec martwe pole. Osobny wpis sugerowalby, ze da sie ja wylaczyc
# niezaleznie od skanu historii — a nie da, bo razem stanowia jedna odpowiedz.
skan_archiwow() {
	local bin="$1" archiwa katalog rc=0 licznik=0 plikow=0 z=""
	archiwa="$(git ls-files '*.zip' 2> /dev/null)"
	if [ -z "$archiwa" ]; then
		echo "Archiwa: brak sledzonych plikow .zip — nie ma czego rozpakowac"
		return 0
	fi
	if ! command -v unzip > /dev/null 2>&1; then
		echo "BLAD: brak unzipa, a w repo sa sledzone archiwa — NIE moge ich sprawdzic" >&2
		return 1
	fi
	katalog="$(mktemp -d)" || return 1
	while IFS= read -r z; do
		[ -n "$z" ] || continue
		licznik=$((licznik + 1))
		unzip -qo "$z" -d "$katalog/$licznik" > /dev/null 2>&1 || true
	done <<< "$archiwa"
	plikow="$(find "$katalog" -type f 2> /dev/null | wc -l)"
	echo "Archiwa: $licznik sledzonych, rozpakowanych plikow: $plikow"
	# ⛔ PODLOGA. Zero plikow przy niezerowej liczbie archiwow znaczy, ze
	# rozpakowanie padlo — a wtedy „brak sekretow" jest zdaniem o PUSTYM katalogu,
	# nie o paczkach. Ta sama klasa co I-2 (mianownik zero brany za czysto).
	if [ "$plikow" -eq 0 ]; then
		echo "BLAD: $licznik archiwow, ale rozpakowano 0 plikow — skan nie mial czego czytac" >&2
		rm -rf "$katalog"
		return 1
	fi
	"$bin" detect --no-git --source "$katalog" --no-banner --redact || rc=$?
	rm -rf "$katalog"
	return $rc
}

# ⛔ Furtka WYLACZNIE dla proby kontrolnej: `source` z ustawionym
# BRAMKI_TYLKO_FUNKCJE=1 daje SAME definicje, bez odpalania bramek. Powod: test ma
# wolac PRAWDZIWA funkcje z tego pliku, a nie jej przepisana kopie — kopia mierzy
# sama siebie i przechodzi nawet wtedy, gdy produkt jest zepsuty.
# To NIE jest sposob na pominiecie bramek w CI: quality.yml wola `bash bramki.sh`
# bez tej zmiennej, wiec sciezka produkcyjna jej nie widzi.
if [ -n "${BRAMKI_TYLKO_FUNKCJE:-}" ]; then
	return 0 2> /dev/null || exit 0
fi

# --- przebieg -----------------------------------------------------------------

echo "=== BRAMKI JAKOSCI — tryb: $TRYB, prog: $PROG z ${#BRAMKI[@]} ==="

bramka actionlint uruchom_actionlint
bramka manifest   uruchom_manifest
bramka slady      uruchom_slady
bramka znaleziska uruchom_znaleziska
bramka status-bez-warunku uruchom_status_bez_warunku
bramka php-lint   uruchom_php_lint
bramka phpcs      uruchom_phpcs
bramka phpstan    uruchom_phpstan
bramka gitleaks   uruchom_gitleaks
bramka poswiadczenia uruchom_poswiadczenia

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
