<?php
/**
 * Odinstalowanie. Wzorzec dojrzałych sklepów: dane sprzedaży domyślnie ZOSTAJĄ;
 * pełne czyszczenie tylko przy włączonej opcji sklepag_usun_dane_przy_odinstalowaniu.
 * Symetria zapis<->sprzątanie: każda rzecz zapisywana przez wtyczkę ma tu swój wiersz.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'sklepag_usun_dane_przy_odinstalowaniu' ) ) {
	return; // Dane zostają — świadoma decyzja domyślna (DECYZJE D22).
}

global $wpdb;

// 1. Tabele (lista = ta sama szóstka co w aktywacji; nowa tabela = trzy miejsca).
foreach ( array( 'produkty', 'sesje_checkout', 'zamowienia', 'zdarzenia', 'logowania', 'rate_limit' ) as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sklepag_{$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- uninstall, nazwy stałe.
}

// 2. Strony wtyczki (twarde usunięcie — utworzyliśmy je my).
foreach ( array( 'sklepag_strona_sklep', 'sklepag_strona_dziekujemy', 'sklepag_strona_regulamin', 'sklepag_strona_polityka' ) as $opcja ) {
	$id = (int) get_option( $opcja, 0 );
	if ( $id ) {
		wp_delete_post( $id, true );
	}
}

// 3. Katalog chroniony z plikami produktów (bezpiecznik: kasujemy TYLKO nasz wzorzec ścieżki).
$katalog = (string) get_option( 'sklepag_katalog_chroniony', '' );
if ( $katalog && false !== strpos( $katalog, 'sklepag-chronione-' ) && is_dir( $katalog ) ) {
	$pliki = scandir( $katalog );
	if ( $pliki ) {
		foreach ( $pliki as $plik ) {
			if ( '.' === $plik || '..' === $plik ) {
				continue;
			}
			$pelna = trailingslashit( $katalog ) . $plik;
			// Best-effort: plik innego właściciela (deploy FTP userem != PHP) zostaje
			// bez PHP Warning. @ jest na allowliście WPCS NoSilencedErrors (unlink/rmdir).
			if ( is_file( $pelna ) ) {
				@unlink( $pelna ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- uninstall best-effort, obcy właściciel nie może wywalić deinstalacji.
			}
		}
	}
	// rmdir TYLKO gdy katalog realnie pusty — inaczej zostaje bez warninga (nie nasze pliki).
	$reszta = array_diff( (array) scandir( $katalog ), array( '.', '..' ) );
	if ( empty( $reszta ) ) {
		@rmdir( $katalog ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
	}
}

// 4. Cron.
wp_clear_scheduled_hook( 'sklepag_dociagnij_zalegle' );

// 5. Wszystkie opcje wtyczki (na końcu — wcześniej były potrzebne).
// esc_like() eskejpuje podkreślnik (SQL wildcard) + prepare — wzorzec Handbook,
// nie skrót WC/EDD (goły LIKE bez prepare łamie regułę WPCS). Chroni cudze opcje.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'sklepag_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall.
