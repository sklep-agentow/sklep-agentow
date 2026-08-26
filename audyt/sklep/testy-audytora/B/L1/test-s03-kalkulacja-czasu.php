<?php
/**
 * Test padający — K7 runda B, soczewka L1 (zgodność z zamówieniem).
 * Kod na SHA 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (audyt/sklep/ZAMROZENIE.md).
 *
 * Cel: S-03 / kryterium GOTOWE #10 — „opisy «ile czasu oszczędza» mają pokrycie
 * w treści" dla OBU produktów sklepu.
 *
 * Wywołuje BADANĄ funkcję produkcyjną sklepag_tresc_produktu() (includes/front-produkt.php)
 * — nie atrapę. Uruchom na stosie warsztatowym:
 *   docker cp test-s03-kalkulacja-czasu.php strona_wp_wordpress:/tmp/t.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/t.php
 *
 * Oczekiwany wynik PO naprawie: obie linie "OK". Na obecnym kodzie 'segregator-maila'
 * PADA — brak klucza 'kalkulacja' (chatbot-live go ma, linia front-produkt.php:328).
 */

$produkty = array( 'chatbot-live', 'segregator-maila' );
$pad      = false;

foreach ( $produkty as $slug ) {
	$t = sklepag_tresc_produktu( $slug );
	if ( ! is_array( $t ) ) {
		echo "FAIL {$slug}: sklepag_tresc_produktu() nie zwrocila tablicy\n";
		$pad = true;
		continue;
	}
	if ( empty( $t['kalkulacja'] ) ) {
		echo "FAIL {$slug}: brak klucza 'kalkulacja' (ile czasu oszczedza) w tresci produktu — S-03 / kryterium #10 niespelnione symetrycznie\n";
		$pad = true;
		continue;
	}
	echo "OK {$slug}: kalkulacja = " . $t['kalkulacja'] . "\n";
}

if ( $pad ) {
	echo "WYNIK: PADA\n";
	exit( 1 );
} else {
	echo "WYNIK: OK\n";
}
