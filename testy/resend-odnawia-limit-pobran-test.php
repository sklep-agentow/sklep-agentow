<?php
/**
 * STRAŻNIK REGRESJI — „Wyślij ponownie" MUSI odnawiać limit pobrań.
 *
 * Wada (recenzja instrukcji sklepu, runda 4, KRYTYCZNA): instrukcja kieruje po ten przycisk
 * kupującego z WYCZERPANYM limitem, a sklepag_obsluz_resend() zapisywało tylko token, termin
 * i treść maila — `pobrania_uzyte` zostawało na 10, więc świeży link od razu dawał 410
 * „Limit pobrań wyczerpany". Mail ze świeżym linkiem obiecywał przy tym „pozwala na 10 pobrań".
 *
 * Uruchomienie:
 *   docker cp testy/resend-odnawia-limit-pobran-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * CZĘŚĆ 1: udany resend przy wyczerpanym limicie → pobrania_uzyte = 0 (świeży link daje pobrania).
 * CZĘŚĆ 2 (kompensacja): NIEUDANY resend → pobrania_uzyte wraca do wartości sprzed kliknięcia
 *   (stary link zachowuje swój stan; kupujący nie dostaje ekstra pobrań na starym linku).
 * CZĘŚĆ 3 (klasa): automatyczne ponowienie przez cron (sklepag_domknij_zakup przy mail_blad)
 *   też rotuje token — i też musi zerować licznik. Runda 5: naprawa objęła egzemplarz, nie klasę.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';

if ( ! function_exists( 'sklepag_obsluz_resend' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin-zamowienie.php';
}

$sid          = 'cs_test_resend_limit_strażnik';
$stary_token  = hash( 'sha256', 'STARY-TOKEN-LIMIT' );
$stary_termin = gmdate( 'Y-m-d H:i:s', time() + 50 * HOUR_IN_SECONDS );
$limit        = (int) SKLEPAG_LIMIT_POBRAN;

$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak produktu w bazie)\n";
	exit( 2 );
}
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$pz} (stripe_session_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona,
		 produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas,
		 pobrania_limit, pobrania_uzyte, token_pobrania, link_wygasa, utworzono, zmieniono)
		 VALUES (%s,%d,%s,%d,%s,%s,%d,'PLN','mail_wyslany','zgoda',NOW(),%d,%d,%s,%s,NOW(),NOW())",
		$sid,
		(int) $produkt->id,
		$produkt->nazwa,
		(int) $produkt->cena_grosze,
		$produkt->plik_sciezka,
		'straznik-limit@example.test',
		(int) $produkt->cena_grosze,
		$limit,
		$limit, // limit WYCZERPANY — dokładnie przypadek z instrukcji
		$stary_token,
		$stary_termin
	)
);
$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
if ( function_exists( 'sklepag_opinia_zalazek' ) ) {
	sklepag_opinia_zalazek( $id, (int) $produkt->id );
}

wp_set_current_user( 1 );
$_GET['zamowienie']     = $id;
$_REQUEST['zamowienie'] = $id;
add_filter(
	'wp_redirect',
	static function ( $miejsce ) {
		throw new Exception( 'PRZEKIEROWANIE: ' . $miejsce );
	},
	99
);

// ---- CZĘŚĆ 1: udany resend zeruje licznik ----
add_filter( 'pre_wp_mail', '__return_true', 99 );
$_GET['_wpnonce']     = wp_create_nonce( 'sklepag_resend' );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
$przek1 = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$przek1 = $e->getMessage();
}
$po1 = $wpdb->get_row( $wpdb->prepare( "SELECT pobrania_uzyte, pobrania_limit, token_pobrania, status FROM {$pz} WHERE id=%d", $id ) );
echo 'STRAZNIK: przekierowanie=' . ( false !== strpos( $przek1, 'sklepag_resend=ok' ) ? 'ok (OK)' : $przek1 ) . "\n";
echo 'STRAZNIK: pobrania_uzyte_po_udanym=' . (int) $po1->pobrania_uzyte . " (OCZEKIWANE: 0)\n";
echo 'STRAZNIK: token_rotowany=' . ( $po1->token_pobrania !== $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass1 = ( 0 === (int) $po1->pobrania_uzyte && (int) $po1->pobrania_uzyte < (int) $po1->pobrania_limit && $po1->token_pobrania !== $stary_token && 'mail_wyslany' === $po1->status );
echo 'STRAZNIK: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - swiezy link nadal ma wyczerpany limit (410)' ) . "\n";
remove_filter( 'pre_wp_mail', '__return_true', 99 );

// ---- CZĘŚĆ 2 (kompensacja): nieudany resend przywraca licznik ----
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=%d WHERE id=%d", $stary_token, $stary_termin, 7, $id ) );
add_filter( 'pre_wp_mail', '__return_false', 99 );
$_GET['_wpnonce']     = wp_create_nonce( 'sklepag_resend' );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
$przek2 = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$przek2 = $e->getMessage();
}
$po2 = $wpdb->get_row( $wpdb->prepare( "SELECT pobrania_uzyte, token_pobrania, status FROM {$pz} WHERE id=%d", $id ) );
echo 'KONTROLA: przekierowanie=' . ( false !== strpos( $przek2, 'sklepag_resend=blad' ) ? 'blad (OK)' : $przek2 ) . "\n";
echo 'KONTROLA: pobrania_uzyte_po_nieudanym=' . (int) $po2->pobrania_uzyte . " (OCZEKIWANE: 7)\n";
echo 'KONTROLA: token_przywrocony=' . ( $po2->token_pobrania === $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass2 = ( 7 === (int) $po2->pobrania_uzyte && $po2->token_pobrania === $stary_token && 'mail_blad' === $po2->status );
echo 'KONTROLA: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - nieudany resend zmienil licznik pobran starego linku' ) . "\n";
remove_filter( 'pre_wp_mail', '__return_false', 99 );

// ---- CZĘŚĆ 3 (klasa, nie egzemplarz): AUTOMATYCZNE ponowienie przez cron też wydaje świeży link ----
// Nieudany resend zostawia mail_blad z wyczerpanym licznikiem; cron bierze go przez
// sklepag_domknij_zakup(), rotuje token — i ten link też musi mieć licznik od zera.
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'oplacona',NOW())", $sid, (int) $produkt->id ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_blad', mail_proby=1, token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=%d WHERE id=%d", $stary_token, $stary_termin, $limit, $id ) );
add_filter( 'pre_wp_mail', '__return_true', 99 );
$wynik3 = sklepag_domknij_zakup( array( 'id' => $sid, 'payment_status' => 'paid' ) );
remove_filter( 'pre_wp_mail', '__return_true', 99 );
$po3 = $wpdb->get_row( $wpdb->prepare( "SELECT pobrania_uzyte, token_pobrania, status FROM {$pz} WHERE id=%d", $id ) );
echo 'CRON: domkniecie=' . ( $wynik3 ? 'true' : 'false' ) . " (OCZEKIWANE: true)\n";
echo 'CRON: status_po=' . $po3->status . " (OCZEKIWANE: mail_wyslany)\n";
echo 'CRON: token_rotowany=' . ( $po3->token_pobrania !== $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'CRON: pobrania_uzyte_po_ponowieniu=' . (int) $po3->pobrania_uzyte . " (OCZEKIWANE: 0)\n";
$pass3 = ( $wynik3 && 'mail_wyslany' === $po3->status && $po3->token_pobrania !== $stary_token && 0 === (int) $po3->pobrania_uzyte );
echo 'CRON: WYNIK=' . ( $pass3 ? 'PASS' : 'FAIL - link z automatycznego ponowienia ma wyczerpany limit (410)' ) . "\n";

// ---- CZĘŚĆ 4 (kompensacja w CRONIE): pad poczty przy automatycznym ponowieniu NIE zabija starego linku ----
// Stan: mail_blad, stary token DZIAŁA (3 z 10 pobrań zużyte). Cron rotuje token, wp_mail pada
// (pre_wp_mail=false) — stary token/termin/licznik mają WRÓCIĆ (do 0.9.27: w bazie zostawał
// skrót, którego nie niósł żaden doręczony mail → kupujący tracił link na stałe).
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_blad', mail_proby=1, token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=3, mail_tresc='TRESC-DORECZONEGO-MAILA-K4' WHERE id=%d", $stary_token, $stary_termin, $id ) );
$op_przed = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", $id ) );
add_filter( 'pre_wp_mail', '__return_false', 99 );
$wynik4 = sklepag_domknij_zakup( array( 'id' => $sid, 'payment_status' => 'paid' ) );
remove_filter( 'pre_wp_mail', '__return_false', 99 );
$po4 = $wpdb->get_row( $wpdb->prepare( "SELECT pobrania_uzyte, token_pobrania, link_wygasa, status FROM {$pz} WHERE id=%d", $id ) );
echo 'CRON-PAD: domkniecie=' . ( $wynik4 ? 'true' : 'false' ) . " (OCZEKIWANE: false)\n";
echo 'CRON-PAD: status_po=' . $po4->status . " (OCZEKIWANE: mail_blad)\n";
echo 'CRON-PAD: token_przywrocony=' . ( $po4->token_pobrania === $stary_token ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'CRON-PAD: pobrania_uzyte_po=' . (int) $po4->pobrania_uzyte . " (OCZEKIWANE: 3)\n";
echo 'CRON-PAD: termin_przywrocony=' . ( $po4->link_wygasa === $stary_termin ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$tresc4 = (string) $wpdb->get_var( $wpdb->prepare( "SELECT mail_tresc FROM {$pz} WHERE id=%d", $id ) );
$op_po   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", $id ) );
echo 'CRON-PAD: mail_tresc_przywrocona=' . ( 'TRESC-DORECZONEGO-MAILA-K4' === $tresc4 ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'CRON-PAD: opinia_hash_przywrocony=' . ( '' === $op_przed || $op_po === $op_przed ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass4 = ( ! $wynik4 && 'mail_blad' === $po4->status && $po4->token_pobrania === $stary_token && 3 === (int) $po4->pobrania_uzyte && $po4->link_wygasa === $stary_termin && 'TRESC-DORECZONEGO-MAILA-K4' === $tresc4 && ( '' === $op_przed || $op_po === $op_przed ) );
echo 'CRON-PAD: WYNIK=' . ( $pass4 ? 'PASS' : 'FAIL - pad poczty w cronie zabil dzialajacy stary link' ) . "\n";

// ---- CZĘŚĆ 5 (CAS własności): proces B przejmuje wysyłkę stale-claimem — kompensacja A NIE nadpisuje stanu B ----
// Filtr pre_wp_mail symuluje proces B: W TRAKCIE wysyłki A podmienia w bazie token,
// mail_tresc i skrót opinii (jakby B odebrał claim po 5 min i sam rotował), po czym
// zwraca false (mail A pada). Kompensacja A ma NIE wejść: CAS `AND token_pobrania=<skrót A>`
// nie trafia (w bazie leży skrót B), więc `if ( $przywrocono > 0 )` (zakup.php) pomija
// wszystkie trzy zapisy. Na kodzie sprzed a2f9dc2 (mail_tresc i skrót opinii przywracane
// BEZ warunku) ten krok PADA na mail_tresc/opinii — kalibracja w opisie PR.
$token_b_hash = hash( 'sha256', 'TOKEN-PROCESU-B-CZESC5' );
$termin_b     = gmdate( 'Y-m-d H:i:s', time() + 72 * HOUR_IN_SECONDS );
$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_blad', mail_proby=1, token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=3, mail_tresc='TRESC-A-SPRZED' WHERE id=%d", $stary_token, $stary_termin, $id ) );
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_opinie SET token_hash='HASH-OPINII-A-PRZED' WHERE zamowienie_id=%d AND status='zalazek'", $id ) );
$op_jest5    = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", $id ) );
$symulacja_b = static function ( $pre ) use ( $wpdb, $pz, $sid, $id, $token_b_hash, $termin_b ) {
	// Proces B: rotacja + doręczenie — jego stan jest od teraz jedyny prawdziwy.
	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=0, mail_tresc='TRESC-B-DORECZONA' WHERE stripe_session_id=%s", $token_b_hash, $termin_b, $sid ) );
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_opinie SET token_hash='HASH-OPINII-B' WHERE zamowienie_id=%d AND status='zalazek'", $id ) );
	return false; // Mail A pada — wchodzi gałąź kompensacji A.
};
add_filter( 'pre_wp_mail', $symulacja_b, 99 );
$wynik5 = sklepag_domknij_zakup( array( 'id' => $sid, 'payment_status' => 'paid' ) );
remove_filter( 'pre_wp_mail', $symulacja_b, 99 );
$po5     = $wpdb->get_row( $wpdb->prepare( "SELECT token_pobrania, mail_tresc, status FROM {$pz} WHERE id=%d", $id ) );
$op_po5  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", $id ) );
echo 'CAS-B: domkniecie=' . ( $wynik5 ? 'true' : 'false' ) . " (OCZEKIWANE: false)\n";
echo 'CAS-B: token_nalezy_do_B=' . ( $po5->token_pobrania === $token_b_hash ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'CAS-B: mail_tresc_B_zachowana=' . ( 'TRESC-B-DORECZONA' === $po5->mail_tresc ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'CAS-B: opinia_hash_B_zachowany=' . ( '' === $op_jest5 || 'HASH-OPINII-B' === $op_po5 ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'CAS-B: status_po=' . $po5->status . " (OCZEKIWANE: mail_blad)\n";
$pass5 = ( ! $wynik5 && $po5->token_pobrania === $token_b_hash && 'TRESC-B-DORECZONA' === $po5->mail_tresc && ( '' === $op_jest5 || 'HASH-OPINII-B' === $op_po5 ) && 'mail_blad' === $po5->status );
echo 'CAS-B: WYNIK=' . ( $pass5 ? 'PASS' : 'FAIL - kompensacja stale-claima nadpisala stan procesu, ktory przejal wysylke' ) . "\n";

$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id=%d", $id ) );

$ok = ( $pass1 && $pass2 && $pass3 && $pass4 && $pass5 );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
