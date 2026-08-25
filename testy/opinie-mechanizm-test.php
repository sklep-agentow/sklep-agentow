<?php
/**
 * Mechanizm opinii (D32) — cały obieg na prawdziwej bazie: zalążek+token przy
 * mailu → walidacja tokenu → zapis z formularza → moderacja → render z bazy.
 *
 * Test tworzy WŁASNY produkt (aktywny=0, niewidoczny w sklepie) i własne wiersze
 * opinii, a na końcu je kasuje — zero śladów po przebiegu (konwencja testów).
 *
 * Uruchomienie:
 *   bash narzedzia-qa/test-php.sh opinie-mechanizm-test
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/opinie.php';

// phpcs:disable WordPress.DB -- test chodzi po prawdziwej bazie wprost (konwencja testy/): własne wiersze, sprzątanie na końcu.

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

global $wpdb;
$to    = $wpdb->prefix . 'sklepag_opinie';
$tp    = $wpdb->prefix . 'sklepag_produkty';
$slug  = 'sklepag-test-opinie-mech';
$zam1  = 9900001;
$zam2  = 9900002;
$teraz = current_time( 'mysql' );

// SETUP: sprzątnij ewentualne resztki poprzedniego przebiegu, załóż produkt testowy.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id IN (%d, %d)", $zam1, $zam2 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tp} WHERE slug = %s", $slug ) );
$wpdb->insert(
	$tp,
	array(
		'nazwa'       => 'TEST mechanizmu opinii',
		'slug'        => $slug,
		'opis_krotki' => 'wiersz testowy — do skasowania',
		'opis_pelny'  => 'wiersz testowy',
		'cena_grosze' => 100,
		'waluta'      => 'PLN',
		'aktywny'     => 0,
		'utworzono'   => $teraz,
		'zmieniono'   => $teraz,
	)
);
$prod_id = (int) $wpdb->insert_id;
sprawdz( 'SETUP: produkt testowy zalozony (aktywny=0)', $prod_id > 0 );

echo "\n=== A. Zalazek przy mailu: token jednorazowy, retry odswieza ===\n";
$token1 = sklepag_opinia_zalazek( $zam1, $prod_id );
sprawdz( 'pierwszy mail: token 64 hex', 64 === strlen( $token1 ) && ctype_xdigit( $token1 ) );
$wiersz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$to} WHERE zamowienie_id = %d", $zam1 ) );
sprawdz( 'wiersz w bazie ze statusem zalazek', $wiersz && 'zalazek' === $wiersz->status );
$token2 = sklepag_opinia_zalazek( $zam1, $prod_id );
sprawdz( 'retry maila: NOWY token (stary przestaje dzialac)', 64 === strlen( $token2 ) && $token2 !== $token1 );
sprawdz( 'stary token odrzucony', null === sklepag_opinia_po_tokenie( $zam1, $token1 ) );
sprawdz( 'nowy token wazny', null !== sklepag_opinia_po_tokenie( $zam1, $token2 ) );
sprawdz( 'zly token odrzucony', null === sklepag_opinia_po_tokenie( $zam1, str_repeat( 'a', 64 ) ) );
sprawdz( 'cudze zamowienie odrzucone', null === sklepag_opinia_po_tokenie( $zam1 + 500, $token2 ) );

echo "\n=== B. Walidacja formularza ===\n";
sprawdz(
	'puste pola -> pola',
	'pola' === sklepag_opinia_zapisz(
		$zam1,
		$token2,
		array(
			'imie'     => '',
			'gwiazdki' => 4,
			'tresc'    => 'x',
			'zgoda'    => 1,
		)
	)
);
sprawdz(
	'gwiazdki poza zakresem -> pola',
	'pola' === sklepag_opinia_zapisz(
		$zam1,
		$token2,
		array(
			'imie'     => 'Ola',
			'gwiazdki' => 9,
			'tresc'    => 'x',
			'zgoda'    => 1,
		)
	)
);
sprawdz(
	'brak zgody -> zgoda',
	'zgoda' === sklepag_opinia_zapisz(
		$zam1,
		$token2,
		array(
			'imie'     => 'Ola',
			'gwiazdki' => 4,
			'tresc'    => 'x',
		)
	)
);

echo "\n=== C. Happy path: zapis -> oczekuje, token spalony ===\n";
$wynik = sklepag_opinia_zapisz(
	$zam1,
	$token2,
	array(
		'imie'     => 'Test Ola',
		'gwiazdki' => 4,
		'tytul'    => 'Robi robote',
		'tresc'    => 'Bot odpowiada na pytania klientow, a trudne oddaje mnie.',
		'zgoda'    => 1,
	)
);
sprawdz( 'zapis przechodzi (pusty wynik)', '' === $wynik );
$wiersz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$to} WHERE zamowienie_id = %d", $zam1 ) );
sprawdz( 'status oczekuje, token_hash pusty', $wiersz && 'oczekuje' === $wiersz->status && '' === (string) $wiersz->token_hash );
sprawdz(
	'drugi zapis tym samym linkiem -> token',
	'token' === sklepag_opinia_zapisz(
		$zam1,
		$token2,
		array(
			'imie'     => 'X',
			'gwiazdki' => 5,
			'tresc'    => 'x',
			'zgoda'    => 1,
		)
	)
);

echo "\n=== D. Moderacja i render z bazy ===\n";
sprawdz( 'przed moderacja: zero opinii na stronie', array() === sklepag_opinie_z_bazy( $slug ) );
sprawdz( 'przyjmij dziala', true === sklepag_opinia_moderuj( (int) $wiersz->id, 'przyjmij' ) );
$lista = sklepag_opinie_z_bazy( $slug );
sprawdz( 'po przyjeciu: jedna opinia z kluczami renderu', 1 === count( $lista ) && 'Test Ola' === $lista[0]['imie'] && 4 === $lista[0]['gwiazdki'] && 'Robi robote' === $lista[0]['tytul'] && '' !== $lista[0]['data'] );
$html = sklepag_render_opinie( $lista );
sprawdz( 'render z bazy: plakietka + imie + gwiazdki', false !== strpos( $html, 'ZWERYFIKOWANY ZAKUP' ) && false !== strpos( $html, '<strong>Test Ola</strong>' ) && false !== strpos( $html, 'aria-label="Ocena 4 na 5"' ) );
sprawdz( 'odrzuc dziala (pomylka do naprawienia)', true === sklepag_opinia_moderuj( (int) $wiersz->id, 'odrzuc' ) );
sprawdz( 'po odrzuceniu: zero opinii na stronie', array() === sklepag_opinie_z_bazy( $slug ) );
sprawdz( 'przyjmij ponownie dziala', true === sklepag_opinia_moderuj( (int) $wiersz->id, 'przyjmij' ) );
sprawdz( 'zla akcja odrzucona', false === sklepag_opinia_moderuj( (int) $wiersz->id, 'skasuj' ) );

echo "\n=== E. Wygasly token ===\n";
$token3 = sklepag_opinia_zalazek( $zam2, $prod_id );
$wpdb->query( $wpdb->prepare( "UPDATE {$to} SET token_wygasa = %s WHERE zamowienie_id = %d", '2020-01-01 00:00:00', $zam2 ) );
sprawdz( 'token po terminie odrzucony', null === sklepag_opinia_po_tokenie( $zam2, $token3 ) );
sprawdz(
	'zapis po terminie -> token',
	'token' === sklepag_opinia_zapisz(
		$zam2,
		$token3,
		array(
			'imie'     => 'X',
			'gwiazdki' => 5,
			'tresc'    => 'x',
			'zgoda'    => 1,
		)
	)
);

echo "\n=== F. SPRZATANIE po tescie ===\n";
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id IN (%d, %d)", $zam1, $zam2 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tp} WHERE id = %d", $prod_id ) );
sprawdz( 'zero wierszy testowych w opiniach', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$to} WHERE zamowienie_id IN (%d, %d)", $zam1, $zam2 ) ) );
sprawdz( 'produkt testowy skasowany', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tp} WHERE id = %d", $prod_id ) ) );

echo "\n";
if ( $GLOBALS['sklepag_test_bledy'] > 0 ) {
	echo 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n";
	exit( 1 );
}
echo "WSZYSTKIE KROKI PRZESZLY\n";
exit( 0 );
