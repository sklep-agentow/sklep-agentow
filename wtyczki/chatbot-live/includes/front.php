<?php
/**
 * Front: assety widgetu + endpointy AJAX (pytanie, zgłoszenie).
 * Czat = wejście NIEZAUFANE: sanitize na wejściu, escape na wyjściu, limity długości.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CHATL_MAX_PYTANIE   = 500;
const CHATL_MAX_WIADOMOSC = 2000;
const CHATL_LIMIT_NA_MIN  = 10;
const CHATL_RETENCJA_DNI  = 90;

/*
 * Osobny, DUZO nizszy limit wywolan AI z jednego IP na dobe.
 * Limit 10/min i dzienny limit AI mierza zle wymiary: pierwszy jest wspolny
 * dla pytan i zgloszen i liczy sie w minutach, drugi jest GLOBALNY dla calej
 * strony. Jeden gosc pytajacy 10x/min przez 10 minut miescil sie w obu,
 * a wypalal caly dzienny budzet AI wszystkich gosci. Po przekroczeniu tego
 * limitu bot dalej odpowiada — z regul, jak bez klucza (tryb regulowy
 * dziala bez zmian).
 */
const CHATL_AI_LIMIT_IP_NA_DOBE = 20;

/**
 * Rate limit per IP na tabeli — wspólny dla obu endpointów nopriv,
 * żeby gość z noncem nie zalewał
 * bazy, nie mail-bombił właściciela ani nie przepalał klucza API w trybie AI.
 *
 * Poprzednia wersja na transiencie (get/set) NIE była atomowa: równoległe
 * żądania gubiły inkrementacje i przepuszczały ponad próg (zmierzone 15/25
 * przy progu 10). Tu inkrementacja jest atomowa (ON DUPLICATE KEY UPDATE),
 * a odczyt zwraca WŁASNĄ wartość żądania (LAST_INSERT_ID, per połączenie) —
 * przy N równoległych żądaniach przechodzi DOKŁADNIE próg, nic więcej.
 *
 * @param string $akcja        Etykieta limitu ('chat' = wspólny kubeł widgetu,
 *                             'ai' = wywołania modelu).
 * @param int    $prog         Maks. żądań na okno z jednego IP (domyślnie
 *                             CHATL_LIMIT_NA_MIN — wywołanie bez progu nie
 *                             zostaje bez limitu).
 * @param int    $okno_sekund  Długość okna w sekundach (minuta albo doba).
 * @return bool True = wolno obsłużyć, false = limit wyczerpany.
 */
function chatl_rate_limit( $akcja, $prog = CHATL_LIMIT_NA_MIN, $okno_sekund = MINUTE_IN_SECONDS ) {
	global $wpdb;
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $ip ) {
		return false;
	}
	$okno_sekund = max( 1, (int) $okno_sekund );
	$t           = $wpdb->prefix . 'chatl_rate_limit';
	$okno        = (int) floor( time() / $okno_sekund );
	$klucz       = $akcja . '|' . $okno . '|' . md5( $ip );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$wynik = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$t} (klucz, licznik, okno_do) VALUES (%s, 1, %s)
		 ON DUPLICATE KEY UPDATE licznik = LAST_INSERT_ID(licznik + 1)",
			$klucz,
			gmdate( 'Y-m-d H:i:s', ( $okno + 2 ) * $okno_sekund )
		)
	);
	if ( 1 === $wynik ) {
		$ile = 1; // Świeży wiersz w tym oknie (INSERT zwraca 1).
	} elseif ( 2 === $wynik ) {
		$ile = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // Inkrement (ODKU zwraca 2).
	} else {
		$ile = 0;
	}
	// phpcs:enable

	if ( 0 === $ile ) {
		return true; // Awaria licznika — limiter chroni przed spamem, nie może zablokować czatu (nonce/walidacje są gdzie indziej).
	}
	return $ile <= $prog;
}

/**
 * Wspólny kubeł limitu dla pytań i zgłoszeń widgetu.
 */
function chatl_limit_ip_ok() {
	return chatl_rate_limit( 'chat', CHATL_LIMIT_NA_MIN );
}

/**
 * Kubeł wywołań AI: jeden adres IP na dobę.
 *
 * Wołany DOPIERO gdy tryb AI jest dostępny — inaczej licznik rósłby też
 * w trybie regułowym. Wyczerpanie limitu NIE jest błędem dla gościa:
 * pytanie leci do silnika regułowego, dokładnie jak przy braku klucza.
 *
 * @return bool True = wolno wywołać model.
 */
function chatl_limit_ai_ip_ok() {
	return chatl_rate_limit( 'ai', CHATL_AI_LIMIT_IP_NA_DOBE, DAY_IN_SECONDS );
}

/**
 * Assety tylko na froncie i tylko gdy bot włączony.
 */
function chatl_assety() {
	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		return;
	}

	wp_enqueue_style( 'chatl-widget', CHATL_URL . 'assets/widget.css', array(), CHATL_WERSJA );
	wp_enqueue_script( 'chatl-widget', CHATL_URL . 'assets/widget.js', array(), CHATL_WERSJA, true );
	wp_localize_script(
		'chatl-widget',
		'chatlDane',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'chatl_front' ),
			'powitanie'  => $ust['powitanie'],
			// Informacja o przetwarzaniu danych przy formularzu kontaktu:
			// co zbieramy, po co, jak długo. Adres polityki pusty => bez linku.
			'prywatnosc' => sprintf(
				'Zbieramy Twój adres e-mail i treść wiadomości wyłącznie po to, żeby odpowiedzieć na Twoje pytanie. Przechowujemy je przez %d dni, potem są kasowane.',
				CHATL_RETENCJA_DNI
			),
			'polityka'   => esc_url( $ust['adres_polityki'] ),
			'zgodaTekst' => 'Zapoznałem się z informacją o przetwarzaniu danych',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'chatl_assety' );

/**
 * Walidacja identyfikatora sesji z przeglądarki (UUID lub podobny token).
 */
function chatl_waliduj_sesje( $sesja ) {
	$sesja = (string) $sesja;
	if ( ! preg_match( '/^[a-zA-Z0-9-]{8,64}$/', $sesja ) ) {
		return '';
	}
	return $sesja;
}

/**
 * AJAX: gość zadaje pytanie → odpowiedź z bazy Q&A albo tekst awaryjny.
 */
function chatl_ajax_pytanie() {
	check_ajax_referer( 'chatl_front', 'nonce' );

	if ( ! chatl_limit_ip_ok() ) {
		wp_send_json_error( array( 'komunikat' => 'Za dużo wiadomości w krótkim czasie — odczekaj chwilę i spróbuj ponownie.' ), 429 );
	}

	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		wp_send_json_error( array( 'komunikat' => 'Czat jest wyłączony.' ), 403 );
	}

	$pytanie = isset( $_POST['pytanie'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pytanie'] ) ) : '';
	$sesja   = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';

	if ( '' === $pytanie || '' === $sesja ) {
		wp_send_json_error( array( 'komunikat' => 'Puste pytanie.' ), 400 );
	}
	if ( mb_strlen( $pytanie ) > CHATL_MAX_PYTANIE ) {
		$pytanie = mb_substr( $pytanie, 0, CHATL_MAX_PYTANIE );
	}

	// Id pytania trzymamy, bo wynik dopasowania znamy dopiero niżej —
	// a to WIERSZ PYTANIA niesie treść, z której powstaje raport luk.
	$id_pytania = chatl_zapisz_wymiane( $sesja, 'gosc', $pytanie );

	// HYBRYDA: najpierw AI (jest klucz + limit dzienny globalny i per IP
	// niewyczerpany); błąd/timeout/brak klucza => zjazd do trybu regułowego.
	if ( chatl_ai_dostepny() && chatl_limit_ai_ip_ok() ) {
		$ai = chatl_ai_odpowiedz( $pytanie );
		if ( null !== $ai ) {
			if ( CHATL_AI_BRAK === trim( $ai ) ) {
				// Próg pewności: model nie zgaduje => uczciwe "nie wiem" + zbiórka kontaktu.
				chatl_oznacz_trafienie( $id_pytania, false );
				chatl_zapisz_wymiane( $sesja, 'bot', $ust['tekst_awaryjny'], 'ai', false );
				wp_send_json_success(
					array(
						'trafiono'  => false,
						'odpowiedz' => $ust['tekst_awaryjny'],
					)
				);
			}
			$ai = sanitize_textarea_field( $ai );
			chatl_oznacz_trafienie( $id_pytania, true );
			chatl_zapisz_wymiane( $sesja, 'bot', $ai, 'ai', true );
			wp_send_json_success(
				array(
					'trafiono'  => true,
					'odpowiedz' => $ai,
				)
			);
		}
	}

	$wynik = chatl_dopasuj( $pytanie );
	if ( null === $wynik ) {
		chatl_oznacz_trafienie( $id_pytania, false );
		chatl_zapisz_wymiane( $sesja, 'bot', $ust['tekst_awaryjny'], 'reguly', false );
		wp_send_json_success(
			array(
				'trafiono'  => false,
				'odpowiedz' => $ust['tekst_awaryjny'],
			)
		);
	}

	chatl_oznacz_trafienie( $id_pytania, true );
	chatl_zapisz_wymiane( $sesja, 'bot', $wynik['odpowiedz'], 'reguly', true );
	wp_send_json_success(
		array(
			'trafiono'  => true,
			'odpowiedz' => $wynik['odpowiedz'],
		)
	);
}
add_action( 'wp_ajax_chatl_pytanie', 'chatl_ajax_pytanie' );
add_action( 'wp_ajax_nopriv_chatl_pytanie', 'chatl_ajax_pytanie' );

/**
 * AJAX: gość zostawia kontakt → zgłoszenie + (opcja) mail do właściciela.
 */
function chatl_ajax_zgloszenie() {
	check_ajax_referer( 'chatl_front', 'nonce' );

	if ( ! chatl_limit_ip_ok() ) {
		wp_send_json_error( array( 'komunikat' => 'Za dużo wiadomości w krótkim czasie — odczekaj chwilę i spróbuj ponownie.' ), 429 );
	}

	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		wp_send_json_error( array( 'komunikat' => 'Czat jest wyłączony.' ), 403 );
	}

	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$wiadomosc = isset( $_POST['wiadomosc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wiadomosc'] ) ) : '';
	$sesja     = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'komunikat' => 'Podaj poprawny adres e-mail.' ), 400 );
	}
	if ( '' === $wiadomosc ) {
		wp_send_json_error( array( 'komunikat' => 'Napisz, w czym pomóc.' ), 400 );
	}
	// Zgoda sprawdzana PO STRONIE SERWERA: checkbox w przeglądarce da się
	// ominąć (DevTools, żądanie wprost do admin-ajax), a bez zgody nie wolno
	// zapisać maila gościa. Zapis zgłoszenia JEST dowodem zgody — bez niej
	// żądanie nie dochodzi do INSERT-a.
	if ( empty( $_POST['zgoda'] ) ) {
		wp_send_json_error(
			array( 'komunikat' => 'Zaznacz proszę zgodę na przetwarzanie danych — bez niej nie możemy zapisać Twojej wiadomości.' ),
			400
		);
	}
	if ( mb_strlen( $wiadomosc ) > CHATL_MAX_WIADOMOSC ) {
		$wiadomosc = mb_substr( $wiadomosc, 0, CHATL_MAX_WIADOMOSC );
	}

	global $wpdb;
	// Zapis MUSI być sprawdzony: bez tego padnięty INSERT kończył się „Dziękujemy",
	// a wiadomość gościa przepadała bez śladu (cichy ubytek danych klienta).
	$zapisano = $wpdb->insert(
		$wpdb->prefix . 'chatl_zgloszenia',
		array(
			'email'     => $email,
			'wiadomosc' => $wiadomosc,
			'sesja'     => $sesja,
			'status'    => 'nowe',
			'utworzono' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $zapisano ) {
		chatl_log_bledu_bazy( 'chatl_ajax_zgloszenie', 'zgłoszenie gościa z czatu' );
		wp_send_json_error(
			array( 'komunikat' => 'Nie udało się zapisać wiadomości — przepraszamy. Spróbuj ponownie za chwilę albo napisz do nas mailem.' ),
			500
		);
	}

	if ( $ust['powiadomienia'] && is_email( $ust['mail_powiadomien'] ) ) {
		$wyslano = wp_mail(
			$ust['mail_powiadomien'],
			'ChatBot LIVE: nowe zgłoszenie z czatu',
			"Gość zostawił wiadomość w czacie na stronie.\n\n"
			. "E-mail gościa: {$email}\n\n"
			. "Wiadomość:\n{$wiadomosc}\n\n"
			. 'Lista zgłoszeń: ' . admin_url( 'admin.php?page=chatl-zgloszenia' )
		);
		// Gościowi nic nie mówimy — jego wiadomość JEST zapisana i widoczna w panelu.
		// Ale właściciel musi wiedzieć, że powiadomienia nie dochodzą, inaczej
		// czekałby na maile, których poczta hostingu nie wysyła (znacznik zdejmuje
		// pierwszy udany wysył).
		chatl_odnotuj_wysylke_powiadomienia( (bool) $wyslano, $ust['mail_powiadomien'] );
	}

	wp_send_json_success( array( 'komunikat' => 'Dziękujemy! Mamy Twoją wiadomość — odezwiemy się na podany adres.' ) );
}
add_action( 'wp_ajax_chatl_zgloszenie', 'chatl_ajax_zgloszenie' );
add_action( 'wp_ajax_nopriv_chatl_zgloszenie', 'chatl_ajax_zgloszenie' );

/**
 * Znacznik „ostatnie powiadomienie mailowe nie wyszło" dla panelu.
 *
 * wp_mail() zwraca false, gdy poczta hostingu odrzuci wysyłkę. Zgłoszenie jest
 * wtedy zapisane, ale właściciel nie dostaje sygnału — bez tego znacznika
 * dowiedziałby się dopiero, wchodząc na listę zgłoszeń z ciekawości.
 *
 * @param bool   $wyslano Wynik wp_mail().
 * @param string $adres   Adres, na który szło powiadomienie.
 * @return void
 */
function chatl_odnotuj_wysylke_powiadomienia( $wyslano, $adres ) {
	if ( $wyslano ) {
		delete_option( 'chatl_blad_maila' );
		return;
	}
	update_option(
		'chatl_blad_maila',
		array(
			'kiedy' => current_time( 'mysql' ),
			'adres' => (string) $adres,
		),
		false
	);
}
