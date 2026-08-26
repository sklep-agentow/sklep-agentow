<?php
/**
 * Test padający — Ł-09: sklepag_przyjmij_zip() (includes/admin-produkty.php)
 * przy UPLOAD_ERR_INI_SIZE (PHP odrzucił plik na poziomie php.ini, ZANIM
 * wtyczka cokolwiek zobaczyła) zawsze cytuje WŁASNY, zadeklarowany limit
 * (SKLEPAG_UPLOAD_LIMIT_B = 20 MB), NIEZALEŻNIE od realnego
 * upload_max_filesize środowiska. Na probny_wp: upload_max_filesize=2M,
 * post_max_size=8M (php -i, zmierzone 26.08.2026) — administrator wysyłający
 * zwykły plik >2 MB (a więc DALEKO PONIŻEJ zadeklarowanych "20 MB") dostaje
 * komunikat każący mu wierzyć, że limit to 20 MB, i będzie próbował w kółko
 * bez szans powodzenia, nie wiedząc o prawdziwej przyczynie.
 *
 * Uruchomienie: docker exec -u www-data probny_wp wp --path=/var/www/html eval-file <ta_sciezka>
 *
 * Wyjście z RZECZYWISTEGO uruchomienia end-to-end przez PRAWDZIWY HTTP
 * multipart POST (26.08.2026, probny_wp, plik 5 MB — POWYŻEJ
 * upload_max_filesize=2M, ale PONIŻEJ zarówno post_max_size=8M jak i
 * zadeklarowanego limitu wtyczki 20 MB — czysty przypadek testowy):
 *   curl -F plik=@sredni-5mb.zip ... -> HTTP 302
 *   Location: .../sklepag-produkty&widok=formularz&produkt=1&sklepag_blad=
 *     "Upload nie powi%C3%B3d%C5%82 si%C4%99 (kod 1) — spróbuj ponownie;
 *      du%C5%BCy plik? Limit: 20 MB."
 *   (zdekodowane: "Upload nie powiódł się (kod 1) — spróbuj ponownie;
 *      duży plik? Limit: 20 MB.")
 *   stan produktu 1 PO probie: plik_rozmiar=146, aktywny=1 (bez zmian —
 *      dobra strona: nic nie nadpisało istniejącego dobrego pliku)
 *   ZERO wpisu w wp_sklepag_zdarzenia opisującego tę próbę.
 * WYNIK=FAIL: komunikat kłamie o realnym limicie, admin nie ma jak się
 * dowiedzieć że prawdziwa ściana to 2 MB (php.ini), nie 20 MB (kod wtyczki).
 *
 * Poniższy test jednostkowy odtwarza DOKŁADNIE tę samą logikę komunikatu
 * przez bezpośrednie wywołanie PRODUKCYJNEJ funkcji sklepag_przyjmij_zip()
 * z fabrykowanym $_FILES o error=UPLOAD_ERR_INI_SIZE (to, co PHP naprawdę
 * ustawia przy przekroczeniu upload_max_filesize) — bez potrzeby realnego
 * dużego pliku na dysku.
 */

$GLOBALS['sklepag_test_wroc_wywolany'] = null;
$wroc = function ( $komunikat ) {
	$GLOBALS['sklepag_test_wroc_wywolany'] = $komunikat;
	throw new \Exception( 'STOP:' . $komunikat ); // Zamiast wp_safe_redirect+exit w produkcji.
};

$_FILES['plik'] = array(
	'name'     => 'paczka.zip',
	'type'     => 'application/zip',
	'tmp_name' => '/tmp/nieistotne',
	'error'    => UPLOAD_ERR_INI_SIZE, // = 1, dokładnie to co PHP ustawia po realnym przekroczeniu upload_max_filesize.
	'size'     => 0,
);

require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-produkty.php';

try {
	sklepag_przyjmij_zip( $wroc );
} catch ( \Exception $e ) {
	// oczekiwane - $wroc rzuca wyjątkiem zamiast redirect+exit.
}

$komunikat = $GLOBALS['sklepag_test_wroc_wywolany'];
echo "PADA_TEST: komunikat_bledu=\"$komunikat\"\n";
echo "PADA_TEST: rzeczywisty_upload_max_filesize_na_srodowisku=" . ini_get( 'upload_max_filesize' ) . " (zmierzone php -i: 2M)\n";

if ( false !== strpos( $komunikat, '20 MB' ) ) {
	echo "PADA_TEST: WYNIK=FAIL - komunikat cytuje zadeklarowany limit wtyczki (20 MB) mimo ze PRAWDZIWA przyczyna to php.ini (tu: upload_max_filesize=2M) - admin dostaje falszywa liczbe i nie ma jak sam zdiagnozowac problemu.\n";
	exit( 1 );
}
echo "PADA_TEST: WYNIK=PASS\n";
exit( 0 );
