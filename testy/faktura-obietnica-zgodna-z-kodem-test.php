<?php
/**
 * Q-1 — sklep NIE obiecuje kupującemu firmowemu czegoś, czego nie robi.
 *
 * ⛔ CO GROZIŁO: formularz zakupu i mail po zakupie pisały „fakturę prześlemy osobno mailem"
 * i „Fakturę wystawimy na te dane i prześlemy osobno na ten adres e-mail" — a sklep faktur
 * NIE WYSTAWIA. Potwierdza to własna instrukcja właściciela („Faktura to Twoja robota,
 * nie sklepu. Sklep faktur nie wystawia"). To obietnica majątkowa wobec przedsiębiorcy,
 * niepokryta ani jedną linią kodu: nic w sklepie nie tworzy dokumentu, nie nadaje numeru,
 * nie wysyła go i nie przypomina o terminie.
 *
 * ⭐ NAPRAWA NIE BUDUJE WYSTAWIANIA FAKTUR — prostuje obietnicę. Tekst ma mówić prawdę
 * o tym, co sklep robi z tymi danymi: zapisuje je przy zamówieniu i pokazuje sprzedawcy.
 *
 * Ten strażnik pyta o OBIE strony: czy zniknęła obietnica ORAZ czy została funkcja
 * (dane do faktury nadal widoczne, zaproszenie dla kupującego bez danych nadal działa).
 * Sama nieobecność zdania byłaby zielona także wtedy, gdyby ktoś wyciął cały blok faktury.
 *
 * Uruchomienie:
 *   docker cp testy/faktura-obietnica-zgodna-z-kodem-test.php strona_wp_wordpress:/tmp/fa.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/fa.php
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
foreach ( array( 'sklepag_zbuduj_mail', 'sklepag_zbuduj_mail_html', 'sklepag_render_produkt' ) as $f ) {
	if ( ! function_exists( $f ) ) {
		echo "  PRZYRZAD: brak funkcji {$f}\n";
		exit( 2 );
	}
}

/* Zdania, których kupujący NIE MA PRAWA przeczytać, dopóki sklep faktur nie wystawia.
   Frazy dosłowne, nie zlepek „faktur" — słowo „faktura" ma prawo w tekstach zostać. */
$OBIETNICE = array(
	'Fakturę wystawimy',
	'fakturę wystawimy',
	'fakturę prześlemy',
	'Fakturę prześlemy',
	'prześlemy osobno na ten adres',
	'posłużą wyłącznie do wystawienia faktury',
);
function obietnice_w( $tekst, $lista ) {
	$zn = array();
	foreach ( $lista as $f ) {
		if ( false !== strpos( $tekst, $f ) ) {
			$zn[] = $f;
		}
	}
	return $zn;
}

$produkt        = new stdClass();
$produkt->nazwa = 'ChatBot LIVE';
$produkt->slug  = 'chatbot-live';
$zam                = new stdClass();
$zam->id            = 4321;
$zam->kwota_grosze  = 14900;
$zam->waluta        = 'PLN';
$zam->zgoda_tresc   = SKLEPAG_ZGODA_TRESC;
$zam->zgoda_czas    = '2026-09-01 09:30:00';
$zam->utworzono     = '2026-09-01 09:29:00';
$link               = 'https://example.test/pobierz?z=1';
$DANE               = 'Firma Próbna Sp. z o.o., NIP 5260250274, ul. Testowa 5, 00-950 Warszawa';

$zam_fv               = clone $zam;
$zam_fv->dane_faktury = $DANE;
$zam_bez              = clone $zam;
$zam_bez->dane_faktury = '';

$ekrany = array(
	'produkt'         => sklepag_render_produkt( 'chatbot-live' ),
	'mail-tekst-z-fv' => sklepag_zbuduj_mail( $produkt, $zam_fv, $link, '' ),
	'mail-html-z-fv'  => sklepag_zbuduj_mail_html( $produkt, $zam_fv, $link, '' ),
	'mail-tekst-bez'  => sklepag_zbuduj_mail( $produkt, $zam_bez, $link, '' ),
	'mail-html-bez'   => sklepag_zbuduj_mail_html( $produkt, $zam_bez, $link, '' ),
);

echo "== A. Zero obietnic, ktorych sklep nie realizuje ==\n";
foreach ( $ekrany as $nazwa => $tresc ) {
	$zn = obietnice_w( $tresc, $OBIETNICE );
	sprawdz( 'A[' . $nazwa . '] brak obietnicy wystawienia/przeslania faktury', empty( $zn ), empty( $zn ) ? '' : 'znalezione: ' . implode( ' | ', $zn ) );
}

echo "\n== B. Tekst MOWI, co sklep naprawde robi z danymi (kontrola dodatnia) ==\n";
/* ⛔ Bez tej sekcji „brak obietnicy" byloby zielone takze wtedy, gdyby ktos wyciac caly blok
   faktury — kupujacy firmowy nie dowiedzialby sie wtedy w ogole, co sie stalo z jego danymi. */
sprawdz( 'B1 formularz zakupu mowi, ze fakture wystawia SPRZEDAWCA poza sklepem', false !== strpos( $ekrany['produkt'], 'poza sklepem' ) );
sprawdz( 'B2 formularz zakupu nadal zaprasza do podania danych na firme', false !== strpos( $ekrany['produkt'], 'chcesz fakturę na firmę' ) );
sprawdz( 'B3 mail tekstowy z danymi: dane pokazane i nazwane', false !== strpos( $ekrany['mail-tekst-z-fv'], 'DANE DO FAKTURY (podane przy zakupie):' ) && false !== strpos( $ekrany['mail-tekst-z-fv'], $DANE ) );
sprawdz( 'B4 mail HTML z danymi: dane pokazane i nazwane', false !== strpos( $ekrany['mail-html-z-fv'], 'Dane do faktury (podane przy zakupie):' ) && false !== strpos( $ekrany['mail-html-z-fv'], esc_html( $DANE ) ) );
sprawdz( 'B5 mail tekstowy z danymi mowi, ze fakture wystawia sprzedawca poza sklepem', false !== strpos( $ekrany['mail-tekst-z-fv'], 'poza sklepem' ) );
sprawdz( 'B6 mail HTML z danymi mowi to samo', false !== strpos( $ekrany['mail-html-z-fv'], 'poza sklepem' ) );
/* Kupujacy MUSI wiedziec, gdzie sie upomniec, skoro sklep sam faktury nie wysle. */
sprawdz( 'B7 mail tekstowy z danymi mowi, gdzie sie upomniec', false !== strpos( $ekrany['mail-tekst-z-fv'], 'napisz' ) );
sprawdz( 'B8 mail HTML z danymi mowi, gdzie sie upomniec', false !== strpos( $ekrany['mail-html-z-fv'], 'napisz' ) );

echo "\n== C. Wariant BEZ danych do faktury — funkcja nietknieta ==\n";
sprawdz( 'C1 mail tekstowy bez danych nadal zaprasza i podaje termin 3 miesiecy', false !== strpos( $ekrany['mail-tekst-bez'], 'Chcesz fakturę?' ) && false !== strpos( $ekrany['mail-tekst-bez'], '3 miesięcy' ) );
sprawdz( 'C2 mail HTML bez danych nadal zaprasza i podaje termin 3 miesiecy', false !== strpos( $ekrany['mail-html-bez'], 'Chcesz fakturę?' ) && false !== strpos( $ekrany['mail-html-bez'], '3 miesięcy' ) );
sprawdz( 'C3 mail bez danych NIE pokazuje bloku danych do faktury', false === strpos( $ekrany['mail-tekst-bez'], 'DANE DO FAKTURY' ) && false === strpos( $ekrany['mail-html-bez'], 'Dane do faktury (podane' ) );

echo "\n" . ( $GLOBALS['sklepag_test_bledy'] ? 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n" : "WSZYSTKO OK\n" );
exit( $GLOBALS['sklepag_test_bledy'] ? 1 : 0 );
