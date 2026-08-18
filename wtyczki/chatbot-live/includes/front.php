<?php
/**
 * Front: assety widgetu + endpointy AJAX (pytanie, zgłoszenie).
 * Czat = wejście NIEZAUFANE: sanitize na wejściu, escape na wyjściu, limity długości.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CHATL_MAX_PYTANIE   = 500;
const CHATL_MAX_WIADOMOSC = 2000;
const CHATL_LIMIT_NA_MIN  = 10;

/**
 * Rate limit per IP (transient, okno 60 s) — wspólny dla obu endpointów nopriv,
 * żeby gość z noncem nie zalewał bazy ani nie mail-bombił właściciela.
 *
 * @return bool True = wolno obsłużyć, false = limit wyczerpany.
 */
function chatl_limit_ip_ok() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $ip ) {
		return false;
	}
	$klucz   = 'chatl_rl_' . md5( $ip );
	$licznik = (int) get_transient( $klucz );
	if ( $licznik >= CHATL_LIMIT_NA_MIN ) {
		return false;
	}
	set_transient( $klucz, $licznik + 1, MINUTE_IN_SECONDS );
	return true;
}

/**
 * Assety tylko na froncie i tylko gdy bot włączony.
 */
function chatl_assety() {
	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		return;
	}

	wp_enqueue_style( 'chatl-widget', CHATL_URL . 'assets/widget.css', array(), CHATL_WERSJA );
	wp_enqueue_script( 'chatl-widget', CHATL_URL . 'assets/widget.js', array(), CHATL_WERSJA, true );
	wp_localize_script(
		'chatl-widget',
		'chatlDane',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'chatl_front' ),
			'powitanie' => $ust['powitanie'],
		)
	);
}
add_action( 'wp_enqueue_scripts', 'chatl_assety' );

/**
 * Walidacja identyfikatora sesji z przeglądarki (UUID lub podobny token).
 */
function chatl_waliduj_sesje( $sesja ) {
	$sesja = (string) $sesja;
	if ( ! preg_match( '/^[a-zA-Z0-9-]{8,64}$/', $sesja ) ) {
		return '';
	}
	return $sesja;
}

/**
 * AJAX: gość zadaje pytanie → odpowiedź z bazy Q&A albo tekst awaryjny.
 */
function chatl_ajax_pytanie() {
	check_ajax_referer( 'chatl_front', 'nonce' );

	if ( ! chatl_limit_ip_ok() ) {
		wp_send_json_error( array( 'komunikat' => 'Za dużo wiadomości w krótkim czasie — odczekaj chwilę i spróbuj ponownie.' ), 429 );
	}

	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		wp_send_json_error( array( 'komunikat' => 'Czat jest wyłączony.' ), 403 );
	}

	$pytanie = isset( $_POST['pytanie'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pytanie'] ) ) : '';
	$sesja   = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';

	if ( '' === $pytanie || '' === $sesja ) {
		wp_send_json_error( array( 'komunikat' => 'Puste pytanie.' ), 400 );
	}
	if ( mb_strlen( $pytanie ) > CHATL_MAX_PYTANIE ) {
		$pytanie = mb_substr( $pytanie, 0, CHATL_MAX_PYTANIE );
	}

	chatl_zapisz_wymiane( $sesja, 'gosc', $pytanie );

	$wynik = chatl_dopasuj( $pytanie );
	if ( null === $wynik ) {
		chatl_zapisz_wymiane( $sesja, 'bot', $ust['tekst_awaryjny'] );
		wp_send_json_success(
			array(
				'trafiono'  => false,
				'odpowiedz' => $ust['tekst_awaryjny'],
			)
		);
	}

	chatl_zapisz_wymiane( $sesja, 'bot', $wynik['odpowiedz'] );
	wp_send_json_success(
		array(
			'trafiono'  => true,
			'odpowiedz' => $wynik['odpowiedz'],
		)
	);
}
add_action( 'wp_ajax_chatl_pytanie', 'chatl_ajax_pytanie' );
add_action( 'wp_ajax_nopriv_chatl_pytanie', 'chatl_ajax_pytanie' );

/**
 * AJAX: gość zostawia kontakt → zgłoszenie + (opcja) mail do właściciela.
 */
function chatl_ajax_zgloszenie() {
	check_ajax_referer( 'chatl_front', 'nonce' );

	if ( ! chatl_limit_ip_ok() ) {
		wp_send_json_error( array( 'komunikat' => 'Za dużo wiadomości w krótkim czasie — odczekaj chwilę i spróbuj ponownie.' ), 429 );
	}

	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		wp_send_json_error( array( 'komunikat' => 'Czat jest wyłączony.' ), 403 );
	}

	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$wiadomosc = isset( $_POST['wiadomosc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wiadomosc'] ) ) : '';
	$sesja     = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'komunikat' => 'Podaj poprawny adres e-mail.' ), 400 );
	}
	if ( '' === $wiadomosc ) {
		wp_send_json_error( array( 'komunikat' => 'Napisz, w czym pomóc.' ), 400 );
	}
	if ( mb_strlen( $wiadomosc ) > CHATL_MAX_WIADOMOSC ) {
		$wiadomosc = mb_substr( $wiadomosc, 0, CHATL_MAX_WIADOMOSC );
	}

	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'chatl_zgloszenia',
		array(
			'email'     => $email,
			'wiadomosc' => $wiadomosc,
			'sesja'     => $sesja,
			'status'    => 'nowe',
			'utworzono' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);

	if ( $ust['powiadomienia'] && is_email( $ust['mail_powiadomien'] ) ) {
		wp_mail(
			$ust['mail_powiadomien'],
			'ChatBot LIVE: nowe zgłoszenie z czatu',
			"Gość zostawił wiadomość w czacie na stronie.\n\n"
			. "E-mail gościa: {$email}\n\n"
			. "Wiadomość:\n{$wiadomosc}\n\n"
			. 'Lista zgłoszeń: ' . admin_url( 'admin.php?page=chatl-zgloszenia' )
		);
	}

	wp_send_json_success( array( 'komunikat' => 'Dziękujemy! Mamy Twoją wiadomość — odezwiemy się na podany adres.' ) );
}
add_action( 'wp_ajax_chatl_zgloszenie', 'chatl_ajax_zgloszenie' );
add_action( 'wp_ajax_nopriv_chatl_zgloszenie', 'chatl_ajax_zgloszenie' );
