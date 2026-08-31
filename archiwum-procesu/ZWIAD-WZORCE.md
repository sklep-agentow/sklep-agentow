# ZWIAD WZORCÓW — jak najlepsi sprzedają cyfrowo (agenty/kursy)

Cel: wzorce dziedziny PRZED budową (zasada: nie zgaduje się budowania). To NIE są strony, na które robimy.

## 1. agenciai.pl (zwiad 16.08.2026, sama — WebFetch + HTML)

**Technologia:** WordPress + Elementor/OptimizePress + WooCommerce. Checkout na OSOBNEJ stronie `platforma-agencjai.pl` (WooCommerce `/zamowienie/?add-to-cart=...`).
**Co sprzedają:** kursy/szkolenia (Claude dla firm 997 zł netto i 2197 zł netto mentoring; AI Video Revolution 1497 zł netto). Ich podstrona nazywa się dosłownie `/sklep-agentow/` — ale to wystawka kursów, nie agentów.
**Przepływ:** strona sprzedażowa (landing per produkt) → przycisk → zewnętrzny checkout → po zapłacie: mail z dostępem do platformy + automatyczna faktura.

### CO BIERZEMY jako wzorzec
1. **Układ strony sprzedażowej produktu:** problem → rozwiązanie → co dostajesz (moduły/zawartość) → opinie → FAQ → gwarancja → CTA. Dokładnie pokrywa wymaganie „na zachętę klienta, jak działa, jak skraca czas pracy".
2. **Checkout ODDZIELONY od strony** — ich landing tylko linkuje do płatności. Potwierdza naszą architekturę: statyczna podstrona + Stripe Payment Link/Checkout na zewnątrz.
3. **Szczegóły prawne widoczne u nich:** „najniższa cena z 30 dni" przy promocji (dyrektywa Omnibus — obowiązek prawny w PL przy obniżkach!) · automatyczna faktura mailem · 14-dniowa gwarancja zwrotu · regulamin + polityka prywatności w stopce. → To wchodzi do NASZEJ checklisty sklepu.
4. Ceny podawane NETTO (B2B-owo) — u nas do decyzji: klient firmowy czy konsument (zmienia netto/brutto i prawo odstąpienia).

### CZEGO NIE bierzemy
- Ich silnika (WordPress+WooCommerce) — nasze strony docelowe to statyczny HTML, wymóg paczki wpinane/odpinane. WooCommerce się nie wpina w statyczny HTML.

## 2. GOTOWE PROJEKTY — polowanie 16.08 ~23:30 (drabina: gotowce przed planowaniem; na życzenie Dzidka)

**WERDYKT: gotowca 1:1 („wpinany sklep w statyczną stronę + własny panel + własna baza za 0 zł") NIE MA. Ale składanka potwierdzona z trzech stron:**

- ⭐ **zipsell** (github.com/yongfook/zipsell, 590★, MIT) — samodzielny sklepik na pliki cyfrowe. ARCHITEKTURA = nasz projekt 1:1: własne konto Stripe · pliki na PRYWATNYM magazynie (S3) · po zakupie mail z **podpisanym, WYGASAJĄCYM linkiem** (konfigurowalny czas ważności + LIMIT POBRAŃ) · panel `/admin`. Stack Ruby on Rails = NIE bierzemy kodu (wymaga serwera za pieniądze); bierzemy WZÓR. [Status aktywności repo: niepotwierdzony — nieistotne, bo bierzemy architekturę.]
- ⭐ **stripe-samples/checkout-one-time-payments** (oficjalny Stripe, MIT, żywy ~1000 commitów) — hosted Checkout + webhook „co zrobić po udanej płatności", frontend czysty HTML/JS. GOTOWIEC-FUNDAMENT płatności; przenosimy wzór na nasze zaplecze.
- **Polar** (polarsource/polar, 10 195★) — cała platforma sprzedaży cyfrowej jako usługa (Merchant of Record). ODPADA jako główna droga: panel i baza u NICH, wyższa prowizja — kłóci się ze spec kolegi (WŁASNA baza, WŁASNY panel, dziennik logowań). Zapisany jako plan-Z.
- Szablony Cloudflare+Stripe (SvelteKit itd.) istnieją — potwierdzają wykonalność stacku Workers+D1+R2+Stripe.

## 3. ŻYWE SKLEPY Z AGENTAMI/PROMPTAMI — wzorce rynku

- **godofprompt.ai** — bundle $199 (przekreślone $400, „lifetime"). Struktura strony produktu: wartość → co dostajesz → testimoniale (5000+ użytkowników) → pełna lista zawartości → 4 kroki → FAQ. Dostarczenie: DOSTĘP do przestrzeni Notion (produkt = dostęp, nie plik!). Checkout ukryty za CTA.
- **agenciai.pl** (PL) — patrz sekcja 1. Zbieżność struktury stron sprzedażowych z godofprompt = wzorzec potwierdzony ×2.
- **Marketplace'y** (PromptBase, FlowGPT, HighLevel dla agencji) — inny model (platforma zbiorcza), nie nasz przypadek (własny sklep na własnej stronie), ale potwierdzają: rynek sprzedaje „agentów" jako prompty+instrukcje+szablony, dokładnie jak nasze „instrukcje agenta, diagramy".

## 4. WZORZEC DOSTARCZENIA (synteza z 3 źródeł)

Mail po zakupie z **wygasającym podpisanym linkiem + limitem pobrań** (wzór zipsell) = najlepiej pasuje do naszej spec („po zakupie mail: potwierdzenie + instrukcje"). Alternatywy widziane na rynku: dostęp do platformy (agenciai), dostęp do Notion (godofprompt) — odnotowane, nie wybieramy bez Dzidka.

## TODO zwiadu (przed projektowaniem WIDOKU sklepu)
- Kryteria sędziego 0-100 dla strony sprzedażowej — z zebranych wzorców (godofprompt + agenciai + zasady Omnibus).
- Ewentualnie 1-2 sklepy stricte „chatbot do kupienia" gdy projektujemy opisy produktów.
