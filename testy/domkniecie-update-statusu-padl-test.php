<?php
/**
 * STRAŻNIK REGRESJI — klasa K-M1 (Mariusz, egzamin 6.09.2026, DUŻY): wynik przejścia stanu
 * `oczekuje → oplacone` w sklepag_domknij_zakup() był IGNOROWANY, a kontrola pod nim sprawdzała
 * ISTNIENIE wiersza, nie STATUS. Gdy sam UPDATE padł (błąd bazy), wiersz istniał w `oczekuje`,
 * sesja checkout dostawała 'oplacona' PRZED dowodem statusu, więc reconciliation
 * (bierze wyłącznie stan='utworzona') już jej nie widziało; po wyczerpaniu ponowień Stripe
 * zamówienie zostawało w `oczekuje` z pobranymi pieniędzmi i bez maila do właściciela.
 * Bliźniak klasy 152 (INSERT w tej samej funkcji sprawdzony po recenzji, UPDATE pod nim nie) = klasa 8.
 *
 * Awaria JEDNEGO zapytania symulowana filtrem `query` (WordPress: wpdb::query() przepuszcza
 * każde zapytanie przez apply_filters('query')): tylko UPDATE przestawiający status na
 * 'oplacone' zostaje podmieniony na zapytanie do nieistniejącej tabeli → $wpdb->query() = false.
 * Nic innego w bazie nie zmienia się; filtr zdejmowany natychmiast po wywołaniu.
 * Maile przechwytuje filtr `pre_wp_mail` (WP ≥ 5.7): liczymy próby, nic nie wychodzi.
 *
 * Uruchomienie:
 *   docker cp testy/domkniecie-update-statusu-padl-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * PASS = przy padniętym UPDATE: funkcja zwraca false, sesja ZOSTAJE 'utworzona', jest ślad
 *        `domkniecie_blad_zapisu` i próba maila do właściciela; ponowienie (Stripe/reconciliation)
 *        domyka zakup; padnięty UPDATE przy zamówieniu domkniętym przez inny silnik = ślad bez alarmu;
 *        zdrowy zakup działa jak dotąd.
 * FAIL = sesja 'oplacona' przy zamówieniu w 'oczekuje' (zakup uwięziony) albo brak śladu/maila.
 */

global $wpdb;
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty LIMIT 1" );
if ( ! $produkt ) {
	echo "WYNIK=BLAD-SRODOWISKA (brak produktu w bazie)\n";
	exit( 2 );
}

$maile = 0;
add_filter(
	'pre_wp_mail',
	static function ( $wynik, $atts ) use ( &$maile ) {
		$maile++;
		return true; // mail „wysłany”, nic nie wychodzi na zewnątrz
	},
	10,
	2
);

$zbuduj_sesje = static function ( $sid ) use ( $wpdb, $ps, $pz, $produkt ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s, %d, 'utworzona', NOW())", $sid, (int) $produkt->id ) );
	return array(
		'id'               => $sid,
		'payment_status'   => 'paid',
		'amount_total'     => (int) $produkt->cena_grosze,
		'currency'         => 'pln',
		'payment_intent'   => 'pi_' . $sid,
		'customer_details' => array( 'email' => 'straznik-update@example.test' ),
	);
};

$psuj_update_statusu = static function ( $q ) {
	if ( false !== strpos( $q, "SET status='oplacone'" ) && false !== strpos( $q, "status='oczekuje'" ) ) {
		return 'UPDATE tabela_ktorej_nie_ma_straznik_km1 SET x=1';
	}
	return $q;
};

/* ---------- 1) AWARIA UPDATE: wiersz jest w `oczekuje`, sesja NIE MOŻE dostać 'oplacona' ---------- */
$sid_a  = 'cs_test_straznik_update_padl';
$sesja  = $zbuduj_sesje( $sid_a );
$maile  = 0;
// Ogranicznik maili do właściciela: jeden mail na rodzaj awarii na godzinę (transient). Drugi bieg tej próby
// w ciągu godziny widziałby 0 maili NIE przez wadę kodu, tylko przez własny poprzedni bieg (klasa 140: próba
// zależna od stanu sprzed) — zerujemy ogranicznik przed pomiarem i po sprzątaniu.
$ogranicznik = 'sklepag_powiadom_' . md5( 'domkniecie_blad_zapisu' );
delete_transient( $ogranicznik );
add_filter( 'query', $psuj_update_statusu );
$ok_a = sklepag_domknij_zakup( $sesja );
remove_filter( 'query', $psuj_update_statusu );

$stan_a   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid_a ) );
$status_a = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$pz} WHERE stripe_session_id=%s", $sid_a ) );
$slad     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE typ='domkniecie_blad_zapisu' AND opis LIKE %s", $wpdb->esc_like( $sid_a ) . '%' ) );
$maile_a  = $maile;

echo "AWARIA_UPDATE: zwrocil=" . ( $ok_a ? 'true' : 'false' ) . " (OCZEKIWANE: false — Stripe ma ponowic)\n";
echo "AWARIA_UPDATE: status_zamowienia=$status_a (OCZEKIWANE: oczekuje — UPDATE padl)\n";
echo "AWARIA_UPDATE: sesja_stan=$stan_a (OCZEKIWANE: utworzona — reconciliation musi ja widziec)\n";
echo "AWARIA_UPDATE: slad_w_dzienniku=$slad (OCZEKIWANE: >=1)\n";
echo "AWARIA_UPDATE: maile_do_wlasciciela=$maile_a (OCZEKIWANE: >=1)\n";
$pass_a = ( false === $ok_a && 'oczekuje' === $status_a && 'utworzona' === $stan_a && $slad >= 1 && $maile_a >= 1 );
echo 'AWARIA_UPDATE: WYNIK=' . ( $pass_a ? 'PASS' : 'FAIL - zamowienie uwiezione w oczekuje: sesja oznaczona albo brak sladu/maila' ) . "\n";

/* ---------- 2) PONOWIENIE (Stripe retry / reconciliation) domyka TEN SAM zakup ---------- */
$ok_b     = sklepag_domknij_zakup( $sesja );
$stan_b   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid_a ) );
$status_b = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$pz} WHERE stripe_session_id=%s", $sid_a ) );
$ile_b    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE stripe_session_id=%s", $sid_a ) );
echo "PONOWIENIE: sesja_stan=$stan_b (OCZEKIWANE: oplacona)\n";
echo "PONOWIENIE: status_zamowienia=$status_b (OCZEKIWANE: nie 'oczekuje')\n";
echo "PONOWIENIE: zamowien=$ile_b (OCZEKIWANE: 1 — bez duplikatu)\n";
$pass_b = ( 'oplacona' === $stan_b && 'oczekuje' !== $status_b && 1 === $ile_b );
echo 'PONOWIENIE: WYNIK=' . ( $pass_b ? 'PASS' : 'FAIL - ponowienie nie domyka zakupu po awarii' ) . "\n";

/* ---------- 2b) UPDATE tej próby pada, ale status przestawił już INNY silnik (zamówienie jest w mail_wyslany po scenariuszu 2) ----------
 * Oczekiwane: ZERO maili-alarmów i ZERO wpisów domkniecie_blad_zapisu (byłyby sprzeczne z odczytanym statusem),
 * JEDEN wpis domkniecie_przejscie_inny_silnik, funkcja idzie dalej (nie false), zamówienie zostaje w mail_wyslany. */
$maile   = 0;
delete_transient( $ogranicznik ); // scenariusz 1 ustawił ogranicznik — bez zerowania asercja „0 maili” byłaby ślepa (r6 DROBNA 3)
$blad_p  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE typ='domkniecie_blad_zapisu' AND opis LIKE %s", $wpdb->esc_like( $sid_a ) . '%' ) );
add_filter( 'query', $psuj_update_statusu );
$ok_d = sklepag_domknij_zakup( $sesja );
remove_filter( 'query', $psuj_update_statusu );
$blad_po   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE typ='domkniecie_blad_zapisu' AND opis LIKE %s", $wpdb->esc_like( $sid_a ) . '%' ) );
$inny      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pd} WHERE typ='domkniecie_przejscie_inny_silnik' AND opis LIKE %s", $wpdb->esc_like( $sid_a ) . '%' ) );
$status_d  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$pz} WHERE stripe_session_id=%s", $sid_a ) );
$alarm_pr  = ( false !== get_transient( $ogranicznik ) ); // ogranicznik ustawia się przy KAŻDEJ próbie alarmu, nawet bez wp_mail
echo "INNY_SILNIK: alarm_probowany=" . ( $alarm_pr ? 'tak' : 'nie' ) . " (OCZEKIWANE: nie)\n";
echo "INNY_SILNIK: zwrocil=" . ( $ok_d ? 'true' : 'false' ) . " (OCZEKIWANE: true — zamowienie jest w porzadku)\n";
echo "INNY_SILNIK: maile_do_wlasciciela=$maile (OCZEKIWANE: 0)\n";
echo "INNY_SILNIK: nowe_wpisy_blad_zapisu=" . ( $blad_po - $blad_p ) . " (OCZEKIWANE: 0)\n";
echo "INNY_SILNIK: wpisy_przejscie_inny_silnik=$inny (OCZEKIWANE: 1)\n";
echo "INNY_SILNIK: status=$status_d (OCZEKIWANE: mail_wyslany)\n";
$pass_d = ( true === $ok_d && 0 === $maile && ! $alarm_pr && $blad_po === $blad_p && 1 === $inny && 'mail_wyslany' === $status_d );
echo 'INNY_SILNIK: WYNIK=' . ( $pass_d ? 'PASS' : 'FAIL - alarm albo blad przy zamowieniu, ktore inny silnik juz domknal' ) . "\n";

/* ---------- 3) KONTROLA: zdrowy zakup bez filtra ---------- */
$sid_c  = 'cs_test_straznik_update_zdrowy';
$sesja2 = $zbuduj_sesje( $sid_c );
$ok_c   = sklepag_domknij_zakup( $sesja2 );
$stan_c = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid_c ) );
$ile_c  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE stripe_session_id=%s", $sid_c ) );
echo "KONTROLA: sesja_stan=$stan_c (OCZEKIWANE: oplacona)\n";
echo "KONTROLA: zamowien=$ile_c (OCZEKIWANE: 1)\n";
$pass_c = ( 'oplacona' === $stan_c && 1 === $ile_c );
echo 'KONTROLA: WYNIK=' . ( $pass_c ? 'PASS' : 'FAIL - naprawa zepsula zdrowy zakup' ) . "\n";

/* ---------- sprzątanie ---------- */
foreach ( array( $sid_a, $sid_c ) as $s ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pz} WHERE stripe_session_id=%s", $s ) );
	if ( $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE zamowienie_id=%d", $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE stripe_session_id=%s", $s ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id=%s", $s ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $s ) . '%' ) );
}

delete_transient( $ogranicznik ); // nie zostawiamy blokady na godzinę dla prawdziwej awarii na stosie
$straznik_ok = ( $pass_a && $pass_b && $pass_c && $pass_d );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
