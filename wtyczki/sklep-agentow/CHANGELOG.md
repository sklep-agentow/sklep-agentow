# Changelog — Sklep Agentów

Wszystkie istotne zmiany w tej wtyczce. Format według
[Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), numeracja według
[Semantic Versioning](https://semver.org/lang/pl/).

Wersja schematu bazy jest podana przy każdej wersji, która go zmieniała —
migracja odpala się sama przy podniesieniu wersji wtyczki, bez deaktywacji
(`SKLEPAG_WERSJA_SCHEMATU`).

Wersje 0.x to okres budowy przed pierwszym wydaniem: wpisy do 0.9.8 są
skrótowe (jedna linia na zmianę), od 0.9.10 pełne. Numer 0.9.9 nie był
wydany osobno (podbicie pośrednie wewnątrz jednej zmiany).

---

## [Niewydane]

Nic jeszcze.

---

## [0.9.28] — 2026-08-31 · schemat bazy 9 (zamowienia.dor_*)

### Naprawione
- **Kompensacja po nieudanej wysyłce przywraca stan ostatniej DORĘCZONEJ wysyłki**, nie „zastany".
  Przy każdym doręczeniu maila wtyczka zapisuje ślad (token pobrania, termin, treść maila, skrót
  linku opinii — kolumny `dor_*`, schemat 9); gdy kolejna rotacja linku zawiedzie (cron ALBO przycisk
  „Wyślij ponownie"), wraca dokładnie ten stan, który kupujący ma w skrzynce. To zamyka znany
  wyjątek opisany przy 0.9.27: pad serwera w pół wysyłki + nieudane ponowienie mogły zostawić
  w bazie link, którego nie niósł żaden doręczony mail. Dla zamówień sprzed migracji (bez śladu)
  działa dotychczasowy zapas: stan sprzed rotacji. Pilnują tego nowe kroki strażnika regresji
  wysyłki, kalibrowane na kodzie sprzed naprawy (musiały na nim paść, zanim przeszły na nowym).
- **Status „błąd maila" zapisuje wyłącznie proces, który faktycznie władał wysyłką** (dowód
  własności tokenem w warunku zapisu). Dotąd pad procesu, któremu odebrano wysyłkę po 5 minutach,
  potrafił oznaczyć DORĘCZONY mail drugiego procesu jako `mail_blad` i podbić mu licznik prób —
  panel kłamał, choć kupujący dostał maila. Część 5 strażnika oczekuje teraz statusu `mail_w_toku`
  po padzie procesu-przegranego (właściciel wysyłki sam domknie).
- **Anonimizacja (RODO) czyści także ślad `dor_*`** — kopię treści maila i tokenu; pomiar skutku
  anonimizacji poszerzony o tokeny i ślad (pomiar tak szeroki jak czyszczenie).

### Zmienione
- **Sekcja CENA strony produktu:** panel „PO ZAKUPIE" po prawej stronie formularza — płatność
  kartą bez zakładania konta, mail z linkiem zaraz po płatności (ważność i limit pobrań
  ze stałych wtyczki, nie z ręki), aktualizacje i wsparcie kupionej kopii; dotąd prawa
  połowa sekcji była pusta.
- **Kafle listy produktów wyrównane do obu krawędzi** treści (`justify-content: space-between`) —
  prawy kafel nie wisi już 40 px od prawej krawędzi.
- **Wyłączony pływający „testing assistant" Stripe** (widoczny tylko na kluczu testowym) —
  nachodził na bąbelek ChatBota; na kluczu produkcyjnym i tak nie występuje.

---

## [0.9.27] — 2026-08-30 · schemat bazy 8 (bez zmian)

### Naprawione
- **Strona nieistniejącego produktu zwraca 404 także na HEAD.** Status ustawiał dopiero render treści
  (za późno dla nagłówków żądania HEAD → 200); teraz `template_redirect` ustawia 404 zanim cokolwiek
  wyjdzie, a przyjazna strona „produkt nie istnieje” renderuje się jak dotąd.
- **Zgoda z wtyczkami SEO gospodarza:** gdy działa Yoast (`WPSEO_VERSION`) lub Rank Math, wtyczka nie
  drukuje własnego canonicala (uniknięcie dwóch znaczników). Dokładniejszy warunek `false !== has_action`
  (priorytet 0 to też podpięcie).
- **Strona nieistniejącego produktu (404) bez canonicala także przy domyślnym WordPressie.** Gdy motyw
  zostawia `rel_canonical` WP, filtr `get_canonical_url` oddaje pusty adres — 404 nie wskazuje już listy
  sklepu jako „głównej” wersji; cudze strony z doklejonym `?sklepag_produkt=` zachowują własny canonical.
- Doprecyzowane zdanie w opisie wtyczki: bez stron „Sklep”/„Dziękujemy” wtyczka nie dokleja niczego do
  innych stron — sklep pokazuje się tam, gdzie właściciel sam wstawi skrót.

- **Awaria poczty przy automatycznym ponowieniu nie zabija już działającego linku.** Cron rotował token
  przed wysyłką; gdy mail padł, w bazie zostawał skrót, którego nie niósł żaden doręczony mail — kupujący
  z działającym starym linkiem tracił go na stałe. Teraz gałąź porażki przywraca token, termin i licznik
  sprzed rotacji (ta sama zasada co przy ręcznym „Wyślij ponownie”); pilnują tego części 4–5
  strażnika `resend-odnawia-limit-pobran` (część 4 pada na kodzie 0.9.26; część 5 pilnuje
  warunku własności dodanego w trakcie prac nad tym wydaniem i pada na kodzie sprzed niego).
  Znany wyjątek (do 0.9.28):
  gdy wysyłka urwie się w pół (pad serwera w jej trakcie) i ponowienie po przejęciu też zawiedzie,
  w bazie może zostać skrót niedoręczonego maila — ratunkiem przycisk „Wyślij ponownie” w panelu.
- **Komunikat po wpisaniu błędnego NIP mówi, co się stało z polem** — że wróciło do poprzednio
  zapisanej wartości i NIP trzeba wpisać ponownie (dotąd brzmiał, jakby pole się czyściło).

### Dodane
- Strażnik weryfikacji podpisu webhooka (poprawny/zły/rotowany sekret/przeterminowany znacznik/podmienione
  body/dwa podpisy) z próbą kontrolną — dotąd odrzut złego podpisu dowodziły tylko raporty audytu.

---

## [0.9.26] — 2026-08-30 · schemat bazy 8 (bez zmian)

### Naprawione
- **Canonical stron sklepu, gdy motyw wyłącza `rel_canonical` WordPressa.** Dotąd wtyczka tylko
  podmieniała adres w mechanizmie WordPressa, więc na motywie, który ten mechanizm wyłącza
  (`remove_action( 'wp_head', 'rel_canonical' )`), lista `/sklep/` i strony produktów nie miały ŻADNEGO
  znacznika canonical — dla Google `/sklep/produkt/x/` i `/sklep/?sklepag_produkt=x` były dwiema stronami
  jednej treści. Teraz `includes/front-seo.php` drukuje własny `<link rel="canonical">` dla listy i produktu,
  ale TYLKO gdy WordPress swojego nie drukuje (inaczej byłyby dwa). Cudze strony motywu nie są dotykane.
- **Pusta opcja strony sklepu nie „przejmuje” już cudzych stron.** `is_page( 0 )` jest w WordPressie prawdą
  na każdej stronie, więc gdy aktywacja nie zdołała utworzyć strony „Sklep”, wtyczka drukowała opis, OpenGraph
  i canonical sklepu w `<head>` każdej strony motywu. Jedna bramka (`sklepag_jest_strona_z_opcji`) we wszystkich
  siedmiu miejscach (meta, canonical, noindex „Dziękujemy” i ładowanie assetów — CSS/JS sklepu też trafiały na cudze strony). Strona nieistniejącego produktu (404) nie dostaje już meta listy.

---

## [0.9.25] — 2026-08-30 · schemat bazy 8

### Dodane
- **Płatność osadzona na stronie produktu** (`assets/platnosc.js`, Stripe.js „dahlia”): Checkout Session tworzona
  z `ui_mode=elements` i `return_url` (zamiast `success_url`/`cancel_url`), formularz e-mail + karta jako elementy
  Stripe w naszym panelu, Appearance API w kolorach strony (limonka na czerni), `confirm()` → powrót na „Dziękujemy”.
  Ten sam obiekt sesji, ten sam webhook i ta sama reconciliation co dotąd. Zapas: bez klucza publikowalnego albo bez
  JS formularz idzie zwykłym POST-em na hostowaną stronę Stripe (jak do 0.9.24) — obie drogi obsługuje jeden endpoint
  (`osadzona=1` → JSON z `client_secret`).
- **Ustawienia → Klucz publikowalny (pk_…)** — jawny, walidowany prefiksem.
- **Dane do faktury przy zakupie** (nazwa firmy, NIP, adres): nieobowiązkowe, komplet albo nic (ustawa o VAT
  art. 106e — faktura wymaga nazwy, NIP-u i adresu nabywcy); NIP z sumą kontrolną (`sklepag_nip_poprawny()`, wspólna
  z Ustawieniami); jadą w metadanych sesji, zapisywane w `zamowienia.dane_faktury`, w mailu (tekst i HTML) i w
  szczegółach zamówienia; anonimizacja RODO czyści je razem z e-mailem; szkic Polityki prywatności wymienia cel
  i podstawę. ⛔ Podanie NIP-u nie zmienia praw kupującego (art. 38a ustawy o prawach konsumenta).
- Mail bez danych do faktury: „chcesz fakturę? odpisz w ciągu 3 miesięcy” (art. 106b ust. 3 ustawy o VAT).
- Strażnik maila: C3 (termin 3 miesięcy), C3b (dane do faktury w obu wersjach) — 28 sprawdzeń.

### Naprawione (K10 — instalacja od zera przez niezależnego testera, 30.08)
- **„Dociągnij zaległe teraz” milczało o pominiętych sesjach**: kliknięte 71 s po zapłacie pomijało sesję młodszą niż
  2 minuty bez słowa, a na hostingu bez działającego WP-Cron (zepsuta pętla zwrotna) opłacony zakup „wisiał”
  20 minut. Teraz przycisk mówi: domknięte / pominięte jako młodsze niż 2 min (kliknij ponownie) / sprawdzone
  teraz i nieopłacone / sprawdzone chwilę temu (z przerwą) / błędy; wynik trafia też do Dziennika (`reconciliation_wynik`).
  Ręczny przycisk pyta Stripe o KAŻDĄ sesję od razu (przerwa dotyczy tylko zadania w tle) — na hostingu bez WP-Cron
  nikt inny nie zapyta.
- **Porzucone koszyki blokowały nowszą opłaconą sesję**: kolejka brała 20 NAJSTARSZYCH sesji „utworzona”, więc
  dwadzieścia „kliknął Kup i wyszedł” (u Stripe otwarte do 24 h) odkładało domknięcie prawdziwego zakupu na dobę.
  Teraz najnowsze najpierw, do 100 w biegu, a sesja sprawdzona i nadal nieopłacona dostaje 9 minut przerwy.
  Strażnik regresji z atrapą Stripe (5 sprawdzeń; na kodzie 0.9.24 pada 5/5).
- Instrukcje: kiedy WP-Cron nie działa (Kondycja witryny: pętla zwrotna) i co wtedy; „odczekaj 2 minuty od zapłaty”.

### Zmienione
- Schemat bazy **8**: kolumna `zamowienia.dane_faktury varchar(300)` (migracja `dbDelta` przy podbiciu wersji).
- Stripe.js i `platnosc.js` ładują się wyłącznie na stronie produktu (nie na liście sklepu); paleta panelu płatności
  czytana ze zmiennych CSS sklepu (jedno źródło kolorów).

---

## [0.9.24] — 2026-08-30

### Dodane
- **Mail po zakupie w wersji HTML** (`includes/mail-html.php`): nazwa sklepu w nagłówku, okładka
  produktu (ta sama co w podglądach linków), przycisk „Pobierz” jako główny element, karta
  zamówienia, blok zgody, kroki instalacji, gwarancja, opinia, stopka sprzedawcy — kolory strony
  (limonka na czerni), układ na tabelach, CSS inline, 600 px, ~11 KB. Wysyłka jako
  `multipart/alternative`: HTML + dotychczasowy tekst jako wersja zapasowa (typ treści nagłówkiem
  jednej wysyłki, nie filtrem globalnym — reszta poczty WordPressa bez zmian). Do 0.9.23 mail był
  czystym tekstem, bez brandingu.
- **Ustawienia → „Dane sprzedawcy”**: nazwa firmy, adres, NIP (suma kontrolna przy zapisie).
  Trafiają do stopki maila (tekst i HTML) razem z mailem kontaktowym jako adresem do reklamacji
  i faktur — potwierdzenie zakupu na trwałym nośniku ma nieść dane przedsiębiorcy (ustawa
  o prawach konsumenta art. 21 ust. 2 → art. 12 ust. 1). Pulpit ostrzega, gdy nazwa albo adres
  są puste.
- **Numer zamówienia w mailu** (`#id`, ten sam co w panelu) i **dopisek o fakturze**.
- Strażnik treści maila rozszerzony o wersję HTML i nowe elementy tekstu (27 sprawdzeń — liczba
  policzona komendą przy 0.9.28; pierwotny wpis zaniżał: „25"; próba
  kontrolna z dwiema podłożonymi wadami pada dokładnie w tych dwóch).

### Zmienione
- Zdanie o zgodzie w mailu bez formy męskiej: „Zgoda zaznaczona przy zakupie …”.

---

## [0.9.23] — 2026-08-30

### Dodane
- **`INSTRUKCJA.md` — instrukcja właściciela** (panel, produkty, zamówienia i statusy, mail
  i link do pobrania, opinie, RODO, dziennik, ustawienia, najczęstsze pytania), ze zrzutami
  panelu robionymi maszynowo (`zrzuty/`). Do tej pory sklep miał tylko instrukcję instalacji
  obok paczek — oba boty instrukcję użytkownika w paczce już miały.
- **`PONAD-SPECYFIKACJE.md`** — jawna lista wszystkiego, co sklep robi ponad literę zamówienia,
  każda pozycja z powodem; plus lista tego, czego świadomie nie ma.

### Naprawione
- **Świeży link do pobrania odnawia limit pobrań** — zarówno z „Wyślij ponownie”, jak i z automatycznego
  ponowienia po nieudanej wysyłce. Świeży link dostaje licznik pobrań od zera; dotąd
  zerowany był tylko termin, więc kupujący z wyczerpanym limitem — czyli ten, którego instrukcja po ten
  przycisk odsyła — dostawał nowy link i od razu „Limit pobrań wyczerpany”. Nieudana wysyłka przywraca
  stary link razem z jego licznikiem.

### Zmienione
- **Szkic Polityki prywatności wymienia adres IP przy pobraniu pliku** (dziennik zdarzeń, 90 dni).
  Szkic powstaje tylko przy pierwszej aktywacji — sklep zainstalowany wcześniej ma stary szkic;
  jeśli Polityka jest już opublikowana, dopisz to zdanie ręcznie (instrukcja, rozdział 6).
- **„Wyślij ponownie” tylko tam, gdzie zadziała.** Przycisk pokazuje się wyłącznie przy zamówieniu w stanie
  „mail wysłany” albo „błąd maila”; przy zwróconym lub spornym sklep mówi wprost, że ponowna wysyłka nie jest
  dostępna (wcześniej przycisk był widoczny, a kliknięcie kończyło się mylącym komunikatem o „domykaniu
  automatycznym”).
- Dwa komunikaty „Brak uprawnień.” w moderacji opinii wypisywane wprost (`esc_html`), a nie przez
  funkcję tłumaczenia bez domeny — wtyczka nie tłumaczy tekstów, więc wywołanie było niespójne
  (wskazał to Plugin Check). Zero zmian w działaniu.

---

## [0.9.22] — 2026-08-29

### Zmienione
- **Wydanie porządkowe.** Z komentarzy w kodzie i z dokumentacji technicznej usunięte
  wewnętrzne oznaczenia naszych raportów z testów — komentarz niesie powód, dla którego
  kod tak wygląda, a nie numer dokumentu. Zero zmian w działaniu sklepu.

---

## [0.9.21] — 2026-08-28

### Naprawione
- **Anonimizacja na żądanie RODO potwierdzała usunięcie, nie sprawdziwszy, czy zapis się udał.**
  Handler zapisywał do dziennika zdarzenie `anonimizacja` i przekierowywał właściciela
  z komunikatem powodzenia **bez patrzenia na wynik** obu zapytań `UPDATE`. Przy porażce
  zapisu właściciel widział potwierdzenie i mógł w dobrej wierze odpowiedzieć osobie, która
  złożyła żądanie, że jej dane usunięto — podczas gdy e-mail zostawał w bazie. Czyli
  niezrealizowane żądanie usunięcia danych z fałszywym dowodem wykonania w dzienniku.
  Teraz rozstrzyga **stan po zapisie**, a nie fakt wywołania: e-mail musi być pusty,
  `usunieto_dnia` ustawione. Przy niepowodzeniu zapisywane jest zdarzenie
  `anonimizacja_blad`, a właściciel dostaje wyraźne ostrzeżenie, żeby NIE potwierdzał
  usunięcia, dopóki nie powtórzy operacji.
- **Komunikat po nieudanym zapisie produktu twierdził, że wgrany plik został usunięty,**
  także wtedy, gdy usunięcie się nie powiodło (`wp_delete_file()` nie zwraca wyniku).
  Właściciel zostawał wtedy z plikiem-sierotą w katalogu paczek i przekonaniem, że go nie ma.
  Komunikat opisuje teraz stan faktyczny, zmierzony po operacji.

- **Adres kupującego mógł zostać w dzienniku zdarzeń mimo potwierdzenia usunięcia.**
  Anonimizacja czyściła najpierw adres w zamówieniu, a dopiero potem jego ślady w opisach
  zdarzeń. Gdy to drugie czyszczenie zawiodło, powtórzenie operacji było już bezskuteczne:
  adres w zamówieniu był pusty, więc nie było czego szukać w dzienniku, a wtyczka meldowała
  powodzenie. Ślad adresu zostawał wtedy w bazie na stałe. Teraz kolejność jest odwrotna —
  ślady wtórne giną PRZED adresem w zamówieniu, a jeśli którekolwiek czyszczenie zawiedzie,
  wtyczka przerywa **nie tknąwszy** adresu w zamówieniu, żeby powtórzenie miało z czego
  odtworzyć cały zakres pracy.
- **Potwierdzenie usunięcia wymaga teraz czystości we wszystkich trzech miejscach**, w których
  siedziały dane kupującego: adres w zamówieniu, jego ślady w opisach zdarzeń oraz imię
  i token przy opinii. Wcześniej sprawdzane było tylko pierwsze z nich.
- ⚠️ **Znana granica:** adres zawierający ukośnik przed znakiem „@" (dopuszczalny, ale w praktyce
  spotykany wyjątkowo) może zostać w dzienniku zdarzeń mimo potwierdzenia usunięcia. Jeśli
  obsługujesz takie adresy, sprawdź dziennik ręcznie po anonimizacji.

### Bezpieczeństwo
- Wszystkie naprawy należą do jednej klasy: **zapisywaliśmy skutek operacji, nie sprawdziwszy,
  czy operacja się udała** — i mierzyliśmy ten skutek w jednym miejscu z trzech.
  Dotyczy danych osobowych kupujących (RODO) oraz plików paczek.

### Sprawdzenie
- Naprawa anonimizacji ma automatyczny test, który **pada na wersji sprzed naprawy** i przechodzi
  po niej. Test podkłada awarię zapisu i sprawdza, że wtyczka w takiej sytuacji NIE potwierdza
  usunięcia, a po powtórzeniu operacji dane znikają ze wszystkich trzech miejsc.
- ⚠️ Naprawa komunikatu przy nieudanym zapisie produktu **nie ma jeszcze własnego testu** —
  jest sprawdzona odczytem kodu. Dopisanie testu wymaga wymuszenia nieudanego usunięcia pliku
  i jest zaplanowane na następną rundę.

## [0.9.20] — 2026-08-28

Domknięcie przelotu 27 punktów po produkcie: ostatnia rzecz z tej listy, którą widzi
kupujący, a której nie napisaliśmy my.

### Poprawione
- **Pola wymagane odzywały się do kupującego po ANGIELSKU.** Stały na samym `required`,
  więc komunikat rysowała przeglądarka własnym tekstem („Please check this box if you
  want to proceed.”, „Please fill out this field.”) — zależnie od jej ustawień
  językowych. Były to jedyne obcojęzyczne zdania, jakie widział kupujący, i pojawiały
  się w dwóch najgorszych możliwych momentach: przy płatności i przy wysyłaniu opinii.
  Sklep podaje teraz własne komunikaty w **obu** formularzach:
  – zakup: „Zaznacz zgodę, żeby przejść do płatności.”
  – opinia: „Wpisz imię — pokażemy je przy opinii.”, „Napisz swoją opinię.”,
    „Zaznacz zgodę na publikację, żeby wysłać opinię.”
  ⛔ Same blokady się NIE zmieniły: zakup bez zgody odrzuca także serwer
  (`includes/zakup.php`), niezależnie od JavaScriptu — zmienił się wyłącznie język
  komunikatów. Pilnuje tego test automatyczny, który sprawdza OBIE rzeczy naraz: że
  formularz dalej nie przechodzi i że człowiek czyta komunikat po polsku.

---

## [0.9.19] — 2026-08-27

Wydanie z ręcznego przejścia po produkcie: instalacja od zera i przeklikanie sklepu
oczami kogoś, kto nie widział tego kodu. Obie poprawki dotyczą rzeczy, których nie
mierzył żaden test automatyczny: utraty pracy właściciela i drogi do treści z klawiatury.

### Poprawione
- **Formularz produktu gubił wszystko, co właściciel wpisał, gdy zapis kończył się
  odrzutem** (brak paczki, zły plik, zła waluta). Formularz stoi za `admin-post` i wraca
  przekierowaniem, więc renderował się od nowa z bazy — dłuższy opis trzeba było pisać
  drugi raz. Odrzut odkłada teraz wysłane pola w przechowalni na 5 minut, a formularz
  odczytuje je JEDEN raz (odświeżenie pokazuje znów stan z bazy) i wyłącznie dla tego
  konta, które je wysłało. Pola pliku nie da się odtworzyć i tego nie udajemy.
- **Formularz OPINII kupującego gubił napisaną treść tak samo jak formularz produktu.**
  Odrzut walidacji (puste imię, brak zgody) odsyłał na czysty formularz, a przepadało do
  2000 znaków — i to treść KUPUJĄCEGO, której właściciel sklepu nie ma jak odtworzyć.
  Odrzut odkłada teraz imię, tytuł, ocenę i treść na 15 minut (kupujący wraca z maila,
  więc okno jest dłuższe niż w panelu), a powrót na formularz je oddaje. Link ze strony
  odrzutu mówi teraz wprost: „Wróć do formularza (Twoja treść czeka)".
  ⛔ **Zgoda na publikację NIE jest odtwarzana** — musi być świeżym gestem kupującego,
  inaczej odrzut „brak zgody" sam by ją za niego postawił.
- **Nagranie w galerii „Co jest w środku" było nieosiągalne z klawiatury.** Przycisk
  odtwarzania miał `hidden` do czasu, aż przeglądarka odrzuci autoodtwarzanie, a element
  `display:none` wypada z kolejności Tab — przy działającym nagraniu nie było ani
  widocznego sygnału, ani żadnej drogi bez myszy. Przycisk jest teraz widoczny zawsze
  (stonowany, pełny pod kursorem i przy fokusie), przełącza odtwarzanie i zatrzymanie,
  a jego etykieta mówi, co zrobi kolejne naciśnięcie. Bez JavaScriptu zachowanie jest
  jak dotąd: nagranie z `autoplay`, bez martwego przycisku.

### Zmienione
- Instrukcja instalacji ostrzega przed dwiema pułapkami, na których potyka się instalacja:
  limitem przesyłania plików mniejszym niż paczka wtyczki (z trzema drogami wyjścia)
  oraz ustawieniem Adaptive Pricing w Stripe, które kupującemu potrafi pokazać cenę
  w innej walucie z opłatą za przewalutowanie.

---

## [0.9.18] — 2026-08-27

### Poprawione
- **Drugi zakup tego samego produktu tuż po opłaceniu pierwszego nie dochodził do skutku**.
  Okno idempotencji (60 s, klucz `md5(IP|produkt_id)`) oddawało zapamiętany
  adres checkoutu bez sprawdzenia, czy tamta sesja nie została już opłacona — kupujący
  trafiał na opłacony ekran Stripe, bez pól karty i bez wyjaśnienia. Ponieważ okno jest
  kluczowane `REMOTE_ADDR`, dotyczyło to także DWÓCH RÓŻNYCH kupujących za jednym adresem
  (CGNAT operatora komórkowego, biuro za NAT-em).
  **Okno ochrony przed podwójnym kliknięciem skróciło się z 60 do 5 sekund** i to jest
  zmiana widoczna dla kupującego: dwa kliknięcia pod rząd nadal nie kończą się podwójnym
  obciążeniem, ale powrót na stronę produktu po kilkunastu sekundach (na przykład przyciskiem
  wstecz) rozpocznie już nową płatność. Tak być musi: informacja o zapłacie przychodzi ze Stripe
  z opóźnieniem, a do tego czasu płatność opłacona i płatność czekająca wyglądają u nas
  identycznie. O tym, czy oddać zapamiętany adres, rozstrzyga więc przede wszystkim WIEK
  okna; stan płatności jest drugim, dodatkowym warunkiem i czytamy go z własnej bazy, żeby
  awaria łączności ze Stripe nie blokowała zakupu.
  Zmiana ma test regresji sprawdzający trzy przypadki naraz, w tym ten z opóźnioną
  informacją o zapłacie.

## [0.9.17] — 2026-08-27

Wersja schematu bazy: **7** (bez zmian).

### Zmienione
- **Druga warstwa ochrony stanu zamówienia.** Ponowna wysyłka maila już wcześniej odmawiała
  pracy na zamówieniu zwróconym albo spornym; teraz nie pozwala na to również sam zapis do
  bazy. Jedna warstwa mniej do przeoczenia, gdyby reguły wejścia kiedyś się rozluźniły.
- Dokumentacja techniczna mówi wprost, że kolumna zapamiętująca stan sprzed zdarzenia
  finansowego jest używana przez obie drogi — zwrot pieniędzy i spór z bankiem — mimo nazwy
  sugerującej wyłącznie spór.

---

## [0.9.16] — 2026-08-27

Wersja schematu bazy: **7** (bez zmian; korzysta z istniejącej kolumny `status_przed_sporem`).

### Naprawione
- **Odrzucony zwrot nie zmyśla, że mail doszedł.** Zamówienie, do którego mail nigdy nie
  dotarł, po zwrocie pieniędzy i późniejszym odrzuceniu zwrotu przez bank dostawało status
  „mail wysłany" — wypadało z licznika nieudanych wysyłek i z ponowień, a właściciel był
  przekonany, że kupujący dostał plik. Teraz sklep zapamiętuje stan sprzed zwrotu i do niego
  wraca, dokładnie tak jak przy sporze z bankiem.
- **Obciążenie zwrotne nie ginie po cichu przy awarii łączności.** Gdy Stripe nie odpowiadał,
  sklep i tak potwierdzał przyjęcie powiadomienia, więc Stripe go nie ponawiał — a pieniądze
  zabrane przez bank zostawały w przychodzie na zawsze. Teraz, tak samo jak przy zwrocie,
  sklep prosi o ponowienie i rozróżnia awarię od cudzej płatności.

---

## [0.9.15] — 2026-08-27

Wersja schematu bazy: **7** (bez zmian względem 0.9.14).

### Naprawione
- **Odrzucony zwrot nie odejmuje kwoty drugi raz.** Gdy bank odrzucał zwrot, pieniądze
  wracały do przychodu — ale powtórzone powiadomienie o tym samym odrzuceniu odejmowało
  kwotę ponownie. Przy dwóch zwrotach częściowych panel pokazywał pełny przychód, choć
  część pieniędzy naprawdę zeszła z konta. Ścieżka cofnięcia ma teraz własny znacznik,
  niezależny od znacznika dopisania zwrotu.
- **Cudzy zwrot nie odpytuje Stripe w kółko.** Pamięć nietrafień („to nie nasza płatność",
  trzymana dobę) była omijana: druga funkcja pytała Stripe bezwarunkowo zaraz po tym, jak
  pierwsza odpowiedziała z pamięci. Przy awarii łączności jedno powiadomienie brało dwa
  limity czasu po 15 sekund zamiast jednego.

### Zmienione
- Bramka zgodności dokumentacji sprawdza też wiersz `SKLEPAG_WERSJA` w tabeli stałych —
  wcześniej patrzyła wyłącznie na nagłówek dokumentu i przepuszczała rozjazd w tabeli.
- Tabela punktów wejścia w dokumentacji technicznej znów jest jedną tabelą (pusta linia
  wyrzucała ostatni wiersz poza nią).

---

## [0.9.14] — 2026-08-26

Wersja schematu bazy: **7** (migracja odpala się sama, bez deaktywacji wtyczki).

### Naprawione
- **Opłacony zakup nie ginie, gdy baza zawiedzie w połowie.** Sesja płatności dostawała
  znacznik „opłacona" nawet wtedy, gdy zamówienia nie udało się zapisać, a mechanizm
  dociągania zaległych bierze wyłącznie sesje nieoznaczone — zakup przepadał bez śladu.
  Teraz znacznik zapada dopiero po potwierdzeniu, że zamówienie jest w bazie, a nieudany
  zapis zostawia wpis w dzienniku zdarzeń.
- **„Wyślij ponownie" nie odbiera kupującemu dostępu.** Nowy link zapisywał się przed
  wysyłką maila, więc nieudana wysyłka unieważniała stary, działający link, a nowy nigdy
  nie docierał. Przy niepowodzeniu poprzedni link i termin wracają.
- **Katalog na pliki produktów odtwarza się sam po aktualizacji wtyczki.** Powstawał tylko
  przy aktywacji, a aktualizacja „w miejscu" aktywacji nie odpala.
- **Wygasły formularz zakupu mówi po polsku i zostawia ślad.** Strona produktu podana
  z pamięci podręcznej niosła token starszy niż doba: przycisk zakupu był martwy dla
  wszystkich odwiedzających, komunikat przychodził po angielsku z rdzenia WordPressa,
  a właściciel nie miał tego skąd zobaczyć.
- **Komunikat o limicie wgrywanego pliku podaje limit, który naprawdę obowiązuje** —
  wcześniej cytował limit wtyczki (20 MB) także wtedy, gdy plik odrzucił serwer przy 2 MB.
- **Opinia bez powiązanego zamówienia nie przejdzie moderacji.** Zapytanie nie odróżniało
  „zamówienie jest i nie jest zanonimizowane" od „zamówienia nie ma".
- **Deinstalacja kasuje też dane tymczasowe wtyczki** (adres sesji płatności i skrót
  adresu IP z okna zabezpieczającego przed podwójnym kliknięciem).
- **Odmowa dostępu w panelu zwraca kod 403, nie 500** (siedem miejsc).
- **Włączanie i wyłączanie sprzedaży produktu nie gubi się przy dwukliku** — zapis jest
  warunkowany stanem widocznym na ekranie.

### Dodane
- **Zwroty i obciążenia zwrotne schodzą z przychodu.** Sklep przyjmuje ze Stripe
  powiadomienia o zwrocie (`refund.created`, `refund.updated`, `charge.refunded`) oraz
  o reklamacji płatności w banku (`charge.dispute.*`). Zwrot pełnej kwoty przestawia
  zamówienie na „zwrócone", zwrot częściowy pomniejsza przychód o swoją kwotę, a spór
  wygrany przywraca zamówienie do utargu. Każde zdarzenie zostawia wpis w dzienniku.
  Nowe kolumny: `payment_intent_id`, `zwrocono_grosze`, `zwrot_czas`.
- **Pulpit ostrzega, gdy sklep nie ma czym przyjąć pieniędzy** — brak klucza Stripe lub
  sekretu webhooka, a także seria nieudanych rozmów ze Stripe w ostatniej dobie.
- **Zapytanie z banku nie zeruje sprzedaży.** Prośba o wyjaśnienia nie zabiera pieniędzy,
  więc zamówienie zostaje w przychodzie — schodzi z niego dopiero pobranie środków albo
  przegrany spór. Spór wygrany i zwrot środków przywracają sprzedaż do stanu **sprzed sporu**:
  zamówienie, do którego mail nie doszedł, wraca do „błąd maila", a nie zostaje oznaczone
  jako wysłane (nowa kolumna `status_przed_sporem`).
- **Zwrot odrzucony przez bank wraca do przychodu** — zwrot ze stanem „nieudany" nie jest
  zwrotem, pieniądze zostały u sprzedawcy.
- **Dwa różne zwroty częściowe o tej samej kwocie liczą się osobno** — księgowanie idzie po
  identyfikatorze zwrotu, nie po samej kwocie.
- **Zwrot nie ginie, gdy Stripe nie odpowie** — sklep prosi wtedy o ponowne przysłanie
  powiadomienia, zamiast potwierdzać przyjęcie czegoś, czego nie zapisał.

### Bezpieczeństwo
- **Publiczne wejście zakupu ma limit żądań PRZED zapisem czegokolwiek do bazy** — inaczej
  obcy skrypt mógł jednym strumieniem żądań zapełnić właścicielowi dziennik zdarzeń.
- **Samonaprawa katalogu z plikami produktów odpala się wyłącznie, gdy ścieżki NIE MA.**
  Katalog chwilowo nieosiągalny (przeniesiony hosting, odpięty dysk) zostaje widocznym
  błędem — inaczej powstałby nowy, pusty katalog, a pliki produktów zostałyby sierotami.
- **Wskaźniki Pulpitu nie pokażą fałszywego zera**, gdy zapytanie padnie — zamiast „Przychód
  0,00 zł" pojawia się jawne ostrzeżenie. Odejmowanie kwot zabezpieczone przed błędem zakresu
  bazy, a okno „ostatniej doby" liczone czasem witryny, nie zegarem serwera bazy.

### Zmienione
- Kod i dokumentacja w paczce nie odwołują się już do wewnętrznej numeracji dokumentów
  roboczych — komentarze niosą powód zmiany, nie numer ustalenia.
- Dokumentacja techniczna: poprawiona liczba tabel, uzupełniona mapa plików o klasy list
  panelu, opinie i dostawcę mapy witryny, opisane statusy zwrotu i obsługiwane zdarzenia Stripe.

---

## [0.9.13] — 2026-08-25

### Naprawione
- **Lista `/sklep/` ma opis meta (description) oraz znaczniki Open Graph i Twitter** —
  z leadu strony i nazw produktów; strony produktów miały je od 0.8.0.
- **Mail po zakupie potwierdza zgodę kupującego** na dostarczenie treści cyfrowej
  przed upływem terminu odstąpienia — dosłowna treść zgody z zamówienia i jej data
  (wymóg dyrektywy 2011/83/UE, art. 8 ust. 7 lit. b). Nagłówek `Reply-To` wskazuje
  „mail kontaktowy właściciela" z ustawień (dotąd odpowiedzi szły na adres nadawcy).
  Akapit o gwarancji, gdy produkt ją ma. Ręczna ponowna wysyłka z panelu bierze
  adres strony produktu po jego identyfikatorze.
- **Strona produktu Segregatora:** układ fazy z nagraniem przechodzi na jedną kolumnę
  poniżej 56 rem — na tabletach 700–768 px dwa kafle miały 314–345 px szerokości.

---

## [0.9.12] — 2026-08-25

### Zmienione
- **Strona produktu Segregatora maila** w układzie wspólnym z ChatBotem: galeria
  „Co jest w środku" w trzech fazach, nagranie przycięte do obszaru panelu (plakat
  → wideo), kadry WebP zamiast długich zrzutów, pełne ekrany w oknie podglądu
  z pułapką fokusu (Tab / Shift+Tab). Media faz 640 KB zamiast 1,45 MB; waga
  strony 1538 → 741 KB.
- Hero = panel Segregatora na żywo + wiersz „Twoje reguły" z chipami zapalanymi
  w takt oznaczanych maili.
- Dawna galeria-siatka usunięta w całości (dane, gałąź renderu, style).

---

## [0.9.11] — 2026-08-25

### Zmienione
- **Strona produktu ChatBot LIVE:** hero z panelem tego bota na żywo (ten sam
  markup co na liście), galeria „Co jest w środku" w trzech fazach (miniatura
  strony z bąbelkiem i nagranie przycięte do okna czatu · formularz i tok
  zgłoszenia · kadry panelu), pełny ekran w oknie podglądu (podpis, Esc, blokada
  przewijania, fokus), sekcje pełnej szerokości, kroki jako karty, FAQ w siatce,
  finał z duetem maskotek. Waga strony 1293 → 507 KB.
- Pigułka zakupu przyklejona do ekranu z własnym wejściem i wyjściem; jedno
  miejsce zakupu na stronie z podświetleniem po przejściu; strażnik odtwarzania
  nagrania.

### Naprawione
- Poprawki: wstępne ładowanie galerii, zachowanie pigułki, strażnik
  nagrania, numeracja faz.

---

## [0.9.10] — 2026-08-25

### Zmienione
- **Lista `/sklep/` w rodzinie ruchu strony gospodarza:** hero dwukolumnowe
  z panelem „nocnej zmiany" (pętla z zatwierdzonych tekstów), własny canvas
  z galaktyką (klatka statyczna przy „ogranicz ruch", poświata CSS bez JS jako
  zapasowa), panel jako szkło z pochyleniem 3D i paralaksą, karty z hoverem
  łańcuchowym, kroki z torem sygnału, „dla kogo" i FAQ na pełną szerokość,
  baner końcowy.
- Wersja lekka dla słabszych komputerów: bez rozmycia tła nad żywym canvasem,
  limit cząstek, bezpiecznik wydajności.
- Galaktyka czytelna w spoczynku, ilustracja ChatBota z kolumną bazy pytań,
  perspektywa panelu widoczna bez najechania, znacznik przy pytaniach FAQ.

---

## Wcześniejsze wersje (skrót)

### [0.9.8] — 2026-08-25 · schemat bazy 4 → 5
- Mechanizm opinii kupujących: tabela `opinie`, jednorazowy token z maila po
  zakupie, formularz opinii, moderacja w panelu, render zaakceptowanych opinii
  na stronie produktu.

### [0.9.7] — 2026-08-25
- Opinie na stronach produktów: awatar, gwiazdki, oznaczenie zweryfikowanego
  zakupu; nagrania sprzedażowe obu botów.

### [0.9.6] — 2026-08-25
- Blok zakupu w nowym układzie; gwarancja satysfakcji 14 dni na stronach produktów.

### [0.9.5] — 2026-08-24
- Hero z ilustracją w brandzie strony, galeria „Co jest w środku", oprawa jasnych
  zrzutów ekranu.

### [0.9.4] — 2026-08-24
- Sekcje strony produktowej: „W paczce", zestawienie z botem generycznym, opis
  mechanizmu prawdziwych opinii, gwarancja (wyłączona do czasu decyzji).

### [0.9.3] — 2026-08-20/21
- Treść strony Segregatora poprawiona (skrzynka IMAP, Microsoft 365) — mówiła
  nieprawdę na niekorzyść produktu.
- Porządki w komentarzach kodu, bez zmian działania.

### [0.9.2] — 2026-08-20
- Licencja GPL w paczce; opisy produktów napisane pod sprzedaż; adresy strony
  w nagłówku wtyczki; drobne poprawki.

### [0.9.1] — 2026-08-20
- Siatka strony w stylu gospodarza; sekcje pojawiające się przy przewijaniu.

### [0.9.0] — 2026-08-19
- Zrzuty żywych botów na kartach i w sekcji „Jak to wygląda"; rozbudowa listy
  sklepu (pasek konkretów, „Od zakupu do działania", „Dla kogo", pytania); własne
  ilustracje SVG na kartach; końcowe wezwanie do zakupu i hierarchia linków;
  link „Sklep" w nawigacji strony.

### [0.8.2] — 2026-08-19
- Po teście instalacji od zera: ostrzeżenie w kokpicie przy „zwykłych"
  odnośnikach bezpośrednich; „Zamówienia" przemianowane na „Pulpit"; instrukcja
  instalacji uzupełniona.

### [0.8.1] — 2026-08-19
- Szlif stron: ikony kart, jawna informacja o braku opinii, separatory FAQ,
  pasek na telefonie.

### [0.8.0] — 2026-08-19
- Strona produktu (jedna strona WordPressa + adres `/sklep/produkt/…/`) z sekcjami
  sprzedażowymi; SEO: tytuł, opis, canonical, Open Graph, dane strukturalne
  (produkt, okruszki, FAQ), okładki, mapa witryny produktów, `noindex` na stronie
  „Dziękujemy".

### [0.7.0] — 2026-08-18
- Fundament frontu w klimacie strony gospodarza (wyspa, tokeny z motywu, karty)
  i lista sklepu.

### [0.6.1] — 2026-08-18
- Wynik zapisu do bazy przy produktach sprawdzany (awaria zapisu = błąd, nie fałszywy
  sukces); osierocony plik sprzątany; dokumentacja zgodna z kodem.

### [0.6.0] — 2026-08-18
- Produkty w panelu: lista, formularz, wgrywanie paczki ZIP do katalogu chronionego,
  walidacje; kopia danych produktu w zamówieniu nietykalna przy edycji.

### [0.5.2] — 2026-08-18
- Baner trybu testowego przy tytule, waluta na kartach.

### [0.5.1] — 2026-08-18
- Szlif panelu: pasek statusów, kolumna akcji, powiadomienie o trybie testowym,
  hierarchia wskaźników.

### [0.5.0] — 2026-08-18 · schemat bazy 3 → 4
- Widok zamówienia: szczegóły, treść wysłanego maila, ponowna wysyłka ze świeżym
  linkiem (stary link przestaje działać), anonimizacja danych kupującego
  (kolumna `usunieto_dnia`).

### [0.4.0] — 2026-08-18
- Pulpit: wskaźniki z porównaniem do poprzedniego okresu, rozkład w czasie, tabela
  zamówień, eksport CSV, samonaprawa zaległych zamówień z budżetem czasu.

### [0.3.0] — 2026-08-18
- Ustawienia sklepu (Settings API): klucze Stripe, nadawca maili, mail kontaktowy,
  waluta, zachowanie danych przy odinstalowaniu; sekrety bez autoładowania.

### [0.2.0] — 2026-08-18 · schemat bazy → 3
- Dziennik logowań i zdarzeń w kokpicie; wykładnicze opóźnienie po nieudanych
  próbach logowania; limit prób z jednego adresu; retencja 90 dni.

### [0.1.0] — 2026-08-18
- Szkielet: własne tabele (dbDelta) z wersją schematu, strony „Sklep" i „Dziękujemy"
  oraz szablony prawne, katalog chroniony z próbą zapisu, odinstalowanie z opcją
  (dane domyślnie zostają).
- Zakup: Stripe Checkout, webhook z podpisem, atomowe przejścia stanu, dwie drogi
  domykania (webhook + zadanie cykliczne), brama pobrań (token, termin, limit),
  strona „Dziękujemy" odpytująca status.
- Zadanie cykliczne podnosi także zamówienia w stanach błędu i w toku wysyłki;
  limit żądań liczony atomowo w tabeli; limity pobrań i ważność linku jako stałe.
