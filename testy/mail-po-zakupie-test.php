<?php
/**
 * Mail po zakupie — test-strażnik treści i nagłówków, kod wyjścia 1 przy porażce.
 *
 * Co groziło (audyt jakości 25.08): audyty sprawdzały, CZY mail wychodzi, nie CO zawiera.
 * Kupujący zaznacza przy zakupie zgodę na natychmiastowe dostarczenie treści cyfrowej
 * (utrata prawa odstąpienia, art. 38 ustawy o prawach konsumenta), sklep zapisuje ją
 * w zamówieniu — ale mail potwierdzający jej nie powtarzał, choć potwierdzenie umowy na
 * trwałym nośniku ma ją zawierać (dyrektywa 2011/83/UE art. 8 ust. 7 lit. b). Do tego
 * „odpisz na tego maila" szło na adres NADAWCY (bez Reply-To na mail kontaktowy właściciela),
 * a o 14-dniowej gwarancji ze strony produktu mail milczał.
 *
 * Strażnik pilnuje:
 *  A. treść: zgoda (dosłownie ta z zamówienia) + data, link, gwarancja z danych produktu,
 *     brak akapitu gwarancji, gdy produkt jej nie ma; brak akapitu zgody, gdy zamówienie
 *     jej nie ma (stare zamówienia sprzed zgody) — mail nie ma prawa się wywalić,
 *  B. nagłówki: Reply-To = mail kontaktowy właściciela, gdy ustawiony; brak, gdy pusty
 *     (nic nie wysyłamy — `pre_wp_mail` zwraca wynik bez PHPMailera).
 *
 * Uruchomienie:
 *   docker cp testy/mail-po-zakupie-test.php strona_wp_wordpress:/tmp/mz.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/mz.php
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

if ( ! function_exists( 'sklepag_zbuduj_mail' ) || ! function_exists( 'sklepag_wyslij_mail_sklepu' ) ) {
	echo "  BLAD funkcje maila niedostepne (wtyczka sklep-agentow nieaktywna?)\n";
	exit( 1 );
}

// --- A. TREŚĆ ---
$produkt        = new stdClass();
$produkt->nazwa = 'ChatBot LIVE';
$produkt->slug  = 'chatbot-live';

$zam               = new stdClass();
$zam->kwota_grosze = 14900;
$zam->waluta       = 'PLN';
$zam->zgoda_tresc  = SKLEPAG_ZGODA_TRESC;
$zam->zgoda_czas   = '2026-08-25 09:30:00'; // UTC, jak zapisuje zakup.
$link              = 'https://example.test/wp-admin/admin-post.php?action=sklepag_pobierz&z=1&t=abc';

$tresc = sklepag_zbuduj_mail( $produkt, $zam, $link, '' );
sprawdz( 'A1 mail zawiera DOSŁOWNĄ treść zgody z zamówienia', false !== strpos( $tresc, SKLEPAG_ZGODA_TRESC ) );
sprawdz( 'A2 mail zawiera datę zgody (czas lokalny z UTC zamówienia)', false !== strpos( $tresc, get_date_from_gmt( '2026-08-25 09:30:00', 'd.m.Y H:i' ) ) );
sprawdz( 'A3 mail zawiera link pobrania', false !== strpos( $tresc, $link ) );
$t_prod = sklepag_tresc_produktu( 'chatbot-live' );
sprawdz( 'A4 mail zawiera gwarancję z danych produktu', ! empty( $t_prod['gwarancja'] ) && false !== strpos( $tresc, $t_prod['gwarancja'] ) );
sprawdz( 'A5 blok zgody stoi PRZED linkiem pobrania (najpierw prawo, potem plik)', strpos( $tresc, 'POTWIERDZENIE TWOJEJ ZGODY' ) < strpos( $tresc, 'POBIERZ SWÓJ PRODUKT' ) );

$bez_slugu        = new stdClass();
$bez_slugu->nazwa = 'Produkt bez danych sprzedażowych';
$stare            = clone $zam;
$stare->zgoda_tresc = '';
$stare->zgoda_czas  = null;
$tresc2 = sklepag_zbuduj_mail( $bez_slugu, $stare, $link, '' );
sprawdz( 'A6 brak zgody w zamówieniu = brak akapitu zgody, mail dalej się buduje', false === strpos( $tresc2, 'POTWIERDZENIE TWOJEJ ZGODY' ) && false !== strpos( $tresc2, $link ) );
sprawdz( 'A7 produkt bez gwarancji = brak akapitu gwarancji', false === strpos( $tresc2, 'GWARANCJA:' ) );

// --- B. NAGŁÓWKI (bez realnej wysyłki) ---
$przechwycone = array();
$zwrot_pre    = function ( $wynik, $atts ) use ( &$przechwycone ) {
	$przechwycone[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $zwrot_pre, 10, 2 );

$poprzedni = get_option( 'sklepag_email_kontaktowy', '' );
update_option( 'sklepag_email_kontaktowy', 'wlasciciel@example.test' );
sklepag_wyslij_mail_sklepu( 'kupujacy@example.test', 'test', 'x' );
$naglowki = isset( $przechwycone[0]['headers'] ) ? (array) $przechwycone[0]['headers'] : array();
sprawdz( 'B1 Reply-To = mail kontaktowy właściciela', in_array( 'Reply-To: wlasciciel@example.test', $naglowki, true ), wp_json_encode( $naglowki ) );

update_option( 'sklepag_email_kontaktowy', '' );
sklepag_wyslij_mail_sklepu( 'kupujacy@example.test', 'test', 'x' );
$naglowki2 = isset( $przechwycone[1]['headers'] ) ? (array) $przechwycone[1]['headers'] : array();
sprawdz( 'B2 pusty mail kontaktowy = brak Reply-To (odpowiedź idzie do nadawcy)', 0 === count( array_filter( $naglowki2, static fn( $h ) => 0 === stripos( $h, 'Reply-To' ) ) ), wp_json_encode( $naglowki2 ) );

// Sprzątanie po sobie: opcja jak była, filtr zdjęty.
update_option( 'sklepag_email_kontaktowy', $poprzedni );
remove_filter( 'pre_wp_mail', $zwrot_pre, 10 );

echo $GLOBALS['sklepag_test_bledy'] ? 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n" : "WSZYSTKO OK\n";
exit( $GLOBALS['sklepag_test_bledy'] ? 1 : 0 );
