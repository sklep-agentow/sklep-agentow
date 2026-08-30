<?php
/**
 * L6 — canonical stron sklepu, gdy motyw gospodarza wyciął `rel_canonical` WP.
 * Test-strażnik, kod wyjścia 1 przy porażce.
 *
 * Co się psuło: `sklepag_seo_canonical` filtruje `get_canonical_url`, ale motyw
 * gospodarza robi `remove_action( 'wp_head', 'rel_canonical' )` — więc nikt tego
 * filtra nie wołał i strona produktu oraz lista nie miały ŻADNEGO canonicala
 * (zmierzone 30.08: /sklep/produkt/chatbot-live/ → 0 znaczników).
 *
 * Kroki A–B to wada; C pilnuje, żeby lekarstwo nie było gorsze od choroby
 * (gospodarz, który rel_canonical ZOSTAWIŁ, ma dostać jeden znacznik, nie dwa);
 * D — nie przejmujemy cudzych stron.
 *
 * Uruchomienie:
 *   docker cp testy/seo-canonical-test.php strona_wp_wordpress:/tmp/canon.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/canon.php
 */

$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost'; // wp eval-file nie ma żądania HTTP.
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

/** Udaje żądanie strony i zwraca listę href z <link rel="canonical"> w wp_head. */
function sklepag_test_canonicale( array $zapytanie ) {
	$q                        = new WP_Query( $zapytanie );
	$GLOBALS['wp_the_query']  = $q;
	$GLOBALS['wp_query']      = $q;
	ob_start();
	do_action( 'wp_head' );
	$html = ob_get_clean();
	preg_match_all( '/<link rel="canonical" href="([^"]*)"/', $html, $m );
	return $m[1];
}

global $wpdb;
$strona_sklep = (int) get_option( 'sklepag_strona_sklep' );
$slug         = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}sklepag_produkty WHERE aktywny = %d ORDER BY id LIMIT 1", 1 ) );
sprawdz( 'warunek wstępny: strona sklepu i aktywny produkt istnieją', $strona_sklep > 0 && $slug, "strona=$strona_sklep slug=" . var_export( $slug, true ) );
if ( ! $strona_sklep || ! $slug ) {
	exit( 1 );
}
$url_produktu = sklepag_url_produktu_front( $slug );
$url_listy    = get_permalink( $strona_sklep );

// Stan gospodarza z 30.08: rel_canonical WP wycięty.
remove_action( 'wp_head', 'rel_canonical' );

$a = sklepag_test_canonicale( array( 'page_id' => $strona_sklep, 'sklepag_produkt' => $slug ) );
sprawdz( 'A. produkt bez rel_canonical WP: dokładnie jeden canonical', 1 === count( $a ), 'mam ' . count( $a ) . ': ' . implode( ' | ', $a ) );
sprawdz( 'A. …i wskazuje przyjazny adres produktu', 1 === count( $a ) && $a[0] === $url_produktu, "oczekiwano [$url_produktu]" );

$b = sklepag_test_canonicale( array( 'page_id' => $strona_sklep ) );
sprawdz( 'B. lista bez rel_canonical WP: dokładnie jeden canonical = adres listy', 1 === count( $b ) && $b[0] === $url_listy, 'mam ' . implode( ' | ', $b ) . " oczekiwano [$url_listy]" );

// Gospodarz, który rel_canonical ZOSTAWIŁ: WP drukuje swój (przez nasz filtr), my milczymy.
add_action( 'wp_head', 'rel_canonical' );
$c = sklepag_test_canonicale( array( 'page_id' => $strona_sklep, 'sklepag_produkt' => $slug ) );
sprawdz( 'C. produkt Z rel_canonical WP: nadal dokładnie jeden (nie dwa) i adres produktu', 1 === count( $c ) && $c[0] === $url_produktu, 'mam ' . count( $c ) . ': ' . implode( ' | ', $c ) );
remove_action( 'wp_head', 'rel_canonical' );

// Cudza strona gospodarza: nie nasz teren.
$inna = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND ID <> %d LIMIT 1", $strona_sklep ) );
if ( $inna ) {
	$d = sklepag_test_canonicale( array( 'page_id' => $inna ) );
	$nasze = array_filter( $d, function ( $h ) use ( $url_listy ) { return $h === $url_listy || false !== strpos( $h, '/produkt/' ); } );
	sprawdz( 'D. cudza strona: wtyczka nie dokłada swojego canonicala', 0 === count( $nasze ), 'mam ' . implode( ' | ', $d ) );
}

// Nieistniejący produkt = 404: żadnego canonicala (nie wolno wskazać listy jako „głównej” wersji 404).
$e = sklepag_test_canonicale( array( 'page_id' => $strona_sklep, 'sklepag_produkt' => 'nie-ma-takiego-produktu' ) );
sprawdz( 'E. nieistniejący produkt (404): zero canonicali', 0 === count( $e ), 'mam ' . implode( ' | ', $e ) );

echo "\nBŁĘDY: {$GLOBALS['sklepag_test_bledy']}\n";
exit( $GLOBALS['sklepag_test_bledy'] > 0 ? 1 : 0 );
