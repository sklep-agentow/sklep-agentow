<?php
/**
 * SONDA AUDYTU H2 — czy REPLAY tego samego (podpisanego) webhooka na sesji JUZ DOMKNIETEJ
 * tworzy DRUGI mail z waznym linkiem pobrania.
 *
 * ZERO TRWALEJ ZMIANY: caly przebieg w START TRANSACTION ... ROLLBACK (wszystkie tabele InnoDB),
 * wysylka przechwycona filtrem `pre_wp_mail` (nic nie wychodzi do lapacza), zero ruchu do Stripe
 * (ladunek podstawiony recznie, `sklepag_domknij_zakup` nie pyta API).
 *
 * TRYB: argument 1 = "mutant" -> podklada wade w CAS przejecia wysylki (filtr `query` zdejmuje
 * warunek statusu), zeby udowodnic, ze sonda UMIE zwrocic 1. Bez argumentu = prawdziwy kod.
 *
 * Kody: 0 = replay NIE tworzy drugiego maila · 1 = TWORZY (albo token sie zrotowal) · 2 = nie zmierzylem
 */
global $wpdb;
$tryb   = ( ( isset( $args[0] ) && 'mutant' === $args[0] ) || 'mutant' === getenv( 'SONDA_TRYB' ) ) ? 'mutant' : 'prawdziwy';
// ⛔ Pierwsza wersja czytala $argv[1] — pod `wp eval-file` tam tego NIE MA, wiec sonda po cichu
// szla trybem 'prawdziwy' i kalibracja bylaby udawana. Zlapane pierwszym przebiegiem.
$pz     = $wpdb->prefix . 'sklepag_zamowienia';
$ps     = $wpdb->prefix . 'sklepag_sesje_checkout';

foreach ( array( 'sklepag_domknij_zakup', 'sklepag_wyslij_mail_sklepu' ) as $f ) {
	if ( ! function_exists( $f ) ) { echo "SONDA: brak funkcji $f — nie mam czego mierzyc\n"; exit( 2 ); }
}

$z = $wpdb->get_row( "SELECT z.* FROM $pz z JOIN $ps s ON s.stripe_session_id=z.stripe_session_id
    JOIN {$wpdb->prefix}sklepag_produkty p ON p.id=z.produkt_id
    WHERE z.status='mail_wyslany' ORDER BY z.id DESC LIMIT 1" );
if ( ! $z ) { echo "SONDA: brak zamowienia DOMKNIETEGO z zywa sesja i produktem\n"; exit( 2 ); }

printf( "TRYB: %s\n", $tryb );
printf( "zamowienie #%d · status=%s · token=%s… · link_wygasa=%s\n", $z->id, $z->status, substr( $z->token_pobrania, 0, 10 ), $z->link_wygasa );

// --- licznik prob wysylki; nic nie wychodzi ---
$GLOBALS['sonda_maile'] = 0;
add_filter( 'pre_wp_mail', function ( $null, $atts ) { ++$GLOBALS['sonda_maile']; return true; }, 10, 2 );

// --- MUTANT: zdejmij warunek statusu z CAS przejecia wysylki (zakup.php:936) ---
$mutacja = function ( $sql ) {
	if ( false !== strpos( $sql, "SET status='mail_w_toku'" ) && false !== strpos( $sql, "status IN ('oplacone','mail_blad')" ) ) {
		$GLOBALS['sonda_mutacja_weszla'] = true;
		return str_replace( "AND status IN ('oplacone','mail_blad')", '', $sql );
	}
	return $sql;
};
$GLOBALS['sonda_mutacja_weszla'] = false;
if ( 'mutant' === $tryb ) { add_filter( 'query', $mutacja ); }

$ladunek = array(
	'id'               => $z->stripe_session_id,
	'payment_status'   => 'paid',
	'amount_total'     => (int) $z->kwota_grosze,
	'currency'         => strtolower( (string) $z->waluta ),
	'customer_details' => array( 'email' => (string) $z->email_kupujacego ),
);

$wpdb->query( 'START TRANSACTION' );
$wynik = sklepag_domknij_zakup( $ladunek );   // <-- REPLAY tego samego zdarzenia
$po    = $wpdb->get_row( $wpdb->prepare( "SELECT status, token_pobrania, link_wygasa, mail_proby FROM $pz WHERE id=%d", (int) $z->id ) );
$wpdb->query( 'ROLLBACK' );

if ( 'mutant' === $tryb ) { remove_filter( 'query', $mutacja ); }

$token_sie_zmienil = ( (string) $po->token_pobrania !== (string) $z->token_pobrania );
printf( "domknij_zakup zwrocil: %s\n", var_export( $wynik, true ) );
printf( "PROB WYSYLKI MAILA: %d\n", (int) $GLOBALS['sonda_maile'] );
printf( "token pobrania zrotowany: %s (%s… -> %s…)\n", $token_sie_zmienil ? 'TAK' : 'nie', substr( $z->token_pobrania, 0, 10 ), substr( (string) $po->token_pobrania, 0, 10 ) );
printf( "status po replayu: %s (przed: %s)\n", $po->status, $z->status );
if ( 'mutant' === $tryb ) { printf( "mutacja CAS-a weszla: %s\n", $GLOBALS['sonda_mutacja_weszla'] ? 'TAK' : 'NIE' ); }

// dowod, ze ROLLBACK zadzialal
$kontrola = $wpdb->get_row( $wpdb->prepare( "SELECT status, token_pobrania FROM $pz WHERE id=%d", (int) $z->id ) );
$czysto   = ( (string) $kontrola->token_pobrania === (string) $z->token_pobrania && (string) $kontrola->status === (string) $z->status );
printf( "PO ROLLBACKU wiersz identyczny jak przed: %s\n", $czysto ? 'TAK' : '⛔ NIE' );
if ( ! $czysto ) { echo "SONDA: rollback nie przywrocil stanu — nie melduje wyniku pomiaru\n"; exit( 2 ); }
if ( 'mutant' === $tryb && ! $GLOBALS['sonda_mutacja_weszla'] ) { echo "SONDA: mutacja NIE weszla — kalibracja nieodbyta\n"; exit( 2 ); }

$wada = ( $GLOBALS['sonda_maile'] > 0 ) || $token_sie_zmienil;
echo $wada ? "WYNIK: DRUGI MAIL / ROTACJA TOKENU — ochrona NIE dziala\n" : "WYNIK: bez drugiego maila i bez rotacji tokenu — ochrona dziala\n";
exit( $wada ? 1 : 0 );
