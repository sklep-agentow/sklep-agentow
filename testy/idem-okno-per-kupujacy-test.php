<?php
/**
 * STRAŻNIK — Q-5: okno idempotencji zakupu rozdziela KUPUJĄCYCH za jednym adresem.
 *
 * Wada (w wydaniach przed tym): klucz okna = md5( IP | produkt | tryb ). Dwie osoby za tym samym adresem
 * (biuro za NAT, CGNAT operatora, uczelnia) dzieliły JEDNO okno: gdy pierwsza kliknęła „Kup”,
 * druga w ciągu SKLEPAG_IDEM_OKNO_S sekund dostawała ADRES CHECKOUTU PIERWSZEJ — cudzą sesję
 * Stripe, z cudzym zamówieniem po drugiej stronie.
 *
 * Naprawa: formularz zakupu niesie losowy znacznik `okno` z renderu strony produktu, a klucz
 * ma czwarty człon. Podwójny klik wysyła TEN SAM formularz (ten sam znacznik), więc ochrona
 * przed podwójną płatnością zostaje; druga osoba ma własny render i własny znacznik.
 *
 * Trzy kierunki, bo naprawa „wyłącz okno” też usunęłaby objaw A i zabrała ochronę B:
 *   A) dwa POST-y, ten sam adres, RÓŻNE znaczniki  => DWIE sesje (drugi NIE dostaje adresu pierwszego),
 *   B) dwa POST-y, ten sam znacznik                 => JEDNA sesja (podwójny klik nie płaci dwa razy),
 *   C) dwa POST-y BEZ znacznika (POST spoza formularza) => JEDNA sesja, jak w poprzednich wydaniach (zapas, nie regresja).
 *
 * Odpowiedź Stripe podstawiona filtrem rdzenia `pre_http_request` (każde utworzenie sesji
 * dostaje NOWY identyfikator) — test nie rusza sieci ani plików wtyczki, mierzy PRODUKT.
 * Produkt zakłada SAM (czysty stos nie ma żadnego) i sprząta po sobie w shutdown.
 *
 * ⛔ KLUCZ BIERZEMY Z PRODUKCJI (`sklepag_klucz_idem`), nie składamy go u siebie (klasa z #199).
 *
 * Uruchomienie:
 *   docker cp testy/idem-okno-per-kupujacy-test.php <kontener>:/tmp/q5.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/q5.php
 * EXIT 0 = A, B i C zgodne · 1 = któryś nie (WADA / REGRESJA) · 2 = przyrząd/środowisko
 *      (m.in. limiter zakupu: 10 prób na adres i minutę; ten strażnik woła handler 6 razy).
 */

global $wpdb;
$pp = $wpdb->prefix . 'sklepag_produkty';
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';

function q5_przyrzad( $powod ) {
	echo 'STRAZNIK: WYNIK=BLAD-SRODOWISKA (' . $powod . ")\n";
	exit( 2 );
}
foreach ( array( 'sklepag_obsluz_kup', 'sklepag_klucz_idem', 'sklepag_ip' ) as $f ) {
	if ( ! function_exists( $f ) ) {
		q5_przyrzad( 'brak funkcji ' . $f );
	}
}

/* ---------- własny produkt z plikiem (czysty stos nie ma żadnego) ---------- */
$znacznik_p = 'q5-' . wp_generate_password( 8, false, false );
$plik       = get_temp_dir() . 'sklepag-' . $znacznik_p . '.zip';
file_put_contents( $plik, "PLIK PROBNY Q5\n" );
$wpdb->insert(
	$pp,
	array(
		'nazwa'        => 'Q5 produkt ' . $znacznik_p,
		'slug'         => $znacznik_p,
		'opis_krotki'  => 'produkt probny Q-5',
		'cena_grosze'  => 14900,
		'waluta'       => 'PLN',
		'plik_sciezka' => $plik,
		'aktywny'      => 1,
		'utworzono'    => current_time( 'mysql' ),
	)
);
$produkt_id = (int) $wpdb->insert_id;
if ( ! $produkt_id ) {
	q5_przyrzad( 'nie udalo sie zalozyc produktu: ' . $wpdb->last_error );
}

$licznik = 0;
$atrapa  = static function ( $krotki, $args, $url ) use ( &$licznik, $znacznik_p ) {
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions' ) ) {
		++$licznik;
		$sid = 'cs_test_Q5' . str_replace( '-', '', $znacznik_p ) . '_' . $licznik;
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => wp_json_encode( array( 'id' => $sid, 'url' => 'https://checkout.stripe.com/c/pay/' . $sid ) ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}
	return $krotki;
};
add_filter( 'pre_http_request', $atrapa, 10, 3 );

$tokeny = array( 'X' => 'q5X' . wp_generate_password( 12, false, false ), 'Y' => 'q5Y' . wp_generate_password( 12, false, false ), 'Z' => 'q5Z' . wp_generate_password( 12, false, false ), '' => '' );

$sprzataj = static function () use ( $wpdb, $pp, $ps, $produkt_id, $plik, $tokeny, $znacznik_p ) {
	foreach ( $tokeny as $t ) {
		delete_transient( sklepag_klucz_idem( $produkt_id, false, $t ) );
	}
	// ⛔ esc_like() JEST TU OBOWIĄZKOWE (klasa 2): literał `cs_test_Q5` niesie DWA `_`,
	// a w `LIKE` `_` pasuje do DOWOLNEGO jednego znaku — wzorzec byłby szerszy niż zamierzony,
	// a to jest DELETE na wspólnej bazie stanowiska. esc_like() escapuje `_` i `%` w stałej;
	// `%` doklejamy PO escapowaniu, bo to jedyny wieloznacznik, którego tu chcemy.
	$wzor_q5 = $wpdb->esc_like( 'cs_test_Q5' . str_replace( '-', '', $znacznik_p ) ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id LIKE %s", $wzor_q5 ) );
	$wpdb->delete( $pp, array( 'id' => $produkt_id ) );
	if ( file_exists( $plik ) ) {
		unlink( $plik );
	}
};
register_shutdown_function( $sprzataj );

/** Woła PRAWDZIWY handler z danym znacznikiem formularza; zwraca adres przekierowania. */
$probuj_kup = static function ( $token ) use ( $produkt_id ) {
	$_POST = array();
	$_POST['produkt_id']  = $produkt_id;
	$_POST['zgoda']       = '1';
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_kup' );
	$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	if ( '' !== $token ) {
		$_POST['okno'] = $token;
	}
	$lap     = static function ( $u ) {
		throw new Exception( 'REDIRECT:' . $u );
	};
	$lap_die = static function () {
		return static function ( $tresc ) {
			if ( is_wp_error( $tresc ) ) {
				$tresc = $tresc->get_error_message();
			}
			throw new Exception( 'WPDIE:' . wp_strip_all_tags( (string) $tresc ) );
		};
	};
	add_filter( 'wp_die_handler', $lap_die, 99 );
	add_filter( 'wp_redirect', $lap, 1 );
	$gdzie = '';
	try {
		sklepag_obsluz_kup();
	} catch ( Throwable $e ) {
		$tresc = $e->getMessage();
		if ( false !== strpos( $tresc, 'Za dużo prób zakupu' ) ) {
			q5_przyrzad( 'limiter zakupu: 10 prob na minute z tego adresu — odczekaj do pelnej minuty' );
		}
		$gdzie = str_replace( array( 'REDIRECT:', 'WPDIE:' ), '', $tresc );
	}
	remove_filter( 'wp_redirect', $lap, 1 );
	remove_filter( 'wp_die_handler', $lap_die, 99 );
	return $gdzie;
};

$wynik = array();
$czek  = 'https://checkout.stripe.com/c/pay/';

/* ---------- A) WADA: dwie osoby za jednym adresem = dwa znaczniki ---------- */
$a1 = $probuj_kup( $tokeny['X'] );
$a2 = $probuj_kup( $tokeny['Y'] );
$a_ok = ( 0 === strpos( $a1, $czek ) ) && ( 0 === strpos( $a2, $czek ) ) && ( $a1 !== $a2 );
echo "A. dwa POST-y, ten sam adres, ROZNE znaczniki -> 1: {$a1}\n                                                2: {$a2}\n";
echo "   OCZEKIWANE: dwa ROZNE adresy checkoutu (druga osoba nie dostaje cudzej sesji)\n";
echo '   WYNIK=' . ( $a_ok ? 'PASS' : 'FAIL - druga osoba za tym samym adresem dostala cudzy checkout (albo zakup zepsuty)' ) . "\n";
$wynik[] = $a_ok;

/* ---------- B) KONTROLA: podwojny klik = ten sam znacznik ---------- */
$b1 = $probuj_kup( $tokeny['Z'] );
$b2 = $probuj_kup( $tokeny['Z'] );
$b_ok = ( 0 === strpos( $b1, $czek ) ) && ( $b1 === $b2 );
echo "B. dwa POST-y, TEN SAM znacznik -> 1: {$b1}\n                                    2: {$b2}\n";
echo "   OCZEKIWANE: ten sam adres (podwojny klik nie tworzy drugiej platnosci)\n";
echo '   WYNIK=' . ( $b_ok ? 'PASS' : 'FAIL - ochrona przed podwojnym klikiem przestala dzialac' ) . "\n";
$wynik[] = $b_ok;

/* ---------- C) ZAPAS: POST bez znacznika (spoza formularza) = okno per adres jak dotad ---------- */
$c1 = $probuj_kup( '' );
$c2 = $probuj_kup( '' );
$c_ok = ( 0 === strpos( $c1, $czek ) ) && ( $c1 === $c2 );
echo "C. dwa POST-y BEZ znacznika -> 1: {$c1}\n                               2: {$c2}\n";
echo "   OCZEKIWANE: ten sam adres (brak znacznika = okno per adres, jak w poprzednich wydaniach)\n";
echo '   WYNIK=' . ( $c_ok ? 'PASS' : 'FAIL - bez znacznika okno przestalo dzialac' ) . "\n";
$wynik[] = $c_ok;

// D. KSZTALT WEJSCIA: `okno[]=x` daje tablice. Bez `is_string` rzutowanie oddaje literal
// `Array`, ktory przechodzi przez wyrazenie [A-Za-z0-9] jako PRAWIDLOWY znacznik — wtedy
// KAZDY wysylajacy tablice trafia do JEDNEGO wspolnego okna. Ten przypadek to obstawia.
$_POST['okno'] = array( 'x' );
$d_pusty = sklepag_znacznik_okna_z_post();
$_POST['okno'] = 'abc123';
$d_zwykly = sklepag_znacznik_okna_z_post();
unset( $_POST['okno'] );
$d_ok = ( '' === $d_pusty && 'abc123' === $d_zwykly );
echo "D. znacznik z POST-a: okno[]=x -> '" . $d_pusty . "'   okno=abc123 -> '" . $d_zwykly . "'\n";
echo "   OCZEKIWANE: tablica = BRAK znacznika (pusty), lancuch = przepuszczony bez zmian\n";
echo '   WYNIK=' . ( $d_ok ? 'PASS' : 'FAIL - tablica w POST udaje prawidlowy znacznik (literal Array)' ) . "\n";
$wynik[] = $d_ok;

remove_filter( 'pre_http_request', $atrapa, 10 );
echo "SESJI U STRIPE (atrapa): {$licznik} (oczekiwane 4: A=2, B=1, C=1)\n";
$wynik[] = ( 4 === $licznik );

$ok = ! in_array( false, $wynik, true );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
