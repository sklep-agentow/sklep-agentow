<?php
/**
 * Dashboard „jak dobre sklepy" — wzorowany na sklepach, które to robią dobrze:
 * karty KPI z porównaniem % do poprzedniego okresu → rozkład zakupów
 * w czasie → tabela zamówień → eksport CSV z aktualnych filtrów.
 * Do tego: notice o zamówieniach z nieudaną wysyłką (panel-czerwone),
 * self-heal reconciliation przy wejściu admina + ręczny przycisk.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SKLEPAG_KATALOG . 'includes/class-sklepag-lista-zamowien.php';

// ⚠️ Odejmowanie kwot ZAWSZE przez CAST na SIGNED: obie kolumny są `int unsigned`, a
// MariaDB przy różnicy ujemnej przerywa zapytanie błędem „BIGINT UNSIGNED value is out of
// range" (zmierzone) — samo GREATEST nie chroni, bo liczy się już po odejmowaniu. Bez tego
// jeden zwrot większy od kwoty zamówienia wywracałby cały Pulpit, nie tylko jedną liczbę.
// Statusy, które LICZĄ SIĘ DO PRZYCHODU. Nie ma tu 'zwrocone' ani 'sporne': pieniądze
// za zwrócony zakup i za otwarte obciążenie zwrotne zeszły z konta właściciela, więc panel
// nie może ich pokazywać jako utargu. Zwrot CZĘŚCIOWY zostawia zamówienie na liście, a jego
// kwota schodzi z sum przez odjęcie `zwrocono_grosze`.
const SKLEPAG_STATUSY_OPLACONE = "'oplacone','mail_w_toku','mail_wyslany','mail_blad'";
/**
 * Badge statusu zamówienia: słowo + kolor + tooltip (nigdy goły tekst).
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
 * @return array{przychod: int|null, zamowienia: int|null, kupujacy: int|null, blad?: string}
 *         Klucz `blad` pojawia się TYLKO wtedy, gdy zapytanie padło — wtedy liczby są `null`,
 *         żeby awaria nie udawała zerowej sprzedaży.
 */
function sklepag_kpi_zakresu( $od, $do_kiedy ) {
	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- nazwa tabeli z prefiksu, SKLEPAG_STATUSY_OPLACONE to stała pliku (nie wejście); KPI liczone na żywo.
	$w = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(GREATEST(CAST(kwota_grosze AS SIGNED) - CAST(zwrocono_grosze AS SIGNED), 0)),0) AS przychod, COUNT(*) AS zamowienia, COUNT(DISTINCT email_kupujacego) AS kupujacy'
			. " FROM {$t} WHERE status IN (" . SKLEPAG_STATUSY_OPLACONE . ') AND utworzono >= %s AND utworzono < %s',
			$od,
			$do_kiedy
		)
	);
	// phpcs:enable

	// Puste $w znaczy, że zapytanie PADŁO (np. migracja schematu nie dołożyła kolumn zwrotu,
	// bo użytkownik bazy nie ma prawa ALTER). Bez tej osłony Pulpit pokazywałby sklepowi,
	// który sprzedaje, „Przychód 0,00 zł" i błąd PHP w logu — czyli cichą nieprawdę zamiast
	// widocznej awarii. Zero jest tu POMIAREM tylko wtedy, gdy zapytanie się udało.
	if ( ! $w ) {
		return array(
			'przychod'   => null,
			'zamowienia' => null,
			'kupujacy'   => null,
			'blad'       => 'Nie udało się policzyć wskaźników — sprawdź, czy aktualizacja bazy przeszła (Dziennik → zdarzenia).',
		);
	}

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

/* ---------- Self-heal + ręczne dociąganie ---------- */

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
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_dociagnij' );

	sklepag_zdarzenie( null, 'reconciliation_reczna', 'przycisk w panelu' );
	update_option( 'sklepag_reconciliation_ostatnia', time(), false );
	$w = sklepag_dociagnij_zalegle( 15, true ); // Budżet czasu < limit PHP hostingu (30 s); ręczny klik pyta o wszystko, bez przerw.
	$w = is_array( $w ) ? $w : array();
	sklepag_zdarzenie( null, 'reconciliation_wynik', sprintf( 'domknięte %d · młodsze niż 2 min (pominięte) %d · sprawdzone teraz, nieopłacone %d · sprawdzone chwilę temu (z przerwą) %d · błędy %d', (int) ( $w['domkniete'] ?? 0 ), (int) ( $w['pominiete_mlode'] ?? 0 ), (int) ( $w['sprawdzone_otwarte'] ?? 0 ), (int) ( $w['z_przerwa'] ?? 0 ), (int) ( $w['bledy'] ?? 0 ) ) );

	// Liczby jadą w adresie, żeby Pulpit powiedział właścicielowi, CO się stało — „pominięto młodsze”
	// to jedyna wskazówka, że trzeba kliknąć jeszcze raz za chwilę — bez niej cisza po kliknięciu
	// potrafi trwać nawet 20 minut, zanim WP-Cron sam dociągnie zaległe.
	wp_safe_redirect(
		add_query_arg(
			array(
				'sklepag_dociagnieto' => '1',
				'd'                   => (int) ( $w['domkniete'] ?? 0 ),
				'm'                   => (int) ( $w['pominiete_mlode'] ?? 0 ),
				'o'                   => (int) ( $w['sprawdzone_otwarte'] ?? 0 ),
				'p'                   => (int) ( $w['z_przerwa'] ?? 0 ),
				'b'                   => (int) ( $w['bledy'] ?? 0 ),
			),
			admin_url( 'admin.php?page=sklepag' )
		)
	);
	exit;
}

/* ---------- Eksport CSV (z wpisem w dzienniku akcji) ---------- */

/**
 * Znaki, od których arkusz kalkulacyjny zaczyna czytać KOMÓRKĘ JAKO FORMUŁĘ.
 * Excel i LibreOffice: `=` i `@` wprost, `+` i `-` jako początek wyrażenia;
 * tabulator i powrót karetki dlatego, że arkusz je zjada i odsłania znak za nimi.
 * Apostrof NIE jest znakiem formuły — jest na liście po to, żeby neutralizacja była
 * odwracalna dla KAŻDEJ wartości: `'Bot roku` bez tego wyszedłby do pliku nietknięty
 * i nie dałoby się go odróżnić od zneutralizowanego `=…`; z drugim apostrofem zdjęcie
 * jednego zawsze oddaje oryginał.
 */
const SKLEPAG_CSV_ZNAKI_FORMULY = array( '=', '+', '-', '@', "\t", "\r", "'" );

/**
 * Neutralizacja wstrzyknięcia formuły do arkusza (CSV injection / formula injection).
 *
 * ⛔ CZEMU TO W OGÓLE JEST POTRZEBNE. Pola tekstowe zamówienia trafiają do CSV tak, jak
 * leżą w bazie. Kupujący, który poda adres `=1+1@example.com`, i właściciel, który nazwie
 * produkt `=HYPERLINK(...)`, wpisują treść, którą Excel wykona NA KOMPUTERZE WŁAŚCICIELA
 * przy otwarciu pliku. `sanitize_email()` i `is_email()` takiego adresu NIE odrzucają.
 *
 * ⛔ OBRONA WARSTWOWA, NIE ZAKŁAD O CUDZE ZACHOWANIE. Neutralizujemy KAŻDE pole tekstowe
 * niezależnie od źródła — także te ustawiane przez właściciela. Oparcie naprawy na tym, że
 * Stripe odrzuci dziwny adres, byłoby zakładem o zachowanie cudzego systemu, który może się
 * zmienić bez uprzedzenia i bez powiadomienia nas; a nazwy produktu Stripe nie widzi wcale.
 *
 * FORMA: apostrof z przodu (zalecenie OWASP dla tej klasy). Wybrany, bo jako JEDYNY
 * z rozważanych jest ODWRACALNY i nie zmienia samej wartości: odczyt maszynowy odzyskuje
 * oryginał zdjęciem dokładnie jednego wiodącego apostrofu — także wtedy, gdy wartość SAMA
 * zaczyna się od apostrofu (dostaje drugi; patrz komentarz przy stałej). Odrzucone: spacja z przodu
 * (zmienia wartość, psuje dopasowanie adresu), `="…"` (samo jest formułą), tabulator
 * z przodu (niewidoczny, zanieczyszcza wartość przy ponownym imporcie).
 *
 * NAZWA wg wzorca bliźniaczek w pozostałych wtyczkach (`chatl_csv_komorka()`,
 * `segm_csv_komorka()`) — trzy kopie są uzasadnione (wtyczki instalują się osobno), trzecia
 * nazwa nie: jeden ciąg `_csv_komorka` wystarcza, żeby wygrepować całą klasę przy audycie.
 *
 * RÓŻNICE wobec bliźniaczek — dwie, obie świadome: (1) apostrof na liście (odwracalność,
 * wyżej), (2) wyjątek na literały liczbowe, poniżej. Bliźniaczki nie mają żadnej z nich.
 *
 * ⛔ WYJĄTEK NA LICZBY. Czysty literał liczbowy (`149,00`, także `-10,00`) NIE jest formułą — arkusz
 * czyta go jako liczbę, a apostrof z przodu zamieniłby go w tekst i zepsuł sumowanie kolumny
 * „Kwota". ⚠️ Prawda o danych: `kwota_grosze` jest `int unsigned` (`aktywacja.php`), zwroty idą przez
 * `zwrocono_grosze`, więc UJEMNA kwota z tego eksportu dziś nie wychodzi; wyjątek broni kolumny
 * dodatniej (której `in_array` i tak by nie tknął) oraz każdej przyszłej kolumny ze znakiem — i jest
 * mierzony przez strażnika na literale `-10,00` podanym jako TEKST (kierunek D).
 * `-1+1@example.com` literałem nie jest, więc neutralizacji podlega.
 *
 * @param mixed $wartosc Komórka do wypisania.
 * @return mixed Wartość bezpieczna dla arkusza (nieteksty i liczby bez zmian).
 */
function sklepag_csv_komorka( $wartosc ) {
	if ( ! is_string( $wartosc ) || '' === $wartosc ) {
		return $wartosc;
	}
	// Literał liczbowy (także ujemny, także z przecinkiem dziesiętnym) zostaje liczbą.
	if ( preg_match( '/^-?\d+(?:[.,]\d+)?$/', $wartosc ) ) {
		return $wartosc;
	}
	if ( ! in_array( $wartosc[0], SKLEPAG_CSV_ZNAKI_FORMULY, true ) ) {
		return $wartosc;
	}
	return "'" . $wartosc;
}

add_action( 'admin_post_sklepag_eksport_csv', 'sklepag_obsluz_eksport_csv' );

/**
 * CSV z AKTUALNYCH filtrów listy (eksport = to, co widać na ekranie).
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
		// ⛔ NEUTRALIZACJA CAŁEGO WIERSZA, NIE WYBRANYCH KOLUMN. Wskazanie „tych dwóch pól,
		// o których wiemy" jest naprawą EGZEMPLARZA: pierwsza nowa kolumna z tekstem wróciłaby
		// z tą samą wadą, a nikt by tego nie zauważył. `sklepag_csv_komorka()` sam zostawia
		// liczby i nieteksty w spokoju, więc objęcie nim wszystkiego niczego nie psuje.
		fputcsv(
			$out,
			array_map(
				'sklepag_csv_komorka',
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
				)
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
 * Komunikat o NIEKOMPLETNYM schemacie bazy: aktywacja/migracja zmierzyła braki po `dbDelta`,
 * wersja nie została podniesiona, sklep ponowi próbę sam — nie przy każdym żądaniu, tylko
 * najwyżej raz na 5 minut (dławik `sklepag_schemat_ponowienie`, `aktywacja.php:302-308`); aktywacja
 * wtyczki kasuje znacznik, więc „deaktywuj i aktywuj" działa natychmiast. Przyczyna
 * (np. brak prawa ALTER/CREATE) wymaga właściciela. Wypisuje notice albo nic.
 *
 * @return bool Czy komunikat został wypisany.
 */
function sklepag_notice_schematu() {
	$schemat_blad = get_option( 'sklepag_schemat_blad', array() );
	if ( ! is_array( $schemat_blad ) || empty( $schemat_blad['braki'] ) ) {
		return false;
	}
	echo '<div class="notice notice-error"><p><strong>Baza sklepu jest niekompletna — sklep może nie przyjmować zamówień.</strong> Brakuje: '
		. esc_html( implode( ', ', array_map( 'strval', (array) $schemat_blad['braki'] ) ) )
		. ' (wersja schematu ' . (int) get_option( 'sklepag_wersja_schematu', 0 ) . ', oczekiwana ' . (int) ( $schemat_blad['oczekiwana'] ?? SKLEPAG_WERSJA_SCHEMATU ) . ').'
		. ( ! empty( $schemat_blad['blad_bazy'] ) ? ' Ostatni błąd bazy: ' . esc_html( (string) $schemat_blad['blad_bazy'] ) : '' )
		. ' Najczęstsza przyczyna: użytkownik bazy bez prawa ALTER/CREATE. Po naprawie uprawnień deaktywuj i aktywuj wtyczkę (sklep ponawia próbę sam co 5 minut, a aktywacja robi to natychmiast).</p></div>';
	return true;
}

function sklepag_admin_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}

	sklepag_dashboard_self_heal();

	global $wpdb;
	$t     = $wpdb->prefix . 'sklepag_zamowienia';
	$okres = sklepag_okres_kpi();
	$g     = sklepag_granice_okresu( $okres );
	$kpi   = sklepag_kpi_zakresu( $g['od'], $g['do'] );
	$poprz = sklepag_kpi_zakresu( $g['poprz_od'], $g['poprz_do'] );

	// Awaria liczenia wskaźników ma być WIDOCZNA, nie udawać zerowej sprzedaży.
	$kpi_blad = ! empty( $kpi['blad'] ) ? (string) $kpi['blad'] : ( ! empty( $poprz['blad'] ) ? (string) $poprz['blad'] : '' );
	if ( '' !== $kpi_blad ) {
		$puste = array(
			'przychod'   => 0,
			'zamowienia' => 0,
			'kupujacy'   => 0,
		);
		$kpi   = $puste;
		$poprz = $puste;
	}

	$aov       = $kpi['zamowienia'] > 0 ? $kpi['przychod'] / $kpi['zamowienia'] : 0;
	$aov_poprz = $poprz['zamowienia'] > 0 ? $poprz['przychod'] / $poprz['zamowienia'] : 0;

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; liczby panelu na żywo.
	$czerwone = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'mail_blad' AND mail_proby >= 3" );
	// phpcs:enable

	// Baner testowy jako cienka linijka PRZY tytule, nie pełny pas — Przychód ma
	// być pierwszą rzeczą na ekranie (hierarchia widoku: najkrótsza droga do liczby).
	/* ⛔ Także dla WŁAŚCICIELA, nie tylko dla kupującego: znacznik „TRYB TESTOWY" wiszący przy
		tytule na kluczu produkcyjnym mówiłby mu, że zero prawdziwych pieniędzy — przy prawdziwych
		pieniądzach na koncie. To ten sam napis i to samo źródło prawdy. */
	$znacznik_trybu = sklepag_tryb_testowy()
		? ' <span class="sklepag-tryb-testowy" title="Płatności Stripe testowe — zero prawdziwych pieniędzy">TRYB TESTOWY</span>'
		: '';
	echo '<div class="wrap sklepag-dashboard"><h1>Sklep Agentów' . wp_kses_post( $znacznik_trybu ) . '</h1>';

	// Diagnostyka katalogu chronionego (przejęta ze szkieletu kości) — bez niego wydawanie plików nie działa.
	$katalog      = (string) get_option( 'sklepag_katalog_chroniony', '' );
	$katalog_blad = (string) get_option( 'sklepag_katalog_chroniony_blad', '' );
	if ( '' === $katalog || '' !== $katalog_blad ) {
		echo '<div class="notice notice-error"><p><strong>Katalog chroniony na pliki produktów: problem.</strong> '
			. esc_html( '' !== $katalog_blad ? $katalog_blad : 'Katalog nie istnieje — deaktywuj i aktywuj wtyczkę, sprawdź uprawnienia uploads.' ) . '</p></div>';
	}

	sklepag_notice_schematu();

	if ( '' !== $kpi_blad ) {
		echo '<div class="notice notice-error"><p><strong>Wskaźniki poniżej są niewiarygodne.</strong> '
			. esc_html( $kpi_blad ) . '</p></div>';
	}

	// PŁATNOŚCI: bez klucza Stripe „Kup teraz" kończy się błędem, a jedyny ślad ląduje
	// w dzienniku zdarzeń. Pulpit o tym milczał — właściciel widział zero sprzedaży i nie
	// miał jak zgadnąć, że sklep w ogóle nie ma czym przyjąć pieniędzy.
	$klucz_stripe = (string) get_option( 'sklepag_stripe_klucz_sekretny', '' );
	$sekret_hook  = (string) get_option( 'sklepag_stripe_webhook_sekret', '' );
	if ( '' === $klucz_stripe || '' === $sekret_hook ) {
		$czego = ( '' === $klucz_stripe && '' === $sekret_hook )
			? 'Brak klucza Stripe i sekretu webhooka'
			: ( '' === $klucz_stripe ? 'Brak klucza Stripe' : 'Brak sekretu webhooka Stripe' );
		echo '<div class="notice notice-error"><p><strong>Sklep nie przyjmie płatności.</strong> '
			. esc_html( $czego ) . ' — kupujący zobaczy błąd zamiast okna płatności'
			. ( '' === $sekret_hook ? ', a potwierdzenia zakupu nie zostaną przyjęte' : '' ) . '. '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=sklepag-ustawienia' ) ) . '">Uzupełnij w Ustawieniach</a>.</p></div>';
	}

	// DANE SPRZEDAWCY: bez nazwy firmy i adresu stopka maila po zakupie nie spełnia art. 21 ustawy
	// o prawach konsumenta (potwierdzenie na trwałym nośniku ma nieść dane przedsiębiorcy).
	// ⛔ ROZSZERZONE : ten sam warunek teraz decyduje TAKŻE o bloku sprzedawcy na stronie
	// produktu (sklepag_formularz_zakupu() w front-produkt.php) — puste pola nic tam nie rysują
	// kupującemu (zaślepka byłaby gorsza niż brak, ta sama klasa: zaślepka udająca gotowy dokument), więc komunikat musi
	// mówić o OBU miejscach, nie tylko o mailu, inaczej właściciel nie wie, że brakuje mu też
	// obowiązku informacyjnego SPRZED zakupu (art. 12 ust. 1), nie tylko potwierdzenia PO nim.
	if ( function_exists( 'sklepag_sprzedawca' ) && ! sklepag_sprzedawca()['kompletne'] ) {
		echo '<div class="notice notice-warning"><p><strong>Kupujący nie widzi danych sprzedawcy — ani przed zakupem, ani w potwierdzeniu.</strong> '
			. 'Formularz zakupu nie pokazuje bloku sprzedawcy (dopóki pola są puste, nic tam nie rysujemy), a mail po zakupie idzie bez nazwy firmy i adresu do reklamacji — '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=sklepag-ustawienia' ) ) . '">uzupełnij „Dane sprzedawcy” w Ustawieniach</a>.</p></div>';
	}

	// Awarie rozmowy ze Stripe z ostatniej doby: odrzucony klucz, timeout, zły podpis.
	// Pojedynczy błąd bywa przypadkiem sieci, seria znaczy, że sprzedaż stoi.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	// Okno liczone czasem WITRYNY, nie zegarem serwera bazy. Kolumna `czas` jest zapisywana
	// przez current_time('mysql'), a NOW() zwraca czas MySQL (zwykle UTC) — na sklepie
	// w Warszawie „ostatnia doba" robiła się z tego 26 godzin, a przy ujemnym przesunięciu
	// 19, więc świeże awarie wypadały z ostrzeżenia. Reszta filtrów czasu w tej wtyczce
	// robi to tak samo (granica reconciliation, retencja).
	$doba_wstecz = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - DAY_IN_SECONDS );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- dziennik zdarzeń wtyczki, licznik na żywo.
	$awarie_stripe = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zdarzenia
			 WHERE typ IN ('checkout_blad','reconciliation_blad','webhook_zly_podpis')
			 AND czas > %s",
			$doba_wstecz
		)
	);
	if ( $awarie_stripe >= 3 ) {
		echo '<div class="notice notice-warning"><p><strong>' . (int) $awarie_stripe . '</strong> nieudanych rozmów ze Stripe w ostatniej dobie '
			. '(odrzucony klucz, brak odpowiedzi albo zły podpis webhooka) — '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=sklepag-dziennik' ) ) . '">zobacz dziennik zdarzeń</a>. '
			. 'Jeśli to się powtarza, sprawdź klucze w Ustawieniach.</p></div>';
	}

	// Panel-czerwone: nieudane wysyłki po wyczerpaniu prób crona.
	if ( $czerwone > 0 ) {
		echo '<div class="notice notice-error"><p><strong>' . (int) $czerwone . '</strong> zamówień z nieudaną wysyłką maila (3 próby wyczerpane) — '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=sklepag&status=mail_blad' ) ) . '">pokaż je</a> i użyj „Wyślij ponownie" w szczegółach zamówienia.</p></div>';
	}
	if ( isset( $_GET['sklepag_dociagnieto'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- komunikat po redirekcie, bez mutacji.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- liczby z redirectu po własnej akcji, tylko do wyświetlenia.
		$d = isset( $_GET['d'] ) ? absint( $_GET['d'] ) : 0;
		$m = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0;
		$o = isset( $_GET['o'] ) ? absint( $_GET['o'] ) : 0;
		$p = isset( $_GET['p'] ) ? absint( $_GET['p'] ) : 0;
		$b = isset( $_GET['b'] ) ? absint( $_GET['b'] ) : 0;
		// phpcs:enable
		$klasa = $b > 0 ? 'notice-warning' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $klasa ) . ' is-dismissible"><p><strong>Dociąganie zaległych wykonane.</strong> '
			. 'Domknięte zakupy: <strong>' . (int) $d . '</strong> · '
			. 'pominięte jako młodsze niż 2 minuty: <strong>' . (int) $m . '</strong>' . ( $m > 0 ? ' (Stripe bywa jeszcze w trakcie — kliknij ponownie za chwilę)' : '' ) . ' · '
			. 'sprawdzone teraz u Stripe i nieopłacone (kupujący nie zapłacił, same wygasną): ' . (int) $o . ' · '
			. 'sprawdzone przez zadanie w tle chwilę temu i nadal nieopłacone (przycisk pyta Stripe od razu, zadanie w tle — po 9 minutach, jeśli działa): ' . (int) $p
			. ( $b > 0 ? ' · <strong>błędy rozmowy ze Stripe: ' . (int) $b . '</strong> — szczegóły w Dzienniku' : '' ) . '.</p></div>';
	}

	// Przełącznik okresu + akcje (prawy górny róg).
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

	// Karty KPI: przychód i zamówienia pierwsze, delta % przy każdej;
	// hierarchia typografią — przychód elewowany jak u Stripe.
	echo '<div class="sklepag-kpi" role="list">';
	// Waluta jak w tabeli (PLN, kod ISO) — jedna etykieta w całym widoku.
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
 * Pasek proporcji statusów zamówień w okresie („ile % ma problem”
 * na jeden rzut oka, bez liczenia wierszy tabeli) — stacked bar
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
		'zwrocone'     => '#6c7781',
		'sporne'       => '#8a1f1f',
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
 * Rozkład zakupów w czasie („w jakim czasie kupowali") — słupki czystym CSS,
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
				"SELECT HOUR(utworzono) AS klucz, SUM(GREATEST(CAST(kwota_grosze AS SIGNED) - CAST(zwrocono_grosze AS SIGNED), 0)) AS suma FROM {$t} WHERE status IN (" . SKLEPAG_STATUSY_OPLACONE . ') AND utworzono >= %s GROUP BY HOUR(utworzono)',
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
				"SELECT DATE(utworzono) AS klucz, SUM(GREATEST(CAST(kwota_grosze AS SIGNED) - CAST(zwrocono_grosze AS SIGNED), 0)) AS suma FROM {$t} WHERE status IN (" . SKLEPAG_STATUSY_OPLACONE . ') AND utworzono >= %s GROUP BY DATE(utworzono)',
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
