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

/**
 * Podstawienie odpowiedzi API bez wychodzenia w świat.
 *
 * Pierwsza wersja tego testu kierowała żądania filtrem `chatl_ai_url` na pętlę
 * zwrotną (`http://127.0.0.1/`). Działało w izolacji, ale **rozsypywało się przy
 * przebiegu razem z testem przeglądarkowym**: ten sam serwer PHP obsługiwał
 * wtedy przeglądarkę, a żądanie do siebie samego potrafiło przekroczyć timeout
 * i „udany" przypadek wychodził jako błąd połączenia. Zmierzone — próba
 * kontrolna meldowała losowo „czysty kod NIE przechodzi".
 *
 * `pre_http_request` nie rusza sieci w ogóle, więc wynik nie zależy od
 * obciążenia stacku ani od kolejności testów.
 *
 * @param int    $kod   Kod HTTP, który ma zobaczyć wtyczka.
 * @param string $cialo Ciało odpowiedzi.
 * @return void
 */
function chatl_test_odpowiedz( $kod, $cialo ) {
	remove_all_filters( 'pre_http_request' );
	$GLOBALS['chatl_test_kod']   = $kod;
	$GLOBALS['chatl_test_cialo'] = $cialo;
	add_filter(
		'pre_http_request',
		function () {
			return array(
				'headers'  => array(),
				'response' => array(
					'code'    => $GLOBALS['chatl_test_kod'],
					'message' => 'stub',
				),
				'body'     => $GLOBALS['chatl_test_cialo'],
			);
		}
	);
}
/** Poprawna odpowiedź modelu — do przypadków „API zadziałało". */
function chatl_test_cialo_ok() {
	return '{"content":[{"type":"text","text":"Odpowiedź testowa."}],"usage":{"input_tokens":5,"output_tokens":2}}';
}
$cialo_404 = '<html><body>Page not found</body></html>';

// Stan startowy: bez znacznika awarii.
delete_option( 'chatl_blad_ai' );
sprawdz( 'klucz API jest skonfigurowany (bez niego test nic nie znaczy)', '' !== chatl_klucz_api() );

echo "\n=== A. Nieudane wywołanie ZOSTAWIA ślad dla właściciela ===\n";
chatl_test_odpowiedz( 404, $cialo_404 );
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
chatl_test_odpowiedz( 404, $cialo_404 );
$odp = chatl_ai_odpowiedz( 'ile kosztuje dostawa' );
sprawdz( 'przy błędzie API bot zjeżdża do reguł (null)', null === $odp );
sprawdz( 'zjazd do reguł NIE jest już cichy — znacznik jest', is_array( get_option( 'chatl_blad_ai' ) ) );

echo "\n=== C. Udane wywołanie ZDEJMUJE znacznik ===\n";
chatl_test_odpowiedz( 200, chatl_test_cialo_ok() );
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

echo "\n=== E. HTTP 200 ze ZNIEKSZTAŁCONYM ciałem też zostawia ślad ===\n";
/*
 * Uwaga recenzenta do PR #79: kod 200 to jeszcze nie sukces. Gdy ciało nie jest
 * poprawnym JSON-em albo bloki tekstowe dają pusty string, gość i tak dostaje
 * odpowiedź z reguł — czyli dla właściciela skutek jest IDENTYCZNY jak przy złym
 * kluczu. Te ścieżki zwracały null BEZ znacznika, więc awaria była cicha,
 * choć ten PR miał ją właśnie uwidocznić.
 *
 * Ciało podstawiamy filtrem pre_http_request: kodu HTTP nie da się rozjechać
 * z treścią zwykłym adresem URL, a to właśnie ten rozjazd tu badamy.
 */
$przypadki = array(
	'ciało w ogóle nie jest JSON-em'          => '<html>Blad bramy proxy</html>',
	'JSON bez pola content'                   => '{"id":"msg_1","model":"x"}',
	'content puste'                           => '{"content":[]}',
	'bloki bez tekstu (same spacje)'          => '{"content":[{"type":"text","text":"   "}]}',
	'bloki innego typu niż text'              => '{"content":[{"type":"image","source":{}}]}',
);
foreach ( $przypadki as $opis => $cialo ) {
	delete_option( 'chatl_blad_ai' );
	chatl_test_odpowiedz( 200, $cialo );
	$wynik = chatl_ai_odpowiedz( 'pytanie testowe' );
	$blad  = get_option( 'chatl_blad_ai' );
	sprawdz( "HTTP 200, {$opis}: bot zjeżdża do reguł", null === $wynik );
	sprawdz( "HTTP 200, {$opis}: ZOSTAJE ślad dla właściciela", is_array( $blad ) && ! empty( $blad['kiedy'] ),
		is_array( $blad ) ? 'kod w znaczniku: ' . $blad['kod'] : 'BRAK ZNACZNIKA' );
	sprawdz( "HTTP 200, {$opis}: znacznik pamięta kod 200, nie 0", is_array( $blad ) && 200 === (int) $blad['kod'] );
}
remove_all_filters( 'pre_http_request' );

echo "\n=== E2. Odmowa modelu to NIE awaria — nie zostawia śladu ===\n";
// stop_reason 'refusal' znaczy „model świadomie nie odpowiada" i wtyczka
// zamienia to na uczciwe „nie wiem" plus zbiórkę kontaktu. To poprawne
// działanie produktu, a nie usterka — czerwony pasek byłby tu fałszywym alarmem.
delete_option( 'chatl_blad_ai' );
chatl_test_odpowiedz( 200, '{"stop_reason":"refusal","content":[{"type":"text","text":"nie moge"}]}' );
$wynik = chatl_ai_odpowiedz( 'pytanie testowe' );
remove_all_filters( 'pre_http_request' );
sprawdz( 'odmowa daje sentinel BRAK_ODPOWIEDZI', CHATL_AI_BRAK === $wynik, 'zwrocone: ' . var_export( $wynik, true ) );
sprawdz( 'i NIE stawia znacznika awarii', false === get_option( 'chatl_blad_ai' ) );

echo "\n=== F. Brak klucza mówi to wprost, zamiast dzwonić do API ===\n";
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
