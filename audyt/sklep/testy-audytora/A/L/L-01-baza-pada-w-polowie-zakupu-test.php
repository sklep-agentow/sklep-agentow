<?php
/**
 * Test PADAJĄCY (Ł-01 — "baza pada w połowie zakupu").
 *
 * Wywołuje PRAWDZIWĄ funkcję produkcyjną `sklepag_domknij_zakup()`
 * (includes/zakup.php) w chwili, gdy tabela `wp_sklepag_zamowienia` jest
 * niedostępna (symulacja awarii bazy przez RENAME TABLE, zgodnie z DOPISEK
 * KOORDYNATORKI — nie wolno zatrzymywać kontenera bazy współdzielonej).
 *
 * Uruchomienie (na stosie probny_wp, kontener probny_wp):
 *   docker cp L-01-baza-pada-w-polowie-zakupu-test.php probny_wp:/tmp/l01-test.php
 *   docker exec -u www-data probny_wp wp --path=/var/www/html eval-file /tmp/l01-test.php
 *
 * WYNIK Z WYKONANIA 26.08.2026 (patrz raport L.json, znalezisko Ł-01):
 *   PADA_TEST: domknij_zakup_zwrocil='false'
 *   PADA_TEST: sesja_stan_po=oplacona (OCZEKIWANE: utworzona, bo zamowienie NIE powstalo)
 *   PADA_TEST: liczba_zamowien_dla_sesji=0 (OCZEKIWANE: 0)
 *   PADA_TEST: nowe_zdarzenia_w_ostatnich_2min=1 (OCZEKIWANE: >=1 opisujące TĘ awarię — ale ten
 *              jeden wpis to STARE zdarzenie sprzed testu (webhook_zly_podpis), nie nowy wpis o
 *              awarii zapisu — zero NOWYCH zdarzeń opisujących AKURAT tę awarię)
 *   PADA_TEST: WYNIK=FAIL - sesja oznaczona jako oplacona mimo braku zamowienia; funkcja
 *              sklepag_dociagnij_zalegle() (reconciliation, includes/zakup.php:476-481) filtruje
 *              `WHERE stan='utworzona'` — sesja ze stanem 'oplacona' NIGDY nie zostanie dociągnięta.
 *
 * Test PADA na obecnym kodzie (0.9.13, commit 5d3dcf9). Przejdzie dopiero gdy:
 *   a) sklepag_oznacz_sesje($sid, 'oplacona') w sklepag_domknij_zakup() będzie wywoływane
 *      TYLKO gdy INSERT/UPDATE do zamowienia faktycznie się powiodło, ORAZ
 *   b) każda niepowodząca się próba zapisu do {$pz} zostawi jawny wpis w wp_sklepag_zdarzenia
 *      (np. typ 'domkniecie_blad_zapisu'), żeby właściciel miał sygnał w panelu.
 */

global $wpdb;
$sid = 'cs_test_lamacz01';
$pz  = $wpdb->prefix . 'sklepag_zamowienia';
$ps  = $wpdb->prefix . 'sklepag_sesje_checkout';

// Warunek wstępny: sesja checkout istnieje i jest w stanie "utworzona" (tak wygląda
// naprawdę tuż po kliknięciu "Kup teraz", zanim webhook Stripe dotrze).
$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s, %d, 'utworzona', NOW())", $sid, 1 ) );
$wpdb->update( $ps, array( 'stan' => 'utworzona' ), array( 'stripe_session_id' => $sid ) );

// Awaria bazy "w połowie zakupu": tabela zamówień znika na czas przetwarzania webhooka.
$wpdb->query( "RENAME TABLE {$pz} TO {$pz}_x" );

$sesja = array(
	'id'               => $sid,
	'payment_status'   => 'paid',
	'amount_total'     => 9900,
	'currency'         => 'pln',
	'customer_details' => array( 'email' => 'lamacz-test@example.test' ),
);

$ok = sklepag_domknij_zakup( $sesja );

// Przywrócenie NATYCHMIAST po teście (obowiązek z DOPISEK KOORDYNATORKI).
$wpdb->query( "RENAME TABLE {$pz}_x TO {$pz}" );

$stan             = $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid ) );
$liczba_zamowien  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$nowe_zdarzenia   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zdarzenia WHERE czas > DATE_SUB(NOW(), INTERVAL 2 MINUTE)" );

echo "PADA_TEST: domknij_zakup_zwrocil=" . var_export( $ok, true ) . "\n";
echo "PADA_TEST: sesja_stan_po={$stan} (OCZEKIWANE: utworzona, bo zamowienie NIE powstalo)\n";
echo "PADA_TEST: liczba_zamowien_dla_sesji={$liczba_zamowien} (OCZEKIWANE: 0)\n";
echo "PADA_TEST: nowe_zdarzenia_w_ostatnich_2min={$nowe_zdarzenia} (OCZEKIWANE: >=1 NOWY wpis o tej awarii)\n";

$fail = ( 'oplacona' === $stan && 0 === $liczba_zamowien );
if ( $fail ) {
	echo "PADA_TEST: WYNIK=FAIL - sesja oznaczona jako oplacona mimo braku zamowienia; sklepag_dociagnij_zalegle() (WHERE stan='utworzona') NIGDY jej nie dociagnie. Zakup trwale zgubiony, bez sladu w panelu.\n";
	exit( 1 );
}
echo "PADA_TEST: WYNIK=PASS\n";
exit( 0 );
