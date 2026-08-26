#!/bin/bash
# PADA na paczce z MUTANTEM M3 (425a3124); na CZYSTEJ 0.9.13 (5d3dcf9, 7e0cf726) PRZECHODZI — patrz README.md w tym katalogu (recenzja #191 W1).
# Opis wady MUTANTA: zakup.php:303-310 obiecuje w komentarzu "claim starszy niz 5 min wolno odebrac",
# a warunek SQL to `zmieniono < $teraz` (zero karencji) -> ponowienie webhooka 10 s po przejeciu
# wysyla DRUGI mail i uniewaznia link z pierwszego. Golden 5: "Dwa maile = KRYTYCZNA".
cd "$(dirname "$0")"; . ./lib.sh; sekret_ustaw
SID="cs_test_T01_$(date +%s)"; MAIL="t01-$$@example.test"
sesja_utworz "$SID"; webhook "$(body_paid "$SID" "$MAIL")" >/dev/null
PRZED=$(maile "$MAIL"); echo "maile po 1. domknieciu: $PRZED"
ZM=$(date -u -d '+2 hours -10 seconds' '+%F %T')   # czas lokalny WP minus 10 s
DB "UPDATE wp_sklepag_zamowienia SET status='mail_w_toku', zmieniono='$ZM' WHERE stripe_session_id='$SID'" >/dev/null
echo "claim ustawiony na 10 s wstecz (proces A wciaz wysyla)"
webhook "$(body_paid "$SID" "$MAIL")" >/dev/null
PO=$(maile "$MAIL"); echo "maile po ponowieniu webhooka: $PO"
sprzataj "$SID"; sekret_przywroc
if [ "$PO" != "$PRZED" ]; then echo "FAIL: claim sprzed 10 s zostal przejety -> $PO maili zamiast $PRZED (obiecana karencja 5 min NIE dziala)"; exit 1; fi
echo "OK"; exit 0
