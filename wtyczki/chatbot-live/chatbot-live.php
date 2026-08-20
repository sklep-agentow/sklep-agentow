<?php
/**
 * Plugin Name: ChatBot LIVE
 * Description: Bot na stronę firmową: 24/7 odpowiada na powtarzalne pytania z bazy Q&A, a gdy nie zna odpowiedzi — zbiera kontakt, żeby właściciel odezwał się na żywo.
 * Version: 1.4.8
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Automatic AI
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chatbot-live
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATL_WERSJA', '1.4.8' );
define( 'CHATL_WERSJA_SCHEMATU', '4' );
define( 'CHATL_KATALOG', plugin_dir_path( __FILE__ ) );
define( 'CHATL_URL', plugin_dir_url( __FILE__ ) );

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
function chatl_log_bledu_bazy( $funkcja, $operacja ) {
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

require_once CHATL_KATALOG . 'includes/instalator.php';
require_once CHATL_KATALOG . 'includes/silnik.php';
require_once CHATL_KATALOG . 'includes/wyglad.php';
require_once CHATL_KATALOG . 'includes/ai.php';
require_once CHATL_KATALOG . 'includes/front.php';

register_activation_hook( __FILE__, 'chatl_aktywacja' );
register_deactivation_hook( __FILE__, 'chatl_deaktywacja' );

if ( is_admin() ) {
	require_once CHATL_KATALOG . 'includes/admin.php';
	require_once CHATL_KATALOG . 'includes/csv.php';
	require_once CHATL_KATALOG . 'includes/statystyki.php';
	require_once CHATL_KATALOG . 'includes/powitanie.php';
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
 * Codzienne czyszczenie: historia rozmów starsza niż CHATL_RETENCJA_DNI
 * (retencja jak w sklepie) + wygasłe wiersze licznika rate-limitu.
 *
 * Okres retencji bierzemy ze STAŁEJ, nie z liczby wpisanej tutaj: panel,
 * widget i INSTRUKCJA mówią gościowi „90 dni" na podstawie
 * CHATL_RETENCJA_DNI, więc gdyby cron miał własną kopię liczby, zmiana
 * stałej cicho rozjechałaby obietnicę z tym, co kod naprawdę kasuje.
 */
function chatl_czyszczenie_historii() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_rozmowy';
	$wynik  = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$tabela} WHERE utworzono < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			gmdate( 'Y-m-d H:i:s', time() - CHATL_RETENCJA_DNI * DAY_IN_SECONDS )
		)
	);
	if ( false === $wynik ) {
		chatl_log_bledu_bazy( 'chatl_czyszczenie_historii', 'kasowanie historii rozmów starszej niż ' . CHATL_RETENCJA_DNI . ' dni' );
	}
	$limity = $wpdb->prefix . 'chatl_rate_limit';
	$wynik  = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$limity} WHERE okno_do < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			gmdate( 'Y-m-d H:i:s' )
		)
	);
	if ( false === $wynik ) {
		chatl_log_bledu_bazy( 'chatl_czyszczenie_historii', 'kasowanie wygasłych okien rate-limitu' );
	}
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
		'ai_informacje'    => '',
		'ai_limit_dzienny' => 100,
		'adres_polityki'   => '',
		// Wygląd widgetu (1.4.0). Wartości domyślne = paleta wpisana
		// w assets/widget.css, więc aktualizacja nie zmienia nikomu wyglądu.
		'kolor_glowny'     => '#bfff38',
		'kolor_naglowka'   => '#08090b',
		'kolor_tekstu'     => 'auto',
		'pozycja'          => 'prawo',
		// Pusty = użyj CHATL_AI_MODEL. Właściciel może wpisać własny
		// identyfikator, gdy dostawca wycofa albo doda model.
		'ai_model'         => '',
		// Zaufanie nagłówkom proxy: domyślnie WYŁĄCZONE, bo X-Forwarded-For
		// da się podrobić. Włącza je świadomie właściciel strony za Cloudflare.
		'za_proxy'         => 0,
	);
	$zapisane = get_option( 'chatl_ustawienia', array() );
	if ( ! is_array( $zapisane ) ) {
		$zapisane = array();
	}
	return array_merge( $domyslne, $zapisane );
}
