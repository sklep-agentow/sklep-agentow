#!/usr/bin/env bash
# STRAŻNIK KLASY — trzy kopie tej samej obrony przed formułą w arkuszu NIE ROZJEŻDŻAJĄ SIĘ.
#
# WADA, która to wywołała (2.09, przelot terenu): obrona przed CSV injection istnieje w repo
# w TRZECH egzemplarzach i miała TRZY różne zestawy znaków:
#   sklep-agentow/includes/admin-dashboard.php  = + - @ \t \r '   (7, plus wyjątek na liczby)
#   segregator-maila/includes/csv.php           = + - @ \t \r     (6)
#   chatbot-live/includes/csv.php               = + - @           (4)  ← NAJSŁABSZA
# Najsłabsza obsługiwała dane najmniej zaufane: treść wpisaną w oknie czatu przez anonimowego
# gościa. Komentarz w kopii segregatora WPROST tłumaczy, po co są tam tabulator i CR — czyli
# ktoś to wiedział, poprawił jedną kopię i nie przeniósł do rodzeństwa.
#
# ⛔ DLACZEGO SZUKA PO ZAWARTOŚCI, NIE PO NAZWIE FUNKCJI: istniejący strażnik
# `testy/csv-bez-formuly-test.php` ładuje TYLKO plik sklepu i mierzy prawdziwą funkcję eksportu
# w pięciu kierunkach — jest dobry, ale pilnuje JEDNEJ kopii z trzech, przez co rozjazd przeżył
# i jeszcze sprawiał wrażenie, że temat jest pokryty. Ten strażnik jest komplementarny: nie
# mierzy zachowania, tylko pilnuje, żeby żadna kopia — także czwarta, dopisana kiedyś pod inną
# nazwą — nie miała węższego zestawu znaków niż wymagany.
#
# Uruchomienie: bash testy/csv-neutralizacja-blizniaki-test.sh   (nie wymaga stosu ani WordPressa)
# EXIT 0 = wszystkie kopie mają komplet · 1 = któraś węższa · 2 = przyrząd/środowisko
set -u
cd "$(dirname "$0")/.." || exit 2

# Minimalny wspólny zestaw. Kopia może mieć WIĘCEJ (sklep ma apostrof i wyjątek na literał
# liczbowy — to świadome, służy odwracalności i temu, żeby kolumna Kwota została liczbą).
WYMAGANE_OPIS="= + - @ tabulator CR"

# Kopie znajdujemy po ZESTAWIE ZNAKÓW, nie po nazwie funkcji.
MAPA="$(timeout 20 grep -rn -F "'=', '+', '-', '@'" --include='*.php' wtyczki/ 2>/dev/null)"
STALE="$(timeout 20 grep -rn -E "^const [A-Z_]*CSV_ZNAKI_FORMULY" --include='*.php' wtyczki/ 2>/dev/null)"
# `sort -u` po plik:linia — sklep trafia i we wzorzec, i w stala, wiec bez tego liczylby sie dwa razy.
WSZYSTKO="$(printf '%s\n%s\n' "$MAPA" "$STALE" | grep -v '^$' | sort -u -t: -k1,2)"

ILE="$(printf '%s\n' "$WSZYSTKO" | grep -c . )"
if [ "$ILE" -eq 0 ]; then
	echo "PRZYRZAD: zero kopii obrony CSV w wtyczki/ — wzorzec przestal trafiac, a to NIE jest dowod braku wady." >&2
	exit 2
fi

echo "=== KOPIE OBRONY PRZED FORMULA W ARKUSZU (znalezione po zestawie znakow) ==="
ZLE=0
while IFS= read -r wiersz; do
	[ -z "$wiersz" ] && continue
	plik="${wiersz%%:*}"
	reszta="${wiersz#*:}"
	nr="${reszta%%:*}"
	tresc="${reszta#*:}"
	ma_tab=0; ma_cr=0
	case "$tresc" in *'\t'*) ma_tab=1 ;; esac
	case "$tresc" in *'\r'*) ma_cr=1 ;; esac
	if [ "$ma_tab" -eq 1 ] && [ "$ma_cr" -eq 1 ]; then
		printf '  OK    %s:%s\n' "$plik" "$nr"
	else
		brak=""
		[ "$ma_tab" -eq 0 ] && brak="tabulator"
		[ "$ma_cr" -eq 0 ] && brak="${brak:+$brak i }CR"
		printf '  BRAK  %s:%s — nie neutralizuje: %s\n' "$plik" "$nr" "$brak"
		ZLE=$((ZLE+1))
	fi
done <<< "$WSZYSTKO"

echo
echo "kopii: $ILE · wymagany zestaw: $WYMAGANE_OPIS"
if [ "$ZLE" -ne 0 ]; then
	echo "ROZJAZD BLIZNIAKOW: $ZLE z $ILE kopii ma WEZSZY zestaw niz reszta." >&2
	echo "  Arkusz zjada wiodacy tabulator albo CR i wykonuje formule stojaca za nim." >&2
	echo "  ⛔ Poprawiajac, ruszaj TAKZE strone odwrotna (odapostrofowanie przy imporcie) —" >&2
	echo "     inaczej runda eksport→import przestaje byc odwracalna." >&2
	exit 1
fi
echo "WSZYSTKIE KOPIE MAJA KOMPLET — brak rozjazdu."
exit 0
