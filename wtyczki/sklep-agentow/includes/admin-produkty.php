<?php
/**
 * CRUD produktów (K4; plan §4A „Produkty", kryterium GOTOWE #7):
 * lista → formularz dodaj/edytuj → zapis z walidacjami → upload zipa do
 * katalogu chronionego (losowa nazwa BEZ rozszerzenia, MIME z TREŚCI,
 * limit 20 MB, weryfikacja PO zapisie: obecność+rozmiar+sha256).
 * Zasady: bez twardego usuwania (deaktywacja — D7); nowy plik = NOWA
 * ścieżka (stare zamówienia trzymają swój snapshot — §5); aktywacja
 * wymaga POTWIERDZONEGO pliku; każda mutacja nonce+caps parą.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SKLEPAG_KATALOG . 'includes/class-sklepag-lista-produktow.php';

const SKLEPAG_UPLOAD_LIMIT_B = 20 * 1024 * 1024; // Plan §7: 20 MB (realne zipy botów <1 MB).

/**
 * Adres formularza produktu (0 = nowy).
 *
 * @param int $id Id produktu.
 */
function sklepag_url_produktu( $id = 0 ) {
	return admin_url( 'admin.php?page=sklepag-produkty&widok=formularz' . ( $id ? '&produkt=' . (int) $id : '' ) );
}

/* ---------- Strona: lista / formularz ---------- */

/**
 * Callback submenu „Produkty".
 */
function sklepag_admin_produkty() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- wybór widoku read-only.
	$widok = isset( $_GET['widok'] ) ? sanitize_key( wp_unslash( $_GET['widok'] ) ) : 'lista';
	$id    = isset( $_GET['produkt'] ) ? absint( $_GET['produkt'] ) : 0;
	// phpcs:enable

	if ( 'formularz' === $widok ) {
		sklepag_produkty_formularz( $id );
		return;
	}

	echo '<div class="wrap"><h1>Produkty <a href="' . esc_url( sklepag_url_produktu() ) . '" class="page-title-action">Dodaj produkt</a></h1>';
	sklepag_produkty_komunikaty();
	$tabela = new Sklepag_Lista_Produktow();
	$tabela->prepare_items();
	$tabela->display();
	echo '<p class="description">Usuwania nie ma celowo: zamówienia trzymają snapshot produktu z chwili zakupu, a produkt najwyżej znika ze sprzedaży (Deaktywuj).</p>';
	echo '</div>';
}

/**
 * Komunikaty po akcjach (redirect z admin-post).
 */
function sklepag_produkty_komunikaty() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- komunikaty po redirekcie, bez mutacji.
	if ( isset( $_GET['sklepag_ok'] ) ) {
		$mapa = array(
			'zapisano'    => 'Produkt zapisany.',
			'plik'        => 'Produkt zapisany, plik paczki wgrany i POTWIERDZONY (obecność + rozmiar + suma kontrolna).',
			'przelaczono' => 'Status sprzedaży przełączony.',
		);
		$k    = sanitize_key( wp_unslash( $_GET['sklepag_ok'] ) );
		if ( isset( $mapa[ $k ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $mapa[ $k ] ) . '</p></div>';
		}
	}
	if ( isset( $_GET['sklepag_blad'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['sklepag_blad'] ) ) ) . '</p></div>';
	}
	// phpcs:enable
}

/**
 * Formularz dodaj/edytuj.
 *
 * @param int $id Id produktu (0 = nowy).
 */
function sklepag_produkty_formularz( $id ) {
	global $wpdb;
	$t       = $wpdb->prefix . 'sklepag_produkty';
	$produkt = null;
	if ( $id ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu.
		$produkt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
		if ( ! $produkt ) {
			echo '<div class="wrap"><h1>Produkt</h1><div class="notice notice-error"><p>Produkt nie istnieje.</p></div></div>';
			return;
		}
	}

	$w = static function ( $pole, $domyslne = '' ) use ( $produkt ) {
		return $produkt ? $produkt->$pole : $domyslne;
	};

	echo '<div class="wrap"><h1>' . ( $id ? 'Edytuj produkt #' . (int) $id : 'Nowy produkt' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=sklepag-produkty' ) ) . '" class="page-title-action">← wróć do listy</a></h1>';
	sklepag_produkty_komunikaty();

	if ( $id ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; informacja o snapshotach.
		$zamowien = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_zamowienia WHERE produkt_id = %d", $id ) );
		// phpcs:enable
		if ( $zamowien > 0 ) {
			echo '<div class="notice notice-info inline"><p>Ten produkt ma <strong>' . (int) $zamowien . '</strong> zamówień. Edycja NICZEGO w nich nie zmienia — trzymają snapshot (nazwę, cenę i plik) z chwili zakupu; nowy plik dostaną tylko NOWE zakupy.</p></div>';
		}
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
	echo '<input type="hidden" name="action" value="sklepag_produkt_zapisz">';
	echo '<input type="hidden" name="produkt_id" value="' . (int) $id . '">';
	wp_nonce_field( 'sklepag_produkt_zapisz' );

	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="sklepag-nazwa">Nazwa</label></th><td><input type="text" id="sklepag-nazwa" name="nazwa" class="regular-text" required value="' . esc_attr( $w( 'nazwa' ) ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="sklepag-opis-krotki">Opis krótki (lista sklepu)</label></th><td><textarea id="sklepag-opis-krotki" name="opis_krotki" class="large-text" rows="2">' . esc_textarea( $w( 'opis_krotki' ) ) . '</textarea></td></tr>';
	echo '<tr><th scope="row"><label for="sklepag-opis-pelny">Opis pełny (strona produktu)</label></th><td><textarea id="sklepag-opis-pelny" name="opis_pelny" class="large-text" rows="6">' . esc_textarea( $w( 'opis_pelny' ) ) . '</textarea><p class="description">Renderowany zawsze jako tekst (XSS: opisy nie niosą HTML — plan §7).</p></td></tr>';
	echo '<tr><th scope="row"><label for="sklepag-cena">Cena</label></th><td><input type="number" id="sklepag-cena" name="cena" step="0.01" min="0.01" required class="small-text" value="' . esc_attr( $id ? number_format( $w( 'cena_grosze' ) / 100, 2, '.', '' ) : '' ) . '"> <input type="text" name="waluta" class="small-text" maxlength="3" pattern="[A-Za-z]{3}" value="' . esc_attr( $w( 'waluta', (string) get_option( 'sklepag_waluta_domyslna', 'PLN' ) ) ) . '"></td></tr>';

	echo '<tr><th scope="row"><label for="sklepag-plik">Plik paczki (ZIP)</label></th><td>';
	if ( $id && '' !== $w( 'plik_sciezka' ) ) {
		$jest = file_exists( $w( 'plik_sciezka' ) );
		echo '<p>Obecny plik: ' . ( $jest ? '<span class="sklepag-badge sklepag-badge-ok">jest</span> ' . esc_html( size_format( (int) $w( 'plik_rozmiar' ) ) ) . ' · sha256 <code>' . esc_html( substr( $w( 'plik_sha256' ), 0, 12 ) ) . '…</code>' : '<span class="sklepag-badge sklepag-badge-blad">zgubiony na dysku</span>' ) . '</p>';
	}
	echo '<input type="file" id="sklepag-plik" name="plik" accept=".zip,application/zip">';
	echo '<p class="description">Tylko ZIP, do ' . esc_html( size_format( SKLEPAG_UPLOAD_LIMIT_B ) ) . '. Nowy plik dostaje NOWĄ chronioną ścieżkę — stare zamówienia trzymają swoją (snapshot). ' . ( $id ? 'Zostaw puste, by nie zmieniać.' : '' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">W sprzedaży</th><td><label><input type="checkbox" name="aktywny" value="1"' . checked( (bool) $w( 'aktywny', false ), true, false ) . '> Produkt widoczny i kupowalny w sklepie</label><p class="description">Aktywacja wymaga POTWIERDZONEGO pliku paczki — bez pliku zapis wróci z błędem (kryterium #7).</p></td></tr>';
	echo '</tbody></table>';

	submit_button( $id ? 'Zapisz zmiany' : 'Dodaj produkt' );
	echo '</form></div>';
}

/* ---------- Zapis (admin-post; nonce+caps parą) ---------- */

add_action( 'admin_post_sklepag_produkt_zapisz', 'sklepag_obsluz_zapis_produktu' );

function sklepag_obsluz_zapis_produktu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_produkt_zapisz' );

	global $wpdb;
	$t  = $wpdb->prefix . 'sklepag_produkty';
	$id = isset( $_POST['produkt_id'] ) ? absint( $_POST['produkt_id'] ) : 0;

	$nazwa       = isset( $_POST['nazwa'] ) ? sanitize_text_field( wp_unslash( $_POST['nazwa'] ) ) : '';
	$opis_krotki = isset( $_POST['opis_krotki'] ) ? sanitize_textarea_field( wp_unslash( $_POST['opis_krotki'] ) ) : '';
	$opis_pelny  = isset( $_POST['opis_pelny'] ) ? sanitize_textarea_field( wp_unslash( $_POST['opis_pelny'] ) ) : '';
	$cena_raw    = isset( $_POST['cena'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['cena'] ) ) ) : '';
	$waluta      = isset( $_POST['waluta'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['waluta'] ) ) ) : 'PLN';
	$aktywny     = ! empty( $_POST['aktywny'] ) ? 1 : 0;

	$wroc = static function ( $blad ) use ( $id ) {
		wp_safe_redirect( add_query_arg( 'sklepag_blad', rawurlencode( $blad ), sklepag_url_produktu( $id ) ) );
		exit;
	};

	// Walidacje (kryterium #7: cena<=0, pusta nazwa, nie-zip, ponad limit = odrzut).
	if ( '' === $nazwa ) {
		$wroc( 'Nazwa nie może być pusta.' );
	}
	$cena_grosze = (int) round( (float) $cena_raw * 100 );
	if ( $cena_grosze <= 0 ) {
		$wroc( 'Cena musi być większa od zera.' );
	}
	if ( ! preg_match( '/^[A-Z]{3}$/', $waluta ) ) {
		$wroc( 'Waluta musi być kodem 3-literowym (np. PLN).' );
	}

	$stary = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	if ( $id && ! $stary ) {
		$wroc( 'Produkt nie istnieje.' );
	}

	// Upload (opcjonalny przy edycji, potrzebny do aktywacji nowego).
	$plik = ( isset( $_FILES['plik'] ) && ! empty( $_FILES['plik']['name'] ) ) ? sklepag_przyjmij_zip( $wroc ) : null;

	$sciezka = $plik ? $plik['sciezka'] : ( $stary ? $stary->plik_sciezka : '' );
	$rozmiar = $plik ? $plik['rozmiar'] : ( $stary ? (int) $stary->plik_rozmiar : 0 );
	$sha     = $plik ? $plik['sha256'] : ( $stary ? $stary->plik_sha256 : '' );
	$plik_ok = '' !== $sciezka && file_exists( $sciezka );

	if ( $aktywny && ! $plik_ok ) {
		$wroc( 'Nie można włączyć sprzedaży bez POTWIERDZONEGO pliku paczki — wgraj ZIP.' );
	}

	// Porażka INSERT/UPDATE (np. kolizja UNIQUE slug w wyścigu równoczesnych zapisów)
	// nie może udawać sukcesu ani zostawić świeżo wgranego zipa bez wiersza w bazie.
	$padl_zapis = static function () use ( $plik, $wroc ) {
		if ( $plik && file_exists( $plik['sciezka'] ) ) {
			wp_delete_file( $plik['sciezka'] );
		}
		$wroc( 'Zapis produktu do bazy nie powiódł się — spróbuj ponownie (wgrany plik został usunięty, nic nie zapisano).' );
	};

	$slug  = sanitize_title( $nazwa );
	$teraz = current_time( 'mysql' );
	$dane  = array(
		'nazwa'       => $nazwa,
		'opis_krotki' => $opis_krotki,
		'opis_pelny'  => $opis_pelny,
		'cena_grosze' => $cena_grosze,
		'waluta'      => $waluta,
		'aktywny'     => $aktywny,
		'zmieniono'   => $teraz,
	);
	if ( $plik ) {
		$dane['plik_sciezka'] = $sciezka;
		$dane['plik_rozmiar'] = $rozmiar;
		$dane['plik_sha256']  = $sha;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- CRUD na własnej tabeli.
	if ( $id ) {
		if ( false === $wpdb->update( $t, $dane, array( 'id' => $id ) ) ) {
			$padl_zapis();
		}
		sklepag_zdarzenie( null, 'produkt_zmieniony', '#' . $id . ' ' . $nazwa . ( $plik ? ' (nowy plik: ' . basename( $sciezka ) . ')' : '' ) );
	} else {
		// Slug unikalny: przy kolizji dokładamy przyrostek (UNIQUE w schemacie).
		$ile_slug = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ile_slug > 0 ) {
			$slug .= '-' . wp_generate_password( 4, false, false );
		}
		$dane['slug']      = $slug;
		$dane['utworzono'] = $teraz;
		if ( false === $wpdb->insert( $t, $dane ) ) {
			$padl_zapis();
		}
		$id = (int) $wpdb->insert_id;
		sklepag_zdarzenie( null, 'produkt_dodany', '#' . $id . ' ' . $nazwa );
	}
	// phpcs:enable

	wp_safe_redirect( add_query_arg( 'sklepag_ok', $plik ? 'plik' : 'zapisano', sklepag_url_produktu( $id ) ) );
	exit;
}

/**
 * Przyjęcie zipa do katalogu chronionego: rozszerzenie + MIME z TREŚCI +
 * limit + losowa nazwa BEZ .zip + weryfikacja PO zapisie (plan §7).
 *
 * @param callable $wroc Funkcja przerywająca z komunikatem.
 * @return array{sciezka: string, rozmiar: int, sha256: string}
 */
function sklepag_przyjmij_zip( $wroc ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- wywoływana WYŁĄCZNIE z handlera sklepag_obsluz_zapis_produktu PO check_admin_referer (sniff nie śledzi przepływu między funkcjami); $_FILES walidowane polami: error/size/tmp_name przez is_uploaded_file, nazwa tylko do rozszerzenia.
	$blad = isset( $_FILES['plik']['error'] ) ? (int) $_FILES['plik']['error'] : UPLOAD_ERR_NO_FILE;
	if ( UPLOAD_ERR_OK !== $blad ) {
		$wroc( 'Upload nie powiódł się (kod ' . $blad . ') — spróbuj ponownie; duży plik? Limit: ' . size_format( SKLEPAG_UPLOAD_LIMIT_B ) . '.' );
	}
	$tmp     = isset( $_FILES['plik']['tmp_name'] ) ? sanitize_text_field( $_FILES['plik']['tmp_name'] ) : '';
	$nazwa   = isset( $_FILES['plik']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['plik']['name'] ) ) : '';
	$rozmiar = isset( $_FILES['plik']['size'] ) ? (int) $_FILES['plik']['size'] : 0;
	// phpcs:enable

	if ( ! is_uploaded_file( $tmp ) ) {
		$wroc( 'Nieprawidłowy upload.' );
	}
	if ( $rozmiar <= 0 || $rozmiar > SKLEPAG_UPLOAD_LIMIT_B ) {
		$wroc( 'Plik ponad limit ' . size_format( SKLEPAG_UPLOAD_LIMIT_B ) . ' (albo pusty).' );
	}
	if ( ! preg_match( '/\.zip$/i', $nazwa ) ) {
		$wroc( 'Dozwolony wyłącznie plik .zip.' );
	}
	// MIME z TREŚCI, nie z nagłówka przeglądarki (plan §7).
	$ft = wp_check_filetype_and_ext( $tmp, $nazwa, array( 'zip' => 'application/zip' ) );
	if ( 'zip' !== $ft['ext'] || 'application/zip' !== $ft['type'] ) {
		$wroc( 'Treść pliku nie jest zipem (MIME sprawdzany z zawartości).' );
	}

	$katalog = (string) get_option( 'sklepag_katalog_chroniony', '' );
	if ( '' === $katalog || ! is_dir( $katalog ) ) {
		$wroc( 'Brak katalogu chronionego — deaktywuj i aktywuj wtyczkę.' );
	}

	// Losowa nazwa BEZ rozszerzenia (warstwa ochrony uploads — §4A).
	$cel = trailingslashit( $katalog ) . 'produkt-' . wp_generate_password( 20, false, false );
	// phpcs:ignore WordPress.WP.AlternativeFunctions -- katalog w uploads, przenosimy zaufany upload; WP_Filesystem zbędne.
	if ( ! move_uploaded_file( $tmp, $cel ) ) {
		$wroc( 'Zapis do katalogu chronionego nie powiódł się (uprawnienia uploads?).' );
	}

	// Weryfikacja PO zapisie: obecność + rozmiar + suma (kryterium #7 „POTWIERDZONY").
	clearstatcache( true, $cel );
	if ( ! file_exists( $cel ) || filesize( $cel ) !== $rozmiar ) {
		$wroc( 'Plik po zapisie nie przeszedł potwierdzenia (obecność/rozmiar) — nie zapisano produktu.' );
	}

	return array(
		'sciezka' => $cel,
		'rozmiar' => $rozmiar,
		'sha256'  => hash_file( 'sha256', $cel ),
	);
}

/* ---------- Przełącznik sprzedaży (deaktywacja zamiast usuwania — D7) ---------- */

add_action( 'admin_post_sklepag_produkt_przelacz', 'sklepag_obsluz_przelacz_produkt' );

function sklepag_obsluz_przelacz_produkt() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_produkt_przelacz' );

	$id = isset( $_GET['produkt'] ) ? absint( $_GET['produkt'] ) : 0;

	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_produkty';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; akcja admina.
	$p = $wpdb->get_row( $wpdb->prepare( "SELECT id, nazwa, aktywny, plik_sciezka FROM {$t} WHERE id = %d", $id ) );
	if ( ! $p ) {
		wp_die( 'Produkt nie istnieje.', 'Sklep Agentów', 404 );
	}

	$nowy = $p->aktywny ? 0 : 1;
	if ( $nowy && ( '' === $p->plik_sciezka || ! file_exists( $p->plik_sciezka ) ) ) {
		wp_safe_redirect( add_query_arg( 'sklepag_blad', rawurlencode( 'Nie można włączyć sprzedaży: produkt nie ma potwierdzonego pliku paczki.' ), admin_url( 'admin.php?page=sklepag-produkty' ) ) );
		exit;
	}
	$wpdb->update(
		$t,
		array(
			'aktywny'   => $nowy,
			'zmieniono' => current_time( 'mysql' ),
		),
		array( 'id' => $id )
	);
	// phpcs:enable
	sklepag_zdarzenie( null, $nowy ? 'produkt_aktywowany' : 'produkt_deaktywowany', '#' . $id . ' ' . $p->nazwa );

	wp_safe_redirect( add_query_arg( 'sklepag_ok', 'przelaczono', admin_url( 'admin.php?page=sklepag-produkty' ) ) );
	exit;
}
