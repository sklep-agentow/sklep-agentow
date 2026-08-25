<?php
/**
 * Render sekcji OPINIE KLIENTÓW — dowód, który przeżyje cofnięcie danych przykładowych.
 *
 * Powód (recenzja #176, WAŻNA 4): obie definicje produktów mają 'opinie' => array(),
 * więc blok renderu nie uruchamia się na żywej stronie i bramki go nie dotykały.
 * Ten test woła sklepag_render_opinie() na WŁASNEJ tablicy — dowód nie zależy od
 * tego, czy produkty mają wpisy. Sprawdza też przypadek bez problemu (pusta lista)
 * i przypadki brzegowe: brak imienia, gwiazdki poza zakresem, stary format, XSS.
 *
 * Uruchomienie:
 *   docker cp testy/opinie-render-test.php strona_wp_wordpress:/tmp/opinie-render-test.php
 *   docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/opinie-render-test.php
 * (albo wąskim oknem recenzenta: bash narzedzia-qa/test-php.sh opinie-render-test)
 * Kod wyjścia 0 = wszystkie kroki przeszły, 1 = któryś nie.
 */

require_once WP_PLUGIN_DIR . '/sklep-agentow/includes/front-produkt.php';

$GLOBALS['sklepag_test_bledy'] = 0;

function sprawdz( $opis, $warunek, $szczegol = '' ) {
	echo ( $warunek ? '  OK   ' : '  BLAD ' ) . $opis . "\n";
	if ( '' !== $szczegol && ! $warunek ) {
		echo '       ' . $szczegol . "\n";
	}
	if ( ! $warunek ) {
		++$GLOBALS['sklepag_test_bledy'];
	}
}

echo "=== A. Przypadek bez problemu: pusta lista = sekcji NIE MA ===\n";
sprawdz( 'pusta tablica daje pusty string', '' === sklepag_render_opinie( array() ) );
sprawdz( 'null daje pusty string', '' === sklepag_render_opinie( null ) );

echo "\n=== B. Nowy format: pelny wpis ===\n";
$html = sklepag_render_opinie(
	array(
		array(
			'tresc'    => 'Bot odpowiada klientom, a trudne <script>alert(1)</script> oddaje mnie.',
			'imie'     => 'anna Kowalska',
			'gwiazdki' => 3,
			'tytul'    => 'Kupione, dziala',
			'data'     => '12.08.2026',
		),
		array(
			'tresc' => 'Drugi wpis bez imienia i bez gwiazdek.',
		),
	)
);
sprawdz( 'sekcja z naglowkiem OPINIE KLIENTÓW', false !== strpos( $html, '[ OPINIE KLIENTÓW ]' ) );
sprawdz( 'avatar = inicjal z DUZEJ litery (anna -> A)', false !== strpos( $html, '<span class="sklepag-avatar" aria-hidden="true">A</span>' ) );
sprawdz( 'imie w calosci przy wpisie', false !== strpos( $html, '<strong>anna Kowalska</strong>' ) );
sprawdz( 'gwiazdki: aria-label „Ocena 3 na 5"', false !== strpos( $html, 'aria-label="Ocena 3 na 5"' ) );
sprawdz( '3 pelne gwiazdki przed spanem pustych', false !== strpos( $html, '>★★★<span class="sklepag-gwiazdki-puste">' ) );
sprawdz( '2 puste gwiazdki w spanie', false !== strpos( $html, '<span class="sklepag-gwiazdki-puste">★★</span>' ) );
sprawdz( 'tytul opinii jest', false !== strpos( $html, '<p class="sklepag-opinia-tytul">Kupione, dziala</p>' ) );
sprawdz( 'data w figcaption', false !== strpos( $html, '12.08.2026</figcaption>' ) );
sprawdz( 'tresc w blockquote, XSS ZESCAPOWANY', false !== strpos( $html, '&lt;script&gt;' ) && false === strpos( $html, '<script>' ) );
sprawdz( 'nota o weryfikacji pod sekcja', false !== strpos( $html, 'Publikujemy wyłącznie opinie osób' ) );

echo "\n=== C. Plakietka: przy KAZDYM wpisie nowego formatu (WAZNA 3) ===\n";
sprawdz( 'plakietka 2x przy 2 wpisach nowego formatu', 2 === substr_count( $html, 'ZWERYFIKOWANY ZAKUP' ) );

echo "\n=== D. Brak imienia: znak zastepczy zamiast pustego krazka (DROBNA 5) ===\n";
sprawdz( 'zero pustych <strong></strong>', false === strpos( $html, '<strong></strong>' ) );
sprawdz( 'wpis bez imienia dostaje „Klient"', false !== strpos( $html, '<strong>Klient</strong>' ) );
sprawdz( 'avatar wpisu bez imienia = K', false !== strpos( $html, '<span class="sklepag-avatar" aria-hidden="true">K</span>' ) );
$html_spacja = sklepag_render_opinie(
	array(
		array(
			'tresc' => 'x',
			'imie'  => '   ',
		),
	)
);
sprawdz( 'imie z samych spacji tez daje „Klient"', false !== strpos( $html_spacja, '<strong>Klient</strong>' ) );

echo "\n=== E. Gwiazdki poza zakresem: zacisk 1-5 ===\n";
$html_9 = sklepag_render_opinie(
	array(
		array(
			'tresc'    => 'x',
			'imie'     => 'Ala',
			'gwiazdki' => 9,
		),
	)
);
sprawdz( '9 gwiazdek zaciska sie do 5, span pustych PUSTY', false !== strpos( $html_9, 'aria-label="Ocena 5 na 5"' ) && false !== strpos( $html_9, '<span class="sklepag-gwiazdki-puste"></span>' ) );
$html_0 = sklepag_render_opinie(
	array(
		array(
			'tresc'    => 'x',
			'imie'     => 'Ala',
			'gwiazdki' => 0,
		),
	)
);
sprawdz( '0 gwiazdek zaciska sie do 1', false !== strpos( $html_0, 'aria-label="Ocena 1 na 5"' ) );
$html_bez = sklepag_render_opinie(
	array(
		array(
			'tresc' => 'x',
			'imie'  => 'Ala',
		),
	)
);
sprawdz( 'brak klucza gwiazdki = 5 (domysl)', false !== strpos( $html_bez, 'aria-label="Ocena 5 na 5"' ) );

echo "\n=== F. Polski inicjal wielobajtowy ===\n";
$html_pl = sklepag_render_opinie(
	array(
		array(
			'tresc' => 'x',
			'imie'  => 'łukasz',
		),
	)
);
sprawdz( 'inicjal Ł (wielobajtowy, z duzej)', false !== strpos( $html_pl, '>Ł</span>' ) );

echo "\n=== G. Dawny format array( tresc, podpis ) POMIJANY — lista mieszana nie przeczy nocie ===\n";
$html_stary = sklepag_render_opinie( array( array( 'Stary wpis dziala.', '— Jan, warsztat' ) ) );
sprawdz( 'wpis wylacznie dawnego formatu = sekcji NIE MA (zero noty bez opinii)', '' === $html_stary );
$html_mix = sklepag_render_opinie(
	array(
		array( 'Stary wpis.', '— Jan' ),
		array(
			'tresc' => 'Nowy wpis.',
			'imie'  => 'Ewa',
		),
	)
);
sprawdz( 'lista mieszana: renderuje sie tylko wpis nowego formatu', false !== strpos( $html_mix, '<strong>Ewa</strong>' ) && false === strpos( $html_mix, 'Stary wpis.' ) );
sprawdz( 'lista mieszana: kazdy WIDOCZNY wpis ma plakietke (1 wpis = 1 plakietka)', 1 === substr_count( $html_mix, 'ZWERYFIKOWANY ZAKUP' ) );

echo "\n";
if ( $GLOBALS['sklepag_test_bledy'] > 0 ) {
	echo 'BLEDY: ' . $GLOBALS['sklepag_test_bledy'] . "\n";
	exit( 1 );
}
echo "WSZYSTKIE KROKI PRZESZLY\n";
exit( 0 );
