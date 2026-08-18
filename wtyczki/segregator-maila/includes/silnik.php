<?php
/**
 * Silnik regułowy segregacji: warunek→kategoria, kolejność = pierwsza wygrywa
 * (JEDEN mechanizm rozstrzygania, bez pola priorytet — recenzja PR #27).
 * Mail bez dopasowania trafia do kubła domyślnego „Reszta" (wzorzec Gmail:
 * brak reguły = mail zostaje w skrzynce głównej).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SEGM_KUBEL_DOMYSLNY = 'Reszta';

/**
 * Klasyfikuje jeden mail wg reguł (posortowanych po kolejnosc ASC).
 *
 * @param object $mail   Wiersz z segm_maile (nadawca, temat, tresc).
 * @param array  $reguly Wiersze z segm_reguly + nazwa kategorii (posortowane).
 * @return array ['kategoria' => string, 'regula_id' => int|null]
 */
function segm_klasyfikuj_mail( $mail, $reguly ) {
	$pola = array(
		'od'    => mb_strtolower( $mail->nadawca ),
		'temat' => mb_strtolower( $mail->temat ),
		'tresc' => mb_strtolower( $mail->tresc ),
	);

	foreach ( $reguly as $regula ) {
		if ( ! isset( $pola[ $regula->pole ] ) ) {
			continue;
		}
		$warianty = array_filter( array_map( 'trim', explode( ',', mb_strtolower( $regula->wzorzec ) ) ) );
		foreach ( $warianty as $wariant ) {
			if ( false !== mb_strpos( $pola[ $regula->pole ], $wariant ) ) {
				return array(
					'kategoria' => $regula->kategoria,
					'regula_id' => (int) $regula->id,
				);
			}
		}
	}

	return array(
		'kategoria' => SEGM_KUBEL_DOMYSLNY,
		'regula_id' => null,
	);
}

/**
 * Reguły z nazwami kategorii, w kolejności rozstrzygania.
 */
function segm_pobierz_reguly() {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT r.id, r.kolejnosc, r.pole, r.wzorzec, r.kategoria_id, k.nazwa AS kategoria
		 FROM {$wpdb->prefix}segm_reguly r
		 JOIN {$wpdb->prefix}segm_kategorie k ON k.id = r.kategoria_id
		 ORDER BY r.kolejnosc ASC, r.id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}

/**
 * Przebieg: przepuszcza CAŁĄ próbkę przez reguły, zapisuje wynik + raport.
 *
 * @return int ID przebiegu.
 */
function segm_wykonaj_przebieg() {
	global $wpdb;

	$reguly = segm_pobierz_reguly();
	$maile  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_maile ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$wyniki         = array();
	$dopasowanych   = 0;
	$etykietowanych = 0;
	$trafionych     = 0;

	foreach ( $maile as $mail ) {
		$wynik = segm_klasyfikuj_mail( $mail, $reguly );
		if ( null !== $wynik['regula_id'] ) {
			$dopasowanych++;
		}

		$oczekiwana = trim( $mail->kategoria_oczekiwana );
		$trafiony   = null; // null = brak etykiety, nie liczy się do accuracy.
		if ( '' !== $oczekiwana ) {
			$etykietowanych++;
			$trafiony = ( 0 === strcasecmp( $oczekiwana, $wynik['kategoria'] ) );
			if ( $trafiony ) {
				$trafionych++;
			}
		}

		$wyniki[] = array(
			'mail_id'    => (int) $mail->id,
			'nadawca'    => $mail->nadawca,
			'temat'      => $mail->temat,
			'kategoria'  => $wynik['kategoria'],
			'regula_id'  => $wynik['regula_id'],
			'oczekiwana' => $oczekiwana,
			'trafiony'   => $trafiony,
		);
	}

	$wpdb->insert(
		$wpdb->prefix . 'segm_przebiegi',
		array(
			'tryb'           => 'reguly',
			'maili'          => count( $maile ),
			'dopasowanych'   => $dopasowanych,
			'etykietowanych' => $etykietowanych,
			'trafionych'     => $trafionych,
			'wyniki'         => wp_json_encode( $wyniki ),
			'uruchomiono'    => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
	);

	return (int) $wpdb->insert_id;
}
