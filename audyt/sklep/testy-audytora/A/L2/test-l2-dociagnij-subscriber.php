<?php
// admin-dashboard.php jest dołączany przez sklep-agentow.php TYLKO gdy is_admin() —
// w kontekście wp-cli eval-file to fałsz (stała WP_ADMIN=false z bootstrapu CLI,
// nie da się jej przełączyć w locie), więc funkcja handlera nie istnieje bez
// bezpośredniego require. To DOŁĄCZENIE PLIKU, nie obejście żadnej bramki kodu —
// sam handler i jego wnętrze wykonują się identycznie jak w realnym wp-admin.
require_once '/var/www/html/wp-content/plugins/sklep-agentow/includes/admin-dashboard.php';
/**
 * L2-01: wywołanie admin_post_sklepag_dociagnij w kontekście SUBSCRIBERA (ID 63),
 * z noncem WYGENEROWANYM I ZWERYFIKOWANYM w TYM SAMYM procesie (unika pułapki
 * "nonce z wp eval nie pasuje do sesji z ciasteczka" — tu nonce i weryfikacja
 * dzielą ten sam kontekst usera, więc token sesji jest spójny po obu stronach).
 * Cel: sprawdzić, czy sklepag_obsluz_dociagnij() ma bramkę current_user_can().
 */
add_filter( 'wp_die_handler', function () {
	return function ( $message, $title = '', $args = array() ) {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
		}
		throw new Exception( 'WP_DIE: ' . wp_strip_all_tags( (string) $message ) );
	};
} );

wp_set_current_user( 63 );
echo "user=" . wp_get_current_user()->user_login . " id=" . get_current_user_id() . "\n";
echo "current_user_can(manage_options)=" . ( current_user_can( 'manage_options' ) ? 'YES' : 'NO' ) . "\n";

$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_dociagnij' );
$_POST                = $_REQUEST;
echo "nonce_verify=" . var_export( wp_verify_nonce( $_REQUEST['_wpnonce'], 'sklepag_dociagnij' ), true ) . "\n";

$przed_opcja = get_option( 'sklepag_reconciliation_ostatnia' );
echo "opcja_przed=" . $przed_opcja . "\n";

try {
	sklepag_obsluz_dociagnij();
	echo "HANDLER ZAKONCZYL SIE BEZ WYJATKU (nie powinno sie zdarzyc - jest exit w kodzie po sukcesie)\n";
} catch ( Exception $e ) {
	echo "PRZECHWYCONO: " . $e->getMessage() . "\n";
}

$po_opcja = get_option( 'sklepag_reconciliation_ostatnia' );
echo "opcja_po=" . $po_opcja . "\n";
echo "OPCJA_ZMIENIONA=" . ( $po_opcja !== $przed_opcja ? 'TAK - HANDLER SIE WYKONAL' : 'NIE' ) . "\n";
