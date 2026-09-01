<?php
// SONDA E7 — czy panel pokazuje wlascicielowi PRAWDZIWA godzine wygasniecia linku.
// Renderuje prawdziwy ekran "Szczegoly zamowienia" i wycina z niego wiersz "Pobrania".
global $wpdb;
require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-dashboard.php';
require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-zamowienie.php';
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $adm ? $adm[0]->ID : 1 );
$zam = $wpdb->get_row( "SELECT id, utworzono, link_wygasa FROM {$wpdb->prefix}sklepag_zamowienia WHERE link_wygasa IS NOT NULL ORDER BY id DESC LIMIT 1" );
if ( ! $zam ) { echo "BRAK ZAMOWIENIA Z LINKIEM — sonda nie ma na czym mierzyc\n"; exit( 2 ); }

$_GET['zamowienie'] = (int) $zam->id;
$_REQUEST['zamowienie'] = (int) $zam->id;
ob_start();
sklepag_admin_zamowienie();
$html = ob_get_clean();

if ( preg_match( '#<tr><td>Pobrania</td><td>(.*?)</td></tr>#s', $html, $m ) ) {
	echo "WIERSZ Z EKRANU PANELU: ", trim( wp_strip_all_tags( $m[1] ) ), "\n";
} else {
	echo "SONDA NIE ZNALAZLA WIERSZA — pomiar nieodbyty\n"; exit( 2 );
}
printf( "surowa kolumna link_wygasa (baza) : %s\n", $zam->link_wygasa );
printf( "ta sama chwila w czasie WITRYNY   : %s  (strefa %s, przesuniecie %+d h)\n",
	get_date_from_gmt( $zam->link_wygasa, 'Y-m-d H:i:s' ), wp_timezone_string(), (int) get_option( 'gmt_offset' ) );
printf( "zamowienie utworzono (czas WITRYNY): %s\n", $zam->utworzono );
printf( "roznica utworzono -> pokazane w panelu: %.1f h  (a link zyje %d h)\n",
	( strtotime( $zam->link_wygasa ) - strtotime( $zam->utworzono ) ) / 3600, (int) SKLEPAG_LINK_GODZIN );

// KONTROLA DODATNIA: czy w tym samym ekranie inne czasy SA w czasie witryny?
if ( preg_match( '#<tr><td>Utworzono</td><td>(.*?)</td></tr>#s', $html, $m2 ) ) {
	echo "kontrola — wiersz 'Utworzono' z ekranu: ", trim( wp_strip_all_tags( $m2[1] ) ), "\n";
}
$roznica = strtotime( get_date_from_gmt( $zam->link_wygasa, 'Y-m-d H:i:s' ) ) - strtotime( $zam->link_wygasa );
printf( "\nWERDYKT: panel pokazuje godzine o %d h wczesniejsza niz prawdziwe wygasniecie linku.\n", $roznica / 3600 );
