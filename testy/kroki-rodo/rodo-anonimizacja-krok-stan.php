<?php
/**
 * Krok 3: odczyt STANU PO operacji + sprzatanie zamowienia probnego.
 *
 * Osobny proces, bo poprzednie kroki koncza sie `exit` wewnatrz handlera.
 * Wywolanie: ... wp eval-file - <id> [--] , id trafia do $args[0].
 *
 * @package sklep-agentow-testy
 */

global $wpdb;

$id = isset( $args[0] ) ? (int) $args[0] : 0;
if ( ! $id ) {
	echo "PRZYRZAD=BRAK-ID\n";
	exit( 2 );
}

$pz  = $wpdb->prefix . 'sklepag_zamowienia';
$zam = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego, usunieto_dnia, token_pobrania FROM {$pz} WHERE id = %d", $id ) ); // phpcs:ignore
if ( ! $zam ) {
	echo "PRZYRZAD=BRAK-ZAMOWIENIA\n";
	exit( 2 );
}

echo 'MAIL_PO=' . ( '' === (string) $zam->email_kupujacego ? 'PUSTY' : 'ZOSTAL' ) . "\n";
echo 'USUNIETO_DNIA=' . ( empty( $zam->usunieto_dnia ) ? 'BRAK' : 'JEST' ) . "\n";
echo 'TOKEN_POBRANIA=' . ( '' === (string) $zam->token_pobrania ? 'PUSTY' : 'ZOSTAL' ) . "\n";

$tz    = $wpdb->prefix . 'sklepag_zdarzenia';
$rodz  = $wpdb->get_col( $wpdb->prepare( "SELECT typ FROM {$tz} WHERE zamowienie_id = %d", $id ) ); // phpcs:ignore
$rodz  = array_map( 'strval', (array) $rodz );
echo 'ZDARZENIE_ANONIMIZACJA=' . ( in_array( 'anonimizacja', $rodz, true ) ? 'JEST' : 'BRAK' ) . "\n";
echo 'ZDARZENIE_ANONIMIZACJA_BLAD=' . ( in_array( 'anonimizacja_blad', $rodz, true ) ? 'JEST' : 'BRAK' ) . "\n";

/* Sprzatanie: test stawia WLASNE warunki wstepne i po sobie zmywa — stos jest wspolny. */
$wpdb->delete( $tz, array( 'zamowienie_id' => $id ) );
$wpdb->delete( $wpdb->prefix . 'sklepag_opinie', array( 'zamowienie_id' => $id ) );
$wpdb->delete( $pz, array( 'id' => $id ) );
echo "SPRZATNIETE=TAK\n";
