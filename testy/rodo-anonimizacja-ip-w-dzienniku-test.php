<?php
/* Dowod reczny P-5: po anonimizacji kupujacego w dzienniku zdarzen zostawaly adresy IP
 * (wpisy `pobranie` i `pobranie_odmowa_zwrot` z pobieranie.php zapisuja je doslownie:
 * „ip 203.0.113.7"). Ten skrypt zasiewa zamowienie testowe z takimi wpisami, pokazuje
 * SELECT PRZED i PO anonimizacji, i sprzata po sobie.
 *
 * DROBNA 5 (recenzja #291): czyszczenie idzie po TREŚCI dziennika w zakresie JEDNEGO
 * zamówienia (`sklepag_kandydaci_ip_w_dzienniku($id)` filtruje `WHERE zamowienie_id = %d`).
 * Bez drugiego, NIEZWIĄZANEGO zamówienia w tym samym dzienniku przeszłaby też naprawa,
 * która zamiast filtrować po `zamowienie_id`, czyści KAŻDY wiersz z adresem IP w całej
 * tabeli — test widziałby to samo "IP_PO=0" i meldował sukces.
 *
 * Kody: 0 = po anonimizacji ZERO adresow IP przy zamowieniu docelowym I obcy wpis nietkniety ·
 *       1 = ktorykolwiek warunek nie wyszedl.
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

// Drugie, NIEZWIĄZANE zamówienie (DROBNA 5) — jego wpis z IP nie wolno ruszyć.
$email_obcy = 'p5-obcy-' . time() . '@example.test';
$wpdb->insert(
	$pz,
	array(
		'stripe_session_id'     => 'cs_test_p5_obcy_' . wp_generate_password( 12, false ),
		'produkt_id'            => 1,
		'produkt_nazwa_kupiona' => 'Zamowienie probne P-5 (obce)',
		'email_kupujacego'      => $email_obcy,
		'status'                => 'mail_wyslany',
		'token_pobrania'        => str_repeat( 'b', 64 ),
		'utworzono'             => current_time( 'mysql' ),
		'zmieniono'             => current_time( 'mysql' ),
	)
);
$id_obcy = (int) $wpdb->insert_id;
if ( ! $id_obcy ) {
	echo "PRZYRZAD=NIE-UDALO-SIE-UTWORZYC-ZAMOWIENIA-OBCEGO\n";
	exit( 2 );
}
echo "ID_OBCY=$id_obcy\n";

sklepag_zdarzenie( $id, 'pobranie', 'ip 203.0.113.7' );
sklepag_zdarzenie( $id, 'pobranie_odmowa_zwrot', 'status oczekuje, ip 198.51.100.42' );
$opis_obcy = 'ip 192.0.2.99';
sklepag_zdarzenie( $id_obcy, 'pobranie', $opis_obcy );

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

echo "=== PO anonimizacji (zamowienie docelowe) ===\n";
$po = $wpdb->get_results( $wpdb->prepare( "SELECT typ, opis FROM {$pe} WHERE zamowienie_id = %d ORDER BY id", $id ) );
foreach ( $po as $w ) {
	echo "  {$w->typ}: {$w->opis}\n";
}
$ip_po = $licz_ip( $po );
echo "IP_PO=$ip_po\n";
// Przypadek BEZ wady W OBRĘBIE czyszczonego wiersza (#291 r1 D5 / r3 D4): czyszczenie ma usunąć ADRES, nie cały opis.
// „Naprawa”, która kasuje cały `opis`, dawałaby IP_PO=0 i przechodziła — dlatego osobna asercja na treść obok adresu.
$tresc_zostala = false;
foreach ( $po as $w ) {
	if ( false !== strpos( (string) $w->opis, 'status oczekuje' ) ) { $tresc_zostala = true; }
}
echo 'TRESC_OBOK_IP_ZOSTALA=' . ( $tresc_zostala ? '1' : '0' ) . "\n";

echo "=== PO anonimizacji (zamowienie OBCE — nie dotyczylo go zadanie) ===\n";
$po_obcy       = $wpdb->get_results( $wpdb->prepare( "SELECT typ, opis FROM {$pe} WHERE zamowienie_id = %d ORDER BY id", $id_obcy ) );
$obcy_nietkniety = ( 1 === count( $po_obcy ) && $opis_obcy === (string) $po_obcy[0]->opis );
foreach ( $po_obcy as $w ) {
	echo "  {$w->typ}: {$w->opis}\n";
}
echo 'OBCY_NIETKNIETY=' . ( $obcy_nietkniety ? '1' : '0' ) . "\n";

$wpdb->delete( $pe, array( 'zamowienie_id' => $id ) );
$wpdb->delete( $pe, array( 'zamowienie_id' => $id_obcy ) );
$wpdb->delete( $pz, array( 'id' => $id ) );
$wpdb->delete( $pz, array( 'id' => $id_obcy ) );
echo "SPRZATANIE=OK\n";

if ( 0 === $ip_po && true === $wynik['ok'] && $obcy_nietkniety && $tresc_zostala ) {
	echo "WYNIK=PASS\n";
	exit( 0 );
}
echo "WYNIK=FAIL\n";
exit( 1 );
