#!/bin/bash
# slady-wewnetrzne.sh — czy to, co dostaje kupujący, wiezie NASZE ślady.   WERSJA 1.1.0
#
# PO CO TO POWSTAŁO: przy zamrażaniu ChatBota 1.5.7 okazało się, że paczka wiozła
# klientowi 20 śladów w 8 plikach — odsyłacze do plików, których kupujący nie
# dostaje (`testy/…`, wewnętrzne rozpoznania), numery naszych zgłoszeń („uwaga
# recenzenta do PR #83") i osoby z zespołu. Wyczyszczenie tego BYŁO NAPRAWĄ
# EGZEMPLARZA: jutro wjeżdża nowy komentarz i wraca dokładnie to samo.
#
# ⛔ Dlaczego nie wystarczy kontrola „zip == repo": ślad siedzi w repozytorium
# DOKŁADNIE tak samo jak w paczce, więc porównanie sum kontrolnych jest na niego
# ślepe z definicji. To musi być osobne pytanie: „czego tu w ogóle nie powinno być".
#
# JEDNO ŹRÓDŁO WZORCÓW dla dwóch miejsc, żeby nie rozjechały się po cichu:
#   - narzedzia-qa/paczka-zgodnosc.sh — skan ROZPAKOWANEJ PACZKI przy zamrażaniu,
#   - narzedzia-qa/bramki.sh (bramka `slady`) — skan plików wtyczek przy KAŻDYM PR-ze.
#
# ZALEGŁOŚCI: narzedzia-qa/slady-zaleglosci.txt wymienia ślady, które JUŻ SĄ
# w repozytorium i czekają na właściciela swojego modułu. Nie blokują (inaczej
# bramka wywracałaby cudze PR-y od pierwszego dnia), ale są WYPISYWANE przy każdym
# przebiegu jako dług. Wpis, który przestał pasować, jest BŁĘDEM — lista ma się
# kurczyć i nie ma prawa gnić.
#
# Użycie: bash narzedzia-qa/slady-wewnetrzne.sh <etykieta> <katalog>
#         bash narzedzia-qa/slady-wewnetrzne.sh chatbot-live wtyczki/chatbot-live
#
# Kody wyjścia: 0 = zero śladów spoza listy zaległości
#               1 = jest ślad NOWY albo wpis zaległości przestał pasować
#               2 = zła liczba argumentów / nie ma katalogu
set -u

WERSJA_NARZEDZIA=1.1.0

ETYKIETA="${1:-}"
KATALOG="${2:-}"
if [ -z "$ETYKIETA" ] || [ -z "$KATALOG" ]; then
	echo "Użycie: bash narzedzia-qa/slady-wewnetrzne.sh <etykieta> <katalog>" >&2
	exit 2
fi
[ -d "$KATALOG" ] || { echo "SLADY: nie ma katalogu $KATALOG" >&2; exit 2; }

KORZEN_NARZEDZIA="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZALEGLOSCI="${SLADY_ZALEGLOSCI:-$KORZEN_NARZEDZIA/slady-zaleglosci.txt}"

# Klasa śladu | wzorzec ERE. Wzorce celowo wąskie: bramka, która krzyczy na
# zwykłe polskie słowa, zostanie wyłączona po tygodniu i nie wróci.
#
# 1.1.0 — „nasz obieg pracy" dostał NASZE SŁOWNICTWO, nie tylko nasze numery.
# Powód z życia: w Segregatorze został BLIŹNIAK wyczyszczonych śladów —
# „Zarzut z pierwszej rundy krytyk" mówi o naszym obiegu dokładnie to samo, co
# „recenzja PR #27", tylko innymi słowami, więc wzorzec go NIE WIDZIAŁ. Naprawa
# samego zdania byłaby naprawą egzemplarza. `\bkrytyk` z granicą słowa, bo bez
# niej wzorzec łapie „diakrytyków".
SLADY=(
	"znacznik roboczy|TODO|FIXME|@todo|\bHACK\b|\bXXX\b|NOTATK[AI] QA|DO USUNIECIA PRZED"
	"adres maszyny dev|localhost|127\.0\.0\.1|0\.0\.0\.0|:8890|:8025|mailpit|ngrok|wp-env|vagrant|\.ddev\b"
	"ścieżka naszego drzewa roboczego|/root/praca|(^|[^a-zA-Z0-9/._-])(testy|boty|audyt|narzedzia-qa|wtyczki)/"
	"nasz obieg pracy|PR #[0-9]|recenzent|recenzj|koordynator|builder[0-9]|prób[ay] kontroln|proba-kontrolna|mutant|\\bkrytyk|egzaminator|Dzidek"
)

# --- wczytanie listy zaległości ----------------------------------------------
# Format: etykieta|ścieżka-względna|treść linii (bez wiodących spacji).
declare -A ZNANE=()
declare -A UZYTE=()
if [ -f "$ZALEGLOSCI" ]; then
	while IFS= read -r wiersz; do
		case "$wiersz" in ''|'#'*) continue ;; esac
		ZNANE["$wiersz"]=1
	done < "$ZALEGLOSCI"
fi

NOWE=0
DLUG=0

for pozycja in "${SLADY[@]}"; do
	nazwa="${pozycja%%|*}"
	wzorzec="${pozycja#*|}"
	while IFS= read -r trafienie; do
		[ -n "$trafienie" ] || continue
		plik="${trafienie%%:*}"
		reszta="${trafienie#*:}"
		numer="${reszta%%:*}"
		tresc="${reszta#*:}"
		# Klucz bez numeru linii: dopisanie linijki wyżej nie ma unieważniać wpisu.
		klucz="$ETYKIETA|$plik|$(printf '%s' "$tresc" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//')"
		if [ -n "${ZNANE[$klucz]:-}" ]; then
			UZYTE["$klucz"]=1
			DLUG=$(( DLUG + 1 ))
			printf '  DLUG     %s:%s (%s)\n' "$plik" "$numer" "$nazwa"
			continue
		fi
		# Tryb dla człowieka, który zamyka dług: wypisuje SAM KLUCZ, gotowy do
		# wklejenia do listy zaległości. Ręczne przepisywanie tych linii zawsze
		# kończy się wpisem, który do niczego nie pasuje.
		if [ -n "${SLADY_WYPISZ_KLUCZE:-}" ]; then
			printf '%s\n' "$klucz"
		else
			printf '  SLAD     %s:%s (%s): %.120s\n' "$plik" "$numer" "$nazwa" "$tresc"
		fi
		NOWE=$(( NOWE + 1 ))
	done < <( cd "$KATALOG" && grep -rInE "$wzorzec" . 2> /dev/null | sed 's|^\./||' )
done

# Wpis zaległości, który do niczego nie pasuje: albo ślad zniknął (super — usuń
# wpis), albo linia się zmieniła i wpis przestał cokolwiek pilnować. Oba przypadki
# wymagają ruchu człowieka, więc oba są czerwone.
OSIEROCONE=0
for klucz in "${!ZNANE[@]}"; do
	case "$klucz" in "$ETYKIETA|"*) ;; *) continue ;; esac
	if [ -z "${UZYTE[$klucz]:-}" ]; then
		printf '  MARTWY   wpis zaleglosci nie pasuje do niczego: %s\n' "$klucz"
		OSIEROCONE=$(( OSIEROCONE + 1 ))
	fi
done

printf 'SLADY %s [%s]: nowe=%d, dlug z listy=%d, martwe wpisy=%d\n' \
	"$WERSJA_NARZEDZIA" "$ETYKIETA" "$NOWE" "$DLUG" "$OSIEROCONE"

if [ "$NOWE" -gt 0 ] || [ "$OSIEROCONE" -gt 0 ]; then
	exit 1
fi
exit 0
