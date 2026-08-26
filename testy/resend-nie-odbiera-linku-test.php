<?php
/**
 * STRAŻNIK REGRESJI — SK-L3-004 („Wyślij ponownie" odbierał kupującemu dostęp).
 *
 * Wada: sklepag_obsluz_resend() zapisywało NOWY token_pobrania PRZED wysyłką maila.
 * Gdy wysyłka padała (wp_mail = false), stary link dawał 403, a nowy nigdy nie
 * dotarł — kupujący tracił dostęp do opłaconego produktu przez akcję administratora.
 *
 * Awaria wysyłki wymuszona filtrem rdzenia `pre_wp_mail` (WP 5.7+) — BEZ podmiany
 * plików wtyczki, więc test nie zmienia badanego kodu (mierzy produkt, nie atrapę).
 *
 * Uruchomienie:
 *   docker cp testy/resend-nie-odbiera-linku-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * PASS = po nieudanym resendzie token pobrania i termin są NIEZMIENIONE (stary link żyje).
 * FAIL = token nadpisany mimo nieudanej wysyłki (kupujący został bez dostępu).
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';

// Plik panelu wtyczka ładuje tylko pod is_admin(); w WP-CLI wczytujemy go wprost,
// żeby testować PRAWDZIWĄ funkcję produkcyjną, a nie jej kopię.
if ( ! function_exists( 'sklepag_obsluz_resend' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin-zamowienie.php';
}

$sid          = 'cs_test_resend_strażnik';
$stary_token  = hash( 'sha256', 'STARY-TOKEN-KTORY-MA-PRZEZYC' );
$stary_termin = gmdate( 'Y-m-d H:i:s', time() + 50 * HOUR_IN_SECONDS );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak produktu w bazie)\n";
	return;
}
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$pz} (stripe_session_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona,
		 produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas,
		 pobrania_limit, token_pobrania, link_wygasa, utworzono, zmieniono)
		 VALUES (%s,%d,%s,%d,%s,%s,%d,'PLN','mail_wyslany','zgoda',NOW(),%d,%s,%s,NOW(),NOW())",
		$sid,
		(int) $produkt->id,
		$produkt->nazwa,
		(int) $produkt->cena_grosze,
		$produkt->plik_sciezka,
		'straznik-resend@example.test',
		(int) $produkt->cena_grosze,
		SKLEPAG_LIMIT_POBRAN,
		$stary_token,
		$stary_termin
	)
);
$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );

// Administrator klika „Wyślij ponownie" — prawdziwa ścieżka, prawdziwy nonce.
wp_set_current_user( 1 );
$_GET['zamowienie']     = $id;
$_REQUEST['zamowienie'] = $id;
$_GET['_wpnonce']       = wp_create_nonce( 'sklepag_resend' );
$_REQUEST['_wpnonce']   = $_GET['_wpnonce'];

// Awaria wysyłki (filtr rdzenia — zero zmian w kodzie wtyczki).
add_filter( 'pre_wp_mail', '__return_false', 99 );
// Przekierowanie kończy handler przez exit — zamieniamy je na wyjątek, żeby dobiec do asercji.
add_filter(
	'wp_redirect',
	static function ( $miejsce ) {
		throw new Exception( 'PRZEKIEROWANIE: ' . $miejsce );
	},
	99
);

$przekierowanie = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$przekierowanie = $e->getMessage();
}

$po = $wpdb->get_row( $wpdb->prepare( "SELECT token_pobrania, link_wygasa, status, mail_proby FROM {$pz} WHERE id=%d", $id ) );

echo 'STRAZNIK: przekierowanie=' . ( false !== strpos( $przekierowanie, 'sklepag_resend=blad' ) ? 'blad (OK)' : $przekierowanie ) . "\n";
echo 'STRAZNIK: token_niezmieniony=' . ( $po->token_pobrania === $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'STRAZNIK: termin_niezmieniony=' . ( (string) $po->link_wygasa === $stary_termin ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'STRAZNIK: status=' . $po->status . " (OCZEKIWANE: mail_blad)\n";

$pass = ( $po->token_pobrania === $stary_token && (string) $po->link_wygasa === $stary_termin && 'mail_blad' === $po->status );
echo 'STRAZNIK: WYNIK=' . ( $pass ? 'PASS' : 'FAIL - nieudany resend odebral kupujacemu dzialajacy link' ) . "\n";

// ---- CZĘŚĆ 2 (próba kontrolna): UDANY resend MUSI nadal rotować token ----
// Bez tego „naprawa" mogłaby po prostu przestać wydawać świeże linki i część 1
// świeciłaby na zielono z zupełnie złego powodu.
remove_filter( 'pre_wp_mail', '__return_false', 99 );
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', token_pobrania=%s, link_wygasa=%s WHERE id=%d", $stary_token, $stary_termin, $id ) );
$_GET['_wpnonce']     = wp_create_nonce( 'sklepag_resend' );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];

$przekierowanie2 = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$przekierowanie2 = $e->getMessage();
}
$po2 = $wpdb->get_row( $wpdb->prepare( "SELECT token_pobrania, status FROM {$pz} WHERE id=%d", $id ) );
$rotacja_ok = ( $po2->token_pobrania !== $stary_token && 'mail_wyslany' === $po2->status );
echo 'KONTROLA: udany_resend_rotuje_token=' . ( $po2->token_pobrania !== $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'KONTROLA: status_po_udanym=' . $po2->status . " (OCZEKIWANE: mail_wyslany)\n";
echo 'KONTROLA: WYNIK=' . ( $rotacja_ok ? 'PASS' : 'FAIL - naprawa zablokowala rotacje tokenu' ) . "\n";

echo 'RAZEM: WYNIK=' . ( $pass && $rotacja_ok ? 'PASS' : 'FAIL' ) . "\n";

$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id=%d", $id ) );
