<?php
/**
 * Segregator maila — eksporter i kasownik danych osobowych (WP Privacy Tools). Test-strażnik: EXIT 1 przy porażce.
 *
 * Pytanie: czy na żądanie „wydaj / usuń moje dane” wtyczka naprawdę wydaje i usuwa to, co trzyma o nadawcy —
 * a nie tylko rejestruje się w narzędziach WP. Mierzymy SKUTEK w bazie (wiersz zniknął / nadawca wyczyszczony),
 * nie sam kod powrotu. Dane testowe: własny, jednoznaczny adres, sprzątany na końcu także przy porażce.
 *
 * Uruchomienie (stos warsztatowy):
 *   docker cp testy/segregator-rodo-test.php strona_wp_wordpress:/tmp/segm-rodo.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/segm-rodo.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$bledy  = 0;
$adres  = 'rodo-probka-' . wp_generate_password( 8, false ) . '@example.test';
$tm     = $wpdb->prefix . 'segm_maile';
$tr     = $wpdb->prefix . 'segm_skrz_ruchy';
$teraz  = current_time( 'mysql' );

function segm_test_ok( $opis, $warunek, $szczegol = '' ) {
	global $bledy;
	if ( $warunek ) {
		echo "  OK   {$opis}\n";
	} else {
		$bledy++;
		echo "  BLAD {$opis}" . ( '' !== $szczegol ? " — {$szczegol}" : '' ) . "\n";
	}
}

echo "=== SEGREGATOR RODO: eksporter + kasownik (adres {$adres}) ===\n";

segm_test_ok( 'eksporter zarejestrowany w WP', isset( apply_filters( 'wp_privacy_personal_data_exporters', array() )['segregator-maila'] ) );
segm_test_ok( 'kasownik zarejestrowany w WP', isset( apply_filters( 'wp_privacy_personal_data_erasers', array() )['segregator-maila'] ) );

// Zasiew: 2 maile (różna wielkość liter w adresie) + 1 ruch w skrzynce + 1 mail INNEJ osoby (nie wolno go ruszyć).
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $tm, array( 'zrodlo' => 'test', 'nadawca' => $adres, 'temat' => 'Temat A', 'tresc' => 'Tresc A', 'utworzono' => $teraz ) );
$wpdb->insert( $tm, array( 'zrodlo' => 'test', 'nadawca' => strtoupper( $adres ), 'temat' => 'Temat B', 'tresc' => 'Tresc B', 'utworzono' => $teraz ) );
$wpdb->insert( $tm, array( 'zrodlo' => 'test', 'nadawca' => 'inna-osoba@example.test', 'temat' => 'Cudzy', 'tresc' => 'Cudza tresc', 'utworzono' => $teraz ) );
$wpdb->insert( $tr, array( 'przebieg_id' => 0, 'nadawca' => $adres, 'temat' => 'Ruch A', 'folder_z' => 'INBOX', 'folder_do' => 'Faktury', 'stan' => 'ok', 'komunikat' => '', 'utworzono' => $teraz ) );
$ruch_id = (int) $wpdb->insert_id;

try {
	$eks = segm_rodo_eksporter( $adres, 1 );
	segm_test_ok( 'eksport: 2 maile + 1 ruch = 3 pozycje', 3 === count( $eks['data'] ), 'jest ' . count( $eks['data'] ) );
	segm_test_ok( 'eksport: done = true', ! empty( $eks['done'] ) );
	$tematy = array_map( function ( $p ) { return $p['data'][1]['value']; }, $eks['data'] );
	segm_test_ok( 'eksport: niesie temat i nie niesie cudzych', in_array( 'Temat A', $tematy, true ) && ! in_array( 'Cudzy', $tematy, true ) );
	$obcy = segm_rodo_eksporter( 'nikt-taki@example.test', 1 );
	segm_test_ok( 'eksport nieznanego adresu: 0 pozycji, done', 0 === count( $obcy['data'] ) && ! empty( $obcy['done'] ) );

	$kas = segm_rodo_kasownik( $adres, 1 );
	segm_test_ok( 'kasownik: items_removed = 2 (oba maile, bez względu na wielkość liter)', 2 === (int) $kas['items_removed'], 'jest ' . (int) $kas['items_removed'] );
	segm_test_ok( 'kasownik: items_retained = 1 (ślad ruchu bez nadawcy)', 1 === (int) $kas['items_retained'], 'jest ' . (int) $kas['items_retained'] );
	segm_test_ok( 'kasownik: done = true', ! empty( $kas['done'] ) );
	$zostalo = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tm} WHERE LOWER(nadawca) = %s", strtolower( $adres ) ) );
	segm_test_ok( 'SKUTEK: 0 maili tej osoby w bazie', 0 === $zostalo, 'jest ' . $zostalo );
	$cudzy = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tm} WHERE nadawca = 'inna-osoba@example.test'" );
	segm_test_ok( 'SKUTEK: cudzy mail nietknięty', 1 === $cudzy );
	$ruch = $wpdb->get_row( $wpdb->prepare( "SELECT nadawca, temat, folder_do FROM {$tr} WHERE id = %d", $ruch_id ) );
	segm_test_ok( 'SKUTEK: ruch zostaje z pustym nadawcą i tematem [usunięto na żądanie], folder bez zmian', $ruch && '' === $ruch->nadawca && '[usunięto na żądanie]' === $ruch->temat && 'Faktury' === $ruch->folder_do );
	$kas2 = segm_rodo_kasownik( $adres, 1 );
	segm_test_ok( 'kasownik drugi raz: 0 usuniętych, done (idempotentny)', 0 === (int) $kas2['items_removed'] && ! empty( $kas2['done'] ) );
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tm} WHERE LOWER(nadawca) IN (%s, 'inna-osoba@example.test')", strtolower( $adres ) ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE id = %d", $ruch_id ) );
}
// phpcs:enable

if ( $bledy > 0 ) {
	echo "NIEPOWODZENIE: {$bledy} asercji nie przeszło\n";
	exit( 1 );
}
echo "WSZYSTKIE ASERCJE PRZESZLY (eksport i kasowanie mierzone po skutku w bazie)\n";
exit( 0 );
