<?php
/**
 * STRAZNIK REGRESJI (bezpieczenstwo): para nonce+uprawnienia na akcjach panelu.
 *
 * ⛔ TO NIE JEST OPIS ZNALEZISKA. Na obecnym kodzie ten test PRZECHODZI.
 * Powstal w rundzie audytu A1, gdy `segm_post_skrzynka_test()` nie miala
 * `current_user_can()` — ale to bylo MUTANT KALIBRACYJNY M1, zdjety commitem
 * 8c1220c. Na `main` ta funkcja sprawdzenie uprawnien MA. Test zostaje jako
 * straznik: gdyby ktos kiedys usunal te pare, tutaj zapali sie na czerwono.
 * Kalibracja zmierzona: na kodzie z mutantem M1 dawal EXIT=1 (2 przypadki),
 * po zdjeciu mutanta EXIT=0.
 *
 * Regula projektu (CLAUDE.md, "Bezpieczenstwo — minimum podrecznikowe"):
 *   "Nonce I sprawdzenie uprawnien PARA na kazdej mutacji panelu".
 * Nonce to ochrona przed CSRF, NIE jest autoryzacja: mowi "to zadanie wyszlo
 * z naszej strony", nie mowi "ten czlowiek ma prawo to zrobic". Dlatego sam
 * `check_admin_referer()` bez `current_user_can()` nie wystarcza.
 *
 * Dlaczego akurat ta akcja jest warta straznika: bez autoryzacji (1) zapisuje
 * globalna opcje `segm_ostatni_raport_cache`, (2) wola `segm_skrzynka_sprawdz()`,
 * czyli LOGUJE SIE na serwer IMAP wlasciciela jego zapisanym haslem i wypisuje
 * liste folderow. Konto bez uprawnien nie ma prawa tego uruchomic.
 *
 * CO TEST SPRAWDZA (4 przypadki, w tym dwa BEZ wady — inaczej "naprawa"
 * mogłaby odebrać funkcję wszystkim i świecić na zielono):
 *   1. subskrybent z WLASNYM waznym nonce  -> MA dostac odmowe
 *   2. redaktor    z WLASNYM waznym nonce  -> MA dostac odmowe
 *   3. administrator z waznym nonce        -> NIE WOLNO go zablokowac
 *   4. kontrola metody: `segm_skrzynka_tryb` (akcja, ktora PARE ma)
 *      odmawia subskrybentowi -> dowod, ze ten test umie odroznic
 *      akcje zabezpieczona od niezabezpieczonej i nie pada "na wszystkim"
 *
 * Uruchomienie:
 *   docker cp testy/segregator-skrzynka-test-uprawnienia.php strona_wp_wordpress:/tmp/u.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/u.php
 * EXIT 0 = wszystkie cztery przypadki jak nalezy, 1 = ktorys nie.
 */

/*
 * Pliki panelu laduja sie tylko przy is_admin(); `wp eval-file` nie jest
 * kontekstem admina, wiec dociagamy je tak, jak robi to wp-admin/admin-post.php,
 * ktory obsluguje akcje `admin_post_*`.
 */
$segm_kat = WP_PLUGIN_DIR . '/segregator-maila/includes/';
foreach ( array( 'csv.php', 'admin.php', 'admin-probka.php', 'admin-skrzynka.php', 'admin-powitanie.php', 'filtry.php', 'konfiguracja.php' ) as $segm_p ) {
	require_once $segm_kat . $segm_p;
}

if ( ! function_exists( 'segm_post_skrzynka_test' ) ) {
	echo "  BLAD srodowisko: wtyczka segregator-maila nie jest zaladowana (brak segm_post_skrzynka_test)\n";
	exit( 1 );
}

$GLOBALS['segm_u_bledy'] = 0;

function segm_u_ok( $opis, $szczegol = '' ) {
	printf( "  OK   %s%s\n", $opis, '' !== $szczegol ? "  [$szczegol]" : '' );
}
function segm_u_blad( $opis, $szczegol = '' ) {
	$GLOBALS['segm_u_bledy']++;
	printf( "  BLAD %s%s\n", $opis, '' !== $szczegol ? "  [$szczegol]" : '' );
}

/* wp_die -> wyjatek (zamiast ubicia procesu) */
add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $msg ) {
			throw new Exception( 'WP_DIE:' . wp_strip_all_tags( is_wp_error( $msg ) ? $msg->get_error_message() : (string) $msg ) );
		};
	}
);
/* przekierowanie -> wyjatek (segm_skrzynka_wroc() konczy sie exit) */
add_filter(
	'wp_redirect',
	static function ( $adres ) {
		throw new Exception( 'PRZEKIEROWANIE:' . $adres );
	},
	1
);

/**
 * Odpala akcje panelu jako podany uzytkownik, z JEGO WLASNYM waznym nonce.
 *
 * Nonce jest liczony dla tego konta — dokladnie taki, jaki dostalby ten
 * uzytkownik, gdyby formularz mu sie wyswietlil. Test NIE podrabia nonce'a:
 * sprawdza wylacznie, czy poza nonce'em stoi jeszcze JAKAKOLWIEK autoryzacja.
 *
 * @return array{wynik: string, tresc: string}
 */
function segm_u_odpal( $uid, $handler, $akcja ) {
	wp_set_current_user( $uid );
	$_REQUEST = array();
	$_POST    = array();
	$_GET     = array();
	$_REQUEST['_wpnonce'] = wp_create_nonce( $akcja );
	$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	$_REQUEST['tryb']     = 'na_sucho';
	$_POST['tryb']        = 'na_sucho';
	try {
		$handler();
	} catch ( Exception $e ) {
		$t = $e->getMessage();
		if ( 0 === strpos( $t, 'WP_DIE:' ) ) {
			return array( 'wynik' => 'odmowa', 'tresc' => substr( $t, 7 ) );
		}
		return array( 'wynik' => 'wykonano', 'tresc' => $t );
	}
	return array( 'wynik' => 'wykonano', 'tresc' => '(handler wrocil bez przekierowania)' );
}

/* --- konta na czas testu --- */
$konta = array();
foreach ( array( 'subscriber' => 'segm_audyt_sub', 'editor' => 'segm_audyt_red' ) as $rola => $login ) {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'user_email' => $login . '@audyt.test', 'role' => $rola ) );
		if ( is_wp_error( $id ) ) {
			echo "  BLAD srodowisko: nie udalo sie zalozyc konta $login: " . $id->get_error_message() . "\n";
			exit( 1 );
		}
	} else {
		$id = $u->ID;
		$u->set_role( $rola );
	}
	$konta[ $rola ] = (int) $id;
}
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $admin ) ) {
	echo "  BLAD srodowisko: brak konta administratora do przypadku BEZ wady\n";
	exit( 1 );
}
$konta['administrator'] = (int) $admin[0];

echo "=== AUDYT: para nonce+uprawnienia na akcjach panelu Segregatora ===\n";
printf( "  konta: subskrybent=%d, redaktor=%d, administrator=%d\n\n", $konta['subscriber'], $konta['editor'], $konta['administrator'] );

/* 1 + 2 — konto bez uprawnien probuje `segm_skrzynka_test` */
foreach ( array( 'subscriber' => 'subskrybent', 'editor' => 'redaktor' ) as $rola => $nazwa ) {
	delete_option( 'segm_ostatni_raport_cache' );
	$r      = segm_u_odpal( $konta[ $rola ], 'segm_post_skrzynka_test', 'segm_skrzynka_test' );
	$zapis  = false !== get_option( 'segm_ostatni_raport_cache', false );
	$opis   = "$nazwa NIE moze wywolac akcji `segm_skrzynka_test`";
	if ( 'odmowa' === $r['wynik'] ) {
		segm_u_ok( $opis, 'odmowa: ' . $r['tresc'] );
	} else {
		segm_u_blad( $opis, 'akcja WYKONANA -> ' . $r['tresc'] . ( $zapis ? ' | opcja segm_ostatni_raport_cache ZAPISANA przez konto bez uprawnien' : '' ) );
	}
	delete_option( 'segm_ostatni_raport_cache' );
	delete_transient( 'segm_komunikat_' . $konta[ $rola ] );
}

/* 3 — przypadek BEZ wady: administrator MA moc uzyc akcji */
$r = segm_u_odpal( $konta['administrator'], 'segm_post_skrzynka_test', 'segm_skrzynka_test' );
if ( 'odmowa' === $r['wynik'] ) {
	segm_u_blad( 'administrator MOZE uzyc akcji `segm_skrzynka_test`', 'zablokowany: ' . $r['tresc'] );
} else {
	segm_u_ok( 'administrator MOZE uzyc akcji `segm_skrzynka_test`', $r['tresc'] );
}
delete_transient( 'segm_komunikat_' . $konta['administrator'] );

/* 4 — kontrola metody na akcji, ktora PARE ma */
$r = segm_u_odpal( $konta['subscriber'], 'segm_post_skrzynka_tryb', 'segm_skrzynka_tryb' );
if ( 'odmowa' === $r['wynik'] ) {
	segm_u_ok( 'kontrola metody: `segm_skrzynka_tryb` (z para) odmawia subskrybentowi', 'odmowa: ' . $r['tresc'] );
} else {
	segm_u_blad( 'kontrola metody: `segm_skrzynka_tryb` (z para) odmawia subskrybentowi', 'nie odmowil -> ' . $r['tresc'] );
}

/* sprzatanie */
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( 'subscriber', 'editor' ) as $rola ) {
	wp_delete_user( $konta[ $rola ] );
	delete_transient( 'segm_komunikat_' . $konta[ $rola ] );
}
delete_option( 'segm_ostatni_raport_cache' );

echo "\n";
if ( 0 === $GLOBALS['segm_u_bledy'] ) {
	echo "WSZYSTKIE KROKI PRZESZLY — kazda badana mutacja panelu ma PARE nonce+uprawnienia\n";
	exit( 0 );
}
printf( "PRZYPADKOW NIEZALICZONYCH: %d\n", $GLOBALS['segm_u_bledy'] );
exit( 1 );
