<?php
/**
 * STRAŻNIK REGRESJI — SK-L-003 (komunikat cytował limit wtyczki zamiast realnego).
 *
 * Wada: przy odrzuceniu pliku przez serwer (UPLOAD_ERR_INI_SIZE) wtyczka mówiła
 * „Limit: 20 MB" (własna stała SKLEPAG_UPLOAD_LIMIT_B), nawet gdy realny sufit
 * php.ini wynosił 2 MB. Właściciel czytał liczbę wtyczki i szukał przyczyny tam,
 * gdzie jej nie było. Nieudane wgranie nie zostawiało też śladu w dzienniku.
 *
 * Limit serwera podstawiamy filtrem rdzenia `upload_size_limit` (wp_max_upload_size()),
 * bez ruszania php.ini i bez podmiany kodu wtyczki.
 *
 * PASS = komunikat cytuje 2 MB (realny limit serwera) i NIE cytuje 20 MB jako obowiązującego,
 *        a w dzienniku pojawia się wpis 'upload_produktu_blad'.
 * FAIL = komunikat nadal cytuje limit wtyczki jako obowiązujący albo brak śladu w dzienniku.
 */

global $wpdb;
if ( ! function_exists( 'sklepag_przyjmij_zip' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin-produkty.php';
}

// Serwer pozwala tylko na 2 MB — mniej niż limit wtyczki (20 MB).
add_filter( 'upload_size_limit', static fn() => 2 * 1024 * 1024, 99 );

$limit_funkcji = function_exists( 'sklepag_limit_uploadu_b' ) ? sklepag_limit_uploadu_b() : SKLEPAG_UPLOAD_LIMIT_B;
echo 'STRAZNIK: limit_obowiazujacy=' . size_format( $limit_funkcji ) . " (OCZEKIWANE: 2 MB)\n";

// Serwer odrzucił plik przed wtyczką.
$_FILES['plik'] = array(
	'error'    => UPLOAD_ERR_INI_SIZE,
	'name'     => 'wielki.zip',
	'tmp_name' => '',
	'size'     => 0,
);

$zdarzen_przed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zdarzenia WHERE typ='upload_produktu_blad'" );

$komunikat = '';
$wroc      = static function ( $tekst ) {
	throw new Exception( $tekst );
};
try {
	sklepag_przyjmij_zip( $wroc );
} catch ( Exception $e ) {
	$komunikat = $e->getMessage();
}
unset( $_FILES['plik'] );

$zdarzen_po = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zdarzenia WHERE typ='upload_produktu_blad'" );

echo 'STRAZNIK: komunikat=' . $komunikat . "\n";
$mowi_o_realnym = ( false !== strpos( $komunikat, '2 MB' ) );
$myli_limitem   = ( false !== strpos( $komunikat, 'Limit: 20 MB' ) );
$slad           = ( $zdarzen_po > $zdarzen_przed );

echo 'STRAZNIK: cytuje_realny_limit=' . ( $mowi_o_realnym ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'STRAZNIK: myli_limitem_wtyczki=' . ( $myli_limitem ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: NIE)\n";
echo 'STRAZNIK: slad_w_dzienniku=' . ( $slad ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'STRAZNIK: WYNIK=' . ( $mowi_o_realnym && ! $myli_limitem && $slad ? 'PASS' : 'FAIL - komunikat o limicie wprowadza w blad' ) . "\n";

$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE typ='upload_produktu_blad'" );
