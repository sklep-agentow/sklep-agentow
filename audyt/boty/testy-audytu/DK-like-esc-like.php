<?php
/**
 * STRAŻNIK DK-2: każdy wzorzec LIKE użyty do sprzątania po wtyczce ma
 * eskejpować `_` i `%`, inaczej skasuje CUDZE wpisy w wp_options.
 *
 * Uruchomienie:
 *   docker cp ... && docker exec strona_wp_wordpress wp eval-file /tmp/DK-like-esc-like.php --allow-root
 * EXIT 0 = czysto, EXIT 1 = wzorzec zjada cudze wpisy.
 *
 * Test NICZEGO nie odinstalowuje — bierze SAME zapytania DELETE z plików
 * uninstall.php i puszcza je na podłożonych wpisach próbnych, które kasuje
 * po sobie w każdym przypadku.
 */
global $wpdb;

$rodziny = array(
	// [ przedrostek wtyczki, wpis WŁASNY (ma zniknąć), wpis OBCY (ma przeżyć) ]
	array( '_transient_chatl_rl_',   '_transient_chatl_rl_DKproba',   '_transient_chatlXrlYDKobca' ),
	array( '_transient_chatl_import_', '_transient_chatl_import_DKproba', '_transient_chatlXimportYDKobca' ),
	array( '_transient_segm_komunikat_', '_transient_segm_komunikat_DKproba', '_transient_segmXkomunikatYDKobca' ),
	array( 'sklepag_',               'sklepag_DKproba',               'sklepagro_DKobca' ),
);

$bledy = 0;

function dk_podloz( $nazwa ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $nazwa ) );
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $nazwa, 'DK' ) );
}
function dk_jest( $nazwa ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $nazwa ) ) > 0;
}
function dk_sprzataj( $nazwy ) {
	global $wpdb;
	foreach ( $nazwy as $n ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $n ) );
	}
}

foreach ( $rodziny as $r ) {
	list( $przedrostek, $wlasny, $obcy ) = $r;

	// --- A. wzorzec TAKI, JAKI JEST W KODZIE (z esc_like) ---
	dk_podloz( $wlasny );
	dk_podloz( $obcy );
	$wzor = $wpdb->esc_like( $przedrostek ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wzor ) );
	$wlasny_znikl = ! dk_jest( $wlasny );
	$obcy_przezyl = dk_jest( $obcy );
	dk_sprzataj( array( $wlasny, $obcy ) );

	// --- B. ten sam wzorzec BEZ esc_like (kalibracja: tak wygląda wada) ---
	dk_podloz( $obcy );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $przedrostek . '%' ) );
	$obcy_zjadany_bez_esc = ! dk_jest( $obcy );
	dk_sprzataj( array( $obcy ) );

	$ok = $wlasny_znikl && $obcy_przezyl;
	if ( ! $ok ) {
		++$bledy;
	}
	printf(
		"%s wzorzec %-34s | wlasny skasowany: %-3s | OBCY (%s) przezyl: %-3s | bez esc_like obcy ginie: %s\n",
		$ok ? 'OK  ' : 'PADA',
		$przedrostek . '%',
		$wlasny_znikl ? 'tak' : 'NIE',
		$obcy,
		$obcy_przezyl ? 'tak' : 'NIE',
		$obcy_zjadany_bez_esc ? 'tak (test rozroznia oba stany)' : 'nie (TEST NIESKALIBROWANY)'
	);
}

echo $bledy ? "\nWYNIK: PADA — {$bledy} wzorzec/wzorce zjadaja cudze wpisy.\n" : "\nWYNIK: OK — kazdy wzorzec tyka wylacznie wlasnych wpisow.\n";
exit( $bledy ? 1 : 0 );
