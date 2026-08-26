<?php
/**
 * Test padający — Ł-02 (runda A, uzupełniający): sklepag_dociagnij_zalegle()
 * (includes/zakup.php) ma DWIE pętle wołające Stripe. Pętla 1 (sesje
 * wp_sklepag_sesje_checkout w stanie 'utworzona') na WP_Error z
 * sklepag_stripe_pobierz_sesje() robi WYŁĄCZNIE `continue` — ZERO wpisu w
 * wp_sklepag_zdarzenia. Pętla 2 (zamówienia utknięte w oplacone/mail_w_toku/
 * mail_blad) na TĘ SAMĄ awarię Stripe loguje `sklepag_zdarzenie(null,
 * 'reconciliation_blad', ...)`. Asymetria = awaria cicha na jednej z DWÓCH
 * dróg tej samej funkcji cron.
 *
 * Uruchomienie: docker exec -u www-data probny_wp wp --path=/var/www/html eval-file <ta_sciezka>
 * WYMAGA: opcja sklepag_stripe_klucz_sekretny = zły klucz (sk_test_zly — stan
 * zastany probny_wp tej rundy, koordynatorka).
 *
 * Wyjście z RZECZYWISTEGO uruchomienia (26.08.2026, probny_wp, po ręcznej
 * reprodukcji identycznych kroków SQL+wp eval — patrz `dowod`/`wyjscie_wykonania`
 * w raporcie L-2.json, cel Ł-02):
 * "PRZED: liczba_zdarzen=3"
 * "PO sklepag_dociagnij_zalegle(): liczba_zdarzen=4 (przybyło DOKŁADNIE 1 —
 *  z pętli 2, dla cs_test_recon_loop2)"
 * "stan sesji cs_test_recon_loop1 PO cronie: utworzona (BEZ ZMIANY — nikt nie
 *  wie, że próba w ogóle się odbyła i zawiodła)"
 * "WYNIK=FAIL: pętla 1 przełknęła WP_Error bez śladu w dzienniku."
 */

global $wpdb;
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$pzd = $wpdb->prefix . 'sklepag_zdarzenia';

// Setup: sesja "utworzona" starsza niż granica 120 s (pętla 1) + zamówienie
// utknięte w mail_blad starsze niż granica (pętla 2) — jak w raporcie.
$sid1 = 'cs_test_L02b_' . wp_generate_password( 6, false, false );
$sid2 = 'cs_test_L02b2_' . wp_generate_password( 6, false, false );

$wpdb->query( $wpdb->prepare( "INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,1,'utworzona', DATE_SUB(NOW(), INTERVAL 300 SECOND))", $sid1 ) );
$wpdb->query( $wpdb->prepare(
	"INSERT INTO {$pz} (stripe_session_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona, produkt_plik_kupiony, email_kupujacego, kwota_grosze, waluta, status, token_pobrania, link_wygasa, pobrania_limit, pobrania_uzyte, mail_proby, utworzono, zmieniono) VALUES (%s,1,'x',9900,'/tmp/x','x@example.com',9900,'PLN','mail_blad',%s,DATE_ADD(NOW(),INTERVAL 3 DAY),10,0,1,DATE_SUB(NOW(), INTERVAL 300 SECOND),DATE_SUB(NOW(), INTERVAL 300 SECOND))",
	$sid2, hash( 'sha256', 'x' . $sid2 )
) );

$klucz = get_option( 'sklepag_stripe_klucz_sekretny' );
if ( '' === (string) $klucz || 'sk_test_zly' !== $klucz ) {
	echo "PADA_TEST: PRZERWANO - klucz Stripe w opcjach nie jest znanym zlym kluczem testowym (oczekiwano sk_test_zly, obecny stan srodowiska inny) - test wymaga stanu zastanego rundy A.\n";
	exit( 2 );
}

$przed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pzd}" );

sklepag_dociagnij_zalegle(); // PRODUKCYJNA funkcja, bez modyfikacji.

$po = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pzd}" );
$stan_sesji1 = $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$ps} WHERE stripe_session_id=%s", $sid1 ) );
$ma_wpis_dla_petli1 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pzd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $sid1 ) . '%' ) );
$ma_wpis_dla_petli2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pzd} WHERE opis LIKE %s", '%' . $wpdb->esc_like( $sid2 ) . '%' ) );

echo "PADA_TEST: zdarzenia_przed=$przed zdarzenia_po=$po\n";
echo "PADA_TEST: stan_sesji_petla1_po_cronie=$stan_sesji1 (OCZEKIWANE do wykrycia bledu: utworzona, BEZ zadnego wpisu opisujacego awarie)\n";
echo "PADA_TEST: wpis_w_dzienniku_dla_petli1(sesje_utworzona)=$ma_wpis_dla_petli1 (OCZEKIWANE ABY BYLO ODPORNE: >=1; DZIS: 0)\n";
echo "PADA_TEST: wpis_w_dzienniku_dla_petli2(zamowienia_utkniete)=$ma_wpis_dla_petli2 (loguje poprawnie, kontrola)\n";

if ( 0 === $ma_wpis_dla_petli1 && $ma_wpis_dla_petli2 >= 1 ) {
	echo "PADA_TEST: WYNIK=FAIL - petla reconciliation dla sesji 'utworzona' polyka blad Stripe calkowicie po cichu (0 wpisow), podczas gdy petla dla zamowien utknietych loguje poprawnie ten sam typ bledu. Asymetria potwierdzona.\n";
	exit( 1 );
}
echo "PADA_TEST: WYNIK=PASS (obie petle logowaly poprawnie)\n";
exit( 0 );
