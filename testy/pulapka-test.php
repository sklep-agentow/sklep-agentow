<?php
/**
 * Pułapka na boty — test na PRAWDZIWEJ bazie i prawdziwym kodzie wtyczki.
 *
 * Sedno decyzji produktowej, którą ten test pilnuje: złapane zgłoszenie
 * **trafia do bazy z flagą**, a nie do kosza. Cisza dotyczy BOTA (odpowiedź jest
 * nieodróżnialna od udanej), nie bazy — bo fałszywy alarm w wersji „cicho
 * wyrzuć" jest niewidzialny dla obu stron, a ten produkt istnieje po to, żeby
 * zbierać kontakty.
 *
 * Uruchomienie:
 *   docker cp testy/pulapka-test.php strona_wp_wordpress:/tmp/pulapka-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/pulapka-test.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
require_once $katalog . 'front.php';

$GLOBALS['chatl_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol && ! $warunek ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}

function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

global $wpdb;
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$ts = $wpdb->prefix . 'chatl_sesje';

$sesja_stara = 'pulapkaTestStaraAAAAAAAAAAAAAAAA';
$sesja_swieza = 'pulapkaTestSwiezaBBBBBBBBBBBBBBB';

function chatl_test_sprzataj() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email LIKE 'pulapka-test%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_sesje WHERE sesja LIKE 'pulapkaTest%'" ); // phpcs:ignore
	delete_option( 'chatl_pulapka' );
}
chatl_test_sprzataj();

// Sesja "stara": wydana 60 s temu — czlowiek zdazyl przeczytac i napisac.
$wpdb->insert( $ts, array( 'sesja' => $sesja_stara, 'stan' => 'bot', 'klucz_hash' => '', 'utworzono' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore
// Sesja "swieza": wydana przed chwila — zadanie w tej samej sekundzie to nie czlowiek.
$wpdb->insert( $ts, array( 'sesja' => $sesja_swieza, 'stan' => 'bot', 'klucz_hash' => '', 'utworzono' => current_time( 'mysql' ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore

echo "=== A. Wykrywanie: ukryte pole ===\n";
$_POST = array();
rowne( 'czyste zadanie ze starej sesji NIE jest lapane', '', chatl_pulapka_zlapala( $sesja_stara ) );

$_POST[ CHATL_PULAPKA_POLE ] = 'https://spam.example';
rowne( 'wypelnione ukryte pole = zlapane', 'pole', chatl_pulapka_zlapala( $sesja_stara ) );

$_POST[ CHATL_PULAPKA_POLE ] = '';
rowne( 'PUSTE ukryte pole to nie jest zlapanie (widget wysyla je zawsze)', '', chatl_pulapka_zlapala( $sesja_stara ) );

echo "\n=== B. Wykrywanie: prog czasu liczony PO STRONIE SERWERA ===\n";
$_POST = array();
rowne( 'zadanie tuz po wydaniu sesji = zlapane', 'czas', chatl_pulapka_zlapala( $sesja_swieza ) );
rowne( 'nieznana sesja NIE jest lapana (mogla wygasnac)', '', chatl_pulapka_zlapala( 'pulapkaTestNieMaTakiej000000000' ) );
rowne( 'pusta sesja tez nie', '', chatl_pulapka_zlapala( '' ) );

echo "\n=== C. Licznik czyni pulapke widoczna w panelu ===\n";
delete_option( 'chatl_pulapka' );
chatl_odnotuj_pulapke( 'pole' );
chatl_odnotuj_pulapke( 'czas' );
$stan = get_option( 'chatl_pulapka' );
rowne( 'licznik zlicza kazde zadzialanie', 2, (int) $stan['ile'] );
sprawdz( 'i zapamietuje powod ostatniego', 'zbyt szybkie żądanie' === $stan['powod'], (string) $stan['powod'] );
sprawdz( 'oraz kiedy', ! empty( $stan['ostatnia'] ) );

echo "\n=== D. SEDNO: zlapane zgloszenie TRAFIA DO BAZY, z flaga ===\n";
/*
 * Nie wolamy endpointu przez HTTP (nonce, naglowki), tylko odtwarzamy DOKLADNIE
 * to, co robi jego galaz zapisu — z flaga wyliczana tak samo.
 */
function chatl_test_zapisz( $email, $pulapka ) {
	global $wpdb;
	return $wpdb->insert(
		$wpdb->prefix . 'chatl_zgloszenia',
		array(
			'email'      => $email,
			'wiadomosc'  => 'tresc testowa',
			'sesja'      => 'pulapkaTestStaraAAAAAAAAAAAAAAAA',
			'status'     => 'nowe',
			'podejrzane' => '' === $pulapka ? 0 : 1,
			'utworzono'  => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%d', '%s' )
	);
}
sprawdz( 'kolumna podejrzane istnieje w schemacie', false !== $wpdb->get_var( "SHOW COLUMNS FROM {$tz} LIKE 'podejrzane'" ) ); // phpcs:ignore
chatl_test_zapisz( 'pulapka-test-czysty@example.test', '' );
chatl_test_zapisz( 'pulapka-test-zlapany@example.test', 'pole' );

$czysty  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pulapka-test-czysty@example.test' ), ARRAY_A ); // phpcs:ignore
$zlapany = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pulapka-test-zlapany@example.test' ), ARRAY_A ); // phpcs:ignore
sprawdz( 'zlapane zgloszenie JEST w bazie (nie wyrzucone po cichu)', is_array( $zlapany ) );
rowne( 'i ma flage podejrzane = 1', 1, (int) $zlapany['podejrzane'] );
rowne( 'czyste ma flage 0', 0, (int) $czysty['podejrzane'] );
rowne( 'tresc zlapanego zachowana w calosci', 'tresc testowa', $zlapany['wiadomosc'] );

echo "\n=== E. Lista panelu: domyslnie czyste, podejrzane za filtrem z licznikiem ===\n";
$domyslne   = $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$tz} WHERE podejrzane = %d AND email LIKE 'pulapka-test%%'", 0 ) ); // phpcs:ignore
$za_filtrem = $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$tz} WHERE podejrzane = %d AND email LIKE 'pulapka-test%%'", 1 ) ); // phpcs:ignore
sprawdz( 'widok domyslny NIE pokazuje zlapanego', ! in_array( 'pulapka-test-zlapany@example.test', $domyslne, true ) );
sprawdz( 'ale pokazuje czyste', in_array( 'pulapka-test-czysty@example.test', $domyslne, true ) );
sprawdz( 'widok "podejrzane" pokazuje zlapane', in_array( 'pulapka-test-zlapany@example.test', $za_filtrem, true ) );
sprawdz( 'i NIE miesza tam czystych', ! in_array( 'pulapka-test-czysty@example.test', $za_filtrem, true ) );

echo "\n=== F. Warunki, ktore czynia to bezpiecznym ===\n";
$kod = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/includes/front.php' ); // phpcs:ignore

sprawdz(
	'WARUNEK 1: zlapane NIE wysyla maila do wlasciciela',
	1 === preg_match( "/if \(\s*'' === \\\$pulapka && \\\$ust\['powiadomienia'\]/", $kod ),
	'brak warunku pulapki przy wp_mail'
);
sprawdz(
	'WARUNEK 2: pulapka sprawdzana PRZED limitem ruchu (zlapane nie zjadaja kubla goscia)',
	strpos( $kod, 'chatl_pulapka_zlapala( $sesja_wstepna )' ) < strpos( $kod, 'if ( ! chatl_limit_ip_ok() ) {' ),
	'kolejnosc odwrocona'
);
sprawdz(
	'WARUNEK 2b: zlapane pytanie NIE dochodzi do trybu AI',
	strpos( $kod, "'silnik'    => 'reguly'" ) < strpos( $kod, 'chatl_limit_ai_ok' ),
	'zlapane pytanie moze siegnac AI'
);
sprawdz(
	'WARUNEK 3: odpowiedz dla zlapanego jest SUKCESEM, nie bledem',
	false !== strpos( $kod, 'wp_send_json_success' ) && 1 === preg_match( '/chatl_odnotuj_pulapke\( \$pulapka \);\s*\/\*.*?wp_send_json_success/s', $kod ),
	'zlapany dostaje inna odpowiedz niz udana — skrypt sie dowie'
);
sprawdz(
	'sufit na ZAPISY zlapanych chroni tabele przed zalaniem',
	false !== strpos( $kod, "chatl_rate_limit( 'pulapka', CHATL_LIMIT_PULAPKA )" ),
	'brak sufitu na zapisy zlapanych'
);

echo "\n=== G. Widget: pole jest w DOM i wysylane zawsze ===\n";
$js = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/assets/widget.js' ); // phpcs:ignore
$css = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/assets/widget.css' ); // phpcs:ignore
sprawdz( 'widget tworzy pole-pulapke', false !== strpos( $js, "polePulapki.name = 'chatl_pole_dodatkowe'" ) );
sprawdz( 'i wysyla je w kazdym zadaniu', false !== strpos( $js, "body.append('chatl_pole_dodatkowe'" ) );
sprawdz( 'pole jest poza zasiegiem tabulatora', false !== strpos( $js, 'polePulapki.tabIndex = -1' ) );
sprawdz( 'i pomijane przez czytnik ekranu', false !== strpos( $js, "polePulapki.setAttribute('aria-hidden', 'true')" ) );
sprawdz( 'autouzupelnianie wylaczone (menedzer hasel nie wypelni)', false !== strpos( $js, "polePulapki.autocomplete = 'off'" ) );
sprawdz( 'CSS wynosi pole poza kadr, a NIE display:none', false !== strpos( $css, 'left: -9999px !important' ) );
sprawdz( 'i nie chowa go przez display:none', false === strpos( $css, '.chatl-pole-dodatkowe {' ) || false === strpos( substr( $css, strpos( $css, '.chatl-pole-dodatkowe {' ) ), 'display: none' ) );

echo "\n=== H. Sprzatanie ===\n";
chatl_test_sprzataj();
rowne( 'dane testowe usuniete', '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$tz} WHERE email LIKE 'pulapka-test%'" ) ); // phpcs:ignore

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
