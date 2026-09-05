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
KOTWICE=0
ODWROTNA=0
# ⛔ KLASA 138: TRZY rozne rzeczy = TRZY liczniki. Wspolny licznik kaze skryptowi orzec
#    o PRODUKCIE rzecz, ktorej NIE ZMIERZYL.
#      BLEDY    straznik nie zlapal mutanta  -> EXIT=1, wada produktu
#      KOTWICE  kotwica sed zgnila           -> EXIT=2, proba nic nie zmierzyla
#      ODWROTNA czysty kod nie przechodzi    -> EXIT=2, proba nic nie znaczy
podsumowanie_proby() {           # $1 = komunikat, gdy wszystko zmierzone i zielone
  # ⛔ KOLEJNOSC: padnieta kontrola odwrotna UNIEWAZNIA przebieg (straznik pada zawsze),
  #    wiec sprawdzana jest PIERWSZA, a licznik BLEDY nie jest wtedy werdyktem.
  if [ "$ODWROTNA" -gt 0 ]; then
    echo "KONTROLA ODWROTNA PADLA ($ODWROTNA) — czysty kod NIE przechodzi, wiec proba NIC nie znaczy."
    echo "  Licznik BLEDY=$BLEDY jest NIEWIARYGODNY i NIE jest werdyktem o produkcie."
    echo "  Sprawdz warunki wstepne (zaleznosci, stos, dane logowania), potem powtorz."
    exit 2
  fi
  if [ "$KOTWICE" -gt 0 ]; then
    echo "PROBA NIC NIE ZMIERZYLA W $KOTWICE PRZYPADKACH — kotwica sed zgnila po refaktorze."
    echo "  To wada PRZYRZADU, nie straznika: zanim oskarzysz produkt, uruchom straznika BEZPOSREDNIO."
    exit 2
  fi
  if [ "$BLEDY" -gt 0 ]; then
    echo "$2"
    exit 1
  fi
  echo "$1"
  exit 0
}

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
		printf '  BLAD mutacja NIE weszla: %-52s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
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
		printf '  BLAD mutacja NIE weszla: %-52s\n' "$opis"; KOTWICE=$((KOTWICE+1)); return
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
	printf '  BLAD mutacja NIE weszla: %-52s\n' "slad w prawdziwym pliku wtyczki"; KOTWICE=$((KOTWICE+1))
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
	echo "  BLAD czysta wtyczka NIE przechodzi skanu sladow"; ODWROTNA=$((ODWROTNA+1))
fi
rm -rf "$PIASKOWNICA"

echo
echo "=== KONTROLA ODWROTNA: czysty stan ma przechodzic ==="
przywroc
if bash $BRAMKI --tryb pelny > /dev/null 2>&1; then
	echo "  OK   komplet bramek na czystym drzewie (EXIT=0)"
else
	echo "  BLAD komplet bramek NIE przechodzi na czystym drzewie"; ODWROTNA=$((ODWROTNA+1))
fi
if bash narzedzia-qa/sprawdz-manifest.sh > /dev/null 2>&1; then
	echo "  OK   straznik manifestu na czystym drzewie (EXIT=0)"
else
	echo "  BLAD straznik manifestu NIE przechodzi na czystym drzewie"; ODWROTNA=$((ODWROTNA+1))
fi

echo
echo "=== POMINIECIE BRAMKI golden MA MIEC DOKLADNIE JEDEN POWOD ==="
# ⛔ POWOD ISTNIENIA TEGO PRZYPADKU. To jedyne miejsce, ktore w tym PR-ze REGRESOWALO: runda 4
# zrownala warunek pominiecia z `wymagaj_narzedzi` „dla zgodnosci" i poszerzyla ciche pominiecie
# z jednego powodu na cztery — trzy z nich do tamtej rundy konczyly sie `padnij` (exit 2), a po
# niej dawaly „WSZYSTKIE WYKONANE BRAMKI ZIELONE" i EXIT=0. Naprawa w rundzie 6 byla mierzona
# skryptem JEDNORAZOWYM, wiec nikt nie mogl jej odtworzyc (recenzja r6, WAZNA 1). Teraz mierzy ja
# ten plik, przy kazdym przelocie prob kontrolnych.
BEZ_UNZIP="$TMPDIR_PROBY/bin-bez-unzip"
ATRAPA_D="$TMPDIR_PROBY/bin-atrapa-docker"
mkdir -p "$BEZ_UNZIP" "$ATRAPA_D"
for _d in /usr/local/sbin /usr/local/bin /usr/sbin /usr/bin /sbin /bin; do
	[ -d "$_d" ] || continue
	for _f in "$_d"/*; do
		_n=$(basename "$_f")
		[ "$_n" = unzip ] && continue
		[ -e "$BEZ_UNZIP/$_n" ] || ln -s "$_f" "$BEZ_UNZIP/$_n" 2> /dev/null
	done
done
printf '#!/bin/sh\nexit 1\n' > "$ATRAPA_D/docker"; chmod +x "$ATRAPA_D/docker"

# ⛔ WARUNEK BAZOWY NAJPIERW. Ten przypadek porownuje trzy srodowiska, a jedno z nich to
# „konto, ktore Dockera WIDZI". Wykonawca CI chodzi jako `ciuser` bez dostepu do `docker.sock`
# (B-3) — dla niego KAZDE srodowisko jest „bez Dockera", wiec porownanie nie ma sensu i wychodzi
# odwrotnie. Zlapane na zywo: przebieg CI po rundzie 7 zaswiecil czerwono na TYM przypadku,
# choc kod byl poprawny. Brak warunkow ma dawac POMINIECIE, nie falszywa czerwien.
if ! docker ps > /dev/null 2>&1; then
	echo "  POMINIETE: to konto nie widzi Dockera — nie ma jak odroznic 'brak Dockera' od 'brak unzip'"
	echo "             (przelot reczny przed wydaniem: bash narzedzia-qa/bramki-proba-kontrolna.sh)"
# ⛔ KONTROLA SAMYCH SRODOWISK PROBY. Bez niej mierzylabym wlasna atrape, nie kod.
elif PATH="$BEZ_UNZIP" command -v unzip > /dev/null 2>&1; then
	echo "  BLAD srodowisko 'bez unzip' MA unzip — proba nic nie mierzy"; BLEDY=$((BLEDY+1))
elif ! PATH="$BEZ_UNZIP" command -v docker > /dev/null 2>&1; then
	echo "  POMINIETE: srodowisko 'bez unzip' nie ma dockera — nie ma jak rozdzielic powodow"
elif PATH="$ATRAPA_D:$PATH" docker ps > /dev/null 2>&1; then
	echo "  BLAD atrapa dockera nie udaje slepego konta — proba nic nie mierzy"; BLEDY=$((BLEDY+1))
else
	# ⛔ `env -u BEZ_STOSU_WP`: ten przypadek mierzy sciezke DOCKEROWA, a na wykonawcy GitHuba
	# (D41) deklaracja stoi w srodowisku calego joba — bez odciecia dziedziczy ja kazdy z trzech
	# pomiarow i „docker+unzip” liczy 1 zamiast 0. Zlapane przelotem prob z BEZ_STOSU_WP=1 (5.09):
	# proba samodzielnie EXIT=0, pod deklaracja EXIT=1. Deklaracje mierzy osobny przypadek nizej.
	ile_pominiec() { env -u BEZ_STOSU_WP "$@" BRAMKI_TYLKO_FUNKCJE=1 bash "$BRAMKI" 2>&1 | grep -c "bramka golden bedzie POMINIETA"; }

	_a=$(ile_pominiec PATH="$PATH")
	_b=$(ile_pominiec PATH="$ATRAPA_D:$PATH")
	_c=$(ile_pominiec PATH="$BEZ_UNZIP")
	if [ "$_a" = 0 ] && [ "$_b" = 1 ] && [ "$_c" = 0 ]; then
		echo "  OK   pominiecie tylko przy braku Dockera (docker+unzip: $_a, bez dockera: $_b, docker bez unzip: $_c)"
	else
		printf '  BLAD zle powody pominiecia: docker+unzip=%s (ma byc 0), bez dockera=%s (ma byc 1), docker bez unzip=%s (ma byc 0)\n' "$_a" "$_b" "$_c"
		BLEDY=$((BLEDY+1))
	fi

	# ⛔ PROBA KONTROLNA TEGO PRZYPADKU: przywracamy REGRESJE z rundy 4 i sprawdzamy, czy ja widac.
	przywroc
	python3 - "$BRAMKI" <<'PYMUT'
import io, sys
P = sys.argv[1]
s = io.open(P, encoding='utf-8').read()
kot = 'if ! docker ps > /dev/null 2>&1; then\n\tDOCKER_WIDOCZNY=nie\n\tPOWOD_BEZ_GOLDENA="to konto nie widzi Dockera"\nfi'
if kot in s:
    mut = ('if ! docker ps > /dev/null 2>&1; then\n\tDOCKER_WIDOCZNY=nie\n\tPOWOD_BEZ_GOLDENA="to konto nie widzi Dockera"\n'
           'elif ! command -v unzip > /dev/null 2>&1; then\n\tDOCKER_WIDOCZNY=nie\n\tPOWOD_BEZ_GOLDENA="brak unzip"\nfi')
    io.open(P, 'w', encoding='utf-8').write(s.replace(kot, mut, 1))
    print('MUTACJA_OK')
else:
    print('MUTACJA_NIEUDANA')
PYMUT
	_c2=$(ile_pominiec PATH="$BEZ_UNZIP")
	if [ "$_c2" = 1 ]; then
		echo "  OK   zlapane: powrot regresji z rundy 4 (brak unzip znowu POMIJA goldena zamiast go wywalic)"
	else
		echo "  BLAD PRZESZLO NIEZAUWAZONE: poszerzony warunek pominiecia (docker bez unzip: $_c2, mialo byc 1)"
		BLEDY=$((BLEDY+1))
	fi
	przywroc
fi

echo
echo "=== DEKLARACJA BEZ_STOSU_WP=1 POMIJA goldena JAWNIE, Z WLASNYM POWODEM (D41, 5.09) ==="
# Wykonawca GitHuba Dockera MA, a stosu nie — bez tej deklaracji golden swiecilby czerwono na
# kazdym PR-ze. Mierzone DWUKIERUNKOWO: z deklaracja 1 pominiecie i powod z nazwa zmiennej,
# bez deklaracji zero (na koncie z Dockerem) — a mutant bez tej galezi ma byc ZLAPANY.
# Ten przypadek nie potrzebuje Dockera: deklaracja ma dzialac takze tam, gdzie go nie ma
# (wtedy pominiecie i tak jest jedno, tylko powod dockerowy zostaje nadpisany deklaracja).
_pom_z=$(BEZ_STOSU_WP=1 BRAMKI_TYLKO_FUNKCJE=1 bash "$BRAMKI" 2>&1 | grep -c "bramka golden bedzie POMINIETA")
_powod=$(BEZ_STOSU_WP=1 BRAMKI_TYLKO_FUNKCJE=1 bash "$BRAMKI" 2>&1 | grep -c "BEZ_STOSU_WP=1 — wykonawca bez stosu WP")
if [ "$_pom_z" = 1 ] && [ "$_powod" = 1 ]; then
	echo "  OK   BEZ_STOSU_WP=1 → 1 pominiecie, powod nazywa deklaracje"
else
	echo "  BLAD BEZ_STOSU_WP=1: pominiec=$_pom_z (ma byc 1), powod z nazwa=$_powod (ma byc 1)"; BLEDY=$((BLEDY+1))
fi
if docker ps > /dev/null 2>&1; then
	_pom_bez=$(env -u BEZ_STOSU_WP BRAMKI_TYLKO_FUNKCJE=1 bash "$BRAMKI" 2>&1 | grep -c "bramka golden bedzie POMINIETA")
	if [ "$_pom_bez" = 0 ]; then
		echo "  OK   bez deklaracji na koncie z Dockerem golden NIE jest pomijany"
	else
		echo "  BLAD bez deklaracji pominiec=$_pom_bez (ma byc 0) — deklaracja wycieka albo Docker niewidoczny"; BLEDY=$((BLEDY+1))
	fi
fi
# Mutant: galaz deklaracji USUNIETA — deklaracja ma przestac dzialac i ma to byc widac.
przywroc
python3 - "$BRAMKI" <<'PYMUT'
import io, sys
P = sys.argv[1]
s = io.open(P, encoding='utf-8').read()
kot = 'if [ "${BEZ_STOSU_WP:-}" = 1 ]; then\n\tDOCKER_WIDOCZNY=nie\n\tPOWOD_BEZ_GOLDENA="BEZ_STOSU_WP=1 — wykonawca bez stosu WP (Docker jest, kontenera bazy nie ma)"\nfi\n'
if kot in s:
    io.open(P, 'w', encoding='utf-8').write(s.replace(kot, '', 1))
    print('MUTACJA_OK')
else:
    print('MUTACJA_NIEUDANA')
PYMUT
_pom_mut=$(BEZ_STOSU_WP=1 BRAMKI_TYLKO_FUNKCJE=1 bash "$BRAMKI" 2>&1 | grep -c "BEZ_STOSU_WP=1 — wykonawca bez stosu WP")
if [ "$_pom_mut" = 0 ]; then
	echo "  OK   zlapane: bez galezi deklaracji powod z nazwa znika ($_pom_mut)"
else
	echo "  BLAD PRZESZLO NIEZAUWAZONE: mutant bez galezi deklaracji dalej drukuje powod ($_pom_mut)"; BLEDY=$((BLEDY+1))
fi
przywroc

echo
# ⛔ PRZYPADEK NA CALY SKRYPT, NIE NA SAMA FUNKCJE (runda 11). Powyzsze przypadki mierza
#    `bramka()` w izolacji; ten pyta o to, co naprawde trafia do opisu PR-a: KOD WYJSCIA
#    calego `bramki.sh`. Wada z rundy 10 zylaby dalej, gdyby ktos naprawil licznik, a zapomnial
#    o ogonie — albo odwrotnie. Bramka `slady` jest w trybie tekstowym, wiec przypadek jest tani.
probuj "bramka odmawia pomiaru (kod 2) — caly zestaw MUSI byc czerwony" \
	"sed -i 's|^bramka slady      uruchom_slady|bramka slady      bash -c \"exit 2\"|' $BRAMKI" \
	"bash $BRAMKI --tryb tekstowy" \
	"NIE ZMIERZONO"

echo
echo "--- TRYB PELNY: kod 2 ma dac NIE ZMIERZONO, a nie 'ktora wypadla' (runda 12) ---"
# ⛔ Przypadek w trybie tekstowym (wyzej) NIE POKRYWA goldena: golden jest w BRAMKI_PHP,
#    wiec w trybie tekstowym jest POMIJANY — a to jedyna bramka, ktora kod 2 deklaruje wprost
#    (testy/golden-stos-probny.sh:7). Recenzja rundy 11 nazwala to klasa 11: pokrycie obiecane
#    szersze niz faktyczne. Do rundy 12 sufit stal PO progu i w trybie pelnym byl MARTWY, bo
#    PROG rowna sie tam liczbie bramek uruchomionych, a kod 2 nie podbija WYKONANE — wiec kazde
#    NIEZMIERZONE > 0 znaczylo WYKONANE < PROG i wygrywala galaz progu z komunikatem
#    "sprawdz, ktora WYPADLA" o bramce, ktora NIE wypadla.
#    Mierzymy SAM OGON z zywego pliku (nie kopie) na scenariuszu trybu pelnego, bez czekania
#    na dziewieciominutowy przebieg. Kontrola wyciecia nizej pilnuje, ze ogon nie jest pusty.
OGON="$(sed -n '/^MAX_NIEZMIERZONYCH=/,$p' "$BRAMKI")"
if ! printf '%s' "$OGON" | grep -q 'NIE WSZYSTKO ZMIERZONE'; then
	echo "  BLAD wyciecie ogona z $BRAMKI jest puste albo niepelne — pomiar NIE ISTNIEJE" >&2
	BLEDY=$((BLEDY+1))
else
	# scenariusz: tryb pelny, 13 bramek, golden skonczyl kodem 2 -> 12 wykonanych, 1 niezmierzona
	_wy=$(
		{
			printf 'NIEZMIERZONE=(golden)\nWYKONANE=12\nPORAZKI=0\nPROG=13\nTRYB=pelny\n'
			printf '%s\n' "$OGON"
		} | bash 2>&1
		echo "KOD=$?"
	)
	_kod=$(printf '%s' "$_wy" | grep -oE 'KOD=[0-9]+' | tail -1 | cut -d= -f2)
	if printf '%s' "$_wy" | grep -q 'NIE WSZYSTKO ZMIERZONE' && [ "${_kod:-0}" -ne 0 ]; then
		printf '  OK   tryb pelny, golden EXIT=2 -> NIE WSZYSTKO ZMIERZONE (EXIT=%s)\n' "$_kod"
		printf '%s' "$_wy" | grep -m1 'STOP: NIE ZMIERZONO' | sed 's/^/         /'
	else
		printf '  BLAD tryb pelny, golden EXIT=2 -> brak zdania NIE WSZYSTKO ZMIERZONE (EXIT=%s)\n' "$_kod"
		printf '%s' "$_wy" | head -3 | sed 's/^/         /'
		BLEDY=$((BLEDY+1))
	fi
	# kontrola ODWROTNA: zero niezmierzonych i komplet wykonanych ma przejsc na zielono
	_wy2=$(
		{
			printf 'NIEZMIERZONE=()\nWYKONANE=13\nPORAZKI=0\nPROG=13\nTRYB=pelny\n'
			printf '%s\n' "$OGON"
		} | bash 2>&1
		echo "KOD=$?"
	)
	_kod2=$(printf '%s' "$_wy2" | grep -oE 'KOD=[0-9]+' | tail -1 | cut -d= -f2)
	if [ "${_kod2:-1}" -eq 0 ] && printf '%s' "$_wy2" | grep -q 'WSZYSTKIE WYKONANE BRAMKI ZIELONE'; then
		printf '  OK   tryb pelny, komplet zmierzony -> zielone (EXIT=%s)\n' "$_kod2"
	else
		printf '  BLAD tryb pelny, komplet zmierzony NIE daje zielonego (EXIT=%s)\n' "$_kod2"
		BLEDY=$((BLEDY+1))
	fi
fi

echo "--- kod wyjscia 2 to NIE ZMIERZONE, nie PORAZKA (I-12) ---"
# ⛔ Bramka `golden` deklaruje kod 2 wprost (testy/golden-stos-probny.sh:7), a `bramka()` do rundy 10
#    raportowala KAZDY niezerowy kod jako PORAZKA. Brak Dockera na koncie wykonawcy CI wygladal wiec
#    identycznie jak prawdziwa regresja produktu — czyli przyrzad mowil "znalazlam wade", nie ustaliwszy
#    niczego. Trzy kierunki, bo naprawa w jedna strone (kod 2 przestaje byc porazka) moze ZASLEPIC
#    bramke na kod 1; ten przypadek pilnuje, ze prawdziwa wada DALEJ jest porazka.
KATALOG_ATRAP="$(mktemp -d -t bramki-kody-XXXXXX)"
for _k in 0 1 2; do
	printf '#!/bin/sh\nexit %s\n' "$_k" > "$KATALOG_ATRAP/kod$_k.sh"
	chmod +x "$KATALOG_ATRAP/kod$_k.sh"
done
# funkcje bramka() wycinamy z zywego pliku — pomiar ma dotyczyc TEGO kodu, nie kopii
WYCIETA="$(sed -n '/^bramka() {/,/^}/p' "$BRAMKI")"
# ⛔ KONTROLA WYCIECIA: pusta funkcja = wszystkie trzy przypadki nizej daja PORAZKI=0 i wygladaja
#    na zielone. Zlapane 3.09: uzylam nieistniejacej zmiennej $KATALOG, sed czytal pusty plik,
#    a dwa z trzech przypadkow "przeszly". Bez tej kontroli pomiar milczaco nie istnieje.
if ! printf '%s' "$WYCIETA" | grep -q 'PORAZKI='; then
	echo "  BLAD wyciecie bramka() z $BRAMKI jest puste albo niepelne — pomiar kodow wyjscia NIE ISTNIEJE" >&2
	BLEDY=$((BLEDY+1))
fi
# ⛔ MIERZYMY DWIE WIELKOSCI, NIE JEDNA (runda 11). Do rundy 10 ta petla czytala sam licznik
#    PORAZKI — i dlatego przepuscila wade, ktora recenzent nazwal w rundzie 10: kod 2 slusznie
#    przestal byc porazka, ale DALEJ podbijal licznik WYKONANE, wiec prog przechodzil, PORAZKI
#    zostawalo zerem i caly zestaw konczyl sie "WSZYSTKIE WYKONANE BRAMKI ZIELONE" z EXIT=0
#    przy bramce, ktora nic nie zmierzyla. Asercja mierzaca JEDNA wielkosc nie widzi wady
#    siedzacej w DRUGIEJ. Format: kod:PORAZKI:WYKONANE.
for _para in "0:0:1" "1:1:1" "2:0:0"; do
	_kod="${_para%%:*}"; _reszta="${_para#*:}"
	_ocz_p="${_reszta%%:*}"; _ocz_w="${_reszta##*:}"
	_wynik=$(
		{
			printf 'POMINIETE=()\nNIEZMIERZONE=()\nWYKONANE=0\nPORAZKI=0\ndeclare -A WYNIKI\nTRYB=x\nDOCKER_WIDOCZNY=tak\nPOWOD_BEZ_GOLDENA=x\n'
			printf '%s\n' "$WYCIETA"
			printf 'bramka "atrapa" %s > /dev/null 2>&1\necho "$PORAZKI:$WYKONANE"\n' "$KATALOG_ATRAP/kod$_kod.sh"
		} | bash
	)
	if [ "$_wynik" = "$_ocz_p:$_ocz_w" ]; then
		printf '  OK   %-52s PORAZKI:WYKONANE=%s\n' "kod wyjscia $_kod -> $_ocz_p:$_ocz_w" "$_wynik"
	else
		printf '  BLAD %-52s PORAZKI:WYKONANE=%s, oczekiwano %s\n' "kod wyjscia $_kod" "$_wynik" "$_ocz_p:$_ocz_w"
		BLEDY=$((BLEDY+1))
	fi
done
rm -rf "$KATALOG_ATRAP"

echo
podsumowanie_proby "KURCZACY SIE ZESTAW BRAMEK ZOSTANIE ZAUWAZONY — wszystkie podlozone awarie zlapane" \
	"PRZEOCZONYCH AWARII: $BLEDY"
