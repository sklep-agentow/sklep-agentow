<?php
/**
 * Odinstalowanie. Wzorzec dojrzałych sklepów: dane sprzedaży domyślnie ZOSTAJĄ;
 * pełne czyszczenie tylko przy włączonej opcji sklepag_usun_dane_przy_odinstalowaniu.
 * WYJĄTEK: sekrety (klucz Stripe, sekret webhooka) to POŚWIADCZENIA, nie dane
 * sprzedaży — kasowane ZAWSZE (najczęstszy błąd wtyczek: „uninstall nie
 * kasował klucza API"). Symetria zapis<->sprzątanie: każda
 * rzecz zapisywana przez wtyczkę ma tu swój wiersz.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 0. Sekrety ZAWSZE out — martwa wtyczka nie zostawia poświadczeń w bazie.
delete_option( 'sklepag_stripe_klucz_sekretny' );
delete_option( 'sklepag_stripe_webhook_sekret' );

// Cron też ZAWSZE out, PRZED wczesnym return (ta sama klasa co A3-2 w Segregatorze):
// harmonogram to nie dane sprzedaży, a martwy wpis w cronie bez wtyczki
// tylko sypie błędami „nie ma takiej funkcji".
wp_clear_scheduled_hook( 'sklepag_dociagnij_zalegle' );

if ( ! get_option( 'sklepag_usun_dane_przy_odinstalowaniu' ) ) {
	return; // Reszta danych zostaje — świadoma decyzja domyślna.
}

global $wpdb;

// 1. Tabele (lista = ta sama siódemka co w aktywacji; nowa tabela = trzy miejsca).
foreach ( array( 'produkty', 'sesje_checkout', 'zamowienia', 'zdarzenia', 'logowania', 'rate_limit', 'opinie' ) as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sklepag_{$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- uninstall, nazwy stałe.
}

// 2. Strony wtyczki (twarde usunięcie — utworzyliśmy je my).
foreach ( array( 'sklepag_strona_sklep', 'sklepag_strona_dziekujemy', 'sklepag_strona_regulamin', 'sklepag_strona_polityka' ) as $opcja ) {
	$strona_id = (int) get_option( $opcja, 0 ); // Nie $id — to global WP (sniff GlobalVariablesOverride).
	if ( $strona_id ) {
		wp_delete_post( $strona_id, true );
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

// 4. Wszystkie opcje wtyczki (na końcu — wcześniej były potrzebne).
// esc_like() eskejpuje podkreślnik (SQL wildcard) + prepare — wzorzec Handbook,
// nie skrót WC/EDD (goły LIKE bez prepare łamie regułę WPCS). Chroni cudze opcje.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'sklepag_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall.

// 4b. TRANSIENTY wtyczki. Transients API zapisuje je w wp_options POD WŁASNYM prefiksem
// (`_transient_sklepag_…` i `_transient_timeout_sklepag_…`), więc czyszczenie po samym
// „sklepag_" ich NIE łapie: po „usuń wszystkie dane" zostawał adres sesji Stripe i skrót IP
// z okna idempotencji zakupu. WP sprząta wygasłe transienty własnym cronem, ale deinstalacja
// nie może zostawiać danych i czekać na cudzy harmonogram.
foreach ( array( '_transient_sklepag_', '_transient_timeout_sklepag_' ) as $prefiks ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefiks ) . '%' ) );
}
// Instalacje z zewnętrznym cache obiektów (Redis/Memcached) trzymają transienty poza bazą —
// tam kasuje je dopiero delete_transient(), dlatego czyścimy OBIEMA drogami.
if ( wp_using_ext_object_cache() ) {
	wp_cache_flush();
}

// 5. User meta wtyczki: Screen Options „na stronę" zapisuje się per użytkownik
// w wp_usermeta, nie w wp_options (symetria zapis↔sprzątanie).
delete_metadata( 'user', 0, 'sklepag_zamowien_na_strone', '', true );
