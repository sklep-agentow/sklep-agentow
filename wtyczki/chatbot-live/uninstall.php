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
if ( empty( $chatl_ust['usun_dane'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_qa" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_zgloszenia" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chatl_rozmowy" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'chatl_ustawienia' );
delete_option( 'chatl_wersja_schematu' );

// Transienty rate-limitu (chatl_rl_*): podkreślniki w LIKE escapowane (\_),
// żeby nie złapać cudzych wpisów o podobnej nazwie.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_chatl\_rl\_%' OR option_name LIKE '\_transient\_timeout\_chatl\_rl\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
