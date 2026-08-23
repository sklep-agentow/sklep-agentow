<?php
/**
 * Panel „Skrzynka (IMAP)": diagnostyka, dane połączenia, tryb pracy,
 * zadanie cykliczne, historia przebiegów i cofanie ostatniego przebiegu.
 * Każda mutacja: nonce + capability manage_options (admin-post + redirect).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Podstrona w menu wtyczki.
 *
 * @return void
 */
function segm_skrzynka_menu() {
	add_submenu_page( 'segm-przebiegi', 'Skrzynka (IMAP)', 'Skrzynka (IMAP)', 'manage_options', 'segm-skrzynka', 'segm_strona_skrzynka' );
}
add_action( 'admin_menu', 'segm_skrzynka_menu', 11 );

/**
 * Zapamiętanie komunikatu na czas przekierowania.
 *
 * Wyniki przebiegu i dziennik protokołu są za długie na adres URL — trzymamy
 * je krótko żyjącym wpisem przypisanym do użytkownika, a nie w zapytaniu.
 *
 * @param string             $typ      'ok' | 'blad' | 'uwaga'.
 * @param string             $tekst    Treść komunikatu.
 * @param array<int, string> $dziennik Dziennik protokołu do podglądu.
 * @return void
 */
function segm_skrzynka_zapamietaj( $typ, $tekst, $dziennik = array() ) {
	set_transient(
		'segm_komunikat_' . get_current_user_id(),
		array(
			'typ'      => $typ,
			'tekst'    => $tekst,
			'dziennik' => array_slice( (array) $dziennik, -60 ),
		),
		5 * MINUTE_IN_SECONDS
	);
}

/**
 * Wyświetlenie i skasowanie zapamiętanego komunikatu.
 *
 * @return void
 */
function segm_skrzynka_pokaz_komunikat() {
	/*
	 * WK-17: komunikat ISTNIAŁ, ale renderował się w środku strony, jako szósty
	 * pasek — pod dwoma paskami WordPressa i paskami wtyczki (błąd AI, błąd
	 * bazy, wskazówka startowa). Kupujący go nie zobaczył i nie wiedział, czy
	 * zapis zadziałał. Teraz idzie przez `admin_notices` z priorytetem 1, czyli
	 * PRZED paskami wtyczki (te mają domyślny 10) — jest pierwszym, co widać
	 * po powrocie z formularza.
	 */
	$ekran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $ekran && false === strpos( (string) $ekran->id, 'segm-skrzynka' ) ) {
		return;
	}
	$klucz = 'segm_komunikat_' . get_current_user_id();
	$wpis  = get_transient( $klucz );
	if ( ! is_array( $wpis ) || empty( $wpis['tekst'] ) ) {
		return;
	}
	delete_transient( $klucz );

	$klasy = array(
		'ok'    => 'notice-success',
		'uwaga' => 'notice-warning',
		'blad'  => 'notice-error',
	);
	$klasa = isset( $klasy[ $wpis['typ'] ] ) ? $klasy[ $wpis['typ'] ] : 'notice-info';
	?>
	<div class="notice <?php echo esc_attr( $klasa ); ?>">
		<p><?php echo esc_html( (string) $wpis['tekst'] ); ?></p>
		<?php if ( ! empty( $wpis['dziennik'] ) ) : ?>
			<details>
				<summary>Pokaż rozmowę z serwerem poczty (bez hasła)</summary>
				<pre style="overflow:auto;max-height:320px;background:#fff;padding:8px;border:1px solid #ccd0d4"><?php echo esc_html( implode( "\n", (array) $wpis['dziennik'] ) ); ?></pre>
			</details>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_notices', 'segm_skrzynka_pokaz_komunikat', 1 );

/**
 * Czego brakuje do „Sprawdź połączenie" — po nazwach pól z formularza (WK-5).
 *
 * @return array<int, string> Puste = wszystko zapisane.
 */
function segm_skrzynka_czego_brakuje() {
	$ust   = segm_skrzynka_ustawienia();
	$braki = array();
	if ( '' === trim( (string) $ust['host'] ) ) {
		$braki[] = 'serwer IMAP';
	}
	if ( '' === trim( (string) $ust['login'] ) ) {
		$braki[] = 'login';
	}
	if ( '' === segm_skrzynka_haslo() ) {
		$braki[] = 'hasło aplikacji';
	}
	return $braki;
}

/* ===================== STRONA ===================== */

/**
 * Strona „Skrzynka (IMAP)".
 *
 * @return void
 */
function segm_strona_skrzynka() {
	if ( isset( $_GET['przebieg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		segm_skrzynka_widok_przebiegu( (int) $_GET['przebieg'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$ust     = segm_skrzynka_ustawienia();
	$zgoda   = segm_zgoda_przenoszenia();
	$ostatni = segm_skrzynka_ostatni_do_cofniecia();
	$gotowa  = segm_skrzynka_skonfigurowana();
	?>
	<div class="wrap">
		<h1>Skrzynka (IMAP)</h1>

		<div class="notice notice-info inline" style="margin:12px 0">
			<p><strong>Domyślnie wtyczka pracuje NA SUCHO.</strong> Loguje się do skrzynki, czyta nagłówki i pokazuje, co <em>zrobiłaby</em> z każdą wiadomością — ale niczego nie rusza. W tym trybie folder otwierany jest poleceniem <code>EXAMINE</code>, czyli tylko do odczytu na poziomie protokołu: serwer poczty sam odrzuci każdą próbę zmiany. Realne przenoszenie włącza się świadomie, niżej.</p>
		</div>

		<h2>Czy ten serwer to udźwignie</h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
			<?php foreach ( segm_skrzynka_diagnostyka() as $wpis ) : ?>
				<tr>
					<td style="width:34%"><strong><?php echo esc_html( $wpis['nazwa'] ); ?></strong></td>
					<td style="width:8%">
						<?php if ( 'ok' === $wpis['stan'] ) : ?>
							<span style="color:#008a20">✔ jest</span>
						<?php elseif ( 'uwaga' === $wpis['stan'] ) : ?>
							<span style="color:#996800">! uwaga</span>
						<?php else : ?>
							<span style="color:#d63638">✘ brak</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $wpis['opis'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2>Połączenie ze skrzynką</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_skrzynka_zapisz">
			<?php wp_nonce_field( 'segm_skrzynka_zapisz' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="segm-host">Serwer IMAP</label></th>
					<td>
						<input type="text" id="segm-host" name="host" class="regular-text" value="<?php echo esc_attr( $ust['host'] ); ?>" placeholder="imap.przyklad.pl">
						<p class="description">Adres podaje Twój dostawca poczty — dla Gmaila jest to <code>imap.gmail.com</code> (port 993, SSL/TLS).<br>
							⛔ <strong>Microsoft 365 i Outlook.com nie zadziałają.</strong> Microsoft wyłączył tam logowanie loginem i hasłem — ich serwer przyjmuje wyłącznie OAuth2, a ta wtyczka loguje się hasłem aplikacji. Sprawdzone na żywym serwerze: <code>outlook.office365.com</code> ogłasza <code>LOGINDISABLED</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-port">Port i szyfrowanie</label></th>
					<td>
						<input type="number" id="segm-port" name="port" min="1" max="65535" value="<?php echo (int) $ust['port']; ?>" style="width:8em">
						<select name="szyfrowanie">
							<option value="ssl" <?php selected( $ust['szyfrowanie'], 'ssl' ); ?>>SSL/TLS od razu (zwykle port 993)</option>
							<option value="starttls" <?php selected( $ust['szyfrowanie'], 'starttls' ); ?>>STARTTLS (zwykle port 143)</option>
							<option value="brak" <?php selected( $ust['szyfrowanie'], 'brak' ); ?>>bez szyfrowania (tylko serwer w tej samej sieci!)</option>
						</select>
						<p class="description">Prawie wszędzie właściwy wybór to <strong>993 + SSL/TLS</strong>. „Bez szyfrowania" posyła hasło otwartym tekstem — przez internet nigdy tego nie używaj.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-login">Login</label></th>
					<td>
						<input type="text" id="segm-login" name="login" class="regular-text" value="<?php echo esc_attr( $ust['login'] ); ?>" placeholder="biuro@twojafirma.pl" autocomplete="off">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-haslo">Hasło aplikacji</label></th>
					<td>
						<?php if ( segm_skrzynka_haslo_ze_stalej() ) : ?>
							<p><strong>Hasło podane w konfiguracji serwera</strong> — stałą <code><?php echo esc_html( SEGM_STALA_HASLA ); ?></code> w <code>wp-config.php</code>.</p>
							<input type="password" id="segm-haslo" class="regular-text" value="" autocomplete="new-password" disabled placeholder="hasło z wp-config.php">
							<p class="description">Pole jest zablokowane, bo stała ma pierwszeństwo nad bazą. Żeby zmienić albo usunąć hasło, popraw <code>wp-config.php</code>. Hasło trzymane poza bazą nie wychodzi z jej kopią zapasową — to sposób zalecany.</p>
						<?php else : ?>
							<?php if ( '' !== segm_skrzynka_haslo() ) : ?>
								<p><strong>Hasło zapisane.</strong>
									<label><input type="checkbox" name="usun_haslo" value="1"> usuń hasło (rozłącza skrzynkę)</label></p>
							<?php endif; ?>
							<input type="password" id="segm-haslo" name="haslo" class="regular-text" value="" autocomplete="new-password"
								placeholder="<?php echo '' !== segm_skrzynka_haslo() ? 'wpisz, aby podmienić' : 'hasło aplikacji'; ?>">
							<p class="description">
								⛔ <strong>Podaj HASŁO APLIKACJI, nigdy głównego hasła do konta.</strong> Hasło aplikacji obowiązuje wyłącznie dla jednego programu, ma ograniczone prawa i unieważnisz je jednym kliknięciem, nie zmieniając hasła do poczty. Gmail i Microsoft 365 przy włączonym logowaniu dwuskładnikowym zwykłego hasła i tak nie przyjmą.<br>
								<strong>Skąd je wziąć:</strong> u dostawcy poczty, w ustawieniach bezpieczeństwa konta — w Gmailu <em>Zarządzaj kontem Google → Bezpieczeństwo → Hasła do aplikacji</em>, u dostawców hostingowych (home.pl, OVH, nazwa.pl) zwykle <em>Poczta → konto → hasła aplikacji / hasła zewnętrzne</em>. Wygenerowane hasło wklej tutaj w całości, razem ze spacjami, jeśli dostawca je wstawia.<br>
								Więcej: <code>INSTRUKCJA.md</code>, rozdział <em>„Prawdziwa skrzynka (IMAP) — segregacja na żywo"</em>, sekcje <em>„Podłączenie krok po kroku"</em> i <em>„⛔ Hasło aplikacji, nigdy hasło do konta"</em>.<br>
								Hasło NIGDY nie wraca do tego pola — po zapisaniu można je tylko podmienić albo usunąć.<br>
								Bezpieczniej: wpisz je stałą <code>define( '<?php echo esc_html( SEGM_STALA_HASLA ); ?>', 'twoje-haslo-aplikacji' );</code> w <code>wp-config.php</code>. Wtedy hasło nie leży w bazie i nie wychodzi z jej kopią zapasową. Stała ma pierwszeństwo nad tym polem.
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-folder">Folder źródłowy</label></th>
					<td>
						<input type="text" id="segm-folder" name="folder_zrodlowy" class="regular-text" value="<?php echo esc_attr( $ust['folder_zrodlowy'] ); ?>">
						<p class="description">Skąd brać wiadomości. Domyślnie <code>INBOX</code>, czyli skrzynka odbiorcza.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-prefiks">Folder nadrzędny na kategorie</label></th>
					<td>
						<input type="text" id="segm-prefiks" name="prefiks" class="regular-text" value="<?php echo esc_attr( $ust['prefiks'] ); ?>">
						<p class="description">Kategoria „Faktury" trafi wtedy do <code><?php echo esc_html( '' === trim( (string) $ust['prefiks'] ) ? 'Faktury' : $ust['prefiks'] . '/Faktury' ); ?></code>. Zostaw puste, żeby foldery powstawały w korzeniu skrzynki. Brakujące foldery zakładamy sami.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-limit">Ile wiadomości na przebieg</label></th>
					<td>
						<input type="number" id="segm-limit" name="limit" min="1" max="500" value="<?php echo (int) $ust['limit']; ?>" style="width:8em">
						<p class="description">Bierzemy tyle NAJNOWSZYCH wiadomości z folderu źródłowego. Jeden przebieg i tak kończy się po <?php echo (int) SEGM_SKRZ_LIMIT_CZASU; ?> s, żeby hosting nie urwał go w połowie — reszta idzie przy następnym.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Co brać pod uwagę</th>
					<td>
						<label><input type="checkbox" name="dopasuj_tresc" value="1" <?php checked( $ust['dopasuj_tresc'] ); ?>> dopasowuj też reguły typu „treść zawiera" (pobiera pierwsze <?php echo (int) SEGM_SKRZ_TRESC_BAJTOW; ?> bajtów treści każdego maila)</label><br>
						<label><input type="checkbox" name="tylko_nieprzeczytane" value="1" <?php checked( $ust['tylko_nieprzeczytane'] ); ?>> tylko wiadomości nieprzeczytane</label><br>
						<label><input type="checkbox" name="ruszaj_reszte" value="1" <?php checked( $ust['ruszaj_reszte'] ); ?>> przenoś także wiadomości, których nie złapała żadna reguła, do folderu „<?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>"</label>
						<p class="description">Odczyt nie oznacza wiadomości jako przeczytanych — pobieramy je poleceniem <code>BODY.PEEK</code>, które z założenia nie rusza znacznika „przeczytane".</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Certyfikat</th>
					<td>
						<label><input type="checkbox" name="ufaj_bez_cert" value="1" <?php checked( $ust['ufaj_bez_cert'] ); ?>> nie sprawdzaj certyfikatu serwera</label>
						<p class="description">⛔ Zaznacz TYLKO dla własnego serwera poczty w tej samej sieci. Wyłączenie sprawdzania certyfikatu otwiera drogę do podszycia się pod serwer i przechwycenia hasła.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Zapisz połączenie' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_skrzynka_test">
			<?php wp_nonce_field( 'segm_skrzynka_test' ); ?>
			<?php submit_button( 'Sprawdź połączenie', 'secondary', 'submit', false, $gotowa ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<span class="description">
				<?php if ( $gotowa ) : ?>
					Łączy się naprawdę, loguje i pokazuje, ile wiadomości widzi w folderze źródłowym. Niczego nie zmienia.
				<?php else : ?>
					<?php
					/*
					 * WK-5: kupujący wypełnił pola, przycisk został szary i nic
					 * nie mówiło dlaczego. Sprawdzenie łączy się z danymi
					 * ZAPISANYMI w bazie, nie z zawartością pól — więc mówimy
					 * to wprost i wymieniamy, czego brakuje po zapisie.
					 */
					$braki = segm_skrzynka_czego_brakuje();
					?>
					<strong>Przycisk jest nieaktywny, dopóki dane połączenia nie są ZAPISANE.</strong>
					Sprawdzenie loguje się danymi z bazy, nie tymi wpisanymi przed chwilą w polach —
					<?php if ( $braki ) : ?>
						brakuje: <strong><?php echo esc_html( implode( ', ', $braki ) ); ?></strong>.
						Uzupełnij i kliknij <strong>„Zapisz połączenie"</strong> (przycisk wyżej); przycisk odblokuje się od razu po zapisie.
					<?php else : ?>
						kliknij <strong>„Zapisz połączenie"</strong> (przycisk wyżej), a ten odblokuje się od razu.
					<?php endif; ?>
				<?php endif; ?>
			</span>
		</form>

		<h2>Tryb pracy</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_skrzynka_tryb">
			<?php wp_nonce_field( 'segm_skrzynka_tryb' ); ?>
			<p>
				<label><input type="radio" name="tryb" value="na_sucho" <?php checked( $ust['tryb'], 'na_sucho' ); ?>>
					<strong>Na sucho (domyślnie)</strong> — pokazuje, co zrobiłby, nie rusza ani jednej wiadomości.</label><br>
				<label><input type="radio" name="tryb" value="na_zywo" <?php checked( $ust['tryb'], 'na_zywo' ); ?> <?php disabled( null === $zgoda ); ?>>
					<strong>Realne przenoszenie</strong> — wiadomości naprawdę zmieniają folder na serwerze poczty.</label>
			</p>
			<?php if ( null === $zgoda ) : ?>
				<p class="description">Realne przenoszenie jest zablokowane, dopóki nie potwierdzisz tego niżej.</p>
			<?php endif; ?>
			<?php submit_button( 'Zapisz tryb', 'secondary', 'submit', false ); ?>
		</form>

		<h3>Potwierdzenie realnego przenoszenia</h3>
		<?php if ( null === $zgoda ) : ?>
			<p>Przenoszenie zmienia CUDZĄ korespondencję na serwerze poczty. Zanim je włączysz, przepuść skrzynkę na sucho i sprawdź w historii, czy reguły trafiają tam, gdzie chcesz. Ostatni przebieg zawsze można cofnąć, ale wcześniejszych już nie.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="segm_skrzynka_zgoda">
				<?php wp_nonce_field( 'segm_skrzynka_zgoda' ); ?>
				<p><label><input type="checkbox" name="zgoda" value="1" required>
					Potwierdzam, że wtyczka może przenosić wiadomości między folderami na tej skrzynce, i że mam do tego prawo.</label></p>
				<?php submit_button( 'Potwierdzam — odblokuj realne przenoszenie', 'primary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p><strong>Potwierdzone</strong>: <?php echo esc_html( $zgoda['kiedy'] ); ?>, użytkownik <code><?php echo esc_html( $zgoda['kto'] ); ?></code>.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="segm_skrzynka_zgoda_cofnij">
				<?php wp_nonce_field( 'segm_skrzynka_zgoda_cofnij' ); ?>
				<?php submit_button( 'Cofnij potwierdzenie (wraca tryb na sucho)', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Cofnąć potwierdzenie? Wtyczka wróci do trybu na sucho.');" ) ); ?>
			</form>
		<?php endif; ?>

		<h2>Segregacja cykliczna</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_skrzynka_harmonogram">
			<?php wp_nonce_field( 'segm_skrzynka_harmonogram' ); ?>
			<p>
				<label for="segm-czestotliwosc">Jak często:</label>
				<select id="segm-czestotliwosc" name="czestotliwosc">
					<?php foreach ( segm_skrzynka_czestotliwosci() as $klucz => $wpis ) : ?>
						<option value="<?php echo esc_attr( $klucz ); ?>" <?php selected( $ust['czestotliwosc'], $klucz ); ?>><?php echo esc_html( $wpis['opis'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php echo esc_html( segm_skrzynka_opis_harmonogramu() ); ?></p>
			<?php submit_button( 'Zapisz harmonogram', 'secondary', 'submit', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
			<input type="hidden" name="action" value="segm_skrzynka_uruchom">
			<?php wp_nonce_field( 'segm_skrzynka_uruchom' ); ?>
			<?php submit_button( 'Uruchom teraz', 'primary', 'submit', false, $gotowa ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<span class="description">Tryb: <strong><?php echo segm_przenoszenie_dozwolone() ? 'realne przenoszenie' : 'na sucho — nic nie zostanie ruszone'; ?></strong>.</span>
		</form>

		<h2>Historia przebiegów</h2>
		<?php if ( $ostatni ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
				<input type="hidden" name="action" value="segm_skrzynka_cofnij">
				<input type="hidden" name="przebieg" value="<?php echo (int) $ostatni->id; ?>">
				<?php wp_nonce_field( 'segm_skrzynka_cofnij' ); ?>
				<?php submit_button( 'Cofnij ostatni przebieg (#' . (int) $ostatni->id . ', ' . (int) $ostatni->przeniesionych . ' wiadomości)', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Cofnąć ostatni przebieg? Wiadomości wrócą do folderu, z którego wyszły.');" ) ); ?>
			</form>
		<?php endif; ?>
		<?php segm_skrzynka_tabela_przebiegow(); ?>
	</div>
	<?php
}

/**
 * Tabela historii przebiegów.
 *
 * @return void
 */
function segm_skrzynka_tabela_przebiegow() {
	global $wpdb;
	$wszystkich = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_skrz_przebiegi" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$strona_nr  = segm_biezaca_strona( (int) ceil( $wszystkich / SEGM_NA_STRONE ) );
	$przebiegi  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			SEGM_NA_STRONE,
			( $strona_nr - 1 ) * SEGM_NA_STRONE
		)
	);
	segm_pasek_stron( 'segm-skrzynka', $wszystkich, $strona_nr );
	?>
	<table class="widefat striped">
		<thead><tr><th>#</th><th>Kiedy</th><th>Tryb</th><th>Skąd</th><th>Sprawdzonych</th><th>Dopasowanych</th><th>Przeniesionych</th><th>Błędów</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $przebiegi ) : ?>
			<tr><td colspan="9">Jeszcze nie było przebiegu na skrzynce.</td></tr>
		<?php endif; ?>
		<?php foreach ( (array) $przebiegi as $p ) : ?>
			<tr>
				<td><?php echo (int) $p->id; ?></td>
				<td><?php echo esc_html( $p->uruchomiono ); ?></td>
				<td>
					<?php echo 'na_zywo' === $p->tryb ? 'realny' : 'na sucho'; ?>
					<?php echo 'cron' === $p->zrodlo ? ' (cyklicznie)' : ''; ?>
					<?php echo (int) $p->cofniety > 0 ? ' — <em>cofnięty</em>' : ''; ?>
				</td>
				<td><?php echo esc_html( $p->folder_zrodlowy ); ?></td>
				<td><?php echo (int) $p->sprawdzonych; ?></td>
				<td><?php echo (int) $p->dopasowanych; ?></td>
				<td><?php echo (int) $p->przeniesionych; ?></td>
				<td><?php echo (int) $p->bledow; ?></td>
				<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-skrzynka&przebieg=' . (int) $p->id . ( $strona_nr > 1 ? '&segm_strona=' . (int) $strona_nr : '' ) ) ); ?>">Szczegóły</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Szczegóły jednego przebiegu: co, kiedy i dokąd.
 *
 * @param int $id Identyfikator przebiegu.
 * @return void
 */
function segm_skrzynka_widok_przebiegu( $id ) {
	global $wpdb;
	$p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $p ) {
		echo '<div class="wrap"><h1>Przebieg skrzynki</h1><p>Nie ma takiego przebiegu.</p></div>';
		return;
	}
	$ruchy = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d ORDER BY id ASC", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$opisy = array(
		'plan'         => 'przeniósłby',
		'w_toku'       => 'w toku (niepotwierdzone)',
		'przeniesiony' => 'przeniesiono',
		'pominiety'    => 'zostawiono',
		'blad'         => 'BŁĄD',
		'cofniety'     => 'cofnięto',
	);
	?>
	<div class="wrap">
		<h1>Przebieg skrzynki #<?php echo (int) $p->id; ?> — <?php echo 'na_zywo' === $p->tryb ? 'realny' : 'na sucho'; ?> (<?php echo esc_html( $p->uruchomiono ); ?>)</h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-skrzynka' . segm_param_strony() ) ); ?>">← wróć do skrzynki</a></p>
		<?php if ( 'na_sucho' === $p->tryb ) : ?>
			<div class="notice notice-info inline"><p>Ten przebieg poszedł <strong>na sucho</strong>: poniżej jest lista tego, co wtyczka <em>zrobiłaby</em>. Na serwerze poczty nic się nie zmieniło.</p></div>
		<?php endif; ?>
		<?php if ( '' !== (string) $p->komunikat ) : ?>
			<div class="notice notice-warning inline"><p><?php echo esc_html( $p->komunikat ); ?></p></div>
		<?php endif; ?>
		<p>Sprawdzonych: <strong><?php echo (int) $p->sprawdzonych; ?></strong> · dopasowanych regułą: <strong><?php echo (int) $p->dopasowanych; ?></strong> · przeniesionych: <strong><?php echo (int) $p->przeniesionych; ?></strong> · błędów: <strong><?php echo (int) $p->bledow; ?></strong></p>

		<table class="widefat striped">
			<thead><tr><th>UID</th><th>Nadawca</th><th>Temat</th><th>Co</th><th>Skąd</th><th>Dokąd</th><th>Uwagi</th></tr></thead>
			<tbody>
			<?php if ( ! $ruchy ) : ?>
				<tr><td colspan="7">Brak pozycji.</td></tr>
			<?php endif; ?>
			<?php foreach ( (array) $ruchy as $r ) : ?>
				<tr>
					<td><?php echo (int) $r->uid; ?></td>
					<td><?php echo esc_html( $r->nadawca ); ?></td>
					<td><?php echo esc_html( $r->temat ); ?></td>
					<td><?php echo esc_html( $opisy[ $r->stan ] ?? $r->stan ); ?></td>
					<td><?php echo esc_html( $r->folder_z ); ?></td>
					<td><?php echo esc_html( '' === (string) $r->folder_do ? '—' : $r->folder_do ); ?></td>
					<td><?php echo esc_html( $r->komunikat ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/* ===================== OBSŁUGA FORMULARZY ===================== */

/**
 * Powrót na stronę skrzynki.
 *
 * @return void
 */
function segm_skrzynka_wroc() {
	wp_safe_redirect( admin_url( 'admin.php?page=segm-skrzynka' ) );
	exit;
}

/**
 * Zapis danych połączenia.
 *
 * @return void
 */
function segm_post_skrzynka_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_zapisz' );

	$stare       = segm_skrzynka_ustawienia();
	$szyfrowanie = isset( $_POST['szyfrowanie'] ) ? sanitize_key( wp_unslash( $_POST['szyfrowanie'] ) ) : 'ssl';
	if ( ! in_array( $szyfrowanie, array( 'ssl', 'starttls', 'brak' ), true ) ) {
		$szyfrowanie = 'ssl';
	}

	// Puste albo błędne pole liczbowe NIE nadpisuje poprzedniej wartości (KR4):
	// `max( 1, (int) '' )` robiło z pustego portu port 1 i pustego limitu
	// limit 1 — a panel mówił „Zapisano". Właściciel dostaje o tym zdanie.
	$uwagi = array();
	$port  = segm_liczba_z_pola( 'port', (int) $stare['port'], 1, 65535, 'port', $uwagi );
	$limit = segm_liczba_z_pola( 'limit', (int) $stare['limit'], 1, 500, 'limit wiadomości na przebieg', $uwagi );

	$nowe = array_merge(
		$stare,
		array(
			'host'                 => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
			'port'                 => $port,
			'szyfrowanie'          => $szyfrowanie,
			'login'                => isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '',
			'folder_zrodlowy'      => isset( $_POST['folder_zrodlowy'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_zrodlowy'] ) ) : 'INBOX',
			'prefiks'              => isset( $_POST['prefiks'] ) ? sanitize_text_field( wp_unslash( $_POST['prefiks'] ) ) : '',
			'limit'                => $limit,
			'dopasuj_tresc'        => empty( $_POST['dopasuj_tresc'] ) ? 0 : 1,
			'tylko_nieprzeczytane' => empty( $_POST['tylko_nieprzeczytane'] ) ? 0 : 1,
			'ruszaj_reszte'        => empty( $_POST['ruszaj_reszte'] ) ? 0 : 1,
			'ufaj_bez_cert'        => empty( $_POST['ufaj_bez_cert'] ) ? 0 : 1,
		)
	);
	if ( '' === trim( $nowe['folder_zrodlowy'] ) ) {
		$nowe['folder_zrodlowy'] = 'INBOX';
	}

	if ( ! segm_zapisz_opcje( 'segm_skrzynka', $nowe ) ) {
		segm_log_bledu_bazy( 'segm_post_skrzynka_zapisz', 'ustawienia połączenia ze skrzynką' );
		segm_skrzynka_zapamietaj( 'blad', 'Nie udało się zapisać ustawień połączenia — baza odrzuciła zapis. Obowiązują POPRZEDNIE ustawienia.' );
		segm_skrzynka_wroc();
	}

	// Hasło: osobna opcja (autoload=no), NIGDY nie wraca do formularza.
	$komunikat = 'Zapisano ustawienia połączenia.';
	if ( ! empty( $_POST['usun_haslo'] ) ) {
		if ( ! segm_skrzynka_zapisz_haslo( '' ) ) {
			segm_skrzynka_zapamietaj( 'blad', 'Ustawienia zapisane, ale hasła NIE udało się usunąć — nadal jest w bazie. Sprawdź stan bazy i powtórz operację.' );
			segm_skrzynka_wroc();
		}
		$komunikat = 'Zapisano ustawienia. Hasło usunięte — skrzynka jest rozłączona.';
	} elseif ( isset( $_POST['haslo'] ) && '' !== trim( (string) wp_unslash( $_POST['haslo'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- warunek tylko bada niepustość; zapisywana wartość przechodzi sanitize_text_field() linijkę niżej.
		if ( ! segm_skrzynka_zapisz_haslo( sanitize_text_field( wp_unslash( $_POST['haslo'] ) ) ) ) {
			segm_log_bledu_bazy( 'segm_post_skrzynka_zapisz', 'hasło do skrzynki' );
			segm_skrzynka_zapamietaj( 'blad', 'Ustawienia zapisane, ale hasła NIE udało się zapisać — baza odrzuciła zapis. Skrzynka nadal używa poprzedniego hasła (albo żadnego).' );
			segm_skrzynka_wroc();
		}
		$komunikat = 'Zapisano ustawienia i nowe hasło aplikacji.';
	}

	if ( $uwagi ) {
		segm_skrzynka_zapamietaj( 'uwaga', $komunikat . ' ALE: ' . implode( ' ', $uwagi ) . ' Popraw pole i zapisz ponownie, zanim klikniesz „Sprawdź połączenie".' );
		segm_skrzynka_wroc();
	}
	segm_skrzynka_zapamietaj( 'ok', $komunikat . ' Kliknij „Sprawdź połączenie", żeby potwierdzić, że serwer je przyjmuje.' );
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_zapisz', 'segm_post_skrzynka_zapisz' );

/**
 * „Sprawdź połączenie".
 *
 * @return void
 */
function segm_post_skrzynka_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_test' );

	$wynik = segm_skrzynka_sprawdz();
	if ( ! $wynik['ok'] ) {
		segm_skrzynka_zapamietaj( 'blad', 'Połączenie NIE doszło do skutku. ' . $wynik['komunikat'], $wynik['dziennik'] );
		segm_skrzynka_wroc();
	}

	$dodatek = '';
	if ( ! empty( $wynik['foldery'] ) ) {
		$dodatek = ' Foldery: ' . implode( ', ', array_slice( $wynik['foldery'], 0, 25 ) ) . ( count( $wynik['foldery'] ) > 25 ? ' …' : '' ) . '.';
	}
	segm_skrzynka_zapamietaj( 'ok', $wynik['komunikat'] . $dodatek, $wynik['dziennik'] );
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_test', 'segm_post_skrzynka_test' );

/**
 * Zapis trybu pracy (na sucho / realnie).
 *
 * @return void
 */
function segm_post_skrzynka_tryb() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_tryb' );

	$tryb = isset( $_POST['tryb'] ) ? sanitize_key( wp_unslash( $_POST['tryb'] ) ) : 'na_sucho';
	if ( 'na_zywo' === $tryb && null === segm_zgoda_przenoszenia() ) {
		segm_skrzynka_zapamietaj( 'blad', 'Realne przenoszenie wymaga wcześniejszego potwierdzenia — tryb pozostał NA SUCHO.' );
		segm_skrzynka_wroc();
	}
	if ( ! in_array( $tryb, array( 'na_sucho', 'na_zywo' ), true ) ) {
		$tryb = 'na_sucho';
	}

	$ust         = segm_skrzynka_ustawienia();
	$ust['tryb'] = $tryb;
	if ( ! segm_zapisz_opcje( 'segm_skrzynka', $ust ) ) {
		segm_log_bledu_bazy( 'segm_post_skrzynka_tryb', 'tryb pracy skrzynki' );
		segm_skrzynka_zapamietaj( 'blad', 'Nie udało się zapisać trybu — baza odrzuciła zapis. Obowiązuje POPRZEDNI tryb.' );
		segm_skrzynka_wroc();
	}

	segm_skrzynka_zapamietaj(
		'ok',
		'na_zywo' === $tryb
			? 'Tryb: REALNE PRZENOSZENIE. Od tej chwili przebiegi naprawdę zmieniają foldery wiadomości na serwerze poczty.'
			: 'Tryb: NA SUCHO. Przebiegi pokazują tylko, co zrobiłyby — nic nie zostanie ruszone.'
	);
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_tryb', 'segm_post_skrzynka_tryb' );

/**
 * Zapis potwierdzenia realnego przenoszenia (data + login).
 *
 * @return void
 */
function segm_post_skrzynka_zgoda() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_zgoda' );

	if ( empty( $_POST['zgoda'] ) ) {
		segm_skrzynka_zapamietaj( 'blad', 'Potwierdzenie NIE zostało zapisane — pole nie było zaznaczone. Wtyczka pracuje dalej na sucho.' );
		segm_skrzynka_wroc();
	}

	$uzytkownik = wp_get_current_user();
	$wpis       = array(
		'kiedy' => current_time( 'mysql' ),
		'kto'   => $uzytkownik->user_login,
	);
	if ( ! segm_zapisz_opcje( 'segm_zgoda_przenoszenie', $wpis ) ) {
		segm_log_bledu_bazy( 'segm_post_skrzynka_zgoda', 'potwierdzenie realnego przenoszenia' );
		segm_skrzynka_zapamietaj( 'blad', 'Nie udało się zapisać potwierdzenia — baza odrzuciła zapis. Wtyczka pracuje dalej NA SUCHO (żadna wiadomość nie została ruszona).' );
		segm_skrzynka_wroc();
	}

	segm_skrzynka_zapamietaj( 'ok', 'Potwierdzenie zapisane. Realne przenoszenie możesz teraz włączyć w sekcji „Tryb pracy" — samo potwierdzenie jeszcze go NIE włącza.' );
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_zgoda', 'segm_post_skrzynka_zgoda' );

/**
 * Cofnięcie potwierdzenia — wtyczka wraca do trybu na sucho.
 *
 * @return void
 */
function segm_post_skrzynka_zgoda_cofnij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_zgoda_cofnij' );

	delete_option( 'segm_zgoda_przenoszenie' );
	if ( null !== segm_zgoda_przenoszenia() ) {
		segm_log_bledu_bazy( 'segm_post_skrzynka_zgoda_cofnij', 'cofnięcie potwierdzenia przenoszenia' );
		segm_skrzynka_zapamietaj( 'blad', 'Nie udało się cofnąć potwierdzenia — baza odrzuciła operację. Realne przenoszenie NADAL jest dozwolone.' );
		segm_skrzynka_wroc();
	}

	$ust         = segm_skrzynka_ustawienia();
	$ust['tryb'] = 'na_sucho';
	segm_zapisz_opcje( 'segm_skrzynka', $ust );

	segm_skrzynka_zapamietaj( 'ok', 'Potwierdzenie cofnięte — wtyczka wróciła do trybu NA SUCHO.' );
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_zgoda_cofnij', 'segm_post_skrzynka_zgoda_cofnij' );

/**
 * Zapis harmonogramu.
 *
 * @return void
 */
function segm_post_skrzynka_harmonogram() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_harmonogram' );

	$wybrana  = isset( $_POST['czestotliwosc'] ) ? sanitize_key( wp_unslash( $_POST['czestotliwosc'] ) ) : 'wylaczone';
	$dostepne = segm_skrzynka_czestotliwosci();
	if ( ! isset( $dostepne[ $wybrana ] ) ) {
		$wybrana = 'wylaczone';
	}

	$ust                  = segm_skrzynka_ustawienia();
	$ust['czestotliwosc'] = $wybrana;
	if ( ! segm_zapisz_opcje( 'segm_skrzynka', $ust ) ) {
		segm_log_bledu_bazy( 'segm_post_skrzynka_harmonogram', 'częstotliwość segregacji cyklicznej' );
		segm_skrzynka_zapamietaj( 'blad', 'Nie udało się zapisać harmonogramu — baza odrzuciła zapis. Obowiązuje POPRZEDNIE ustawienie.' );
		segm_skrzynka_wroc();
	}
	segm_skrzynka_przeplanuj();

	segm_skrzynka_zapamietaj( 'ok', segm_skrzynka_opis_harmonogramu() );
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_harmonogram', 'segm_post_skrzynka_harmonogram' );

/**
 * „Uruchom teraz".
 *
 * @return void
 */
function segm_post_skrzynka_uruchom() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_uruchom' );

	$wynik = segm_skrzynka_przebieg( 'reczny' );
	if ( '' !== $wynik['blad'] ) {
		segm_skrzynka_zapamietaj( 'blad', $wynik['blad'] );
		segm_skrzynka_wroc();
	}

	$tekst = 'na_zywo' === $wynik['tryb']
		? sprintf(
			'Przebieg #%d (realny): sprawdzono %d wiadomości, reguły dopasowały %d, przeniesiono %d, błędów %d.',
			$wynik['id'],
			$wynik['sprawdzonych'],
			$wynik['dopasowanych'],
			$wynik['przeniesionych'],
			$wynik['bledow']
		)
		: sprintf(
			'Przebieg #%d NA SUCHO: sprawdzono %d wiadomości, reguły dopasowały %d — tyle wiadomości zmieniłoby folder. Na serwerze poczty NIC się nie zmieniło. Szczegóły w historii poniżej.',
			$wynik['id'],
			$wynik['sprawdzonych'],
			$wynik['dopasowanych']
		);
	if ( '' !== $wynik['komunikat'] ) {
		$tekst .= ' ' . $wynik['komunikat'];
	}

	segm_skrzynka_zapamietaj( $wynik['bledow'] > 0 ? 'uwaga' : 'ok', $tekst );
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_uruchom', 'segm_post_skrzynka_uruchom' );

/**
 * Cofnięcie ostatniego przebiegu.
 *
 * @return void
 */
function segm_post_skrzynka_cofnij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_skrzynka_cofnij' );

	$id    = isset( $_POST['przebieg'] ) ? (int) $_POST['przebieg'] : 0;
	$wynik = segm_skrzynka_cofnij( $id );
	if ( '' !== $wynik['blad'] ) {
		segm_skrzynka_zapamietaj( 'blad', $wynik['blad'] );
		segm_skrzynka_wroc();
	}

	segm_skrzynka_zapamietaj(
		$wynik['bledow'] > 0 ? 'uwaga' : 'ok',
		sprintf(
			'Cofnięto przebieg #%d: %d wiadomości wróciło na miejsce%s.',
			$id,
			$wynik['cofnietych'],
			$wynik['bledow'] > 0 ? ', ale ' . $wynik['bledow'] . ' NIE udało się cofnąć — szczegóły przy pozycjach w historii' : ''
		)
	);
	segm_skrzynka_wroc();
}
add_action( 'admin_post_segm_skrzynka_cofnij', 'segm_post_skrzynka_cofnij' );
