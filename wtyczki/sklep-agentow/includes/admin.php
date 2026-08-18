<?php
/**
 * Panel wp-admin (K3): menu wtyczki, wspólne assety admina i strona-pulpit
 * z licznikami z bazy (prawdziwe zapytania, zero atrap). Widoki żyją w osobnych
 * plikach admin-*.php; dashboard „jak dobre sklepy" wg audyt/WZORCE-DASHBOARD-K3.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SKLEPAG_KATALOG . 'includes/admin-dashboard.php';
require_once SKLEPAG_KATALOG . 'includes/admin-dziennik.php';
require_once SKLEPAG_KATALOG . 'includes/admin-ustawienia.php';

add_action( 'admin_menu', 'sklepag_admin_menu' );
add_action( 'admin_enqueue_scripts', 'sklepag_admin_assety' );

function sklepag_admin_menu() {
	$pulpit = add_menu_page(
		'Sklep Agentów',
		'Sklep Agentów',
		'manage_options',
		'sklepag',
		'sklepag_admin_dashboard',
		'dashicons-cart',
		56
	);
	// Pierwsza pozycja submenu = czytelna etykieta zamiast powtórki nazwy wtyczki.
	add_submenu_page( 'sklepag', 'Sklep Agentów — pulpit', 'Pulpit', 'manage_options', 'sklepag', 'sklepag_admin_dashboard' );
	add_submenu_page( 'sklepag', 'Dziennik — logowania i zdarzenia', 'Dziennik', 'manage_options', 'sklepag-dziennik', 'sklepag_admin_dziennik' );
	add_submenu_page( 'sklepag', 'Ustawienia sklepu', 'Ustawienia', 'manage_options', 'sklepag-ustawienia', 'sklepag_admin_ustawienia' );

	// Screen Options „na stronę" dla tabeli zamówień (natywny wzorzec WP).
	add_action(
		'load-' . $pulpit,
		function () {
			add_screen_option(
				'per_page',
				array(
					'label'   => 'Zamówień na stronę',
					'default' => 20,
					'option'  => 'sklepag_zamowien_na_strone',
				)
			);
		}
	);
}

add_filter(
	'set_screen_option_sklepag_zamowien_na_strone',
	function ( $status, $option, $value ) {
		unset( $status, $option );
		return max( 1, min( 200, (int) $value ) );
	},
	10,
	3
);

/**
 * Wspólny CSS panelu — TYLKO na ekranach wtyczki (nie spowalniamy reszty wp-admin).
 *
 * @param string $hook Sufiks ekranu z admin_enqueue_scripts.
 */
function sklepag_admin_assety( $hook ) {
	if ( 'toplevel_page_sklepag' !== $hook && false === strpos( $hook, 'page_sklepag-' ) ) {
		return;
	}
	wp_enqueue_style( 'sklepag-admin', plugins_url( 'assets/admin.css', SKLEPAG_PLIK ), array(), SKLEPAG_WERSJA );
}

// Szkielet-liczniki z kości zastąpił pełny dashboard (includes/admin-dashboard.php);
// diagnostykę katalogu chronionego przejął notice w sklepag_admin_dashboard().
