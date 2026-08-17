# PLAN PROJEKTU v2 — „Sklep z agentami AI" (wpinana podstrona dla stron statycznych)

Wersja 2.1 — 2026-08-17 (v2 + runda 2: krytyka 6, triage w krytyki/), po rundzie krytyki: 5 niezależnych recenzji (własny krytyk + 4 obce
modele), mapa zbieżności w `krytyki/MAPA-ZBIEZNOSCI.md`. Dokument bez nazw i adresów.
**Termin egzaminu: 30.08 · 3 próby oddania · pierwsza próba planowana 27.08 (bufor na zwroty).**

## 1. CO BUDUJEMY I PO CO

Podstrona-sklep sprzedająca **2 produkty cyfrowe** („agenty AI": ChatBot LIVE i Segregator
maila — paczki z instrukcjami, diagramami i plikami do wdrożenia). Sklep **wpinany i odpinany**
w istniejące strony statyczne (GitHub Pages; strona docelowa = statyczny eksport Next.js),
docelowo na 4 strony różnych właścicieli. Zlecenie egzaminacyjne: ocenia osoba trzecia,
**zero pytań do zlecającego** (decyzje własne, dokumentowane), całość w **trybie testowym
płatności**.

⭐ **NOWE względem v1 (krytyka C7):** zakres obejmuje też **WYTWORZENIE SAMYCH PRODUKTÓW**
(treść 2 paczek: instrukcje, diagramy, pliki) z własnym kryterium jakości — sklep nie może
sprzedawać pustki; egzaminator najpierw otworzy pliki, nie kod.

## 2. WYMAGANIA (siatka: FUNCTIONAL / NON-FUNCTIONAL / SCHEMA)

**FUNCTIONAL (z notatki zlecenia, dosłownie):** po zakupie mail (potwierdzenie + instrukcje) ·
2 produkty z cenami i opisem „na zachętę" · panel admina: kiedy/za ile kupili/w jakim czasie
(dashboard) · baza: dane kupujących/płatności · produkty z pełnym dodawaniem/usuwaniem/
modyfikowaniem · dziennik logowań admina (kto, kiedy, konkrety).
Interpretacje zapisane w MAPA-SPEC (moment 1 kadencji): „w jakim czasie" = rozkład zakupów
w czasie (sumy w okresach) · „czas zakupu" = czas webhooka potwierdzającego płatność ·
„kto" = pole login wpisywane przy logowaniu (jedno konto; przy nieudanej próbie zapisujemy
wpisaną wartość) — decyzje z uzasadnieniem, nie zgadywanie.

**NON-FUNCTIONAL (standardy T1-T7 z poprzedniego zlecenia + prawo):** bezpieczeństwo (sekcja 7) ·
RODO (sekcja 8) · dostępność WCAG-lite: labelki, kontrast, klawiatura+fokus, aria (EAA) ·
Chrome/Edge/Firefox + pełny RWD (zakup z telefonu) · **SEO-minimum ŚWIADOMIE OGRANICZONE**
(konflikt z wymogiem „nowy produkt bez zmian kodu" — priorytet ma wymóg FUNKCJONALNY z notatki):
witryna renderuje produkty Z API; meta/OpenGraph statyczne ogólne per podstrona, JSON-LD i treść
produktów generowane skryptem (współczesne wyszukiwarki wykonują JS; pozycjonowanie poza
zakresem); **demo-kopia strony gospodarza = noindex** · szybkość: strona
statyczna + API bez N+1 · spójny styl kodu (eslint/prettier w CI; zakaz `debugger` i gołych
`console.log`).

**SCHEMA:** sekcja 5.

## 3. MUST NOT (świadomie poza zakresem — łapie nadgorliwość i lekcję „16 tabel zamiast 4")

Kont/logowania kupujących NIE ma · kursów NIE sprzedajemy · faktur NIE wystawiamy · plików
produktów NIE trzymamy publicznie · reszty strony-gospodarza NIE dotykamy · prawdziwych
płatności NIE włączamy · **przecen i przekreślonych cen NIE MA** (Omnibus-historia-cen wraca
tylko na wyraźne życzenie, z tabelą historii) · **sekcji „opinie" NIE MA** (zero zmyślonych
opinii — prawo; zamiast tego „dla kogo jest ten agent") · samoobsługowego ponownego wysłania
linku NIE ma (kanał kontaktu właściciela) · monitoringu/alertingu NIE ma w v1 (panel + retry
wystarczą; wraca przy produkcji) · płatności asynchronicznych NIE obsługujemy (egzamin = karty
testowe; obsłużone: completed + expired) · multi-tenant NIE ma (izolacja per konto właściciela
to świadoma architektura).

## 4. ARCHITEKTURA v2 (zmiany wytłuszczone)

**A. WITRYNA** — katalog `sklep/` (czysty HTML/CSS/JS): lista produktów, strona produktu
(układ: problem → co dostajesz → dla kogo → FAQ → cena → przycisk), strona „dziękujemy"
**odpytująca status zamówienia po id sesji** (koniec z „sprawdź maila" w ciemno).
**Izolacja CSS: prefiks klas + reset w kontenerze.** `config.js` per strona — WYŁĄCZNIE dane
publiczne (nazwa, adres zaplecza, klucz publiczny płatności, mail kontaktowy właściciela,
dane sprzedawcy do regulaminu). **Baner „TRYB TESTOWY — płatności nie są pobierane".**

**B. PANEL ADMINA — NA ORIGINIE WORKERA, nie na stronie gospodarza** (pewniak #4: wspólny
origin = kradzież tokena przez skrypty gospodarza). Worker serwuje panel na własnym adresie:
własny CSP, zero wspólnego magazynu przeglądarki z hostem, adres panelu poza paczką wpinaną.

**C. ZAPLECZE** — Cloudflare Worker + D1 + R2 (limity darmowe z cennika 16.08: 100 tys.
żądań/dzień · D1 5 GB, 5 mln odczytów/dzień, 100 tys. zapisów/dzień · R2 10 GB, 1 mln op. A
i 10 mln op. B/mies. — przy pojedynczych zakupach dziennie zapas tysiąckrotny; **do
potwierdzenia w kości limity CPU/pamięci wolnego planu** — rejestr założeń). Każdy właściciel =
własne konto (izolacja danych i pieniędzy) + **własne konto Stripe** (kasa na jego rachunek).
**Instalacja zaplecza = JEDEN skrypt** (tworzy D1 + migracje + seed + sekrety + deploy +
hasło admina; hasło ustawiane przez skrypt/seed, NIGDY przez publiczny endpoint — C10).
**`/api/wersja`** zwraca wersję paczki i stan schematu; **API wersjonowane ścieżką (`/v1/`)** —
przyszłe aktualizacje zaplecza nie łamią starych witryn (pełny proces aktualizacji 4 instalacji =
po egzaminie, jawna granica zakresu).

**D. PŁATNOŚCI** — Stripe Checkout (hostowana strona płatności; pełne przekierowanie —
odporne na adblock; kart nie dotykamy). **Wzorzec kodu: oficjalny przewodnik Stripe dla
Cloudflare Workers** (constructEventAsync/Web Crypto — NIE przykład Node/Express; C13).
Tryb testowy; sekret webhooka per środowisko (CLI ≠ endpoint — do checklisty świeżego
wdrożenia).

**E. POBRANIA — ZAWSZE przez endpoint Workera** (pewniak #5): link = token HMAC z terminem
(72 h), Worker strumieniuje plik z R2; licznik = **atomowy UPDATE z warunkiem PRZY ROZPOCZĘCIU
pobrania GET** (pełności transferu nie da się wiarygodnie wykryć przy streamingu — decyzja
jawna; dlatego limit 10 jako zapas). HEAD nie zużywa limitu (skanery poczty; C12); serwujemy
**bez Accept-Ranges** (jawnie). Presigned URL bezpośrednio do R2 — NIE (nie umie liczyć).

**F. MAILE** — na egzamin: dostawca w trybie testowym/zweryfikowane adresy + **podgląd treści
maila w panelu przy zamówieniu** (egzaminator ZOBACZY mail bez skrzynki). Test dostarczalności
na kilku dostawcach Z folderem spam. **Jawny rozdział „droga do produkcji": wysyłka
produkcyjna WYMAGA domeny** (opcja: jedna subdomena nadawcza dla 4 instalacji — decyzja
z właścicielami; nie blokuje egzaminu). Sprawdzić stopkę reklamową darmowego planu (C28) —
jeśli jest, zmiana dostawcy.

## 5. MODEL DANYCH v2 (D1)

- `produkty`: id, nazwa, opis_krotki, opis_pelny (renderowane jako TEKST, nigdy HTML),
  cena_grosze, waluta, plik_klucz_r2, plik_rozmiar, plik_sha256, aktywny, utworzono, zmieniono.
  **Usuwanie = deaktywacja** gdy istnieją zamówienia; plik nowej wersji = NOWY klucz
  (stare zamówienia trzymają swój — wersjonowanie B11).
- `sesje_checkout` ⭐NOWA (C1): id, stripe_session_id, produkt_id, utworzono, stan
  (utworzona/oplacona/wygasla) — ślad po NASZEJ stronie zanim przyjdzie webhook; podstawa
  „dociągnij zaległe" i testu porzuconych.
- `zamowienia`: id, stripe_session_id UNIQUE, produkt_id, **produkt_nazwa_kupiona,
  produkt_cena_kupiona, produkt_plik_klucz** (snapshot z chwili zakupu), email_kupujacego,
  kwota_grosze, waluta, **status: oczekuje → oplacone → mail_wyslany | mail_blad**,
  **zgoda_tresc, zgoda_czas** (dowód checkboxa per zamówienie — B41), link_wygasa,
  pobrania_limit, pobrania_uzyte, mail_proby, utworzono (=czas webhooka), zmieniono.
- `zdarzenia` ⭐NOWA (kartka MP: nieusuwalna oś czasu): id, zamowienie_id NULL, typ, opis,
  czas — append-only; obejmuje TAKŻE akcje admina (CRUD, resend, eksport, logowanie).
- `logowania_admina`: id, czas, **login_wpisany**, wynik, ip, user_agent.
- `konfig`: klucz, wartosc (hash hasła, wersja schematu; ustawienia NOWYCH linków —
  jawne w panelu, że nie zmieniają już wystawionych; C25).
Retencja: logi logowań i zdarzenia techniczne czyszczone po 90 dniach (cron); anonimizacja
kupującego na żądanie: **email → NULL + usunieto_dnia** (skrót odpada — to nadal dana osobowa;
kwoty i czasy zostają — dashboard żyje).

## 6. PRZEPŁYW ZAKUPU v2

Strona produktu: **checkbox zgody na natychmiastowe dostarczenie (wymagany)** → „Kup" →
`POST /api/zamow {produkt_id, zgoda:true, klucz_idempotencji}` (60-sekundowe okno — podwójny
klik nie tworzy 2 sesji; B20) → Worker: walidacja produktu AKTYWNEGO **z potwierdzonym
plikiem w R2** (D8), zapis do `sesje_checkout`, sesja Stripe (cena z bazy) → przekierowanie →
płatność testowa → **webhook**: weryfikacja podpisu → `INSERT ... ON CONFLICT DO NOTHING` →
**odczyt stanu i WZNOWIENIE od miejsca zatrzymania** (snapshot → link → status `mail_w_toku`
PRZED wysyłką → mail z kluczem idempotencji dostawcy (id zamówienia) → status po POMIARZE
skutku; Worker zwraca błąd, dopóki całość niedomknięta — ponowienia Stripe DOKAŃCZAJĄ robotę
zamiast duplikować; pewniak #6). Okno duplikatu maila przy padnięciu tuż PO wysyłce a przed
zapisem statusu: ŚWIADOMIE zaakceptowane (lepszy podwójny mail potwierdzenia niż żaden);
test pokrywa OBA punkty przerwania. → strona „dziękujemy" odpytuje status i pokazuje
„mail wysłany" / „przetwarzamy".
**Awarie:** mail_blad → cron co 10 min, do 3 prób, potem panel-czerwone + ręczny przycisk ·
sesja porzucona → widoczna w `sesje_checkout` · API leży → witryna: komunikat · link
wygasł/limit → strona błędu z mailem kontaktowym właściciela (z config).

## 7. BEZPIECZEŃSTWO v2 (checklist → każda pozycja ma test w swoim przyrocie)

Podpis webhooka (Web Crypto, constructEventAsync) · idempotencja pełna (ON CONFLICT + resume) ·
cena wyłącznie z bazy · **każdy endpoint admina z własną autoryzacją** (CORS ≠ kontrola
dostępu; B13) · sesje admina: **cookie HttpOnly+Secure+SameSite=Strict** (panel i API na tym samym originie
Workera), rekord po stronie serwera, krótkie życie, unieważnianie przy wylogowaniu, mutacje
dodatkowo wymagają niestandardowego nagłówka (CSRF), **zmiana hasła w panelu** + procedura
resetu skryptem (D4/B23) · limit prób
logowania: opóźnienie wykładnicze + dziennik (Turnstile = produkcja) · **rate limit na
publicznych endpointach** (B19) · opisy/nazwy renderowane jako tekst (XSS; B15) · upload:
presigned PUT do R2 (limit Workera ominięty; A5), limit rozmiaru + MIME + losowy klucz,
weryfikacja obecności PO wgraniu (matka H) · linki pobrań: HMAC + wygaśnięcie + atomowy
licznik pełnych GET · sekrety wyłącznie w magazynie Workera; config.js tylko publiczne (B33) ·
zakaz logowania sekretów (przegląd przed oddaniem) · CSP panelu (osobny origin) · gitleaks +
CodeQL/Dependabot w CI.

## 8. PRAWO / RODO v2

Zgoda na natychmiastowe dostarczenie zapisywana per zamówienie (treść + czas) · regulamin
i polityka prywatności jako SZABLONY wypełniane danymi sprzedawcy z config (paczka nie
publikuje cudzego regulaminu; C18) · minimalizacja: tylko email · anonimizacja na żądanie
(email→NULL) · instrukcja właściciela: akceptacja standardowych umów powierzenia (DPA)
u operatora płatności i dostawcy maili ·
retencja logów 90 dni · polityka wymienia operatora płatności i dostawcę maili jako podmioty
przetwarzające · **bez przecen → Omnibus-ceny nie dotyczy (jawnie)** · bez opinii → Omnibus-
opinie nie dotyczy (jawnie) · WCAG-lite (EAA).

## 9. PLAN PRACY v2 (harmonogram do terminu; POMIAR czasu per krok)

- **K0 · 17-18.08 — SPIKE WPIĘCIA (ryzyko nr 1 najpierw; pewniak #1):** na kopii strony
  gospodarza: **wariant A (link w menu w ŹRÓDLE/public) — PREFEROWANY**; B (skrypt +
  MutationObserver) tylko jako fallback z jawnym ostrzeżeniem o kruchości;
  test hydracji, routera (pełne przeładowanie na /sklep/), CSP, wycieków CSS, zachowania po
  rebuildzie. **Wynik spike'a = decyzja mechanizmu wpięcia i definicja „czystego diffu"
  (diff ŹRÓDŁA + wyłączenie zaplecza).** Równolegle startują **PRODUKTY-TREŚĆ** (2 paczki;
  kryterium: kompletna instrukcja wdrożenia agenta + diagram + pliki; „ile czasu oszczędza"
  poparte treścią, nie zmyślone). **Bramka treści: szkic OBU paczek do 19.08, recenzja treści
  przez Dzidka PRZED K5** — egzaminator najpierw otworzy pliki, nie kod.
- **K1 · 18-19.08 — KOŚĆ + TESTY OD RAZU:** skrypt instalacyjny zaplecza → 1 produkt (seed) →
  zakup kartą testową → webhook (ON CONFLICT + resume) → mail → pobranie przez Worker.
  Testy automatyczne od pierwszego dnia (wrangler dev/Miniflare + stripe CLI): webhook 2×,
  przerwanie przed mailem + ponowienie dokańcza, podmiana ceny, licznik równoległy.
  **Golden master przelotu** (zapis odpowiedzi/stanów/treści maila — regresja za darmo).
- **K2 · 19.08 — WROGA SESJA na kości:** adblock, zamknięcie karty, spam-folder, podwójny
  klik, webhook 2×, pusta baza, seed od zera, odświeżenie „dziękujemy", link po limicie,
  HEAD/Range, plik usunięty z R2. Audyt pierwszego przelotu: sesje po pytaniu A/B/B2/C/D
  (E-migracje: N/D w v1 — zapisane).
- **K3 · 20-21.08 — PANEL (na Workerze):** logowanie (login+hasło, dziennik z loginem),
  dashboard (lista + sumy w czasie + eksport CSV), podgląd zamówienia Z TREŚCIĄ maila,
  resend, log zdarzeń (w tym akcje admina), zmiana hasła.
- **K4 · 22.08 — CRUD:** produkty + presigned upload + walidacje (typ/rozmiar/cena>0/pusta
  nazwa) + deaktywacja zamiast usuwania + snapshot w zamówieniach nietknięty (test).
- **K5 · 23-24.08 — WITRYNA:** strony produktów NA TREŚCI z produktów-paczek, WCAG-lite,
  RWD, JSON-LD, baner trybu testowego, regulamin/polityka z szablonów; sędzia wyglądu 0-100
  wg kryteriów dziedziny PRZED pokazaniem.
- **K6 · 25.08 — POWTARZALNOŚĆ (pewniak #7/B51):** DRUGA instalacja od zera na czystej kopii
  innej strony wg instrukcji — wykonuje NIE-autor (Dzidek): „zestawienie zaplecza" (mierzone
  osobno, z listą kont) + „wpięcie witryny" ≤30 min; test „ta strona→ten backend→ten Stripe".
- **K7 · 26.08 — AUDYT KOŃCOWY:** 4 kontrole równolegle (zgodność z MAPA-SPEC · klikanie+
  wygląd+axe · paczka i repo (gitleaks, instalacja od zera, sweep śmieci .bak/.orig/lorem) ·
  bezpieczeństwo ATAKUJE — konkretne polecenia omijające walidacje) + **kalibracja: bank
  mutantów projektu** (5 klas zwróconych przy MP + wyścig + limit-mierzy-nie-to; mutanty
  zacommitowane na neutralnej gałęzi, zakaz narzędzi historii, próg 2/3) + krytyczny przelot
  **3/3 (pass^k)** + próba obalenia (osobna sesja: „udowodnij, że NIE nadaje się do wydania").
  Naprawy KLASAMI (grep całe repo; >5 plików = porcje z zielonym CI; 3. powrót klasy = STOP).
- **K8 · 27.08 — PACZKA + PRÓBA ODDANIA nr 1:** zamrożenie → docs Z KODU (dwupoziomowe:
  klient prostym językiem + zrzuty; informatyk: diagram architektury) → JEDNO wydanie (tag,
  suma kontrolna, opis PL) → bramka ludzka: automat robi 5-6 zrzutów krytycznych ścieżek,
  **Dzidek ogląda i odblokowuje** → runbook oddania (link działa z zewnątrz, dane demo,
  collaborator ZAPROSZONY przed linkiem) → oddanie.
- **28-30.08 — BUFOR na zwroty (próby 2-3).** Zmiana po zamrożeniu = ścieżka hotfix.

Rytm: branch → test na żywych danych testowych → PR (recenzent-Claude + CI) → merge;
następny krok dopiero po POTWIERDZONYM wyniku poprzedniego. Bramki CI rosną z kodem
(manifest przez osobne PR-y). Gdy bramka czerwona — naprawiamy produkt, NIGDY miarę.

## 10. KADENCJA SPRAWDZANIA (ze skilla sprawdzanie-zlecenia, dopasowana)

MOMENT 1 (dziś): **MAPA-SPEC.md** generowana z surowej notatki zlecenia (statusy: dosłowne /
interpretacja / odstępstwo z datą) — audytor zgodności dostaje POTEM źródło+mapę, nie moje
streszczenie. MOMENT 2: audyt pierwszego przelotu NA KOŚCI (K2). W TRAKCIE: automaty w CI
(eslint, testy, gitleaks, CodeQL; znaleziony błąd → w 24 h reguła lintera albo test).
MOMENT 4 (K7): cztery kontrole + kalibracja + GO/NO-GO z trzech liczb (zero krytycznych
i dużych · kalibracja ≥ próg · zero rozjazdów) — liczby liczy skrypt, nie proza.
Audytor: zawsze świeża sesja w zerowym kontekście, jedna soczewka na agenta, znalezisko =
padający test, „zero z uzasadnieniem" wymagane. Zasada dwóch prób naprawy, potem reset
i inne podejście.

## 11. REJESTR ZAŁOŻEŃ v2

- Z1. Symulacja BLIK w teście Stripe — sprawdzana w K1 (karta = pewnik).
- Z2. Limity CPU darmowego Workera: 10 ms CPU/wywołanie (cennik 16.08) — to czas PROCESORA,
  nie zegara (czekanie na D1/R2/sieć się nie liczy; HMAC i klej API to ułamki ms) → ryzyko
  niskie, ale MIERZONE w K1 (pass^k 3/3).
- Z8. Mechanizm wpięcia potwierdzony wyłącznie na stronie egzaminacyjnej — pozostałe 3 strony
  (przyszłość) dostają checklistę kompatybilności (CSP/router/ścieżki) przy swoim wdrożeniu.
- Z3. Mechanizm wpięcia — rozstrzyga SPIKE (K0), nie wiara.
- Z4. Dostawca maili: dostarczalność wielu skrzynek + spam + stopka darmowego planu — K1/K2.
- Z5. ~~Panel na osobnej domenie~~ ROZSTRZYGNIĘTE: panel na originie Workera.
- Z6. KTO wykonuje wpięcie u właścicieli: założenie = właściciel z dostępem do ŹRÓDŁA strony
  (tylko zmiana w źródle przeżywa rebuild) — instrukcja pisana pod to; koszt zmiany: średni.
- Z7. Wolumen: pojedyncze zakupy dziennie (przy zmianie: limity przeliczyć).

## 12. KRYTERIA „GOTOWE" v2 (binarne; mierzą NASZĄ część; wykonywane NA PACZCE na świeżym wdrożeniu)

1. Zakup kartą testową: zaplecze przekazuje mail dostawcy ≤60 s od webhooka (nasz pomiar);
   osobno test ręczny: mail w skrzynce (z kontrolą spamu) i **treść maila widoczna w panelu**.
2. Link pobiera WŁAŚCIWY plik (sha256 zgodny ze snapshotem); po terminie/limicie odmawia;
   HEAD/Range nie zużywa limitu; 2 równoległe pobrania przy ostatnim slocie → przechodzi 1.
3. Webhook 2× = jedno zamówienie i jeden mail; ponowienie po przerwaniu W POŁOWIE dokańcza
   (test z wymuszonym padnięciem przed mailem).
4. Podmieniona cena/produkt w żądaniu = zignorowane; zakup bez zaznaczonej zgody = niemożliwy;
   webhook bez ważnego podpisu = odrzucony; endpoint admina bez sesji = odmowa.
5. Panel: zakup widoczny (czas=webhook, kwota, produkt ze SNAPSHOTU); edycja/deaktywacja
   produktu NIE zmienia historii; dziennik logowań z loginem (udane i nieudane); log zdarzeń
   obejmuje akcje admina; eksport CSV działa.
6. CRUD: nowy produkt widoczny w sklepie bez zmian kodu; produkt bez potwierdzonego pliku
   nie da się aktywować; walidacje odrzucają cenę ≤0 i pustą nazwę.
7. DRUGA instalacja: zaplecze skryptem (czas zmierzony, jawny) + wpięcie witryny wg instrukcji
   przez NIE-autora ≤30 min; zakup na instalacji B ląduje wyłącznie w bazie B.
8. Odpięcie wg instrukcji: diff źródła strony czysty + zaplecze wyłączone; kupujący przed
   odpięciem: los linków opisany w instrukcji (decyzja właściciela).
9. Krytyczny przelot (zakup→mail→pobranie) przechodzi **3/3 powtórzeń** (pass^k).
10. Produkty-treść: obie paczki istnieją, kompletne (instrukcja+diagram+pliki), opis „ile czasu
    oszczędza" ma pokrycie w treści paczki.
11. WCAG-lite: formularze z labelkami, obsługa klawiaturą, fokus widoczny; RWD na telefonie;
    strony działają w Chrome/Edge/Firefox.
12. Kalibracja audytu ≥ próg (2/3 mutantów) — inaczej audyt nieważny, nie oddajemy.

## 13a. DOKUMENTY PROJEKTU (ten sam komplet co w dziale audytów — kto, gdzie, kiedy powstaje)

**Żywe od startu (już istnieją, aktualizowane na bieżąco):**
`ZROZUMIENIE.md` (surowe zdania + fakty; build-guard pilnuje) · `MAPA-SPEC.md` (litera źródła ↔
wykonanie, statusy dosłowne/interpretacja/odstępstwo; wsad dla audytora zgodności) ·
`DECYZJE.md` (decyzja+uzasadnienie+odrzucone alternatywy — zamiennik pytań do klienta) ·
`audyt/REJESTR-WERYFIKACJI.md` (co sprawdzone, CZYM, wynik, dowód) · `audyt/POMIAR-CZASU.md` ·
`audyt/DZIEN-1.md` (checklista bramek) · `.github/manifest-bramek.txt` (rośnie TYLKO osobnym PR)
· `krytyki/` (zapisy wierne + triage + mapa zbieżności) · rejestr założeń (sekcja 11 planu).

**Powstają w zdefiniowanym momencie:**
`KONTRAKTY.md` (styki witryna↔API↔Stripe↔mail) + `audyt/mapa-ryzyka.txt` (wagi per
odpowiedzialność) + `rubryka-GOTOWE.md` (z sekcji 12, zamrożona) — po kości, w projekcie
technicznym (bramka D) · `mutanty/*.patch` (bank łatek do kalibracji) — przed K7 ·
raporty audytów (`audyt/`) — K2 i K7 · `CHANGELOG.md` — od pierwszego wydania ·
`README.md` Z KODU — po zamrożeniu (K8) · dokumentacja klienta (K8): instrukcja wdrożenia
prostym językiem + zrzuty, diagram architektury dla informatyka, szablony regulaminu
i polityki, rozdział „test vs produkcja" (sekcja 13).

Zasada wspólna z działem audytów: dokument, którego nie pilnuje skrypt albo bramka,
umiera — dlatego każdy z powyższych ma właściciela-moment, a nie „będzie się prowadziło".

## 13b. ŹRÓDŁA OFICJALNE DZIEDZINY (drabina szukania, szczebel 2 — jak docs WP przy MP)

Przy każdej niepewności NAJPIERW te źródła, nie pamięć i nie blogi:
- **Stripe:** docs.stripe.com — Checkout, webhooks (podpis!), tryb testowy, karty testowe;
  przewodnik Stripe dla **Cloudflare Workers** (constructEventAsync).
- **Cloudflare:** developers.cloudflare.com — Workers (limity, sekrety), D1 (SQL, migracje,
  transakcje), R2 (presigned PUT), Cron Triggers, Wrangler CLI.
- **Dostawca maili** (kandydat Brevo): oficjalne API docs + polityka darmowego planu
  (stopka? limity? adres nadawcy).
- **Next.js:** nextjs.org/docs — static export, hydracja, router (rozumienie
  strony-GOSPODARZA, nie nasz stack).
- **GitHub Pages:** docs.github.com — zachowanie hostingu (404, ścieżki, nagłówki).
- **Google Search Central + schema.org:** dane strukturalne Product/JSON-LD (nasze „SEO-minimum").
- **MDN:** Web Crypto (HMAC), fetch, cookies (HttpOnly/SameSite) — wzorce webowe.
- **WCAG / W3C WAI quick reference:** dostępność formularzy (WCAG-lite).
- **Prawo konsumenckie (UOKiK / ustawa o prawach konsumenta):** odstąpienie od umowy
  o treści cyfrowe — brzmienie zgody w checkboxie i regulaminie.
- **Playwright docs:** testy E2E + axe (dostępność automatyczna).
⛔ WooCommerce/WordPress docs — NIE dotyczą tego stacku (inny świat; zostają przy zleceniach WP).
Nazwa narzędzia podana przez model = hipoteza do sprawdzenia w tych źródłach, nie fakt.

## 13. CO ODDZIELA TRYB TESTOWY OD PRODUKCJI (jawna lista — nie obietnica „podmiany kluczy")

Aktywacja kont Stripe właścicieli (KYC, rachunek) · webhook produkcyjny z nowym sekretem ·
domena nadawcza maili (bez niej produkcyjna wysyłka NIE działa) · VAT od treści cyfrowych
dla konsumentów UE (możliwy OSS — właściciele konsultują księgowość; my NIE doradzamy
podatkowo) · regulamin/polityka z danymi konkretnego sprzedawcy · warunki darmowego planu
Cloudflare przy działalności zarobkowej · usunięcie banera trybu testowego · przegląd
limitów przy realnym ruchu. **Ta lista trafia do instrukcji właściciela jako osobny rozdział.**
