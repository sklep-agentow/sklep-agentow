<?php
/**
 * Provider wp-sitemaps dla produktów sklepu (K5 PR 2; PLAN-WYGLADU-K5 §7).
 * Produkty żyją we własnej tabeli (nie CPT), więc rdzeń ich nie widzi —
 * oficjalna droga to własny WP_Sitemaps_Provider (krytyk #5).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sklepag_Sitemap_Produkty extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'sklepagprodukty';
		$this->object_type = 'sklepagprodukty';
	}

	/**
	 * Lista URL-i aktywnych produktów (wolumen Z6: pojedyncze produkty —
	 * jedna strona sitemapy wystarcza na zawsze).
	 *
	 * @param int    $page_num Numer strony sitemapy.
	 * @param string $object_subtype Nieużywane (brak podtypów).
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( $page_num > 1 ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- własna tabela (prefiks WP, zero wejścia użytkownika); sitemap ma być świeży po CRUD.
		$slugi = $wpdb->get_col( "SELECT slug FROM {$wpdb->prefix}sklepag_produkty WHERE aktywny = 1 ORDER BY id" );

		$lista = array();
		foreach ( $slugi as $slug ) {
			$lista[] = array( 'loc' => sklepag_url_produktu_front( $slug ) );
		}
		return $lista;
	}

	/**
	 * Liczba stron sitemapy (Z6: zawsze 1).
	 *
	 * @param string $object_subtype Nieużywane.
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
