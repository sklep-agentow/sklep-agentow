<?php
/**
 * A6 (odwrotny): czy DEKLARACJA PRYWATNOSCI wylicza wszystko, co NAPRAWDE
 * opuszcza WordPressa w trybie AI ChatBota.
 *
 * Kierunek: od KODU do DOKUMENTU. Bierzemy prawdziwy ladunek wychodzacy
 * (przechwycony filtrem pre_http_request — nic nie leci do API, nie zuzywa
 * klucza ani limitu) i dla kazdego jego skladnika pytamy, czy deklaracja
 * prywatnosci go wymienia.
 *
 * Deklaracja (INSTRUKCJA.md §9) wylicza TRZY rzeczy: tresc pytania goscia,
 * opis firmy, baze Q&A. Kod wysyla CZWARTA: do CHATL_AI_PAMIEC_WYMIAN
 * wczesniejszych wymian tej rozmowy — czyli tekst, ktory gosc napisal
 * WCZESNIEJ, razem z tym, co w nim podal (imie, nazwisko, telefon).
 * Wlasciciel pisze wlasna polityke prywatnosci z tej sekcji, wiec niepelne
 * wyliczenie to sprawa RODO, nie literowka.
 *
 * Sciezka do instrukcji da sie podmienic zmienna srodowiskowa A6_INSTRUKCJA
 * — po to, zeby dalo sie zmierzyc kalibracje na poprawionym dokumencie.
 *
 * Uruchomienie:
 *   docker cp testy/a6-deklaracja-vs-ladunek-ai.php strona_wp_wordpress:/tmp/a6t.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/a6t.php
 * EXIT 0 = deklaracja pelna, 1 = czegos nie wymienia.
 */
$GLOBALS['a6_bledy'] = 0; // ⛔ NIE '$b = 0' — wp eval-file biegnie w zasiegu FUNKCJI.
function a6ok( $o, $s = '' ) { printf( "  OK   %s%s\n", $o, '' !== $s ? "  [$s]" : '' ); }
function a6bl( $o, $s = '' ) { $GLOBALS['a6_bledy']++; printf( "  BLAD %s%s\n", $o, '' !== $s ? "  [$s]" : '' ); }

$instr = getenv( 'A6_INSTRUKCJA' ) ?: WP_PLUGIN_DIR . '/chatbot-live/INSTRUKCJA.md';
if ( ! is_readable( $instr ) ) { echo "  BLAD srodowisko: brak $instr\n"; exit( 1 ); }
$tekst = file_get_contents( $instr );
$p = strpos( $tekst, 'Deklaracja prywatności' );
if ( false === $p ) { echo "  BLAD srodowisko: brak sekcji 'Deklaracja prywatności'\n"; exit( 1 ); }
$dekl = substr( $tekst, $p, 1800 );
printf( "=== A6: deklaracja prywatnosci vs prawdziwy ladunek AI ===\n  dokument: %s\n\n", $instr );

/* --- zbuduj PRAWDZIWY ladunek --- */
global $wpdb;
$sesja = 'a6' . wp_generate_password( 18, false, false );
$tr = $wpdb->prefix . 'chatl_rozmowy'; $ts = $wpdb->prefix . 'chatl_sesje';
$wpdb->insert( $ts, array('sesja'=>$sesja,'stan'=>'bot','klucz_hash'=>wp_hash('x'),'utworzono'=>current_time('mysql'),'aktywnosc'=>current_time('mysql')), array('%s','%s','%s','%s','%s') );
$znacznik = 'Jan Kowalski, tel 555-111-222';
$wpdb->insert( $tr, array('sesja'=>$sesja,'rola'=>'gosc','tresc'=>"Nazywam sie $znacznik",'utworzono'=>current_time('mysql')) );
$wpdb->insert( $tr, array('sesja'=>$sesja,'rola'=>'bot','tresc'=>'Prosze zostawic maila.','utworzono'=>current_time('mysql')) );
$wpdb->insert( $tr, array('sesja'=>$sesja,'rola'=>'gosc','tresc'=>'Jakie sa godziny otwarcia?','utworzono'=>current_time('mysql')) );
$id_biez = (int) $wpdb->insert_id;

$GLOBALS['a6_body'] = null;
add_filter( 'pre_http_request', function ( $k, $args, $url ) {
	if ( false !== strpos( $url, 'api.anthropic.com' ) ) { $GLOBALS['a6_body'] = $args['body']; return new WP_Error( 'a6', 'przechwycone przez audyt' ); }
	return $k;
}, 10, 3 );
chatl_ai_odpowiedz( 'Jakie sa godziny otwarcia?', chatl_ai_historia( $sesja, $id_biez ) );
$body = $GLOBALS['a6_body'];
$wpdb->delete( $tr, array( 'sesja' => $sesja ) );
$wpdb->delete( $ts, array( 'sesja' => $sesja ) );

if ( null === $body ) { echo "  ⚠️ ANOMALIA: nie przechwycono zadania — tryb AI nie doszedl do wywolania. NIE DA SIE ORZEC.\n"; exit( 1 ); }
$d = json_decode( $body, true );

/* --- 1. KONTROLA MECHANIZMU: skladniki, ktore deklaracja WYMIENIA, musza byc w ladunku --- */
$ma_pytanie = false;
foreach ( (array) $d['messages'] as $m ) { if ( 'user' === $m['role'] && false !== strpos( (string) $m['content'], 'godziny otwarcia' ) ) { $ma_pytanie = true; } }
if ( $ma_pytanie && ! empty( $d['system'] ) ) {
	a6ok( '0. kontrola: w ladunku SA skladniki, ktore deklaracja wymienia', 'pytanie goscia + blok systemowy (opis firmy, baza Q&A)' );
} else {
	a6bl( '0. kontrola: ladunek nie zawiera nawet tego, co deklaracja wymienia — pomiar podejrzany' );
}

/* --- 2. WADA: czy deklaracja wymienia WCZESNIEJSZE wymiany rozmowy --- */
$historia_w_ladunku = false;
foreach ( (array) $d['messages'] as $m ) { if ( false !== strpos( (string) $m['content'], $znacznik ) ) { $historia_w_ladunku = true; } }
$dekl_mowi_o_historii = (bool) preg_match( '/wcześniejsz|poprzedni|historia rozmowy|dotychczasow|przebieg rozmowy|ostatnie wymian|pamięć rozmowy|bieżąc\w+ rozmow/iu', $dekl );
if ( $historia_w_ladunku && ! $dekl_mowi_o_historii ) {
	a6bl( '1. deklaracja NIE wymienia wczesniejszych wiadomosci goscia, a one wychodza do API',
		'w ladunku jest: "' . $znacznik . '"' );
} elseif ( ! $historia_w_ladunku ) {
	a6ok( '1. wczesniejsze wiadomosci goscia NIE opuszczaja WordPressa' );
} else {
	a6ok( '1. deklaracja wymienia wczesniejsze wiadomosci rozmowy' );
}

/* --- 3. BEZ WADY: deklaracja nie moze OBIECYWAC czegos, czego kod nie robi --- */
if ( preg_match( '/załączni|plik.{0,12}goscia|adres IP goscia.{0,20}(wysyłan|API)/iu', $dekl ) ) {
	a6bl( '2. deklaracja wymienia skladnik, ktorego kod NIE wysyla — dokument obiecuje w druga strone' );
} else {
	a6ok( '2. deklaracja nie wymienia niczego ponad to, co kod wysyla' );
}

echo "\n";
if ( 0 === $GLOBALS['a6_bledy'] ) { echo "WSZYSTKIE KROKI PRZESZLY — deklaracja prywatnosci pokrywa caly ladunek AI\n"; exit( 0 ); }
printf( "PRZYPADKOW NIEZALICZONYCH: %d\n", $GLOBALS['a6_bledy'] ); exit( 1 );
