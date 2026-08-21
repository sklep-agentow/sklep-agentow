<?php
/**
 * STRAŻNIK DK-6: zamek `segm_zajmij_blokade()` ma wpuścić DOKŁADNIE JEDNEGO.
 *
 * Zamek stoi na `get_option()` (sprawdź) + `add_option()` (zajmij) — to dwa
 * kroki, więc ma okno wyścigu. Test odtwarza dokładnie ten układ, w którym
 * dwa procesy startują razem:
 *   A: get_option() -> nie ma znacznika (proces A zapamiętuje "nie ma")
 *   B: zajmuje znacznik (wiersz ląduje w bazie, poza cache procesu A)
 *   A: add_option() -> czy A też uzna, że zajął?
 *
 * EXIT 0 = wpuszczony jeden, EXIT 1 = wpuszczeni dwaj naraz.
 * Test kasuje po sobie znacznik w każdym przypadku.
 */
global $wpdb;
if ( ! defined( 'SEGM_BLOKADA' ) ) {
	echo "POMINIETO: wtyczka segregator-maila nie jest zaladowana.\n";
	exit( 0 );
}
$nazwa = SEGM_BLOKADA;

// czysty start
delete_option( $nazwa );

// --- PROBA KONTROLNA (przypadek BEZ wady): dwa zajecia PO KOLEI w jednym procesie ---
$a1 = segm_zajmij_blokade( 'kontrola: pierwszy' );
$a2 = segm_zajmij_blokade( 'kontrola: drugi' );
printf( "kontrola (po kolei):  pierwszy=%s  drugi=%s\n", var_export( $a1, true ), var_export( $a2, true ) );
$kontrola_ok = ( true === $a1 && true !== $a2 );
segm_zwolnij_blokade();

// --- SCIEZKA BADANA: uklad wyscigu ---
delete_option( $nazwa );
// A sprawdza — znacznika nie ma. Od tej chwili proces A "wie", ze go nie ma.
$widzi_a = get_option( $nazwa );
// B (inny proces PHP) zajmuje znacznik. Piszemy wprost do bazy, bo proces B
// ma wlasny cache i wpis A go nie widzi — dokladnie jak przy dwoch zadaniach.
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
		$nazwa,
		maybe_serialize( segm_stempel_blokady( 'proces B: przebieg segregacji' ) )
	)
);
$b_trzyma = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $nazwa ) );
// A konczy swoje zajmowanie.
$a_wynik = segm_zajmij_blokade( 'proces A: przebieg segregacji' );

printf( "wyscig:  A widzial przed startem: %s | B trzyma znacznik w bazie: %s | A dostal: %s\n",
	var_export( $widzi_a, true ), $b_trzyma ? 'tak' : 'nie', var_export( $a_wynik, true ) );

$kto_teraz = get_option( $nazwa );
printf( "znacznik po wszystkim nalezy do: %s\n", is_array( $kto_teraz ) && isset( $kto_teraz['opis'] ) ? $kto_teraz['opis'] : '(brak)' );

// sprzatanie
delete_option( $nazwa );
printf( "sprzatanie: znacznik = %s\n", var_export( get_option( $nazwa ), true ) );

if ( ! $kontrola_ok ) {
	echo "\nTEST NIESKALIBROWANY: zamek nie odrzucil drugiego nawet po kolei.\n";
	exit( 1 );
}
if ( true === $a_wynik ) {
	echo "\nWYNIK: PADA — B trzymal znacznik, a A tez dostal 'true'. Dwie operacje ida naraz.\n";
	exit( 1 );
}
echo "\nWYNIK: OK — w ukladzie wyscigu wpuszczony zostal tylko jeden.\n";
exit( 0 );
