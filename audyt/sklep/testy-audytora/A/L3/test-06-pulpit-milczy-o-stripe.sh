#!/bin/bash
# PADA na 0.9.13: cel L3-16 mowi "zly/pusty klucz = sygnal w panelu, nie cicha awaria".
# Przy PUSTYM kluczu sk_ przycisk Kup dziala, kupujacy dostaje surowa strone bledu 502,
# a Pulpit sklepu (admin.php?page=sklepag) nie pokazuje o tym ani slowa.
cd "$(dirname "$0")"; . ./lib.sh
DL=$(DB "SELECT LENGTH(option_value) FROM wp_options WHERE option_name='sklepag_stripe_klucz_sekretny'")
if [ "$DL" != "0" ]; then echo "POMINIETY: klucz Stripe jest ustawiony (dlugosc $DL) — test dotyczy stanu bez klucza"; exit 0; fi
HASLO_ADMIN="$(tr -d '\n' < /root/.claude/sekrety-audyt-admin.txt)"
curl -s -c /tmp/ck-t06.txt -d "log=audytor-admin&pwd=$HASLO_ADMIN&wp-submit=1&testcookie=1" "$BAZA/wp-login.php" -o /dev/null
curl -s -b /tmp/ck-t06.txt "$BAZA/wp-admin/admin.php?page=sklepag" -o /tmp/t06.html
SYGNAL=$(grep -c -i 'notice.*\(klucz\|stripe\|platnos\)' /tmp/t06.html)
curl -s "$BAZA/sklep/produkt/chatbot-live/" -o /tmp/t06p.html
N=$(grep -o 'name="_wpnonce" value="[a-z0-9]*"' /tmp/t06p.html | head -1 | sed 's/.*value="//;s/"//')
KOD=$(curl -s -o /dev/null -w '%{http_code}' -d "action=sklepag_kup&produkt_id=1&zgoda=1&_wpnonce=$N" "$BAZA/wp-admin/admin-post.php")
echo "ostrzezen o Stripe na Pulpicie: $SYGNAL | klikniecie Kup konczy sie HTTP=$KOD"
rm -f /tmp/ck-t06.txt /tmp/t06.html /tmp/t06p.html
if [ "$SYGNAL" = "0" ] && [ "$KOD" = "502" ]; then echo "FAIL: sklep przyjmuje klikniecia Kup i zwraca 502, a Pulpit nie ostrzega o niesprawnej platnosci"; exit 1; fi
echo "OK"; exit 0
