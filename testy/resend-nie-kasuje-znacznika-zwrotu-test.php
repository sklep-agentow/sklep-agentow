<?php
/**
 * STRAŻNIK REGRESJI — „Wyślij ponownie" kasowało znacznik zwrotu.
 *
 * ⛔ ZAKRES TEGO STRAŻNIKA — tylko WARSTWA PIERWSZA, i tylko tyle obiecuje jego nazwa:
 * biała lista na wejściu (`admin-zamowienie.php`) wpuszcza wyłącznie `mail_wyslany`
 * i `mail_blad`, więc zamówienie `zwrocone`/`sporne` dostaje 409 i NIE dociera do zapisu.
 *
 * Warstwy DRUGIEJ (warunek `AND status NOT IN (…)` na samym zapisie) ten plik NIE mierzy
 * i mierzyć nie może: żeby tam dojść, trzeba by najpierw rozluźnić białą listę, czyli zmienić
 * produkcję. Wcześniejsza wersja miała tu przypadek `ZAPIS`, który wykonywał WŁASNE zapytanie
 * przepisane w teście — to mierzyło test, nie produkcję, i wypadało jak dowód, którym nie było.
 * Usunięty świadomie.
 *
 * ⭐ Dowodem drugiej warstwy jest `narzedzia-qa/bramka-status-bez-warunku.sh` (statycznie,
 * na całej klasie zapisów) wraz z jej próbą kontrolną — nie ten strażnik.
 *
 * Test woła PRAWDZIWĄ funkcję produkcyjną — z uprawnieniami administratora i poprawnym nonce.
 * Przekierowanie na końcu przechwytujemy filtrem (funkcja kończy się `exit`), a wysyłkę maila
 * podmieniamy na udaną przez `pre_wp_mail`, żeby wejść w gałąź „mail poszedł".
 *
 * PASS = resend na zamówieniu ZWRÓCONYM odmawia (409) i nie rusza ani statusu, ani kwoty
 *        zwrotu; zamówienie w stanie `mail_blad` nadal przechodzi normalną drogę.
 * FAIL = wejście do resendu przepuszcza stan zamknięty finansowo.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

if ( ! function_exists( 'sklepag_obsluz_resend' ) ) {
	if ( ! @include_once SKLEPAG_KATALOG . 'includes/admin-zamowienie.php' ) {
		echo "WYNIK=FAIL - brak includes/admin-zamowienie.php\n";
		echo "RAZEM: WYNIK=FAIL\n";
		exit( 1 ); // wyjście awaryjne też musi nieść kod błędu, inaczej CI zobaczy zero
	}
}
if ( ! function_exists( 'sklepag_obsluz_resend' ) ) {
	echo "WYNIK=FAIL - brak funkcji sklepag_obsluz_resend\n";
	echo "RAZEM: WYNIK=FAIL\n";
	exit( 1 );
}

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu)\n";
	exit( 2 ); // 2 = środowisko, nie werdykt o kodzie
}
$admini = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $admini ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak administratora na stosie)\n";
	exit( 2 );
}
wp_set_current_user( (int) $admini[0] );

$sid   = 'cs_test_straznik_resend_zwrot';
$pi    = 'pi_test_straznik_resend_zwrot';
$kwota = 9900;

$posprzataj = static function () use ( $wpdb, $pz, $pd, $sid ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	if ( $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE zamowienie_id=%d", $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
};
$zaloz = static function ( $status, $zwrocono ) use ( $wpdb, $pz, $sid, $pi, $kwota, $produkt, $posprzataj ) {
	$posprzataj();
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$pz} (stripe_session_id, payment_intent_id, produkt_id, produkt_nazwa_kupiona,
			 produkt_cena_kupiona, produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status,
			 zwrocono_grosze, zgoda_tresc, zgoda_czas, pobrania_limit, token_pobrania, utworzono, zmieniono)
			 VALUES (%s,%s,%d,%s,%d,%s,%s,%d,'PLN',%s,%d,'zgoda',NOW(),%d,%s,NOW(),NOW())",
			$sid, $pi, (int) $produkt->id, $produkt->nazwa, $kwota, $produkt->plik_sciezka,
			'resend-straznik@example.test', $kwota, $status, (int) $zwrocono, SKLEPAG_LIMIT_POBRAN,
			str_repeat( 'a', 64 )
		)
	);
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
};

// Mail „wychodzi" bez ruchu na zewnątrz; przekierowanie przechwytujemy, bo funkcja kończy exit.
add_filter( 'pre_wp_mail', '__return_true', 99 );
add_filter(
	'wp_redirect',
	static function ( $loc ) {
		throw new Exception( 'PRZEKIEROWANIE: ' . $loc );
	},
	PHP_INT_MAX
);
// `wp_die` kończy proces — podmieniamy uchwyt na wyjątek, żeby test mógł czytać werdykt.
// PRIORYTET MAKSYMALNY, bo WP-CLI rejestruje SWÓJ uchwyt (`WP_CLI::error`) na tym samym
// filtrze; przy niższym priorytecie nasz zostaje nadpisany i test pada na cudzym komunikacie
// zamiast czytać werdykt (zmierzone: przy priorytecie 1 wychodziło „Error: …", EXIT=1).
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

$odpal = static function ( $id ) {
	$_GET['zamowienie'] = $id;
	$nonce              = wp_create_nonce( 'sklepag_resend' );
	$_GET['_wpnonce']   = $nonce;
	$_REQUEST['_wpnonce'] = $nonce;
	try {
		sklepag_obsluz_resend();
		return 'BEZ PRZEKIEROWANIA';
	} catch ( Exception $e ) {
		return $e->getMessage();
	}
};

/* ---------- 1) WARSTWA 1: resend na zamówieniu ZWRÓCONYM jest ODRZUCANY ---------- */
$id1  = $zaloz( 'zwrocone', $kwota );
$ret1 = $odpal( $id1 );
$po1  = $wpdb->get_row( $wpdb->prepare( "SELECT status, zwrocono_grosze FROM {$pz} WHERE id=%d", $id1 ) );
echo 'ZWROCONE: werdykt=' . $ret1 . " (OCZEKIWANE: ODMOWA 409)\n";
echo 'ZWROCONE: status_po=' . $po1->status . " (OCZEKIWANE: zwrocone — znacznik zwrotu zostaje)\n";
echo 'ZWROCONE: zwrocono=' . (int) $po1->zwrocono_grosze . " (OCZEKIWANE: $kwota)\n";
$pass1 = ( false !== strpos( $ret1, 'ODMOWA 409' ) && 'zwrocone' === $po1->status && $kwota === (int) $po1->zwrocono_grosze );
echo 'ZWROCONE: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - resend wszedl na zamowienie zwrocone' ) . "\n";

/* ---------- 2) KONTROLA: zamówienie w stanie `mail_blad` przechodzi normalnie ---------- */
// Przypadek BEZ wady — tu resend MA prawo zmienić status, bo `mail_blad` jest na białej
// liście. Wysyłkę wymuszamy UDANĄ (`pre_wp_mail`), więc status ma wyjść `mail_wyslany`.
// Gdyby i ten przypadek padał, strażnik krzyczałby „zawsze", a nie „przy tej wadzie".
$id2 = $zaloz( 'mail_blad', 0 );
$ret2 = $odpal( $id2 );
$po2  = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$pz} WHERE id=%d", $id2 ) );
echo 'ZWYKLE: status_po_resendzie=' . $po2->status . " (OCZEKIWANE: mail_wyslany — zwykla droga nietknieta)\n";
$pass2 = ( 'mail_wyslany' === $po2->status );
echo 'ZWYKLE: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - naprawa zablokowala normalny resend' ) . "\n";

$posprzataj();
unset( $_GET['zamowienie'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'] );

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik krzyczy FAIL, a CI
// widzi zero i przepuszcza wadę (klasa 11: nazwa bramki szersza niż to, co sprawdza).
$straznik_ok = ( $pass1 && $pass2 );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
