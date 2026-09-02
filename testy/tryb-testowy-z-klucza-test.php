<?php
/**
 * Tryb testowy sklepu — strażnik ORZECZENIA, nie obecności napisu (P-3).
 *
 * CO GROZIŁO: napis „TRYB TESTOWY — płatności nie są pobierane" siedział w kodzie
 * na sztywno, bez jednego warunku. Po przejściu na klucze produkcyjne sklep dalej
 * zapewniałby kupującego, że pieniędzy nie pobiera — pobierając je naprawdę.
 * To zdanie na stronie i w mailu jest OBIETNICĄ, nie ozdobą.
 *
 * ⛔ TEN STRAŻNIK MUSI MIERZYĆ OBA PRZYPADKI. Test sprawdzający tylko „przy kluczu
 * testowym napis jest" przechodziłby również na kodzie SPRZED naprawy — czyli nie
 * mierzyłby niczego. Przypadek produkcyjny (napisu NIE MA) jest tym, który cokolwiek
 * dowodzi; przypadek testowy jest kontrolą DODATNIĄ (naprawa nie ma prawa odebrać
 * ostrzeżenia temu, komu się ono należy).
 *
 * ⛔ Poprzedni strażnik tej treści (mail-po-zakupie-test.php C11) asertował napis
 * BEZWARUNKOWO. Pytamy tu o TRYB, nie o obecność tekstu.
 *
 * Ścieżki objęte renderem (wszystkie, którymi ten napis dociera do człowieka):
 *   1. strona sklepu            sklepag_shortcode_sklep()
 *   2. strona „Dziękujemy"      sklepag_shortcode_dziekujemy()
 *   3. strona produktu          sklepag_render_produkt()  — baner, obie kotwice,
 *                               przycisk „Kup teraz", notka z kartą, przycisk „Zapłać"
 *   4. mail po zakupie HTML     sklepag_zbuduj_mail_html()
 *   5. mail po zakupie tekst    sklepag_zbuduj_mail()
 *   6. Pulpit właściciela       sklepag_admin_dashboard()
 *   7. assets/platnosc.js       — etykiety przycisków NADPISYWANE w przeglądarce;
 *                                 plik nie ma prawa nieść własnej kopii napisu
 *                                 (naprawa samego PHP byłaby tam cofana w locie).
 *
 * Uruchomienie:
 *   docker cp testy/tryb-testowy-z-klucza-test.php strona_wp_wordpress:/tmp/tt.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/tt.php
 */

$GLOBALS['sklepag_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_test_bledy'];
	}
}

/* Ekrany panelu wtyczka wciąga dopiero pod `is_admin()`, a `wp eval-file` biegnie poza nim.
   Bez tego Pulpit — ścieżka, na której WŁAŚCICIEL czyta o trybie — zostałby niezmierzony. */
if ( ! function_exists( 'sklepag_admin_dashboard' ) && defined( 'SKLEPAG_KATALOG' ) && file_exists( SKLEPAG_KATALOG . 'includes/admin.php' ) ) {
	require_once SKLEPAG_KATALOG . 'includes/admin.php';
}

foreach ( array( 'sklepag_tryb_testowy', 'sklepag_baner_testowy', 'sklepag_shortcode_sklep', 'sklepag_shortcode_dziekujemy', 'sklepag_render_produkt', 'sklepag_zbuduj_mail', 'sklepag_zbuduj_mail_html', 'sklepag_admin_dashboard' ) as $f ) {
	if ( ! function_exists( $f ) ) {
		echo "  BLAD brak funkcji {$f} (wtyczka sklep-agentow nieaktywna albo naprawa nie wgrana?)\n";
		exit( 1 );
	}
}

/* ⛔ Klucz na WSPÓLNYM stosie warsztatowym jest prawdziwy (testowy Stripe). Zdejmujemy go
   na czas pomiaru i oddajemy w shutdown — także wtedy, gdy test padnie w połowie albo
   wyjdzie fatalem. Bez tego kolejna sesja zastałaby sklep bez czym płacić. */
$klucz_kopia = get_option( 'sklepag_stripe_klucz_sekretny', false );
$przywroc    = static function () use ( $klucz_kopia ) {
	if ( false === $klucz_kopia ) {
		delete_option( 'sklepag_stripe_klucz_sekretny' );
	} else {
		update_option( 'sklepag_stripe_klucz_sekretny', $klucz_kopia, false );
	}
};
register_shutdown_function( $przywroc );

/* Atrapy kluczy: świadomie KRÓTKIE. Bramka poświadczeń łapie `(sk|rk)_(test|live)_[A-Za-z0-9]{16,}`,
   więc pełnowymiarowa atrapa w pliku repo paliłaby własną bramkę. */
$KL_TEST = 'sk_' . 'test_ATRAPA';
$KL_ZYWY = 'sk_' . 'live_ATRAPA';

function ustaw_klucz( $k ) {
	update_option( 'sklepag_stripe_klucz_sekretny', $k, false );
	wp_cache_delete( 'sklepag_stripe_klucz_sekretny', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

// Atrapa zamówienia i produktu do obu wersji maila — te same pola co w produkcji.
$produkt        = new stdClass();
$produkt->nazwa = 'ChatBot LIVE';
$produkt->slug  = 'chatbot-live';
$zam                = new stdClass();
$zam->id            = 4321;
$zam->kwota_grosze  = 14900;
$zam->waluta        = 'PLN';
$zam->zgoda_tresc   = SKLEPAG_ZGODA_TRESC;
$zam->zgoda_czas    = '2026-09-01 09:30:00';
$zam->utworzono     = '2026-09-01 09:29:00';
$link               = 'https://example.test/wp-admin/admin-post.php?action=sklepag_pobierz&z=1&t=abc';

/* Pulpit woła current_user_can( 'manage_options' ) i bez uprawnień robi wp_die — pomiar
   musi stać w butach właściciela sklepu, inaczej mierzyłby stronę odmowy. */
/* `wp eval-file` nie ma nagłówków żądania, a lista zamówień na Pulpicie pyta o HTTP_HOST —
   bez tego wyjście strażnika tonie w ostrzeżeniach rdzenia i przestaje być czytelne. */
if ( empty( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST'] = (string) wp_parse_url( home_url(), PHP_URL_HOST );
}

$admini    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin_id  = ! empty( $admini ) ? (int) $admini[0] : 0;
if ( $admin_id ) {
	wp_set_current_user( $admin_id );
}

function zbierz_ekrany( $produkt, $zam, $link, $admin_id ) {
	$e = array(
		'sklep'      => sklepag_shortcode_sklep(),
		'dziekujemy' => sklepag_shortcode_dziekujemy(),
		'produkt'    => sklepag_render_produkt( 'chatbot-live' ),
		'mail-html'  => sklepag_zbuduj_mail_html( $produkt, $zam, $link, '' ),
		'mail-tekst' => sklepag_zbuduj_mail( $produkt, $zam, $link, '' ),
	);
	if ( $admin_id ) {
		ob_start();
		sklepag_admin_dashboard();
		$e['pulpit'] = (string) ob_get_clean();
	}
	return $e;
}

/* Frazy DOSŁOWNE, nie zlepek „testow" — kupujący czyta zdania, a luźny wzorzec łapałby
   niewinne słowa (np. „przetestować") i strażnik świeciłby na czerwono bez wady. */
$FRAZY = array(
	'TRYB TESTOWY',
	'tryb testowy',
	'tryb testowy”',
	'płatność testowa',
	'płatności testowe',
	'płatnościach Stripe',
	'testowa Stripe',
	'kartą testową',
	'nie została pobrana',
	'nie są pobierane',
	'4242',
	'sklepag-baner-testowy',
	'sklepag-tryb-testowy',
);

function frazy_w( $tekst, $frazy ) {
	$zn = array();
	foreach ( $frazy as $f ) {
		if ( false !== strpos( $tekst, $f ) ) {
			$zn[] = $f;
		}
	}
	return $zn;
}

// =====================================================================
// A. ORZECZENIE O TRYBIE — samo źródło prawdy
// =====================================================================
echo "== A. Orzeczenie o trybie (prefiks klucza sekretnego) ==\n";
ustaw_klucz( $KL_TEST );
sprawdz( 'A1 klucz sk_test… = TRYB TESTOWY', true === sklepag_tryb_testowy() );
ustaw_klucz( $KL_ZYWY );
sprawdz( 'A2 klucz sk_live… = NIE tryb testowy', false === sklepag_tryb_testowy() );
ustaw_klucz( '' );
sprawdz( 'A3 BRAK klucza = NIE tryb testowy (fail-closed: nie obiecuj, czego nie umiesz dowieść)', false === sklepag_tryb_testowy() );
ustaw_klucz( 'sk_' . 'nieznanyprefiks' );
sprawdz( 'A4 nierozpoznany prefiks = NIE tryb testowy', false === sklepag_tryb_testowy() );
ustaw_klucz( $KL_TEST );
sprawdz( 'A5 baner niepusty w trybie testowym', '' !== sklepag_baner_testowy() );
ustaw_klucz( $KL_ZYWY );
sprawdz( 'A6 baner PUSTY poza trybem testowym', '' === sklepag_baner_testowy(), '[' . sklepag_baner_testowy() . ']' );

// =====================================================================
// B. KLUCZ TESTOWY — kontrola DODATNIA: ostrzeżenie MA być na każdej ścieżce
// =====================================================================
echo "\n== B. Klucz testowy: napis JEST na wszystkich siedmiu ścieżkach ==\n";
ustaw_klucz( $KL_TEST );
$t = zbierz_ekrany( $produkt, $zam, $link, $admin_id );

sprawdz( 'B1 strona sklepu: baner trybu', false !== strpos( $t['sklep'], 'sklepag-baner-testowy' ) );
sprawdz( 'B2 strona sklepu: krok „płatność testowa Stripe" i FAQ o trybie testowym', false !== strpos( $t['sklep'], 'płatność testowa Stripe' ) && false !== strpos( $t['sklep'], 'tryb testowy' ) );
sprawdz( 'B3 strona „Dziękujemy": baner trybu', false !== strpos( $t['dziekujemy'], 'sklepag-baner-testowy' ) );
sprawdz( 'B4 strona produktu: baner trybu', false !== strpos( $t['produkt'], 'sklepag-baner-testowy' ) );
sprawdz( 'B5 strona produktu: „Kup teraz (płatność testowa)" 3× (dwie kotwice + przycisk formularza)', 3 === substr_count( $t['produkt'], 'Kup teraz (płatność testowa)' ), substr_count( $t['produkt'], 'Kup teraz (płatność testowa)' ) . '×' );
sprawdz( 'B6 strona produktu: notka STRIPE · TRYB TESTOWY · KARTA 4242', false !== strpos( $t['produkt'], 'STRIPE · TRYB TESTOWY · KARTA 4242 4242 4242 4242' ) );
sprawdz( 'B7 strona produktu: przycisk panelu „Zapłać (płatność testowa)"', false !== strpos( $t['produkt'], 'Zapłać (płatność testowa)' ) );
sprawdz( 'B8 mail HTML: zdanie o niepobranej płatności', false !== strpos( $t['mail-html'], 'TRYB TESTOWY — płatność nie została pobrana' ) );
sprawdz( 'B9 mail tekstowy: to samo zdanie przy kwocie', false !== strpos( $t['mail-tekst'], '(TRYB TESTOWY — płatność nie została pobrana)' ) );
if ( $admin_id ) {
	sprawdz( 'B10 Pulpit właściciela: znacznik TRYB TESTOWY przy tytule', false !== strpos( $t['pulpit'], 'TRYB TESTOWY' ) );
} else {
	sprawdz( 'B10 Pulpit właściciela: brak konta administratora na stosie — ścieżka NIEZMIERZONA', false );
}

// =====================================================================
// C. KLUCZ PRODUKCYJNY — to ten przypadek cokolwiek mierzy
// =====================================================================
echo "\n== C. Klucz produkcyjny: napisu NIE MA na ŻADNEJ ścieżce ==\n";
ustaw_klucz( $KL_ZYWY );
$p = zbierz_ekrany( $produkt, $zam, $link, $admin_id );

foreach ( $p as $nazwa => $tresc ) {
	$zn = frazy_w( $tresc, $FRAZY );
	sprawdz( 'C[' . $nazwa . '] zero deklaracji o trybie testowym', empty( $zn ), empty( $zn ) ? '' : 'znalezione: ' . implode( ' | ', $zn ) );
}

// =====================================================================
// D. KONTROLA DODATNIA DO C — naprawa nie ma prawa wyczyścić ekranu
//    („nie ma napisu", bo nie ma niczego, byłoby zielone i bezwartościowe)
// =====================================================================
echo "\n== D. Klucz produkcyjny: ekrany dalej niosą swoją treść ==\n";
sprawdz( 'D1 strona sklepu dalej się renderuje (hero + FAQ + finał)', false !== strpos( $p['sklep'], '[ SKLEP ]' ) && false !== strpos( $p['sklep'], 'sklepag-faq' ) && false !== strpos( $p['sklep'], 'Kupujesz —' ) );
sprawdz( 'D2 strona „Dziękujemy" dalej pyta o status', false !== strpos( $p['dziekujemy'], '[ ZAMÓWIENIE ]' ) );
/* 4× — trzy CTA, które straciły dopisek, PLUS pigułka „sticky" (front-produkt.php), która
   nigdy go nie nosiła. Gdyby naprawa zamiast skracać etykietę usunęła przycisk, ten warunek padnie. */
sprawdz( 'D3 strona produktu: „Kup teraz" 4× i „Zapłać" — przyciski nie zniknęły, straciły tylko dopisek', 4 === substr_count( $p['produkt'], '>Kup teraz<' ) && false !== strpos( $p['produkt'], '>Zapłać<' ), substr_count( $p['produkt'], '>Kup teraz<' ) . '× Kup teraz' );
sprawdz( 'D4 strona produktu: notka o Stripe zostaje (kupujący wie, kto bierze dane karty)', false !== strpos( $p['produkt'], 'sklepag-zakup-notka' ) );
sprawdz( 'D5 mail HTML: kwota na miejscu, zniknął tylko dopisek', false !== strpos( $p['mail-html'], '149,00' ) && false !== strpos( $p['mail-html'], esc_url( $link ) ) );
sprawdz( 'D6 mail tekstowy: kwota na miejscu', false !== strpos( $p['mail-tekst'], 'Kwota: 149,00 PLN' ) && false !== strpos( $p['mail-tekst'], $link ) );
if ( $admin_id ) {
	sprawdz( 'D7 Pulpit dalej ma tytuł i wskaźniki', false !== strpos( $p['pulpit'], 'Sklep Agentów' ) && false !== strpos( $p['pulpit'], 'sklepag-dashboard' ) );
}

// =====================================================================
// E. PRZEGLĄDARKA — pułapka klasy A4b-F2
//    `platnosc.js` nadpisuje etykiety przycisków PO renderze PHP. Własna kopia
//    napisu w tym pliku cofałaby naprawę w locie: strona przyszłaby z serwera
//    poprawna, a kupujący i tak zobaczyłby „(płatność testowa)".
// =====================================================================
echo "\n== E. Etykiety przycisków w przeglądarce (assets/platnosc.js) ==\n";
$js_sciezka = WP_PLUGIN_DIR . '/sklep-agentow/assets/platnosc.js';
$js         = file_exists( $js_sciezka ) ? (string) file_get_contents( $js_sciezka ) : '';
sprawdz( 'E0 plik platnosc.js czytelny', '' !== $js, $js_sciezka );
/* Pytamy o KOD, nie o cały plik: komentarz ma prawo cytować napis — i cytuje, bo opisuje
   właśnie tę pułapkę. Szukamy kopii, którą skrypt WPISZE do przycisku. Komentarze blokowe
   wycinamy; komentarzy `//` ten plik nie używa w ogóle (sprawdzone), a `//` z adresu URL
   nie ma tu prawa się pojawić — cały ruch idzie przez `cfg` z PHP. */
$js_kod        = (string) preg_replace( '#/\*.*?\*/#s', ' ', $js );
$js_znalezione = frazy_w( $js_kod, $FRAZY );
sprawdz( 'E1 kod platnosc.js (bez komentarzy) nie niesie własnej kopii napisu o trybie', empty( $js_znalezione ), empty( $js_znalezione ) ? '' : 'znalezione: ' . implode( ' | ', $js_znalezione ) );
sprawdz( 'E2 platnosc.js odtwarza etykietę ZAPAMIĘTANĄ z DOM-u (jedno źródło = PHP)', false !== strpos( $js, 'etykietaKup' ) && false !== strpos( $js, 'etykietaZaplac' ) );

$przywroc();
echo "\n" . ( $GLOBALS['sklepag_test_bledy'] ? 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n" : "WSZYSTKO OK\n" );
exit( $GLOBALS['sklepag_test_bledy'] ? 1 : 0 );
