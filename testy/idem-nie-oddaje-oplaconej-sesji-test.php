<?php
/**
 * STRAŻNIK REGRESJI — SK-L3-005 (okno idempotencji oddawało JUŻ OPŁACONĄ sesję Stripe).
 *
 * Wada: sklepag_obsluz_kup() trzyma przez 60 s transient `sklepag_idem_<md5(IP|produkt)>`
 * z adresem checkoutu i przy każdej kolejnej próbie zakupu przekierowuje na ZAPAMIĘTANY
 * adres — bez sprawdzenia, czy tamta sesja nie została w międzyczasie opłacona. Kto zapłacił
 * i w ciągu minuty klika przycisk zakupu jeszcze raz, trafia na opłacony checkout (ekran
 * Stripe bez pól karty) zamiast na nowy zakup. Okno jest kluczowane REMOTE_ADDR, więc za
 * wspólnym adresem (CGNAT operatora, biuro za NAT) dotyka DWÓCH RÓŻNYCH kupujących.
 *
 * Test sprawdza OBA kierunki, bo naprawa polegająca na wyłączeniu okna też usunęłaby objaw —
 * i zabrałaby ochronę przed podwójnym kliknięciem, która jest zamierzona (PLAN-PROJEKTU §206):
 *   A) sesja OPŁACONA w oknie  => NIE wolno oddać starego adresu (ma powstać nowy checkout),
 *   B) sesja CZEKAJĄCA w oknie => WOLNO oddać stary adres (podwójny klik nie tworzy dwóch płatności).
 *
 * Odpowiedź Stripe podstawiona filtrem rdzenia `pre_http_request` — test nie rusza sieci
 * ani plików wtyczki, więc mierzy PRODUKT, nie atrapę.
 *
 * Uruchomienie:
 *   docker cp testy/idem-nie-oddaje-oplaconej-sesji-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * EXIT 0 = A i B zgodne z oczekiwaniem · 1 = któryś nie · 2 = środowisko bez produktu.
 */

global $wpdb;
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty WHERE aktywny = 1 LIMIT 1" );
if ( ! $produkt || '' === $produkt->plik_sciezka || ! file_exists( $produkt->plik_sciezka ) ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak aktywnego produktu z plikiem)\n";
	exit( 2 );
}

$stary_sid = 'cs_test_STARA_SESJA_STRAZNIK';
$stary_url = 'https://checkout.stripe.com/c/pay/' . $stary_sid;
$nowy_sid  = 'cs_test_NOWA_SESJA_STRAZNIK';
$nowy_url  = 'https://checkout.stripe.com/c/pay/' . $nowy_sid;
$okno      = 'sklepag_idem_' . md5( sklepag_ip() . '|' . (int) $produkt->id );

// Atrapa Stripe: utworzenie sesji zwraca NOWY identyfikator, w kształcie prawdziwej
// odpowiedzi /v1/checkout/sessions (id + url).
$atrapa = static function ( $krotki, $args, $url ) use ( $nowy_sid, $nowy_url ) {
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions' ) ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => wp_json_encode( array( 'id' => $nowy_sid, 'url' => $nowy_url ) ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}
	return $krotki;
};
add_filter( 'pre_http_request', $atrapa, 10, 3 );

$posprzataj = static function () use ( $wpdb, $ps, $okno, $stary_sid, $nowy_sid ) {
	delete_transient( $okno );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id IN (%s,%s)", $stary_sid, $nowy_sid ) );
};

/** Woła PRAWDZIWY handler i zwraca adres, na który przekierował. */
$probuj_kup = static function () use ( $produkt ) {
	$_POST['produkt_id']  = (int) $produkt->id;
	$_POST['zgoda']       = '1';
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_kup' );
	$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	$lap                  = static function ( $u ) {
		throw new Exception( 'REDIRECT:' . $u );
	};
	add_filter( 'wp_redirect', $lap, 1 );
	$gdzie = '';
	try {
		sklepag_obsluz_kup();
	} catch ( Exception $e ) {
		$gdzie = str_replace( 'REDIRECT:', '', $e->getMessage() );
	}
	remove_filter( 'wp_redirect', $lap, 1 );
	return $gdzie;
};

$wynik = array();

/* ---------- A) WADA: w oknie siedzi sesja JUŻ OPŁACONA ---------- */
$posprzataj();
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'oplacona',%s)",
		$stary_sid,
		(int) $produkt->id,
		current_time( 'mysql' )
	)
);
set_transient( $okno, array( 'url' => $stary_url, 'sid' => $stary_sid ), MINUTE_IN_SECONDS );
$a    = $probuj_kup();
$a_ok = ( $a !== $stary_url );
echo 'A. okno z sesja OPLACONA -> ' . ( '' === $a ? '(brak przekierowania)' : $a ) . "\n";
echo '   OCZEKIWANE: cokolwiek INNEGO niz ' . $stary_url . "\n";
echo '   WYNIK=' . ( $a_ok ? 'PASS' : 'FAIL - oddal kupujacemu oplacona sesje' ) . "\n";
$wynik[] = $a_ok;

/* ---------- B) KONTROLA: w oknie siedzi sesja CZEKAJĄCA (ochrona ma działać) ---------- */
$posprzataj();
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'utworzona',%s)",
		$stary_sid,
		(int) $produkt->id,
		current_time( 'mysql' )
	)
);
set_transient( $okno, array( 'url' => $stary_url, 'sid' => $stary_sid ), MINUTE_IN_SECONDS );
$b    = $probuj_kup();
$b_ok = ( $b === $stary_url );
echo 'B. okno z sesja CZEKAJACA -> ' . ( '' === $b ? '(brak przekierowania)' : $b ) . "\n";
echo '   OCZEKIWANE: ' . $stary_url . " (podwojny klik nie tworzy drugiej platnosci)\n";
echo '   WYNIK=' . ( $b_ok ? 'PASS' : 'FAIL - ochrona przed podwojnym klikiem przestala dzialac' ) . "\n";
$wynik[] = $b_ok;

$posprzataj();
remove_filter( 'pre_http_request', $atrapa, 10 );

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik pisze FAIL, a CI widzi zero.
$ok = ! in_array( false, $wynik, true );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
