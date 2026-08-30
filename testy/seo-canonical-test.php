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

// Opcja strony sklepu PUSTA (aktywacja nie utworzyła strony): is_page( 0 ) jest prawdą wszędzie,
// więc bez bramki wtyczka pisała canonical i meta listy w cudze <head>. Opcję podmieniamy tylko
// w pamięci procesu (pre_option_), baza nietknięta.
if ( $inna ) {
	$pusta = function () {
		return 0;
	};
	add_filter( 'pre_option_sklepag_strona_sklep', $pusta );
	$f     = sklepag_test_canonicale( array( 'page_id' => $inna ) );
	$nasze = array_filter( $f, function ( $h ) { return false !== strpos( $h, '/uslugi/' ) || false !== strpos( $h, '/produkt/' ); } );
	sprawdz( 'F. opcja strony sklepu pusta, cudza strona: zero naszych canonicali', 0 === count( $nasze ), 'mam ' . implode( ' | ', $f ) );
	$q                       = new WP_Query( array( 'page_id' => $inna ) );
	$GLOBALS['wp_the_query'] = $q;
	$GLOBALS['wp_query']     = $q;
	ob_start();
	sklepag_seo_head();
	$glowa = ob_get_clean();
	sprawdz( 'F. …i zero meta listy (description/og:url) w cudzym <head>', '' === trim( $glowa ), 'mam: ' . substr( $glowa, 0, 120 ) );
	// Kolejka assetów jest globalna — kroki A–C (strona produktu) już ją napełniły; zerujemy przed pomiarem.
	wp_dequeue_style( 'sklepag-front' );
	wp_dequeue_script( 'sklepag-ruch' );
	do_action( 'wp_enqueue_scripts' );
	sprawdz( 'F. …i zero assetów sklepu (front.css / front-ruch.js) na cudzej stronie', ! wp_style_is( 'sklepag-front', 'enqueued' ) && ! wp_script_is( 'sklepag-ruch', 'enqueued' ), 'front.css=' . var_export( wp_style_is( 'sklepag-front', 'enqueued' ), true ) . ' ruch=' . var_export( wp_script_is( 'sklepag-ruch', 'enqueued' ), true ) );
	remove_filter( 'pre_option_sklepag_strona_sklep', $pusta );
	sprawdz( 'F. kontrola: opcja w bazie nietknięta', (int) get_option( 'sklepag_strona_sklep' ) === $strona_sklep );
}

// 404 nieistniejącego produktu: także bez meta listy (og:url nie może wskazywać /sklep/).
$q                       = new WP_Query( array( 'page_id' => $strona_sklep, 'sklepag_produkt' => 'nie-ma-takiego-produktu' ) );
$GLOBALS['wp_the_query'] = $q;
$GLOBALS['wp_query']     = $q;
ob_start();
sklepag_seo_head();
$glowa404 = ob_get_clean();
sprawdz( 'G. nieistniejący produkt (404): zero og:url/description listy', false === strpos( $glowa404, 'og:url' ) && false === strpos( $glowa404, 'name="description"' ), 'mam: ' . substr( $glowa404, 0, 120 ) );

echo "\nBŁĘDY: {$GLOBALS['sklepag_test_bledy']}\n";
exit( $GLOBALS['sklepag_test_bledy'] > 0 ? 1 : 0 );
