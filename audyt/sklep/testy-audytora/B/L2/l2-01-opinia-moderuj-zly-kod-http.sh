#!/usr/bin/env bash
# PADAJĄCY test — L2-01
# Kod: 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d, includes/admin-opinie.php:118-121
#   function sklepag_obsluz_moderacje_opinii() {
#       if ( ! current_user_can( 'manage_options' ) ) {
#           wp_die( esc_html__( 'Brak uprawnień.' ) );   // <- BRAK trzeciego argumentu (kod HTTP)
#       }
#       check_admin_referer( 'sklepag_opinia_moderuj' );
#
# Pozostałych 6 handlerów panelu (dociagnij, eksport_csv, produkt_zapisz,
# produkt_przelacz, resend, anonimizuj) woła wp_die( '...', 'Sklep Agentów', 403 )
# z jawnym kodem 403. Ten jeden NIE — domyślny kod WP dla wp_die() bez podanego
# 'response' to 500 (wp-includes/functions.php, _default_wp_die_handler:
# `if ( ! isset( $r['response'] ) ) { ... $r['response'] = 500; }`).
#
# SKUTEK: dostęp jest poprawnie ZABLOKOWANY (nie ma bypassu), ale subscriber/atakujący
# bez uprawnień dostaje HTTP 500 zamiast 403 — monitoring/WAF/testy filtrujące po
# kodzie 403 jako "odmowa dostępu" zobaczą to jako AWARIĘ SERWERA, nie jako odrzucone
# żądanie. Niespójne z pozostałymi 6 handlerami tej samej pary nonce+capability (L2-01).
#
# Wymaga: sesja SUBSCRIBERA w cookie-jarze (audytor-sub). Ścieżkę do jara podaj
# zmienną środowiskową CIASTKA_SUB — skrypt NIE zawiera żadnych sekretów/ciasteczek.
#
# Uruchomienie:
#   CIASTKA_SUB=/tmp/k7b-L2/ck-sub.txt bash l2-01-opinia-moderuj-zly-kod-http.sh [http://localhost:8890]

set -euo pipefail
BASE="${1:-http://localhost:8890}"
JAR="${CIASTKA_SUB:-}"

if [ -z "$JAR" ] || [ ! -f "$JAR" ]; then
  echo "NIE DA SIĘ SPRAWDZIĆ: brak CIASTKA_SUB (cookie-jar sesji audytor-sub). Zaloguj się jako"
  echo "subscriber i podaj ścieżkę zmienną środowiskową CIASTKA_SUB (patrz DOPISEK-KOORDYNATORKI-B.md)."
  exit 2
fi

code=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" \
  -d "action=sklepag_opinia_moderuj&opinia_id=1&akcja=zatwierdz&status_powrotu=oczekuje&_wpnonce=xxxxxxxxxx" \
  "$BASE/wp-admin/admin-post.php")

echo "Kod HTTP dla subscribera bez uprawnień na sklepag_opinia_moderuj: $code"

if [ "$code" = "500" ]; then
  echo "PADA: oczekiwano 403 (jak w pozostałych 6 handlerach), dostano 500."
  exit 1
elif [ "$code" = "403" ]; then
  echo "PRZESZŁO: 403 jak oczekiwano — na tym kodzie błąd nie występuje."
  exit 0
else
  echo "NIEOCZEKIWANY KOD: $code (ani 403, ani 500) — zbadaj ręcznie."
  exit 3
fi
