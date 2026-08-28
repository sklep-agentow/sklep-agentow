<?php
/**
 * Krok 2 anonimizacji RODO: ZAPIS PADA — czy handler NADAL potwierdza usuniecie?
 *
 * ⛔ TO JEST PROBA PODLOZONA. Filtr `query` psuje DOKLADNIE JEDNO zapytanie —
 *    to, ktore czysci e-mail kupujacego — i nic wiecej. Reszta sciezki dziala
 *    normalnie, wiec test odroznia „zapis padl” od „cokolwiek padlo”.
 *
 * Oczekiwane po naprawie: zdarzenie `anonimizacja_blad`, `wp_die` z ostrzezeniem,
 * e-mail NIETKNIETY w bazie i BRAK zdarzenia `anonimizacja`.
 * Przed naprawa: redirect z `sklepag_anonimizacja=ok` i zdarzenie `anonimizacja`,
 * mimo ze dane kupujacego zostaly w bazie.
 *
 * @package sklep-agentow-testy
 */

global $wpdb;

/* Handler zyje w pliku ladowanym tylko za `is_admin()`, a pod WP-CLI to falsz.
   Dociagamy TEN SAM plik produkcyjny — nie kopie, nie atrape. */
if ( ! function_exists( 'sklepag_obsluz_anonimizacje' ) ) {
	$plik_handlera = WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-zamowienie.php';
	if ( ! is_readable( $plik_handlera ) ) {
		echo "PRZYRZAD=BRAK-PLIKU-HANDLERA\n";
		exit( 2 );
	}
	require_once $plik_handlera;
}
if ( ! function_exists( 'sklepag_obsluz_anonimizacje' ) ) {
	echo "PRZYRZAD=HANDLER-NIEDOSTEPNY\n";
	exit( 2 );
}


$pz    = $wpdb->prefix . 'sklepag_zamowienia';
$teraz = current_time( 'mysql' );
$mail  = 'proba-rodo-awaria-' . wp_generate_password( 8, false ) . '@example.test';

$wpdb->insert(
	$pz,
	array(
		'stripe_session_id'     => 'cs_test_rodo_awaria_' . wp_generate_password( 12, false ),
		'produkt_id'            => 1,
		'produkt_nazwa_kupiona' => 'Zamowienie probne RODO (awaria zapisu)',
		'email_kupujacego'      => $mail,
		'status'                => 'mail_wyslany',
		'token_pobrania'        => str_repeat( 'b', 64 ),
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
$id = (int) $wpdb->insert_id;
if ( ! $id ) {
	echo "PRZYRZAD=NIE-UDALO-SIE-UTWORZYC-ZAMOWIENIA\n";
	exit( 2 );
}
echo 'ID=' . $id . "\n";
echo 'MAIL_PRZED=' . $mail . "\n";

$admini = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $admini ) ) {
	echo "PRZYRZAD=BRAK-ADMINA\n";
	exit( 2 );
}
wp_set_current_user( $admini[0]->ID );

$nonce                = wp_create_nonce( 'sklepag_anonimizuj' );
$_GET['zamowienie']   = $id;
$_GET['_wpnonce']     = $nonce;
$_REQUEST['_wpnonce'] = $nonce;

/* PODLOZONA WADA: to jedno zapytanie ma sie nie udac. Celujemy w oba znaczniki
   naraz, zeby nie trafic przypadkiem w inne UPDATE tej samej tabeli. */
$trafione = 0;
add_filter(
	'query',
	static function ( $zapytanie ) use ( &$trafione ) {
		if ( false !== strpos( $zapytanie, "email_kupujacego=''" ) && false !== strpos( $zapytanie, 'usunieto_dnia' ) ) {
			++$trafione;
			return 'UPDATE tabela_ktora_nie_istnieje_rodo SET x=1';
		}
		return $zapytanie;
	},
	99
);
$wpdb->suppress_errors( true );

add_filter(
	'wp_redirect',
	static function ( $lokalizacja ) {
		echo 'REDIRECT=' . $lokalizacja . "\n";
		return false;
	},
	99
);

add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $wiadomosc ) {
			echo 'WPDIE=' . wp_strip_all_tags( is_wp_error( $wiadomosc ) ? $wiadomosc->get_error_message() : (string) $wiadomosc ) . "\n";
			exit( 0 );
		};
	},
	99
);

register_shutdown_function(
	static function () use ( &$trafione ) {
		echo 'PODLOZENIE_TRAFILO=' . (int) $trafione . "\n";
	}
);

sklepag_obsluz_anonimizacje();
