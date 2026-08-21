<?php
/**
 * PADAJACY TEST — soczewka KRAWEDZIE: puste / dokladnie maksimum / jeden ponad.
 * Kod obu wtyczek wykonuje sie NAPRAWDE; nic nie jest atrapa.
 */
global $wpdb;
foreach ( array( 'chatbot-live/includes/admin.php', 'chatbot-live/includes/operator.php', 'chatbot-live/includes/import.php', 'chatbot-live/includes/csv.php', 'chatbot-live/includes/statystyki.php', 'chatbot-live/includes/powitanie.php', 'segregator-maila/includes/csv.php', 'segregator-maila/includes/admin.php', 'segregator-maila/includes/admin-probka.php', 'segregator-maila/includes/admin-skrzynka.php', 'segregator-maila/includes/admin-powitanie.php', 'segregator-maila/includes/filtry.php', 'segregator-maila/includes/konfiguracja.php' ) as $p ) {
	if ( file_exists( WP_PLUGIN_DIR . '/' . $p ) ) { require_once WP_PLUGIN_DIR . '/' . $p; }
}
wp_set_current_user( 1 );
$GLOBALS['PAD'] = 0; $GLOBALS['ZDA'] = 0;
function zdaj( $id, $ok, $co ) {
	$GLOBALS['ZDA']++; if ( ! $ok ) { $GLOBALS['PAD']++; }
	printf( "%-5s %-9s %s\n", $id, $ok ? 'PRZESZLO' : 'PADA', $co );
}
function gdzie( $fn ) {
	$p = function ( $l ) { throw new Exception( 'R:' . $l ); };
	add_filter( 'wp_redirect', $p, 1 );
	try { $fn(); $w = '(brak przekierowania)'; } catch ( Exception $e ) { $w = substr( $e->getMessage(), 2 ); }
	remove_filter( 'wp_redirect', $p, 1 ); return $w;
}
function zapisz_ustawienia_chatl( $post ) {
	$_POST = $post;
	$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
	$a = gdzie( 'chatl_post_ustawienia_zapisz' ); wp_cache_flush(); return $a;
}

echo "===== POLA LICZBOWE: pusta wartosc (to wysyla przegladarka, gdy tresc pola\n";
echo "      type=number jest nieparsowalna: \"50 000\", \"50,000\", \"abc\") =====\n";

// Stan wyjsciowy: oba stop-lossy ustawione swiadomie.
$u = chatl_ustawienia(); $u['ai_limit_tokenow'] = 500000; $u['ai_limit_dzienny'] = 300;
update_option( 'chatl_ustawienia', $u ); wp_cache_flush();
printf( "     przed: limit_tokenow=%d, limit_dzienny=%d\n", chatl_ustawienia()['ai_limit_tokenow'], chatl_ustawienia()['ai_limit_dzienny'] );

$adres = zapisz_ustawienia_chatl( array( 'wlaczony' => 1, 'ai_limit_tokenow' => '', 'ai_limit_dzienny' => '' ) );
$tok = (int) chatl_ustawienia()['ai_limit_tokenow'];
$dzi = (int) chatl_ustawienia()['ai_limit_dzienny'];
printf( "     po   : limit_tokenow=%d  (0 = STOP-LOSS WYLACZONY), limit_dzienny=%d\n", $tok, $dzi );
printf( "     wlasciciel zobaczyl: %s\n", $adres );
zdaj( 'KR1', 500000 === $tok || false === strpos( $adres, 'chatl_ok=zapisano' ),
	'ChatBot: pusta wartosc w polu "limit tokenow" NIE moze po cichu WYLACZYC stop-lossu (0 = wylaczony)' );
zdaj( 'KR2', 300 === $dzi || false === strpos( $adres, 'chatl_ok=zapisano' ),
	'ChatBot: pusta wartosc w polu "limit dzienny" NIE moze po cichu zjechac limitu do 1' );

$adres = zapisz_ustawienia_chatl( array( 'wlaczony' => 1, 'ai_limit_tokenow' => '250000', 'ai_limit_dzienny' => '77' ) );
printf( "     kontrola: poprawne wartosci -> limit_tokenow=%d, limit_dzienny=%d\n", chatl_ustawienia()['ai_limit_tokenow'], chatl_ustawienia()['ai_limit_dzienny'] );
zdaj( 'KR3', 250000 === (int) chatl_ustawienia()['ai_limit_tokenow'] && 77 === (int) chatl_ustawienia()['ai_limit_dzienny'],
	'PROBA KONTROLNA: poprawne liczby zapisuja sie tym samym przebiegiem' );

// Ten sam wzorzec w drugiej wtyczce: port i limit skrzynki IMAP.
$s = get_option( 'segm_skrzynka', array() ); $s = is_array( $s ) ? $s : array();
$s['port'] = 993; $s['limit'] = 200; $s['serwer'] = 'imap.example.test'; $s['login'] = 'x@example.test';
update_option( 'segm_skrzynka', $s ); wp_cache_flush();
$_POST = array( 'port' => '', 'limit' => '', 'serwer' => 'imap.example.test', 'login' => 'x@example.test' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_skrzynka_zapisz' );
$adres = gdzie( 'segm_post_skrzynka_zapisz' ); wp_cache_flush();
$sk = get_option( 'segm_skrzynka', array() );
printf( "     Segregator po pustych polach: port=%s, limit=%s\n", isset( $sk['port'] ) ? $sk['port'] : '?', isset( $sk['limit'] ) ? $sk['limit'] : '?' );
printf( "     wlasciciel zobaczyl: %s\n", $adres );
zdaj( 'KR4', isset( $sk['port'] ) && 993 === (int) $sk['port'],
	'Segregator: pusty port IMAP NIE moze po cichu stac sie portem 1' );

echo "\n===== RETENCJA: wpis DOKLADNIE na granicy (dzien 90) i dzien ponad (91) =====\n";
$zapamietany_offset = get_option( 'gmt_offset' );
$zapamietana_strefa = get_option( 'timezone_string' );
update_option( 'timezone_string', '' );
update_option( 'gmt_offset', 0 );
wp_cache_flush();

$tr = $wpdb->prefix . 'chatl_rozmowy';
$wpdb->query( "DELETE FROM {$tr} WHERE sesja LIKE 'KRAW-%'" );
$wstaw = function ( $sesja, $sekund_temu ) use ( $wpdb, $tr ) {
	$wpdb->insert( $tr, array(
		'sesja' => $sesja, 'rola' => 'gosc', 'silnik' => 'reguly', 'tresc' => 'krawedz',
		'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - $sekund_temu ),
	), array( '%s', '%s', '%s', '%s', '%s' ) );
};
$wstaw( 'KRAW-89d', 89 * DAY_IN_SECONDS );
$wstaw( 'KRAW-90d', 90 * DAY_IN_SECONDS - 60 );   // dokladnie na granicy, o minute mlodszy
$wstaw( 'KRAW-91d', 91 * DAY_IN_SECONDS );
chatl_czyszczenie_historii();
$zostalo = $wpdb->get_col( "SELECT sesja FROM {$tr} WHERE sesja LIKE 'KRAW-%' ORDER BY sesja" );
echo "     przy strefie UTC zostalo: " . ( $zostalo ? implode( ', ', $zostalo ) : '(nic)' ) . "\n";
zdaj( 'KR5', in_array( 'KRAW-89d', $zostalo, true ) && in_array( 'KRAW-90d', $zostalo, true ) && ! in_array( 'KRAW-91d', $zostalo, true ),
	'ChatBot: dzien 90 zostaje, dzien 91 znika (strefa UTC) — sama granica' );

// Ta sama granica, ale strefa UJEMNA (kupujacy w USA): -5 h.
update_option( 'gmt_offset', -5 ); wp_cache_flush();
$wpdb->query( "DELETE FROM {$tr} WHERE sesja LIKE 'KRAW-%'" );
$wstaw( 'KRAW-89d20h', 89 * DAY_IN_SECONDS + 20 * HOUR_IN_SECONDS ); // MLODSZY niz 90 dni
chatl_czyszczenie_historii();
$zyje = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tr} WHERE sesja = 'KRAW-89d20h'" );
printf( "     ChatBot, gmt_offset=-5: wpis wieku 89 dni 20 h przezyl? %s\n", $zyje ? 'TAK' : 'NIE — skasowany PRZED czasem' );
zdaj( 'KR6', 1 === $zyje, 'ChatBot: wpis MLODSZY niz 90 dni nie moze zniknac (obietnica retencji 90 dni)' );

// Kontrola: ta sama granica w Segregatorze, ktory poprawke o strefe MA.
$tm = $wpdb->prefix . 'segm_maile';
$wpdb->query( "DELETE FROM {$tm} WHERE nadawca = 'krawedz@test'" );
$wpdb->insert( $tm, array(
	'nadawca' => 'krawedz@test', 'temat' => 'krawedz', 'tresc' => 'krawedz',
	'zrodlo' => 'csv', 'kategoria_oczekiwana' => '',
	'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - ( 89 * DAY_IN_SECONDS + 20 * HOUR_IN_SECONDS ) ),
), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
if ( ! $wpdb->insert_id ) { echo "     ⛔ PRZYRZAD: wiersz kontrolny NIE powstal — " . $wpdb->last_error . "\n"; }
segm_czyszczenie_probki();
$zyje_s = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tm} WHERE nadawca = 'krawedz@test'" );
printf( "     Segregator, gmt_offset=-5: wpis wieku 89 dni 20 h przezyl? %s\n", $zyje_s ? 'TAK' : 'NIE' );
zdaj( 'KR7', 1 === $zyje_s, 'PROBA KONTROLNA: blizniacza retencja Segregatora na tej samej granicy i strefie' );

// Sprzatanie po sobie.
$wpdb->query( "DELETE FROM {$tr} WHERE sesja LIKE 'KRAW-%'" );
$wpdb->query( "DELETE FROM {$tm} WHERE nadawca = 'krawedz@test'" );
update_option( 'gmt_offset', $zapamietany_offset );
update_option( 'timezone_string', $zapamietana_strefa );
$u['ai_limit_tokenow'] = 0; $u['ai_limit_dzienny'] = 200; update_option( 'chatl_ustawienia', $u );

echo "\nZDANYCH " . ( $GLOBALS['ZDA'] - $GLOBALS['PAD'] ) . "/" . $GLOBALS['ZDA'] . ", PADA " . $GLOBALS['PAD'] . "\n";
exit( $GLOBALS['PAD'] > 0 ? 1 : 0 );
