<?php
/**
 * Praca na POJEDYNCZYM mailu i licznik trafień reguły.
 *
 * Problem, który to rozwiązuje: dotąd każda operacja na próbce była operacją na
 * CAŁEJ próbce (import CSV, demo, „wyczyść"), a jedyną drogą sprawdzenia reguły
 * był pełny przebieg i czytanie raportu. Właściciel, który chciał zobaczyć, czy
 * poprawiona reguła łapie JEDEN problematyczny mail, musiał przebudować cały
 * plik CSV i przepuścić całą próbkę. Tutaj dokłada ten jeden mail, poprawia
 * wzorzec i od razu widzi liczbę.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Próbka i reguły pobrane RAZ, do wielokrotnego liczenia trafień.
 *
 * Powód istnienia: lista reguł liczy trafienia dla KAŻDEGO wiersza, a każde
 * liczenie czytało wcześniej całą próbkę i całą listę reguł osobnym
 * zapytaniem. Przy dwudziestu regułach dawało to czterdzieści zapytań na jedno
 * wyświetlenie strony — a wystarczą dwa, bo dane są dla wszystkich wierszy
 * IDENTYCZNE. Kto liczy raz (formularz reguły), może kontekst pominąć:
 * funkcja pobierze go sama.
 *
 * @param array<int, object>|null $reguly Reguły, jeśli wywołujący ma je już pobrane.
 * @return array{maile: array<int, object>, reguly: array<int, object>}
 */
function segm_kontekst_trafien( $reguly = null ) {
	global $wpdb;

	return array(
		'maile'  => (array) $wpdb->get_results( "SELECT id, nadawca, temat, tresc FROM {$wpdb->prefix}segm_maile ORDER BY id ASC" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'reguly' => null === $reguly ? (array) segm_pobierz_reguly() : (array) $reguly,
	);
}

/**
 * Ile maili z BIEŻĄCEJ próbki łapie dana reguła.
 *
 * Zwracamy DWIE różne liczby, bo to dwie różne prawdy i mylenie ich byłoby
 * kłamstwem wobec właściciela:
 *
 * - `pasuje`  — do ilu maili pasuje sam wzorzec, gdyby ta reguła była jedyna,
 * - `wygrywa` — ile maili ta reguła realnie zgarnia w przebiegu, czyli po
 *   uwzględnieniu, że PIERWSZA PASUJĄCA REGUŁA WYGRYWA. Reguła stojąca niżej
 *   może pasować do dwudziestu maili i nie zgarnąć ani jednego, bo wcześniejsza
 *   zabrała je pierwsza. To jest dokładnie ta pułapka, którą licznik ma pokazać.
 *
 * Liczymy tą samą funkcją co przebieg (`segm_klasyfikuj_mail`), więc liczba
 * z panelu nie może rozjechać się z raportem.
 *
 * @param string                                                       $pole      'od' | 'temat' | 'tresc'.
 * @param string                                                       $wzorzec   Wzorzec (warianty po przecinku).
 * @param int                                                          $id_reguly ID edytowanej reguły; 0 = nowa reguła (na koniec listy).
 * @param array{maile: array<int, object>, reguly: array<int, object>}|null $kontekst Próbka i reguły z segm_kontekst_trafien(); null = pobierz sam.
 * @return array{pasuje: int, wygrywa: int, maili: int, pusty_wzorzec: bool, zabrane_przez: array<string, int>}
 */
function segm_policz_trafienia( $pole, $wzorzec, $id_reguly = 0, $kontekst = null ) {
	if ( ! is_array( $kontekst ) || ! isset( $kontekst['maile'], $kontekst['reguly'] ) ) {
		$kontekst = segm_kontekst_trafien();
	}

	$wynik = array(
		'pasuje'        => 0,
		'wygrywa'       => 0,
		'maili'         => 0,
		'pusty_wzorzec' => false,
		'zabrane_przez' => array(),
	);

	// Liczbę maili ustalamy ZAWSZE, także przy pustym wzorcu. Wcześniej wyjście
	// „na skróty" zostawiało maili=0 i opis mówił „próbka jest pusta" nad próbką
	// trzydziestu maili — komunikat wskazywał wtedy na zupełnie nie ten problem.
	$maile          = $kontekst['maile'];
	$wynik['maili'] = count( $maile );

	$wzorzec = trim( (string) $wzorzec );
	if ( '' === $wzorzec ) {
		$wynik['pusty_wzorzec'] = true;
		return $wynik;
	}
	if ( ! $maile ) {
		return $wynik;
	}

	// Lista reguł taka, jaka BYŁABY po zapisaniu tego formularza: przy edycji
	// podmieniamy regułę w jej miejscu (kolejność zostaje), przy nowej dokładamy
	// na koniec — bo tam trafia nowa reguła (`Dodaj regułę (na koniec listy)`).
	// ⛔ Pracujemy na KOPIACH reguł, nie na obiektach z kontekstu: podmiana
	// pola i wzorca poniżej zostawałaby w kontekście i psuła liczenie każdemu
	// następnemu wierszowi listy (kontekst jest wspólny dla wszystkich).
	$id_probny = $id_reguly > 0 ? (int) $id_reguly : -1;
	$podmienic = false;
	$reguly    = array();
	foreach ( $kontekst['reguly'] as $regula ) {
		$kopia = clone $regula;
		if ( $id_reguly > 0 && (int) $kopia->id === (int) $id_reguly ) {
			$kopia->pole    = $pole;
			$kopia->wzorzec = $wzorzec;
			$podmienic      = true;
		}
		$reguly[] = $kopia;
	}
	if ( ! $podmienic ) {
		$reguly[] = (object) array(
			'id'        => $id_probny,
			'pole'      => $pole,
			'wzorzec'   => $wzorzec,
			'kategoria' => SEGM_KUBEL_DOMYSLNY,
		);
	}

	foreach ( $maile as $mail ) {
		$pola = array(
			'od'    => mb_strtolower( $mail->nadawca ),
			'temat' => mb_strtolower( $mail->temat ),
			'tresc' => mb_strtolower( $mail->tresc ),
		);
		if ( isset( $pola[ $pole ] ) && segm_wzorzec_pasuje( $pola[ $pole ], $wzorzec ) ) {
			++$wynik['pasuje'];

			$decyzja = segm_klasyfikuj_mail( $mail, $reguly );
			if ( (int) $decyzja['regula_id'] === $id_probny ) {
				++$wynik['wygrywa'];
			} else {
				// Kto zabrał ten mail przed nami — bez tego właściciel widzi
				// samo „0 wygrywa" i nie wie, dlaczego.
				$kto = (string) $decyzja['kategoria'];
				if ( ! isset( $wynik['zabrane_przez'][ $kto ] ) ) {
					$wynik['zabrane_przez'][ $kto ] = 0;
				}
				++$wynik['zabrane_przez'][ $kto ];
			}
		}
	}

	arsort( $wynik['zabrane_przez'] );
	return $wynik;
}

/**
 * Zdanie po polsku opisujące wynik liczenia.
 *
 * @param array{pasuje: int, wygrywa: int, maili: int, pusty_wzorzec: bool, zabrane_przez: array<string, int>} $l Wynik segm_policz_trafienia().
 * @return string
 */
function segm_opis_trafien( $l ) {
	// „Nie wpisałeś wzorca" i „nie masz próbki" to dwie różne przyczyny zera
	// i dwie różne rady. Pusta próbka jest przeszkodą większą (bez niej nawet
	// dobry wzorzec nic nie pokaże), więc mówimy o niej pierwszej — a wzorzec
	// zgłaszamy dopiero wtedy, gdy jest na czym liczyć.
	if ( 0 === $l['maili'] ) {
		return 'Próbka jest pusta — nie ma na czym policzyć. Wgraj CSV, przywróć demo albo dodaj mail ręcznie.';
	}
	if ( ! empty( $l['pusty_wzorzec'] ) ) {
		return sprintf(
			'Nie ma czego liczyć — pole wzorca jest puste. Wpisz warianty po przecinku (np. „faktur, rachunek”) i kliknij ponownie. Próbka ma %d maili i czeka.',
			$l['maili']
		);
	}
	if ( 0 === $l['pasuje'] ) {
		return sprintf( 'Ten wzorzec nie pasuje do ŻADNEGO z %d maili w próbce.', $l['maili'] );
	}

	$opis = sprintf(
		'Wzorzec pasuje do %d z %d maili, a realnie zgarnia %d.',
		$l['pasuje'],
		$l['maili'],
		$l['wygrywa']
	);
	if ( $l['wygrywa'] < $l['pasuje'] ) {
		$kto = array();
		foreach ( $l['zabrane_przez'] as $kategoria => $ile ) {
			$kto[] = $kategoria . ' (' . (int) $ile . ')';
		}
		$opis .= sprintf(
			' Różnicę %d zabierają reguły stojące WYŻEJ — pierwsza pasująca wygrywa. Te maile trafiają do: %s. Przesuń regułę wyżej, jeśli ma je łapać ona.',
			$l['pasuje'] - $l['wygrywa'],
			implode( ', ', $kto )
		);
	}
	return $opis;
}

/* ===================== POJEDYNCZY MAIL W PRÓBCE ===================== */

/**
 * Formularz dodania albo edycji JEDNEGO maila.
 *
 * @param object|null $edytowany Wiersz z segm_maile albo null.
 * @return void
 */
function segm_formularz_maila( $edytowany ) {
	?>
	<h2><?php echo $edytowany ? 'Edytuj mail #' . (int) $edytowany->id : 'Dodaj pojedynczy mail'; ?></h2>
	<p class="description" style="max-width:760px">Nie musisz przebudowywać całego pliku CSV, żeby sprawdzić jeden przypadek. Dołóż tu mail, który sprawia kłopot, popraw regułę i policz trafienia — import CSV zastępuje całą próbkę, ten formularz rusza wyłącznie jeden wiersz.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="segm_mail_zapisz">
		<?php wp_nonce_field( 'segm_mail_zapisz' ); ?>
		<input type="hidden" name="id" value="<?php echo $edytowany ? (int) $edytowany->id : 0; ?>">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="segm-mail-od">Od (nadawca)</label></th>
				<td><input type="text" id="segm-mail-od" name="nadawca" class="regular-text" required
					value="<?php echo $edytowany ? esc_attr( $edytowany->nadawca ) : ''; ?>" placeholder="klient@przyklad.pl"></td>
			</tr>
			<tr>
				<th scope="row"><label for="segm-mail-temat">Temat</label></th>
				<td><input type="text" id="segm-mail-temat" name="temat" class="large-text"
					value="<?php echo $edytowany ? esc_attr( $edytowany->temat ) : ''; ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="segm-mail-tresc">Treść</label></th>
				<td><textarea id="segm-mail-tresc" name="tresc" class="large-text" rows="4"><?php echo $edytowany ? esc_textarea( $edytowany->tresc ) : ''; ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="segm-mail-etykieta">Etykieta (opcjonalnie)</label></th>
				<td>
					<input type="text" id="segm-mail-etykieta" name="kategoria_oczekiwana" class="regular-text"
						value="<?php echo $edytowany ? esc_attr( $edytowany->kategoria_oczekiwana ) : ''; ?>" placeholder="np. Faktury">
					<p class="description">Kategoria, do której ten mail POWINIEN trafić. Puste = mail nie liczy się do accuracy.</p>
				</td>
			</tr>
		</table>
		<?php submit_button( $edytowany ? 'Zapisz zmiany' : 'Dodaj mail do próbki', 'primary', 'submit', false ); ?>
		<?php if ( $edytowany ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=segm-probka' . segm_param_strony() ) ); ?>" style="margin-left:8px">Anuluj edycję</a>
		<?php endif; ?>
	</form>
	<?php
}

/**
 * Zapis pojedynczego maila (dodanie albo edycja).
 *
 * @return void
 */
function segm_post_mail_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_mail_zapisz' );

	global $wpdb;
	$id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$nadawca = isset( $_POST['nadawca'] ) ? sanitize_text_field( wp_unslash( $_POST['nadawca'] ) ) : '';
	$temat   = isset( $_POST['temat'] ) ? sanitize_text_field( wp_unslash( $_POST['temat'] ) ) : '';
	$tresc   = isset( $_POST['tresc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tresc'] ) ) : '';
	$etyk    = isset( $_POST['kategoria_oczekiwana'] ) ? sanitize_text_field( wp_unslash( $_POST['kategoria_oczekiwana'] ) ) : '';

	if ( '' === trim( $nadawca ) ) {
		segm_przekieruj_blad( 'segm-probka', 'Nadawca jest wymagany — bez niego reguły typu „nadawca zawiera" nie mają czego dopasować.' );
	}

	// Te same granice co przy imporcie CSV: jedno pole nie może rozsadzić
	// kolumny tylko dlatego, że przyszło formularzem, a nie plikiem.
	$nadawca = mb_substr( $nadawca, 0, 190 );
	$temat   = mb_substr( $temat, 0, SEGM_MAX_POLE );
	$tresc   = mb_substr( $tresc, 0, SEGM_MAX_POLE );
	$etyk    = mb_substr( $etyk, 0, 100 );

	$zajeta = segm_zajmij_blokade( $id > 0 ? 'edycja maila w próbce' : 'dodawanie maila do próbki' );
	if ( true !== $zajeta ) {
		segm_przekieruj_blad( 'segm-probka', sprintf( 'Trwa inna operacja na próbce (%s) — nie ruszamy jej w połowie. Odczekaj chwilę i spróbuj ponownie.', $zajeta ) );
	}

	$dane = array(
		'nadawca'              => $nadawca,
		'temat'                => $temat,
		'tresc'                => $tresc,
		'kategoria_oczekiwana' => $etyk,
	);

	if ( $id > 0 ) {
		$zapis = $wpdb->update(
			$wpdb->prefix . 'segm_maile',
			$dane,
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		// UWAGA na 0: update() zwraca 0 W DWÓCH RÓŻNYCH sytuacjach — gdy nic
		// się nie zmieniło (właściciel kliknął „Zapisz" bez zmian) ORAZ gdy
		// wiersza już nie ma (ktoś skasował mail między otwarciem formularza
		// a zapisem, albo zrobiła to retencja). Pierwsze jest sukcesem, drugie
		// NIE — a bez tego sprawdzenia oba kończyły się komunikatem
		// „Mail zapisany", czyli fałszywym sukcesem nad nieistniejącym mailem.
		if ( 0 === $zapis ) {
			$istnieje = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 0 === $istnieje ) {
				segm_zwolnij_blokade();
				segm_przekieruj_blad( 'segm-probka', sprintf( 'Maila #%d NIE MA już w próbce — ktoś go usunął (albo zrobiła to retencja), zanim zapisałeś zmiany. Nic nie zostało zapisane; jeśli ten przypadek jest Ci potrzebny, dodaj go jako nowy mail.', $id ) );
			}
		}
		$komunikat = 'mail_zapisany';
	} else {
		$dane['zrodlo']    = 'reczny';
		$dane['utworzono'] = current_time( 'mysql' );
		$zapis             = $wpdb->insert(
			$wpdb->prefix . 'segm_maile',
			$dane,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$komunikat         = 'mail_dodany';
	}
	segm_zwolnij_blokade();

	if ( false === $zapis ) {
		segm_log_bledu_bazy( 'segm_post_mail_zapisz', $id > 0 ? 'edycja maila ' . $id : 'dodanie maila do próbki' );
		segm_przekieruj_blad( 'segm-probka', 'Nie udało się zapisać maila — baza odrzuciła zapis. Próbka jest NIEZMIENIONA.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_ok=' . $komunikat ) );
	exit;
}
add_action( 'admin_post_segm_mail_zapisz', 'segm_post_mail_zapisz' );

/**
 * Usunięcie pojedynczego maila z próbki.
 *
 * @return void
 */
function segm_post_mail_usun() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'segm_mail_usun_' . $id );

	$zajeta = segm_zajmij_blokade( 'usuwanie maila z próbki' );
	if ( true !== $zajeta ) {
		segm_przekieruj_blad( 'segm-probka', sprintf( 'Trwa inna operacja na próbce (%s) — nie ruszamy jej w połowie. Odczekaj chwilę i spróbuj ponownie.', $zajeta ) );
	}

	global $wpdb;
	$usuniete = $wpdb->delete( $wpdb->prefix . 'segm_maile', array( 'id' => $id ), array( '%d' ) );
	segm_zwolnij_blokade();

	if ( false === $usuniete ) {
		segm_log_bledu_bazy( 'segm_post_mail_usun', 'usunięcie maila ' . $id );
		segm_przekieruj_blad( 'segm-probka', 'Nie udało się usunąć maila — baza odrzuciła operację. Mail NADAL jest w próbce.' );
	}
	if ( 0 === (int) $usuniete ) {
		segm_przekieruj_blad( 'segm-probka', 'Nie ma już takiego maila w próbce — ktoś usunął go wcześniej.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=segm-probka&segm_ok=mail_usuniety' ) );
	exit;
}
add_action( 'admin_post_segm_mail_usun', 'segm_post_mail_usun' );

/* ===================== LICZNIK TRAFIEŃ PRZY FORMULARZU REGUŁY ===================== */

/**
 * „Policz na próbce" — bez zapisywania reguły.
 *
 * Wynik i wpisane wartości wracają krótko żyjącym wpisem przypisanym do
 * użytkownika, a nie adresem URL: wzorzec bywa długi i zawiera przecinki,
 * a nikt nie chce oglądać wzorca w pasku adresu.
 *
 * @return void
 */
function segm_post_regula_policz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	// Nonce pod WŁASNĄ nazwą: w tym samym formularzu siedzi już `_wpnonce`
	// zapisu reguły, a przycisk „Policz" wysyła inną akcję — bez osobnego pola
	// sprawdzenie trafiałoby na nonce zapisu i kończyło się „Link wygasł".
	check_admin_referer( 'segm_regula_policz', '_wpnonce_policz' );

	$pole    = isset( $_POST['pole'] ) ? sanitize_key( wp_unslash( $_POST['pole'] ) ) : 'od';
	$wzorzec = isset( $_POST['wzorzec'] ) ? sanitize_text_field( wp_unslash( $_POST['wzorzec'] ) ) : '';
	$id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$kat     = isset( $_POST['kategoria_id'] ) ? (int) $_POST['kategoria_id'] : 0;
	if ( ! in_array( $pole, array( 'od', 'temat', 'tresc' ), true ) ) {
		$pole = 'od';
	}

	set_transient(
		'segm_liczenie_' . get_current_user_id(),
		array(
			'pole'         => $pole,
			'wzorzec'      => $wzorzec,
			'id'           => $id,
			'kategoria_id' => $kat,
			'opis'         => segm_opis_trafien( segm_policz_trafienia( $pole, $wzorzec, $id ) ),
		),
		5 * MINUTE_IN_SECONDS
	);

	wp_safe_redirect( admin_url( 'admin.php?page=segm-reguly' . ( $id > 0 ? '&edytuj=' . $id : '' ) ) );
	exit;
}
add_action( 'admin_post_segm_regula_policz', 'segm_post_regula_policz' );

/**
 * Odczyt i skasowanie zapamiętanego wyniku liczenia.
 *
 * @return array{pole: string, wzorzec: string, id: int, kategoria_id: int, opis: string}|null
 */
function segm_odbierz_liczenie() {
	$klucz = 'segm_liczenie_' . get_current_user_id();
	$wpis  = get_transient( $klucz );
	if ( ! is_array( $wpis ) || ! isset( $wpis['opis'] ) ) {
		return null;
	}
	delete_transient( $klucz );
	return $wpis;
}
