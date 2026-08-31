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
ZLE=0; ILE=0; POMINIETO=0

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
	[ "$KOD" -eq 0 ] || ZLE=$((ZLE + 1))
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
exit $([ "$ZLE" -eq 0 ] && echo 0 || echo 1)
