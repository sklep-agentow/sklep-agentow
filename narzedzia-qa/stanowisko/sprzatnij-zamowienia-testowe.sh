#!/bin/bash
# sprzatnij-zamowienia-testowe.sh — usuwa ze STANOWISKA WARSZTATOWEGO zamówienia zostawione przez testy
# (znalezisko I-22: nasz własny test produkował fałszywy przychód na Pulpicie — sześć zamówień po 149,00 PLN
# ze statusem mail_wyslany liczyło się do przychodu, który ogląda właściciel).
#
# Kogo uznajemy za testowego: e-mail kupującego z domeny `test.local` albo `example.com`, albo PUSTY
# (osierocony wiersz). To są NASZE adresy z testów (kupujacy-e2e, kupujacy-osadzony, audyt-*, opinie-test);
# prawdziwy kupujący nigdy nie ma takiej domeny (RFC 2606 / RFC 6761 — zarezerwowane do testów).
#
# Użycie: bash narzedzia-qa/stanowisko/sprzatnij-zamowienia-testowe.sh            # tylko LISTA (nic nie kasuje)
#         bash narzedzia-qa/stanowisko/sprzatnij-zamowienia-testowe.sh --kasuj    # kasuje, po ZRZUCIE do pliku
#         KONTENER_WP=inny bash …                                                  # domyślnie strona_wp_wordpress
# ⛔ Zawsze najpierw zrzut wierszy do /tmp/zamowienia-testowe-<data>.json — kasowanie jest nieodwracalne.
# Kasuje razem: zdarzenia (zamowienie_id), opinie (zamowienie_id), sesje_checkout (stripe_session_id) — tak jak
# sprzątanie w testach golden. Wychodzi kodem 2, gdy kontener nie odpowiada; 0 gdy zrobione albo nic do zrobienia.
set -u
KONTENER="${KONTENER_WP:-strona_wp_wordpress}"
TRYB="${1:-lista}"
WP="docker exec $KONTENER wp --allow-root --path=/var/www/html"
if ! docker ps --format '{{.Names}}' | grep -qx "$KONTENER"; then echo "SPRZATNIJ: kontener $KONTENER nie chodzi — nic nie zmierzono" >&2; exit 2; fi
# SQL w apostrofach: warunek wchodzi do PHP-owego stringu w cudzyslowach (pierwsza wersja z cudzyslowami w SQL
# rozrywala string PHP i dawala FALSZYWE ZERO — „0 zamowien” przy 20 na stanowisku; zlapane proba na zywo).
WARUNEK="email_kupujacego LIKE '%@test.local' OR email_kupujacego LIKE '%@example.com' OR email_kupujacego = ''"

LISTA=$($WP eval "global \$wpdb; \$p=\$wpdb->prefix.'sklepag_zamowienia'; foreach (\$wpdb->get_results(\"SELECT id, email_kupujacego e, kwota_grosze k, status s, utworzono u FROM \$p WHERE $WARUNEK ORDER BY id\") as \$z) printf(\"%s|%s|%s|%s|%s\n\", \$z->id, \$z->e, \$z->k, \$z->s, \$z->u);" 2>/dev/null)
ILE=$(printf '%s' "$LISTA" | grep -c .)
echo "SPRZATNIJ: zamowien testowych na $KONTENER: $ILE"
printf '%s\n' "$LISTA" | sed 's/^/  /' | head -40
[ "$ILE" -gt 0 ] || { echo "SPRZATNIJ: nic do zrobienia"; exit 0; }
[ "$TRYB" = "--kasuj" ] || { echo "SPRZATNIJ: tryb LISTA — dodaj --kasuj, zeby usunac (po zrzucie)"; exit 0; }

ZRZUT="/tmp/zamowienia-testowe-$(date +%F-%H%M%S).json"
$WP eval "global \$wpdb; \$p=\$wpdb->prefix.'sklepag_zamowienia'; echo wp_json_encode(\$wpdb->get_results(\"SELECT * FROM \$p WHERE $WARUNEK\", ARRAY_A));" > "$ZRZUT" 2>/dev/null
if ! [ -s "$ZRZUT" ] || ! grep -q '"id"' "$ZRZUT"; then echo "SPRZATNIJ: zrzut $ZRZUT pusty — NIE kasuje" >&2; exit 2; fi
echo "SPRZATNIJ: zrzut $ZRZUT ($(wc -c < "$ZRZUT") B)"

USUNIETE=$($WP eval "global \$wpdb; \$pz=\$wpdb->prefix.'sklepag_zamowienia'; \$pd=\$wpdb->prefix.'sklepag_zdarzenia'; \$po=\$wpdb->prefix.'sklepag_opinie'; \$ps=\$wpdb->prefix.'sklepag_sesje_checkout'; \$n=0; foreach (\$wpdb->get_results(\"SELECT id, stripe_session_id sid FROM \$pz WHERE $WARUNEK\") as \$z) { \$wpdb->delete(\$pd, array('zamowienie_id'=>(int)\$z->id)); \$wpdb->delete(\$po, array('zamowienie_id'=>(int)\$z->id)); if (\$z->sid) { \$wpdb->delete(\$ps, array('stripe_session_id'=>\$z->sid)); } \$n += (int)\$wpdb->delete(\$pz, array('id'=>(int)\$z->id)); } echo \$n;" 2>/dev/null)
ZOSTALO=$($WP eval "global \$wpdb; \$p=\$wpdb->prefix.'sklepag_zamowienia'; echo (int)\$wpdb->get_var(\"SELECT COUNT(*) FROM \$p WHERE $WARUNEK\");" 2>/dev/null)
echo "SPRZATNIJ: usunieto $USUNIETE zamowien (ze zdarzeniami, opiniami, sesjami); zostalo testowych: $ZOSTALO"
[ "${ZOSTALO:-1}" -eq 0 ] && exit 0
echo "SPRZATNIJ: cos zostalo — sprawdz recznie" >&2; exit 1
