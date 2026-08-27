<?php
/**
 * Panel: moderacja opinii. Lista wg statusu + przyjmij/odrzuć.
 * Publikacja na stronie produktu = wyłącznie status 'przyjeta'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ekran „Opinie": zakładki statusów + tabela + akcje.
 */
function sklepag_admin_opinie() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.' ), 'Sklep Agentów', 403 );
	}
	global $wpdb;
	$to = $wpdb->prefix . 'sklepag_opinie';
	$tp = $wpdb->prefix . 'sklepag_produkty';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- własna tabela; widok moderacji musi być świeży.
	$liczby = array(
		'oczekuje'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$to} WHERE status='oczekuje'" ),
		'przyjeta'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$to} WHERE status='przyjeta'" ),
		'odrzucona' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$to} WHERE status='odrzucona'" ),
		'zalazek'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$to} WHERE status='zalazek'" ),
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sam filtr widoku (GET), zero zmiany stanu.
	$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'oczekuje';
	if ( ! in_array( $status, array( 'oczekuje', 'przyjeta', 'odrzucona' ), true ) ) {
		$status = 'oczekuje';
	}

	$pz      = $wpdb->prefix . 'sklepag_zamowienia';
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT o.*, p.nazwa AS produkt_nazwa, z.usunieto_dnia AS rodo_usunieto
			 FROM {$to} o LEFT JOIN {$tp} p ON p.id = o.produkt_id LEFT JOIN {$pz} z ON z.id = o.zamowienie_id
			 WHERE o.status = %s ORDER BY o.zmieniono DESC LIMIT 200",
			$status
		)
	);
	// phpcs:enable

	echo '<div class="wrap"><h1>Opinie</h1>';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- odczyt komunikatu z redirectu, zero zmiany stanu.
	if ( isset( $_GET['sklepag_ok'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Zapisane.</p></div>';
	} elseif ( isset( $_GET['sklepag_nic'] ) ) {
		echo '<div class="notice notice-warning is-dismissible"><p>Nic się nie zmieniło — wiersz był już w tym stanie (podwójne kliknięcie?) albo nie istnieje.</p></div>';
	}
	// phpcs:enable

	$baza = admin_url( 'admin.php?page=sklepag-opinie' );
	echo '<ul class="subsubsub">';
	foreach ( array(
		'oczekuje'  => 'Oczekujące',
		'przyjeta'  => 'Przyjęte',
		'odrzucona' => 'Odrzucone',
	) as $s => $etykieta ) {
		echo '<li><a href="' . esc_url( add_query_arg( 'status', $s, $baza ) ) . '"' . ( $status === $s ? ' class="current"' : '' ) . '>'
			. esc_html( $etykieta ) . ' <span class="count">(' . (int) $liczby[ $s ] . ')</span></a>'
			. ( 'odrzucona' === $s ? '' : ' |' ) . ' </li>';
	}
	echo '</ul>';
	echo '<p class="description" style="clear:both">Wysłane maile z linkiem do opinii, na które nikt jeszcze nie odpowiedział: ' . (int) $liczby['zalazek'] . '. Opinia pojawia się na stronie produktu wyłącznie po przyjęciu.</p>';

	if ( empty( $wiersze ) ) {
		echo '<p><em>Brak opinii w tym widoku.</em></p></div>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr><th>Produkt</th><th>Imię</th><th>Ocena</th><th>Tytuł i treść</th><th>Zamówienie</th><th>Zmieniono</th><th>Akcje</th></tr></thead><tbody>';
	foreach ( $wiersze as $w ) {
		echo '<tr>';
		echo '<td>' . esc_html( '' !== (string) $w->produkt_nazwa ? $w->produkt_nazwa : ( '#' . (int) $w->produkt_id ) ) . '</td>';
		echo '<td>' . esc_html( $w->imie ) . '</td>';
		echo '<td>' . esc_html( str_repeat( '★', max( 1, min( 5, (int) $w->gwiazdki ) ) ) ) . ' (' . (int) $w->gwiazdki . '/5)</td>';
		echo '<td>' . ( '' !== (string) $w->tytul ? '<strong>' . esc_html( $w->tytul ) . '</strong><br>' : '' ) . esc_html( (string) $w->tresc ) . '</td>';
		echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=sklepag-zamowienie&id=' . (int) $w->zamowienie_id ) ) . '">#' . (int) $w->zamowienie_id . '</a></td>';
		echo '<td>' . esc_html( (string) $w->zmieniono ) . '</td>';
		echo '<td>';
		if ( ! empty( $w->rodo_usunieto ) ) {
			// Kupujący zanonimizowany (RODO): moderacja odmawia (sklepag_opinia_moderuj),
			// więc zamiast przycisków — jasna etykieta. Wiersz nie może wyglądać jak
			// zwykłe odrzucenie, które wolno cofnąć.
			echo '<span class="description">[ ZANONIMIZOWANA — RODO ] bez moderacji</span>';
			echo '</td></tr>';
			continue;
		}
		foreach ( array(
			'przyjmij' => 'Przyjmij',
			'odrzuc'   => 'Odrzuć',
		) as $akcja => $etykieta ) {
			if ( ( 'przyjmij' === $akcja && 'przyjeta' === $w->status ) || ( 'odrzuc' === $akcja && 'odrzucona' === $w->status ) ) {
				continue; // Bez przycisku na stan, w którym wiersz już jest.
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:6px">';
			echo '<input type="hidden" name="action" value="sklepag_opinia_moderuj">';
			echo '<input type="hidden" name="opinia_id" value="' . (int) $w->id . '">';
			echo '<input type="hidden" name="akcja" value="' . esc_attr( $akcja ) . '">';
			echo '<input type="hidden" name="status_powrotu" value="' . esc_attr( $status ) . '">';
			wp_nonce_field( 'sklepag_opinia_moderuj' );
			echo '<button type="submit" class="button' . ( 'przyjmij' === $akcja ? ' button-primary' : '' ) . '">' . esc_html( $etykieta ) . '</button>';
			echo '</form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Akcja moderacji (admin-post, nonce + uprawnienia).
 */
function sklepag_obsluz_moderacje_opinii() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.' ), 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_opinia_moderuj' );

	$id     = isset( $_POST['opinia_id'] ) ? (int) $_POST['opinia_id'] : 0;
	$akcja  = isset( $_POST['akcja'] ) ? sanitize_key( $_POST['akcja'] ) : '';
	$status = isset( $_POST['status_powrotu'] ) ? sanitize_key( $_POST['status_powrotu'] ) : 'oczekuje';

	$ok = sklepag_opinia_moderuj( $id, $akcja );

	// „Zapisane." tylko gdy wiersz NAPRAWDĘ zmienił stan — podwójne kliknięcie,
	// nieistniejące id i przegrany wyścig CAS dostają uczciwe ostrzeżenie.
	wp_safe_redirect(
		add_query_arg(
			array(
				'status'                               => $status,
				( $ok ? 'sklepag_ok' : 'sklepag_nic' ) => 1,
			),
			admin_url( 'admin.php?page=sklepag-opinie' )
		)
	);
	exit;
}
add_action( 'admin_post_sklepag_opinia_moderuj', 'sklepag_obsluz_moderacje_opinii' );
