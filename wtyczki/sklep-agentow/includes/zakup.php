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

	// Inkrementacja MUSI być atomowa: para get/set transient
	// gubi żądania równoległe — zmierzone 40/40 przepuszczonych przy progu 30.
	// Odczyt WŁASNEJ wartości przez LAST_INSERT_ID(expr) — osobny SELECT licznik
	// miał mikro-okno wyścigu (A czytał wartość zawyżoną przez równoległe B;
	// wzorzec przeniesiony z chatl_rate_limit).
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wynik = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$t} (klucz, licznik, okno_do) VALUES (%s, 1, %s)
		 ON DUPLICATE KEY UPDATE licznik = LAST_INSERT_ID(licznik + 1)",
			$klucz,
			gmdate( 'Y-m-d H:i:s', ( $okno + 2 ) * MINUTE_IN_SECONDS )
		)
	);
	// ⚠️ Poprawność zależy od tego, że `licznik` ZAWSZE się zmienia przy update —
	// wtedy MySQL zwraca 2 (nigdy 0 „bez zmian"). Gdyby ktoś kiedyś zmienił
	// wyrażenie ON DUPLICATE KEY UPDATE na takie, które może zostawić licznik bez
	// zmiany, gałąź `else` fałszywie zgłosiłaby awarię i przepuściła nadmiar
	// (fail-open). Dziś licznik+1 to gwarantuje.
	if ( 1 === $wynik ) {
		$ile = 1; // Świeży wiersz w tym oknie (INSERT zwraca 1).
	} elseif ( 2 === $wynik ) {
		$ile = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // Inkrement (ODKU zwraca 2).
	} else {
		$ile = 0;
	}
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
			// Jedna baza czasu zgody = GMT (jak ścieżka główna w metadanych Stripe); mail i panel
			// przeliczają na czas lokalny. Awaryjnie (brak metadanych) też GMT, nie czas lokalny.
			isset( $sesja['metadata']['zgoda_czas'] ) ? $sesja['metadata']['zgoda_czas'] : current_time( 'mysql', true ),
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
					gmdate( 'Y-m-d H:i:s', strtotime( $teraz ) )
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

	// 4b) Zalążek opinii + jednorazowy link „oceń bota" (D32) — w tej samej sekcji
	// chronionej CAS-em mail_w_toku co token pobrania: retry maila odświeża token
	// opinii, ale opinii już przysłanej nie rusza (wtedy mail idzie bez akapitu oceny).
	$token_op = sklepag_opinia_zalazek( (int) $zam->id, (int) $produkt->id );
	$link_op  = '' !== $token_op ? admin_url( 'admin-post.php' ) . '?action=sklepag_ocen&z=' . (int) $zam->id . '&t=' . $token_op : '';

	// 5) Mail: zapis treści PRZED pomiarem skutku (podgląd w panelu niezależnie od wyniku).
	$link  = admin_url( 'admin-post.php' ) . '?action=sklepag_pobierz&z=' . (int) $zam->id . '&t=' . $token;
	$tresc = sklepag_zbuduj_mail( $produkt, $zam, $link, $link_op );
	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET mail_tresc=%s, zmieniono=%s WHERE id=%d", $tresc, $teraz, (int) $zam->id ) );

	$wyslano = false;
	if ( '' !== $email && is_email( $email ) ) {
		$wyslano = sklepag_wyslij_mail_sklepu( $email, 'Twój zakup: ' . $produkt->nazwa . ' — potwierdzenie i pobranie', $tresc );
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

/**
 * Filtry nadawcy jako nazwane funkcje — remove_filter wymaga tej samej
 * referencji, a nazwana funkcja gwarantuje to bez trzymania closure.
 */
function sklepag_filtr_nadawca_email( $from ) {
	$wlasny = (string) get_option( 'sklepag_nadawca_email', '' );
	return ( '' !== $wlasny && is_email( $wlasny ) ) ? $wlasny : $from;
}

function sklepag_filtr_nadawca_nazwa( $name ) {
	$wlasna = (string) get_option( 'sklepag_nadawca_nazwa', '' );
	return '' !== $wlasna ? $wlasna : $name;
}

/**
 * wp_mail z nadawcą z ustawień sklepu — filtry zakładane WYŁĄCZNIE wokół
 * NASZEJ wysyłki i zdejmowane od razu (granica planu §3: maile reszty
 * strony pozostają nietknięte).
 *
 * @param string $adresat Adresat.
 * @param string $temat   Temat.
 * @param string $tresc   Treść.
 * @return bool Wynik wp_mail.
 */
function sklepag_wyslij_mail_sklepu( $adresat, $temat, $tresc ) {
	// Reply-To = „mail kontaktowy właściciela" z ustawień (gdy ustawiony): mail mówi „odpisz na tego
	// maila", a odpowiedź szła na adres NADAWCY — przy nadawcy typu noreply ginęła (audyt 25.08).
	$naglowki = array();
	$kontakt  = (string) get_option( 'sklepag_email_kontaktowy', '' );
	if ( '' !== $kontakt && is_email( $kontakt ) ) {
		$naglowki[] = 'Reply-To: ' . $kontakt;
	}
	add_filter( 'wp_mail_from', 'sklepag_filtr_nadawca_email' );
	add_filter( 'wp_mail_from_name', 'sklepag_filtr_nadawca_nazwa' );
	try {
		return wp_mail( $adresat, $temat, $tresc, $naglowki );
	} finally {
		// Filtry schodzą też przy rzucie z wp_mail — nie mogą wisieć na resztę żądania.
		remove_filter( 'wp_mail_from', 'sklepag_filtr_nadawca_email' );
		remove_filter( 'wp_mail_from_name', 'sklepag_filtr_nadawca_nazwa' );
	}
}

function sklepag_zbuduj_mail( $produkt, $zam, $link, $link_opinii = '' ) {
	$dni   = (int) round( SKLEPAG_LINK_GODZIN / 24 );
	$ocena = '' !== $link_opinii
		? "OCEŃ SWÓJ ZAKUP:\nGdy przetestujesz bota, zostaw opinię — to 2 minuty:\n" . $link_opinii . "\nLink do opinii jest jednorazowy i ważny " . (int) SKLEPAG_OPINIA_DNI . " dni. Opinię publikujemy po potwierdzeniu zakupu i akceptacji.\n\n"
		: '';
	// Potwierdzenie zgody na trwałym nośniku (dyrektywa 2011/83/UE art. 8 ust. 7 lit. b — potwierdzenie
	// umowy ma zawierać potwierdzenie uprzedniej wyraźnej zgody i przyjęcia do wiadomości z art. 16 lit. m).
	// Treść i chwila zgody = dokładnie to, co zapisano w zamówieniu przy zakupie (audyt 25.08).
	$zgoda = '';
	if ( ! empty( $zam->zgoda_tresc ) ) {
		$kiedy  = ! empty( $zam->zgoda_czas ) ? get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) : '';
		$zgoda  = "POTWIERDZENIE TWOJEJ ZGODY:\n" . $zam->zgoda_tresc . "\n";
		$zgoda .= '' !== $kiedy ? 'Zgodę zaznaczyłeś przy zakupie ' . $kiedy . ".\n\n" : "\n";
	}
	// Gwarancja z danych produktu (ta sama, którą obiecuje strona produktu; puste = bez akapitu).
	$gwarancja = '';
	if ( ! empty( $produkt->slug ) && function_exists( 'sklepag_tresc_produktu' ) ) {
		$t = sklepag_tresc_produktu( $produkt->slug );
		if ( $t && ! empty( $t['gwarancja'] ) ) {
			$gwarancja = "GWARANCJA:\n" . $t['gwarancja'] . "\nAby z niej skorzystać, odpisz na tego maila.\n\n";
		}
	}
	return "Dziękujemy za zakup w sklepie!\n\n"
		. 'Produkt: ' . $produkt->nazwa . "\n"
		. 'Kwota: ' . number_format( $zam->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $zam->waluta . " (TRYB TESTOWY — płatność nie została pobrana)\n\n"
		. $zgoda
		. "POBIERZ SWÓJ PRODUKT:\n" . $link . "\n"
		. 'Link jest ważny ' . $dni . ' dni i pozwala na ' . SKLEPAG_LIMIT_POBRAN . " pobrań.\n\n"
		. "INSTRUKCJA W SKRÓCIE:\n1. Pobierz paczkę ZIP z linku powyżej.\n2. W kokpicie WordPressa: Wtyczki → Dodaj → Wyślij wtyczkę na serwer → wybierz ZIP.\n3. Aktywuj i działaj — pełna instrukcja jest w paczce.\n\n"
		. $gwarancja
		. $ocena
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

/**
 * @param int $budzet_s Budżet CZASU ściany w sekundach dla wywołań SYNCHRONICZNYCH
 *                      (self-heal/przycisk na dashboardzie: worst-case
 *                      40 requestów × 15 s timeout przekroczyłby max_execution_time
 *                      hostingu i wywalił render: „100 s > 30 s").
 *                      0 = bez limitu (WP-cron — kontekst tolerancyjny na czas).
 *                      Niedociągnięte w budżecie zostają na następny bieg (idempotentne).
 */
function sklepag_dociagnij_zalegle( $budzet_s = 0 ) {
	global $wpdb;
	$ps    = $wpdb->prefix . 'sklepag_sesje_checkout';
	$start = microtime( true );

	$po_budzecie = static function () use ( $budzet_s, $start ) {
		return $budzet_s > 0 && ( microtime( true ) - $start ) >= $budzet_s;
	};

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
		if ( $po_budzecie() ) {
			break; // Resztę dociągnie cron — render admina nie może wisieć.
		}
		$sesja = sklepag_stripe_pobierz_sesje( $sid );
		if ( is_wp_error( $sesja ) ) {
			continue; // Chwilowy błąd sieci/Stripe — następny przebieg spróbuje ponownie, bez zaśmiecania dziennika.
		}
		if ( isset( $sesja['payment_status'] ) && 'unpaid' !== $sesja['payment_status'] ) {
			sklepag_domknij_zakup( $sesja ); // TEN SAM silnik co webhook (D24).
		} elseif ( isset( $sesja['status'] ) && 'expired' === $sesja['status'] ) {
			sklepag_oznacz_sesje( $sid, 'wygasla' );
		}
	}

	// 2) ZAMÓWIENIA niedomknięte: sesja dostaje stan
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
		if ( $po_budzecie() ) {
			break; // Jw.: budżet czasu wywołań synchronicznych.
		}
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
