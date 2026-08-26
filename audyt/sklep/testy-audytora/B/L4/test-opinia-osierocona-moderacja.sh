#!/usr/bin/env bash
# Test NIE niszczy stanu produkcyjnego (dopisuje jeden testowy wiersz opinii z
# zamowienie_id wskazującym na NIEISTNIEJĄCE zamówienie — łatwo odwracalne):
#   DELETE FROM wp_sklepag_opinie WHERE zamowienie_id=999999;
#
# Pada NA KODZIE 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (includes/opinie.php:182-189):
#   $zanonimizowane = $wpdb->get_var($wpdb->prepare(
#     "SELECT z.usunieto_dnia FROM {$t} o JOIN {$pz} z ON z.id = o.zamowienie_id WHERE o.id = %d",
#     $opinia_id
#   ));
#   if ( $zanonimizowane ) { return false; }
#   ... UPDATE ... status=$nowy ...
#
# INNER JOIN na nieistniejące zamówienie zwraca ZERO WIERSZY -> get_var() zwraca NULL,
# DOKŁADNIE tak samo jak dla zamówienia ISTNIEJĄCEGO, ale NIEZANONIMIZOWANEGO
# (usunieto_dnia IS NULL). Funkcja nie potrafi odróżnić „zamówienie istnieje i jest
# w porządku" od „zamówienia w ogóle nie ma" — w obu przypadkach $zanonimizowane
# jest falsy i moderacja PRZECHODZI: opinia bez żadnego zweryfikowanego zamówienia
# zostaje opublikowana (status='przyjeta'), co łamie kontrakt „weryfikacja zakupu
# przed publikacją" (D32 / Omnibus, material/03-zalozenia.md).
set -euo pipefail
echo "=== 1) wstaw opinie-sierote (zamowienie_id=999999, ktorego NIE MA w wp_sklepag_zamowienia) ==="
docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -e "
DELETE FROM wp_sklepag_opinie WHERE zamowienie_id=999999;
INSERT INTO wp_sklepag_opinie (zamowienie_id, produkt_id, imie, gwiazdki, tytul, tresc, status, token_hash, utworzono, zmieniono)
VALUES (999999, 1, \"Osierocona\", 5, \"t\", \"tresc\", \"oczekuje\", \"h\", NOW(), NOW());
"'

echo "=== 2) wywolaj PRAWDZIWA funkcje produkcyjna sklepag_opinia_moderuj('przyjmij') ==="
docker exec -u www-data probny_wp wp --path=/var/www/html eval '
global $wpdb;
$id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=999999");
$wynik = sklepag_opinia_moderuj( $id, "przyjmij" );
$status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sklepag_opinie WHERE id=%d", $id));
echo "id=$id wynik_funkcji=" . var_export($wynik, true) . " status_po=$status\n";
if ( $status === "przyjeta" ) {
    echo "PADA: opinia bez zamowienia zostala OPUBLIKOWANA — moderacja nie odmowila.\n";
    exit(1);
}
echo "OK: moderacja odmowila (status != przyjeta).\n";
exit(0);
'
KOD=$?

echo "=== 3) sprzatanie testowego wiersza ==="
docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -e "DELETE FROM wp_sklepag_opinie WHERE zamowienie_id=999999;"'

exit $KOD
