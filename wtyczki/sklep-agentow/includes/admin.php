<?php
/**
 * Panel wp-admin — kość: strona główna z licznikami z bazy (prawdziwe zapytania,
 * zero atrap). Dashboard „jak dobre sklepy" dojdzie w K3 (najpierw wzorce dziedziny).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'sklepag_admin_menu' );

function sklepag_admin_menu() {
	add_menu_page(
		'Sklep Agentów',
		'Sklep Agentów',
		'manage_options',
		'sklepag',
		'sklepag_admin_strona_glowna',
		'dashicons-cart',
		56
	);
}

function sklepag_admin_strona_glowna() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}

	global $wpdb;
	$p = $wpdb->prefix . 'sklepag_';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwy tabel z prefiksu WP; liczniki z własnych tabel, na żywo.
	$produkty_wszystkie = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}produkty" );
	$produkty_aktywne   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}produkty WHERE aktywny = 1" );
	$zamowienia         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}zamowienia" );
	$oplacone           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}zamowienia WHERE status IN ('oplacone','mail_w_toku','mail_wyslany','mail_blad')" );
	// phpcs:enable

	$katalog      = get_option( 'sklepag_katalog_chroniony', '' );
	$katalog_blad = get_option( 'sklepag_katalog_chroniony_blad', '' );

	echo '<div class="wrap"><h1>Sklep Agentów — kość</h1>';
	echo '<p><strong>TRYB TESTOWY</strong> — płatności Stripe w trybie testowym, zero prawdziwych pieniędzy.</p>';
	echo '<table class="widefat striped" style="max-width:560px">';
	echo '<tbody>';
	echo '<tr><td>Produkty (aktywne / wszystkie)</td><td>' . esc_html( $produkty_aktywne . ' / ' . $produkty_wszystkie ) . '</td></tr>';
	echo '<tr><td>Zamówienia (opłacone / wszystkie)</td><td>' . esc_html( $oplacone . ' / ' . $zamowienia ) . '</td></tr>';
	echo '<tr><td>Katalog chroniony</td><td>' . ( $katalog ? '<code>' . esc_html( basename( $katalog ) ) . '</code>' : '<strong style="color:#b32d2e">BRAK</strong>' ) . '</td></tr>';
	if ( $katalog_blad ) {
		echo '<tr><td>⚠ Błąd katalogu</td><td>' . esc_html( $katalog_blad ) . '</td></tr>';
	}
	echo '<tr><td>Wersja schematu bazy</td><td>' . esc_html( (string) get_option( 'sklepag_wersja_schematu', '?' ) ) . '</td></tr>';
	echo '</tbody></table>';
	echo '<p>Dashboard sprzedaży, zamówienia, produkty (CRUD), dziennik i ustawienia — kolejne kroki K3/K4 planu.</p>';
	echo '</div>';
}
