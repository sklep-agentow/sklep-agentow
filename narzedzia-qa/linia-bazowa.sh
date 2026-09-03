#!/bin/bash
# linia-bazowa.sh — LINIA BAZOWA TESTÓW na stosie warsztatowym.               WERSJA 0.1.0
#
# PO CO: etap napraw po audycie (23.08). Żeby naprawa jednej rzeczy nie zepsuła po cichu
# innej, trzeba WIEDZIEĆ, co przechodziło PRZED — i porównać PO. CI testów nie uruchamia
# (wymagają żywego Dockera), więc to jest ręczny rytuał koordynatorki z wynikiem w pliku.
#
# CO ROBI:
#   1. zrzuca bazę stosu (`wp db export`) — bo część testów audytu NIE sprząta po sobie
#      (zmierzone 23.08: test-awaria-zapisu.php nadpisuje klucz API i hasło IMAP),
#   2. uruchamia KAŻDY test z listy (PHP przez wp eval-file w kontenerze, JS przez node,
#      skrypty bash w korzeniu repo) z limitem czasu, zapisuje EXIT i ogon wyjścia,
#   3. po KAŻDYM teście z `audyt/boty/` przywraca bazę z zrzutu (testy audytu psują stan);
#      testom z `testy/` ufa, że sprzątają (tak są pisane — prefiks danych + kasowanie),
#   4. na końcu przywraca bazę i kod wtyczek z repo (`docker cp`), żeby stos = HEAD,
#   5. pisze wynik: `audyt/linia-bazowa/<etykieta>.tsv` (nazwa<TAB>EXIT<TAB>sekundy)
#      + blok w `audyt/LINIA-BAZOWA-TESTOW.md` z SHA i datą; logi w katalogu poza repo.
#
# UŻYCIE:
#   bash narzedzia-qa/linia-bazowa.sh <etykieta>                  # np. baza-766b4c9, po-C4
#   bash narzedzia-qa/linia-bazowa.sh --porownaj <A.tsv> <B.tsv>  # regresje (0→1) i naprawy (1→0)
# WYMAGA: docker, node (testy JS), `/root/.claude/sklep-admin-roboczy.env` (WP_ADMIN_USER/PASS).
# KODY:  0 = przebieg zrobiony (także gdy testy padały — to linia bazowa, nie bramka)
#        1 = tryb --porownaj: są REGRESJE (0→1)
#        2 = jedno z CZTERECH, nazwane w komunikacie: NIE WIEM (to konto nie widzi Dockera —
#            o dostępie, nie o kontenerze) · Docker odpowiedział i stosu NIE MA · brak narzędzia ·
#            złe użycie. ⛔ Pierwsze dwa to RÓŻNE rzeczy i nie wolno ich zlewać w jedno zdanie.
#
# ⛔ POMIJA ŚWIADOMIE: `*-proba-kontrolna.sh` i `kalibracja-mutantem.sh` (to kalibratory —
#    PODMIENIAJĄ kod wtyczki w kontenerze, mierzą przyrząd, nie produkt), `seed-demo.sh`,
#    `zrzuty-panelu.js`, `svg-pomiar-tekstow.js` (narzędzia), `testy/e2e/` (sklep, poza etapem),
#    `segregator-deklaracja-vs-ladunek-ai.php` (silnik strażnika A3-3 — biega przez wrapper `.sh`),
#    `lamacz-baza-i-srodowisko.php` (zamrożona sonda audytu — kształt kodu sprzed S1, dowód w git).
#    Pominięte są WYPISANE w wyniku — pominięcie ciche wyglądałoby jak „sprawdzone".
# ⚠️ Przywrócenie bazy z zrzutu mariadb-dump robi DROP tylko tabel, które istniały w chwili zrzutu —
#    tabela dopisana przez test w trakcie przebiegu PRZEŻYJE przywrócenie (sierota, nieszkodliwa, ale bądź świadom).
# ⚠️ Etykieta to nazwa NADANA przez wołającego; SHA w wyniku to HEAD drzewa w chwili przebiegu — wierz SHA.
set -u

KONTENER=strona_wp_wordpress
KONTENER_DB=strona_wp_db
KONTENER_IMAP=segm_imap
ENV_ADMIN=/root/.claude/sklep-admin-roboczy.env
LIMIT_S=300

# --- tryb porównania -----------------------------------------------------------
if [ "${1:-}" = "--porownaj" ]; then
	A="${2:-}"; B="${3:-}"
	[ -f "$A" ] && [ -f "$B" ] || { echo "użycie: --porownaj <A.tsv> <B.tsv>" >&2; exit 2; }
	REGRESJE=0; NAPRAWY=0
	while IFS=$'\t' read -r nazwa exit_b _; do
		exit_a=$(awk -F'\t' -v n="$nazwa" '$1==n{print $2}' "$A")
		[ -z "$exit_a" ] && { echo "NOWY      $nazwa → EXIT=$exit_b (nie było w $A)"; continue; }
		if [ "$exit_a" = "0" ] && [ "$exit_b" != "0" ] && [ "$exit_b" != "POMINIETE" ]; then echo "⛔ REGRESJA  $nazwa  $exit_a → $exit_b"; REGRESJE=$((REGRESJE+1)); fi
		if [ "$exit_a" != "0" ] && [ "$exit_b" = "0" ]; then echo "✅ NAPRAWA   $nazwa  $exit_a → $exit_b"; NAPRAWY=$((NAPRAWY+1)); fi
	done < "$B"
	while IFS=$'\t' read -r nazwa _; do
		awk -F'\t' -v n="$nazwa" '$1==n{f=1} END{exit !f}' "$B" || echo "ZNIKNĄŁ   $nazwa (był w $A, nie ma w $B)"
	done < "$A"
	echo "porównanie: regresje=$REGRESJE naprawy=$NAPRAWY"
	[ "$REGRESJE" -eq 0 ]; exit $?
fi

KORZEN=$(git rev-parse --show-toplevel 2>/dev/null) || { echo "linia-bazowa: nie jestem w repo git" >&2; exit 2; }
cd "$KORZEN" || exit 2

wp() { docker exec "$KONTENER" wp --allow-root --path=/var/www/html "$@"; }

ETYKIETA="${1:-}"
[ -n "$ETYKIETA" ] || { echo "użycie: linia-bazowa.sh <etykieta> | --porownaj A B" >&2; exit 2; }
command -v docker >/dev/null || { echo "brak dockera" >&2; exit 2; }
# ⛔ „NIE WIEM" TO NIE TO SAMO CO „NIE CHODZI" — patrz `narzedzia-qa/stos.sh:153`.
docker ps > /dev/null 2>&1 || { echo "NIE WIEM, czy stos $KONTENER chodzi — to konto nie widzi Dockera (brak dostepu do demona, nie brak kontenera)" >&2; exit 2; }
docker ps --filter "name=^${KONTENER}$" --filter status=running -q | grep -q . || { echo "Docker odpowiedzial: stosu $KONTENER na liscie NIE MA" >&2; exit 2; }
[ -f "$ENV_ADMIN" ] && { set -a; . "$ENV_ADMIN"; set +a; }

SHA=$(git rev-parse --short HEAD)
KIEDY=$(date '+%Y-%m-%d %H:%M')
WYNIKI_DIR="audyt/linia-bazowa"; mkdir -p "$WYNIKI_DIR"
TSV="$WYNIKI_DIR/$ETYKIETA.tsv"; : > "$TSV"
LOGI="${LINIA_LOGI:-/root/praca/zlecenia/wymiana/linia-bazowa-logi}/$ETYKIETA"; mkdir -p "$LOGI"
ZRZUT="$LOGI/baza-przed.sql"
# ⛔ w kontenerze WP NIE MA mysqldump/mysql (zmierzone 23.08) — zrzut i przywracanie idą przez kontener bazy,
#    tym samym wzorcem co narzedzia-qa/lib-stos-probny.sh (sql_bazy). Zrzut ma być NIEPUSTY — pusty import = „OK" i stan stracony.
HASLO_DB="$(docker exec "$KONTENER_DB" printenv MARIADB_ROOT_PASSWORD 2>/dev/null || docker exec "$KONTENER_DB" printenv MYSQL_ROOT_PASSWORD 2>/dev/null)"
[ -n "$HASLO_DB" ] || { echo "nie umiem odczytać hasła root bazy z $KONTENER_DB" >&2; exit 2; }
BAZA_WP="$(docker exec "$KONTENER" printenv WORDPRESS_DB_NAME 2>/dev/null)"; BAZA_WP="${BAZA_WP:-wordpress}"
echo "=== LINIA BAZOWA [$ETYKIETA] HEAD=$SHA $KIEDY baza=$BAZA_WP ==="
echo "--- zrzut bazy przed ---"
docker exec -e MYSQL_PWD="$HASLO_DB" "$KONTENER_DB" mariadb-dump -uroot --single-transaction --routines "$BAZA_WP" > "$ZRZUT" 2>"$LOGI/zrzut.err" \
	|| { echo "zrzut bazy padł: $(head -c 300 "$LOGI/zrzut.err")" >&2; exit 2; }
ROZMIAR=$(wc -c < "$ZRZUT"); [ "$ROZMIAR" -gt 100000 ] || { echo "zrzut podejrzanie mały ($ROZMIAR B) — nie ruszam dalej" >&2; exit 2; }
TABEL_PRZED=$(grep -c '^CREATE TABLE' "$ZRZUT"); echo "zrzut: $ROZMIAR B, tabel: $TABEL_PRZED"
przywroc_baze() {
	docker exec -i -e MYSQL_PWD="$HASLO_DB" "$KONTENER_DB" mariadb -uroot "$BAZA_WP" < "$ZRZUT" 2>>"$LOGI/przywracanie.err" || echo "⚠️ przywracanie bazy zgłosiło błąd (patrz $LOGI/przywracanie.err)"
	wp cache flush >/dev/null 2>&1
}
przywroc_kod()  { for w in chatbot-live segregator-maila sklep-agentow; do docker cp "wtyczki/$w/." "$KONTENER:/var/www/html/wp-content/plugins/$w/" >/dev/null && docker exec "$KONTENER" chown -R www-data:www-data "/var/www/html/wp-content/plugins/$w"; done; }
# worktree nie ma node_modules (nieśledzone, leżą w głównym drzewie) — testy JS padałyby w 0 s
#    z „Cannot find module 'playwright-core'" (zmierzone 23.08: 13 testów JS „padło" przez to)
if [ ! -e node_modules ] && [ -d /root/praca/zlecenia/sklep-agentow/node_modules ]; then
	ln -s /root/praca/zlecenia/sklep-agentow/node_modules node_modules && echo "node_modules: dowiązanie do głównego drzewa"
fi
# stos = HEAD na starcie (żeby linia bazowa mierzyła kod z repo, nie to, co ktoś zostawił)
przywroc_kod
# skrzynka testowa ma pamięć ulotną (brak wolumenu) — restart = czysty stan przed testami Łamacza
docker restart "$KONTENER_IMAP" >/dev/null 2>&1 && sleep 5

uruchom() { # $1 = ścieżka testu, $2 = typ (php|js|sh), $3 = czy przywracać bazę po (tak|nie)
	local t="$1" typ="$2" przywroc="$3" log="$LOGI/$(echo "$1" | tr '/' '_').log" start koniec kod
	start=$(date +%s)
	case "$typ" in
		php) docker cp "$t" "$KONTENER:/tmp/lb-test.php" >/dev/null && timeout "$LIMIT_S" docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/lb-test.php > "$log" 2>&1; kod=$? ;;
		js)  timeout "$LIMIT_S" flock /root/praca/zlecenia/wymiana/stos.lock node "$t" > "$log" 2>&1; kod=$? ;;
		# Testy powloki, ktore przyjmuja kontener jako $1, mialy domyslnie `probny_wp` — stos rundy,
		# ktory tu NIE stoi. Wolane bez argumentu czytaly pustke i raportowaly FAIL w 0 s: 3 fałszywe
		# czerwone na przebieg (zmierzone 27.08 — po podaniu kontenera wszystkie trzy EXIT=0).
		sh)  args=""; grep -q "probny_wp" "$t" 2>/dev/null && args="$KONTENER"
		     # shellcheck disable=SC2086
		     timeout "$LIMIT_S" flock /root/praca/zlecenia/wymiana/stos.lock bash "$t" $args > "$log" 2>&1; kod=$? ;;
	esac
	koniec=$(date +%s)
	printf '%s\t%s\t%s\n' "$t" "$kod" "$((koniec-start))" >> "$TSV"
	printf '%-60s EXIT=%-3s %4ss\n' "$t" "$kod" "$((koniec-start))"
	[ "$przywroc" = "tak" ] && przywroc_baze
}

echo "--- testy/ (PHP) ---"
for t in testy/*.php; do
	# Silnik strażnika A3-3: dokument↔kod. Uruchamia go WYŁĄCZNIE wrapper
	# testy/segregator-deklaracja-vs-ladunek-ai.sh (wgrywa dokumenty i kod
	# Z REPO do kontenera); wołany wprost sam odmawia biegu (EXIT=1), bo
	# kopia wtyczki na stosie nie jest źródłem prawdy — to nie jest wada.
	case "$t" in *segregator-deklaracja-vs-ladunek-ai.php) echo "POMINIĘTE (silnik strażnika A3-3 — uruchamia go wrapper .sh): $t"; printf '%s\tPOMINIETE\t0\n' "$t" >> "$TSV"; continue;; esac
	# ⛔ Strażnik A6-F1 NISZCZY STAN: `DROP TABLE` na tabeli opinii i `sklepag_aktywacja()`
	#    (tworzy strony, katalog, zadanie cykliczne, przebudowuje reguły adresów). Jego własny
	#    nagłówek mówi „uruchamiać na STOSIE PRÓBNYM, nie na stosie z danymi”, a ta pętla jedzie
	#    po `strona_wp_wordpress` i NIE przywraca bazy między testami — więc kolejne testy
	#    dostawałyby zrzuconą tabelę. Spust ma własną drogę: `testy/schemat-wersja-po-sprawdzeniu-proba-kontrolna.sh`
	#    stawia stos próbny i wywołuje go tam.
	case "$t" in *schemat-wersja-po-sprawdzeniu-test.php) echo "POMINIĘTE (niszczy stan — biegnie na stosie próbnym przez własną próbę kontrolną): $t"; printf '%s\tPOMINIETE\t0\n' "$t" >> "$TSV"; continue;; esac
	uruchom "$t" php nie
done
echo "--- testy/ (JS, przeglądarka) ---"
for t in testy/*.js; do
	case "$t" in *zrzuty-panelu.js|*svg-pomiar-tekstow.js) echo "POMINIĘTE (narzędzie, nie test): $t"; printf '%s\tPOMINIETE\t0\n' "$t" >> "$TSV"; continue;; *dziennik-test.js|*produkty-test.js) echo "POMINIĘTE (SKLEP — poza etapem; dziennik-test zatruwa limiter logowań): $t"; printf '%s\tPOMINIETE\t0\n' "$t" >> "$TSV"; continue;; esac
	uruchom "$t" js nie
done
echo "--- testy/ (bash: bramki dokumentacji) ---"
for t in testy/*.sh; do
	case "$t" in *-proba-kontrolna.sh|*kalibracja-mutantem.sh|*seed-demo.sh) echo "POMINIĘTE (kalibrator/narzędzie — podmienia kod): $t"; printf '%s\tPOMINIETE\t0\n' "$t" >> "$TSV"; continue;; esac
	uruchom "$t" sh nie
done
echo "--- audyt/boty/testy-audytu + repro (przywracanie bazy po każdym) ---"
for t in audyt/boty/testy-audytu/*.php audyt/boty/repro/*.php; do
	[ -f "$t" ] || continue
	# Zamrożona sonda audytu A2 (repro sprzed napraw): woła funkcję diagnostyki
	# usuniętą przez S1 → FATAL 255. Sonda jest DOWODEM historycznym w git —
	# kodu się pod nią nie naprawia i nie podnosi się jej do nowego kształtu.
	case "$t" in *lamacz-baza-i-srodowisko.php) echo "POMINIĘTE (zamrożona sonda audytu — kształt kodu sprzed S1): $t"; printf '%s\tPOMINIETE\t0\n' "$t" >> "$TSV"; continue;; esac
	uruchom "$t" php tak
done
for t in audyt/boty/testy-audytu/*.js; do [ -f "$t" ] && uruchom "$t" js tak; done
for t in audyt/boty/testy-audytu/*.sh; do [ -f "$t" ] && uruchom "$t" sh tak; done
echo "POMINIĘTE (sklep, poza etapem): testy/e2e/ (katalog)"; printf 'testy/e2e/\tPOMINIETE\t0\n' >> "$TSV"

echo "--- przywracam bazę i kod wtyczek z HEAD ---"
przywroc_baze; przywroc_kod
# zrzut zostaje w $LOGI jako dowód stanu PRZED (poza repo)

# podsumowanie do markdown
ILE=$(grep -vc 'POMINIETE' "$TSV"); ZERA=$(awk -F'\t' '$2=="0"' "$TSV" | wc -l); PADA=$(awk -F'\t' '$2!="0" && $2!="POMINIETE"' "$TSV" | wc -l)
{
	echo; echo "## [$ETYKIETA] — HEAD \`$SHA\` — $KIEDY — uruchomione $ILE · przechodzi $ZERA · pada $PADA"
	echo; echo "| test | EXIT | s |"; echo "|---|---|---|"
	awk -F'\t' '{printf "| `%s` | %s | %s |\n", $1, $2, $3}' "$TSV"
	echo; echo "Logi: \`$LOGI/\`. Plik porównań: \`$TSV\`."
} >> audyt/LINIA-BAZOWA-TESTOW.md
echo "=== KONIEC: uruchomione $ILE · przechodzi $ZERA · pada $PADA → $TSV ==="
exit 0
