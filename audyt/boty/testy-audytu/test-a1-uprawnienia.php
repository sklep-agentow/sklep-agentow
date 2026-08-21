<?php
/**
 * STRAŻNIK A1 — para NONCE + UPRAWNIENIE na KAŻDEJ akcji `admin_post_` obu wtyczek.
 *
 * Co pilnuje: żeby nikt nie dopisał mutacji panelu, którą da się wywołać z konta
 * BEZ uprawnień. Test nadaje roli WŁASNY, WAŻNY nonce — dzięki temu bramka nonce'a
 * jest spełniona i jedyną przeszkodą, jaka zostaje, jest UPRAWNIENIE. Uchwyt, który
 * w takich warunkach wykona się do końca, jest dziurą.
 *
 * ⛔ DWA ZABEZPIECZENIA PRZED FAŁSZYWĄ ZIELENIĄ (obie pułapki złapane w trakcie audytu):
 *   1. W WP-CLI `is_admin()` jest FALSE, więc pliki panelu NIE ładują się same i żaden
 *      hak `admin_post_` nie powstaje. Pierwsza wersja tej próby znalazła ZERO akcji
 *      i wyglądała identycznie jak „wszystko czyste". Dlatego pliki doczytujemy jawnie...
 *   2. ...a gdy akcji jest mniej niż PRÓG, test PADA zamiast przejść.
 *
 * Wartości w $_POST są NIESZKODLIWE (identyfikatory, których nie ma), żeby ewentualne
 * przepuszczenie nie popsuło stanu. Konta testowe są zakładane i KASOWANE po próbie.
 *
 * Uruchomienie:
 *   docker cp audyt/boty/testy-audytu/test-a1-uprawnienia.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
 * Kod wyjścia 0 = każda mutacja odmówiła, 1 = któraś przepuściła, 2 = próba się nie odbyła.
 */

const A1_PROG_AKCJI = 40;
const A1_ID_PROBNY  = 999999; // identyfikator, ktorego nie ma — i czlon nonce'a sklejanego dynamicznie // w chwili pisania: 47 (17 ChatBot + 30 Segregator)

foreach ( array( 'chatbot-live', 'segregator-maila' ) as $wt ) {
	foreach ( glob( WP_PLUGIN_DIR . '/' . $wt . '/includes/*.php' ) as $plik ) {
		require_once $plik;
	}
}

add_filter( 'wp_die_handler', function () {
	return function ( $wiadomosc ) {
		if ( is_wp_error( $wiadomosc ) ) { $wiadomosc = $wiadomosc->get_error_message(); }
		throw new Exception( 'WP_DIE: ' . wp_strip_all_tags( (string) $wiadomosc ) );
	};
}, 99 );

$akcje = array();
foreach ( $GLOBALS['wp_filter'] as $hak => $obj ) {
	if ( 0 !== strpos( $hak, 'admin_post_' ) || 0 === strpos( $hak, 'admin_post_nopriv_' ) ) { continue; }
	foreach ( $obj->callbacks as $cb ) {
		foreach ( $cb as $w ) {
			$f = $w['function'];
			if ( is_string( $f ) && ( 0 === strpos( $f, 'chatl_' ) || 0 === strpos( $f, 'segm_' ) ) ) { $akcje[ $hak ] = $f; }
		}
	}
}
ksort( $akcje );
printf( "Akcji admin_post_ znalezionych: %d\n", count( $akcje ) );
if ( count( $akcje ) < A1_PROG_AKCJI ) {
	printf( "STOP: prog to %d — proba SIE NIE ODBYLA (nie ladujesz plikow panelu?). To NIE jest wynik 'czysto'.\n", A1_PROG_AKCJI );
	exit( 2 );
}

/** Nazwa nonce'a ze ŹRÓDŁA uchwytu (i pomocników), nie zgadywana. */
function a1_nonce_akcji( $uchwyt ) {
	$szukaj = function ( $fn ) {
		if ( ! function_exists( $fn ) ) { return null; }
		$r = new ReflectionFunction( $fn );
		if ( ! $r->getFileName() ) { return null; }
		$src = implode( '', array_slice( file( $r->getFileName() ), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
		// Nonce sklejany ze zmiennej: check_admin_referer( 'chatl_qa_usun_' . $id ).
		// ⛔ Bez tego uchwyt odmawia z powodu NONCE'A, a sprawdzenie uprawnienia
		// nigdy nie zostaje dotkniete — i test swieci zielono, nic nie zmierzywszy.
		if ( preg_match( "/check_admin_referer\(\s*'([^']+)'\s*\.\s*\\\$[a-z_]+/", $src, $m ) ) { return $m[1] . A1_ID_PROBNY; }
		if ( preg_match( "/check_admin_referer\(\s*'([^']+)'/", $src, $m ) ) { return $m[1]; }
		if ( preg_match( "/wp_verify_nonce\(\s*[^,]+,\s*'([^']+)'/", $src, $m ) ) { return $m[1]; }
		return null;
	};
	$w = $szukaj( $uchwyt );
	if ( null !== $w ) { return $w; }
	$r   = new ReflectionFunction( $uchwyt );
	$src = implode( '', array_slice( file( $r->getFileName() ), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
	// Pomocnik z nonce'em przekazanym w argumencie (np. chatl_csv_brama( 'akcja' )).
	if ( preg_match( "/\b(?:chatl|segm)_[a-z0-9_]+\(\s*'([^']+)'\s*\)/", $src, $m ) ) { return $m[1]; }
	return null;
}

$konta = array();
foreach ( array( 'subskrybent' => 'subscriber', 'redaktor' => 'editor' ) as $etykieta => $rola ) {
	$login = 'a1straz_' . $rola;
	$u     = get_user_by( 'login', $login );
	$id    = $u ? (int) $u->ID : (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 20 ), 'user_email' => $login . '@straznik.invalid', 'role' => $rola ) );
	$konta[ $etykieta ] = $id;
}
register_shutdown_function( function () use ( $konta ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $konta as $id ) { if ( $id > 0 ) { wp_delete_user( $id ); } }
	echo "[konta testowe skasowane]\n";
} );

$przeszly = 0;
$niezmierzone = 0;
$GLOBALS['a1_w_trakcie'] = '';
$GLOBALS['a1_podsumowano'] = false;
register_shutdown_function( function () {
	if ( $GLOBALS['a1_podsumowano'] ) { return; }
	$gdzie = (string) $GLOBALS['a1_w_trakcie'];
	echo "\n  BLAD  uchwyt PRZESZEDL DO KONCA i ubil test przez exit(): " . ( '' !== $gdzie ? $gdzie : '(nieznany)' ) . "\n";
	echo "NIEZALICZONE (ciche wyjscie w trakcie proby)\n";
	exit( 1 );
} );
foreach ( $konta as $etykieta => $uid ) {
	printf( "\n=== ROLA: %s (ID %d) ===\n", $etykieta, $uid );
	foreach ( $akcje as $akcja => $uchwyt ) {
		wp_set_current_user( $uid );
		$nonce_akcja = a1_nonce_akcji( $uchwyt );
		$_POST       = array( 'action' => str_replace( 'admin_post_', '', $akcja ), '_wp_http_referer' => '/wp-admin/' );
		foreach ( array( 'id', 'regula_id', 'kategoria_id', 'mail_id', 'qa_id', 'zgloszenie_id', 'przebieg_id', 'sesja_id', 'rozmowa_id' ) as $k ) { $_POST[ $k ] = A1_ID_PROBNY; }
		if ( null !== $nonce_akcja ) { $_POST['_wpnonce'] = wp_create_nonce( $nonce_akcja ); }
		$_GET     = $_POST; // czesc uchwytow czyta id z adresu
		$_REQUEST = $_POST;
		// ⛔ Uchwyt, ktory PRZEJDZIE do konca, konczy sie wp_safe_redirect() + exit —
		// a exit ubija caly proces testu, wiec podsumowanie nigdy sie nie wypisuje
		// i powloka widzi kod 0. Znacznik ponizej przezywa exit: funkcja zamkniecia
		// zamienia takie ciche wyjscie w glosna porazke.
		$GLOBALS['a1_w_trakcie'] = $akcja . ' / ' . $etykieta;
		try {
			ob_start(); $uchwyt(); ob_end_clean();
			printf( "  BLAD  %-42s PRZEPUSCILA konto bez uprawnien\n", $akcja );
			++$przeszly;
		} catch ( Exception $e ) {
			$kom = $e->getMessage();
			$przez_uprawnienie = false !== mb_stripos( $kom, 'uprawnie' );
			if ( $przez_uprawnienie ) {
				printf( "  OK    %-42s odmowa przez UPRAWNIENIE\n", $akcja );
			} else {
				// Odmowa nastapila, ale z powodu nonce'a — o uprawnieniu ta proba
				// NIE MOWI NIC. Zgloszenie tego wprost jest cala wartoscia straznika.
				printf( "  BLAD  %-42s odmowa TYLKO przez nonce (%s) — uprawnienie NIEZMIERZONE\n", $akcja, mb_substr( $kom, 0, 40 ) );
				++$niezmierzone;
			}
		} catch ( Error $e ) {
			printf( "  BLAD  %-42s blad PHP zamiast odmowy: %s\n", $akcja, mb_substr( $e->getMessage(), 0, 50 ) );
			++$przeszly;
		}
		$GLOBALS['a1_w_trakcie'] = '';
	}
}

printf( "\nPROB: %d (%d akcji x %d role)\n", count( $akcje ) * count( $konta ), count( $akcje ), count( $konta ) );
printf( "PRZEPUSZCZONYCH: %d\n", $przeszly );
printf( "ODMOW TYLKO PRZEZ NONCE (uprawnienie niezmierzone): %d\n", $niezmierzone );
$GLOBALS['a1_podsumowano'] = true;
if ( $przeszly > 0 || $niezmierzone > 0 ) { echo "NIEZALICZONE\n"; exit( 1 ); }
echo "KAZDA MUTACJA ODMOWILA KONTU BEZ UPRAWNIEN.\n";
exit( 0 );
