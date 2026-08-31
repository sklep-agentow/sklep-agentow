<?php
/**
 * Krok 1 anonimizacji RODO: sciezka szczesliwa — zapis sie udaje.
 *
 * ⛔ TEN PLIK TYLKO DRUKUJE. Werdykt liczy `rodo-anonimizacja-pomiar-skutku-test.sh`,
 *    bo `sklepag_obsluz_anonimizacje()` konczy sie `exit` po przekierowaniu: asercje
 *    postawione PO wywolaniu nigdy by sie nie wykonaly, a kod wyjscia i tak bylby
 *    zerowy (klasa 17 rejestru).
 *
 * Wolamy PRAWDZIWY handler, nie jego kopie — sonda powtarzajaca samo zapytanie
 * mierzylaby zapytanie, a nie sciezke.
 *
 * @package sklep-agentow-testy
 */

global $wpdb;

/* Handler zyje w pliku ladowanym tylko za `is_admin()`, a pod WP-CLI to falsz.
   Dociagamy TEN SAM plik produkcyjny — nie kopie, nie atrape. */
if ( ! function_exists( 'sklepag_obsluz_anonimizacje' ) ) {
	$plik_handlera = WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-zamowienie.php';
	if ( ! is_readable( $plik_handlera ) ) {
		echo "PRZYRZAD=BRAK-PLIKU-HANDLERA\n";
		exit( 2 );
	}
	require_once $plik_handlera;
}
if ( ! function_exists( 'sklepag_obsluz_anonimizacje' ) ) {
	echo "PRZYRZAD=HANDLER-NIEDOSTEPNY\n";
	exit( 2 );
}


$pz    = $wpdb->prefix . 'sklepag_zamowienia';
$teraz = current_time( 'mysql' );
$mail  = 'proba-rodo-' . wp_generate_password( 8, false ) . '@example.test';

$wpdb->insert(
	$pz,
	array(
		'stripe_session_id'     => 'cs_test_rodo_' . wp_generate_password( 12, false ),
		'produkt_id'            => 1,
		'produkt_nazwa_kupiona' => 'Zamowienie probne RODO',
		'email_kupujacego'      => $mail,
		'status'                => 'mail_wyslany',
		'token_pobrania'        => str_repeat( 'a', 64 ),
		/* ⛔ WAZNA 1 recenzji #234 r5: tresc maila niesie PELNA kopie danych do faktury
		   (zakup.php sklada blok „DANE DO FAKTURY" z $zam->dane_faktury i zapisuje calosc
		   do mail_tresc). Zasiew odtwarza ten uklad — anonimizacja musi wyczyscic OBA
		   miejsca, nie tylko kolumne dane_faktury. */
		'dane_faktury'          => "Firma Probna Sp. z o.o.\nNIP: 5260250274\nul. Probna 1, 00-001 Warszawa",
		'mail_tresc'            => "Dziekujemy za zakup!\n\nDANE DO FAKTURY (podane przy zakupie):\nFirma Probna Sp. z o.o.\nNIP: 5260250274\nul. Probna 1, 00-001 Warszawa\nFakture wystawimy w ustawowym terminie.",
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
$id = (int) $wpdb->insert_id;
if ( ! $id ) {
	echo "PRZYRZAD=NIE-UDALO-SIE-UTWORZYC-ZAMOWIENIA\n";
	exit( 2 );
}
echo 'ID=' . $id . "\n";
echo 'MAIL_PRZED=' . $mail . "\n";

/* Warunek wstepny WLASNEGO przyrzadu: kontrola musi UMIEC zobaczyc blad, ktorego sie boi.
   Czytamy z BAZY (nie z tablicy zasiewu), zeby dowiesc, ze dane faktury naprawde leza
   w tresci maila przed operacja — inaczej „PUSTY po" niczego by nie mierzylo. */
$zasiew = $wpdb->get_row( $wpdb->prepare( "SELECT mail_tresc, dane_faktury FROM {$pz} WHERE id = %d", $id ) ); // phpcs:ignore
echo 'MAIL_TRESC_PRZED=' . ( ( $zasiew && false !== strpos( (string) $zasiew->mail_tresc, 'NIP: 5260250274' ) ) ? 'ZAWIERA-FAKTURE' : 'BEZ-FAKTURY' ) . "\n";

$admini = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $admini ) ) {
	echo "PRZYRZAD=BRAK-ADMINA\n";
	exit( 2 );
}
wp_set_current_user( $admini[0]->ID );

$nonce                 = wp_create_nonce( 'sklepag_anonimizuj' );
$_GET['zamowienie']    = $id;
$_GET['_wpnonce']      = $nonce;
$_REQUEST['_wpnonce']  = $nonce;

add_filter(
	'wp_redirect',
	static function ( $lokalizacja ) {
		echo 'REDIRECT=' . $lokalizacja . "\n";
		return false;
	},
	99
);

/* Priorytet 99, nie 1: filtr zwracajacy WARTOSC rozstrzyga OSTATNI, a WP-CLI
   podklada wlasny handler — z niskim priorytetem nasz przegrywa. */
add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $wiadomosc ) {
			echo 'WPDIE=' . wp_strip_all_tags( is_wp_error( $wiadomosc ) ? $wiadomosc->get_error_message() : (string) $wiadomosc ) . "\n";
			exit( 0 );
		};
	},
	99
);

sklepag_obsluz_anonimizacje();
