#!/bin/bash
# mapa-licznik.sh — liczy cele mapy polowania i PRZEPISUJE wiersz LICZNIK.        WERSJA 1.1.1
#
# PO CO: mapa z licznikiem „N zamkniętych" była przy botach martwa — nikt jej nie
# aktualizował (lekcja jak-audytowac §☑️: odhacza KOORDYNATOR, przelicza SKRYPT,
# nie ręka). Ten skrypt jest jedynym, który wolno pisać do wiersza LICZNIK.
#
# Użycie: bash narzedzia-qa/mapa-licznik.sh <plik-mapy> [--zamroz <sha> | --sprawdz-zamrozenie | --tylko-licz]
#   bez opcji            — przelicza, przepisuje LICZNIK, wypisuje podsumowanie; EXIT 0
#   --zamroz <sha>       — jw. + dopisuje pod LICZNIK wiersz ZAMROZONA (sha + liczba celów)
#   --sprawdz-zamrozenie — TYLKO ODCZYT; EXIT 1, gdy mapa jest zamrożona, a liczba celów ≠ zapisana
#                          (mapa „rosnąca w nieskończoność" po rundzie B — reguła 7 planu K7)
#   --tylko-licz         — TYLKO ODCZYT; wypisuje policzony wiersz, NIE dotyka pliku (dla k7-koniec.sh —
#                          bramka końca nie może pisać do tego, co audytuje; recenzja #182 r2 D7)
#   Dodatkowo EXIT 2 = zła liczba argumentów / brak pliku / brak wiersza LICZNIK.
#   EXIT 1 (KAŻDY tryb) = wiersz „- ” z NIEZNANYM znacznikiem (np. ❓): taki cel znikałby z licznika bez
#   śladu — skrypt wypisuje numery linii i nic nie przepisuje.
#
# Cel = wiersz zaczynający się od „- ” + jeden ze statusów: ⬜ ✅ 🐞 ⚠️ ⛔. Każdy INNY wiersz „- ” = błąd.
set -u
PLIK="${1:-}"; TRYB="${2:-}"; SHA="${3:-}"
[ -n "$PLIK" ] && [ -f "$PLIK" ] || { echo "Użycie: bash narzedzia-qa/mapa-licznik.sh <plik-mapy> [--zamroz <sha>|--sprawdz-zamrozenie]" >&2; exit 2; }
grep -q '^CELE: ' "$PLIK" || { echo "MAPA-LICZNIK: brak wiersza LICZNIK (CELE: …) w $PLIK" >&2; exit 2; }

licz() { grep -c "^- $1 " "$PLIK"; }
N_OK=$(licz '✅'); N_BUG=$(licz '🐞'); N_AN=$(licz '⚠️'); N_POZA=$(licz '⛔'); N_NIE=$(licz '⬜')
N_CELE=$(( N_OK + N_BUG + N_AN + N_POZA + N_NIE ))
NOWY="CELE: $N_CELE · ✅ $N_OK · 🐞 $N_BUG · ⚠️ $N_AN · ⛔ $N_POZA · ⬜ $N_NIE"

# Nieznany znacznik: wiersz „- coś", gdzie „coś" nie jest jednym z pięciu statusów. Bez tej kontroli
# cel oznaczony np. ❓ albo literówką po prostu wypadał z sumy — mapa „domykała się" przez zgubę.
# (także WCIĘTY punkt listy `  - ⬜ …` — markdown nadal go renderuje, a licznik by go zgubił; recenzja #184 D1)
NIEZNANE=$(grep -nE '^[[:space:]]*- ' "$PLIK" | grep -vE '^[0-9]+:- (⬜|✅|🐞|⚠️|⛔) ' || true)
if [ -n "$NIEZNANE" ]; then
	echo "MAPA-LICZNIK: $(printf '%s\n' "$NIEZNANE" | wc -l) wiersz(y) „- ” z NIEZNANYM znacznikiem — nie liczę, nie przepisuję:" >&2
	printf '%s\n' "$NIEZNANE" | cut -c1-120 | sed 's/^/  linia /' >&2
	exit 1
fi

# Walidacja argumentów PRZED zapisem (recenzja #184 D2: --zamroz bez sha przepisywał LICZNIK, zanim odmówił)
case "$TRYB" in
	--zamroz) [ -n "$SHA" ] || { echo "MAPA-LICZNIK: --zamroz wymaga sha" >&2; exit 2; } ;;
	""|--tylko-licz|--sprawdz-zamrozenie) ;;
	*) echo "MAPA-LICZNIK: nieznana opcja $TRYB" >&2; exit 2 ;;
esac
case "$TRYB" in
	""|--zamroz)
		TMP="$(mktemp)"
		awk -v nowy="$NOWY" '/^CELE: /{print nowy; next} {print}' "$PLIK" > "$TMP" && mv "$TMP" "$PLIK"
		;;
esac

case "$TRYB" in
	--tylko-licz) ;;
	--zamroz)
		grep -q '^ZAMROZONA: ' "$PLIK" && { echo "MAPA-LICZNIK: mapa już zamrożona — nie zamrażam drugi raz" >&2; exit 1; }
		printf 'ZAMROZONA: %s · celow=%s · %s\n' "$SHA" "$N_CELE" "$(date +%Y-%m-%dT%H:%M)" >> "$PLIK"
		;;
	--sprawdz-zamrozenie)
		if grep -q '^ZAMROZONA: ' "$PLIK"; then
			ZAP=$(sed -n 's/^ZAMROZONA: .*celow=\([0-9]*\).*/\1/p' "$PLIK" | tail -1)
			if [ "$ZAP" != "$N_CELE" ]; then
				echo "MAPA-LICZNIK: mapa zamrożona z $ZAP celami, a ma $N_CELE — cele dopisane po zamrożeniu" >&2
				echo "$NOWY"; exit 1
			fi
			echo "zamrożenie OK ($ZAP celów)"
		else
			echo "mapa NIE jest zamrożona"
		fi
		;;
	"") ;;
	*) echo "MAPA-LICZNIK: nieznana opcja $TRYB" >&2; exit 2 ;;
esac

echo "$NOWY"
exit 0
