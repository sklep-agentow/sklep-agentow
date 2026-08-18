# Krytyka 7 — plan v3 (świeży krytyk-obalacz, subagent sonnet, 18.08) + TRIAGE

Zapis wierny znalezisk (skróty bez zmiany sensu) + rozstrzygnięcie. Równolegle:
recenzja PR #26 (recenzent-Claude) — zbieżność oznaczona. Poprawki: commit „docs:
poprawki po recenzji PR #26 i krytyce 7".

| # | Waga | Znalezisko (sedno) | Triage | Co zrobiono |
|---|------|--------------------|--------|-------------|
| 1 | KRYTYCZNE | Brak mechanizmu doręczenia webhooka Stripe do lokalnego Dockera (serwery Stripe nie widzą localhosta); GOTOWE #2 nie do wykonania | TRAFIONE | §4A: `stripe listen` (CLI) lokalnie / endpoint Dashboardu na hostingu + **DRUGA niezależna droga: reconciliation przez `GET /checkout/sessions/{id}`** (cron domyka zakup bez webhooka); K1 testuje obie drogi; Z9 |
| 2 | KRYTYCZNE | Katalog „chroniony" w uploads = publicznie serwowany; sama losowość nazwy katalogu, HMAC teatrem przy wycieku URL | TRAFIONE | §4A: ochrona WARSTWAMI wzorem Woo/EDD (losowe nazwy plików bez .zip + index.php + .htaccess deny na Apache + gotowa reguła nginx w instrukcji) + test K2: bezpośredni GET = odmowa; Z10. Stack potwierdzony: Apache 2.4.67 (rejestr) |
| 3 | DUŻE | 13 dni-etapów na 10 dni kalendarzowych; nakładki 20/21/22.08 nie nazwane | TRAFIONE | §9: jawna notka o pół-dniach granicznych + zasada cięcia szlifu K5 (nie audytu) przy dryfie; POMIAR-CZASU jako wskaźnik najpóźniej 21.08 |
| 4 | DUŻE | Zero bufora między audytem a zamrożeniem paczki | TRAFIONE (zbieżne z recenzją PR #26) | K7 rozbite: 25.08 audyt · **26.08 K7b = naprawy + POWTÓRKA kalibracji** (bufor wewnętrzny, nie mylić z buforem na zwroty) |
| 5 | DUŻE | Rubryka zamrażana po K2, gdy połowa zakresu nie istnieje | TRAFIONE | §12: jawna ścieżka zmiany po zamrożeniu — wyłącznie osobny PR z wpisem w DECYZJE (nigdy po cichu) |
| 6 | DUŻE | WP-cron = pseudo-cron odpalany ruchem; „co 10 min" niegwarantowane na cichej stronie | TRAFIONE | §4A CRON: (a) reconciliation też przy wejściu na dashboard (self-heal), (b) ręczny przycisk, (c) instrukcja: DISABLE_WP_CRON + systemowy cron |
| 7 | DUŻE | Trasa REST bez `permission_callback` = notice (łamie GOTOWE #1) | TRAFIONE | §4A: jawny `permission_callback => __return_true` (autoryzacją jest podpis) |
| 8 | DUŻE | Uprawnienia zapisu uploads w Dockerze (UID www-data 33/82) nieadresowane | TRAFIONE (klasa znana z MP) | K1: próba kontrolna zapisu do uploads + upload_max_filesize + serwer stacka |
| 9 | DUŻE | Backoff logowania bez pokrycia w GOTOWE + ryzyko zablokowania egzaminatora na wspólnych kontach | TRAFIONE | GOTOWE #8: pomiar opóźnienia; §7: opóźnienie per login+IP, SUFIT 60 s, reset po sukcesie, NIGDY twarda blokada |
| 10 | DROBNE/DUŻE | Limit rozmiaru zipa bez liczby i bez odniesienia do upload_max_filesize | TRAFIONE | §7: limit 20 MB (boty <1 MB); realny limit PHP sprawdzany w K1 |
| 11 | DROBNE | K6 na czystym WP nie testuje kolizji z realnym motywem/cache strony | CZĘŚCIOWO (budowa i K2/K5 CAŁY CZAS idą na kopii realnej strony) | K2: dopisane wprost — wroga sesja na stacku z realnym motywem; kolizje łapane ciągle |
| 12 | DROBNE | Kompletność treści sprzedażowej strony produktu (S-03) bez kryterium | TRAFIONE (zbieżne z recenzją) | GOTOWE #10 rozszerzone: komplet S-03 na stronie produktu |
| 13 | DROBNE | Koszt przeglądu PR-ów buildera nieujęty w harmonogramie | TRAFIONE | §9: stała godzina zamykająca dzień, w obłożeniu K1-K5 |
| 14 | — | NIE SPRAWDZIŁ: ZWIAD-STRIPE, rejestr, kod, docker-compose, prawo | przyjęte | lista białych plam krytyka = wsad dla audytu K7 (soczewki bez podwójnego pokrycia) |

Zbieżność recenzja PR #26 × krytyk (niezależnie): bufor przed K8 (#4) · pokrycie
obietnic bezpieczeństwa w GOTOWE (#9/#10/#12) → pewniaki, naprawione w pierwszej
kolejności. Znaleziska #1/#2 (tylko krytyk) zweryfikowane u siebie: oba realne.
