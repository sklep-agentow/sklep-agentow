<?php
/**
 * Strażnik: link do dokumentów prawnych (F5-F1), blok sprzedawcy przed przyciskiem (F5-F3)
 * i kompensacja stron prawnych na instalacji aktualizowanej W MIEJSCU (F5-F4).
 *
 * Uruchomienie: przez spust `narzedzia-qa/prawne-migracja-stos-probny.sh`, który stawia stos
 *               jednorazowy `probny_wp`, wgrywa wtyczkę Z DRZEWA i woła ten plik przez
 *               `wp eval-file`. ⛔ Nie uruchamiać na wspólnym stanowisku: część przypadków
 *               zapisuje opcje `sklepag_strona_*` i zakłada strony (kompensacja robi to sama,
 *               a filtr `pre_option_*` nie pokazałby, co naprawdę wylądowało w bazie).
 *
 * EXIT 0 = wszystkie przypadki wyszły · 1 = któryś nie wyszedł · 2 = brak warunków.
 *
 * ⛔ LICZNIK BŁĘDÓW ŻYJE W TYM SAMYM ZASIĘGU CO ASERCJE (pod `wp eval-file` licznik zamknięty
 * w funkcji potrafi nie dojechać do `exit` i strażnik melduje sukces mimo porażek).
 */

if ( ! function_exists( 'wp_insert_post' ) ) {
	fwrite( STDERR, "PROBA: brak WordPressa\n" );
	exit( 2 );
}
if ( ! defined( 'SKLEPAG_KATALOG' ) ) {
	fwrite( STDERR, "PROBA: wtyczka sklep-agentow nie jest wgrana/aktywna\n" );
	exit( 2 );
}
foreach ( array( 'includes/admin.php', 'includes/admin-dashboard.php' ) as $plik_panelu ) {
	// Panel wczytuje się wyłącznie za `is_admin()`, a `wp eval-file` biegnie z wiersza poleceń.
	// Dociągam jawnie — dlatego niżej stoi OSOBNA asercja `has_action`, bo sam fakt istnienia
	// funkcji nie dowodzi, że jest WPIĘTA.
	if ( file_exists( SKLEPAG_KATALOG . $plik_panelu ) ) {
		require_once SKLEPAG_KATALOG . $plik_panelu;
	}
}
foreach ( array( 'sklepag_link_prawny', 'sklepag_formularz_zakupu', 'sklepag_kompensuj_strony_prawne', 'sklepag_definicje_stron', 'sklepag_migruj_schemat_jesli_trzeba' ) as $wymagana ) {
	if ( ! function_exists( $wymagana ) ) {
		fwrite( STDERR, "PROBA: brak funkcji $wymagana — wgrany kod jest starszy niż ten test\n" );
		exit( 2 );
	}
}

wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	fwrite( STDERR, "PROBA: uzytkownik 1 nie ma manage_options\n" );
	exit( 2 );
}

$bledy = 0;
$sprawdz = function ( $etykieta, $warunek, $szczegol = '' ) use ( &$bledy ) {
	if ( $warunek ) {
		printf( "  OK   %s\n", $etykieta );
		return;
	}
	$bledy++;
	printf( "  ZLE  %s%s\n", $etykieta, $szczegol ? ' — ' . $szczegol : '' );
};

$definicje    = sklepag_definicje_stron();
$wzor_reg     = $definicje['sklepag_strona_regulamin'][1];
$sekcje_wzoru = sklepag_sekcje_szablonu( $wzor_reg );

echo "=== 0. rozpoznanie sekcji wzoru (materiał dla reszty testu) ===\n";
$sprawdz( 'wzor regulaminu ma co najmniej 8 nazwanych sekcji', count( $sekcje_wzoru ) >= 8, 'znaleziono ' . count( $sekcje_wzoru ) );
$sprawdz( 'i sa wsrod nich sekcje z art. 12 ust. 1 dolozone przez F5-F2',
	isset( $sekcje_wzoru['Procedura reklamacyjna'] ) && isset( $sekcje_wzoru['Cena'] ),
	'nazwy: ' . implode( ' | ', array_keys( $sekcje_wzoru ) ) );

echo "=== A. LINK PRAWNY (F5-F1): szkic z zaslepka -> strona 'w przygotowaniu' ===\n";
$id_reg = wp_insert_post( array(
	'post_title'   => 'Regulamin sklepu (SZABLON)',
	'post_content' => $wzor_reg,
	'post_status'  => 'draft',
	'post_type'    => 'page',
) );
if ( ! $id_reg || is_wp_error( $id_reg ) ) {
	fwrite( STDERR, "PROBA: nie udalo sie zalozyc strony testowej\n" );
	exit( 2 );
}
update_option( 'sklepag_strona_regulamin', $id_reg, false );
$link = sklepag_link_prawny( 'regulamin' );
$sprawdz( 'szkic z SZABLON -> adres ?sklepag_prawne=regulamin', false !== strpos( $link, 'sklepag_prawne=regulamin' ), "link: $link" );
$sprawdz( 'i NIE prowadzi na szkic (permalink szkicu)', false === strpos( $link, '?page_id=' . $id_reg ), "link: $link" );

echo "=== A2. opublikowana i bez zaslepki -> link = permalink strony ===\n";
wp_update_post( array( 'ID' => $id_reg, 'post_status' => 'publish', 'post_title' => 'Regulamin sklepu', 'post_content' => 'Sprzedawca: Firma sp. z o.o., ul. Prawdziwa 1.' ) );
clean_post_cache( $id_reg );
$link = sklepag_link_prawny( 'regulamin' );
$sprawdz( 'gotowa strona -> permalink', get_permalink( $id_reg ) === $link, "link: $link" );
$sprawdz( 'i zaden fallback sie nie wlacza', false === strpos( $link, 'sklepag_prawne' ), "link: $link" );

echo "=== B. BLOK SPRZEDAWCY (F5-F3) w formularzu zakupu ===\n";
// ⛔ ATRAPA MA MIEC KOMPLET KOLUMN, ktore czyta formularz (nazwa, cena_grosze, waluta) —
// atrapa uboga produkuje warningi PHP z wlasnego przyrzadu i log bledow oskarza wtedy produkt.
$produkt = (object) array( 'id' => 1, 'nazwa' => 'Bot probny', 'slug' => 'bot-probny', 'cena_grosze' => 12300, 'waluta' => 'PLN', 'opis_krotki' => 'opis', 'opis_pelny' => 'opis' );
// Pulpit renderuje tabele rdzenia WP, ktora czyta HTTP_HOST — w CLI go nie ma.
if ( empty( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST'] = wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . wp_parse_url( home_url(), PHP_URL_PORT );
}
update_option( 'sklepag_firma_nazwa', 'Firma Probna sp. z o.o.', false );
update_option( 'sklepag_firma_adres', "ul. Probna 1\n00-001 Warszawa", false );
update_option( 'sklepag_firma_nip', '1234563218', false );
update_option( 'sklepag_email_kontaktowy', 'kontakt@example.com', false );
$html_pelny = sklepag_formularz_zakupu( $produkt );
$sprawdz( 'komplet danych -> blok sprzedawcy jest w HTML formularza',
	false !== strpos( $html_pelny, 'sklepag-sprzedawca-przed-zakupem' ),
	'kupujacy nie widzi, kto sprzedaje, PRZED klikiem w zakup (art. 12 ust. 1 upk)' );
$sprawdz( 'blok niesie nazwe firmy', false !== strpos( $html_pelny, 'Firma Probna sp. z o.o.' ) );
$sprawdz( 'blok niesie adres', false !== strpos( $html_pelny, 'Probna 1' ) );
$sprawdz( 'blok niesie NIP', false !== strpos( $html_pelny, 'NIP 1234563218' ) );
$sprawdz( 'blok stoi PRZED przyciskiem zakupu (kolejnosc w HTML)',
	strpos( $html_pelny, 'sklepag-sprzedawca-przed-zakupem' ) < strpos( $html_pelny, 'type="submit"' ),
	'blok jest, ale za przyciskiem — decyzja zapada wczesniej' );

echo "=== B2. puste dane sprzedawcy -> zadnej zaslepki, ale ostrzezenie dla wlasciciela ===\n";
update_option( 'sklepag_firma_nazwa', '', false );
update_option( 'sklepag_firma_adres', '', false );
update_option( 'sklepag_firma_nip', '', false );
$html_puste = sklepag_formularz_zakupu( $produkt );
$sprawdz( 'puste dane -> bloku NIE MA (zaslepka bylaby gorsza niz brak)',
	false === strpos( $html_puste, 'sklepag-sprzedawca-przed-zakupem' ) );
$sprawdz( 'kontrola dodatnia: formularz w ogole sie wyrenderowal', false !== strpos( $html_puste, 'sklepag-kup-formularz' ) );
if ( function_exists( 'sklepag_admin_dashboard' ) ) {
	$wyj_pulpit = '';
	try {
		ob_start();
		sklepag_admin_dashboard();
		$wyj_pulpit = (string) ob_get_clean();
	} catch ( \Throwable $e ) {
		$wyj_pulpit = 'BLAD: ' . $e->getMessage();
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}
	$sprawdz( 'pulpit ostrzega, ze kupujacy nie widzi danych sprzedawcy',
		false !== strpos( $wyj_pulpit, 'Kupujący nie widzi danych sprzedawcy' ),
		'wyjscie pulpitu: ' . substr( wp_strip_all_tags( $wyj_pulpit ), 0, 160 ) );
} else {
	$sprawdz( 'pulpit da sie zmierzyc (funkcja sklepag_admin_dashboard istnieje)', false, 'panel nie zaladowal sie w CLI' );
}

echo "=== C. KOMPENSACJA (F5-F4) — wpiecie: migracja MUSI ja wolac ===\n";
$sprawdz( 'ostrzezenie o nowych sekcjach wisi na admin_notices',
	false !== has_action( 'admin_notices', 'sklepag_ostrzez_nowe_sekcje_szablonu' ),
	'funkcja istnieje, ale nikt jej nie wola' );
$sprawdz( 'blizniak z tej samej rodziny tez wisi (kontrola dodatnia pomiaru)',
	false !== has_action( 'admin_notices', 'sklepag_ostrzez_strony_prawne' ) );
update_option( 'sklepag_wersja_schematu', SKLEPAG_WERSJA_SCHEMATU, false ); // brama schematu ZAMKNIETA
delete_option( 'sklepag_wersja_stron' );
delete_transient( 'sklepag_schemat_ponowienie' );
sklepag_migruj_schemat_jesli_trzeba();
$sprawdz( 'migracja podbila znacznik wersji stron mimo aktualnego schematu',
	(int) get_option( 'sklepag_wersja_stron', 0 ) === SKLEPAG_WERSJA_STRON,
	'kompensacja siedzi ZA brama wersji schematu — instalacja z aktualnymi tabelami nigdy jej nie zobaczy' );

echo "=== C1. brak strony (skasowana) -> kompensacja zaklada ja jak aktywacja ===\n";
wp_delete_post( $id_reg, true );
update_option( 'sklepag_strona_regulamin', $id_reg, false ); // opcja wskazuje na wpis, ktorego nie ma
delete_option( 'sklepag_wersja_stron' );
sklepag_kompensuj_strony_prawne();
$id_nowy = (int) get_option( 'sklepag_strona_regulamin', 0 );
$wpis_nowy = $id_nowy ? get_post( $id_nowy ) : null;
$sprawdz( 'po migracji strona regulaminu ISTNIEJE', (bool) $wpis_nowy, "opcja wskazuje na $id_nowy" );
if ( $wpis_nowy ) {
	$sprawdz( 'jest szkicem z zaslepka SZABLON (D39 — nie publikujemy za wlasciciela)',
		'draft' === $wpis_nowy->post_status && false !== strpos( $wpis_nowy->post_title, 'SZABLON' ),
		'status: ' . $wpis_nowy->post_status );
	$sprawdz( 'ma komplet sekcji wzoru', count( sklepag_sekcje_szablonu( $wpis_nowy->post_content ) ) === count( $sekcje_wzoru ) );
}

echo "=== C2. strona-SZABLON bez dwoch sekcji -> kompensacja je DOPISUJE, reszty nie rusza ===\n";
$linie      = explode( "\n", $wzor_reg );
$okrojone   = array();
$usuniete   = array();
foreach ( $linie as $linia ) {
	if ( 0 === strpos( $linia, 'Procedura reklamacyjna:' ) || 0 === strpos( $linia, 'Cena:' ) ) {
		$usuniete[] = $linia;
		continue;
	}
	$okrojone[] = $linia;
}
$wlasny_slad = 'Sprzedawca: MOJA FIRMA WPISANA RECZNIE, ul. Wlasna 7.';
$okrojone[]  = $wlasny_slad;
$id_szablon  = wp_insert_post( array(
	'post_title'   => 'Regulamin sklepu (SZABLON)',
	'post_content' => implode( "\n", $okrojone ),
	'post_status'  => 'draft',
	'post_type'    => 'page',
) );
update_option( 'sklepag_strona_regulamin', $id_szablon, false );
delete_option( 'sklepag_wersja_stron' );
$sprawdz( 'warunek wstepny: dwie sekcje naprawde usuniete', 2 === count( $usuniete ) );
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_szablon );
$po = get_post( $id_szablon );
$tresc_po = $po ? $po->post_content : '';
$sprawdz( 'dopisana sekcja Cena', false !== strpos( $tresc_po, 'Cena:' ), 'brakujaca sekcja NIE zostala dopisana' );
$sprawdz( 'dopisana sekcja Procedura reklamacyjna', false !== strpos( $tresc_po, 'Procedura reklamacyjna:' ) );
$sprawdz( 'tresc wpisana przez wlasciciela NIETKNIETA', false !== strpos( $tresc_po, $wlasny_slad ) );
$sprawdz( 'sekcja juz obecna NIE zostala zdublowana',
	1 === substr_count( $tresc_po, 'Zgoda na natychmiastowe dostarczenie:' ),
	'wystapien: ' . substr_count( $tresc_po, 'Zgoda na natychmiastowe dostarczenie:' ) );
$sprawdz( 'status pozostal szkicem', $po && 'draft' === $po->post_status );
$sprawdz( 'brak meldunku o nowych sekcjach (szablon zalatany, nie ma o czym meldowac)',
	! get_option( 'sklepag_strony_nowe_sekcje', array() ) );

echo "=== C2b. drugi bieg bez podbicia znacznika NIC nie robi (bramka) ===\n";
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_szablon );
$po2 = get_post( $id_szablon );
$sprawdz( 'tresc identyczna po drugim biegu', $po2 && $po2->post_content === $tresc_po,
	'kompensacja dopisuje przy kazdym zadaniu — dokument rosnie bez konca' );

echo "=== C3. WLASNY dokument wlasciciela (bez zaslepki) -> tresc NIETKNIETA, meldunek w panelu ===\n";
$wlasny = "Regulamin sklepu Firmy Wlasnej\n\nSprzedawca: Firma Wlasna sp. z o.o., ul. Prawdziwa 1.\nProdukty: wtyczki.\n";
$id_wlasny = wp_insert_post( array(
	'post_title'   => 'Regulamin sklepu',
	'post_content' => $wlasny,
	'post_status'  => 'publish',
	'post_type'    => 'page',
) );
update_option( 'sklepag_strona_regulamin', $id_wlasny, false );
delete_option( 'sklepag_strony_nowe_sekcje' );
delete_option( 'sklepag_wersja_stron' );
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_wlasny );
$po_wlasny = get_post( $id_wlasny );
$sprawdz( 'tresc wlasnego dokumentu NIETKNIETA', $po_wlasny && $po_wlasny->post_content === $wlasny,
	'kompensacja dopisala cos do gotowego dokumentu prawnego bez wiedzy sprzedawcy' );
$meldunek = get_option( 'sklepag_strony_nowe_sekcje', array() );
$sprawdz( 'jest meldunek o sekcjach, ktorych w dokumencie nie widac', is_array( $meldunek ) && ! empty( $meldunek ) );
ob_start();
sklepag_ostrzez_nowe_sekcje_szablonu();
$wyj_notice = (string) ob_get_clean();
$sprawdz( 'panel wymienia NAZWY sekcji, nie samo haslo o nowych sekcjach',
	false !== strpos( $wyj_notice, 'Procedura reklamacyjna' ) && false !== strpos( $wyj_notice, 'Cena' ),
	'komunikat: ' . substr( wp_strip_all_tags( $wyj_notice ), 0, 200 ) );
$sprawdz( 'komunikat mowi, ze tresci nie ruszamy', false !== strpos( $wyj_notice, 'nie ruszamy' ) );

echo "=== C5. POLITYKA: zdanie zmienione WEWNATRZ sekcji Zakres (F4-F2) dociera do starej instalacji ===\n";
$stare_zdanie = 'dziennik zdarzeń (90 dni, w tym adres IP przy każdym pobraniu pliku);';
$polityka_stara = "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nAdministrator danych: [NAZWA, KONTAKT]\nZakres: adres e-mail kupującego, dane transakcji, $stare_zdanie dobrowolnie podane dane do faktury.\nPodmioty przetwarzające: operator płatności Stripe; mechanizm poczty serwera.";
$id_pol_stara = wp_insert_post( array( 'post_title' => 'Polityka prywatności sklepu (SZABLON)', 'post_content' => $polityka_stara, 'post_status' => 'draft', 'post_type' => 'page' ) );
$id_pol_przed = (int) get_option( 'sklepag_strona_polityka', 0 );
update_option( 'sklepag_strona_polityka', $id_pol_stara, false );
delete_option( 'sklepag_strony_nowe_sekcje' );
delete_option( 'sklepag_wersja_stron' );
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_pol_stara );
$pol_po = get_post( $id_pol_stara );
$pol_tresc = $pol_po ? $pol_po->post_content : '';
$sprawdz( 'stare zdanie o 90 dniach ZNIKNELO', false === strpos( $pol_tresc, $stare_zdanie ), 'kompensacja widzi tylko nazwy sekcji, nie zmiany w ich tresci' );
$sprawdz( 'nowe zdanie (WARUNKOWE, WP-Cron) JEST', false !== strpos( $pol_tresc, 'WP-Cron' ) && false !== strpos( $pol_tresc, 'WARUNKOWE' ) );
$sprawdz( 'reszta linii Zakres nietknieta (dane do faktury zostaly)', false !== strpos( $pol_tresc, 'dobrowolnie podane dane do faktury.' ) );
$sprawdz( 'sekcja Zakres NIE zostala zdublowana', 1 === substr_count( $pol_tresc, 'Zakres:' ) );
echo "=== C5b. POLITYKA przepisana przez wlasciciela (bez naszego zdania) -> nietknieta + meldunek o zdaniu ===\n";
$polityka_wlasna = "Polityka prywatnosci Firmy Wlasnej\n\nAdministrator danych: Firma Wlasna.\nZakres: e-mail i dane zamowienia. Dziennik czyscimy sami.\nPodmioty przetwarzające: Stripe.";
$id_pol_wlasna = wp_insert_post( array( 'post_title' => 'Polityka prywatności', 'post_content' => $polityka_wlasna, 'post_status' => 'publish', 'post_type' => 'page' ) );
update_option( 'sklepag_strona_polityka', $id_pol_wlasna, false );
delete_option( 'sklepag_strony_nowe_sekcje' );
delete_option( 'sklepag_wersja_stron' );
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_pol_wlasna );
$pw = get_post( $id_pol_wlasna );
$sprawdz( 'wlasna polityka NIETKNIETA', $pw && $pw->post_content === $polityka_wlasna );
$meld_pol = get_option( 'sklepag_strony_nowe_sekcje', array() );
$sprawdz( 'meldunek wymienia zdanie o sprzataniu', is_array( $meld_pol ) && false !== strpos( wp_json_encode( $meld_pol ), 'sprz' ), 'meldunek: ' . substr( (string) wp_json_encode( $meld_pol ), 0, 200 ) );
echo "=== C5d. WLASNY dokument (bez SZABLON) z NASZYM starym zdaniem slowo w slowo -> NIETKNIETY + meldunek (r4 W1) ===\n";
$polityka_wlasna_stare = "Polityka prywatnosci Firmy Wlasnej\n\nAdministrator danych: Firma Wlasna.\nZakres: e-mail, dane zamowienia, " . $stare_zdanie . "\nPodmioty przetwarzajace: Stripe.\n";
$id_pol_wl2 = wp_insert_post( array( 'post_title' => 'Polityka prywatności', 'post_content' => $polityka_wlasna_stare, 'post_status' => 'publish', 'post_type' => 'page' ) );
update_option( 'sklepag_strona_polityka', $id_pol_wl2, false );
delete_option( 'sklepag_strony_nowe_sekcje' );
delete_option( 'sklepag_wersja_stron' );
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_pol_wl2 );
$pw2 = get_post( $id_pol_wl2 );
$sprawdz( 'wlasny dokument ze starym zdaniem NIETKNIETY (instrukcja: ani znaku)', $pw2 && $pw2->post_content === $polityka_wlasna_stare, 'kompensacja podmienila zdanie w cudzym dokumencie prawnym' );
$meld_wl2 = get_option( 'sklepag_strony_nowe_sekcje', array() );
$sprawdz( 'wlasny dokument dostaje MELDUNEK o zdaniu do przepisania', is_array( $meld_wl2 ) && false !== strpos( wp_json_encode( $meld_wl2 ), 'sprz' ), 'meldunek: ' . substr( (string) wp_json_encode( $meld_wl2 ), 0, 160 ) );
wp_delete_post( $id_pol_wl2, true );
echo "=== C6. ZNACZNIK PRZED PRACA: bieg ustawia znacznik zanim cokolwiek zapisze (rownolegle zadanie wraca na bramie) ===\n";
delete_option( 'sklepag_wersja_stron' );
$znacznik_w_trakcie = null;
$podgladacz = function ( $post_id ) use ( &$znacznik_w_trakcie ) {
	if ( null === $znacznik_w_trakcie ) { $znacznik_w_trakcie = (int) get_option( 'sklepag_wersja_stron', 0 ); }
};
add_action( 'save_post', $podgladacz );
update_option( 'sklepag_strona_polityka', $id_pol_stara, false );
wp_update_post( array( 'ID' => $id_pol_stara, 'post_content' => $polityka_stara ) ); // przywracamy stare zdanie, zeby kompensacja miala co zapisac
$znacznik_w_trakcie = null;
sklepag_kompensuj_strony_prawne();
remove_action( 'save_post', $podgladacz );
$sprawdz( 'w chwili PIERWSZEGO zapisu znacznik byl juz podbity', SKLEPAG_WERSJA_STRON === $znacznik_w_trakcie, 'znacznik w trakcie zapisu: ' . var_export( $znacznik_w_trakcie, true ) . ' (drugie rownolegle zadanie zalozyloby druga strone)' );
echo "=== C5c. SZABLON polityki z PRZEPISANYM zdaniem o sprzataniu -> nie podmieniamy, ale MELDUJEMY ===\n";
$polityka_szablon_przepisana = "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nAdministrator danych: [NAZWA, KONTAKT]\nZakres: e-mail, dane zamowienia; dziennik czyscimy raz na kwartal recznie.\nPodmioty przetwarzające: Stripe.";
$id_pol_sz = wp_insert_post( array( 'post_title' => 'Polityka prywatności sklepu (SZABLON)', 'post_content' => $polityka_szablon_przepisana, 'post_status' => 'draft', 'post_type' => 'page' ) );
update_option( 'sklepag_strona_polityka', $id_pol_sz, false );
delete_option( 'sklepag_strony_nowe_sekcje' );
delete_option( 'sklepag_wersja_stron' );
sklepag_kompensuj_strony_prawne();
clean_post_cache( $id_pol_sz );
$psz = get_post( $id_pol_sz );
$sprawdz( 'przepisane zdanie w SZABLONIE nietkniete', $psz && false !== strpos( $psz->post_content, 'raz na kwartal recznie' ) );
$meld_sz = get_option( 'sklepag_strony_nowe_sekcje', array() );
$sprawdz( 'SZABLON z przepisanym zdaniem dostaje MELDUNEK o zdaniu', is_array( $meld_sz ) && false !== strpos( wp_json_encode( $meld_sz ), 'sprz' ), 'meldunek: ' . substr( (string) wp_json_encode( $meld_sz ), 0, 200 ) );
wp_delete_post( $id_pol_sz, true );
echo "=== C5e/C5f. SZABLON polityki z HISTORYCZNA linia Zakres (0.9.8 / 0.9.0) -> cala linia podmieniona: faktura + opinie + WP-Cron (r4 W3) ===\n";
foreach ( array(
	'0.9.8 (opinie, bez faktury)'  => 'Zakres: adres e-mail kupującego, dane transakcji, dziennik zdarzeń (90 dni); przy dobrowolnej opinii — imię i treść opinii, publikowane na stronie produktu wyłącznie za wyrażoną w formularzu zgodą (usunięcie/anonimizację można wykonać na żądanie).',
	'0.9.0 (bez opinii i faktury)' => 'Zakres: adres e-mail kupującego, dane transakcji, dziennik zdarzeń (90 dni).',
) as $etykieta => $linia_hist ) {
	$pol_hist = "SZABLON — wypełnij danymi sprzedawcy przed publikacją.\n\nAdministrator danych: [NAZWA, KONTAKT]\n" . $linia_hist . "\nPodmioty przetwarzające: operator płatności Stripe; mechanizm poczty serwera.\n";
	$id_h     = wp_insert_post( array( 'post_title' => 'Polityka prywatności sklepu (SZABLON)', 'post_content' => $pol_hist, 'post_status' => 'draft', 'post_type' => 'page' ) );
	update_option( 'sklepag_strona_polityka', $id_h, false );
	delete_option( 'sklepag_strony_nowe_sekcje' );
	delete_option( 'sklepag_wersja_stron' );
	sklepag_kompensuj_strony_prawne();
	clean_post_cache( $id_h );
	$ph = get_post( $id_h );
	$th = $ph ? $ph->post_content : '';
	$sprawdz( "$etykieta: stara linia Zakres ZNIKNELA", false === strpos( $th, $linia_hist ) );
	$sprawdz( "$etykieta: nowa linia ma dane do faktury", false !== strpos( $th, 'dane do faktury (nazwa firmy, NIP' ) );
	$sprawdz( "$etykieta: nowa linia ma opinie", false !== strpos( $th, 'przy dobrowolnej opinii' ) );
	$sprawdz( "$etykieta: nowa linia ma sprzatanie warunkowe (WP-Cron)", false !== strpos( $th, 'WP-Cron' ) );
	$sprawdz( "$etykieta: sekcja Zakres NIE zdublowana", 1 === substr_count( $th, 'Zakres:' ), 'Zakres: x' . substr_count( $th, 'Zakres:' ) );
	wp_delete_post( $id_h, true );
}
echo "=== C8. AKTYWACJA na starej instalacji (strony zastane) NIE deklaruje szablonu za aktualny ===\n";
$id_zastany = wp_insert_post( array( 'post_title' => 'Regulamin sklepu (SZABLON)', 'post_content' => "SZABLON stary\nSprzedawca: [NAZWA]", 'post_status' => 'draft', 'post_type' => 'page' ) );
update_option( 'sklepag_strona_regulamin', $id_zastany, false );
update_option( 'sklepag_wersja_stron', SKLEPAG_WERSJA_STRON, true ); // stan po poprzedniej aktywacji
sklepag_utworz_strony();
$sprawdz( 'aktywacja ze strona zastana KASUJE znacznik (kompensacja obejrzy strony)', (int) get_option( 'sklepag_wersja_stron', 0 ) !== SKLEPAG_WERSJA_STRON, 'znacznik: ' . get_option( 'sklepag_wersja_stron', 'brak' ) . ' — deaktywuj/aktywuj zabijaloby kompensacje' );
$sprawdz( 'aktywacja nie zdublowala zastanej strony', $id_zastany === (int) get_option( 'sklepag_strona_regulamin', 0 ) );
wp_delete_post( $id_zastany, true );
delete_option( 'sklepag_strona_regulamin' );
$id_pol_na_bok = (int) get_option( 'sklepag_strona_polityka', 0 );
delete_option( 'sklepag_strona_polityka' ); // obie strony maja powstac z TEGO szablonu
sklepag_utworz_strony(); // swieze strony: znacznik ma byc ustawiony
$sprawdz( 'aktywacja na czystej instalacji USTAWIA znacznik', (int) get_option( 'sklepag_wersja_stron', 0 ) === SKLEPAG_WERSJA_STRON );
foreach ( array( 'sklepag_strona_regulamin', 'sklepag_strona_polityka' ) as $o_sw ) { $i_sw = (int) get_option( $o_sw, 0 ); if ( $i_sw ) { wp_delete_post( $i_sw, true ); } }
update_option( 'sklepag_strona_polityka', $id_pol_na_bok, false );
echo "=== C8b. AKTYWACJA: nieudane wp_insert_post NIE ustawia znacznika szablonu (r4 W4) ===\n";
$id_reg_na_bok2 = (int) get_option( 'sklepag_strona_regulamin', 0 );
$id_pol_na_bok2 = (int) get_option( 'sklepag_strona_polityka', 0 );
delete_option( 'sklepag_strona_regulamin' );
delete_option( 'sklepag_strona_polityka' );
delete_option( 'sklepag_wersja_stron' );
add_filter( 'wp_insert_post_empty_content', '__return_true' ); // WordPress uznaje tresc za pusta -> wp_insert_post oddaje 0 (bez $wp_error)
sklepag_utworz_strony();
remove_filter( 'wp_insert_post_empty_content', '__return_true' );
$sprawdz( 'przy nieudanym zalozeniu stron znacznik NIE jest ustawiony', (int) get_option( 'sklepag_wersja_stron', 0 ) !== SKLEPAG_WERSJA_STRON, 'znacznik: ' . var_export( get_option( 'sklepag_wersja_stron', null ), true ) );
$sprawdz( 'zadna strona prawna nie powstala (opcje puste)', 0 === (int) get_option( 'sklepag_strona_regulamin', 0 ) && 0 === (int) get_option( 'sklepag_strona_polityka', 0 ) );
foreach ( array( 'sklepag_strona_regulamin' => $id_reg_na_bok2, 'sklepag_strona_polityka' => $id_pol_na_bok2 ) as $o_b => $i_b ) {
	if ( $i_b ) { update_option( $o_b, $i_b, false ); } else { delete_option( $o_b ); }
}
echo "=== C8c. KOMPENSACJA: nieudane wp_insert_post w galezi (a) KASUJE znacznik, zeby nastepny bieg sprobowal znowu (#294 r7 W1) ===\n";
$id_reg_na_bok3 = (int) get_option( 'sklepag_strona_regulamin', 0 );
$id_pol_na_bok3 = (int) get_option( 'sklepag_strona_polityka', 0 );
$znacznik_na_bok3 = get_option( 'sklepag_wersja_stron', null );
delete_option( 'sklepag_strona_regulamin' );
delete_option( 'sklepag_strona_polityka' );
update_option( 'sklepag_wersja_stron', SKLEPAG_WERSJA_STRON - 1, true ); // stara instalacja: kompensacja MA wejsc
add_filter( 'wp_insert_post_empty_content', '__return_true' );
sklepag_kompensuj_strony_prawne();
remove_filter( 'wp_insert_post_empty_content', '__return_true' );
$sprawdz( 'kompensacja: po nieudanym zalozeniu strony znacznik NIE zostaje na biezacej wersji', (int) get_option( 'sklepag_wersja_stron', 0 ) !== SKLEPAG_WERSJA_STRON, 'znacznik: ' . var_export( get_option( 'sklepag_wersja_stron', null ), true ) );
$sprawdz( 'kompensacja: zadna strona nie powstala (opcje puste)', 0 === (int) get_option( 'sklepag_strona_regulamin', 0 ) && 0 === (int) get_option( 'sklepag_strona_polityka', 0 ) );
// kontrola dodatnia: ten sam bieg BEZ awarii zaklada strony i ustawia znacznik
sklepag_kompensuj_strony_prawne();
$id_reg_c8c = (int) get_option( 'sklepag_strona_regulamin', 0 );
$id_pol_c8c = (int) get_option( 'sklepag_strona_polityka', 0 );
$sprawdz( 'kompensacja: nastepny bieg zaklada obie strony i ustawia znacznik', $id_reg_c8c > 0 && $id_pol_c8c > 0 && (int) get_option( 'sklepag_wersja_stron', 0 ) === SKLEPAG_WERSJA_STRON );
foreach ( array( $id_reg_c8c, $id_pol_c8c ) as $i_c ) { if ( $i_c ) { wp_delete_post( $i_c, true ); } }
foreach ( array( 'sklepag_strona_regulamin' => $id_reg_na_bok3, 'sklepag_strona_polityka' => $id_pol_na_bok3 ) as $o_b => $i_b ) {
	if ( $i_b ) { update_option( $o_b, $i_b, false ); } else { delete_option( $o_b ); }
}
if ( null === $znacznik_na_bok3 ) { delete_option( 'sklepag_wersja_stron' ); } else { update_option( 'sklepag_wersja_stron', $znacznik_na_bok3, true ); }
echo "=== C7. KONTROLA PRZYRZADU: test biegnie pod WP-CLI (brame frontu mierzy spust zadaniem HTTP, nie ten test — r4 W2) ===\n";
$sprawdz( 'kontrola przyrzadu: test biegnie pod WP-CLI (dlatego C1-C6 mogly pracowac)', defined( 'WP_CLI' ) && WP_CLI );
update_option( 'sklepag_strona_polityka', $id_pol_przed, false );
wp_delete_post( $id_pol_stara, true );
wp_delete_post( $id_pol_wlasna, true );
echo "=== C4. kontrola dodatnia: gosc nie widzi ostrzezen panelu ===\n";
wp_set_current_user( 0 );
ob_start();
sklepag_ostrzez_nowe_sekcje_szablonu();
$wyj_gosc = (string) ob_get_clean();
$sprawdz( 'gosc nie dostaje komunikatu panelu', '' === trim( $wyj_gosc ) );
wp_set_current_user( 1 );

// --- sprzatanie ---------------------------------------------------------------
foreach ( array( $id_nowy, $id_szablon, $id_wlasny ) as $id ) {
	if ( $id && get_post( $id ) ) {
		wp_delete_post( $id, true );
	}
}
delete_option( 'sklepag_strony_nowe_sekcje' );

echo "\n";
if ( 0 === $bledy ) {
	echo "STRAZNIK LINK+BLOK+MIGRACJA: WYNIK=PASS (EXIT=0)\n";
	exit( 0 );
}
echo "STRAZNIK LINK+BLOK+MIGRACJA: $bledy NIE WYSZLO — WYNIK=FAIL (EXIT=1)\n";
exit( 1 );
