<?php
/**
 * ChatBot LIVE — zdarzenia strażnika wejścia: licznik, dwa źródła, limit, uninstall
 * (paczka C3: LC-F2, DK-1, DK-5, P2-Z2, P2-Z3; klasa nr 7 z CLAUDE.md).
 *
 * PO CO (audyt):
 *  - **DK-5**: licznik w `wp_options` (`get_option` → +1 → `update_option`) gubił
 *    13 z 20 równoległych inkrementacji — i tym więcej, im większy ruch, czyli
 *    dokładnie wtedy, gdy liczba jest właścicielowi potrzebna.
 *  - **DK-1**: licznik pułapki i lista „podejrzanych" zgłoszeń to były DWA źródła
 *    prawdy o jednym zdarzeniu (14 vs 5) — pasek obiecywał wiersze, których nie ma.
 *  - **LC-F2 / P2-Z3**: ścieżka pułapki i ścieżka złego nonce'a nie miały limitu —
 *    bot miał drogę TAŃSZĄ niż uczciwy gość, a każde żądanie pisało do bazy.
 *  - **P2-Z2**: opcja `chatl_pulapka` przeżywała pełne odinstalowanie.
 *
 * Zamrożone `DK-rownolegle-liczniki.sh` i `DK-dwa-zrodla-pulapka.sh` czytają
 * `get_option` (stary przyrząd) → zostają dowodem audytu; strażnikiem jest ten plik.
 *
 * Przypadki BEZ WADY (żeby naprawa nie odebrała funkcji): prawdziwy gość nie jest
 * blokowany kubłem złapanych ani kubłem odrzuconych nonce'ów; czyste zgłoszenie
 * dalej przechodzi i nie jest „podejrzane"; pułapka dalej działa PRZED limitem
 * ruchu gościa (WARUNEK 2 z `pulapka-test.php` zostaje prawdą).
 *
 * Sprząta po sobie: własne zdarzenia, sesje `pulLicznikTest*`, zgłoszenia
 * `pul-licznik-test%`, własne kubły limitu. Nie dotyka opcji ani danych Segregatora.
 *
 * Kalibracja (opis PR C3): na main sprzed naprawy EXIT=1, po naprawie EXIT=0.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-pulapka-licznik-test.php strona_wp_wordpress:/tmp/cpl.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/cpl.php; echo EXIT=$?
 */

global $wpdb;
$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
require_once $katalog . 'front.php';
require_once $katalog . 'admin.php';

$GLOBALS['cpl_bledy'] = 0;
function cpl( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? ' (' . $szczegol . ')' : '' );
	if ( ! $ok ) {
		++$GLOBALS['cpl_bledy'];
	}
}

/*
 * Na kodzie SPRZED naprawy (schemat ≤ 7) nie ma ani tabeli zdarzeń, ani funkcji,
 * które ten strażnik mierzy. Kończymy wtedy czystym EXIT=1 z powodem, zamiast
 * fatalem na pierwszym wywołaniu — kalibracja ma być czytelna, nie przypadkowa.
 */
foreach ( array( 'chatl_odnotuj_zdarzenie', 'chatl_zdarzenia_stan' ) as $cpl_f ) {
	if ( ! function_exists( $cpl_f ) ) {
		printf( "  BLAD kod nie ma %s() — zdarzenia strażnika nadal siedzą w wp_options (stan sprzed C3)
", $cpl_f );
		echo "
BLEDOW: 1
";
		exit( 1 );
	}
}

$cpl_sesja_stara = 'pulLicznikTestStara00000000000A';
$cpl_mail        = 'pul-licznik-test@example.test';

function cpl_sprzataj() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj IN ('pulapka','nonce')" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email LIKE 'pul-licznik-test%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_sesje WHERE sesja LIKE 'pulLicznikTest%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE 'chat|%' OR klucz LIKE 'pulapka|%' OR klucz LIKE 'nonce-odrzucony|%'" ); // phpcs:ignore
}
register_shutdown_function( 'cpl_sprzataj' );
cpl_sprzataj();

echo "=== A. Tabela zdarzeń istnieje i jest w trzech miejscach (dbDelta + uninstall + schemat) ===\n";
$t = $wpdb->prefix . 'chatl_zdarzenia';
cpl( (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ), 'tabela chatl_zdarzenia istnieje na stosie' ); // phpcs:ignore
$instalator = (string) file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/includes/instalator.php' ); // phpcs:ignore
$uninstall  = (string) file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/uninstall.php' ); // phpcs:ignore
$glowny     = (string) file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/chatbot-live.php' ); // phpcs:ignore
cpl( false !== strpos( $instalator, 'CREATE TABLE {$p}chatl_zdarzenia' ), 'miejsce 1/3: dbDelta zakłada chatl_zdarzenia' );
cpl( false !== strpos( $uninstall, 'DROP TABLE IF EXISTS {$wpdb->prefix}chatl_zdarzenia' ), 'miejsce 2/3: uninstall kasuje tabelę (ścieżka pełnego czyszczenia)' );
cpl( false !== strpos( $glowny, "delete_option( 'chatl_pulapka' )" ) && false !== strpos( $glowny, "delete_option( 'chatl_blad_nonce' )" ), 'miejsce 3/3: podniesienie schematu kasuje stare liczniki z wp_options' );
cpl( false !== strpos( $uninstall, "delete_option( 'chatl_pulapka' )" ), 'P2-Z2: uninstall kasuje też opcję chatl_pulapka (instalacja sprzed aktualizacji)' );

echo "\n=== B. DK-5: N zapisów NARAZ = N zdarzeń (INSERT jest atomowy) ===\n";
/*
 * Równoległość mierzona tak, jak w zamrożonym DK-rownolegle-liczniki.sh: N żądań
 * naraz. Tu bez HTTP (limiter i tak by je uciął po progu) — N procesów WP-CLI
 * piszących do tej samej tabeli w tej samej chwili, każdy własnym połączeniem.
 */
$n   = 20;
$cmd = 'for i in $(seq 1 ' . $n . '); do wp --path=/var/www/html eval \'require_once WP_PLUGIN_DIR."/chatbot-live/includes/front.php"; chatl_odnotuj_pulapke("pole","pytanie");\' & done; wait';
exec( 'bash -lc ' . escapeshellarg( $cmd ) . ' 2>&1', $wyj, $kod ); // phpcs:ignore
$ile = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE rodzaj = 'pulapka'" ); // phpcs:ignore
cpl( $n === $ile, "DK-5: $n równoległych zapisów → $n zdarzeń (opcja gubiła 13 z 20)", 'zapisano: ' . $ile );
$stan = chatl_zdarzenia_stan( 'pulapka' );
cpl( $n === (int) $stan['ile'], 'licznik panelu = COUNT(*) zdarzeń, nie osobna liczba', (string) $stan['ile'] );
cpl_sprzataj();

echo "\n=== C. DK-1: licznik i lista podejrzanych zgłoszeń mówią to samo ===\n";
$ts = $wpdb->prefix . 'chatl_sesje';
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$wpdb->insert( $ts, array( 'sesja' => $cpl_sesja_stara, 'stan' => 'bot', 'klucz_hash' => '', 'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - 600 ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore

class Cpl_Wyjscie extends Exception {}
function cpl_uchwyt() {
	return function () {
		throw new Cpl_Wyjscie();
	};
}
/**
 * Woła endpoint gościa tak jak widget i zwraca odkodowaną odpowiedź.
 *
 * Kodu HTTP tą drogą NIE mierzymy: `wp_send_json_*` ustawia go przez
 * `status_header()`, którego w WP-CLI nie ma jak przechwycić — mierzymy to,
 * co widzi gość, czyli TREŚĆ odpowiedzi („Za dużo wiadomości…" = odmowa 429).
 * Kody HTTP na tych samych ścieżkach mierzy zamrożony przebieg audytu (curl).
 */
function cpl_zadanie( $funkcja, $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', 'cpl_uchwyt' );
	add_filter( 'wp_die_handler', 'cpl_uchwyt' );
	ob_start();
	try {
		$funkcja();
	} catch ( Cpl_Wyjscie $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// wp_send_json_* zawsze kończy żądanie.
	}
	$wyjscie = ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', 'cpl_uchwyt' );
	remove_filter( 'wp_die_handler', 'cpl_uchwyt' );
	return json_decode( $wyjscie, true );
}
/** Czy odpowiedź to odmowa „za dużo żądań" (to, co gość czyta przy 429). */
function cpl_odmowa( $odp ) {
	return is_array( $odp ) && empty( $odp['success'] )
		&& false !== mb_strpos( (string) ( $odp['data']['komunikat'] ?? '' ), 'Za dużo' );
}
function cpl_post_zgloszenie( $email, $pulapka, $sesja ) {
	return array(
		'nonce'              => wp_create_nonce( 'chatl_front' ),
		'sesja'              => $sesja,
		'email'              => $email,
		'wiadomosc'          => 'tresc testowa',
		'zgoda'              => '1',
		CHATL_PULAPKA_POLE   => $pulapka ? 'https://spam.example' : '',
	);
}
add_filter( 'pre_wp_mail', '__return_true' );

// Trzy złapane zgłoszenia (poniżej sufitu CHATL_LIMIT_PULAPKA = 5/min).
for ( $i = 0; $i < 3; $i++ ) {
	cpl_zadanie( 'chatl_ajax_zgloszenie', cpl_post_zgloszenie( 'pul-licznik-test' . $i . '@example.test', true, $cpl_sesja_stara ) );
}
$stan   = chatl_zdarzenia_stan( 'pulapka' );
$wierszy = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tz} WHERE email LIKE 'pul-licznik-test%' AND podejrzane = 1" ); // phpcs:ignore
cpl( 3 === (int) ( $stan['sciezki']['zgloszenie'] ?? 0 ) && 3 === $wierszy, 'DK-1: ile zdarzeń ze ścieżki zgłoszenia, tyle wierszy podejrzanych', 'zdarzeń: ' . (int) ( $stan['sciezki']['zgloszenie'] ?? 0 ) . ', wierszy: ' . $wierszy );

// Dwa złapane pytania do bota — zgłoszenia nie powstają (nie ma czego zapisać).
for ( $i = 0; $i < 2; $i++ ) {
	cpl_zadanie( 'chatl_ajax_pytanie', array( 'nonce' => wp_create_nonce( 'chatl_front' ), 'sesja' => $cpl_sesja_stara, 'pytanie' => 'test', CHATL_PULAPKA_POLE => 'bot' ) );
}
$stan = chatl_zdarzenia_stan( 'pulapka' );
cpl( 2 === (int) ( $stan['sciezki']['pytanie'] ?? 0 ) && 5 === (int) $stan['ile'], 'ścieżka pytania liczona OSOBNO (2), razem 5', wp_json_encode( $stan['sciezki'] ) );
ob_start();
chatl_notice_pulapki();
$pasek = (string) ob_get_clean();
cpl( false !== strpos( $pasek, '<strong>3</strong> przy formularzu kontaktu' ) && false !== strpos( $pasek, '<strong>2</strong> to pytania do bota' ), 'DK-1: pasek w panelu mówi, ILU złapanych właściciel ZNAJDZIE na liście', wp_strip_all_tags( $pasek ) );

echo "\n=== D. LC-F2 / P2-Z3: ścieżka pułapki i złego nonce'a mają limit ===\n";
cpl_sprzataj();
$wpdb->insert( $ts, array( 'sesja' => $cpl_sesja_stara, 'stan' => 'bot', 'klucz_hash' => '', 'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - 600 ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore
$odmowy = 0;
for ( $i = 0; $i < 12; $i++ ) {
	$odp     = cpl_zadanie( 'chatl_ajax_pytanie', array( 'nonce' => wp_create_nonce( 'chatl_front' ), 'sesja' => $cpl_sesja_stara, 'pytanie' => 'test', CHATL_PULAPKA_POLE => 'bot' ) );
	$odmowy += cpl_odmowa( $odp ) ? 1 : 0;
}
$zdarzen = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE rodzaj = 'pulapka'" ); // phpcs:ignore
cpl( $odmowy > 0, 'LC-F2: powyżej progu złapany dostaje ODMOWĘ (wcześniej 200 żądań / 7 s bez ani jednej)', 'odmów: ' . $odmowy . ' z 12' );
cpl( $zdarzen <= CHATL_LIMIT_PULAPKA, 'i przestaje zapisywać do bazy powyżej sufitu', 'zdarzeń: ' . $zdarzen . ' przy sufitcie ' . CHATL_LIMIT_PULAPKA );

cpl_sprzataj();
$odmowy = 0;
for ( $i = 0; $i < 14; $i++ ) {
	$odp     = cpl_zadanie( 'chatl_ajax_pytanie', array( 'nonce' => 'zepsuty', 'sesja' => $cpl_sesja_stara, 'pytanie' => 'test' ) );
	$odmowy += cpl_odmowa( $odp ) ? 1 : 0;
}
$zdarzen = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE rodzaj = 'nonce'" ); // phpcs:ignore
cpl( $odmowy > 0, 'P2-Z3: powyżej progu zły nonce dostaje ODMOWĘ (wcześniej 30/30 przechodziło bez limitu)', 'odmów: ' . $odmowy . ' z 14' );
cpl( $zdarzen <= CHATL_LIMIT_NA_MIN, 'i zapisów jest najwyżej tyle, ile próg', 'zdarzeń: ' . $zdarzen );

echo "\n=== E. PRZYPADKI BEZ WADY: prawdziwy gość nie obrywa ===\n";
cpl_sprzataj();
$wpdb->insert( $ts, array( 'sesja' => $cpl_sesja_stara, 'stan' => 'bot', 'klucz_hash' => '', 'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - 600 ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore
// Najpierw 6 złapanych (kubeł złapanych wyczerpany), potem CZYSTE zgłoszenie gościa.
for ( $i = 0; $i < 6; $i++ ) {
	cpl_zadanie( 'chatl_ajax_pytanie', array( 'nonce' => wp_create_nonce( 'chatl_front' ), 'sesja' => $cpl_sesja_stara, 'pytanie' => 'test', CHATL_PULAPKA_POLE => 'bot' ) );
}
$odp    = cpl_zadanie( 'chatl_ajax_zgloszenie', cpl_post_zgloszenie( 'pul-licznik-test-czysty@example.test', false, $cpl_sesja_stara ) );
$czysty = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pul-licznik-test-czysty@example.test' ), ARRAY_A ); // phpcs:ignore
cpl( ! empty( $odp['success'] ), 'kubeł złapanych NIE blokuje prawdziwego gościa', wp_json_encode( $odp ) );
cpl( is_array( $czysty ) && 0 === (int) $czysty['podejrzane'], 'czyste zgłoszenie zapisane i NIE jest podejrzane' );
$sciezki = chatl_zdarzenia_stan( 'pulapka' )['sciezki'];
cpl( ! isset( $sciezki['zgloszenie'] ), 'i nie dopisało zdarzenia pułapki', wp_json_encode( $sciezki ) );

// Zły nonce nie zjada kubła gościa — kubły są osobne.
cpl_sprzataj();
$wpdb->insert( $ts, array( 'sesja' => $cpl_sesja_stara, 'stan' => 'bot', 'klucz_hash' => '', 'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - 600 ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore
for ( $i = 0; $i < 12; $i++ ) {
	cpl_zadanie( 'chatl_ajax_pytanie', array( 'nonce' => 'zepsuty', 'sesja' => $cpl_sesja_stara, 'pytanie' => 'test' ) );
}
$odp = cpl_zadanie( 'chatl_ajax_zgloszenie', cpl_post_zgloszenie( 'pul-licznik-test-po-nonce@example.test', false, $cpl_sesja_stara ) );
cpl( ! empty( $odp['success'] ), 'gość po serii odrzuconych nonce’ów dalej jest obsługiwany (osobne kubły)', wp_json_encode( $odp ) );

echo "\n=== F. Ukrycie ostrzeżenia o nonce’ach dalej działa ===\n";
cpl_sprzataj();
chatl_odnotuj_odrzucony_nonce();
cpl( 1 === (int) chatl_zdarzenia_stan( 'nonce' )['ile'], 'odrzucony nonce odnotowany' );
wp_set_current_user( 1 );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'chatl_nonce_zamknij' );
$p = function ( $l ) {
	throw new Cpl_Wyjscie();
};
add_filter( 'wp_redirect', $p, 1 );
try {
	chatl_post_nonce_zamknij();
} catch ( Cpl_Wyjscie $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	// spodziewane przekierowanie
}
remove_filter( 'wp_redirect', $p, 1 );
cpl( 0 === (int) chatl_zdarzenia_stan( 'nonce' )['ile'], '„ukryj to ostrzeżenie" kasuje zdarzenia nonce' );
chatl_odnotuj_odrzucony_nonce();
cpl( 1 === (int) chatl_zdarzenia_stan( 'nonce' )['ile'], 'i ostrzeżenie wraca przy nowym odrzuceniu' );

echo "\n=== G. Retencja: zdarzenia nie rosną bez końca ===\n";
$wpdb->insert( $t, array( 'rodzaj' => 'pulapka', 'szczegol' => 'stare', 'sciezka' => 'pytanie', 'utworzono' => wp_date( 'Y-m-d H:i:s', time() - ( CHATL_RETENCJA_DNI + 5 ) * DAY_IN_SECONDS ) ) ); // phpcs:ignore
$wpdb->insert( $t, array( 'rodzaj' => 'pulapka', 'szczegol' => 'swieze', 'sciezka' => 'pytanie', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
chatl_czyszczenie_historii();
$stare  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE szczegol = 'stare'" ); // phpcs:ignore
$swieze = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE szczegol = 'swieze'" ); // phpcs:ignore
cpl( 0 === $stare, 'zdarzenie starsze niż retencja skasowane' );
cpl( 1 === $swieze, 'PRZYPADEK BEZ WADY: świeże zdarzenie zostaje' );

echo "\n" . ( $GLOBALS['cpl_bledy'] > 0 ? 'BLEDOW: ' . $GLOBALS['cpl_bledy'] : 'WSZYSTKO OK' ) . "\n";
exit( $GLOBALS['cpl_bledy'] > 0 ? 1 : 0 );
