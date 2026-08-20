<?php
/**
 * B — tryb AI nie pamiętał BIEŻĄCEJ rozmowy. Test-strażnik, kod wyjścia 1.
 *
 * Zmierzone przez koordynatorkę: `ai.php` wysyłał `messages` z dokładnie jednym
 * elementem — samym pytaniem, zero historii. Gość pytał „jak dojechać", dostawał
 * odpowiedź, dopytywał „gdzie zaparkować" — a model nie wiedział, o czym mowa,
 * choć wizualnie to jedna rozmowa.
 *
 * ⚠️ To NIE była nasza świadoma granica: odpuściliśmy pamięć MIĘDZY wizytami,
 * a tu chodzi o pamięć W TRAKCIE jednej rozmowy.
 *
 * Test nie wychodzi do API — podgląda ciało żądania filtrem `pre_http_request`.
 *
 * Uruchomienie:
 *   docker cp testy/pamiec-rozmowy-test.php strona_wp_wordpress:/tmp/pr.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/pr.php
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
/**
 * Serializacja DO SZUKANIA polskiego tekstu.
 *
 * wp_json_encode() domyślnie zamienia polskie znaki na \uXXXX, więc szukanie
 * w jego wyniku frazy z ogonkami NIGDY nie trafia — asercja „tego tu nie ma"
 * przechodziłaby wtedy pusto, niczego nie sprawdzając. Zmierzone na tym teście.
 */
function chatl_test_szukalne( $co ) {
	return (string) wp_json_encode( $co, JSON_UNESCAPED_UNICODE );
}

function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

global $wpdb;
$tabela = $wpdb->prefix . 'chatl_rozmowy';
$sesja  = 'test-pamiec-' . wp_generate_password( 12, false, false );
$obca   = 'test-obca-' . wp_generate_password( 12, false, false );

function chatl_test_wpis( $sesja, $rola, $tresc ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'chatl_rozmowy',
		array(
			'sesja'     => $sesja,
			'rola'      => $rola,
			'silnik'    => 'ai',
			'tresc'     => $tresc,
			'utworzono' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);
	return (int) $wpdb->insert_id;
}

echo "=== A. Pusta rozmowa nie wysyła pustej historii ===\n";
rowne( 'brak wymian => brak historii', array(), chatl_ai_historia( $sesja ) );
rowne( 'pusta sesja => brak historii', array(), chatl_ai_historia( '' ) );

echo "\n=== B. Historia jest chronologiczna i ma poprawne role ===\n";
/*
 * Cudzą rozmowę zakładamy NAJPIERW, żeby jej wiersze miały NIŻSZE id niż nasze.
 * Inaczej filtr „id < bieżące pytanie" odcinałby je niezależnie od warunku
 * sesji, a krok C przechodziłby PUSTO — sprawdzałby, że zapytanie z przeciekiem
 * nie zwraca czegoś, czego i tak nie mogłoby zwrócić. Zmierzone na tym teście
 * próbą kontrolną: mutant „przeciek między sesjami" nie był łapany.
 */
chatl_test_wpis( $obca, 'gosc', 'sekret innego goscia' );
chatl_test_wpis( $obca, 'bot', 'odpowiedz dla innego goscia' );
chatl_test_wpis( $sesja, 'gosc', 'jak do was dojechać' );
chatl_test_wpis( $sesja, 'bot', 'Przykładowa 1, Warszawa.' );
$id_teraz = chatl_test_wpis( $sesja, 'gosc', 'gdzie zaparkować' );
$h = chatl_ai_historia( $sesja, $id_teraz );
rowne( 'dwie wymiany przed bieżącym pytaniem', 2, count( $h ) );
rowne( 'pierwsza to pytanie gościa (user)', 'user', $h[0]['role'] );
rowne( 'druga to odpowiedź bota (assistant)', 'assistant', $h[1]['role'] );
rowne( 'kolejność chronologiczna', 'jak do was dojechać', $h[0]['content'] );
sprawdz( 'BIEŻĄCE pytanie NIE jest w historii (nie zdubluje się)',
	false === mb_strpos( chatl_test_szukalne( $h ), 'gdzie zaparkować' ) );

echo "\n=== C. Historia NIE przecieka między rozmowami ===\n";
$h = chatl_ai_historia( $sesja, $id_teraz );
sprawdz( 'wymiany z cudzej sesji nie wchodzą do historii',
	false === mb_strpos( chatl_test_szukalne( $h ), 'sekret innego goscia' ),
	chatl_test_szukalne( $h ) );
sprawdz( 'ani odpowiedzi bota z cudzej sesji',
	false === mb_strpos( chatl_test_szukalne( $h ), 'odpowiedz dla innego goscia' ) );

echo "\n=== D. Pamięć jest UCIĘTA — każda wymiana to tokeny właściciela ===\n";
for ( $i = 0; $i < 20; $i++ ) {
	chatl_test_wpis( $sesja, 'gosc', 'pytanie numer ' . $i );
	chatl_test_wpis( $sesja, 'bot', 'odpowiedz numer ' . $i );
}
$h = chatl_ai_historia( $sesja );
sprawdz( 'historia nie rośnie bez końca', count( $h ) <= CHATL_AI_PAMIEC_WYMIAN * 2, 'wiadomości: ' . count( $h ) );
sprawdz( 'zachowane są NAJNOWSZE wymiany, nie najstarsze',
	false !== mb_strpos( wp_json_encode( $h ), 'pytanie numer 19' )
	&& false === mb_strpos( wp_json_encode( $h ), 'pytanie numer 0"' ) );

echo "\n=== E. Długa odpowiedź z przeszłości nie zdominuje żądania ===\n";
$sesja_d = 'test-dluga-' . wp_generate_password( 8, false, false );
chatl_test_wpis( $sesja_d, 'gosc', 'krótkie pytanie' );
chatl_test_wpis( $sesja_d, 'bot', str_repeat( 'x', 5000 ) );
$h = chatl_ai_historia( $sesja_d );
sprawdz( 'wiadomość przycięta do limitu', mb_strlen( $h[1]['content'] ) <= CHATL_AI_PAMIEC_ZNAKOW + 1,
	'znaków: ' . mb_strlen( $h[1]['content'] ) . ', limit: ' . CHATL_AI_PAMIEC_ZNAKOW );

echo "\n=== F. Kształt rozmowy jest poprawny mimo dziur w zapisie ===\n";
$sesja_k = 'test-ksztalt-' . wp_generate_password( 8, false, false );
chatl_test_wpis( $sesja_k, 'bot', 'powitanie bez pytania' );
chatl_test_wpis( $sesja_k, 'gosc', 'pierwsze pytanie' );
chatl_test_wpis( $sesja_k, 'gosc', 'drugie pytanie bez odpowiedzi' );
chatl_test_wpis( $sesja_k, 'bot', 'odpowiedz' );
$h = chatl_ai_historia( $sesja_k );
sprawdz( 'historia zaczyna się od gościa', 'user' === $h[0]['role'], $h[0]['role'] );
$naprzemienne = true;
foreach ( $h as $i => $w ) {
	if ( $i > 0 && $h[ $i - 1 ]['role'] === $w['role'] ) {
		$naprzemienne = false;
	}
}
sprawdz( 'role są naprzemienne (dwa pytania pod rząd scalone)', $naprzemienne, wp_json_encode( wp_list_pluck( $h, 'role' ) ) );

echo "\n=== G. SEDNO: historia naprawdę leci w żądaniu do API ===\n";
$GLOBALS['chatl_test_body'] = '';
add_filter(
	'pre_http_request',
	function ( $wynik, $args ) {
		$GLOBALS['chatl_test_body'] = isset( $args['body'] ) ? (string) $args['body'] : '';
		return new WP_Error( 'chatl_test', 'przechwycone' );
	},
	10,
	2
);
$sesja_w = 'test-wysylka-' . wp_generate_password( 8, false, false );
chatl_test_wpis( $sesja_w, 'gosc', 'jak do was dojechać' );
chatl_test_wpis( $sesja_w, 'bot', 'Przykładowa 1, Warszawa.' );
chatl_ai_odpowiedz( 'gdzie zaparkować', chatl_ai_historia( $sesja_w ) );
remove_all_filters( 'pre_http_request' );
$cialo = json_decode( $GLOBALS['chatl_test_body'], true );
$msg   = is_array( $cialo ) && isset( $cialo['messages'] ) ? $cialo['messages'] : array();
sprawdz( 'messages ma WIĘCEJ niż jeden element (przed naprawą był dokładnie jeden)',
	count( $msg ) > 1, 'elementów: ' . count( $msg ) );
rowne( 'ostatni element to bieżące pytanie', 'gdzie zaparkować', end( $msg )['content'] );
sprawdz( 'wcześniejsza odpowiedź bota jest w żądaniu',
	false !== mb_strpos( chatl_test_szukalne( $msg ), 'Przykładowa 1' ) );
rowne( 'rozmowa zaczyna się od gościa', 'user', $msg[0]['role'] );

$wpdb->query( "DELETE FROM {$tabela} WHERE sesja LIKE 'test-pamiec-%' OR sesja LIKE 'test-obca-%' OR sesja LIKE 'test-dluga-%' OR sesja LIKE 'test-ksztalt-%' OR sesja LIKE 'test-wysylka-%'" );
rowne( 'test nie zostawia wierszy', 0,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabela} WHERE sesja LIKE 'test-pamiec-%' OR sesja LIKE 'test-wysylka-%'" ) );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
