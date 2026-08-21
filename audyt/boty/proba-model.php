<?php
// Proba: czy "Przetestuj klucz" pyta TYM modelem, ktorego uzywa bot?
// Zadne zapisy do bazy: klucz i ustawienia podstawione filtrami w pamieci,
// wywolania HTTP przechwycone przez pre_http_request (nic nie wychodzi na zewnatrz).
$GLOBALS['zebrane'] = array();

add_filter( 'option_chatl_ustawienia', function ( $v ) {
	$v = is_array( $v ) ? $v : array();
	$v['ai_model'] = 'model-ktorego-dostawca-nie-zna';
	return $v;
}, 99 );
add_filter( 'default_option_chatl_ustawienia', function ( $v ) {
	return array( 'ai_model' => 'model-ktorego-dostawca-nie-zna' );
}, 99 );
add_filter( 'option_chatl_klucz_api', function () { return 'sk-atrapa'; }, 99 );
add_filter( 'default_option_chatl_klucz_api', function () { return 'sk-atrapa'; }, 99 );

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$b = json_decode( (string) $args['body'], true );
	$GLOBALS['zebrane'][] = isset( $b['model'] ) ? $b['model'] : '(brak)';
	return array(
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body'     => wp_json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => 'ok' ) ), 'usage' => array() ) ),
		'headers'  => array(),
		'cookies'  => array(),
	);
}, 10, 3 );

echo "ustawienie wlasciciela (ai_model): " . chatl_ustawienia()['ai_model'] . "\n";
echo "chatl_ai_model() [tym pyta bot]:   " . chatl_ai_model() . "\n";

chatl_ai_zapytaj( 'jakies pytanie goscia' );
echo "model w ZWYKLYM wywolaniu bota:    " . $GLOBALS['zebrane'][0] . "\n";

// --- czy przycisk kasuje ostrzezenie o awarii AI? ---
$kopia = get_option( 'chatl_blad_ai' );
update_option( 'chatl_blad_ai', array( 'kiedy' => '2026-08-21 10:00:00', 'kod' => 404, 'tresc' => 'PROBA AUDYTU' ), false );
echo "znacznik chatl_blad_ai PRZED:      " . ( get_option( 'chatl_blad_ai' ) ? 'JEST (pasek widoczny)' : 'brak' ) . "\n";

$wynik = chatl_ai_test_klucza();
echo "model w 'Przetestuj klucz':        " . $GLOBALS['zebrane'][1] . "\n";
echo "wynik przycisku:                   " . ( $wynik['ok'] ? 'OK' : 'BLAD' ) . " / " . $wynik['komunikat'] . "\n";
echo "znacznik chatl_blad_ai PO:         " . ( get_option( 'chatl_blad_ai' ) ? 'JEST' : 'SKASOWANY (pasek znikl)' ) . "\n";

// sprzatanie: przywracamy stan sprzed proby
if ( false === $kopia ) { delete_option( 'chatl_blad_ai' ); } else { update_option( 'chatl_blad_ai', $kopia, false ); }
echo "sprzatanie, chatl_blad_ai wrocil do: " . var_export( get_option( 'chatl_blad_ai' ), true ) . "\n";

echo ( $GLOBALS['zebrane'][0] !== $GLOBALS['zebrane'][1] ) ? "\nWYNIK: PADA — przycisk testuje INNY model niz bot.\n" : "\nWYNIK: OK — ten sam model.\n";
