<?php
/**
 * ODRZUT WALIDACJI NIE KASUJE WPISANEJ TREŚCI — formularz PRODUKTU (panel właściciela).
 *
 * Znalezione ręcznym przejściem po produkcie: zapis produktu kończący się odrzutem
 * (brak pliku, zły plik) wracał z pustymi polami. Właściciel, który napisał długi opis
 * i zapomniał paczki, pisał wszystko od nowa. Potwierdzone trzykrotnie.
 *
 * ⛔ TO SAMO SIEDZIAŁO W DRUGIM FORMULARZU — opinii kupującego (do 2000 znaków, a
 * kupującemu nikt tego nie odtworzy). Bliźniak ma WŁASNY test, bo jego handler kończy
 * się `exit`: `testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh`. Nie da się go
 * domknąć w tym pliku — `exit` ubija asercje stojące po nim, a kod wyjścia i tak wychodzi
 * zerowy, więc test wyglądałby na zdany, nie wykonawszy połowy sprawdzeń.
 *
 * Formularz produktu kończy odrzut wyjściem z żądania (przekierowanie albo strona z linkiem
 * powrotnym), więc treść wraca przez przechowalnię czytaną JEDEN raz — i wyłącznie na
 * renderze będącym odpowiedzią na odrzut. Test sprawdza również to ograniczenie: szkic
 * porzucony (bo strona po odrzucie się nie wyrenderowała) NIE MOŻE wrócić przy
 * następnym, świadomym wejściu w formularz — bo wtedy właściciel patrzyłby na szkic
 * zamiast na bazę i mógłby go zapisać, nie wiedząc o tym.
 *
 * Uruchomienie:
 *   bash narzedzia-qa/test-php.sh produkt-formularz-trzyma-tresc-po-odrzucie-test
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-produkty.php';

// phpcs:disable WordPress.DB -- test chodzi po prawdziwej bazie wprost (konwencja testy/): własne wiersze, sprzątanie na końcu.

$GLOBALS['sklepag_test_bledy'] = 0;

// ⛔ WP-CLI ma WŁĄCZONE natychmiastowe opróżnianie bufora, więc samo `ob_start()` nie
// zatrzymuje `echo` z renderowanych stron — całe HTML-e lądowały w wyniku testu i topiły
// w sobie asercje. Wyłączamy to na czas przebiegu: strony mają być MIERZONE, nie oglądane.
ob_implicit_flush( false );

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
// `$wroc` w handlerze produktu kończy się `exit`, którego pod `wp eval-file` nie da się
// przeżyć. Filtr `wp_redirect` rzuca wyjątek ZANIM dojdzie do exit. Gdyby filtr przestał
// być wołany, `try` skończy się bez wyjątku i test to ZGŁOSI — patrz asercja
// „handler wyszedl bledem"; cicho nie przejdzie.
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
$dlugi  = rtrim( 'Opis pelny, ktory wlasciciel pisal dziesiec minut. ' . str_repeat( 'Tresc warta odzyskania. ', 12 ) );
$krotki = 'Krotki opis z listy sklepu';

/**
 * Wysyła formularz produktu i zwraca komunikat błędu z przekierowania (albo pusty napis).
 *
 * @param array $pola Pola formularza.
 */
function wyslij_formularz( array $pola ) {
	$_POST                        = $pola;
	$_REQUEST                     = $pola;
	$_FILES                       = array();
	$_REQUEST['_wpnonce']         = wp_create_nonce( 'sklepag_produkt_zapisz' );
	$_POST['_wpnonce']            = $_REQUEST['_wpnonce'];
	$_REQUEST['_wp_http_referer'] = admin_url( 'admin.php?page=sklepag-produkty' );
	try {
		sklepag_obsluz_zapis_produktu();
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
	return '';
}

/**
 * Renderuje formularz produktu i zwraca HTML.
 *
 * @param int  $id          Produkt (0 = nowy).
 * @param bool $po_odrzucie Czy render jest odpowiedzią na odrzut (adres z `sklepag_blad`).
 */
function narysuj_formularz( $id, $po_odrzucie = true ) {
	if ( $po_odrzucie ) {
		$_GET['sklepag_blad'] = 'komunikat z odrzutu';
	} else {
		unset( $_GET['sklepag_blad'] );
	}
	ob_start();
	sklepag_produkty_formularz( $id );
	return (string) ob_get_clean();
}

delete_transient( $klucz );

// === A. ŚCIEŻKA PÓŹNA — aktywacja bez pliku (dokładnie przypadek z przejścia) ===
echo "A. Produkt, odrzut: wlaczenie sprzedazy bez pliku paczki\n";
$wynik = wyslij_formularz(
	array(
		'action'      => 'sklepag_produkt_zapisz',
		'produkt_id'  => 0,
		'nazwa'       => 'Produkt z przelotu',
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
sprawdz( 'formularz trzyma NAZWE', false !== strpos( $html, 'value="Produkt z przelotu"' ) );
sprawdz( 'formularz trzyma OPIS KROTKI', false !== strpos( $html, esc_textarea( $krotki ) ) );
sprawdz( 'formularz trzyma OPIS PELNY (najdrozsza strata)', false !== strpos( $html, esc_textarea( $dlugi ) ) );
sprawdz( 'formularz trzyma CENE (przecinek zamieniony na kropke — pole type=number)', false !== strpos( $html, 'value="199.00"' ) );
sprawdz( 'formularz trzyma zaznaczona sprzedaz', false !== strpos( $html, "name=\"aktywny\" value=\"1\" checked='checked'" ) );

// === B. JEDNORAZOWOŚĆ — drugie wejscie pokazuje czysty formularz ================
echo "B. Przechowalnia jest jednorazowa\n";
$html2 = narysuj_formularz( 0 );
sprawdz( 'drugie wejscie NIE wskrzesza szkicu', false === strpos( $html2, 'value="Produkt z przelotu"' ) );
sprawdz( 'przechowalnia skasowana po odczycie', false === get_transient( $klucz ) );

// === C. ŚCIEŻKA WCZESNA — zla waluta (inny punkt odrzutu, ta sama klasa) =======
echo "C. Produkt, odrzut: zla waluta (wczesna sciezka walidacji)\n";
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

// === D. SZKIC PORZUCONY NIE WRACA PRZY ZWYKŁYM WEJŚCIU =========================
// Najgroźniejszy wariant: gdyby szkic nakładał się na KAŻDYM renderze, właściciel
// wchodzący w edycję zobaczyłby porzucone wartości bez żadnego komunikatu błędu —
// i „Zapisz zmiany" wpisałoby je do bazy jako świadomą zmianę.
echo "D. Szkic porzucony NIE wraca przy zwyklym wejsciu w formularz\n";
wyslij_formularz(
	array(
		'action'      => 'sklepag_produkt_zapisz',
		'produkt_id'  => 0,
		'nazwa'       => 'Porzucony szkic',
		'opis_krotki' => '',
		'opis_pelny'  => '',
		'cena'        => '10,00',
		'waluta'      => 'XX',
		'aktywny'     => '0',
	)
);
sprawdz( 'szkic czeka w przechowalni po odrzucie', is_array( get_transient( $klucz ) ) );
$html_zwykly = narysuj_formularz( 0, false );
sprawdz( 'wejscie BEZ sklepag_blad nie pokazuje porzuconego szkicu', false === strpos( $html_zwykly, 'value="Porzucony szkic"' ) );
sprawdz( 'i nie konsumuje go po cichu (zostaje do wlasciwego powrotu)', is_array( get_transient( $klucz ) ) );
$html_po = narysuj_formularz( 0, true );
sprawdz( 'a na powrocie po odrzucie tresc jednak wraca', false !== strpos( $html_po, 'value="Porzucony szkic"' ) );

// === E. ROZDZIAŁ KONT — na DRUGIM PRAWDZIWYM administratorze ===================
// Wcześniejsza wersja tego testu przełączała się na identyfikator, którego nie ma;
// WordPress daje wtedy użytkownika o ID 0, więc porównywałaby szkic konta N z kluczem
// konta 0 i przeszłaby nawet wtedy, gdyby klucz w ogóle nie zawierał użytkownika.
echo "E. Szkic nalezy do jednego konta (drugi PRAWDZIWY administrator)\n";
$login = 'sklepag_test_admin2';
$drugi = get_user_by( 'login', $login );
$nowy  = false;
if ( ! $drugi ) {
	$id_nowy = wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => 'sklepag-test-admin2@example.invalid',
			'role'       => 'administrator',
		)
	);
	if ( ! is_wp_error( $id_nowy ) ) {
		$drugi = get_user_by( 'id', $id_nowy );
		$nowy  = true;
	}
}
if ( $drugi ) {
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
	wp_set_current_user( (int) $drugi->ID );
	sprawdz( 'drugie konto to naprawde inny, istniejacy uzytkownik', get_current_user_id() === (int) $drugi->ID && get_current_user_id() !== $uid && get_current_user_id() > 0 );
	$html4 = narysuj_formularz( 0, true );
	sprawdz( 'drugi administrator NIE widzi cudzego szkicu', false === strpos( $html4, 'value="Szkic prywatny"' ) );
	wp_set_current_user( $uid );
	if ( $nowy ) {
		wp_delete_user( (int) $drugi->ID );
	}
} else {
	sprawdz( 'udalo sie przygotowac drugie konto administratora', false, 'wp_insert_user nie zwrocil uzytkownika' );
}
delete_transient( $klucz );

echo "\n";
if ( 0 === $GLOBALS['sklepag_test_bledy'] ) {
	echo "WYNIK: OK — formularz produktu oddaje tresc po odrzucie, a porzucony szkic nie wraca.\n";
	exit( 0 );
}
echo 'WYNIK: BLEDY = ' . $GLOBALS['sklepag_test_bledy'] . "\n";
exit( 1 );
