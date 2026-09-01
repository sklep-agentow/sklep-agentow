<?php
/**
 * STRAŻNIK REGRESJI — SK-L3-005 (okno idempotencji oddawało JUŻ OPŁACONĄ sesję Stripe).
 *
 * Wada: sklepag_obsluz_kup() trzyma przez 60 s transient `sklepag_idem_<md5(IP|produkt|tryb)>`
 * z adresem checkoutu i przy każdej kolejnej próbie zakupu przekierowuje na ZAPAMIĘTANY
 * adres — bez sprawdzenia, czy tamta sesja nie została w międzyczasie opłacona. Kto zapłacił
 * i w ciągu minuty klika przycisk zakupu jeszcze raz, trafia na opłacony checkout (ekran
 * Stripe bez pól karty) zamiast na nowy zakup. Okno jest kluczowane REMOTE_ADDR, więc za
 * wspólnym adresem (CGNAT operatora, biuro za NAT) dotyka DWÓCH RÓŻNYCH kupujących.
 *
 * Test sprawdza TRZY kierunki, bo naprawa polegająca na wyłączeniu okna też usunęłaby objaw —
 * i zabrałaby ochronę przed podwójnym kliknięciem, która jest zamierzona:
 *   A) sesja OPŁACONA w oknie                => NIE wolno oddać starego adresu (nowy checkout),
 *   B) sesja CZEKAJĄCA w ŚWIEŻYM oknie       => WOLNO (podwójny klik nie tworzy dwóch płatności),
 *   C) sesja CZEKAJĄCA w STARYM oknie        => NIE wolno — zapłacone leży u nas jako `utworzona`,
 *      dopóki nie wróci webhook, więc w bazie C i B to DOKŁADNIE ten sam wiersz i rozstrzyga
 *      wyłącznie wiek okna. Bez C strażnik nie odróżniłby naprawy od jej braku w tym kierunku.
 *
 * Odpowiedź Stripe podstawiona filtrem rdzenia `pre_http_request` — test nie rusza sieci
 * ani plików wtyczki, więc mierzy PRODUKT, nie atrapę.
 *
 * Uruchomienie:
 *   docker cp testy/idem-nie-oddaje-oplaconej-sesji-test.php <kontener>:/tmp/t.php
 *   docker exec -u www-data <kontener> wp --path=/var/www/html eval-file /tmp/t.php
 *
 * EXIT 0 = A, B i C zgodne z oczekiwaniem · 1 = któryś nie (REGRESJA)
 *      2 = środowisko: brak aktywnego produktu z plikiem ALBO wyczerpany limiter zakupu
 *          (10 prób na adres i minutę; w CLI adres jest stały, więc licznik dzielą wszystkie
 *          przebiegi. Strażnik woła handler 3 razy na przebieg, więc limiter dobija na 11.
 *          WYWOŁANIU w obrębie jednej minuty. ⛔ Nie przeliczaj tego na numer przebiegu:
 *          licznik chodzi po MINUTACH, więc przebiegi rozłożone w czasie nie dobiją go wcale
 *          (zmierzone: 4 przebiegi rozłożone = 4× EXIT=0; 4 pod rząd = EXIT=2 na czwartym).
 *          Kod 2 NIE znaczy regresji.
 */

global $wpdb;
$ps = $wpdb->prefix . 'sklepag_sesje_checkout';

$produkt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}sklepag_produkty WHERE aktywny = 1 LIMIT 1" );
if ( ! $produkt || '' === $produkt->plik_sciezka || ! file_exists( $produkt->plik_sciezka ) ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak aktywnego produktu z plikiem)\n";
	exit( 2 );
}

$stary_sid = 'cs_test_STARA_SESJA_STRAZNIK';
$stary_url = 'https://checkout.stripe.com/c/pay/' . $stary_sid;
$nowy_sid  = 'cs_test_NOWA_SESJA_STRAZNIK';
$nowy_url  = 'https://checkout.stripe.com/c/pay/' . $nowy_sid;
/* ⛔ KLUCZ BIERZEMY Z PRODUKCJI, nie skladamy go u siebie. Druga kopia rozjechala sie
   z oryginalem przy 0.9.25 (doszedl trzeci czlon: tryb platnosci) i straznik przez szesc
   wydan podkladal okno pod adresem, ktorego handler nigdy nie czytal — nie mierzyl NICZEGO
   i nie mowil o tym ani slowem. `false` = sciezka hostowana, bo tak wola go ten test. */
if ( ! function_exists( 'sklepag_klucz_idem' ) ) {
	echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (brak sklepag_klucz_idem — wtyczka sprzed 0.9.33?)\n";
	exit( 2 );
}
$okno      = sklepag_klucz_idem( (int) $produkt->id, false );
// Prog czytamy z produktu; gdy stalej nie ma, to znaczy, ze badamy kod SPRZED naprawy —
// wtedy bierzemy wartosc kalibracyjna, zeby straznik dal sie w ogole uruchomic i PADL.
$prog_okna = defined( 'SKLEPAG_IDEM_OKNO_S' ) ? SKLEPAG_IDEM_OKNO_S : 5;

// Atrapa Stripe: utworzenie sesji zwraca NOWY identyfikator, w kształcie prawdziwej
// odpowiedzi /v1/checkout/sessions (id + url).
$atrapa = static function ( $krotki, $args, $url ) use ( $nowy_sid, $nowy_url ) {
	if ( false !== strpos( $url, 'api.stripe.com/v1/checkout/sessions' ) ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => wp_json_encode( array( 'id' => $nowy_sid, 'url' => $nowy_url ) ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}
	return $krotki;
};
add_filter( 'pre_http_request', $atrapa, 10, 3 );

$posprzataj = static function () use ( $wpdb, $ps, $okno, $stary_sid, $nowy_sid ) {
	delete_transient( $okno );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$ps} WHERE stripe_session_id IN (%s,%s)", $stary_sid, $nowy_sid ) );
};

/** Woła PRAWDZIWY handler i zwraca adres, na który przekierował. */
$probuj_kup = static function () use ( $produkt ) {
	$_POST['produkt_id']  = (int) $produkt->id;
	$_POST['zgoda']       = '1';
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'sklepag_kup' );
	$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	$lap                  = static function ( $u ) {
		throw new Exception( 'REDIRECT:' . $u );
	};
	// wp_die() pod `wp eval-file` trafia na uchwyt WP-CLI: drukuje `Error: …` i KONCZY PROCES,
	// zamiast rzucic wyjatek — wiec sam `catch` nigdy nie zobaczy limitera (recenzja #199 r2).
	// Podmieniamy uchwyt na rzucajacy, tak jak juz zrobiono z wp_redirect.
	$lap_die              = static function () {
		return static function ( $tresc ) {
			if ( is_wp_error( $tresc ) ) {
				$tresc = $tresc->get_error_message();
			}
			throw new Exception( 'WPDIE:' . wp_strip_all_tags( (string) $tresc ) );
		};
	};
	add_filter( 'wp_die_handler', $lap_die, 99 );
	add_filter( 'wp_redirect', $lap, 1 );
	$gdzie = '';
	try {
		sklepag_obsluz_kup();
	} catch ( Throwable $e ) {
		$tresc = $e->getMessage();
		// Handler zaczyna od limitera (10 prob na IP i minute), a w CLI sklepag_ip() zwraca
		// stale 127.0.0.1 (zmierzone; REMOTE_ADDR ustawia WP-CLI) — wiec licznik jest WSPOLNY
		// dla wszystkich przebiegow. Straznik wola
		// handler 3 razy na przebieg, wiec limiter dobija na 11. WYWOLANIU w obrebie minuty.
		// Konczylo sie to kodem 1, czyli slowem REGRESJA, choc zaden kod sie nie zmienil.
		// To awaria srodowiska: kod 2, zgodnie z naglowkiem pliku.
		// ⛔ Nie przeliczaj tego na numer przebiegu — licznik chodzi po MINUTACH, wiec przebiegi
		// rozlozone w czasie nie dobija go wcale (patrz naglowek).
		if ( false !== strpos( $tresc, 'Za dużo prób zakupu' ) ) {
			echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (limiter zakupu: 10 prob na minute z tego adresu;\n";
			echo "          w CLI adres jest staly, wiec licznik dziela wszystkie przebiegi.\n";
			echo "          Odczekaj do pelnej minuty i uruchom ponownie.)\n";
			exit( 2 );
		}
		$gdzie = str_replace( array( 'REDIRECT:', 'WPDIE:' ), '', $tresc );
	}
	remove_filter( 'wp_redirect', $lap, 1 );
	remove_filter( 'wp_die_handler', $lap_die, 99 );
	return $gdzie;
};

$wynik = array();

/* ---------- A) WADA: w oknie siedzi sesja JUŻ OPŁACONA ---------- */
$posprzataj();
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'oplacona',%s)",
		$stary_sid,
		(int) $produkt->id,
		current_time( 'mysql' )
	)
);
set_transient( $okno, array( 'url' => $stary_url, 'sid' => $stary_sid, 'czas' => time() ), MINUTE_IN_SECONDS );
$a    = $probuj_kup();
// ⛔ ROWNOSC z nowym adresem, nie sama roznosc od starego: `wp_die` jest teraz przechwytywane
// i jego tresc trafia do $a, a komunikat bledu z definicji nie rowna sie $stary_url — wiec
// warunek `!==` mowil PASS takze przy CALKOWICIE zepsutej sciezce zakupu (recenzja #199 r3).
$a_ok = ( $a === $nowy_url );
echo 'A. okno z sesja OPLACONA -> ' . ( '' === $a ? '(brak przekierowania)' : $a ) . "\n";
echo '   OCZEKIWANE: ' . $nowy_url . " (NOWY checkout, nie sam brak starego)\n";
echo '   WYNIK=' . ( $a_ok ? 'PASS' : 'FAIL - nie powstal nowy checkout (oddal oplacona sesje albo sciezka zakupu jest zepsuta)' ) . "\n";
$wynik[] = $a_ok;

/* ---------- B) KONTROLA: w oknie siedzi sesja CZEKAJĄCA (ochrona ma działać) ---------- */
$posprzataj();
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'utworzona',%s)",
		$stary_sid,
		(int) $produkt->id,
		current_time( 'mysql' )
	)
);
set_transient( $okno, array( 'url' => $stary_url, 'sid' => $stary_sid, 'czas' => time() ), MINUTE_IN_SECONDS );
$b    = $probuj_kup();
$b_ok = ( $b === $stary_url );
echo 'B. okno z sesja CZEKAJACA -> ' . ( '' === $b ? '(brak przekierowania)' : $b ) . "\n";
echo '   OCZEKIWANE: ' . $stary_url . " (podwojny klik nie tworzy drugiej platnosci)\n";
echo '   WYNIK=' . ( $b_ok ? 'PASS' : 'FAIL - ochrona przed podwojnym klikiem przestala dzialac' ) . "\n";
$wynik[] = $b_ok;

/* ---------- C) WEBHOOK W DRODZE: zaplacone u Stripe, u nas wciaz `utworzona` ---------- */
/* To jest ta sama tresc wiersza co w przypadku B — rozstrzyga WYLACZNIE wiek okna.
   `oplacona` zapisuje tylko sklepag_domknij_zakup() (webhook / reconciliation), a
   reconciliation pomija sesje mlodsze niz 120 s. Przez pierwsze sekundy po zaplacie
   stan lokalny NIE odroznia sesji oplaconej od czekajacej — dlatego wiek okna jest
   warunkiem nadrzednym. Uwaga recenzenta WAZNA 1 do PR #199. */
$posprzataj();
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$ps} (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s,%d,'utworzona',%s)",
		$stary_sid,
		(int) $produkt->id,
		current_time( 'mysql' )
	)
);
set_transient(
	$okno,
	array(
		'url'  => $stary_url,
		'sid'  => $stary_sid,
		'czas' => time() - ( $prog_okna + 25 ),
	),
	MINUTE_IN_SECONDS
);
$c    = $probuj_kup();
$c_ok = ( $c === $nowy_url );   // jw. — rownosc z nowym checkoutem, nie sama roznosc
echo 'C. okno STARE (' . ( $prog_okna + 25 ) . ' s), sesja u nas wciaz CZEKAJACA -> ' . ( '' === $c ? '(brak przekierowania)' : $c ) . "\n";
echo '   OCZEKIWANE: ' . $nowy_url . " (NOWY checkout; po tylu sekundach to nie jest podwojny klik)\n";
echo '   WYNIK=' . ( $c_ok ? 'PASS' : 'FAIL - nie powstal nowy checkout (oddal sesje, ktora mogla juz byc oplacona, albo zakup jest zepsuty)' ) . "\n";
$wynik[] = $c_ok;

$posprzataj();
remove_filter( 'pre_http_request', $atrapa, 10 );

// Kod wyjścia MUSI zgadzać się z werdyktem — inaczej strażnik pisze FAIL, a CI widzi zero.
$ok = ! in_array( false, $wynik, true );
echo 'RAZEM: WYNIK=' . ( $ok ? 'PASS' : 'FAIL' ) . "\n";
exit( $ok ? 0 : 1 );
