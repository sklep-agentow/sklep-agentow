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
 * nie przejdzie: konfiguracja skrzynki wraca do stanu sprzed próby (to WSPÓLNY
 * stos, pracuje na nim też builder 1), wiersze testowe znikają, a wiadomości
 * lądują w SEGMTEST-zuzyte. Foldery testowe są dwa i stałe.
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
// ⛔ Ani temat, ani nadawca NIE MOGA pasowac do REGUL DEMO z instalatora.
// Pierwsza wersja wysylala z `ksiegowosc@dostawca.test` z tematem
// "SEGMTEST-FAKTURA-..." — a demo lapie `od: ksiegowosc` (regula 1) ORAZ
// `temat: faktur` (regula 2). Rozstrzyga kolejnosc, wiec wygrywala regula
// DEMO: wiadomosc szla do "Segregator.Faktury", test byl zielony i dowodzil,
// ze dziala JAKAS regula — nie ta dodana. Ponizszy znacznik nie wystepuje
// w zadnym wzorcu demo (pola od, temat, tresc).
$ZNACZNIK = 'segmznak' . strtolower( wp_generate_password( 8, false ) );
$TEMAT    = 'SEGMTEST ' . $ZNACZNIK;
$NADAWCA  = $ZNACZNIK . '@segmtest.invalid';
const SEGMTEST_KATEGORIA = 'SEGMTEST-Kategoria';
const SEGMTEST_ZRODLO    = 'SEGMTEST-IN';
const SEGMTEST_ZUZYTE    = 'SEGMTEST-zuzyte';
$ZRODLO = SEGMTEST_ZRODLO;
$ZUZYTE = SEGMTEST_ZUZYTE;

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
function segmtest_wyslij( $temat, $nadawca ) {
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
	$pisz( "MAIL FROM:<$nadawca>" );
	$pisz( 'RCPT TO:<szef@firma.test>' );
	$pisz( 'DATA' );
	fwrite( $s, "Subject: $temat\r\nFrom: $nadawca\r\nTo: szef@firma.test\r\n\r\nWiadomosc probna, bez slow z regul demo.\r\n.\r\n" );
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

function segmtest_sprzataj( $znacznik, $zrodlo = '' ) {
	global $wpdb;

	// Konfiguracja wspolnego stosu wraca do stanu sprzed proby.
	if ( null === $GLOBALS['segm_test_stare_ust'] ) {
		delete_option( 'segm_skrzynka' );
	} else {
		update_option( 'segm_skrzynka', $GLOBALS['segm_test_stare_ust'] );
	}
	segm_skrzynka_zapisz_haslo( (string) $GLOBALS['segm_test_stare_haslo'] );
	if ( null === $GLOBALS['segm_test_stara_zgoda'] ) {
		delete_option( 'segm_zgoda_przenoszenie' );
	} else {
		update_option( 'segm_zgoda_przenoszenie', $GLOBALS['segm_test_stara_zgoda'] );
	}
	// ⛔ Wszystkie trzy zapytania szukaja po ZNACZNIKU przez esc_like — nie po
	// brzmieniu tematu ani nazwy. Wersja z 'SEGMTEST-%' przestala cokolwiek
	// lapac w chwili, gdy temat zmienil sie na "SEGMTEST <znacznik>" (spacja
	// zamiast mysnika) i zostawila 28 wierszy we wspolnej bazie.
	$jak = '%' . $wpdb->esc_like( $znacznik ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_reguly WHERE wzorzec LIKE %s", $jak ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE %s", $jak ) );
	// Kategoria ma nazwe STALA, wiec porownujemy ja wprost — bez wzorca, ktory
	// moglby sie rozjechac z nazwa przy nastepnej zmianie.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = %s", SEGMTEST_KATEGORIA ) );
	// Klient IMAP tej wtyczki CELOWO nie ma kasowania (rozdział „Granice":
	// „Nie kasuje wiadomości"), więc próba też nie kasuje — odstawia swoje
	// wiadomości do SEGMTEST-zuzyte. Foldery testowe są DWA i stałe
	// (SEGMTEST-IN, SEGMTEST-zuzyte), więc ich liczba nie rośnie z przebiegami.
	$foldery = array( 'INBOX' );
	if ( '' !== $zrodlo ) {
		$foldery[] = $zrodlo;
	}
	foreach ( $foldery as $f ) {
		$k = new Segm_Imap( 20 );
		if ( $k->polacz( 'segm_imap', 3143, 'brak' ) && $k->zaloguj( 'szef', 'tajne1234' ) ) {
			if ( null !== $k->otworz( $f, false ) ) {
				foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
					$w = segm_skrzynka_wiadomosc( $k, $uid, false );
					if ( $w && false !== mb_strpos( (string) $w->temat, $znacznik ) ) {
						$k->przenies( $uid, SEGMTEST_ZUZYTE );
					}
				}
			}
			$k->rozlacz();
		}
	}
}

/**
 * Odstawia WSZYSTKO z podanego folderu do SEGMTEST-zuzyte.
 *
 * Wołane PRZED próbą: folder źródłowy ma być pusty, bo inaczej przebieg
 * policzyłby resztki po poprzednich uruchomieniach (widziane: „przeniesionych:
 * 2", potem „3"). Kasować nie możemy — klient tej wtyczki nie ma kasowania
 * i mieć nie będzie („Nie kasuje wiadomości" z rozdziału Granice).
 */
function segmtest_oproznij( $folder, $zuzyte ) {
	$k = new Segm_Imap( 20 );
	if ( ! $k->polacz( 'segm_imap', 3143, 'brak' ) || ! $k->zaloguj( 'szef', 'tajne1234' ) ) {
		return false;
	}
	if ( ! $k->folder_istnieje( $zuzyte ) ) {
		$k->utworz_folder( $zuzyte );
	}
	if ( ! $k->folder_istnieje( $folder ) ) {
		$k->utworz_folder( $folder );
	}
	if ( null !== $k->otworz( $folder, false ) ) {
		foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
			$k->przenies( $uid, $zuzyte );
		}
	}
	$k->rozlacz();
	return true;
}

// Sprzatanie ZANIM zaczniemy: resztki po poprzednich przebiegach leza w folderze
// zrodlowym i przebieg policzylby je razem z nasza (widziane: "przeniesionych: 2").
segmtest_oproznij( $ZRODLO, $ZUZYTE );
register_shutdown_function( 'segmtest_sprzataj', $ZNACZNIK, $ZRODLO );

// ---------------------------------------------------------------- przygotowanie
// ⛔ To jest WSPOLNY stos warsztatowy — pracuje na nim tez builder 1. Podmieniamy
// konfiguracje skrzynki na czas proby, wiec MUSIMY oddac ja taka, jaka byla:
// inaczej zostawiamy cudzemu stosowi obcy host, obce haslo i tryb `na_zywo`.
$GLOBALS['segm_test_stare_ust']   = get_option( 'segm_skrzynka', null );
$GLOBALS['segm_test_stare_haslo'] = segm_skrzynka_haslo();
$GLOBALS['segm_test_stara_zgoda'] = get_option( 'segm_zgoda_przenoszenie', null );

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

$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => SEGMTEST_KATEGORIA, 'utworzono' => current_time( 'mysql' ) ) );
$kategoria = (int) $wpdb->insert_id;
$wpdb->insert(
	$wpdb->prefix . 'segm_reguly',
	array( 'kolejnosc' => 1, 'pole' => 'temat', 'wzorzec' => $ZNACZNIK, 'kategoria_id' => $kategoria, 'utworzono' => current_time( 'mysql' ) )
);

echo "=== PRZYGOTOWANIE ===\n";
krok( segmtest_wyslij( $TEMAT, $NADAWCA ), 'wiadomosc testowa wyslana na skrzynke', $TEMAT );
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
$k_c = new Segm_Imap( 20 );
$k_c->polacz( 'segm_imap', 3143, 'brak' );
$k_c->zaloguj( 'szef', 'tajne1234' );
$FOLDER_MOJ = Segm_Imap::z_utf7( segm_skrzynka_folder_dla( $k_c, $ust['prefiks'], SEGMTEST_KATEGORIA ) );
$k_c->rozlacz();
krok( 0 === segmtest_ile_w_folderze( $FOLDER_MOJ, $TEMAT ), 'folder docelowy pusty — nic sie nie przeniosło', $FOLDER_MOJ );

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
// ⛔ ASERCJA ROZSTRZYGAJACA: folder ma byc folderem MOJEJ kategorii, a nie
// jakimkolwiek. "Cokolwiek niepustego" przechodzilo takze wtedy, gdy zadzialala
// regula DEMO — i wlasnie tak bylo w pierwszej wersji tej proby.
$k_pom = new Segm_Imap( 20 );
$k_pom->polacz( 'segm_imap', 3143, 'brak' );
$k_pom->zaloguj( 'szef', 'tajne1234' );
$OCZEKIWANY = Segm_Imap::z_utf7( segm_skrzynka_folder_dla( $k_pom, $ust['prefiks'], SEGMTEST_KATEGORIA ) );
$k_pom->rozlacz();
krok( $FOLDER_DO === $OCZEKIWANY, 'wiadomosc trafila do folderu MOJEJ kategorii, nie cudzej reguly', "jest: $FOLDER_DO · oczekiwany: $OCZEKIWANY" );
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
