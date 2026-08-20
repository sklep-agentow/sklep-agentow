<?php
/**
 * Silnik regułowy: proste dopasowanie podłańcuchów po wariantach słów kluczowych.
 * Odmiany łapane przez warianty wpisane ręcznie („cena, ceny, cennik") —
 * automatyczny stemming to droga rozbudowy, nie brak w tej wersji.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Które warianty pary trafiły w pytanie gościa.
 *
 * JEDYNE miejsce, gdzie żyje reguła dopasowania (małe litery, podział po
 * przecinku, szukanie podłańcucha). Wołają ją i silnik (chatl_dopasuj), i test
 * bota w panelu — inaczej panel mógłby pokazywać co innego, niż bot naprawdę
 * zrobił, a wtedy panel kłamie na temat własnego produktu.
 *
 * @param string $pytanie        Pytanie gościa (surowe).
 * @param string $slowa_kluczowe Warianty pary, rozdzielone przecinkami.
 * @return array Lista trafionych wariantów (już po mb_strtolower i trim).
 */
function chatl_trafione_warianty( $pytanie, $slowa_kluczowe ) {
	$szukane  = mb_strtolower( trim( (string) $pytanie ) );
	$trafione = array();
	if ( '' === $szukane ) {
		return $trafione;
	}
	$warianty = array_filter( array_map( 'trim', explode( ',', mb_strtolower( (string) $slowa_kluczowe ) ) ) );
	foreach ( $warianty as $wariant ) {
		if ( false !== mb_strpos( $szukane, $wariant ) ) {
			$trafione[] = $wariant;
		}
	}
	return $trafione;
}

/**
 * Punktacja jednej pary: ile wariantów trafiło i jak długie łącznie.
 *
 * Wydzielone razem z dopasowaniem, żeby „ile trafień" i „jak liczymy remis"
 * też miały jedno źródło — panel pokazuje dokładnie te liczby, którymi
 * silnik rozstrzygnął.
 *
 * @param array $trafione Wynik chatl_trafione_warianty().
 * @return array ['trafienia' => int, 'dlugosc' => int]
 */
function chatl_punktacja( $trafione ) {
	$dlugosc = 0;
	foreach ( $trafione as $wariant ) {
		$dlugosc += mb_strlen( $wariant );
	}
	return array(
		'trafienia' => count( $trafione ),
		'dlugosc'   => $dlugosc,
	);
}

/**
 * Szuka najlepszej odpowiedzi na pytanie gościa.
 *
 * Wynik: liczba trafionych wariantów decyduje; przy remisie wygrywa dłuższe
 * łączne trafienie (bardziej konkretny wpis). Zero trafień => null.
 *
 * @param string $pytanie Surowe pytanie gościa.
 * @return array|null ['id' => int, 'odpowiedz' => string, 'trafione' => array]
 *                    albo null. 'trafione' = warianty, którymi ta para wygrała
 *                    (panel pokazuje je bez powtarzania algorytmu).
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

	$trafione_najlepszego = array();

	foreach ( $wpisy as $wpis ) {
		$trafione  = chatl_trafione_warianty( $szukane, $wpis->slowa_kluczowe );
		$punkty    = chatl_punktacja( $trafione );
		$trafienia = $punkty['trafienia'];
		$dlugosc   = $punkty['dlugosc'];
		if ( $trafienia > $max_traf || ( $trafienia === $max_traf && $trafienia > 0 && $dlugosc > $max_dl ) ) {
			$max_traf             = $trafienia;
			$max_dl               = $dlugosc;
			$najlepszy            = $wpis;
			$trafione_najlepszego = $trafione;
		}
	}

	if ( null === $najlepszy ) {
		return null;
	}

	return array(
		'id'        => (int) $najlepszy->id,
		'odpowiedz' => $najlepszy->odpowiedz,
		'trafione'  => $trafione_najlepszego,
	);
}

/**
 * Zapisuje wymianę do historii rozmów.
 *
 * @param string $sesja  Identyfikator sesji przeglądarki (już zwalidowany).
 * @param string $rola   'gosc' albo 'bot'.
 * @param string $tresc  Treść (już zsanityzowana).
 * @param string $silnik 'reguly' albo 'ai' (który silnik odpowiedział).
 * @return bool True = zapisano, false = zapis padł (odpowiedź bota i tak leci
 *              do gościa; brak wpisu w historii nie jest powodem, żeby urwać
 *              działającą rozmowę — ale ma zostawić ślad w logu).
 */
function chatl_zapisz_wymiane( $sesja, $rola, $tresc, $silnik = 'reguly' ) {
	global $wpdb;
	$zapisano = $wpdb->insert(
		$wpdb->prefix . 'chatl_rozmowy',
		array(
			'sesja'     => $sesja,
			'rola'      => $rola,
			'silnik'    => $silnik,
			'tresc'     => $tresc,
			'utworzono' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $zapisano ) {
		chatl_log_bledu_bazy( 'chatl_zapisz_wymiane', 'wpis do historii rozmów (rola: ' . $rola . ')' );
		return false;
	}
	return true;
}
