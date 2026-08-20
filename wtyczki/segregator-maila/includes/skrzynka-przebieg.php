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

	$reguly       = segm_pobierz_reguly();
	$ruchy        = array();
	$dopasowanych = 0;
	$przenies     = 0;
	$bledow       = 0;
	$start        = time();
	$przerwano    = false;
	$utworzone    = array();

	foreach ( $uidy as $uid ) {
		if ( time() - $start > SEGM_SKRZ_LIMIT_CZASU ) {
			$przerwano = true;
			break;
		}

		$wiadomosc = segm_skrzynka_wiadomosc( $klient, $uid, ! empty( $ust['dopasuj_tresc'] ) );
		if ( null === $wiadomosc ) {
			++$bledow;
			$ruchy[] = segm_skrzynka_ruch( $uid, '', '', '', (string) $ust['folder_zrodlowy'], '', 'blad', $klient->blad() );
			continue;
		}

		$decyzja   = segm_klasyfikuj_mail( $wiadomosc, $reguly );
		$kategoria = $decyzja['kategoria'];
		$dopasowal = null !== $decyzja['regula_id'];
		if ( $dopasowal ) {
			++$dopasowanych;
		}

		if ( ! $dopasowal && empty( $ust['ruszaj_reszte'] ) ) {
			$ruchy[] = segm_skrzynka_ruch(
				$uid,
				$wiadomosc->message_id,
				$wiadomosc->nadawca,
				$wiadomosc->temat,
				(string) $ust['folder_zrodlowy'],
				'',
				'pominiety',
				'Żadna reguła nie pasuje — wiadomość zostaje na miejscu (kubeł „' . SEGM_KUBEL_DOMYSLNY . '" wyłączony w ustawieniach).'
			);
			continue;
		}

		$cel = segm_skrzynka_folder_dla( $klient, (string) $ust['prefiks'], $kategoria );
		if ( $cel === $zrodlowy ) {
			$ruchy[] = segm_skrzynka_ruch(
				$uid,
				$wiadomosc->message_id,
				$wiadomosc->nadawca,
				$wiadomosc->temat,
				(string) $ust['folder_zrodlowy'],
				Segm_Imap::z_utf7( $cel ),
				'pominiety',
				'Folder docelowy jest tym samym folderem co źródłowy — nie ma czego przenosić.'
			);
			continue;
		}

		if ( ! $na_zywo ) {
			$ruchy[] = segm_skrzynka_ruch(
				$uid,
				$wiadomosc->message_id,
				$wiadomosc->nadawca,
				$wiadomosc->temat,
				(string) $ust['folder_zrodlowy'],
				Segm_Imap::z_utf7( $cel ),
				'plan',
				''
			);
			continue;
		}

		if ( ! isset( $utworzone[ $cel ] ) ) {
			if ( ! $klient->utworz_folder( $cel ) ) {
				++$bledow;
				$ruchy[] = segm_skrzynka_ruch( $uid, $wiadomosc->message_id, $wiadomosc->nadawca, $wiadomosc->temat, (string) $ust['folder_zrodlowy'], Segm_Imap::z_utf7( $cel ), 'blad', $klient->blad() );
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
				$ruchy[] = segm_skrzynka_ruch( $uid, $wiadomosc->message_id, $wiadomosc->nadawca, $wiadomosc->temat, (string) $ust['folder_zrodlowy'], Segm_Imap::z_utf7( $cel ), 'blad', 'Folder docelowy powstał, ale nie udało się wrócić do folderu źródłowego — ' . $klient->blad() );
				continue;
			}
		}

		$wynik = $klient->przenies( $uid, $cel );
		if ( null === $wynik ) {
			++$bledow;
			$ruchy[] = segm_skrzynka_ruch( $uid, $wiadomosc->message_id, $wiadomosc->nadawca, $wiadomosc->temat, (string) $ust['folder_zrodlowy'], Segm_Imap::z_utf7( $cel ), 'blad', $klient->blad() );
			continue;
		}
		++$przenies;
		$ruch                 = segm_skrzynka_ruch( $uid, $wiadomosc->message_id, $wiadomosc->nadawca, $wiadomosc->temat, (string) $ust['folder_zrodlowy'], Segm_Imap::z_utf7( $cel ), 'przeniesiony', $wynik['uwaga'] );
		$ruch['uid_docelowy'] = (int) $wynik['uid_docelowy'];
		$ruchy[]              = $ruch;
	}

	$klient->rozlacz();

	if ( $przerwano ) {
		$uwagi[] = sprintf(
			'Przebieg zatrzymał się po %d s na %d wiadomościach z %d — reszta pójdzie następnym razem. To celowy limit, żeby hosting nie urwał żądania w połowie przenoszenia.',
			SEGM_SKRZ_LIMIT_CZASU,
			count( $ruchy ),
			count( $uidy )
		);
	}

	$tryb  = $na_zywo ? 'na_zywo' : 'na_sucho';
	$zapis = $wpdb->insert(
		$wpdb->prefix . 'segm_skrz_przebiegi',
		array(
			'tryb'            => $tryb,
			'zrodlo'          => 'cron' === $zrodlo ? 'cron' : 'reczny',
			'folder_zrodlowy' => (string) $ust['folder_zrodlowy'],
			'sprawdzonych'    => count( $ruchy ),
			'dopasowanych'    => $dopasowanych,
			'przeniesionych'  => $przenies,
			'bledow'          => $bledow,
			'cofniety'        => 0,
			'komunikat'       => implode( ' ', $uwagi ),
			'uruchomiono'     => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
	);
	if ( false === $zapis ) {
		segm_log_bledu_bazy( 'segm_skrzynka_przebieg', 'zapis przebiegu skrzynki (tryb: ' . $tryb . ')' );
		segm_zwolnij_blokade();
		$pusty['tryb'] = $tryb;
		$pusty['blad'] = 'na_zywo' === $tryb
			? 'Wiadomości ZOSTAŁY przeniesione na serwerze, ale zapis historii do bazy nie powiódł się — nie ma z czego cofnąć tego przebiegu. Sprawdź stan bazy i folder docelowy w programie pocztowym.'
			: 'Przebieg policzył się, ale zapis wyniku do bazy nie powiódł się — raportu NIE ma. Sprawdź stan bazy i uruchom przebieg ponownie.';
		return $pusty;
	}
	$id_przebiegu = (int) $wpdb->insert_id;

	foreach ( $ruchy as $ruch ) {
		$ruch['przebieg_id'] = $id_przebiegu;
		$ruch['utworzono']   = current_time( 'mysql' );
		if ( false === $wpdb->insert(
			$wpdb->prefix . 'segm_skrz_ruchy',
			$ruch,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		) ) {
			segm_log_bledu_bazy( 'segm_skrzynka_przebieg', 'zapis pozycji historii (UID ' . $ruch['uid'] . ')' );
		}
	}

	segm_zwolnij_blokade();

	return array(
		'id'             => $id_przebiegu,
		'tryb'           => $tryb,
		'sprawdzonych'   => count( $ruchy ),
		'dopasowanych'   => $dopasowanych,
		'przeniesionych' => $przenies,
		'bledow'         => $bledow,
		'komunikat'      => implode( ' ', $uwagi ),
		'blad'           => '',
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
 * @param string $stan       'plan' | 'przeniesiony' | 'pominiety' | 'blad' | 'cofniety'.
 * @param string $komunikat  Wyjaśnienie dla właściciela.
 * @return array<string, mixed>
 */
function segm_skrzynka_ruch( $uid, $message_id, $nadawca, $temat, $skad, $dokad, $stan, $komunikat ) {
	return array(
		'przebieg_id'  => 0,
		'uid'          => (int) $uid,
		'message_id'   => mb_substr( (string) $message_id, 0, 190 ),
		'nadawca'      => mb_substr( (string) $nadawca, 0, 190 ),
		'temat'        => (string) $temat,
		'folder_z'     => (string) $skad,
		'folder_do'    => (string) $dokad,
		'stan'         => (string) $stan,
		'komunikat'    => (string) $komunikat,
		'uid_docelowy' => 0,
		'utworzono'    => '',
	);
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
		$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}segm_skrz_ruchy WHERE przebieg_id = %d AND stan = 'przeniesiony' ORDER BY folder_do ASC, id ASC", (int) $id_przebiegu ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	$klient  = $polaczenie['klient'];
	$otwarty = '';

	foreach ( $ruchy as $ruch ) {
		$folder_do = Segm_Imap::do_utf7( (string) $ruch->folder_do );
		$folder_z  = Segm_Imap::do_utf7( (string) $ruch->folder_z );
		if ( $otwarty !== $folder_do ) {
			if ( null === $klient->otworz( $folder_do, false ) ) {
				++$wynik['bledow'];
				segm_skrzynka_oznacz_ruch( (int) $ruch->id, 'przeniesiony', 'Cofanie: nie udało się otworzyć folderu „' . $ruch->folder_do . '" — ' . $klient->blad() );
				continue;
			}
			$otwarty = $folder_do;
		}

		$uid = (int) $ruch->uid_docelowy;
		if ( $uid <= 0 && '' !== (string) $ruch->message_id ) {
			$znalezione = $klient->znajdz_po_message_id( (string) $ruch->message_id );
			if ( is_array( $znalezione ) && ! empty( $znalezione ) ) {
				$uid = (int) $znalezione[0];
			}
		}
		if ( $uid <= 0 ) {
			++$wynik['bledow'];
			segm_skrzynka_oznacz_ruch( (int) $ruch->id, 'przeniesiony', 'Cofanie: nie odnaleziono tej wiadomości w folderze „' . $ruch->folder_do . '" (serwer nie zwrócił nowego identyfikatora, a nagłówka Message-ID też nie ma). Przenieś ją z powrotem ręcznie w programie pocztowym.' );
			continue;
		}

		if ( null === $klient->przenies( $uid, $folder_z ) ) {
			++$wynik['bledow'];
			segm_skrzynka_oznacz_ruch( (int) $ruch->id, 'przeniesiony', 'Cofanie: serwer odmówił przeniesienia z powrotem — ' . $klient->blad() );
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
	return $wpdb->get_row(
		"SELECT * FROM {$wpdb->prefix}segm_skrz_przebiegi
		 WHERE tryb = 'na_zywo' AND cofniety = 0 AND przeniesionych > 0
		 ORDER BY id DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}
