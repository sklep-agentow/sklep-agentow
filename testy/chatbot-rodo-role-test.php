<?php
/**
 * ChatBot LIVE — cztery role rozmowy nazwane po ludzku (paczka C5, znalezisko P2-Z4).
 *
 * PO CO (audyt): eksporter RODO mapował rolę wiersza jako „wszystko, co nie jest
 * gościem, to Bot". W tabeli rozmów są CZTERY role: `gosc`, `bot`, `operator`
 * (człowiek z obsługi) i `system` (komunikaty o przejęciu rozmowy). Osoba, która
 * na wniosek RODO dostawała swoje dane, czytała więc, że odpowiadał jej „Bot",
 * choć pisał do niej człowiek — a komunikaty systemowe wyglądały na wypowiedzi
 * bota. Ten sam skrót był w eksporcie CSV rozmów.
 *
 * Zamrożona sonda `audyt/boty/proba-rodo.php` odtwarza wadę (EXIT zawsze 0) —
 * strażnikiem jest ten plik.
 *
 * Przypadek BEZ WADY: eksport dalej wydaje komplet wierszy tej samej osoby, a rola
 * gościa i bota nazywa się tak samo jak dotąd („Gość", „Bot") — naprawa nie miesza
 * ludziom nazw, które już znają.
 *
 * Sprząta po sobie: własne zgłoszenia, rozmowy i sesje (prefiks `rodoRole`).
 *
 * Kalibracja (opis PR C5): na kodzie sprzed naprawy EXIT=1, po naprawie EXIT=0.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-rodo-role-test.php strona_wp_wordpress:/tmp/crr.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/crr.php; echo EXIT=$?
 */

global $wpdb;
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/rodo.php';
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/csv.php';

$GLOBALS['crr_bledy'] = 0;
function crr( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? ' (' . $szczegol . ')' : '' );
	if ( ! $ok ) {
		++$GLOBALS['crr_bledy'];
	}
}

$crr_mail  = 'rodo-role-test@example.test';
$crr_sesja = 'rodoRoleSesja00000000000000000A';
function crr_sprzataj() {
	global $wpdb, $crr_mail, $crr_sesja;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email = %s", $crr_mail ) ); // phpcs:ignore
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja = %s", $crr_sesja ) ); // phpcs:ignore
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $crr_sesja ) ); // phpcs:ignore
}
register_shutdown_function( 'crr_sprzataj' );
crr_sprzataj();

echo "=== A. Jedno źródło nazw ról (chatl_nazwa_roli) ===\n";
crr( function_exists( 'chatl_nazwa_roli' ), 'funkcja chatl_nazwa_roli() istnieje' );
if ( ! function_exists( 'chatl_nazwa_roli' ) ) {
	echo "\nBLEDOW: 1 — role nadal mapowane w każdym miejscu osobno (stan sprzed C5)\n";
	exit( 1 );
}
crr( 'Gość' === chatl_nazwa_roli( 'gosc' ), 'gosc → Gość (bez zmian)' );
crr( 'Bot' === chatl_nazwa_roli( 'bot' ), 'bot → Bot (bez zmian)' );
crr( 'Bot' !== chatl_nazwa_roli( 'operator' ) && false !== mb_stripos( chatl_nazwa_roli( 'operator' ), 'człowiek' ), 'operator → nazwa mówiąca, że to CZŁOWIEK', chatl_nazwa_roli( 'operator' ) );
crr( 'Bot' !== chatl_nazwa_roli( 'system' ), 'system → NIE „Bot" (to komunikat wtyczki, nie wypowiedź bota)', chatl_nazwa_roli( 'system' ) );
crr( 'cos-nieznanego' === chatl_nazwa_roli( 'cos-nieznanego' ), 'nieznana rola wraca bez zmiany (nie udajemy, że wiemy)' );

echo "\n=== B. P2-Z4: eksport RODO nazywa wszystkie cztery role ===\n";
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$tr = $wpdb->prefix . 'chatl_rozmowy';
$wpdb->insert( $tz, array( 'email' => $crr_mail, 'wiadomosc' => 'zgloszenie probne', 'sesja' => $crr_sesja, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
foreach ( array(
	array( 'gosc', 'PYTANIE-GOSCIA' ),
	array( 'bot', 'ODPOWIEDZ-BOTA' ),
	array( 'system', 'Do rozmowy dołączył konsultant — od tej chwili odpowiada człowiek, nie bot.' ),
	array( 'operator', 'ODPOWIEDZ-CZLOWIEKA' ),
) as $w ) {
	$wpdb->insert( $tr, array( 'sesja' => $crr_sesja, 'rola' => $w[0], 'silnik' => 'reguly', 'tresc' => $w[1], 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
}

$eksportery = apply_filters( 'wp_privacy_personal_data_exporters', array() );
crr( isset( $eksportery['chatbot-live'] ), 'eksporter ChatBota jest zarejestrowany w kreatorze WordPressa' );
$wynik = call_user_func( $eksportery['chatbot-live']['callback'], $crr_mail, 1 );

$role = array();
foreach ( (array) $wynik['data'] as $grupa ) {
	$tresc = '';
	$kto   = '';
	foreach ( (array) $grupa['data'] as $pole ) {
		if ( 'Kto' === $pole['name'] ) {
			$kto = (string) $pole['value'];
		}
		if ( 'Treść' === $pole['name'] ) {
			$tresc = (string) $pole['value'];
		}
	}
	if ( '' !== $kto ) {
		$role[ $tresc ] = $kto;
	}
}
crr( 'Gość' === ( $role['PYTANIE-GOSCIA'] ?? '' ), 'wiersz gościa opisany „Gość"', wp_json_encode( $role ) );
crr( 'Bot' === ( $role['ODPOWIEDZ-BOTA'] ?? '' ), 'wiersz bota opisany „Bot"' );
crr( 'Bot' !== ( $role['ODPOWIEDZ-CZLOWIEKA'] ?? 'Bot' ), 'P2-Z4: wiadomość KONSULTANTA nie jest już podpisana „Bot"', (string) ( $role['ODPOWIEDZ-CZLOWIEKA'] ?? '(brak)' ) );
crr( chatl_nazwa_roli( 'operator' ) === ( $role['ODPOWIEDZ-CZLOWIEKA'] ?? '' ), 'i nosi tę samą nazwę, co w reszcie wtyczki' );
$systemowy = '';
foreach ( $role as $t => $k ) {
	if ( false !== mb_strpos( $t, 'dołączył konsultant' ) ) {
		$systemowy = $k;
	}
}
crr( 'Bot' !== $systemowy && '' !== $systemowy, 'komunikat systemowy nie udaje wypowiedzi bota', $systemowy );
crr( 4 === count( $role ), 'PRZYPADEK BEZ WADY: eksport wydaje KOMPLET czterech wierszy', (string) count( $role ) );

echo "\n=== C. Sweep: ta sama mapa w eksporcie CSV rozmów ===\n";
$csv = (string) file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/includes/csv.php' ); // phpcs:ignore
crr( false === strpos( $csv, "'gosc' === \$w->rola ? 'gość' : 'bot'" ), 'CSV nie ma już własnej mapy „wszystko poza gościem to bot"' );
crr( false !== strpos( $csv, 'chatl_nazwa_roli(' ), 'CSV używa wspólnej funkcji nazw ról' );

echo "\n" . ( $GLOBALS['crr_bledy'] > 0 ? 'BLEDOW: ' . $GLOBALS['crr_bledy'] : 'WSZYSTKO OK' ) . "\n";
exit( $GLOBALS['crr_bledy'] > 0 ? 1 : 0 );
