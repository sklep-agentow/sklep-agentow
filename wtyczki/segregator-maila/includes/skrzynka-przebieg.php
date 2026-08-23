<?php
/**
 * Przebieg segregacji na PRAWDZIWEJ skrzynce: pobranie wiadomości, decyzja wg
 * istniejących reguł, przeniesienie do folderów na serwerze poczty, historia
 * i cofnięcie ostatniego przebiegu. Plus zadanie cykliczne WP-Cron.
 *
 * ⭐ Tryb NA SUCHO jest domyślny i to on decyduje o sposobie otwarcia folderu:
 * EXAMINE (RFC 3501 6.3.2) otwiera skrzynkę w trybie READ-ONLY na poziomie
 * protokołu — serwer odrzuci każdą próbę zmiany. Realne przenoszenie wymaga
 * PRZEŁĄCZENIA trybu ORAZ osobnego, jednorazowego potwierdzenia.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dostępne częstotliwości zadania cyklicznego: klucz harmonogramu => opis.
 *
 * @return array<string, array{sekundy: int, opis: string}>
 */
function segm_skrzynka_czestotliwosci() {
	return array(
		'wylaczone'        => array(
			'sekundy' => 0,
			'opis'    => 'wyłączone (tylko przycisk „Uruchom teraz")',
		),
		'segm_co_15_minut' => array(
			'sekundy' => 15 * MINUTE_IN_SECONDS,
			'opis'    => 'co 15 minut',
		),
		'segm_co_30_minut' => array(
			'sekundy' => 30 * MINUTE_IN_SECONDS,
			'opis'    => 'co 30 minut',
		),
		'segm_co_godzine'  => array(
			'sekundy' => HOUR_IN_SECONDS,
			'opis'    => 'co godzinę',
		),
		'segm_co_6_godzin' => array(
			'sekundy' => 6 * HOUR_IN_SECONDS,
			'opis'    => 'co 6 godzin',
		),
		'segm_raz_na_dobe' => array(
			'sekundy' => DAY_IN_SECONDS,
			'opis'    => 'raz na dobę',
		),
	);
}

/**
 * Rejestracja własnych harmonogramów w WordPressie.
 *
 * wp_get_schedules() scala nasze wpisy z wbudowanymi tak, że WBUDOWANE
 * WYGRYWAJĄ przy tym samym kluczu (wp-includes/cron.php:
 * array_merge( apply_filters( 'cron_schedules', array() ), $schedules )) —
 * dlatego wszystkie nasze klucze mają przedrostek segm_.
 *
 * @param array<string, array{interval: int, display: string}> $harmonogramy Harmonogramy innych wtyczek.
 * @return array<string, array{interval: int, display: string}>
 */
function segm_skrzynka_harmonogramy( $harmonogramy ) {
	foreach ( segm_skrzynka_czestotliwosci() as $klucz => $wpis ) {
		if ( $wpis['sekundy'] > 0 ) {
			$harmonogramy[ $klucz ] = array(
				'interval' => $wpis['sekundy'],
				'display'  => 'Segregator maila: ' . $wpis['opis'],
			);
		}
	}
	return $harmonogramy;
}
// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- interwaly siedza w segm_skrzynka_czestotliwosci(), sniff nie umie ich policzyc przez wywolanie funkcji; najkrotszy z nich to 15 * MINUTE_IN_SECONDS, czyli dokladnie minimum wymagane przez ten sniff.
add_filter( 'cron_schedules', 'segm_skrzynka_harmonogramy' );

/**
 * Ustawienie harmonogramu zgodnie z wybraną częstotliwością.
 *
 * @return void
 */
function segm_skrzynka_przeplanuj() {
	$ust           = segm_skrzynka_ustawienia();
	$czestotliwosc = (string) $ust['czestotliwosc'];
	$dostepne      = segm_skrzynka_czestotliwosci();

	wp_clear_scheduled_hook( 'segm_skrzynka_cron' );
	if ( ! isset( $dostepne[ $czestotliwosc ] ) || 0 === $dostepne[ $czestotliwosc ]['sekundy'] ) {
		return;
	}
	wp_schedule_event( time() + $dostepne[ $czestotliwosc ]['sekundy'], $czestotliwosc, 'segm_skrzynka_cron' );
}

/**
 * Wejście zadania cyklicznego.
 *
 * @return void
 */
function segm_skrzynka_cron_wykonaj() {
	segm_skrzynka_przebieg( 'cron' );
}
add_action( 'segm_skrzynka_cron', 'segm_skrzynka_cron_wykonaj' );

/**
 * Opis stanu harmonogramu dla panelu.
 *
 * @return string
 */
function segm_skrzynka_opis_harmonogramu() {
	$ust      = segm_skrzynka_ustawienia();
	$dostepne = segm_skrzynka_czestotliwosci();
	$wybrana  = isset( $dostepne[ $ust['czestotliwosc'] ] ) ? $dostepne[ $ust['czestotliwosc'] ] : $dostepne['wylaczone'];
	if ( 0 === $wybrana['sekundy'] ) {
		return 'Segregacja cykliczna jest wyłączona — przebieg ruszy tylko po kliknięciu „Uruchom teraz".';
	}
	$kiedy = wp_next_scheduled( 'segm_skrzynka_cron' );
	if ( ! $kiedy ) {
		return 'Ustawiono „' . $wybrana['opis'] . '", ale zadanie NIE jest zaplanowane — sprawdź, czy WP-Cron działa na tym hostingu.';
	}
	return 'Ustawiono „' . $wybrana['opis'] . '". Najbliższy przebieg: ' . wp_date( 'j.m.Y H:i', $kiedy ) . '.';
}

/* ===================== PRZEBIEG ===================== */

/**
 * Jeden przebieg segregacji na skrzynce.
 *
 * ⭐ KOLEJNOŚĆ: ZAPIS ZAMIARU PRZED RUCHEM (LS-F2). Historia jest jedynym
 * źródłem, z którego da się cofnąć przebieg — więc powstaje ZANIM cokolwiek
 * ruszy na serwerze: najpierw wiersz przebiegu, potem dla każdej wiadomości
 * wiersz „w toku", dopiero po udanym zapisie `UID MOVE`, a po potwierdzeniu
 * od serwera wiersz dostaje stan „przeniesiony" i nowy UID. Gdy baza odmówi:
 *  - wiadomość, której zamiaru nie dało się zapisać, NIE jest ruszana,
 *  - przebieg PRZERYWA się na pierwszej odmowie (baza, która padła, nie
 *    przyjmie też następnych wierszy) i zwraca `blad` — panel pokazuje błąd,
 *    a nie „sukces" z pustą historią,
 *  - `przeniesionych` liczy tylko ruchy potwierdzone przez serwer.
 * Wiersz, który został „w toku" (proces padł między MOVE a potwierdzeniem),
 * jest dla cofania sygnałem „szukaj po Message-ID", a nie „pomiń".
 * Wcześniej wiersze historii powstawały PO pętli przenoszenia — przy padzie
 * bazy poczta była rozłożona po folderach, historia pusta, cofanie martwe,
 * a panel pokazywał „przeniesionych=1, błędów=0".
 *
 * @param string $zrodlo 'reczny' albo 'cron'.
 * @return array{id: int, tryb: string, sprawdzonych: int, dopasowanych: int, przeniesionych: int, bledow: int, komunikat: string, blad: string}
 */
function segm_skrzynka_przebieg( $zrodlo = 'reczny' ) {
	global $wpdb;

	$pusty = array(
		'id'             => 0,
		'tryb'           => 'na_sucho',
		'sprawdzonych'   => 0,
		'dopasowanych'   => 0,
		'przeniesionych' => 0,
		'bledow'         => 0,
		'komunikat'      => '',
		'blad'           => '',
	);

	$ust     = segm_skrzynka_ustawienia();
	$na_zywo = segm_przenoszenie_dozwolone();
	$uwagi   = array();
	if ( 'na_zywo' === $ust['tryb'] && ! $na_zywo ) {
		$uwagi[] = 'Tryb ustawiono na realne przenoszenie, ale BRAK jednorazowego potwierdzenia — przebieg poszedł NA SUCHO i nic nie ruszył.';
	}
	$tryb          = $na_zywo ? 'na_zywo' : 'na_sucho';
	$pusty['tryb'] = $tryb;

	// Ten sam znacznik co przy próbce CSV: dwa przebiegi naraz (cron + klik
	// w panelu) liczyłyby i przenosiły te same wiadomości dwa razy.
	$zajeta = segm_zajmij_blokade( 'segregacja skrzynki' );
	if ( true !== $zajeta ) {
		$pusty['blad'] = sprintf( 'Trwa inna operacja (%s) — przebieg NIE wystartował. Odczekaj chwilę i spróbuj ponownie.', $zajeta );
		return $pusty;
	}

	$polaczenie = segm_skrzynka_polacz();
	if ( null === $polaczenie['klient'] ) {
		segm_zwolnij_blokade();
		$pusty['blad'] = $polaczenie['blad'];
		return $pusty;
	}
	$klient = $polaczenie['klient'];

	$zrodlowy = Segm_Imap::do_utf7( (string) $ust['folder_zrodlowy'] );
	$stan     = $klient->otworz( $zrodlowy, ! $na_zywo );
	if ( null === $stan ) {
		$pusty['blad'] = $klient->blad();
		$klient->rozlacz();
		segm_zwolnij_blokade();
		return $pusty;
	}

	$uidy = $klient->uidy( empty( $ust['tylko_nieprzeczytane'] ) ? 'ALL' : 'UNSEEN' );
	if ( null === $uidy ) {
		$pusty['blad'] = $klient->blad();
		$klient->rozlacz();
		segm_zwolnij_blokade();
		return $pusty;
	}

	// Bierzemy NAJNOWSZE wiadomości: przy skrzynce z 40 tysiącami maili
	// właściciela interesuje bieżąca poczta, a nie archiwum sprzed lat.
	$limit = max( 1, (int) $ust['limit'] );
	if ( count( $uidy ) > $limit ) {
		$uidy = array_slice( $uidy, -$limit );
	}

	// Wiersz przebiegu powstaje PRZED pierwszym ruchem — bez niego nie ma do
	// czego przypiąć historii, więc bez niego nic nie ruszamy.
	$zapis = $wpdb->insert(
		$wpdb->prefix . 'segm_skrz_przebiegi',
		array(
			'tryb'            => $tryb,
			'zrodlo'          => 'cron' === $zrodlo ? 'cron' : 'reczny',
			'folder_zrodlowy' => (string) $ust['folder_zrodlowy'],
			'sprawdzonych'    => 0,
			'dopasowanych'    => 0,
			'przeniesionych'  => 0,
			'bledow'          => 0,
			'cofniety'        => 0,
			'komunikat'       => 'Przebieg w toku.',
			'uruchomiono'     => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
	);
	if ( false === $zapis ) {
		segm_log_bledu_bazy( 'segm_skrzynka_przebieg', 'zapis przebiegu skrzynki (tryb: ' . $tryb . ')' );
		$klient->rozlacz();
		segm_zwolnij_blokade();
		$pusty['blad'] = 'Nie udało się zapisać przebiegu w bazie — przebieg NIE wystartował i żadna wiadomość nie została ruszona. Sprawdź stan bazy i uruchom ponownie.';
		return $pusty;
	}
	$id_przebiegu = (int) $wpdb->insert_id;

	$reguly       = segm_pobierz_reguly();
	$sprawdzonych = 0;
	$dopasowanych = 0;
	$przenies     = 0;
	$bledow       = 0;
	$start        = time();
	$przerwano    = false;
	$baza_padla   = '';
	$utworzone    = array();

	foreach ( $uidy as $uid ) {
		if ( time() - $start > SEGM_SKRZ_LIMIT_CZASU ) {
			$przerwano = true;
			break;
		}
		++$sprawdzonych;

		$wiadomosc = segm_skrzynka_wiadomosc( $klient, $uid, ! empty( $ust['dopasuj_tresc'] ) );
		if ( null === $wiadomosc ) {
			++$bledow;
			if ( ! segm_skrzynka_zapisz_ruch( $id_przebiegu, segm_skrzynka_ruch( $uid, '', '', '', (string) $ust['folder_zrodlowy'], '', 'blad', $klient->blad() ) ) ) {
				$baza_padla = 'zapis pozycji historii (UID ' . (int) $uid . ')';
				break;
			}
			continue;
		}

		$decyzja   = segm_klasyfikuj_mail( $wiadomosc, $reguly );
		$kategoria = $decyzja['kategoria'];
		$dopasowal = null !== $decyzja['regula_id'];
		if ( $dopasowal ) {
			++$dopasowanych;
		}

		if ( ! $dopasowal && empty( $ust['ruszaj_reszte'] ) ) {
			$ruch = segm_skrzynka_ruch(
				$uid,
				$wiadomosc->message_id,
				$wiadomosc->nadawca,
				$wiadomosc->temat,
				(string) $ust['folder_zrodlowy'],
				'',
				'pominiety',
				'Żadna reguła nie pasuje — wiadomość zostaje na miejscu (kubeł „' . SEGM_KUBEL_DOMYSLNY . '" wyłączony w ustawieniach).'
			);
			if ( ! segm_skrzynka_zapisz_ruch( $id_przebiegu, $ruch ) ) {
				$baza_padla = 'zapis pozycji historii (UID ' . (int) $uid . ')';
				break;
			}
			continue;
		}

		$cel = segm_skrzynka_folder_dla( $klient, (string) $ust['prefiks'], $kategoria );
		if ( $cel === $zrodlowy ) {
			$ruch = segm_skrzynka_ruch(
				$uid,
				$wiadomosc->message_id,
				$wiadomosc->nadawca,
				$wiadomosc->temat,
				(string) $ust['folder_zrodlowy'],
				Segm_Imap::z_utf7( $cel ),
				'pominiety',
				'Folder docelowy jest tym samym folderem co źródłowy — nie ma czego przenosić.'
			);
			if ( ! segm_skrzynka_zapisz_ruch( $id_przebiegu, $ruch ) ) {
				$baza_padla = 'zapis pozycji historii (UID ' . (int) $uid . ')';
				break;
			}
			continue;
		}

		if ( ! $na_zywo ) {
			$ruch = segm_skrzynka_ruch(
				$uid,
				$wiadomosc->message_id,
				$wiadomosc->nadawca,
				$wiadomosc->temat,
				(string) $ust['folder_zrodlowy'],
				Segm_Imap::z_utf7( $cel ),
				'plan',
				''
			);
			if ( ! segm_skrzynka_zapisz_ruch( $id_przebiegu, $ruch ) ) {
				$baza_padla = 'zapis pozycji historii (UID ' . (int) $uid . ')';
				break;
			}
			continue;
		}

		if ( ! isset( $utworzone[ $cel ] ) ) {
			if ( ! $klient->utworz_folder( $cel ) ) {
				++$bledow;
				if ( ! segm_skrzynka_zapisz_ruch( $id_przebiegu, segm_skrzynka_ruch( $uid, $wiadomosc->message_id, $wiadomosc->nadawca, $wiadomosc->temat, (string) $ust['folder_zrodlowy'], Segm_Imap::z_utf7( $cel ), 'blad', $klient->blad() ) ) ) {
					$baza_padla = 'zapis pozycji historii (UID ' . (int) $uid . ')';
					break;
				}
				continue;
			}
			$utworzone[ $cel ] = true;
			// CREATE mogło zmienić bieżący folder na niektórych serwerach —
			// otwieramy źródłowy z powrotem, żeby UID-y dotyczyły tego, co trzeba.
			// Wynik SPRAWDZAMY: bez wybranego folderu źródłowego kolejne UID MOVE
			// nie ma czego przenosić i lepiej powiedzieć to przy tej wiadomości,
			// niż zbierać serię niezrozumiałych odmów serwera.
			if ( null === $klient->otworz( $zrodlowy, false ) ) {
				++$bledow;
				if ( ! segm_skrzynka_zapisz_ruch( $id_przebiegu, segm_skrzynka_ruch( $uid, $wiadomosc->message_id, $wiadomosc->nadawca, $wiadomosc->temat, (string) $ust['folder_zrodlowy'], Segm_Imap::z_utf7( $cel ), 'blad', 'Folder docelowy powstał, ale nie udało się wrócić do folderu źródłowego — ' . $klient->blad() ) ) ) {
					$baza_padla = 'zapis pozycji historii (UID ' . (int) $uid . ')';
					break;
				}
				continue;
			}
		}

		// ZAMIAR do bazy — dopiero potem ruch na serwerze.
		$ruch     = segm_skrzynka_ruch(
			$uid,
			$wiadomosc->message_id,
			$wiadomosc->nadawca,
			$wiadomosc->temat,
			(string) $ust['folder_zrodlowy'],
			Segm_Imap::z_utf7( $cel ),
			'w_toku',
			'Przenoszenie w toku. Jeśli ten wpis się nie zmienił, przebieg urwał się między przeniesieniem a potwierdzeniem — cofanie odszuka wiadomość po Message-ID.'
		);
		$id_ruchu = segm_skrzynka_zapisz_ruch( $id_przebiegu, $ruch );
		if ( ! $id_ruchu ) {
			$baza_padla = 'zapis zamiaru przeniesienia (UID ' . (int) $uid . ') — wiadomość NIE została ruszona';
			break;
		}

		$wynik = $klient->przenies( $uid, $cel );
		if ( null === $wynik ) {
			++$bledow;
			segm_skrzynka_oznacz_ruch( $id_ruchu, 'blad', $klient->blad() );
			continue;
		}
		++$przenies;
		if ( ! segm_skrzynka_potwierdz_ruch( $id_ruchu, (int) $wynik['uid_docelowy'], (int) $wynik['uidvalidity_do'], (string) $wynik['uwaga'] ) ) {
			// Wiadomość JEST przeniesiona (liczymy ją), ale potwierdzenie nie
			// weszło do bazy — wiersz zostaje „w toku" i mówi o tym wprost.
			// Dalej nie idziemy: baza, która odmówiła, nie przyjmie kolejnych zamiarów.
			$baza_padla = 'potwierdzenie przeniesienia (UID ' . (int) $uid . ') — wiadomość JEST przeniesiona, w historii widnieje jako „w toku"';
			break;
		}
	}

	$klient->rozlacz();

	if ( $przerwano ) {
		$uwagi[] = sprintf(
			'Przebieg zatrzymał się po %d s na %d wiadomościach z %d — reszta pójdzie następnym razem. To celowy limit, żeby hosting nie urwał żądania w połowie przenoszenia.',
			SEGM_SKRZ_LIMIT_CZASU,
			$sprawdzonych,
			count( $uidy )
		);
	}
	$blad = '';
	if ( '' !== $baza_padla ) {
		$blad    = sprintf(
			'Zapis historii do bazy nie powiódł się (%s). Przebieg #%d PRZERWANY po %d wiadomościach z %d: potwierdzonych przeniesień %d, reszta została na miejscu. Sprawdź stan bazy, zanim uruchomisz go ponownie.',
			$baza_padla,
			$id_przebiegu,
			$sprawdzonych,
			count( $uidy ),
			$przenies
		);
		$uwagi[] = $blad;
	}

	if ( false === $wpdb->update(
		$wpdb->prefix . 'segm_skrz_przebiegi',
		array(
			'sprawdzonych'   => $sprawdzonych,
			'dopasowanych'   => $dopasowanych,
			'przeniesionych' => $przenies,
			'bledow'         => $bledow,
			'komunikat'      => implode( ' ', $uwagi ),
		),
		array( 'id' => $id_przebiegu ),
		array( '%d', '%d', '%d', '%d', '%s' ),
		array( '%d' )
	) ) {
		segm_log_bledu_bazy( 'segm_skrzynka_przebieg', 'podsumowanie przebiegu #' . $id_przebiegu );
		if ( '' === $blad ) {
			$blad = sprintf(
				'Przebieg #%d policzył się (przeniesionych %d), ale podsumowania nie udało się zapisać w bazie — historia pozycji jest kompletna, liczby w tabeli przebiegów mogą pokazywać zera. Sprawdź stan bazy.',
				$id_przebiegu,
				$przenies
			);
		}
	}

	segm_zwolnij_blokade();

	return array(
		'id'             => $id_przebiegu,
		'tryb'           => $tryb,
		'sprawdzonych'   => $sprawdzonych,
		'dopasowanych'   => $dopasowanych,
		'przeniesionych' => $przenies,
		'bledow'         => $bledow,
		'komunikat'      => implode( ' ', $uwagi ),
		'blad'           => $blad,
	);
}

/**
 * Jeden wiersz historii (kolejność kluczy = kolejność formatów w insert()).
 *
 * @param int    $uid        UID w folderze źródłowym.
 * @param string $message_id Nagłówek Message-ID.
 * @param string $nadawca    Adres nadawcy.
 * @param string $temat      Temat.
 * @param string $skad       Folder źródłowy (czytelnie).
 * @param string $dokad      Folder docelowy (czytelnie).
 * @param string $stan       'plan' | 'w_toku' | 'przeniesiony' | 'pominiety' | 'blad' | 'cofniety'.
 * @param string $komunikat  Wyjaśnienie dla właściciela.
 * @return array<string, mixed>
 */
function segm_skrzynka_ruch( $uid, $message_id, $nadawca, $temat, $skad, $dokad, $stan, $komunikat ) {
	return array(
		'przebieg_id'    => 0,
		'uid'            => (int) $uid,
		'message_id'     => mb_substr( (string) $message_id, 0, 190 ),
		'nadawca'        => mb_substr( (string) $nadawca, 0, 190 ),
		'temat'          => (string) $temat,
		'folder_z'       => (string) $skad,
		'folder_do'      => (string) $dokad,
		'stan'           => (string) $stan,
		'komunikat'      => (string) $komunikat,
		'uid_docelowy'   => 0,
		'uidvalidity_do' => 0,
		'utworzono'      => '',
	);
}

/**
 * Zapis jednej pozycji historii. „Skąd wiem, że się udało": insert() zwraca
 * false przy porażce, a insert_id > 0 przy sukcesie — zwracamy ten drugi.
 *
 * @param int                  $id_przebiegu Identyfikator przebiegu.
 * @param array<string, mixed> $ruch         Wiersz z segm_skrzynka_ruch().
 * @return int Identyfikator wiersza albo 0 przy porażce (błąd już zalogowany).
 */
function segm_skrzynka_zapisz_ruch( $id_przebiegu, $ruch ) {
	global $wpdb;
	$ruch['przebieg_id'] = (int) $id_przebiegu;
	$ruch['utworzono']   = current_time( 'mysql' );
	$wynik               = $wpdb->insert(
		$wpdb->prefix . 'segm_skrz_ruchy',
		$ruch,
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
	);
	if ( false === $wynik || (int) $wpdb->insert_id <= 0 ) {
		segm_log_bledu_bazy( 'segm_skrzynka_przebieg', 'zapis pozycji historii (UID ' . (int) $ruch['uid'] . ')' );
		return 0;
	}
	return (int) $wpdb->insert_id;
}

/**
 * Potwierdzenie ruchu w historii: „w toku" → „przeniesiony" + nowy UID
 * i UIDVALIDITY folderu docelowego (cofanie sprawdza ją, zanim zaufa UID-owi).
 *
 * @param int    $id_ruchu       Identyfikator wiersza historii.
 * @param int    $uid_docelowy   UID w folderze docelowym (0 = serwer nie podał).
 * @param int    $uidvalidity_do UIDVALIDITY folderu docelowego (0 = nieznane).
 * @param string $uwaga          Uwaga z klienta IMAP (np. serwer bez MOVE).
 * @return bool True, gdy wiersz po zapisie ma stan „przeniesiony".
 */
function segm_skrzynka_potwierdz_ruch( $id_ruchu, $uid_docelowy, $uidvalidity_do, $uwaga ) {
	global $wpdb;
	$wynik = $wpdb->update(
		$wpdb->prefix . 'segm_skrz_ruchy',
		array(
			'stan'           => 'przeniesiony',
			'komunikat'      => (string) $uwaga,
			'uid_docelowy'   => (int) $uid_docelowy,
			'uidvalidity_do' => (int) $uidvalidity_do,
		),
		array( 'id' => (int) $id_ruchu ),
		array( '%s', '%s', '%d', '%d' ),
		array( '%d' )
	);
	if ( false === $wynik ) {
		segm_log_bledu_bazy( 'segm_skrzynka_przebieg', 'potwierdzenie przeniesienia (pozycja ' . (int) $id_ruchu . ')' );
		return false;
	}
	// update() zwraca 0 także wtedy, gdy nic nie pasowało — prawdę mówi odczyt PO zapisie.
	$stan = $wpdb->get_var( $wpdb->prepare( "SELECT stan FROM {$wpdb->prefix}segm_skrz_ruchy WHERE id = %d", (int) $id_ruchu ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return 'przeniesiony' === $stan;
}

/* ===================== COFANIE ===================== */

/**
 * Cofnięcie przebiegu: każda przeniesiona wiadomość wraca do folderu, z którego
 * wyszła.
 *
 * Nowy UID bierzemy z kodu odpowiedzi COPYUID (RFC 4315). Serwer NIE MUSI go
 * zwrócić — wtedy szukamy wiadomości w folderze docelowym po nagłówku
 * Message-ID. Gdy i to zawiedzie, mówimy wprost, której wiadomości nie
 * odnaleziono, zamiast zgadywać.
 *
 * ⭐ Liczymy TYLKO to, co naprawdę wróciło (LS-F1): `Segm_Imap::przenies()`
 * sprawdza skutek ruchu, więc wiadomość, którą właściciel w międzyczasie
 * przeciągnął gdzie indziej, dostaje BŁĄD przy swojej pozycji, a nie
 * „wróciła". Przebieg jest „cofnięty w całości" tylko przy zerze błędów —
 * inaczej przycisk zostaje i drugą próbę da się zrobić.
 *
 * ⭐ UIDVALIDITY (T-3, RFC 3501 §2.3.1.1): zapamiętany UID znaczy coś tylko
 * w folderze o TEJ SAMEJ UIDVALIDITY. Folder skasowany i założony na nowo
 * zaczyna UID-y od 1 — stary UID wskazywałby OBCĄ wiadomość i to ją byśmy
 * „cofnęli". Gdy UIDVALIDITY się różni, UID-owi nie ufamy i szukamy po
 * Message-ID. Pozycje „w toku" (proces padł między MOVE a potwierdzeniem)
 * traktujemy tak samo: UID-u nie znamy, szukamy po Message-ID.
 *
 * @param int $id_przebiegu Identyfikator przebiegu do cofnięcia.
 * @return array{ok: bool, cofnietych: int, bledow: int, blad: string}
 */
function segm_skrzynka_cofnij( $id_przebiegu ) {
	global $wpdb;

	$wynik = array(
		'ok'         => false,
		'cofnietych' => 0,
		'bledow'     => 0,
		'blad'       => '',
	);

	$przebieg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi WHERE id = %d", (int) $id_przebiegu ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $przebieg ) {
		$wynik['blad'] = 'Nie ma takiego przebiegu.';
		return $wynik;
	}
	if ( 'na_zywo' !== $przebieg->tryb ) {
		$wynik['blad'] = 'Ten przebieg poszedł NA SUCHO — nic nie zostało przeniesione, więc nie ma czego cofać.';
		return $wynik;
	}
	if ( (int) $przebieg->cofniety > 0 ) {
		$wynik['blad'] = 'Ten przebieg został już cofnięty.';
		return $wynik;
	}

	// Cofać wolno WYŁĄCZNIE ostatni nie-cofnięty przebieg — tak mówi instrukcja
	// i tak mówi panel. Samo ukryte pole w formularzu tego nie pilnuje: między
	// wyświetleniem strony a kliknięciem cron mógł dorzucić nowszy przebieg,
	// a wtedy cofanie starszego przestawiałoby wiadomości, które w międzyczasie
	// pojechały gdzie indziej. Sprawdzamy stan bazy, nie to, co przyszło z żądania.
	$najnowszy = segm_skrzynka_ostatni_do_cofniecia();
	if ( $najnowszy && (int) $najnowszy->id !== (int) $przebieg->id ) {
		$wynik['blad'] = sprintf(
			'Cofnąć można tylko OSTATNI przebieg, a od czasu przebiegu #%d był jeszcze przebieg #%d (%s), którego nikt nie cofnął. Cofnij najpierw #%d.',
			(int) $przebieg->id,
			(int) $najnowszy->id,
			(string) $najnowszy->uruchomiono,
			(int) $najnowszy->id
		);
		return $wynik;
	}

	$ruchy = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d AND stan IN ('przeniesiony', 'w_toku') ORDER BY folder_do ASC, id ASC", (int) $id_przebiegu ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
	if ( ! $ruchy ) {
		$wynik['blad'] = 'W tym przebiegu nie ma ani jednej przeniesionej wiadomości.';
		return $wynik;
	}

	$zajeta = segm_zajmij_blokade( 'cofanie przebiegu skrzynki' );
	if ( true !== $zajeta ) {
		$wynik['blad'] = sprintf( 'Trwa inna operacja (%s) — cofanie NIE wystartowało. Odczekaj chwilę i spróbuj ponownie.', $zajeta );
		return $wynik;
	}

	$polaczenie = segm_skrzynka_polacz();
	if ( null === $polaczenie['klient'] ) {
		segm_zwolnij_blokade();
		$wynik['blad'] = $polaczenie['blad'];
		return $wynik;
	}
	$klient              = $polaczenie['klient'];
	$otwarty             = '';
	$otwarty_uidvalidity = 0;

	foreach ( $ruchy as $ruch ) {
		$folder_do = Segm_Imap::do_utf7( (string) $ruch->folder_do );
		$folder_z  = Segm_Imap::do_utf7( (string) $ruch->folder_z );
		if ( $otwarty !== $folder_do ) {
			$stan_folderu = $klient->otworz( $folder_do, false );
			if ( null === $stan_folderu ) {
				++$wynik['bledow'];
				segm_skrzynka_oznacz_ruch( (int) $ruch->id, (string) $ruch->stan, 'Cofanie: nie udało się otworzyć folderu „' . $ruch->folder_do . '" — ' . $klient->blad() );
				continue;
			}
			$otwarty             = $folder_do;
			$otwarty_uidvalidity = (int) $stan_folderu['uidvalidity'];
		}

		$uid              = 'przeniesiony' === $ruch->stan ? (int) $ruch->uid_docelowy : 0;
		$zapisana_uv      = (int) $ruch->uidvalidity_do;
		$folder_odnowiony = $uid > 0 && $zapisana_uv > 0 && $otwarty_uidvalidity > 0 && $zapisana_uv !== $otwarty_uidvalidity;
		if ( $folder_odnowiony ) {
			// Ten sam numer, inny folder: UID-owi nie wolno ufać.
			$uid = 0;
		}
		if ( $uid <= 0 && '' !== (string) $ruch->message_id ) {
			$znalezione = $klient->znajdz_po_message_id( (string) $ruch->message_id );
			if ( is_array( $znalezione ) && ! empty( $znalezione ) ) {
				$uid = (int) $znalezione[0];
			}
		}
		if ( $uid <= 0 ) {
			++$wynik['bledow'];
			if ( $folder_odnowiony ) {
				$powod = 'folder „' . $ruch->folder_do . '" został od czasu przebiegu skasowany i założony na nowo (zmieniła się jego UIDVALIDITY), a wiadomości o tym Message-ID już w nim nie ma';
			} elseif ( 'w_toku' === $ruch->stan ) {
				$powod = 'przebieg urwał się między przeniesieniem a potwierdzeniem i nie wiadomo, czy wiadomość w ogóle została przeniesiona; po Message-ID nie odnaleziono jej w folderze „' . $ruch->folder_do . '"';
			} else {
				$powod = 'nie odnaleziono tej wiadomości w folderze „' . $ruch->folder_do . '" (serwer nie zwrócił nowego identyfikatora, a nagłówka Message-ID też nie ma)';
			}
			segm_skrzynka_oznacz_ruch( (int) $ruch->id, (string) $ruch->stan, 'Cofanie: ' . $powod . '. Sprawdź ją ręcznie w programie pocztowym.' );
			continue;
		}

		if ( null === $klient->przenies( $uid, $folder_z ) ) {
			++$wynik['bledow'];
			segm_skrzynka_oznacz_ruch( (int) $ruch->id, (string) $ruch->stan, 'Cofanie: nie udało się przenieść z powrotem — ' . $klient->blad() . ' Sprawdź ją ręcznie w programie pocztowym.' );
			continue;
		}
		++$wynik['cofnietych'];
		segm_skrzynka_oznacz_ruch( (int) $ruch->id, 'cofniety', 'Wiadomość wróciła do folderu „' . $ruch->folder_z . '".' );
		// Po MOVE bieżący folder zostaje ten sam, ale UID-y się przesuwają —
		// kolejne wiadomości i tak adresujemy po UID, więc nic nie tracimy.
	}

	$klient->rozlacz();

	$cofniety_w_calosci = 0 === $wynik['bledow'] ? 1 : 0;
	if ( false === $wpdb->update(
		$wpdb->prefix . 'segm_skrz_przebiegi',
		array( 'cofniety' => $cofniety_w_calosci ),
		array( 'id' => (int) $id_przebiegu ),
		array( '%d' ),
		array( '%d' )
	) ) {
		segm_log_bledu_bazy( 'segm_skrzynka_cofnij', 'oznaczenie przebiegu jako cofniętego' );
	}

	segm_zwolnij_blokade();
	$wynik['ok'] = $wynik['cofnietych'] > 0;
	return $wynik;
}

/**
 * Aktualizacja stanu jednej pozycji historii.
 *
 * @param int    $id        Identyfikator wiersza.
 * @param string $stan      Nowy stan.
 * @param string $komunikat Wyjaśnienie.
 * @return void
 */
function segm_skrzynka_oznacz_ruch( $id, $stan, $komunikat ) {
	global $wpdb;
	if ( false === $wpdb->update(
		$wpdb->prefix . 'segm_skrz_ruchy',
		array(
			'stan'      => $stan,
			'komunikat' => $komunikat,
		),
		array( 'id' => (int) $id ),
		array( '%s', '%s' ),
		array( '%d' )
	) ) {
		segm_log_bledu_bazy( 'segm_skrzynka_oznacz_ruch', 'aktualizacja pozycji historii ' . (int) $id );
	}
}

/**
 * Ostatni przebieg, który da się cofnąć (albo null).
 *
 * @return object|null
 */
function segm_skrzynka_ostatni_do_cofniecia() {
	global $wpdb;
	// Prawdę o tym, czy jest co cofać, mówi historia pozycji (także „w toku"
	// po urwanym przebiegu), nie licznik w wierszu przebiegu — ten mógł nie
	// dojechać do bazy, gdy przebieg padł w połowie.
	return $wpdb->get_row(
		"SELECT p.* FROM {$wpdb->prefix}segm_skrz_przebiegi p
		 WHERE p.tryb = 'na_zywo' AND p.cofniety = 0
		   AND EXISTS (SELECT 1 FROM {$wpdb->prefix}segm_skrz_ruchy r WHERE r.przebieg_id = p.id AND r.stan IN ('przeniesiony', 'w_toku'))
		 ORDER BY p.id DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}
