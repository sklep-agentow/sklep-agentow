<?php
/**
 * PADAJACY TEST — soczewka "skad kod wie, ze sie udalo".
 * Wymusza REALNA awarie zapisu do bazy (filtr 'query' rdzenia WP psuje
 * konkretne zapytanie -> $wpdb->update zwraca false, dokladnie jak przy
 * odebranych uprawnieniach do tabeli / pelnym dysku) i pyta, co widzi
 * wlasciciel. Kod wtyczek wykonuje sie NAPRAWDE, nic nie jest atrapa.
 */
global $wpdb;
// Panele obu wtyczek ladowane sa pod is_admin(); WP-CLI nie jest adminem,
// wiec dociagamy DOKLADNIE te pliki, ktore laduje wtyczka w panelu.
foreach ( array( 'chatbot-live/includes/admin.php', 'chatbot-live/includes/operator.php', 'chatbot-live/includes/import.php', 'chatbot-live/includes/csv.php', 'chatbot-live/includes/statystyki.php', 'chatbot-live/includes/powitanie.php', 'segregator-maila/includes/csv.php', 'segregator-maila/includes/admin.php', 'segregator-maila/includes/admin-probka.php', 'segregator-maila/includes/admin-skrzynka.php', 'segregator-maila/includes/admin-powitanie.php', 'segregator-maila/includes/filtry.php', 'segregator-maila/includes/konfiguracja.php' ) as $plik ) {
	if ( file_exists( WP_PLUGIN_DIR . '/' . $plik ) ) { require_once WP_PLUGIN_DIR . '/' . $plik; }
}
$wpdb->suppress_errors( true );
wp_set_current_user( 1 );
$GLOBALS['PAD'] = 0;
$GLOBALS['ZDA'] = 0;

function zdaj( $id, $ok, $co ) {
	$GLOBALS['ZDA']++;
	if ( ! $ok ) { $GLOBALS['PAD']++; }
	printf( "%-4s %-9s %s\n", $id, $ok ? 'PRZESZLO' : 'PADA', $co );
}
/** Psuje zapis do wp_options dla wskazanej opcji (albo UPDATE wskazanej tabeli). */
function psuj( $igla ) {
	$f = function ( $q ) use ( $igla ) {
		if ( false !== stripos( $q, $igla ) && 0 === stripos( ltrim( $q ), 'UPDATE' ) ) {
			return 'UPDATE ' . $GLOBALS['wpdb']->options . ' SET option_value = 1 WHERE option_id = (SELECT 1 FROM tabela_ktorej_nie_ma)';
		}
		return $q;
	};
	add_filter( 'query', $f, 999 );
	return $f;
}
function odpsuj( $f ) { remove_filter( 'query', $f, 999 ); }

/** Uruchamia handler admin_post i zwraca adres przekierowania. */
function gdzie_przekieruje( $fn ) {
	$przechwyt = function ( $l ) { throw new Exception( 'R:' . $l ); };
	add_filter( 'wp_redirect', $przechwyt, 1 );
	try { $fn(); $wynik = '(brak przekierowania)'; }
	catch ( Exception $e ) { $wynik = substr( $e->getMessage(), 2 ); }
	remove_filter( 'wp_redirect', $przechwyt, 1 );
	return $wynik;
}

echo "===== T1/T2: zapis USTAWIEN, gdy baza odrzuca zapis =====\n";

update_option( 'chatl_ustawienia', array_merge( chatl_ustawienia(), array( 'ai_limit_dzienny' => 11 ) ) );
$_POST = array( 'wlaczony' => 1, 'ai_limit_dzienny' => 999 );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
$f = psuj( 'chatl_ustawienia' );
$adres_ch = gdzie_przekieruje( 'chatl_post_ustawienia_zapisz' );
odpsuj( $f );
wp_cache_flush();
$limit_ch = (int) chatl_ustawienia()['ai_limit_dzienny'];
echo "     ChatBot   -> $adres_ch\n     w bazie zostalo ai_limit_dzienny=$limit_ch (proszono o 999)\n";
zdaj( 'T1', 999 === $limit_ch || false === strpos( $adres_ch, 'chatl_ok=zapisano' ),
	'ChatBot: po odrzuconym zapisie wlasciciel NIE moze dostac potwierdzenia "zapisano"' );

update_option( 'segm_ustawienia', array_merge( segm_ustawienia(), array( 'ai_limit_dzienny' => 11 ) ) );
$_POST = array( 'ai_limit_dzienny' => 999 );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'segm_ustawienia_zapisz' );
$f = psuj( 'segm_ustawienia' );
$adres_se = gdzie_przekieruje( 'segm_post_ustawienia_zapisz' );
odpsuj( $f );
wp_cache_flush();
$limit_se = (int) segm_ustawienia()['ai_limit_dzienny'];
echo "     Segregator-> $adres_se\n     w bazie zostalo ai_limit_dzienny=$limit_se (proszono o 999)\n";
zdaj( 'T2', 999 === $limit_se || false === strpos( $adres_se, 'segm_ok=zapisano' ),
	'PROBA KONTROLNA: blizniaczy handler Segregatora na TEJ SAMEJ awarii' );

echo "\n===== T3/T4: PRZEJECIE ROZMOWY, gdy baza odrzuca zapis =====\n";
$sesja = chatl_nowa_sesja();
$wynik_ok = chatl_przejmij( $sesja, 1 );
$stan_ok  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $sesja ) );
zdaj( 'T4', true === $wynik_ok && 'czlowiek' === $stan_ok,
	'PROBA KONTROLNA: przy dzialajacej bazie przejecie zwraca true I stan=czlowiek (' . $stan_ok . ')' );

chatl_oddaj_botowi( $sesja, '' );
wp_cache_flush();
$przed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_rozmowy WHERE tresc LIKE 'Do rozmowy dolaczyl%' OR tresc LIKE 'Do rozmowy dołączył%'" );
$f = psuj( 'chatl_sesje' );
$wynik_pad = chatl_przejmij( $sesja, 1 );
odpsuj( $f );
$stan_pad = (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $sesja ) );
$po = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_rozmowy WHERE tresc LIKE 'Do rozmowy dolaczyl%' OR tresc LIKE 'Do rozmowy dołączył%'" );
echo "     chatl_przejmij() zwrocilo: " . var_export( $wynik_pad, true ) . "; stan w bazie: '$stan_pad'\n";
echo "     komunikatow 'Do rozmowy dolaczyl konsultant' dopisanych goscowi: " . ( $po - $przed ) . "\n";
zdaj( 'T3', ( 'czlowiek' === $stan_pad ) || ( false === $wynik_pad ),
	'ChatBot: chatl_przejmij() ma zwrocic false, gdy przejecie NIE doszlo do skutku (docblock: "Czy przejecie doszlo do skutku")' );

echo "\n===== T5/T6: ZAPIS SEKRETU (klucz API / haslo IMAP), gdy baza odrzuca zapis =====\n";
update_option( 'chatl_klucz_api', 'STARY-KLUCZ' );
$f = psuj( 'chatl_klucz_api' );
$r5 = chatl_zapisz_klucz( 'NOWY-KLUCZ' );
odpsuj( $f );
wp_cache_flush();
echo "     chatl_zapisz_klucz() zwrocilo: " . var_export( $r5, true ) . "; w bazie: '" . get_option( 'chatl_klucz_api' ) . "'\n";
zdaj( 'T5', 'NOWY-KLUCZ' === get_option( 'chatl_klucz_api' ) || false === $r5,
	'ChatBot: zapis klucza API musi dac wolajacemu odrozniac sukces od porazki' );

update_option( 'segm_imap_haslo', 'STARE-HASLO' );
$f = psuj( 'segm_imap_haslo' );
$r6 = segm_skrzynka_zapisz_haslo( 'NOWE-HASLO' );
odpsuj( $f );
wp_cache_flush();
echo "     segm_skrzynka_zapisz_haslo() zwrocilo: " . var_export( $r6, true ) . "; w bazie: '" . get_option( 'segm_imap_haslo' ) . "'\n";
zdaj( 'T6', 'NOWE-HASLO' === get_option( 'segm_imap_haslo' ) || false === $r6,
	'PROBA KONTROLNA: blizniaczy zapis sekretu w Segregatorze MIERZY stan po operacji' );

update_option( 'chatl_klucz_api', '' );
update_option( 'segm_imap_haslo', '' );
echo "\nZDANYCH " . ( $GLOBALS['ZDA'] - $GLOBALS['PAD'] ) . "/" . $GLOBALS['ZDA'] . ", PADA " . $GLOBALS['PAD'] . "\n";
exit( $GLOBALS['PAD'] > 0 ? 1 : 0 );
