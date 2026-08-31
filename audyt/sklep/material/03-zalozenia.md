# 03 — ZAŁOŻENIA PRZYJĘTE (SKLEP) — co jest decyzją, a nie brakiem

> ⛔ Każda pozycja niżej to **świadoma decyzja z uzasadnieniem**, zapisana w rejestrze
> `DECYZJE.md`. Zgłoszenie jej jako błędu = szum, który zjada czas weryfikacji. Jeśli
> uważasz, że decyzja jest ZŁA — napisz to osobno, w sekcji „wątpliwości co do decyzji",
> nie jako znalezisko.
>
> Ten plik dotyczy WTYCZKI SKLEP. Założenia specyficzne DLA ŚRODKA botów (granice
> ChatBota i Segregatora: Z1–Z7) są w `audyt/boty/material/03-zalozenia.md` — nie
> duplikuję ich tu. D19 (hybryda botów: silnik regułowy + AI) jest wpisana niżej, bo
> sklep sprzedaje boty W TEJ formie — to dotyczy też oceny treści strony produktu.

## REJESTR DECYZJI D1–D33 (źródło: `DECYZJE.md`, cała tabela)

Kolumny: numer decyzji · co przyjęte (cytat/skrót z kolumny „Decyzja") · czego z tego
powodu NIE zgłaszać jako błędu.

| # | Co przyjęte | Czego NIE zgłaszać |
|---|---|---|
| D1 | Zaplecze pierwotnie: „Cloudflare Worker+D1+R2, frontend czysty statyczny" — **ARCHIWALNE, unieważnione przez D16** (piwot na WordPressa) | Nie zgłaszaj braku Cloudflare/Workera/D1/R2 — dzisiejsza architektura to WordPress; D1 zostaje w rejestrze jako historia decyzji, nie jako stan docelowy |
| D2 | Płatności: „Stripe Checkout, tryb testowy na egzamin" | Nie zgłaszaj braku P24/PayU/Tpay/Autopay ani braku PRAWDZIWYCH (produkcyjnych) płatności — wybór Stripe i tryb testowy są świadome; egzamin celowo bez prawdziwych pieniędzy |
| D3 | Panel admina na originie Workera — **ARCHIWALNE w przesłance**: D16 stwierdza wprost, że „wp-admin jest z definicji na originie strony", więc pierwotny problem/rozwiązanie D3 przestał istnieć | Nie zgłaszaj „panelu na obcym originie" jako luki — ochronę panelu przejęły standardy WP (auth rdzenia, capabilities, nonces) |
| D4 | Pobrania: „przez endpoint Workera (HMAC, streaming, atomowy licznik PRZY STARCIE, limit 10, bez Accept-Ranges)" — mechanizm przeniesiony na endpoint wtyczki po D16 | Nie zgłaszaj braku presigned URL (nie liczyłby pobrań) ani braku Accept-Ranges/wznawiania pobierania (pozwoliłoby ominąć licznik) — to świadome wybory |
| D5 | Webhook: „INSERT ON CONFLICT + wznowienie; błąd aż do domknięcia; mail_w_toku przed wysyłką; okno duplikatu maila zaakceptowane" | Nie zgłaszaj sporadycznego podwójnego maila jako bugu w tym oknie — świadomie zaakceptowane (lepszy podwójny mail niż żaden); okno później zawężone przez D24 |
| D6 | „BEZ przecen i BEZ sekcji opinii w v1"; UZUPEŁNIENIE 24.08: sekcja opinii WCHODZI, ale WYŁĄCZNIE prawdziwe od zweryfikowanych kupujących, BEZ gwiazdek-agregatów; przeceny NADAL nie ma | Nie zgłaszaj braku przecen/promocji, braku zbiorczej oceny gwiazdkowej (np. „4.8★ z 32 opinii") ani braku możliwości dodania opinii przez gościa/niekupującego — Omnibus wymaga historii cen (jej nie mamy) i weryfikacji kupującego (mamy — patrz D32) |
| D7 | „Kursy poza zakresem; «usuwanie» produktów = deaktywacja przy zamówieniach; snapshot produktu w zamówieniu" | Nie zgłaszaj braku sprzedaży kursów ani braku twardego DELETE produktu powiązanego z zamówieniem — to zamierzone (integralność historii, kursy = zakres innego egzaminu) |
| D8 | „Cel nr 1 = zdać egzamin; wszystko w trybie testowym; produkcja = jawny rozdział różnic" (NIE „podmiana kluczy") | Nie zgłaszaj braku aktywnych/produkcyjnych kont płatności ani „niedokończenia" przejścia test→produkcja — patrz `PLAN-PROJEKTU.md` §13, jawna lista tego, co dzieli tryb testowy od produkcji |
| D9 | „Repo prywatne na koncie klienckim; blokada main = lokalny hook" (nie GitHub branch protection) | Nie zgłaszaj braku natywnej ochrony gałęzi GitHub na prywatnym repo — świadomie zastąpiona lokalnym hookiem (GitHub Free nie daje branch protection na prywatnym repo) |
| D10 | „SEO-minimum świadomie ograniczone: produkty renderowane z API, meta ogólne, JSON-LD skryptem" (NIE pre-render) | Nie zgłaszaj braku pre-renderowania/SSG produktów — świadomy wybór, bo pre-render łamałby wymóg „nowy produkt bez zmian kodu" |
| D11 | „Anonimizacja: email→NULL + usunieto_dnia; retencja logów 90 dni; DPA u dostawców w instrukcji" | Nie zgłaszaj braku hashowania e-maila zamiast zerowania (hash uznano dalej za daną osobową) ani braku twardego kasowania wierszy zamówień |
| D12 | „Interpretacje litery notatki: S-04… S-07… S-09…" — ✅ KLEPNIĘTE przez właściciela 18.08 z wytyczną „rób jak dobre sklepy" | Nie zgłaszaj rozbieżności między literą notatki a wykonaniem S-04/S-07/S-09 jako błędu — to zaakceptowana interpretacja, patrz `MAPA-SPEC.md` (i `material/01-zamowienie.md` część b) |
| D13 | Wariant A wpięcia (link w źródle) preferowany, B (skrypt) fallback — **ARCHIWALNE**: dotyczyło statycznego HTML, unieważnione przez D16 | Nie zgłaszaj — nieaktualne po piwocie; dziś nie ma mechanizmu „wpięcia" skryptem do obcego DOM |
| D14 | „Bank mutantów + kalibracja audytu ZOSTAJE mimo uwagi «przerost»" | Nie zgłaszaj braku uproszczenia procesu audytu — kalibracja świadomie utrzymana (lekcja z MP: audyt bez kalibracji dał zero znalezisk i był bezwartościowy) |
| D15 | Mechanizm wpięcia: katalog `sklep/` + `wtyczka.js` + script defer — **ARCHIWALNE**, źródło wprost: „UNIEWAŻNIONE przez D16" | Nie zgłaszaj braku tego mechanizmu — to historia sprzed WordPressa; dowody spike'a zostają w `audyt/REJESTR-WERYFIKACJI.md` jako zapis procesu, nie jako stan dzisiejszy |
| D16 | „PIWOT NA WORDPRESSA: sklep budujemy jako WTYCZKĘ WP z zapleczem CAŁYM w WP… unieważnia D1 (Cloudflare), D15 (mechanizm wpięcia) ORAZ D3 w przesłance" | Nie zgłaszaj braku architektury Cloudflare/Worker/D1/R2 — to CAŁA podstawa dzisiejszej architektury, nie luka |
| D17 | „Sklep = WŁASNA wtyczka WP, NIE WooCommerce" | Nie zgłaszaj braku integracji z WooCommerce/EDD/SureCart — świadomy wybór (egzamin ocenia naszą robotę, nie cudzy kod) |
| D18 | „Stripe przez WP HTTP API (`wp_remote_post`, form-encoded) + RĘCZNA weryfikacja podpisu webhooka (`hash_hmac` SHA-256 + `hash_equals`, surowe body, tolerancja 5 min)" | Nie zgłaszaj braku biblioteki `stripe-php`/SDK — świadomie pominięta (zero zysku przy 2 wywołaniach API, dodatkowy vendor/utrzymanie) |
| D19 | Boty „testowe" = HYBRYDA: silnik regułowy (zawsze) + prawdziwe AI przez API (klucz właściciela, stop-loss); „IMAP/pamięć rozmów/operator na żywo = po egzaminie" | Nie zgłaszaj braku IMAP w Segregatorze, braku pamięci rozmów w ChatBocie ani braku czatu z operatorem na żywo — jawnie zadeklarowane granice (patrz też Z1–Z4 w `audyt/boty/material/03-zalozenia.md`) |
| D20 | „Front sklepu: strony WP tworzone przy aktywacji + shortcode'y; assety ładowane tylko na stronach sklepu" | Nie zgłaszaj braku integracji z szablonem motywu ani braku bloków Gutenberga — świadomie najmniej inwazyjne (motywu nie ruszamy) |
| D21 | „Teksty polskie wprost, BEZ i18n/textdomain" | Nie zgłaszaj braku plików `.pot`/`.po` ani braku `textdomain` — świadomie pominięte (odbiorcy = 4 polskie strony; omija minę „textdomain too early" WP 6.7+) |
| D22 | „Uninstall: dane sprzedaży domyślnie ZOSTAJĄ; opcja «usuń wszystkie dane» (default OFF)… SEKRETY (klucz Stripe, sekret webhooka) kasowane ZAWSZE niezależnie od opcji… CRON (`sklepag_dociagnij_zalegle`) kasowany ZAWSZE niezależnie od opcji" | Nie zgłaszaj pozostawienia danych sprzedaży/tabel po deinstalacji jako braku — to DOMYŚLNE zachowanie. ⚠️ ZGŁASZAJ natomiast, jeśli sekrety albo zaplanowane zadanie PRZEŻYWAJĄ deinstalację nawet przy wyłączonej opcji czyszczenia — to jest błąd (dokładnie klasa #1 ściągi egzaminatora) |
| D23 | „Warsztat: łapacz SMTP (mailpit) dołożony do stacka DO OGLĄDANIA maili; produkt używa czystego `wp_mail`" | Nie zgłaszaj braku wbudowanej wtyczki SMTP w produkcie — świadomie pominięta (na hostingu wysyłką rządzi hosting, jawne w instrukcji); mailpit to narzędzie WARSZTATU, nie część paczki produktu |
| D24 | „DWA silniki domykania zakupu (webhook + reconciliation przez API) z JEDNYM przejęciem: każde przejście stanu zamówienia = atomowy UPDATE z warunkiem (CAS)… źródłem prawdy stanu jest wyłącznie `zamowienia.status`" | Nie zgłaszaj istnienia DRUGIEGO mechanizmu (reconciliation) obok webhooka jako duplikacji/nadmiarowości — to świadoma redundancja przeciw zawodności webhooka |
| D25 | Bramki CI: php-lint+phpstan(level 5)+gitleaks od razu na WSZYSTKIE 3 wtyczki; „phpcs… startowo TYLKO na wtyczce sklepu" (boty dołączyły jednym ruchem ~23.08); sniff `WordPress.DB.DirectDatabaseQuery` wyłączony dla botów JEDNĄ deklaracją, bo „boty trzymają CAŁY stan we własnych tabelach" | Nie zgłaszaj braku aktywnego sniffa `DirectDatabaseQuery` dla botów jako luki jakości kodu — świadomie wyłączony i sprawdzony (69 trafień = wyłącznie tabele własne `chatl_*`/`segm_*`); sniffy bezpieczeństwa SQL zostają aktywne wszędzie |
| D26 | „Opóźnienie logowań = sleep… + ciasny rate-limit 6/min per IP na `wp_authenticate` ODBIJAJĄCY nadmiar natychmiast (429)… REZYDUALNE (zaakceptowane): atak z WIELU IP naraz = klasa DDoS, poza zakresem wtyczki — obrona na poziomie serwera (fail2ban/WAF)" | Nie zgłaszaj braku ochrony przed rozproszonym atakiem (wiele IP) na logowanie — świadomie rezydualne ryzyko przekazane do instrukcji wdrożenia (K8), nie do kodu wtyczki |
| D27 | K5 (wygląd stron produktowych) „KLEPNIĘTE W CAŁOŚCI"; SEO PODSTAWOWE-DOBRE „rozszerzenie ponad literę → do PONAD-SPECYFIKACJE.md" | SEO jest ROZSZERZENIEM ponad literę zamówienia — nie zgłaszaj braku SEO jako niespełnienia ZAMÓWIENIA (zamówienie o SEO nie mówi); jeśli SEO jest już obiecane w treści strony a nie działa, to inna sprawa (patrz `05-reguly-branzy.md`) |
| D28 | K5b: „link «Sklep» w OBU nawigacjach motywu kopii… tylko istniejące klasy (motyw ma nav wpisany na sztywno)"; obrazy = zrzuty ŻYWYCH botów bez retuszu (częściowo zmienione przez D28b niżej) | Nie zgłaszaj braku WIDEO produktów — świadomie odłożone po egzaminie (mało zysku na koszt nagrania/hostingu) |
| D28b | „KARTA listy = własna ILUSTRACJA SVG w brandzie… zrzut ŻYWEGO bota przenosi się WYŁĄCZNIE na stronę produktu" do galerii „CO JEST W ŚRODKU" (zastąpiła „JAK TO WYGLĄDA", PR #173) | Nie zgłaszaj braku zrzutu żywego bota NA KARCIE listy produktów — świadomie przeniesiony (karta = marketing/branding, strona produktu = dowód) |
| D29–D30 | *(ta numeracja NIE występuje w `DECYZJE.md` — luka w samym rejestrze źródłowym, sprawdzona: po D28b następuje wprost D31)* | — nie jest to brak tego pliku materiału |
| D31 | „GWARANCJA SATYSFAKCJI 14 DNI na oba boty: «zwrot bez pytań»… Ryzyko «kupi-skopiuje-zwróci» ZAAKCEPTOWANE świadomie" | Nie zgłaszaj braku zabezpieczenia przed nadużyciem gwarancji (np. blokady odkupienia po zwrocie) — ryzyko świadomie zaakceptowane (kod i tak GPL; wartość = paczka+aktualizacje+wsparcie); nie zgłaszaj też braku ustawowego prawa odstąpienia — go tu nie ma z mocy prawa (art. 38, zgoda na natychmiastowe dostarczenie) |
| D32 | „MECHANIZM OPINII: tabela `sklepag_opinie` (`UNIQUE zamowienie_id`)… token przy mailu zakupowym (30 dni)… formularz przez `admin-post` z tokenem (zgoda na publikację wymagana, pole-pułapka, rate-limit)… moderacja w panelu… na stronie WYŁĄCZNIE `przyjeta`" | Nie zgłaszaj braku wystawienia opinii bez zakupu, braku publicznego formularza bez tokenu ani braku natychmiastowej publikacji bez moderacji — wszystko świadomie zamknięte (Omnibus wymaga weryfikacji zakupu przed publikacją) |
| D33 | K7 (audyt sklepu): „kalibracja audytorów = 100% mutantów WŁASNEGO zakresu + zero alarmów na pułapkach…"; „mechanika = gałąź `audyt/mutanty-sklep`… tabela rozliczenia COMMITOWANA na tej gałęzi… `kalibruj.sh` NIEUŻYWANY w K7"; „limit JEDNEJ powtórki na audytora" | Nie zgłaszaj braku banku mutantów w repo głównym/w tym materiale — trzymany świadomie na osobnej gałęzi `audyt/mutanty-sklep` (lekcja: bank „poza repo" w luźnym pliku ZNIKNĄŁ wcześniej — gałąź jest bezpieczniejsza) | (dziś: bank przeniesiony na TAG `bank/mutanty-sklep`, 31.08 #238)

## MUST NOT z PLAN-PROJEKTU.md §3 (cytat)

Źródło: `PLAN-PROJEKTU.md`, „## 3. MUST NOT (świadomie poza zakresem)", linie 44–60 —
DOSŁOWNIE:

> Kont/logowania KUPUJĄCYCH nie ma (konta w WP mają tylko adminowie) · kursów NIE
> sprzedajemy · faktur NIE wystawiamy · plików produktów NIE serwujemy bezpośrednio
> (tylko brama PHP) · prawdziwych płatności NIE włączamy · przecen NIE MA (Omnibus-ceny
> — jawnie nie dotyczy) · opinie WYŁĄCZNIE prawdziwe od zweryfikowanych kupujących,
> zbierane jednorazowym linkiem z maila zakupowego i publikowane po ręcznej akceptacji
> (D6 + UZUPEŁNIENIE 24.08 + D32) — bez zbierania od gości i bez ocen zbiorczych
> (Omnibus-opinie DOTYCZY i jest spełniony weryfikacją zakupu przed publikacją oraz notą
> przy sekcji) · **WooCommerce NIE używamy** (D17 — sklep to nasza wtyczka) ·
> **tłumaczeń/i18n NIE robimy** (teksty polskie wprost — D21; omija też minę textdomain
> WP 6.7+) · multisite WP NIE (jedna zwykła instalacja) · monitoringu zewnętrznego NIE
> w v1 · botów z PRAWDZIWYM AI/IMAP NIE na egzamin (D19 — rdzeń demo; jawna droga
> rozbudowy w instrukcji) · **motywu i cudzych wtyczek strony NIE ruszamy** — granica
> „nie dotykamy reszty strony" po piwocie: nasze wtyczki żyją we własnych
> tabelach/opcjach/stronach/shortcode'ach, a odpięcie = deinstalacja z czystym uninstall.

## Zasady egzaminu (cytat)

Źródło: `ZROZUMIENIE.md`.

### ⭐ ZASADA EGZAMINU (właściciel 17.08 ~00:20): ZERO PYTAŃ DO KOLEGI

> Wszystkie niejasności rozstrzygamy SAMI i dokumentujemy w rejestrze decyzji
> (archiwum-procesu/PLAN-ETAP1.md). „Kursy" = egzamin DRUGIEGO zdającego, nie nasz temat. Koło ratunkowe
> (1 telefon) = na prawdziwy zator.

### ⏰ TERMIN I ZASADY OCENY (właściciel 17.08 ~02:20; AKTUALIZACJE 20.08 i 25.08)

> **Termin: 1.09.2026 (zmiana z 30.08 — właściciel 20.08) · 3 próby oddania · ocenia kolega
> (Manik), zasady jak przy MP.**
> 25.08 (właściciel): po KAŻDEJ próbie dostajemy TYLKO LICZBĘ błędów, nie listę. Konsekwencje:
> konkrety musi znaleźć NASZ audyt (K7), a liczba od kolegi po próbie 1 jest odważnikiem
> audytu (jego N kontra nasza lista — N > 0 przy naszej pustej liście = ślepa plama
> audytu, nie pech). 3 próby to zasób: próba 1 (~31.08) mierzy ślepe plamy, próby 2–3
> (1.09) = hotfixy mini-cyklem. Limit tygodniowy Claude odnawia się w niedzielę 30.08 →
> przed niedzielą: materiał, kalibracja, runda A, naprawy krytycznych; po niedzieli:
> runda B, re-audyt, przelot człowieka. (Historia: pierwotny plan „próba 1 z zapasem
> ~26–27.08" — nieaktualny po rundzie stron 24–25.08 i K7.)

⚠️ **Rozbieżność między dokumentami źródłowymi (zgłaszam, nie rozstrzygam):**
`PLAN-PROJEKTU.md` (nagłówek, linia 7) podaje „Termin egzaminu: 30.08 · 3 próby oddania
· próba 1 planowana 27.08", a `ZROZUMIENIE.md` (aktualizacja 20.08, cytat wyżej) podaje
„1.09.2026". ROZSTRZYGNIĘTE 25.08 przez koordynatorkę: obowiązuje **1.09.2026** (właściciel 20.08 i 25.08; nagłówek PLAN-PROJEKTU poprawiony) — wcześniej pytanie do koordynatorki/
zamawiającego, nie znalezisko w kodzie wtyczki.

---

Rzecz spoza tej listy, która wygląda na świadomą decyzję, ale nie ma tu wpisu = PYTANIE
DO ZAMAWIAJĄCEGO, nie błąd.

**Rzecz spoza tej listy, która wygląda na świadomą decyzję, ale nie ma tu wpisu = PYTANIE DO ZAMAWIAJĄCEGO, nie błąd.**
