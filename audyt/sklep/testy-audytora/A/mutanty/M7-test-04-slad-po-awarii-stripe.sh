#!/bin/bash
# PADA na 0.9.13: zakup.php:489-491 — gdy Stripe nie odpowiada, petla 1 reconciliation robi `continue`
# BEZ wpisu do dziennika. Zaplacona sesja, ktorej webhook nie dotarl, nie zostawia zadnego sladu
# (brak zamowienia, brak zdarzenia, brak licznika na Pulpicie).
cd "$(dirname "$0")"; . ./lib.sh
SID="cs_test_T04_$(date +%s)"
DB "INSERT INTO wp_sklepag_sesje_checkout (stripe_session_id, produkt_id, stan, utworzono) VALUES ('$SID',1,'utworzona',DATE_SUB(NOW(), INTERVAL 10 MINUTE))" >/dev/null
WP cron event run sklepag_dociagnij_zalegle >/dev/null 2>&1
SLADY=$(DB "SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE opis LIKE '%$SID%'")
STAN=$(DB "SELECT stan FROM wp_sklepag_sesje_checkout WHERE stripe_session_id='$SID'")
echo "slady w dzienniku dla $SID: $SLADY | stan sesji: $STAN"
DB "DELETE FROM wp_sklepag_sesje_checkout WHERE stripe_session_id='$SID'" >/dev/null
if [ "$SLADY" = "0" ]; then echo "FAIL: awaria dociagania nie zostawila ANI JEDNEGO wpisu w dzienniku"; exit 1; fi
echo "OK"; exit 0
