<?php
/**
 * Statystyki i raport luk ChatBota — test na PRAWDZIWEJ bazie i prawdziwym kodzie.
 *
 * Dwie warstwy, dwa rodzaje sprawdzeń:
 *   A. funkcje czyste (normalizacja, rdzenie, podobieństwo, grupowanie,
 *      walidacja dat, procenty) — wejście wpisane wprost, wynik do znaku,
 *   B. funkcje czytające bazę (chatl_statystyki_okresu, chatl_luki_okresu) —
 *      sprawdzane RÓŻNICOWO: mierzymy stan przed, wkładamy znaną porcję
 *      wierszy, mierzymy po. Dzięki temu test nie zakłada, że baza jest pusta,
 *      i nie psuje się od danych, które ktoś zostawił wcześniej.
 *
 * Wiersze testowe mają sesje z przedrostkiem 'test-stat-' i są kasowane na
 * końcu — także wtedy, gdy któryś krok nie przeszedł.
 *
 * Uruchomienie:
 *   docker cp testy/statystyki-test.php strona_wp_wordpress:/tmp/statystyki-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/statystyki-test.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
// statystyki.php wtyczka ładuje tylko w panelu (is_admin()), a wp-cli panelem
// nie jest — więc w teście dociągamy go wprost, tak samo jak panel-vs-bot.php.
require_once $katalog . 'statystyki.php';

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

function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz(
		$opis,
		$oczekiwane === $mam,
		'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']'
	);
}

/* ============ A. FUNKCJE CZYSTE ============ */

echo "=== A1. Normalizacja pytania ===\n";
rowne( 'małe litery, bez ogonków, bez znaków zapytania', 'ile kosztuje dostawa', chatl_normalizuj_pytanie( '  ILE kosztuje Dostawa?? ' ) );
rowne( 'ogonki schodzą do ASCII', 'gdzie jest zolty samochod', chatl_normalizuj_pytanie( 'Gdzie jest żółty samochód' ) );
rowne( 'sama interpunkcja daje pusty wynik', '', chatl_normalizuj_pytanie( '???!!! ...' ) );

echo "\n=== A2. Rdzenie znaczących słów ===\n";
rowne( 'krótkie słówka i uprzejmości wypadają', array( 'kosz', 'dost' ), chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'Dzień dobry, ile kosztuje dostawa?' ) ) );
rowne( 'rdzeń skraca odmianę do wspólnego początku', array( 'kosz', 'dost' ), chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'koszty dostawy' ) ) );
// Odmiana potrafi siedzieć na piątym znaku („firmę"/„firmy"), dlatego rdzeń
// grupowania ma cztery znaki, a nie pięć — inaczej to samo pytanie o fakturę
// dla firmy z zagranicy rozpadałoby się na dwa tematy.
rowne( 'rdzeń łapie odmianę na piątym znaku', array( 'wyst', 'fakt', 'firm', 'zagr' ), chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'Czy wystawiacie fakturę na firmę zagraniczną?' ) ) );
sprawdz(
	'„firmę" i „firmy" dają ten sam rdzeń',
	chatl_rdzenie_pytania( 'firme' ) === chatl_rdzenie_pytania( 'firmy' ),
	wp_json_encode( chatl_rdzenie_pytania( 'firme' ) ) . ' vs ' . wp_json_encode( chatl_rdzenie_pytania( 'firmy' ) )
);
rowne( 'pytanie z samych słówek nie ma rdzeni', array(), chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'a co z tym' ) ) );

echo "\n=== A3. Podobieństwo (to ta liczba decyduje o scaleniu) ===\n";
$a = chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'ile kosztuje dostawa do Krakowa' ) );
$b = chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'koszt dostawy' ) );
$c = chatl_rdzenie_pytania( chatl_normalizuj_pytanie( 'czy pracujecie w soboty' ) );
rowne( 'odmiany tego samego pytania: 67% (>= próg)', 67, chatl_podobienstwo_pytan( $a, $b ) );
rowne( 'inny temat: 0% (< próg)', 0, chatl_podobienstwo_pytan( $a, $c ) );
rowne( 'pusty zbiór rdzeni to NIE jest „to samo pytanie"', 0, chatl_podobienstwo_pytan( array(), array() ) );
sprawdz( 'próg scalania wynosi ' . CHATL_LUKI_PROG . '%', 50 === CHATL_LUKI_PROG );

echo "\n=== A4. Grupowanie pytań bez odpowiedzi ===\n";
$wejscie = array(
	array(
		'tresc'     => 'Ile kosztuje dostawa do Krakowa?',
		'utworzono' => '2026-08-19 10:00:00',
	),
	array(
		'tresc'     => 'koszt dostawy',
		'utworzono' => '2026-08-19 11:00:00',
	),
	array(
		'tresc'     => 'Jaki jest koszt dostawy?',
		'utworzono' => '2026-08-20 09:00:00',
	),
	array(
		'tresc'     => 'Czy pracujecie w soboty?',
		'utworzono' => '2026-08-18 08:00:00',
	),
	array(
		'tresc'     => 'czy pracujecie w soboty',
		'utworzono' => '2026-08-20 12:00:00',
	),
);
$grupy = chatl_grupuj_pytania( $wejscie );
rowne( '5 pytań schodzi do 2 tematów', 2, count( $grupy ) );
rowne( 'temat najliczniejszy ma 3 pytania', 3, $grupy[0]['ile'] );
rowne( 'drugi temat ma 2 pytania', 2, $grupy[1]['ile'] );
sprawdz(
	'reprezentant pierwszego tematu jest o dostawie',
	false !== mb_stripos( $grupy[0]['pytanie'], 'dostaw' ),
	'reprezentant: „' . $grupy[0]['pytanie'] . '"'
);
rowne( 'pierwszy temat pamięta 3 sformułowania', 3, count( $grupy[0]['warianty'] ) );
rowne( 'ostatnie wystąpienie tematu 1', '2026-08-20 09:00:00', $grupy[0]['ostatnie'] );
sprawdz(
	'sformułowania o sobotach NIE wpadły do dostawy',
	false === mb_stripos( wp_json_encode( $grupy[0]['warianty'] ), 'sobot' )
);
// Pisownia gościa zostaje NIETKNIĘTA: normalizacja służy tylko do sklejania
// tematów, a właściciel ma zobaczyć, jak pytanie naprawdę padło. Dlatego
// „Czy pracujecie w soboty?" i „czy pracujecie w soboty" to jeden temat
// o liczniku 2, ale dwa osobne warianty na liście.
$suma_wariantow = 0;
foreach ( $grupy[1]['warianty'] as $w ) {
	$suma_wariantow += $w['ile'];
}
rowne( 'temat 2 zachowuje obie pisownie gościa', 2, count( $grupy[1]['warianty'] ) );
rowne( 'liczniki wariantów sumują się do licznika tematu', $grupy[1]['ile'], $suma_wariantow );

echo "\n=== A5. Podpowiedź słów kluczowych do nowej pary ===\n";
// Podpowiedź ma DŁUŻSZY rdzeń niż grupowanie: trafia do bazy Q&A i zaczyna
// dopasowywać odpowiedzi, więc „kosz" (łapałby „koszulę") byłoby za szerokie.
rowne( 'rdzenie, bo silnik dopasowuje podłańcuchy', 'koszt, dosta, krako', chatl_sugerowane_slowa( 'Ile kosztuje dostawa do Krakowa?' ) );
sprawdz( 'podpowiedź jest dłuższa niż rdzeń grupowania', CHATL_SUGESTIA_RDZEN > CHATL_LUKI_RDZEN, CHATL_SUGESTIA_RDZEN . ' > ' . CHATL_LUKI_RDZEN );
rowne( 'pytanie bez znaczących słów daje pustą podpowiedź', '', chatl_sugerowane_slowa( 'a co z tym?' ) );

echo "\n=== A6. Walidacja okresu (dane z formularza są NIEZAUFANE) ===\n";
sprawdz( 'poprawna data przechodzi', true === chatl_data_ok( '2026-08-20' ) );
sprawdz( 'miesiąc 13 odpada', false === chatl_data_ok( '2026-13-01' ) );
sprawdz( '29 lutego w roku nieprzestępnym odpada', false === chatl_data_ok( '2026-02-29' ) );
sprawdz( 'napis odpada', false === chatl_data_ok( 'wczoraj' ) );
sprawdz( 'próba wstrzyknięcia odpada', false === chatl_data_ok( "2026-08-20' OR 1=1 --" ) );
rowne( 'procent z zerowego mianownika to 0.0, nie błąd', 0.0, chatl_procent( 5, 0 ) );
rowne( 'procent liczy się normalnie', 33.3, chatl_procent( 2, 6 ) );

echo "\n=== A7. Domyślny okres to ostatnie 30 dni (z dzisiaj włącznie) ===\n";
$okres = chatl_okres_z_zadania();
rowne( 'koniec okresu to dzisiaj', wp_date( 'Y-m-d' ), $okres['do'] );
rowne( 'początek okresu to dzisiaj minus 29 dni', wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), $okres['od'] );

/* ============ B. NA PRAWDZIWEJ BAZIE (różnicowo) ============ */

global $wpdb;
$t_roz = $wpdb->prefix . 'chatl_rozmowy';
$t_zgl = $wpdb->prefix . 'chatl_zgloszenia';

function chatl_test_sprzataj() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja LIKE 'test-stat-%'" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE sesja LIKE 'test-stat-%'" );
}
chatl_test_sprzataj();

echo "\n=== B0. Kolumna trafiono naprawdę istnieje w bazie ===\n";
$kolumny = $wpdb->get_col( "SHOW COLUMNS FROM {$t_roz}" );
sprawdz( 'tabela rozmów ma kolumnę trafiono', in_array( 'trafiono', $kolumny, true ), 'kolumny: ' . implode( ', ', $kolumny ) );
$indeksy = $wpdb->get_col( "SHOW INDEX FROM {$t_roz}", 2 );
sprawdz( 'jest indeks pod raport luk', in_array( 'luki', $indeksy, true ), 'indeksy: ' . implode( ', ', array_unique( $indeksy ) ) );

$dzis  = wp_date( 'Y-m-d' );
$przed = chatl_statystyki_okresu( $dzis, $dzis );
$luki0 = chatl_luki_okresu( $dzis, $dzis );

$teraz = current_time( 'mysql' );
$wiersze = array(
	array( 'test-stat-1', 'gosc', 'reguly', 'Ile kosztuje dostawa do Krakowa?', 0 ),
	array( 'test-stat-1', 'bot', 'reguly', 'Nie znam odpowiedzi.', 0 ),
	array( 'test-stat-1', 'gosc', 'reguly', 'Jakie macie godziny otwarcia?', 1 ),
	array( 'test-stat-1', 'bot', 'reguly', 'Pracujemy 8-16.', 1 ),
	array( 'test-stat-1', 'gosc', 'reguly', 'Gdzie jest wasze biuro?', 1 ),
	array( 'test-stat-1', 'bot', 'ai', 'Przykładowa 1, Warszawa.', 1 ),
	array( 'test-stat-1', 'bot', 'reguly', 'Wiersz sprzed 1.3.0 — bez znacznika.', null ),
	array( 'test-stat-2', 'gosc', 'reguly', 'koszt dostawy', 0 ),
	array( 'test-stat-2', 'bot', 'reguly', 'Nie znam odpowiedzi.', 0 ),
	array( 'test-stat-2', 'gosc', 'reguly', 'Jaki jest koszt dostawy?', 0 ),
	array( 'test-stat-2', 'bot', 'reguly', 'Nie znam odpowiedzi.', 0 ),
	array( 'test-stat-2', 'gosc', 'reguly', 'Czy pracujecie w soboty?', 0 ),
	array( 'test-stat-2', 'bot', 'reguly', 'Nie znam odpowiedzi.', 0 ),
);
// ⛔ 41 wierszy bez znacznika, nie 1. Mutant „nieoznaczone wpadają do mianownika skuteczności”
// zmienia zwracany procent o odpowiedziano×nieozn/(d·(d+nieozn)); przy JEDNYM wierszu i bazie stosu
// z setkami dzisiejszych wierszy (po dniu testów) różnica znikała w zaokrągleniu do 0,1 % i próba
// kontrolna meldowała „TEST ŚLEPY” (5.09 21:32 — o 20:28 na mniejszej bazie przechodziła).
// 41 wierszy trzyma mutant widocznym do ok. 40 000 wierszy dziennie. Klasa: kontrola w SKALI.
for ( $i = 0; $i < 40; $i++ ) {
	$wiersze[] = array( 'test-stat-1', 'bot', 'reguly', 'Wiersz sprzed 1.3.0 — bez znacznika (' . $i . ').', null );
}
foreach ( $wiersze as $w ) {
	$dane   = array(
		'sesja'     => $w[0],
		'rola'      => $w[1],
		'silnik'    => $w[2],
		'tresc'     => $w[3],
		'utworzono' => $teraz,
	);
	$format = array( '%s', '%s', '%s', '%s', '%s' );
	if ( null !== $w[4] ) {
		$dane['trafiono'] = $w[4];
		$format[]         = '%d';
	}
	$wpdb->insert( $t_roz, $dane, $format );
}
$wpdb->insert(
	$t_zgl,
	array(
		'email'     => 'test@seed.local',
		'wiadomosc' => 'Zostawiam kontakt.',
		'sesja'     => 'test-stat-2',
		'status'    => 'nowe',
		'utworzono' => $teraz,
	),
	array( '%s', '%s', '%s', '%s', '%s' )
);

$po    = chatl_statystyki_okresu( $dzis, $dzis );
$luki1 = chatl_luki_okresu( $dzis, $dzis );

echo "\n=== B1. Liczby zbiorcze (różnica po dołożeniu 6 pytań) ===\n";
rowne( 'pytań gości +6', 6, $po['pytania'] - $przed['pytania'] );
rowne( 'odpowiedzi bota +2', 2, $po['odpowiedziano'] - $przed['odpowiedziano'] );
rowne( 'bez odpowiedzi +4', 4, $po['bez_odpowiedzi'] - $przed['bez_odpowiedzi'] );
rowne( 'wierszy bez znacznika (sprzed 1.3.0) +41', 41, $po['nieoznaczone'] - $przed['nieoznaczone'] );
rowne( 'odpowiedzi z reguł +1', 1, $po['z_regul'] - $przed['z_regul'] );
rowne( 'odpowiedzi z AI +1', 1, $po['z_ai'] - $przed['z_ai'] );
rowne( 'rozmów +2', 2, $po['rozmowy'] - $przed['rozmowy'] );
rowne( 'zgłoszeń +1', 1, $po['zgloszenia'] - $przed['zgloszenia'] );
rowne( 'rozmów zakończonych kontaktem +1', 1, $po['rozmowy_z_kontaktem'] - $przed['rozmowy_z_kontaktem'] );

echo "\n=== B2. Skuteczność liczy się BEZ wierszy nieoznaczonych ===\n";
// 2 trafione / (2 trafione + 4 nietrafione) = 33,3%. Gdyby wiersz sprzed
// aktualizacji wpadł do mianownika, wyszłoby 28,6% — czyli panel karałby
// właściciela za dane, których nikt nie zmierzył.
$tylko_nasze = chatl_procent( $po['odpowiedziano'] - $przed['odpowiedziano'], ( $po['odpowiedziano'] - $przed['odpowiedziano'] ) + ( $po['bez_odpowiedzi'] - $przed['bez_odpowiedzi'] ) );
rowne( 'skuteczność samej porcji testowej = 33.3%', 33.3, $tylko_nasze );
sprawdz(
	'wiersz NULL nie wpadł ani do trafionych, ani do nietrafionych',
	41 === ( $po['nieoznaczone'] - $przed['nieoznaczone'] )
	&& 6 === ( ( $po['odpowiedziano'] - $przed['odpowiedziano'] ) + ( $po['bez_odpowiedzi'] - $przed['bez_odpowiedzi'] ) )
);
/*
 * Powyższe kroki liczą różnice WŁASNYM wzorem, więc sprawdzają dane, nie kod
 * — próba kontrolna wykazała, że przy takim zestawie można podmienić wzór
 * skuteczności w chatl_statystyki_okresu() i test tego nie zauważy. Dlatego
 * tutaj sprawdzamy WPROST liczbę, którą zwraca funkcja: ma być trafione /
 * (trafione + nietrafione), bez wierszy nieoznaczonych w mianowniku.
 */
sprawdz( 'w okresie SĄ wiersze bez znacznika (inaczej ten krok nic nie sprawdza)', $po['nieoznaczone'] > 0, 'nieoznaczonych: ' . $po['nieoznaczone'] );
rowne(
	'zwrócona skuteczność = trafione / (trafione + nietrafione)',
	chatl_procent( $po['odpowiedziano'], $po['odpowiedziano'] + $po['bez_odpowiedzi'] ),
	$po['skutecznosc']
);
rowne(
	'zwrócona konwersja = rozmowy z kontaktem / wszystkie rozmowy',
	chatl_procent( $po['rozmowy_z_kontaktem'], $po['rozmowy'] ),
	$po['konwersja']
);

echo "\n=== B3. Raport luk czyta PYTANIA gościa, nie odpowiedzi bota ===\n";
rowne( 'pytań bez odpowiedzi +4', 4, $luki1['wszystkich'] - $luki0['wszystkich'] );
$nasze = array();
foreach ( $luki1['grupy'] as $g ) {
	foreach ( $g['warianty'] as $w ) {
		if ( false !== mb_stripos( $w['tekst'], 'dostaw' ) || false !== mb_stripos( $w['tekst'], 'sobot' ) ) {
			$nasze[ $g['pytanie'] ] = $g['ile'];
		}
	}
}
sprawdz(
	'nasze 4 pytania trafiły do 2 tematów raportu',
	2 === count( $nasze ),
	'tematy: ' . wp_json_encode( $nasze, JSON_UNESCAPED_UNICODE )
);
sprawdz(
	'żaden temat nie jest treścią ODPOWIEDZI bota',
	false === mb_stripos( wp_json_encode( array_keys( $nasze ) ), 'Nie znam odpowiedzi' )
);

echo "\n=== B4. Statystyka dzienna widzi dzisiejszy ruch ===\n";
$dni     = chatl_statystyki_dzienne( $dzis, $dzis );
$dzis_ma = 0;
foreach ( $dni as $d ) {
	if ( (string) $d->dzien === $dzis ) {
		$dzis_ma = (int) $d->pytania;
	}
}
sprawdz( 'dzisiejszy dzień ma co najmniej 6 pytań', $dzis_ma >= 6, 'pytań dzisiaj: ' . $dzis_ma );

echo "\n=== B5. Okres poza zakresem danych daje zera, nie błąd ===\n";
$pusty = chatl_statystyki_okresu( '2000-01-01', '2000-01-02' );
rowne( 'pytania = 0', 0, $pusty['pytania'] );
rowne( 'skuteczność = 0.0 (bez dzielenia przez zero)', 0.0, $pusty['skutecznosc'] );

chatl_test_sprzataj();
$zostalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_roz} WHERE sesja LIKE 'test-stat-%'" );
echo "\n=== B6. Sprzątanie ===\n";
rowne( 'test nie zostawia po sobie wierszy', 0, $zostalo );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
