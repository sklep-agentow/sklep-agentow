#!/bin/bash
# L5 — runda B — commit_head 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d
# PADA NA OBECNYM KODZIE: komentarze PHP wysyłane klientowi w paczce odwołują się do
# WEWNĘTRZNEJ numeracji planowania (S-04, D32, K3, K5, kryterium GOTOWE #7,
# PLAN-WYGLADU-K5, MAPA-SPEC), której klient nie posiada i nie rozumie — klasa
# "ślady wewnętrzne w paczce klienta" (ściąga egzaminatora, klasa #5). Bramka
# narzedzia-qa/slady-wewnetrzne.sh tego NIE łapie (jej wzorzec numeru wymaga
# separatora typu "PR #"/"nr #"/"zgłoszenie #" + 2-4 cyfry — "S-04", "D32", "#7"
# tego nie spełniają). Test liczy trafienia w KODZIE PACZKI (nie w audyt/, nie w
# testach) — jeśli >0, to jest błąd tej klasy.
# Nieniszczący (tylko odczyt).
set -u
PACZKA="${1:-/root/praca/zlecenia/wymiana/k7-runda-B/sklep-agentow}"

WZORZEC='\bK[0-9]\b|\bD3?[0-9]\b|\bS-[0-9]{2}\b|kryterium GOTOWE ?#[0-9]|PLAN-WYGLADU|MAPA-SPEC|PLAN-PROJEKTU'

TRAFIENIA=$(grep -rnE "$WZORZEC" "$PACZKA"/includes/*.php "$PACZKA"/sklep-agentow.php 2>/dev/null)
LICZBA=$(printf '%s\n' "$TRAFIENIA" | grep -c .)

echo "Trafienia wewnętrznej numeracji planu w komentarzach kodu paczki: $LICZBA"
if [ "$LICZBA" -gt 0 ]; then
	echo "$TRAFIENIA" | head -15
	echo "..."
	echo "PADA: $LICZBA odwołań do wewnętrznych kodów planu (S-xx/D-xx/K-x/kryterium GOTOWE/PLAN-WYGLADU) w kodzie wysyłanym klientowi."
	exit 1
fi
echo "OK: brak."
exit 0
