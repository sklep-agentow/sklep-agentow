#!/usr/bin/env bash
# ⛔ TEST NISZCZĄCY STOS: robi PEŁNĄ deinstalację wtyczki (DROP TABLE ×7, kasuje strony,
#    kasuje wszystkie opcje sklepag_*) na kontenerze probny_wp / bazie wp_probny (:8891).
#    PRZYWRÓCENIE PO TEŚCIE:
#      docker exec -i strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny' \
#      bash narzedzia-qa/k7-stos-rundy.sh boty/paczki/sklep-agentow-0.9.13.zip   (z katalogu repo; stawia probny_wp od nowa)
#      + dane: sed 's/strona_wp_wordpress/probny_wp/g' testy/seed-demo.sh | bash -s 24   (recenzja #195 D4)
#      docker exec -u www-data probny_wp wp --path=/var/www/html plugin install /tmp/paczka.zip --force --activate
#
# Pada NA KODZIE 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (uninstall.php:70):
#   $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
#     $wpdb->esc_like( 'sklepag_' ) . '%' ) );
# Ta klauzula dopasowuje TYLKO nazwy zaczynające się od `sklepag_`. WP Transients API
# (set_transient) zapisuje jednak opcję pod nazwą `_transient_<klucz>` +
# `_transient_timeout_<klucz>` — prefiks `_transient_` idzie PRZED `sklepag_`, więc
# transient utworzony w includes/zakup.php:91,116
# ($okno = 'sklepag_idem_' . md5($ip.'|'.$produkt_id); set_transient($okno, ..., 60);)
# NIGDY nie zostanie złapany przez ten DELETE — niezależnie od stanu przełącznika
# sklepag_usun_dane_przy_odinstalowaniu.
#
# Test WYWOŁUJE prawdziwy set_transient() z DOKŁADNIE tym samym wzorcem nazwy co
# zakup.php, a potem prawdziwy uninstall.php przez `wp plugin uninstall`.
set -euo pipefail
echo "=== 1) wlacz pelne czyszczenie ==="
docker exec -u www-data probny_wp wp --path=/var/www/html option update sklepag_usun_dane_przy_odinstalowaniu 1

echo "=== 2) utworz transient DOKLADNIE wg wzorca zakup.php:91,116 ==="
docker exec -u www-data probny_wp wp --path=/var/www/html eval '
$okno = "sklepag_idem_" . md5("203.0.113.9|1");
set_transient($okno, array("url"=>"https://checkout.stripe.com/test/sesja123"), 60);
echo "klucz: ".$okno."\n";
'

echo "=== 3) PRZED uninstall: wiersz w wp_options ==="
docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wp_probny -e "SELECT option_name FROM wp_options WHERE option_name LIKE \"%sklepag_idem%\""'

echo "=== 4) deinstalacja pelna (deactivate + uninstall) ==="
docker exec -u www-data probny_wp wp --path=/var/www/html plugin deactivate sklep-agentow
docker exec -u www-data probny_wp wp --path=/var/www/html plugin uninstall sklep-agentow || true

echo "=== 5) PO uninstall: czy wiersz PRZEZYL? (test PADA jesli cokolwiek zwroci) ==="
WYNIK=$(docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -N wp_probny -e "SELECT COUNT(*) FROM wp_options WHERE option_name LIKE \"%sklepag_idem%\""')
echo "liczba przezywajacych wierszy: $WYNIK"
if [ "$WYNIK" -gt 0 ]; then
  echo "PADA: pelne czyszczenie (przelacznik ON) nie usunelo transientu sklepag_idem_* — BLAD POTWIERDZONY."
  exit 1
else
  echo "OK: transient zostal usuniety (blad naprawiony)."
  exit 0
fi
