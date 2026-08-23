<?php
/**
 * STRAŻNIK A3-3: czy DEKLARACJA PRYWATNOŚCI Segregatora wylicza wszystko, co
 * NAPRAWDĘ opuszcza WordPressa w trybie AI — i czy mówi prawdę o skrzynce.
 *
 * Kierunek: od KODU do DOKUMENTU (wzór: testy/a6-deklaracja-vs-ladunek-ai.php
 * dla ChatBota). Bierzemy PRAWDZIWY ładunek wychodzący, przechwycony filtrem
 * `pre_http_request` — nic nie leci do API, nie zużywa klucza ani limitu —
 * i dla każdego jego składnika pytamy, czy deklaracja go wymienia.
 *
 * Co mierzy:
 *   A. (kontrola przyrządu) w ładunku SĄ składniki, które deklaracja wymienia
 *      (nadawca, temat, treść) — inaczej pomiar jest bez wartości;
 *   B. (A3-3) każdy DALSZY składnik ładunku jest wymieniony w deklaracji:
 *      nazwy kategorii właściciela, identyfikator wiersza próbki, nazwa modelu,
 *      oraz fakt, że przy błędzie żądanie jest PONAWIANE (te same dane lecą
 *      drugi raz);
 *   C. deklaracja nie obiecuje w drugą stronę — nie wymienia składników,
 *      których kod NIE wysyła (załączniki, adres IP, nagłówki maila);
 *   D. (fakt zweryfikowany 23.08) przebieg na PRAWDZIWEJ SKRZYNCE nie wysyła
 *      do dostawcy AI ani jednego żądania, a deklaracja mówi to wprost;
 *   E. „Przetestuj klucz" wysyła wyłącznie słowo „ping" — żadnej cudzej treści.
 *
 * Dokument da się podmienić zmienną `SEGM_DEKLARACJA` (do kalibracji na
 * poprawionym pliku). Test oddaje stosowi klucz API, zgodę na AI i ślad błędu.
 *
 * Uruchomienie:
 *   docker cp testy/segregator-deklaracja-vs-ladunek-ai.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
 * EXIT 0 = deklaracja pokrywa cały ładunek, 1 = czegoś nie wymienia.
 */
global $wpdb;
if ( ! function_exists( 'segm_ai_klasyfikuj_probke' ) ) {
	echo "POMINIETO: wtyczka segregator-maila nie jest zaladowana.\n";
	exit( 0 );
}
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/silnik.php';
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/ai.php';
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/skrzynka.php';
require_once WP_PLUGIN_DIR . '/segregator-maila/includes/skrzynka-przebieg.php';

$GLOBALS['segmd_bledy'] = 0;
function segmd( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? '  [' . mb_substr( (string) $szczegol, 0, 200 ) . ']' : '' );
	if ( ! $ok ) {
		++$GLOBALS['segmd_bledy'];
	}
	return $ok;
}

$plik = getenv( 'SEGM_DEKLARACJA' ) ? getenv( 'SEGM_DEKLARACJA' ) : WP_PLUGIN_DIR . '/segregator-maila/INSTRUKCJA.md';
if ( ! is_readable( $plik ) ) {
	echo "BLAD srodowisko: brak $plik\n";
	exit( 1 );
}
$tekst = (string) file_get_contents( $plik );
$poz   = mb_strpos( $tekst, 'Deklaracja prywatności' );
if ( false === $poz ) {
	echo "BLAD srodowisko: brak sekcji 'Deklaracja prywatności' w $plik\n";
	exit( 1 );
}
$dekl = mb_substr( $tekst, $poz, 4000 );
printf( "=== A3-3: deklaracja prywatnosci vs PRAWDZIWY ladunek AI ===\n  dokument: %s\n\n", $plik );

/* ---------------------------------------------- stan sprzed proby */
$GLOBALS['segmd_stan'] = array(
	'klucz' => get_option( 'segm_klucz_api', null ),
	'zgoda' => get_option( 'segm_zgoda_ai', null ),
	'blad'  => get_option( 'segm_blad_ai', null ),
);
$GLOBALS['segmd_sprzatanie'] = array();
register_shutdown_function(
	function () {
		global $wpdb;
		remove_all_filters( 'pre_http_request' );
		foreach ( $GLOBALS['segmd_sprzatanie'] as $co ) {
			$wpdb->delete( $wpdb->prefix . $co[0], array( 'id' => (int) $co[1] ) );
		}
		delete_option( 'segm_klucz_api' );
		if ( null !== $GLOBALS['segmd_stan']['klucz'] ) {
			add_option( 'segm_klucz_api', $GLOBALS['segmd_stan']['klucz'], '', false );
		}
		if ( null === $GLOBALS['segmd_stan']['zgoda'] ) {
			delete_option( 'segm_zgoda_ai' );
		} else {
			update_option( 'segm_zgoda_ai', $GLOBALS['segmd_stan']['zgoda'] );
		}
		if ( null === $GLOBALS['segmd_stan']['blad'] ) {
			delete_option( 'segm_blad_ai' );
		} else {
			update_option( 'segm_blad_ai', $GLOBALS['segmd_stan']['blad'], false );
		}
	}
);

/* ------------------------------------------- prawdziwy ladunek AI */
delete_option( 'segm_klucz_api' );
add_option( 'segm_klucz_api', 'segmd-udawany-klucz', '', false );
update_option(
	'segm_zgoda_ai',
	array(
		'kiedy' => current_time( 'mysql' ),
		'kto'   => 'segmd-test',
	)
);
$GLOBALS['segmd_zadania'] = array();
add_filter(
	'pre_http_request',
	function ( $k, $args, $url ) {
		$GLOBALS['segmd_zadania'][] = array(
			'url'  => (string) $url,
			'body' => isset( $args['body'] ) ? (string) $args['body'] : '',
		);
		return new WP_Error( 'segmd', 'przechwycone przez straznika' );
	},
	10,
	3
);

$kategoria = 'SEGMD-Kategoria';
$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => $kategoria, 'utworzono' => current_time( 'mysql' ) ) );
$GLOBALS['segmd_sprzatanie'][] = array( 'segm_kategorie', (int) $wpdb->insert_id );
$nadawca = 'anna.kowalska@segmd.test';
$temat   = 'Faktura 7/2026 do zaplaty';
$tresc   = 'Dzien dobry, w zalaczniku faktura. Numer sprawy 90010112345, telefon 600-700-800.';
$wpdb->insert(
	$wpdb->prefix . 'segm_maile',
	array( 'nadawca' => $nadawca, 'temat' => $temat, 'tresc' => $tresc, 'kategoria_oczekiwana' => '', 'utworzono' => current_time( 'mysql' ) )
);
$id_maila                      = (int) $wpdb->insert_id;
$GLOBALS['segmd_sprzatanie'][] = array( 'segm_maile', $id_maila );

$maile = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_maile WHERE id = %d", $id_maila ) ); // phpcs:ignore
segm_ai_klasyfikuj_probke( $maile );
$zadania = $GLOBALS['segmd_zadania'];
$GLOBALS['segmd_zadania'] = array();

if ( ! $zadania ) {
	echo "  ANOMALIA: nie przechwycono zadnego zadania — tryb AI nie doszedl do wywolania. NIE DA SIE ORZEC.\n";
	exit( 1 );
}
$cialo = json_decode( $zadania[0]['body'], true );
$msg   = '';
foreach ( (array) ( isset( $cialo['messages'] ) ? $cialo['messages'] : array() ) as $m ) {
	$msg .= (string) $m['content'];
}
$system = isset( $cialo['system'] ) ? (string) $cialo['system'] : '';
printf( "  zadan do api.anthropic.com: %d | klucze ciala: %s\n", count( $zadania ), implode( ', ', array_keys( (array) $cialo ) ) );
printf( "  tresc uzytkownika (200): %s\n\n", mb_substr( $msg, 0, 200 ) );

echo "=== A. KONTROLA PRZYRZADU: w ladunku sa skladniki, ktore deklaracja wymienia ===\n";
segmd( false !== mb_strpos( $msg, $nadawca ), 'nadawca JEST w ladunku' );
segmd( false !== mb_strpos( $msg, 'Faktura 7' ), 'temat JEST w ladunku' );
segmd( false !== mb_strpos( $msg, '600-700-800' ), 'tresc maila JEST w ladunku' );
segmd(
	(bool) preg_match( '/\*\*nadawca\*\*/u', $dekl ) && (bool) preg_match( '/\*\*temat\*\*/u', $dekl ) && (bool) preg_match( '/\*\*treść\*\* maila/u', $dekl ),
	'deklaracja wymienia nadawce, temat i tresc'
);

echo "\n=== B. A3-3: dalsze skladniki ladunku tez musza byc w deklaracji ===\n";
$kategorie_w_ladunku = false !== mb_strpos( $system, $kategoria );
segmd(
	! $kategorie_w_ladunku || (bool) preg_match( '/nazw\w* (Twoich )?kategorii|lista kategorii|nazwy kategorii/iu', $dekl ),
	'deklaracja wymienia NAZWY KATEGORII (ida w bloku systemowym)',
	$kategorie_w_ladunku ? 'w ladunku jest: ' . $kategoria : 'kategorii nie ma w ladunku'
);
$id_w_ladunku = false !== mb_strpos( $msg, '"id"' );
segmd(
	! $id_w_ladunku || (bool) preg_match( '/identyfikator\w* wiersza|numer\w* (wiersza|maila) w próbce|id wiersza/iu', $dekl ),
	'deklaracja wymienia IDENTYFIKATOR wiersza probki',
	$id_w_ladunku ? 'w ladunku jest klucz "id"' : 'brak'
);
$model_w_ladunku = ! empty( $cialo['model'] );
segmd(
	! $model_w_ladunku || (bool) preg_match( '/nazw\w* modelu/iu', $dekl ),
	'deklaracja wymienia NAZWE MODELU (idzie w zadaniu)',
	$model_w_ladunku ? (string) $cialo['model'] : 'brak'
);
segmd(
	count( $zadania ) < 2 || (bool) preg_match( '/ponawia|ponowien|drugi raz|retry|jedna próba powtórzen/iu', $dekl ),
	'deklaracja mowi, ze przy bledzie zadanie jest PONAWIANE (te same dane leca drugi raz)',
	count( $zadania ) . ' zadania w jednym przebiegu'
);
segmd(
	(bool) preg_match( '/(nie )?wysyłamy załącznik|załączniki|bez załącznik/iu', $dekl ),
	'deklaracja mowi WPROST, co NIE wychodzi (np. zalaczniki)'
);

echo "\n=== C. BEZ WADY: deklaracja nie obiecuje w druga strone ===\n";
$obiecuje_zalaczniki = (bool) preg_match( '/wysyłan\w+ (są )?załącznik|załączniki (są )?wysyłane/iu', $dekl );
segmd( ! $obiecuje_zalaczniki, 'deklaracja NIE twierdzi, ze wysylamy zalaczniki (kod ich nie wysyla)' );
segmd( false === mb_strpos( $msg, 'kategoria_oczekiwana' ), 'kolumna „kategoria oczekiwana" NIE wychodzi do dostawcy' );

echo "\n=== D. Skrzynka IMAP: przebieg na zywo nie wysyla NICZEGO do dostawcy AI ===\n";
$GLOBALS['segmd_zadania'] = array();
$wynik_przebiegu          = segm_skrzynka_przebieg( 'reczny' );
$do_ai                    = 0;
foreach ( $GLOBALS['segmd_zadania'] as $z ) {
	if ( false !== mb_strpos( $z['url'], 'api.anthropic.com' ) ) {
		++$do_ai;
	}
}
printf( "  przebieg skrzynki: blad=\"%s\" | zadan do dostawcy AI: %d\n", mb_substr( (string) $wynik_przebiegu['blad'], 0, 90 ), $do_ai );
segmd( 0 === $do_ai, 'przebieg skrzynki NIE wysyla zadnego zadania do dostawcy AI' );
segmd(
	(bool) preg_match( '/na skrzynce.{0,80}(regu|nic nie wychodzi)|skrzynk\w+.{0,120}nie (są )?wysyłan|nie opuszcza\w* .{0,40}(dostawcy|modelu)/iu', $dekl ),
	'deklaracja mowi WPROST, ze na skrzynce AI nie pracuje i nic tam nie wychodzi'
);

echo "\n=== E. Przetestuj klucz wysyla wylacznie slowo ping ===\n";
$GLOBALS['segmd_zadania'] = array();
segm_ai_przetestuj_klucz();
$ping_ok = false;
foreach ( $GLOBALS['segmd_zadania'] as $z ) {
	$c = json_decode( $z['body'], true );
	$t = '';
	foreach ( (array) ( isset( $c['messages'] ) ? $c['messages'] : array() ) as $m ) {
		$t .= (string) $m['content'];
	}
	$ping_ok = 'ping' === trim( $t );
	printf( '  tresc testu klucza: [%s]' . "\n", mb_substr( trim( $t ), 0, 60 ) );
}
segmd( $ping_ok, 'test klucza wysyla samo „ping", zadnej cudzej tresci' );
segmd( (bool) preg_match( '/„?ping”?/u', $dekl ), 'deklaracja mowi, ze test klucza wysyla wylacznie „ping"' );

printf( "\nBLEDOW: %d\n", $GLOBALS['segmd_bledy'] );
exit( $GLOBALS['segmd_bledy'] > 0 ? 1 : 0 );
