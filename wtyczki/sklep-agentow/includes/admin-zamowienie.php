<?php
/**
 * Widok pojedynczego zamówienia (układ wzorowany na WooCommerce i Stripe):
 * LEWA kolumna: status (badge) → dane zamówienia/kupującego/płatności →
 * treść wysłanego maila + „Wyślij ponownie" TUŻ OBOK (wzorzec Stripe receipt).
 * PRAWA kolumna: oś czasu zdarzeń tego zamówienia.
 * Akcje: resend (regeneruje token+termin — stary link mógł wygasnąć) oraz
 * anonimizacja kupującego na żądanie RODO (email→NULL +
 * usunieto_dnia; kwoty i czasy zostają — dashboard żyje).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Akcja: ponowna wysyłka maila ---------- */

add_action( 'admin_post_sklepag_resend', 'sklepag_obsluz_resend' );

/**
 * Resend generuje ŚWIEŻY token i termin (72 h) — stary link mógł wygasnąć,
 * a to właśnie najczęstszy powód ponownej wysyłki. Skrót w bazie nadpisany =
 * stary link przestaje działać (jeden ważny token na zamówienie).
 * Wyścig dwóch równoczesnych resendów: wygrywa ostatni zapis skrótu, wcześniejszy
 * mail niesie martwy link — naprawialne kolejnym resendem, akceptowalne dla akcji ręcznej.
 */
/*
 * ⛔ DŁUG NAZWANY, NIE UKRYTY: ta ścieżka ma PIĘĆ zapisów `$wpdb->query()`, których wynik
 * nie jest sprawdzany (rotacja tokenu przed wysyłką, status po sukcesie, status mail_blad,
 * przywrót treści maila, przywrót skrótu opinii). Zapis KOMPENSACJI tokenu jest od 0.9.28
 * mierzony (`$przywrocono` steruje statusem, treścią wpisu dziennika i przywrotami) —
 * pozostałe wciąż zapisują skutek bez pomiaru; ta sama klasa co naprawiona niżej
 * w anonimizacji, spłacona częściowo. Do tego samego długu należy kompensacja licznika pobrań po
 * nieudanej wysyłce: przywraca wartość ze zdjęcia `$zam` z wejścia, nie ze stanu bazy — pobranie,
 * które trafi w okno między odczytem a zapisem (stary link jeszcze żyje), ginie z licznika
 * (kupujący dostaje jedno pobranie ponad limit). Okno wąskie, skutek mały, klasa ta sama.
 *
 * Świadomie nietknięte w tej zmianie: to inna ścieżka, z własnym ryzykiem i własnym zestawem
 * przypadków (ponowna wysyłka, kompensacja po nieudanym mailu), więc wymaga własnych testów.
 * Dopisanie jej do naprawy anonimizacji rozdmuchałoby zakres jednej zmiany.
 */
function sklepag_obsluz_resend() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_resend' );

	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; akcja admina na żywych danych.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE id = %d", $id ) );

	if ( ! $zam ) {
		wp_die( 'Zamówienie nie istnieje.', 'Sklep Agentów', 404 );
	}
	if ( '' === (string) $zam->email_kupujacego ) {
		wp_die( 'Zamówienie bez adresu e-mail (kupujący zanonimizowany?) — nie ma dokąd wysłać.', 'Sklep Agentów', 400 );
	}
	// Tylko stany PO domknięciu wysyłki: `mail_w_toku` = webhook
	// właśnie domyka (resend nadpisałby token bez CAS), `oczekuje`/`oplacone` =
	// silnik zaraz wyśle sam. Utknięte `mail_w_toku` odzyskuje reconciliation
	// (stale-claim po 5 min) i przestawia status — wtedy resend się odblokuje.
	if ( in_array( $zam->status, array( 'zwrocone', 'sporne' ), true ) ) {
		// Zamknięte finansowo: ponowna wysyłka niedostępna — nie „domykanie automatyczne”, tylko decyzja.
		wp_die( 'Zamówienie zwrócone albo sporne — ponowna wysyłka linku nie jest dostępna. Link, który kupujący już ma, działa do końca swojej ważności.', 'Sklep Agentów', 409 );
	}
	if ( ! in_array( $zam->status, array( 'mail_wyslany', 'mail_blad' ), true ) ) {
		wp_die( 'Zamówienie jest właśnie domykane automatycznie (status: ' . esc_html( $zam->status ) . ') — ponowna wysyłka dostępna, gdy silnik skończy.', 'Sklep Agentów', 409 );
	}

	// Świeży token + termin, treść odbudowana ze SNAPSHOTU zamówienia.
	$token = bin2hex( random_bytes( 32 ) );
	$teraz = current_time( 'mysql' );
	$link  = admin_url( 'admin-post.php' ) . '?action=sklepag_pobierz&z=' . (int) $zam->id . '&t=' . $token;

	// Link do opinii jak w wysyłce automatycznej: zalążek odświeża token
	// w stanie 'zalazek', a dla opinii już przysłanej zwraca '' — mail idzie
	// wtedy bez akapitu oceny. Bez tego resend gubił kupującemu drogę do opinii.
	// Zapamiętujemy skrót tokenu opinii SPRZED rotacji. Nieudana wysyłka nie może zabrać
	// kupującemu także drogi do opinii — to ta sama wada co przy linku pobrania, tylko na
	// drugim tokenie: stary link z pierwszego maila przestałby działać, a nowy nie dotarł.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$opinia_hash_przed = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d", (int) $zam->id ) );
	$token_op          = sklepag_opinia_zalazek( (int) $zam->id, (int) $zam->produkt_id );
	$link_op           = '' !== $token_op ? admin_url( 'admin-post.php' ) . '?action=sklepag_ocen&z=' . (int) $zam->id . '&t=' . $token_op : '';
	// Skrót opinii PO rotacji zalążka — przy DORĘCZENIU wejdzie do śladu dor_* (schemat v9),
	// dokładnie jak w wysyłce automatycznej (zakup.php).
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$opinia_hash_po = (string) $wpdb->get_var( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", (int) $zam->id ) );

	$produkt_snapshot        = new stdClass();
	$produkt_snapshot->nazwa = $zam->produkt_nazwa_kupiona;
	// Slug po id produktu: akapit gwarancji w mailu bierze treść z danych produktu (jak wysyłka
	// automatyczna); produkt skasowany = brak sluga = mail bez tego akapitu, bez błędu.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki, nazwa z prefiksu WP.
	$produkt_snapshot->slug = (string) $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}sklepag_produkty WHERE id=%d", (int) $zam->produkt_id ) );
	$tresc                  = sklepag_zbuduj_mail( $produkt_snapshot, $zam, $link, $link_op );

	// Świeży link = świeży termin ORAZ świeży limit pobrań. Instrukcja kieruje po ten przycisk
	// kupującego z WYCZERPANYM limitem — bez zerowania `pobrania_uzyte` nowy link dawał 410
	// od pierwszego kliknięcia, a mail obiecywał „pozwala na 10 pobrań".
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=0, mail_tresc=%s, zmieniono=%s WHERE id=%d",
			hash( 'sha256', $token ),
			gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_LINK_GODZIN * HOUR_IN_SECONDS ),
			$tresc,
			$teraz,
			(int) $zam->id
		)
	);

	$wyslano = sklepag_wyslij_mail_sklepu(
		$zam->email_kupujacego,
		'Twój zakup: ' . $zam->produkt_nazwa_kupiona . ' — potwierdzenie i pobranie',
		$tresc,
		sklepag_zbuduj_mail_html( $produkt_snapshot, $zam, $link, $link_op )
	);

	if ( $wyslano ) {
		// Zamówienia zwrócone i sporne (SKLEPAG_STATUSY_ZAMKNIETE) nie docierają tutaj — bramka wyżej
		// odmawia im ponownej wysyłki (wp_die 409), mail dla nich NIE wychodzi. Warunek NOT IN zostaje
		// jako druga warstwa: gdyby bramka kiedyś zniknęła, jedno kliknięcie „Wyślij ponownie" na
		// zamówieniu zwróconym kasowałoby znacznik zwrotu i wracało je do liczby sprzedaży na Pulpicie.
		// DORĘCZONE — ślad dor_* (schemat v9) zapisany razem ze statusem, jak w zakup.php:
		// kompensacja przyszłej nieudanej rotacji przywraca stan z maila, który DOSZEDŁ.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SKLEPAG_STATUSY_ZAMKNIETE to stała wtyczki, nie wejście użytkownika (jak SKLEPAG_STATUSY_OPLACONE w admin-dashboard.php).
		$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET status='mail_wyslany', dor_token_pobrania=token_pobrania, dor_link_wygasa=link_wygasa, dor_mail_tresc=mail_tresc, dor_opinia_hash=%s, zmieniono=%s WHERE id=%d AND status NOT IN (" . SKLEPAG_STATUSY_ZAMKNIETE . ')', $opinia_hash_po, $teraz, (int) $zam->id ) );
		sklepag_zdarzenie( (int) $zam->id, 'mail_resend', 'ręczna ponowna wysyłka do: ' . $zam->email_kupujacego . ' (świeży link)' );
	} else {
		// KOMPENSACJA: nowy token wszedł do bazy PRZED wysyłką, więc nieudany mail zostawiłby
		// kupującego bez niczego — stary link daje 403, a nowy nigdy nie dotarł. Przywracamy
		// stan ostatniej DORĘCZONEJ wysyłki (ślad dor_* z v9); zapas dla zamówień sprzed v9:
		// zdjęcie z wejścia handlera (jak dotąd). ⛔ CAS na WŁASNY świeży skrót: gdyby między
		// naszą rotacją a kompensacją token przejął cron (stale-claim), jego stanu nie ruszamy
		// — ta sama zasada własności co w zakup.php.
		$token_wlasnosci = hash( 'sha256', $token );
		$ma_dor          = isset( $zam->dor_token_pobrania ) && '' !== (string) $zam->dor_token_pobrania;
		$zrodlo_token    = $ma_dor ? (string) $zam->dor_token_pobrania : (string) $zam->token_pobrania;
		$zrodlo_uzyte    = ( (string) $zam->token_pobrania === $zrodlo_token ) ? (int) $zam->pobrania_uzyte : 0;
		$zrodlo_termin   = $ma_dor ? $zam->dor_link_wygasa : $zam->link_wygasa;
		$przywrocono     = 0;
		if ( '' !== $zrodlo_token ) {
			if ( null === $zrodlo_termin ) {
				// Kolumna `link_wygasa` jest DATETIME NULL — prepare() z %s zrobiłby z NULL-a pusty
				// string (data zerowa albo błąd w trybie ścisłym), więc NULL zapisujemy dosłownie.
				$przywrocono = (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=NULL, pobrania_uzyte=%d, zmieniono=%s WHERE id=%d AND token_pobrania=%s",
						$zrodlo_token,
						$zrodlo_uzyte,
						$teraz,
						(int) $zam->id,
						hash( 'sha256', $token )
					)
				);
			} else {
				$przywrocono = (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$pz} SET token_pobrania=%s, link_wygasa=%s, pobrania_uzyte=%d, zmieniono=%s WHERE id=%d AND token_pobrania=%s",
						$zrodlo_token,
						(string) $zrodlo_termin,
						$zrodlo_uzyte,
						$teraz,
						(int) $zam->id,
						hash( 'sha256', $token )
					)
				);
			}
		}
		if ( $przywrocono > 0 ) {
			$token_wlasnosci = $zrodlo_token;
		}
		// Stan i licznik prób osobno od kompensacji tokenu — status wolno ruszyć tylko na
		// zamówieniu nie-zamkniętym finansowo (druga warstwa po bramce 409) i tylko pod
		// dowodem własności tokenu (nie przestawiamy statusu cudzej, doręczonej wysyłki).
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SKLEPAG_STATUSY_ZAMKNIETE to stała wtyczki, nie wejście użytkownika (jak SKLEPAG_STATUSY_OPLACONE w admin-dashboard.php).
				"UPDATE {$pz} SET status='mail_blad', mail_proby=mail_proby+1, zmieniono=%s WHERE id=%d AND status NOT IN (" . SKLEPAG_STATUSY_ZAMKNIETE . ') AND token_pobrania=%s',
				$teraz,
				(int) $zam->id,
				$token_wlasnosci
			)
		);
		// Treść maila i skrót opinii też wracają — ale wyłącznie gdy kompensacja tokenu przeszła
		// (własność potwierdzona): panel ma pokazywać treść maila, który DOSZEDŁ.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( $przywrocono > 0 ) {
			$zrodlo_tresc  = $ma_dor ? (string) $zam->dor_mail_tresc : (string) $zam->mail_tresc;
			$zrodlo_opinia = $ma_dor ? (string) $zam->dor_opinia_hash : $opinia_hash_przed;
			$wpdb->query( $wpdb->prepare( "UPDATE {$pz} SET mail_tresc=%s WHERE id=%d", $zrodlo_tresc, (int) $zam->id ) );
			if ( '' !== $zrodlo_opinia ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_opinie SET token_hash=%s WHERE zamowienie_id=%d AND status='zalazek'", $zrodlo_opinia, (int) $zam->id ) );
			}
		}
		// phpcs:enable
		// Treść wpisu pod POMIAREM skutku: „przywrócony" wolno napisać tylko, gdy CAS przeszedł
		// — inaczej panel twierdziłby, że kupujący ma link, którego w bazie nie ma.
		if ( $przywrocono > 0 ) {
			sklepag_zdarzenie( (int) $zam->id, 'mail_blad', 'ręczna ponowna wysyłka nieudana (wp_mail zwrócił false) — przywrócony stan ostatniej doręczonej wysyłki (link pobrania, treść maila, link do opinii)' );
		} else {
			sklepag_zdarzenie( (int) $zam->id, 'mail_przejety', 'ręczna ponowna wysyłka nieudana, a token przejął w międzyczasie inny proces — jego stan jest aktualny, niczego nie przywrócono' );
		}
	}
	// phpcs:enable

	wp_safe_redirect( add_query_arg( 'sklepag_resend', $wyslano ? 'ok' : 'blad', sklepag_url_zamowienia( (int) $zam->id ) ) );
	exit;
}

/* ---------- Akcja: anonimizacja kupującego (RODO) ---------- */

add_action( 'admin_post_sklepag_anonimizuj', 'sklepag_obsluz_anonimizacje' );

/**
 * Anonimizacja kupującego na żądanie (RODO).
 *
 * Kolejność jest kontraktem, nie stylem: najpierw ślady WTÓRNE (adres w opisach zdarzeń,
 * imię i token przy opinii), na końcu ŹRÓDŁO (adres w zamówieniu). Źródło jest kluczem
 * do znalezienia śladów, więc ginie ostatnie — inaczej powtórzenie po awarii nie miałoby
 * czego szukać. Gdy czyszczenie śladów zawiedzie, funkcja przerywa NIE TKNĄWSZY źródła.
 *
 * Adres → pusty + `usunieto_dnia`; token pobrania unieważniany (zanonimizowany klient =
 * koniec relacji). Kwoty i czasy ZOSTAJĄ — dashboard żyje.
 *
 * Ślady adresu w dzienniku zdarzeń czyszczone są po TREŚCI, w całej tabeli — także w wierszach
 * niezwiązanych z tym zamówieniem (np. wpis o eksporcie CSV zapisujący frazę wyszukiwania,
 * która bywa adresem). Żądanie dotyczy adresu człowieka, nie pojedynczego wiersza.
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
 * z nazwaniem, które miejsce nie zostało domknięte, oraz `wp_die` z ostrzeżeniem, żeby
 * NIE potwierdzać usunięcia osobie składającej żądanie.
 *
 * ⛔ `mail_tresc` jest w zapytaniu, bo pole bywa potrzebne przy diagnozie zamówienia,
 * ale ta funkcja go NIE zmienia — treść maila nie zawiera adresu w postaci innej niż
 * ta czyszczona wyżej.
 */
function sklepag_obsluz_anonimizacje() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}
	check_admin_referer( 'sklepag_anonimizuj' );

	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; akcja admina.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT id, email_kupujacego, mail_tresc FROM {$pz} WHERE id = %d", $id ) );
	if ( ! $zam ) {
		wp_die( 'Zamówienie nie istnieje.', 'Sklep Agentów', 404 );
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
	 *
	 * Teraz kazda porazka zostawia zrodlo nietkniete, wiec powtorzenie ma z czego
	 * odtworzyc caly zakres pracy.
	 */
	$ok_zdarzenia = true;
	if ( '' !== $email ) {
		/*
		 * ⛔ SZUKAMY PO TRESCI ADRESU, NIE PO PRZYPISANIU DO ZAMOWIENIA.
		 *
		 * Zadanie usuniecia dotyczy ADRESU CZLOWIEKA, a nie jednego wiersza. Adres trafia
		 * do dziennika takze w wierszach BEZ `zamowienie_id` — np. wpis o eksporcie CSV
		 * zapisuje uzyte filtry, a wsrod nich fraze wyszukiwania, ktora bywa e-mailem
		 * (wyszukiwarka zamowien nazywa sie wprost „Szukaj po e-mailu”). Czyszczenie
		 * ograniczone do `zamowienie_id = %d` zostawialoby taki slad, a wtyczka i tak
		 * meldowalaby usuniecie.
		 *
		 * `esc_like()` obowiazkowy: `_` i `%` sa dzikimi kartami SQL, a adres moze je zawierac.
		 */
		/*
		 * ⛔ DOPASOWANIE MUSI BYC ZAKOTWICZONE. `REPLACE` w SQL zamienia KAZDY podciag,
		 * a adres jednego kupujacego bywa koncowka adresu innego (`ala@x.pl` siedzi
		 * w `bala@x.pl`). Czyszczenie jednej osoby przepisywaloby wtedy wpis dotyczacy
		 * DRUGIEJ — czyli naprawiajac wyciek, psulibysmy cudze dane.
		 *
		 * Dlatego dopasowanie liczone jest w PHP, z granica na znakach dozwolonych
		 * w adresie: podmieniamy tylko wystapienia, przed ktorymi i za ktorymi NIE MA
		 * kolejnego znaku adresu. SQL sluzy wylacznie do ZAWEZENIA kandydatow.
		 */
		$znacznik  = '[usunięto — RODO]';
		$kandydaci = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, opis FROM {$wpdb->prefix}sklepag_zdarzenia WHERE opis LIKE %s",
				'%' . $wpdb->esc_like( $email ) . '%'
			)
		);
		/*
		 * ⛔ KOTWICA MUSI POKRYWAC PELNY ZBIOR ZNAKOW ADRESU, nie wygodny podzbior.
		 * RFC 5322 dopuszcza w czesci lokalnej takze `!#$%&'*+-/=?^_`{|}~`, wiec kotwica
		 * z samych liter, cyfr i `._%+-` przepuszczalaby adresy typu `!x@a.pl` — i wracalaby
		 * ta sama korupcja cudzych wpisow, ktora ta naprawa zamyka.
		 * ⛔ JEDNA definicja, dwa uzycia: czyszczenie i pomiar MUSZA dopasowywac identycznie.
		 */
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
				/* Brak zakotwiczonego wystapienia = to NIE jest adres tego kupujacego.
					Wiersz zostaje nietkniety — celowo. */
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
	 * NIE mialoby juz czego szukac — `$email` wracaloby puste, warunek pominalby
	 * dziennik, licznik sladow nie mialby wzorca, a skutek wyszedlby „ok".
	 * Adres kupujacego zostalby w dzienniku NA STALE. To ta sama wada, ktora
	 * naprawiamy, tylko przesunieta o jeden krok — dlatego stoi tu twarde wyjscie.
	 *
	 * Zrodlo jest KLUCZEM do sladow wtornych, wiec ginie ostatnie i tylko wtedy,
	 * gdy slady wtorne juz zniknely.
	 */
	if ( false === $ok_zdarzenia ) {
		sklepag_zdarzenie( (int) $zam->id, 'anonimizacja_blad', 'nie udało się usunąć adresu z dziennika zdarzeń — adres w zamówieniu NIETKNIĘTY, żeby powtórzenie miało z czego odtworzyć zakres' );
		wp_die(
			'Anonimizacja NIE doszła do skutku: nie udało się usunąć adresu z dziennika zdarzeń. Adres w zamówieniu jest NIETKNIĘTY, więc powtórzenie operacji domknie całość — ale część śladów w dzienniku mogła już zostać usunięta. Nie potwierdzaj usunięcia osobie, która złożyła żądanie, dopóki nie powtórzysz operacji i nie zobaczysz potwierdzenia.',
			'Sklep Agentów',
			500
		);
	}

	/* Symetria RODO dla mechanizmu opinii — TAKZE przed zrodlem. Wynik nie rozstrzyga
		(false znaczy tu rowniez „zamowienie nie ma opinii"), rozstrzyga pomiar nizej. */
	sklepag_anonimizuj_opinie( (int) $zam->id );

	$ok_zam = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$pz} SET email_kupujacego='', dane_faktury='', usunieto_dnia=%s, token_pobrania='', link_wygasa=NULL, dor_token_pobrania='', dor_link_wygasa=NULL, dor_mail_tresc=NULL, dor_opinia_hash='', zmieniono=%s WHERE id=%d",
			$teraz,
			$teraz,
			(int) $zam->id
		)
	);
	// phpcs:enable

	/*
	 * ⛔ POMIAR SKUTKU, NIE WYWOLANIA. Wczesniej ta funkcja zapisywala do dziennika
	 * „kupujacy zanonimizowany" i meldowala wlascicielowi „ok" BEZ sprawdzenia, czy
	 * ktorykolwiek z zapisow sie udal. Przy porazce zapisu wlasciciel odpowiadal
	 * czlowiekowi, ze jego dane usunieto, a e-mail zostawal w bazie — czyli
	 * NIEZREALIZOWANE ZADANIE RODO z falszywym dowodem, ze zostalo zrealizowane.
	 *
	 * Dlaczego odczyt, a nie sam kod powrotu: `$wpdb->query()` zwraca 0 (nie false),
	 * gdy nic sie nie zmienilo — a tak wyglada POWTORNA anonimizacja tego samego
	 * zamowienia. Traktowanie zera jak awarii dawaloby falszywy alarm. Podobnie
	 * `sklepag_anonimizuj_opinie()` zwraca false takze wtedy, gdy zamowienie po
	 * prostu NIE MA opinii — to normalny przypadek, wiec jej wynik nie rozstrzyga.
	 * Rozstrzyga STAN PO ZAPISIE: e-mail ma byc pusty, `usunieto_dnia` ustawione.
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nazwy tabel z prefiksu; akcja admina.
	$po = $wpdb->get_row( $wpdb->prepare( "SELECT email_kupujacego, usunieto_dnia, token_pobrania, dor_token_pobrania, dor_mail_tresc, dor_opinia_hash FROM {$pz} WHERE id = %d", (int) $zam->id ) );

	/*
	 * ⛔ SKUTEK LICZY SIE WE WSZYSTKICH TRZECH MIEJSCACH, w ktorych siedzial adres
	 * albo dane kupujacego. Pomiar tylko w tabeli
	 * zamowien meldowal sukces, gdy adres zostawal w dzienniku zdarzen.
	 *
	 * `esc_like()` przy LIKE jest OBOWIAZKOWY — `_` i `%` sa dzikimi kartami SQL,
	 * a adres e-mail moze je zawierac.
	 */
	$slady_dziennik = 0;
	if ( '' !== $email ) {
		/* Pomiar MUSI byc dokladnie tak szeroki jak czyszczenie — inaczej nazwa kontroli
			(„brak sladow adresu”) obiecuje wiecej, niz kontrola sprawdza. */
		/* Pomiar zakotwiczony DOKLADNIE tak samo jak czyszczenie — inaczej liczylby
			cudze adresy jako nasze slady i nigdy nie zszedlby do zera. */
		$do_sprawdzenia = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT opis FROM {$wpdb->prefix}sklepag_zdarzenia WHERE opis LIKE %s",
				'%' . $wpdb->esc_like( $email ) . '%'
			)
		);
		/* Ta sama granica co przy czyszczeniu — inaczej pomiar mierzylby co innego niz naprawa. */
		/* TA SAMA definicja co przy czyszczeniu — stala wtyczki, zeby nie dalo sie rozjechac. */
		$wzor_pomiar = '/(?<!' . SKLEPAG_GRANICA_ADRESU . ')' . preg_quote( $email, '/' ) . '(?!' . SKLEPAG_GRANICA_ADRESU . ')/';
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
			(int) $zam->id
		)
	);
	// phpcs:enable

	/* Pomiar TAK SZEROKI jak czyszczenie (v9): tokeny i ślad dor_* też muszą zniknąć —
		dor_mail_tresc to KOPIA treści maila (NULL po czyszczeniu; (string) NULL = ''). */
	$skutek_ok = $po
		&& '' === (string) $po->email_kupujacego
		&& ! empty( $po->usunieto_dnia )
		&& '' === (string) $po->token_pobrania
		&& '' === (string) $po->dor_token_pobrania
		&& '' === (string) $po->dor_mail_tresc
		&& '' === (string) $po->dor_opinia_hash
		&& 0 === $slady_dziennik
		&& 0 === $slady_opinia;

	/* `$ok_zdarzenia` nie jest tu juz sprawdzane: jego porazka konczy sciezke WYZEJ,
		zanim cokolwiek tkniemy w zamowieniu. Powtorzenie warunku byloby martwym kodem
		(phpstan: identical.alwaysFalse) i sugerowaloby ochrone, ktorej to miejsce nie daje. */
	if ( false === $ok_zam || ! $skutek_ok ) {
		sklepag_zdarzenie(
			(int) $zam->id,
			'anonimizacja_blad',
			'anonimizacja NIE doszła do skutku — '
			. 'adres w zamówieniu: ' . ( ( $po && '' === (string) $po->email_kupujacego ) ? 'usunięty' : 'ZOSTAŁ' )
			. ', ślady w dzienniku: ' . (int) $slady_dziennik
			. ', dane opinii: ' . (int) $slady_opinia
		);
		wp_die(
			'Anonimizacja NIE doszła do skutku — dane kupującego mogą nadal być w bazie. Nie potwierdzaj usunięcia osobie, która złożyła żądanie, dopóki nie powtórzysz operacji.',
			'Sklep Agentów',
			500
		);
	}

	sklepag_zdarzenie( (int) $zam->id, 'anonimizacja', 'kupujący zanonimizowany na żądanie (RODO); token pobrania i opinia unieważnione' );

	wp_safe_redirect( add_query_arg( 'sklepag_anonimizacja', 'ok', sklepag_url_zamowienia( (int) $zam->id ) ) );
	exit;
}

/* ---------- Render ---------- */

/**
 * Adres widoku zamówienia (strona zarejestrowana bez pozycji w menu).
 *
 * @param int $id Id zamówienia.
 */
function sklepag_url_zamowienia( $id ) {
	return admin_url( 'admin.php?page=sklepag-zamowienie&zamowienie=' . (int) $id );
}

/**
 * Callback strony „Zamówienie" (ukryta — wejście linkiem z tabeli dashboardu).
 */
function sklepag_admin_zamowienie() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.', 'Sklep Agentów', 403 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt identyfikatora widoku read-only.
	$id = isset( $_GET['zamowienie'] ) ? absint( $_GET['zamowienie'] ) : 0;

	global $wpdb;
	$pz = $wpdb->prefix . 'sklepag_zamowienia';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; widok na żywo.
	$zam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pz} WHERE id = %d", $id ) );
	// phpcs:enable

	echo '<div class="wrap sklepag-zamowienie">';
	echo '<h1>Zamówienie ' . ( $zam ? '#' . (int) $zam->id : '' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=sklepag' ) ) . '" class="page-title-action">← wróć do listy</a></h1>';

	if ( ! $zam ) {
		echo '<div class="notice notice-error"><p>Zamówienie nie istnieje — mogło zostać usunięte.</p></div></div>';
		return;
	}

	// Komunikaty po akcjach (redirect z admin-post).
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- komunikaty po redirekcie, bez mutacji.
	if ( isset( $_GET['sklepag_resend'] ) ) {
		echo 'ok' === $_GET['sklepag_resend']
			? '<div class="notice notice-success is-dismissible"><p>Mail wysłany ponownie ze świeżym linkiem pobrania (ważny ' . (int) round( SKLEPAG_LINK_GODZIN / 24 ) . ' dni).</p></div>'
			: '<div class="notice notice-error"><p>Ponowna wysyłka NIE powiodła się — sprawdź konfigurację poczty serwera.</p></div>';
	}
	if ( isset( $_GET['sklepag_anonimizacja'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Kupujący zanonimizowany: adres usunięty, token pobrania unieważniony; kwoty i czasy pozostały w statystykach.</p></div>';
	}
	// phpcs:enable

	$zanonimizowane = ! empty( $zam->usunieto_dnia );

	echo '<div class="sklepag-zamowienie-kolumny">';

	// LEWA kolumna.
	echo '<div class="sklepag-zamowienie-glowna">';

	echo '<div class="postbox sklepag-box"><div class="inside">';
	echo '<p class="sklepag-zamowienie-status">Status: ' . sklepag_badge_statusu( $zam->status ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge budowany z escapowanych części.
	echo '<table class="widefat striped sklepag-zamowienie-dane"><tbody>';
	echo '<tr><td>Data zakupu (czas webhooka)</td><td>' . esc_html( $zam->utworzono ) . '</td></tr>';
	echo '<tr><td>Kupujący</td><td>' . ( $zanonimizowane ? '<em>zanonimizowany (' . esc_html( $zam->usunieto_dnia ) . ')</em>' : esc_html( $zam->email_kupujacego ) ) . '</td></tr>';
	echo '<tr><td>Produkt (snapshot z chwili zakupu)</td><td>' . esc_html( $zam->produkt_nazwa_kupiona ) . '</td></tr>';
	// Dane do faktury podane przez kupującego (0.9.25) — anonimizacja RODO czyści je razem z e-mailem.
	echo '<tr><td>Dane do faktury</td><td>' . ( $zanonimizowane ? '<em>zanonimizowane</em>' : ( '' !== (string) ( $zam->dane_faktury ?? '' ) ? esc_html( $zam->dane_faktury ) : '<span class="sklepag-szary">nie podano (zakup prywatny)</span>' ) ) . '</td></tr>';
	echo '<tr><td>Kwota</td><td>' . esc_html( number_format( $zam->kwota_grosze / 100, 2, ',', ' ' ) . ' ' . $zam->waluta ) . ' <span class="sklepag-szary">(tryb testowy)</span></td></tr>';
	echo '<tr><td>Sesja Stripe</td><td><code>' . esc_html( $zam->stripe_session_id ) . '</code></td></tr>';
	echo '<tr><td>Pobrania</td><td>' . esc_html( (int) $zam->pobrania_uzyte . ' / ' . (int) $zam->pobrania_limit ) . ( $zam->link_wygasa ? ' · link ważny do ' . esc_html( $zam->link_wygasa ) : ' · link unieważniony' ) . '</td></tr>';
	// Kolumna zgoda_czas jest w GMT — panel pokazuje czas lokalny strony, ten sam, który dostaje kupujący w mailu.
	echo '<tr><td>Zgoda na dostarczenie treści cyfrowej</td><td>' . esc_html( $zam->zgoda_czas ? get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) . ' (czas lokalny)' : '—' ) . '</td></tr>';
	echo '</tbody></table>';
	echo '</div></div>';

	// Treść maila + akcje TUŻ OBOK (wzorzec Stripe „Resend receipt").
	echo '<div class="postbox sklepag-box"><div class="inside">';
	echo '<h2 class="sklepag-box-tytul">Treść wysłanego maila <span class="sklepag-szary">(podgląd treści — nie kopia z Twojej skrzynki)</span></h2>';
	echo $zam->mail_tresc
		? '<pre class="sklepag-mail-tresc">' . esc_html( $zam->mail_tresc ) . '</pre>'
		: '<p class="description">Treść maila jeszcze nie powstała (zamówienie niedomknięte).</p>';
	// Przycisk tylko w stanach, w których akcja go przyjmie (mail_wyslany, mail_blad) — wcześniej pokazywał się
	// też na zamówieniu zwróconym/spornym, a kliknięcie kończyło się odmową o „domykaniu automatycznym”.
	if ( ! $zanonimizowane && '' !== (string) $zam->email_kupujacego && in_array( $zam->status, array( 'mail_wyslany', 'mail_blad' ), true ) ) {
		echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_resend&zamowienie=' . (int) $zam->id ), 'sklepag_resend' ) ) . '" class="button button-primary">Wyślij ponownie (świeży link)</a></p>';
	}
	echo '</div></div>';

	// RODO.
	echo '<div class="postbox sklepag-box"><div class="inside">';
	echo '<h2 class="sklepag-box-tytul">Dane osobowe (RODO)</h2>';
	if ( $zanonimizowane ) {
		echo '<p>Kupujący zanonimizowany ' . esc_html( $zam->usunieto_dnia ) . ' — adres usunięty, kwoty i czasy pozostały w statystykach.</p>';
	} else {
		echo '<p class="description">Na żądanie kupującego: usuwa adres e-mail i unieważnia link pobrania; kwoty i czasy zostają (statystyki sprzedaży). Operacja NIEODWRACALNA.</p>';
		echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sklepag_anonimizuj&zamowienie=' . (int) $zam->id ), 'sklepag_anonimizuj' ) ) . '" class="button sklepag-button-uwaga" onclick="return confirm(\'Anonimizacja jest NIEODWRACALNA: adres e-mail zniknie, link pobrania przestanie działać. Kontynuować?\');">Anonimizuj kupującego</a></p>';
	}
	echo '</div></div>';

	echo '</div>'; // Koniec lewej.

	// PRAWA kolumna: oś czasu zdarzeń zamówienia.
	echo '<div class="sklepag-zamowienie-boczna"><div class="postbox sklepag-box"><div class="inside">';
	echo '<h2 class="sklepag-box-tytul">Oś czasu</h2>';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- nazwa tabeli z prefiksu; oś czasu na żywo.
	$zdarzenia = $wpdb->get_results( $wpdb->prepare( "SELECT czas, typ, opis, uzytkownik FROM {$wpdb->prefix}sklepag_zdarzenia WHERE zamowienie_id = %d ORDER BY id DESC LIMIT 50", (int) $zam->id ) );
	// phpcs:enable
	if ( empty( $zdarzenia ) ) {
		echo '<p class="description">Brak zdarzeń dla tego zamówienia.</p>';
	} else {
		echo '<ul class="sklepag-os-czasu">';
		foreach ( $zdarzenia as $z ) {
			echo '<li><span class="sklepag-os-czas">' . esc_html( $z->czas ) . '</span><br><strong>' . esc_html( $z->typ ) . '</strong>'
				. ( '' !== $z->uzytkownik ? ' <span class="sklepag-badge sklepag-badge-admin">' . esc_html( $z->uzytkownik ) . '</span>' : '' )
				. ( '' !== $z->opis ? '<br><span class="sklepag-szary">' . esc_html( mb_substr( $z->opis, 0, 200 ) ) . '</span>' : '' ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div></div></div>';

	echo '</div></div>';
}
