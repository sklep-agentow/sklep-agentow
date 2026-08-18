<?php
/**
 * Silnik regułowy: proste dopasowanie podłańcuchów po wariantach słów kluczowych.
 * Odmiany łapane przez warianty wpisane ręcznie („cena, ceny, cennik") —
 * automatyczny stemming = droga rozbudowy PO egzaminie (recenzja PR #27).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Szuka najlepszej odpowiedzi na pytanie gościa.
 *
 * Wynik: liczba trafionych wariantów decyduje; przy remisie wygrywa dłuższe
 * łączne trafienie (bardziej konkretny wpis). Zero trafień => null.
 *
 * @param string $pytanie Surowe pytanie gościa.
 * @return array|null ['id' => int, 'odpowiedz' => string] albo null.
 */
function chatl_dopasuj( $pytanie ) {
	global $wpdb;

	$szukane = mb_strtolower( trim( (string) $pytanie ) );
	if ( '' === $szukane ) {
		return null;
	}

	$tabela = $wpdb->prefix . 'chatl_qa';
	$wpisy  = $wpdb->get_results( "SELECT id, slowa_kluczowe, odpowiedz FROM {$tabela} WHERE aktywne = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$najlepszy = null;
	$max_traf  = 0;
	$max_dl    = 0;

	foreach ( $wpisy as $wpis ) {
		$warianty = array_filter( array_map( 'trim', explode( ',', mb_strtolower( $wpis->slowa_kluczowe ) ) ) );
		$trafienia = 0;
		$dlugosc   = 0;
		foreach ( $warianty as $wariant ) {
			if ( false !== mb_strpos( $szukane, $wariant ) ) {
				$trafienia++;
				$dlugosc += mb_strlen( $wariant );
			}
		}
		if ( $trafienia > $max_traf || ( $trafienia === $max_traf && $trafienia > 0 && $dlugosc > $max_dl ) ) {
			$max_traf  = $trafienia;
			$max_dl    = $dlugosc;
			$najlepszy = $wpis;
		}
	}

	if ( null === $najlepszy ) {
		return null;
	}

	return array(
		'id'        => (int) $najlepszy->id,
		'odpowiedz' => $najlepszy->odpowiedz,
	);
}

/**
 * Zapisuje wymianę do historii rozmów.
 *
 * @param string $sesja Identyfikator sesji przeglądarki (już zwalidowany).
 * @param string $rola  'gosc' albo 'bot'.
 * @param string $tresc Treść (już zsanityzowana).
 */
function chatl_zapisz_wymiane( $sesja, $rola, $tresc ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'chatl_rozmowy',
		array(
			'sesja'     => $sesja,
			'rola'      => $rola,
			'tresc'     => $tresc,
			'utworzono' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s' )
	);
}
