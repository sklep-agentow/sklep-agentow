<?php
/**
 * Odinstalowanie Segregatora maila.
 * Domyślnie dane ZOSTAJĄ; pełne czyszczenie tylko przy zaznaczonej opcji.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$segm_ust = get_option( 'segm_ustawienia', array() );

// Harmonogram retencji zdejmuje już deaktywacja, ale odinstalowanie ma zostawić
// czysto także wtedy, gdy wtyczkę usunięto z pominięciem tamtej ścieżki
// (np. skasowanie katalogu i „usuń" z listy) — martwy wpis w cronie tylko
// sypie błędami „nie ma takiej funkcji".
wp_clear_scheduled_hook( 'segm_czyszczenie_probki' );
wp_clear_scheduled_hook( 'segm_skrzynka_cron' );

// Znacznik „operacja na próbce w toku" to stan chwilowy, nie dane użytkownika —
// kasujemy ZAWSZE, żeby po odinstalowaniu nie został w bazie wpis bez wtyczki,
// która umiałaby go zwolnić.
delete_option( 'segm_operacja_w_toku' );

// Transienty panelu nazwane są per użytkownik (`segm_komunikat_<ID>` —
// komunikaty skrzynki, `segm_liczenie_<ID>` — wynik liczenia trafień reguły),
// więc nie da się ich skasować jednym delete_transient(): nazwy nie znamy
// z góry. Potrafią zawierać nazwy folderów cudzej skrzynki i zapis rozmowy
// z serwerem poczty (bez hasła), więc znikają ZAWSZE, nie tylko przy „usuń
// wszystkie dane". Kasujemy też wiersze `_transient_timeout_*`, bo to osobne
// wpisy w wp_options i bez nich zostałyby sieroty.
foreach ( array( 'segm_komunikat_', 'segm_liczenie_' ) as $segm_rodzina ) {
	foreach ( array( '_transient_', '_transient_timeout_' ) as $segm_przedrostek ) {
		$segm_wzor = $wpdb->esc_like( $segm_przedrostek . $segm_rodzina ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $segm_wzor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

// Stan ekranu powitalnego to stan INTERFEJSU, nie dane właściciela: znika
// zawsze, żeby po ponownej instalacji tej samej wersji wstęp pokazał się od nowa
// i żeby w wp_usermeta nie zostały sieroty po wtyczce, której już nie ma.
delete_option( 'segm_powitanie_gotowe' );
delete_metadata( 'user', 0, 'segm_powitanie_widziane', '', true );

// Sekret właściciela (klucz API) kasowany ZAWSZE, niezależnie od opcji „usuń dane"
// (zasada D22: poświadczenia ≠ dane użytkowe; martwa wtyczka nie zostawia klucza
// w bazie — znalezisko wrogiej sesji botów 19.08, zmierzone na czystym WP).
delete_option( 'segm_klucz_api' );

// HASŁO DO CUDZEJ SKRZYNKI — kasowane ZAWSZE i bezwarunkowo, niezależnie od
// opcji „usuń dane". Poświadczenia do konta pocztowego osoby trzeciej to
// najcięższy sekret, jaki ta wtyczka trzyma; odinstalowana wtyczka nie ma
// prawa zostawić go w bazie ani w jej kopii zapasowej.
delete_option( 'segm_imap_haslo' );

// Zgoda na REALNE przenoszenie wiadomości też znika zawsze: po ponownej
// instalacji wtyczka ma wrócić do trybu NA SUCHO i poprosić o tę decyzję
// od nowa, a nie odziedziczyć potwierdzenie sprzed odinstalowania.
delete_option( 'segm_zgoda_przenoszenie' );

// Zgoda na wysyłkę próbki do AI też znika ZAWSZE: po ponownej instalacji
// właściciel ma podjąć tę decyzję świadomie od nowa, a nie odziedziczyć
// potwierdzenie sprzed odinstalowania.
delete_option( 'segm_zgoda_ai' );

if ( empty( $segm_ust['usun_dane'] ) ) {
	return;
}
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_kategorie" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_reguly" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_przebiegi" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_ai_uzycie" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_skrz_przebiegi" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_skrz_ruchy" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'segm_ustawienia' );
delete_option( 'segm_skrzynka' );
delete_option( 'segm_wersja_schematu' );

// Ślad „baner powitalny schowany" trzymany per użytkownik — bez tego zostałyby
// osierocone wiersze w wp_usermeta po wtyczce, której już nie ma.
delete_metadata( 'user', 0, 'segm_baner_ukryty', '', true );
