#!/usr/bin/env bash
# ⛔ TEST NISZCZY STAN STOSU :8891 NA CZAS TRWANIA: podmienia adres API Stripe w
# includes/stripe.php (docker cp+sed) i klucz sklepag_stripe_klucz_sekretny.
# Przywraca sam na końcu (trap): kopia oryginału w /tmp, klucz z powrotem na "".
# Jeśli przerwiesz Ctrl+C w środku, odtwórz ręcznie:
#   docker cp /tmp/l02-stripe.php.orig probny_wp:/var/www/html/wp-content/plugins/sklep-agentow/includes/stripe.php
#   docker exec -u www-data probny_wp wp --path=/var/www/html option update sklepag_stripe_klucz_sekretny ""
#
# Pada NA KODZIE 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d (sklep-agentow 0.9.13).
# Powód: sklepag_dociagnij_zalegle() (includes/zakup.php:465-526) przy niedostępnym
# Stripe loguje 'reconciliation_blad' na KAŻDĄ zaległą sesję/zamówienie, ale NIGDY nie
# zmienia zamowienia.status ani nie inkrementuje mail_proby. Licznik "panel-czerwone"
# w admin-dashboard.php:298 liczy WYŁĄCZNIE `status='mail_blad' AND mail_proby>=3` —
# ta ścieżka awarii nigdy tam nie trafia. Zamówienie może wisieć w 'oplacone'/
# 'mail_w_toku' w NIESKOŃCZONOŚĆ bez żadnego wyróżnienia w panelu.

set -uo pipefail
DB_EXEC() { docker exec strona_wp_db sh -c "MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb -N wp_probny -e \"$1\""; }
WP() { docker exec -u www-data probny_wp wp --path=/var/www/html "$@"; }

docker cp probny_wp:/var/www/html/wp-content/plugins/sklep-agentow/includes/stripe.php /tmp/l02-stripe.php.orig
trap 'docker cp /tmp/l02-stripe.php.orig probny_wp:/var/www/html/wp-content/plugins/sklep-agentow/includes/stripe.php; WP option update sklepag_stripe_klucz_sekretny "" >/dev/null 2>&1' EXIT

sed 's#https://api.stripe.com#https://api.stripe-nieosiagalny-test.invalid#' /tmp/l02-stripe.php.orig > /tmp/l02-stripe-patched.php
docker cp /tmp/l02-stripe-patched.php probny_wp:/var/www/html/wp-content/plugins/sklep-agentow/includes/stripe.php
WP option update sklepag_stripe_klucz_sekretny "sk_test_L02FAKE" >/dev/null

echo "== stan PRZED reconciliation =="
DB_EXEC "SELECT id,status,mail_proby FROM wp_sklepag_zamowienia WHERE status IN ('oplacone','mail_w_toku','mail_blad') ORDER BY id LIMIT 5"
czerwone_przed=$(DB_EXEC "SELECT COUNT(*) FROM wp_sklepag_zamowienia WHERE status='mail_blad' AND mail_proby>=3")

echo "== uruchamiam reconciliation (cron event) z niedostepnym Stripe =="
WP cron event run sklepag_dociagnij_zalegle >/dev/null 2>&1

echo "== stan PO reconciliation (status/mail_proby maja byc BEZ ZMIAN) =="
DB_EXEC "SELECT id,status,mail_proby FROM wp_sklepag_zamowienia WHERE status IN ('oplacone','mail_w_toku','mail_blad') ORDER BY id LIMIT 5"
blad_zdarzen=$(DB_EXEC "SELECT COUNT(*) FROM wp_sklepag_zdarzenia WHERE typ='reconciliation_blad'")
czerwone_po=$(DB_EXEC "SELECT COUNT(*) FROM wp_sklepag_zamowienia WHERE status='mail_blad' AND mail_proby>=3")

echo "zdarzen reconciliation_blad=$blad_zdarzen  panel-czerwone przed=$czerwone_przed po=$czerwone_po"

# Asercja OCZEKIWANA (naprawiony kod): jeśli reconciliation nie mogła domknąć zamówień
# z powodu niedostępności Stripe, licznik "panel-czerwone" (albo inny wyraźny sygnał)
# MUSI wzrosnąć — inaczej admin nie ma żadnego powodu zajrzeć do dziennika zdarzeń.
if [ "$blad_zdarzen" -gt 0 ] && [ "$czerwone_po" -eq "$czerwone_przed" ]; then
  echo "PADA: $blad_zdarzen awarii dociagania Stripe zalogowanych, ZERO wzrostu sygnalu panel-czerwone — awaria cicha."
  exit 1
fi
echo "OK: sygnal w panelu wzrosl razem z awariami."
exit 0
