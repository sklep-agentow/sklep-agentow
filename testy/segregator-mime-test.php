<?php
/**
 * Segregator maila — czy reguła „treść zawiera" widzi TO SAMO, co człowiek.
 *
 * Znalezisko A7c-F1 (`audyt/plamy/A7c-RAPORT.md` §2) miało CZTERY drogi, każdą
 * z inną przyczyną, i naprawa jednej nie zamykała pozostałych:
 *   ① mail wieloczęściowy nie był w ogóle dekodowany — reguła dostawała granice
 *      części, nagłówki MIME i base64 (`multipart/alternative` to kształt, jaki
 *      wysyła Gmail, Outlook i Thunderbird po sformatowaniu),
 *   ② `array_pop()` bezwarunkowo odcinał ostatnią linię base64 — krótki mail
 *      tracił swoją JEDYNĄ linię i cała treść znikała,
 *   ③ nazwa zestawu znaków była sprawdzana wobec `mb_list_encodings()`, gdzie
 *      siedzą kodowania TRANSPORTOWE — `charset=BASE64` od nadawcy zamieniał
 *      czytelny tekst w bajtowy śmieć,
 *   ④ `strip_tags()` kasował od niedomkniętego `<` do końca fragmentu — zdanie
 *      „termin <piatek. Proszę o faktura pro forma" traciło wszystko po słowie
 *      „termin".
 *
 * Każdy scenariusz niżej jest mailem, w którym człowiek WIDZI słowo reguły.
 * Test pyta o dwie rzeczy naraz: czy reguła trafia i czy do dopasowania NIE
 * trafiają nagłówki MIME (inaczej reguła „treść zawiera charset" łapałaby
 * każdy wieloczęściowy mail — to druga strona tej samej wady, scenariusz E).
 *
 * ⛔ Bez WordPressa i bez dockera: `segm_skrzynka_tresc_tekstem()` i
 *    `segm_wzorzec_pasuje()` ładują się z PLIKU, tak jak robi to sonda audytu.
 *    Uruchamianie przez `narzedzia-qa/test-php.sh` mierzyłoby kod KONTENERA,
 *    nie kod z worktree (klasa 141 ze ściągi egzaminatora).
 *
 * Uruchomienie:  php testy/segregator-mime-test.php
 * Kod wyjścia 0 = wszystkie asercje przeszły, 1 = któraś nie.
 *
 * Zmienna `SEGM_KATALOG_WTYCZKI` wskazuje katalog `includes/` wtyczki —
 * używa jej próba kontrolna, żeby podłożyć mutanta w KOPII, nie w repo.
 */

define( 'ABSPATH', __DIR__ );

/**
 * Atrapa `wp_strip_all_tags()` — sciezka skalarna przepisana z WordPressa,
 * tak jak w sondzie audytu `audyt/plamy/A7c-sonda-mime.php`.
 *
 * Naprawiona funkcja JEJ NIE WOLA (powod w `skrzynka.php`), ale atrapa musi
 * tu byc: bez niej kod SPRZED naprawy i mutanty proby kontrolnej padalyby na
 * brak funkcji zamiast na asercji — czyli test mierzylby brak WordPressa,
 * a nie zachowanie produktu.
 */
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

$katalog = getenv( 'SEGM_KATALOG_WTYCZKI' );
if ( ! is_string( $katalog ) || '' === $katalog ) {
	$katalog = dirname( __DIR__ ) . '/wtyczki/segregator-maila/includes';
}
$katalog = rtrim( $katalog, '/' ) . '/';
if ( ! is_file( $katalog . 'skrzynka.php' ) ) {
	fwrite( STDERR, "BLAD nie znalazlem skrzynka.php w: $katalog\n" );
	exit( 2 );
}
require $katalog . 'skrzynka.php';
require $katalog . 'silnik.php';

$bledy = 0;

/**
 * Jedna asercja z wypisanym powodem.
 *
 * @param bool   $ok        Czy warunek spełniony.
 * @param string $opis      Co sprawdzamy.
 * @param string $szczegol  Zmierzona wartość (nie przepisana!).
 * @return void
 */
function krok( $ok, $opis, $szczegol = '' ) {
	global $bledy;
	printf( "  %s %s%s\n", $ok ? 'OK  ' : 'BLAD', $opis, '' !== $szczegol ? " [$szczegol]" : '' );
	if ( ! $ok ) {
		++$bledy;
	}
}

/** Skrót: co zobaczy reguła właściciela. */
function widzi( $tresc, $naglowki ) {
	return segm_skrzynka_tresc_tekstem( $tresc, $naglowki );
}

/** Czy reguła o takim wzorcu trafia w to, co zobaczyła funkcja. */
function regula( $tekst, $wzorzec ) {
	return segm_wzorzec_pasuje( mb_strtolower( $tekst ), $wzorzec );
}

/** Skrócony podgląd tekstu do komunikatu (bez znaków końca linii). */
function podglad( $tekst, $ile = 48 ) {
	return str_replace( array( "\r", "\n" ), ' ', mb_substr( $tekst, 0, $ile ) );
}

$b64      = static function ( $s ) {
	return chunk_split( base64_encode( $s ), 76, "\r\n" );
};
$widoczny = "Dzien dobry,\r\nw zalaczniku Faktura nr 7/2026.\r\nPozdrawiam";
$nag      = static function ( $typ, $kodowanie ) {
	return "From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: $typ\r\nContent-Transfer-Encoding: $kodowanie\r\n\r\n";
};

echo "=== SEGREGATOR / MIME: regula widzi to, co czlowiek (A7c-F1, cztery drogi) ===\n\n";

// ---------------------------------------------------------------- kontrola dodatnia
echo "KONTROLA DODATNIA — najzwyklejszy mail\n";
$t = widzi( $widoczny, $nag( 'text/plain; charset=UTF-8', '7bit' ) );
krok( $t === $widoczny, 'A. text/plain 7bit oddany bez zmian', strlen( $t ) . ' znakow' );
krok( regula( $t, 'faktura' ), 'A. regula "faktura" TRAFIA' );
krok( ! regula( $t, 'nieobecneslowoxyz' ), 'A. regula na slowo NIEOBECNE nie trafia (dopasowanie nie mowi "tak" zawsze)' );

// ---------------------------------------------------------------- droga ②
echo "\nDROGA 2 — base64 bez bezwarunkowego odcinania ostatniej linii\n";
$t = widzi( rtrim( $b64( $widoczny ), "\r\n" ), $nag( 'text/plain; charset=UTF-8', 'base64' ) );
krok( $t === $widoczny, 'B. base64 w JEDNEJ linii bez CRLF nie znika', strlen( $t ) . ' znakow' );
$dlugi = str_repeat( 'Tresc wypelniajaca. ', 12 ) . $widoczny;
$t     = widzi( $b64( $dlugi ), $nag( 'text/plain; charset=UTF-8', 'base64' ) );
krok( $t === $dlugi, 'C. base64 w wielu liniach z CRLF odkodowany w calosci', strlen( $t ) . ' znakow' );

// ---------------------------------------------------------------- droga ①
echo "\nDROGA 1 — mail wieloczesciowy (ksztalt z Gmaila/Outlooka/Thunderbirda)\n";
$multi_b64 = "--gr4nica\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
	. $b64( $widoczny )
	. "--gr4nica\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
	. $b64( '<p>' . $widoczny . '</p>' )
	. "--gr4nica--\r\n";
$nag_multi = "From: klient@example.com\r\nSubject: Faktura\r\nContent-Type: multipart/alternative; boundary=\"gr4nica\"\r\n\r\n";
$t         = widzi( $multi_b64, $nag_multi );
krok( $t === $widoczny, 'D. multipart/alternative, czesc tekstowa base64 -> tekst czlowieka', podglad( $t ) );
krok( regula( $t, 'faktura' ), 'D. regula "faktura" TRAFIA' );
krok( ! regula( $t, 'charset' ), 'D. naglowki MIME NIE trafiaja do tekstu (regula "charset" nie lapie)' );
krok( ! regula( $t, 'gr4nica' ), 'D. granica czesci NIE trafia do tekstu' );
krok( ! regula( $t, 'content-transfer-encoding' ), 'D. nazwa naglowka czesci NIE trafia do tekstu' );

$multi_7bit = "--gr4nica\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n"
	. $widoczny . "\r\n--gr4nica--\r\n";
$t          = widzi( $multi_7bit, $nag_multi );
krok( $t === $widoczny, 'E. multipart/alternative, czesc tekstowa 7bit -> tekst czlowieka', podglad( $t ) );
krok( ! regula( $t, 'charset' ), 'E. naglowki MIME NIE trafiaja do tekstu' );

// multipart/alternative ZAGNIEZDZONY w multipart/mixed — poczta z zalacznikiem.
$mixed = "--zewn\r\nContent-Type: multipart/alternative; boundary=\"gr4nica\"\r\n\r\n"
	. $multi_b64
	. "--zewn\r\nContent-Type: application/pdf; name=\"faktura.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
	. $b64( 'PDF-BINARIA-NIE-DLA-REGULY' )
	. "--zewn--\r\n";
$t     = widzi( $mixed, "From: klient@example.com\r\nContent-Type: multipart/mixed; boundary=zewn\r\n\r\n" );
krok( $t === $widoczny, 'D2. multipart/alternative w multipart/mixed (rekurencja) -> tekst czlowieka', podglad( $t ) );
krok( ! regula( $t, 'pdf-binaria' ), 'D2. zalacznik NIE trafia do tekstu' );

// multipart BEZ czesci text/plain — zostaje text/html po zdjeciu znacznikow.
$tylko_html = "--gr4nica\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
	. quoted_printable_encode( '<html><body><p>Dzien dobry, w zalaczniku Faktura nr 7/2026.</p></body></html>' )
	. "\r\n--gr4nica--\r\n";
$t          = widzi( $tylko_html, $nag_multi );
krok( regula( $t, 'faktura' ), 'D3. multipart tylko z text/html -> regula TRAFIA', podglad( $t ) );
krok( ! regula( $t, 'body' ), 'D3. znaczniki HTML zdjete (regula "body" nie lapie)' );
krok( ! regula( $t, 'charset' ), 'D3. naglowki MIME NIE trafiaja do tekstu' );

// ---------------------------------------------------------------- droga ③
echo "\nDROGA 3 — charset to ZESTAW ZNAKOW, nie kodowanie transportowe\n";
$t = widzi( $widoczny, $nag( 'text/plain; charset=BASE64', '7bit' ) );
krok( $t === $widoczny, 'G. charset=BASE64 od nadawcy nie przepuszcza tekstu przez dekoder', podglad( $t ) );
$t = widzi( $widoczny, $nag( 'text/plain; charset=QUOTED-PRINTABLE', '7bit' ) );
krok( $t === $widoczny, 'G2. charset=QUOTED-PRINTABLE tak samo', podglad( $t ) );
$pl_utf   = "Zamowienie: zolc, faktura gotowa";
$pl_8859  = (string) mb_convert_encoding( $pl_utf, 'ISO-8859-2', 'UTF-8' );
$t        = widzi( $pl_8859, $nag( 'text/plain; charset=ISO-8859-2', '8bit' ) );
krok( $t === $pl_utf, 'G3. PRAWDZIWY zestaw znakow (ISO-8859-2) nadal przewalutowany na UTF-8', podglad( $t ) );
krok( mb_check_encoding( $t, 'UTF-8' ), 'G3. wynik jest poprawnym UTF-8' );

// ---------------------------------------------------------------- droga ④
echo "\nDROGA 4 — niedomkniety znak mniejszosci nie zjada reszty tresci\n";
$k = "Dzien dobry,\r\ntermin <piatek.\r\nProsze o faktura pro forma.\r\nPozdrawiam";
$t = widzi( $k, $nag( 'text/plain; charset=UTF-8', '7bit' ) );
krok( $t === $k, 'K. text/plain z "<piatek" zostaje w calosci', strlen( $t ) . ' z ' . strlen( $k ) . ' znakow' );
krok( regula( $t, 'faktura' ), 'K. regula "faktura" TRAFIA' );
$j = "Dzien dobry,\r\nbudzet < 500 zl.\r\nProsze o faktura pro forma.\r\nPozdrawiam";
$t = widzi( $j, $nag( 'text/plain; charset=UTF-8', '7bit' ) );
krok( $t === $j, 'J. zwykly znak mniejszosci w zdaniu handlowym nietkniety', strlen( $t ) . ' znakow' );

// ---------------------------------------------------------------- odpornosc (T15)
echo "\nODPORNOSC — wejscie zlosliwe nie moze zawiesic dekodera (T15 raportu)\n";
$zle = str_repeat( '<script a=', 400 ) . ' Faktura nr 7/2026';
$t0  = microtime( true );
$t   = widzi( $zle, $nag( 'text/html; charset=UTF-8', '7bit' ) );
$czas = microtime( true ) - $t0;
krok( regula( $t, 'faktura' ), 'H. 4 kB niedomknietych <script> — slowo nadal widoczne dla reguly', strlen( $t ) . ' znakow' );
krok( $czas < 0.5, 'H. dekodowanie ponizej progu 0,5 s', sprintf( '%.3f s', $czas ) );

$zlamania = str_repeat( "=\r\n", 2000 ) . 'Faktura nr 7/2026';
$t0       = microtime( true );
$t        = widzi( $zlamania, $nag( 'text/plain; charset=UTF-8', 'quoted-printable' ) );
$czas     = microtime( true ) - $t0;
krok( 'Faktura nr 7/2026' === $t, 'I. quoted-printable z samych zlaman odkodowany', podglad( $t ) );
krok( $czas < 0.5, 'I. dekodowanie ponizej progu 0,5 s', sprintf( '%.3f s', $czas ) );

$glebokie = '';
for ( $i = 12; $i > 0; $i-- ) {
	$glebokie = "--g$i\r\nContent-Type: multipart/mixed; boundary=\"g" . ( $i + 1 ) . "\"\r\n\r\n" . $glebokie . "--g$i--\r\n";
}
$t0   = microtime( true );
$t    = widzi( $glebokie, "Content-Type: multipart/mixed; boundary=\"g1\"\r\n\r\n" );
$czas = microtime( true ) - $t0;
krok( $czas < 0.5, 'L. 12 poziomow zagniezdzenia MIME nie zawiesza rozbioru', sprintf( '%.3f s, %d znakow', $czas, strlen( $t ) ) );

echo "\n";
if ( 0 === $bledy ) {
	echo "WSZYSTKIE ASERCJE PRZESZLY — regula widzi tekst czlowieka w kazdej z czterech drog\n";
	exit( 0 );
}
printf( "NIE PRZESZLO ASERCJI: %d\n", $bledy );
exit( 1 );
