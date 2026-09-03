<?php
/**
 * STRAŻNIK — A6-F1: wersja schematu rośnie TYLKO po zmierzonym, kompletnym schemacie.
 *
 * Wada (do 0.9.38 włącznie; naprawiona w 0.9.39, czyli w wydaniu, którego ten strażnik pilnuje):
 * aktywacja i migracja podnosiły `sklepag_wersja_schematu` od razu po pętli
 * `dbDelta()`, nie sprawdzając, czy tabele i kolumny naprawdę powstały. Nieudana migracja
 * (brak prawa ALTER/CREATE, cudza tabela) zamykała bramę „wersja == stała” na zawsze —
 * sklep nie przyjmował zamówień i nikt nie wiedział dlaczego. Przyczyna-matka H.
 *
 * Cztery kierunki (A-C to schemat, D to sam dlawik ponowienia):
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
/* ⛔ Takze PRZED przypadkiem A. Poprzedni BIEG tego straznika (albo dowolne zadanie
 * do sklepu w ostatnich 5 minutach) zostawia znacznik `sklepag_schemat_ponowienie`,
 * wiec A dostawalby wczesny `return` i caly straznik konczyl EXIT=1 przy zdrowym kodzie.
 * Zlapane wlasna proba kontrolna: po biegu z cofnietym kasowaniem NASTEPNY bieg,
 * juz z naprawa, dalej dawal EXIT=1 — test nie byl powtarzalny.
 */
delete_transient( 'sklepag_schemat_ponowienie' );
sklepag_migruj_schemat_jesli_trzeba();
remove_filter( 'query', $blokuj, 1 );

$znacznik_po_a = ( false !== get_transient( 'sklepag_schemat_ponowienie' ) );  // D: po NIEUDANEJ migracji ma ZOSTAC
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
/*
 * ⛔ WLASNE WARUNKI WSTEPNE PRZED KAZDYM PRZYPADKIEM.
 * Produkcja dlawi ponowienie migracji transientem `sklepag_schemat_ponowienie` na 5 minut
 * (aktywacja.php) — sensownie, bo bez odstepu kazde zadanie placiloby 7 x dbDelta.
 * Ale ten straznik wola migracje TRZY RAZY w JEDNYM procesie: po przypadku A znacznik
 * zostaje ustawiony (naprawa w A z zalozenia sie nie udaje), wiec B i C dostawaly wczesny
 * `return` zamiast realnej proby i OBA padaly. Straznik mierzyl wtedy WLASNY dlawik,
 * nie zachowanie produkcji. Kasujemy znacznik przed kazdym kolejnym przypadkiem —
 * to odpowiednik „deaktywuj i aktywuj”, ktory produkcja jawnie dopuszcza.
 */
delete_transient( 'sklepag_schemat_ponowienie' );
sklepag_migruj_schemat_jesli_trzeba();
$znacznik_po_b = ( false !== get_transient( 'sklepag_schemat_ponowienie' ) );  // D: po UDANEJ migracji ma ZNIKNAC
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
// Ten sam dlawik co przed B — przypadek C tez wola migracje i tez musi
// dostac realna probe, nie wczesny `return` po znaczniku z poprzedniego kroku.
delete_transient( 'sklepag_schemat_ponowienie' );
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

/* ---------- D) SAM DŁAWIK: kasowany w A–C trzy razy, a ANI RAZU nie mierzony ----------
 * Przypadki A–C kasują `sklepag_schemat_ponowienie` przed każdym wołaniem migracji, żeby dostać
 * realną próbę. To poprawne — ale znaczyło, że gdyby dławik zniknął albo przestał działać, TEN test
 * nie zauważyłby tego ani razu (recenzja #274 r4, WAŻNA 1).
 *
 * ⛔ MIERZYMY TO, CO KOD NAPRAWDĘ ROBI, a nie to, co brzmi sensownie. Dwie wcześniejsze wersje tego
 * przypadku sprawdzały, czy „drugie wołanie pod rząd" zostanie zatrzymane — i padały na KAŻDYM
 * kodzie, także tam, gdzie dławik działa bez zarzutu. Powód: `aktywacja.php:302-308` kasuje znacznik
 * PO UDANEJ migracji, więc po sukcesie kolejne wołanie MA prawo pracować. Dławik istnieje po to, by
 * nie ponawiać w kółko migracji, która PADA. Test scenariusza, którego kod nie ma, to nie strażnik.
 *
 * Właściwe pytanie ma dwa kierunki i oba są tu mierzone:
 *   D1 — po migracji NIEUDANEJ (przypadek A) znacznik MUSI zostać, inaczej pętla ponowień wraca;
 *   D2 — po migracji UDANEJ (przypadek B) znacznik MUSI zniknąć.
 * Oba stany zapisałam w A i B, tuż po tamtejszych wołaniach migracji.
 *
 * ⛔ D3 MIERZY INNĄ FUNKCJĘ NIŻ D1 i D2, i to jest sedno uwagi WAŻNEJ 2 z rundy 5.
 * Komunikat na Pulpicie obiecuje właścicielowi konkretną drogę: „deaktywuj i aktywuj wtyczkę,
 * a zadziała natychmiast". Tę drogę realizuje `sklepag_aktywacja()` (`aktywacja.php:264`), a NIE
 * `sklepag_migruj_schemat_jesli_trzeba()` (`:303-308`), którą mierzą D1 i D2. Do rundy 6 ŻADEN
 * strażnik nie wołał `sklepag_aktywacja()` — czyli obietnica z Pulpitu nie była mierzona nigdzie,
 * a dowód D2 opisywał ją słowami, których nie sprawdzał.
 */
// D3: droga, ktora Pulpit PODAJE wlascicielowi — „deaktywuj i aktywuj". Wolamy PRAWDZIWA
// funkcje aktywacji, nie migracje, i sprawdzamy, czy znacznik po niej znika.
set_transient( 'sklepag_schemat_ponowienie', 1, 5 * MINUTE_IN_SECONDS );
$znacznik_przed_akt = ( false !== get_transient( 'sklepag_schemat_ponowienie' ) );
sklepag_aktywacja();
$znacznik_po_akt = ( false !== get_transient( 'sklepag_schemat_ponowienie' ) );

echo 'D. znacznik ponowienia: po NIEUDANEJ migracji ' . ( $znacznik_po_a ? 'JEST' : 'BRAK' )
	. ' (OCZEKIWANE: JEST — inaczej ponowienia wracaja w petli), po UDANEJ '
	. ( $znacznik_po_b ? 'JEST' : 'BRAK' ) . ' (OCZEKIWANE: BRAK), przed aktywacja '
	. ( $znacznik_przed_akt ? 'JEST' : 'BRAK' ) . ' (OCZEKIWANE: JEST — inaczej nie ma czego kasowac), '
	. 'po sklepag_aktywacja() ' . ( $znacznik_po_akt ? 'JEST' : 'BRAK' )
	. " (OCZEKIWANE: BRAK — to droga, ktora Pulpit podaje wlascicielowi)\n";
$d_ok = $znacznik_po_a && ! $znacznik_po_b && $znacznik_przed_akt && ! $znacznik_po_akt;
echo '   WYNIK=' . ( $d_ok ? 'PASS' : 'FAIL - dlawik nie trzyma po awarii, nie zwalnia po sukcesie albo aktywacja go NIE kasuje' ) . "\n";
$wynik[] = $d_ok;

$ok = ! in_array( false, $wynik, true );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
