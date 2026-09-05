#!/usr/bin/env bash
# PRÓBA KONTROLNA strażnika klasy 107 — testy/csv-neutralizacja-blizniaki-test.sh.
#
# PO CO: strażnik, którego nic nie woła i który nigdy nie padł, niczego nie chroni (klasa 105).
# Recenzja #298 r1 (W3) złapała go dokładnie w tym stanie: istniał, CI go nie uruchamiało,
# a CO-WISI ogłaszało dług rozliczonym powołując się właśnie na niego. Ta próba jest jego
# SPUSTEM (przelot `narzedzia-qa/proby-kontrolne-przelot.sh` bierze każdy `*proba-kontrolna*.sh`)
# i jego KALIBRACJĄ: podkłada mutanty w KOPII plików (nigdy w repo) i wymaga, żeby strażnik padł;
# czysta kopia musi przejść.
#
# Mutanty (każdy psuje DOKŁADNIE JEDEN warunek):
#   A. segregator bez tabulatora w zestawie          → strażnik MUSI paść (EXIT=1, „BRAK … tabulator")
#   B. stała ChatBota bez powrotu karetki             → strażnik MUSI paść (EXIT=1, „BRAK … CR")
#   C. kopia ChatBota wypada z pomiaru (plik bez listy) → strażnik MUSI paść na PODŁODZE (EXIT=1, „PODLOGA")
#   kontrola odwrotna: czysta kopia trzech plików     → EXIT=0, „kopii: 3"
#   kontrola na PRAWDZIWYM repo (bez CSV_BLIZNIAKI_KORZEN) → EXIT=0 i liczba kopii z wyjścia strażnika; bez mutanta
#   (mutant tej gałęzi = C na kopii: strażnik pada na podłodze, gdy kopii ubędzie)
#
# Nie wymaga stosu, Dockera ani PHP — strażnik czyta pliki grepem.
# EXIT 0 = strażnik skalibrowany · 1 = któryś mutant przeszedł niezauważony albo czysty kod padł · 2 = przyrząd
set -u
cd "$(dirname "$0")/.." || exit 2
STRAZNIK="testy/csv-neutralizacja-blizniaki-test.sh"
[ -f "$STRAZNIK" ] || { echo "brak $STRAZNIK — nie zmierzono" >&2; exit 2; }

# Trzy kopie obrony, które strażnik ma widzieć (ścieżki względem korzenia repo):
PLIKI=(
	"wtyczki/sklep-agentow/includes/admin-dashboard.php"
	"wtyczki/segregator-maila/includes/csv.php"
	"wtyczki/chatbot-live/includes/csv.php"
)
for f in "${PLIKI[@]}"; do [ -f "$f" ] || { echo "brak $f — nie zmierzono" >&2; exit 2; }; done

ROBOCZY="$(mktemp -d)"
sprzataj() { case "$ROBOCZY" in /tmp/*) rm -rf "$ROBOCZY" ;; esac; }
trap sprzataj EXIT
BLEDY=0; KOTWICE=0; PODLOZONE=0

wgraj_czysty() {
	rm -rf "$ROBOCZY/wtyczki"
	for f in "${PLIKI[@]}"; do mkdir -p "$ROBOCZY/$(dirname "$f")"; cp "$f" "$ROBOCZY/$f"; done
}

# probuj <opis> <plik-wzgledny> <kotwica-F> <sed-mutacja|USUN> <fragment oczekiwany w wyjsciu>
probuj() {
	local opis="$1" plik="$2" kotwica="$3" mutacja="$4" oczekiwane="$5" przed po wyjscie kod=0
	PODLOZONE=$(( PODLOZONE + 1 ))
	wgraj_czysty
	if [ "$mutacja" = "USUN" ]; then
		rm -f "$ROBOCZY/$plik"
	else
		if [ "$(grep -cF -- "$kotwica" "$ROBOCZY/$plik")" -eq 0 ]; then
			printf '  KOTW mutacja NIE weszla (kotwica nie ma w co trafic): %s\n' "$opis"; KOTWICE=$(( KOTWICE + 1 )); return
		fi
		przed="$(cksum < "$ROBOCZY/$plik")"; sed -i "$mutacja" "$ROBOCZY/$plik"; po="$(cksum < "$ROBOCZY/$plik")"
		if [ "$przed" = "$po" ]; then
			printf '  KOTW mutacja NIE weszla (plik bez zmian): %s\n' "$opis"; KOTWICE=$(( KOTWICE + 1 )); return
		fi
	fi
	wyjscie="$( CSV_BLIZNIAKI_KORZEN="$ROBOCZY" bash "$STRAZNIK" 2>&1 )" || kod=$?
	if [ "$kod" -eq 1 ] && printf '%s\n' "$wyjscie" | grep -q -- "$oczekiwane"; then
		printf '  OK   straznik PADL na: %-62s (EXIT=%s)\n' "$opis" "$kod"
	else
		printf '  BLAD straznik NIE zlapal: %-59s (EXIT=%s, brak w wyjsciu: %s)\n' "$opis" "$kod" "$oczekiwane"
		printf '%s\n' "$wyjscie" | tail -3 | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

echo "=== PROBA KONTROLNA: czy straznik blizniakow CSV widzi podlozone rozjazdy ==="
# A. segregator: `"\t", "\r"` → `"\r"` (kotwica: dokładnie ten fragment linii z listą)
probuj "A. segregator bez tabulatora w zestawie" "wtyczki/segregator-maila/includes/csv.php" \
	"array( '=', '+', '-', '@', \"\\t\", \"\\r\" )" \
	"s/array( '=', '+', '-', '@', \"\\\\t\", \"\\\\r\" )/array( '=', '+', '-', '@', \"\\\\r\" )/" \
	"nie neutralizuje: tabulator"
# B. ChatBot: stała bez CR
probuj "B. stala ChatBota bez powrotu karetki" "wtyczki/chatbot-live/includes/csv.php" \
	"\"\\t\", \"\\r\", \"'\" )" \
	"s/\"\\\\t\", \"\\\\r\", \"'\" )/\"\\\\t\", \"'\" )/" \
	"nie neutralizuje: CR"
# C. kopia ChatBota znika z pomiaru → podłoga liczby kopii
probuj "C. kopia ChatBota wypada z pomiaru (podloga MIN_KOPII)" "wtyczki/chatbot-live/includes/csv.php" \
	"" "USUN" "PODLOGA"

echo "=== KONTROLA NA PRAWDZIWYM REPOZYTORIUM — straznik mierzy wtyczki/ z drzewa, nie kopie (#298 r6 W1) ==="
# Mutanty wyzej biegna na KOPII trzech nazwanych plikow; to, czego strazniK ma pilnowac naprawde — „czwarta kopia
# dopisana pod inna nazwa" w CALYM wtyczki/ — jest mierzone tylko tutaj: bieg bez CSV_BLIZNIAKI_KORZEN, na repo.
kod=0; wyjscie="$( bash "$STRAZNIK" 2>&1 )" || kod=$?
ile_repo="$( printf '%s\n' "$wyjscie" | grep -oE '^kopii: [0-9]+' | grep -oE '[0-9]+' )"
# Strażnik sam egzekwuje podłogę (MIN_KOPII → EXIT=1), więc tu rozstrzyga kod wyjścia; liczba kopii jest meldowana, nie oceniana.
if [ "$kod" -eq 0 ] && [ -n "$ile_repo" ]; then
	echo "  OK   repozytorium: EXIT=0, kopii: $ile_repo (podłogę egzekwuje strażnik), wszystkie z kompletem"
else
	echo "  BLAD repozytorium: EXIT=$kod, kopii: ${ile_repo:-?} — rozjazd albo kopia poza pomiarem"; printf '%s\n' "$wyjscie" | tail -4 | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
fi

echo "=== KONTROLA ODWROTNA — czysta kopia MA przejsc ==="
wgraj_czysty; kod=0
wyjscie="$( CSV_BLIZNIAKI_KORZEN="$ROBOCZY" bash "$STRAZNIK" 2>&1 )" || kod=$?
if [ "$kod" -eq 0 ] && printf '%s\n' "$wyjscie" | grep -q '^kopii: 3 '; then
	echo "  OK   czysta kopia: EXIT=0, kopii: 3"
else
	echo "  BLAD czysta kopia: EXIT=$kod (oczekiwano 0 i 'kopii: 3')"; printf '%s\n' "$wyjscie" | tail -4 | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
fi

echo
echo "podlozone: $PODLOZONE · zlapane: $(( PODLOZONE - BLEDY - KOTWICE )) · kotwice nietrafione: $KOTWICE · bledy: $BLEDY"
if [ "$KOTWICE" -ne 0 ]; then
	echo "CSV-BLIZNIAKI-PROBA: $KOTWICE mutacji NIE weszlo — proba nie mierzy tego, co obiecuje (kod sie zmienil?)." >&2
	exit 1
fi
if [ "$BLEDY" -ne 0 ]; then
	echo "CSV-BLIZNIAKI-PROBA: straznik NIE JEST skalibrowany ($BLEDY). EXIT=1" >&2
	exit 1
fi
echo "CSV-BLIZNIAKI-PROBA: straznik skalibrowany — $PODLOZONE/$PODLOZONE mutantow zlapanych, czysta kopia przechodzi. EXIT=0"
exit 0
