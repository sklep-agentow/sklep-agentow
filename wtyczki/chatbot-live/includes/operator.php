<?php
/**
 * Panel operatora: lista żywych rozmów, przejęcie, okno rozmowy, zakończenie.
 *
 * Cała ta strona jest za `manage_options` i za noncem — punkt gościa jest publiczny
 * (i broni się sekretem sesji), ale punkty operatora nie mają prawa być dostępne
 * dla nikogo poza właścicielem witryny.
 *
 * @package chatbot-live
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ile rozmów pokazuje lista. Więcej nie mieści się na ekranie i nie ma sensu. */
const CHATL_LISTA_ROZMOW = 50;

/** Po ilu minutach bez słowa gościa rozmowa znika z listy „żywych". */
const CHATL_ROZMOWA_ZYWA_MIN = 30;

/**
 * Lista żywych rozmów do panelu.
 *
 * Jedno zapytanie z podzapytaniami, nie pętla po sesjach: przy 50 rozmowach
 * wariant „SELECT w pętli" to 101 zapytań na każde otwarcie strony, a panel
 * właściciela ma być tani nawet wtedy, gdy w sklepie jest ruch.
 *
 * @return array
 */
function chatl_zywe_rozmowy() {
	global $wpdb;
	$s = $wpdb->prefix . 'chatl_sesje';
	$r = $wpdb->prefix . 'chatl_rozmowy';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.sesja, s.stan, s.operator, s.aktywnosc, s.operator_akt,
				( SELECT tresc FROM {$r} WHERE sesja = s.sesja AND rola = 'gosc' ORDER BY id DESC LIMIT 1 ) AS ostatnie_pytanie,
				( SELECT utworzono FROM {$r} WHERE sesja = s.sesja AND rola = 'gosc' ORDER BY id DESC LIMIT 1 ) AS ostatnie_kiedy,
				( SELECT COUNT(*) FROM {$r} WHERE sesja = s.sesja ) AS ile_wiadomosci
			FROM {$s} s
			WHERE s.aktywnosc >= %s
			ORDER BY ( s.stan = 'czlowiek' ) DESC, s.aktywnosc DESC
			LIMIT %d",
			gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( CHATL_ROZMOWA_ZYWA_MIN * MINUTE_IN_SECONDS ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
			CHATL_LISTA_ROZMOW
		),
		ARRAY_A
	);
	// phpcs:enable
}

/**
 * „Ile czeka" w formie, którą człowiek czyta bez liczenia.
 *
 * @param string $kiedy Data z bazy.
 * @return string
 */
function chatl_ile_czeka( $kiedy ) {
	if ( empty( $kiedy ) ) {
		return '—';
	}
	$sek = current_time( 'timestamp' ) - strtotime( $kiedy ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	if ( $sek < 60 ) {
		return 'przed chwilą';
	}
	if ( $sek < HOUR_IN_SECONDS ) {
		return sprintf( '%d min', (int) floor( $sek / 60 ) );
	}
	return sprintf( '%d godz.', (int) floor( $sek / HOUR_IN_SECONDS ) );
}

/**
 * Wiadomości jednej rozmowy — dla okna operatora.
 *
 * @param string $sesja Sesja.
 * @param int    $od_id Bierz nowsze niż to id (0 = wszystkie).
 * @return array
 */
function chatl_rozmowa_wiadomosci( $sesja, $od_id = 0 ) {
	global $wpdb;
	$r = $wpdb->prefix . 'chatl_rozmowy';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, rola, tresc, utworzono FROM {$r} WHERE sesja = %s AND id > %d ORDER BY id ASC LIMIT 200",
			$sesja,
			(int) $od_id
		),
		ARRAY_A
	);
	// phpcs:enable
}

/**
 * Podstrona „Rozmowy na żywo".
 *
 * @return void
 */
function chatl_menu_rozmowy() {
	add_submenu_page(
		'chatl-qa',
		'Rozmowy na żywo',
		'Rozmowy na żywo',
		'manage_options',
		'chatl-rozmowy',
		'chatl_ekran_rozmowy'
	);
}
add_action( 'admin_menu', 'chatl_menu_rozmowy', 11 );

/**
 * Ekran: lista rozmów albo okno jednej rozmowy.
 *
 * @return void
 */
function chatl_ekran_rozmowy() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sam ODCZYT listy/rozmowy, bez zmiany stanu; każda akcja niżej ma własny nonce.
	$sesja = isset( $_GET['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_GET['sesja'] ) ) : '';

	if ( '' !== $sesja ) {
		chatl_ekran_jednej_rozmowy( $sesja );
		return;
	}
	?>
	<div class="wrap">
		<h1>Rozmowy na żywo</h1>
		<p class="description">
			Rozmowy z ostatnich <?php echo (int) CHATL_ROZMOWA_ZYWA_MIN; ?> minut. Po przejęciu rozmowy
			<strong>bot milczy</strong> w tej rozmowie, a gość widzi komunikat, że pisze do człowieka.
			Jeśli nie odpiszesz przez <?php echo (int) ( CHATL_PRZEJECIE_CISZA / 60 ); ?> minut, rozmowa
			wraca do bota — też z komunikatem dla gościa.
		</p>
		<?php chatl_komunikat_ok(); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Kto pisze</th>
					<th>Ostatnie pytanie gościa</th>
					<th>Czeka</th>
					<th>Wiadomości</th>
					<th>Akcja</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$rozmowy = chatl_zywe_rozmowy();
			if ( ! $rozmowy ) :
				?>
				<tr><td colspan="5">Nikt teraz nie rozmawia. Ta lista pokazuje rozmowy z ostatnich <?php echo (int) CHATL_ROZMOWA_ZYWA_MIN; ?> minut.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rozmowy as $w ) : ?>
				<?php
				$czlowiek = 'czlowiek' === $w['stan'];
				$kto      = $czlowiek ? (int) $w['operator'] : 0;
				$autor    = $kto ? get_userdata( $kto ) : null;
				?>
				<tr>
					<td>
						<?php if ( $czlowiek ) : ?>
							<strong style="color:#d63638">Człowiek</strong><br>
							<span class="description"><?php echo esc_html( $autor ? $autor->display_name : 'operator' ); ?></span>
						<?php else : ?>
							Bot
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( mb_substr( (string) $w['ostatnie_pytanie'], 0, 120 ) ); ?></td>
					<td><?php echo esc_html( chatl_ile_czeka( $w['ostatnie_kiedy'] ) ); ?></td>
					<td><?php echo (int) $w['ile_wiadomosci']; ?></td>
					<td>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-rozmowy&sesja=' . rawurlencode( $w['sesja'] ) ) ); ?>">
							<?php echo $czlowiek ? 'Otwórz' : 'Otwórz i przejmij'; ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Okno jednej rozmowy dla operatora.
 *
 * @param string $sesja Sesja.
 * @return void
 */
function chatl_ekran_jednej_rozmowy( $sesja ) {
	$w        = chatl_sesja_wiersz( $sesja );
	$stan     = chatl_stan_rozmowy( $sesja );
	$czlowiek = 'czlowiek' === $stan;
	?>
	<div class="wrap">
		<h1>Rozmowa na żywo</h1>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-rozmowy' ) ); ?>">&larr; Wróć do listy rozmów</a>
		</p>
		<?php chatl_komunikat_ok(); ?>
		<?php if ( null === $w ) : ?>
			<div class="notice notice-error"><p>Nie ma takiej rozmowy. Mogła zostać usunięta razem z historią.</p></div>
			</div>
			<?php
			return;
		endif;
		?>

		<p>
			<?php if ( $czlowiek ) : ?>
				<strong style="color:#d63638">Rozmowę prowadzi człowiek — bot w niej milczy.</strong>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_zakoncz&sesja=' . rawurlencode( $sesja ) ), 'chatl_przejecie' ) ); ?>">Zakończ i oddaj botowi</a>
			<?php else : ?>
				<strong>Rozmowę prowadzi bot.</strong>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_przejmij&sesja=' . rawurlencode( $sesja ) ), 'chatl_przejecie' ) ); ?>">Przejmij rozmowę</a>
			<?php endif; ?>
		</p>

		<div id="chatl-rozmowa" class="chatl-panel-rozmowa" data-sesja="<?php echo esc_attr( $sesja ); ?>" data-od="0">
			<?php foreach ( chatl_rozmowa_wiadomosci( $sesja ) as $m ) : ?>
				<?php chatl_panel_wiersz_rozmowy( $m ); ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $czlowiek ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chatl-panel-odpowiedz">
				<?php wp_nonce_field( 'chatl_przejecie' ); ?>
				<input type="hidden" name="action" value="chatl_operator_wyslij">
				<input type="hidden" name="sesja" value="<?php echo esc_attr( $sesja ); ?>">
				<?php
				/* Szkic po odmowie — odczyt JEDNORAZOWY: po wstawieniu do pola kasujemy przechowalnię,
					żeby ta sama treść nie wracała przy każdym kolejnym wejściu na rozmowę. */
				$chatl_szkic = (string) get_transient( chatl_klucz_szkicu_operatora( $sesja ) );
				if ( '' !== $chatl_szkic ) {
					delete_transient( chatl_klucz_szkicu_operatora( $sesja ) );
				}
				?>
				<textarea name="tresc" rows="3" class="large-text" placeholder="Napisz do gościa…" required><?php echo esc_textarea( $chatl_szkic ); ?></textarea>
				<?php submit_button( 'Wyślij do gościa', 'primary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p class="description">Żeby napisać do gościa, najpierw <strong>przejmij rozmowę</strong> — inaczej gość dostawałby naraz Twoją wiadomość i odpowiedź bota.</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Jeden wiersz rozmowy w panelu.
 *
 * @param array $m Wiersz.
 * @return void
 */
function chatl_panel_wiersz_rozmowy( $m ) {
	$etykiety = array(
		'gosc'     => 'Gość',
		'bot'      => 'Bot',
		'operator' => 'Ty',
		'system'   => 'System',
	);
	$rola     = isset( $etykiety[ $m['rola'] ] ) ? $etykiety[ $m['rola'] ] : $m['rola'];
	?>
	<div class="chatl-panel-wiadomosc chatl-panel-<?php echo esc_attr( $m['rola'] ); ?>" data-id="<?php echo (int) $m['id']; ?>">
		<span class="chatl-panel-kto"><?php echo esc_html( $rola ); ?></span>
		<span class="chatl-panel-tresc"><?php echo esc_html( $m['tresc'] ); ?></span>
		<span class="chatl-panel-czas"><?php echo esc_html( mysql2date( 'H:i', $m['utworzono'] ) ); ?></span>
	</div>
	<?php
}

/**
 * Assety panelu operatora — tylko na podstronie rozmów.
 *
 * Ładowanie ich na każdym ekranie panelu byłoby doładowywaniem cudzej pracy:
 * właściciel edytujący produkty nie ma powodu pobierać skryptu czatu.
 *
 * @param string $hak Identyfikator ekranu.
 * @return void
 */
function chatl_assety_operatora( $hak ) {
	if ( false === strpos( (string) $hak, 'chatl-rozmowy' ) ) {
		return;
	}
	wp_enqueue_style( 'chatl-operator', CHATL_URL . 'assets/operator.css', array(), CHATL_WERSJA );
	wp_enqueue_script( 'chatl-operator', CHATL_URL . 'assets/operator.js', array(), CHATL_WERSJA, true );
	wp_localize_script(
		'chatl-operator',
		'chatlPanel',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'chatl_panel_rozmowa' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'chatl_assety_operatora' );

/**
 * Przejęcie rozmowy z panelu.
 *
 * @return void
 */
function chatl_post_przejmij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_przejecie' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce sprawdzony linijkę wyżej.
	$sesja = isset( $_GET['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_GET['sesja'] ) ) : '';
	if ( '' === $sesja ) {
		chatl_przekieruj_blad( 'chatl-rozmowy', 'Nieprawidłowa rozmowa.' );
	}
	// „Przejęta" tylko wtedy, gdy przejęcie ZMIERZONO w bazie — inaczej operator
	// pisałby do rozmowy, w której dalej odpowiada bot.
	if ( ! chatl_przejmij( $sesja, get_current_user_id() ) ) {
		chatl_przekieruj_blad( 'chatl-rozmowy&sesja=' . rawurlencode( $sesja ), 'Nie udało się przejąć rozmowy — baza odrzuciła zapis. Rozmowa NADAL jest przy bocie; gość nie dostał komunikatu o konsultancie. Sprawdź stan bazy i spróbuj ponownie.' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-rozmowy&sesja=' . rawurlencode( $sesja ) . '&chatl_ok=przejeta' ) );
	exit;
}
add_action( 'admin_post_chatl_przejmij', 'chatl_post_przejmij' );

/**
 * Oddanie rozmowy botowi z panelu.
 *
 * @return void
 */
function chatl_post_zakoncz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_przejecie' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce sprawdzony linijkę wyżej.
	$sesja = isset( $_GET['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_GET['sesja'] ) ) : '';
	if ( '' === $sesja ) {
		chatl_przekieruj_blad( 'chatl-rozmowy', 'Nieprawidłowa rozmowa.' );
	}
	if ( ! chatl_oddaj_botowi( $sesja, 'Konsultant zakończył rozmowę — dalej pomaga bot.' ) ) {
		chatl_przekieruj_blad( 'chatl-rozmowy&sesja=' . rawurlencode( $sesja ), 'Nie udało się oddać rozmowy botowi — baza odrzuciła zapis. Rozmowa NADAL jest przy Tobie; gość nie dostał komunikatu o zakończeniu. Sprawdź stan bazy i spróbuj ponownie.' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-rozmowy&sesja=' . rawurlencode( $sesja ) . '&chatl_ok=oddana' ) );
	exit;
}
add_action( 'admin_post_chatl_zakoncz', 'chatl_post_zakoncz' );

/**
 * Przechowalnia szkicu operatora na czas przekierowania po odmowie (5 minut, per użytkownik
 * i per rozmowa). Wzorzec przeniesiony ze sklepu (`admin-produkty.php:384`), ten sam co przy
 * formularzu produktu i formularzu opinii: odrzut walidacji nie ma prawa kasować tego,
 * co człowiek napisał.
 *
 * @param string $sesja Identyfikator rozmowy.
 * @param string $tresc Treść wpisana przez operatora.
 * @return void
 */
function chatl_odloz_szkic_operatora( $sesja, $tresc ) {
	set_transient( chatl_klucz_szkicu_operatora( $sesja ), (string) $tresc, 5 * MINUTE_IN_SECONDS );
}

/**
 * Klucz przechowalni szkicu. Per użytkownik ORAZ per rozmowa — inaczej szkic z jednej rozmowy
 * wskoczyłby do drugiej, a operator wysłałby gościowi nie to zdanie i nie temu człowiekowi.
 *
 * @param string $sesja Identyfikator rozmowy.
 * @return string
 */
function chatl_klucz_szkicu_operatora( $sesja ) {
	return 'chatl_szkic_op_' . get_current_user_id() . '_' . md5( (string) $sesja );
}

/**
 * Handler wysyłki wiadomości operatora do gościa (admin-post).
 *
 * Waliduje uprawnienia, nonce i długość treści. Przy odrzucie z powodu limitu ODKŁADA
 * treść przez chatl_odloz_szkic_operatora(), żeby przekierowanie nie skasowało tego,
 * co człowiek napisał. Render rozmowy odczytuje szkic JEDEN raz
 * i od razu kasuje przechowalnię.
 *
 * @return void
 */
function chatl_post_operator_wyslij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_przejecie' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce sprawdzony linijkę wyżej.
	$sesja = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';
	$tresc = isset( $_POST['tresc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tresc'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( '' === $sesja || '' === trim( $tresc ) ) {
		chatl_przekieruj_blad( 'chatl-rozmowy', 'Pusta wiadomość albo nieprawidłowa rozmowa.' );
	}
	// Ten sam wzorzec co przy limicie pytania gościa: ciche `mb_substr` wyglądało dla operatora
	// jak sukces (przekierowanie bez błędu), a gość dostawał urwane zdanie. Odmowa mówi
	// liczbę i limit — dokładnie jak gościowi w `front.php`.
	$dlugosc = mb_strlen( $tresc );
	if ( $dlugosc > CHATL_MAX_WIADOMOSC ) {
		// ⛔ TREŚĆ WRACA DO POLA. Handler stoi za `admin-post` i kończy błąd przekierowaniem,
		// więc formularz rysuje się od nowa z pustym `textarea` — bez przechowalni operator
		// traciłby wszystko, co napisał, a komunikat kazałby mu „skrócić” coś, czego już nie ma.
		// To ten sam wzorzec, którym sklep zamknął odrzuty formularza produktu i formularza
		// opinii. Wzorzec: przechowalnia per użytkownik i per rozmowa na 5 minut, odczytana
		// przy renderze JEDEN raz. Widżet gościa nie traci treści (`assets/widget.js` — pole
		// z `maxLength` skraca wklejkę z ostrzeżeniem, a przy odmowie serwera treść zostaje w polu),
		// więc operator nie może mieć gorzej.
		chatl_odloz_szkic_operatora( $sesja, $tresc );
		chatl_przekieruj_blad(
			'chatl-rozmowy&sesja=' . rawurlencode( $sesja ),
			sprintf( 'Wiadomość ma %d znaków, a limit to %d — skróć ją i wyślij ponownie. Nic nie zostało wysłane, treść czeka w polu.', $dlugosc, CHATL_MAX_WIADOMOSC )
		);
	}
	// Pisanie do rozmowy, której się nie przejęło, dałoby gościowi dwa głosy naraz.
	if ( 'czlowiek' !== chatl_stan_rozmowy( $sesja ) ) {
		chatl_przekieruj_blad( 'chatl-rozmowy', 'Najpierw przejmij rozmowę — bez tego odpowiadałby też bot.' );
	}
	// 0 = wiadomość NIE trafiła do bazy, więc gość jej nie zobaczy — operator ma
	// to wiedzieć teraz, nie po ciszy po drugiej stronie.
	if ( 0 === chatl_operator_wiadomosc( $sesja, $tresc ) ) {
		chatl_przekieruj_blad( 'chatl-rozmowy&sesja=' . rawurlencode( $sesja ), 'Wiadomość NIE została wysłana — baza odrzuciła zapis. Gość jej nie zobaczy. Sprawdź stan bazy i wyślij ponownie.' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-rozmowy&sesja=' . rawurlencode( $sesja ) ) );
	exit;
}
add_action( 'admin_post_chatl_operator_wyslij', 'chatl_post_operator_wyslij' );

/**
 * AJAX dla panelu: co nowego w rozmowie (odświeżanie okna operatora).
 *
 * Osobny punkt od publicznego: ten jest za `manage_options` i noncem, tamten
 * za sekretem sesji. Wspólny punkt musiałby godzić dwa różne modele dostępu
 * i skończyłby się gałęzią „jeśli admin, to przepuść" wewnątrz publicznego kodu.
 *
 * @return void
 */
function chatl_ajax_panel_rozmowa() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'komunikat' => 'Brak uprawnień.' ), 403 );
	}
	check_ajax_referer( 'chatl_panel_rozmowa' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce sprawdzony linijkę wyżej.
	$sesja = isset( $_POST['sesja'] ) ? chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) ) : '';
	$od_id = isset( $_POST['od_id'] ) ? absint( wp_unslash( $_POST['od_id'] ) ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( '' === $sesja ) {
		wp_send_json_error( array( 'komunikat' => 'Nieprawidłowa rozmowa.' ), 400 );
	}

	$nowe = array();
	foreach ( chatl_rozmowa_wiadomosci( $sesja, $od_id ) as $m ) {
		$nowe[] = array(
			'id'    => (int) $m['id'],
			'rola'  => $m['rola'],
			'tresc' => (string) $m['tresc'],
			'czas'  => mysql2date( 'H:i', $m['utworzono'] ),
		);
	}
	wp_send_json_success(
		array(
			'stan' => chatl_stan_rozmowy( $sesja ),
			'nowe' => $nowe,
		)
	);
}
add_action( 'wp_ajax_chatl_panel_rozmowa', 'chatl_ajax_panel_rozmowa' );
