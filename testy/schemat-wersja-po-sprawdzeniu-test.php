<?php
/**
 * STRAŻNIK — A6-F1: wersja schematu rośnie TYLKO po zmierzonym, kompletnym schemacie.
 *
 * Wada (do 0.9.37): aktywacja i migracja podnosiły `sklepag_wersja_schematu` od razu po pętli
 * `dbDelta()`, nie sprawdzając, czy tabele i kolumny naprawdę powstały. Nieudana migracja
 * (brak prawa ALTER/CREATE, cudza tabela) zamykała bramę „wersja == stała” na zawsze —
 * sklep nie przyjmował zamówień i nikt nie wiedział dlaczego. Przyczyna-matka H.
 *
 * Trzy kierunki:
 *   A) PODŁOŻONA AWARIA: tabela `opinie` zrzucona, a filtr `query` zamienia jej CREATE na SELECT 1
 *      (jak brak prawa CREATE) → po migracji wersja MUSI zostać STARA, opcja `sklepag_schemat_blad`
 *      MUSI wymienić brakującą tabelę, Pulpit MUSI pokazać notice;
 *   B) NAPRAWA ŚRODOWISKA: filtr zdjęty, migracja znowu → tabela wraca, wersja = stała, opcja błędu znika;
 *   C) KONTROLA: kompletny schemat + wersja stała → migracja nic nie zmienia (brama zamknięta).
 *
 * Uruchamiać na STOSIE PRÓBNYM (zrzuca i odtwarza tabelę opinii; na stosie z danymi — nie).
 * EXIT 0 = A, B, C zgodne · 1 = WADA/REGRESJA · 2 = przyrząd/środowisko.
 */

global $wpdb;
$tab_opinie = $wpdb->prefix . 'sklepag_opinie';

function a6_przyrzad( $powod ) {
	echo 'STRAZNIK: WYNIK=BLAD-SRODOWISKA (' . $powod . ")\n";
	exit( 2 );
}
foreach ( array( 'sklepag_migruj_schemat_jesli_trzeba', 'sklepag_tabele_sql' ) as $f ) {
	if ( ! function_exists( $f ) ) {
		a6_przyrzad( 'brak funkcji ' . $f );
	}
}
if ( ! defined( 'SKLEPAG_WERSJA_SCHEMATU' ) ) {
	a6_przyrzad( 'brak SKLEPAG_WERSJA_SCHEMATU' );
}
$STALA = (int) SKLEPAG_WERSJA_SCHEMATU;
$STARA = $STALA - 1;

$wynik = array();

/* ---------- A) PODŁOŻONA AWARIA: CREATE opinii nie wykonuje się ---------- */
$wpdb->query( "DROP TABLE IF EXISTS `{$tab_opinie}`" ); // phpcs:ignore
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tab_opinie ) ) ) === $tab_opinie ) {
	a6_przyrzad( 'nie udalo sie zrzucic tabeli opinii' );
}
update_option( 'sklepag_wersja_schematu', $STARA, false );
delete_option( 'sklepag_schemat_blad' );

$blokuj = static function ( $query ) use ( $tab_opinie ) {
	if ( 0 === stripos( ltrim( (string) $query ), 'CREATE TABLE' ) && false !== stripos( (string) $query, $tab_opinie ) ) {
		return 'SELECT 1'; // jak brak prawa CREATE: zapytanie „przechodzi”, tabela nie powstaje
	}
	return $query;
};
add_filter( 'query', $blokuj, 1 );
sklepag_migruj_schemat_jesli_trzeba();
remove_filter( 'query', $blokuj, 1 );

$wersja_a = (int) get_option( 'sklepag_wersja_schematu', 0 );
$blad_a   = get_option( 'sklepag_schemat_blad', array() );
$tab_a    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tab_opinie ) ) ) === $tab_opinie;
echo 'A. tabela opinii po migracji: ' . ( $tab_a ? 'JEST (podlozenie nie weszlo?)' : 'BRAK (jak zalozono)' ) . "\n";
echo 'A. wersja schematu: ' . $wersja_a . ' (OCZEKIWANE: ' . $STARA . " — STARA, bo schemat niekompletny)\n";
echo 'A. sklepag_schemat_blad: ' . ( is_array( $blad_a ) && ! empty( $blad_a['braki'] ) ? implode( ',', (array) $blad_a['braki'] ) : 'BRAK' ) . " (OCZEKIWANE: zawiera opinie)\n";
$a_ok = ( ! $tab_a ) && ( $wersja_a === $STARA ) && is_array( $blad_a ) && ! empty( $blad_a['braki'] ) && false !== strpos( implode( ',', (array) $blad_a['braki'] ), 'sklepag_opinie' );
// Pulpit: komunikat o niekompletnym schemacie — funkcja wołana wprost (pełny render Pulpitu pod
// wp-cli nie dochodzi do skutku) + sprawdzenie, że Pulpit tę funkcję WOŁA (plik wtyczki na stosie).
$render = '';
$plik_pulpitu = WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-dashboard.php';
if ( ! function_exists( 'sklepag_notice_schematu' ) && file_exists( $plik_pulpitu ) ) {
	require_once $plik_pulpitu; // pod wp-cli pliki panelu nie są ładowane (is_admin() = false)
}
if ( function_exists( 'sklepag_notice_schematu' ) ) {
	ob_start();
	sklepag_notice_schematu();
	$render = (string) ob_get_clean();
}
$pulpit_wola  = file_exists( $plik_pulpitu ) && preg_match( '/^\s*sklepag_notice_schematu\(\);/m', (string) file_get_contents( $plik_pulpitu ) );
$notice_a     = false !== strpos( $render, 'Baza sklepu jest niekompletna' ) && false !== strpos( $render, 'sklepag_opinie' ) && $pulpit_wola;
echo 'A. komunikat o niekompletnej bazie: ' . ( '' !== $render ? 'JEST' : 'BRAK' ) . ', Pulpit go wola: ' . ( $pulpit_wola ? '1' : '0' ) . " (OCZEKIWANE: JEST, 1)\n";
$a_ok = $a_ok && $notice_a;
echo '   WYNIK=' . ( $a_ok ? 'PASS' : 'FAIL - wersja podniesiona mimo braku tabeli albo brak komunikatu (nieudana migracja znika po cichu)' ) . "\n";
$wynik[] = $a_ok;

/* ---------- B) ŚRODOWISKO NAPRAWIONE: migracja ponawia się i domyka ---------- */
sklepag_migruj_schemat_jesli_trzeba();
$wersja_b = (int) get_option( 'sklepag_wersja_schematu', 0 );
$blad_b   = get_option( 'sklepag_schemat_blad', array() );
$tab_b    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tab_opinie ) ) ) === $tab_opinie;
echo 'B. tabela opinii: ' . ( $tab_b ? 'JEST' : 'BRAK' ) . " (OCZEKIWANE: JEST)\n";
echo 'B. wersja schematu: ' . $wersja_b . ' (OCZEKIWANE: ' . $STALA . ")\n";
echo 'B. sklepag_schemat_blad: ' . ( empty( $blad_b ) ? 'BRAK' : 'JEST' ) . " (OCZEKIWANE: BRAK)\n";
$b_ok = $tab_b && ( $wersja_b === $STALA ) && empty( $blad_b );
echo '   WYNIK=' . ( $b_ok ? 'PASS' : 'FAIL - migracja nie ponowila sie po naprawie srodowiska' ) . "\n";
$wynik[] = $b_ok;

/* ---------- C) KONTROLA: kompletny schemat, wersja stała — brama zamknięta ---------- */
$licznik = 0;
$licz    = static function ( $query ) use ( &$licznik ) {
	if ( 0 === stripos( ltrim( (string) $query ), 'CREATE TABLE' ) ) {
		++$licznik;
	}
	return $query;
};
add_filter( 'query', $licz, 1 );
sklepag_migruj_schemat_jesli_trzeba();
remove_filter( 'query', $licz, 1 );
echo 'C. CREATE TABLE przy aktualnej wersji: ' . $licznik . " (OCZEKIWANE: 0 — brama zamknieta)\n";
$c_ok = ( 0 === $licznik ) && ( (int) get_option( 'sklepag_wersja_schematu', 0 ) === $STALA );
echo '   WYNIK=' . ( $c_ok ? 'PASS' : 'FAIL - brama wersji przestala dzialac' ) . "\n";
$wynik[] = $c_ok;

$ok = ! in_array( false, $wynik, true );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
