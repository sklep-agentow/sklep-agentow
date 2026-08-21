<?php
/** ŁAMACZ v2 — izolacja mechanizmu. Audyt 3fc884d. */
$k = WP_PLUGIN_DIR . '/segregator-maila/includes/';
foreach ( array( 'konfiguracja.php', 'class-segm-imap.php', 'silnik.php', 'skrzynka.php', 'skrzynka-przebieg.php' ) as $f ) { require_once $k . $f; }
global $wpdb;
$ZN = 'lamzn' . strtolower( wp_generate_password( 6, false ) );
$ZRODLO = 'LAM-IN'; $ZUZYTE = 'LAM-zuzyte'; $KAT = 'LAM-Kat'; $BOK = 'LAM-gdzieindziej';
function p( $s ) { echo $s . "\n"; }
function kl() { $c = new Segm_Imap( 20 ); return ( $c->polacz( 'segm_imap', 3143, 'brak' ) && $c->zaloguj( 'szef', 'tajne1234' ) ) ? $c : null; }
function tematy( $f ) { $c = kl(); if ( ! $c ) return array('BLAD'); if ( null === $c->otworz( $f, true ) ) { $c->rozlacz(); return array('(folder nie otwarty)'); }
	$t = array(); foreach ( (array) $c->uidy('ALL') as $u ) { $w = segm_skrzynka_wiadomosc( $c, $u, false ); $t[] = 'UID'.$u.':'.( $w ? $w->temat : '?' ); } $c->rozlacz(); return $t; }
function wyslij( $t, $n ) { $s = @fsockopen('segm_imap',3025,$e,$es,10); if(!$s) return false; $r=function()use($s){return fgets($s,1024);};
	$w=function($l)use($s,$r){fwrite($s,$l."\r\n");return $r();}; $r();$w('HELO t');$w("MAIL FROM:<$n>");$w('RCPT TO:<szef@firma.test>');$w('DATA');
	fwrite($s,"Subject: $t\r\nFrom: $n\r\nTo: szef@firma.test\r\nMessage-ID: <$t@lam>\r\n\r\ntresc\r\n.\r\n"); $r();$w('QUIT');fclose($s);return true; }
function do_zrodla( $t, $z ) { $c = kl(); if(!$c) return false; if(!$c->folder_istnieje($z)) $c->utworz_folder($z);
	if ( null === $c->otworz('INBOX',false) ) { $c->rozlacz(); return false; } $ok=false;
	foreach ( (array) $c->uidy('ALL') as $u ) { $w = segm_skrzynka_wiadomosc($c,$u,false); if ($w && false!==mb_strpos((string)$w->temat,$t)) $ok=(bool)$c->przenies($u,$z); } $c->rozlacz(); return $ok; }

$GLOBALS['lam_ust']=get_option('segm_skrzynka',null); $GLOBALS['lam_h']=segm_skrzynka_haslo(); $GLOBALS['lam_z']=get_option('segm_zgoda_przenoszenie',null); $GLOBALS['lam_pr']=array();
register_shutdown_function(function() use ($ZN,$ZRODLO,$ZUZYTE,$KAT,$BOK){ global $wpdb;
	if(null===$GLOBALS['lam_ust']) delete_option('segm_skrzynka'); else update_option('segm_skrzynka',$GLOBALS['lam_ust']);
	segm_skrzynka_zapisz_haslo((string)$GLOBALS['lam_h']);
	if(null===$GLOBALS['lam_z']) delete_option('segm_zgoda_przenoszenie'); else update_option('segm_zgoda_przenoszenie',$GLOBALS['lam_z']);
	$jak='%'.$wpdb->esc_like($ZN).'%';
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_reguly WHERE wzorzec LIKE %s",$jak));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE %s",$jak));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = %s",$KAT));
	foreach((array)$GLOBALS['lam_pr'] as $id){ $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id=%d",(int)$id));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id=%d",(int)$id)); }
	foreach(array('INBOX',$ZRODLO,$BOK,'LamTest.'.$KAT) as $f){ $c=kl(); if(!$c) continue; if(!$c->folder_istnieje($ZUZYTE)) $c->utworz_folder($ZUZYTE);
		if(null!==$c->otworz($f,false)){ foreach((array)$c->uidy('ALL') as $u){ $w=segm_skrzynka_wiadomosc($c,$u,false); if($w && false!==mb_strpos((string)$w->temat,$ZN)) $c->przenies($u,$ZUZYTE);} } $c->rozlacz(); }
	p("\n[sprzatanie wykonane]"); });

$ust=array_merge(segm_skrzynka_ustawienia(),array('host'=>'segm_imap','port'=>3143,'szyfrowanie'=>'brak','login'=>'szef',
	'folder_zrodlowy'=>$ZRODLO,'prefiks'=>'LamTest','limit'=>50,'tryb'=>'na_zywo','ruszaj_reszte'=>0,'tylko_nieprzeczytane'=>0));
update_option('segm_skrzynka',$ust); segm_skrzynka_zapisz_haslo('tajne1234');
update_option('segm_zgoda_przenoszenie',array('kiedy'=>current_time('mysql'),'kto'=>'lamacz'));
$wpdb->insert($wpdb->prefix.'segm_kategorie',array('nazwa'=>$KAT,'utworzono'=>current_time('mysql'))); $kid=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'segm_reguly',array('kolejnosc'=>1,'pole'=>'temat','wzorzec'=>$ZN,'kategoria_id'=>$kid,'utworzono'=>current_time('mysql')));
$c=kl(); if(!$c){p('STOP brak IMAP');exit(2);} foreach(array($ZUZYTE,$ZRODLO,$BOK) as $f){ if(!$c->folder_istnieje($f)) $c->utworz_folder($f);} 
if(null!==$c->otworz($ZRODLO,false)) foreach((array)$c->uidy('ALL') as $u) $c->przenies($u,$ZUZYTE);
$CEL7=segm_skrzynka_folder_dla($c,'LamTest',$KAT); $CEL=Segm_Imap::z_utf7($CEL7); $c->rozlacz();

/** Jeden pelny cykl: wyslij -> przebieg na zywo -> (opcjonalnie czlowiek rusza maila) -> cofnij. */
function cykl( $etykieta, $ruszaj_recznie, $ZN, $ZRODLO, $CEL, $CEL7, $BOK ) {
	global $wpdb;
	$temat = 'LAM' . ( $ruszaj_recznie ? 'B' : 'A' ) . ' ' . $ZN;
	p( "\n############ $etykieta ############" );
	wyslij( $temat, $ZN . '@lam.invalid' ); sleep(1); do_zrodla( $temat, $ZRODLO );
	$pr = segm_skrzynka_przebieg('test'); $GLOBALS['lam_pr'][]=(int)$pr['id'];
	p( "przebieg id={$pr['id']} przeniesionych={$pr['przeniesionych']} blad={$pr['blad']}" );
	$ruch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id=%d AND stan='przeniesiony'",(int)$pr['id']));
	if(!$ruch){ p('STOP: nic nie przeniesiono'); return; }
	p( "historia: uid_docelowy={$ruch->uid_docelowy} folder_do={$ruch->folder_do}" );
	p( "folder docelowy: " . implode(' | ', tematy($CEL)) );
	if ( $ruszaj_recznie ) {
		// CZLOWIEK w swoim programie pocztowym przeciaga maila do innego folderu.
		$c = kl(); $c->otworz($CEL7,false);
		foreach((array)$c->uidy('ALL') as $u){ $w=segm_skrzynka_wiadomosc($c,$u,false); if($w && false!==mb_strpos((string)$w->temat,$temat)) $c->przenies($u,$BOK); }
		$c->rozlacz();
		p( "-> CZLOWIEK przeciagnal maila do $BOK. Folder docelowy teraz: " . implode(' | ', tematy($CEL)) );
	}
	$cof = segm_skrzynka_cofnij((int)$pr['id']);
	p( "COFNIECIE: ok=".var_export($cof['ok'],true)." cofnietych={$cof['cofnietych']} bledow={$cof['bledow']} blad='{$cof['blad']}'" );
	$st = $wpdb->get_row($wpdb->prepare("SELECT stan,komunikat FROM {$wpdb->prefix}segm_skrz_ruchy WHERE id=%d",(int)$ruch->id));
	$fl = (int)$wpdb->get_var($wpdb->prepare("SELECT cofniety FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id=%d",(int)$pr['id']));
	p( "historia po cofnieciu: stan={$st->stan} cofniety={$fl} | {$st->komunikat}" );
	p( ">>> FOLDER ZRODLOWY: " . implode(' | ', tematy($ZRODLO)) );
	p( ">>> FOLDER $BOK: " . implode(' | ', tematy($BOK)) );
}
cykl('PROBA KONTROLNA — nikt nie rusza maila (tak ma dzialac)', false, $ZN,$ZRODLO,$CEL,$CEL7,$BOK);
cykl('PROBA LAMIACA — wlasciciel przeciagnal maila recznie',  true,  $ZN,$ZRODLO,$CEL,$CEL7,$BOK);

/* ===== L5: DISABLE_WP_CRON — czy panel mowi prawde ===== */
p( "\n############ L5 — DISABLE_WP_CRON ############" );
p( 'stala zdefiniowana w tym procesie: ' . var_export( defined('DISABLE_WP_CRON') ? constant('DISABLE_WP_CRON') : 'NIEZDEFINIOWANA', true ) );
$u2 = get_option('segm_skrzynka'); $u2['czestotliwosc']='segm_co_godzine'; update_option('segm_skrzynka',$u2);
segm_skrzynka_przeplanuj();
p( 'opis harmonogramu (funkcja panelu skrzynki): ' . segm_skrzynka_opis_harmonogramu() );
if ( function_exists('segm_skrzynka_diagnoza') ) { p('diagnoza: ' . wp_json_encode(segm_skrzynka_diagnoza())); }
wp_clear_scheduled_hook('segm_skrzynka_cron');
