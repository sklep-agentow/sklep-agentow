<?php
/**
 * ZNALEZISKO AUDYTU DANYCH: `chatbot-live/uninstall.php` nie zdejmuje swojego
 * zadania crona — po odinstalowaniu w bazie zostaje zaplanowany zaczep
 * `chatl_czyszczenie_historii`, dla ktorego nie ma juz kodu.
 *
 * Scenariusz, w ktorym to boli (opisany i ZAMKNIETY w blizniaczej wtyczce,
 * `segregator-maila/uninstall.php`: "odinstalowanie ma zostawic czysto takze
 * wtedy, gdy wtyczke usunieto z pominieciem tamtej sciezki"): katalog wtyczki
 * znika bez przejscia przez deaktywacje — wtedy `chatl_deaktywacja()` nigdy
 * nie biegnie i jedynym sprzataczem jest `uninstall.php`, ktory tego nie robi.
 * Zostaje wpis w `wp_options` -> `cron`, ktory WordPress probuje odpalac
 * codziennie i za kazdym razem trafia w nieistniejaca funkcje.
 *
 * PRZYPADKI:
 *   1. WADA:     po uninstall ChatBota cron `chatl_czyszczenie_historii` ma zniknac
 *   2. BEZ WADY: uninstall NIE moze zdjac crona CUDZEJ wtyczki
 *   3. KONTROLA: ta sama proba na Segregatorze (ktory to robi poprawnie) ma PRZEJSC
 *      — bez tego padniecie w 1. nie dowodzi niczego o metodzie pomiaru
 *
 * Uruchomienie:
 *   docker cp testy/uninstall-zombie-cron-test.php strona_wp_wordpress:/tmp/z.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/z.php
 */
global $wpdb;
$GLOBALS['B'] = 0; // ⛔ NIE '$B = 0' — wp eval-file biegnie w zasiegu FUNKCJI, licznik by nie rosl
function zok($o,$s=''){ printf("  OK   %s%s\n",$o,''!==$s?"  [$s]":''); }
function zbl($o,$s=''){ $GLOBALS['B']++; printf("  BLAD %s%s\n",$o,''!==$s?"  [$s]":''); }

$obcy = 'obca_wtyczka_zaczep_audyt';
$stan = array();
foreach ( array('chatl_czyszczenie_historii','segm_czyszczenie_probki','segm_skrzynka_cron') as $h ) {
	$stan[$h] = wp_next_scheduled( $h );
}
echo "=== AUDYT DANYCH: co zostaje w cronie po odinstalowaniu ===\n";

$probuj = function ( $katalog, $slug, $hook, $optust ) use ( &$obcy ) {
	// stan wyjsciowy: zaczep wtyczki zaplanowany + zaczep CUDZEJ wtyczki zaplanowany
	if ( ! wp_next_scheduled( $hook ) )  { wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', $hook ); }
	if ( ! wp_next_scheduled( $obcy ) )  { wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', $obcy ); }
	$u = (array) get_option( $optust, array() ); $u['usun_dane'] = 1; update_option( $optust, $u );
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { define( 'WP_UNINSTALL_PLUGIN', $slug ); }
	// uninstall NIE moze zrzucic prawdziwych tabel stosu: podstawiamy prefiks testowy
	global $wpdb; $real = $wpdb->prefix; $base = $wpdb->base_prefix;
	$wpdb->set_prefix( 'wpaudz_' ); $wpdb->options = $base . 'options'; $wpdb->usermeta = $base . 'usermeta';
	include WP_PLUGIN_DIR . '/' . $katalog . '/uninstall.php';
	$wpdb->set_prefix( $real ); $wpdb->options = $base . 'options'; $wpdb->usermeta = $base . 'usermeta';
	return array( 'wtyczki' => (bool) wp_next_scheduled( $hook ), 'obcy' => (bool) wp_next_scheduled( $obcy ) );
};

$r = $probuj( 'chatbot-live', 'chatbot-live/chatbot-live.php', 'chatl_czyszczenie_historii', 'chatl_ustawienia' );
if ( $r['wtyczki'] ) { zbl( '1. ChatBot: cron `chatl_czyszczenie_historii` ZOSTAJE po odinstalowaniu', 'zombie w wp_options->cron' ); }
else                 { zok( '1. ChatBot: cron zdjety przez uninstall' ); }
if ( $r['obcy'] )    { zok( '2. ChatBot: cron CUDZEJ wtyczki nietkniety' ); }
else                 { zbl( '2. ChatBot: uninstall zdjal cron CUDZEJ wtyczki', 'kasowanie za szerokie' ); }

$r2 = $probuj( 'segregator-maila', 'segregator-maila/segregator-maila.php', 'segm_czyszczenie_probki', 'segm_ustawienia' );
if ( $r2['wtyczki'] ) { zbl( '3. KONTROLA Segregator: cron ZOSTAL — metoda pomiaru podejrzana' ); }
else                  { zok( '3. KONTROLA Segregator: cron zdjety przez uninstall', 'blizniacza wtyczka robi to poprawnie' ); }

/* sprzatanie: zdejmij zaczep testowy, przywroc stan wyjsciowy cronow */
wp_clear_scheduled_hook( $obcy );
foreach ( $stan as $h => $kiedy ) { if ( $kiedy && ! wp_next_scheduled( $h ) ) { wp_schedule_event( $kiedy, 'daily', $h ); } }
foreach ( (array) $wpdb->get_col( "SHOW TABLES LIKE 'wpaudz\\_%'" ) as $t ) { $wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $t ) . "`" ); }

echo "\n";
if ( 0 === $GLOBALS['B'] ) { echo "WSZYSTKIE KROKI PRZESZLY — odinstalowanie nie zostawia zombie w cronie\n"; exit( 0 ); }
printf( "PRZYPADKOW NIEZALICZONYCH: %d\n", $GLOBALS['B'] ); exit( 1 );
