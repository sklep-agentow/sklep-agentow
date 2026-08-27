<?php
/**
 * STRAŻNIK REGRESJI — SK-L3-003 (opłacony zakup czekał na mail, gdy Stripe nie odpowiadał).
 *
 * Wada: druga pętla sklepag_dociagnij_zalegle() — ta, która dosyła maile do zamówień JUŻ
 * opłaconych — najpierw pytała Stripe o `payment_status`. Przy błędzie łączności robiła
 * `continue`, więc zapłacony i zapisany zakup stał w `mail_w_toku`/`oplacone` dopóki Stripe
 * nie odpowie, i nie trafiał nawet do licznika czerwonych na Pulpicie. Płatność była już
 * potwierdzona w naszej bazie — nie było czego weryfikować.
 *
 * Awarię łączności wymuszamy pustym kluczem Stripe (klient zwraca WP_Error bez ruchu w sieci).
 *
 * PASS = zamówienie wychodzi ze stanu oczekiwania mimo braku odpowiedzi Stripe.
 * FAIL = zamówienie nadal stoi w 'oplacone'/'mail_w_toku'.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu)\n";
	return;
}

$sid = 'cs_test_straznik_reconc';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $sid ) );

$stare = gmdate( 'Y-m-d H:i:s', time() - 3600 );
$wpdb->query( $wpdb->prepare( "INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'oplacona',%s)", $sid, (int) $produkt->id, $stare ) );
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$pz} (stripe_session_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona,
		 produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas,
		 pobrania_limit, utworzono, zmieniono)
		 VALUES (%s,%d,%s,%d,%s,%s,%d,'PLN','oplacone','zgoda',%s,%d,%s,%s)",
		$sid, (int) $produkt->id, $produkt->nazwa, (int) $produkt->cena_grosze, $produkt->plik_sciezka,
		'reconc-straznik@example.test', (int) $produkt->cena_grosze, $stare, SKLEPAG_LIMIT_POBRAN, $stare, $stare
	)
);
$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );

// AWARIA ŁĄCZNOŚCI: bez klucza klient Stripe zwraca WP_Error, nie dotykając sieci.
$klucz_kopia = get_option( 'sklepag_stripe_klucz_sekretny', '' );
update_option( 'sklepag_stripe_klucz_sekretny', '', false );

sklepag_dociagnij_zalegle();

update_option( 'sklepag_stripe_klucz_sekretny', $klucz_kopia, false );

$po = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$pz} WHERE id=%d", $id ) );
echo "STRAZNIK: status_po=$po (OCZEKIWANE: mail_wyslany albo mail_blad — NIE oplacone/mail_w_toku)\n";
$pass = in_array( $po, array( 'mail_wyslany', 'mail_blad' ), true );


$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id=%d", $id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $sid ) );

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik krzyczy FAIL, a CI
// widzi zero i przepuszcza wadę (klasa 11: nazwa bramki szersza niż to, co sprawdza).
$straznik_ok = ( $pass );
echo 'STRAZNIK: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL - oplacony zakup stoi, bo Stripe nie odpowiada' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
