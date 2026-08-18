<?php
/**
 * Plugin Name: ChatBot LIVE
 * Description: Bot na stronę firmową: 24/7 odpowiada na powtarzalne pytania z bazy Q&A, a gdy nie zna odpowiedzi — zbiera kontakt, żeby właściciel odezwał się na żywo.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Sklep Agentów
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATL_WERSJA', '1.0.0' );
define( 'CHATL_WERSJA_SCHEMATU', '1' );
define( 'CHATL_KATALOG', plugin_dir_path( __FILE__ ) );
define( 'CHATL_URL', plugin_dir_url( __FILE__ ) );

require_once CHATL_KATALOG . 'includes/instalator.php';
require_once CHATL_KATALOG . 'includes/silnik.php';
require_once CHATL_KATALOG . 'includes/front.php';

register_activation_hook( __FILE__, 'chatl_aktywacja' );
register_deactivation_hook( __FILE__, 'chatl_deaktywacja' );

if ( is_admin() ) {
	require_once CHATL_KATALOG . 'includes/admin.php';
}

/**
 * Ścieżka aktualizacji schematu: gdy wersja w bazie różni się od kodu,
 * dbDelta dociąga zmiany (nowa tabela = dbDelta + uninstall + ta ścieżka).
 */
function chatl_sprawdz_schemat() {
	if ( get_option( 'chatl_wersja_schematu' ) !== CHATL_WERSJA_SCHEMATU ) {
		chatl_utworz_tabele();
		update_option( 'chatl_wersja_schematu', CHATL_WERSJA_SCHEMATU );
	}
}
add_action( 'plugins_loaded', 'chatl_sprawdz_schemat' );

/**
 * Codzienne czyszczenie historii rozmów starszych niż 90 dni (retencja jak w sklepie).
 */
function chatl_czyszczenie_historii() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_rozmowy';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$tabela} WHERE utworzono < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS )
		)
	);
}
add_action( 'chatl_czyszczenie_historii', 'chatl_czyszczenie_historii' );

/**
 * Ustawienia wtyczki z wartościami domyślnymi.
 */
function chatl_ustawienia() {
	$domyslne = array(
		'wlaczony'         => 1,
		'powitanie'        => 'Dzień dobry! Jestem botem tej strony — zapytaj o cennik, godziny otwarcia albo ofertę. Jeśli nie będę znać odpowiedzi, zostawisz wiadomość i odezwiemy się osobiście.',
		'tekst_awaryjny'   => 'Nie znam odpowiedzi na to pytanie. Zostaw proszę maila i pytanie — odezwiemy się najszybciej, jak się da.',
		'mail_powiadomien' => get_option( 'admin_email' ),
		'powiadomienia'    => 1,
		'usun_dane'        => 0,
	);
	$zapisane = get_option( 'chatl_ustawienia', array() );
	if ( ! is_array( $zapisane ) ) {
		$zapisane = array();
	}
	return array_merge( $domyslne, $zapisane );
}
