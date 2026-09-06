#!/usr/bin/env bash
# STRAŻNIK REGRESJI — klasa K-M2 (Mariusz, egzamin 6.09.2026, ŚREDNI): suma zwrotów `zwrocono_grosze`
# była liczona jako ODCZYT → DODANIE → ZAPIS BEZ WARUNKU (`SET zwrocono_grosze=%d WHERE id=%d`), więc dwa
# równoległe `refund.created` (A 30 zł, B 20 zł) czytały to samo 0 i zapisywały 30 albo 20 zamiast 50 (lost update).
# Ten sam wzorzec ochrony (CAS / atomowy przyrost) mamy na `status` (D24) — na kolumnie z pieniędzmi go nie było.
#
# Wyścig wymuszany DETERMINISTYCZNIE: robotnik A śpi 1,5 s tuż przed swoim UPDATE-em (po odczycie),
# robotnik B startuje 0,5 s po A i kończy w tym oknie. Dwa procesy PHP w kontenerze (`wp eval-file`).
# Uruchomienie: bash testy/zwrot-rownolegly-test.sh [kontener=strona_wp_wordpress]; echo $?
# PASS = zwrocono == 5000 (oba zwroty policzone), sekwencyjnie 5000, duplikat raz, status z drugiego silnika nie cofnięty. FAIL = jeden zwrot zgubiony albo status nadpisany.
set -u
K="${1:-strona_wp_wordpress}"
TU="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
W=/tmp/zwrot-rownolegly-worker.php
docker cp "$TU/pomocnicze/zwrot-rownolegly-worker.php" "$K:$W" >/dev/null || { echo "BLAD-SRODOWISKA: docker cp"; exit 2; }
wp() { docker exec -u www-data "$K" wp --path=/var/www/html eval-file "$W" "$@" 2>&1; }
BLED=0

# 1) RÓWNOLEGLE: A (3000, opóźniony) i B (2000) na tym samym zamówieniu
wp zaloz | grep -q zalozone || { echo "BLAD-SRODOWISKA: zaloz"; wp zaloz; exit 2; }
wp zwrot re_rown_A 3000 opoznij > /tmp/zwrot-rown-A.log 2>&1 &
PA=$!
sleep 0.5
wp zwrot re_rown_B 2000 > /tmp/zwrot-rown-B.log 2>&1
wait "$PA"
cat /tmp/zwrot-rown-A.log /tmp/zwrot-rown-B.log | sed 's/^/  robotnik: /'
R=$(wp odczyt | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2)
echo "ROWNOLEGLE: zwrocono=${R:-brak} (OCZEKIWANE: 5000 — A 3000 + B 2000)"
if [ "${R:-0}" -eq 5000 ]; then echo "ROWNOLEGLE: WYNIK=PASS"; else echo "ROWNOLEGLE: WYNIK=FAIL - rownolegly zwrot zgubiony (lost update)"; BLED=1; fi

# 2) KONTROLA: sekwencyjnie te same dwa zwroty → 5000 (próba nie może padać z innego powodu niż wyścig)
wp zaloz >/dev/null
wp zwrot re_rown_C 3000 >/dev/null
wp zwrot re_rown_D 2000 >/dev/null
R2=$(wp odczyt | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2)
echo "SEKWENCYJNIE: zwrocono=${R2:-brak} (OCZEKIWANE: 5000)"
if [ "${R2:-0}" -eq 5000 ]; then echo "SEKWENCYJNIE: WYNIK=PASS"; else echo "SEKWENCYJNIE: WYNIK=FAIL - zwykle ksiegowanie zepsute"; BLED=1; fi

# 3) KONTROLA: równoległy DUPLIKAT tego samego zdarzenia (Stripe: „might occasionally receive the same event more than once”) → 3000, nie 6000
wp zaloz >/dev/null
wp zwrot re_rown_E 3000 opoznij > /tmp/zwrot-rown-E1.log 2>&1 &
PE=$!
sleep 0.5
wp zwrot re_rown_E 3000 > /tmp/zwrot-rown-E2.log 2>&1
wait "$PE"
R3=$(wp odczyt | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2)
echo "DUPLIKAT_ROWNOLEGLY: zwrocono=${R3:-brak} (OCZEKIWANE: 3000 — jeden zwrot, dwa doreczenia)"
if [ "${R3:-0}" -eq 3000 ]; then echo "DUPLIKAT_ROWNOLEGLY: WYNIK=PASS"; else echo "DUPLIKAT_ROWNOLEGLY: WYNIK=FAIL - duplikat policzony dwa razy"; BLED=1; fi

# 4) STATUS PISANY PRZEZ DRUGI SILNIK W OKNIE KSIĘGOWANIA (recenzja #307 r4): zwrot częściowy czyta pod zamkiem
#    `mail_w_toku`, w oknie domykanie kończy wysyłkę (`mail_wyslany`) POZA zamkiem; zapis zwrotu NIE MOŻE cofnąć statusu
#    (przed naprawą cofał → po 5 min drugi mail). Oczekiwane: status zostaje mail_wyslany, zwrot wstrzymany (false),
#    ponowienie księguje 3000 przy mail_wyslany.
wp zaloz >/dev/null
wp status mail_w_toku >/dev/null
wp zwrot re_rown_S 3000 opoznij > /tmp/zwrot-rown-S.log 2>&1 &
PS=$!
sleep 0.5
wp status mail_wyslany >/dev/null
wait "$PS"
sed 's/^/  robotnik: /' /tmp/zwrot-rown-S.log
O4=$(wp odczyt); R4=$(echo "$O4" | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2); S4=$(echo "$O4" | grep -oE 'status=[a-z_]+' | cut -d= -f2)
echo "STATUS_INNY_SILNIK: status=${S4:-brak} zwrocono=${R4:-brak} (OCZEKIWANE: mail_wyslany, 0 — zapis zwrotu wstrzymany, nie cofnal statusu)"
if [ "${S4:-}" = "mail_wyslany" ] && [ "${R4:-0}" -eq 0 ] && grep -q -- '-> false' /tmp/zwrot-rown-S.log; then echo "STATUS_INNY_SILNIK: WYNIK=PASS"; else echo "STATUS_INNY_SILNIK: WYNIK=FAIL - zapis zwrotu nadpisal status z drugiego silnika albo nie oddal false"; BLED=1; fi
wp zwrot re_rown_S 3000 >/dev/null
O5=$(wp odczyt); R5=$(echo "$O5" | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2); S5=$(echo "$O5" | grep -oE 'status=[a-z_]+' | cut -d= -f2)
echo "PONOWIENIE_PO_WYSCIGU: status=${S5:-brak} zwrocono=${R5:-brak} (OCZEKIWANE: mail_wyslany, 3000)"
if [ "${S5:-}" = "mail_wyslany" ] && [ "${R5:-0}" -eq 3000 ]; then echo "PONOWIENIE_PO_WYSCIGU: WYNIK=PASS"; else echo "PONOWIENIE_PO_WYSCIGU: WYNIK=FAIL - ponowienie nie zaksiegowalo zwrotu po wyscigu"; BLED=1; fi

# 5) A→B→A (recenzja r5): status wraca do tej samej wartości MIĘDZY UPDATE-em (0 wierszy) a odczytem kontrolnym.
#    Sam odczyt statusu mówił „nic się nie zmieniło” i kod ogłaszał zwrot zaksięgowany (wpis zwrot_*), choć kwoty nie zapisał —
#    wpis blokował potem ponowienie na stałe. Oczekiwane: false, zwrocono 0, ZERO wpisów zwrot_* z tym id; ponowienie → 3000.
wp zaloz >/dev/null
docker exec "$K" rm -f /tmp/zwrot-rown-po-update
wp zwrot re_rown_ABA 3000 opoznij2 > /tmp/zwrot-rown-ABA.log 2>&1 &
PABA=$!
sleep 1.0
wp status oplacone >/dev/null            # B — w oknie przed UPDATE-em robotnika
i=0; until docker exec "$K" test -f /tmp/zwrot-rown-po-update || [ $i -ge 60 ]; do sleep 0.2; i=$((i+1)); done
wp status mail_wyslany >/dev/null        # z powrotem A — w oknie przed odczytem kontrolnym
wait "$PABA"
sed 's/^/  robotnik: /' /tmp/zwrot-rown-ABA.log
O6=$(wp odczyt); R6=$(echo "$O6" | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2); L6=$(wp slady re_rown_ABA | grep -oE 'slady=[0-9]+' | cut -d= -f2)
echo "STATUS_A_B_A: zwrocono=${R6:-brak} slady_zwrot=${L6:-brak} (OCZEKIWANE: 0 i 0 — zapis bez skutku NIE oglasza zwrotu)"
if [ "${R6:-1}" -eq 0 ] && [ "${L6:-1}" -eq 0 ] && grep -q -- '-> false' /tmp/zwrot-rown-ABA.log; then echo "STATUS_A_B_A: WYNIK=PASS"; else echo "STATUS_A_B_A: WYNIK=FAIL - zwrot oglaszony jako zaksiegowany bez zapisu kwoty (A->B->A)"; BLED=1; fi
wp zwrot re_rown_ABA 3000 >/dev/null
R7=$(wp odczyt | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2)
echo "PONOWIENIE_PO_A_B_A: zwrocono=${R7:-brak} (OCZEKIWANE: 3000)"
if [ "${R7:-0}" -eq 3000 ]; then echo "PONOWIENIE_PO_A_B_A: WYNIK=PASS"; else echo "PONOWIENIE_PO_A_B_A: WYNIK=FAIL - ponowienie nie zaksiegowalo zwrotu"; BLED=1; fi

# 6) SPÓR w oknie drugiego silnika (bliźniak scenariusza 4 dla sklepag_zaksieguj_spor): status zmienia się poza zamkiem
#    między odczytem a zapisem sporu → zapis wstrzymany (false), status z drugiego silnika zostaje; ponowienie → sporne.
wp zaloz >/dev/null
wp spor 9900 opoznij > /tmp/zwrot-rown-SP.log 2>&1 &
PSP=$!
sleep 1.0
wp status mail_w_toku >/dev/null
wait "$PSP"
sed 's/^/  robotnik: /' /tmp/zwrot-rown-SP.log
O8=$(wp odczyt); S8=$(echo "$O8" | grep -oE 'status=[a-z_]+' | cut -d= -f2); R8=$(echo "$O8" | grep -oE 'zwrocono=[0-9]+' | cut -d= -f2)
echo "SPOR_INNY_SILNIK: status=${S8:-brak} zwrocono=${R8:-brak} (OCZEKIWANE: mail_w_toku, 0 — zapis sporu wstrzymany)"
if [ "${S8:-}" = "mail_w_toku" ] && [ "${R8:-1}" -eq 0 ] && grep -q -- '-> false' /tmp/zwrot-rown-SP.log; then echo "SPOR_INNY_SILNIK: WYNIK=PASS"; else echo "SPOR_INNY_SILNIK: WYNIK=FAIL - zapis sporu nadpisal status z drugiego silnika albo nie oddal false"; BLED=1; fi
wp spor 9900 >/dev/null
S9=$(wp odczyt | grep -oE 'status=[a-z_]+' | cut -d= -f2)
echo "PONOWIENIE_SPORU: status=${S9:-brak} (OCZEKIWANE: sporne)"
if [ "${S9:-}" = "sporne" ]; then echo "PONOWIENIE_SPORU: WYNIK=PASS"; else echo "PONOWIENIE_SPORU: WYNIK=FAIL - ponowienie nie zaksiegowalo sporu"; BLED=1; fi

wp sprzataj >/dev/null
docker exec "$K" rm -f "$W" /tmp/zwrot-rown-po-update 2>/dev/null
[ "$BLED" -eq 0 ] && { echo "RAZEM: WYNIK=PASS"; exit 0; }
echo "RAZEM: WYNIK=FAIL"; exit 1
