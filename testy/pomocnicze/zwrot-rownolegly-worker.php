<?php
/**
 * ROBOTNIK próby `zwrot-rownolegly-test.sh` (klasa K-M2). NIE uruchamiaj sam — steruje nim skrypt .sh.
 * Tryby (pierwszy argument `wp eval-file … <tryb> …`):
 *   zaloz                        → tworzy zamówienie testowe (mail_wyslany, 9900 gr)
 *   zwrot <refund_id> <kwota> [opoznij]  → księguje `refund.created`; z `opoznij` śpi 1,5 s
 *                                          tuż PRZED UPDATE-em sumy (filtr `query`), już PO odczycie —
 *                                          to jest okno wyścigu odczyt-zmiana-zapis, wymuszone deterministycznie
 *   odczyt                       → drukuje `zwrocono=<grosze>`
 *   sprzataj                     → kasuje zamówienie i zdarzenia
 */
global $wpdb;
$pz   = $wpdb->prefix . 'sklepag_zamowienia';
$pd   = $wpdb->prefix . 'sklepag_zdarzenia';
$sid  = 'cs_test_straznik_rownolegly';
$pi   = 'pi_test_straznik_rownolegly';
$tryb = isset( $args[0] ) ? (string) $args[0] : '';

if ( 'zaloz' === $tryb ) {
	$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
	if ( ! $produkt ) {
		echo "BLAD-SRODOWISKA: brak produktu\n";
		exit( 2 );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $pi ) . '%' ) );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$pz} (stripe_session_id, payment_intent_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona, produkt_plik_kupiony,
			 email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas, pobrania_limit, utworzono, zmieniono)
			 VALUES (%s, %s, %d, %s, %d, %s, %s, %d, 'PLN', 'mail_wyslany', 'zgoda', NOW(), 10, NOW(), NOW())",
			$sid, $pi, (int) $produkt->id, (string) $produkt->nazwa, 9900, (string) $produkt->plik_sciezka, 'straznik-rownolegly@example.test', 9900
		)
	);
	echo "zalozone\n";
	exit( 0 );
}
if ( 'zwrot' === $tryb ) {
	$rid = (string) $args[1];
	$am  = (int) $args[2];
	if ( isset( $args[3] ) && 'opoznij' === $args[3] ) {
		add_filter(
			'query',
			static function ( $q ) {
				if ( false !== strpos( $q, 'zwrocono_grosze' ) && 0 === strpos( ltrim( $q ), 'UPDATE' ) ) {
					usleep( 1500000 ); // 1,5 s: drugi robotnik zdąży odczytać i zapisać w tym oknie
				}
				return $q;
			}
		);
	}
	$ok = sklepag_zaksieguj_zwrot( 'refund.created', array( 'id' => $rid, 'payment_intent' => $pi, 'amount' => $am, 'status' => 'succeeded' ) );
	echo 'zwrot ' . $rid . ' ' . $am . ' -> ' . ( $ok ? 'true' : 'false' ) . "\n";
	exit( 0 );
}
if ( 'odczyt' === $tryb ) {
	echo 'zwrocono=' . (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE stripe_session_id=%s", $sid ) ) . "\n";
	exit( 0 );
}
if ( 'sprzataj' === $tryb ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	if ( $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE zamowienie_id=%d", $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $pi ) . '%' ) );
	echo "posprzatane\n";
	exit( 0 );
}
echo "nieznany tryb: $tryb\n";
exit( 2 );
