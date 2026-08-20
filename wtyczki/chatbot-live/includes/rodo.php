<?php
/**
 * Wpięcie w narzędzia RODO WordPressa: eksporter i kasownik danych osobowych.
 *
 * WordPress ma wbudowany kreator (Narzędzia → Eksport/Usuwanie danych osobowych),
 * który obsługuje wniosek osoby, weryfikację mailem, paczkę ZIP i dziennik żądań.
 * Wtyczka, która trzyma maile gości i treść ich rozmów, a do tego kreatora się nie
 * podłącza, zostawia właściciela z obowiązkiem i bez narzędzia — przy produkcie
 * sprzedawanym z sekcją o RODO to niespójność między deklaracją a wykonaniem.
 *
 * KLUCZEM JEST ADRES E-MAIL, i to wyznacza granicę tego, co da się wydać i skasować:
 * zgłoszenia mają e-mail wprost, a historia rozmów NIE — rozmowa jest wiązana
 * z osobą wyłącznie przez sesję, w której ta osoba zostawiła zgłoszenie. Rozmowa
 * kogoś, kto nigdy nie podał maila, jest anonimowa i nie ma jak jej przypisać do
 * wniosku. Mówimy o tym wprost w komunikacie kreatora, zamiast udawać komplet.
 *
 * @package chatbot-live
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ile wierszy na jedną stronę kreatora. Kreator woła nas w pętli, aż `done`. */
const CHATL_RODO_PORCJA = 100;

/**
 * Sesje powiązane z adresem e-mail.
 *
 * Wiązanie idzie przez zgłoszenia: to jedyne miejsce, w którym gość podał adres.
 *
 * @param string $email Adres.
 * @return array Lista identyfikatorów sesji.
 */
function chatl_rodo_sesje_maila( $email ) {
	global $wpdb;
	$t = $wpdb->prefix . 'chatl_zgloszenia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$sesje = $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT sesja FROM {$t} WHERE email = %s AND sesja <> ''", $email )
	);
	// phpcs:enable
	return array_values( array_filter( (array) $sesje ) );
}

/**
 * Eksporter: zgłoszenia i rozmowy powiązane z adresem.
 *
 * @param string $email Adres z wniosku.
 * @param int    $strona Numer strony (od 1).
 * @return array Format wymagany przez WordPressa: data + done.
 */
function chatl_rodo_eksporter( $email, $strona = 1 ) {
	global $wpdb;
	$strona = max( 1, (int) $strona );
	$offset = ( $strona - 1 ) * CHATL_RODO_PORCJA;
	$dane   = array();

	$tz = $wpdb->prefix . 'chatl_zgloszenia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$zgloszenia = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, email, wiadomosc, status, utworzono FROM {$tz} WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$email,
			CHATL_RODO_PORCJA,
			$offset
		),
		ARRAY_A
	);
	// phpcs:enable

	foreach ( (array) $zgloszenia as $z ) {
		$dane[] = array(
			'group_id'    => 'chatl_zgloszenia',
			'group_label' => 'Zgłoszenia z czatu',
			'item_id'     => 'chatl-zgloszenie-' . (int) $z['id'],
			'data'        => array(
				array(
					'name'  => 'Adres e-mail',
					'value' => $z['email'],
				),
				array(
					'name'  => 'Wiadomość',
					'value' => $z['wiadomosc'],
				),
				array(
					'name'  => 'Status',
					'value' => $z['status'],
				),
				array(
					'name'  => 'Data',
					'value' => $z['utworzono'],
				),
			),
		);
	}

	/*
	 * Rozmowy dokładamy dopiero wtedy, gdy zgłoszenia się skończyły. Kreator woła
	 * nas stronami i oczekuje, że każda strona jest skończona — mieszanie dwóch
	 * źródeł na jednej stronie kończy się gubieniem wierszy przy przewijaniu.
	 */
	$sesje  = chatl_rodo_sesje_maila( $email );
	$miejsc = CHATL_RODO_PORCJA - count( (array) $zgloszenia );
	if ( $miejsc > 0 && $sesje ) {
		/*
		 * Dwa źródła na wspólnej numeracji stron. Przesunięcie rozmów liczymy
		 * względem CAŁEJ liczby zgłoszeń, a nie względem bieżącej strony:
		 * przy dokładnie pełnej stronie zgłoszeń (100) naiwne `OFFSET $offset`
		 * przeskoczyłoby pierwsze sto wierszy rozmowy i wydało wniosek
		 * z dziurą — czyli najgorszy możliwy błąd w eksporcie danych osobowych,
		 * bo niewidoczny dla obu stron.
		 */
		$tz_count = $wpdb->prefix . 'chatl_zgloszenia';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
		$ile_zgloszen  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz_count} WHERE email = %s", $email ) );
		$offset_rozmow = max( 0, $offset - $ile_zgloszen );
		$tr            = $wpdb->prefix . 'chatl_rozmowy';
		$miejsca       = implode( ', ', array_fill( 0, count( $sesje ), '%s' ) );
		$rozmowy       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sesja, rola, tresc, utworzono FROM {$tr} WHERE sesja IN ({$miejsca}) ORDER BY id ASC LIMIT %d OFFSET %d",
				array_merge( $sesje, array( $miejsc, $offset_rozmow ) )
			),
			ARRAY_A
		);
		// phpcs:enable
		foreach ( (array) $rozmowy as $r ) {
			$dane[] = array(
				'group_id'    => 'chatl_rozmowy',
				'group_label' => 'Historia rozmowy z botem',
				'item_id'     => 'chatl-rozmowa-' . (int) $r['id'],
				'data'        => array(
					array(
						'name'  => 'Kto',
						'value' => 'gosc' === $r['rola'] ? 'Gość' : 'Bot',
					),
					array(
						'name'  => 'Treść',
						'value' => $r['tresc'],
					),
					array(
						'name'  => 'Data',
						'value' => $r['utworzono'],
					),
				),
			);
		}
	}

	return array(
		'data' => $dane,
		'done' => count( $dane ) < CHATL_RODO_PORCJA,
	);
}

/**
 * Kasownik: usuwa zgłoszenia i powiązane rozmowy.
 *
 * Kolejność ma znaczenie: NAJPIERW rozmowy, POTEM zgłoszenia. Zgłoszenie jest
 * jedynym łącznikiem między adresem a sesją — skasowane najpierw, zabrałoby ze
 * sobą wiedzę o tym, które rozmowy usunąć, i zostawiłoby je na zawsze.
 *
 * Argument `$strona` jest w sygnaturze, bo taki jest kontrakt kreatora, ale nie
 * jest uzywany: kasowanie idzie JEDNYM zapytaniem na tabele, wiec nie ma czego
 * dzielic na strony. Zwracamy `done` od razu i kreator nie wola nas ponownie.
 *
 * @param string $email  Adres z wniosku.
 * @param int    $strona Numer strony — wymagany przez kreator, nieuzywany (patrz wyzej).
 * @return array Format wymagany przez WordPressa.
 */
function chatl_rodo_kasownik( $email, $strona = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	global $wpdb;
	$usuniete   = 0;
	$komunikaty = array();

	$sesje = chatl_rodo_sesje_maila( $email );
	if ( $sesje ) {
		$tr      = $wpdb->prefix . 'chatl_rozmowy';
		$miejsca = implode( ', ', array_fill( 0, count( $sesje ), '%s' ) );
		/*
		 * Lista miejsc na sesje budowana jest z array_fill(), wiec phpcs nie ma
		 * jak policzyc podstawien i ostrzega na wyrost — wartosci ida przez
		 * prepare() co do jednej. Poprawnosc pilnuje straznik: sekcja D sprawdza,
		 * ze kasowanie tyka TYLKO wnioskodawce.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
		$ile = $wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja IN ({$miejsca})", $sesje ) );
		// phpcs:enable
		$usuniete += max( 0, (int) $ile );
	}

	$tz = $wpdb->prefix . 'chatl_zgloszenia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$ile_z = $wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email = %s", $email ) );
	// phpcs:enable
	$usuniete += max( 0, (int) $ile_z );

	/*
	 * Uczciwość wobec właściciela, który tym odpowiada osobie składającej wniosek:
	 * mówimy WPROST, czego nie dało się przypisać. Rozmowa gościa, który nigdy nie
	 * zostawił maila, nie ma z tym adresem żadnego związku w bazie — nie „została
	 * zachowana", tylko nie jest daną tej osoby, o ile nie da się jej powiązać.
	 */
	$komunikaty[] = 'Usunięto zgłoszenia z tym adresem oraz rozmowy z sesji, w których to zgłoszenie powstało. '
		. 'Rozmowy gości, którzy nigdy nie podali adresu e-mail, nie są powiązane z żadnym adresem w bazie — '
		. 'kasuje je automatycznie retencja po ' . (int) CHATL_RETENCJA_DNI . ' dniach.';

	return array(
		'items_removed'  => $usuniete > 0,
		'items_retained' => false,
		'messages'       => $komunikaty,
		'done'           => true,
	);
}

/**
 * Rejestracja eksportera w kreatorze WordPressa.
 *
 * @param array $eksportery Lista.
 * @return array
 */
function chatl_rejestruj_eksporter( $eksportery ) {
	$eksportery['chatbot-live'] = array(
		'exporter_friendly_name' => 'ChatBot LIVE — zgłoszenia i rozmowy',
		'callback'               => 'chatl_rodo_eksporter',
	);
	return $eksportery;
}
add_filter( 'wp_privacy_personal_data_exporters', 'chatl_rejestruj_eksporter' );

/**
 * Rejestracja kasownika w kreatorze WordPressa.
 *
 * @param array $kasowniki Lista.
 * @return array
 */
function chatl_rejestruj_kasownik( $kasowniki ) {
	$kasowniki['chatbot-live'] = array(
		'eraser_friendly_name' => 'ChatBot LIVE — zgłoszenia i rozmowy',
		'callback'             => 'chatl_rodo_kasownik',
	);
	return $kasowniki;
}
add_filter( 'wp_privacy_personal_data_erasers', 'chatl_rejestruj_kasownik' );

/**
 * Podpowiedź do polityki prywatności — też narzędzie WordPressa, nie nasze.
 *
 * Właściciel dostaje gotowy akapit do wklejenia zamiast pisać go sam ze słuchu.
 * Treść mówi to samo, co wtyczka robi naprawdę: co zbieramy, po co i jak długo.
 *
 * @return void
 */
function chatl_polityka_prywatnosci() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$tresc  = '<p>' . esc_html(
		sprintf(
			'Czat na stronie zapisuje treść rozmowy oraz — jeśli zostawisz zgłoszenie — Twój adres e-mail i treść wiadomości. Historia rozmów jest kasowana automatycznie po %d dniach. Zgłoszenia zostają do czasu ich usunięcia przez administratora.',
			(int) CHATL_RETENCJA_DNI
		)
	) . '</p>';
	$tresc .= '<p>' . esc_html( 'Jeśli włączony jest tryb AI, treść pytania wraz z opisem firmy i bazą pytań jest wysyłana do dostawcy modelu (Anthropic) w celu wygenerowania odpowiedzi.' ) . '</p>';
	$tresc .= '<p>' . esc_html( 'Możesz poprosić o kopię swoich danych albo o ich usunięcie — administrator obsłuży wniosek wbudowanymi narzędziami WordPressa (Narzędzia → Eksport/Usuwanie danych osobowych).' ) . '</p>';
	wp_add_privacy_policy_content( 'ChatBot LIVE', $tresc );
}
add_action( 'admin_init', 'chatl_polityka_prywatnosci' );
