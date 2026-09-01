<?php
/**
 * Mail po zakupie — wersja HTML (0.9.24).
 *
 * Do 0.9.23 mail był czystym tekstem (`wp_mail` domyślnie wysyła text/plain): bez nazwy sklepu
 * w nagłówku, bez obrazu produktu, bez przycisku — w skrzynce kupującego wyglądał jak
 * komunikat z systemu, nie jak potwierdzenie ze sklepu. Ta wersja jedzie razem z tekstem
 * (multipart/alternative, patrz sklepag_wyslij_mail_sklepu): treść obu wersji jest ta sama,
 * HTML dokłada tylko wygląd.
 *
 * Wzorzec (zwiad 30.08 po maila-potwierdzeniach Stripe, Shopify, Lemon Squeezy, Apple):
 * karta 600 px na jasnym tle · nagłówek tekstowy z nazwą sklepu, nie hero-grafika · przy
 * produkcie CYFROWYM przycisk „Pobierz" jest głównym elementem (kupujący najpierw chce plik,
 * potem czyta kwotę) · numer zamówienia wyeksponowany osobno · jeden kolor akcentu ·
 * stopka z danymi prawnymi, bez sprzedawania. Brand ze strony sklepu: limonka #bfff38 na
 * czerni #08090b (ten sam przycisk „Kup teraz"), papier #f5f5f5, stal #8f929c.
 *
 * Reguły techniczne klientów poczty: układ na tabelach, CSS wyłącznie inline (Gmail wycina
 * <style> z <head>), szerokość 600 px, obrazy z pełnym adresem (plugins_url) i atrybutem alt —
 * przy wyłączonych obrazkach tekst alt niesie nazwę produktu. Cały mail < 20 KB (Gmail
 * obcina powyżej 102 KB).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Składa wersję HTML maila po zakupie. Dane wejściowe identyczne jak w sklepag_zbuduj_mail
 * (ta sama funkcja dla wysyłki automatycznej i „Wyślij ponownie").
 *
 * @param object $produkt     Produkt (nazwa, slug).
 * @param object $zam         Zamówienie (id, kwota_grosze, waluta, zgoda_tresc, zgoda_czas, utworzono).
 * @param string $link        Link pobrania.
 * @param string $link_opinii Link do opinii (puste = bez sekcji).
 * @return string HTML.
 */
function sklepag_zbuduj_mail_html( $produkt, $zam, $link, $link_opinii = '' ) {
	$s        = sklepag_sprzedawca();
	$sklep    = wp_specialchars_decode( (string) get_option( 'sklepag_nadawca_nazwa', '' ), ENT_QUOTES );
	$sklep    = '' !== trim( $sklep ) ? $sklep : $s['nazwa'];
	$dni      = (int) round( SKLEPAG_LINK_GODZIN / 24 );
	$numer    = ! empty( $zam->id ) ? '#' . (int) $zam->id : '';
	$kupiono  = ! empty( $zam->utworzono ) ? get_date_from_gmt( get_gmt_from_date( $zam->utworzono ), 'd.m.Y H:i' ) : '';
	$kwota    = number_format( $zam->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $zam->waluta;
	$okladka  = ( ! empty( $produkt->slug ) && function_exists( 'sklepag_okladka_url' ) ) ? sklepag_okladka_url( $produkt->slug ) : '';
	$kontakt  = (string) get_option( 'sklepag_email_kontaktowy', '' );
	$kontakt  = ( '' !== $kontakt && is_email( $kontakt ) ) ? $kontakt : '';
	$strona   = home_url( '/' );
	$strona_t = preg_replace( '#^https?://#', '', untrailingslashit( $strona ) );

	$gwarancja = '';
	if ( ! empty( $produkt->slug ) && function_exists( 'sklepag_tresc_produktu' ) ) {
		$t = sklepag_tresc_produktu( $produkt->slug );
		if ( $t && ! empty( $t['gwarancja'] ) ) {
			$gwarancja = (string) $t['gwarancja'];
		}
	}

	// Paleta i typografia (inline, bo klienci poczty wycinają <style>).
	$f    = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;";
	$mono = "font-family:'SF Mono',Menlo,Consolas,'Liberation Mono',monospace;";
	$tusz = '#08090b';
	$stal = '#5c606b'; // Stal z brandu (#8f929c) przyciemniona: na białym 4,5:1 to minimum dla tekstu.
	$lime = '#bfff38';
	$pap  = '#f5f5f5';
	$lin  = '#e4e5e9';

	$h = array();

	$h[] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
	$h[] = '<html lang="pl" xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
	$h[] = '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">';
	$h[] = '<title>' . esc_html( 'Twój zakup: ' . $produkt->nazwa ) . '</title></head>';
	$h[] = '<body style="margin:0;padding:0;background:' . $pap . ';' . $f . '">';
	// Preheader: tekst widoczny w liście skrzynki obok tematu, w treści ukryty.
	$h[] = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px;">'
		. esc_html( trim( ( '' !== $numer ? 'Zamówienie ' . $numer . ' · ' : '' ) . $produkt->nazwa . ' · link do pobrania w środku' ) ) . '</div>';
	$h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $pap . ';"><tr><td align="center" style="padding:24px 12px;">';
	$h[] = '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">';

	// Nagłówek: kreska w kolorze akcentu + czarny pas z nazwą sklepu i etykietą.
	$h[] = '<tr><td style="height:4px;background:' . $lime . ';font-size:0;line-height:0;">&nbsp;</td></tr>';
	$h[] = '<tr><td style="background:' . $tusz . ';padding:18px 32px;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
		. '<td style="' . $f . 'color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-0.01em;">' . esc_html( $sklep ) . '</td>'
		. '<td align="right" style="' . $mono . 'color:' . $lime . ';font-size:11px;letter-spacing:0.16em;text-transform:uppercase;">[ potwierdzenie zakupu' . ( '' !== $numer ? ' · ' . esc_html( $numer ) : '' ) . ' ]</td>'
		. '</tr></table></td></tr>';

	// Okładka produktu (ta sama, którą strona pokazuje w podglądach linków).
	if ( '' !== $okladka ) {
		$h[] = '<tr><td style="background:' . $tusz . ';font-size:0;line-height:0;"><img src="' . esc_url( $okladka ) . '" width="600" alt="' . esc_attr( $produkt->nazwa ) . '" style="display:block;width:100%;max-width:600px;height:auto;border:0;"></td></tr>';
	}

	// Treść główna.
	$h[] = '<tr><td style="padding:32px 32px 8px 32px;">';
	$h[] = '<h1 style="margin:0 0 8px 0;' . $f . 'color:' . $tusz . ';font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-0.02em;">Dziękujemy za zakup!</h1>';
	$h[] = '<p style="margin:0 0 24px 0;' . $f . 'color:' . $stal . ';font-size:16px;line-height:1.55;">' . esc_html( $produkt->nazwa ) . ' czeka na pobranie. Kliknij przycisk — link jest ważny ' . (int) $dni . ' dni i pozwala na ' . (int) SKLEPAG_LIMIT_POBRAN . ' pobrań.</p>';

	// Główny przycisk (produkt cyfrowy: pobranie przed kwotą).
	$h[] = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px 0;"><tr>'
		. '<td align="center" style="background:' . $lime . ';border-radius:8px;">'
		. '<a href="' . esc_url( $link ) . '" style="display:inline-block;padding:15px 28px;' . $f . 'color:' . $tusz . ';font-size:16px;font-weight:700;text-decoration:none;letter-spacing:-0.01em;">Pobierz ' . esc_html( $produkt->nazwa ) . ' &rarr;</a>'
		. '</td></tr></table>';
	$h[] = '<p style="margin:0 0 28px 0;' . $f . 'color:' . $stal . ';font-size:12px;line-height:1.5;">Przycisk nie działa? Skopiuj adres do przeglądarki:<br><a href="' . esc_url( $link ) . '" style="color:' . $stal . ';word-break:break-all;">' . esc_html( $link ) . '</a></p>';

	// Karta zamówienia.
	$wiersz = static function ( $etykieta, $wartosc, $ostatni = false ) use ( $f, $stal, $tusz, $lin ) {
		$obr = $ostatni ? '' : 'border-bottom:1px solid ' . $lin . ';';
		return '<tr><td style="padding:10px 0;' . $obr . $f . 'color:' . $stal . ';font-size:13px;width:44%;">' . esc_html( $etykieta ) . '</td>'
			. '<td style="padding:10px 0;' . $obr . $f . 'color:' . $tusz . ';font-size:14px;font-weight:600;text-align:right;">' . $wartosc . '</td></tr>';
	};
	$h[]    = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $pap . ';border-radius:10px;margin:0 0 24px 0;"><tr><td style="padding:8px 20px;">';
	$h[]    = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
	if ( '' !== $numer ) {
		$h[] = $wiersz( 'Numer zamówienia', esc_html( $numer ) );
	}
	$h[] = $wiersz( 'Produkt', esc_html( $produkt->nazwa ) );
	if ( '' !== $kupiono ) {
		$h[] = $wiersz( 'Data zakupu', esc_html( $kupiono ) );
	}
	/* ⛔ Zdanie o niepobranej płatności idzie do kupującego na trwałym nośniku — na kluczu
		produkcyjnym byłoby po prostu nieprawdą. Ten sam warunek co w wersji tekstowej maila. */
	$dopisek_trybu = sklepag_tryb_testowy()
		? '<br><span style="' . $f . 'font-weight:400;font-size:12px;color:#8a5a00;">TRYB TESTOWY — płatność nie została pobrana</span>'
		: '';
	$h[]           = $wiersz( 'Kwota', esc_html( $kwota ) . $dopisek_trybu, true );
	$h[]           = '</table></td></tr></table>';

	// Potwierdzenie zgody (trwały nośnik — dyrektywa 2011/83/UE art. 8 ust. 7 lit. b).
	if ( ! empty( $zam->zgoda_tresc ) ) {
		$kiedy = ! empty( $zam->zgoda_czas ) ? get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) : '';
		$h[]   = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;"><tr><td style="border-left:3px solid ' . $lime . ';padding:4px 0 4px 16px;">'
			. '<p style="margin:0 0 4px 0;' . $mono . 'color:' . $stal . ';font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">Potwierdzenie Twojej zgody</p>'
			. '<p style="margin:0;' . $f . 'color:' . $tusz . ';font-size:13px;line-height:1.55;">' . esc_html( $zam->zgoda_tresc ) . '</p>'
			. ( '' !== $kiedy ? '<p style="margin:4px 0 0 0;' . $f . 'color:' . $stal . ';font-size:12px;">Zgoda zaznaczona przy zakupie ' . esc_html( $kiedy ) . '.</p>' : '' )
			. '</td></tr></table>';
	}

	// Instrukcja w trzech krokach.
	$kroki = array(
		'Pobierz paczkę ZIP z przycisku powyżej.',
		'W kokpicie WordPressa: Wtyczki → Dodaj → Wyślij wtyczkę na serwer → wybierz ZIP.',
		'Aktywuj i działaj — pełna instrukcja jest w paczce.',
	);
	$h[]   = '<p style="margin:0 0 10px 0;' . $mono . 'color:' . $stal . ';font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">Instrukcja w skrócie</p>';
	$h[]   = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">';
	foreach ( $kroki as $i => $krok ) {
		$h[] = '<tr><td width="32" valign="top" style="padding:0 0 10px 0;' . $mono . 'color:' . $tusz . ';font-size:14px;font-weight:700;">' . ( $i + 1 ) . '.</td>'
			. '<td valign="top" style="padding:0 0 10px 0;' . $f . 'color:' . $tusz . ';font-size:14px;line-height:1.5;">' . esc_html( $krok ) . '</td></tr>';
	}
	$h[] = '</table>';

	// Gwarancja (ta sama, którą obiecuje strona produktu).
	if ( '' !== $gwarancja ) {
		$h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid ' . $lin . ';border-radius:10px;margin:0 0 24px 0;"><tr><td style="padding:16px 20px;">'
			. '<p style="margin:0 0 6px 0;' . $mono . 'color:' . $stal . ';font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">Gwarancja</p>'
			. '<p style="margin:0;' . $f . 'color:' . $tusz . ';font-size:13px;line-height:1.55;">' . esc_html( $gwarancja ) . ' Aby z niej skorzystać, odpisz na tego maila.</p>'
			. '</td></tr></table>';
	}

	// Opinia (jednorazowy link, drugi plan — bez konkurowania z przyciskiem pobrania).
	if ( '' !== $link_opinii ) {
		$h[] = '<p style="margin:0 0 4px 0;' . $mono . 'color:' . $stal . ';font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">Oceń swój zakup</p>';
		$h[] = '<p style="margin:0 0 24px 0;' . $f . 'color:' . $tusz . ';font-size:14px;line-height:1.55;">Gdy przetestujesz bota, zostaw opinię — to 2 minuty: '
			. '<a href="' . esc_url( $link_opinii ) . '" style="color:' . $tusz . ';font-weight:600;">napisz opinię</a>.<br>'
			. '<span style="color:' . $stal . ';font-size:12px;">Link do opinii jest jednorazowy i ważny ' . (int) SKLEPAG_OPINIA_DNI . ' dni. Opinię publikujemy po potwierdzeniu zakupu i akceptacji.</span></p>';
	}

	// Faktura i pytania.
	if ( ! empty( $zam->dane_faktury ) ) {
		$h[] = '<p style="margin:0 0 6px 0;' . $f . 'color:' . $tusz . ';font-size:14px;line-height:1.55;"><strong>Dane do faktury (podane przy zakupie):</strong> ' . esc_html( $zam->dane_faktury ) . '<br><span style="color:' . $stal . ';font-size:12px;">Fakturę wystawimy na te dane i prześlemy osobno na ten adres e-mail.</span></p>';
	} else {
		$h[] = '<p style="margin:0 0 6px 0;' . $f . 'color:' . $tusz . ';font-size:14px;line-height:1.55;"><strong>Chcesz fakturę?</strong> Odpisz na tego maila w ciągu 3 miesięcy od zakupu i podaj dane firmy (nazwa, adres, NIP).</p>';
	}
	$h[] = '<p style="margin:0 0 8px 0;' . $f . 'color:' . $tusz . ';font-size:14px;line-height:1.55;">Masz pytanie? Odpisz na tego maila.</p>';
	$h[] = '</td></tr>';

	// Stopka: dane sprzedawcy (art. 21 ustawy o prawach konsumenta), bez treści sprzedażowych.
	$stopka = array( '<strong style="color:' . $tusz . ';">' . esc_html( $s['nazwa'] ) . '</strong>' );
	if ( '' !== $s['adres'] ) {
		$stopka[] = nl2br( esc_html( str_replace( array( "\r\n", "\r" ), "\n", $s['adres'] ) ) );
	}
	if ( '' !== $s['nip'] ) {
		$stopka[] = 'NIP ' . esc_html( $s['nip'] );
	}
	if ( '' !== $kontakt ) {
		$stopka[] = 'Reklamacje i faktury: <a href="mailto:' . esc_attr( $kontakt ) . '" style="color:' . $stal . ';">' . esc_html( $kontakt ) . '</a>';
	}
	$stopka[] = '<a href="' . esc_url( $strona ) . '" style="color:' . $stal . ';">' . esc_html( $strona_t ) . '</a>';
	$h[]      = '<tr><td style="padding:20px 32px 28px 32px;border-top:1px solid ' . $lin . ';">'
		. '<p style="margin:0 0 6px 0;' . $mono . 'color:' . $stal . ';font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">Sprzedawca</p>'
		. '<p style="margin:0;' . $f . 'color:' . $stal . ';font-size:12px;line-height:1.6;">' . implode( '<br>', $stopka ) . '</p>'
		. '<p style="margin:12px 0 0 0;' . $f . 'color:' . $stal . ';font-size:11px;line-height:1.5;">Ten mail jest potwierdzeniem zawarcia umowy na odległość i dowodem zakupu — zachowaj go.</p>'
		. '</td></tr>';

	$h[] = '</table></td></tr></table></body></html>';

	return implode( "\n", $h );
}
