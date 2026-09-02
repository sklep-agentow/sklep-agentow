<?php
/**
 * Ręczny eksport zgłoszeń i historii rozmów do CSV (przyciskiem w panelu).
 *
 * ⛔ ŚWIADOMIE BEZ eksportu automatycznego przed kasowaniem po 90 dniach:
 * zrzut do pliku tuż przed usunięciem znaczy, że dane NIE są kasowane, tylko
 * przenoszone gdzie indziej — sprzeczne z obietnicą „kasujemy po 90 dniach"
 * i z zasadą minimalizacji danych. Kiedy właściciel chce kopię, bierze ją sam
 * i sam odpowiada za to, co z nią dalej robi.
 *
 * Treści pochodzą od gości (wejście NIEZAUFANE), więc każda komórka przechodzi
 * neutralizację formuł arkuszy (OWASP CSV injection).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CHATL_CSV_PORCJA = 500;

/**
 * Neutralizacja komórki na eksport (arkusze wykonują komórki od =,+,-,@).
 *
 * @param string $wartosc Surowa wartość.
 * @return string Wartość bezpieczna do wstawienia w arkusz.
 */
function chatl_csv_komorka( $wartosc ) {
	$wartosc = (string) $wartosc;
	if ( '' !== $wartosc && in_array( $wartosc[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		$wartosc = "'" . $wartosc;
	}
	return $wartosc;
}

/**
 * Nagłówki HTTP pliku CSV + uchwyt do strumienia wyjścia.
 *
 * BOM UTF-8 na starcie: bez niego Excel (polskie ustawienia) czyta plik jako
 * windows-1250 i polskie znaki wychodzą krzakami. Import w drugą stronę BOM
 * zdejmuje, więc plik zostaje zgodny w obie strony.
 *
 * @param string $nazwa Nazwa pliku.
 * @return resource
 */
function chatl_csv_start( $nazwa ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $nazwa );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	return $out;
}

/**
 * Wspólna brama eksportu: capability + nonce.
 *
 * @param string $akcja Nazwa akcji (element nonce'a).
 * @return void
 */
function chatl_csv_brama( $akcja ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( $akcja );
}

/**
 * Eksport wszystkich zgłoszeń z czatu.
 */
function chatl_post_eksport_zgloszen() {
	chatl_csv_brama( 'chatl_eksport_zgloszen' );

	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_zgloszenia';
	$out    = chatl_csv_start( 'chatbot-zgloszenia-' . gmdate( 'Y-m-d' ) . '.csv' );
	fputcsv( $out, array( 'id', 'data', 'email', 'wiadomosc', 'status', 'sesja' ), ',', '"', '\\' );

	// Porcjami po ID: przy dużej liczbie zgłoszeń nie wciągamy całej tabeli
	// do pamięci PHP naraz.
	$od = 0;
	do {
		$wiersze = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id > %d ORDER BY id ASC LIMIT %d", $od, CHATL_CSV_PORCJA ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ile     = count( $wiersze );
		foreach ( $wiersze as $z ) {
			$od = (int) $z->id;
			fputcsv(
				$out,
				array(
					(int) $z->id,
					chatl_csv_komorka( $z->utworzono ),
					chatl_csv_komorka( $z->email ),
					chatl_csv_komorka( $z->wiadomosc ),
					chatl_csv_komorka( $z->status ),
					chatl_csv_komorka( $z->sesja ),
				),
				',',
				'"',
				'\\'
			);
		}
	} while ( CHATL_CSV_PORCJA === $ile );

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_chatl_eksport_zgloszen', 'chatl_post_eksport_zgloszen' );

/**
 * Eksport historii rozmów (całej albo jednej sesji).
 */
function chatl_post_eksport_rozmow() {
	chatl_csv_brama( 'chatl_eksport_rozmow' );

	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_rozmowy';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce sprawdzony w chatl_csv_brama() linijkę wyżej.
	$sesja = isset( $_GET['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_GET['sesja'] ) ) : '';

	$nazwa = '' === $sesja
		? 'chatbot-rozmowy-' . gmdate( 'Y-m-d' ) . '.csv'
		: 'chatbot-rozmowa-' . $sesja . '.csv';
	$out   = chatl_csv_start( $nazwa );
	fputcsv( $out, array( 'sesja', 'data', 'kto', 'silnik', 'tresc' ), ',', '"', '\\' );

	$od = 0;
	do {
		if ( '' === $sesja ) {
			$wiersze = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id > %d ORDER BY id ASC LIMIT %d", $od, CHATL_CSV_PORCJA ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$wiersze = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE sesja = %s AND id > %d ORDER BY id ASC LIMIT %d", $sesja, $od, CHATL_CSV_PORCJA ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$ile = count( $wiersze );
		foreach ( $wiersze as $w ) {
			$od = (int) $w->id;
			fputcsv(
				$out,
				array(
					chatl_csv_komorka( $w->sesja ),
					chatl_csv_komorka( $w->utworzono ),
					chatl_nazwa_roli( $w->rola ),
					chatl_csv_komorka( $w->silnik ),
					chatl_csv_komorka( $w->tresc ),
				),
				',',
				'"',
				'\\'
			);
		}
	} while ( CHATL_CSV_PORCJA === $ile );

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_chatl_eksport_rozmow', 'chatl_post_eksport_rozmow' );

/**
 * Eksport bazy pytań i odpowiedzi.
 *
 * Kolumny są DOKŁADNIE tymi, które przyjmuje import (includes/import.php),
 * więc obieg „pobierz → popraw w arkuszu → wgraj z powrotem" domyka się bez
 * żadnej dokumentacji formatu: wzorem pliku jest własna baza właściciela.
 * Kolumny `id` świadomie NIE eksportujemy — import tylko dopisuje, więc numer
 * z pliku i tak nie miałby jak trafić do bazy, a jego obecność sugerowałaby
 * właścicielowi, że edycja pliku nadpisze istniejący wpis.
 */
function chatl_post_eksport_qa() {
	chatl_csv_brama( 'chatl_eksport_qa' );

	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_qa';
	$out    = chatl_csv_start( 'chatbot-baza-qa-' . gmdate( 'Y-m-d' ) . '.csv' );
	fputcsv( $out, array( 'pytanie', 'slowa_kluczowe', 'odpowiedz', 'aktywne' ), ',', '"', '\\' );

	$od = 0;
	do {
		$wiersze = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id > %d ORDER BY id ASC LIMIT %d", $od, CHATL_CSV_PORCJA ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ile     = count( $wiersze );
		foreach ( $wiersze as $w ) {
			$od = (int) $w->id;
			fputcsv(
				$out,
				array(
					chatl_csv_komorka( $w->pytanie ),
					chatl_csv_komorka( $w->slowa_kluczowe ),
					chatl_csv_komorka( $w->odpowiedz ),
					(int) $w->aktywne,
				),
				',',
				'"',
				'\\'
			);
		}
	} while ( CHATL_CSV_PORCJA === $ile );

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_chatl_eksport_qa', 'chatl_post_eksport_qa' );
