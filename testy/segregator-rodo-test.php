<?php
/**
 * Segregator maila — eksporter i kasownik danych osobowych (WP Privacy Tools). Test-strażnik: EXIT 1 przy porażce.
 *
 * Pytanie: czy na żądanie „wydaj / usuń moje dane” wtyczka naprawdę wydaje i usuwa to, co trzyma o nadawcy —
 * we WSZYSTKICH trzech tabelach (próbka, dziennik ruchów, JSON raportów przebiegów) — a nie tylko rejestruje się
 * w narzędziach WP. Mierzymy SKUTEK w bazie (wiersz zniknął / nadawca wyczyszczony), nie sam kod powrotu.
 * Dane testowe: własny, jednoznaczny adres, sprzątany na końcu także przy porażce.
 *
 * Uruchomienie (stos warsztatowy):
 *   docker cp testy/segregator-rodo-test.php strona_wp_wordpress:/tmp/segm-rodo.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/segm-rodo.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb, $bledy; // ⛔ `global` także dla licznika: pod `wp eval-file` plik jest dołączany w funkcji, więc `$bledy = 0` bez `global` byłoby zmienną lokalną, a asercje (global) liczyłyby do INNEJ — test nie mógłby paść (5 BLAD i „przeszły”, zmierzone 5.09).
$bledy  = 0;
$adres  = 'rodo-probka-' . wp_generate_password( 8, false ) . '@example.test';
$tm     = $wpdb->prefix . 'segm_maile';
$tr     = $wpdb->prefix . 'segm_skrz_ruchy';
$tp     = $wpdb->prefix . 'segm_przebiegi';
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
$wpdb->insert( $tr, array( 'przebieg_id' => 0, 'nadawca' => $adres, 'temat' => 'Ruch A', 'message_id' => '<msg-rodo-a@example.test>', 'folder_z' => 'INBOX', 'folder_do' => 'Faktury', 'stan' => 'ok', 'komunikat' => '', 'utworzono' => $teraz ) );
$ruch_id = (int) $wpdb->insert_id;
// + 1 raport przebiegu z tą osobą (2 wpisy, różna wielkość liter) i cudzym wpisem + 1 raport BEZ tej osoby (nie wolno go ruszyć).
$wyniki_a = array(
	array( 'mail_id' => 1, 'nadawca' => $adres, 'temat' => 'Raport A', 'oczekiwana' => '', 'kategoria' => 'Faktury', 'regula_id' => 7, 'trafiony' => null ),
	array( 'mail_id' => 2, 'nadawca' => strtoupper( $adres ), 'temat' => 'Raport B', 'oczekiwana' => 'Inne', 'kategoria' => 'Inne', 'regula_id' => null, 'trafiony' => true ),
	array( 'mail_id' => 3, 'nadawca' => 'inna-osoba@example.test', 'temat' => 'Cudzy raport', 'oczekiwana' => '', 'kategoria' => 'Inne', 'regula_id' => null, 'trafiony' => null ),
);
$wyniki_b = array(
	array( 'mail_id' => 4, 'nadawca' => 'inna-osoba@example.test', 'temat' => 'Tylko cudzy', 'oczekiwana' => '', 'kategoria' => 'Inne', 'regula_id' => null, 'trafiony' => null ),
);
$wpdb->insert( $tp, array( 'tryb' => 'reguly', 'maili' => 3, 'dopasowanych' => 1, 'etykietowanych' => 1, 'trafionych' => 1, 'wyniki' => wp_json_encode( $wyniki_a ), 'uruchomiono' => $teraz ) );
$przebieg_a = (int) $wpdb->insert_id;
$wpdb->insert( $tp, array( 'tryb' => 'reguly', 'maili' => 1, 'dopasowanych' => 0, 'etykietowanych' => 0, 'trafionych' => 0, 'wyniki' => wp_json_encode( $wyniki_b ), 'uruchomiono' => $teraz ) );
$przebieg_b     = (int) $wpdb->insert_id;
$wyniki_b_zapis = (string) $wpdb->get_var( $wpdb->prepare( "SELECT wyniki FROM {$tp} WHERE id = %d", $przebieg_b ) );

try {
	$eks = segm_rodo_eksporter( $adres, 1 );
	segm_test_ok( 'eksport: 2 maile + 1 ruch + 2 wpisy raportu = 5 pozycji', 5 === count( $eks['data'] ), 'jest ' . count( $eks['data'] ) );
	$grupy = array_count_values( array_map( function ( $p ) { return $p['group_id']; }, $eks['data'] ) );
	segm_test_ok( 'eksport: grupa raportów ma 2 pozycje (obie wielkości liter), z tematem i nadaną kategorią', 2 === ( $grupy['segm-przebiegi'] ?? 0 ) && in_array( 'Raport B', array_map( function ( $p ) { return $p['data'][1]['value']; }, $eks['data'] ), true ), 'jest ' . ( $grupy['segm-przebiegi'] ?? 0 ) );
	segm_test_ok( 'eksport: done = true', ! empty( $eks['done'] ) );
	$tematy = array_map( function ( $p ) { return $p['data'][1]['value']; }, $eks['data'] );
	$msgid = array_filter( array_map( function ( $p ) { return 'segm-ruchy' === $p['group_id'] ? end( $p['data'] )['value'] : null; }, $eks['data'] ) );
	segm_test_ok( 'eksport: ruch niesie Message-ID (to, co trzymamy z jej wiadomości)', in_array( '<msg-rodo-a@example.test>', $msgid, true ), wp_json_encode( $msgid ) );
	segm_test_ok( 'eksport: niesie temat i nie niesie cudzych (ani z próbki, ani z raportu)', in_array( 'Temat A', $tematy, true ) && ! in_array( 'Cudzy', $tematy, true ) && ! in_array( 'Cudzy raport', $tematy, true ) && ! in_array( 'Tylko cudzy', $tematy, true ) );
	$obcy = segm_rodo_eksporter( 'nikt-taki@example.test', 1 );
	segm_test_ok( 'eksport nieznanego adresu: 0 pozycji, done', 0 === count( $obcy['data'] ) && ! empty( $obcy['done'] ) );

	$kas = segm_rodo_kasownik( $adres, 1 );
	segm_test_ok( 'kasownik: items_removed = 2 (oba maile, bez względu na wielkość liter)', 2 === (int) $kas['items_removed'], 'jest ' . (int) $kas['items_removed'] );
	segm_test_ok( 'kasownik: items_retained = 3 (ślad ruchu + 2 wpisy raportu bez nadawcy)', 3 === (int) $kas['items_retained'], 'jest ' . (int) $kas['items_retained'] );
	segm_test_ok( 'kasownik: done = true', ! empty( $kas['done'] ) );
	$zostalo = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tm} WHERE LOWER(nadawca) = %s", strtolower( $adres ) ) );
	segm_test_ok( 'SKUTEK: 0 maili tej osoby w bazie', 0 === $zostalo, 'jest ' . $zostalo );
	$cudzy = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tm} WHERE nadawca = 'inna-osoba@example.test'" );
	segm_test_ok( 'SKUTEK: cudzy mail nietknięty', 1 === $cudzy );
	$ruch = $wpdb->get_row( $wpdb->prepare( "SELECT nadawca, temat, message_id, folder_do FROM {$tr} WHERE id = %d", $ruch_id ) );
	segm_test_ok( 'SKUTEK: ruch zostaje z pustym nadawcą, tematem [usunięto na żądanie] i pustym Message-ID, folder bez zmian', $ruch && '' === $ruch->nadawca && '[usunięto na żądanie]' === $ruch->temat && '' === $ruch->message_id && 'Faktury' === $ruch->folder_do, wp_json_encode( $ruch ) );
	$rap_a = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT wyniki FROM {$tp} WHERE id = %d", $przebieg_a ) ), true );
	segm_test_ok( 'SKUTEK: w raporcie oba wpisy tej osoby bez nadawcy i z tematem [usunięto na żądanie], kategoria i trafienie bez zmian', is_array( $rap_a ) && 3 === count( $rap_a ) && '' === $rap_a[0]['nadawca'] && '[usunięto na żądanie]' === $rap_a[0]['temat'] && 'Faktury' === $rap_a[0]['kategoria'] && '' === $rap_a[1]['nadawca'] && '[usunięto na żądanie]' === $rap_a[1]['temat'] && true === $rap_a[1]['trafiony'], wp_json_encode( $rap_a ) );
	segm_test_ok( 'SKUTEK: cudzy wpis w tym samym raporcie nietknięty', is_array( $rap_a ) && 'inna-osoba@example.test' === $rap_a[2]['nadawca'] && 'Cudzy raport' === $rap_a[2]['temat'] );
	$rap_b = (string) $wpdb->get_var( $wpdb->prepare( "SELECT wyniki FROM {$tp} WHERE id = %d", $przebieg_b ) );
	segm_test_ok( 'SKUTEK: raport bez tej osoby bajt w bajt bez zmian', $rap_b === $wyniki_b_zapis );
	segm_test_ok( 'SKUTEK: adres nie występuje już w żadnym raporcie (LIKE po JSON-ie)', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tp} WHERE LOWER(wyniki) LIKE %s", '%' . $wpdb->esc_like( strtolower( $adres ) ) . '%' ) ) );
	$kas2 = segm_rodo_kasownik( $adres, 1 );
	segm_test_ok( 'kasownik drugi raz: 0 usuniętych, 0 zostawionych, done (idempotentny)', 0 === (int) $kas2['items_removed'] && 0 === (int) $kas2['items_retained'] && ! empty( $kas2['done'] ) );
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tm} WHERE LOWER(nadawca) IN (%s, 'inna-osoba@example.test')", strtolower( $adres ) ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE id = %d", $ruch_id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tp} WHERE id IN (%d, %d)", $przebieg_a, $przebieg_b ) );
}
// phpcs:enable

if ( $bledy > 0 ) {
	echo "NIEPOWODZENIE: {$bledy} asercji nie przeszło\n";
	exit( 1 );
}
echo "WSZYSTKIE ASERCJE PRZESZLY (eksport i kasowanie mierzone po skutku w bazie)\n";
exit( 0 );
