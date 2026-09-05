#!/bin/bash
# instaluj-spust-crona.sh — wkłada zewnętrzny spust WP-Cron w crona HOSTA.  WERSJA 1.0.0
#
# Źródłem prawdy jest plik w tym repozytorium; na hoście leży KOPIA pod stałą
# ścieżką (/usr/local/sbin), żeby wpis crona nie wskazywał na worktree, który
# po scaleniu znika.
#
# Kody wyjścia: 0 = zainstalowane, 1 = nie ma czego instalować / brak praw.
set -eu

ZRODLO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/wp-cron-stanowisko.sh"
CEL=/usr/local/sbin/wp-cron-stanowisko.sh
WPIS=/etc/cron.d/stanowisko-wp-cron

[ -r "$ZRODLO" ] || { echo "INSTALACJA: brak $ZRODLO" >&2; exit 1; }

install -m 0755 "$ZRODLO" "$CEL"

cat > "$WPIS" <<CRON
# Zewnętrzny spust WP-Cron stanowiska warsztatowego (znalezisko I-25).
# Pętla zwrotna WordPressa tu NIE DZIAŁA (siteurl :8890, apache w kontenerze na :80),
# więc DISABLE_WP_CRON=true, a zaległe zadania dociąga ten wpis co minutę.
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * root $CEL >> /var/log/stanowisko-wp-cron.log 2>&1
CRON
chmod 0644 "$WPIS"

echo "INSTALACJA: $WPIS + $CEL — gotowe"
