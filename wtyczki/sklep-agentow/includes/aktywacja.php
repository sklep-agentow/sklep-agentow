<?php
/**
 * Aktywacja / deaktywacja / migracja schematu.
 * Zasady: dbDelta + wersja schematu w opcji; nowa tabela = TRZY miejsca
 * (tutaj, uninstall.php, migracja); symetria zapis<->sprzątanie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definicje tabel w formacie dbDelta (dwie spacje po PRIMARY KEY — wymóg dbDelta).
 */
function sklepag_tabele_sql() {
	global $wpdb;
	$c = $wpdb->get_charset_collate();
	$p = $wpdb->prefix . 'sklepag_';

	return array(
		"CREATE TABLE {$p}produkty (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nazwa varchar(200) NOT NULL,
			slug varchar(200) NOT NULL,
			opis_krotki text NOT NULL,
			opis_pelny longtext NOT NULL,
			cena_grosze int(11) unsigned NOT NULL DEFAULT 0,
			waluta char(3) NOT NULL DEFAULT 'PLN',
			plik_sciezka varchar(500) NOT NULL DEFAULT '',
			plik_rozmiar bigint(20) unsigned NOT NULL DEFAULT 0,
			plik_sha256 char(64) NOT NULL DEFAULT '',
			aktywny tinyint(1) NOT NULL DEFAULT 0,
			utworzono datetime NOT NULL,
			zmieniono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$c};",

		"CREATE TABLE {$p}sesje_checkout (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stripe_session_id varchar(255) NOT NULL,
			produkt_id bigint(20) unsigned NOT NULL,
			stan varchar(20) NOT NULL DEFAULT 'utworzona',
			utworzono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY stripe_session_id (stripe_session_id),
			KEY stan (stan)
		) {$c};",

		"CREATE TABLE {$p}zamowienia (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stripe_session_id varchar(255) NOT NULL,
			produkt_id bigint(20) unsigned NOT NULL,
			produkt_nazwa_kupiona varchar(200) NOT NULL,
			produkt_cena_kupiona int(11) unsigned NOT NULL DEFAULT 0,
			produkt_plik_kupiony varchar(500) NOT NULL DEFAULT '',
			email_kupujacego varchar(320) NOT NULL DEFAULT '',
			kwota_grosze int(11) unsigned NOT NULL DEFAULT 0,
			waluta char(3) NOT NULL DEFAULT 'PLN',
			status varchar(20) NOT NULL DEFAULT 'oczekuje',
			zgoda_tresc text,
			zgoda_czas datetime DEFAULT NULL,
			token_pobrania char(64) NOT NULL DEFAULT '',
			link_wygasa datetime DEFAULT NULL,
			pobrania_limit smallint(5) unsigned NOT NULL DEFAULT 10,
			pobrania_uzyte smallint(5) unsigned NOT NULL DEFAULT 0,
			mail_proby smallint(5) unsigned NOT NULL DEFAULT 0,
			mail_tresc longtext,
			utworzono datetime NOT NULL,
			zmieniono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY stripe_session_id (stripe_session_id),
			KEY status (status),
			KEY utworzono (utworzono)
		) {$c};",

		"CREATE TABLE {$p}zdarzenia (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			zamowienie_id bigint(20) unsigned DEFAULT NULL,
			typ varchar(50) NOT NULL,
			opis text NOT NULL,
			uzytkownik varchar(60) NOT NULL DEFAULT '',
			czas datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY zamowienie_id (zamowienie_id),
			KEY czas (czas)
		) {$c};",

		"CREATE TABLE {$p}logowania (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			czas datetime NOT NULL,
			login_wpisany varchar(60) NOT NULL DEFAULT '',
			wynik varchar(10) NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY czas (czas)
		) {$c};",

		"CREATE TABLE {$p}rate_limit (
			klucz varchar(64) NOT NULL,
			licznik int(11) unsigned NOT NULL DEFAULT 1,
			okno_do datetime NOT NULL,
			PRIMARY KEY  (klucz)
		) {$c};",
	);
}

function sklepag_aktywacja() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( sklepag_tabele_sql() as $sql ) {
		dbDelta( $sql );
	}
	update_option( 'sklepag_wersja_schematu', SKLEPAG_WERSJA_SCHEMATU, false );

	sklepag_utworz_strony();
	sklepag_utworz_katalog_chroniony();

	if ( ! wp_next_scheduled( 'sklepag_dociagnij_zalegle' ) ) {
		wp_schedule_event( time() + 600, 'sklepag_10min', 'sklepag_dociagnij_zalegle' );
	}
}

function sklepag_deaktywacja() {
	wp_clear_scheduled_hook( 'sklepag_dociagnij_zalegle' );
}

/**
 * Migracja przy podbiciu SKLEPAG_WERSJA_SCHEMATU (dbDelta jest idempotentne).
 */
function sklepag_migruj_schemat_jesli_trzeba() {
	if ( (int) get_option( 'sklepag_wersja_schematu', 0 ) === SKLEPAG_WERSJA_SCHEMATU ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( sklepag_tabele_sql() as $sql ) {
		dbDelta( $sql );
	}
	update_option( 'sklepag_wersja_schematu', SKLEPAG_WERSJA_SCHEMATU, false );
}

/**
 * Strony wtyczki. Sklep i Dziękujemy publikowane; Regulamin i Polityka jako
 * SZKICE-szablony (właściciel wypełnia danymi sprzedawcy i sam publikuje).
 */
function sklepag_utworz_strony() {
	$strony = array(
		'sklepag_strona_sklep'      => array( 'Sklep', '[sklepag_sklep]', 'publish' ),
		'sklepag_strona_dziekujemy' => array( 'Dziękujemy za zakup', '[sklepag_dziekujemy]', 'publish' ),
		'sklepag_strona_regulamin'  => array( 'Regulamin sklepu (SZABLON)', "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nSprzedawca: [NAZWA, ADRES, KONTAKT]\nProdukty: treści cyfrowe (wtyczki WordPressa) dostarczane natychmiast po opłaceniu.\nZgoda na natychmiastowe dostarczenie: kupujący wyraża ją przy zakupie i przyjmuje utratę prawa odstąpienia (art. 38 ust. o prawach konsumenta).\nReklamacje: [MAIL WŁAŚCICIELA].", 'draft' ),
		'sklepag_strona_polityka'   => array( 'Polityka prywatności sklepu (SZABLON)', "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nAdministrator danych: [NAZWA, KONTAKT]\nZakres: adres e-mail kupującego, dane transakcji, dziennik zdarzeń (90 dni).\nPodmioty przetwarzające: operator płatności Stripe; mechanizm poczty serwera.\nBoty w trybie AI wysyłają treść zapytań do dostawcy API — [UZUPEŁNIJ po włączeniu].", 'draft' ),
	);

	foreach ( $strony as $opcja => $dane ) {
		$id = (int) get_option( $opcja, 0 );
		if ( $id && get_post( $id ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_title'   => $dane[0],
				'post_content' => $dane[1],
				'post_status'  => $dane[2],
				'post_type'    => 'page',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( $opcja, $id, false );
		}
	}
}

/**
 * Katalog chroniony na pliki produktów: losowy sufiks + index.php + .htaccess
 * (Apache). Na nginx .htaccess nie działa — brama i tak jest w PHP, a instrukcja
 * wdrożenia podaje regułę location deny (plan v3 par. 4A).
 */
function sklepag_utworz_katalog_chroniony() {
	$istniejacy = get_option( 'sklepag_katalog_chroniony', '' );
	if ( $istniejacy && is_dir( $istniejacy ) ) {
		return;
	}

	$upload = wp_upload_dir();
	$sciezka = trailingslashit( $upload['basedir'] ) . 'sklepag-chronione-' . wp_generate_password( 16, false, false );

	if ( ! wp_mkdir_p( $sciezka ) ) {
		// Ta sama widzialność błędu co niżej — porażka mkdir to też cicha ślepota (recenzja PR #28).
		update_option( 'sklepag_katalog_chroniony_blad', 'mkdir nieudany — sprawdź uprawnienia uploads', false );
		return;
	}

	// Próba kontrolna zapisu (docker/uprawnienia) — bez niej cicha ślepota.
	$test = trailingslashit( $sciezka ) . 'index.php';
	$ok   = file_put_contents( $test, "<?php // cisza\n" );

	if ( false === $ok ) {
		// Sprzątamy świeży katalog — bez tego zostaje sierota, której uninstall nie zna (recenzja PR #28).
		rmdir( $sciezka ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		update_option( 'sklepag_katalog_chroniony_blad', 'zapis nieudany — sprawdź uprawnienia uploads', false );
		return;
	}

	// Apache 2.4 (Require) i 2.2 (Deny) — hosting docelowy nieznany (Z10); brama i tak w PHP.
	$ht = file_put_contents(
		trailingslashit( $sciezka ) . '.htaccess',
		"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
	);
	if ( false === $ht ) {
		// Ta sama próba kontrolna co dla index.php (recenzja PR #28, uwaga 2. rundy).
		unlink( trailingslashit( $sciezka ) . 'index.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rmdir( $sciezka ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		update_option( 'sklepag_katalog_chroniony_blad', 'zapis .htaccess nieudany — sprawdź uprawnienia uploads', false );
		return;
	}

	update_option( 'sklepag_katalog_chroniony', $sciezka, false );
	delete_option( 'sklepag_katalog_chroniony_blad' );
}
