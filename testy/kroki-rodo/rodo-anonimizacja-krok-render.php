<?php
/**
 * Krok 2b: render panelu szczegółów ZANONIMIZOWANEGO zamówienia.
 *
 * WAŻNA 1 recenzji #234 r6: po naprawie r5 (mail_tresc=NULL) panel wypisywał
 * „Treść maila jeszcze nie powstała (zamówienie niedomknięte)" obok statusu
 * „mail wysłany" i wiersza „zanonimizowany" — trzy zdania sprzeczne na jednej
 * stronie. Ten krok woła PRAWDZIWĄ funkcję renderu (nie kopię markupu) i melduje,
 * które frazy stoją w HTML-u. Werdykt liczy sędzia-powłoka.
 *
 * Wywołanie: ... wp eval-file - <id> — id ZANONIMIZOWANEGO zamówienia w $args[0].
 * Musi biec PRZED krokiem stanu (tamten sprząta zamówienie).
 *
 * @package sklep-agentow-testy
 */

global $wpdb;

$id = isset( $args[0] ) ? (int) $args[0] : 0;
if ( ! $id ) {
	echo "PRZYRZAD=BRAK-ID\n";
	exit( 2 );
}

/* Pliki admina żyją za `is_admin()` — pod WP-CLI dociągamy TE SAME pliki produkcyjne.
   admin-dashboard.php pierwszy: render używa sklepag_badge_statusu() stamtąd. */
foreach ( array( 'admin-dashboard.php', 'admin-zamowienie.php' ) as $plik ) {
	$sciezka = WP_PLUGIN_DIR . '/sklep-agentow/includes/' . $plik;
	if ( ! is_readable( $sciezka ) ) {
		echo 'PRZYRZAD=BRAK-PLIKU-' . $plik . "\n";
		exit( 2 );
	}
	require_once $sciezka;
}
if ( ! function_exists( 'sklepag_admin_zamowienie' ) ) {
	echo "PRZYRZAD=RENDER-NIEDOSTEPNY\n";
	exit( 2 );
}

$admini = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $admini ) ) {
	echo "PRZYRZAD=BRAK-ADMINA\n";
	exit( 2 );
}
wp_set_current_user( $admini[0]->ID );

$_GET['zamowienie'] = $id;
/* Render z parametrami powrotu z admin-post — tak widzi stronę właściciel sekundę po anonimizacji
   (komunikat u góry). #270 r1 KRYTYCZNA 1: komunikat meldował usunięcie faktury, której nie usunął.

   ⛔ PARAMETRY BIERZEMY Z PRAWDZIWEGO PRZEKIEROWANIA, NIE Z GŁOWY (#270 r2/r3, DROBNA 1).
   Do tej wersji krok wpisywał sobie `faktura=1` sam, a asercja niżej sprawdzała komunikat,
   który z tego parametru powstaje — czyli MIERZYŁA WŁASNE WEJŚCIE. Gdyby produkcja przestała
   wysyłać `faktura=1`, ten test dalej świeciłby na zielono. Teraz adres przekierowania podaje
   wołający (zmienna środowiskowa RODO_REDIRECT), a bierze go z wyjścia kroku szczęśliwej
   ścieżki, gdzie powstał w `wp_redirect` KODU PRODUKCJI.
   Brak zmiennej = ODMOWA biegu (exit 2), nie ciche wpisanie domyślnych wartości: „nie ma
   parametru” i „parametr jest zły” muszą wyglądać różnie. */
$przekierowanie = (string) getenv( 'RODO_REDIRECT' );
if ( '' === $przekierowanie ) {
	echo "PRZYRZAD=BRAK-RODO-REDIRECT\n";
	exit( 2 );
}
$zapytanie = (string) wp_parse_url( $przekierowanie, PHP_URL_QUERY );
$parametry = array();
wp_parse_str( $zapytanie, $parametry );
if ( ! isset( $parametry['sklepag_anonimizacja'] ) ) {
	echo "PRZYRZAD=REDIRECT-BEZ-POTWIERDZENIA\n";
	exit( 2 );
}
$_GET['sklepag_anonimizacja'] = (string) $parametry['sklepag_anonimizacja'];
/* `faktura` bierzemy TAKA, JAKA PRZYSZLA — także gdy jej nie ma. Brak parametru to
   informacja o zachowaniu produkcji, a nie luka do zalatania domyslna wartoscia. */
$_GET['faktura'] = isset( $parametry['faktura'] ) ? (string) $parametry['faktura'] : '';
echo 'RENDER_PARAMETRY_Z_PRODUKCJI=' . rawurlencode( $zapytanie ) . "\n";
ob_start();
sklepag_admin_zamowienie();
$html = (string) ob_get_clean();

/* Warunek wstępny przyrządu: render naprawdę doszedł do skutku i dotyczy TEGO zamówienia. */
if ( '' === $html || false === strpos( $html, 'sklepag-zamowienie' ) || false === strpos( $html, '#' . $id ) ) {
	echo "PRZYRZAD=RENDER-PUSTY-LUB-CUDZY\n";
	exit( 2 );
}
echo "RENDER_PRZYRZAD=OK\n";

/* Szukamy po RDZENIU „niedomkni" (odmiana polska), frazy naprawy po pełnym brzmieniu. */
echo 'RENDER_FRAZA_NIEDOMKNIETE=' . ( false !== strpos( $html, 'niedomkni' ) ? '1' : '0' ) . "\n";
echo 'RENDER_FRAZA_USUNIETA=' . ( false !== strpos( $html, 'usunięta przy anonimizacji' ) ? '1' : '0' ) . "\n";
/* #270 r1: komunikat po powrocie i wiersz „Dane do faktury” — faktura ZOSTAJE i ma być widoczna (NIP z zasiewu). */
echo 'RENDER_KOMUNIKAT_FAKTURA_USUNIETA=' . ( false !== strpos( $html, 'dane do faktury i treść maila usunięte' ) ? '1' : '0' ) . "\n";
echo 'RENDER_KOMUNIKAT_FAKTURA_ZOSTALA=' . ( false !== strpos( $html, 'dane do faktury (nazwa, NIP, adres) ZOSTAŁY' ) ? '1' : '0' ) . "\n";
echo 'RENDER_FAKTURA_WIDOCZNA=' . ( false !== strpos( $html, '5260250274' ) ? '1' : '0' ) . "\n";
