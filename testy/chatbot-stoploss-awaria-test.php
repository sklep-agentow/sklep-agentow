<?php
/**
 * ChatBot LIVE — stop-loss kosztowy NIE może być fail-open (T-1, paczka C2).
 *
 * PO CO: `chatl_ai_zuzycie_dnia()` zwracało zera zarówno przy „brak wiersza na
 * dziś", jak i przy BŁĘDZIE zapytania (padnięta/uszkodzona tabela licznika).
 * Zera < limit, więc `chatl_ai_dostepny()` mówiło „tak" i wtyczka wołała płatne
 * API przy liczniku, który nie liczy — właściciel dowiedziałby się z rachunku.
 * Po naprawie: błąd odczytu licznika = tryb AI WSTRZYMANY (odpowiadają reguły)
 * + trwały ślad błędu bazy (pasek w panelu).
 *
 * Do tego KR1/KR2 (audyt A5): PUSTE pole „dobowy limit tokenów" wyłączało
 * stop-loss (0 = wyłączony), puste „dzienny limit wywołań" zjeżdżało do 1 — a panel
 * mówił „zapisano". Kryterium zamrożonego testu dosłownie: puste pole liczbowe NIE
 * nadpisuje poprzedniej wartości i właściciel dostaje komunikat inny niż „zapisano".
 *
 * Przypadki BEZ WADY: działający licznik poniżej limitu → AI dostępne; wyczerpany
 * limit → niedostępne (stary stop-loss dalej działa); poprawne liczby w polach
 * zapisują się i dają „zapisano"; jawne 0 w limicie tokenów = świadome wyłączenie;
 * `chatl_rate_limit()` to inna funkcja (świadomie fail-open, limiter spamu) — nietykana.
 *
 * Awaria jest PRAWDZIWA (filtr `query` podmienia SELECT na tabelę, której nie ma).
 * Sprząta po sobie: opcje z migawki, wiersz licznika na dziś przywrócony.
 *
 * Kalibracja (opis PR C2): na main sprzed naprawy EXIT=1, po naprawie EXIT=0.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-stoploss-awaria-test.php strona_wp_wordpress:/tmp/csa.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/csa.php; echo EXIT=$?
 */

global $wpdb;
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/admin.php'; // panel ładuje się pod is_admin(); WP-CLI nie jest adminem
$wpdb->suppress_errors( true );
wp_set_current_user( 1 );

$GLOBALS['csa_bledy'] = 0;
function csa( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? ' (' . $szczegol . ')' : '' );
	if ( ! $ok ) {
		++$GLOBALS['csa_bledy'];
	}
}

/* ───────── MIGAWKA + SPRZĄTANIE ───────── */
$tabela = $wpdb->prefix . 'chatl_ai_uzycie';
$dzis   = current_time( 'Y-m-d' );
$GLOBALS['csa_mig'] = array(
	'chatl_ustawienia' => get_option( 'chatl_ustawienia', null ),
	'chatl_klucz_api'  => get_option( 'chatl_klucz_api', null ),
	'chatl_blad_bazy'  => get_option( 'chatl_blad_bazy', null ),
);
$GLOBALS['csa_wiersz'] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE dzien = %s", $dzis ), ARRAY_A ); // phpcs:ignore
$GLOBALS['csa_filtr']  = null;
register_shutdown_function(
	function () use ( $tabela, $dzis ) {
		global $wpdb;
		if ( $GLOBALS['csa_filtr'] ) {
			remove_filter( 'query', $GLOBALS['csa_filtr'], 999 );
		}
		foreach ( $GLOBALS['csa_mig'] as $o => $w ) {
			if ( null === $w ) {
				delete_option( $o );
			} else {
				update_option( $o, $w );
			}
		}
		$wpdb->delete( $tabela, array( 'dzien' => $dzis ), array( '%s' ) ); // phpcs:ignore
		if ( is_array( $GLOBALS['csa_wiersz'] ) ) {
			$wpdb->insert( $tabela, $GLOBALS['csa_wiersz'] ); // phpcs:ignore
		}
		wp_cache_flush();
	}
);

/** Psuje SELECT z licznika AI (tak wygląda uszkodzona/usunięta tabela). */
function csa_psuj() {
	$f = function ( $q ) {
		if ( false !== stripos( $q, 'chatl_ai_uzycie' ) && 0 === stripos( ltrim( $q ), 'SELECT' ) ) {
			return 'SELECT * FROM tabela_ktorej_nie_ma';
		}
		return $q;
	};
	add_filter( 'query', $f, 999 );
	$GLOBALS['csa_filtr'] = $f;
}
function csa_odpsuj() {
	if ( $GLOBALS['csa_filtr'] ) {
		remove_filter( 'query', $GLOBALS['csa_filtr'], 999 );
		$GLOBALS['csa_filtr'] = null;
	}
}
function csa_slad() {
	$b = get_option( 'chatl_blad_bazy' );
	return is_array( $b ) && isset( $b['funkcja'] ) ? (string) $b['funkcja'] : '';
}

// Warunki wyjściowe: klucz jest (stała albo opcja), limity świadome, licznik na dziś = 0.
if ( ! chatl_klucz_ze_stalej() ) {
	update_option( 'chatl_klucz_api', 'csa-klucz-probny' );
}
$u = chatl_ustawienia();
$u['ai_limit_dzienny'] = 5;
$u['ai_limit_tokenow'] = 0; // wyłączony — badamy licznik wywołań
update_option( 'chatl_ustawienia', $u );
$wpdb->delete( $tabela, array( 'dzien' => $dzis ), array( '%s' ) ); // phpcs:ignore
delete_option( 'chatl_blad_bazy' );
wp_cache_flush();

echo "=== A. BEZ WADY: działający licznik ===\n";
csa( 0 === chatl_ai_uzycie_dzis(), 'brak wiersza na dziś = 0 wywołań (to nie jest błąd)', (string) chatl_ai_uzycie_dzis() );
csa( true === chatl_ai_dostepny(), 'poniżej limitu → tryb AI dostępny' );
csa( '' === csa_slad(), 'brak wiersza NIE zostawia śladu błędu bazy', csa_slad() );

for ( $i = 0; $i < 5; $i++ ) {
	chatl_ai_zuzyj( array( 'tokeny_wejscia' => 10, 'tokeny_wyjscia' => 5 ) );
}
csa( 5 === chatl_ai_uzycie_dzis(), 'po 5 wywołaniach licznik = 5', (string) chatl_ai_uzycie_dzis() );
csa( false === chatl_ai_dostepny(), 'wyczerpany limit (5/5) → tryb AI NIEDOSTĘPNY (stary stop-loss działa)' );

echo "\n=== B. AWARIA: licznik nie do odczytania ===\n";
csa_psuj();
$z = chatl_ai_zuzycie_dnia();
$dostepny = chatl_ai_dostepny();
csa_odpsuj();
csa( false === $dostepny, 'T-1: błąd odczytu licznika → tryb AI WSTRZYMANY (nie fail-open)', var_export( $dostepny, true ) );
csa( 'chatl_ai_zuzycie_dnia' === csa_slad(), 'T-1: trwały ślad błędu bazy wskazuje odczyt licznika', csa_slad() );
csa( ! empty( $z['blad'] ), 'T-1: wynik odczytu niesie znacznik błędu (nie udaje zmierzonego zera)', wp_json_encode( $z ) );

echo "\n=== C. BEZ WADY po awarii: licznik wraca → decyzja wraca ===\n";
$wpdb->delete( $tabela, array( 'dzien' => $dzis ), array( '%s' ) ); // phpcs:ignore
csa( true === chatl_ai_dostepny(), 'po ustąpieniu awarii i zerowym liczniku AI znów dostępne' );

echo "\n=== D. KR1/KR2: puste pole liczbowe NIE nadpisuje limitu i nie udaje „zapisano\" ===\n";
function csa_zapisz( $post ) {
	$_POST = $post;
	$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
	$p = function ( $l ) {
		throw new Exception( 'R:' . $l );
	};
	add_filter( 'wp_redirect', $p, 1 );
	try {
		chatl_post_ustawienia_zapisz();
		$w = '(brak)';
	} catch ( Exception $e ) {
		$w = substr( $e->getMessage(), 2 );
	}
	remove_filter( 'wp_redirect', $p, 1 );
	wp_cache_flush();
	return $w;
}
$u = chatl_ustawienia();
$u['ai_limit_dzienny'] = 300;
$u['ai_limit_tokenow'] = 500000;
update_option( 'chatl_ustawienia', $u );
$adres = csa_zapisz( array( 'wlaczony' => 1, 'ai_limit_dzienny' => '', 'ai_limit_tokenow' => '' ) );
$po    = chatl_ustawienia();
csa( 300 === (int) $po['ai_limit_dzienny'] && 500000 === (int) $po['ai_limit_tokenow'], 'KR1/KR2: puste pola → POPRZEDNIE limity zostają (300 / 500000)', $po['ai_limit_dzienny'] . ' / ' . $po['ai_limit_tokenow'] );
csa( false === strpos( $adres, 'chatl_ok=zapisano' ) && false !== strpos( $adres, 'chatl_blad=' ), 'KR1/KR2: komunikat INNY niż „zapisano" (chatl_blad)', rawurldecode( $adres ) );
$adres = csa_zapisz( array( 'wlaczony' => 1, 'ai_limit_dzienny' => '50 000', 'ai_limit_tokenow' => 'abc' ) );
$po    = chatl_ustawienia();
csa( 300 === (int) $po['ai_limit_dzienny'] && 500000 === (int) $po['ai_limit_tokenow'] && false === strpos( $adres, 'chatl_ok=zapisano' ), 'KR1/KR2: nieczytelne („50 000", „abc") → poprzednie zostają, bez „zapisano"' );
$adres = csa_zapisz( array( 'wlaczony' => 1, 'ai_limit_dzienny' => '77', 'ai_limit_tokenow' => '250000' ) );
$po    = chatl_ustawienia();
csa( 77 === (int) $po['ai_limit_dzienny'] && 250000 === (int) $po['ai_limit_tokenow'] && false !== strpos( $adres, 'chatl_ok=zapisano' ), 'BEZ WADY: poprawne liczby → zapisane i „zapisano"', $adres );
$adres = csa_zapisz( array( 'wlaczony' => 1, 'ai_limit_dzienny' => '77', 'ai_limit_tokenow' => '0' ) );
$po    = chatl_ustawienia();
csa( 0 === (int) $po['ai_limit_tokenow'] && false !== strpos( $adres, 'chatl_ok=zapisano' ), 'BEZ WADY: jawne 0 w limicie tokenów = świadome wyłączenie, „zapisano"' );

echo "\n" . ( $GLOBALS['csa_bledy'] > 0 ? 'BLEDOW: ' . $GLOBALS['csa_bledy'] : 'WSZYSTKO OK' ) . "\n";
exit( $GLOBALS['csa_bledy'] > 0 ? 1 : 0 );
