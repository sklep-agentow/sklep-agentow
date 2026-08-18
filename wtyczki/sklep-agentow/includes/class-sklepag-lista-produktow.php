<?php
/**
 * Tabela produktów (K4) na rdzeniowym WP_List_Table. Akcje wierszowe:
 * Edytuj + Deaktywuj/Aktywuj (usuwania NIE ma — D7: historia zamówień
 * trzyma snapshot, produkt najwyżej znika ze sprzedaży).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Sklepag_Lista_Produktow extends WP_List_Table {

	const NA_STRONE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'produkt',
				'plural'   => 'produkty',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'id'          => 'Nr',
			'nazwa'       => 'Nazwa',
			'cena_grosze' => 'Cena',
			'plik'        => 'Plik paczki',
			'aktywny'     => 'W sprzedaży',
			'zmieniono'   => 'Zmieniono',
			'akcje'       => 'Akcje',
		);
	}

	public function prepare_items() {
		global $wpdb;
		$t = $wpdb->prefix . 'sklepag_produkty';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- paginacja listingu read-only.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; CRUD na żywo.
		$razem       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, nazwa, cena_grosze, waluta, plik_sciezka, plik_rozmiar, aktywny, zmieniono FROM {$t} ORDER BY aktywny DESC, nazwa ASC LIMIT %d OFFSET %d",
				self::NA_STRONE,
				( $paged - 1 ) * self::NA_STRONE
			)
		);
		// phpcs:enable

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => $razem,
				'per_page'    => self::NA_STRONE,
				'total_pages' => (int) ceil( $razem / self::NA_STRONE ),
			)
		);
	}

	/**
	 * @param object{id: int|string, nazwa: string, cena_grosze: int|string, waluta: string, plik_sciezka: string, plik_rozmiar: int|string, aktywny: int|string, zmieniono: string} $item        Wiersz produktu.
	 * @param string                                                                                                                                                                 $column_name Klucz kolumny.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return '#' . (int) $item->id;
			case 'nazwa':
				return '<a href="' . esc_url( sklepag_url_produktu( (int) $item->id ) ) . '"><strong>' . esc_html( $item->nazwa ) . '</strong></a>';
			case 'cena_grosze':
				return esc_html( number_format( $item->cena_grosze / 100, 2, ',', ' ' ) . ' ' . $item->waluta );
			case 'plik':
				if ( '' === $item->plik_sciezka ) {
					return '<span class="sklepag-badge sklepag-badge-blad">brak</span>';
				}
				$jest = file_exists( $item->plik_sciezka );
				return $jest
					? '<span class="sklepag-badge sklepag-badge-ok">jest</span> <span class="sklepag-szary">' . esc_html( size_format( (int) $item->plik_rozmiar ) ) . '</span>'
					: '<span class="sklepag-badge sklepag-badge-blad" title="Ścieżka zapisana, ale pliku nie ma na dysku">zgubiony</span>';
			case 'aktywny':
				return $item->aktywny
					? '<span class="sklepag-badge sklepag-badge-ok">w sprzedaży</span>'
					: '<span class="sklepag-badge sklepag-badge-szary">wyłączony</span>';
			case 'zmieniono':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->zmieniono ) );
			case 'akcje':
				$edytuj   = '<a href="' . esc_url( sklepag_url_produktu( (int) $item->id ) ) . '" class="button button-small">Edytuj</a> ';
				$przelacz = wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_produkt_przelacz&produkt=' . (int) $item->id ), 'sklepag_produkt_przelacz' );
				$edytuj  .= $item->aktywny
					? '<a href="' . esc_url( $przelacz ) . '" class="button button-small">Deaktywuj</a>'
					: '<a href="' . esc_url( $przelacz ) . '" class="button button-small">Aktywuj</a>';
				return $edytuj;
			default:
				return '';
		}
	}

	public function no_items() {
		echo 'Brak produktów — dodaj pierwszy przyciskiem „Dodaj produkt" u góry.';
	}
}
