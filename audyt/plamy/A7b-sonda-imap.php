<?php
/**
 * SONDA AUDYTOWA A7b — co robi Segm_Imap z wejsciem wrogim albo obcietym.
 *
 * Sama mierzy i DRUKUJE (werdykt z tego pliku wystawia czlowiek), ale od 5.09 jest
 * SILNIKIEM straznika `testy/segregator-imap-sufity-proba-kontrolna.sh` (exit 1),
 * ktory biegnie w przelocie prob kontrolnych. Uruchamiac przez A7b-sonda-imap.sh,
 * ktory stawia atrape (A7b-sonda-imap-atrapa.php) i podaje port oraz scenariusz.
 *
 * ⛔ Limit pamieci ustawia wolajacy (skrypt daje -d memory_limit=64M), zeby
 * brak ograniczenia po stronie klienta objawil sie SZYBKO i bezpiecznie,
 * a nie zabral pamieci maszyny.
 *
 * Uzycie: php sonda.php <port> <scenariusz>
 */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/wtyczki/segregator-maila/includes/class-segm-imap.php';

$port = (int) $argv[1];
$scen = $argv[2];
$start = microtime( true );

register_shutdown_function( function () use ( &$start, $scen ) {
	$b = error_get_last();
	printf(
		"WYNIK[%s] czas=%.1fs pamiec_szczyt=%.1fMB %s\n",
		$scen, microtime( true ) - $start, memory_get_peak_usage( true ) / 1048576,
		( $b && in_array( $b['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) )
			? 'FATAL: ' . preg_replace( '/\s+/', ' ', substr( $b['message'], 0, 120 ) )
			: 'bez fatala'
	);
} );

$k = new Segm_Imap( 5 ); // limit czasu 5 s, zeby sonda nie wisiala
if ( ! $k->polacz( '127.0.0.1', $port, 'brak', false ) ) {
	echo "POLACZENIE: NIE — " . $k->blad() . "\n"; exit( 1 );
}
echo "POLACZENIE: tak\n";
if ( 'search-40k' === $scen ) {
	$u = $k->uidy( 'ALL' );  // UID SEARCH ALL — jedna linia z 40 000 UID-ow
	echo 'UIDY: ' . ( is_array( $u ) ? count( $u ) : 'null' ) . "\n";
} else {
	$r = $k->naglowki( 42 );   // UID FETCH … (BODY.PEEK[HEADER])
	echo 'NAGLOWKI: ' . ( null === $r ? 'null' : 'string(' . strlen( $r ) . ')' ) . "\n";
}
echo 'BLAD: ' . ( '' === $k->blad() ? '(brak)' : $k->blad() ) . "\n";
