#!/usr/bin/env bash
# STRAŻNIK DK-5: licznik, do którego pisze N żądań NARAZ, ma po nich pokazać N.
#   - ścieżka badana: chatl_blad_nonce (opcja, get_option -> update_option)
#   - próba kontrolna: tabela chatl_rate_limit (INSERT ... ON DUPLICATE KEY UPDATE)
# EXIT 0 = oba liczniki się zgadzają, EXIT 1 = zgubione inkrementacje.
set -u
WP="docker exec strona_wp_wordpress wp"
URL="http://localhost:8890/wp-admin/admin-ajax.php"
N="${1:-20}"

KOPIA=$($WP eval 'echo json_encode( get_option("chatl_blad_nonce") );' --allow-root)
$WP eval 'delete_option("chatl_blad_nonce");' --allow-root

echo "== SCIEZKA BADANA: $N zadan NARAZ ze zlym noncem (licznik w wp_options) =="
seq 1 "$N" | xargs -P "$N" -I{} curl -s -o /dev/null -X POST "$URL" \
  -d "action=chatl_pytanie&nonce=zepsuty{}&sesja=aaaaaaaa&pytanie=rownolegle{}"
PO=$($WP eval 'echo (int) ((array) get_option("chatl_blad_nonce"))["ile"];' --allow-root)
echo "oczekiwane: $N   zapisane w chatl_blad_nonce.ile: $PO"

echo ""
echo "== PROBA KONTROLNA: $N zadan NARAZ do licznika w TABELI (chatl_rate_limit) =="
KLUCZ=$($WP eval 'echo "dkproba" . time();' --allow-root)
$WP eval "
global \$wpdb; \$t = \$wpdb->prefix . 'chatl_rate_limit';
\$wpdb->query( \$wpdb->prepare( \"DELETE FROM {\$t} WHERE klucz = %s\", '$KLUCZ' ) );" --allow-root
seq 1 "$N" | xargs -P "$N" -I{} docker exec strona_wp_wordpress wp eval "
global \$wpdb; \$t = \$wpdb->prefix . 'chatl_rate_limit';
\$wpdb->query( \$wpdb->prepare( \"INSERT INTO {\$t} (klucz, licznik, okno_do) VALUES (%s, 1, %s)
  ON DUPLICATE KEY UPDATE licznik = LAST_INSERT_ID(licznik + 1)\", '$KLUCZ', gmdate('Y-m-d H:i:s', time()+120) ) );" --allow-root
POT=$($WP eval "
global \$wpdb; \$t = \$wpdb->prefix . 'chatl_rate_limit';
echo (int) \$wpdb->get_var( \$wpdb->prepare( \"SELECT licznik FROM {\$t} WHERE klucz = %s\", '$KLUCZ' ) );" --allow-root)
echo "oczekiwane: $N   zapisane w chatl_rate_limit.licznik: $POT"

# sprzatanie
$WP eval "
global \$wpdb; \$t = \$wpdb->prefix . 'chatl_rate_limit';
\$wpdb->query( \$wpdb->prepare( \"DELETE FROM {\$t} WHERE klucz = %s\", '$KLUCZ' ) );
if ( '$KOPIA' === 'false' ) { delete_option('chatl_blad_nonce'); }
else { update_option('chatl_blad_nonce', json_decode('$KOPIA', true), false); }
echo 'sprzatanie: chatl_blad_nonce=' . json_encode(get_option('chatl_blad_nonce')) . ' wiersz probny usuniety';" --allow-root
echo ""

if [ "$PO" = "$N" ] && [ "$POT" = "$N" ]; then
  echo "WYNIK: OK — oba liczniki zgadzaja sie z liczba zadan."; exit 0
fi
echo "WYNIK: PADA — opcja zgubila $((N - PO)) z $N inkrementacji; tabela zgubila $((N - POT))."
exit 1
