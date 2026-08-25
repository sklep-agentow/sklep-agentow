#!/bin/bash
# podloz-mutanty-sklep.sh — podkłada bank mutantów K7 na gałęzi audyt/mutanty-sklep (PLAN-K7 §5).
#
# ⛔ NIE DZIAŁA NIGDZIE INDZIEJ: sprawdza gałąź i przerywa, gdy to nie audyt/mutanty-sklep.
# ⛔ KOTWICA = WZORZEC, nie numer linii: każda podmiana wymaga, żeby wzorzec wystąpił DOKŁADNIE raz;
#    inaczej kod 3 i STOP (lekcja botów: kotwica po numerze linii po rebase kłamała).
# ⛔ Jeden mutant = jeden commit „MUTANT Mx — do zdjęcia: git revert <sha>”. Tabela rozliczenia
#    COMMITOWANA NA TEJ GAŁĘZI (bank botów „poza repo” zniknął — lekcja 25.08).
#
# Użycie: bash podloz-mutanty-sklep.sh [--sprawdz]
#   --sprawdz  — tylko liczy kotwice (0 zmian, 0 commitów); EXIT 0 = wszystkie trafiają raz
set -euo pipefail
TYLKO_SPRAWDZ=0; [ "${1:-}" = "--sprawdz" ] && TYLKO_SPRAWDZ=1

GALAZ="$(git rev-parse --abbrev-ref HEAD)"
[ "$GALAZ" = "audyt/mutanty-sklep" ] || { echo "STOP: jestem na [$GALAZ], mutanty wolno podkładać tylko na audyt/mutanty-sklep" >&2; exit 2; }
[ -z "$(git status --porcelain)" ] || { echo "STOP: drzewo nie jest czyste" >&2; exit 2; }
W=wtyczki/sklep-agentow
TABELA=audyt/sklep/ROZLICZENIE-MUTANTOW-SKLEP.md
WIERSZE="$(mktemp)"; trap 'rm -f "$WIERSZE"' EXIT
ZLE_KOTWICE=0

podmien() { # podmien <plik> <kotwica> <nowa-treść>
	local n; n=$(python3 -c 'import io,sys; print(io.open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$1" "$2")
	if [ "$n" -ne 1 ]; then printf 'ZGNIŁA KOTWICA w %s: wzorzec występuje %s razy (oczekiwano 1)\n  [%.90s]\n' "$1" "$n" "$2" >&2; ZLE_KOTWICE=$((ZLE_KOTWICE+1)); [ "$TYLKO_SPRAWDZ" -eq 1 ] && return 0; exit 3; fi
	[ "$TYLKO_SPRAWDZ" -eq 1 ] && { printf '  kotwica OK  %s\n' "$1"; return 0; }
	python3 -c 'import io,sys; p=sys.argv[1]; s=io.open(p,encoding="utf-8").read(); io.open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2],sys.argv[3]))' "$1" "$2" "$3"
}

zapisz() { # zapisz <id> <soczewka> <plik> <kotwica-skrócona> <opis>
	[ "$TYLKO_SPRAWDZ" -eq 1 ] && return 0
	git add -A
	git commit -q -m "MUTANT $1 — do zdjęcia: git revert \$(git rev-parse HEAD)

soczewka: $2
plik:     $3
kotwica:  $4
co robi:  $5"
	local sha; sha="$(git rev-parse --short HEAD)"
	printf '| %s | %s | `%s` | `%s` | `%s` | %s |\n' "$1" "$2" "$3" "$4" "$sha" "$5" >> "$WIERSZE"
	printf '  podłożony %-3s %-5s %s\n' "$1" "$2" "$sha"
}

echo "=== CZĘŚĆ 1 — MUTANTY PRAWDZIWE (7) ==="

# ── M1 · L1 — dziennik przestaje zapisywać NIEUDANE próby (S-09 milczy o porażkach) ─────────────
podmien $W/includes/logowania.php \
"$(cat <<'EOF'
function sklepag_loguj_nieudane( $username, $error ) {
	unset( $error );
	sklepag_zapisz_logowanie( $username, 'blad' );
}
EOF
)" \
"$(cat <<'EOF'
function sklepag_loguj_nieudane( $username, $error ) {
	unset( $error );
	// Login z nieudanej próby bywa cudzym adresem e-mail (atak słownikowy) — nie utrwalamy go
	// w dzienniku (minimalizacja danych); opóźnienie wykładnicze działa niezależnie.
	if ( ! username_exists( $username ) ) {
		return;
	}
	sklepag_zapisz_logowanie( $username, 'blad' );
}
EOF
)"
zapisz M1 L1 "includes/logowania.php" "function sklepag_loguj_nieudane" \
	"nieudane próby na NIEISTNIEJĄCE loginy nie trafiają do dziennika (a to one są atakiem); opóźnienie wykładnicze liczy z dziennika, więc dla nich też znika"

# ── M2 · L2 — w handlerze panelu znika current_user_can, nonce ZOSTAJE ──────────────────────────
podmien $W/includes/admin-dashboard.php \
"$(cat <<'EOF'
function sklepag_obsluz_dociagnij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_dociagnij' );
EOF
)" \
"$(cat <<'EOF'
function sklepag_obsluz_dociagnij() {
	check_admin_referer( 'sklepag_dociagnij' );
EOF
)"
zapisz M2 L2 "includes/admin-dashboard.php" "function sklepag_obsluz_dociagnij" \
	"„Dociągnij zaległe” sprawdza już tylko nonce; oczekiwana KWALIFIKACJA: para złamana, bez nonce nieeksploatowalne z zewnątrz — DUŻE, nie KRYTYCZNE"

# ── M3 · L3 (wyścig / dwa silniki) — okno odbioru claimu 5 min → 0 ──────────────────────────────
podmien $W/includes/zakup.php \
"$(cat <<'EOF'
					gmdate( 'Y-m-d H:i:s', strtotime( $teraz ) - 300 )
EOF
)" \
"$(cat <<'EOF'
					gmdate( 'Y-m-d H:i:s', strtotime( $teraz ) )
EOF
)"
zapisz M3 L3 "includes/zakup.php" "strtotime( \$teraz ) - 300" \
	"claim mail_w_toku odbierany natychmiast: webhook i cron/reconciliation biegną RAZEM, drugi regeneruje token → link z pierwszego maila umiera po cichu (dwa silniki, wyścig)"

# ── M4 · L3 (dowód skutku) — resend: false z wp_mail traktowane jak sukces ──────────────────────
podmien $W/includes/admin-zamowienie.php \
"$(cat <<'EOF'
	if ( $wyslano ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', zmieniono=%s WHERE id=%d", $teraz, (int) $zam->id ) );
		sklepag_zdarzenie( (int) $zam->id, 'mail_resend'
EOF
)" \
"$(cat <<'EOF'
	// wp_mail zwraca null, gdy filtr pre_wp_mail przejmie wysyłkę (wtyczki SMTP) — to też sukces.
	if ( null !== $wyslano ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', zmieniono=%s WHERE id=%d", $teraz, (int) $zam->id ) );
		sklepag_zdarzenie( (int) $zam->id, 'mail_resend'
EOF
)"
zapisz M4 L3 "includes/admin-zamowienie.php" "null !== \$wyslano" \
	"ponowna wysyłka: wp_mail=false → status mail_wyslany + zdarzenie mail_resend + zielony komunikat; gałąź mail_blad martwa — skutek do UDOWODNIENIA (stos: wp_mail false), nie do opisania"

# ── M5 · L4 — nowa opcja bez prefiksu sklepag_, której uninstall.php NIE kasuje ─────────────────
podmien $W/includes/admin-dashboard.php \
"$(cat <<'EOF'
	update_option( 'sklepag_reconciliation_ostatnia', time(), false );
	sklepag_dociagnij_zalegle( 8 );
EOF
)" \
"$(cat <<'EOF'
	update_option( 'sklepag_reconciliation_ostatnia', time(), false );
	// Znacznik ostatniego wejścia na pulpit — krótka nazwa poza prefiksem sklepu, żeby nie mieszać ze stanem sprzedaży.
	update_option( 'sag_pulpit_ostatnie_wejscie', array( 'kiedy' => time(), 'kto' => get_current_user_id() ), false );
	sklepag_dociagnij_zalegle( 8 );
EOF
)"
zapisz M5 L4 "includes/admin-dashboard.php" "sag_pulpit_ostatnie_wejscie" \
	"nowa opcja zapisywana w samonaprawie pulpitu; uninstall.php kasuje LIKE 'sklepag_%' → ta zostaje po odinstalowaniu (z ID użytkownika)"

# ── M6 · L5 — obietnica behawioralna: zamówienie mówi „HEAD nie zużywa limitu”, kod zaczyna liczyć ─
podmien $W/includes/pobieranie.php \
"$(cat <<'EOF'
	// HEAD: nagłówki bez zużywania limitu (skanery poczty dotykają linków).
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
		nocache_headers();
EOF
)" \
"$(cat <<'EOF'
	// HEAD: same nagłówki. Slot zużywamy jak przy pobraniu — skaner, który dotyka linku,
	// to też „użycie” i inaczej limit 10 nie chroni pliku przed botami.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET pobrania_uzyte = pobrania_uzyte + 1, zmieniono = %s WHERE id = %d AND pobrania_uzyte < pobrania_limit", current_time( 'mysql' ), (int) $zam->id ) );
		nocache_headers();
EOF
)"
zapisz M6 L5 "includes/pobieranie.php" "HEAD: same nagłówki" \
	"HEAD zużywa slot pobrania; zamówienie (01-zamowienie.md: „HEAD nie zużywa”) i readme („do 10 pobrań”) obiecują inaczej; stała 10 bez zmian → dok=kod (L6) NIE łapie"

# ── M7 · Ł — błąd Stripe (WP_Error) w reconciliation połknięty bez wpisu w zdarzenia ────────────
podmien $W/includes/zakup.php \
"$(cat <<'EOF'
		if ( $po_budzecie() ) {
			break; // Resztę dociągnie cron — render admina nie może wisieć.
		}
		$sesja = sklepag_stripe_pobierz_sesje( $sid );
		if ( is_wp_error( $sesja ) ) {
			sklepag_zdarzenie( null, 'reconciliation_blad', $sid . ': ' . $sesja->get_error_message() );
			continue;
		}
EOF
)" \
"$(cat <<'EOF'
		if ( $po_budzecie() ) {
			break; // Resztę dociągnie cron — render admina nie może wisieć.
		}
		$sesja = sklepag_stripe_pobierz_sesje( $sid );
		if ( is_wp_error( $sesja ) ) {
			continue; // Chwilowy błąd sieci/Stripe — następny przebieg spróbuje ponownie, bez zaśmiecania dziennika.
		}
EOF
)"
zapisz M7 L "includes/zakup.php" "Chwilowy błąd sieci/Stripe" \
	"WP_Error ze Stripe w reconciliation znika bez śladu w zdarzeniach: zły klucz = wieczne „oczekuje” i pusty dziennik; wyrocznia w prompcie Ł"

echo; echo "=== CZĘŚĆ 2 — PUŁAPKI (wyglądają źle, SĄ POPRAWNE — alarm = FAIL kalibracji) ==="

# ── F1 · zdublowane sprawdzenie uprawnień (przed i po nonce) ────────────────────────────────────
podmien $W/includes/admin-zamowienie.php \
"$(cat <<'EOF'
	check_admin_referer( 'sklepag_resend' );

	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;
EOF
)" \
"$(cat <<'EOF'
	check_admin_referer( 'sklepag_resend' );
	if ( ! current_user_can( 'manage_options' ) ) {
		// Powtórka ŚWIADOMA: nonce nie niesie uprawnień (wp_verify_nonce nie sprawdza roli), a w tym
		// handlerze między nonce a zapisem nie ma już żadnej bramki — zostawiamy obie kontrole.
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}

	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;
EOF
)"
zapisz F1 FAŁSZYWY "includes/admin-zamowienie.php" "Powtórka ŚWIADOMA" \
	"POPRAWNE: zdublowane current_user_can przed i po nonce — nadmiarowe, nie wadliwe"

# ── F2 · krótsze okno limitu prób pobrania (bezpieczniej), liczba nigdzie nie obiecana ─────────
podmien $W/includes/pobieranie.php \
"$(cat <<'EOF'
	if ( ! sklepag_rate_limit( 'pobierz', 30 ) ) {
EOF
)" \
"$(cat <<'EOF'
	// 20/min: skaner poczty dotyka linku 2–3×, realny kupujący < 5; 30 zostawiało zapas dla botów.
	if ( ! sklepag_rate_limit( 'pobierz', 20 ) ) {
EOF
)"
zapisz F2 FAŁSZYWY "includes/pobieranie.php" "sklepag_rate_limit( 'pobierz', 20 )" \
	"POPRAWNE: próg 30→20 prób/min — OSTRZEJ; liczby 30 nie ma w readme/DOK-TECH/zamówieniu, więc nic nie kłamie"

# ── F3 · obronny function_exists w samonaprawie pulpitu ────────────────────────────────────────
podmien $W/includes/admin-dashboard.php \
"$(cat <<'EOF'
function sklepag_dashboard_self_heal() {
	$ostatni = (int) get_option( 'sklepag_reconciliation_ostatnia', 0 );
EOF
)" \
"$(cat <<'EOF'
function sklepag_dashboard_self_heal() {
	if ( ! function_exists( 'sklepag_dociagnij_zalegle' ) ) {
		return; // Obronnie: pulpit ładowany bez zakup.php (np. wtyczka w trakcie aktualizacji) nie ma prawa się wywrócić.
	}
	$ostatni = (int) get_option( 'sklepag_reconciliation_ostatnia', 0 );
EOF
)"
zapisz F3 FAŁSZYWY "includes/admin-dashboard.php" "function_exists( 'sklepag_dociagnij_zalegle' )" \
	"POPRAWNE: obronny guard, zachowanie bez zmian; „martwy kod” to nie usterka"

# ── F4 · phpcs:ignore z uzasadnieniem przy zapisie loginu ──────────────────────────────────────
podmien $W/includes/logowania.php \
"$(cat <<'EOF'
	sklepag_zapisz_logowanie( $username, 'blad' );
}
EOF
)" \
"$(cat <<'EOF'
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $username to login z formularza rdzenia; do bazy idzie przez prepare() (%s) w sklepag_zapisz_logowanie(), zapis 1:1 jest CELOWY (S-09 „konkrety”: co wpisano).
	sklepag_zapisz_logowanie( $username, 'blad' );
}
EOF
)"
zapisz F4 FAŁSZYWY "includes/logowania.php" "phpcs:ignore WordPress.Security.ValidatedSanitizedInput" \
	"POPRAWNE: wyłączenie reguły phpcs z uzasadnieniem; wartość idzie przez prepare()"

if [ "$TYLKO_SPRAWDZ" -eq 1 ]; then
	[ "$ZLE_KOTWICE" -eq 0 ] && { echo "KOTWICE: 11/11 trafiają dokładnie raz — można podkładać"; exit 0; }
	echo "KOTWICE: $ZLE_KOTWICE zgniłych"; exit 3
fi

# ── tabela rozliczenia (COMMITOWANA na gałęzi) ──────────────────────────────────────────────────
BAZA=$(git rev-parse --short 'k7-zamrozenie^{commit}')
{
	echo "# ROZLICZENIE MUTANTÓW SKLEPU — gałąź audyt/mutanty-sklep (PLAN-K7 §5)"
	echo
	echo "Baza: \`$BAZA\` = tag \`k7-zamrozenie\` (sklep-agentow 0.9.13, zamrożony). Każdy mutant = osobny commit."
	echo
	echo "⛔ Ta gałąź NIE JEST SCALANA nigdzie. ⛔ Audytor NIE WIE o kalibracji (PLAN-K7 §5)."
	echo "⛔ Kotwica = WZORZEC, nie numer linii — \`podloz-mutanty-sklep.sh\` przerywa z kodem 3, gdy wzorzec nie trafi dokładnie raz."
	echo "Runda A biegnie na PACZCE Z TEJ GAŁĘZI (znaleziska poza liniami mutantów są prawdziwe), runda B na czystej."
	echo
	echo "## Bank"
	echo
	echo "| # | soczewka | plik | kotwica (wzorzec) | commit do odwrócenia | co robi / czego oczekujemy |"
	echo "|---|---|---|---|---|---|"
	cat "$WIERSZE"
	echo
	echo "Zdjęcie pojedynczego mutanta: \`git revert <commit>\`. Zdjęcie wszystkich: skasowanie gałęzi."
	echo
	echo "## Rozliczenie (wypełnia koordynatorka PO rundzie; czyta \`narzedzia-qa/k7-koniec.sh\` warunek 2)"
	echo
	echo "PASS = 100 % mutantów WŁASNEGO zakresu + 0 alarmów na F1–F4 + poprawna kwalifikacja (M2: DUŻE nie KRYTYCZNE)."
	echo "Limit: JEDNA poprawka promptu/materiału i jeden ponowny przebieg (\`proby\` ≤ 2); drugi FAIL = soczewka NIEKALIBROWANA."
	echo "Mutant trafiony przez CUDZĄ soczewkę = znalezisko tej soczewki, nie zmienia kalibracji przypisanej. L6 = niekalibrowana (jawnie)."
	echo
	echo "| audytor | mutant | znalazl | falszywy | werdykt | proby |"
	echo "|---|---|---|---|---|---|"
	echo "| L1 | M1 | | | | |"
	echo "| L2 | M2 | | | | |"
	echo "| L3 | M3 | | | | |"
	echo "| L3 | M4 | | | | |"
	echo "| L4 | M5 | | | | |"
	echo "| L5 | M6 | | | | |"
	echo "| L | M7 | | | | |"
} > "$TABELA"
git add "$TABELA"
git commit -q -m "kalibracja: ROZLICZENIE-MUTANTOW-SKLEP.md — bank 7 prawdziwych + 4 pułapki (baza $BAZA)"
echo; echo "TABELA: $TABELA (commit $(git rev-parse --short HEAD)) — bank podłożony, 11 commitów + tabela"
