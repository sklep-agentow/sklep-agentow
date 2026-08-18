<?php
/**
 * Przepływ zakupu: Kup → Checkout Session → webhook/reconciliation → SILNIK
 * domykania (JEDEN dla obu dróg; przejścia stanu WYŁĄCZNIE przez CAS — D24).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SKLEPAG_ZGODA_TRESC = 'Wyrażam zgodę na natychmiastowe dostarczenie treści cyfrowej i przyjmuję do wiadomości utratę prawa odstąpienia od umowy (art. 38 ustawy o prawach konsumenta).';

/* ---------- RATE LIMIT (atomowy licznik w tabeli, per IP i okno minutowe;
	webhook JAWNIE poza limiterem) ---------- */

function sklepag_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
}

function sklepag_rate_limit( $akcja, $prog = 10 ) {
	global $wpdb;
	$t     = $wpdb->prefix . 'sklepag_rate_limit';
	$okno  = (int) floor( time() / MINUTE_IN_SECONDS );
	$klucz = $akcja . '|' . $okno . '|' . md5( sklepag_ip() );

	// Inkrementacja MUSI być atomowa (recenzja PR #33): para get/set transient
	// gubi żądania równoległe — zmierzone 40/40 przepuszczonych przy progu 30.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$t} (klucz, licznik, okno_do) VALUES (%s, 1, %s)
		 ON DUPLICATE KEY UPDATE licznik = licznik + 1",
			$klucz,
			gmdate( 'Y-m-d H:i:s', ( $okno + 2 ) * MINUTE_IN_SECONDS )
		)
	);
	$ile = (int) $wpdb->get_var( $wpdb->prepare( "SELECT licznik FROM {$t} WHERE klucz = %s", $klucz ) );
	// phpcs:enable

	if ( 0 === $ile ) {
		return true; // Awaria zapisu/odczytu licznika — limiter chroni przed spamem, nie może zablokować sklepu (autoryzacje są gdzie indziej: nonce/podpis/token).
	}
	return $ile <= $prog;
}

/* ---------- KUP (formularz publiczny) ---------- */

add_action( 'admin_post_nopriv_sklepag_kup', 'sklepag_obsluz_kup' );
add_action( 'admin_post_sklepag_kup', 'sklepag_obsluz_kup' );

function sklepag_obsluz_kup() {
	check_admin_referer( 'sklepag_kup' );

	$sklep_url = get_permalink( (int) get_option( 'sklepag_strona_sklep' ) );

	if ( ! sklepag_rate_limit( 'kup', 10 ) ) {
		wp_die( 'Za dużo prób. Odczekaj minutę.', 'Sklep Agentów', 429 );
	}

	$produkt_id = isset( $_POST['produkt_id'] ) ? absint( $_POST['produkt_id'] ) : 0;
	$zgoda      = isset( $_POST['zgoda'] ) && '1' === $_POST['zgoda'];

	if ( ! $zgoda ) {
		wp_die( 'Zakup wymaga zgody na natychmiastowe dostarczenie treści cyfrowej.', 'Sklep Agentów', 400 );
	}

	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_produkty';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; walidacja zakupu czyta świeży stan produktu.
	$produkt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND aktywny = 1", $produkt_id ) );

	if ( ! $produkt || '' === $produkt->plik_sciezka || ! file_exists( $produkt->plik_sciezka ) ) {
		wp_die( 'Produkt niedostępny.', 'Sklep Agentów', 404 );
	}

	// Okno idempotencji 60 s: podwójny klik nie tworzy drugiej sesji.
	$okno = 'sklepag_idem_' . md5( sklepag_ip() . '|' . $produkt_id );
	$juz  = get_transient( $okno );
	if ( is_array( $juz ) && ! empty( $juz['url'] ) ) {
		wp_redirect( $juz['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- adres Stripe (zewnętrzny, z API).
		exit;
	}

	$zgoda_czas = current_time( 'mysql', true );
	$sesja      = sklepag_stripe_utworz_sesje( $produkt, $zgoda_czas, wp_generate_uuid4() );

	if ( is_wp_error( $sesja ) || empty( $sesja['id'] ) || empty( $sesja['url'] ) ) {
		sklepag_zdarzenie( null, 'checkout_blad', is_wp_error( $sesja ) ? $sesja->get_error_message() : 'brak id/url w odpowiedzi' );
		wp_die( 'Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.', 'Sklep Agentów', 502 );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->prefix}sklepag_sesje_checkout (stripe_session_id, produkt_id, stan, utworzono) VALUES (%s, %d, 'utworzona', %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sesja['id'],
			$produkt->id,
			current_time( 'mysql' )
		)
	);

	set_transient( $okno, array( 'url' => $sesja['url'] ), MINUTE_IN_SECONDS );

	wp_redirect( $sesja['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
}

/* ---------- ZDARZENIA (append-only oś czasu) ---------- */

function sklepag_zdarzenie( $zamowienie_id, $typ, $opis ) {
	global $wpdb;
	$uzytkownik = is_user_logged_in() ? wp_get_current_user()->user_login : '';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$wpdb->prefix . 'sklepag_zdarzenia',
		array(
			'zamowienie_id' => $zamowienie_id,
			'typ'           => $typ,
			'opis'          => mb_substr( (string) $opis, 0, 60000 ),
			'uzytkownik'    => $uzytkownik,
			'czas'          => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	);
}

/* ---------- WEBHOOK (REST; autoryzacja = podpis; POZA rate-limiterem) ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'sklepag/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => 'sklepag_webhook',
				'permission_callback' => '__return_true', // Publiczny endpoint — autoryzacją JEST podpis Stripe.
			)
		);
		register_rest_route(
			'sklepag/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => 'sklepag_status_zamowienia',
				'permission_callback' => '__return_true',
			)
		);
	}
);

function sklepag_webhook( WP_REST_Request $zadanie ) {
	$body     = $zadanie->get_body(); // SUROWE body — żadnych modyfikacji przed podpisem.
	$naglowek = $zadanie->get_header( 'stripe_signature' );
	$sekret   = (string) get_option( 'sklepag_stripe_webhook_sekret', '' );

	if ( ! sklepag_stripe_weryfikuj_podpis( $body, (string) $naglowek, $sekret ) ) {
		sklepag_zdarzenie( null, 'webhook_zly_podpis', 'odrzucono (ip ' . sklepag_ip() . ')' );
		return new WP_REST_Response( array( 'blad' => 'zly podpis' ), 400 );
	}

	$zdarzenie = json_decode( $body, true );
	$typ       = isset( $zdarzenie['type'] ) ? $zdarzenie['type'] : '';
	$sesja     = isset( $zdarzenie['data']['object'] ) ? $zdarzenie['data']['object'] : array();

	// Best practice Stripe: fulfillment na completed ORAZ async_payment_succeeded,
	// bramka na payment_status (BLIK i inne asynchroniczne: completed przychodzi jako unpaid).
	if ( in_array( $typ, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
		if ( isset( $sesja['payment_status'] ) && 'unpaid' === $sesja['payment_status'] ) {
			sklepag_zdarzenie( null, 'webhook_unpaid', $typ . ' ' . ( $sesja['id'] ?? '?' ) . ' — czekamy na async' );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		$ok = sklepag_domknij_zakup( $sesja );
		return new WP_REST_Response( array( 'ok' => (bool) $ok ), $ok ? 200 : 500 );
	}

	if ( 'checkout.session.async_payment_failed' === $typ || 'checkout.session.expired' === $typ ) {
		sklepag_oznacz_sesje( $sesja['id'] ?? '', 'wygasla' );
		sklepag_zdarzenie( null, 'sesja_niedoszla', $typ . ' ' . ( $sesja['id'] ?? '?' ) );
	}

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

function sklepag_oznacz_sesje( $sesja_id, $stan ) {
	if ( '' === $sesja_id ) {
		return;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $wpdb->prefix . 'sklepag_sesje_checkout', array( 'stan' => $stan ), array( 'stripe_session_id' => $sesja_id ), array( '%s' ), array( '%s' ) );
}

/* ---------- SILNIK DOMYKANIA — jeden dla webhooka i reconciliation (D24) ---------- */

/**
 * true = domknięte/nic do roboty; false = niedomknięte (retry ma wrócić).
 * KAŻDE przejście stanu = atomowy UPDATE z warunkiem (CAS). Przegrany wyścig
 * wycofuje się BEZ maila.
 */
function sklepag_domknij_zakup( $sesja ) {
	global $wpdb;
	$sid = isset( $sesja['id'] ) ? (string) $sesja['id'] : '';
	if ( '' === $sid ) {
		return true;
	}

	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	$ps = $wpdb->prefix . 'sklepag_sesje_checkout';
	$pp = $wpdb->prefix . 'sklepag_produkty';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$nasza = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ps} WHERE stripe_session_id = %s", $sid ) );
	if ( ! $nasza ) {
		// Obca/testowa sesja (np. stripe trigger) — przyjmujemy, logujemy, NIE failujemy retry.
		sklepag_zdarzenie( null, 'webhook_nieznana_sesja', $sid );
		return true;
	}

	$produkt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pp} WHERE id = %d", (int) $nasza->produkt_id ) );
	if ( ! $produkt ) {
		sklepag_zdarzenie( null, 'domkniecie_brak_produktu', $sid );
		return true;
	}

	$email = '';
	if ( ! empty( $sesja['customer_details']['email'] ) ) {
		$email = sanitize_email( $sesja['customer_details']['email'] );
	} elseif ( ! empty( $sesja['customer_email'] ) ) {
		$email = sanitize_email( $sesja['customer_email'] );
	}
	$kwota  = isset( $sesja['amount_total'] ) ? (int) $sesja['amount_total'] : (int) $produkt->cena_grosze;
	$waluta = isset( $sesja['currency'] ) ? strtoupper( (string) $sesja['currency'] ) : $produkt->waluta;
	$teraz  = current_time( 'mysql' );

	// 1) UTWORZENIE wiersza — INSERT IGNORE (UNIQUE stripe_session_id chroni tylko utworzenie).
	$wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$pz}
		 (stripe_session_id, produkt_id, produkt_nazwa_kupiona, produkt_cena_kupiona, produkt_plik_kupiony,
		  email_kupujacego, kwota_grosze, waluta, status, zgoda_tresc, zgoda_czas,
		  pobrania_limit, utworzono, zmieniono)
		 VALUES (%s, %d, %s, %d, %s, %s, %d, %s, 'oczekuje', %s, %s, %d, %s, %s)",
			$sid,
			$produkt->id,
			$produkt->nazwa,
			$produkt->cena_grosze,
			$produkt->plik_sciezka,
			$email,
			$kwota,
			$waluta,
			SKLEPAG_ZGODA_TRESC,
			isset( $sesja['metadata']['zgoda_czas'] ) ? $sesja['metadata']['zgoda_czas'] : $teraz,
			SKLEPAG_LIMIT_POBRAN,
			$teraz,
			$teraz
		)
	);

	// 2) CAS: oczekuje -> oplacone.
	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='oplacone', zmieniono=%s WHERE stripe_session_id=%s AND status='oczekuje'", $teraz, $sid ) );
	sklepag_oznacz_sesje( $sid, 'oplacona' );

	// 3) CAS: przejęcie wysyłki (oplacone/mail_blad -> mail_w_toku). Przegrany wraca bez maila.
	$przejete = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET status='mail_w_toku', zmieniono=%s WHERE stripe_session_id=%s AND status IN ('oplacone','mail_blad') AND mail_proby < 3",
			$teraz,
			$sid
		)
	);

	if ( ! $przejete ) {
		$stan = $wpdb->get_row( $wpdb->prepare( "SELECT status, zmieniono, mail_proby FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
		if ( ! $stan ) {
			return false;
		}
		if ( 'mail_wyslany' === $stan->status ) {
			return true; // Ktoś już domknął.
		}
		if ( 'mail_w_toku' === $stan->status ) {
			// Przejęcie po crashu: claim starszy niż 5 min wolno odebrać (stale-claim).
			// ŚWIADOMY KOMPROMIS (DECYZJE D5+D24): pad tuż PO wp_mail a PRZED zapisem
			// stanu = możliwy DRUGI mail po odebraniu claimu — lepszy podwójny niż
			// żaden. Wyścig RÓWNOCZESNY wyklucza CAS (delta maili = 1, zmierzone E2E).
			$odebrane = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$pz} SET zmieniono=%s WHERE stripe_session_id=%s AND status='mail_w_toku' AND zmieniono < %s",
					$teraz,
					$sid,
					gmdate( 'Y-m-d H:i:s', strtotime( $teraz ) - 300 )
				)
			);
			if ( ! $odebrane ) {
				return false; // Ktoś inny właśnie wysyła — retry wróci.
			}
		} else {
			return false; // mail_blad z wyczerpanymi próbami → panel-czerwone (K3), retry nic nie wskóra... ale zwracamy false, żeby ślad w retry był.
		}
	}

	// 4) Token pobrania (surowy do maila, skrót do bazy) + termin — dopiero PO przejęciu.
	$token = bin2hex( random_bytes( 32 ) );
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, zmieniono=%s WHERE stripe_session_id=%s AND status='mail_w_toku'",
			hash( 'sha256', $token ),
			gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_LINK_GODZIN * HOUR_IN_SECONDS ),
			$teraz,
			$sid
		)
	);

	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE stripe_session_id=%s", $sid ) );

	// 5) Mail: zapis treści PRZED pomiarem skutku (podgląd w panelu niezależnie od wyniku).
	$link  = admin_url( 'admin-post.php' ) . '?action=sklepag_pobierz&z=' . (int) $zam->id . '&t=' . $token;
	$tresc = sklepag_zbuduj_mail( $produkt, $zam, $link );
	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET mail_tresc=%s, zmieniono=%s WHERE id=%d", $tresc, $teraz, (int) $zam->id ) );

	$wyslano = false;
	if ( '' !== $email && is_email( $email ) ) {
		$wyslano = wp_mail( $email, 'Twój zakup: ' . $produkt->nazwa . ' — potwierdzenie i pobranie', $tresc );
	}

	// 6) Stan PO POMIARZE skutku (CAS z mail_w_toku).
	if ( $wyslano ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', zmieniono=%s WHERE stripe_session_id=%s AND status='mail_w_toku'", $teraz, $sid ) );
		sklepag_zdarzenie( (int) $zam->id, 'mail_wyslany', 'do: ' . $email );
		return true;
	}

	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_blad', mail_proby=mail_proby+1, zmieniono=%s WHERE stripe_session_id=%s AND status='mail_w_toku'", $teraz, $sid ) );
	sklepag_zdarzenie( (int) $zam->id, 'mail_blad', '' === $email ? 'brak adresu email w sesji' : 'wp_mail zwrocil false' );
	return false;
	// phpcs:enable
}

function sklepag_zbuduj_mail( $produkt, $zam, $link ) {
	$dni = (int) round( SKLEPAG_LINK_GODZIN / 24 );
	return "Dziękujemy za zakup w sklepie!\n\n"
		. 'Produkt: ' . $produkt->nazwa . "\n"
		. 'Kwota: ' . number_format( $zam->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $zam->waluta . " (TRYB TESTOWY — płatność nie została pobrana)\n\n"
		. "POBIERZ SWÓJ PRODUKT:\n" . $link . "\n"
		. 'Link jest ważny ' . $dni . ' dni i pozwala na ' . SKLEPAG_LIMIT_POBRAN . " pobrań.\n\n"
		. "INSTRUKCJA W SKRÓCIE:\n1. Pobierz paczkę ZIP z linku powyżej.\n2. W kokpicie WordPressa: Wtyczki → Dodaj → Wyślij wtyczkę na serwer → wybierz ZIP.\n3. Aktywuj i działaj — pełna instrukcja jest w paczce.\n\n"
		. "Masz pytanie? Odpisz na tego maila.\n";
}

/* ---------- STATUS dla strony „Dziękujemy" (bez danych osobowych) ---------- */

function sklepag_status_zamowienia( WP_REST_Request $zadanie ) {
	if ( ! sklepag_rate_limit( 'status', 30 ) ) {
		return new WP_REST_Response( array( 'blad' => 'limit' ), 429 );
	}
	$sid = sanitize_text_field( (string) $zadanie->get_param( 'sesja' ) );
	if ( '' === $sid || ! str_starts_with( $sid, 'cs_' ) ) {
		return new WP_REST_Response( array( 'status' => 'brak' ), 200 );
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}sklepag_zamowienia WHERE stripe_session_id=%s", $sid ) );
	return new WP_REST_Response( array( 'status' => $status ? $status : 'oczekuje' ), 200 );
}

/* ---------- RECONCILIATION — druga droga domykania (cron + self-heal) ---------- */

function sklepag_dociagnij_zalegle() {
	global $wpdb;
	$ps = $wpdb->prefix . 'sklepag_sesje_checkout';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$granica = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 120 );
	$sesje   = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT stripe_session_id FROM {$ps} WHERE stan='utworzona' AND utworzono < %s ORDER BY utworzono LIMIT 20",
			$granica
		)
	);
	// phpcs:enable

	foreach ( $sesje as $sid ) {
		$sesja = sklepag_stripe_pobierz_sesje( $sid );
		if ( is_wp_error( $sesja ) ) {
			sklepag_zdarzenie( null, 'reconciliation_blad', $sid . ': ' . $sesja->get_error_message() );
			continue;
		}
		if ( isset( $sesja['payment_status'] ) && 'unpaid' !== $sesja['payment_status'] ) {
			sklepag_domknij_zakup( $sesja ); // TEN SAM silnik co webhook (D24).
		} elseif ( isset( $sesja['status'] ) && 'expired' === $sesja['status'] ) {
			sklepag_oznacz_sesje( $sid, 'wygasla' );
		}
	}

	// 2) ZAMÓWIENIA niedomknięte (KRYTYCZNA z recenzji PR #33): sesja dostaje stan
	// 'oplacona' zaraz po CAS-ie płatności — ZANIM mail wyjdzie — więc pętla wyżej
	// nigdy nie widzi zamówień uwięzionych w mail_blad (pad wysyłki) ani mail_w_toku
	// (pad procesu). Podnosimy je tu TYM SAMYM silnikiem: CAS + stale-claim same
	// rozstrzygają, mail_proby<3 trzyma plan §6 (3 próby crona → panel-czerwone K3).
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$pz           = $wpdb->prefix . 'sklepag_zamowienia';
	$niedomkniete = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT stripe_session_id FROM {$pz} WHERE status IN ('oplacone','mail_w_toku','mail_blad') AND mail_proby < 3 AND zmieniono < %s ORDER BY zmieniono LIMIT 20",
			$granica
		)
	);
	// phpcs:enable
	foreach ( $niedomkniete as $sid ) {
		$sesja = sklepag_stripe_pobierz_sesje( $sid );
		if ( is_wp_error( $sesja ) ) {
			sklepag_zdarzenie( null, 'reconciliation_blad', $sid . ': ' . $sesja->get_error_message() );
			continue;
		}
		if ( isset( $sesja['payment_status'] ) && 'unpaid' !== $sesja['payment_status'] ) {
			sklepag_domknij_zakup( $sesja );
		}
	}

	// 3) Sprzątanie liczników rate-limit po oknie (tabela techniczna, wiersz żyje ~2 min).
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_rate_limit WHERE okno_do < UTC_TIMESTAMP()" );

	// 4) Retencja 90 dni (plan §5/§8; polityka deklaruje „dziennik zdarzeń (90 dni)"):
	// logowania i zdarzenia starsze wypadają. Historia SPRZEDAŻY żyje w `zamowienia`
	// (status, kwoty, mail_tresc) — jej retencja to D22, nie ten krok.
	$retencja = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 90 * DAY_IN_SECONDS );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_logowania WHERE czas < %s", $retencja ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE czas < %s", $retencja ) );
	// phpcs:enable
}
add_action( 'sklepag_dociagnij_zalegle', 'sklepag_dociagnij_zalegle' );
