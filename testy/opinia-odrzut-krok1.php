<?php
/**
 * KROK 1 bliźniaka: zasiej opinię i wywołaj ODRZUT formularza kupującego.
 *
 * ⛔ Ten plik NIE jest testem sam w sobie i jego kod wyjścia nic nie znaczy — handler
 * opinii kończy się `exit`, więc każde wyjście jest zerowe niezależnie od wyniku.
 * Sędzią jest `testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh`, który czyta TREŚĆ.
 *
 * Stan między krokami idzie przez opcję `sklepag_test_odrzut_opinii` (kasuje ją krok 3).
 */

require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/opinie.php';
// phpcs:disable WordPress.DB -- test na prawdziwej bazie: własne wiersze, sprzątane w kroku 3.

global $wpdb;
$tp    = $wpdb->prefix . 'sklepag_produkty';
$slug  = 'sklepag-test-odrzut-opinii';
$teraz = current_time( 'mysql' );

$wpdb->delete( $tp, array( 'slug' => $slug ) );
$wpdb->insert(
	$tp,
	array(
		'slug'        => $slug,
		'nazwa'       => 'Produkt testowy odrzutu opinii',
		'opis_krotki' => '',
		'opis_pelny'  => '',
		'cena_grosze' => 1000,
		'waluta'      => 'PLN',
		'aktywny'     => 0,
		'utworzono'   => $teraz,
	)
);
$prod_id = (int) $wpdb->insert_id;
$zam_id  = 9900301;
$token   = sklepag_opinia_zalazek( $zam_id, $prod_id );
$tresc   = rtrim( 'Bot odpisuje moim klientom w nocy i to jest cala roznica. ' . str_repeat( 'Napisalem to raz i nie chce pisac drugi. ', 8 ) );

update_option(
	'sklepag_test_odrzut_opinii',
	array(
		'prod_id' => $prod_id,
		'zam_id'  => $zam_id,
		'token'   => $token,
		'tresc'   => $tresc,
	),
	false
);

echo "KROK1: zasiano opinie dla zamowienia " . (int) $zam_id . "\n";
echo "KROK1: dlugosc tresci = " . strlen( $tresc ) . " znakow\n";

// Imię z samych białych znaków przechodzi `required` przeglądarki, a po sanityzacji jest
// puste — handler zwraca „pola" i idzie ścieżką odrzutu, dokładnie jak u kupującego.
$_GET  = array();
$_POST = array(
	'action'           => 'sklepag_ocen_zapisz',
	'z'                => $zam_id,
	't'                => $token,
	'sklepag_imie'     => '   ',
	'sklepag_tytul'    => 'Warto',
	'sklepag_tresc'    => $tresc,
	'sklepag_gwiazdki' => '4',
	'sklepag_zgoda'    => '1',
);
echo "KROK1: wolam handler zapisu opinii (skonczy sie exit)\n";
sklepag_obsluz_ocen_zapis();
