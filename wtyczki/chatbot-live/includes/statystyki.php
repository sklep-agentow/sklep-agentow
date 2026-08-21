<?php
/**
 * Statystyki i RAPORT LUK: ile pytań przyszło, na ile bot odpowiedział,
 * ile skończyło się zbiórką kontaktu — i czego botowi brakuje w bazie.
 *
 * Ten plik ma DWIE warstwy i celowo je rozdziela:
 *   1. liczenie i grupowanie (funkcje bez echo) — da się je odpalić z wp-cli
 *      i sprawdzić testem po stronie PHP,
 *   2. rysowanie strony panelu (chatl_strona_statystyki).
 * Bez tego rozdziału jedynym sposobem sprawdzenia liczb byłoby czytanie HTML-a
 * przeglądarką — a wtedy test mierzy układ strony, nie arytmetykę.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Ile pytań bez odpowiedzi wciągamy do pamięci na potrzeby grupowania.
 * Grupowanie porównuje każdą grupę z każdą klastrą, więc koszt rośnie
 * szybciej niż liniowo — na stronie panelu nie wolno tego puścić bez sufitu.
 * Sufit NIE JEST cichy: gdy pytań jest więcej, strona pisze wprost, ile
 * wzięła i ile było wszystkich (patrz 'obciete' w chatl_luki_okresu).
 */
const CHATL_LUKI_LIMIT = 2000;

/* Ile grup luk pokazuje strona (reszta jest policzona, tylko nie wypisana). */
const CHATL_LUKI_POKAZ = 25;

/*
 * Próg scalania dwóch grup pytań, w procentach wspólnych rdzeni.
 * 50% = połowa rdzeni musi być wspólna. Miara to Jaccard (część wspólna
 * podzielona przez sumę zbiorów), więc próg działa tak samo dla pytań
 * krótkich i długich — inaczej dwuwyrazowe pytania scalałyby się ze wszystkim.
 */
const CHATL_LUKI_PROG = 50;

/*
 * Do ilu znaków skracamy słowo, szukając jego rdzenia PRZY GRUPOWANIU.
 * Polski odmienia końcówki, nie początki: „kosztuje"/„koszt"/„kosztów" mają
 * wspólny początek, a jako całe słowa nie mają ze sobą NIC wspólnego. Bez tego
 * kroku grupowanie po polsku praktycznie nie działa (sprawdzone: „ile kosztuje
 * dostawa" i „koszt dostawy" na całych słowach dają 0%).
 *
 * Cztery, nie pięć: przy pięciu „firmę" i „firmy" to wciąż dwa różne rdzenie
 * (odmiana siedzi dokładnie na piątym znaku), więc „Czy wystawiacie fakturę na
 * firmę zagraniczną?" i „faktura dla firmy z zagranicy" rozjeżdżały się na dwa
 * tematy. Ryzyko sklejenia przypadkowej pary słów o wspólnych czterech znakach
 * jest tu małe, bo próg dotyczy CAŁEGO zbioru rdzeni pytania, a nie jednej pary.
 */
const CHATL_LUKI_RDZEN = 4;

/*
 * Rdzeń do PODPOWIEDZI słów kluczowych jest DŁUŻSZY niż do grupowania — bo
 * trafia w inne miejsce i ma inny koszt pomyłki. Grupowanie tylko układa listę
 * dla właściciela; podpowiedź ląduje w bazie Q&A i zaczyna dopasowywać
 * odpowiedzi gościom. Wariant „kosz" łapałby „koszulę" i „kosz na śmieci",
 * a „koszt" łapie „koszt", „koszty", „kosztuje" i „kosztów" — i nic więcej.
 */
const CHATL_SUGESTIA_RDZEN = 5;

/**
 * Znaki diakrytyczne → ASCII. Gość pisze „cennik" i „cenník" i „CENNIK",
 * a to jedno pytanie; bez zdjęcia ogonków byłyby trzy osobne grupy.
 *
 * @param string $tekst Tekst wejściowy.
 * @return string
 */
function chatl_bez_ogonkow( $tekst ) {
	$mapa = array(
		'ą' => 'a',
		'ć' => 'c',
		'ę' => 'e',
		'ł' => 'l',
		'ń' => 'n',
		'ó' => 'o',
		'ś' => 's',
		'ź' => 'z',
		'ż' => 'z',
	);
	return strtr( (string) $tekst, $mapa );
}

/**
 * Postać pytania, po której porównujemy: małe litery, bez ogonków,
 * bez interpunkcji, pojedyncze spacje.
 *
 * @param string $pytanie Surowe pytanie gościa.
 * @return string Pusty string, gdy po oczyszczeniu nie zostało nic.
 */
function chatl_normalizuj_pytanie( $pytanie ) {
	$t = chatl_bez_ogonkow( mb_strtolower( trim( (string) $pytanie ) ) );
	$t = preg_replace( '/[^a-z0-9 ]+/u', ' ', $t );
	$t = preg_replace( '/\s+/u', ' ', (string) $t );
	return trim( (string) $t );
}

/**
 * Słowa, które w pytaniu nic nie znaczą — sama uprzejmość i szkielet zdania.
 *
 * Lista jest KRÓTKA z rozmysłem: każde wyrzucone słowo to ryzyko, że dwa
 * różne pytania zostaną uznane za jedno. Słowa krótsze niż 4 znaki i tak
 * odpadają w chatl_rdzenie_pytania, więc nie ma ich tutaj.
 *
 * @return array
 */
function chatl_slowa_puste() {
	return array(
		'dzien',
		'dobry',
		'witam',
		'prosze',
		'czesc',
		'jaki',
		'jaka',
		'jakie',
		'gdzie',
		'kiedy',
		'ktory',
		'ktora',
		'ktore',
		'ktorej',
		'jest',
		'sacie',
		'macie',
		'mozecie',
		'mozna',
		'moge',
		'chcialbym',
		'chcialabym',
		'chce',
		'wasz',
		'wasza',
		'wasze',
		'waszych',
		'moje',
		'moja',
		'bardzo',
		'takze',
		'jeszcze',
		'moze',
		'sie',
		'tego',
		'jakos',
		'ewentualnie',
		// Spójniki: pokazał je test przeglądarkowy — „oraz" wylądowało
		// w podpowiedzi słów kluczowych, a jako wariant dopasowania łapałoby
		// każde pytanie ze spójnikiem, czyli praktycznie wszystkie.
		'oraz',
		'albo',
		'czyli',
		'przez',
		'jesli',
		'tylko',
		'zeby',
	);
}

/**
 * Rdzenie znaczących słów pytania — to po nich porównujemy pytania.
 *
 * @param string $znormalizowane Wynik chatl_normalizuj_pytanie().
 * @param int    $dlugosc         Do ilu znaków skracać słowo. Domyślnie rdzeń
 *                                grupowania; podpowiedź słów kluczowych podaje
 *                                własną, dłuższą wartość (patrz komentarz przy
 *                                CHATL_SUGESTIA_RDZEN).
 * @return array Unikalne rdzenie (mogą być puste, gdy pytanie to same
 *               krótkie słówka — wtedy grupujemy tylko po dokładnej równości).
 */
function chatl_rdzenie_pytania( $znormalizowane, $dlugosc = CHATL_LUKI_RDZEN ) {
	$puste   = chatl_slowa_puste();
	$rdzenie = array();
	foreach ( explode( ' ', (string) $znormalizowane ) as $slowo ) {
		if ( mb_strlen( $slowo ) < 4 || in_array( $slowo, $puste, true ) ) {
			continue;
		}
		$rdzenie[] = mb_substr( $slowo, 0, max( 1, (int) $dlugosc ) );
	}
	return array_values( array_unique( $rdzenie ) );
}

/**
 * Podobieństwo dwóch pytań: Jaccard na rdzeniach, w procentach.
 *
 * @param array $a Rdzenie pierwszego pytania.
 * @param array $b Rdzenie drugiego pytania.
 * @return int 0–100. Dwa zbiory puste => 0 (nie „100%": brak rdzeni znaczy
 *             „nie wiem", a nie „to samo pytanie").
 */
function chatl_podobienstwo_pytan( $a, $b ) {
	if ( ! $a || ! $b ) {
		return 0;
	}
	// Dzielenie jest bezpieczne bez dodatkowej straży: oba zbiory są tu już
	// niepuste (sprawdzone wyżej), więc ich suma ma co najmniej jeden element.
	$wspolne = count( array_intersect( $a, $b ) );
	$suma    = count( array_unique( array_merge( $a, $b ) ) );
	return (int) round( 100 * $wspolne / $suma );
}

/**
 * Grupuje pytania bez odpowiedzi w tematy.
 *
 * Dwa kroki, w tej kolejności:
 *   1. sklejamy pytania IDENTYCZNE po normalizacji (to gros powtórzeń),
 *   2. sklejamy grupy PODOBNE (wspólne rdzenie ≥ CHATL_LUKI_PROG).
 * Krok 2 jest zachłanny i idzie od grup najliczniejszych — dzięki temu
 * reprezentantem tematu zostaje pytanie, które pada najczęściej, a wynik
 * nie zależy od kolejności wierszy z bazy (przy remisie decyduje alfabet).
 *
 * @param array $wiersze Obiekty/tablice z kluczami 'tresc' i 'utworzono'.
 * @return array Lista grup: pytanie (reprezentant), ile, ostatnie, warianty[].
 */
function chatl_grupuj_pytania( $wiersze ) {
	$kubelki = array();
	foreach ( $wiersze as $w ) {
		$tresc = is_object( $w ) ? $w->tresc : $w['tresc'];
		$kiedy = is_object( $w ) ? $w->utworzono : $w['utworzono'];
		$norma = chatl_normalizuj_pytanie( $tresc );
		if ( '' === $norma ) {
			continue;
		}
		if ( ! isset( $kubelki[ $norma ] ) ) {
			$kubelki[ $norma ] = array(
				'norma'    => $norma,
				'ile'      => 0,
				'ostatnie' => '',
				'formy'    => array(),
				'rdzenie'  => chatl_rdzenie_pytania( $norma ),
			);
		}
		++$kubelki[ $norma ]['ile'];
		$forma = trim( (string) $tresc );
		if ( ! isset( $kubelki[ $norma ]['formy'][ $forma ] ) ) {
			$kubelki[ $norma ]['formy'][ $forma ] = 0;
		}
		++$kubelki[ $norma ]['formy'][ $forma ];
		if ( (string) $kiedy > $kubelki[ $norma ]['ostatnie'] ) {
			$kubelki[ $norma ]['ostatnie'] = (string) $kiedy;
		}
	}

	$kubelki = array_values( $kubelki );
	usort(
		$kubelki,
		function ( $x, $y ) {
			if ( $x['ile'] !== $y['ile'] ) {
				return $y['ile'] - $x['ile'];
			}
			return strcmp( $x['norma'], $y['norma'] );
		}
	);

	$klastry = array();
	foreach ( $kubelki as $kub ) {
		$trafil = false;
		foreach ( $klastry as $i => $kl ) {
			if ( chatl_podobienstwo_pytan( $kl['rdzenie'], $kub['rdzenie'] ) >= CHATL_LUKI_PROG ) {
				$klastry[ $i ]['ile'] += $kub['ile'];
				foreach ( $kub['formy'] as $forma => $ile ) {
					if ( ! isset( $klastry[ $i ]['formy'][ $forma ] ) ) {
						$klastry[ $i ]['formy'][ $forma ] = 0;
					}
					$klastry[ $i ]['formy'][ $forma ] += $ile;
				}
				if ( $kub['ostatnie'] > $klastry[ $i ]['ostatnie'] ) {
					$klastry[ $i ]['ostatnie'] = $kub['ostatnie'];
				}
				$trafil = true;
				break;
			}
		}
		if ( ! $trafil ) {
			// Rdzenie klastra zostają rdzeniami PIERWSZEJ (najliczniejszej)
			// grupy i już się nie zmieniają. Gdyby rosły przy każdym scaleniu,
			// klaster stopniowo pasowałby do wszystkiego — jedna grupa
			// „różne" zamiast tematów.
			$klastry[] = $kub;
		}
	}

	$wynik = array();
	foreach ( $klastry as $kl ) {
		$formy = $kl['formy'];
		arsort( $formy );
		$lista = array();
		foreach ( $formy as $tekst => $ile ) {
			$lista[] = array(
				'tekst' => (string) $tekst,
				'ile'   => (int) $ile,
			);
		}
		$wynik[] = array(
			'pytanie'  => $lista[0]['tekst'],
			'ile'      => (int) $kl['ile'],
			'ostatnie' => $kl['ostatnie'],
			'warianty' => $lista,
		);
	}

	usort(
		$wynik,
		function ( $x, $y ) {
			if ( $x['ile'] !== $y['ile'] ) {
				return $y['ile'] - $x['ile'];
			}
			return strcmp( $y['ostatnie'], $x['ostatnie'] );
		}
	);
	return $wynik;
}

/**
 * Podpowiedź słów kluczowych dla nowej pary Q&A, zrobiona z pytania gościa.
 *
 * To PODPOWIEDŹ, nie gotowiec: zwracamy rdzenie (skrócone słowa), bo dokładnie
 * tak wygląda dobry wpis w tej bazie — silnik dopasowuje podłańcuchy, więc
 * „koszt" łapie i „koszty", i „kosztuje", a całe „kosztuje" nie złapie „koszt".
 *
 * @param string $pytanie Pytanie gościa.
 * @return string Warianty rozdzielone przecinkami (może być pusty string).
 */
function chatl_sugerowane_slowa( $pytanie ) {
	$rdzenie = chatl_rdzenie_pytania( chatl_normalizuj_pytanie( $pytanie ), CHATL_SUGESTIA_RDZEN );
	return implode( ', ', array_slice( $rdzenie, 0, 6 ) );
}

/**
 * Procent z zabezpieczeniem przed dzieleniem przez zero.
 *
 * @param int $licznik   Licznik.
 * @param int $mianownik Mianownik.
 * @return float Zaokrąglone do 1 miejsca; 0.0 gdy mianownik zerowy.
 */
function chatl_procent( $licznik, $mianownik ) {
	if ( (int) $mianownik <= 0 ) {
		return 0.0;
	}
	return round( 100 * (int) $licznik / (int) $mianownik, 1 );
}

/**
 * Granice okresu w czasie LOKALNYM strony.
 *
 * Wiersze zapisujemy przez current_time( 'mysql' ), czyli w strefie strony —
 * więc i granice muszą być lokalne. Liczenie ich na gmdate() przesuwałoby
 * dobę o offset strefy i „dzisiaj" zaczynałoby się np. o 02:00.
 *
 * @param string $od Data YYYY-MM-DD.
 * @param string $do_dnia Data YYYY-MM-DD.
 * @return array [0] => 'YYYY-MM-DD 00:00:00', [1] => 'YYYY-MM-DD 23:59:59'
 */
function chatl_granice_okresu( $od, $do_dnia ) {
	return array( $od . ' 00:00:00', $do_dnia . ' 23:59:59' );
}

/**
 * Liczby zbiorcze okresu.
 *
 * @param string $od Data początkowa YYYY-MM-DD (lokalna).
 * @param string $do_dnia Data końcowa YYYY-MM-DD (lokalna).
 * @return array Klucze: pytania, odpowiedziano, bez_odpowiedzi, nieoznaczone,
 *               z_regul, z_ai, rozmowy, zgloszenia, rozmowy_z_kontaktem,
 *               skutecznosc, konwersja.
 */
function chatl_statystyki_okresu( $od, $do_dnia ) {
	global $wpdb;
	list( $start, $koniec ) = chatl_granice_okresu( $od, $do_dnia );

	$t = $wpdb->prefix . 'chatl_rozmowy';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z $wpdb->prefix, wartości przez prepare().
	$r = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				SUM( rola = 'gosc' ) AS pytania,
				SUM( rola = 'bot' AND trafiono = 1 ) AS odpowiedziano,
				SUM( rola = 'bot' AND trafiono = 0 ) AS bez_odpowiedzi,
				SUM( rola = 'bot' AND trafiono IS NULL ) AS nieoznaczone,
				SUM( rola = 'bot' AND trafiono = 1 AND silnik = 'reguly' ) AS z_regul,
				SUM( rola = 'bot' AND trafiono = 1 AND silnik = 'ai' ) AS z_ai,
				COUNT( DISTINCT sesja ) AS rozmowy
			FROM {$t} WHERE utworzono BETWEEN %s AND %s",
			$start,
			$koniec
		)
	);

	$tz = $wpdb->prefix . 'chatl_zgloszenia';
	$z  = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS zgloszenia, COUNT( DISTINCT NULLIF( sesja, '' ) ) AS rozmowy_z_kontaktem
			FROM {$tz} WHERE utworzono BETWEEN %s AND %s",
			$start,
			$koniec
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$s = array(
		'pytania'             => (int) ( $r->pytania ?? 0 ),
		'odpowiedziano'       => (int) ( $r->odpowiedziano ?? 0 ),
		'bez_odpowiedzi'      => (int) ( $r->bez_odpowiedzi ?? 0 ),
		'nieoznaczone'        => (int) ( $r->nieoznaczone ?? 0 ),
		'z_regul'             => (int) ( $r->z_regul ?? 0 ),
		'z_ai'                => (int) ( $r->z_ai ?? 0 ),
		'rozmowy'             => (int) ( $r->rozmowy ?? 0 ),
		'zgloszenia'          => (int) ( $z->zgloszenia ?? 0 ),
		'rozmowy_z_kontaktem' => (int) ( $z->rozmowy_z_kontaktem ?? 0 ),
	);

	// Mianownikiem skuteczności są WYŁĄCZNIE odpowiedzi z oznaczeniem.
	// Wliczenie wierszy sprzed aktualizacji (trafiono IS NULL) zaniżałoby
	// wynik o dane, o których nic nie wiadomo — panel ma nie zgadywać.
	$s['skutecznosc'] = chatl_procent( $s['odpowiedziano'], $s['odpowiedziano'] + $s['bez_odpowiedzi'] );
	$s['konwersja']   = chatl_procent( $s['rozmowy_z_kontaktem'], $s['rozmowy'] );
	return $s;
}

/**
 * Ruch dzień po dniu (do słupków na stronie).
 *
 * @param string $od Data początkowa.
 * @param string $do_dnia Data końcowa.
 * @return array Wiersze: dzien, pytania, luki.
 */
function chatl_statystyki_dzienne( $od, $do_dnia ) {
	global $wpdb;
	list( $start, $koniec ) = chatl_granice_okresu( $od, $do_dnia );
	$t                      = $wpdb->prefix . 'chatl_rozmowy';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z $wpdb->prefix, wartości przez prepare().
	$wiersze = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE( utworzono ) AS dzien,
				SUM( rola = 'gosc' ) AS pytania,
				SUM( rola = 'bot' AND trafiono = 0 ) AS luki
			FROM {$t} WHERE utworzono BETWEEN %s AND %s
			GROUP BY DATE( utworzono ) ORDER BY dzien ASC",
			$start,
			$koniec
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wiersze;
}

/**
 * Zużycie tokenów AI w okresie (z tabeli licznika, nie z historii rozmów).
 *
 * Rachunek u dostawcy modelu idzie z TOKENÓW, a nie z liczby wywołań — dlatego
 * właściciel musi widzieć tę liczbę obok skuteczności. Odczyt z cache trzymamy
 * osobno, bo jest wielokrotnie tańszy i wrzucenie go do jednej sumy zawyżałoby
 * obraz kosztu.
 *
 * @param string $od      Data początkowa (lokalna).
 * @param string $do_dnia Data końcowa (lokalna).
 * @return array wywolania, tokeny_wejscia, tokeny_wyjscia, tokeny_cache_zapis,
 *               tokeny_cache_odczyt, platne (suma trzech pierwszych rodzajów).
 */
function chatl_statystyki_tokenow( $od, $do_dnia ) {
	global $wpdb;
	$t = $wpdb->prefix . 'chatl_ai_uzycie';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z $wpdb->prefix, wartości przez prepare().
	$r = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				COALESCE( SUM( wywolania ), 0 ) AS wywolania,
				COALESCE( SUM( tokeny_wejscia ), 0 ) AS tokeny_wejscia,
				COALESCE( SUM( tokeny_wyjscia ), 0 ) AS tokeny_wyjscia,
				COALESCE( SUM( tokeny_cache_zapis ), 0 ) AS tokeny_cache_zapis,
				COALESCE( SUM( tokeny_cache_odczyt ), 0 ) AS tokeny_cache_odczyt
			FROM {$t} WHERE dzien BETWEEN %s AND %s",
			$od,
			$do_dnia
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$w           = array(
		'wywolania'           => (int) ( $r['wywolania'] ?? 0 ),
		'tokeny_wejscia'      => (int) ( $r['tokeny_wejscia'] ?? 0 ),
		'tokeny_wyjscia'      => (int) ( $r['tokeny_wyjscia'] ?? 0 ),
		'tokeny_cache_zapis'  => (int) ( $r['tokeny_cache_zapis'] ?? 0 ),
		'tokeny_cache_odczyt' => (int) ( $r['tokeny_cache_odczyt'] ?? 0 ),
	);
	$w['platne'] = $w['tokeny_wejscia'] + $w['tokeny_wyjscia'] + $w['tokeny_cache_zapis'];
	return $w;
}

/**
 * Pytania gości, na które bot NIE odpowiedział — już pogrupowane.
 *
 * @param string $od Data początkowa.
 * @param string $do_dnia Data końcowa.
 * @return array Klucze: grupy (wynik chatl_grupuj_pytania), wszystkich, wzietych, obciete.
 */
function chatl_luki_okresu( $od, $do_dnia ) {
	global $wpdb;
	list( $start, $koniec ) = chatl_granice_okresu( $od, $do_dnia );
	$t                      = $wpdb->prefix . 'chatl_rozmowy';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwa tabeli z $wpdb->prefix, wartości przez prepare().
	$wszystkich = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE rola = 'gosc' AND trafiono = 0 AND utworzono BETWEEN %s AND %s",
			$start,
			$koniec
		)
	);
	$wiersze    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tresc, utworzono FROM {$t}
			WHERE rola = 'gosc' AND trafiono = 0 AND utworzono BETWEEN %s AND %s
			ORDER BY id DESC LIMIT %d",
			$start,
			$koniec,
			CHATL_LUKI_LIMIT
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array(
		'grupy'      => chatl_grupuj_pytania( $wiersze ),
		'wszystkich' => $wszystkich,
		'wzietych'   => count( $wiersze ),
		'obciete'    => $wszystkich > count( $wiersze ),
	);
}

/* ===================== STRONA PANELU ===================== */

/**
 * Okres wybrany w formularzu — z sensownymi granicami i domyślnymi.
 *
 * @return array Klucze: od, do, zakres ('7'|'30'|'90'|'wlasny').
 */
function chatl_okres_z_zadania() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- formularz tylko czyta dane; brak mutacji.
	$zakres  = isset( $_GET['zakres'] ) ? sanitize_key( wp_unslash( $_GET['zakres'] ) ) : '30';
	$od      = isset( $_GET['od'] ) ? sanitize_text_field( wp_unslash( $_GET['od'] ) ) : '';
	$do_dnia = isset( $_GET['do'] ) ? sanitize_text_field( wp_unslash( $_GET['do'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$dzis = wp_date( 'Y-m-d' );
	if ( 'wlasny' === $zakres && chatl_data_ok( $od ) && chatl_data_ok( $do_dnia ) ) {
		if ( $od > $do_dnia ) {
			list( $od, $do_dnia ) = array( $do_dnia, $od );
		}
		return array(
			'od'     => $od,
			'do'     => $do_dnia,
			'zakres' => 'wlasny',
		);
	}

	$dni = in_array( $zakres, array( '7', '30', '90' ), true ) ? (int) $zakres : 30;
	return array(
		'od'     => wp_date( 'Y-m-d', time() - ( $dni - 1 ) * DAY_IN_SECONDS ),
		'do'     => $dzis,
		'zakres' => (string) $dni,
	);
}

/**
 * Czy to prawdziwa data YYYY-MM-DD (a nie „2026-13-45" ani napis z formularza).
 *
 * @param string $data Wartość z formularza.
 * @return bool
 */
function chatl_data_ok( $data ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $data, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Kafelek z jedną liczbą.
 *
 * @param string $etykieta Podpis.
 * @param string $wartosc  Liczba (już sformatowana).
 * @param string $opis     Zdanie pod liczbą.
 * @param string $kolor    Kolor akcentu (hex).
 * @return void
 */
function chatl_kafelek( $etykieta, $wartosc, $opis, $kolor = '#2271b1' ) {
	?>
	<div style="flex:1 1 180px;min-width:180px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo esc_attr( $kolor ); ?>;padding:12px 16px">
		<div style="color:#50575e;font-size:13px"><?php echo esc_html( $etykieta ); ?></div>
		<div style="font-size:28px;font-weight:600;line-height:1.2"><?php echo esc_html( $wartosc ); ?></div>
		<div style="color:#646970;font-size:12px"><?php echo esc_html( $opis ); ?></div>
	</div>
	<?php
}

/**
 * Strona „Statystyki i luki".
 */
function chatl_strona_statystyki() {
	$okres = chatl_okres_z_zadania();
	$s     = chatl_statystyki_okresu( $okres['od'], $okres['do'] );
	$luki  = chatl_luki_okresu( $okres['od'], $okres['do'] );
	$dni   = chatl_statystyki_dzienne( $okres['od'], $okres['do'] );
	?>
	<div class="wrap">
		<h1>Statystyki i luki</h1>
		<?php chatl_komunikat_ok(); ?>
		<p>Co bot zrobił w wybranym okresie i — najważniejsze — <strong>czego mu brakuje w bazie</strong>.
			Historia starsza niż <?php echo (int) CHATL_RETENCJA_DNI; ?> dni jest kasowana, więc statystyki sięgają najwyżej tak daleko.</p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:16px 0">
			<input type="hidden" name="page" value="chatl-statystyki">
			<label for="chatl-zakres">Okres:</label>
			<select id="chatl-zakres" name="zakres">
				<option value="7" <?php selected( $okres['zakres'], '7' ); ?>>ostatnie 7 dni</option>
				<option value="30" <?php selected( $okres['zakres'], '30' ); ?>>ostatnie 30 dni</option>
				<option value="90" <?php selected( $okres['zakres'], '90' ); ?>>ostatnie 90 dni</option>
				<option value="wlasny" <?php selected( $okres['zakres'], 'wlasny' ); ?>>własny zakres →</option>
			</select>
			<label for="chatl-od" class="screen-reader-text">Data od</label>
			<input type="date" id="chatl-od" name="od" value="<?php echo esc_attr( $okres['od'] ); ?>">
			<label for="chatl-do" class="screen-reader-text">Data do</label>
			<input type="date" id="chatl-do" name="do" value="<?php echo esc_attr( $okres['do'] ); ?>">
			<?php submit_button( 'Pokaż', 'secondary', 'submit', false ); ?>
			<span class="description">Własny zakres działa po wybraniu „własny zakres" z listy.</span>
		</form>

		<p><strong>Okres: <?php echo esc_html( $okres['od'] ); ?> — <?php echo esc_html( $okres['do'] ); ?></strong></p>

		<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:8px">
			<?php
			chatl_kafelek( 'Pytania gości', number_format_i18n( $s['pytania'] ), 'w ' . number_format_i18n( $s['rozmowy'] ) . ' rozmowach' );
			/*
			 * Gdy w okresie nie ma ANI JEDNEJ zmierzonej odpowiedzi (świeża
			 * instalacja albo same wiersze sprzed 1.3.0), kafelek pokazuje
			 * kreskę, a nie „0 (0,0%)". Zero i „nie mierzono" to dwie różne
			 * rzeczy, a akurat ta różnica decyduje, czy właściciel pomyśli,
			 * że kupił zepsutego bota.
			 */
			$zmierzone = $s['odpowiedziano'] + $s['bez_odpowiedzi'];
			chatl_kafelek(
				'Bot odpowiedział',
				$zmierzone > 0 ? number_format_i18n( $s['odpowiedziano'] ) . ' (' . number_format_i18n( $s['skutecznosc'], 1 ) . '%)' : '—',
				$zmierzone > 0 ? 'skuteczność bota' : 'brak zmierzonych odpowiedzi w tym okresie',
				'#00a32a'
			);
			chatl_kafelek(
				'Bez odpowiedzi',
				number_format_i18n( $s['bez_odpowiedzi'] ),
				'tu jest robota do zrobienia',
				'#d63638'
			);
			chatl_kafelek(
				'Zebrane kontakty',
				number_format_i18n( $s['zgloszenia'] ),
				number_format_i18n( $s['rozmowy_z_kontaktem'] ) . ' z ' . number_format_i18n( $s['rozmowy'] ) . ' rozmów (' . number_format_i18n( $s['konwersja'], 1 ) . '%)',
				'#dba617'
			);
			?>
		</div>

		<?php if ( $s['z_ai'] > 0 || $s['z_regul'] > 0 ) : ?>
			<p class="description">Odpowiedzi z reguł: <strong><?php echo esc_html( number_format_i18n( $s['z_regul'] ) ); ?></strong>,
				z trybu AI: <strong><?php echo esc_html( number_format_i18n( $s['z_ai'] ) ); ?></strong>.</p>
		<?php endif; ?>

		<?php $tok = chatl_statystyki_tokenow( $okres['od'], $okres['do'] ); ?>
		<?php if ( $tok['wywolania'] > 0 ) : ?>
			<div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #dba617;padding:12px 16px;margin:8px 0;max-width:900px">
				<strong>Zużycie trybu AI w tym okresie:</strong>
				<?php echo esc_html( number_format_i18n( $tok['wywolania'] ) ); ?> wywołań,
				<strong><?php echo esc_html( number_format_i18n( $tok['platne'] ) ); ?></strong> tokenów płatnych
				(wejście <?php echo esc_html( number_format_i18n( $tok['tokeny_wejscia'] ) ); ?>,
				wyjście <?php echo esc_html( number_format_i18n( $tok['tokeny_wyjscia'] ) ); ?>,
				zapis do cache <?php echo esc_html( number_format_i18n( $tok['tokeny_cache_zapis'] ) ); ?>).
				<?php if ( $tok['tokeny_cache_odczyt'] > 0 ) : ?>
					Odczyt z cache: <?php echo esc_html( number_format_i18n( $tok['tokeny_cache_odczyt'] ) ); ?> tokenów — liczony osobno, bo jest wielokrotnie tańszy.
				<?php endif; ?>
				<p class="description" style="margin:6px 0 0">
					<strong>To tokeny, a nie wywołania, tworzą rachunek u dostawcy modelu.</strong>
					Im większa baza Q&amp;A, tym droższe pojedyncze pytanie — cała aktywna baza idzie w instrukcji przy każdym wywołaniu.
					Dobowy limit tokenów ustawisz w <a href="<?php echo esc_url( admin_url( 'admin.php?page=chatl-ustawienia' ) ); ?>">Ustawieniach</a>.
					Aktualne stawki sprawdzisz u dostawcy — wtyczka nie podaje kwot, bo cennik się zmienia, a ona nie ma jak go odświeżyć.
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $s['nieoznaczone'] > 0 ) : ?>
			<div class="notice notice-info inline" style="margin:8px 0"><p>
				<strong><?php echo esc_html( number_format_i18n( $s['nieoznaczone'] ) ); ?></strong> odpowiedzi w tym okresie pochodzi sprzed aktualizacji do wersji
				<?php echo esc_html( CHATL_WERSJA ); ?> i <strong>nie ma znacznika</strong>, czy bot trafił. Nie są wliczone w skuteczność —
				wcześniejsze wersje tej informacji nie zapisywały, a panel nie zgaduje. Kolejne rozmowy liczą się już normalnie.
			</p></div>
		<?php endif; ?>

		<h2>Ruch dzień po dniu</h2>
		<?php if ( ! $dni ) : ?>
			<p>Brak rozmów w tym okresie.</p>
		<?php else : ?>
			<?php
			$maks = 1;
			foreach ( $dni as $d ) {
				$maks = max( $maks, (int) $d->pytania );
			}
			?>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th style="width:110px">Dzień</th><th style="width:90px">Pytań</th><th style="width:110px">Bez odpowiedzi</th><th>Ruch</th></tr></thead>
				<tbody>
				<?php foreach ( $dni as $d ) : ?>
					<?php
					// sprintf, a nie rzutowanie: szerokość idzie do CSS, więc musi być
					// zawsze zapisana tak samo (kropka dziesiętna, jedno miejsce),
					// niezależnie od ustawienia precision w php.ini.
					$sz_all = sprintf( '%.1f', chatl_procent( (int) $d->pytania, $maks ) );
					$sz_lk  = sprintf( '%.1f', chatl_procent( (int) $d->luki, $maks ) );
					?>
					<tr>
						<td><?php echo esc_html( $d->dzien ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $d->pytania ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $d->luki ) ); ?></td>
						<td>
							<div style="background:#2271b1;height:12px;width:<?php echo esc_attr( $sz_all ); ?>%;min-width:1px" title="pytania"></div>
							<div style="background:#d63638;height:6px;width:<?php echo esc_attr( $sz_lk ); ?>%;min-width:0" title="bez odpowiedzi"></div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Niebieski pasek = wszystkie pytania dnia, czerwony pod nim = te bez odpowiedzi. Skala względem najbardziej ruchliwego dnia okresu.</p>
		<?php endif; ?>

		<h2>Czego botowi brakuje — najczęstsze pytania bez odpowiedzi</h2>
		<p>Pytania gości, na które bot <strong>nie miał odpowiedzi</strong>, zebrane w tematy. Przycisk obok przenosi do bazy Q&amp;A
			z <strong>wpisanym już pytaniem</strong> i podpowiedzianymi słowami kluczowymi — zostaje dopisać odpowiedź.</p>
		<?php if ( $luki['obciete'] ) : ?>
			<div class="notice notice-warning inline" style="margin:8px 0"><p>
				Pytań bez odpowiedzi w tym okresie jest <strong><?php echo esc_html( number_format_i18n( $luki['wszystkich'] ) ); ?></strong>;
				pogrupowano <strong><?php echo esc_html( number_format_i18n( $luki['wzietych'] ) ); ?></strong> najnowszych
				(limit <?php echo (int) CHATL_LUKI_LIMIT; ?>, żeby strona panelu się nie zapchała). Zawęź okres, żeby zobaczyć resztę.
			</p></div>
		<?php endif; ?>

		<?php
		/*
		 * Stronicowanie luk dzieje się NAD JUŻ POGRUPOWANĄ tablicą, a nie w SQL —
		 * grupowanie tematów jest robotą PHP (rdzenie słów, próg podobieństwa),
		 * więc numer strony nie ma jak wejść do zapytania. Twardy sufit
		 * CHATL_LUKI_LIMIT na wiersze WEJŚCIOWE zostaje bez zmian: to osobna,
		 * świadoma granica wydajności, opisana w komunikacie wyżej, a nie ta
		 * sama sprawa co brak strony drugiej.
		 */
		$luki_stron  = max( 1, (int) ceil( count( $luki['grupy'] ) / CHATL_LUKI_POKAZ ) );
		$luki_strona = min( chatl_numer_strony(), $luki_stron );
		$luki_offset = ( $luki_strona - 1 ) * CHATL_LUKI_POKAZ;
		?>
		<table class="widefat striped">
			<thead><tr><th style="width:70px">Pytań</th><th>Temat (pytanie gościa)</th><th style="width:150px">Ostatnio</th><th style="width:210px">Akcja</th></tr></thead>
			<tbody>
			<?php if ( ! $luki['grupy'] ) : ?>
				<tr><td colspan="4">Brak pytań bez odpowiedzi w tym okresie — albo baza Q&amp;A jest kompletna, albo w tym okresie nikt nie pytał.</td></tr>
			<?php endif; ?>
			<?php foreach ( array_slice( $luki['grupy'], $luki_offset, CHATL_LUKI_POKAZ ) as $g ) : ?>
				<?php
				$link = add_query_arg(
					array(
						'page'         => 'chatl-qa',
						'nowe_pytanie' => rawurlencode( $g['pytanie'] ),
						'nowe_slowa'   => rawurlencode( chatl_sugerowane_slowa( $g['pytanie'] ) ),
					),
					admin_url( 'admin.php' )
				);
				?>
				<tr>
					<td><strong><?php echo esc_html( number_format_i18n( $g['ile'] ) ); ?></strong></td>
					<td>
						<?php echo esc_html( $g['pytanie'] ); ?>
						<?php if ( count( $g['warianty'] ) > 1 ) : ?>
							<details style="margin-top:4px">
								<summary style="cursor:pointer;color:#50575e">…i <?php echo esc_html( number_format_i18n( count( $g['warianty'] ) - 1 ) ); ?> innych sformułowań w tym temacie</summary>
								<ul style="margin:6px 0 0 18px;list-style:disc">
									<?php foreach ( array_slice( $g['warianty'], 1, 20 ) as $w ) : ?>
										<li><?php echo esc_html( $w['tekst'] ); ?> <span class="description">× <?php echo esc_html( number_format_i18n( $w['ile'] ) ); ?></span></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $g['ostatnie'] ); ?></td>
					<td><a class="button button-primary" href="<?php echo esc_url( $link ); ?>">Dopisz odpowiedź →</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		chatl_stronicowanie(
			count( $luki['grupy'] ),
			CHATL_LUKI_POKAZ,
			$luki_strona,
			array_filter(
				array(
					'page'   => 'chatl-statystyki',
					// Okres bierzemy z $okres, a nie wprost z adresu: to jest ta sama
					// wartość PO walidacji (chatl_okres_z_zadania), więc odsyłacze stron
					// nie przeniosą dalej daty, którą strona i tak odrzuciła.
					'zakres' => $okres['zakres'],
					'od'     => 'wlasny' === $okres['zakres'] ? $okres['od'] : '',
					'do'     => 'wlasny' === $okres['zakres'] ? $okres['do'] : '',
				)
			)
		);
		?>
		<?php if ( count( $luki['grupy'] ) > CHATL_LUKI_POKAZ ) : ?>
			<p class="description">Tematy są posortowane od najczęstszych — pierwsza strona to te, na których odpowiedź daje najwięcej.
				Dalsze strony to zwykle ogon pojedynczych pytań.</p>
		<?php endif; ?>
		<p class="description">Tematy sklejamy po wspólnych rdzeniach słów (próg <?php echo (int) CHATL_LUKI_PROG; ?>%), bo polski odmienia końcówki:
			„ile kosztuje dostawa" i „koszt dostawy" to dla właściciela jedno pytanie. Rozwiń „inne sformułowania", żeby zobaczyć, co dokładnie wpadło do tematu.</p>
	</div>
	<?php
}
