<?php
/**
 * Q-2 — kasownik RODO ChatBota liczy się z WYNIKIEM kasowania śladu sesji.
 *
 * ⛔ CO GROZIŁO: `chatl_rodo_kasownik()` sprawdzało wynik kasowania śladu współdzielonego
 * i zgłoszeń, ale wynik kasowania wiersza `chatl_sesje` szedł WYŁĄCZNIE do dziennika błędów.
 * Nie liczył się ani do liczby usuniętych, ani do komunikatu, którym kreator prywatności
 * WordPressa melduje wykonanie żądania. Komentarz w samym kodzie mówi wprost, że wiersz sesji
 * też jest śladem osoby (kiedy pisała, czy przejął ją człowiek) — a mimo to jego awaria
 * kończyła się potwierdzeniem usunięcia. Ta sama klasa, którą ten plik zamknął już raz
 * przy komunikacie bezwarunkowym: meldujemy skutek, którego nie zmierzyliśmy.
 *
 * Strażnik mierzy OBIE strony warunku:
 *   A. powodzenie — wiersz sesji NAPRAWDĘ znika i jest policzony w komunikacie,
 *   B. PODŁOŻONA awaria tej jednej tabeli — kasownik zatrzymuje się i OSTRZEGA,
 *      a zgłoszenia (kasowane dopiero niżej) zostają NIETKNIĘTE.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-kasownik-slad-sesji-test.php strona_wp_wordpress:/tmp/ks.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/ks.php
 */

$GLOBALS['chatl_test_bledy'] = 0;
function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}
function przyrzad( $p ) {
	echo "  PRZYRZAD: {$p}\n";
	exit( 2 );
}
if ( ! function_exists( 'chatl_rodo_kasownik' ) ) {
	przyrzad( 'brak chatl_rodo_kasownik — wtyczka chatbot-live nieaktywna?' );
}

global $wpdb;
$ts = $wpdb->prefix . 'chatl_sesje';
$tr = $wpdb->prefix . 'chatl_rozmowy';
$tz = $wpdb->prefix . 'chatl_zgloszenia';

$znak  = 'ks' . wp_generate_password( 10, false, false );
$teraz = current_time( 'mysql' );

/**
 * Zasiew: sesja + dwie wiadomości + zgłoszenie z adresem. Zwraca parę [sesja, email].
 *
 * ⛔ `wp eval-file` wciąga plik W FUNKCJI, więc zmienne z góry pliku NIE są globalne —
 * `global $ts` dawało tu pustą nazwę tabeli i `insert()` szedł w nicość, a warunek wstępny
 * padał ZANIM cokolwiek zmierzyliśmy. Nazwy składamy na nowo, resztę bierzemy parametrem.
 */
function zasiej( $sufiks, $znak, $teraz ) {
	global $wpdb;
	$ts    = $wpdb->prefix . 'chatl_sesje';
	$tr    = $wpdb->prefix . 'chatl_rozmowy';
	$tz    = $wpdb->prefix . 'chatl_zgloszenia';
	$sesja = $znak . $sufiks;
	$email = $znak . $sufiks . '@example.test';
	$wpdb->insert(
		$ts,
		array(
			'sesja'      => $sesja,
			'stan'       => 'bot',
			'utworzono'  => $teraz,
			'aktywnosc'  => $teraz,
			'klucz_hash' => str_repeat( 'a', 64 ),
		)
	);
	foreach ( array( 'gosc', 'bot' ) as $rola ) {
		$wpdb->insert(
			$tr,
			array(
				'sesja'     => $sesja,
				'rola'      => $rola,
				'silnik'    => 'regul',
				'tresc'     => 'proba ' . $znak,
				'utworzono' => $teraz,
			)
		);
	}
	$wpdb->insert(
		$tz,
		array(
			'email'      => $email,
			'wiadomosc'  => 'proba ' . $znak,
			'sesja'      => $sesja,
			'status'     => 'nowe',
			'utworzono'  => $teraz,
			'podejrzane' => 0,
		)
	);
	return array( $sesja, $email );
}
function ile( $tabela, $kolumna, $wartosc ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabela} WHERE {$kolumna} = %s", $wartosc ) );
}

$sprzataj = static function () use ( $wpdb, $ts, $tr, $tz, $znak ) {
	foreach ( array( $ts, $tr ) as $t ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE sesja LIKE %s", $wpdb->esc_like( $znak ) . '%' ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email LIKE %s", $wpdb->esc_like( $znak ) . '%' ) );
	delete_option( CHATL_RODO_OPCJA_WSPOLNE );
};
register_shutdown_function( $sprzataj );

// =====================================================================
// A. POWODZENIE — slad sesji znika i JEST policzony
// =====================================================================
echo "== A. Powodzenie: wiersz sesji znika i liczy sie do komunikatu ==\n";
list( $sesja_a, $email_a ) = zasiej( 'a', $znak, $teraz );
sprawdz( 'A0 warunek wstepny: sesja, wiadomosci i zgloszenie zasiane', 1 === ile( $ts, 'sesja', $sesja_a ) && 2 === ile( $tr, 'sesja', $sesja_a ) && 1 === ile( $tz, 'email', $email_a ) );

$wynik_a = chatl_rodo_kasownik( $email_a );
$tekst_a = implode( ' ', (array) $wynik_a['messages'] );
sprawdz( 'A1 wiersz sesji NAPRAWDE zniknal z bazy', 0 === ile( $ts, 'sesja', $sesja_a ) );
sprawdz( 'A2 wiadomosci i zgloszenie tez zniknely', 0 === ile( $tr, 'sesja', $sesja_a ) && 0 === ile( $tz, 'email', $email_a ) );
sprawdz( 'A3 kreator dostaje items_removed = true', true === (bool) $wynik_a['items_removed'] );
sprawdz( 'A4 komunikat WYMIENIA slad sesji (nie tylko zgloszenia i wiadomosci)', false !== stripos( $tekst_a, 'ślad' ) && false !== stripos( $tekst_a, 'sesji' ), $tekst_a );

// =====================================================================
// B. PODLOZONA AWARIA kasowania sesji — zatrzymanie i ostrzezenie
// =====================================================================
echo "\n== B. Baza odrzuca kasowanie wiersza sesji ==\n";
list( $sesja_b, $email_b ) = zasiej( 'b', $znak, $teraz );
sprawdz( 'B0 warunek wstepny: zasiew na miejscu', 1 === ile( $ts, 'sesja', $sesja_b ) && 1 === ile( $tz, 'email', $email_b ) );

/* Psujemy DOKLADNIE jedno zapytanie: DELETE z tabeli sesji. Filtr `query` przepuszcza
   wszystko inne bez zmian, wiec awaria jest punktowa, a nie „baza padla". */
$trafien   = 0;
$psuj      = static function ( $zapytanie ) use ( $ts, &$trafien ) {
	if ( 0 === stripos( ltrim( $zapytanie ), 'DELETE' ) && false !== strpos( $zapytanie, $ts ) ) {
		++$trafien;
		return 'DELETE FROM tabela_ktorej_nie_ma_' . md5( $zapytanie ) . ' WHERE 1=1';
	}
	return $zapytanie;
};
add_filter( 'query', $psuj );
$wpdb->suppress_errors( true );
$wynik_b = chatl_rodo_kasownik( $email_b );
$wpdb->suppress_errors( false );
remove_filter( 'query', $psuj );

$tekst_b = implode( ' ', (array) $wynik_b['messages'] );
/* ⛔ BEZ TEGO WARUNKU cala sekcja B bylaby wydmuszka: „kasownik ostrzegl" znaczyloby tylko
   tyle, ze podlozenie w ogole nie trafilo w zapytanie. */
sprawdz( 'B1 podlozenie TRAFILO w zapytanie kasujace sesje', $trafien > 0, 'trafien: ' . $trafien );
sprawdz( 'B2 wiersz sesji faktycznie ZOSTAL (awaria byla prawdziwa)', 1 === ile( $ts, 'sesja', $sesja_b ) );
sprawdz( 'B3 kreator dostaje items_retained = true (cos zostalo)', true === (bool) $wynik_b['items_retained'] );
sprawdz( 'B4 komunikat OSTRZEGA i zabrania potwierdzania usuniecia', false !== stripos( $tekst_b, 'ODRZUCIŁA' ) && false !== stripos( $tekst_b, 'NIE potwierdzaj' ), $tekst_b );
/* Zatrzymanie ma byc PRAWDZIWE: zgloszenia kasowane sa dopiero po sesji, wiec przy przerwaniu
   musza zostac. Gdyby kod poleciał dalej, komunikat ostrzegalby o czyms, co juz sie stalo. */
sprawdz( 'B5 ZATRZYMANIE: zgloszenie z tym adresem NIETKNIETE', 1 === ile( $tz, 'email', $email_b ) );

$sprzataj();
echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'BLEDY: ' . $GLOBALS['chatl_test_bledy'] . "\n" : "WSZYSTKO OK\n" );
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
