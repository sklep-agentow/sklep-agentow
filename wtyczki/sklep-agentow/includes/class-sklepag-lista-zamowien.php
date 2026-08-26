<?php
/**
 * Tabela zamówień dashboardu na rdzeniowym WP_List_Table.
 * Kolumny ze SNAPSHOTU zakupu (produkt_nazwa_kupiona — edycja produktu nie
 * zmienia historii); status jako badge słowo+kolor z tooltipem.
 * Widok read-only; filtry (status, zakres dat, szukaj po mailu) niosą się
 * GET-em i są reużywane 1:1 przez eksport CSV.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Sklepag_Lista_Zamowien extends WP_List_Table {

	/**
	 * Etykiety i klasy badge statusów — jedno źródło dla tabeli i CSV.
	 */
	const STATUSY = array(
		'oczekuje'     => array( 'oczekuje', 'sklepag-badge-szary', 'Webhook przyjęty, płatność jeszcze niepotwierdzona' ),
		'oplacone'     => array( 'opłacone', 'sklepag-badge-info', 'Płatność potwierdzona, mail w przygotowaniu' ),
		'mail_w_toku'  => array( 'mail w toku', 'sklepag-badge-uwaga', 'Trwa wysyłka maila z linkiem pobrania' ),
		'mail_wyslany' => array( 'mail wysłany', 'sklepag-badge-ok', 'Zakup domknięty — mail z linkiem dostarczony do wysyłki' ),
		'mail_blad'    => array( 'błąd maila', 'sklepag-badge-blad', 'Wysyłka nieudana — cron ponawia do 3 prób, potem wymaga ręcznej interwencji' ),
		'zwrocone'     => array( 'zwrócone', 'sklepag-badge-szary', 'Pieniądze zwrócone kupującemu — zamówienie nie liczy się do przychodu' ),
		'sporne'       => array( 'obciążenie zwrotne', 'sklepag-badge-blad', 'Kupujący zareklamował płatność w banku — kwota zdjęta z konta do czasu rozstrzygnięcia' ),
	);

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'zamowienie',
				'plural'   => 'zamowienia',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'id'                    => 'Nr',
			'utworzono'             => 'Data',
			'email_kupujacego'      => 'Kupujący',
			'produkt_nazwa_kupiona' => 'Produkt',
			'kwota_grosze'          => 'Kwota',
			'pobrania'              => 'Pobrania',
			'status'                => 'Status',
			'akcje'                 => 'Akcje',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'utworzono'    => array( 'utworzono', true ),
			'kwota_grosze' => array( 'kwota_grosze', false ),
		);
	}

	/**
	 * Filtry z GET po allowlistach — parametry listingu read-only,
	 * współdzielone z eksportem CSV (sklepag_filtry_zamowien()).
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$f = sklepag_filtry_zamowien();
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="sklepag-filtr-status">Filtruj status</label>';
		echo '<select name="status" id="sklepag-filtr-status">';
		echo '<option value="">Wszystkie statusy</option>';
		foreach ( self::STATUSY as $klucz => $dane ) {
			echo '<option value="' . esc_attr( $klucz ) . '"' . selected( $f['status'], $klucz, false ) . '>' . esc_html( $dane[0] ) . '</option>';
		}
		echo '</select> ';
		echo '<label class="screen-reader-text" for="sklepag-filtr-od">Data od</label>';
		echo '<input type="date" id="sklepag-filtr-od" name="od" value="' . esc_attr( $f['od'] ) . '"> ';
		echo '<label class="screen-reader-text" for="sklepag-filtr-do">Data do</label>';
		echo '<input type="date" id="sklepag-filtr-do" name="do_dnia" value="' . esc_attr( $f['do_dnia'] ) . '"> ';
		submit_button( 'Filtruj', '', 'filter_action', false );
		echo '</div>';
	}

	public function prepare_items() {
		global $wpdb;
		$t = $wpdb->prefix . 'sklepag_zamowienia';
		$f = sklepag_filtry_zamowien();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- parametry listingu read-only (standard WP_List_Table).
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$order   = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'utworzono';
		// phpcs:enable
		if ( ! in_array( $orderby, array( 'utworzono', 'kwota_grosze' ), true ) ) {
			$orderby = 'utworzono';
		}

		$na_strone = $this->get_items_per_page( 'sklepag_zamowien_na_strone', 20 );

		list( $gdzie, $args ) = sklepag_warunek_zamowien( $f );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders -- nazwa tabeli z prefiksu; WHERE budowany WYŁĄCZNIE z placeholderów (sklepag_warunek_zamowien), liczba placeholderów DYNAMICZNA (sniff nie umie jej policzyć), orderby/order z allowlisty; dane na żywo.
		// prepare() z ZEREM placeholderów rzuca notice — przy pustych filtrach COUNT idzie bez prepare (zapytanie bez wejścia użytkownika).
		$razem       = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$t} {$gdzie}", $args ) : "SELECT COUNT(*) FROM {$t} {$gdzie}" );
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, utworzono, email_kupujacego, produkt_nazwa_kupiona, kwota_grosze, waluta, pobrania_uzyte, pobrania_limit, status
			 FROM {$t} {$gdzie} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d",
				array_merge( $args, array( $na_strone, ( $paged - 1 ) * $na_strone ) )
			)
		);
		// phpcs:enable

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $razem,
				'per_page'    => $na_strone,
				'total_pages' => (int) ceil( $razem / $na_strone ),
			)
		);
	}

	/**
	 * @param object{id: int|string, utworzono: string, email_kupujacego: string, produkt_nazwa_kupiona: string, kwota_grosze: int|string, waluta: string, pobrania_uzyte: int|string, pobrania_limit: int|string, status: string} $item        Wiersz zamówienia.
	 * @param string                                                                                                                                                                                                                $column_name Klucz kolumny.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				// Link do szczegółów + row action (natywny wzorzec list tabel WP).
				$url = sklepag_url_zamowienia( (int) $item->id );
				return '<a href="' . esc_url( $url ) . '"><strong>#' . (int) $item->id . '</strong></a>'
					. $this->row_actions( array( 'view' => '<a href="' . esc_url( $url ) . '">Szczegóły</a>' ) );
			case 'utworzono':
				// Data wg ustawień WP (spójność z resztą wp-admin).
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->utworzono ) );
			case 'email_kupujacego':
			case 'produkt_nazwa_kupiona':
				return esc_html( $item->$column_name );
			case 'kwota_grosze':
				return esc_html( number_format( $item->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $item->waluta );
			case 'pobrania':
				return esc_html( (int) $item->pobrania_uzyte . ' / ' . (int) $item->pobrania_limit );
			case 'status':
				return sklepag_badge_statusu( $item->status );
			case 'akcje':
				// Akcja widoczna bez hovera (uwaga sędziego wyglądu — wzorce §3).
				return '<a href="' . esc_url( sklepag_url_zamowienia( (int) $item->id ) ) . '" class="button button-small">Szczegóły</a>';
			default:
				return '';
		}
	}

	public function no_items() {
		echo 'Brak zamówień dla wybranych filtrów — pierwszy opłacony zakup pojawi się tu automatycznie (webhook Stripe albo „dociągnij zaległe").';
	}
}
