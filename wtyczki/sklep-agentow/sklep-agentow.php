<?php
/**
 * Plugin Name: Sklep Agentów
 * Description: Sklep z testowymi botami AI — Stripe Checkout (tryb testowy), mail po zakupie, wydanie plików, panel w wp-admin. Zaplecze całe w WordPressie, własne tabele.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Warsztat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKLEPAG_WERSJA', '0.1.0' );
define( 'SKLEPAG_WERSJA_SCHEMATU', 1 );
define( 'SKLEPAG_PLIK', __FILE__ );
define( 'SKLEPAG_KATALOG', plugin_dir_path( __FILE__ ) );

require_once SKLEPAG_KATALOG . 'includes/aktywacja.php';

register_activation_hook( __FILE__, 'sklepag_aktywacja' );
register_deactivation_hook( __FILE__, 'sklepag_deaktywacja' );

add_action( 'plugins_loaded', 'sklepag_start' );

function sklepag_start() {
	sklepag_migruj_schemat_jesli_trzeba();

	add_shortcode( 'sklepag_sklep', 'sklepag_shortcode_sklep' );
	add_shortcode( 'sklepag_dziekujemy', 'sklepag_shortcode_dziekujemy' );

	if ( is_admin() ) {
		require_once SKLEPAG_KATALOG . 'includes/admin.php';
	}
}

/**
 * Lista aktywnych produktów. Kość: uczciwy stan pusty, bez atrap.
 */
function sklepag_shortcode_sklep() {
	global $wpdb;
	$tabela   = $wpdb->prefix . 'sklepag_produkty';
	$produkty = $wpdb->get_results(
		$wpdb->prepare( "SELECT nazwa, slug, opis_krotki, cena_grosze, waluta FROM {$tabela} WHERE aktywny = %d ORDER BY nazwa", 1 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z prefiksu WP, nie z wejścia.
	);

	$html  = '<div class="sklepag-sklep">';
	$html .= '<p class="sklepag-baner-testowy"><strong>TRYB TESTOWY</strong> — płatności nie są pobierane.</p>';

	if ( empty( $produkty ) ) {
		$html .= '<p>Brak produktów w sprzedaży.</p></div>';
		return $html;
	}

	$html .= '<ul class="sklepag-lista">';
	foreach ( $produkty as $p ) {
		$cena  = number_format( $p->cena_grosze / 100, 2, ',', ' ' ) . ' ' . esc_html( $p->waluta );
		$html .= '<li><strong>' . esc_html( $p->nazwa ) . '</strong> — ' . esc_html( $p->opis_krotki ) . ' <em>' . $cena . '</em></li>';
	}
	$html .= '</ul></div>';
	return $html;
}

/**
 * Strona „Dziękujemy" — w kości placeholder statusu; odpytywanie po id sesji dojdzie z checkoutem.
 */
function sklepag_shortcode_dziekujemy() {
	return '<div class="sklepag-dziekujemy"><p>Dziękujemy za zakup. Status zamówienia pojawi się tutaj.</p></div>';
}
