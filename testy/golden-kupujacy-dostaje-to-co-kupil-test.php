<?php
/**
 * GOLDEN — kupujący dostaje DOKŁADNIE to, co kupił.
 *
 * ⛔ SCENARIUSZ, KTÓREGO NIE MIAŁ ŻADEN Z NASZYCH TESTÓW: produkt zmienia się MIĘDZY
 * utworzeniem płatności a jej domknięciem. Do 0.9.31 `sklepag_domknij_zakup()` czytało
 * produkt z tabeli PONOWNIE i to z tego świeżego odczytu zapisywało `produkt_nazwa_kupiona`,
 * `produkt_cena_kupiona` i `produkt_plik_kupiony` — czyli kolumny „kupiona" zamrażały stan
 * z chwili DOMKNIĘCIA, nie z chwili zakupu. Skutki, każdy osobno:
 *   1. właściciel podmienia plik → kupujący pobiera CO INNEGO, niż kupił,
 *   2. właściciel zmienia cenę → Stripe pobrał starą kwotę, a w wierszu zamówienia leżą
 *      dwie różne wartości z dwóch źródeł (`kwota_grosze` ze Stripe, `produkt_cena_kupiona`
 *      z bieżącej tabeli),
 *   3. odczyt przy domknięciu nie pytał o `aktywny=1` (checkout pyta) — produkt wycofany
 *      ze sprzedaży i tak się domykał z bieżących danych.
 *
 * ⭐ TEN TEST MUSI PADAĆ NA KODZIE SPRZED NAPRAWY. Jeśli przechodzi, mierzy co innego.
 *
 * Przebieg: sesja przez PRAWDZIWY handler zakupu (odpowiedź Stripe podstawiona filtrem
 * `pre_http_request` — bez sieci) → PODMIANA produktu w bazie → domknięcie przez
 * `sklepag_domknij_zakup()` → co wylądowało w zamówieniu i w OBU wersjach maila.
 *
 * Uruchomienie:
 *   docker cp testy/golden-kupujacy-dostaje-to-co-kupil-test.php strona_wp_wordpress:/tmp/gold.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/gold.php
 * Kody wyjścia: 0 = kupujący dostał swoje · 1 = WADA · 2 = błąd przyrządu.
 */

$GLOBALS['sklepag_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_test_bledy'];
	}
}
function przyrzad( $powod ) {
	echo "  PRZYRZAD: {$powod}\n";
	exit( 2 );
}

foreach ( array( 'sklepag_obsluz_kup', 'sklepag_domknij_zakup', 'sklepag_zbuduj_mail', 'sklepag_zbuduj_mail_html' ) as $f ) {
	if ( ! function_exists( $f ) ) {
		przyrzad( "brak funkcji {$f} — wtyczka sklep-agentow nieaktywna?" );
	}
}
/* Ścieżka OSADZONA kończy się `wp_send_json`, którą da się przechwycić. Ścieżka hostowana
   kończy się `exit` po przekierowaniu — po niej żadna asercja by się nie wykonała. */
if ( '' === sklepag_stripe_klucz_publikowalny() ) {
	przyrzad( 'brak klucza publikowalnego na stosie — handler poszedlby sciezka hostowana (exit), test nic by nie zmierzyl' );
}

global $wpdb;
$pp = $wpdb->prefix . 'sklepag_produkty';
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

$znacznik = 'golden-' . wp_generate_password( 8, false, false );
$plik_a   = get_temp_dir() . 'sklepag-' . $znacznik . '-A.zip';
$plik_b   = get_temp_dir() . 'sklepag-' . $znacznik . '-B.zip';
file_put_contents( $plik_a, "PLIK KUPIONY (A)\n" );
file_put_contents( $plik_b, "PLIK PODMIENIONY PO ZAKUPIE (B)\n" );

$NAZWA_PRZED = 'Golden PRZED ' . $znacznik;
$NAZWA_PO    = 'Golden PO PODMIANIE ' . $znacznik;
$CENA_PRZED  = 14900;
$CENA_PO     = 99900;

$teraz = current_time( 'mysql' );
$wpdb->insert(
	$pp,
	array(
		'nazwa'        => $NAZWA_PRZED,
		'slug'         => $znacznik,
		'opis_krotki'  => 'produkt probny golden',
		'cena_grosze'  => $CENA_PRZED,
		'waluta'       => 'PLN',
		'plik_sciezka' => $plik_a,
		'aktywny'      => 1,
		'utworzono'    => $teraz,
	)
);
$produkt_id = (int) $wpdb->insert_id;
if ( ! $produkt_id ) {
	przyrzad( 'nie udalo sie zalozyc produktu probnego: ' . $wpdb->last_error );
}

$SID = 'cs_test_' . str_replace( '-', '', $znacznik ) . 'GOLDEN';

/* Sprzątanie w shutdown: fatal albo `exit` w połowie nie ma prawa zostawić atrapy produktu
   na WSPÓLNYM stosie warsztatowym. */
$sprzataj = static function () use ( $wpdb, $pp, $ps, $pz, $pd, $produkt_id, $SID, $plik_a, $plik_b ) {
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id = %s", $SID ) ); // phpcs:ignore
	if ( $zam ) {
		$wpdb->delete( $pd, array( 'zamowienie_id' => (int) $zam->id ) );
		$wpdb->delete( $wpdb->prefix . 'sklepag_opinie', array( 'zamowienie_id' => (int) $zam->id ) );
		$wpdb->delete( $pz, array( 'id' => (int) $zam->id ) );
	}
	$wpdb->delete( $ps, array( 'stripe_session_id' => $SID ) );
	$wpdb->delete( $pp, array( 'id' => $produkt_id ) );
	@unlink( $plik_a );
	@unlink( $plik_b );
};
register_shutdown_function( $sprzataj );

// =====================================================================
// 1. UTWORZENIE SESJI przez PRAWDZIWY handler zakupu (bez sieci)
// =====================================================================
echo "== 1. Utworzenie platnosci (handler produkcyjny, odpowiedz Stripe podstawiona) ==\n";

$podstaw = static function ( $wynik, $args, $url ) use ( $SID ) {
	if ( false === strpos( (string) $url, '/checkout/sessions' ) ) {
		return $wynik;
	}
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode(
			array(
				'id'            => $SID,
				'url'           => 'https://checkout.stripe.test/' . $SID,
				'client_secret' => $SID . '_secret',
			)
		),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
	);
};
add_filter( 'pre_http_request', $podstaw, 10, 3 );

$nonce                = wp_create_nonce( 'sklepag_kup' );
$_POST                = array(
	'produkt_id' => $produkt_id,
	'zgoda'      => '1',
	'osadzona'   => '1',
	'_wpnonce'   => $nonce,
);
$_REQUEST              = $_POST;
$_SERVER['REQUEST_METHOD'] = 'POST';

/* `wp_send_json` konczy handler przez `wp_die`. Podmieniamy uchwyt na wyjatek, zeby
   oddal sterowanie zamiast ubic proces — inaczej asercje nizej nigdy by nie ruszyly. */
$rzuc = static function () {
	return static function () {
		throw new Exception( 'wp_die-przechwycone' );
	};
};
/* WSZYSTKIE TRZY uchwyty: `wp_die()` wybiera jeden z nich zaleznie od ksztaltu zadania,
   a `wp_send_json` ustawia naglowek JSON. Podmiana tylko domyslnego zostawiala WP-CLI
   jego wlasny uchwyt — proces konczyl sie po wypisaniu JSON-a, z kodem 0, i ani jedna
   asercja nie ruszala. Zlapane na sobie: „EXIT=0" bez ani jednej linii OK. */
add_filter( 'wp_die_handler', $rzuc, 99 );
add_filter( 'wp_die_json_handler', $rzuc, 99 );
add_filter( 'wp_die_ajax_handler', $rzuc, 99 );
/* ⛔ POZA AJAX-em `wp_send_json()` konczy sie GOLYM `die` — bez `wp_die`, wiec bez zadnego
   uchwytu do podmiany. Zlapane na sobie: proces konczyl sie po wypisaniu JSON-a z kodem 0
   i ANI JEDNA asercja nie ruszala, a wyglada to jak test, ktory przeszedl. Wlaczamy tryb
   AJAX na czas wywolania handlera — wtedy `wp_send_json` idzie przez `wp_die`. */
add_filter( 'wp_doing_ajax', '__return_true' );

ob_start();
try {
	sklepag_obsluz_kup();
} catch ( Exception $e ) {
	// spodziewane
}
$odpowiedz = trim( (string) ob_get_clean() );
remove_filter( 'pre_http_request', $podstaw, 10 );
remove_filter( 'wp_doing_ajax', '__return_true' );

$sesja_wiersz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ps} WHERE stripe_session_id = %s", $SID ) ); // phpcs:ignore
if ( ! $sesja_wiersz ) {
	echo '  odpowiedz handlera: ' . substr( $odpowiedz, 0, 300 ) . "\n";
	przyrzad( 'handler NIE zalozyl wiersza sesji — nie ma czego domykac, test nic by nie zmierzyl' );
}
sprawdz( 'K1 warunek wstepny: sesja zalozona przez handler produkcyjny', true, 'sesja ' . $SID . ', produkt_id ' . (int) $sesja_wiersz->produkt_id );

$przed = $wpdb->get_row( $wpdb->prepare( "SELECT nazwa, cena_grosze, plik_sciezka FROM {$pp} WHERE id = %d", $produkt_id ) ); // phpcs:ignore
sprawdz( 'K2 warunek wstepny: produkt w bazie ma stan SPRZED podmiany', $NAZWA_PRZED === $przed->nazwa && $CENA_PRZED === (int) $przed->cena_grosze && $plik_a === $przed->plik_sciezka );

// =====================================================================
// 2. PODMIANA PRODUKTU — dokladnie to, co robi wlasciciel w panelu
// =====================================================================
echo "\n== 2. Wlasciciel podmienia produkt PO utworzeniu platnosci ==\n";
$wpdb->update(
	$pp,
	array(
		'nazwa'        => $NAZWA_PO,
		'cena_grosze'  => $CENA_PO,
		'plik_sciezka' => $plik_b,
		'aktywny'      => 0,
	),
	array( 'id' => $produkt_id )
);
$po = $wpdb->get_row( $wpdb->prepare( "SELECT nazwa, cena_grosze, plik_sciezka, aktywny FROM {$pp} WHERE id = %d", $produkt_id ) ); // phpcs:ignore
/* ⛔ BEZ TEGO WARUNKU caly test bylby wydmuszka: „kupujacy dostal PRZED" znaczyloby tylko
   tyle, ze podmiana nie weszla. Dowodzimy, ze tabela produktow NAPRAWDE sie zmienila. */
sprawdz( 'K3 warunek wstepny: podmiana WESZLA do tabeli produktow', $NAZWA_PO === $po->nazwa && $CENA_PO === (int) $po->cena_grosze && $plik_b === $po->plik_sciezka && 0 === (int) $po->aktywny );

// =====================================================================
// 3. DOMKNIECIE PLATNOSCI — webhook przynosi kwote, ktora Stripe NAPRAWDE pobral
// =====================================================================
echo "\n== 3. Domkniecie platnosci (webhook) ==\n";
/* ⛔ MAIL MIERZYMY TEN, KTORY NAPRAWDE WYSZEDL, a nie zbudowany drugi raz obok — inaczej
   test sprawdzalby wlasne sklejenie, nie sciezke produkcyjna. `pre_wp_mail` przechwytuje
   wysylke bez PHPMailera; wersja tekstowa wisi w globalu, ktory wtyczka zdejmuje po wyslaniu. */
$przechwycony = array();
$lap_mail     = static function ( $wynik, $atts ) use ( &$przechwycony ) {
	$przechwycony   = (array) $atts;
	$przechwycony['alt_tekst'] = isset( $GLOBALS['sklepag_mail_alt_tekst'] ) ? (string) $GLOBALS['sklepag_mail_alt_tekst'] : '';
	return true;
};
add_filter( 'pre_wp_mail', $lap_mail, 10, 2 );

$ladunek = array(
	'id'               => $SID,
	'amount_total'     => $CENA_PRZED,
	'currency'         => 'pln',
	'payment_status'   => 'paid',
	'customer_details' => array( 'email' => 'golden-' . $znacznik . '@example.test' ),
	'metadata'         => array( 'zgoda_czas' => current_time( 'mysql', true ) ),
);
$domkniete = sklepag_domknij_zakup( $ladunek );
sprawdz( 'K4 domkniecie zwrocilo powodzenie', (bool) $domkniete );

$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE stripe_session_id = %s", $SID ) ); // phpcs:ignore
if ( ! $zam ) {
	przyrzad( 'domkniecie NIE zalozylo zamowienia — nie ma czego mierzyc' );
}

// =====================================================================
// 4. GOLDEN — co dostal kupujacy
// =====================================================================
echo "\n== 4. GOLDEN: kupujacy dostaje to, co kupil ==\n";
sprawdz( 'G1 nazwa w zamowieniu = nazwa Z CHWILI ZAKUPU', $NAZWA_PRZED === (string) $zam->produkt_nazwa_kupiona, 'jest: ' . $zam->produkt_nazwa_kupiona );
sprawdz( 'G2 cena w zamowieniu = cena Z CHWILI ZAKUPU', $CENA_PRZED === (int) $zam->produkt_cena_kupiona, 'jest: ' . (int) $zam->produkt_cena_kupiona );
sprawdz( 'G3 PLIK w zamowieniu = plik Z CHWILI ZAKUPU (to jego pobierze kupujacy)', $plik_a === (string) $zam->produkt_plik_kupiony, 'jest: ' . $zam->produkt_plik_kupiony );
/* Dwie wartosci o pieniadzach w JEDNYM wierszu musza sie zgadzac: kwota faktycznie pobrana
   przez Stripe i cena zapisana jako kupiona. Rozjazd znaczy, ze pochodza z dwoch zrodel. */
sprawdz( 'G4 kwota pobrana przez Stripe == cena zapisana jako kupiona (jedno zrodlo, nie dwa)', (int) $zam->kwota_grosze === (int) $zam->produkt_cena_kupiona, 'kwota_grosze ' . (int) $zam->kwota_grosze . ' vs produkt_cena_kupiona ' . (int) $zam->produkt_cena_kupiona );
sprawdz( 'G5 tresc pliku pod zapisana sciezka = to, co bylo w chwili zakupu', file_exists( (string) $zam->produkt_plik_kupiony ) && false !== strpos( (string) file_get_contents( (string) $zam->produkt_plik_kupiony ), 'PLIK KUPIONY (A)' ) );

remove_filter( 'pre_wp_mail', $lap_mail, 10 );
if ( empty( $przechwycony ) ) {
	przyrzad( 'domkniecie nie wyslalo maila — nie ma czego mierzyc w punktach G6-G8' );
}
$temat    = isset( $przechwycony['subject'] ) ? (string) $przechwycony['subject'] : '';
$mail_h   = isset( $przechwycony['message'] ) ? (string) $przechwycony['message'] : '';
$mail_t   = (string) $przechwycony['alt_tekst'];
sprawdz( 'G6 TEMAT maila niesie nazwe Z CHWILI ZAKUPU, nie biezaca', false !== strpos( $temat, $NAZWA_PRZED ) && false === strpos( $temat, $NAZWA_PO ), 'temat: ' . $temat );
sprawdz( 'G7 mail HTML (ten wyslany) niesie nazwe Z CHWILI ZAKUPU', false !== strpos( $mail_h, $NAZWA_PRZED ) && false === strpos( $mail_h, $NAZWA_PO ) );
sprawdz( 'G8 wersja tekstowa maila niesie nazwe Z CHWILI ZAKUPU', '' !== $mail_t && false !== strpos( $mail_t, $NAZWA_PRZED ) && false === strpos( $mail_t, $NAZWA_PO ) );
/* Wycofanie produktu ze sprzedazy NIE MA prawa zablokowac domkniecia juz OPLACONEGO zakupu —
   pieniadze sa pobrane, wiec towar musi dojsc. `aktywny=0` blokuje NOWE zakupy, nie stare. */
sprawdz( 'G9 produkt wycofany (aktywny=0) NIE blokuje domkniecia oplaconego zakupu', 'oczekuje' !== (string) $zam->status && '' !== (string) $zam->produkt_plik_kupiony, 'status: ' . $zam->status );

// =====================================================================
// 5. ZAPAS DLA SESJI SPRZED MIGRACJI — naprawa nie ma prawa odebrac obslugi
//    zakupom, ktore wisialy w locie w chwili aktualizacji wtyczki.
// =====================================================================
echo "\n== 5. Sesja SPRZED migawki (schemat < 10): domkniecie dalej dziala ==\n";
$SID_STARY = $SID . 'STARA';
$wpdb->insert(
	$ps,
	array(
		'stripe_session_id' => $SID_STARY,
		'produkt_id'        => $produkt_id,
		'stan'              => 'utworzona',
		'utworzono'         => current_time( 'mysql' ),
	)
);
$stara = $wpdb->get_row( $wpdb->prepare( "SELECT produkt_nazwa FROM {$ps} WHERE stripe_session_id = %s", $SID_STARY ) ); // phpcs:ignore
sprawdz( 'K5 warunek wstepny: sesja NAPRAWDE bez migawki', $stara && '' === (string) $stara->produkt_nazwa );
$ladunek2                     = $ladunek;
$ladunek2['id']               = $SID_STARY;
$ladunek2['customer_details'] = array( 'email' => 'golden-stara-' . $znacznik . '@example.test' );
sklepag_domknij_zakup( $ladunek2 );
$zam2 = $wpdb->get_row( $wpdb->prepare( "SELECT produkt_nazwa_kupiona, status FROM {$pz} WHERE stripe_session_id = %s", $SID_STARY ) ); // phpcs:ignore
/* Dla takiej sesji jedynym zrodlem jest tabela produktow — czyli stan BIEZACY. To nie jest
   regres, tylko jedyne, co da sie o tym zakupie wiedziec; wazne, ze zamowienie POWSTAJE. */
sprawdz( 'F1 zamowienie z sesji bez migawki POWSTAJE (zapas dziala)', (bool) $zam2, $zam2 ? 'status ' . $zam2->status : 'BRAK WIERSZA' );
sprawdz( 'F2 dla sesji bez migawki dane ida z tabeli produktow (stare zachowanie)', $zam2 && $NAZWA_PO === (string) $zam2->produkt_nazwa_kupiona, $zam2 ? (string) $zam2->produkt_nazwa_kupiona : '' );
$zam2_pelne = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id = %s", $SID_STARY ) ); // phpcs:ignore
if ( $zam2_pelne ) {
	$wpdb->delete( $pd, array( 'zamowienie_id' => (int) $zam2_pelne->id ) );
	$wpdb->delete( $wpdb->prefix . 'sklepag_opinie', array( 'zamowienie_id' => (int) $zam2_pelne->id ) );
	$wpdb->delete( $pz, array( 'id' => (int) $zam2_pelne->id ) );
}
$wpdb->delete( $ps, array( 'stripe_session_id' => $SID_STARY ) );

$sprzataj();
echo "\n" . ( $GLOBALS['sklepag_test_bledy'] ? 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n" : "WSZYSTKO OK\n" );
exit( $GLOBALS['sklepag_test_bledy'] ? 1 : 0 );
