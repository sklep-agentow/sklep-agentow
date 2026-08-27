<?php
/**
 * STRAŻNIK REGRESJI — pamięć nietrafień omijana i DWA pytania do Stripe w jednym żądaniu.
 *
 * Wada: `sklepag_zamowienie_po_platnosci` zwracała gołe `null` dla dwóch zupełnie różnych
 * sytuacji — „to nie nasza płatność" i „nie udało się zapytać Stripe". Żeby je rozróżnić,
 * `sklepag_zwrot_nieprzypisany` pytała Stripe DRUGI raz, bezwarunkowo. Skutki dwa:
 *  - pamięć nietrafień (transient `sklepag_niepowiazana_*`) nic nie oszczędzała — cudzy
 *    zwrot na wspólnym koncie Stripe i tak generował zapytanie przy każdym powtórzeniu;
 *  - przy awarii łączności jedno żądanie webhooka brało 15 s + 15 s, ponad regułę
 *    „timeout 15 s, JEDNA próba" z `includes/stripe.php:4-5` i ponad punkt briefingu
 *    „suma prób < limit PHP hostingu".
 *
 * Test NIE puka do sieci: `pre_http_request` przechwytuje żądanie przed wysłaniem, liczy je
 * i podaje odpowiedź. Klucz Stripe podmieniamy na atrapę (bez niej `sklepag_stripe_request`
 * kończy się na braku klucza i nie doszłoby do żadnego żądania) i przywracamy na końcu.
 *
 * PASS = cudza płatność pyta Stripe RAZ i zostaje zapamiętana; powtórka nie pyta wcale;
 *        awaria łączności pyta RAZ, prosi Stripe o ponowienie i NIE zapamiętuje nietrafienia.
 * FAIL = którakolwiek ścieżka pyta Stripe więcej razy, niż deklaruje dokumentacja i komentarz.
 */

global $wpdb;
$pd = $wpdb->prefix . 'sklepag_zdarzenia';

if ( ! function_exists( 'sklepag_zaksieguj_zwrot' ) || ! function_exists( 'sklepag_zamowienie_po_platnosci' ) ) {
	echo "WYNIK=FAIL - brak mechanizmu ksiegowania zwrotu\n";
	echo "RAZEM: WYNIK=FAIL\n";
	exit( 1 ); // wyjście awaryjne też musi nieść kod błędu, inaczej CI zobaczy zero
}

$pi        = 'pi_test_straznik_obcy';
$klucz_pud = 'sklepag_niepowiazana_' . md5( $pi );
$opcja_kl  = 'sklepag_stripe_klucz_sekretny';

// Własne warunki wstępne: stos jest wspólny, więc nie zakładamy ani klucza, ani czystej pamięci.
$klucz_przed = get_option( $opcja_kl, '' );
update_option( $opcja_kl, 'atrapa-straznika-bez-sieci' ); // celowo NIE w kształcie klucza Stripe

$licznik = 0;
$tryb    = 'brak_sesji';
$filtr   = static function ( $wstepna, $args, $url ) use ( &$licznik, &$tryb ) {
	if ( false === strpos( (string) $url, 'checkout/sessions' ) ) {
		return $wstepna; // cudze żądanie na tym stosie — nie nasza sprawa i nie nasz licznik
	}
	++$licznik;
	if ( 'awaria' === $tryb ) {
		return new WP_Error( 'http_request_failed', 'symulowana awaria lacznosci ze Stripe' );
	}
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( array( 'object' => 'list', 'data' => array() ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $filtr, 10, 3 );

$posprzataj = static function () use ( $wpdb, $pd, $pi, $klucz_pud ) {
	delete_transient( $klucz_pud );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $pi ) . '%' ) );
};
$zdarzenie = array( 'id' => 're_test_obcy', 'payment_intent' => $pi, 'amount' => 1000, 'status' => 'succeeded' );

/* ---------- 1) KONTROLA: pierwsze zetknięcie z cudzą płatnością ---------- */
// Przypadek BEZ wady — ścieżka ma prawo zapytać Stripe raz. Gdyby ten przypadek też padał,
// strażnik krzyczałby „zawsze", a nie „przy tej wadzie".
$posprzataj();
$licznik = 0;
$tryb    = 'brak_sesji';
$wynik1  = sklepag_zaksieguj_zwrot( 'refund.created', $zdarzenie );
$pytan1  = $licznik;
$pamiec1 = get_transient( $klucz_pud );
echo "PIERWSZY_RAZ: pytan_do_stripe=$pytan1 (OCZEKIWANE: 1)\n";
echo 'PIERWSZY_RAZ: zapamietane_jako_obce=' . ( 'nie' === $pamiec1 ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'PIERWSZY_RAZ: webhook_zamkniety=' . ( true === $wynik1 ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass1 = ( 1 === $pytan1 && 'nie' === $pamiec1 && true === $wynik1 );
echo 'PIERWSZY_RAZ: WYNIK=' . ( $pass1 ? 'PASS' : 'FAIL - pierwsze zetkniecie nie pyta dokladnie raz albo nie zapamietuje' ) . "\n";

/* ---------- 2) POWTÓRKA cudzego zwrotu — pamięć nietrafień ma OSZCZĘDZIĆ pytanie ---------- */
$licznik = 0;
$wynik2  = sklepag_zaksieguj_zwrot( 'refund.created', $zdarzenie );
echo "POWTORKA_OBCEGO: pytan_do_stripe=$licznik (OCZEKIWANE: 0 — pamiec nietrafien trzyma dobe)\n";
echo 'POWTORKA_OBCEGO: webhook_zamkniety=' . ( true === $wynik2 ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
$pass2 = ( 0 === $licznik && true === $wynik2 );
echo 'POWTORKA_OBCEGO: WYNIK=' . ( $pass2 ? 'PASS' : 'FAIL - pamiec nietrafien omijana, cudzy zwrot i tak pyta Stripe' ) . "\n";

/* ---------- 3) AWARIA łączności — JEDNA próba, prośba o ponowienie, nic nie zapamiętane ---------- */
$posprzataj();
$licznik = 0;
$tryb    = 'awaria';
$wynik3  = sklepag_zaksieguj_zwrot( 'refund.created', $zdarzenie );
$pytan3  = $licznik;
$pamiec3 = get_transient( $klucz_pud );
echo "AWARIA: pytan_do_stripe=$pytan3 (OCZEKIWANE: 1 — regula 'timeout 15 s, JEDNA proba')\n";
echo 'AWARIA: prosi_o_ponowienie=' . ( false === $wynik3 ? 'TAK' : 'NIE' ) . " (OCZEKIWANE: TAK)\n";
echo 'AWARIA: zapamietane_jako_obce=' . ( false === $pamiec3 ? 'NIE' : 'TAK' ) . " (OCZEKIWANE: NIE — awaria to nie werdykt)\n";
$pass3 = ( 1 === $pytan3 && false === $wynik3 && false === $pamiec3 );
echo 'AWARIA: WYNIK=' . ( $pass3 ? 'PASS' : 'FAIL - awaria lacznosci pyta Stripe dwa razy w jednym zadaniu' ) . "\n";

remove_filter( 'pre_http_request', $filtr, 10 );
$posprzataj();
if ( '' === $klucz_przed ) {
	delete_option( $opcja_kl );
} else {
	update_option( $opcja_kl, $klucz_przed );
}

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik krzyczy FAIL, a CI
// widzi zero i przepuszcza wadę (klasa 11: nazwa bramki szersza niż to, co sprawdza).
$straznik_ok = ( $pass1 && $pass2 && $pass3 );
echo 'RAZEM: WYNIK=' . ( $straznik_ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $straznik_ok ? 0 : 1 );
