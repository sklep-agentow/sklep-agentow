<?php
/**
 * STRAŻNIK REGRESJI — eksport CSV zamówień NIE WYNOSI FORMUŁY do arkusza właściciela.
 *
 * WADA (CSV injection / formula injection): `sklepag_obsluz_eksport_csv()` wypychało pola
 * tekstowe zamówienia do `fputcsv()` tak, jak leżą w bazie. Kupujący podaje adres
 * `=1+1@example.com` (`sanitize_email()` i `is_email()` PRZEPUSZCZAJĄ taki adres), właściciel
 * klika „Eksport CSV" i otwiera plik w Excelu albo LibreOffice — formuła wykonuje się NA JEGO
 * komputerze. Drugą drogą jest nazwa produktu, którą ustawia sam właściciel i której żaden
 * zewnętrzny system nie widzi.
 *
 * ⛔ MIERZY PRAWDZIWĄ FUNKCJĘ EKSPORTU, nie kopię jej logiki. `sklepag_obsluz_eksport_csv()`
 * kończy się `exit`, więc wyjście przechwytujemy buforem, a asercje robimy w funkcji
 * zamknięcia — inaczej trzeba by przepisać budowanie wiersza u siebie i strażnik pilnowałby
 * własnej kopii zamiast produktu (ta sama pułapka, co druga kopia klucza idempotencji).
 *
 * PIĘĆ KIERUNKÓW, bo „naprawa" polegająca na wycięciu znaków albo na apostrofie przed KAŻDĄ
 * komórką też usunęłaby objaw — i zepsuła plik:
 *   A) adres od kupującego zaczynający się od `=`   => zneutralizowany
 *   B) nazwa produktu od właściciela z `=HYPERLINK` => zneutralizowana (drugie źródło, ta sama klasa)
 *   C) zwykły wiersz bez znaków formuły             => BEZ ZMIAN, co do bajtu
 *   D) kolumna „Kwota"                              => nadal LICZBA, bez apostrofu
 *   E) odwracalność                                 => zdjęcie jednego apostrofu daje oryginał
 *
 * Uruchomienie:
 *   docker cp testy/csv-bez-formuly-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * EXIT 0 = wszystkie pięć kierunków zgodne · 1 = REGRESJA · 2 = środowisko/przyrząd
 */

global $wpdb;
$tz = $wpdb->prefix . 'sklepag_zamowienia';

// ⛔ Wtyczka wciąga pliki panelu za `is_admin()` (sklep-agentow.php:114), a `wp eval-file`
// chodzi w CLI — bez tego doładowania strażnik meldowałby „brak funkcji" zamiast mierzyć.
// Doładowujemy PLIK PRODUKTU, nie kopię jego logiki.
if ( ! function_exists( 'sklepag_obsluz_eksport_csv' ) && defined( 'SKLEPAG_KATALOG' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin-dashboard.php';
}
if ( ! function_exists( 'sklepag_obsluz_eksport_csv' ) ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak sklepag_obsluz_eksport_csv — wtyczka nieaktywna?)\n";
	exit( 2 );
}

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $admin ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak konta administratora)\n";
	exit( 2 );
}
wp_set_current_user( (int) $admin[0] );

$znacznik   = 'STRAZNIK-CSV-' . wp_generate_password( 8, false, false );
$zly_email  = '=1+1@example.com';
$zla_nazwa  = '=HYPERLINK("http://zly.example","klik")';
$ok_email   = 'zwykly-' . strtolower( $znacznik ) . '@example.com';
$ok_nazwa   = 'Bot ChatBot LIVE ' . $znacznik;

// ⛔ Wiersze wkładamy PROSTO DO TABELI. Przejście przez checkout wymagałoby Stripe, a wada
// siedzi na drodze baza → CSV, nie na drodze zakupu. Adres w bazie jest DOKŁADNIE tym, co
// przepuszcza sanitize_email() — sprawdzamy to niżej, żeby nie badać wartości niemożliwej.
if ( ! is_email( $zly_email ) || sanitize_email( $zly_email ) !== $zly_email ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (WordPress nie przepuszcza '{$zly_email}' — wektor byłby wymyślony)\n";
	exit( 2 );
}

$wspolne = array(
	'utworzono'      => current_time( 'mysql' ),
	'kwota_grosze'   => 14900,
	'waluta'         => 'PLN',
	'status'         => 'oplacone',
	'pobrania_uzyte' => 0,
	'pobrania_limit' => 10,
);
$wstawione = array();
$nr        = 0;
foreach ( array(
	array( 'email_kupujacego' => $zly_email, 'produkt_nazwa_kupiona' => $ok_nazwa ),
	array( 'email_kupujacego' => $ok_email,  'produkt_nazwa_kupiona' => $zla_nazwa ),
	array( 'email_kupujacego' => $ok_email,  'produkt_nazwa_kupiona' => $ok_nazwa ),
) as $wiersz ) {
	// `stripe_session_id` ma indeks UNIQUE — pusty w trzech wierszach zderzyłby się ze sobą.
	++$nr;
	$wiersz['stripe_session_id'] = 'cs_test_' . $znacznik . '_' . $nr;
	if ( false === $wpdb->insert( $tz, array_merge( $wspolne, $wiersz ), array( '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ) ) ) {
		echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (nie umiem wstawić zamówienia próbnego)\n";
		exit( 2 );
	}
	$wstawione[] = (int) $wpdb->insert_id;
}

$posprzataj = static function () use ( $wpdb, $tz, $wstawione ) {
	foreach ( $wstawione as $id ) {
		$wpdb->delete( $tz, array( 'id' => $id ), array( '%d' ) );
	}
	// Handler dokłada wpis do dziennika przy KAŻDYM eksporcie — kasujemy wyłącznie swój.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE typ = 'eksport_csv' AND id > %d",
			(int) get_option( 'sklepag_straznik_csv_id_przed', 0 )
		)
	);
	delete_option( 'sklepag_straznik_csv_id_przed' );
};
update_option(
	'sklepag_straznik_csv_id_przed',
	(int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$wpdb->prefix}sklepag_zdarzenia" ),
	false
);

/** Rozbiór CSV dokładnie tak, jak zrobi to arkusz: separator `;`, cudzysłów `"`. */
$rozbierz = static function ( $csv ) {
	$csv   = preg_replace( '/^\xEF\xBB\xBF/', '', $csv );
	$plik  = fopen( 'php://memory', 'r+' );
	fwrite( $plik, $csv );
	rewind( $plik );
	$wiersze = array();
	while ( false !== ( $w = fgetcsv( $plik, 0, ';', '"', '\\' ) ) ) {
		$wiersze[] = $w;
	}
	fclose( $plik );
	return $wiersze;
};

$bledy = 0;
$zglos = static function ( $co ) use ( &$bledy ) {
	echo "  NIE WYSZLO  {$co}\n";
	++$bledy;
};
$potwierdz = static function ( $co ) {
	echo "  WYSZLO      {$co}\n";
};

// ⛔ ASERCJE W FUNKCJI ZAMKNIĘCIA. Handler kończy się `exit`, którego nie da się przechwycić —
// PHP uruchamia po nim funkcje zamknięcia i to w nich jest jedyne miejsce, gdzie mamy
// JEDNOCZEŚNIE wyjście handlera i możliwość ustawienia kodu wyjścia.
register_shutdown_function(
	static function () use ( $rozbierz, $zglos, $potwierdz, $posprzataj, &$bledy, $zly_email, $zla_nazwa, $ok_email, $ok_nazwa ) {
		// ⛔ CZYTAMY Z PRZECHWYTYWACZA, NIE Z `ob_get_clean()`. WP-CLI opróżnia bufor ZANIM
		// uruchomi funkcje zamknięcia, więc `ob_get_clean()` zastawał tu pustkę, a CSV szedł
		// na stdout — strażnik meldował „eksport nie wypisał nic", chociaż eksport wypisał
		// wszystko. Bufor z funkcją zwrotną zbiera treść w chwili opróżnienia i zwraca '',
		// więc działa niezależnie od tego, kto i kiedy bufor domyka.
		$csv = isset( $GLOBALS['straznik_csv_zebrane'] ) ? (string) $GLOBALS['straznik_csv_zebrane'] : '';
		$posprzataj();

		if ( '' === trim( (string) $csv ) ) {
			echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (eksport nie wypisał nic)\n";
			exit( 2 );
		}
		$wiersze = $rozbierz( $csv );
		$naglowek = array_shift( $wiersze );
		if ( ! $naglowek || count( $naglowek ) < 9 ) {
			echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (CSV bez spodziewanego nagłówka)\n";
			exit( 2 );
		}

		$szukaj = static function ( $wiersze, $kol, $wartosc ) {
			foreach ( $wiersze as $w ) {
				if ( isset( $w[ $kol ] ) && ( $w[ $kol ] === $wartosc || $w[ $kol ] === "'" . $wartosc ) ) {
					return $w;
				}
			}
			return null;
		};
		$formula = static function ( $tekst ) {
			return '' !== (string) $tekst && in_array( $tekst[0], array( '=', '+', '-', '@', "\t", "\r" ), true );
		};

		// A) adres od kupującego
		$a = $szukaj( $wiersze, 2, $zly_email );
		if ( null === $a ) {
			$zglos( "A: nie znalazłem w CSV wiersza z adresem {$zly_email} — strażnik nic nie zmierzył" );
		} elseif ( $formula( $a[2] ) ) {
			$zglos( "A: adres kupującego wychodzi do arkusza JAKO FORMUŁA: [{$a[2]}]" );
		} else {
			$potwierdz( "A: adres kupującego zneutralizowany: [{$a[2]}]" );
		}

		// B) nazwa produktu od właściciela
		$b = $szukaj( $wiersze, 3, $zla_nazwa );
		if ( null === $b ) {
			$zglos( 'B: nie znalazłem w CSV wiersza z nazwą produktu z formułą — strażnik nic nie zmierzył' );
		} elseif ( $formula( $b[3] ) ) {
			$zglos( "B: nazwa produktu wychodzi do arkusza JAKO FORMUŁA: [{$b[3]}]" );
		} else {
			$potwierdz( "B: nazwa produktu zneutralizowana: [{$b[3]}]" );
		}

		// C) zwykły wiersz — BEZ ZMIAN
		$c = $szukaj( $wiersze, 3, $ok_nazwa );
		if ( null === $c ) {
			$zglos( 'C: nie znalazłem zwykłego wiersza — nie wiem, czy naprawa go nie tknęła' );
		} elseif ( $c[2] !== $ok_email || $c[3] !== $ok_nazwa ) {
			$zglos( "C: zwykły wiersz ZMIENIONY — adres [{$c[2]}] zamiast [{$ok_email}], nazwa [{$c[3]}] zamiast [{$ok_nazwa}]" );
		} else {
			$potwierdz( 'C: zwykły wiersz nietknięty, co do bajtu' );
		}

		// D) kolumna Kwota nadal LICZBĄ
		$d = null !== $a ? $a : $c;
		if ( null === $d ) {
			$zglos( 'D: brak wiersza do sprawdzenia kolumny Kwota' );
		} elseif ( ! preg_match( '/^-?\d+(?:[.,]\d+)?$/', (string) $d[4] ) ) {
			$zglos( "D: kolumna Kwota przestała być liczbą: [{$d[4]}] — arkusz nie zsumuje sprzedaży" );
		} else {
			$potwierdz( "D: kolumna Kwota nadal liczbą: [{$d[4]}]" );
		}

		// E) odwracalność — odczyt maszynowy odzyskuje oryginał
		if ( null === $a ) {
			$zglos( 'E: brak wiersza do sprawdzenia odwracalności' );
		} elseif ( preg_replace( '/^\'/', '', (string) $a[2] ) !== $zly_email ) {
			$zglos( 'E: po zdjęciu jednego apostrofu NIE wychodzi oryginalny adres — neutralizacja gubi dane' );
		} else {
			$potwierdz( 'E: zdjęcie jednego apostrofu oddaje oryginał — neutralizacja odwracalna' );
		}

		if ( 0 === $bledy ) {
			echo "STRAZNIK: WYNIK=OK - A, B, C, D i E zgodne z oczekiwaniem\n";
			exit( 0 );
		}
		echo "STRAZNIK: WYNIK=FAIL - {$bledy} z 5 kierunkow nie wyszlo (REGRESJA)\n";
		exit( 1 );
	}
);

$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_eksport_csv' );
$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
$GLOBALS['straznik_csv_zebrane'] = '';
ob_start(
	static function ( $bufor ) {
		$GLOBALS['straznik_csv_zebrane'] .= $bufor;
		return '';
	}
);
sklepag_obsluz_eksport_csv();
