<?php
/**
 * Mechanizm opinii: zalążek przy mailu zakupowym → formularz z jednorazowego
 * linku → moderacja w panelu → render na stronie produktu.
 *
 * Zasady:
 * - Token jak w bramie pobrań: surowy WYŁĄCZNIE w mailu, w bazie skrót
 *   sha256, porównanie stałoczasowe; ważność SKLEPAG_OPINIA_DNI dni.
 * - Jedna opinia na zamówienie (UNIQUE zamowienie_id) — weryfikacja zakupu
 *   z danych zamówień sklepu, bez kont kupujących (granica §9).
 * - Publikacja WYŁĄCZNIE po ręcznej akceptacji właściciela (status 'przyjeta') —
 *   nota pod sekcją opinii mówi prawdę z konstrukcji, nie z obietnicy.
 *
 * Statusy: 'zalazek' (mail wysłany, token żywy) → 'oczekuje' (przysłane,
 * token spalony) → 'przyjeta' / 'odrzucona' (moderacja w panelu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zalążek opinii dla zamówienia + świeży token do maila.
 *
 * INSERT IGNORE tworzy wiersz przy pierwszym mailu; przy PONOWNEJ wysyłce
 * (retry maila) UPDATE z CAS-em na statusie odświeża token — nowy mail zawsze
 * niesie działający link, ale opinii już przysłanej nie wolno ruszyć.
 *
 * @param int $zamowienie_id Id zamówienia.
 * @param int $produkt_id    Id produktu.
 * @return string Surowy token do maila albo '' gdy opinia już przysłana.
 */
function sklepag_opinia_zalazek( $zamowienie_id, $produkt_id ) {
	global $wpdb;
	$t      = $wpdb->prefix . 'sklepag_opinie';
	$token  = bin2hex( random_bytes( 32 ) );
	$skrot  = hash( 'sha256', $token );
	$teraz  = current_time( 'mysql' );
	$wygasa = gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_OPINIA_DNI * DAY_IN_SECONDS );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- własna tabela, prefiks WP; stan musi być świeży (webhook/retry).
	$wstawione = $wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$t} (zamowienie_id, produkt_id, status, token_hash, token_wygasa, utworzono, zmieniono)
			 VALUES (%d, %d, 'zalazek', %s, %s, %s, %s)",
			$zamowienie_id,
			$produkt_id,
			$skrot,
			$wygasa,
			$teraz,
			$teraz
		)
	);
	if ( ! $wstawione ) {
		$odswiezone = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET token_hash=%s, token_wygasa=%s, zmieniono=%s WHERE zamowienie_id=%d AND status='zalazek'",
				$skrot,
				$wygasa,
				$teraz,
				$zamowienie_id
			)
		);
		if ( ! $odswiezone ) {
			return ''; // Opinia już przysłana (oczekuje/przyjęta/odrzucona) — link nie należy się.
		}
	}
	// phpcs:enable
	return $token;
}

/**
 * Wiersz opinii dla ważnego tokenu albo null.
 *
 * @param int    $zamowienie_id Id zamówienia z linku.
 * @param string $token         Surowy token z linku.
 * @return object|null Wiersz opinii (status 'zalazek', token ważny) albo null.
 */
function sklepag_opinia_po_tokenie( $zamowienie_id, $token ) {
	global $wpdb;
	$t = $wpdb->prefix . 'sklepag_opinie';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- jw.
	$op = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE zamowienie_id = %d", $zamowienie_id ) );

	if ( ! $op || 'zalazek' !== $op->status || '' === (string) $op->token_hash || 64 !== strlen( $token )
		|| ! hash_equals( $op->token_hash, hash( 'sha256', $token ) ) ) {
		return null;
	}
	if ( ! empty( $op->token_wygasa ) && strtotime( $op->token_wygasa ) < time() ) {
		return null;
	}
	return $op;
}

/**
 * Zapis treści opinii z formularza (status zalazek → oczekuje, token spalony).
 *
 * @param int    $zamowienie_id Id zamówienia.
 * @param string $token         Surowy token.
 * @param array  $dane          Pola formularza (imie, gwiazdki, tytul, tresc, zgoda).
 * @return string '' gdy zapisane; inaczej powód: 'token' | 'pola' | 'zgoda' | 'wyscig'.
 */
function sklepag_opinia_zapisz( $zamowienie_id, $token, $dane ) {
	global $wpdb;
	$op = sklepag_opinia_po_tokenie( $zamowienie_id, $token );
	if ( ! $op ) {
		return 'token';
	}

	$imie  = sanitize_text_field( isset( $dane['imie'] ) ? $dane['imie'] : '' );
	$gw    = isset( $dane['gwiazdki'] ) ? (int) $dane['gwiazdki'] : 0;
	$tytul = sanitize_text_field( isset( $dane['tytul'] ) ? $dane['tytul'] : '' );
	$tresc = sanitize_textarea_field( isset( $dane['tresc'] ) ? $dane['tresc'] : '' );

	if ( '' === $imie || '' === $tresc || $gw < 1 || $gw > 5 ) {
		return 'pola';
	}
	if ( empty( $dane['zgoda'] ) ) {
		return 'zgoda';
	}

	// Twarde sufity długości — mb_substr ma polyfill rdzenia WP (compat.php).
	$imie  = mb_substr( $imie, 0, 100 );
	$tytul = mb_substr( $tytul, 0, 150 );
	$tresc = mb_substr( $tresc, 0, 2000 );

	$t     = $wpdb->prefix . 'sklepag_opinie';
	$teraz = current_time( 'mysql' );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- jw.; CAS na statusie wyklucza podwójny zapis.
	$zapisane = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$t} SET imie=%s, gwiazdki=%d, tytul=%s, tresc=%s, zgoda_tresc=%s, zgoda_czas=%s, status='oczekuje', token_hash='', zmieniono=%s
			 WHERE zamowienie_id=%d AND status='zalazek'",
			$imie,
			$gw,
			$tytul,
			$tresc,
			SKLEPAG_OPINIA_ZGODA_TRESC,
			$teraz,
			$teraz,
			$zamowienie_id
		)
	);
	// phpcs:enable
	if ( ! $zapisane ) {
		return 'wyscig';
	}
	if ( function_exists( 'sklepag_zdarzenie' ) ) {
		sklepag_zdarzenie( $zamowienie_id, 'opinia_wyslana', 'gwiazdki: ' . $gw );
	}
	return '';
}

/**
 * Moderacja: przyjmij/odrzuć (CAS na statusie; pomyłkę wolno naprawić w obie strony).
 *
 * ⛔ Wyjątek RODO: opinia zamówienia ZANONIMIZOWANEGO (usunieto_dnia w zamówieniu)
 * nie podlega moderacji WCALE — bez tej odmowy jeden klik „Przyjmij" wracałby
 * treść zapomnianego klienta na stronę (jako „Klient" z plakietką). Warunek stoi
 * na zamówieniu, nie na statusie opinii, więc kryje też stan 'oczekuje'.
 *
 * @param int    $opinia_id Id opinii.
 * @param string $akcja     'przyjmij' albo 'odrzuc'.
 * @return bool Czy stan się zmienił.
 */
function sklepag_opinia_moderuj( $opinia_id, $akcja ) {
	global $wpdb;
	$t     = $wpdb->prefix . 'sklepag_opinie';
	$pz    = $wpdb->prefix . 'sklepag_zamowienia';
	$teraz = current_time( 'mysql' );

	if ( 'przyjmij' === $akcja ) {
		$nowy = 'przyjeta';
		$z    = "('oczekuje','odrzucona')";
	} elseif ( 'odrzuc' === $akcja ) {
		$nowy = 'odrzucona';
		$z    = "('oczekuje','przyjeta')";
	} else {
		return false;
	}
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- jw.; $z to stała lista statusów z tej funkcji, nie wejście.
	// Pytamy o CAŁY wiersz, nie o jedną kolumnę. get_var() zwracał NULL w DWÓCH różnych
	// sytuacjach: „zamówienie jest i nie jest zanonimizowane" ORAZ „zamówienia w ogóle nie ma"
	// (JOIN bez trafienia) — nie do odróżnienia. Skutkiem drugiej opinia bez zamówienia
	// przechodziła moderację i lądowała na stronie produktu jako opinia zweryfikowanego kupującego.
	$powiazanie = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT z.id AS zamowienie_id, z.usunieto_dnia FROM {$t} o JOIN {$pz} z ON z.id = o.zamowienie_id WHERE o.id = %d",
			$opinia_id
		)
	);
	if ( ! $powiazanie ) {
		if ( function_exists( 'sklepag_zdarzenie' ) ) {
			sklepag_zdarzenie( null, 'opinia_bez_zamowienia', 'moderacja wstrzymana: opinia ' . (int) $opinia_id . ' nie ma powiązanego zamówienia' );
		}
		return false;
	}
	if ( $powiazanie->usunieto_dnia ) {
		return false;
	}
	$ok = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status=%s, zmieniono=%s WHERE id=%d AND status IN {$z}", $nowy, $teraz, $opinia_id ) );
	// phpcs:enable
	if ( $ok && function_exists( 'sklepag_zdarzenie' ) ) {
		$op = $wpdb->get_row( $wpdb->prepare( "SELECT zamowienie_id FROM {$t} WHERE id=%d", $opinia_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- jw.
		sklepag_zdarzenie( $op ? (int) $op->zamowienie_id : null, 'opinia_' . $nowy, 'id opinii: ' . (int) $opinia_id );
	}
	return (bool) $ok;
}

/**
 * Anonimizacja RODO dla opinii zamówienia — symetria z „anonimizuj kupującego":
 * imię znika, token opinii umiera (jak token pobrania: koniec relacji), a opinia
 * opublikowana lub czekająca SCHODZI ze strony (status 'odrzucona') — publikacja
 * stała na zgodzie i relacji, których już nie ma. Treść zostaje w bazie jako ślad
 * moderacyjny bez atrybucji i BEZ drogi powrotu: render pokazuje wyłącznie
 * 'przyjeta', a sklepag_opinia_moderuj ODMAWIA każdej moderacji, dopóki zamówienie
 * ma usunieto_dnia — przycisk w panelu niczego tu nie wskrzesi.
 * Zalążek zostaje zalążkiem — martwy token czyni go bezużytecznym.
 *
 * @param int $zamowienie_id Id zamówienia.
 * @return bool Czy jakiś wiersz się zmienił.
 */
function sklepag_anonimizuj_opinie( $zamowienie_id ) {
	global $wpdb;
	$t     = $wpdb->prefix . 'sklepag_opinie';
	$teraz = current_time( 'mysql' );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- własna tabela; akcja RODO musi trafić w świeży stan.
	$ok = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$t} SET imie='', token_hash='', token_wygasa=NULL,
			 status = IF( status = 'zalazek', 'zalazek', 'odrzucona' ), zmieniono=%s
			 WHERE zamowienie_id=%d",
			$teraz,
			$zamowienie_id
		)
	);
	// phpcs:enable
	if ( $ok && function_exists( 'sklepag_zdarzenie' ) ) {
		sklepag_zdarzenie( $zamowienie_id, 'opinia_anonimizacja', 'imię i token opinii usunięte, publikacja zdjęta' );
	}
	return (bool) $ok;
}

/**
 * PRZYJĘTE opinie produktu w kluczach renderu (sklepag_render_opinie).
 *
 * @param string $slug Slug produktu.
 * @return array Lista wpisów (tresc/imie/gwiazdki/tytul/data), najnowsze WG DATY
 *               PRZYSŁANIA (zgoda_czas) pierwsze — moderacja nie przestawia kolejności.
 */
function sklepag_opinie_z_bazy( $slug ) {
	global $wpdb;
	$to = $wpdb->prefix . 'sklepag_opinie';
	$tp = $wpdb->prefix . 'sklepag_produkty';
	// Data i kolejność z zgoda_czas (chwila PRZYSŁANIA opinii) — zmieniono rusza się
	// przy każdej moderacji, więc cofnięta pomyłka wskakiwałaby na górę z dzisiejszą datą.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- jw.; widok musi być świeży po moderacji.
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT o.imie, o.gwiazdki, o.tytul, o.tresc, o.zgoda_czas, o.zmieniono
			 FROM {$to} o JOIN {$tp} p ON p.id = o.produkt_id
			 WHERE p.slug = %s AND o.status = 'przyjeta'
			 ORDER BY COALESCE(o.zgoda_czas, o.zmieniono) DESC LIMIT 20",
			$slug
		)
	);
	// phpcs:enable
	$lista = array();
	foreach ( (array) $wiersze as $w ) {
		$kiedy   = ! empty( $w->zgoda_czas ) ? (string) $w->zgoda_czas : (string) $w->zmieniono;
		$lista[] = array(
			'tresc'    => (string) $w->tresc,
			'imie'     => (string) $w->imie,
			'gwiazdki' => (int) $w->gwiazdki,
			'tytul'    => (string) $w->tytul,
			'data'     => date_i18n( 'd.m.Y', strtotime( $kiedy ) ),
		);
	}
	return $lista;
}

/**
 * Wspólna, samodzielna strona mechanizmu opinii (poza motywem — jak brama pobrań,
 * kupujący przychodzi z maila). Minimalny styl w brandzie sklepu.
 *
 * @param string $tytul      Nagłówek strony.
 * @param string $tresc_html Treść (JUŻ zescapowana przez wołającego).
 */
function sklepag_opinia_strona( $tytul, $tresc_html ) {
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<meta name="robots" content="noindex, nofollow"><title>' . esc_html( $tytul ) . '</title>';
	echo '<style>body{margin:0;background:#08090b;color:#f5f5f5;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}main{max-width:560px;margin:0 auto;padding:48px 20px}h1{font-size:26px;line-height:1.25}a{color:#bfff38}label{display:block;margin:14px 0 4px;font-weight:600}input[type=text],textarea{width:100%;box-sizing:border-box;background:#101116;color:#f5f5f5;border:1px solid rgba(245,245,245,.18);border-radius:6px;padding:10px}textarea{min-height:140px}fieldset{border:0;padding:0;margin:14px 0 0}.sklepag-gw{display:flex;gap:10px;margin-top:4px}.sklepag-gw label{margin:0;font-weight:400}.sklepag-zgoda{display:flex;gap:8px;align-items:flex-start;margin-top:16px;font-size:14px;color:#c9cbd3}.sklepag-zgoda input{margin-top:4px}button{margin-top:20px;background:#bfff38;color:#08090b;border:0;border-radius:6px;padding:12px 22px;font-weight:700;font-size:16px;cursor:pointer}.sklepag-nota{color:#8f929c;font-size:13px;margin-top:18px}.sklepag-extra{position:absolute;left:-9999px;top:-9999px}</style></head><body><main>';
	echo '<p style="color:#8f929c;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;letter-spacing:1px">[ SKLEP · OPINIA ]</p>';
	echo '<h1>' . esc_html( $tytul ) . '</h1>';
	echo $tresc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- składane wyłącznie z literałów i wartości zescapowanych w wołających.
	/* Ta sama procedura co w `assets/front-ruch.js`: komunikat pola wymaganego bierzemy
		z atrybutu PRZY POLU, żeby przeglądarka nie odezwała się do kupującego po angielsku.
		⛔ Kopia, a nie wspólny plik, jest tu ŚWIADOMA: ta strona rysuje się samodzielnym
		dokumentem bez `wp_head()` (link przychodzi z maila, kupujący nie ma sesji WP), więc
		nie widzi assetów sklepu — dołożenie kolejki WordPressa złamałoby jej samodzielność.
		Walidację i tak rozstrzyga serwer; to wyłącznie warstwa języka. */
	echo '<script>(function(){Array.prototype.forEach.call(document.querySelectorAll("[data-sklepag-blad]"),function(p){"use strict";var t=p.getAttribute("data-sklepag-blad");var u=function(){p.setCustomValidity(p.validity.valueMissing?t:"");};p.setCustomValidity("");u();p.addEventListener("change",u);p.addEventListener("input",u);p.addEventListener("invalid",u);});})();</script>';
	echo '</main></body></html>';
}

/**
 * GET z maila: formularz oceny (token jednorazowy).
 */
function sklepag_obsluz_ocen_formularz() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- link przychodzi Z MAILA: kupujący nie ma sesji WP, nonce niemożliwy; autoryzacją jest token (porównanie stałoczasowe w sklepag_opinia_po_tokenie).
	if ( function_exists( 'sklepag_rate_limit' ) && ! sklepag_rate_limit( 'opinia_get', 30 ) ) {
		sklepag_opinia_strona( 'Chwila przerwy', '<p>Za dużo prób naraz. Odczekaj minutę i otwórz link ponownie.</p>' );
		exit;
	}
	$z     = isset( $_GET['z'] ) ? (int) $_GET['z'] : 0;
	$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
	// phpcs:enable
	$op = sklepag_opinia_po_tokenie( $z, $token );
	if ( ! $op ) {
		sklepag_opinia_strona( 'Link nieaktywny', '<p>Ten link do opinii nie działa: opinia mogła już zostać wysłana albo link wygasł (' . (int) SKLEPAG_OPINIA_DNI . ' dni od zakupu).</p><p class="sklepag-nota">Masz pytanie? Odpisz na maila z potwierdzeniem zakupu.</p>' );
		exit;
	}

	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- własna tabela, prefiks WP.
	$nazwa = (string) $wpdb->get_var( $wpdb->prepare( "SELECT nazwa FROM {$wpdb->prefix}sklepag_produkty WHERE id = %d", (int) $op->produkt_id ) );

	// ⛔ ODRZUT NIE MOŻE KASOWAĆ TREŚCI, KTÓRĄ NAPISAŁ KUPUJĄCY. Tu w grę wchodzi do 2000
	// znaków, a kupującemu — inaczej niż właścicielowi sklepu — nikt tego nie odtworzy.
	// Odrzut odkłada wypełnione pola w przechowalni na 15 minut i wraca tu z `blad=1`;
	// szkic nakładamy WYŁĄCZNIE na tym powrocie i kasujemy po pierwszym odczycie.
	// Klucz nie niesie tokenu jawnie (token jest tu autoryzacją), tylko jego skrót.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- powrót po odrzucie; autoryzacją całej ścieżki jest token sprawdzony wyżej.
	$po_odrzucie  = isset( $_GET['blad'] );
	$klucz_szkicu = 'sklepag_szkic_opinii_' . md5( (int) $z . '|' . $token );
	$szkic        = $po_odrzucie ? get_transient( $klucz_szkicu ) : false;
	if ( is_array( $szkic ) ) {
		delete_transient( $klucz_szkicu );
	} else {
		$szkic = array();
	}
	$we = static function ( $pole ) use ( $szkic ) {
		return isset( $szkic[ $pole ] ) ? (string) $szkic[ $pole ] : '';
	};
	// ⛔ ZGODY NIE ODTWARZAMY NIGDY. Zaznaczenie zgody na publikację imienia musi być
	// świeżym gestem człowieka; gdyby wracała zaznaczona, odrzut „brak zgody" sam by ją
	// za kupującego postawił.
	$ocena_szkicu = isset( $szkic['gwiazdki'] ) ? (int) $szkic['gwiazdki'] : 0;

	$gwiazdki = '';
	for ( $i = 1; $i <= 5; $i++ ) {
		$wybrana   = $ocena_szkicu > 0 ? ( $ocena_szkicu === $i ) : ( 5 === $i );
		$gwiazdki .= '<label><input type="radio" name="sklepag_gwiazdki" value="' . (int) $i . '"' . ( $wybrana ? ' checked' : '' ) . '> ' . (int) $i . '★</label>';
	}

	$formularz  = '<p>Oceniasz: <strong>' . esc_html( '' !== $nazwa ? $nazwa : 'zakupiony produkt' ) . '</strong>. Publikujemy opinię dopiero po potwierdzeniu zakupu i akceptacji — dlatego przy opinii pojawi się plakietka „ZWERYFIKOWANY ZAKUP".</p>';
	$formularz .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$formularz .= '<input type="hidden" name="action" value="sklepag_ocen_zapisz">';
	$formularz .= '<input type="hidden" name="z" value="' . (int) $op->zamowienie_id . '">';
	$formularz .= '<input type="hidden" name="t" value="' . esc_attr( $token ) . '">';
	$formularz .= '<div class="sklepag-extra" aria-hidden="true"><label>Zostaw to pole puste<input type="text" name="sklepag_extra" tabindex="-1" autocomplete="off"></label></div>';
	$formularz .= '<fieldset><label>Ocena</label><div class="sklepag-gw">' . $gwiazdki . '</div></fieldset>';
	$formularz .= '<label for="sklepag-imie">Imię (pokażemy je przy opinii)</label><input id="sklepag-imie" type="text" name="sklepag_imie" maxlength="100" required data-sklepag-blad="' . esc_attr( SKLEPAG_BLAD_OPINIA_IMIE ) . '" value="' . esc_attr( $we( 'imie' ) ) . '">';
	$formularz .= '<label for="sklepag-tytul">Tytuł opinii (opcjonalnie)</label><input id="sklepag-tytul" type="text" name="sklepag_tytul" maxlength="150" value="' . esc_attr( $we( 'tytul' ) ) . '">';
	$formularz .= '<label for="sklepag-tresc">Twoja opinia</label><textarea id="sklepag-tresc" name="sklepag_tresc" maxlength="2000" required data-sklepag-blad="' . esc_attr( SKLEPAG_BLAD_OPINIA_TRESC ) . '">' . esc_textarea( $we( 'tresc' ) ) . '</textarea>';
	$formularz .= '<label class="sklepag-zgoda"><input type="checkbox" name="sklepag_zgoda" value="1" required data-sklepag-blad="' . esc_attr( SKLEPAG_BLAD_OPINIA_ZGODA ) . '"> ' . esc_html( SKLEPAG_OPINIA_ZGODA_TRESC ) . '</label>';
	$formularz .= '<button type="submit">Wyślij opinię</button>';
	$formularz .= '<p class="sklepag-nota">Link jest jednorazowy. Opinia trafia najpierw do moderacji — na stronie pojawi się po akceptacji.</p>';
	$formularz .= '</form>';

	sklepag_opinia_strona( 'Oceń swój zakup', $formularz );
	exit;
}
add_action( 'admin_post_nopriv_sklepag_ocen', 'sklepag_obsluz_ocen_formularz' );
add_action( 'admin_post_sklepag_ocen', 'sklepag_obsluz_ocen_formularz' );

/**
 * POST formularza: walidacja + zapis (zalazek → oczekuje).
 */
function sklepag_obsluz_ocen_zapis() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- jak wyżej: autoryzacją jest jednorazowy token z maila, nie nonce sesji WP.
	if ( function_exists( 'sklepag_rate_limit' ) && ! sklepag_rate_limit( 'opinia_post', 10 ) ) {
		sklepag_opinia_strona( 'Chwila przerwy', '<p>Za dużo prób naraz. Odczekaj minutę i spróbuj ponownie.</p>' );
		exit;
	}
	if ( ! empty( $_POST['sklepag_extra'] ) ) {
		// Pułapka-pole: robotowi pokazujemy neutralny sukces, nic nie zapisując.
		sklepag_opinia_strona( 'Dziękujemy', '<p>Dziękujemy za przesłanie opinii.</p>' );
		exit;
	}
	$z     = isset( $_POST['z'] ) ? (int) $_POST['z'] : 0;
	$token = isset( $_POST['t'] ) ? sanitize_text_field( wp_unslash( $_POST['t'] ) ) : '';
	$dane  = array(
		'imie'     => isset( $_POST['sklepag_imie'] ) ? sanitize_text_field( wp_unslash( $_POST['sklepag_imie'] ) ) : '',
		'gwiazdki' => isset( $_POST['sklepag_gwiazdki'] ) ? (int) $_POST['sklepag_gwiazdki'] : 0,
		'tytul'    => isset( $_POST['sklepag_tytul'] ) ? sanitize_text_field( wp_unslash( $_POST['sklepag_tytul'] ) ) : '',
		'tresc'    => isset( $_POST['sklepag_tresc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sklepag_tresc'] ) ) : '',
		'zgoda'    => ! empty( $_POST['sklepag_zgoda'] ),
	);
	// phpcs:enable

	$wynik = sklepag_opinia_zapisz( $z, $token, $dane );

	if ( '' === $wynik ) {
		sklepag_opinia_strona( 'Dziękujemy!', '<p>Opinia dotarła i czeka na akceptację — po niej pojawi się na stronie produktu z plakietką „ZWERYFIKOWANY ZAKUP".</p>' );
		exit;
	}
	if ( 'pola' === $wynik || 'zgoda' === $wynik ) {
		$powod = 'zgoda' === $wynik
			? 'Bez zgody na publikację nie możemy pokazać opinii — zaznacz zgodę i wyślij ponownie.'
			: 'Uzupełnij imię, treść opinii i ocenę (1–5), potem wyślij ponownie.';
		// Odkładamy to, co kupujący już napisał — bez zgody, która musi być świeżym gestem.
		set_transient(
			'sklepag_szkic_opinii_' . md5( (int) $z . '|' . $token ),
			array(
				'imie'     => $dane['imie'],
				'tytul'    => $dane['tytul'],
				'tresc'    => $dane['tresc'],
				'gwiazdki' => (int) $dane['gwiazdki'],
			),
			15 * MINUTE_IN_SECONDS
		);
		sklepag_opinia_strona( 'Prawie się udało', '<p>' . esc_html( $powod ) . '</p><p><a href="' . esc_url( admin_url( 'admin-post.php' ) . '?action=sklepag_ocen&z=' . (int) $z . '&t=' . rawurlencode( $token ) . '&blad=1' ) . '">← Wróć do formularza (Twoja treść czeka)</a></p>' );
		exit;
	}
	sklepag_opinia_strona( 'Link nieaktywny', '<p>Nie udało się zapisać: opinia mogła już zostać wysłana albo link wygasł.</p>' );
	exit;
}
add_action( 'admin_post_nopriv_sklepag_ocen_zapisz', 'sklepag_obsluz_ocen_zapis' );
add_action( 'admin_post_sklepag_ocen_zapisz', 'sklepag_obsluz_ocen_zapis' );
