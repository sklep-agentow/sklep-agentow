<?php
/**
 * F4-F2 — szablon Polityki prywatności i opis dziennika MUSZĄ mówić prawdę o sprzątaniu:
 * warunkowe (WP-Cron), co 10 minut, po 90 dniach, tylko dziennik zdarzeń i historia logowań,
 * dane zamówień NIE są usuwane po czasie. Dotąd szablon obiecywał „90 dni” bez warunku
 * (mechanizm jest warunkowy — gdy WP-Cron stoi, nic nie znika), a opis dziennika „usuwane automatycznie”.
 *
 * Strażnik czyta ŹRÓDŁO PHP wprost (literały w kodzie), więc próba kontrolna nie wymaga stosu.
 * Uczciwie nazwane: mierzy OBECNOŚĆ zdań w szablonie, nie działanie sprzątania (to mierzy cron).
 *
 * Uruchomienie:  php testy/polityka-sprzatanie-warunkowe-test.php
 *   SKLEPAG_KATALOG_INCLUDES=<katalog> — próba kontrolna podkłada KOPIĘ z wadą (drzewo nietknięte).
 * Kody: 0 = wszystkie zdania obecne i liczba dni zgodna ze stałą · 1 = brakuje · 2 = przyrząd.
 */
$katalog = getenv( 'SKLEPAG_KATALOG_INCLUDES' ) ?: __DIR__ . '/../wtyczki/sklep-agentow/includes';
$glowny  = __DIR__ . '/../wtyczki/sklep-agentow/sklep-agentow.php';
foreach ( array( "$katalog/aktywacja.php", "$katalog/admin-dziennik.php", $glowny ) as $p ) {
	if ( ! is_file( $p ) ) { fwrite( STDERR, "PRZYRZAD: brak pliku $p\n" ); exit( 2 ); }
}
$zrodlo = file_get_contents( "$katalog/aktywacja.php" );
if ( ! preg_match( "/'sklepag_strona_polityka'\s*=>\s*array\(\s*'[^']*',\s*\"((?:[^\"\\\\]|\\\\.)*)\"/s", $zrodlo, $m ) ) {
	fwrite( STDERR, "PRZYRZAD: nie znalazlam literalu szablonu polityki w aktywacja.php\n" ); exit( 2 );
}
$polityka = $m[1];
if ( ! preg_match( "/define\(\s*'SKLEPAG_RETENCJA_DNI',\s*(\d+)\s*\)/", file_get_contents( $glowny ), $d ) ) {
	fwrite( STDERR, "PRZYRZAD: brak stalej SKLEPAG_RETENCJA_DNI\n" ); exit( 2 );
}
$dni = (int) $d[1];
$dziennik = file_get_contents( "$katalog/admin-dziennik.php" );

$bledy = 0;
function spr( $nazwa, $ok ) {
	global $bledy;
	echo ( $ok ? '  OK   ' : '  ZLE  ' ) . $nazwa . "\n";
	if ( ! $ok ) { $bledy++; }
}
echo "=== A. Szablon polityki — sprzątanie opisane PRAWDZIWIE ===\n";
spr( 'zadanie WP-Cron nazwane jako mechanizm', false !== strpos( $polityka, 'WP-Cron' ) );
spr( 'sprzątanie nazwane WARUNKOWYM', false !== stripos( $polityka, 'WARUNKOWE' ) );
spr( 'warunek: „gdy harmonogram WordPressa działa”', false !== strpos( $polityka, 'gdy harmonogram WordPressa działa' ) );
spr( 'skutek stanu bez crona: wpisy pozostają', false !== strpos( $polityka, 'wpisy pozostają' ) );
spr( "liczba dni w szablonie == SKLEPAG_RETENCJA_DNI ($dni)", false !== strpos( $polityka, "starsze niż $dni dni" ) );
spr( 'zakres: dziennik zdarzeń I historia logowań', false !== strpos( $polityka, 'dziennik zdarzeń i historia logowań' ) );
spr( 'zamówienia NIE są usuwane po czasie (obowiązek podatkowy)', false !== strpos( $polityka, 'NIE są usuwane po czasie' ) && false !== strpos( $polityka, 'obowiązek podatkowy' ) );
spr( 'stara bezwarunkowa obietnica „(90 dni, w tym adres IP” zniknęła', false === strpos( $polityka, "($dni dni, w tym adres IP" ) );

echo "=== B. Opis dziennika w panelu — ta sama prawda ===\n";
spr( 'opis dziennika nazywa WP-Cron', false !== strpos( $dziennik, 'WP-Cron' ) );
spr( 'opis dziennika ma warunek harmonogramu', false !== strpos( $dziennik, 'gdy harmonogram WordPressa działa' ) );
spr( 'opis dziennika nie mówi już „usuwane automatycznie”', false === strpos( $dziennik, 'usuwane automatycznie' ) );

echo "=== C. Kontrola dodatnia przyrządu (musi być prawdą zawsze) ===\n";
spr( 'szablon polityki ma administratora danych (literał odczytany naprawdę)', false !== strpos( $polityka, 'Administrator danych' ) );

echo "\n";
if ( $bledy > 0 ) { echo "POLITYKA-SPRZATANIE: $bledy NIE WYSZLO — WYNIK=FAIL (EXIT=1)\n"; exit( 1 ); }
echo "POLITYKA-SPRZATANIE: szablon i opis dziennika mówią prawdę o sprzątaniu (warunkowe, WP-Cron, $dni dni, tylko dziennik+logowania) — WYNIK=PASS (EXIT=0)\n";
exit( 0 );
