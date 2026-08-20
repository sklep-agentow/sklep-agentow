<?php
/**
 * Aktywacja, tabele (dbDelta), seed demo, cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tworzy/aktualizuje tabele wtyczki przez dbDelta.
 * Nowa tabela = 3 miejsca: tutaj + uninstall.php + ścieżka upgrade (chatl_sprawdz_schemat).
 */
function chatl_utworz_tabele() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$p       = $wpdb->prefix;

	dbDelta(
		"CREATE TABLE {$p}chatl_qa (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		pytanie text NOT NULL,
		slowa_kluczowe text NOT NULL,
		odpowiedz text NOT NULL,
		aktywne tinyint(1) NOT NULL DEFAULT 1,
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}chatl_zgloszenia (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL,
		wiadomosc text NOT NULL,
		sesja varchar(64) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'nowe',
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) {$charset};"
	);

	/*
	 * trafiono: czy bot ZNAŁ odpowiedź na to pytanie (1/0). Ustawiane na OBU
	 * wierszach wymiany — na pytaniu gościa i na odpowiedzi bota — bo raport
	 * luk potrzebuje TREŚCI PYTANIA, a nie treści odpowiedzi.
	 *
	 * NULL znaczy „nie wiadomo" i występuje tylko w wierszach sprzed 1.3.0.
	 * Świadomie NIE zgadujemy ich wstecz (np. porównaniem treści z aktualnym
	 * tekstem awaryjnym): właściciel mógł ten tekst po drodze zmienić, więc
	 * takie „uzupełnienie" wpisałoby do statystyk sprzedawanego produktu
	 * liczby, których nikt nigdy nie zmierzył. Statystyki liczą NULL-e osobno
	 * i mówią o nich wprost na stronie.
	 */
	dbDelta(
		"CREATE TABLE {$p}chatl_rozmowy (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		sesja varchar(64) NOT NULL,
		rola varchar(10) NOT NULL,
		silnik varchar(10) NOT NULL DEFAULT 'reguly',
		tresc text NOT NULL,
		trafiono tinyint(1) DEFAULT NULL,
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY sesja (sesja),
		KEY utworzono (utworzono),
		KEY luki (rola,trafiono,utworzono)
	) {$charset};"
	);

	/*
	 * Rachunek u dostawcy modelu idzie z TOKENOW, nie z liczby wywolan — klient
	 * z 200 parami Q&A placi wielokrotnosc klienta z 10 parami przy tym samym
	 * "limicie 100 wywolan". Dlatego liczymy cztery pola, dokladnie te, ktore
	 * zwraca API w polu `usage`.
	 */
	dbDelta(
		"CREATE TABLE {$p}chatl_ai_uzycie (
		dzien date NOT NULL,
		wywolania int(10) unsigned NOT NULL DEFAULT 0,
		tokeny_wejscia bigint(20) unsigned NOT NULL DEFAULT 0,
		tokeny_wyjscia bigint(20) unsigned NOT NULL DEFAULT 0,
		tokeny_cache_zapis bigint(20) unsigned NOT NULL DEFAULT 0,
		tokeny_cache_odczyt bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (dzien)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}chatl_rate_limit (
		klucz varchar(64) NOT NULL,
		licznik int(10) unsigned NOT NULL DEFAULT 0,
		okno_do datetime NOT NULL,
		PRIMARY KEY  (klucz),
		KEY okno_do (okno_do)
	) {$charset};"
	);
}

/**
 * Aktywacja: tabele + wersja schematu + seed + harmonogram czyszczenia historii.
 */
function chatl_aktywacja() {
	chatl_utworz_tabele();
	update_option( 'chatl_wersja_schematu', CHATL_WERSJA_SCHEMATU );
	chatl_seed_demo();

	if ( ! wp_next_scheduled( 'chatl_czyszczenie_historii' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'chatl_czyszczenie_historii' );
	}

	/*
	 * Znacznik dla ekranu powitalnego. Krótkie życie (minuta), bo dotyczy
	 * JEDNEGO przekierowania zaraz po kliknięciu „Włącz" — gdyby żył długo,
	 * powitanie wyskakiwałoby administratorowi, który wszedł do panelu
	 * godzinę później i nie wiedziałby, skąd się wzięło.
	 * Stałą definiuje includes/powitanie.php, ładowany tylko w panelu, więc
	 * przy aktywacji przez WP-CLI nazwy trzymamy się wprost.
	 */
	set_transient( 'chatl_po_aktywacji', 1, MINUTE_IN_SECONDS );
}

/**
 * Deaktywacja: zdejmij cron (dane zostają).
 */
function chatl_deaktywacja() {
	wp_clear_scheduled_hook( 'chatl_czyszczenie_historii' );
}

/**
 * Seed: 10 par Q&A typowej polskiej firmy usługowej.
 * Wchodzi tylko do PUSTEJ bazy — nie nadpisuje pracy właściciela przy reaktywacji.
 */
function chatl_seed_demo() {
	global $wpdb;
	$tabela = $wpdb->prefix . 'chatl_qa';

	$ile = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabela}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $ile > 0 ) {
		return;
	}

	/*
	 * Warianty to podłańcuchy: krótkie rdzenie („otwart", „konsultac") łapią
	 * odmiany, a formy bez polskich znaków — pisownię bez diakrytyków.
	 *
	 * Silnik dopasowuje PODŁAŃCUCHY, więc na literówki i brak ogonków nie ma
	 * innej obrony niż wpisane wprost warianty („cenik", „otwarci"). Dodając
	 * je pilnujemy, żeby NIE dublowały rdzenia, który już tu jest: liczba
	 * trafionych wariantów jest punktacją, więc wariant zawierający istniejący
	 * rdzeń („rachunek" obok „rachun") zawyżałby wynik tej pary kosztem innych.
	 */
	$seed = array(
		array(
			'Ile kosztują usługi? Gdzie znajdę cennik?',
			'cena, ceny, cenie, cennik, cenik, koszt, ile płac, ile plac, stawk, wycen, opłat, oplat',
			'Aktualny cennik znajdziesz na stronie „Cennik". Ceny zależą od zakresu — napisz, czego potrzebujesz, a przygotujemy wycenę bez zobowiązań.',
		),
		array(
			'W jakich godzinach jesteście otwarci?',
			'godzin, otwart, otwarc, czynn, pracujecie, sobot, weekend, niedziel, do której, do ktorej, od której, od ktorej',
			'Pracujemy od poniedziałku do piątku w godzinach 8:00–16:00. W weekendy odpisujemy na wiadomości w miarę możliwości.',
		),
		array(
			'Jak się z wami skontaktować?',
			'kontakt, telefon, zadzwoni, dzwoni, mail, mejl, napisać, napisac, numer',
			'Najszybciej: telefon 600 000 000 (pn–pt 8:00–16:00) albo mail biuro@twojafirma.pl. Możesz też zostawić wiadomość tutaj w czacie — oddzwonimy.',
		),
		array(
			'Czym się zajmujecie? Jaka jest oferta?',
			'ofert, oferuj, usług, uslug, zakres, zajmujecie, robicie',
			'Świadczymy usługi dla firm i klientów indywidualnych — pełny zakres znajdziesz na stronie „Oferta". Jeśli nie masz pewności, czy pomożemy w Twojej sprawie, zostaw pytanie, a doradzimy.',
		),
		array(
			'Gdzie was znajdę? Jak dojechać?',
			'adres, dojazd, dojecha, lokalizac, gdzie jest, gdzie was, jak trafi, mapa, siedzib, parking',
			'Nasza siedziba: ul. Przykładowa 1, 00-001 Warszawa. Parking dla klientów jest od strony podwórza. Dokładna mapa dojazdu jest na stronie „Kontakt".',
		),
		array(
			'Jakie formy płatności przyjmujecie?',
			'płatno, platno, przelew, gotówk, gotowk, kartą, karta, kartę, karte, blik, zapłac, zaplac',
			'Przyjmujemy przelew, BLIK i gotówkę. Za usługi stałe wystawiamy faktury z 7-dniowym terminem płatności.',
		),
		array(
			'Jaki jest termin realizacji?',
			'termin, realizacj, jak długo, jak dlugo, ile trwa, ile czasu, kiedy będzie, kiedy bedzie, na kiedy, czas oczekiwania',
			'Standardowy termin realizacji to 3–5 dni roboczych od potwierdzenia zamówienia. Przy większych zleceniach termin ustalamy indywidualnie.',
		),
		array(
			'Czy dajecie gwarancję? Jak zgłosić reklamację?',
			'gwaranc, reklamac, reklamow, zwrot, zepsu, usterk, napraw',
			'Tak, na nasze usługi dajemy 12 miesięcy gwarancji. Reklamację zgłosisz mailem na biuro@twojafirma.pl — odpowiadamy do 3 dni roboczych.',
		),
		array(
			'Czy wystawiacie faktury VAT?',
			'faktur, vat, rachun, nip',
			'Tak, wystawiamy faktury VAT. Podaj NIP przy zamówieniu, a fakturę wyślemy mailem po realizacji.',
		),
		array(
			'Jak umówić wizytę lub konsultację?',
			'umówi, umowi, wizyt, spotka, konsultac, rezerwac, zapisa',
			'Wizytę umówisz telefonicznie (600 000 000) albo zostaw tutaj maila z dopiskiem „konsultacja" — wrócimy z propozycją terminu w 1 dzień roboczy.',
		),
	);

	$teraz = current_time( 'mysql' );
	foreach ( $seed as $para ) {
		$zapisano = $wpdb->insert(
			$tabela,
			array(
				'pytanie'        => $para[0],
				'slowa_kluczowe' => $para[1],
				'odpowiedz'      => $para[2],
				'aktywne'        => 1,
				'utworzono'      => $teraz,
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $zapisano ) {
			chatl_log_bledu_bazy( 'chatl_seed_demo', 'wpis demo do bazy Q&A: ' . $para[0] );
		}
	}
}
