<?php
/**
 * Krok C: POWTORZENIE anonimizacji po awarii — czy druga proba domyka WSZYSTKO?
 *
 * ⛔ SCENARIUSZ Z RECENZJI #207 (WAZNA 2). Przy poprzedniej kolejnosci operacji
 *    (najpierw zrodlo, potem dziennik) powtorzenie bylo NIEMOZLIWE do domkniecia:
 *    zrodlo bylo juz czyste, `$email` wracalo puste, warunek pomijal czyszczenie
 *    dziennika, a pomiar skutku patrzyl tylko na tabele zamowien i meldowal sukces.
 *    Adres kupujacego zostawal w `opis` wierszy zdarzen NA STALE.
 *
 * Test odtwarza dokladnie ten przebieg:
 *   1. zamowienie ma adres w tabeli ORAZ w opisie zdarzenia,
 *   2. PIERWSZA proba z podlozona awaria czyszczenia ZRODLA -> handler musi odmowic,
 *   3. DRUGA proba, juz bez awarii -> musi domknac OBA miejsca.
 *
 * Argument: brak. Drukuje ID i znaczniki, werdykt liczy skrypt powloki.
 *
 * @package sklep-agentow-testy
 */

global $wpdb;

/* Handler zyje w pliku ladowanym tylko za `is_admin()`, a pod WP-CLI to falsz.
   Dociagamy TEN SAM plik produkcyjny — nie kopie, nie atrape. */
if ( ! function_exists( 'sklepag_obsluz_anonimizacje' ) ) {
	$plik_handlera = WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-zamowienie.php';
	if ( ! is_readable( $plik_handlera ) ) {
		echo "PRZYRZAD=BRAK-PLIKU-HANDLERA\n";
		exit( 2 );
	}
	require_once $plik_handlera;
}

/*
 * ⛔ WLASNE WARUNKI WSTEPNE: kasujemy pozostalosci po WCZESNIEJSZYCH przebiegach, zanim
 * cokolwiek zmierzymy. Bez tego licznik wpisow obcych widzialby cudze wiersze i asercja
 * przeszlaby TAKZE wtedy, gdy biezacy wpis zostal przepisany — czyli falszywy alarm
 * zamieniony w SLEPOTE. Przebieg przerwany w polowie (np. przy kalibracji) zostawia takie
 * wiersze, wiec to nie jest teoria.
 */
$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id IS NULL AND typ IN ('eksport_csv','proba_obcy','proba_obcy_rfc','proba_rodo')" ); // phpcs:ignore

$pz    = $wpdb->prefix . 'sklepag_zamowienia';
$tz    = $wpdb->prefix . 'sklepag_zdarzenia';
$teraz = current_time( 'mysql' );
$mail  = 'proba-rodo-powtorka-' . wp_generate_password( 8, false ) . '@example.test';

$wpdb->insert(
	$pz,
	array(
		'stripe_session_id'     => 'cs_test_rodo_powt_' . wp_generate_password( 12, false ),
		'produkt_id'            => 1,
		'produkt_nazwa_kupiona' => 'Zamowienie probne RODO (powtorka)',
		'email_kupujacego'      => $mail,
		'status'                => 'mail_wyslany',
		'token_pobrania'        => str_repeat( 'c', 64 ),
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
$id = (int) $wpdb->insert_id;
if ( ! $id ) {
	echo "PRZYRZAD=NIE-UDALO-SIE-UTWORZYC-ZAMOWIENIA\n";
	exit( 2 );
}

/* ⛔ WARUNEK WSTEPNY: adres MUSI byc rowniez w dzienniku, inaczej test nie ma czego
   pilnowac i „przeszlo" znaczyloby cisze. */
$wpdb->insert(
	$tz,
	array(
		'zamowienie_id' => $id,
		'typ'           => 'proba_rodo',
		'opis'          => 'mail wyslany na ' . $mail,
		'uzytkownik'    => 'test',
		'czas'          => $teraz,
	)
);
/* ⛔ SLAD BEZ `zamowienie_id` — dokladnie taki zapisuje eksport CSV z filtrem
   „szukaj po e-mailu”. Anonimizacja ograniczona do wierszy zamowienia zostawialaby go,
   a wtyczka i tak meldowalaby usuniecie. */
$wpdb->insert(
	$tz,
	array(
		'zamowienie_id' => null,
		'typ'           => 'eksport_csv',
		'opis'          => '3 wierszy; filtry: {"szukaj":"' . $mail . '"}',
		'uzytkownik'    => 'test',
		'czas'          => $teraz,
	)
);
/* ⛔ WPIS INNEGO KUPUJACEGO, ktorego adres ZAWIERA nasz jako koncowke.
   Niezakotwiczone `REPLACE(opis, adres, ...)` przepisaloby ten wiersz — czyli anonimizacja
   jednej osoby psulaby dane drugiej. Ten wiersz MUSI przezyc nietkniety. */
$mail_obcy = 'b' . $mail;
$wpdb->insert(
	$tz,
	array(
		'zamowienie_id' => null,
		'typ'           => 'proba_obcy',
		'opis'          => 'mail wyslany na ' . $mail_obcy,
		'uzytkownik'    => 'test',
		'czas'          => $teraz,
	)
);
echo 'MAIL_OBCY=' . $mail_obcy . "\n";

/* ⛔ DRUGI wpis obcego — adres poprzedzony znakiem, ktory RFC dopuszcza w czesci lokalnej
   (`!`), a ktorego WEZSZA kotwica nie znala. Stara granica (`[A-Za-z0-9._%+-]`) przepuszczala
   takie wystapienie i przepisywala cudzy wpis. Ten wiersz TEZ musi przezyc nietkniety. */
$mail_obcy_rfc = '!' . $mail;
$wpdb->insert(
	$tz,
	array(
		'zamowienie_id' => null,
		'typ'           => 'proba_obcy_rfc',
		'opis'          => 'mail wyslany na ' . $mail_obcy_rfc,
		'uzytkownik'    => 'test',
		'czas'          => $teraz,
	)
);
echo 'MAIL_OBCY_RFC=' . $mail_obcy_rfc . "\n";

$luzem = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE zamowienie_id IS NULL AND opis LIKE %s", '%' . $wpdb->esc_like( $mail ) . '%' ) ); // phpcs:ignore
echo 'ADRES_LUZEM_PRZED=' . $luzem . "\n";

$w_dzienniku = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE zamowienie_id = %d AND opis LIKE %s", $id, '%' . $wpdb->esc_like( $mail ) . '%' ) ); // phpcs:ignore
echo 'ID=' . $id . "\n";
echo 'ADRES_W_DZIENNIKU_PRZED=' . $w_dzienniku . "\n";
if ( 1 !== $w_dzienniku ) {
	echo "PRZYRZAD=WARUNEK-WSTEPNY-NIESPELNIONY\n";
	exit( 2 );
}

$admini = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $admini ) ) {
	echo "PRZYRZAD=BRAK-ADMINA\n";
	exit( 2 );
}
wp_set_current_user( $admini[0]->ID );

$nonce                = wp_create_nonce( 'sklepag_anonimizuj' );
$_GET['zamowienie']   = $id;
$_GET['_wpnonce']     = $nonce;
$_REQUEST['_wpnonce'] = $nonce;

/* PROBA 1: psujemy czyszczenie DZIENNIKA — to jest scenariusz z WAZNEJ 2 recenzji #207.
   Zrodlo MUSI zostac nietkniete, inaczej powtorka nie ma czego szukac. */
$trafione = 0;
$psuj     = static function ( $zapytanie ) use ( &$trafione ) {
	/* ⛔ Celujemy w KSZTALT, ktory istnieje TERAZ: czyszczenie dziennika przeszlo z jednego
	   `REPLACE` w SQL na `$wpdb->update()` per wiersz. Stary wzorzec przestal trafiac i test
	   cicho niczego nie mierzyl — zlapal to wlasny warunek wstepny (PODLOZENIE_TRAFILO=0). */
	if ( false !== strpos( $zapytanie, 'sklepag_zdarzenia' ) && false !== strpos( $zapytanie, 'SET' ) && false !== strpos( $zapytanie, 'opis' ) ) {
		++$trafione;
		return 'UPDATE tabela_ktora_nie_istnieje_rodo SET x=1';
	}
	return $zapytanie;
};
add_filter( 'query', $psuj, 99 );
$wpdb->suppress_errors( true );

/* ⛔ Filtr MUSI byc zdejmowalny: bez `remove_filter` przezyl do proby 2 i podpisywal
   jej wynik etykieta PROBA1 — zlapane na sobie 28.08 (wada testu, nie kodu). */
$redirect1 = static function ( $lokalizacja ) {
	echo 'PROBA1_REDIRECT=' . $lokalizacja . "\n";
	return false;
};
add_filter( 'wp_redirect', $redirect1, 99 );
add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $wiadomosc ) {
			echo 'PROBA1_WPDIE=' . wp_strip_all_tags( is_wp_error( $wiadomosc ) ? $wiadomosc->get_error_message() : (string) $wiadomosc ) . "\n";
			/* NIE konczymy procesu: chcemy w tym samym przebiegu wykonac PROBE 2. */
			throw new Exception( 'wp_die-proba1' );
		};
	},
	99
);

try {
	sklepag_obsluz_anonimizacje();
} catch ( Exception $e ) {
	echo 'PROBA1_PRZERWANA=' . $e->getMessage() . "\n";
}
echo 'PROBA1_PODLOZENIE_TRAFILO=' . (int) $trafione . "\n";

/* PROBA 2: awaria mija. Handler musi domknac OBA miejsca. */
remove_filter( 'query', $psuj, 99 );
remove_filter( 'wp_redirect', $redirect1, 99 );
$stan = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego FROM {$pz} WHERE id = %d", $id ) ); // phpcs:ignore
echo 'ADRES_W_ZRODLE_PO_PROBIE1=' . ( ( $stan && '' === (string) $stan->email_kupujacego ) ? 'PUSTY' : 'ZOSTAL' ) . "\n";

add_filter(
	'wp_redirect',
	static function ( $lokalizacja ) {
		echo 'PROBA2_REDIRECT=' . $lokalizacja . "\n";
		return false;
	},
	99
);

sklepag_obsluz_anonimizacje();
