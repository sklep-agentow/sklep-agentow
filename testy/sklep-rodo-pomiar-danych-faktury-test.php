<?php
/**
 * Pomiar skutku anonimizacji NIE OBEJMOWAŁ kolumny `dane_faktury`. Strażnik, EXIT=1 przy wadzie.
 *
 * KLASA: pomiar skutku WĘŻSZY niż zapis. `sklepag_anonimizuj_zamowienie()` czyściła
 * jedenaście kolumn, a mierzyła siedem — wśród niezmierzonych była JEDYNA pozostała
 * kolumna z danymi osobowymi: nazwa firmy, NIP i adres kupującego. Gdyby czyszczenie
 * akurat tej kolumny nie doszło do skutku, funkcja meldowała sukces, panel pokazywał
 * „Kupujący zanonimizowany", a właściciel potwierdzał człowiekowi usunięcie danych,
 * które nadal leżą w bazie. To ta sama klasa co „potwierdzenie bez pomiaru" z #234,
 * tylko o jedną kolumnę dalej.
 *
 * ⛔ TO JEST PRÓBA PODŁOŻONA. Filtr `query` wycina z zapytania anonimizacji DOKŁADNIE
 *    JEDEN fragment — `dane_faktury='', ` — i nic więcej. Reszta zapytania wykonuje się
 *    normalnie, więc przypadek psuje DOKŁADNIE JEDEN warunek i odróżnia „nie zmierzono
 *    faktury" od „cokolwiek padło".
 *
 * Przypadki (każdy psuje najwyżej jeden warunek):
 *   A. BEZ WADY — domyślne wywołanie (panel, od 0.9.37): faktura ZOSTAJE, funkcja melduje sukces
 *                 (do 0.9.36 panel czyścił fakturę; od 0.9.37 obie drogi ją zachowują — jeden obowiązek podatkowy).
 *   B. WADA     — podłożone pominięcie kolumny: funkcja MUSI odmówić (`ok=false`),
 *                 zapisać `anonimizacja_blad` i NIE zapisać `anonimizacja`.
 *   C. BEZ WADY — tryb kasownika RODO: faktura ZOSTAJE i to nie jest awaria. Bez tego
 *                 przypadku „naprawa" mogłaby uznać wymóg podatkowy za nieudany zapis.
 *   D. BEZ WADY — zamówienie bez danych do faktury: pusta kolumna to nie brak skutku.
 *
 * Uruchomienie:
 *   docker cp testy/sklep-rodo-pomiar-danych-faktury-test.php strona_wp_wordpress:/tmp/pf.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/pf.php; echo EXIT=$?
 */

$GLOBALS['sklepag_pf_bledy'] = 0;

function sklepag_pf_sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_pf_bledy'];
	}
}

function sklepag_pf_rowne( $opis, $oczekiwane, $mam ) {
	sklepag_pf_sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

/** Zamówienie testowe z kompletem rzeczy, które anonimizacja ma sprzątnąć. Zwraca id. */
function sklepag_pf_zamowienie( $znacznik, $email, $faktura ) {
	global $wpdb;
	$teraz = current_time( 'mysql' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$wpdb->prefix . 'sklepag_zamowienia',
		array(
			'stripe_session_id'     => 'pomiarFaktury_' . $znacznik,
			'produkt_id'            => 0,
			'produkt_nazwa_kupiona' => 'Zamowienie probne (pomiar faktury)',
			'email_kupujacego'      => $email,
			'kwota_grosze'          => 4900,
			'status'                => 'mail_wyslany',
			'token_pobrania'        => str_repeat( 'c', 64 ),
			'mail_tresc'            => 'Dziękujemy za zakup, ' . $email,
			'dane_faktury'          => $faktura,
			'utworzono'             => $teraz,
			'zmieniono'             => $teraz,
		)
	);
	return (int) $wpdb->insert_id;
}

/** Ile zdarzeń danego typu ma to zamówienie. */
function sklepag_pf_zdarzen( $id, $typ ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id = %d AND typ = %s",
			$id,
			$typ
		)
	);
	// phpcs:enable
}

/** Kolumna zamówienia po zapisie — odczyt świeżego stanu, nie pamięć. */
function sklepag_pf_kolumna( $id, $kolumna ) {
	global $wpdb;
	$dozwolone = array( 'email_kupujacego', 'dane_faktury', 'mail_tresc', 'usunieto_dnia', 'token_pobrania' );
	if ( ! in_array( $kolumna, $dozwolone, true ) ) {
		return null;
	}
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT {$kolumna} FROM {$wpdb->prefix}sklepag_zamowienia WHERE id = %d", $id ) );
	// phpcs:enable
}

function sklepag_pf_sprzataj() {
	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$ids = $wpdb->get_col( "SELECT id FROM {$pz} WHERE stripe_session_id LIKE 'pomiarFaktury%'" );
	if ( $ids ) {
		$lista = implode( ',', array_map( 'intval', $ids ) );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id IN ({$lista})" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id IN ({$lista})" );
	}
	$wpdb->query( "DELETE FROM {$pz} WHERE stripe_session_id LIKE 'pomiarFaktury%'" );
	// phpcs:enable
}

global $wpdb;
sklepag_pf_sprzataj();

if ( ! function_exists( 'sklepag_anonimizuj_zamowienie' ) ) {
	echo "PRZYRZAD=BRAK-FUNKCJI-ANONIMIZACJI (wtyczka sprzed wydzielenia?)\n";
	exit( 2 );
}

$faktura = 'Firma PF sp. z o.o., NIP 1234563218, ul. Probna 7, Gdansk';

echo "=== A. BEZ WADY — domyslne wywolanie (panel od 0.9.37): faktura ZOSTAJE, sukces ===\n";
$id_a = sklepag_pf_zamowienie( 'A', 'pomiar-faktury-a@example.test', $faktura );
$w_a  = sklepag_anonimizuj_zamowienie( $id_a );
sklepag_pf_rowne( 'anonimizacja panelu konczy sie sukcesem', true, $w_a['ok'] );
sklepag_pf_rowne( 'etap: gotowe', 'gotowe', $w_a['etap'] );
sklepag_pf_sprawdz( 'dane do faktury ZOSTAJA (obowiazek podatkowy, jak w kasowniku)', $faktura === sklepag_pf_kolumna( $id_a, 'dane_faktury' ), sklepag_pf_kolumna( $id_a, 'dane_faktury' ) );
sklepag_pf_rowne( 'i funkcja melduje zachowana fakture', true, $w_a['zachowana_faktura'] );
sklepag_pf_rowne( 'adres e-mail wyczyszczony', '', sklepag_pf_kolumna( $id_a, 'email_kupujacego' ) );
sklepag_pf_rowne( 'zapisane zdarzenie anonimizacji', 1, sklepag_pf_zdarzen( $id_a, 'anonimizacja' ) );
sklepag_pf_rowne( 'i ani jednego zdarzenia bledu', 0, sklepag_pf_zdarzen( $id_a, 'anonimizacja_blad' ) );

echo "\n=== B. WADA PODLOZONA: z zapytania znika DOKLADNIE kolumna dane_faktury ===\n";
$id_b     = sklepag_pf_zamowienie( 'B', 'pomiar-faktury-b@example.test', $faktura );
$trafione = 0;
$psuj     = static function ( $zapytanie ) use ( &$trafione ) {
	/* Celujemy w trzy znaczniki naraz, zeby nie trafic w inne UPDATE tej samej tabeli. */
	if ( false !== strpos( $zapytanie, "email_kupujacego=''" )
		&& false !== strpos( $zapytanie, 'usunieto_dnia' )
		&& false !== strpos( $zapytanie, "dane_faktury=''" ) ) {
		++$trafione;
		/* JEDNA zmiana: kolumna wypada z listy SET. Reszta zapytania biegnie normalnie. */
		return str_replace( "dane_faktury='', ", '', $zapytanie );
	}
	return $zapytanie;
};
add_filter( 'query', $psuj, 99 );
$w_b = sklepag_anonimizuj_zamowienie( $id_b, false ); // jawne czyszczenie faktury: tylko po to, zeby podlozyc wade w zapytaniu
remove_filter( 'query', $psuj, 99 );

sklepag_pf_rowne( 'podlozenie trafilo w zapytanie — jest co mierzyc', 1, $trafione );
sklepag_pf_sprawdz( 'warunek wstepny: dane do faktury NAPRAWDE zostaly w bazie', $faktura === sklepag_pf_kolumna( $id_b, 'dane_faktury' ), sklepag_pf_kolumna( $id_b, 'dane_faktury' ) );
sklepag_pf_rowne( 'reszta zapytania POSZLA — adres e-mail jednak zniknal', '', sklepag_pf_kolumna( $id_b, 'email_kupujacego' ) );
sklepag_pf_rowne( 'i tresc maila tez', '', sklepag_pf_kolumna( $id_b, 'mail_tresc' ) );

sklepag_pf_rowne( 'SEDNO: funkcja NIE potwierdza usuniecia', false, $w_b['ok'] );
sklepag_pf_rowne( 'i nazywa etap: zapis', 'zapis', $w_b['etap'] );
sklepag_pf_rowne( 'zapisane zdarzenie bledu', 1, sklepag_pf_zdarzen( $id_b, 'anonimizacja_blad' ) );
sklepag_pf_rowne( 'i ANI JEDNEGO zdarzenia „zanonimizowany”', 0, sklepag_pf_zdarzen( $id_b, 'anonimizacja' ) );

echo "\n=== C. BEZ WADY — kasownik RODO: faktura ZOSTAJE i to nie jest awaria ===\n";
$id_c = sklepag_pf_zamowienie( 'C', 'pomiar-faktury-c@example.test', $faktura );
$w_c  = sklepag_anonimizuj_zamowienie( $id_c, true );
sklepag_pf_rowne( 'kasownik konczy sie sukcesem mimo zachowanej faktury', true, $w_c['ok'] );
sklepag_pf_rowne( 'i melduje, ze faktura zostala zachowana', true, $w_c['zachowana_faktura'] );
sklepag_pf_sprawdz( 'dane do faktury NIETKNIETE (obowiazek podatkowy)', $faktura === sklepag_pf_kolumna( $id_c, 'dane_faktury' ), sklepag_pf_kolumna( $id_c, 'dane_faktury' ) );
sklepag_pf_rowne( 'adres e-mail mimo to usuniety', '', sklepag_pf_kolumna( $id_c, 'email_kupujacego' ) );
sklepag_pf_rowne( 'zdarzenie anonimizacji zapisane', 1, sklepag_pf_zdarzen( $id_c, 'anonimizacja' ) );
sklepag_pf_rowne( 'i zadnego bledu', 0, sklepag_pf_zdarzen( $id_c, 'anonimizacja_blad' ) );

echo "\n=== D. BEZ WADY — zamowienie BEZ danych do faktury (zakup prywatny) ===\n";
$id_d = sklepag_pf_zamowienie( 'D', 'pomiar-faktury-d@example.test', '' );
$w_d  = sklepag_anonimizuj_zamowienie( $id_d );
sklepag_pf_rowne( 'pusta kolumna to nie brak skutku', true, $w_d['ok'] );
sklepag_pf_rowne( 'i nie melduje zachowanej faktury', false, $w_d['zachowana_faktura'] );
sklepag_pf_rowne( 'zadnego zdarzenia bledu', 0, sklepag_pf_zdarzen( $id_d, 'anonimizacja_blad' ) );

echo "\n=== E. Sprzatanie ===\n";
sklepag_pf_sprzataj();
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$zostalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zamowienia WHERE stripe_session_id LIKE 'pomiarFaktury%'" );
// phpcs:enable
sklepag_pf_rowne( 'dane testowe usuniete', 0, $zostalo );

echo "\n" . ( $GLOBALS['sklepag_pf_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['sklepag_pf_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['sklepag_pf_bledy'] ? 1 : 0 );
