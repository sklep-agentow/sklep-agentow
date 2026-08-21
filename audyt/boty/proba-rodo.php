<?php
// Proba: co eksporter RODO wypisuje w kolumnie "Kto" dla wiadomosci OPERATORA.
// Dane testowe z wlasnym prefiksem sesji, kasowane na koncu.
global $wpdb;
$sesja = 'auditrodo-' . wp_generate_password( 12, false );
$mail  = 'proba-audytu-rodo@example.invalid';
$tr = $wpdb->prefix . 'chatl_rozmowy';
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$ts = $wpdb->prefix . 'chatl_sesje';
$teraz = current_time( 'mysql' );

$wpdb->insert( $ts, array( 'sesja'=>$sesja,'stan'=>'czlowiek','klucz_hash'=>'x','utworzono'=>$teraz,'aktywnosc'=>$teraz ) );
foreach ( array( array('gosc','Dzien dobry, ile kosztuje przeglad?'), array('bot','Nie znam odpowiedzi.'), array('system','Do rozmowy dolaczyl konsultant.'), array('operator','Tu Anna z serwisu, przeglad to 250 zl.') ) as $w ) {
	$wpdb->insert( $tr, array( 'sesja'=>$sesja,'rola'=>$w[0],'silnik'=>'reguly','tresc'=>$w[1],'utworzono'=>$teraz ) );
}
$wpdb->insert( $tz, array( 'email'=>$mail,'wiadomosc'=>'Prosze o kontakt','sesja'=>$sesja,'status'=>'nowe','podejrzane'=>0,'utworzono'=>$teraz ) );

$wynik = chatl_rodo_eksporter( $mail, 1 );
echo "Paczka RODO wydana wnioskodawcy (grupa 'Historia rozmowy z botem'):\n";
foreach ( $wynik['data'] as $poz ) {
	if ( 'chatl_rozmowy' !== $poz['group_id'] ) { continue; }
	$kto = ''; $tresc = '';
	foreach ( $poz['data'] as $pole ) {
		if ( 'Kto' === $pole['name'] ) { $kto = $pole['value']; }
		if ( 'Treść' === $pole['name'] ) { $tresc = $pole['value']; }
	}
	echo sprintf( "  Kto: %-6s | Treść: %s\n", $kto, $tresc );
}
// sprzatanie
$wpdb->delete( $tr, array( 'sesja' => $sesja ) );
$wpdb->delete( $tz, array( 'sesja' => $sesja ) );
$wpdb->delete( $ts, array( 'sesja' => $sesja ) );
echo "\nsprzatanie: pozostalo wierszy rozmow=" . (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$tr} WHERE sesja=%s",$sesja) )
	. " zgloszen=" . (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$tz} WHERE sesja=%s",$sesja) )
	. " sesji=" . (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$ts} WHERE sesja=%s",$sesja) ) . "\n";
