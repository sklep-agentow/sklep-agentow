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
// Handler resendu (sekcja C3) żyje w pliku panelu, ładowanym tylko przy is_admin() —
// wp eval-file leci frontem, więc dociągamy jawnie (sam require rejestruje tylko hooki).
require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/admin-zamowienie.php';

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

// SETUP: sprzątnij ewentualne resztki poprzedniego przebiegu (WSZYSTKIE zamówienia
// testowe — przerwany przebieg nie może wywrócić następnego), załóż produkt testowy.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id IN (%d, %d, %d, %d, %d)", $zam1, $zam2, 9900011, 9900012, 9900013 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zamowienia WHERE id IN (%d, %d)", $zam1, 9900011 ) );
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

echo "\n=== C2. Akapit OCEN w mailu — zakup i RESEND uzywaja tej samej pary funkcji ===\n";
$prod_obj = (object) array( 'nazwa' => 'TEST mechanizmu opinii' );
$zam_obj  = (object) array(
	'kwota_grosze' => 100,
	'waluta'       => 'PLN',
);
$mail_z   = sklepag_zbuduj_mail( $prod_obj, $zam_obj, 'LINK-POBRANIA', 'LINK-OPINII-XYZ' );
sprawdz( 'mail z linkiem opinii MA akapit OCEN SWOJ ZAKUP i link', false !== strpos( $mail_z, 'OCEŃ SWÓJ ZAKUP' ) && false !== strpos( $mail_z, 'LINK-OPINII-XYZ' ) );
$mail_bez = sklepag_zbuduj_mail( $prod_obj, $zam_obj, 'LINK-POBRANIA' );
sprawdz( 'mail bez linku (opinia juz przyslana) NIE MA akapitu OCEN', false === strpos( $mail_bez, 'OCEŃ SWÓJ ZAKUP' ) );
sprawdz( 'zalazek po przyslanej opinii zwraca pusty string (resend nie wskrzesza linku)', '' === sklepag_opinia_zalazek( $zam1, $prod_id ) );

echo "\n=== C3. HANDLER resendu na zywo (wzorzec domowy: nonce + przechwycony redirect i mail) ===\n";
$zam6 = 9900014;
$pzh  = $wpdb->prefix . 'sklepag_zamowienia';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pzh} WHERE id = %d", $zam6 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id = %d", $zam6 ) );
$wpdb->insert(
	$pzh,
	array(
		'id'                    => $zam6,
		'stripe_session_id'     => 'cs_test_opinie_mech_' . $zam6,
		'produkt_id'            => $prod_id,
		'produkt_nazwa_kupiona' => 'TEST mechanizmu opinii',
		'produkt_cena_kupiona'  => 100,
		'email_kupujacego'      => 'resend-test@example.com',
		'kwota_grosze'          => 100,
		'waluta'                => 'PLN',
		'status'                => 'mail_wyslany',
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
$admini = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
sprawdz( 'warunek testu: stack ma administratora', ! empty( $admini ) );
wp_set_current_user( (int) $admini[0]->ID );
$_GET['zamowienie']   = $zam6;
$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_resend' );
$przekieruj           = function ( $url ) {
	throw new Exception( 'REDIRECT:' . $url );
};
$bez_maila            = '__return_true'; // pre_wp_mail: wyslano=true bez realnej wysylki.
add_filter( 'wp_redirect', $przekieruj );
add_filter( 'pre_wp_mail', $bez_maila );
$wyjscie = '';
try {
	sklepag_obsluz_resend();
} catch ( Exception $e ) {
	$wyjscie = $e->getMessage();
}
remove_filter( 'wp_redirect', $przekieruj );
remove_filter( 'pre_wp_mail', $bez_maila );
wp_set_current_user( 0 );
unset( $_GET['zamowienie'], $_REQUEST['_wpnonce'] );
sprawdz( 'handler konczy przekierowaniem sklepag_resend=ok', 0 === strpos( $wyjscie, 'REDIRECT:' ) && false !== strpos( $wyjscie, 'sklepag_resend=ok' ) );
$op_resend = $wpdb->get_row( $wpdb->prepare( "SELECT status, token_hash FROM {$to} WHERE zamowienie_id = %d", $zam6 ) );
sprawdz( 'resend ZALOZYL zalazek opinii zamowieniu sprzed mechanizmu', $op_resend && 'zalazek' === $op_resend->status && 64 === strlen( (string) $op_resend->token_hash ) );
$tresc_resend = (string) $wpdb->get_var( $wpdb->prepare( "SELECT mail_tresc FROM {$pzh} WHERE id = %d", $zam6 ) );
sprawdz( 'mail ZAPISANY PRZEZ HANDLER ma akapit OCEN i link sklepag_ocen', false !== strpos( $tresc_resend, 'OCEŃ SWÓJ ZAKUP' ) && false !== strpos( $tresc_resend, 'action=sklepag_ocen&z=' . $zam6 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pzh} WHERE id = %d", $zam6 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id = %d", $zam6 ) );

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

echo "\n=== G. Render CALEJ strony produktu (sklepag_render_produkt), nie samej funkcji ===\n";
$zam3 = 9900011; // produkt generyczny (spoza dwojki)
$zam4 = 9900012; // produkt z dwojki (chatbot-live)
$zam5 = 9900013; // zalazek do anonimizacji
$wpdb->query( $wpdb->prepare( "UPDATE {$tp} SET aktywny = 1 WHERE id = %d", $prod_id ) ); // render wymaga aktywnego produktu
$token4 = sklepag_opinia_zalazek( $zam3, $prod_id );
sklepag_opinia_zapisz(
	$zam3,
	$token4,
	array(
		'imie'     => 'Generyczna Ewa',
		'gwiazdki' => 5,
		'tresc'    => 'Dziala u mnie od pierwszego dnia.',
		'zgoda'    => 1,
	)
);
$wiersz_g = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$to} WHERE zamowienie_id = %d", $zam3 ) );
sklepag_opinia_moderuj( (int) $wiersz_g->id, 'przyjmij' );
$strona_g = sklepag_render_produkt( $slug );
sprawdz( 'strona GENERYCZNA: sekcja opinii + plakietka + imie (WAZNA z recenzji)', false !== strpos( $strona_g, '[ OPINIE KLIENTÓW ]' ) && false !== strpos( $strona_g, 'ZWERYFIKOWANY ZAKUP' ) && false !== strpos( $strona_g, 'Generyczna Ewa' ) );

$chat_id = (int) $wpdb->get_var( "SELECT id FROM {$tp} WHERE slug = 'chatbot-live'" );
sprawdz( 'warunek testu: stack ma produkt chatbot-live', $chat_id > 0 );
$token5 = sklepag_opinia_zalazek( $zam4, $chat_id );
sklepag_opinia_zapisz(
	$zam4,
	$token5,
	array(
		'imie'     => 'Dwojkowa Ola',
		'gwiazdki' => 4,
		'tresc'    => 'Test pelnego ukladu strony.',
		'zgoda'    => 1,
	)
);
$wiersz_c = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$to} WHERE zamowienie_id = %d", $zam4 ) );
sklepag_opinia_moderuj( (int) $wiersz_c->id, 'przyjmij' );
$strona_c = sklepag_render_produkt( 'chatbot-live' );
sprawdz( 'strona DWOJKI: opinia z bazy renderuje sie w pelnym ukladzie sekcji', false !== strpos( $strona_c, 'Dwojkowa Ola' ) && false !== strpos( $strona_c, 'ZWERYFIKOWANY ZAKUP' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id = %d", $zam4 ) ); // od razu — zywe demo nie moze pokazac wpisu testowego
sprawdz( 'wpis testowy dwojki sprzatniety OD RAZU', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$to} WHERE zamowienie_id = %d", $zam4 ) ) );

echo "\n=== H. Anonimizacja RODO (KRYTYCZNA z recenzji): imie i token umieraja, publikacja schodzi ===\n";
sprawdz( 'anonimizacja zwraca true przy zmianie', true === sklepag_anonimizuj_opinie( $zam3 ) );
$w_po = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$to} WHERE zamowienie_id = %d", $zam3 ) );
sprawdz( 'imie puste, token martwy, status odrzucona', $w_po && '' === (string) $w_po->imie && '' === (string) $w_po->token_hash && null === $w_po->token_wygasa && 'odrzucona' === $w_po->status );
$strona_po = sklepag_render_produkt( $slug );
sprawdz( 'zanonimizowana opinia ZNIKNELA ze strony (a sekcja z opinia zam1 zostala)', false === strpos( $strona_po, 'Generyczna Ewa' ) && false !== strpos( $strona_po, 'Test Ola' ) );
$w_kontrola = $wpdb->get_row( $wpdb->prepare( "SELECT imie, status FROM {$to} WHERE zamowienie_id = %d", $zam1 ) );
sprawdz( 'przypadek KONTROLNY: opinia innego zamowienia NIETKNIETA', $w_kontrola && 'Test Ola' === $w_kontrola->imie && 'przyjeta' === $w_kontrola->status );
$token6 = sklepag_opinia_zalazek( $zam5, $prod_id );
sklepag_anonimizuj_opinie( $zam5 );
$w_zal = $wpdb->get_row( $wpdb->prepare( "SELECT status, token_hash FROM {$to} WHERE zamowienie_id = %d", $zam5 ) );
sprawdz( 'zalazek po anonimizacji: zostaje zalazkiem, ale token martwy', $w_zal && 'zalazek' === $w_zal->status && '' === (string) $w_zal->token_hash && null === sklepag_opinia_po_tokenie( $zam5, $token6 ) );

// Przypadek BEZ wady (ksztalt PRODUKCYJNY): zamowienie ISTNIEJE z usunieto_dnia=NULL
// — moderacja MUSI dzialac. Bez tego kroku odmowa typu "dla kazdego zamowienia"
// (np. SELECT z.id zamiast z.usunieto_dnia) swiecilaby na zielono caly test.
$pz = $wpdb->prefix . 'sklepag_zamowienia';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE id = %d", $zam1 ) );
$wstaw_zam1 = $wpdb->insert(
	$pz,
	array(
		'id'                    => $zam1,
		'stripe_session_id'     => 'cs_test_opinie_mech_' . $zam1,
		'produkt_id'            => $prod_id,
		'produkt_nazwa_kupiona' => 'TEST mechanizmu opinii',
		'email_kupujacego'      => 'test-opinie@example.com',
		'status'                => 'mail_wyslany',
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
// Oprawa MUSI powstac — przy nieudanym insercie krok badalby sciezke "brak wiersza
// zamowienia", czyli dokladnie te, ktora mial wyeliminowac (recenzja #177 r4).
sprawdz( 'oprawa kontroli: wiersz zamowienia zam1 POWSTAL (usunieto_dnia=NULL)', 1 === (int) $wstaw_zam1 && null === $wpdb->get_var( $wpdb->prepare( "SELECT usunieto_dnia FROM {$pz} WHERE id = %d", $zam1 ) ) && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE id = %d", $zam1 ) ) );
sprawdz( 'KONTROLA ksztaltu produkcyjnego: usunieto_dnia=NULL -> moderacja DZIALA (odrzuc i przyjmij)', true === sklepag_opinia_moderuj( (int) $wiersz->id, 'odrzuc' ) && true === sklepag_opinia_moderuj( (int) $wiersz->id, 'przyjmij' ) );
sprawdz( 'opinia po kontroli produkcyjnej NADAL na stronie', false !== strpos( sklepag_render_produkt( $slug ), 'Test Ola' ) );

// FURTKA (recenzja #177 r2): przycisk „Przyjmij" NIE moze wskrzesic zanonimizowanej
// opinii. Odmowa stoi na zamowieniu (usunieto_dnia), wiec zakladamy wiersz zamowienia.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE id = %d", $zam3 ) );
$wpdb->insert(
	$pz,
	array(
		'id'                    => $zam3,
		'stripe_session_id'     => 'cs_test_opinie_mech_' . $zam3,
		'produkt_id'            => $prod_id,
		'produkt_nazwa_kupiona' => 'TEST mechanizmu opinii',
		'email_kupujacego'      => '',
		'status'                => 'mail_wyslany',
		'usunieto_dnia'         => $teraz,
		'utworzono'             => $teraz,
		'zmieniono'             => $teraz,
	)
);
sprawdz( 'RODO-furtka: przyjecie zanonimizowanej opinii ODMOWIONE', false === sklepag_opinia_moderuj( (int) $wiersz_g->id, 'przyjmij' ) );
$w_furtka = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$to} WHERE zamowienie_id = %d", $zam3 ) );
sprawdz( 'status zostal odrzucona, tresc NIE wrocila na strone', $w_furtka && 'odrzucona' === $w_furtka->status && false === strpos( sklepag_render_produkt( $slug ), 'Dziala u mnie od pierwszego dnia.' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE id = %d", $zam3 ) );

echo "\n=== I. SPRZATANIE po tescie ===\n";
$wpdb->query( $wpdb->prepare( "DELETE FROM {$to} WHERE zamowienie_id IN (%d, %d, %d, %d, %d)", $zam1, $zam2, $zam3, $zam4, $zam5 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$pz} WHERE id IN (%d, %d)", $zam1, $zam3 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tp} WHERE id = %d", $prod_id ) );
sprawdz( 'zero wierszy testowych w zamowieniach', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE id IN (%d, %d)", $zam1, $zam3 ) ) );
sprawdz( 'zero wierszy testowych w opiniach', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$to} WHERE zamowienie_id IN (%d, %d, %d, %d, %d)", $zam1, $zam2, $zam3, $zam4, $zam5 ) ) );
sprawdz( 'produkt testowy skasowany', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tp} WHERE id = %d", $prod_id ) ) );

echo "\n";
if ( $GLOBALS['sklepag_test_bledy'] > 0 ) {
	echo 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n";
	exit( 1 );
}
echo "WSZYSTKIE KROKI PRZESZLY\n";
exit( 0 );
