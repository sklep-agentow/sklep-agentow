<?php
/**
 * Prawdziwa skrzynka: konfiguracja IMAP, diagnostyka środowiska, przebieg
 * segregacji na żywej poczcie (domyślnie NA SUCHO) i cofanie ostatniego
 * przebiegu.
 *
 * Plik ładowany ZAWSZE, nie tylko w adminie: zadanie cykliczne WP-Cron chodzi
 * poza panelem (wp-cron.php), więc kod przebiegu musi tam być widoczny.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nazwa stałej z wp-config.php, która ma pierwszeństwo nad hasłem z bazy.
 */
const SEGM_STALA_HASLA = 'SEGM_IMAP_HASLO';

/**
 * Ile bajtów treści maila pobieramy do dopasowania reguł „treść zawiera".
 */
const SEGM_SKRZ_TRESC_BAJTOW = 4096;

/**
 * Twardy limit czasu jednego przebiegu (sekundy). Po jego przekroczeniu
 * przebieg kończy się uczciwie („zrobiono X z Y"), zamiast zostać ucięty
 * w połowie przez limit czasu żądania na hostingu.
 */
const SEGM_SKRZ_LIMIT_CZASU = 20;

/**
 * Limit czasu pojedynczej operacji sieciowej (sekundy).
 */
const SEGM_SKRZ_LIMIT_GNIAZDA = 15;

/**
 * Ustawienia skrzynki z wartościami domyślnymi.
 *
 * ⭐ 'tryb' => 'na_sucho' to WYMÓG, nie preferencja: dopóki właściciel
 * świadomie nie przełączy, wtyczka pokazuje, co ZROBIŁABY, i nie rusza ani
 * jednej wiadomości.
 *
 * @return array<string, mixed>
 */
function segm_skrzynka_ustawienia() {
	$domyslne = array(
		'host'                 => '',
		'port'                 => 993,
		'szyfrowanie'          => 'ssl',
		'login'                => '',
		'folder_zrodlowy'      => 'INBOX',
		'prefiks'              => 'Segregator',
		'limit'                => 50,
		'dopasuj_tresc'        => 1,
		'tylko_nieprzeczytane' => 0,
		'ruszaj_reszte'        => 0,
		'ufaj_bez_cert'        => 0,
		'tryb'                 => 'na_sucho',
		'czestotliwosc'        => 'wylaczone',
	);
	$zapisane = get_option( 'segm_skrzynka', array() );
	if ( ! is_array( $zapisane ) ) {
		$zapisane = array();
	}
	return array_merge( $domyslne, $zapisane );
}

/**
 * Czy skrzynka jest w ogóle skonfigurowana (host + login + hasło).
 *
 * @return bool
 */
function segm_skrzynka_skonfigurowana() {
	return array() === segm_skrzynka_czego_brakuje();
}

/**
 * Czego brakuje do połączenia — po nazwach pól z formularza.
 *
 * JEDNO źródło prawdy: „skrzynka gotowa" to po prostu pusta lista braków.
 * Dwie funkcje sprawdzające te same trzy warunki osobno rozjechałyby się przy
 * pierwszym dołożonym polu — panel mówiłby „brakuje X", a przycisk i tak byłby
 * aktywny (albo odwrotnie).
 *
 * @return array<int, string> Puste = wszystko, czego trzeba, jest zapisane.
 */
function segm_skrzynka_czego_brakuje() {
	$ust   = segm_skrzynka_ustawienia();
	$braki = array();
	if ( '' === trim( (string) $ust['host'] ) ) {
		$braki[] = 'serwer IMAP';
	}
	if ( '' === trim( (string) $ust['login'] ) ) {
		$braki[] = 'login';
	}
	if ( '' === segm_skrzynka_haslo() ) {
		$braki[] = 'hasło aplikacji';
	}
	return $braki;
}

/* ===================== HASŁO ===================== */

/**
 * Czy hasło podano stałą SEGM_IMAP_HASLO w wp-config.php.
 *
 * Stała MA PIERWSZEŃSTWO nad bazą — dokładnie jak klucz API w ChatBocie.
 * Hasło do CUDZEJ skrzynki w bazie WordPressa wyjeżdża z każdą kopią
 * zapasową bazy i z każdym eksportem na środowisko testowe; hasło w
 * wp-config.php zostaje na serwerze.
 *
 * ⛔ Świadomie NIE szyfrujemy hasła w bazie: klucz szyfrujący leżałby w tym
 * samym WordPressie, obok danych — to teatr, nie zabezpieczenie.
 *
 * @return bool
 */
function segm_skrzynka_haslo_ze_stalej() {
	return defined( SEGM_STALA_HASLA ) && '' !== trim( (string) constant( SEGM_STALA_HASLA ) );
}

/**
 * Hasło aplikacji do skrzynki: stała z wp-config.php, a jeśli jej nie ma —
 * opcja (autoload=no).
 *
 * @return string
 */
function segm_skrzynka_haslo() {
	if ( segm_skrzynka_haslo_ze_stalej() ) {
		return trim( (string) constant( SEGM_STALA_HASLA ) );
	}
	return (string) get_option( 'segm_imap_haslo', '' );
}

/**
 * Zapis/kasowanie hasła z wymuszonym autoload=no.
 *
 * Gdy hasło jest w konfiguracji serwera, panel go nie nadpisuje — inaczej
 * wpis w formularzu po cichu wygrywałby ze stałą wbrew deklaracji.
 *
 * @param string $haslo Hasło aplikacji ('' = skasuj).
 * @return bool True, gdy po operacji w bazie leży to, co miało tam trafić.
 */
function segm_skrzynka_zapisz_haslo( $haslo ) {
	if ( segm_skrzynka_haslo_ze_stalej() ) {
		return true;
	}
	if ( '' === $haslo ) {
		delete_option( 'segm_imap_haslo' );
		return '' === (string) get_option( 'segm_imap_haslo', '' );
	}
	if ( false === get_option( 'segm_imap_haslo' ) ) {
		add_option( 'segm_imap_haslo', $haslo, '', false );
	} else {
		update_option( 'segm_imap_haslo', $haslo );
	}
	return (string) get_option( 'segm_imap_haslo', '' ) === $haslo;
}

/* ===================== ZGODA NA REALNE PRZENOSZENIE ===================== */

/**
 * Świadome włączenie realnego przenoszenia (data + login do okazania).
 *
 * @return array{kiedy: string, kto: string}|null Null = zgody NIE MA.
 */
function segm_zgoda_przenoszenia() {
	$zapisana = get_option( 'segm_zgoda_przenoszenie', array() );
	if ( ! is_array( $zapisana ) || empty( $zapisana['kiedy'] ) ) {
		return null;
	}
	return array(
		'kiedy' => (string) $zapisana['kiedy'],
		'kto'   => isset( $zapisana['kto'] ) ? (string) $zapisana['kto'] : 'nieznany',
	);
}

/**
 * Czy wolno w ogóle ruszyć wiadomości na serwerze.
 *
 * @return bool
 */
function segm_przenoszenie_dozwolone() {
	$ust = segm_skrzynka_ustawienia();
	return 'na_zywo' === $ust['tryb'] && null !== segm_zgoda_przenoszenia();
}

/* ===================== DIAGNOSTYKA ŚRODOWISKA ===================== */

/**
 * Co ten PHP potrafi, zanim ktokolwiek kliknie „Sprawdź połączenie".
 *
 * Wtyczka ma POWIEDZIEĆ, czego brakuje, a nie paść z białym ekranem.
 *
 * @return array<int, array{nazwa: string, stan: string, opis: string, znacznik?: string}>
 *         stan: 'ok' | 'uwaga' | 'blad'; 'znacznik' (opcjonalny) zastępuje
 *         domyślny napis w kolumnie stanu, gdy „✔ jest" byłoby mylące.
 */
function segm_skrzynka_diagnostyka() {
	$wynik = array();

	$gniazda = function_exists( 'stream_socket_client' );
	$wynik[] = array(
		'nazwa' => 'Połączenia sieciowe (stream_socket_client)',
		'stan'  => $gniazda ? 'ok' : 'blad',
		'opis'  => $gniazda
			? 'Dostępne — wtyczka ma czym otworzyć połączenie z serwerem poczty.'
			: 'ZABLOKOWANE dyrektywą disable_functions w php.ini. Bez tej funkcji wtyczka nie połączy się z żadną skrzynką — poproś hostingodawcę o odblokowanie stream_socket_client.',
	);

	$openssl = extension_loaded( 'openssl' );
	$wynik[] = array(
		'nazwa' => 'Szyfrowanie (rozszerzenie openssl)',
		'stan'  => $openssl ? 'ok' : 'blad',
		'opis'  => $openssl
			? 'Dostępne — połączenie SSL/TLS i STARTTLS zadziała.'
			: 'BRAK. Bez OpenSSL da się połączyć tylko bez szyfrowania, czyli z hasłem lecącym otwartym tekstem — nie rób tego przez internet. Poproś hostingodawcę o rozszerzenie openssl.',
	);

	$mbstring = function_exists( 'mb_convert_encoding' ) && in_array( 'UTF7-IMAP', mb_list_encodings(), true );
	$wynik[]  = array(
		'nazwa' => 'Nazwy folderów (mbstring, kodowanie UTF7-IMAP)',
		'stan'  => $mbstring ? 'ok' : 'uwaga',
		'opis'  => $mbstring
			? 'Dostępne — foldery z polskimi znakami będą nazwane poprawnie.'
			: 'Brak obsługi kodowania UTF7-IMAP w mbstring. Foldery z polskimi znakami mogą mieć na serwerze przekręcone nazwy — używaj nazw kategorii bez polskich liter.',
	);

	// ⛔ Sprawdzamy rozszerzenie imap i mówimy o nim WPROST.
	// Ono NIE jest nam potrzebne (mamy własny klient po gnieździe), ale
	// właściciel ma prawo wiedzieć, dlaczego wtyczka działa bez niego.
	$ext_imap = extension_loaded( 'imap' );
	$wynik[]  = array(
		'nazwa'    => 'Rozszerzenie PHP „imap" (NIEwymagane)',
		'stan'     => 'ok',
		// Kolumna stanu mówiła „✔ jest", a opis obok zaczynał się od
		// „Nieobecne" — kupujący czytał to jako błąd w tabelce. Kolumna ma
		// mówić o STANIE RZECZY, a nie tylko „u nas wszystko gra": własny
		// znacznik rozstrzyga, co widać, i zgadza się z pierwszym słowem opisu.
		'znacznik' => $ext_imap ? '✔ jest (nieużywane)' : '— nie dotyczy',
		'opis'     => $ext_imap
			? 'Obecne, ale wtyczka z niego nie korzysta — rozmawia z serwerem protokołem po gnieździe, więc działa tak samo bez niego.'
			: 'Nieobecne — i dobrze, bo wtyczka go NIE potrzebuje. Podręcznik PHP (php.net/manual/en/imap.installation.php): „This extension has been moved to the PECL repository and is no longer bundled with PHP as of PHP 8.4.0". Wtyczka oparta na funkcjach imap_* przestałaby u Ciebie działać po aktualizacji PHP — ta jest oparta na własnym kliencie IMAP.',
	);

	$cron    = ! defined( 'DISABLE_WP_CRON' ) || ! constant( 'DISABLE_WP_CRON' );
	$wynik[] = array(
		'nazwa' => 'Zadania cykliczne WordPressa (WP-Cron)',
		'stan'  => $cron ? 'ok' : 'uwaga',
		'opis'  => $cron
			? 'Włączone — segregacja w tle ruszy sama.'
			: 'Wyłączone stałą DISABLE_WP_CRON w wp-config.php. Segregacja cykliczna ruszy tylko wtedy, gdy Twój hosting woła wp-cron.php własnym harmonogramem (cron systemowy). Przycisk „Uruchom teraz" działa niezależnie.',
	);

	return $wynik;
}

/**
 * Czy diagnostyka wykryła coś, co uniemożliwia połączenie.
 *
 * @return bool
 */
function segm_skrzynka_srodowisko_pada() {
	foreach ( segm_skrzynka_diagnostyka() as $wpis ) {
		if ( 'blad' === $wpis['stan'] && 0 === strpos( $wpis['nazwa'], 'Połączenia sieciowe' ) ) {
			return true;
		}
	}
	return false;
}

/* ===================== POŁĄCZENIE ===================== */

/**
 * Nawiązuje i loguje połączenie wg zapisanych ustawień.
 *
 * @return array{klient: Segm_Imap|null, blad: string, dziennik: array<int, string>}
 */
function segm_skrzynka_polacz() {
	$ust = segm_skrzynka_ustawienia();
	if ( ! segm_skrzynka_skonfigurowana() ) {
		return array(
			'klient'   => null,
			'blad'     => 'Skrzynka nie jest jeszcze skonfigurowana — uzupełnij serwer, login i hasło aplikacji.',
			'dziennik' => array(),
		);
	}

	$klient = new Segm_Imap( SEGM_SKRZ_LIMIT_GNIAZDA );
	if ( ! $klient->polacz( (string) $ust['host'], (int) $ust['port'], (string) $ust['szyfrowanie'], ! empty( $ust['ufaj_bez_cert'] ) ) ) {
		return array(
			'klient'   => null,
			'blad'     => $klient->blad(),
			'dziennik' => $klient->dziennik(),
		);
	}
	if ( ! $klient->zaloguj( (string) $ust['login'], segm_skrzynka_haslo() ) ) {
		$blad     = $klient->blad();
		$dziennik = $klient->dziennik();
		$klient->rozlacz();
		return array(
			'klient'   => null,
			'blad'     => $blad,
			'dziennik' => $dziennik,
		);
	}
	return array(
		'klient'   => $klient,
		'blad'     => '',
		'dziennik' => $klient->dziennik(),
	);
}

/**
 * „Sprawdź połączenie": łączy się NAPRAWDĘ i mówi, ile wiadomości widzi.
 *
 * @return array{ok: bool, komunikat: string, wiadomosci: int, foldery: array<int, string>, moznosci: array<int, string>, dziennik: array<int, string>}
 */
function segm_skrzynka_sprawdz() {
	$ust        = segm_skrzynka_ustawienia();
	$pusty      = array(
		'ok'         => false,
		'komunikat'  => '',
		'wiadomosci' => 0,
		'foldery'    => array(),
		'moznosci'   => array(),
		'dziennik'   => array(),
	);
	$polaczenie = segm_skrzynka_polacz();
	if ( null === $polaczenie['klient'] ) {
		$pusty['komunikat'] = $polaczenie['blad'];
		$pusty['dziennik']  = $polaczenie['dziennik'];
		return $pusty;
	}

	$klient = $polaczenie['klient'];
	$zrodlo = Segm_Imap::do_utf7( (string) $ust['folder_zrodlowy'] );
	// EXAMINE, nie SELECT: samo sprawdzenie połączenia nie ma prawa niczego
	// zmienić w skrzynce, nawet znacznika „przeczytane".
	$stan = $klient->otworz( $zrodlo, true );
	if ( null === $stan ) {
		$pusty['komunikat'] = $klient->blad();
		$pusty['dziennik']  = $klient->dziennik();
		$klient->rozlacz();
		return $pusty;
	}

	$foldery = $klient->foldery();
	$nazwy   = array();
	foreach ( (array) $foldery as $folder ) {
		$nazwy[] = $folder['nazwa_czytelna'];
	}

	$wynik = array(
		'ok'         => true,
		'komunikat'  => sprintf(
			'Połączono z %s jako %s. Folder „%s" ma %d wiadomości. Folderów na serwerze: %d.',
			(string) $ust['host'],
			(string) $ust['login'],
			(string) $ust['folder_zrodlowy'],
			(int) $stan['wiadomosci'],
			count( $nazwy )
		),
		'wiadomosci' => (int) $stan['wiadomosci'],
		'foldery'    => $nazwy,
		'moznosci'   => $klient->moznosci(),
		'dziennik'   => $klient->dziennik(),
	);
	$klient->rozlacz();
	return $wynik;
}

/* ===================== NAGŁÓWKI I TREŚĆ ===================== */

/**
 * Wartość nagłówka z surowego bloku nagłówków.
 *
 * Nagłówki bywają ZAWINIĘTE (RFC 5322 3.2.2: kontynuacja zaczyna się od
 * spacji albo tabulatora) — przed szukaniem je rozwijamy, inaczej długi temat
 * urwałby się w połowie.
 *
 * @param string $surowe Surowy blok nagłówków.
 * @param string $nazwa  Nazwa nagłówka.
 * @return string
 */
function segm_skrzynka_naglowek( $surowe, $nazwa ) {
	$rozwiniete = preg_replace( '/\r?\n[ \t]+/', ' ', (string) $surowe );
	if ( ! preg_match( '/^' . preg_quote( $nazwa, '/' ) . ':[ \t]*(.*)$/mi', (string) $rozwiniete, $m ) ) {
		return '';
	}
	return trim( $m[1] );
}

/**
 * Nagłówek zakodowany słowami MIME (=?UTF-8?B?...?=) na czysty UTF-8.
 *
 * @param string $wartosc Wartość nagłówka.
 * @return string
 */
function segm_skrzynka_odkoduj_naglowek( $wartosc ) {
	if ( '' === $wartosc || ! function_exists( 'iconv_mime_decode' ) ) {
		return $wartosc;
	}
	$odkodowane = iconv_mime_decode( $wartosc, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );
	return false === $odkodowane ? $wartosc : $odkodowane;
}

/**
 * Sam adres e-mail z nagłówka From (bez nazwy nadawcy).
 *
 * @param string $od Wartość nagłówka From.
 * @return string
 */
function segm_skrzynka_adres( $od ) {
	if ( preg_match( '/<([^>]+)>/', $od, $m ) ) {
		return trim( $m[1] );
	}
	if ( preg_match( '/([^\s<>,;"]+@[^\s<>,;"]+)/', $od, $m ) ) {
		return trim( $m[1] );
	}
	return trim( $od );
}

/**
 * Ile poziomów zagnieżdżenia MIME rozbieramy (multipart w multipart).
 *
 * Poczta z załącznikiem to zwykle `multipart/mixed`, a w środku
 * `multipart/alternative` — dwa poziomy. Piąty poziom w prawdziwej poczcie
 * nie występuje; limit jest po to, żeby spreparowana wiadomość nie zapętliła
 * rozbioru.
 */
const SEGM_SKRZ_GLEBOKOSC_MIME = 5;

/**
 * Ile części jednej wiadomości oglądamy, zanim przestaniemy szukać tekstu.
 */
const SEGM_SKRZ_CZESCI_MIME = 40;

/**
 * Zestawy znaków, na które wolno przewalutować treść.
 *
 * ⛔ Tu NIE WOLNO użyć `mb_list_encodings()`: ta lista niesie także kodowania
 * TRANSPORTOWE (`BASE64`, `QUOTED-PRINTABLE`, `7BIT`, `UUENCODE`,
 * `HTML-ENTITIES`), a nazwę zestawu podaje NADAWCA w swoim `Content-Type`.
 * Wystarczyło `charset=BASE64`, żeby czytelny tekst przeszedł przez dekoder
 * base64 i wyszedł z niego bajtowy śmieć.
 *
 * @return array<int, string>
 */
function segm_skrzynka_zestawy_znakow() {
	return array(
		'UTF-8',
		'US-ASCII',
		'ISO-8859-1',
		'ISO-8859-2',
		'ISO-8859-3',
		'ISO-8859-4',
		'ISO-8859-5',
		'ISO-8859-6',
		'ISO-8859-7',
		'ISO-8859-8',
		'ISO-8859-9',
		'ISO-8859-10',
		'ISO-8859-13',
		'ISO-8859-14',
		'ISO-8859-15',
		'ISO-8859-16',
		'WINDOWS-1250',
		'WINDOWS-1251',
		'WINDOWS-1252',
		'WINDOWS-1253',
		'WINDOWS-1254',
		'WINDOWS-1255',
		'WINDOWS-1256',
		'WINDOWS-1257',
		'WINDOWS-1258',
		'KOI8-R',
		'KOI8-U',
		'UTF-16',
		'UTF-16BE',
		'UTF-16LE',
		'UTF-32',
		'UTF-32BE',
		'UTF-32LE',
		'EUC-JP',
		'SJIS',
		'ISO-2022-JP',
		'BIG5',
		'GB18030',
		'EUC-KR',
	);
}

/**
 * Wartość parametru z nagłówka typu (`charset`, `boundary`).
 *
 * @param string $naglowek Wartość nagłówka Content-Type.
 * @param string $nazwa    Nazwa parametru.
 * @return string Pusty łańcuch, gdy parametru nie ma.
 */
function segm_skrzynka_parametr_typu( $naglowek, $nazwa ) {
	$wzor = '/;\s*' . preg_quote( $nazwa, '/' ) . '\s*=\s*(?:"([^"]*)"|([^;\s]+))/i';
	if ( ! preg_match( $wzor, ';' . $naglowek, $m ) ) {
		return '';
	}
	return trim( '' !== $m[1] ? $m[1] : ( isset( $m[2] ) ? $m[2] : '' ) );
}

/**
 * Ładunek części rozkodowany wg JEJ WŁASNEGO Content-Transfer-Encoding.
 *
 * ⭐ Base64 bez `array_pop()`: poprzednia wersja odcinała ostatnią linię
 * ZAWSZE — także wtedy, gdy nic nie było ucięte — więc krótki mail bez znaku
 * końca linii tracił swoją JEDYNĄ linię i cała treść znikała. Tu zamiast
 * kasować linię odrzucamy wyłącznie NIEPEŁNĄ czwórkę znaków na końcu: gdy
 * treść przyszła w całości, reszta z dzielenia wynosi zero i nie ginie nic.
 *
 * @param string $cialo     Ładunek części.
 * @param string $kodowanie Wartość Content-Transfer-Encoding części.
 * @return string
 */
function segm_skrzynka_odkoduj_ladunek( $cialo, $kodowanie ) {
	$kodowanie = strtolower( trim( (string) $kodowanie ) );

	if ( 'base64' === $kodowanie ) {
		$czysty = (string) preg_replace( '#[^A-Za-z0-9+/=]#', '', $cialo );
		$reszta = strlen( $czysty ) % 4;
		if ( 0 !== $reszta ) {
			$czysty = substr( $czysty, 0, strlen( $czysty ) - $reszta );
		}
		// Tryb nieścisły (drugi argument false) pomija znaki spoza alfabetu
		// base64 i NIGDY nie zwraca false — dlatego wynik idzie wprost.
		return (string) base64_decode( $czysty, false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- to nie zaciemnianie kodu, tylko odkodowanie treści maila zgodnie z nagłówkiem Content-Transfer-Encoding (RFC 2045).
	}

	if ( 'quoted-printable' === $kodowanie ) {
		return quoted_printable_decode( $cialo );
	}

	return $cialo;
}

/**
 * Treść sprowadzona do UTF-8 wg zestawu znaków TEJ CZĘŚCI.
 *
 * @param string $tekst  Rozkodowany ładunek.
 * @param string $zestaw Nazwa zestawu z nagłówka części.
 * @return string
 */
function segm_skrzynka_na_utf8( $tekst, $zestaw ) {
	$zestaw = strtoupper( trim( (string) $zestaw ) );
	if ( 'UTF8' === $zestaw ) {
		$zestaw = 'UTF-8';
	}
	if ( 'ASCII' === $zestaw ) {
		$zestaw = 'US-ASCII';
	}
	if ( '' === $zestaw || 'UTF-8' === $zestaw || 'US-ASCII' === $zestaw ) {
		return $tekst;
	}
	if ( ! in_array( $zestaw, segm_skrzynka_zestawy_znakow(), true ) ) {
		// Nazwa spoza listy ZESTAWÓW ZNAKÓW: zostawiamy tekst nietknięty.
		// Ostatnia furtka niżej i tak sprowadzi go do poprawnego UTF-8.
		return $tekst;
	}
	$przerobione = mb_convert_encoding( $tekst, 'UTF-8', $zestaw );
	return is_string( $przerobione ) ? $przerobione : $tekst;
}

/**
 * Znaczniki HTML zdjęte tak, żeby NIEDOMKNIĘTY `<` nie zjadał reszty treści.
 *
 * ⛔ Powód, dla którego nie ma tu `wp_strip_all_tags()`: `strip_tags()` traktuje
 * `<` przed literą jako początek znacznika i kasuje wszystko do końca tekstu,
 * gdy domknięcia nie ma. Mail „termin <piatek. Proszę o faktura pro forma"
 * tracił w ten sposób wszystko po słowie „termin" — a człowiek widzi w nim
 * całe zdanie. Tu zdejmujemy WYŁĄCZNIE znaczniki, które naprawdę się domykają.
 *
 * @param string $html Treść części text/html.
 * @return string
 */
function segm_skrzynka_bez_znacznikow( $html ) {
	// Skrypty i style razem z zawartością; `[^<>]*` zamiast `[^>]*?` nie
	// pozwala wzorcowi nawracać po całym tekście przy niedomkniętym `<`.
	$bez = preg_replace( '#<(script|style)\b[^<>]*>.*?</\1\s*>#is', ' ', (string) $html );
	if ( ! is_string( $bez ) ) {
		$bez = (string) $html;
	}
	// Znacznik = `<`, litera, cokolwiek bez nawiasów, `>`. Sam `<` przed
	// literą bez domknięcia zostaje w tekście, bo człowiek też go widzi.
	$bez = preg_replace( '#</?[A-Za-z][^<>]*>#', ' ', $bez );
	if ( ! is_string( $bez ) ) {
		$bez = (string) $html;
	}
	$bez = html_entity_decode( $bez, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$bez = preg_replace( '/[ \t]+/', ' ', $bez );
	return trim( (string) $bez );
}

/**
 * Nagłówki i ładunek jednej części rozdzielone pierwszą pustą linią.
 *
 * @param string $czesc Surowa część spomiędzy granic.
 * @return array{0: string, 1: string} Nagłówki części, ładunek części.
 */
function segm_skrzynka_rozbij_czesc( $czesc ) {
	if ( preg_match( '/\r\n\r\n|\n\n|\r\r/', $czesc, $m, PREG_OFFSET_CAPTURE ) ) {
		$koniec = (int) $m[0][1] + strlen( $m[0][0] );
		return array( substr( $czesc, 0, $koniec ), substr( $czesc, $koniec ) );
	}
	// Brak pustej linii: albo same nagłówki (część ucięta na 4 kB), albo sam
	// ładunek (część bez nagłówków). Rozstrzyga kształt pierwszej linii.
	if ( preg_match( '/^[A-Za-z][A-Za-z0-9\-]*:/', ltrim( $czesc, "\r\n" ) ) ) {
		return array( $czesc, '' );
	}
	return array( '', $czesc );
}

/**
 * Części wiadomości wieloczęściowej wycięte po granicy (RFC 2046 §5.1).
 *
 * @param string $cialo   Ładunek wiadomości wieloczęściowej.
 * @param string $granica Wartość parametru boundary.
 * @return array<int, string>
 */
function segm_skrzynka_czesci_mime( $cialo, $granica ) {
	$znacznik = '--' . $granica;
	$linie    = preg_split( '/\r\n|\n|\r/', $cialo );
	$czesci   = array();
	$biezaca  = null;

	foreach ( (array) $linie as $linia ) {
		$przyciete = rtrim( (string) $linia, " \t" );
		if ( $znacznik . '--' === $przyciete ) {
			if ( null !== $biezaca ) {
				$czesci[] = implode( "\r\n", $biezaca );
			}
			$biezaca = null;
			break;
		}
		if ( $znacznik === $przyciete ) {
			if ( null !== $biezaca ) {
				$czesci[] = implode( "\r\n", $biezaca );
			}
			$biezaca = array();
			if ( count( $czesci ) >= SEGM_SKRZ_CZESCI_MIME ) {
				$biezaca = null;
				break;
			}
			continue;
		}
		if ( null !== $biezaca ) {
			$biezaca[] = $linia;
		}
	}

	if ( null !== $biezaca ) {
		// Fragment urwany na SEGM_SKRZ_TRESC_BAJTOW: ostatnia część nie ma
		// swojej granicy zamykającej, a i tak niesie tekst do dopasowania.
		$czesci[] = implode( "\r\n", $biezaca );
	}

	return $czesci;
}

/**
 * Jedna część MIME sprowadzona do tekstu widzianego przez człowieka.
 *
 * @param string $cialo      Ładunek części (bez jej nagłówków).
 * @param string $naglowki   Nagłówki TEJ części (albo wiadomości, gdy to poziom 0).
 * @param int    $glebokosc  Poziom zagnieżdżenia.
 * @return array{tekst: string, zwykly: bool} `zwykly` = tekst pochodzi z text/plain.
 */
function segm_skrzynka_czesc_tekstem( $cialo, $naglowki, $glebokosc ) {
	$pusto = array(
		'tekst'  => '',
		'zwykly' => false,
	);
	if ( '' === $cialo ) {
		return $pusto;
	}

	$typ       = segm_skrzynka_naglowek( $naglowki, 'Content-Type' );
	$kodowanie = segm_skrzynka_naglowek( $naglowki, 'Content-Transfer-Encoding' );
	$glowny    = strtolower( trim( (string) strtok( $typ, ';' ) ) );

	if ( 0 === strpos( $glowny, 'multipart/' ) && $glebokosc < SEGM_SKRZ_GLEBOKOSC_MIME ) {
		$granica = segm_skrzynka_parametr_typu( $typ, 'boundary' );
		if ( '' !== $granica ) {
			$zwykly = '';
			$bogaty = '';
			foreach ( segm_skrzynka_czesci_mime( $cialo, $granica ) as $czesc ) {
				list( $nag_czesci, $ladunek ) = segm_skrzynka_rozbij_czesc( $czesc );
				$wynik                        = segm_skrzynka_czesc_tekstem( $ladunek, $nag_czesci, $glebokosc + 1 );
				if ( '' === $wynik['tekst'] ) {
					continue;
				}
				if ( $wynik['zwykly'] && '' === $zwykly ) {
					$zwykly = $wynik['tekst'];
				} elseif ( ! $wynik['zwykly'] && '' === $bogaty ) {
					$bogaty = $wynik['tekst'];
				}
			}
			if ( '' !== $zwykly ) {
				return array(
					'tekst'  => $zwykly,
					'zwykly' => true,
				);
			}
			return array(
				'tekst'  => $bogaty,
				'zwykly' => false,
			);
		}
	}

	// Część, która nie jest tekstem (załącznik, obrazek) nie ma czego wnieść
	// do reguły „treść zawiera". Na poziomie 0 typu nieznanego nie odrzucamy:
	// mail bez Content-Type to zwykły tekst (RFC 2045 §5.2).
	if ( $glebokosc > 0 && '' !== $glowny && 0 !== strpos( $glowny, 'text/' ) ) {
		return $pusto;
	}

	$tekst = segm_skrzynka_odkoduj_ladunek( $cialo, $kodowanie );
	$tekst = segm_skrzynka_na_utf8( $tekst, segm_skrzynka_parametr_typu( $typ, 'charset' ) );

	if ( 'text/html' === $glowny ) {
		return array(
			'tekst'  => segm_skrzynka_bez_znacznikow( $tekst ),
			'zwykly' => false,
		);
	}

	return array(
		'tekst'  => trim( $tekst ),
		'zwykly' => true,
	);
}

/**
 * Treść maila sprowadzona do tekstu, na którym da się dopasować reguły.
 *
 * KONTRAKT: oddajemy TEKST, KTÓRY WIDZI CZŁOWIEK w swoim programie pocztowym —
 * pierwsze SEGM_SKRZ_TRESC_BAJTOW bajtów sekcji TEXT rozebrane na części MIME,
 * z których bierzemy `text/plain` (a gdy jej nie ma — `text/html` bez
 * znaczników). Każda część jest odkodowana JEJ WŁASNYM
 * `Content-Transfer-Encoding` i sprowadzona do UTF-8 JEJ WŁASNYM `charset`.
 *
 * ⛔ Granice części i nagłówki części NIE TRAFIAJĄ do wyniku — inaczej reguła
 * „treść zawiera charset" łapałaby każdy wieloczęściowy mail, a reguła na
 * słowo z treści milczałaby przy poczcie z Gmaila, Outlooka i Thunderbirda
 * (`multipart/alternative` to kształt, który wysyła każdy z nich).
 *
 * To nadal NIE jest pełny rozbiór MIME: nie wyciągamy załączników i nie
 * sklejamy części `message/partial`.
 *
 * @param string $surowa    Surowy fragment sekcji TEXT.
 * @param string $naglowki  Surowe nagłówki wiadomości.
 * @return string
 */
function segm_skrzynka_tresc_tekstem( $surowa, $naglowki ) {
	$surowa = (string) $surowa;
	if ( '' === $surowa ) {
		return '';
	}

	$wynik = segm_skrzynka_czesc_tekstem( $surowa, (string) $naglowki, 0 );
	$tekst = $wynik['tekst'];

	if ( ! mb_check_encoding( $tekst, 'UTF-8' ) ) {
		// Ostatnia furtka: cokolwiek przyszlo bez poprawnej deklaracji zestawu,
		// ma wyjsc stad jako poprawny UTF-8 — regula dopasowuje na UTF-8.
		$tekst = mb_convert_encoding( $tekst, 'UTF-8', 'ISO-8859-2' );
	}

	return $tekst;
}

/**
 * Wiadomość z serwera sprowadzona do postaci, którą rozumie silnik reguł.
 *
 * @param Segm_Imap $klient        Połączony klient.
 * @param int       $uid           UID wiadomości.
 * @param bool      $z_trescia     Czy pobrać też początek treści.
 * @return object|null Obiekt z polami nadawca, temat, tresc, message_id, data.
 */
function segm_skrzynka_wiadomosc( $klient, $uid, $z_trescia ) {
	$naglowki = $klient->naglowki( $uid );
	if ( null === $naglowki ) {
		return null;
	}

	$tresc = '';
	if ( $z_trescia ) {
		$tresc = segm_skrzynka_tresc_tekstem( (string) $klient->tresc( $uid, SEGM_SKRZ_TRESC_BAJTOW ), $naglowki );
	}

	$od = segm_skrzynka_odkoduj_naglowek( segm_skrzynka_naglowek( $naglowki, 'From' ) );

	return (object) array(
		'id'                   => (int) $uid,
		'nadawca'              => segm_skrzynka_adres( $od ),
		'nadawca_pelny'        => $od,
		'temat'                => segm_skrzynka_odkoduj_naglowek( segm_skrzynka_naglowek( $naglowki, 'Subject' ) ),
		'tresc'                => $tresc,
		'message_id'           => segm_skrzynka_naglowek( $naglowki, 'Message-ID' ),
		'data'                 => segm_skrzynka_naglowek( $naglowki, 'Date' ),
		'kategoria_oczekiwana' => '',
	);
}

/**
 * Pełna nazwa folderu docelowego dla kategorii (z prefiksem i separatorem
 * hierarchii serwera), już w kodowaniu protokołu.
 *
 * @param Segm_Imap $klient    Połączony klient.
 * @param string    $prefiks   Prefiks folderów z ustawień (może być pusty).
 * @param string    $kategoria Nazwa kategorii.
 * @return string
 */
function segm_skrzynka_folder_dla( $klient, $prefiks, $kategoria ) {
	$prefiks   = trim( (string) $prefiks );
	$kategoria = trim( (string) $kategoria );
	if ( '' === $prefiks ) {
		return Segm_Imap::do_utf7( $kategoria );
	}
	$sep = $klient->pobierz_rozdzielnik();
	if ( '' === $sep ) {
		// Serwer bez hierarchii folderów — sklejamy nazwę jednym myślnikiem,
		// zamiast tworzyć folder z separatorem, którego serwer nie rozumie.
		return Segm_Imap::do_utf7( $prefiks . '-' . $kategoria );
	}
	return Segm_Imap::do_utf7( $prefiks . $sep . $kategoria );
}
