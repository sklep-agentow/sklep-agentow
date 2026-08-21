<?php
/**
 * Dziennik logowań na konta adminów (S-09) + opóźnienie wykładnicze po nieudanych.
 *
 * ⛔ Ten plik MUSI być ładowany ZAWSZE (nie za is_admin()): wp-login.php
 * nie jest ekranem admina, a to właśnie tam odpalają się hooki logowania.
 *
 * Rdzeń WP nie wywołuje wp_login_failed dla pustego loginu/hasła
 * (kody empty_username/empty_password odsiane w wp_authenticate()),
 * więc dziennik nie puchnie od pustych strzałów.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SKLEPAG_OPOZNIENIE_SUFIT_S = 60;      // Plan §7: sufit opóźnienia; OPÓŹNIENIE, nie blokada (wspólne konta — nie wolno zablokować prawdziwego użytkownika).
const SKLEPAG_OPOZNIENIE_OKNO_S  = 15 * 60; // Błędy starsze niż okno nie podbijają opóźnienia.

add_action( 'wp_login', 'sklepag_loguj_udane', 10, 2 );
add_action( 'wp_login_failed', 'sklepag_loguj_nieudane', 10, 2 );
add_action( 'wp_authenticate', 'sklepag_opoznij_probe_logowania', 5, 1 );

/**
 * Wspólny zapis wiersza dziennika logowań.
 *
 * @param string $login Login wpisany w formularzu (nie musi istnieć w WP).
 * @param string $wynik 'ok' albo 'blad'.
 */
function sklepag_zapisz_logowanie( $login, $wynik ) {
	global $wpdb;

	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- własna tabela dziennika (append-only).
	$wpdb->insert(
		$wpdb->prefix . 'sklepag_logowania',
		array(
			'czas'          => current_time( 'mysql' ),
			'login_wpisany' => mb_substr( sanitize_user( (string) $login, true ), 0, 60 ),
			'wynik'         => $wynik,
			'ip'            => sklepag_ip(),
			'user_agent'    => mb_substr( $user_agent, 0, 255 ),
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Udane logowanie — „kto, kiedy, konkrety" (S-09). Zeruje też licznik
 * opóźnienia: kolejne próby liczą się od NAJNOWSZEGO sukcesu tej pary.
 *
 * @param string  $user_login Login.
 * @param WP_User $user       Zalogowany użytkownik (nieużywany — dziennik trzyma login wpisany).
 */
function sklepag_loguj_udane( $user_login, $user ) {
	unset( $user );
	sklepag_zapisz_logowanie( $user_login, 'ok' );
}

/**
 * Nieudana próba — też do dziennika (S-09 „konkrety": IP, przeglądarka).
 *
 * @param string   $username Login wpisany.
 * @param WP_Error $error    Powód (nie zapisujemy treści — komunikaty rdzenia zdradzają, czy konto istnieje).
 */
function sklepag_loguj_nieudane( $username, $error ) {
	unset( $error );
	sklepag_zapisz_logowanie( $username, 'blad' );
}

/**
 * Opóźnienie wykładnicze PRZED sprawdzeniem hasła (plan §7): po n nieudanych
 * próbach pary (login+IP) od ostatniego sukcesu następna próba czeka
 * min(2^n, 60) s. Sleep nie wlicza się do max_execution_time (zegar liczy
 * czas CPU skryptu), a sufit 60 s trzyma workera krótko.
 *
 * ⛔ Amplifikacja przez równoległość: salwa równoczesnych
 * POST-ów na wp-login.php zajęłaby po workerze na sleep każda — dlatego
 * ZANIM cokolwiek zaśnie, ciasny limit prób per IP odbija nadmiar
 * NATYCHMIAST (worker wolny od razu). Człowiek nie zrobi >6 prób/min przy
 * rosnących opóźnieniach — prawdziwego użytkownika to nie dotyka (i nie jest to
 * blokada konta, tylko chwilowy limit adresu). Rezydualne ryzyko
 * (wiele IP = DDoS) — poza zakresem wtyczki, D26.
 *
 * @param string $username Login z formularza (przez referencję w rdzeniu — tu tylko czytany).
 */
function sklepag_opoznij_probe_logowania( $username ) {
	$login = mb_substr( sanitize_user( (string) $username, true ), 0, 60 );
	if ( '' === $login ) {
		return; // Puste nie trafiają do dziennika — nie ma czego liczyć.
	}

	if ( ! sklepag_rate_limit( 'login', 6 ) ) {
		wp_die( 'Za dużo prób logowania z tego adresu. Odczekaj minutę i spróbuj ponownie.', 'Logowanie', 429 );
	}

	global $wpdb;
	$t  = $wpdb->prefix . 'sklepag_logowania';
	$ip = sklepag_ip();

	// Błędy pary login+IP nowsze niż ostatni sukces tej pary I nie starsze niż okno.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; dziennik musi być czytany na żywo.
	$n = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$t}
		 WHERE login_wpisany = %s AND ip = %s AND wynik = 'blad'
		   AND czas > COALESCE(
		       (SELECT MAX(s.czas) FROM {$t} s WHERE s.login_wpisany = %s AND s.ip = %s AND s.wynik = 'ok'),
		       '1970-01-01 00:00:00'
		   )
		   AND czas > %s",
			$login,
			$ip,
			$login,
			$ip,
			gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - SKLEPAG_OPOZNIENIE_OKNO_S )
		)
	);
	// phpcs:enable

	if ( $n < 1 ) {
		return;
	}

	sleep( min( 2 ** $n, SKLEPAG_OPOZNIENIE_SUFIT_S ) );
}
