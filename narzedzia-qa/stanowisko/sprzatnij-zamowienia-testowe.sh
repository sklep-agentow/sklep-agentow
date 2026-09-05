#!/bin/bash
# sprzatnij-zamowienia-testowe.sh — usuwa ze STANOWISKA WARSZTATOWEGO zamówienia zostawione przez testy
# (znalezisko I-22: nasz własny test produkował fałszywy przychód na Pulpicie — sześć zamówień po 149,00 PLN
# ze statusem mail_wyslany liczyło się do przychodu, który ogląda właściciel).
#
# Kogo uznajemy za testowego — DWA warunki naraz: e-mail kupującego z domeny `test.local` albo `example.com`
# ORAZ sesja Stripe `cs_test_…`. ⛔ PUSTY e-mail NIGDY nie kwalifikuje do skasowania: zakup.php (domknięcie sesji)
# zapisuje pusty e-mail, gdy Stripe nie odda adresu, i taki wiersz przechodzi na `oplacone` — to normalne
# zamówienie, nie sierota (#296 r1 WAŻNA 1). A na tym stanowisku `cs_test_` ma KAŻDE zamówienie (Stripe w trybie
# testowym, także zakupy przechodzące przez prawdziwe przekierowanie Stripe), więc `cs_test_` nie odróżnia
# „naszych testów” od „ręcznych zakupów właściciela” (#296 r2 WAŻNA 1) — odróżnia je wyłącznie DOMENA e-maila.
# Wiersze z pustym e-mailem są WYPISYWANE osobno do ręcznej oceny, bez kasowania.
# Zrzut z 5.09 sprawdzony po r1: 20 skasowanych wierszy, 2 z pustym e-mailem, wszystkie 20 z `cs_test_`.
# Domeny: `example.com` rezerwuje RFC 2606; `test.local` to NASZA konwencja z testów (kupujacy-e2e, kupujacy-osadzony,
# audyt-*, opinie-test), nie rezerwacja z RFC.
#
# Użycie: bash narzedzia-qa/stanowisko/sprzatnij-zamowienia-testowe.sh            # tylko LISTA (nic nie kasuje)
#         bash narzedzia-qa/stanowisko/sprzatnij-zamowienia-testowe.sh --kasuj    # kasuje, po ZRZUCIE do pliku
#         KONTENER_WP=inny bash …                                                  # domyślnie strona_wp_wordpress
# ⛔ Zawsze najpierw zrzut wierszy do /tmp/zamowienia-testowe-<data>.json — kasowanie jest nieodwracalne.
# Kasuje razem: zdarzenia (zamowienie_id), opinie (zamowienie_id), sesje_checkout (stripe_session_id) — tak jak
# sprzątanie w testach golden. Kody wyjścia: 2 — kontener nie odpowiada, pomiar nie dał liczby albo zrzut pusty
# („nie zmierzyłam”, nic nie skasowane); 1 — po kasowaniu coś zostało (sprawdź ręcznie); 0 — zrobione albo nic do zrobienia.
# ⛔ Zrzut leży w /tmp i znika z restartem maszyny — skopiuj go, jeśli ma być dowodem (5.09: /root/.claude/jobs/…).
set -u
KONTENER="${KONTENER_WP:-strona_wp_wordpress}"
TRYB="${1:-lista}"
WP="docker exec $KONTENER wp --allow-root --path=/var/www/html"
if ! docker ps --format '{{.Names}}' | grep -qx "$KONTENER"; then echo "SPRZATNIJ: kontener $KONTENER nie chodzi — nic nie zmierzono" >&2; exit 2; fi
# SQL w apostrofach: warunek wchodzi do PHP-owego stringu w cudzyslowach (pierwsza wersja z cudzyslowami w SQL
# rozrywala string PHP i dawala FALSZYWE ZERO — „0 zamowien” przy 20 na stanowisku; zlapane proba na zywo).
# `\\_` → w SQL `\_` = dosłowny podkreślnik (klasa 2 ze ściągi: goły `_` w LIKE to „dowolny znak”).
WARUNEK="(email_kupujacego LIKE '%@test.local' OR email_kupujacego LIKE '%@example.com') AND stripe_session_id LIKE 'cs\\_test\\_%'"

# KONTROLA DODATNIA (#296 r1 WAŻNA 2): zanim uwierzymy w „0 testowych”, pomiar musi oddać LICZBĘ wszystkich zamówień.
# Bez tego każda awaria pomiaru (fatal PHP, brak tabeli, inny prefiks) wyglądała jak „nic do zrobienia”.
RAZEM=$($WP eval "global \$wpdb; \$p=\$wpdb->prefix.'sklepag_zamowienia'; echo (int)\$wpdb->get_var(\"SELECT COUNT(*) FROM \$p\");" 2>&1 | tail -1)
case "$RAZEM" in ''|*[!0-9]*) echo "SPRZATNIJ: pomiar nie dal liczby (odpowiedz: ${RAZEM:-pusta}) — nic nie zmierzono" >&2; exit 2 ;; esac
LISTA=$($WP eval "global \$wpdb; \$p=\$wpdb->prefix.'sklepag_zamowienia'; foreach (\$wpdb->get_results(\"SELECT id, email_kupujacego e, kwota_grosze k, status s, utworzono u FROM \$p WHERE $WARUNEK ORDER BY id\") as \$z) printf(\"%s|%s|%s|%s|%s\n\", \$z->id, \$z->e, \$z->k, \$z->s, \$z->u);" 2>/dev/null) || { echo "SPRZATNIJ: zapytanie o testowe padlo — nic nie zmierzono" >&2; exit 2; }
if printf '%s' "$LISTA" | grep -q . && printf '%s\n' "$LISTA" | grep -qvE '^[0-9]+\|'; then echo "SPRZATNIJ: odpowiedz nie jest lista wierszy: $(printf '%s' "$LISTA" | head -2)" >&2; exit 2; fi
ILE=$(printf '%s' "$LISTA" | grep -c .)
OSIEROCONE=$($WP eval "global \$wpdb; \$p=\$wpdb->prefix.'sklepag_zamowienia'; echo (int)\$wpdb->get_var(\"SELECT COUNT(*) FROM \$p WHERE email_kupujacego = ''\");" 2>/dev/null | tail -1)
echo "SPRZATNIJ: zamowien razem na $KONTENER: $RAZEM, testowych (domena test.local/example.com ORAZ cs_test_): $ILE, z pustym e-mailem (NIE kasowane, do recznej oceny): ${OSIEROCONE:-?}"
[ -n "$LISTA" ] && printf '%s\n' "$LISTA" | sed 's/^/  /'
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
