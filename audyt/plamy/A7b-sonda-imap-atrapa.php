<?php
/**
 * Atrapa serwera IMAP dla sondy audytowej. Jeden klient, jeden scenariusz.
 * Uruchomienie: php atrapa-serwera.php <port> <scenariusz>
 */
$port = (int) $argv[1];
$scen = $argv[2];
$serw = stream_socket_server( "tcp://127.0.0.1:$port", $errno, $errstr );
if ( ! $serw ) { fwrite( STDERR, "ATRAPA NIE WSTALA: $errstr\n" ); exit( 3 ); }
$k = stream_socket_accept( $serw, 30 );
if ( ! $k ) { exit( 3 ); }
function w( $k, $s ) { fwrite( $k, $s ); }
function czytaj( $k ) { return fgets( $k ); }

w( $k, "* OK atrapa gotowa\r\n" );
$c = czytaj( $k );                       // CAPABILITY albo LOGIN
w( $k, "* CAPABILITY IMAP4rev1\r\n" );
$tag = strtok( (string) $c, ' ' );
w( $k, "$tag OK done\r\n" );

$c   = czytaj( $k );                     // polecenie badane
$tag = strtok( (string) $c, ' ' );

switch ( $scen ) {
	case 'literal-ogromny':
		// deklaruje 900 MB i STRUMIENIUJE bez konca
		w( $k, "* 1 FETCH (BODY[HEADER] {943718400}\r\n" );
		$blok = str_repeat( 'A', 65536 );
		while ( true ) { if ( false === @fwrite( $k, $blok ) ) { break; } }
		break;

	case 'literal-sama-deklaracja':
		// deklaruje 900 MB i NIE wysyla ani jednego bajtu danych
		w( $k, "* 1 FETCH (BODY[HEADER] {943718400}\r\n" );
		sleep( 60 );
		break;

	case 'poprawny':
		// KONTROLA DODATNIA: poprawna odpowiedz, klient ma zwrocic tresc
		$h = "Message-ID: <a@b>\r\nSubject: test\r\n\r\n";
		w( $k, '* 1 FETCH (BODY[HEADER] {' . strlen( $h ) . "}\r\n" );
		w( $k, $h );
		w( $k, ")\r\n" );
		w( $k, "$tag OK FETCH completed\r\n" );
		break;

	case 'literal-obciety':
		// deklaruje 1000 bajtow, wysyla 10 i zamyka
		w( $k, "* 1 FETCH (BODY[HEADER] {1000}\r\n" );
		w( $k, "ABCDEFGHIJ" );
		fclose( $k ); exit( 0 );

	case 'bez-crlf':
		// bajty bez konca linii, potem cisza
		w( $k, "* 1 FETCH " . str_repeat( 'X', 4096 ) );
		sleep( 60 );
		break;

	case 'bez-znacznika':
		// nieskonczony strumien linii nietagowanych — brak linii konczacej
		while ( true ) { if ( false === @fwrite( $k, "* 1 EXISTS\r\n" ) ) { break; } }
		break;

	case 'linia-ogromna':
		// JEDNA linia bez konca, rosnaca bez konca (fgets bez limitu)
		$blok = str_repeat( 'B', 65536 );
		while ( true ) { if ( false === @fwrite( $k, $blok ) ) { break; } }
		break;
}
fclose( $k );
