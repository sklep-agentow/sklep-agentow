<?php
/**
 * STRAŻNIK — CZ-1: pytanie gościa ponad limit dostaje ODMOWĘ z liczbą, nie ciche cięcie.
 *
 * Wada (do 1.5.14): `chatl_ajax_pytanie()` robiło `mb_substr( $pytanie, 0, 500 )` po cichu — bot
 * dostawał urwany tekst, odpowiadał na pół pytania, a gość nie wiedział dlaczego.
 *
 * Dwa kierunki (naprawa „odrzuć wszystko” zabrałaby czat):
 *   A) 501 znaków → odpowiedź `success=false`, kod `za_dlugie`, komunikat z liczbą i limitem,
 *      ŻADEN wiersz pytania nie zapisany w rozmowie (urwany tekst nie idzie do bota);
 *   B) dokładnie 500 znaków → NIE `za_dlugie`, wiersz pytania zapisany w całości (500 znaków);
 *   C/D) BLIŹNIAK — wiadomość zgłoszenia: 2001 znaków = odmowa `za_dlugie`, 0 zapisów · 2000 = zapis w całości.
 *
 * Sieć podstawiona (`pre_http_request` zwraca WP_Error) — bot nie woła AI; wiersz pytania powstaje
 * PRZED odpowiedzią, więc B mierzy zapis, nie jakość odpowiedzi.
 * Uruchamiać na STOSIE PRÓBNYM (zakłada i kasuje własną sesję). EXIT 0 = A i B · 1 = WADA · 2 = przyrząd.
 */

global $wpdb;
$ts = $wpdb->prefix . 'chatl_sesje';
$tr = $wpdb->prefix . 'chatl_rozmowy';

function cz1_przyrzad( $powod ) {
	echo 'STRAZNIK: WYNIK=BLAD-SRODOWISKA (' . $powod . ")\n";
	exit( 2 );
}
foreach ( array( 'chatl_ajax_pytanie', 'chatl_ustawienia' ) as $f ) {
	if ( ! function_exists( $f ) ) {
		cz1_przyrzad( 'brak funkcji ' . $f );
	}
}
if ( ! defined( 'CHATL_MAX_PYTANIE' ) ) {
	cz1_przyrzad( 'brak CHATL_MAX_PYTANIE' );
}
$LIMIT  = (int) CHATL_MAX_PYTANIE;
$sesja  = 'cz1LimitTest' . substr( md5( (string) microtime( true ) ), 0, 12 );
$sekret = 'cz1sekret' . wp_generate_password( 8, false, false );

class Cz1_Wyjscie extends Exception {}
function cz1_uchwyt() {
	return function () {
		throw new Cz1_Wyjscie();
	};
}
function cz1_zadanie( $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', 'cz1_uchwyt' );
	add_filter( 'wp_die_handler', 'cz1_uchwyt' );
	ob_start();
	try {
		chatl_ajax_pytanie();
	} catch ( Cz1_Wyjscie $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// wp_send_json_* zawsze kończy żądanie.
	}
	$wyjscie = ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', 'cz1_uchwyt' );
	remove_filter( 'wp_die_handler', 'cz1_uchwyt' );
	return json_decode( $wyjscie, true );
}

// ⛔ WŁASNY adres, nie cudzy kubeł. Poprzednia wersja kasowała `chat|%`, czyli kubły
// limitu WSZYSTKICH adresów na wspólnym stosie warsztatowym — test sprzątał po innych.
// 203.0.113.0/24 to pula zarezerwowana na dokumentację (RFC 5737), więc nie zderzy się
// z niczyim realnym adresem.
$ip_testu                = '203.0.113.' . wp_rand( 2, 254 );
$_SERVER['REMOTE_ADDR']  = $ip_testu;
$klucz_testu             = 'chat|' . $ip_testu;

// Opcję zapisujemy, więc trzeba ją ODDAĆ w stanie zastanym — inaczej test trwale
// przestawia ustawienie na stosie, z którego korzystają inni.
$ust_przed = get_option( 'chatl_ustawienia', null );

$sprzataj = static function () use ( $wpdb, $ts, $tr, $sesja, $klucz_testu, $ust_przed ) {
	$wpdb->delete( $ts, array( 'sesja' => $sesja ) );
	$wpdb->delete( $tr, array( 'sesja' => $sesja ) );
	$wpdb->delete( $wpdb->prefix . 'chatl_rate_limit', array( 'klucz' => $klucz_testu ) );
	if ( null === $ust_przed ) {
		delete_option( 'chatl_ustawienia' );
	} else {
		update_option( 'chatl_ustawienia', $ust_przed, false );
	}
};
register_shutdown_function( $sprzataj );

// Czat włączony (stos próbny może mieć ustawienia domyślne — upewniamy się, nie zakładamy).
$ust             = chatl_ustawienia();
$ust['wlaczony'] = 1;
update_option( 'chatl_ustawienia', $ust, false );

// Sesja z sekretem jak z widgetu: wiersz + ciasteczko (chatl_sesja_uprawniona sprawdza hash).
$wpdb->insert( $ts, array( 'sesja' => $sesja, 'stan' => 'bot', 'klucz_hash' => wp_hash( $sekret ), 'utworzono' => gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - 600 ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- sesja STARSZA niż pułapka czasowa (CHATL_PULAPKA_SEKUND): świeża sesja = bot
if ( ! $wpdb->insert_id && $wpdb->last_error ) {
	cz1_przyrzad( 'nie udalo sie zalozyc sesji: ' . $wpdb->last_error );
}
$_COOKIE[ 'chatl_k_' . $sesja ] = $sekret;
// Bez sieci: AI nie jest wołane, każde żądanie HTTP pada od razu.
$bez_sieci = static function () {
	return new WP_Error( 'cz1', 'bez sieci w tescie' );
};
add_filter( 'pre_http_request', $bez_sieci, 10, 3 );

$wynik = array();
$post  = static function ( $pytanie ) use ( $sesja ) {
	return array( 'nonce' => wp_create_nonce( 'chatl_front' ), 'sesja' => $sesja, 'pytanie' => $pytanie );
};
$ile_pytan = static function () use ( $wpdb, $tr, $sesja ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s AND rola = 'gosc'", $sesja ) ); // phpcs:ignore
};
$dlugosc_ostatniego = static function () use ( $wpdb, $tr, $sesja ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT CHAR_LENGTH(tresc) FROM {$tr} WHERE sesja = %s AND rola = 'gosc' ORDER BY id DESC LIMIT 1", $sesja ) ); // phpcs:ignore
};

/* ---------- A) 501 znaków → odmowa z liczbą, nic nie zapisane ---------- */
$dlugie = str_repeat( 'a', $LIMIT + 1 );
$odp_a  = cz1_zadanie( $post( $dlugie ) );
$kod_a  = is_array( $odp_a ) ? (string) ( $odp_a['data']['kod'] ?? '' ) : '';
$kom_a  = is_array( $odp_a ) ? (string) ( $odp_a['data']['komunikat'] ?? '' ) : '';
$ile_a  = $ile_pytan();
echo 'A. odpowiedz: success=' . ( ! empty( $odp_a['success'] ) ? '1' : '0' ) . ' kod=' . ( '' !== $kod_a ? $kod_a : '(brak)' ) . ' komunikat=' . ( '' !== $kom_a ? $kom_a : '(brak)' ) . "\n";
echo 'A. wierszy pytania goscia w rozmowie: ' . $ile_a . " (OCZEKIWANE: 0 — urwany tekst nie idzie do bota)\n";
$a_ok = is_array( $odp_a ) && empty( $odp_a['success'] ) && 'za_dlugie' === $kod_a
	&& false !== strpos( $kom_a, (string) ( $LIMIT + 1 ) ) && false !== strpos( $kom_a, (string) $LIMIT ) && 0 === $ile_a;
echo 'A: WYNIK=' . ( $a_ok ? 'PASS' : 'FAIL - za dlugie pytanie przyjete po cichu (przyciete) albo bez liczby znakow' ) . "\n";
$wynik[] = $a_ok;

/* ---------- B) dokładnie 500 znaków → przechodzi, zapisane w całości ---------- */
$rowne = str_repeat( 'b', $LIMIT );
$odp_b = cz1_zadanie( $post( $rowne ) );
$kod_b = is_array( $odp_b ) ? (string) ( $odp_b['data']['kod'] ?? '' ) : '';
$ile_b = $ile_pytan();
$dl_b  = $dlugosc_ostatniego();
echo 'B. odpowiedz: kod=' . ( '' !== $kod_b ? $kod_b : '(brak)' ) . ' · wierszy pytania: ' . $ile_b . ' · dlugosc zapisanego: ' . $dl_b . ' (OCZEKIWANE: nie za_dlugie, 1 wiersz, ' . $LIMIT . " znakow)\n";
$b_ok = ( 'za_dlugie' !== $kod_b ) && 1 === $ile_b && $LIMIT === $dl_b;
echo 'B: WYNIK=' . ( $b_ok ? 'PASS' : 'FAIL - pytanie na limicie odrzucone albo zapisane niepelne' ) . "\n";
$wynik[] = $b_ok;

/* ---------- C/D) BLIŹNIAK: wiadomość zgłoszenia (limit CHATL_MAX_WIADOMOSC) ---------- */
$LIMIT_W = defined( 'CHATL_MAX_WIADOMOSC' ) ? (int) CHATL_MAX_WIADOMOSC : 2000;
$tz      = $wpdb->prefix . 'chatl_zgloszenia';
$mail_t  = 'cz1-limit-' . substr( $sesja, -6 ) . '@example.test';
add_filter( 'pre_wp_mail', '__return_true' );
$zgl = static function ( $wiadomosc ) use ( $sesja, $mail_t ) {
	$_POST = $_REQUEST = array( 'nonce' => wp_create_nonce( 'chatl_front' ), 'sesja' => $sesja, 'email' => $mail_t, 'wiadomosc' => $wiadomosc, 'zgoda' => '1' );
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', 'cz1_uchwyt' );
	add_filter( 'wp_die_handler', 'cz1_uchwyt' );
	ob_start();
	try {
		chatl_ajax_zgloszenie();
	} catch ( Cz1_Wyjscie $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// wp_send_json_* zawsze kończy żądanie.
	}
	$w = ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', 'cz1_uchwyt' );
	remove_filter( 'wp_die_handler', 'cz1_uchwyt' );
	return json_decode( $w, true );
};
$ile_zgl = static function () use ( $wpdb, $tz, $mail_t ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", $mail_t ) ); // phpcs:ignore
};
$dl_zgl = static function () use ( $wpdb, $tz, $mail_t ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT CHAR_LENGTH(wiadomosc) FROM {$tz} WHERE email = %s ORDER BY id DESC LIMIT 1", $mail_t ) ); // phpcs:ignore
};
register_shutdown_function( static function () use ( $wpdb, $tz, $mail_t ) { $wpdb->delete( $tz, array( 'email' => $mail_t ) ); } );
if ( ! function_exists( 'chatl_ajax_zgloszenie' ) ) {
	echo "C/D: pominiete — brak chatl_ajax_zgloszenie\n";
} else {
	$odp_c = $zgl( str_repeat( 'c', $LIMIT_W + 1 ) );
	$kod_c = is_array( $odp_c ) ? (string) ( $odp_c['data']['kod'] ?? '' ) : '';
	$kom_c = is_array( $odp_c ) ? (string) ( $odp_c['data']['komunikat'] ?? '' ) : '';
	$ile_c = $ile_zgl();
	echo 'C. zgloszenie ' . ( $LIMIT_W + 1 ) . ' znakow: success=' . ( ! empty( $odp_c['success'] ) ? '1' : '0' ) . ' kod=' . ( '' !== $kod_c ? $kod_c : '(brak)' ) . ' · zapisanych zgloszen: ' . $ile_c . " (OCZEKIWANE: za_dlugie, 0)\n";
	$c_ok = is_array( $odp_c ) && empty( $odp_c['success'] ) && 'za_dlugie' === $kod_c && false !== strpos( $kom_c, (string) ( $LIMIT_W + 1 ) ) && 0 === $ile_c;
	echo 'C: WYNIK=' . ( $c_ok ? 'PASS' : 'FAIL - za dluga wiadomosc zgloszenia przyjeta po cichu (urwana u wlasciciela)' ) . "\n";
	$wynik[] = $c_ok;
	$odp_d = $zgl( str_repeat( 'd', $LIMIT_W ) );
	$kod_d = is_array( $odp_d ) ? (string) ( $odp_d['data']['kod'] ?? '' ) : '';
	$ile_d = $ile_zgl();
	$dl_d  = $dl_zgl();
	echo 'D. zgloszenie ' . $LIMIT_W . ' znakow: kod=' . ( '' !== $kod_d ? $kod_d : '(brak)' ) . ' · zapisanych: ' . $ile_d . ' · dlugosc: ' . $dl_d . ' (OCZEKIWANE: nie za_dlugie, 1, ' . $LIMIT_W . ")\n";
	$d_ok = ( 'za_dlugie' !== $kod_d ) && 1 === $ile_d && $LIMIT_W === $dl_d;
	echo 'D: WYNIK=' . ( $d_ok ? 'PASS' : 'FAIL - wiadomosc na limicie odrzucona albo zapisana niepelna' ) . "\n";
	$wynik[] = $d_ok;
}
remove_filter( 'pre_wp_mail', '__return_true' );

remove_filter( 'pre_http_request', $bez_sieci, 10 );
$ok = ! in_array( false, $wynik, true );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
