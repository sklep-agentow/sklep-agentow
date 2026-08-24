<?php
/**
 * Ustawienia sklepu (K3b) — Settings API wg Plugin Handbook:
 * register_setting + add_settings_section/field, zapis przez options.php
 * (nonce i capability robi rdzeń), sanitize_callback na każdej opcji.
 *
 * Sekrety (klucz Stripe, sekret webhooka): pole password, wartość NIGDY nie
 * wraca do formularza; puste pole = zostaw bez zmian; usunięcie = checkbox.
 * Opcje sekretne zakładane na admin_init z autoload=no (options.php robi
 * update_option, które ZACHOWUJE autoload) — nigdy nie ładują się na froncie;
 * szczegóły przy sklepag_ustawienia_rejestracja().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'sklepag_ustawienia_rejestracja' );

/**
 * Rejestracja opcji, sekcji i pól.
 *
 * Opcje sekretne zakładane tu PUSTE z autoload=no, ZANIM dotknie ich formularz:
 * options.php przy zapisie robi update_option, które ZACHOWUJE autoload
 * istniejącej opcji — bez tego pierwszy zapis dałby autoload=yes i sekrety
 * ładowałyby się na froncie przy każdym żądaniu (plan §7). Właśnie TU, nie
 * w aktywacji: hak aktywacji nie odpala przy update-in-place (podmiana plików
 * bez reaktywacji), a admin_init biegnie przy każdym wejściu
 * do admina i add_option jest no-opem, gdy opcja już istnieje; na froncie zero
 * kosztu (admin_init tam nie ma).
 */
function sklepag_ustawienia_rejestracja() {
	add_option( 'sklepag_stripe_klucz_sekretny', '', '', false );
	add_option( 'sklepag_stripe_webhook_sekret', '', '', false );

	// --- Stripe (sekrety — własne callbacki „puste = bez zmian"). ---
	register_setting(
		'sklepag_ustawienia',
		'sklepag_stripe_klucz_sekretny',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sklepag_sanityzuj_klucz_stripe',
			'show_in_rest'      => false,
		)
	);
	register_setting(
		'sklepag_ustawienia',
		'sklepag_stripe_webhook_sekret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sklepag_sanityzuj_sekret_webhooka',
			'show_in_rest'      => false,
		)
	);

	// --- Maile. ---
	register_setting(
		'sklepag_ustawienia',
		'sklepag_nadawca_nazwa',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'sklepag_ustawienia',
		'sklepag_nadawca_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sklepag_sanityzuj_nadawca_email',
			'default'           => '',
		)
	);
	register_setting(
		'sklepag_ustawienia',
		'sklepag_email_kontaktowy',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sklepag_sanityzuj_email_kontaktowy',
			'default'           => '',
		)
	);

	// --- Sklep. ---
	register_setting(
		'sklepag_ustawienia',
		'sklepag_waluta_domyslna',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sklepag_sanityzuj_walute',
			'default'           => 'PLN',
		)
	);

	// --- Dane (D22). ---
	register_setting(
		'sklepag_ustawienia',
		'sklepag_usun_dane_przy_odinstalowaniu',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		)
	);

	add_settings_section( 'sklepag_sekcja_stripe', 'Stripe (tryb testowy)', 'sklepag_sekcja_stripe_opis', 'sklepag-ustawienia' );
	add_settings_field( 'sklepag_stripe_klucz_sekretny', 'Klucz sekretny (sk_test_…)', 'sklepag_pole_klucz_stripe', 'sklepag-ustawienia', 'sklepag_sekcja_stripe', array( 'label_for' => 'sklepag_stripe_klucz_sekretny' ) );
	add_settings_field( 'sklepag_stripe_webhook_sekret', 'Sekret webhooka (whsec_…)', 'sklepag_pole_sekret_webhooka', 'sklepag-ustawienia', 'sklepag_sekcja_stripe', array( 'label_for' => 'sklepag_stripe_webhook_sekret' ) );

	add_settings_section( 'sklepag_sekcja_maile', 'Maile', 'sklepag_sekcja_maile_opis', 'sklepag-ustawienia' );
	add_settings_field( 'sklepag_nadawca_nazwa', 'Nazwa nadawcy', 'sklepag_pole_nadawca_nazwa', 'sklepag-ustawienia', 'sklepag_sekcja_maile', array( 'label_for' => 'sklepag_nadawca_nazwa' ) );
	add_settings_field( 'sklepag_nadawca_email', 'Adres nadawcy', 'sklepag_pole_nadawca_email', 'sklepag-ustawienia', 'sklepag_sekcja_maile', array( 'label_for' => 'sklepag_nadawca_email' ) );
	add_settings_field( 'sklepag_email_kontaktowy', 'Mail kontaktowy właściciela', 'sklepag_pole_email_kontaktowy', 'sklepag-ustawienia', 'sklepag_sekcja_maile', array( 'label_for' => 'sklepag_email_kontaktowy' ) );

	add_settings_section( 'sklepag_sekcja_sklep', 'Sklep', '__return_null', 'sklepag-ustawienia' );
	add_settings_field( 'sklepag_waluta_domyslna', 'Domyślna waluta nowych produktów', 'sklepag_pole_waluta', 'sklepag-ustawienia', 'sklepag_sekcja_sklep', array( 'label_for' => 'sklepag_waluta_domyslna' ) );

	add_settings_section( 'sklepag_sekcja_dane', 'Dane przy odinstalowaniu', 'sklepag_sekcja_dane_opis', 'sklepag-ustawienia' );
	add_settings_field( 'sklepag_usun_dane_przy_odinstalowaniu', 'Pełne czyszczenie', 'sklepag_pole_usun_dane', 'sklepag-ustawienia', 'sklepag_sekcja_dane', array( 'label_for' => 'sklepag_usun_dane_przy_odinstalowaniu' ) );
}

/* ---------- Sanityzacja ---------- */

/**
 * Sekret: puste pole = ZACHOWAJ starą wartość (nie nadpisuj pustym);
 * żądanie usunięcia niesie osobny checkbox (czytany tu z tego samego POST-a
 * chronionego nonce'em options.php).
 *
 * @param string $wartosc     Nowa wartość z formularza.
 * @param string $opcja       Nazwa opcji.
 * @param string $checkbox    Nazwa pola „usuń".
 * @param string $prefiks     Wymagany początek wartości (ochrona przed wklejeniem złego sekretu).
 */
function sklepag_sanityzuj_sekret( $wartosc, $opcja, $checkbox, $prefiks ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- POST z options.php, nonce zweryfikowany przez rdzeń (check_admin_referer w options.php) zanim odpali sanitize_callback.
	if ( ! empty( $_POST[ $checkbox ] ) ) {
		return '';
	}
	// phpcs:enable
	$wartosc = trim( sanitize_text_field( (string) $wartosc ) );
	if ( '' === $wartosc ) {
		return (string) get_option( $opcja, '' ); // Puste = bez zmian.
	}
	if ( ! str_starts_with( $wartosc, $prefiks ) ) {
		add_settings_error( $opcja, $opcja . '_prefiks', 'Wartość nie wygląda na właściwy sekret (oczekiwany początek: ' . $prefiks . '…) — zostawiono poprzednią.', 'error' );
		return (string) get_option( $opcja, '' );
	}
	return $wartosc;
}

function sklepag_sanityzuj_klucz_stripe( $wartosc ) {
	return sklepag_sanityzuj_sekret( $wartosc, 'sklepag_stripe_klucz_sekretny', 'sklepag_usun_klucz_stripe', 'sk_' );
}

function sklepag_sanityzuj_sekret_webhooka( $wartosc ) {
	return sklepag_sanityzuj_sekret( $wartosc, 'sklepag_stripe_webhook_sekret', 'sklepag_usun_sekret_webhooka', 'whsec_' );
}

/**
 * E-mail: pusty dozwolony (funkcja wyłączona), niepoprawny = błąd + STARA wartość.
 * Rdzeń woła sanitize_callback tylko z wartością (bez nazwy opcji), stąd dwa
 * cienkie callbacki nad wspólnym rdzeniem.
 *
 * @param string $wartosc Wartość z formularza.
 * @param string $opcja   Nazwa opcji (do odczytu starej wartości przy błędzie).
 */
function sklepag_sanityzuj_email_opcji( $wartosc, $opcja ) {
	$wartosc = trim( (string) $wartosc );
	if ( '' === $wartosc ) {
		return '';
	}
	$czysty = sanitize_email( $wartosc );
	if ( '' === $czysty || ! is_email( $czysty ) ) {
		add_settings_error( $opcja, $opcja . '_niepoprawny', 'Adres e-mail „' . esc_html( $wartosc ) . '" jest niepoprawny — pole pozostawione bez zmian.', 'error' );
		return (string) get_option( $opcja, '' );
	}
	return $czysty;
}

function sklepag_sanityzuj_nadawca_email( $wartosc ) {
	return sklepag_sanityzuj_email_opcji( $wartosc, 'sklepag_nadawca_email' );
}

function sklepag_sanityzuj_email_kontaktowy( $wartosc ) {
	return sklepag_sanityzuj_email_opcji( $wartosc, 'sklepag_email_kontaktowy' );
}

/**
 * Waluta: dokładnie 3 wielkie litery (ISO 4217), inaczej stara wartość.
 *
 * @param string $wartosc Wartość z formularza.
 */
function sklepag_sanityzuj_walute( $wartosc ) {
	$wartosc = strtoupper( trim( (string) $wartosc ) );
	if ( ! preg_match( '/^[A-Z]{3}$/', $wartosc ) ) {
		add_settings_error( 'sklepag_waluta_domyslna', 'sklepag_waluta', 'Waluta musi być kodem 3-literowym (np. PLN) — zostawiono poprzednią.', 'error' );
		return (string) get_option( 'sklepag_waluta_domyslna', 'PLN' );
	}
	return $wartosc;
}

/* ---------- Sekcje (opisy) ---------- */

function sklepag_sekcja_stripe_opis() {
	echo '<p>Klucze z panelu Stripe (tryb TESTOWY: <code>sk_test_…</code>). Sekret webhooka: z Dashboardu (endpoint na hostingu) albo z <code>stripe listen --print-secret</code> (lokalnie). Wartości są przechowywane bez podglądu i nie wracają do formularza.</p>';
}

function sklepag_sekcja_maile_opis() {
	echo '<p>Nadawca dotyczy WYŁĄCZNIE maili sklepu (potwierdzenia zakupów) — maile reszty strony pozostają nietknięte. Mail kontaktowy pokazuje się kupującym przy problemach z pobraniem.</p>';
}

function sklepag_sekcja_dane_opis() {
	echo '<p>Domyślnie dane sprzedaży ZOSTAJĄ po odinstalowaniu (historia sprzedaży to nie śmieć). Włączenie = pełne czyszczenie: tabele, opcje, strony wtyczki, pliki chronione. Klucze Stripe i zaplanowane zadanie cron są usuwane ZAWSZE, niezależnie od tej opcji.</p>';
}

/* ---------- Pola ---------- */

/**
 * Pole sekretu: password bez wartości + status ustawienia + checkbox usunięcia.
 *
 * @param string $opcja    Nazwa opcji.
 * @param string $checkbox Nazwa pola „usuń".
 */
function sklepag_pole_sekretu( $opcja, $checkbox ) {
	$ustawiony = '' !== (string) get_option( $opcja, '' );
	echo '<input type="password" id="' . esc_attr( $opcja ) . '" name="' . esc_attr( $opcja ) . '" value="" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( $ustawiony ? 'ustawiony — zostaw puste, by nie zmieniać' : 'nieustawiony' ) . '">';
	echo $ustawiony
		? ' <span class="sklepag-badge sklepag-badge-ok">ustawiony</span>'
		: ' <span class="sklepag-badge sklepag-badge-blad">brak</span>';
	if ( $ustawiony ) {
		echo '<p><label><input type="checkbox" name="' . esc_attr( $checkbox ) . '" value="1"> Usuń zapisany sekret</label></p>';
	}
}

function sklepag_pole_klucz_stripe() {
	sklepag_pole_sekretu( 'sklepag_stripe_klucz_sekretny', 'sklepag_usun_klucz_stripe' );
}

function sklepag_pole_sekret_webhooka() {
	sklepag_pole_sekretu( 'sklepag_stripe_webhook_sekret', 'sklepag_usun_sekret_webhooka' );
}

function sklepag_pole_nadawca_nazwa() {
	echo '<input type="text" id="sklepag_nadawca_nazwa" name="sklepag_nadawca_nazwa" value="' . esc_attr( (string) get_option( 'sklepag_nadawca_nazwa', '' ) ) . '" class="regular-text" placeholder="np. Sklep Agentów">';
}

function sklepag_pole_nadawca_email() {
	echo '<input type="email" id="sklepag_nadawca_email" name="sklepag_nadawca_email" value="' . esc_attr( (string) get_option( 'sklepag_nadawca_email', '' ) ) . '" class="regular-text" placeholder="sklep@twoja-domena.pl">';
	echo '<p class="description">Puste = domyślny nadawca WordPressa. Adres musi być dopuszczony przez hosting (SPF/DKIM), inaczej maile lądują w spamie.</p>';
}

function sklepag_pole_email_kontaktowy() {
	echo '<input type="email" id="sklepag_email_kontaktowy" name="sklepag_email_kontaktowy" value="' . esc_attr( (string) get_option( 'sklepag_email_kontaktowy', '' ) ) . '" class="regular-text" placeholder="kontakt@twoja-domena.pl">';
	echo '<p class="description">Pokazywany kupującym przy problemie z pobraniem (link wygasł / limit wyczerpany).</p>';
}

function sklepag_pole_waluta() {
	echo '<input type="text" id="sklepag_waluta_domyslna" name="sklepag_waluta_domyslna" value="' . esc_attr( (string) get_option( 'sklepag_waluta_domyslna', 'PLN' ) ) . '" class="small-text" maxlength="3" pattern="[A-Za-z]{3}">';
}

function sklepag_pole_usun_dane() {
	echo '<label><input type="checkbox" id="sklepag_usun_dane_przy_odinstalowaniu" name="sklepag_usun_dane_przy_odinstalowaniu" value="1"' . checked( (bool) get_option( 'sklepag_usun_dane_przy_odinstalowaniu', false ), true, false ) . '> Usuń WSZYSTKIE dane sklepu przy odinstalowaniu wtyczki</label>';
}

/* ---------- Render strony ---------- */

/**
 * Callback submenu „Ustawienia".
 */
function sklepag_admin_ustawienia() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	echo '<div class="wrap"><h1>Ustawienia sklepu</h1>';
	settings_errors();
	echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( 'sklepag_ustawienia' );
	do_settings_sections( 'sklepag-ustawienia' );
	submit_button( 'Zapisz ustawienia' );
	echo '</form></div>';
}
