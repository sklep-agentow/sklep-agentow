<?php
/**
 * Aktywacja, tabele (dbDelta), seed: kategorie + reguły + próbka ~30 maili demo PL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tworzy/aktualizuje tabele przez dbDelta.
 * Nowa tabela = 3 miejsca: tutaj + uninstall.php + segm_sprawdz_schemat().
 */
function segm_utworz_tabele() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$p       = $wpdb->prefix;

	dbDelta(
		"CREATE TABLE {$p}segm_kategorie (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		nazwa varchar(100) NOT NULL,
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}segm_reguly (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kolejnosc int(10) unsigned NOT NULL DEFAULT 0,
		pole varchar(10) NOT NULL,
		wzorzec text NOT NULL,
		kategoria_id bigint(20) unsigned NOT NULL,
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY kolejnosc (kolejnosc)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}segm_maile (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		zrodlo varchar(10) NOT NULL DEFAULT 'demo',
		nadawca varchar(190) NOT NULL,
		temat text NOT NULL,
		tresc text NOT NULL,
		kategoria_oczekiwana varchar(100) NOT NULL DEFAULT '',
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}segm_przebiegi (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		tryb varchar(20) NOT NULL DEFAULT 'reguly',
		maili int(10) unsigned NOT NULL DEFAULT 0,
		dopasowanych int(10) unsigned NOT NULL DEFAULT 0,
		etykietowanych int(10) unsigned NOT NULL DEFAULT 0,
		trafionych int(10) unsigned NOT NULL DEFAULT 0,
		wyniki longtext NOT NULL,
		uruchomiono datetime NOT NULL,
		PRIMARY KEY  (id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}segm_skrz_przebiegi (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		tryb varchar(10) NOT NULL DEFAULT 'na_sucho',
		zrodlo varchar(10) NOT NULL DEFAULT 'reczny',
		folder_zrodlowy varchar(255) NOT NULL DEFAULT '',
		sprawdzonych int(10) unsigned NOT NULL DEFAULT 0,
		dopasowanych int(10) unsigned NOT NULL DEFAULT 0,
		przeniesionych int(10) unsigned NOT NULL DEFAULT 0,
		bledow int(10) unsigned NOT NULL DEFAULT 0,
		cofniety tinyint(1) NOT NULL DEFAULT 0,
		komunikat text NOT NULL,
		uruchomiono datetime NOT NULL,
		PRIMARY KEY  (id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}segm_skrz_ruchy (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		przebieg_id bigint(20) unsigned NOT NULL,
		uid bigint(20) unsigned NOT NULL DEFAULT 0,
		message_id varchar(190) NOT NULL DEFAULT '',
		nadawca varchar(190) NOT NULL DEFAULT '',
		temat text NOT NULL,
		folder_z varchar(255) NOT NULL DEFAULT '',
		folder_do varchar(255) NOT NULL DEFAULT '',
		stan varchar(20) NOT NULL DEFAULT 'plan',
		komunikat text NOT NULL,
		uid_docelowy bigint(20) unsigned NOT NULL DEFAULT 0,
		utworzono datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY przebieg_id (przebieg_id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$p}segm_ai_uzycie (
		dzien date NOT NULL,
		wywolania int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (dzien)
	) {$charset};"
	);
}

/**
 * Aktywacja: tabele + wersja schematu + seed + harmonogram retencji.
 */
function segm_aktywacja() {
	segm_utworz_tabele();
	update_option( 'segm_wersja_schematu', SEGM_WERSJA_SCHEMATU );
	segm_seed_demo();

	if ( ! wp_next_scheduled( 'segm_czyszczenie_probki' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'segm_czyszczenie_probki' );
	}
}

/**
 * Deaktywacja: zdejmij cron (dane zostają — właściciel może wrócić bez strat).
 */
function segm_deaktywacja() {
	wp_clear_scheduled_hook( 'segm_czyszczenie_probki' );
	// Segregacja skrzynki sięga na CUDZY serwer poczty — wyłączona wtyczka nie
	// ma prawa dalej tam chodzić, nawet gdyby wpis w cronie przetrwał.
	wp_clear_scheduled_hook( 'segm_skrzynka_cron' );
}

/**
 * Seed kategorii, reguł i próbki demo — tylko do PUSTEJ bazy.
 */
function segm_seed_demo() {
	global $wpdb;
	$teraz = current_time( 'mysql' );

	if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_kategorie" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$kategorie = array( 'Oferty', 'Faktury', 'Reklamacje', 'Spam-podejrzane', 'Reszta' );
		$id_kat    = array();
		foreach ( $kategorie as $nazwa ) {
			if ( false === $wpdb->insert(
				$wpdb->prefix . 'segm_kategorie',
				array(
					'nazwa'     => $nazwa,
					'utworzono' => $teraz,
				),
				array( '%s', '%s' )
			) ) {
				segm_log_bledu_bazy( 'segm_seed_demo', 'kategoria demo: ' . $nazwa );
				// Bez ID kategorii reguły nie mają dokąd kierować — przerywamy seed
				// zamiast wstawiać reguły z kategoria_id = 0 (osierocone wiersze).
				return;
			}
			$id_kat[ $nazwa ] = (int) $wpdb->insert_id;
		}

		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_reguly" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// Kolejność = jedyny mechanizm rozstrzygania: pierwsza pasująca wygrywa.
			$reguly    = array(
				array( 'od', 'faktury@, ksiegowosc, księgowość, rozliczenia@', 'Faktury' ),
				array( 'temat', 'faktur, rachunek, płatnoś, platnosc, nota księgowa, nota ksiegowa', 'Faktury' ),
				array( 'temat', 'reklamac, zwrot, uszkodzon, wadliw', 'Reklamacje' ),
				array( 'tresc', 'reklamuj, zepsu, nie działa, nie dziala, żądam zwrotu, zadam zwrotu', 'Reklamacje' ),
				array( 'temat', 'ofert, wycen, zapytanie', 'Oferty' ),
				array( 'tresc', 'proszę o wycenę, prosze o wycene, zainteresowan, ile kosztuje', 'Oferty' ),
				array( 'od', 'no-reply, noreply, newsletter, mailing@', 'Spam-podejrzane' ),
				array( 'temat', 'wygrałeś, wygrales, promocja, rabat, ostatnia szansa, gratis', 'Spam-podejrzane' ),
			);
			$kolejnosc = 1;
			foreach ( $reguly as $r ) {
				if ( false === $wpdb->insert(
					$wpdb->prefix . 'segm_reguly',
					array(
						'kolejnosc'    => $kolejnosc++,
						'pole'         => $r[0],
						'wzorzec'      => $r[1],
						'kategoria_id' => $id_kat[ $r[2] ],
						'utworzono'    => $teraz,
					),
					array( '%d', '%s', '%s', '%d', '%s' )
				) ) {
					segm_log_bledu_bazy( 'segm_seed_demo', 'reguła demo: ' . $r[0] . ' → ' . $r[2] );
				}
			}
		}
	}

	if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}segm_maile" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		segm_wstaw_probke_demo();
	}
}

/**
 * Wstawia próbkę 30 maili demo PL (z etykietami — raport pokazuje accuracy od razu).
 * Wołane też przez przycisk „przywróć próbkę demo".
 *
 * @return int Ile wierszy baza odrzuciła (0 = próbka kompletna).
 */
function segm_wstaw_probke_demo() {
	global $wpdb;
	$teraz = current_time( 'mysql' );

	$maile = array(
		array( 'jan.kowalski@interia.pl', 'Zapytanie ofertowe — remont biura', 'Dzień dobry, proszę o wycenę remontu biura 120 m2 w centrum Łodzi. Pozdrawiam, Jan Kowalski', 'Oferty' ),
		array( 'biuro@budmax.pl', 'Oferta współpracy przy inwestycji', 'Szanowni Państwo, jesteśmy zainteresowani stałą współpracą przy naszych inwestycjach. Ile kosztuje u Państwa obsługa miesięczna?', 'Oferty' ),
		array( 'anna.nowak@gmail.com', 'Wycena ogrodzenia', 'Proszę o wycenę ogrodzenia panelowego 45 mb z bramą. Kiedy mógłby przyjechać ktoś na pomiar?', 'Oferty' ),
		array( 'm.wisniewski@firma-abc.pl', 'Zapytanie o dostępność terminów', 'Witam, czy mają Państwo wolne terminy w październiku? Jestem zainteresowany usługą kompleksową.', 'Oferty' ),
		array( 'kontakt@nowydom.pl', 'Prośba o ofertę na wykończenie', 'Prosimy o przygotowanie oferty na wykończenie 3 mieszkań deweloperskich. Szczegóły w załączniku.', 'Oferty' ),
		array( 'tomasz.zajac@op.pl', 'Pytanie o cenę', 'Ile kosztuje wymiana instalacji w domu 90 m2? Proszę o orientacyjną wycenę.', 'Oferty' ),
		array( 'faktury@hurtownia-omega.pl', 'Faktura VAT FV/2026/08/311', 'W załączeniu przesyłamy fakturę VAT za zamówienie nr 4581. Termin płatności: 14 dni.', 'Faktury' ),
		array( 'ksiegowosc@stalprofil.pl', 'Nota księgowa 44/2026', 'Dzień dobry, przesyłamy notę księgową do umowy ramowej. Prosimy o potwierdzenie odbioru.', 'Faktury' ),
		array( 'rozliczenia@energia-plus.pl', 'Rozliczenie za sierpień', 'Przesyłamy rozliczenie zużycia energii za sierpień. Kwota do zapłaty: 1 245,80 zł.', 'Faktury' ),
		array( 'biuro@rachunkowo.pl', 'Rachunek za usługi księgowe', 'W załączniku rachunek za prowadzenie księgowości w sierpniu. Pozdrawiamy.', 'Faktury' ),
		array( 'j.malinowska@wp.pl', 'Płatność za zlecenie 112', 'Dzień dobry, potwierdzam wykonanie przelewu za zlecenie nr 112. Proszę o fakturę końcową.', 'Faktury' ),
		array( 'faktury@leasingauto.pl', 'Faktura za ratę leasingową 08/2026', 'Przesyłamy fakturę za ratę leasingową. Prosimy o terminową płatność.', 'Faktury' ),
		array( 'k.wojcik@gmail.com', 'Reklamacja — uszkodzona dostawa', 'Zamówienie dotarło z uszkodzonym narożnikiem. Żądam zwrotu kosztów albo wymiany towaru.', 'Reklamacje' ),
		array( 'p.szymanski@onet.pl', 'Zwrot towaru z zamówienia 771', 'Chcę zwrócić część towaru z zamówienia 771 — nie zgadza się kolor. Jak wygląda procedura?', 'Reklamacje' ),
		array( 'biuro@mieszkania-krak.pl', 'Usterka po montażu', 'Po Państwa montażu drzwi się nie domykają i zamek nie działa. Proszę o pilny kontakt serwisu.', 'Reklamacje' ),
		array( 'ewa.d@interia.pl', 'Pralka znowu nie działa', 'Naprawiana u Państwa pralka znowu nie działa — to już druga awaria w miesiąc. Reklamuję tę naprawę.', 'Reklamacje' ),
		array( 'marek.b@vp.pl', 'Wadliwy element zestawu', 'W dostarczonym zestawie jeden element jest wadliwy (pęknięcie). Proszę o wymianę w ramach gwarancji.', 'Reklamacje' ),
		array( 'no-reply@superokazje24.pl', 'WYGRAŁEŚ voucher 500 zł!', 'Gratulacje! Twój adres został wylosowany. Kliknij link i odbierz voucher w 24 godziny!', 'Spam-podejrzane' ),
		array( 'newsletter@modnesklepy.pl', 'Promocja -70% tylko dziś', 'Największa promocja sezonu! Rabat do -70% na wszystko. Ostatnia szansa, oferta znika o północy!', 'Spam-podejrzane' ),
		array( 'mailing@kryptoinwest.io', 'Podwoisz kapitał w tydzień', 'Sprawdzona metoda inwestycji. Gwarantowany zysk, zero ryzyka. Wejdź i zobacz, zanim usuniemy dostęp.', 'Spam-podejrzane' ),
		array( 'noreply@konkurs-online.net', 'Ostatnia szansa na nagrodę', 'Twoja nagroda czeka! Potwierdź dane osobowe w formularzu, aby odebrać gratis.', 'Spam-podejrzane' ),
		array( 'no-reply@banik-secure.com', 'Weryfikacja konta wymagana', 'Twoje konto zostanie zablokowane. Kliknij link i potwierdź dane logowania w ciągu 12 godzin.', 'Spam-podejrzane' ),
		array( 'urszula.k@gmail.com', 'Podziękowanie za sprawną realizację', 'Dziękuję za szybką i porządną robotę przy tarasie. Będę polecać znajomym!', 'Reszta' ),
		array( 'sekretariat@sp7lodz.pl', 'Prośba o przesunięcie terminu spotkania', 'Dzień dobry, czy możemy przesunąć czwartkowe spotkanie na godzinę 14:00?', 'Reszta' ),
		array( 'lukasz.p@firma-xyz.pl', 'Dokumenty do umowy', 'W załączeniu przesyłam podpisane dokumenty. Proszę o odesłanie jednego egzemplarza.', 'Reszta' ),
		array( 'kadry@budmax.pl', 'Aktualizacja danych kontaktowych', 'Informujemy o zmianie numeru telefonu do działu kadr. Nowy numer w stopce.', 'Reszta' ),
		array( 'h.zielinska@op.pl', 'Pytanie o godziny otwarcia biura', 'Czy biuro jest czynne w sobotę? Chciałabym podrzucić dokumenty osobiście.', 'Reszta' ),
		array( 'biuro@targi-budowlane.pl', 'Zaproszenie na targi branżowe', 'Zapraszamy na jesienne targi budowlane w Expo Łódź. Wstęp dla wystawców bezpłatny.', 'Reszta' ),
		array( 'adam.g@gmail.com', 'Prosba o kontakt w sprawie wyceny', 'Dzien dobry, prosze o wycene ocieplenia poddasza ok 60 m2. Najlepiej kontakt po 16.', 'Oferty' ),
		array( 'ksiegowosc@transpol.pl', 'Duplikat faktury 209/08/2026', 'Zgodnie z prośbą przesyłamy duplikat faktury. Oryginał wysłano 5 sierpnia.', 'Faktury' ),
	);

	$nieudane = 0;
	foreach ( $maile as $m ) {
		if ( false === $wpdb->insert(
			$wpdb->prefix . 'segm_maile',
			array(
				'zrodlo'               => 'demo',
				'nadawca'              => $m[0],
				'temat'                => $m[1],
				'tresc'                => $m[2],
				'kategoria_oczekiwana' => $m[3],
				'utworzono'            => $teraz,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		) ) {
			++$nieudane;
			segm_log_bledu_bazy( 'segm_wstaw_probke_demo', 'mail demo od „' . $m[0] . '"' );
		}
	}
	return $nieudane;
}
