<?php
/**
 * Pozycja A — przejęcie rozmowy przez człowieka. Strażnik, kod wyjścia 1 przy porażce.
 *
 * Sprawdza trzy rzeczy, które przy odbiorze są rozstrzygające:
 *
 *   1. publiczny punkt odpytywania NIE wydaje cudzej rozmowy — także wtedy, gdy
 *      napastnik ZNA identyfikator sesji (drugim składnikiem jest sekret z ciastka
 *      HttpOnly, którego nie ma);
 *   2. gość WIDZI, że rozmawia z człowiekiem — komunikat leci do okna rozmowy;
 *   3. OBA kierunki: bot MILCZY po przejęciu i ODZYWA SIĘ po zakończeniu.
 *
 * Kroki idą przez PRAWDZIWE żądania HTTP do endpointów wtyczki (admin-ajax
 * i REST), a nie przez wywołanie funkcji z pominięciem warstwy wejścia —
 * inaczej strażnik pilnowałby czegoś innego niż to, co widzi gość.
 *
 * Uruchomienie:
 *   docker cp testy/przejecie-test.php strona_wp_wordpress:/tmp/pj.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/pj.php
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

/*
 * Adres jako STALA, nie zmienna: `wp eval-file` uruchamia kod wewnatrz funkcji,
 * wiec zmienna z gory pliku NIE jest globalna i `global $baza` w pomocniku dalby
 * null — a zadanie poszloby pod pusty adres i kazdy krok padlby "z niewiadomych
 * powodow". Wpadka warta komentarza, bo trafia kazdego, kto pisze tu nowy test.
 */
define( 'CHATL_TEST_BAZA', 'http://localhost' );

/** Żądanie do admin-ajax jako GOŚĆ (bez ciastek zalogowanego). */
function chatl_test_ajax( $akcja, $pola = array(), $ciastka = array() ) {
	$naglowki = array();
	if ( $ciastka ) {
		$pary = array();
		foreach ( $ciastka as $k => $v ) {
			$pary[] = $k . '=' . $v;
		}
		$naglowki['Cookie'] = implode( '; ', $pary );
	}
	$odp = wp_remote_post(
		CHATL_TEST_BAZA . '/wp-admin/admin-ajax.php',
		array(
			'timeout' => 10,
			'headers' => $naglowki,
			'body'    => array_merge( array( 'action' => $akcja ), $pola ),
		)
	);
	return array(
		'kod'   => is_wp_error( $odp ) ? 0 : (int) wp_remote_retrieve_response_code( $odp ),
		'dane'  => is_wp_error( $odp ) ? null : json_decode( wp_remote_retrieve_body( $odp ), true ),
		'ciast' => is_wp_error( $odp ) ? array() : wp_remote_retrieve_header( $odp, 'set-cookie' ),
	);
}

/** Odpytanie punktu REST jako gość; $ciastka = array( nazwa => wartosc ). */
function chatl_test_odpytaj( $sesja, $od_id = 0, $ciastka = array() ) {
	$naglowki = array( 'Content-Type' => 'application/json' );
	if ( $ciastka ) {
		$pary = array();
		foreach ( $ciastka as $k => $v ) {
			$pary[] = $k . '=' . $v;
		}
		$naglowki['Cookie'] = implode( '; ', $pary );
	}
	$odp = wp_remote_post(
		CHATL_TEST_BAZA . '/wp-json/chatl/v1/rozmowa',
		array(
			'timeout' => 10,
			'headers' => $naglowki,
			'body'    => wp_json_encode(
				array(
					'sesja' => $sesja,
					'od_id' => (int) $od_id,
				)
			),
		)
	);
	return array(
		'kod'  => is_wp_error( $odp ) ? 0 : (int) wp_remote_retrieve_response_code( $odp ),
		'dane' => is_wp_error( $odp ) ? null : json_decode( wp_remote_retrieve_body( $odp ), true ),
	);
}

/** Treści wszystkich wiadomości w rozmowie, po roli. */
function chatl_test_role( $sesja ) {
	global $wpdb;
	$t = $wpdb->prefix . 'chatl_rozmowy';
	$w = $wpdb->get_results( $wpdb->prepare( "SELECT rola, tresc FROM {$t} WHERE sesja = %s ORDER BY id ASC", $sesja ), ARRAY_A ); // phpcs:ignore
	$out = array();
	foreach ( (array) $w as $x ) {
		$out[] = $x['rola'];
	}
	return $out;
}

global $wpdb;
$stare_ust = get_option( 'chatl_ustawienia', array() );

/*
 * Czyscimy WLASNE kuble limitu przed przebiegiem. Powod jest merytoryczny, nie
 * kosmetyczny: wydawanie sesji jest limitowane na adres IP (10/min), a proba
 * kontrolna uruchamia ten straznik SIEDEM razy pod rzad z jednego adresu. Bez
 * tego przebieg nr 2 padalby na limicie i wygladal jak blad w kodzie, ktorego
 * tam nie ma. Kasujemy tylko kuble tego testu, nie cala tabele.
 */
$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE 'sesja|%' OR klucz LIKE 'chat|%' OR klucz LIKE 'odpyt|%' OR klucz LIKE 'nonce|%'" ); // phpcs:ignore

echo "=== A. Sesje wydaje SERWER, nie przegladarka ===\n";
$odp = chatl_test_ajax( 'chatl_sesja' );
$sesja = isset( $odp['dane']['data']['sesja'] ) ? $odp['dane']['data']['sesja'] : '';
sprawdz( 'punkt wydaje sesje', '' !== $sesja, 'sesja: ' . $sesja );
rowne( 'sesja ma 32 znaki', 32, strlen( $sesja ) );
sprawdz( 'sesja jest alfanumeryczna', (bool) preg_match( '/^[a-zA-Z0-9]{32}$/', $sesja ) );

// Sekret wychodzi WYLACZNIE jako ciastko HttpOnly — nie w tresci odpowiedzi.
$naglowek_ciastka = is_array( $odp['ciast'] ) ? implode( ' ', $odp['ciast'] ) : (string) $odp['ciast'];
sprawdz( 'sekret idzie ciastkiem HttpOnly', false !== stripos( $naglowek_ciastka, 'httponly' ), trim( substr( $naglowek_ciastka, 0, 120 ) ) );
sprawdz( 'sekretu NIE ma w tresci odpowiedzi', false === strpos( wp_json_encode( $odp['dane'] ), 'chatl_k_' ) );

$sekret = '';
if ( preg_match( '/chatl_k_' . preg_quote( $sesja, '/' ) . '=([^;]+)/', $naglowek_ciastka, $m ) ) {
	$sekret = $m[1];
}
sprawdz( 'ciastko dotyczy TEJ sesji', '' !== $sekret );

$odp2   = chatl_test_ajax( 'chatl_sesja' );
$sesja2 = isset( $odp2['dane']['data']['sesja'] ) ? $odp2['dane']['data']['sesja'] : '';
sprawdz( 'dwie sesje sa rozne', $sesja !== $sesja2 );

echo "\n=== B. Bot ODPOWIADA, dopoki rozmowy nie przejal czlowiek ===\n";
$n     = chatl_test_ajax( 'chatl_nonce' );
$nonce = isset( $n['dane']['data']['nonce'] ) ? $n['dane']['data']['nonce'] : '';
sprawdz( 'gosc dostal nonce', '' !== $nonce );

$p = chatl_test_ajax(
	'chatl_pytanie',
	array(
		'nonce'   => $nonce,
		'sesja'   => $sesja,
		'pytanie' => 'jakie macie godziny otwarcia',
	)
);
sprawdz( 'bot odpowiedzial na pytanie', ! empty( $p['dane']['data']['odpowiedz'] ), 'odpowiedz: ' . substr( (string) ( $p['dane']['data']['odpowiedz'] ?? '' ), 0, 60 ) );
sprawdz( 'w rozmowie jest wiersz bota', in_array( 'bot', chatl_test_role( $sesja ), true ), 'role: ' . implode( ',', chatl_test_role( $sesja ) ) );

echo "\n=== C. PRZEJECIE: gosc widzi, ze rozmawia z czlowiekiem ===\n";
chatl_przejmij( $sesja, 1 );
rowne( 'stan rozmowy to czlowiek', 'czlowiek', chatl_stan_rozmowy( $sesja ) );

$o = chatl_test_odpytaj( $sesja, 0, array( 'chatl_k_' . $sesja => $sekret ) );
rowne( 'odpytywanie zwraca stan czlowiek', 'czlowiek', $o['dane']['stan'] ?? '' );
$tresci = wp_json_encode( $o['dane']['nowe'] ?? array() );
sprawdz( 'gosc dostaje KOMUNIKAT o czlowieku', false !== strpos( $tresci, 'konsultant' ), 'w odpowiedzi: ' . mb_substr( wp_json_encode( $o['dane']['nowe'][0]['tresc'] ?? '' ), 0, 90 ) );
rowne( 'interwal skraca sie do 5 s', 5, (int) ( $o['dane']['interwal'] ?? 0 ) );

echo "\n=== D. KIERUNEK 1: po przejeciu bot MILCZY ===\n";
$role_przed = chatl_test_role( $sesja );
$ile_bot_przed = count( array_keys( $role_przed, 'bot', true ) );

$p2 = chatl_test_ajax(
	'chatl_pytanie',
	array(
		'nonce'   => $nonce,
		'sesja'   => $sesja,
		'pytanie' => 'a czy jest parking?',
	)
);
$role_po      = chatl_test_role( $sesja );
$ile_bot_po   = count( array_keys( $role_po, 'bot', true ) );
$ile_gosc_po  = count( array_keys( $role_po, 'gosc', true ) );

sprawdz( 'endpoint melduje, ze rozmowe przejeto', ! empty( $p2['dane']['data']['przejeta'] ) );
rowne( 'bot NIE dopisal ani jednej odpowiedzi', $ile_bot_przed, $ile_bot_po );
rowne( 'pytanie goscia MIMO TO jest zapisane (operator ma je przeczytac)', 2, $ile_gosc_po );
sprawdz( 'bot nie odeslal tresci do goscia', '' === (string) ( $p2['dane']['data']['odpowiedz'] ?? '' ) );

echo "\n=== E. Odpowiedz operatora dochodzi do goscia ===\n";
chatl_operator_wiadomosc( $sesja, 'Parking jest od podworza, wjazd od ulicy Krotkiej.' );
$o2 = chatl_test_odpytaj( $sesja, 0, array( 'chatl_k_' . $sesja => $sekret ) );
$t2 = wp_json_encode( $o2['dane']['nowe'] ?? array() );
sprawdz( 'wiadomosc operatora jest w odpowiedzi', false !== strpos( $t2, 'Krotkiej' ) );
sprawdz( 'i ma role operator, nie bot', false !== strpos( $t2, '"rola":"operator"' ) );

echo "\n=== F. KIERUNEK 2: po zakonczeniu bot ZNOWU MOWI ===\n";
chatl_oddaj_botowi( $sesja, 'Konsultant zakonczyl rozmowe — dalej pomaga bot.' );
rowne( 'stan wrocil do bota', 'bot', chatl_stan_rozmowy( $sesja ) );

$p3 = chatl_test_ajax(
	'chatl_pytanie',
	array(
		'nonce'   => $nonce,
		'sesja'   => $sesja,
		'pytanie' => 'jakie macie godziny otwarcia',
	)
);
$ile_bot_koniec = count( array_keys( chatl_test_role( $sesja ), 'bot', true ) );
sprawdz( 'bot znowu odpowiada', ! empty( $p3['dane']['data']['odpowiedz'] ) );
sprawdz( 'i dopisal odpowiedz do rozmowy', $ile_bot_koniec > $ile_bot_po, 'wierszy bota: ' . $ile_bot_przed . ' -> ' . $ile_bot_po . ' -> ' . $ile_bot_koniec );
sprawdz( 'gosc dostal komunikat o powrocie bota', false !== strpos( wp_json_encode( chatl_test_odpytaj( $sesja, 0, array( 'chatl_k_' . $sesja => $sekret ) )['dane']['nowe'] ?? array() ), 'zakonczyl' ) );

echo "\n=== G. SEDNO BEZPIECZENSTWA: cudza rozmowa jest nie do odczytania ===\n";
// Napastnik ZNA identyfikator sesji ofiary (wyciekl z logu, zrzutu ekranu,
// cudzej wtyczki czytajacej sessionStorage) — ale nie ma sekretu z ciastka.
chatl_przejmij( $sesja, 1 );
chatl_operator_wiadomosc( $sesja, 'TAJNE: numer zamowienia 12345 i adres klienta' );

$bez_ciastka = chatl_test_odpytaj( $sesja, 0 );
rowne( 'zadanie bez ciastka konczy sie 200 (bez sygnalu, ze sesja istnieje)', 200, $bez_ciastka['kod'] );
rowne( 'napastnik NIE dostaje ani jednej wiadomosci', 0, count( (array) ( $bez_ciastka['dane']['nowe'] ?? array( 1 ) ) ) );
sprawdz( 'i nie widzi TRESCI rozmowy', false === strpos( wp_json_encode( $bez_ciastka['dane'] ), 'TAJNE' ) );
rowne( 'ani stanu przejecia', 'bot', $bez_ciastka['dane']['stan'] ?? '' );

$podrobione = chatl_test_odpytaj( $sesja, 0, array( 'chatl_k_' . $sesja => 'podrobiony-sekret-napastnika' ) );
sprawdz( 'podrobione ciastko tez nic nie daje', false === strpos( wp_json_encode( $podrobione['dane'] ), 'TAJNE' ) );

// Sesja obcego goscia: wlasciwy sekret, ale CUDZA rozmowa.
$o3       = chatl_test_ajax( 'chatl_sesja' );
$sesja_x  = $o3['dane']['data']['sesja'];
$nag_x    = is_array( $o3['ciast'] ) ? implode( ' ', $o3['ciast'] ) : (string) $o3['ciast'];
preg_match( '/chatl_k_' . preg_quote( $sesja_x, '/' ) . '=([^;]+)/', $nag_x, $mx );
$obcy = chatl_test_odpytaj( $sesja, 0, array( 'chatl_k_' . $sesja_x => $mx[1] ?? 'x' ) );
sprawdz( 'ciastko INNEJ sesji nie otwiera tej rozmowy', false === strpos( wp_json_encode( $obcy['dane'] ), 'TAJNE' ) );

$zly = chatl_test_odpytaj( '../../etc/passwd' );
rowne( 'identyfikator o zlym ksztalcie odrzucony', 400, $zly['kod'] );

echo "\n=== H. Powrot do bota po CISZY operatora (bez crona) ===\n";
$t = $wpdb->prefix . 'chatl_sesje';
$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET operator_akt = %s, przejeto = %s WHERE sesja = %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - CHATL_PRZEJECIE_CISZA - 60 ), gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - CHATL_PRZEJECIE_CISZA - 60 ), $sesja ) ); // phpcs:ignore
rowne( 'cisza operatora oddaje rozmowe botowi', 'bot', chatl_stan_rozmowy( $sesja ) );
$po_ciszy = chatl_test_odpytaj( $sesja, 0, array( 'chatl_k_' . $sesja => $sekret ) );
sprawdz( 'i gosc dowiaduje sie o tym WPROST', false !== strpos( wp_json_encode( $po_ciszy['dane']['nowe'] ?? array() ), 'nie odpowiada' ) );

echo "\n=== I. Sprzatanie ===\n";
foreach ( array( $sesja, $sesja2, $sesja_x ) as $s ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja = %s", $s ) ); // phpcs:ignore
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE sesja = %s", $s ) ); // phpcs:ignore
}
update_option( 'chatl_ustawienia', $stare_ust );
sprawdz( 'wiersze testowe usuniete', 0 === count( chatl_test_role( $sesja ) ) );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
