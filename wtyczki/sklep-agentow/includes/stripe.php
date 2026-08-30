<?php
/**
 * Klient Stripe przez WP HTTP API — bez SDK (świadomie, żeby paczka nie ciągnęła zewnętrznych zależności).
 * Zasady: timeout 15 s, JEDNA próba (ponowienia robią Stripe/cron — ściąga:
 * pętla retry nie może przekroczyć limitu PHP hostingu); wersja API przypięta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SKLEPAG_STRIPE_API    = 'https://api.stripe.com';
const SKLEPAG_STRIPE_WERSJA = '2026-07-29.dahlia';

function sklepag_stripe_klucz() {
	return (string) get_option( 'sklepag_stripe_klucz_sekretny', '' );
}

/**
 * Jedno żądanie do Stripe. $dane != null => POST form-encoded.
 * Zwraca array (JSON) albo WP_Error.
 */
function sklepag_stripe_request( $sciezka, $dane = null, $klucz_idempotencji = '' ) {
	$klucz = sklepag_stripe_klucz();
	if ( '' === $klucz ) {
		return new WP_Error( 'sklepag_brak_klucza', 'Brak klucza Stripe w ustawieniach.' );
	}

	$naglowki = array(
		'Authorization'  => 'Bearer ' . $klucz,
		'Stripe-Version' => SKLEPAG_STRIPE_WERSJA,
	);
	if ( '' !== $klucz_idempotencji ) {
		$naglowki['Idempotency-Key'] = $klucz_idempotencji;
	}

	$args = array(
		'timeout' => 15,
		'headers' => $naglowki,
	);

	if ( null !== $dane ) {
		$args['method']                  = 'POST';
		$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
		$args['body']                    = http_build_query( $dane );
		$odp                             = wp_remote_post( SKLEPAG_STRIPE_API . $sciezka, $args );
	} else {
		$odp = wp_remote_get( SKLEPAG_STRIPE_API . $sciezka, $args );
	}

	if ( is_wp_error( $odp ) ) {
		return $odp;
	}

	$kod  = (int) wp_remote_retrieve_response_code( $odp );
	$dane = json_decode( wp_remote_retrieve_body( $odp ), true );

	if ( $kod >= 400 || ! is_array( $dane ) ) {
		$opis = isset( $dane['error']['message'] ) ? $dane['error']['message'] : ( 'HTTP ' . $kod );
		return new WP_Error( 'sklepag_stripe_' . $kod, $opis );
	}

	return $dane;
}

/**
 * Checkout Session dla produktu. BEZ payment_method_types (dynamiczne metody —
 * best practice Stripe; BLIK/karty rządzone Dashboardem). Cena WYŁĄCZNIE z bazy.
 */
/**
 * @param object $produkt           Produkt z bazy.
 * @param string $zgoda_czas        Chwila zgody (GMT).
 * @param string $klucz_idempotencji Klucz idempotencji Stripe.
 * @param bool   $osadzona          true = sesja `ui_mode=elements` (formularz płatności NA NASZEJ stronie,
 *                                  0.9.25): zamiast `success_url`/`cancel_url` — `return_url` (Stripe ich
 *                                  w tym trybie nie przyjmuje), Stripe zwraca `client_secret` zamiast `url`.
 *                                  false = dotychczasowa strona hostowana (zapas bez JS / bez klucza publikowalnego).
 * @param array  $faktura           Dane do faktury podane przez kupującego (nazwa, nip) — jadą w metadanych
 *                                  sesji, żeby webhook i reconciliation dostały je tą samą drogą co zgodę.
 */
function sklepag_stripe_utworz_sesje( $produkt, $zgoda_czas, $klucz_idempotencji, $osadzona = false, $faktura = array() ) {
	$dziekujemy = get_permalink( (int) get_option( 'sklepag_strona_dziekujemy' ) );
	$sklep      = get_permalink( (int) get_option( 'sklepag_strona_sklep' ) );

	$dane = array(
		'mode'                                          => 'payment',
		'line_items[0][price_data][currency]'           => strtolower( $produkt->waluta ),
		'line_items[0][price_data][unit_amount]'        => (int) $produkt->cena_grosze,
		'line_items[0][price_data][product_data][name]' => $produkt->nazwa,
		'line_items[0][price_data][product_data][description]' => wp_trim_words( $produkt->opis_krotki, 20 ),
		'line_items[0][quantity]'                       => 1,
		'metadata[produkt_id]'                          => (int) $produkt->id,
		'metadata[zgoda_czas]'                          => $zgoda_czas,
	);
	if ( $osadzona ) {
		$dane['ui_mode']    = 'elements';
		$dane['return_url'] = add_query_arg( 'sesja', '{CHECKOUT_SESSION_ID}', $dziekujemy );
	} else {
		$dane['success_url'] = add_query_arg( 'sesja', '{CHECKOUT_SESSION_ID}', $dziekujemy );
		$dane['cancel_url']  = $sklep;
	}
	if ( ! empty( $faktura['nazwa'] ) ) {
		$dane['metadata[faktura_nazwa]'] = mb_substr( (string) $faktura['nazwa'], 0, 200 );
	}
	if ( ! empty( $faktura['nip'] ) ) {
		$dane['metadata[faktura_nip]'] = (string) $faktura['nip'];
	}
	if ( ! empty( $faktura['adres'] ) ) {
		$dane['metadata[faktura_adres]'] = mb_substr( (string) $faktura['adres'], 0, 200 );
	}

	return sklepag_stripe_request( '/v1/checkout/sessions', $dane, $klucz_idempotencji );
}

function sklepag_stripe_pobierz_sesje( $sesja_id ) {
	return sklepag_stripe_request( '/v1/checkout/sessions/' . rawurlencode( $sesja_id ) );
}

/**
 * Sesja checkout po identyfikatorze płatności.
 *
 * Zdarzenia o zwrocie i obciążeniu zwrotnym niosą wyłącznie `payment_intent` — ani obiekt
 * zwrotu, ani obciążenia nie zawiera identyfikatora sesji. Dla zamówień zapisanych PRZED
 * dodaniem kolumny `payment_intent_id` to jedyna droga, żeby ustalić, czyj jest zwrot.
 *
 * @return array|WP_Error Sesja checkout albo błąd (także gdy Stripe nie zna takiej płatności).
 */
function sklepag_stripe_sesja_po_platnosci( $payment_intent ) {
	$odp = sklepag_stripe_request( '/v1/checkout/sessions?limit=1&payment_intent=' . rawurlencode( $payment_intent ) );
	if ( is_wp_error( $odp ) ) {
		return $odp;
	}
	if ( empty( $odp['data'][0]['id'] ) ) {
		return new WP_Error( 'sklepag_brak_sesji', 'Stripe nie zna sesji dla płatności ' . $payment_intent );
	}
	return $odp['data'][0];
}

/**
 * Ręczna weryfikacja podpisu webhooka (dokumentacja Stripe: „Verify manually”):
 * nagłówek `t=...,v1=...[,v1=...]`, signed_payload = t + "." + SUROWE body,
 * HMAC-SHA256, porównanie stałoczasowe, tolerancja czasu 5 min (nigdy 0).
 */
function sklepag_stripe_weryfikuj_podpis( $surowe_body, $naglowek, $sekret, $tolerancja = 300 ) {
	if ( '' === $sekret || '' === $naglowek ) {
		return false;
	}

	$t   = '';
	$v1s = array();
	foreach ( explode( ',', $naglowek ) as $czesc ) {
		$para = explode( '=', trim( $czesc ), 2 );
		if ( 2 !== count( $para ) ) {
			continue;
		}
		if ( 't' === $para[0] ) {
			$t = $para[1];
		} elseif ( 'v1' === $para[0] ) {
			$v1s[] = $para[1];
		}
	}

	if ( '' === $t || empty( $v1s ) || abs( time() - (int) $t ) > $tolerancja ) {
		return false;
	}

	$oczekiwany = hash_hmac( 'sha256', $t . '.' . $surowe_body, $sekret );
	foreach ( $v1s as $v1 ) {
		if ( hash_equals( $oczekiwany, $v1 ) ) {
			return true;
		}
	}
	return false;
}
