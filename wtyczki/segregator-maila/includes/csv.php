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
 * Autodetekcja separatora po PIERWSZEJ linii pliku: polski Excel („Zapisz
 * jako CSV" przy locale PL) daje średniki, ręczne pliki — przecinki.
 * Wygrywa separator, który rozcina pierwszą linię na więcej kolumn.
 *
 * @param string $linia Pierwsza linia pliku (bez BOM).
 * @return string ',' albo ';'
 */
function segm_wykryj_separator( $linia ) {
	$przecinkiem = count( str_getcsv( $linia, ',', '"', '\\' ) );
	$srednikiem  = count( str_getcsv( $linia, ';', '"', '\\' ) );
	return $srednikiem > $przecinkiem ? ';' : ',';
}

/**
 * Import CSV: kolumny od,temat,tresc[,kategoria_oczekiwana]; import ZASTĘPUJE
 * bieżącą próbkę (klient wrzuca eksport swojej skrzynki zamiast demo).
 * Separator (przecinek/średnik) wykrywany automatycznie; BOM UTF-8 zdejmowany;
 * wiersz danych z mniej niż 3 kolumnami = ODRZUCENIE całego importu (żadnego
 * cichego importowania śmieci przy złym separatorze).
 *
 * @param string $sciezka Ścieżka tmp wgranego pliku.
 * @return array|WP_Error ['zaimportowano' => int, 'pominieto' => int, 'separator' => string]
 */
function segm_importuj_csv( $sciezka ) {
	global $wpdb;

	$uchwyt = fopen( $sciezka, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $uchwyt ) {
		return new WP_Error( 'segm_csv', 'Nie udało się odczytać pliku.' );
	}

	$pierwsza_linia = fgets( $uchwyt );
	if ( false === $pierwsza_linia ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'segm_csv', 'Plik jest pusty.' );
	}
	$separator = segm_wykryj_separator( preg_replace( '/^\xEF\xBB\xBF/', '', $pierwsza_linia ) );
	rewind( $uchwyt );

	$wiersze   = array();
	$pominieto = 0;
	$pierwszy  = true;
	$nr        = 0;

	while ( false !== ( $w = fgetcsv( $uchwyt, 0, $separator, '"', '\\' ) ) ) {
		$nr++;
		if ( $pierwszy ) {
			$pierwszy = false;
			// Excel przy zapisie UTF-8 dokleja BOM — zdejmij przed rozpoznaniem nagłówka.
			if ( isset( $w[0] ) ) {
				$w[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $w[0] );
			}
			// Wiersz nagłówka (od,temat,...) pomijamy.
			if ( isset( $w[0] ) && 'od' === strtolower( trim( (string) $w[0] ) ) ) {
				continue;
			}
		}
		// Pustą linię (np. końcówka pliku) pomijamy bez błędu.
		$niepuste = array_filter( $w, fn( $p ) => '' !== trim( (string) $p ) );
		if ( ! $niepuste ) {
			$pominieto++;
			continue;
		}
		// Wiersz danych z < 3 kolumnami = zły separator/format => twarde odrzucenie.
		if ( count( $w ) < 3 ) {
			fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error(
				'segm_csv',
				sprintf(
					'Import odrzucony: wiersz %d ma mniej niż 3 kolumny (wykryty separator: „%s"). Wymagane kolumny: od%stemat%stresc — sprawdź separator i format pliku. Próbka NIE została zmieniona.',
					$nr,
					$separator,
					$separator,
					$separator
				)
			);
		}
		if ( count( $wiersze ) >= SEGM_IMPORT_MAX_WIERSZY ) {
			$pominieto++;
			continue;
		}
		$od    = sanitize_text_field( (string) $w[0] );
		$temat = sanitize_text_field( (string) $w[1] );
		$tresc = sanitize_textarea_field( (string) $w[2] );
		$oczek = isset( $w[3] ) ? sanitize_text_field( (string) $w[3] ) : '';
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
		'separator'     => $separator,
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
