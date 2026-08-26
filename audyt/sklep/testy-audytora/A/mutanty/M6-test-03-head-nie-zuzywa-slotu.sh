#!/bin/bash
# PADA na 0.9.13: pobieranie.php:5 deklaruje "HEAD nie zuzywa limitu" (i tak brzmi cel L3-07),
# a pobieranie.php:57-58 inkrementuje pobrania_uzyte takze dla HEAD. Skaner poczty (2-3 dotkniecia)
# zjada kupujacemu sloty, a panel nie ma zadnego sposobu na zresetowanie licznika.
cd "$(dirname "$0")"; . ./lib.sh; sekret_ustaw
SID="cs_test_T03_$(date +%s)"; MAIL="t03-$$@example.test"
sesja_utworz "$SID"; webhook "$(body_paid "$SID" "$MAIL")" >/dev/null
L=$(link_z_ostatniego_maila "$MAIL")
PRZED=$(DB "SELECT pobrania_uzyte FROM wp_sklepag_zamowienia WHERE stripe_session_id='$SID'")
curl -s -I -o /dev/null "$L"
PO=$(DB "SELECT pobrania_uzyte FROM wp_sklepag_zamowienia WHERE stripe_session_id='$SID'")
echo "pobrania_uzyte przed HEAD=$PRZED po HEAD=$PO"
sprzataj "$SID"; sekret_przywroc
if [ "$PRZED" != "$PO" ]; then echo "FAIL: HEAD zuzyl slot ($PRZED -> $PO)"; exit 1; fi
echo "OK"; exit 0
