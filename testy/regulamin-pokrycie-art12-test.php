<?php
/**
 * F5-F2 — szablon Regulaminu MUSI pokrywać punkty art. 12 ust. 1 ustawy o prawach konsumenta
 * wymienione w audycie (`audyt/plamy/F5-RAPORT.md` §② — pkt 5, 7, 8, 13, 14, 19/20, 21).
 * Do 0.9.40 szablon miał 5 linii i pokrywał tylko pkt 12 (odstąpienie) i częściowo 2/14 —
 * ten strażnik czyta ŹRÓDŁO PHP wprost (bez WordPressa: string jest literałem w kodzie),
 * więc próba kontrolna „usuń jeden nagłówek" nie wymaga stosu Dockera.
 *
 * Uruchomienie:  php testy/regulamin-pokrycie-art12-test.php
 * Kody: 0 = wszystkie nagłówki obecne · 1 = brakuje choć jednego · 2 = nie znalazłam literału (przyrząd).
 */

$plik = __DIR__ . '/../wtyczki/sklep-agentow/includes/aktywacja.php';
if ( ! is_file( $plik ) ) {
	fwrite( STDERR, "PRZYRZAD: brak pliku $plik\n" );
	exit( 2 );
}
$zrodlo = file_get_contents( $plik );

// Wyciągamy literał przypisany do klucza 'sklepag_strona_regulamin' — drugi element tablicy (treść strony).
if ( ! preg_match( "/'sklepag_strona_regulamin'\s*=>\s*array\(\s*'[^']*',\s*\"((?:[^\"\\\\]|\\\\.)*)\"/s", $zrodlo, $m ) ) {
	fwrite( STDERR, "PRZYRZAD: nie znalazlam literalu szablonu regulaminu w $plik\n" );
	exit( 2 );
}
// Odwracamy PHP-owe escapowanie \n, \" itd. tak, jak zrobiłby to interpreter tego literału.
$tekst = stripcslashes( $m[1] );

// Lista nagłówków = lista z raportu F5 (§② tabela, punkty faktycznie brakujące do 0.9.40).
// pkt 2 (dane sprzedawcy) i pkt 12 (odstąpienie) były już pokryte — nie duplikujemy ich tu.
$WYMAGANE = array(
	'pkt 5 (cena z podatkami)'                    => 'Cena:',
	'pkt 7 (sposób i termin zapłaty)'             => 'Sposób i termin zapłaty:',
	'pkt 8 (procedura reklamacyjna)'              => 'Procedura reklamacyjna:',
	'pkt 13 (odpowiedzialność za zgodność)'       => 'Odpowiedzialność za zgodność świadczenia z umową:',
	'pkt 14 (gwarancja — już było od 0.9.40)'     => 'Gwarancja satysfakcji',
	'pkt 19/20 (funkcjonalność i kompatybilność)' => 'Funkcjonalność i kompatybilność treści cyfrowej:',
	'pkt 21 (ODR / pozasądowe)'                   => 'Pozasądowe rozwiązywanie sporów:',
);

$bledy = 0;
foreach ( $WYMAGANE as $opis => $naglowek ) {
	$ok = false !== strpos( $tekst, $naglowek );
	echo ( $ok ? '  OK   ' : '  BLAD ' ) . $opis . ' — nagłówek „' . $naglowek . '"' . "\n";
	if ( ! $ok ) {
		$bledy++;
	}
}

// Kontrola dodatnia: coś, co ZAWSZE musi tam być (część nietknięta tym PR-em) — jeśli TO
// akurat nie znajdzie, przyrząd sam jest zepsuty, nie szablon.
$kontrola = false !== strpos( $tekst, 'Sprzedawca:' ) && false !== strpos( $tekst, 'SZABLON' );
echo ( $kontrola ? '  OK   ' : '  BLAD ' ) . 'kontrola dodatnia (Sprzedawca:/SZABLON obecne)' . "\n";
if ( ! $kontrola ) {
	$bledy++;
}

echo "\n";
if ( 0 === $bledy ) {
	echo "REGULAMIN POKRYCIE ART.12: WYNIK=PASS (EXIT=0)\n";
	exit( 0 );
}
echo "REGULAMIN POKRYCIE ART.12: $bledy brak(i) — WYNIK=FAIL (EXIT=1)\n";
exit( 1 );
