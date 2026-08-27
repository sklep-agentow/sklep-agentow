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
 *        powtórka tego samego zdarzenia niczego nie zmienia (brak podwójnego księgowania)
 *        — dotyczy OBU stron: dopisania zwrotu i jego cofnięcia po odrzuceniu przez bank.
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
	exit( 1 ); // wyjście awaryjne też musi nieść kod błędu, inaczej CI zobaczy zero
}

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu)\n";
	exit( 2 ); // 2 = środowisko, nie werdykt o kodzie
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


$posprzataj();

/* ---------- 4) ZWROT ODRZUCONY PRZEZ BANK wraca do przychodu ---------- */
$id4 = $zaloz();
sklepag_zaksieguj_zwrot( 'refund.created', array( 'id' => 're_test_odrzucony', 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'pending' ) );
$po_zwrocie = (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id4 ) );
sklepag_zaksieguj_zwrot( 'refund.updated', array( 'id' => 're_test_odrzucony', 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'failed' ) );
$po_odrzuceniu = (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id4 ) );
echo "ODRZUCONY: po_zwrocie=$po_zwrocie (OCZEKIWANE: $kwota)\n";
echo "ODRZUCONY: po_odrzuceniu=$po_odrzuceniu (OCZEKIWANE: 0 — pieniadze zostaly u sprzedawcy)\n";
$pass4 = ( $kwota === $po_zwrocie && 0 === $po_odrzuceniu );
echo 'ODRZUCONY: WYNIK=' . ( $pass4 ? 'PASS' : 'FAIL - nieudany zwrot nie wrocil do przychodu' ) . "\n";

/* ---------- 5) DWA RÓŻNE zwroty częściowe po TYLE SAMO sumują się ---------- */
$id5 = $zaloz();
sklepag_zaksieguj_zwrot( 'refund.created', array( 'id' => 're_test_polowa_A', 'payment_intent' => $pi, 'amount' => 3000, 'status' => 'succeeded' ) );
sklepag_zaksieguj_zwrot( 'refund.created', array( 'id' => 're_test_polowa_B', 'payment_intent' => $pi, 'amount' => 3000, 'status' => 'succeeded' ) );
$suma = (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id5 ) );
echo "DWA_ROWNE: zwrocono=$suma (OCZEKIWANE: 6000 — dwa RÓZNE zwroty po 3000)\n";
$pass5 = ( 6000 === $suma );
echo 'DWA_ROWNE: WYNIK=' . ( $pass5 ? 'PASS' : 'FAIL - dwa rowne zwroty policzone jak jeden' ) . "\n";

/* ---------- 6) ZAPYTANIE Z BANKU nie rusza przychodu ---------- */
$id6 = $zaloz();
sklepag_zaksieguj_spor( 'charge.dispute.created', array( 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'warning_needs_response' ) );
$po6 = $wpdb->get_row( $wpdb->prepare( "SELECT status, zwrocono_grosze FROM {$pz} WHERE id=%d", $id6 ) );
echo 'ZAPYTANIE: status=' . $po6->status . " (OCZEKIWANE: mail_wyslany — bez zmiany)\n";
echo 'ZAPYTANIE: zwrocono=' . (int) $po6->zwrocono_grosze . " (OCZEKIWANE: 0 — bank jeszcze nic nie zabral)\n";
$pass6 = ( 'mail_wyslany' === $po6->status && 0 === (int) $po6->zwrocono_grosze );
echo 'ZAPYTANIE: WYNIK=' . ( $pass6 ? 'PASS' : 'FAIL - samo zapytanie z banku wyzerowalo sprzedaz' ) . "\n";

/* ---------- 7) SPÓR WYGRANY wraca do stanu SPRZED, nie zmysla wysylki ---------- */
$id7 = $zaloz();
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_blad', mail_proby=3 WHERE id=%d", $id7 ) );
sklepag_zaksieguj_spor( 'charge.dispute.created', array( 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'needs_response' ) );
$w_sporze = $wpdb->get_row( $wpdb->prepare( "SELECT status, status_przed_sporem FROM {$pz} WHERE id=%d", $id7 ) );
sklepag_zaksieguj_spor( 'charge.dispute.closed', array( 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'won' ) );
$po7 = $wpdb->get_row( $wpdb->prepare( "SELECT status, zwrocono_grosze FROM {$pz} WHERE id=%d", $id7 ) );
echo 'WYGRANY: w_sporze=' . $w_sporze->status . ' (zapamietany: ' . $w_sporze->status_przed_sporem . ")\n";
echo 'WYGRANY: status_po=' . $po7->status . " (OCZEKIWANE: mail_blad — mail nadal NIE doszedl)\n";
$pass7 = ( 'sporne' === $w_sporze->status && 'mail_blad' === $po7->status && 0 === (int) $po7->zwrocono_grosze );
echo 'WYGRANY: WYNIK=' . ( $pass7 ? 'PASS' : 'FAIL - wygrany spor zmyslil, ze mail poszedl' ) . "\n";

/* ---------- 8) ZWROT ŚRODKÓW PO SPORZE (funds_reinstated) nie cofa naprawy ---------- */
$id8 = $zaloz();
sklepag_zaksieguj_spor( 'charge.dispute.created', array( 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'needs_response' ) );
sklepag_zaksieguj_spor( 'charge.dispute.closed', array( 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'won' ) );
sklepag_zaksieguj_spor( 'charge.dispute.funds_reinstated', array( 'payment_intent' => $pi, 'amount' => $kwota, 'status' => 'won' ) );
$po8 = $wpdb->get_row( $wpdb->prepare( "SELECT status, zwrocono_grosze FROM {$pz} WHERE id=%d", $id8 ) );
echo 'PO_ZWROCIE_SRODKOW: status=' . $po8->status . " (OCZEKIWANE: mail_wyslany — spor wygrany zostaje wygrany)\n";
$pass8 = ( 'sporne' !== $po8->status && 0 === (int) $po8->zwrocono_grosze );
echo 'PO_ZWROCIE_SRODKOW: WYNIK=' . ( $pass8 ? 'PASS' : 'FAIL - spozniony komunikat o zwrocie srodkow znow wyrzucil zamowienie ze sprzedazy' ) . "\n";

/* ---------- 9) POWTÓRKA ZWROTU ODRZUCONEGO nie odejmuje kwoty drugi raz ---------- */
// Ścieżka DOPISANIA zwrotu ma znacznik po identyfikatorze od początku (przypadek 2).
// Ścieżka COFNIĘCIA go nie miała: jej własny wpis `zwrot_nieudany` pasował do wzorca
// `zwrot_%`, więc druga dostawa tego samego `refund.updated` przechodziła dalej i
// odejmowała kwotę ponownie. Przy DWÓCH zwrotach częściowych bezpiecznik `$kwota === $juz`
// tego nie łapie — dlatego przypadek ma tu dwa zwroty, nie jeden.
$id9 = $zaloz();
sklepag_zaksieguj_zwrot( 'refund.created', array( 'id' => 're_test_powt_A', 'payment_intent' => $pi, 'amount' => 3000, 'status' => 'succeeded' ) );
sklepag_zaksieguj_zwrot( 'refund.created', array( 'id' => 're_test_powt_B', 'payment_intent' => $pi, 'amount' => 2000, 'status' => 'succeeded' ) );
$po_dwoch = (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id9 ) );
$odrzucenie = array( 'id' => 're_test_powt_A', 'payment_intent' => $pi, 'amount' => 3000, 'status' => 'failed' );
sklepag_zaksieguj_zwrot( 'refund.updated', $odrzucenie );
$po_odrzuceniu9 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id9 ) );
sklepag_zaksieguj_zwrot( 'refund.updated', $odrzucenie ); // TA SAMA wiadomość dostarczona drugi raz
$po_powtorce9 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE id=%d", $id9 ) );
echo "POWTORKA_ODRZUCONY: po_dwoch_zwrotach=$po_dwoch (OCZEKIWANE: 5000)\n";
echo "POWTORKA_ODRZUCONY: po_odrzuceniu_A=$po_odrzuceniu9 (OCZEKIWANE: 2000 — zostaje sam zwrot B)\n";
echo "POWTORKA_ODRZUCONY: po_powtorce=$po_powtorce9 (OCZEKIWANE: 2000 — bez drugiego odjecia)\n";
$pass9 = ( 5000 === $po_dwoch && 2000 === $po_odrzuceniu9 && 2000 === $po_powtorce9 );
echo 'POWTORKA_ODRZUCONY: WYNIK=' . ( $pass9 ? 'PASS' : 'FAIL - powtorzone odrzucenie odjelo kwote drugi raz' ) . "\n";

$posprzataj();

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik krzyczy FAIL, a CI
// widzi zero i przepuszcza wadę (klasa 11: nazwa bramki szersza niż to, co sprawdza).
$straznik_ok = ( $pass1 && $pass2 && $pass3 && $pass4 && $pass5 && $pass6 && $pass7 && $pass8 && $pass9 );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
