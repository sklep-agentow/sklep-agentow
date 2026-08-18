<?php
/**
 * Brama pobrań — WYŁĄCZNIE w PHP (D4): token HMAC-owany (w bazie skrót),
 * termin 72 h, limit 10, licznik ATOMOWY przy starcie GET, bez Accept-Ranges,
 * HEAD nie zużywa limitu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_nopriv_sklepag_pobierz', 'sklepag_obsluz_pobranie' );
add_action( 'admin_post_sklepag_pobierz', 'sklepag_obsluz_pobranie' );

function sklepag_obsluz_pobranie() {
	if ( ! sklepag_rate_limit( 'pobierz', 30 ) ) {
		wp_die( 'Za dużo prób. Odczekaj minutę.', 'Sklep Agentów', 429 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- link pobrania przychodzi Z MAILA: kupujący nie ma sesji WP, nonce niemożliwy; autoryzacją jest token HMAC (porównanie stałoczasowe niżej).
	$id    = isset( $_GET['z'] ) ? absint( $_GET['z'] ) : 0;
	$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE id = %d", $id ) );

	// Mail kontaktowy właściciela z ustawień (plan §6); bez niego — odesłanie do maila z zakupu.
	$email_kontaktowy = (string) get_option( 'sklepag_email_kontaktowy', '' );
	$kontakt          = ( '' !== $email_kontaktowy && is_email( $email_kontaktowy ) )
		? 'Skontaktuj się z właścicielem sklepu: ' . $email_kontaktowy
		: 'Skontaktuj się z właścicielem sklepu (odpisz na maila z zakupem).';

	if ( ! $zam || '' === $zam->token_pobrania || 64 !== strlen( $token )
		|| ! hash_equals( $zam->token_pobrania, hash( 'sha256', $token ) ) ) {
		wp_die( 'Nieprawidłowy link pobrania. ' . esc_html( $kontakt ), 'Sklep Agentów', 403 );
	}

	if ( ! $zam->link_wygasa || strtotime( $zam->link_wygasa ) < time() ) {
		wp_die( 'Link pobrania wygasł. ' . esc_html( $kontakt ), 'Sklep Agentów', 410 );
	}

	$plik = (string) $zam->produkt_plik_kupiony; // Snapshot z chwili zakupu.
	if ( '' === $plik || ! file_exists( $plik ) ) {
		sklepag_zdarzenie( (int) $zam->id, 'pobranie_brak_pliku', $plik );
		wp_die( 'Plik chwilowo niedostępny. ' . esc_html( $kontakt ), 'Sklep Agentów', 503 );
	}

	$rozmiar = filesize( $plik );
	$nazwa   = sanitize_file_name( $zam->produkt_nazwa_kupiona ) . '.zip';

	// HEAD: nagłówki bez zużywania limitu (skanery poczty dotykają linków).
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Length: ' . $rozmiar );
		header( 'Accept-Ranges: none' );
		exit;
	}

	// LICZNIK ATOMOWY przy STARCIE pobrania (UPDATE z warunkiem — D4).
	$slot = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET pobrania_uzyte = pobrania_uzyte + 1, zmieniono = %s WHERE id = %d AND pobrania_uzyte < pobrania_limit",
			current_time( 'mysql' ),
			(int) $zam->id
		)
	);
	// phpcs:enable

	if ( ! $slot ) {
		wp_die( 'Limit pobrań wyczerpany. ' . esc_html( $kontakt ), 'Sklep Agentów', 410 );
	}

	sklepag_zdarzenie( (int) $zam->id, 'pobranie', 'ip ' . sklepag_ip() );

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $nazwa . '"' );
	header( 'Content-Length: ' . $rozmiar );
	header( 'Accept-Ranges: none' );
	header( 'X-Robots-Tag: noindex' );

	readfile( $plik ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- strumieniowanie pliku bramą.
	exit;
}
