<?php
/**
 * L3 — weryfikacja podpisu webhooka Stripe (sklepag_stripe_weryfikuj_podpis).
 * Test-strażnik, kod wyjścia 1 przy porażce. Woła PRAWDZIWĄ funkcję produkcyjną
 * (nie kopię zapytania — lekcja z sondy, która mierzyła zapytanie zamiast ścieżki).
 *
 * Dotąd odrzut złego podpisu dowodziły tylko raporty audytu L3 na żywym stosie;
 * ta para (test + próba kontrolna) czyni z tego strażnika CI (dług z CO-WISI §#226 r2).
 *
 * Uruchomienie:
 *   docker cp testy/webhook-podpis-test.php strona_wp_wordpress:/tmp/wh.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/wh.php
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

$sekret = 'whsec_TEST_' . wp_generate_password( 24, false, false );
$body   = '{"id":"evt_test","type":"checkout.session.completed"}';
$t      = time();
$dobry  = hash_hmac( 'sha256', $t . '.' . $body, $sekret );

sprawdz( 'A. poprawny podpis → przyjęty', true === sklepag_stripe_weryfikuj_podpis( $body, "t=$t,v1=$dobry", $sekret ) );
sprawdz( 'B. zły podpis (deadbeef) → odrzut', false === sklepag_stripe_weryfikuj_podpis( $body, "t=$t,v1=deadbeef", $sekret ) );
sprawdz( 'C. podpis dobrym sekretem, ale INNY sekret w opcji (rotacja) → odrzut', false === sklepag_stripe_weryfikuj_podpis( $body, "t=$t,v1=$dobry", 'whsec_INNY' ) );
$stary = $t - 400;
$pods  = hash_hmac( 'sha256', $stary . '.' . $body, $sekret );
sprawdz( 'D. znacznik czasu sprzed 400 s (tolerancja 300) → odrzut', false === sklepag_stripe_weryfikuj_podpis( $body, "t=$stary,v1=$pods", $sekret ) );
sprawdz( 'E. brak nagłówka → odrzut', false === sklepag_stripe_weryfikuj_podpis( $body, '', $sekret ) );
sprawdz( 'F. podmienione body przy dobrym podpisie → odrzut', false === sklepag_stripe_weryfikuj_podpis( $body . 'X', "t=$t,v1=$dobry", $sekret ) );
sprawdz( 'G. dwa v1, jeden dobry (rotacja po stronie Stripe) → przyjęty', true === sklepag_stripe_weryfikuj_podpis( $body, "t=$t,v1=deadbeef,v1=$dobry", $sekret ) );

echo "\nBŁĘDY: {$GLOBALS['sklepag_test_bledy']}\n";
exit( $GLOBALS['sklepag_test_bledy'] > 0 ? 1 : 0 );
