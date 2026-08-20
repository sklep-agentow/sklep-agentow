<?php
/**
 * L5 — model zaszyty na sztywno. Test-strażnik, kod wyjścia 1 przy porażce.
 *
 * Co groziło: `CHATL_AI_MODEL` wskazywał migawkę z datą
 * (`claude-haiku-4-5-20251001`). Migawki bywają wycofywane, a wtedy KAŻDA
 * sprzedana kopia traci tryb AI naraz — i jedyną drogą naprawy jest ręczna
 * wysyłka zipa mailem, bo nie mamy serwera aktualizacji. Filtr istniał, ale
 * kupujący wtyczkę nie pisze filtrów.
 *
 * Uruchomienie:
 *   docker cp testy/wybor-modelu-test.php strona_wp_wordpress:/tmp/wm.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/wm.php
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
function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}
function ustaw_model( $model ) {
	$ust             = chatl_ustawienia();
	$ust['ai_model'] = $model;
	update_option( 'chatl_ustawienia', $ust );
}

$stare_ust = get_option( 'chatl_ustawienia', array() );

echo "=== A. Domyślny model NIE MA daty w identyfikatorze ===\n";
sprawdz( 'CHATL_AI_MODEL bez sufiksu z datą', 1 !== preg_match( '/-\d{8}$/', CHATL_AI_MODEL ), 'model: ' . CHATL_AI_MODEL );
rowne( 'domyślnym jest alias bez daty', 'claude-haiku-4-5', CHATL_AI_MODEL );
sprawdz( 'żaden model z listy podpowiedzi nie ma daty',
	0 === count( array_filter( array_keys( chatl_ai_modele() ), function ( $m ) { return 1 === preg_match( '/-\d{8}$/', $m ); } ) ),
	implode( ', ', array_keys( chatl_ai_modele() ) ) );

echo "\n=== B. Bez ustawienia wtyczka używa wartości domyślnej ===\n";
ustaw_model( '' );
rowne( 'puste ustawienie => stała produktu', CHATL_AI_MODEL, chatl_ai_model() );

echo "\n=== C. Właściciel może wybrać własny model BEZ pisania filtrów ===\n";
ustaw_model( 'claude-sonnet-5' );
rowne( 'ustawienie wygrywa ze stałą', 'claude-sonnet-5', chatl_ai_model() );
ustaw_model( 'model-ktorego-jeszcze-nie-ma-2099' );
rowne( 'własny identyfikator też przechodzi (lista nie jest więzieniem)', 'model-ktorego-jeszcze-nie-ma-2099', chatl_ai_model() );

echo "\n=== D. Śmieci w ustawieniu nie wysyłają pustego modelu do API ===\n";
ustaw_model( '   ' );
rowne( 'same spacje => wracamy do domyślnego', CHATL_AI_MODEL, chatl_ai_model() );

echo "\n=== E. Filtr zostaje ostatnim słowem dla integratora ===\n";
ustaw_model( 'claude-sonnet-5' );
add_filter(
	'chatl_ai_model',
	function () {
		return 'model-z-proxy';
	}
);
rowne( 'filtr wygrywa z ustawieniem', 'model-z-proxy', chatl_ai_model() );
remove_all_filters( 'chatl_ai_model' );
add_filter(
	'chatl_ai_model',
	function () {
		return '';
	}
);
rowne( 'filtr zwracający pustkę nie psuje żądania', CHATL_AI_MODEL, chatl_ai_model() );
remove_all_filters( 'chatl_ai_model' );

echo "\n=== F. Model naprawdę trafia do żądania do API ===\n";
// Podglądamy ciało żądania filtrem WordPressa, bez wychodzenia w świat.
ustaw_model( 'claude-sonnet-5' );
$GLOBALS['chatl_test_body'] = '';
add_filter(
	'pre_http_request',
	function ( $wynik, $args ) {
		$GLOBALS['chatl_test_body'] = isset( $args['body'] ) ? (string) $args['body'] : '';
		return new WP_Error( 'chatl_test', 'przechwycone przez test' );
	},
	10,
	2
);
chatl_ai_odpowiedz( 'pytanie testowe' );
remove_all_filters( 'pre_http_request' );
$cialo = json_decode( $GLOBALS['chatl_test_body'], true );
sprawdz( 'żądanie zawiera pole model', is_array( $cialo ) && isset( $cialo['model'] ) );
rowne( 'i jest to model wybrany przez właściciela', 'claude-sonnet-5', is_array( $cialo ) ? (string) $cialo['model'] : '' );

update_option( 'chatl_ustawienia', $stare_ust );
sprawdz( 'ustawienia właściciela przywrócone', (string) ( chatl_ustawienia()['ai_model'] ?? '' ) === (string) ( $stare_ust['ai_model'] ?? '' ) );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
