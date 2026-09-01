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

# ⛔ WLASNE WARUNKI WSTEPNE (znalezisko I-12, 31.08). Ta proba uruchamia KOMPLET bramek,
# w tym phpcs i phpstan — a te potrzebuja `vendor/`. W swiezym klonie albo w oderwanym
# drzewie roboczym bez `composer install` obie padaja i proba meldowala wtedy
# „PRZEOCZONYCH AWARII: 1", czyli OSKARZALA PRODUKT o wade, ktorej nie ma.
# Zmierzone tego samego dnia: to samo repo, dwa katalogi — z `vendor/` kod 0 i 10 bramek
# zielonych, bez `vendor/` kod 1 i falszywy alarm. Brak warunkow wstepnych to „nie
# zmierzylam" (kod 2), nigdy „znalazlam wade" (kod 1).
for N in phpstan phpcs; do
	if [ ! -x "vendor/bin/$N" ]; then
		echo "PROBA-BRAMKI: brak vendor/bin/$N — komplet bramek nie ma czym liczyc." >&2
		echo "              To NIE jest wada bramek. Uruchom: composer install --no-scripts" >&2
		exit 2
	fi
done

KOPIA_B=$(mktemp)
KOPIA_W=$(mktemp)
BLEDY=0

# ⛔ WŁASNY, JEDNORAZOWY KATALOG TYMCZASOWY NA CZAS PRÓBY (1.09).
# Ta próba PODKŁADA błędy do plików i zaraz je PRZYWRACA. PHPStan rozpoznaje
# zmianę pliku po czasie modyfikacji z rozdzielczością SEKUNDY — podłożenie
# i przywrócenie mieszczą się w tej samej sekundzie, więc do trwałego cache
# wpada wynik policzony na ZEPSUTYM kodzie i zostaje tam po próbie.
# Zmierzone 1.09: po przelocie tej próby `bramki.sh` dawało phpstan EXIT=1,
# a po odsunięciu cache — EXIT=0 („No errors") na tym samym kodzie.
# ⚠️ To nie jest wada PHPStana ani bramki, tylko skutek uboczny próby, która
# celowo psuje pliki. Izolujemy ją: własny TMPDIR, kasowany razem z próbą,
# więc trwały cache (2 s zamiast 26 s) zostaje nietknięty.
TMPDIR_PROBY=$(mktemp -d)
export TMPDIR="$TMPDIR_PROBY"

cp "$BRAMKI" "$KOPIA_B"
cp "$WORKFLOW" "$KOPIA_W"
przywroc() { cp "$KOPIA_B" "$BRAMKI"; cp "$KOPIA_W" "$WORKFLOW"; }
trap 'przywroc; rm -f "$KOPIA_B" "$KOPIA_W"; rm -rf "$TMPDIR_PROBY"' EXIT

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

# 1. Ktos kasuje wywolanie phpstana — licznik ma zobaczyc, ze wykonano o jedna mniej.
#    Fraza BEZ liczby: dolozenie siodmej bramki (21.08) uniewaznilo kotwice
#    "wykonano 5 bramek" i przypadek zaczal przechodzic niezauwazony.
probuj "wywolanie phpstana skasowane (licznik)" \
	"sed -i '/^bramka phpstan /d' $BRAMKI" \
	"bash $BRAMKI --tryb pelny" \
	"wymaga co najmniej"

# 2. To samo, ale ktos „sprytnie" obniza prog, zeby licznik nie krzyczal —
#    wtedy krzyczy straznik manifestu.
probuj "phpstan skasowany I prog obnizony do 5 (manifest)" \
	"sed -i '/^bramka phpstan /d' $BRAMKI && sed -i 's/^PROG_PELNY=[0-9]*/PROG_PELNY=5/' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"NIKT JEJ NIE WOŁA"

# 3. Bramka znika z rejestru — manifest sie nie zgadza.
probuj "phpcs wyrzucony z rejestru BRAMKI=()" \
	"sed -i '/^BRAMKI=(/ s/ phpcs//' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"bramka zniknęła"

# 4. Ciche obnizenie poprzeczki bez ruszania bramek.
probuj "prog pelnego przebiegu obnizony do 4" \
	"sed -i 's/^PROG_PELNY=[0-9]*/PROG_PELNY=4/' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"poprzeczka obniżona"

# 4b. Bramka `slady` jest od 21.08 w manifescie, czyli jest TWARDYM MINIMUM.
#     Dwa te same zamachy co na phpcs: zniknięcie z rejestru i obniżenie progu.
probuj "slady wyrzucone z rejestru BRAMKI=()" \
	"sed -i '/^BRAMKI=(/ s/ slady//' $BRAMKI" \
	"bash narzedzia-qa/sprawdz-manifest.sh" \
	"'slady' jest w manifescie"

probuj "prog obnizony ponizej liczby bramek z manifestu" \
	"sed -i 's/^PROG_PELNY=[0-9]*/PROG_PELNY=6/' $BRAMKI" \
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
echo "=== BRAMKA slady: nowy slad w pliku wtyczki MA byc zlapany ==="
# Pracujemy na KOPII katalogu wtyczki — zaden plik repozytorium nie jest dotykany.
SKANER=narzedzia-qa/slady-wewnetrzne.sh
PIASKOWNICA=$(mktemp -d)
cp -r wtyczki/chatbot-live "$PIASKOWNICA/"
KOPIA_WTYCZKI="$PIASKOWNICA/chatbot-live"

slad_probuj() {
	local opis="$1" tresc="$2" fraza="$3" wyjscie kod=0
	cp -r wtyczki/chatbot-live/. "$KOPIA_WTYCZKI/"
	printf '%s\n' "$tresc" >> "$KOPIA_WTYCZKI/includes/silnik.php"
	if ! grep -qF "$tresc" "$KOPIA_WTYCZKI/includes/silnik.php"; then
		printf '  BLAD mutacja NIE weszla: %-52s\n' "$opis"; BLEDY=$((BLEDY+1)); return
	fi
	wyjscie=$(SLADY_ZALEGLOSCI=/dev/null bash "$SKANER" chatbot-live "$KOPIA_WTYCZKI" 2>&1) || kod=$?
	if [ "$kod" -ne 0 ] && printf '%s' "$wyjscie" | grep -qF "$fraza"; then
		printf '  OK   zlapane: %-56s (EXIT=%d)\n' "$opis" "$kod"
	else
		printf '  BLAD PRZESZLO NIEZAUWAZONE: %-42s (EXIT=%d)\n' "$opis" "$kod"; BLEDY=$((BLEDY+1))
	fi
}

slad_probuj "TODO w pliku wtyczki"           "// TODO: dokonczyc przed oddaniem" "znacznik roboczy"
slad_probuj "adres maszyny dev"              "// sprawdzone na http://localhost:8890/wp-admin/" "adres maszyny dev"
slad_probuj "odsylacz do naszego drzewa"     "// liczbe pilnuje testy/silnik-test.php" "ścieżka naszego drzewa roboczego"
slad_probuj "numer PR-a i recenzent"         "// uwaga recenzenta do PR #133" "nasz obieg pracy"
# BLIZNIAK: to samo o naszym obiegu, ale BEZ slowa "recenzja" i bez numeru PR-a.
# Wzorzec 1.0.0 tego nie widzial i zostawil taki komentarz w Segregatorze.
slad_probuj "nasze slownictwo bez numeru zgloszenia" "// zarzut z pierwszej rundy krytyk" "nasz obieg pracy"
slad_probuj "sciaga egzaminatora w komentarzu"       "// klasa #1 sciagi egzaminatora" "nasz obieg pracy"
# IMIE Z ZESPOLU: mianownik I odmiana osobno. Wzorzec `Dzidek` gubil odmiany, poprawka
# na `Dzidk` gubila mianownik — bez tych DWOCH przypadkow regresja wzorca przeszla po cichu.
slad_probuj "imie z zespolu w mianowniku"            "// feedback Dzidek 19.08: karty za ciasne" "nasz obieg pracy"
slad_probuj "imie z zespolu w odmianie"              "// uwaga od Dzidka 19.08: karty za ciasne" "nasz obieg pracy"

# ⭐ TO JEST TEN PRZYPADEK, O KTORY CHODZI W MANIFESCIE: nie „skaner widzi slad",
# tylko „CI STOI NA CZERWONO". Mutacja idzie do PRAWDZIWEGO pliku wtyczki, uruchamiane
# jest DOKLADNIE to polecenie, ktore wola quality.yml, a plik wraca na miejsce z kopii
# zapasowej niezaleznie od wyniku.
PLIK_WTYCZKI=wtyczki/chatbot-live/includes/silnik.php
KOPIA_PLIKU="$PIASKOWNICA/silnik.php.oryginal"
cp "$PLIK_WTYCZKI" "$KOPIA_PLIKU"
printf '\n// TODO: zdjac przed oddaniem — patrz testy/silnik-test.php (PR #999)\n' >> "$PLIK_WTYCZKI"
if grep -qF "zdjac przed oddaniem" "$PLIK_WTYCZKI"; then
	WYJ_CI=$(bash "$BRAMKI" --tryb pelny 2>&1); KOD_CI=$?
	cp "$KOPIA_PLIKU" "$PLIK_WTYCZKI"
	if [ "$KOD_CI" -ne 0 ] && printf '%s' "$WYJ_CI" | grep -qF "BRAMKA slady: EXIT=1"; then
		printf '  OK   zlapane: %-56s (EXIT=%d)\n' "podlozony slad ZAPALA CALE bramki.sh (to co wola CI)" "$KOD_CI"
		printf '%s\n' "$WYJ_CI" | grep -E "^  slady|^NIEZALICZONE" | sed 's/^/         /'
	else
		printf '  BLAD PRZESZLO NIEZAUWAZONE: %-42s (EXIT=%d)\n' "podlozony slad w bramki.sh" "$KOD_CI"; BLEDY=$((BLEDY+1))
	fi
else
	printf '  BLAD mutacja NIE weszla: %-52s\n' "slad w prawdziwym pliku wtyczki"; BLEDY=$((BLEDY+1))
fi
cp "$KOPIA_PLIKU" "$PLIK_WTYCZKI"

# Lista zaleglosci ma sie KURCZYC, a nie gnic: wpis, ktory do niczego nie pasuje,
# jest czerwony. Inaczej po refaktorze zostaje spis rzeczy, ktorych juz nie ma.
cp -r wtyczki/chatbot-live/. "$KOPIA_WTYCZKI/"
MARTWA_LISTA="$PIASKOWNICA/zaleglosci.txt"
echo "chatbot-live|includes/silnik.php|// slad, ktorego juz dawno nie ma" > "$MARTWA_LISTA"
WYJ=$(SLADY_ZALEGLOSCI="$MARTWA_LISTA" bash "$SKANER" chatbot-live "$KOPIA_WTYCZKI" 2>&1); KOD=$?
if [ "$KOD" -ne 0 ] && printf '%s' "$WYJ" | grep -qF "MARTWY"; then
	printf '  OK   zlapane: %-56s (EXIT=%d)\n' "martwy wpis na liscie zaleglosci" "$KOD"
else
	printf '  BLAD PRZESZLO NIEZAUWAZONE: %-42s (EXIT=%d)\n' "martwy wpis na liscie zaleglosci" "$KOD"; BLEDY=$((BLEDY+1))
fi

# Kontrola odwrotna DLA TEJ bramki: czysta wtyczka ma przechodzic, inaczej
# „naprawa" polegalaby na krzyczeniu na wszystko.
cp -r wtyczki/chatbot-live/. "$KOPIA_WTYCZKI/"
if SLADY_ZALEGLOSCI=/dev/null bash "$SKANER" chatbot-live "$KOPIA_WTYCZKI" > /dev/null 2>&1; then
	echo "  OK   czysta wtyczka przechodzi skan sladow (EXIT=0)"
else
	echo "  BLAD czysta wtyczka NIE przechodzi skanu sladow"; BLEDY=$((BLEDY+1))
fi
rm -rf "$PIASKOWNICA"

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
