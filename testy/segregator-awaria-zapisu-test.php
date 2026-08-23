<?php
/**
 * STRAŻNIK „skąd kod wie, że się udało" — Segregator (A2-F3 bliźniak, A2-F4, KR4).
 *
 * Audyt A2 pokazał klasę: zapis do bazy bez pomiaru wyniku → panel mówi
 * „zapisano", a w bazie zostaje stara wartość; znaczniki awarii same padają po
 * cichu. Audyt A5 (KR4): puste pole liczbowe po cichu staje się wartością
 * graniczną (port 1, limit 1), a właściciel dostaje „Zapisano".
 *
 * Co mierzy (każdy punkt ma parę AWARIA / BEZ WADY):
 *   A. `segm_zapisz_klucz()` zwraca wynik: przy odrzuconym zapisie false i stary
 *      klucz w bazie (obie gałęzie: UPDATE istniejącego i INSERT nowego);
 *      przy działającej bazie true i nowy klucz; kasowanie → true i pusto;
 *   B. handler ustawień przy odrzuconym zapisie klucza NIE mówi „zapisano",
 *      tylko o niezapisanym kluczu; przy działającej bazie → „zapisano";
 *   C. `segm_zapisz_blad_ai()` (znacznik awarii trybu AI) — gdy sam pada,
 *      zostawia ślad w `segm_blad_bazy` (funkcja = segm_zapisz_blad_ai);
 *      gdy działa — znacznik zapisany, śladu błędu nie ma;
 *   D. KR4-bliźniak: puste „dzienny limit wywołań AI" NIE zjeżdża do 1 —
 *      poprzednia wartość zostaje, komunikat ≠ „zapisano"; liczba → zapisana;
 *   E. KR4: puste „port"/„limit" skrzynki → poprzednie wartości zostają,
 *      komunikat panelu ≠ zielone „Zapisano"; poprawne liczby → zapisane, „ok".
 *
 * Awarię bazy podkłada filtr `query` (jak zamrożony `test-awaria-zapisu.php`).
 * Test ODDAJE wspólnemu stosowi stan sprzed próby: klucz API, ustawienia,
 * znaczniki, ustawienia skrzynki, transient komunikatu — także po padzie.
 * Pomija A/B, gdy klucz jest w stałej (panel go wtedy nie zapisuje).
 *
 * Uruchomienie:
 *   docker cp testy/segregator-awaria-zapisu-test.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
 * EXIT 0 = wszystko OK, 1 = któryś punkt padł.
 */
global $wpdb;
if ( ! function_exists( 'segm_zapisz_klucz' ) ) {
	echo "POMINIETO: wtyczka segregator-maila nie jest zaladowana.\n";
	exit( 0 );
}
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/admin.php';
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/admin-skrzynka.php';

$GLOBALS['segmaz_bledy'] = 0;
function segmaz( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " ($szczegol)" : '' );
	if ( ! $ok ) {
		++$GLOBALS['segmaz_bledy'];
	}
	return $ok;
}
/** Psuje zapytania danego rodzaju (UPDATE/INSERT), które dotykają $igla. */
function segmaz_psuj( $igla, $rodzaj = 'UPDATE' ) {
	$f = function ( $q ) use ( $igla, $rodzaj ) {
		if ( false !== stripos( $q, $igla ) && 0 === stripos( ltrim( $q ), $rodzaj ) ) {
			return 'UPDATE ' . $GLOBALS['wpdb']->options . ' SET option_value = 1 WHERE option_id = (SELECT 1 FROM segmaz_tabela_ktorej_nie_ma)';
		}
		return $q;
	};
	add_filter( 'query', $f, 999 );
	return $f;
}
function segmaz_odpsuj( $f ) {
	remove_filter( 'query', $f, 999 );
}
/** Uruchamia handler admin_post i zwraca adres przekierowania (zdekodowany). */
function segmaz_dokad( $fn ) {
	$przechwyt = function ( $l ) {
		throw new Exception( 'R:' . $l );
	};
	add_filter( 'wp_redirect', $przechwyt, 1 );
	try {
		$fn();
		$wynik = '(brak przekierowania)';
	} catch ( Exception $e ) {
		$wynik = substr( $e->getMessage(), 2 );
	}
	remove_filter( 'wp_redirect', $przechwyt, 1 );
	return rawurldecode( $wynik );
}
function segmaz_slad_funkcja() {
	$w = get_option( 'segm_blad_bazy' );
	return is_array( $w ) && isset( $w['funkcja'] ) ? (string) $w['funkcja'] : '';
}
function segmaz_komunikat_skrzynki() {
	$k = get_transient( 'segm_komunikat_' . get_current_user_id() );
	return is_array( $k ) ? $k : array( 'typ' => '(brak)', 'tekst' => '' );
}

// ---------------------------------------------------------------- stan sprzed
$GLOBALS['segmaz_stan'] = array(
	'segm_klucz_api'    => get_option( 'segm_klucz_api', null ),
	'segm_ustawienia'   => get_option( 'segm_ustawienia', null ),
	'segm_blad_ai'      => get_option( 'segm_blad_ai', null ),
	'segm_blad_bazy'    => get_option( 'segm_blad_bazy', null ),
	'segm_skrzynka'     => get_option( 'segm_skrzynka', null ),
	'transient'         => get_transient( 'segm_komunikat_' . get_current_user_id() ),
);
register_shutdown_function(
	function () {
		remove_all_filters( 'query' );
		$wpdb = $GLOBALS['wpdb'];
		$wpdb->suppress_errors( false );
		foreach ( $GLOBALS['segmaz_stan'] as $nazwa => $wartosc ) {
			if ( 'transient' === $nazwa ) {
				continue;
			}
			if ( null === $wartosc ) {
				delete_option( $nazwa );
			} elseif ( 'segm_klucz_api' === $nazwa ) {
				// klucz: autoload=no jak w produkcie
				delete_option( $nazwa );
				add_option( $nazwa, $wartosc, '', false );
			} else {
				update_option( $nazwa, $wartosc );
			}
		}
		$t = 'segm_komunikat_' . get_current_user_id();
		if ( false === $GLOBALS['segmaz_stan']['transient'] ) {
			delete_transient( $t );
		} else {
			set_transient( $t, $GLOBALS['segmaz_stan']['transient'], 5 * MINUTE_IN_SECONDS );
		}
	}
);
// Handlery panelu sprawdzaja uprawnienia i nonce — test biegnie jako admin.
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $admin ? (int) $admin[0] : 1 );
$wpdb->suppress_errors( true );

// ------------------------------------------------- A. segm_zapisz_klucz() ma wynik
echo "=== A. A2-F3: segm_zapisz_klucz() zwraca wynik zmierzony odczytem ===\n";
if ( segm_klucz_ze_stalej() ) {
	echo "  (pominieto A i B: klucz ze stalej — panel go nie zapisuje)\n";
} else {
	delete_option( 'segm_klucz_api' );
	add_option( 'segm_klucz_api', 'segmaz-STARY', '', false );
	$f = segmaz_psuj( 'segm_klucz_api', 'UPDATE' );
	$r = segm_zapisz_klucz( 'segmaz-NOWY' );
	segmaz_odpsuj( $f );
	wp_cache_flush();
	segmaz( false === $r, 'AWARIA (UPDATE): zwraca false', var_export( $r, true ) );
	segmaz( 'segmaz-STARY' === get_option( 'segm_klucz_api' ), 'AWARIA (UPDATE): w bazie zostal stary klucz', (string) get_option( 'segm_klucz_api' ) );

	delete_option( 'segm_klucz_api' );
	$f = segmaz_psuj( 'segm_klucz_api', 'INSERT' );
	$r = segm_zapisz_klucz( 'segmaz-NOWY' );
	segmaz_odpsuj( $f );
	wp_cache_flush();
	segmaz( false === $r, 'AWARIA (INSERT, brak klucza): zwraca false', var_export( $r, true ) );
	segmaz( '' === (string) get_option( 'segm_klucz_api', '' ), 'AWARIA (INSERT): w bazie nadal pusto' );

	$r = segm_zapisz_klucz( 'segmaz-NOWY' );
	segmaz( true === $r && 'segmaz-NOWY' === get_option( 'segm_klucz_api' ), 'BEZ WADY: dzialajaca baza → true i nowy klucz w bazie', var_export( $r, true ) );
	$r = segm_zapisz_klucz( 'segmaz-NOWY2' );
	segmaz( true === $r && 'segmaz-NOWY2' === get_option( 'segm_klucz_api' ), 'BEZ WADY: podmiana istniejacego → true', var_export( $r, true ) );
	$r = segm_zapisz_klucz( '' );
	segmaz( true === $r && '' === (string) get_option( 'segm_klucz_api', '' ), 'BEZ WADY: kasowanie → true i pusto', var_export( $r, true ) );

	// --------------------------------------- B. handler ustawien reaguje na wynik
	echo "\n=== B. A2-F3: handler ustawien NIE mowi \"zapisano\", gdy klucz nie wszedl ===\n";
	delete_option( 'segm_klucz_api' );
	add_option( 'segm_klucz_api', 'segmaz-STARY', '', false );
	$ust_przed = segm_ustawienia();
	$_POST     = array(
		'ai_limit_dzienny' => (string) $ust_przed['ai_limit_dzienny'],
		'klucz_api'        => 'segmaz-Z-PANELU',
	);
	$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_ustawienia_zapisz' );
	$f                    = segmaz_psuj( 'segm_klucz_api', 'UPDATE' );
	$adres                = segmaz_dokad( 'segm_post_ustawienia_zapisz' );
	segmaz_odpsuj( $f );
	wp_cache_flush();
	segmaz( false === strpos( $adres, 'segm_ok=zapisano' ), 'AWARIA: brak „zapisano" w przekierowaniu', $adres );
	segmaz( false !== stripos( $adres, 'klucz' ), 'AWARIA: komunikat mowi o kluczu', $adres );
	segmaz( 'segmaz-STARY' === get_option( 'segm_klucz_api' ), 'AWARIA: stary klucz zostal' );
	$adres = segmaz_dokad( 'segm_post_ustawienia_zapisz' );
	segmaz( false !== strpos( $adres, 'segm_ok=zapisano' ) && 'segmaz-Z-PANELU' === get_option( 'segm_klucz_api' ), 'BEZ WADY: dzialajaca baza → „zapisano" i nowy klucz', $adres );
}

// ------------------------------------------ C. znacznik awarii AI nie pada po cichu
echo "\n=== C. A2-F4: znacznik awarii trybu AI zostawia slad, gdy sam nie wejdzie ===\n";
delete_option( 'segm_blad_ai' );
delete_option( 'segm_blad_bazy' );
$f  = segmaz_psuj( 'segm_blad_ai', 'UPDATE' );
$f2 = segmaz_psuj( 'segm_blad_ai', 'INSERT' );
segm_zapisz_blad_ai( 'proba segmaz', 0 );
segmaz_odpsuj( $f );
segmaz_odpsuj( $f2 );
wp_cache_flush();
segmaz( 'segm_zapisz_blad_ai' === segmaz_slad_funkcja(), 'AWARIA: padniety znacznik awarii AI zostawia slad bledu bazy', segmaz_slad_funkcja() );
delete_option( 'segm_blad_bazy' );
segm_zapisz_blad_ai( 'proba segmaz', 0 );
$z = get_option( 'segm_blad_ai' );
segmaz( is_array( $z ) && 0 === (int) $z['kod'] && 'proba segmaz' === $z['komunikat'] && '' === segmaz_slad_funkcja(), 'BEZ WADY: znacznik zapisany, bez sladu bledu bazy' );

// --------------------------------- D. KR4-blizniak: puste pole limitu AI w ustawieniach
echo "\n=== D. KR4-blizniak: puste pole dziennego limitu wywolan AI nie zjezdza do 1 ===\n";
update_option( 'segm_ustawienia', array_merge( segm_ustawienia(), array( 'ai_limit_dzienny' => 77 ) ) );
wp_cache_flush();
$_POST                = array( 'ai_limit_dzienny' => '' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_ustawienia_zapisz' );
$adres                = segmaz_dokad( 'segm_post_ustawienia_zapisz' );
wp_cache_flush();
$limit = (int) segm_ustawienia()['ai_limit_dzienny'];
segmaz( 77 === $limit, 'puste pole: poprzednia wartosc (77) zostaje', (string) $limit );
segmaz( false === strpos( $adres, 'segm_ok=zapisano' ), 'puste pole: komunikat ≠ „zapisano"', $adres );
$_POST                = array( 'ai_limit_dzienny' => 'abc' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_ustawienia_zapisz' );
$adres                = segmaz_dokad( 'segm_post_ustawienia_zapisz' );
wp_cache_flush();
segmaz( 77 === (int) segm_ustawienia()['ai_limit_dzienny'] && false === strpos( $adres, 'segm_ok=zapisano' ), 'pole „abc": poprzednia wartosc zostaje, komunikat ≠ „zapisano"', $adres );
$_POST                = array( 'ai_limit_dzienny' => '250' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_ustawienia_zapisz' );
$adres                = segmaz_dokad( 'segm_post_ustawienia_zapisz' );
wp_cache_flush();
segmaz( 250 === (int) segm_ustawienia()['ai_limit_dzienny'] && false !== strpos( $adres, 'segm_ok=zapisano' ), 'BEZ WADY: liczba zapisana tym samym przebiegiem → „zapisano"', $adres );

// ------------------------------------------------ E. KR4: port i limit skrzynki
echo "\n=== E. KR4: puste port/limit skrzynki nie staja sie 1 po cichu ===\n";
$s = segm_skrzynka_ustawienia();
$s = array_merge( $s, array( 'host' => 'imap.segmaz.invalid', 'port' => 993, 'limit' => 200, 'login' => 'x@segmaz.invalid' ) );
update_option( 'segm_skrzynka', $s );
wp_cache_flush();
delete_transient( 'segm_komunikat_' . get_current_user_id() );
$_POST                = array( 'host' => 'imap.segmaz.invalid', 'port' => '', 'limit' => '', 'login' => 'x@segmaz.invalid', 'szyfrowanie' => 'ssl', 'folder_zrodlowy' => 'INBOX' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_skrzynka_zapisz' );
segmaz_dokad( 'segm_post_skrzynka_zapisz' );
wp_cache_flush();
$sk = segm_skrzynka_ustawienia();
$km = segmaz_komunikat_skrzynki();
printf( "  po pustych polach: port=%s limit=%s | komunikat [%s] %s\n", $sk['port'], $sk['limit'], $km['typ'], mb_substr( (string) $km['tekst'], 0, 160 ) );
segmaz( 993 === (int) $sk['port'], 'pusty port: poprzednia wartosc 993 zostaje', (string) $sk['port'] );
segmaz( 200 === (int) $sk['limit'], 'pusty limit: poprzednia wartosc 200 zostaje', (string) $sk['limit'] );
segmaz( 'ok' !== $km['typ'], 'komunikat NIE jest zielonym „ok"', $km['typ'] );
segmaz( false !== mb_stripos( (string) $km['tekst'], 'port' ) && false !== mb_stripos( (string) $km['tekst'], 'limit' ), 'komunikat wymienia oba pola', mb_substr( (string) $km['tekst'], 0, 120 ) );

$_POST                = array( 'host' => 'imap.segmaz.invalid', 'port' => '99999', 'limit' => '0', 'login' => 'x@segmaz.invalid', 'szyfrowanie' => 'ssl', 'folder_zrodlowy' => 'INBOX' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_skrzynka_zapisz' );
segmaz_dokad( 'segm_post_skrzynka_zapisz' );
wp_cache_flush();
$sk = segm_skrzynka_ustawienia();
$km = segmaz_komunikat_skrzynki();
segmaz( 993 === (int) $sk['port'] && 200 === (int) $sk['limit'] && 'ok' !== $km['typ'], 'poza zakresem (99999 / 0): poprzednie wartosci zostaja, komunikat ≠ ok', "port={$sk['port']} limit={$sk['limit']} [{$km['typ']}]" );

$_POST                = array( 'host' => 'imap.segmaz.invalid', 'port' => '143', 'limit' => '20', 'login' => 'x@segmaz.invalid', 'szyfrowanie' => 'starttls', 'folder_zrodlowy' => 'INBOX' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_skrzynka_zapisz' );
segmaz_dokad( 'segm_post_skrzynka_zapisz' );
wp_cache_flush();
$sk = segm_skrzynka_ustawienia();
$km = segmaz_komunikat_skrzynki();
segmaz( 143 === (int) $sk['port'] && 20 === (int) $sk['limit'] && 'ok' === $km['typ'], 'BEZ WADY: poprawne liczby zapisane, komunikat „ok"', "port={$sk['port']} limit={$sk['limit']} [{$km['typ']}]" );

// -------------------------- F. A2-F4: pozostale zapisy z aktywacji (recenzja #159)
echo "\n=== F. A2-F4: znacznik ekranu powitalnego i wersja schematu tez sa mierzone ===\n";
$stan_pow = get_option( 'segm_powitanie_gotowe', null );
$stan_sch = get_option( 'segm_wersja_schematu', null );
delete_option( 'segm_blad_bazy' );
delete_option( 'segm_powitanie_gotowe' );
$f  = segmaz_psuj( 'segm_powitanie_gotowe', 'UPDATE' );
$f2 = segmaz_psuj( 'segm_powitanie_gotowe', 'INSERT' );
segm_aktywacja();
segmaz_odpsuj( $f );
segmaz_odpsuj( $f2 );
wp_cache_flush();
segmaz( 'segm_aktywacja' === segmaz_slad_funkcja(), 'AWARIA: padniety znacznik powitania zostawia slad bledu bazy', segmaz_slad_funkcja() );
delete_option( 'segm_blad_bazy' );
delete_option( 'segm_wersja_schematu' );
$f  = segmaz_psuj( 'segm_wersja_schematu', 'UPDATE' );
$f2 = segmaz_psuj( 'segm_wersja_schematu', 'INSERT' );
segm_sprawdz_schemat();
segmaz_odpsuj( $f );
segmaz_odpsuj( $f2 );
wp_cache_flush();
segmaz( 'segm_sprawdz_schemat' === segmaz_slad_funkcja(), 'AWARIA: padniety zapis wersji schematu zostawia slad bledu bazy', segmaz_slad_funkcja() );
delete_option( 'segm_blad_bazy' );
segm_sprawdz_schemat();
wp_cache_flush();
segmaz( SEGM_WERSJA_SCHEMATU === get_option( 'segm_wersja_schematu' ) && '' === segmaz_slad_funkcja(), 'BEZ WADY: dzialajaca baza — wersja schematu zapisana, bez sladu bledu', (string) get_option( 'segm_wersja_schematu' ) );
// stan sprzed proby
if ( null === $stan_pow ) {
	delete_option( 'segm_powitanie_gotowe' );
} else {
	update_option( 'segm_powitanie_gotowe', $stan_pow );
}
if ( null !== $stan_sch ) {
	update_option( 'segm_wersja_schematu', $stan_sch );
}

printf( "\nBLEDOW: %d\n", $GLOBALS['segmaz_bledy'] );
exit( $GLOBALS['segmaz_bledy'] > 0 ? 1 : 0 );
