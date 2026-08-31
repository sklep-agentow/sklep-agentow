<?php
/**
 * Strona produktu:
 * lista i produkt żyją na JEDNEJ stronie WP „Sklep" — przełącza query var
 * `sklepag_produkt` (slug). Przyjazny adres /{slug-sklepu}/produkt/{slug}/
 * przez rewrite (slug strony czytany z opcji — NIE hardkod „sklep");
 * fallback ?sklepag_produkt=slug działa zawsze (permalinki wyłączone też).
 * KAŻDY produkt dostaje pełną stronę z pól bazy (nowy produkt
 * bez zmian kodu); dwa znane slugi dostają DODATKOWO bogate sekcje
 * (układ sekcji: problem/kroki/dowody/FAQ/mockupy).
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
 * Slug strony sklepu z opcji (hardkod „sklep" pęka przy „-2").
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
	// (aktualizacja wtyczki nie odpala aktywacji — jak przy harmonogramie crona).
	$podpis = md5( $slug . '|' . $id . '|v1' );
	if ( get_option( 'sklepag_rewrite_podpis' ) !== $podpis ) {
		flush_rewrite_rules( false );
		update_option( 'sklepag_rewrite_podpis', $podpis, false );
	}
}
add_action( 'init', 'sklepag_rejestruj_rewrite' );

/**
 * Status 404 USTAWIANY WCZEŚNIE (template_redirect), gdy slug wskazuje produkt,
 * którego nie ma. Dotąd 404 ustawiał dopiero render skrótu (już w treści strony)
 * — żądanie HEAD dostawało 200, GET 404 (zmierzone curl 30.08). Strona przyjazna
 * „produkt nie istnieje” renderuje się dalej — zmieniamy tylko kod statusu.
 */
function sklepag_status_404_wczesnie() {
	$slug = (string) get_query_var( 'sklepag_produkt' );
	if ( '' === $slug || ! sklepag_jest_strona_z_opcji( 'sklepag_strona_sklep' ) ) {
		return;
	}
	if ( ! sklepag_produkt_po_slugu( sanitize_title( $slug ) ) ) {
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'sklepag_status_404_wczesnie' );

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
 * Sekcja OPINIE KLIENTÓW z tablicy wpisów. Nic do pokazania = pusty string (sekcji nie ma).
 *
 * Wpis = tablica z kluczami 'tresc'/'imie'/'gwiazdki'(1-5)/'tytul'/'data' — te same
 * klucze odda tabela `sklepag_opinie` po zbudowaniu mechanizmu. Plakietka
 * ZWERYFIKOWANY ZAKUP stoi przy KAŻDYM wpisie: nota pod sekcją gwarantuje
 * weryfikację wszystkich publikowanych opinii, więc wpis bez plakietki wyglądałby
 * jak „opinia podejrzana". Z tego samego powodu dawny format array( tresc, podpis )
 * — bez plakietki — jest POMIJANY, nie renderowany: w repo nie ma ani jednego
 * takiego wpisu, a lista mieszana przeczyłaby nocie.
 *
 * Wydzielone z sklepag_tresc_produktu, żeby render dało się udowodnić na dowolnej
 * tablicy, niezależnie od danych produktów — i tu wepnie się render z bazy
 * po zbudowaniu mechanizmu opinii.
 *
 * @param mixed         $opinie   Lista wpisów opinii (tablica; inny typ = brak sekcji).
 * @param callable|null $etykieta Generator numerowanej etykiety sekcji (strona produktu);
 *                                null = etykieta bez numeru. Wołany dopiero, gdy sekcja
 *                                naprawdę powstaje — numer nie rośnie dla pustej sekcji.
 * @return string HTML sekcji albo '' gdy nie ma czego pokazać.
 */
function sklepag_render_opinie( $opinie, $etykieta = null ) {
	if ( ! is_array( $opinie ) ) {
		return '';
	}
	$figury = '';
	foreach ( $opinie as $op ) {
		if ( ! is_array( $op ) || ! isset( $op['tresc'] ) ) {
			continue; // Dawny format bez klucza 'tresc' — pomijany (komentarz nad funkcją).
		}
		$imie = isset( $op['imie'] ) ? trim( (string) $op['imie'] ) : '';
		if ( '' === $imie ) {
			$imie = 'Klient'; // Bez imienia: znak zastępczy zamiast pustego krążka i pustego <strong>.
		}
		$gw = isset( $op['gwiazdki'] ) ? max( 1, min( 5, (int) $op['gwiazdki'] ) ) : 5;
		// mb_substr polyfilluje rdzeń WP (compat.php), mb_strtoupper NIE — bez
		// strażnika host bez mbstring padłby fatalem na stronie produktu.
		$inicjal = mb_substr( $imie, 0, 1 );
		$inicjal = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $inicjal ) : strtoupper( $inicjal );
		$figury .= '<figure class="sklepag-opinia"><div class="sklepag-opinia-glowa">';
		$figury .= '<span class="sklepag-avatar" aria-hidden="true">' . esc_html( $inicjal ) . '</span>';
		$figury .= '<div><p class="sklepag-opinia-imie"><strong>' . esc_html( $imie ) . '</strong>';
		$figury .= ' <span class="sklepag-opinia-badge">ZWERYFIKOWANY ZAKUP</span>';
		$figury .= '</p><p class="sklepag-gwiazdki" aria-label="Ocena ' . (int) $gw . ' na 5">' . str_repeat( '★', $gw ) . '<span class="sklepag-gwiazdki-puste">' . str_repeat( '★', 5 - $gw ) . '</span></p></div></div>';
		if ( ! empty( $op['tytul'] ) ) {
			$figury .= '<p class="sklepag-opinia-tytul">' . esc_html( $op['tytul'] ) . '</p>';
		}
		$figury .= '<blockquote>' . esc_html( $op['tresc'] ) . '</blockquote>';
		if ( ! empty( $op['data'] ) ) {
			$figury .= '<figcaption class="sklepag-mono-etykieta">' . esc_html( $op['data'] ) . '</figcaption>';
		}
		$figury .= '</figure>';
	}
	if ( '' === $figury ) {
		return '';
	}
	$naglowek = is_callable( $etykieta ) ? $etykieta( 'OPINIE KLIENTÓW' ) : '<p class="sklepag-mono-etykieta">[ OPINIE KLIENTÓW ]</p>';
	return '<section class="sklepag-sekcja">' . $naglowek . '<div class="sklepag-opinie">'
		. $figury
		. '</div><p class="sklepag-opinie-nota">Publikujemy wyłącznie opinie osób, które kupiły ten produkt — przed publikacją potwierdzamy zakup w danych zamówień sklepu.</p></section>';
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
			'zdanie'       => 'Nie trać klientów piszących po godzinach: bot odpowiada 24/7, zbiera kontakty, a trudne rozmowy oddaje Tobie.',
			'demo_cta'     => 'Porozmawiaj z botem TERAZ — to ten sam bot: kliknij zielony bąbelek w rogu strony i zadaj pytanie.',
			'pasek_glowa'  => 'BOT · ONLINE',
			'pasek'        => 'ODPOWIADA 24/7',
			// Człon „przetestuj" tylko gdy bąbelek REALNIE widoczny: wtyczka aktywna
			// I włącznik w jej ustawieniach ON (sama aktywność nie
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
			'uczciwosc'    => array(
				'<strong>Bot NIE zmyśla</strong> — odpowiada wyłącznie z Twojej bazy pytań i odpowiedzi. Nie zna odpowiedzi? Zbiera kontakt, Ty odpisujesz później.',
				'„LIVE" znaczy: w każdej chwili możesz PRZEJĄĆ rozmowę i pisać do gościa osobiście — bot milknie, a gość widzi wprost, że rozmawia z człowiekiem; po pięciu minutach Twojej ciszy rozmowa wraca do bota.',
				'Pełnoprawny silnik regułowy działa od razu — bez kluczy i opłat. Tryb AI włączasz WŁASNYM kluczem; przy limicie dziennym lub błędzie bot sam wraca do reguł.',
				'Prywatność: w trybie regułowym treść rozmów nie opuszcza Twojego serwera; w trybie AI pytanie gościa idzie do dostawcy API (gotowa wzmianka do Twojej polityki prywatności jest w instrukcji).',
			),
			/*
			 * Pliki galerii to KOPIE zrzutow z wtyczek botow (assets/w-srodku/ —
			 * samowystarczalnosc sklepu). Po regeneracji zrzutow instrukcji botow
			 * skopiuj nowe wersje takze tutaj. Kadry (*-kadr.webp) i plakat nagrania
			 * (*-plakat.webp) sa wyciete ffmpeg-iem z TYCH SAMYCH plikow (crop od gory,
			 * wymiary nizej) — po podmianie oryginalu wytnij je ponownie.
			 *
			 * FAZY: galeria opowiada w trzech wierszach
			 * strona -> brak odpowiedzi -> panel. Narracje sklejone z istniejacych tresci
			 * (funkcje, FAQ, alty) — zero nowych obietnic i liczb.
			 */
			'fazy'         => array(
				array(
					'tytul' => 'Na Twojej stronie',
					'opis'  => 'Bąbelek w rogu Twojej strony: gość pyta o cennik czy godziny, bot odpowiada z Twojej bazy pytań i odpowiedzi — także po godzinach.',
					'uklad' => 'wideo',
					'kafle' => array(
						array(
							// Świeży zrzut TEJ strony (Playwright, 1440 px): prawy dolny róg z ZAMKNIĘTYM bąbelkiem —
							// kontekst „na Twojej stronie"; obok nagranie otwartej rozmowy (dwa różne kadry, nie dubel).
							'plik'   => 'chatbot-live--babelek.webp',
							'kadr'   => 'contain',
							'alt'    => 'Prawy dolny róg strony z zielonym bąbelkiem czatu — okno rozmowy jest zamknięte, dopóki gość nie kliknie',
							'podpis' => 'TAK TO WYGLĄDA NA TWOJEJ STRONIE — BĄBELEK W ROGU, ZANIM GOŚĆ KLIKNIE',
							'szer'   => 1310,
							'wys'    => 780,
						),
						array(
							// Kadr okna czatu (crop 346×536 od x=930,y=260 z nagrania 1280×800 — okno stoi w tym samym
							// miejscu przez całe 19,9 s); oryginał chatbot-demo.webm zostaje w assets.
							'wideo'  => 'chatbot-demo-kadr.webm',
							'plakat' => 'chatbot-demo-plakat.webp',
							'alt'    => 'Nagranie: gość pyta „Ile kosztuje ChatBot LIVE?", bot od razu odpowiada ceną 149,00 zł i opisem z bazy; na „Czy jest gwarancja?" bot podaje 14-dniową gwarancję zwrotu',
							'podpis' => 'NAGRANIE Z TEJ STRONY — PYTANIA O CENĘ I GWARANCJĘ, ODPOWIEDZI Z BAZY',
							'szer'   => 346,
							'wys'    => 536,
						),
					),
				),
				array(
					'tytul' => 'Gdy bot nie zna odpowiedzi',
					'opis'  => 'Bot prosi o maila i zapisuje zgłoszenie — kontakt nie przepada. Przycisk „zostaw wiadomość" jest w oknie od początku rozmowy.',
					'uklad' => 'formularz',
					'kafle' => array(
						array(
							'plik'   => 'chatbot-live--widget-formularz.png',
							'kadr'   => 'contain',
							'alt'    => 'Formularz „Zostaw wiadomość" w oknie czatu: pola e-mail i treść, zgoda RODO',
							'podpis' => 'GDY BOT NIE ZNA ODPOWIEDZI — ZBIERA KONTAKT',
							'szer'   => 320,
							'wys'    => 440,
						),
						array(
							'tok'    => array(
								'Gość pyta o coś, czego nie ma w Twojej bazie.',
								'Bot prosi o adres e-mail i treść wiadomości.',
								'Zgłoszenie zapisuje się w wp-admin — na życzenie z mailem do Ciebie.',
								'Odpisujesz, kiedy masz chwilę. Kontakt nie przepada.',
							),
							'podpis' => 'TOK ZGŁOSZENIA — KONTAKT NIE PRZEPADA',
						),
					),
				),
				array(
					'tytul' => 'Twój panel',
					'opis'  => 'Ustawienia po polsku: włącznik bąbelka, teksty powitania, tryb AI z limitem dziennym. Statystyki pokazują, o co pytają goście i czego brakuje w bazie.',
					'uklad' => 'panel',
					'kafle' => array(
						array(
							'plik'      => 'chatbot-live--ustawienia.png',
							'kadr_plik' => 'chatbot-live--ustawienia-kadr.webp',
							'kadr_szer' => 1078,
							'kadr_wys'  => 500,
							'alt'       => 'Panel ustawień ChatBota w wp-admin: włącznik, teksty powitania, tryb AI z limitem dziennym',
							'podpis'    => 'PANEL USTAWIEŃ — WSZYSTKO PO POLSKU',
							'szer'      => 1078,
							'wys'       => 2857,
						),
						array(
							'plik'      => 'chatbot-live--statystyki.png',
							'kadr_plik' => 'chatbot-live--statystyki-kadr.webp',
							'kadr_szer' => 1100,
							'kadr_wys'  => 520,
							'alt'       => 'Statystyki ChatBota: 177 pytań, wykres ruchu dzień po dniu, lista pytań bez odpowiedzi',
							'podpis'    => 'STATYSTYKI I LUKI W BAZIE — WIDZISZ, O CO PYTAJĄ',
							'szer'      => 1100,
							'wys'       => 790,
						),
					),
				),
			),
			'pakiet'       => array(
				'Plik ZIP wtyczki — instalujesz jak każdą wtyczkę WordPressa, w kilka minut.',
				'Instrukcja krok po kroku z 6 zrzutami ekranu.',
				'10 gotowych par pytań i odpowiedzi po polsku — zestaw startowy.',
				'Dokumentacja techniczna z diagramem przepływu (dla Twojego informatyka).',
				'Historia zmian i licencja w paczce.',
			),
			'porownanie'   => array(
				'tytul'   => 'Telefon i skrzynka kontra bot',
				'kol'     => array( 'Robione ręcznie', 'Z botem' ),
				'wiersze' => array(
					array( 'Odpowiadasz w godzinach pracy.', 'Bot odpowiada 24/7 — także w święta i po 22.' ),
					array( 'Urlop albo chorobowe = cisza na stronie.', 'Bąbelek wisi na stronie zawsze.' ),
					array( 'Każde „ile kosztuje?" przerywa robotę.', 'Powtarzalne pytania zdejmuje baza Q&A.' ),
					array( 'Kontakt z telefonu łatwo zgubić.', 'Każde zgłoszenie zapisane, z mailem gościa.' ),
				),
				'przypis' => 'Bot nie zastępuje człowieka w trudnych sprawach — dlatego rozmowę możesz w każdej chwili przejąć i dokończyć osobiście.',
			),
			/*
			 * ⛔ OPINIE: wyłącznie PRAWDZIWE, od zweryfikowanych kupujących (zasada uczciwości,
			 * dyrektywa Omnibus). Puste = sekcja się nie renderuje. Format wpisu — WYŁĄCZNIE
			 * tablica z kluczami (wpis bez klucza 'tresc' jest przez render POMIJANY):
			 * array(
			 *     'tresc'    => 'Treść opinii.',
			 *     'imie'     => 'Imię',       // brak = „Klient"
			 *     'gwiazdki' => 5,            // 1-5; brak = 5
			 *     'tytul'    => 'Tytuł',      // opcjonalny
			 *     'data'     => '25.08.2026', // opcjonalna
			 * )
			 * O pochodzeniu opinii mówi stała NOTA renderowana razem z sekcją
			 * (sklepag_render_opinie) — niczego więcej nie trzeba zmieniać na stronie.
			 * Opinie kupujących idą MECHANIZMEM (tabela sklepag_opinie, moderacja
			 * w panelu) i renderują się PRZED wpisami z tej tablicy — ta tablica zostaje
			 * jako ręczne uzupełnienie.
			 */
			'opinie'       => array(),
			/*
			 * ⛔ GWARANCJA: obietnica zwrotu pieniędzy = zobowiązanie WŁAŚCICIELA.
			 * Treść i włączenie to decyzja WŁAŚCICIELA SKLEPU; puste = sekcja się nie renderuje.
			 */
			'gwarancja'    => '14 dni gwarancji satysfakcji: jeśli bot nie spełni Twoich oczekiwań, napisz do nas — zwracamy pieniądze bez pytań. Zwrot oznacza wygaśnięcie prawa do aktualizacji i wsparcia dla kupionej kopii.',
			'kalkulacja'   => 'Policz po swojemu: 10 pytań dziennie × 3 minuty = około 5 godzin miesięcznie z głowy. Podstaw własne liczby — matematyka jest jawna.',
			'faq'          => array(
				array( 'Czy potrzebuję klucza AI?', 'Nie — bot w pełni działa bez niego, na Twojej bazie pytań i odpowiedzi.' ),
				array( 'Jakie są wymagania techniczne?', 'WordPress 6.0 lub nowszy i PHP 8.1 lub nowszy — standard na dzisiejszych hostingach. Bez dodatkowych usług; tryb AI wymaga tylko Twojego klucza API.' ),
				array( 'Co się dzieje, gdy bot nie zna odpowiedzi?', 'Prosi o maila, zapisuje zgłoszenie i może powiadomić Cię mailem — kontakt nie przepada.' ),
				array( 'Czy bot sam się uczy?', 'Nie — odpowiada wyłącznie z Twojej bazy. To zaleta: zero zmyślonych odpowiedzi o Twojej firmie.' ),
				array( 'Jak wstawię bota na stronę?', 'Instalujesz wtyczkę — bąbelek pojawia się sam; włącznik i teksty w ustawieniach.' ),
				array( 'Co z RODO?', 'Zgłoszenia gości zostają w Twojej bazie. W trybie AI treść pytań idzie do dostawcy API — gotowa wzmianka do Twojej polityki prywatności jest w instrukcji.' ),
			),
			'final'        => 'Zacznij odpowiadać w nocy. Bez nocek.',
		),
		'segregator-maila' => array(
			'etykieta'       => '[ <span class="sklepag-akcent">BOT</span> · PORZĄDEK W POCZCIE ]',
			'zdanie'         => 'Przestań zaczynać dzień od przekopywania skrzynki: reguły układają pocztę do kategorii, a raport pokazuje, ile roboty zeszło z Ciebie.',
			'demo_cta'       => 'Przetestuj bez ryzyka: 30 maili demo w zestawie — raport z liczbami po pierwszym przebiegu, bez podpinania skrzynki.',
			'zgodnosc'       => array( 'DZIAŁA Z: Gmail i skrzynki hostingowe (hasło aplikacji)', 'NIE DZIAŁA Z: Microsoft 365 / Outlook.com (wymagają OAuth)' ),
			'pasek_glowa'    => 'SEGREGATOR · GOTOWY',
			'pasek'          => 'PRÓBKA 30 MAILI W ZESTAWIE',
			'pasek_zywy'     => '',
			'problem_t'      => 'Wszystko na jednej kupie.',
			'problem'        => 'Oferty, faktury, reklamacje i spam w jednym strumieniu. Zanim odpiszesz ważnym, przekopujesz się przez resztę.',
			'mockup_czatu'   => false,
			'przedpo'        => true,
			'funkcje'        => array(
				array( 'Reguły segregacji', 'Nadawca / temat / słowa w treści → kategoria. Kolejność na liście rozstrzyga: pierwsza pasująca wygrywa — zero niejednoznaczności.' ),
				array( 'Kategorie pod Twoją firmę', 'Dowolne nazwy: dodajesz i zmieniasz, jak chcesz — Oferty, Faktury, Reklamacje. Kategorii używanej przez regułę nie skasujesz przypadkiem.' ),
				array( 'Próbki zamiast ryzyka', 'Wbudowane 30 maili demo po polsku + import WŁASNEJ próbki z CSV (od, temat, treść).' ),
				array( 'Raport z liczbami', 'Ile do której kategorii, ile bez dopasowania, % pokrycia; przy próbce z oczekiwaną kategorią — trafność i lista pomyłek.' ),
				array( 'Tryb AI (opcjonalny)', 'Twój klucz: klasyfikacja modelem + przebieg „porównaj reguły vs AI" z raportem różnic.' ),
			),
			'kroki'          => array(
				'Zainstaluj wtyczkę.',
				'Ułóż reguły pod swoją skrzynkę.',
				'Puść przebieg i czytaj raport.',
			),
			'dowody'         => array(
				array( '30 maili demo', 'przebieg testowy od pierwszej minuty, bez podpinania czegokolwiek' ),
				array( '% pokrycia', 'liczony z TWOJEJ próbki — raport pokazuje liczby, nie obietnice' ),
				array( '0 maili na zewnątrz', 'tryb regułowy działa w całości w Twoim WordPressie' ),
			),
			'dla_kogo'       => array( 'Biura i sekretariaty', 'Firmy usługowe z jedną wspólną skrzynką', 'Każdy, kto zaczyna dzień od przekopywania poczty' ),
			'uczciwosc'      => array(
				'Reguły układasz i sprawdzasz na próbce, a potem puszczasz je na PRAWDZIWEJ skrzynce: wtyczka rozkłada pocztę do folderów na serwerze poczty.',
				'<strong>Domyślnie tryb NA SUCHO</strong>: nie rusza ani jednej wiadomości, dopóki sam nie potwierdzisz — a ostatni przebieg cofniesz jednym kliknięciem.',
				'<strong>Czego nie ma, mówimy przed zakupem</strong>: logowanie tylko loginem i hasłem aplikacji, więc Microsoft 365 i Outlook.com NIE zadziałają (tam wymagane jest OAuth).',
				'Na skrzynce klasyfikują reguły, nie AI; bez czytania załączników.',
			),
			/*
			 * FAZY: galeria opowiada w trzech wierszach
			 * probka i przebieg -> reguly -> raport i kontrola. Narracje sklejone z istniejacych
			 * tresci (funkcje, FAQ, alty) — zero nowych obietnic i liczb. Kadry (*-kadr.webp)
			 * sa wyciete ffmpeg-iem z TYCH SAMYCH plikow (crop od podanego y: probka 930,
			 * reguly 280, ustawienia 46; kategorie i raport w calosci). Nagranie przyciete do
			 * obszaru panelu (crop=1100:760:170:40 z 1280x800 — bez menu gospodarza i plakietki
			 * „Plugins"), skalowane do 900 px, plakat = jego klatka 0,2 s; oryginal
			 * segregator-demo.webm zostaje w assets (na stronie nieuzywany).
			 */
			'fazy'           => array(
				array(
					'tytul' => 'Testujesz od razu',
					'opis'  => 'Wbudowane 30 maili demo po polsku: puszczasz przebieg bez podpinania skrzynki i od razu czytasz raport z liczbami.',
					'uklad' => 'nagranie',
					'kafle' => array(
						array(
							'wideo'  => 'segregator-demo-kadr.webm',
							'plakat' => 'segregator-demo-plakat.webp',
							'alt'    => 'Nagranie: kliknięcie Przepuść próbkę i świeży raport — liczby, trafienia per kategoria, każda wiadomość z regułą, która ją złapała',
							'podpis' => 'NAGRANIE Z PANELU — PRZEBIEG PRÓBKI I RAPORT NA ŻYWO',
							'szer'   => 900,
							'wys'    => 622,
						),
						array(
							'plik'      => 'segregator-maila--probka-demo.png',
							'kadr_plik' => 'segregator-maila--probka-demo-kadr.webp',
							'kadr_szer' => 1100,
							'kadr_wys'  => 760,
							'alt'       => 'Ekran Próbka maili: tabela 30 przykładowych wiadomości po polsku z nadawcą, tematem i treścią',
							'podpis'    => '30 MAILI DEMO — TESTUJESZ OD PIERWSZEJ MINUTY',
							'szer'      => 1100,
							'wys'       => 2726,
						),
					),
				),
				array(
					'tytul' => 'Twoje reguły',
					'opis'  => 'Nadawca, temat albo słowa w treści → kategoria; pierwsza pasująca wygrywa. Kategorie nazywasz pod swoją firmę — używanej przez regułę nie skasujesz przypadkiem.',
					'uklad' => 'panel',
					'kafle' => array(
						array(
							'plik'      => 'segregator-maila--reguly.png',
							'kadr_plik' => 'segregator-maila--reguly-kadr.webp',
							'kadr_szer' => 1078,
							'kadr_wys'  => 500,
							'alt'       => 'Lista reguł segregacji: nadawca, temat, słowa w treści, kategoria i kolumny Pasuje/Zgarnia',
							'podpis'    => 'REGUŁY — PIERWSZA PASUJĄCA WYGRYWA',
							'szer'      => 1100,
							'wys'       => 1070,
						),
						array(
							'plik'      => 'segregator-maila--kategorie.png',
							'kadr_plik' => 'segregator-maila--kategorie-kadr.webp',
							'kadr_szer' => 1100,
							'kadr_wys'  => 511,
							'alt'       => 'Lista kategorii poczty: Oferty, Faktury, Reklamacje i własne',
							'podpis'    => 'KATEGORIE POD TWOJĄ FIRMĘ — UŻYWANEJ NIE SKASUJESZ PRZYPADKIEM',
							'szer'      => 1100,
							'wys'       => 511,
						),
					),
				),
				array(
					'tytul' => 'Raport i kontrola',
					'opis'  => 'Ile do której kategorii, ile bez dopasowania, % pokrycia — a przy próbce z oczekiwaną kategorią także trafność i lista pomyłek. Tryb AI włączasz własnym kluczem, z dziennym limitem kosztów.',
					'uklad' => 'panel',
					'kafle' => array(
						array(
							'plik'      => 'segregator-maila--zywy.jpg',
							'kadr_plik' => 'segregator-maila--zywy-kadr.webp',
							'kadr_szer' => 1200,
							'kadr_wys'  => 470,
							'kadr'      => 'raport',
							'alt'       => 'Raport przebiegu Segregatora maila w panelu WordPressa: 30 maili w próbce, pokrycie regułami 80 procent, trafność 100 procent, podział na kategorie',
							'podpis'    => 'PRAWDZIWY RAPORT Z PANELU — LICZBY BEZ RETUSZU',
							'szer'      => 1200,
							'wys'       => 470,
						),
						array(
							'plik'      => 'segregator-maila--ustawienia.png',
							'kadr_plik' => 'segregator-maila--ustawienia-kadr.webp',
							'kadr_szer' => 1078,
							'kadr_wys'  => 500,
							'alt'       => 'Ustawienia Segregatora: tryb AI (hybryda), klucz API, dzienny limit kosztów',
							'podpis'    => 'TRYB AI I STOP-LOSSY — TY TRZYMASZ BUDŻET',
							'szer'      => 1078,
							'wys'       => 719,
						),
					),
				),
			),
			'pakiet'         => array(
				'Plik ZIP wtyczki — instalujesz jak każdą wtyczkę WordPressa, w kilka minut.',
				'Instrukcja krok po kroku z 6 zrzutami ekranu.',
				'Próbka 30 maili demo po polsku — testujesz od pierwszej minuty, bez podpinania skrzynki.',
				'Dokumentacja techniczna z diagramem przepływu (dla Twojego informatyka).',
				'Historia zmian i licencja w paczce.',
			),
			'porownanie'     => array(
				'tytul'   => 'Ręczne sortowanie kontra reguły',
				'kol'     => array( 'Robione ręcznie', 'Z botem' ),
				'wiersze' => array(
					array( 'Każdy ranek zaczyna się od przekopywania skrzynki.', 'Przebieg układa pocztę według Twoich reguł.' ),
					array( 'Przy setnym mailu uwaga siada.', 'Reguła numer 7 działa tak samo za setnym razem.' ),
					array( 'Nie wiesz, ile czasu schodzi na sortowanie.', 'Raport z liczbami po każdym przebiegu.' ),
					array( 'Pomyłka ginie w skrzynce.', 'Tryb na sucho i cofnięcie ostatniego przebiegu.' ),
				),
				'przypis' => 'Reguły układasz raz i poprawiasz, gdy raport pokaże pomyłki — bot nie zgaduje za Ciebie.',
			),
			/* ⛔ OPINIE i GWARANCJA: te same zasady co przy chatbot-live (komentarz wyżej). */
			'opinie'         => array(),
			'gwarancja'      => '14 dni gwarancji satysfakcji: jeśli bot nie spełni Twoich oczekiwań, napisz do nas — zwracamy pieniądze bez pytań. Zwrot oznacza wygaśnięcie prawa do aktualizacji i wsparcia dla kupionej kopii.',
			'dowody_przypis' => 'Liczby z próbki 30 maili demo — Twoją trafność pokaże raport na TWOICH regułach i TWOJEJ poczcie.',
			'faq'            => array(
				array( 'Czy muszę podpinać skrzynkę?', 'Nie musisz — możesz pracować wyłącznie na próbkach CSV. Ale możesz: wtyczka obsługuje prawdziwą skrzynkę i przenosi w niej pocztę do folderów.' ),
				array( 'Jakie są wymagania techniczne?', 'WordPress 6.0 lub nowszy i PHP 8.1 lub nowszy. Praca na próbkach CSV nie potrzebuje nic więcej. Do połączenia z PRAWDZIWĄ skrzynką hosting musi mieć włączoną funkcję stream_socket_client (najtańsze hostingi bywają z nią na bakier) i rozszerzenie OpenSSL do szyfrowania — wbudowana diagnostyka wtyczki sprawdzi oba za Ciebie i powie, o co poprosić hostingodawcę. Tryb AI wymaga tylko Twojego klucza API.' ),
				array( 'Zadziała z Microsoft 365 albo Outlook.com?', 'Nie. Tam wymagane jest logowanie OAuth, którego ta wtyczka nie obsługuje. Gmail i skrzynki hostingowe z hasłem aplikacji: tak.' ),
				array( 'Czy coś mi się pomiesza w poczcie?', 'Domyślny tryb na sucho niczego nie rusza — pokazuje tylko, co by zrobił. Realne przenoszenie wymaga przełączenia i osobnego potwierdzenia, a ostatni przebieg da się cofnąć.' ),
				array( 'Skąd wezmę CSV?', 'Eksport z klienta poczty (Gmail, Thunderbird, Outlook) — instrukcja pokazuje jak, krok po kroku.' ),
				array( 'Co znaczy trafność (accuracy)?', 'Dodajesz do próbki kolumnę z oczekiwaną kategorią — raport liczy % trafień i pokazuje pomyłki.' ),
				array( 'Czy AI jest wymagane?', 'Nie — pełny tryb regułowy działa bez klucza. AI to opcja do porównania wyników.' ),
				array( 'Co z RODO?', 'Próbki zostają w Twojej bazie. W trybie AI treść idzie do dostawcy API — gotowa wzmianka do polityki prywatności w instrukcji.' ),
			),
			'final'          => 'Niech poczta sama się układa.',
		),
	);
	return isset( $tresci[ $slug ] ) ? $tresci[ $slug ] : null;
}

/**
 * Ilustracja produktu do KARTY listy i HERO strony produktu (obraz ma pokazywać, co produkt robi:
 * robot segregujący maile, ktoś odpisujący na czacie). Własne SVG w palecie
 * strony (limonkowy line-art, roboty-bliźniaki) zamiast generycznego stocka;
 * zrzut żywego bota jest na stronie produktu pierwszym kaflem galerii.
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
 * @param array  $zrzut Dane z sklepag_ilustracja_produktu() albo wpis galerii (plik/alt/podpis/szer/wys).
 * @param string $klasa Klasa CSS figure.
 * @param bool   $z_podpisem Czy dodać figcaption.
 * @param bool   $z_paskiem  Czy dodać pasek „okna aplikacji" (tylko dla zrzutów —
 *                           ilustracja nie udaje okna programu).
 * @param bool   $leniwie    Czy `loading="lazy"` (false dla ilustracji kart listy — obraz ma
 *                           wejść razem z kartą w kaskadzie, nie chwilę po niej).
 */
function sklepag_figure_zrzutu( $zrzut, $klasa, $z_podpisem, $z_paskiem = true, $leniwie = true ) {
	// Modyfikator per produkt (kadr okna karty ustawiany w CSS, np. dolna
	// część okna czatu zamiast uciętego nagłówka). Sufiks ZAWSZE na bazowym
	// (pierwszym) członie klasy — przy wieloczłonowym $klasa doklejanie do
	// całości dublowało tokeny i celowało w zły człon.
	$czlony = explode( ' ', $klasa );
	$klasa .= ' ' . $czlony[0] . '--' . sanitize_html_class( pathinfo( $zrzut['plik'], PATHINFO_FILENAME ) );
	$html   = '<figure class="' . esc_attr( $klasa ) . '">';
	if ( $z_paskiem ) {
		// Pasek „okna aplikacji" (3 kropki jak w mockupie czatu): wspólna rama godzi
		// ciemny widget z jasnym panelem wp-admin — inaczej czyta się jak dwa różne języki wizualne.
		$html .= '<span class="sklepag-okno-pasek" aria-hidden="true"><span></span><span></span><span></span></span>';
	}
	$html .= '<img src="' . esc_url( plugins_url( 'assets/produkty/' . $zrzut['plik'], SKLEPAG_PLIK ) ) . '" alt="' . esc_attr( $zrzut['alt'] ) . '" width="' . (int) $zrzut['szer'] . '" height="' . (int) $zrzut['wys'] . '"' . ( $leniwie ? ' loading="lazy"' : '' ) . '>';
	if ( $z_podpisem ) {
		$html .= '<figcaption class="sklepag-mono-etykieta">' . esc_html( $zrzut['podpis'] ) . '</figcaption>';
	}
	$html .= '</figure>';
	return $html;
}

/**
 * Mono-pictogram produktu (bez ikon karty są nierozróżnialne).
 * Inline SVG w limonce — zero CDN/fontów ikon. Fallback: iskra.
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
 * Formularz zakupu (jedyna droga: checkbox zgody + nonce — mechanika bez zmian).
 *
 * @param object $p Produkt (wiersz bazy).
 */
function sklepag_formularz_zakupu( $p ) {
	$id_zgody = 'sklepag-zgoda-' . (int) $p->id;
	$html     = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sklepag-zakup" id="sklepag-kup-formularz">';
	$html    .= '<input type="hidden" name="action" value="sklepag_kup">';
	$html    .= '<input type="hidden" name="produkt_id" value="' . (int) $p->id . '">';
	$html    .= wp_nonce_field( 'sklepag_kup', '_wpnonce', true, false );
	$html    .= '<label class="sklepag-zgoda" for="' . esc_attr( $id_zgody ) . '"><input type="checkbox" id="' . esc_attr( $id_zgody ) . '" name="zgoda" value="1" required data-sklepag-blad="' . esc_attr( SKLEPAG_BLAD_ZGODA_ZAKUP ) . '"> <span>' . esc_html( SKLEPAG_ZGODA_TRESC ) . '</span></label>';
	// Dane do faktury — nieobowiązkowe, nasze pola (0.9.25). Zwinięte pod <details>, żeby osoba prywatna
	// nie widziała pól firmowych; NIP sprawdzany po stronie serwera (suma kontrolna), tu tylko podpowiedź.
	$html .= '<details class="sklepag-faktura"><summary class="sklepag-mono-etykieta">[ KUPUJESZ NA FIRMĘ? DANE DO FAKTURY ]</summary>'
		. '<div class="sklepag-faktura-pola">'
		. '<label><span>Nazwa firmy</span><input type="text" name="faktura_nazwa" maxlength="200" autocomplete="organization" placeholder="np. Firma Sp. z o.o."></label>'
		. '<label><span>NIP</span><input type="text" name="faktura_nip" inputmode="numeric" maxlength="13" autocomplete="off" placeholder="10 cyfr"></label>'
		. '<label class="sklepag-faktura-adres"><span>Adres firmy</span><textarea name="faktura_adres" rows="2" maxlength="200" autocomplete="street-address" placeholder="ul. Przykładowa 1, 00-001 Warszawa"></textarea></label>'
		. '<p class="sklepag-faktura-notka">Pola nieobowiązkowe — wypełnij wszystkie trzy, jeśli chcesz fakturę na firmę. Dane trafią do potwierdzenia zakupu i posłużą wyłącznie do wystawienia faktury; fakturę prześlemy osobno mailem. Podanie NIP-u nie zmienia Twoich praw kupującego.</p>'
		. '</div></details>';
	$html .= '<button type="submit" class="sklepag-kup">Kup teraz (płatność testowa)</button>';
	$html .= '<p class="sklepag-mono-etykieta sklepag-zakup-notka">STRIPE · TRYB TESTOWY · KARTA 4242 4242 4242 4242</p>';
	$html .= '</form>';
	// Panel płatności osadzonej (0.9.25): `platnosc.js` odsłania go po utworzeniu sesji i montuje w nim
	// elementy Stripe (e-mail, karta) — formularz karty jest w ramce Stripe (PCI), my dajemy tylko ramy i kolory.
	// Bez JS/klucza publikowalnego panel zostaje ukryty, a formularz wyżej idzie na stronę hostowaną.
	$html .= '<section class="sklepag-platnosc" id="sklepag-platnosc" hidden aria-live="polite">'
		. '<p class="sklepag-mono-etykieta">[ PŁATNOŚĆ · ' . esc_html( $p->nazwa ) . ' · ' . esc_html( number_format( $p->cena_grosze / 100, 2, ',', ' ' ) . ' ' . $p->waluta ) . ' ]</p>'
		. '<div id="sklepag-platnosc-kontakt"></div>'
		. '<div id="sklepag-platnosc-karta"></div>'
		. '<p class="sklepag-platnosc-blad" id="sklepag-platnosc-blad" role="alert" hidden></p>'
		. '<button type="button" class="sklepag-kup" id="sklepag-platnosc-zaplac" disabled>Zapłać (płatność testowa)</button>'
		. '<button type="button" class="sklepag-platnosc-wroc" id="sklepag-platnosc-wroc">Wróć</button>'
		. '<p class="sklepag-platnosc-notka">Dane karty trafiają bezpośrednio do Stripe (ramka Stripe na tej stronie). Po zapłacie wrócisz na stronę potwierdzenia, a link do pobrania przyjdzie mailem.</p>'
		. '</section>';
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
 * Wizualizacja PRZED/PO segregacji (nasza przewaga).
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
 * Tytuł hero z akcentem: ostatnie słowo pisane WIELKIMI literami (np. „LIVE") dostaje limonkę —
 * jak „AI" w logo gospodarza. Nazwa bez takiego słowa (Segregator maila) zostaje jednolita.
 *
 * @param string $nazwa Nazwa produktu z bazy.
 */
function sklepag_tytul_z_akcentem( $nazwa ) {
	if ( preg_match( '/^(.*\s)(\p{Lu}{2,})$/u', $nazwa, $m ) ) {
		return esc_html( $m[1] ) . '<span class="sklepag-akcent">' . esc_html( $m[2] ) . '</span>';
	}
	return esc_html( $nazwa );
}

/**
 * Galeria „CO JEST W ŚRODKU" w FAZACH: każda faza to wiersz
 * z narracją po lewej (numer, tytuł, jedno zdanie z istniejących treści) i kaflami-oknami
 * po prawej. Biały zrzut wp-admin siedzi w ciemnej ramie okna jako KADR (osobny, mały plik),
 * a pełny ekran otwiera się w lightboxie — całe białe ekrany na czarnej stronie były plamami,
 * a ich gęsty interfejs w rozmiarze kafla był nieczytelny.
 *
 * @param array $fazy      Lista faz: tytul, opis, uklad, kafle (numer fazy nadaje jej pozycja
 *                         na liście — dane nie trzymają własnej numeracji, więc nie mogą się
 *                         rozjechać przy zmianie kolejności).
 * @param int   $nr_sekcji Numer sekcji na stronie — fazy numerują się „04.1, 04.2, 04.3"
 *                         (przypięte do sekcji, odróżnialne od kroków 01/02/03 sekcji obok).
 */
function sklepag_render_fazy( $fazy, $nr_sekcji ) {
	$html = '<div class="sklepag-fazy">';
	foreach ( array_values( $fazy ) as $i => $f ) {
		$numer = sprintf( '%02d.%d', (int) $nr_sekcji, $i + 1 );
		// `sklepag-obserwuj`: faza wjeżdża, gdy SAMA wchodzi w ekran (front-ruch.js), nie razem
		// z górą długiej sekcji — inaczej dolne fazy odsłaniałyby się poza kadrem.
		$html .= '<div class="sklepag-faza sklepag-obserwuj sklepag-faza--' . sanitize_html_class( $f['uklad'] ) . '">';
		$html .= '<div class="sklepag-faza-glowa"><p class="sklepag-faza-numer" aria-hidden="true">' . esc_html( $numer ) . '</p>';
		$html .= '<h2 class="sklepag-faza-tytul">' . esc_html( $f['tytul'] ) . '</h2><p class="sklepag-faza-opis">' . esc_html( $f['opis'] ) . '</p></div>';
		$html .= '<div class="sklepag-faza-kafle">';
		foreach ( $f['kafle'] as $k ) {
			$html .= sklepag_kafel_fazy( $k );
		}
		$html .= '</div></div>';
	}
	return $html . '</div>';
}

/**
 * Jeden kafel fazy: nagranie (plakat → wideo), zrzut (kadr w kaflu, pełny ekran w lightboxie)
 * albo karta toku (mono-lista kroków — dla fazy z jednym zrzutem, zamiast sierotki).
 *
 * @param array $k Dane kafla (wideo+plakat | plik [+kadr_plik] | tok).
 */
function sklepag_kafel_fazy( $k ) {
	$pasek = '<span class="sklepag-okno-pasek" aria-hidden="true"><span></span><span></span><span></span></span>';
	if ( ! empty( $k['tok'] ) ) {
		$html = '<div class="sklepag-kafel sklepag-kafel--tok">' . $pasek . '<ol class="sklepag-tok">';
		foreach ( $k['tok'] as $i => $krok ) {
			$html .= '<li><span class="sklepag-tok-numer" aria-hidden="true">' . sprintf( '%02d', $i + 1 ) . '</span><span>' . esc_html( $krok ) . '</span></li>';
		}
		return $html . '</ol><p class="sklepag-mono-etykieta">' . esc_html( $k['podpis'] ) . '</p></div>';
	}
	if ( ! empty( $k['wideo'] ) ) {
		$zrodlo = esc_url( plugins_url( 'assets/w-srodku/' . $k['wideo'], SKLEPAG_PLIK ) );
		$plakat = esc_url( plugins_url( 'assets/w-srodku/' . $k['plakat'], SKLEPAG_PLIK ) );
		// Plakat = stop-klatka nagrania: obraz jest od pierwszej klatki, a gaśnie dopiero, gdy
		// nagranie naprawdę gra (front-ruch.js). Przy „ogranicz ruch" zostaje sam plakat.
		$html  = '<figure class="sklepag-kafel sklepag-kafel--wideo">' . $pasek . '<div class="sklepag-kafel-kadr">';
		$html .= '<video src="' . $zrodlo . '" poster="' . $plakat . '" width="' . (int) $k['szer'] . '" height="' . (int) $k['wys'] . '" autoplay muted loop playsinline preload="auto" aria-label="' . esc_attr( $k['alt'] ) . '"></video>';
		$html .= '<img class="sklepag-kafel-plakat" src="' . $plakat . '" alt="" aria-hidden="true" width="' . (int) $k['szer'] . '" height="' . (int) $k['wys'] . '">';
		// Prawdziwy przycisk (dostępny z klawiatury), a nie pseudo-element. Od 0.9.19 przy
		// DZIAŁAJĄCYM JS jest widoczny ZAWSZE — `front-ruch.js` zdejmuje `hidden` przy starcie,
		// bo ruch trwający dłużej niż 5 s musi dać się zatrzymać, a element `display:none`
		// wypada z kolejności Tab. Atrybut `hidden` w markupie jest zapasem na BRAK JS:
		// przycisk, którego nie ma czym obsłużyć, byłby gorszy niż jego brak — wtedy zostaje
		// samo `autoplay`. Ta sama zasada opisana przy regule w `assets/front.css`.
		$html .= '<button type="button" class="sklepag-kafel-odtworz" hidden><span aria-hidden="true">▶</span> Odtwórz nagranie</button>';
		return $html . '</div><figcaption class="sklepag-mono-etykieta">' . esc_html( $k['podpis'] ) . '</figcaption></figure>';
	}
	$pelny = esc_url( plugins_url( 'assets/w-srodku/' . $k['plik'], SKLEPAG_PLIK ) );
	$kadr  = ! empty( $k['kadr_plik'] ) ? esc_url( plugins_url( 'assets/w-srodku/' . $k['kadr_plik'], SKLEPAG_PLIK ) ) : $pelny;
	$szer  = ! empty( $k['kadr_plik'] ) ? (int) $k['kadr_szer'] : (int) $k['szer'];
	$wys   = ! empty( $k['kadr_plik'] ) ? (int) $k['kadr_wys'] : (int) $k['wys'];
	$klasa = 'sklepag-kafel' . ( ! empty( $k['kadr'] ) ? ' sklepag-kafel--' . sanitize_html_class( $k['kadr'] ) : '' );
	$html  = '<figure class="' . esc_attr( $klasa ) . '">' . $pasek;
	// Kafel pokazuje KADR (mały plik), odnośnik prowadzi do PEŁNEGO ekranu — ładowanego dopiero
	// po kliknięciu (lightbox), nie razem ze stroną.
	$html .= '<a class="sklepag-lightbox" href="' . $pelny . '" data-podpis="' . esc_attr( $k['podpis'] ) . '" aria-label="' . esc_attr( 'Powiększ: ' . $k['podpis'] ) . '">';
	$html .= '<div class="sklepag-kafel-kadr"><img src="' . $kadr . '" alt="' . esc_attr( $k['alt'] ) . '" width="' . $szer . '" height="' . $wys . '" loading="lazy"></div></a>';
	return $html . '<figcaption class="sklepag-mono-etykieta">' . esc_html( $k['podpis'] ) . '</figcaption></figure>';
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

	// Etykiety sekcji numerowane kolejno `[ 01 · … ]` jak na liście.
	// Numer rośnie tylko dla sekcji, które naprawdę się renderują (opinie i gwarancja bywają puste).
	$nr       = 0;
	$etykieta = static function ( $tekst ) use ( &$nr ) {
		++$nr;
		return '<p class="sklepag-mono-etykieta">[ <span class="sklepag-akcent">' . sprintf( '%02d', $nr ) . '</span> · ' . $tekst . ' ]</p>';
	};

	$html = '<div class="sklepag-wyspa sklepag-produkt">';
	// Tło „blueprint z sygnałami" jak na liście (ta sama materia). Strona jest ~3× dłuższa od
	// listy, więc pingów jest 14 — dodatkowe węzły niżej (CSS .sklepag-pingi--produkt).
	$html .= '<div class="sklepag-tlo" aria-hidden="true"></div>';
	$html .= '<div class="sklepag-pingi sklepag-pingi--produkt" aria-hidden="true">' . str_repeat( '<i></i>', 14 ) . '</div>';
	$html .= '<nav class="sklepag-okruszki" aria-label="Okruszki"><a href="' . esc_url( get_permalink( (int) get_option( 'sklepag_strona_sklep' ) ) ) . '">Sklep</a> <span aria-hidden="true">/</span> <span>' . esc_html( $p->nazwa ) . '</span></nav>';

	// HERO — dwie kolumny: tekst + ILUSTRACJA BRANDOWA (w hero ma być branding,
	// wnętrze botów pokazujemy niżej). Żywe zrzuty wnętrza są w galerii
	// [ CO JEST W ŚRODKU ]. Kierunki wejść z motywu gospodarza ustawia CSS.
	$zrzut_hero = sklepag_ilustracja_produktu( $slug );
	$html      .= '<div class="sklepag-hero-uklad">';
	$html      .= '<header class="sklepag-hero">';
	$html      .= '<p class="sklepag-mono-etykieta">' . ( $t ? wp_kses( $t['etykieta'], array( 'span' => array( 'class' => array() ) ) ) : '[ <span class="sklepag-akcent">BOT</span> · TESTOWY ]' ) . '</p>';
	$html      .= '<h1 class="sklepag-hero-tytul">' . sklepag_tytul_z_akcentem( $p->nazwa ) . '</h1>';
	$html      .= '<p class="sklepag-lead">' . esc_html( $t ? $t['zdanie'] : $p->opis_krotki ) . '</p>';
	if ( $t ) {
		$glowa = explode( ' · ', $t['pasek_glowa'], 2 );
		$html .= '<p class="sklepag-pasek-statusu"><span class="sklepag-kropka" aria-hidden="true"></span>[ <span class="sklepag-akcent">' . esc_html( $glowa[0] ) . '</span> · ' . esc_html( isset( $glowa[1] ) ? $glowa[1] : '' ) . ' ] ' . esc_html( $t['pasek'] . $t['pasek_zywy'] ) . '</p>';
	}
	$html .= $cena;
	$html .= '<p><a class="sklepag-kup sklepag-kup-kotwica" href="#sklepag-zakup">Kup teraz (płatność testowa)</a></p>';
	if ( $t && ! empty( $t['gwarancja'] ) ) {
		$html .= '<p class="sklepag-trigger">✔ 14 dni na zwrot — bez pytań</p>';
	}
	if ( $t && ! empty( $t['demo_cta'] ) ) {
		$html .= '<p class="sklepag-demo-cta">▶ ' . esc_html( $t['demo_cta'] ) . '</p>';
	}
	if ( $t && ! empty( $t['zgodnosc'] ) ) {
		$html .= '<ul class="sklepag-zgodnosc">';
		foreach ( $t['zgodnosc'] as $z ) {
			$html .= '<li>' . esc_html( $z ) . '</li>';
		}
		$html .= '</ul>';
	}
	$html .= sklepag_baner_testowy();
	$html .= '</header>';
	// Wizual hero = PANEL NA ŻYWO tego produktu (statyczna ilustracja w hero była za słaba) —
	// ta sama sygnatura co hero listy; ilustracja brandowa zostaje na karcie listy. Produkt spoza
	// dwójki (bez panelu) dostaje ilustrację, jeśli ją ma.
	$panel = sklepag_panel_nocnej_zmiany( $slug );
	if ( '' !== $panel ) {
		$html .= '<div class="sklepag-hero-wizual sklepag-hero-wizual--panel">' . $panel . '</div>';
	} elseif ( $zrzut_hero ) {
		$html .= '<div class="sklepag-hero-wizual">' . sklepag_figure_zrzutu( $zrzut_hero, 'sklepag-zrzut sklepag-hero-zrzut', false, false ) . '</div>';
	}
	$html .= '</div>';

	if ( $t ) {
		// PROBLEM — tekst w torze czytania.
		$html .= '<section class="sklepag-sekcja">' . $etykieta( 'PROBLEM' );
		$html .= '<h2 class="sklepag-sekcja-tytul">' . esc_html( $t['problem_t'] ) . '</h2>';
		$html .= '<p class="sklepag-sekcja-tekst">' . esc_html( $t['problem'] ) . '</p></section>';

		// CO DOSTAJESZ — karty funkcji na pełnej szerokości (+ mockup czatu przy chatbocie).
		$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'CO DOSTAJESZ' );
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

		// JAK DZIAŁA — 3 równe karty z torem sygnału 01 → 02 → 03 (jak „od zakupu do działania"
		// na liście); numeracja = realna sekwencja.
		$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'JAK DZIAŁA' ) . '<ol class="sklepag-kroki sklepag-kroki--siatka">';
		foreach ( $t['kroki'] as $k ) {
			$html .= '<li><span class="sklepag-krok-tekst">' . esc_html( $k ) . '</span></li>';
		}
		$html .= '</ol></section>';

		// CO JEST W ŚRODKU — galeria w fazach (oba produkty; dawna galeria-siatka wypadła razem
		// z ostatnim produktem, który jej używał).
		if ( ! empty( $t['fazy'] ) ) {
			$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'CO JEST W ŚRODKU' ) . sklepag_render_fazy( $t['fazy'], $nr ) . '</section>';
		}

		// W PACZCE — policzalna zawartość zipa (fakty z paczki, nie obietnice), 2 kolumny.
		if ( ! empty( $t['pakiet'] ) ) {
			$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'W PACZCE' ) . '<ul class="sklepag-pakiet sklepag-pakiet--siatka">';
			foreach ( $t['pakiet'] as $poz ) {
				$html .= '<li>' . esc_html( $poz ) . '</li>';
			}
			$html .= '</ul></section>';
		}

		// ILE CZASU ODZYSKUJESZ (dedykowane miejsce): 3 karty-dowody.
		$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'ILE CZASU ODZYSKUJESZ' );
		$html .= '<div class="sklepag-dowody">';
		foreach ( $t['dowody'] as $d ) {
			$html .= '<div class="sklepag-dowod"><p class="sklepag-dowod-liczba">' . esc_html( $d[0] ) . '</p><p class="sklepag-dowod-podpis">' . esc_html( $d[1] ) . '</p></div>';
		}
		$html .= '</div>';
		if ( ! empty( $t['kalkulacja'] ) ) {
			$html .= '<p class="sklepag-przypis">' . esc_html( $t['kalkulacja'] ) . '</p>';
		}
		if ( ! empty( $t['dowody_przypis'] ) ) {
			$html .= '<p class="sklepag-przypis">' . esc_html( $t['dowody_przypis'] ) . '</p>';
		}
		if ( $t['przedpo'] ) {
			$html .= sklepag_przed_po();
		}
		$html .= '</section>';

		// RĘCZNIE KONTRA BOT — porównanie jakościowe (bez wymyślonych stawek cudzej pracy).
		if ( ! empty( $t['porownanie'] ) ) {
			$por   = $t['porownanie'];
			$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'RĘCZNIE KONTRA BOT' );
			$html .= '<h2 class="sklepag-sekcja-tytul">' . esc_html( $por['tytul'] ) . '</h2>';
			$html .= '<div class="sklepag-vs"><div class="sklepag-vs-naglowek">' . esc_html( $por['kol'][0] ) . '</div><div class="sklepag-vs-naglowek sklepag-vs-bot">' . esc_html( $por['kol'][1] ) . '</div>';
			foreach ( $por['wiersze'] as $w ) {
				$html .= '<div class="sklepag-vs-lewa" data-kol="' . esc_attr( $por['kol'][0] ) . '">' . esc_html( $w[0] ) . '</div><div class="sklepag-vs-prawa" data-kol="' . esc_attr( $por['kol'][1] ) . '">' . esc_html( $w[1] ) . '</div>';
			}
			$html .= '</div>';
			$html .= '<p class="sklepag-vs-przypis">' . esc_html( $por['przypis'] ) . '</p></section>';
		}

		// DLA KOGO — 3 równe karty.
		$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'DLA KOGO' ) . '<ul class="sklepag-dla-kogo sklepag-dla-kogo--karty">';
		foreach ( $t['dla_kogo'] as $dk ) {
			$html .= '<li>' . esc_html( $dk ) . '</li>';
		}
		$html .= '</ul></section>';

		// OPINIE KLIENTÓW — wyłącznie PRAWDZIWE, od zweryfikowanych kupujących
		// (zasada uczciwości, dyrektywa Omnibus). Najpierw PRZYJĘTE z mechanizmu (
		// tabela sklepag_opinie), po nich ewentualne wpisy ręczne z tej tablicy.
		// Nic do pokazania = sekcji nie ma (i numer sekcji nie rośnie).
		$html .= sklepag_render_opinie( array_merge( sklepag_opinie_z_bazy( $slug ), is_array( $t['opinie'] ) ? $t['opinie'] : array() ), $etykieta );

		// UCZCIWOŚĆ (zamiast fejk-opinii — Omnibus). Punkty, nie ściana
		// (badania: 53% kupujących samo szuka ograniczeń — forma ma być skanowalna).
		$html .= '<section class="sklepag-sekcja sklepag-uczciwosc">' . $etykieta( 'CO DOKŁADNIE DOSTAJESZ — I CZEGO NIE MA' );
		if ( is_array( $t['uczciwosc'] ) ) {
			$html .= '<ul class="sklepag-uczciwosc-punkty">';
			foreach ( $t['uczciwosc'] as $u ) {
				$html .= '<li>' . wp_kses( $u, array( 'strong' => array() ) ) . '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p class="sklepag-sekcja-tekst">' . esc_html( $t['uczciwosc'] ) . '</p>';
		}
		$html .= '</section>';
	} elseif ( '' !== trim( (string) $p->opis_pelny ) ) {
		// Produkt spoza dwójki: opis pełny z bazy, render jako tekst (XSS).
		$html .= '<section class="sklepag-sekcja">' . $etykieta( 'O PRODUKCIE' );
		$html .= '<p class="sklepag-sekcja-tekst">' . nl2br( esc_html( $p->opis_pelny ) ) . '</p></section>';
	}

	if ( ! $t ) {
		// OPINIE dla produktu SPOZA dwójki: zalążek powstaje przy każdym zakupie,
		// więc przyjęte opinie muszą się pokazać także na stronie generycznej. Dwójka
		// z bogatą treścią ma tę sekcję wyżej, w ustalonym porządku sekcji strony —
		// dlatego dwa wywołania zamiast wspólnego (kolejność sekcji to decyzja projektu).
		$html .= sklepag_render_opinie( sklepag_opinie_z_bazy( $slug ), $etykieta );
	}

	// CENA + ZAKUP.
	// Kotwice „Kup teraz" (hero, finał, pigułka) celują w SEKCJĘ (id), nie w formularz: po skoku cena
	// zostaje w kadrze pod sticky nagłówkiem gospodarza, a ramka formularza błyska (CSS :target).
	$html .= '<section class="sklepag-sekcja sklepag-sekcja-zakup" id="sklepag-zakup">' . $etykieta( 'CENA' );
	// 0.9.28: układ dwukolumnowy — pudło zakupu po lewej BEZ ZMIAN (kotwica, formularz,
	// selektory platnosc.js nietknięte), po prawej panel „W PACZCE" zamiast pustki
	// (zgłoszenie właściciela 31.08). Liczby panelu idą ze STAŁYCH wtyczki — dokument
	// zawsze równy kodowi (ta sama zasada co sekcja PYTANIA na liście).
	$html .= '<div class="sklepag-zakup-uklad"><div class="sklepag-zakup-glowna">';
	$html .= $cena;
	if ( $t && ! empty( $t['gwarancja'] ) ) {
		$html .= '<p class="sklepag-trigger">✔ 14 dni gwarancji satysfakcji — zwrot bez pytań</p>';
	}
	if ( $t && ! empty( $t['zgodnosc'] ) ) {
		$html .= '<p class="sklepag-przypis">' . esc_html( implode( ' · ', $t['zgodnosc'] ) ) . '</p>';
	}
	$html .= sklepag_formularz_zakupu( $p );
	// Panel niesie WYŁĄCZNIE to, czego nie ma nigdzie indziej w kadrze ani w sekcji
	// „W PACZCE" wyżej (runda 2: panel powtarzał jej nazwę i dwa punkty) — przebieg
	// zakupu i prawa kupionej kopii, liczby ze stałych wtyczki.
	$html .= '</div><aside class="sklepag-zakup-aside" aria-label="Co się dzieje po zakupie">';
	$html .= '<p class="sklepag-mono-etykieta">[ PO ZAKUPIE ]</p><ul class="sklepag-zakup-aside-lista">';
	$html .= '<li>płacisz kartą przez Stripe — bez zakładania konta</li>';
	$html .= '<li>mail z linkiem pobrania zaraz po płatności — link ważny ' . (int) SKLEPAG_LINK_GODZIN . ' h, do ' . (int) SKLEPAG_LIMIT_POBRAN . ' pobrań</li>';
	if ( $t && ! empty( $t['gwarancja'] ) ) {
		// NIE powtarzamy zdania o 14 dniach — stoi 30 cm w lewo (p.sklepag-trigger).
		$html .= '<li>aktualizacje i wsparcie dla kupionej kopii</li>';
	}
	$html .= '</ul></aside></div></section>';

	// GWARANCJA — obietnica zwrotu = zobowiązanie finansowe; treść ustala właściciel sklepu.
	if ( $t && ! empty( $t['gwarancja'] ) ) {
		$html .= '<section class="sklepag-sekcja sklepag-gwarancja">' . $etykieta( 'GWARANCJA' );
		$html .= '<p class="sklepag-sekcja-tekst">' . esc_html( $t['gwarancja'] ) . '</p></section>';
	}

	// FAQ — siatka 2 kolumn, każdy blok pytanie+odpowiedź z górnym hairline'em (jak na liście).
	if ( $t && ! empty( $t['faq'] ) ) {
		$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna">' . $etykieta( 'FAQ' ) . '<dl class="sklepag-faq sklepag-faq--siatka">';
		foreach ( $t['faq'] as $f ) {
			$html .= '<div class="sklepag-faq-blok"><dt>' . esc_html( $f[0] ) . '</dt><dd>' . esc_html( $f[1] ) . '</dd></div>';
		}
		$html .= '</dl></section>';
	}

	// FINALNY CTA — baner-panel z duetem maskotek (ta sama para tekst + wizual co na liście).
	$html .= '<section class="sklepag-sekcja sklepag-sekcja--pelna sklepag-final sklepag-final--baner">';
	$html .= '<div class="sklepag-final-tresc">';
	$html .= '<h2 class="sklepag-sekcja-tytul">' . esc_html( $t ? $t['final'] : 'Gotowy na start?' ) . '</h2>';
	$html .= '<p class="sklepag-final-przyciski"><a class="sklepag-kup sklepag-kup-kotwica" href="#sklepag-zakup">Kup teraz (płatność testowa)</a></p>';
	// Podpis duetu = TEN produkt (na stronie ChatBota podpis listy z nazwą Segregatora mylił).
	$podpis_duetu = '<p class="sklepag-mono-etykieta sklepag-final-duet-podpis">[ <span class="sklepag-akcent">' . esc_html( $p->nazwa ) . '</span>' . ( $t && ! empty( $t['gwarancja'] ) ? ' · 14 dni na zwrot' : '' ) . ' ]</p>';
	$html        .= '</div>' . sklepag_duet_maskotek( $podpis_duetu ) . '</section>';

	// Sticky pasek CTA (badanie Baymard: przycisk z marginesem, NIE pelna szerokosc).
	$html .= '<div class="sklepag-sticky" hidden><span class="sklepag-sticky-cena">' . esc_html( number_format( $p->cena_grosze / 100, 0, ',', ' ' ) ) . ' ' . esc_html( $p->waluta ) . '</span><a class="sklepag-kup sklepag-kup-kotwica" href="#sklepag-zakup">Kup teraz</a></div>';
	$html .= '</div>';
	return $html;
}
