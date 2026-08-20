<?php
/**
 * Plugin Name: Segregator maila
 * Description: Testowy bot porządkujący skrzynkę firmową: rozdziela maile do kategorii według reguł (kolejność = pierwsza wygrywa) i pokazuje raport z liczbami — trafienia, % pokrycia, accuracy przy próbce z etykietami.
 * Version: 1.2.1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Sklep Agentów
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEGM_WERSJA', '1.2.1' );
define( 'SEGM_WERSJA_SCHEMATU', '2' );
define( 'SEGM_KATALOG', plugin_dir_path( __FILE__ ) );

/**
 * Wpis do logu WP po nieudanym zapisie do bazy.
 *
 * $wpdb->insert/update/delete/query zwracają FALSE przy porażce (0 to poprawny
 * wynik „nic nie pasowało"), więc każde wywołanie porównujemy z false ===.
 * Log idzie przez wp_trigger_error() — natywne API WP (6.4+), respektuje
 * WP_DEBUG i nie łamie zakazu error_log() z phpcs.xml.dist. Na starszym WP
 * (deklarowane minimum 6.0) log po prostu nie powstaje; komunikat błędu dla
 * użytkownika działa niezależnie od wersji i to on jest właściwą obroną
 * przed cichym ubytkiem danych.
 *
 * @param string $funkcja  Nazwa funkcji, w której zapis padł.
 * @param string $operacja Krótki opis operacji (co miało trafić do bazy).
 * @return void
 */
function segm_log_bledu_bazy( $funkcja, $operacja ) {
	global $wpdb;
	if ( ! function_exists( 'wp_trigger_error' ) ) {
		return;
	}
	$szczegol = '' !== (string) $wpdb->last_error ? (string) $wpdb->last_error : 'sterownik nie podał komunikatu';
	wp_trigger_error(
		(string) $funkcja,
		'zapis do bazy nie powiódł się (' . $operacja . '): ' . $szczegol,
		E_USER_WARNING
	);
}

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
