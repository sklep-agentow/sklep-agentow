<?php
/**
 * Plugin Name: Sklep Agentów
 * Description: Sklep z testowymi botami AI — Stripe Checkout (tryb testowy), mail po zakupie, wydanie plików, panel w wp-admin. Zaplecze całe w WordPressie, własne tabele.
 * Version: 0.3.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Warsztat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKLEPAG_WERSJA', '0.3.0' );
define( 'SKLEPAG_WERSJA_SCHEMATU', 3 ); // v3: indeks para_logowania (login,ip,czas) pod opóźnienie wykładnicze.
define( 'SKLEPAG_LIMIT_POBRAN', 10 ); // Jedno źródło: INSERT zamówienia + treść maila (recenzja PR #33).
define( 'SKLEPAG_LINK_GODZIN', 72 );  // Ważność linku pobrania — jw.
define( 'SKLEPAG_PLIK', __FILE__ );
define( 'SKLEPAG_KATALOG', plugin_dir_path( __FILE__ ) );

require_once SKLEPAG_KATALOG . 'includes/aktywacja.php';
require_once SKLEPAG_KATALOG . 'includes/stripe.php';
require_once SKLEPAG_KATALOG . 'includes/zakup.php';
require_once SKLEPAG_KATALOG . 'includes/pobieranie.php';
require_once SKLEPAG_KATALOG . 'includes/logowania.php'; // ZAWSZE, nie za is_admin() — hooki logowania żyją na wp-login.php.

register_activation_hook( __FILE__, 'sklepag_aktywacja' );
register_deactivation_hook( __FILE__, 'sklepag_deaktywacja' );

// Interwał 10 min dla „dociągnij zaległe" (reconciliation — druga droga domykania).
// phpcs:disable WordPress.WP.CronInterval.CronSchedulesInterval -- 10 min świadomie (plan §4A): tryb testowy Stripe ponawia webhook tylko 3× w parę godzin, cron domyka zaległe zakupy.
add_filter(
	'cron_schedules',
	function ( $harmonogramy ) {
		$harmonogramy['sklepag_10min'] = array(
			'interval' => 600,
			'display'  => 'Co 10 minut (Sklep Agentów)',
		);
		return $harmonogramy;
	}
);
// phpcs:enable WordPress.WP.CronInterval.CronSchedulesInterval

add_action( 'plugins_loaded', 'sklepag_start' );

function sklepag_start() {
	sklepag_migruj_schemat_jesli_trzeba();

	// Self-heal harmonogramu (aktualizacja wtyczki nie odpala aktywacji).
	if ( ! wp_next_scheduled( 'sklepag_dociagnij_zalegle' ) ) {
		wp_schedule_event( time() + 600, 'sklepag_10min', 'sklepag_dociagnij_zalegle' );
	}

	add_shortcode( 'sklepag_sklep', 'sklepag_shortcode_sklep' );
	add_shortcode( 'sklepag_dziekujemy', 'sklepag_shortcode_dziekujemy' );
	add_action( 'wp_enqueue_scripts', 'sklepag_assety_frontu' );

	if ( is_admin() ) {
		require_once SKLEPAG_KATALOG . 'includes/admin.php';
	}
}

/**
 * Assety TYLKO na stronach sklepu (nie spowalniamy reszty strony — plan §2).
 */
function sklepag_assety_frontu() {
	if ( is_page( (int) get_option( 'sklepag_strona_dziekujemy' ) ) ) {
		wp_enqueue_script( 'sklepag-dziekujemy', plugins_url( 'assets/dziekujemy.js', SKLEPAG_PLIK ), array(), SKLEPAG_WERSJA, true );
		wp_add_inline_script( 'sklepag-dziekujemy', 'window.sklepagRest = ' . wp_json_encode( esc_url_raw( rest_url( 'sklepag/v1/' ) ) ) . ';', 'before' );
	}
}

/**
 * Lista aktywnych produktów. Kość: uczciwy stan pusty, bez atrap.
 */
function sklepag_shortcode_sklep() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'sklepag_produkty';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki; lista musi być świeża (CRUD w adminie), cache bez zysku.
	$produkty = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, nazwa, slug, opis_krotki, cena_grosze, waluta FROM {$tabela} WHERE aktywny = %d ORDER BY nazwa", 1 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z prefiksu WP, nie z wejścia.
	);

	$html  = '<div class="sklepag-sklep">';
	$html .= '<p class="sklepag-baner-testowy"><strong>TRYB TESTOWY</strong> — płatności nie są pobierane.</p>';

	if ( empty( $produkty ) ) {
		$html .= '<p>Brak produktów w sprzedaży.</p></div>';
		return $html;
	}

	$html .= '<ul class="sklepag-lista" style="list-style:none;padding:0">';
	foreach ( $produkty as $p ) {
		$cena     = number_format( $p->cena_grosze / 100, 2, ',', ' ' ) . ' ' . esc_html( $p->waluta );
		$html    .= '<li style="margin:0 0 1.5em;padding:1em;border:1px solid #ddd;border-radius:8px">';
		$html    .= '<strong>' . esc_html( $p->nazwa ) . '</strong> — ' . esc_html( $p->opis_krotki ) . ' <em>' . $cena . '</em>';
		$html    .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:.6em">';
		$html    .= '<input type="hidden" name="action" value="sklepag_kup">';
		$html    .= '<input type="hidden" name="produkt_id" value="' . (int) $p->id . '">';
		$html    .= wp_nonce_field( 'sklepag_kup', '_wpnonce', true, false );
		$id_zgody = 'sklepag-zgoda-' . (int) $p->id;
		$html    .= '<label for="' . esc_attr( $id_zgody ) . '" style="display:block;margin:.4em 0"><input type="checkbox" id="' . esc_attr( $id_zgody ) . '" name="zgoda" value="1" required> ' . esc_html( SKLEPAG_ZGODA_TRESC ) . '</label>';
		$html    .= '<button type="submit" class="sklepag-kup">Kup teraz (płatność testowa)</button>';
		$html    .= '</form></li>';
	}
	$html .= '</ul></div>';
	return $html;
}

/**
 * Strona „Dziękujemy" — status odpytywany JS-em po id sesji (assets/dziekujemy.js);
 * spełnienie i tak robi webhook/reconciliation, nie ta strona (best practice Stripe).
 */
function sklepag_shortcode_dziekujemy() {
	return '<div class="sklepag-dziekujemy"><p class="sklepag-status" role="status" aria-live="polite">Sprawdzamy status zamówienia…</p></div>';
}
