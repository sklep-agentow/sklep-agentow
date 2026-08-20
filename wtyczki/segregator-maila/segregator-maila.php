<?php
/**
 * Plugin Name: Segregator maila
 * Description: Testowy bot porządkujący skrzynkę firmową: rozdziela maile do kategorii według reguł (kolejność = pierwsza wygrywa) i pokazuje raport z liczbami — trafienia, % pokrycia, accuracy przy próbce z etykietami.
 * Version: 1.2.1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Sklep Agentów
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEGM_WERSJA', '1.2.1' );
define( 'SEGM_WERSJA_SCHEMATU', '2' );
define( 'SEGM_KATALOG', plugin_dir_path( __FILE__ ) );

/*
 * RETENCJA (RODO): próbka i raporty to CUDZA korespondencja — po tylu dniach
 * codzienny cron kasuje je z bazy. Ta sama liczba co w ChatBocie.
 */
const SEGM_RETENCJA_DNI = 90;

/**
 * Wpis do logu WP po nieudanym zapisie do bazy.
 *
 * $wpdb->insert/update/delete/query zwracają FALSE przy porażce (0 to poprawny
 * wynik „nic nie pasowało"), więc każde wywołanie porównujemy z false ===.
 * Log idzie przez wp_trigger_error() — natywne API WP (6.4+), respektuje
 * WP_DEBUG i nie łamie zakazu error_log() z phpcs.xml.dist. Na starszym WP
 * (deklarowane minimum 6.0) log po prostu nie powstaje; komunikat błędu dla
 * użytkownika działa niezależnie od wersji i to on jest właściwą obroną
 * przed cichym ubytkiem danych.
 *
 * @param string $funkcja  Nazwa funkcji, w której zapis padł.
 * @param string $operacja Krótki opis operacji (co miało trafić do bazy).
 * @return void
 */
function segm_log_bledu_bazy( $funkcja, $operacja ) {
	global $wpdb;
	if ( ! function_exists( 'wp_trigger_error' ) ) {
		return;
	}
	$szczegol = '' !== (string) $wpdb->last_error ? (string) $wpdb->last_error : 'sterownik nie podał komunikatu';
	wp_trigger_error(
		(string) $funkcja,
		'zapis do bazy nie powiódł się (' . $operacja . '): ' . $szczegol,
		E_USER_WARNING
	);
}

/**
 * Zapis opcji z UCZCIWYM wynikiem.
 *
 * update_option() zwraca false także wtedy, gdy nowa wartość jest IDENTYCZNA
 * ze starą — samo `false` nie odróżnia „nic się nie zmieniło" od „zapis padł",
 * więc naiwne sprawdzenie sypałoby fałszywymi błędami. Porażkę potwierdzamy
 * odczytem: false + wartość w bazie inna niż zapisywana = realny błąd.
 * Przy padniętym zapisie update_option() wychodzi PRZED zasileniem cache'u,
 * więc get_option() zwraca wtedy starą wartość i różnica jest widoczna.
 *
 * @param string $nazwa   Nazwa opcji.
 * @param mixed  $wartosc Wartość do zapisania.
 * @return bool True, gdy po operacji w bazie leży to, co miało tam trafić.
 */
function segm_zapisz_opcje( $nazwa, $wartosc ) {
	if ( update_option( $nazwa, $wartosc ) ) {
		return true;
	}
	return get_option( $nazwa ) === $wartosc;
}

require_once SEGM_KATALOG . 'includes/instalator.php';

register_activation_hook( __FILE__, 'segm_aktywacja' );
register_deactivation_hook( __FILE__, 'segm_deaktywacja' );

// Bot działa wyłącznie w adminie — front nie ładuje nic poza ścieżką upgrade.
if ( is_admin() ) {
	require_once SEGM_KATALOG . 'includes/silnik.php';
	require_once SEGM_KATALOG . 'includes/ai.php';
	require_once SEGM_KATALOG . 'includes/csv.php';
	require_once SEGM_KATALOG . 'includes/admin.php';
}

/**
 * Ścieżka aktualizacji schematu (nowa tabela = dbDelta + uninstall + to miejsce).
 */
function segm_sprawdz_schemat() {
	if ( get_option( 'segm_wersja_schematu' ) !== SEGM_WERSJA_SCHEMATU ) {
		segm_utworz_tabele();
		update_option( 'segm_wersja_schematu', SEGM_WERSJA_SCHEMATU );
	}
}
add_action( 'plugins_loaded', 'segm_sprawdz_schemat' );

/**
 * Codzienne czyszczenie: próbka i raporty starsze niż SEGM_RETENCJA_DNI dni
 * (wzór 1:1 z ChatBota — rejestracja przy aktywacji, zdjęcie przy deaktywacji,
 * wpis w uninstall.php).
 *
 * Kasujemy OBIE tabele z treścią osób trzecich: segm_maile (nadawca, temat,
 * treść) ORAZ segm_przebiegi (kolumna wyniki trzyma nadawcę i temat każdego
 * maila) — czyszczenie samej próbki zostawiałoby tę samą korespondencję
 * w raportach, czyli retencję tylko na papierze.
 *
 * Funkcja żyje w pliku głównym (ładowanym zawsze), bo WP-Cron chodzi poza
 * adminem — includes/ ładują się tylko dla is_admin().
 *
 * @return void
 */
function segm_czyszczenie_probki() {
	global $wpdb;

	// Wiersze zapisujemy przez current_time( 'mysql' ), czyli w czasie LOKALNYM
	// strony — granicę liczymy w tej samej skali, żeby przy strefie +02:00 nie
	// kasować dwie godziny za wcześnie.
	$granica = gmdate(
		'Y-m-d H:i:s',
		time() + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - SEGM_RETENCJA_DNI * DAY_IN_SECONDS
	);

	$maile = $wpdb->prefix . 'segm_maile';
	$wynik = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$maile} WHERE utworzono < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$granica
		)
	);
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_czyszczenie_probki', 'kasowanie próbki starszej niż ' . SEGM_RETENCJA_DNI . ' dni' );
	}

	$przebiegi = $wpdb->prefix . 'segm_przebiegi';
	$wynik     = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$przebiegi} WHERE uruchomiono < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$granica
		)
	);
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_czyszczenie_probki', 'kasowanie raportów starszych niż ' . SEGM_RETENCJA_DNI . ' dni' );
	}
}
add_action( 'segm_czyszczenie_probki', 'segm_czyszczenie_probki' );

/**
 * Dopięcie harmonogramu na instalacji, która była włączona JUŻ PRZED tą wersją.
 * register_activation_hook nie odpala się przy zwykłej podmianie plików, więc
 * bez tej furtki retencja nie ruszyłaby u nikogo, kto ma wtyczkę aktywną —
 * a to dokładnie ci ludzie mają cudzą korespondencję w bazie.
 *
 * @return void
 */
function segm_zapewnij_cron() {
	if ( ! wp_next_scheduled( 'segm_czyszczenie_probki' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'segm_czyszczenie_probki' );
	}
}
add_action( 'plugins_loaded', 'segm_zapewnij_cron' );

/**
 * Ustawienia wtyczki z wartościami domyślnymi.
 */
function segm_ustawienia() {
	$domyslne = array(
		'usun_dane'        => 0,
		'ai_limit_dzienny' => 100,
	);
	$zapisane = get_option( 'segm_ustawienia', array() );
	if ( ! is_array( $zapisane ) ) {
		$zapisane = array();
	}
	return array_merge( $domyslne, $zapisane );
}
