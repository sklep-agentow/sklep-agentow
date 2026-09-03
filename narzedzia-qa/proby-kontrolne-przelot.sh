#!/bin/bash
# proby-kontrolne-przelot.sh — URUCHAMIA wszystkie próby kontrolne i SAM pisze log.
#
# PO CO (recenzja PR #198, runda 3, uwaga 3): kontrola „6 próby kontrolne" w k7-koniec.sh
# nie uruchamiała żadnej próby — grepowała plik pisany RĘCZNIE. Skutek zmierzony przez
# recenzentkę: log mówił `bramki-proba-kontrolna.sh EXIT=1`, a uruchomienie tej samej próby
# na HEAD dawało EXIT=0. Artefakt dowodowy rozjechał się z rzeczywistością i nikt tego nie
# widział, bo nikt go nie odtwarzał. Ten skrypt odbiera człowiekowi pióro: log jest WYNIKIEM
# przebiegu, nie notatką o przebiegu.
#
# DATA WAŻNOŚCI. Nagłówek niesie COMMIT, na którym przelot się odbył. k7-koniec.sh porównuje
# go z HEAD i odmawia zaliczenia dowodu z innego stanu kodu — dowód bez daty ważności to
# dowód o nieznanym wieku.
#
# Wywołanie: bash narzedzia-qa/proby-kontrolne-przelot.sh [plik-logu] [katalog]
#            (domyślnie: audyt/sklep/PROBY-KONTROLNE.log, katalog `.` = całe repo)
#
# ⛔ DRUGI ARGUMENT — ZAKRES, dodany 31.08. Powód zmierzony: przelot po CAŁYM repo bierze
# 38 prób, z czego 11 to testy PRODUKTU wymagające żywego stosu WordPressa. W CI stosu
# nie ma i manifest bramek mówi o tym wprost („w CI nie ma WP+Stripe"), więc wołanie
# całości w kroku per-PR kończyłoby się jedenastoma porażkami, które nie są wadami kodu.
# Bramka wydania (k7-koniec.sh) woła NADAL bez zakresu i to jest właściwe — tam stos stoi.
# Zakres zawęża TYLKO to, co przelatujemy; nie zmienia progu ani sposobu liczenia.
# Kody wyjścia: 0 = przelot wykonany i WSZYSTKIE próby dały zero ·
#               1 = przelot wykonany, ale któraś próba nie przeszła (log i tak zapisany) ·
#               2 = nie ma czego uruchamiać / drzewo brudne.
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
LOG="${1:-audyt/sklep/PROBY-KONTROLNE.log}"
ZNACZNIK='WYGENEROWANE-PRZEZ: narzedzia-qa/proby-kontrolne-przelot.sh'

# Próby, których NIE uruchamiamy maszynowo — z powodem. Cicha redukcja zakresu czyta się
# jak „sprawdzono wszystko", dlatego pominięcia lądują w logu, nie w komentarzu w kodzie.
declare -A POMIJANE=(
	[dowod-zachowania-segregator-proba-kontrolna.sh]="deinstaluje wtyczkę ze stosu — do przelotu ręcznego przed wydaniem"
)

# ⛔ PRÓBY WYMAGAJĄCE DOCKERA (1.09; od 2.09 jest ich PIĘĆ). Te próby stawiają stos próbny albo wołają
# PHP w kontenerze. Wykonawca CI od 31.08 chodzi jako `ciuser` (naprawa B-3) i NIE
# widzi `docker.sock` — zmierzone: `permission denied while trying to connect to
# the docker API at unix:///var/run/docker.sock`. Dostępu dać NIE MOŻNA: grupa
# `docker` jest równoważnikiem roota i unieważniłaby całą tamtą naprawę.
#
# ⭐ Dlaczego nie na sztywno w POMIJANE: bo lokalnie (i na każdym koncie z Dockerem)
# te próby DZIAŁAJĄ i mają biec. Pomijamy je wyłącznie tam, gdzie Dockera faktycznie
# nie ma — inaczej sami sobie zabralibyśmy pokrycie na maszynach, gdzie jest.
#
# ⚠️ Dlaczego to nie jest zamiecenie pod dywan: pominięcie ląduje w logu z powodem,
# jest policzone, a niżej stoi SUFIT — lista pominięć, która rośnie, wywala przelot.
# ⛔ DŁUG: te próby MUSZĄ przejść ręcznie przed wydaniem. CI ich nie sprawdzi.
WYMAGA_DOCKERA=(
	dowod-aktualizacji-proba-kontrolna.sh
	dowod-z-paczki-proba-kontrolna.sh
	stos-proba-kontrolna.sh
	test-php-proba-kontrolna.sh
	golden-migawka-proba-kontrolna.sh
)
if ! docker ps >/dev/null 2>&1; then
	for N in "${WYMAGA_DOCKERA[@]}"; do
		POMIJANE[$N]="wymaga Dockera, a to konto go nie widzi (wykonawca CI bez roota, B-3) — przelot ręczny przed wydaniem"
	done
fi

# ⛔ PRÓBY WYMAGAJĄCE `zip` (3.09). Powód zmierzony, nie przewidziany z góry: mamy DWA runnery
# (`hetzner-gosia`, `oracle-micro`) i one NIE SĄ IDENTYCZNE. Porównanie narzędzie po narzędziu:
#   zip     hetzner TAK · micro NIE      <- to
#   docker  hetzner TAK · micro NIE      <- to było przewidziane wyżej
#   unzip, jq, git, curl, php, composer, node — na obu tak samo
# Skutek, zanim to dopisano: ten sam commit #272 miał bramki ZIELONE (11 min, Hetzner)
# i CZERWONE (50 min, Oracle) — bo `k7-koniec-proba-kontrolna.sh:118` i
# `paczka-zgodnosc-proba-kontrolna.sh:293` odmawiają wtedy pomiaru kodem 2, a przelot
# liczył każdy niezerowy kod jako PORAŻKĘ. CI było więc NIEDETERMINISTYCZNE: wynik zależał
# od tego, który wykonawca złapał zadanie.
#
# ⭐ Ta sama zasada co przy Dockerze: nie na sztywno, tylko tam, gdzie `zip` faktycznie
# nie istnieje; pominięcie ląduje w logu z powodem, jest policzone i podlega SUFITOWI.
# ⛔ DŁUG: te dwie próby MUSZĄ przejść ręcznie przed wydaniem — na maszynie, która ma `zip`.
WYMAGA_ZIP=(
	k7-koniec-proba-kontrolna.sh
	paczka-zgodnosc-proba-kontrolna.sh
)
if ! command -v zip > /dev/null 2>&1; then
	for N in "${WYMAGA_ZIP[@]}"; do
		POMIJANE[$N]="wymaga `zip`, a ten wykonawca go nie ma (runnery nie są identyczne) — przelot ręczny przed wydaniem"
	done
fi

# ⛔ SUFIT POMINIĘĆ. Bez niego lista pominięć rośnie po cichu i przelot świeci
# zielono, sprawdzając coraz mniej — dokładnie ta klasa błędu, dla której ten
# skrypt w ogóle powstał. Podniesienie sufitu ma być świadomą decyzją w commicie.
MAX_POMINIETYCH=7   # 3.09: +1 wobec 6. Powod: WYMAGA_ZIP (2 proby) minus jedna, ktora i tak
                    # byla juz liczona — na koncie CI bez Dockera i bez `zip` pominiec jest 7
                    # (5 dockerowych + 2 zipowe). Bez tej zmiany moja wlasna naprawa wywalilaby
                    # przelot na suficie — czyli mechanizm zadzialal tak, jak mial: wymusil
                    # JAWNA decyzje w commicie zamiast cichego rozrostu listy pominiec.
                    # 2.09 (I-24): 6 = +1 golden-migawka-proba-kontrolna.sh (stos probny).

if [ -n "$(git status --porcelain -uno 2>/dev/null)" ]; then
	echo "PRZELOT: drzewo robocze BRUDNE — dowód powstałby na kodzie, którego nie ma w historii." >&2
	echo "         Zacommituj zmiany i powtórz (log niesie COMMIT i jest po nim sprawdzany)." >&2
	exit 2
fi

ZAKRES="${2:-.}"
[ -d "$ZAKRES" ] || { echo "PRZELOT: katalog zakresu [$ZAKRES] nie istnieje." >&2; exit 2; }
mapfile -t PROBY < <(find "$ZAKRES" -name '*proba-kontrolna*.sh' -not -path './.git/*' | sed 's|^\./||' | sort)
# ⛔ Zero prób to ODMOWA, nie sukces: zawężony zakres, który nic nie łapie, wyglądałby
# dokładnie jak przelot bez uwag. Ta sama klasa co mianownik zero w I-2.
[ "${#PROBY[@]}" -ge 1 ] || { echo "PRZELOT: nie znalazłem żadnej próby kontrolnej w [$ZAKRES]." >&2; exit 2; }

COMMIT=$(git rev-parse --short=7 HEAD)
GALAZ=$(git rev-parse --abbrev-ref HEAD)
START=$(date '+%Y-%m-%d %H:%M')

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
ZLE=0; ILE=0; POMINIETO=0; ZLE_NAZWY=()

for SCIEZKA in "${PROBY[@]}"; do
	NAZWA="${SCIEZKA##*/}"
	if [ -n "${POMIJANE[$NAZWA]:-}" ]; then
		printf 'POMINIETO %s — %s\n' "$NAZWA" "${POMIJANE[$NAZWA]}" >> "$TMP"
		POMINIETO=$((POMINIETO + 1))
		echo "  pomijam  $NAZWA (${POMIJANE[$NAZWA]})"
		continue
	fi
	echo "  uruchamiam  $NAZWA"
	T0=$SECONDS
	bash "$SCIEZKA" > /dev/null 2>&1
	KOD=$?
	CZAS=$((SECONDS - T0))
	printf '%s EXIT=%s %ss\n' "$NAZWA" "$KOD" "$CZAS" >> "$TMP"
	ILE=$((ILE + 1))
	# ⛔ NAZWA PADAJACEJ PROBY NA STDOUT, nie tylko do logu. Log ladowal w katalogu
	# tymczasowym wykonawcy, wiec w CI widac bylo wylacznie „z EXIT≠0: 2" — bez ani jednej
	# nazwy. Kazda porazka wymagala wtedy recznego dochodzenia u siebie, a odtworzenie
	# zestawu CI nie jest darmowe (konto z Dockerem uruchamia INNY zestaw prob).
	[ "$KOD" -eq 0 ] || { ZLE=$((ZLE + 1)); ZLE_NAZWY+=( "$NAZWA (EXIT=$KOD)" ); printf '  PADLA       %s (EXIT=%s)\n' "$NAZWA" "$KOD"; }
done

mkdir -p "$(dirname "$LOG")"
{
	printf '# PRÓBY KONTROLNE NARZĘDZI — przelot %s\n' "$START"
	printf '#\n'
	printf '# Każda pozycja: skrypt podkłada narzędziu znaną wadę i sprawdza, czy narzędzie ją WIDZI.\n'
	printf '# Kod wyjścia zero znaczy: narzędzie zareagowało na podłożoną wadę i przepuściło czysty kod.\n'
	printf '#\n'
	printf '%s\n' "$ZNACZNIK"
	printf 'COMMIT: %s\n' "$COMMIT"
	printf 'GALAZ: %s\n' "$GALAZ"
	printf 'PODSUMOWANIE: uruchomionych %s, z EXIT≠0: %s, pominiętych: %s\n' "$ILE" "$ZLE" "$POMINIETO"
	printf '#\n'
	cat "$TMP"
} > "$LOG"

printf '\nPRZELOT: uruchomionych %s, z EXIT≠0: %s, pominiętych %s — zapisane w %s\n' "$ILE" "$ZLE" "$POMINIETO" "$LOG"
[ "$ZLE" -eq 0 ] || printf 'PRZELOT: %s prób nie przeszło — log niesie stan faktyczny, nie wycinamy pozycji.\n' "$ZLE"
# Nazwy jeszcze raz, na koncu — kto czyta sam ogon dlugiego logu CI, tez ma je zobaczyc.
# Po jednej w linii: przecinkami sklejona lista dwudziestu nazw jest nieczytelna dokladnie tam,
# gdzie ma pomoc.
[ "$ZLE" -eq 0 ] || printf 'PRZELOT: nie przeszly:\n'
[ "$ZLE" -eq 0 ] || printf '         %s\n' "${ZLE_NAZWY[@]}"

# ⛔ SUFIT POMINIĘĆ — zestaw, który się kurczy, wygląda dokładnie jak zdany.
if [ "$POMINIETO" -gt "$MAX_POMINIETYCH" ]; then
	printf 'PRZELOT: pominiętych %s przy suficie %s — lista pominięć ROŚNIE.\n' "$POMINIETO" "$MAX_POMINIETYCH" >&2
	printf '         To awaria pokrycia, nie sukces. Podnieś sufit ŚWIADOMIE w commicie albo napraw próbę.\n' >&2
	exit 1
fi

exit $([ "$ZLE" -eq 0 ] && echo 0 || echo 1)
