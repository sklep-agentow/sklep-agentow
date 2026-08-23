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

/**
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
 * Nazwa ukrytego pola-pułapki.
 *
 * Celowo NIE brzmi jak „email", „imie" ani „telefon": menedżery haseł
 * i autouzupełnianie przeglądarki wypełniają pola po nazwie, a fałszywy alarm
 * u prawdziwego gościa jest tu droższy niż przepuszczony spam.
 */
const CHATL_PULAPKA_POLE = 'chatl_pole_dodatkowe';

/**
 * Ile sekund od WYDANIA SESJI żądanie uznajemy za zbyt szybkie na człowieka.
 *
 * Liczone po stronie serwera, z `utworzono` w tabeli sesji — nie ze znacznika
 * przysłanego przez przeglądarkę, bo taki znacznik skrypt po prostu podrabia.
 * Dwie sekundy to próg dobrany pod NAJSZYBSZEGO człowieka, nie pod średniego:
 * gość, który kliknie gotowe pytanie od razu po otwarciu okna, i tak potrzebuje
 * więcej — okno musi się rozwinąć, a sesja jest wydawana przed rysowaniem.
 */
const CHATL_PULAPKA_SEKUND = 2;

/**
 * Sufit ZŁAPANYCH zgłoszeń zapisywanych z jednego adresu w minucie.
 *
 * Złapane zgłoszenie ląduje w bazie (decyzja produktowa: nie oddajemy leada za
 * elegancję mechanizmu), więc sama pułapka nie może stać się drogą do zalania
 * tabeli. Powyżej tego sufitu zgłoszenie NIE jest zapisywane — ale odpowiedź
 * dla wysyłającego jest nadal identyczna, żeby skrypt się nie dowiedział.
 */
const CHATL_LIMIT_PULAPKA = 5;

/*
 * Limit odswiezania zabezpieczenia formularza z jednego adresu IP na minute.
 * Wyzszy niz wspolny kubel czatu, bo na jedno nieudane pytanie przypada jedno
 * odswiezenie i gosc nie moze wpasc w blokade w trakcie ratowania wlasnej
 * rozmowy. Endpoint jest tani, bo niczego nie zapisuje, ale ma byc pod
 * limitem, zeby nie stal sie darmowa maszynka do generowania tokenow.
 */
const CHATL_LIMIT_NONCE = 20;

/*
 * Ile dni panel pokazuje ostrzezenie o odrzuconych nonce'ach.
 * Znacznika NIE kasujemy przy udanym zadaniu, bo to byloby zapisanie do bazy
 * na sciezce KAZDEGO pytania goscia. Zamiast tego ostrzezenie samo cichnie,
 * gdy ostatnie odrzucenie jest starsze niz to okno.
 */
const CHATL_NONCE_OKNO_DNI = 7;

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
 * @param string $tozsamosc    Czyj kubeł: pusty = adres gościa, inaczej np. 'sesja:abc'.
 * @return bool True = wolno obsłużyć, false = limit wyczerpany.
 */
function chatl_rate_limit( $akcja, $prog = CHATL_LIMIT_NA_MIN, $okno_sekund = MINUTE_IN_SECONDS, $tozsamosc = '' ) {
	global $wpdb;
	// Pusta tozsamosc = kubel na adres goscia (tak dziala reszta limitow). Odpytywanie
	// podaje wlasna: w biurze i za proxy wszyscy goscie maja JEDEN adres, wiec limit
	// per IP karalby ich nawzajem — a przy przejeciu rozmowy odciety gosc przestaje
	// dostawac wiadomosci od czlowieka, z ktorym rozmawia.
	$ip = '' !== (string) $tozsamosc ? (string) $tozsamosc : chatl_ip_goscia();
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
 * Adres IP gościa — z uwzględnieniem proxy, ale TYLKO na wyraźną decyzję właściciela.
 *
 * Problem, który to naprawia: limit „na IP" liczony z samego REMOTE_ADDR mierzy
 * adres PROXY. Strona za Cloudflare albo za proxy hostingu widzi jeden adres dla
 * wszystkich gości — wtedy 10 wiadomości na minutę obowiązuje CAŁĄ WITRYNĘ,
 * a dobowy limit AI (20 na IP) staje się limitem 20 na całą stronę.
 *
 * Nagłówkom ufamy WYŁĄCZNIE po włączeniu przełącznika w ustawieniach i to jest
 * decyzja świadoma, nie oszczędność: `X-Forwarded-For` da się podrobić zwykłym
 * żądaniem, więc na stronie BEZ proxy zaufanie mu oznaczałoby, że każdy obchodzi
 * limit, podając za każdym razem inny adres. Domyślnie przełącznik jest WYŁĄCZONY.
 *
 * Kolejność (jak w opisie luki L2): CF-Connecting-IP, potem pierwszy adres
 * z X-Forwarded-For, potem REMOTE_ADDR.
 *
 * @return string Adres IP albo pusty string, gdy nie da się go ustalić.
 */
function chatl_ip_goscia() {
	$ust = chatl_ustawienia();
	$ip  = '';

	if ( ! empty( $ust['za_proxy'] ) ) {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- każdy odczyt niżej przechodzi przez filter_var( ..., FILTER_VALIDATE_IP ).
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// X-Forwarded-For to LISTA: „gość, proxy1, proxy2". Interesuje nas pierwszy.
			$lista = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( (string) $lista[0] );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		// Nagłówek z bzdurą (albo z adresem prywatnym podstawionym przez kogoś
		// „na próbę") NIE może wyłączyć limitu — wtedy schodzimy do REMOTE_ADDR.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '';
		}
	}

	if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	/**
	 * Ostatnie słowo dla integratora: hosting bywa za nietypowym nagłówkiem
	 * (True-Client-IP, X-Real-IP). Zwrócenie pustego stringa = „nie wiem".
	 *
	 * @param string $ip Ustalony adres.
	 */
	$ip = (string) apply_filters( 'chatl_ip_goscia', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Odnotowanie odrzuconego nonce'a — sygnał dla właściciela.
 *
 * Bez tego awaria jest CAŁKOWICIE cicha: strona z cache podaje gościom HTML
 * starszy niż życie nonce'a, każde pytanie kończy się odmową, a właściciel
 * widzi w panelu normalnie działający panel i pustą historię rozmów.
 * Wzorzec 1:1 z chatl_odnotuj_wysylke_powiadomienia().
 *
 * @return void
 */
function chatl_odnotuj_odrzucony_nonce() {
	$blad = get_option( 'chatl_blad_nonce' );
	$ile  = is_array( $blad ) && isset( $blad['ile'] ) ? (int) $blad['ile'] + 1 : 1;
	update_option(
		'chatl_blad_nonce',
		array(
			'ostatni' => current_time( 'mysql' ),
			'ile'     => $ile,
		),
		false
	);
}

/**
 * Sprawdzenie nonce'a, które NIE ubija żądania.
 *
 * check_ajax_referer() w domyślnej postaci kończy proces odpowiedzią „-1",
 * więc (a) nie ma gdzie odnotować awarii, (b) gość dostaje coś, co nie jest
 * JSON-em wtyczki. Tutaj sprawdzamy sami i odpowiadamy WŁASNYM błędem
 * z kodem `nonce` — po tym kodzie widget wie, że ma dociągnąć świeży nonce
 * i powtórzyć żądanie, zamiast straszyć gościa komunikatem o awarii.
 *
 * @return void Kończy żądanie, gdy nonce jest zły.
 */
function chatl_wymagaj_nonce() {
	if ( check_ajax_referer( 'chatl_front', 'nonce', false ) ) {
		return;
	}
	chatl_odnotuj_odrzucony_nonce();
	wp_send_json_error(
		array(
			'komunikat' => 'Sesja formularza wygasła — odświeżam i próbuję ponownie.',
			'kod'       => 'nonce',
		),
		403
	);
}

/**
 * AJAX: wydanie świeżego nonce'a gościowi, któremu strona przyszła z cache.
 *
 * Endpoint CELOWO nie wymaga nonce'a — jego jedynym zadaniem jest wydanie
 * nonce'a komuś, kto ma nieważny. Nie osłabia to niczego: ten sam token każdy
 * może odczytać, wchodząc na stronę, a `wp_create_nonce( 'chatl_front' )` jest
 * ważny WYŁĄCZNIE dla dwóch publicznych akcji czatu — nie otwiera niczego,
 * czego gość nie mógłby zrobić i tak. Ochroną jest osobny limit na IP.
 */
function chatl_ajax_nonce() {
	if ( ! chatl_rate_limit( 'nonce', CHATL_LIMIT_NONCE ) ) {
		wp_send_json_error( array( 'komunikat' => 'Za dużo żądań — odczekaj chwilę.' ), 429 );
	}
	$ust = chatl_ustawienia();
	if ( ! $ust['wlaczony'] ) {
		wp_send_json_error( array( 'komunikat' => 'Czat jest wyłączony.' ), 403 );
	}
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'chatl_front' ) ) );
}
add_action( 'wp_ajax_chatl_nonce', 'chatl_ajax_nonce' );
add_action( 'wp_ajax_nopriv_chatl_nonce', 'chatl_ajax_nonce' );

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

	/*
	 * Kolory właściciela dokładamy jako KILKA ZMIENNYCH CSS, a nie jako
	 * wygenerowany arkusz: statyczny widget.css dalej siedzi w cache
	 * przeglądarki, a doklejka ma kilkadziesiąt bajtów. Gdy wygląd jest
	 * domyślny, chatl_style_wygladu() zwraca pusty string i strona nie
	 * dostaje ani jednego dodatkowego bajtu.
	 */
	$style = chatl_style_wygladu( $ust );
	if ( '' !== $style ) {
		wp_add_inline_style( 'chatl-widget', $style );
	}

	wp_enqueue_script( 'chatl-widget', CHATL_URL . 'assets/widget.js', array(), CHATL_WERSJA, true );
	wp_localize_script(
		'chatl-widget',
		'chatlDane',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'chatl_front' ),
			'powitanie'   => $ust['powitanie'],
			// Informacja o przetwarzaniu danych przy formularzu kontaktu:
			// co zbieramy, po co, jak długo. Adres polityki pusty => bez linku.
			'prywatnosc'  => sprintf(
				'Zbieramy Twój adres e-mail i treść wiadomości wyłącznie po to, żeby odpowiedzieć na Twoje pytanie. Przechowujemy je przez %d dni, potem są kasowane.',
				CHATL_RETENCJA_DNI
			),
			'polityka'    => esc_url( $ust['adres_polityki'] ),
			/*
			 * Nota na STARCIE rozmowy, nie dopiero przy formularzu kontaktu.
			 * Samo pisanie na czacie już ląduje w chatl_rozmowy — gość dowiadywał
			 * się o tym dopiero, gdy zostawiał maila, a wtedy część rozmowy była
			 * zapisana od kilku minut. Sprzedajemy wielu firmom i każda staje się
			 * administratorem danych, więc informacja ma być PRZED pierwszym słowem.
			 */
			'notaZapisu'  => sprintf(
				'Rozmowa jest zapisywana przez %d dni, żebyśmy mogli wrócić z odpowiedzią.',
				CHATL_RETENCJA_DNI
			),
			'notaLink'    => 'Polityka prywatności',
			'zgodaTekst'  => 'Zapoznałem się z informacją o przetwarzaniu danych',
			'pozycja'     => chatl_wyglad_ustawienia( $ust )['pozycja'],
			// Punkt odpytywania: co nowego w MOJEJ rozmowie (przejęcie przez człowieka).
			'restUrl'     => esc_url_raw( rest_url( 'chatl/v1/rozmowa' ) ),
			/*
			 * Komunikaty o zmianie rozmówcy. Gość ma wiedzieć, KOMU pisze, ZANIM
			 * napisze coś, czego nie powiedziałby maszynie — dlatego to jest pasek
			 * w oknie rozmowy, a nie zmiana awatara, którą łatwo przeoczyć.
			 */
			'ludzkiPasek' => 'Rozmawiasz z człowiekiem — do rozmowy dołączył konsultant.',
			'botPasek'    => 'Rozmawiasz z botem.',
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
	chatl_wymagaj_nonce();

	/*
	 * Pułapka sprawdzana PRZED limitem i PRZED czymkolwiek płatnym. Kolejność
	 * jest tu decyzją, nie przypadkiem: złapane żądanie nie może zużywać kubła,
	 * który blokuje PRAWDZIWYCH gości — inaczej bot dalej wypychałby ludzi
	 * z czatu, tyle że innym mechanizmem.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdzony linijkę wyżej.
	$sesja_wstepna = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';
	$pulapka       = chatl_pulapka_zlapala( $sesja_wstepna );
	if ( '' !== $pulapka ) {
		chatl_odnotuj_pulapke( $pulapka );
		/*
		 * Odpowiedź jest nieodróżnialna od zwykłego „nie znam odpowiedzi": bot
		 * nie dowiaduje się, że wpadł, a człowiek fałszywie złapany dostaje
		 * normalnie działający czat, tylko bez trybu AI.
		 */
		wp_send_json_success(
			array(
				'odpowiedz' => chatl_ustawienia()['tekst_awaryjny'],
				'silnik'    => 'reguly',
				'trafiono'  => false,
			)
		);
	}

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

	/*
	 * Sekret sesji obowiązuje TAKŻE przy zapisie — inaczej ktoś, kto zdobył sam
	 * identyfikator, mógłby podszyć się pod gościa i wstrzyknąć operatorowi
	 * wiadomość. Kod `sesja` jest sygnałem dla widgetu: poproś o nową sesję
	 * i spróbuj jeszcze raz (dokładnie tak, jak przy wygasłym nonce).
	 */
	if ( ! chatl_zapis_dozwolony( $sesja ) ) {
		wp_send_json_error(
			array(
				'komunikat' => 'Sesja rozmowy wygasła — zaczynam nową i wysyłam ponownie.',
				'kod'       => 'sesja',
			),
			403
		);
	}
	if ( mb_strlen( $pytanie ) > CHATL_MAX_PYTANIE ) {
		$pytanie = mb_substr( $pytanie, 0, CHATL_MAX_PYTANIE );
	}

	// Id pytania trzymamy, bo wynik dopasowania znamy dopiero niżej —
	// a to WIERSZ PYTANIA niesie treść, z której powstaje raport luk.
	$id_pytania = chatl_zapisz_wymiane( $sesja, 'gosc', $pytanie );
	chatl_sesja_aktywnosc( $sesja );

	/*
	 * PRZEJĘCIE: gdy rozmowę prowadzi człowiek, bot MILCZY — i to jest cała
	 * różnica między „mamy live chat" a „mamy dwa boty naraz".
	 *
	 * Wiadomość gościa jest już zapisana (operator musi ją przeczytać w panelu),
	 * ale nie idzie ani do modelu, ani do reguł: żadnego wywołania API (czyli
	 * żadnego kosztu), żadnej odpowiedzi automatu wpadającej człowiekowi w słowo.
	 * Odpowiedź operatora dojdzie do gościa odpytywaniem, nie tą odpowiedzią.
	 *
	 * Crisp dokumentuje u siebie zachowanie odwrotne (wiadomość operatora nie
	 * przerywa scenariusza bota) — dlatego strażnik sprawdza OBA kierunki:
	 * milczenie po przejęciu i powrót głosu po zakończeniu.
	 */
	if ( chatl_bot_milczy( $sesja ) ) {
		wp_send_json_success(
			array(
				'przejeta'  => true,
				'trafiono'  => null,
				'odpowiedz' => '',
			)
		);
	}

	// HYBRYDA: najpierw AI (jest klucz + limit dzienny globalny i per IP
	// niewyczerpany); błąd/timeout/brak klucza => zjazd do trybu regułowego.
	if ( chatl_ai_dostepny() && chatl_limit_ai_ip_ok() ) {
		// Historia BIEŻĄCEJ rozmowy: bez niej model nie wiedział, o czym mowa
		// przy dopytaniu. Bierzemy wiersze STARSZE niż właśnie zapisane pytanie,
		// żeby nie wysłać go dwa razy.
		$ai = chatl_ai_odpowiedz( $pytanie, chatl_ai_historia( $sesja, $id_pytania ) );
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
	chatl_wymagaj_nonce();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdzony linijkę wyżej.
	$sesja_wstepna = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';
	// Bez progu czasu — przy formularzu kontaktu fałszywy alarm kosztuje
	// utracony kontakt, a próg i tak nie łapie bota strzelającego wprost
	// w admin-ajax.php (patrz komentarz przy chatl_pulapka_zlapala).
	$pulapka = chatl_pulapka_zlapala( $sesja_wstepna, false );
	if ( '' !== $pulapka ) {
		chatl_odnotuj_pulapke( $pulapka );
	}

	if ( '' === $pulapka && ! chatl_limit_ip_ok() ) {
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

	/*
	 * ZŁAPANE ZGŁOSZENIE TRAFIA DO BAZY — z flagą, nie do kosza. Decyzja
	 * produktowa, nie techniczna: ten bot istnieje po to, żeby małej firmie
	 * ZBIERAĆ KONTAKTY. Koszt przejrzenia trzech spamów jest niższy niż koszt
	 * jednego utraconego klienta, a fałszywy alarm w wersji „cicho wyrzuć" jest
	 * NIEWIDZIALNY dla obu stron: gość myśli, że wysłał, właściciel nie wie,
	 * że coś przepadło.
	 *
	 * Cisza dotyczy BOTA, nie bazy — odpowiedź niżej jest identyczna jak przy
	 * czystym zgłoszeniu, więc skrypt się nie dowiaduje, że wpadł.
	 *
	 * Sufit na złapane zapisy: skoro złapane lądują w bazie, sama pułapka nie
	 * może stać się drogą do zalania tabeli. Powyżej sufitu nie zapisujemy —
	 * i to jest jedyne miejsce, w którym coś przepada. Nie dotyczy czystych
	 * zgłoszeń, więc prawdziwy klient nigdy tędy nie wypada.
	 */
	$zapis_pominiety = false;
	if ( '' !== $pulapka && ! chatl_rate_limit( 'pulapka', CHATL_LIMIT_PULAPKA ) ) {
		$zapis_pominiety = true;
	}

	// Zapis MUSI być sprawdzony: bez tego padnięty INSERT kończył się „Dziękujemy",
	// a wiadomość gościa przepadała bez śladu (cichy ubytek danych klienta).
	$zapisano = $zapis_pominiety ? true : $wpdb->insert(
		$wpdb->prefix . 'chatl_zgloszenia',
		array(
			'email'      => $email,
			'wiadomosc'  => $wiadomosc,
			'sesja'      => $sesja,
			'status'     => 'nowe',
			'podejrzane' => '' === $pulapka ? 0 : 1,
			'utworzono'  => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%d', '%s' )
	);
	if ( false === $zapisano ) {
		chatl_log_bledu_bazy( 'chatl_ajax_zgloszenie', 'zgłoszenie gościa z czatu' );
		wp_send_json_error(
			array( 'komunikat' => 'Nie udało się zapisać wiadomości — przepraszamy. Spróbuj ponownie za chwilę albo napisz do nas mailem.' ),
			500
		);
	}

	/*
	 * ⛔ ZŁAPANE ZGŁOSZENIE NIE WYSYŁA MAILA. To był główny powód postawienia tej
	 * pułapki: ochrona właściciela przed mail-bombą. Zgłoszenie jest w panelu,
	 * za filtrem „podejrzane", więc nic nie ginie — ale skrzynka zostaje czysta.
	 */
	if ( '' === $pulapka && $ust['powiadomienia'] && is_email( $ust['mail_powiadomien'] ) ) {
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
/**
 * Czy TO żądanie wpadło w pułapkę na boty.
 *
 * ⚠️ Uczciwie o zasięgu, żeby nikt nie wziął tego za ochronę budżetu AI:
 * pułapka łapie skrypty, które **zeskrobały stronę i odsyłają wszystko, co
 * znalazły**, albo strzelają natychmiast po pobraniu strony. NIE łapie kogoś,
 * kto zna kształt żądania i wysyła je wprost do `admin-ajax.php` — przed tym
 * broni dobowy limit na adres (CHATL_AI_LIMIT_IP_NA_DOBE), a nie to.
 * To jest tanie sito na masową głupotę, nie zamek na determinację.
 *
 * ⚠️ PRÓG CZASU JEST WŁĄCZANY OSOBNO I NIE DZIAŁA PRZY FORMULARZU KONTAKTU.
 * Powód zmierzony testem przeglądarkowym, nie wydedukowany: prawdziwy gość,
 * który otworzy czat i od razu kliknie „Zostaw wiadomość", potrafi wysłać
 * formularz szybciej niż w dwie sekundy od wydania sesji — i wpada we własną
 * pułapkę. Cena tego fałszywego alarmu to **utracony kontakt**, czyli jedyna
 * rzecz, dla której ten produkt istnieje.
 *
 * Rachunek wychodzi tu jednoznacznie na niekorzyść progu czasu przy zgłoszeniu:
 * bot uderzający wprost w `admin-ajax.php` podaje ZMYŚLONĄ sesję, dla której nie
 * ma wiersza w tabeli — a wtedy próg czasu i tak nie zadziała (brak wiersza to
 * nie dowód bota). Łapie więc wyłącznie skrypt, który grzecznie poprosi o sesję
 * i natychmiast strzeli. Przy pytaniu do bota koszt pomyłki jest bliski zeru
 * (gość dostaje odpowiedź z reguł i nawet tego nie zauważy), więc tam próg
 * zostaje. Przy formularzu — nie.
 *
 * @param string $sesja        Identyfikator sesji (już zwalidowany).
 * @param bool   $sprawdzaj_czas Czy stosować próg czasu (patrz wyżej).
 * @return string '' gdy czysto, inaczej powód: 'pole' albo 'czas'.
 */
function chatl_pulapka_zlapala( $sesja, $sprawdzaj_czas = true ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdza chatl_wymagaj_nonce() na wejściu do endpointu.
	if ( ! empty( $_POST[ CHATL_PULAPKA_POLE ] ) ) {
		return 'pole';
	}
	if ( ! $sprawdzaj_czas || '' === (string) $sesja ) {
		return '';
	}
	global $wpdb;
	$t = $wpdb->prefix . 'chatl_sesje';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$utworzono = $wpdb->get_var( $wpdb->prepare( "SELECT utworzono FROM {$t} WHERE sesja = %s", $sesja ) );
	if ( ! $utworzono ) {
		return ''; // Brak wiersza sesji to nie dowód bota — sesja mogła wygasnąć.
	}
	// Oba znaczniki w czasie WITRYNY: sesja zapisywana przez current_time( 'mysql' ).
	$wiek = (int) current_time( 'timestamp' ) - (int) strtotime( (string) $utworzono ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	return $wiek < CHATL_PULAPKA_SEKUND ? 'czas' : '';
}

/**
 * Licznik zadziałań pułapki — żeby właściciel WIDZIAŁ, że działa.
 *
 * Bez licznika pułapka jest niesprawdzalna z panelu: cisza znaczy albo „nic nie
 * przyszło", albo „przepuszczam wszystko", i nie da się tego rozróżnić. Licznik
 * czyni też widocznym FAŁSZYWY ALARM — jeśli rośnie, a klienci skarżą się, że
 * pisali i nie dostali odpowiedzi, właściciel wie, gdzie zajrzeć.
 *
 * @param string $powod 'pole' albo 'czas'.
 * @return void
 */
function chatl_odnotuj_pulapke( $powod ) {
	$stan = get_option( 'chatl_pulapka', array() );
	$stan = is_array( $stan ) ? $stan : array();
	update_option(
		'chatl_pulapka',
		array(
			'ile'      => 1 + (int) ( $stan['ile'] ?? 0 ),
			'ostatnia' => current_time( 'mysql' ),
			'powod'    => 'pole' === $powod ? 'ukryte pole' : 'zbyt szybkie żądanie',
		),
		false
	);
}

function chatl_odnotuj_wysylke_powiadomienia( $wyslano, $adres ) {
	if ( $wyslano ) {
		delete_option( 'chatl_blad_maila' );
		return;
	}
	$zapisano = chatl_zapisz_opcje(
		'chatl_blad_maila',
		array(
			'kiedy' => current_time( 'mysql' ),
			'adres' => (string) $adres,
		),
		false
	);
	// Znacznik awarii poczty, który sam pada po cichu, to podwójna cisza —
	// porażka jego zapisu idzie do trwałego śladu błędu bazy (A2-F4).
	if ( ! $zapisano ) {
		chatl_log_bledu_bazy( 'chatl_odnotuj_wysylke_powiadomienia', 'znacznik „powiadomienia mailowe nie wychodzą”' );
	}
}
