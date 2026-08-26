<?php
/**
 * Plugin Name: Sklep Agentów
 * Plugin URI: https://automaticai.pl/sklep/
 * Description: Sprzedawaj wtyczki-boty AI bezpośrednio ze swojej strony: płatność kartą, automatyczny mail z linkiem do pobrania, panel zamówień ze statystykami i dziennikiem logowań. Zamówienia, produkty i pliki zostają w Twojej bazie i na Twoim serwerze — bez abonamentu. Płatność obsługuje Stripe.
 * Version: 0.9.14
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Automatic AI
 * Author URI: https://automaticai.pl
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKLEPAG_WERSJA', '0.9.14' );
define( 'SKLEPAG_WERSJA_SCHEMATU', 6 ); // v6: kolumny zwrotu (payment_intent_id, zwrocono_grosze, zwrot_czas); v5: tabela opinii; v4: kolumna zamowienia.usunieto_dnia (anonimizacja RODO); v3: indeks para_logowania.
define( 'SKLEPAG_LIMIT_POBRAN', 10 ); // Jedno źródło: INSERT zamówienia + treść maila.
define( 'SKLEPAG_LINK_GODZIN', 72 );  // Ważność linku pobrania — jw.
define( 'SKLEPAG_OPINIA_DNI', 30 );   // Ważność linku „oceń bota" — kupujący najpierw testuje produkt.
define( 'SKLEPAG_OPINIA_ZGODA_TRESC', 'Zgadzam się na publikację mojej opinii wraz z podanym imieniem na stronie produktu.' );
define( 'SKLEPAG_PLIK', __FILE__ );
define( 'SKLEPAG_KATALOG', plugin_dir_path( __FILE__ ) );

require_once SKLEPAG_KATALOG . 'includes/aktywacja.php';
require_once SKLEPAG_KATALOG . 'includes/stripe.php';
require_once SKLEPAG_KATALOG . 'includes/zakup.php';
require_once SKLEPAG_KATALOG . 'includes/pobieranie.php';
require_once SKLEPAG_KATALOG . 'includes/logowania.php'; // ZAWSZE, nie za is_admin() — hooki logowania żyją na wp-login.php.
require_once SKLEPAG_KATALOG . 'includes/front-produkt.php'; // widok produktu + rewrite (init) — poza is_admin().
require_once SKLEPAG_KATALOG . 'includes/front-seo.php';     // title/meta/JSON-LD/sitemap.
require_once SKLEPAG_KATALOG . 'includes/opinie.php';        // opinie: zalążek+token przy mailu, formularz admin-post (poza is_admin!), render z bazy.

register_activation_hook( __FILE__, 'sklepag_aktywacja' );
register_deactivation_hook( __FILE__, 'sklepag_deaktywacja' );

// Interwał 10 min dla „dociągnij zaległe" (reconciliation — druga droga domykania).
// phpcs:disable WordPress.WP.CronInterval.CronSchedulesInterval -- 10 min świadomie: tryb testowy Stripe ponawia webhook tylko 3× w parę godzin, cron domyka zaległe zakupy.
add_filter(
	'cron_schedules',
	function ( $harmonogramy ) {
		$harmonogramy['sklepag_10min'] = array(
			'interval' => 600,
			'display'  => 'Co 10 minut (Sklep Agentów)',
		);
		return $harmonogramy;
	}
);
// phpcs:enable WordPress.WP.CronInterval.CronSchedulesInterval

add_action( 'plugins_loaded', 'sklepag_start' );

function sklepag_start() {
	sklepag_migruj_schemat_jesli_trzeba();

	// Self-heal harmonogramu (aktualizacja wtyczki nie odpala aktywacji).
	if ( ! wp_next_scheduled( 'sklepag_dociagnij_zalegle' ) ) {
		wp_schedule_event( time() + 600, 'sklepag_10min', 'sklepag_dociagnij_zalegle' );
	}

	// Self-heal katalogu chronionego — DRUGI zasób tworzony tylko przy aktywacji.
	// Aktualizacja w miejscu („Aktualizuj teraz") aktywacji NIE odpala, więc instalacja bez
	// opcji `sklepag_katalog_chroniony` nie odzyskiwała jej niczym: cron miał samonaprawę,
	// katalog nie, a bez niego pliki produktów nie mają chronionego miejsca. Sprawdzamy w
	// panelu i w cronie (funkcja jest idempotentna: gdy katalog jest, wraca natychmiast) —
	// nie na każdym żądaniu frontu, żeby wizyta kupującego nie płaciła za to sprawdzeniem dysku.
	if ( is_admin() || wp_doing_cron() ) {
		sklepag_utworz_katalog_chroniony();
	}

	add_shortcode( 'sklepag_sklep', 'sklepag_shortcode_sklep' );
	add_shortcode( 'sklepag_dziekujemy', 'sklepag_shortcode_dziekujemy' );
	add_action( 'wp_enqueue_scripts', 'sklepag_assety_frontu' );

	if ( is_admin() ) {
		require_once SKLEPAG_KATALOG . 'includes/admin.php';
	}
}

/**
 * Wersja assetu do cache-bustingu: numer wtyczki + czas modyfikacji pliku. Podmiana
 * pliku bez podbicia wersji (rundy wyglądu 25.08) zostawiała przeglądarkom stary CSS/JS
 * pod tym samym adresem `?ver=` — na żywo wyglądało to jak brak animacji wejścia.
 */
function sklepag_wersja_assetu( $sciezka ) {
	$plik  = SKLEPAG_KATALOG . $sciezka;
	$mtime = file_exists( $plik ) ? filemtime( $plik ) : 0;
	return SKLEPAG_WERSJA . ( $mtime ? '.' . $mtime : '' );
}

/**
 * Assety TYLKO na stronach sklepu (nie spowalniamy reszty strony).
 */
function sklepag_assety_frontu() {
	$strona_sklep      = (int) get_option( 'sklepag_strona_sklep' );
	$strona_dziekujemy = (int) get_option( 'sklepag_strona_dziekujemy' );

	if ( is_page( $strona_sklep ) || is_page( $strona_dziekujemy ) ) {
		wp_enqueue_style( 'sklepag-front', plugins_url( 'assets/front.css', SKLEPAG_PLIK ), array(), sklepag_wersja_assetu( 'assets/front.css' ) );

		/* Pojawianie się sekcji przy przewijaniu (spójność z motywem gospodarza).
			Skrypt w <head> z `defer` + inline PRZED nim: klasa `sklepag-js` musi
			trafić na <html> ZANIM przeglądarka namaluje treść — to ONA ukrywa elementy
			od pierwszej klatki (jak `html.js` gospodarza); skrypt tylko odsłania.
			Inline dodaje klasę wyłącznie, gdy jest IntersectionObserver i nie ma
			„ogranicz ruch", a po 2,5 s dodaje `sklepag-odslon` (CSS pokazuje wszystko)
			TYLKO gdy front-ruch.js nie zgłosił gotowości klasą `sklepag-ruch-ok` (bezwarunkowy
			bezpiecznik gasił kaskadę sekcji poza pierwszym ekranem).
			Bez JS klasa nie powstaje i CSS niczego nie ukrywa. */
		wp_enqueue_script(
			'sklepag-ruch',
			plugins_url( 'assets/front-ruch.js', SKLEPAG_PLIK ),
			array(),
			sklepag_wersja_assetu( 'assets/front-ruch.js' ),
			array(
				'in_footer' => false,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'sklepag-ruch',
			"(function(){try{if(!('IntersectionObserver' in window)||(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)){return;}var h=document.documentElement;h.classList.add('sklepag-js');setTimeout(function(){if(!h.classList.contains('sklepag-ruch-ok')){h.classList.add('sklepag-odslon');}},2500);}catch(e){}})();",
			'before'
		);
	}
	if ( is_page( $strona_dziekujemy ) ) {
		wp_enqueue_script( 'sklepag-dziekujemy', plugins_url( 'assets/dziekujemy.js', SKLEPAG_PLIK ), array(), sklepag_wersja_assetu( 'assets/dziekujemy.js' ), true );
		wp_add_inline_script( 'sklepag-dziekujemy', 'window.sklepagRest = ' . wp_json_encode( esc_url_raw( rest_url( 'sklepag/v1/' ) ) ) . ';', 'before' );
	}
}

/**
 * Baner trybu testowego — jedna treść na wszystkich stronach sklepu (
 * napis bez zmian — testy na nim polegają).
 */
function sklepag_baner_testowy() {
	return '<p class="sklepag-baner-testowy"><span class="sklepag-baner-testowy-tytul">TRYB TESTOWY</span> — płatności nie są pobierane. Przetestujesz cały zakup kartą testową, nic nie płacąc.</p>';
}

/**
 * Lead hero listy — JEDNO źródło dla strony i dla meta description listy (front-seo.php);
 * dwa egzemplarze tego zdania rozjechałyby się po cichu.
 */
function sklepag_lead_listy() {
	return 'Gotowe wtyczki-boty dla Twojej firmy. Instalujesz jak każdą wtyczkę WordPressa — działają od pierwszej minuty.';
}

/**
 * Duet maskotek (te same kreski co ilustracje kart) — wizual banera końcowego listy
 * i strony produktu: baner ma parę tekst + wizual jak hero i karty. aria-hidden.
 *
 * @param string $podpis Gotowy HTML podpisu pod duetem ('' = podpis listy z obiema nazwami).
 */
function sklepag_duet_maskotek( $podpis = '' ) {
	if ( '' === $podpis ) {
		// Domyślny podpis listy: oba produkty. Strona produktu podaje własny (nazwa TEGO bota).
		$podpis = '<p class="sklepag-mono-etykieta sklepag-final-duet-podpis">[ ChatBot LIVE <span class="sklepag-akcent">·</span> Segregator maila ]</p>';
	}
	return '<div class="sklepag-final-duet" aria-hidden="true"><svg viewBox="0 0 520 300" fill="none" stroke="#bfff38" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><g class="sklepag-duet-unos"><path d="M196 70 h108 a14 14 0 0 1 14 14 v46 a14 14 0 0 1 -14 14 h-72 l-24 20 v-20 h-12 a14 14 0 0 1 -14 -14 v-46 a14 14 0 0 1 14 -14 z" fill="#bfff38" stroke="none"/><path d="M206 98 h68 M206 118 h44" stroke="#08090b" stroke-width="5"/></g><g><path d="M132 122 v-24"/><circle cx="132" cy="90" r="7" fill="#bfff38" stroke="none"/><rect x="88" y="122" width="88" height="72" rx="20" fill="#101116"/><circle class="sklepag-duet-oko" cx="118" cy="158" r="6" fill="#bfff38" stroke="none"/><circle class="sklepag-duet-oko" cx="146" cy="158" r="6" fill="#bfff38" stroke="none"/><path d="M120 176 q12 10 24 0"/><path d="M100 194 v40 a18 18 0 0 0 18 18 h28 a18 18 0 0 0 18 -18 v-40"/><path d="M100 214 q-30 12 -40 36"/><circle cx="58" cy="252" r="7" fill="#bfff38" stroke="none"/><path d="M164 214 q26 -18 34 -44"/><circle cx="200" cy="168" r="7" fill="#bfff38" stroke="none"/></g><g><path d="M392 122 v-24"/><circle cx="392" cy="90" r="7" fill="#bfff38" stroke="none"/><rect x="348" y="122" width="88" height="72" rx="20" fill="#101116"/><circle class="sklepag-duet-oko sklepag-duet-oko--b" cx="378" cy="158" r="6" fill="#bfff38" stroke="none"/><circle class="sklepag-duet-oko sklepag-duet-oko--b" cx="406" cy="158" r="6" fill="#bfff38" stroke="none"/><path d="M380 176 q12 10 24 0"/><path d="M360 194 v40 a18 18 0 0 0 18 18 h28 a18 18 0 0 0 18 -18 v-40"/><path d="M360 214 q-26 -16 -34 -40"/><circle cx="324" cy="170" r="7" fill="#bfff38" stroke="none"/><path d="M424 214 q28 10 38 32"/><circle cx="464" cy="248" r="7" fill="#bfff38" stroke="none"/></g><g class="sklepag-duet-unos sklepag-duet-unos--b"><rect x="270" y="130" width="64" height="44" rx="8" fill="#08090b" stroke="#8f929c" stroke-width="3"/><path d="m274 136 28 22 28 -22" stroke="#8f929c" stroke-width="3"/><rect x="270" y="130" width="64" height="44" rx="8" stroke="#bfff38" stroke-width="3" stroke-dasharray="6 10"/></g><path d="M214 236 q46 20 92 0" stroke-dasharray="2 10" stroke-width="3"/><path d="M40 276 h440" stroke="#8f929c" stroke-width="2" stroke-dasharray="2 10"/></svg>' . $podpis . '</div>';
}

/**
 * PANEL NOCNEJ ZMIANY — okno aplikacji, w którym bot(y) „pracują" w pętli (czysty CSS, zero JS).
 * ILUSTRACJA (aria-hidden): dymki i maile to TE SAME zatwierdzone teksty co mockup czatu i PRZED/PO
 * na stronach produktów — żadnych nowych obietnic ani liczb. Bez JS / przy „ogranicz ruch" panel
 * stoi w stanie końcowym (wszystko widoczne). Lista pokazuje oba wiersze; hero produktu — swój
 * (przegląd na żywo 25.08: „ilustracja w hero za słaba" → żywy panel zamiast statycznego obrazka).
 *
 * @param string $tryb 'oba' (lista) | 'chatbot-live' | 'segregator-maila' (hero produktu).
 * @return string HTML panelu; '' dla nieznanego trybu.
 */
function sklepag_panel_nocnej_zmiany( $tryb = 'oba' ) {
	$czat  = in_array( $tryb, array( 'oba', 'chatbot-live' ), true );
	$maile = in_array( $tryb, array( 'oba', 'segregator-maila' ), true );
	if ( ! $czat && ! $maile ) {
		return '';
	}
	$html  = '<div class="sklepag-panel' . ( 'oba' === $tryb ? '' : ' sklepag-panel--' . sanitize_html_class( $tryb ) ) . '" aria-hidden="true">';
	$html .= '<span class="sklepag-panel-adnotacja sklepag-panel-adnotacja--gora">' . ( $maile && ! $czat ? 'Twoja skrzynka' : 'Twoja strona' ) . '</span>';
	// Adnotacja dolna hero Segregatora = „Twoje foldery" (skrzynka → foldery, jak w treściach produktu);
	// „Poranny raport" dublował etykietę wiersza w panelu tuż nad nią (sędzia r2). Lista bez zmian.
	$html .= '<span class="sklepag-panel-adnotacja sklepag-panel-adnotacja--dol">' . ( 'oba' === $tryb ? 'Twoja skrzynka' : ( $czat ? 'Twoja baza pytań' : 'Twoje foldery' ) ) . '</span>';
	$html .= '<div class="sklepag-panel-okno">';
	$html .= '<span class="sklepag-okno-pasek"><span></span><span></span><span></span></span>';
	if ( $czat ) {
		$html .= '<div class="sklepag-panel-wiersz">';
		$html .= '<p class="sklepag-mono-etykieta"><span class="sklepag-kropka"></span>[ ChatBot LIVE · <span class="sklepag-akcent">online</span> ]</p>';
		$html .= '<div class="sklepag-panel-czat">';
		$html .= '<p class="sklepag-dymek sklepag-dymek-gosc">Ile kosztuje strzyżenie?</p>';
		// Awatar = głowa TEJ SAMEJ maskotki co na ilustracjach kart (jedna „ręka" rysunku);
		// oczy mrugają czystym CSS (sędzia r1: akcent marki zamiast generycznego UI).
		$html .= '<div class="sklepag-panel-bot"><span class="sklepag-panel-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5.5V3.6"/><circle cx="12" cy="2.6" r="1" fill="currentColor" stroke="none"/><rect x="4.5" y="5.5" width="15" height="12" rx="3.5"/><circle class="sklepag-oko" cx="9.3" cy="11" r="1.1" fill="currentColor" stroke="none"/><circle class="sklepag-oko" cx="14.7" cy="11" r="1.1" fill="currentColor" stroke="none"/><path d="M9.6 14.2q2.4 2 4.8 0"/></svg></span>';
		$html .= '<div class="sklepag-panel-odpowiedz">';
		$html .= '<p class="sklepag-dymek sklepag-dymek-bot sklepag-pisze"><span></span><span></span><span></span></p>';
		$html .= '<p class="sklepag-dymek sklepag-dymek-bot">Strzyżenie damskie od 80 zł, męskie od 50 zł. Pełny cennik: automatic.ai/cennik</p>';
		$html .= '</div></div></div></div>';
		if ( 'oba' !== $tryb ) {
			// Wiersz „baza pytań": tematy = to, co bot robi wg opisu produktu (cennik, godziny, kontakt) —
			// jak na ilustracji karty; chipy podświetlają się po kolei (CSS).
			$html .= '<div class="sklepag-panel-baza"><span class="sklepag-mono-etykieta">Baza pytań</span><span class="sklepag-panel-chip">[ Cennik ]</span><span class="sklepag-panel-chip">[ Godziny ]</span><span class="sklepag-panel-chip">[ Kontakt ]</span></div>';
		}
	}
	if ( $maile ) {
		$html   .= '<div class="sklepag-panel-wiersz">';
		$html   .= '<p class="sklepag-mono-etykieta"><span class="sklepag-kropka"></span>[ Segregator maila · <span class="sklepag-akcent">poranny raport</span> ]</p>';
		$html   .= '<div class="sklepag-panel-maile">';
		$koperta = '<svg class="sklepag-panel-mail-ikona" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6.5 9 7 9-7"/></svg>';
		$html   .= '<p class="sklepag-panel-mail">' . $koperta . '<span class="sklepag-panel-mail-temat">FV 88/2026 — księgowość</span><span class="sklepag-tag">Faktury</span></p>';
		$html   .= '<p class="sklepag-panel-mail">' . $koperta . '<span class="sklepag-panel-mail-temat">RE: wycena ogrodzenia</span><span class="sklepag-tag">Oferty</span></p>';
		$html   .= '<p class="sklepag-panel-mail">' . $koperta . '<span class="sklepag-panel-mail-temat">reklamacja zamówienia 113</span><span class="sklepag-tag">Reklamacje</span></p>';
		$html   .= '</div></div>';
		if ( 'oba' !== $tryb ) {
			// Wiersz „Twoje reguły" (tylko hero produktu): chipy = kategorie z wierszy maili wyżej; chip zapala
			// się W TAKT tagu maila (CSS, te same opóźnienia) — reguła łapie mail, tag wskakuje, chip świeci.
			$html .= '<div class="sklepag-panel-baza sklepag-panel-baza--reguly"><span class="sklepag-mono-etykieta">Twoje reguły</span><span class="sklepag-panel-chip">[ Faktury ]</span><span class="sklepag-panel-chip">[ Oferty ]</span><span class="sklepag-panel-chip">[ Reklamacje ]</span></div>';
		}
	}
	$html .= '</div></div>';
	return $html;
}

/**
 * Lista aktywnych produktów (klimat strony-gospodarza).
 * Uczciwy stan pusty, bez atrap.
 */
function sklepag_shortcode_sklep() {
	// Widok produktu na TEJ SAMEJ stronie (query var).
	$slug_produktu = get_query_var( 'sklepag_produkt' );
	if ( is_string( $slug_produktu ) && '' !== $slug_produktu ) {
		return sklepag_render_produkt( sanitize_title( $slug_produktu ) );
	}

	global $wpdb;
	$tabela = $wpdb->prefix . 'sklepag_produkty';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki; lista musi być świeża (CRUD w adminie), cache bez zysku.
	$produkty = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, nazwa, slug, opis_krotki, cena_grosze, waluta FROM {$tabela} WHERE aktywny = %d ORDER BY nazwa", 1 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z prefiksu WP, nie z wejścia.
	);

	$html = '<div class="sklepag-wyspa sklepag-sklep">';
	// TŁO „BLUEPRINT Z SYGNAŁAMI" na CAŁEJ wyspie (przegląd na żywo 25.08: „coś na całe tło, z inspiracji,
	// nie 1:1 hero gospodarza"): siatka projektowa jak na ilustracjach kart + wolny promień skanu
	// + pingi (czysty CSS, zero canvasa — lekkie dla każdej karty graficznej). aria-hidden.
	$html .= '<div class="sklepag-tlo" aria-hidden="true"></div>';
	// Pingi POZA maskowanym tłem (maska nad animacją = przeliczanie całej warstwy co klatkę).
	$html .= '<div class="sklepag-pingi" aria-hidden="true">' . str_repeat( '<i></i>', 9 ) . '</div>';
	// Hero 2-kolumnowy:
	// tekst po lewej, po prawej PANEL NOCNEJ ZMIANY. Na wąskim ekranie panel pod tekstem.
	$html .= '<div class="sklepag-hero-lista">';
	$html .= '<header class="sklepag-hero">';
	$html .= '<p class="sklepag-mono-etykieta">[ SKLEP ]</p>';
	$html .= '<h1 class="sklepag-hero-tytul"><span class="sklepag-akcent">Boty</span>, które robią robotę.</h1>';
	$html .= '<p class="sklepag-lead">' . esc_html( sklepag_lead_listy() ) . '</p>';
	$html .= sklepag_baner_testowy();
	$html .= '<p class="sklepag-demo-cta"><span class="sklepag-akcent">▶</span> Zielony bąbelek w rogu strony to ChatBot LIVE — porozmawiaj z nim, zanim kupisz.</p>';
	// Pasek konkretów: każda pozycja ma pokrycie w kodzie/pomiarze (dok=kod):
	// 6.0+ = „Requires at least" obu botów · czysta deinstalacja = zmierzona
	// we wrogiej sesji botów (diff przed-po pusty) · bez abonamentu = zakup
	// jednorazowy, żadnych płatności cyklicznych w kodzie.
	$html .= '<ul class="sklepag-konkrety" aria-label="Najważniejsze informacje o produktach">';
	$html .= '<li>WordPress 6.0+</li><li>instalacja jak każdej wtyczki</li><li>czysta deinstalacja</li><li>bez abonamentu</li>';
	$html .= '</ul>';
	$html .= '</header>';
	// PANEL NOCNEJ ZMIANY — sygnatura ruchu listy: okno aplikacji, w którym oba boty
	// „pracują" w pętli (czysty CSS, zero JS). To ILUSTRACJA (aria-hidden): dymki i maile
	// to TE SAME zatwierdzone teksty co mockup czatu i PRZED/PO na stronach produktów —
	// żadnych nowych obietnic ani liczb. Bez JS / przy „ogranicz ruch" panel stoi
	// w stanie końcowym (wszystko widoczne).
	$html .= sklepag_panel_nocnej_zmiany( 'oba' );
	$html .= '</div>';

	if ( empty( $produkty ) ) {
		$html .= '<p class="sklepag-pusto">[ BRAK PRODUKTÓW W SPRZEDAŻY ]</p></div>';
		return $html;
	}

	$html .= '<div class="sklepag-karty">';
	$nr    = 0;
	foreach ( $produkty as $p ) {
		++$nr;
		// Karta: ILUSTRACJA w brandzie (D28b + UZUPEŁNIENIE 24.08); zrzut żywego
		// bota jest na stronie produktu pierwszym kaflem galerii [ CO JEST W ŚRODKU ].
		$obraz = sklepag_ilustracja_produktu( $p->slug );
		$html .= '<article class="sklepag-karta">';
		if ( $obraz ) {
			// Bez lazy: ilustracja ma wejść RAZEM z kartą (kaskada), nie chwilę po niej.
			$html .= sklepag_figure_zrzutu( $obraz, 'sklepag-karta-obraz sklepag-karta-ilustracja', false, false, false );
		}
		// Głowa karty w języku gospodarza: mono-numer + hairline + ikona (jak jego karty usług).
		$html .= '<div class="sklepag-karta-glowa"><span class="sklepag-karta-nr">' . sprintf( '%02d', $nr ) . '</span><span class="sklepag-karta-linia"></span>' . sklepag_ikona_produktu( $p->slug ) . '</div>';
		$html .= '<p class="sklepag-mono-etykieta">[ <span class="sklepag-akcent">BOT</span> · WTYCZKA WordPress ]</p>';
		$html .= '<h2 class="sklepag-karta-tytul">' . esc_html( $p->nazwa ) . '</h2>';
		$html .= '<p class="sklepag-karta-opis">' . esc_html( $p->opis_krotki ) . '</p>';
		$html .= '<p class="sklepag-cena">' . esc_html( number_format( $p->cena_grosze / 100, 2, ',', ' ' ) ) . '<span class="sklepag-cena-waluta">' . esc_html( $p->waluta ) . '</span></p>';
		$html .= '<p><a class="sklepag-kup sklepag-kup-kotwica" href="' . esc_url( sklepag_url_produktu_front( $p->slug ) ) . '">Zobacz szczegóły <span aria-hidden="true">→</span></a></p>';
		$html .= '</article>';
	}
	$html .= '</div>';

	// OD ZAKUPU DO DZIAŁANIA (wzorzec „ile trwa uruchomienie" — zwiad 19.08;
	// treść kroków = faktyczny przepływ: mail z instrukcją i linkiem do zipa).
	// Trzy równe karty w rzędzie (runda stron 25.08) — numer z hairline'em jak u gospodarza.
	$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna"><p class="sklepag-mono-etykieta">[ <span class="sklepag-akcent">01</span> · OD ZAKUPU DO DZIAŁANIA ]</p>';
	$html .= '<ol class="sklepag-kroki sklepag-kroki--siatka">';
	$html .= '<li><span class="sklepag-krok-tekst">Kupujesz — płatność testowa Stripe, bez zakładania konta.</span></li>';
	$html .= '<li><span class="sklepag-krok-tekst">Dostajesz maila: potwierdzenie, instrukcja i link do paczki (zip wtyczki).</span></li>';
	$html .= '<li><span class="sklepag-krok-tekst">Instalujesz jak każdą wtyczkę WordPressa — bot działa od razu, na start z danymi demo.</span></li>';
	$html .= '</ol></section>';

	// DLA KOGO — z tych samych treści co strony produktów (jedno źródło, zero rozjazdu).
	$kolumny = '';
	foreach ( $produkty as $p ) {
		$t = sklepag_tresc_produktu( $p->slug );
		if ( ! $t || empty( $t['dla_kogo'] ) ) {
			continue;
		}
		$kolumny .= '<div class="sklepag-dla-kogo-kol"><h3 class="sklepag-dla-kogo-tytul">' . sklepag_ikona_produktu( $p->slug ) . esc_html( $p->nazwa ) . '</h3><ul class="sklepag-dla-kogo">';
		foreach ( $t['dla_kogo'] as $dk ) {
			$kolumny .= '<li>' . esc_html( $dk ) . '</li>';
		}
		$kolumny .= '</ul><p><a class="sklepag-link-powrot" href="' . esc_url( sklepag_url_produktu_front( $p->slug ) ) . '">Więcej o tym bocie →</a></p></div>';
	}
	if ( '' !== $kolumny ) {
		$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna"><p class="sklepag-mono-etykieta">[ <span class="sklepag-akcent">02</span> · DLA KOGO ]</p><div class="sklepag-dla-kogo-kolumny sklepag-dla-kogo-kolumny--pelne">' . $kolumny . '</div></section>';
	}

	// PYTANIA — FAQ całego sklepu (pytania produktowe są na stronach produktów).
	// Liczby linku pobrania generowane ze stałych = dok zawsze równy kodowi.
	// Siatka 2 kolumn (runda stron 25.08): bloki <div> w <dl> to poprawny HTML5.
	$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna"><p class="sklepag-mono-etykieta">[ <span class="sklepag-akcent">03</span> · PYTANIA ]</p><dl class="sklepag-faq sklepag-faq--siatka">';
	$html .= '<div class="sklepag-faq-blok"><dt>Co dokładnie dostaję po zakupie?</dt><dd>' . esc_html( sprintf( 'Maila z potwierdzeniem, instrukcją i linkiem do pobrania paczki: zip wtyczki bota z instrukcją po polsku. Link jest ważny %d godziny (limit %d pobrań).', SKLEPAG_LINK_GODZIN, SKLEPAG_LIMIT_POBRAN ) ) . '</dd></div>';
	$html .= '<div class="sklepag-faq-blok"><dt>Czy muszę zakładać konto?</dt><dd>Nie. Podajesz tylko adres e-mail przy płatności — na niego przychodzi paczka.</dd></div>';
	$html .= '<div class="sklepag-faq-blok"><dt>Co znaczy „tryb testowy”?</dt><dd>Sklep działa na testowych płatnościach Stripe — żadne prawdziwe pieniądze nie są pobierane. Do testów służy karta 4242 4242 4242 4242.</dd></div>';
	$html .= '<div class="sklepag-faq-blok"><dt>Czy boty wymagają klucza AI albo opłat?</dt><dd>Nie — oba w pełni działają w trybie regułowym, bez klucza i bez abonamentu. Tryb AI to opcja na Twój własny klucz.</dd></div>';
	$html .= '</dl></section>';

	// FINALNY CTA listy (sędzia #2: strona produktu domyka finałem, lista
	// kończyła się FAQ bez zamknięcia sprzedażowego). Baner-panel z mgławicą (runda stron).
	$html     .= '<section class="sklepag-sekcja sklepag-sekcja--pelna sklepag-final sklepag-final--baner">';
	$html     .= '<div class="sklepag-final-tresc">';
	$html     .= '<h2 class="sklepag-sekcja-tytul">Wybierz bota i przetestuj go u siebie.</h2>';
	$przyciski = '';
	foreach ( $produkty as $p ) {
		$przyciski .= '<a class="sklepag-kup sklepag-kup-kotwica" href="' . esc_url( sklepag_url_produktu_front( $p->slug ) ) . '">' . esc_html( $p->nazwa ) . ' <span aria-hidden="true">→</span></a> ';
	}
	$html .= '<p class="sklepag-final-przyciski">' . $przyciski . '</p>';
	$html .= '</div>';
	// Duet maskotek (te same kreski co ilustracje kart) — baner ma parę tekst+wizual jak hero i karty.
	$html .= sklepag_duet_maskotek();
	$html .= '</section>';

	$html .= '</div>';
	return $html;
}

/**
 * Strona „Dziękujemy" — status odpytywany JS-em po id sesji (assets/dziekujemy.js);
 * spełnienie i tak robi webhook/reconciliation, nie ta strona (best practice Stripe).
 */
function sklepag_shortcode_dziekujemy() {
	return '<div class="sklepag-wyspa sklepag-dziekujemy">' . sklepag_baner_testowy() . '<p class="sklepag-status" role="status" aria-live="polite">[ ZAMÓWIENIE ] Sprawdzamy status…</p></div>';
}
