<?php
/**
 * Widok pojedynczego zamówienia (K3d; wzorce §4 — WooCommerce/Stripe):
 * LEWA kolumna: status (badge) → dane zamówienia/kupującego/płatności →
 * treść wysłanego maila + „Wyślij ponownie" TUŻ OBOK (wzorzec Stripe receipt).
 * PRAWA kolumna: oś czasu zdarzeń tego zamówienia.
 * Akcje: resend (regeneruje token+termin — stary link mógł wygasnąć) oraz
 * anonimizacja kupującego na żądanie RODO (email→NULL +
 * usunieto_dnia; kwoty i czasy zostają — dashboard żyje).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Akcja: ponowna wysyłka maila ---------- */

add_action( 'admin_post_sklepag_resend', 'sklepag_obsluz_resend' );

/**
 * Resend generuje ŚWIEŻY token i termin (72 h) — stary link mógł wygasnąć,
 * a to właśnie najczęstszy powód ponownej wysyłki. Skrót w bazie nadpisany =
 * stary link przestaje działać (jeden ważny token na zamówienie).
 * Wyścig dwóch równoczesnych resendów: wygrywa ostatni zapis skrótu, wcześniejszy
 * mail niesie martwy link — naprawialne kolejnym resendem, akceptowalne dla akcji ręcznej.
 */
function sklepag_obsluz_resend() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_resend' );

	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; akcja admina na żywych danych.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE id = %d", $id ) );

	if ( ! $zam ) {
		wp_die( 'Zamówienie nie istnieje.', 'Sklep Agentów', 404 );
	}
	if ( '' === (string) $zam->email_kupujacego ) {
		wp_die( 'Zamówienie bez adresu e-mail (kupujący zanonimizowany?) — nie ma dokąd wysłać.', 'Sklep Agentów', 400 );
	}
	// Tylko stany PO domknięciu wysyłki: `mail_w_toku` = webhook
	// właśnie domyka (resend nadpisałby token bez CAS), `oczekuje`/`oplacone` =
	// silnik zaraz wyśle sam. Utknięte `mail_w_toku` odzyskuje reconciliation
	// (stale-claim po 5 min) i przestawia status — wtedy resend się odblokuje.
	if ( ! in_array( $zam->status, array( 'mail_wyslany', 'mail_blad' ), true ) ) {
		wp_die( 'Zamówienie jest właśnie domykane automatycznie (status: ' . esc_html( $zam->status ) . ') — ponowna wysyłka dostępna, gdy silnik skończy.', 'Sklep Agentów', 409 );
	}

	// Świeży token + termin, treść odbudowana ze SNAPSHOTU zamówienia.
	$token = bin2hex( random_bytes( 32 ) );
	$teraz = current_time( 'mysql' );
	$link  = admin_url( 'admin-post.php' ) . '?action=sklepag_pobierz&z=' . (int) $zam->id . '&t=' . $token;

	// Link do opinii jak w wysyłce automatycznej: zalążek odświeża token
	// w stanie 'zalazek', a dla opinii już przysłanej zwraca '' — mail idzie
	// wtedy bez akapitu oceny. Bez tego resend gubił kupującemu drogę do opinii.
	// Zapamiętujemy skrót tokenu opinii SPRZED rotacji. Nieudana wysyłka nie może zabrać
	// kupującemu także drogi do opinii — to ta sama wada co przy linku pobrania, tylko na
	// drugim tokenie: stary link z pierwszego maila przestałby działać, a nowy nie dotarł.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$opinia_hash_przed = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", (int) $zam->id ) );
	$token_op          = sklepag_opinia_zalazek( (int) $zam->id, (int) $zam->produkt_id );
	$link_op           = '' !== $token_op ? admin_url( 'admin-post.php' ) . '?action=sklepag_ocen&z=' . (int) $zam->id . '&t=' . $token_op : '';

	$produkt_snapshot        = new stdClass();
	$produkt_snapshot->nazwa = $zam->produkt_nazwa_kupiona;
	// Slug po id produktu: akapit gwarancji w mailu bierze treść z danych produktu (jak wysyłka
	// automatyczna); produkt skasowany = brak sluga = mail bez tego akapitu, bez błędu.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki, nazwa z prefiksu WP.
	$produkt_snapshot->slug = (string) $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}sklepag_produkty WHERE id=%d", (int) $zam->produkt_id ) );
	$tresc                  = sklepag_zbuduj_mail( $produkt_snapshot, $zam, $link, $link_op );

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, mail_tresc=%s, zmieniono=%s WHERE id=%d",
			hash( 'sha256', $token ),
			gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_LINK_GODZIN * HOUR_IN_SECONDS ),
			$tresc,
			$teraz,
			(int) $zam->id
		)
	);

	$wyslano = sklepag_wyslij_mail_sklepu( $zam->email_kupujacego, 'Twój zakup: ' . $zam->produkt_nazwa_kupiona . ' — potwierdzenie i pobranie', $tresc );

	if ( $wyslano ) {
		// Status nadpisujemy TYLKO gdy zamówienie nie jest zamknięte finansowo. Bez tego jedno
		// kliknięcie „Wyślij ponownie" na zamówieniu zwróconym kasowało znacznik zwrotu i wracało
		// je do liczby sprzedaży na Pulpicie. Mail wychodzi tak czy tak — blokujemy zapis stanu,
		// nie wysyłkę.
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', zmieniono=%s WHERE id=%d AND status NOT IN (" . SKLEPAG_STATUSY_ZAMKNIETE . ')', $teraz, (int) $zam->id ) );
		sklepag_zdarzenie( (int) $zam->id, 'mail_resend', 'ręczna ponowna wysyłka do: ' . $zam->email_kupujacego . ' (świeży link)' );
	} else {
		// KOMPENSACJA: nowy token wszedł do bazy PRZED wysyłką, więc nieudany mail zostawiłby
		// kupującego bez niczego — stary link daje 403, a nowy nigdy nie dotarł. Przywracamy
		// poprzedni token i termin, żeby „Wyślij ponownie" nigdy nie ODBIERAŁO dostępu.
		if ( null === $zam->link_wygasa ) {
			// Kolumna `link_wygasa` jest DATETIME NULL — prepare() z %s zrobiłby z NULL-a pusty
			// string (data zerowa albo błąd w trybie ścisłym), więc NULL zapisujemy dosłownie.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=NULL, zmieniono=%s WHERE id=%d",
					(string) $zam->token_pobrania,
					$teraz,
					(int) $zam->id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, zmieniono=%s WHERE id=%d",
					(string) $zam->token_pobrania,
					(string) $zam->link_wygasa,
					$teraz,
					(int) $zam->id
				)
			);
		}
		// Stan i licznik prób osobno od kompensacji tokenu: token i termin MUSZĄ wrócić zawsze
		// (inaczej „Wyślij ponownie" odbierałoby dostęp), a status wolno ruszyć tylko wtedy,
		// gdy zamówienie nie jest zamknięte finansowo.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$pz} SET status='mail_blad', mail_proby=mail_proby+1, zmieniono=%s WHERE id=%d AND status NOT IN (" . SKLEPAG_STATUSY_ZAMKNIETE . ')',
				$teraz,
				(int) $zam->id
			)
		);
		// Treść maila też wraca: panel pokazuje „treść wysłanego maila" i po nieudanej próbie
		// wyświetlałby link, który już nie działa, a ten działający nie byłby widoczny nigdzie.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET mail_tresc=%s WHERE id=%d", (string) $zam->mail_tresc, (int) $zam->id ) );
		if ( '' !== $opinia_hash_przed ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_opinie SET token_hash=%s WHERE zamowienie_id=%d AND status='zalazek'", $opinia_hash_przed, (int) $zam->id ) );
		}
		// phpcs:enable
		sklepag_zdarzenie( (int) $zam->id, 'mail_blad', 'ręczna ponowna wysyłka nieudana (wp_mail zwrócił false) — poprzedni link pobrania, treść maila i link do opinii przywrócone' );
	}
	// phpcs:enable

	wp_safe_redirect( add_query_arg( 'sklepag_resend', $wyslano ? 'ok' : 'blad', sklepag_url_zamowienia( (int) $zam->id ) ) );
	exit;
}

/* ---------- Akcja: anonimizacja kupującego (RODO) ---------- */

add_action( 'admin_post_sklepag_anonimizuj', 'sklepag_obsluz_anonimizacje' );

/**
 * Email → NULL + usunieto_dnia; kwoty i czasy ZOSTAJĄ (dashboard żyje).
 * Ślady adresu w opisach zdarzeń tego zamówienia zastępowane znacznikiem;
 * token pobrania unieważniany (zanonimizowany klient = koniec relacji).
 */
function sklepag_obsluz_anonimizacje() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_anonimizuj' );

	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; akcja admina.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT id, email_kupujacego, mail_tresc FROM {$pz} WHERE id = %d", $id ) );
	if ( ! $zam ) {
		wp_die( 'Zamówienie nie istnieje.', 'Sklep Agentów', 404 );
	}

	$email = (string) $zam->email_kupujacego;
	$teraz = current_time( 'mysql' );

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET email_kupujacego='', usunieto_dnia=%s, token_pobrania='', link_wygasa=NULL, zmieniono=%s WHERE id=%d",
			$teraz,
			$teraz,
			(int) $zam->id
		)
	);
	if ( '' !== $email ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}sklepag_zdarzenia SET opis = REPLACE(opis, %s, '[usunięto — RODO]') WHERE zamowienie_id = %d",
				$email,
				(int) $zam->id
			)
		);
	}
	// phpcs:enable

	// Symetria RODO dla mechanizmu opinii: imię i token opinii umierają razem
	// z tokenem pobrania, publikacja schodzi ze strony — szczegóły w sklepag_anonimizuj_opinie().
	sklepag_anonimizuj_opinie( (int) $zam->id );

	sklepag_zdarzenie( (int) $zam->id, 'anonimizacja', 'kupujący zanonimizowany na żądanie (RODO); token pobrania i opinia unieważnione' );

	wp_safe_redirect( add_query_arg( 'sklepag_anonimizacja', 'ok', sklepag_url_zamowienia( (int) $zam->id ) ) );
	exit;
}

/* ---------- Render ---------- */

/**
 * Adres widoku zamówienia (strona zarejestrowana bez pozycji w menu).
 *
 * @param int $id Id zamówienia.
 */
function sklepag_url_zamowienia( $id ) {
	return admin_url( 'admin.php?page=sklepag-zamowienie&zamowienie=' . (int) $id );
}

/**
 * Callback strony „Zamówienie" (ukryta — wejście linkiem z tabeli dashboardu).
 */
function sklepag_admin_zamowienie() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt identyfikatora widoku read-only.
	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; widok na żywo.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE id = %d", $id ) );
	// phpcs:enable

	echo '<div class="wrap sklepag-zamowienie">';
	echo '<h1>Zamówienie ' . ( $zam ? '#' . (int) $zam->id : '' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=sklepag' ) ) . '" class="page-title-action">← wróć do listy</a></h1>';

	if ( ! $zam ) {
		echo '<div class="notice notice-error"><p>Zamówienie nie istnieje — mogło zostać usunięte.</p></div></div>';
		return;
	}

	// Komunikaty po akcjach (redirect z admin-post).
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- komunikaty po redirekcie, bez mutacji.
	if ( isset( $_GET['sklepag_resend'] ) ) {
		echo 'ok' === $_GET['sklepag_resend']
			? '<div class="notice notice-success is-dismissible"><p>Mail wysłany ponownie ze świeżym linkiem pobrania (ważny ' . (int) round( SKLEPAG_LINK_GODZIN / 24 ) . ' dni).</p></div>'
			: '<div class="notice notice-error"><p>Ponowna wysyłka NIE powiodła się — sprawdź konfigurację poczty serwera.</p></div>';
	}
	if ( isset( $_GET['sklepag_anonimizacja'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Kupujący zanonimizowany: adres usunięty, token pobrania unieważniony; kwoty i czasy pozostały w statystykach.</p></div>';
	}
	// phpcs:enable

	$zanonimizowane = ! empty( $zam->usunieto_dnia );

	echo '<div class="sklepag-zamowienie-kolumny">';

	// LEWA kolumna.
	echo '<div class="sklepag-zamowienie-glowna">';

	echo '<div class="postbox sklepag-box"><div class="inside">';
	echo '<p class="sklepag-zamowienie-status">Status: ' . sklepag_badge_statusu( $zam->status ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge budowany z escapowanych części.
	echo '<table class="widefat striped sklepag-zamowienie-dane"><tbody>';
	echo '<tr><td>Data zakupu (czas webhooka)</td><td>' . esc_html( $zam->utworzono ) . '</td></tr>';
	echo '<tr><td>Kupujący</td><td>' . ( $zanonimizowane ? '<em>zanonimizowany (' . esc_html( $zam->usunieto_dnia ) . ')</em>' : esc_html( $zam->email_kupujacego ) ) . '</td></tr>';
	echo '<tr><td>Produkt (snapshot z chwili zakupu)</td><td>' . esc_html( $zam->produkt_nazwa_kupiona ) . '</td></tr>';
	echo '<tr><td>Kwota</td><td>' . esc_html( number_format( $zam->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $zam->waluta ) . ' <span class="sklepag-szary">(tryb testowy)</span></td></tr>';
	echo '<tr><td>Sesja Stripe</td><td><code>' . esc_html( $zam->stripe_session_id ) . '</code></td></tr>';
	echo '<tr><td>Pobrania</td><td>' . esc_html( (int) $zam->pobrania_uzyte . ' / ' . (int) $zam->pobrania_limit ) . ( $zam->link_wygasa ? ' · link ważny do ' . esc_html( $zam->link_wygasa ) : ' · link unieważniony' ) . '</td></tr>';
	// Kolumna zgoda_czas jest w GMT — panel pokazuje czas lokalny strony, ten sam, który dostaje kupujący w mailu.
	echo '<tr><td>Zgoda na dostarczenie treści cyfrowej</td><td>' . esc_html( $zam->zgoda_czas ? get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) . ' (czas lokalny)' : '—' ) . '</td></tr>';
	echo '</tbody></table>';
	echo '</div></div>';

	// Treść maila + akcje TUŻ OBOK (wzorzec Stripe „Resend receipt").
	echo '<div class="postbox sklepag-box"><div class="inside">';
	echo '<h2 class="sklepag-box-tytul">Treść wysłanego maila <span class="sklepag-szary">(podgląd treści — nie kopia z Twojej skrzynki)</span></h2>';
	echo $zam->mail_tresc
		? '<pre class="sklepag-mail-tresc">' . esc_html( $zam->mail_tresc ) . '</pre>'
		: '<p class="description">Treść maila jeszcze nie powstała (zamówienie niedomknięte).</p>';
	if ( ! $zanonimizowane && '' !== (string) $zam->email_kupujacego ) {
		echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_resend&zamowienie=' . (int) $zam->id ), 'sklepag_resend' ) ) . '" class="button button-primary">Wyślij ponownie (świeży link)</a></p>';
	}
	echo '</div></div>';

	// RODO.
	echo '<div class="postbox sklepag-box"><div class="inside">';
	echo '<h2 class="sklepag-box-tytul">Dane osobowe (RODO)</h2>';
	if ( $zanonimizowane ) {
		echo '<p>Kupujący zanonimizowany ' . esc_html( $zam->usunieto_dnia ) . ' — adres usunięty, kwoty i czasy pozostały w statystykach.</p>';
	} else {
		echo '<p class="description">Na żądanie kupującego: usuwa adres e-mail i unieważnia link pobrania; kwoty i czasy zostają (statystyki sprzedaży). Operacja NIEODWRACALNA.</p>';
		echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_anonimizuj&zamowienie=' . (int) $zam->id ), 'sklepag_anonimizuj' ) ) . '" class="button sklepag-button-uwaga" onclick="return confirm(\'Anonimizacja jest NIEODWRACALNA: adres e-mail zniknie, link pobrania przestanie działać. Kontynuować?\');">Anonimizuj kupującego</a></p>';
	}
	echo '</div></div>';

	echo '</div>'; // Koniec lewej.

	// PRAWA kolumna: oś czasu zdarzeń zamówienia.
	echo '<div class="sklepag-zamowienie-boczna"><div class="postbox sklepag-box"><div class="inside">';
	echo '<h2 class="sklepag-box-tytul">Oś czasu</h2>';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; oś czasu na żywo.
	$zdarzenia = $wpdb->get_results( $wpdb->prepare( "SELECT czas, typ, opis, uzytkownik FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id = %d ORDER BY id DESC LIMIT 50", (int) $zam->id ) );
	// phpcs:enable
	if ( empty( $zdarzenia ) ) {
		echo '<p class="description">Brak zdarzeń dla tego zamówienia.</p>';
	} else {
		echo '<ul class="sklepag-os-czasu">';
		foreach ( $zdarzenia as $z ) {
			echo '<li><span class="sklepag-os-czas">' . esc_html( $z->czas ) . '</span><br><strong>' . esc_html( $z->typ ) . '</strong>'
				. ( '' !== $z->uzytkownik ? ' <span class="sklepag-badge sklepag-badge-admin">' . esc_html( $z->uzytkownik ) . '</span>' : '' )
				. ( '' !== $z->opis ? '<br><span class="sklepag-szary">' . esc_html( mb_substr( $z->opis, 0, 200 ) ) . '</span>' : '' ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div></div></div>';

	echo '</div></div>';
}
