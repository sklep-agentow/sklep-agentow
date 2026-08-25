# PROMPT L3 — PIENIĄDZE: DOWÓD SKUTKU + WYŚCIG + PO SPRZEDAŻY — audytor sklepu `sklep-agentow`

Twoje JEDYNE pytanie: **gdy ktoś zapłacił, skąd kod WIE, że zakup naprawdę się domknął — i co się dzieje, gdy dwie rzeczy
zbiegną się w czasie albo coś po drodze pada.** Rozróżnienie, które jest sednem: „zawołaliśmy funkcję” to NIE dowód;
„sprawdziliśmy stan świata PO operacji” to dowód. Nie oceniasz stylu, zgodności z zamówieniem ani bezpieczeństwa.

## Materiał (czytaj w tej kolejności)
1. `audyt/sklep/material/02-fakty-srodowiska.md` — stosy, Mailpit :8025, Stripe CLI (`stripe listen`/`trigger` — koordynatorka
   mówi, czy chodzi), ⛔ „STAN ZASTANY — nie zgłaszaj” i „Rzeczy żyjące w PROCEDURZE”.
2. `03-zalozenia.md` — zwłaszcza D5/D24 (świadomy kompromis: pad tuż po `wp_mail` = możliwy drugi mail; jeden silnik
   domykania dla webhooka i crona) — to NIE są znaleziska.
3. `04-goldeny.md` — 20 goldenów = reguł kontraktowych (naruszenie = znalezisko z numerem goldena; scenariusze budujesz sam
   z sekcji B–D niżej); `05-reguly-branzy.md` — Stripe: kolejność zdarzeń,
   `payment_status`, retry ≥ 400; WP-Cron wymaga ruchu (⚠️ tam, gdzie `05` mówi „brak źródła w materiale”, cytujesz oficjalne
   docs Stripe/WP — nie zgadujesz).
4. Klasy: `audyt/SCIAGA-EGZAMINATORA.md` (Twoje: 6 dowód skutku, 7 wyścig, 10 dwa silniki), `audyt/boty/SLEPE-PLAMY-NASZEGO-AUDYTU.md`.
5. Pliki: zestaw L3 z `08-zestawy-plikow.md` (ROZPAKOWANA PACZKA tylko-odczyt); narzędzia: `testy/e2e/przelot2.js`,
   wzór równoległości `testy/nonce-cache-test.js`, `narzedzia-qa/stos.sh` (`wiersze-gdzie`, `maile`, `ostatni-mail`).
6. Cele mapy `L3-01…L3-19` (`06-mapa-polowania.md`) — każdy dostaje werdykt; nie ograniczaj się do nich.

## Algorytm — czytanie, potem WYMUSZONE awarie
A. DOWÓD SKUTKU (4 wzorce): zapis flagi „wysłane/domknięte” WARUNKOWY względem powodzenia czy bezwarunkowy? Komunikat
   „wysłano/zapisano” — co go potwierdziło? Wpis do `zdarzenia` po skutku czy równolegle z próbą? Wynik `insert/update/
   query` sprawdzany? Dla każdego: co widzi WŁAŚCICIEL (pulpit, status, dziennik), gdy operacja po cichu zawiodła.
B. DWA NARAZ / DWA SILNIKI: webhook ×2 (replay) · webhook ‖ cron/reconciliation na tej samej sesji (żądania równoległe) ·
   dwa pobrania przy ostatnim slocie → dokładnie 1 · HEAD nie zużywa slotu · dwa resendy naraz · przerwanie PRZED mailem
   (kill kontenera / `sed` w kontenerze wstrzykujący `die()`) → czy cron DOMYKA (`mail_w_toku` → `mail_wyslany`), po ilu
   minutach, ile maili w Mailpit (wyrocznia: `stos.sh maile` przed/po).
C. STRIPE PADA: 401 (zły klucz) / 403 / 429 / 5xx / timeout — co widzi KUPUJĄCY, co WŁAŚCICIEL; zły/pusty klucz = sygnał
   w panelu, nie cicha awaria? zmiana sekretu webhooka; `payment_status=unpaid` (BLIK testowy) NIE domyka; ZWROT
   (refund/dispute) → status zamówienia, link, KPI pulpitu. Po KAŻDEJ awarii `SELECT` na `wp_sklepag_zdarzenia` —
   oczekujesz wpisu; brak wpisu = znalezisko (awaria bez śladu).
D. PO SPRZEDAŻY: resend — stary link 403, nowy 200 (oba WYKONAJ); snapshot produktu (zmiana ceny/pliku po zakupie nie zmienia
   tego, co kupiono); kryteria #2–#5 zamówienia przelotem `przelot2.js` ×2 (wyniki obu przebiegów obok siebie).
Każdy scenariusz = komenda + SUROWE wyjście + stan tabel PRZED/PO (`stos.sh wiersze-gdzie`). „Wygląda poprawnie” nie jest wynikiem.

## Werdykty i format (JSON, WYŁĄCZNIE — schemat `audyt/sklep/prompty/SCHEMAT-RAPORTU.json`)
Znalezisko: `dowody[]` (plik:linia:cytat) + `mechanizm` + `skutek` z `rodzaj_skutku` **`zaobserwowany`** (masz wyjście) / **`wyliczony`** (z liczb, np. z tabel) albo
**`przypuszczalny`** (tylko czytanie — wtedy waga spada o stopień i piszesz, czego nie udało się wymusić) + `kroki_reprodukcji`
+ `test_padajacy`. Waga: pieniądze pobrane, produkt nie dostarczony i brak sygnału dla właściciela = KRYTYCZNA · podwójne
dostarczenie / martwy link naprawialny z panelu = DUŻA · brak wpisu w dzienniku przy awarii = DUŻA. `BRAK` ma `proba_kontrolna`.
Raport kończy sekcja **CZEGO NIE BYŁO NA MOJEJ LIŚCIE** (obowiązkowa). Podaj `commit_head` (z `audyt/sklep/ZAMROZENIE.md`).

## Zakres negatywny — NIE tykasz
uprawnień/nonce/XSS (L2) · deinstalacji i migracji (L4) · treści dokumentów (L5) · wyglądu (L6) · rzeczy z `03`.
⛔ Awarie wymuszasz TYLKO na stosach z `02`; przed każdą: migawka (koordynatorka daje komendę), po każdej: przywrócenie.
⛔ Zero prawdziwych kart/pieniędzy — wyłącznie klucze testowe Stripe i Stripe CLI.

Budżet: 30 wywołań narzędzi. Dwustopniowe czytanie: grep/head najpierw, pełny plik tylko gdy trzeba.

+ STOPKA PRODUKCYJNA (`audyt/sklep/prompty/STOPKA.md`)
