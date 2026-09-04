<?php
/**
 * Strażnik: właściciel MUSI zobaczyć, że regulamin i polityka są niegotowe.
 *
 * Uruchomienie: przez spust `narzedzia-qa/strony-prawne-stos-probny.sh`, ktory wgrywa ten plik
 *               do kontenera probnego i wola go przez `wp eval-file`. Sciezki w kontenerze nie
 *               wpisujemy tu na sztywno — katalog `sklep-agentow-testy` NIE ISTNIEJE w repo
 *               (klasa 3: dokument mowi o sciezce, ktorej nie ma).
 * EXIT 0 = wszystkie przypadki wyszły · 1 = strażnik nie widzi którejś sytuacji · 2 = brak warunków.
 *
 * ⛔ WŁASNE WARUNKI WSTĘPNE, bo stos bywa WSPÓLNY z innymi biegami: test zakłada DWIE własne strony,
 * podmienia opcje `pre_option_*` FILTREM (zero zapisu do bazy opcji) i kasuje swoje strony na końcu.
 * Stron 120/121 ani opcji sklepu nie dotyka w żaden sposób.
 *
 * ⛔ LICZNIK BŁĘDÓW ŻYJE W TYM SAMYM ZASIĘGU CO ASERCJE. Pod `wp eval-file` licznik zamknięty
 * w funkcji potrafi nie dojechać do `exit` i strażnik melduje sukces mimo porażek (złapane 21.08).
 */

if ( ! function_exists( 'wp_insert_post' ) ) {
	fwrite( STDERR, "PROBA: brak WordPressa\n" );
	exit( 2 );
}
/**
 * ⛔ `includes/admin.php` jest wczytywany WYŁĄCZNIE za `is_admin()` (warunek `is_admin()` w `sklep-agentow.php`),
 * a `wp eval-file` biegnie z wiersza poleceń, więc panel się nie ładuje i funkcji nie ma.
 * Dociągam plik jawnie — ale przez to sam odczyt „funkcja istnieje" przestaje dowodzić, że jest
 * WPIĘTA. Dlatego zaraz niżej stoi OSOBNA asercja na `has_action`, a `require_once` wykonuje
 * `add_action` dokładnie tak, jak zrobiłby to panel.
 */
if ( ! function_exists( 'sklepag_ostrzez_strony_prawne' ) ) {
	if ( ! defined( 'SKLEPAG_KATALOG' ) || ! file_exists( SKLEPAG_KATALOG . 'includes/admin.php' ) ) {
		fwrite( STDERR, "PROBA: wtyczka sklep-agentow nie jest wgrana\n" );
		exit( 2 );
	}
	require_once SKLEPAG_KATALOG . 'includes/admin.php';
}
if ( ! function_exists( 'sklepag_ostrzez_strony_prawne' ) ) {
	fwrite( STDERR, "PROBA: brak sklepag_ostrzez_strony_prawne() nawet po wczytaniu panelu\n" );
	exit( 2 );
}

wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	fwrite( STDERR, "PROBA: uzytkownik 1 nie ma manage_options — nie ma czym zmierzyc widoku wlasciciela\n" );
	exit( 2 );
}

$bledy = 0;
$id_reg = 0;
$id_pol = 0;

/** Drukuje ostrzeżenie do napisu — funkcja pisze przez echo. */
function sklepag_test_zlap() {
	ob_start();
	sklepag_ostrzez_strony_prawne();
	return (string) ob_get_clean();
}

/** Ustawia, co zwrócą opcje stron, BEZ zapisu do bazy. */
$ustaw_opcje = function ( $reg, $pol ) {
	remove_all_filters( 'pre_option_sklepag_strona_regulamin' );
	remove_all_filters( 'pre_option_sklepag_strona_polityka' );
	add_filter( 'pre_option_sklepag_strona_regulamin', function () use ( $reg ) { return $reg; } );
	add_filter( 'pre_option_sklepag_strona_polityka', function () use ( $pol ) { return $pol; } );
};

$sprawdz = function ( $etykieta, $warunek, $szczegol = '' ) use ( &$bledy ) {
	if ( $warunek ) {
		printf( "  OK   %s\n", $etykieta );
		return;
	}
	$bledy++;
	printf( "  ZLE  %s%s\n", $etykieta, $szczegol ? ' — ' . $szczegol : '' );
};

$id_reg = wp_insert_post( array(
	'post_title'   => 'PROBA KONTROLNA — Regulamin sklepu (SZABLON)',
	'post_content' => "SZABLON — wypelnij danymi sprzedawcy przed publikacja.\n\nSprzedawca: [NAZWA, ADRES, KONTAKT]",
	'post_status'  => 'draft',
	'post_type'    => 'page',
) );
$id_pol = wp_insert_post( array(
	'post_title'   => 'PROBA KONTROLNA — Polityka prywatnosci sklepu (SZABLON)',
	'post_content' => "SZABLON — wypelnij danymi sprzedawcy przed publikacja.",
	'post_status'  => 'draft',
	'post_type'    => 'page',
) );
if ( ! $id_reg || ! $id_pol || is_wp_error( $id_reg ) || is_wp_error( $id_pol ) ) {
	fwrite( STDERR, "PROBA: nie udalo sie zalozyc wlasnych stron testowych\n" );
	exit( 2 );
}

echo "=== 0. ostrzezenie jest WPIETE w panel, a nie tylko zdefiniowane ===\n";
$sprawdz( 'sklepag_ostrzez_strony_prawne wisi na admin_notices',
	false !== has_action( 'admin_notices', 'sklepag_ostrzez_strony_prawne' ),
	'funkcja istnieje, ale nikt jej nie wola — wlasciciel nigdy jej nie zobaczy' );
$sprawdz( 'blizniak z tej samej rodziny tez wisi (kontrola dodatnia pomiaru)',
	false !== has_action( 'admin_notices', 'sklepag_ostrzez_permalinki' ),
	'has_action nie widzi NICZEGO — pomiar wyzej nic nie znaczy' );

echo "=== A. obie strony w wersji roboczej z SZABLONEM — ostrzezenie MUSI wymienic OBIE ===\n";
$ustaw_opcje( $id_reg, $id_pol );
$wyj = sklepag_test_zlap();
$sprawdz( 'ostrzezenie w ogole sie pokazalo', '' !== trim( $wyj ) );
$sprawdz( 'wymienia Regulamin', false !== strpos( $wyj, 'Regulamin sklepu' ) );
$sprawdz( 'wymienia Polityke', false !== strpos( $wyj, 'Polityka prywatności sklepu' ) );
$sprawdz( 'jest bledem, nie zolta podpowiedzia', false !== strpos( $wyj, 'notice-error' ) );
$sprawdz( 'nie da sie go zamknac (brak is-dismissible)', false === strpos( $wyj, 'is-dismissible' ) );
// KOMUNIKAT MUSI NIESC POWOD ZMIERZONY DLA KAZDEJ STRONY OSOBNO.
// Warunek w admin.php jest ALTERNATYWA trzech przeslanek (nieopublikowana ALBO zaslepka w tytule
// ALBO w tresci). Do rundy 2 komunikat mowil jednym zdaniem o wersji roboczej takze o stronie
// OPUBLIKOWANEJ i kazal ja opublikowac — opis stanu, w ktorym ona nie jest (klasa 3).
$sprawdz( 'przy stronie roboczej powod brzmi NIEOPUBLIKOWANA', false !== strpos( $wyj, 'nieopublikowana' ),
	'brak zmierzonego powodu przy stronie w wersji roboczej' );
$sprawdz( 'i NIE mowi o zaslepce, ktorej tu nie bylo', false === strpos( $wyj, 'zaślepką' ) );

echo "=== B. obie gotowe: opublikowane, bez slowa SZABLON — ostrzezenia NIE MA ===\n";
wp_update_post( array( 'ID' => $id_reg, 'post_status' => 'publish', 'post_title' => 'Regulamin sklepu', 'post_content' => 'Sprzedawca: Firma sp. z o.o., ul. Prawdziwa 1.' ) );
wp_update_post( array( 'ID' => $id_pol, 'post_status' => 'publish', 'post_title' => 'Polityka prywatnosci sklepu', 'post_content' => 'Administrator danych: Firma sp. z o.o.' ) );
clean_post_cache( $id_reg );
clean_post_cache( $id_pol );
$wyj = sklepag_test_zlap();
$sprawdz( 'cisza, gdy obie strony sa gotowe', '' === trim( $wyj ), 'ostrzezenie krzyczy na gotowe dokumenty: ' . substr( wp_strip_all_tags( $wyj ), 0, 80 ) );

echo "=== C. OPUBLIKOWANA, ale w tresci nadal SZABLON — to NIE jest gotowe ===\n";
wp_update_post( array( 'ID' => $id_reg, 'post_content' => "SZABLON — wypelnij danymi sprzedawcy.\n\nSprzedawca: [NAZWA, ADRES, KONTAKT]" ) );
clean_post_cache( $id_reg );
$wyj = sklepag_test_zlap();
$sprawdz( 'lapie zaslepke w tresci opublikowanej strony', false !== strpos( $wyj, 'Regulamin sklepu' ),
	'opublikowanie z niewypelnionym szablonem uchodzi za gotowe' );
$sprawdz( 'i NIE oskarza drugiej, ktora jest gotowa', false === strpos( $wyj, 'Polityka prywatności sklepu' ) );
$sprawdz( 'powod mowi o ZASLEPCE, nie o braku publikacji', false !== strpos( $wyj, 'zaślepką' ),
	'strona OPUBLIKOWANA dostaje powod, ktorego nie ma: ' . substr( wp_strip_all_tags( $wyj ), 0, 120 ) );
$sprawdz( 'i NIE nazywa opublikowanej strony nieopublikowana', false === strpos( $wyj, 'nieopublikowana' ),
	'komunikat opisuje stan, w ktorym strona NIE JEST — wlasciciel przestaje wierzyc ostrzezeniu' );
// ⛔ Do rundy 3 ta asercja szukala frazy `opublikuj obie strony`, ktorej kod juz NIE MA (runda 2
// przepisala ja na `opublikuj te strony`) — wiec warunek byl zawsze prawdziwy i NIE MOGL PASC.
// Teraz mierzy to, co ma mierzyc: gdy WSZYSTKIE strony sa opublikowane, komunikat nie moze kazac ich publikowac.
$sprawdz( 'i nie kaze publikowac czegos, co juz jest opublikowane', false === strpos( $wyj, 'opublikuj t' ),
	'komunikat kaze opublikowac strone, ktora sam nazywa opublikowana: ' . substr( wp_strip_all_tags( $wyj ), 0, 140 ) );
$sprawdz( 'i mowi, ze zmiana zadziala od razu', false !== strpos( $wyj, 'już opublikowana' ) || false !== strpos( $wyj, 'już opublikowane' ) );

echo "=== C2. STAN MIESZANY: jedna ROBOCZA, druga OPUBLIKOWANA z zaslepka ===\n";
// ⛔ To NIE jest droga egzotyczna, tylko najbardziej prawdopodobna: `sklepag_utworz_strony()` w `aktywacja.php`
//    zaklada obie strony jako `draft` z „(SZABLON)” w TYTULE, wiec wlasciciel idzie strona po
//    stronie i miedzy jedna a druga JEST dokladnie w tym stanie.
//    Do rundy 6 przelacznikiem zdania koncowego byla ALTERNATYWA po calej liscie (`in_array`),
//    wiec obecnosc JEDNEJ strony roboczej kazala „opublikowac te strony” takze o tej, ktora
//    nawias obok nazywal „opublikowana”. Sprzecznosc w jednym akapicie (recenzja #278, WAZNA 1).
wp_update_post( array( 'ID' => $id_reg, 'post_status' => 'publish', 'post_title' => 'Regulamin sklepu (SZABLON)', 'post_content' => 'Sprzedawca: Firma sp. z o.o.' ) );
wp_update_post( array( 'ID' => $id_pol, 'post_status' => 'draft', 'post_title' => 'Polityka prywatnosci sklepu', 'post_content' => 'Administrator danych: Firma sp. z o.o.' ) );
clean_post_cache( $id_reg );
clean_post_cache( $id_pol );
$wyj = sklepag_test_zlap();
$sprawdz( 'MIESZANY: obie strony sa na liscie', false !== strpos( $wyj, 'Regulamin sklepu' ) && false !== strpos( $wyj, 'Polityka prywatności sklepu' ),
	'stan mieszany gubi jedna ze stron' );
$sprawdz( 'MIESZANY: oba powody sa nazwane osobno', false !== strpos( $wyj, 'zaślepką' ) && false !== strpos( $wyj, 'nieopublikowana' ),
	'stan mieszany opisuje obie strony jednym powodem' );
$sprawdz( 'MIESZANY: polecenie publikacji dotyczy TYLKO strony roboczej', false !== strpos( $wyj, 'jeszcze robocza' ),
	'komunikat kaze opublikowac takze strone, ktora sam nazywa opublikowana — sprzecznosc w jednym akapicie: '
		. substr( wp_strip_all_tags( $wyj ), 0, 200 ) );
$sprawdz( 'MIESZANY: nie mowi o wszystkich stronach naraz', false === strpos( $wyj, 'opublikuj te strony, zanim' ),
	'zdanie koncowe wrocilo do formy obejmujacej strone juz opublikowana' );

echo "=== D. opcji nie ma (wlasciciel skasowal strone) — nie pouczamy ===\n";
$ustaw_opcje( 0, 0 );
$wyj = sklepag_test_zlap();
$sprawdz( 'cisza przy braku stron', '' === trim( $wyj ) );

echo "=== E. wpis w koszu — nie pouczamy ===\n";
$ustaw_opcje( $id_reg, 0 );
wp_trash_post( $id_reg );
clean_post_cache( $id_reg );
$wyj = sklepag_test_zlap();
$sprawdz( 'cisza przy stronie w koszu', '' === trim( $wyj ) );
wp_untrash_post( $id_reg );

echo "=== F. kontrola dodatnia samego pomiaru: bez uprawnien ostrzezenia byc nie moze ===\n";
$ustaw_opcje( $id_reg, $id_pol );
wp_set_current_user( 0 );
$wyj = sklepag_test_zlap();
$sprawdz( 'gosc nie widzi ostrzezen panelu', '' === trim( $wyj ) );
wp_set_current_user( 1 );

// --- sprzatanie: wlasne strony znikaja na twardo -----------------------------
wp_delete_post( $id_reg, true );
wp_delete_post( $id_pol, true );
$zostalo = 0;
foreach ( array( $id_reg, $id_pol ) as $id ) {
	if ( get_post( $id ) ) {
		$zostalo++;
	}
}
$sprawdz( 'po sobie posprzatane (wlasne strony skasowane)', 0 === $zostalo, "zostalo $zostalo stron testowych" );

echo "\n";
if ( 0 === $bledy ) {
	echo "STRAZNIK STRON PRAWNYCH: WYNIK=PASS (EXIT=0)\n";
	exit( 0 );
}
echo "STRAZNIK STRON PRAWNYCH: $bledy NIE WYSZLO — WYNIK=FAIL (EXIT=1)\n";
exit( 1 );
