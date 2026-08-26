<?php
/**
 * STRAŻNIK REGRESJI — SK-L3-001 (panel liczył zwrócone pieniądze jako przychód).
 *
 * Wada: webhook obsługiwał wyłącznie checkout.session.*, więc zwrot i obciążenie zwrotne
 * nie zostawiały w sklepie ŻADNEGO śladu. Zamówienie zostawało w statusie 'mail_wyslany',
 * KPI „Przychód" liczyło je dalej, a oś czasu zamówienia milczała.
 *
 * Test woła PRAWDZIWE funkcje produkcyjne ładunkiem w kształcie zdarzenia Stripe.
 * Powiązanie idzie po `payment_intent_id` zapisanym w zamówieniu, więc test NIE puka do API.
 *
 * PASS = pełny zwrot zdejmuje zamówienie z przychodu i zapisuje status 'zwrocone';
 *        zwrot częściowy pomniejsza przychód o swoją kwotę i zostawia zamówienie na liście;
 *        powtórka tego samego zdarzenia niczego nie zmienia (brak podwójnego księgowania).
 * FAIL = przychód nadal liczy zwrócone pieniądze albo brak śladu w dzienniku.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';
if ( ! function_exists( 'sklepag_kpi_zakresu' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin-dashboard.php';
}

// Na kodzie SPRZED naprawy nie ma ani mechanizmu księgowania zwrotu, ani kolumn — to nie
// jest błąd testu, tylko dokładnie ta wada: sklep nie ma jak odnotować zwrotu pieniędzy.
$kolumny = $wpdb->get_col( "SHOW COLUMNS FROM {$pz}" );
$braki   = array_diff( array( 'payment_intent_id', 'zwrocono_grosze', 'zwrot_czas' ), $kolumny );
if ( ! function_exists( 'sklepag_zaksieguj_zwrot' ) || $braki ) {
	echo "PELNY: WYNIK=FAIL - brak mechanizmu ksiegowania zwrotu (funkcja: " .
		( function_exists( 'sklepag_zaksieguj_zwrot' ) ? 'jest' : 'BRAK' ) .
		'; brakujace kolumny: ' . ( $braki ? implode( ',', $braki ) : 'zadnej' ) . ")\n";
	echo "RAZEM: WYNIK=FAIL\n";
	return;
}

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu)\n";
	return;
}

$sid   = 'cs_test_straznik_zwrot';
$pi    = 'pi_test_straznik_zwrot';
$kwota = 9900;

$posprzataj = static function () use ( $wpdb, $pz, $pd, $sid, $pi ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	if ( $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE zamowienie_id=%d", $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $pi ) . '%' ) );
};
$zaloz = static function () use ( $wpdb, $pz, $sid, $pi, $kwota, $produkt, $posprzataj ) {
	$posprzataj();
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$pz} (stripe_session_id, payment_intent_id, produkt_id, produkt_nazwa_kupiona,
			 produkt_cena_kupiona, produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status,
			 zgoda_tresc, zgoda_czas, pobrania_limit, utworzono, zmieniono)
			 VALUES (%s,%s,%d,%s,%d,%s,%s,%d,'PLN','mail_wyslany','zgoda',NOW(),%d,NOW(),NOW())",
			$sid, $pi, (int) $produkt->id, $produkt->nazwa, $kwota, $produkt->plik_sciezka,
			'zwrot-straznik@example.test', $kwota, SKLEPAG_LIMIT_POBRAN
		)
	);
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
};
$przychod = static function () use ( $wpdb, $pz, $sid ) {
	// Ten sam wzór co KPI Pulpitu, zawężony do zamówienia testowego (stos bywa zaśmiecony).
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(GREATEST(kwota_grosze - zwrocono_grosze, 0)),0) FROM {$pz}
			 WHERE stripe_session_id=%s AND status IN (" . SKLEPAG_STATUSY_OPLACONE . ')',
			$sid
		)
	);
};

/* ---------- 1) ZWROT PEŁNY ---------- */
$id = $zaloz();
$przed = $przychod();
sklepag_zaksieguj_zwrot( 'charge.refunded', array( 'payment_intent' => $pi, 'amount' => $kwota, 'amount_refunded' => $kwota, 'refunded' => true ) );
$po      = $wpdb->get_row( $wpdb->prepare( "SELECT status, zwrocono_grosze FROM {$pz} WHERE id=%d", $id ) );
$przychod_po = $przychod();
$wpis    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE zamowienie_id=%d AND typ='zwrot_pelny'", $id ) );

echo "PELNY: przychod_przed=$przed (OCZEKIWANE: $kwota)\n";
echo "PELNY: przychod_po=$przychod_po (OCZEKIWANE: 0)\n";
echo 'PELNY: status=' . $po->status . " (OCZEKIWANE: zwrocone)\n";
echo 'PELNY: wpis_w_dzienniku=' . ( $wpis ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass1 = ( $kwota === $przed && 0 === $przychod_po && 'zwrocone' === $po->status && $wpis >= 1 );
echo 'PELNY: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - zwrocone pieniadze nadal licza sie jako przychod' ) . "\n";

/* ---------- 2) POWTÓRKA tego samego zdarzenia (bez podwójnego księgowania) ---------- */
sklepag_zaksieguj_zwrot( 'refund.created', array( 'payment_intent' => $pi, 'amount' => $kwota ) );
$po2 = $wpdb->get_row( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id ) );
$wpisy2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE zamowienie_id=%d AND typ LIKE 'zwrot_%%'", $id ) );
echo 'POWTORKA: zwrocono=' . (int) $po2->zwrocono_grosze . " (OCZEKIWANE: $kwota — bez podwojenia)\n";
echo "POWTORKA: wpisow_o_zwrocie=$wpisy2 (OCZEKIWANE: 1)\n";
$pass2 = ( $kwota === (int) $po2->zwrocono_grosze && 1 === $wpisy2 );
echo 'POWTORKA: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - drugie zdarzenie zaksiegowalo zwrot ponownie' ) . "\n";

/* ---------- 3) PRÓBA KONTROLNA: zwrot CZĘŚCIOWY ---------- */
$id3 = $zaloz();
sklepag_zaksieguj_zwrot( 'charge.refunded', array( 'payment_intent' => $pi, 'amount' => $kwota, 'amount_refunded' => 3000, 'refunded' => false ) );
$po3 = $wpdb->get_row( $wpdb->prepare( "SELECT status, zwrocono_grosze FROM {$pz} WHERE id=%d", $id3 ) );
$przychod3 = $przychod();
echo 'CZESCIOWY: status=' . $po3->status . " (OCZEKIWANE: mail_wyslany — zamowienie zostaje)\n";
echo "CZESCIOWY: przychod=$przychod3 (OCZEKIWANE: " . ( $kwota - 3000 ) . ")\n";
$pass3 = ( 'mail_wyslany' === $po3->status && ( $kwota - 3000 ) === $przychod3 );
echo 'CZESCIOWY: WYNIK=' . ( $pass3 ? 'PASS' : 'FAIL - zwrot czesciowy zle policzony' ) . "\n";

echo 'RAZEM: WYNIK=' . ( $pass1 && $pass2 && $pass3 ? 'PASS' : 'FAIL' ) . "\n";
$posprzataj();
