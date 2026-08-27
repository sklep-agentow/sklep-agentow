<?php
/**
 * KROK 3 bliźniaka: sprawdź jednorazowość przechowalni i POSPRZĄTAJ po przebiegu.
 *
 * Ten krok kończy się normalnie, więc jego kod wyjścia JEST prawdziwy — ale sędzią
 * całości i tak zostaje `testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh`.
 */

require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/opinie.php';
// phpcs:disable WordPress.DB -- test na prawdziwej bazie: kasujemy wyłącznie własne wiersze.

$bledy = 0;
$stan  = get_option( 'sklepag_test_odrzut_opinii' );
if ( ! is_array( $stan ) ) {
	echo "KROK3: BRAK STANU Z KROKU 1\n";
	exit( 1 );
}

$klucz = 'sklepag_szkic_opinii_' . md5( (int) $stan['zam_id'] . '|' . $stan['token'] );
$po    = get_transient( $klucz );
if ( false === $po ) {
	echo "KROK3-OK: przechowalnia skasowana po pierwszym odczycie\n";
} else {
	echo "KROK3-BLAD: przechowalnia przezyla odczyt (szkic wrocilby drugi raz)\n";
	++$bledy;
}

// Powrót BEZ `blad=1` nie ma prawa nakładać szkicu — sprawdzamy to na świeżo odłożonym
// wpisie, żeby nie orzekać na podstawie tego, że przechowalnia jest już pusta.
set_transient( $klucz, array( 'tresc' => 'PORZUCONA TRESC KONTROLNA' ), 5 * MINUTE_IN_SECONDS );
echo "KROK3: renderuje wejscie BEZ blad=1 (skonczy sie exit)\n";
echo "<<<FORMULARZ-BEZ-BLEDU\n";

register_shutdown_function(
	static function () use ( $klucz, $stan, $bledy ) {
		global $wpdb;
		delete_transient( $klucz );
		delete_option( 'sklepag_test_odrzut_opinii' );
		$wpdb->delete( $wpdb->prefix . 'sklepag_opinie', array( 'zamowienie_id' => (int) $stan['zam_id'] ) );
		$wpdb->delete( $wpdb->prefix . 'sklepag_produkty', array( 'id' => (int) $stan['prod_id'] ) );
		echo "\nKROK3: posprzatane (opinia, produkt, przechowalnia, opcja stanu)\n";
		echo 0 === $bledy ? "KROK3-KONIEC-OK\n" : "KROK3-KONIEC-BLEDY\n";
	}
);

$_POST = array();
$_GET  = array(
	'z' => (int) $stan['zam_id'],
	't' => $stan['token'],
);
sklepag_obsluz_ocen_formularz();
