<?php
/**
 * RODO — eksporter i kasownik danych osobowych dla narzędzi prywatności WordPressa
 * (Narzędzia → Eksport / Usuń dane osobowe) oraz podpowiedź do Polityki prywatności.
 *
 * Segregator trzyma cudzą korespondencję w TRZECH tabelach: nadawca, temat i treść (`segm_maile`,
 * próbka/demo), nadawca i temat ruchów w skrzynce (`segm_skrz_ruchy`, dziennik przebiegów) oraz
 * nadawca i temat każdego maila próbki w raportach przebiegów (`segm_przebiegi`, kolumna `wyniki`
 * z JSON-em — raport w panelu i eksport CSV pokazują ten adres wprost).
 * Sklep i ChatBot LIVE rejestrują eksporter i kasownik od dawna — Segregator nie miał ich wcale,
 * więc na żądanie „wydaj / usuń moje dane” właściciel skrzynki nie miał czym odpowiedzieć.
 * Wzór: `chatbot-live/includes/rodo.php` (ta sama para filtrów, ten sam kształt odpowiedzi).
 *
 * Klucz osoby = adres e-mail nadawcy (porównanie bez rozróżniania wielkości liter).
 * Kasowanie: wiersze `segm_maile` znikają w całości (to treść korespondencji tej osoby);
 * w `segm_skrz_ruchy` i w JSON-ie raportów `segm_przebiegi` zostaje sam ślad (folder z → do,
 * stan, nadana kategoria) z wyczyszczonym nadawcą i tematem — dziennik i raport mają dowodzić,
 * CO segregator zrobił, nie KTO pisał. Wynik mierzony po skutku (rows_affected), nie po wywołaniu.
 * Raporty przeszukujemy po JSON-ie (LIKE po adresie zakodowanym tak, jak go zapisał
 * wp_json_encode), a dopasowanie osoby rozstrzyga porównanie pola `nadawca` w PHP.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SEGM_RODO_STRONA = 100;

/**
 * Raporty przebiegów (`segm_przebiegi`), w których JSON `wyniki` wymienia ten adres jako nadawcę.
 * Wstępny filtr LIKE po adresie zakodowanym jak przy zapisie (wp_json_encode), bez rozróżniania
 * wielkości liter; o tym, KTÓRE wpisy należą do osoby, rozstrzyga segm_rodo_wpis_osoby().
 *
 * @param string $email  Adres (już małymi literami, przycięty).
 * @param int    $limit  Ile wierszy raportów.
 * @param int    $offset Od którego.
 * @return array<int, object> Wiersze: id, tryb, uruchomiono, wyniki.
 */
function segm_rodo_przebiegi_osoby( $email, $limit, $offset ) {
	global $wpdb;
	$igla = '%' . $wpdb->esc_like( strtolower( trim( (string) wp_json_encode( $email ), '"' ) ) ) . '%';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki, nazwa z prefiksu WP.
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, tryb, uruchomiono, wyniki FROM {$wpdb->prefix}segm_przebiegi WHERE LOWER(wyniki) LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$igla,
			(int) $limit,
			(int) $offset
		)
	);
	// phpcs:enable
	return (array) $wiersze;
}

/**
 * Czy wpis z JSON-a raportu należy do tej osoby (po polu `nadawca`, bez rozróżniania wielkości liter).
 *
 * @param mixed  $wpis  Element tablicy `wyniki`.
 * @param string $email Adres (małymi literami).
 * @return bool
 */
function segm_rodo_wpis_osoby( $wpis, $email ) {
	return is_array( $wpis ) && isset( $wpis['nadawca'] ) && strtolower( trim( (string) $wpis['nadawca'] ) ) === $email;
}

/**
 * Eksporter: wszystkie maile i ruchy, których nadawcą jest podany adres.
 *
 * @param string $email  Adres z żądania.
 * @param int    $strona Strona (WP woła wielokrotnie, aż `done` = true).
 * @return array{data: array, done: bool}
 */
function segm_rodo_eksporter( $email, $strona = 1 ) {
	global $wpdb;
	$email  = strtolower( trim( (string) $email ) );
	$strona = max( 1, (int) $strona );
	$od     = ( $strona - 1 ) * SEGM_RODO_STRONA;
	$dane   = array();

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własne tabele wtyczki, nazwy z prefiksu WP.
	$maile = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, zrodlo, temat, tresc, kategoria_oczekiwana, utworzono FROM {$wpdb->prefix}segm_maile WHERE LOWER(nadawca) = %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$email,
			SEGM_RODO_STRONA,
			$od
		)
	);
	foreach ( (array) $maile as $m ) {
		$dane[] = array(
			'group_id'    => 'segm-maile',
			'group_label' => 'Segregator maila — wiadomości',
			'item_id'     => 'segm-mail-' . (int) $m->id,
			'data'        => array(
				array(
					'name'  => 'Nadawca',
					'value' => $email,
				),
				array(
					'name'  => 'Temat',
					'value' => (string) $m->temat,
				),
				array(
					'name'  => 'Treść',
					'value' => (string) $m->tresc,
				),
				array(
					'name'  => 'Źródło',
					'value' => (string) $m->zrodlo,
				),
				array(
					'name'  => 'Kategoria',
					'value' => (string) $m->kategoria_oczekiwana,
				),
				array(
					'name'  => 'Zapisano',
					'value' => (string) $m->utworzono,
				),
			),
		);
	}

	$ruchy = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, temat, folder_z, folder_do, stan, utworzono FROM {$wpdb->prefix}segm_skrz_ruchy WHERE LOWER(nadawca) = %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$email,
			SEGM_RODO_STRONA,
			$od
		)
	);
	// phpcs:enable
	foreach ( (array) $ruchy as $r ) {
		$dane[] = array(
			'group_id'    => 'segm-ruchy',
			'group_label' => 'Segregator maila — ruchy w skrzynce',
			'item_id'     => 'segm-ruch-' . (int) $r->id,
			'data'        => array(
				array(
					'name'  => 'Nadawca',
					'value' => $email,
				),
				array(
					'name'  => 'Temat',
					'value' => (string) $r->temat,
				),
				array(
					'name'  => 'Z folderu',
					'value' => (string) $r->folder_z,
				),
				array(
					'name'  => 'Do folderu',
					'value' => (string) $r->folder_do,
				),
				array(
					'name'  => 'Stan',
					'value' => (string) $r->stan,
				),
				array(
					'name'  => 'Zapisano',
					'value' => (string) $r->utworzono,
				),
			),
		);
	}

	$przebiegi = segm_rodo_przebiegi_osoby( $email, SEGM_RODO_STRONA, $od );
	foreach ( $przebiegi as $p ) {
		$wyniki = json_decode( (string) $p->wyniki, true );
		if ( ! is_array( $wyniki ) ) {
			continue;
		}
		foreach ( $wyniki as $n => $w ) {
			if ( ! segm_rodo_wpis_osoby( $w, $email ) ) {
				continue;
			}
			$dane[] = array(
				'group_id'    => 'segm-przebiegi',
				'group_label' => 'Segregator maila — raporty przebiegów (próbka)',
				'item_id'     => 'segm-przebieg-' . (int) $p->id . '-' . (int) $n,
				'data'        => array(
					array(
						'name'  => 'Nadawca',
						'value' => $email,
					),
					array(
						'name'  => 'Temat',
						'value' => isset( $w['temat'] ) ? (string) $w['temat'] : '',
					),
					array(
						'name'  => 'Nadana kategoria',
						'value' => isset( $w['kategoria'] ) ? (string) $w['kategoria'] : '',
					),
					array(
						'name'  => 'Oczekiwana kategoria',
						'value' => isset( $w['oczekiwana'] ) ? (string) $w['oczekiwana'] : '',
					),
					array(
						'name'  => 'Przebieg (tryb, data)',
						'value' => (string) $p->tryb . ', ' . (string) $p->uruchomiono,
					),
				),
			);
		}
	}

	$koniec = ( count( (array) $maile ) < SEGM_RODO_STRONA ) && ( count( (array) $ruchy ) < SEGM_RODO_STRONA ) && ( count( $przebiegi ) < SEGM_RODO_STRONA );
	return array(
		'data' => $dane,
		'done' => $koniec,
	);
}

/**
 * Kasownik: maile tej osoby znikają, w ruchach i w raportach przebiegów zostaje ślad bez nadawcy i tematu.
 * Liczby z pomiaru skutku (`rows_affected`), błąd bazy = `done` = false z komunikatem,
 * żeby WordPress nie zgłosił właścicielowi „usunięto”, gdy nic nie usunął.
 *
 * @param string $email  Adres z żądania.
 * @param int    $strona Strona (jeden przebieg kasuje wszystko, więc zawsze `done`).
 * @return array{items_removed: int, items_retained: int, messages: array, done: bool}
 */
function segm_rodo_kasownik( $email, $strona = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- sygnatura filtra WP.
	global $wpdb;
	$email      = strtolower( trim( (string) $email ) );
	$usuniete   = 0;
	$zostawione = 0;
	$komunikaty = array();

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własne tabele wtyczki, nazwy z prefiksu WP.
	$w1 = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_maile WHERE LOWER(nadawca) = %s", $email ) );
	if ( false === $w1 ) {
		$komunikaty[] = 'Segregator maila: nie udało się usunąć wiadomości (błąd bazy: ' . (string) $wpdb->last_error . ').';
	} else {
		$usuniete += (int) $w1;
	}

	$w2 = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}segm_skrz_ruchy SET nadawca = '', temat = '[usunięto na żądanie]' WHERE LOWER(nadawca) = %s",
			$email
		)
	);
	// phpcs:enable
	if ( false === $w2 ) {
		$komunikaty[] = 'Segregator maila: nie udało się wyczyścić nadawcy w dzienniku ruchów (błąd bazy: ' . (string) $wpdb->last_error . ').';
	} else {
		$zostawione += (int) $w2; // ślad ruchu zostaje, dane osoby z niego zniknęły
		if ( (int) $w2 > 0 ) {
			$komunikaty[] = 'Segregator maila: w dzienniku ruchów zostały wpisy bez nadawcy i tematu (dowód, co narzędzie zrobiło w skrzynce).';
		}
	}

	// Raporty przebiegów: JSON `wyniki` przepisujemy bez nadawcy i tematu tej osoby, reszta wpisu
	// (kategoria, trafienie) zostaje — raport ma dalej dowodzić, jak reguły zadziałały. Wszystkie
	// wiersze naraz (raport = jedno uruchomienie, jest ich tyle, ile uruchomień, nie maili).
	$w3 = true;
	foreach ( segm_rodo_przebiegi_osoby( $email, 10000, 0 ) as $p ) {
		$wyniki = json_decode( (string) $p->wyniki, true );
		if ( ! is_array( $wyniki ) ) {
			continue;
		}
		$zmienione = 0;
		foreach ( $wyniki as $n => $w ) {
			if ( segm_rodo_wpis_osoby( $w, $email ) ) {
				$wyniki[ $n ]['nadawca'] = '';
				$wyniki[ $n ]['temat']   = '[usunięto na żądanie]';
				++$zmienione;
			}
		}
		if ( 0 === $zmienione ) {
			continue;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki.
		$wyn = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}segm_przebiegi SET wyniki = %s WHERE id = %d",
				wp_json_encode( $wyniki ),
				(int) $p->id
			)
		);
		// phpcs:enable
		if ( false === $wyn ) {
			$w3           = false;
			$komunikaty[] = 'Segregator maila: nie udało się wyczyścić nadawcy w raporcie przebiegu #' . (int) $p->id . ' (błąd bazy: ' . (string) $wpdb->last_error . ').';
			continue;
		}
		$zostawione += $zmienione; // wpisy raportu zostają, dane osoby z nich zniknęły
	}
	if ( $w3 && $zostawione > ( false === $w2 ? 0 : (int) $w2 ) ) {
		$komunikaty[] = 'Segregator maila: w raportach przebiegów zostały wpisy bez nadawcy i tematu (dowód, jak zadziałały reguły).';
	}

	return array(
		'items_removed'  => $usuniete,
		'items_retained' => $zostawione,
		'messages'       => $komunikaty,
		'done'           => ( false !== $w1 && false !== $w2 && $w3 ),
	);
}

/**
 * Rejestracja w narzędziach prywatności WP.
 *
 * @param array $eksportery Zarejestrowane eksportery.
 * @return array
 */
function segm_rejestruj_eksporter( $eksportery ) {
	$eksportery['segregator-maila'] = array(
		'exporter_friendly_name' => 'Segregator maila',
		'callback'               => 'segm_rodo_eksporter',
	);
	return $eksportery;
}
add_filter( 'wp_privacy_personal_data_exporters', 'segm_rejestruj_eksporter' );

/**
 * @param array $kasowniki Zarejestrowane kasowniki.
 * @return array
 */
function segm_rejestruj_kasownik( $kasowniki ) {
	$kasowniki['segregator-maila'] = array(
		'eraser_friendly_name' => 'Segregator maila',
		'callback'             => 'segm_rodo_kasownik',
	);
	return $kasowniki;
}
add_filter( 'wp_privacy_personal_data_erasers', 'segm_rejestruj_kasownik' );

/**
 * Podpowiedź do Polityki prywatności (Ustawienia → Prywatność → „Przewodnik”).
 */
function segm_rodo_podpowiedz_polityki() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$tresc = '<p>Wtyczka Segregator maila przetwarza wiadomości e-mail ze skrzynki właściciela strony: adres nadawcy, temat i treść. '
		. 'Dane te służą wyłącznie do posegregowania poczty według reguł ustawionych przez właściciela. '
		. 'Jeśli włączono tryb AI, temat i treść wiadomości są przekazywane do dostawcy modelu językowego (Anthropic, serwery w USA) w celu zaproponowania kategorii; '
		. 'tryb AI można wyłączyć w ustawieniach wtyczki. '
		. 'Dziennik przebiegów zapisuje nadawcę i temat przeniesionych wiadomości. '
		. 'Osoba, której dane dotyczą, może zażądać ich wydania lub usunięcia — wtyczka obsługuje narzędzia WordPressa do eksportu i usuwania danych osobowych.</p>';
	wp_add_privacy_policy_content( 'Segregator maila', wp_kses_post( $tresc ) );
}
add_action( 'admin_init', 'segm_rodo_podpowiedz_polityki' );
