<?php
/**
 * Sklep — adres gościa za proxy, limity na adres, dziennik logowań. Test-strażnik: EXIT 1 przy porażce.
 *
 * Co się psuło (audyt niezależny 5.09): `sklepag_ip()` brało wyłącznie `REMOTE_ADDR`, więc za
 * Cloudflare/proxy wszyscy goście liczyli się jako jeden adres — limit logowań 6/min (cały WordPress)
 * wyrzucał właściciela z wp-admin, sprzedaż miała sufit 10 zakupów/min na całą witrynę. Dziennik logowań
 * zapisywał każdy wpisany ciąg (hasło wpisane w pole loginu lądowało w bazie).
 *
 * Najważniejszy krok to C: dwóch gości za JEDNYM proxy ma OSOBNE kubły. Reszta pilnuje, żeby lekarstwo
 * nie było gorsze od choroby — nagłówkom ufamy wyłącznie na decyzję właściciela.
 *
 * Uruchomienie:
 *   docker cp testy/ip-za-proxy-sklep-test.php strona_wp_wordpress:/tmp/ips.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/ips.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}
function naglowki( $remote, $cf = null, $xff = null ) {
	$_SERVER['REMOTE_ADDR'] = $remote;
	unset( $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	if ( null !== $cf ) {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = $cf;
	}
	if ( null !== $xff ) {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
	}
}

$stara_opcja = get_option( 'sklepag_za_proxy', null );

echo "=== A. DOMYŚLNIE nagłówki proxy są IGNOROWANE (da się je podrobić) ===\n";
update_option( 'sklepag_za_proxy', false );
naglowki( '203.0.113.7', '198.51.100.9', '198.51.100.9, 203.0.113.7' );
rowne( 'liczy się adres połączenia, nie nagłówek', '203.0.113.7', sklepag_ip() );

echo "\n=== B. Po WŁĄCZENIU: CF-Connecting-IP → X-Forwarded-For → REMOTE_ADDR ===\n";
update_option( 'sklepag_za_proxy', true );
naglowki( '203.0.113.7', '198.51.100.9', '192.0.2.5, 203.0.113.7' );
rowne( 'CF-Connecting-IP ma pierwszeństwo', '198.51.100.9', sklepag_ip() );
naglowki( '203.0.113.7', null, '192.0.2.5, 10.0.0.1, 203.0.113.7' );
rowne( 'bez CF bierzemy PIERWSZY adres z X-Forwarded-For', '192.0.2.5', sklepag_ip() );
naglowki( '203.0.113.7' );
rowne( 'bez nagłówków schodzimy do REMOTE_ADDR', '203.0.113.7', sklepag_ip() );
naglowki( '203.0.113.7', 'to-nie-jest-adres' );
rowne( 'nagłówek ze śmieciem NIE wyłącza limitu — wracamy do REMOTE_ADDR', '203.0.113.7', sklepag_ip() );
naglowki( '203.0.113.7', null, "'; DROP TABLE wp_options; --" );
rowne( 'próba wstrzyknięcia w nagłówku odpada', '203.0.113.7', sklepag_ip() );

echo "\n=== C. SEDNO LUKI: dwóch gości za JEDNYM proxy ma OSOBNE kubły ===\n";
$akcja = 'test' . wp_generate_password( 6, false, false );
update_option( 'sklepag_za_proxy', true );
naglowki( '203.0.113.7', '198.51.100.11' );
$a1 = sklepag_rate_limit( $akcja, 3 );
$a2 = sklepag_rate_limit( $akcja, 3 );
$a3 = sklepag_rate_limit( $akcja, 3 );
$a4 = sklepag_rate_limit( $akcja, 3 );
sprawdz( 'gość A: trzy żądania przechodzą, czwarte odbite', $a1 && $a2 && $a3 && ! $a4 );
naglowki( '203.0.113.7', '198.51.100.22' );
sprawdz( 'gość B zza tego samego proxy NIE jest ukarany za gościa A', sklepag_rate_limit( $akcja, 3 ) );

echo "\n=== D. Bez przełącznika ci sami dwaj goście dzielą JEDEN kubeł (stan sprzed naprawy = strona bez proxy) ===\n";
$akcja2 = 'test' . wp_generate_password( 6, false, false );
update_option( 'sklepag_za_proxy', false );
naglowki( '203.0.113.7', '198.51.100.11' );
sklepag_rate_limit( $akcja2, 3 );
sklepag_rate_limit( $akcja2, 3 );
sklepag_rate_limit( $akcja2, 3 );
naglowki( '203.0.113.7', '198.51.100.22' );
sprawdz( 'gość B dostaje odmowę przez ruch gościa A — dokładnie wada z audytu niezależnego', ! sklepag_rate_limit( $akcja2, 3 ) );

echo "\n=== E. Filtr: furtka na nietypowy nagłówek; śmieć z filtra = REMOTE_ADDR, nie pusty kubeł ===\n";
update_option( 'sklepag_za_proxy', false );
naglowki( '203.0.113.7' );
add_filter( 'sklepag_ip', function () { return '198.51.100.77'; } );
rowne( 'filtr ma ostatnie słowo', '198.51.100.77', sklepag_ip() );
remove_all_filters( 'sklepag_ip' );
add_filter( 'sklepag_ip', function () { return 'bzdura'; } );
rowne( 'filtr zwracający śmieć = REMOTE_ADDR', '203.0.113.7', sklepag_ip() );
remove_all_filters( 'sklepag_ip' );

echo "\n=== F. Dziennik logowań: nieistniejący login NIE trafia do bazy ===\n";
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => array( 'user_login' ) ) );
$login_admina = $admin ? (string) $admin[0]->user_login : '';
sprawdz( 'jest konto administratora do próby', '' !== $login_admina );
rowne( 'istniejący login zapisuje się jak wpisany', $login_admina, sklepag_login_do_dziennika( $login_admina ) );
rowne( 'hasło wpisane w pole loginu → „[nieistniejący]”', '[nieistniejący]', sklepag_login_do_dziennika( 'Kot!2026#' ) );
rowne( 'pusty login → pusty (nie liczy się)', '', sklepag_login_do_dziennika( '' ) );

echo "\n=== G. Sufit opóźnienia 2 s ===\n";
rowne( 'SKLEPAG_OPOZNIENIE_SUFIT_S = 2', 2, SKLEPAG_OPOZNIENIE_SUFIT_S );

if ( null === $stara_opcja ) {
	delete_option( 'sklepag_za_proxy' );
} else {
	update_option( 'sklepag_za_proxy', $stara_opcja );
}
sprawdz( 'ustawienie właściciela przywrócone', get_option( 'sklepag_za_proxy', null ) === $stara_opcja );

if ( $GLOBALS['sklepag_test_bledy'] > 0 ) {
	echo "NIEPOWODZENIE: {$GLOBALS['sklepag_test_bledy']} asercji nie przeszło\n";
	exit( 1 );
}
echo "WSZYSTKIE ASERCJE PRZESZLY (adres za proxy, osobne kubły, dziennik bez nieistniejących loginów, sufit 2 s)\n";
exit( 0 );
