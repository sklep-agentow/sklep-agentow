<?php
/**
 * Formularz produktu NIE GUBI wpisanej treści przy błędzie walidacji (audyt K7, B6/B7).
 *
 * Znalezisko audytora: za każdym razem, gdy zapis kończył się odrzutem (brak pliku,
 * zły plik), wszystkie pola — nazwa, oba opisy, cena — wracały puste. Właściciel,
 * który napisał długi opis i zapomniał paczki, pisał wszystko od nowa. Potwierdzone
 * trzykrotnie, więc to zachowanie, nie przypadek.
 *
 * Handler siedzi za `admin-post` i kończy błąd PRZEKIEROWANIEM, więc formularz
 * renderuje się od nowa z bazy. Naprawa: odrzut odkłada wysłane pola w przechowalni,
 * formularz je odczytuje JEDEN raz i kasuje.
 *
 * ⛔ CZEGO TEN TEST PILNUJE POZA SAMĄ NAPRAWĄ:
 *   - obie ścieżki odrzutu (wczesna: zła waluta · późna: aktywacja bez pliku),
 *     żeby naprawa nie zamknęła jednego egzemplarza zamiast klasy,
 *   - JEDNORAZOWOŚĆ przechowalni — drugie wejście w formularz pokazuje stan z bazy,
 *     nie wskrzeszony szkic sprzed godziny,
 *   - ODDZIELENIE UŻYTKOWNIKÓW — szkic jednego admina nie może wyciec drugiemu.
 *
 * Uruchomienie:
 *   bash narzedzia-qa/test-php.sh produkt-formularz-trzyma-szkic-test
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-produkty.php';

$GLOBALS['sklepag_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol && ! $warunek ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_test_bledy'];
	}
}

// --- oprawa: przekierowanie zamiast wyjścia z procesu --------------------------
// `$wroc` kończy się `exit`, którego pod `wp eval-file` nie da się przeżyć. Filtr
// `wp_redirect` rzuca wyjątek ZANIM dojdzie do exit — to jedyny sposób, żeby handler
// dał się w ogóle zawołać. Gdyby filtr przestał być wołany, `try` skończy się bez
// wyjątku i test to ZGŁOSI (nie przemilczy — patrz asercja „handler wyszedł błędem").
add_filter(
	'wp_redirect',
	static function ( $cel ) {
		throw new RuntimeException( 'PRZEKIEROWANIE:' . $cel );
	},
	1
);

$admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( empty( $admin ) ) {
	echo "  BLAD brak konta administratora na stosie — nie ma kim wykonac zapisu\n";
	exit( 1 );
}
wp_set_current_user( (int) $admin[0] );
$uid    = get_current_user_id();
$klucz  = 'sklepag_szkic_produktu_' . $uid;
// ⛔ `rtrim`: `sanitize_textarea_field()` OBCINA biale znaki z konca, wiec napis konczacy
// sie spacja nigdy nie wroci znak w znak. Bez tego test szukalby czegos, czego produkt
// nie ma prawa oddac — i oskarzalby kod o wlasna wade (zlapane przy kalibracji).
$dlugi  = rtrim( 'Opis pelny, ktory wlasciciel pisal dziesiec minut. ' . str_repeat( 'Tresc warta odzyskania. ', 12 ) );
$krotki = 'Krotki opis z listy sklepu';

/**
 * Wysyła formularz i zwraca komunikat błędu z przekierowania (albo pusty napis).
 */
function wyslij_formularz( array $pola ) {
	$_POST                = $pola;
	$_REQUEST             = $pola;
	$_FILES               = array();
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_produkt_zapisz' );
	$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	$_REQUEST['_wp_http_referer'] = admin_url( 'admin.php?page=sklepag-produkty' );
	try {
		sklepag_obsluz_zapis_produktu();
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
	return '';
}

/**
 * Renderuje formularz i zwraca jego HTML.
 */
function narysuj_formularz( $id ) {
	ob_start();
	sklepag_produkty_formularz( $id );
	return (string) ob_get_clean();
}

delete_transient( $klucz );

// === A. ŚCIEŻKA PÓŹNA — aktywacja bez pliku (dokładnie przypadek audytora) ======
echo "A. Odrzut: wlaczenie sprzedazy bez pliku paczki\n";
$wynik = wyslij_formularz(
	array(
		'action'      => 'sklepag_produkt_zapisz',
		'produkt_id'  => 0,
		'nazwa'       => 'Produkt z audytu K7',
		'opis_krotki' => $krotki,
		'opis_pelny'  => $dlugi,
		'cena'        => '199,00',
		'waluta'      => 'PLN',
		'aktywny'     => '1',
	)
);
sprawdz( 'handler wyszedl bledem (przekierowanie przechwycone)', false !== strpos( $wynik, 'PRZEKIEROWANIE:' ), 'wynik: ' . $wynik );
sprawdz( 'komunikat mowi o braku pliku', false !== strpos( rawurldecode( $wynik ), 'bez POTWIERDZONEGO pliku' ), 'wynik: ' . $wynik );

$html = narysuj_formularz( 0 );
sprawdz( 'formularz trzyma NAZWE', false !== strpos( $html, 'value="Produkt z audytu K7"' ) );
sprawdz( 'formularz trzyma OPIS KROTKI', false !== strpos( $html, esc_textarea( $krotki ) ) );
sprawdz( 'formularz trzyma OPIS PELNY (najdrozsza strata)', false !== strpos( $html, esc_textarea( $dlugi ) ) );
sprawdz( 'formularz trzyma CENE tak, jak ja wpisano', false !== strpos( $html, 'value="199.00"' ) );
sprawdz( 'formularz trzyma zaznaczona sprzedaz', false !== strpos( $html, "name=\"aktywny\" value=\"1\" checked='checked'" ) );

// === B. JEDNORAZOWOŚĆ — drugie wejscie pokazuje czysty formularz ================
echo "B. Przechowalnia jest jednorazowa\n";
$html2 = narysuj_formularz( 0 );
sprawdz( 'drugie wejscie NIE wskrzesza szkicu', false === strpos( $html2, 'value="Produkt z audytu K7"' ) );
sprawdz( 'przechowalnia skasowana po odczycie', false === get_transient( $klucz ) );

// === C. ŚCIEŻKA WCZESNA — zla waluta (inny punkt odrzutu, ta sama klasa) =======
echo "C. Odrzut: zla waluta (wczesna sciezka walidacji)\n";
$wynik = wyslij_formularz(
	array(
		'action'      => 'sklepag_produkt_zapisz',
		'produkt_id'  => 0,
		'nazwa'       => 'Drugi produkt',
		'opis_krotki' => $krotki,
		'opis_pelny'  => $dlugi,
		'cena'        => '49,90',
		'waluta'      => 'ZLOTOWKI',
		'aktywny'     => '0',
	)
);
sprawdz( 'handler wyszedl bledem waluty', false !== strpos( rawurldecode( $wynik ), 'Waluta musi' ), 'wynik: ' . $wynik );
$html3 = narysuj_formularz( 0 );
sprawdz( 'takze TU formularz trzyma opis pelny', false !== strpos( $html3, esc_textarea( $dlugi ) ) );
sprawdz( 'takze TU formularz trzyma nazwe', false !== strpos( $html3, 'value="Drugi produkt"' ) );

// === D. SZKIC NIE WYCIEKA DO INNEGO UZYTKOWNIKA ================================
echo "D. Szkic nalezy do jednego konta\n";
wyslij_formularz(
	array(
		'action'      => 'sklepag_produkt_zapisz',
		'produkt_id'  => 0,
		'nazwa'       => 'Szkic prywatny',
		'opis_krotki' => '',
		'opis_pelny'  => '',
		'cena'        => '10,00',
		'waluta'      => 'XX',
		'aktywny'     => '0',
	)
);
$obcy = 999000 + $uid;   // konto, ktore nie istnieje — wystarczy INNY identyfikator
wp_set_current_user( $obcy );
$html4 = narysuj_formularz( 0 );
sprawdz( 'inny uzytkownik NIE widzi cudzego szkicu', false === strpos( $html4, 'value="Szkic prywatny"' ) );
wp_set_current_user( $uid );
delete_transient( $klucz );

echo "\n";
if ( 0 === $GLOBALS['sklepag_test_bledy'] ) {
	echo "WYNIK: OK — formularz trzyma wpisana tresc na obu sciezkach odrzutu.\n";
	exit( 0 );
}
echo 'WYNIK: BLEDY = ' . $GLOBALS['sklepag_test_bledy'] . "\n";
exit( 1 );
