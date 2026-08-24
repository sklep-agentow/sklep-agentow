<?php
/**
 * ChatBot LIVE — „Przetestuj klucz" mierzy TĘ konfigurację, która obsługuje gości
 * (paczka C5, znalezisko P2-Z1).
 *
 * PO CO (audyt): przycisk brał model ze stałej `CHATL_AI_MODEL`, a nie z
 * `chatl_ai_model()`, więc pomijał pole „własny model" z ustawień. Właściciel
 * wpisywał nazwę z literówką, bot po cichu zjeżdżał do reguł — a diagnostyka
 * mówiła „Klucz działa", bo pytała modelu domyślnego. Drugi objaw: udany test
 * kasował znacznik `chatl_blad_ai`, czyli JEDYNY sygnał o niedziałającym trybie AI,
 * dokładnie w chwili, gdy właściciel klikał, żeby ten sygnał zrozumieć.
 *
 * Zamrożona sonda `audyt/boty/proba-model.php` odtwarza wadę (EXIT zawsze 0) —
 * strażnikiem jest ten plik.
 *
 * NIC nie wychodzi na zewnątrz: żądania do API przechwytuje filtr `pre_http_request`,
 * więc test nie zużywa klucza właściciela ani limitu u dostawcy.
 *
 * Przypadki BEZ WADY: przy poprawnym kluczu i domyślnym modelu przycisk dalej mówi
 * „działa"; udana odpowiedź wydana GOŚCIOWI dalej gasi alarm.
 *
 * Sprząta po sobie: ustawienia i znacznik alarmu z migawki.
 *
 * Kalibracja (opis PR C5): na kodzie sprzed naprawy EXIT=1, po naprawie EXIT=0.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-test-klucza-test.php strona_wp_wordpress:/tmp/ctk.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/ctk.php; echo EXIT=$?
 */

global $wpdb;
require_once WP_PLUGIN_DIR . '/chatbot-live/includes/admin.php';

$GLOBALS['ctk_bledy'] = 0;
function ctk( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? ' (' . $szczegol . ')' : '' );
	if ( ! $ok ) {
		++$GLOBALS['ctk_bledy'];
	}
}

/* ───────── MIGAWKA + SPRZĄTANIE ───────── */
$GLOBALS['ctk_mig'] = array(
	'chatl_ustawienia' => get_option( 'chatl_ustawienia', null ),
	'chatl_klucz_api'  => get_option( 'chatl_klucz_api', null ),
	'chatl_blad_ai'    => get_option( 'chatl_blad_ai', null ),
);
register_shutdown_function(
	function () {
		foreach ( $GLOBALS['ctk_mig'] as $o => $w ) {
			if ( null === $w ) {
				delete_option( $o );
			} else {
				update_option( $o, $w );
			}
		}
		wp_cache_flush();
	}
);

/*
 * Atrapa API: zapisuje, o jaki MODEL pytano, i odpowiada tak, jak każe
 * $GLOBALS['ctk_kod'] — nic nie wychodzi z serwera.
 */
$GLOBALS['ctk_model'] = '';
$GLOBALS['ctk_kod']   = 200;
add_filter(
	'pre_http_request',
	function ( $wynik, $args, $url ) {
		if ( false === strpos( (string) $url, 'api.anthropic.com' ) ) {
			return $wynik;
		}
		$cialo                = json_decode( (string) ( $args['body'] ?? '' ), true );
		$GLOBALS['ctk_model'] = (string) ( $cialo['model'] ?? '' );
		return array(
			'headers'  => array(),
			'body'     => 200 === $GLOBALS['ctk_kod'] ? '{"content":[{"type":"text","text":"ok"}]}' : '{"error":"proba"}',
			'response' => array( 'code' => $GLOBALS['ctk_kod'], 'message' => '' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

if ( chatl_klucz_ze_stalej() ) {
	echo "  (pominięto: klucz w stałej CHATL_KLUCZ_API — panel go nie zapisuje)\n";
	exit( 0 );
}
update_option( 'chatl_klucz_api', 'ctk-klucz-probny' );

echo "=== A. P2-Z1: przycisk pyta modelem WŁAŚCICIELA, nie stałą produktu ===\n";
$u              = chatl_ustawienia();
$u['ai_model']  = 'ctk-model-wlasciciela';
update_option( 'chatl_ustawienia', $u );
wp_cache_flush();
$GLOBALS['ctk_model'] = '';
$wynik                = chatl_ai_test_klucza();
ctk( 'ctk-model-wlasciciela' === $GLOBALS['ctk_model'], 'zapytano modelem z ustawień, nie CHATL_AI_MODEL', 'pytano: ' . $GLOBALS['ctk_model'] );
ctk( chatl_ai_model() === $GLOBALS['ctk_model'], 'to DOKŁADNIE ten model, którym pyta bot (chatl_ai_model())', chatl_ai_model() . ' vs ' . $GLOBALS['ctk_model'] );
ctk( false !== strpos( $wynik['komunikat'], 'ctk-model-wlasciciela' ), 'komunikat mówi, jaki model sprawdzono', $wynik['komunikat'] );

echo "\n=== B. Udany test gasi alarm — bo pytał TĄ SAMĄ konfiguracją co bot ===\n";
/*
 * Kryterium z audyt/LINIA-BAZOWA-TESTOW.md i z istniejącego strażnika
 * testy/blad-klucza-panel.js krok [4]: pasek ma znikać po teście TYM SAMYM
 * modelem co bot. Sedno naprawy P2-Z1 jest więc w sekcji A (czym pytamy),
 * a nie w gaszeniu paska: gaszenie było groźne dopóty, dopóki test mógł
 * przejść na modelu, którego bot nie używa.
 */
update_option( 'chatl_blad_ai', array( 'kiedy' => current_time( 'mysql' ), 'kod' => 401, 'tresc' => 'proba C5' ), false );
wp_cache_flush();
$GLOBALS['ctk_kod']   = 200;
$GLOBALS['ctk_model'] = '';
$wynik                = chatl_ai_test_klucza();
ctk( true === $wynik['ok'], 'przycisk mówi: klucz działa', $wynik['komunikat'] );
ctk( chatl_ai_model() === $GLOBALS['ctk_model'], 'i zrobił to modelem bota (warunek gaszenia paska)', $GLOBALS['ctk_model'] );
ctk( false === get_option( 'chatl_blad_ai' ), 'dlatego czerwony pasek gaśnie' );
ob_start();
chatl_notice_bledu_ai();
$pasek = (string) ob_get_clean();
ctk( '' === trim( $pasek ), 'i panel go już nie rysuje' );

echo "\n=== B2. Nieudany test NIE gasi alarmu (i zapala własny) ===\n";
update_option( 'chatl_blad_ai', array( 'kiedy' => current_time( 'mysql' ), 'kod' => 401, 'tresc' => 'proba C5' ), false );
wp_cache_flush();
$GLOBALS['ctk_kod'] = 500;
$wynik              = chatl_ai_test_klucza();
$alarm              = get_option( 'chatl_blad_ai' );
ctk( false === $wynik['ok'], 'przycisk melduje porażkę', $wynik['komunikat'] );
ctk( is_array( $alarm ) && 500 === (int) $alarm['kod'], 'alarm zostaje i pokazuje ŚWIEŻY kod błędu', wp_json_encode( $alarm ) );

echo "\n=== C. PRZYPADEK BEZ WADY: alarm gasi udana odpowiedź wydana GOŚCIOWI ===\n";
$GLOBALS['ctk_kod'] = 200; // sekcja B2 zostawiła atrapę w trybie awarii
$sesja              = 'ctkSesjaProbna0000000000000000A';
$wpdb->insert( $wpdb->prefix . 'chatl_sesje', array( 'sesja' => $sesja, 'stan' => 'bot', 'klucz_hash' => wp_hash( 'ctk' ), 'utworzono' => current_time( 'mysql' ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore
$odp = chatl_ai_odpowiedz( 'pytanie próbne C5', $sesja );
ctk( is_string( $odp ) && '' !== $odp, 'bot dostał odpowiedź z API (atrapa)', (string) $odp );
ctk( false === get_option( 'chatl_blad_ai' ), 'i TO zgasiło alarm' );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja = %s", $sesja ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $sesja ) ); // phpcs:ignore

echo "\n=== D. PRZYPADEK BEZ WADY: domyślny model i poprawny klucz → dziala ===\n";
$GLOBALS['ctk_kod'] = 200;
$u                  = chatl_ustawienia();
$u['ai_model'] = '';
update_option( 'chatl_ustawienia', $u );
wp_cache_flush();
$GLOBALS['ctk_model'] = '';
$wynik                = chatl_ai_test_klucza();
ctk( true === $wynik['ok'] && CHATL_AI_MODEL === $GLOBALS['ctk_model'], 'bez własnego modelu pytamy domyślnym produktu', $GLOBALS['ctk_model'] );

echo "\n=== E. Odrzucony klucz: przycisk mówi prawdę i ZAPALA alarm ===\n";
delete_option( 'chatl_blad_ai' );
$GLOBALS['ctk_kod'] = 401;
$wynik              = chatl_ai_test_klucza();
ctk( false === $wynik['ok'] && 401 === (int) $wynik['kod'], 'przycisk melduje odrzucenie klucza', $wynik['komunikat'] );
ctk( is_array( get_option( 'chatl_blad_ai' ) ), 'i alarm zostaje zapalony (właściciel widzi pasek)' );

echo "\n" . ( $GLOBALS['ctk_bledy'] > 0 ? 'BLEDOW: ' . $GLOBALS['ctk_bledy'] : 'WSZYSTKO OK' ) . "\n";
exit( $GLOBALS['ctk_bledy'] > 0 ? 1 : 0 );
