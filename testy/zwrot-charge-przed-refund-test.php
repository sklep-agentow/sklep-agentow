<?php
/**
 * STRAŻNIK REGRESJI — klasa K-G1 (znaleziona 6.09.2026 przy sprawdzaniu uwag Mariusza):
 * `charge.refunded` (obiekt charge, `amount_refunded` = SUMA) przychodzące PRZED `refund.created`
 * tego samego zwrotu liczyło zwrot DWA razy: identyfikator z obiektu charge to `ch_…`, więc
 * późniejsze `refund.created` z `re_…` nie znajdowało swojego śladu i DODAWAŁO kwotę do sumy.
 * Stripe: „doesn’t guarantee the delivery of events in the order that they’re generated”
 * (https://docs.stripe.com/webhooks#event-ordering). Dotychczasowy test wysyłał `refund.created`
 * BEZ `id` (gałąź max()), a prawdziwy Stripe zawsze daje `id`.
 *
 * Uruchomienie:
 *   docker cp testy/zwrot-charge-przed-refund-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * PASS = w każdej kolejności zdarzeń suma zwrotów = suma PRAWDZIWYCH zwrotów.
 * FAIL = ten sam zwrot policzony dwa razy (charge przed refund) albo dwa różne zwroty zjedzone.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt || ! function_exists( 'sklepag_zaksieguj_zwrot' ) ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu albo funkcji ksiegowania)\n";
	exit( 2 );
}

$sid   = 'cs_test_straznik_kolejnosc';
$pi    = 'pi_test_straznik_kolejnosc';
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
			"INSERT INTO {$pz} (stripe_session_id, payment_intent_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona, produkt_plik_kupiony,
			 email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas, pobrania_limit, utworzono, zmieniono)
			 VALUES (%s, %s, %d, %s, %d, %s, %s, %d, 'PLN', 'mail_wyslany', 'zgoda', NOW(), 10, NOW(), NOW())",
			$sid,
			$pi,
			(int) $produkt->id,
			(string) $produkt->nazwa,
			$kwota,
			(string) $produkt->plik_sciezka,
			'straznik-kolejnosc@example.test',
			$kwota
		)
	);
};
$zwrocono = static function () use ( $wpdb, $pz, $sid ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT zwrocono_grosze FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
};
$charge = static function ( $suma, $ids ) use ( $pi ) {
	$dane = array();
	foreach ( $ids as $rid => $am ) {
		$dane[] = array( 'id' => $rid, 'amount' => $am, 'status' => 'succeeded' );
	}
	return array( 'id' => 'ch_test_kolejnosc', 'payment_intent' => $pi, 'amount' => 9900, 'amount_refunded' => $suma, 'refunded' => ( $suma >= 9900 ), 'refunds' => array( 'data' => $dane ) );
};
$refund = static function ( $rid, $am ) use ( $pi ) {
	return array( 'id' => $rid, 'payment_intent' => $pi, 'amount' => $am, 'status' => 'succeeded' );
};

/* ---------- 1) charge (z listą refunds) PRZED refund.created tego samego zwrotu ---------- */
$zaloz();
sklepag_zaksieguj_zwrot( 'charge.refunded', $charge( 3000, array( 're_kol_A' => 3000 ) ) );
sklepag_zaksieguj_zwrot( 'refund.created', $refund( 're_kol_A', 3000 ) );
$w1 = $zwrocono();
echo "CHARGE_PRZED_REFUND: zwrocono=$w1 (OCZEKIWANE: 3000 — jeden zwrot, dwa zdarzenia)\n";
$pass1 = ( 3000 === $w1 );
echo 'CHARGE_PRZED_REFUND: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - ten sam zwrot policzony dwa razy' ) . "\n";

/* ---------- 2) charge BEZ listy refunds (API bez rozwinięcia) PRZED refund.created ---------- */
$zaloz();
$ch = $charge( 3000, array() );
unset( $ch['refunds'] );
sklepag_zaksieguj_zwrot( 'charge.refunded', $ch );
sklepag_zaksieguj_zwrot( 'refund.created', $refund( 're_kol_B', 3000 ) );
$w2 = $zwrocono();
echo "CHARGE_BEZ_LISTY: zwrocono=$w2 (OCZEKIWANE: 3000 — suma z charge jest autorytatywna i juz pokrywa ten zwrot)\n";
$pass2 = ( 3000 === $w2 );
echo 'CHARGE_BEZ_LISTY: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - zwrot dopisany do sumy, ktora go juz zawierala' ) . "\n";

/* ---------- 3) KONTROLA: dwa RÓŻNE zwroty w zwykłej kolejności nadal się sumują ---------- */
$zaloz();
sklepag_zaksieguj_zwrot( 'refund.created', $refund( 're_kol_C', 3000 ) );
sklepag_zaksieguj_zwrot( 'charge.refunded', $charge( 3000, array( 're_kol_C' => 3000 ) ) );
sklepag_zaksieguj_zwrot( 'refund.created', $refund( 're_kol_D', 2000 ) );
sklepag_zaksieguj_zwrot( 'charge.refunded', $charge( 5000, array( 're_kol_C' => 3000, 're_kol_D' => 2000 ) ) );
$w3 = $zwrocono();
echo "DWA_ROZNE_ZWYKLA_KOLEJNOSC: zwrocono=$w3 (OCZEKIWANE: 5000)\n";
$pass3 = ( 5000 === $w3 );
echo 'DWA_ROZNE_ZWYKLA_KOLEJNOSC: WYNIK=' . ( $pass3 ? 'PASS' : 'FAIL - naprawa zjadla drugi zwrot' ) . "\n";

/* ---------- 4) KONTROLA: dwa RÓŻNE zwroty, charge zawsze PRZED swoim refund.created ---------- */
$zaloz();
$ch1 = $charge( 3000, array() ); unset( $ch1['refunds'] );
$ch2 = $charge( 5000, array() ); unset( $ch2['refunds'] );
sklepag_zaksieguj_zwrot( 'charge.refunded', $ch1 );
sklepag_zaksieguj_zwrot( 'refund.created', $refund( 're_kol_E', 3000 ) );
sklepag_zaksieguj_zwrot( 'charge.refunded', $ch2 );
sklepag_zaksieguj_zwrot( 'refund.created', $refund( 're_kol_F', 2000 ) );
$w4 = $zwrocono();
echo "DWA_ROZNE_CHARGE_PIERWSZY: zwrocono=$w4 (OCZEKIWANE: 5000)\n";
$pass4 = ( 5000 === $w4 );
echo 'DWA_ROZNE_CHARGE_PIERWSZY: WYNIK=' . ( $pass4 ? 'PASS' : 'FAIL - suma z charge nie rozstrzyga' ) . "\n";

$posprzataj();
$straznik_ok = ( $pass1 && $pass2 && $pass3 && $pass4 );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
