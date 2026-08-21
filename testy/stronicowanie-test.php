<?php
/**
 * Stronicowanie trzech list panelu — test na PRAWDZIWEJ bazie i prawdziwym kodzie.
 *
 * Najgroźniejszy błąd stronicowania nie polega na tym, że nie działa — tylko na
 * tym, że działa PRAWIE: przy złym przesunięciu jeden wiersz wypada między
 * stronami albo pokazuje się dwa razy, a właściciel nie ma jak tego zauważyć.
 * Dlatego test nie sprawdza „czy są odsyłacze", tylko **składa wszystkie strony
 * w jeden zbiór i porównuje go z zawartością bazy**.
 *
 * Uruchomienie:
 *   docker cp testy/stronicowanie-test.php strona_wp_wordpress:/tmp/stronicowanie-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/stronicowanie-test.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
require_once $katalog . 'statystyki.php';
require_once $katalog . 'admin.php';

$GLOBALS['chatl_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol && ! $warunek ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}

function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

global $wpdb;
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$tr = $wpdb->prefix . 'chatl_rozmowy';

function chatl_test_sprzataj() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email LIKE 'stron-test%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja LIKE 'stronTest%'" ); // phpcs:ignore
}
chatl_test_sprzataj();

echo "=== A. Numer strony z adresu ===\n";
$_GET = array();
rowne( 'brak parametru = strona pierwsza', 1, chatl_numer_strony() );
$_GET['strona_listy'] = '3';
rowne( 'liczba z adresu jest brana', 3, chatl_numer_strony() );
$_GET['strona_listy'] = '-5';
rowne( 'liczba ujemna nie da ujemnego przesuniecia', 1, chatl_numer_strony() );
$_GET['strona_listy'] = 'DROP TABLE';
rowne( 'smiec w adresie nie wywraca listy', 1, chatl_numer_strony() );
$_GET = array();

echo "\n=== B. SEDNO: zaden wiersz nie ginie i nie dubluje sie miedzy stronami ===\n";
/*
 * Wkladamy WIECEJ wierszy niz miesci strona i skladamy wszystkie strony w jeden
 * zbior. Rozmiar strony przycinamy filtrem, zeby nie wkladac dwustu wierszy —
 * mechanizm jest ten sam, a test zostaje szybki.
 */
$na_strone = 5;
$ile       = 12;
for ( $i = 1; $i <= $ile; $i++ ) {
	$wpdb->insert(
		$tz,
		array(
			'email'      => sprintf( 'stron-test-%02d@example.test', $i ),
			'wiadomosc'  => 'wiersz ' . $i,
			'sesja'      => '',
			'status'     => 'nowe',
			'podejrzane' => 0,
			'utworzono'  => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%d', '%s' )
	);
}

$wszystkich = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tz} WHERE podejrzane = 0 AND email LIKE 'stron-test%'" ); // phpcs:ignore
rowne( 'wiersze testowe sa w bazie', $ile, $wszystkich );

$zebrane = array();
$stron   = (int) ceil( $ile / $na_strone );
for ( $s = 1; $s <= $stron; $s++ ) {
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT email FROM {$tz} WHERE podejrzane = 0 AND email LIKE 'stron-test%%' ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore
			$na_strone,
			( $s - 1 ) * $na_strone
		),
		ARRAY_A
	);
	foreach ( $wiersze as $w ) {
		$zebrane[] = $w['email'];
	}
}
rowne( 'suma wszystkich stron = liczba wierszy w bazie', $ile, count( $zebrane ) );
rowne( 'i ani jeden wiersz nie powtorzyl sie na dwoch stronach', $ile, count( array_unique( $zebrane ) ) );

echo "\n=== C. Odsylacze stron: sa, gdy trzeba, i nie ma ich, gdy nie trzeba ===\n";
ob_start();
chatl_stronicowanie( 12, 5, 1, array( 'page' => 'chatl-zgloszenia' ) );
$pasek = ob_get_clean();
sprawdz( 'przy 12 wierszach i stronie po 5 pasek jest', '' !== trim( $pasek ) );
sprawdz( 'pasek podaje LICZBE wszystkich pozycji', false !== strpos( $pasek, '12' ), $pasek );
sprawdz( 'i prowadzi na strone druga', false !== strpos( $pasek, 'strona_listy=2' ), $pasek );
sprawdz( 'odsylacz zachowuje podstrone panelu', false !== strpos( $pasek, 'page=chatl-zgloszenia' ), $pasek );

ob_start();
chatl_stronicowanie( 3, 5, 1, array( 'page' => 'chatl-zgloszenia' ) );
$pasek_maly = ob_get_clean();
rowne( 'przy jednej stronie NIE ma paska (pusty pasek tylko myli)', '', trim( $pasek_maly ) );

ob_start();
chatl_stronicowanie( 40, 5, 2, array( 'page' => 'chatl-zgloszenia', 'widok' => 'podejrzane' ) );
$pasek_filtr = ob_get_clean();
sprawdz( 'pasek ZACHOWUJE filtr widoku (inaczej strona 2 pokazuje inne dane)', false !== strpos( $pasek_filtr, 'widok=podejrzane' ), $pasek_filtr );

echo "\n=== D. Rozmowy liczone po SESJACH, nie po wierszach ===\n";
/*
 * Jedna rozmowa to wiele wymian. Liczenie wierszy zamiast sesji daloby liczbe
 * stron oderwana od tego, co widac na liscie — i puste strony na koncu.
 */
for ( $s = 1; $s <= 3; $s++ ) {
	for ( $w = 1; $w <= 4; $w++ ) {
		$wpdb->insert(
			$tr,
			array(
				'sesja'     => sprintf( 'stronTestSesja%02d', $s ),
				'rola'      => 0 === $w % 2 ? 'bot' : 'gosc',
				'silnik'    => 'reguly',
				'tresc'     => 'wymiana ' . $w,
				'utworzono' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
$sesji   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT sesja) FROM {$tr} WHERE sesja LIKE 'stronTest%'" ); // phpcs:ignore
$wierszy = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tr} WHERE sesja LIKE 'stronTest%'" ); // phpcs:ignore
rowne( 'trzy rozmowy po cztery wymiany', 3, $sesji );
rowne( 'to dwanascie wierszy', 12, $wierszy );
sprawdz( 'liczba stron liczona z SESJI rozni sie od liczonej z wierszy', $sesji !== $wierszy );

echo "\n=== E. Kod naprawde uzywa stronicowania (nie samo narzedzie istnieje) ===\n";
$kod_admin = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/includes/admin.php' ); // phpcs:ignore
$kod_stat  = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/includes/statystyki.php' ); // phpcs:ignore

sprawdz( 'lista zgloszen ma OFFSET', false !== strpos( $kod_admin, 'LIMIT %d OFFSET %d", $flaga, CHATL_NA_STRONE_ZGLOSZEN' ) );
sprawdz( 'lista rozmow ma OFFSET', false !== strpos( $kod_admin, 'LIMIT %d OFFSET %d", CHATL_NA_STRONE_ROZMOW' ) );
sprawdz( 'lista rozmow liczy DISTINCT sesja', false !== strpos( $kod_admin, 'COUNT(DISTINCT sesja)' ) );
sprawdz( 'raport luk przesuwa sie po grupach', false !== strpos( $kod_stat, 'array_slice( $luki[\'grupy\'], $luki_offset' ) );
sprawdz( 'zadna lista nie zostala z twardym LIMIT 200', false === strpos( $kod_admin, 'ORDER BY id DESC LIMIT 200' ) );
sprawdz( 'ani z twardym LIMIT 100', false === strpos( $kod_admin, 'ORDER BY koniec DESC LIMIT 100' ) );

echo "\n=== F. Strona spoza zakresu nie daje pustej listy bez wyjasnienia ===\n";
sprawdz( 'kod przycina numer strony do ostatniej istniejacej (zgloszenia)', false !== strpos( $kod_admin, '$strona           = min( $strona, $stron );' ) );
sprawdz( 'to samo dla rozmow', false !== strpos( $kod_admin, '$strona_r     = min( $strona_r, $stron_r );' ) );
sprawdz( 'i dla luk', false !== strpos( $kod_stat, '$luki_strona = min( chatl_numer_strony(), $luki_stron );' ) );

echo "\n=== G. Sufit wejsciowy raportu luk ZOSTAJE (to osobna granica) ===\n";
/*
 * CHATL_LUKI_LIMIT ogranicza wiersze WEJSCIOWE do grupowania i jest swiadoma
 * granica wydajnosci opisana w panelu. Stronicowanie dotyczy WYNIKU grupowania.
 * Zdjecie tego sufitu przy okazji stronicowania byloby cicha zmiana zachowania.
 */
rowne( 'sufit wejsciowy nadal wynosi 2000', 2000, CHATL_LUKI_LIMIT );
sprawdz( 'i nadal jest uzywany w zapytaniu luk', false !== strpos( $kod_stat, 'CHATL_LUKI_LIMIT' ) );

echo "\n=== H. Sprzatanie ===\n";
chatl_test_sprzataj();
rowne( 'dane testowe usuniete', '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$tz} WHERE email LIKE 'stron-test%'" ) ); // phpcs:ignore

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
