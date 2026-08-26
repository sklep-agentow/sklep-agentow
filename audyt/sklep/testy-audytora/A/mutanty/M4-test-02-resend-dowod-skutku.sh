#!/bin/bash
# PADA na paczce z MUTANTEM M4 (425a3124); na CZYSTEJ 0.9.13 (5d3dcf9, 7e0cf726) PRZECHODZI — patrz README.md w tym katalogu (recenzja #191 W1).
# Opis wady MUTANTA: admin-zamowienie.php:92 `if ( null !== $wyslano )` — wp_mail nigdy nie zwraca null,
# wiec gałąź sukcesu wykonuje sie ZAWSZE: nieudana wysylka zapisuje status 'mail_wyslany'
# i zdarzenie 'mail_resend'. Golden 13: komunikat sukcesu bez dowodu = cicha awaria.
cd "$(dirname "$0")"; . ./lib.sh; sekret_ustaw
SID="cs_test_T02_$(date +%s)"; MAIL="t02-$$@example.test"
sesja_utworz "$SID"; webhook "$(body_paid "$SID" "$MAIL")" >/dev/null
ZID=$(DB "SELECT id FROM wp_sklepag_zamowienia WHERE stripe_session_id='$SID'")
DB "UPDATE wp_sklepag_zamowienia SET email_kupujacego='to-nie-jest-adres' WHERE id=$ZID" >/dev/null
HASLO_ADMIN="$(tr -d '\n' < /root/.claude/sekrety-audyt-admin.txt)"
curl -s -c /tmp/ck-t02.txt -d "log=audytor-admin&pwd=$HASLO_ADMIN&wp-submit=1&testcookie=1" "$BAZA/wp-login.php" -o /dev/null
curl -s -b /tmp/ck-t02.txt "$BAZA/wp-admin/admin.php?page=sklepag-zamowienie&zamowienie=$ZID" -o /tmp/t02.html
NR=$(grep -o "action=sklepag_resend&#038;zamowienie=$ZID&#038;_wpnonce=[a-z0-9]*" /tmp/t02.html | head -1 | sed 's/&#038;/\&/g')
WSZ_PRZED=$(curl -s "$MAILPIT/api/v1/messages?limit=1" | sed 's/{"total":\([0-9]*\).*/\1/')
curl -s -b /tmp/ck-t02.txt -o /dev/null "$BAZA/wp-admin/admin-post.php?$NR"
WSZ_PO=$(curl -s "$MAILPIT/api/v1/messages?limit=1" | sed 's/{"total":\([0-9]*\).*/\1/')
ST=$(DB "SELECT status FROM wp_sklepag_zamowienia WHERE id=$ZID"); TYP=$(DB "SELECT typ FROM wp_sklepag_zdarzenia WHERE zamowienie_id=$ZID ORDER BY id DESC LIMIT 1")
echo "maile w Mailpit: przed=$WSZ_PRZED po=$WSZ_PO | status=$ST | ostatnie zdarzenie=$TYP"
rm -f /tmp/ck-t02.txt /tmp/t02.html; sprzataj "$SID"; sekret_przywroc
if [ "$WSZ_PRZED" = "$WSZ_PO" ] && [ "$ST" = "mail_wyslany" ]; then echo "FAIL: zaden mail nie wyszedl, a zamowienie ma status 'mail_wyslany' i zdarzenie '$TYP'"; exit 1; fi
echo "OK"; exit 0
