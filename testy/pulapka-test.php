<?php
/**
 * Pułapka na boty — test na PRAWDZIWEJ bazie i prawdziwym kodzie wtyczki.
 *
 * Sedno decyzji produktowej, którą ten test pilnuje: złapane zgłoszenie
 * **trafia do bazy z flagą**, a nie do kosza. Cisza dotyczy BOTA (odpowiedź jest
 * nieodróżnialna od udanej), nie bazy — bo fałszywy alarm w wersji „cicho
 * wyrzuć" jest niewidzialny dla obu stron, a ten produkt istnieje po to, żeby
 * zbierać kontakty.
 *
 * Uruchomienie:
 *   docker cp testy/pulapka-test.php strona_wp_wordpress:/tmp/pulapka-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/pulapka-test.php
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

$katalog = WP_PLUGIN_DIR . '/chatbot-live/includes/';
require_once $katalog . 'silnik.php';
require_once $katalog . 'front.php';

$GLOBALS['chatl_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol && ! $warunek ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}

function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

global $wpdb;
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$ts = $wpdb->prefix . 'chatl_sesje';

$sesja_stara = 'pulapkaTestStaraAAAAAAAAAAAAAAAA';
$sesja_swieza = 'pulapkaTestSwiezaBBBBBBBBBBBBBBB';

function chatl_test_sprzataj() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email LIKE 'pulapka-test%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_sesje WHERE sesja LIKE 'pulapkaTest%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj IN ('pulapka','nonce')" ); // phpcs:ignore
	/*
	 * Czyscimy WLASNE kuble limitu. Powod jest merytoryczny, nie kosmetyczny:
	 * kazdy przebieg wysyla dwa zgloszenia, a proba kontrolna odpala test
	 * dziewiec razy pod rzad — bez tego dziewiaty przebieg dostaje 429 i "czysty
	 * kod nie przechodzi", czyli proba mowilaby o wadzie, ktorej nie ma.
	 */
	$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE 'chat|%' OR klucz LIKE 'pulapka|%' OR klucz LIKE 'ai|%' OR klucz LIKE 'nonce-odrzucony|%'" ); // phpcs:ignore
}
chatl_test_sprzataj();

/*
 * Powiadomienia mailowe JAWNIE wlaczone na czas testu. Wspolny stos dryfuje:
 * zapis formularza ustawien bez zaznaczonego checkboxa (albo reczna zmiana
 * w panelu) zostawia powiadomienia=0 — a sekcja D3 mierzy REALNA wysylke
 * wp_mail(), wiec bez wlasnego warunku wstepnego test pada niezaleznie od
 * kodu wtyczki (linia bazowa 24.08: 0 maili zamiast 1). Migawka wraca na
 * shutdown — takze po bledzie krytycznym — co do wartosci I autoloadu
 * (wzorzec 1:1 z chatbot-awaria-zapisu-test.php).
 */
$GLOBALS['pt_ust_migawka'] = array(
	'wartosc'  => get_option( 'chatl_ustawienia', null ),
	'autoload' => $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'chatl_ustawienia' ) ), // phpcs:ignore
);
register_shutdown_function(
	function () {
		global $wpdb;
		$m = $GLOBALS['pt_ust_migawka'];
		if ( null === $m['wartosc'] ) {
			delete_option( 'chatl_ustawienia' );
		} else {
			update_option( 'chatl_ustawienia', $m['wartosc'] );
			if ( null !== $m['autoload'] ) {
				$wpdb->update( $wpdb->options, array( 'autoload' => $m['autoload'] ), array( 'option_name' => 'chatl_ustawienia' ), array( '%s' ), array( '%s' ) ); // phpcs:ignore
			}
		}
		wp_cache_flush();
	}
);
$pt_ust                     = chatl_ustawienia();
$pt_ust['powiadomienia']    = 1;
$pt_ust['mail_powiadomien'] = 'pulapka-test-wlasciciel@example.test';
update_option( 'chatl_ustawienia', $pt_ust );
wp_cache_flush();

// Sesja "stara": wydana 60 s temu — czlowiek zdazyl przeczytac i napisac.
$wpdb->insert( $ts, array( 'sesja' => $sesja_stara, 'stan' => 'bot', 'klucz_hash' => wp_hash( 'pt' ), 'utworzono' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore
// Sesja "swieza": wydana przed chwila — zadanie w tej samej sekundzie to nie czlowiek.
$wpdb->insert( $ts, array( 'sesja' => $sesja_swieza, 'stan' => 'bot', 'klucz_hash' => wp_hash( 'pt' ), 'utworzono' => current_time( 'mysql' ), 'aktywnosc' => current_time( 'mysql' ) ) ); // phpcs:ignore

echo "=== A. Wykrywanie: ukryte pole ===\n";
$_POST = array();
rowne( 'czyste zadanie ze starej sesji NIE jest lapane', '', chatl_pulapka_zlapala( $sesja_stara ) );

$_POST[ CHATL_PULAPKA_POLE ] = 'https://spam.example';
rowne( 'wypelnione ukryte pole = zlapane', 'pole', chatl_pulapka_zlapala( $sesja_stara ) );

$_POST[ CHATL_PULAPKA_POLE ] = '';
rowne( 'PUSTE ukryte pole to nie jest zlapanie (widget wysyla je zawsze)', '', chatl_pulapka_zlapala( $sesja_stara ) );

echo "\n=== B. Wykrywanie: prog czasu liczony PO STRONIE SERWERA ===\n";
$_POST = array();
rowne( 'zadanie tuz po wydaniu sesji = zlapane', 'czas', chatl_pulapka_zlapala( $sesja_swieza ) );
rowne( 'nieznana sesja NIE jest lapana (mogla wygasnac)', '', chatl_pulapka_zlapala( 'pulapkaTestNieMaTakiej000000000' ) );
rowne( 'prog czasu da sie WYLACZYC dla wybranej sciezki', '', chatl_pulapka_zlapala( $sesja_swieza, false ) );

echo "\n=== C. Licznik czyni pulapke widoczna w panelu ===\n";
/*
 * Od schematu 8 zdarzenia leza w tabeli chatl_zdarzenia (wiersz na zdarzenie),
 * a nie w opcji chatl_pulapka: licznik w wp_options gubil rownolegle zapisy
 * i rozjezdzal sie z lista zlapanych zgloszen (DK-1, DK-5). Licznik = COUNT(*),
 * a rozbicie po sciezce mowi, ilu zlapanych wlasciciel ZNAJDZIE na liscie.
 */
$wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = 'pulapka'" ); // phpcs:ignore
chatl_odnotuj_pulapke( 'pole', 'zgloszenie' );
chatl_odnotuj_pulapke( 'czas', 'pytanie' );
$stan = chatl_zdarzenia_stan( 'pulapka' );
rowne( 'licznik zlicza kazde zadzialanie', 2, (int) $stan['ile'] );
sprawdz( 'i zapamietuje powod ostatniego', 'zbyt szybkie żądanie' === $stan['szczegol'], (string) $stan['szczegol'] );
sprawdz( 'oraz kiedy', '' !== $stan['ostatnia'] );
rowne( 'oraz KTOREDY przyszlo (zgloszenie)', 1, (int) ( $stan['sciezki']['zgloszenie'] ?? 0 ) );
rowne( 'oraz KTOREDY przyszlo (pytanie)', 1, (int) ( $stan['sciezki']['pytanie'] ?? 0 ) );
sprawdz( 'stara opcja chatl_pulapka NIE jest juz uzywana', false === get_option( 'chatl_pulapka' ) );

echo "\n=== D. SEDNO: zlapane zgloszenie TRAFIA DO BAZY, z flaga ===\n";
/*
 * ⛔ Ta sekcja wola PRAWDZIWY endpoint, a nie kopie jego logiki. Pierwsza wersja
 * miala wlasny helper powtarzajacy INSERT — proba kontrolna to obnazyla: mutant
 * "flaga nie jest stawiana" nie zostal zlapany, bo test sprawdzal SWOJ kod,
 * nie kod wtyczki. Test, ktory powtarza implementacje, dowodzi tylko tego,
 * ze umiem ja przepisac.
 *
 * wp_send_json_* konczy sie wp_die(), wiec podmieniamy uchwyt na wyjatek
 * i lapiemy wyjscie buforem — to jest podrecznikowy sposob wolania handlera
 * AJAX bez HTTP.
 */
class Chatl_Test_Wyjscie extends Exception {}

function chatl_test_uchwyt_die() {
	return function () {
		throw new Chatl_Test_Wyjscie();
	};
}

/**
 * Wola endpoint zgloszenia tak, jak zrobilby to widget.
 *
 * @param string $email        Adres.
 * @param bool   $wypelnij_pulapke Czy wypelnic ukryte pole (czyli udawac bota).
 * @param string $sesja        Sesja.
 * @return array Odkodowana odpowiedz JSON.
 */
function chatl_test_zgloszenie( $email, $wypelnij_pulapke, $sesja ) {
	$_POST = array(
		'nonce'     => wp_create_nonce( 'chatl_front' ),
		'sesja'     => $sesja,
		'email'     => $email,
		'wiadomosc' => 'tresc testowa',
		'zgoda'     => '1',
	);
	$_POST[ CHATL_PULAPKA_POLE ] = $wypelnij_pulapke ? 'https://spam.example' : '';
	$_REQUEST                    = $_POST;

	/*
	 * Bez tego wp_send_json() poza kontekstem AJAX wola ZWYKLE die(), ktorego
	 * zaden filtr nie przechwyci — proces konczylby sie na pierwszym wywolaniu.
	 * wp_doing_ajax() przepuszcza swoj wynik przez filtr, wiec tedy.
	 */
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', 'chatl_test_uchwyt_die' );
	add_filter( 'wp_die_handler', 'chatl_test_uchwyt_die' );
	ob_start();
	try {
		chatl_ajax_zgloszenie();
	} catch ( Chatl_Test_Wyjscie $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// Spodziewane: wp_send_json_* zawsze konczy zadanie.
	}
	$wyjscie = ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', 'chatl_test_uchwyt_die' );
	remove_filter( 'wp_die_handler', 'chatl_test_uchwyt_die' );
	return json_decode( $wyjscie, true );
}

// Poczta idzie do lapacza, nie w swiat — i sprawdzamy, CZY w ogole wyszla.
$GLOBALS['chatl_test_maile'] = 0;
add_filter(
	'pre_wp_mail',
	function () {
		++$GLOBALS['chatl_test_maile'];
		return true;
	}
);

sprawdz( 'kolumna podejrzane istnieje w schemacie', (bool) $wpdb->get_var( "SHOW COLUMNS FROM {$tz} LIKE 'podejrzane'" ) ); // phpcs:ignore

$odp_czysta = chatl_test_zgloszenie( 'pulapka-test-czysty@example.test', false, $sesja_stara );
$maile_po_czystym = $GLOBALS['chatl_test_maile'];
$odp_zlapana = chatl_test_zgloszenie( 'pulapka-test-zlapany@example.test', true, $sesja_stara );
$maile_po_zlapanym = $GLOBALS['chatl_test_maile'];

$czysty  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pulapka-test-czysty@example.test' ), ARRAY_A ); // phpcs:ignore
$zlapany = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pulapka-test-zlapany@example.test' ), ARRAY_A ); // phpcs:ignore

sprawdz( 'zlapane zgloszenie JEST w bazie (nie wyrzucone po cichu)', is_array( $zlapany ) );
rowne( 'i ma flage podejrzane = 1', 1, (int) ( $zlapany['podejrzane'] ?? -1 ) );
rowne( 'czyste ma flage 0', 0, (int) ( $czysty['podejrzane'] ?? -1 ) );
rowne( 'tresc zlapanego zachowana w calosci', 'tresc testowa', (string) ( $zlapany['wiadomosc'] ?? '' ) );

echo "\n=== D2. Dla goscia odpowiedz jest NIEODROZNIALNA od udanej ===\n";
sprawdz( 'czyste zgloszenie konczy sie sukcesem', ! empty( $odp_czysta['success'] ) );
sprawdz( 'zlapane TAKZE sukcesem (spamer nie wie, ze wpadl)', ! empty( $odp_zlapana['success'] ) );
rowne(
	'i komunikat jest CO DO ZNAKU ten sam',
	(string) ( $odp_czysta['data']['komunikat'] ?? 'A' ),
	(string) ( $odp_zlapana['data']['komunikat'] ?? 'B' )
);

echo "\n=== D3. WARUNEK 1 zmierzony, nie wyczytany z kodu ===\n";
rowne( 'czyste zgloszenie WYSLALO powiadomienie', 1, $maile_po_czystym );
rowne( 'zlapane NIE wyslalo zadnego (ochrona przed mail-bomba)', 1, $maile_po_zlapanym );

echo "\n=== B2. FORMULARZ KONTAKTU nie stosuje progu czasu ===\n";
/*
 * Znalezione testem przegladarkowym, nie wydedukowane: prawdziwy gosc, ktory
 * otworzy czat i OD RAZU klika "Zostaw wiadomosc", wysyla formularz szybciej niz
 * w dwie sekundy od wydania sesji — i wpadal we wlasna pulapke. Cena tego
 * falszywego alarmu to UTRACONY KONTAKT, czyli jedyna rzecz, dla ktorej ten
 * produkt istnieje. Prog czasu i tak nie lapie bota strzelajacego wprost
 * w admin-ajax.php, bo taki podaje ZMYSLONA sesje (brak wiersza = nie lapiemy).
 */
$odp_szybka = chatl_test_zgloszenie( 'pulapka-test-szybki@example.test', false, $sesja_swieza );
$szybki     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pulapka-test-szybki@example.test' ), ARRAY_A ); // phpcs:ignore
sprawdz( 'zgloszenie ze SWIEZEJ sesji doszlo do bazy', is_array( $szybki ) );
rowne( 'i jest CZYSTE — prog czasu go nie zlapal', 0, (int) ( $szybki['podejrzane'] ?? -1 ) );

$odp_szybka_bot = chatl_test_zgloszenie( 'pulapka-test-szybki-bot@example.test', true, $sesja_swieza );
$szybki_bot     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tz} WHERE email = %s", 'pulapka-test-szybki-bot@example.test' ), ARRAY_A ); // phpcs:ignore
rowne( 'ale UKRYTE POLE lapie tak samo, niezaleznie od wieku sesji', 1, (int) ( $szybki_bot['podejrzane'] ?? -1 ) );
// Czyscimy $_POST: wywolania endpointu wyzej zostawily w nim wypelnione
// pole-pulapke, wiec bez tego sprawdzalibysmy co innego, niz opisuje krok.
$_POST = array();
rowne( 'pusta sesja tez nie', '', chatl_pulapka_zlapala( '' ) );

echo "\n=== E. Lista panelu: domyslnie czyste, podejrzane za filtrem z licznikiem ===\n";
$domyslne   = $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$tz} WHERE podejrzane = %d AND email LIKE 'pulapka-test%%'", 0 ) ); // phpcs:ignore
$za_filtrem = $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$tz} WHERE podejrzane = %d AND email LIKE 'pulapka-test%%'", 1 ) ); // phpcs:ignore
sprawdz( 'widok domyslny NIE pokazuje zlapanego', ! in_array( 'pulapka-test-zlapany@example.test', $domyslne, true ) );
sprawdz( 'ale pokazuje czyste', in_array( 'pulapka-test-czysty@example.test', $domyslne, true ) );
sprawdz( 'widok "podejrzane" pokazuje zlapane', in_array( 'pulapka-test-zlapany@example.test', $za_filtrem, true ) );
sprawdz( 'i NIE miesza tam czystych', ! in_array( 'pulapka-test-czysty@example.test', $za_filtrem, true ) );

echo "\n=== F. Warunki, ktore czynia to bezpiecznym ===\n";
$kod = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/includes/front.php' ); // phpcs:ignore

sprawdz(
	'WARUNEK 1: zlapane NIE wysyla maila do wlasciciela',
	1 === preg_match( "/if \(\s*'' === \\\$pulapka && \\\$ust\['powiadomienia'\]/", $kod ),
	'brak warunku pulapki przy wp_mail'
);
sprawdz(
	'WARUNEK 2: pulapka sprawdzana PRZED limitem ruchu (zlapane nie zjadaja kubla goscia)',
	strpos( $kod, 'chatl_pulapka_zlapala( $sesja_wstepna )' ) < strpos( $kod, 'if ( ! chatl_limit_ip_ok() ) {' ),
	'kolejnosc odwrocona'
);
/*
 * Pierwsza wersja tego kroku porownywala pozycje w CALYM pliku i szukala funkcji
 * chatl_limit_ai_ok, ktora NIE ISTNIEJE (naprawde nazywa sie chatl_limit_ai_ip_ok).
 * strpos zwracal false, porownanie liczylo bzdure — czyli krok "przechodzil"
 * albo "padal" bez zwiazku z kodem. Teraz: wycinamy CIALO endpointu i sprawdzamy
 * kolejnosc w nim, a nazwy funkcji sa najpierw potwierdzane jako istniejace.
 */
$ciało = '';
if ( preg_match( '/function chatl_ajax_pytanie\(.*?\n}/s', $kod, $m ) ) {
	$ciało = $m[0];
}
sprawdz( 'cialo endpointu chatl_ajax_pytanie dalo sie wyciac', '' !== $ciało );
sprawdz( 'obie nazwy funkcji AI naprawde istnieja w kodzie', function_exists( 'chatl_limit_ai_ip_ok' ) && function_exists( 'chatl_ai_odpowiedz' ) );

$poz_pulapki = strpos( $ciało, 'chatl_pulapka_zlapala(' );
$poz_wyjscia = strpos( $ciało, 'wp_send_json_success' );
$poz_ai      = strpos( $ciało, 'chatl_ai_odpowiedz(' );
sprawdz(
	'WARUNEK 2b: zlapane pytanie WYCHODZI z endpointu, zanim kod dojdzie do AI',
	false !== $poz_pulapki && false !== $poz_wyjscia && false !== $poz_ai
		&& $poz_pulapki < $poz_wyjscia && $poz_wyjscia < $poz_ai,
	'pulapka=' . var_export( $poz_pulapki, true ) . ' wyjscie=' . var_export( $poz_wyjscia, true ) . ' ai=' . var_export( $poz_ai, true )
);
sprawdz(
	'i nie zuzywa dobowego budzetu AI (limit wolany dopiero po pulapce)',
	false !== strpos( $ciało, 'chatl_limit_ai_ip_ok' )
		&& $poz_wyjscia < strpos( $ciało, 'chatl_limit_ai_ip_ok' ),
	'limit AI wolany przed wyjsciem pulapki'
);
sprawdz(
	'WARUNEK 3: odpowiedz dla zlapanego jest SUKCESEM, nie bledem',
	false !== strpos( $kod, 'wp_send_json_success' ) && 1 === preg_match( '/chatl_odnotuj_pulapke\( \$pulapka, .pytanie. \);\s*\/\*.*?wp_send_json_success/s', $kod ),
	'zlapany dostaje inna odpowiedz niz udana — skrypt sie dowie'
);
/*
 * Od C3 zlapany powyzej sufitu dostaje ODMOWE (429). To nie lamie WARUNKU 3:
 * odmowa ma DOKLADNIE te sama tresc, co zwykle przekroczenie limitu u uczciwego
 * goscia, wiec skrypt dowiaduje sie tylko, ze pisze za szybko — nie, ze wpadl
 * w pulapke. Gdyby te dwa komunikaty sie rozjechaly, pulapka bylaby wykrywalna.
 */
preg_match_all( "/'komunikat' => 'Za dużo wiadomości[^']*'/u", $kod, $trafienia );
sprawdz(
	'odmowa na sciezce pulapki jest NIEODROZNIALNA od zwyklego limitu',
	count( $trafienia[0] ) >= 3 && 1 === count( array_unique( $trafienia[0] ) ),
	'komunikaty: ' . implode( ' | ', array_unique( $trafienia[0] ) )
);
sprawdz(
	'sufit na zlapane chroni tabele przed zalaniem (osobny kubel, 429 przed zapisem)',
	2 === substr_count( $kod, "chatl_rate_limit( 'pulapka', CHATL_LIMIT_PULAPKA )" ),
	'sufit ma stac na OBU sciezkach zlapanych (pytanie i zgloszenie)'
);
sprawdz(
	'sciezka zlego nonce tez ma kubel (nie jest tansza niz dla goscia)',
	false !== strpos( $kod, "chatl_rate_limit( 'nonce-odrzucony', CHATL_LIMIT_NA_MIN )" ),
	'brak limitu na sciezce odrzuconego nonce'
);

echo "\n=== G. Widget: pole jest w DOM i wysylane zawsze ===\n";
$js = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/assets/widget.js' ); // phpcs:ignore
$css = file_get_contents( WP_PLUGIN_DIR . '/chatbot-live/assets/widget.css' ); // phpcs:ignore
sprawdz( 'widget tworzy pole-pulapke', false !== strpos( $js, "polePulapki.name = 'chatl_pole_dodatkowe'" ) );
sprawdz( 'i wysyla je w kazdym zadaniu', false !== strpos( $js, "body.append('chatl_pole_dodatkowe'" ) );
sprawdz( 'pole jest poza zasiegiem tabulatora', false !== strpos( $js, 'polePulapki.tabIndex = -1' ) );
sprawdz( 'i pomijane przez czytnik ekranu', false !== strpos( $js, "polePulapki.setAttribute('aria-hidden', 'true')" ) );
sprawdz( 'autouzupelnianie wylaczone (menedzer hasel nie wypelni)', false !== strpos( $js, "polePulapki.autocomplete = 'off'" ) );
sprawdz( 'CSS wynosi pole poza kadr, a NIE display:none', false !== strpos( $css, 'left: -9999px !important' ) );
sprawdz( 'i nie chowa go przez display:none', false === strpos( $css, '.chatl-pole-dodatkowe {' ) || false === strpos( substr( $css, strpos( $css, '.chatl-pole-dodatkowe {' ) ), 'display: none' ) );

echo "\n=== H. Sprzatanie ===\n";
chatl_test_sprzataj();
rowne( 'dane testowe usuniete', '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$tz} WHERE email LIKE 'pulapka-test%'" ) ); // phpcs:ignore

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
