<?php
/**
 * Strona produktu (K5, PLAN-WYGLADU-K5 §2B; PR 2 z 3):
 * lista i produkt żyją na JEDNEJ stronie WP „Sklep" — przełącza query var
 * `sklepag_produkt` (slug). Przyjazny adres /{slug-sklepu}/produkt/{slug}/
 * przez rewrite (slug strony czytany z opcji — NIE hardkod „sklep");
 * fallback ?sklepag_produkt=slug działa zawsze (permalinki wyłączone też).
 * KAŻDY produkt dostaje pełną stronę z pól bazy (kryterium #7: nowy produkt
 * bez zmian kodu); dwa znane slugi dostają DODATKOWO bogate sekcje
 * (boty/TRESC-STRON-PRODUKTOW.md — problem/kroki/dowody/FAQ/mockupy).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query var widoku produktu.
 *
 * @param array $vars Query vars WP.
 */
function sklepag_query_vars( $vars ) {
	$vars[] = 'sklepag_produkt';
	return $vars;
}
add_filter( 'query_vars', 'sklepag_query_vars' );

/**
 * Slug strony sklepu z opcji (krytyk #4: hardkod „sklep" pęka przy „-2").
 */
function sklepag_slug_strony_sklepu() {
	$id = (int) get_option( 'sklepag_strona_sklep' );
	return $id ? (string) get_post_field( 'post_name', $id ) : '';
}

/**
 * Rewrite: /{slug-sklepu}/produkt/{slug-produktu}/ → strona sklepu + query var.
 */
function sklepag_rejestruj_rewrite() {
	$slug = sklepag_slug_strony_sklepu();
	$id   = (int) get_option( 'sklepag_strona_sklep' );
	if ( '' === $slug || ! $id ) {
		return;
	}
	add_rewrite_rule(
		'^' . preg_quote( $slug, '#' ) . '/produkt/([^/]+)/?$',
		'index.php?page_id=' . $id . '&sklepag_produkt=$matches[1]',
		'top'
	);

	// Self-heal flush: raz, gdy zmienił się slug/strona/wersja reguły
	// (aktualizacja wtyczki nie odpala aktywacji — wzorzec crona z K1).
	$podpis = md5( $slug . '|' . $id . '|v1' );
	if ( get_option( 'sklepag_rewrite_podpis' ) !== $podpis ) {
		flush_rewrite_rules( false );
		update_option( 'sklepag_rewrite_podpis', $podpis, false );
	}
}
add_action( 'init', 'sklepag_rejestruj_rewrite' );

/**
 * Adres strony produktu: ładny przy permalinkach, query var bez nich.
 *
 * @param string $slug Slug produktu.
 */
function sklepag_url_produktu_front( $slug ) {
	$strona = (int) get_option( 'sklepag_strona_sklep' );
	$baza   = $strona ? get_permalink( $strona ) : home_url( '/' );
	if ( get_option( 'permalink_structure' ) ) {
		return trailingslashit( $baza ) . 'produkt/' . rawurlencode( $slug ) . '/';
	}
	return add_query_arg( 'sklepag_produkt', rawurlencode( $slug ), $baza );
}

/**
 * Produkt AKTYWNY po slugu (widok publiczny nie pokazuje wyłączonych).
 *
 * @param string $slug Slug produktu.
 */
function sklepag_produkt_po_slugu( $slug ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- własna tabela; widok musi być świeży po CRUD.
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT id, nazwa, slug, opis_krotki, opis_pelny, cena_grosze, waluta FROM {$wpdb->prefix}sklepag_produkty WHERE slug = %s AND aktywny = 1", $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefiks WP.
	);
}

/**
 * Bogata treść znanych produktów (TRESC-STRON-PRODUKTOW.md).
 * Null = produkt spoza dwójki → strona generyczna z pól bazy.
 *
 * @param string $slug Slug produktu.
 */
function sklepag_tresc_produktu( $slug ) {
	$tresci = array(
		'chatbot-live'     => array(
			'etykieta'     => '[ <span class="sklepag-akcent">BOT</span> · CZAT NA STRONĘ ]',
			'zdanie'       => 'Odpowiada klientom 24/7. A gdy nie zna odpowiedzi — bierze kontakt i oddaje rozmowę Tobie.',
			'pasek_glowa'  => 'BOT · ONLINE',
			'pasek'        => 'ODPOWIADA 24/7',
			// Człon „przetestuj" tylko gdy bąbelek REALNIE widoczny: wtyczka aktywna
			// I włącznik w jej ustawieniach ON (recenzja #52 — sama aktywność nie
			// gwarantuje widgetu; właściciel może go wyłączyć bez deaktywacji).
			'pasek_zywy'   => ( function_exists( 'chatl_ustawienia' ) && ! empty( chatl_ustawienia()['wlaczony'] ) ) ? ' — PRZETESTUJ: BĄBELEK W ROGU' : '',
			'problem_t'    => 'Te same pytania. Codziennie.',
			'problem'      => 'Cennik? Godziny? Dojazd? Każde z nich to przerwana robota — albo klient, który pisał po godzinach i nie doczekał się odpowiedzi.',
			'mockup_czatu' => true,
			'przedpo'      => false,
			'funkcje'      => array(
				array( 'Baza pytań i odpowiedzi', 'Wpisujesz własne pary z wariantami słów („cena, ceny, cennik") — bot odpowiada z TWOJEJ bazy, zero konfabulacji.' ),
				array( 'Zbieranie kontaktów', 'Bot nie zna odpowiedzi → prosi o maila i zapisuje zgłoszenie. Stały przycisk „zostaw wiadomość" od początku rozmowy.' ),
				array( 'Historia rozmów', 'Zapis wymian w wp-admin (retencja 90 dni) + lista zgłoszeń do odpisania.' ),
				array( 'Start od pierwszej minuty', '10 gotowych par pytań po polsku w zestawie — działa zaraz po instalacji.' ),
				array( 'Tryb AI (opcjonalny)', 'Twój klucz API, odpowiedzi tylko z informacji o Twojej firmie, dzienny limit kosztów i automatyczny powrót do reguł przy błędzie.' ),
			),
			'kroki'        => array(
				'Zainstaluj wtyczkę i włącz bąbelek.',
				'Wpisz swoje pytania i odpowiedzi.',
				'Bot odpowiada, Ty dostajesz kontakty do klientów.',
			),
			'dowody'       => array(
				array( '24/7', 'bot nie ma godzin pracy — odpowiada też wtedy, gdy Ty śpisz' ),
				array( '10 par Q&A', 'zestaw startowy po polsku, policzalny w paczce' ),
				array( '100% rozmów u Ciebie', 'tryb regułowy: treść nie opuszcza Twojego WordPressa' ),
			),
			'dla_kogo'     => array( 'Usługi lokalne — fryzjer, warsztat, gabinet', 'Sklepy i firmy z powtarzalnymi pytaniami', 'Każdy, kto traci klientów piszących po godzinach' ),
			'uczciwosc'    => 'Nie znajdziesz tu opinii z gwiazdkami — sprzedajemy od niedawna i nie fabrykujemy ocen; zamiast tego masz tryb testowy i seed demo, żeby sprawdzić samemu. Pełnoprawny silnik regułowy działa od razu — bez kluczy i opłat. „LIVE" znaczy dwie rzeczy: bot odpowiada natychmiast, a Ty możesz w każdej chwili PRZEJĄĆ rozmowę i pisać do gościa osobiście — wtedy bot w niej milczy, a gość widzi wprost, że rozmawia z człowiekiem; po pięciu minutach Twojej ciszy rozmowa sama wraca do bota. Gdy nie przejmiesz, bot zbiera kontakt i odpisujesz później. Bot nie udaje wszechwiedzy. Tryb AI włączasz WŁASNYM kluczem; przy limicie dziennym lub błędzie bot sam wraca do reguł. Prywatność: w trybie regułowym treść rozmów nie opuszcza Twojego serwera; w trybie AI pytanie gościa idzie do API dostawcy (informacja też do Twojej polityki prywatności).',
			'faq'          => array(
				array( 'Czy potrzebuję klucza AI?', 'Nie — bot w pełni działa bez niego, na Twojej bazie pytań i odpowiedzi.' ),
				array( 'Co się dzieje, gdy bot nie zna odpowiedzi?', 'Prosi o maila, zapisuje zgłoszenie i może powiadomić Cię mailem — kontakt nie przepada.' ),
				array( 'Czy bot sam się uczy?', 'Nie — odpowiada wyłącznie z Twojej bazy. To zaleta: zero zmyślonych odpowiedzi o Twojej firmie.' ),
				array( 'Jak wstawię bota na stronę?', 'Instalujesz wtyczkę — bąbelek pojawia się sam; włącznik i teksty w ustawieniach.' ),
				array( 'Co z RODO?', 'Zgłoszenia gości zostają w Twojej bazie. W trybie AI treść pytań idzie do dostawcy API — gotowa wzmianka do Twojej polityki prywatności jest w instrukcji.' ),
			),
			'final'        => 'Zacznij odpowiadać w nocy. Bez nocek.',
		),
		'segregator-maila' => array(
			'etykieta'     => '[ <span class="sklepag-akcent">BOT</span> · PORZĄDEK W POCZCIE ]',
			'zdanie'       => 'Rozdziela pocztę do kategorii według Twoich reguł — i pokazuje raport, ile roboty zrobił za Ciebie.',
			'pasek_glowa'  => 'SEGREGATOR · GOTOWY',
			'pasek'        => 'PRÓBKA 30 MAILI W ZESTAWIE',
			'pasek_zywy'   => '',
			'problem_t'    => 'Wszystko na jednej kupie.',
			'problem'      => 'Oferty, faktury, reklamacje i spam w jednym strumieniu. Zanim odpiszesz ważnym, przekopujesz się przez resztę.',
			'mockup_czatu' => false,
			'przedpo'      => true,
			'funkcje'      => array(
				array( 'Reguły segregacji', 'Nadawca / temat / słowa w treści → kategoria. Kolejność na liście rozstrzyga: pierwsza pasująca wygrywa — zero niejednoznaczności.' ),
				array( 'Kategorie pod Twoją firmę', 'Dowolne nazwy, pełny CRUD: Oferty, Faktury, Reklamacje — jak chcesz.' ),
				array( 'Próbki zamiast ryzyka', 'Wbudowane 30 maili demo po polsku + import WŁASNEJ próbki z CSV (od, temat, treść).' ),
				array( 'Raport z liczbami', 'Ile do której kategorii, ile bez dopasowania, % pokrycia; przy próbce z oczekiwaną kategorią — trafność i lista pomyłek.' ),
				array( 'Tryb AI (opcjonalny)', 'Twój klucz: klasyfikacja modelem + przebieg „porównaj reguły vs AI" z raportem różnic.' ),
			),
			'kroki'        => array(
				'Zainstaluj wtyczkę.',
				'Ułóż reguły pod swoją skrzynkę.',
				'Puść przebieg i czytaj raport.',
			),
			'dowody'       => array(
				array( '30 maili demo', 'przebieg testowy od pierwszej minuty, bez podpinania czegokolwiek' ),
				array( '% pokrycia', 'liczony z TWOJEJ próbki — raport pokazuje liczby, nie obietnice' ),
				array( '0 maili na zewnątrz', 'tryb regułowy działa w całości w Twoim WordPressie' ),
			),
			'dla_kogo'     => array( 'Biura i sekretariaty', 'Firmy usługowe z jedną wspólną skrzynką', 'Każdy, kto zaczyna dzień od przekopywania poczty' ),
			'uczciwosc'    => 'Nie znajdziesz tu opinii z gwiazdkami — sprzedajemy od niedawna i nie fabrykujemy ocen; zamiast tego masz raport z liczbami z TWOJEJ próbki. Reguły układasz i sprawdzasz na próbce, a potem puszczasz je na PRAWDZIWEJ skrzynce: wtyczka rozkłada pocztę do folderów na serwerze poczty, domyślnie chodzi NA SUCHO i nie rusza ani jednej wiadomości, dopóki sam nie potwierdzisz, a ostatni przebieg cofniesz jednym kliknięciem. Czego nie ma, mówimy przed zakupem: logowanie tylko loginem i hasłem aplikacji, więc Microsoft 365 i Outlook.com NIE zadziałają (tam wymagane jest OAuth); na skrzynce klasyfikują reguły, nie AI; bez czytania załączników.',
			'faq'          => array(
				array( 'Czy muszę podpinać skrzynkę?', 'Nie musisz — możesz pracować wyłącznie na próbkach CSV. Ale możesz: wtyczka obsługuje prawdziwą skrzynkę i przenosi w niej pocztę do folderów.' ),
				array( 'Zadziała z Microsoft 365 albo Outlook.com?', 'Nie. Tam wymagane jest logowanie OAuth, którego ta wtyczka nie obsługuje. Gmail i skrzynki hostingowe z hasłem aplikacji: tak.' ),
				array( 'Czy coś mi się pomiesza w poczcie?', 'Domyślny tryb na sucho niczego nie rusza — pokazuje tylko, co by zrobił. Realne przenoszenie wymaga przełączenia i osobnego potwierdzenia, a ostatni przebieg da się cofnąć.' ),
				array( 'Skąd wezmę CSV?', 'Eksport z klienta poczty (Gmail, Thunderbird, Outlook) — instrukcja pokazuje jak, krok po kroku.' ),
				array( 'Co znaczy trafność (accuracy)?', 'Dodajesz do próbki kolumnę z oczekiwaną kategorią — raport liczy % trafień i pokazuje pomyłki.' ),
				array( 'Czy AI jest wymagane?', 'Nie — pełny tryb regułowy działa bez klucza. AI to opcja do porównania wyników.' ),
				array( 'Co z RODO?', 'Próbki zostają w Twojej bazie. W trybie AI treść idzie do dostawcy API — gotowa wzmianka do polityki prywatności w instrukcji.' ),
			),
			'final'        => 'Niech poczta sama się układa.',
		),
	);
	return isset( $tresci[ $slug ] ) ? $tresci[ $slug ] : null;
}

/**
 * Zrzut ŻYWEGO bota do karty i strony produktu (feedback Dzidka 19.08: „potrzeba
 * obrazów do produktów"). Zrzuty robione z tej instalacji, bez retuszu — spójne
 * z sekcją uczciwości. Wymiary wpisane na sztywno = brak CLS przy lazy-load.
 *
 * @param string $slug Slug produktu.
 * @return array|null Dane obrazu (plik/alt/podpis/szer/wys) albo null.
 */
function sklepag_zrzut_produktu( $slug ) {
	$zrzuty = array(
		'chatbot-live'     => array(
			'plik'   => 'chatbot-live.jpg',
			'alt'    => 'Okno rozmowy ChatBota LIVE na stronie: gość pyta o godziny otwarcia, bot odpowiada wpisem z bazy pytań i odpowiedzi',
			'podpis' => 'PRAWDZIWY ZRZUT Z TEJ STRONY — TRYB REGUŁOWY, BEZ KLUCZA AI',
			'szer'   => 682,
			'wys'    => 908,
		),
		'segregator-maila' => array(
			'plik'   => 'segregator-maila.jpg',
			'alt'    => 'Raport przebiegu Segregatora maila w panelu WordPressa: 30 maili w próbce, pokrycie regułami 80 procent, trafność 100 procent, podział na kategorie',
			'podpis' => 'PRAWDZIWY RAPORT Z PANELU — PRÓBKA 30 MAILI DEMO, LICZBY BEZ RETUSZU',
			'szer'   => 1200,
			'wys'    => 470,
		),
	);
	return isset( $zrzuty[ $slug ] ) ? $zrzuty[ $slug ] : null;
}

/**
 * Ilustracja produktu do KARTY listy (feedback Dzidka 19.08: „fajne obrazy —
 * robot segregujący maile, ktoś odpisujący na czacie"). Własne SVG w palecie
 * strony (limonkowy line-art, roboty-bliźniaki) zamiast generycznego stocka;
 * zrzut żywego bota zostaje na stronie produktu jako dowód.
 *
 * @param string $slug Slug produktu.
 * @return array|null Dane obrazu (plik/alt/szer/wys) albo null.
 */
function sklepag_ilustracja_produktu( $slug ) {
	$ilustracje = array(
		'chatbot-live'     => array(
			'plik' => 'chatbot-live.svg',
			'alt'  => 'Ilustracja: robot odpisuje klientom na czacie — gość pyta, bot odpowiada z Twojej bazy',
			'szer' => 880,
			'wys'  => 560,
		),
		'segregator-maila' => array(
			'plik' => 'segregator-maila.svg',
			'alt'  => 'Ilustracja: robot segreguje maile — koperty trafiają do przegródek Oferty, Faktury i Reszta',
			'szer' => 880,
			'wys'  => 560,
		),
	);
	return isset( $ilustracje[ $slug ] ) ? $ilustracje[ $slug ] : null;
}

/**
 * Znacznik <figure> z obrazem produktu (wspólny dla karty listy i strony produktu).
 *
 * @param array  $zrzut Dane z sklepag_zrzut_produktu()/sklepag_ilustracja_produktu().
 * @param string $klasa Klasa CSS figure.
 * @param bool   $z_podpisem Czy dodać figcaption.
 * @param bool   $z_paskiem  Czy dodać pasek „okna aplikacji" (tylko dla zrzutów —
 *                           ilustracja nie udaje okna programu).
 */
function sklepag_figure_zrzutu( $zrzut, $klasa, $z_podpisem, $z_paskiem = true ) {
	// Modyfikator per produkt (kadr okna karty ustawiany w CSS, np. dolna
	// część okna czatu zamiast uciętego nagłówka). Sufiks ZAWSZE na bazowym
	// (pierwszym) członie klasy — przy wieloczłonowym $klasa doklejanie do
	// całości dublowało tokeny i celowało w zły człon (recenzja #60).
	$czlony = explode( ' ', $klasa );
	$klasa .= ' ' . $czlony[0] . '--' . sanitize_html_class( pathinfo( $zrzut['plik'], PATHINFO_FILENAME ) );
	$html   = '<figure class="' . esc_attr( $klasa ) . '">';
	if ( $z_paskiem ) {
		// Pasek „okna aplikacji" (3 kropki jak w mockupie czatu): wspólna rama godzi
		// ciemny widget z jasnym panelem wp-admin — uwaga sędziego o dwóch językach.
		$html .= '<span class="sklepag-okno-pasek" aria-hidden="true"><span></span><span></span><span></span></span>';
	}
	$html .= '<img src="' . esc_url( plugins_url( 'assets/produkty/' . $zrzut['plik'], SKLEPAG_PLIK ) ) . '" alt="' . esc_attr( $zrzut['alt'] ) . '" width="' . (int) $zrzut['szer'] . '" height="' . (int) $zrzut['wys'] . '" loading="lazy">';
	if ( $z_podpisem ) {
		$html .= '<figcaption class="sklepag-mono-etykieta">' . esc_html( $zrzut['podpis'] ) . '</figcaption>';
	}
	$html .= '</figure>';
	return $html;
}

/**
 * Mono-pictogram produktu (sędzia wyglądu: karty nierozróżnialne bez ikon).
 * Inline SVG w limonce — zero CDN/fontów ikon (D27). Fallback: iskra.
 *
 * @param string $slug Slug produktu.
 */
function sklepag_ikona_produktu( $slug ) {
	$wspolne = 'class="sklepag-ikona" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
	if ( 'chatbot-live' === $slug ) {
		return '<svg ' . $wspolne . '><path d="M21 12a8 8 0 0 1-8 8H5l-2 2V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z"/><circle cx="9.5" cy="12" r=".8" fill="currentColor"/><circle cx="13" cy="12" r=".8" fill="currentColor"/><circle cx="16.5" cy="12" r=".8" fill="currentColor"/></svg>';
	}
	if ( 'segregator-maila' === $slug ) {
		return '<svg ' . $wspolne . '><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6.5 9 7 9-7"/><path d="M7 22h10"/></svg>';
	}
	return '<svg ' . $wspolne . '><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l2.8 2.8M16.2 16.2 19 19M19 5l-2.8 2.8M7.8 16.2 5 19"/></svg>';
}

/**
 * Formularz zakupu (jedyna droga: checkbox zgody + nonce — mechanika K1 bez zmian).
 *
 * @param object $p Produkt (wiersz bazy).
 */
function sklepag_formularz_zakupu( $p ) {
	$id_zgody = 'sklepag-zgoda-' . (int) $p->id;
	$html     = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sklepag-zakup" id="sklepag-kup-formularz">';
	$html    .= '<input type="hidden" name="action" value="sklepag_kup">';
	$html    .= '<input type="hidden" name="produkt_id" value="' . (int) $p->id . '">';
	$html    .= wp_nonce_field( 'sklepag_kup', '_wpnonce', true, false );
	$html    .= '<label class="sklepag-zgoda" for="' . esc_attr( $id_zgody ) . '"><input type="checkbox" id="' . esc_attr( $id_zgody ) . '" name="zgoda" value="1" required> <span>' . esc_html( SKLEPAG_ZGODA_TRESC ) . '</span></label>';
	$html    .= '<button type="submit" class="sklepag-kup">Kup teraz (płatność testowa)</button>';
	$html    .= '<p class="sklepag-mono-etykieta sklepag-zakup-notka">STRIPE · TRYB TESTOWY · KARTA 4242 4242 4242 4242</p>';
	$html    .= '</form>';
	return $html;
}

/**
 * Statyczny mockup okna czatu (wzór tidio — dymki ZAWSZE w ramce; czysty CSS).
 */
function sklepag_mockup_czatu() {
	return '<div class="sklepag-mockup" aria-hidden="true">'
		. '<div class="sklepag-mockup-pasek"><span></span><span></span><span></span></div>'
		. '<div class="sklepag-mockup-tresc">'
		. '<p class="sklepag-dymek sklepag-dymek-gosc">Ile kosztuje strzyżenie?</p>'
		. '<p class="sklepag-dymek sklepag-dymek-bot">Strzyżenie damskie od 80 zł, męskie od 50 zł. Pełny cennik: automatic.ai/cennik</p>'
		. '<p class="sklepag-dymek sklepag-dymek-gosc">A w soboty pracujecie?</p>'
		. '<p class="sklepag-dymek sklepag-dymek-bot sklepag-pisze"><span></span><span></span><span></span></p>'
		. '</div></div>';
}

/**
 * Wizualizacja PRZED/PO segregacji (nasza przewaga — PLAN-WYGLADU-K5 §5b.3).
 */
function sklepag_przed_po() {
	$przed = array( 'FV 88/2026 — księgowość', 'RE: wycena ogrodzenia', 'Wygraj bon 5000 zł!!!', 'reklamacja zamówienia 113', 'oferta współpracy B2B' );
	$po    = array(
		array( 'Faktury', 'FV 88/2026 — księgowość' ),
		array( 'Oferty', 'RE: wycena ogrodzenia' ),
		array( 'Podejrzane', 'Wygraj bon 5000 zł!!!' ),
		array( 'Reklamacje', 'reklamacja zamówienia 113' ),
		array( 'Oferty', 'oferta współpracy B2B' ),
	);
	$html  = '<div class="sklepag-przedpo">';
	$html .= '<p class="sklepag-mono-etykieta">[ PRZYKŁAD · DANE DEMO ]</p>';
	$html .= '<div class="sklepag-przedpo-kolumny">';
	$html .= '<div class="sklepag-przedpo-kol"><p class="sklepag-przedpo-naglowek">Zanim</p>';
	foreach ( $przed as $m ) {
		$html .= '<p class="sklepag-mail-chaos">' . esc_html( $m ) . '</p>';
	}
	$html .= '</div>';
	$html .= '<div class="sklepag-przedpo-kol"><p class="sklepag-przedpo-naglowek">Po</p>';
	foreach ( $po as $m ) {
		$html .= '<p class="sklepag-mail-po"><span class="sklepag-tag">' . esc_html( $m[0] ) . '</span> ' . esc_html( $m[1] ) . '</p>';
	}
	$html .= '</div></div></div>';
	return $html;
}

/**
 * Pełny widok strony produktu.
 *
 * @param string $slug Slug produktu z query var.
 */
function sklepag_render_produkt( $slug ) {
	$p = sklepag_produkt_po_slugu( $slug );

	if ( ! $p ) {
		status_header( 404 );
		return '<div class="sklepag-wyspa"><p class="sklepag-mono-etykieta">[ SKLEP ]</p>'
			. '<p class="sklepag-pusto">[ PRODUKT NIE ISTNIEJE ALBO NIE JEST JUŻ W SPRZEDAŻY ]</p>'
			. '<p><a class="sklepag-link-powrot" href="' . esc_url( get_permalink( (int) get_option( 'sklepag_strona_sklep' ) ) ) . '">← Wróć do sklepu</a></p></div>';
	}

	$t    = sklepag_tresc_produktu( $slug );
	$cena = '<p class="sklepag-cena">' . esc_html( number_format( $p->cena_grosze / 100, 2, ',', ' ' ) ) . '<span class="sklepag-cena-waluta">' . esc_html( $p->waluta ) . '</span></p>';

	$html  = '<div class="sklepag-wyspa sklepag-produkt">';
	$html .= '<nav class="sklepag-okruszki" aria-label="Okruszki"><a href="' . esc_url( get_permalink( (int) get_option( 'sklepag_strona_sklep' ) ) ) . '">Sklep</a> <span aria-hidden="true">/</span> <span>' . esc_html( $p->nazwa ) . '</span></nav>';

	// HERO.
	$html .= '<header class="sklepag-hero">';
	$html .= '<p class="sklepag-mono-etykieta">' . ( $t ? wp_kses( $t['etykieta'], array( 'span' => array( 'class' => array() ) ) ) : '[ <span class="sklepag-akcent">BOT</span> · TESTOWY ]' ) . '</p>';
	$html .= '<h1 class="sklepag-hero-tytul">' . esc_html( $p->nazwa ) . '</h1>';
	$html .= '<p class="sklepag-lead">' . esc_html( $t ? $t['zdanie'] : $p->opis_krotki ) . '</p>';
	if ( $t ) {
		$glowa = explode( ' · ', $t['pasek_glowa'], 2 );
		$html .= '<p class="sklepag-pasek-statusu"><span class="sklepag-kropka" aria-hidden="true"></span>[ <span class="sklepag-akcent">' . esc_html( $glowa[0] ) . '</span> · ' . esc_html( isset( $glowa[1] ) ? $glowa[1] : '' ) . ' ] ' . esc_html( $t['pasek'] . $t['pasek_zywy'] ) . '</p>';
	}
	$html .= $cena;
	$html .= '<p><a class="sklepag-kup sklepag-kup-kotwica" href="#sklepag-kup-formularz">Kup teraz (płatność testowa)</a></p>';
	$html .= sklepag_baner_testowy();
	$html .= '</header>';

	if ( $t ) {
		// PROBLEM.
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ PROBLEM ]</p>';
		$html .= '<h2 class="sklepag-sekcja-tytul">' . esc_html( $t['problem_t'] ) . '</h2>';
		$html .= '<p class="sklepag-sekcja-tekst">' . esc_html( $t['problem'] ) . '</p></section>';

		// CO DOSTAJESZ (+ mockup czatu przy chatbocie).
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ CO DOSTAJESZ ]</p>';
		$html .= '<div class="sklepag-funkcje-uklad">';
		$html .= '<div class="sklepag-funkcje">';
		foreach ( $t['funkcje'] as $f ) {
			$html .= '<div class="sklepag-funkcja"><h3>' . esc_html( $f[0] ) . '</h3><p>' . esc_html( $f[1] ) . '</p></div>';
		}
		$html .= '</div>';
		if ( $t['mockup_czatu'] ) {
			$html .= sklepag_mockup_czatu();
		}
		$html .= '</div></section>';

		// JAK DZIAŁA — 3 kroki (numeracja = realna sekwencja).
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ JAK DZIAŁA ]</p><ol class="sklepag-kroki">';
		foreach ( $t['kroki'] as $k ) {
			$html .= '<li>' . esc_html( $k ) . '</li>';
		}
		$html .= '</ol></section>';

		// JAK TO WYGLĄDA — zrzut żywego bota (feedback Dzidka 19.08).
		$zrzut = sklepag_zrzut_produktu( $slug );
		if ( $zrzut ) {
			$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ JAK TO WYGLĄDA ]</p>';
			$html .= sklepag_figure_zrzutu( $zrzut, 'sklepag-zrzut', true );
			$html .= '</section>';
		}

		// ILE CZASU ODZYSKUJESZ (S-03; krytyk #9 — dedykowane miejsce).
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ ILE CZASU ODZYSKUJESZ ]</p>';
		$html .= '<div class="sklepag-dowody">';
		foreach ( $t['dowody'] as $d ) {
			$html .= '<div class="sklepag-dowod"><p class="sklepag-dowod-liczba">' . esc_html( $d[0] ) . '</p><p class="sklepag-dowod-podpis">' . esc_html( $d[1] ) . '</p></div>';
		}
		$html .= '</div>';
		if ( $t['przedpo'] ) {
			$html .= sklepag_przed_po();
		}
		$html .= '</section>';

		// DLA KOGO.
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ DLA KOGO ]</p><ul class="sklepag-dla-kogo">';
		foreach ( $t['dla_kogo'] as $dk ) {
			$html .= '<li>' . esc_html( $dk ) . '</li>';
		}
		$html .= '</ul></section>';

		// UCZCIWOŚĆ (zamiast fejk-opinii — D6/Omnibus).
		$html .= '<section class="sklepag-sekcja sklepag-uczciwosc"><p class="sklepag-mono-etykieta">[ CO DOKŁADNIE DOSTAJESZ — I CZEGO NIE MA ]</p>';
		$html .= '<p class="sklepag-sekcja-tekst">' . esc_html( $t['uczciwosc'] ) . '</p></section>';
	} elseif ( '' !== trim( (string) $p->opis_pelny ) ) {
		// Produkt spoza dwójki: opis pełny z bazy, render jako tekst (XSS — plan §7).
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ O PRODUKCIE ]</p>';
		$html .= '<p class="sklepag-sekcja-tekst">' . nl2br( esc_html( $p->opis_pelny ) ) . '</p></section>';
	}

	// CENA + ZAKUP.
	$html .= '<section class="sklepag-sekcja sklepag-sekcja-zakup"><p class="sklepag-mono-etykieta">[ CENA ]</p>';
	$html .= $cena . sklepag_formularz_zakupu( $p ) . '</section>';

	// FAQ.
	if ( $t && ! empty( $t['faq'] ) ) {
		$html .= '<section class="sklepag-sekcja"><p class="sklepag-mono-etykieta">[ FAQ ]</p><dl class="sklepag-faq">';
		foreach ( $t['faq'] as $f ) {
			$html .= '<dt>' . esc_html( $f[0] ) . '</dt><dd>' . esc_html( $f[1] ) . '</dd>';
		}
		$html .= '</dl></section>';
	}

	// FINALNY CTA.
	$html .= '<section class="sklepag-sekcja sklepag-final">';
	$html .= '<h2 class="sklepag-sekcja-tytul">' . esc_html( $t ? $t['final'] : 'Gotowy na start?' ) . '</h2>';
	$html .= '<p><a class="sklepag-kup sklepag-kup-kotwica" href="#sklepag-kup-formularz">Kup teraz (płatność testowa)</a></p>';
	$html .= '</section>';

	$html .= '</div>';
	return $html;
}
