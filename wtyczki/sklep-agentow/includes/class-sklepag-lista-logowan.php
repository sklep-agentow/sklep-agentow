<?php
/**
 * Tabela dziennika logowań (S-09) na rdzeniowym WP_List_Table —
 * paginacja/sortowanie/wygląd 1:1 z resztą wp-admin (wzorce K3).
 * Widok WYŁĄCZNIE do odczytu: zero mutacji, zero akcji wierszowych.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Sklepag_Lista_Logowan extends WP_List_Table {

	const NA_STRONE = 50;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'logowanie',
				'plural'   => 'logowania',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'czas'          => 'Czas',
			'login_wpisany' => 'Login',
			'wynik'         => 'Wynik',
			'ip'            => 'IP',
			'user_agent'    => 'Przeglądarka',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'czas' => array( 'czas', true ),
		);
	}

	public function prepare_items() {
		global $wpdb;
		$t = $wpdb->prefix . 'sklepag_logowania';

		// Odczyt parametrów listingu z GET — standard WP_List_Table (sortowanie/paginacja
		// to linki, nie formularz z nonce); widok read-only za manage_options.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable
		// Kolumna sortowania ze sztywnej allowlisty (jedyna sortowalna: czas).
		$orderby = 'czas';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu, orderby/order z allowlisty; dziennik czytany na żywo.
		$razem       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT czas, login_wpisany, wynik, ip, user_agent FROM {$t} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d",
				self::NA_STRONE,
				( $paged - 1 ) * self::NA_STRONE
			)
		);
		// phpcs:enable

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $razem,
				'per_page'    => self::NA_STRONE,
				'total_pages' => (int) ceil( $razem / self::NA_STRONE ),
			)
		);
	}

	/**
	 * @param object{czas: string, login_wpisany: string, wynik: string, ip: string, user_agent: string} $item        Wiersz dziennika (stdClass z get_results).
	 * @param string                                                                                     $column_name Klucz kolumny.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'czas':
			case 'login_wpisany':
			case 'ip':
				return esc_html( $item->$column_name );
			case 'user_agent':
				return esc_html( mb_substr( $item->user_agent, 0, 80 ) );
			default:
				return '';
		}
	}

	/**
	 * Wynik jako kolorowy badge (wzorce K3: nigdy goły tekst) — słowo + kolor,
	 * żeby status nie był niesiony SAMYM kolorem (WCAG).
	 */
	public function column_wynik( $item ) {
		if ( 'ok' === $item->wynik ) {
			return '<span class="sklepag-badge sklepag-badge-ok">udane</span>';
		}
		return '<span class="sklepag-badge sklepag-badge-blad">nieudane</span>';
	}

	/**
	 * Pusty stan z wskazówką (wzorce K3), nie goła tabela.
	 */
	public function no_items() {
		echo 'Dziennik jest pusty — wpisy pojawią się przy pierwszym (udanym lub nieudanym) logowaniu na konto.';
	}
}
