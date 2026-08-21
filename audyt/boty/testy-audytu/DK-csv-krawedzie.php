<?php
/**
 * STRAŻNIK DK-4: co widzi CZŁOWIEK, gdy poda importowi CSV plik z krawędzi.
 * Parser (chatl_import_parsuj) jest czysty wobec wejścia, więc sprawdzamy go
 * treścią z pamięci — bez podszywania się pod upload i bez zapisu do bazy.
 * EXIT 0 = każdy przypadek kończy się albo poprawnym wczytaniem, albo
 * komunikatem, z którego wiadomo, co zrobić.
 */
foreach ( array( 'statystyki', 'csv', 'import' ) as $dk_plik ) {
	require_once WP_PLUGIN_DIR . '/chatbot-live/includes/' . $dk_plik . '.php';
}

$GLOBALS['dk_bledy'] = 0;
function dk( $nazwa, $tresc, $oczekiwanie ) {
	$w = chatl_import_parsuj( $tresc );
	$ile   = count( $w['wiersze'] );
	$blad  = (string) $w['blad'];
	$pyt   = $ile ? (string) $w['wiersze'][0]['pytanie'] : '';
	$dobrze = call_user_func( $oczekiwanie, $w );
	if ( ! $dobrze ) { ++$GLOBALS['dk_bledy']; }
	printf(
		"%s %-38s wierszy:%-4d | %s\n",
		$dobrze ? 'OK  ' : 'PADA',
		$nazwa,
		$ile,
		'' !== $blad ? 'komunikat: ' . mb_substr( $blad, 0, 80 ) : 'pierwsze pytanie: ' . mb_substr( $pyt, 0, 60 )
	);
	return $w;
}

$naglowek = "pytanie,slowa_kluczowe,odpowiedz\n";
$wiersz   = "Czy jesteście otwarci w sobotę?,sobota,\"Tak, w soboty pracujemy 9–13.\"\n";

echo "iconv dostępny: " . ( function_exists( 'iconv' ) ? 'tak' : 'NIE (konwersja CP1250 nie zadziała)' ) . "\n\n";

// 1. KONTROLA — poprawny UTF-8 (przypadek BEZ wady)
dk( 'UTF-8, z nagłówkiem (kontrola)', $naglowek . $wiersz, function ( $w ) {
	return '' === $w['blad'] && 1 === count( $w['wiersze'] ) && false !== mb_strpos( $w['wiersze'][0]['pytanie'], 'sobotę' );
} );

// 2. WINDOWS-1250 — domyślne kodowanie polskiego Excela
$cp = iconv( 'UTF-8', 'CP1250', "pytanie,slowa_kluczowe,odpowiedz\nCzy jesteście otwarci w sobotę?,sobota,\"Tak, pracujemy 9–13 (półtorej godziny przerwy).\"\n" );
dk( 'Windows-1250 (polski Excel)', (string) $cp, function ( $w ) {
	return '' === $w['blad'] && 1 === count( $w['wiersze'] )
		&& false !== mb_strpos( $w['wiersze'][0]['pytanie'], 'jesteście' )
		&& false === mb_strpos( $w['wiersze'][0]['pytanie'], '?' . chr( 0xEF ) );
} );

// 2b. WINDOWS-1250 ze ŚREDNIKIEM (tak zapisuje Excel przy polskich ustawieniach)
$cp2 = iconv( 'UTF-8', 'CP1250', "pytanie;slowa_kluczowe;odpowiedz\nGdzie jesteście?;adres;Ulica Zażółć 5, Łódź\n" );
dk( 'Windows-1250 + separator średnik', (string) $cp2, function ( $w ) {
	return '' === $w['blad'] && 1 === count( $w['wiersze'] ) && false !== mb_strpos( $w['wiersze'][0]['odpowiedz'], 'Łódź' );
} );

// 3. BEZ NAGŁÓWKA — same dane
dk( 'bez nagłówka (same dane)', $wiersz, function ( $w ) {
	// Ma odmówić i POWIEDZIEĆ, czego brakuje — nie wciągnąć pierwszego wiersza jako nagłówka po cichu.
	return '' !== $w['blad'] && false !== mb_strpos( $w['blad'], 'pytanie' ) && 0 === count( $w['wiersze'] );
} );

// 4. PUSTY PLIK
dk( 'plik pusty (0 bajtów)', '', function ( $w ) {
	return '' !== $w['blad'] && 0 === count( $w['wiersze'] );
} );

// 4b. SAM NAGŁÓWEK, zero danych
dk( 'sam nagłówek, zero wierszy', $naglowek, function ( $w ) {
	return 0 === count( $w['wiersze'] );
} );

// 5. BARDZO DUŻY — ponad próg bajtów
$duzy = $naglowek . str_repeat( "Pytanie numer X," . str_repeat( 'a', 900 ) . ",Odpowiedz\n", 2400 );
dk( 'bardzo duży (> ' . (int) ( CHATL_IMPORT_MAX_BAJTOW / 1048576 ) . ' MB)', $duzy, function ( $w ) {
	return '' !== $w['blad'] && false !== mb_strpos( $w['blad'], 'większy' ) && 0 === count( $w['wiersze'] );
} );

// 5b. DUŻO WIERSZY, ale pod progiem bajtów
$duzo = $naglowek;
for ( $i = 0; $i < 2100; $i++ ) { $duzo .= "Pytanie numer {$i},slowo{$i},Odpowiedz {$i}\n"; }
dk( 'ponad ' . CHATL_IMPORT_MAX_WIERSZY . ' wierszy', $duzo, function ( $w ) {
	return '' !== $w['blad'] && false !== mb_strpos( $w['blad'], 'wierszy' ) && 0 === count( $w['wiersze'] );
} );

// 6. BOM UTF-8 (tak wraca NASZ WŁASNY eksport)
dk( 'UTF-8 z BOM (własny eksport)', "\xEF\xBB\xBF" . $naglowek . $wiersz, function ( $w ) {
	return '' === $w['blad'] && 1 === count( $w['wiersze'] );
} );

// 7. plik binarny podany zamiast CSV
dk( 'plik binarny (nie CSV)', "\x89PNG\r\n\x1a\n" . random_bytes( 200 ), function ( $w ) {
	return '' !== $w['blad']; // ma odmówić z komunikatem, nie wciągnąć śmieci
} );

echo "\n(parser jest czysty wobec wejścia — ten test niczego nie zapisał do bazy)\n";
echo $GLOBALS['dk_bledy'] ? "\nWYNIK: PADA — {$GLOBALS['dk_bledy']} przypadek/przypadki bez sensownego wyjścia.\n" : "\nWYNIK: OK — każda krawędź kończy się albo wczytaniem, albo zrozumiałym komunikatem.\n";
exit( $GLOBALS['dk_bledy'] ? 1 : 0 );
