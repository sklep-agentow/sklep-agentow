# Instrukcja instalacji — Sklep Agentów + 2 boty (WordPress)

Dla właściciela strony — instalacja od zera do pierwszego testowego zakupu.
Czas: ~30 minut. Nic tu nie wymaga programowania.

## Co dostajesz

Trzy wtyczki WordPressa (pliki .zip):

1. **`sklep-agentow.zip`** — sklep: strony „Sklep" i „Dziękujemy", płatności
   Stripe (TRYB TESTOWY — żadne prawdziwe pieniądze nie płyną), mail po
   zakupie z linkiem do pobrania, panel w kokpicie WordPressa (zamówienia,
   produkty, dziennik logowań, ustawienia).
2. **`chatbot-live.zip`** — produkt: bot czatu na stronę (bąbelek).
3. **`segregator-maila.zip`** — produkt: segregator poczty na próbkach CSV.

Boty mają WŁASNE instrukcje w środku paczek — ta instrukcja prowadzi przez
instalację całości i uruchomienie sklepu.

## Wymagania

- WordPress 6.7 lub nowszy · PHP 8.1 lub nowszy (sprawdzisz w:
  Narzędzia → Zdrowie witryny → Informacje).
- Konto Stripe (darmowe) — do testów wystarczy samo konto, BEZ aktywacji
  płatności i weryfikacji firmy: <https://dashboard.stripe.com/register>.
- Dostęp administratora do kokpitu WordPressa (wp-admin).
- **Limit wysyłki plików: co najmniej 8 MB.** Paczka sklepu waży około 4 MB, a wiele
  hostingów ma domyślnie ustawione 2 MB — wtedy WordPress odmówi wgrania wtyczki.
  Sprawdzisz to w: Narzędzia → Zdrowie witryny → Informacje → Serwer, pozycja
  „Maksymalny rozmiar przesyłanego pliku". Jeśli jest mniejszy niż 8 MB, zajrzyj do
  sekcji „Najczęstsze problemy" na końcu tej instrukcji — jest tam, co zrobić.

## Krok 0 — bezpośrednie odnośniki (1 min, WAŻNE na świeżym WordPressie)

Świeży WordPress ma Ustawienia → Bezpośrednie odnośniki na „Zwykłe" — wtedy
strona sklepu pokazuje po cichu bloga zamiast sklepu, a płatności nie mogą
zgłosić się do Twojej strony (webhook zwraca 404). ZANIM zainstalujesz sklep:

1. Kokpit → Ustawienia → Bezpośrednie odnośniki.
2. Wybierz dowolną strukturę INNĄ niż „Zwykłe" (polecamy „Nazwa wpisu").
3. Zapisz zmiany.

Jeśli o tym zapomnisz — wtyczka pokaże czerwone ostrzeżenie w kokpicie
z linkiem do tego ustawienia.

## Krok 1 — instalacja wtyczki sklepu (5 min)

⚠️ **NAJPIERW SPRAWDŹ, ILE WOLNO WGRAĆ — to zatrzymuje instalację najczęściej.**
Paczka `sklep-agentow.zip` waży około **4 MB**, a świeży WordPress na wielu
hostingach przyjmuje pliki **do 2 MB**. Wtedy Krok 1 kończy się komunikatem
w rodzaju „Przesłany plik przekracza dyrektywę upload_max_filesize" i dalej
nie da się przejść.

- Limit zobaczysz w: Kokpit → **Media → Dodaj nowy** (napis „Maksymalny rozmiar
  pliku przesyłania") albo Narzędzia → Zdrowie witryny → Informacje → Serwer.
- Za mało? Masz trzy drogi, od najprostszej:
  1. **Poproś hosting** o podniesienie `upload_max_filesize` i `post_max_size`
     do 32 MB — na większości paneli to jeden przełącznik, często zrobisz to sam
     w sekcji PHP.
  2. **Wgraj wtyczkę przez menedżer plików albo FTP**: rozpakuj `sklep-agentow.zip`
     i skopiuj folder `sklep-agentow` do `wp-content/plugins/`, potem włącz ją
     w Kokpit → Wtyczki. Limit przesyłania w ogóle wtedy nie obowiązuje.
  3. Jeśli hosting nie pozwala na żadne z powyższych — daj znać, przygotujemy
     paczkę dzieloną.

1. Kokpit → Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer →
   wybierz `sklep-agentow.zip` → Zainstaluj → **Włącz**.
2. Przy włączeniu wtyczka SAMA tworzy strony: „Sklep", „Dziękujemy za zakup"
   oraz dwa SZKICE do uzupełnienia: „Regulamin sklepu (SZABLON)" i „Polityka
   prywatności sklepu (SZABLON)" — wypełnij je swoimi danymi i opublikuj,
   zanim pokażesz sklep światu.
3. W menu kokpitu pojawia się pozycja **Sklep Agentów**.

## Krok 2 — klucze Stripe (10 min)

1. Zaloguj się do Stripe → upewnij się, że przełącznik **Test mode**
   (prawy górny róg Dashboardu) jest WŁĄCZONY.
2. Dashboard → Developers → API keys → skopiuj **Secret key**
   (zaczyna się od `sk_test_…`).
3. Kokpit → Sklep Agentów → Ustawienia → sekcja „Stripe (tryb testowy)":
   - **Klucz sekretny (sk_test_…)** — wklej klucz z punktu 2.
   - **Sekret webhooka (whsec_…)** — patrz Krok 3 (zależy od tego, gdzie
     stoi strona).
   - **Klucz publikowalny (pk_test_…)** — obok klucza sekretnego („Publishable
     key”). Z nim formularz karty jest na Twojej stronie produktu, w jej kolorach;
     bez niego kupujący przechodzi na stronę płatności Stripe (też działa).
4. Uzupełnij też: **Nazwa nadawcy** i **Adres nadawcy** (od kogo przychodzą
   maile po zakupie), **Mail kontaktowy właściciela** (pokazywany kupującym
   przy problemach z linkiem), **Dane sprzedawcy** (nazwa firmy, adres, NIP —
   stopka maila po zakupie; bez nich Pulpit pokazuje ostrzeżenie), **Domyślna
   waluta nowych produktów** (np. PLN).

## Krok 3 — webhook (informacja o opłaceniu zakupu)

Stripe po opłaceniu wysyła sygnał („webhook") na Twoją stronę — z niego
powstaje zamówienie i mail. Skąd wziąć sekret webhooka:

**A. Strona na hostingu z publicznym adresem (docelowo):**
1. Dashboard Stripe (Test mode) → Developers → Webhooks → Add endpoint.
2. Adres endpointu: `https://TWOJA-DOMENA/wp-json/sklepag/v1/webhook`
3. Zdarzenia do nasłuchu — zaznacz **OBA**:
   - `checkout.session.completed` — zwykła płatność kartą,
   - `checkout.session.async_payment_succeeded` — płatności, które potwierdzają
     się z opóźnieniem (m.in. **BLIK**, przelewy).

   ⚠️ Podpięcie tylko pierwszego nie psuje zakupu, ale przy BLIK-u kupujący czeka
   na mail do najbliższego dociągania (do 10 minut) zamiast dostać go od razu.
4. Po utworzeniu skopiuj **Signing secret** (`whsec_…`) → wklej w
   Ustawieniach sklepu.

**B. Komputer lokalny / środowisko testowe bez publicznego adresu:**
Serwery Stripe nie widzą localhosta — użyj oficjalnego Stripe CLI:
```
stripe listen --forward-to http://localhost:PORT/wp-json/sklepag/v1/webhook
```
Sekret webhooka pokaże `stripe listen --print-secret` (⚠️ to INNY sekret niż
z Dashboardu — wklej ten z CLI).

**Siatka bezpieczeństwa:** nawet gdy webhook nie dochodzi, sklep co 10 minut
SAM dopytuje Stripe o opłacone sesje i domyka zakupy (wolniej, ale pewnie).
Ręczny przycisk „dociągnij zaległe" jest na dashboardzie sklepu.

## Krok 4 — produkty (10 min)

1. Kokpit → Sklep Agentów → Produkty → **Dodaj produkt**.
2. Wypełnij: nazwa (np. „ChatBot LIVE"), opis krótki (na listę sklepu),
   opis pełny, cena, waluta.
3. **Plik paczki (ZIP)** — wgraj zip bota (np. `chatbot-live.zip`).
   Plik ląduje w katalogu chronionym pod losową nazwą — nikt nie pobierze
   go bez opłaconego zamówienia.
   Ciekawostka: dla obu botów strona produktu w sklepie sama pokazuje
   rozbudowaną treść sprzedażową (sekcje, FAQ, zrzut rozmowy) — to wbudowana
   prezentacja produktów, nie musisz jej pisać; Twój opis z formularza
   uzupełnia listę sklepu.
4. Zaznacz **„Produkt widoczny i kupowalny w sklepie"** → Zapisz.
   (Bez potwierdzonego pliku nie da się włączyć sprzedaży — to celowe.)
5. Powtórz dla drugiego bota.

## Krok 5 — pierwszy testowy zakup (5 min)

1. Wejdź na stronę **Sklep** → wybierz produkt → „Zobacz szczegóły" →
   zaznacz zgodę → **Kup teraz (płatność testowa)**.
2. Pod przyciskiem rozwija się panel płatności (gdy wpisałeś klucz publikowalny)
   albo otwiera strona Stripe (gdy nie). Podaj dowolny mail i testową kartę:
   **4242 4242 4242 4242**, dowolna przyszła data ważności, dowolne CVC.
   Kupujący na firmę może rozwinąć „Dane do faktury” i podać nazwę, NIP i adres —
   trafią do maila i do szczegółów zamówienia (fakturę wystawiasz sam).
3. Po płatności wrócisz na „Dziękujemy" — strona sama odświeża status aż do
   „Mail wysłany".
4. Sprawdź: Kokpit → Sklep Agentów → **Pulpit** — zakup jest na liście
   zamówień (razem ze statystykami), a treść maila obejrzysz w szczegółach
   zamówienia (przycisk „Wyślij ponownie" gdyby mail nie dotarł).

⚠️ **Wyłącz Adaptive Pricing w Stripe (krok, nie ciekawostka)** — inaczej kupujący
z zagranicy zobaczy cenę przeliczoną na swoją walutę (np. EUR), co w teście
instalacji zmyliło testera przy pierwszym zakupie. Gdzie: Dashboard Stripe →
Settings → Payments → Adaptive Pricing (układ menu Stripe bywa przestawiany —
w razie czego wpisz "Adaptive Pricing" w wyszukiwarkę ustawień Dashboardu).

⚠️ **Ekran Stripe potrafi zaproponować INNĄ WALUTĘ niż Twoja cena.** Sklep wysyła
do Stripe walutę produktu (np. 199 PLN) i tyle Ci wpływa — ale Stripe ma ustawienie
**Adaptive Pricing**, które kupującemu z zagranicy pokazuje cenę przeliczoną na jego
walutę, z doliczoną opłatą za przewalutowanie (przy naszym pomiarze: euro i około 4%).
Kupujący widzi wtedy kwotę, której nie ma na Twojej stronie, i łatwo to przeoczyć.

- Sprawdzisz i wyłączysz to w: Dashboard Stripe → **Settings → Payments → Adaptive
  Pricing** (w trybie testowym i produkcyjnym osobno).
- Zostawiasz włączone świadomie? Napisz na stronie produktu, że kupujący spoza strefy
  złotego zobaczy cenę w swojej walucie — to Twoja decyzja handlowa, nie usterka sklepu.

⚠️ **Maile:** wtyczka używa standardowej wysyłki WordPressa. Na hostingu
wysyłką rządzi hosting — jeśli maile nie wychodzą, skontaktuj się z pomocą
hostingu albo skonfiguruj SMTP (dowolną wtyczką SMTP). W środowisku
testowym bez poczty treść maila ZAWSZE widać w panelu przy zamówieniu.

## Krok 6 — instalacja botów jako wtyczek Twojej strony (opcjonalnie)

Boty to produkty sklepu — ale możesz ich też używać u siebie:
Wtyczki → Dodaj nową → wyślij `chatbot-live.zip` / `segregator-maila.zip` →
Włącz. Dalej prowadzą instrukcje W paczkach botów (konfiguracja w kwadrans).

## Ustawienia produkcyjne (gdy strona idzie w świat)

- **Dodaj link „Sklep" do menu strony** — bez niego goście nie trafią do
  sklepu z nawigacji. Na motywie z menu WordPressa: Wygląd → Menu → dodaj
  pozycję wskazującą stronę „Sklep". Na motywie z nawigacją wpisaną na
  sztywno w kod (tak jest na stronie Automatic AI po konwersji): dopisz
  w `header.php` obok pozycji „FAQ” — w OBU listach (nawigacja główna
  i mobilna) — gotowe fragmenty. ⚠️ **Edytuj plik przez SFTP albo menedżer
  plików hostingu (droga główna).** Wbudowany edytor WordPressa (Wygląd →
  Edytor plików motywu) przed zapisem sprawdza, czy strona odpowiada sama
  sobie — na świeżym serwerze bez tej pętli zwrotnej pokazuje „Unable to
  communicate back with site” i **cofa zapis bez śladu** (tak wyszło w teście
  instalacji K10). Fragmenty do wklejenia:

  Nawigacja główna (desktop), zaraz po `<li>` z FAQ:

  ```html
  <li class=""><a class="text-sm transition-colors hover:text-fg text-steel" href="/sklep/">Sklep</a></li>
  ```

  Nawigacja mobilna (szablon `tpl-menu-mobilne`): wklej PO OSTATNIEJ pozycji
  listy (w motywie kolegi ostatnia to FAQ z numerkiem `08`). Gotowiec niżej ma
  już obie wartości przesunięte o jeden względem tej pozycji (numerek `09`,
  `--menu-delay: 0.45s`); jeśli u Ciebie ostatnia pozycja ma inny numerek lub
  inne opóźnienie, przesuń o jeden numerek i o 0,05 s względem niej:

  ```html
  <li class="menu-item-in" style="--menu-delay: 0.45s;"><a class="flex items-baseline gap-4 py-4 text-3xl font-semibold tracking-tight transition-colors border-b border-line hover:text-volt" href="/sklep/"><span class="font-mono text-xs text-steel">09</span>Sklep</a></li>
  ```

  Nawigacja w STOPCE (`footer.php`, lista „Nawigacja"), zaraz po `<li>` z FAQ:

  ```html
  <li><a class="text-sm text-steel transition-colors hover:text-fg" href="/sklep/">Sklep</a></li>
  ```
- **Ustawienia → Czytanie → ODZNACZ opcję zniechęcania wyszukiwarek do
  indeksowania** (oficjalny angielski tekst opcji: „Discourage search engines
  from indexing this site"; polskie brzmienie zależy od tłumaczenia Twojego
  WordPressa) —
  bez odznaczenia WordPress nie wystawia mapy strony (sitemap) i Google nie
  zaindeksuje sklepu.
- **Nagłówki bezpieczeństwa i CSP to robota serwera, nie wtyczki.** Poproś
  informatyka (albo ustaw w panelu hostingu / Cloudflare), żeby strona wysyłała
  nagłówki `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy` i `X-Frame-Options: SAMEORIGIN`. ⚠️ Jeśli strona ma
  **Content-Security-Policy**, musi dopuszczać Stripe — inaczej formularz karty
  na stronie produktu przestanie działać: `script-src 'self' https://js.stripe.com`,
  `frame-src https://js.stripe.com https://hooks.stripe.com`,
  `connect-src 'self' https://api.stripe.com`; krótkie skrypty osadzone w stronie
  (konfiguracja formularza) wymagają `'unsafe-inline'` albo nonce'a/hasha. Po każdej
  zmianie CSP zrób testowy zakup kartą testową.
- Rozważ ochronę logowania na poziomie serwera (np. fail2ban albo reguła
  WAF na `wp-login.php`) — wtyczka spowalnia zgadywanie haseł i ogranicza
  próby z jednego adresu, ale zmasowany atak z WIELU adresów to robota dla
  zapory serwera, nie wtyczki.
- Jeśli na stronie działa wtyczka bezpieczeństwa/WAF — dopuść publiczny
  POST na `…/wp-json/sklepag/v1/webhook` (autoryzacją jest podpis Stripe).

## Test a produkcja — co trzeba zmienić, żeby sprzedawać naprawdę

Po instalacji sklep pracuje w trybie testowym Stripe (baner na stronach sklepu
o tym przypomina). Przejście na prawdziwe płatności to osobna decyzja.
Poniżej najważniejsze kroki — punkt 2 przełącza sam napisy o trybie, ale
pozostałe kroki trzeba zrobić osobno, inaczej sklep przyjmie pieniądze
i nie doręczy zakupu:

1. **Aktywacja konta Stripe** — weryfikacja tożsamości i rachunek bankowy,
   po stronie Stripe.
2. **Klucze produkcyjne** `sk_live_…` w Ustawieniach sklepu, w miejsce
   testowych `sk_test_…`. ⭐ To jednocześnie **jedyny przełącznik trybu**:
   z prefiksu tego klucza sklep sam orzeka, czy pisać o płatności testowej
   (patrz punkt 6).
3. **Nowy webhook produkcyjny** z własnym sekretem `whsec_…` — sekret z trybu
   testowego NIE działa na produkcji (to osobne środowiska Stripe).
4. **Wysyłka maili przez hosting lub SMTP** — bez tego mail po zakupie NIE
   wyjdzie, a kupujący nie dostanie linku do pobrania.
5. **Regulamin i Polityka prywatności** wypełnione Twoimi danymi sprzedawcy
   i opublikowane (przy instalacji powstały jako szkice).
6. **Oznaczenia trybu testowego** — znikają **same**, razem z podmianą klucza
   z punktu 2. Sklep nie ma osobnego przełącznika trybu: pyta o prefiks klucza
   sekretnego. `sk_test_…` = tryb testowy, cokolwiek innego (w tym `sk_live_…`,
   a także brak klucza) = sklep **nie** zapewnia, że pieniędzy nie pobiera.

   Zdejmowane są wszystkie naraz — kupujący nie zobaczy słowa „testowa”:
   - baner na liście produktów, na stronie produktu i na stronie „Dziękujemy
     za zakup" (ekran oglądany PO ZAPŁACENIU),
   - dopisek „(płatność testowa)" na przyciskach „Kup teraz" i „Zapłać”
     (także po nieudanej próbie płatności, gdy napis odtwarza przeglądarka),
   - notka z numerem karty testowej przy formularzu zakupu,
   - pytanie „Co znaczy tryb testowy?" w sekcji FAQ (ustępuje miejsca pytaniu
     o bezpieczeństwo płatności),
   - dopisek o niepobranej płatności w **obu wersjach** e-maila po zakupie.

   Twoje własne ekrany też: znacznik przy tytule Pulpitu, dopisek przy kwocie
   w szczegółach zamówienia i tytuł sekcji Stripe w Ustawieniach.

   ⛔ Zmiana klucza zdejmuje **napisy**, nie całą resztę tej listy: bez kroków
   3–5 sklep przyjmie prawdziwe pieniądze i nie doręczy zakupu. Jeśli cokolwiek
   z tej listy budzi Twoją wątpliwość — **napisz do nas** przed pierwszą
   prawdziwą sprzedażą.
7. **Wtyczka bezpieczeństwa lub WAF** (jeśli działa na stronie) — dopuść
   publiczny POST na `…/wp-json/sklepag/v1/webhook`; autoryzacją jest podpis
   Stripe, nie logowanie.
8. **Podatki (VAT/OSS)** — to sprawa Twojej księgowości. Sklep przekazuje do
   Stripe cenę produktu i nie wylicza podatku za Ciebie.
9. **Boty z prawdziwym AI** (jeśli używasz ich u siebie) — każdy bot ma
   własne ustawienia i własny klucz API dostawcy AI; w paczkach botów są
   osobne instrukcje.

## Najczęstsze problemy

- **Zakup opłacony, a na „Dziękujemy” wisi „Przetwarzamy…” i Pulpit pokazuje 0 zamówień** —
  webhook nie dociera (zły sekret, Krok 3) ORAZ zadania w tle WordPressa nie chodzą. Sprawdź:
  Kokpit → Narzędzia → Kondycja witryny: „Żądanie zwrotne (loopback) nie powiodło się” /
  „zadania zaplanowane”. Do czasu naprawy: Pulpit → **Dociągnij zaległe teraz** (odczekaj 2 minuty
  od zapłaty; przycisk mówi, ile domknął, a ile pominął jako zbyt młode). Trwale: poproś hosting
  o cron systemowy wołający `wp-cron.php` co 10 minut (wtedy w `wp-config.php`
  `define( 'DISABLE_WP_CRON', true );`) — standardowa rada WordPressa, nie wymóg sklepu.
- **Przy wgrywaniu wtyczki: „Przesłany plik przekracza dyrektywę upload_max_filesize"**
  (po angielsku: „The uploaded file exceeds the upload_max_filesize directive in php.ini")
  → limit wysyłki plików na Twoim hostingu jest mniejszy niż paczka sklepu (~4 MB).
  Masz trzy wyjścia, od najprostszego:
  1. W panelu hostingu znajdź ustawienia PHP i podnieś **upload_max_filesize** oraz
     **post_max_size** do 32 MB. U większości dostawców to jedno pole do zmiany.
  2. Jeśli nie widzisz takiej opcji — napisz do obsługi hostingu jedno zdanie:
     „Proszę o podniesienie upload_max_filesize i post_max_size do 32 MB".
  3. Gdy żadne z powyższych nie wchodzi w grę — rozpakuj paczkę na komputerze i wgraj
     powstały katalog przez menedżer plików albo FTP do `wp-content/plugins/`, a potem
     włącz wtyczkę w zakładce Wtyczki. Efekt jest ten sam.
- **Przy wgrywaniu wtyczki: „Nie udało się przenieść przesłanego pliku do wp-content/uploads/…"**
  → katalog, w którym WordPress trzyma przesyłane pliki, ma złego właściciela i serwer nie
  może do niego pisać. To ustawienie hostingu, nie wtyczki. Napisz do obsługi: „Proszę
  ustawić właściciela katalogu wp-content/uploads na użytkownika serwera WWW".

- **Zakup „wisi" na Dziękujemy** → webhook nie dochodzi. Sprawdź sekret
  webhooka (Dashboard vs CLI — to RÓŻNE sekrety!). Sklep i tak domknie
  zakup w ~10 minut (dociąganie zaległych) — albo od ręki: dashboard
  sklepu → „dociągnij zaległe".
- **Mail nie przyszedł** → treść maila jest w panelu przy zamówieniu
  („Wyślij ponownie" po naprawie poczty); na hostingu skonfiguruj SMTP.
- **„Nie można włączyć sprzedaży bez pliku"** → produkt musi mieć wgrany
  i potwierdzony zip zanim pójdzie do sprzedaży — wgraj plik w edycji
  produktu.
- **Strona produktu daje 404 po przenosinach** → Ustawienia → Bezpośrednie
  odnośniki → Zapisz (odświeżenie reguł adresów).
- **Stripe Dashboard pokazuje błędy 500 przy `checkout.session.completed`,
  choć zamówienie powstało** → tak wygląda środowisko BEZ działającej poczty:
  sklep celowo odpowiada Stripe'owi „ponów", dopóki mail z linkiem nie
  wyjdzie — dzięki temu ponowienia Stripe'a dokańczają wysyłkę same, gdy
  poczta wróci. Po skonfigurowaniu wysyłki maili (SMTP/hosting) błędy
  znikają; do tego czasu treść maila i ręczna wysyłka są w panelu przy
  zamówieniu.

## Odinstalowanie

Wtyczki → Sklep Agentów → Wyłącz → Usuń. Domyślnie dane sprzedaży ZOSTAJĄ
w bazie (historia zamówień to nie śmieć). Chcesz zniknąć bez śladu?
Ustawienia sklepu → „Dane przy odinstalowaniu" → włącz „Pełne czyszczenie"
PRZED usunięciem wtyczki — wtedy znikają tabele, opcje, strony sklepu
i pliki chronione. Klucze Stripe i zaplanowane zadania są usuwane ZAWSZE,
niezależnie od tej opcji.

⚠️ **Boty czyścisz OSOBNO:** ChatBot LIVE i Segregator maila (jeśli były
zainstalowane jako wtyczki) mają KAŻDY własną opcję „usuń wszystkie dane"
we WŁASNYCH ustawieniach — opcja sklepu ich nie obejmuje. Bez włączenia
tych opcji boty zostawią swoje tabele w bazie (dane demo, historia rozmów,
przebiegi). Klucz API każdego bota jest usuwany ZAWSZE, jak w sklepie.
