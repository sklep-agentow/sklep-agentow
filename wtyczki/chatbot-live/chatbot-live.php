<?php
/**
 * Plugin Name: ChatBot LIVE
 * Description: Bot na stronę firmową: 24/7 odpowiada na powtarzalne pytania z bazy Q&A, a gdy nie zna odpowiedzi — zbiera kontakt, żeby właściciel odezwał się na żywo.
 * Version: 1.5.8
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

define( 'CHATL_WERSJA', '1.5.8' );
define( 'CHATL_WERSJA_SCHEMATU', '7' );
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
/**
 * Ile dni panel pokazuje ostrzeżenie o nieudanym zapisie do bazy.
 * Znacznika NIE kasujemy przy udanym zapisie — nie ma jednego „sukcesu", po
 * którym można by go zgasić, a kasowanie na ścieżce każdego zapisu byłoby
 * dokładaniem zapytania tam, gdzie właśnie coś nie działa. Zamiast tego
 * ostrzeżenie samo cichnie, gdy ostatni błąd jest starszy niż to okno.
 */
const CHATL_BLAD_BAZY_OKNO_DNI = 14;

function chatl_log_bledu_bazy( $funkcja, $operacja ) {
	global $wpdb;
	$szczegol = '' !== (string) $wpdb->last_error ? (string) $wpdb->last_error : 'sterownik nie podał komunikatu';

	/*
	 * ⛔ TRWAŁY ŚLAD, NIEZALEŻNY OD WP_DEBUG. wp_trigger_error() niżej jest dla
	 * instalacji deweloperskiej: rdzeń WordPressa kończy je natychmiast, gdy
	 * WP_DEBUG jest wyłączony (wp-includes/functions.php: „Bail out if WP_DEBUG
	 * is not turned on"), a wyłączony jest na KAŻDEJ normalnej instalacji
	 * klienta. Bez tego zapisu 29 miejsc w obu wtyczkach traciło błąd bez śladu
	 * i właściciel nie miał jak się dowiedzieć, że zapis nie doszedł.
	 *
	 * Ten sam wzorzec, co znacznik błędu trybu AI: opcja + pasek w panelu.
	 * Liczymy powtórzenia zamiast dopisywać wpis za wpisem — błąd w pętli
	 * (import CSV, seed, cron) inaczej zalałby tabelę opcji.
	 *
	 * ⚠️ To jest kanał NAJLEPSZEGO WYSIŁKU: gdy padnie cała baza, ten zapis
	 * też się nie uda. Chroni przed awarią POJEDYNCZEGO zapytania, nie przed
	 * awarią serwera bazy — i tak jest opisany w dokumentacji.
	 */
	$wpis = get_option( 'chatl_blad_bazy' );
	$wpis = is_array( $wpis ) ? $wpis : array();
	$ile  = isset( $wpis['ile'] ) ? (int) $wpis['ile'] + 1 : 1;
	update_option(
		'chatl_blad_bazy',
		array(
			'ostatni'  => current_time( 'mysql' ),
			'funkcja'  => (string) $funkcja,
			'operacja' => mb_substr( (string) $operacja, 0, 200 ),
			'szczegol' => mb_substr( $szczegol, 0, 300 ),
			'ile'      => $ile,
		),
		false
	);

	if ( ! function_exists( 'wp_trigger_error' ) ) {
		return;
	}
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
require_once CHATL_KATALOG . 'includes/przejecie.php';
require_once CHATL_KATALOG . 'includes/rodo.php';
require_once CHATL_KATALOG . 'includes/front.php';

register_activation_hook( __FILE__, 'chatl_aktywacja' );
register_deactivation_hook( __FILE__, 'chatl_deaktywacja' );

if ( is_admin() ) {
	require_once CHATL_KATALOG . 'includes/admin.php';
	require_once CHATL_KATALOG . 'includes/operator.php';
	require_once CHATL_KATALOG . 'includes/csv.php';
	require_once CHATL_KATALOG . 'includes/import.php';
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
		/*
		 * Dobowy limit TOKENOW — drugi stop-loss, obok limitu wywołań.
		 * 200 000 to mniej więcej tyle, ile zużyje 100 wywołań (domyślny limit
		 * wywołań) przy typowej bazie Q&A małej firmy. Zaczyna więc gryźć
		 * dopiero, gdy POJEDYNCZE wywołanie jest nienormalnie drogie — czyli
		 * dokładnie w przypadku, którego limit wywołań w ogóle nie widzi.
		 * 0 = wyłączony (świadoma decyzja właściciela).
		 */
		'ai_limit_tokenow' => 200000,
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
