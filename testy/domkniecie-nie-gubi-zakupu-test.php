<?php
/**
 * STRAŻNIK REGRESJI — SK-L-001 (KRYTYCZNE: opłacony zakup ginął przy awarii zapisu).
 *
 * Wada: sklepag_domknij_zakup() oznaczało sesję checkout jako 'oplacona' BEZWARUNKOWO,
 * nie sprawdzając, czy zamówienie faktycznie powstało. Przy awarii tabeli `zamowienia`
 * (padnięta tabela, pełny dysk) sesja dostawała 'oplacona' bez zamówienia, a
 * sklepag_dociagnij_zalegle() bierze WYŁĄCZNIE stan='utworzona' — zakup przepadał
 * trwale i bez śladu w panelu.
 *
 * Awaria zapisu symulowana przez RENAME TABLE (nie wolno zatrzymywać bazy współdzielonej);
 * tabela wraca NATYCHMIAST po wywołaniu.
 *
 * Uruchomienie:
 *   docker cp testy/domkniecie-nie-gubi-zakupu-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * PASS = przy awarii sesja ZOSTAJE 'utworzona' (reconciliation ją dociągnie) i powstaje
 *        wpis w dzienniku zdarzeń; a normalny zakup nadal kończy się 'oplacona'.
 * FAIL = sesja 'oplacona' bez zamówienia (zakup zgubiony) albo zdrowy zakup przestał działać.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu w bazie)\n";
	return;
}

$zbuduj_sesje = static function ( $sid ) use ( $wpdb, $ps, $pz, $produkt ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s, %d, 'utworzona', NOW())", $sid, (int) $produkt->id ) );
	return array(
		'id'               => $sid,
		'payment_status'   => 'paid',
		'amount_total'     => (int) $produkt->cena_grosze,
		'currency'         => 'pln',
		'customer_details' => array( 'email' => 'straznik-domkniecie@example.test' ),
	);
};

/* ---------- 1) AWARIA ZAPISU: sesja NIE MOŻE zostać oznaczona jako opłacona ---------- */
$sid_a  = 'cs_test_straznik_awaria';
$sesja  = $zbuduj_sesje( $sid_a );
$przed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pd}" );

$wpdb->query( "RENAME TABLE {$pz} TO {$pz}_straznik_x" );
$ok_a = sklepag_domknij_zakup( $sesja );
$wpdb->query( "RENAME TABLE {$pz}_straznik_x TO {$pz}" ); // przywrócenie NATYCHMIAST

$stan_a     = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid_a ) );
$zamowien_a = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE stripe_session_id=%s", $sid_a ) );
$slad       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE typ='domkniecie_blad_zapisu' AND opis LIKE %s", $wpdb->esc_like( $sid_a ) . '%' ) );

echo "AWARIA: zwrocil=" . ( $ok_a ? 'true' : 'false' ) . " (OCZEKIWANE: false — retry ma wrocic)\n";
echo "AWARIA: sesja_stan=$stan_a (OCZEKIWANE: utworzona)\n";
echo "AWARIA: zamowien=$zamowien_a (OCZEKIWANE: 0)\n";
echo "AWARIA: slad_w_dzienniku=$slad (OCZEKIWANE: 1)\n";
$pass_a = ( false === $ok_a && 'utworzona' === $stan_a && 0 === $zamowien_a && $slad >= 1 );
echo 'AWARIA: WYNIK=' . ( $pass_a ? 'PASS' : 'FAIL - oplacony zakup zgubiony bez sladu' ) . "\n";

/* ---------- 2) PRÓBA KONTROLNA: zdrowy zakup musi działać jak dotąd ---------- */
$sid_b  = 'cs_test_straznik_zdrowy';
$sesja2 = $zbuduj_sesje( $sid_b );
$ok_b   = sklepag_domknij_zakup( $sesja2 );

$stan_b     = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid_b ) );
$zamowien_b = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE stripe_session_id=%s", $sid_b ) );

echo "KONTROLA: sesja_stan=$stan_b (OCZEKIWANE: oplacona)\n";
echo "KONTROLA: zamowien=$zamowien_b (OCZEKIWANE: 1)\n";
$pass_b = ( 'oplacona' === $stan_b && 1 === $zamowien_b );
echo 'KONTROLA: WYNIK=' . ( $pass_b ? 'PASS' : 'FAIL - naprawa zepsula zdrowy zakup' ) . "\n";



/* ---------- sprzątanie ---------- */
foreach ( array( $sid_a, $sid_b ) as $s ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $s ) );
	if ( $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE zamowienie_id=%d", $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $s ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $s ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE opis LIKE %s", $wpdb->esc_like( $s ) . '%' ) );
}

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik krzyczy FAIL, a CI
// widzi zero i przepuszcza wadę (klasa 11: nazwa bramki szersza niż to, co sprawdza).
$straznik_ok = ( $pass_a && $pass_b );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
