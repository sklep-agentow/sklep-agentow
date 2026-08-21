<?php
/**
 * Eksport i import WŁASNEJ konfiguracji: kategorie + reguły, w JSON-ie.
 *
 * Problem, który to rozwiązuje: eksport filtrów do Gmaila jest jednokierunkowy
 * i świadomie stratny (patrz filtry.php). Właściciel, który dopracował dwadzieścia
 * reguł i zmienia hosting albo klonuje stronę, tracił cały układ i wpisywał go
 * od nowa. Tu zabiera swoją pracę ze sobą.
 *
 * ⛔ Czego ten plik NIE eksportuje i dlaczego:
 * - hasła do skrzynki i klucza API — poświadczenia nie jeżdżą w pliku, który
 *   ląduje w pobranych, na dysku i w załączniku maila,
 * - próbki maili i historii — to cudza korespondencja, objęta retencją;
 *   wywożenie jej plikiem obchodziłoby tę retencję,
 * - zgód (AI, przenoszenie) — decyzja właściciela na JEDNEJ instalacji nie jest
 *   decyzją na drugiej. Po imporcie obie trzeba podjąć od nowa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wersja formatu pliku konfiguracji. Rośnie, gdy zmieni się jego kształt.
 */
const SEGM_KONFIG_FORMAT = 1;

/**
 * Górna granica wielkości wczytywanego pliku konfiguracji (bajty).
 */
const SEGM_KONFIG_MAX_BAJTOW = 524288;

/**
 * Ile najwyżej kategorii i reguł przyjmiemy z jednego pliku.
 */
const SEGM_KONFIG_MAX_WPISOW = 500;

/**
 * Górna długość wzorca reguły z pliku.
 *
 * Świadomie WŁASNA stała, a nie SEGM_MAX_POLE z csv.php: tamta pilnuje pola
 * w importowanym CSV i mieszkają w innym pliku. Sięganie po nią stąd wiązałoby
 * ten plik z kolejnością ładowania — i wywracało się wszędzie, gdzie csv.php
 * nie jest wczytany (np. w WP-CLI). Złapane testem, nie czytaniem kodu.
 */
const SEGM_KONFIG_MAX_WZORZEC = 2000;

/**
 * Konfiguracja do wywiezienia.
 *
 * Reguły wiążemy z kategorią po NAZWIE, nie po identyfikatorze: na drugiej
 * instalacji te same kategorie mają inne id, a wywożenie id dałoby po imporcie
 * reguły kierujące w nicość.
 *
 * @return array<string, mixed>
 */
function segm_konfig_zbierz() {
	global $wpdb;

	$kategorie = array();
	foreach ( (array) $wpdb->get_results( "SELECT nazwa FROM {$wpdb->prefix}segm_kategorie ORDER BY id ASC" ) as $k ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$kategorie[] = (string) $k->nazwa;
	}

	$reguly = array();
	foreach ( (array) segm_pobierz_reguly() as $r ) {
		$reguly[] = array(
			'kolejnosc' => (int) $r->kolejnosc,
			'pole'      => (string) $r->pole,
			'wzorzec'   => (string) $r->wzorzec,
			'kategoria' => (string) $r->kategoria,
		);
	}

	return array(
		'format'     => SEGM_KONFIG_FORMAT,
		'wtyczka'    => 'segregator-maila',
		'wersja'     => SEGM_WERSJA,
		'wywieziono' => current_time( 'mysql' ),
		'kategorie'  => $kategorie,
		'reguly'     => $reguly,
	);
}

/**
 * Pobranie pliku z konfiguracją.
 *
 * @return void
 */
function segm_post_konfig_eksport() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_konfig_eksport' );

	$json = wp_json_encode( segm_konfig_zbierz(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		segm_przekieruj_blad( 'segm-reguly', 'Nie udało się przygotować pliku konfiguracji (błąd kodowania JSON). Nic nie zostało pobrane.' );
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=segregator-konfiguracja-' . gmdate( 'Y-m-d' ) . '.json' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- to pobierany plik JSON, nie HTML; escapowanie zepsułoby jego treść.
	exit;
}
add_action( 'admin_post_segm_konfig_eksport', 'segm_post_konfig_eksport' );

/**
 * Sprawdzenie i uporządkowanie wczytanej konfiguracji.
 *
 * Plik przychodzi z zewnątrz, więc traktujemy go jak dane od nieznajomego:
 * każdy klucz sprawdzany, każda wartość sanityzowana i przycięta, liczby wpisów
 * ograniczone. Zwracamy komunikat błędu zamiast wyjątku, bo ten komunikat ma
 * przeczytać właściciel, nie log.
 *
 * @param string $tresc Zawartość pliku.
 * @return array{blad: string}|array{kategorie: array<int, string>, reguly: array<int, array<string, string>>, wersja: string}
 */
function segm_konfig_sprawdz( $tresc ) {
	$dane = json_decode( $tresc, true );
	if ( ! is_array( $dane ) ) {
		return array( 'blad' => 'To nie jest poprawny plik JSON. Wskaż plik pobrany przyciskiem „Pobierz konfigurację”.' );
	}
	if ( ! isset( $dane['wtyczka'] ) || 'segregator-maila' !== $dane['wtyczka'] ) {
		return array( 'blad' => 'Ten plik nie pochodzi z Segregatora maila (brak znacznika wtyczki). Nic nie zostało zmienione.' );
	}
	if ( ! isset( $dane['format'] ) || (int) $dane['format'] > SEGM_KONFIG_FORMAT ) {
		return array(
			'blad' => sprintf(
				'Plik jest w nowszym formacie (%s) niż rozumie ta wersja wtyczki (%d). Zaktualizuj wtyczkę i spróbuj ponownie — zgadywanie nowszego formatu skończyłoby się przekręconymi regułami.',
				isset( $dane['format'] ) ? (string) (int) $dane['format'] : 'nieznanym',
				SEGM_KONFIG_FORMAT
			),
		);
	}
	if ( ! isset( $dane['kategorie'] ) || ! is_array( $dane['kategorie'] ) || ! isset( $dane['reguly'] ) || ! is_array( $dane['reguly'] ) ) {
		return array( 'blad' => 'Plik nie zawiera list kategorii i reguł. Nic nie zostało zmienione.' );
	}
	if ( count( $dane['kategorie'] ) > SEGM_KONFIG_MAX_WPISOW || count( $dane['reguly'] ) > SEGM_KONFIG_MAX_WPISOW ) {
		return array( 'blad' => sprintf( 'Plik ma więcej niż %d kategorii albo reguł — to nie wygląda na konfigurację jednej skrzynki. Nic nie zostało zmienione.', SEGM_KONFIG_MAX_WPISOW ) );
	}

	$kategorie = array();
	foreach ( $dane['kategorie'] as $nazwa ) {
		if ( ! is_string( $nazwa ) ) {
			continue;
		}
		$nazwa = mb_substr( sanitize_text_field( $nazwa ), 0, 100 );
		if ( '' !== trim( $nazwa ) ) {
			$kategorie[] = $nazwa;
		}
	}
	$kategorie = array_values( array_unique( $kategorie ) );

	$reguly = array();
	foreach ( $dane['reguly'] as $r ) {
		if ( ! is_array( $r ) || ! isset( $r['pole'], $r['wzorzec'], $r['kategoria'] ) ) {
			continue;
		}
		$pole = sanitize_key( (string) $r['pole'] );
		if ( ! in_array( $pole, array( 'od', 'temat', 'tresc' ), true ) ) {
			continue;
		}
		$wzorzec   = mb_substr( sanitize_text_field( (string) $r['wzorzec'] ), 0, SEGM_KONFIG_MAX_WZORZEC );
		$kategoria = mb_substr( sanitize_text_field( (string) $r['kategoria'] ), 0, 100 );
		if ( '' === trim( $wzorzec ) || '' === trim( $kategoria ) ) {
			continue;
		}
		// Reguła kierująca do kategorii, której nie ma na liście, byłaby sierotą —
		// dokładamy tę kategorię, zamiast po cichu wyrzucić regułę.
		if ( ! in_array( $kategoria, $kategorie, true ) ) {
			$kategorie[] = $kategoria;
		}
		$reguly[] = array(
			'pole'      => $pole,
			'wzorzec'   => $wzorzec,
			'kategoria' => $kategoria,
		);
	}

	if ( empty( $kategorie ) && empty( $reguly ) ) {
		return array( 'blad' => 'Po sprawdzeniu pliku nie zostało ani jednej poprawnej kategorii i ani jednej poprawnej reguły. Nic nie zostało zmienione.' );
	}

	return array(
		'kategorie' => $kategorie,
		'reguly'    => $reguly,
		'wersja'    => isset( $dane['wersja'] ) ? mb_substr( sanitize_text_field( (string) $dane['wersja'] ), 0, 20 ) : 'nieznana',
	);
}

/**
 * Wczytanie konfiguracji z pliku — ZASTĘPUJE kategorie i reguły.
 *
 * @return void
 */
function segm_post_konfig_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_konfig_import' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- tmp_name z $_FILES, walidowane niżej.
	$tmp = isset( $_FILES['plik_konfig']['tmp_name'] ) ? $_FILES['plik_konfig']['tmp_name'] : '';
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		segm_przekieruj_blad( 'segm-reguly', 'Nie wgrano pliku konfiguracji.' );
	}
	$rozmiar = (int) filesize( $tmp );
	if ( $rozmiar > SEGM_KONFIG_MAX_BAJTOW ) {
		segm_przekieruj_blad( 'segm-reguly', sprintf( 'Plik ma %s, a limit to %s. Konfiguracja kategorii i reguł nigdy tyle nie waży — to nie ten plik.', size_format( $rozmiar ), size_format( SEGM_KONFIG_MAX_BAJTOW ) ) );
	}

	global $wp_filesystem;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$tresc = $wp_filesystem ? $wp_filesystem->get_contents( $tmp ) : false;
	if ( false === $tresc || '' === $tresc ) {
		segm_przekieruj_blad( 'segm-reguly', 'Nie udało się odczytać wgranego pliku.' );
	}

	$konfig = segm_konfig_sprawdz( $tresc );
	if ( isset( $konfig['blad'] ) ) {
		segm_przekieruj_blad( 'segm-reguly', $konfig['blad'] );
	}

	$zajeta = segm_zajmij_blokade( 'wczytywanie konfiguracji' );
	if ( true !== $zajeta ) {
		segm_przekieruj_blad( 'segm-reguly', sprintf( 'Trwa inna operacja (%s) — nie podmieniamy reguł w jej trakcie. Odczekaj chwilę i spróbuj ponownie.', $zajeta ) );
	}

	$wynik = segm_konfig_wgraj( $konfig );
	segm_zwolnij_blokade();

	if ( '' !== $wynik['blad'] ) {
		segm_przekieruj_blad( 'segm-reguly', $wynik['blad'] );
	}

	wp_safe_redirect(
		admin_url(
			'admin.php?page=segm-reguly&segm_ok=konfig&kategorii=' . (int) $wynik['kategorii'] . '&regul=' . (int) $wynik['regul']
		)
	);
	exit;
}
add_action( 'admin_post_segm_konfig_import', 'segm_post_konfig_import' );

/**
 * Podmiana kategorii i reguł na wczytane.
 *
 * Kolejność operacji jest tu istotna: NAJPIERW wstawiamy komplet kategorii
 * (bo reguły potrzebują ich identyfikatorów), potem kasujemy stare reguły,
 * na końcu wstawiamy nowe. Kasowanie kategorii zostawiamy na sam koniec i tylko
 * dla tych, które nie są używane — żeby nieudany import nie zostawił bazy bez
 * kategorii, do których kierują reguły.
 *
 * @param array{kategorie: array<int, string>, reguly: array<int, array<string, string>>} $konfig Sprawdzona konfiguracja.
 * @return array{blad: string, kategorii: int, regul: int}
 */
function segm_konfig_wgraj( $konfig ) {
	global $wpdb;
	$teraz = current_time( 'mysql' );
	$wynik = array(
		'blad'      => '',
		'kategorii' => 0,
		'regul'     => 0,
	);

	// Mapa istniejących kategorii po nazwie — istniejących NIE dublujemy.
	$mapa = array();
	foreach ( (array) $wpdb->get_results( "SELECT id, nazwa FROM {$wpdb->prefix}segm_kategorie" ) as $k ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$mapa[ (string) $k->nazwa ] = (int) $k->id;
	}

	foreach ( $konfig['kategorie'] as $nazwa ) {
		if ( isset( $mapa[ $nazwa ] ) ) {
			continue;
		}
		if ( false === $wpdb->insert(
			$wpdb->prefix . 'segm_kategorie',
			array(
				'nazwa'     => $nazwa,
				'utworzono' => $teraz,
			),
			array( '%s', '%s' )
		) ) {
			segm_log_bledu_bazy( 'segm_konfig_wgraj', 'kategoria z pliku: ' . $nazwa );
			$wynik['blad'] = 'Nie udało się zapisać kategorii „' . $nazwa . '" — baza odrzuciła zapis. Import PRZERWANY, reguły zostały bez zmian.';
			return $wynik;
		}
		$mapa[ $nazwa ] = (int) $wpdb->insert_id;
		++$wynik['kategorii'];
	}

	if ( false === $wpdb->query( "DELETE FROM {$wpdb->prefix}segm_reguly" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		segm_log_bledu_bazy( 'segm_konfig_wgraj', 'czyszczenie reguł przed importem' );
		$wynik['blad'] = 'Nie udało się usunąć dotychczasowych reguł — baza odrzuciła operację. Reguły są NIEZMIENIONE.';
		return $wynik;
	}

	$kolejnosc = 1;
	foreach ( $konfig['reguly'] as $r ) {
		if ( false === $wpdb->insert(
			$wpdb->prefix . 'segm_reguly',
			array(
				'kolejnosc'    => $kolejnosc,
				'pole'         => $r['pole'],
				'wzorzec'      => $r['wzorzec'],
				'kategoria_id' => $mapa[ $r['kategoria'] ],
				'utworzono'    => $teraz,
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		) ) {
			segm_log_bledu_bazy( 'segm_konfig_wgraj', 'reguła z pliku (' . $r['pole'] . ': ' . $r['wzorzec'] . ')' );
			$wynik['blad'] = sprintf(
				'Baza odrzuciła zapis reguły numer %d („%s"). Wczytano %d z %d reguł — lista reguł jest NIEPEŁNA, sprawdź ją przed uruchomieniem przebiegu.',
				$kolejnosc,
				$r['wzorzec'],
				$wynik['regul'],
				count( $konfig['reguly'] )
			);
			return $wynik;
		}
		++$wynik['regul'];
		++$kolejnosc;
	}

	return $wynik;
}

/**
 * Sekcja „Przeprowadzka" na stronie reguł.
 *
 * @return void
 */
function segm_konfig_sekcja() {
	global $wpdb;
	$ile_kat = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_kategorie" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$ile_reg = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_reguly" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<h2>Przeprowadzka: zabierz swoje reguły ze sobą</h2>
	<p style="max-width:820px">Zmieniasz hosting, klonujesz stronę albo chcesz mieć kopię swojej pracy? Pobierz plik z <strong><?php echo (int) $ile_kat; ?> kategoriami i <?php echo (int) $ile_reg; ?> regułami</strong> i wczytaj go na drugiej instalacji. To co innego niż <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SEGM_FILTRY_STRONA ) ); ?>">eksport filtrów</a>: tamten idzie do Gmaila i Outlooka, jest jednokierunkowy i świadomie stratny — ten wraca do Segregatora w całości.</p>
	<p class="description" style="max-width:820px">⛔ W pliku <strong>NIE MA</strong> hasła do skrzynki ani klucza API (poświadczenia nie jeżdżą w pliku, który ląduje w pobranych), <strong>NIE MA</strong> próbki maili ani historii (to cudza korespondencja objęta retencją) i <strong>NIE MA</strong> zapisanych zgód — na nowej instalacji podejmujesz te decyzje od nowa.</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:16px">
		<input type="hidden" name="action" value="segm_konfig_eksport">
		<?php wp_nonce_field( 'segm_konfig_eksport' ); ?>
		<?php submit_button( 'Pobierz konfigurację (JSON)', 'secondary', 'submit', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:inline-block">
		<input type="hidden" name="action" value="segm_konfig_import">
		<?php wp_nonce_field( 'segm_konfig_import' ); ?>
		<input type="file" name="plik_konfig" accept=".json,application/json" required aria-label="Plik konfiguracji JSON">
		<?php submit_button( 'Wczytaj konfigurację', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Wczytanie ZASTĄPI wszystkie obecne reguły. Kategorie zostaną uzupełnione o brakujące, żadna nie zniknie. Kontynuować?');" ) ); ?>
	</form>
	<p class="description">Wczytanie <strong>zastępuje wszystkie reguły</strong>. Kategorie są <strong>uzupełniane</strong> o brakujące — żadna istniejąca nie znika, bo mogą do niej kierować raporty i foldery na skrzynce.</p>
	<?php
}
