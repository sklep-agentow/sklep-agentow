<?php
// SONDA E7 — czy tekst "Odpisz na tego maila" ma dokad wrocic.
// Zero zapisu: opcje podmieniane filtrem pre_option_*, wysylka przerwana filtrem pre_wp_mail.
$zlap = null;
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$zlap ) {
	$GLOBALS['sonda_zlap'] = $atts;
	return true; // udajemy sukces, nic nie wychodzi na SMTP
}, 10, 2 );

function sonda_case( $kontakt, $nadawca ) {
	$f_k = function () use ( $kontakt ) { return $kontakt; };
	$f_n = function () use ( $nadawca ) { return $nadawca; };
	add_filter( 'pre_option_sklepag_email_kontaktowy', $f_k );
	add_filter( 'pre_option_sklepag_nadawca_email', $f_n );
	unset( $GLOBALS['sonda_zlap'] );
	sklepag_wyslij_mail_sklepu( 'kupujacy@example.test', 'temat', "tresc\n", '<p>tresc</p>' );
	$a = $GLOBALS['sonda_zlap'];
	$naglowki = is_array( $a['headers'] ) ? implode( ' | ', $a['headers'] ) : (string) $a['headers'];
	$stopka = sklepag_stopka_sprzedawcy_tekst();
	remove_filter( 'pre_option_sklepag_email_kontaktowy', $f_k );
	remove_filter( 'pre_option_sklepag_nadawca_email', $f_n );
	printf( "  kontakt=[%s] nadawca=[%s]\n    naglowki: %s\n    Reply-To obecny: %s\n    stopka ma 'Reklamacje i faktury': %s\n",
		$kontakt, $nadawca, $naglowki,
		( false !== stripos( $naglowki, 'reply-to' ) ? 'TAK' : 'NIE' ),
		( false !== strpos( $stopka, 'Reklamacje i faktury' ) ? 'TAK' : 'NIE' ) );
}

echo "STAN FAKTYCZNY OPCJI NA STOSIE:\n";
printf( "  sklepag_email_kontaktowy = [%s]\n", (string) get_option( 'sklepag_email_kontaktowy', '' ) );
printf( "  sklepag_nadawca_email    = [%s]\n", (string) get_option( 'sklepag_nadawca_email', '' ) );
printf( "  sklepag_nadawca_nazwa    = [%s]\n", (string) get_option( 'sklepag_nadawca_nazwa', '' ) );
printf( "  sklepag_firma_nazwa      = [%s]\n", (string) get_option( 'sklepag_firma_nazwa', '' ) );
printf( "  sklepag_firma_adres      = [%s]\n", str_replace( "\n", ' / ', (string) get_option( 'sklepag_firma_adres', '' ) ) );
printf( "  sklepag_firma_nip        = [%s]\n", (string) get_option( 'sklepag_firma_nip', '' ) );

echo "\nA) kontakt PUSTY, nadawca typu noreply (przypadek badany):\n";
sonda_case( '', 'noreply@sklep.test' );
echo "\nB) kontakt USTAWIONY (kontrola dodatnia — sonda MUSI zobaczyc Reply-To):\n";
sonda_case( 'kontakt@sklep.test', 'noreply@sklep.test' );

echo "\nC) ile razy tekst maila mowi kupujacemu 'odpisz na tego maila':\n";
$prod = (object) array( 'nazwa' => 'ChatBot LIVE', 'slug' => 'chatbot-live' );
$zam  = (object) array( 'id' => 1, 'kwota_grosze' => 14900, 'waluta' => 'PLN', 'zgoda_tresc' => 'zgoda', 'zgoda_czas' => '2026-09-01 00:00:00', 'utworzono' => '2026-09-01 00:00:00', 'dane_faktury' => '' );
$t = sklepag_zbuduj_mail( $prod, $zam, 'http://x/link', 'http://x/opinia' );
printf( "  tekstowa wersja: %d\n", preg_match_all( '/odpisz na tego maila/iu', $t ) );
$h = sklepag_zbuduj_mail_html( $prod, $zam, 'http://x/link', 'http://x/opinia' );
printf( "  wersja HTML:     %d\n", preg_match_all( '/odpisz na tego maila/iu', $h ) );
