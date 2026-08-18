<?php
/**
 * Odinstalowanie Segregatora maila.
 * Domyślnie dane ZOSTAJĄ; pełne czyszczenie tylko przy zaznaczonej opcji.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$segm_ust = get_option( 'segm_ustawienia', array() );
if ( empty( $segm_ust['usun_dane'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_kategorie" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_reguly" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}segm_przebiegi" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'segm_ustawienia' );
delete_option( 'segm_wersja_schematu' );
