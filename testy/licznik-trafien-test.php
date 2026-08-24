<?php
/**
 * Licznik trafień przy regule — test-strażnik, kod wyjścia 1 przy porażce.
 *
 * Co groziło: lista reguł liczyła trafienia dla KAŻDEGO wiersza, a każde
 * liczenie czytało całą próbkę i całą listę reguł osobnym zapytaniem. Przy
 * dwudziestu regułach dawało to czterdzieści zapytań na jedno wyświetlenie
 * strony — z których dwa wystarczą, bo dane są dla wszystkich wierszy
 * identyczne. Rosło to liniowo z liczbą reguł, więc właściciel, który
 * najbardziej potrzebuje licznika (dużo reguł), obrywał najmocniej.
 *
 * Strażnik pilnuje DWÓCH rzeczy naraz, bo sama liczba zapytań to za mało:
 *  - liczba zapytań nie rośnie z liczbą reguł,
 *  - liczby w kolumnach „Pasuje"/„Zgarnia" są DOKŁADNIE te same, co przy
 *    liczeniu po staremu (bez kontekstu). Szybciej i źle to nie jest naprawa.
 *
 * Uruchomienie:
 *   docker cp testy/licznik-trafien-test.php strona_wp_wordpress:/tmp/lt.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/lt.php
 */

$GLOBALS['segm_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['segm_test_bledy'];
	}
}
function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

global $wpdb;

// Pliki panelu ładują się tylko dla `is_admin()`, a wp-cli adminem nie jest —
// dociągamy ten jeden plik wprost, żeby test mógł wołać liczenie trafień.
if ( ! function_exists( 'segm_kontekst_trafien' ) ) {
	require_once WP_PLUGIN_DIR . '/segregator-maila/includes/admin-probka.php';
}

if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
	// $wpdb->num_queries rośnie zawsze, niezależnie od SAVEQUERIES — mierzymy
	// właśnie ten licznik, więc test nie wymaga trybu debugowania.
	echo "(SAVEQUERIES wyłączone — liczymy przez \$wpdb->num_queries, to wystarcza)\n";
}

/**
 * Ile zapytań do bazy kosztuje wywołanie funkcji.
 *
 * @param callable $co Kod do zmierzenia.
 * @return array{zapytan: int, wynik: mixed}
 */
function segm_zmierz_zapytania( $co ) {
	global $wpdb;
	$przed  = (int) $wpdb->num_queries;
	$wynik  = $co();
	$po     = (int) $wpdb->num_queries;
	return array(
		'zapytan' => $po - $przed,
		'wynik'   => $wynik,
	);
}

/* ===================== PRZYGOTOWANIE ===================== */

// Próbka demo (30 maili) + reguły demo (8) — ten sam materiał, który widzi
// właściciel po aktywacji, więc liczby w teście są liczbami z realnego panelu.
segm_seed_demo();

/*
 * Wspólny stos testowy DRYFUJE: import konfiguracji (segm_konfig_wgraj) czy
 * ręczne porządki potrafią zostawić bazę z kategoriami, ale BEZ reguł — a seed
 * CELOWO nie odtwarza reguł, gdy kategorie istnieją (reaktywacja nie może
 * wskrzeszać demo w skonfigurowanej instalacji). Ten test potrzebuje materiału
 * demo, więc brakujące reguły demo dosiewa SAM — dokładnie te, które seed
 * wstawiłby na czystej bazie, z kategoriami mapowanymi po nazwie.
 * (Linia bazowa 24.08: „reguł: 0, maili: 30" — stąd ta dosiewka.)
 */
if ( count( segm_pobierz_reguly() ) < 5 ) {
	$lt_kat = array();
	foreach ( $wpdb->get_results( "SELECT id, nazwa FROM {$wpdb->prefix}segm_kategorie" ) as $lt_k ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lt_kat[ $lt_k->nazwa ] = (int) $lt_k->id;
	}
	$lt_demo = array(
		array( 'od', 'faktury@, ksiegowosc, księgowość, rozliczenia@', 'Faktury' ),
		array( 'temat', 'faktur, rachunek, płatnoś, platnosc, nota księgowa, nota ksiegowa', 'Faktury' ),
		array( 'temat', 'reklamac, zwrot, uszkodzon, wadliw', 'Reklamacje' ),
		array( 'tresc', 'reklamuj, zepsu, nie działa, nie dziala, żądam zwrotu, zadam zwrotu', 'Reklamacje' ),
		array( 'temat', 'ofert, wycen, zapytanie', 'Oferty' ),
		array( 'tresc', 'proszę o wycenę, prosze o wycene, zainteresowan, ile kosztuje', 'Oferty' ),
		array( 'od', 'no-reply, noreply, newsletter, mailing@', 'Spam-podejrzane' ),
		array( 'temat', 'wygrałeś, wygrales, promocja, rabat, ostatnia szansa, gratis', 'Spam-podejrzane' ),
	);
	$lt_kol  = 1 + (int) $wpdb->get_var( "SELECT COALESCE(MAX(kolejnosc),0) FROM {$wpdb->prefix}segm_reguly" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $lt_demo as $lt_r ) {
		if ( ! isset( $lt_kat[ $lt_r[2] ] ) ) {
			continue; // Brak kategorii o tej nazwie — nie wstawiamy reguły-sieroty.
		}
		$wpdb->insert(
			$wpdb->prefix . 'segm_reguly',
			array(
				'kolejnosc'    => $lt_kol++,
				'pole'         => $lt_r[0],
				'wzorzec'      => $lt_r[1],
				'kategoria_id' => $lt_kat[ $lt_r[2] ],
				'utworzono'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
	}
}

$reguly = segm_pobierz_reguly();

// Migawka reguł ZANIM cokolwiek policzymy — jako tekst, nie jako obiekty.
// Porównywanie obiektów z obiektami z kontekstu nic by nie dało: to te same
// obiekty, więc mutacja jednej strony „zgadzałaby się" z drugą.
$oryginaly = array();
foreach ( $reguly as $r ) {
	$oryginaly[ (int) $r->id ] = $r->pole . '|' . $r->wzorzec;
}

$maili  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile" );
sprawdz( 'jest na czym liczyć: reguły i próbka', count( $reguly ) >= 5 && $maili >= 20, 'reguł: ' . count( $reguly ) . ', maili: ' . $maili );

echo "\n=== A. Kontekst pobiera dane RAZ ===\n";
$p = segm_zmierz_zapytania(
	function () {
		return segm_kontekst_trafien();
	}
);
rowne( 'kontekst bez reguł = 2 zapytania (próbka + reguły)', 2, $p['zapytan'] );

$p = segm_zmierz_zapytania(
	function () use ( $reguly ) {
		return segm_kontekst_trafien( $reguly );
	}
);
rowne( 'kontekst z gotowymi regułami = 1 zapytanie (sama próbka)', 1, $p['zapytan'] );

$kontekst = segm_kontekst_trafien( $reguly );
rowne( 'kontekst niesie całą próbkę', $maili, count( $kontekst['maile'] ) );
rowne( 'kontekst niesie wszystkie reguły', count( $reguly ), count( $kontekst['reguly'] ) );

echo "\n=== B. Liczenie CAŁEJ listy reguł nie dokłada ani jednego zapytania ===\n";
$p = segm_zmierz_zapytania(
	function () use ( $reguly, $kontekst ) {
		$suma = array();
		foreach ( $reguly as $r ) {
			$suma[ (int) $r->id ] = segm_policz_trafienia( $r->pole, $r->wzorzec, (int) $r->id, $kontekst );
		}
		return $suma;
	}
);
$z_kontekstem = $p['wynik'];
rowne( 'cała lista reguł z kontekstem = 0 zapytań', 0, $p['zapytan'] );
sprawdz( 'policzone dla każdej reguły', count( $z_kontekstem ) === count( $reguly ), 'policzono: ' . count( $z_kontekstem ) . ' z ' . count( $reguly ) );

echo "\n=== C. Stan sprzed naprawy: bez kontekstu każdy wiersz odpytuje bazę ===\n";
$p = segm_zmierz_zapytania(
	function () use ( $reguly ) {
		$suma = array();
		foreach ( $reguly as $r ) {
			$suma[ (int) $r->id ] = segm_policz_trafienia( $r->pole, $r->wzorzec, (int) $r->id );
		}
		return $suma;
	}
);
$bez_kontekstu = $p['wynik'];
$oczekiwane    = 2 * count( $reguly );
rowne( 'bez kontekstu koszt rośnie z liczbą reguł (2 na wiersz)', $oczekiwane, $p['zapytan'] );
sprawdz(
	'kontekst realnie oszczędza zapytania',
	$p['zapytan'] > 0,
	'stary sposób: ' . $p['zapytan'] . ' zapytań, nowy: 0 (+1 na kontekst)'
);

echo "\n=== D. Szybciej ORAZ tak samo: liczby bez zmian ===\n";
$rozjazdy = array();
foreach ( $reguly as $r ) {
	$id = (int) $r->id;
	if ( $z_kontekstem[ $id ] !== $bez_kontekstu[ $id ] ) {
		$rozjazdy[] = '#' . $id . ' „' . $r->wzorzec . '"';
	}
}
sprawdz(
	'wynik z kontekstem IDENTYCZNY jak bez kontekstu (wszystkie reguły)',
	array() === $rozjazdy,
	array() === $rozjazdy ? 'sprawdzono reguł: ' . count( $reguly ) : 'rozjazd przy: ' . implode( ', ', $rozjazdy )
);

$suma_pasuje = 0;
foreach ( $z_kontekstem as $t ) {
	$suma_pasuje += (int) $t['pasuje'];
}
sprawdz( 'licznik w ogóle coś liczy (nie same zera)', $suma_pasuje > 0, 'suma „pasuje" po wszystkich regułach: ' . $suma_pasuje );

echo "\n=== E. Wspólny kontekst nie psuje się po drodze ===\n";
// Tu siedziała pułapka: funkcja podmienia pole i wzorzec reguły próbnej.
// Gdyby robiła to na obiektach z kontekstu, podmiana zostawałaby w nim na
// stałe i każdy NASTĘPNY wiersz liczyłby już według cudzego wzorca.
$pierwsza = $reguly[0];
$id_p     = (int) $pierwsza->id;
$przed    = segm_policz_trafienia( $pierwsza->pole, $pierwsza->wzorzec, $id_p, $kontekst );

segm_policz_trafienia( $pierwsza->pole, 'zzz-wzorzec-ktory-nie-pasuje-do-niczego', $id_p, $kontekst );
segm_policz_trafienia( 'temat', 'faktura', $id_p, $kontekst );

$po = segm_policz_trafienia( $pierwsza->pole, $pierwsza->wzorzec, $id_p, $kontekst );
rowne( 'ten sam rachunek po cudzych liczeniach daje ten sam wynik', $przed, $po );

$zepsute = array();
foreach ( $kontekst['reguly'] as $r ) {
	$teraz = $r->pole . '|' . $r->wzorzec;
	if ( ! isset( $oryginaly[ (int) $r->id ] ) || $oryginaly[ (int) $r->id ] !== $teraz ) {
		$zepsute[] = '#' . (int) $r->id . ' (' . $teraz . ')';
	}
}
sprawdz(
	'reguły w kontekście NIE zostały podmienione',
	array() === $zepsute,
	array() === $zepsute ? 'nietknięte: ' . count( $reguly ) : 'podmienione: ' . implode( ', ', $zepsute )
);

echo "\n=== F. Formularz pojedynczej reguły dalej działa bez kontekstu ===\n";
$jedna = segm_policz_trafienia( $reguly[0]->pole, $reguly[0]->wzorzec, (int) $reguly[0]->id );
rowne( 'wynik bez kontekstu = wynik z kontekstem', $z_kontekstem[ (int) $reguly[0]->id ], $jedna );

$nowa = segm_policz_trafienia( 'temat', 'faktura' );
sprawdz( 'nowa reguła (id 0) liczy się bez wywrotki', is_array( $nowa ) && isset( $nowa['pasuje'], $nowa['wygrywa'], $nowa['maili'] ), 'maili: ' . (int) $nowa['maili'] . ', pasuje: ' . (int) $nowa['pasuje'] );

$pusty = segm_policz_trafienia( 'temat', '   ', 0, $kontekst );
sprawdz( 'pusty wzorzec nadal mówi o pustym wzorcu, nie o pustej próbce', true === $pusty['pusty_wzorzec'] && $pusty['maili'] === $maili, 'maili: ' . (int) $pusty['maili'] );

echo "\n";
if ( 0 === $GLOBALS['segm_test_bledy'] ) {
	echo "LICZNIK TRAFIEN OK — koszt nie rosnie z liczba regul, liczby bez zmian\n";
	exit( 0 );
}
echo 'PORAZEK: ' . $GLOBALS['segm_test_bledy'] . "\n";
exit( 1 );
