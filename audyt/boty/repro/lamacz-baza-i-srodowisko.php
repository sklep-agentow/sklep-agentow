<?php
/** ŁAMACZ v3 — L1 (baza pada w polowie), L2 (zly klucz), L5 (DISABLE_WP_CRON). Audyt 3fc884d. */
$k = WP_PLUGIN_DIR . '/segregator-maila/includes/';
foreach ( array('konfiguracja.php','class-segm-imap.php','silnik.php','skrzynka.php','skrzynka-przebieg.php','ai.php') as $f ) { require_once $k . $f; }
global $wpdb;
function p($s){ echo $s."\n"; }

/* ================= L5 ================= */
p( "############ L5 — DISABLE_WP_CRON ############" );
p( 'DISABLE_WP_CRON w tym procesie: ' . ( defined('DISABLE_WP_CRON') ? var_export(constant('DISABLE_WP_CRON'),true) : 'NIEZDEFINIOWANA' ) );
$diag = null;
foreach ( get_defined_functions()['user'] as $fn ) {
	if ( 0 === strpos( $fn, 'segm_skrzynka_srodowisko' ) && 'segm_skrzynka_srodowisko_pada' !== $fn ) { $diag = $fn; }
}
p( 'funkcja diagnostyki srodowiska: ' . var_export( $diag, true ) );
if ( $diag ) { foreach ( (array) $diag() as $w ) { if ( false !== stripos( $w['nazwa'], 'cron' ) ) { p( 'DIAGNOSTYKA -> [' . $w['stan'] . '] ' . $w['nazwa'] . ': ' . $w['opis'] ); } } }
$u = get_option('segm_skrzynka'); $stare_cz = isset($u['czestotliwosc']) ? $u['czestotliwosc'] : 'wylaczone';
$u['czestotliwosc']='segm_co_godzine'; update_option('segm_skrzynka',$u);
segm_skrzynka_przeplanuj();
p( 'PANEL SKRZYNKI -> ' . segm_skrzynka_opis_harmonogramu() );
wp_clear_scheduled_hook('segm_skrzynka_cron');
$u['czestotliwosc']=$stare_cz; update_option('segm_skrzynka',$u);

/* ================= L2 ================= */
p( "\n############ L2 — KLUCZ API ZLY / WYGASLY ############" );
$r = new ReflectionFunction('segm_ai_przetestuj_klucz');
p( 'segm_ai_przetestuj_klucz() przyjmuje argumentow: ' . $r->getNumberOfParameters() );
$stary_klucz = null; $opcja_klucza = null;
foreach ( array('segm_ai_klucz','segm_klucz_api','segm_ai') as $o ) { $v = get_option($o,null); if ( null !== $v ) { $opcja_klucza = $o; $stary_klucz = $v; } }
p( 'opcja z kluczem: ' . var_export($opcja_klucza,true) );
if ( $r->getNumberOfParameters() >= 1 ) {
	$w = segm_ai_przetestuj_klucz( 'sk-ant-api03-KLUCZ-CELOWO-ZLY-lamacz' );
	p( 'WYNIK TESTU ZLEGO KLUCZA: ' . wp_json_encode( $w, JSON_UNESCAPED_UNICODE ) );
} else { p( 'funkcja bez argumentu — pomijam, zeby nie ruszac klucza wspolnego stosu' ); }

/* ================= L1 ================= */
p( "\n############ L1 — BAZA NIE ODPOWIADA W POLOWIE PRZEBIEGU ############" );
$ZN='lam3'.strtolower(wp_generate_password(6,false)); $ZRODLO='LAM-IN'; $ZUZYTE='LAM-zuzyte'; $KAT='LAM-Kat3'; $TEMAT='LAM3 '.$ZN;
function kl(){ $c=new Segm_Imap(20); return ($c->polacz('segm_imap',3143,'brak')&&$c->zaloguj('szef','tajne1234'))?$c:null; }
function tematy($f){ $c=kl(); if(!$c) return array('BLAD'); if(null===$c->otworz($f,true)){$c->rozlacz();return array('(brak folderu)');}
	$t=array(); foreach((array)$c->uidy('ALL') as $u){ $w=segm_skrzynka_wiadomosc($c,$u,false); $t[]='UID'.$u.':'.($w?$w->temat:'?'); } $c->rozlacz(); return $t; }
function wyslij($t,$n){ $s=@fsockopen('segm_imap',3025,$e,$es,10); if(!$s) return false; $r=function()use($s){return fgets($s,1024);};
	$w=function($l)use($s,$r){fwrite($s,$l."\r\n");return $r();}; $r();$w('HELO t');$w("MAIL FROM:<$n>");$w('RCPT TO:<szef@firma.test>');$w('DATA');
	fwrite($s,"Subject: $t\r\nFrom: $n\r\nTo: szef@firma.test\r\nMessage-ID: <$t@lam>\r\n\r\ntresc\r\n.\r\n");$r();$w('QUIT');fclose($s);return true; }
function do_zrodla($t,$z){ $c=kl(); if(!$c) return false; if(!$c->folder_istnieje($z)) $c->utworz_folder($z);
	if(null===$c->otworz('INBOX',false)){$c->rozlacz();return false;} $ok=false;
	foreach((array)$c->uidy('ALL') as $u){ $w=segm_skrzynka_wiadomosc($c,$u,false); if($w&&false!==mb_strpos((string)$w->temat,$t)) $ok=(bool)$c->przenies($u,$z);} $c->rozlacz(); return $ok; }

$GLOBALS['lam_ust']=get_option('segm_skrzynka',null); $GLOBALS['lam_h']=segm_skrzynka_haslo(); $GLOBALS['lam_z']=get_option('segm_zgoda_przenoszenie',null); $GLOBALS['lam_pr']=array();
register_shutdown_function(function() use($ZN,$ZRODLO,$ZUZYTE,$KAT){ global $wpdb;
	if(null===$GLOBALS['lam_ust']) delete_option('segm_skrzynka'); else update_option('segm_skrzynka',$GLOBALS['lam_ust']);
	segm_skrzynka_zapisz_haslo((string)$GLOBALS['lam_h']);
	if(null===$GLOBALS['lam_z']) delete_option('segm_zgoda_przenoszenie'); else update_option('segm_zgoda_przenoszenie',$GLOBALS['lam_z']);
	$jak='%'.$wpdb->esc_like($ZN).'%';
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_reguly WHERE wzorzec LIKE %s",$jak));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE %s",$jak));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = %s",$KAT));
	foreach((array)$GLOBALS['lam_pr'] as $id){ $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id=%d",(int)$id));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id=%d",(int)$id)); }
	foreach(array('INBOX',$ZRODLO,'LamTest.'.$KAT) as $f){ $c=kl(); if(!$c) continue; if(!$c->folder_istnieje($ZUZYTE)) $c->utworz_folder($ZUZYTE);
		if(null!==$c->otworz($f,false)) foreach((array)$c->uidy('ALL') as $u){ $w=segm_skrzynka_wiadomosc($c,$u,false); if($w&&false!==mb_strpos((string)$w->temat,$ZN)) $c->przenies($u,$ZUZYTE); } $c->rozlacz(); }
	p("\n[sprzatanie wykonane]"); });

$ust=array_merge(segm_skrzynka_ustawienia(),array('host'=>'segm_imap','port'=>3143,'szyfrowanie'=>'brak','login'=>'szef',
	'folder_zrodlowy'=>$ZRODLO,'prefiks'=>'LamTest','limit'=>50,'tryb'=>'na_zywo','ruszaj_reszte'=>0,'tylko_nieprzeczytane'=>0));
update_option('segm_skrzynka',$ust); segm_skrzynka_zapisz_haslo('tajne1234');
update_option('segm_zgoda_przenoszenie',array('kiedy'=>current_time('mysql'),'kto'=>'lamacz'));
$wpdb->insert($wpdb->prefix.'segm_kategorie',array('nazwa'=>$KAT,'utworzono'=>current_time('mysql'))); $kid=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'segm_reguly',array('kolejnosc'=>1,'pole'=>'temat','wzorzec'=>$ZN,'kategoria_id'=>$kid,'utworzono'=>current_time('mysql')));
$c=kl(); foreach(array($ZUZYTE,$ZRODLO) as $f){ if(!$c->folder_istnieje($f)) $c->utworz_folder($f);} 
if(null!==$c->otworz($ZRODLO,false)) foreach((array)$c->uidy('ALL') as $u) $c->przenies($u,$ZUZYTE);
$CEL=Segm_Imap::z_utf7(segm_skrzynka_folder_dla($c,'LamTest',$KAT)); $c->rozlacz();
wyslij($TEMAT,$ZN.'@lam.invalid'); sleep(1); do_zrodla($TEMAT,$ZRODLO);
p('w zrodle przed przebiegiem: '.implode(' | ',tematy($ZRODLO)));

// AWARIA: baza przestaje przyjmowac wiersze historii w POLOWIE przebiegu
// (przebieg juz sie zapisal, ruchy juz nie) — tak wyglada padniecie bazy/dysku.
add_filter('query', function($q){ if ( false !== strpos($q,'segm_skrz_ruchy') && 0 === stripos(ltrim($q),'INSERT') ) { return 'INSERT INTO lamacz_tabela_ktorej_nie_ma (a) VALUES (1)'; } return $q; }, 99);
$wpdb->suppress_errors(true);
$pr = segm_skrzynka_przebieg('test'); $GLOBALS['lam_pr'][]=(int)$pr['id'];
remove_all_filters('query'); $wpdb->suppress_errors(false);
p('CO ZWROCIL PRZEBIEG (to widzi wlasciciel w panelu):');
p('   przeniesionych='.$pr['przeniesionych'].'  bledow='.$pr['bledow'].'  blad="'.$pr['blad'].'"  komunikat="'.$pr['komunikat'].'"');
$ile_ruchow=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id=%d",(int)$pr['id']));
$wiersz=$wpdb->get_row($wpdb->prepare("SELECT przeniesionych,cofniety FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id=%d",(int)$pr['id']));
p('BAZA: wierszy historii ruchow='.$ile_ruchow.'  a przebieg deklaruje przeniesionych='.($wiersz?$wiersz->przeniesionych:'?'));
p('SERWER POCZTY: zrodlo='.implode(' | ',tematy($ZRODLO)).'   docelowy='.implode(' | ',tematy($CEL)));
$cof = segm_skrzynka_cofnij((int)$pr['id']);
p('PROBA COFNIECIA: ok='.var_export($cof['ok'],true).' cofnietych='.$cof['cofnietych'].' blad="'.$cof['blad'].'"');
