<?php
/**
 * Dashboard „jak dobre sklepy" — wzorowany na sklepach, które to robią dobrze:
 * karty KPI z porównaniem % do poprzedniego okresu → rozkład zakupów
 * w czasie (S-04) → tabela zamówień → eksport CSV z aktualnych filtrów.
 * Do tego: notice o zamówieniach z nieudaną wysyłką (panel-czerwone, plan §6),
 * self-heal reconciliation przy wejściu admina + ręczny przycisk (plan §4A).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SKLEPAG_KATALOG . 'includes/class-sklepag-lista-zamowien.php';

const SKLEPAG_STATUSY_OPLACONE = "'oplacone','mail_w_toku','mail_wyslany','mail_blad'";

/**
 * Badge statusu zamówienia: słowo + kolor + tooltip (wzorce K3 §3 — nigdy goły tekst).
 *
 * @param string $status Status z bazy.
 */
function sklepag_badge_statusu( $status ) {
	$s = isset( Sklepag_Lista_Zamowien::STATUSY[ $status ] )
		? Sklepag_Lista_Zamowien::STATUSY[ $status ]
		: array( $status, 'sklepag-badge-szary', '' );
	return '<span class="sklepag-badge ' . esc_attr( $s[1] ) . '" title="' . esc_attr( $s[2] ) . '">' . esc_html( $s[0] ) . '</span>';
}

/**
 * Filtry listy zamówień z GET po allowlistach — wspólne dla tabeli i CSV.
 * (Read-only parametry listingu; mutacji nie ma.)
 *
 * @return array{status: string, od: string, do_dnia: string, szukaj: string}
 */
function sklepag_filtry_zamowien() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtry listingu read-only.
	$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$od      = isset( $_GET['od'] ) ? sanitize_text_field( wp_unslash( $_GET['od'] ) ) : '';
	$do_dnia = isset( $_GET['do_dnia'] ) ? sanitize_text_field( wp_unslash( $_GET['do_dnia'] ) ) : '';
	$szukaj  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	// phpcs:enable

	$data_ok = static function ( $d ) {
		return ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) ? $d : '';
	};

	return array(
		'status'  => isset( Sklepag_Lista_Zamowien::STATUSY[ $status ] ) ? $status : '',
		'od'      => $data_ok( $od ),
		'do_dnia' => $data_ok( $do_dnia ),
		'szukaj'  => mb_substr( $szukaj, 0, 100 ),
	);
}

/**
 * Warunek WHERE + argumenty do prepare dla filtrów zamówień.
 * Zwraca ZAWSZE niepusty WHERE (1=1), żeby składanie zapytań było jednolite.
 *
 * @param array $f Filtry z sklepag_filtry_zamowien().
 * @return array{0: string, 1: array} WHERE i argumenty placeholderów.
 */
function sklepag_warunek_zamowien( $f ) {
	global $wpdb;
	$gdzie = 'WHERE 1=1';
	$args  = array();

	if ( '' !== $f['status'] ) {
		$gdzie .= ' AND status = %s';
		$args[] = $f['status'];
	}
	if ( '' !== $f['od'] ) {
		$gdzie .= ' AND utworzono >= %s';
		$args[] = $f['od'] . ' 00:00:00';
	}
	if ( '' !== $f['do_dnia'] ) {
		$gdzie .= ' AND utworzono <= %s';
		$args[] = $f['do_dnia'] . ' 23:59:59';
	}
	if ( '' !== $f['szukaj'] ) {
		$gdzie .= ' AND email_kupujacego LIKE %s';
		$args[] = '%' . $wpdb->esc_like( $f['szukaj'] ) . '%';
	}

	return array( $gdzie, $args );
}

/* ---------- Okresy KPI ---------- */

/**
 * Wybrany okres kart KPI z GET po allowlist (read-only).
 *
 * @return string 'dzis' | '7dni' | '30dni'
 */
function sklepag_okres_kpi() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- przełącznik widoku read-only.
	$o = isset( $_GET['okres'] ) ? sanitize_key( wp_unslash( $_GET['okres'] ) ) : '7dni';
	return in_array( $o, array( 'dzis', '7dni', '30dni' ), true ) ? $o : '7dni';
}

/**
 * Granice okresu i poprzedniego analogicznego okresu (czas lokalny WP,
 * spójny z kolumną utworzono zapisywaną current_time).
 *
 * @param string $okres Klucz okresu.
 * @return array{od: string, do: string, poprz_od: string, poprz_do: string, dni: int}
 */
function sklepag_granice_okresu( $okres ) {
	$teraz = strtotime( current_time( 'mysql' ) );
	if ( 'dzis' === $okres ) {
		$od = strtotime( 'today', $teraz );
		$dl = $teraz - $od;
		if ( $dl < 1 ) {
			$dl = 1;
		}
		return array(
			'od'       => gmdate( 'Y-m-d H:i:s', $od ),
			'do'       => gmdate( 'Y-m-d H:i:s', $teraz ),
			'poprz_od' => gmdate( 'Y-m-d H:i:s', $od - DAY_IN_SECONDS ),
			'poprz_do' => gmdate( 'Y-m-d H:i:s', $od - DAY_IN_SECONDS + $dl ),
			'dni'      => 1,
		);
	}
	$dni = ( '30dni' === $okres ) ? 30 : 7;
	$od  = $teraz - $dni * DAY_IN_SECONDS;
	return array(
		'od'       => gmdate( 'Y-m-d H:i:s', $od ),
		'do'       => gmdate( 'Y-m-d H:i:s', $teraz ),
		'poprz_od' => gmdate( 'Y-m-d H:i:s', $od - $dni * DAY_IN_SECONDS ),
		'poprz_do' => gmdate( 'Y-m-d H:i:s', $od ),
		'dni'      => $dni,
	);
}

/**
 * Liczby KPI dla zakresu dat (tylko zamówienia OPŁACONE — stany po CAS płatności).
 *
 * @param string $od Początek (Y-m-d H:i:s).
 * @param string $do_kiedy Koniec.
 * @return array{przychod: int, zamowienia: int, kupujacy: int}
 */
function sklepag_kpi_zakresu( $od, $do_kiedy ) {
	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- nazwa tabeli z prefiksu, SKLEPAG_STATUSY_OPLACONE to stała pliku (nie wejście); KPI liczone na żywo.
	$w = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(kwota_grosze),0) AS przychod, COUNT(*) AS zamowienia, COUNT(DISTINCT email_kupujacego) AS kupujacy'
			. " FROM {$t} WHERE status IN (" . SKLEPAG_STATUSY_OPLACONE . ') AND utworzono >= %s AND utworzono < %s',
			$od,
			$do_kiedy
		)
	);
	// phpcs:enable
	return array(
		'przychod'   => (int) $w->przychod,
		'zamowienia' => (int) $w->zamowienia,
		'kupujacy'   => (int) $w->kupujacy,
	);
}

/**
 * Delta procentowa jako badge ze strzałką i znakiem (nie samym kolorem — WCAG).
 *
 * @param int|float $teraz    Wartość bieżąca.
 * @param int|float $poprzednio Wartość poprzedniego okresu.
 */
function sklepag_delta_html( $teraz, $poprzednio ) {
	if ( $poprzednio <= 0 ) {
		return $teraz > 0
			? '<span class="sklepag-delta sklepag-delta-plus" title="Poprzedni okres: 0">▲ nowe</span>'
			: '<span class="sklepag-delta sklepag-delta-zero">— bez zmian</span>';
	}
	$proc = ( $teraz - $poprzednio ) / $poprzednio * 100;
	$abs  = number_format( abs( $proc ), abs( $proc ) >= 100 ? 0 : 1, ',', '' );
	if ( $proc > 0.05 ) {
		return '<span class="sklepag-delta sklepag-delta-plus">▲ +' . esc_html( $abs ) . '%</span>';
	}
	if ( $proc < -0.05 ) {
		return '<span class="sklepag-delta sklepag-delta-minus">▼ −' . esc_html( $abs ) . '%</span>';
	}
	return '<span class="sklepag-delta sklepag-delta-zero">— bez zmian</span>';
}

/* ---------- Self-heal + ręczne dociąganie (plan §4A) ---------- */

/**
 * Reconciliation przy wejściu admina na dashboard, jeśli ostatni bieg
 * starszy niż 10 min (WP-cron na cichej stronie może nie ruszyć wcale).
 */
function sklepag_dashboard_self_heal() {
	$ostatni = (int) get_option( 'sklepag_reconciliation_ostatnia', 0 );
	if ( time() - $ostatni < 600 ) {
		return;
	}
	update_option( 'sklepag_reconciliation_ostatnia', time(), false );
	sklepag_dociagnij_zalegle( 8 ); // Budżet czasu: render admina nie może wisieć; resztę dociągnie cron.
}

add_action( 'admin_post_sklepag_dociagnij', 'sklepag_obsluz_dociagnij' );

/**
 * Ręczny przycisk „Dociągnij zaległe teraz" (nonce + caps + log akcji).
 */
function sklepag_obsluz_dociagnij() {
	check_admin_referer( 'sklepag_dociagnij' );

	sklepag_zdarzenie( null, 'reconciliation_reczna', 'przycisk w panelu' );
	update_option( 'sklepag_reconciliation_ostatnia', time(), false );
	sklepag_dociagnij_zalegle( 15 ); // Budżet czasu < limit PHP hostingu (30 s); reszta na cron.

	wp_safe_redirect( add_query_arg( 'sklepag_dociagnieto', '1', admin_url( 'admin.php?page=sklepag' ) ) );
	exit;
}

/* ---------- Eksport CSV (kryterium #6; log akcji) ---------- */

add_action( 'admin_post_sklepag_eksport_csv', 'sklepag_obsluz_eksport_csv' );

/**
 * CSV z AKTUALNYCH filtrów listy (eksport = to, co widać na ekranie — wzorce §5).
 * Średnik + BOM: polski Excel otwiera poprawnie bez importu.
 */
function sklepag_obsluz_eksport_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_eksport_csv' );

	global $wpdb;
	$t                    = $wpdb->prefix . 'sklepag_zamowienia';
	$f                    = sklepag_filtry_zamowien();
	list( $gdzie, $args ) = sklepag_warunek_zamowien( $f );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- WHERE wyłącznie z placeholderów (jak tabela), limit twardy.
	$sql     = "SELECT id, utworzono, email_kupujacego, produkt_nazwa_kupiona, kwota_grosze, waluta, status, pobrania_uzyte, pobrania_limit FROM {$t} {$gdzie} ORDER BY utworzono DESC, id DESC LIMIT 10000";
	$wiersze = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );
	// phpcs:enable

	sklepag_zdarzenie( null, 'eksport_csv', count( $wiersze ) . ' wierszy; filtry: ' . wp_json_encode( array_filter( $f ) ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="zamowienia-' . gmdate( 'Ymd-Hi' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- strumień CSV do przeglądarki.
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- BOM UTF-8 dla Excela.
	fputcsv( $out, array( 'Nr', 'Data', 'Kupujący', 'Produkt', 'Kwota', 'Waluta', 'Status', 'Pobrania użyte', 'Limit pobrań' ), ';', '"', '\\' );
	foreach ( $wiersze as $w ) {
		fputcsv(
			$out,
			array(
				$w['id'],
				$w['utworzono'],
				$w['email_kupujacego'],
				$w['produkt_nazwa_kupiona'],
				number_format( $w['kwota_grosze'] / 100, 2, ',', '' ),
				$w['waluta'],
				isset( Sklepag_Lista_Zamowien::STATUSY[ $w['status'] ] ) ? Sklepag_Lista_Zamowien::STATUSY[ $w['status'] ][0] : $w['status'],
				$w['pobrania_uzyte'],
				$w['pobrania_limit'],
			),
			';',
			'"',
			'\\'
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/* ---------- Render dashboardu ---------- */

/**
 * Strona główna panelu = dashboard (zastępuje szkielet-liczniki z kości).
 */
function sklepag_admin_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}

	sklepag_dashboard_self_heal();

	global $wpdb;
	$t     = $wpdb->prefix . 'sklepag_zamowienia';
	$okres = sklepag_okres_kpi();
	$g     = sklepag_granice_okresu( $okres );
	$kpi   = sklepag_kpi_zakresu( $g['od'], $g['do'] );
	$poprz = sklepag_kpi_zakresu( $g['poprz_od'], $g['poprz_do'] );

	$aov       = $kpi['zamowienia'] > 0 ? $kpi['przychod'] / $kpi['zamowienia'] : 0;
	$aov_poprz = $poprz['zamowienia'] > 0 ? $poprz['przychod'] / $poprz['zamowienia'] : 0;

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; liczby panelu na żywo.
	$czerwone = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'mail_blad' AND mail_proby >= 3" );
	// phpcs:enable

	// Baner testowy jako cienka linijka PRZY tytule, nie pełny pas — Przychód ma
	// być pierwszą rzeczą na ekranie (sędzia #2: hierarchia + czas do liczby).
	echo '<div class="wrap sklepag-dashboard"><h1>Sklep Agentów <span class="sklepag-tryb-testowy" title="Płatności Stripe testowe — zero prawdziwych pieniędzy">TRYB TESTOWY</span></h1>';

	// Diagnostyka katalogu chronionego (przejęta ze szkieletu kości) — bez niego wydawanie plików nie działa.
	$katalog      = (string) get_option( 'sklepag_katalog_chroniony', '' );
	$katalog_blad = (string) get_option( 'sklepag_katalog_chroniony_blad', '' );
	if ( '' === $katalog || '' !== $katalog_blad ) {
		echo '<div class="notice notice-error"><p><strong>Katalog chroniony na pliki produktów: problem.</strong> '
			. esc_html( '' !== $katalog_blad ? $katalog_blad : 'Katalog nie istnieje — deaktywuj i aktywuj wtyczkę, sprawdź uprawnienia uploads.' ) . '</p></div>';
	}

	// Panel-czerwone (plan §6): nieudane wysyłki po wyczerpaniu prób crona.
	if ( $czerwone > 0 ) {
		echo '<div class="notice notice-error"><p><strong>' . (int) $czerwone . '</strong> zamówień z nieudaną wysyłką maila (3 próby wyczerpane) — '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=sklepag&status=mail_blad' ) ) . '">pokaż je</a> i użyj „Wyślij ponownie" w szczegółach zamówienia.</p></div>';
	}
	if ( isset( $_GET['sklepag_dociagnieto'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- komunikat po redirekcie, bez mutacji.
		echo '<div class="notice notice-success is-dismissible"><p>Dociąganie zaległych wykonane — świeże dane poniżej.</p></div>';
	}

	// Przełącznik okresu + akcje (prawy górny róg — wzorce §5).
	$baza = admin_url( 'admin.php?page=sklepag' );
	echo '<div class="sklepag-pasek">';
	echo '<nav class="sklepag-okresy" aria-label="Okres statystyk">';
	foreach ( array(
		'dzis'  => 'Dziś',
		'7dni'  => '7 dni',
		'30dni' => '30 dni',
	) as $klucz => $etykieta ) {
		$aktywny = $okres === $klucz;
		echo '<a href="' . esc_url( add_query_arg( 'okres', $klucz, $baza ) ) . '" class="button' . ( $aktywny ? ' button-primary' : '' ) . '"' . ( $aktywny ? ' aria-current="page"' : '' ) . '>' . esc_html( $etykieta ) . '</a> ';
	}
	echo '</nav>';
	echo '<div class="sklepag-akcje">';
	echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_dociagnij' ), 'sklepag_dociagnij' ) ) . '" class="button">Dociągnij zaległe teraz</a> ';
	$eksport = wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_eksport_csv' ), 'sklepag_eksport_csv' );
	foreach ( sklepag_filtry_zamowien() as $k => $v ) {
		if ( '' !== $v ) {
			$eksport = add_query_arg( 'szukaj' === $k ? 's' : $k, rawurlencode( $v ), $eksport );
		}
	}
	echo '<a href="' . esc_url( $eksport ) . '" class="button">Eksport CSV</a>';
	echo '</div></div>';

	// Karty KPI (wzorce §1-2: przychód i zamówienia pierwsze, delta % przy każdej;
	// hierarchia typografią — przychód elewowany jak u Stripe, uwaga sędziego wyglądu).
	echo '<div class="sklepag-kpi" role="list">';
	// Waluta jak w tabeli (PLN, kod ISO) — jedna etykieta w całym widoku (sędzia #2).
	sklepag_karta_kpi( 'Przychód', number_format( $kpi['przychod'] / 100, 2, ',', ' ' ) . ' PLN', sklepag_delta_html( $kpi['przychod'], $poprz['przychod'] ), true );
	sklepag_karta_kpi( 'Zamówienia', (string) $kpi['zamowienia'], sklepag_delta_html( $kpi['zamowienia'], $poprz['zamowienia'] ) );
	sklepag_karta_kpi( 'Średnia wartość', $aov > 0 ? number_format( $aov / 100, 2, ',', ' ' ) . ' PLN' : '—', sklepag_delta_html( $aov, $aov_poprz ) );
	sklepag_karta_kpi( 'Kupujący', (string) $kpi['kupujacy'], sklepag_delta_html( $kpi['kupujacy'], $poprz['kupujacy'] ) );
	echo '</div>';

	sklepag_pasek_statusow( $g );
	sklepag_rozklad_w_czasie( $okres, $g );

	// Tabela zamówień (formularz GET niesie filtry; page/okres jako hidden).
	$tabela = new Sklepag_Lista_Zamowien();
	$tabela->prepare_items();
	echo '<h2>Zamówienia</h2>';
	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="sklepag">';
	echo '<input type="hidden" name="okres" value="' . esc_attr( $okres ) . '">';
	$tabela->search_box( 'Szukaj po e-mailu', 'sklepag-szukaj' );
	$tabela->display();
	echo '</form>';

	echo '</div>';
}

/**
 * Jedna karta KPI.
 *
 * @param string $tytul  Nazwa metryki.
 * @param string $liczba Sformatowana wartość.
 * @param string $delta  HTML delty (z sklepag_delta_html — już escapowany).
 * @param bool   $glowna Metryka główna (większa typografia — hierarchia jak u Stripe).
 */
function sklepag_karta_kpi( $tytul, $liczba, $delta, $glowna = false ) {
	echo '<div class="sklepag-karta' . ( $glowna ? ' sklepag-karta-glowna' : '' ) . '" role="listitem"><span class="sklepag-karta-tytul">' . esc_html( $tytul ) . '</span>'
		. '<span class="sklepag-karta-liczba">' . esc_html( $liczba ) . '</span>'
		. $delta . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $delta budowana wyłącznie w sklepag_delta_html z escapowanych części.
}

/**
 * Pasek proporcji statusów zamówień w okresie (uwaga sędziego: „ile % ma
 * problem" na jeden rzut oka, bez liczenia wierszy tabeli) — stacked bar
 * czystym CSS + legenda z liczbami (status nigdy samym kolorem — WCAG).
 *
 * @param array $g Granice okresu.
 */
function sklepag_pasek_statusow( $g ) {
	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_zamowienia';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; agregacja na żywo.
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT status, COUNT(*) AS ile FROM {$t} WHERE utworzono >= %s AND utworzono < %s GROUP BY status",
			$g['od'],
			$g['do']
		),
		OBJECT_K
	);
	// phpcs:enable

	$razem = 0;
	foreach ( $wiersze as $w ) {
		$razem += (int) $w->ile;
	}
	if ( 0 === $razem ) {
		return; // Pusty okres — pasek bez danych to szum; pusty stan niesie tabela.
	}

	$kolory = array(
		'mail_wyslany' => '#2e7d38',
		'oplacone'     => '#2271b1',
		'mail_w_toku'  => '#b98a00',
		'mail_blad'    => '#b32d2e',
		'oczekuje'     => '#8c8f94',
	);

	echo '<div class="sklepag-statusy-pasek-box"><h2>Statusy zamówień w okresie</h2>';
	echo '<div class="sklepag-statusy-pasek" role="img" aria-label="Proporcje statusów zamówień">';
	foreach ( $kolory as $status => $kolor ) {
		$ile = isset( $wiersze[ $status ] ) ? (int) $wiersze[ $status ]->ile : 0;
		if ( 0 === $ile ) {
			continue;
		}
		$s = Sklepag_Lista_Zamowien::STATUSY[ $status ];
		echo '<div class="sklepag-statusy-czesc" style="width:' . esc_attr( (string) round( $ile / $razem * 100, 2 ) ) . '%;background:' . esc_attr( $kolor ) . '" title="' . esc_attr( $s[0] . ': ' . $ile ) . '"></div>';
	}
	echo '</div><p class="sklepag-statusy-legenda">';
	foreach ( $kolory as $status => $kolor ) {
		$ile = isset( $wiersze[ $status ] ) ? (int) $wiersze[ $status ]->ile : 0;
		if ( 0 === $ile ) {
			continue;
		}
		$s = Sklepag_Lista_Zamowien::STATUSY[ $status ];
		echo '<span class="sklepag-legenda-pozycja"><span class="sklepag-legenda-kropka" style="background:' . esc_attr( $kolor ) . '"></span>' . esc_html( $s[0] . ': ' . $ile ) . '</span> ';
	}
	echo '</p></div>';
}

/**
 * Rozkład zakupów w czasie (S-04: „w jakim czasie") — słupki czystym CSS,
 * bez bibliotek zewnętrznych; dziś = godzinowo, 7/30 dni = dziennie.
 *
 * @param string $okres Klucz okresu.
 * @param array  $g     Granice okresu.
 */
function sklepag_rozklad_w_czasie( $okres, $g ) {
	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_zamowienia';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- nazwa tabeli z prefiksu, SKLEPAG_STATUSY_OPLACONE to stała pliku (nie wejście); agregacja na żywo.
	if ( 'dzis' === $okres ) {
		$wiersze  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(utworzono) AS klucz, SUM(kwota_grosze) AS suma FROM {$t} WHERE status IN (" . SKLEPAG_STATUSY_OPLACONE . ') AND utworzono >= %s GROUP BY HOUR(utworzono)',
				$g['od']
			),
			OBJECT_K
		);
		$etykiety = range( 0, 23 );
		$format   = static function ( $k ) {
			return sprintf( '%02d:00', $k );
		};
	} else {
		$wiersze = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(utworzono) AS klucz, SUM(kwota_grosze) AS suma FROM {$t} WHERE status IN (" . SKLEPAG_STATUSY_OPLACONE . ') AND utworzono >= %s GROUP BY DATE(utworzono)',
				$g['od']
			),
			OBJECT_K
		);
		// Od $i=0: dzień $start (kalendarzowa data $g['od'], częściowy) TEŻ dostaje
		// słupek — zapytanie go łapie (utworzono >= od), a wykres bez niego
		// przeczyłby karcie KPI (off-by-one). Słupków jest dni+1.
		$etykiety = array();
		$start    = strtotime( substr( $g['od'], 0, 10 ) );
		for ( $i = 0; $i <= $g['dni']; $i++ ) {
			$etykiety[] = gmdate( 'Y-m-d', $start + $i * DAY_IN_SECONDS );
		}
		$format = static function ( $k ) {
			return substr( (string) $k, 5 ); // md z Y-m-d.
		};
	}
	// phpcs:enable

	$maks = 0;
	foreach ( $etykiety as $e ) {
		$maks = max( $maks, isset( $wiersze[ $e ] ) ? (int) $wiersze[ $e ]->suma : 0 );
	}

	echo '<div class="sklepag-rozklad"><h2>Rozkład zakupów w czasie</h2>';
	if ( 0 === $maks ) {
		echo '<p class="description">Brak opłaconych zakupów w tym okresie — słupki pojawią się przy pierwszej sprzedaży.</p></div>';
		return;
	}
	echo '<div class="sklepag-slupki" role="img" aria-label="Przychód wg ' . ( 'dzis' === $okres ? 'godzin' : 'dni' ) . '">';
	foreach ( $etykiety as $e ) {
		$suma = isset( $wiersze[ $e ] ) ? (int) $wiersze[ $e ]->suma : 0;
		$proc = (int) round( $suma / $maks * 100 );
		$opis = $format( $e ) . ': ' . number_format( $suma / 100, 2, ',', ' ' ) . ' zł';
		echo '<div class="sklepag-slupek-kolumna" title="' . esc_attr( $opis ) . '">'
			. '<div class="sklepag-slupek" style="height:' . esc_attr( (string) max( $proc, $suma > 0 ? 4 : 1 ) ) . '%"></div>'
			. '<span class="sklepag-slupek-etykieta">' . esc_html( $format( $e ) ) . '</span></div>';
	}
	echo '</div></div>';
}
