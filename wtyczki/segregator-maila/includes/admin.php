<?php
/**
 * Admin: przebiegi + raport, reguły (kolejność = pierwsza wygrywa), kategorie,
 * próbka maili (demo / import CSV), ustawienia.
 * Każda mutacja: nonce + capability manage_options (admin-post + redirect).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function segm_admin_menu() {
	add_menu_page( 'Segregator maila', 'Segregator maila', 'manage_options', 'segm-przebiegi', 'segm_strona_przebiegi', 'dashicons-email-alt', 59 );
	add_submenu_page( 'segm-przebiegi', 'Przebiegi i raporty', 'Przebiegi', 'manage_options', 'segm-przebiegi', 'segm_strona_przebiegi' );
	add_submenu_page( 'segm-przebiegi', 'Reguły segregacji', 'Reguły', 'manage_options', 'segm-reguly', 'segm_strona_reguly' );
	add_submenu_page( 'segm-przebiegi', 'Kategorie', 'Kategorie', 'manage_options', 'segm-kategorie', 'segm_strona_kategorie' );
	add_submenu_page( 'segm-przebiegi', 'Próbka maili', 'Próbka maili', 'manage_options', 'segm-probka', 'segm_strona_probka' );
	add_submenu_page( 'segm-przebiegi', 'Ustawienia Segregatora', 'Ustawienia', 'manage_options', 'segm-ustawienia', 'segm_strona_ustawienia' );
}
add_action( 'admin_menu', 'segm_admin_menu' );

/**
 * Slugi stron wtyczki — jedno źródło prawdy dla menu, banera i powrotów.
 *
 * @return array<int, string>
 */
function segm_strony() {
	return array( 'segm-przebiegi', 'segm-reguly', 'segm-kategorie', 'segm-probka', 'segm-skrzynka', 'segm-ustawienia' );
}

/**
 * Baner przy pierwszym wejściu do panelu: uczciwie, czym ten bot JEST.
 *
 * Zarzut z pierwszej rundy krytyk brzmiał „nazywa się segregator, a nie
 * segreguje" — bo nazwa i karta produktu sugerują wpięcie do skrzynki.
 * Prawda musi stać na wierzchu, a nie kilka ekranów niżej, więc baner wisi na
 * KAŻDEJ stronie wtyczki, dopóki właściciel go nie schowa.
 *
 * Chowanie idzie przez zwykły odnośnik z nonce (meta użytkownika), a nie przez
 * „is-dismissible" — tamto zamyka baner tylko na jedno wyświetlenie i wraca
 * przy następnym wejściu, dopóki nie dołoży się do tego JS-a i AJAX-a.
 *
 * @return void
 */
function segm_baner_powitalny() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko rozpoznanie, na której stronie jesteśmy.
	$strona = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! in_array( $strona, segm_strony(), true ) ) {
		return;
	}
	if ( get_user_meta( get_current_user_id(), 'segm_baner_ukryty', true ) ) {
		return;
	}
	// Kto zobaczył pełnoekranowy ekran powitalny po aktywacji, ten przeczytał już
	// dokładnie to samo przesłanie — baner z tą samą treścią zaraz po kliknięciu
	// „Zaczynam" byłby powtórzeniem, nie przypomnieniem. Baner zostaje dla
	// instalacji zaktualizowanych PODMIANĄ PLIKÓW: tam nie ma aktywacji, więc
	// ekran powitalny się nie odzywa i to baner jest jedynym powitaniem.
	if ( get_user_meta( get_current_user_id(), SEGM_POWITANIE_META, true ) ) {
		return;
	}
	$schowaj = wp_nonce_url(
		admin_url( 'admin-post.php?action=segm_baner_ukryj&strona=' . $strona ),
		'segm_baner_ukryj'
	);
	?>
	<div class="notice notice-info">
		<p><strong>Zacznij od próbki, dopiero potem podłącz skrzynkę.</strong> Reguły sprawdzasz najpierw na PRÓBCE maili, którą wgrasz (albo na wbudowanym demo 30 maili): raport pokazuje z liczbami, ile poczty automat rozdzieli sam i gdzie się pomyli — <em>zanim</em> cokolwiek dotknie prawdziwej korespondencji.</p>
		<p>Gotowe reguły puszczasz na żywą skrzynkę w zakładce <strong>Skrzynka (IMAP)</strong>. <strong>Domyślnie działa tam tryb NA SUCHO</strong>: wtyczka pokazuje, co by przeniosła, i nie rusza ani jednej wiadomości, dopóki sam tego nie włączysz.
			<a href="<?php echo esc_url( $schowaj ); ?>">Rozumiem — nie pokazuj więcej</a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'segm_baner_powitalny' );

/**
 * Pasek „tryb AI nie działa" na stronach wtyczki.
 *
 * Zjazd do reguł jest cichy w raporcie z rozmysłem, ale właściciel PŁACI za
 * tryb AI i ma prawo wiedzieć, że od tygodnia liczą u niego same reguły.
 * Pasek gaśnie sam przy pierwszym udanym wywołaniu — nie trzeba go zamykać.
 *
 * @return void
 */
function segm_pasek_bledu_ai() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko rozpoznanie, na której stronie jesteśmy.
	$strona = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! in_array( $strona, segm_strony(), true ) ) {
		return;
	}
	$blad = segm_blad_ai();
	if ( null === $blad ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><strong>Tryb AI nie działa — przebiegi liczą się samymi regułami.</strong>
			<?php echo esc_html( segm_opis_bledu_ai( $blad['kod'] ) ); ?>
			Ostatnia nieudana próba: <?php echo esc_html( $blad['kiedy'] ); ?>.</p>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-ustawienia' ) ); ?>">Sprawdź klucz w Ustawieniach Segregatora</a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'segm_pasek_bledu_ai' );

/**
 * Schowanie banera dla bieżącego użytkownika (decyzja per osoba, nie globalna).
 *
 * @return void
 */
function segm_post_baner_ukryj() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_baner_ukryj' );

	$strona = isset( $_GET['strona'] ) ? sanitize_key( wp_unslash( $_GET['strona'] ) ) : 'segm-przebiegi';
	if ( ! in_array( $strona, segm_strony(), true ) ) {
		$strona = 'segm-przebiegi';
	}

	$uzytkownik = get_current_user_id();
	update_user_meta( $uzytkownik, 'segm_baner_ukryty', 1 );
	// Gdyby zapis nie przeszedł, baner po prostu wróci — ale niech właściciel
	// wie DLACZEGO wraca, zamiast klikać ten sam odnośnik w kółko.
	if ( ! get_user_meta( $uzytkownik, 'segm_baner_ukryty', true ) ) {
		segm_przekieruj_blad( $strona, 'Nie udało się zapamiętać, że baner ma zniknąć — baza odrzuciła zapis. Baner będzie się pokazywał dalej.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . $strona ) );
	exit;
}
add_action( 'admin_post_segm_baner_ukryj', 'segm_post_baner_ukryj' );

/**
 * Komunikat po powrocie z admin-post (?segm_ok=... / ?segm_blad=...).
 */
function segm_komunikaty() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- tylko odczyt komunikatu.
	if ( isset( $_GET['segm_ok'] ) ) {
		$teksty = array(
			'zapisano'      => 'Zapisano.',
			'usunieto'      => 'Usunięto.',
			'demo'          => 'Przywrócono próbkę demo (30 maili).',
			'wyczyszczono'  => 'Wyczyszczono: próbka i raporty przebiegów usunięte z bazy.',
			'zgoda'         => 'Zgoda zapisana — tryby AI są od teraz dostępne.',
			'zgoda_cofnij'  => 'Zgoda cofnięta — tryby AI są zablokowane. Próbka i raporty zostają bez zmian.',
			'mail_dodany'   => 'Mail dodany do próbki — reszta próbki bez zmian.',
			'mail_zapisany' => 'Mail zapisany.',
			'mail_usuniety' => 'Mail usunięty z próbki.',
		);
		$klucz  = sanitize_key( wp_unslash( $_GET['segm_ok'] ) );
		if ( isset( $teksty[ $klucz ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $teksty[ $klucz ] ) . '</p></div>';
		}
		if ( 'klucz_dziala' === $klucz ) {
			$opis = isset( $_GET['opis'] ) ? sanitize_text_field( wp_unslash( $_GET['opis'] ) ) : 'Klucz działa.';
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $opis ) . '</p></div>';
		}
		if ( 'import' === $klucz ) {
			$ile = isset( $_GET['ile'] ) ? (int) $_GET['ile'] : 0;
			$pom = isset( $_GET['pominieto'] ) ? (int) $_GET['pominieto'] : 0;
			$kod = isset( $_GET['kodowanie'] ) ? sanitize_text_field( wp_unslash( $_GET['kodowanie'] ) ) : '';
			$prz = ! empty( $_GET['przerwano'] );
			// Wykryte kodowanie mówimy wprost: gdy plik NIE był w UTF-8, to jest
			// jedyny moment, w którym właściciel może wychwycić, że detekcja
			// trafiła obok i polskie znaki poszły do bazy krzywo.
			$opis_kod = '';
			if ( 'UTF-8' === $kod ) {
				$opis_kod = ' Kodowanie pliku: UTF-8.';
			} elseif ( '' !== $kod ) {
				$opis_kod = ' Kodowanie pliku: ' . $kod . ' — polskie znaki przekonwertowano na UTF-8. Jeśli w tabeli poniżej widzisz krzaki, zapisz plik jako „CSV UTF-8" i zaimportuj ponownie.';
			}
			echo '<div class="notice notice-success is-dismissible"><p>Zaimportowano ' . (int) $ile . ' maili (próbka zastąpiona).' . ( $pom > 0 ? ' Pominięto pustych wierszy: ' . (int) $pom . '.' : '' ) . esc_html( $opis_kod ) . '</p></div>';
			if ( $prz ) {
				echo '<div class="notice notice-warning is-dismissible"><p>Plik ma więcej niż ' . (int) SEGM_IMPORT_MAX_WIERSZY . ' wierszy — wzięliśmy pierwsze ' . (int) SEGM_IMPORT_MAX_WIERSZY . ' i <strong>dalszej części pliku w ogóle nie czytaliśmy</strong> (dlatego import był szybki). Do testu reguł taka próbka wystarcza; jeśli chcesz sprawdzić inny fragment skrzynki, wytnij go w arkuszu i wgraj osobno.</p></div>';
			}
		}
	}
	if ( isset( $_GET['segm_blad'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['segm_blad'] ) ) ) . '</p></div>';
	}
	// phpcs:enable
}

/**
 * Powrót z admin-post z komunikatem błędu (zapis do bazy padł).
 * Operacja, której zapis nie przeszedł, NIE MOŻE kończyć się „Zapisano.".
 *
 * @param string $strona    Slug strony wtyczki (page=...).
 * @param string $komunikat Treść dla admina.
 * @return void
 */
function segm_przekieruj_blad( $strona, $komunikat ) {
	wp_safe_redirect( admin_url( 'admin.php?page=' . $strona . '&segm_blad=' . rawurlencode( $komunikat ) ) );
	exit;
}

/* ===================== PRZEBIEGI + RAPORT ===================== */

function segm_strona_przebiegi() {
	global $wpdb;

	if ( isset( $_GET['przebieg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		segm_widok_raportu( (int) $_GET['przebieg'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$przebiegi = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_przebiegi ORDER BY id DESC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$maili     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="wrap">
		<h1>Przebiegi segregacji</h1>
		<?php segm_komunikaty(); ?>
		<p>Segregator działa na próbce (<strong><?php echo (int) $maili; ?> maili</strong>) — test reguł PRZED podpięciem żywej skrzynki. Przebieg klasyfikuje każdy mail wg reguł (pierwsza pasująca wygrywa), reszta trafia do „<?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>".</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_przebieg_start">
			<?php wp_nonce_field( 'segm_przebieg_start' ); ?>
			<?php
			$ai_jest    = segm_ai_dostepny();
			$ai_zgoda   = segm_zgoda_ai_udzielona();
			$ai_za_duza = $maili > SEGM_AI_MAX_PROBKA;
			$ai_mozliwe = $ai_jest && $ai_zgoda && ! $ai_za_duza;
			?>
			<p>
				<label><input type="radio" name="tryb" value="reguly" checked> reguły (lokalnie, bez klucza, bez limitu próbki)</label><br>
				<label><input type="radio" name="tryb" value="ai" <?php disabled( ! $ai_mozliwe ); ?>> AI (klasyfikuje model; próbka do <?php echo (int) SEGM_AI_MAX_PROBKA; ?> maili)</label><br>
				<label><input type="radio" name="tryb" value="porownaj" <?php disabled( ! $ai_mozliwe ); ?>> porównaj oba (raport różnic — co AI łapie ponad reguły; próbka do <?php echo (int) SEGM_AI_MAX_PROBKA; ?> maili)</label>
			</p>
			<?php if ( ! $ai_zgoda ) : ?>
				<p class="description"><strong>Tryby AI zablokowane: brak zgody na wysyłkę próbki.</strong> W trybie AI treść maili z próbki — także korespondencja osób trzecich — wychodzi do zewnętrznego dostawcy modelu. Potwierdź to raz w <a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-ustawienia' ) ); ?>">Ustawieniach Segregatora</a>. Bez potwierdzenia przebieg AI się NIE uruchomi (to nie jest zjazd do reguł — to Twoja decyzja, której jeszcze nie ma).</p>
			<?php elseif ( ! $ai_jest ) : ?>
				<p class="description">Tryby AI nieaktywne: <?php echo '' === segm_klucz_api() ? 'brak klucza API (Ustawienia)' : 'dzienny limit wywołań wyczerpany'; ?>. Przy błędzie AI przebieg i tak zjeżdża do reguł.</p>
			<?php elseif ( $ai_za_duza ) : ?>
				<p class="description">Tryby AI nieaktywne: próbka ma <?php echo (int) $maili; ?> maili, a tryb AI obsługuje maks. <?php echo (int) SEGM_AI_MAX_PROBKA; ?> (jedno wywołanie API to do 18 s — timeout 9 s + retry — a typowy limit czasu żądania na hostingu to 30 s). Zmniejsz próbkę albo użyj trybu „reguły".</p>
			<?php endif; ?>
			<?php submit_button( 'Przepuść próbkę', 'primary', 'submit', false ); ?>
		</form>

		<h2>Historia przebiegów</h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Kiedy</th><th>Tryb</th><th>Maili</th><th>Przypisane (reguły/AI, poza „Reszta")</th><th>Pokrycie</th><th>Accuracy</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $przebiegi ) : ?>
				<tr><td colspan="8">Jeszcze nie było przebiegu — kliknij przycisk powyżej.</td></tr>
			<?php endif; ?>
			<?php foreach ( $przebiegi as $p ) : ?>
				<tr>
					<td><?php echo (int) $p->id; ?></td>
					<td><?php echo esc_html( $p->uruchomiono ); ?></td>
					<td><?php echo esc_html( $p->tryb ); ?></td>
					<td><?php echo (int) $p->maili; ?></td>
					<td><?php echo (int) $p->dopasowanych; ?></td>
					<td><?php echo esc_html( segm_procent( $p->dopasowanych, $p->maili ) ); ?></td>
					<td><?php echo $p->etykietowanych > 0 ? esc_html( segm_procent( $p->trafionych, $p->etykietowanych ) . ' (' . (int) $p->trafionych . '/' . (int) $p->etykietowanych . ')' ) : '—'; ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-przebiegi&przebieg=' . (int) $p->id ) ); ?>">Zobacz raport</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function segm_procent( $licznik, $mianownik ) {
	if ( (int) $mianownik <= 0 ) {
		return '—';
	}
	return number_format( 100 * (int) $licznik / (int) $mianownik, 1, ',', '' ) . '%';
}

function segm_widok_raportu( $id ) {
	global $wpdb;
	$p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_przebiegi WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $p ) {
		echo '<div class="wrap"><h1>Raport</h1><p>Nie ma takiego przebiegu.</p></div>';
		return;
	}
	$wyniki = json_decode( $p->wyniki, true );
	if ( ! is_array( $wyniki ) ) {
		$wyniki = array();
	}

	$per_kategoria = array();
	$pomylki       = array();
	$roznice       = array();
	$trafionych_ai = 0;
	foreach ( $wyniki as $w ) {
		$k = $w['kategoria'];
		if ( ! isset( $per_kategoria[ $k ] ) ) {
			$per_kategoria[ $k ] = 0;
		}
		++$per_kategoria[ $k ];
		if ( false === $w['trafiony'] ) {
			$pomylki[] = $w;
		}
		if ( 'porownaj' === $p->tryb ) {
			if ( ! empty( $w['roznica'] ) ) {
				$roznice[] = $w;
			}
			if ( true === ( $w['trafiony_ai'] ?? null ) ) {
				++$trafionych_ai;
			}
		}
	}
	arsort( $per_kategoria );

	$reguly_opis = array();
	foreach ( segm_pobierz_reguly() as $r ) {
		$reguly_opis[ (int) $r->id ] = '#' . (int) $r->kolejnosc . ' ' . $r->pole . ' zawiera „' . $r->wzorzec . '"';
	}
	$nazwy_trybow = array(
		'reguly'   => 'reguły',
		'ai'       => 'AI',
		'porownaj' => 'porównanie reguły vs AI',
	);
	?>
	<div class="wrap">
		<h1>Raport przebiegu #<?php echo (int) $p->id; ?> — <?php echo esc_html( $nazwy_trybow[ $p->tryb ] ?? $p->tryb ); ?> (<?php echo esc_html( $p->uruchomiono ); ?>)</h1>
		<?php segm_komunikaty(); ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-przebiegi' ) ); ?>">← wróć do przebiegów</a> |
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_eksport_csv&przebieg=' . (int) $p->id ), 'segm_eksport_' . (int) $p->id ) ); ?>">Eksport CSV</a></p>

		<h2>Liczby</h2>
		<ul style="list-style:disc;padding-left:20px">
			<li>Maili w próbce: <strong><?php echo (int) $p->maili; ?></strong></li>
			<li><?php echo 'ai' === $p->tryb ? 'Przypisane przez AI do konkretnej kategorii' : 'Złapane regułami'; ?>: <strong><?php echo (int) $p->dopasowanych; ?></strong> — pokrycie <strong><?php echo esc_html( segm_procent( $p->dopasowanych, $p->maili ) ); ?></strong> (tyle skrzynki automat rozdziela sam)</li>
			<li>W kuble „<?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>": <strong><?php echo (int) ( $p->maili - $p->dopasowanych ); ?></strong></li>
			<?php if ( $p->etykietowanych > 0 ) : ?>
				<li>ACCURACY<?php echo 'porownaj' === $p->tryb ? ' reguł' : ''; ?> (maile z etykietą <code>kategoria_oczekiwana</code>): <strong><?php echo esc_html( segm_procent( $p->trafionych, $p->etykietowanych ) ); ?></strong> (<?php echo (int) $p->trafionych; ?>/<?php echo (int) $p->etykietowanych; ?> trafień, pomyłek: <?php echo count( $pomylki ); ?>)</li>
				<?php if ( 'porownaj' === $p->tryb ) : ?>
					<li>ACCURACY AI: <strong><?php echo esc_html( segm_procent( $trafionych_ai, $p->etykietowanych ) ); ?></strong> (<?php echo (int) $trafionych_ai; ?>/<?php echo (int) $p->etykietowanych; ?> trafień)</li>
				<?php endif; ?>
			<?php else : ?>
				<li>Accuracy: brak etykiet w próbce (dodaj kolumnę <code>kategoria_oczekiwana</code> w CSV, żeby zmierzyć trafność).</li>
			<?php endif; ?>
			<?php if ( 'porownaj' === $p->tryb ) : ?>
				<li>Reguły vs AI: <strong><?php echo (int) ( $p->maili - count( $roznice ) ); ?></strong> zgodnych, <strong><?php echo count( $roznice ); ?></strong> różnic (<?php echo esc_html( segm_procent( count( $roznice ), $p->maili ) ); ?>)</li>
			<?php endif; ?>
		</ul>

		<?php if ( 'porownaj' === $p->tryb && $roznice ) : ?>
			<h2>Różnice reguły vs AI (<?php echo count( $roznice ); ?>) — co AI widzi inaczej niż reguły</h2>
			<table class="widefat striped">
				<thead><tr><th>Od</th><th>Temat</th><th>Reguły</th><th>AI</th><th>Etykieta</th></tr></thead>
				<tbody>
				<?php foreach ( $roznice as $w ) : ?>
					<tr>
						<td><?php echo esc_html( $w['nadawca'] ); ?></td>
						<td><?php echo esc_html( $w['temat'] ); ?></td>
						<td><?php echo esc_html( $w['kategoria'] ); ?></td>
						<td><strong><?php echo esc_html( $w['kategoria_ai'] ); ?></strong></td>
						<td><?php echo '' === $w['oczekiwana'] ? '—' : esc_html( $w['oczekiwana'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Trafienia per kategoria</h2>
		<table class="widefat striped" style="max-width:480px">
			<thead><tr><th>Kategoria</th><th>Maili</th><th>Udział</th></tr></thead>
			<tbody>
			<?php foreach ( $per_kategoria as $kat => $ile ) : ?>
				<tr><td><?php echo esc_html( $kat ); ?></td><td><?php echo (int) $ile; ?></td><td><?php echo esc_html( segm_procent( $ile, $p->maili ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pomylki ) : ?>
			<h2>Pomyłki (<?php echo count( $pomylki ); ?>)</h2>
			<table class="widefat striped">
				<thead><tr><th>Od</th><th>Temat</th><th>Przydzielono</th><th>Oczekiwano</th><th>Reguła</th></tr></thead>
				<tbody>
				<?php foreach ( $pomylki as $w ) : ?>
					<tr>
						<td><?php echo esc_html( $w['nadawca'] ); ?></td>
						<td><?php echo esc_html( $w['temat'] ); ?></td>
						<td><strong><?php echo esc_html( $w['kategoria'] ); ?></strong></td>
						<td><?php echo esc_html( $w['oczekiwana'] ); ?></td>
						<td><?php echo null === $w['regula_id'] ? 'żadna (kubeł domyślny)' : ( isset( $reguly_opis[ $w['regula_id'] ] ) ? esc_html( $reguly_opis[ $w['regula_id'] ] ) : 'reguła usunięta (#' . (int) $w['regula_id'] . ')' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Wszystkie wyniki (mail → kategoria → <?php echo 'ai' === $p->tryb ? 'AI' : 'reguła'; ?>)</h2>
		<table class="widefat striped">
			<thead><tr><th>Od</th><th>Temat</th><th>Kategoria</th>
			<?php
			if ( 'porownaj' === $p->tryb ) :
				?>
				<th>Kategoria (AI)</th><?php endif; ?><th>Która reguła</th><th>Etykieta</th></tr></thead>
			<tbody>
			<?php foreach ( $wyniki as $w ) : ?>
				<tr>
					<td><?php echo esc_html( $w['nadawca'] ); ?></td>
					<td><?php echo esc_html( $w['temat'] ); ?></td>
					<td><?php echo esc_html( $w['kategoria'] ); ?></td>
				<?php if ( 'porownaj' === $p->tryb ) : ?>
						<td><?php echo esc_html( $w['kategoria_ai'] ?? '' ); ?><?php echo ! empty( $w['roznica'] ) ? ' ⚠' : ''; ?></td>
					<?php endif; ?>
					<td><?php echo 'ai' === $p->tryb ? 'AI' : ( null === $w['regula_id'] ? '—' : ( isset( $reguly_opis[ $w['regula_id'] ] ) ? esc_html( $reguly_opis[ $w['regula_id'] ] ) : 'reguła usunięta (#' . (int) $w['regula_id'] . ')' ) ); ?></td>
					<td><?php echo '' === $w['oczekiwana'] ? '—' : esc_html( $w['oczekiwana'] ) . ( $w['trafiony'] ? ' ✔' : ' ✘' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function segm_post_przebieg_start() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_przebieg_start' );

	$tryb = isset( $_POST['tryb'] ) ? sanitize_key( wp_unslash( $_POST['tryb'] ) ) : 'reguly';
	if ( ! in_array( $tryb, array( 'reguly', 'ai', 'porownaj' ), true ) ) {
		$tryb = 'reguly';
	}

	$wynik = segm_wykonaj_przebieg( $tryb );
	if ( ! empty( $wynik['blad'] ) ) {
		// Próbka ponad limit AI: jawny komunikat, przebieg NIE wystartował.
		wp_safe_redirect( admin_url( 'admin.php?page=segm-przebiegi&segm_blad=' . rawurlencode( $wynik['blad'] ) ) );
		exit;
	}
	$adres = 'admin.php?page=segm-przebiegi&przebieg=' . (int) $wynik['id'];
	if ( ! empty( $wynik['ostrzezenie'] ) ) {
		// Ostrzeżenie o zmianie próbki w trakcie ma trafić NA RAPORT — raport
		// istnieje i jest do obejrzenia, tylko opisuje stan sprzed zmiany.
		$adres .= '&segm_blad=' . rawurlencode( (string) $wynik['ostrzezenie'] );
	} elseif ( $wynik['zjazd'] ) {
		$adres .= '&segm_blad=' . rawurlencode( 'AI nie odpowiedziało (błąd, limit albo brak klucza) — wykonano przebieg regułowy.' );
	}
	wp_safe_redirect( admin_url( $adres ) );
	exit;
}
add_action( 'admin_post_segm_przebieg_start', 'segm_post_przebieg_start' );

function segm_post_eksport_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['przebieg'] ) ? (int) $_GET['przebieg'] : 0;
	check_admin_referer( 'segm_eksport_' . $id );

	global $wpdb;
	$p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_przebiegi WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $p ) {
		wp_die( 'Nie ma takiego przebiegu.' );
	}
	$wyniki = json_decode( $p->wyniki, true );
	segm_eksportuj_csv( $p, is_array( $wyniki ) ? $wyniki : array() );
}
add_action( 'admin_post_segm_eksport_csv', 'segm_post_eksport_csv' );

/* ===================== REGUŁY ===================== */

function segm_strona_reguly() {
	global $wpdb;
	$reguly    = segm_pobierz_reguly();
	$kategorie = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_kategorie ORDER BY nazwa ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$edytowana = null;
	if ( isset( $_GET['edytuj'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edytowana = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_reguly WHERE id = %d", (int) $_GET['edytuj'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.NonceVerification.Recommended
	}

	// Wynik „Policz na próbce": wypełnia formularz tym, co właściciel wpisał,
	// żeby po policzeniu nie musiał wklepywać wzorca od nowa.
	$liczenie = segm_odbierz_liczenie();
	$wpisane  = array(
		'pole'         => $liczenie ? $liczenie['pole'] : ( $edytowana ? $edytowana->pole : '' ),
		'wzorzec'      => $liczenie ? $liczenie['wzorzec'] : ( $edytowana ? $edytowana->wzorzec : '' ),
		'kategoria_id' => $liczenie && $liczenie['kategoria_id'] > 0 ? (int) $liczenie['kategoria_id'] : ( $edytowana ? (int) $edytowana->kategoria_id : 0 ),
	);
	$pola     = array(
		'od'    => 'nadawca zawiera',
		'temat' => 'temat zawiera',
		'tresc' => 'treść zawiera',
	);
	?>
	<div class="wrap">
		<h1>Reguły segregacji</h1>
		<?php segm_komunikaty(); ?>
		<p>Kolumny <strong>Pasuje</strong> i <strong>Zgarnia</strong> liczą na BIEŻĄCEJ próbce: pierwsza mówi, do ilu maili pasuje sam wzorzec, druga — ile z nich reguła realnie bierze, bo wcześniejsza reguła mogła je zabrać. Różnica między tymi liczbami to najczęstsza przyczyna „reguła wygląda dobrze, a nic nie łapie".</p>
		<p><strong>Kolejność na liście rozstrzyga: pierwsza pasująca reguła wygrywa</strong> (jak filtry Gmail) — osobnego pola „priorytet" nie ma. Wzorzec to warianty po przecinku (np. <code>faktur, rachunek</code>) — wystarczy, że JEDEN pasuje jako fragment tekstu.</p>

		<h2><?php echo $edytowana ? 'Edytuj regułę #' . (int) $edytowana->id : 'Dodaj regułę'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_regula_zapisz">
			<?php wp_nonce_field( 'segm_regula_zapisz' ); ?>
			<input type="hidden" name="id" value="<?php echo $edytowana ? (int) $edytowana->id : 0; ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="segm-pole">Warunek</label></th>
					<td>
						<select id="segm-pole" name="pole">
							<?php foreach ( $pola as $klucz => $opis ) : ?>
								<option value="<?php echo esc_attr( $klucz ); ?>" <?php selected( $wpisane['pole'], $klucz ); ?>><?php echo esc_html( $opis ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="wzorzec" class="regular-text" required placeholder="warianty po przecinku"
							value="<?php echo esc_attr( $wpisane['wzorzec'] ); ?>" aria-label="Wzorzec (warianty po przecinku)">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-kategoria">→ kategoria</label></th>
					<td>
						<select id="segm-kategoria" name="kategoria_id" required>
							<?php foreach ( $kategorie as $k ) : ?>
								<option value="<?php echo (int) $k->id; ?>" <?php selected( $wpisane['kategoria_id'], (int) $k->id ); ?>><?php echo esc_html( $k->nazwa ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit" style="margin:0;padding:0">
				<?php submit_button( $edytowana ? 'Zapisz zmiany' : 'Dodaj regułę (na koniec listy)', 'primary', 'submit', false ); ?>
				<button type="submit" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					name="action" value="segm_regula_policz" formnovalidate>Policz na próbce (bez zapisywania)</button>
				<?php wp_nonce_field( 'segm_regula_policz', '_wpnonce_policz' ); ?>
				<?php if ( $edytowana ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-reguly' ) ); ?>" style="margin-left:8px">Anuluj edycję</a>
				<?php endif; ?>
			</p>
		</form>
		<?php if ( $liczenie ) : ?>
			<div class="notice notice-info inline" style="margin:12px 0"><p><strong>Licznik:</strong> <?php echo esc_html( $liczenie['opis'] ); ?></p></div>
		<?php endif; ?>

		<h2>Lista reguł (kolejność rozstrzygania)</h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Warunek</th><th>→ kategoria</th><th title="Do ilu maili z próbki pasuje sam wzorzec">Pasuje</th><th title="Ile maili ta reguła realnie zgarnia — pierwsza pasująca wygrywa">Zgarnia</th><th>Kolejność</th><th>Akcje</th></tr></thead>
			<tbody>
			<?php if ( ! $reguly ) : ?>
				<tr><td colspan="7">Brak reguł — wszystko poleci do „<?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>".</td></tr>
			<?php endif; ?>
			<?php
			$ostatnia = count( $reguly );
			$i        = 0;
			foreach ( $reguly as $r ) :
				++$i;
				?>
				<tr>
					<td><?php echo (int) $r->kolejnosc; ?></td>
					<td><?php echo esc_html( $pola[ $r->pole ] . ' „' . $r->wzorzec . '"' ); ?></td>
					<td><?php echo esc_html( $r->kategoria ); ?></td>
					<?php $trafienia = segm_policz_trafienia( $r->pole, $r->wzorzec, (int) $r->id ); ?>
					<td><?php echo (int) $trafienia['pasuje']; ?></td>
					<td<?php echo $trafienia['pasuje'] > 0 && 0 === $trafienia['wygrywa'] ? ' style="color:#996800"' : ''; ?>>
						<?php echo (int) $trafienia['wygrywa']; ?>
						<?php echo $trafienia['pasuje'] > 0 && 0 === $trafienia['wygrywa'] ? ' <span title="Wcześniejsza reguła zabiera te maile pierwsza">!</span>' : ''; ?>
					</td>
					<td>
						<?php if ( $i > 1 ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_regula_ruch&id=' . (int) $r->id . '&kierunek=gora' ), 'segm_regula_ruch_' . (int) $r->id ) ); ?>" aria-label="Przesuń wyżej">▲</a>
						<?php endif; ?>
						<?php if ( $i < $ostatnia ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_regula_ruch&id=' . (int) $r->id . '&kierunek=dol' ), 'segm_regula_ruch_' . (int) $r->id ) ); ?>" aria-label="Przesuń niżej">▼</a>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-reguly&edytuj=' . (int) $r->id ) ); ?>">Edytuj</a> |
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_regula_usun&id=' . (int) $r->id ), 'segm_regula_usun_' . (int) $r->id ) ); ?>"
							onclick="return confirm('Usunąć tę regułę?');">Usuń</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function segm_post_regula_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_regula_zapisz' );

	global $wpdb;
	$id           = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$pole         = isset( $_POST['pole'] ) ? sanitize_key( wp_unslash( $_POST['pole'] ) ) : '';
	$wzorzec      = isset( $_POST['wzorzec'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['wzorzec'] ) ), 0, SEGM_MAX_POLE ) : '';
	$kategoria_id = isset( $_POST['kategoria_id'] ) ? (int) $_POST['kategoria_id'] : 0;

	if ( ! in_array( $pole, array( 'od', 'temat', 'tresc' ), true ) || '' === $wzorzec ) {
		wp_die( 'Niepoprawna reguła.' );
	}
	$istnieje_kat = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_kategorie WHERE id = %d", $kategoria_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $istnieje_kat ) {
		wp_die( 'Nie ma takiej kategorii.' );
	}

	if ( $id > 0 ) {
		$wynik = $wpdb->update(
			$wpdb->prefix . 'segm_reguly',
			array(
				'pole'         => $pole,
				'wzorzec'      => $wzorzec,
				'kategoria_id' => $kategoria_id,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	} else {
		$max   = (int) $wpdb->get_var( "SELECT MAX(kolejnosc) FROM {$wpdb->prefix}segm_reguly" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wynik = $wpdb->insert(
			$wpdb->prefix . 'segm_reguly',
			array(
				'kolejnosc'    => $max + 1,
				'pole'         => $pole,
				'wzorzec'      => $wzorzec,
				'kategoria_id' => $kategoria_id,
				'utworzono'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
	}
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_post_regula_zapisz', 'reguła segregacji (id: ' . $id . ')' );
		segm_przekieruj_blad( 'segm-reguly', 'Nie udało się zapisać reguły — baza odrzuciła zapis. Reguła NIE została zachowana; przebieg policzy się bez niej.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-reguly&segm_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_segm_regula_zapisz', 'segm_post_regula_zapisz' );

function segm_post_regula_usun() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'segm_regula_usun_' . $id );

	global $wpdb;
	if ( false === $wpdb->delete( $wpdb->prefix . 'segm_reguly', array( 'id' => $id ), array( '%d' ) ) ) {
		segm_log_bledu_bazy( 'segm_post_regula_usun', 'usunięcie reguły (id: ' . $id . ')' );
		segm_przekieruj_blad( 'segm-reguly', 'Nie udało się usunąć reguły — baza odrzuciła operację. Reguła NADAL działa w przebiegach.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-reguly&segm_ok=usunieto' ) );
	exit;
}
add_action( 'admin_post_segm_regula_usun', 'segm_post_regula_usun' );

function segm_post_regula_ruch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'segm_regula_ruch_' . $id );
	$kierunek = isset( $_GET['kierunek'] ) ? sanitize_key( wp_unslash( $_GET['kierunek'] ) ) : '';

	global $wpdb;
	$biezaca = $wpdb->get_row( $wpdb->prepare( "SELECT id, kolejnosc FROM {$wpdb->prefix}segm_reguly WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $biezaca ) {
		if ( 'gora' === $kierunek ) {
			$sasiad = $wpdb->get_row( $wpdb->prepare( "SELECT id, kolejnosc FROM {$wpdb->prefix}segm_reguly WHERE kolejnosc < %d ORDER BY kolejnosc DESC LIMIT 1", $biezaca->kolejnosc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sasiad = $wpdb->get_row( $wpdb->prepare( "SELECT id, kolejnosc FROM {$wpdb->prefix}segm_reguly WHERE kolejnosc > %d ORDER BY kolejnosc ASC LIMIT 1", $biezaca->kolejnosc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $sasiad ) {
			// Zamiana kolejności to DWA zapisy: jeśli przejdzie tylko pierwszy,
			// dwie reguły mają tę samą kolejność i rozstrzyga id — kolejność
			// na ekranie przestaje odpowiadać kolejności rozstrzygania.
			$a = $wpdb->update( $wpdb->prefix . 'segm_reguly', array( 'kolejnosc' => (int) $sasiad->kolejnosc ), array( 'id' => (int) $biezaca->id ), array( '%d' ), array( '%d' ) );
			$b = $wpdb->update( $wpdb->prefix . 'segm_reguly', array( 'kolejnosc' => (int) $biezaca->kolejnosc ), array( 'id' => (int) $sasiad->id ), array( '%d' ), array( '%d' ) );
			if ( false === $a || false === $b ) {
				segm_log_bledu_bazy( 'segm_post_regula_ruch', 'zamiana kolejności reguł (' . (int) $biezaca->id . ' ↔ ' . (int) $sasiad->id . ')' );
				segm_przekieruj_blad( 'segm-reguly', 'Nie udało się zmienić kolejności reguł — baza odrzuciła zapis. Sprawdź kolejność na liście przed uruchomieniem przebiegu.' );
			}
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-reguly' ) );
	exit;
}
add_action( 'admin_post_segm_regula_ruch', 'segm_post_regula_ruch' );

/* ===================== KATEGORIE ===================== */

function segm_strona_kategorie() {
	global $wpdb;
	$kategorie = $wpdb->get_results(
		"SELECT k.*, (SELECT COUNT(*) FROM {$wpdb->prefix}segm_reguly r WHERE r.kategoria_id = k.id) AS regul
		 FROM {$wpdb->prefix}segm_kategorie k ORDER BY k.nazwa ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
	$edytowana = null;
	if ( isset( $_GET['edytuj'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edytowana = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_kategorie WHERE id = %d", (int) $_GET['edytuj'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.NonceVerification.Recommended
	}
	?>
	<div class="wrap">
		<h1>Kategorie</h1>
		<?php segm_komunikaty(); ?>
		<p>Do kategorii kierują reguły. Maile bez reguły trafiają do „<?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>" (kubeł wbudowany — działa nawet bez kategorii o tej nazwie).</p>

		<h2><?php echo $edytowana ? 'Zmień nazwę kategorii #' . (int) $edytowana->id : 'Dodaj kategorię'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_kategoria_zapisz">
			<?php wp_nonce_field( 'segm_kategoria_zapisz' ); ?>
			<input type="hidden" name="id" value="<?php echo $edytowana ? (int) $edytowana->id : 0; ?>">
			<input type="text" name="nazwa" class="regular-text" required maxlength="100" aria-label="Nazwa kategorii"
				value="<?php echo $edytowana ? esc_attr( $edytowana->nazwa ) : ''; ?>">
			<?php submit_button( $edytowana ? 'Zapisz nazwę' : 'Dodaj kategorię', 'primary', 'submit', false ); ?>
			<?php if ( $edytowana ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-kategorie' ) ); ?>">Anuluj</a>
			<?php endif; ?>
		</form>

		<h2>Lista</h2>
		<table class="widefat striped" style="max-width:640px">
			<thead><tr><th>Nazwa</th><th>Reguł kieruje tu</th><th>Akcje</th></tr></thead>
			<tbody>
			<?php foreach ( $kategorie as $k ) : ?>
				<tr>
					<td><?php echo esc_html( $k->nazwa ); ?></td>
					<td><?php echo (int) $k->regul; ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-kategorie&edytuj=' . (int) $k->id ) ); ?>">Zmień nazwę</a>
						<?php if ( 0 === (int) $k->regul ) : ?>
							| <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_kategoria_usun&id=' . (int) $k->id ), 'segm_kategoria_usun_' . (int) $k->id ) ); ?>"
								onclick="return confirm('Usunąć kategorię?');">Usuń</a>
						<?php else : ?>
							| <span title="Najpierw usuń/przepnij reguły">usuwanie zablokowane</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function segm_post_kategoria_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_kategoria_zapisz' );

	global $wpdb;
	$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$nazwa = isset( $_POST['nazwa'] ) ? sanitize_text_field( wp_unslash( $_POST['nazwa'] ) ) : '';
	if ( '' === $nazwa ) {
		wp_die( 'Nazwa jest wymagana.' );
	}
	$nazwa = mb_substr( $nazwa, 0, 100 );

	// Duplikat nazwy myliłby w liście rozwijanej reguł (rozstrzyga ID, ale UI po nazwie).
	$duplikat = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_kategorie WHERE LOWER(nazwa) = LOWER(%s) AND id != %d", $nazwa, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $duplikat > 0 ) {
		wp_safe_redirect( admin_url( 'admin.php?page=segm-kategorie&segm_blad=' . rawurlencode( 'Kategoria o tej nazwie już istnieje.' ) ) );
		exit;
	}

	if ( $id > 0 ) {
		$wynik = $wpdb->update( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => $nazwa ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	} else {
		$wynik = $wpdb->insert(
			$wpdb->prefix . 'segm_kategorie',
			array(
				'nazwa'     => $nazwa,
				'utworzono' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);
	}
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_post_kategoria_zapisz', 'kategoria „' . $nazwa . '" (id: ' . $id . ')' );
		segm_przekieruj_blad( 'segm-kategorie', 'Nie udało się zapisać kategorii — baza odrzuciła zapis. Kategoria NIE została zachowana.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-kategorie&segm_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_segm_kategoria_zapisz', 'segm_post_kategoria_zapisz' );

function segm_post_kategoria_usun() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'segm_kategoria_usun_' . $id );

	global $wpdb;
	$regul = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_reguly WHERE kategoria_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $regul > 0 ) {
		wp_safe_redirect( admin_url( 'admin.php?page=segm-kategorie&segm_blad=' . rawurlencode( 'Kategoria ma przypisane reguły — najpierw je usuń albo przepnij.' ) ) );
		exit;
	}
	if ( false === $wpdb->delete( $wpdb->prefix . 'segm_kategorie', array( 'id' => $id ), array( '%d' ) ) ) {
		segm_log_bledu_bazy( 'segm_post_kategoria_usun', 'usunięcie kategorii (id: ' . $id . ')' );
		segm_przekieruj_blad( 'segm-kategorie', 'Nie udało się usunąć kategorii — baza odrzuciła operację. Kategoria NADAL jest na liście.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-kategorie&segm_ok=usunieto' ) );
	exit;
}
add_action( 'admin_post_segm_kategoria_usun', 'segm_post_kategoria_usun' );

/* ===================== PRÓBKA MAILI ===================== */

function segm_strona_probka() {
	global $wpdb;
	$ile   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$maile = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_maile ORDER BY id ASC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$edytowany_mail = null;
	if ( isset( $_GET['edytuj_mail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edytowany_mail = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_maile WHERE id = %d", (int) $_GET['edytuj_mail'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.NonceVerification.Recommended
	}
	?>
	<div class="wrap">
		<h1>Próbka maili (<?php echo (int) $ile; ?>)</h1>
		<?php segm_komunikaty(); ?>
		<p>Segregator testujesz na próbce — bez podpinania żywej skrzynki. Możesz wgrać własny eksport CSV (kolumny: <code>od, temat, tresc</code>, opcjonalnie <code>kategoria_oczekiwana</code> — wtedy raport policzy accuracy). Separator: przecinek albo średnik (jak w polskim Excelu) — wykrywany automatycznie. Import <strong>zastępuje</strong> bieżącą próbkę; maks. <?php echo (int) SEGM_IMPORT_MAX_WIERSZY; ?> wierszy i <?php echo esc_html( size_format( SEGM_IMPORT_MAX_BAJTOW ) ); ?> na plik (po limicie wierszy czytanie jest przerywane — reszty pliku nie ruszamy).</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-bottom:12px">
			<input type="hidden" name="action" value="segm_import_csv">
			<?php wp_nonce_field( 'segm_import_csv' ); ?>
			<input type="file" name="plik_csv" accept=".csv,text/csv" required aria-label="Plik CSV z próbką">
			<?php submit_button( 'Importuj CSV (zastępuje próbkę)', 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_probka_demo">
			<?php wp_nonce_field( 'segm_probka_demo' ); ?>
			<?php submit_button( 'Przywróć próbkę demo (30 maili)', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Zastąpić bieżącą próbkę wbudowanym demo?');" ) ); ?>
		</form>

		<?php segm_formularz_maila( $edytowany_mail ); ?>

		<h2>Retencja danych (RODO)</h2>
		<p>Próbka to zwykle korespondencja Twoich klientów, czyli dane osobowe. Wtyczka kasuje ją sama:
			<strong>codzienne zadanie usuwa maile i raporty starsze niż <?php echo (int) SEGM_RETENCJA_DNI; ?> dni</strong>
			(<?php echo esc_html( segm_opis_najblizszego_czyszczenia() ); ?>). Nie chcesz czekać — wyczyść od ręki:</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_probka_wyczysc">
			<?php wp_nonce_field( 'segm_probka_wyczysc' ); ?>
			<?php submit_button( 'Wyczyść próbkę teraz (maile + raporty przebiegów)', 'delete', 'submit', false, array( 'onclick' => "return confirm('Usunąć CAŁĄ próbkę i WSZYSTKIE raporty przebiegów? Tego nie da się cofnąć. Kategorie i reguły zostają.');" ) ); ?>
		</form>
		<p class="description">Raporty kasujemy razem z próbką, bo trzymają nadawcę i temat każdego maila — czyszczenie samej tabeli maili zostawiałoby tę samą korespondencję w historii przebiegów. Kategorie, reguły i klucz API zostają nietknięte.</p>

		<h2>Maile w próbce<?php echo $ile > 200 ? ' (pierwsze 200)' : ''; ?></h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Źródło</th><th>Od</th><th>Temat</th><th>Treść (początek)</th><th>Etykieta</th><th>Akcje</th></tr></thead>
			<tbody>
			<?php foreach ( $maile as $m ) : ?>
				<tr>
					<td><?php echo (int) $m->id; ?></td>
					<td><?php echo esc_html( $m->zrodlo ); ?></td>
					<td><?php echo esc_html( $m->nadawca ); ?></td>
					<td><?php echo esc_html( $m->temat ); ?></td>
					<td><?php echo esc_html( wp_html_excerpt( $m->tresc, 90, '…' ) ); ?></td>
					<td><?php echo '' === $m->kategoria_oczekiwana ? '—' : esc_html( $m->kategoria_oczekiwana ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-probka&edytuj_mail=' . (int) $m->id ) ); ?>">Edytuj</a> |
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_mail_usun&id=' . (int) $m->id ), 'segm_mail_usun_' . (int) $m->id ) ); ?>"
							onclick="return confirm('Usunąć ten mail z próbki? Reszta próbki zostaje.');">Usuń</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function segm_post_import_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_import_csv' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- tmp_name z $_FILES, walidowane niżej.
	$tmp = isset( $_FILES['plik_csv']['tmp_name'] ) ? $_FILES['plik_csv']['tmp_name'] : '';
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_blad=' . rawurlencode( 'Nie wgrano pliku.' ) ) );
		exit;
	}

	// Atrybut accept=".csv" w formularzu to podpowiedź dla okna wyboru pliku,
	// nie zabezpieczenie — żądanie może przyjść bez formularza. Rozszerzenie
	// sprawdzamy tu, a to, czy plik NAPRAWDĘ jest tekstem, weryfikuje import
	// (odrzut danych binarnych i UTF-16).
	$nazwa = isset( $_FILES['plik_csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['plik_csv']['name'] ) ) : '';
	$typ   = wp_check_filetype(
		$nazwa,
		array(
			'csv' => 'text/csv',
			'txt' => 'text/plain',
		)
	);
	if ( ! in_array( $typ['ext'], array( 'csv', 'txt' ), true ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_blad=' . rawurlencode( 'Przyjmujemy tylko pliki .csv (albo .txt z zawartością CSV). Wgrany plik ma inne rozszerzenie — próbka NIE została zmieniona.' ) ) );
		exit;
	}

	$wynik = segm_importuj_csv( $tmp );
	if ( is_wp_error( $wynik ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_blad=' . rawurlencode( $wynik->get_error_message() ) ) );
		exit;
	}

	wp_safe_redirect(
		admin_url(
			'admin.php?page=segm-probka&segm_ok=import&ile=' . (int) $wynik['zaimportowano']
			. '&pominieto=' . (int) $wynik['pominieto']
			. '&przerwano=' . ( empty( $wynik['przerwano'] ) ? '0' : '1' )
			. '&kodowanie=' . rawurlencode( (string) $wynik['kodowanie'] )
		)
	);
	exit;
}
add_action( 'admin_post_segm_import_csv', 'segm_post_import_csv' );

function segm_post_probka_demo() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_probka_demo' );

	$zajeta = segm_zajmij_blokade( 'przywracanie próbki demo' );
	if ( true !== $zajeta ) {
		segm_przekieruj_blad( 'segm-probka', sprintf( 'Trwa inna operacja na próbce (%s) — nie ruszamy jej w połowie. Odczekaj chwilę i spróbuj ponownie.', $zajeta ) );
	}

	global $wpdb;
	if ( false === $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}segm_maile" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		segm_log_bledu_bazy( 'segm_post_probka_demo', 'czyszczenie próbki przed przywróceniem demo' );
		segm_zwolnij_blokade();
		segm_przekieruj_blad( 'segm-probka', 'Nie udało się wyczyścić próbki — baza odrzuciła operację. Próbka NIE została zmieniona.' );
	}
	$nieudane = segm_wstaw_probke_demo();
	segm_zwolnij_blokade();
	if ( $nieudane > 0 ) {
		segm_przekieruj_blad(
			'segm-probka',
			sprintf( 'Próbka demo jest NIEKOMPLETNA: baza odrzuciła %d z 30 maili. Raport z takiej próbki policzy się na niepełnych danych.', $nieudane )
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_ok=demo' ) );
	exit;
}
add_action( 'admin_post_segm_probka_demo', 'segm_post_probka_demo' );

/**
 * Kiedy retencja posprząta następnym razem — zdanie do panelu.
 * Gdy harmonogramu nie ma (np. WP-Cron wyłączony przez DISABLE_WP_CRON i nikt
 * go nie woła), NIE udajemy, że jest: właściciel ma to zobaczyć.
 *
 * @return string
 */
function segm_opis_najblizszego_czyszczenia() {
	$kiedy = wp_next_scheduled( 'segm_czyszczenie_probki' );
	if ( ! $kiedy ) {
		return 'uwaga: zadanie NIE jest zaplanowane — sprawdź, czy WP-Cron działa na tym hostingu';
	}
	return 'najbliższe czyszczenie: ' . wp_date( 'j.m.Y H:i', $kiedy );
}

/**
 * „Wyczyść próbkę teraz": usuwa maile i raporty przebiegów, czyli WSZYSTKO,
 * co trzyma cudzą korespondencję. Kategorie i reguły (konfiguracja właściciela)
 * zostają — po wyczyszczeniu można od razu wgrać nową próbkę.
 *
 * @return void
 */
function segm_post_probka_wyczysc() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_probka_wyczysc' );

	$zajeta = segm_zajmij_blokade( 'czyszczenie próbki na żądanie' );
	if ( true !== $zajeta ) {
		segm_przekieruj_blad( 'segm-probka', sprintf( 'Trwa inna operacja na próbce (%s) — nie ruszamy jej w połowie. Odczekaj chwilę i spróbuj ponownie.', $zajeta ) );
	}

	global $wpdb;
	if ( false === $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}segm_maile" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		segm_log_bledu_bazy( 'segm_post_probka_wyczysc', 'czyszczenie próbki na żądanie' );
		segm_zwolnij_blokade();
		segm_przekieruj_blad( 'segm-probka', 'Nie udało się wyczyścić próbki — baza odrzuciła operację. Maile NADAL są w bazie.' );
	}
	if ( false === $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}segm_przebiegi" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		segm_log_bledu_bazy( 'segm_post_probka_wyczysc', 'czyszczenie raportów przebiegów na żądanie' );
		segm_zwolnij_blokade();
		segm_przekieruj_blad( 'segm-probka', 'Próbka została wyczyszczona, ale raportów przebiegów NIE udało się usunąć — nadawcy i tematy maili nadal są w bazie. Sprawdź stan bazy i powtórz operację.' );
	}
	segm_zwolnij_blokade();

	wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_ok=wyczyszczono' ) );
	exit;
}
add_action( 'admin_post_segm_probka_wyczysc', 'segm_post_probka_wyczysc' );

/* ===================== USTAWIENIA ===================== */

function segm_strona_ustawienia() {
	$ust = segm_ustawienia();
	?>
	<div class="wrap">
		<h1>Ustawienia Segregatora</h1>
		<?php segm_komunikaty(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_ustawienia_zapisz">
			<?php wp_nonce_field( 'segm_ustawienia_zapisz' ); ?>
			<table class="form-table" role="presentation">
				<tr><th colspan="2"><h2>Tryb AI (hybryda)</h2>
					<p style="font-weight:normal">Z kluczem API klasyfikację może robić prawdziwy model (Claude) — do wyboru przy przebiegu: reguły / AI / porównaj oba. Bez klucza albo przy błędzie API segregator działa w trybie regułowym.</p></th></tr>
				<tr>
					<th scope="row"><label for="segm-klucz">Klucz API (Anthropic)</label></th>
					<td>
						<?php $klucz = segm_klucz_api(); ?>
						<?php if ( segm_klucz_ze_stalej() ) : ?>
							<p><strong>Klucz podany w konfiguracji serwera</strong> (…<?php echo esc_html( substr( $klucz, -4 ) ); ?>) — stałą <code><?php echo esc_html( SEGM_STALA_KLUCZA ); ?></code> w <code>wp-config.php</code>.</p>
							<input type="password" id="segm-klucz" class="regular-text" value="" autocomplete="off" disabled placeholder="klucz z wp-config.php">
							<p class="description">To pole jest zablokowane, bo stała ma pierwszeństwo nad bazą. Żeby zmienić albo wyłączyć klucz, popraw <code>wp-config.php</code>. Klucz trzymany poza bazą nie wychodzi z jej kopią zapasową — to sposób zalecany.</p>
						<?php else : ?>
							<?php if ( '' !== $klucz ) : ?>
								<p><strong>Klucz zapisany</strong> (…<?php echo esc_html( substr( $klucz, -4 ) ); ?>).
									<label><input type="checkbox" name="usun_klucz" value="1"> usuń klucz (wyłącza tryby AI)</label></p>
							<?php endif; ?>
							<input type="password" id="segm-klucz" name="klucz_api" class="regular-text" value="" autocomplete="off"
								placeholder="<?php echo '' !== $klucz ? 'wklej, aby podmienić' : 'sk-ant-…'; ?>">
							<p class="description">Klucz WŁAŚCICIELA — przechowywany tylko w Twojej bazie (bez autoload), wysyłany wyłącznie do api.anthropic.com.<br>
								Bezpieczniej: wpisz go stałą <code>define( '<?php echo esc_html( SEGM_STALA_KLUCZA ); ?>', 'sk-ant-…' );</code> w <code>wp-config.php</code> — wtedy klucz nie leży w bazie i nie wychodzi z jej kopią zapasową. Stała ma pierwszeństwo nad tym polem.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-ai-limit">Dzienny limit wywołań AI (stop-loss)</label></th>
					<td>
						<input type="number" id="segm-ai-limit" name="ai_limit_dzienny" min="1" max="100000" value="<?php echo (int) $ust['ai_limit_dzienny']; ?>">
						<p class="description">Jedna partia <?php echo (int) SEGM_AI_PARTIA; ?> maili = 1 wywołanie. Dzisiaj zużyto: <strong><?php echo (int) segm_ai_uzycie_dzis(); ?> / <?php echo (int) $ust['ai_limit_dzienny']; ?></strong>. Po wyczerpaniu limitu przebiegi AI zjeżdżają do reguł do północy. Limit jest miękki: równoległe wywołania mogą go minimalnie przekroczyć.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Usuwanie wtyczki</th>
					<td>
						<label><input type="checkbox" name="usun_dane" value="1" <?php checked( $ust['usun_dane'] ); ?>> przy odinstalowaniu usuń WSZYSTKIE dane (kategorie, reguły, próbka, przebiegi, klucz API, ustawienia)</label>
						<p class="description">Domyślnie dane zostają — możesz odinstalować i wrócić bez strat.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Zapisz ustawienia' ); ?>
		</form>
		<h2>Sprawdzenie klucza API</h2>
		<?php $ost_blad = segm_blad_ai(); ?>
		<?php if ( null !== $ost_blad ) : ?>
			<div class="notice notice-error inline" style="margin:12px 0">
				<p><strong>Ostatnie wywołanie AI nie powiodło się</strong> (<?php echo esc_html( $ost_blad['kiedy'] ); ?>, model <code><?php echo esc_html( $ost_blad['model'] ); ?></code>).<br>
					<?php echo esc_html( segm_opis_bledu_ai( $ost_blad['kod'] ) ); ?></p>
				<p><em>Odpowiedź dostawcy:</em> <code><?php echo esc_html( $ost_blad['komunikat'] ); ?></code></p>
			</div>
		<?php endif; ?>
		<p>Przebieg AI przy błędzie <strong>zjeżdża do reguł i kończy się wynikiem</strong>, a nie ekranem błędu — to celowe. Żeby jednak cisza nie trwała miesiącami, sprawdź klucz tutaj: wychodzi JEDNO wywołanie ze słowem „ping”, <strong>bez ani jednego maila z próbki</strong>.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_test_klucza">
			<?php wp_nonce_field( 'segm_test_klucza' ); ?>
			<?php submit_button( 'Przetestuj klucz API', 'secondary', 'submit', false, '' === segm_klucz_api() ? array( 'disabled' => 'disabled' ) : array() ); ?>
			<span class="description"><?php echo '' === segm_klucz_api() ? 'Najpierw zapisz klucz.' : 'Sprawdza poświadczenia i nazwę modelu — niczego nie klasyfikuje i nie zabiera jednostki z dziennego limitu AI.'; ?></span>
		</form>

		<h2>Zgoda na wysyłkę próbki do AI</h2>
		<?php $zgoda = segm_zgoda_ai(); ?>
		<?php if ( null === $zgoda ) : ?>
			<p><strong>Tryby AI są zablokowane</strong>, dopóki tego nie potwierdzisz. Powód: próbka to zwykle korespondencja Twoich klientów — <em>cudze</em> dane osobowe. W trybie AI nadawca, temat i treść każdego maila z próbki wychodzą z Twojego serwera do zewnętrznego dostawcy modelu (Anthropic, <code>api.anthropic.com</code>). Tryb „reguły" działa lokalnie i takiej zgody nie wymaga.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="segm_zgoda_zapisz">
				<?php wp_nonce_field( 'segm_zgoda_zapisz' ); ?>
				<p><label><input type="checkbox" name="zgoda" value="1" required>
					Potwierdzam, że treść maili z próbki (także korespondencja osób trzecich) będzie wysyłana do zewnętrznego dostawcy modelu w celu klasyfikacji, i że mam do tego podstawę — a przetwarzanie jest opisane w mojej polityce prywatności.</label></p>
				<?php submit_button( 'Zapisz zgodę i odblokuj tryby AI', 'primary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p><strong>Zgoda udzielona</strong>: <?php echo esc_html( $zgoda['kiedy'] ); ?>, użytkownik <code><?php echo esc_html( $zgoda['kto'] ); ?></code>. Tryby AI są odblokowane.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="segm_zgoda_cofnij">
				<?php wp_nonce_field( 'segm_zgoda_cofnij' ); ?>
				<?php submit_button( 'Cofnij zgodę (blokuje tryby AI)', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Cofnąć zgodę? Tryby AI przestaną działać do czasu ponownego potwierdzenia.');" ) ); ?>
			</form>
			<p class="description">Cofnięcie zgody blokuje wyłącznie tryby AI — próbka, reguły, raporty i klucz API zostają nietknięte. Zapis daty i loginu jest po to, żeby dało się odpowiedzieć na pytanie „kto i kiedy na to pozwolił".</p>
		<?php endif; ?>

		<h2>Prywatność</h2>
		<p>Tryb regułowy: treść maili <strong>nie opuszcza</strong> Twojego WordPressa — segregacja działa lokalnie na próbce. Tryby AI: treść maili z próbki (nadawca, temat, treść) jest wysyłana do API dostawcy modelu (Anthropic) — poinformuj o tym w swojej polityce prywatności. Wtyczka nie łączy się z żadną skrzynką pocztową — pracuje na próbce, którą sam wgrywasz.</p>
	</div>
	<?php
}

function segm_post_ustawienia_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_ustawienia_zapisz' );

	$stare = segm_ustawienia();
	$nowe  = array_merge(
		$stare,
		array(
			'usun_dane'        => empty( $_POST['usun_dane'] ) ? 0 : 1,
			'ai_limit_dzienny' => isset( $_POST['ai_limit_dzienny'] ) ? max( 1, min( 100000, (int) $_POST['ai_limit_dzienny'] ) ) : $stare['ai_limit_dzienny'],
		)
	);
	if ( ! segm_zapisz_opcje( 'segm_ustawienia', $nowe ) ) {
		segm_log_bledu_bazy( 'segm_post_ustawienia_zapisz', 'ustawienia wtyczki (limit AI, usuwanie danych)' );
		segm_przekieruj_blad( 'segm-ustawienia', 'Nie udało się zapisać ustawień — baza odrzuciła zapis. Obowiązują POPRZEDNIE ustawienia (m.in. dzienny limit wywołań AI).' );
	}

	// Klucz API: osobna opcja (autoload=no), nigdy nie wraca do formularza.
	if ( ! empty( $_POST['usun_klucz'] ) ) {
		segm_zapisz_klucz( '' );
	} elseif ( isset( $_POST['klucz_api'] ) && '' !== trim( (string) wp_unslash( $_POST['klucz_api'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- warunek tylko bada niepustość; zapisywana wartość przechodzi sanitize_text_field() linijkę niżej.
		segm_zapisz_klucz( sanitize_text_field( wp_unslash( $_POST['klucz_api'] ) ) );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-ustawienia&segm_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_segm_ustawienia_zapisz', 'segm_post_ustawienia_zapisz' );

/**
 * Zapis zgody na wysyłkę próbki do AI (data + login — do okazania przy audycie).
 *
 * @return void
 */
function segm_post_zgoda_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_zgoda_zapisz' );

	if ( empty( $_POST['zgoda'] ) ) {
		segm_przekieruj_blad( 'segm-ustawienia', 'Zgoda NIE została zapisana — pole potwierdzenia nie było zaznaczone. Tryby AI pozostają zablokowane.' );
	}

	$uzytkownik = wp_get_current_user();
	$wpis       = array(
		'kiedy' => current_time( 'mysql' ),
		'kto'   => $uzytkownik->user_login,
	);
	if ( ! segm_zapisz_opcje( 'segm_zgoda_ai', $wpis ) ) {
		segm_log_bledu_bazy( 'segm_post_zgoda_zapisz', 'zgoda na wysyłkę próbki do AI' );
		segm_przekieruj_blad( 'segm-ustawienia', 'Nie udało się zapisać zgody — baza odrzuciła zapis. Tryby AI pozostają ZABLOKOWANE (żaden mail nie wyszedł na zewnątrz).' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-ustawienia&segm_ok=zgoda' ) );
	exit;
}
add_action( 'admin_post_segm_zgoda_zapisz', 'segm_post_zgoda_zapisz' );

/**
 * „Przetestuj klucz API".
 *
 * @return void
 */
function segm_post_test_klucza() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_test_klucza' );

	$wynik = segm_ai_przetestuj_klucz();
	if ( ! $wynik['ok'] ) {
		segm_przekieruj_blad( 'segm-ustawienia', $wynik['komunikat'] );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-ustawienia&segm_ok=klucz_dziala&opis=' . rawurlencode( $wynik['komunikat'] ) ) );
	exit;
}
add_action( 'admin_post_segm_test_klucza', 'segm_post_test_klucza' );

/**
 * Cofnięcie zgody — tryby AI wracają do stanu zablokowanego.
 *
 * @return void
 */
function segm_post_zgoda_cofnij() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_zgoda_cofnij' );

	delete_option( 'segm_zgoda_ai' );
	// delete_option() zwraca false także dla opcji, której nie było — o tym, czy
	// zgoda naprawdę zniknęła, rozstrzyga odczyt, nie zwracana wartość.
	if ( segm_zgoda_ai_udzielona() ) {
		segm_log_bledu_bazy( 'segm_post_zgoda_cofnij', 'cofnięcie zgody na wysyłkę próbki do AI' );
		segm_przekieruj_blad( 'segm-ustawienia', 'Nie udało się cofnąć zgody — baza odrzuciła operację. Zgoda NADAL obowiązuje, tryby AI są dostępne. Sprawdź stan bazy i powtórz operację.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-ustawienia&segm_ok=zgoda_cofnij' ) );
	exit;
}
add_action( 'admin_post_segm_zgoda_cofnij', 'segm_post_zgoda_cofnij' );
