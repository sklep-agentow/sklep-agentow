# KRYTYKI OBCYCH MODELI — plan v1 (wklejone przez Dzidka 17.08 ~03:30)

Cztery niezależne przebiegi (A, B, C, D wg kolejności wklejenia). Zapis wierny uwag;
pominięto wyłącznie: wyciek angielskiego brudnopisu rozumowania jednego z modeli,
stopki „odpowiedź generowana przez AI" i komunikat o zmianie modelu (Kimi K2.6).
Pełny surowy tekst = wiadomość Dzidka w czacie 17.08 ~03:30.

---

## MODEL A (7 uwag + białe plamy)

**A1 KRYTYCZNA — Wstrzykiwanie czystego DOM do aplikacji hydrowanej Reactem.** React
oczekuje kontroli nad DOM; zewnętrzna ingerencja = hydration mismatch; przy re-renderze
React nadpisze/usunie wstrzyknięty fragment albo aplikacja gospodarza rzuci błędem.

**A2 KRYTYCZNA — Brak własnej domeny do wysyłki maili (Brevo).** Polityki DMARC/SPF
(Google/Yahoo 2024) odrzucają ruch API podszywający się pod domeny freemailowe; Brevo
nie autoryzuje wysyłki z cudzej domeny bez dowodu własności. Szczęśliwa ścieżka stanie:
zakup zapisany, mail nigdy nie dojdzie.

**A3 WAŻNA — Omnibus bez historii cen.** Jedna kolumna cena_grosze nadpisywana przy
edycji kasuje historię; „najniższa cena z 30 dni" niemożliwa; funkcja będzie atrapą.

**A4 WAŻNA — XSS przy tokenie admina na stronie gospodarza.** Jedna domena dla witryny
i panelu + token w localStorage = skrypty gospodarza (śledzące, wtyczki) mają pełny
dostęp; przejęcie tokena = pobranie bazy klientów przez API.

**A5 WAŻNA — Limit payloadu Workera dla plików do R2.** Twarde limity request body;
plik 150 MB → 413 zanim dotknie R2.

**A6 WAŻNA — Rozspójnienie bazy przy fizycznym usuwaniu produktów.** DELETE produktu
przy zamówieniach wskazujących produkt_id = błąd 500 dashboardu; klucze obce chroniące
= „usuwanie" przestaje działać dla kupionych produktów.

**A7 DROBNA — Race na stronie „Dziękujemy".** Webhook bywa kilkanaście sekund po
powrocie; klient widzi „sprawdź maila", skrzynka pusta, niepokój/odświeżanie.

**Białe plamy A:** waga plików (Worker→R2 vs presigned) · dystrybucja kluczy 4 adminów
(wrangler.toml bez ujawniania sobie nawzajem) · izolacja CSS (Shadow DOM/prefiksy —
wyciek stylów w obie strony) · migracje D1 i automatyzacja pod ≤30 min.

---

## MODEL B (51 uwag + białe plamy) — skróty tytułowe z pełną treścią argumentów

**B1 KRYT — „Własne konto per właściciel" nie rozwiązuje wieloinstalacyjności.** Brak
mechanizmu instalacji/inicjalizacji/aktualizacji 4 niezależnych instancji; na 2-3 stronie
zostanie zły worker_url/klucz z innej instancji; zamówienie poleci do cudzej bazy.
**B2 KRYT — „Podmiana kluczy = produkcja" to nieprawda.** Dochodzą webhooki, domeny,
maile, reputacja, dane, zwroty, monitoring; po podmianie webhook dalej wskaże test.
**B3 KRYT — Brak rozdzielenia środowiska testowego od produkcyjnego.** Dane testowe
w tym samym miejscu; po przełączeniu panel pokaże stare zakupy testowe.
**B4 KRYT — Dostarczenie pliku opisane zbyt ogólnie.** Kto podpisuje URL, czy można
przekazać dalej, licznik przed czy po sukcesie, co po błędzie transferu.
**B5 KRYT — Licznik 5 pobrań bez operacji atomowej.** Dwa równoczesne żądania → 6-10 pobrań.
**B6 KRYT — Idempotencja nie obejmuje całego skutku webhooka.** INSERT przeszedł, Worker
padł przed mailem → ponowienie uznane za duplikat → klient zapłacił, nie dostanie.
**B7 KRYT — mail_blad bez trwałej kolejki/retry.** Timeout dostawcy → stan zależny od
momentu padnięcia.
**B8 KRYT — Brak modelu stanów płatności poza „webhook przyszedł".**
**B9 KRYT — Brak niezmiennego stanu produktu z momentu zakupu.** Edycja/zmiana pliku po
zakupie → historia przestaje być prawdziwa.
**B10 KRYT — CRUD z pełnym usuwaniem sprzeczny z danymi zamówień.** Puste nazwy, uszkodzone
rekordy, niekompletny raport.
**B11 KRYT — Brak wersjonowania produktu/pliku.** Klient A kupił v1, ponowne pobranie
da v2.
**B12 KRYT — Sesja panelu zbyt ogólna.** Gdzie token, jak unieważniany, rotowany;
wylogowanie nie unieważnia; localStorage/XSS.
**B13 KRYT — CORS to nie uwierzytelnienie.** Poza przeglądarką CORS nie istnieje;
endpoint admin bez własnej autoryzacji = dane dostępne.
**B14 KRYT — Brak ochrony CSRF operacji administracyjnych.**
**B15 KRYT — Brak ochrony XSS w danych produktów.** Opisy z bazy renderowane jako HTML.
**B16 KRYT — Upload do R2 niedookreślony.** Rozmiar, MIME, nazwy, nadpisywanie.
**B17 KRYT — „Kto" w logu nierealizowane.** Tabela bez identyfikatora; egzaminator
zapyta „które konto?", odpowiedź: „IP X".
**B18 WAŻ — Brak logu działań administracyjnych** (tylko logowania) — kto usunął produkt?
**B19 WAŻ — Brak limitów publicznych endpointów** (enumeracja, koszty, tysiące checkoutów).
**B20 WAŻ — Podwójny klik ≠ dwie sesje Checkout.** Idempotencja na POST /api/zamow —
session_id tego nie łapie, bo każda sesja ma inny id.
**B21 WAŻ — „Mail ≤2 min" złe kryterium** (zależy od dostawcy/filtrów).
**B22 WAŻ — „Mail doszedł" nieprecyzyjne** (accepted przez API ≠ w skrzynce).
**B23 WAŻ — Brak odzyskania po utracie hasła admina.**
**B24 WAŻ — Brak backupu/odtworzenia D1/R2.**
**B25 WAŻ — Brak monitoringu błędów Workera.**
**B26 WAŻ — „Stripe ponawia 3 dni" jako założenie projektowe zamiast własnej odporności.**
**B27 WAŻ — Brak obsługi kolejności zdarzeń.**
**B28 WAŻ — Brak snapshotu nazwy produktu w zamówieniu** (kwota to za mało).
**B29 WAŻ — Wyścig edycji produktu i zakupu** (UI 49 zł, checkout 59 zł).
**B30 WAŻ — „Usunięcie produktu" bez semantyki** (rekord? plik? dezaktywacja?).
**B31 WAŻ — „Zero śladu" niezdefiniowane** (repo? cache? CDN? konfiguracja zewnętrzna?).
**B32 WAŻ — Skrypt dokładający menu kruchy** (zmiana layoutu hosta = sklep znika bez błędu).
**B33 WAŻ — config.js: brak rozdzielenia konfiguracji publicznej od prywatnej.**
**B34 WAŻ — Brak polityki kompatybilności 4 stron** (CSP, routing, cache różne).
**B35 WAŻ — Brak testu po deployu „ta strona→ten backend→ten Stripe".** Zakup u B
zapisze się w bazie A.
**B36 WAŻ — Konflikty nazw/identyfikatorów między instalacjami.**
**B37 WAŻ — Kość pomija administracyjną obsługę problemów** (retry, ręczne odzyskiwanie).
**B38 WAŻ — „Mutanty przez kalibrator" niewymierne** (brak klas przypadków i oczekiwanych
rezultatów; audyt = przypadkowe próby).
**B39 WAŻ — Brak testu odświeżenia/ponownego wejścia po sukcesie.**
**B40 WAŻ — Brak testu „A kupił, B dostał link".**
**B41 WAŻ — Checkbox odstąpienia jako gotowy fakt prawny** — brak zapisu CO user
zaakceptował przy KTÓRYM zamówieniu.
**B42 WAŻ — RODO powierzchowne** (IP, UA, logi, sesje — różne retencje).
**B43 WAŻ — Brak polityki retencji** (logi rosną bez końca).
**B44 WAŻ — „Dane płatności" niezdefiniowane** (minimalny zestaw pól do obsługi problemu).
**B45 WAŻ — Brak semantyki „czasu zakupu"** (utworzenie checkoutu? webhook? potwierdzenie?).
**B46 DROB — Mieszanie decyzji z hipotezami.**
**B47 DROB — „Cennik potwierdzony 16.08" słabą kotwicą** (data zamiast wersji/konfiguracji).
**B48 DROB — „1 produkt w bazie" bez danych startowych/seed** (panelu jeszcze nie ma).
**B49 DROB — Brak testu pustej bazy** (świeża instalacja = stan pusty).
**B50 DROB — Brak testu nieprawidłowych danych produktu** (cena 0/ujemna, pusty plik).
**B51 KRYT — NAJWIĘKSZA SPRZECZNOŚĆ: plan mierzy „czy sklep działa", nie „czy potrafię
bezpiecznie skopiować sklep na kolejną stronę".** Pierwsze wdrożenie 7/7, drugie = ręczna
edycja plików i sekretów.
**Białe plamy B:** ~27 pozycji — m.in. weryfikacja webhooka w praktyce, prywatność linków
R2, atomowość licznika, bezpieczeństwo retry, duplikaty checkoutów, izolacja instalacji,
CSP/cache/routing hostów, sekrety w konfiguracji publicznej, timestamp „kiedy kupili",
tożsamość w logach, wykonalność instrukcji przez nietechnicznego, powtarzalność testów,
spójność po awarii każdego z komponentów, interpretacja wymagań przez egzaminatora.

---

## MODEL C (10 KRYT + 12 WAŻ + 7 DROB + białe plamy)

**C1 KRYT — Sesja Stripe nie zostawia śladu po naszej stronie.** Zamówienie powstaje
dopiero z webhooka; zły sekret/literówka w URL endpointu → po 3 dniach ponowień Stripe
się poddaje; pieniądze u Stripe, w bazie pustka, nie ma od czego zacząć „dociągania".
**C2 KRYT — Idempotencja nie chroni przed przerwaniem w połowie.** INSERT → Brevo 9 s →
timeout → retry odbija się od UNIQUE ścieżką błędu; mail_blad nigdy się nie ustawi.
Kryterium 5 tego nie wykryje (testuje 2 czyste dostarczenia). Potrzebne: INSERT ON
CONFLICT DO NOTHING + odczyt statusu + wznowienie od miejsca zatrzymania + status
„oczekuje" przed wysyłką.
**C3 KRYT — „Produkcja = podmiana kluczy" trafi do instrukcji klienta jako fałsz.**
Dochodzi KYC, VAT od treści cyfrowych w UE i OSS, regulamin konkretnego sprzedawcy,
administrator danych, domena, warunki CF, Omnibus. Egzaminator uzna za świadome
pomniejszenie zakresu ALBO właściciel podmieni klucze i zacznie sprzedawać.
**C4 KRYT — Poczta bez domeny: Z4 testuje objaw, nie przyczynę.** SPF/DKIM Brevo +
From na obcej domenie = brak wyrównania DMARC; test na 5 dostawcach (Gmail, Outlook,
wp.pl, o2, firmowa) z folderem spam; realne wyjście: SUBDOMENA WYKONAWCY jako nadawca
dla wszystkich 4 instalacji — ale to zmiana architektury „każdy osobno" i musi być
w planie, nie w panice.
**C5 KRYT — Sprzeczność „gospodarz = eksport Next.js" z „kopiujesz katalog + linijka
w menu".** Kopiować trzeba do public/ w ŹRÓDLE (JSX, nie HTML) albo do wyniku builda —
kolejny deploy właściciela kasuje sklep bez śladu. „Czysty diff" — czyj? źródła czy
artefaktu? Router Next przechwyci link do /sklep/ → 404 bez wymuszenia pełnego
przeładowania; skrypt dokładający menu = React usunie węzeł przy re-renderze
(bez MutationObservera pozycja znika po pierwszej interakcji).
**C6 KRYT — Największe ryzyko testowane jako piąte, wbrew własnej zasadzie.** Kręgosłup
zdefiniowany jako płatność+mail (udeptane ścieżki z oficjalnymi wzorcami), a jedyna
rzecz, której nikt za nas nie rozwiązał — integracja — na końcu. Dowiadujesz się w 70%
projektu, że model wpinania nie działa.
**C7 KRYT — W planie pracy NIE MA wytworzenia samych produktów.** Kroki 1-7 to
hydraulika; „ile czasu oszczędza" bez istniejącego produktu jest zmyślone; egzaminator
zajrzy tam najpierw, bo nie wymaga czytania kodu. Na 3 dni przed oddaniem wrzucisz
2 PDF-y pisane w godzinę i sklep sprzedaje pustkę.
**C8 KRYT — „Opinie" produktu, który nie miał klienta.** Omnibus zakazuje opinii bez
weryfikacji, że pochodzą od kupujących; modelu danych na opinie nie ma → będą zaszyte
w HTML = wymyślone. Sprzeczność między dwiema sąsiednimi sekcjami planu.
**C9 KRYT — Dziennik „kto" architektonicznie niemożliwy.** Jedno konto, samo hasło,
tabela bez tożsamości; przy nieudanej próbie nie zapiszesz nawet, na kogo próbowano.
Minimum: pole login (nawet przy jednym koncie) + zapis wpisanej wartości + jawna
decyzja w dokumentacji.
**C10 KRYT — Ustawianie hasła „przy instalacji" bez mechanizmu.** Endpoint instalacyjny
musi się sam zamykać po pierwszym użyciu i być testowany; klasyka: zostaje żywy na
jednej z 4 instalacji → dowolna osoba ustawia sobie hasło do cudzego panelu.
**C11 WAŻ — Podpisany link R2 i limit pobrań to DWA RÓŻNE mechanizmy.** Presigned URL
wygasa, ale nie zlicza — R2 serwuje z pominięciem naszego kodu; licznik wymaga Workera
w ścieżce pobrania (własny token HMAC + strumieniowanie + inkrementacja). Inaczej pola
limitu martwe i kryterium 1 nie przechodzi.
**C12 WAŻ — Limit 5 pobrań zjedzą skanery poczty.** Antywirusy, Outlook Safe Links,
podglądy, żądania Range — kilka odwiedzin zanim człowiek kliknie; kupujący z firmową
skrzynką dostanie „limit wyczerpany" przy pierwszym kliknięciu. Liczyć unikalne pełne
pobrania z filtrowaniem HEAD/Range albo zrezygnować z limitu na rzecz wygaśnięcia.
**C13 WAŻ — Wskazany wzorzec kodu jest z innego runtime.** stripe-samples to
Node/Express; Worker: brak req.rawBody, synchroniczne constructEvent nie zadziała
(wariant Web Crypto), stripe-node wymaga jawnego klienta HTTP. Pewność była pozorna
dokładnie tam, gdzie nie wolno improwizować (podpis webhooka).
**C14 WAŻ — Omnibus wymaga historii cen** → tabela historia_cen przy edycji, wykryta
po zbudowaniu CRUD-a, albo usunięcie obietnicy z sekcji 6.
**C15 WAŻ — Powołanie na proces kontroli jakości w wersji, której już nie ma.**
W v3 mutanty testują TESTY (mutacja → CI → strażnik czerwony), a tu nie ma ani jednego
testu automatycznego — mutanty nie mają czego czerwienić; „testy prawdy klienta"
niemożliwe z definicji (zakaz pytań) — czym zastępujesz wyrocznię klienta?
**C16 WAŻ — „≤30 min" mierzy najłatwiejszy fragment i mierzy je zła osoba.** 30 minut
liczone od istnienia kont CF/Stripe/Brevo + wdrożonego Workera + schematu D1 — to są
godziny; wykonanie instrukcji przez autora nie jest testem instrukcji. Rozdziel
„zestawienie zaplecza" (jednorazowe, mierz osobno) od „wpięcia na stronę"; instrukcję
wykonuje ktoś, kto projektu nie zna.
**C17 WAŻ — 4 instalacje: koszt utrzymania nie jest w planie.** Poprawka błędu = 4
wdrożenia, 4 migracje, 4 zestawy sekretów; brak numeru wersji per instalacja → endpoint
wersji + wersja w panelu.
**C18 WAŻ — Regulamin/polityka per właściciel, a config ma 3 pola.** Wpięty w 30 minut
sklep opublikuje CUDZY regulamin albo puste nawiasy.
**C19 WAŻ — Usuwanie danych vs dashboard.** Kasowanie wiersza rozjeżdża sumy;
niekasowanie nie realizuje obietnicy; email też u Stripe i dostawcy maili → anonimizacja
(email→NULL + usunieto_dnia) + uczciwe zdanie w polityce o podmiotach przetwarzających.
**C20 WAŻ — Brak testów automatycznych przy systemie na pieniądze.** „Ponowienie
webhooka w trakcie niedokończonego zapisu" nie da się odtworzyć klikaniem; minimum:
stripe trigger CLI + testy integracyjne na Miniflare/wrangler dev.
**C21 WAŻ — Limit prób per IP.** IPv6 = dowolna liczba adresów; licznik w D1 na ścieżce
nieuwierzytelnionej = zapis do bazy jako wektor; opóźnienie wykładnicze + Turnstile.
**C22 WAŻ — Brak backupu i eksportu.** Usunięcie produktu kasuje klucz pliku; eksport
CSV = najtańsza obsługa wymagania „dashboard", której egzaminator nie podważy.
**C23 DROB — „W jakim czasie" = interpretacja do rejestru założeń** (sumy w okresach
vs czas od wejścia do zakupu).
**C24 DROB — Brak stanu „oczekuje" i licznika ponowień maila.**
**C25 DROB — Dwa źródła prawdy o wygasaniu linków** (zamowienia.link_wygasa vs konfig).
**C26 DROB — Upload przez Workera vs presigned PUT — zdecyduj teraz, nie w kroku 4.**
**C27 DROB — Brak banera „TRYB TESTOWY — płatności nie są pobierane".**
**C28 DROB — Darmowy Brevo dokłada reklamową stopkę do maili transakcyjnych.**
**C29 DROB — Sekret webhooka z CLI ≠ sekret endpointu z dashboardu** (błąd podpisu wykryty
dopiero na świeżym wdrożeniu).
**Białe plamy C:** czym SĄ produkty (zawartość, objętość, format, czy istnieją) · budżet
czasu per etap · rubryka egzaminatora (czy w ogóle uruchamia kod?) · struktura
strony-gospodarza i dostęp do źródła · KTO wykonuje wpięcie (właściciel? wykonawca?
egzaminator?) · stan kont i zgód właścicieli · co znaczą kalibrator/mutanty bez CI ·
czym zastąpiona wyrocznia klienta · warstwa wizualna (pierwsze, co zobaczy oceniający) ·
status prawny sprzedawców · los kupujących po odpięciu · wolumen ruchu.

---

## MODEL D (20 uwag + białe plamy; przebieg z wyciekiem brudnopisu — uwagi zachowane)

**D1 KRYT — Omnibus bez tabeli historia_cen** (stara wartość przepada przy edycji).
**D2 KRYT — Token admina w localStorage na domenie gospodarza** (Analytics/chat/widgety
mają dostęp; Hotjar czyta panel).
**D3 KRYT — Brak transakcyjności w webhooku** (crash między INSERT a UPDATE statusu →
zielone „opłacone" bez maila i bez czerwonego alarmu).
**D4 WAŻ — Brak zmiany hasła admina w panelu** (rotacja po wycieku = grzebanie w SQL).
**D5 WAŻ — CORS bez zarządzania listą originów per strona** (przeniesienie na własną
domenę = sklep martwy, błąd tylko w konsoli).
**D6 WAŻ — Brevo bez planu B** (spam/limit/blokada konta = projekt staje).
**D7 WAŻ — Panel jako statyczny HTML pod przewidywalnym adresem** (security through
obscurity; cache widoku po wylogowaniu).
**D8 WAŻ — Brak walidacji istnienia pliku w R2 przed aktywacją produktu** (klient płaci,
klika, 404).
**D9 WAŻ — Brak rate limitingu poza logowaniem** (spam sesji Stripe; zjedzenie 100k
żądań/dzień).
**D10 WAŻ — Konflikt z client-side routingiem Next.js** (router nie zna /sklep/ →
404 przy odświeżeniu/powrocie).
**D11 WAŻ — Brak backupu D1** (przypadkowy DELETE nieodwracalny).
**D12 WAŻ — IP/UA bez retencji i mechanizmu usuwania** (RODO: logi rosną w nieskończoność).
**D13 WAŻ — Tylko checkout.session.completed** (brak async_payment_succeeded,
dispute.created).
**D14 WAŻ — Brak samoobsługowego ponownego wysłania linku przez kupującego** (każdy
problem eskaluje do admina).
**D15 WAŻ — config.js statyczny** (rotacja klucza/zmiana adresu Workera = przebudowa
4 repozytoriów).
**D16 DROB — CSP gospodarza może blokować skrypty/fetch** (działa lokalnie, nie na
docelowej stronie).
**D17 DROB — Brak testów automatycznych** (regresja: zmiana w CRUD psuje kość).
**D18 DROB — „Mutanty przez kalibrator" bez definicji.**
**D19 DROB — Mechanizm linków nieprecyzyjny** (własny token vs presigned; konsekwencje
dla limitów i bezpieczeństwa).
**D20 DROB — Brak walidacji uploadu** (10 GB, HTML z JS = XSS, plik wykonywalny).
**Białe plamy D:** struktura repo i build · bundler czy vanilla · mechanizm podpisu
(HMAC? JWT? rotacja?) · izolacja właścicieli (osobne konta czy multi-tenant site_id) ·
specyfikacja tokena sesji i unieważnianie (blacklist?) · proces instalacji (migracje
czy ręczny SQL) · dokumentacja API · szczegóły CORS (preflight, credentials) · staging ·
replay attack (timestamp webhooka) · semantyka DELETE produktu · retry Brevo (backoff?) ·
waluty · wylogowanie (serwer czy tylko localStorage.removeItem) · błędy R2 przy
generowaniu linku.
