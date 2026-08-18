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
	add_submenu_page( 'chatl-qa', 'Zgłoszenia z czatu', 'Zgłoszenia', 'manage_options', 'chatl-zgloszenia', 'chatl_strona_zgloszenia' );
	add_submenu_page( 'chatl-qa', 'Historia rozmów', 'Historia rozmów', 'manage_options', 'chatl-historia', 'chatl_strona_historia' );
	add_submenu_page( 'chatl-qa', 'Ustawienia ChatBota', 'Ustawienia', 'manage_options', 'chatl-ustawienia', 'chatl_strona_ustawienia' );
}
add_action( 'admin_menu', 'chatl_admin_menu' );

/**
 * Komunikat sukcesu po powrocie z admin-post (?chatl_ok=...).
 */
function chatl_komunikat_ok() {
	if ( ! isset( $_GET['chatl_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$teksty = array(
		'zapisano'  => 'Zapisano.',
		'usunieto'  => 'Usunięto.',
		'zmieniono' => 'Zmieniono status.',
	);
	$klucz = sanitize_key( wp_unslash( $_GET['chatl_ok'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $teksty[ $klucz ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $teksty[ $klucz ] ) . '</p></div>';
	}
}

/* ===================== BAZA Q&A ===================== */

function chatl_strona_qa() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_qa';

	$edytowany = null;
	if ( isset( $_GET['edytuj'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edytowany = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id = %d", (int) $_GET['edytuj'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.NonceVerification.Recommended
	}
	$wpisy = $wpdb->get_results( "SELECT * FROM {$tabela} ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="wrap">
		<h1>Baza pytań i odpowiedzi</h1>
		<?php chatl_komunikat_ok(); ?>
		<p>Bot dopasowuje pytanie gościa po <strong>słowach kluczowych z wariantami</strong> (rozdzielaj przecinkami, np. <code>cena, ceny, cennik</code>). Odmiany słów łapiesz, wpisując warianty.</p>

		<h2><?php echo $edytowany ? 'Edytuj wpis #' . (int) $edytowany->id : 'Dodaj nowy wpis'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="chatl_qa_zapisz">
			<?php wp_nonce_field( 'chatl_qa_zapisz' ); ?>
			<input type="hidden" name="id" value="<?php echo $edytowany ? (int) $edytowany->id : 0; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="chatl-pytanie">Pytanie wzorcowe</label></th>
					<td><input type="text" id="chatl-pytanie" name="pytanie" class="large-text" required
						value="<?php echo $edytowany ? esc_attr( $edytowany->pytanie ) : ''; ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="chatl-slowa">Słowa kluczowe (warianty po przecinku)</label></th>
					<td><input type="text" id="chatl-slowa" name="slowa_kluczowe" class="large-text" required
						value="<?php echo $edytowany ? esc_attr( $edytowany->slowa_kluczowe ) : ''; ?>"></td>
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
		$wpdb->update( $tabela, $dane, array( 'id' => $id ), array( '%s', '%s', '%s', '%d' ), array( '%d' ) );
	} else {
		$dane['utworzono'] = current_time( 'mysql' );
		$wpdb->insert( $tabela, $dane, array( '%s', '%s', '%s', '%d', '%s' ) );
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
	$wpdb->delete( $wpdb->prefix . 'chatl_qa', array( 'id' => $id ), array( '%d' ) );

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
	$wpdb->update( $wpdb->prefix . 'chatl_zgloszenia', array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

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
	$wpdb->delete( $wpdb->prefix . 'chatl_zgloszenia', array( 'id' => $id ), array( '%d' ) );

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-zgloszenia&chatl_ok=usunieto' ) );
	exit;
}
add_action( 'admin_post_chatl_zgloszenie_usun', 'chatl_post_zgloszenie_usun' );

/* ===================== HISTORIA ROZMÓW ===================== */

function chatl_strona_historia() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_rozmowy';

	// Self-heal retencji: WP-cron to pseudo-cron (bez ruchu nie odpala), więc
	// wejście admina na Historię też czyści wpisy starsze niż 90 dni (idempotentne).
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
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-historia' ) ); ?>">← wróć do listy rozmów</a></p>
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
		<p>Zapis wymian bot↔gość (ostatnie 100 rozmów). Historia starsza niż <strong>90 dni</strong> jest czyszczona automatycznie.</p>
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
				<tr><th colspan="2"><h2>Tryb AI (hybryda)</h2>
					<p style="font-weight:normal">Z kluczem API odpowiedzi generuje prawdziwy model (Claude) na podstawie informacji o firmie i bazy Q&amp;A — z zakazem zgadywania (niepewny zbiera kontakt). Bez klucza, po wyczerpaniu limitu albo przy błędzie API bot działa w trybie regułowym.</p></th></tr>
				<tr>
					<th scope="row"><label for="chatl-klucz">Klucz API (Anthropic)</label></th>
					<td>
						<?php $klucz = chatl_klucz_api(); ?>
						<?php if ( '' !== $klucz ) : ?>
							<p><strong>Klucz zapisany</strong> (…<?php echo esc_html( substr( $klucz, -4 ) ); ?>).
								<label><input type="checkbox" name="usun_klucz" value="1"> usuń klucz (wyłącza tryb AI)</label></p>
						<?php endif; ?>
						<input type="password" id="chatl-klucz" name="klucz_api" class="regular-text" value="" autocomplete="off"
							placeholder="<?php echo '' !== $klucz ? 'wklej, aby podmienić' : 'sk-ant-…'; ?>">
						<p class="description">Klucz WŁAŚCICIELA strony — przechowywany tylko w Twojej bazie (bez autoload), nigdzie nie wysyłany poza api.anthropic.com.</p>
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
						<p class="description">Dzisiaj zużyto: <strong><?php echo (int) chatl_ai_uzycie_dzis(); ?> / <?php echo (int) $ust['ai_limit_dzienny']; ?></strong> wywołań. Po wyczerpaniu limitu bot do północy odpowiada z reguł.</p>
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
		<p>Tryb regułowy: treść rozmów <strong>nie opuszcza</strong> Twojego WordPressa — dopasowanie działa lokalnie. Tryb AI: treść pytania gościa (wraz z informacjami o firmie i bazą Q&amp;A) jest wysyłana do API dostawcy modelu (Anthropic) — poinformuj o tym w swojej polityce prywatności. Historia rozmów jest przechowywana 90 dni.</p>
	</div>
	<?php
}

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
	);

	// Klucz API: osobna opcja (autoload=no), nigdy nie wraca do formularza.
	if ( ! empty( $_POST['usun_klucz'] ) ) {
		chatl_zapisz_klucz( '' );
	} elseif ( isset( $_POST['klucz_api'] ) && '' !== trim( (string) wp_unslash( $_POST['klucz_api'] ) ) ) {
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
