<?php
/**
 * Import próbki z CSV + eksport wyników przebiegu do CSV.
 * CSV to wejście NIEZAUFANE: sanitize każdej komórki, limity, a przy eksporcie
 * neutralizacja formuł arkuszy (OWASP CSV injection: komórka od =,+,-,@).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SEGM_IMPORT_MAX_WIERSZY = 500;
const SEGM_MAX_POLE           = 2000;

/**
 * Import CSV: kolumny od,temat,tresc[,kategoria_oczekiwana]; import ZASTĘPUJE
 * bieżącą próbkę (klient wrzuca eksport swojej skrzynki zamiast demo).
 *
 * @param string $sciezka Ścieżka tmp wgranego pliku.
 * @return array|WP_Error ['zaimportowano' => int, 'pominieto' => int]
 */
function segm_importuj_csv( $sciezka ) {
	global $wpdb;

	$uchwyt = fopen( $sciezka, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $uchwyt ) {
		return new WP_Error( 'segm_csv', 'Nie udało się odczytać pliku.' );
	}

	$wiersze   = array();
	$pominieto = 0;
	$pierwszy  = true;

	while ( false !== ( $w = fgetcsv( $uchwyt, 0, ',', '"', '\\' ) ) ) {
		if ( $pierwszy ) {
			$pierwszy = false;
			// Wiersz nagłówka (od,temat,...) pomijamy.
			if ( isset( $w[0] ) && 'od' === strtolower( trim( (string) $w[0] ) ) ) {
				continue;
			}
		}
		if ( count( $wiersze ) >= SEGM_IMPORT_MAX_WIERSZY ) {
			$pominieto++;
			continue;
		}
		$od    = isset( $w[0] ) ? sanitize_text_field( $w[0] ) : '';
		$temat = isset( $w[1] ) ? sanitize_text_field( $w[1] ) : '';
		$tresc = isset( $w[2] ) ? sanitize_textarea_field( $w[2] ) : '';
		$oczek = isset( $w[3] ) ? sanitize_text_field( $w[3] ) : '';
		if ( '' === $od && '' === $temat && '' === $tresc ) {
			$pominieto++;
			continue;
		}
		$wiersze[] = array(
			'zrodlo'               => 'import',
			'nadawca'              => mb_substr( $od, 0, 190 ),
			'temat'                => mb_substr( $temat, 0, SEGM_MAX_POLE ),
			'tresc'                => mb_substr( $tresc, 0, SEGM_MAX_POLE ),
			'kategoria_oczekiwana' => mb_substr( $oczek, 0, 100 ),
			'utworzono'            => current_time( 'mysql' ),
		);
	}
	fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	if ( ! $wiersze ) {
		return new WP_Error( 'segm_csv', 'Plik nie zawiera żadnego poprawnego wiersza (kolumny: od, temat, tresc, opcjonalnie kategoria_oczekiwana).' );
	}

	$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $wiersze as $wiersz ) {
		$wpdb->insert( $wpdb->prefix . 'segm_maile', $wiersz, array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	return array(
		'zaimportowano' => count( $wiersze ),
		'pominieto'     => $pominieto,
	);
}

/**
 * Neutralizacja komórki na eksport (arkusze wykonują komórki od =,+,-,@).
 */
function segm_csv_komorka( $wartosc ) {
	$wartosc = (string) $wartosc;
	if ( '' !== $wartosc && in_array( $wartosc[0], array( '=', '+', '-', '@' ), true ) ) {
		$wartosc = "'" . $wartosc;
	}
	return $wartosc;
}

/**
 * Eksport wyników przebiegu do CSV (strumień do przeglądarki).
 *
 * @param object $przebieg Wiersz segm_przebiegi.
 * @param array  $wyniki   Zdekodowane wyniki przebiegu.
 */
function segm_eksportuj_csv( $przebieg, $wyniki ) {
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=segregator-przebieg-' . (int) $przebieg->id . '.csv' );

	$porownanie = 'porownaj' === $przebieg->tryb;

	$out      = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$naglowek = array( 'od', 'temat', 'kategoria', 'regula_id', 'kategoria_oczekiwana', 'trafiony' );
	if ( $porownanie ) {
		$naglowek[] = 'kategoria_ai';
		$naglowek[] = 'roznica';
	}
	fputcsv( $out, $naglowek, ',', '"', '\\' );
	foreach ( $wyniki as $w ) {
		$wiersz = array(
			segm_csv_komorka( $w['nadawca'] ),
			segm_csv_komorka( $w['temat'] ),
			segm_csv_komorka( $w['kategoria'] ),
			null === $w['regula_id'] ? '' : (int) $w['regula_id'],
			segm_csv_komorka( $w['oczekiwana'] ),
			null === $w['trafiony'] ? '' : ( $w['trafiony'] ? 'tak' : 'NIE' ),
		);
		if ( $porownanie ) {
			$wiersz[] = segm_csv_komorka( $w['kategoria_ai'] ?? '' );
			$wiersz[] = empty( $w['roznica'] ) ? '' : 'ROZNICA';
		}
		fputcsv( $out, $wiersz, ',', '"', '\\' );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
