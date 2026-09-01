<?php
/**
 * I-16 — sklep wpięty w mechanizm prywatności WordPressa. Strażnik, EXIT=1 przy porażce.
 *
 * Co pilnuje (jeden warunek na przypadek):
 *   A. kreator WordPressa NAPRAWDĘ widzi eksporter i kasownik sklepu — sama obecność
 *      funkcji nic nie daje, jeśli filtr ich nie zwraca. To zarazem KONTROLA DODATNIA:
 *      na kodzie sprzed naprawy sekcja A pada i test kończy się tu, EXIT=1.
 *   B. eksport wydaje dane istniejącego kupującego (zamówienie, opinia, sesja płatności).
 *   C. eksport nieznanego adresu jest PUSTY — przypadek BEZ wady, żeby „naprawa" nie
 *      zaczęła wydawać cudzych danych każdemu, kto zapyta.
 *   D. wniosek jednej osoby nie wydaje danych drugiej.
 *   E. stronicowanie oddaje KOMPLET — eksport z dziurą jest gorszy niż brak eksportu,
 *      bo nikt go nie zauważy. Pułapka: DOKŁADNIE pełna pierwsza strona.
 *   F. kasownik czyści ślad wtórny (dziennik zdarzeń) i źródło (adres w zamówieniu).
 *   G. kasownik ZOSTAWIA dane do faktury (obowiązek z ustawy o VAT, DECYZJE.md D36)
 *      i mówi o tym wprost w `items_retained` + powodzie.
 *   H. kasowanie osoby A nie tyka zamówienia osoby B.
 *
 * Uruchomienie:
 *   docker cp testy/sklep-rodo-narzedzia-wp-test.php strona_wp_wordpress:/tmp/sklep-rodo.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/sklep-rodo.php; echo EXIT=$?
 */

$GLOBALS['sklepag_t_bledy'] = 0;

function sklepag_t_sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_t_bledy'];
	}
}

function sklepag_t_rowne( $opis, $oczekiwane, $mam ) {
	sklepag_t_sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

/** Wszystkie wartości z paczki eksportu spłaszczone do jednego napisu. */
function sklepag_t_tresc( $wynik ) {
	$tekst = '';
	foreach ( (array) $wynik['data'] as $grupa ) {
		foreach ( (array) $grupa['data'] as $pole ) {
			$tekst .= $pole['value'] . "\n";
		}
	}
	return $tekst;
}

global $wpdb;
$tzam = $wpdb->prefix . 'sklepag_zamowienia';
$tzda = $wpdb->prefix . 'sklepag_zdarzenia';
$topi = $wpdb->prefix . 'sklepag_opinie';
$tses = $wpdb->prefix . 'sklepag_sesje_checkout';

$mail_a = 'rodo-sklep-a@example.test';
$mail_b = 'rodo-sklep-b@example.test';
$mail_c = 'rodo-sklep-c@example.test';
$maile  = array( $mail_a, $mail_b, $mail_c );

/** Sprzątanie — po tym przebiegu i po ewentualnym poprzednim. */
function sklepag_t_sprzataj( $maile ) {
	global $wpdb;
	$tzam = $wpdb->prefix . 'sklepag_zamowienia';
	$tzda = $wpdb->prefix . 'sklepag_zdarzenia';
	$topi = $wpdb->prefix . 'sklepag_opinie';
	$tses = $wpdb->prefix . 'sklepag_sesje_checkout';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$ids = $wpdb->get_col( "SELECT id FROM {$tzam} WHERE stripe_session_id LIKE 'rodoSklep%'" );
	if ( $ids ) {
		$lista = implode( ',', array_map( 'intval', $ids ) );
		$wpdb->query( "DELETE FROM {$topi} WHERE zamowienie_id IN ({$lista})" );
		$wpdb->query( "DELETE FROM {$tzda} WHERE zamowienie_id IN ({$lista})" );
	}
	$wpdb->query( "DELETE FROM {$tzam} WHERE stripe_session_id LIKE 'rodoSklep%'" );
	$wpdb->query( "DELETE FROM {$tses} WHERE stripe_session_id LIKE 'rodoSklep%'" );
	foreach ( $maile as $m ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tzda} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $m ) . '%' ) );
	}
	// phpcs:enable
}

/** Zamówienie testowe. Zwraca id. */
function sklepag_t_zamowienie( $sesja, $email, $nazwa, $faktura = '' ) {
	global $wpdb;
	$teraz = current_time( 'mysql' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$wpdb->prefix . 'sklepag_zamowienia',
		array(
			'stripe_session_id'     => $sesja,
			'produkt_id'            => 0,
			'produkt_nazwa_kupiona' => $nazwa,
			'produkt_cena_kupiona'  => 4900,
			'email_kupujacego'      => $email,
			'kwota_grosze'          => 4900,
			'waluta'                => 'PLN',
			'status'                => 'mail_wyslany',
			'mail_tresc'            => 'Dziękujemy za zakup, ' . $email,
			'dane_faktury'          => $faktura,
			'utworzono'             => $teraz,
			'zmieniono'             => $teraz,
		)
	);
	return (int) $wpdb->insert_id;
}

sklepag_t_sprzataj( $maile );

echo "=== A. Kreator WordPressa NAPRAWDE widzi sklep (kontrola dodatnia) ===\n";
$eksportery = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$kasowniki  = apply_filters( 'wp_privacy_personal_data_erasers', array() );
sklepag_t_sprawdz( 'eksporter sklepu zarejestrowany w kreatorze', isset( $eksportery['sklep-agentow'] ) );
sklepag_t_sprawdz( 'kasownik sklepu zarejestrowany w kreatorze', isset( $kasowniki['sklep-agentow'] ) );
sklepag_t_sprawdz( 'eksporter ma wywolywalny callback', isset( $eksportery['sklep-agentow']['callback'] ) && is_callable( $eksportery['sklep-agentow']['callback'] ) );
sklepag_t_sprawdz( 'kasownik ma wywolywalny callback', isset( $kasowniki['sklep-agentow']['callback'] ) && is_callable( $kasowniki['sklep-agentow']['callback'] ) );
sklepag_t_sprawdz( 'wtyczka doklada tresc do polityki prywatnosci', false !== has_action( 'admin_init', 'sklepag_polityka_prywatnosci' ) );
sklepag_t_sprawdz( 'anonimizacja jest wywolywalna POZA zadaniem HTTP panelu', function_exists( 'sklepag_anonimizuj_zamowienie' ) );

if ( $GLOBALS['sklepag_t_bledy'] ) {
	echo "\nNIEPOWODZENIE: sklep NIE jest wpiety w mechanizm prywatnosci WordPressa — "
		. $GLOBALS['sklepag_t_bledy'] . " krokow nie przeszlo. Dalszych sekcji nie ma na czym uruchomic.\n";
	exit( 1 );
}

$eksport = $eksportery['sklep-agentow']['callback'];
$kasuj   = $kasowniki['sklep-agentow']['callback'];

echo "\n=== B. Eksport wydaje dane istniejacego kupujacego ===\n";
$id_a = sklepag_t_zamowienie( 'rodoSklepA1', $mail_a, 'ZAM-A-BOT', 'Firma A sp. z o.o., NIP 1234563218, ul. Testowa 1, Warszawa' );
$teraz = current_time( 'mysql' );
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $topi, array( 'zamowienie_id' => $id_a, 'produkt_id' => 0, 'imie' => 'Anna-RODO', 'gwiazdki' => 5, 'tytul' => 'TYT-A', 'tresc' => 'OPI-A tresc opinii', 'status' => 'przyjeta', 'token_hash' => str_repeat( 'a', 64 ), 'utworzono' => $teraz, 'zmieniono' => $teraz ) );
$wpdb->insert( $tses, array( 'stripe_session_id' => 'rodoSklepA1', 'produkt_id' => 0, 'stan' => 'oplacona', 'utworzono' => $teraz ) );
// phpcs:enable
sklepag_zdarzenie( $id_a, 'test_rodo', 'wpis dziennika z adresem ' . $mail_a . ' w tresci' );

$w_a   = call_user_func( $eksport, $mail_a, 1 );
$tre_a = sklepag_t_tresc( $w_a );
sklepag_t_sprawdz( 'eksport zawiera adres e-mail kupujacego', false !== strpos( $tre_a, $mail_a ), $tre_a );
sklepag_t_sprawdz( 'eksport zawiera nazwe kupionego produktu', false !== strpos( $tre_a, 'ZAM-A-BOT' ) );
sklepag_t_sprawdz( 'eksport zawiera dane do faktury', false !== strpos( $tre_a, 'NIP 1234563218' ) );
sklepag_t_sprawdz( 'eksport zawiera opinie kupujacego', false !== strpos( $tre_a, 'OPI-A tresc opinii' ) );
sklepag_t_sprawdz( 'eksport zawiera sesje platnosci', false !== strpos( $tre_a, 'rodoSklepA1' ) );
sklepag_t_rowne( 'przy malej liczbie danych konczy sie na pierwszej stronie', true, $w_a['done'] );

echo "\n=== C. PRZYPADEK BEZ WADY: nieznany adres dostaje PUSTKE ===\n";
$w_nikt = call_user_func( $eksport, 'rodo-sklep-nikt@example.test', 1 );
sklepag_t_rowne( 'eksport nieznanego adresu nie ma ani jednej pozycji', 0, count( (array) $w_nikt['data'] ) );
sklepag_t_rowne( 'i konczy sie od razu', true, $w_nikt['done'] );

echo "\n=== D. Wniosek jednej osoby NIE wydaje danych drugiej ===\n";
$id_b = sklepag_t_zamowienie( 'rodoSklepB1', $mail_b, 'ZAM-B-BOT', 'Firma B, NIP 5252248481, ul. Druga 2, Krakow' );
$w_a2   = call_user_func( $eksport, $mail_a, 1 );
$tre_a2 = sklepag_t_tresc( $w_a2 );
sklepag_t_sprawdz( 'eksport osoby A nie zawiera zamowienia osoby B', false === strpos( $tre_a2, 'ZAM-B-BOT' ), $tre_a2 );
sklepag_t_sprawdz( 'ani adresu osoby B', false === strpos( $tre_a2, $mail_b ) );

echo "\n=== E. Stronicowanie oddaje KOMPLET (pulapka: dokladnie pelna strona) ===\n";
$porcja = (int) SKLEPAG_RODO_PORCJA;
$ids_c  = array();
for ( $i = 0; $i < $porcja; $i++ ) {
	$ids_c[] = sklepag_t_zamowienie( 'rodoSklepC' . $i, $mail_c, 'ZAMC-' . $i, '' );
}
for ( $i = 0; $i < 3; $i++ ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( $topi, array( 'zamowienie_id' => $ids_c[ $i ], 'produkt_id' => 0, 'imie' => 'Cezary-RODO', 'gwiazdki' => 4, 'tytul' => 'TYTC-' . $i, 'tresc' => 'OPIC-' . $i, 'status' => 'przyjeta', 'token_hash' => '', 'utworzono' => $teraz, 'zmieniono' => $teraz ) );
}
for ( $i = 0; $i < 2; $i++ ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( $tses, array( 'stripe_session_id' => 'rodoSklepC' . $i, 'produkt_id' => 0, 'stan' => 'oplacona', 'utworzono' => $teraz ) );
}

$wszystko = '';
$strona   = 1;
do {
	$w         = call_user_func( $eksport, $mail_c, $strona );
	$wszystko .= sklepag_t_tresc( $w );
	++$strona;
} while ( empty( $w['done'] ) && $strona < 20 );

sklepag_t_sprawdz( 'kreator dostal wiecej niz jedna strone', $strona > 2, 'stron: ' . ( $strona - 1 ) );
$brakujace = array();
for ( $i = 0; $i < $porcja; $i++ ) {
	if ( false === strpos( $wszystko, 'ZAMC-' . $i . "\n" ) ) {
		$brakujace[] = 'ZAMC-' . $i;
	}
}
for ( $i = 0; $i < 3; $i++ ) {
	if ( false === strpos( $wszystko, 'OPIC-' . $i . "\n" ) ) {
		$brakujace[] = 'OPIC-' . $i;
	}
}
for ( $i = 0; $i < 2; $i++ ) {
	if ( false === strpos( $wszystko, 'rodoSklepC' . $i . "\n" ) ) {
		$brakujace[] = 'sesja rodoSklepC' . $i;
	}
}
sklepag_t_rowne( 'zaden wiersz nie wypadl ze stronicowania', 0, count( $brakujace ), 'brakuje: ' . implode( ',', array_slice( $brakujace, 0, 5 ) ) );

echo "\n=== F. Kasownik czysci slad WTORNY i ZRODLO ===\n";
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$slad_przed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tzda} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $mail_a ) . '%' ) );
sklepag_t_rowne( 'przed kasowaniem adres SIEDZI w dzienniku zdarzen', 1, $slad_przed );

$k_a = call_user_func( $kasuj, $mail_a, 1 );
sklepag_t_rowne( 'kasownik melduje usuniecie', true, $k_a['items_removed'] );

$po_a = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego, usunieto_dnia, mail_tresc, token_pobrania, dane_faktury FROM {$tzam} WHERE id = %d", $id_a ) );
sklepag_t_rowne( 'ZRODLO: adres w zamowieniu usuniety', '', (string) $po_a->email_kupujacego );
sklepag_t_sprawdz( 'zamowienie ma znacznik usunieto_dnia', ! empty( $po_a->usunieto_dnia ), (string) $po_a->usunieto_dnia );
sklepag_t_rowne( 'tresc maila (kopia danych kupujacego) usunieta', '', (string) $po_a->mail_tresc );
$slad_po = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tzda} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $mail_a ) . '%' ) );
sklepag_t_rowne( 'SLAD WTORNY: adresu nie ma juz w dzienniku zdarzen', 0, $slad_po );
$imie_po = (string) $wpdb->get_var( $wpdb->prepare( "SELECT imie FROM {$topi} WHERE zamowienie_id = %d", $id_a ) );
sklepag_t_rowne( 'SLAD WTORNY: imie przy opinii usuniete', '', $imie_po );

echo "\n=== G. Dane wymagane prawem ZOSTAJA i kasownik MOWI o tym wprost ===\n";
sklepag_t_sprawdz( 'dane do faktury nietkniete (obowiazek z ustawy o VAT)', false !== strpos( (string) $po_a->dane_faktury, 'NIP 1234563218' ), (string) $po_a->dane_faktury );
sklepag_t_rowne( 'items_retained = true, bo cos ZOSTALO', true, $k_a['items_retained'] );
$kom_a = implode( ' ', (array) $k_a['messages'] );
sklepag_t_sprawdz( 'komunikat nazywa, CO zostalo', false !== stripos( $kom_a, 'faktur' ), $kom_a );
sklepag_t_sprawdz( 'i podaje POWOD (podstawe prawna)', false !== stripos( $kom_a, 'VAT' ), $kom_a );

echo "\n=== H. Kasowanie osoby A NIE tyka osoby B ===\n";
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$po_b = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego, usunieto_dnia FROM {$tzam} WHERE id = %d", $id_b ) );
sklepag_t_rowne( 'adres osoby B NIETKNIETY', $mail_b, (string) $po_b->email_kupujacego );
sklepag_t_sprawdz( 'zamowienie osoby B bez znacznika usuniecia', empty( $po_b->usunieto_dnia ) );

$k_nikt = call_user_func( $kasuj, 'rodo-sklep-nikt@example.test', 1 );
sklepag_t_rowne( 'PRZYPADEK BEZ WADY: nieznany adres nie melduje usuniecia', false, $k_nikt['items_removed'] );
sklepag_t_rowne( 'ani nie melduje zachowania czegokolwiek', false, $k_nikt['items_retained'] );
// phpcs:enable

echo "\n=== I. Sprzatanie ===\n";
sklepag_t_sprzataj( $maile );
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$zostalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tzam} WHERE stripe_session_id LIKE 'rodoSklep%'" );
// phpcs:enable
sklepag_t_rowne( 'dane testowe usuniete', 0, $zostalo );

echo "\n" . ( $GLOBALS['sklepag_t_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['sklepag_t_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['sklepag_t_bledy'] ? 1 : 0 );
