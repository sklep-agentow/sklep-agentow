#!/usr/bin/env bash
# STRAŻNIK sufitów klienta IMAP (Segm_Imap::MAX_*) — próba kontrolna z atrapą serwera, bez Dockera.
# Wrogie scenariusze (literał 900 MB zapowiedziany / strumieniowany, linia bez końca, strumień bez
# znacznika) MUSZĄ skończyć się komunikatem, nie fatalem PHP; kontrole dodatnie MUSZĄ przejść:
# `poprawny` (FETCH nagłówka) i `search-40k` (`* SEARCH` z 40 000 UID-ów w JEDNEJ linii ~240 KB —
# recenzja #301 r3: sufit 64 KB odcinał skrzynki od ~11 000 maili). EXIT 1 = wada, 2 = brak php.
set -u
cd "$(dirname "$0")/.." || exit 2
command -v php >/dev/null 2>&1 || { echo "IMAP-SUFITY: brak php — nie zmierzono"; exit 2; }
WY="$(PORT=$((15300 + RANDOM % 1000)) timeout 150 bash audyt/plamy/A7b-sonda-imap.sh 2>&1)"
echo "$WY" | grep -E "WYNIK|UIDY|NAGLOWKI"
BLEDY=0
FAT=$(echo "$WY" | grep -c "FATAL"); [ "$FAT" -eq 0 ] || { echo "  ZLE  $FAT scenariusz(e) skonczyly sie fatalem PHP"; BLEDY=$((BLEDY+1)); }
N=$(echo "$WY" | grep -c "bez fatala"); [ "$N" -eq 8 ] || { echo "  ZLE  oczekiwano 8 scenariuszy bez fatala, jest $N"; BLEDY=$((BLEDY+1)); }
echo "$WY" | grep -q "NAGLOWKI: string(" || { echo "  ZLE  kontrola dodatnia 'poprawny': klient nie oddal naglowka"; BLEDY=$((BLEDY+1)); }
echo "$WY" | grep -q "UIDY: 40000" || { echo "  ZLE  kontrola dodatnia 'search-40k': klient nie oddal 40000 UID-ow (sufit linii odcina duza skrzynke)"; BLEDY=$((BLEDY+1)); }
if [ "$BLEDY" -gt 0 ]; then echo "IMAP-SUFITY: PORAZKA ($BLEDY) EXIT=1"; exit 1; fi
echo "IMAP-SUFITY: 8/8 bez fatala, obie kontrole dodatnie przeszly. EXIT=0"; exit 0
