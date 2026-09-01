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

if ( ! function_exists( 'sklepag_zbuduj_mail' ) || ! function_exists( 'sklepag_wyslij_mail_sklepu' ) || ! function_exists( 'sklepag_zbuduj_mail_html' ) ) {
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
// Kolumna `utworzono` jest w bazie NOT NULL — atrapa musi być tego samego rodzaju co
// prawdziwe zamówienie, inaczej test mierzy uboższy obiekt niż produkcja.
$zam->utworzono    = '2026-08-25 09:29:00';
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
// A8: mail podawał wyłącznie chwilę ZGODY — kupujący nie znajdował w potwierdzeniu
// odpowiedzi na „kiedy to kupiłem". Data zakupu stoi teraz obok, osobno od zgody,
// bo obie niosą inną treść (jedna handlową, druga prawną).
sprawdz( 'A8 mail podaje datę zakupu, osobno od chwili zgody', false !== strpos( $tresc, 'Data zakupu: ' ) );

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

// --- C. WERSJA HTML (0.9.24) + tekst z numerem, fakturą i stopką sprzedawcy ---
// Atrapa zamówienia z id jak w bazie (numer zamówienia = id wiersza) i dane sprzedawcy
// z Ustawień — przywracane na końcu.
// `false` = opcji nie było (wspólny stos warsztatowy) — sprzątanie ją KASUJE, nie zostawia pustej.
$poprz_firma = array(
	'sklepag_firma_nazwa' => get_option( 'sklepag_firma_nazwa', false ),
	'sklepag_firma_adres' => get_option( 'sklepag_firma_adres', false ),
	'sklepag_firma_nip'   => get_option( 'sklepag_firma_nip', false ),
);
// Sprzątanie w shutdown: fatal albo exit między zapisem a końcem testu zostawiały fikcyjnego
// sprzedawcę na WSPÓLNYM stosie (recenzja #220 r1: test padł na linii z nową funkcją, opcje zostały).
$GLOBALS['sklepag_test_sprzatanie'] = static function () use ( $poprz_firma, $poprzedni ) {
	foreach ( $poprz_firma as $opcja => $wartosc ) {
		if ( false === $wartosc ) {
			delete_option( $opcja );
		} else {
			update_option( $opcja, $wartosc );
		}
	}
	update_option( 'sklepag_email_kontaktowy', $poprzedni );
};
register_shutdown_function( $GLOBALS['sklepag_test_sprzatanie'] );
update_option( 'sklepag_firma_nazwa', 'Firma Testowa Sp. z o.o.' );
update_option( 'sklepag_firma_adres', "ul. Próbna 7\n00-002 Warszawa" );
update_option( 'sklepag_firma_nip', '5260250274' );
update_option( 'sklepag_email_kontaktowy', 'wlasciciel@example.test' );

$zam_id     = clone $zam;
$zam_id->id = 4321;
$link_op    = 'https://example.test/wp-admin/admin-post.php?action=sklepag_ocen&z=4321&t=def';
$tekst      = sklepag_zbuduj_mail( $produkt, $zam_id, $link, $link_op );
$html       = sklepag_zbuduj_mail_html( $produkt, $zam_id, $link, $link_op );

sprawdz( 'C1 tekst: numer zamówienia = id wiersza', false !== strpos( $tekst, 'Numer zamówienia: #4321' ) );
sprawdz( 'C2 tekst: stopka sprzedawcy z nazwą, adresem i NIP-em', false !== strpos( $tekst, "SPRZEDAWCA:\nFirma Testowa Sp. z o.o.\nul. Próbna 7\n00-002 Warszawa\nNIP 5260250274" ) );
sprawdz( 'C2b tekst i html: zdanie o zgodzie bezosobowe w OBU wersjach (bez „zaznaczyłeś”)', false !== strpos( $tekst, 'Zgoda zaznaczona przy zakupie' ) && false !== strpos( $html, 'Zgoda zaznaczona przy zakupie' ) && false === strpos( $tekst . $html, 'zaznaczyłeś' ) );
sprawdz( 'C3 tekst: dopisek o fakturze z terminem 3 miesięcy (art. 106b ust. 3 VAT), gdy brak danych', false !== strpos( $tekst, 'FAKTURA:' ) && false !== strpos( $tekst, '3 miesięcy' ) );
// C3b (0.9.25): dane do faktury podane przy zakupie — w OBU wersjach zamiast „chcesz fakturę?”.
$zam_fv               = clone $zam_id;
$zam_fv->dane_faktury = 'Firma Próbna Sp. z o.o., NIP 5260250274, ul. Testowa 5, 00-950 Warszawa';
$tekst_fv             = sklepag_zbuduj_mail( $produkt, $zam_fv, $link, '' );
$html_fv              = sklepag_zbuduj_mail_html( $produkt, $zam_fv, $link, '' );
sprawdz( 'C3b tekst i html: dane do faktury z zamówienia w obu wersjach, bez „chcesz fakturę?”', false !== strpos( $tekst_fv, "DANE DO FAKTURY (podane przy zakupie):\n" . $zam_fv->dane_faktury ) && false !== strpos( $html_fv, esc_html( $zam_fv->dane_faktury ) ) && false === strpos( $tekst_fv, 'FAKTURA:' ) && false === strpos( $html_fv, 'Chcesz fakturę?' ) );
sprawdz( 'C4 html: dosłowna treść zgody (esc_html nie zmienia polskich znaków)', false !== strpos( $html, esc_html( SKLEPAG_ZGODA_TRESC ) ) );
sprawdz( 'C5 html: link pobrania w przycisku ORAZ jako tekst zapasowy (2 wystąpienia)', 2 === substr_count( $html, esc_url( $link ) ) );
sprawdz( 'C6 html: okładka produktu z alt = nazwa produktu', false !== strpos( $html, sklepag_okladka_url( 'chatbot-live' ) ) && false !== strpos( $html, 'alt="ChatBot LIVE"' ) );
sprawdz( 'C7 html: numer zamówienia, stopka sprzedawcy i NIP', false !== strpos( $html, '#4321' ) && false !== strpos( $html, 'Firma Testowa Sp. z o.o.' ) && false !== strpos( $html, 'NIP 5260250274' ) );
sprawdz( 'C8 html: gwarancja z danych produktu', false !== strpos( $html, esc_html( $t_prod['gwarancja'] ) ) );
sprawdz( 'C9 html: link do opinii, gdy podany', false !== strpos( $html, esc_url( $link_op ) ) );
sprawdz( 'C10 html: bez <style> w <head> (Gmail wycina) i poniżej 60 KB (Gmail obcina od 102 KB)', false === stripos( $html, '<style' ) && strlen( $html ) < 60000, strlen( $html ) . ' B' );
/* ⛔ Do 0.9.28 ten warunek asertował napis BEZWARUNKOWO — a napis był w kodzie na sztywno.
   Po wpięciu trybu (P-3) taki test zieleniałby na kłamstwie: przy kluczu produkcyjnym mail
   NIE ma prawa mówić o niepobranej płatności. Pytamy więc o TRYB, nie o obecność tekstu,
   i sprawdzamy OBIE strony warunku — pełny pomiar wszystkich ścieżek robi
   `testy/tryb-testowy-z-klucza-test.php`. */
$zdanie_trybu = 'TRYB TESTOWY — płatność nie została pobrana';
sprawdz(
	'C11 html: zdanie o trybie testowym dokładnie wtedy, gdy sklep jest w trybie testowym',
	sklepag_tryb_testowy() === ( false !== strpos( $html, $zdanie_trybu ) ),
	sklepag_tryb_testowy() ? 'stos w trybie testowym — zdanie ma być' : 'stos na kluczu produkcyjnym — zdania ma nie być'
);
sprawdz( 'C11b html i tekst mówią o trybie TO SAMO (dwie wersje maila nie mają prawa się różnić o pieniądze)', ( false !== strpos( $html, $zdanie_trybu ) ) === ( false !== strpos( $tekst, $zdanie_trybu ) ) );
$html2 = sklepag_zbuduj_mail_html( $bez_slugu, $stare, $link, '' );
sprawdz( 'C12 html: bez zgody/gwarancji/opinii mail dalej się buduje z linkiem', false !== strpos( $html2, esc_url( $link ) ) && false === strpos( $html2, 'Potwierdzenie Twojej zgody' ) && false === strpos( $html2, 'Gwarancja</p>' ) && false === strpos( $html2, 'Oceń swój zakup' ) );

// --- D. WYSYŁKA: nagłówek HTML tylko przy wersji HTML; wersja tekstowa nie wisi w globalu ---
sklepag_wyslij_mail_sklepu( 'kupujacy@example.test', 'test', 'tekst', '<p>html</p>' );
$n3 = isset( $przechwycone[2]['headers'] ) ? (array) $przechwycone[2]['headers'] : array();
sprawdz( 'D1 z HTML: nagłówek Content-Type text/html i treść = HTML', in_array( 'Content-Type: text/html; charset=UTF-8', $n3, true ) && '<p>html</p>' === $przechwycone[2]['message'], wp_json_encode( $n3 ) );
sprawdz( 'D2 po wysyłce global wersji tekstowej i hak phpmailer_init zdjęte', empty( $GLOBALS['sklepag_mail_alt_tekst'] ) && false === has_action( 'phpmailer_init', 'sklepag_phpmailer_wersja_tekstowa' ) );
sklepag_wyslij_mail_sklepu( 'kupujacy@example.test', 'test', 'tekst' );
$n4 = isset( $przechwycone[3]['headers'] ) ? (array) $przechwycone[3]['headers'] : array();
sprawdz( 'D3 bez HTML: brak nagłówka Content-Type (mail tekstowy jak do 0.9.23)', 0 === count( array_filter( $n4, static fn( $h ) => 0 === stripos( $h, 'Content-Type' ) ) ) && 'tekst' === $przechwycone[3]['message'], wp_json_encode( $n4 ) );

// Sprzątanie po sobie: opcje jak były (ta sama procedura biegnie też w shutdown), filtr zdjęty.
$GLOBALS['sklepag_test_sprzatanie']();
remove_filter( 'pre_wp_mail', $zwrot_pre, 10 );

echo $GLOBALS['sklepag_test_bledy'] ? 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n" : "WSZYSTKO OK\n";
exit( $GLOBALS['sklepag_test_bledy'] ? 1 : 0 );
