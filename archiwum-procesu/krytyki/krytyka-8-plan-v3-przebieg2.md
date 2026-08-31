# Krytyka 8 — plan v3, DRUGI przebieg (świeży subagent sonnet, 18.08) + TRIAGE

Zadanie: zweryfikować, czy 13 napraw z krytyki 7 REALNIE siedzi w tekście, i szukać
nowych dziur (zwłaszcza wprowadzonych naprawami). Wynik weryfikacji: **13/13 napraw
potwierdzonych cytatami** — triage krytyki 7 uczciwy. Nowe znaleziska + triage:

| # | Waga | Znalezisko (sedno) | Triage | Co zrobiono |
|---|------|--------------------|--------|-------------|
| 1 | DUŻE/KRYT | WYŚCIG webhook × reconciliation o ten sam wiersz: `mail_w_toku` bez atomowego przejęcia; INSERT IGNORE chroni tylko utworzenie wiersza; przy 2 wyzwalaczach reconciliation (cron + self-heal przy 4 adminach) realny podwójny mail, w tym jeden z UNIEWAŻNIONYM tokenem | TRAFIONE (klasa z lekcji MP „lock przez INSERT ≠ mutex") | §4A: każde przejście stanu = CAS (UPDATE z warunkiem, 0 wierszy = wycofaj się bez maila); D24 w DECYZJE; GOTOWE #4: test RÓWNOLEGŁEGO odpalenia obu silników = 1 mail, 1 ważny token |
| 2 | ŚREDNIE | Dwie tabele stanu (sesje_checkout.stan, zamowienia.status) bez granicy transakcyjnej — możliwy rozjazd | TRAFIONE | §5: JEDYNE źródło prawdy = `zamowienia.status`; stan sesji informacyjny, dopisywany best-effort PO przejściu; rozjazd niegroźny i widoczny |
| 3 | ŚREDNIE/DUŻE | Rate limit „per IP" może dławić trasę webhooka (stała pula IP Stripe) — cichy ubój głównej drogi zamaskowany przez reconciliation | TRAFIONE | §7: trasa webhooka JAWNIE wyłączona z limitera (autoryzacja = podpis) |
| 4 | DROBNE/ŚREDNIE | Cudze wtyczki bezpieczeństwa/WAF na wspólnej stronie mogą filtrować publiczny POST do REST — nietestowane | CZĘŚCIOWO (K2 i tak biegnie na stacku z realnym zestawem strony; przyszłe wtyczki nieznane) | §13 (test vs produkcja): jawny punkt „dopuścić trasę webhooka w WAF"; reconciliation = siatka bezpieczeństwa |
| 5 | DUŻE | Gęstość K7 + dokumentacja bez czasu (warunkowy bonus w K7b, finalizacja w dzień próby) | TRAFIONE w części doc | K6: instrukcja instalacji MUSI istnieć PRZED K6 (powstaje do 23.08, K8 tylko finalizuje) — dokumentacja przestaje być robotą ostatniego dnia. Gęstość K7: świadoma (kontrole równoległe, skrypty), przyjęta jak rezydualne ryzyko z recenzji |

Rezydualne, przyjęte z otwartymi oczami: brak zapasu gdyby POWTÓRKA kalibracji
(26.08) też poległa (zbieżne z recenzją PR #26) · gęstość K7 dla 1 osoby.
