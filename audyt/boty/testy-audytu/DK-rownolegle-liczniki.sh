#!/usr/bin/env bash
# STRAŻNIK DK-5 (przyrząd ZAKTUALIZOWANY po naprawie C3, PR #162): równoległe
# zapisy licznika nie mogą ginąć.
#   HISTORIA: znalezisko DK-5 pokazało, że licznik w wp_options (chatl_blad_nonce,
#   get_option -> update_option) gubił 13 z 20 równoległych zapisów. Naprawa C3
#   USUNĘŁA licznik w opcjach — zdarzenia idą do tabeli chatl_zdarzenia
#   (INSERT = wiersz, nic nie ginie), a ścieżka złego nonce'a ma kubeł
#   `nonce-odrzucony` z progiem CHATL_LIMIT_NA_MIN zapisów/min (celowo:
#   ochrona bazy przed zalaniem; odpowiedź HTTP jest taka sama nad progiem).
#   - ścieżka badana: N żądań NARAZ ze złym noncem → w tabeli zdarzeń jest
#     DOKŁADNIE min(N, próg) wierszy `nonce`, a kubeł naliczył CAŁE N atomowo.
#     Zgubiona inkrementacja = wynik PONIŻEJ tych liczb.
#   - próba kontrolna: tabela chatl_rate_limit (INSERT ... ON DUPLICATE KEY UPDATE)
# EXIT 0 = liczniki co do sztuki, EXIT 1 = zgubione inkrementacje.
set -u
WP="docker exec strona_wp_wordpress wp"
URL="http://localhost:8890/wp-admin/admin-ajax.php"
N="${1:-20}"

PROG=$($WP eval 'echo (int) CHATL_LIMIT_NA_MIN;' --allow-root)
OCZEKIWANE=$(( N < PROG ? N : PROG ))

# Czyste pole: własne zdarzenia `nonce` i świeże okno kubła (jak sprzątanie pulapka-test).
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"nonce\"");' --allow-root
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE \"nonce-odrzucony|%\"");' --allow-root

echo "== SCIEZKA BADANA: $N zadan NARAZ ze zlym noncem (zdarzenia w TABELI, prog zapisu $PROG/min) =="
seq 1 "$N" | xargs -P "$N" -I{} curl -s -o /dev/null -X POST "$URL" \
  -d "action=chatl_pytanie&nonce=zepsuty{}&sesja=aaaaaaaa&pytanie=rownolegle{}"
PO=$($WP eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = \"nonce\"");' --allow-root)
KUBEL=$($WP eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COALESCE(SUM(licznik),0) FROM {$wpdb->prefix}chatl_rate_limit WHERE klucz LIKE \"nonce-odrzucony|%\"");' --allow-root)
echo "oczekiwane zdarzenia: $OCZEKIWANE (min z N=$N i progu $PROG)   zapisane w chatl_zdarzenia: $PO"
echo "oczekiwany kubel: $N   naliczone w chatl_rate_limit: $KUBEL"

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

# sprzatanie: wiersz probny kontrolnej + wlasne zdarzenia i kubel sciezki badanej
$WP eval "
global \$wpdb; \$t = \$wpdb->prefix . 'chatl_rate_limit';
\$wpdb->query( \$wpdb->prepare( \"DELETE FROM {\$t} WHERE klucz = %s\", '$KLUCZ' ) );
\$wpdb->query( \"DELETE FROM {\$t} WHERE klucz LIKE 'nonce-odrzucony|%'\" );
\$wpdb->query( \"DELETE FROM {\$wpdb->prefix}chatl_zdarzenia WHERE rodzaj = 'nonce'\" );
echo 'sprzatanie: zdarzenia nonce i kubly usuniete, wiersz probny usuniety';" --allow-root
echo ""

if [ "$PO" = "$OCZEKIWANE" ] && [ "$KUBEL" = "$N" ] && [ "$POT" = "$N" ]; then
  echo "WYNIK: OK — zdarzenia co do progu, kubel i tabela co do sztuki."; exit 0
fi
echo "WYNIK: PADA — zdarzenia: $PO/$OCZEKIWANE; kubel: $KUBEL/$N; tabela kontrolna zgubila $((N - POT))."
exit 1
