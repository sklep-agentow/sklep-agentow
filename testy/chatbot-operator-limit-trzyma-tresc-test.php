<?php
/**
 * ODMOWA „ZA DŁUGA WIADOMOŚĆ” NIE KASUJE TEGO, CO OPERATOR NAPISAŁ — panel rozmów ChatBota.
 *
 * Znalezisko z recenzji #275 (runda 2, WAŻNA 1): odmowa u operatora kończyła żądanie
 * przekierowaniem, a formularz rysował się od nowa z pustym `textarea` — do 2000+ znaków
 * przepadało, a komunikat kazał operatorowi „skrócić” coś, czego już nie miał.
 *
 * ⛔ To jest TRZECI egzemplarz klasy zamkniętej w sklepie jako `CZ-2` (formularz produktu)
 *    i `CZ-3` (opinia kupującego). Wzorzec naprawy — przechowalnia per użytkownik na 5 minut —
 *    stoi w `wtyczki/sklep-agentow/includes/admin-produkty.php:384`.
 *
 * Co ten strażnik mierzy (i czego NIE mierzy):
 *   A. szkic odłożony przez `chatl_odloz_szkic_operatora()` da się odczytać w całości,
 *   B. klucz jest per ROZMOWA — szkic z jednej rozmowy NIE wchodzi do drugiej (inaczej
 *      operator wysłałby nie to zdanie i nie temu człowiekowi),
 *   C. klucz jest per UŻYTKOWNIK — dwóch operatorów nie dzieli szkicu,
 *   D. odczyt jest JEDNORAZOWY: po skasowaniu przechowalni ta sama treść nie wraca,
 *   E. treść ze znakami specjalnymi wraca BEZ zmian (to samo, co operator napisał),
 *   F. limit `CHATL_MAX_WIADOMOSC` istnieje i jest liczbą — bez tego cała ścieżka odmowy
 *      nie ma się o co oprzeć.
 * ⛔ NIE mierzy samego przekierowania: `chatl_przekieruj_blad()` kończy się `exit`, więc
 *    asercje stojące po nim nigdy by nie pobiegły, a kod wyjścia i tak wyszedłby zerowy —
 *    test wyglądałby na zdany, nie wykonawszy połowy sprawdzeń (ta sama pułapka, którą
 *    opisuje nagłówek `testy/produkt-formularz-trzyma-tresc-po-odrzucie-test.php`).
 *
 * Uruchomienie:
 *   bash narzedzia-qa/test-php.sh chatbot-operator-limit-trzyma-tresc-test
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/operator.php';

ob_implicit_flush( false );

// ⛔ LICZNIK BLEDOW MUSI ZYC W $GLOBALS, NIE W ZMIENNEJ LOKALNEJ. Pod `wp eval-file`
// kod najwyzszego poziomu NIE biegnie w zasiegu globalnym, wiec `global $bledy` w funkcji
// wskazywalo INNA zmienna niz ta tutaj: asercje meldowaly ZLE, a test i tak konczyl EXIT=0.
// Zlapane 2.09 proba kontrolna z podlozona wada — bez niej ten straznik nie pilnowalby NICZEGO.
$GLOBALS['chatl_test_bledy'] = 0;

/**
 * Asercja z powodem. Wypisuje jedną linię na sprawdzenie.
 *
 * @param string $opis      Co sprawdzamy.
 * @param bool   $warunek   Wynik.
 * @param string $szczegol  Pomiar do wypisania.
 * @return void
 */
function chops( $opis, $warunek, $szczegol = '' ) {
	if ( $warunek ) {
		echo '  OK   ' . $opis . ( '' !== $szczegol ? '  [' . $szczegol . ']' : '' ) . "\n";
		return;
	}
	echo '  ZLE  ' . $opis . ( '' !== $szczegol ? '  [' . $szczegol . ']' : '' ) . "\n";
	++$GLOBALS['chatl_test_bledy'];
}

echo "=== WARUNKI WSTEPNE PRZYRZADU ===\n";
foreach ( array( 'chatl_odloz_szkic_operatora', 'chatl_klucz_szkicu_operatora' ) as $funkcja ) {
	if ( ! function_exists( $funkcja ) ) {
		echo "PRZYRZAD: brak funkcji {$funkcja} — nie ma czego mierzyc\n";
		exit( 2 );
	}
}
if ( ! defined( 'CHATL_MAX_WIADOMOSC' ) ) {
	echo "PRZYRZAD: brak stalej CHATL_MAX_WIADOMOSC\n";
	exit( 2 );
}
chops( 'F. limit wiadomosci jest liczba dodatnia', is_int( CHATL_MAX_WIADOMOSC ) && CHATL_MAX_WIADOMOSC > 0, 'CHATL_MAX_WIADOMOSC=' . CHATL_MAX_WIADOMOSC );

$uzytkownik = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $uzytkownik ) ) {
	echo "PRZYRZAD: brak administratora\n";
	exit( 2 );
}
wp_set_current_user( $uzytkownik[0]->ID );

$sesja_a = 'chops-a-' . wp_generate_password( 8, false );
$sesja_b = 'chops-b-' . wp_generate_password( 8, false );
// Treść DŁUŻSZA niż limit — dokładnie taka, jaką odrzuca handler, plus znaki, na których
// łatwo o zgubione escapowanie (apostrof, cudzysłów, nowa linia, ogonki).
$tresc = str_repeat( 'Zażółć gęślą jaźń — "cytat" i \'apostrof\'.' . "\n", (int) ceil( ( CHATL_MAX_WIADOMOSC + 50 ) / 41 ) );

echo "=== A. SZKIC WRACA W CALOSCI ===\n";
chatl_odloz_szkic_operatora( $sesja_a, $tresc );
$odczyt = (string) get_transient( chatl_klucz_szkicu_operatora( $sesja_a ) );
chops( 'A. odlozony szkic da sie odczytac', '' !== $odczyt, 'dlugosc=' . mb_strlen( $odczyt ) );
chops( 'A. tresc identyczna, znak w znak', $odczyt === $tresc, 'wpisane=' . mb_strlen( $tresc ) . ' odczytane=' . mb_strlen( $odczyt ) );
chops( 'A. szkic jest DLUZSZY niz limit (czyli mierzymy przypadek odmowy)', mb_strlen( $tresc ) > CHATL_MAX_WIADOMOSC, mb_strlen( $tresc ) . ' > ' . CHATL_MAX_WIADOMOSC );

echo "=== E. ZNAKI SPECJALNE BEZ ZMIAN ===\n";
chops( 'E. ogonki, cudzyslowy, apostrofy i nowe linie przetrwaly', false !== mb_strpos( $odczyt, 'Zażółć gęślą jaźń' ) && false !== mb_strpos( $odczyt, "\n" ), 'zawiera ogonki i nowa linie' );

echo "=== B. KLUCZ PER ROZMOWA ===\n";
$obca = (string) get_transient( chatl_klucz_szkicu_operatora( $sesja_b ) );
chops( 'B. szkic z rozmowy A NIE wchodzi do rozmowy B', '' === $obca, 'odczyt dla B: ' . ( '' === $obca ? 'pusto' : 'NIEPUSTO' ) );
chops( 'B. klucze obu rozmow sa rozne', chatl_klucz_szkicu_operatora( $sesja_a ) !== chatl_klucz_szkicu_operatora( $sesja_b ) );

echo "=== C. KLUCZ PER UZYTKOWNIK ===\n";
$klucz_admina = chatl_klucz_szkicu_operatora( $sesja_a );
$drugi = get_users( array( 'number' => 1, 'exclude' => array( $uzytkownik[0]->ID ) ) );
if ( empty( $drugi ) ) {
	$id_drugi = wp_insert_user( array(
		'user_login' => 'chops-drugi-' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 16 ),
		'role'       => 'administrator',
	) );
	$sprzataj_uzytkownika = ! is_wp_error( $id_drugi ) ? (int) $id_drugi : 0;
} else {
	$id_drugi             = (int) $drugi[0]->ID;
	$sprzataj_uzytkownika = 0;
}
if ( is_wp_error( $id_drugi ) || 0 === (int) $id_drugi ) {
	echo "PRZYRZAD: nie umiem uzyskac drugiego uzytkownika\n";
	exit( 2 );
}
wp_set_current_user( (int) $id_drugi );
$klucz_drugiego = chatl_klucz_szkicu_operatora( $sesja_a );
chops( 'C. dwoch operatorow ma ROZNE klucze dla tej samej rozmowy', $klucz_admina !== $klucz_drugiego );
chops( 'C. drugi operator NIE widzi cudzego szkicu', '' === (string) get_transient( $klucz_drugiego ) );
wp_set_current_user( $uzytkownik[0]->ID );

echo "=== D. ODCZYT JEDNORAZOWY ===\n";
delete_transient( chatl_klucz_szkicu_operatora( $sesja_a ) );
chops( 'D. po skasowaniu przechowalni szkic NIE wraca', '' === (string) get_transient( chatl_klucz_szkicu_operatora( $sesja_a ) ) );

// sprzątanie
delete_transient( chatl_klucz_szkicu_operatora( $sesja_b ) );
if ( 0 !== $sprzataj_uzytkownika ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $sprzataj_uzytkownika );
}

echo "\n";
if ( 0 === (int) $GLOBALS['chatl_test_bledy'] ) {
	echo "OPERATOR: odmowa NIE kasuje wpisanej tresci (EXIT=0)\n";
	exit( 0 );
}
echo 'OPERATOR: ' . (int) $GLOBALS['chatl_test_bledy'] . " sprawdzen NIE WYSZLO (EXIT=1)\n";
exit( 1 );
