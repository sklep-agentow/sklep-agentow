<?php
/**
 * Ekran powitalny pokazywany RAZ po włączeniu wtyczki.
 *
 * Problem, który rozwiązuje: klient wgrywa wtyczkę, klika „Włącz" i nic się nie
 * dzieje — musi sam znaleźć, gdzie są ustawienia. Po aktywacji przekierowujemy go
 * więc jeden raz na krótką stronę z trzema pierwszymi krokami.
 *
 * „Raz" jest liczone PER UŻYTKOWNIK w metadanych (`segm_powitanie_widziane`),
 * a nie globalną opcją: na stronie z kilkoma administratorami każdy ma prawo
 * zobaczyć wstęp u siebie, a nikt nie ma prawa oglądać go po raz drugi.
 * Znacznik trzyma numer wersji, więc przy dużej aktualizacji da się wstęp
 * pokazać ponownie, nie kasując niczego w bazie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug strony powitalnej.
 */
const SEGM_POWITANIE_STRONA = 'segm-powitanie';

/**
 * Nazwa metadanej z informacją, że ten użytkownik już widział wstęp.
 */
const SEGM_POWITANIE_META = 'segm_powitanie_widziane';

/**
 * Rejestracja strony — od razu zdejmowana z menu.
 *
 * Strona musi mieć rodzica, żeby WordPress w ogóle ją wyrenderował, ale nie ma
 * czego szukać w menu: wchodzi się na nią raz, z przekierowania.
 *
 * @return void
 */
function segm_powitanie_menu() {
	add_submenu_page(
		'segm-przebiegi',
		'Witamy w Segregatorze',
		'Witamy',
		'manage_options',
		SEGM_POWITANIE_STRONA,
		'segm_strona_powitanie'
	);
}
add_action( 'admin_menu', 'segm_powitanie_menu', 12 );

/**
 * Zdjęcie pozycji z menu (sama strona zostaje dostępna pod adresem).
 *
 * @return void
 */
function segm_powitanie_ukryj_w_menu() {
	remove_submenu_page( 'segm-przebiegi', SEGM_POWITANIE_STRONA );
}
add_action( 'admin_head', 'segm_powitanie_ukryj_w_menu' );

/**
 * Jednorazowe przekierowanie po aktywacji.
 *
 * @return void
 */
function segm_powitanie_przekieruj() {
	if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// Włączanie hurtem („zaznacz wszystkie → Włącz") nie może skończyć się
	// wyrzuceniem użytkownika z listy wtyczek w środku operacji.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko rozpoznanie, czy trwa włączanie hurtem; żadnych danych stąd nie zapisujemy.
	if ( isset( $_GET['activate-multi'] ) ) {
		return;
	}
	if ( ! get_option( 'segm_powitanie_gotowe' ) ) {
		return;
	}
	if ( SEGM_WERSJA === (string) get_user_meta( get_current_user_id(), SEGM_POWITANIE_META, true ) ) {
		return;
	}

	// Znacznik stawiamy PRZED przekierowaniem. Gdyby użytkownik zamknął kartę
	// zamiast kliknąć „zaczynam", wstęp i tak ma się nie odezwać ponownie —
	// „raz" znaczy raz, nie „do skutku".
	update_user_meta( get_current_user_id(), SEGM_POWITANIE_META, SEGM_WERSJA );

	wp_safe_redirect( admin_url( 'admin.php?page=' . SEGM_POWITANIE_STRONA ) );
	exit;
}
add_action( 'admin_init', 'segm_powitanie_przekieruj' );

/**
 * Treść strony powitalnej.
 *
 * @return void
 */
function segm_strona_powitanie() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$instrukcja   = plugins_url( 'INSTRUKCJA.md', SEGM_KATALOG . 'segregator-maila.php' );
	$dokumentacja = plugins_url( 'DOKUMENTACJA-TECHNICZNA.md', SEGM_KATALOG . 'segregator-maila.php' );
	?>
	<div class="wrap">
		<h1>Dziękujemy — Segregator maila jest włączony</h1>

		<p style="font-size:15px;max-width:760px">Wtyczka jest gotowa do pracy i ma już wgrane demo:
			5 kategorii, 8 przykładowych reguł i 30 polskich maili do testu. Nic nie łączy się
			jeszcze z Twoją skrzynką — <strong>tę decyzję podejmujesz sam, w kroku trzecim</strong>.</p>

		<h2>Trzy pierwsze kroki</h2>
		<ol style="font-size:14px;max-width:760px;line-height:1.7">
			<li><strong>Zobacz, jak to liczy.</strong> Uruchom przebieg na wbudowanej próbce —
				raport pokaże, ile poczty automat rozdzieli sam i przy których mailach się myli.<br>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=segm-przebiegi' ) ); ?>">Przepuść próbkę</a></li>
			<li><strong>Dopasuj reguły do swojej firmy.</strong> Reguła to „pole zawiera wzorzec →
				kategoria”, a kolejność rozstrzyga: pierwsza pasująca wygrywa.<br>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=segm-reguly' ) ); ?>">Otwórz reguły</a></li>
			<li><strong>Dopiero potem podłącz skrzynkę.</strong> Wtyczka zaczyna
				<strong>na sucho</strong>: pokazuje, co by przeniosła, i nie rusza ani jednej
				wiadomości, dopóki sam tego nie włączysz.<br>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=segm-skrzynka' ) ); ?>">Skrzynka (IMAP)</a></li>
		</ol>

		<h2>Gdzie szukać pomocy</h2>
		<p style="max-width:760px">
			<a href="<?php echo esc_url( $instrukcja ); ?>">INSTRUKCJA.md</a> — instrukcja użytkownika krok po kroku.<br>
			<a href="<?php echo esc_url( $dokumentacja ); ?>">DOKUMENTACJA-TECHNICZNA.md</a> — mapa systemu dla programisty i audytora.<br>
			Oba pliki leżą też w katalogu wtyczki, razem z <code>CHANGELOG.md</code>.
		</p>

		<p style="margin-top:28px">
			<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=segm-przebiegi' ) ); ?>">Zaczynam</a>
		</p>
		<p class="description">Ten ekran pokazuje się <strong>raz</strong>. Nie wróci przy kolejnych
			wejściach do panelu — a gdybyś chciał go jeszcze przejrzeć, jest pod adresem
			<code>admin.php?page=<?php echo esc_html( SEGM_POWITANIE_STRONA ); ?></code>.</p>
	</div>
	<?php
}
