<?php
/**
 * STRAŻNIK REGRESJI — po ZWROCIE albo w SPORZE brama pobrań ODMAWIA (410), nie wydaje pliku.
 *
 * WADA (Q-4): `sklepag_obsluz_pobranie()` sprawdzała wyłącznie token i termin, więc kupujący, któremu
 * właściciel oddał pieniądze, pobierał produkt do końca 72 h i limitu 10. Status `zwrocone`/`sporne`
 * ustawia `sklepag_zapisz_zwrot()`; brama nie patrzyła na niego wcale.
 *
 * ⛔ MIERZY PRAWDZIWĄ FUNKCJĘ BRAMY, nie kopię jej warunków. `wp_die()` podmieniamy na wyjątek
 * (priorytet MAKSYMALNY — WP-CLI rejestruje własny uchwyt na tym samym filtrze), a przypadek
 * KONTROLNY (zamówienie zwykłe) prowadzimy celowo do wp_die(503) przez NIEISTNIEJĄCY plik:
 * brama ma przejść kontrolę statusu i dotrzeć do etapu pliku. Dzięki temu żaden przypadek
 * nie kończy się `readfile()` + `exit`, który ucinałby proces przed werdyktem.
 *
 * PASS = zwrócone → ODMOWA 410 bez zużycia slotu + zdarzenie `pobranie_odmowa_zwrot`;
 *        sporne   → ODMOWA 410 bez zużycia slotu;
 *        zwykłe   → ODMOWA 503 (etap pliku), czyli kontrola statusu NIE blokuje normalnej drogi.
 * Kod wyjścia: 0 PASS · 1 FAIL · 2 środowisko.
 * Uruchomienie (na stosie): wp --path=/var/www/html eval-file testy/pobranie-po-zwrocie-odmawia-test.php
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

if ( ! function_exists( 'sklepag_obsluz_pobranie' ) ) {
	if ( ! @include_once SKLEPAG_KATALOG . 'includes/pobieranie.php' ) {
		echo "WYNIK=FAIL - brak includes/pobieranie.php\n";
		echo "RAZEM: WYNIK=FAIL\n";
		exit( 1 );
	}
}
if ( ! function_exists( 'sklepag_obsluz_pobranie' ) ) {
	echo "WYNIK=FAIL - brak funkcji sklepag_obsluz_pobranie\n";
	echo "RAZEM: WYNIK=FAIL\n";
	exit( 1 );
}
// Brama czyta WYŁĄCZNIE wiersz zamówienia (migawka produktu z chwili zakupu), więc strażnik nie potrzebuje
// wiersza w tabeli produktów — na czystym stosie próbnym (kalibracja) żadnego produktu nie ma i to nie może
// być powodem kodu 2. Produkt-widmo: id 0, nazwa własna.
$produkt = (object) array( 'id' => 0, 'nazwa' => 'Produkt strażnika Q-4' );

$sid   = 'cs_test_straznik_pobranie_zwrot';
$pi    = 'pi_test_straznik_pobranie_zwrot';
$kwota = 9900;
$token = str_repeat( 'b', 64 );                 // jawny token w linku
$skrot = hash( 'sha256', $token );              // w bazie leży skrót

$posprzataj = static function () use ( $wpdb, $pz, $pd, $sid ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	if ( $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE zamowienie_id=%d", $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
};

$zaloz = static function ( $status, $zwrocono ) use ( $wpdb, $pz, $sid, $pi, $kwota, $produkt, $posprzataj, $skrot ) {
	$posprzataj();
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$pz} (stripe_session_id, payment_intent_id, produkt_id, produkt_nazwa_kupiona,
			 produkt_cena_kupiona, produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status,
			 zwrocono_grosze, zgoda_tresc, zgoda_czas, pobrania_limit, pobrania_uzyte, token_pobrania, link_wygasa,
			 utworzono, zmieniono)
			 VALUES (%s,%s,%d,%s,%d,%s,%s,%d,'PLN',%s,%d,'zgoda',NOW(),%d,0,%s,DATE_ADD(NOW(), INTERVAL 1 DAY),NOW(),NOW())",
			$sid, $pi, (int) $produkt->id, $produkt->nazwa, $kwota,
			'/nie/ma/takiego/pliku-straznik-q4.zip',   // celowo: kontrola ma skończyć na etapie PLIKU (503)
			'pobranie-straznik@example.test', $kwota, $status, (int) $zwrocono, SKLEPAG_LIMIT_POBRAN, $skrot
		)
	);
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
};

add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $wiadomosc, $tytul = '', $args = array() ) {
			$kod = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 0;
			throw new Exception( 'ODMOWA ' . $kod . ': ' . wp_strip_all_tags( (string) $wiadomosc ) );
		};
	},
	PHP_INT_MAX
);
$odpal = static function ( $id, $metoda = 'GET' ) use ( $token ) {
	$_GET['z']                 = $id;
	$_GET['t']                 = $token;
	$_SERVER['REQUEST_METHOD'] = $metoda;
	$_SERVER['REMOTE_ADDR']    = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
	try {
		sklepag_obsluz_pobranie();
		return 'BEZ ODMOWY (wydano plik?)';
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
};
$stan = static function ( $id ) use ( $wpdb, $pz, $pd ) {
	return array(
		'uzyte'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT pobrania_uzyte FROM {$pz} WHERE id=%d", $id ) ),
		'odmowy' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE zamowienie_id=%d AND typ='pobranie_odmowa_zwrot'", $id ) ),
	);
};

/* ---------- 1) ZWRÓCONE: brama odmawia 410, slot nietknięty, zdarzenie zapisane ---------- */
$id1 = $zaloz( 'zwrocone', $kwota );
$r1  = $odpal( $id1 );
$s1  = $stan( $id1 );
echo 'ZWROCONE: werdykt=' . $r1 . " (OCZEKIWANE: ODMOWA 410 …zwrócone…)\n";
echo 'ZWROCONE: pobrania_uzyte=' . $s1['uzyte'] . " (OCZEKIWANE: 0 — odmowa nie zużywa slotu)\n";
echo 'ZWROCONE: zdarzen_odmowy=' . $s1['odmowy'] . " (OCZEKIWANE: 1)\n";
$pass1 = ( 0 === strpos( $r1, 'ODMOWA 410' ) && false !== strpos( $r1, 'zwrócone' ) && 0 === $s1['uzyte'] && 1 === $s1['odmowy'] );
echo 'ZWROCONE: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - brama wydaje produkt po zwrocie' ) . "\n";

/* ---------- 2) SPORNE: brama odmawia 410, slot nietknięty ---------- */
$id2 = $zaloz( 'sporne', 0 );
$r2  = $odpal( $id2 );
$s2  = $stan( $id2 );
echo 'SPORNE: werdykt=' . $r2 . " (OCZEKIWANE: ODMOWA 410 …sporze…)\n";
echo 'SPORNE: pobrania_uzyte=' . $s2['uzyte'] . " (OCZEKIWANE: 0)\n";
$pass2 = ( 0 === strpos( $r2, 'ODMOWA 410' ) && false !== strpos( $r2, 'sporze' ) && 0 === $s2['uzyte'] );
echo 'SPORNE: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - brama wydaje produkt w trakcie sporu' ) . "\n";

/* ---------- 3) KONTROLA: zwykłe zamówienie przechodzi kontrolę statusu i dociera do etapu pliku ---------- */
// Plik celowo nie istnieje → oczekujemy ODMOWY 503 z etapu PLIKU, nie 410 ze statusu.
// Gdyby i tu było 410, strażnik krzyczałby „zawsze”, a nie „przy tej wadzie”.
$id3 = $zaloz( 'mail_wyslany', 0 );
$r3  = $odpal( $id3 );
$s3  = $stan( $id3 );
echo 'ZWYKLE: werdykt=' . $r3 . " (OCZEKIWANE: ODMOWA 503 — etap pliku, czyli status NIE blokuje)\n";
echo 'ZWYKLE: zdarzen_odmowy=' . $s3['odmowy'] . " (OCZEKIWANE: 0)\n";
$pass3 = ( 0 === strpos( $r3, 'ODMOWA 503' ) && 0 === $s3['odmowy'] );
echo 'ZWYKLE: WYNIK=' . ( $pass3 ? 'PASS' : 'FAIL - naprawa zablokowala normalne pobranie' ) . "\n";

/* ---------- 4) ZWROT CZĘŚCIOWY: status bez zmian, zwrocono_grosze > 0 — dostęp ZOSTAJE (obietnica z INSTRUKCJA/CHANGELOG) ---------- */
// Granica „zwrot częściowy nadal pobiera” była obiecana w trzech dokumentach i niemierzona:
// kolejna zmiana bramy z warunkiem `zwrocono_grosze > 0` zabrałaby dostęp po geście właściciela — tu ma paść.
$id4 = $zaloz( 'mail_wyslany', (int) floor( $kwota / 2 ) );
$r4  = $odpal( $id4 );
$s4  = $stan( $id4 );
echo 'CZESCIOWY: werdykt=' . $r4 . " (OCZEKIWANE: ODMOWA 503 — etap pliku, czyli zwrot częściowy NIE odcina)\n";
echo 'CZESCIOWY: zdarzen_odmowy=' . $s4['odmowy'] . " (OCZEKIWANE: 0)\n";
$pass4 = ( 0 === strpos( $r4, 'ODMOWA 503' ) && 0 === $s4['odmowy'] );
echo 'CZESCIOWY: WYNIK=' . ( $pass4 ? 'PASS' : 'FAIL - zwrot czesciowy odcina dostep wbrew obietnicy z instrukcji' ) . "\n";

/* ---------- 5) HEAD na ZWROCONYM: odmowa TAK SAMO, ale BEZ wpisu w dzienniku ----------
 * Granica dodana w rundzie 3 i do rundy 4 niepilnowana: skaner poczty w kliencie kupującego
 * odpytuje link metodą HEAD, więc wpis „pobranie_odmowa_zwrot” z takiego żądania byłby
 * nieodróżnialny od realnej próby człowieka. `pobieranie.php:57-61` liczy `$metoda_head`
 * i pomija `sklepag_zdarzenie()`. Skasowanie tego warunku nie zmienia NICZEGO w odmowie —
 * dlatego przypadki 1-4 przechodzą po nim tak samo i granica przeżyła bez strażnika. */
$id5 = $zaloz( 'zwrocone', $kwota );
$r5  = $odpal( $id5, 'HEAD' );
$s5  = $stan( $id5 );
echo 'HEAD: werdykt=' . $r5 . " (OCZEKIWANE: ODMOWA 410 — HEAD nie omija bramy)\n";
echo 'HEAD: pobrania_uzyte=' . $s5['uzyte'] . " (OCZEKIWANE: 0)\n";
echo 'HEAD: zdarzen_odmowy=' . $s5['odmowy'] . " (OCZEKIWANE: 0 — skaner poczty NIE zasmieca dziennika)\n";
$pass5 = ( 0 === strpos( $r5, 'ODMOWA 410' ) && 0 === $s5['uzyte'] && 0 === $s5['odmowy'] );
echo 'HEAD: WYNIK=' . ( $pass5 ? 'PASS' : 'FAIL - HEAD albo omija brame, albo zapisuje zdarzenie nieodroznialne od proby czlowieka' ) . "\n";

$posprzataj();
unset( $_GET['z'], $_GET['t'] );
$straznik_ok = ( $pass1 && $pass2 && $pass3 && $pass4 && $pass5 );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
