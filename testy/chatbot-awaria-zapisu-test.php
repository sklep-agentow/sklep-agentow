<?php
/**
 * ChatBot LIVE — „skąd kod wie, że się udało" (klasa A2, paczka C1).
 *
 * PO CO: audyt A2 (audyt/boty/RAPORT-A2-AWARIA.md) pokazał, że przy odrzuconym
 * zapisie do bazy ChatBot ZAKŁADAŁ sukces: panel mówił „zapisano" (A2-F1),
 * przejęcie rozmowy zwracało true i gość czytał „dołączył konsultant" (A2-F2),
 * zapis klucza API nie miał wyniku (A2-F3), a znaczniki awarii same padały po
 * cichu (A2-F4). Zamrożony `audyt/boty/testy-audytu/test-awaria-zapisu.php`
 * mierzy trzy egzemplarze i nadpisuje opcje na stosie — ten strażnik mierzy
 * CAŁĄ KLASĘ w ChatBocie (rodzeństwo z przejecie.php, wołający w panelu,
 * znaczniki F4) i SPRZĄTA po sobie (opcje z migawki, własne wiersze kasowane).
 *
 * Awaria jest PRAWDZIWA: filtr `query` rdzenia podmienia wskazane zapytanie na
 * takie, które MariaDB odrzuca — kod wtyczki wykonuje się w całości.
 *
 * Każdy przypadek ma bliźniaka BEZ WADY (działająca baza → operacja przechodzi),
 * żeby naprawa nie odebrała funkcji wszystkim. Nie dotyka opcji Segregatora.
 *
 * Kalibracja (opis PR C1): na main sprzed naprawy EXIT=1, po naprawie EXIT=0.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-awaria-zapisu-test.php strona_wp_wordpress:/tmp/caz.php
 *   docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/caz.php; echo EXIT=$?
 */

global $wpdb;

// Panel i operator ładują się pod is_admin(); WP-CLI nie jest adminem.
foreach ( array( 'admin.php', 'operator.php' ) as $plik ) {
	require_once WP_PLUGIN_DIR . '/chatbot-live/includes/' . $plik;
}
$wpdb->suppress_errors( true );
wp_set_current_user( 1 );

$GLOBALS['caz_bledy'] = 0;
function caz( $ok, $opis, $szczegol = '' ) {
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? ' (' . $szczegol . ')' : '' );
	if ( ! $ok ) {
		++$GLOBALS['caz_bledy'];
	}
}

/* ───────── MIGAWKA + SPRZĄTANIE (także po błędzie krytycznym) ───────── */
$caz_opcje = array( 'chatl_ustawienia', 'chatl_klucz_api', 'chatl_blad_bazy', 'chatl_blad_ai', 'chatl_blad_maila', 'chatl_rodo_sesje_wspolne' );
$GLOBALS['caz_migawka'] = array();
foreach ( $caz_opcje as $o ) {
	// Wartość I autoload: samo update_option() odtworzyłoby opcję z innym autoloadem
	// niż miała (klucz API ma autoload=off) — migawka ma wracać co do kolumny.
	$GLOBALS['caz_migawka'][ $o ] = array(
		'wartosc'  => get_option( $o, null ),
		'autoload' => $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $o ) ), // phpcs:ignore
	);
}
$GLOBALS['caz_sesje']  = array();
$GLOBALS['caz_emaile'] = array( 'c1awaria-a@example.test', 'c1awaria-b@example.test' );
$GLOBALS['caz_filtry'] = array();
register_shutdown_function(
	function () {
		global $wpdb;
		foreach ( $GLOBALS['caz_filtry'] as $f ) {
			remove_filter( 'query', $f, 999 );
		}
		foreach ( $GLOBALS['caz_migawka'] as $o => $m ) {
			if ( null === $m['wartosc'] ) {
				delete_option( $o );
				continue;
			}
			update_option( $o, $m['wartosc'] );
			if ( null !== $m['autoload'] ) {
				$wpdb->update( $wpdb->options, array( 'autoload' => $m['autoload'] ), array( 'option_name' => $o ), array( '%s' ), array( '%s' ) ); // phpcs:ignore
			}
		}
		$ts = $wpdb->prefix . 'chatl_sesje';
		$tr = $wpdb->prefix . 'chatl_rozmowy';
		$tz = $wpdb->prefix . 'chatl_zgloszenia';
		foreach ( array_unique( $GLOBALS['caz_sesje'] ) as $s ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$ts} WHERE sesja = %s", $s ) ); // phpcs:ignore
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja = %s", $s ) ); // phpcs:ignore
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE sesja = %s", $s ) ); // phpcs:ignore
		}
		foreach ( $GLOBALS['caz_emaile'] as $e ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email = %s", $e ) ); // phpcs:ignore
		}
		wp_cache_flush();
	}
);

/* ───────── PRZYRZĄD: prawdziwa awaria wskazanego zapytania ───────── */
/** Psuje UPDATE/INSERT zawierające igłę. Zwraca filtr do zdjęcia przez odpsuj(). */
function psuj( $igla, $op = 'UPDATE' ) {
	$f = function ( $q ) use ( $igla, $op ) {
		if ( false !== stripos( $q, $igla ) && 0 === stripos( ltrim( $q ), $op ) ) {
			return 'INSERT' === $op
				? 'INSERT INTO tabela_ktorej_nie_ma (x) VALUES (1)'
				: 'UPDATE ' . $GLOBALS['wpdb']->options . ' SET option_value = 1 WHERE option_id = (SELECT 1 FROM tabela_ktorej_nie_ma)';
		}
		return $q;
	};
	add_filter( 'query', $f, 999 );
	$GLOBALS['caz_filtry'][] = $f;
	return $f;
}
function odpsuj( $f ) {
	remove_filter( 'query', $f, 999 );
}
/** Uruchamia handler admin-post i zwraca adres przekierowania (albo '(brak)'). */
function dokad( $fn ) {
	$p = function ( $l ) {
		throw new Exception( 'R:' . $l );
	};
	add_filter( 'wp_redirect', $p, 1 );
	try {
		$fn();
		$w = '(brak przekierowania)';
	} catch ( Exception $e ) {
		$w = substr( $e->getMessage(), 2 );
	}
	remove_filter( 'wp_redirect', $p, 1 );
	return $w;
}
function ostatni_blad_funkcja() {
	$b = get_option( 'chatl_blad_bazy' );
	return is_array( $b ) && isset( $b['funkcja'] ) ? (string) $b['funkcja'] : '';
}
function stan_sesji( $s ) {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}chatl_sesje WHERE sesja = %s", $s ) ); // phpcs:ignore
}
function ile_system( $s, $fragment ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja = %s AND rola = 'system' AND tresc LIKE %s", $s, '%' . $wpdb->esc_like( $fragment ) . '%' ) ); // phpcs:ignore
}

echo "=== A. chatl_zapisz_opcje(): wynik MIERZONY odczytem ===\n";
caz( function_exists( 'chatl_zapisz_opcje' ), 'funkcja chatl_zapisz_opcje() istnieje (bliźniak segm_zapisz_opcje)' );
if ( function_exists( 'chatl_zapisz_opcje' ) ) {
	delete_option( 'chatl_blad_maila' );
	caz( true === chatl_zapisz_opcje( 'chatl_blad_maila', array( 'kiedy' => 'x', 'adres' => 'a@b' ), false ), 'BEZ WADY: zapis nowej wartości → true' );
	caz( true === chatl_zapisz_opcje( 'chatl_blad_maila', array( 'kiedy' => 'x', 'adres' => 'a@b' ), false ), 'BEZ WADY: ta sama wartość drugi raz → true (identyczna ≠ porażka)' );
	$f = psuj( 'chatl_blad_maila' );
	$r = chatl_zapisz_opcje( 'chatl_blad_maila', array( 'kiedy' => 'y', 'adres' => 'a@b' ), false );
	odpsuj( $f );
	caz( false === $r, 'AWARIA: odrzucony UPDATE → false', var_export( $r, true ) );
}

echo "\n=== B. A2-F1: handler ustawień — „zapisano” tylko po zmierzonym zapisie ===\n";
$ust = chatl_ustawienia();
update_option( 'chatl_ustawienia', array_merge( $ust, array( 'ai_limit_dzienny' => 17 ) ) );
$_POST = array( 'wlaczony' => 1, 'ai_limit_dzienny' => 4242 );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
$f = psuj( 'chatl_ustawienia' );
$adres = dokad( 'chatl_post_ustawienia_zapisz' );
odpsuj( $f );
wp_cache_flush();
$limit = (int) chatl_ustawienia()['ai_limit_dzienny'];
caz( false === strpos( $adres, 'chatl_ok=zapisano' ), 'AWARIA: brak „zapisano" w przekierowaniu', $adres );
caz( false !== strpos( $adres, 'chatl_blad=' ), 'AWARIA: właściciel dostaje komunikat błędu (chatl_blad)' );
caz( 17 === $limit, 'AWARIA: w bazie został POPRZEDNI limit (17)', (string) $limit );
caz( 'chatl_post_ustawienia_zapisz' === ostatni_blad_funkcja(), 'AWARIA: trwały ślad błędu bazy wskazuje handler', ostatni_blad_funkcja() );

$_POST = array( 'wlaczony' => 1, 'ai_limit_dzienny' => 4242 );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
$adres = dokad( 'chatl_post_ustawienia_zapisz' );
wp_cache_flush();
caz( false !== strpos( $adres, 'chatl_ok=zapisano' ), 'BEZ WADY: działająca baza → „zapisano"', $adres );
caz( 4242 === (int) chatl_ustawienia()['ai_limit_dzienny'], 'BEZ WADY: nowy limit w bazie (4242)' );

echo "\n=== C. A2-F3: zapis klucza API ma wynik, a handler na niego reaguje ===\n";
if ( chatl_klucz_ze_stalej() ) {
	echo "  (pominięto: klucz ze stałej CHATL_KLUCZ_API — panel go nie zapisuje)\n";
} else {
	update_option( 'chatl_klucz_api', 'c1-STARY' );
	$f = psuj( 'chatl_klucz_api' );
	$r = chatl_zapisz_klucz( 'c1-NOWY' );
	odpsuj( $f );
	wp_cache_flush();
	caz( false === $r, 'AWARIA: chatl_zapisz_klucz() → false', var_export( $r, true ) );
	caz( 'c1-STARY' === get_option( 'chatl_klucz_api' ), 'AWARIA: w bazie został stary klucz' );

	$_POST = array( 'wlaczony' => 1, 'klucz_api' => 'c1-NOWY-Z-PANELU' );
	$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_ustawienia_zapisz' );
	$f = psuj( 'chatl_klucz_api' );
	$adres = dokad( 'chatl_post_ustawienia_zapisz' );
	odpsuj( $f );
	wp_cache_flush();
	caz( false === strpos( $adres, 'chatl_ok=zapisano' ) && false !== stripos( rawurldecode( $adres ), 'klucz' ), 'AWARIA: handler mówi o NIEZAPISANYM kluczu zamiast „zapisano"', rawurldecode( $adres ) );

	$r = chatl_zapisz_klucz( 'c1-NOWY' );
	caz( true === $r && 'c1-NOWY' === get_option( 'chatl_klucz_api' ), 'BEZ WADY: działająca baza → true i nowy klucz w bazie' );
	caz( true === chatl_zapisz_klucz( '' ) && '' === (string) get_option( 'chatl_klucz_api', '' ), 'BEZ WADY: kasowanie klucza → true i pusto' );
}

echo "\n=== D. A2-F2: przejęcie i rodzeństwo w przejecie.php — stan ODCZYTANY po zapisie ===\n";
$s = chatl_nowa_sesja();
$GLOBALS['caz_sesje'][] = $s;
caz( 32 === strlen( $s ) && 'bot' === stan_sesji( $s ), 'BEZ WADY: chatl_nowa_sesja() zakłada wiersz (stan bot)' );

$f = psuj( 'chatl_sesje' );
$r = chatl_przejmij( $s, 1 );
odpsuj( $f );
caz( false === $r, 'AWARIA: chatl_przejmij() → false', var_export( $r, true ) );
caz( 'bot' === stan_sesji( $s ), 'AWARIA: rozmowa została przy bocie' );
caz( 0 === ile_system( $s, 'konsultant' ), 'AWARIA: gość NIE dostał „dołączył konsultant"', (string) ile_system( $s, 'konsultant' ) );
caz( 'chatl_przejmij' === ostatni_blad_funkcja(), 'AWARIA: ślad błędu wskazuje chatl_przejmij', ostatni_blad_funkcja() );

$r = chatl_przejmij( $s, 1 );
caz( true === $r && 'czlowiek' === stan_sesji( $s ), 'BEZ WADY: przejęcie → true i stan czlowiek' );

// Brak wiersza przy ZDROWEJ bazie (sesja skasowana między listą a kliknięciem) ≠ błąd bazy.
delete_option( 'chatl_blad_bazy' );
$r  = chatl_przejmij( 'c1AwariaSesjaKtorejNieMa00000000', 1 );
$r2 = chatl_oddaj_botowi( 'c1AwariaSesjaKtorejNieMa00000000', '' );
caz( false === $r && false === $r2 && '' === ostatni_blad_funkcja(), 'BEZ WADY: przejęcie/oddanie NIEISTNIEJĄCEJ sesji → false BEZ fałszywego alarmu błędu bazy', ostatni_blad_funkcja() );
caz( 1 === ile_system( $s, 'konsultant' ), 'BEZ WADY: gość dostał komunikat o konsultancie raz' );

$f = psuj( 'chatl_sesje' );
$r = chatl_oddaj_botowi( $s, 'Konsultant zakończył rozmowę — dalej pomaga bot.' );
odpsuj( $f );
caz( false === $r, 'AWARIA: chatl_oddaj_botowi() → false', var_export( $r, true ) );
caz( 'czlowiek' === stan_sesji( $s ), 'AWARIA: rozmowa została przy człowieku' );
caz( 0 === ile_system( $s, 'zakończył' ), 'AWARIA: gość NIE dostał „zakończył rozmowę"' );

$f = psuj( 'chatl_sesje' );
$id = chatl_operator_wiadomosc( $s, 'c1 wiadomość operatora przy padniętym znaczniku' );
odpsuj( $f );
caz( $id > 0 && 'chatl_operator_wiadomosc' === ostatni_blad_funkcja(), 'AWARIA: wiadomość zapisana (id>0), a padnięty znacznik aktywności zostawia ślad', 'id=' . $id . ' funkcja=' . ostatni_blad_funkcja() );
$f = psuj( 'chatl_rozmowy', 'INSERT' );
$id = chatl_operator_wiadomosc( $s, 'c1 wiadomość, która nie wejdzie' );
odpsuj( $f );
caz( 0 === $id, 'AWARIA: odrzucony INSERT wiadomości → 0 (operator ma to wiedzieć)', (string) $id );

// Cisza operatora: powrót do bota mierzony, nie zakładany.
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}chatl_sesje SET operator_akt = %s, przejeto = %s WHERE sesja = %s", '2000-01-01 00:00:00', '2000-01-01 00:00:00', $s ) ); // phpcs:ignore
$f = psuj( 'chatl_sesje' );
$stan = chatl_stan_rozmowy( $s );
odpsuj( $f );
caz( 'czlowiek' === $stan && 'czlowiek' === stan_sesji( $s ), 'AWARIA: po ciszy operatora przy padniętym zapisie stan = to, co w bazie (czlowiek)', $stan );
$stan = chatl_stan_rozmowy( $s );
caz( 'bot' === $stan && 'bot' === stan_sesji( $s ), 'BEZ WADY: po ciszy operatora rozmowa wraca do bota', $stan );
caz( 1 === ile_system( $s, 'nie odpowiada' ), 'BEZ WADY: gość dostał komunikat o powrocie bota raz' );

$r = chatl_oddaj_botowi( $s, '' );
caz( true === $r && 'bot' === stan_sesji( $s ), 'BEZ WADY: oddanie botowi → true' );

$f = psuj( 'chatl_sesje', 'INSERT' );
$pusta = chatl_nowa_sesja();
odpsuj( $f );
caz( '' === $pusta, 'AWARIA: chatl_nowa_sesja() przy odrzuconym INSERT → "" (bez sesji widmo)', var_export( $pusta, true ) );
caz( 'chatl_nowa_sesja' === ostatni_blad_funkcja(), 'AWARIA: ślad błędu wskazuje chatl_nowa_sesja', ostatni_blad_funkcja() );

echo "\n=== E. Wołający w panelu operatora reagują na wynik ===\n";
$_GET = array( 'sesja' => $s );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'] = wp_create_nonce( 'chatl_przejecie' );
$f = psuj( 'chatl_sesje' );
$adres = dokad( 'chatl_post_przejmij' );
odpsuj( $f );
caz( false === strpos( $adres, 'chatl_ok=przejeta' ) && false !== strpos( $adres, 'chatl_blad=' ), 'AWARIA: „Przejmij rozmowę" → komunikat błędu, nie „przejęta"', $adres );
$adres = dokad( 'chatl_post_przejmij' );
caz( false !== strpos( $adres, 'chatl_ok=przejeta' ) && 'czlowiek' === stan_sesji( $s ), 'BEZ WADY: „Przejmij rozmowę" → przejęta', $adres );

$_POST = array( 'sesja' => $s, 'tresc' => 'c1 odpowiedź z panelu' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'chatl_przejecie' );
$f = psuj( 'chatl_rozmowy', 'INSERT' );
$adres = dokad( 'chatl_post_operator_wyslij' );
odpsuj( $f );
caz( false !== strpos( $adres, 'chatl_blad=' ), 'AWARIA: wysyłka wiadomości z panelu przy odrzuconym INSERT → komunikat błędu', $adres );
$adres = dokad( 'chatl_post_operator_wyslij' );
caz( false === strpos( $adres, 'chatl_blad=' ), 'BEZ WADY: wysyłka wiadomości z panelu przechodzi', $adres );

$_GET = array( 'sesja' => $s );
$_REQUEST['_wpnonce'] = $_GET['_wpnonce'] = wp_create_nonce( 'chatl_przejecie' );
$f = psuj( 'chatl_sesje' );
$adres = dokad( 'chatl_post_zakoncz' );
odpsuj( $f );
caz( false === strpos( $adres, 'chatl_ok=oddana' ) && false !== strpos( $adres, 'chatl_blad=' ), 'AWARIA: „Zakończ" → komunikat błędu, nie „oddana"', $adres );
$adres = dokad( 'chatl_post_zakoncz' );
caz( false !== strpos( $adres, 'chatl_ok=oddana' ) && 'bot' === stan_sesji( $s ), 'BEZ WADY: „Zakończ" → oddana', $adres );

echo "\n=== F. A2-F4: znaczniki awarii, które same nie mogą paść po cichu ===\n";
delete_option( 'chatl_blad_ai' );
$f = psuj( 'chatl_blad_ai' );
$f2 = psuj( 'chatl_blad_ai', 'INSERT' );
chatl_odnotuj_blad_ai( new WP_Error( 'c1', 'próba c1' ), 0 );
odpsuj( $f );
odpsuj( $f2 );
caz( 'chatl_odnotuj_blad_ai' === ostatni_blad_funkcja(), 'AWARIA: padnięty znacznik awarii AI zostawia ślad błędu bazy', ostatni_blad_funkcja() );
delete_option( 'chatl_blad_bazy' );
chatl_odnotuj_blad_ai( new WP_Error( 'c1', 'próba c1' ), 0 );
$z = get_option( 'chatl_blad_ai' );
caz( is_array( $z ) && 0 === (int) $z['kod'] && '' === ostatni_blad_funkcja(), 'BEZ WADY: znacznik awarii AI zapisany, bez śladu błędu bazy' );

delete_option( 'chatl_blad_maila' );
$f = psuj( 'chatl_blad_maila' );
$f2 = psuj( 'chatl_blad_maila', 'INSERT' );
chatl_odnotuj_wysylke_powiadomienia( false, 'c1@example.test' );
odpsuj( $f );
odpsuj( $f2 );
caz( 'chatl_odnotuj_wysylke_powiadomienia' === ostatni_blad_funkcja(), 'AWARIA: padnięty znacznik „poczta nie wychodzi" zostawia ślad', ostatni_blad_funkcja() );
delete_option( 'chatl_blad_bazy' );
chatl_odnotuj_wysylke_powiadomienia( false, 'c1@example.test' );
$z = get_option( 'chatl_blad_maila' );
caz( is_array( $z ) && 'c1@example.test' === $z['adres'] && '' === ostatni_blad_funkcja(), 'BEZ WADY: znacznik poczty zapisany, bez śladu błędu bazy' );

// Ślad RODO: sesja współdzielona przez dwa adresy, z wiadomością (żeby nie wypadła przy przycinaniu).
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$sw = 'c1AwariaSesjaWspolna00000000000' . wp_generate_password( 1, false );
$GLOBALS['caz_sesje'][] = $sw;
foreach ( $GLOBALS['caz_emaile'] as $e ) {
	$wpdb->insert( $tz, array( 'email' => $e, 'wiadomosc' => 'c1', 'sesja' => $sw, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
}
chatl_zapisz_wymiane( $sw, 'gosc', 'c1 wiadomość we wspólnej sesji' );
update_option( 'chatl_rodo_sesje_wspolne', array(), false );
delete_option( 'chatl_blad_bazy' );
$f = psuj( 'chatl_rodo_sesje_wspolne' );
$r = chatl_rodo_zapamietaj_wspolne( array( $sw ) );
odpsuj( $f );
caz( false === $r && 'chatl_rodo_zapamietaj_wspolne' === ostatni_blad_funkcja(), 'AWARIA: ślad RODO → false + ślad błędu', var_export( $r, true ) );
$f = psuj( 'chatl_rodo_sesje_wspolne' );
$kas = chatl_rodo_kasownik( 'c1awaria-a@example.test', 1 );
odpsuj( $f );
$ile_a = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", 'c1awaria-a@example.test' ) ); // phpcs:ignore
caz( false === $kas['items_removed'] && 1 === $ile_a && false !== strpos( implode( ' ', $kas['messages'] ), 'NIC nie zostało usunięte' ), 'AWARIA: kasownik NIE kasuje bez śladu i mówi to wprost', implode( ' | ', $kas['messages'] ) );
$r = chatl_rodo_zapamietaj_wspolne( array( $sw ) );
caz( true === $r && in_array( $sw, chatl_rodo_slad_wspolnych(), true ), 'BEZ WADY: ślad RODO zapisany → true' );
$kas = chatl_rodo_kasownik( 'c1awaria-a@example.test', 1 );
$ile_a = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", 'c1awaria-a@example.test' ) ); // phpcs:ignore
caz( true === $kas['items_removed'] && 0 === $ile_a, 'BEZ WADY: kasownik kasuje zgłoszenie wnioskodawcy', implode( ' | ', $kas['messages'] ) );

echo "\n" . ( $GLOBALS['caz_bledy'] > 0 ? 'BLEDOW: ' . $GLOBALS['caz_bledy'] : 'WSZYSTKO OK' ) . "\n";
exit( $GLOBALS['caz_bledy'] > 0 ? 1 : 0 );
