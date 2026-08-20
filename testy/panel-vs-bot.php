<?php
/**
 * Panel testowy ChatBota MUSI mówić to samo, co realny bot.
 *
 * Uwaga recenzenta z PR #63: `chatl_trafione_warianty()` w `includes/admin.php`
 * powtarzała algorytm dopasowania z `chatl_dopasuj()` w `includes/silnik.php`.
 * Dwie kopie tej samej reguły = panel może się rozjechać z botem, a wtedy panel
 * kłamie na temat własnego produktu.
 *
 * Uruchomienie (bez przeglądarki):
 *   docker cp testy/panel-vs-bot.php strona_wp_wordpress:/tmp/panel-vs-bot.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/panel-vs-bot.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 *
 * ⚠️ Testy 1-3 padają na kodzie SPRZED wydzielenia wspólnej funkcji.
 * Test 4 na samych wynikach przechodził PRZED i PO — bo obie kopie były wtedy
 * identyczne co do zachowania. Dopiero ZMIANA reguły w jednym miejscu je
 * rozjeżdża, i po to jest `testy/kalibracja-mutantem.sh`.
 */

$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
require_once $katalog . 'admin.php';

/*
 * Licznik JAWNIE w $GLOBALS. `wp eval-file` wykonuje plik w zakresie funkcji,
 * więc zmienna z góry pliku NIE jest globalna — `global $bledy` w sprawdz()
 * łapałoby wtedy pustkę i test kończyłby się EXIT=0 mimo błędów (zmierzone).
 */
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

/* ---------- 1. Reguła dopasowania ma być zdefiniowana W SILNIKU ---------- */
echo "[1] gdzie mieszka regula dopasowania\n";
$refleksja = new ReflectionFunction( 'chatl_trafione_warianty' );
$plik      = basename( $refleksja->getFileName() );
sprawdz( 'chatl_trafione_warianty() zdefiniowana w silnik.php', 'silnik.php' === $plik, 'zdefiniowana w: ' . $plik );

/* ---------- 2. Panel NIE MOŻE mieć drugiej kopii algorytmu ---------- */
echo "\n[2] czy panel ma wlasna kopie algorytmu\n";
$zrodlo_panelu = file_get_contents( $katalog . 'admin.php' );
$prymitywy     = array( 'mb_strpos', "explode( ','" );
foreach ( $prymitywy as $p ) {
	sprawdz( "admin.php NIE zawiera prymitywu dopasowania: {$p}", false === strpos( $zrodlo_panelu, $p ) );
}

/* ---------- 3. Silnik oddaje warianty, ktorymi wygral ---------- */
echo "\n[3] czy silnik oddaje panelowi warianty, ktorymi wygral\n";
/*
 * Pytanie sondujące budujemy Z DANYCH — pierwszy wariant pierwszej aktywnej
 * pary, postawiony na początku zdania. Dzięki temu trafia zarówno przy regule
 * „zawiera", jak i „zaczyna się od", więc kalibracja mutantem nie wywraca tego
 * kroku z powodu doboru pytania zamiast z powodu usterki.
 */
global $wpdb;
$pierwsza = $wpdb->get_row( "SELECT slowa_kluczowe FROM {$wpdb->prefix}chatl_qa WHERE aktywne = 1 ORDER BY id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$sonda    = null === $pierwsza ? '' : trim( (string) strtok( $pierwsza->slowa_kluczowe, ',' ) ) . ' prosze';
$probka   = chatl_dopasuj( $sonda );
sprawdz(
	'chatl_dopasuj() zwraca klucz "trafione"',
	is_array( $probka ) && array_key_exists( 'trafione', $probka ),
	'pytanie sondujace: "' . $sonda . '"'
);

/* ---------- 4. Rownowaznosc: panel i bot na tym samym pytaniu ---------- */
echo "\n[4] rownowaznosc panelu i realnego bota\n";
global $wpdb;
$pary = $wpdb->get_results( "SELECT id, pytanie, slowa_kluczowe FROM {$wpdb->prefix}chatl_qa WHERE aktywne = 1" );

$pytania = array(
	'ile kosztuje wasza usluga',
	'w jakich godzinach jestescie otwarci',
	'jak sie z wami skontaktowac',
	'gdzie was znajde',
	'czy wystawiacie fakture vat',
	'chce umowic konsultacje',
	'macie gdzies cenik uslug',
	'czy montujecie panele fotowoltaiczne na dachu stodoly',
	'CENNIK WIELKIMI LITERAMI',
	'   ',
);

printf( "%-56s | %-14s | %-14s\n", 'pytanie', 'bot (id pary)', 'panel (id pary)' );
echo str_repeat( '-', 92 ) . "\n";

foreach ( $pytania as $pytanie ) {
	// REALNY BOT: dokladnie ta sama funkcja, ktora obsluguje goscia w AJAX-ie.
	$bot    = chatl_dopasuj( $pytanie );
	$bot_id = null === $bot ? 0 : (int) $bot['id'];

	// PANEL: punktacja policzona funkcja, ktorej panel uzywa do pokazania
	// wariantow. Jesli to inna regula niz w silniku — wyjdzie inna para.
	$panel_id        = 0;
	$panel_max_traf  = 0;
	$panel_max_dl    = 0;
	$panel_warianty  = array();
	foreach ( $pary as $para ) {
		$trafione  = chatl_trafione_warianty( $pytanie, $para->slowa_kluczowe );
		$trafienia = count( $trafione );
		$dlugosc   = 0;
		foreach ( $trafione as $w ) {
			$dlugosc += mb_strlen( $w );
		}
		if ( $trafienia > $panel_max_traf || ( $trafienia === $panel_max_traf && $trafienia > 0 && $dlugosc > $panel_max_dl ) ) {
			$panel_max_traf = $trafienia;
			$panel_max_dl   = $dlugosc;
			$panel_id       = (int) $para->id;
			$panel_warianty = $trafione;
		}
	}

	printf( "%-56s | %-14s | %-14s\n", mb_substr( $pytanie, 0, 56 ), 0 === $bot_id ? 'brak' : '#' . $bot_id, 0 === $panel_id ? 'brak' : '#' . $panel_id );
	sprawdz( '  ta sama para dla: "' . trim( $pytanie ) . '"', $bot_id === $panel_id );

	// Warianty pokazywane w panelu musza byc TYMI SAMYMI, ktorymi silnik wygral.
	if ( 0 !== $bot_id && is_array( $bot ) && array_key_exists( 'trafione', $bot ) ) {
		sprawdz(
			'  te same warianty dla: "' . trim( $pytanie ) . '"',
			$bot['trafione'] === $panel_warianty,
			'silnik: [' . implode( ', ', $bot['trafione'] ) . ']  panel: [' . implode( ', ', $panel_warianty ) . ']'
		);
	}
}

$bledy = (int) $GLOBALS['chatl_test_bledy'];
echo "\n" . ( $bledy ? 'NIEPOWODZENIE: ' . $bledy . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $bledy ? 1 : 0 );
