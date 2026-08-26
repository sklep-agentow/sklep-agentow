#!/bin/bash
# PADAJĄCY TEST — L3 runda B — commit_head 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d
#
# Znalezisko: sklepag_webhook() w includes/zakup.php:167-198 obsługuje WYŁĄCZNIE
# 4 typy zdarzeń Stripe: checkout.session.completed, .async_payment_succeeded,
# .async_payment_failed, .expired. Zdarzenia zwrotu/sporu (`charge.refunded`,
# `charge.dispute.created` i pochodne) NIE MAJĄ żadnej gałęzi — webhook kończy
# na domyślnym `return new WP_REST_Response( array('ok'=>true), 200 )` (linia 197)
# BEZ ŻADNEGO zapisu. Potwierdzone też grepem (brak wyniku):
#   grep -rniE "refund|dispute|charge\.refund|charge\.dispute" --include=*.php .
#
# Skutek: gdy właściciel zwraca pieniądze w Dashboardzie Stripe (D31: „zwrot bez
# pytań"), sklep o tym W OGÓLE się nie dowiaduje — status zamówienia zostaje
# 'mail_wyslany', link pobrania i limit pobrań DALEJ działają, dziennik zdarzeń nie
# ma ani jednego wpisu o zwrocie, a KPI „Przychód" na dashboardzie nadal liczy tę
# sprzedaż jako przychód mimo zwrotu pieniędzy. To dokładnie pytanie z celu L3-19
# („ZWROT w Stripe → status zamówienia, ważność linku, KPI”) — i w kodzie nie ma na
# nie żadnej odpowiedzi.
#
# Ten test NIE niszczy nic: wysyła webhook `charge.refunded` dla PRAWDZIWEGO,
# istniejącego zamówienia i sprawdza, że NIC się nie zmieniło — co jest właśnie
# problemem. PASS (naprawione) = pojawia się jakikolwiek ślad (zdarzenie w
# dzienniku LUB zmiana statusu). FAIL (dziś) = zero śladu, HTTP 200 ok:true.
#
# Wymaga: docker, curl, openssl, mariadb w kontenerze strona_wp_db.
# Uruchomienie: bash test-l3-brak-obslugi-zwrotu.sh

set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
WP=strona_wp_wordpress
DB=strona_wp_db
SEKRET="whsec_TEST_$$_refund"

sprzataj() {
  docker exec -u www-data "$WP" wp --path=/var/www/html option update sklepag_stripe_webhook_sekret "" >/dev/null 2>&1
}
trap sprzataj EXIT

# Bierzemy PRAWDZIWE, istniejące zamówienie ze stosu (nic nie wstawiamy) — dowolne opłacone.
read -r ZAM_ID SID STATUS_PRZED PROBY_PRZED <<EOF
$(docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT id, stripe_session_id, status, pobrania_uzyte FROM wp_sklepag_zamowienia WHERE status='mail_wyslany' ORDER BY id LIMIT 1\"")
EOF
if [ -z "${ZAM_ID:-}" ]; then
  echo "NIE DA SIĘ SPRAWDZIĆ: brak żadnego zamówienia status=mail_wyslany na stosie"; exit 2
fi
echo "zamówienie testowe (istniejące, NIE zmieniamy poza tym testem): #${ZAM_ID} / ${SID}"
echo "status PRZED: ${STATUS_PRZED}   pobrania_uzyte PRZED: ${PROBY_PRZED}"

ZDARZ_PRZED=$(docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE zamowienie_id=${ZAM_ID}\"")

docker exec -u www-data "$WP" wp --path=/var/www/html option update sklepag_stripe_webhook_sekret "$SEKRET" >/dev/null

BODY="{\"type\":\"charge.refunded\",\"data\":{\"object\":{\"id\":\"ch_test_regres\",\"payment_intent\":\"pi_test_regres\",\"metadata\":{\"checkout_session_id\":\"${SID}\"},\"amount_refunded\":14900}}}"
t=$(date +%s); sig="${t}.${BODY}"
v1=$(printf '%s' "$sig" | openssl dgst -sha256 -hmac "$SEKRET" | sed 's/^.* //')
echo "wysylam webhook charge.refunded dla tego zamowienia..."
curl -s -o /tmp/refund_resp.json -w 'HTTP %{http_code}\n' \
  -H "Content-Type: application/json" -H "Stripe-Signature: t=${t},v1=${v1}" \
  -X POST --data "$BODY" http://localhost:8890/wp-json/sklepag/v1/webhook
cat /tmp/refund_resp.json; echo

STATUS_PO=$(docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT status FROM wp_sklepag_zamowienia WHERE id=${ZAM_ID}\"")
ZDARZ_PO=$(docker exec "$DB" sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb wordpress -N -e \"SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE zamowienie_id=${ZAM_ID}\"")
echo "status PO: ${STATUS_PO}   zdarzeń PRZED/PO: ${ZDARZ_PRZED}/${ZDARZ_PO}"

grep -rniE "refund|dispute" --include=*.php "$KORZEN/wtyczki/sklep-agentow"/includes/ | grep -v "front-produkt.php" \
  && echo "(znaleziono obsługę — sprawdź ręcznie)" \
  || echo "grep w includes/ (poza tekstami marketingowymi front-produkt.php): 0 wynikow — brak jakiejkolwiek obslugi refund/dispute"

if [ "$STATUS_PO" = "$STATUS_PRZED" ] && [ "$ZDARZ_PO" = "$ZDARZ_PRZED" ]; then
  echo "FAIL: webhook zwrotu nie zostawił ŻADNEGO śladu (status i dziennik bez zmian)."
  echo "Zamówienie nadal wygląda jak opłacone: link pobrania nadal działa, KPI dashboardu nadal liczy przychód."
  exit 1
fi
echo "PASS: zwrot pozostawił ślad (status lub dziennik się zmienił) — naprawione."
exit 0
