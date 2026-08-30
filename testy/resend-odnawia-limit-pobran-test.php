<?php
/**
 * STRAŻNIK REGRESJI — „Wyślij ponownie" MUSI odnawiać limit pobrań.
 *
 * Wada (recenzja instrukcji sklepu, runda 4, KRYTYCZNA): instrukcja kieruje po ten przycisk
 * kupującego z WYCZERPANYM limitem, a sklepag_obsluz_resend() zapisywało tylko token, termin
 * i treść maila — `pobrania_uzyte` zostawało na 10, więc świeży link od razu dawał 410
 * „Limit pobrań wyczerpany". Mail ze świeżym linkiem obiecywał przy tym „pozwala na 10 pobrań".
 *
 * Uruchomienie:
 *   docker cp testy/resend-odnawia-limit-pobran-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * CZĘŚĆ 1: udany resend przy wyczerpanym limicie → pobrania_uzyte = 0 (świeży link daje pobrania).
 * CZĘŚĆ 2 (kompensacja): NIEUDANY resend → pobrania_uzyte wraca do wartości sprzed kliknięcia
 *   (stary link zachowuje swój stan; kupujący nie dostaje ekstra pobrań na starym linku).
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';

if ( ! function_exists( 'sklepag_obsluz_resend' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin-zamowienie.php';
}

$sid          = 'cs_test_resend_limit_strażnik';
$stary_token  = hash( 'sha256', 'STARY-TOKEN-LIMIT' );
$stary_termin = gmdate( 'Y-m-d H:i:s', time() + 50 * HOUR_IN_SECONDS );
$limit        = (int) SKLEPAG_LIMIT_POBRAN;

$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak produktu w bazie)\n";
	exit( 2 );
}
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$pz} (stripe_session_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona,
		 produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas,
		 pobrania_limit, pobrania_uzyte, token_pobrania, link_wygasa, utworzono, zmieniono)
		 VALUES (%s,%d,%s,%d,%s,%s,%d,'PLN','mail_wyslany','zgoda',NOW(),%d,%d,%s,%s,NOW(),NOW())",
		$sid,
		(int) $produkt->id,
		$produkt->nazwa,
		(int) $produkt->cena_grosze,
		$produkt->plik_sciezka,
		'straznik-limit@example.test',
		(int) $produkt->cena_grosze,
		$limit,
		$limit, // limit WYCZERPANY — dokładnie przypadek z instrukcji
		$stary_token,
		$stary_termin
	)
);
$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
if ( function_exists( 'sklepag_opinia_zalazek' ) ) {
	sklepag_opinia_zalazek( $id, (int) $produkt->id );
}

wp_set_current_user( 1 );
$_GET['zamowienie']     = $id;
$_REQUEST['zamowienie'] = $id;
add_filter(
	'wp_redirect',
	static function ( $miejsce ) {
		throw new Exception( 'PRZEKIEROWANIE: ' . $miejsce );
	},
	99
);

// ---- CZĘŚĆ 1: udany resend zeruje licznik ----
add_filter( 'pre_wp_mail', '__return_true', 99 );
$_GET['_wpnonce']     = wp_create_nonce( 'sklepag_resend' );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
$przek1 = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$przek1 = $e->getMessage();
}
$po1 = $wpdb->get_row( $wpdb->prepare( "SELECT pobrania_uzyte, pobrania_limit, token_pobrania, status FROM {$pz} WHERE id=%d", $id ) );
echo 'STRAZNIK: przekierowanie=' . ( false !== strpos( $przek1, 'sklepag_resend=ok' ) ? 'ok (OK)' : $przek1 ) . "\n";
echo 'STRAZNIK: pobrania_uzyte_po_udanym=' . (int) $po1->pobrania_uzyte . " (OCZEKIWANE: 0)\n";
echo 'STRAZNIK: token_rotowany=' . ( $po1->token_pobrania !== $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass1 = ( 0 === (int) $po1->pobrania_uzyte && (int) $po1->pobrania_uzyte < (int) $po1->pobrania_limit && $po1->token_pobrania !== $stary_token && 'mail_wyslany' === $po1->status );
echo 'STRAZNIK: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - swiezy link nadal ma wyczerpany limit (410)' ) . "\n";
remove_filter( 'pre_wp_mail', '__return_true', 99 );

// ---- CZĘŚĆ 2 (kompensacja): nieudany resend przywraca licznik ----
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=%d WHERE id=%d", $stary_token, $stary_termin, 7, $id ) );
add_filter( 'pre_wp_mail', '__return_false', 99 );
$_GET['_wpnonce']     = wp_create_nonce( 'sklepag_resend' );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
$przek2 = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$przek2 = $e->getMessage();
}
$po2 = $wpdb->get_row( $wpdb->prepare( "SELECT pobrania_uzyte, token_pobrania, status FROM {$pz} WHERE id=%d", $id ) );
echo 'KONTROLA: przekierowanie=' . ( false !== strpos( $przek2, 'sklepag_resend=blad' ) ? 'blad (OK)' : $przek2 ) . "\n";
echo 'KONTROLA: pobrania_uzyte_po_nieudanym=' . (int) $po2->pobrania_uzyte . " (OCZEKIWANE: 7)\n";
echo 'KONTROLA: token_przywrocony=' . ( $po2->token_pobrania === $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass2 = ( 7 === (int) $po2->pobrania_uzyte && $po2->token_pobrania === $stary_token && 'mail_blad' === $po2->status );
echo 'KONTROLA: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - nieudany resend zmienil licznik pobran starego linku' ) . "\n";
remove_filter( 'pre_wp_mail', '__return_false', 99 );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id=%d", $id ) );

$ok = ( $pass1 && $pass2 );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
