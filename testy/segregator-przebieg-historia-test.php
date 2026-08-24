<?php
/**
 * STRAŻNIK LS-F2: przebieg NA ŻYWO nie rusza poczty, której nie umie zapisać
 * w historii — a gdy zapis pada, mówi o tym wprost i nie kłamie w liczniku.
 *
 * Znalezisko (raport Łamacza Segregatora, Ł-B): wtyczka najpierw przenosiła
 * wiadomości na serwerze, a wiersze historii dopisywała dopiero po pętli.
 * Gdy baza odmówiła w tym oknie: poczta przeniesiona, historia pusta,
 * cofanie martwe, a panel pokazywał „przeniesionych=1 bledow=0 blad=''".
 *
 * Co mierzy:
 *   A. (bez wady) przebieg na żywo bez awarii: 1 przeniesiona, wiersz historii
 *      „przeniesiony" z UID docelowym, wiadomość w folderze kategorii;
 *      cofnięcie wraca z nią do źródła (droga odwrotu działa, gdy baza działa);
 *   B. (LS-F2) baza odrzuca INSERT do historii ruchów → przebieg ZWRACA BŁĄD,
 *      `przeniesionych` = 0 i wiadomość NADAL leży w folderze źródłowym;
 *   C. (LS-F2) baza odrzuca zapis wiersza przebiegu → przebieg zwraca błąd
 *      i NIC nie rusza (wiadomość w źródle).
 *
 * Awarię bazy podkłada filtr `query` WordPressa (ta sama metoda co sonda
 * `audyt/boty/repro/lamacz-baza-i-srodowisko.php`): INSERT do wskazanej
 * tabeli idzie do tabeli, której nie ma. Nic nie jest kasowane.
 *
 * Sprząta po sobie także po padzie: konfiguracja skrzynki, hasło i zgoda
 * wracają do stanu sprzed próby (WSPÓLNY stos), wiersze testowe znikają,
 * wiadomości lądują w SEGMTEST-zuzyte.
 *
 * Uruchomienie:
 *   docker cp testy/segregator-przebieg-historia-test.php strona_wp_wordpress:/tmp/x.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
 * EXIT 0 = wszystko OK, 1 = któryś punkt padł.
 */

$katalog = WP_PLUGIN_DIR . '/segregator-maila/includes/';
require_once $katalog . 'konfiguracja.php';
require_once $katalog . 'class-segm-imap.php';
require_once $katalog . 'silnik.php';
require_once $katalog . 'skrzynka.php';
require_once $katalog . 'skrzynka-przebieg.php';

global $wpdb;
$GLOBALS['segmh_bledy'] = 0;
// ⛔ `wp eval-file` uruchamia plik w ZASIĘGU FUNKCJI — zmienne z góry pliku NIE są
// globalne, więc wszystko, czego potrzebuje sprzątanie przy zamknięciu, siedzi
// w $GLOBALS (ta sama pułapka, którą opisuje segregator-skrzynka-proba-kontrolna.sh).
$ZN      = 'segmhist' . strtolower( wp_generate_password( 8, false ) );
$ZRODLO  = 'SEGMHIST-IN';
$KAT     = 'SEGMHIST-Kategoria';
$PREFIKS = 'SEGMHIST';
$ZUZYTE  = 'SEGMTEST-zuzyte';
$GLOBALS['segmh_cfg']       = compact( 'ZN', 'ZRODLO', 'KAT', 'PREFIKS', 'ZUZYTE' );
$GLOBALS['segmh_przebiegi'] = array();

function segmh( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " ($szczegol)" : '' );
	if ( ! $ok ) {
		++$GLOBALS['segmh_bledy'];
	}
	return $ok;
}
function segmh_klient() {
	$k = new Segm_Imap( 20 );
	if ( ! $k->polacz( 'segm_imap', 3143, 'brak' ) || ! $k->zaloguj( 'szef', 'tajne1234' ) ) {
		return null;
	}
	return $k;
}
function segmh_wyslij( $temat, $nadawca ) {
	$s = @fsockopen( 'segm_imap', 3025, $e, $es, 10 );
	if ( ! $s ) {
		return false;
	}
	$r = function () use ( $s ) {
		return fgets( $s, 1024 );
	};
	$w = function ( $l ) use ( $s, $r ) {
		fwrite( $s, $l . "\r\n" );
		return $r();
	};
	$r();
	$w( 'HELO test' );
	$w( "MAIL FROM:<$nadawca>" );
	$w( 'RCPT TO:<szef@firma.test>' );
	$w( 'DATA' );
	fwrite( $s, "Subject: $temat\r\nFrom: $nadawca\r\nTo: szef@firma.test\r\nMessage-ID: <$nadawca>\r\n\r\nProba strażnika historii.\r\n.\r\n" );
	$r();
	$w( 'QUIT' );
	fclose( $s );
	return true;
}
/** UID-y wiadomości z danym znacznikiem w temacie w danym folderze (pusta tablica, gdy folderu nie ma). */
function segmh_uidy_z( $folder, $znacznik ) {
	$k = segmh_klient();
	if ( ! $k ) {
		return array( -1 );
	}
	$wynik = array();
	if ( null !== $k->otworz( $folder, true ) ) {
		foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
			$w = segm_skrzynka_wiadomosc( $k, $uid, false );
			if ( $w && false !== mb_strpos( (string) $w->temat, $znacznik ) ) {
				$wynik[] = (int) $uid;
			}
		}
	}
	$k->rozlacz();
	return $wynik;
}
/** Przenosi wiadomości ze znacznikiem z $skad do $dokad (tworzy folder docelowy). */
function segmh_przestaw( $skad, $dokad, $znacznik ) {
	$k = segmh_klient();
	if ( ! $k ) {
		return false;
	}
	if ( ! $k->folder_istnieje( $dokad ) ) {
		$k->utworz_folder( $dokad );
	}
	$ok = false;
	if ( null !== $k->otworz( $skad, false ) ) {
		foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
			$w = segm_skrzynka_wiadomosc( $k, $uid, false );
			if ( $w && false !== mb_strpos( (string) $w->temat, $znacznik ) ) {
				$ok = null !== $k->przenies( $uid, $dokad );
			}
		}
	}
	$k->rozlacz();
	return $ok;
}
function segmh_sprzataj() {
	global $wpdb;
	extract( $GLOBALS['segmh_cfg'] ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- ZN, ZRODLO, KAT, PREFIKS, ZUZYTE (patrz uwaga przy definicji)
	remove_all_filters( 'query' );
	if ( null === $GLOBALS['segmh_stare_ust'] ) {
		delete_option( 'segm_skrzynka' );
	} else {
		update_option( 'segm_skrzynka', $GLOBALS['segmh_stare_ust'] );
	}
	segm_skrzynka_zapisz_haslo( (string) $GLOBALS['segmh_stare_haslo'] );
	if ( null === $GLOBALS['segmh_stara_zgoda'] ) {
		delete_option( 'segm_zgoda_przenoszenie' );
	} else {
		update_option( 'segm_zgoda_przenoszenie', $GLOBALS['segmh_stara_zgoda'] );
	}
	delete_option( SEGM_BLOKADA );
	$jak = '%' . $wpdb->esc_like( $ZN ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_reguly WHERE wzorzec LIKE %s", $jak ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = %s", $KAT ) );
	foreach ( $GLOBALS['segmh_przebiegi'] as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d", (int) $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", (int) $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE %s", $jak ) );
	foreach ( array( 'INBOX', $ZRODLO, $PREFIKS . '.' . $KAT ) as $f ) {
		segmh_przestaw( $f, $ZUZYTE, $ZN );
	}
}
function segmh_zapamietaj_przebieg( $id ) {
	if ( $id > 0 ) {
		$GLOBALS['segmh_przebiegi'][] = (int) $id;
	}
}
/** Ostatni wiersz przebiegu — też gdy przebieg zwrócił id=0 (awaria) a wiersz jednak powstał. */
function segmh_ostatni_przebieg_id() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}segm_skrz_przebiegi" ); // phpcs:ignore
}

// ---------------------------------------------------------------- przygotowanie
$GLOBALS['segmh_stare_ust']   = get_option( 'segm_skrzynka', null );
$GLOBALS['segmh_stare_haslo'] = segm_skrzynka_haslo();
$GLOBALS['segmh_stara_zgoda'] = get_option( 'segm_zgoda_przenoszenie', null );
register_shutdown_function( 'segmh_sprzataj' );

$ust = array_merge(
	segm_skrzynka_ustawienia(),
	array(
		'host'                 => 'segm_imap',
		'port'                 => 3143,
		'szyfrowanie'          => 'brak',
		'login'                => 'szef',
		'folder_zrodlowy'      => $ZRODLO,
		'prefiks'              => $PREFIKS,
		'limit'                => 50,
		'tryb'                 => 'na_zywo',
		'ruszaj_reszte'        => 0,
		'tylko_nieprzeczytane' => 0,
		'dopasuj_tresc'        => 0,
	)
);
update_option( 'segm_skrzynka', $ust );
segm_skrzynka_zapisz_haslo( 'tajne1234' );
update_option(
	'segm_zgoda_przenoszenie',
	array(
		'kiedy' => current_time( 'mysql' ),
		'kto'   => 'segmhist-test',
	)
);
$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => $KAT, 'utworzono' => current_time( 'mysql' ) ) );
$kat_id = (int) $wpdb->insert_id;
$wpdb->insert(
	$wpdb->prefix . 'segm_reguly',
	array( 'kolejnosc' => 1, 'pole' => 'temat', 'wzorzec' => $ZN, 'kategoria_id' => $kat_id, 'utworzono' => current_time( 'mysql' ) )
);
$FOLDER_KAT = $PREFIKS . '.' . $KAT;
$id_przed   = segmh_ostatni_przebieg_id();

echo "=== PRZYGOTOWANIE ===\n";
segmh( segm_przenoszenie_dozwolone(), 'tryb na zywo + zgoda (przebieg NAPRAWDE przenosi)' );
segmh( segmh_wyslij( "SEGMHIST $ZN A", "$ZN-a@segmhist.invalid" ), 'wiadomosc A wyslana' );
sleep( 1 );
segmh( segmh_przestaw( 'INBOX', $ZRODLO, $ZN ), 'wiadomosc A odstawiona do ' . $ZRODLO );
segmh( 1 === count( segmh_uidy_z( $ZRODLO, $ZN ) ), 'A lezy w zrodle przed przebiegiem' );

// ------------------------------------------------------------- A. bez wady
echo "\n=== A. BEZ WADY: przebieg na zywo, baza dziala ===\n";
$w = segm_skrzynka_przebieg( 'reczny' );
segmh_zapamietaj_przebieg( $w['id'] );
segmh( '' === $w['blad'], 'przebieg bez bledu', $w['blad'] );
segmh( 1 === (int) $w['przeniesionych'], 'przeniesionych = 1', (string) $w['przeniesionych'] );
$wiersze = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d", (int) $w['id'] ) ); // phpcs:ignore
segmh( 1 === count( $wiersze ) && 'przeniesiony' === $wiersze[0]->stan, 'historia: 1 wiersz w stanie „przeniesiony"', count( $wiersze ) . ' wierszy' . ( $wiersze ? ', stan=' . $wiersze[0]->stan : '' ) );
segmh( $wiersze && (int) $wiersze[0]->uid_docelowy > 0, 'historia zna UID docelowy', $wiersze ? (string) $wiersze[0]->uid_docelowy : '-' );
segmh( 0 === count( segmh_uidy_z( $ZRODLO, $ZN ) ), 'A zniknela ze zrodla' );
segmh( 1 === count( segmh_uidy_z( $FOLDER_KAT, $ZN ) ), 'A jest w folderze kategorii ' . $FOLDER_KAT );
$c = segm_skrzynka_cofnij( (int) $w['id'] );
segmh( true === $c['ok'] && 1 === (int) $c['cofnietych'] && 0 === (int) $c['bledow'], 'cofniecie: 1 wrocila, 0 bledow', wp_json_encode( $c ) );
segmh( 1 === count( segmh_uidy_z( $ZRODLO, $ZN ) ), 'A z powrotem w zrodle' );

// ------------------------------------------------------- B. pad zapisu historii
echo "\n=== B. LS-F2: baza odrzuca INSERT do historii ruchow ===\n";
$wpdb->suppress_errors( true );
add_filter(
	'query',
	function ( $q ) {
		if ( false !== strpos( $q, 'segm_skrz_ruchy' ) && 0 === stripos( ltrim( $q ), 'INSERT' ) ) {
			return 'INSERT INTO segmhist_tabela_ktorej_nie_ma (a) VALUES (1)';
		}
		return $q;
	},
	99
);
$w = segm_skrzynka_przebieg( 'reczny' );
remove_all_filters( 'query' );
$wpdb->suppress_errors( false );
$id_b = $w['id'] > 0 ? (int) $w['id'] : segmh_ostatni_przebieg_id();
if ( $id_b > $id_przed ) {
	segmh_zapamietaj_przebieg( $id_b );
}
printf( "  przebieg zwrocil: przeniesionych=%d bledow=%d blad=\"%s\"\n", $w['przeniesionych'], $w['bledow'], $w['blad'] );
segmh( '' !== $w['blad'], 'przebieg ZWRACA BLAD, gdy historia nie daje sie zapisac' );
segmh( 0 === (int) $w['przeniesionych'], 'przeniesionych = 0 (licznik nie klamie)', (string) $w['przeniesionych'] );
segmh( 1 === count( segmh_uidy_z( $ZRODLO, $ZN ) ), 'A NADAL w zrodle — nic nie ruszono bez zapisu' );
segmh( 0 === count( segmh_uidy_z( $FOLDER_KAT, $ZN ) ), 'folder kategorii pusty' );
$wiersz_b = $id_b > $id_przed ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", $id_b ) ) : null; // phpcs:ignore
segmh( ! $wiersz_b || 0 === (int) $wiersz_b->przeniesionych, 'wiersz przebiegu (jesli jest) nie deklaruje przeniesionych', $wiersz_b ? 'przeniesionych=' . $wiersz_b->przeniesionych : 'brak wiersza' );
// (recenzja #156) Gdy koncowe podsumowanie tez padnie, w wierszu zostaje
// komunikat startowy — ma OSTRZEGAC, a nie mowic „w toku" w nieskonczonosc.
segmh( ! $wiersz_b || false !== mb_strpos( (string) $wiersz_b->komunikat, 'NIE doszedl' ) || false !== mb_strpos( (string) $wiersz_b->komunikat, 'NIE doszedł' ) || false !== mb_strpos( (string) $wiersz_b->komunikat, 'PRZERWANY' ), 'komunikat wiersza ostrzega, ze przebieg sie nie dokonczyl', $wiersz_b ? mb_substr( (string) $wiersz_b->komunikat, 0, 120 ) : 'brak wiersza' );

// ------------------------------- B2. pad zapisu historii I podsumowania (recenzja #156)
echo "\n=== B2. LS-F2: pada zapis historii ORAZ koncowe podsumowanie ===\n";
$wpdb->suppress_errors( true );
add_filter(
	'query',
	function ( $q ) {
		$p = ltrim( $q );
		if ( false !== strpos( $q, 'segm_skrz_ruchy' ) && 0 === stripos( $p, 'INSERT' ) ) {
			return 'INSERT INTO segmhist_tabela_ktorej_nie_ma (a) VALUES (1)';
		}
		if ( false !== strpos( $q, 'segm_skrz_przebiegi' ) && 0 === stripos( $p, 'UPDATE' ) ) {
			return 'UPDATE segmhist_tabela_ktorej_nie_ma SET a = 1';
		}
		return $q;
	},
	99
);
$w = segm_skrzynka_przebieg( 'reczny' );
remove_all_filters( 'query' );
$wpdb->suppress_errors( false );
$id_b2 = $w['id'] > 0 ? (int) $w['id'] : segmh_ostatni_przebieg_id();
if ( $id_b2 > $id_przed ) {
	segmh_zapamietaj_przebieg( $id_b2 );
}
$wiersz_b2 = $id_b2 > $id_przed ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", $id_b2 ) ) : null; // phpcs:ignore
printf( "  wiersz przebiegu #%d: komunikat=\"%s\"\n", $id_b2, $wiersz_b2 ? mb_substr( (string) $wiersz_b2->komunikat, 0, 160 ) : '(brak)' );
segmh( $wiersz_b2 && false !== mb_strpos( (string) $wiersz_b2->komunikat, 'NIE doszedł do końca' ), 'wiersz, ktory zostal bez podsumowania, OSTRZEGA zamiast mowic „w toku"', $wiersz_b2 ? mb_substr( (string) $wiersz_b2->komunikat, 0, 80 ) : 'brak' );
segmh( 1 === count( segmh_uidy_z( $ZRODLO, $ZN ) ), 'A NADAL w zrodle' );

// ------------------------------------------------ C. pad zapisu wiersza przebiegu
echo "\n=== C. LS-F2: baza odrzuca zapis wiersza przebiegu ===\n";
$wpdb->suppress_errors( true );
add_filter(
	'query',
	function ( $q ) {
		if ( false !== strpos( $q, 'segm_skrz_przebiegi' ) && 0 === stripos( ltrim( $q ), 'INSERT' ) ) {
			return 'INSERT INTO segmhist_tabela_ktorej_nie_ma (a) VALUES (1)';
		}
		return $q;
	},
	99
);
$w = segm_skrzynka_przebieg( 'reczny' );
remove_all_filters( 'query' );
$wpdb->suppress_errors( false );
printf( "  przebieg zwrocil: przeniesionych=%d bledow=%d blad=\"%s\"\n", $w['przeniesionych'], $w['bledow'], $w['blad'] );
segmh( '' !== $w['blad'], 'przebieg zwraca blad' );
segmh( 0 === (int) $w['przeniesionych'], 'przeniesionych = 0', (string) $w['przeniesionych'] );
segmh( 1 === count( segmh_uidy_z( $ZRODLO, $ZN ) ), 'A NADAL w zrodle — bez wiersza przebiegu nic nie rusza' );

// --------------------------------------------------- D. bez wady po awariach
echo "\n=== D. BEZ WADY: po awariach zwykly przebieg dalej przenosi ===\n";
$w = segm_skrzynka_przebieg( 'reczny' );
segmh_zapamietaj_przebieg( $w['id'] );
segmh( '' === $w['blad'] && 1 === (int) $w['przeniesionych'], 'przebieg po awariach: 1 przeniesiona, bez bledu', wp_json_encode( $w ) );
segmh( 1 === count( segmh_uidy_z( $FOLDER_KAT, $ZN ) ), 'A w folderze kategorii' );
segmh( null === get_option( SEGM_BLOKADA, null ) || false === get_option( SEGM_BLOKADA ), 'zamek zwolniony po przebiegach' );

printf( "\nBLEDOW: %d\n", $GLOBALS['segmh_bledy'] );
exit( $GLOBALS['segmh_bledy'] > 0 ? 1 : 0 );
