<?php
/**
 * Klient IMAP4rev1 w CZYSTYM PHP: gniazdo TCP + TLS, bez rozszerzenia ext/imap.
 *
 * DLACZEGO własny klient, a nie funkcje imap_*:
 * rozszerzenie IMAP zostało wyjęte z PHP i przeniesione do PECL — cytat
 * z podręcznika (php.net/manual/en/imap.installation.php, „PHP 8.4"):
 * „This extension has been moved to the PECL repository and is no longer
 * bundled with PHP as of PHP 8.4.0". Na typowym hostingu współdzielonym nikt
 * tego nie doinstaluje, a wtyczka ma być produktem, nie życzeniem. Dlatego
 * rozmawiamy z serwerem protokołem, po gnieździe, na tym co PHP ma zawsze.
 * Obecność ext/imap i tak SPRAWDZAMY i pokazujemy w diagnostyce (skrzynka.php).
 *
 * Zakres wg RFC 3501 (IMAP4rev1): CAPABILITY, LOGIN, LIST, SELECT, EXAMINE,
 * CREATE, SUBSCRIBE, UID SEARCH, UID FETCH, UID COPY, UID STORE, LOGOUT
 * + rozszerzenia: MOVE (RFC 6851), UIDPLUS/COPYUID i UID EXPUNGE (RFC 4315),
 * STARTTLS (RFC 3501 6.2.1). Nazwy folderów kodowane zmodyfikowanym UTF-7
 * (RFC 3501 5.1.3) przez mbstring — kodowanie „UTF7-IMAP".
 *
 * ⛔ Czego ta klasa NIE robi: nie parsuje struktury MIME, nie pobiera
 * załączników, nie obsługuje AUTHENTICATE (OAuth2/XOAUTH2) — do logowania
 * służy LOGIN z HASŁEM APLIKACJI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Połączenie z jedną skrzynką IMAP.
 */
class Segm_Imap {

	/**
	 * Koniec linii protokołu (RFC 3501: wszystkie linie kończy CRLF).
	 */
	const KONIEC = "\r\n";

	/**
	 * Ile linii dziennika protokołu trzymamy (podgląd dla właściciela).
	 */
	const DZIENNIK_LINII = 120;

	/**
	 * Ile znaków jednej linii dziennika pokazujemy.
	 */
	const DZIENNIK_ZNAKOW = 300;

	/**
	 * Gniazdo do serwera.
	 *
	 * @var resource|null
	 */
	private $gniazdo = null;

	/**
	 * Licznik znaczników poleceń (RFC 3501 2.2.1: każde polecenie ma tag).
	 *
	 * @var int
	 */
	private $licznik = 0;

	/**
	 * Ostatni błąd po polsku.
	 *
	 * @var string
	 */
	private $blad = '';

	/**
	 * Możliwości serwera z CAPABILITY (wielkimi literami).
	 *
	 * @var array<int, string>
	 */
	private $moznosci = array();

	/**
	 * Separator hierarchii folderów zwrócony przez LIST "" "".
	 *
	 * @var string
	 */
	private $rozdzielnik = '';

	/**
	 * Dziennik protokołu BEZ hasła — do okna „Sprawdź połączenie".
	 *
	 * @var array<int, string>
	 */
	private $dziennik = array();

	/**
	 * Limit czasu gniazda w sekundach.
	 *
	 * @var int
	 */
	private $limit_czasu;

	/**
	 * Ostrzeżenia PHP przechwycone przy ostatniej operacji sieciowej.
	 *
	 * @var array<int, string>
	 */
	private $ostrzezenia = array();

	/**
	 * @param int $limit_czasu Limit czasu połączenia i odczytu (sekundy).
	 */
	public function __construct( $limit_czasu = 15 ) {
		$this->limit_czasu = max( 3, (int) $limit_czasu );
	}

	/**
	 * Zamknięcie gniazda, gdyby ktoś zapomniał wywołać rozlacz().
	 */
	public function __destruct() {
		$this->rozlacz();
	}

	/**
	 * Ostatni błąd (pusty string = nie było błędu).
	 *
	 * @return string
	 */
	public function blad() {
		return $this->blad;
	}

	/**
	 * Dziennik protokołu (C: polecenie / S: odpowiedź), bez hasła.
	 *
	 * @return array<int, string>
	 */
	public function dziennik() {
		return $this->dziennik;
	}

	/**
	 * Czy serwer zgłosił daną możliwość (np. MOVE, UIDPLUS, STARTTLS).
	 *
	 * @param string $nazwa Nazwa możliwości.
	 * @return bool
	 */
	public function ma_moznosc( $nazwa ) {
		return in_array( strtoupper( $nazwa ), $this->moznosci, true );
	}

	/**
	 * Lista możliwości serwera.
	 *
	 * @return array<int, string>
	 */
	public function moznosci() {
		return $this->moznosci;
	}

	/**
	 * Separator hierarchii folderów (np. „/" albo „."). Pusty = serwer płaski.
	 *
	 * @return string
	 */
	public function rozdzielnik() {
		return $this->rozdzielnik;
	}

	/**
	 * Czy gniazdo jest otwarte.
	 *
	 * @return bool
	 */
	public function polaczony() {
		return is_resource( $this->gniazdo );
	}

	/* ===================== POŁĄCZENIE ===================== */

	/**
	 * Otwiera połączenie i wita się z serwerem.
	 *
	 * @param string $host          Nazwa hosta serwera IMAP.
	 * @param int    $port          Port (993 = TLS od razu, 143 = zwykły/STARTTLS).
	 * @param string $szyfrowanie   'ssl' | 'starttls' | 'brak'.
	 * @param bool   $ufaj_bez_cert Prawda = NIE sprawdzaj certyfikatu (tylko serwery testowe).
	 * @return bool
	 */
	public function polacz( $host, $port, $szyfrowanie, $ufaj_bez_cert = false ) {
		$this->blad = '';
		if ( ! function_exists( 'stream_socket_client' ) ) {
			$this->blad = 'Ten hosting ma wyłączoną funkcję stream_socket_client() (dyrektywa disable_functions w php.ini) — wtyczka nie ma jak otworzyć połączenia z serwerem poczty. Poproś hostingodawcę o jej odblokowanie.';
			return false;
		}
		if ( 'brak' !== $szyfrowanie && ! extension_loaded( 'openssl' ) ) {
			$this->blad = 'Ten PHP nie ma rozszerzenia OpenSSL, a bez niego nie ma szyfrowanego połączenia z pocztą. Poproś hostingodawcę o włączenie rozszerzenia openssl.';
			return false;
		}

		$kontekst = stream_context_create(
			array(
				'ssl' => array(
					'peer_name'         => $host,
					'SNI_enabled'       => true,
					'verify_peer'       => ! $ufaj_bez_cert,
					'verify_peer_name'  => ! $ufaj_bez_cert,
					'allow_self_signed' => (bool) $ufaj_bez_cert,
				),
			)
		);

		$adres   = ( 'ssl' === $szyfrowanie ? 'ssl://' : 'tcp://' ) . $host . ':' . (int) $port;
		$errno   = 0;
		$errstr  = '';
		$gniazdo = $this->z_przechwytem(
			function () use ( $adres, &$errno, &$errstr, $kontekst ) {
				return stream_socket_client( $adres, $errno, $errstr, $this->limit_czasu, STREAM_CLIENT_CONNECT, $kontekst );
			}
		);
		$this->zapisz_dziennik( 'C: (połączenie) ' . $adres );
		if ( false === $gniazdo ) {
			// Przy porażce TLS $errstr bywa PUSTY, a prawdziwy powód („certificate
			// verify failed") siedzi w ostrzeżeniu PHP, które wyciszyliśmy —
			// wyciągamy go stamtąd, zamiast zbyć właściciela ogólnikiem.
			$szczegol   = '' === $errstr ? $this->opis_ostrzezen() : $errstr;
			$this->blad = sprintf(
				'Połączenie z serwerem poczty %s nie doszło do skutku. %s Szczegóły techniczne (do wklejenia hostingodawcy): %s%s.',
				$host . ':' . (int) $port,
				self::po_ludzku( $errno, $szczegol ),
				$szczegol,
				$errno > 0 ? ' (kod systemowy ' . (int) $errno . ')' : ''
			);
			return false;
		}
		$this->gniazdo = $gniazdo;
		stream_set_timeout( $this->gniazdo, $this->limit_czasu );

		$powitanie = $this->czytaj_linie();
		if ( null === $powitanie ) {
			$this->blad = 'Połączenie stanęło, ale powitania IMAP nie było — serwer ' . $this->powod_przerwania() . '. Najczęstsza przyczyna: port obsługuje szyfrowanie od razu (993), a wybrano „bez szyfrowania" albo odwrotnie.';
			$this->rozlacz();
			return false;
		}
		$this->zapisz_dziennik( 'S: ' . $powitanie['tekst'] );
		if ( 0 !== strpos( $powitanie['tekst'], '* OK' ) && 0 !== strpos( $powitanie['tekst'], '* PREAUTH' ) ) {
			$this->blad = 'Serwer odrzucił połączenie na wejściu: ' . $powitanie['tekst'];
			$this->rozlacz();
			return false;
		}

		if ( ! $this->odswiez_moznosci() ) {
			return false;
		}

		if ( 'starttls' === $szyfrowanie && ! $this->starttls( $host ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Podniesienie zwykłego połączenia do TLS (RFC 3501 6.2.1 STARTTLS).
	 *
	 * Ustawienia weryfikacji certyfikatu wiszą już na gnieździe (kontekst
	 * z stream_socket_client), więc handshake bierze je stamtąd.
	 *
	 * @param string $host Host (do weryfikacji nazwy w certyfikacie).
	 * @return bool
	 */
	private function starttls( $host ) {
		if ( ! $this->ma_moznosc( 'STARTTLS' ) ) {
			$this->blad = 'Serwer nie ogłasza STARTTLS, więc na tym porcie nie da się zaszyfrować połączenia. Użyj portu 993 z szyfrowaniem SSL/TLS.';
			$this->rozlacz();
			return false;
		}
		$odp = $this->wykonaj( 'STARTTLS' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Serwer odmówił STARTTLS: ' . ( null === $odp ? 'brak odpowiedzi' : $odp['ogon'] );
			$this->rozlacz();
			return false;
		}

		// STREAM_CRYPTO_METHOD_TLSv1_2/1_3 — stałe z podręcznika PHP
		// (function.stream-socket-enable-crypto). Świadomie BEZ TLS 1.0/1.1.
		$metoda = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
		$udane  = $this->z_przechwytem(
			function () use ( $metoda ) {
				return stream_socket_enable_crypto( $this->gniazdo, true, $metoda );
			}
		);
		$this->zapisz_dziennik( 'C: (TLS handshake) ' . ( true === $udane ? 'OK' : 'PORAŻKA' ) );
		if ( true !== $udane ) {
			$this->blad = sprintf(
				'Szyfrowanie STARTTLS nie doszło do skutku (serwer %s): %s. Najczęstsza przyczyna: certyfikat serwera nie pasuje do nazwy hosta albo jest podpisany przez siebie.',
				$host,
				$this->opis_ostrzezen()
			);
			$this->rozlacz();
			return false;
		}

		// RFC 3501 6.2.1: po STARTTLS klient MUSI odpytać CAPABILITY ponownie —
		// lista sprzed szyfrowania mogła być podmieniona po drodze.
		return $this->odswiez_moznosci();
	}

	/**
	 * Logowanie hasłem aplikacji (RFC 3501 6.2.3 LOGIN).
	 *
	 * @param string $login Login (zwykle pełny adres e-mail).
	 * @param string $haslo HASŁO APLIKACJI, nie hasło główne konta.
	 * @return bool
	 */
	public function zaloguj( $login, $haslo ) {
		if ( $this->ma_moznosc( 'LOGINDISABLED' ) ) {
			// Dwa RÓŻNE powody pod jedną nazwą — właściciel musi wiedzieć który,
			// bo jeden naprawi ustawieniem, a drugiego nie naprawi wcale.
			$this->blad = 'Serwer nie przyjmuje logowania loginem i hasłem (ogłasza LOGINDISABLED). Możliwe przyczyny są dwie. (1) Połączenie nie jest szyfrowane — wtedy przełącz się na SSL/TLS, port 993. (2) Dostawca wyłączył logowanie hasłem i wymaga wyłącznie OAuth2 — tak działa dziś Microsoft 365 i Outlook.com. W tym drugim przypadku hasło aplikacji NIE pomoże: ta wtyczka loguje się loginem i hasłem, więc takiej skrzynki nie obsłuży.';
			return false;
		}
		$odp = $this->wykonaj(
			'LOGIN ' . $this->tekst( $login ) . ' ' . $this->tekst( $haslo ),
			'LOGIN ' . $this->tekst( $login ) . ' ****'
		);
		if ( null === $odp ) {
			return false;
		}
		if ( 'OK' !== $odp['status'] ) {
			$this->blad = 'Serwer odrzucił logowanie: ' . $odp['ogon'] . ' — sprawdź login i HASŁO APLIKACJI (u dostawców z logowaniem dwuskładnikowym zwykłe hasło konta NIE zadziała).';
			return false;
		}
		// Po zalogowaniu lista możliwości bywa inna (np. dochodzi MOVE).
		return $this->odswiez_moznosci();
	}

	/**
	 * LOGOUT + zamknięcie gniazda.
	 *
	 * @return void
	 */
	public function rozlacz() {
		if ( ! $this->polaczony() ) {
			return;
		}
		$this->wykonaj( 'LOGOUT' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- to gniazdo TCP, nie plik; WP_Filesystem gniazd nie obsługuje.
		fclose( $this->gniazdo );
		$this->gniazdo = null;
	}

	/* ===================== FOLDERY ===================== */

	/**
	 * Odświeżenie listy możliwości serwera (RFC 3501 6.1.1).
	 *
	 * @return bool
	 */
	private function odswiez_moznosci() {
		$this->moznosci = array();
		$odp            = $this->wykonaj( 'CAPABILITY' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Serwer nie odpowiedział na CAPABILITY: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return false;
		}
		foreach ( $odp['linie'] as $linia ) {
			if ( 0 === strpos( $linia['tekst'], '* CAPABILITY' ) ) {
				$czesci         = preg_split( '/\s+/', trim( substr( $linia['tekst'], strlen( '* CAPABILITY' ) ) ) );
				$this->moznosci = array_map( 'strtoupper', array_filter( (array) $czesci ) );
			}
		}
		return true;
	}

	/**
	 * Separator hierarchii: LIST "" "" (RFC 3501 6.3.8 — pusta nazwa to
	 * specjalne pytanie „jaki masz separator").
	 *
	 * @return string Separator albo pusty string, gdy serwer jest płaski.
	 */
	public function pobierz_rozdzielnik() {
		if ( '' !== $this->rozdzielnik ) {
			return $this->rozdzielnik;
		}
		$odp = $this->wykonaj( 'LIST "" ""' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			return '';
		}
		foreach ( $odp['linie'] as $linia ) {
			$wpis = $this->rozbierz_liste( $linia['tekst'] );
			if ( null !== $wpis && '' !== $wpis['rozdzielnik'] ) {
				$this->rozdzielnik = $wpis['rozdzielnik'];
				return $this->rozdzielnik;
			}
		}
		return '';
	}

	/**
	 * Wszystkie foldery skrzynki (LIST "" "*").
	 *
	 * @return array<int, array{nazwa: string, nazwa_czytelna: string, flagi: string}>|null
	 */
	public function foldery() {
		$odp = $this->wykonaj( 'LIST "" "*"' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Nie udało się pobrać listy folderów: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}
		$foldery = array();
		foreach ( $odp['linie'] as $linia ) {
			$wpis = $this->rozbierz_liste( $linia['tekst'] );
			if ( null === $wpis ) {
				continue;
			}
			if ( '' === $this->rozdzielnik && '' !== $wpis['rozdzielnik'] ) {
				$this->rozdzielnik = $wpis['rozdzielnik'];
			}
			$foldery[] = array(
				'nazwa'          => $wpis['nazwa'],
				'nazwa_czytelna' => self::z_utf7( $wpis['nazwa'] ),
				'flagi'          => $wpis['flagi'],
			);
		}
		return $foldery;
	}

	/**
	 * Czy folder o takiej nazwie (już zakodowanej) istnieje.
	 *
	 * @param string $nazwa Nazwa folderu w kodowaniu serwera.
	 * @return bool|null Null = nie dało się sprawdzić.
	 */
	public function folder_istnieje( $nazwa ) {
		$odp = $this->wykonaj( 'LIST "" ' . $this->tekst( $nazwa ) );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			return null;
		}
		foreach ( $odp['linie'] as $linia ) {
			if ( 0 === strpos( $linia['tekst'], '* LIST' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Zakłada folder (RFC 3501 6.3.3 CREATE) i zapisuje go do subskrypcji,
	 * żeby pokazał się w programie pocztowym właściciela.
	 *
	 * Zakłada też brakujące poziomy hierarchii — serwer NIE ma obowiązku
	 * tworzyć rodziców, a bez tego „Segregator/Faktury" pada na skrzynce,
	 * na której nie ma jeszcze folderu „Segregator".
	 *
	 * @param string $nazwa Nazwa folderu w kodowaniu serwera.
	 * @return bool
	 */
	public function utworz_folder( $nazwa ) {
		$poziomy = array( $nazwa );
		$sep     = $this->pobierz_rozdzielnik();
		if ( '' !== $sep ) {
			$poziomy = array();
			$czesci  = explode( $sep, $nazwa );
			$sklejka = '';
			foreach ( $czesci as $czesc ) {
				$sklejka   = '' === $sklejka ? $czesc : $sklejka . $sep . $czesc;
				$poziomy[] = $sklejka;
			}
		}

		foreach ( $poziomy as $poziom ) {
			if ( true === $this->folder_istnieje( $poziom ) ) {
				continue;
			}
			$odp = $this->wykonaj( 'CREATE ' . $this->tekst( $poziom ) );
			if ( null === $odp || 'OK' !== $odp['status'] ) {
				// Wyścig dwóch przebiegów: folder mógł powstać między sprawdzeniem
				// a CREATE — wtedy „NO" znaczy „już jest", a nie „nie udało się".
				if ( true === $this->folder_istnieje( $poziom ) ) {
					continue;
				}
				$this->blad = 'Nie udało się założyć folderu „' . self::z_utf7( $poziom ) . '": ' . ( null === $odp ? $this->blad : $odp['ogon'] );
				return false;
			}
			$this->wykonaj( 'SUBSCRIBE ' . $this->tekst( $poziom ) );
		}
		return true;
	}

	/**
	 * Otwarcie folderu.
	 *
	 * ⭐ $tylko_odczyt = true daje EXAMINE (RFC 3501 6.3.2), czyli otwarcie
	 * folderu w trybie READ-ONLY na poziomie PROTOKOŁU: serwer odrzuci każdą
	 * próbę zmiany. To jest techniczna gwarancja trybu „na sucho", a nie
	 * obietnica, że kod czegoś nie wywoła.
	 *
	 * @param string $nazwa        Nazwa folderu w kodowaniu serwera.
	 * @param bool   $tylko_odczyt Prawda = EXAMINE, fałsz = SELECT.
	 * @return array{wiadomosci: int, uidvalidity: int}|null
	 */
	public function otworz( $nazwa, $tylko_odczyt = true ) {
		$polecenie = ( $tylko_odczyt ? 'EXAMINE ' : 'SELECT ' ) . $this->tekst( $nazwa );
		$odp       = $this->wykonaj( $polecenie );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Nie udało się otworzyć folderu „' . self::z_utf7( $nazwa ) . '": ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}
		$wynik = array(
			'wiadomosci'  => 0,
			'uidvalidity' => 0,
		);
		foreach ( $odp['linie'] as $linia ) {
			if ( preg_match( '/^\* (\d+) EXISTS/', $linia['tekst'], $m ) ) {
				$wynik['wiadomosci'] = (int) $m[1];
			}
			if ( preg_match( '/\[UIDVALIDITY (\d+)\]/', $linia['tekst'], $m ) ) {
				$wynik['uidvalidity'] = (int) $m[1];
			}
		}
		return $wynik;
	}

	/* ===================== WIADOMOŚCI ===================== */

	/**
	 * UID-y wiadomości w otwartym folderze (RFC 3501 6.4.4 + 6.4.8).
	 *
	 * @param string $kryterium Kryterium wyszukiwania IMAP (domyślnie ALL).
	 * @return array<int, int>|null
	 */
	public function uidy( $kryterium = 'ALL' ) {
		$odp = $this->wykonaj( 'UID SEARCH ' . $kryterium );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Nie udało się pobrać listy wiadomości: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}
		$uidy = array();
		foreach ( $odp['linie'] as $linia ) {
			if ( 0 === strpos( $linia['tekst'], '* SEARCH' ) ) {
				foreach ( preg_split( '/\s+/', trim( substr( $linia['tekst'], strlen( '* SEARCH' ) ) ) ) as $liczba ) {
					if ( '' !== $liczba && ctype_digit( $liczba ) ) {
						$uidy[] = (int) $liczba;
					}
				}
			}
		}
		return $uidy;
	}

	/**
	 * Nagłówki jednej wiadomości.
	 *
	 * BODY.PEEK zamiast BODY — RFC 3501 6.4.5: „An alternate form of
	 * BODY[<section>] that does not implicitly set the \Seen flag". Bez tego
	 * samo obejrzenie skrzynki oznaczałoby cudzą pocztę jako przeczytaną.
	 *
	 * @param int $uid UID wiadomości.
	 * @return string|null Surowe nagłówki albo null przy błędzie.
	 */
	public function naglowki( $uid ) {
		$odp = $this->wykonaj( 'UID FETCH ' . (int) $uid . ' (BODY.PEEK[HEADER])' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Nie udało się pobrać nagłówków wiadomości ' . (int) $uid . ': ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}
		return $this->pierwszy_literal( $odp['linie'] );
	}

	/**
	 * Początek treści wiadomości (bez pobierania załączników).
	 *
	 * Pobieramy CZĘŚĆ sekcji TEXT — RFC 3501 6.4.5 dopuszcza zapis
	 * <początek.długość>, więc z maila z załącznikiem na 20 MB ściągamy
	 * tylko pierwsze kilka kB.
	 *
	 * @param int $uid    UID wiadomości.
	 * @param int $bajtow Ile bajtów treści pobrać.
	 * @return string|null
	 */
	public function tresc( $uid, $bajtow ) {
		$odp = $this->wykonaj( 'UID FETCH ' . (int) $uid . ' (BODY.PEEK[TEXT]<0.' . (int) $bajtow . '>)' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			return null;
		}
		return $this->pierwszy_literal( $odp['linie'] );
	}

	/**
	 * Czy wiadomość o danym UID istnieje w otwartym folderze (RFC 3501 6.4.8).
	 *
	 * @param int $uid UID wiadomości.
	 * @return bool|null Null = serwer nie odpowiedział poprawnie (blad() mówi czemu).
	 */
	public function istnieje( $uid ) {
		$uidy = $this->uidy( 'UID ' . (int) $uid );
		if ( null === $uidy ) {
			return null;
		}
		return in_array( (int) $uid, $uidy, true );
	}

	/**
	 * UIDVALIDITY folderu bez zmiany folderu bieżącego (RFC 3501 6.3.10 STATUS).
	 *
	 * @param string $nazwa Nazwa folderu w kodowaniu serwera.
	 * @return int 0 = serwer nie podał.
	 */
	public function uidvalidity( $nazwa ) {
		$odp = $this->wykonaj( 'STATUS ' . $this->tekst( $nazwa ) . ' (UIDVALIDITY)' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			return 0;
		}
		foreach ( $odp['linie'] as $linia ) {
			if ( preg_match( '/UIDVALIDITY (\d+)/', $linia['tekst'], $m ) ) {
				return (int) $m[1];
			}
		}
		return 0;
	}

	/**
	 * Przeniesienie wiadomości do innego folderu.
	 *
	 * Droga główna: UID MOVE (RFC 6851) — jedna operacja, bez stanów pośrednich.
	 * Serwer bez MOVE: UID COPY + oznaczenie \Deleted + UID EXPUNGE (RFC 4315).
	 * Serwer bez UIDPLUS zostaje z wiadomością oznaczoną do usunięcia i mówimy
	 * to WPROST — zwykłe EXPUNGE skasowałoby też cudze wiadomości oznaczone
	 * \Deleted przez inny program (RFC 4315 2.1 ostrzega o tym wprost).
	 *
	 * ⭐ Liczy się SKUTEK, nie status (LS-F1): RFC 3501 §6.4.8 — polecenie UID
	 * z numerem, który nie pasuje do żadnej wiadomości, kończy się „OK", bo
	 * serwer nie ma nic do zgłoszenia. Wtyczka liczyła kiedyś „przeniesiono"
	 * za sam status, więc cofnięcie meldowało sukces dla wiadomości, którą
	 * właściciel w międzyczasie przeciągnął gdzie indziej. Dlatego:
	 *  - serwer z UIDPLUS zwraca COPYUID (RFC 4315 §3) — PUSTY zbiór UID-ów
	 *    w COPYUID znaczy „nic nie ruszyłem" i tak to meldujemy (null + blad);
	 *  - serwer bez UIDPLUS: sprawdzamy istnienie PRZED ruchem (UID SEARCH)
	 *    i po nim — wiadomość, której nie było, albo która dalej leży w źródle,
	 *    to porażka, nie sukces.
	 * Zwracamy też UIDVALIDITY folderu docelowego (z COPYUID albo STATUS) —
	 * cofanie porównuje ją z bieżącą, zanim zaufa zapamiętanemu UID-owi.
	 *
	 * @param int    $uid   UID wiadomości w otwartym folderze.
	 * @param string $cel   Nazwa folderu docelowego w kodowaniu serwera.
	 * @return array{uid_docelowy: int, uidvalidity_do: int, sposob: string, uwaga: string}|null
	 */
	public function przenies( $uid, $cel ) {
		$uid = (int) $uid;
		// Bez UIDPLUS nie dostaniemy COPYUID, więc skutek mierzymy stanem folderu.
		$recznie = ! $this->ma_moznosc( 'UIDPLUS' );
		if ( $recznie ) {
			$jest = $this->istnieje( $uid );
			if ( null === $jest ) {
				return null;
			}
			if ( ! $jest ) {
				$this->blad = 'W otwartym folderze nie ma wiadomości o UID ' . $uid . ' — nic nie zostało przeniesione (ktoś przeniósł ją albo skasował poza wtyczką).';
				return null;
			}
		}

		if ( $this->ma_moznosc( 'MOVE' ) ) {
			$odp = $this->wykonaj( 'UID MOVE ' . $uid . ' ' . $this->tekst( $cel ) );
			if ( null === $odp || 'OK' !== $odp['status'] ) {
				$this->blad = 'Serwer odmówił przeniesienia wiadomości ' . $uid . ': ' . ( null === $odp ? $this->blad : $odp['ogon'] );
				return null;
			}
			$skutek = $this->skutek_ruchu( $odp, $uid, $cel, $recznie );
			if ( null === $skutek ) {
				return null;
			}
			return array(
				'uid_docelowy'   => $skutek['uid'],
				'uidvalidity_do' => $skutek['uidvalidity'],
				'sposob'         => 'UID MOVE',
				'uwaga'          => '',
			);
		}

		$odp = $this->wykonaj( 'UID COPY ' . $uid . ' ' . $this->tekst( $cel ) );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Serwer odmówił skopiowania wiadomości ' . $uid . ' do folderu docelowego: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}
		$skutek = $this->skutek_ruchu( $odp, $uid, $cel, false );
		if ( null === $skutek ) {
			return null;
		}
		$uid_docelowy   = $skutek['uid'];
		$uidvalidity_do = $skutek['uidvalidity'];

		$odp = $this->wykonaj( 'UID STORE ' . $uid . ' +FLAGS.SILENT (\\Deleted)' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Kopia wiadomości ' . $uid . ' trafiła do folderu docelowego, ale oryginału NIE udało się oznaczyć do usunięcia — w skrzynce zostaje duplikat: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}

		if ( ! $this->ma_moznosc( 'UIDPLUS' ) ) {
			return array(
				'uid_docelowy'   => $uid_docelowy,
				'uidvalidity_do' => $uidvalidity_do,
				'sposob'         => 'UID COPY + \\Deleted',
				'uwaga'          => 'Serwer nie ma rozszerzeń MOVE ani UIDPLUS: kopia jest w folderze docelowym, a oryginał oznaczyliśmy do usunięcia, ale NIE kasujemy go sami — zwykłe EXPUNGE usunęłoby też wiadomości oznaczone do kasowania przez Twój program pocztowy. Oryginał zniknie, gdy opróżnisz kosz w kliencie poczty.',
			);
		}

		$odp = $this->wykonaj( 'UID EXPUNGE ' . $uid );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			return array(
				'uid_docelowy'   => $uid_docelowy,
				'uidvalidity_do' => $uidvalidity_do,
				'sposob'         => 'UID COPY + \\Deleted',
				'uwaga'          => 'Kopia jest w folderze docelowym i oryginał jest oznaczony do usunięcia, ale UID EXPUNGE nie przeszło (' . ( null === $odp ? $this->blad : $odp['ogon'] ) . ') — oryginał chwilowo zostaje w skrzynce.',
			);
		}
		return array(
			'uid_docelowy'   => $uid_docelowy,
			'uidvalidity_do' => $uidvalidity_do,
			'sposob'         => 'UID COPY + UID EXPUNGE',
			'uwaga'          => '',
		);
	}

	/**
	 * Co NAPRAWDĘ zrobił MOVE/COPY: nowy UID i UIDVALIDITY celu z COPYUID,
	 * a gdy serwer nie dał COPYUID — stan folderu źródłowego po ruchu
	 * (tylko gdy $recznie, czyli bez UIDPLUS) i STATUS celu.
	 *
	 * @param array{status: string, ogon: string, linie: array<int, array{tekst: string, literaly: array<int, string>}>} $odp     Odpowiedź serwera.
	 * @param int    $uid     UID w folderze źródłowym.
	 * @param string $cel     Folder docelowy (kodowanie serwera).
	 * @param bool   $recznie Czy brak COPYUID sprawdzać stanem źródła.
	 * @return array{uid: int, uidvalidity: int}|null Null = nic nie ruszono (blad() mówi czemu).
	 */
	private function skutek_ruchu( $odp, $uid, $cel, $recznie ) {
		$c = $this->copyuid( $odp );
		if ( $c['jest'] ) {
			if ( $c['pusty'] ) {
				$this->blad = 'Serwer odpowiedział OK, ale COPYUID jest pusty: w otwartym folderze nie ma wiadomości o UID ' . (int) $uid . ' — nic nie zostało przeniesione (ktoś przeniósł ją albo skasował poza wtyczką).';
				return null;
			}
			return array(
				'uid'         => $c['uid'],
				'uidvalidity' => $c['uidvalidity'],
			);
		}
		if ( $recznie ) {
			$jest = $this->istnieje( $uid );
			if ( null === $jest ) {
				return null;
			}
			if ( $jest ) {
				$this->blad = 'Serwer odpowiedział OK, ale wiadomość ' . (int) $uid . ' nadal leży w folderze źródłowym — nie została przeniesiona.';
				return null;
			}
		}
		return array(
			'uid'         => 0,
			'uidvalidity' => $this->uidvalidity( $cel ),
		);
	}

	/**
	 * Szukanie wiadomości po nagłówku Message-ID (droga odwrotu, gdy serwer
	 * nie zwrócił COPYUID i nie znamy nowego UID-u).
	 *
	 * @param string $message_id Wartość nagłówka Message-ID.
	 * @return array<int, int>|null
	 */
	public function znajdz_po_message_id( $message_id ) {
		return $this->uidy( 'HEADER Message-ID ' . $this->tekst( $message_id ) );
	}

	/* ===================== PROTOKÓŁ ===================== */

	/**
	 * Wysyła polecenie ze znacznikiem i zbiera odpowiedź do linii zakończenia.
	 *
	 * Metoda jest NIECZYSTA (rozmowa z serwerem): to samo polecenie wysłane
	 * dwa razy może dać inną odpowiedź — dlatego adnotacja @phpstan-impure.
	 * Bez niej analiza uznaje powtórne sprawdzenie „czy folder już jest"
	 * za martwy kod, a to jest właśnie obsługa wyścigu dwóch przebiegów.
	 *
	 * @param string      $polecenie Polecenie IMAP bez znacznika i CRLF.
	 * @param string|null $etykieta  Wersja do dziennika (LOGIN bez hasła).
	 * @return array{status: string, ogon: string, linie: array<int, array{tekst: string, literaly: array<int, string>}>}|null
	 *
	 * @phpstan-impure
	 */
	private function wykonaj( $polecenie, $etykieta = null ) {
		if ( ! $this->polaczony() ) {
			$this->blad = 'Połączenie z serwerem poczty jest już zamknięte.';
			return null;
		}
		++$this->licznik;
		$znacznik = sprintf( 'S%03d', $this->licznik );
		$this->zapisz_dziennik( 'C: ' . $znacznik . ' ' . ( null === $etykieta ? $polecenie : $etykieta ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- zapis do gniazda TCP, nie do pliku; WP_Filesystem gniazd nie obsługuje.
		$zapisane = fwrite( $this->gniazdo, $znacznik . ' ' . $polecenie . self::KONIEC );
		if ( false === $zapisane ) {
			$this->blad = 'Zerwane połączenie: nie udało się wysłać polecenia do serwera poczty.';
			return null;
		}

		$linie = array();
		while ( true ) {
			$linia = $this->czytaj_linie();
			if ( null === $linia ) {
				$this->blad = 'Serwer poczty ' . $this->powod_przerwania() . ' w trakcie polecenia ' . strtok( $polecenie, ' ' ) . '.';
				return null;
			}
			$this->zapisz_dziennik( 'S: ' . $linia['tekst'] );

			if ( 0 === strpos( $linia['tekst'], $znacznik . ' ' ) ) {
				$ogon   = trim( substr( $linia['tekst'], strlen( $znacznik ) + 1 ) );
				$status = strtoupper( (string) strtok( $ogon, ' ' ) );
				if ( ! in_array( $status, array( 'OK', 'NO', 'BAD' ), true ) ) {
					$status = 'BAD';
				}
				return array(
					'status' => $status,
					'ogon'   => $ogon,
					'linie'  => $linie,
				);
			}
			$linie[] = $linia;
		}
	}

	/**
	 * Wykonuje operację sieciową, przechwytując ostrzeżenia PHP.
	 *
	 * Ostrzeżenia z gniazd nie mogą wylądować w środku strony panelu, ale ich
	 * TREŚĆ jest dla właściciela najcenniejsza — to w niej OpenSSL pisze, co
	 * dokładnie odrzucił („certificate verify failed", „Name does not match
	 * expected"). Dlatego zamiast wyciszać je operatorem @, zbieramy je
	 * własnym uchwytem i wkładamy do komunikatu po polsku.
	 *
	 * @param callable $operacja Operacja do wykonania.
	 * @return mixed
	 */
	private function z_przechwytem( $operacja ) {
		$this->ostrzezenia = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- to nie kod diagnostyczny: uchwyt zakladamy na CZAS JEDNEJ operacji na gniezdzie i zdejmujemy restore_error_handler() dwie linijki nizej. Alternatywa (operator @) tez wymaga wyjatku od sniffa, a przy okazji gubi tresc bledu OpenSSL, ktora jest tu najcenniejsza dla wlasciciela.
		set_error_handler(
			function ( $numer, $tekst ) {
				$this->ostrzezenia[] = trim( (string) preg_replace( '/^[a-z_]+\(\):\s*/i', '', (string) $tekst ) );
				return true;
			}
		);
		$wynik = $operacja();
		restore_error_handler();
		return $wynik;
	}

	/**
	 * Przechwycone ostrzeżenia sklejone w jedno zdanie.
	 *
	 * @return string
	 */
	private function opis_ostrzezen() {
		$unikalne = array_values( array_unique( array_filter( $this->ostrzezenia ) ) );
		if ( empty( $unikalne ) ) {
			return 'serwer nie odpowiedział';
		}
		return implode( '; ', $unikalne );
	}

	/**
	 * Dlaczego odczyt się urwał: cisza do limitu czasu czy zamknięte połączenie.
	 *
	 * Rozróżnienie jest ważne dla właściciela: „nie odpowiedział przez 15 s"
	 * to zwykle zły port albo zapora, a „zerwał połączenie" to najczęściej
	 * odrzucone poświadczenia albo serwer, który przewrócił się na naszym
	 * poleceniu.
	 *
	 * @return string
	 */
	private function powod_przerwania() {
		if ( ! is_resource( $this->gniazdo ) ) {
			return 'zamknął połączenie';
		}
		$meta = stream_get_meta_data( $this->gniazdo );
		if ( ! empty( $meta['timed_out'] ) ) {
			return 'przestał odpowiadać (limit ' . $this->limit_czasu . ' s)';
		}
		if ( ! empty( $meta['eof'] ) || feof( $this->gniazdo ) ) {
			return 'zerwał połączenie bez odpowiedzi (sprawdź login i hasło aplikacji — część serwerów zamyka połączenie zamiast odpowiedzieć „błędne dane")';
		}
		return 'przerwał rozmowę';
	}

	/**
	 * Czyta jedną linię odpowiedzi razem z literałami.
	 *
	 * Literał (RFC 3501 4.3) to „{liczba}CRLF" i po nim DOKŁADNIE tyle bajtów —
	 * w środku mogą być znaki końca linii, więc samo fgets() rozjechałoby
	 * parsowanie. Dane literałów oddajemy osobno, a w tekście zostaje „{n}".
	 *
	 * @return array{tekst: string, literaly: array<int, string>}|null
	 */
	private function czytaj_linie() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets -- odczyt z gniazda TCP, nie z pliku.
		$tekst = fgets( $this->gniazdo );
		if ( false === $tekst ) {
			return null;
		}
		$literaly = array();

		while ( preg_match( '/\{(\d+)\}\r?\n$/', $tekst, $m ) ) {
			$ile     = (int) $m[1];
			$dane    = '';
			$pobrane = 0;
			while ( $pobrane < $ile ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- odczyt z gniazda TCP, nie z pliku.
				$kawalek = fread( $this->gniazdo, $ile - $pobrane );
				if ( false === $kawalek || '' === $kawalek ) {
					return null;
				}
				$dane    .= $kawalek;
				$pobrane += strlen( $kawalek );
			}
			$literaly[] = $dane;

			$tekst = rtrim( $tekst, "\r\n" );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets -- odczyt z gniazda TCP, nie z pliku.
			$reszta = fgets( $this->gniazdo );
			if ( false === $reszta ) {
				return null;
			}
			$tekst .= $reszta;
		}

		return array(
			'tekst'    => rtrim( $tekst, "\r\n" ),
			'literaly' => $literaly,
		);
	}

	/**
	 * Pierwszy literał z odpowiedzi (dane FETCH-a).
	 *
	 * @param array<int, array{tekst: string, literaly: array<int, string>}> $linie Linie odpowiedzi.
	 * @return string|null
	 */
	private function pierwszy_literal( $linie ) {
		foreach ( $linie as $linia ) {
			if ( ! empty( $linia['literaly'] ) ) {
				return (string) $linia['literaly'][0];
			}
		}
		return null;
	}

	/**
	 * Kod odpowiedzi COPYUID (RFC 4315 3: „COPYUID SP uidvalidity SP uid-set
	 * SP uid-set"): UIDVALIDITY folderu DOCELOWEGO, UID-y źródłowe, UID-y nadane
	 * kopiom. Kopiujemy po JEDNEJ wiadomości, więc zbiory mają jeden element.
	 *
	 * Serwer NIE MUSI zwrócić COPYUID (`jest` = false). Gdy zwraca, a zbiory są
	 * PUSTE (`pusty` = true), to wprost mówi: „żadna wiadomość nie pasowała" —
	 * czyli nic nie przeniósł, choć status był OK (tak robi np. GreenMail:
	 * `* OK [COPYUID 1787494337  ]`).
	 *
	 * @param array{status: string, ogon: string, linie: array<int, array{tekst: string, literaly: array<int, string>}>} $odp Odpowiedź serwera.
	 * @return array{jest: bool, pusty: bool, uidvalidity: int, uid: int}
	 */
	private function copyuid( $odp ) {
		$brak      = array(
			'jest'        => false,
			'pusty'       => false,
			'uidvalidity' => 0,
			'uid'         => 0,
		);
		$kandydaci = array( $odp['ogon'] );
		foreach ( $odp['linie'] as $linia ) {
			$kandydaci[] = $linia['tekst'];
		}
		foreach ( $kandydaci as $tekst ) {
			if ( preg_match( '/\[COPYUID (\d+) ([0-9,:]*) ?([0-9,:]*)\s*\]/i', $tekst, $m ) ) {
				$nowe = trim( $m[3] );
				return array(
					'jest'        => true,
					'pusty'       => '' === $nowe,
					'uidvalidity' => (int) $m[1],
					'uid'         => '' === $nowe ? 0 : (int) strtok( $nowe, ',:' ),
				);
			}
		}
		return $brak;
	}

	/**
	 * Kod błędu gniazda po ludzku.
	 *
	 * Kupujący dostawał „Connection refused (kod 111)" — żargon w komunikacie
	 * pisanym dla właściciela małej firmy. Kod ZOSTAJE (jest potrzebny, gdy
	 * trzeba to wkleić hostingodawcy), ale pierwsze zdanie mówi, co to znaczy
	 * i co z tym zrobić. Numery to stałe errno z POSIX-a, te same na każdym
	 * Linuksie; nieznany kod dostaje zdanie ogólne, nie pustkę.
	 *
	 * @param int    $errno    Kod błędu z stream_socket_client().
	 * @param string $szczegol Komunikat systemowy (rozpoznajemy w nim TLS).
	 * @return string Zdanie po polsku, zakończone kropką.
	 */
	private static function po_ludzku( $errno, $szczegol ) {
		/*
		 * ⛔ KOLEJNOŚĆ MA ZNACZENIE: najpierw treść komunikatu systemowego,
		 * dopiero potem kod. Przy nierozwiązanej nazwie serwera (literówka
		 * w adresie — najczęstszy błąd właściciela) PHP ustawia `errno = 0`,
		 * a nie -3: zmierzone na PHP 8.4, `php_network_getaddresses:
		 * getaddrinfo for … failed: Name or service not known`. Gdyby kod
		 * czytać pierwszy, ta gałąź byłaby martwa i właściciel dostawałby
		 * ogólnik plus angielski komunikat sterownika.
		 */
		if ( false !== stripos( $szczegol, 'getaddrinfo' ) || false !== stripos( $szczegol, 'php_network_getaddresses' ) || false !== stripos( $szczegol, 'name or service not known' ) || false !== stripos( $szczegol, 'no address associated' ) ) {
			return 'Nie udało się odnaleźć serwera o tym adresie — nazwa nie zamienia się na adres w sieci. Sprawdź, czy w polu „Serwer IMAP" nie ma literówki (dla Gmaila jest to imap.gmail.com).';
		}
		/*
		 * Heurystyka TLS musi być WĄSKA: samo „ssl" pada też w naszym własnym
		 * adresie (`Unable to connect to ssl://host:993`), więc szeroki wzorzec
		 * przypisywał winę szyfrowaniu przy każdej porażce połączenia — i mówił
		 * „serwer odpowiedział", gdy nikt nie odebrał. Dopasowujemy komunikaty,
		 * które NAPRAWDĘ pochodzą z warstwy szyfrowania.
		 */
		if ( preg_match( '/SSL routines|certificate verify|enable crypto|wrong version number|sslv3|tlsv1|handshake/i', $szczegol ) ) {
			return 'Serwer odpowiedział, ale nie udało się zestawić szyfrowania. Najczęstsza przyczyna to niedobrana para port + szyfrowanie (993 idzie z „SSL/TLS od razu", 143 ze „STARTTLS"); przy serwerze z certyfikatem podpisanym przez siebie pomaga opcja pomijania weryfikacji certyfikatu.';
		}
		$opisy = array(
			111 => 'Serwer odrzucił połączenie na tym porcie — nikt tam nie odbiera. Sprawdź numer portu (najczęściej 993) i czy adres serwera jest właściwy.',
			110 => 'Serwer nie odpowiedział w wyznaczonym czasie. Zwykle blokuje to zapora hostingu albo adres jest niewłaściwy.',
			113 => 'Do tego serwera nie ma drogi z Twojego hostingu — połączenia wychodzące są zablokowane albo adres jest zły.',
			101 => 'Twój hosting nie ma połączenia z siecią, którą trzeba przejść do tego serwera.',
		);
		if ( isset( $opisy[ (int) $errno ] ) ) {
			return $opisy[ (int) $errno ];
		}
		return 'Sprawdź adres serwera, numer portu i wybrane szyfrowanie — najczęściej to one się nie zgadzają. Gdy wszystko wygląda dobrze, przeszkodą bywa zapora hostingu blokująca połączenia wychodzące.';
	}

	/**
	 * Dopisanie linii do dziennika protokołu (bez hasła, z obcięciem).
	 *
	 * @param string $linia Linia dziennika.
	 * @return void
	 */
	private function zapisz_dziennik( $linia ) {
		$linia = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '.', (string) $linia );
		if ( strlen( (string) $linia ) > self::DZIENNIK_ZNAKOW ) {
			$linia = substr( (string) $linia, 0, self::DZIENNIK_ZNAKOW ) . '… (obcięte)';
		}
		$this->dziennik[] = (string) $linia;
		if ( count( $this->dziennik ) > self::DZIENNIK_LINII ) {
			array_shift( $this->dziennik );
		}
	}

	/**
	 * Argument tekstowy polecenia jako łańcuch w cudzysłowie.
	 *
	 * RFC 3501 4.3: w takim łańcuchu cudzysłów i odwrotny ukośnik trzeba
	 * poprzedzić odwrotnym ukośnikiem (quoted-specials).
	 *
	 * @param string $wartosc Wartość do wstawienia w polecenie.
	 * @return string
	 */
	private function tekst( $wartosc ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $wartosc ) . '"';
	}

	/**
	 * Rozbiór linii odpowiedzi LIST: flagi, separator, nazwa.
	 *
	 * @param string $tekst Linia odpowiedzi.
	 * @return array{flagi: string, rozdzielnik: string, nazwa: string}|null
	 */
	private function rozbierz_liste( $tekst ) {
		if ( ! preg_match( '/^\* (?:LIST|LSUB) \(([^)]*)\) (NIL|"(?:[^"\\\\]|\\\\.)*"|\S+) (.*)$/', $tekst, $m ) ) {
			return null;
		}
		$rozdzielnik = 'NIL' === $m[2] ? '' : $this->odtekst( $m[2] );
		return array(
			'flagi'       => $m[1],
			'rozdzielnik' => $rozdzielnik,
			'nazwa'       => $this->odtekst( trim( $m[3] ) ),
		);
	}

	/**
	 * Zdjęcie cudzysłowów i odwrotnych ukośników z argumentu odpowiedzi.
	 *
	 * @param string $wartosc Wartość z odpowiedzi serwera.
	 * @return string
	 */
	private function odtekst( $wartosc ) {
		$wartosc = (string) $wartosc;
		if ( strlen( $wartosc ) >= 2 && '"' === $wartosc[0] && '"' === substr( $wartosc, -1 ) ) {
			$wartosc = substr( $wartosc, 1, -1 );
			$wartosc = str_replace( array( '\\"', '\\\\' ), array( '"', '\\' ), $wartosc );
		}
		return $wartosc;
	}

	/* ===================== NAZWY FOLDERÓW (RFC 3501 5.1.3) ===================== */

	/**
	 * Nazwa folderu z UTF-8 na zmodyfikowany UTF-7 protokołu IMAP.
	 *
	 * @param string $nazwa Nazwa w UTF-8.
	 * @return string
	 */
	public static function do_utf7( $nazwa ) {
		return mb_convert_encoding( (string) $nazwa, 'UTF7-IMAP', 'UTF-8' );
	}

	/**
	 * Nazwa folderu ze zmodyfikowanego UTF-7 na UTF-8 (do pokazania człowiekowi).
	 *
	 * @param string $nazwa Nazwa w kodowaniu serwera.
	 * @return string
	 */
	public static function z_utf7( $nazwa ) {
		return mb_convert_encoding( (string) $nazwa, 'UTF-8', 'UTF7-IMAP' );
	}
}
