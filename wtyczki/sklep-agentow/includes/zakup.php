<?php
/**
 * Przepływ zakupu: Kup → Checkout Session → webhook/reconciliation → SILNIK
 * domykania (JEDEN dla obu dróg; przejścia stanu WYŁĄCZNIE przez CAS).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SKLEPAG_ZGODA_TRESC = 'Wyrażam zgodę na natychmiastowe dostarczenie treści cyfrowej i przyjmuję do wiadomości utratę prawa odstąpienia od umowy (art. 38 ustawy o prawach konsumenta).';

/* ---------- RATE LIMIT (atomowy licznik w tabeli, per IP i okno minutowe;
	webhook JAWNIE poza limiterem) ---------- */

/**
 * Ile sekund okno idempotencji wolno oddawać zapamiętany adres checkoutu.
 * Podwójny klik mieści się poniżej sekundy; wpisanie karty u Stripe trwa dziesiątki sekund,
 * więc ten próg rozdziela oba zdarzenia BEZ pytania Stripe o stan — awaria łączności ze Stripe
 * nie może blokować ścieżki zakupu.
 */
const SKLEPAG_IDEM_OKNO_S = 5;

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
	// LIMITER JAKO PIERWSZY. To wejście jest publiczne (`admin_post_nopriv_`), a każde
	// sprawdzenie niżej zostawia wpis w dzienniku zdarzeń — bez limitu na wejściu dowolny
	// obcy mógłby jednym skryptem zapchać właścicielowi dziennik tysiącami wpisów
	// „nieważny token". Licznik limitera trzyma jeden wiersz na adres i minutę, więc
	// kosztuje stałą wartość niezależnie od liczby prób.
	if ( ! sklepag_rate_limit( 'kup', 10 ) ) {
		wp_die( 'Za dużo prób zakupu z tego adresu w ciągu minuty. Odczekaj do pełnej minuty i kliknij przycisk zakupu ponownie — nic nie zostało pobrane z Twojego konta. Jeśli kupujesz z sieci firmowej, licznik obejmuje też inne osoby w tej sieci.', 'Sklep Agentów', 429 );
	}

	// Nieważny nonce sprawdzamy PRZED rdzeniem, bo domyślna ścieżka (check_admin_referer →
	// wp_nonce_ays) pokazuje kupującemu angielski komunikat rdzenia i NIE zostawia śladu.
	// Najczęstsza przyczyna to pamięć podręczna strony: strona produktu serwowana z cache
	// niesie nonce sprzed ponad doby, więc „Kup teraz" jest martwy dla WSZYSTKICH
	// odwiedzających, a właściciel nie ma tego skąd zobaczyć. Wpis w dzienniku zamienia
	// cichą awarię w głośną; sprawdzenie rdzenia zostaje niżej (referer + hooki).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- to JEST weryfikacja nonce.
	$nonce_kup = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce_kup, 'sklepag_kup' ) ) {
		sklepag_zdarzenie(
			null,
			'kup_nonce_niewazny',
			'formularz zakupu odrzucony: token strony wygasł lub go nie było' .
			( '' === $nonce_kup ? ' (brak tokenu w żądaniu)' : ' (token starszy niż 24 h — typowe przy pamięci podręcznej strony)' )
		);
		wp_die(
			'Ta strona była za długo otwarta albo pokazała się z pamięci podręcznej, więc formularz zakupu stracił ważność. ' .
			'Odśwież stronę produktu (Ctrl+F5) i kliknij „Kup teraz" jeszcze raz — nic nie zostało pobrane z Twojego konta. ' .
			'Jeśli to się powtarza, daj znać właścicielowi sklepu: przyczyną bywa pamięć podręczna na stronie produktu.',
			'Sklep Agentów',
			403
		);
	}
	check_admin_referer( 'sklepag_kup' );

	$sklep_url = get_permalink( (int) get_option( 'sklepag_strona_sklep' ) );

	$produkt_id = isset( $_POST['produkt_id'] ) ? absint( $_POST['produkt_id'] ) : 0;
	$zgoda      = isset( $_POST['zgoda'] ) && '1' === $_POST['zgoda'];

	if ( ! $zgoda ) {
		wp_die( 'Zakup wymaga zgody na natychmiastowe dostarczenie treści cyfrowej.', 'Sklep Agentów', 400 );
	}

	// 0.9.25: płatność OSADZONA (formularz karty na naszej stronie). Skrypt `platnosc.js` woła ten sam
	// endpoint z polem `osadzona=1` i dostaje JSON z `client_secret` zamiast przekierowania. Bez JS
	// (albo bez klucza publikowalnego w Ustawieniach) formularz idzie zwykłym POST-em i kupujący
	// trafia na hostowaną stronę Stripe jak do 0.9.24 — obie drogi kończą się tą samą sesją,
	// tym samym webhookiem i tą samą stroną „Dziękujemy”.
	$osadzona = isset( $_POST['osadzona'] ) && '1' === $_POST['osadzona'] && '' !== sklepag_stripe_klucz_publikowalny();

	// Dane do faktury — NIEOBOWIĄZKOWE, nasze własne pola (nie Stripe): nazwa, NIP, ADRES. Faktura dla
	// firmy wymaga nazwy, NIP-u i adresu nabywcy (ustawa o VAT art. 106e ust. 1 pkt 3), więc albo komplet,
	// albo wszystkie puste — sam NIP dawałby właścicielowi fałszywe „mam dane do faktury”. Zły NIP = odmowa
	// z komunikatem (kupujący poprawia). ⛔ Podanie NIP-u NICZEGO nie zmienia w prawach kupującego (zgoda z art. 38,
	// gwarancja, reklamacje): art. 38a ustawy o prawach konsumenta wiąże ochronę z charakterem zakupu, nie z NIP-em.
	$faktura      = array(
		'nazwa' => isset( $_POST['faktura_nazwa'] ) ? sanitize_text_field( wp_unslash( $_POST['faktura_nazwa'] ) ) : '',
		'nip'   => isset( $_POST['faktura_nip'] ) ? preg_replace( '/[\s-]/', '', sanitize_text_field( wp_unslash( $_POST['faktura_nip'] ) ) ) : '',
		'adres' => isset( $_POST['faktura_adres'] ) ? trim( preg_replace( '/\s*\n\s*/', ', ', sanitize_textarea_field( wp_unslash( $_POST['faktura_adres'] ) ) ) ) : '',
	);
	$blad_faktury = '';
	if ( '' !== $faktura['nip'] && ! sklepag_nip_poprawny( $faktura['nip'] ) ) {
		$blad_faktury = 'NIP nie jest poprawny (10 cyfr z sumą kontrolną). Popraw albo zostaw dane do faktury puste.';
	} elseif ( ( '' !== $faktura['nazwa'] || '' !== $faktura['nip'] || '' !== $faktura['adres'] ) && ( '' === $faktura['nazwa'] || '' === $faktura['nip'] || '' === $faktura['adres'] ) ) {
		$blad_faktury = 'Do faktury potrzebne są wszystkie trzy dane: nazwa firmy, NIP i adres. Uzupełnij brakujące albo zostaw wszystkie pola puste.';
	}
	if ( '' !== $blad_faktury ) {
		if ( $osadzona ) {
			wp_send_json( array( 'blad' => $blad_faktury ), 400 );
		}
		wp_die( esc_html( $blad_faktury ) . ' Wróć do strony produktu i popraw formularz.', 'Sklep Agentów', 400 );
	}

	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_produkty';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; walidacja zakupu czyta świeży stan produktu.
	$produkt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND aktywny = 1", $produkt_id ) );

	if ( ! $produkt || '' === $produkt->plik_sciezka || ! file_exists( $produkt->plik_sciezka ) ) {
		wp_die( 'Produkt niedostępny.', 'Sklep Agentów', 404 );
	}

	// Okno idempotencji: podwójny klik nie tworzy drugiej sesji.
	//
	// DWA warunki, oba konieczne, bo każdy z osobna ma dziurę:
	//
	// 1. WIEK OKNA (SKLEPAG_IDEM_OKNO_S). Podwójny klik trwa ułamek sekundy; opłacenie
	//    checkoutu wymaga wpisania karty, więc trwa dziesiątki sekund. Krótkie okno
	//    rozdziela te dwa zdarzenia bez pytania kogokolwiek o stan.
	//    ⛔ To jest warunek NADRZĘDNY i nie da się go zastąpić kontrolą stanu sesji:
	//    `oplacona` zapisuje wyłącznie sklepag_domknij_zakup(), wołane z webhooka i z
	//    reconciliation — a reconciliation pomija sesje młodsze niż 120 s. Zanim webhook
	//    dotrze, opłacona sesja LEŻY W BAZIE JAKO `utworzona` i jest nieodróżnialna od
	//    czekającej. Sam stan lokalny zwężał więc okno wady, zamiast ją zamknąć.
	// 2. STAN SESJI. Łapie przypadki, w których webhook JUŻ wrócił (sesja opłacona,
	//    wygaszona) albo wiersza sesji w ogóle nie ma — brak danych to odmowa, nie domysł.
	//
	// Okno jest kluczowane adresem IP (`sklepag_ip()` czyta goły REMOTE_ADDR), więc za
	// wspólnym adresem (CGNAT operatora, biuro za NAT) trafia też w DRUGĄ osobę. Krótkie
	// okno ogranicza to do kilku sekund; pełne rozdzielenie kupujących wymagałoby klucza
	// związanego z sesją przeglądarki i jest znanym, świadomie otwartym ograniczeniem tego okna.
	// Okno kluczowane też TRYBEM: sesja hostowana ma `url`, osadzona ma `client_secret` — pomieszanie
	// oddałoby JS-owi adres do przekierowania albo formularzowi sekret bez strony.
	$okno = 'sklepag_idem_' . md5( sklepag_ip() . '|' . $produkt_id . '|' . ( $osadzona ? 'o' : 'h' ) );
	$juz  = get_transient( $okno );
	if ( is_array( $juz ) && ( ! empty( $juz['url'] ) || ! empty( $juz['client_secret'] ) ) ) {
		$wiek_ok = ! empty( $juz['czas'] ) && ( time() - (int) $juz['czas'] ) <= SKLEPAG_IDEM_OKNO_S;
		if ( $wiek_ok && ! empty( $juz['sid'] ) && sklepag_sesja_czeka_na_platnosc( $juz['sid'] ) ) {
			if ( $osadzona ) {
				wp_send_json(
					array(
						'clientSecret' => $juz['client_secret'],
						'sesja'        => $juz['sid'],
					)
				);
			}
			wp_redirect( $juz['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- adres Stripe (zewnętrzny, z API).
			exit;
		}
		delete_transient( $okno );
	}

	$zgoda_czas = current_time( 'mysql', true );
	$sesja      = sklepag_stripe_utworz_sesje( $produkt, $zgoda_czas, wp_generate_uuid4(), $osadzona, $faktura );

	$brak = is_wp_error( $sesja ) || empty( $sesja['id'] ) || ( $osadzona ? empty( $sesja['client_secret'] ) : empty( $sesja['url'] ) );
	if ( $brak ) {
		sklepag_zdarzenie( null, 'checkout_blad', is_wp_error( $sesja ) ? $sesja->get_error_message() : ( $osadzona ? 'brak id/client_secret w odpowiedzi' : 'brak id/url w odpowiedzi' ) );
		if ( $osadzona ) {
			wp_send_json( array( 'blad' => 'Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.' ), 502 );
		}
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

	set_transient(
		$okno,
		array(
			'url'           => isset( $sesja['url'] ) ? (string) $sesja['url'] : '',
			'client_secret' => isset( $sesja['client_secret'] ) ? (string) $sesja['client_secret'] : '',
			'sid'           => $sesja['id'],
			'czas'          => time(),
		),
		MINUTE_IN_SECONDS
	);

	if ( $osadzona ) {
		wp_send_json(
			array(
				'clientSecret' => (string) $sesja['client_secret'],
				'sesja'        => (string) $sesja['id'],
			)
		);
	}
	wp_redirect( $sesja['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
}

/**
 * Klucz publikowalny Stripe (pk_…). Pusty = płatność osadzona wyłączona, zostaje strona hostowana.
 */
function sklepag_stripe_klucz_publikowalny() {
	$pk = trim( (string) get_option( 'sklepag_stripe_klucz_publikowalny', '' ) );
	return ( '' !== $pk && str_starts_with( $pk, 'pk_' ) ) ? $pk : '';
}

/**
 * NIP: 10 cyfr z poprawną sumą kontrolną (wagi 6,5,7,2,3,4,5,6,7; reszta z 11 = ostatnia cyfra).
 * Jedna definicja dla Ustawień (dane sprzedawcy) i formularza zakupu (dane do faktury).
 *
 * @param string $cyfry Same cyfry (bez myślników i spacji).
 */
function sklepag_nip_poprawny( $cyfry ) {
	if ( ! preg_match( '/^[0-9]{10}$/', (string) $cyfry ) ) {
		return false;
	}
	$wagi = array( 6, 5, 7, 2, 3, 4, 5, 6, 7 );
	$suma = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$suma += $wagi[ $i ] * (int) $cyfry[ $i ];
	}
	return ( $suma % 11 ) === (int) $cyfry[9];
}

/**
 * Dane do faktury z metadanych sesji (podane przez kupującego w formularzu) — jedna linia do bazy i maila.
 *
 * @param array $sesja Obiekt sesji Stripe.
 */
function sklepag_dane_faktury_z_sesji( $sesja ) {
	$m      = isset( $sesja['metadata'] ) && is_array( $sesja['metadata'] ) ? $sesja['metadata'] : array();
	$nazwa  = isset( $m['faktura_nazwa'] ) ? sanitize_text_field( (string) $m['faktura_nazwa'] ) : '';
	$nip    = isset( $m['faktura_nip'] ) ? preg_replace( '/[^0-9]/', '', (string) $m['faktura_nip'] ) : '';
	$adres  = isset( $m['faktura_adres'] ) ? sanitize_text_field( (string) $m['faktura_adres'] ) : '';
	$czesci = array();
	if ( '' !== $nazwa ) {
		$czesci[] = $nazwa;
	}
	if ( '' !== $nip ) {
		$czesci[] = 'NIP ' . $nip;
	}
	if ( '' !== $adres ) {
		$czesci[] = $adres;
	}
	return mb_substr( implode( ', ', $czesci ), 0, 300 );
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

	// ZWROT PIENIĘDZY. Stripe od aktualizacji z 28.10.2024 zaleca `refund.created` —
	// `charge.refunded` nadal przychodzi, ale dokumentacja sama odsyła po szczegóły do
	// `refund.created`. Nasłuchujemy OBU i rozstrzygamy po kwocie już zapisanej w bazie,
	// żeby dwa zdarzenia o tym samym zwrocie nie odjęły pieniędzy dwa razy.
	if ( 'refund.created' === $typ || 'refund.updated' === $typ || 'charge.refunded' === $typ ) {
		if ( ! sklepag_zaksieguj_zwrot( $typ, $sesja ) ) {
			// Nie udało się ustalić, czyj to zwrot, bo Stripe nie odpowiedział — prosimy
			// o ponowienie. Odpowiedź 200 znaczyłaby „przyjęte", a zwrot przepadłby po cichu.
			return new WP_REST_Response(
				array(
					'ok'    => false,
					'powod' => 'ponow',
				),
				500
			);
		}
	}

	// OBCIĄŻENIE ZWROTNE (kupujący reklamuje płatność w swoim banku). Pieniądze schodzą
	// z konta właściciela od razu, więc zamówienie przestaje być przychodem tak samo jak zwrot.
	if ( 0 === strpos( $typ, 'charge.dispute.' ) ) {
		if ( ! sklepag_zaksieguj_spor( $typ, $sesja ) ) {
			// Ten sam powód co przy zwrocie: nie udało się ustalić, czyje to obciążenie,
			// bo Stripe nie odpowiedział. Odpowiedź 200 znaczyłaby „przyjęte", a pieniądze
			// zabrane przez bank zostałyby w przychodzie na zawsze.
			return new WP_REST_Response(
				array(
					'ok'    => false,
					'powod' => 'ponow',
				),
				500
			);
		}
	}

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Czy sesja checkout WCIĄŻ czeka na płatność — a więc czy wolno oddać jej adres z okna
 * idempotencji? Stan trzymamy u siebie (`sklepag_oznacz_sesje`), więc pytanie
 * nie kosztuje wywołania Stripe i nie psuje się przy awarii łączności.
 * Brak wiersza = false: nieznanego checkoutu nie podajemy kupującemu.
 */
function sklepag_sesja_czeka_na_platnosc( $sesja_id ) {
	if ( '' === $sesja_id ) {
		return false;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$stan = $wpdb->get_var(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z prefiksu.
		$wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}sklepag_sesje_checkout WHERE stripe_session_id = %s", $sesja_id )
	);
	return 'utworzona' === $stan;
}

function sklepag_oznacz_sesje( $sesja_id, $stan ) {
	if ( '' === $sesja_id ) {
		return;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $wpdb->prefix . 'sklepag_sesje_checkout', array( 'stan' => $stan ), array( 'stripe_session_id' => $sesja_id ), array( '%s' ), array( '%s' ) );
}

/* ---------- ZWROTY I OBCIĄŻENIA ZWROTNE (księgowość panelu) ---------- */

/**
 * Identyfikator płatności ze zdarzenia o zwrocie/sporze. Obiekty `refund`, `charge`
 * i `dispute` niosą `payment_intent`; identyfikatora sesji checkout NIE niesie żaden.
 */
function sklepag_platnosc_ze_zdarzenia( $obiekt ) {
	if ( ! isset( $obiekt['payment_intent'] ) ) {
		return '';
	}
	return is_array( $obiekt['payment_intent'] )
		? (string) ( $obiekt['payment_intent']['id'] ?? '' )
		: (string) $obiekt['payment_intent'];
}

/**
 * Zamówienie po identyfikatorze płatności.
 *
 * Zamówienia sprzed wersji schematu 6 nie mają zapisanej płatności — dla nich pytamy
 * Stripe o sesję checkout tej płatności i szukamy po identyfikatorze sesji. Znaleziony
 * numer płatności dopisujemy, żeby drugi raz już nie pytać.
 *
 * `$powod` (parametr wyjściowy) niesie DLACZEGO nie ma zamówienia — 'nie_nasze' albo
 * 'awaria:<opis>'. Bez tego wołający musiał pytać Stripe DRUGI raz w tym samym żądaniu,
 * żeby odróżnić te dwa przypadki: dwa razy 15 s limitu i pamięć nietrafień omijana.
 */
function sklepag_zamowienie_po_platnosci( $payment_intent, &$powod = null ) {
	global $wpdb;
	$powod = '';
	$pz    = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE payment_intent_id=%s", $payment_intent ) );
	if ( $zam ) {
		return $zam;
	}

	// Pamięć NIETRAFIEŃ. Na koncie Stripe dzielonym z inną integracją każdy cudzy zwrot
	// pytałby nasz sklep o sesję — z 15-sekundowym limitem czasu i przy każdym powtórzeniu
	// powiadomienia. Raz ustalone „to nie nasza płatność" trzymamy dobę.
	$klucz_pudla = 'sklepag_niepowiazana_' . md5( $payment_intent );
	if ( 'nie' === get_transient( $klucz_pudla ) ) {
		$powod = 'nie_nasze';
		return null;
	}
	$sesja = sklepag_stripe_sesja_po_platnosci( $payment_intent );
	if ( is_wp_error( $sesja ) || empty( $sesja['id'] ) ) {
		if ( is_wp_error( $sesja ) && 'sklepag_brak_sesji' !== $sesja->get_error_code() ) {
			// AWARIA łączności — dane wciąż mogą być nasze. Nie zapamiętujemy nietrafienia
			// (zapamiętane zamknęłoby zwrot na dobę przez jeden timeout).
			$powod = 'awaria:' . $sesja->get_error_message();
			return null;
		}
		if ( is_wp_error( $sesja ) ) {
			set_transient( $klucz_pudla, 'nie', DAY_IN_SECONDS ); // Stripe odpowiedział: nie zna takiej sesji.
		}
		$powod = 'nie_nasze';
		return null;
	}
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE stripe_session_id=%s", (string) $sesja['id'] ) );
	if ( $zam ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET payment_intent_id=%s WHERE id=%d", $payment_intent, (int) $zam->id ) );
		$zam->payment_intent_id = $payment_intent;
	} else {
		$powod = 'nie_nasze'; // sesja u Stripe jest, ale nie należy do tego sklepu
	}
	// phpcs:enable
	return $zam;
}

/**
 * Czy ten konkretny zwrot był już zaksięgowany?
 *
 * Sprawdzamy po identyfikatorze zwrotu zapisanym w dzienniku zdarzeń. Bez tego dwa RÓŻNE
 * zwroty częściowe po tyle samo (np. dwa razy po 50 zł z zamówienia za 200 zł) policzyłyby
 * się jak jeden, bo porównanie samych kwot ich nie odróżnia.
 *
 * `$typ_wpisu` zawęża pytanie do JEDNEGO rodzaju wpisu. Bez tego zawężenia ścieżka
 * COFNIĘCIA zwrotu nie miała własnego znacznika: jej własny wpis `zwrot_nieudany` pasował
 * do wzorca `zwrot_%`, więc powtórzone powiadomienie o odrzuceniu odejmowało kwotę drugi raz.
 */
function sklepag_zwrot_juz_zaksiegowany( $zamowienie_id, $refund_id, $typ_wpisu = '' ) {
	global $wpdb;
	if ( '' === $refund_id ) {
		return false;
	}
	$opis_jak = '%' . $wpdb->esc_like( $refund_id ) . '%';
	if ( '' !== $typ_wpisu ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id=%d AND typ=%s AND opis LIKE %s LIMIT 1",
				(int) $zamowienie_id,
				$typ_wpisu,
				$opis_jak
			)
		);
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id=%d AND typ LIKE %s AND opis LIKE %s LIMIT 1",
			(int) $zamowienie_id,
			$wpdb->esc_like( 'zwrot_' ) . '%',
			$opis_jak
		)
	);
}

/**
 * Zapisuje w zamówieniu, ile pieniędzy zeszło z konta właściciela.
 *
 * Dwa źródła prawdy, o różnej wadze:
 *  - obiekt `charge` (zdarzenie `charge.refunded`) niesie `amount_refunded`, czyli SUMĘ
 *    wszystkich zwrotów tej płatności. To wartość autorytatywna i wolno jej zejść w dół —
 *    tak wraca odrzucony przez bank zwrot.
 *  - obiekt `refund` (zdarzenia `refund.*`) niesie kwotę JEDNEGO zwrotu. Doliczamy go do
 *    dotychczasowej sumy, ale tylko raz — po identyfikatorze zwrotu, nie po kwocie.
 *
 * Zwrot ze stanem `failed` albo `canceled` NIE jest zwrotem: pieniądze zostały u sprzedawcy,
 * więc jeśli wcześniej go policzyliśmy, odejmujemy z powrotem.
 */
function sklepag_zaksieguj_zwrot( $typ, $obiekt ) {
	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';

	$pi = sklepag_platnosc_ze_zdarzenia( $obiekt );
	if ( '' === $pi ) {
		sklepag_zdarzenie( null, 'zwrot_bez_powiazania', $typ . ': zdarzenie bez identyfikatora płatności' );
		return true;
	}
	$powod = '';
	$zam   = sklepag_zamowienie_po_platnosci( $pi, $powod );
	if ( ! $zam ) {
		return sklepag_zwrot_nieprzypisany( $typ, $pi, $powod );
	}

	$juz         = (int) $zam->zwrocono_grosze;
	$refund_id   = isset( $obiekt['id'] ) && is_string( $obiekt['id'] ) ? (string) $obiekt['id'] : '';
	$stan_zwrotu = isset( $obiekt['status'] ) ? (string) $obiekt['status'] : '';
	$nieudany    = in_array( $stan_zwrotu, array( 'failed', 'canceled' ), true );

	if ( isset( $obiekt['amount_refunded'] ) ) {
		// Obiekt charge — suma wszystkich zwrotów, także po korekcie w dół.
		$kwota = (int) $obiekt['amount_refunded'];
	} elseif ( $nieudany ) {
		// Zwrot odrzucony: pieniądze zostają u sprzedawcy. Odejmujemy, jeśli był policzony.
		// KOLEJNOŚĆ MA ZNACZENIE: najpierw pytamy o WŁASNY wpis cofnięcia, bo po pierwszym
		// cofnięciu wpis `zwrot_nieudany` sam pasuje do wzorca `zwrot_%` i drugie pytanie
		// odpowiedziałoby „tak, było" także przy powtórce.
		if ( sklepag_zwrot_juz_zaksiegowany( (int) $zam->id, $refund_id, 'zwrot_nieudany' ) ) {
			return true; // to cofnięcie już policzyliśmy — powtórka powiadomienia.
		}
		if ( ! sklepag_zwrot_juz_zaksiegowany( (int) $zam->id, $refund_id ) ) {
			return true; // tego zwrotu nigdy nie policzyliśmy — nie ma czego cofać.
		}
		$kwota = max( 0, $juz - (int) ( $obiekt['amount'] ?? 0 ) );
		sklepag_zapisz_zwrot( $zam, $kwota, 'zwrot_nieudany', 'zwrot ' . $refund_id . ' odrzucony (' . $stan_zwrotu . ') — pieniądze wracają do przychodu' );
		return true;
	} elseif ( '' === $refund_id ) {
		// Zdarzenie bez identyfikatora zwrotu: nie da się odróżnić NOWEGO zwrotu od powtórki
		// tego samego. Liczymy ostrożnie — bierzemy większą z wartości zamiast dodawać, żeby
		// powtórzone powiadomienie nie odjęło pieniędzy drugi raz.
		$kwota = max( $juz, (int) ( $obiekt['amount'] ?? 0 ) );
	} else {
		// Obiekt refund z identyfikatorem — kwota jednego zwrotu, liczona raz na identyfikator.
		if ( sklepag_zwrot_juz_zaksiegowany( (int) $zam->id, $refund_id ) ) {
			return true;
		}
		$kwota = $juz + (int) ( $obiekt['amount'] ?? 0 );
	}

	if ( $kwota === $juz ) {
		return true;
	}
	$pelny = ( $kwota >= (int) $zam->kwota_grosze );
	sklepag_zapisz_zwrot(
		$zam,
		$kwota,
		$pelny ? 'zwrot_pelny' : 'zwrot_czesciowy',
		'zwrócono łącznie ' . number_format_i18n( $kwota / 100, 2 ) . ' ' . $zam->waluta .
		' z ' . number_format_i18n( (int) $zam->kwota_grosze / 100, 2 ) . ' ' . $zam->waluta .
		' (' . $typ . ( '' !== $refund_id ? ', zwrot ' . $refund_id : '' ) . ')'
	);
	return true;
}

/**
 * Zwrot, którego nie da się przypisać do zamówienia.
 *
 * Rozróżniamy dwie zupełnie różne sytuacje, które wcześniej dostawały ten sam wpis
 * i to samo „ok" dla Stripe: CUDZA płatność (inna integracja na tym samym koncie —
 * nie nasza sprawa) oraz AWARIA łączności ze Stripe (nasza sprawa, dane wciąż są).
 * W drugim przypadku oddajemy błąd, żeby Stripe ponowił powiadomienie — inaczej
 * zwrot przepadał po cichu przy jednym timeoucie.
 *
 * Rozstrzygnięcie bierzemy z `$powod` ustalonego przez `sklepag_zamowienie_po_platnosci`.
 * Wcześniej ta funkcja pytała Stripe SAMA — drugi raz w tym samym żądaniu (15 s + 15 s
 * przy awarii, ponad regułę „jedna próba" z includes/stripe.php) i z pominięciem pamięci
 * nietrafień, przez co cudzy zwrot odpytywał Stripe przy każdym powtórzeniu.
 *
 * @return bool true = zamknięte, false = niech Stripe ponowi.
 */
function sklepag_zwrot_nieprzypisany( $typ, $payment_intent, $powod = '', $co = 'zwrot' ) {
	if ( 0 === strpos( (string) $powod, 'awaria:' ) ) {
		sklepag_zdarzenie(
			null,
			$co . '_odlozony',
			$typ . ': nie udało się zapytać Stripe o płatność ' . $payment_intent . ' (' . substr( (string) $powod, 7 ) . ') — poprosimy o ponowienie'
		);
		return false;
	}
	sklepag_zdarzenie( null, $co . '_bez_powiazania', $typ . ': płatność ' . $payment_intent . ' nie należy do żadnego zamówienia tego sklepu' );
	return true;
}

/**
 * Jeden zapis kwoty zwrotu + wpis w dzienniku (wspólny dla wszystkich dróg).
 *
 * Stan sprzed zwrotu zapamiętujemy w `status_przed_sporem` — tej samej kolumnie, której
 * używa ścieżka sporu, bo pytanie jest identyczne: „do czego wrócić, gdy pieniądze wrócą".
 * Bez tego cofnięcie zwrotu wpisywało `mail_wyslany` NA SZTYWNO i zamówienie, do którego
 * mail nigdy nie doszedł, wypadało z licznika czerwonych i z ponowień. Ścieżka sporu
 * rozwiązywała to samo pytanie już wcześniej — tu jest jej bliźniak.
 *
 * Zapisujemy TYLKO gdy kolumna jest pusta: pierwsze zdarzenie finansowe (zwrot albo spór)
 * ustala punkt powrotu, kolejne go nie nadpisują.
 */
function sklepag_zapisz_zwrot( $zam, $kwota, $typ_wpisu, $opis ) {
	global $wpdb;
	$pz    = $wpdb->prefix . 'sklepag_zamowienia';
	$teraz = current_time( 'mysql' );
	$pelny = ( $kwota >= (int) $zam->kwota_grosze );
	$przed = (string) $zam->status_przed_sporem;

	// Status zmieniamy przy zwrocie CAŁOŚCI oraz przy jego COFNIĘCIU (kwota schodzi poniżej
	// całości ze stanu `zwrocone`). Zwykły zwrot częściowy zostawia zamówienie na liście,
	// a jego kwota schodzi z sum przez `zwrocono_grosze`.
	if ( $pelny ) {
		if ( '' === $przed && 'zwrocone' !== $zam->status && 'sporne' !== $zam->status ) {
			$przed = (string) $zam->status; // punkt powrotu, zanim nadpiszemy status
		}
		$status = 'zwrocone';
	} elseif ( 'zwrocone' === $zam->status ) {
		// Zwrot cofnięty albo pomniejszony — wracamy do stanu SPRZED, nie do wymyślonego.
		$status = '' !== $przed ? $przed : 'oplacone';
		if ( 0 === (int) $kwota && 'sporne' !== $status ) {
			$przed = ''; // nic już nie wisi: punkt powrotu przestaje być potrzebny
		}
	} else {
		$status = (string) $zam->status;
	}
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			// status-bez-warunku: $status wyliczony wyżej z `$zam->status` i `$przed`, z jawną
			// gałęzią dla cofnięcia zwrotu — to jest właśnie ochrona stanu poprzedniego.
			"UPDATE {$pz} SET zwrocono_grosze=%d, zwrot_czas=%s, status=%s, status_przed_sporem=%s, zmieniono=%s WHERE id=%d",
			(int) $kwota,
			$kwota > 0 ? $teraz : null,
			$status,
			$przed,
			$teraz,
			(int) $zam->id
		)
	);
	// phpcs:enable
	sklepag_zdarzenie( (int) $zam->id, $typ_wpisu, $opis );
}

/**
 * Obciążenie zwrotne: kupujący reklamuje płatność w swoim banku.
 *
 * Nie każde zdarzenie sporu oznacza, że pieniądze zeszły z konta. Zapytanie z banku
 * (`warning_needs_response`, `warning_under_review`) to prośba o wyjaśnienia — pieniądze
 * zostają. Dopiero pobranie środków albo przegrany spór zdejmuje sprzedaż z przychodu,
 * a zwrot środków i spór wygrany ją przywracają.
 *
 * Stan sprzed sporu zapamiętujemy w `status_przed_sporem`, żeby powrót nie zmyślał historii
 * wysyłki: zamówienie, do którego mail nigdy nie doszedł, ma wrócić do `mail_blad`,
 * a nie zostać oznaczone jako wysłane (bo wypadłoby z licznika czerwonych i z ponowień).
 *
 * @return bool true = zamknięte, false = niech Stripe ponowi (awaria łączności ze Stripe).
 */
function sklepag_zaksieguj_spor( $typ, $obiekt ) {
	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';

	$pi = sklepag_platnosc_ze_zdarzenia( $obiekt );
	if ( '' === $pi ) {
		sklepag_zdarzenie( null, 'spor_bez_powiazania', $typ . ': zdarzenie bez identyfikatora płatności' );
		return true;
	}
	$powod = '';
	$zam   = sklepag_zamowienie_po_platnosci( $pi, $powod );
	if ( ! $zam ) {
		// Bliźniak zachowania ze ścieżki zwrotu: awaria łączności
		// ze Stripe NIE jest werdyktem „to cudza płatność". Oddajemy false, żeby webhook
		// odpowiedział 500 i Stripe ponowił — inaczej jeden timeout zostawiał pieniądze
		// zabrane przez bank w przychodzie na zawsze.
		return sklepag_zwrot_nieprzypisany( $typ, $pi, $powod, 'spor' );
	}

	$wynik = isset( $obiekt['status'] ) ? (string) $obiekt['status'] : '';
	$kwota = isset( $obiekt['amount'] ) ? (int) $obiekt['amount'] : (int) $zam->kwota_grosze;
	$teraz = current_time( 'mysql' );

	$tylko_pytanie     = in_array( $wynik, array( 'warning_needs_response', 'warning_under_review' ), true );
	$pieniadze_zeszly  = ( 'charge.dispute.funds_withdrawn' === $typ )
		|| ( 'charge.dispute.created' === $typ && ! $tylko_pytanie )
		|| ( 'charge.dispute.closed' === $typ && 'lost' === $wynik );
	$pieniadze_wrocily = ( 'charge.dispute.funds_reinstated' === $typ )
		|| ( 'charge.dispute.closed' === $typ && in_array( $wynik, array( 'won', 'warning_closed' ), true ) );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	if ( $pieniadze_wrocily ) {
		if ( 'sporne' !== $zam->status && 0 === (int) $zam->zwrocono_grosze ) {
			sklepag_zdarzenie( (int) $zam->id, 'spor_zamkniety', 'bank zamknął sprawę (' . $typ . ', status ' . $wynik . ') — zamówienie i tak nie było w sporze' );
			return true;
		}
		$powrot = '' !== (string) $zam->status_przed_sporem ? (string) $zam->status_przed_sporem : 'oplacone';
		$reszta = max( 0, (int) $zam->zwrocono_grosze - $kwota ); // prawdziwy zwrot częściowy sprzed sporu zostaje
		$wpdb->query(
			$wpdb->prepare(
				// status-bez-warunku: $powrot wyliczony wyżej z `status_przed_sporem`, czyli
				// z zapamiętanego stanu poprzedniego — nie jest stałą.
				"UPDATE {$pz} SET zwrocono_grosze=%d, zwrot_czas=%s, status=%s, status_przed_sporem='', zmieniono=%s WHERE id=%d",
				$reszta,
				$reszta > 0 ? $zam->zwrot_czas : null,
				$powrot,
				$teraz,
				(int) $zam->id
			)
		);
		sklepag_zdarzenie( (int) $zam->id, 'spor_wygrany', 'pieniądze wracają do przychodu (' . $typ . ', status ' . $wynik . '); zamówienie wraca do stanu „' . $powrot . '"' );
		return true;
	}

	if ( ! $pieniadze_zeszly ) {
		// Zapytanie z banku: odnotowujemy, ale NIE ruszamy ani statusu, ani przychodu.
		sklepag_zdarzenie(
			(int) $zam->id,
			'spor_zapytanie',
			'bank prosi o wyjaśnienia (' . $typ . ', status ' . $wynik . ') — pieniądze na razie zostają; odpowiedz w panelu Stripe'
		);
		return true;
	}

	$przed = '' !== (string) $zam->status_przed_sporem ? (string) $zam->status_przed_sporem : (string) $zam->status;
	$wpdb->query(
		$wpdb->prepare(
			// status-bez-warunku: 'sporne' ustawiamy świadomie, a stan sprzed zapisujemy w tym
			// samym zapytaniu do `status_przed_sporem` (wyliczony wyżej z `$zam->status`).
			"UPDATE {$pz} SET zwrocono_grosze=%d, zwrot_czas=%s, status='sporne', status_przed_sporem=%s, zmieniono=%s WHERE id=%d",
			max( (int) $zam->zwrocono_grosze, $kwota ),
			$teraz,
			'sporne' === $przed ? '' : $przed,
			$teraz,
			(int) $zam->id
		)
	);
	// phpcs:enable
	sklepag_zdarzenie(
		(int) $zam->id,
		'charge.dispute.closed' === $typ ? 'spor_przegrany' : 'spor_otwarty',
		'obciążenie zwrotne (' . $typ . ( '' !== $wynik ? ', status ' . $wynik : '' ) . '): ' .
		number_format_i18n( $kwota / 100, 2 ) . ' ' . $zam->waluta . ' poza przychodem'
	);
	return true;
}

/* ---------- SILNIK DOMYKANIA — jeden dla webhooka i reconciliation ---------- */

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
		  pobrania_limit, dane_faktury, utworzono, zmieniono)
		 VALUES (%s, %d, %s, %d, %s, %s, %d, %s, 'oczekuje', %s, %s, %d, %s, %s, %s)",
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
			sklepag_dane_faktury_z_sesji( $sesja ),
			$teraz,
			$teraz
		)
	);

	// 2) CAS: oczekuje -> oplacone.
	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='oplacone', zmieniono=%s WHERE stripe_session_id=%s AND status='oczekuje'", $teraz, $sid ) );

	// DOWÓD SKUTKU PRZED FLAGĄ STANU. Sesję wolno oznaczyć jako obsłużoną DOPIERO gdy
	// zamówienie naprawdę jest w bazie. Wcześniej flaga szła bezwarunkowo: awaria zapisu
	// (tabela niedostępna, pełny dysk, przekroczony limit) dawała sesję 'oplacona' BEZ
	// zamówienia, a sklepag_dociagnij_zalegle() bierze tylko stan='utworzona' — opłacony
	// zakup ginął bez śladu i bez sygnału dla właściciela.
	$zamowienie_jest = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$pz} WHERE stripe_session_id=%s", $sid ) );
	if ( ! $zamowienie_jest ) {
		$powod = '' !== (string) $wpdb->last_error ? (string) $wpdb->last_error : 'brak wiersza po INSERT/UPDATE';
		sklepag_zdarzenie( null, 'domkniecie_blad_zapisu', $sid . ': ' . $powod );
		// Sesja zostaje 'utworzona' → dociągnie ją reconciliation, a webhook Stripe ponowi.
		return false;
	}
	// Identyfikator płatności zapisujemy JUŻ TERAZ, bo zdarzenia o zwrocie i obciążeniu
	// zwrotnym przychodzą ze Stripe wyłącznie na poziomie płatności (refund.created,
	// charge.dispute.*) i NIE niosą identyfikatora sesji checkout. Bez tej kolumny każdy
	// zwrot wymagałby dodatkowego pytania do API tylko po to, żeby ustalić, czyj jest.
	$pi = '';
	if ( isset( $sesja['payment_intent'] ) ) {
		$pi = is_array( $sesja['payment_intent'] )
			? (string) ( $sesja['payment_intent']['id'] ?? '' )   // pole przyszło rozwinięte
			: (string) $sesja['payment_intent'];
	}
	if ( '' !== $pi ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET payment_intent_id=%s WHERE stripe_session_id=%s AND payment_intent_id=''", $pi, $sid ) );
	}

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
			// ŚWIADOMY KOMPROMIS: pad tuż PO wp_mail a PRZED zapisem
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
			return false; // mail_blad z wyczerpanymi próbami → panel-czerwone, retry nic nie wskóra... ale zwracamy false, żeby ślad w retry był.
		}
	}

	// 4) Token pobrania (surowy do maila, skrót do bazy) + termin — dopiero PO przejęciu.
	// Świeży link = licznik pobrań od zera (ta sama zasada co przycisk ponownej wysyłki w panelu):
	// ponowienie po nieudanym resendzie kupującego z wyczerpanym limitem inaczej wydawało
	// link, który od pierwszego kliknięcia dawał 410. Przy pierwszym domknięciu zakupu licznik i tak jest 0.
	$token = bin2hex( random_bytes( 32 ) );
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=0, zmieniono=%s WHERE stripe_session_id=%s AND status='mail_w_toku'",
			hash( 'sha256', $token ),
			gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_LINK_GODZIN * HOUR_IN_SECONDS ),
			$teraz,
			$sid
		)
	);

	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE stripe_session_id=%s", $sid ) );

	// 4b) Zalążek opinii + jednorazowy link „oceń bota" — w tej samej sekcji
	// chronionej CAS-em mail_w_toku co token pobrania: retry maila odświeża token
	// opinii, ale opinii już przysłanej nie rusza (wtedy mail idzie bez akapitu oceny).
	$token_op = sklepag_opinia_zalazek( (int) $zam->id, (int) $produkt->id );
	$link_op  = '' !== $token_op ? admin_url( 'admin-post.php' ) . '?action=sklepag_ocen&z=' . (int) $zam->id . '&t=' . $token_op : '';

	// 5) Mail: zapis treści PRZED pomiarem skutku (podgląd w panelu niezależnie od wyniku).
	$link  = admin_url( 'admin-post.php' ) . '?action=sklepag_pobierz&z=' . (int) $zam->id . '&t=' . $token;
	$tresc = sklepag_zbuduj_mail( $produkt, $zam, $link, $link_op );
	$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET mail_tresc=%s, zmieniono=%s WHERE id=%d", $tresc, $teraz, (int) $zam->id ) );

	// Adres z zamówienia, gdy wywołanie nie niosło danych kupującego (domknięcie z danych
	// własnych w reconciliation). Bez tego mail nie miałby dokąd pójść mimo kompletu danych w bazie.
	if ( '' === $email && isset( $zam->email_kupujacego ) ) {
		$email = (string) $zam->email_kupujacego;
	}

	$wyslano = false;
	if ( '' !== $email && is_email( $email ) ) {
		$wyslano = sklepag_wyslij_mail_sklepu(
			$email,
			'Twój zakup: ' . $produkt->nazwa . ' — potwierdzenie i pobranie',
			$tresc,
			sklepag_zbuduj_mail_html( $produkt, $zam, $link, $link_op )
		);
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
 * NASZEJ wysyłki i zdejmowane od razu (granica: maile reszty
 * strony pozostają nietknięte).
 *
 * Z wersją HTML mail idzie jako multipart/alternative: HTML w treści, tekst jako AltBody
 * PHPMailera (klient bez HTML albo z wyłączonymi obrazkami czyta ten sam tekst, który panel
 * pokazuje w podglądzie). Typ treści ustawiany NAGŁÓWKIEM tej jednej wysyłki, nie filtrem
 * `wp_mail_content_type` — filtr zostawiony na żądanie przestawiłby na HTML także reset hasła
 * i resztę poczty WordPressa.
 *
 * @param string $adresat    Adresat.
 * @param string $temat      Temat.
 * @param string $tresc      Treść tekstowa (zawsze; bez HTML = cały mail).
 * @param string $tresc_html Treść HTML (puste = mail tekstowy, jak do 0.9.23).
 * @return bool Wynik wp_mail.
 */
function sklepag_wyslij_mail_sklepu( $adresat, $temat, $tresc, $tresc_html = '' ) {
	// Reply-To = „mail kontaktowy właściciela" z ustawień (gdy ustawiony): mail mówi „odpisz na tego
	// maila", a odpowiedź szła na adres NADAWCY — przy nadawcy typu noreply ginęła.
	$naglowki = array();
	$kontakt  = (string) get_option( 'sklepag_email_kontaktowy', '' );
	if ( '' !== $kontakt && is_email( $kontakt ) ) {
		$naglowki[] = 'Reply-To: ' . $kontakt;
	}
	$html = '' !== $tresc_html;
	if ( $html ) {
		$naglowki[]                        = 'Content-Type: text/html; charset=UTF-8';
		$GLOBALS['sklepag_mail_alt_tekst'] = $tresc;
	}
	add_filter( 'wp_mail_from', 'sklepag_filtr_nadawca_email' );
	add_filter( 'wp_mail_from_name', 'sklepag_filtr_nadawca_nazwa' );
	if ( $html ) {
		add_action( 'phpmailer_init', 'sklepag_phpmailer_wersja_tekstowa' );
	}
	try {
		return wp_mail( $adresat, $temat, $html ? $tresc_html : $tresc, $naglowki );
	} finally {
		// Filtry schodzą też przy rzucie z wp_mail — nie mogą wisieć na resztę żądania.
		remove_filter( 'wp_mail_from', 'sklepag_filtr_nadawca_email' );
		remove_filter( 'wp_mail_from_name', 'sklepag_filtr_nadawca_nazwa' );
		remove_action( 'phpmailer_init', 'sklepag_phpmailer_wersja_tekstowa' );
		unset( $GLOBALS['sklepag_mail_alt_tekst'] );
	}
}

/**
 * Wersja tekstowa (AltBody) dla wysyłki HTML — PHPMailer z niepustym AltBody składa
 * multipart/alternative sam. Hak zakładany i zdejmowany wyłącznie wokół naszej wysyłki.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Instancja z wp_mail.
 */
function sklepag_phpmailer_wersja_tekstowa( $phpmailer ) {
	if ( ! empty( $GLOBALS['sklepag_mail_alt_tekst'] ) ) {
		$phpmailer->AltBody = (string) $GLOBALS['sklepag_mail_alt_tekst']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- własność PHPMailera.
	}
}

/**
 * Dane sprzedawcy do stopki maila (Ustawienia → Dane sprzedawcy). Nazwa ma zapas: nadawca
 * maili, potem nazwa strony — stopka nigdy nie jest pusta, ale kompletna jest tylko
 * z wypełnionymi polami (Pulpit ostrzega, gdy ich brak).
 *
 * @return array{nazwa:string,adres:string,nip:string,kompletne:bool}
 */
function sklepag_sprzedawca() {
	$nazwa = trim( (string) get_option( 'sklepag_firma_nazwa', '' ) );
	$adres = trim( (string) get_option( 'sklepag_firma_adres', '' ) );
	$nip   = trim( (string) get_option( 'sklepag_firma_nip', '' ) );
	if ( '' === $nazwa ) {
		$nazwa = trim( (string) get_option( 'sklepag_nadawca_nazwa', '' ) );
	}
	if ( '' === $nazwa ) {
		$nazwa = wp_specialchars_decode( (string) get_option( 'blogname', '' ), ENT_QUOTES );
	}
	return array(
		'nazwa'     => $nazwa,
		'adres'     => $adres,
		'nip'       => $nip,
		'kompletne' => '' !== trim( (string) get_option( 'sklepag_firma_nazwa', '' ) ) && '' !== $adres,
	);
}

/**
 * Stopka sprzedawcy w wersji tekstowej maila.
 */
function sklepag_stopka_sprzedawcy_tekst() {
	$s     = sklepag_sprzedawca();
	$linie = array( $s['nazwa'] );
	if ( '' !== $s['adres'] ) {
		$linie[] = str_replace( array( "\r\n", "\r" ), "\n", $s['adres'] );
	}
	if ( '' !== $s['nip'] ) {
		$linie[] = 'NIP ' . $s['nip'];
	}
	$kontakt = (string) get_option( 'sklepag_email_kontaktowy', '' );
	if ( '' !== $kontakt && is_email( $kontakt ) ) {
		$linie[] = 'Reklamacje i faktury: ' . $kontakt;
	}
	return "SPRZEDAWCA:\n" . implode( "\n", $linie ) . "\n";
}

function sklepag_zbuduj_mail( $produkt, $zam, $link, $link_opinii = '' ) {
	$dni   = (int) round( SKLEPAG_LINK_GODZIN / 24 );
	$ocena = '' !== $link_opinii
		? "OCEŃ SWÓJ ZAKUP:\nGdy przetestujesz bota, zostaw opinię — to 2 minuty:\n" . $link_opinii . "\nLink do opinii jest jednorazowy i ważny " . (int) SKLEPAG_OPINIA_DNI . " dni. Opinię publikujemy po potwierdzeniu zakupu i akceptacji.\n\n"
		: '';
	// Potwierdzenie zgody na trwałym nośniku (dyrektywa 2011/83/UE art. 8 ust. 7 lit. b — potwierdzenie
	// umowy ma zawierać potwierdzenie uprzedniej wyraźnej zgody i przyjęcia do wiadomości z art. 16 lit. m).
	// Treść i chwila zgody = dokładnie to, co zapisano w zamówieniu przy zakupie.
	$zgoda = '';
	if ( ! empty( $zam->zgoda_tresc ) ) {
		$kiedy  = ! empty( $zam->zgoda_czas ) ? get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) : '';
		$zgoda  = "POTWIERDZENIE TWOJEJ ZGODY:\n" . $zam->zgoda_tresc . "\n";
		$zgoda .= '' !== $kiedy ? 'Zgoda zaznaczona przy zakupie ' . $kiedy . ".\n\n" : "\n";
	}
	// Gwarancja z danych produktu (ta sama, którą obiecuje strona produktu; puste = bez akapitu).
	$gwarancja = '';
	if ( ! empty( $produkt->slug ) && function_exists( 'sklepag_tresc_produktu' ) ) {
		$t = sklepag_tresc_produktu( $produkt->slug );
		if ( $t && ! empty( $t['gwarancja'] ) ) {
			$gwarancja = "GWARANCJA:\n" . $t['gwarancja'] . "\nAby z niej skorzystać, odpisz na tego maila.\n\n";
		}
	}
	// Data zakupu OBOK potwierdzenia zgody. Mail podawał wyłącznie chwilę zgody, przez co
	// kupujący nie miał w potwierdzeniu odpowiedzi na proste „kiedy to kupiłem" — a to pierwsza
	// rzecz, której szuka po miesiącu. Czas zgody zostaje osobno, bo niesie inną treść prawną.
	$kupiono = ! empty( $zam->utworzono ) ? get_date_from_gmt( get_gmt_from_date( $zam->utworzono ), 'd.m.Y H:i' ) : '';
	// Numer zamówienia = id wiersza, ten sam, który panel pokazuje w „Zamówienie #N": kupujący
	// piszący do wsparcia ma czym się przedstawić (dotąd id siedziało tylko w linku pobrania).
	$numer = ! empty( $zam->id ) ? '#' . (int) $zam->id : '';

	return "Dziękujemy za zakup w sklepie!\n\n"
		. ( '' !== $numer ? 'Numer zamówienia: ' . $numer . "\n" : '' )
		. 'Produkt: ' . $produkt->nazwa . "\n"
		. ( '' !== $kupiono ? 'Data zakupu: ' . $kupiono . "\n" : '' )
		. 'Kwota: ' . number_format( $zam->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $zam->waluta . " (TRYB TESTOWY — płatność nie została pobrana)\n\n"
		. $zgoda
		. "POBIERZ SWÓJ PRODUKT:\n" . $link . "\n"
		. 'Link jest ważny ' . $dni . ' dni i pozwala na ' . SKLEPAG_LIMIT_POBRAN . " pobrań.\n\n"
		. "INSTRUKCJA W SKRÓCIE:\n1. Pobierz paczkę ZIP z linku powyżej.\n2. W kokpicie WordPressa: Wtyczki → Dodaj → Wyślij wtyczkę na serwer → wybierz ZIP.\n3. Aktywuj i działaj — pełna instrukcja jest w paczce.\n\n"
		. $gwarancja
		. $ocena
		. ( ! empty( $zam->dane_faktury )
			? "DANE DO FAKTURY (podane przy zakupie):\n" . $zam->dane_faktury . "\nFakturę wystawimy na te dane i prześlemy osobno na ten adres e-mail.\n\n"
			: "FAKTURA:\nChcesz fakturę? Odpisz na tego maila w ciągu 3 miesięcy od zakupu i podaj dane firmy (nazwa, adres, NIP).\n\n" )
		. "Masz pytanie? Odpisz na tego maila.\n\n"
		. sklepag_stopka_sprzedawcy_tekst();
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

	// Wynik dla wołającego (przycisk w panelu pokazuje go właścicielowi). K10 (30.08): tester kliknął
	// „Dociągnij zaległe” 71 s po zapłacie, sesja była młodsza niż 120 s, przycisk pominął ją BEZ SŁOWA,
	// cron na tym hostingu nie chodził (zepsuta pętla zwrotna) — i zakup „wisiał” 20 minut mimo opłacenia.
	$wynik = array(
		'domkniete'          => 0,
		'pominiete_mlode'    => 0,
		'sprawdzone_otwarte' => 0,
		'bledy'              => 0,
	);

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$granica                  = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 120 );
	$wynik['pominiete_mlode'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ps} WHERE stan='utworzona' AND utworzono >= %s", $granica ) );
	// NAJNOWSZE NAJPIERW + LIMIT 100 + przerwa po sprawdzeniu: porzucone koszyki (kliknął „Kup”, nie zapłacił)
	// zostają „utworzone” do wygaśnięcia u Stripe (24 h). Przy ORDER BY utworzono (najstarsze) LIMIT 20
	// dwadzieścia takich sesji blokowało nowszą OPŁACONĄ na dobę — a to właśnie „przed chwilą zapłacił,
	// nic nie przyszło” jest przypadkiem, na który właściciel czeka z palcem na przycisku. Sesja sprawdzona
	// i nadal otwarta dostaje 9 min przerwy (transient), więc kolejne biegi schodzą do starszych.
	$sesje = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT stripe_session_id FROM {$ps} WHERE stan='utworzona' AND utworzono < %s ORDER BY utworzono DESC LIMIT 100",
			$granica
		)
	);
	// phpcs:enable

	$sprawdzone = 0;
	foreach ( $sesje as $sid ) {
		if ( $po_budzecie() || $sprawdzone >= 20 ) {
			break; // Resztę dociągnie cron — render admina nie może wisieć.
		}
		$klucz_przerwy = 'sklepag_recon_' . md5( $sid );
		if ( get_transient( $klucz_przerwy ) ) {
			continue; // Sprawdzona przed chwilą i nadal nieopłacona — nie pytamy Stripe co bieg.
		}
		++$sprawdzone;
		$sesja = sklepag_stripe_pobierz_sesje( $sid );
		if ( is_wp_error( $sesja ) ) {
			++$wynik['bledy'];
			sklepag_zdarzenie( null, 'reconciliation_blad', $sid . ': ' . $sesja->get_error_message() );
			continue;
		}
		if ( isset( $sesja['payment_status'] ) && 'unpaid' !== $sesja['payment_status'] ) {
			if ( sklepag_domknij_zakup( $sesja ) ) { // TEN SAM silnik co webhook.
				++$wynik['domkniete'];
			}
		} elseif ( isset( $sesja['status'] ) && 'expired' === $sesja['status'] ) {
			sklepag_oznacz_sesje( $sid, 'wygasla' );
		} else {
			++$wynik['sprawdzone_otwarte'];
			set_transient( $klucz_przerwy, 1, 9 * MINUTE_IN_SECONDS );
		}
	}

	// 2) ZAMÓWIENIA niedomknięte: sesja dostaje stan
	// 'oplacona' zaraz po CAS-ie płatności — ZANIM mail wyjdzie — więc pętla wyżej
	// nigdy nie widzi zamówień uwięzionych w mail_blad (pad wysyłki) ani mail_w_toku
	// (pad procesu). Podnosimy je tu TYM SAMYM silnikiem: CAS + stale-claim same
	// rozstrzygają, mail_proby<3 trzyma budżet trzech prób crona, potem zamówienie świeci na czerwono w panelu.
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
		// Te zamówienia są JUŻ opłacone w naszej bazie — przeszedł na nich atomowy zapis
		// płatności. Brakuje im wyłącznie maila. Pytanie do Stripe o `payment_status` było
		// tu nadmiarowe, a przy braku odpowiedzi (błąd sieci, timeout, odrzucony klucz)
		// zamykało JEDYNĄ drogę wysyłki: opłacony i zapisany zakup stał w `mail_w_toku`,
		// dopóki Stripe nie odpowie, i nie trafiał nawet do licznika czerwonych na Pulpicie.
		// Domykamy z danych własnych — płatność jest już potwierdzona, nie ma czego weryfikować.
		sklepag_domknij_zakup(
			array(
				'id'             => $sid,
				'payment_status' => 'paid',
			)
		);
	}

	// 3) Sprzątanie liczników rate-limit po oknie (tabela techniczna, wiersz żyje ~2 min).
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_rate_limit WHERE okno_do < UTC_TIMESTAMP()" );

	// 4) Retencja 90 dni (polityka deklaruje „dziennik zdarzeń (90 dni)"):
	// logowania i zdarzenia starsze wypadają. Historia SPRZEDAŻY żyje w `zamowienia`
	// (status, kwoty, mail_tresc) — jej retencja to osobna decyzja właściciela, nie ten krok.
	$retencja = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 90 * DAY_IN_SECONDS );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_logowania WHERE czas < %s", $retencja ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE czas < %s", $retencja ) );
	// phpcs:enable

	return $wynik;
}
add_action( 'sklepag_dociagnij_zalegle', 'sklepag_dociagnij_zalegle' );
