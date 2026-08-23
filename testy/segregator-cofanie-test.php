<?php
/**
 * STRAŻNIK LS-F1 + T-3: cofnięcie przebiegu liczy TYLKO wiadomości, które
 * naprawdę wróciły — i nie daje się nabrać na stary UID w nowym folderze.
 *
 * Znalezisko (raport Łamacza Segregatora, Ł-A): `UID MOVE` nieistniejącego
 * UID-a kończy się „OK" (RFC 3501 §6.4.8 — serwer nie ma nic do zgłoszenia),
 * a wtyczka liczyła „++cofnietych" za sam status. Właściciel dostawał
 * „cofnięto 2", gdy wróciła jedna, a przebieg dostawał `cofniety=1` i drugiej
 * próby już nie było. Wariant ostrzejszy (T-3): folder kategorii skasowany
 * i założony na nowo — UID-y zaczynają od 1, stary UID wskazuje OBCĄ
 * wiadomość; UIDVALIDITY było odczytywane i wyrzucane.
 *
 * Co mierzy:
 *   A. (bez wady) przebieg przenosi A i B, cofnięcie: cofnietych=2, obie w źródle;
 *   B. (LS-F1) przebieg przenosi A i B; CZŁOWIEK przeciąga B gdzie indziej;
 *      cofnięcie melduje cofnietych=1, bledow=1; A w źródle, B tam, gdzie ją
 *      zostawił człowiek; przebieg NIE jest oznaczony jako cofnięty w całości;
 *   C. (T-3) przebieg przenosi A; folder kategorii skasowany i założony na
 *      nowo, ląduje w nim OBCA wiadomość C (dostaje ten sam UID co A);
 *      cofnięcie melduje 0 cofniętych i błąd; C ZOSTAJE w folderze kategorii
 *      (nie jest „cofana" jako A), źródło puste.
 *
 * Sprząta po sobie także po padzie (WSPÓLNY stos): konfiguracja skrzynki,
 * hasło i zgoda wracają; wiersze testowe znikają; wiadomości do SEGMTEST-zuzyte.
 *
 * Uruchomienie:
 *   docker cp testy/segregator-cofanie-test.php strona_wp_wordpress:/tmp/x.php
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
$GLOBALS['segmc_bledy'] = 0;
// ⛔ `wp eval-file` uruchamia plik w ZASIĘGU FUNKCJI — to, czego potrzebuje
// sprzątanie przy zamknięciu, siedzi w $GLOBALS.
$ZN      = 'segmcof' . strtolower( wp_generate_password( 8, false ) );
$ZRODLO  = 'SEGMCOF-IN';
$KAT     = 'SEGMCOF-Kategoria';
$PREFIKS = 'SEGMCOF';
$OBOK    = 'SEGMCOF-gdzieindziej';
$ZUZYTE  = 'SEGMTEST-zuzyte';
$FOLDER_KAT = $PREFIKS . '.' . $KAT;
$GLOBALS['segmc_cfg']       = compact( 'ZN', 'ZRODLO', 'KAT', 'PREFIKS', 'OBOK', 'ZUZYTE', 'FOLDER_KAT' );
$GLOBALS['segmc_przebiegi'] = array();

function segmc( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " ($szczegol)" : '' );
	if ( ! $ok ) {
		++$GLOBALS['segmc_bledy'];
	}
	return $ok;
}
function segmc_klient() {
	$k = new Segm_Imap( 20 );
	if ( ! $k->polacz( 'segm_imap', 3143, 'brak' ) || ! $k->zaloguj( 'szef', 'tajne1234' ) ) {
		return null;
	}
	return $k;
}
function segmc_wyslij( $temat, $nadawca ) {
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
	fwrite( $s, "Subject: $temat\r\nFrom: $nadawca\r\nTo: szef@firma.test\r\nMessage-ID: <$nadawca>\r\n\r\nProba strażnika cofania.\r\n.\r\n" );
	$r();
	$w( 'QUIT' );
	fclose( $s );
	return true;
}
/** Tematy (z fragmentem znacznika) w folderze — pusta tablica, gdy folderu nie ma. */
function segmc_tematy( $folder, $znacznik ) {
	$k = segmc_klient();
	if ( ! $k ) {
		return array( 'BLAD POLACZENIA' );
	}
	$wynik = array();
	if ( null !== $k->otworz( $folder, true ) ) {
		foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
			$w = segmc_wiadomosc( $k, $uid );
			if ( $w && false !== mb_strpos( (string) $w->temat, $znacznik ) ) {
				$wynik[] = 'UID' . $uid . ':' . trim( str_replace( $znacznik, '', (string) $w->temat ) );
			}
		}
	}
	$k->rozlacz();
	return $wynik;
}
function segmc_wiadomosc( $k, $uid ) {
	return segm_skrzynka_wiadomosc( $k, $uid, false );
}
/** Przenosi wiadomości, których temat zawiera $czego, z $skad do $dokad (tworzy $dokad). */
function segmc_przestaw( $skad, $dokad, $czego ) {
	$k = segmc_klient();
	if ( ! $k ) {
		return false;
	}
	if ( ! $k->folder_istnieje( $dokad ) ) {
		$k->utworz_folder( $dokad );
	}
	$ok = false;
	if ( null !== $k->otworz( $skad, false ) ) {
		foreach ( (array) $k->uidy( 'ALL' ) as $uid ) {
			$w = segmc_wiadomosc( $k, $uid );
			if ( $w && false !== mb_strpos( (string) $w->temat, $czego ) ) {
				$ok = null !== $k->przenies( $uid, $dokad );
			}
		}
	}
	$k->rozlacz();
	return $ok;
}
/**
 * Kasuje folder na serwerze — jak człowiek w programie pocztowym. Klient
 * wtyczki CELOWO nie ma kasowania („Nie kasuje wiadomości"), więc test mówi
 * do serwera sam, surowym gniazdem.
 */
function segmc_skasuj_folder( $folder ) {
	$s = @fsockopen( 'segm_imap', 3143, $e, $es, 10 );
	if ( ! $s ) {
		return false;
	}
	$do_konca = function ( $tag ) use ( $s ) {
		$ostatnia = '';
		while ( ( $l = fgets( $s, 4096 ) ) !== false ) {
			$ostatnia = $l;
			if ( 0 === strpos( $l, $tag . ' ' ) ) {
				break;
			}
		}
		return $ostatnia;
	};
	fgets( $s, 4096 );
	fwrite( $s, "a1 LOGIN szef tajne1234\r\n" );
	$do_konca( 'a1' );
	fwrite( $s, 'a2 DELETE "' . $folder . "\"\r\n" );
	$odp = $do_konca( 'a2' );
	fwrite( $s, "a3 LOGOUT\r\n" );
	fclose( $s );
	return false !== strpos( $odp, 'OK' );
}
function segmc_sprzataj() {
	global $wpdb;
	extract( $GLOBALS['segmc_cfg'] ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- ZN, ZRODLO, KAT, PREFIKS, OBOK, ZUZYTE, FOLDER_KAT (+FOLDER_KAT_C od czesci C)
	if ( null === $GLOBALS['segmc_stare_ust'] ) {
		delete_option( 'segm_skrzynka' );
	} else {
		update_option( 'segm_skrzynka', $GLOBALS['segmc_stare_ust'] );
	}
	segm_skrzynka_zapisz_haslo( (string) $GLOBALS['segmc_stare_haslo'] );
	if ( null === $GLOBALS['segmc_stara_zgoda'] ) {
		delete_option( 'segm_zgoda_przenoszenie' );
	} else {
		update_option( 'segm_zgoda_przenoszenie', $GLOBALS['segmc_stara_zgoda'] );
	}
	delete_option( SEGM_BLOKADA );
	$jak = '%' . $wpdb->esc_like( $ZN ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_reguly WHERE wzorzec LIKE %s", $jak ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_kategorie WHERE nazwa = %s", $KAT ) );
	foreach ( $GLOBALS['segmc_przebiegi'] as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d", (int) $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", (int) $id ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}segm_skrz_ruchy WHERE temat LIKE %s", $jak ) );
	$foldery = array( 'INBOX', $ZRODLO, $FOLDER_KAT, $OBOK );
	if ( isset( $FOLDER_KAT_C ) ) {
		$foldery[] = $FOLDER_KAT_C;
	}
	foreach ( $foldery as $f ) {
		segmc_przestaw( $f, $ZUZYTE, $ZN );
	}
}
function segmc_przebieg() {
	$w = segm_skrzynka_przebieg( 'reczny' );
	if ( $w['id'] > 0 ) {
		$GLOBALS['segmc_przebiegi'][] = (int) $w['id'];
	}
	return $w;
}
function segmc_wiersz_przebiegu( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", (int) $id ) ); // phpcs:ignore
}

// ---------------------------------------------------------------- przygotowanie
$GLOBALS['segmc_stare_ust']   = get_option( 'segm_skrzynka', null );
$GLOBALS['segmc_stare_haslo'] = segm_skrzynka_haslo();
$GLOBALS['segmc_stara_zgoda'] = get_option( 'segm_zgoda_przenoszenie', null );
register_shutdown_function( 'segmc_sprzataj' );

update_option(
	'segm_skrzynka',
	array_merge(
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
	)
);
segm_skrzynka_zapisz_haslo( 'tajne1234' );
update_option( 'segm_zgoda_przenoszenie', array( 'kiedy' => current_time( 'mysql' ), 'kto' => 'segmcof-test' ) );
$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => $KAT, 'utworzono' => current_time( 'mysql' ) ) );
$kat_id = (int) $wpdb->insert_id;
$wpdb->insert(
	$wpdb->prefix . 'segm_reguly',
	array( 'kolejnosc' => 1, 'pole' => 'temat', 'wzorzec' => $ZN, 'kategoria_id' => $kat_id, 'utworzono' => current_time( 'mysql' ) )
);

echo "=== PRZYGOTOWANIE ===\n";
segmc( segm_przenoszenie_dozwolone(), 'tryb na zywo + zgoda' );
segmc( segmc_wyslij( "SEGMCOF $ZN A", "$ZN-a@segmcof.invalid" ), 'wiadomosc A wyslana' );
segmc( segmc_wyslij( "SEGMCOF $ZN B", "$ZN-b@segmcof.invalid" ), 'wiadomosc B wyslana' );
sleep( 1 );
segmc( segmc_przestaw( 'INBOX', $ZRODLO, $ZN ), 'A i B odstawione do ' . $ZRODLO );
segmc( 2 === count( segmc_tematy( $ZRODLO, $ZN ) ), 'A i B leza w zrodle', implode( ' | ', segmc_tematy( $ZRODLO, $ZN ) ) );

// ------------------------------------------------------------- A. bez wady
echo "\n=== A. BEZ WADY: nikt nie rusza poczty miedzy przebiegiem a cofnieciem ===\n";
$w = segmc_przebieg();
segmc( '' === $w['blad'] && 2 === (int) $w['przeniesionych'], 'przebieg: 2 przeniesione', wp_json_encode( $w ) );
$c = segm_skrzynka_cofnij( (int) $w['id'] );
segmc( true === $c['ok'] && 2 === (int) $c['cofnietych'] && 0 === (int) $c['bledow'], 'cofniecie: 2 wrocily, 0 bledow', wp_json_encode( $c ) );
segmc( 2 === count( segmc_tematy( $ZRODLO, $ZN ) ), 'A i B z powrotem w zrodle', implode( ' | ', segmc_tematy( $ZRODLO, $ZN ) ) );
$p = segmc_wiersz_przebiegu( (int) $w['id'] );
segmc( $p && 1 === (int) $p->cofniety, 'przebieg oznaczony jako cofniety w calosci' );

// ------------------------------------------- B. LS-F1: czlowiek przeciagnal B
echo "\n=== B. LS-F1: wlasciciel przeciagnal B gdzie indziej przed cofnieciem ===\n";
$w = segmc_przebieg();
segmc( '' === $w['blad'] && 2 === (int) $w['przeniesionych'], 'przebieg: 2 przeniesione', wp_json_encode( $w ) );
segmc( segmc_przestaw( $FOLDER_KAT, $OBOK, "$ZN B" ), 'CZLOWIEK przeciagnal B do ' . $OBOK );
segmc( 1 === count( segmc_tematy( $FOLDER_KAT, $ZN ) ), 'w folderze kategorii zostala tylko A', implode( ' | ', segmc_tematy( $FOLDER_KAT, $ZN ) ) );
$c = segm_skrzynka_cofnij( (int) $w['id'] );
printf( "  cofniecie zwrocilo: %s\n", wp_json_encode( $c ) );
segmc( 1 === (int) $c['cofnietych'], 'cofnietych = 1 (tylko A naprawde wrocila)', (string) $c['cofnietych'] );
segmc( 1 === (int) $c['bledow'], 'bledow = 1 (B nie odnaleziona)', (string) $c['bledow'] );
$w_zrodle = segmc_tematy( $ZRODLO, $ZN );
segmc( 1 === count( $w_zrodle ) && preg_match( '/ A$/', $w_zrodle[0] ), 'w zrodle jest A i tylko A', implode( ' | ', $w_zrodle ) );
segmc( 1 === count( segmc_tematy( $OBOK, $ZN ) ), 'B nadal tam, gdzie zostawil ja czlowiek', implode( ' | ', segmc_tematy( $OBOK, $ZN ) ) );
$p = segmc_wiersz_przebiegu( (int) $w['id'] );
segmc( $p && 0 === (int) $p->cofniety, 'przebieg NIE jest oznaczony jako cofniety w calosci (droga odwrotu otwarta)', $p ? 'cofniety=' . $p->cofniety : 'brak wiersza' );
$ruch_b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d AND temat LIKE %s", (int) $w['id'], '%' . $wpdb->esc_like( "$ZN B" ) . '%' ) ); // phpcs:ignore
segmc( $ruch_b && 'cofniety' !== $ruch_b->stan, 'pozycja B w historii NIE udaje cofnietej', $ruch_b ? 'stan=' . $ruch_b->stan . ' / ' . $ruch_b->komunikat : 'brak wiersza' );
// porzadek przed C: B wraca do zrodla recznie
segmc_przestaw( $OBOK, $ZRODLO, "$ZN B" );
segmc_przestaw( $ZRODLO, $ZUZYTE, "$ZN B" );

// ------------------------------------- C. T-3: folder skasowany i zalozony na nowo
echo "\n=== C. T-3: folder kategorii skasowany i zalozony na nowo, w srodku OBCA wiadomosc ===\n";
// ⚠️ GreenMail 2.1.3: DELETE folderu, który KTÓRAKOLWIEK sesja (także już
// zamknięta) wcześniej wybrała przez SELECT/EXAMINE, kończy się NPE po stronie
// serwera i zerwaniem połączenia — folder zostaje. Dlatego część C dostaje
// prefiks unikalny dla tego uruchomienia: folder kategorii powstaje w przebiegu
// (CREATE + UID MOVE, bez SELECT na nim) i NIKT go nie ogląda przed skasowaniem.
$PREFIKS_C    = 'SEGMCOF-' . substr( $ZN, -6 );
$FOLDER_KAT_C = $PREFIKS_C . '.' . $KAT;
$GLOBALS['segmc_cfg']['FOLDER_KAT_C'] = $FOLDER_KAT_C;
$ust_c            = segm_skrzynka_ustawienia();
$ust_c['prefiks'] = $PREFIKS_C;
update_option( 'segm_skrzynka', $ust_c );
segmc( 1 === count( segmc_tematy( $ZRODLO, $ZN ) ), 'w zrodle zostala sama A', implode( ' | ', segmc_tematy( $ZRODLO, $ZN ) ) );
$w = segmc_przebieg();
segmc( '' === $w['blad'] && 1 === (int) $w['przeniesionych'], 'przebieg: A przeniesiona do ' . $FOLDER_KAT_C, wp_json_encode( $w ) );
segmc( segmc_skasuj_folder( $FOLDER_KAT_C ), 'CZLOWIEK skasowal folder kategorii (razem z A)' );
sleep( 2 ); // GreenMail nadaje UIDVALIDITY z zegara sekundowego — nowy folder ma dostac inne
segmc( segmc_wyslij( "SEGMCOF $ZN C-obca", "$ZN-c@segmcof.invalid" ), 'obca wiadomosc C wyslana' );
sleep( 1 );
segmc( segmc_przestaw( 'INBOX', $FOLDER_KAT_C, "$ZN C-obca" ), 'C wlozona do ZALOZONEGO NA NOWO folderu kategorii' );
$w_kat = segmc_tematy( $FOLDER_KAT_C, $ZN );
segmc( 1 === count( $w_kat ) && 0 === strpos( $w_kat[0], 'UID1:' ), 'C ma UID 1 — ten sam, ktory miala A', implode( ' | ', $w_kat ) );
$c = segm_skrzynka_cofnij( (int) $w['id'] );
printf( "  cofniecie zwrocilo: %s\n", wp_json_encode( $c ) );
segmc( 0 === (int) $c['cofnietych'], 'cofnietych = 0 (A juz nie istnieje)', (string) $c['cofnietych'] );
segmc( false === $c['ok'] || $c['bledow'] > 0, 'cofniecie melduje problem, nie sukces', wp_json_encode( $c ) );
segmc( 1 === count( segmc_tematy( $FOLDER_KAT_C, $ZN ) ), 'obca C ZOSTALA w folderze kategorii (nie zostala „cofnieta" jako A)', implode( ' | ', segmc_tematy( $FOLDER_KAT_C, $ZN ) ) );
segmc( 0 === count( segmc_tematy( $ZRODLO, $ZN ) ), 'zrodlo puste — nic obcego tam nie trafilo', implode( ' | ', segmc_tematy( $ZRODLO, $ZN ) ) );

printf( "\nBLEDOW: %d\n", $GLOBALS['segmc_bledy'] );
exit( $GLOBALS['segmc_bledy'] > 0 ? 1 : 0 );
