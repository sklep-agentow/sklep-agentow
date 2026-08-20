#!/bin/bash
# Proba kontrolna zbicia szesciu zadan w jedno.
#
# Zbicie bramek do jednego zadania rodzi dokladnie jedno powazne ryzyko:
# kurczacy sie zestaw wyglada jak sukces. Szesc zielonych zadan bylo widac na
# liscie; jedno zadanie, ktore po cichu przestalo wolac phpstana, wyglada rownie
# zielono. Ten skrypt PODKLADA takie awarie po kolei i sprawdza, czy ktos je
# lapie — licznik w bramki.sh albo straznik manifestu.
#
# Uruchomienie: bash narzedzia-qa/bramki-proba-kontrolna.sh
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$KORZEN" || exit 2
BRAMKI=narzedzia-qa/bramki.sh
WORKFLOW=.github/workflows/quality.yml
KOPIA_B=$(mktemp)
KOPIA_W=$(mktemp)
BLEDY=0

cp "$BRAMKI" "$KOPIA_B"
cp "$WORKFLOW" "$KOPIA_W"
przywroc() { cp "$KOPIA_B" "$BRAMKI"; cp "$KOPIA_W" "$WORKFLOW"; }
trap 'przywroc; rm -f "$KOPIA_B" "$KOPIA_W"' EXIT

# $1 opis, $2 mutacja, $3 komenda straznika, $4 fraza, ktora ma paść
probuj() {
	local opis="$1" mutacja="$2" straznik="$3" fraza="$4"
	przywroc
	local przed po
	przed=$(md5sum "$BRAMKI" "$WORKFLOW" | md5sum)
	eval "$mutacja"
	po=$(md5sum "$BRAMKI" "$WORKFLOW" | md5sum)
	if [ "$przed" = "$po" ]; then
		printf '  BLAD mutacja NIE weszla: %-52s\n' "$opis"; BLEDY=$((BLEDY+1)); return
	fi
	local wyjscie kod
	wyjscie=$(eval "$straznik" 2>&1); kod=$?
	if [ "$kod" -ne 0 ] && printf '%s' "$wyjscie" | grep -qF "$fraza"; then
		printf '  OK   zlapane: %-56s (EXIT=%d)\n' "$opis" "$kod"
		printf '%s' "$wyjscie" | grep -F "$fraza" | head -1 | sed 's/^/         /'
	else
		printf '  BLAD PRZESZLO NIEZAUWAZONE: %-42s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
	fi
}

echo "=== PROBA KONTROLNA: czy kurczacy sie zestaw bramek zostanie zauwazony ==="

# 1. Ktos kasuje wywolanie phpstana — licznik ma zobaczyc, ze wykonano 5 z 6.
probuj "wywolanie phpstana skasowane (licznik)" \
	"sed -i '/^bramka phpstan /d' $BRAMKI" \
	"bash $BRAMKI --tryb pelny" \
	"STOP: wykonano 5 bramek"

# 2. To samo, ale ktos „sprytnie" obniza prog, zeby licznik nie krzyczal —
#    wtedy krzyczy straznik manifestu.
probuj "phpstan skasowany I prog obnizony do 5 (manifest)" \
	"sed -i '/^bramka phpstan /d' $BRAMKI && sed -i 's/^PROG_PELNY=6/PROG_PELNY=5/' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"NIKT JEJ NIE WOŁA"

# 3. Bramka znika z rejestru — manifest sie nie zgadza.
probuj "phpcs wyrzucony z rejestru BRAMKI=()" \
	"sed -i 's/^BRAMKI=(actionlint manifest php-lint phpcs phpstan gitleaks)/BRAMKI=(actionlint manifest php-lint phpstan gitleaks)/' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"bramka zniknęła"

# 4. Ciche obnizenie poprzeczki bez ruszania bramek.
probuj "prog pelnego przebiegu obnizony do 4" \
	"sed -i 's/^PROG_PELNY=6/PROG_PELNY=4/' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"poprzeczka obniżona"

# 5. Workflow przestaje wolac skrypt z bramkami.
probuj "quality.yml nie wola juz bramki.sh" \
	"sed -i 's|bash narzedzia-qa/bramki.sh|echo pomijam|' $WORKFLOW" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"NIE WOŁA narzedzia-qa/bramki.sh"

# 6. Furtka continue-on-error.
probuj "continue-on-error w quality.yml" \
	"sed -i 's|^  bramki:|  bramki:\\n    continue-on-error: true|' $WORKFLOW" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"continue-on-error"

# 7. Furtka `if:` na kroku z bramkami.
probuj "krok z bramkami dostal warunek if:" \
	"sed -i 's|      - name: Bramki jakosci|      - name: Bramki jakosci\\n        if: github.actor != '\\''nikt'\\''|' $WORKFLOW" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"krok z bramkami ma warunek if:"

# 8. Tryb tekstowy NIE moze byc furtka dla zmiany dotykajacej PHP.
przywroc
echo
echo "=== KONTROLA: zmiana z plikiem .php NIE moze wpasc w tryb tekstowy ==="
WYNIK=$(ZMIENIONE_PLIKI="dokument.md wtyczki/segregator-maila/includes/ai.php" bash $BRAMKI --tryb auto 2>&1 | grep -c 'tryb: pelny')
if [ "$WYNIK" -gt 0 ]; then
	echo "  OK   zmiana z plikiem .php wymusza tryb pelny"
else
	echo "  BLAD zmiana z plikiem .php NIE wymusila trybu pelnego"; BLEDY=$((BLEDY+1))
fi
WYNIK=$(ZMIENIONE_PLIKI="testy/cos.sh" bash $BRAMKI --tryb auto 2>&1 | grep -c 'tryb: pelny')
if [ "$WYNIK" -gt 0 ]; then
	echo "  OK   zmiana skryptu .sh tez wymusza tryb pelny"
else
	echo "  BLAD zmiana .sh potraktowana jako tekstowa"; BLEDY=$((BLEDY+1))
fi

echo
echo "=== KONTROLA ODWROTNA: czysty stan ma przechodzic ==="
przywroc
if bash $BRAMKI --tryb pelny > /dev/null 2>&1; then
	echo "  OK   komplet bramek na czystym drzewie (EXIT=0)"
else
	echo "  BLAD komplet bramek NIE przechodzi na czystym drzewie"; BLEDY=$((BLEDY+1))
fi
if bash narzedzia-qa/sprawdz-manifest.sh > /dev/null 2>&1; then
	echo "  OK   straznik manifestu na czystym drzewie (EXIT=0)"
else
	echo "  BLAD straznik manifestu NIE przechodzi na czystym drzewie"; BLEDY=$((BLEDY+1))
fi

echo
if [ "$BLEDY" -eq 0 ]; then
	echo "KURCZACY SIE ZESTAW BRAMEK ZOSTANIE ZAUWAZONY — wszystkie podlozone awarie zlapane"
	exit 0
fi
echo "PRZEOCZONYCH AWARII: $BLEDY"
exit 1
