<?php
/**
 * L4 — zły albo wygasły klucz API. Test-strażnik, kod wyjścia 1 przy porażce.
 *
 * Co się psuło: nieudane wywołanie API szło wyłącznie do `debug.log`, który na
 * produkcji jest wyłączony. Właściciel płacił za tryb AI, dostawał odpowiedzi
 * z reguł i **nie miał jak tego zauważyć** — w nieskończoność.
 *
 * Test nie wychodzi do prawdziwego API: podmienia adres filtrem `chatl_ai_url`
 * (ten sam punkt rozszerzenia, którym wtyczka wpuszcza proxy) na własną stronę.
 * Adres nieistniejący daje HTTP 404 = „API odrzuciło", strona główna daje 200
 * = „API odpowiedziało". Dzięki temu test jest deterministyczny, offline
 * i nie kosztuje właściciela ani jednego tokena.
 *
 * Uruchomienie:
 *   docker cp testy/blad-klucza-test.php strona_wp_wordpress:/tmp/bk.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/bk.php
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

/** Podmiana adresu API na stronę, która na pewno odda zadany kod HTTP. */
function chatl_test_api_url( $url ) {
	$GLOBALS['chatl_test_url'] = $url;
	remove_all_filters( 'chatl_ai_url' );
	add_filter(
		'chatl_ai_url',
		function () {
			return $GLOBALS['chatl_test_url'];
		}
	);
}
/*
 * Adres bierzemy z PĘTLI ZWROTNEJ, nie z home_url(): adres witryny bywa
 * ustawiony na domenę zewnętrzną (na stacku warsztatowym to tunel), której
 * kontener nie rozwiąże — wtedy każde żądanie kończy się błędem połączenia
 * i test nie potrafiłby odróżnić „API odrzuciło" od „API odpowiedziało".
 * Zmierzone: cURL error 6, Could not resolve host.
 */
$adres_200 = 'http://127.0.0.1/';
$adres_404 = 'http://127.0.0.1/nie-ma-takiej-strony-chatl-test/';

// Stan startowy: bez znacznika awarii.
delete_option( 'chatl_blad_ai' );
sprawdz( 'klucz API jest skonfigurowany (bez niego test nic nie znaczy)', '' !== chatl_klucz_api() );

echo "\n=== A. Nieudane wywołanie ZOSTAWIA ślad dla właściciela ===\n";
chatl_test_api_url( $adres_404 );
$wynik = chatl_ai_test_klucza();
sprawdz( 'test klucza melduje porażkę', false === $wynik['ok'], 'kod: ' . $wynik['kod'] . ' — ' . $wynik['komunikat'] );
sprawdz( 'komunikat mówi WPROST, co jest nie tak', false !== mb_strpos( $wynik['komunikat'], '404' ) );
$blad = get_option( 'chatl_blad_ai' );
sprawdz( 'znacznik awarii zapisany', is_array( $blad ) && ! empty( $blad['kiedy'] ), wp_json_encode( $blad ) );
sprawdz( 'znacznik pamięta kod HTTP', is_array( $blad ) && 404 === (int) $blad['kod'] );
sprawdz( 'znacznik pamięta fragment odpowiedzi', is_array( $blad ) && isset( $blad['tresc'] ) );
sprawdz( 'fragment jest PRZYCIĘTY (nie cała strona błędu)', is_array( $blad ) && mb_strlen( (string) $blad['tresc'] ) <= 300, 'znaków: ' . mb_strlen( (string) $blad['tresc'] ) );

echo "\n=== B. Ścieżka GOŚCIA też zostawia ślad (nie tylko przycisk w panelu) ===\n";
delete_option( 'chatl_blad_ai' );
chatl_test_api_url( $adres_404 );
$odp = chatl_ai_odpowiedz( 'ile kosztuje dostawa' );
sprawdz( 'przy błędzie API bot zjeżdża do reguł (null)', null === $odp );
sprawdz( 'zjazd do reguł NIE jest już cichy — znacznik jest', is_array( get_option( 'chatl_blad_ai' ) ) );

echo "\n=== C. Udane wywołanie ZDEJMUJE znacznik ===\n";
chatl_test_api_url( $adres_200 );
$wynik = chatl_ai_test_klucza();
sprawdz( 'test klucza melduje sukces', true === $wynik['ok'], 'kod: ' . $wynik['kod'] . ' — ' . $wynik['komunikat'] );
sprawdz( 'komunikat podaje nazwę modelu', false !== mb_strpos( $wynik['komunikat'], (string) apply_filters( 'chatl_ai_model', CHATL_AI_MODEL ) ) );
sprawdz( 'znacznik awarii zdjęty', false === get_option( 'chatl_blad_ai' ) );

echo "\n=== D. Sukces przy braku znacznika NIE pisze do bazy ===\n";
// Udane wywołania to przypadek NORMALNY — gdyby każde kasowało opcję, każde
// pytanie gościa kończyłoby się zapisem do bazy bez powodu. Mierzymy DRUGIE
// wywołanie z rzędu: pierwsze może jeszcze dopisać brak opcji do pamięci
// podręcznej WordPressa, drugie nie ma już czego szukać ani czego zapisać.
chatl_odnotuj_sukces_ai();
$zapytan_przed = get_num_queries();
chatl_odnotuj_sukces_ai();
$zapytan_po = get_num_queries();
sprawdz( 'brak znacznika => zero zapytań do bazy', $zapytan_po === $zapytan_przed, 'zapytań: ' . ( $zapytan_po - $zapytan_przed ) );
sprawdz( 'znacznika nadal nie ma', false === get_option( 'chatl_blad_ai' ) );

echo "\n=== E. Brak klucza mówi to wprost, zamiast dzwonić do API ===\n";
$stary_klucz = get_option( 'chatl_klucz_api' );
delete_option( 'chatl_klucz_api' );
$wynik = chatl_ai_test_klucza();
sprawdz( 'bez klucza test odmawia', false === $wynik['ok'] );
sprawdz( 'i mówi, co zrobić', false !== mb_stripos( $wynik['komunikat'], 'klucz' ), $wynik['komunikat'] );
if ( false !== $stary_klucz ) {
	add_option( 'chatl_klucz_api', $stary_klucz, '', false );
}
sprawdz( 'klucz właściciela przywrócony po teście', chatl_klucz_api() === (string) $stary_klucz );

delete_option( 'chatl_blad_ai' );
echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
