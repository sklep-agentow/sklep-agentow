<?php
/**
 * ChatBot LIVE — operator, który pisze odpowiedź dłużej niż CHATL_PRZEJECIE_CISZA, NIE traci tekstu.
 * Test-strażnik: EXIT 1 przy porażce.
 *
 * Pytanie: czy kliknięcie „Wyślij” po długim pisaniu oddaje wiadomość gościowi (operator nie milczał —
 * pisał), a gdy rozmowa naprawdę wróciła już do bota, czy treść czeka w polu zamiast zginąć.
 * Mierzymy SKUTEK: wiersz wiadomości w bazie, stan sesji, znacznik aktywności, przechowalnia szkicu.
 *
 * Uruchomienie (stos warsztatowy): bash narzedzia-qa/test-php.sh chatbot-operator-wysylka-po-ciszy-test
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Panel operatora wczytuje się tylko w kontekście admina (is_admin()), a wp eval-file biegnie poza nim.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/admin.php';
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/operator.php';

global $wpdb, $bledy; // ⛔ `global` także dla licznika: pod `wp eval-file` plik biegnie w funkcji (klasa 158).
$bledy = 0;
$ts    = $wpdb->prefix . 'chatl_sesje';
$tr    = $wpdb->prefix . 'chatl_rozmowy';
$sesja = 'opciszatest' . strtolower( wp_generate_password( 12, false ) );

function chatl_test_ok( $opis, $warunek, $szczegol = '' ) {
	global $bledy;
	if ( $warunek ) {
		echo "  OK   {$opis}\n";
	} else {
		++$bledy;
		echo "  BLAD {$opis}" . ( '' !== $szczegol ? " — {$szczegol}" : '' ) . "\n";
	}
}

/**
 * Wywołanie handlera admin-post z przechwyconym przekierowaniem (wzór: opinie-mechanizm-test).
 *
 * @return string 'REDIRECT:<url>' albo '' gdy handler nie przekierował.
 */
function chatl_test_wyslij( $sesja, $tresc ) {
	$_POST['sesja']       = $sesja;
	$_POST['tresc']       = $tresc;
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'chatl_przejecie' );
	$przekieruj           = function ( $url ) {
		throw new Exception( 'REDIRECT:' . $url );
	};
	add_filter( 'wp_redirect', $przekieruj );
	$wyjscie = '';
	try {
		chatl_post_operator_wyslij();
	} catch ( Exception $e ) {
		$wyjscie = $e->getMessage();
	}
	remove_filter( 'wp_redirect', $przekieruj );
	unset( $_POST['sesja'], $_POST['tresc'], $_REQUEST['_wpnonce'] );
	return $wyjscie;
}

echo "=== CHATBOT: operator wysyła po ciszy dłuższej niż limit (sesja {$sesja}) ===\n";
$admini = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
chatl_test_ok( 'warunek testu: stos ma administratora', ! empty( $admini ) );
wp_set_current_user( (int) $admini[0]->ID );

$teraz  = current_time( 'mysql' );
$dawno  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - CHATL_PRZEJECIE_CISZA - 60 ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->insert( $ts, array( 'sesja' => $sesja, 'stan' => 'czlowiek', 'klucz_hash' => 'test', 'operator' => (int) $admini[0]->ID, 'przejeto' => $dawno, 'operator_akt' => $dawno, 'utworzono' => $teraz, 'aktywnosc' => $teraz ) );

try {
	// S4: rozmowa w bazie PRZY CZŁOWIEKU, ale operator „milczał” dłużej niż limit, bo pisał odpowiedź.
	$przed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s AND rola = 'operator'", $sesja ) );
	$w1    = chatl_test_wyslij( $sesja, 'Odpowiedź pisana przez 6 minut' );
	chatl_test_ok( 'wysyłka po ciszy: przekierowanie do rozmowy BEZ komunikatu błędu', 0 === strpos( $w1, 'REDIRECT:' ) && false !== strpos( $w1, 'sesja=' . $sesja ) && false === strpos( $w1, 'chatl_blad' ), $w1 );
	$po = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s AND rola = 'operator'", $sesja ) );
	chatl_test_ok( 'SKUTEK: wiadomość operatora trafiła do bazy (gość ją zobaczy)', $po === $przed + 1, "przed={$przed} po={$po}" );
	$s = $wpdb->get_row( $wpdb->prepare( "SELECT stan, operator_akt FROM {$ts} WHERE sesja = %s", $sesja ), ARRAY_A );
	chatl_test_ok( 'SKUTEK: rozmowa ZOSTAJE przy człowieku', is_array( $s ) && 'czlowiek' === $s['stan'], wp_json_encode( $s ) );
	chatl_test_ok( 'SKUTEK: znacznik aktywności odświeżony wysyłką (cisza liczona od teraz)', is_array( $s ) && ( current_time( 'timestamp' ) - strtotime( $s['operator_akt'] ) ) < 30, wp_json_encode( $s ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	chatl_test_ok( 'stan przez chatl_stan_rozmowy(): czlowiek (nic nie wygasło)', 'czlowiek' === chatl_stan_rozmowy( $sesja ) );

	// Rozmowa NAPRAWDĘ oddana botowi (wcześniejsze odpytanie gościa): odmowa, ale treść czeka w polu.
	$wpdb->update( $ts, array( 'stan' => 'bot' ), array( 'sesja' => $sesja ) );
	$tresc2 = 'Ten tekst nie może zginąć';
	$w2     = chatl_test_wyslij( $sesja, $tresc2 );
	chatl_test_ok( 'rozmowa przy bocie: odmowa z komunikatem, przekierowanie DO TEJ rozmowy', 0 === strpos( $w2, 'REDIRECT:' ) && false !== strpos( $w2, 'chatl_blad' ) && false !== strpos( $w2, 'sesja=' . $sesja ), $w2 );
	chatl_test_ok( 'SKUTEK: treść czeka w przechowalni szkicu (operator zobaczy ją w polu)', $tresc2 === (string) get_transient( chatl_klucz_szkicu_operatora( $sesja ) ) );
	$po2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s AND rola = 'operator'", $sesja ) );
	chatl_test_ok( 'SKUTEK: nic nie dopisało się do rozmowy przy bocie', $po2 === $po, "po={$po} po2={$po2}" );
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ts} WHERE sesja = %s", $sesja ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja = %s", $sesja ) );
	delete_transient( chatl_klucz_szkicu_operatora( $sesja ) );
	wp_set_current_user( 0 );
}
// phpcs:enable

if ( $bledy > 0 ) {
	echo "NIEPOWODZENIE: {$bledy} asercji nie przeszło\n";
	exit( 1 );
}
echo "WSZYSTKIE ASERCJE PRZESZLY (skutek mierzony w bazie i w przechowalni)\n";
exit( 0 );
