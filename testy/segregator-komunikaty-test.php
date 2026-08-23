<?php
/**
 * STRAŻNIK komunikatów Segregatora (WK-3, WK-4, WK-13, A4-F3).
 *
 * Dziennik kupującego (audyt/boty/WCIELENIE-KUPUJACY.md):
 *   19:54 — świeżo kupiona wtyczka wita czerwonym „KLUCZ API ZOSTAŁ ODRZUCONY"
 *           o kluczu, którego właściciel nigdy nie wpisywał (WK-3);
 *   19:55 — w tabelce zgodności „✔ jest" stoi obok opisu „Nieobecne" (WK-4);
 *   19:57 — „Connection refused (kod 111)" — żargon dla laika (WK-13).
 * Audyt A4 (front/dostępność): `<select name="szyfrowanie">` bez etykiety —
 * czytnik ekranu nie ma jak nazwać tego pola (A4-F3, WCAG poziom A).
 *
 * Co mierzy (każdy punkt z parą AWARIA / BEZ WADY):
 *   A. (WK-3) ślad błędu AI + BRAK klucza → pasek NIE mówi „odrzucony",
 *      mówi skąd wziąć klucz, jest ostrzeżeniem (żółty), nie błędem;
 *      ten sam ślad + klucz W BAZIE → pasek jak dawniej (czerwony, „odrzucony");
 *   B. (WK-4) wiersz „Rozszerzenie PHP imap" — napis w kolumnie stanu zgadza
 *      się z pierwszym zdaniem opisu (żadnego „✔ jest" obok „Nieobecne");
 *      pozostałe wiersze mają domyślne znaczniki;
 *   C. (WK-13) komunikat połączenia zaczyna się zdaniem po polsku — także przy
 *      LITERÓWCE w adresie serwera, gdzie PHP daje `errno = 0` — kod
 *      systemowy zostaje w części „szczegóły techniczne"; para port+szyfrowanie
 *      dostaje własne zdanie; poprawne połączenie nadal działa;
 *   D. (A4-F3) pole wyboru szyfrowania ma `id` i etykietę `<label for>`.
 *
 * Test niczego nie zapisuje na stałe: klucz API, ślad błędu AI i ustawienia
 * skrzynki wracają do stanu sprzed próby (także po padzie w środku).
 *
 * Uruchomienie:
 *   docker cp testy/segregator-komunikaty-test.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
 * EXIT 0 = wszystko OK, 1 = któryś punkt padł.
 */
global $wpdb;
if ( ! function_exists( 'segm_skrzynka_diagnostyka' ) ) {
	echo "POMINIETO: wtyczka segregator-maila nie jest zaladowana.\n";
	exit( 0 );
}
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/admin.php';
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/admin-skrzynka.php';

$GLOBALS['segmk_bledy'] = 0;
function segmk( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " (" . mb_substr( (string) $szczegol, 0, 180 ) . ")" : '' );
	if ( ! $ok ) {
		++$GLOBALS['segmk_bledy'];
	}
	return $ok;
}
/** Czysty tekst paska, tak jak przeczyta go człowiek. */
function segmk_pasek() {
	ob_start();
	segm_pasek_bledu_ai();
	$html = (string) ob_get_clean();
	return array(
		'html' => $html,
		'tekst' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ),
	);
}

$GLOBALS['segmk_stan'] = array(
	'segm_klucz_api' => get_option( 'segm_klucz_api', null ),
	'segm_blad_ai'   => get_option( 'segm_blad_ai', null ),
);
register_shutdown_function(
	function () {
		delete_option( 'segm_klucz_api' );
		if ( null !== $GLOBALS['segmk_stan']['segm_klucz_api'] ) {
			add_option( 'segm_klucz_api', $GLOBALS['segmk_stan']['segm_klucz_api'], '', false );
		}
		if ( null === $GLOBALS['segmk_stan']['segm_blad_ai'] ) {
			delete_option( 'segm_blad_ai' );
		} else {
			update_option( 'segm_blad_ai', $GLOBALS['segmk_stan']['segm_blad_ai'], false );
		}
	}
);
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $admin ? (int) $admin[0] : 1 );
$_GET['page'] = 'segm-skrzynka';

echo "=== A. WK-3: pasek AI u wlasciciela, ktory NIGDY nie wpisywal klucza ===\n";
if ( segm_klucz_ze_stalej() ) {
	echo "  (pominieto: klucz ze stalej SEGM_KLUCZ_API — panel go nie zapisuje)\n";
} else {
	delete_option( 'segm_klucz_api' );
	update_option(
		'segm_blad_ai',
		array(
			'kiedy'     => current_time( 'mysql' ),
			'kod'       => 401,
			'komunikat' => 'HTTP 401: API key is invalid.',
			'model'     => 'claude-haiku-4-5',
		),
		false
	);
	wp_cache_flush();
	$p = segmk_pasek();
	printf( "  pasek: %s\n", mb_substr( $p['tekst'], 0, 200 ) );
	segmk( '' !== $p['tekst'], 'pasek w ogole sie pokazuje (wlasciciel wie, ze AI nie dziala)' );
	// AND, nie OR: zniknięcie JEDNEGO z dwóch zdań nie wystarczy — regresja,
	// która przywróci samo „KLUCZ API ZOSTAŁ ODRZUCONY", ma ten test ZAPALIĆ.
	segmk( false === mb_stripos( $p['tekst'], 'ZOSTAŁ ODRZUCONY' ) && false === mb_stripos( $p['tekst'], 'czy jest wpisany w całości' ), 'BEZ klucza pasek NIE zarzuca, ze klucz wlasciciela zostal odrzucony', $p['tekst'] );
	segmk( false !== mb_stripos( $p['tekst'], 'nie ma klucza' ), 'pasek mowi WPROST, ze klucza nie ma' );
	segmk( false !== mb_stripos( $p['tekst'], 'console.anthropic.com' ) || false !== mb_stripos( $p['tekst'], SEGM_STALA_KLUCZA ), 'pasek mowi, SKAD wziac klucz i gdzie go wpisac' );
	segmk( false !== mb_stripos( $p['tekst'], 'regułowy jest pełnoprawny' ), 'pasek uspokaja: bez klucza wtyczka dziala regulami' );
	segmk( false !== strpos( $p['html'], 'notice-warning' ) && false === strpos( $p['html'], 'notice-error' ), 'brak klucza to OSTRZEZENIE (zolte), nie blad (czerwone)' );

	echo "\n  --- BEZ WADY: klucz JEST w bazie, dostawca go odrzucil ---\n";
	delete_option( 'segm_klucz_api' );
	add_option( 'segm_klucz_api', 'segmk-testowy-klucz', '', false );
	wp_cache_flush();
	$p = segmk_pasek();
	printf( "  pasek: %s\n", mb_substr( $p['tekst'], 0, 160 ) );
	segmk( false !== mb_stripos( $p['tekst'], 'ODRZUCONY' ), 'przy WPISANYM kluczu pasek nadal nazywa przyczyne (401 = odrzucony)' );
	segmk( false !== strpos( $p['html'], 'notice-error' ), 'przy wpisanym kluczu pasek jest czerwony' );

	echo "\n  --- BEZ WADY: brak sladu bledu = brak paska ---\n";
	delete_option( 'segm_blad_ai' );
	wp_cache_flush();
	$pusty = segmk_pasek();
	segmk( '' === $pusty['tekst'], 'bez sladu bledu AI zaden pasek sie nie pokazuje' );
}

echo "\n=== B. WK-4: tabelka zgodnosci nie przeczy sama sobie ===\n";
$domysl = array(
	'ok'    => '✔ jest',
	'uwaga' => '! uwaga',
	'blad'  => '✘ brak',
);
foreach ( segm_skrzynka_diagnostyka() as $w ) {
	$napis = isset( $w['znacznik'] ) && '' !== $w['znacznik'] ? $w['znacznik'] : $domysl[ $w['stan'] ];
	$opis  = (string) $w['opis'];
	if ( false !== mb_strpos( $w['nazwa'], 'imap' ) ) {
		printf( '  wiersz imap: kolumna=[%s] | opis zaczyna sie: [%s]' . "\n", $napis, mb_substr( $opis, 0, 40 ) );
		$mowi_ze_jest = false !== mb_strpos( $napis, 'jest' ) && false === mb_strpos( $napis, 'nieużywane' );
		$opis_nieobecne = 0 === mb_strpos( $opis, 'Nieobecne' );
		segmk( ! ( $mowi_ze_jest && $opis_nieobecne ), 'kolumna nie mowi „jest", gdy opis mowi „Nieobecne”', $napis . ' / ' . mb_substr( $opis, 0, 40 ) );
		segmk( extension_loaded( 'imap' ) ? false !== mb_strpos( $napis, 'nieużywane' ) : false !== mb_strpos( $napis, 'nie dotyczy' ), 'napis w kolumnie zgadza sie ze stanem rzeczy', $napis );
	} else {
		segmk( $napis === $domysl[ $w['stan'] ], 'pozostale wiersze maja domyslny znacznik: ' . mb_substr( $w['nazwa'], 0, 40 ), $napis );
	}
}

echo "\n=== C. WK-13: komunikat polaczenia po ludzku, kod w szczegolach ===\n";
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/class-segm-imap.php';
$k = new Segm_Imap( 5 );
$k->polacz( 'segm_imap', 3999, 'brak' );
$blad = $k->blad();
printf( "  %s\n", mb_substr( $blad, 0, 220 ) );
segmk( false === mb_stripos( mb_substr( $blad, 0, 120 ), 'Connection refused' ), 'pierwsze zdanie NIE jest komunikatem systemowym po angielsku', mb_substr( $blad, 0, 90 ) );
segmk( false !== mb_stripos( $blad, 'nikt tam nie odbiera' ), 'komunikat mowi po polsku, co sie stalo' );
segmk( false !== mb_stripos( $blad, 'Sprawdź numer portu' ), 'komunikat mowi, co z tym zrobic' );
segmk( false !== mb_stripos( $blad, 'Szczegóły techniczne' ) && false !== mb_strpos( $blad, '111' ), 'kod systemowy ZOSTAJE, w czesci technicznej', 'jest 111' );

$k2 = new Segm_Imap( 5 );
$k2->polacz( 'segm_imap', 3143, 'ssl' );
$blad2 = $k2->blad();
printf( "  %s\n", mb_substr( $blad2, 0, 200 ) );
// Stary komunikat tez zawieral slowo „szyfrowanie" („Sprawdź adres serwera, port
// i szyfrowanie") — asercja musi trafiac w zdanie charakterystyczne dla NOWEGO.
segmk( false !== mb_stripos( $blad2, 'nie udało się zestawić szyfrowania' ), 'niedobrana para port+szyfrowanie dostaje wlasne zdanie', mb_substr( $blad2, 0, 140 ) );
segmk( false === mb_stripos( $blad2, 'Serwer odrzucił połączenie na tym porcie' ), 'blad TLS nie jest mylony z odmowa polaczenia', mb_substr( $blad2, 0, 100 ) );

// (recenzja #163) Literowka w adresie to NAJCZESTSZY blad wlasciciela, a PHP
// ustawia przy niej errno = 0 — rozpoznanie musi isc po TRESCI komunikatu,
// nie po kodzie; wczesniej ta galaz byla martwa.
$k_dns = new Segm_Imap( 5 );
$k_dns->polacz( 'imap.segmk-nie-istnieje.example', 993, 'ssl' );
$blad3 = $k_dns->blad();
printf( "  %s\n", mb_substr( $blad3, 0, 230 ) );
segmk( false !== mb_stripos( $blad3, 'Nie udało się odnaleźć serwera o tym adresie' ), 'literowka w adresie: komunikat NAZYWA przyczyne po polsku', mb_substr( $blad3, 0, 120 ) );
segmk( false !== mb_stripos( $blad3, 'literówki' ), 'komunikat mowi, co sprawdzic (literowka w polu „Serwer IMAP")' );
segmk( false === mb_stripos( mb_substr( $blad3, 0, 150 ), 'getaddrinfo' ), 'pierwsze zdanie NIE jest angielskim komunikatem sterownika', mb_substr( $blad3, 0, 120 ) );
segmk( false !== mb_stripos( $blad3, 'Szczegóły techniczne' ) && false !== mb_stripos( $blad3, 'getaddrinfo' ), 'komunikat sterownika ZOSTAJE w czesci technicznej' );
segmk( false === mb_stripos( $blad3, 'zestawić szyfrowania' ), 'blad nazwy NIE jest przypisywany szyfrowaniu (mimo „ssl://" w tresci)', mb_substr( $blad3, 0, 120 ) );

$k3 = new Segm_Imap( 10 );
$ok3 = $k3->polacz( 'segm_imap', 3143, 'brak' ) && $k3->zaloguj( 'szef', 'tajne1234' );
segmk( $ok3, 'BEZ WADY: poprawne dane nadal sie laczą i logują' );
if ( $ok3 ) {
	$k3->rozlacz();
}

echo "\n=== D. A4-F3: pole wyboru szyfrowania ma etykiete ===\n";
$plik = (string) file_get_contents( WP_PLUGIN_DIR . '/segregator-maila/includes/admin-skrzynka.php' );
preg_match( '/<select[^>]*name="szyfrowanie"[^>]*>/', $plik, $m );
$znacznik = isset( $m[0] ) ? $m[0] : '';
printf( "  %s\n", $znacznik );
segmk( false !== strpos( $znacznik, 'id="segm-szyfrowanie"' ), 'pole ma wlasne id' );
segmk( false !== strpos( $plik, 'for="segm-szyfrowanie"' ), 'jest <label for> wskazujacy to pole (WCAG A)' );
segmk( false !== strpos( $plik, 'for="segm-port"' ), 'BEZ WADY: etykieta pola portu nadal jest' );

printf( "\nBLEDOW: %d\n", $GLOBALS['segmk_bledy'] );
exit( $GLOBALS['segmk_bledy'] > 0 ? 1 : 0 );
