<?php
/**
 * Import bazy Q&A z pliku CSV — z PODGLĄDEM przed zapisem.
 *
 * Dlaczego podgląd jest sednem, a nie ozdobą: plik przychodzi z arkusza
 * właściciela, czyli z miejsca, nad którym nie mamy żadnej kontroli. Import
 * „na ślepo" albo wsypuje do bazy śmieci, albo — gorzej — wsypuje wpisy, które
 * WYGLĄDAJĄ dobrze, a bot nigdy w nie nie trafi. Podgląd jest bezpiecznikiem:
 * właściciel widzi każdy wiersz, widzi werdykt i widzi, na co bot naprawdę
 * będzie reagował, ZANIM cokolwiek wyląduje w bazie.
 *
 * ⚠️ Rzecz, która decyduje o tym, czy import komuś pomoże: silnik dopasowuje
 * WYŁĄCZNIE po `slowa_kluczowe` (silnik.php: SELECT id, slowa_kluczowe,
 * odpowiedz + chatl_trafione_warianty). Kolumna `pytanie` to etykieta dla
 * właściciela — bot nigdy w nią nie patrzy. Wiersz z pustymi słowami
 * kluczowymi jest więc MARTWY: gość może wkleić pytanie słowo w słowo i nie
 * dostanie odpowiedzi. A typowy plik z Excela ma dokładnie pytanie i odpowiedź,
 * bez słów kluczowych. Dlatego brakujące słowa PODPOWIADAMY (patrz
 * chatl_import_slowa_wiersza) i pokazujemy je w podglądzie w polu do EDYCJI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sufit wierszy jednego pliku. Powyżej odmawiamy z komunikatem, zamiast paść
 * na limicie pamięci PHP — 2000 par to i tak baza większa niż u kogokolwiek,
 * kto kupuje wtyczkę na stronę firmową.
 */
const CHATL_IMPORT_MAX_WIERSZY = 2000;

/** Sufit rozmiaru pliku (bajty). Ta sama zasada co wyżej: odmowa, nie awaria. */
const CHATL_IMPORT_MAX_BAJTOW = 2097152;

/** Ile minut żyje podgląd czekający na potwierdzenie. */
const CHATL_IMPORT_MINUT = 15;

/**
 * Klucz transientu z podglądem — OSOBNY DLA KAŻDEGO UŻYTKOWNIKA.
 *
 * Dwóch administratorów wgrywających pliki w tej samej minucie nie może sobie
 * nawzajem podmienić podglądu: to jest dokładnie ten scenariusz, w którym
 * ekran pokazuje jedno, a zapis robi drugie.
 *
 * @return string
 */
function chatl_import_klucz() {
	return 'chatl_import_' . get_current_user_id();
}

/**
 * Zdjęcie BOM UTF-8 z początku pliku.
 *
 * Nasz WŁASNY eksport stawia BOM (bez niego Excel z polskimi ustawieniami
 * czyta plik jako windows-1250), więc plik, który najczęściej tu wróci, zaczyna
 * się od tych trzech bajtów.
 *
 * Uczciwie o sile tej funkcji, bo próba kontrolna to zmierzyła: jej usunięcie
 * NIE psuje dziś importu. Nagłówek przeżyłby i bez niej, bo
 * chatl_import_klucz_kolumny() zamienia bajty BOM-u na „_" i obcina je razem
 * z podkreśleniami. To jest jednak zbieg okoliczności w normalizacji nazw,
 * a nie gwarancja: gdyby ktoś kiedyś zmienił tamtą normalizację, pierwsza
 * kolumna po cichu przestałaby być rozpoznawana. Zdejmujemy BOM WPROST, żeby
 * ta własność miała jedno, nazwane miejsce — a strażnik pilnuje jej osobno.
 *
 * @param string $tresc Treść pliku.
 * @return string
 */
function chatl_import_bez_bom( $tresc ) {
	$tresc = (string) $tresc;
	if ( 0 === strncmp( $tresc, "\xEF\xBB\xBF", 3 ) ) {
		return substr( $tresc, 3 );
	}
	return $tresc;
}

/**
 * Doprowadzenie treści do UTF-8.
 *
 * Polski Excel zapisuje „CSV (rozdzielany przecinkami)" w windows-1250, nie
 * w UTF-8. Bez konwersji polskie znaki wchodzą do bazy jako krzaki i właściciel
 * dowiaduje się o tym dopiero od gościa. Wykrywanie jest po fakcie: jeśli treść
 * NIE jest poprawnym UTF-8, traktujemy ją jako windows-1250 (jedyne realne
 * drugie kodowanie w polskim Excelu) i konwertujemy.
 *
 * ⚠️ Konwersja idzie przez `iconv`, NIE przez `mb_convert_encoding` — i to nie
 * jest kwestia gustu. mbstring **nie zna CP1250**: tej strony kodowej nie ma
 * na liście `mb_list_encodings()` (sprawdzone na naszym PHP 8.4), więc
 * `mb_convert_encoding( …, 'UTF-8', 'Windows-1250' )` kończy się rzuconym
 * `ValueError`, czyli białym ekranem — dokładnie na pliku z polskiego Excela,
 * czyli w jedynym przypadku, dla którego ta konwersja w ogóle istnieje.
 * Wykrywanie zostaje przy mbstring (UTF-8 zna doskonale), konwersja przy iconv.
 *
 * @param string $tresc Treść pliku.
 * @return string|null Treść w UTF-8 albo null, gdy nie ma czym przekonwertować.
 */
function chatl_import_na_utf8( $tresc ) {
	$tresc = (string) $tresc;
	if ( ! function_exists( 'mb_check_encoding' ) ) {
		// Uczciwa odmowa zamiast cichego kaleczenia treści: bez mbstring nie
		// mamy jak nawet SPRAWDZIĆ kodowania, a wtyczka i tak wymaga mbstring
		// w silniku dopasowania (mb_strtolower/mb_strpos).
		return null;
	}
	if ( mb_check_encoding( $tresc, 'UTF-8' ) ) {
		return $tresc;
	}
	if ( ! function_exists( 'iconv' ) ) {
		return null;
	}
	// //IGNORE: bajty, których w CP1250 nie ma (0x81, 0x83, 0x88, 0x90, 0x98),
	// wypadają zamiast wywracać cały import jednym feralnym znakiem.
	$przekonwertowane = iconv( 'CP1250', 'UTF-8//IGNORE', $tresc );
	return false === $przekonwertowane ? null : $przekonwertowane;
}

/**
 * Separator kolumn odgadnięty z wiersza nagłówka.
 *
 * Polski Excel zapisuje CSV ze ŚREDNIKIEM (bierze go z ustawień regionalnych
 * Windows), a nasz eksport i reszta świata — z przecinkiem. Plik ze średnikiem
 * wczytany przecinkiem daje JEDNĄ kolumnę o nazwie "pytanie;odpowiedz",
 * czyli komunikat „brak wymaganych kolumn" przy pliku, który jest poprawny.
 * Zgadujemy po tym, który znak daje więcej kolumn — przy remisie przecinek,
 * bo taki jest nasz własny eksport.
 *
 * @param string $naglowek Pierwszy wiersz pliku.
 * @return string
 */
function chatl_import_separator( $naglowek ) {
	$naglowek = (string) $naglowek;
	return substr_count( $naglowek, ';' ) > substr_count( $naglowek, ',' ) ? ';' : ',';
}

/**
 * Zdjęcie apostrofu, którym eksport neutralizuje formuły arkuszy.
 *
 * chatl_csv_komorka() dokleja apostrof przed =,+,-,@ (OWASP CSV injection).
 * Gdyby import go nie zdejmował, każde okrążenie eksport→import doklejałoby
 * kolejny apostrof i po trzech rundach odpowiedź zaczynałaby się od "'''".
 *
 * @param string $wartosc Komórka z pliku.
 * @return string
 */
function chatl_import_odapostrofuj( $wartosc ) {
	$wartosc = (string) $wartosc;
	if ( 2 <= strlen( $wartosc ) && "'" === $wartosc[0] && in_array( $wartosc[1], array( '=', '+', '-', '@' ), true ) ) {
		return substr( $wartosc, 1 );
	}
	return $wartosc;
}

/**
 * Nazwa kolumny sprowadzona do postaci porównywalnej.
 *
 * Właściciel ma w arkuszu „Słowa kluczowe", „slowa_kluczowe" albo „SŁOWA
 * KLUCZOWE" — dla nas to jedna i ta sama kolumna. Mapujemy PO NAZWIE, nie po
 * pozycji, żeby przestawienie kolumn w Excelu nie psuło importu.
 *
 * @param string $nazwa Nagłówek z pliku.
 * @return string
 */
function chatl_import_klucz_kolumny( $nazwa ) {
	$nazwa = chatl_bez_ogonkow( mb_strtolower( trim( (string) $nazwa ) ) );
	$nazwa = preg_replace( '/[^a-z0-9]+/', '_', $nazwa );
	return trim( (string) $nazwa, '_' );
}

/**
 * Mapa: nazwa kolumny z pliku → nasze pole.
 *
 * @return array
 */
function chatl_import_kolumny() {
	return array(
		'pytanie'          => 'pytanie',
		'pytanie_wzorcowe' => 'pytanie',
		'slowa_kluczowe'   => 'slowa_kluczowe',
		'slowa'            => 'slowa_kluczowe',
		'warianty'         => 'slowa_kluczowe',
		'odpowiedz'        => 'odpowiedz',
		'odpowiedz_bota'   => 'odpowiedz',
		'aktywne'          => 'aktywne',
		'aktywny'          => 'aktywne',
	);
}

/**
 * Wartość kolumny „aktywne" z tego, co ludzie realnie wpisują w arkuszu.
 *
 * @param string $wartosc Komórka (może nie istnieć).
 * @return int 0 albo 1. Brak kolumny i pusta komórka => 1 (wpis ma działać).
 */
function chatl_import_aktywne( $wartosc ) {
	$w = chatl_bez_ogonkow( mb_strtolower( trim( (string) $wartosc ) ) );
	if ( '' === $w ) {
		return 1;
	}
	return in_array( $w, array( '0', 'nie', 'no', 'false', 'n' ), true ) ? 0 : 1;
}

/**
 * Słowa kluczowe wiersza: z pliku albo podpowiedziane z pytania.
 *
 * Podpowiedź idzie przez chatl_sugerowane_slowa() — TĘ SAMĄ funkcję, którą
 * panel podpowiada słowa przy ręcznym dodawaniu pary (statystyki.php). Nie
 * piszemy tu drugiej heurystyki: dwie kopie tej samej reguły rozjadą się przy
 * pierwszej zmianie, a wtedy import i panel podpowiadałyby co innego.
 *
 * @param string $z_pliku Zawartość kolumny ze słowami (może być pusta).
 * @param string $pytanie Pytanie wzorcowe.
 * @return array array( slowa, zrodlo ) — zrodlo: 'plik' albo 'wygenerowane'.
 */
function chatl_import_slowa_wiersza( $z_pliku, $pytanie ) {
	$z_pliku = trim( (string) $z_pliku );
	if ( '' !== $z_pliku ) {
		return array( $z_pliku, 'plik' );
	}
	return array( chatl_sugerowane_slowa( $pytanie ), 'wygenerowane' );
}

/**
 * Ostrzeżenie o wierszu, w który bot nie trafi (albo trafi nie tym, czym trzeba).
 *
 * Dwa różne niepowodzenia, więc dwa różne komunikaty — sklejenie ich w jeden
 * („coś jest nie tak ze słowami") kazałoby właścicielowi zgadywać, co poprawić:
 *
 * 1. BRAK wariantów — z pytania nie dało się wyciągnąć ani jednego rdzenia
 *    (samo „Ile to?" to same krótkie słówka). Bot NIGDY nie trafi w ten wpis.
 * 2. Wariant KRÓTSZY niż CHATL_SUGESTIA_RDZEN — dopasowanie idzie podłańcuchem,
 *    więc za krótki rdzeń łapie nie to, co trzeba: „kosz" łapie „koszule"
 *    (przykład z komentarza przy tej stałej w statystyki.php).
 *
 * @param string $slowa Warianty rozdzielone przecinkami.
 * @return string Pusty string, gdy nie ma o czym ostrzegać.
 */
function chatl_import_ostrzezenie( $slowa ) {
	$warianty = array_filter( array_map( 'trim', explode( ',', (string) $slowa ) ) );
	if ( ! $warianty ) {
		return 'Bot NIGDY nie trafi w ten wpis — nie ma ani jednego słowa kluczowego. Wpisz warianty ręcznie.';
	}
	$najkrotszy = PHP_INT_MAX;
	foreach ( $warianty as $wariant ) {
		$najkrotszy = min( $najkrotszy, mb_strlen( $wariant ) );
	}
	if ( $najkrotszy < CHATL_SUGESTIA_RDZEN ) {
		return sprintf(
			'Najkrótszy wariant ma %d zn. (mniej niż %d) — bot dopasowuje fragmentem, więc taki rdzeń łapie nie to, co trzeba (jak „kosz" łapie „koszule"). Sprawdź go.',
			(int) $najkrotszy,
			(int) CHATL_SUGESTIA_RDZEN
		);
	}
	return '';
}

/**
 * Pytania, które JUŻ są w bazie — w postaci znormalizowanej, do wykrywania duplikatów.
 *
 * Normalizacja przez chatl_normalizuj_pytanie() (ta sama, po której statystyki
 * grupują pytania), więc „Ile kosztuje dostawa?" i „ile kosztuje dostawa"
 * to jeden i ten sam wpis, a nie dwa.
 *
 * @return array Klucze: znormalizowane pytania.
 */
function chatl_import_istniejace_pytania() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_qa';
	$wpisy  = $wpdb->get_col( "SELECT pytanie FROM {$tabela}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$mapa   = array();
	foreach ( (array) $wpisy as $pytanie ) {
		$mapa[ chatl_normalizuj_pytanie( $pytanie ) ] = true;
	}
	return $mapa;
}

/**
 * Parsowanie treści pliku CSV do wierszy z werdyktem.
 *
 * Funkcja jest CZYSTA wobec wejścia (dostaje string, nie ścieżkę) — dzięki temu
 * strażnik sprawdza parsowanie plikiem z pamięci, bez udawania uploadu HTTP.
 *
 * @param string $tresc Surowa treść pliku.
 * @return array array( blad, wiersze, liczby )
 */
function chatl_import_parsuj( $tresc ) {
	$pusty = array(
		'blad'    => '',
		'wiersze' => array(),
		'liczby'  => array(
			'nowe'      => 0,
			'duplikaty' => 0,
			'bledne'    => 0,
		),
	);

	if ( strlen( (string) $tresc ) > CHATL_IMPORT_MAX_BAJTOW ) {
		$pusty['blad'] = sprintf( 'Plik jest większy niż %d MB. Podziel go na części.', (int) ( CHATL_IMPORT_MAX_BAJTOW / 1048576 ) );
		return $pusty;
	}

	$tresc = chatl_import_na_utf8( chatl_import_bez_bom( $tresc ) );
	if ( null === $tresc ) {
		$pusty['blad'] = 'Nie udało się odczytać kodowania pliku. Serwer musi mieć rozszerzenia PHP „mbstring" i „iconv" — poproś hostingodawcę o ich włączenie albo zapisz plik w Excelu jako „CSV UTF-8".';
		return $pusty;
	}
	if ( '' === trim( $tresc ) ) {
		$pusty['blad'] = 'Plik jest pusty.';
		return $pusty;
	}

	// Pierwszy wiersz bez strtok(): strtok trzyma stan globalny, a tu potrzebny
	// jest tylko kawałek tekstu do najbliższego końca linii.
	$pierwsza  = substr( $tresc, 0, strcspn( $tresc, "\r\n" ) );
	$separator = chatl_import_separator( $pierwsza );
	$uchwyt    = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $uchwyt, $tresc ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	rewind( $uchwyt );

	$naglowek = fgetcsv( $uchwyt, 0, $separator, '"', '\\' );
	if ( ! is_array( $naglowek ) ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$pusty['blad'] = 'Nie udało się odczytać pierwszego wiersza pliku.';
		return $pusty;
	}

	$mapa  = chatl_import_kolumny();
	$gdzie = array();
	foreach ( $naglowek as $pozycja => $nazwa ) {
		$klucz = chatl_import_klucz_kolumny( $nazwa );
		if ( isset( $mapa[ $klucz ] ) && ! isset( $gdzie[ $mapa[ $klucz ] ] ) ) {
			$gdzie[ $mapa[ $klucz ] ] = (int) $pozycja;
		}
	}
	if ( ! isset( $gdzie['pytanie'], $gdzie['odpowiedz'] ) ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$pusty['blad'] = sprintf(
			'Plik musi mieć kolumny „pytanie" i „odpowiedz". W pierwszym wierszu znalazłem: %s.',
			'' === trim( implode( ', ', array_map( 'strval', $naglowek ) ) ) ? '(pusty wiersz)' : implode( ', ', array_map( 'strval', $naglowek ) )
		);
		return $pusty;
	}

	$istniejace = chatl_import_istniejace_pytania();
	$w_pliku    = array();
	$wiersze    = array();
	$numer      = 1;

	while ( true ) {
		$kolumny = fgetcsv( $uchwyt, 0, $separator, '"', '\\' );
		if ( false === $kolumny ) {
			break;
		}
		++$numer;
		if ( count( $wiersze ) >= CHATL_IMPORT_MAX_WIERSZY ) {
			$pusty['blad'] = sprintf( 'Plik ma więcej niż %d wierszy. Podziel go na części.', (int) CHATL_IMPORT_MAX_WIERSZY );
			fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $pusty;
		}
		// fgetcsv oddaje dla pustej linii tablicę z jednym null-em; false
		// (koniec pliku) obsłużyliśmy już wyżej przerwaniem pętli.
		if ( 1 === count( $kolumny ) && null === $kolumny[0] ) {
			continue; // Pusta linia (np. na końcu pliku) — nie jest błędem właściciela.
		}

		$komorka = static function ( $pole ) use ( $kolumny, $gdzie ) {
			if ( ! isset( $gdzie[ $pole ] ) || ! isset( $kolumny[ $gdzie[ $pole ] ] ) ) {
				return '';
			}
			return chatl_import_odapostrofuj( (string) $kolumny[ $gdzie[ $pole ] ] );
		};

		$pytanie   = sanitize_text_field( $komorka( 'pytanie' ) );
		$odpowiedz = sanitize_textarea_field( $komorka( 'odpowiedz' ) );

		if ( '' === $pytanie && '' === $odpowiedz ) {
			continue; // Wiersz całkiem pusty — arkusze lubią je zostawiać.
		}

		$wiersz = array(
			'numer'       => $numer,
			'pytanie'     => $pytanie,
			'odpowiedz'   => $odpowiedz,
			'slowa'       => '',
			'zrodlo_slow' => 'plik',
			'aktywne'     => chatl_import_aktywne( $komorka( 'aktywne' ) ),
			'status'      => 'nowy',
			'powod'       => '',
			'ostrzezenie' => '',
		);

		if ( '' === $pytanie || '' === $odpowiedz ) {
			$wiersz['status'] = 'blad';
			$wiersz['powod']  = '' === $pytanie ? 'brak pytania' : 'brak odpowiedzi';
			$wiersze[]        = $wiersz;
			continue;
		}

		list( $slowa, $zrodlo ) = chatl_import_slowa_wiersza( $komorka( 'slowa_kluczowe' ), $pytanie );
		$wiersz['slowa']        = sanitize_text_field( $slowa );
		$wiersz['zrodlo_slow']  = $zrodlo;
		$wiersz['ostrzezenie']  = chatl_import_ostrzezenie( $wiersz['slowa'] );

		$znormalizowane = chatl_normalizuj_pytanie( $pytanie );
		if ( isset( $istniejace[ $znormalizowane ] ) ) {
			$wiersz['status'] = 'duplikat';
			$wiersz['powod']  = 'takie pytanie już jest w bazie';
		} elseif ( isset( $w_pliku[ $znormalizowane ] ) ) {
			$wiersz['status'] = 'duplikat';
			$wiersz['powod']  = sprintf( 'to samo pytanie jest wyżej w pliku (wiersz %d)', (int) $w_pliku[ $znormalizowane ] );
		} else {
			$w_pliku[ $znormalizowane ] = $numer;
		}

		$wiersze[] = $wiersz;
	}
	fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	$liczby = array(
		'nowe'      => 0,
		'duplikaty' => 0,
		'bledne'    => 0,
	);
	foreach ( $wiersze as $w ) {
		if ( 'nowy' === $w['status'] ) {
			++$liczby['nowe'];
		} elseif ( 'duplikat' === $w['status'] ) {
			++$liczby['duplikaty'];
		} else {
			++$liczby['bledne'];
		}
	}

	if ( ! $wiersze ) {
		$pusty['blad'] = 'Plik ma nagłówek, ale ani jednego wiersza z danymi.';
		return $pusty;
	}

	return array(
		'blad'    => '',
		'wiersze' => $wiersze,
		'liczby'  => $liczby,
	);
}

/**
 * Krok 1: wczytanie pliku i przygotowanie PODGLĄDU. Nic nie trafia do bazy.
 */
function chatl_post_qa_import_podglad() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_qa_import_podglad' );

	/*
	 * $_FILES nie da się „odkazić" funkcją sanitize_*: to tablica metadanych
	 * od PHP, a nie tekst od użytkownika. Odkażamy to, co z niej BIERZEMY —
	 * `error` przez rzutowanie na int, a `tmp_name` przez is_uploaded_file(),
	 * czyli sprawdzenie mocniejsze niż jakakolwiek filtracja tekstu: ścieżka
	 * musi pochodzić z uploadu tego żądania. Nazwy pliku od klienta nie
	 * używamy w ogóle — nigdzie go pod nią nie zapisujemy.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce sprawdzony linijkę wyżej; uzasadnienie braku sanitize_* w komentarzu powyżej.
	$plik = isset( $_FILES['plik'] ) ? $_FILES['plik'] : null;
	if ( ! is_array( $plik ) || ! isset( $plik['tmp_name'] ) || ! isset( $plik['error'] ) || UPLOAD_ERR_OK !== (int) $plik['error'] ) {
		chatl_przekieruj_blad( 'chatl-qa', chatl_import_blad_uploadu( is_array( $plik ) && isset( $plik['error'] ) ? (int) $plik['error'] : UPLOAD_ERR_NO_FILE ) );
	}

	$sciezka = (string) $plik['tmp_name'];
	if ( ! is_uploaded_file( $sciezka ) ) {
		chatl_przekieruj_blad( 'chatl-qa', 'Plik nie przyszedł z formularza — odrzucony.' );
	}

	/*
	 * Czytamy WPROST z pliku tymczasowego i nigdzie go nie kopiujemy.
	 * wp_handle_upload() wrzuciłby plik z pytaniami i odpowiedziami klienta do
	 * uploads/ NA STAŁE, pod adresem możliwym do odgadnięcia — a my go tam nie
	 * potrzebujemy ani przez sekundę. PHP kasuje plik tymczasowy sam po żądaniu.
	 */
	$tresc = file_get_contents( $sciezka ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $tresc ) {
		chatl_przekieruj_blad( 'chatl-qa', 'Nie udało się odczytać wgranego pliku.' );
	}

	$wynik = chatl_import_parsuj( $tresc );
	if ( '' !== $wynik['blad'] ) {
		chatl_przekieruj_blad( 'chatl-qa', $wynik['blad'] );
	}

	set_transient( chatl_import_klucz(), $wynik, CHATL_IMPORT_MINUT * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa&chatl_import=podglad' ) );
	exit;
}
add_action( 'admin_post_chatl_qa_import_podglad', 'chatl_post_qa_import_podglad' );

/**
 * Komunikat po nieudanym uploadzie — po ludzku, nie numerem błędu PHP.
 *
 * Najczęstszy przypadek u klienta to plik większy niż upload_max_filesize
 * hostingu, a domyślny komunikat WP („nie wybrano pliku") kieruje wtedy
 * właściciela w złą stronę.
 *
 * @param int $kod Kod z $_FILES['plik']['error'].
 * @return string
 */
function chatl_import_blad_uploadu( $kod ) {
	switch ( (int) $kod ) {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			return sprintf(
				'Plik jest za duży dla tego serwera (limit hostingu: %s). Podziel plik na części.',
				size_format( wp_max_upload_size() )
			);
		case UPLOAD_ERR_PARTIAL:
			return 'Plik doszedł tylko w części — spróbuj wgrać go jeszcze raz.';
		case UPLOAD_ERR_NO_TMP_DIR:
		case UPLOAD_ERR_CANT_WRITE:
			return 'Serwer nie ma gdzie zapisać pliku tymczasowego — to konfiguracja hostingu, zgłoś ją hostingodawcy.';
		default:
			return 'Nie wybrano pliku CSV.';
	}
}

/**
 * Krok 2: zapis potwierdzonego podglądu.
 *
 * SEDNO: zapisujemy wiersze Z TRANSIENTU, czyli DOKŁADNIE te, które
 * wyrenderował podgląd — nie parsujemy pliku po raz drugi. Wersja z ponownym
 * parsowaniem wygląda identycznie, a pozwala pokazać co innego, niż się zapisze
 * (inny plik pod tą samą nazwą, inny separator wykryty przy drugim przebiegu).
 * Z formularza bierzemy WYŁĄCZNIE poprawione słowa kluczowe — pytania
 * i odpowiedzi pochodzą z podglądu, więc podmiana pola w przeglądarce nie
 * wprowadzi do bazy treści, której właściciel nie widział.
 */
function chatl_post_qa_import_zapisz() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_qa_import_zapisz' );

	$podglad = get_transient( chatl_import_klucz() );
	if ( ! is_array( $podglad ) || empty( $podglad['wiersze'] ) ) {
		chatl_przekieruj_blad( 'chatl-qa', sprintf( 'Podgląd wygasł (ważny %d minut) — wgraj plik jeszcze raz.', (int) CHATL_IMPORT_MINUT ) );
	}

	// Sanityzacja NA GRANICY: każdy element z osobna, zanim tablica pójdzie
	// dalej. chatl_import_zapisz_wiersze() odkaża je jeszcze raz — sanitize_*
	// jest idempotentne, a funkcja zapisu ma być bezpieczna także wtedy, gdy
	// zawoła ją kiedyś inny kod.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdzony w check_admin_referer wyżej.
	$poprawione = array();
	if ( isset( $_POST['slowa'] ) && is_array( $_POST['slowa'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- każdy element odkażany sanitize_text_field w pętli poniżej; indeks rzutowany na int.
		foreach ( wp_unslash( $_POST['slowa'] ) as $indeks => $wartosc ) {
			$poprawione[ (int) $indeks ] = sanitize_text_field( (string) $wartosc );
		}
	}

	$wynik = chatl_import_zapisz_wiersze( $podglad['wiersze'], $poprawione );

	delete_transient( chatl_import_klucz() );

	if ( $wynik['padlo'] > 0 ) {
		chatl_przekieruj_blad(
			'chatl-qa',
			sprintf(
				'Zapisano %d z %d wpisów — przy %d baza odrzuciła zapis. Te wpisy NIE zostały zachowane.',
				(int) $wynik['zapisane'],
				(int) ( $wynik['zapisane'] + $wynik['padlo'] ),
				(int) $wynik['padlo']
			)
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa&chatl_ok=zaimportowano&chatl_ile=' . (int) $wynik['zapisane'] ) );
	exit;
}
add_action( 'admin_post_chatl_qa_import_zapisz', 'chatl_post_qa_import_zapisz' );

/**
 * Zapis wierszy z ZATWIERDZONEGO podglądu.
 *
 * Wydzielone z uchwytu żądania nie dla elegancji, tylko żeby dało się to
 * sprawdzić: obietnica „zapisuje się dokładnie to, co pokazał podgląd" jest
 * sprawdzalna tylko wtedy, gdy zapis jest funkcją biorącą WIERSZE PODGLĄDU,
 * a nie ścieżkę do pliku. Strażnik podaje tu ten sam obiekt, który wcześniej
 * wyszedł z chatl_import_parsuj(), i porównuje bazę z podglądem co do znaku.
 *
 * @param array $wiersze    Wiersze z podglądu.
 * @param array $poprawione Słowa kluczowe poprawione na ekranie (indeks => tekst).
 * @return array array( zapisane, padlo )
 */
function chatl_import_zapisz_wiersze( $wiersze, $poprawione = array() ) {
	global $wpdb;
	$tabela   = $wpdb->prefix . 'chatl_qa';
	$teraz    = current_time( 'mysql' );
	$zapisane = 0;
	$padlo    = 0;

	foreach ( (array) $wiersze as $indeks => $wiersz ) {
		if ( 'nowy' !== $wiersz['status'] ) {
			continue; // Duplikaty i wiersze z błędem świadomie pomijamy — podgląd zapowiedział to wprost.
		}
		$slowa = isset( $poprawione[ $indeks ] ) ? sanitize_text_field( (string) $poprawione[ $indeks ] ) : (string) $wiersz['slowa'];
		if ( '' === trim( $slowa ) ) {
			// Wyczyszczone pole nie może znaczyć „zapisz wpis, w który bot nigdy
			// nie trafi" — wracamy do tego, co pokazał podgląd.
			$slowa = (string) $wiersz['slowa'];
		}

		$wynik = $wpdb->insert(
			$tabela,
			array(
				'pytanie'        => $wiersz['pytanie'],
				'slowa_kluczowe' => $slowa,
				'odpowiedz'      => $wiersz['odpowiedz'],
				'aktywne'        => (int) $wiersz['aktywne'],
				'utworzono'      => $teraz,
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $wynik ) {
			++$padlo;
			chatl_log_bledu_bazy( 'chatl_import_zapisz_wiersze', 'wpis z importu CSV: ' . $wiersz['pytanie'] );
		} else {
			++$zapisane;
		}
	}

	return array(
		'zapisane' => $zapisane,
		'padlo'    => $padlo,
	);
}

/**
 * Porzucenie podglądu bez zapisu.
 */
function chatl_post_qa_import_anuluj() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'chatl_qa_import_anuluj' );
	delete_transient( chatl_import_klucz() );
	wp_safe_redirect( admin_url( 'admin.php?page=chatl-qa' ) );
	exit;
}
add_action( 'admin_post_chatl_qa_import_anuluj', 'chatl_post_qa_import_anuluj' );

/**
 * Ekran podglądu (renderowany na stronie Baza Q&A, gdy czeka potwierdzenie).
 *
 * @return bool Czy podgląd był i został wyrenderowany.
 */
function chatl_import_ekran_podgladu() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- samo przełączenie widoku; zapis idzie osobnym żądaniem z noncem.
	if ( ! isset( $_GET['chatl_import'] ) ) {
		return false;
	}
	$podglad = get_transient( chatl_import_klucz() );
	if ( ! is_array( $podglad ) || empty( $podglad['wiersze'] ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( 'Podgląd wygasł (ważny %d minut) — wgraj plik jeszcze raz.', (int) CHATL_IMPORT_MINUT ) ) . '</p></div>';
		return false;
	}

	$liczby = $podglad['liczby'];
	?>
	<h2>Podgląd importu — nic jeszcze nie zostało zapisane</h2>
	<p>
		Do zapisania: <strong><?php echo (int) $liczby['nowe']; ?></strong> ·
		pomijane duplikaty: <strong><?php echo (int) $liczby['duplikaty']; ?></strong> ·
		wiersze z błędem: <strong><?php echo (int) $liczby['bledne']; ?></strong>
	</p>
	<p class="description">
		Bot dopasowuje pytanie gościa <strong>wyłącznie po słowach kluczowych</strong> — pytanie wzorcowe jest tylko etykietą dla Ciebie.
		Słowa oznaczone jako <em>podpowiedziane</em> zrobiliśmy z treści pytania; <strong>możesz je tu poprawić przed zapisem</strong>.
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="chatl_qa_import_zapisz">
		<?php wp_nonce_field( 'chatl_qa_import_zapisz' ); ?>
		<table class="widefat striped">
			<thead><tr><th>Wiersz</th><th>Werdykt</th><th>Pytanie wzorcowe</th><th>Słowa kluczowe (możesz poprawić)</th><th>Odpowiedź</th><th>Aktywny</th></tr></thead>
			<tbody>
			<?php foreach ( $podglad['wiersze'] as $indeks => $w ) : ?>
				<tr>
					<td><?php echo (int) $w['numer']; ?></td>
					<td>
						<?php if ( 'nowy' === $w['status'] ) : ?>
							<strong>do zapisania</strong>
						<?php else : ?>
							<?php echo 'duplikat' === $w['status'] ? 'pomijany' : '<strong>błąd</strong>'; ?>
							<br><span class="description"><?php echo esc_html( $w['powod'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $w['pytanie'] ); ?></td>
					<td>
						<?php if ( 'nowy' === $w['status'] ) : ?>
							<input type="text" class="large-text code" name="slowa[<?php echo (int) $indeks; ?>]"
								value="<?php echo esc_attr( $w['slowa'] ); ?>">
							<?php if ( 'wygenerowane' === $w['zrodlo_slow'] ) : ?>
								<br><span class="description">⚙ <strong>podpowiedziane</strong> z pytania — sprawdź je</span>
							<?php endif; ?>
							<?php if ( '' !== $w['ostrzezenie'] ) : ?>
								<br><span class="description" style="color:#b32d2e">⚠ <?php echo esc_html( $w['ostrzezenie'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<?php echo esc_html( $w['slowa'] ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( wp_html_excerpt( $w['odpowiedz'], 120, '…' ) ); ?></td>
					<td><?php echo $w['aktywne'] ? 'tak' : '<strong>nie</strong>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<?php submit_button( sprintf( 'Zapisz %d wpisów', (int) $liczby['nowe'] ), 'primary', 'submit', false, $liczby['nowe'] > 0 ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_qa_import_anuluj' ), 'chatl_qa_import_anuluj' ) ); ?>">Anuluj import</a>
		</p>
	</form>
	<?php
	return true;
}

/**
 * Sekcja „Import i eksport" na stronie Baza Q&A.
 */
function chatl_import_ekran_formularza() {
	?>
	<h2>Import i eksport bazy (CSV)</h2>
	<p class="description">
		Masz gotową listę pytań w Excelu? Wgraj ją tutaj — <strong>zobaczysz podgląd i dopiero potem zapisujesz</strong>.
		Plik musi mieć kolumny <code>pytanie</code> i <code>odpowiedz</code>; <code>slowa_kluczowe</code> i <code>aktywne</code> są nieobowiązkowe.
		Kolejność kolumn nie ma znaczenia. Najprościej: pobierz swoją bazę poniżej, popraw w arkuszu i wgraj z powrotem.
	</p>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="chatl_qa_import_podglad">
		<?php wp_nonce_field( 'chatl_qa_import_podglad' ); ?>
		<input type="file" name="plik" accept=".csv,text/csv" required>
		<?php submit_button( 'Wczytaj i pokaż podgląd', 'secondary', 'submit', false ); ?>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=chatl_eksport_qa' ), 'chatl_eksport_qa' ) ); ?>">Pobierz CSV (cała baza Q&amp;A)</a>
	</form>
	<?php
}
