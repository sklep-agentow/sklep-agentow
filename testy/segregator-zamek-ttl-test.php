<?php
/**
 * STRAŻNIK zamka Segregatora (T-4 + DK-6): znacznik `segm_operacja_w_toku`
 * wpuszcza DOKŁADNIE jednego, a po padniętym procesie NIE blokuje na zawsze.
 *
 * Co mierzy (każdy punkt osobno, żeby padał na właściwym):
 *   1. (bez wady) dwa zajęcia PO KOLEI — drugi dostaje opis pierwszego;
 *   2. (bez wady) zwolnienie oddaje zamek;
 *   3. (T-4) znacznik PRZETERMINOWANY (proces padł, `do` w przeszłości) →
 *      następny przejmuje zamek, zamiast stać w nieskończoność;
 *   4. (T-4, odtworzona postać) znacznik w OBCYM kształcie (wartość bez pola
 *      `do` — np. zostawiona przez starszą wersję albo ręcznie) — na kodzie
 *      sprzed naprawy blokował wtyczkę NA ZAWSZE, bo sprawdzenie terminu
 *      pomijało taki wiersz, a `add_option()` odbijał się od istniejącego;
 *   5. (DK-6, ten sam układ co zamrożony `audyt/boty/testy-audytu/
 *      DK-blokada-segregatora.php`) proces B wstawia znacznik do bazy ZA
 *      PLECAMI procesu A, który już „wie", że znacznika nie ma — A musi dostać
 *      odmowę, a znacznik ma dalej należeć do B.
 *
 * Test kasuje po sobie znacznik w KAŻDYM przypadku (także po padzie w środku).
 * Uruchomienie:
 *   docker cp testy/segregator-zamek-ttl-test.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
 * EXIT 0 = wszystkie punkty OK, 1 = któryś padł.
 */
global $wpdb;
if ( ! defined( 'SEGM_BLOKADA' ) ) {
	echo "POMINIETO: wtyczka segregator-maila nie jest zaladowana.\n";
	exit( 0 );
}
$GLOBALS['segmzt_bledy'] = 0;
function segmzt( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " ($szczegol)" : '' );
	if ( ! $ok ) {
		++$GLOBALS['segmzt_bledy'];
	}
	return $ok;
}
/**
 * Wpis „z innego procesu": prosto do bazy, z pominięciem cache opcji tego procesu.
 * $swiezy_proces = true: udajemy NOWE żądanie PHP po padzie (cache opcji czysty,
 * więc funkcja zobaczy wiersz); false: cache zostaje — to układ wyścigu DK-6,
 * w którym proces A już „wie", że znacznika nie ma.
 */
function segmzt_wstaw_obcy( $wartosc, $swiezy_proces = true ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", SEGM_BLOKADA ) );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			SEGM_BLOKADA,
			maybe_serialize( $wartosc )
		)
	);
	if ( $swiezy_proces ) {
		wp_cache_delete( SEGM_BLOKADA, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}
}
function segmzt_w_bazie() {
	global $wpdb;
	$s = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", SEGM_BLOKADA ) );
	return null === $s ? null : maybe_unserialize( $s );
}
register_shutdown_function(
	function () {
		delete_option( SEGM_BLOKADA );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", SEGM_BLOKADA ) );
	}
);
delete_option( SEGM_BLOKADA );

echo "=== 1. BEZ WADY: dwa zajecia po kolei ===\n";
$a = segm_zajmij_blokade( 'zamektest: pierwszy' );
$b = segm_zajmij_blokade( 'zamektest: drugi' );
segmzt( true === $a, 'pierwszy dostaje zamek', var_export( $a, true ) );
segmzt( 'zamektest: pierwszy' === $b, 'drugi dostaje OPIS pierwszego, nie zamek', var_export( $b, true ) );

echo "=== 2. BEZ WADY: zwolnienie oddaje zamek ===\n";
segm_zwolnij_blokade();
$c = segm_zajmij_blokade( 'zamektest: po zwolnieniu' );
segmzt( true === $c, 'po zwolnieniu kolejny dostaje zamek', var_export( $c, true ) );
segm_zwolnij_blokade();

echo "=== 3. T-4: znacznik przeterminowany (proces padl) ===\n";
segmzt_wstaw_obcy(
	array(
		'opis' => 'zamektest: zabity proces',
		'do'   => time() - 3600,
	)
);
$d = segm_zajmij_blokade( 'zamektest: przejmujacy' );
$w = segmzt_w_bazie();
segmzt( true === $d, 'przeterminowany znacznik zostaje przejety', var_export( $d, true ) );
segmzt( is_array( $w ) && 'zamektest: przejmujacy' === ( $w['opis'] ?? '' ), 'znacznik w bazie nalezy do przejmujacego', is_array( $w ) ? ( $w['opis'] ?? '?' ) : var_export( $w, true ) );
segm_zwolnij_blokade();

echo "=== 4. T-4: znacznik w obcym ksztalcie (bez pola 'do') ===\n";
segmzt_wstaw_obcy( 'zamektest-stary-ksztalt' );
$e = segm_zajmij_blokade( 'zamektest: po starym ksztalcie' );
segmzt( true === $e, 'znacznik bez terminu NIE blokuje na zawsze — zostaje przejety', var_export( $e, true ) );
segm_zwolnij_blokade();
segmzt( null === segmzt_w_bazie(), 'po zwolnieniu wiersza nie ma w bazie' );

echo "=== 5. DK-6: uklad wyscigu (B wstawia za plecami A) ===\n";
delete_option( SEGM_BLOKADA );
$widzi_a = get_option( SEGM_BLOKADA ); // A zapamietuje: „nie ma" (cache notoptions)
segmzt_wstaw_obcy( segm_stempel_blokady( 'zamektest: proces B' ), false );
$a_wynik = segm_zajmij_blokade( 'zamektest: proces A' );
$w       = segmzt_w_bazie();
segmzt( false === $widzi_a, 'A przed startem nie widzial znacznika', var_export( $widzi_a, true ) );
segmzt( true !== $a_wynik, 'A dostaje ODMOWE, choc jego cache mowi „wolne"', var_export( $a_wynik, true ) );
segmzt( is_array( $w ) && 'zamektest: proces B' === ( $w['opis'] ?? '' ), 'znacznik dalej nalezy do B (A go nie nadpisal)', is_array( $w ) ? ( $w['opis'] ?? '?' ) : var_export( $w, true ) );
delete_option( SEGM_BLOKADA );

echo "=== 6. BEZ WADY: po wyscigu zwykle zajecie dalej dziala ===\n";
$f = segm_zajmij_blokade( 'zamektest: na koniec' );
segmzt( true === $f, 'czysty zamek daje sie zajac', var_export( $f, true ) );
segm_zwolnij_blokade();

printf( "\nBLEDOW: %d\n", $GLOBALS['segmzt_bledy'] );
exit( $GLOBALS['segmzt_bledy'] > 0 ? 1 : 0 );
