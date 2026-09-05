#!/bin/bash
# wp-cron-stanowisko.sh — ZEWNĘTRZNY spust WP-Cron dla stanowiska warsztatowego.  WERSJA 1.0.0
#
# PO CO TO POWSTAŁO (znalezisko I-25, zmierzone 1.09 i potwierdzone 5.09):
# na stanowisku leżało 15 zadań w kolejce, 8 zaległych, najstarsze
# `sklepag_dociagnij_zalegle` sprzed godzin. Przyczyna NIE jest "brak ruchu":
# `siteurl` to http://localhost:8890, a WEWNĄTRZ kontenera na 8890 nikt nie
# słucha (apache stoi na 80), więc pętla zwrotna po wp-cron.php kończy się
# odmową połączenia:
#     docker exec strona_wp_wordpress curl http://localhost:8890/wp-cron.php
#     -> kod 000, curl exit 7 (connection refused)
# Żaden ruch tego nie naprawi — WP-Cron nie ma jak wystartować sam.
# Dlatego: DISABLE_WP_CRON=true w wp-config.php + TEN spust z hosta co minutę.
#
# Konsekwencja, o której trzeba wiedzieć: gdy ten wpis crona zniknie, zadania
# przestaną chodzić CAŁKIEM i po cichu. Kontrola: `cron event list` nie może
# pokazywać zaległych starszych niż ~2 minuty (patrz próba kontrolna w opisie
# w narzedzia-qa/stanowisko/README.md).
#
# Kody wyjścia: 0 = bieg wykonany albo kontener nie chodzi (nie alarmujemy,
#               stanowisko bywa zgaszone), 1 = wp zwróciło błąd.
set -u

KONTENER=strona_wp_wordpress
SCIEZKA_WP=/var/www/html

if ! docker ps --format '{{.Names}}' | grep -qx "$KONTENER"; then
	# kontener zgaszony — to nie jest awaria crona
	exit 0
fi

docker exec -u www-data "$KONTENER" wp --path="$SCIEZKA_WP" cron event run --due-now --quiet
