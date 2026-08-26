# Wspolne dla testow L3. Wymaga: sekret webhooka USTAWIONY w opcji sklepag_stripe_webhook_sekret
# (skrypt sam go ustawia i przywraca), Mailpit na :8025, stos :8890.
BAZA=http://localhost:8890
MAILPIT=http://localhost:8025
DB(){ docker exec strona_wp_db sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb --skip-column-names -B wordpress -e \"$1\""; }
WP(){ docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html "$@"; }
SEKRET_STARY=""
sekret_ustaw(){ SEKRET_STARY=$(DB "SELECT option_value FROM wp_options WHERE option_name='sklepag_stripe_webhook_sekret'"); SEK="whsec_TEST_$$_$(date +%s)"; WP option update sklepag_stripe_webhook_sekret "$SEK" >/dev/null; }
sekret_przywroc(){ DB "UPDATE wp_options SET option_value='${SEKRET_STARY}' WHERE option_name='sklepag_stripe_webhook_sekret'" >/dev/null; }
webhook(){ local B="$1" T SIG; T=$(date +%s); SIG=$(printf '%s.%s' "$T" "$B" | openssl dgst -sha256 -hmac "$SEK" -r | cut -d' ' -f1)
  curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "Stripe-Signature: t=$T,v1=$SIG" --data-binary "$B" "$BAZA/wp-json/sklepag/v1/webhook"; }
body_paid(){ printf '{"type":"checkout.session.completed","data":{"object":{"id":"%s","payment_status":"paid","amount_total":14900,"currency":"pln","customer_details":{"email":"%s"},"metadata":{"produkt_id":"1","zgoda_czas":"2026-08-25 23:00:00"}}}}' "$1" "$2"; }
sesja_utworz(){ DB "INSERT INTO wp_sklepag_sesje_checkout (stripe_session_id, produkt_id, stan, utworzono) VALUES ('$1',1,'utworzona',NOW())"; }
maile(){ curl -s "$MAILPIT/api/v1/search?query=$(printf %s "$1" | sed 's/@/%40/')" | sed 's/.*"messages_count":\([0-9]*\).*/\1/;s/{.*//'; }
link_z_ostatniego_maila(){ curl -s "$MAILPIT/api/v1/search?query=$(printf %s "$1" | sed 's/@/%40/')" | python3 -c "
import json,sys,urllib.request,re
d=json.load(sys.stdin); m=sorted(d['messages'], key=lambda x:x['Created'])[-1]
j=json.load(urllib.request.urlopen('http://localhost:8025/api/v1/message/'+m['ID']))
print(re.findall(r'https?://[^\s]*action=sklepag_pobierz[^\s]*', j['Text'])[0])"; }
sprzataj(){ DB "DELETE z FROM wp_sklepag_zdarzenia z JOIN wp_sklepag_zamowienia o ON o.id=z.zamowienie_id WHERE o.stripe_session_id='$1'"
  DB "DELETE FROM wp_sklepag_opinie WHERE zamowienie_id IN (SELECT id FROM wp_sklepag_zamowienia WHERE stripe_session_id='$1')"
  DB "DELETE FROM wp_sklepag_zamowienia WHERE stripe_session_id='$1'"; DB "DELETE FROM wp_sklepag_sesje_checkout WHERE stripe_session_id='$1'"
  DB "DELETE FROM wp_sklepag_zdarzenia WHERE opis LIKE '%$1%'"; }
