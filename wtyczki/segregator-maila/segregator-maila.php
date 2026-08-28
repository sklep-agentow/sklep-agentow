<?php
/**
 * Plugin Name: Segregator maila
 * Description: Porządkuje firmową skrzynkę: łączy się z nią po IMAP i rozkłada pocztę do folderów według Twoich reguł. Domyślnie pracuje NA SUCHO — pokazuje, co by przeniósł, i nie rusza ani jednej wiadomości, dopóki sam tego nie włączysz. Do tego zadanie cykliczne, historia i cofnięcie ostatniego przebiegu.
 * Version: 1.3.5
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Automatic AI
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: segregator-maila
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEGM_WERSJA', '1.3.5' );
define( 'SEGM_WERSJA_SCHEMATU', '4' );
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
/**
 * Ile dni panel pokazuje ostrzeżenie o nieudanym zapisie do bazy.
 * Znacznika NIE kasujemy przy udanym zapisie — nie ma jednego „sukcesu", po
 * którym można by go zgasić. Ostrzeżenie samo cichnie, gdy ostatni błąd jest
 * starszy niż to okno. Ta sama liczba co w ChatBocie: jeden wzorzec, nie dwa.
 */
const SEGM_BLAD_BAZY_OKNO_DNI = 14;

function segm_log_bledu_bazy( $funkcja, $operacja ) {
	global $wpdb;
	$szczegol = '' !== (string) $wpdb->last_error ? (string) $wpdb->last_error : 'sterownik nie podał komunikatu';

	/*
	 * ⛔ TRWAŁY ŚLAD, NIEZALEŻNY OD WP_DEBUG — rozciągnięty wzorzec znacznika
	 * błędu trybu AI (`segm_blad_ai`): opcja + pasek w panelu. wp_trigger_error()
	 * niżej zostaje dla instalacji deweloperskiej, ale rdzeń WordPressa kończy je
	 * natychmiast przy WP_DEBUG=false (wp-includes/functions.php: „Bail out if
	 * WP_DEBUG is not turned on"), czyli na KAŻDEJ normalnej instalacji klienta.
	 *
	 * Liczymy powtórzenia zamiast dopisywać wpis za wpisem — błąd w pętli
	 * (import CSV, seed, cron czyszczący próbkę) zalałby inaczej tabelę opcji.
	 *
	 * ⚠️ Kanał NAJLEPSZEGO WYSIŁKU: gdy padnie cała baza, ten zapis też się nie
	 * uda. Chroni przed awarią POJEDYNCZEGO zapytania, nie serwera bazy.
	 */
	$wpis = get_option( 'segm_blad_bazy' );
	$wpis = is_array( $wpis ) ? $wpis : array();
	$ile  = isset( $wpis['ile'] ) ? (int) $wpis['ile'] + 1 : 1;
	update_option(
		'segm_blad_bazy',
		array(
			'ostatni'  => current_time( 'mysql' ),
			'funkcja'  => (string) $funkcja,
			'operacja' => mb_substr( (string) $operacja, 0, 200 ),
			'szczegol' => mb_substr( $szczegol, 0, 300 ),
			'ile'      => $ile,
		),
		false
	);

	if ( ! function_exists( 'wp_trigger_error' ) ) {
		return;
	}
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
 * @param string    $nazwa    Nazwa opcji.
 * @param mixed     $wartosc  Wartość do zapisania.
 * @param bool|null $autoload Jak w update_option() (null = bez zmiany / domyślnie).
 * @return bool True, gdy po operacji w bazie leży to, co miało tam trafić.
 */
function segm_zapisz_opcje( $nazwa, $wartosc, $autoload = null ) {
	if ( update_option( $nazwa, $wartosc, $autoload ) ) {
		return true;
	}
	return get_option( $nazwa ) === $wartosc;
}

/**
 * Liczba z pola formularza z uczciwą obsługą pustego pola i śmieci.
 *
 * Pole puste, nie-liczba albo poza zakresem NIE nadpisuje poprzedniej wartości
 * — zostaje ona w mocy, a właściciel dostaje o tym zdanie (zamiast cichego
 * „1"). Pola nieobecnego w żądaniu nie ruszamy wcale.
 *
 * @param string        $pole       Nazwa pola w $_POST.
 * @param int           $poprzednia Wartość obowiązująca przed zapisem.
 * @param int           $min        Dolna granica (włącznie).
 * @param int           $max        Górna granica (włącznie).
 * @param string        $nazwa      Nazwa pola po ludzku (do komunikatu).
 * @param array<string> $uwagi      Lista zdań dla właściciela (dopisywana).
 * @return int Wartość do zapisania.
 */
function segm_liczba_z_pola( $pole, $poprzednia, $min, $max, $nazwa, &$uwagi ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wołane z handlerów, które nonce sprawdziły wcześniej.
	if ( ! isset( $_POST[ $pole ] ) ) {
		return (int) $poprzednia;
	}
	$surowe = trim( sanitize_text_field( wp_unslash( $_POST[ $pole ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $surowe ) {
		$uwagi[] = sprintf( 'Pole „%s" było puste — zostawiono poprzednią wartość %d.', $nazwa, (int) $poprzednia );
		return (int) $poprzednia;
	}
	if ( ! ctype_digit( $surowe ) || (int) $surowe < $min || (int) $surowe > $max ) {
		$uwagi[] = sprintf( 'Pole „%s" ma wartość „%s", a przyjmujemy liczbę całkowitą od %d do %d — zostawiono poprzednią wartość %d.', $nazwa, mb_substr( $surowe, 0, 20 ), $min, $max, (int) $poprzednia );
		return (int) $poprzednia;
	}
	return (int) $surowe;
}

require_once SEGM_KATALOG . 'includes/instalator.php';

register_activation_hook( __FILE__, 'segm_aktywacja' );
register_deactivation_hook( __FILE__, 'segm_deaktywacja' );

/*
 * Segregacja skrzynki chodzi TAKŻE poza panelem: zadanie cykliczne WP-Cron
 * odpala się z wp-cron.php, gdzie is_admin() jest fałszem. Silnik reguł i cała
 * obsługa IMAP muszą więc być widoczne zawsze — inaczej cron wywalałby się na
 * „nie ma takiej funkcji". Reszta (panel, import CSV, eksport filtrów) zostaje
 * przy adminie, bo front nie ma z nich żadnego pożytku.
 */
require_once SEGM_KATALOG . 'includes/silnik.php';
require_once SEGM_KATALOG . 'includes/ai.php';
require_once SEGM_KATALOG . 'includes/class-segm-imap.php';
require_once SEGM_KATALOG . 'includes/skrzynka.php';
require_once SEGM_KATALOG . 'includes/skrzynka-przebieg.php';

if ( is_admin() ) {
	require_once SEGM_KATALOG . 'includes/csv.php';
	require_once SEGM_KATALOG . 'includes/admin.php';
	require_once SEGM_KATALOG . 'includes/admin-probka.php';
	require_once SEGM_KATALOG . 'includes/admin-skrzynka.php';
	require_once SEGM_KATALOG . 'includes/admin-powitanie.php';
	require_once SEGM_KATALOG . 'includes/filtry.php';
	require_once SEGM_KATALOG . 'includes/konfiguracja.php';
}

/**
 * Ścieżka aktualizacji schematu (nowa tabela = dbDelta + uninstall + to miejsce).
 */
function segm_sprawdz_schemat() {
	if ( get_option( 'segm_wersja_schematu' ) !== SEGM_WERSJA_SCHEMATU ) {
		segm_utworz_tabele();
		// Porażka jest idempotentna (migracja pobiegnie przy następnym żądaniu),
		// ale ma zostawić ślad — inaczej dbDelta leciałoby przy każdym żądaniu
		// bez jednego słowa dlaczego (A2-F4, niższa waga).
		if ( ! segm_zapisz_opcje( 'segm_wersja_schematu', SEGM_WERSJA_SCHEMATU ) ) {
			segm_log_bledu_bazy( 'segm_sprawdz_schemat', 'zapis wersji schematu ' . SEGM_WERSJA_SCHEMATU );
		}
	}
}
add_action( 'plugins_loaded', 'segm_sprawdz_schemat' );

/**
 * Codzienne czyszczenie: próbka i raporty starsze niż SEGM_RETENCJA_DNI dni
 * (wzór 1:1 z ChatBota — rejestracja przy aktywacji, zdjęcie przy deaktywacji,
 * wpis w uninstall.php).
 *
 * Kasujemy WSZYSTKIE tabele z treścią osób trzecich: segm_maile (nadawca,
 * temat, treść), segm_przebiegi (kolumna wyniki trzyma nadawcę i temat każdego
 * maila) oraz historię segregacji żywej skrzynki (segm_skrz_ruchy +
 * segm_skrz_przebiegi) — czyszczenie samej próbki zostawiałoby tę samą
 * korespondencję w raportach, czyli retencję tylko na papierze.
 *
 * Funkcja żyje w pliku głównym (ładowanym zawsze), bo WP-Cron chodzi poza
 * adminem — includes/ ładują się tylko dla is_admin().
 *
 * @return void
 */
function segm_czyszczenie_probki() {
	global $wpdb;

	// Retencja też kasuje wiersze, więc nie może wejść w środek przebiegu ani
	// importu. Gdy coś trwa — odpuszczamy tę dobę: cron jest codzienny, a dane
	// starsze niż 90 dni nie zrobią się od tego młodsze o więcej niż dzień.
	if ( true !== segm_zajmij_blokade( 'czyszczenie starych danych (retencja)' ) ) {
		return;
	}

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

	// Historia segregacji ŻYWEJ skrzynki trzyma nadawcę i temat każdej ruszonej
	// wiadomości, czyli tę samą kategorię danych co próbka — obejmuje ją więc
	// ta sama retencja. Bez tego wpisu retencja byłaby tylko na papierze.
	$ruchy = $wpdb->prefix . 'segm_skrz_ruchy';
	$wynik = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$ruchy} WHERE utworzono < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$granica
		)
	);
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_czyszczenie_probki', 'kasowanie historii skrzynki starszej niż ' . SEGM_RETENCJA_DNI . ' dni' );
	}

	$skrz_przebiegi = $wpdb->prefix . 'segm_skrz_przebiegi';
	$wynik          = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$skrz_przebiegi} WHERE uruchomiono < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$granica
		)
	);
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_czyszczenie_probki', 'kasowanie przebiegów skrzynki starszych niż ' . SEGM_RETENCJA_DNI . ' dni' );
	}

	segm_zwolnij_blokade();
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

/*
 * Znacznik „operacja na próbce w toku" (P2.3). Nazwa opcji + ile sekund wpis
 * jest ważny: po tym czasie znacznik po padniętym procesie przestaje blokować
 * wtyczkę (najdłuższa chroniona operacja to przebieg AI, ~18 s).
 */
const SEGM_BLOKADA       = 'segm_operacja_w_toku';
const SEGM_BLOKADA_WAZNA = 120;

/**
 * Zajęcie znacznika na czas operacji ruszającej próbkę.
 *
 * Chroniony scenariusz: `TRUNCATE TABLE segm_maile` (import CSV, przywrócenie
 * demo, „wyczyść teraz", retencja) leci w tym samym czasie, w którym przebieg
 * czyta tę tabelę — druga karta w przeglądarce wystarczy. Efektem był raport
 * policzony na pustej albo połowicznej próbce, BEZ jednego słowa ostrzeżenia.
 *
 * Zamek jest ATOMOWY na poziomie bazy, nie cache opcji (DK-6, T-4):
 *  - stan czytamy PROSTO z `wp_options` — cache opcji tego procesu
 *    (`notoptions`, alloptions, trwały object cache) nie widzi wpisu, który
 *    sekundę temu zrobił inny proces PHP, a zamek ma chronić właśnie przed nim;
 *  - zajęcie to jeden `INSERT IGNORE` po kluczu unikalnym `option_name`
 *    i liczy się WYNIK: dokładnie 1 wiersz = nasz, 0 = ktoś był szybszy.
 *    `add_option()` się do tego NIE nadaje: sprawdza istnienie w pamięci
 *    procesu i robi `INSERT … ON DUPLICATE KEY UPDATE`, który nadpisuje cudzy
 *    wiersz i melduje sukces — dwa przebiegi szły naraz, a znacznik kłamał,
 *    kto go trzyma (zmierzone testem wyścigu dwóch procesów);
 *  - znacznik po procesie, który padł (fatal, timeout, zabity), przestaje
 *    blokować po SEGM_BLOKADA_WAZNA s — przejmujemy go, ale kasując DOKŁADNIE
 *    ten przeterminowany wiersz (po wartości), nie to, co w międzyczasie mógł
 *    zająć kto inny; to samo dla wiersza w obcym kształcie (bez pola `do`),
 *    który wcześniej blokował wtyczkę NA ZAWSZE, bo sprawdzenie terminu go
 *    pomijało, a add_option() odbijał się od istniejącego (T-4);
 *  - błąd bazy przy zajmowaniu = ODMOWA, nie zgoda (brak danych ≠ wolne).
 * Własna tabela byłaby zmianą schematu bez zysku: klucz unikalny na
 * `option_name` daje tę samą atomowość, a zamrożony test audytu mierzy
 * dokładnie ten kształt (proces B = INSERT do `wp_options`).
 *
 * Transakcja nie wchodzi w grę: TRUNCATE w MySQL robi niejawny COMMIT, więc
 * opakowanie go w transakcję niczego by nie odizolowało.
 *
 * @param string $opis Co trzyma znacznik (pokazywane drugiej karcie).
 * @return true|string True = zajęte; string = opis operacji, która trwa.
 */
function segm_zajmij_blokade( $opis ) {
	global $wpdb;

	$surowy = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", SEGM_BLOKADA ) );
	if ( null !== $surowy ) {
		$stary = maybe_unserialize( $surowy );
		if ( is_array( $stary ) && isset( $stary['do'] ) && (int) $stary['do'] > time() ) {
			return isset( $stary['opis'] ) && '' !== (string) $stary['opis'] ? (string) $stary['opis'] : 'inna operacja na próbce';
		}
		// Przeterminowany albo w obcym kształcie: proces, który go zostawił, już
		// nie pracuje. Kasujemy ten konkretny wiersz (po wartości) — gdy ktoś
		// właśnie przejął zamek świeżym wpisem, DELETE go nie ruszy, a nasz
		// INSERT niżej odbije się od niego uczciwie.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", SEGM_BLOKADA, $surowy ) );
	}

	$moja      = maybe_serialize( segm_stempel_blokady( $opis ) );
	$wstawiono = $wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			SEGM_BLOKADA,
			$moja
		)
	);
	if ( 1 !== (int) $wstawiono ) {
		// 0 = ktoś był szybszy (wiersz już jest); false = błąd bazy → odmowa.
		return 'inna operacja na próbce';
	}
	// Zapamiętujemy WŁASNY stempel — zwolnienie kasuje tylko jego (patrz niżej).
	$GLOBALS['segm_blokada_moja'] = $moja;
	// Pisaliśmy obok API opcji — cache tego procesu musi to zobaczyć, żeby
	// get_option()/delete_option() po drodze nie pracowały na starym „nie ma".
	wp_cache_delete( SEGM_BLOKADA, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	return true;
}

/**
 * Zawartość znacznika: co go trzyma i do kiedy jest ważny.
 *
 * @param string $opis Co trzyma znacznik.
 * @return array{opis: string, do: int}
 */
function segm_stempel_blokady( $opis ) {
	return array(
		'opis' => (string) $opis,
		'do'   => time() + SEGM_BLOKADA_WAZNA,
	);
}

/**
 * Zwolnienie znacznika (wołane na KAŻDEJ drodze wyjścia z operacji).
 *
 * Kasujemy WYŁĄCZNIE własny stempel (`option_value` = to, co sami wstawiliśmy).
 * Bezwarunkowe `delete_option()` miało wadę symetryczną do DK-6: proces, który
 * przekroczył `SEGM_BLOKADA_WAZNA` i którego zamek ktoś w międzyczasie przejął,
 * na wyjściu kasował CUDZY, świeży znacznik i wpuszczał trzecią operację.
 * Gdy tego stempla nie mamy (zamek nie był nasz), nie ruszamy niczego.
 *
 * @return void
 */
function segm_zwolnij_blokade() {
	global $wpdb;

	$moja = isset( $GLOBALS['segm_blokada_moja'] ) ? (string) $GLOBALS['segm_blokada_moja'] : '';
	unset( $GLOBALS['segm_blokada_moja'] );
	if ( '' === $moja ) {
		return;
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", SEGM_BLOKADA, $moja ) );
	wp_cache_delete( SEGM_BLOKADA, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

/**
 * Zgoda właściciela na wysyłanie próbki do zewnętrznego dostawcy modelu.
 *
 * W trybie AI do api.anthropic.com wychodzi treść maili — a to zwykle
 * korespondencja OSÓB TRZECICH, nie właściciela. Sama informacja w panelu to
 * za mało: potrzebne jest jednorazowe, wyraźne potwierdzenie, zapisane z datą
 * i loginem, żeby dało się je okazać przy pytaniu „kto i kiedy na to pozwolił".
 *
 * @return array{kiedy: string, kto: string}|null Null = zgody NIE MA.
 */
function segm_zgoda_ai() {
	$zapisana = get_option( 'segm_zgoda_ai', array() );
	if ( ! is_array( $zapisana ) || empty( $zapisana['kiedy'] ) ) {
		return null;
	}
	return array(
		'kiedy' => (string) $zapisana['kiedy'],
		'kto'   => isset( $zapisana['kto'] ) ? (string) $zapisana['kto'] : 'nieznany',
	);
}

/**
 * Czy tryby AI wolno w ogóle uruchomić.
 *
 * @return bool
 */
function segm_zgoda_ai_udzielona() {
	return null !== segm_zgoda_ai();
}

/**
 * Ustawienia wtyczki z wartościami domyślnymi.
 */
function segm_ustawienia() {
	$domyslne = array(
		'usun_dane'        => 0,
		'ai_limit_dzienny' => 100,
		'ai_model'         => '',
	);
	$zapisane = get_option( 'segm_ustawienia', array() );
	if ( ! is_array( $zapisane ) ) {
		$zapisane = array();
	}
	return array_merge( $domyslne, $zapisane );
}
