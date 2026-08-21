<?php
/**
 * STRAŻNIK DK-3: ten sam ciąg (polskie znaki + emoji + znaczniki HTML +
 * cudzysłowy + ampersand) przechodzi przez KAŻDE pole tekstowe obu botów.
 *
 * Sprawdzane trzy rzeczy, osobno:
 *   Z — czy się ZAPISUJE bez uszkodzenia (polskie znaki i emoji wracają całe),
 *   S — czy sanityzacja robi to, co ma (znaczniki HTML znikają albo są escapowane),
 *   W — czy po WYŚWIETLENIU w panelu nie ma surowego <script> i nie psuje układu.
 *
 * Pola gościa (pytanie, zgłoszenie) idą przez PRAWDZIWY endpoint HTTP —
 * ustawia je skrypt DK-znaki-pola.sh i podaje tu przez $GLOBALS.
 * Pola panelu zapisujemy wprost do tabel i renderujemy PRAWDZIWĄ funkcją strony.
 *
 * EXIT 0 = wszystkie pola czyste.
 */
global $wpdb;

$proba = "Zażółć gęślą jaźń ĄĆĘŁŃÓŚŹŻ 😀 <script>alert(\"XSS\")</script> <b>gruby</b> \"cudzysłów\" & ampersand — półpauza";
$GLOBALS['dk_bledy'] = 0;
$GLOBALS['dk_wyniki'] = array();

/** Czy w wartości ocalały polskie znaki i emoji. */
function dk_calosc( $v ) {
	return ( false !== mb_strpos( (string) $v, 'gęślą' ) )
		&& ( false !== mb_strpos( (string) $v, 'ĄĆĘŁŃÓŚŹŻ' ) )
		&& ( false !== mb_strpos( (string) $v, '😀' ) )
		&& ( false !== mb_strpos( (string) $v, 'półpauza' ) )
		&& ( false !== mb_strpos( (string) $v, '"cudzysłów"' ) )
		&& ( false !== mb_strpos( (string) $v, '&' ) );
}
function dk_bez_znacznikow( $v ) {
	return false === mb_strpos( (string) $v, '<script' ) && false === mb_strpos( (string) $v, '<b>' );
}
function dk_wynik( $pole, $wartosc, $render ) {
	// wp eval-file wykonuje plik w zasięgu funkcji — stan trzymamy w $GLOBALS.
	$z = dk_calosc( $wartosc );
	$s = dk_bez_znacznikow( $wartosc );
	$w = ( null === $render ) ? null : ( false === mb_strpos( $render, '<script>alert' ) );
	if ( ! $z || ! $s || false === $w ) { ++$GLOBALS['dk_bledy']; }
	$GLOBALS['dk_wyniki'][] = sprintf(
		"%s %-38s Z:%-3s S:%-3s W:%-4s | %s",
		( $z && $s && false !== $w ) ? 'OK  ' : 'PADA',
		$pole,
		$z ? 'tak' : 'NIE',
		$s ? 'tak' : 'NIE',
		null === $w ? '—' : ( $w ? 'tak' : 'NIE' ),
		mb_substr( (string) $wartosc, 0, 58 ) . '…'
	);
}

/* ---------- pola GOŚCIA (zapisane wcześniej przez prawdziwe HTTP) ---------- */
$sesja = isset( $GLOBALS['dk_sesja'] ) ? $GLOBALS['dk_sesja'] : '';
$mail  = isset( $GLOBALS['dk_mail'] ) ? $GLOBALS['dk_mail'] : '';
if ( '' !== $sesja ) {
	$v = (string) $wpdb->get_var( $wpdb->prepare( "SELECT tresc FROM {$wpdb->prefix}chatl_rozmowy WHERE sesja=%s AND rola='gosc' ORDER BY id DESC LIMIT 1", $sesja ) );
	dk_wynik( 'ChatBot: pytanie gościa (HTTP)', $v, null );
}
if ( '' !== $mail ) {
	$v = (string) $wpdb->get_var( $wpdb->prepare( "SELECT wiadomosc FROM {$wpdb->prefix}chatl_zgloszenia WHERE email=%s ORDER BY id DESC LIMIT 1", $mail ) );
	dk_wynik( 'ChatBot: wiadomość zgłoszenia (HTTP)', $v, null );
}

/* ---------- pola PANELU ChatBota ---------- */
foreach ( array( 'csv', 'import', 'statystyki', 'powitanie', 'operator', 'admin' ) as $dk_plik ) {
	require_once WP_PLUGIN_DIR . '/chatbot-live/includes/' . $dk_plik . '.php';
}
wp_set_current_user( 1 );

$proba_qa = sanitize_textarea_field( $proba ); // tak jak chatl_post_qa_zapisz()
$wpdb->insert( $wpdb->prefix . 'chatl_qa', array( 'pytanie' => $proba_qa, 'slowa_kluczowe' => 'dk3proba', 'odpowiedz' => $proba_qa, 'aktywne' => 1, 'utworzono' => current_time( 'mysql' ) ) );
ob_start(); chatl_strona_qa(); $render = ob_get_clean();
$v = (string) $wpdb->get_var( "SELECT pytanie FROM {$wpdb->prefix}chatl_qa WHERE slowa_kluczowe='dk3proba' LIMIT 1" );
dk_wynik( 'ChatBot: baza Q&A (pytanie)', $v, $render );
$v = (string) $wpdb->get_var( "SELECT odpowiedz FROM {$wpdb->prefix}chatl_qa WHERE slowa_kluczowe='dk3proba' LIMIT 1" );
dk_wynik( 'ChatBot: baza Q&A (odpowiedź)', $v, $render );
echo "  render Q&A: znaczniki zaescape'owane: " . ( substr_count( $render, '&lt;script&gt;' ) > 0 ? 'tak' : 'nie' )
	. ", emoji w HTML: " . ( false !== mb_strpos( $render, '😀' ) ? 'tak' : 'nie' )
	. ", surowy <script>: " . ( false !== mb_strpos( $render, '<script>alert' ) ? 'JEST' : 'brak' ) . "\n";

$kopia_ust = get_option( 'chatl_ustawienia' );
$u = (array) $kopia_ust; $u['powitanie'] = sanitize_textarea_field( $proba ); $u['tekst_awaryjny'] = sanitize_textarea_field( $proba ); update_option( 'chatl_ustawienia', $u );
ob_start(); chatl_strona_ustawienia(); $render_u = ob_get_clean();
dk_wynik( 'ChatBot: powitanie (ustawienia)', chatl_ustawienia()['powitanie'], $render_u );
dk_wynik( 'ChatBot: tekst awaryjny', chatl_ustawienia()['tekst_awaryjny'], $render_u );

/* ---------- pola PANELU Segregatora ---------- */
foreach ( array( 'csv', 'admin', 'admin-probka', 'admin-skrzynka', 'admin-powitanie', 'filtry', 'konfiguracja' ) as $dk_plik ) {
	require_once WP_PLUGIN_DIR . '/segregator-maila/includes/' . $dk_plik . '.php';
}
$wpdb->insert( $wpdb->prefix . 'segm_kategorie', array( 'nazwa' => mb_substr( sanitize_text_field( $proba ), 0, 100 ), 'utworzono' => current_time( 'mysql' ) ) );
$kid = (int) $wpdb->insert_id;
if ( ! $kid ) { echo 'BLAD: nie udalo sie dodac kategorii: ' . $wpdb->last_error . "\n"; }
$wpdb->insert( $wpdb->prefix . 'segm_reguly', array( 'kategoria_id' => $kid, 'kolejnosc' => 999, 'pole' => 'temat', 'wzorzec' => sanitize_text_field( $proba ), 'utworzono' => current_time( 'mysql' ) ) );
ob_start(); segm_strona_kategorie(); $render_k = ob_get_clean();
ob_start(); segm_strona_reguly(); $render_r = ob_get_clean();
$v = (string) $wpdb->get_var( $wpdb->prepare( "SELECT nazwa FROM {$wpdb->prefix}segm_kategorie WHERE id=%d", $kid ) );
dk_wynik( 'Segregator: nazwa kategorii', $v, $render_k );
$v = (string) $wpdb->get_var( $wpdb->prepare( "SELECT wzorzec FROM {$wpdb->prefix}segm_reguly WHERE kategoria_id=%d", $kid ) );
dk_wynik( 'Segregator: wzorzec reguły', $v, $render_r );
echo "  render kategorii: surowy <script>: " . ( false !== mb_strpos( $render_k, '<script>alert' ) ? 'JEST' : 'brak' )
	. " | render reguł: surowy <script>: " . ( false !== mb_strpos( $render_r, '<script>alert' ) ? 'JEST' : 'brak' ) . "\n";

/* ---------- SPRZĄTANIE ---------- */
update_option( 'chatl_ustawienia', $kopia_ust );
$a = $wpdb->delete( $wpdb->prefix . 'chatl_qa', array( 'slowa_kluczowe' => 'dk3proba' ) );
$wpdb->delete( $wpdb->prefix . 'segm_reguly', array( 'kategoria_id' => $kid ) );
$b = $wpdb->delete( $wpdb->prefix . 'segm_kategorie', array( 'id' => $kid ) );
if ( '' !== $mail ) { $wpdb->delete( $wpdb->prefix . 'chatl_zgloszenia', array( 'email' => $mail ) ); }
if ( '' !== $sesja ) { $wpdb->delete( $wpdb->prefix . 'chatl_rozmowy', array( 'sesja' => $sesja ) ); $wpdb->delete( $wpdb->prefix . 'chatl_sesje', array( 'sesja' => $sesja ) ); }

echo "\n" . implode( "\n", $GLOBALS['dk_wyniki'] ) . "\n";
echo "\nsprzatanie: qa={$a} kategorie={$b} + reguly, ustawienia przywrocone (powitanie: "
	. mb_substr( chatl_ustawienia()['powitanie'], 0, 30 ) . "…)\n";
echo "Z = zapis nieuszkodzony (pl+emoji+cudzyslowy+&), S = znaczniki HTML usuniete, W = render bez surowego <script>\n";
echo $GLOBALS['dk_bledy'] ? "\nWYNIK: PADA — {$GLOBALS['dk_bledy']} pol(a) z problemem.\n" : "\nWYNIK: OK — kazde pole przyjelo, oddalo i wyswietlilo ciag bezpiecznie.\n";
exit( $GLOBALS['dk_bledy'] ? 1 : 0 );
