<?php
/**
 * Segregator maila — PIĘĆ ścieżek, za które klient płaci, na PRAWDZIWEJ skrzynce.
 *
 * Do tej pory Segregator miał trzy testy i żaden nie dotykał działania:
 * dwa sprawdzały dokumentację, trzeci jeden licznik. Zero strażników na tym,
 * co kupujący dostaje za swoje pieniądze:
 *
 *   A. połączenie ze skrzynką      — i czy ODMOWA mówi, co jest nie tak
 *   B. przebieg reguł              — czy reguła w ogóle dopasowuje maila
 *   C. tryb NA SUCHO               — czy naprawdę NIC nie rusza (obietnica nr 1)
 *   D. przenoszenie maili          — czy wiadomość faktycznie zmienia folder
 *   E. cofnięcie przebiegu         — czy wraca dokładnie tam, skąd wyszła
 *
 * Test gada z serwerem IMAP `segm_imap` (GreenMail) w tej samej sieci dockera,
 * wysyła własną wiadomość przez SMTP i sprząta po sobie — także gdy któryś krok
 * nie przejdzie. Wiersze testowe mają przedrostek 'SEGMTEST-'.
 *
 * Uruchomienie:
 *   docker cp testy/segregator-skrzynka-test.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/x.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

$katalog = WP_PLUGIN_DIR . '/segregator-maila/includes/';
require_once $katalog . 'konfiguracja.php';
require_once $katalog . 'class-segm-imap.php';
require_once $katalog . 'silnik.php';
require_once $katalog . 'skrzynka.php';
require_once $katalog . 'skrzynka-przebieg.php';

global $wpdb;
$GLOBALS['segm_test_bledy'] = 0;
$TEMAT  = 'SEGMTEST-FAKTURA-' . wp_generate_password( 8, false );
$ZRODLO = 'SEGMTEST-IN-' . wp_generate_password( 6, false );

function krok( $ok, $opis, $szczegol = '' ) {
	if ( $ok ) {
		printf( "  OK   %s%s\n", $opis, '' !== $szczegol ? " ($szczegol)" : '' );
		return true;
	}
	printf( "  BLAD %s%s\n", $opis, '' !== $szczegol ? " ($szczegol)" : '' );
	++$GLOBALS['segm_test_bledy'];
	return false;
}

/** Wysyłka wiadomości wprost po SMTP — bez wp_mail, bo ta poszłaby do mailpita. */
function segmtest_wyslij( $temat ) {
	$s = @fsockopen( 'segm_imap', 3025, $e, $es, 10 );
	if ( ! $s ) {
		return false;
	}
	$czytaj = function () use ( $s ) {
		return fgets( $s, 1024 );
	};
	$pisz = function ( $l ) use ( $s, $czytaj ) {
		fwrite( $s, $l . "\r\n" );
		return $czytaj();
	};
	$czytaj();
	$pisz( 'HELO test' );
	$pisz( 'MAIL FROM:<ksiegowosc@dostawca.test>' );
	$pisz( 'RCPT TO:<szef@firma.test>' );
	$pisz( 'DATA' );
	fwrite( $s, "Subject: $temat\r\nFrom: ksiegowosc@dostawca.test\r\nTo: szef@firma.test\r\n\r\nTresc testowa segregatora.\r\n.\r\n" );
	$czytaj();
	$pisz( 'QUIT' );
	fclose( $s );
	return true;
}

/** Ile wiadomości o tym temacie leży w danym folderze. */
function segmtest_ile_w_folderze( $folder, $temat ) {
	$k = new Segm_Imap( 20 );
	if ( ! $k->polacz( 'segm_imap', 3143, 'brak' ) || ! $k->zaloguj( 'szef', 'tajne1234' ) ) {
		return -1;
	}
	if ( null === $k->otworz( $folder, true ) ) {
		$k->rozlacz();
		return 0;
	}
	$uidy = $k->uidy( 'ALL' );
	$ile  = 0;
	foreach ( (array) $uidy as $uid ) {
		$w = segm_skrzynka_wiadomosc( $k, $uid, false );
		if ( $w && false !== mb_strpos( (string) $w->temat, $temat ) ) {
			++$ile;
		}
	}
	$k->rozlacz();
	return $ile;
}

function segmtest_odstaw_do_zrodla( $temat, $zrodlo ) {
	$k = new Segm_Imap( 20 );
	if ( ! $k->polacz( 'segm_imap', 3143, 'brak' ) || ! $k->zaloguj( 'szef', 'tajne1234' ) ) {
		return false;
	}
	if ( ! $k->folder_istnieje( $zrodlo ) ) {
		$k->utworz_folder( $zrodlo );
	}
	if ( null === $k->otworz( 'INBOX', false ) ) {
		$k->rozlacz();
		return false;
	}
	$ok = false;
	foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
		$w = segm_skrzynka_wiadomosc( $k, $uid, false );
		if ( $w && false !== mb_strpos( (string) $w->temat, $temat ) ) {
			$ok = $k->przenies( $uid, $zrodlo );
		}
	}
	$k->rozlacz();
	return $ok;
}

function segmtest_sprzataj( $temat ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}segm_reguly WHERE wzorzec LIKE 'SEGMTEST-%'" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}segm_kategorie WHERE nazwa LIKE 'SEGMTEST-%'" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE 'SEGMTEST-%'" );
	delete_option( 'segm_zgoda_przenoszenie' );
	// Klient IMAP tej wtyczki CELOWO nie ma kasowania (rozdział „Granice":
	// „Nie kasuje wiadomości"), więc próba też nie kasuje — odsuwa swoje
	// wiadomości na bok, do własnego folderu. Każdy przebieg ma inny temat,
	// więc odstawione śmieci nie mieszają się do następnego pomiaru.
	foreach ( array( 'INBOX', 'Segregator/SEGMTEST-Faktury', 'Segregator.SEGMTEST-Faktury' ) as $f ) {
		$k = new Segm_Imap( 20 );
		if ( $k->polacz( 'segm_imap', 3143, 'brak' ) && $k->zaloguj( 'szef', 'tajne1234' ) ) {
			if ( null !== $k->otworz( $f, false ) ) {
				foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
					$w = segm_skrzynka_wiadomosc( $k, $uid, false );
					if ( $w && false !== mb_strpos( (string) $w->temat, 'SEGMTEST-' ) ) {
						$k->przenies( $uid, 'SEGMTEST-zuzyte' );
					}
				}
			}
			$k->rozlacz();
		}
	}
}

// Sprzatanie ZANIM zaczniemy: resztki po poprzednich przebiegach leza w folderze
// zrodlowym i przebieg policzylby je razem z nasza (widziane: "przeniesionych: 2").
register_shutdown_function( 'segmtest_sprzataj', $TEMAT );

// ---------------------------------------------------------------- przygotowanie
$ust = segm_skrzynka_ustawienia();
$ust = array_merge(
	$ust,
	array(
		'host'            => 'segm_imap',
		'port'            => 3143,
		'szyfrowanie'     => 'brak',
		'login'           => 'szef',
		'folder_zrodlowy' => $ZRODLO,
		'prefiks'         => 'Segregator',
		'limit'           => 50,
		'tryb'            => 'na_sucho',
		'ruszaj_reszte'   => 0,
	)
);
update_option( 'segm_skrzynka', $ust );
segm_skrzynka_zapisz_haslo( 'tajne1234' );

$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => 'SEGMTEST-Faktury', 'utworzono' => current_time( 'mysql' ) ) );
$kategoria = (int) $wpdb->insert_id;
$wpdb->insert(
	$wpdb->prefix . 'segm_reguly',
	array( 'kolejnosc' => 1, 'pole' => 'temat', 'wzorzec' => $TEMAT, 'kategoria_id' => $kategoria, 'utworzono' => current_time( 'mysql' ) )
);

echo "=== PRZYGOTOWANIE ===\n";
krok( segmtest_wyslij( $TEMAT ), 'wiadomosc testowa wyslana na skrzynke', $TEMAT );
sleep( 1 );
krok( segmtest_odstaw_do_zrodla( $TEMAT, $ZRODLO ), 'wiadomosc przeniesiona do wlasnego folderu zrodlowego SEGMTEST-IN' );
krok( 1 === segmtest_ile_w_folderze( $ZRODLO, $TEMAT ), 'lezy w SEGMTEST-IN przed przebiegiem' );

// ------------------------------------------------------------------- A. polaczenie
echo "\n=== A. POLACZENIE ZE SKRZYNKA ===\n";
$pol = segm_skrzynka_polacz();
krok( null !== $pol['klient'], 'polaczenie i logowanie poprawnymi danymi' );
if ( null !== $pol['klient'] ) {
	$pol['klient']->rozlacz();
}
$ust_zle         = $ust;
$ust_zle['host'] = 'nie-ma-takiego-serwera.test';
update_option( 'segm_skrzynka', $ust_zle );
$zle = segm_skrzynka_polacz();
krok( null === $zle['klient'], 'nieosiagalny serwer NIE otwiera skrzynki' );
krok( '' !== trim( (string) $zle['blad'] ), 'odmowa NAZYWA przyczyne', mb_substr( (string) $zle['blad'], 0, 60 ) );
update_option( 'segm_skrzynka', $ust );

// ---------------------------------------------------------------- B. przebieg regul
echo "\n=== B. PRZEBIEG REGUL ===\n";
$p = segm_skrzynka_przebieg( 'test' );
krok( (int) $p['sprawdzonych'] >= 1, 'przebieg przeczytal wiadomosci', 'sprawdzonych: ' . $p['sprawdzonych'] );
krok( (int) $p['dopasowanych'] >= 1, 'regula DOPASOWALA nasza wiadomosc', 'dopasowanych: ' . $p['dopasowanych'] );

// ------------------------------------------------------------------ C. tryb na sucho
echo "\n=== C. TRYB NA SUCHO — NIC NIE RUSZA ===\n";
krok( 0 === (int) $p['przeniesionych'], 'na sucho przeniesionych = 0', 'przeniesionych: ' . $p['przeniesionych'] );
krok( 1 === segmtest_ile_w_folderze( $ZRODLO, $TEMAT ), 'wiadomosc DALEJ lezy w folderze zrodlowym po przebiegu na sucho' );
krok( 0 === segmtest_ile_w_folderze( 'Segregator.SEGMTEST-Faktury', $TEMAT ) + segmtest_ile_w_folderze( 'Segregator/SEGMTEST-Faktury', $TEMAT ), 'folder docelowy pusty — nic sie nie przeniosło' );

// --------------------------------------------------------------- D. przenoszenie
echo "\n=== D. PRZENOSZENIE MAILI (NA ZYWO) ===\n";
$ust['tryb'] = 'na_zywo';
update_option( 'segm_skrzynka', $ust );
update_option( 'segm_zgoda_przenoszenie', array( 'kiedy' => current_time( 'mysql' ), 'kto' => 'test' ) );
$pz = segm_skrzynka_przebieg( 'test' );
krok( 1 === (int) $pz['przeniesionych'], 'przebieg na zywo przeniosl 1 wiadomosc', 'przeniesionych: ' . $pz['przeniesionych'] );
krok( 0 === segmtest_ile_w_folderze( $ZRODLO, $TEMAT ), 'wiadomosci NIE MA juz w folderze zrodlowym' );
$FOLDER_DO = (string) $wpdb->get_var(
	$wpdb->prepare( "SELECT folder_do FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE %s AND folder_do <> '' ORDER BY id DESC LIMIT 1", '%' . $wpdb->esc_like( $TEMAT ) . '%' )
);
krok( '' !== $FOLDER_DO, 'ruch zapisal nazwe folderu docelowego', $FOLDER_DO );
krok( 1 === segmtest_ile_w_folderze( $FOLDER_DO, $TEMAT ), 'wiadomosc JEST w folderze docelowym', $FOLDER_DO );

// ----------------------------------------------------------------- E. cofniecie
echo "\n=== E. COFNIECIE PRZEBIEGU ===\n";
$c = segm_skrzynka_cofnij( (int) $pz['id'] );
krok( (int) ( $c['cofnietych'] ?? 0 ) >= 1, 'cofniecie zglasza cofniete ruchy', 'cofnietych: ' . (int) ( $c['cofnietych'] ?? 0 ) );
krok( 1 === segmtest_ile_w_folderze( $ZRODLO, $TEMAT ), 'wiadomosc WROCILA do folderu zrodlowego' );
krok( 0 === segmtest_ile_w_folderze( $FOLDER_DO, $TEMAT ), 'folder docelowy znowu pusty', $FOLDER_DO );

echo "\n";
if ( 0 === $GLOBALS['segm_test_bledy'] ) {
	echo "WSZYSTKIE KROKI PRZESZLY\n";
	exit( 0 );
}
printf( "NIE PRZESZLO: %d krok(ow)\n", $GLOBALS['segm_test_bledy'] );
exit( 1 );
