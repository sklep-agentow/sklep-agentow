<?php
/**
 * Plugin Name: Sklep Agentów
 * Description: Sklep z testowymi botami AI — Stripe Checkout (tryb testowy), mail po zakupie, wydanie plików, panel w wp-admin. Zaplecze całe w WordPressie, własne tabele.
 * Version: 0.8.1
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Warsztat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKLEPAG_WERSJA', '0.8.1' );
define( 'SKLEPAG_WERSJA_SCHEMATU', 4 ); // v4: kolumna zamowienia.usunieto_dnia (anonimizacja RODO — K3d); v3: indeks para_logowania.
define( 'SKLEPAG_LIMIT_POBRAN', 10 ); // Jedno źródło: INSERT zamówienia + treść maila (recenzja PR #33).
define( 'SKLEPAG_LINK_GODZIN', 72 );  // Ważność linku pobrania — jw.
define( 'SKLEPAG_PLIK', __FILE__ );
define( 'SKLEPAG_KATALOG', plugin_dir_path( __FILE__ ) );

require_once SKLEPAG_KATALOG . 'includes/aktywacja.php';
require_once SKLEPAG_KATALOG . 'includes/stripe.php';
require_once SKLEPAG_KATALOG . 'includes/zakup.php';
require_once SKLEPAG_KATALOG . 'includes/pobieranie.php';
require_once SKLEPAG_KATALOG . 'includes/logowania.php'; // ZAWSZE, nie za is_admin() — hooki logowania żyją na wp-login.php.
require_once SKLEPAG_KATALOG . 'includes/front-produkt.php'; // K5: widok produktu + rewrite (init) — poza is_admin().
require_once SKLEPAG_KATALOG . 'includes/front-seo.php';     // K5: title/meta/JSON-LD/sitemap (D27).

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
	$strona_sklep      = (int) get_option( 'sklepag_strona_sklep' );
	$strona_dziekujemy = (int) get_option( 'sklepag_strona_dziekujemy' );

	if ( is_page( $strona_sklep ) || is_page( $strona_dziekujemy ) ) {
		wp_enqueue_style( 'sklepag-front', plugins_url( 'assets/front.css', SKLEPAG_PLIK ), array(), SKLEPAG_WERSJA );
	}
	if ( is_page( $strona_dziekujemy ) ) {
		wp_enqueue_script( 'sklepag-dziekujemy', plugins_url( 'assets/dziekujemy.js', SKLEPAG_PLIK ), array(), SKLEPAG_WERSJA, true );
		wp_add_inline_script( 'sklepag-dziekujemy', 'window.sklepagRest = ' . wp_json_encode( esc_url_raw( rest_url( 'sklepag/v1/' ) ) ) . ';', 'before' );
	}
}

/**
 * Baner trybu testowego — jedna treść na wszystkich stronach sklepu (plan §4A;
 * napis bez zmian od K1 — testy i K2 na nim polegają).
 */
function sklepag_baner_testowy() {
	return '<p class="sklepag-baner-testowy">TRYB TESTOWY — płatności nie są pobierane</p>';
}

/**
 * Lista aktywnych produktów (K5: klimat gospodarza — PLAN-WYGLADU-K5 §2A).
 * Uczciwy stan pusty, bez atrap.
 */
function sklepag_shortcode_sklep() {
	// Widok produktu na TEJ SAMEJ stronie (query var — PLAN-WYGLADU-K5 §2B/PR 2).
	$slug_produktu = get_query_var( 'sklepag_produkt' );
	if ( is_string( $slug_produktu ) && '' !== $slug_produktu ) {
		return sklepag_render_produkt( sanitize_title( $slug_produktu ) );
	}

	global $wpdb;
	$tabela = $wpdb->prefix . 'sklepag_produkty';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki; lista musi być świeża (CRUD w adminie), cache bez zysku.
	$produkty = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, nazwa, slug, opis_krotki, cena_grosze, waluta FROM {$tabela} WHERE aktywny = %d ORDER BY nazwa", 1 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z prefiksu WP, nie z wejścia.
	);

	$html  = '<div class="sklepag-wyspa sklepag-sklep">';
	$html .= '<header class="sklepag-hero">';
	$html .= '<p class="sklepag-mono-etykieta">[ SKLEP ]</p>';
	$html .= '<h1 class="sklepag-hero-tytul"><span class="sklepag-akcent">Boty</span>, które robią robotę.</h1>';
	$html .= '<p class="sklepag-lead">Gotowe wtyczki-boty dla Twojej firmy. Instalujesz jak każdą wtyczkę WordPressa — działają od pierwszej minuty.</p>';
	$html .= sklepag_baner_testowy();
	$html .= '</header>';

	if ( empty( $produkty ) ) {
		$html .= '<p class="sklepag-pusto">[ BRAK PRODUKTÓW W SPRZEDAŻY ]</p></div>';
		return $html;
	}

	$html .= '<div class="sklepag-karty">';
	foreach ( $produkty as $p ) {
		$html .= '<article class="sklepag-karta">';
		$html .= '<div class="sklepag-karta-glowa">' . sklepag_ikona_produktu( $p->slug ) . '<p class="sklepag-mono-etykieta">[ <span class="sklepag-akcent">BOT</span> · TESTOWY ]</p></div>';
		$html .= '<h2 class="sklepag-karta-tytul">' . esc_html( $p->nazwa ) . '</h2>';
		$html .= '<p class="sklepag-karta-opis">' . esc_html( $p->opis_krotki ) . '</p>';
		$html .= '<p class="sklepag-cena">' . esc_html( number_format( $p->cena_grosze / 100, 2, ',', ' ' ) ) . '<span class="sklepag-cena-waluta">' . esc_html( $p->waluta ) . '</span></p>';
		$html .= '<p><a class="sklepag-kup sklepag-kup-kotwica" href="' . esc_url( sklepag_url_produktu_front( $p->slug ) ) . '">Zobacz szczegóły <span aria-hidden="true">→</span></a></p>';
		$html .= '</article>';
	}
	$html .= '</div></div>';
	return $html;
}

/**
 * Strona „Dziękujemy" — status odpytywany JS-em po id sesji (assets/dziekujemy.js);
 * spełnienie i tak robi webhook/reconciliation, nie ta strona (best practice Stripe).
 */
function sklepag_shortcode_dziekujemy() {
	return '<div class="sklepag-wyspa sklepag-dziekujemy">' . sklepag_baner_testowy() . '<p class="sklepag-status" role="status" aria-live="polite">[ ZAMÓWIENIE ] Sprawdzamy status…</p></div>';
}
