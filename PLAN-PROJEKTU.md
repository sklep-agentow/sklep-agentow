# PLAN PROJEKTU — „Sklep z agentami AI" (wpinana podstrona dla stron statycznych)

Wersja 1 do krytyki — 2026-08-17. Dokument celowo bez nazw i adresów (bezpieczny do pokazania na zewnątrz).

## 1. CO BUDUJEMY I PO CO

Podstrona-sklep sprzedająca **2 produkty cyfrowe** („agenty AI": ChatBot LIVE i Segregator maila — paczki z instrukcjami, diagramami, plikami do wdrożenia). Sklep ma być **wpinany i odpinany jak wtyczka** w istniejące strony STATYCZNE (czysty HTML na darmowym hostingu typu GitHub Pages — strona docelowa to statyczny eksport Next.js). Docelowo ta sama paczka wejdzie na 4 różne strony różnych właścicieli.

**Kontekst:** to zlecenie egzaminacyjne — ocenia je osoba trzecia. Zasady egzaminu: żadnych pytań do zlecającego (wszystkie decyzje podejmujemy sami i dokumentujemy z uzasadnieniem), całość na **trybie testowym płatności** (zero prawdziwych pieniędzy; przejście na produkcję = podmiana kluczy w konfiguracji, poza zakresem egzaminu).

**Wymagania ze zlecenia (dosłownie):** po zakupie mail do kupującego (potwierdzenie + instrukcje) · 2 różne produkty z cenami i opisem „na zachętę" (co robi, jak działa, ile czasu oszczędza) · panel admina: kiedy, za ile kupili, w jakim czasie (dashboard) · bazy danych: dane kupujących/płatności · produkty z pełnym dodawaniem/usuwaniem/modyfikowaniem · dziennik logowań na konto admina (kto, kiedy, konkrety).

**MUST NOT (świadomie poza zakresem):** kont/logowania kupujących NIE ma (zakup „z ulicy" na maila; jedno konto admina na stronę) · kursów NIE sprzedajemy · faktur NIE wystawiamy · plików produktów NIE trzymamy w publicznym repo · reszty strony-gospodarza NIE dotykamy · prawdziwych płatności NIE włączamy.

## 2. ARCHITEKTURA (dlaczego taka)

Strona statyczna nie wykona kodu serwerowego, więc sklep = **dwie części**:

**A. WITRYNA** — katalog `sklep/` (czysty HTML/CSS/JS, bez frameworka): lista produktów, strona produktu (układ wg wzorców rynku: problem → co dostajesz → opinie → FAQ → cena → przycisk), strona „dziękujemy", panel admina (aplikacja HTML). Do strony-gospodarza wpinana przez: skopiowanie katalogu + JEDEN punkt zaczepienia (linijka linku w menu albo dołączany skrypt dokładający pozycję menu — wybór po teście, bo strona-gospodarz jest hydrowana Reactem). `config.js` per strona: nazwa, adres zaplecza, klucz publiczny płatności.

**B. ZAPLECZE** — darmowy plan Cloudflare: **Worker** (API), **D1** (baza SQL), **R2** (prywatny magazyn plików produktów). Limity darmowe (potwierdzone w cenniku 16.08): 100 tys. żądań/dzień, baza 5 GB, magazyn 10 GB — zapas tysiąckrotny przy pojedynczych zakupach dziennie. Każdy właściciel strony = własne darmowe konto (izolacja danych i pieniędzy).

**C. PŁATNOŚCI** — Stripe Checkout (strona płatności HOSTOWANA przez Stripe; przekierowanie całej karty przeglądarki, nie skrypt osadzony — działa też z adblockiem; nasza strona nigdy nie dotyka danych kart). Tryb testowy: karty testowe, zero KYC. Wzorzec kodu: oficjalny, utrzymywany przykład stripe-samples/checkout-one-time-payments.

**D. MAILE** — wysyłka przez HTTP API (Worker nie zestawi SMTP): dostawca darmowy, wybór W KOŚCI (kandydat: Brevo — do 300 maili/dzień, pozwala wysyłać ze zweryfikowanego ADRESU bez własnej domeny; strony na darmowym hostingu domen nie mają). Mail = potwierdzenie + instrukcje + **wygasający, podpisany link do pobrania** (ważność 72 h, limit 5 pobrań — wzorzec z projektu zipsell).

## 3. PRZEPŁYW ZAKUPU

**Szczęśliwa ścieżka:** kupujący na `/sklep/` (witryna ciągnie produkty z API zaplecza) → klik „Kup" → witryna woła `POST /api/zamow {produkt_id}` → Worker tworzy sesję Stripe Checkout (cena Z BAZY, nie z żądania!) → przekierowanie na stronę Stripe → płatność testowa → Stripe wysyła webhook `checkout.session.completed` → Worker: weryfikuje PODPIS webhooka → sprawdza idempotencję (session_id UNIQUE — webhook wysłany 2× nie robi 2 zamówień) → zapisuje zamówienie (email z sesji, produkt, kwota, czas) → generuje wygasający link do pliku z R2 → wysyła mail → kupujący wraca na `/sklep/dziekujemy` („sprawdź maila").

**Odnogi i awarie:** przerwana płatność = brak webhooka = brak zamówienia (stan czysty) · mail nie wyszedł = zamówienie ma status „mail_blad", panel pokazuje je na czerwono z przyciskiem „wyślij ponownie" · webhook nie doszedł = Stripe sam ponawia do 3 dni; do decyzji w audycie czy dokładać ręczne „dociągnij zaległe" · API zaplecza leży = witryna pokazuje „sklep chwilowo niedostępny" zamiast pustej strony · link wygasł/limit pobrań = strona błędu z instrukcją kontaktu.

## 4. MODEL DANYCH (D1)

- `produkty`: id, nazwa, opis_krotki, opis_pelny, cena_grosze, waluta, plik_klucz_r2, aktywny, utworzono, zmieniono
- `zamowienia`: id, stripe_session_id (UNIQUE), produkt_id, email_kupujacego, kwota_grosze, waluta, status (oplacone / mail_wyslany / mail_blad), link_wygasa, pobrania_limit, pobrania_uzyte, utworzono
- `logowania_admina`: id, czas, wynik (ok/blad), ip, user_agent
- `konfig`: klucz, wartosc (hash hasła admina, ustawienia linków)

## 5. PANEL ADMINA

Logowanie jednym hasłem (hash w bazie, ustawiane przy instalacji) → token sesyjny z terminem ważności. **Każda próba logowania — udana i nieudana — do dziennika** (czas, IP, przeglądarka). Ochrona przed zgadywaniem hasła: limit prób na IP. Funkcje: dashboard sprzedaży (lista: kiedy/co/za ile + suma w czasie), pełny CRUD produktów (w tym wgranie pliku do R2), podgląd zamówień + ponowna wysyłka maila, odczyt dziennika logowań.

## 6. BEZPIECZEŃSTWO I PRAWO (checklist wbudowany w plan)

Podpis webhooka weryfikowany · idempotencja zamówień · pliki wyłącznie przez podpisane wygasające linki · hasło hashowane · limit prób logowania + dziennik · CORS zawężony do domeny witryny · sekrety w magazynie sekretów Workera, NIGDY w repo · cena zawsze z bazy (klient nie może wysłać własnej) · RODO minimum: zbieramy tylko email, polityka prywatności sklepu, możliwość usunięcia danych kupującego z panelu · regulamin sprzedaży + checkbox „zgadzam się na natychmiastowe dostarczenie treści cyfrowej" (utrata prawa odstąpienia — wymóg przy plikach) · przy przekreślonych cenach: „najniższa cena z 30 dni" (Omnibus).

## 7. PLAN PRACY (kolejność z bramkami)

1. **KOŚĆ** (najpierw!): 1 produkt w bazie → zakup testową kartą → webhook → mail z działającym wygasającym linkiem. Bez panelu. Kość przechodzi = kręgosłup potwierdzony.
2. Wroga sesja na kości: adblock, zamknięcie karty w trakcie, mail w spamie, podwójny klik, webhook 2×.
3. Przyrost „panel": logowanie + dziennik + dashboard (na danych z kości).
4. Przyrost „CRUD": zarządzanie produktami + upload pliku.
5. Przyrost „witryna": pełne strony produktów (opisy wg wzorców), wpięcie w KOPIĘ strony-gospodarza, test punktu zaczepienia menu.
6. Audyt końcowy: mutanty przez kalibrator, dosłowne wykonanie instrukcji na czystej kopii, próba obalenia.
7. Paczka + instrukcja wdrożenia prostym językiem (wpięcie ≤30 min) + demo dla egzaminatora.

Rytm: każdy przyrost = branch → test na żywych danych testowych → PR → merge; nie zaczynam następnego, póki poprzedni nie ma potwierdzonego wyniku.

## 8. REJESTR ZAŁOŻEŃ NIEPOTWIERDZONYCH (jawny)

- Z1. Symulacja BLIK w trybie testowym Stripe — do sprawdzenia w kości (karta testowa = pewnik).
- Z2. Darmowy plan Cloudflare a komercja — bez znaczenia dla egzaminu (tryb testowy); potwierdzić w warunkach przed produkcją.
- Z3. Punkt zaczepienia menu przy stronie hydrowanej Reactem — 2 warianty, wybór po teście na kości.
- Z4. Dostawca maili bez własnej domeny (kandydat Brevo) — potwierdzić w kości, że mail dochodzi na DOWOLNY adres kupującego.
- Z5. Panel i API na różnych domenach (hosting statyczny vs Worker) — sesja tokenem w nagłówku, nie cookie; potwierdzić wygodę w kości.

## 9. KRYTERIA „GOTOWE" (binarne, do rubryki egzaminu)

1. Zakup testową kartą: od kliknięcia „Kup" do maila z linkiem ≤ 2 minuty; link pobiera WŁAŚCIWY plik; po limicie pobrań/terminie przestaje działać.
2. Panel pokazuje ten zakup (czas, produkt, kwota) bez grzebania w kodzie.
3. Dodanie nowego produktu W PANELU = widoczny w sklepie bez zmiany kodu; edycja i usunięcie analogicznie.
4. Każde logowanie do panelu (udane i nieudane) jest w dzienniku z czasem i IP.
5. Webhook dostarczony dwukrotnie = JEDNO zamówienie i JEDEN mail.
6. Wpięcie paczki na czystą kopię innej strony statycznej wg instrukcji ≤ 30 minut; odpięcie wg instrukcji = zero śladu (czysty diff).
7. Cała powyższa lista przechodzi na ŚWIEŻYM wdrożeniu (nie na środowisku deweloperskim).
