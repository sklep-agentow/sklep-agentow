<?php
/**
 * Plugin Name: Segregator maila
 * Description: Testowy bot porządkujący skrzynkę firmową: rozdziela maile do kategorii według reguł (kolejność = pierwsza wygrywa) i pokazuje raport z liczbami — trafienia, % pokrycia, accuracy przy próbce z etykietami.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Sklep Agentów
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEGM_WERSJA', '1.1.0' );
define( 'SEGM_WERSJA_SCHEMATU', '2' );
define( 'SEGM_KATALOG', plugin_dir_path( __FILE__ ) );

require_once SEGM_KATALOG . 'includes/instalator.php';

register_activation_hook( __FILE__, 'segm_aktywacja' );

// Bot działa wyłącznie w adminie — front nie ładuje nic poza ścieżką upgrade.
if ( is_admin() ) {
	require_once SEGM_KATALOG . 'includes/silnik.php';
	require_once SEGM_KATALOG . 'includes/ai.php';
	require_once SEGM_KATALOG . 'includes/csv.php';
	require_once SEGM_KATALOG . 'includes/admin.php';
}

/**
 * Ścieżka aktualizacji schematu (nowa tabela = dbDelta + uninstall + to miejsce).
 */
function segm_sprawdz_schemat() {
	if ( get_option( 'segm_wersja_schematu' ) !== SEGM_WERSJA_SCHEMATU ) {
		segm_utworz_tabele();
		update_option( 'segm_wersja_schematu', SEGM_WERSJA_SCHEMATU );
	}
}
add_action( 'plugins_loaded', 'segm_sprawdz_schemat' );

/**
 * Ustawienia wtyczki z wartościami domyślnymi.
 */
function segm_ustawienia() {
	$domyslne = array(
		'usun_dane'        => 0,
		'ai_limit_dzienny' => 100,
	);
	$zapisane = get_option( 'segm_ustawienia', array() );
	if ( ! is_array( $zapisane ) ) {
		$zapisane = array();
	}
	return array_merge( $domyslne, $zapisane );
}
