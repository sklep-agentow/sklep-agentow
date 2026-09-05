<?php
/**
 * L3 — stop-loss liczył WYWOŁANIA, a rachunek idzie z TOKENÓW.
 * Test-strażnik, kod wyjścia 1 przy porażce.
 *
 * Klient z 200 parami Q&A płacił wielokrotność klienta z 10 parami przy
 * identycznym „limicie 100 wywołań na dobę", bo cała aktywna baza idzie
 * w instrukcji systemowej przy KAŻDYM pytaniu. W panelu nie było ani jednej
 * liczby o koszcie.
 *
 * Test nie wychodzi do prawdziwego API: podstawia odpowiedź filtrem
 * `pre_http_request`, więc jest deterministyczny, offline i darmowy.
 *
 * Uruchomienie:
 *   docker cp testy/tokeny-koszt-test.php strona_wp_wordpress:/tmp/tk.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/tk.php
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

/** Podstawia odpowiedź API z zadanym `usage`, bez wychodzenia w świat. */
function chatl_test_odpowiedz_api( $we, $wy, $cache_zapis = 0, $cache_odczyt = 0 ) {
	$GLOBALS['chatl_test_usage'] = array(
		'input_tokens'                => $we,
		'output_tokens'               => $wy,
		'cache_creation_input_tokens' => $cache_zapis,
		'cache_read_input_tokens'     => $cache_odczyt,
	);
	remove_all_filters( 'pre_http_request' );
	add_filter(
		'pre_http_request',
		function ( $wynik, $args ) {
			$GLOBALS['chatl_test_body'] = isset( $args['body'] ) ? (string) $args['body'] : '';
			return array(
				'headers'  => array(),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => wp_json_encode(
					array(
						'content' => array(
							array(
								'type' => 'text',
								'text' => 'Odpowiedź testowa.',
							),
						),
						'usage'   => $GLOBALS['chatl_test_usage'],
					)
				),
			);
		},
		10,
		2
	);
}

/**
 * Odpowiedź API o DOWOLNYM ciele — do ścieżek, które nie kończą się tekstem.
 *
 * @param string $cialo Surowe ciało odpowiedzi (może być nie-JSON).
 * @return void
 */
function chatl_test_odpowiedz_cialo( $cialo ) {
	remove_all_filters( 'pre_http_request' );
	add_filter(
		'pre_http_request',
		function () use ( $cialo ) {
			return array(
				'headers'  => array(),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => $cialo,
			);
		},
		10,
		2
	);
}

global $wpdb;
$tabela    = $wpdb->prefix . 'chatl_ai_uzycie';
$dzis      = current_time( 'Y-m-d' );
$stare_ust = get_option( 'chatl_ustawienia', array() );
$stary_licznik = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE dzien = %s", $dzis ), ARRAY_A );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabela} WHERE dzien = %s", $dzis ) );

echo "=== A. Tabela licznika ma kolumny na tokeny ===\n";
$kolumny = $wpdb->get_col( "SHOW COLUMNS FROM {$tabela}" );
foreach ( array( 'tokeny_wejscia', 'tokeny_wyjscia', 'tokeny_cache_zapis', 'tokeny_cache_odczyt' ) as $k ) {
	sprawdz( "kolumna {$k} istnieje", in_array( $k, $kolumny, true ) );
}

echo "\n=== B. Nazwy pól `usage` czytane zgodnie z dokumentacją API ===\n";
$u = chatl_ai_usage(
	array(
		'usage' => array(
			'input_tokens'                => 11,
			'output_tokens'               => 22,
			'cache_creation_input_tokens' => 33,
			'cache_read_input_tokens'     => 44,
		),
	)
);
rowne( 'input_tokens', 11, $u['tokeny_wejscia'] );
rowne( 'output_tokens', 22, $u['tokeny_wyjscia'] );
rowne( 'cache_creation_input_tokens', 33, $u['tokeny_cache_zapis'] );
rowne( 'cache_read_input_tokens', 44, $u['tokeny_cache_odczyt'] );
rowne( 'brak pola usage nie wywraca zapisu', 0, chatl_ai_usage( array() )['tokeny_wejscia'] );

echo "\n=== C. Udane wywołanie ZAPISUJE tokeny, nie tylko wywołanie ===\n";
chatl_test_odpowiedz_api( 1500, 120 );
chatl_ai_zarezerwuj(); // jak front.php:566 — od 1.5.18 licznik wywołań podbija REZERWACJA przed płatnym wywołaniem, nie chatl_ai_zuzyj()
chatl_ai_odpowiedz( 'pytanie testowe' );
$d = chatl_ai_zuzycie_dnia();
rowne( 'wywołania +1', 1, $d['wywolania'] );
rowne( 'tokeny wejścia zapisane', 1500, $d['tokeny_wejscia'] );
rowne( 'tokeny wyjścia zapisane', 120, $d['tokeny_wyjscia'] );
rowne( 'tokeny płatne to suma we+wy+zapis cache', 1620, chatl_ai_tokeny_dzis() );

echo "\n=== D. Kolejne wywołania SUMUJĄ się w tym samym dniu ===\n";
chatl_test_odpowiedz_api( 500, 80, 0, 900 );
chatl_ai_zarezerwuj(); // jak front.php:566 — od 1.5.18 licznik wywołań podbija REZERWACJA przed płatnym wywołaniem, nie chatl_ai_zuzyj()
chatl_ai_odpowiedz( 'drugie pytanie' );
$d = chatl_ai_zuzycie_dnia();
rowne( 'wywołania +1 (razem 2)', 2, $d['wywolania'] );
rowne( 'tokeny wejścia zsumowane', 2000, $d['tokeny_wejscia'] );
rowne( 'odczyt z cache liczony OSOBNO', 900, $d['tokeny_cache_odczyt'] );
rowne( 'odczyt z cache NIE wchodzi do tokenów płatnych', 2200, chatl_ai_tokeny_dzis() );

echo "\n=== E. SEDNO LUKI: limit tokenów zatrzymuje AI, gdy limit wywołań tego nie widzi ===\n";
$ust                      = chatl_ustawienia();
$ust['ai_limit_dzienny']  = 100;   // wywołań zużyto 2 ze 100 — ten limit jest daleki
$ust['ai_limit_tokenow']  = 2000;  // tokenów zużyto 2200 — ten jest przekroczony
update_option( 'chatl_ustawienia', $ust );
sprawdz( 'limit wywołań NIE jest wyczerpany', chatl_ai_uzycie_dzis() < 100, 'wywołań: ' . chatl_ai_uzycie_dzis() );
sprawdz( 'mimo to tryb AI jest zablokowany (chroni rachunek właściciela)', false === chatl_ai_dostepny() );
$ust['ai_limit_tokenow'] = 1000000;
update_option( 'chatl_ustawienia', $ust );
sprawdz( 'po podniesieniu limitu tokenów AI znów działa', true === chatl_ai_dostepny() );
$ust['ai_limit_tokenow'] = 0;
update_option( 'chatl_ustawienia', $ust );
sprawdz( 'limit 0 znaczy WYŁĄCZONY, nie „zero tokenów"', true === chatl_ai_dostepny() );

echo "\n=== F. Cache instrukcji dokładany TYLKO powyżej progu (poniżej działa po cichu wcale) ===\n";
$krotka = str_repeat( 'a', 100 );
$dluga  = str_repeat( 'a', CHATL_AI_CACHE_PROG_ZNAKOW );
$blok_k = chatl_ai_blok_systemowy( $krotka );
$blok_d = chatl_ai_blok_systemowy( $dluga );
sprawdz( 'system to TABLICA bloków (wymóg cache w API)', is_array( $blok_k ) && isset( $blok_k[0]['type'] ) );
sprawdz( 'krótka instrukcja BEZ cache_control', ! isset( $blok_k[0]['cache_control'] ) );
sprawdz( 'długa instrukcja Z cache_control ephemeral',
	isset( $blok_d[0]['cache_control']['type'] ) && 'ephemeral' === $blok_d[0]['cache_control']['type'] );
sprawdz( 'próg jest zapasowy względem 4096 tokenów', CHATL_AI_CACHE_PROG_ZNAKOW >= 4096 * 4, 'próg: ' . CHATL_AI_CACHE_PROG_ZNAKOW . ' znaków' );

echo "\n=== G. Instrukcja naprawdę leci jako tablica bloków ===\n";
chatl_test_odpowiedz_api( 10, 10 );
chatl_ai_zarezerwuj(); // jak front.php:566 — od 1.5.18 licznik wywołań podbija REZERWACJA przed płatnym wywołaniem, nie chatl_ai_zuzyj()
chatl_ai_odpowiedz( 'trzecie pytanie' );
$cialo = json_decode( $GLOBALS['chatl_test_body'], true );
sprawdz( 'pole system w żądaniu jest tablicą', is_array( $cialo ) && isset( $cialo['system'] ) && is_array( $cialo['system'] ) );
sprawdz( 'pierwszy blok ma typ text', is_array( $cialo ) && isset( $cialo['system'][0]['type'] ) && 'text' === $cialo['system'][0]['type'] );

echo "\n=== H. Sciezki, ktore KOSZTUJA, a nie konczyly sie tekstem (uwaga do PR #83) ===\n";
/*
 * Kod 200 znaczy, ze dostawca policzyl wywolanie — nawet jesli model odmowil
 * albo bloki wyszly puste. Przed poprawka te dwa wyjscia nie zwiekszaly ANI
 * licznika wywolan, ANI tokenow: wlasciciel placil, a oba stop-lossy tego
 * nie widzialy. W PR o liczeniu kosztu to byla dziura wprost w temacie.
 */
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabela} WHERE dzien = %s", $dzis ) );
delete_option( 'chatl_blad_ai' );

chatl_test_odpowiedz_cialo( wp_json_encode( array(
	'stop_reason' => 'refusal',
	'content'     => array( array( 'type' => 'text', 'text' => 'nie moge' ) ),
	'usage'       => array( 'input_tokens' => 700, 'output_tokens' => 30 ),
) ) );
chatl_ai_zarezerwuj(); // jak front.php:566 — od 1.5.18 licznik wywołań podbija REZERWACJA przed płatnym wywołaniem, nie chatl_ai_zuzyj()
$wynik = chatl_ai_odpowiedz( 'pytanie, ktore model odrzuci' );
$d     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE dzien = %s", $dzis ), ARRAY_A );
rowne( 'odmowa modelu daje sentinel BRAK_ODPOWIEDZI', CHATL_AI_BRAK, $wynik );
rowne( 'odmowa LICZY sie jako wywolanie', 1, (int) $d['wywolania'] );
rowne( 'odmowa LICZY tokeny (rachunek przyszedl)', 730, chatl_ai_tokeny_dzis() );
sprawdz( 'odmowa nadal NIE stawia znacznika awarii (granica z PR #79)', false === get_option( 'chatl_blad_ai' ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabela} WHERE dzien = %s", $dzis ) );
chatl_test_odpowiedz_cialo( wp_json_encode( array(
	'content' => array( array( 'type' => 'text', 'text' => '   ' ) ),
	'usage'   => array( 'input_tokens' => 500, 'output_tokens' => 10 ),
) ) );
chatl_ai_zarezerwuj(); // jak front.php:566 — od 1.5.18 licznik wywołań podbija REZERWACJA przed płatnym wywołaniem, nie chatl_ai_zuzyj()
$wynik = chatl_ai_odpowiedz( 'pytanie, na ktore model nic nie powie' );
$d     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE dzien = %s", $dzis ), ARRAY_A );
rowne( 'pusty tekst zjezdza do regul (null)', null, $wynik );
rowne( 'pusty tekst LICZY sie jako wywolanie', 1, (int) $d['wywolania'] );
rowne( 'pusty tekst LICZY tokeny', 510, chatl_ai_tokeny_dzis() );
sprawdz( 'pusty tekst ZOSTAWIA slad dla wlasciciela', is_array( get_option( 'chatl_blad_ai' ) ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabela} WHERE dzien = %s", $dzis ) );
delete_option( 'chatl_blad_ai' );
chatl_test_odpowiedz_cialo( '<html>brama zgodnosci zjadla odpowiedz</html>' );
chatl_ai_zarezerwuj(); // jak front.php:566 — od 1.5.18 licznik wywołań podbija REZERWACJA przed płatnym wywołaniem, nie chatl_ai_zuzyj()
$wynik = chatl_ai_odpowiedz( 'pytanie, ktoremu proxy podmienilo odpowiedz' );
$d     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE dzien = %s", $dzis ), ARRAY_A );
rowne( 'cialo nie do odczytania zjezdza do regul', null, $wynik );
rowne( 'i mimo to LICZY sie jako wywolanie', 1, (int) $d['wywolania'] );
rowne( 'tokenow nie ma skad wziac — zostaja zerem, nie zgadujemy', 0, chatl_ai_tokeny_dzis() );
sprawdz( 'i zostaje slad dla wlasciciela', is_array( get_option( 'chatl_blad_ai' ) ) );
delete_option( 'chatl_blad_ai' );

remove_all_filters( 'pre_http_request' );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabela} WHERE dzien = %s", $dzis ) );
if ( is_array( $stary_licznik ) ) {
	$wpdb->insert( $tabela, $stary_licznik );
}
update_option( 'chatl_ustawienia', $stare_ust );
sprawdz( 'licznik i ustawienia właściciela przywrócone',
	(int) chatl_ustawienia()['ai_limit_dzienny'] === (int) ( $stare_ust['ai_limit_dzienny'] ?? 100 ) );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
