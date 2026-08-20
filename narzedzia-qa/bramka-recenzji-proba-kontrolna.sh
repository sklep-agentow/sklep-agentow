#!/bin/bash
# bramka-recenzji-proba-kontrolna.sh — dowód, że bramka recenzji nie jest ozdobą.
#
# Bramka, która nigdy nie zapaliła się na czerwono, jest nieodróżnialna od bramki
# zepsutej. Ten skrypt PODKŁADA jej pod nos oba przypadki, o które chodzi:
# prawdziwy NIEMY ZGON (ma paść) i brak recenzji ZGODNY Z ZASADAMI na PR-ze
# zmieniającym CI (ma przepuścić) — plus przypadek-pułapkę, w którym PR rusza
# workflow, ale CLI jednak ruszyło: wyjątek NIE MOŻE wtedy przykryć zgonu.
#
# Danych nie bierze z GitHuba: listę zmian, plik wyjścia CLI i odpowiedź API
# podstawia plikami, więc próba działa na biurku i w CI tak samo.
#
# Kody wyjścia: 0 = wszystkie podłożone przypadki rozpoznane poprawnie
#               1 = bramka pomyliła się choć raz
set -u

KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRAMKA="$KORZEN/narzedzia-qa/bramka-recenzji.sh"
ROBOCZY="$(mktemp -d)"
trap 'rm -rf "$ROBOCZY"' EXIT

BLEDY=0
TERAZ=1000000000

# Odpowiedź API z komentarzem recenzenta powstałym w trakcie przebiegu.
cat > "$ROBOCZY/komentarz-jest.json" <<'JSON'
[
  { "user": { "login": "claude[bot]" }, "created_at": "2001-09-09T01:47:00Z" }
]
JSON

# Pusta lista — recenzent nie napisał nic.
echo '[]' > "$ROBOCZY/komentarza-brak.json"

# Komentarz JEST, ale stary: z poprzedniej recenzji, sprzed startu tego przebiegu.
cat > "$ROBOCZY/komentarz-stary.json" <<'JSON'
[
  { "user": { "login": "claude[bot]" }, "created_at": "1999-01-01T00:00:00Z" }
]
JSON

# Komentarz jest, ale nie od recenzenta — człowiek pisał pod PR-em.
cat > "$ROBOCZY/komentarz-czlowieka.json" <<'JSON'
[
  { "user": { "login": "szydlowskikonrad95-art" }, "created_at": "2001-09-09T01:47:00Z" }
]
JSON

# Plik wyjścia CLI: istnieje = CLI wystartowało.
echo '{"type":"result"}' > "$ROBOCZY/wynik-cli.json"
BRAK_PLIKU="$ROBOCZY/nie-ma-takiego-pliku.json"

# sprawdz <opis> <oczekiwany-kod> <czas-sekund> <zmienione-pliki> <plik-cli> <zrodlo-komentarzy>
sprawdz() {
	local opis="$1" oczekiwany="$2" czas="$3" zmiany="$4" plik_cli="$5" komentarze="$6"
	local wyjscie kod
	wyjscie="$(
		RECENZJA_START="$TERAZ" \
		RECENZJA_KONIEC="$(( TERAZ + czas ))" \
		ZMIENIONE_PLIKI="$zmiany" \
		PLIK_WYNIKU_CLI="$plik_cli" \
		ZRODLO_KOMENTARZY="$komentarze" \
		bash "$BRAMKA" 2>&1
	)"
	kod=$?
	if [ "$kod" -eq "$oczekiwany" ]; then
		printf '  OK   %-58s (EXIT=%s)\n' "$opis" "$kod"
	else
		printf '  BLAD %-58s (EXIT=%s, oczekiwano %s)\n' "$opis" "$kod" "$oczekiwany"
		printf '%s\n' "$wyjscie" | sed 's/^/         /'
		BLEDY=$(( BLEDY + 1 ))
	fi
}

echo "=== PODKLADAM NIEMY ZGON — bramka MA PASC ==="
sprawdz "zgon po 20 s, ani jednego komentarza"        1 20  "wtyczki/segregator-maila/includes/ai.php" "$BRAK_PLIKU"        "$ROBOCZY/komentarza-brak.json"
sprawdz "20 s, ale komentarz-znacznik JUZ jest"       1 20  "wtyczki/segregator-maila/includes/ai.php" "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarz-jest.json"
sprawdz "cztery minuty mielenia i zero komentarzy"    1 240 "wtyczki/segregator-maila/includes/ai.php" "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarza-brak.json"
sprawdz "komentarz jest, ale z POPRZEDNIEJ recenzji"  1 240 "wtyczki/segregator-maila/includes/ai.php" "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarz-stary.json"
sprawdz "pisal czlowiek, nie recenzent"               1 240 "wtyczki/segregator-maila/includes/ai.php" "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarz-czlowieka.json"
sprawdz "dokladnie 60 s — prog ma byc PRZEKROCZONY"   1 60  "wtyczki/segregator-maila/includes/ai.php" "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarz-jest.json"

echo
echo "=== PODKLADAM LEGALNY WYJATEK — bramka MA PRZEPUSCIC ==="
sprawdz "PR rusza recenzent.yml, CLI nie wystartowalo" 0 5  ".github/workflows/recenzent.yml"          "$BRAK_PLIKU"        "$ROBOCZY/komentarza-brak.json"
sprawdz "PR rusza quality.yml, CLI nie wystartowalo"   0 5  ".github/workflows/quality.yml"            "$BRAK_PLIKU"        "$ROBOCZY/komentarza-brak.json"
sprawdz "PR rusza workflow OBOK innych plikow"         0 5  ".github/workflows/recenzent.yml narzedzia-qa/bramki.sh" "$BRAK_PLIKU" "$ROBOCZY/komentarza-brak.json"

echo
echo "=== PULAPKA: wyjatek NIE MOZE przykryc prawdziwego zgonu ==="
sprawdz "PR rusza CI, ale CLI RUSZYLO i zdechlo po 20 s" 1 20 ".github/workflows/quality.yml"           "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarza-brak.json"

echo
echo "=== KONTROLA ODWROTNA: prawdziwa recenzja ma PRZECHODZIC ==="
sprawdz "cztery minuty i komentarz recenzenta"          0 240 "wtyczki/segregator-maila/includes/ai.php" "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarz-jest.json"
sprawdz "recenzja na PR-ze do CI, gdy jednak sie odbyla" 0 240 ".github/workflows/quality.yml"            "$ROBOCZY/wynik-cli.json" "$ROBOCZY/komentarz-jest.json"

echo
echo "=== KONTROLA WEJSCIA: brak danych to kod 2, nie ciche zielone ==="
kod=0
wyjscie="$( RECENZJA_KONIEC=$TERAZ bash "$BRAMKA" 2>&1 )" || kod=$?
if [ "$kod" -eq 2 ]; then
	printf '  OK   %-58s (EXIT=%s)\n' "brak RECENZJA_START konczy sie kodem 2" "$kod"
else
	printf '  BLAD %-58s (EXIT=%s, oczekiwano 2)\n' "brak RECENZJA_START konczy sie kodem 2" "$kod"
	printf '%s\n' "$wyjscie" | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
fi

kod=0
wyjscie="$(
	RECENZJA_START="$TERAZ" RECENZJA_KONIEC="$(( TERAZ + 240 ))" \
	ZMIENIONE_PLIKI="wtyczki/segregator-maila/includes/ai.php" \
	PLIK_WYNIKU_CLI="$ROBOCZY/wynik-cli.json" \
	ZRODLO_KOMENTARZY="$ROBOCZY/nie-ma-takiego-json.json" \
	bash "$BRAMKA" 2>&1
)" || kod=$?
if [ "$kod" -eq 2 ]; then
	printf '  OK   %-58s (EXIT=%s)\n' "nieczytelne zrodlo komentarzy = kod 2" "$kod"
else
	printf '  BLAD %-58s (EXIT=%s, oczekiwano 2)\n' "nieczytelne zrodlo komentarzy = kod 2" "$kod"
	printf '%s\n' "$wyjscie" | sed 's/^/         /'
	BLEDY=$(( BLEDY + 1 ))
fi

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "NIEMY ZGON ZOSTANIE ZAUWAZONY, A LEGALNY WYJATEK PRZEPUSZCZONY — wszystkie przypadki rozpoznane"
	exit 0
fi
echo "BRAMKA RECENZJI POMYLILA SIE $BLEDY RAZ(Y) — nie oddawaj jej w tym stanie"
exit 1
