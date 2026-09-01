<?php
/**
 * Most sklepu do mechanizmu prywatności WordPressa (RODO).
 *
 * Sklep miał WŁASNĄ anonimizację kupującego (przycisk w panelu zamówienia) i nie miał
 * ani jednego wpięcia w narzędzia wbudowane: Narzędzia → Eksportuj / Usuń dane osobowe.
 * Obsługa sklepu dostawała żądanie RODO, klikała narzędzie WordPressa, dostawała raport
 * BEZ danych ze sklepu i uznawała obowiązek za wypełniony — a dane kupującego zostawały.
 * Ten plik jest tym mostem: ta sama, sprawdzona anonimizacja, wołana przez kreator.
 *
 * ⛔ Anonimizacja mieszkała w środku obsługi żądania HTTP (uprawnienia, nonce, `$_GET`,
 * `wp_die`) — nie dało się jej wywołać skądkolwiek indziej. Tu stoi jej czysta postać,
 * zwracająca WYNIK zamiast kończyć proces; panel został cienką skorupą, która ją woła.
 *
 * @package Sklep_Agentow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ile pozycji na jedną stronę eksportu. Kreator WordPressa woła eksporter PARTIAMI
 * i przekazuje numer strony — eksporter, który zawsze oddaje wszystko na stronie 1,
 * przewraca się na koncie z długą historią zakupów.
 */
const SKLEPAG_RODO_PORCJA = 100;

/* ============================================================================
 * 1. Anonimizacja zamówienia — czysta funkcja, wołana z panelu I z kasownika.
 * ========================================================================= */

/**
 * Anonimizacja kupującego przy JEDNYM zamówieniu (RODO).
 *
 * Kolejność jest kontraktem, nie stylem: najpierw ślady WTÓRNE (adres w opisach zdarzeń,
 * imię i token przy opinii), na końcu ŹRÓDŁO (adres w zamówieniu). Źródło jest kluczem
 * do znalezienia śladów, więc ginie ostatnie — inaczej powtórzenie po awarii nie miałoby
 * czego szukać. Gdy czyszczenie śladów zawiedzie, funkcja przerywa NIE TKNĄWSZY źródła.
 *
 * Adres → pusty + `usunieto_dnia`; token pobrania unieważniany (zanonimizowany klient =
 * koniec relacji). Kwoty i czasy ZOSTAJĄ — dashboard żyje.
 *
 * Ślady adresu w dzienniku zdarzeń czyszczone są po TREŚCI, w całej tabeli — także
 * w wierszach niezwiązanych z tym zamówieniem (np. wpis o eksporcie CSV zapisujący frazę
 * wyszukiwania, która bywa adresem). Żądanie dotyczy adresu człowieka, nie jednego wiersza.
 * ⛔ Dopasowanie jest ZAKOTWICZONE na granicy znaków adresu: adres jednego kupującego bywa
 * końcówką adresu innego, więc niezakotwiczona podmiana przepisywałaby cudze wpisy.
 *
 * ⚠️ ZNANA GRANICA TEJ FUNKCJI: adres zawierający `/` w części lokalnej (RFC dopuszcza,
 * w praktyce spotykany wyjątkowo) trafia do dziennika przez `wp_json_encode` jako `\/`
 * i NIE zostanie tu znaleziony — wyszukiwanie idzie po surowej postaci adresu. Taki ślad
 * zostanie w dzienniku, mimo potwierdzenia usunięcia. Nazwane świadomie, nie przeoczone:
 * domknięcie wymaga szukania obu postaci naraz i własnego przypadku testowego.
 *
 * Potwierdzenie usunięcia idzie WYŁĄCZNIE za zmierzonym stanem po zapisie, i to we
 * wszystkich trzech miejscach naraz. Przy niepowodzeniu: zdarzenie `anonimizacja_blad`
 * z nazwaniem, które miejsce nie zostało domknięte — a wołający dostaje `ok = false`
 * i ma NIE potwierdzać usunięcia osobie składającej żądanie.
 *
 * ⛔ `mail_tresc` (oryginalna treść wysłanego maila) jest CZYSZCZONA razem ze śladem
 * `dor_*`: treść niesie pełną kopię danych do faktury (nazwa firmy, NIP, adres — blok
 * „DANE DO FAKTURY" składany przy wysyłce) oraz sam adres e-mail. Diagnoza zamówienia
 * po anonimizacji obywa się bez niej — relacja z kupującym jest zakończona (resend i tak
 * odmawia bez adresu).
 *
 * ⛔ `$zachowaj_dane_faktury` NIE jest wygodą. Kasownik RODO musi zostawić nazwę, NIP
 * i adres z faktury: to obowiązek prawny (art. 6 ust. 1 lit. c RODO — ustawa o VAT,
 * art. 106e ust. 1 pkt 3 wymaga adresu nabywcy na fakturze).
 * Przycisk w panelu działa jak dotąd i czyści WSZYSTKO — właściciel klika go świadomie,
 * na własną odpowiedzialność, i tego zachowania ta zmiana nie rusza.
 *
 * @param int  $id                    Id zamówienia.
 * @param bool $zachowaj_dane_faktury Czy zostawić kolumnę `dane_faktury` (wymóg podatkowy).
 * @return array{ok:bool,etap:string,powod:string,zachowana_faktura:bool,slady_dziennik:int,slady_opinia:int}
 */
function sklepag_anonimizuj_zamowienie( $id, $zachowaj_dane_faktury = false ) {
	global $wpdb;
	$id = (int) $id;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nazwy tabel z prefiksu; obsługa żądania RODO musi trafiać w świeży stan.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT id, email_kupujacego, dane_faktury FROM {$pz} WHERE id = %d", $id ) );
	if ( ! $zam ) {
		return array(
			'ok'                => false,
			'etap'              => 'brak_zamowienia',
			'powod'             => 'Zamówienie nie istnieje.',
			'zachowana_faktura' => false,
			'slady_dziennik'    => 0,
			'slady_opinia'      => 0,
		);
	}

	$email = (string) $zam->email_kupujacego;
	$teraz = current_time( 'mysql' );

	/*
	 * ⛔ KOLEJNOSC JEST CZESCIA NAPRAWY, nie stylem.
	 *
	 * Najpierw slady WTORNE (dziennik zdarzen, opinia), na koncu ZRODLO (adres
	 * w zamowieniu). Powod: adresu w dzienniku szukamy PO TRESCI, wiec potrzebny
	 * jest do tego sam adres — a on znika razem ze zrodlem.
	 *
	 * Przy odwrotnej kolejnosci powtorzenie operacji po awarii dziennika bylo
	 * NIEMOZLIWE: zrodlo bylo juz czyste, `$email` wracalo puste, warunek pomijal
	 * czyszczenie dziennika w calosci, a pomiar skutku patrzyl tylko na zamowienie
	 * i meldowal sukces. Adres kupujacego zostawal w `opis` wierszy zdarzen NA STALE,
	 * z potwierdzeniem usuniecia dla wlasciciela.
	 */
	$ok_zdarzenia = true;
	if ( '' !== $email ) {
		/*
		 * ⛔ SZUKAMY PO TRESCI ADRESU, NIE PO PRZYPISANIU DO ZAMOWIENIA.
		 *
		 * Zadanie usuniecia dotyczy ADRESU CZLOWIEKA, a nie jednego wiersza. Adres trafia
		 * do dziennika takze w wierszach BEZ `zamowienie_id` — np. wpis o eksporcie CSV
		 * zapisuje uzyte filtry, a wsrod nich fraze wyszukiwania, ktora bywa e-mailem.
		 *
		 * `esc_like()` obowiazkowy: `_` i `%` sa dzikimi kartami SQL, a adres moze je zawierac.
		 *
		 * ⛔ DOPASOWANIE MUSI BYC ZAKOTWICZONE. `REPLACE` w SQL zamienia KAZDY podciag,
		 * a adres jednego kupujacego bywa koncowka adresu innego (`ala@x.pl` siedzi
		 * w `bala@x.pl`). SQL sluzy wylacznie do ZAWEZENIA kandydatow, dopasowanie liczy PHP.
		 */
		$znacznik  = '[usunięto — RODO]';
		$kandydaci = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, opis FROM {$wpdb->prefix}sklepag_zdarzenia WHERE opis LIKE %s",
				'%' . $wpdb->esc_like( $email ) . '%'
			)
		);
		/* ⛔ JEDNA definicja granicy, dwa uzycia: czyszczenie i pomiar MUSZA dopasowywac identycznie. */
		$wzor = '/(?<!' . SKLEPAG_GRANICA_ADRESU . ')' . preg_quote( $email, '/' ) . '(?!' . SKLEPAG_GRANICA_ADRESU . ')/';
		foreach ( (array) $kandydaci as $k ) {
			$nowy = preg_replace( $wzor, $znacznik, (string) $k->opis );
			if ( null === $nowy ) {
				/* ⛔ `null` to AWARIA silnika wyrazen, nie „brak dopasowania”. Ciche
					potraktowanie jej jak braku adresu zostawiloby dane i zameldowalo sukces. */
				$ok_zdarzenia = false;
				continue;
			}
			if ( $nowy === (string) $k->opis ) {
				/* Brak zakotwiczonego wystapienia = to NIE jest adres tego kupujacego. */
				continue;
			}
			if ( false === $wpdb->update( $wpdb->prefix . 'sklepag_zdarzenia', array( 'opis' => $nowy ), array( 'id' => (int) $k->id ) ) ) {
				$ok_zdarzenia = false;
			}
		}
	}

	/*
	 * ⛔ PRZERWANIE PRZED ZRODLEM, gdy slad wtorny nie zostal usuniety.
	 *
	 * Sama kolejnosc nie wystarcza: gdyby kod poleciał dalej i wyczyscil adres
	 * w zamowieniu mimo nieudanego czyszczenia dziennika, powtorzenie operacji
	 * NIE mialoby juz czego szukac, a skutek wyszedlby „ok". Adres kupujacego
	 * zostalby w dzienniku NA STALE — ta sama wada, tylko przesunieta o krok.
	 */
	if ( false === $ok_zdarzenia ) {
		sklepag_zdarzenie( $id, 'anonimizacja_blad', 'nie udało się usunąć adresu z dziennika zdarzeń — adres w zamówieniu NIETKNIĘTY, żeby powtórzenie miało z czego odtworzyć zakres' );
		return array(
			'ok'                => false,
			'etap'              => 'dziennik',
			'powod'             => 'Nie udało się usunąć adresu z dziennika zdarzeń. Adres w zamówieniu jest NIETKNIĘTY, więc powtórzenie operacji domknie całość — ale część śladów w dzienniku mogła już zostać usunięta.',
			'zachowana_faktura' => false,
			'slady_dziennik'    => 0,
			'slady_opinia'      => 0,
		);
	}

	/* Symetria RODO dla mechanizmu opinii — TAKZE przed zrodlem. Wynik nie rozstrzyga
		(false znaczy tu rowniez „zamowienie nie ma opinii"), rozstrzyga pomiar nizej. */
	sklepag_anonimizuj_opinie( $id );

	/* ⛔ Jedyna roznica miedzy trybem panelu a trybem kasownika RODO: kolumna faktury.
		Reszta zapisu jest IDENTYCZNA — inaczej byloby to drugie, rozjezdzajace sie
		czyszczenie zamiast jednego, sprawdzonego. */
	$czysc_fakture = $zachowaj_dane_faktury ? '' : "dane_faktury='', ";

	$ok_zam = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET email_kupujacego='', {$czysc_fakture}mail_tresc=NULL, usunieto_dnia=%s, token_pobrania='', link_wygasa=NULL, dor_token_pobrania='', dor_link_wygasa=NULL, dor_mail_tresc=NULL, dor_opinia_hash='', zmieniono=%s WHERE id=%d",
			$teraz,
			$teraz,
			$id
		)
	);

	/*
	 * ⛔ POMIAR SKUTKU, NIE WYWOLANIA. Wczesniej ta sciezka zapisywala do dziennika
	 * „kupujacy zanonimizowany" i meldowala „ok" BEZ sprawdzenia, czy ktorykolwiek
	 * z zapisow sie udal — czyli NIEZREALIZOWANE ZADANIE RODO z falszywym dowodem.
	 *
	 * Dlaczego odczyt, a nie sam kod powrotu: `$wpdb->query()` zwraca 0 (nie false),
	 * gdy nic sie nie zmienilo — a tak wyglada POWTORNA anonimizacja tego samego
	 * zamowienia. Podobnie `sklepag_anonimizuj_opinie()` zwraca false takze wtedy,
	 * gdy zamowienie po prostu NIE MA opinii. Rozstrzyga STAN PO ZAPISIE.
	 */
	$po = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego, usunieto_dnia, token_pobrania, mail_tresc, dane_faktury, dor_token_pobrania, dor_mail_tresc, dor_opinia_hash FROM {$pz} WHERE id = %d", $id ) );

	/*
	 * ⛔ SKUTEK LICZY SIE WE WSZYSTKICH TRZECH MIEJSCACH, w ktorych siedzial adres
	 * albo dane kupujacego. Pomiar tylko w tabeli zamowien meldowal sukces, gdy adres
	 * zostawal w dzienniku zdarzen. Pomiar MUSI byc dokladnie tak szeroki i tak samo
	 * zakotwiczony jak czyszczenie — inaczej mierzy co innego, niz naprawia.
	 */
	$slady_dziennik = 0;
	if ( '' !== $email ) {
		$do_sprawdzenia = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT opis FROM {$wpdb->prefix}sklepag_zdarzenia WHERE opis LIKE %s",
				'%' . $wpdb->esc_like( $email ) . '%'
			)
		);
		$wzor_pomiar    = '/(?<!' . SKLEPAG_GRANICA_ADRESU . ')' . preg_quote( $email, '/' ) . '(?!' . SKLEPAG_GRANICA_ADRESU . ')/';
		foreach ( (array) $do_sprawdzenia as $tresc ) {
			$trafienie = preg_match( $wzor_pomiar, (string) $tresc );
			if ( false === $trafienie || 1 === $trafienie ) {
				/* Awaria silnika liczy sie JAK SLAD: nie wolno jej czytac jako „czysto”. */
				++$slady_dziennik;
			}
		}
	}
	$slady_opinia = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id = %d AND ( imie <> '' OR token_hash <> '' )",
			$id
		)
	);
	// phpcs:enable

	/* Pomiar TAK SZEROKI jak czyszczenie (v9): tokeny, ORYGINAŁ treści maila i ślad dor_*
		też muszą zniknąć — mail_tresc niesie kopię danych kupującego, dor_mail_tresc to
		KOPIA treści maila (NULL po czyszczeniu; (string) NULL = ''). */

	/*
	 * ⛔ POMIAR MUSI PASOWAĆ DO TRYBU, a nie do jednego z dwóch.
	 *
	 * `dane_faktury` długo była czyszczona i NIE MIERZONA — jedyna pozostała kolumna
	 * z danymi osobowymi (nazwa firmy, NIP, adres) poza pomiarem skutku. Gdyby jej
	 * czyszczenie nie doszło, funkcja meldowała sukces, panel pisał „Kupujący
	 * zanonimizowany", a właściciel potwierdzał człowiekowi usunięcie danych, które
	 * nadal leżą w bazie. To ta sama klasa co potwierdzenie bez pomiaru, o kolumnę dalej.
	 *
	 * ⛔ Warunek NIE MOŻE brzmieć „kolumna ma być pusta" bezwarunkowo: w trybie kasownika
	 * RODO ta kolumna ma ZOSTAĆ (obowiązek podatkowy). Twardy pomiar na pusto zamieniłby
	 * wymóg prawa w meldunek o nieudanym zapisie i kasownik nie domknąłby ani jednego
	 * wniosku. Mierzymy więc to, co ten tryb miał ZROBIĆ.
	 */
	$faktura_ok = $zachowaj_dane_faktury || ( $po && '' === (string) $po->dane_faktury );

	$skutek_ok = $po
		&& '' === (string) $po->email_kupujacego
		&& ! empty( $po->usunieto_dnia )
		&& '' === (string) $po->token_pobrania
		&& '' === (string) $po->mail_tresc
		&& $faktura_ok
		&& '' === (string) $po->dor_token_pobrania
		&& '' === (string) $po->dor_mail_tresc
		&& '' === (string) $po->dor_opinia_hash
		&& 0 === $slady_dziennik
		&& 0 === $slady_opinia;

	if ( false === $ok_zam || ! $skutek_ok ) {
		sklepag_zdarzenie(
			$id,
			'anonimizacja_blad',
			'anonimizacja NIE doszła do skutku — '
			. 'adres w zamówieniu: ' . ( ( $po && '' === (string) $po->email_kupujacego ) ? 'usunięty' : 'ZOSTAŁ' )
			. ', treść maila: ' . ( ( $po && '' === (string) $po->mail_tresc ) ? 'usunięta' : 'ZOSTAŁA' )
			/* W trybie kasownika faktura ma ZOSTAĆ, więc nie ma o czym meldować — inaczej
				dziennik nazywałby wymóg podatkowy niedomkniętym miejscem. */
			. ( $zachowaj_dane_faktury ? '' : ', dane do faktury: ' . ( $faktura_ok ? 'usunięte' : 'ZOSTAŁY' ) )
			. ', ślady w dzienniku: ' . (int) $slady_dziennik
			. ', dane opinii: ' . (int) $slady_opinia
		);
		return array(
			'ok'                => false,
			'etap'              => 'zapis',
			'powod'             => 'Dane kupującego mogą nadal być w bazie.',
			'zachowana_faktura' => false,
			'slady_dziennik'    => (int) $slady_dziennik,
			'slady_opinia'      => (int) $slady_opinia,
		);
	}

	$zachowana_faktura = $zachowaj_dane_faktury && '' !== (string) $zam->dane_faktury;

	sklepag_zdarzenie(
		$id,
		'anonimizacja',
		'kupujący zanonimizowany na żądanie (RODO); token pobrania i opinia unieważnione'
		. ( $zachowana_faktura ? '; dane do faktury ZACHOWANE — obowiązek podatkowy (ustawa o VAT)' : '' )
	);

	return array(
		'ok'                => true,
		'etap'              => 'gotowe',
		'powod'             => '',
		'zachowana_faktura' => $zachowana_faktura,
		'slady_dziennik'    => 0,
		'slady_opinia'      => 0,
	);
}

/* ============================================================================
 * 2. Eksporter — Narzędzia → Eksportuj dane osobowe.
 * ========================================================================= */

/**
 * Wycinek JEDNEGO źródła na WSPÓLNEJ numeracji stron kreatora.
 *
 * ⛔ Najgroźniejszy przypadek to DOKŁADNIE pełna strona z poprzedniego źródła: naiwne
 * `OFFSET $offset` przeskakiwałoby wtedy początek następnego źródła i paczka wychodziła
 * z dziurą — najgorszy możliwy błąd w eksporcie danych osobowych, bo niewidoczny dla
 * obu stron. Dlatego przesunięcie liczymy względem liczby wierszy w źródłach PRZED.
 *
 * @param int $ile_w_zrodle   Wszystkie wiersze tego źródła.
 * @param int $offset_globalny Przesunięcie strony w całości danych.
 * @param int $wierszy_przed  Suma wierszy w źródłach poprzedzających.
 * @param int $wolne_sloty    Ile pozycji zmieści się jeszcze na tej stronie.
 * @return array{0:int,1:int}|null Limit i przesunięcie w TYM źródle, albo null.
 */
function sklepag_rodo_wycinek( $ile_w_zrodle, $offset_globalny, $wierszy_przed, $wolne_sloty ) {
	if ( $wolne_sloty <= 0 || $ile_w_zrodle <= 0 ) {
		return null;
	}
	$lokalny = $offset_globalny - $wierszy_przed;
	if ( $lokalny >= $ile_w_zrodle ) {
		return null; // Ta strona jest już za tym źródłem.
	}
	return array( $wolne_sloty, max( 0, $lokalny ) );
}

/**
 * Eksporter: zamówienia, opinie i sesje płatności powiązane z adresem.
 *
 * @param string $email  Adres z wniosku.
 * @param int    $strona Numer strony (od 1) — kreator woła nas partiami.
 * @return array{data:array,done:bool} Format wymagany przez WordPressa.
 */
function sklepag_rodo_eksporter( $email, $strona = 1 ) {
	global $wpdb;
	$email  = trim( (string) $email );
	$strona = max( 1, (int) $strona );
	$offset = ( $strona - 1 ) * SKLEPAG_RODO_PORCJA;
	$dane   = array();

	/* ⛔ Pusty adres NIE JEST wnioskiem „o wszystko": zanonimizowane zamówienia mają
		`email_kupujacego = ''`, więc puste zapytanie wydałoby je co do jednego. */
	if ( '' === $email ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	$po = $wpdb->prefix . 'sklepag_opinie';
	$ps = $wpdb->prefix . 'sklepag_sesje_checkout';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nazwy tabel z prefiksu; obsługa wniosku RODO czyta świeży stan.
	$ile_zam = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE email_kupujacego = %s", $email ) );
	$ile_opi = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$po} WHERE zamowienie_id IN ( SELECT id FROM {$pz} WHERE email_kupujacego = %s )", $email ) );
	$ile_ses = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ps} WHERE stripe_session_id IN ( SELECT stripe_session_id FROM {$pz} WHERE email_kupujacego = %s AND stripe_session_id <> '' )", $email ) );

	$przed = 0;

	// --- Źródło 1: zamówienia.
	$w = sklepag_rodo_wycinek( $ile_zam, $offset, $przed, SKLEPAG_RODO_PORCJA - count( $dane ) );
	if ( null !== $w ) {
		$zamowienia = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, stripe_session_id, payment_intent_id, produkt_nazwa_kupiona, kwota_grosze, waluta, status, dane_faktury, utworzono FROM {$pz} WHERE email_kupujacego = %s ORDER BY id ASC LIMIT %d OFFSET %d",
				$email,
				$w[0],
				$w[1]
			),
			ARRAY_A
		);
		foreach ( (array) $zamowienia as $z ) {
			$pola = array(
				array(
					'name'  => 'Numer zamówienia',
					'value' => (string) (int) $z['id'],
				),
				array(
					'name'  => 'Data zakupu',
					'value' => (string) $z['utworzono'],
				),
				array(
					'name'  => 'Adres e-mail',
					'value' => $email,
				),
				array(
					'name'  => 'Kupiony produkt',
					'value' => (string) $z['produkt_nazwa_kupiona'],
				),
				array(
					'name'  => 'Kwota',
					'value' => number_format_i18n( (int) $z['kwota_grosze'] / 100, 2 ) . ' ' . (string) $z['waluta'],
				),
				array(
					'name'  => 'Status',
					'value' => (string) $z['status'],
				),
			);
			if ( '' !== (string) $z['dane_faktury'] ) {
				$pola[] = array(
					'name'  => 'Dane do faktury',
					'value' => (string) $z['dane_faktury'],
				);
			}
			/*
			 * ⛔ IDENTYFIKATORY U OPERATORA PŁATNOŚCI WYDAJEMY, bo je TRZYMAMY.
			 *
			 * Wcześniej `stripe_session_id` stał w zapytaniu i nie trafiał do ani jednego pola —
			 * odczyt bez odbiorcy, sugerujący, że coś jest eksportowane, choć nie było.
			 * Po nich Stripe odtwarza tożsamość kupującego, więc pominięcie ich w paczce
			 * czyniło ją niepełną, a zamówienie zostaje z nimi także po anonimizacji.
			 */
			$pola[] = array(
				'name'  => 'Identyfikator sesji płatności u operatora (Stripe)',
				'value' => (string) $z['stripe_session_id'],
			);
			if ( '' !== (string) $z['payment_intent_id'] ) {
				$pola[] = array(
					'name'  => 'Identyfikator płatności u operatora (Stripe)',
					'value' => (string) $z['payment_intent_id'],
				);
			}
			$dane[] = array(
				'group_id'    => 'sklepag_zamowienia',
				'group_label' => 'Sklep Agentów — zamówienia',
				'item_id'     => 'sklepag-zamowienie-' . (int) $z['id'],
				'data'        => $pola,
			);
		}
	}
	$przed += $ile_zam;

	// --- Źródło 2: opinie (wiązane przez zamówienie, bo opinia nie nosi adresu).
	$w = sklepag_rodo_wycinek( $ile_opi, $offset, $przed, SKLEPAG_RODO_PORCJA - count( $dane ) );
	if ( null !== $w ) {
		$opinie = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, zamowienie_id, imie, gwiazdki, tytul, tresc, status, utworzono FROM {$po} WHERE zamowienie_id IN ( SELECT id FROM {$pz} WHERE email_kupujacego = %s ) ORDER BY id ASC LIMIT %d OFFSET %d",
				$email,
				$w[0],
				$w[1]
			),
			ARRAY_A
		);
		foreach ( (array) $opinie as $o ) {
			$dane[] = array(
				'group_id'    => 'sklepag_opinie',
				'group_label' => 'Sklep Agentów — opinie o produktach',
				'item_id'     => 'sklepag-opinia-' . (int) $o['id'],
				'data'        => array(
					array(
						'name'  => 'Zamówienie',
						'value' => (string) (int) $o['zamowienie_id'],
					),
					array(
						'name'  => 'Podpis pod opinią',
						'value' => (string) $o['imie'],
					),
					array(
						'name'  => 'Ocena',
						'value' => (string) (int) $o['gwiazdki'],
					),
					array(
						'name'  => 'Tytuł',
						'value' => (string) $o['tytul'],
					),
					array(
						'name'  => 'Treść',
						'value' => (string) $o['tresc'],
					),
					array(
						'name'  => 'Status',
						'value' => (string) $o['status'],
					),
					array(
						'name'  => 'Data',
						'value' => (string) $o['utworzono'],
					),
				),
			);
		}
	}
	$przed += $ile_opi;

	// --- Źródło 3: sesje płatności (nośnik techniczny, ale wiąże kupującego ze Stripe).
	$w = sklepag_rodo_wycinek( $ile_ses, $offset, $przed, SKLEPAG_RODO_PORCJA - count( $dane ) );
	if ( null !== $w ) {
		$sesje = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, stripe_session_id, stan, utworzono FROM {$ps} WHERE stripe_session_id IN ( SELECT stripe_session_id FROM {$pz} WHERE email_kupujacego = %s AND stripe_session_id <> '' ) ORDER BY id ASC LIMIT %d OFFSET %d",
				$email,
				$w[0],
				$w[1]
			),
			ARRAY_A
		);
		foreach ( (array) $sesje as $s ) {
			$dane[] = array(
				'group_id'    => 'sklepag_sesje',
				'group_label' => 'Sklep Agentów — sesje płatności',
				'item_id'     => 'sklepag-sesja-' . (int) $s['id'],
				'data'        => array(
					array(
						'name'  => 'Identyfikator sesji u operatora płatności',
						'value' => (string) $s['stripe_session_id'],
					),
					array(
						'name'  => 'Stan',
						'value' => (string) $s['stan'],
					),
					array(
						'name'  => 'Data',
						'value' => (string) $s['utworzono'],
					),
				),
			);
		}
	}
	$przed += $ile_ses;
	// phpcs:enable

	/* `done` liczone z SUMY wierszy, nie z „strona wyszła krótsza": przy dokładnie pełnej
		ostatniej stronie kreator dostawałby jeszcze jedno, puste wołanie. */
	return array(
		'data' => $dane,
		'done' => ( $offset + count( $dane ) ) >= $przed,
	);
}

/**
 * Rejestracja eksportera w kreatorze WordPressa.
 *
 * @param array $eksportery Lista.
 * @return array
 */
function sklepag_rejestruj_eksporter( $eksportery ) {
	$eksportery['sklep-agentow'] = array(
		'exporter_friendly_name' => 'Sklep Agentów — zamówienia, opinie, płatności',
		'callback'               => 'sklepag_rodo_eksporter',
	);
	return $eksportery;
}
add_filter( 'wp_privacy_personal_data_exporters', 'sklepag_rejestruj_eksporter' );

/* ============================================================================
 * 3. Kasownik — Narzędzia → Usuń dane osobowe.
 * ========================================================================= */

/**
 * Kasownik: anonimizuje wszystkie zamówienia kupującego.
 *
 * ⛔ Zamówienie NIE JEST kasowane, tylko anonimizowane — kwoty i daty trzymają
 * statystyki właściciela, a dane do faktury (nazwa, NIP, adres) zostają, bo wymaga
 * ich prawo podatkowe. Kreator dostaje to wprost w `items_retained` i w komunikacie:
 * właściciel odpowiada tym tekstem osobie składającej wniosek, więc nie wolno mu
 * potwierdzać usunięcia, którego nie było.
 *
 * ⛔ Stronicowanie: wiersze WYPADAJĄ ze zbioru po anonimizacji (adres robi się pusty),
 * więc kolejne wołanie bierze po prostu następną porcję — bez `OFFSET`, który
 * przeskakiwałby dane. Gdy CAŁA porcja się nie udała, kończymy `done = true`
 * z głośnym komunikatem: pętla „strona za stroną" na tym samym, nieusuwalnym
 * zamówieniu nie skończyłaby się nigdy.
 *
 * @param string $email  Adres z wniosku.
 * @param int    $strona Numer strony (od 1) — nieużywany, patrz wyżej.
 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
 */
function sklepag_rodo_kasownik( $email, $strona = 1 ) {
	global $wpdb;
	unset( $strona );
	$email = trim( (string) $email );
	$pz    = $wpdb->prefix . 'sklepag_zamowienia';

	/* ⛔ Pusty adres pasowałby do KAŻDEGO zanonimizowanego zamówienia. */
	if ( '' === $email ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array( 'Wniosek bez adresu e-mail — sklep nie ma czego szukać.' ),
			'done'           => true,
		);
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; obsługa wniosku RODO czyta świeży stan.
	$zamowienia = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id FROM {$pz} WHERE email_kupujacego = %s ORDER BY id ASC LIMIT %d",
			$email,
			SKLEPAG_RODO_PORCJA
		)
	);

	if ( ! $zamowienia ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array( 'Nie znaleziono w sklepie żadnego zamówienia powiązanego z tym adresem.' ),
			'done'           => true,
		);
	}

	$usuniete  = 0;
	$zachowane = 0;
	$nieudane  = array();
	foreach ( $zamowienia as $z ) {
		$wynik = sklepag_anonimizuj_zamowienie( (int) $z->id, true );
		if ( ! empty( $wynik['ok'] ) ) {
			++$usuniete;
			if ( ! empty( $wynik['zachowana_faktura'] ) ) {
				++$zachowane;
			}
			continue;
		}
		$nieudane[] = (int) $z->id;
	}

	$zostalo = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pz} WHERE email_kupujacego = %s", $email ) );
	// phpcs:enable

	$komunikaty = array();
	if ( $usuniete > 0 ) {
		$komunikaty[] = sprintf(
			'Sklep Agentów: zanonimizowano dane kupującego (zamówień: %d) — adres e-mail, treść wysłanego maila, ślady adresu w dzienniku zdarzeń oraz podpis i token przy opinii. '
			. 'Link do pobrania pliku przestał działać.',
			$usuniete
		);
	}
	if ( $zachowane > 0 ) {
		$komunikaty[] = sprintf(
			'ZACHOWANO dane do faktury — nazwę, NIP i adres (zamówień: %d). Powód: obowiązek prawny (art. 6 ust. 1 lit. c RODO); ustawa o VAT wymaga adresu nabywcy na fakturze (art. 106e ust. 1 pkt 3). '
			. 'Tych danych nie wolno usunąć na wniosek o usunięcie danych.',
			$zachowane
		);
	}
	/*
	 * ⛔ IDENTYFIKATORY U OPERATORA PŁATNOŚCI ZOSTAJĄ — i wnioskodawca ma się o tym dowiedzieć.
	 *
	 * Wcześniej ten komunikat brzmiał „zamówienie zostaje w statystykach BEZ DANYCH OSOBOWYCH
	 * (kwota, data, nazwa produktu)" i było to NIEPRAWDĄ: zamówienie zostaje także ze
	 * `stripe_session_id` i `payment_intent_id`, a wiersz w `sesje_checkout` zostaje w całości.
	 * Eksporter wydaje te identyfikatory jako dane osobowe — więc kasownik nie ma prawa milczeć
	 * o tym, że ich nie usuwa. To była sprzeczność wewnątrz jednego pliku.
	 *
	 * Dlaczego ZOSTAJĄ, a nie giną: nie jest to wygoda, tylko warunek poprawności obsługi
	 * pieniędzy. `zakup.php` odnajduje zamówienie po `payment_intent_id` przy ZWROCIE
	 * i przy obciążeniu zwrotnym, a `stripe_session_id` niesie idempotencję domknięcia zakupu
	 * (powtórzony webhook Stripe trafia w istniejące zamówienie zamiast tworzyć drugie).
	 * Wyczyszczenie ich sprawiłoby, że zwrot zgłoszony PO wniosku RODO nie miałby czego
	 * znaleźć — pieniądze wyszłyby poza ewidencją sklepu.
	 */
	$komunikaty[] = 'ZACHOWANO także identyfikatory płatności u operatora (Stripe): numer sesji przy zamówieniu i w rejestrze płatności oraz numer płatności. '
		. 'Powód: po nich sklep rozpoznaje zwrot i reklamację bankową zgłoszone PO tym wniosku — bez nich zwrot pieniędzy nie miałby czego znaleźć. '
		. 'Same w sobie nie zawierają imienia, adresu ani e-maila; dane po stronie operatora płatności usuwa się wnioskiem złożonym u niego.';
	$komunikaty[] = sprintf(
		'Poza tym zamówienie zostaje w statystykach bez danych osobowych (kwota, data, nazwa produktu). Adresy IP w dzienniku zdarzeń i w dzienniku logowań nie są powiązane z adresem e-mail — '
		. 'usuwa je retencja po %d dniach.',
		SKLEPAG_RETENCJA_DNI
	);
	if ( $nieudane ) {
		$komunikaty[] = sprintf(
			'⛔ NIE UDAŁO SIĘ zanonimizować zamówień o numerach: %s. Powód jest zapisany w dzienniku zdarzeń (wpisy „anonimizacja_blad"). '
			. 'Nie potwierdzaj usunięcia osobie składającej wniosek, dopóki nie powtórzysz operacji.',
			implode( ', ', $nieudane )
		);
	}

	/* ⛔ `done` nie może zależeć wyłącznie od „czy coś jeszcze zostało": porcja, w której
		nie udało się NIC, zostawia te same wiersze i kreator wołałby nas w kółko. */
	$done = ( 0 === $zostalo ) || ( 0 === $usuniete );

	/*
	 * ⛔ `items_retained` jest PRAWDĄ przy każdej udanej anonimizacji, nie tylko przy fakturze:
	 * identyfikatory płatności zostają ZAWSZE. Wcześniej zamówienie bez danych do faktury
	 * wracało z `items_retained = false`, czyli kreator WordPressa meldował właścicielowi
	 * „nie zachowano niczego" — a zostawały dwa identyfikatory prowadzące do kupującego.
	 */
	return array(
		'items_removed'  => $usuniete > 0,
		'items_retained' => ( $usuniete > 0 ) || ( $zachowane > 0 ) || ! empty( $nieudane ),
		'messages'       => $komunikaty,
		'done'           => $done,
	);
}

/**
 * Rejestracja kasownika w kreatorze WordPressa.
 *
 * @param array $kasowniki Lista.
 * @return array
 */
function sklepag_rejestruj_kasownik( $kasowniki ) {
	$kasowniki['sklep-agentow'] = array(
		'eraser_friendly_name' => 'Sklep Agentów — zamówienia, opinie, płatności',
		'callback'             => 'sklepag_rodo_kasownik',
	);
	return $kasowniki;
}
add_filter( 'wp_privacy_personal_data_erasers', 'sklepag_rejestruj_kasownik' );

/* ============================================================================
 * 4. Podpowiedź do polityki prywatności.
 * ========================================================================= */

/**
 * Gotowy akapit do polityki prywatności — też narzędzie WordPressa, nie nasze.
 *
 * Właściciel dostaje tekst mówiący to, co sklep robi NAPRAWDĘ: co zbiera, po co,
 * na jakiej podstawie i jak długo trzyma. Szablon strony polityki (tworzony przy
 * aktywacji) mówi to samo — te dwa teksty muszą zostać zgodne.
 *
 * @return void
 */
function sklepag_polityka_prywatnosci() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$tresc  = '<p>' . esc_html( 'Sklep zapisuje adres e-mail kupującego, dane zamówienia (produkt, kwota, data, status płatności) oraz — jeśli zostały podane dobrowolnie — dane do faktury: nazwę, NIP i adres. Płatność obsługuje Stripe jako osobny podmiot przetwarzający; sklep przechowuje identyfikator sesji płatności.' ) . '</p>';
	$tresc .= '<p>' . esc_html( 'Przy dobrowolnej opinii zapisywany jest podpis (imię) i treść opinii — publikowane na stronie produktu wyłącznie za zgodą wyrażoną w formularzu.' ) . '</p>';
	$tresc .= '<p>' . esc_html( sprintf( 'Dziennik zdarzeń i dziennik logowań (w tym adresy IP przy pobraniu pliku) są usuwane automatycznie po %d dniach.', SKLEPAG_RETENCJA_DNI ) ) . '</p>';
	$tresc .= '<p>' . esc_html( 'Podstawy: wykonanie umowy sprzedaży (art. 6 ust. 1 lit. b RODO) oraz obowiązek prawny (art. 6 ust. 1 lit. c RODO) — ustawa o VAT wymaga zachowania danych nabywcy z faktury, więc te dane pozostają także po wniosku o usunięcie.' ) . '</p>';
	$tresc .= '<p>' . esc_html( 'Można poprosić o kopię swoich danych albo o ich usunięcie — administrator obsłuży wniosek wbudowanymi narzędziami WordPressa (Narzędzia → Eksportuj / Usuń dane osobowe).' ) . '</p>';
	wp_add_privacy_policy_content( 'Sklep Agentów', $tresc );
}
add_action( 'admin_init', 'sklepag_polityka_prywatnosci' );
