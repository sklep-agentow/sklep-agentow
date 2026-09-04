<?php
/**
 * Aktywacja / deaktywacja / migracja schematu.
 * Zasady: dbDelta + wersja schematu w opcji; nowa tabela = TRZY miejsca
 * (tutaj, uninstall.php, migracja); symetria zapis<->sprzątanie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definicje tabel w formacie dbDelta (dwie spacje po PRIMARY KEY — wymóg dbDelta).
 */
function sklepag_tabele_sql() {
	global $wpdb;
	$c = $wpdb->get_charset_collate();
	$p = $wpdb->prefix . 'sklepag_';

	return array(
		"CREATE TABLE {$p}produkty (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nazwa varchar(200) NOT NULL,
			slug varchar(200) NOT NULL,
			opis_krotki text NOT NULL,
			opis_pelny longtext NOT NULL,
			cena_grosze int(11) unsigned NOT NULL DEFAULT 0,
			waluta char(3) NOT NULL DEFAULT 'PLN',
			plik_sciezka varchar(500) NOT NULL DEFAULT '',
			plik_rozmiar bigint(20) unsigned NOT NULL DEFAULT 0,
			plik_sha256 char(64) NOT NULL DEFAULT '',
			aktywny tinyint(1) NOT NULL DEFAULT 0,
			utworzono datetime NOT NULL,
			zmieniono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$c};",

		/* ⛔ MIGAWKA PRODUKTU (schemat 10). Produkt wolno zmienić MIĘDZY utworzeniem płatności
			a jej domknięciem — a domknięcie zapisuje do zamówienia to, co ma pod ręką. Dopóki
			czytało tabelę produktów PONOWNIE, kupujący płacił za jedno, a dostawał drugie:
			podmieniony plik, inną cenę obok kwoty faktycznie pobranej przez Stripe, nazwę
			z chwili domknięcia. Te kolumny zamrażają stan Z CHWILI ZAKUPU — jedyny, za który
			kupujący zapłacił. Puste = sesja sprzed migracji; domknięcie ma wtedy zapas. */
		"CREATE TABLE {$p}sesje_checkout (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stripe_session_id varchar(255) NOT NULL,
			produkt_id bigint(20) unsigned NOT NULL,
			produkt_nazwa varchar(200) NOT NULL DEFAULT '',
			produkt_slug varchar(200) NOT NULL DEFAULT '',
			produkt_cena_grosze bigint(20) unsigned NOT NULL DEFAULT 0,
			produkt_waluta varchar(10) NOT NULL DEFAULT '',
			produkt_plik text NULL,
			stan varchar(20) NOT NULL DEFAULT 'utworzona',
			utworzono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY stripe_session_id (stripe_session_id),
			KEY stan (stan)
		) {$c};",

		"CREATE TABLE {$p}zamowienia (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stripe_session_id varchar(255) NOT NULL,
			produkt_id bigint(20) unsigned NOT NULL,
			produkt_nazwa_kupiona varchar(200) NOT NULL,
			produkt_cena_kupiona int(11) unsigned NOT NULL DEFAULT 0,
			produkt_plik_kupiony varchar(500) NOT NULL DEFAULT '',
			email_kupujacego varchar(320) NOT NULL DEFAULT '',
			kwota_grosze int(11) unsigned NOT NULL DEFAULT 0,
			waluta char(3) NOT NULL DEFAULT 'PLN',
			status varchar(20) NOT NULL DEFAULT 'oczekuje',
			zgoda_tresc text,
			zgoda_czas datetime DEFAULT NULL,
			token_pobrania char(64) NOT NULL DEFAULT '',
			link_wygasa datetime DEFAULT NULL,
			pobrania_limit smallint(5) unsigned NOT NULL DEFAULT 10,
			pobrania_uzyte smallint(5) unsigned NOT NULL DEFAULT 0,
			mail_proby smallint(5) unsigned NOT NULL DEFAULT 0,
			mail_tresc longtext,
			dor_token_pobrania char(64) NOT NULL DEFAULT '',
			dor_link_wygasa datetime DEFAULT NULL,
			dor_mail_tresc longtext,
			dor_opinia_hash char(64) NOT NULL DEFAULT '',
			usunieto_dnia datetime DEFAULT NULL,
			payment_intent_id varchar(255) NOT NULL DEFAULT '',
			zwrocono_grosze int(11) unsigned NOT NULL DEFAULT 0,
			zwrot_czas datetime DEFAULT NULL,
			status_przed_sporem varchar(20) NOT NULL DEFAULT '',
			dane_faktury varchar(300) NOT NULL DEFAULT '',
			utworzono datetime NOT NULL,
			zmieniono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY stripe_session_id (stripe_session_id),
			KEY status (status),
			KEY utworzono (utworzono),
			KEY payment_intent_id (payment_intent_id)
		) {$c};",

		"CREATE TABLE {$p}zdarzenia (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			zamowienie_id bigint(20) unsigned DEFAULT NULL,
			typ varchar(50) NOT NULL,
			opis text NOT NULL,
			uzytkownik varchar(60) NOT NULL DEFAULT '',
			czas datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY zamowienie_id (zamowienie_id),
			KEY czas (czas)
		) {$c};",

		"CREATE TABLE {$p}logowania (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			czas datetime NOT NULL,
			login_wpisany varchar(60) NOT NULL DEFAULT '',
			wynik varchar(10) NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY czas (czas),
			KEY para_logowania (login_wpisany,ip,czas)
		) {$c};",

		"CREATE TABLE {$p}rate_limit (
			klucz varchar(64) NOT NULL,
			licznik int(11) unsigned NOT NULL DEFAULT 1,
			okno_do datetime NOT NULL,
			PRIMARY KEY  (klucz)
		) {$c};",

		"CREATE TABLE {$p}opinie (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			zamowienie_id bigint(20) unsigned NOT NULL,
			produkt_id bigint(20) unsigned NOT NULL,
			imie varchar(100) NOT NULL DEFAULT '',
			gwiazdki tinyint(1) unsigned NOT NULL DEFAULT 5,
			tytul varchar(150) NOT NULL DEFAULT '',
			tresc text,
			status varchar(20) NOT NULL DEFAULT 'zalazek',
			token_hash char(64) NOT NULL DEFAULT '',
			token_wygasa datetime DEFAULT NULL,
			zgoda_tresc text,
			zgoda_czas datetime DEFAULT NULL,
			utworzono datetime NOT NULL,
			zmieniono datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY zamowienie_id (zamowienie_id),
			KEY produkt_status (produkt_id,status)
		) {$c};",
	);
}

/**
 * Tabele i kolumny, których wymaga schemat — WYCZYTANE z tych samych definicji, które dostaje
 * `dbDelta()`. Jedno źródło: nowa kolumna w definicji jest sprawdzana bez dopisywania jej gdziekolwiek.
 *
 * @return array<string, string[]> nazwa tabeli (z prefiksem) => lista kolumn.
 */
function sklepag_schemat_oczekiwany() {
	$oczekiwane = array();
	foreach ( sklepag_tabele_sql() as $sql ) {
		if ( ! preg_match( '/CREATE TABLE\s+(\S+)\s*\(/i', $sql, $m ) ) {
			continue;
		}
		$tabela  = $m[1];
		$kolumny = array();
		foreach ( preg_split( '/\r?\n/', $sql ) as $linia ) {
			$linia = trim( $linia );
			// Kolumna = identyfikator + typ; klucze (PRIMARY/UNIQUE/KEY) i nawiasy zamykające pomijamy.
			if ( preg_match( '/^([a-z_][a-z0-9_]*)\s+(bigint|int|smallint|tinyint|varchar|char|text|longtext|mediumtext|datetime|date|timestamp|decimal)\b/i', $linia, $k ) ) {
				$kolumny[] = strtolower( $k[1] );
			}
		}
		$oczekiwane[ $tabela ] = $kolumny;
	}
	return $oczekiwane;
}

/**
 * Co w bazie NAPRAWDĘ brakuje względem definicji — pomiar PO operacji, nie założenie.
 *
 * @return string[] Braki jako „tabela” albo „tabela.kolumna”; pusta tablica = schemat kompletny.
 */
function sklepag_schemat_braki() {
	global $wpdb;
	$braki = array();
	foreach ( sklepag_schemat_oczekiwany() as $tabela => $kolumny ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nazwy z własnych definicji, odczyt schematu.
		// ⛔ esc_like() OBOWIĄZKOWY przy LIKE: `_` jest dziką kartą SQL, a nazwy naszych tabel
		// niosą po dwa (`wp_sklepag_produkty`), więc wzorzec bez escapowania pasuje do KAŻDEJ
		// tabeli tej samej długości z dowolnym znakiem na tych pozycjach — cudza tabela mogłaby
		// zostać uznana za naszą i schemat wyglądałby na kompletny. Konwencja tej wtyczki stoi
		// obok: komentarz o `esc_like()` w uninstall.php i w includes/prywatnosc.php.
		$jest = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tabela ) ) );
		if ( $jest !== $tabela ) {
			$braki[] = $tabela;
			continue;
		}
		$w_bazie = array_map( 'strtolower', (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$tabela}`", 0 ) );
		// phpcs:enable
		foreach ( $kolumny as $kolumna ) {
			if ( ! in_array( $kolumna, $w_bazie, true ) ) {
				$braki[] = $tabela . '.' . $kolumna;
			}
		}
	}
	return $braki;
}

/**
 * JEDNO miejsce budowy schematu dla aktywacji i migracji (do 0.9.38 włącznie były dwie kopie pętli;
 * scala je właśnie to wydanie, 0.9.39).
 *
 * ⛔ Znacznik wersji idzie w górę TYLKO po zmierzeniu skutku: `dbDelta()` może nie zdążyć, trafić
 *    na brak prawa ALTER albo na cudzą tabelę o tej samej nazwie — a brama „wersja == stała” zamykała
 *    się wtedy na zawsze i nieudana migracja nie powtarzała się już nigdy (A6-F1). Teraz przy brakach
 *    wersja zostaje stara (ponowienie po odstępie 5 minut, znacznik `sklepag_schemat_ponowienie`; aktywacja wtyczki kasuje znacznik), a właściciel dostaje na Pulpicie listę
 *    braków i ostatni błąd bazy. Przyczyna-matka H: nie zapisuj skutku, którego nie zmierzyłeś.
 *
 * @return bool true = schemat kompletny (wersja zapisana), false = braki (wersja NIE zapisana).
 */
function sklepag_zbuduj_schemat() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	// ⛔ `last_error` KASUJE KAŻDE KOLEJNE ZAPYTANIE (`wpdb::query()` zaczyna od `flush()`),
	// więc jeden odczyt PO całej pętli pokazywał błąd wyłącznie wtedy, gdy zawiodła OSTATNIA
	// tabela — przy siedmiu tabelach komunikat był prawie zawsze pusty, choć dokumentacja
	// obiecywała właścicielowi „ostatni błąd bazy”. Czytamy po KAŻDYM `dbDelta` i zbieramy
	// wszystkie RÓŻNE komunikaty.
	// ⛔ NIE zerujemy `last_error` przed `dbDelta`: robi to za nas pierwsze zapytanie, które
	// `dbDelta` i tak wykonuje (`DESCRIBE`), a jawne przypisanie pustki mylnie zawężało typ
	// dla analizy statycznej — porównanie wychodziło „zawsze fałszywe”.
	$bledy_bazy = array();
	foreach ( sklepag_tabele_sql() as $sql ) {
		dbDelta( $sql );
		$blad = (string) $wpdb->last_error;
		if ( '' !== $blad ) {
			$bledy_bazy[] = $blad;
		}
	}
	$blad_bazy = implode( ' | ', array_unique( $bledy_bazy ) );
	$braki     = sklepag_schemat_braki();
	if ( empty( $braki ) ) {
		update_option( 'sklepag_wersja_schematu', SKLEPAG_WERSJA_SCHEMATU, false );
		delete_option( 'sklepag_schemat_blad' );
		return true;
	}
	update_option(
		'sklepag_schemat_blad',
		array(
			'braki'      => $braki,
			'blad_bazy'  => $blad_bazy,
			'oczekiwana' => SKLEPAG_WERSJA_SCHEMATU,
			'czas'       => current_time( 'mysql' ),
		),
		false
	);
	return false;
}

function sklepag_aktywacja() {
	// ⛔ Kasujemy znacznik odstępu, żeby aktywacja próbowała ZAWSZE od razu — to jest
	// dokładnie ta droga, którą komunikat na Pulpicie podaje właścicielowi po naprawie
	// uprawnień („deaktywuj i aktywuj wtyczkę”). Bez tego musiałby czekać do 5 minut.
	delete_transient( 'sklepag_schemat_ponowienie' );
	sklepag_zbuduj_schemat();

	sklepag_utworz_strony();
	sklepag_utworz_katalog_chroniony();

	if ( ! wp_next_scheduled( 'sklepag_dociagnij_zalegle' ) ) {
		wp_schedule_event( time() + 600, 'sklepag_10min', 'sklepag_dociagnij_zalegle' );
	}

	// rewrite strony produktu + flush (strony właśnie powstały, więc slug
	// sklepu już jest); flush odbudowuje też reguły rdzenia (w tym wp-sitemap —
	// bywały zgubione: /wp-sitemap.xml dawał 404).
	if ( function_exists( 'sklepag_rejestruj_rewrite' ) ) {
		sklepag_rejestruj_rewrite();
	}
	flush_rewrite_rules( false );
}

function sklepag_deaktywacja() {
	wp_clear_scheduled_hook( 'sklepag_dociagnij_zalegle' );
	flush_rewrite_rules( false ); // Nasze reguły znikają z rejestru — sprzątamy wpisy.
}

/**
 * Migracja przy podbiciu SKLEPAG_WERSJA_SCHEMATU (dbDelta jest idempotentne). Gdy poprzednia
 * próba zostawiła braki, wersja jest stara, więc brama przepuszcza i próbujemy znowu.
 */
function sklepag_migruj_schemat_jesli_trzeba() {
	if ( (int) get_option( 'sklepag_wersja_schematu', 0 ) === SKLEPAG_WERSJA_SCHEMATU ) {
		return;
	}
	// ⛔ ODSTĘP MIĘDZY PRÓBAMI. Przy brakach wersja CELOWO zostaje stara, żeby próba się
	// powtórzyła — ale bez odstępu powtarza się przy KAŻDYM żądaniu, także anonimowym
	// frontowym, i każde takie żądanie płaci 7 × `dbDelta` (z `DESCRIBE` i ponawianym
	// `CREATE`) plus 14 zapytań pomiarowych. Na instalacji bez prawa ALTER/CREATE ten stan
	// jest TRWAŁY, więc sklep zwalniałby każdemu odwiedzającemu, dopóki właściciel nie
	// zajrzy do panelu. Ponawiamy co 5 minut; aktywacja wtyczki kasuje znacznik, więc
	// „deaktywuj i aktywuj” z komunikatu na Pulpicie działa NATYCHMIAST, bez czekania.
	if ( false !== get_transient( 'sklepag_schemat_ponowienie' ) ) {
		return;
	}
	set_transient( 'sklepag_schemat_ponowienie', 1, 5 * MINUTE_IN_SECONDS );
	if ( sklepag_zbuduj_schemat() ) {
		delete_transient( 'sklepag_schemat_ponowienie' );
	}
}

/**
 * Strony wtyczki. Sklep i Dziękujemy publikowane; Regulamin i Polityka jako
 * SZKICE-szablony (właściciel wypełnia danymi sprzedawcy i sam publikuje).
 */
function sklepag_utworz_strony() {
	$strony = array(
		'sklepag_strona_sklep'      => array( 'Sklep', '[sklepag_sklep]', 'publish' ),
		'sklepag_strona_dziekujemy' => array( 'Dziękujemy za zakup', '[sklepag_dziekujemy]', 'publish' ),
		'sklepag_strona_regulamin'  => array( 'Regulamin sklepu (SZABLON)', "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nSprzedawca: [NAZWA, ADRES, KONTAKT]\nProdukty: treści cyfrowe (wtyczki WordPressa) dostarczane natychmiast po opłaceniu.\nZgoda na natychmiastowe dostarczenie: kupujący wyraża ją przy zakupie i przyjmuje utratę prawa odstąpienia (art. 38 ust. o prawach konsumenta).\nGwarancja satysfakcji [ILE DNI — domyślnie 14]: NIEZALEŻNIE od powyższego sprzedawca zobowiązuje się dobrowolnie zwrócić pieniądze na wniosek kupującego złożony w tym terminie. To zobowiązanie PONAD ustawę, nie ustawowe prawo odstąpienia — strona produktu je obiecuje, więc regulamin musi je potwierdzać. Zwrot oznacza wygaśnięcie prawa do aktualizacji i wsparcia dla kupionej kopii.\nReklamacje: [MAIL WŁAŚCICIELA].", 'draft' ),
		'sklepag_strona_polityka'   => array( 'Polityka prywatności sklepu (SZABLON)', "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nAdministrator danych: [NAZWA, KONTAKT]\nZakres: adres e-mail kupującego, dane transakcji, dziennik zdarzeń (90 dni, w tym adres IP przy każdym pobraniu pliku); dobrowolnie podane dane do faktury (nazwa firmy, NIP, adres — cel: wystawienie faktury, podstawa: obowiązek prawny z ustawy o VAT); przy dobrowolnej opinii — imię i treść opinii, publikowane na stronie produktu wyłącznie za wyrażoną w formularzu zgodą (usunięcie/anonimizację można wykonać na żądanie).\nPodmioty przetwarzające: operator płatności Stripe; mechanizm poczty serwera.\nBoty w trybie AI wysyłają treść zapytań do dostawcy API — [UZUPEŁNIJ po włączeniu].", 'draft' ),
	);

	foreach ( $strony as $opcja => $dane ) {
		$id = (int) get_option( $opcja, 0 );
		if ( $id && get_post( $id ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_title'   => $dane[0],
				'post_content' => $dane[1],
				'post_status'  => $dane[2],
				'post_type'    => 'page',
			)
		);
		// Bez 4. argumentu $wp_error wp_insert_post zwraca int (0 = porażka) — is_wp_error tu martwe (phpstan).
		if ( $id ) {
			update_option( $opcja, $id, false );
		}
	}
}

/**
 * Katalog chroniony na pliki produktów: losowy sufiks + index.php + .htaccess
 * (Apache). Na nginx .htaccess nie działa — brama i tak jest w PHP, a instrukcja
 * wdrożenia podaje regułę location deny.
 */
function sklepag_utworz_katalog_chroniony() {
	$istniejacy = get_option( 'sklepag_katalog_chroniony', '' );
	if ( $istniejacy && is_dir( $istniejacy ) ) {
		return;
	}

	$upload  = wp_upload_dir();
	$sciezka = trailingslashit( $upload['basedir'] ) . 'sklepag-chronione-' . wp_generate_password( 16, false, false );

	if ( ! wp_mkdir_p( $sciezka ) ) {
		// Porażka mkdir też musi być widoczna — inaczej przeszłaby niezauważona.
		update_option( 'sklepag_katalog_chroniony_blad', 'mkdir nieudany — sprawdź uprawnienia uploads', false );
		return;
	}

	// Sprawdzamy zapisem, czy katalog naprawdę jest zapisywalny (uprawnienia)
	// — bez tego błąd przeszedłby niezauważony.
	$test = trailingslashit( $sciezka ) . 'index.php';
	$ok   = file_put_contents( $test, "<?php // cisza\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- katalog w uploads; WP_Filesystem (abstrakcja FTP) przy aktywacji potrafi pytać o poświadczenia.

	if ( false === $ok ) {
		// Sprzątamy świeży katalog — bez tego zostaje sierota, której uninstall nie zna.
		rmdir( $sciezka ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		update_option( 'sklepag_katalog_chroniony_blad', 'zapis nieudany — sprawdź uprawnienia uploads', false );
		return;
	}

	// Apache 2.4 (Require) i 2.2 (Deny) — hosting docelowy nieznany; brama i tak w PHP.
	$ht = file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions -- jw.: uploads, bez WP_Filesystem.
		trailingslashit( $sciezka ) . '.htaccess',
		"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
	);
	if ( false === $ht ) {
		// Ta sama kontrola co dla index.php.
		unlink( trailingslashit( $sciezka ) . 'index.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rmdir( $sciezka ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		update_option( 'sklepag_katalog_chroniony_blad', 'zapis .htaccess nieudany — sprawdź uprawnienia uploads', false );
		return;
	}

	update_option( 'sklepag_katalog_chroniony', $sciezka, false );
	delete_option( 'sklepag_katalog_chroniony_blad' );
}
