<?php
/**
 * EKSPORT REGUŁ DO FILTRÓW SKRZYNKI (P1.3) — Gmail i Outlook.
 *
 * Po co: bez tego Segregator kończy się na raporcie, czyli jest analizatorem
 * pliku, a nie segregatorem. Eksport przenosi sprawdzone reguły tam, gdzie
 * naprawdę stoi poczta.
 *
 * TWARDY WARUNEK, na którym stoi cały ten plik: nasz silnik zatrzymuje się na
 * PIERWSZEJ pasującej regule (silnik.php: `return` w pętli), a Gmail takiego
 * „przestań przetwarzać kolejne” nie ma. Eksport 1:1 dałby na żywej skrzynce
 * INNY wynik niż nasz własny raport — czyli produkt kłamałby dokładnie tam,
 * gdzie obiecuje dowód. Dlatego każdy filtr dostaje tu warunek wykluczający
 * WSZYSTKIE wcześniejsze reguły; wtedy mail wpada najwyżej do jednej
 * kategorii, niezależnie od kolejności stosowania filtrów przez Gmaila.
 *
 * ŹRÓDŁA FORMATU (czytane, nie odtwarzane z pamięci):
 * - Nazwy właściwości XML i przestrzenie nazw: „Migrate from the Email
 *   Settings API” — https://developers.google.com/workspace/gmail/api/guides/migrate-from-email-settings
 *   (dokumentuje `apps:property` o nazwach from, to, subject, hasTheWord,
 *   doesNotHaveTheWord, hasAttachment, label, shouldArchive, shouldMarkAsRead,
 *   shouldStar, forwardTo, shouldTrash, neverSpam, size, sizeOperator,
 *   sizeUnit oraz ich mapowanie na pola Gmail API).
 * - Składnia zapytań (from:, subject:, OR, nawiasy, minus, cudzysłów):
 *   https://support.google.com/mail/answer/7190
 * - „Any query using Gmail’s advanced search syntax can also be used in a
 *   filter” + „If multiple criteria are present in a filter, a message must
 *   satisfy all criteria”: https://developers.google.com/workspace/gmail/api/guides/filter_settings
 * - Outlook: „Stop processing more rules”
 *   https://support.microsoft.com/en-us/outlook/mail/stop-processing-more-rules-in-outlook
 *   oraz eksport reguł do pliku .rwz, który „can only be opened and used
 *   within classic Outlook”
 *   https://support.microsoft.com/en-us/office/import-or-export-a-set-of-rules-in-classic-outlook-f54b5bd2-40e0-426e-9f25-e51fa14eeb95
 *   — dlatego dla Outlooka NIE generujemy pliku (format zamknięty), tylko
 *   instrukcję krok po kroku.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Slug strony eksportu w menu wtyczki. */
const SEGM_FILTRY_STRONA = 'segm-filtry';

/**
 * Znaki, które rozbijają zapytanie Gmaila, a w naszym wzorcu są zwykłym
 * tekstem. Gmail nie ma znaku ucieczki, więc jedyne uczciwe wyjście to je
 * usunąć i POWIEDZIEĆ o tym przy regule, której to dotyczy.
 */
const SEGM_FILTRY_ZNAKI_DO_USUNIECIA = array( '"', '(', ')', '{', '}' );

/**
 * Różnice między naszym silnikiem a filtrami skrzynki — jedno źródło prawdy
 * dla panelu i dla KAŻDEGO wygenerowanego pliku.
 *
 * @return array<int, string>
 */
function segm_filtry_ostrzezenia() {
	return array(
		'KOLEJNOŚĆ. Nasz silnik zatrzymuje się na PIERWSZEJ pasującej regule. Gmail nie ma odpowiednika „przestań przetwarzać kolejne filtry”, więc eksport 1:1 dałby inny wynik niż nasz raport. Dlatego każdy filtr dostaje tu dodatkowy warunek wykluczający wszystkie wcześniejsze reguły (w Gmailu: pole „Nie zawiera”). Dzięki temu mail wpada najwyżej do JEDNEJ kategorii, niezależnie od tego, w jakiej kolejności Gmail zastosuje filtry.',
		'DOPASOWANIE TEKSTU. My szukamy FRAGMENTU: wzorzec „faktur” łapie u nas „faktury” i „fakturami”. Wyszukiwarka Gmaila operuje na słowach, nie na fragmentach, więc filtr może NIE złapać maili, które nasz raport policzył. Wzorce urwane w połowie słowa dopisz przed eksportem do pełnych form (np. „faktur” → „faktura, faktury, fakturę”).',
		'OUTLOOK JEST WIERNIEJSZY. Tam odwzorowanie jest PEŁNE, bo Outlook ma opcję „Stop processing more rules” („Zatrzymaj przetwarzanie kolejnych reguł”). Zaznaczona w każdej regule sprawia, że Outlook zachowuje się dokładnie jak nasz silnik: pierwsza pasująca wygrywa i koniec.',
		'WIELKOŚĆ LITER I OGONKI. Nasz silnik porównuje po sprowadzeniu do małych liter. Skrzynki mają własną normalizację (w tym znaków diakrytycznych), której nie kontrolujemy.',
		'TO NIE JEST GWARANCJA, że skrzynka policzy tak samo jak nasz raport. Raport mówi, co robią TWOJE reguły na TWOJEJ próbce. Eksport przenosi je tak blisko, jak pozwala format docelowy — resztę widzisz powyżej, wypisaną wprost.',
	);
}

/**
 * Warianty wzorca (rozdzielone przecinkiem), bez pustych.
 *
 * @param string $wzorzec Wzorzec reguły.
 * @return array<int, string>
 */
function segm_filtry_warianty( $wzorzec ) {
	$warianty = array_map( 'trim', explode( ',', (string) $wzorzec ) );
	return array_values(
		array_filter(
			$warianty,
			function ( $w ) {
				return '' !== $w;
			}
		)
	);
}

/**
 * Jeden wariant zamieniony na termin zapytania Gmaila.
 *
 * Wielowyrazowy wariant idzie w cudzysłów (składnia „exact phrase”), wariant
 * zaczynający się od minusa też — inaczej Gmail wziąłby go za wykluczenie.
 *
 * @param string $wariant Wariant z wzorca reguły.
 * @return string
 */
function segm_filtry_termin( $wariant ) {
	$czysty = str_replace( SEGM_FILTRY_ZNAKI_DO_USUNIECIA, '', (string) $wariant );
	$czysty = trim( $czysty );
	if ( '' === $czysty ) {
		return '';
	}
	if ( preg_match( '/\s/u', $czysty ) || 0 === strpos( $czysty, '-' ) ) {
		return '"' . $czysty . '"';
	}
	return $czysty;
}

/**
 * Klauzula zapytania dla jednej reguły, np. `from:(faktury@ OR ksiegowosc)`.
 *
 * Pole „tresc” idzie jako wolny tekst, bo Gmail nie ma operatora „tylko treść”
 * — i właśnie dlatego taka reguła dostaje osobną uwagę (szuka szerzej).
 *
 * @param object $regula Wiersz reguły z segm_pobierz_reguly().
 * @return string Pusty string = reguły nie da się przełożyć.
 */
function segm_filtry_klauzula( $regula ) {
	$terminy = array();
	foreach ( segm_filtry_warianty( $regula->wzorzec ) as $wariant ) {
		$termin = segm_filtry_termin( $wariant );
		if ( '' !== $termin ) {
			$terminy[] = $termin;
		}
	}
	if ( ! $terminy ) {
		return '';
	}

	$grupa = '(' . implode( ' OR ', $terminy ) . ')';
	if ( 'od' === $regula->pole ) {
		return 'from:' . $grupa;
	}
	if ( 'temat' === $regula->pole ) {
		return 'subject:' . $grupa;
	}
	return $grupa;
}

/**
 * Cała robota przekładu: reguły → filtry + lista tego, czego przełożyć NIE MOŻNA.
 *
 * @return array{filtry: array<int, array<string, mixed>>, nieprzekladalne: array<int, array<string, string>>}
 */
function segm_filtry_przygotuj() {
	$filtry          = array();
	$nieprzekladalne = array();
	$wczesniejsze    = array();

	foreach ( segm_pobierz_reguly() as $regula ) {
		$klauzula = segm_filtry_klauzula( $regula );
		$opis     = 'Reguła #' . (int) $regula->kolejnosc . ' (' . (string) $regula->pole . ' zawiera „' . (string) $regula->wzorzec . '”) → ' . (string) $regula->kategoria;

		if ( '' === $klauzula ) {
			$nieprzekladalne[] = array(
				'co'       => $opis,
				'dlaczego' => 'Po usunięciu znaków, których zapytanie Gmaila nie przyjmuje, nie zostało nic do szukania.',
			);
			continue;
		}

		$uwagi = array();
		if ( 'tresc' === $regula->pole ) {
			$uwagi[] = 'U nas ta reguła patrzy TYLKO w treść. Gmail nie ma operatora „tylko treść”, więc zapytanie przeszukuje całą wiadomość (nadawca, temat, treść) — filtr może złapać WIĘCEJ maili niż nasz raport.';
		}
		foreach ( segm_filtry_warianty( $regula->wzorzec ) as $wariant ) {
			if ( str_replace( SEGM_FILTRY_ZNAKI_DO_USUNIECIA, '', $wariant ) !== $wariant ) {
				$uwagi[] = 'Z wariantu „' . $wariant . '” usunięto znaki rozbijające zapytanie Gmaila (' . implode( ' ', SEGM_FILTRY_ZNAKI_DO_USUNIECIA ) . ') — sprawdź, czy filtr nadal łapie to, co miał.';
			}
		}

		$negacja        = implode( ' OR ', $wczesniejsze );
		$filtry[]       = array(
			'kolejnosc'    => (int) $regula->kolejnosc,
			'kategoria'    => (string) $regula->kategoria,
			'pole'         => (string) $regula->pole,
			'wzorzec'      => (string) $regula->wzorzec,
			'zapytanie'    => $klauzula,
			'negacja'      => $negacja,
			'do_wklejenia' => '' === $negacja ? $klauzula : $klauzula . ' -(' . $negacja . ')',
			'uwagi'        => $uwagi,
		);
		$wczesniejsze[] = $klauzula;
	}

	$nieprzekladalne[] = array(
		'co'       => 'Kubeł domyślny „' . SEGM_KUBEL_DOMYSLNY . '”',
		'dlaczego' => 'To nie jest reguła, tylko „żadna reguła nie pasowała”. Ani Gmail, ani Outlook nie mają warunku „nic innego nie zadziałało”, więc TEGO NIE DA SIĘ WYEKSPORTOWAĆ i nie ma go w żadnym z plików. Skutek w skrzynce wychodzi jednak ten sam: mail, którego nie złapie żaden filtr, po prostu zostaje w Skrzynce odbiorczej.',
	);
	$nieprzekladalne[] = array(
		'co'       => 'Accuracy i raport z liczbami',
		'dlaczego' => 'Skrzynka nie ma pojęcia o kolumnie „kategoria_oczekiwana” ani o pomyłkach — to zostaje po naszej stronie. Eksport przenosi decyzje, nie pomiary.',
	);

	return array(
		'filtry'          => $filtry,
		'nieprzekladalne' => $nieprzekladalne,
	);
}

/**
 * Tekst bezpieczny do wstawienia w komentarz XML.
 *
 * W komentarzu XML nie wolno użyć „--” ani skończyć go myślnikiem (XML 1.0
 * §2.5) — a tam trafia treść pisana przez właściciela (nazwy kategorii,
 * wzorce), więc nie ma co zakładać, że będzie grzeczna.
 *
 * @param string $tekst Tekst do komentarza.
 * @return string
 */
function segm_filtry_bezpieczny_komentarz( $tekst ) {
	$tekst = str_replace( '--', '- -', (string) $tekst );
	return rtrim( $tekst, '-' );
}

/**
 * Jedna właściwość filtra.
 *
 * Atrybuty w APOSTROFACH, wcięcie tabulatorami, znacznik samozamykający —
 * dokładnie tak, jak w pliku wyeksportowanym z żywej skrzynki (wzorzec
 * w audyt/boty/WZORZEC-gmail-filters.xml). esc_xml() zamienia apostrof na
 * &apos;, więc wartość z apostrofem nie rozbije atrybutu.
 *
 * @param string $nazwa   Nazwa właściwości (from, subject, hasTheWord, ...).
 * @param string $wartosc Wartość.
 * @return string
 */
function segm_filtry_wlasciwosc( $nazwa, $wartosc ) {
	return "\t\t<apps:property name='" . esc_xml( $nazwa ) . "' value='" . esc_xml( $wartosc ) . "'/>\n";
}

/**
 * Identyfikator wpisu w schemacie Atom.
 *
 * Gmail wstawia tu swoje wewnętrzne identyfikatory (`...:filter:<liczby>*<liczby>`).
 * My wstawiamy WŁASNE, jawnie oznaczone „segm” — po pierwsze nie da się ich
 * pomylić z prawdziwym filtrem w skrzynce, po drugie nie udajemy, że znamy
 * regułę budowania tamtych.
 *
 * @param string $rodzaj    'filters' dla kanału, 'filter' dla wpisu.
 * @param string $przyrostek Część odróżniająca.
 * @return string
 */
function segm_filtry_id( $rodzaj, $przyrostek ) {
	return 'tag:mail.google.com,2008:' . $rodzaj . ':segm' . $przyrostek;
}

/**
 * Plik XML do „Importuj filtry” w Gmailu.
 *
 * Struktura odwzorowana z PLIKU WYEKSPORTOWANEGO Z ŻYWEJ SKRZYNKI
 * (audyt/boty/WZORZEC-gmail-filters.xml): deklaracja XML w apostrofach, kanał
 * Atom z title/id/updated/author, każdy filtr jako <entry> z category
 * term='filter', title „Mail Filter”, id, updated i PUSTYM <content>, a dopiero
 * potem właściwości. Nazwy właściwości pochodzą z dokumentacji Google
 * („Migrate from the Email Settings API”), nie z próbki.
 *
 * ⚠️ CZEGO NIE WIEMY: Google nie dokumentuje, KTÓRE pola są WYMAGANE przy
 * imporcie, a które Gmail uzupełnia sam. Ze wzorca widać tylko, że Gmail
 * ZAPISUJE sizeOperator i sizeUnit nawet wtedy, gdy rozmiar nie był ustawiany
 * — więc je odtwarzamy, ale to obserwacja z jednego pliku, nie gwarancja.
 *
 * @param array $dane        Wynik segm_filtry_przygotuj().
 * @param bool  $archiwizuj  Czy dołożyć shouldArchive (Gmail: „Skip the Inbox”).
 * @return string
 */
function segm_filtry_xml( $dane, $archiwizuj ) {
	$komentarz = array(
		'WYGENEROWANE PRZEZ WTYCZKĘ „Segregator maila” — ' . current_time( 'Y-m-d H:i' ),
		'',
		'PRZECZYTAJ, ZANIM ZAIMPORTUJESZ:',
	);
	foreach ( segm_filtry_ostrzezenia() as $i => $ostrzezenie ) {
		$komentarz[] = ( $i + 1 ) . '. ' . $ostrzezenie;
	}
	$komentarz[] = '';
	$komentarz[] = 'CZEGO W TYM PLIKU NIE MA (nie da się tego przełożyć na filtry):';
	foreach ( $dane['nieprzekladalne'] as $pozycja ) {
		$komentarz[] = '- ' . $pozycja['co'] . ' — ' . $pozycja['dlaczego'];
	}

	$uzytkownik = wp_get_current_user();
	$kiedy      = gmdate( 'Y-m-d\TH:i:s\Z' );
	$stempel    = gmdate( 'YmdHis' );

	$xml  = "<?xml version='1.0' encoding='UTF-8'?>\n";
	$xml .= '<!--' . "\n" . segm_filtry_bezpieczny_komentarz( implode( "\n", $komentarz ) ) . "\n" . '-->' . "\n";
	$xml .= "<feed xmlns='http://www.w3.org/2005/Atom' xmlns:apps='http://schemas.google.com/apps/2006'>\n";
	$xml .= "\t<title>Mail Filters</title>\n";
	$xml .= "\t<id>" . esc_xml( segm_filtry_id( 'filters', $stempel ) ) . "</id>\n";
	$xml .= "\t<updated>" . esc_xml( $kiedy ) . "</updated>\n";
	$xml .= "\t<author>\n";
	$xml .= "\t\t<name>" . esc_xml( $uzytkownik->display_name ) . "</name>\n";
	$xml .= "\t\t<email>" . esc_xml( $uzytkownik->user_email ) . "</email>\n";
	$xml .= "\t</author>\n";

	foreach ( $dane['filtry'] as $filtr ) {
		$xml .= "\t<entry>\n";
		$xml .= "\t\t<category term='filter'></category>\n";
		$xml .= "\t\t<title>Mail Filter</title>\n";
		$xml .= "\t\t<id>" . esc_xml( segm_filtry_id( 'filter', $stempel . '*' . (int) $filtr['kolejnosc'] ) ) . "</id>\n";
		$xml .= "\t\t<updated>" . esc_xml( $kiedy ) . "</updated>\n";
		$xml .= "\t\t<content></content>\n";
		$xml .= segm_filtry_wlasciwosc( 'hasTheWord', $filtr['zapytanie'] );
		if ( '' !== $filtr['negacja'] ) {
			$xml .= segm_filtry_wlasciwosc( 'doesNotHaveTheWord', $filtr['negacja'] );
		}
		$xml .= segm_filtry_wlasciwosc( 'label', $filtr['kategoria'] );
		if ( $archiwizuj ) {
			$xml .= segm_filtry_wlasciwosc( 'shouldArchive', 'true' );
		}
		// Gmail dopisuje te dwie właściwości nawet bez ustawionego rozmiaru —
		// zachowujemy się tak samo, żeby plik był jak najbliżej jego własnego.
		$xml .= segm_filtry_wlasciwosc( 'sizeOperator', 's_sl' );
		$xml .= segm_filtry_wlasciwosc( 'sizeUnit', 's_smb' );
		$xml .= "\t</entry>\n";
	}

	$xml .= "</feed>\n";
	return $xml;
}

/**
 * Instrukcja dla Outlooka. Pliku .rwz NIE generujemy: Microsoft pisze wprost,
 * że „This file type can only be opened and used within classic Outlook”, a
 * import/eksport reguł nie działa w Outlooku w przeglądarce ani w nowym
 * Outlooku — czyli plik, który umielibyśmy zrobić, u połowy klientów i tak
 * byłby bezużyteczny. Zamiast tego: lista do odklikania, w naszej kolejności.
 *
 * @param array $dane Wynik segm_filtry_przygotuj().
 * @return string
 */
function segm_filtry_outlook( $dane ) {
	$linie   = array();
	$linie[] = 'SEGREGATOR MAILA — reguły do przeniesienia do Outlooka';
	$linie[] = 'wygenerowano: ' . current_time( 'Y-m-d H:i' );
	$linie[] = '';
	$linie[] = 'DLACZEGO TO INSTRUKCJA, A NIE PLIK DO IMPORTU';
	$linie[] = 'Outlook eksportuje reguły do pliku .rwz, który — cytując pomoc Microsoftu —';
	$linie[] = '„can only be opened and used within classic Outlook”, a sam import/eksport reguł';
	$linie[] = 'nie działa w Outlooku w przeglądarce ani w nowym Outlooku. Format jest zamknięty,';
	$linie[] = 'więc nie udajemy, że umiemy go wygenerować.';
	$linie[] = '';
	$linie[] = 'PRZECZYTAJ, ZANIM ZACZNIESZ:';
	foreach ( segm_filtry_ostrzezenia() as $i => $ostrzezenie ) {
		$linie[] = ( $i + 1 ) . '. ' . $ostrzezenie;
	}
	$linie[] = '';
	$linie[] = 'JAK TO USTAWIĆ';
	$linie[] = '1. Outlook klasyczny: Plik → Zarządzaj regułami i alertami → Nowa reguła.';
	$linie[] = '   Outlook w przeglądarce / nowy: Ustawienia → Poczta → Reguły → Dodaj regułę.';
	$linie[] = '2. Twórz reguły W TEJ KOLEJNOŚCI, w której są poniżej — ona rozstrzyga.';
	$linie[] = '3. W KAŻDEJ regule zaznacz „Zatrzymaj przetwarzanie kolejnych reguł”';
	$linie[] = '   („Stop processing more rules”). Bez tego Outlook zachowa się jak Gmail,';
	$linie[] = '   czyli nałoży kilka reguł naraz, i wynik przestanie zgadzać się z raportem.';
	$linie[] = '4. Uwaga przy imporcie do istniejącej skrzynki: reguły dokładane są NA KONIEC';
	$linie[] = '   listy, więc sprawdź, czy nie wyprzedza ich coś, co już tam było.';
	$linie[] = '';
	$linie[] = str_repeat( '=', 70 );

	$ile = count( $dane['filtry'] );
	foreach ( $dane['filtry'] as $nr => $filtr ) {
		$pola_opis = array(
			'od'    => 'nadawca zawiera',
			'temat' => 'temat zawiera',
			'tresc' => 'treść wiadomości zawiera',
		);
		$linie[]   = '';
		$linie[]   = 'REGUŁA ' . ( $nr + 1 ) . ' z ' . $ile;
		$linie[]   = '  warunek:   ' . ( $pola_opis[ $filtr['pole'] ] ?? $filtr['pole'] ) . ':';
		foreach ( segm_filtry_warianty( $filtr['wzorzec'] ) as $wariant ) {
			$linie[] = '               • ' . $wariant;
		}
		$linie[] = '             (wpisz każdy wariant osobno — wystarczy, że pasuje którykolwiek)';
		$linie[] = '  akcja:     przenieś wiadomość do folderu: ' . $filtr['kategoria'];
		$linie[] = '  NA KONIEC: zaznacz „Zatrzymaj przetwarzanie kolejnych reguł”';
		foreach ( $filtr['uwagi'] as $uwaga ) {
			$linie[] = '  UWAGA:     ' . $uwaga;
		}
	}

	$linie[] = '';
	$linie[] = str_repeat( '=', 70 );
	$linie[] = '';
	$linie[] = 'CZEGO NIE DA SIĘ PRZENIEŚĆ:';
	foreach ( $dane['nieprzekladalne'] as $pozycja ) {
		$linie[] = '- ' . $pozycja['co'];
		$linie[] = '  ' . $pozycja['dlaczego'];
	}
	$linie[] = '';

	return implode( "\r\n", $linie ) . "\r\n";
}

/**
 * Lista zapytań w CSV — do wklejania w pole wyszukiwania Gmaila „ręcznie”,
 * kiedy właściciel woli zobaczyć wyniki, zanim cokolwiek zaimportuje.
 *
 * Ten plik otwiera się w arkuszu, więc obowiązuje ta sama obrona co przy
 * eksporcie raportu: komórka od =, +, -, @ przechodzi przez segm_csv_komorka()
 * (OWASP CSV injection). Tu nie jest to teoria — kolumna z zapytaniem potrafi
 * zaczynać się od nawiasu albo minusa, a nazwy kategorii i wzorce pisze
 * użytkownik.
 *
 * @param array $dane Wynik segm_filtry_przygotuj().
 * @return void Wysyła plik i kończy żądanie.
 */
function segm_filtry_csv( $dane ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=segregator-zapytania-gmail.csv' );

	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

	foreach ( segm_filtry_ostrzezenia() as $i => $ostrzezenie ) {
		fputcsv( $out, array( segm_csv_komorka( 'UWAGA ' . ( $i + 1 ) . ': ' . $ostrzezenie ) ), ',', '"', '\\' );
	}
	foreach ( $dane['nieprzekladalne'] as $pozycja ) {
		fputcsv( $out, array( segm_csv_komorka( 'NIE DA SIĘ WYEKSPORTOWAĆ: ' . $pozycja['co'] . ' — ' . $pozycja['dlaczego'] ) ), ',', '"', '\\' );
	}
	fputcsv( $out, array( '' ), ',', '"', '\\' );

	fputcsv(
		$out,
		array( 'kolejnosc', 'kategoria', 'pole_u_nas', 'wzorzec_u_nas', 'zapytanie_gmail', 'wyklucz_wczesniejsze_reguly', 'do_wklejenia_w_gmailu', 'uwagi' ),
		',',
		'"',
		'\\'
	);
	foreach ( $dane['filtry'] as $filtr ) {
		fputcsv(
			$out,
			array(
				(int) $filtr['kolejnosc'],
				segm_csv_komorka( $filtr['kategoria'] ),
				segm_csv_komorka( $filtr['pole'] ),
				segm_csv_komorka( $filtr['wzorzec'] ),
				segm_csv_komorka( $filtr['zapytanie'] ),
				segm_csv_komorka( $filtr['negacja'] ),
				segm_csv_komorka( $filtr['do_wklejenia'] ),
				segm_csv_komorka( implode( ' | ', $filtr['uwagi'] ) ),
			),
			',',
			'"',
			'\\'
		);
	}

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

/* ===================== PANEL ===================== */

/**
 * Podstrona „Eksport filtrów”. Rejestrowana z tego pliku, a nie z admin.php —
 * dzięki temu cała funkcja mieści się w jednym pliku.
 *
 * @return void
 */
function segm_filtry_menu() {
	add_submenu_page(
		'segm-przebiegi',
		'Eksport filtrów do skrzynki',
		'Eksport filtrów',
		'manage_options',
		SEGM_FILTRY_STRONA,
		'segm_strona_filtry'
	);
}
add_action( 'admin_menu', 'segm_filtry_menu', 11 );

/**
 * Widok strony eksportu.
 *
 * @return void
 */
function segm_strona_filtry() {
	$dane = segm_filtry_przygotuj();
	?>
	<div class="wrap">
		<h1>Eksport filtrów do skrzynki</h1>
		<?php segm_komunikaty(); ?>
		<p>Reguły, które sprawdziłeś na próbce, możesz przenieść do prawdziwej skrzynki. Poniżej widzisz DOKŁADNIE to, co wyjdzie w pliku — razem z tym, czego przenieść się nie da.</p>

		<div class="notice notice-warning" style="padding:8px 12px">
			<h2 style="margin-top:4px">Zanim wyeksportujesz — czym filtry skrzynki różnią się od naszego raportu</h2>
			<ol style="margin-left:18px">
				<?php foreach ( segm_filtry_ostrzezenia() as $ostrzezenie ) : ?>
					<li style="margin-bottom:6px"><?php echo esc_html( $ostrzezenie ); ?></li>
				<?php endforeach; ?>
			</ol>
		</div>

		<h2>Co wyjedzie do Gmaila (<?php echo count( $dane['filtry'] ); ?> filtrów)</h2>
		<?php if ( ! $dane['filtry'] ) : ?>
			<p><strong>Nie ma czego eksportować</strong> — nie masz ani jednej reguły, którą da się przełożyć. Dodaj reguły w zakładce „Reguły”.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>#</th><th>Kategoria → etykieta</th><th>Warunek u nas</th><th>Zapytanie Gmaila</th><th>Wyklucza wcześniejsze reguły</th></tr></thead>
				<tbody>
				<?php foreach ( $dane['filtry'] as $filtr ) : ?>
					<tr>
						<td><?php echo (int) $filtr['kolejnosc']; ?></td>
						<td><strong><?php echo esc_html( $filtr['kategoria'] ); ?></strong></td>
						<td><?php echo esc_html( $filtr['pole'] . ' zawiera „' . $filtr['wzorzec'] . '”' ); ?></td>
						<td><code><?php echo esc_html( $filtr['zapytanie'] ); ?></code></td>
						<td><?php echo '' === $filtr['negacja'] ? '— (pierwsza reguła)' : '<code>' . esc_html( $filtr['negacja'] ) . '</code>'; ?></td>
					</tr>
					<?php foreach ( $filtr['uwagi'] as $uwaga ) : ?>
						<tr><td></td><td colspan="4" class="description">⚠ <?php echo esc_html( $uwaga ); ?></td></tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Czego NIE da się wyeksportować</h2>
		<table class="widefat striped">
			<thead><tr><th style="width:32%">Co</th><th>Dlaczego</th></tr></thead>
			<tbody>
			<?php foreach ( $dane['nieprzekladalne'] as $pozycja ) : ?>
				<tr><td><strong><?php echo esc_html( $pozycja['co'] ); ?></strong></td><td><?php echo esc_html( $pozycja['dlaczego'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2>Pobierz</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="segm_eksport_filtrow">
			<?php wp_nonce_field( 'segm_eksport_filtrow' ); ?>
			<p><label><input type="checkbox" name="archiwizuj" value="1">
				dołóż do każdego filtra „Pomiń Skrzynkę odbiorczą (archiwizuj)” — mail od razu znika z głównej listy i leży pod etykietą</label></p>
			<p class="description" style="margin-bottom:12px">Bez tego filtr tylko nakleja etykietę, a mail zostaje w Skrzynce odbiorczej. Zacznij bez archiwizacji, zobacz przez kilka dni, czy filtry łapią to, co trzeba, i dopiero potem wyeksportuj z tą opcją.</p>
			<p>
				<button type="submit" name="format" value="gmail_xml" class="button button-primary">Gmail: plik XML do zaimportowania</button>
				<button type="submit" name="format" value="gmail_csv" class="button">Gmail: zapytania do wklejenia (CSV)</button>
				<button type="submit" name="format" value="outlook_txt" class="button">Outlook: instrukcja krok po kroku (TXT)</button>
			</p>
		</form>
		<p class="description">
			Gmail: <em>Ustawienia → Zobacz wszystkie ustawienia → Filtry i zablokowane adresy → Importuj filtry</em>.
			Outlook nie ma importu z zewnętrznego pliku (jego własny format <code>.rwz</code> otwiera tylko klasyczny Outlook), dlatego tam dostajesz listę do odklikania.
		</p>
	</div>
	<?php
}

/**
 * Wysyłka wybranego pliku. Ta sama ochrona co przy eksporcie raportu CSV:
 * capability + nonce, a treść przechodzi przez escapowanie właściwe formatowi
 * (esc_xml dla XML, segm_csv_komorka dla arkusza).
 *
 * @return void
 */
function segm_post_eksport_filtrow() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'segm_eksport_filtrow' );

	$format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : '';
	if ( ! in_array( $format, array( 'gmail_xml', 'gmail_csv', 'outlook_txt' ), true ) ) {
		segm_przekieruj_blad( SEGM_FILTRY_STRONA, 'Nieznany format eksportu — nic nie zostało pobrane.' );
	}

	$dane = segm_filtry_przygotuj();
	if ( ! $dane['filtry'] ) {
		segm_przekieruj_blad( SEGM_FILTRY_STRONA, 'Nie ma ani jednej reguły, którą da się przełożyć na filtr — pusty plik nikomu nie pomoże. Dodaj reguły w zakładce „Reguły”.' );
	}

	if ( 'gmail_csv' === $format ) {
		segm_filtry_csv( $dane );
	}

	nocache_headers();
	if ( 'gmail_xml' === $format ) {
		$archiwizuj = ! empty( $_POST['archiwizuj'] );
		header( 'Content-Type: application/atom+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=segregator-filtry-gmail.xml' );
		echo segm_filtry_xml( $dane, $archiwizuj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dokument budowany w segm_filtry_xml(), każda wartość przez esc_xml().
		exit;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=segregator-reguly-outlook.txt' );
	echo segm_filtry_outlook( $dane ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plik tekstowy, nie HTML; treść nie trafia do przeglądarki jako znaczniki.
	exit;
}
add_action( 'admin_post_segm_eksport_filtrow', 'segm_post_eksport_filtrow' );
