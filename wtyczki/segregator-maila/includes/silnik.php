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
 * Przebieg: przepuszcza CAŁĄ próbkę w wybranym trybie, zapisuje wynik + raport.
 *
 * Tryby: 'reguly' | 'ai' | 'porownaj'. Gdy AI zawiedzie (błąd/limit/klucz),
 * przebieg ZJEŻDŻA do trybu regułowego — jak w ChatBocie.
 *
 * @param string $tryb Tryb przebiegu.
 * @return array ['id' => int, 'zjazd' => bool] Zjazd = chciano AI, wyszły reguły.
 */
function segm_wykonaj_przebieg( $tryb = 'reguly' ) {
	global $wpdb;

	$reguly = segm_pobierz_reguly();
	$maile  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_maile ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$zjazd   = false;
	$mapa_ai = null;
	if ( in_array( $tryb, array( 'ai', 'porownaj' ), true ) ) {
		$mapa_ai = segm_ai_dostepny() ? segm_ai_klasyfikuj_probke( $maile ) : null;
		if ( null === $mapa_ai ) {
			$tryb  = 'reguly';
			$zjazd = true;
		}
	}

	$wyniki         = array();
	$dopasowanych   = 0;
	$etykietowanych = 0;
	$trafionych     = 0;

	foreach ( $maile as $mail ) {
		$oczekiwana = trim( $mail->kategoria_oczekiwana );
		$wpis       = array(
			'mail_id'    => (int) $mail->id,
			'nadawca'    => $mail->nadawca,
			'temat'      => $mail->temat,
			'oczekiwana' => $oczekiwana,
		);

		if ( 'ai' === $tryb ) {
			$kategoria         = $mapa_ai[ (int) $mail->id ] ?? SEGM_KUBEL_DOMYSLNY;
			$wpis['kategoria'] = $kategoria;
			$wpis['regula_id'] = null;
			if ( SEGM_KUBEL_DOMYSLNY !== $kategoria ) {
				$dopasowanych++;
			}
		} else {
			$wynik             = segm_klasyfikuj_mail( $mail, $reguly );
			$kategoria         = $wynik['kategoria'];
			$wpis['kategoria'] = $kategoria;
			$wpis['regula_id'] = $wynik['regula_id'];
			if ( null !== $wynik['regula_id'] ) {
				$dopasowanych++;
			}
		}

		$wpis['trafiony'] = null; // null = brak etykiety, nie liczy się do accuracy.
		if ( '' !== $oczekiwana ) {
			$etykietowanych++;
			$wpis['trafiony'] = ( 0 === strcasecmp( $oczekiwana, $kategoria ) );
			if ( $wpis['trafiony'] ) {
				$trafionych++;
			}
		}

		if ( 'porownaj' === $tryb ) {
			$kat_ai               = $mapa_ai[ (int) $mail->id ] ?? SEGM_KUBEL_DOMYSLNY;
			$wpis['kategoria_ai'] = $kat_ai;
			$wpis['roznica']      = ( 0 !== strcasecmp( $kategoria, $kat_ai ) );
			$wpis['trafiony_ai']  = '' === $oczekiwana ? null : ( 0 === strcasecmp( $oczekiwana, $kat_ai ) );
		}

		$wyniki[] = $wpis;
	}

	$wpdb->insert(
		$wpdb->prefix . 'segm_przebiegi',
		array(
			'tryb'           => $tryb,
			'maili'          => count( $maile ),
			'dopasowanych'   => $dopasowanych,
			'etykietowanych' => $etykietowanych,
			'trafionych'     => $trafionych,
			'wyniki'         => wp_json_encode( $wyniki ),
			'uruchomiono'    => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
	);

	return array(
		'id'    => (int) $wpdb->insert_id,
		'zjazd' => $zjazd,
	);
}
