#!/bin/bash
# mapa-licznik.sh — liczy cele mapy polowania i PRZEPISUJE wiersz LICZNIK.        WERSJA 1.3.0
#
# PO CO: mapa z licznikiem „N zamkniętych" była przy botach martwa — nikt jej nie
# aktualizował (lekcja jak-audytowac §☑️: odhacza KOORDYNATOR, przelicza SKRYPT,
# nie ręka). Ten skrypt jest jedynym, który wolno pisać do wiersza LICZNIK.
#
# Użycie: bash narzedzia-qa/mapa-licznik.sh <plik-mapy> [--przelot] [--zamroz <sha> | --sprawdz-zamrozenie | --tylko-licz]
#   --przelot            — zestaw statusów PRZELOTU CZŁOWIEKA (⬜ ✅ 😖 🐞) i jego format LICZNIKA; jedyne narzędzie,
#                          któremu wolno pisać `CELE:` w PRZELOT-CZLOWIEKA-SKLEP.md (recenzja #186 W2)
#   bez opcji            — przelicza, przepisuje LICZNIK, wypisuje podsumowanie; EXIT 0
#   --zamroz <sha>       — jw. + dopisuje pod LICZNIK wiersz ZAMROZONA (sha + liczba celów)
#   --sprawdz-zamrozenie — TYLKO ODCZYT; EXIT 1, gdy mapa jest zamrożona, a liczba celów ≠ zapisana ALBO odcisk TREŚCI celów
#                          (ID + treść, bez statusu i odsyłacza; 1.3.0, recenzja #195 D3) ≠ zapisany `tresc=`
#   --odcisk             — TYLKO ODCZYT; wypisuje odcisk treści celów (do ręcznego uzupełnienia starego wiersza ZAMROZONA)
#                          (mapa „rosnąca w nieskończoność" po rundzie B — reguła 7 planu K7)
#   --tylko-licz         — TYLKO ODCZYT; wypisuje policzony wiersz, NIE dotyka pliku (dla k7-koniec.sh —
#                          bramka końca nie może pisać do tego, co audytuje; recenzja #182 r2 D7)
#   Dodatkowo EXIT 2 = zła liczba argumentów / brak pliku / brak wiersza LICZNIK.
#   EXIT 1 (KAŻDY tryb) = wiersz „- ” z NIEZNANYM znacznikiem (np. ❓): taki cel znikałby z licznika bez
#   śladu — skrypt wypisuje numery linii i nic nie przepisuje.
#
# Cel = wiersz zaczynający się od „- ” + status z ZESTAWU: mapa (domyślnie) ⬜ ✅ 🐞 ⚠️ ⛔ · przelot (`--przelot`) ⬜ ✅ 😖 🐞.
# Każdy INNY wiersz „- ” (także wcięty) = błąd, EXIT 1.
set -u
PLIK="${1:-}"; shift || true; TRYB=""; SHA=""; ZESTAW=mapa
while [ $# -gt 0 ]; do case "$1" in --przelot) ZESTAW=przelot ;; --zamroz) TRYB=--zamroz; SHA="${2:-}"; [ $# -gt 1 ] && shift ;; --*) TRYB="$1" ;; *) echo "MAPA-LICZNIK: nieznany argument $1" >&2; exit 2 ;; esac; shift; done
[ -n "$PLIK" ] && [ -f "$PLIK" ] || { echo "Użycie: bash narzedzia-qa/mapa-licznik.sh <plik-mapy> [--zamroz <sha>|--sprawdz-zamrozenie]" >&2; exit 2; }
grep -q '^CELE: ' "$PLIK" || { echo "MAPA-LICZNIK: brak wiersza LICZNIK (CELE: …) w $PLIK" >&2; exit 2; }

licz() { grep -c "^- $1 " "$PLIK"; }
if [ "$ZESTAW" = przelot ]; then
	N_OK=$(licz '✅'); N_BUG=$(licz '🐞'); N_DZIW=$(licz '😖'); N_NIE=$(licz '⬜')
	N_CELE=$(( N_OK + N_BUG + N_DZIW + N_NIE )); STATUSY='⬜|✅|😖|🐞'
	NOWY="CELE: $N_CELE · ✅ $N_OK · 😖 $N_DZIW · 🐞 $N_BUG · ⬜ $N_NIE"
else
	N_OK=$(licz '✅'); N_BUG=$(licz '🐞'); N_AN=$(licz '⚠️'); N_POZA=$(licz '⛔'); N_NIE=$(licz '⬜')
	N_CELE=$(( N_OK + N_BUG + N_AN + N_POZA + N_NIE )); STATUSY='⬜|✅|🐞|⚠️|⛔'
	NOWY="CELE: $N_CELE · ✅ $N_OK · 🐞 $N_BUG · ⚠️ $N_AN · ⛔ $N_POZA · ⬜ $N_NIE"
fi

# Nieznany znacznik: wiersz „- coś", gdzie „coś" nie jest statusem AKTYWNEGO zestawu ($STATUSY). Bez tej kontroli
# cel oznaczony np. ❓ albo literówką po prostu wypadał z sumy — mapa „domykała się" przez zgubę.
# (także WCIĘTY punkt listy `  - ⬜ …` — markdown nadal go renderuje, a licznik by go zgubił; recenzja #184 D1)
NIEZNANE=$(grep -nE '^[[:space:]]*- ' "$PLIK" | grep -vE "^[0-9]+:- ($STATUSY) " || true)
if [ -n "$NIEZNANE" ]; then
	echo "MAPA-LICZNIK: $(printf '%s\n' "$NIEZNANE" | wc -l) wiersz(y) „- ” z NIEZNANYM znacznikiem — nie liczę, nie przepisuję:" >&2
	printf '%s\n' "$NIEZNANE" | cut -c1-120 | sed 's/^/  linia /' >&2
	exit 1
fi

# Walidacja argumentów PRZED zapisem (recenzja #184 D2: --zamroz bez sha przepisywał LICZNIK, zanim odmówił)
case "$TRYB" in
	--zamroz) [ -n "$SHA" ] || { echo "MAPA-LICZNIK: --zamroz wymaga sha" >&2; exit 2; } ;;
	""|--tylko-licz|--sprawdz-zamrozenie|--odcisk) ;;
	*) echo "MAPA-LICZNIK: nieznana opcja $TRYB" >&2; exit 2 ;;
esac
case "$TRYB" in
	""|--zamroz)
		TMP="$(mktemp)"
		awk -v nowy="$NOWY" '/^CELE: /{print nowy; next} {print}' "$PLIK" > "$TMP" && mv "$TMP" "$PLIK"
		;;
esac

# Odcisk TREŚCI celów: „ID · treść” bez statusu i bez odsyłacza [..] — status wolno zmieniać po zamrożeniu (⬜→✅ to
# postęp), ale podmiana celu na inny albo przeredagowanie treści przy tej samej liczbie NIE (recenzja #195 D3).
ODCISK=$(grep -E "^- ($STATUSY) " "$PLIK" | sed -E 's/^- [^ ]+ //; s/ · \[[^]]*\][ ·]*$//; s/[ ·]+$//' | LC_ALL=C sort | sha256sum | cut -c1-12)
case "$TRYB" in
	--tylko-licz) ;;
	--odcisk) echo "$ODCISK" ;;
	--zamroz)
		grep -q '^ZAMROZONA: ' "$PLIK" && { echo "MAPA-LICZNIK: mapa już zamrożona — nie zamrażam drugi raz" >&2; exit 1; }
		printf 'ZAMROZONA: %s · celow=%s · tresc=%s · %s\n' "$SHA" "$N_CELE" "$ODCISK" "$(date +%Y-%m-%dT%H:%M)" >> "$PLIK"
		;;
	--sprawdz-zamrozenie)
		if grep -q '^ZAMROZONA: ' "$PLIK"; then
			ZAP=$(sed -n 's/^ZAMROZONA: .*celow=\([0-9]*\).*/\1/p' "$PLIK" | tail -1)
			ZAP_T=$(sed -n 's/^ZAMROZONA: .*tresc=\([0-9a-f]*\).*/\1/p' "$PLIK" | tail -1)
			if [ "$ZAP" != "$N_CELE" ]; then
				echo "MAPA-LICZNIK: mapa zamrożona z $ZAP celami, a ma $N_CELE — cele dopisane po zamrożeniu" >&2
				echo "$NOWY"; exit 1
			fi
			if [ -n "$ZAP_T" ] && [ "$ZAP_T" != "$ODCISK" ]; then
				echo "MAPA-LICZNIK: mapa zamrożona z odciskiem treści $ZAP_T, a ma $ODCISK — cel podmieniony/przeredagowany po zamrożeniu" >&2
				echo "$NOWY"; exit 1
			fi
			[ -n "$ZAP_T" ] || echo "MAPA-LICZNIK: wiersz ZAMROZONA bez odcisku treści (sprzed 1.3.0) — pilnuję tylko liczby" >&2
			echo "zamrożenie OK ($ZAP celów, tresc=${ZAP_T:-brak})"
		else
			echo "mapa NIE jest zamrożona"
		fi
		;;
	"") ;;
	*) echo "MAPA-LICZNIK: nieznana opcja $TRYB" >&2; exit 2 ;;
esac

echo "$NOWY"
exit 0
