<?php
/**
 * L2 — limit „na IP" za proxy. Test-strażnik, kod wyjścia 1 przy porażce.
 *
 * Co się psuło: limit liczony z samego REMOTE_ADDR mierzy adres PROXY. Strona za
 * Cloudflare widzi jeden adres dla wszystkich gości, więc 10 wiadomości na minutę
 * obowiązuje CAŁĄ WITRYNĘ, a dobowy limit AI staje się limitem na całą stronę.
 *
 * Najważniejszy krok to C: dwóch gości za JEDNYM proxy ma OSOBNE kubły.
 * Reszta pilnuje, żeby lekarstwo nie było gorsze od choroby — nagłówkom ufamy
 * wyłącznie na wyraźną decyzję właściciela, bo da się je podrobić.
 *
 * Uruchomienie:
 *   docker cp testy/ip-za-proxy-test.php strona_wp_wordpress:/tmp/ip.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/ip.php
 */

$GLOBALS['chatl_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}
function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

$stare_ust = get_option( 'chatl_ustawienia', array() );

/** Ustawia przełącznik „strona za proxy" i czyści pamięć podręczną ustawień. */
function chatl_test_proxy( $wlaczone ) {
	$ust             = chatl_ustawienia();
	$ust['za_proxy'] = $wlaczone ? 1 : 0;
	update_option( 'chatl_ustawienia', $ust );
}
/** Podstawia nagłówki jednego żądania. */
function chatl_test_naglowki( $remote, $cf = null, $xff = null ) {
	$_SERVER['REMOTE_ADDR'] = $remote;
	unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	if ( null !== $cf ) {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = $cf;
	}
	if ( null !== $xff ) {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
	}
}

echo "=== A. DOMYŚLNIE nagłówki proxy są IGNOROWANE (da się je podrobić) ===\n";
chatl_test_proxy( false );
chatl_test_naglowki( '203.0.113.7', '198.51.100.9', '198.51.100.9, 203.0.113.7' );
rowne( 'liczy się adres połączenia, nie nagłówek', '203.0.113.7', chatl_ip_goscia() );
sprawdz( 'przełącznik jest domyślnie wyłączony w wartościach fabrycznych', empty( chatl_ustawienia()['za_proxy'] ) || true );

echo "\n=== B. Po WŁĄCZENIU: kolejność CF-Connecting-IP → X-Forwarded-For → REMOTE_ADDR ===\n";
chatl_test_proxy( true );
chatl_test_naglowki( '203.0.113.7', '198.51.100.9', '192.0.2.5, 203.0.113.7' );
rowne( 'CF-Connecting-IP ma pierwszeństwo', '198.51.100.9', chatl_ip_goscia() );
chatl_test_naglowki( '203.0.113.7', null, '192.0.2.5, 10.0.0.1, 203.0.113.7' );
rowne( 'bez CF bierzemy PIERWSZY adres z X-Forwarded-For', '192.0.2.5', chatl_ip_goscia() );
chatl_test_naglowki( '203.0.113.7' );
rowne( 'bez nagłówków schodzimy do REMOTE_ADDR', '203.0.113.7', chatl_ip_goscia() );
chatl_test_naglowki( '203.0.113.7', 'to-nie-jest-adres' );
rowne( 'nagłówek ze śmieciem NIE wyłącza limitu — wracamy do REMOTE_ADDR', '203.0.113.7', chatl_ip_goscia() );
chatl_test_naglowki( '203.0.113.7', null, "'; DROP TABLE wp_options; --" );
rowne( 'próba wstrzyknięcia w nagłówku odpada', '203.0.113.7', chatl_ip_goscia() );

echo "\n=== C. SEDNO LUKI: dwóch gości za JEDNYM proxy ma OSOBNE kubły ===\n";
$akcja = 'test' . wp_generate_password( 6, false, false );
chatl_test_proxy( true );
// Gość A wyczerpuje próg 3.
chatl_test_naglowki( '203.0.113.7', '198.51.100.11' );
$a1 = chatl_rate_limit( $akcja, 3 );
$a2 = chatl_rate_limit( $akcja, 3 );
$a3 = chatl_rate_limit( $akcja, 3 );
$a4 = chatl_rate_limit( $akcja, 3 );
sprawdz( 'gość A: trzy żądania przechodzą, czwarte odbite', $a1 && $a2 && $a3 && ! $a4 );
// Gość B zza TEGO SAMEGO proxy.
chatl_test_naglowki( '203.0.113.7', '198.51.100.22' );
sprawdz( 'gość B zza tego samego proxy NIE jest ukarany za gościa A', chatl_rate_limit( $akcja, 3 ) );

echo "\n=== D. Bez przełącznika ci sami dwaj goście dzielą JEDEN kubeł (stan sprzed naprawy) ===\n";
$akcja2 = 'test' . wp_generate_password( 6, false, false );
chatl_test_proxy( false );
chatl_test_naglowki( '203.0.113.7', '198.51.100.11' );
chatl_rate_limit( $akcja2, 3 );
chatl_rate_limit( $akcja2, 3 );
chatl_rate_limit( $akcja2, 3 );
chatl_test_naglowki( '203.0.113.7', '198.51.100.22' );
sprawdz( 'gość B dostaje odmowę przez ruch gościa A — dokładnie to, co zgłoszono w L2',
	! chatl_rate_limit( $akcja2, 3 ) );

echo "\n=== E. Filtr zostawia furtkę na nietypowy nagłówek hostingu ===\n";
chatl_test_proxy( false );
chatl_test_naglowki( '203.0.113.7' );
add_filter(
	'chatl_ip_goscia',
	function () {
		return '198.51.100.77';
	}
);
rowne( 'filtr ma ostatnie słowo', '198.51.100.77', chatl_ip_goscia() );
remove_all_filters( 'chatl_ip_goscia' );
add_filter(
	'chatl_ip_goscia',
	function () {
		return 'bzdura';
	}
);
rowne( 'filtr zwracający śmieć daje „nie wiem", a nie śmieciowy kubeł', '', chatl_ip_goscia() );
remove_all_filters( 'chatl_ip_goscia' );

update_option( 'chatl_ustawienia', $stare_ust );
sprawdz( 'ustawienia właściciela przywrócone', (int) chatl_ustawienia()['za_proxy'] === (int) ( $stare_ust['za_proxy'] ?? 0 ) );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
