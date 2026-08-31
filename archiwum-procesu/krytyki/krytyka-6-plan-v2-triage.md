# KRYTYKA 6 — świeży model na PLAN v2 (17.08 ~04:40) + mój triage

Zapis: 16 uwag (2 KRYT / 10 WAŻ / 4 DROB) + białe plamy. Pełny tekst w wiadomości właściciela
(z pominięciem wycieku brudnopisu modelu). Poniżej każda uwaga + werdykt.

## KRYTYCZNE

**K1. Sprzeczność: statyczna witryna + SEO vs „nowy produkt bez zmian kodu".** → **TRAFIONA.**
Rozstrzygnięcie (priorytet ma wymóg FUNKCJONALNY z notatki zlecenia — CRUD; SEO to nasz
dodatek): witryna renderuje produkty Z API (spełnia CRUD), a SEO-minimum ŚWIADOMIE
OGRANICZONE — meta/OG statyczne ogólne, JSON-LD i treść generowane skryptem (współczesny
Google wykonuje JS; pozycjonowanie i tak poza zakresem). Decyzja jawna w planie v2.1.

**K2. Limit CPU darmowego Workera niepotwierdzony przed architekturą.** → **CZĘŚCIOWO.**
Fakt z cennika (sprawdzony 16.08): 10 ms CPU/wywołanie — ale to czas PROCESORA, nie zegara:
czekanie na D1/R2/fetch się NIE liczy; HMAC na małym payloadzie to ułamki ms; strumień R2
przepływa bez pracy CPU. Typowe API-klej mieści się z zapasem. Ryzyko realne, ale niskie —
pomiar w K1 zostaje (pass^k 3/3 wykaże), architektura bez zmian.

## WAŻNE

**W3. Idempotencja maila: padnięcie PO wysyłce, PRZED zapisem statusu = duplikat.** →
**TRAFIONA (subtelna).** Fix: status `mail_w_toku` przed wysyłką + klucz idempotencji
dostawcy (id zamówienia), a okno duplikatu przy padnięciu tuż po wysyłce — ŚWIADOMIE
zaakceptowane (lepszy podwójny mail potwierdzenia niż żaden); test pokrywa OBA punkty
przerwania (przed i po wysyłce).

**W4. Produkty-treść bez wczesnej bramki.** → **PRZYJĘTA.** Kamień: szkic obu paczek do
19.08, recenzja treści przez właściciela PRZED K5 (witryną).

**W5. Plan nie-samodzielny (odwołania C7/B11...).** → **CZĘŚCIOWO.** To dokument roboczy
warsztatu — odwołania zostają (mapa jest w repo obok). Dokumentacja DLA EGZAMINATORA (K8)
pisana od zera, samodzielna, bez tych kodów — już w planie.

**W6. Wariant B (MutationObserver) traktowany równorzędnie, a jest kruchy.** → **PRZYJĘTA.**
Spike z PREFERENCJĄ wariantu A (link w źródle); B tylko fallback z jawnym ostrzeżeniem.

**W7. Jeden spike ≠ cztery strony.** → **CZĘŚCIOWO.** Znamy dziś JEDNĄ stronę docelową
(egzaminacyjną) — pozostałe 3 to przyszłość bez materiału. Nowe założenie Z8: mechanizm
potwierdzony na stronie egzaminacyjnej; każda kolejna dostaje checklistę kompatybilności
(CSP/router/ścieżki) przy wdrożeniu.

**W8. „Pełny GET" przy streamingu niewykrywalny.** → **PRZYJĘTA (upraszczamy uczciwie).**
Licznik atomowo PRZY ROZPOCZĘCIU pobrania (pełności transferu nie da się wiarygodnie
stwierdzić); HEAD nie zużywa; serwujemy bez Accept-Ranges; limit 10 właśnie jako zapas.

**W9. Brak procesu aktualizacji 4 instalacji.** → **CZĘŚCIOWO.** API wersjonowane ścieżką
(/v1/) + CHANGELOG + /api/wersja; PEŁNY proces aktualizacji = po egzaminie (produkcja),
zapisane jako granica zakresu.

**W10. CORS/CSP hostów.** → już w spike'u (K0) + wpis do instrukcji wpięcia (jeśli host ma
CSP — wymagane wyjątki wypisane).

**W11. Cookie/CSRF niedopisane.** → **PRZYJĘTA.** Panel i API są na TYM SAMYM originie
Workera → sesja = cookie HttpOnly+Secure+SameSite=Strict; mutacje dodatkowo wymagają
niestandardowego nagłówka.

**W12. RODO: hash emaila to nadal dane osobowe; brak DPA.** → **PRZYJĘTA.** Anonimizacja:
email → NULL + usunieto_dnia (zamiast skrótu); instrukcja właściciela: akceptacja
standardowych umów powierzenia (DPA) u operatora płatności i dostawcy maili.

## DROBNE

**D13. K1 za ciasny.** → CZĘŚCIOWO: tempo AI + kość jest mała; elastyczność: K1 może pożyczyć
pół dnia z K3 bez ruszania próby 27.08.
**D14. Bank mutantów = przerost.** → **ODRZUCONA z dowodem:** przy MP audyty bez kalibracji
dały „zero znalezisk" i były bezwartościowe (zmierzone 4.08); kalibracja kosztuje ~30 min
i jest jedynym pomiarem, czy audyt w ogóle widzi. Zostaje.
**D15. Zależność od właściciela.** → **ODRZUCONA:** bramka ludzka to ŚWIADOMA zasada procesu
(jedyne wyjście z pętli „agent sam siebie sprawdza"); właściciel jest stroną projektu, nie
ryzykiem zewnętrznym.
**D16. Range nieobsłużony jawnie.** → PRZYJĘTA: serwujemy bez Accept-Ranges (jawnie).

## Werdykt rundy 2
2/2 krytyczne trafione w realne dziury dokumentu (SEO-vs-CRUD, doprecyzowany duplikat
maila) — obie tanie do naprawy na papierze, obie i tak wyszłyby w kości. Poprawki → v2.1.
PEŁNA runda krytyki nr 3 = po kości, na projekcie technicznym (bramka D), zgodnie z procesem.
