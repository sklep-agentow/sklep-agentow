<?php
/**
 * STRAŻNIK REGRESJI — panel „Szczegóły zamówienia" pokazuje termin ważności linku
 * w CZASIE WITRYNY, tak samo jak sąsiedni wiersz „Zgoda", nie surowe UTC bez etykiety.
 *
 * WADA (A7b-F2, `audyt/plamy/A7b-RAPORT.md`): kolumna `link_wygasa` jest jedyną kolumną
 * zamówień zapisywaną w UTC (`gmdate()`), a panel drukował ją SUROWO, bez konwersji
 * i bez słowa, że to UTC — dwie linie NIŻEJ ten sam plik konwertuje `zgoda_czas` poprawnie.
 * Skutek: właściciel w strefie z dodatnim przesunięciem (np. Europe/Warsaw) widział termin
 * o 1–2 h WCZEŚNIEJSZY niż prawdziwy. Zachowanie (odmowa pobrania) było poprawne — to była
 * wyłącznie wada WYŚWIETLANIA.
 *
 * ⛔ MIERZY PRAWDZIWĄ FUNKCJĘ RENDERUJĄCĄ (`sklepag_admin_zamowienie()`), nie kopię logiki —
 * ta sama zasada co w `csv-bez-formuly-test.php`.
 *
 * Uruchomienie (na STOSIE PRÓBNYM, nigdy na wspólnym):
 *   bash narzedzia-qa/panel-link-wygasa-czas-lokalny-proba-kontrolna.sh
 *
 * EXIT 0 = wiersz „Pobrania" niesie czas w strefie witryny + etykietę „(czas lokalny)",
 *          zgodny co do minuty z konwersją `get_date_from_gmt()` · 1 = REGRESJA · 2 = środowisko
 */

$GLOBALS['sklepag_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_test_bledy'];
	}
}

// `admin-zamowienie.php` jest dołączany przez wtyczkę TYLKO za `is_admin()` — pod `wp eval-file`
// (kontekst WP-CLI) ten warunek jest fałszywy, więc funkcja nie istnieje, choć wtyczka jest aktywna.
// Ten sam chwyt co w `audyt/czesciowo/E7-sonda-panel-czas.php`: dociągamy plik wprost.
if ( ! function_exists( 'sklepag_admin_zamowienie' ) && defined( 'WP_PLUGIN_DIR' ) ) {
	// `sklepag_badge_statusu()` żyje w admin-dashboard.php — admin-zamowienie.php jej używa,
	// więc bez tego drugiego pliku fatal na wywołaniu, nie na braku funkcji renderującej.
	foreach ( array( 'admin-dashboard.php', 'admin-zamowienie.php' ) as $sklepag_plik_nazwa ) {
		$sklepag_plik = WP_PLUGIN_DIR . '/sklep-agentow/includes/' . $sklepag_plik_nazwa;
		if ( file_exists( $sklepag_plik ) ) {
			require_once $sklepag_plik;
		}
	}
}
if ( ! function_exists( 'sklepag_admin_zamowienie' ) ) {
	echo "  BLAD funkcja sklepag_admin_zamowienie niedostepna (wtyczka sklep-agentow nieaktywna?)\n";
	exit( 1 );
}

// Wymuszona strefa z NIEZEROWYM przesunięciem — na UTC test nie odróżniłby dobrego
// zachowania od złego (obie wersje wyglądałyby tak samo). Odczyt UTC/lokalny zależy
// od tej opcji poprzez `get_date_from_gmt()` i `wp_timezone_string()`.
update_option( 'timezone_string', 'Europe/Warsaw' );
update_option( 'gmt_offset', '' ); // WP: albo timezone_string, albo gmt_offset — nie oba naraz.

global $wpdb;
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $adm ) ) {
	echo "  BLAD brak administratora do wp_set_current_user — sonda nie ma na kim mierzyc\n";
	exit( 2 );
}
wp_set_current_user( $adm[0]->ID );

$pz = $wpdb->prefix . 'sklepag_zamowienia';

// Chwila w LECIE (Europe/Warsaw = UTC+2), żeby przesunięcie było jednoznaczne i stałe w teście.
$link_wygasa_utc = '2026-06-01 10:00:00';
$teraz           = current_time( 'mysql' );
$sesja           = 'sklepag_test_a7bf2_' . wp_generate_password( 12, false );

$wstawiono = $wpdb->insert(
	$pz,
	array(
		'stripe_session_id'     => $sesja,
		'produkt_id'            => 0,
		'produkt_nazwa_kupiona' => 'Produkt testowy A7b-F2',
		'kwota_grosze'          => 10000,
		'waluta'                => 'PLN',
		'status'                => 'mail_wyslany',
		'token_pobrania'        => wp_generate_password( 32, false ),
		'link_wygasa'           => $link_wygasa_utc,
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
if ( ! $wstawiono ) {
	echo '  BLAD nie udalo sie wstawic testowego zamowienia: ' . esc_html( $wpdb->last_error ) . "\n";
	exit( 2 );
}
$id = (int) $wpdb->insert_id;

$_GET['zamowienie']     = $id;
$_REQUEST['zamowienie'] = $id;
ob_start();
sklepag_admin_zamowienie();
$html = ob_get_clean();

// Sprzątanie od razu, żeby awaria asercji niżej nie zostawiła śladu na stosie.
$wpdb->delete( $pz, array( 'id' => $id ) );

if ( ! preg_match( '#<tr><td>Pobrania</td><td>(.*?)</td></tr>#s', $html, $m ) ) {
	echo "  BLAD sonda nie znalazla wiersza \"Pobrania\" — pomiar nieodbyty\n";
	exit( 2 );
}
$wiersz = trim( wp_strip_all_tags( $m[1] ) );
echo '  wiersz z ekranu panelu: ' . $wiersz . "\n";

$oczekiwany_lokalny = get_date_from_gmt( $link_wygasa_utc, 'd.m.Y H:i' );
printf( "  surowa kolumna link_wygasa (baza, UTC)     : %s\n", $link_wygasa_utc );
printf( "  ta sama chwila w czasie WITRYNY (oczekiwana): %s (strefa %s)\n", $oczekiwany_lokalny, wp_timezone_string() );

sprawdz(
	'wiersz niesie czas w strefie witryny, nie surowe UTC',
	false !== strpos( $wiersz, $oczekiwany_lokalny )
);
sprawdz(
	'wiersz ma etykietę strefy „(czas lokalny)", tak jak sąsiedni wiersz „Zgoda"',
	false !== strpos( $wiersz, '(czas lokalny)' )
);
sprawdz(
	'wiersz NIE zawiera surowego UTC bez etykiety (dowód, że to nie przypadkowa zgodność)',
	$oczekiwany_lokalny === $link_wygasa_utc || false === strpos( $wiersz, $link_wygasa_utc )
);

// KONTROLA DODATNIA: sąsiedni wiersz „Zgoda" (nietknięty tym zadaniem) ma już dziś ten sam
// wzorzec — jeśli test go NIE widzi, to test jest ślepy, nie że produkt jest zdrowy.
sprawdz(
	'kontrola dodatnia: przyrząd w ogóle widzi etykietę „(czas lokalny)" w tym HTML-u',
	false !== strpos( $html, '(czas lokalny)' )
);

echo "\n";
if ( $GLOBALS['sklepag_test_bledy'] > 0 ) {
	echo 'WYNIK: BLEDOW ' . $GLOBALS['sklepag_test_bledy'] . "\n";
	exit( 1 );
}
echo "WYNIK: OK\n";
exit( 0 );
