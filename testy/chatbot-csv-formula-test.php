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
 * ⛔ NIE przez narzedzia-qa/test-php.sh: to wgrywa plik do /tmp kontenera i mierzyłoby kod KONTENERA, nie
 *    kod z drzewa roboczego (klasa 141 ze ściągi; ta sama notka w testy/segregator-mime-test.php). Test jest
 *    czystym PHP CLI: liczy ścieżkę wtyczki od własnego położenia, a licznik trzyma w $GLOBALS, bo pod
 *    `wp eval-file` plik jest włączany wewnątrz funkcji i zwykłe `global` wskazywałoby inną zmienną.
 * Kod wyjścia 0 = wszystkie asercje przeszły, 1 = któraś nie, 2 = nie znalazłam plików wtyczki.
 */
// Pod `wp eval-file` (narzedzia-qa/test-php.sh) WordPress i wtyczka juz sa zaladowane — wtedy
// mierzymy funkcje zywej wtyczki i NIE ladujemy plikow drugi raz (staly i funkcji nie da sie
// zadeklarowac ponownie). Z wiersza polecen (`php testy/...`, proba kontrolna z mutantem w KOPII)
// ladujemy pliki sami.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}
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
if ( ! function_exists( 'chatl_csv_komorka' ) ) {
	require_once $katalog . 'csv.php';
}
if ( ! function_exists( 'chatl_import_odapostrofuj' ) ) {
	require_once $katalog . 'import.php';
}

$GLOBALS['bledy'] = 0;
function krok( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " [$szczegol]" : '' );
	if ( ! $ok ) {
		++$GLOBALS['bledy'];
	}
}
function widoczny( $s ) {
	return str_replace( array( "\t", "\r" ), array( '\\t', '\\r' ), $s );
}

echo "=== CHATBOT / CSV: komorka zaczynajaca sie od znaku formuly dostaje apostrof, import go zdejmuje ===\n\n";

// Każdy znak, który arkusz potrafi uznać za początek formuły, PLUS apostrof (dla odwracalności) — ten sam
// zestaw siedmiu znaków, co SKLEPAG_CSV_ZNAKI_FORMULY w sklepie. Lista wpisana tu NA SZTYWNO celowo:
// gdyby test czytał stałą z kodu, mutant usuwający znak ze stałej przeszedłby niezauważony.
$niebezpieczne = array( '=', '+', '-', '@', "\t", "\r", "'" );
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
// Komórka od apostrofu + znak formuły (recenzja #298 W4): eksport MUSI dołożyć drugi apostrof, inaczej
// import zdjąłby znak, którego eksport nie dołożył, i zamienił martwy literał "'=SUM(A1)" w żywą formułę.
foreach ( array( "'=SUM(A1)", "'+1", "'@x", "'-2", "''podwojny", "'abc" ) as $od_apostrofu ) {
	$eksport = chatl_csv_komorka( $od_apostrofu );
	krok( "'" . $od_apostrofu === $eksport, 'eksport: komorka od apostrofu dostaje DRUGI apostrof', $eksport );
	krok( chatl_import_odapostrofuj( $eksport ) === $od_apostrofu, 'import: runda odwracalna dla komorki od apostrofu', $od_apostrofu );
}
// Surowy plik z cudzej ręki: "'=SUM(A1)" bez naszego drugiego apostrofu — import zdejmuje jeden znak, jak w sklepie.
// To ŚWIADOME: taka komórka nie pochodzi z naszego eksportu, a po zdjęciu trafia do bazy jako tekst, nie do arkusza.
krok( chatl_import_odapostrofuj( "'=SUM(A1)" ) === '=SUM(A1)', 'import: cudzy pojedynczy apostrof przed formula zdjety (jak sklep)' );
krok( chatl_csv_komorka( '' ) === '' && chatl_import_odapostrofuj( '' ) === '', 'pusta komorka w obie strony pusta' );

echo "\n";
if ( 0 === $GLOBALS['bledy'] ) {
	echo "WSZYSTKIE ASERCJE PRZESZLY — eksport chroni arkusz, import zdejmuje ochrone symetrycznie\n";
	exit( 0 );
}
printf( "NIE PRZESZLO ASERCJI: %d\n", $GLOBALS['bledy'] );
exit( 1 );
