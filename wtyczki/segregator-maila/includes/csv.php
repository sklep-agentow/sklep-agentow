<?php
/**
 * Import próbki z CSV + eksport wyników przebiegu do CSV.
 * CSV to wejście NIEZAUFANE: sanitize każdej komórki, limity, a przy eksporcie
 * neutralizacja formuł arkuszy (OWASP CSV injection: komórka od =,+,-,@).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SEGM_IMPORT_MAX_WIERSZY = 500;
const SEGM_MAX_POLE           = 2000;

/* Ile bajtów początku pliku wystarczy, żeby rozpoznać kodowanie. */
const SEGM_PROBKA_KODOWANIA = 65536;

/*
 * Twardy limit rozmiaru wgrywanego pliku. 500 wierszy × maksymalne pola
 * (190 + 2000 + 2000 + 100 znaków) to ok. 2,2 MB w UTF-8 — 5 MB daje zapas na
 * cudzysłowy, długie linie i wiersze ponad limit, a jednocześnie odcina plik
 * z milionem linii ZANIM zacznie zajmować pamięć i czas PHP.
 */
const SEGM_IMPORT_MAX_BAJTOW = 5242880;

/**
 * Rozpoznanie kodowania pliku CSV.
 *
 * Dotąd zdejmowany był tylko znacznik BOM, a reszta kodu zakładała UTF-8 —
 * plik z polskiego Excela („Zapisz jako CSV") jest w Windows-1250 i wchodził
 * jako krzaki na każdym ą, ć, ę, ł.
 *
 * UCZCIWIE o granicy tej detekcji: Windows-1250 i ISO-8859-2 są jednobajtowe
 * i obejmują prawie ten sam zakres bajtów, więc ŻADNA heurystyka nie odróżni
 * ich pewnie na krótkim tekście. Rozstrzygamy więc twardo i przewidywalnie:
 * poprawny UTF-8 zostaje UTF-8, wszystko inne traktujemy jako Windows-1250
 * (kodowanie polskiego Excela, czyli źródło ~każdego takiego pliku) i mówimy
 * o tym użytkownikowi w komunikacie po imporcie.
 *
 * @param string $probka Początek pliku (surowe bajty).
 * @return string 'UTF-8' | 'Windows-1250' | '' gdy kodowanie nieobsługiwane.
 */
function segm_wykryj_kodowanie( $probka ) {
	// UTF-16/UTF-32 (Excel: „Tekst Unicode") — fgetcsv tego nie rozetnie,
	// bo separatory są tam zapisane na dwóch bajtach. Lepszy jawny odrzut niż
	// import wiersza-krzaka.
	if ( 0 === strncmp( $probka, "\xFF\xFE", 2 ) || 0 === strncmp( $probka, "\xFE\xFF", 2 ) ) {
		return '';
	}
	if ( 0 === strncmp( $probka, "\xEF\xBB\xBF", 3 ) ) {
		return 'UTF-8';
	}
	// Próbka mogła uciąć znak wielobajtowy w połowie — bez tego poprawny plik
	// UTF-8 zostałby uznany za jednobajtowy tylko dlatego, że akurat tam się
	// skończyła.
	$obciete = 0;
	while ( $obciete < 3 && '' !== $probka && ! mb_check_encoding( $probka, 'UTF-8' ) ) {
		$probka = substr( $probka, 0, -1 );
		++$obciete;
	}
	return mb_check_encoding( $probka, 'UTF-8' ) ? 'UTF-8' : 'Windows-1250';
}

/**
 * Przekodowanie pojedynczej komórki na UTF-8 (baza i cała wtyczka są w UTF-8).
 *
 * Robi to ICONV, nie mbstring — i to nie jest kwestia gustu: mbstring w PHP
 * NIE ZNA Windows-1250. Sprawdzone na stacku (PHP 8.4):
 *   mb_list_encodings() => Windows-1252, Windows-1254, ISO-8859-2, Windows-1251
 * a mb_convert_encoding( ..., 'Windows-1250' ) rzuca ValueError „contains
 * invalid encoding". Podstawienie ISO-8859-2 „bo podobne" byłoby zgadywaniem:
 * te strony różnią się m.in. na ą, ś, ź, ż, czyli dokładnie tam, gdzie boli.
 *
 * //IGNORE zamiast //TRANSLIT: Windows-1250 ma kilka bajtów nieprzypisanych
 * (0x81, 0x83, 0x88, 0x90, 0x98) — mają zniknąć, a nie zamienić się w „?".
 *
 * @param string $tekst     Komórka w kodowaniu źródłowym.
 * @param string $kodowanie Kodowanie wykryte dla pliku.
 * @return string
 */
function segm_na_utf8( $tekst, $kodowanie ) {
	if ( 'UTF-8' === $kodowanie ) {
		return $tekst;
	}
	$wynik = iconv( $kodowanie, 'UTF-8//IGNORE', $tekst );
	return false === $wynik ? $tekst : $wynik;
}

/**
 * Autodetekcja separatora po PIERWSZEJ linii pliku: polski Excel („Zapisz
 * jako CSV" przy locale PL) daje średniki, ręczne pliki — przecinki.
 * Wygrywa separator, który rozcina pierwszą linię na więcej kolumn.
 *
 * @param string $linia Pierwsza linia pliku (bez BOM).
 * @return string ',' albo ';'
 */
function segm_wykryj_separator( $linia ) {
	$przecinkiem = count( str_getcsv( $linia, ',', '"', '\\' ) );
	$srednikiem  = count( str_getcsv( $linia, ';', '"', '\\' ) );
	return $srednikiem > $przecinkiem ? ';' : ',';
}

/**
 * Import CSV: kolumny od,temat,tresc[,kategoria_oczekiwana]; import ZASTĘPUJE
 * bieżącą próbkę (klient wrzuca eksport swojej skrzynki zamiast demo).
 * Separator (przecinek/średnik) i kodowanie (UTF-8 / Windows-1250) wykrywane
 * automatycznie; BOM UTF-8 zdejmowany; wiersz danych z mniej niż 3 kolumnami =
 * ODRZUCENIE całego importu (żadnego cichego importowania śmieci przy złym
 * separatorze).
 *
 * @param string $sciezka Ścieżka tmp wgranego pliku.
 * @return array|WP_Error ['zaimportowano' => int, 'pominieto' => int, 'przerwano' => bool, 'separator' => string, 'kodowanie' => string]
 */
function segm_importuj_csv( $sciezka ) {
	global $wpdb;

	// Limit rozmiaru PRZED otwarciem pliku: import 500 wierszy nie ma powodu
	// dotykać pliku wielkości płyty, a odrzut na wejściu jest jedyną obroną,
	// której nie da się obejść nietypową zawartością.
	$rozmiar = (int) filesize( $sciezka ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
	if ( $rozmiar > SEGM_IMPORT_MAX_BAJTOW ) {
		return new WP_Error(
			'segm_csv',
			sprintf(
				'Plik ma %s, a limit to %s. Import próbki bierze najwyżej %d wierszy — wytnij z pliku fragment do przetestowania reguł i wgraj go ponownie. Próbka NIE została zmieniona.',
				size_format( $rozmiar ),
				size_format( SEGM_IMPORT_MAX_BAJTOW ),
				SEGM_IMPORT_MAX_WIERSZY
			)
		);
	}

	$uchwyt = fopen( $sciezka, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $uchwyt ) {
		return new WP_Error( 'segm_csv', 'Nie udało się odczytać pliku.' );
	}

	$poczatek = (string) fread( $uchwyt, SEGM_PROBKA_KODOWANIA ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread

	// Bajt zerowy nie występuje w pliku tekstowym — to znak, że ktoś wgrał
	// obrazek/archiwum/plik binarny z rozszerzeniem .csv. Odrzucamy, zanim
	// fgetcsv zacznie z tego produkować „wiersze".
	if ( false !== strpos( $poczatek, "\0" ) && 0 !== strncmp( $poczatek, "\xFF\xFE", 2 ) && 0 !== strncmp( $poczatek, "\xFE\xFF", 2 ) ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'segm_csv', 'To nie jest plik tekstowy CSV (zawiera dane binarne). Wgraj plik zapisany jako CSV — najlepiej „CSV UTF-8". Próbka NIE została zmieniona.' );
	}

	$kodowanie = segm_wykryj_kodowanie( $poczatek );
	if ( '' === $kodowanie ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'segm_csv', 'Plik jest zapisany w UTF-16 (tak zapisuje pozycja „Tekst Unicode" w Excelu) — tego formatu nie czytamy. Zapisz go jako „CSV UTF-8" albo zwykły „CSV" i wgraj ponownie. Próbka NIE została zmieniona.' );
	}
	if ( 'UTF-8' !== $kodowanie && ! function_exists( 'iconv' ) ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'segm_csv', 'Plik nie jest zapisany w UTF-8, a ten serwer nie ma rozszerzenia PHP „iconv", którym umiemy go przekodować (mbstring nie obsługuje Windows-1250). Zapisz plik jako „CSV UTF-8" i wgraj ponownie — albo poproś hosting o włączenie iconv. Próbka NIE została zmieniona.' );
	}
	rewind( $uchwyt );

	$pierwsza_linia = fgets( $uchwyt );
	if ( false === $pierwsza_linia ) {
		fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'segm_csv', 'Plik jest pusty.' );
	}
	// Separator liczymy na surowych bajtach: przecinek i średnik mają ten sam
	// kod w UTF-8 i w Windows-1250, więc przekodowanie nic by tu nie zmieniło.
	$separator = segm_wykryj_separator( preg_replace( '/^\xEF\xBB\xBF/', '', $pierwsza_linia ) );
	rewind( $uchwyt );

	$wiersze   = array();
	$pominieto = 0;
	$przerwano = false;
	$pierwszy  = true;
	$nr        = 0;

	while ( false !== ( $w = fgetcsv( $uchwyt, 0, $separator, '"', '\\' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- kanoniczny idiom czytania CSV; przypisanie jawnie porównane z false.
		++$nr;
		if ( $pierwszy ) {
			$pierwszy = false;
			// Excel przy zapisie UTF-8 dokleja BOM — zdejmij przed rozpoznaniem nagłówka.
			if ( isset( $w[0] ) ) {
				$w[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $w[0] );
			}
			// Wiersz nagłówka (od,temat,...) pomijamy.
			if ( isset( $w[0] ) && 'od' === strtolower( trim( (string) $w[0] ) ) ) {
				continue;
			}
		}
		// Pustą linię (np. końcówka pliku) pomijamy bez błędu.
		$niepuste = array_filter( $w, fn( $p ) => '' !== trim( (string) $p ) );
		if ( ! $niepuste ) {
			++$pominieto;
			continue;
		}
		// Wiersz danych z < 3 kolumnami = zły separator/format => twarde odrzucenie.
		if ( count( $w ) < 3 ) {
			fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error(
				'segm_csv',
				sprintf(
					'Import odrzucony: wiersz %d ma mniej niż 3 kolumny (wykryty separator: „%s"). Wymagane kolumny: od%stemat%stresc — sprawdź separator i format pliku. Próbka NIE została zmieniona.',
					$nr,
					$separator,
					$separator,
					$separator
				)
			);
		}
		// Przekodowanie PRZED sanityzacją: sanitize_text_field() wycina bajty
		// niebędące poprawnym UTF-8, więc na krzakach z Windows-1250 zjadałoby
		// polskie znaki zamiast je uratować.
		$od        = sanitize_text_field( segm_na_utf8( (string) $w[0], $kodowanie ) );
		$temat     = sanitize_text_field( segm_na_utf8( (string) $w[1], $kodowanie ) );
		$tresc     = sanitize_textarea_field( segm_na_utf8( (string) $w[2], $kodowanie ) );
		$oczek     = isset( $w[3] ) ? sanitize_text_field( segm_na_utf8( (string) $w[3], $kodowanie ) ) : '';
		$wiersze[] = array(
			'zrodlo'               => 'import',
			'nadawca'              => mb_substr( $od, 0, 190 ),
			'temat'                => mb_substr( $temat, 0, SEGM_MAX_POLE ),
			'tresc'                => mb_substr( $tresc, 0, SEGM_MAX_POLE ),
			'kategoria_oczekiwana' => mb_substr( $oczek, 0, 100 ),
			'utworzono'            => current_time( 'mysql' ),
		);

		// PRZERWANIE, nie „licz dalej i pomijaj": wcześniej pętla i tak
		// przechodziła plik do końca, więc plik z milionem linii był mielony
		// w całości mimo limitu 500. Sprawdzamy jeszcze, czy za limitem coś
		// realnie jest — żeby nie straszyć komunikatem pliku, który ma
		// równiutko 500 wierszy i pustą linię na końcu.
		if ( count( $wiersze ) >= SEGM_IMPORT_MAX_WIERSZY ) {
			$dalej = fgets( $uchwyt );
			while ( false !== $dalej && '' === trim( $dalej ) ) {
				$dalej = fgets( $uchwyt );
			}
			$przerwano = false !== $dalej;
			break;
		}
	}
	fclose( $uchwyt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	if ( ! $wiersze ) {
		return new WP_Error( 'segm_csv', 'Plik nie zawiera żadnego poprawnego wiersza (kolumny: od, temat, tresc, opcjonalnie kategoria_oczekiwana).' );
	}

	// Wyczyszczenie próbki MUSI się udać przed wstawianiem — inaczej nowe wiersze
	// doklejałyby się do starej próbki, a komunikat mówiłby „zastąpiono".
	if ( false === $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}segm_maile" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		segm_log_bledu_bazy( 'segm_importuj_csv', 'czyszczenie bieżącej próbki przed importem' );
		return new WP_Error( 'segm_csv', 'Nie udało się wyczyścić bieżącej próbki — import przerwany. Próbka NIE została zmieniona.' );
	}
	$nieudane = 0;
	foreach ( $wiersze as $wiersz ) {
		if ( false === $wpdb->insert( $wpdb->prefix . 'segm_maile', $wiersz, array( '%s', '%s', '%s', '%s', '%s', '%s' ) ) ) {
			++$nieudane;
			segm_log_bledu_bazy( 'segm_importuj_csv', 'wiersz próbki od „' . $wiersz['nadawca'] . '"' );
		}
	}
	if ( $nieudane > 0 ) {
		return new WP_Error(
			'segm_csv',
			sprintf(
				'Import NIEKOMPLETNY: z %d wierszy baza odrzuciła %d. W próbce jest %d maili — raport z takiej próbki policzy się na niepełnych danych. Sprawdź stan bazy i zaimportuj plik ponownie.',
				count( $wiersze ),
				$nieudane,
				count( $wiersze ) - $nieudane
			)
		);
	}

	return array(
		'zaimportowano' => count( $wiersze ),
		'pominieto'     => $pominieto,
		'przerwano'     => $przerwano,
		'separator'     => $separator,
		'kodowanie'     => $kodowanie,
	);
}

/**
 * Neutralizacja komórki na eksport (arkusze wykonują komórki od =,+,-,@).
 */
function segm_csv_komorka( $wartosc ) {
	$wartosc = (string) $wartosc;
	if ( '' !== $wartosc && in_array( $wartosc[0], array( '=', '+', '-', '@' ), true ) ) {
		$wartosc = "'" . $wartosc;
	}
	return $wartosc;
}

/**
 * Eksport wyników przebiegu do CSV (strumień do przeglądarki).
 *
 * @param object $przebieg Wiersz segm_przebiegi.
 * @param array  $wyniki   Zdekodowane wyniki przebiegu.
 */
function segm_eksportuj_csv( $przebieg, $wyniki ) {
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=segregator-przebieg-' . (int) $przebieg->id . '.csv' );

	$porownanie = 'porownaj' === $przebieg->tryb;

	$out      = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$naglowek = array( 'od', 'temat', 'kategoria', 'regula_id', 'kategoria_oczekiwana', 'trafiony' );
	if ( $porownanie ) {
		$naglowek[] = 'kategoria_ai';
		$naglowek[] = 'roznica';
	}
	fputcsv( $out, $naglowek, ',', '"', '\\' );
	foreach ( $wyniki as $w ) {
		$wiersz = array(
			segm_csv_komorka( $w['nadawca'] ),
			segm_csv_komorka( $w['temat'] ),
			segm_csv_komorka( $w['kategoria'] ),
			null === $w['regula_id'] ? '' : (int) $w['regula_id'],
			segm_csv_komorka( $w['oczekiwana'] ),
			null === $w['trafiony'] ? '' : ( $w['trafiony'] ? 'tak' : 'NIE' ),
		);
		if ( $porownanie ) {
			$wiersz[] = segm_csv_komorka( $w['kategoria_ai'] ?? '' );
			$wiersz[] = empty( $w['roznica'] ) ? '' : 'ROZNICA';
		}
		fputcsv( $out, $wiersz, ',', '"', '\\' );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
