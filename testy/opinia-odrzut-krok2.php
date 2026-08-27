<?php
/**
 * KROK 2 bliźniaka: sprawdź przechowalnię i wyrenderuj POWRÓT na formularz (`blad=1`).
 *
 * ⛔ Kod wyjścia tego pliku nic nie znaczy (renderer kończy `exit`) — wynik czyta
 * `testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh` z TREŚCI wypisanej niżej
 * oraz z HTML-a formularza, który leci na standardowe wyjście.
 */

require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/opinie.php';

$stan = get_option( 'sklepag_test_odrzut_opinii' );
if ( ! is_array( $stan ) ) {
	echo "KROK2: BRAK STANU Z KROKU 1\n";
	exit( 1 );
}

$klucz = 'sklepag_szkic_opinii_' . md5( (int) $stan['zam_id'] . '|' . $stan['token'] );
$szkic = get_transient( $klucz );

echo ( is_array( $szkic ) ? 'KROK2-OK: ' : 'KROK2-BLAD: ' ) . "przechowalnia po odrzucie istnieje\n";
echo ( is_array( $szkic ) && isset( $szkic['tresc'] ) && $szkic['tresc'] === $stan['tresc'] ? 'KROK2-OK: ' : 'KROK2-BLAD: ' ) . "przechowalnia niesie DOKLADNIE napisana tresc\n";
echo ( is_array( $szkic ) && ! array_key_exists( 'zgoda', $szkic ) ? 'KROK2-OK: ' : 'KROK2-BLAD: ' ) . "zgoda NIE jest odkladana (musi byc swiezym gestem)\n";
echo ( is_array( $szkic ) && isset( $szkic['gwiazdki'] ) && 4 === (int) $szkic['gwiazdki'] ? 'KROK2-OK: ' : 'KROK2-BLAD: ' ) . "ocena zapamietana taka, jaka byla wybrana\n";

echo "KROK2: renderuje powrot na formularz z blad=1 (skonczy sie exit)\n";
echo "<<<FORMULARZ\n";
$_POST = array();
$_GET  = array(
	'z'    => (int) $stan['zam_id'],
	't'    => $stan['token'],
	'blad' => '1',
);
sklepag_obsluz_ocen_formularz();
