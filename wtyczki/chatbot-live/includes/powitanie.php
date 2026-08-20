<?php
/**
 * Ekran powitalny po aktywacji — pierwsze pięć minut klienta z wtyczką.
 *
 * Bez tego klient klika „Włącz" i nie dzieje się nic: musi sam znaleźć menu,
 * sam zgadnąć, od czego zacząć. Płatna wtyczka tak się nie zachowuje.
 *
 * Zamknięcie jest zapamiętywane W METADANYCH UŻYTKOWNIKA, nie w opcji: na
 * jednej stronie bywa kilku administratorów i to, że jeden przeczytał
 * powitanie, nie znaczy, że pozostali je widzieli. Opcja globalna schowałaby
 * ekran przed wszystkimi po pierwszym kliknięciu kogokolwiek.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Klucz metadanych użytkownika: „ten człowiek już widział powitanie". */
const CHATL_META_POWITANIE = 'chatl_powitanie_zamkniete';

/* Znacznik „wtyczkę właśnie aktywowano" — krótkie życie, bo dotyczy JEDNEGO
	przekierowania zaraz po kliknięciu „Włącz", a nie stanu wtyczki. */
const CHATL_TRANSIENT_AKTYWACJA = 'chatl_po_aktywacji';

/**
 * Rejestruje stronę powitania.
 *
 * Strona ma istnieć (żeby dało się na nią wejść linkiem), ale nie ma zajmować
 * pozycji w menu — powitanie czyta się raz, a nie wraca do niego z listy.
 */
function chatl_menu_powitania() {
	add_submenu_page( 'chatl-qa', 'ChatBot LIVE — start', 'Start', 'manage_options', 'chatl-powitanie', 'chatl_strona_powitania' );
}
add_action( 'admin_menu', 'chatl_menu_powitania', 20 );

/**
 * Zdejmuje powitanie z MENU, ale dopiero na `admin_head`.
 *
 * Zmierzone: `remove_submenu_page()` wywołane jeszcze w `admin_menu` odbiera
 * stronie dostęp — WordPress ustala jej „hook" przez `get_admin_page_parent()`,
 * które szuka wpisu w `$submenu`; po usunięciu wpisu rodzic się nie znajduje,
 * hook wychodzi inny niż zarejestrowany i `admin.php` odpowiada
 * „Sorry, you are not allowed to access this page".
 *
 * Na `admin_head` strona jest już rozpoznana, a menu jeszcze nie narysowane —
 * pozycja znika z listy, a link nadal działa.
 */
function chatl_ukryj_powitanie_w_menu() {
	remove_submenu_page( 'chatl-qa', 'chatl-powitanie' );
}
add_action( 'admin_head', 'chatl_ukryj_powitanie_w_menu' );

/**
 * Przekierowanie na powitanie — DOKŁADNIE raz, zaraz po aktywacji.
 *
 * Warunki są cztery i każdy ma powód:
 *  - znacznik aktywacji (inaczej ekran wracałby przy każdym wejściu do panelu),
 *  - użytkownik jeszcze go nie zamknął (pamięć per konto),
 *  - uprawnienie administratora (powitanie prowadzi do ustawień),
 *  - NIE trwa zbiorcze włączanie wtyczek — przy „activate-multi" WordPress
 *    włącza kilka wtyczek naraz i przekierowanie ukradłoby ekran pozostałym.
 */
function chatl_moze_powitanie() {
	if ( ! get_transient( CHATL_TRANSIENT_AKTYWACJA ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt znacznika WordPressa o zbiorczej aktywacji; brak mutacji.
	if ( isset( $_GET['activate-multi'] ) ) {
		delete_transient( CHATL_TRANSIENT_AKTYWACJA );
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}

	delete_transient( CHATL_TRANSIENT_AKTYWACJA );

	if ( get_user_meta( get_current_user_id(), CHATL_META_POWITANIE, true ) ) {
		return; // Ten administrator już to widział i zamknął.
	}
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-powitanie' ) );
	exit;
}
add_action( 'admin_init', 'chatl_moze_powitanie' );

/**
 * Treść ekranu powitalnego: podziękowanie, trzy kroki, odnośniki.
 */
function chatl_strona_powitania() {
	$ile_qa = 0;
	global $wpdb;
	$ile_qa = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chatl_qa WHERE aktywne = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="wrap">
		<h1>Dziękujemy — ChatBot LIVE jest włączony</h1>
		<p style="font-size:15px;max-width:760px">
			Bot już stoi na Twojej stronie i odpowiada z bazy <strong><?php echo esc_html( number_format_i18n( $ile_qa ) ); ?></strong>
			gotowych par pytanie-odpowiedź. Żeby mówił o <em>Twojej</em> firmie, a nie o przykładowej, zrób trzy rzeczy — zajmą kwadrans.
		</p>

		<div style="display:flex;flex-wrap:wrap;gap:16px;margin:20px 0;max-width:1100px">
			<div style="flex:1 1 300px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:16px">
				<div style="color:#50575e;font-size:13px">Krok 1</div>
				<h2 style="margin:4px 0 8px">Ustaw teksty i mail</h2>
				<p>Powitanie, tekst na wypadek braku odpowiedzi i adres, na który mają przychodzić zgłoszenia z czatu. Tam też wybierzesz <strong>kolory i róg</strong>, w którym stoi widget.</p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-ustawienia' ) ); ?>">Przejdź do ustawień</a>
			</div>
			<div style="flex:1 1 300px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #00a32a;padding:16px">
				<div style="color:#50575e;font-size:13px">Krok 2</div>
				<h2 style="margin:4px 0 8px">Podmień bazę Q&amp;A na swoją</h2>
				<p>Przykładowe wpisy mówią o firmie z przykładu. Wpisz swoje ceny, godziny, adres i terminy — to od tego zależy, czy bot jest przydatny.</p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-qa' ) ); ?>">Otwórz bazę Q&amp;A</a>
			</div>
			<div style="flex:1 1 300px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #dba617;padding:16px">
				<div style="color:#50575e;font-size:13px">Krok 3</div>
				<h2 style="margin:4px 0 8px">Sprawdź i obserwuj luki</h2>
				<p>Przetestuj bota na własnych pytaniach, a po kilku dniach zajrzyj w <strong>Statystyki i luki</strong> — pokażą, o co pytają klienci, a bot nie umie odpowiedzieć.</p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-statystyki' ) ); ?>">Zobacz statystyki</a>
			</div>
		</div>

		<p>
			Pełna instrukcja po polsku jest w pliku <code>INSTRUKCJA.md</code> w paczce, opis techniczny
			(schemat bazy, stałe, punkty wejścia) w <code>DOKUMENTACJA-TECHNICZNA.md</code>, a historia zmian
			w <code>CHANGELOG.md</code>.
		</p>

		<p style="margin-top:24px">
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_powitanie_zamknij' ), 'chatl_powitanie_zamknij' ) ); ?>">
				Rozumiem, zamknij ten ekran
			</a>
			<span class="description">Zamknięty nie wraca — także po ponownej aktywacji wtyczki. Pamiętamy to osobno dla każdego konta administratora.</span>
		</p>
	</div>
	<?php
}

/**
 * „Zamknij" — zapamiętanie decyzji tego administratora i powrót do pracy.
 */
function chatl_post_powitanie_zamknij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_powitanie_zamknij' );

	update_user_meta( get_current_user_id(), CHATL_META_POWITANIE, 1 );
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa' ) );
	exit;
}
add_action( 'admin_post_chatl_powitanie_zamknij', 'chatl_post_powitanie_zamknij' );
