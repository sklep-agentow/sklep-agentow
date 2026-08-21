<?php
/**
 * ZNALEZISKO AUDYTU DANYCH: tabela `chatl_sesje` nie ma ŻADNEJ ścieżki kasowania.
 *
 * Trop od audytora awarii, sprawdzony wyszukiwaniem: w całej wtyczce nie ma
 * ani jednego `DELETE FROM …chatl_sesje` — tylko INSERT/UPDATE/SELECT i
 * `DROP TABLE` w uninstall.php. Skutki są dwa i oba są w zakresie „co zostaje":
 *
 *   A. RETENCJA — `chatl_czyszczenie_historii()` kasuje `chatl_rozmowy`
 *      i `chatl_rate_limit`, a `chatl_sesje` pomija. Tabela rośnie bez końca:
 *      jeden wiersz na KAŻDĄ sesję widgetu, także tę, której rozmowę
 *      retencja skasowała 90 dni temu.
 *   B. RODO — `chatl_rodo_kasownik()` kasuje zgłoszenia i rozmowy, a wiersz
 *      sesji wnioskodawcy zostaje: po „usunięciu danych" w bazie dalej leży
 *      ślad, że ta sesja istniała, kiedy powstała i czy przejął ją człowiek.
 *
 * TEST MA CZTERY PRZYPADKI + KONTROLĘ MECHANIZMU:
 *   0. kontrola: STARA rozmowa znika, ŚWIEŻA zostaje (tabela OBJĘTA retencją)
 *      — dowód, że próg i harness działają; bez tego padnięcie niżej nic nie znaczy
 *   1. WADA:     stara sesja ma zniknąć po retencji
 *   2. BEZ WADY: świeża sesja ma ZOSTAĆ po retencji
 *   3. WADA:     sesja wnioskodawcy ma zniknąć po kasowaniu RODO
 *   4. BEZ WADY: sesja OBCEJ osoby ma ZOSTAĆ po kasowaniu RODO
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-sesje-retencja-rodo-test.php strona_wp_wordpress:/tmp/s.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/s.php
 * EXIT 0 = wszystko jak należy, 1 = któryś przypadek nie.
 */

$chatl_kat = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $chatl_kat . 'rodo.php'; // ładuje się tylko przy is_admin()

if ( ! function_exists( 'chatl_czyszczenie_historii' ) || ! function_exists( 'chatl_rodo_kasownik' ) ) {
	echo "  BLAD srodowisko: wtyczka chatbot-live nie zaladowana\n";
	exit( 1 );
}

global $wpdb;
$GLOBALS['chatl_bledy'] = 0;
$ts = $wpdb->prefix . 'chatl_sesje';
$tr = $wpdb->prefix . 'chatl_rozmowy';
$tz = $wpdb->prefix . 'chatl_zgloszenia';

function chatl_ok( $o, $s = '' ) { printf( "  OK   %s%s\n", $o, '' !== $s ? "  [$s]" : '' ); }
function chatl_bl( $o, $s = '' ) { $GLOBALS['chatl_bledy']++; printf( "  BLAD %s%s\n", $o, '' !== $s ? "  [$s]" : '' ); }

$dni    = defined( 'CHATL_RETENCJA_DNI' ) ? (int) CHATL_RETENCJA_DNI : 90;
$stary  = gmdate( 'Y-m-d H:i:s', time() - ( $dni + 30 ) * DAY_IN_SECONDS );
$swiezy = current_time( 'mysql' );
$znak   = 'AUDYTDANYCH' . wp_generate_password( 10, false, false );

printf( "=== AUDYT DANYCH: co zostaje w chatl_sesje ===\n  prog retencji CHATL_RETENCJA_DNI=%d dni; stary wpis datowany na %s\n\n", $dni, $stary );

/* --- podkladamy dane --- */
$s_stara  = $znak . 'stara';
$s_swieza = $znak . 'swieza';
$s_wnio   = $znak . 'wniosk';
$s_obca   = $znak . 'obca00';
$mail_w   = strtolower( $znak ) . '@wnioskodawca.test';
$mail_o   = strtolower( $znak ) . '@obcy.test';

foreach ( array( array( $s_stara, $stary ), array( $s_swieza, $swiezy ), array( $s_wnio, $swiezy ), array( $s_obca, $swiezy ) ) as $p ) {
	$wpdb->insert( $ts, array( 'sesja' => $p[0], 'stan' => 'bot', 'klucz_hash' => wp_hash( 'x' ), 'utworzono' => $p[1], 'aktywnosc' => $p[1] ), array( '%s', '%s', '%s', '%s', '%s' ) );
}
// kontrola mechanizmu: rozmowa stara i swieza w tabeli OBJETEJ retencja
$wpdb->insert( $tr, array( 'sesja' => $s_stara,  'rola' => 'gosc', 'tresc' => 'stara wiadomosc',  'utworzono' => $stary ) );
$wpdb->insert( $tr, array( 'sesja' => $s_swieza, 'rola' => 'gosc', 'tresc' => 'swieza wiadomosc', 'utworzono' => $swiezy ) );
// dane RODO: wnioskodawca i osoba obca
$wpdb->insert( $tr, array( 'sesja' => $s_wnio, 'rola' => 'gosc', 'tresc' => 'wiadomosc wnioskodawcy', 'utworzono' => $swiezy ) );
$wpdb->insert( $tz, array( 'sesja' => $s_wnio, 'email' => $mail_w, 'wiadomosc' => 'prosze o kontakt', 'status' => 'nowe', 'utworzono' => $swiezy ) );
$wpdb->insert( $tz, array( 'sesja' => $s_obca, 'email' => $mail_o, 'wiadomosc' => 'inna osoba',      'status' => 'nowe', 'utworzono' => $swiezy ) );

$jest = function ( $tab, $kol, $val ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tab} WHERE {$kol} = %s", $val ) ) > 0;
};

/* --- 0. KONTROLA MECHANIZMU na tabeli OBJETEJ retencja --- */
chatl_czyszczenie_historii();
$r_stara  = $jest( $tr, 'sesja', $s_stara );
$r_swieza = $jest( $tr, 'sesja', $s_swieza );
if ( ! $r_stara && $r_swieza ) {
	chatl_ok( '0. kontrola: retencja kasuje STARA rozmowe, zostawia SWIEZA', 'chatl_rozmowy — prog dziala' );
} else {
	chatl_bl( '0. kontrola: retencja NIE dziala jak trzeba na chatl_rozmowy', 'stara=' . ( $r_stara ? 'zostala' : 'znikla' ) . ' swieza=' . ( $r_swieza ? 'zostala' : 'ZNIKLA' ) );
}

/* --- 1. WADA: stara sesja po retencji --- */
if ( $jest( $ts, 'sesja', $s_stara ) ) {
	chatl_bl( '1. stara sesja (' . ( $dni + 30 ) . ' dni) NIE zostala skasowana przez retencje', 'wiersz w chatl_sesje ZOSTAJE — tabela rosnie bez konca' );
} else {
	chatl_ok( '1. stara sesja skasowana przez retencje' );
}
/* --- 2. BEZ WADY: swieza sesja ma zostac --- */
if ( $jest( $ts, 'sesja', $s_swieza ) ) {
	chatl_ok( '2. swieza sesja ZOSTALA (naprawa nie moze kasowac wszystkiego)' );
} else {
	chatl_bl( '2. swieza sesja ZNIKLA — kasowanie za szerokie' );
}

/* --- 3/4. RODO --- */
chatl_rodo_kasownik( $mail_w );
if ( $jest( $ts, 'sesja', $s_wnio ) ) {
	chatl_bl( '3. po kasowaniu RODO wiersz sesji wnioskodawcy ZOSTAJE w chatl_sesje', 'slad osoby przezyl "usuniecie danych"' );
} else {
	chatl_ok( '3. kasowanie RODO usunelo tez wiersz sesji wnioskodawcy' );
}
if ( $jest( $ts, 'sesja', $s_obca ) ) {
	chatl_ok( '4. sesja OBCEJ osoby nietknieta przez wniosek RODO' );
} else {
	chatl_bl( '4. wniosek RODO skasowal sesje OBCEJ osoby — kasowanie za szerokie' );
}

/* --- sprzatanie --- */
foreach ( array( $s_stara, $s_swieza, $s_wnio, $s_obca ) as $s ) {
	$wpdb->delete( $ts, array( 'sesja' => $s ) );
	$wpdb->delete( $tr, array( 'sesja' => $s ) );
	$wpdb->delete( $tz, array( 'sesja' => $s ) );
}
$wspolne = (array) get_option( 'chatl_rodo_sesje_wspolne', array() );
update_option( 'chatl_rodo_sesje_wspolne', array_values( array_diff( $wspolne, array( $s_wnio, $s_obca ) ) ), false );

echo "\n";
if ( 0 === $GLOBALS['chatl_bledy'] ) {
	echo "WSZYSTKIE KROKI PRZESZLY — chatl_sesje sprzatana przez retencje i przez RODO\n";
	exit( 0 );
}
printf( "PRZYPADKOW NIEZALICZONYCH: %d\n", $GLOBALS['chatl_bledy'] );
exit( 1 );
