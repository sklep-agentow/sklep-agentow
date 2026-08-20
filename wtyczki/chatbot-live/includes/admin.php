<?php
/**
 * Admin: menu, CRUD Q&A, zgłoszenia, historia rozmów, ustawienia.
 * Każda mutacja: nonce + capability manage_options (admin-post + redirect).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function chatl_admin_menu() {
	add_menu_page( 'ChatBot LIVE', 'ChatBot LIVE', 'manage_options', 'chatl-qa', 'chatl_strona_qa', 'dashicons-format-chat', 58 );
	add_submenu_page( 'chatl-qa', 'Baza pytań i odpowiedzi', 'Baza Q&A', 'manage_options', 'chatl-qa', 'chatl_strona_qa' );
	add_submenu_page( 'chatl-qa', 'Statystyki i luki', 'Statystyki i luki', 'manage_options', 'chatl-statystyki', 'chatl_strona_statystyki' );
	add_submenu_page( 'chatl-qa', 'Zgłoszenia z czatu', 'Zgłoszenia', 'manage_options', 'chatl-zgloszenia', 'chatl_strona_zgloszenia' );
	add_submenu_page( 'chatl-qa', 'Historia rozmów', 'Historia rozmów', 'manage_options', 'chatl-historia', 'chatl_strona_historia' );
	add_submenu_page( 'chatl-qa', 'Ustawienia ChatBota', 'Ustawienia', 'manage_options', 'chatl-ustawienia', 'chatl_strona_ustawienia' );
}
add_action( 'admin_menu', 'chatl_admin_menu' );

/**
 * Komunikaty po powrocie z admin-post (?chatl_ok=... / ?chatl_blad=...).
 * Kanał błędu istnieje, bo operacja, której zapis padł, NIE MOŻE kończyć się
 * zieloną plakietką „Zapisano." — admin musi zobaczyć, że danych nie ma.
 */
function chatl_komunikat_ok() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- tylko odczyt komunikatu.
	if ( isset( $_GET['chatl_ok'] ) ) {
		$teksty = array(
			'zapisano'  => 'Zapisano.',
			'klucz_ok'  => 'Klucz API działa — API odpowiedziało poprawnie.',
			'usunieto'  => 'Usunięto.',
			'zmieniono' => 'Zmieniono status.',
			'przejeta'  => 'Rozmowa przejęta — od tej chwili bot w niej milczy, a gość widzi, że pisze do człowieka.',
			'oddana'    => 'Rozmowa oddana botowi — gość dostał o tym komunikat.',
		);
		$klucz  = sanitize_key( wp_unslash( $_GET['chatl_ok'] ) );
		if ( isset( $teksty[ $klucz ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $teksty[ $klucz ] ) . '</p></div>';
		}
	}
	if ( isset( $_GET['chatl_blad'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['chatl_blad'] ) ) ) . '</p></div>';
	}
	// phpcs:enable
	chatl_notice_bledu_maila();
	chatl_notice_bledu_ai();
	chatl_notice_odrzuconych_nonce();
}

/**
 * Powrót z admin-post z komunikatem błędu (zapis do bazy padł).
 *
 * @param string $strona    Slug strony wtyczki (page=...).
 * @param string $komunikat Treść dla admina.
 * @return void
 */
function chatl_przekieruj_blad( $strona, $komunikat ) {
	wp_safe_redirect( admin_url( 'admin.php?page=' . $strona . '&chatl_blad=' . rawurlencode( $komunikat ) ) );
	exit;
}

/**
 * Ostrzeżenie, gdy ostatnie powiadomienie mailowe o zgłoszeniu nie wyszło
 * (znacznik ustawia chatl_odnotuj_wysylke_powiadomienia() z front.php).
 */
function chatl_notice_bledu_maila() {
	$blad = get_option( 'chatl_blad_maila' );
	if ( ! is_array( $blad ) || empty( $blad['kiedy'] ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Powiadomienia mailowe nie dochodzą.</strong> Ostatnia próba wysyłki na <code>'
		. esc_html( (string) ( $blad['adres'] ?? '' ) ) . '</code> nie powiodła się ('
		. esc_html( (string) $blad['kiedy'] ) . '). Zgłoszenia gości <strong>są zapisane</strong> i widoczne na liście — sprawdź konfigurację poczty na hostingu (np. wtyczkę SMTP).</p></div>';
}

/**
 * Model wybrany w formularzu: lista albo pole „własny".
 *
 * Pusty wynik znaczy „użyj wartości domyślnej produktu" — dlatego wybranie
 * z listy modelu równego CHATL_AI_MODEL nie zapisuje niczego na sztywno.
 * Dzięki temu podniesienie domyślnego modelu w kolejnej wersji wtyczki dociera
 * do wszystkich, którzy go świadomie nie zmienili.
 *
 * @param string $stary Poprzednia wartość (gdy formularz nic nie przysłał).
 * @return string Identyfikator modelu albo pusty string.
 */
function chatl_model_z_formularza( $stary ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- wołane z chatl_post_ustawienia_zapisz(), które sprawdza nonce i uprawnienia.
	if ( ! isset( $_POST['ai_model_lista'] ) ) {
		return (string) $stary;
	}
	$wybor = sanitize_text_field( wp_unslash( $_POST['ai_model_lista'] ) );
	if ( 'wlasny' === $wybor ) {
		$wlasny = isset( $_POST['ai_model_wlasny'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_model_wlasny'] ) ) : '';
		// Identyfikatory modeli to litery, cyfry, myślniki i kropki — nic więcej
		// nie ma prawa trafić do żądania do API.
		$wlasny = trim( (string) preg_replace( '/[^a-zA-Z0-9.\-]/', '', $wlasny ) );
		return $wlasny;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( ! isset( chatl_ai_modele()[ $wybor ] ) ) {
		return (string) $stary;
	}
	return CHATL_AI_MODEL === $wybor ? '' : $wybor;
}

/**
 * Czerwony pasek: ostatnie wywołanie API nie powiodło się.
 *
 * To jest jedyny kanał, którym właściciel dowie się, że płaci za tryb AI,
 * którego nie ma. Log WordPressa nie wystarcza — na produkcji WP_DEBUG_LOG
 * jest wyłączony i błąd nie ma dokąd trafić.
 *
 * @return void
 */
function chatl_notice_bledu_ai() {
	$blad = get_option( 'chatl_blad_ai' );
	if ( ! is_array( $blad ) || empty( $blad['kiedy'] ) ) {
		return;
	}
	$kod = (int) ( $blad['kod'] ?? 0 );
	if ( 401 === $kod || 403 === $kod ) {
		$rada = 'API odrzuciło klucz — najprawdopodobniej jest zły, wygasł albo stracił uprawnienia. Wklej nowy w ustawieniach.';
	} elseif ( 404 === $kod ) {
		$rada = 'API nie zna podanego modelu — sprawdź jego nazwę w ustawieniach.';
	} elseif ( 200 === $kod ) {
		// Osobna rada, bo to zupełnie inny trop niż zły klucz: połączenie
		// i uwierzytelnienie zadziałały, popsuła się TREŚĆ odpowiedzi.
		$rada = 'API odpowiedziało poprawnym kodem, ale treść odpowiedzi była nieczytelna. Najczęstsza przyczyna to proxy, brama zgodności albo wtyczka bezpieczeństwa po drodze, która podmienia odpowiedź. Klucz jest tu prawdopodobnie w porządku.';
	} else {
		$rada = 'Sprawdź klucz i połączenie z API; jeśli błąd się powtarza, skontaktuj się z dostawcą modelu.';
	}

	echo '<div class="notice notice-error"><p><strong>Tryb AI nie działa — bot odpowiada wyłącznie z reguł.</strong><br>'
		. 'Ostatnie nieudane wywołanie API: <code>' . esc_html( (string) $blad['kiedy'] ) . '</code>'
		. ( $kod > 0 ? ', kod HTTP <code>' . esc_html( (string) $kod ) . '</code>' : ' (błąd połączenia)' ) . '.<br>'
		. esc_html( $rada ) . '<br>'
		. ( ! empty( $blad['tresc'] ) ? '<code>' . esc_html( (string) $blad['tresc'] ) . '</code><br>' : '' )
		. '<a href="' . esc_url( admin_url( 'admin.php?page=chatl-ustawienia' ) ) . '">Przejdź do ustawień i przetestuj klucz</a>'
		. ' — znacznik znika sam po pierwszym udanym wywołaniu.</p></div>';
}

/**
 * „Przetestuj klucz" — jedno tanie wywołanie API, wynik od razu na ekranie.
 */
function chatl_post_test_klucza() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_test_klucza' );

	$wynik = chatl_ai_test_klucza();
	if ( $wynik['ok'] ) {
		wp_safe_redirect( admin_url( 'admin.php?page=chatl-ustawienia&chatl_ok=klucz_ok' ) );
		exit;
	}
	chatl_przekieruj_blad( 'chatl-ustawienia', $wynik['komunikat'] );
}
add_action( 'admin_post_chatl_test_klucza', 'chatl_post_test_klucza' );

/**
 * Ostrzeżenie: gościom odbija się nonce (najczęściej: strona z cache).
 *
 * To jest awaria, której właściciel NIE MA JAK zobaczyć inaczej — czat po
 * prostu przestaje działać wszystkim naraz, a panel wygląda normalnie.
 * Ostrzeżenie samo cichnie po CHATL_NONCE_OKNO_DNI dniach bez nowych odrzuceń,
 * bo znacznika nie kasujemy na ścieżce zwykłego pytania (to byłby zapis do bazy
 * przy każdej wiadomości gościa).
 *
 * @return void
 */
function chatl_notice_odrzuconych_nonce() {
	$blad = get_option( 'chatl_blad_nonce' );
	if ( ! is_array( $blad ) || empty( $blad['ostatni'] ) ) {
		return;
	}
	$kiedy = strtotime( (string) $blad['ostatni'] );
	if ( ! $kiedy || $kiedy < time() - CHATL_NONCE_OKNO_DNI * DAY_IN_SECONDS ) {
		return;
	}
	$zamknij = wp_nonce_url( admin_url( 'admin-post.php?action=chatl_nonce_zamknij' ), 'chatl_nonce_zamknij' );
	echo '<div class="notice notice-error"><p><strong>Gościom nie działa czat — odrzucone zabezpieczenie formularza.</strong> '
		. 'Odrzuconych żądań: <code>' . esc_html( number_format_i18n( (int) $blad['ile'] ) ) . '</code>, ostatnie: <code>'
		. esc_html( (string) $blad['ostatni'] ) . '</code>.<br>'
		. 'Najczęstsza przyczyna: <strong>wtyczka albo usługa cache</strong> (LiteSpeed, WP Rocket, W3TC, Cloudflare) podaje gościom '
		. 'stronę starszą niż ważność zabezpieczenia. Widget próbuje wtedy odświeżyć je sam i powtórzyć pytanie, więc rozmowa zwykle '
		. 'dochodzi do skutku — ale jeśli ta liczba rośnie, <strong>wyłącz cache dla strony z czatem</strong> albo skróć czas życia cache.<br>'
		. '<a href="' . esc_url( $zamknij ) . '">Rozumiem, ukryj to ostrzeżenie</a> (wróci, gdy pojawią się nowe odrzucenia).</p></div>';
}

/**
 * Ukrycie ostrzeżenia o nonce'ach — kasuje znacznik.
 */
function chatl_post_nonce_zamknij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_nonce_zamknij' );
	delete_option( 'chatl_blad_nonce' );
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa&chatl_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_chatl_nonce_zamknij', 'chatl_post_nonce_zamknij' );

/* ===================== BAZA Q&A ===================== */

function chatl_strona_qa() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_qa';

	$edytowany = null;
	if ( isset( $_GET['edytuj'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edytowany = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id = %d", (int) $_GET['edytuj'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.NonceVerification.Recommended
	}
	$wpisy = $wpdb->get_results( "SELECT * FROM {$tabela} ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/*
	 * Skrót ze strony „Statystyki i luki": pytanie gościa, na które bot nie
	 * umiał odpowiedzieć, wchodzi tu WPISANE — razem z podpowiedzią słów
	 * kluczowych. To zamyka pętlę: raport pokazuje lukę, jedno kliknięcie
	 * otwiera jej załatanie. Wartości z adresu tylko WYPEŁNIAJĄ formularz,
	 * niczego nie zapisują — zapis idzie jak zawsze przez admin-post z noncem.
	 */
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- samo wypełnienie pól formularza; mutacja jest osobnym żądaniem z noncem.
	$z_luki       = isset( $_GET['nowe_pytanie'] ) ? sanitize_text_field( wp_unslash( $_GET['nowe_pytanie'] ) ) : '';
	$slowa_z_luki = isset( $_GET['nowe_slowa'] ) ? sanitize_text_field( wp_unslash( $_GET['nowe_slowa'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( $edytowany ) {
		$wart_pytanie = $edytowany->pytanie;
		$wart_slowa   = $edytowany->slowa_kluczowe;
	} else {
		$wart_pytanie = $z_luki;
		$wart_slowa   = $slowa_z_luki;
	}
	?>
	<div class="wrap">
		<h1>Baza pytań i odpowiedzi</h1>
		<?php chatl_komunikat_ok(); ?>
		<p>Bot dopasowuje pytanie gościa po <strong>słowach kluczowych z wariantami</strong> (rozdzielaj przecinkami, np. <code>cena, ceny, cennik</code>). Odmiany słów łapiesz, wpisując warianty.</p>

		<?php chatl_panel_testu_bota(); ?>

		<?php if ( ! $edytowany && '' !== $z_luki ) : ?>
			<div class="notice notice-info inline" style="margin:12px 0"><p>
				Pytanie przyszło ze strony <a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-statystyki' ) ); ?>">Statystyki i luki</a> —
				bot nie umiał na nie odpowiedzieć. <strong>Wpisz odpowiedź</strong> i zapisz; słowa kluczowe to tylko podpowiedź, popraw je śmiało.
			</p></div>
		<?php endif; ?>
		<h2><?php echo $edytowany ? 'Edytuj wpis #' . (int) $edytowany->id : 'Dodaj nowy wpis'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="chatl_qa_zapisz">
			<?php wp_nonce_field( 'chatl_qa_zapisz' ); ?>
			<input type="hidden" name="id" value="<?php echo $edytowany ? (int) $edytowany->id : 0; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="chatl-pytanie">Pytanie wzorcowe</label></th>
					<td><input type="text" id="chatl-pytanie" name="pytanie" class="large-text" required
						value="<?php echo esc_attr( $wart_pytanie ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-slowa">Słowa kluczowe (warianty po przecinku)</label></th>
					<td><input type="text" id="chatl-slowa" name="slowa_kluczowe" class="large-text" required
						value="<?php echo esc_attr( $wart_slowa ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-odpowiedz">Odpowiedź bota</label></th>
					<td><textarea id="chatl-odpowiedz" name="odpowiedz" class="large-text" rows="4" required><?php echo $edytowany ? esc_textarea( $edytowany->odpowiedz ) : ''; ?></textarea></td>
				</tr>
				<tr>
					<th scope="row">Aktywny</th>
					<td><label><input type="checkbox" name="aktywne" value="1" <?php checked( ! $edytowany || $edytowany->aktywne ); ?>> bot używa tego wpisu</label></td>
				</tr>
			</table>
			<?php submit_button( $edytowany ? 'Zapisz zmiany' : 'Dodaj wpis' ); ?>
			<?php if ( $edytowany ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-qa' ) ); ?>">Anuluj edycję</a>
			<?php endif; ?>
		</form>

		<h2>Wpisy (<?php echo count( $wpisy ); ?>)</h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Pytanie wzorcowe</th><th>Słowa kluczowe</th><th>Odpowiedź</th><th>Aktywny</th><th>Akcje</th></tr></thead>
			<tbody>
			<?php if ( ! $wpisy ) : ?>
				<tr><td colspan="6">Baza jest pusta — dodaj pierwszy wpis powyżej.</td></tr>
			<?php endif; ?>
			<?php foreach ( $wpisy as $w ) : ?>
				<tr>
					<td><?php echo (int) $w->id; ?></td>
					<td><?php echo esc_html( $w->pytanie ); ?></td>
					<td><?php echo esc_html( $w->slowa_kluczowe ); ?></td>
					<td><?php echo esc_html( wp_html_excerpt( $w->odpowiedz, 120, '…' ) ); ?></td>
					<td><?php echo $w->aktywne ? 'tak' : '<strong>nie</strong>'; ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-qa&edytuj=' . (int) $w->id ) ); ?>">Edytuj</a> |
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_qa_usun&id=' . (int) $w->id ), 'chatl_qa_usun_' . (int) $w->id ) ); ?>"
							onclick="return confirm('Usunąć ten wpis?');">Usuń</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Test bota na własnej bazie, bez wychodzenia na stronę.
 *
 * Pokazuje NIE TYLKO odpowiedź, ale i KTÓRA para ją dała (id + pytanie
 * wzorcowe + dopasowane warianty) — dopasowanie przestaje być czarną skrzynką
 * i widać, dlaczego bot odpowiedział tak, a nie inaczej.
 *
 * Formularz jest GET-em na tę samą stronę: to odczyt, nic nie zmienia w bazie,
 * więc nie ma tu mutacji do zabezpieczania noncem (capability pilnuje
 * add_menu_page: manage_options).
 */
function chatl_panel_testu_bota() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- formularz tylko odczytuje; brak mutacji.
	$pytanie = isset( $_GET['test'] ) ? sanitize_text_field( wp_unslash( $_GET['test'] ) ) : '';
	// phpcs:enable
	$ust = chatl_ustawienia();
	?>
	<div class="card" style="max-width:100%;padding:12px 16px">
		<h2 style="margin-top:0">Test bota</h2>
		<p class="description">Wpisz pytanie tak, jak zrobiłby to gość — zobaczysz odpowiedź i <strong>która para zadziałała</strong>.</p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="chatl-qa">
			<input type="text" name="test" class="regular-text" placeholder="np. ile kosztuje wasza usługa"
				value="<?php echo esc_attr( $pytanie ); ?>" aria-label="Pytanie do testu bota">
			<?php submit_button( 'Sprawdź odpowiedź', 'secondary', 'submit', false ); ?>
			<?php if ( '' !== $pytanie ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-qa' ) ); ?>">wyczyść</a>
			<?php endif; ?>
		</form>
		<?php if ( '' !== $pytanie ) : ?>
			<?php $wynik = chatl_dopasuj( $pytanie ); ?>
			<?php if ( null === $wynik ) : ?>
				<p><strong>Żadna para nie pasuje.</strong> Gość dostałby tekst awaryjny i propozycję zostawienia kontaktu:</p>
				<blockquote style="margin:0 0 8px;padding:8px 12px;border-left:4px solid #d63638;background:#fff"><?php echo esc_html( $ust['tekst_awaryjny'] ); ?></blockquote>
				<p class="description">Chcesz, żeby bot to łapał? Dopisz wariant do słów kluczowych którejś pary powyżej.</p>
			<?php else : ?>
				<?php $para = chatl_wpis_qa( (int) $wynik['id'] ); ?>
				<p>Odpowiedź, którą dostałby gość:</p>
				<blockquote style="margin:0 0 8px;padding:8px 12px;border-left:4px solid #00a32a;background:#fff"><?php echo esc_html( $wynik['odpowiedz'] ); ?></blockquote>
				<?php if ( $para ) : ?>
					<p>Zadziałała para <strong>#<?php echo (int) $para->id; ?></strong> — „<?php echo esc_html( $para->pytanie ); ?>"
						(<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-qa&edytuj=' . (int) $para->id ) ); ?>">edytuj</a>).<br>
						<?php // Warianty bierzemy z wyniku SILNIKA, a nie liczymy drugi raz — panel nie ma własnej wersji dopasowania. ?>
						Dopasowane warianty: <code><?php echo esc_html( implode( ', ', $wynik['trafione'] ) ); ?></code></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( chatl_ai_dostepny() ) : ?>
				<p class="description"><strong>Uwaga:</strong> tryb AI jest włączony, więc gość najpierw dostaje odpowiedź modelu — ten test pokazuje silnik regułowy, czyli to, co odpowie bot bez klucza, po limicie albo przy błędzie API. Test celowo NIE wywołuje API (nie przepala budżetu).</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Pojedynczy wpis bazy Q&A (do pokazania, która para zadziałała).
 *
 * @param int $id Identyfikator wpisu.
 * @return object|null
 */
function chatl_wpis_qa( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}chatl_qa WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

function chatl_post_qa_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_qa_zapisz' );

	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_qa';

	$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$dane = array(
		'pytanie'        => isset( $_POST['pytanie'] ) ? sanitize_text_field( wp_unslash( $_POST['pytanie'] ) ) : '',
		'slowa_kluczowe' => isset( $_POST['slowa_kluczowe'] ) ? sanitize_text_field( wp_unslash( $_POST['slowa_kluczowe'] ) ) : '',
		'odpowiedz'      => isset( $_POST['odpowiedz'] ) ? sanitize_textarea_field( wp_unslash( $_POST['odpowiedz'] ) ) : '',
		'aktywne'        => empty( $_POST['aktywne'] ) ? 0 : 1,
	);
	if ( '' === $dane['pytanie'] || '' === $dane['slowa_kluczowe'] || '' === $dane['odpowiedz'] ) {
		wp_die( 'Wszystkie pola są wymagane.' );
	}

	if ( $id > 0 ) {
		$wynik = $wpdb->update( $tabela, $dane, array( 'id' => $id ), array( '%s', '%s', '%s', '%d' ), array( '%d' ) );
	} else {
		$dane['utworzono'] = current_time( 'mysql' );
		$wynik             = $wpdb->insert( $tabela, $dane, array( '%s', '%s', '%s', '%d', '%s' ) );
	}
	if ( false === $wynik ) {
		chatl_log_bledu_bazy( 'chatl_post_qa_zapisz', 'wpis bazy Q&A (id: ' . $id . ')' );
		chatl_przekieruj_blad( 'chatl-qa', 'Nie udało się zapisać wpisu — baza odrzuciła zapis. Wpis NIE został zachowany; spróbuj ponownie.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa&chatl_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_chatl_qa_zapisz', 'chatl_post_qa_zapisz' );

function chatl_post_qa_usun() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'chatl_qa_usun_' . $id );

	global $wpdb;
	if ( false === $wpdb->delete( $wpdb->prefix . 'chatl_qa', array( 'id' => $id ), array( '%d' ) ) ) {
		chatl_log_bledu_bazy( 'chatl_post_qa_usun', 'usunięcie wpisu Q&A (id: ' . $id . ')' );
		chatl_przekieruj_blad( 'chatl-qa', 'Nie udało się usunąć wpisu — baza odrzuciła operację. Wpis NADAL jest w bazie.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa&chatl_ok=usunieto' ) );
	exit;
}
add_action( 'admin_post_chatl_qa_usun', 'chatl_post_qa_usun' );

/* ===================== ZGŁOSZENIA ===================== */

function chatl_strona_zgloszenia() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_zgloszenia';
	$wpisy  = $wpdb->get_results( "SELECT * FROM {$tabela} ORDER BY id DESC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="wrap">
		<h1>Zgłoszenia z czatu</h1>
		<?php chatl_komunikat_ok(); ?>
		<p>Goście, którym bot nie odpowiedział (albo którzy od razu chcieli kontaktu) — <strong>do odpisania na żywo</strong>.</p>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_eksport_zgloszen' ), 'chatl_eksport_zgloszen' ) ); ?>">Pobierz CSV (wszystkie zgłoszenia)</a>
			<span class="description">Kopia dla Ciebie — bot jej nigdzie nie wysyła i nie robi jej sam z siebie.</span></p>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Data</th><th>E-mail gościa</th><th>Wiadomość</th><th>Status</th><th>Akcje</th></tr></thead>
			<tbody>
			<?php if ( ! $wpisy ) : ?>
				<tr><td colspan="6">Brak zgłoszeń.</td></tr>
			<?php endif; ?>
			<?php foreach ( $wpisy as $z ) : ?>
				<tr>
					<td><?php echo (int) $z->id; ?></td>
					<td><?php echo esc_html( $z->utworzono ); ?></td>
					<td><a href="mailto:<?php echo esc_attr( $z->email ); ?>"><?php echo esc_html( $z->email ); ?></a></td>
					<td><?php echo esc_html( $z->wiadomosc ); ?></td>
					<td><?php echo 'nowe' === $z->status ? '<strong>nowe</strong>' : esc_html( $z->status ); ?></td>
					<td>
						<?php $nowy_status = 'nowe' === $z->status ? 'obsluzone' : 'nowe'; ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_zgloszenie_status&id=' . (int) $z->id . '&status=' . $nowy_status ), 'chatl_zgloszenie_status_' . (int) $z->id ) ); ?>">
							<?php echo 'obsluzone' === $nowy_status ? 'Oznacz obsłużone' : 'Oznacz jako nowe'; ?></a> |
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_zgloszenie_usun&id=' . (int) $z->id ), 'chatl_zgloszenie_usun_' . (int) $z->id ) ); ?>"
							onclick="return confirm('Usunąć to zgłoszenie?');">Usuń</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function chatl_post_zgloszenie_status() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'chatl_zgloszenie_status_' . $id );

	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	if ( ! in_array( $status, array( 'nowe', 'obsluzone' ), true ) ) {
		wp_die( 'Nieznany status.' );
	}

	global $wpdb;
	if ( false === $wpdb->update( $wpdb->prefix . 'chatl_zgloszenia', array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ) ) {
		chatl_log_bledu_bazy( 'chatl_post_zgloszenie_status', 'zmiana statusu zgłoszenia (id: ' . $id . ')' );
		chatl_przekieruj_blad( 'chatl-zgloszenia', 'Nie udało się zmienić statusu — baza odrzuciła zapis. Status pozostał bez zmian.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-zgloszenia&chatl_ok=zmieniono' ) );
	exit;
}
add_action( 'admin_post_chatl_zgloszenie_status', 'chatl_post_zgloszenie_status' );

function chatl_post_zgloszenie_usun() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'chatl_zgloszenie_usun_' . $id );

	global $wpdb;
	if ( false === $wpdb->delete( $wpdb->prefix . 'chatl_zgloszenia', array( 'id' => $id ), array( '%d' ) ) ) {
		chatl_log_bledu_bazy( 'chatl_post_zgloszenie_usun', 'usunięcie zgłoszenia (id: ' . $id . ')' );
		chatl_przekieruj_blad( 'chatl-zgloszenia', 'Nie udało się usunąć zgłoszenia — baza odrzuciła operację. Zgłoszenie NADAL jest w bazie.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-zgloszenia&chatl_ok=usunieto' ) );
	exit;
}
add_action( 'admin_post_chatl_zgloszenie_usun', 'chatl_post_zgloszenie_usun' );

/* ===================== HISTORIA ROZMÓW ===================== */

function chatl_strona_historia() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_rozmowy';

	// Self-heal retencji: WP-cron to pseudo-cron (bez ruchu nie odpala), więc
	// wejście admina na Historię też czyści wpisy starsze niż okres retencji (idempotentne).
	chatl_czyszczenie_historii();

	$sesja = '';
	if ( isset( $_GET['sesja'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sesja = chatl_waliduj_sesje( wp_unslash( $_GET['sesja'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	if ( '' !== $sesja ) {
		$wymiany = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE sesja = %s ORDER BY id ASC", $sesja ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap">
			<h1>Rozmowa <code><?php echo esc_html( $sesja ); ?></code></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-historia' ) ); ?>">← wróć do listy rozmów</a> |
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_eksport_rozmow&sesja=' . rawurlencode( $sesja ) ), 'chatl_eksport_rozmow' ) ); ?>">Pobierz CSV tej rozmowy</a></p>
			<table class="widefat striped">
				<thead><tr><th>Czas</th><th>Kto</th><th>Treść</th></tr></thead>
				<tbody>
				<?php foreach ( $wymiany as $w ) : ?>
					<tr>
						<td><?php echo esc_html( $w->utworzono ); ?></td>
						<td><?php echo 'gosc' === $w->rola ? 'Gość' : ( 'ai' === $w->silnik ? 'Bot (AI)' : 'Bot (reguły)' ); ?></td>
						<td><?php echo esc_html( $w->tresc ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return;
	}

	$rozmowy = $wpdb->get_results( "SELECT sesja, COUNT(*) AS wymian, MIN(utworzono) AS poczatek, MAX(utworzono) AS koniec FROM {$tabela} GROUP BY sesja ORDER BY koniec DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="wrap">
		<h1>Historia rozmów</h1>
		<p>Zapis wymian bot↔gość (ostatnie 100 rozmów). Historia starsza niż <strong><?php echo (int) CHATL_RETENCJA_DNI; ?> dni</strong> jest czyszczona automatycznie.</p>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_eksport_rozmow' ), 'chatl_eksport_rozmow' ) ); ?>">Pobierz CSV (cała historia)</a>
			<span class="description">Chcesz zachować rozmowy dłużej niż <?php echo (int) CHATL_RETENCJA_DNI; ?> dni? Pobierz je sam, zanim zostaną skasowane — wtyczka <strong>nie</strong> robi kopii automatycznie, bo wtedy „kasujemy po <?php echo (int) CHATL_RETENCJA_DNI; ?> dniach" przestałoby być prawdą.</span></p>
		<table class="widefat striped">
			<thead><tr><th>Sesja</th><th>Wymian</th><th>Początek</th><th>Ostatnia aktywność</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $rozmowy ) : ?>
				<tr><td colspan="5">Brak zapisanych rozmów.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rozmowy as $r ) : ?>
				<tr>
					<td><code><?php echo esc_html( $r->sesja ); ?></code></td>
					<td><?php echo (int) $r->wymian; ?></td>
					<td><?php echo esc_html( $r->poczatek ); ?></td>
					<td><?php echo esc_html( $r->koniec ); ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-historia&sesja=' . rawurlencode( $r->sesja ) ) ); ?>">Zobacz rozmowę</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/* ===================== USTAWIENIA ===================== */

function chatl_strona_ustawienia() {
	$ust = chatl_ustawienia();
	$wyg = chatl_wyglad_ustawienia( $ust );
	?>
	<div class="wrap">
		<h1>Ustawienia ChatBota</h1>
		<?php chatl_komunikat_ok(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="chatl_ustawienia_zapisz">
			<?php wp_nonce_field( 'chatl_ustawienia_zapisz' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Widget czatu</th>
					<td><label><input type="checkbox" name="wlaczony" value="1" <?php checked( $ust['wlaczony'] ); ?>> pokazuj bąbelek czatu na stronie</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-powitanie">Tekst powitania</label></th>
					<td><textarea id="chatl-powitanie" name="powitanie" class="large-text" rows="3"><?php echo esc_textarea( $ust['powitanie'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-awaryjny">Tekst awaryjny (brak odpowiedzi)</label></th>
					<td><textarea id="chatl-awaryjny" name="tekst_awaryjny" class="large-text" rows="3"><?php echo esc_textarea( $ust['tekst_awaryjny'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-mail">Mail powiadomień o zgłoszeniach</label></th>
					<td>
						<input type="email" id="chatl-mail" name="mail_powiadomien" class="regular-text" value="<?php echo esc_attr( $ust['mail_powiadomien'] ); ?>">
						<p class="description"><label><input type="checkbox" name="powiadomienia" value="1" <?php checked( $ust['powiadomienia'] ); ?>> wysyłaj mail przy każdym nowym zgłoszeniu</label></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-polityka">Adres polityki prywatności</label></th>
					<td>
						<input type="url" id="chatl-polityka" name="adres_polityki" class="regular-text" placeholder="https://twojafirma.pl/polityka-prywatnosci"
							value="<?php echo esc_attr( $ust['adres_polityki'] ); ?>">
						<p class="description">
							Link pokazywany gościowi przy formularzu kontaktu, obok informacji, co zbieramy (e-mail i treść pytania), po co (odpowiedź na pytanie) i jak długo (<?php echo (int) CHATL_RETENCJA_DNI; ?> dni). Puste pole = sama informacja, bez linku.
							<?php $polityka_wp = get_privacy_policy_url(); ?>
							<?php if ( '' !== $polityka_wp && $polityka_wp !== $ust['adres_polityki'] ) : ?>
								<br>Ten WordPress ma ustawioną stronę polityki prywatności: <code><?php echo esc_html( $polityka_wp ); ?></code> — możesz wkleić ten adres.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr><th colspan="2"><h2>Wygląd widgetu</h2>
					<p style="font-weight:normal">Kolory bąbelka i paska nagłówka oraz róg, w którym stoi widget. Domyślne wartości to paleta, z którą wtyczka jest sprzedawana — puste pole wraca do niej.</p></th></tr>
				<tr>
					<th scope="row"><label for="chatl-kolor-glowny">Kolor główny</label></th>
					<td>
						<input type="color" id="chatl-kolor-glowny" name="kolor_glowny" value="<?php echo esc_attr( $wyg['kolor_glowny'] ); ?>">
						<code id="chatl-kolor-glowny-kod"><?php echo esc_html( $wyg['kolor_glowny'] ); ?></code>
						<p class="description">Bąbelek czatu, dymki bota, przyciski i obwódka fokusu.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-kolor-naglowka">Kolor paska nagłówka</label></th>
					<td>
						<input type="color" id="chatl-kolor-naglowka" name="kolor_naglowka" value="<?php echo esc_attr( $wyg['kolor_naglowka'] ); ?>">
						<code id="chatl-kolor-naglowka-kod"><?php echo esc_html( $wyg['kolor_naglowka'] ); ?></code>
						<p class="description">Pasek z tytułem okna czatu i paski formularza kontaktu.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Kolor tekstu na tych kolorach</th>
					<td>
						<label><input type="checkbox" id="chatl-tekst-auto" name="kolor_tekstu_auto" value="1" <?php checked( 'auto' === $wyg['kolor_tekstu'] ); ?>>
							<strong>dobierz automatycznie</strong> (zalecane — wtyczka wybiera ciemny albo jasny, ten z lepszym kontrastem)</label>
						<p style="margin:8px 0 0">
							<input type="color" id="chatl-kolor-tekstu" name="kolor_tekstu" value="<?php echo esc_attr( 'auto' === $wyg['kolor_tekstu'] ? CHATL_KOLOR_CIEMNY : $wyg['kolor_tekstu'] ); ?>">
							<span class="description">używany, gdy powyższe jest odznaczone</span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Pozycja widgetu</th>
					<td>
						<label><input type="radio" name="pozycja" value="prawo" <?php checked( 'prawo', $wyg['pozycja'] ); ?>> prawy dolny róg</label><br>
						<label><input type="radio" name="pozycja" value="lewo" <?php checked( 'lewo', $wyg['pozycja'] ); ?>> lewy dolny róg</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Podgląd</th>
					<td>
						<?php chatl_podglad_wygladu(); ?>
					</td>
				</tr>

				<tr><th colspan="2"><h2>Tryb AI (hybryda)</h2>
					<p style="font-weight:normal">Z kluczem API odpowiedzi generuje prawdziwy model (Claude) na podstawie informacji o firmie i bazy Q&amp;A — z zakazem zgadywania (niepewny zbiera kontakt). Bez klucza, po wyczerpaniu limitu albo przy błędzie API bot działa w trybie regułowym.</p></th></tr>
				<tr>
					<th scope="row"><label for="chatl-klucz">Klucz API (Anthropic)</label></th>
					<td>
						<?php $klucz = chatl_klucz_api(); ?>
						<?php if ( chatl_klucz_ze_stalej() ) : ?>
							<p><strong>Klucz podany w konfiguracji serwera</strong> (…<?php echo esc_html( substr( $klucz, -4 ) ); ?>) — stałą <code>CHATL_KLUCZ_API</code> w <code>wp-config.php</code>.</p>
							<input type="password" id="chatl-klucz" class="regular-text" value="" autocomplete="off" disabled
								placeholder="klucz z wp-config.php">
							<p class="description">To pole jest zablokowane, bo stała ma pierwszeństwo nad bazą. Żeby zmienić albo wyłączyć klucz, popraw <code>wp-config.php</code>. Klucz trzymany poza bazą nie wychodzi z kopią zapasową bazy — to zalecany sposób.</p>
						<?php else : ?>
							<?php if ( '' !== $klucz ) : ?>
								<p><strong>Klucz zapisany</strong> (…<?php echo esc_html( substr( $klucz, -4 ) ); ?>).
									<label><input type="checkbox" name="usun_klucz" value="1"> usuń klucz (wyłącza tryb AI)</label></p>
							<?php endif; ?>
							<input type="password" id="chatl-klucz" name="klucz_api" class="regular-text" value="" autocomplete="off"
								placeholder="<?php echo '' !== $klucz ? 'wklej, aby podmienić' : 'sk-ant-…'; ?>">
							<p class="description">Klucz WŁAŚCICIELA strony — przechowywany tylko w Twojej bazie (bez autoload), nigdzie nie wysyłany poza api.anthropic.com.<br>
								Bezpieczniej: wpisz go stałą <code>define( 'CHATL_KLUCZ_API', 'sk-ant-…' );</code> w <code>wp-config.php</code> — wtedy klucz nie leży w bazie i nie wychodzi z jej kopią zapasową. Stała ma pierwszeństwo nad tym polem.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-ai-model">Model</label></th>
					<td>
						<?php
						$modele  = chatl_ai_modele();
						$biezacy = chatl_ai_model();
						$znany   = isset( $modele[ $biezacy ] );
						?>
						<select id="chatl-ai-model" name="ai_model_lista">
							<?php foreach ( $modele as $id => $opis ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $znany && $biezacy === $id ); ?>><?php echo esc_html( $opis ); ?></option>
							<?php endforeach; ?>
							<option value="wlasny" <?php selected( ! $znany ); ?>>własny — wpisz identyfikator poniżej</option>
						</select>
						<p style="margin:8px 0 0">
							<input type="text" id="chatl-ai-model-wlasny" name="ai_model_wlasny" class="regular-text" placeholder="np. claude-haiku-4-5"
								value="<?php echo esc_attr( $znany ? '' : $biezacy ); ?>">
							<span class="description">używane, gdy wybierzesz „własny"</span>
						</p>
						<p class="description">
							Teraz w użyciu: <code><?php echo esc_html( $biezacy ); ?></code>.
							<strong>Jeśli dostawca udostępnia identyfikator bez daty — użyj właśnie takiego</strong>
							(np. <code>claude-haiku-4-5</code> zamiast <code>claude-haiku-4-5-20251001</code>): nazwy z datą wskazują konkretną
							migawkę modelu, a te bywają wycofywane i wtedy tryb AI przestaje działać. Nie każdy model ma taki skrócony
							identyfikator — jeżeli dostawca podaje wyłącznie nazwę z datą, wpisz ją i pilnuj komunikatów o wycofaniu.<br>
							Aktualną listę modeli i cennik sprawdzisz u dostawcy: <a href="https://docs.anthropic.com/en/docs/about-claude/models" target="_blank" rel="noopener">dokumentacja modeli Anthropic</a>.
							Kolejność na liście to „taniej ↔ mocniej"; do odpowiadania z bazy Q&amp;A najtańszy model zwykle w zupełności wystarcza.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Sprawdzenie klucza</th>
					<td>
						<?php // Odnośnik, a nie przycisk w formularzu: przycisk wysłałby CAŁE ustawienia, a to ma być samo sprawdzenie. ?>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_test_klucza' ), 'chatl_test_klucza' ) ); ?>">Przetestuj klucz</a>
						<p class="description">Jedno tanie wywołanie API (jedno słowo, <code>max_tokens = 1</code>) — sprawdza klucz, połączenie i nazwę modelu, nie zużywa dziennego limitu.
							Wynik pojawi się na górze tej strony.
							<?php $blad_ai = get_option( 'chatl_blad_ai' ); ?>
							<?php if ( is_array( $blad_ai ) && ! empty( $blad_ai['kiedy'] ) ) : ?>
								<br><strong style="color:#d63638">Ostatnie wywołanie API nie powiodło się</strong> (<?php echo esc_html( (string) $blad_ai['kiedy'] ); ?>) — bot odpowiada teraz wyłącznie z reguł.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-ai-info">Informacje o firmie dla AI</label></th>
					<td>
						<textarea id="chatl-ai-info" name="ai_informacje" class="large-text" rows="6" placeholder="Opisz firmę własnymi słowami: co robicie, ceny, godziny, adres…"><?php echo esc_textarea( $ust['ai_informacje'] ); ?></textarea>
						<p class="description">Model odpowiada TYLKO na podstawie tego opisu i aktywnych wpisów bazy Q&amp;A.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-ai-limit">Dzienny limit wywołań AI (stop-loss)</label></th>
					<td>
						<input type="number" id="chatl-ai-limit" name="ai_limit_dzienny" min="1" max="100000" value="<?php echo (int) $ust['ai_limit_dzienny']; ?>">
						<p class="description">Dzisiaj zużyto: <strong><?php echo (int) chatl_ai_uzycie_dzis(); ?> / <?php echo (int) $ust['ai_limit_dzienny']; ?></strong> wywołań. Po wyczerpaniu limitu bot do północy odpowiada z reguł. Limit jest miękki: równoległe zapytania gości mogą go minimalnie przekroczyć.<br>
							Ten limit jest <strong>globalny dla całej strony</strong>. Osobno działa twardszy limit <strong><?php echo (int) CHATL_AI_LIMIT_IP_NA_DOBE; ?> wywołań AI na dobę z jednego adresu IP</strong> — żeby jeden gość nie wypalił budżetu wszystkich. Po jego przekroczeniu ten gość dostaje odpowiedzi z reguł, reszta strony działa normalnie.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-ai-limit-tokenow">Dobowy limit tokenów (drugi stop-loss)</label></th>
					<td>
						<?php $zuzycie = chatl_ai_zuzycie_dnia(); ?>
						<input type="number" id="chatl-ai-limit-tokenow" name="ai_limit_tokenow" min="0" max="100000000" step="1000"
							value="<?php echo (int) $ust['ai_limit_tokenow']; ?>">
						<p class="description">
							Dzisiaj zużyto: <strong><?php echo esc_html( number_format_i18n( chatl_ai_tokeny_dzis() ) ); ?></strong>
							<?php if ( (int) $ust['ai_limit_tokenow'] > 0 ) : ?>
								/ <?php echo esc_html( number_format_i18n( (int) $ust['ai_limit_tokenow'] ) ); ?>
							<?php endif; ?>
							tokenów płatnych
							(wejście <?php echo esc_html( number_format_i18n( $zuzycie['tokeny_wejscia'] ) ); ?>,
							wyjście <?php echo esc_html( number_format_i18n( $zuzycie['tokeny_wyjscia'] ) ); ?>,
							zapis do cache <?php echo esc_html( number_format_i18n( $zuzycie['tokeny_cache_zapis'] ) ); ?>;
							odczyt z cache <?php echo esc_html( number_format_i18n( $zuzycie['tokeny_cache_odczyt'] ) ); ?> — tańszy, liczony osobno).<br>
							<strong>Rachunek u dostawcy idzie z tokenów, nie z liczby wywołań.</strong> Przy dużej bazie Q&amp;A
							jedno pytanie potrafi kosztować wielokrotność innego, a limit wywołań tego w ogóle nie widzi —
							dlatego ten drugi limit istnieje. Po jego wyczerpaniu bot do północy odpowiada z reguł, tak samo
							jak po wyczerpaniu limitu wywołań. <strong>0 = wyłączony.</strong>
							Limit jest miękki — tak samo jak limit wywołań: równoległe zapytania gości mogą go minimalnie przekroczyć,
							bo zużycie zapisujemy po odpowiedzi API, a nie przed nią.<br>
							Aktualne stawki za tokeny sprawdzisz u dostawcy — wtyczka celowo nie podaje kwot, bo cennik się zmienia,
							a ona nie ma jak go odświeżyć.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Strona za Cloudflare albo proxy</th>
					<td>
						<label><input type="checkbox" name="za_proxy" value="1" <?php checked( $ust['za_proxy'] ); ?>>
							ustalaj adres gościa z nagłówków proxy (<code>CF-Connecting-IP</code>, <code>X-Forwarded-For</code>)</label>
						<p class="description">
							<strong>Włącz tylko wtedy, gdy Twoja strona naprawdę stoi za Cloudflare albo za proxy hostingu.</strong>
							Bez tego wtyczka widzi adres proxy zamiast adresu gościa, więc limit <?php echo (int) CHATL_LIMIT_NA_MIN; ?> wiadomości na minutę
							obowiązuje <strong>całą witrynę naraz</strong>, a dobowy limit <?php echo (int) CHATL_AI_LIMIT_IP_NA_DOBE; ?> wywołań AI na adres staje się limitem na całą stronę.<br>
							Domyślnie wyłączone, bo te nagłówki da się <strong>podrobić</strong>: na stronie bez proxy zaufanie im oznaczałoby,
							że każdy obchodzi limity, podając za każdym razem inny adres. Nie wiesz? Zostaw wyłączone — limity będą co najwyżej surowsze.<br>
							<strong>Sam Cloudflare to za mało.</strong> Jeżeli do Twojego serwera da się dobić <strong>z pominięciem</strong> proxy
							(wpisując bezpośrednio jego adres IP), podrobiony nagłówek nadal obchodzi limit — bo żądanie nie przechodzi wtedy przez
							Cloudflare, który normalnie ten nagłówek nadpisuje. Poproś hosting o ograniczenie ruchu do adresów Cloudflare
							albo zostaw przełącznik wyłączony.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Usuwanie wtyczki</th>
					<td>
						<label><input type="checkbox" name="usun_dane" value="1" <?php checked( $ust['usun_dane'] ); ?>> przy odinstalowaniu usuń WSZYSTKIE dane (baza Q&A, zgłoszenia, historia, ustawienia)</label>
						<p class="description">Domyślnie dane zostają — możesz odinstalować i wrócić bez strat.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Zapisz ustawienia' ); ?>
		</form>
		<h2>Prywatność</h2>
		<p>Tryb regułowy: treść rozmów <strong>nie opuszcza</strong> Twojego WordPressa — dopasowanie działa lokalnie. Tryb AI: treść pytania gościa (wraz z informacjami o firmie i bazą Q&amp;A) jest wysyłana do API dostawcy modelu (Anthropic) — poinformuj o tym w swojej polityce prywatności. Historia rozmów jest przechowywana <?php echo (int) CHATL_RETENCJA_DNI; ?> dni.</p>
		<p>Formularz kontaktu ma <strong>wymagany</strong> checkbox zgody na przetwarzanie danych — sprawdzany także po stronie serwera, więc zgłoszenie bez zgody nie trafia do bazy. Sam zapisany wiersz jest więc dowodem, że zgoda była.</p>
	</div>
	<?php
}

/**
 * Podgląd widgetu w panelu + odczyt kontrastu WCAG.
 *
 * Podgląd to ten sam mechanizm co na froncie — zmienne CSS na `.chatl-korzen` —
 * więc nie jest osobną makietą, która mogłaby pokazywać co innego niż widget.
 * Wartości startowe rysuje PHP; po każdej zmianie pola przelicza je
 * assets/panel-wyglad.js, żeby właściciel widział wybór PRZED zapisem.
 *
 * @return void
 */
function chatl_podglad_wygladu() {
	$z     = chatl_zmienne_wygladu();
	$ocena = chatl_ocena_kontrastu();
	$style = '';
	foreach ( $z as $nazwa => $wartosc ) {
		$style .= $nazwa . ':' . $wartosc . ';';
	}
	?>
	<div id="chatl-podglad" style="<?php echo esc_attr( $style ); ?>display:inline-block;padding:14px;border:1px solid #c3c4c7;background:#101116;border-radius:8px">
		<div style="width:240px;border-radius:8px;overflow:hidden;border:1px solid rgba(245,245,245,.12)">
			<div id="chatl-podglad-naglowek" style="background:var(--chatl-naglowek);color:var(--chatl-naglowek-tekst);padding:8px 10px;font-size:13px">Napisz do nas</div>
			<div style="background:#101116;padding:10px">
				<div id="chatl-podglad-dymek" style="background:var(--chatl-glowny);color:var(--chatl-glowny-tekst);border-radius:12px;padding:8px 12px;font-size:13px;display:inline-block">Dzień dobry! W czym pomóc?</div>
			</div>
		</div>
		<div id="chatl-podglad-babelek" style="width:44px;height:44px;border-radius:50%;background:var(--chatl-glowny);color:var(--chatl-glowny-tekst);border:2px solid var(--chatl-glowny-tekst);margin-top:10px;display:flex;align-items:center;justify-content:center;font-size:20px">💬</div>
	</div>
	<div id="chatl-kontrast" style="margin-top:10px">
		<?php foreach ( $ocena as $poz ) : ?>
			<p style="margin:4px 0" data-chatl-para="<?php echo esc_attr( 'Bąbelek i przyciski' === $poz['etykieta'] ? 'glowny' : 'naglowek' ); ?>">
				<strong><?php echo esc_html( $poz['etykieta'] ); ?>:</strong>
				kontrast <span class="chatl-kontrast-wartosc"><?php echo esc_html( number_format_i18n( $poz['kontrast'], 2 ) ); ?></span>:1 —
				<span class="chatl-kontrast-ocena" style="font-weight:600;color:<?php echo $poz['ok'] ? '#00a32a' : '#d63638'; ?>">
					<?php echo $poz['ok'] ? 'spełnia WCAG AA' : 'PONIŻEJ progu WCAG AA'; ?>
				</span>
			</p>
		<?php endforeach; ?>
		<p class="description">Próg to <?php echo esc_html( number_format_i18n( CHATL_WCAG_PROG, 1 ) ); ?>:1 dla zwykłego tekstu (WCAG 2.1, kryterium 1.4.3, poziom AA).
			Poniżej progu tekst na bąbelku bywa nieczytelny — zostaw „dobierz automatycznie", a wtyczka pilnuje tego za Ciebie.</p>
	</div>
	<?php
}

/**
 * Skrypt podglądu — TYLKO na stronie ustawień wtyczki.
 *
 * @param string $hak Identyfikator ekranu panelu.
 * @return void
 */
function chatl_assety_panelu( $hak ) {
	if ( false === strpos( (string) $hak, 'chatl-ustawienia' ) ) {
		return;
	}
	wp_enqueue_script( 'chatl-panel-wyglad', CHATL_URL . 'assets/panel-wyglad.js', array(), CHATL_WERSJA, true );
	wp_localize_script(
		'chatl-panel-wyglad',
		'chatlWyglad',
		array(
			'prog'    => CHATL_WCAG_PROG,
			'ciemny'  => CHATL_KOLOR_CIEMNY,
			'jasny'   => CHATL_KOLOR_JASNY,
			// Separator dziesiętny bierzemy z WordPressa, żeby liczba policzona
			// w przeglądarce wyglądała TAK SAMO jak ta narysowana przez PHP —
			// inaczej po zapisie „3,45" zmieniałoby się w „3.45" i właściciel
			// miałby prawo sądzić, że coś się przeliczyło inaczej.
			'sep'     => false !== strpos( number_format_i18n( 1.5, 1 ), ',' ) ? ',' : '.',
			'zgodny'  => 'spełnia WCAG AA',
			'niezgod' => 'PONIŻEJ progu WCAG AA',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'chatl_assety_panelu' );

function chatl_post_ustawienia_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_ustawienia_zapisz' );

	$stare = chatl_ustawienia();
	$nowe  = array(
		'wlaczony'         => empty( $_POST['wlaczony'] ) ? 0 : 1,
		'powitanie'        => isset( $_POST['powitanie'] ) ? sanitize_textarea_field( wp_unslash( $_POST['powitanie'] ) ) : $stare['powitanie'],
		'tekst_awaryjny'   => isset( $_POST['tekst_awaryjny'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tekst_awaryjny'] ) ) : $stare['tekst_awaryjny'],
		'mail_powiadomien' => isset( $_POST['mail_powiadomien'] ) ? sanitize_email( wp_unslash( $_POST['mail_powiadomien'] ) ) : $stare['mail_powiadomien'],
		'powiadomienia'    => empty( $_POST['powiadomienia'] ) ? 0 : 1,
		'usun_dane'        => empty( $_POST['usun_dane'] ) ? 0 : 1,
		'ai_informacje'    => isset( $_POST['ai_informacje'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_informacje'] ) ) : $stare['ai_informacje'],
		'ai_limit_dzienny' => isset( $_POST['ai_limit_dzienny'] ) ? max( 1, min( 100000, (int) $_POST['ai_limit_dzienny'] ) ) : $stare['ai_limit_dzienny'],
		'adres_polityki'   => isset( $_POST['adres_polityki'] ) ? esc_url_raw( wp_unslash( $_POST['adres_polityki'] ) ) : $stare['adres_polityki'],
		// Wygląd: kolor z niepoprawną wartością NIE psuje widgetu — sanitizer
		// zwraca pusty string, a chatl_wyglad_ustawienia() podstawia domyślny.
		'kolor_glowny'     => isset( $_POST['kolor_glowny'] ) ? chatl_sanitize_kolor( wp_unslash( $_POST['kolor_glowny'] ) ) : $stare['kolor_glowny'],
		'kolor_naglowka'   => isset( $_POST['kolor_naglowka'] ) ? chatl_sanitize_kolor( wp_unslash( $_POST['kolor_naglowka'] ) ) : $stare['kolor_naglowka'],
		'kolor_tekstu'     => empty( $_POST['kolor_tekstu_auto'] ) && isset( $_POST['kolor_tekstu'] )
			? chatl_sanitize_kolor( wp_unslash( $_POST['kolor_tekstu'] ) )
			: 'auto',
		'pozycja'          => isset( $_POST['pozycja'] ) && 'lewo' === sanitize_key( wp_unslash( $_POST['pozycja'] ) ) ? 'lewo' : 'prawo',
		'ai_limit_tokenow' => isset( $_POST['ai_limit_tokenow'] ) ? max( 0, min( 100000000, (int) $_POST['ai_limit_tokenow'] ) ) : $stare['ai_limit_tokenow'],
		'ai_model'         => chatl_model_z_formularza( $stare['ai_model'] ),
		'za_proxy'         => empty( $_POST['za_proxy'] ) ? 0 : 1,
	);

	// Klucz API: osobna opcja (autoload=no), nigdy nie wraca do formularza.
	if ( ! empty( $_POST['usun_klucz'] ) ) {
		chatl_zapisz_klucz( '' );
	} elseif ( isset( $_POST['klucz_api'] ) && '' !== trim( (string) wp_unslash( $_POST['klucz_api'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- warunek tylko bada niepustość; zapisywana wartość przechodzi sanitize_text_field() linijkę niżej.
		chatl_zapisz_klucz( sanitize_text_field( wp_unslash( $_POST['klucz_api'] ) ) );
	}
	if ( '' === $nowe['powitanie'] ) {
		$nowe['powitanie'] = $stare['powitanie'];
	}
	if ( '' === $nowe['tekst_awaryjny'] ) {
		$nowe['tekst_awaryjny'] = $stare['tekst_awaryjny'];
	}
	update_option( 'chatl_ustawienia', $nowe );

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-ustawienia&chatl_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_chatl_ustawienia_zapisz', 'chatl_post_ustawienia_zapisz' );
