<?php
/**
 * Wpięcie w narzędzia RODO WordPressa: eksporter i kasownik danych osobowych.
 *
 * WordPress ma wbudowany kreator (Narzędzia → Eksport/Usuwanie danych osobowych),
 * który obsługuje wniosek osoby, weryfikację mailem, paczkę ZIP i dziennik żądań.
 * Wtyczka, która trzyma maile gości i treść ich rozmów, a do tego kreatora się nie
 * podłącza, zostawia właściciela z obowiązkiem i bez narzędzia — przy produkcie
 * sprzedawanym z sekcją o RODO to niespójność między deklaracją a wykonaniem.
 *
 * KLUCZEM JEST ADRES E-MAIL, i to wyznacza granicę tego, co da się wydać i skasować:
 * zgłoszenia mają e-mail wprost, a historia rozmów NIE — rozmowa jest wiązana
 * z osobą wyłącznie przez sesję, w której ta osoba zostawiła zgłoszenie. Rozmowa
 * kogoś, kto nigdy nie podał maila, jest anonimowa i nie ma jak jej przypisać do
 * wniosku. Mówimy o tym wprost w komunikacie kreatora, zamiast udawać komplet.
 *
 * @package chatbot-live
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ile wierszy na jedną stronę kreatora. Kreator woła nas w pętli, aż `done`. */
const CHATL_RODO_PORCJA = 100;

/**
 * Nazwa opcji z listą sesji, o których WIEMY, że były współdzielone.
 *
 * ⚠️ Po co osobny ślad, skoro współdzielenie widać w tabeli zgłoszeń: bo kasownik
 * **niszczy ten dowód w tej samej operacji**. Sekwencja, która to pokazuje —
 * i która jest normalnym trybem pracy kreatora, bo wnioski przychodzą jeden po
 * drugim, często w odstępie dni:
 *
 * 1. w sesji S zgłoszenia zostawiają osoby A i B (dwa różne adresy),
 * 2. A składa wniosek o usunięcie: zgłoszenie A znika, rozmowa S zostaje
 *    zachowana — poprawnie, bo w chwili sprawdzania zgłoszenie B jeszcze było,
 * 3. B składa **swój** wniosek później. W tabeli nie ma już żadnego obcego
 *    adresu dla sesji S, więc bez tego śladu S wyglądałaby na wyłączną własność B,
 * 4. eksport wydałby B **całą rozmowę, razem z wiadomościami osoby A**.
 *
 * Czyli ten sam błąd, tyle że rozłożony w czasie. Ślad zostaje zapisany, ZANIM
 * kasownik cokolwiek usunie, i przeżywa skasowanie jednego z dwóch zgłoszeń.
 * Nie jest daną osobową: to sam identyfikator sesji, bez adresu i bez treści.
 *
 * ⚠️ Granica nazwana wprost: zapis idzie przez
 * `get_option` → `update_option`, a to NIE jest operacja atomowa. Gdyby dwa
 * wnioski o usunięcie były przetwarzane dokładnie równocześnie, jeden zapis
 * mógłby nadpisać ślad zapisany przez drugi i ochrona wróciłaby do stanu
 * „tylko dla wniosków równoczesnych". Zostawiamy tak świadomie: kreator RODO
 * jest ekranem administracyjnym obsługiwanym po kolei, a zamiana tego na
 * blokadę albo osobną tabelę kosztuje migrację schematu przy ryzyku, którego
 * nie da się wywołać normalną pracą z panelem. Gdyby ryzyko przestało być
 * teoretyczne (kilku administratorów obsługujących wnioski hurtowo), właściwym
 * krokiem jest tabela z kluczem UNIQUE na sesji, a nie kolejna warstwa nad opcją.
 */
const CHATL_RODO_OPCJA_WSPOLNE = 'chatl_rodo_sesje_wspolne';

/**
 * Sesje zapamiętane jako współdzielone.
 *
 * @return array
 */
function chatl_rodo_slad_wspolnych() {
	$lista = get_option( CHATL_RODO_OPCJA_WSPOLNE, array() );
	return is_array( $lista ) ? array_values( array_filter( array_map( 'strval', $lista ) ) ) : array();
}

/**
 * Dopisanie sesji do śladu współdzielonych. Wołane PRZED kasowaniem zgłoszeń.
 *
 * @param array $sesje Identyfikatory sesji.
 * @return bool True, gdy po operacji ślad w bazie zawiera to, co miał (także gdy
 *              nie było czego dopisywać). False = baza odrzuciła zapis — kasownik
 *              ma się wtedy ZATRZYMAĆ, bo bez śladu dowód współdzielenia przepada
 *              razem ze skasowanym zgłoszeniem i nie da się go już odtworzyć.
 */
function chatl_rodo_zapamietaj_wspolne( $sesje ) {
	global $wpdb;
	$sesje = array_values( array_filter( array_map( 'strval', (array) $sesje ) ) );
	if ( ! $sesje ) {
		return true;
	}
	$lista = array_values( array_unique( array_merge( chatl_rodo_slad_wspolnych(), $sesje ) ) );

	/*
	 * Przycinanie przy zapisie, a nie w cronie: rodo.php ładuje się wyłącznie
	 * w panelu (`is_admin()`), więc zadanie crona i tak by tej funkcji nie
	 * zobaczyło. Zapis zdarza się rzadko — tylko przy wniosku o usunięcie
	 * dotyczącym sesji współdzielonej — więc jeden dodatkowy SELECT jest tani.
	 *
	 * Sesja, po której nie została ANI JEDNA wiadomość (retencja skasowała
	 * rozmowę po CHATL_RETENCJA_DNI dniach), nie ma już czego chronić i wypada
	 * z listy. Dzięki temu opcja nie rośnie w nieskończoność.
	 */
	$tr      = $wpdb->prefix . 'chatl_rozmowy';
	$miejsca = implode( ', ', array_fill( 0, count( $lista ), '%s' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
	$zywe = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT sesja FROM {$tr} WHERE sesja IN ({$miejsca})", $lista ) );
	$zywe = array_values( array_filter( (array) $zywe ) );

	if ( ! chatl_zapisz_opcje( CHATL_RODO_OPCJA_WSPOLNE, $zywe, false ) ) {
		chatl_log_bledu_bazy( 'chatl_rodo_zapamietaj_wspolne', 'ślad sesji współdzielonych (wniosek RODO)' );
		return false;
	}
	return true;
}

/**
 * Sesje powiązane z adresem — Z PODZIAŁEM na wyłączne i współdzielone.
 *
 * ⚠️ TU JEST SEDNO CAŁEGO MODUŁU. Rozmowę wiążemy z osobą przez sesję, w której
 * zostawiła zgłoszenie — ale **sesja nie jest własnością jednego adresu**. Gość
 * może w jednej sesji przeglądarki zostawić dwa różne adresy (rozmyślił się,
 * podał służbowy zamiast prywatnego), a z komputera w recepcji czy w bibliotece
 * pisze kolejno kilka osób. Wtedy JEDNA rozmowa zawiera wiadomości DWÓCH osób,
 * nierozróżnialne co do wiersza.
 *
 * Traktowanie takiej sesji jako „danych wnioskodawcy" daje dwie awarie, obie
 * poważne przy RODO i obie po cichu:
 * - **eksport** wydałby wnioskodawcy treść rozmowy drugiej osoby (art. 15 ust. 4
 *   wprost mówi, że prawo do kopii nie może naruszać praw innych osób),
 * - **kasowanie** usunęłoby dane drugiej osoby na wniosek osoby trzeciej.
 *
 * Dlatego sesji WSPÓŁDZIELONEJ nie ruszamy w żadną stronę i mówimy o tym wprost,
 * zamiast zgadywać, który wiersz rozmowy należy do kogo. Zgadywanie po czasie
 * („wiadomości przed zgłoszeniem są tej osoby") wygląda mądrze i jest fałszem:
 * w rozmowie z dwoma zgłoszeniami nie ma żadnego znacznika autora.
 *
 * @param string $email Adres.
 * @return array array( wlasne, wspoldzielone ) — listy identyfikatorów sesji.
 */
function chatl_rodo_sesje_maila( $email ) {
	global $wpdb;
	$t    = $wpdb->prefix . 'chatl_zgloszenia';
	$obce = array();

	/*
	 * Wyciszenie obejmuje OBA zapytania i kończy się przed returnem — listy
	 * miejsc budujemy z array_fill(), więc phpcs nie ma jak policzyć podstawień
	 * i ostrzega na wyrost; wszystkie wartości przechodzą przez prepare() co do
	 * jednej. Poprawność pilnuje strażnik (sekcje C i D testu RODO).
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
	$sesje = $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT sesja FROM {$t} WHERE email = %s AND sesja <> ''", $email )
	);
	$sesje = array_values( array_filter( (array) $sesje ) );

	if ( $sesje ) {
		// Które z TYCH sesji mają w zgłoszeniach także INNY adres.
		$miejsca = implode( ', ', array_fill( 0, count( $sesje ), '%s' ) );
		$obce    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT sesja FROM {$t} WHERE sesja IN ({$miejsca}) AND email <> %s",
				array_merge( $sesje, array( $email ) )
			)
		);
		$obce    = array_values( array_filter( (array) $obce ) );
	}
	// phpcs:enable

	/*
	 * Sesja raz uznana za współdzieloną zostaje nią NA ZAWSZE — także wtedy, gdy
	 * drugie zgłoszenie zniknęło już z tabeli, bo jego właściciel złożył własny
	 * wniosek o usunięcie. Bez tego kroku ochrona działałaby wyłącznie dla wniosków
	 * rozpatrywanych RÓWNOCZEŚNIE, a kreator WordPressa obsługuje je po kolei,
	 * często w odstępie dni (patrz komentarz przy CHATL_RODO_OPCJA_WSPOLNE).
	 */
	$obce = array_values( array_unique( array_merge( $obce, array_intersect( $sesje, chatl_rodo_slad_wspolnych() ) ) ) );

	return array(
		'wlasne'        => array_values( array_diff( $sesje, $obce ) ),
		'wspoldzielone' => $obce,
	);
}

/**
 * Eksporter: zgłoszenia i rozmowy powiązane z adresem.
 *
 * @param string $email Adres z wniosku.
 * @param int    $strona Numer strony (od 1).
 * @return array Format wymagany przez WordPressa: data + done.
 */
function chatl_rodo_eksporter( $email, $strona = 1 ) {
	global $wpdb;
	$strona = max( 1, (int) $strona );
	$offset = ( $strona - 1 ) * CHATL_RODO_PORCJA;
	$dane   = array();

	$tz = $wpdb->prefix . 'chatl_zgloszenia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$zgloszenia = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, email, wiadomosc, status, utworzono FROM {$tz} WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$email,
			CHATL_RODO_PORCJA,
			$offset
		),
		ARRAY_A
	);
	// phpcs:enable

	foreach ( (array) $zgloszenia as $z ) {
		$dane[] = array(
			'group_id'    => 'chatl_zgloszenia',
			'group_label' => 'Zgłoszenia z czatu',
			'item_id'     => 'chatl-zgloszenie-' . (int) $z['id'],
			'data'        => array(
				array(
					'name'  => 'Adres e-mail',
					'value' => $z['email'],
				),
				array(
					'name'  => 'Wiadomość',
					'value' => $z['wiadomosc'],
				),
				array(
					'name'  => 'Status',
					'value' => $z['status'],
				),
				array(
					'name'  => 'Data',
					'value' => $z['utworzono'],
				),
			),
		);
	}

	/*
	 * Rozmowy dokładamy dopiero wtedy, gdy zgłoszenia się skończyły. Kreator woła
	 * nas stronami i oczekuje, że każda strona jest skończona — mieszanie dwóch
	 * źródeł na jednej stronie kończy się gubieniem wierszy przy przewijaniu.
	 */
	$podzial     = chatl_rodo_sesje_maila( $email );
	$sesje       = $podzial['wlasne'];
	$wolne_sloty = CHATL_RODO_PORCJA - count( (array) $zgloszenia );
	if ( $wolne_sloty > 0 && $sesje ) {
		/*
		 * Dwa źródła na wspólnej numeracji stron. Przesunięcie rozmów liczymy
		 * względem CAŁEJ liczby zgłoszeń, a nie względem bieżącej strony:
		 * przy dokładnie pełnej stronie zgłoszeń (100) naiwne `OFFSET $offset`
		 * przeskoczyłoby pierwsze sto wierszy rozmowy i wydało wniosek
		 * z dziurą — czyli najgorszy możliwy błąd w eksporcie danych osobowych,
		 * bo niewidoczny dla obu stron.
		 */
		$tz_count = $wpdb->prefix . 'chatl_zgloszenia';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
		$ile_zgloszen  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tz_count} WHERE email = %s", $email ) );
		$offset_rozmow = max( 0, $offset - $ile_zgloszen );
		$tr            = $wpdb->prefix . 'chatl_rozmowy';
		$miejsca       = implode( ', ', array_fill( 0, count( $sesje ), '%s' ) );
		$rozmowy       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sesja, rola, tresc, utworzono FROM {$tr} WHERE sesja IN ({$miejsca}) ORDER BY id ASC LIMIT %d OFFSET %d",
				array_merge( $sesje, array( $wolne_sloty, $offset_rozmow ) )
			),
			ARRAY_A
		);
		// phpcs:enable
		foreach ( (array) $rozmowy as $r ) {
			$dane[] = array(
				'group_id'    => 'chatl_rozmowy',
				'group_label' => 'Historia rozmowy z botem',
				'item_id'     => 'chatl-rozmowa-' . (int) $r['id'],
				'data'        => array(
					array(
						'name'  => 'Kto',
						'value' => chatl_nazwa_roli( $r['rola'] ),
					),
					array(
						'name'  => 'Treść',
						'value' => $r['tresc'],
					),
					array(
						'name'  => 'Data',
						'value' => $r['utworzono'],
					),
				),
			);
		}
	}

	/*
	 * `done` liczymy z WIERSZY DANYCH, zanim dołożymy notatkę o pominiętych
	 * rozmowach — inaczej notatka mogłaby dobić stronę do CHATL_RODO_PORCJA
	 * i kreator wołałby nas o jedną pustą stronę więcej.
	 */
	$gotowe = count( $dane ) < CHATL_RODO_PORCJA;

	/*
	 * Milczenie o pominiętej rozmowie byłoby gorsze niż jej pominięcie: paczka
	 * wyglądałaby na komplet. Notatkę dokładamy raz, na pierwszej stronie.
	 */
	if ( 1 === $strona && $podzial['wspoldzielone'] ) {
		$dane[] = array(
			'group_id'    => 'chatl_rozmowy',
			'group_label' => 'Historia rozmowy z botem',
			'item_id'     => 'chatl-rozmowa-pominieta',
			'data'        => array(
				array(
					'name'  => 'Rozmowy pominięte świadomie',
					'value' => sprintf(
						'W %d rozmowie/rozmowach ten adres nie był jedynym — w tej samej sesji przeglądarki '
							. 'zgłoszenie zostawiła także inna osoba (wspólny komputer albo drugi adres tego samego gościa). '
							. 'Treści takiej rozmowy nie wydajemy, bo zawiera wiadomości drugiej osoby, '
							. 'a nie da się rozstrzygnąć, który wiersz należy do kogo.',
						count( $podzial['wspoldzielone'] )
					),
				),
			),
		);
	}

	return array(
		'data' => $dane,
		'done' => $gotowe,
	);
}

/**
 * Kasownik: usuwa zgłoszenia i powiązane rozmowy.
 *
 * Kolejność ma znaczenie: NAJPIERW rozmowy, POTEM zgłoszenia. Zgłoszenie jest
 * jedynym łącznikiem między adresem a sesją — skasowane najpierw, zabrałoby ze
 * sobą wiedzę o tym, które rozmowy usunąć, i zostawiłoby je na zawsze.
 *
 * Argument `$strona` jest w sygnaturze, bo taki jest kontrakt kreatora, ale nie
 * jest uzywany: kasowanie idzie JEDNYM zapytaniem na tabele, wiec nie ma czego
 * dzielic na strony. Zwracamy `done` od razu i kreator nie wola nas ponownie.
 *
 * @param string $email  Adres z wniosku.
 * @param int    $strona Numer strony — wymagany przez kreator, nieuzywany (patrz wyzej).
 * @return array Format wymagany przez WordPressa.
 */
function chatl_rodo_kasownik( $email, $strona = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	global $wpdb;
	$komunikaty = array();

	$podzial = chatl_rodo_sesje_maila( $email );

	/*
	 * NAJPIERW ślad, POTEM kasowanie. Odwrotna kolejność kasowałaby dowód
	 * współdzielenia, zanim zdążylibyśmy go zapisać — patrz komentarz przy
	 * CHATL_RODO_OPCJA_WSPOLNE.
	 *
	 * Gdy zapis śladu padł, NIE kasujemy nic: skasowanie zgłoszenia bez śladu
	 * zabrałoby jedyny dowód, że sesja była współdzielona, i następny wniosek
	 * (drugiej osoby) dostałby eksport z cudzymi wiadomościami. Wniosek można
	 * powtórzyć po naprawie bazy; wycieku cofnąć się nie da. Komunikat mówi
	 * wprost, że nic nie usunięto.
	 */
	if ( ! chatl_rodo_zapamietaj_wspolne( $podzial['wspoldzielone'] ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array(
				'ChatBot LIVE: NIC nie zostało usunięte — baza odrzuciła zapis śladu rozmów współdzielonych, '
					. 'a bez niego kasowanie mogłoby później wydać innej osobie cudze wiadomości. '
					. 'Sprawdź stan bazy (pasek ostrzeżenia w panelu ChatBota) i uruchom usuwanie ponownie.',
			),
			'done'           => true,
		);
	}

	$ile_r = 0;
	if ( $podzial['wlasne'] ) {
		$tr      = $wpdb->prefix . 'chatl_rozmowy';
		$miejsca = implode( ', ', array_fill( 0, count( $podzial['wlasne'] ), '%s' ) );
		/*
		 * Lista miejsc na sesje budowana jest z array_fill(), wiec phpcs nie ma
		 * jak policzyc podstawien i ostrzega na wyrost — wartosci ida przez
		 * prepare() co do jednej. Poprawnosc pilnuje straznik: sekcja D sprawdza,
		 * ze kasowanie tyka TYLKO wnioskodawce.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
		$ile = $wpdb->query( $wpdb->prepare( "DELETE FROM {$tr} WHERE sesja IN ({$miejsca})", $podzial['wlasne'] ) );
		// phpcs:enable
		$ile_r = max( 0, (int) $ile );

		/*
		 * Wiersz sesji też jest śladem osoby (kiedy pisała, czy przejął ją
		 * człowiek), więc „usunięcie danych" musi go objąć — inaczej po wniosku
		 * RODO w bazie zostaje dowód, że ta rozmowa była (A3-1, część B).
		 * Kasujemy WYŁĄCZNIE sesje wyłączne wnioskodawcy. Sesja współdzielona
		 * ZOSTAJE nietknięta — tak samo jak jej rozmowa wyżej — bo należy też
		 * do drugiej osoby.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
		$ts    = $wpdb->prefix . 'chatl_sesje';
		$ile_s = $wpdb->query( $wpdb->prepare( "DELETE FROM {$ts} WHERE sesja IN ({$miejsca})", $podzial['wlasne'] ) );
		// phpcs:enable
		if ( false === $ile_s ) {
			chatl_log_bledu_bazy( 'chatl_rodo_kasownik', 'kasowanie wierszy sesji wnioskodawcy' );
		}
	}

	$tz = $wpdb->prefix . 'chatl_zgloszenia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$ile_z = $wpdb->query( $wpdb->prepare( "DELETE FROM {$tz} WHERE email = %s", $email ) );
	// phpcs:enable
	$ile_z = max( 0, (int) $ile_z );

	$usuniete = $ile_r + $ile_z;

	/*
	 * ⛔ KOMUNIKAT MUSI MÓWIĆ TO, CO SIĘ NAPRAWDĘ STAŁO — z LICZBAMI, nie w trybie
	 * bezwarunkowym. Wcześniejsza wersja pisała „Usunięto zgłoszenia oraz rozmowy"
	 * ZAWSZE, także wtedy, gdy nie zniknął ani jeden wiersz. Właściciel odpowiada
	 * tym tekstem osobie, która złożyła wniosek RODO, więc zdanie o kasowaniu,
	 * którego nie było, to potwierdzenie nieprawdy wysłane w cudzym imieniu.
	 */
	if ( 0 === $usuniete ) {
		$komunikaty[] = 'Nie znaleziono w ChatBocie żadnych danych powiązanych z tym adresem — nic nie zostało usunięte.';
	} else {
		$komunikaty[] = sprintf(
			'ChatBot LIVE: usunięto %d zgłoszenie/zgłoszenia z tym adresem oraz %d wiadomość/wiadomości z powiązanych rozmów.',
			(int) $ile_z,
			(int) $ile_r
		);
	}

	/*
	 * Rozmowa, w której SWÓJ adres zostawiła także inna osoba, nie jest kasowana —
	 * usunięcie zabrałoby dane tej drugiej osoby na wniosek osoby trzeciej.
	 * Właściciel musi o tym wiedzieć, bo to jedyny przypadek, w którym coś zostaje
	 * i w którym może być potrzebna jego decyzja.
	 */
	if ( $podzial['wspoldzielone'] ) {
		$komunikaty[] = sprintf(
			'Zachowano %d rozmowę/rozmowy: w tej samej sesji przeglądarki zgłoszenie zostawiła także inna osoba '
				. '(wspólny komputer albo drugi adres tego samego gościa). Skasowanie zabrałoby dane tej drugiej osoby, '
				. 'a wiersze rozmowy nie mają znacznika autora, więc nie da się ich rozdzielić automatycznie.',
			count( $podzial['wspoldzielone'] )
		);
	}

	/*
	 * Uczciwość wobec właściciela: rozmowa gościa, który nigdy nie zostawił maila,
	 * nie jest „zachowana" — nie ma jej jak powiązać z żadnym adresem w bazie.
	 */
	$komunikaty[] = 'Rozmowy gości, którzy nigdy nie podali adresu e-mail, nie są powiązane z żadnym adresem w bazie — '
		. 'kasuje je automatycznie retencja po ' . (int) CHATL_RETENCJA_DNI . ' dniach.';

	return array(
		'items_removed'  => $usuniete > 0,
		'items_retained' => (bool) $podzial['wspoldzielone'],
		'messages'       => $komunikaty,
		'done'           => true,
	);
}

/**
 * Rejestracja eksportera w kreatorze WordPressa.
 *
 * @param array $eksportery Lista.
 * @return array
 */
function chatl_rejestruj_eksporter( $eksportery ) {
	$eksportery['chatbot-live'] = array(
		'exporter_friendly_name' => 'ChatBot LIVE — zgłoszenia i rozmowy',
		'callback'               => 'chatl_rodo_eksporter',
	);
	return $eksportery;
}
add_filter( 'wp_privacy_personal_data_exporters', 'chatl_rejestruj_eksporter' );

/**
 * Rejestracja kasownika w kreatorze WordPressa.
 *
 * @param array $kasowniki Lista.
 * @return array
 */
function chatl_rejestruj_kasownik( $kasowniki ) {
	$kasowniki['chatbot-live'] = array(
		'eraser_friendly_name' => 'ChatBot LIVE — zgłoszenia i rozmowy',
		'callback'             => 'chatl_rodo_kasownik',
	);
	return $kasowniki;
}
add_filter( 'wp_privacy_personal_data_erasers', 'chatl_rejestruj_kasownik' );

/**
 * Podpowiedź do polityki prywatności — też narzędzie WordPressa, nie nasze.
 *
 * Właściciel dostaje gotowy akapit do wklejenia zamiast pisać go sam ze słuchu.
 * Treść mówi to samo, co wtyczka robi naprawdę: co zbieramy, po co i jak długo.
 *
 * @return void
 */
function chatl_polityka_prywatnosci() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$tresc  = '<p>' . esc_html(
		sprintf(
			'Czat na stronie zapisuje treść rozmowy oraz — jeśli zostawisz zgłoszenie — Twój adres e-mail i treść wiadomości. Historia rozmów jest kasowana automatycznie po %d dniach. Zgłoszenia zostają do czasu ich usunięcia przez administratora.',
			(int) CHATL_RETENCJA_DNI
		)
	) . '</p>';
	$tresc .= '<p>' . esc_html( 'Jeśli włączony jest tryb AI, treść pytania wraz z opisem firmy i bazą pytań jest wysyłana do dostawcy modelu (Anthropic) w celu wygenerowania odpowiedzi.' ) . '</p>';
	$tresc .= '<p>' . esc_html( 'Możesz poprosić o kopię swoich danych albo o ich usunięcie — administrator obsłuży wniosek wbudowanymi narzędziami WordPressa (Narzędzia → Eksport/Usuwanie danych osobowych).' ) . '</p>';
	wp_add_privacy_policy_content( 'ChatBot LIVE', $tresc );
}
add_action( 'admin_init', 'chatl_polityka_prywatnosci' );
