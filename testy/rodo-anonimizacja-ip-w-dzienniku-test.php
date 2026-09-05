<?php
/* Dowod reczny P-5: po anonimizacji kupujacego w dzienniku zdarzen zostawaly adresy IP
 * (wpisy `pobranie` i `pobranie_odmowa_zwrot` z pobieranie.php zapisuja je doslownie:
 * „ip 203.0.113.7"). Ten skrypt zasiewa zamowienie testowe z takimi wpisami, pokazuje
 * SELECT PRZED i PO anonimizacji, i sprzata po sobie.
 *
 * Kody: 0 = po anonimizacji ZERO adresow IP w dzienniku · 1 = adres IP ZOSTAL.
 */
global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pe = $wpdb->prefix . 'sklepag_zdarzenia';

$email = 'p5-dowod-' . time() . '@example.test';
$wpdb->insert(
	$pz,
	array(
		'stripe_session_id'     => 'cs_test_p5_' . wp_generate_password( 12, false ),
		'produkt_id'            => 1,
		'produkt_nazwa_kupiona' => 'Zamowienie probne P-5',
		'email_kupujacego'      => $email,
		'status'                => 'mail_wyslany',
		'token_pobrania'        => str_repeat( 'a', 64 ),
		'utworzono'             => current_time( 'mysql' ),
		'zmieniono'             => current_time( 'mysql' ),
	)
);
$id = (int) $wpdb->insert_id;
if ( ! $id ) {
	echo "PRZYRZAD=NIE-UDALO-SIE-UTWORZYC-ZAMOWIENIA\n";
	exit( 2 );
}
echo "ID=$id\n";

sklepag_zdarzenie( $id, 'pobranie', 'ip 203.0.113.7' );
sklepag_zdarzenie( $id, 'pobranie_odmowa_zwrot', 'status oczekuje, ip 198.51.100.42' );

$licz_ip = static function ( $wiersze ) {
	$n = 0;
	foreach ( $wiersze as $w ) {
		if ( preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $w->opis ) ) {
			++$n;
		}
	}
	return $n;
};

echo "=== PRZED anonimizacja (SELECT typ, opis FROM {$pe} WHERE zamowienie_id=$id) ===\n";
$przed = $wpdb->get_results( $wpdb->prepare( "SELECT typ, opis FROM {$pe} WHERE zamowienie_id = %d ORDER BY id", $id ) );
foreach ( $przed as $w ) {
	echo "  {$w->typ}: {$w->opis}\n";
}
echo 'IP_PRZED=' . $licz_ip( $przed ) . "\n";

$wynik = sklepag_anonimizuj_zamowienie( $id, true );
echo 'WYNIK_OK=' . ( $wynik['ok'] ? '1' : '0' ) . ' SLADY_IP=' . $wynik['slady_ip'] . "\n";

echo "=== PO anonimizacji ===\n";
$po = $wpdb->get_results( $wpdb->prepare( "SELECT typ, opis FROM {$pe} WHERE zamowienie_id = %d ORDER BY id", $id ) );
foreach ( $po as $w ) {
	echo "  {$w->typ}: {$w->opis}\n";
}
$ip_po = $licz_ip( $po );
echo "IP_PO=$ip_po\n";

$wpdb->delete( $pe, array( 'zamowienie_id' => $id ) );
$wpdb->delete( $pz, array( 'id' => $id ) );
echo "SPRZATANIE=OK\n";

if ( 0 === $ip_po && true === $wynik['ok'] ) {
	echo "WYNIK=PASS\n";
	exit( 0 );
}
echo "WYNIK=FAIL\n";
exit( 1 );
