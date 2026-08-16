# TRIAGE krytyki 1 — moja weryfikacja uwaga-po-uwadze (17.08 ~01:20)

Zasada: nie bronię tezy; każdą uwagę sprawdziłam względem planu. Werdykt + co z nią robimy.
**Wynik: 17/17 przyjętych (2 częściowo, 0 odrzuconych).** Poprawki wejdą do PLANU v2 po zebraniu krytyk z innych modeli (zbieżność ≥2 = pewniak), żeby scalać RAZ.

1. **PRZYJĘTE (najważniejsza uwaga rundy).** Spike wpięcia w stronę hydrowaną = KROK 0, przed kością, na naszej kopii strony. Przy okazji sprawdzamy CSP strony-gospodarza (biała plama z końca krytyki).
2. **PRZYJĘTE.** Zgoda na natychmiastowe dostarczenie: do przepływu przed płatnością — na stronie produktu ALBO wbudowanym polem zgody Stripe Checkout (co prostsze — rozstrzygnie spike/kość). Trafia też do rubryki.
3. **PRZYJĘTE.** Rubryka GOTOWE dostaje punkty bezpieczeństwa: podmieniona cena w żądaniu = zignorowana · webhook bez ważnego podpisu = odrzucony · pobranie bez ważnego linku = odmowa · zakup bez zaznaczonej zgody = niemożliwy.
4. **PRZYJĘTE.** Sprzeczność Z5↔krok 1: weryfikacja sesji panelu przenosi się do przyrostu „panel".
5. **PRZYJĘTE.** Ochrona tokenu panelu do projektu (bramka D): własny CSP panelu, zakaz skryptów inline, token krótkiego życia w sessionStorage, wylogowanie po bezczynności.
6. **PRZYJĘTE.** Rozstrzygnięcie RODO↔historia: PSEUDONIMIZACJA (email → skrót nieodwracalny) zamiast kasowania wiersza — kwoty i czasy zostają do dashboardu, dane osobowe znikają. Dopisać do modelu danych.
7. **PRZYJĘTE CZĘŚCIOWO.** V1 NIE MA promocji/przecen → dopisujemy do MUST NOT; punkt Omnibus zostaje z adnotacją „dotyczy dopiero, gdy pojawią się przeceny" (nie budujemy historii cen na zapas).
8. **PRZYJĘTE.** Auto-retry maila: cron-trigger Workera co 10 min dociąga zamówienia `mail_blad` (do 3 prób), ręczny przycisk zostaje jako ostatnia deska.
9. **PRZYJĘTE (luka dokumentu).** Dopisać wprost: każdy właściciel = WŁASNE konto Stripe (tak było w ZROZUMIENIE.md; plan tego nie powtórzył — stąd słuszny zarzut).
10. **PRZYJĘTE.** Maszyna stanów zamówienia do projektu: `oplacone → mail_wyslany | mail_blad`; `mail_blad → (retry/ręcznie) → mail_wyslany`; każda zmiana stanu z czasem — prosty log zdarzeń zamówienia.
11. **PRZYJĘTE.** Kryterium ≤2 min przeformułować: mierzymy NASZĄ część (zaplecze przekazuje mail dostawcy ≤60 s od webhooka) + osobno test ręczny „mail realnie doszedł". SLA cudzego serwera poza rubryką.
12. **PRZYJĘTE.** Dopisać limity OPERACJI (mam liczby z oficjalnego cennika 16.08): D1 = 5 mln odczytów/dzień, 100 tys. zapisów/dzień; R2 = 1 mln operacji klasy A + 10 mln klasy B/mies. Nadal zapas tysiąckrotny — ale ma być w dokumencie, nie w mojej głowie.
13. **PRZYJĘTE CZĘŚCIOWO.** Nadawca maila per właściciel = JEGO zweryfikowany adres w JEGO koncie dostawcy (element configu wdrożenia). Ryzyko spamu bez domeny odnotowane w planie jako ograniczenie darmowego hostingu.
14. **PRZYJĘTE.** Kanał kontaktu przy błędzie = mail właściciela z config.js, pokazywany na stronie błędu.
15. **PRZYJĘTE.** Limit logowań: 5 prób / 15 min / IP, okno przesuwne, automatyczne odblokowanie — zero trwałego lockoutu admina.
16. **PRZYJĘTE.** Do wykazu stron witryny dochodzą: polityka prywatności + regulamin (szablony po polsku w paczce).
17. **PRZYJĘTE.** Upload pliku produktu przez presigned URL prosto do R2 (omija pułap rozmiaru żądania Workera); limit pliku 200 MB na start.

## Białe plamy — co z nimi
- **Termin egzaminu**: jedyne pytanie, które wolno zadać — DZIDKOWI (nie koledze): czy egzamin ma termin?
- Wroga sesja: ręczna + skrypty tam, gdzie się da (powtarzalne przypadki → testy automatyczne od razu).
- Makiety UX: powstaną przy przyroście „witryna", z wzorców z ZWIAD-WZORCE.md + sędzia 0-100 przed pokazaniem.
- Instrukcja wdrożenia: szkic powstanie razem z paczką; kryterium ≤30 min mierzone na CZYSTEJ kopii.
- CSP strony-gospodarza: sprawdzane w spike'u (krok 0).
