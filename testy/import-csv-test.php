<?php
/**
 * Import bazy Q&A z CSV — test na PRAWDZIWEJ bazie i prawdziwym kodzie wtyczki.
 *
 * Sednem tej funkcji jest obietnica: „to, co widzisz w podglądzie, to jest to,
 * co się zapisze". Test sprawdza ją dosłownie — porównuje wiersze w bazie
 * z obiektem podglądu, znak po znaku — a nie tylko to, że coś się wstawiło.
 *
 * Wiersze testowe mają pytania z przedrostkiem 'TEST-IMP-' i są kasowane na
 * końcu, także gdy któryś krok nie przeszedł.
 *
 * Uruchomienie:
 *   docker cp testy/import-csv-test.php strona_wp_wordpress:/tmp/import-csv-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/import-csv-test.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
// statystyki.php i import.php wtyczka ładuje tylko w panelu (is_admin()),
// a wp-cli panelem nie jest — dociągamy je wprost, jak inne testy panelu.
require_once $katalog . 'statystyki.php';
require_once $katalog . 'admin.php';
require_once $katalog . 'csv.php';
require_once $katalog . 'import.php';

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
	sprawdz(
		$opis,
		$oczekiwane === $mam,
		'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']'
	);
}

global $wpdb;
$tabela_qa = $wpdb->prefix . 'chatl_qa';

/** Ile wierszy testowych jest teraz w bazie. */
function chatl_test_ile_testowych() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_qa WHERE pytanie LIKE 'TEST-IMP-%'" ); // phpcs:ignore
}

/** Wiersz z bazy po pytaniu. */
function chatl_test_wiersz( $pytanie ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}chatl_qa WHERE pytanie = %s", $pytanie ), ARRAY_A ); // phpcs:ignore
}

/** Sprzątanie — wywoływane też przy porażce. */
function chatl_test_sprzataj() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_qa WHERE pytanie LIKE 'TEST-IMP-%'" ); // phpcs:ignore
}

chatl_test_sprzataj();

/* ============ A. CZYTANIE PLIKU, KTORY PRZYSZEDL Z EXCELA ============ */

echo "=== A. Plik z Excela: separator, kodowanie, BOM, kolejnosc kolumn ===\n";

rowne( 'separator: przecinek gdy przecinkow wiecej', ',', chatl_import_separator( 'pytanie,odpowiedz,aktywne' ) );
rowne( 'separator: srednik gdy srednikow wiecej (polski Excel)', ';', chatl_import_separator( 'pytanie;odpowiedz;aktywne' ) );
rowne( 'separator: remis rozstrzygamy na przecinek (nasz eksport)', ',', chatl_import_separator( 'pytanie' ) );

$csv_srednik = "pytanie;slowa_kluczowe;odpowiedz\nTEST-IMP-Ile kosztuje dostawa;\"dostaw, wysylk\";Dostawa kosztuje 15 zl.\n";
$w_srednik   = chatl_import_parsuj( $csv_srednik );
rowne( 'plik ze srednikiem: brak bledu', '', $w_srednik['blad'] );
rowne( 'plik ze srednikiem: jeden wiersz', 1, count( $w_srednik['wiersze'] ) );
rowne( 'plik ze srednikiem: pytanie odczytane w calosci', 'TEST-IMP-Ile kosztuje dostawa', $w_srednik['wiersze'][0]['pytanie'] );

$csv_bom = "\xEF\xBB\xBFpytanie,odpowiedz\nTEST-IMP-Z BOM,Odpowiedz z BOM\n";
$w_bom   = chatl_import_parsuj( $csv_bom );
rowne( 'BOM z naszego wlasnego eksportu zdjety', '', $w_bom['blad'] );
rowne( 'BOM: pierwsza kolumna rozpoznana jako pytanie', 'TEST-IMP-Z BOM', $w_bom['wiersze'][0]['pytanie'] );
// Kontrakt samej funkcji, przypiety wprost. Powod jest zmierzony proba kontrolna:
// naglowek przezylby BOM takze bez tej funkcji, bo normalizator nazw kolumn
// zamienia jej bajty na "_". Bez tego kroku usuniecie chatl_import_bez_bom()
// przechodziloby przez straznika NIEZAUWAZONE.
rowne( 'chatl_import_bez_bom zdejmuje BOM', 'pytanie,odpowiedz', chatl_import_bez_bom( "\xEF\xBB\xBFpytanie,odpowiedz" ) );
rowne( 'i nie rusza pliku bez BOM-u', 'pytanie,odpowiedz', chatl_import_bez_bom( 'pytanie,odpowiedz' ) );

// UWAGA: konwersja W TESCIE tez idzie przez iconv, bo mbstring NIE ZNA CP1250
// (nie ma go w mb_list_encodings na PHP 8.4) — mb_convert_encoding rzucilby tu
// ValueError. To ta sama pulapka, ktora ten test wykryl w kodzie wtyczki.
$csv_1250 = iconv( 'UTF-8', 'CP1250', "pytanie,odpowiedz\nTEST-IMP-Zażółć gęślą jaźń,Zdążyłem\n" );
sprawdz( 'plik testowy naprawde JEST w cp1250, a nie w utf-8', ! mb_check_encoding( $csv_1250, 'UTF-8' ) );
$w_1250   = chatl_import_parsuj( $csv_1250 );
rowne( 'windows-1250 przekonwertowane, nie krzaki', 'TEST-IMP-Zażółć gęślą jaźń', $w_1250['wiersze'][0]['pytanie'] );

$csv_kolejnosc = "aktywne,odpowiedz,Pytanie,Słowa kluczowe\n0,Odpowiedz X,TEST-IMP-Kolejnosc,\"klucz, wariant\"\n";
$w_kolejnosc   = chatl_import_parsuj( $csv_kolejnosc );
rowne( 'kolumny mapowane po NAZWIE, nie po pozycji', 'TEST-IMP-Kolejnosc', $w_kolejnosc['wiersze'][0]['pytanie'] );
rowne( 'naglowek z ogonkami i wielka litera rozpoznany', 'plik', $w_kolejnosc['wiersze'][0]['zrodlo_slow'] );
rowne( 'kolumna aktywne=0 uszanowana', 0, $w_kolejnosc['wiersze'][0]['aktywne'] );

rowne( 'aktywne: puste = wpis ma dzialac', 1, chatl_import_aktywne( '' ) );
rowne( 'aktywne: "nie" = wylaczony', 0, chatl_import_aktywne( 'nie' ) );
rowne( 'aktywne: "NIE" (wielkie litery) tez', 0, chatl_import_aktywne( 'NIE' ) );
rowne( 'aktywne: "tak" = wlaczony', 1, chatl_import_aktywne( 'tak' ) );

rowne( 'apostrof neutralizacji formul zdjety', '=SUMA(A1)', chatl_import_odapostrofuj( "'=SUMA(A1)" ) );
rowne( 'apostrof w normalnym tekscie NIE ruszany', "'zwykly apostrof", chatl_import_odapostrofuj( "'zwykly apostrof" ) );

/* ============ B. SLOWA KLUCZOWE: PODPOWIEDZ I OSTRZEZENIE ============ */

echo "\n=== B. Slowa kluczowe — bo TYLKO po nich bot dopasowuje ===\n";

$csv_bez_slow = "pytanie,odpowiedz\nTEST-IMP-Ile kosztuje konsultacja u dentysty,Konsultacja kosztuje 150 zl.\n";
$w_bez_slow   = chatl_import_parsuj( $csv_bez_slow );
$wiersz_b     = $w_bez_slow['wiersze'][0];
rowne( 'brak kolumny ze slowami => podpowiadamy', 'wygenerowane', $wiersz_b['zrodlo_slow'] );
sprawdz( 'podpowiedz nie jest pusta', '' !== $wiersz_b['slowa'], 'slowa: [' . $wiersz_b['slowa'] . ']' );
rowne(
	'podpowiedz to DOKLADNIE wynik chatl_sugerowane_slowa (jedno zrodlo reguly)',
	chatl_sugerowane_slowa( 'TEST-IMP-Ile kosztuje konsultacja u dentysty' ),
	$wiersz_b['slowa']
);

// Pytanie z samych krotkich slowek: chatl_rdzenie_pytania nie da ani jednego
// rdzenia, wiec bot NIGDY nie trafi w ten wpis — i to musi byc widac.
$pusta_podpowiedz = chatl_sugerowane_slowa( 'Ile to?' );
rowne( 'z samych krotkich slowek nie ma rdzeni', '', $pusta_podpowiedz );
sprawdz(
	'pusta podpowiedz => ostrzezenie "bot NIGDY nie trafi"',
	false !== strpos( chatl_import_ostrzezenie( '' ), 'NIGDY' ),
	chatl_import_ostrzezenie( '' )
);
sprawdz(
	'wariant krotszy niz CHATL_SUGESTIA_RDZEN => ostrzezenie o lapaniu nie tego',
	false !== strpos( chatl_import_ostrzezenie( 'kosz' ), 'koszule' ),
	chatl_import_ostrzezenie( 'kosz' )
);
rowne( 'wariant rowny CHATL_SUGESTIA_RDZEN => bez ostrzezenia', '', chatl_import_ostrzezenie( 'koszt' ) );
rowne( 'slowa z pliku NIE sa podmieniane podpowiedzia', 'plik', $w_srednik['wiersze'][0]['zrodlo_slow'] );
rowne( 'i zostaja takie, jak je wpisal wlasciciel', 'dostaw, wysylk', $w_srednik['wiersze'][0]['slowa'] );
rowne( 'przecinek W CUDZYSLOWIE nie rozbija kolumn', 'Dostawa kosztuje 15 zl.', $w_srednik['wiersze'][0]['odpowiedz'] );

/* ============ C. DUPLIKATY ============ */

echo "\n=== C. Duplikaty — import DOPISUJE, nie nadpisuje ===\n";

$wpdb->insert(
	$tabela_qa,
	array(
		'pytanie'        => 'TEST-IMP-Juz w bazie',
		'slowa_kluczowe' => 'juz, bazie',
		'odpowiedz'      => 'Stara odpowiedz, ktorej import NIE ma prawa ruszyc.',
		'aktywne'        => 1,
		'utworzono'      => current_time( 'mysql' ),
	),
	array( '%s', '%s', '%s', '%d', '%s' )
);

$csv_dupy = "pytanie,slowa_kluczowe,odpowiedz\n"
	. "TEST-IMP-Juz w bazie,\"inne, slowa\",NOWA odpowiedz z pliku\n"
	. "TEST-IMP-Powtorka w pliku,aaaaa,Pierwsza\n"
	. "test-imp-powtorka w pliku?,bbbbb,Druga (to samo pytanie innym zapisem)\n"
	. "TEST-IMP-Nowy wpis,ccccc,Trzecia\n";
$w_dupy   = chatl_import_parsuj( $csv_dupy );

rowne( 'duplikat wobec bazy rozpoznany', 'duplikat', $w_dupy['wiersze'][0]['status'] );
sprawdz( 'i powod nazwany wprost', false !== strpos( $w_dupy['wiersze'][0]['powod'], 'w bazie' ), $w_dupy['wiersze'][0]['powod'] );
rowne( 'powtorka W PLIKU tez jest duplikatem', 'duplikat', $w_dupy['wiersze'][2]['status'] );
sprawdz( 'mimo innej wielkosci liter i znaku zapytania', false !== strpos( $w_dupy['wiersze'][2]['powod'], 'wyżej w pliku' ), $w_dupy['wiersze'][2]['powod'] );
rowne( 'liczba do zapisania', 2, $w_dupy['liczby']['nowe'] );
rowne( 'liczba duplikatow', 2, $w_dupy['liczby']['duplikaty'] );

/* ============ D. SEDNO: PODGLAD NIC NIE ZAPISUJE, ZAPIS BIERZE PODGLAD ============ */

echo "\n=== D. SEDNO: podglad nie zapisuje, a zapis bierze DOKLADNIE podglad ===\n";

$przed = chatl_test_ile_testowych();
$csv_d = "pytanie,slowa_kluczowe,odpowiedz,aktywne\n"
	. "TEST-IMP-Czy wystawiacie fakture,\"faktur, vat\",\"Tak, wystawiamy fakture VAT.\",1\n"
	. "TEST-IMP-Jak dojechac do biura,\"dojazd, parking\",Jestesmy przy ul. Testowej 1.,0\n";
$podglad = chatl_import_parsuj( $csv_d );
$po_podgladzie = chatl_test_ile_testowych();

rowne( 'podglad NIE dopisal ani jednego wiersza do bazy', $przed, $po_podgladzie );
rowne( 'podglad zapowiada 2 wpisy', 2, $podglad['liczby']['nowe'] );

$wynik_zapisu = chatl_import_zapisz_wiersze( $podglad['wiersze'] );
rowne( 'zapis melduje 2 wpisy', 2, $wynik_zapisu['zapisane'] );
rowne( 'i zadnego nieudanego', 0, $wynik_zapisu['padlo'] );
rowne( 'w bazie przybyly dokladnie 2', $przed + 2, chatl_test_ile_testowych() );

// Porownanie CO DO ZNAKU: baza vs to, co pokazal podglad.
$zgodne = true;
foreach ( $podglad['wiersze'] as $w ) {
	$z_bazy = chatl_test_wiersz( $w['pytanie'] );
	if ( ! $z_bazy
		|| $z_bazy['slowa_kluczowe'] !== $w['slowa']
		|| $z_bazy['odpowiedz'] !== $w['odpowiedz']
		|| (int) $z_bazy['aktywne'] !== (int) $w['aktywne'] ) {
		$zgodne = false;
		echo '       rozjazd na: ' . $w['pytanie'] . "\n";
	}
}
sprawdz( 'kazdy zapisany wiersz jest CO DO ZNAKU tym, co pokazal podglad', $zgodne );

$wylaczony = chatl_test_wiersz( 'TEST-IMP-Jak dojechac do biura' );
rowne( 'aktywne=0 z pliku dotrwalo do bazy', 0, (int) $wylaczony['aktywne'] );

// Duplikat NIE nadpisal starej odpowiedzi.
$stary = chatl_test_wiersz( 'TEST-IMP-Juz w bazie' );
sprawdz( 'wpis, ktory byl w bazie, zostal NIETKNIETY', 'Stara odpowiedz, ktorej import NIE ma prawa ruszyc.' === $stary['odpowiedz'], $stary['odpowiedz'] );

echo "\n=== D2. Poprawka slow z ekranu podgladu trafia do bazy ===\n";

$csv_e   = "pytanie,odpowiedz\nTEST-IMP-Pytanie do poprawki slow,Odpowiedz E\n";
$podgl_e = chatl_import_parsuj( $csv_e );
rowne( 'wiersz ma slowa podpowiedziane', 'wygenerowane', $podgl_e['wiersze'][0]['zrodlo_slow'] );
chatl_import_zapisz_wiersze( $podgl_e['wiersze'], array( 0 => 'recznie, poprawione, warianty' ) );
$po_poprawce = chatl_test_wiersz( 'TEST-IMP-Pytanie do poprawki slow' );
rowne( 'do bazy poszly slowa POPRAWIONE, nie podpowiedziane', 'recznie, poprawione, warianty', $po_poprawce['slowa_kluczowe'] );

$csv_f   = "pytanie,slowa_kluczowe,odpowiedz\nTEST-IMP-Pole wyczyszczone,waznesl,Odpowiedz F\n";
$podgl_f = chatl_import_parsuj( $csv_f );
chatl_import_zapisz_wiersze( $podgl_f['wiersze'], array( 0 => '   ' ) );
$po_pustym = chatl_test_wiersz( 'TEST-IMP-Pole wyczyszczone' );
rowne( 'wyczyszczone pole NIE zapisuje wpisu bez slow (bot by w niego nie trafil)', 'waznesl', $po_pustym['slowa_kluczowe'] );

/* ============ E. OBIEG EKSPORT -> IMPORT ============ */

echo "\n=== E. Obieg: pobierz -> popraw w arkuszu -> wgraj z powrotem ===\n";

// Tak wyglada wiersz po naszym eksporcie: komorka od '=' dostaje apostrof.
$tresc_z_formula = '=SUMA(A1:A9) to nie jest formula, tylko odpowiedz';
$po_eksporcie    = chatl_csv_komorka( $tresc_z_formula );
sprawdz( 'eksport neutralizuje formule', "'" === $po_eksporcie[0] );

$csv_round = "pytanie,slowa_kluczowe,odpowiedz\nTEST-IMP-Round trip," . 'roundt' . ',"' . $po_eksporcie . "\"\n";
$w_round   = chatl_import_parsuj( $csv_round );
rowne( 'import zdejmuje apostrof — tresc wraca IDENTYCZNA', $tresc_z_formula, $w_round['wiersze'][0]['odpowiedz'] );

/* ============ F. BRUDNE I ZLE PLIKI: KOMUNIKAT, NIE AWARIA ============ */

echo "\n=== F. Krzywy plik konczy sie KOMUNIKATEM, nie bialym ekranem ===\n";

$w_pusty = chatl_import_parsuj( '' );
sprawdz( 'pusty plik: komunikat', '' !== $w_pusty['blad'], $w_pusty['blad'] );

$w_brak_kolumn = chatl_import_parsuj( "imie,nazwisko\nJan,Kowalski\n" );
sprawdz( 'brak wymaganych kolumn: komunikat', '' !== $w_brak_kolumn['blad'], $w_brak_kolumn['blad'] );
sprawdz( 'komunikat WYMIENIA znalezione naglowki', false !== strpos( $w_brak_kolumn['blad'], 'imie' ), $w_brak_kolumn['blad'] );

$w_sam_naglowek = chatl_import_parsuj( "pytanie,odpowiedz\n" );
sprawdz( 'sam naglowek bez danych: komunikat', '' !== $w_sam_naglowek['blad'], $w_sam_naglowek['blad'] );

$w_braki = chatl_import_parsuj( "pytanie,odpowiedz\nTEST-IMP-Bez odpowiedzi,\n,Odpowiedz bez pytania\nTEST-IMP-Dobry wiersz,Dobra odpowiedz\n" );
rowne( 'wiersz bez odpowiedzi oznaczony jako blad', 'blad', $w_braki['wiersze'][0]['status'] );
rowne( 'wiersz bez pytania tez', 'blad', $w_braki['wiersze'][1]['status'] );
rowne( 'a dobry wiersz obok nich przechodzi', 'nowy', $w_braki['wiersze'][2]['status'] );
rowne( 'licznik bledow zgadza sie z tabelka', 2, $w_braki['liczby']['bledne'] );

$w_puste_linie = chatl_import_parsuj( "pytanie,odpowiedz\r\nTEST-IMP-CRLF,Odpowiedz CRLF\r\n\r\n" );
rowne( 'CRLF i pusta linia na koncu nie robia wiersza-widma', 1, count( $w_puste_linie['wiersze'] ) );

$duzy = "pytanie,odpowiedz\n";
for ( $i = 0; $i < CHATL_IMPORT_MAX_WIERSZY + 5; $i++ ) {
	$duzy .= 'TEST-IMP-Wiersz ' . $i . ',Odpowiedz ' . $i . "\n";
}
$w_duzy = chatl_import_parsuj( $duzy );
sprawdz( 'plik ponad sufit wierszy: odmowa z komunikatem, nie brak pamieci', '' !== $w_duzy['blad'], $w_duzy['blad'] );
rowne( 'i ani jeden wiersz z takiego pliku nie wchodzi', 0, count( $w_duzy['wiersze'] ) );

/* ============ G. SPRZATANIE ============ */

echo "\n=== G. Sprzatanie ===\n";
chatl_test_sprzataj();
rowne( 'wiersze testowe usuniete', 0, chatl_test_ile_testowych() );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
