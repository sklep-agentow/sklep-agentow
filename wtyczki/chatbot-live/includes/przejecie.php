<?php
/**
 * Przejęcie rozmowy przez człowieka: stan rozmowy, uciszenie bota, odpytywanie.
 *
 * Model stanu wzięty z Chatwoota:
 * JEDNO pole na rozmowie decyduje, kto odpowiada — `bot` albo `czlowiek` — zamiast
 * dwóch niezależnych przełączników („bot wyłączony" + „operator przypisany"), które
 * prędzej czy później się rozjadą i zostawią rozmowę bez żadnej ze stron.
 *
 * @package chatbot-live
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ile sekund ciszy operatora oddaje rozmowę botowi.
 *
 * Pięć minut, bo tyle mniej więcej trwa odejście od biurka po kawę, a dłuższa cisza
 * to dla gościa martwy czat: pisze do człowieka, którego już nie ma, i nie dostaje
 * nawet odpowiedzi z reguł, którą bot dałby od ręki. Powrót JEST komunikowany
 * gościowi — cicha zmiana rozmówcy byłaby wobec niego nieuczciwa.
 */
const CHATL_PRZEJECIE_CISZA = 300;

/*
 * Rytm odpytywania w sekundach. Liczby wprost z pomiaru (opis PR): odpytywanie
 * co sekundę przy 100 gościach to 6000 żądań/min i 2,5 zajętego procesu PHP,
 * czyli więcej, niż typowy pakiet współdzielony w ogóle ma.
 *
 * Interwał podaje SERWER przy każdej odpowiedzi, a nie przeglądarka — dzięki temu
 * zmiana rytmu nie wymaga wysyłania klientom nowego pliku JS.
 */
const CHATL_ODPYT_BOT      = 15;
const CHATL_ODPYT_CZLOWIEK = 5;
const CHATL_ODPYT_UKRYTA   = 30;
const CHATL_ODPYT_CISZA    = 120;

/** Po tylu sekundach bez słowa operatora zwalniamy odpytywanie (rozmowa przygasła). */
const CHATL_PRZEJECIE_SPOWOLNIENIE = 120;

/** Najwięcej odpytań na minutę z JEDNEJ sesji — sufit na wypadek zepsutego klienta. */
const CHATL_LIMIT_ODPYT = 40;

/**
 * Nowy identyfikator sesji — wydawany przez SERWER.
 *
 * Wcześniej wymyślała go przeglądarka, ze ścieżką zapasową opartą o `Date.now()`
 * i `Math.random()`. Dopóki sesja tylko sklejała własną rozmowę, koszt zgadnięcia
 * był niski. Od chwili, gdy publiczny punkt odpytywania na jej podstawie WYDAJE
 * treść rozmowy, zgadnięty identyfikator to podsłuch cudzej rozmowy — a
 * `Math.random()` nie jest generatorem kryptograficznym i nigdy nie miał nim być.
 *
 * `wp_generate_password( 32, false )` to 32 znaki z alfanumerycznego zakresu
 * (>180 bitów), rysowane z tego samego źródła co hasła WordPressa.
 *
 * @return string Identyfikator sesji.
 */
function chatl_nowa_sesja() {
	global $wpdb;
	$sesja  = wp_generate_password( 32, false );
	$sekret = wp_generate_password( 32, false );

	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'chatl_sesje',
		array(
			'sesja'      => $sesja,
			'stan'       => 'bot',
			'klucz_hash' => wp_hash( $sekret ),
			'utworzono'  => current_time( 'mysql' ),
			'aktywnosc'  => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);

	chatl_ustaw_ciastko_sesji( $sesja, $sekret );
	return $sesja;
}

/**
 * Ciastko z sekretem sesji — DRUGI składnik dostępu do rozmowy.
 *
 * Sam identyfikator sesji nie wystarczy, żeby przeczytać rozmowę: trzeba mieć
 * jeszcze sekret, który wychodzi z serwera raz i **tylko** jako ciastko
 * `HttpOnly`. Skutki:
 *
 * - identyfikator, który wycieknie (log serwera, zrzut ekranu, cudza wtyczka
 *   analityczna czytająca `sessionStorage`), **nie otwiera** cudzej rozmowy;
 * - `HttpOnly` znaczy, że żaden skrypt na stronie — także cudzy, wstrzyknięty —
 *   nie odczyta sekretu z JavaScriptu;
 * - w bazie leży wyłącznie `wp_hash()` sekretu, więc podejrzenie tabeli
 *   (kopia zapasowa, phpMyAdmin) też nie daje wstępu do rozmów.
 *
 * Nazwa ciastka zawiera identyfikator sesji, żeby gość z KILKOMA otwartymi
 * kartami miał osobny sekret na kartę — jedno wspólne ciastko unieważniałoby
 * starszą kartę przy otwarciu nowej.
 *
 * @param string $sesja  Sesja.
 * @param string $sekret Sekret.
 * @return void
 */
function chatl_ustaw_ciastko_sesji( $sesja, $sekret ) {
	if ( headers_sent() ) {
		return;
	}
	setcookie(
		'chatl_k_' . $sesja,
		$sekret,
		array(
			'expires'  => 0, // Ciastko sesyjne: ginie razem z przeglądarką, jak reszta rozmowy.
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/**
 * Czy żądanie ma prawo czytać TĘ rozmowę.
 *
 * @param string     $sesja Sesja.
 * @param array|null $w     Wiersz sesji albo null.
 * @return bool
 */
function chatl_sesja_uprawniona( $sesja, $w ) {
	if ( ! is_array( $w ) ) {
		return false;
	}
	/*
	 * ZAMKNIĘTE DOMYŚLNIE. Wiersz bez sekretu nie jest „sesją zaufaną" — jest
	 * sesją, której serwer nigdy nie wydał, więc nie ma czego otwierać.
	 *
	 * Poprzednia wersja tej gałęzi zwracała tu `true` („stare sesje przepuszczamy").
	 * Dziś nie dało się tego wykorzystać, bo jedyna ścieżka zakładania wiersza
	 * ustawia sekret — ale to jest mina, nie ustępstwo: wystarczyłoby, żeby ktoś
	 * kiedyś dołożył drugie miejsce wstawiania wiersza (choćby uzupełnianie starych
	 * sesji), a dziura otworzyłaby się po cichu, w funkcji, która ma AUTORYZOWAĆ.
	 * Strażnik pilnuje osobno, że w tabeli nie ma wierszy bez sekretu.
	 */
	if ( empty( $w['klucz_hash'] ) ) {
		return false;
	}
	$nazwa = 'chatl_k_' . $sesja;
	if ( empty( $_COOKIE[ $nazwa ] ) ) {
		return false;
	}
	$sekret = sanitize_text_field( wp_unslash( $_COOKIE[ $nazwa ] ) );
	return hash_equals( (string) $w['klucz_hash'], wp_hash( $sekret ) );
}

/**
 * Wiersz stanu rozmowy albo null.
 *
 * @param string $sesja Identyfikator sesji (już zwalidowany).
 * @return array|null
 */
function chatl_sesja_wiersz( $sesja ) {
	global $wpdb;
	if ( '' === (string) $sesja ) {
		return null;
	}
	$t = $wpdb->prefix . 'chatl_sesje';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$w = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE sesja = %s", $sesja ), ARRAY_A );
	// phpcs:enable
	return is_array( $w ) ? $w : null;
}

/**
 * Zapis wiadomości systemowej — tej, którą gość CZYTA w oknie rozmowy.
 *
 * Rola `system` jest osobna od `bot` celowo: statystyki i raport luk liczą
 * wyłącznie `gosc` i `bot`, więc komunikat o przejęciu nie zafałszuje ani
 * skuteczności bota, ani listy luk.
 *
 * @param string $sesja Sesja.
 * @param string $tresc Treść komunikatu.
 * @return int Id wiersza.
 */
function chatl_komunikat_systemowy( $sesja, $tresc ) {
	return chatl_zapisz_wymiane( $sesja, 'system', $tresc, 'przejecie' );
}

/**
 * Czy operator zamilkł na tyle długo, że rozmowa wraca do bota.
 *
 * @param array|null $w Wiersz sesji albo null dla nieznanej sesji.
 * @return bool
 */
function chatl_przejecie_wygaslo( $w ) {
	if ( ! is_array( $w ) || 'czlowiek' !== $w['stan'] ) {
		return false;
	}
	$ostatnia = ! empty( $w['operator_akt'] ) ? $w['operator_akt'] : $w['przejeto'];
	if ( empty( $ostatnia ) ) {
		return false;
	}
	return ( current_time( 'timestamp' ) - strtotime( $ostatnia ) ) > CHATL_PRZEJECIE_CISZA; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
}

/**
 * Oddanie rozmowy botowi (ręcznie przyciskiem albo po ciszy operatora).
 *
 * @param string $sesja      Sesja.
 * @param string $komunikat  Co ma zobaczyć gość.
 * @return void
 */
function chatl_oddaj_botowi( $sesja, $komunikat ) {
	global $wpdb;
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'chatl_sesje',
		array(
			'stan'         => 'bot',
			'operator'     => null,
			'operator_akt' => null,
			'przejeto'     => null,
		),
		array( 'sesja' => $sesja ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%s' )
	);
	if ( '' !== $komunikat ) {
		chatl_komunikat_systemowy( $sesja, $komunikat );
	}
}

/**
 * Przejęcie rozmowy przez operatora.
 *
 * Uprawnienia sprawdza WYWOŁUJĄCY (punkt panelu), bo ta funkcja jest też używana
 * przez strażnika — ale wywołanie z panelu bez `manage_options` nie ma jak tu dojść.
 *
 * @param string $sesja Sesja.
 * @param int    $kto   Id użytkownika WordPressa.
 * @return bool Czy przejęcie doszło do skutku.
 */
function chatl_przejmij( $sesja, $kto ) {
	global $wpdb;
	if ( '' === (string) $sesja ) {
		return false;
	}
	chatl_sesja_aktywnosc( $sesja ); // Sesja mogła jeszcze nie mieć wiersza.
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'chatl_sesje',
		array(
			'stan'         => 'czlowiek',
			'operator'     => (int) $kto,
			'przejeto'     => current_time( 'mysql' ),
			'operator_akt' => current_time( 'mysql' ),
		),
		array( 'sesja' => $sesja ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%s' )
	);
	// Gość dowiaduje się o zmianie rozmówcy WPROST, a nie po zmianie awatara.
	chatl_komunikat_systemowy( $sesja, 'Do rozmowy dołączył konsultant — od tej chwili odpowiada człowiek, nie bot.' );
	return true;
}

/**
 * Wiadomość operatora do gościa.
 *
 * Odświeża też znacznik aktywności, bo to on decyduje, kiedy cisza operatora
 * odda rozmowę botowi — pisanie JEST aktywnością.
 *
 * @param string $sesja Sesja.
 * @param string $tresc Treść.
 * @return int Id wiersza.
 */
function chatl_operator_wiadomosc( $sesja, $tresc ) {
	global $wpdb;
	$id = chatl_zapisz_wymiane( $sesja, 'operator', $tresc, 'czlowiek' );
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'chatl_sesje',
		array( 'operator_akt' => current_time( 'mysql' ) ),
		array( 'sesja' => $sesja ),
		array( '%s' ),
		array( '%s' )
	);
	return $id;
}

/**
 * Czy TO żądanie ma prawo pisać w tej rozmowie.
 *
 * Decyzja świadoma, nie przypadek:
 * sekretu wymagamy także przy ZAPISIE, nie tylko przy odczycie. Bez tego ktoś, kto
 * zdobył sam identyfikator sesji, mógłby **podszyć się pod gościa** i wstrzyknąć
 * operatorowi wiadomość — a operator odpowiada człowiekowi, którego nie zweryfikuje
 * inaczej niż treścią rozmowy. Ryzyko jest małe (identyfikator jest nie do
 * zgadnięcia), ale skutek — wprowadzenie w błąd człowieka po drugiej stronie —
 * jest gorszy niż koszt zamknięcia tej ścieżki.
 *
 * Cena, którą płacimy świadomie: przeglądarka z zablokowanymi ciastkami
 * pierwszej strony nie utrzyma sesji. Widget wykrywa to po odpowiedzi `kod: sesja`,
 * prosi o nową sesję i ponawia raz — jeśli i to nie przejdzie, gość dostaje
 * jasny komunikat zamiast czatu, który „po prostu nie działa".
 *
 * @param string $sesja Sesja.
 * @return bool
 */
function chatl_zapis_dozwolony( $sesja ) {
	$w = chatl_sesja_wiersz( $sesja );
	if ( null === $w ) {
		return false;
	}
	return chatl_sesja_uprawniona( $sesja, $w );
}

/**
 * Stan rozmowy Z UWZGLĘDNIENIEM wygaśnięcia przejęcia.
 *
 * Każde wejście do rozmowy (pytanie gościa, odpytywanie) przechodzi tędy, więc
 * powrót do bota po ciszy operatora dzieje się bez crona — a to znaczy, że działa
 * także na witrynie, na której WP-Cron jest wyłączony (częste u hostingów, które
 * podmieniają go własnym zadaniem systemowym).
 *
 * @param string $sesja Sesja.
 * @return string 'bot' albo 'czlowiek'.
 */
function chatl_stan_rozmowy( $sesja ) {
	$w = chatl_sesja_wiersz( $sesja );
	if ( null === $w ) {
		return 'bot';
	}
	if ( chatl_przejecie_wygaslo( $w ) ) {
		chatl_oddaj_botowi(
			$sesja,
			'Konsultant nie odpowiada — z rozmową znów pomaga bot. Napisz pytanie jeszcze raz.'
		);
		return 'bot';
	}
	return 'czlowiek' === $w['stan'] ? 'czlowiek' : 'bot';
}

/**
 * Czy bot ma milczeć w tej rozmowie.
 *
 * @param string $sesja Sesja.
 * @return bool
 */
function chatl_bot_milczy( $sesja ) {
	return 'czlowiek' === chatl_stan_rozmowy( $sesja );
}

/**
 * Odnotowanie aktywności gościa — do listy rozmów operatora („ile czeka").
 *
 * @param string $sesja Sesja.
 * @return void
 */
function chatl_sesja_aktywnosc( $sesja ) {
	global $wpdb;
	$t = $wpdb->prefix . 'chatl_sesje';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	/*
	 * Wyłącznie UPDATE. Wcześniej stało tu INSERT ... ON DUPLICATE KEY, czyli
	 * DRUGIE miejsce zakładania sesji — a zakładało wiersz BEZ sekretu, więc
	 * dokładnie ten przypadek, który wyżej musi być zamknięty. Sesje zakłada
	 * tylko chatl_nowa_sesja(), razem z sekretem. Jedno miejsce, jedna reguła.
	 */
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$t} SET aktywnosc = %s WHERE sesja = %s",
			current_time( 'mysql' ),
			$sesja
		)
	);
	// phpcs:enable
}

/**
 * Interwał odpytywania, który serwer podpowiada przeglądarce.
 *
 * @param string $stan          Stan rozmowy.
 * @param array|null $w         Wiersz sesji albo null.
 * @param bool   $karta_ukryta  Czy karta gościa jest schowana.
 * @return int Sekundy.
 */
function chatl_interwal_odpytywania( $stan, $w, $karta_ukryta ) {
	if ( 'czlowiek' !== $stan ) {
		return CHATL_ODPYT_BOT;
	}
	if ( $karta_ukryta ) {
		return CHATL_ODPYT_UKRYTA;
	}
	$ostatnia = is_array( $w ) && ! empty( $w['operator_akt'] ) ? $w['operator_akt'] : '';
	if ( '' !== $ostatnia
		&& ( current_time( 'timestamp' ) - strtotime( $ostatnia ) ) > CHATL_PRZEJECIE_SPOWOLNIENIE ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		return CHATL_ODPYT_CISZA;
	}
	return CHATL_ODPYT_CZLOWIEK;
}

/**
 * Publiczny punkt odpytywania: co nowego w MOJEJ rozmowie.
 *
 * Trzy rzeczy, na których stoi bezpieczeństwo tego punktu:
 *
 * 1. Zapytanie jest ZAWSZE zawężone `WHERE sesja = %s` przez `prepare()`. Nie ma tu
 *    ścieżki, która czyta rozmowy „wszystkie" albo „po fragmencie" — obcy
 *    identyfikator zwraca pustą listę, a nie cudzą rozmowę.
 * 2. Identyfikator jest sprawdzany PRZED jakimkolwiek odczytem
 *    (`chatl_waliduj_sesje()`), więc do zapytania nie trafi nic o innym kształcie.
 * 3. Metoda to POST, nie GET — token sesji jedzie w ciele żądania, a nie w adresie.
 *    W adresie wylądowałby w logach serwera i w nagłówku Referer, czyli sekret
 *    wyciekałby tam, gdzie nikt go nie szuka.
 *
 * @param WP_REST_Request $req Żądanie.
 * @return WP_REST_Response
 */
function chatl_rest_rozmowa( $req ) {
	global $wpdb;

	$sesja = chatl_waliduj_sesje( (string) $req->get_param( 'sesja' ) );
	if ( '' === $sesja ) {
		return new WP_REST_Response( array( 'kod' => 'zla_sesja' ), 400 );
	}

	// Sufit liczony NA SESJĘ, nie na adres IP: w biurze albo za proxy operatora
	// wszyscy goście mają jeden adres, więc limit per IP karałby ich nawzajem.
	if ( ! chatl_rate_limit( 'odpyt', CHATL_LIMIT_ODPYT, MINUTE_IN_SECONDS, 'sesja:' . $sesja ) ) {
		return new WP_REST_Response( array( 'kod' => 'za_czesto' ), 429 );
	}

	$od_id  = max( 0, (int) $req->get_param( 'od_id' ) );
	$ukryta = (bool) $req->get_param( 'ukryta' );

	$w = chatl_sesja_wiersz( $sesja );

	/*
	 * DRUGI SKŁADNIK. Sam identyfikator sesji nie otwiera rozmowy — trzeba mieć
	 * jeszcze sekret z ciastka HttpOnly, wydany przy zakładaniu tej sesji.
	 * Odpowiedź na brak sekretu jest CELOWO taka sama jak na nieznaną sesję
	 * (pusta rozmowa w trybie bota): kto zgaduje, nie dowie się nawet tego,
	 * czy trafił w istniejącą sesję.
	 */
	if ( null !== $w && ! chatl_sesja_uprawniona( $sesja, $w ) ) {
		return new WP_REST_Response(
			array(
				'stan'     => 'bot',
				'nowe'     => array(),
				'interwal' => CHATL_ODPYT_BOT,
			),
			200
		);
	}

	$stan = chatl_stan_rozmowy( $sesja );

	$t = $wpdb->prefix . 'chatl_rozmowy';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			/*
			 * Tylko `operator` i `system`. Odpowiedzi bota gość dostaje
			 * synchronicznie, w odpowiedzi na własne pytanie — wydawanie ich
			 * jeszcze raz tędy dublowałoby wiadomości w oknie. Własne wiadomości
			 * gościa też odpadają: ma je u siebie od chwili wysłania.
			 */
			"SELECT id, rola, tresc, utworzono FROM {$t}
			WHERE sesja = %s AND id > %d AND rola IN ('operator','system')
			ORDER BY id ASC LIMIT 20",
			$sesja,
			$od_id
		),
		ARRAY_A
	);
	// phpcs:enable

	$nowe = array();
	foreach ( (array) $wiersze as $wiersz ) {
		$nowe[] = array(
			'id'    => (int) $wiersz['id'],
			'rola'  => $wiersz['rola'],
			'tresc' => (string) $wiersz['tresc'],
		);
	}

	return new WP_REST_Response(
		array(
			'stan'     => $stan,
			'nowe'     => $nowe,
			'interwal' => chatl_interwal_odpytywania( $stan, $w, $ukryta ),
		),
		200
	);
}

/**
 * Rejestracja punktu odpytywania.
 *
 * `permission_callback` jest obowiązkowy od WP 5.5 — punkt jest publiczny
 * (gość nie jest zalogowany), więc `__return_true`, a CAŁA kontrola dostępu
 * siedzi w środku: walidacja sesji, zawężenie zapytania i limit żądań.
 *
 * @return void
 */
function chatl_rest_init() {
	register_rest_route(
		'chatl/v1',
		'/rozmowa',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'chatl_rest_rozmowa',
			'args'                => array(
				'sesja'  => array(
					'required'          => true,
					'validate_callback' => function ( $v ) {
						return '' !== chatl_waliduj_sesje( (string) $v );
					},
				),
				'od_id'  => array(
					'default'           => 0,
					'sanitize_callback' => 'absint',
				),
				'ukryta' => array(
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'chatl_rest_init' );

/**
 * AJAX: wydanie świeżej sesji gościowi, który jej jeszcze nie ma.
 *
 * Osobny punkt, a nie doklejenie do endpointu nonce'a: nonce jest krótkotrwałym
 * tokenem CSRF, a sesja jest kluczem do treści rozmowy. Mieszanie ich w jednej
 * odpowiedzi zaciera, które z nich wolno gdzie wysłać.
 *
 * @return void
 */
function chatl_ajax_sesja() {
	if ( ! chatl_rate_limit( 'sesja', CHATL_LIMIT_NA_MIN ) ) {
		wp_send_json_error( array( 'komunikat' => 'Za dużo żądań — odczekaj chwilę.' ), 429 );
	}
	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		wp_send_json_error( array( 'komunikat' => 'Czat jest wyłączony.' ), 403 );
	}
	wp_send_json_success( array( 'sesja' => chatl_nowa_sesja() ) );
}
add_action( 'wp_ajax_chatl_sesja', 'chatl_ajax_sesja' );
add_action( 'wp_ajax_nopriv_chatl_sesja', 'chatl_ajax_sesja' );
