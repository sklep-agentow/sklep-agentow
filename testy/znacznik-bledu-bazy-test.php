<?php
/**
 * Trwały znacznik nieudanego zapisu do bazy — obie wtyczki.
 *
 * PO CO: `wp_trigger_error()` nic nie zapisuje przy WP_DEBUG=false (rdzeń:
 * „Bail out if WP_DEBUG is not turned on"), a wyłączony jest na KAŻDEJ normalnej
 * instalacji klienta. 29 miejsc w obu wtyczkach gubiło tam błąd bez śladu.
 *
 * Sprawdza OBA kierunki. Ten drugi jest ważniejszy: bez niego nie wiadomo,
 * czy znacznik nie zapala się zawsze.
 *
 * Uruchomienie:
 *   docker cp testy/znacznik-bledu-bazy-test.php strona_wp_wordpress:/tmp/z.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/z.php
 */

global $wpdb;
$GLOBALS['zn_bledy'] = 0;

function zkrok( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " ($szczegol)" : '' );
	if ( ! $ok ) {
		++$GLOBALS['zn_bledy'];
	}
}

/** Wymusza PRAWDZIWY blad zapisu: insert do tabeli, ktorej nie ma. */
function zn_wymus_blad_bazy() {
	global $wpdb;
	$cisza = $wpdb->suppress_errors( true );
	$wpdb->query( "INSERT INTO {$wpdb->prefix}nie_ma_takiej_tabeli_probnej (x) VALUES (1)" ); // phpcs:ignore WordPress.DB
	$wpdb->suppress_errors( $cisza );
	return '' !== (string) $wpdb->last_error;
}

foreach ( array(
	array( 'ChatBot LIVE', 'chatbot-live', 'chatl_blad_bazy', 'chatl_log_bledu_bazy' ),
	array( 'Segregator maila', 'segregator-maila', 'segm_blad_bazy', 'segm_log_bledu_bazy' ),
) as $bot ) {
	list( $nazwa, $katalog, $opcja, $funkcja ) = $bot;
	printf( "\n=== %s ===\n", $nazwa );

	if ( ! function_exists( $funkcja ) ) {
		require_once WP_PLUGIN_DIR . '/' . $katalog . '/' . $katalog . '.php';
	}
	if ( ! function_exists( $funkcja ) ) {
		zkrok( false, "funkcja $funkcja jest dostepna" );
		continue;
	}

	delete_option( $opcja );

	// ── KIERUNEK 2 (wazniejszy): zdrowy zapis NIE zapala znacznika ──────────
	$wpdb->query( "SELECT 1" ); // phpcs:ignore WordPress.DB
	zkrok( false === get_option( $opcja ), 'PRZYPADEK BEZ WADY: zdrowe zapytanie NIE zapala znacznika' );

	$przed = get_option( $opcja );
	$wpdb->insert( $wpdb->options, array( 'option_name' => 'zn_proba_' . wp_generate_password( 6, false ), 'option_value' => 'x', 'autoload' => 'no' ) );
	zkrok( get_option( $opcja ) === $przed, 'PRZYPADEK BEZ WADY: udany INSERT nie zmienia znacznika' );

	// ── KIERUNEK 1: nieudany zapis ZAPALA znacznik ─────────────────────────
	zkrok( zn_wymus_blad_bazy(), 'podlozony blad zapisu naprawde wystapil (baza zglosila blad)' );
	call_user_func( $funkcja, 'proba_kalibracyjna', 'zapis probny do nieistniejacej tabeli' );

	$zn = get_option( $opcja );
	zkrok( is_array( $zn ), 'znacznik ZOSTAL zapisany mimo WP_DEBUG=' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false' ) );
	if ( is_array( $zn ) ) {
		zkrok( ! empty( $zn['ostatni'] ), 'znacznik niesie date ostatniego bledu', (string) ( $zn['ostatni'] ?? '' ) );
		zkrok( 'proba_kalibracyjna' === ( $zn['funkcja'] ?? '' ), 'znacznik niesie nazwe funkcji' );
		zkrok( '' !== (string) ( $zn['szczegol'] ?? '' ), 'znacznik niesie komunikat sterownika', mb_substr( (string) ( $zn['szczegol'] ?? '' ), 0, 48 ) );
		zkrok( 1 === (int) ( $zn['ile'] ?? 0 ), 'licznik wystapien = 1' );
	}

	// ── powtorzenie LICZY, a nie dopisuje kolejnego wpisu ───────────────────
	zn_wymus_blad_bazy();
	call_user_func( $funkcja, 'proba_kalibracyjna', 'zapis probny do nieistniejacej tabeli' );
	$zn2 = get_option( $opcja );
	zkrok( 2 === (int) ( $zn2['ile'] ?? 0 ), 'drugi blad PODNOSI licznik zamiast mnozyc wpisy', 'ile: ' . (int) ( $zn2['ile'] ?? 0 ) );

	delete_option( $opcja );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'zn_proba_%'" ); // phpcs:ignore WordPress.DB
}

echo "\n";
if ( 0 === $GLOBALS['zn_bledy'] ) {
	echo "WSZYSTKIE KROKI PRZESZLY\n";
	exit( 0 );
}
printf( "NIE PRZESZLO: %d krok(ow)\n", $GLOBALS['zn_bledy'] );
exit( 1 );
