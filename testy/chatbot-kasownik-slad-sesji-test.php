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
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email LIKE %s", '%' . $wpdb->esc_like( $znak ) . '%' ) );
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
sprawdz( 'B4 komunikat OSTRZEGA i zabrania potwierdzania usuniecia', false !== stripos( $tekst_b, 'ODRZUCIŁA' ) && false !== stripos( $tekst_b, 'NIE potwierdzaj' ) && false !== stripos( $tekst_b, 'Ślad sesji ZOSTAŁ' ), $tekst_b );
/* Zatrzymanie ma byc PRAWDZIWE: zgloszenia kasowane sa dopiero po sesji, wiec przy przerwaniu
   musza zostac. Gdyby kod poleciał dalej, komunikat ostrzegalby o czyms, co juz sie stalo. */
sprawdz( 'B5 ZATRZYMANIE: zgloszenie z tym adresem NIETKNIETE', 1 === ile( $tz, 'email', $email_b ) );

// =====================================================================
// C i D. BLIZNIAKI TEJ SAMEJ WADY — pozostale dwa kasowania w tej samej funkcji
//    (recenzja #259: naprawa jednego egzemplarza zostawiala dwa zepsute)
// =====================================================================
/**
 * Podklada awarie DOKLADNIE jednego DELETE i oddaje [wynik kasownika, ile trafien].
 *
 * @param string $tabela Pelna nazwa tabeli, ktorej DELETE ma paść.
 * @param string $email  Adres wnioskodawcy.
 */
function z_awaria_na( $tabela, $email ) {
	global $wpdb;
	$trafien = 0;
	$psuj    = static function ( $zapytanie ) use ( $tabela, &$trafien ) {
		if ( 0 === stripos( ltrim( $zapytanie ), 'DELETE' ) && false !== strpos( $zapytanie, $tabela ) ) {
			++$trafien;
			return 'DELETE FROM tabela_ktorej_nie_ma_' . md5( $zapytanie ) . ' WHERE 1=1';
		}
		return $zapytanie;
	};
	add_filter( 'query', $psuj );
	$wpdb->suppress_errors( true );
	$wynik = chatl_rodo_kasownik( $email );
	$wpdb->suppress_errors( false );
	remove_filter( 'query', $psuj );
	return array( $wynik, $trafien );
}

echo "\n== C. Baza odrzuca kasowanie WIADOMOSCI rozmow ==\n";
list( $sesja_c, $email_c ) = zasiej( 'c', $znak, $teraz );
list( $wynik_c, $traf_c )  = z_awaria_na( $tr, $email_c );
$tekst_c = implode( ' ', (array) $wynik_c['messages'] );
sprawdz( 'C1 podlozenie TRAFILO w zapytanie kasujace rozmowy', $traf_c > 0, 'trafien: ' . $traf_c );
sprawdz( 'C2 wiadomosci faktycznie ZOSTALY (awaria byla prawdziwa)', 2 === ile( $tr, 'sesja', $sesja_c ) );
sprawdz( 'C3 kreator dostaje items_retained = true', true === (bool) $wynik_c['items_retained'] );
sprawdz( 'C4 komunikat OSTRZEGA i zabrania potwierdzania usuniecia', false !== stripos( $tekst_c, 'ODRZUCIŁA' ) && false !== stripos( $tekst_c, 'NIE potwierdzaj' ), $tekst_c );
sprawdz( 'C5 ZATRZYMANIE: slad sesji i zgloszenie NIETKNIETE', 1 === ile( $ts, 'sesja', $sesja_c ) && 1 === ile( $tz, 'email', $email_c ) );
sprawdz( 'C6 items_removed = false — nic nie znikneło, wiec nie wolno tego meldowac', false === (bool) $wynik_c['items_removed'] );

echo "\n== D. Baza odrzuca kasowanie ZGLOSZEN (to one nioda sam adres e-mail) ==\n";
list( $sesja_d, $email_d ) = zasiej( 'd', $znak, $teraz );
list( $wynik_d, $traf_d )  = z_awaria_na( $tz, $email_d );
$tekst_d = implode( ' ', (array) $wynik_d['messages'] );
sprawdz( 'D1 podlozenie TRAFILO w zapytanie kasujace zgloszenia', $traf_d > 0, 'trafien: ' . $traf_d );
sprawdz( 'D2 zgloszenie z adresem faktycznie ZOSTALO', 1 === ile( $tz, 'email', $email_d ) );
sprawdz( 'D3 kreator dostaje items_retained = true', true === (bool) $wynik_d['items_retained'] );
sprawdz( 'D4 komunikat OSTRZEGA i nazywa, ze zostaly ZGLOSZENIA', false !== stripos( $tekst_d, 'NIE potwierdzaj' ) && false !== stripos( $tekst_d, 'Zgłoszenia z tym adresem ZOSTAŁY' ), $tekst_d );
/* Sciezka awaryjna NIE MOZE gubic zdania o retencji (drobna z recenzji #259). */
sprawdz( 'D5 komunikat awaryjny niesie takze zdanie o retencji rozmow bez adresu', false !== stripos( $tekst_d, 'retencja po' ) );

echo "\n== E. Sciezka awaryjna nie gubi notatki o rozmowach WSPOLDZIELONYCH ==\n";
/* Sesja z DWOMA roznymi adresami = wspoldzielona: rozmowy i sesja zostaja nietkniete,
   a kasownik idzie prosto do zgloszen — tam podkladamy awarie. */
list( $sesja_e, $email_e ) = zasiej( 'e', $znak, $teraz );
$wpdb->insert(
	$tz,
	array(
		'email'      => 'ktos-inny-' . $znak . '@example.test',
		'wiadomosc'  => 'druga osoba w tej samej sesji',
		'sesja'      => $sesja_e,
		'status'     => 'nowe',
		'utworzono'  => $teraz,
		'podejrzane' => 0,
	)
);
list( $wynik_e, $traf_e ) = z_awaria_na( $tz, $email_e );
$tekst_e = implode( ' ', (array) $wynik_e['messages'] );
sprawdz( 'E1 podlozenie TRAFILO', $traf_e > 0, 'trafien: ' . $traf_e );
sprawdz( 'E2 komunikat awaryjny niesie notatke o rozmowach wspoldzielonych', false !== stripos( $tekst_e, 'Zachowano' ) && false !== stripos( $tekst_e, 'inna osoba' ), $tekst_e );
sprawdz( 'E3 i zdanie o retencji', false !== stripos( $tekst_e, 'retencja po' ) );

$sprzataj();
echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'BLEDY: ' . $GLOBALS['chatl_test_bledy'] . "\n" : "WSZYSTKO OK\n" );
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
