# MAPA ZBIEŻNOŚCI — 5 krytyk planu v1 (nasz krytyk + modele A/B/C/D) · 17.08 ~03:50

Zasada przepisu: zbieżność ≥2 niezależnych = PEWNIAK (wchodzi do v2 bez dyskusji).
Unikaty oceniam po treści. Głosy: W=własny krytyk, A/B/C/D=obce modele.

## PEWNIAKI (posortowane po liczbie głosów)

1. **Wpięcie w hydrowany Next.js = ryzyko nr 1, testować NAJPIERW (spike)** — W1, A1, B32, C5, C6, D10 (6/5 przebiegów, bo C dał 2 uwagi). Dodatkowo C5: router Next przechwyci link → wymusić pełne przeładowanie; kopiowanie do ŹRÓDŁA (public/) vs artefaktu; „czysty diff" zdefiniować (źródło). → **DECYZJA: spike = krok 0 budowy; wynik spike'a decyduje o wariancie punktu zaczepienia; definicja odpięcia = diff ŹRÓDŁA strony + wyłączenie zaplecza.**
2. **Maile bez domeny nie dojdą / spam (DMARC)** — Z4 nasz, A2, C4, D6, B22 (5). → **DECYZJA: na egzamin tryb sandbox/zweryfikowane adresy + test na kilku dostawcach Z FOLDEREM SPAM; do planu jawny rozdział „droga do produkcji maili" (wymaga domeny; opcja: subdomena wykonawcy dla 4 instalacji — pytanie do właściciela, nie blokuje). Plan B dostawcy zapisany.**
3. **Omnibus/przekreślone ceny bez historii cen** — W7, A3, B28/47, C14, D1 (5). → **DECYZJA: v1 NIE MA przecen i przekreślonych cen (MUST NOT); punkt Omnibus z sekcji prawnej wykreślony jako martwy; wraca TYLKO z tabelą historii cen, jeśli klient kiedyś zechce promocji.**
4. **Token admina na originie gospodarza = XSS/kradzież** — W5, A4, B12, C21, D2, D7 (5). → **DECYZJA ARCHITEKTONICZNA: panel admina przenosimy NA ORIGIN WORKERA (Worker serwuje panel na swoim adresie). Zero wspólnego localStorage z gospodarzem, własny CSP, przewidywalny URL przestaje być częścią paczki wpinanej. Witryna sklepu zostaje na stronie gospodarza (ona nie ma sekretów).**
5. **Licznik pobrań: presigned R2 nie liczy; atomowość; skanery poczty** — B4/5, C11, C12, D19, A-białe (4). → **DECYZJA: pobranie ZAWSZE przez endpoint Workera (token HMAC w linku, strumieniowanie z R2), licznik = atomowy UPDATE z warunkiem; liczymy tylko pełne GET (HEAD/Range nie zużywa); limit podniesiony do 10; wygaśnięcie 72 h zostaje.**
6. **Idempotencja niepełna: przerwanie w połowie, brak wznowienia** — W (część), B6, C2, D3 (4). → **DECYZJA: webhook = INSERT ON CONFLICT DO NOTHING → odczyt stanu → WZNOWIENIE od miejsca zatrzymania (maszyna stanów: oczekuje→oplacone→mail_wyslany/mail_blad) → Worker zwraca błąd dopóki całość nie domknięta (retry Stripe DOKAŃCZA robotę, nie duplikuje). Statusy zapisywane PO pomiarze skutku (matka H).**
7. **Powtarzalna instalacja 4× / „≤30 min" źle mierzone** — B1/51, C16/17, A-białe, D15 (4). → **DECYZJA: skrypt instalacyjny zaplecza (wrangler: D1+migracje+seed+sekrety+deploy) + ROZDZIELENIE miar: „zestawienie zaplecza" (jednorazowe, mierzone osobno, z listą kont) vs „wpięcie witryny" (≤30 min); endpoint /api/wersja + wersja w panelu; test instrukcji wykonuje WŁAŚCICIEL (nie autor); test po wdrożeniu: „ta strona→ten backend→ten Stripe" (B35).**
8. **Snapshot produktu w zamówieniu + soft-delete + wersja pliku** — nasza kartka MP, B9/10/11/28/30, A6 (4). → **DECYZJA: zamówienie kopiuje nazwę+cenę+klucz pliku (wersję) w chwili zakupu; produkt z zamówieniami tylko DEAKTYWOWANY; plik wersjonowany kluczem (nowy plik = nowy klucz, stare zamówienia trzymają swój).**
9. **„Kto" w dzienniku + brak logu akcji admina** — W (częściowo), B17/18, C9 (3). → **DECYZJA: pole „login" przy logowaniu (zapisujemy też wpisaną wartość przy nieudanej próbie); log zdarzeń append-only rozszerzony o AKCJE admina (CRUD, resend, eksport); jawna decyzja w docs: jedno konto, dlaczego.**
10. **POST /api/zamow: ślad sesji + podwójny klik + rate limit** — B19/20, C1, D9 (3). → **DECYZJA: tabela sesji checkout (zapis PRZED przekierowaniem — ślad po naszej stronie); klucz idempotencji na klik (60 s okno); limit żądań na IP na publicznych endpointach; strona dziękujemy ODPYTUJE status po session_id (rozwiązuje też A7/B39 race „sprawdź maila").**
11. **„Produkcja = podmiana kluczy" to fałszywa obietnica** — B2/3, C3 (3, w tym VAT/OSS!). → **DECYZJA: sekcja planu przepisana na „CO ODDZIELA TRYB TESTOWY OD PRODUKCJI" (KYC, VAT-OSS przy konsumentach UE, domena, webhook produkcyjny, regulamin per sprzedawca, warunki CF) — jawna lista, żadnych obietnic; baner „TRYB TESTOWY" w interfejsie (C27).**
12. **XSS/CSRF/walidacja: opisy produktów, upload, CORS≠auth** — B13/14/15/16, C21, D20 (3). → **DECYZJA: opisy renderowane jako TEKST (zero HTML z bazy); upload: limit rozmiaru+MIME+losowy klucz (presigned PUT — omija limit Workera, A5/C26); każdy endpoint admin z własną autoryzacją (CORS to nie kontrola dostępu); token krótkiego życia, unieważnianie po stronie serwera, wylogowanie kasuje sesję w bazie.**
13. **Backup/eksport/retencja** — B24/42/43, C19/22, D11/12 (3). → **DECYZJA: eksport CSV zamówień w panelu; skrypt backupu D1 (wrangler export) w instrukcji; retencja: logi 90 dni auto-czyszczone, pseudonimizacja email na żądanie (email→skrót + usunieto_dnia), polityka wymienia Stripe/dostawcę maili jako procesorów.**
14. **Wroga sesja/testy za wąskie** — W (część), B39/40/48/49/50, C20, D17 (3). → **DECYZJA: do testów dochodzą: pusta baza · nieprawidłowe dane produktu · odświeżenie po sukcesie · „A kupił, B dostał link" (świadoma granica: link = okaziciel z wygaśnięciem i limitem — zapisana decyzja) · webhook w trakcie niedokończonego zapisu (Miniflare + stripe trigger) · seed danych startowych zdefiniowany.**
15. **Testy automatyczne + mutanty mają czego czerwienić** — C15/20, B38, D17/18 (3). → **DECYZJA: od kości istnieją testy automatyczne (Miniflare/wrangler dev + stripe CLI); kalibrator sadzi mutanty W KODZIE i wymaga czerwonego TESTU (jak w v3); wyrocznię klienta zastępuje: notatka kolegi + kartka-styl MP + scenariusze kupującego (jawnie zapisane w rubryce).**

## UNIKATY PRZYJĘTE (1 głos, ale trafiają w punkt)

- **C7: w planie NIE MA wytworzenia samych PRODUKTÓW** (treści paczek!). → nowy krok:
  „produkty-treść" (2 paczki: instrukcje+diagramy+pliki, z kryterium jakości), zanim
  witryna dostanie opisy. Egzaminator najpierw otworzy pliki, nie kod.
- **C8: opinie = sprzeczność z prawem** → sekcja „opinie" WYCIĘTA z układu strony
  (zastąpiona „dla kogo jest ten agent"). MUST NOT: żadnych zmyślonych opinii.
- **C10: endpoint pierwszego hasła musi się sam zamykać** → hasło ustawiane W SKRYPCIE
  instalacyjnym (sekret Workera/seed), NIE przez publiczny endpoint.
- **C13: wzorzec Stripe jest na Node, nie Workers** → wzorcem zostaje oficjalny przewodnik
  Stripe/Cloudflare dla Workers (constructEventAsync/Web Crypto); zapis w planie poprawiony.
- **C29: sekret CLI ≠ sekret endpointu** → do checklisty świeżego wdrożenia.
- **C28: stopka reklamowa darmowego Brevo** → sprawdzić w kości; jeśli jest — zmiana
  dostawcy (kandydat zapasowy: Resend sandbox na egzamin).
- **D8: walidacja istnienia pliku w R2 przed aktywacją produktu** → przyjęte (aktywacja
  wymaga potwierdzonego pliku).
- **B45: semantyka czasu zakupu** → czas zakupu = czas webhooka potwierdzającego płatność
  (zapis w słowniku danych).
- **A-białe: izolacja CSS** → prefiks `sk-` + reset w kontenerze sklepu (spike sprawdza).

## ODRZUCONE / ŚWIADOMIE POZA v1 (z uzasadnieniem — lekcja zakresu z retrospektywy!)

- **B25/D: monitoring/alerting Workera** → poza v1 (panel-czerwone + cron-retry + Stripe
  retry wystarczą na egzamin; alerting = „miłe", wraca przy produkcji).
- **D14: samoobsługowe ponowne wysłanie linku przez kupującego** → poza v1 (kanał
  kontaktu właściciela na stronie błędu; self-service otwiera enumerację adresów).
- **D13/B8: pełna maszyna zdarzeń Stripe (async, dispute)** → poza v1: egzamin = karty
  testowe (płatność synchroniczna); jawna decyzja w rejestrze + obsłużony expired.
- **C21: Turnstile na logowaniu** → poza v1 (opóźnienie wykładnicze + dziennik + długie
  hasło); Turnstile wraca przy produkcji.
- **B36: multi-tenant site_id** → odrzucone na stałe: izolacja per konto właściciela
  jest ŚWIADOMĄ architekturą (RODO, kasa, prostota) — potwierdzona, nie przeoczona.

## CO TA RUNDA MÓWI O NASZYM KRYTYKU
Nasz krytyk złapał 3 z 6 największych pewniaków (spike, Omnibus, token/XSS) i przegapił:
niepełną idempotencję (C2/B6), licznik-pobrań-vs-presigned (C11) i brak treści produktów
(C7). Wniosek na przyszłość: krytyk techniczny + krytyk „czego brakuje w OGÓLE" to dwie
różne soczewki — następnym razem odpalać obie.
