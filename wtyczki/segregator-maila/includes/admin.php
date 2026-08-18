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
 * Komunikat po powrocie z admin-post (?segm_ok=... / ?segm_blad=...).
 */
function segm_komunikaty() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- tylko odczyt komunikatu.
	if ( isset( $_GET['segm_ok'] ) ) {
		$teksty = array(
			'zapisano' => 'Zapisano.',
			'usunieto' => 'Usunięto.',
			'demo'     => 'Przywrócono próbkę demo (30 maili).',
		);
		$klucz = sanitize_key( wp_unslash( $_GET['segm_ok'] ) );
		if ( isset( $teksty[ $klucz ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $teksty[ $klucz ] ) . '</p></div>';
		}
		if ( 'import' === $klucz ) {
			$ile = isset( $_GET['ile'] ) ? (int) $_GET['ile'] : 0;
			$pom = isset( $_GET['pominieto'] ) ? (int) $_GET['pominieto'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>Zaimportowano ' . (int) $ile . ' maili (próbka zastąpiona).' . ( $pom > 0 ? ' Pominięto wierszy: ' . (int) $pom . '.' : '' ) . '</p></div>';
		}
	}
	if ( isset( $_GET['segm_blad'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['segm_blad'] ) ) ) . '</p></div>';
	}
	// phpcs:enable
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
			<?php submit_button( 'Przepuść próbkę przez reguły', 'primary', 'submit', false ); ?>
		</form>

		<h2>Historia przebiegów</h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Kiedy</th><th>Tryb</th><th>Maili</th><th>Złapane regułami</th><th>Pokrycie</th><th>Accuracy</th><th></th></tr></thead>
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
	foreach ( $wyniki as $w ) {
		$k = $w['kategoria'];
		if ( ! isset( $per_kategoria[ $k ] ) ) {
			$per_kategoria[ $k ] = 0;
		}
		$per_kategoria[ $k ]++;
		if ( false === $w['trafiony'] ) {
			$pomylki[] = $w;
		}
	}
	arsort( $per_kategoria );

	$reguly_opis = array();
	foreach ( segm_pobierz_reguly() as $r ) {
		$reguly_opis[ (int) $r->id ] = '#' . (int) $r->kolejnosc . ' ' . $r->pole . ' zawiera „' . $r->wzorzec . '"';
	}
	?>
	<div class="wrap">
		<h1>Raport przebiegu #<?php echo (int) $p->id; ?> (<?php echo esc_html( $p->uruchomiono ); ?>)</h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-przebiegi' ) ); ?>">← wróć do przebiegów</a> |
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=segm_eksport_csv&przebieg=' . (int) $p->id ), 'segm_eksport_' . (int) $p->id ) ); ?>">Eksport CSV</a></p>

		<h2>Liczby</h2>
		<ul style="list-style:disc;padding-left:20px">
			<li>Maili w próbce: <strong><?php echo (int) $p->maili; ?></strong></li>
			<li>Złapane regułami: <strong><?php echo (int) $p->dopasowanych; ?></strong> — pokrycie <strong><?php echo esc_html( segm_procent( $p->dopasowanych, $p->maili ) ); ?></strong> (tyle skrzynki automat rozdziela sam)</li>
			<li>Bez reguły (→ <?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>): <strong><?php echo (int) ( $p->maili - $p->dopasowanych ); ?></strong></li>
			<?php if ( $p->etykietowanych > 0 ) : ?>
				<li>ACCURACY (maile z etykietą <code>kategoria_oczekiwana</code>): <strong><?php echo esc_html( segm_procent( $p->trafionych, $p->etykietowanych ) ); ?></strong> (<?php echo (int) $p->trafionych; ?>/<?php echo (int) $p->etykietowanych; ?> trafień, pomyłek: <?php echo count( $pomylki ); ?>)</li>
			<?php else : ?>
				<li>Accuracy: brak etykiet w próbce (dodaj kolumnę <code>kategoria_oczekiwana</code> w CSV, żeby zmierzyć trafność).</li>
			<?php endif; ?>
		</ul>

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

		<h2>Wszystkie wyniki (mail → kategoria → reguła)</h2>
		<table class="widefat striped">
			<thead><tr><th>Od</th><th>Temat</th><th>Kategoria</th><th>Która reguła</th><th>Etykieta</th></tr></thead>
			<tbody>
			<?php foreach ( $wyniki as $w ) : ?>
				<tr>
					<td><?php echo esc_html( $w['nadawca'] ); ?></td>
					<td><?php echo esc_html( $w['temat'] ); ?></td>
					<td><?php echo esc_html( $w['kategoria'] ); ?></td>
					<td><?php echo null === $w['regula_id'] ? '—' : ( isset( $reguly_opis[ $w['regula_id'] ] ) ? esc_html( $reguly_opis[ $w['regula_id'] ] ) : 'reguła usunięta (#' . (int) $w['regula_id'] . ')' ); ?></td>
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

	$id = segm_wykonaj_przebieg();
	wp_safe_redirect( admin_url( 'admin.php?page=segm-przebiegi&przebieg=' . $id ) );
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
	$pola = array(
		'od'    => 'nadawca zawiera',
		'temat' => 'temat zawiera',
		'tresc' => 'treść zawiera',
	);
	?>
	<div class="wrap">
		<h1>Reguły segregacji</h1>
		<?php segm_komunikaty(); ?>
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
								<option value="<?php echo esc_attr( $klucz ); ?>" <?php selected( $edytowana && $edytowana->pole === $klucz ); ?>><?php echo esc_html( $opis ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="wzorzec" class="regular-text" required placeholder="warianty po przecinku"
							value="<?php echo $edytowana ? esc_attr( $edytowana->wzorzec ) : ''; ?>" aria-label="Wzorzec (warianty po przecinku)">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="segm-kategoria">→ kategoria</label></th>
					<td>
						<select id="segm-kategoria" name="kategoria_id" required>
							<?php foreach ( $kategorie as $k ) : ?>
								<option value="<?php echo (int) $k->id; ?>" <?php selected( $edytowana && (int) $edytowana->kategoria_id === (int) $k->id ); ?>><?php echo esc_html( $k->nazwa ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( $edytowana ? 'Zapisz zmiany' : 'Dodaj regułę (na koniec listy)' ); ?>
			<?php if ( $edytowana ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-reguly' ) ); ?>">Anuluj edycję</a>
			<?php endif; ?>
		</form>

		<h2>Lista reguł (kolejność rozstrzygania)</h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Warunek</th><th>→ kategoria</th><th>Kolejność</th><th>Akcje</th></tr></thead>
			<tbody>
			<?php if ( ! $reguly ) : ?>
				<tr><td colspan="5">Brak reguł — wszystko poleci do „<?php echo esc_html( SEGM_KUBEL_DOMYSLNY ); ?>".</td></tr>
			<?php endif; ?>
			<?php $ostatnia = count( $reguly ); $i = 0; foreach ( $reguly as $r ) : $i++; ?>
				<tr>
					<td><?php echo (int) $r->kolejnosc; ?></td>
					<td><?php echo esc_html( $pola[ $r->pole ] . ' „' . $r->wzorzec . '"' ); ?></td>
					<td><?php echo esc_html( $r->kategoria ); ?></td>
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
		$wpdb->update(
			$wpdb->prefix . 'segm_reguly',
			array( 'pole' => $pole, 'wzorzec' => $wzorzec, 'kategoria_id' => $kategoria_id ),
			array( 'id' => $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	} else {
		$max = (int) $wpdb->get_var( "SELECT MAX(kolejnosc) FROM {$wpdb->prefix}segm_reguly" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
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
	$wpdb->delete( $wpdb->prefix . 'segm_reguly', array( 'id' => $id ), array( '%d' ) );

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
			$wpdb->update( $wpdb->prefix . 'segm_reguly', array( 'kolejnosc' => (int) $sasiad->kolejnosc ), array( 'id' => (int) $biezaca->id ), array( '%d' ), array( '%d' ) );
			$wpdb->update( $wpdb->prefix . 'segm_reguly', array( 'kolejnosc' => (int) $biezaca->kolejnosc ), array( 'id' => (int) $sasiad->id ), array( '%d' ), array( '%d' ) );
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
		$wpdb->update( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => $nazwa ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	} else {
		$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => $nazwa, 'utworzono' => current_time( 'mysql' ) ), array( '%s', '%s' ) );
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
	$wpdb->delete( $wpdb->prefix . 'segm_kategorie', array( 'id' => $id ), array( '%d' ) );

	wp_safe_redirect( admin_url( 'admin.php?page=segm-kategorie&segm_ok=usunieto' ) );
	exit;
}
add_action( 'admin_post_segm_kategoria_usun', 'segm_post_kategoria_usun' );

/* ===================== PRÓBKA MAILI ===================== */

function segm_strona_probka() {
	global $wpdb;
	$ile   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$maile = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}segm_maile ORDER BY id ASC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="wrap">
		<h1>Próbka maili (<?php echo (int) $ile; ?>)</h1>
		<?php segm_komunikaty(); ?>
		<p>Segregator testujesz na próbce — bez podpinania żywej skrzynki. Możesz wgrać własny eksport CSV (kolumny: <code>od, temat, tresc</code>, opcjonalnie <code>kategoria_oczekiwana</code> — wtedy raport policzy accuracy). Separator: przecinek albo średnik (jak w polskim Excelu) — wykrywany automatycznie. Import <strong>zastępuje</strong> bieżącą próbkę; maks. <?php echo (int) SEGM_IMPORT_MAX_WIERSZY; ?> wierszy.</p>

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

		<h2>Maile w próbce<?php echo $ile > 200 ? ' (pierwsze 200)' : ''; ?></h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Źródło</th><th>Od</th><th>Temat</th><th>Treść (początek)</th><th>Etykieta</th></tr></thead>
			<tbody>
			<?php foreach ( $maile as $m ) : ?>
				<tr>
					<td><?php echo (int) $m->id; ?></td>
					<td><?php echo esc_html( $m->zrodlo ); ?></td>
					<td><?php echo esc_html( $m->nadawca ); ?></td>
					<td><?php echo esc_html( $m->temat ); ?></td>
					<td><?php echo esc_html( wp_html_excerpt( $m->tresc, 90, '…' ) ); ?></td>
					<td><?php echo '' === $m->kategoria_oczekiwana ? '—' : esc_html( $m->kategoria_oczekiwana ); ?></td>
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

	$wynik = segm_importuj_csv( $tmp );
	if ( is_wp_error( $wynik ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_blad=' . rawurlencode( $wynik->get_error_message() ) ) );
		exit;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_ok=import&ile=' . (int) $wynik['zaimportowano'] . '&pominieto=' . (int) $wynik['pominieto'] ) );
	exit;
}
add_action( 'admin_post_segm_import_csv', 'segm_post_import_csv' );

function segm_post_probka_demo() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_probka_demo' );

	global $wpdb;
	$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}segm_maile" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	segm_wstaw_probke_demo();

	wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_ok=demo' ) );
	exit;
}
add_action( 'admin_post_segm_probka_demo', 'segm_post_probka_demo' );

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
				<tr>
					<th scope="row">Usuwanie wtyczki</th>
					<td>
						<label><input type="checkbox" name="usun_dane" value="1" <?php checked( $ust['usun_dane'] ); ?>> przy odinstalowaniu usuń WSZYSTKIE dane (kategorie, reguły, próbka, przebiegi, ustawienia)</label>
						<p class="description">Domyślnie dane zostają — możesz odinstalować i wrócić bez strat.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Zapisz ustawienia' ); ?>
		</form>
		<h2>Prywatność</h2>
		<p>Tryb regułowy: treść maili <strong>nie opuszcza</strong> Twojego WordPressa — segregacja działa lokalnie na próbce. Wtyczka nie łączy się z żadną skrzynką pocztową (działa na próbkach CSV — dlatego to uczciwie „testowy bot").</p>
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
		array( 'usun_dane' => empty( $_POST['usun_dane'] ) ? 0 : 1 )
	);
	update_option( 'segm_ustawienia', $nowe );

	wp_safe_redirect( admin_url( 'admin.php?page=segm-ustawienia&segm_ok=zapisano' ) );
	exit;
}
add_action( 'admin_post_segm_ustawienia_zapisz', 'segm_post_ustawienia_zapisz' );
