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
			$this->blad = sprintf(
				'Nie udało się połączyć z %s: %s (kod %d). Sprawdź adres serwera, port i szyfrowanie — a przy serwerze z certyfikatem podpisanym przez siebie także opcję pomijania weryfikacji certyfikatu.',
				$adres,
				'' === $errstr ? $this->opis_ostrzezen() : $errstr,
				$errno
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
	 * Przeniesienie wiadomości do innego folderu.
	 *
	 * Droga główna: UID MOVE (RFC 6851) — jedna operacja, bez stanów pośrednich.
	 * Serwer bez MOVE: UID COPY + oznaczenie \Deleted + UID EXPUNGE (RFC 4315).
	 * Serwer bez UIDPLUS zostaje z wiadomością oznaczoną do usunięcia i mówimy
	 * to WPROST — zwykłe EXPUNGE skasowałoby też cudze wiadomości oznaczone
	 * \Deleted przez inny program (RFC 4315 2.1 ostrzega o tym wprost).
	 *
	 * @param int    $uid   UID wiadomości w otwartym folderze.
	 * @param string $cel   Nazwa folderu docelowego w kodowaniu serwera.
	 * @return array{uid_docelowy: int, sposob: string, uwaga: string}|null
	 */
	public function przenies( $uid, $cel ) {
		if ( $this->ma_moznosc( 'MOVE' ) ) {
			$odp = $this->wykonaj( 'UID MOVE ' . (int) $uid . ' ' . $this->tekst( $cel ) );
			if ( null === $odp || 'OK' !== $odp['status'] ) {
				$this->blad = 'Serwer odmówił przeniesienia wiadomości ' . (int) $uid . ': ' . ( null === $odp ? $this->blad : $odp['ogon'] );
				return null;
			}
			return array(
				'uid_docelowy' => $this->copyuid( $odp ),
				'sposob'       => 'UID MOVE',
				'uwaga'        => '',
			);
		}

		$odp = $this->wykonaj( 'UID COPY ' . (int) $uid . ' ' . $this->tekst( $cel ) );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Serwer odmówił skopiowania wiadomości ' . (int) $uid . ' do folderu docelowego: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}
		$uid_docelowy = $this->copyuid( $odp );

		$odp = $this->wykonaj( 'UID STORE ' . (int) $uid . ' +FLAGS.SILENT (\\Deleted)' );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			$this->blad = 'Kopia wiadomości ' . (int) $uid . ' trafiła do folderu docelowego, ale oryginału NIE udało się oznaczyć do usunięcia — w skrzynce zostaje duplikat: ' . ( null === $odp ? $this->blad : $odp['ogon'] );
			return null;
		}

		if ( ! $this->ma_moznosc( 'UIDPLUS' ) ) {
			return array(
				'uid_docelowy' => $uid_docelowy,
				'sposob'       => 'UID COPY + \\Deleted',
				'uwaga'        => 'Serwer nie ma rozszerzeń MOVE ani UIDPLUS: kopia jest w folderze docelowym, a oryginał oznaczyliśmy do usunięcia, ale NIE kasujemy go sami — zwykłe EXPUNGE usunęłoby też wiadomości oznaczone do kasowania przez Twój program pocztowy. Oryginał zniknie, gdy opróżnisz kosz w kliencie poczty.',
			);
		}

		$odp = $this->wykonaj( 'UID EXPUNGE ' . (int) $uid );
		if ( null === $odp || 'OK' !== $odp['status'] ) {
			return array(
				'uid_docelowy' => $uid_docelowy,
				'sposob'       => 'UID COPY + \\Deleted',
				'uwaga'        => 'Kopia jest w folderze docelowym i oryginał jest oznaczony do usunięcia, ale UID EXPUNGE nie przeszło (' . $odp['ogon'] . ') — oryginał chwilowo zostaje w skrzynce.',
			);
		}
		return array(
			'uid_docelowy' => $uid_docelowy,
			'sposob'       => 'UID COPY + UID EXPUNGE',
			'uwaga'        => '',
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
	 * UID nadany kopii w folderze docelowym z kodu odpowiedzi COPYUID
	 * (RFC 4315 3: „COPYUID SP uidvalidity SP uid-set SP uid-set").
	 *
	 * Kopiujemy po JEDNEJ wiadomości, więc zbiory UID-ów mają jeden element
	 * i drugi z nich jest nowym UID-em. Serwer NIE MUSI zwrócić COPYUID —
	 * wtedy 0, a cofanie przebiegu szuka wiadomości po Message-ID.
	 *
	 * @param array{status: string, ogon: string, linie: array<int, array{tekst: string, literaly: array<int, string>}>} $odp Odpowiedź serwera.
	 * @return int
	 */
	private function copyuid( $odp ) {
		$kandydaci = array( $odp['ogon'] );
		foreach ( $odp['linie'] as $linia ) {
			$kandydaci[] = $linia['tekst'];
		}
		foreach ( $kandydaci as $tekst ) {
			if ( preg_match( '/\[COPYUID (\d+) ([0-9,:]+) ([0-9,:]+)\]/i', $tekst, $m ) ) {
				return (int) strtok( $m[3], ',:' );
			}
		}
		return 0;
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
