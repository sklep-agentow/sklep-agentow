<?php
/**
 * Wygląd widgetu i kontrast WCAG — test na prawdziwym kodzie wtyczki.
 *
 * Najważniejszy krok jest jeden: przy DOMYŚLNYCH ustawieniach wtyczka nie
 * dokłada do strony ANI JEDNEGO bajtu stylów. To jest dowód na obietnicę
 * „aktualizacja nie zmienia nikomu wyglądu" — bez niego pozostałaby deklaracją.
 *
 * Uruchomienie:
 *   docker cp testy/wyglad-test.php strona_wp_wordpress:/tmp/wyglad-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/wyglad-test.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

require_once WP_PLUGIN_DIR . '/chatbot-live/includes/wyglad.php';

$GLOBALS['chatl_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}
function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

echo "=== A. Sanityzacja koloru (pole formularza jest NIEZAUFANE) ===\n";
rowne( 'skrót 3-znakowy rozwijany do pełnego', '#aabbcc', chatl_sanitize_kolor( '#ABC' ) );
rowne( 'pełny zapis przechodzi, wielkość liter bez znaczenia', '#bfff38', chatl_sanitize_kolor( '#BFFF38' ) );
rowne( 'nazwa koloru odpada', '', chatl_sanitize_kolor( 'red' ) );
rowne( 'pięć znaków odpada', '', chatl_sanitize_kolor( '#12345' ) );
rowne( 'próba wstrzyknięcia odpada', '', chatl_sanitize_kolor( '#fff;}body{display:none' ) );
rowne( 'javascript: odpada', '', chatl_sanitize_kolor( 'javascript:alert(1)' ) );

echo "\n=== B. Kontrast liczony wzorem z WCAG 2.1 ===\n";
rowne( 'czerń–biel to maksimum skali', 21.0, chatl_kontrast( '#000000', '#ffffff' ) );
rowne( 'ten sam kolor to minimum skali', 1.0, chatl_kontrast( '#ffffff', '#ffffff' ) );
sprawdz( 'kolejność argumentów nie zmienia wyniku',
	chatl_kontrast( '#bfff38', '#08090b' ) === chatl_kontrast( '#08090b', '#bfff38' ) );
$k_domyslny = chatl_kontrast( '#bfff38', '#08090b' );
sprawdz( 'domyślna para (limonka/tusz) spełnia próg WCAG AA', $k_domyslny >= CHATL_WCAG_PROG, 'kontrast: ' . $k_domyslny . ':1' );
$k_zly = chatl_kontrast( '#bfff38', '#ffffff' );
sprawdz( 'limonka z bielą NIE spełnia progu', $k_zly < CHATL_WCAG_PROG, 'kontrast: ' . $k_zly . ':1' );
sprawdz( 'próg to 4.5 (WCAG 2.1, kryterium 1.4.3, poziom AA)', 4.5 === CHATL_WCAG_PROG );

echo "\n=== C. Automatyczny dobór koloru tekstu ===\n";
rowne( 'na jasnej limonce tekst ciemny', CHATL_KOLOR_CIEMNY, chatl_kolor_tekstu_na( '#bfff38' ) );
rowne( 'na czarnym tuszu tekst jasny', CHATL_KOLOR_JASNY, chatl_kolor_tekstu_na( '#08090b' ) );
rowne( 'na granacie tekst jasny', CHATL_KOLOR_JASNY, chatl_kolor_tekstu_na( '#12306b' ) );
rowne( 'na żółtym tekst ciemny', CHATL_KOLOR_CIEMNY, chatl_kolor_tekstu_na( '#ffd400' ) );
$auto_ok = true;
foreach ( array( '#bfff38', '#08090b', '#12306b', '#ffd400', '#e2001a', '#ffffff', '#7f7f7f' ) as $tlo ) {
	$auto_ok = $auto_ok && chatl_kontrast( $tlo, chatl_kolor_tekstu_na( $tlo ) ) >= CHATL_WCAG_PROG;
}
sprawdz( 'automat trafia w próg WCAG dla 7 różnych teł', $auto_ok );

echo "\n=== D. Domyślny wygląd NIE dokłada do strony ani jednego bajtu ===\n";
$dom = chatl_wyglad_domyslne();
rowne( 'przy domyślnych ustawieniach reguła CSS jest pusta', '', chatl_style_wygladu( $dom ) );
rowne( 'pusta reguła to zero bajtów', 0, strlen( chatl_style_wygladu( $dom ) ) );
$z = chatl_zmienne_wygladu( $dom );
rowne( 'domyślny kolor główny = paleta z arkusza', '#bfff38', $z['--chatl-glowny'] );
rowne( 'domyślny tekst na nim = tusz (jak przed 1.4.0)', '#08090b', $z['--chatl-glowny-tekst'] );
rowne( 'domyślny nagłówek = tusz', '#08090b', $z['--chatl-naglowek'] );
rowne( 'domyślny tekst nagłówka = papier (jak przed 1.4.0)', '#f5f5f5', $z['--chatl-naglowek-tekst'] );
rowne( 'domyślny pierścień = jak w arkuszu', 'rgba(191, 255, 56, 0.18)', $z['--chatl-pierscien'] );

echo "\n=== E. Zmieniony wygląd dokłada TYLKO to, co zmienione ===\n";
$granat = array_merge( $dom, array( 'kolor_glowny' => '#12306b' ) );
$css    = chatl_style_wygladu( $granat );
sprawdz( 'reguła siedzi na .chatl-korzen, NIE w :root', 0 === strpos( $css, '.chatl-korzen{' ), $css );
sprawdz( 'jest nowy kolor główny', false !== strpos( $css, '--chatl-glowny:#12306b' ) );
sprawdz( 'jest przeliczony pierścień', false !== strpos( $css, '--chatl-pierscien:rgba(18, 48, 107, 0.18)' ) );
sprawdz( 'jest przeliczony kolor tekstu na nim', false !== strpos( $css, '--chatl-glowny-tekst:#f5f5f5' ) );
sprawdz( 'NIE ma nagłówka — nikt go nie zmieniał', false === strpos( $css, '--chatl-naglowek:' ) );
sprawdz( 'doklejka jest krótka (poniżej 200 bajtów)', strlen( $css ) < 200, 'bajtów: ' . strlen( $css ) );

echo "\n=== F. Własny kolor tekstu i ocena kontrastu dla właściciela ===\n";
$zly   = array_merge( $dom, array( 'kolor_tekstu' => '#ffffff' ) );
$ocena = chatl_ocena_kontrastu( $zly );
sprawdz( 'ocena zwraca dwie pary (bąbelek i nagłówek)', 2 === count( $ocena ) );
sprawdz( 'biel na limonce oznaczona jako PONIŻEJ progu', false === $ocena[0]['ok'], $ocena[0]['etykieta'] . ': ' . $ocena[0]['kontrast'] . ':1' );
sprawdz( 'biel na tuszu oznaczona jako spełniająca próg', true === $ocena[1]['ok'], $ocena[1]['etykieta'] . ': ' . $ocena[1]['kontrast'] . ':1' );
$ocena_dom = chatl_ocena_kontrastu( $dom );
sprawdz( 'domyślny zestaw spełnia próg w OBU parach', $ocena_dom[0]['ok'] && $ocena_dom[1]['ok'] );

echo "\n=== G. Śmieci w ustawieniach nie psują widgetu ===\n";
$smieci = array_merge( $dom, array( 'kolor_glowny' => 'DROP TABLE', 'pozycja' => 'srodek' ) );
$w      = chatl_wyglad_ustawienia( $smieci );
rowne( 'niepoprawny kolor wraca do domyślnego', '#bfff38', $w['kolor_glowny'] );
rowne( 'nieznana pozycja wraca do domyślnej', 'prawo', $w['pozycja'] );
rowne( 'pozycja lewo jest przyjmowana', 'lewo', chatl_wyglad_ustawienia( array_merge( $dom, array( 'pozycja' => 'lewo' ) ) )['pozycja'] );
rowne( 'przy śmieciach reguła nadal pusta (bo wychodzą domyślne)', '', chatl_style_wygladu( $smieci ) );

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
