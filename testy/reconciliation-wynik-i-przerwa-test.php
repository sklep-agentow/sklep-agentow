<?php
/**
 * Reconciliation — strażnik WYNIKU i PRZERWY po sprawdzeniu (0.9.25, po K10). Kod wyjścia 1 przy porażce.
 *
 * Co groziło (K10, 30.08): tester kliknął „Dociągnij zaległe teraz” 71 s po zapłacie — sesja młodsza niż
 * 120 s została pominięta BEZ SŁOWA, cron hostingu nie chodził (zepsuta pętla zwrotna) i opłacony zakup
 * „wisiał” 20 minut. Do tego ORDER BY utworzono LIMIT 20: dwadzieścia porzuconych koszyków (kliknął „Kup”,
 * nie zapłacił; u Stripe „open/unpaid” do 24 h) blokowało nowszą OPŁACONĄ sesję na dobę.
 *
 * Strażnik (Stripe ATRAPOWANE filtrem `pre_http_request` — bez sieci, bez klucza):
 *  R1 wynik liczy sesję młodszą niż 2 min jako POMINIĘTĄ (i jej nie pyta),
 *  R2 sesja stara, u Stripe otwarta i nieopłacona → sprawdzona 1×, dostaje przerwę (transient),
 *  R3 drugi bieg od razu → NIE pyta Stripe o tę samą sesję (0 zapytań), ale liczy ją jako otwartą (Pulpit nie mówi „0·0·0·0”),
 *  R4 22 porzucone starsze sesje + 1 opłacona nowsza → opłacona domknięta w PIERWSZYM biegu
 *     (na starym kodzie: LIMIT 20 najstarszych = opłacona nietknięta),
 *  R5 funkcja zwraca tablicę z czterema licznikami (przycisk w panelu ją pokazuje).
 *
 * Uruchomienie:
 *   docker cp testy/reconciliation-wynik-i-przerwa-test.php strona_wp_wordpress:/tmp/rw.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/rw.php
 */

$GLOBALS['sklepag_test_bledy'] = 0;
function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_test_bledy'];
	}
}

global $wpdb;
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pf = 'cs_test_RWTEST_';

// Atrapa Stripe: odpowiedź zależy od id sesji. `$GLOBALS['rw_zapytania']` liczy wywołania.
$GLOBALS['rw_zapytania'] = 0;
$GLOBALS['rw_oplacone']  = array();
$atrapa                  = function ( $pre, $args, $url ) use ( $pf ) {
	if ( false === strpos( $url, '/v1/checkout/sessions/' . $pf ) ) {
		return $pre;
	}
	++$GLOBALS['rw_zapytania'];
	$id       = rawurldecode( substr( $url, strrpos( $url, '/' ) + 1 ) );
	$oplacona = in_array( $id, $GLOBALS['rw_oplacone'], true );
	$body     = array(
		'id'               => $id,
		'status'           => $oplacona ? 'complete' : 'open',
		'payment_status'   => $oplacona ? 'paid' : 'unpaid',
		'amount_total'     => 100,
		'currency'         => 'pln',
		'customer_details' => array( 'email' => 'rw-test@example.test' ),
		'metadata'         => array( 'zgoda_czas' => gmdate( 'Y-m-d H:i:s' ) ),
	);
	return array(
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'body'     => wp_json_encode( $body ),
		'headers'  => array(),
	);
};
add_filter( 'pre_http_request', $atrapa, 10, 3 );

$sprzatanie = function () use ( $wpdb, $ps, $pz, $pf ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id LIKE %s", $wpdb->esc_like( $pf ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id LIKE %s", $wpdb->esc_like( $pf ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_sklepag_recon_%' ) ) as $o ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_transient( substr( $o, strlen( '_transient_' ) ) );
	}
};
register_shutdown_function( $sprzatanie );
$sprzatanie();

// Izolacja od WSPÓLNEGO stosu: cudze sesje „utworzone” (E2E, próby ręczne) dostają przerwę na czas testu,
// żeby liczniki mierzyły wyłącznie sesje tego testu, a atrapa nie musiała udawać Stripe dla obcych id.
$granica_t = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 120 );
$obce      = $wpdb->get_col( $wpdb->prepare( "SELECT stripe_session_id FROM {$ps} WHERE stan='utworzona' AND utworzono < %s AND stripe_session_id NOT LIKE %s", $granica_t, $wpdb->esc_like( $pf ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
foreach ( $obce as $sid ) {
	set_transient( 'sklepag_recon_' . md5( $sid ), 1, 15 * MINUTE_IN_SECONDS );
}
$n_obce     = count( $obce ); // obce z przerwą liczą się teraz jako „otwarte” — oczekiwania niżej to uwzględniają
$mlode_obce = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ps} WHERE stan='utworzona' AND utworzono >= %s", gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 120 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

$produkt_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}sklepag_produkty WHERE aktywny=1 ORDER BY id LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
if ( ! $produkt_id ) {
	echo "  BLAD brak aktywnego produktu na stosie\n";
	exit( 1 );
}
$teraz = strtotime( current_time( 'mysql' ) );
$wstaw = function ( $sid, $wiek_s ) use ( $wpdb, $ps, $produkt_id, $teraz ) {
	$wpdb->insert(
		$ps,
		array(
			'stripe_session_id' => $sid,
			'produkt_id'        => $produkt_id,
			'stan'              => 'utworzona',
			'utworzono'         => gmdate( 'Y-m-d H:i:s', $teraz - $wiek_s ),
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

// --- R1 + R2: jedna młoda (60 s), jedna stara (600 s) otwarta/nieopłacona ---
$wstaw( $pf . 'MLODA', 60 );
$wstaw( $pf . 'STARA', 600 );
$w = sklepag_dociagnij_zalegle( 0 );
sprawdz( 'R5 funkcja zwraca tablicę z 5 licznikami', is_array( $w ) && isset( $w['domkniete'], $w['pominiete_mlode'], $w['sprawdzone_otwarte'], $w['z_przerwa'], $w['bledy'] ), wp_json_encode( $w ) );
sprawdz( 'R1 młodsza niż 2 min = pominięta (policzona, nie pytana)', ( 1 + $mlode_obce ) === (int) ( $w['pominiete_mlode'] ?? -1 ) && 1 === $GLOBALS['rw_zapytania'], 'zapytań do Stripe: ' . $GLOBALS['rw_zapytania'] . ', młode obce: ' . $mlode_obce );
sprawdz( 'R2 stara otwarta/nieopłacona = sprawdzona 1× i ma przerwę (obce z przerwą w OSOBNYM kubełku)', 1 === (int) ( $w['sprawdzone_otwarte'] ?? -1 ) && $n_obce === (int) ( $w['z_przerwa'] ?? -1 ) && false !== get_transient( 'sklepag_recon_' . md5( $pf . 'STARA' ) ), 'otwarte=' . (int) ( $w['sprawdzone_otwarte'] ?? -1 ) . ' z_przerwa=' . (int) ( $w['z_przerwa'] ?? -1 ) . ' obce=' . $n_obce );

// --- R3: drugi bieg od razu — bez zapytania o STARA ---
$GLOBALS['rw_zapytania'] = 0;
$w2                      = sklepag_dociagnij_zalegle( 0 );
sprawdz( 'R3 drugi bieg nie pyta ponownie o sesję z przerwą i liczy ją w kubełku „z przerwą”', 0 === $GLOBALS['rw_zapytania'] && 0 === (int) ( $w2['sprawdzone_otwarte'] ?? -1 ) && ( 1 + $n_obce ) === (int) ( $w2['z_przerwa'] ?? -1 ), 'zapytań: ' . $GLOBALS['rw_zapytania'] . ' z_przerwa: ' . (int) ( $w2['z_przerwa'] ?? -1 ) );

// --- R4: 22 porzucone STARSZE + 1 opłacona NOWSZA (ale > 2 min) — opłacona domknięta od razu ---
$sprzatanie();
for ( $i = 1; $i <= 22; $i++ ) {
	$wstaw( sprintf( '%sPORZUCONA%02d', $pf, $i ), 3600 + $i );
}
$wstaw( $pf . 'OPLACONA', 300 );
$GLOBALS['rw_oplacone']  = array( $pf . 'OPLACONA' );
$GLOBALS['rw_zapytania'] = 0;
$w4                      = sklepag_dociagnij_zalegle( 0 );
$zam                     = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$pz} WHERE stripe_session_id=%s", $pf . 'OPLACONA' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
sprawdz( 'R4 opłacona za 22 porzuconymi domknięta w PIERWSZYM biegu', 1 === (int) ( $w4['domkniete'] ?? 0 ) && $zam && in_array( $zam->status, array( 'oplacone', 'mail_w_toku', 'mail_wyslany', 'mail_blad' ), true ), 'wynik=' . wp_json_encode( $w4 ) . ' status=' . ( $zam ? $zam->status : 'brak' ) . ' zapytań=' . $GLOBALS['rw_zapytania'] );

remove_filter( 'pre_http_request', $atrapa, 10 );
echo $GLOBALS['sklepag_test_bledy'] ? 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n" : "WSZYSTKO OK\n";
exit( $GLOBALS['sklepag_test_bledy'] ? 1 : 0 );
