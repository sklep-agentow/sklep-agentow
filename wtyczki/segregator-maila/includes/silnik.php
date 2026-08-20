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
 * @return array ['id' => int, 'zjazd' => bool] + opcjonalnie ['blad' => string].
 *               Zjazd = chciano AI, wyszły reguły.
 */
function segm_wykonaj_przebieg( $tryb = 'reguly' ) {
	global $wpdb;

	// P2.3: przebieg czyta próbkę, a import/demo/„wyczyść teraz"/retencja ją
	// kasują — bez znacznika dwie karty naraz dawały raport policzony na
	// pustej próbce, i to bez słowa ostrzeżenia.
	$zajeta = segm_zajmij_blokade( 'przebieg segregacji' );
	if ( true !== $zajeta ) {
		return array(
			'id'    => 0,
			'zjazd' => false,
			'blad'  => sprintf(
				'Trwa inna operacja na próbce (%s) — przebieg NIE wystartował, bo policzyłby się na danych zmienianych w trakcie. Odczekaj chwilę i uruchom go ponownie.',
				$zajeta
			),
		);
	}

	$reguly = segm_pobierz_reguly();
	$maile  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_maile ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$zjazd   = false;
	$mapa_ai = null;
	if ( in_array( $tryb, array( 'ai', 'porownaj' ), true ) ) {
		// P0.4: bez wyraźnej zgody właściciela treść cudzych maili NIE wychodzi
		// do zewnętrznego dostawcy. To NIE jest zjazd do reguł — cichy zjazd
		// wyglądałby jak „AI nie odpowiedziało", a tu chodzi o świadomą decyzję,
		// której nikt jeszcze nie podjął. Przebieg NIE startuje.
		if ( ! segm_zgoda_ai_udzielona() ) {
			segm_zwolnij_blokade();
			return array(
				'id'    => 0,
				'zjazd' => false,
				'blad'  => 'Tryb AI wymaga jednorazowego potwierdzenia: w trybie AI treść maili z próbki (także korespondencja osób trzecich) wychodzi do zewnętrznego dostawcy modelu. Potwierdź to w Ustawieniach Segregatora — sekcja „Zgoda na wysyłkę próbki do AI". Do tego czasu działa tryb „reguły" (lokalnie, nic nie opuszcza Twojego WordPressa).',
			);
		}

		// TWARDY limit próbki dla AI (limit PHP 30 s): JAWNY błąd zamiast
		// fatala w środku pętli albo cichego zjazdu — przebieg NIE startuje.
		if ( count( $maile ) > SEGM_AI_MAX_PROBKA ) {
			segm_zwolnij_blokade();
			return array(
				'id'    => 0,
				'zjazd' => false,
				'blad'  => sprintf(
					'Tryb AI obsługuje próbkę do %d maili (Twoja ma %d): jedno wywołanie API trwa do 18 s (timeout 9 s + jeden retry), a typowy limit czasu żądania na hostingu to 30 s — większa próbka urwałaby przebieg w połowie. Uruchom tryb „reguły" (bez limitu) albo zmniejsz próbkę.',
					SEGM_AI_MAX_PROBKA,
					count( $maile )
				),
			);
		}
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
				++$dopasowanych;
			}
		} else {
			$wynik             = segm_klasyfikuj_mail( $mail, $reguly );
			$kategoria         = $wynik['kategoria'];
			$wpis['kategoria'] = $kategoria;
			$wpis['regula_id'] = $wynik['regula_id'];
			if ( null !== $wynik['regula_id'] ) {
				++$dopasowanych;
			}
		}

		$wpis['trafiony'] = null; // null = brak etykiety, nie liczy się do accuracy.
		if ( '' !== $oczekiwana ) {
			++$etykietowanych;
			$wpis['trafiony'] = ( 0 === strcasecmp( $oczekiwana, $kategoria ) );
			if ( $wpis['trafiony'] ) {
				++$trafionych;
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

	// Bez tego sprawdzenia padnięty zapis kończył się przekierowaniem na raport
	// „#0", czyli „Nie ma takiego przebiegu" — admin widział pustkę zamiast błędu.
	$zapisano = $wpdb->insert(
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
	if ( false === $zapisano ) {
		segm_log_bledu_bazy( 'segm_wykonaj_przebieg', 'zapis wyniku przebiegu (tryb: ' . $tryb . ')' );
		segm_zwolnij_blokade();
		return array(
			'id'    => 0,
			'zjazd' => $zjazd,
			'blad'  => 'Przebieg policzył się, ale zapis wyniku do bazy nie powiódł się — raportu NIE ma. Sprawdź stan bazy i uruchom przebieg ponownie.',
		);
	}

	$id = (int) $wpdb->insert_id;
	segm_zwolnij_blokade();

	// Pas i szelki do znacznika: gdyby próbka mimo wszystko zmieniła się w
	// trakcie liczenia (znacznik nie jest atomowy co do milisekundy), raport
	// ma to POWIEDZIEĆ, a nie udawać, że policzył się na tym, co jest w bazie.
	$ile_teraz = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wynik     = array(
		'id'    => $id,
		'zjazd' => $zjazd,
	);
	if ( count( $maile ) !== $ile_teraz ) {
		$wynik['ostrzezenie'] = sprintf(
			'UWAGA: w trakcie przebiegu próbka zmieniła się (na starcie miała %d maili, teraz ma %d) — raport #%d opisuje stan sprzed zmiany. Uruchom przebieg ponownie, żeby liczby zgadzały się z bieżącą próbką.',
			count( $maile ),
			$ile_teraz,
			$id
		);
	}
	return $wynik;
}
