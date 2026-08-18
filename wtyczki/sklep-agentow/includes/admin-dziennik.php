<?php
/**
 * Strona „Dziennik" w wp-admin (S-09): zakładka Logowania (kto/kiedy/wynik/IP/
 * przeglądarka) + zakładka Zdarzenia (akcje adminów i zdarzenia systemowe).
 * Wyłącznie odczyt — mutacji tu nie ma.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SKLEPAG_KATALOG . 'includes/class-sklepag-lista-logowan.php';
require_once SKLEPAG_KATALOG . 'includes/class-sklepag-lista-zdarzen.php';

/**
 * Render strony Dziennik (callback submenu).
 */
function sklepag_admin_dziennik() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}

	// Zakładka z GET po allowlist — nawigacja read-only (standard nav-tab wp-admin).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$zakladka = isset( $_GET['zakladka'] ) ? sanitize_key( wp_unslash( $_GET['zakladka'] ) ) : 'logowania';
	if ( ! in_array( $zakladka, array( 'logowania', 'zdarzenia' ), true ) ) {
		$zakladka = 'logowania';
	}

	$baza = admin_url( 'admin.php?page=sklepag-dziennik' );

	echo '<div class="wrap">';
	echo '<h1>Dziennik</h1>';
	echo '<nav class="nav-tab-wrapper" aria-label="Zakładki dziennika">';
	echo '<a href="' . esc_url( $baza . '&zakladka=logowania' ) . '" class="nav-tab' . ( 'logowania' === $zakladka ? ' nav-tab-active' : '' ) . '"' . ( 'logowania' === $zakladka ? ' aria-current="page"' : '' ) . '>Logowania</a>';
	echo '<a href="' . esc_url( $baza . '&zakladka=zdarzenia' ) . '" class="nav-tab' . ( 'zdarzenia' === $zakladka ? ' nav-tab-active' : '' ) . '"' . ( 'zdarzenia' === $zakladka ? ' aria-current="page"' : '' ) . '>Zdarzenia</a>';
	echo '</nav>';

	$tabela = ( 'zdarzenia' === $zakladka ) ? new Sklepag_Lista_Zdarzen() : new Sklepag_Lista_Logowan();
	$tabela->prepare_items();

	// Formularz GET: filtr źródła (zdarzenia) i paginacja wracają na tę samą stronę.
	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="sklepag-dziennik">';
	echo '<input type="hidden" name="zakladka" value="' . esc_attr( $zakladka ) . '">';
	$tabela->display();
	echo '</form>';

	if ( 'logowania' === $zakladka ) {
		echo '<p class="description">Wpisy starsze niż 90 dni są usuwane automatycznie (retencja — polityka prywatności). Po kolejnych nieudanych próbach logowania odpowiedź jest wykładniczo opóźniana (do ' . (int) SKLEPAG_OPOZNIENIE_SUFIT_S . ' s), bez twardej blokady konta.</p>';
	} else {
		echo '<p class="description">Oś czasu: akcje adminów (CRUD produktów, ponowne wysyłki, eksporty) i zdarzenia systemowe (webhooki, maile, pobrania). Retencja 90 dni.</p>';
	}

	echo '</div>';
}
