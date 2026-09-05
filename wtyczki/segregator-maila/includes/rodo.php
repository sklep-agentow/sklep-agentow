<?php
/**
 * RODO — eksporter i kasownik danych osobowych dla narzędzi prywatności WordPressa
 * (Narzędzia → Eksport / Usuń dane osobowe) oraz podpowiedź do Polityki prywatności.
 *
 * Segregator trzyma cudzą korespondencję: nadawca, temat i treść (tabela `segm_maile`,
 * próbka/demo) oraz nadawca i temat ruchów w skrzynce (`segm_skrz_ruchy`, dziennik przebiegów).
 * Sklep i ChatBot LIVE rejestrują eksporter i kasownik od dawna — Segregator nie miał ich wcale,
 * więc na żądanie „wydaj / usuń moje dane” właściciel skrzynki nie miał czym odpowiedzieć.
 * Wzór: `chatbot-live/includes/rodo.php` (ta sama para filtrów, ten sam kształt odpowiedzi).
 *
 * Klucz osoby = adres e-mail nadawcy (porównanie bez rozróżniania wielkości liter).
 * Kasowanie: wiersze `segm_maile` znikają w całości (to treść korespondencji tej osoby);
 * w `segm_skrz_ruchy` zostaje sam ślad ruchu (folder z → do, stan) z wyczyszczonym nadawcą
 * i tematem — dziennik przebiegu ma dowodzić, CO segregator zrobił w skrzynce, nie KTO pisał.
 * Wynik mierzony po skutku (rows_affected), nie po samym wywołaniu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SEGM_RODO_STRONA = 100;

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
				array( 'name' => 'Nadawca', 'value' => $email ),
				array( 'name' => 'Temat', 'value' => (string) $m->temat ),
				array( 'name' => 'Treść', 'value' => (string) $m->tresc ),
				array( 'name' => 'Źródło', 'value' => (string) $m->zrodlo ),
				array( 'name' => 'Kategoria', 'value' => (string) $m->kategoria_oczekiwana ),
				array( 'name' => 'Zapisano', 'value' => (string) $m->utworzono ),
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
				array( 'name' => 'Nadawca', 'value' => $email ),
				array( 'name' => 'Temat', 'value' => (string) $r->temat ),
				array( 'name' => 'Z folderu', 'value' => (string) $r->folder_z ),
				array( 'name' => 'Do folderu', 'value' => (string) $r->folder_do ),
				array( 'name' => 'Stan', 'value' => (string) $r->stan ),
				array( 'name' => 'Zapisano', 'value' => (string) $r->utworzono ),
			),
		);
	}

	$koniec = ( count( (array) $maile ) < SEGM_RODO_STRONA ) && ( count( (array) $ruchy ) < SEGM_RODO_STRONA );
	return array(
		'data' => $dane,
		'done' => $koniec,
	);
}

/**
 * Kasownik: maile tej osoby znikają, w ruchach zostaje ślad bez nadawcy i tematu.
 * Liczby z pomiaru skutku (`rows_affected`), błąd bazy = `done` = false z komunikatem,
 * żeby WordPress nie zgłosił właścicielowi „usunięto”, gdy nic nie usunął.
 *
 * @param string $email  Adres z żądania.
 * @param int    $strona Strona (jeden przebieg kasuje wszystko, więc zawsze `done`).
 * @return array{items_removed: int, items_retained: int, messages: array, done: bool}
 */
function segm_rodo_kasownik( $email, $strona = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- sygnatura filtra WP.
	global $wpdb;
	$email     = strtolower( trim( (string) $email ) );
	$usuniete  = 0;
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

	return array(
		'items_removed'  => $usuniete,
		'items_retained' => $zostawione,
		'messages'       => $komunikaty,
		'done'           => ( false !== $w1 && false !== $w2 ),
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
