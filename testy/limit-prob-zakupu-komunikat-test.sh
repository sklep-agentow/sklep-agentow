#!/bin/bash
# STRAŻNIK — SK-L2-002 (komunikat po przekroczeniu limitu prób nic nie tłumaczył).
#
# Limiter liczy WSZYSTKIE próby zakupu z jednego adresu — to zamierzone (standard obrony
# przed nadużyciem). Wadą był komunikat: „Za dużo prób. Odczekaj minutę." nie mówił, czy
# coś już pobrano z konta, ani dlaczego blokada dotyka kogoś, kto kliknął pierwszy raz
# (w sieci firmowej jeden adres wyjściowy jest wspólny dla wielu osób).
#
# Token bierzemy ważny (inaczej odmowa zapada wcześniej, na sprawdzeniu tokenu) i strzelamy
# ponad próg, żeby dostać PRAWDZIWĄ odpowiedź 429 z produktu, nie sam tekst z pliku.
#
# PASS (exit 0) = odpowiedź 429 zapewnia, że nic nie pobrano z konta, i wyjaśnia wspólny adres.
# FAIL (exit 1) = komunikat bez tych informacji albo limiter nie odpowiada kodem 429.
#
# Uruchomienie: bash testy/limit-prob-zakupu-komunikat-test.sh [kontener]
set -u
KONTENER="${1:-probny_wp}"
WP="docker exec -u www-data $KONTENER wp --path=/var/www/html"

NONCE=$($WP eval 'echo wp_create_nonce("sklepag_kup");' 2>/dev/null | tail -1)
if [ -z "$NONCE" ]; then
  echo "STRAZNIK: WYNIK=BLAD-SRODOWISKA (nie udalo sie wygenerowac tokenu)"; exit 2
fi

KOD=""; TRESC=""
for i in $(seq 1 14); do
  ODP=$(docker exec "$KONTENER" curl -s -w '\nHTTP=%{http_code}' \
    -X POST 'http://localhost/wp-admin/admin-post.php' \
    --data "action=sklepag_kup&produkt_id=1&zgoda=1&_wpnonce=$NONCE" 2>/dev/null)
  KOD=$(printf '%s' "$ODP" | tail -1 | sed 's/HTTP=//')
  TRESC=$(printf '%s' "$ODP" | head -n -1)
  [ "$KOD" = "429" ] && break
done

echo "STRAZNIK: kod_http=$KOD (OCZEKIWANE: 429)"
PL=0; NAT=0
printf '%s' "$TRESC" | grep -q "nic nie zostało pobrane" && PL=1
printf '%s' "$TRESC" | grep -q "sieci firmowej" && NAT=1
echo "STRAZNIK: mowi_ze_nic_nie_pobrano=$( [ "$PL" = 1 ] && echo TAK || echo NIE ) (OCZEKIWANE: TAK)"
echo "STRAZNIK: tlumaczy_wspolny_adres=$( [ "$NAT" = 1 ] && echo TAK || echo NIE ) (OCZEKIWANE: TAK)"

# Sprzatanie sladow po serii prob (dziennik zdarzen i licznik limitera).
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}sklepag_rate_limit"); $wpdb->query("DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE typ IN (\"checkout_blad\",\"kup_nonce_niewazny\") AND czas > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");' >/dev/null 2>&1

if [ "$KOD" = "429" ] && [ "$PL" = 1 ] && [ "$NAT" = 1 ]; then
  echo "STRAZNIK: WYNIK=PASS"; exit 0
fi
echo "STRAZNIK: WYNIK=FAIL - komunikat po przekroczeniu limitu nie tlumaczy sytuacji kupujacemu"
exit 1
