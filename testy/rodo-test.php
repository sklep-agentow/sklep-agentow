<?php
/**
 * Pozycja E — wpięcie w narzędzia RODO WordPressa. Strażnik, kod wyjścia 1 przy porażce.
 *
 * Trzy rzeczy, na których stoi ta funkcja:
 *   1. eksporter i kasownik są NAPRAWDĘ zarejestrowane w kreatorze WordPressa
 *      (sam fakt istnienia funkcji nic nie daje, jeśli filtr ich nie widzi);
 *   2. wniosek jednej osoby NIE rusza danych drugiej — to jest ten sam błąd co
 *      wyciek cudzej rozmowy, tylko w drugą stronę: skasowanie nie swojego;
 *   3. stronicowanie oddaje KOMPLET — eksport z dziurą jest gorszy niż brak
 *      eksportu, bo nikt go nie zauważy.
 *
 * Uruchomienie:
 *   docker cp testy/rodo-test.php strona_wp_wordpress:/tmp/rodo.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/rodo.php
 */

$GLOBALS['chatl_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['chatl_test_bledy'];
	}
}

function rowne( $opis, $oczekiwane, $mam ) {
	sprawdz( $opis, $oczekiwane === $mam, 'oczekiwano [' . var_export( $oczekiwane, true ) . '], mam [' . var_export( $mam, true ) . ']' );
}

/** Wszystkie wartości z paczki eksportu, spłaszczone do jednego napisu. */
function chatl_test_tresc_eksportu( $wynik ) {
	$tekst = '';
	foreach ( (array) $wynik['data'] as $grupa ) {
		foreach ( (array) $grupa['data'] as $pole ) {
			$tekst .= $pole['value'] . "\n";
		}
	}
	return $tekst;
}

global $wpdb;
$tz = $wpdb->prefix . 'chatl_zgloszenia';
$tr = $wpdb->prefix . 'chatl_rozmowy';

$mail_a = 'rodo-test-a@example.test';
$mail_b = 'rodo-test-b@example.test';
$sesja_a = 'rodoTestSesjaAAAAAAAAAAAAAAAAAAA';
$sesja_b = 'rodoTestSesjaBBBBBBBBBBBBBBBBBBB';

// Sprzątanie po ewentualnym poprzednim przebiegu.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email IN (%s, %s)", $mail_a, $mail_b ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja IN (%s, %s)", $sesja_a, $sesja_b ) ); // phpcs:ignore

echo "=== A. Kreator WordPressa NAPRAWDE nas widzi ===\n";
$eksportery = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$kasowniki  = apply_filters( 'wp_privacy_personal_data_erasers', array() );
sprawdz( 'eksporter zarejestrowany w kreatorze', isset( $eksportery['chatbot-live'] ) );
sprawdz( 'kasownik zarejestrowany w kreatorze', isset( $kasowniki['chatbot-live'] ) );
sprawdz( 'eksporter ma wywolywalny callback', isset( $eksportery['chatbot-live']['callback'] ) && is_callable( $eksportery['chatbot-live']['callback'] ) );
sprawdz( 'kasownik ma wywolywalny callback', isset( $kasowniki['chatbot-live']['callback'] ) && is_callable( $kasowniki['chatbot-live']['callback'] ) );

echo "\n=== B. Eksport wydaje zgloszenia I powiazane rozmowy ===\n";
$wpdb->insert( $tz, array( 'email' => $mail_a, 'wiadomosc' => 'TRESC-A: prosze o oferte', 'sesja' => $sesja_a, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
$wpdb->insert( $tr, array( 'sesja' => $sesja_a, 'rola' => 'gosc', 'silnik' => 'reguly', 'tresc' => 'ROZMOWA-A: ile kosztuje dostawa', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
$wpdb->insert( $tr, array( 'sesja' => $sesja_a, 'rola' => 'bot', 'silnik' => 'reguly', 'tresc' => 'ODPOWIEDZ-A: dostawa 15 zl', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore

$wynik = call_user_func( $eksportery['chatbot-live']['callback'], $mail_a, 1 );
$tresc = chatl_test_tresc_eksportu( $wynik );
sprawdz( 'eksport zawiera adres e-mail', false !== strpos( $tresc, $mail_a ) );
sprawdz( 'eksport zawiera tresc zgloszenia', false !== strpos( $tresc, 'TRESC-A' ) );
sprawdz( 'eksport zawiera pytanie goscia z rozmowy', false !== strpos( $tresc, 'ROZMOWA-A' ) );
sprawdz( 'eksport zawiera odpowiedz bota', false !== strpos( $tresc, 'ODPOWIEDZ-A' ) );
rowne( 'przy malej liczbie danych konczy sie na pierwszej stronie', true, $wynik['done'] );

echo "\n=== C. SEDNO: wniosek jednej osoby NIE dotyka danych drugiej ===\n";
$wpdb->insert( $tz, array( 'email' => $mail_b, 'wiadomosc' => 'TRESC-B: pytanie klienta B', 'sesja' => $sesja_b, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
$wpdb->insert( $tr, array( 'sesja' => $sesja_b, 'rola' => 'gosc', 'silnik' => 'reguly', 'tresc' => 'ROZMOWA-B: sprawa klienta B', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore

$wynik_a = call_user_func( $eksportery['chatbot-live']['callback'], $mail_a, 1 );
$tresc_a = chatl_test_tresc_eksportu( $wynik_a );
sprawdz( 'eksport osoby A nie zawiera zgloszenia osoby B', false === strpos( $tresc_a, 'TRESC-B' ) );
sprawdz( 'ani rozmowy osoby B', false === strpos( $tresc_a, 'ROZMOWA-B' ) );

echo "\n=== D. Kasowanie usuwa dane wnioskodawcy — i tylko jego ===\n";
$wynik_k = call_user_func( $kasowniki['chatbot-live']['callback'], $mail_a, 1 );
rowne( 'kasownik melduje usuniecie', true, $wynik_k['items_removed'] );
rowne( 'i nie zostawia niczego po cichu', false, $wynik_k['items_retained'] );
sprawdz( 'wyjasnia wprost, czego nie da sie powiazac z adresem', false !== strpos( implode( ' ', (array) $wynik_k['messages'] ), 'retencja' ) );

rowne( 'zgloszenia osoby A skasowane', '0', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", $mail_a ) ) ); // phpcs:ignore
rowne( 'rozmowa osoby A skasowana', '0', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s", $sesja_a ) ) ); // phpcs:ignore
rowne( 'zgloszenie osoby B NIETKNIETE', '1', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", $mail_b ) ) ); // phpcs:ignore
rowne( 'rozmowa osoby B NIETKNIETA', '1', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s", $sesja_b ) ) ); // phpcs:ignore


echo "\n=== D2. Komunikat MOWI TO, CO SIE STALO (a nie zawsze to samo) ===\n";
/*
 * Uwaga z odbioru: komunikat kasownika brzmial „Usunieto zgloszenia oraz rozmowy"
 * BEZWARUNKOWO — takze wtedy, gdy nie znikl ani jeden wiersz. Wlasciciel odpowiada
 * tym tekstem osobie skladajacej wniosek RODO, wiec potwierdzal kasowanie, ktorego
 * nie bylo. To ta sama klasa bledu co falszywe „Mail zapisany".
 */
$komunikat_a = implode( ' ', (array) $wynik_k['messages'] );
sprawdz( 'przy realnym kasowaniu komunikat podaje LICZBY', 1 === preg_match( '/usunięto \\d+ zgłoszenie/u', $komunikat_a ), $komunikat_a );
sprawdz( 'i liczbe wiadomosci z rozmow', 1 === preg_match( '/\\d+ wiadomość/u', $komunikat_a ), $komunikat_a );

$wynik_pusty    = call_user_func( $kasowniki['chatbot-live']['callback'], 'rodo-test-nikt@example.test', 1 );
$komunikat_pusty = implode( ' ', (array) $wynik_pusty['messages'] );
rowne( 'wniosek o adres bez danych: items_removed = false', false, $wynik_pusty['items_removed'] );
sprawdz( 'komunikat NIE potwierdza kasowania, ktorego nie bylo', false === stripos( $komunikat_pusty, 'usunięto' ), $komunikat_pusty );
sprawdz( 'i mowi wprost, ze nic nie znaleziono', false !== strpos( $komunikat_pusty, 'Nie znaleziono' ), $komunikat_pusty );

echo "\n=== D3. SESJA NIE JEST WLASNOSCIA JEDNEGO ADRESU ===\n";
/*
 * Uwaga z odbioru, powazniejsza: rozmowa byla wiazana z SESJA, a nie z osoba.
 * Gdy w jednej sesji przegladarki zostana zostawione DWA rozne adresy (wspolny
 * komputer w recepcji, albo gosc, ktory rozmyslil sie i podal drugi adres),
 * jedna rozmowa zawiera wiadomosci DWOCH osob. Wniosek jednej z nich zabieral
 * wtedy dane drugiej — a eksport WYDAWAL jej tresc rozmowy osoby trzeciej.
 */
$mail_d1   = 'rodo-test-wspolna1@example.test';
$mail_d2   = 'rodo-test-wspolna2@example.test';
$sesja_wsp = 'rodoTestSesjaWSPOLNAAAAAAAAAAAAA';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email IN (%s, %s)", $mail_d1, $mail_d2 ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja = %s", $sesja_wsp ) ); // phpcs:ignore

$wpdb->insert( $tz, array( 'email' => $mail_d1, 'wiadomosc' => 'ZGL-WSP-1', 'sesja' => $sesja_wsp, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
$wpdb->insert( $tz, array( 'email' => $mail_d2, 'wiadomosc' => 'ZGL-WSP-2', 'sesja' => $sesja_wsp, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
$wpdb->insert( $tr, array( 'sesja' => $sesja_wsp, 'rola' => 'gosc', 'silnik' => 'reguly', 'tresc' => 'ROZMOWA-WSPOLNA: sprawa dwoch osob', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore

$podzial = chatl_rodo_sesje_maila( $mail_d1 );
rowne( 'sesja z dwoma adresami NIE jest sesja wylaczna', 0, count( $podzial['wlasne'] ) );
rowne( 'jest rozpoznana jako wspoldzielona', 1, count( $podzial['wspoldzielone'] ) );

$eksp_wsp  = call_user_func( $eksportery['chatbot-live']['callback'], $mail_d1, 1 );
$tresc_wsp = chatl_test_tresc_eksportu( $eksp_wsp );
sprawdz( 'EKSPORT nie wydaje tresci rozmowy dwoch osob', false === strpos( $tresc_wsp, 'ROZMOWA-WSPOLNA' ), $tresc_wsp );
sprawdz( 'ale wlasne zgloszenie wnioskodawcy wydaje', false !== strpos( $tresc_wsp, 'ZGL-WSP-1' ) );
sprawdz( 'nie wydaje zgloszenia tej drugiej osoby', false === strpos( $tresc_wsp, 'ZGL-WSP-2' ) );
sprawdz( 'i MOWI, ze rozmowa zostala pominieta (paczka nie udaje kompletu)', false !== strpos( $tresc_wsp, 'pominięte' ) || false !== strpos( $tresc_wsp, 'nie był jedynym' ), $tresc_wsp );

$kas_wsp   = call_user_func( $kasowniki['chatbot-live']['callback'], $mail_d1, 1 );
$komun_wsp = implode( ' ', (array) $kas_wsp['messages'] );
rowne( 'KASOWANIE zostawia rozmowe dwoch osob', '1', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s", $sesja_wsp ) ) ); // phpcs:ignore
rowne( 'zgloszenie DRUGIEJ osoby NIETKNIETE', '1', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", $mail_d2 ) ) ); // phpcs:ignore
rowne( 'wlasne zgloszenie wnioskodawcy skasowane', '0', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email = %s", $mail_d1 ) ) ); // phpcs:ignore
rowne( 'items_retained = true, bo cos ZOSTALO', true, $kas_wsp['items_retained'] );
sprawdz( 'i komunikat mowi, ze zachowano i dlaczego', false !== strpos( $komun_wsp, 'Zachowano' ) && false !== strpos( $komun_wsp, 'inna osoba' ), $komun_wsp );


echo "\n=== D4. Wnioski przychodza PO KOLEI, a nie naraz ===\n";
/*
 * Znalezisko recenzenta, ostrzejsze niz wyglada: wykrywanie sesji wspoldzielonej
 * czytalo BIEZACY stan tabeli zgloszen, a kasownik w tej samej operacji kasuje
 * zgloszenie wnioskodawcy — czyli niszczy dowod, ze sesja byla wspoldzielona.
 * Kreator WordPressa obsluguje wnioski jeden po drugim, czesto w odstepie dni,
 * wiec ochrona dzialalaby wylacznie dla wnioskow rozpatrywanych ROWNOCZESNIE.
 *
 * Sekwencja: A juz skasowal swoje dane (sekcja D3 wyzej). Teraz wniosek sklada B.
 */
$podzial_po = chatl_rodo_sesje_maila( $mail_d2 );
rowne( 'po skasowaniu danych A sesja NADAL jest wspoldzielona dla B', 1, count( $podzial_po['wspoldzielone'] ) );
rowne( 'i nie stala sie wylaczna wlasnoscia B', 0, count( $podzial_po['wlasne'] ) );

$eksp_b  = call_user_func( $eksportery['chatbot-live']['callback'], $mail_d2, 1 );
$tresc_b = chatl_test_tresc_eksportu( $eksp_b );
sprawdz( 'eksport dla B NIE wydaje rozmowy z wiadomosciami osoby A', false === strpos( $tresc_b, 'ROZMOWA-WSPOLNA' ), $tresc_b );

$kas_b = call_user_func( $kasowniki['chatbot-live']['callback'], $mail_d2, 1 );
rowne( 'i kasowanie dla B tez jej nie zabiera', '1', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tr} WHERE sesja = %s", $sesja_wsp ) ) ); // phpcs:ignore
rowne( 'items_retained dla B tez true', true, $kas_b['items_retained'] );

// Slad nie moze rosnac w nieskonczonosc: sesja bez ani jednej wiadomosci wypada.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja = %s", $sesja_wsp ) ); // phpcs:ignore
chatl_rodo_zapamietaj_wspolne( array( 'rodoTestSesjaDOPRZYCIECIA00000' ) );
$slad = chatl_rodo_slad_wspolnych();
sprawdz( 'sesja bez rozmowy wypada ze sladu (opcja nie rosnie bez konca)', ! in_array( $sesja_wsp, $slad, true ), implode( ',', $slad ) );
sprawdz( 'i nowa sesja bez rozmowy tez tam nie zostaje', ! in_array( 'rodoTestSesjaDOPRZYCIECIA00000', $slad, true ), implode( ',', $slad ) );

echo "\n=== E. Stronicowanie oddaje KOMPLET (eksport z dziura jest niewidoczny) ===\n";
/*
 * Najgrozniejszy przypadek to DOKLADNIE pelna strona zgloszen: naiwne liczenie
 * przesuniecia przeskakuje wtedy pierwsza porcje rozmow i wniosek wychodzi
 * z dziura, ktorej nie zauwazy ani wlasciciel, ani osoba skladajaca wniosek.
 */
$mail_c  = 'rodo-test-c@example.test';
$sesja_c = 'rodoTestSesjaCCCCCCCCCCCCCCCCCCC';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email = %s", $mail_c ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja = %s", $sesja_c ) ); // phpcs:ignore

$ile_zgloszen = (int) CHATL_RODO_PORCJA; // dokladnie pelna strona
for ( $i = 0; $i < $ile_zgloszen; $i++ ) {
	$wpdb->insert( $tz, array( 'email' => $mail_c, 'wiadomosc' => 'ZGL-' . $i, 'sesja' => $sesja_c, 'status' => 'nowe', 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
}
$ile_rozmow = 5;
for ( $i = 0; $i < $ile_rozmow; $i++ ) {
	$wpdb->insert( $tr, array( 'sesja' => $sesja_c, 'rola' => 'gosc', 'silnik' => 'reguly', 'tresc' => 'ROZ-' . $i, 'utworzono' => current_time( 'mysql' ) ) ); // phpcs:ignore
}

$wszystko = '';
$strona   = 1;
do {
	$w         = call_user_func( $eksportery['chatbot-live']['callback'], $mail_c, $strona );
	$wszystko .= chatl_test_tresc_eksportu( $w );
	++$strona;
} while ( empty( $w['done'] ) && $strona < 20 );

sprawdz( 'kreator dostal wiecej niz jedna strone', $strona > 2, 'stron: ' . ( $strona - 1 ) );
$brakujace = array();
for ( $i = 0; $i < $ile_zgloszen; $i++ ) {
	if ( false === strpos( $wszystko, 'ZGL-' . $i . "\n" ) ) {
		$brakujace[] = 'ZGL-' . $i;
	}
}
for ( $i = 0; $i < $ile_rozmow; $i++ ) {
	if ( false === strpos( $wszystko, 'ROZ-' . $i . "\n" ) ) {
		$brakujace[] = 'ROZ-' . $i;
	}
}
rowne( 'zaden wiersz nie wypadl ze stronicowania', 0, count( $brakujace ), 'brakuje: ' . implode( ',', array_slice( $brakujace, 0, 5 ) ) );

echo "\n=== F. Podpowiedz do polityki prywatnosci ===\n";
sprawdz( 'wtyczka dokłada tresc do polityki prywatnosci', has_action( 'admin_init', 'chatl_polityka_prywatnosci' ) !== false );

echo "\n=== G. Sprzatanie ===\n";
delete_option( CHATL_RODO_OPCJA_WSPOLNE );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email IN (%s, %s, %s, %s, %s)", $mail_a, $mail_b, $mail_c, $mail_d1, $mail_d2 ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja IN (%s, %s, %s, %s)", $sesja_a, $sesja_b, $sesja_c, $sesja_wsp ) ); // phpcs:ignore
rowne( 'dane testowe usuniete', '0', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz} WHERE email IN (%s, %s, %s)", $mail_a, $mail_b, $mail_c ) ) ); // phpcs:ignore

echo "\n" . ( $GLOBALS['chatl_test_bledy'] ? 'NIEPOWODZENIE: ' . $GLOBALS['chatl_test_bledy'] . ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY' ) . "\n";
exit( $GLOBALS['chatl_test_bledy'] ? 1 : 0 );
