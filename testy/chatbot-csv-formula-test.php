<?php
/**
 * ChatBot LIVE — obrona przed formułą w arkuszu przy eksporcie CSV i jej ZDJĘCIE przy imporcie.
 *
 * Dlaczego ten test istnieje: obrona żyła w trzech wtyczkach w trzech różnych zestawach znaków
 * i kopia ChatBota była najsłabsza (= + - @, bez tabulatora i CR), a obsługiwała dane najmniej
 * zaufane — treść wpisaną w oknie czatu przez anonimowego gościa, którą właściciel eksportuje
 * do arkusza. Strażnik bliźniaków (`testy/csv-neutralizacja-blizniaki-test.sh`) pilnuje, że
 * ZESTAW znaków jest w kodzie; ten test mierzy ZACHOWANIE prawdziwych funkcji: eksport dokłada
 * apostrof, import go zdejmuje, runda eksport→import jest odwracalna dla każdego znaku.
 *
 * ⛔ Bez WordPressa i bez dockera: `chatl_csv_komorka()` i `chatl_import_odapostrofuj()` ładują
 *    się z PLIKU, a `add_action()` z poziomu pliku dostaje atrapę (nic nie rejestruje).
 *    Zmienna `CHATL_KATALOG_WTYCZKI` wskazuje katalog `includes/` — używa jej próba kontrolna,
 *    żeby podłożyć mutanta w KOPII, nie w repo.
 *
 * Uruchomienie:  php testy/chatbot-csv-formula-test.php
 * Kod wyjścia 0 = wszystkie asercje przeszły, 1 = któraś nie, 2 = nie znalazłam plików wtyczki.
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
$katalog = getenv( 'CHATL_KATALOG_WTYCZKI' );
if ( ! is_string( $katalog ) || '' === $katalog ) {
	$katalog = dirname( __DIR__ ) . '/wtyczki/chatbot-live/includes';
}
$katalog = rtrim( $katalog, '/' ) . '/';
if ( ! is_file( $katalog . 'csv.php' ) || ! is_file( $katalog . 'import.php' ) ) {
	fwrite( STDERR, "BLAD nie znalazlem csv.php/import.php w: $katalog\n" );
	exit( 2 );
}
require $katalog . 'csv.php';
require $katalog . 'import.php';

$bledy = 0;
function krok( $ok, $opis, $szczegol = '' ) {
	global $bledy;
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " [$szczegol]" : '' );
	if ( ! $ok ) {
		++$bledy;
	}
}
function widoczny( $s ) {
	return str_replace( array( "\t", "\r" ), array( '\\t', '\\r' ), $s );
}

echo "=== CHATBOT / CSV: komorka zaczynajaca sie od znaku formuly dostaje apostrof, import go zdejmuje ===\n\n";

// Każdy znak, który arkusz kalkulacyjny potrafi uznać za początek formuły — ten sam zestaw, co w sklepie i segregatorze.
$niebezpieczne = array( '=', '+', '-', '@', "\t", "\r" );
foreach ( $niebezpieczne as $z ) {
	$surowe = $z . 'SUM(A1:A9)';
	$eksport = chatl_csv_komorka( $surowe );
	krok( "'" . $surowe === $eksport, 'eksport: komorka od "' . widoczny( $z ) . '" dostaje apostrof', widoczny( $eksport ) );
	$import = chatl_import_odapostrofuj( $eksport );
	krok( $import === $surowe, 'import: apostrof zdjety, runda eksport->import odwracalna dla "' . widoczny( $z ) . '"', widoczny( $import ) );
}

// Kontrola dodatnia w drugą stronę: zwykły tekst i liczba NIE dostają apostrofu i import ich nie rusza.
foreach ( array( 'Dzien dobry', '149,00', 'abc=def' ) as $zwykly ) {
	krok( chatl_csv_komorka( $zwykly ) === $zwykly, 'eksport: zwykla komorka bez apostrofu', widoczny( $zwykly ) );
	krok( chatl_import_odapostrofuj( $zwykly ) === $zwykly, 'import: zwykla komorka nietknieta', widoczny( $zwykly ) );
}
// Apostrof, który wpisał CZŁOWIEK (nie nasz), zostaje: "'abc" nie zaczyna się od znaku formuły po apostrofie.
krok( chatl_import_odapostrofuj( "'abc" ) === "'abc", 'import: cudzy apostrof przed zwyklym tekstem zostaje' );
krok( chatl_csv_komorka( '' ) === '' && chatl_import_odapostrofuj( '' ) === '', 'pusta komorka w obie strony pusta' );

echo "\n";
if ( 0 === $bledy ) {
	echo "WSZYSTKIE ASERCJE PRZESZLY — eksport chroni arkusz, import zdejmuje ochrone symetrycznie\n";
	exit( 0 );
}
printf( "NIE PRZESZLO ASERCJI: %d\n", $bledy );
exit( 1 );
