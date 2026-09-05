<?php
/**
 * ChatBot LIVE — dobowy stop-loss AI: rezerwacja slotu ATOMOWA, przed płatnym wywołaniem. Test-strażnik: EXIT 1 przy porażce.
 *
 * Co się psuło: `chatl_ai_dostepny()` tylko CZYTAŁO licznik, a licznik rósł dopiero PO odpowiedzi API
 * (`chatl_ai_zuzyj()`) — klasyczne check-then-act: N równoczesnych żądań przy ostatnim slocie wszystkie
 * przechodziły kontrolę i wszystkie odpalały płatne wywołanie, więc limit dobowy dało się przekroczyć o N.
 * Ten sam plik miał już atomowy licznik w `chatl_rate_limit()` — stop-loss (mechanizm chroniący budżet) nie.
 *
 * Mierzymy SKUTEK w bazie: po rezerwacji ponad limit licznik NIE przekracza limitu; zwolnienie oddaje slot;
 * zapis tokenów po sukcesie NIE dokłada drugiego wywołania.
 *
 * Uruchomienie:
 *   docker cp testy/chatbot-stoploss-rezerwacja-test.php strona_wp_wordpress:/tmp/sl.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/sl.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

global $wpdb;
$t         = $wpdb->prefix . 'chatl_ai_uzycie';
$dzien     = current_time( 'Y-m-d' );
$stare_ust = get_option( 'chatl_ustawienia', array() );
$wiersz    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE dzien = %s", $dzien ), ARRAY_A ); // phpcs:ignore
$start     = (int) ( $wiersz['wywolania'] ?? 0 );

$licznik = function () use ( $wpdb, $t, $dzien ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT wywolania FROM {$t} WHERE dzien = %s", $dzien ) ); // phpcs:ignore
};

try {
	echo "=== A. Rezerwacja przy ostatnim slocie: N prób, licznik NIGDY ponad limit ===\n";
	$ust                     = chatl_ustawienia();
	$ust['ai_limit_dzienny'] = $start + 2;
	update_option( 'chatl_ustawienia', $ust );
	sprawdz( 'funkcja rezerwacji istnieje', function_exists( 'chatl_ai_zarezerwuj' ) );
	$r1 = chatl_ai_zarezerwuj();
	$r2 = chatl_ai_zarezerwuj();
	$r3 = chatl_ai_zarezerwuj();
	$r4 = chatl_ai_zarezerwuj();
	sprawdz( 'dwa sloty przechodzą, trzeci i czwarty odbite', $r1 && $r2 && ! $r3 && ! $r4, var_export( array( $r1, $r2, $r3, $r4 ), true ) );
	rowne( 'licznik = dokładnie limit (odbite próby nie zostawiają śladu)', $start + 2, $licznik() );

	echo "\n=== B. Zwolnienie slotu po nieudanym wywołaniu oddaje miejsce ===\n";
	chatl_ai_zwolnij();
	rowne( 'po zwolnieniu licznik = limit - 1', $start + 1, $licznik() );
	sprawdz( 'zwolniony slot da się wziąć ponownie', chatl_ai_zarezerwuj() );
	rowne( 'licznik znów = limit', $start + 2, $licznik() );

	echo "\n=== C. Zapis tokenów po sukcesie NIE dokłada drugiego wywołania ===\n";
	chatl_ai_zuzyj( array( 'tokeny_wejscia' => 10, 'tokeny_wyjscia' => 5 ) );
	rowne( 'wywołania bez zmian po zapisie tokenów', $start + 2, $licznik() );
	$d = chatl_ai_zuzycie_dnia();
	sprawdz( 'tokeny doliczone', (int) $d['tokeny_wejscia'] >= 10 && (int) $d['tokeny_wyjscia'] >= 5 );

	echo "\n=== D. Kontrola odwrotna: przy wolnym limicie rezerwacja przechodzi ===\n";
	$ust['ai_limit_dzienny'] = $start + 100;
	update_option( 'chatl_ustawienia', $ust );
	sprawdz( 'rezerwacja przechodzi przy wolnym limicie', chatl_ai_zarezerwuj() );
} finally {
	update_option( 'chatl_ustawienia', $stare_ust );
	if ( $wiersz ) {
		$wpdb->update( $t, array( 'wywolania' => $wiersz['wywolania'], 'tokeny_wejscia' => $wiersz['tokeny_wejscia'], 'tokeny_wyjscia' => $wiersz['tokeny_wyjscia'] ), array( 'dzien' => $dzien ) ); // phpcs:ignore
	} else {
		$wpdb->delete( $t, array( 'dzien' => $dzien ) ); // phpcs:ignore
	}
	sprawdz( 'licznik dnia i ustawienia przywrócone', $licznik() === $start );
}

if ( $GLOBALS['chatl_test_bledy'] > 0 ) {
	echo "NIEPOWODZENIE: {$GLOBALS['chatl_test_bledy']} asercji nie przeszło\n";
	exit( 1 );
}
echo "WSZYSTKIE ASERCJE PRZESZLY (rezerwacja atomowa, licznik nigdy ponad limit)\n";
exit( 0 );
