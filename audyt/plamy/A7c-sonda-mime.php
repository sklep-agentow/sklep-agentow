<?php
/**
 * SONDA AUDYTOWA A7c — co Segregator robi z TREŚCIĄ maila obcego człowieka.
 *
 * Metoda ta sama co przy A7b: nie czytamy, tylko URUCHAMIAMY. Zamiast atrapy
 * serwera jest atrapa WIADOMOŚCI — kilka maili zbudowanych tak, jak buduje je
 * zwykły program pocztowy, plus warianty brzegowe. Sonda woła prawdziwe
 * `segm_skrzynka_tresc_tekstem()` i prawdziwe `segm_wzorzec_pasuje()`,
 * i pokazuje, CO ZOBACZY REGUŁA właściciela.
 *
 * ⛔ Sonda MIERZY i DRUKUJE. Nie jest strażnikiem, nie należy do bramek,
 *    werdykt wystawia człowiek.
 *
 * PODSTAWIENIA (wypisane, bo bez WordPressa nie da się inaczej):
 *  - `wp_strip_all_tags()` — ścieżka skalarna PRZEPISANA CO DO ZNAKU z WordPressa
 *    7.1 działającego na stanowisku (`wp-includes/formatting.php`); pominięta
 *    tylko gałąź dla argumentu nieskalarnego, bo sonda podaje wyłącznie łańcuchy.
 *  - `__()` — tożsamość; wołane wyłącznie w tej pominiętej gałęzi.
 * Wszystko inne to funkcje produktu i PHP, bez atrap.
 *
 * Użycie: php audyt/plamy/A7c-sonda-mime.php
 */

define( 'ABSPATH', __DIR__ );

function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
	$text = strip_tags( $text );
	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}
	return trim( $text );
}

require dirname( __DIR__, 2 ) . '/wtyczki/segregator-maila/includes/skrzynka.php';
require dirname( __DIR__, 2 ) . '/wtyczki/segregator-maila/includes/silnik.php';

/** Zwykły mail: nagłówki + treść, tak jak dostaje je klient IMAP. */
function a7c_mail( $naglowki, $tresc ) {
	return array( 'naglowki' => $naglowki, 'tresc' => $tresc );
}

$SLOWO = 'faktura';   // słowo, którego szuka reguła właściciela
$b64   = static function ( $s ) { return chunk_split( base64_encode( $s ), 76, "\r\n" ); };

$tekst_widoczny = "Dzien dobry,\r\nw zalaczniku Faktura nr 7/2026.\r\nPozdrawiam";

$scenariusze = array(

	// KONTROLA DODATNIA — najzwyklejszy mail, reguła MUSI trafić.
	'A. text/plain 7bit (kontrola dodatnia)' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n",
		$tekst_widoczny
	),

	// base64 w JEDNEJ linii, bez znaku końca linii na końcu.
	'B. text/plain base64, jedna linia bez CRLF' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n",
		rtrim( $b64( $tekst_widoczny ), "\r\n" )
	),

	// base64 w wielu liniach, zakończone CRLF (tak wysyła typowy klient).
	'C. text/plain base64, wiele linii z CRLF' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n",
		$b64( str_repeat( "Tresc wypelniajaca. ", 12 ) . $tekst_widoczny )
	),

	// multipart/alternative — tak wygląda WIĘKSZOŚĆ prawdziwej poczty.
	'D. multipart/alternative, czesc tekstowa base64' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: multipart/alternative; boundary=\"gr4nica\"\r\n\r\n",
		"--gr4nica\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
			. $b64( $tekst_widoczny )
			. "--gr4nica\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
			. $b64( '<p>' . $tekst_widoczny . '</p>' )
			. "--gr4nica--\r\n"
	),

	// multipart, ale część tekstowa czytelna wprost.
	'E. multipart/alternative, czesc tekstowa 7bit' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: multipart/alternative; boundary=\"gr4nica\"\r\n\r\n",
		"--gr4nica\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n"
			. $tekst_widoczny . "\r\n--gr4nica--\r\n"
	),

	// quoted-printable — druga droga kodowania z RFC 2045.
	'F. text/plain quoted-printable' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n",
		quoted_printable_encode( $tekst_widoczny )
	),

	// charset, ktory NIE jest zestawem znakow, tylko kodowaniem transportowym
	// z listy mbstring — nadawca wybiera go sam.
	'G. charset=BASE64 (nazwa z mb_list_encodings)' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/plain; charset=BASE64\r\nContent-Transfer-Encoding: 7bit\r\n\r\n",
		$tekst_widoczny
	),

	// WEJSCIE ZLOSLIWE 1: mnostwo niedomknietych znacznikow <script> — wzorzec
	// wp_strip_all_tags ma tu nawrocic po calym tekscie dla kazdego otwarcia.
	'H. 4 kB niedomknietych <script> (czas)' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n",
		str_repeat( '<script a=', 400 ) . ' Faktura nr 7/2026'
	),

	// WEJSCIE ZLOSLIWE 2: quoted-printable z samych miekkich zlaman.
	'I. quoted-printable z samych zlaman (czas)' => a7c_mail(
		"From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n",
		str_repeat( "=\r\n", 2000 ) . 'Faktura nr 7/2026'
	),

	// ZWYKLE ZDANIE HANDLOWE z ostrym nawiasem PRZED slowem reguly.
	// (Pierwsza wersja tego scenariusza szukala slowa, ktorego w mailu NIE BYLO —
	// „faktura" wobec „fakture" — i dala falszywy alarm. Slowo musi byc doslowne.)
	'J. text/plain, "< 500" przed slowem reguly' => a7c_mail(
		"From: klient@example.com\r\nSubject: Wycena\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n",
		"Dzien dobry,\r\nbudzet < 500 zl.\r\nProsze o faktura pro forma.\r\nPozdrawiam"
	),

	// NIEDOMKNIETY znacznik przed slowem reguly: "<piatek" (znak mniejszosci
	// bezposrednio przed litera) — strip_tags widzi tu poczatek znacznika.
	'K. text/plain, "<piatek" przed slowem reguly' => a7c_mail(
		"From: klient@example.com\r\nSubject: Wycena\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n",
		"Dzien dobry,\r\ntermin <piatek.\r\nProsze o faktura pro forma.\r\nPozdrawiam"
	),
);

printf( "%-46s %8s %10s  %s\n", 'SCENARIUSZ', 'DLUGOSC', 'REGULA', 'POCZATEK TEGO, CO ZOBACZY REGULA' );
echo str_repeat( '-', 132 ), "\n";
$trafien = 0;
foreach ( $scenariusze as $nazwa => $m ) {
	$t0      = microtime( true );
	$wynik   = segm_skrzynka_tresc_tekstem( $m['tresc'], $m['naglowki'] );
	$czas    = microtime( true ) - $t0;
	$pasuje  = segm_wzorzec_pasuje( mb_strtolower( $wynik ), $SLOWO );
	$trafien += $pasuje ? 1 : 0;
	printf(
		"%-46s %8d %10s  %s\n",
		$nazwa,
		strlen( $wynik ),
		$pasuje ? 'TRAFIA' : 'NIE TRAFIA',
		str_replace( array( "\r", "\n" ), ' ', mb_substr( $wynik, 0, 62 ) )
	);
	if ( $czas > 0.5 ) {
		printf( "%-46s %s\n", '', sprintf( '⚠ czas dekodowania %.2f s', $czas ) );
	}
}
echo str_repeat( '-', 132 ), "\n";
printf( "Regula \"tresc zawiera %s\" trafia w %d z %d scenariuszy.\n", $SLOWO, $trafien, count( $scenariusze ) );
echo "We WSZYSTKICH slowo jest w mailu widoczne dla czlowieka.\n";
