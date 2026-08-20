<?php
/**
 * Wygląd widgetu: kolory z ustawień → zmienne CSS + kontrola kontrastu (WCAG).
 *
 * Dlaczego to osobny plik, a nie kilka linii w froncie: liczenie kontrastu to
 * arytmetyka z normy, którą trzeba dać się sprawdzić testem bez uruchamiania
 * przeglądarki (testy/wyglad-test.php). Wszystko poniżej to funkcje czyste —
 * biorą kolory, zwracają liczby i napisy, niczego nie drukują.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Próg kontrastu z WCAG 2.1, kryterium 1.4.3 (poziom AA) dla zwykłego tekstu.
 * To nie jest ozdoba: tekst poniżej tego progu jest nieczytelny dla części
 * użytkowników, a audytor stawia ten zarzut od razu.
 */
const CHATL_WCAG_PROG = 4.5;

/*
 * Dwa kolory tekstu, spośród których dobiera automat: tusz i papier z palety
 * wtyczki (NIE czyste #000/#fff). Dzięki temu automatyczny wybór przy
 * domyślnych kolorach daje DOKŁADNIE to, co widget miał przed 1.4.0 —
 * aktualizacja nie zmienia nikomu wyglądu ani o jeden odcień.
 */
const CHATL_KOLOR_CIEMNY = '#08090b';
const CHATL_KOLOR_JASNY  = '#f5f5f5';

/* Krycie pierścienia fokusu/hover, jak w arkuszu sprzed zmiany. */
const CHATL_PIERSCIEN_ALFA = 0.18;

/**
 * Domyślne wartości ustawień wyglądu = paleta zapisana w assets/widget.css.
 *
 * @return array
 */
function chatl_wyglad_domyslne() {
	return array(
		'kolor_glowny'   => '#bfff38',
		'kolor_naglowka' => '#08090b',
		'kolor_tekstu'   => 'auto',
		'pozycja'        => 'prawo',
	);
}

/**
 * Kolor z formularza → `#rrggbb` albo pusty string.
 *
 * Własna funkcja zamiast sanitize_hex_color(): tamta mieszka w warstwie
 * admina, a ten kod czyta też front. Skrót trzyznakowy rozwijamy sami, żeby
 * dalej w kodzie istniała TYLKO jedna postać koloru.
 *
 * @param string $kolor Wartość z pola formularza.
 * @return string
 */
function chatl_sanitize_kolor( $kolor ) {
	$kolor = strtolower( trim( (string) $kolor ) );
	if ( preg_match( '/^#([0-9a-f]{3})$/', $kolor, $m ) ) {
		return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	if ( preg_match( '/^#[0-9a-f]{6}$/', $kolor ) ) {
		return $kolor;
	}
	return '';
}

/**
 * Składowe RGB koloru (0–255).
 *
 * @param string $kolor `#rrggbb`.
 * @return array [r, g, b]; czerń przy wartości niepoprawnej.
 */
function chatl_rgb( $kolor ) {
	$kolor = chatl_sanitize_kolor( $kolor );
	if ( '' === $kolor ) {
		return array( 0, 0, 0 );
	}
	return array(
		hexdec( substr( $kolor, 1, 2 ) ),
		hexdec( substr( $kolor, 3, 2 ) ),
		hexdec( substr( $kolor, 5, 2 ) ),
	);
}

/**
 * Luminancja względna koloru wg WCAG 2.1 (wzór z normy, nie przybliżenie).
 *
 * @param string $kolor `#rrggbb`.
 * @return float 0.0 (czerń) – 1.0 (biel).
 */
function chatl_luminancja( $kolor ) {
	$wagi   = array( 0.2126, 0.7152, 0.0722 );
	$suma   = 0.0;
	$skladn = chatl_rgb( $kolor );
	foreach ( $skladn as $i => $wartosc ) {
		$c     = $wartosc / 255;
		$c     = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		$suma += $wagi[ $i ] * $c;
	}
	return $suma;
}

/**
 * Współczynnik kontrastu dwóch kolorów wg WCAG 2.1.
 *
 * @param string $a Pierwszy kolor `#rrggbb`.
 * @param string $b Drugi kolor `#rrggbb`.
 * @return float Od 1.0 (identyczne) do 21.0 (czerń–biel), zaokrąglone do 2 miejsc.
 */
function chatl_kontrast( $a, $b ) {
	$la          = chatl_luminancja( $a );
	$lb          = chatl_luminancja( $b );
	$jasniejszy  = max( $la, $lb );
	$ciemniejszy = min( $la, $lb );
	return round( ( $jasniejszy + 0.05 ) / ( $ciemniejszy + 0.05 ), 2 );
}

/**
 * Który kolor tekstu jest czytelny na podanym tle.
 *
 * @param string $tlo Kolor tła `#rrggbb`.
 * @return string CHATL_KOLOR_CIEMNY albo CHATL_KOLOR_JASNY.
 */
function chatl_kolor_tekstu_na( $tlo ) {
	return chatl_kontrast( $tlo, CHATL_KOLOR_CIEMNY ) >= chatl_kontrast( $tlo, CHATL_KOLOR_JASNY )
		? CHATL_KOLOR_CIEMNY
		: CHATL_KOLOR_JASNY;
}

/**
 * Pierścień fokusu/hover w kolorze głównym, z tym samym kryciem co wcześniej.
 *
 * @param string $kolor Kolor główny `#rrggbb`.
 * @return string Zapis `rgba(r, g, b, a)`.
 */
function chatl_pierscien_z( $kolor ) {
	list( $r, $g, $b ) = chatl_rgb( $kolor );
	return 'rgba(' . (int) $r . ', ' . (int) $g . ', ' . (int) $b . ', ' . CHATL_PIERSCIEN_ALFA . ')';
}

/**
 * Ustawienia wyglądu po sanityzacji, z domyślnymi w miejsce śmieci.
 *
 * @param array|null $ust Ustawienia wtyczki; null = pobierz.
 * @return array Klucze jak w chatl_wyglad_domyslne().
 */
function chatl_wyglad_ustawienia( $ust = null ) {
	$dom = chatl_wyglad_domyslne();
	if ( null === $ust ) {
		$ust = chatl_ustawienia();
	}

	$glowny   = chatl_sanitize_kolor( isset( $ust['kolor_glowny'] ) ? $ust['kolor_glowny'] : '' );
	$naglowek = chatl_sanitize_kolor( isset( $ust['kolor_naglowka'] ) ? $ust['kolor_naglowka'] : '' );
	$tekst    = isset( $ust['kolor_tekstu'] ) ? (string) $ust['kolor_tekstu'] : $dom['kolor_tekstu'];
	if ( 'auto' !== $tekst ) {
		$tekst = chatl_sanitize_kolor( $tekst );
	}
	$pozycja = isset( $ust['pozycja'] ) ? (string) $ust['pozycja'] : $dom['pozycja'];

	return array(
		'kolor_glowny'   => '' !== $glowny ? $glowny : $dom['kolor_glowny'],
		'kolor_naglowka' => '' !== $naglowek ? $naglowek : $dom['kolor_naglowka'],
		'kolor_tekstu'   => '' !== $tekst ? $tekst : $dom['kolor_tekstu'],
		'pozycja'        => in_array( $pozycja, array( 'prawo', 'lewo' ), true ) ? $pozycja : $dom['pozycja'],
	);
}

/**
 * Zmienne CSS widgetu wyliczone z ustawień.
 *
 * Tryb `auto` (domyślny) dobiera kolor tekstu OSOBNO dla bąbelka i dla paska
 * nagłówka — bo to dwa różne tła i jeden napis nie może być czytelny na obu,
 * gdy właściciel wybierze np. limonkę i granat.
 *
 * @param array|null $ust Ustawienia wtyczki; null = pobierz.
 * @return array Nazwa zmiennej CSS => wartość.
 */
function chatl_zmienne_wygladu( $ust = null ) {
	$w = chatl_wyglad_ustawienia( $ust );

	if ( 'auto' === $w['kolor_tekstu'] ) {
		$tekst_glowny   = chatl_kolor_tekstu_na( $w['kolor_glowny'] );
		$tekst_naglowka = chatl_kolor_tekstu_na( $w['kolor_naglowka'] );
	} else {
		$tekst_glowny   = $w['kolor_tekstu'];
		$tekst_naglowka = $w['kolor_tekstu'];
	}

	return array(
		'--chatl-glowny'         => $w['kolor_glowny'],
		'--chatl-glowny-tekst'   => $tekst_glowny,
		'--chatl-naglowek'       => $w['kolor_naglowka'],
		'--chatl-naglowek-tekst' => $tekst_naglowka,
		'--chatl-pierscien'      => chatl_pierscien_z( $w['kolor_glowny'] ),
	);
}

/**
 * Zmienne, które arkusz `assets/widget.css` ma już wpisane jako domyślne.
 *
 * Trzymane tutaj po to, żeby dało się sprawdzić, czy właściciel COKOLWIEK
 * zmienił — jeśli nie, front nie dostaje ani jednego dodatkowego bajtu.
 *
 * @return array
 */
function chatl_zmienne_domyslne() {
	return chatl_zmienne_wygladu( chatl_wyglad_domyslne() );
}

/**
 * Reguła CSS z nadpisanymi zmiennymi — albo pusty string.
 *
 * Trzy rzeczy, o które prosiła recenzja wyglądu:
 *   1. NIE generujemy arkusza od nowa — wychodzi kilka deklaracji zmiennych,
 *      reszta stylów zostaje w statycznym pliku (cache przeglądarki działa),
 *   2. wypisujemy TYLKO zmienne różne od domyślnych — właściciel, który
 *      niczego nie zmienił, nie dostaje na stronie ani jednego bajtu extra,
 *   3. zmienne siedzą na `.chatl-korzen`, nie w `:root` — wtyczka nie zaśmieca
 *      globalnej przestrzeni nazw motywu klienta.
 *
 * @param array|null $ust Ustawienia wtyczki; null = pobierz.
 * @return string Pusty string, gdy wygląd jest domyślny.
 */
function chatl_style_wygladu( $ust = null ) {
	$teraz    = chatl_zmienne_wygladu( $ust );
	$domyslne = chatl_zmienne_domyslne();

	$rozne = array();
	foreach ( $teraz as $nazwa => $wartosc ) {
		if ( ! isset( $domyslne[ $nazwa ] ) || $domyslne[ $nazwa ] !== $wartosc ) {
			$rozne[] = $nazwa . ':' . $wartosc;
		}
	}
	if ( ! $rozne ) {
		return '';
	}
	return '.chatl-korzen{' . implode( ';', $rozne ) . '}';
}

/**
 * Ocena kontrastu zestawu kolorów — do pokazania właścicielowi w panelu.
 *
 * @param array|null $ust Ustawienia wtyczki; null = pobierz.
 * @return array Lista: etykieta, tlo, tekst, kontrast (float), ok (bool).
 */
function chatl_ocena_kontrastu( $ust = null ) {
	$z     = chatl_zmienne_wygladu( $ust );
	$pary  = array(
		array( 'Bąbelek i przyciski', $z['--chatl-glowny'], $z['--chatl-glowny-tekst'] ),
		array( 'Pasek nagłówka', $z['--chatl-naglowek'], $z['--chatl-naglowek-tekst'] ),
	);
	$wynik = array();
	foreach ( $pary as $para ) {
		$k       = chatl_kontrast( $para[1], $para[2] );
		$wynik[] = array(
			'etykieta' => $para[0],
			'tlo'      => $para[1],
			'tekst'    => $para[2],
			'kontrast' => $k,
			'ok'       => $k >= CHATL_WCAG_PROG,
		);
	}
	return $wynik;
}
