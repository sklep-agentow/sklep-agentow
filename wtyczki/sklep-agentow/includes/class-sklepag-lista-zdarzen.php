<?php
/**
 * Tabela dziennika zdarzeń: log AKCJI adminów (CRUD/resend/eksport — S-09
 * rozszerzenie) + zdarzenia systemowe (webhooki, maile, pobrania) w jednej
 * osi czasu, z filtrem źródła. Widok wyłącznie do odczytu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Sklepag_Lista_Zdarzen extends WP_List_Table {

	const NA_STRONE = 50;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'zdarzenie',
				'plural'   => 'zdarzenia',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'czas'          => 'Czas',
			'typ'           => 'Typ',
			'zamowienie_id' => 'Zamówienie',
			'uzytkownik'    => 'Kto',
			'opis'          => 'Opis',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'czas' => array( 'czas', true ),
		);
	}

	/**
	 * Filtr źródła nad tabelą: wszystkie / akcje adminów / systemowe.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$zrodlo = $this->zrodlo();
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="sklepag-zrodlo">Filtruj źródło zdarzeń</label>';
		echo '<select name="zrodlo" id="sklepag-zrodlo">';
		echo '<option value=""' . selected( $zrodlo, '', false ) . '>Wszystkie zdarzenia</option>';
		echo '<option value="admin"' . selected( $zrodlo, 'admin', false ) . '>Tylko akcje adminów</option>';
		echo '<option value="system"' . selected( $zrodlo, 'system', false ) . '>Tylko systemowe</option>';
		echo '</select> ';
		submit_button( 'Filtruj', '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Źródło z GET po allowlist ('' / admin / system) — listing read-only.
	 */
	private function zrodlo() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtr listingu read-only.
		$z = isset( $_GET['zrodlo'] ) ? sanitize_key( wp_unslash( $_GET['zrodlo'] ) ) : '';
		return in_array( $z, array( 'admin', 'system' ), true ) ? $z : '';
	}

	public function prepare_items() {
		global $wpdb;
		$t = $wpdb->prefix . 'sklepag_zdarzenia';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- parametry listingu read-only (standard WP_List_Table).
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable

		$zrodlo = $this->zrodlo();
		$gdzie  = '';
		if ( 'admin' === $zrodlo ) {
			$gdzie = "WHERE uzytkownik <> ''";
		} elseif ( 'system' === $zrodlo ) {
			$gdzie = "WHERE uzytkownik = ''";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; WHERE/order ze sztywnych allowlist; dziennik na żywo.
		$razem       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$gdzie}" );
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT czas, typ, zamowienie_id, uzytkownik, opis FROM {$t} {$gdzie} ORDER BY czas {$order}, id {$order} LIMIT %d OFFSET %d",
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
	 * @param object{czas: string, typ: string, zamowienie_id: int|string|null, uzytkownik: string, opis: string} $item        Wiersz zdarzenia (stdClass z get_results).
	 * @param string                                                                                              $column_name Klucz kolumny.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'czas':
			case 'typ':
				return esc_html( $item->$column_name );
			case 'zamowienie_id':
				return $item->zamowienie_id ? '#' . (int) $item->zamowienie_id : '—';
			case 'uzytkownik':
				return '' !== $item->uzytkownik
					? '<span class="sklepag-badge sklepag-badge-admin">' . esc_html( $item->uzytkownik ) . '</span>'
					: '<span class="sklepag-szary">system</span>';
			case 'opis':
				return esc_html( mb_substr( $item->opis, 0, 160 ) );
			default:
				return '';
		}
	}

	public function no_items() {
		echo 'Brak zdarzeń dla wybranego filtra — oś czasu wypełni się przy zakupach i akcjach adminów.';
	}
}
