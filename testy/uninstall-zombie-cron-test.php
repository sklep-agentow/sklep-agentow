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
 *   9-12. SKLEP (#170): ta sama klasa w trzeciej wtyczce — sciezka B i sciezka A,
 *      z jawnym odcieciem drog kasujacych PRAWDZIWE dane (patrz $probuj/$wyzeruj)
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
foreach ( array('chatl_czyszczenie_historii','segm_czyszczenie_probki','segm_skrzynka_cron','sklepag_dociagnij_zalegle') as $h ) {
	$stan[$h] = wp_next_scheduled( $h );
}
echo "=== AUDYT DANYCH: co zostaje w cronie po odinstalowaniu ===\n";

/*
 * ⛔ KOPIE `wp_options` i `wp_usermeta`. Test wlacza PRAWDZIWY `uninstall.php`,
 * ktory kasuje opcje wtyczki (`chatl_klucz_api`, `segm_imap_haslo`,
 * `segm_skrzynka`, oba `*_ustawienia`…). Do tej poprawki podstawiany byl tylko
 * prefiks TABEL, a `$wpdb->options` wskazywal prawdziwa tabele — wiec kazde
 * uruchomienie tego testu KASOWALO klucz API wlasciciela i konfiguracje skrzynki
 * na wspolnym stosie (zmierzone 23.08: po przebiegu `chatl_klucz_api` znikal,
 * a `tokeny-koszt-test.php` padal na dwoch krokach z powodu braku klucza).
 * Teraz `delete_option()` trafia w KOPIE: prawdziwe wiersze zostaja nietkniete,
 * a jedynym skutkiem ubocznym jest wyczyszczony cache opcji (odbudowuje sie sam).
 */
$kopiuj_tabele = function ( $zrodlo, $kopia ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $kopia ) . "`" ); // phpcs:ignore
	$wpdb->query( "CREATE TABLE `" . esc_sql( $kopia ) . "` LIKE `" . esc_sql( $zrodlo ) . "`" ); // phpcs:ignore
	$wpdb->query( "INSERT INTO `" . esc_sql( $kopia ) . "` SELECT * FROM `" . esc_sql( $zrodlo ) . "`" ); // phpcs:ignore
};

/**
 * Jedno odinstalowanie na KOPIACH tabel.
 *
 * @param string $katalog   Katalog wtyczki.
 * @param string $slug      Slug do WP_UNINSTALL_PLUGIN.
 * @param string $hook      Zaczep crona wtyczki.
 * @param string $optust    Nazwa opcji z ustawieniami.
 * @param int    $usun_dane 1 = sciezka B (pelne czyszczenie), 0 = sciezka A (dane zostaja).
 * @param int    $plaska    1 = $optust to PLASKA opcja 0/1 (sklep), nie tablica z kluczem `usun_dane`.
 * @param array  $wyzeruj   Opcje do USUNIECIA z kopii przed uninstallem — jawne odciecie drog,
 *                          ktore pisza POZA kopiami ($wpdb->posts przez wp_delete_post,
 *                          prawdziwy dysk przez unlink). Bez tego sciezka B sklepu bylaby
 *                          bezpieczna tylko PRZYPADKIEM (gdy stos akurat nie ma tych opcji).
 */
$probuj = function ( $katalog, $slug, $hook, $optust, $usun_dane = 1, $plaska = 0, $wyzeruj = array() ) use ( &$obcy, $kopiuj_tabele ) {
	// stan wyjsciowy: zaczep wtyczki zaplanowany + zaczep CUDZEJ wtyczki zaplanowany
	if ( ! wp_next_scheduled( $hook ) )  { wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', $hook ); }
	if ( ! wp_next_scheduled( $obcy ) )  { wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', $obcy ); }
	if ( $plaska ) { update_option( $optust, (int) $usun_dane ); }
	else { $u = (array) get_option( $optust, array() ); $u['usun_dane'] = (int) $usun_dane; update_option( $optust, $u ); }
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { define( 'WP_UNINSTALL_PLUGIN', $slug ); }
	// uninstall NIE moze ruszyc prawdziwych tabel stosu: podstawiamy prefiks testowy
	// ORAZ kopie wp_options / wp_usermeta (patrz komentarz wyzej).
	global $wpdb; $real = $wpdb->prefix; $base = $wpdb->base_prefix;
	$kopiuj_tabele( $base . 'options', 'wpaudz_options' );
	$kopiuj_tabele( $base . 'usermeta', 'wpaudz_usermeta' );
	$wpdb->set_prefix( 'wpaudz_' ); $wpdb->options = 'wpaudz_options'; $wpdb->usermeta = 'wpaudz_usermeta';
	foreach ( $wyzeruj as $opc ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM wpaudz_options WHERE option_name = %s', $opc ) ); // phpcs:ignore
		wp_cache_delete( $opc, 'options' ); // get_option nie moze siegnac po PRAWDZIWA wartosc z cache
	}
	wp_cache_delete( 'alloptions', 'options' );
	include WP_PLUGIN_DIR . '/' . $katalog . '/uninstall.php';
	/*
	 * Skutek mierzymy TAM, GDZIE uninstall pisal: `wp_clear_scheduled_hook()` to
	 * te samo `wp_options`, ktore podstawilismy kopia, wiec `wp_next_scheduled()`
	 * po przywroceniu prawdziwej tabeli pokazywalby stan SPRZED odinstalowania
	 * (zmierzone: wszystkie cztery przypadki „cron ZOSTAJE", takze kontrolny
	 * Segregator, ktory robi to poprawnie). Czytamy wiec harmonogram z kopii.
	 */
	$surowy = $wpdb->get_var( "SELECT option_value FROM wpaudz_options WHERE option_name = 'cron'" ); // phpcs:ignore
	$wpdb->set_prefix( $real ); $wpdb->options = $base . 'options'; $wpdb->usermeta = $base . 'usermeta';
	wp_cache_flush(); // cache opcji zostal wyczyszczony przez delete_option() na kopii
	$harm = maybe_unserialize( (string) $surowy );
	$ma   = function ( $h ) use ( $harm ) {
		if ( ! is_array( $harm ) ) { return false; }
		foreach ( $harm as $kiedy => $zaczepy ) {
			if ( is_array( $zaczepy ) && isset( $zaczepy[ $h ] ) ) { return true; }
		}
		return false;
	};
	return array( 'wtyczki' => $ma( $hook ), 'obcy' => $ma( $obcy ) );
};

$r = $probuj( 'chatbot-live', 'chatbot-live/chatbot-live.php', 'chatl_czyszczenie_historii', 'chatl_ustawienia' );
if ( $r['wtyczki'] ) { zbl( '1. ChatBot: cron `chatl_czyszczenie_historii` ZOSTAJE po odinstalowaniu', 'zombie w wp_options->cron' ); }
else                 { zok( '1. ChatBot: cron zdjety przez uninstall' ); }
if ( $r['obcy'] )    { zok( '2. ChatBot: cron CUDZEJ wtyczki nietkniety' ); }
else                 { zbl( '2. ChatBot: uninstall zdjal cron CUDZEJ wtyczki', 'kasowanie za szerokie' ); }

$r2 = $probuj( 'segregator-maila', 'segregator-maila/segregator-maila.php', 'segm_czyszczenie_probki', 'segm_ustawienia' );
if ( $r2['wtyczki'] ) { zbl( '3. KONTROLA Segregator: cron ZOSTAL — metoda pomiaru podejrzana' ); }
else                  { zok( '3. KONTROLA Segregator: cron zdjety przez uninstall', 'blizniacza wtyczka robi to poprawnie' ); }

/*
 * SCIEZKA A — domyslna, czyli ta, ktora wybiera wiekszosc wlascicieli: „dane
 * ZOSTAJA" (`usun_dane = 0`). Zaczep crona nie jest dana wlasciciela, tylko
 * wpisem, w ktory WordPress uderza codziennie i trafia w nieistniejaca funkcje,
 * wiec ma zniknac TAKZE tedy. Bez tego przypadku straznik przechodzil, nawet
 * gdyby `wp_clear_scheduled_hook()` wrocilo pod wczesny `return` — dokladnie
 * ten ksztalt MIAL uninstall SKLEPU do #170 (uwaga recenzenta #167; przypadki
 * 9-12 nizej pilnuja, zeby nie wrocil).
 */
$r3 = $probuj( 'chatbot-live', 'chatbot-live/chatbot-live.php', 'chatl_czyszczenie_historii', 'chatl_ustawienia', 0 );
if ( $r3['wtyczki'] ) { zbl( '4. ChatBot, sciezka A (usun_dane=0): cron ZOSTAJE', 'zdejmowanie crona siedzi PO wczesnym return' ); }
else                  { zok( '4. ChatBot, sciezka A (dane zostaja): cron i tak zdjety' ); }
if ( $r3['obcy'] )    { zok( '5. ChatBot, sciezka A: cron CUDZEJ wtyczki nietkniety' ); }
else                  { zbl( '5. ChatBot, sciezka A: uninstall zdjal cron CUDZEJ wtyczki', 'kasowanie za szerokie' ); }

$r4 = $probuj( 'segregator-maila', 'segregator-maila/segregator-maila.php', 'segm_czyszczenie_probki', 'segm_ustawienia', 0 );
if ( $r4['wtyczki'] ) { zbl( '6. KONTROLA Segregator, sciezka A: cron ZOSTAL' ); }
else                  { zok( '6. KONTROLA Segregator, sciezka A: cron zdjety', 'blizniak trzyma ten sam warunek' ); }

/*
 * PRZYPADEK BEZ WADY dla samej METODY: sciezka A NIE MOZE skasowac danych.
 * Mierzymy to na kopii, do ktorej pisal uninstall — gdyby ktos przesunal
 * kasowanie ustawien przed `return`, zobaczymy to tutaj, a nie u klienta.
 */
$zostalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM wpaudz_options WHERE option_name = 'segm_ustawienia'" ); // phpcs:ignore
if ( 1 === $zostalo ) { zok( '7. sciezka A zostawia ustawienia wtyczki (kasuje tylko to, co ulotne)' ); }
else                  { zbl( '7. sciezka A skasowala ustawienia wtyczki', 'domyslne odinstalowanie ma ZOSTAWIC dane' ); }

/*
 * SKLEP (#170) — trzecia wtyczka z ta sama klasa wady. Sciezka B sklepu robi
 * `wp_delete_post( , true )` i `unlink()` — obie drogi pisza POZA kopiami
 * (prawdziwe wp_posts, prawdziwy dysk), wiec sa JAWNIE odciete lista $wyzeruj:
 * bez opcji stron i katalogu chronionego uninstall nie ma czego kasowac.
 * Opcja przelacznika jest PLASKA (0/1), nie tablica — stad $plaska = 1.
 */
$sklep_wyzeruj = array( 'sklepag_strona_sklep', 'sklepag_strona_dziekujemy', 'sklepag_strona_regulamin', 'sklepag_strona_polityka', 'sklepag_katalog_chroniony' );
$r5 = $probuj( 'sklep-agentow', 'sklep-agentow/sklep-agentow.php', 'sklepag_dociagnij_zalegle', 'sklepag_usun_dane_przy_odinstalowaniu', 1, 1, $sklep_wyzeruj );
if ( $r5['wtyczki'] ) { zbl( '9. SKLEP, sciezka B: cron `sklepag_dociagnij_zalegle` ZOSTAJE', 'zombie w wp_options->cron' ); }
else                  { zok( '9. SKLEP, sciezka B: cron zdjety przez uninstall' ); }
if ( $r5['obcy'] )    { zok( '10. SKLEP, sciezka B: cron CUDZEJ wtyczki nietkniety' ); }
else                  { zbl( '10. SKLEP, sciezka B: uninstall zdjal cron CUDZEJ wtyczki', 'kasowanie za szerokie' ); }

/* Sciezka A sklepu — przypadek, ktory na kodzie sprzed #170 PADA (kalibracja naprawy). */
$r6 = $probuj( 'sklep-agentow', 'sklep-agentow/sklep-agentow.php', 'sklepag_dociagnij_zalegle', 'sklepag_usun_dane_przy_odinstalowaniu', 0, 1, $sklep_wyzeruj );
if ( $r6['wtyczki'] ) { zbl( '11. SKLEP, sciezka A (usun_dane=0): cron ZOSTAJE', 'zdejmowanie crona siedzi PO wczesnym return' ); }
else                  { zok( '11. SKLEP, sciezka A (dane zostaja): cron i tak zdjety' ); }
$sklep_zostalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM wpaudz_options WHERE option_name = 'sklepag_wersja_schematu'" ); // phpcs:ignore
if ( 1 === $sklep_zostalo ) { zok( '12. SKLEP, sciezka A zostawia dane (wersja schematu przezyla)' ); }
else                        { zbl( '12. SKLEP, sciezka A skasowala dane wtyczki', 'domyslne odinstalowanie ma ZOSTAWIC dane' ); }

/* Dowod, ze prawdziwe opcje stosu przezyly ten test (patrz komentarz o kopiach). */
$klucz_zyje = null !== $wpdb->get_var( "SELECT option_id FROM {$wpdb->options} WHERE option_name = 'chatl_klucz_api'" ); // phpcs:ignore
$skrz_zyje  = null !== $wpdb->get_var( "SELECT option_id FROM {$wpdb->options} WHERE option_name = 'segm_skrzynka'" ); // phpcs:ignore
if ( $klucz_zyje && $skrz_zyje ) { zok( '8. PRAWDZIWE opcje stosu nietkniete (klucz API, skrzynka)' ); }
else                             { zbl( '8. test skasowal PRAWDZIWE opcje stosu', 'klucz=' . ( $klucz_zyje ? 'jest' : 'ZNIKL' ) . ' skrzynka=' . ( $skrz_zyje ? 'jest' : 'ZNIKLA' ) ); }

/* sprzatanie: zdejmij zaczep testowy, przywroc stan wyjsciowy cronow */
wp_clear_scheduled_hook( $obcy );
foreach ( $stan as $h => $kiedy ) { if ( $kiedy && ! wp_next_scheduled( $h ) ) { wp_schedule_event( $kiedy, 'daily', $h ); } }
foreach ( (array) $wpdb->get_col( "SHOW TABLES LIKE 'wpaudz\\_%'" ) as $t ) { $wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $t ) . "`" ); }
wp_cache_flush();

echo "\n";
if ( 0 === $GLOBALS['B'] ) { echo "WSZYSTKIE KROKI PRZESZLY — odinstalowanie nie zostawia zombie w cronie\n"; exit( 0 ); }
printf( "PRZYPADKOW NIEZALICZONYCH: %d\n", $GLOBALS['B'] ); exit( 1 );
