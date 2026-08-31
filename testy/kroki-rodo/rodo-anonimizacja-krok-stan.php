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
$zam = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego, usunieto_dnia, token_pobrania, mail_tresc, dane_faktury FROM {$pz} WHERE id = %d", $id ) ); // phpcs:ignore
if ( ! $zam ) {
	echo "PRZYRZAD=BRAK-ZAMOWIENIA\n";
	exit( 2 );
}

echo 'MAIL_PO=' . ( '' === (string) $zam->email_kupujacego ? 'PUSTY' : 'ZOSTAL' ) . "\n";
echo 'USUNIETO_DNIA=' . ( empty( $zam->usunieto_dnia ) ? 'BRAK' : 'JEST' ) . "\n";
echo 'TOKEN_POBRANIA=' . ( '' === (string) $zam->token_pobrania ? 'PUSTY' : 'ZOSTAL' ) . "\n";
/* WAZNA 1 #234 r5: oryginal tresci maila niesie kopie danych faktury — (string) NULL = ''. */
echo 'MAIL_TRESC=' . ( '' === (string) $zam->mail_tresc ? 'PUSTY' : 'ZOSTAL' ) . "\n";
echo 'DANE_FAKTURY=' . ( '' === (string) $zam->dane_faktury ? 'PUSTY' : 'ZOSTAL' ) . "\n";

$tz    = $wpdb->prefix . 'sklepag_zdarzenia';
$rodz  = $wpdb->get_col( $wpdb->prepare( "SELECT typ FROM {$tz} WHERE zamowienie_id = %d", $id ) ); // phpcs:ignore
$rodz  = array_map( 'strval', (array) $rodz );
echo 'ZDARZENIE_ANONIMIZACJA=' . ( in_array( 'anonimizacja', $rodz, true ) ? 'JEST' : 'BRAK' ) . "\n";
echo 'ZDARZENIE_ANONIMIZACJA_BLAD=' . ( in_array( 'anonimizacja_blad', $rodz, true ) ? 'JEST' : 'BRAK' ) . "\n";

/* ⛔ Slady adresu w CALEJ tabeli zdarzen — takze w wierszach BEZ `zamowienie_id`
   Krok nie zna adresu, wiec szuka po markerze testowym:
   wszystkie adresy tego testu koncza sie na @example.test. */
/* ⛔ Wpis OBCEGO kupujacego jest WYKLUCZONY z licznika: jego adres tez konczy sie markerem
   testowym, ale MA zostac — to inny czlowiek. Liczenie go jako naszego sladu sprawialoby,
   ze licznik nigdy nie zejdzie do zera, niezaleznie od tego, czy naprawa dziala. */
$slady = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE typ NOT IN ('proba_obcy','proba_obcy_rfc') AND opis LIKE %s", '%' . $wpdb->esc_like( '@example.test' ) . '%' ) ); // phpcs:ignore
echo 'SLADY_W_DZIENNIKU=' . $slady . "\n";

/* ⛔ Wpis OBCEGO kupujacego (adres zawierajacy nasz jako koncowke) MUSI przezyc nietkniety. */
$obcy = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE typ = 'proba_obcy' AND opis LIKE %s", '%' . $wpdb->esc_like( '@example.test' ) . '%' ) ); // phpcs:ignore
echo 'WPIS_OBCEGO_CALY=' . $obcy . "\n";
$obcy_rfc = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE typ = 'proba_obcy_rfc' AND opis LIKE %s", '%' . $wpdb->esc_like( '@example.test' ) . '%' ) ); // phpcs:ignore
echo 'WPIS_OBCEGO_RFC_CALY=' . $obcy_rfc . "\n";

/* Sprzatanie: test stawia WLASNE warunki wstepne i po sobie zmywa — stos jest wspolny. */
$wpdb->delete( $tz, array( 'zamowienie_id' => $id ) );
/* ⛔ TAKZE wiersze BEZ `zamowienie_id` z markerem testowym — inaczej slady zostawione przez
   kalibracje na starym kodzie przezyja przebieg i zatruja NASTEPNY pomiar (zlapane na sobie). */
/* ⛔ Sprzatamy po TYPIE, nie po markerze w tresci: udana anonimizacja PODMIENIA adres na
   znacznik, wiec wiersz przestaje zawierac marker i sprzatanie po tresci przestaje go widziec.
   Kazdy UDANY przebieg zostawialby wtedy wiersz na wspolnym stosie. */
$wpdb->query( "DELETE FROM {$tz} WHERE zamowienie_id IS NULL AND typ IN ('eksport_csv','proba_obcy','proba_obcy_rfc','proba_rodo')" ); // phpcs:ignore
$wpdb->delete( $wpdb->prefix . 'sklepag_opinie', array( 'zamowienie_id' => $id ) );
$wpdb->delete( $pz, array( 'id' => $id ) );
echo "SPRZATNIETE=TAK\n";
