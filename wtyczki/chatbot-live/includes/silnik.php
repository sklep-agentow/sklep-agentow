<?php
/**
 * Silnik regułowy: proste dopasowanie podłańcuchów po wariantach słów kluczowych.
 * Odmiany łapane przez warianty wpisane ręcznie („cena, ceny, cennik") —
 * automatyczny stemming to droga rozbudowy, nie brak w tej wersji.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Które warianty pary trafiły w pytanie gościa.
 *
 * JEDYNE miejsce, gdzie żyje reguła dopasowania (małe litery, podział po
 * przecinku, szukanie podłańcucha). Wołają ją i silnik (chatl_dopasuj), i test
 * bota w panelu — inaczej panel mógłby pokazywać co innego, niż bot naprawdę
 * zrobił, a wtedy panel kłamie na temat własnego produktu.
 *
 * @param string $pytanie        Pytanie gościa (surowe).
 * @param string $slowa_kluczowe Warianty pary, rozdzielone przecinkami.
 * @return array Lista trafionych wariantów (już po mb_strtolower i trim).
 */
function chatl_trafione_warianty( $pytanie, $slowa_kluczowe ) {
	$szukane  = mb_strtolower( trim( (string) $pytanie ) );
	$trafione = array();
	if ( '' === $szukane ) {
		return $trafione;
	}
	$warianty = array_filter( array_map( 'trim', explode( ',', mb_strtolower( (string) $slowa_kluczowe ) ) ) );
	foreach ( $warianty as $wariant ) {
		if ( false !== mb_strpos( $szukane, $wariant ) ) {
			$trafione[] = $wariant;
		}
	}
	return $trafione;
}

/**
 * Punktacja jednej pary: ile wariantów trafiło i jak długie łącznie.
 *
 * Wydzielone razem z dopasowaniem, żeby „ile trafień" i „jak liczymy remis"
 * też miały jedno źródło — panel pokazuje dokładnie te liczby, którymi
 * silnik rozstrzygnął.
 *
 * @param array $trafione Wynik chatl_trafione_warianty().
 * @return array ['trafienia' => int, 'dlugosc' => int]
 */
function chatl_punktacja( $trafione ) {
	$dlugosc = 0;
	foreach ( $trafione as $wariant ) {
		$dlugosc += mb_strlen( $wariant );
	}
	return array(
		'trafienia' => count( $trafione ),
		'dlugosc'   => $dlugosc,
	);
}

/**
 * Szuka najlepszej odpowiedzi na pytanie gościa.
 *
 * Wynik: liczba trafionych wariantów decyduje; przy remisie wygrywa dłuższe
 * łączne trafienie (bardziej konkretny wpis). Zero trafień => null.
 *
 * @param string $pytanie Surowe pytanie gościa.
 * @return array|null ['id' => int, 'odpowiedz' => string, 'trafione' => array]
 *                    albo null. 'trafione' = warianty, którymi ta para wygrała
 *                    (panel pokazuje je bez powtarzania algorytmu).
 */
function chatl_dopasuj( $pytanie ) {
	global $wpdb;

	$szukane = mb_strtolower( trim( (string) $pytanie ) );
	if ( '' === $szukane ) {
		return null;
	}

	$tabela = $wpdb->prefix . 'chatl_qa';
	$wpisy  = $wpdb->get_results( "SELECT id, slowa_kluczowe, odpowiedz FROM {$tabela} WHERE aktywne = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$najlepszy = null;
	$max_traf  = 0;
	$max_dl    = 0;

	$trafione_najlepszego = array();

	foreach ( $wpisy as $wpis ) {
		$trafione  = chatl_trafione_warianty( $szukane, $wpis->slowa_kluczowe );
		$punkty    = chatl_punktacja( $trafione );
		$trafienia = $punkty['trafienia'];
		$dlugosc   = $punkty['dlugosc'];
		if ( $trafienia > $max_traf || ( $trafienia === $max_traf && $trafienia > 0 && $dlugosc > $max_dl ) ) {
			$max_traf             = $trafienia;
			$max_dl               = $dlugosc;
			$najlepszy            = $wpis;
			$trafione_najlepszego = $trafione;
		}
	}

	if ( null === $najlepszy ) {
		return null;
	}

	return array(
		'id'        => (int) $najlepszy->id,
		'odpowiedz' => $najlepszy->odpowiedz,
		'trafione'  => $trafione_najlepszego,
	);
}

/**
 * Zapisuje wymianę do historii rozmów.
 *
 * @param string    $sesja    Identyfikator sesji przeglądarki (już zwalidowany).
 * @param string    $rola     'gosc' albo 'bot'.
 * @param string    $tresc    Treść (już zsanityzowana).
 * @param string    $silnik   'reguly' albo 'ai' (który silnik odpowiedział).
 * @param bool|null $trafiono Czy bot znał odpowiedź. NULL = jeszcze nie wiadomo
 *                            (tak zapisujemy pytanie gościa — wynik znamy
 *                            dopiero po dopasowaniu; domyka je
 *                            chatl_oznacz_trafienie()).
 * @return int Id zapisanego wiersza, 0 = zapis padł. Odpowiedź bota i tak
 *             leci do gościa; brak wpisu w historii nie jest powodem, żeby
 *             urwać działającą rozmowę — ale ma zostawić ślad w logu.
 */
/**
 * Nazwa roli dla CZŁOWIEKA — jedno źródło dla eksportu RODO, CSV i panelu.
 *
 * W tabeli rozmów są CZTERY role (`gosc`, `bot`, `operator`, `system`), a mapy
 * „wszystko, co nie jest gościem, to Bot" były w dwóch miejscach: eksporter RODO
 * i eksport CSV. Skutek: osoba, która dostała swoje dane na wniosek RODO,
 * czytała, że odpowiadał jej „Bot", choć pisał do niej człowiek z obsługi
 * (audyt P2-Z4) — a komunikaty systemowe („do rozmowy dołączył konsultant")
 * wyglądały na wypowiedzi bota.
 *
 * @param string $rola Rola z kolumny `rola`.
 * @return string Nazwa do pokazania człowiekowi.
 */
function chatl_nazwa_roli( $rola ) {
	$nazwy = array(
		'gosc'     => 'Gość',
		'bot'      => 'Bot',
		'operator' => 'Konsultant (człowiek)',
		'system'   => 'Komunikat systemowy',
	);
	$rola  = (string) $rola;
	return isset( $nazwy[ $rola ] ) ? $nazwy[ $rola ] : $rola;
}

function chatl_zapisz_wymiane( $sesja, $rola, $tresc, $silnik = 'reguly', $trafiono = null ) {
	global $wpdb;
	$dane   = array(
		'sesja'     => $sesja,
		'rola'      => $rola,
		'silnik'    => $silnik,
		'tresc'     => $tresc,
		'utworzono' => current_time( 'mysql' ),
	);
	$format = array( '%s', '%s', '%s', '%s', '%s' );
	if ( null !== $trafiono ) {
		$dane['trafiono'] = $trafiono ? 1 : 0;
		$format[]         = '%d';
	}
	$zapisano = $wpdb->insert( $wpdb->prefix . 'chatl_rozmowy', $dane, $format );
	if ( false === $zapisano ) {
		chatl_log_bledu_bazy( 'chatl_zapisz_wymiane', 'wpis do historii rozmów (rola: ' . $rola . ')' );
		return 0;
	}
	return (int) $wpdb->insert_id;
}

/**
 * Dopisuje wynik dopasowania do wiersza PYTANIA gościa.
 *
 * Osobny krok, bo w chwili zapisu pytania jeszcze nie wiemy, czy bot je
 * zrozumie. Znacznik musi wylądować na wierszu gościa (nie tylko bota), bo
 * raport luk pokazuje TREŚĆ PYTANIA — czyli to, czego brakuje w bazie Q&A.
 *
 * @param int  $id_pytania Id z chatl_zapisz_wymiane() (0 = zapis pytania padł).
 * @param bool $trafiono   Czy bot znał odpowiedź.
 * @return bool True = oznaczono.
 */
function chatl_oznacz_trafienie( $id_pytania, $trafiono ) {
	$id_pytania = (int) $id_pytania;
	if ( $id_pytania <= 0 ) {
		return false;
	}
	global $wpdb;
	$wynik = $wpdb->update(
		$wpdb->prefix . 'chatl_rozmowy',
		array( 'trafiono' => $trafiono ? 1 : 0 ),
		array( 'id' => $id_pytania ),
		array( '%d' ),
		array( '%d' )
	);
	if ( false === $wynik ) {
		chatl_log_bledu_bazy( 'chatl_oznacz_trafienie', 'oznaczenie trafienia przy pytaniu (id: ' . $id_pytania . ')' );
		return false;
	}
	return true;
}
