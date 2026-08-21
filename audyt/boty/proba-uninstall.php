<?php
/**
 * Proba: co ZOSTAJE w wp_options po PELNYM odinstalowaniu ChatBota
 * (usun_dane = 1). Atrapa WordPressa — nie dotyka zadnej prawdziwej bazy.
 */
define( 'WP_UNINSTALL_PLUGIN', 'chatbot-live/chatbot-live.php' );

// Stan witryny tuz przed odinstalowaniem: wszystkie opcje, ktore wtyczka zapisuje.
$GLOBALS['opcje'] = array(
	'chatl_ustawienia'       => array( 'usun_dane' => 1 ),
	'chatl_klucz_api'        => 'sk-tajny',
	'chatl_wersja_schematu'  => '7',
	'chatl_blad_maila'       => array( 'kiedy' => 'x' ),
	'chatl_blad_ai'          => array( 'kiedy' => 'x' ),
	'chatl_blad_nonce'       => array( 'ile' => 3 ),
	'chatl_blad_bazy'        => array( 'ile' => 3 ),
	'chatl_rodo_sesje_wspolne' => array( 'abc' ),
	'chatl_pulapka'          => array( 'ile' => 12, 'ostatnia' => '2026-08-21 10:00:00', 'powod' => 'ukryte pole' ),
);
$GLOBALS['sql'] = array();

function get_option( $n, $d = false ) { return array_key_exists( $n, $GLOBALS['opcje'] ) ? $GLOBALS['opcje'][ $n ] : $d; }
function delete_option( $n ) { unset( $GLOBALS['opcje'][ $n ] ); return true; }
function delete_metadata() { return true; }

class Atrapa_wpdb {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public function query( $s ) { $GLOBALS['sql'][] = $s; return 0; }
	public function prepare( $s, ...$a ) { return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $s ), $a ); }
	public function esc_like( $t ) { return addcslashes( $t, '_%\\' ); }
}
$wpdb = new Atrapa_wpdb();
$GLOBALS['wpdb'] = $wpdb;

require __DIR__ . '/../../wtyczki/chatbot-live/uninstall.php';

echo "Opcje wtyczki POZOSTALE w wp_options po pelnym odinstalowaniu:\n";
$zostalo = array_keys( $GLOBALS['opcje'] );
if ( ! $zostalo ) {
	echo "  (zadnych)\nWYNIK: OK\n";
	exit( 0 );
}
foreach ( $zostalo as $k ) {
	echo "  - {$k} = " . str_replace( "\n", '', var_export( $GLOBALS['opcje'][ $k ], true ) ) . "\n";
}
echo "WYNIK: PADA — " . count( $zostalo ) . " opcji wtyczki przezylo odinstalowanie.\n";
exit( 1 );
