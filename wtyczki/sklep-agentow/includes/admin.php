<?php
/**
 * Panel wp-admin: menu wtyczki, wspólne assety admina i strona-pulpit
 * z licznikami z bazy (prawdziwe zapytania, zero atrap). Widoki żyją w osobnych
 * plikach admin-*.php; dashboard „jak dobre sklepy".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SKLEPAG_KATALOG . 'includes/admin-dashboard.php';
require_once SKLEPAG_KATALOG . 'includes/admin-zamowienie.php';
require_once SKLEPAG_KATALOG . 'includes/admin-produkty.php';
require_once SKLEPAG_KATALOG . 'includes/admin-dziennik.php';
require_once SKLEPAG_KATALOG . 'includes/admin-ustawienia.php';
require_once SKLEPAG_KATALOG . 'includes/admin-opinie.php';

add_action( 'admin_menu', 'sklepag_admin_menu' );
add_action( 'admin_enqueue_scripts', 'sklepag_admin_assety' );
add_action( 'admin_notices', 'sklepag_ostrzez_permalinki' );
add_action( 'admin_notices', 'sklepag_ostrzez_strony_prawne' );

/**
 * Świeży WordPress ma bezpośrednie odnośniki „Zwykłe" — wtedy /sklep/ po cichu
 * pokazuje bloga, a ładny adres trasy webhooka /wp-json/sklepag/v1/webhook NIE ISTNIEJE:
 * WordPress oddaje na nim stronę główną motywu z kodem 200 (zmierzone, nie 404 — Stripe
 * uzna takie 2xx za doręczenie i nie ponowi). Sama trasa REST działa, tylko pod adresem
 * ?rest_route=/sklepag/v1/webhook. Potwierdzenia zakupu dociąga wtedy reconciliation
 * (co 10 minut), więc zakup nie ginie, ale mail potrafi się spóźnić o te 10 minut.
 * Test instalacji od zera: bez tego komunikatu instalujący utknąłby w tym miejscu.
 * Notice trwały (nie do zamknięcia), dopóki struktura odnośników jest pusta.
 */
function sklepag_ostrzez_permalinki() {
	if ( ! current_user_can( 'manage_options' ) || '' !== get_option( 'permalink_structure' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p><strong>Sklep Agentów:</strong> bezpośrednie odnośniki są ustawione na „Zwykłe" — strona sklepu i webhook płatności NIE DZIAŁAJĄ, dopóki tego nie zmienisz. Wejdź w <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">Ustawienia → Bezpośrednie odnośniki</a>, wybierz dowolną strukturę inną niż „Zwykłe" (np. „Nazwa wpisu") i zapisz.</p></div>';
}

/**
 * Aktywacja zakłada „Regulamin sklepu (SZABLON)" i „Politykę prywatności sklepu (SZABLON)"
 * jako WERSJE ROBOCZE z miejscami na dane sprzedawcy (`aktywacja.php:202-203`). Szablon w wersji
 * roboczej jest świadomy — treści prawnej nie da się napisać za właściciela, a opublikowanie
 * dokumentu z „[NAZWA, ADRES, KONTAKT]" byłoby gorsze niż jego brak.
 *
 * ⛔ Do 0.9.40 na tym się kończyło i to był błąd: obie strony leżały w wersji roboczej, a NIC ich
 * nie wołało — opcje `sklepag_strona_regulamin` i `sklepag_strona_polityka` były czytane wyłącznie
 * w `uninstall.php`, żeby je skasować (zmierzone: 1 odczyt w całej wtyczce, przy 7 plikach
 * czytających bliźniaczą `sklepag_strona_sklep`). Właściciel nie miał skąd wiedzieć, że one
 * istnieją i czekają na uzupełnienie — a sklep w tym czasie sprzedawał treści cyfrowe.
 * To jest ryzyko prawne właściciela (obowiązek informacyjny sprzedawcy, administrator danych),
 * nie kosmetyka, dlatego komunikat jest trwały i nie da się go zamknąć.
 *
 * Warunek jest SZERSZY niż „wersja robocza", bo opublikowanie nie znaczy „gotowe": strona
 * z „SZABLON" w tytule albo z niewypełnioną zaślepką w treści jest tak samo niegotowa.
 * Brak strony (opcja pusta lub wpis skasowany) NIE jest ostrzegany — właściciel mógł ją skasować
 * świadomie, bo ma własny regulamin gdzie indziej, i nie naszą rzeczą jest to podważać.
 */
function sklepag_ostrzez_strony_prawne() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$do_uzupelnienia = array();
	$powody          = array();
	$strony          = array(
		'sklepag_strona_regulamin' => 'Regulamin sklepu',
		'sklepag_strona_polityka'  => 'Polityka prywatności sklepu',
	);
	foreach ( $strony as $opcja => $nazwa ) {
		$id = (int) get_option( $opcja, 0 );
		if ( $id <= 0 ) {
			continue;
		}
		$wpis = get_post( $id );
		if ( ! $wpis || 'trash' === $wpis->post_status ) {
			continue;
		}
		// ⛔ Warunek jest ALTERNATYWA trzech przeslanek, wiec komunikat NIE MOZE mowic jednego
		//    zdania o wszystkich. Strona opublikowana z zaslepka w tresci dostawala zdanie „nadal
		//    SZABLON w wersji roboczej” i polecenie „opublikuj” — czyli opis stanu, w ktorym nie jest.
		//    Wlasciciel otwieral ja, widzial opublikowana i przestawal wierzyc ostrzeżeniu.
		$powod = '';
		if ( 'publish' !== $wpis->post_status ) {
			$powod = 'nieopublikowana';
		} elseif ( false !== strpos( $wpis->post_title, 'SZABLON' ) || false !== strpos( $wpis->post_content, 'SZABLON' ) ) {
			$powod = 'opublikowana, ale wciąż z zaślepką SZABLON';
		}
		if ( '' !== $powod ) {
			$powody[]          = $powod;
			$do_uzupelnienia[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $nazwa )
				. '</a> (' . esc_html( $powod ) . ')';
		}
	}
	if ( ! $do_uzupelnienia ) {
		return;
	}
	// ⛔ Zdanie koncowe MUSI pasowac do tego, co naprawde brakuje. Jedno zdanie na wszystkie przypadki
	//    kazalo OPUBLIKOWAC strone, ktora nawias obok nazywal juz opublikowana — sprzecznosc wewnatrz
	//    jednego komunikatu, czyli ta sama klasa, ktora ten PR naprawia. Liczba mnoga tez byla stala,
	//    wiec przy jednej stronie komunikat mowil o "stronach".
	// ⛔ PRZELACZNIK LICZY POWODY, NIE PYTA „CZY ISTNIEJE JAKIS”. Runda 5 rozdzielila zdanie
	//    koncowe na dwie galezie, ale przelacznikiem byla ALTERNATYWA po calej liscie
	//    (`in_array`). W stanie MIESZANYM — jedna strona robocza, druga opublikowana z zaslepka —
	//    `$sa_robocze` bylo prawda, wiec komunikat mowil „opublikuj te strony” takze o stronie,
	//    ktora nawias obok nazywal „opublikowana”. Sprzecznosc w JEDNYM akapicie, czyli ta sama
	//    klasa, ktora ten PR mial zamknac. Stan mieszany nie jest egzotyczny: `aktywacja.php:202-203`
	//    zaklada obie strony jako `draft` z „(SZABLON)” w TYTULE, wiec wlasciciel idzie strona po
	//    stronie i miedzy jedna a druga JEST w tym stanie.
	//    Teraz zdanie koncowe mowi osobno o kazdej grupie i tylko wtedy, gdy ta grupa istnieje.
	$ile          = count( $do_uzupelnienia );
	$ile_roboczych = count( array_keys( $powody, 'nieopublikowana', true ) );
	$ile_zaslepek  = count( array_keys( $powody, 'opublikowana, ale wciąż z zaślepką SZABLON', true ) );
	$co_zrobic    = 'Uzupełnij dane sprzedawcy (nazwa, adres, kontakt, adres do reklamacji)';
	if ( $ile_zaslepek > 0 ) {
		$co_zrobic .= ', usuń zaślepki SZABLON';
	}
	if ( $ile_roboczych > 0 && $ile_zaslepek > 0 ) {
		// Stan MIESZANY: polecenie „opublikuj” dotyczy WYLACZNIE stron roboczych, i tak jest nazwane.
		$co_zrobic .= ' i <strong>' . ( 1 === $ile_roboczych ? 'opublikuj tę stronę, która jest jeszcze robocza' : 'opublikuj te strony, które są jeszcze robocze' )
			. ', zanim zaczniesz sprzedawać</strong>';
	} elseif ( $ile_roboczych > 0 ) {
		$co_zrobic .= ' i <strong>' . ( 1 === $ile ? 'opublikuj tę stronę' : 'opublikuj te strony' ) . ', zanim zaczniesz sprzedawać</strong>';
	} else {
		$co_zrobic .= ' — <strong>' . ( 1 === $ile ? 'ta strona jest już opublikowana' : 'te strony są już opublikowane' ) . ', więc zmiana zadziała od razu</strong>';
	}
	echo '<div class="notice notice-error"><p><strong>Sklep Agentów:</strong> sklep może przyjmować płatności, a dokumenty dla kupujących nie są gotowe: '
		. wp_kses( implode( ' oraz ', $do_uzupelnienia ), array( 'a' => array( 'href' => array() ) ) )
		. ' — przy każdej stoi, czego jej brakuje. ' . wp_kses( $co_zrobic, array( 'strong' => array() ) ) . '. Sprzedaż treści cyfrowych bez regulaminu i informacji o administratorze danych jest ryzykiem prawnym po Twojej stronie.</p></div>';
}

function sklepag_admin_menu() {
	$pulpit = add_menu_page(
		'Sklep Agentów',
		'Sklep Agentów',
		'manage_options',
		'sklepag',
		'sklepag_admin_dashboard',
		'dashicons-cart',
		56
	);
	// Pierwsza pozycja submenu = czytelna etykieta zamiast powtórki nazwy wtyczki.
	add_submenu_page( 'sklepag', 'Sklep Agentów — pulpit', 'Pulpit', 'manage_options', 'sklepag', 'sklepag_admin_dashboard' );
	add_submenu_page( 'sklepag', 'Produkty sklepu', 'Produkty', 'manage_options', 'sklepag-produkty', 'sklepag_admin_produkty' );
	add_submenu_page( 'sklepag', 'Opinie kupujących — moderacja', 'Opinie', 'manage_options', 'sklepag-opinie', 'sklepag_admin_opinie' );
	add_submenu_page( 'sklepag', 'Dziennik — logowania i zdarzenia', 'Dziennik', 'manage_options', 'sklepag-dziennik', 'sklepag_admin_dziennik' );
	add_submenu_page( 'sklepag', 'Ustawienia sklepu', 'Ustawienia', 'manage_options', 'sklepag-ustawienia', 'sklepag_admin_ustawienia' );
	// Widok zamówienia: strona zarejestrowana, pozycja w menu ukrywana TYLKO
	// gdy nie jesteśmy na niej. ⛔ Bezwarunkowe remove_submenu_page odcina
	// dostęp HTTP: rdzeń wyznacza rodzica strony po wpisie w $submenu i bez
	// niego hook się nie zgadza → „Sorry, you are not allowed" (złapane
	// zrzutem przez prawdziwą przeglądarkę — wp eval tego nie widzi).
	add_submenu_page( 'sklepag', 'Zamówienie', 'Zamówienie', 'manage_options', 'sklepag-zamowienie', 'sklepag_admin_zamowienie' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt bieżącej strony menu, bez mutacji.
	$biezaca = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'sklepag-zamowienie' !== $biezaca ) {
		remove_submenu_page( 'sklepag', 'sklepag-zamowienie' );
	}

	// Screen Options „na stronę" dla tabeli zamówień (natywny wzorzec WP).
	add_action(
		'load-' . $pulpit,
		function () {
			add_screen_option(
				'per_page',
				array(
					'label'   => 'Zamówień na stronę',
					'default' => 20,
					'option'  => 'sklepag_zamowien_na_strone',
				)
			);
		}
	);
}

add_filter(
	'set_screen_option_sklepag_zamowien_na_strone',
	function ( $status, $option, $value ) {
		unset( $status, $option );
		return max( 1, min( 200, (int) $value ) );
	},
	10,
	3
);

/**
 * Wspólny CSS panelu — TYLKO na ekranach wtyczki (nie spowalniamy reszty wp-admin).
 *
 * @param string $hook Sufiks ekranu z admin_enqueue_scripts.
 */
function sklepag_admin_assety( $hook ) {
	if ( 'toplevel_page_sklepag' !== $hook && false === strpos( $hook, 'page_sklepag-' ) ) {
		return;
	}
	wp_enqueue_style( 'sklepag-admin', plugins_url( 'assets/admin.css', SKLEPAG_PLIK ), array(), sklepag_wersja_assetu( 'assets/admin.css' ) );
}

// Szkielet-liczniki z kości zastąpił pełny dashboard (includes/admin-dashboard.php);
// diagnostykę katalogu chronionego przejął notice w sklepag_admin_dashboard().
