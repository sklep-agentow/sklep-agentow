#!/bin/bash
# PADA na 0.9.13: wtyczka nie obsluguje ZADNEGO zdarzenia zwrotu (grep 'refund|dispute|charge'
# po includes/*.php = 0 trafien). Po zwrocie w Stripe zamowienie nie dostaje wpisu na osi czasu,
# a KPI Pulpitu dalej liczy te kwote jako przychod.
cd "$(dirname "$0")"; . ./lib.sh; sekret_ustaw
SID="cs_test_T05_$(date +%s)"; MAIL="t05-$$@example.test"
sesja_utworz "$SID"; webhook "$(body_paid "$SID" "$MAIL")" >/dev/null
ZID=$(DB "SELECT id FROM wp_sklepag_zamowienia WHERE stripe_session_id='$SID'")
PRZED=$(DB "SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE zamowienie_id=$ZID")
B=$(printf '{"type":"charge.refunded","data":{"object":{"id":"ch_T05","amount":14900,"amount_refunded":14900,"refunded":true,"metadata":{"checkout_session":"%s"}}}}' "$SID")
KOD=$(webhook "$B"); ST=$(DB "SELECT status FROM wp_sklepag_zamowienia WHERE id=$ZID")
PO=$(DB "SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE zamowienie_id=$ZID")
echo "webhook charge.refunded -> HTTP=$KOD | zdarzenia zamowienia: przed=$PRZED po=$PO | status=$ST"
sprzataj "$SID"; sekret_przywroc
if [ "$PRZED" = "$PO" ]; then echo "FAIL: zwrot nie zostawil ani jednego wpisu na osi czasu zamowienia (status dalej '$ST')"; exit 1; fi
echo "OK"; exit 0
