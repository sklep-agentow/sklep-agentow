<?php
/**
 * Odinstalowanie ChatBot LIVE.
 * Domyślnie dane ZOSTAJĄ (właściciel może wrócić bez strat).
 * Pełne czyszczenie tylko, gdy w ustawieniach zaznaczono „usuń wszystkie dane".
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$chatl_ust = get_option( 'chatl_ustawienia', array() );

// Sekret właściciela (klucz API) kasowany ZAWSZE, niezależnie od opcji „usuń dane":
// poświadczenie to nie są dane użytkowe, a martwa wtyczka nie ma prawa zostawiać
// klucza w bazie.
delete_option( 'chatl_klucz_api' );

if ( empty( $chatl_ust['usun_dane'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_qa" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_zgloszenia" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_rozmowy" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_ai_uzycie" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_rate_limit" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'chatl_ustawienia' );
delete_option( 'chatl_wersja_schematu' );
delete_option( 'chatl_blad_maila' );

// Znacznik „widziałem ekran powitalny" leży przy KAŻDYM administratorze osobno,
// więc kasujemy go dla wszystkich naraz ($delete_all = true). Bez tego po pełnym
// czyszczeniu zostawałyby sieroty w wp_usermeta.
delete_metadata( 'user', 0, 'chatl_powitanie_zamkniete', '', true );

// Transienty rate-limitu (chatl_rl_*) ze STARSZYCH wersji wtyczki (≤1.1.x —
// od 1.2.0 licznik żyje w tabeli): esc_like() eskejpuje podkreślnik (SQL
// wildcard) + prepare — wzorzec Handbook, żeby nie złapać cudzych wpisów
// o podobnej nazwie.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_chatl_rl_' ) . '%', $wpdb->esc_like( '_transient_timeout_chatl_rl_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
