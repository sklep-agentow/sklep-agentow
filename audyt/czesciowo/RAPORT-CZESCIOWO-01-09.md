# RAPORT — obszary „częściowo" z `audyt/MAPA-TERENU-INFRA.md`

**Kto:** audytor, sesja 2026-09-01. **Gałąź:** `audyt/czesciowo-01-09` (ze świeżego `main`, `e3528d7`).
**Kodu produktu nie tknąłem.** Zlecenie: dziewiętnaście obszarów „częściowo" bez `H4`.
⭐ **Korekta zakresu w trakcie przelotu** (koordynatorka, 1.09): z listy wyjęte `E2`, `E3`, `D5`,
`D4`, `G4`, `G5` — idą innym kanałem. Żadnego z nich nie zdążyłem zacząć.
**Zostaje trzynaście** (`A1 A2 A3 A5 B5 B6 E6 E7 F1 F2 H1 H2 H3`) — w wiadomości było
napisane „dwanaście", ale wyliczone pozycje to trzynaście (19 − 6); liczę z listy, nie ze słowa.

**Metoda tego przelotu — jedno pytanie do każdego obszaru:**
> co dokładnie zostało tu zmierzone, a co tylko **wygląda** na zmierzone?

---

# E7 — TEKSTY WIDZIANE PRZEZ KLIENTA (panel, maile)

**Stan wejściowy z mapy:** 🟡, „audyt sklepu, osobny rejestr".
**Wynik: 1 × `SRED`, 2 × `MALE`, pięć terenów czysto.**

## Gdzie kończył się pomiar

Zmierzone było **to, co mail i panel MÓWIĄ** — treść zdań, zgodność liczb ze stałymi,
komplet statusów. Nie było zmierzone **to, czy zdanie zostaje prawdziwe, gdy klient
na nie patrzy**: czy strona nadal robi to, co o sobie pisze; czy godzina na ekranie
oznacza tę godzinę; czy kanał, do którego tekst odsyła, w ogóle istnieje.
Wszystkie trzy znaleziska siedzą dokładnie na tej granicy.

Przyrządy leżą obok tego raportu i powtarzają się jedną komendą:
`audyt/czesciowo/E7-sonda-dziekujemy.js` · `E7-sonda-panel-czas.php` · `E7-sonda-replyto.php`.
Mierzą i drukują — nie są strażnikami i nie należą do bramek.

---

## 1. ZNALEZISKA

### 🟠 `E7-F1` — **SRED** · strona „Dziękujemy" po dwóch minutach **przestaje odpytywać status**, a na ekranie zostaje zdanie „(strona odświeża status sama)" — i zostaje tam już na zawsze

**ZNALEZISKO.** `assets/dziekujemy.js` odpytuje `sklepag/v1/status` co 4 s i kończy warunkiem
`proby >= 30` (`dziekujemy.js:47`). Po trzydziestym odpytaniu skrypt **milczy**, ale nie zmienia
napisu. Kupujący, którego zamówienie jeszcze nie zostało domknięte, patrzy dalej na:
```
Przetwarzamy Twoją płatność… (strona odświeża status sama)
```
Zdanie było prawdziwe przez pierwsze **2 minuty** i jest nieprawdziwe przez cały czas potem.
Strona nie podaje przy tym **żadnego następnego kroku** — nie mówi „sprawdź skrzynkę za chwilę",
nie mówi „jeśli nic nie przyjdzie, napisz do sklepu". Ta sama cisza jest w gałęzi `.catch`
(`:51`): gdy REST nie odpowie, na ekranie nie zmienia się nic.

**DOWÓD — uruchomienie w przeglądarce, nie odczyt kodu.**
`node audyt/czesciowo/E7-sonda-dziekujemy.js` (chromium, strona `/dziekujemy-za-zakup/?sesja=cs_test_…`):

```
[ 11 s] po 10 s:  odpytan=3  · ekran: "Przetwarzamy Twoją płatność… (strona odświeża status sama)"
[ 61 s] po 60 s:  odpytan=15 · ekran: "Przetwarzamy Twoją płatność… (strona odświeża status sama)"
[121 s] po 120 s: odpytan=30 · ekran: "Przetwarzamy Twoją płatność… (strona odświeża status sama)"
[166 s] po 165 s: odpytan=30 · ekran: "Przetwarzamy Twoją płatność… (strona odświeża status sama)"
[211 s] po 210 s: odpytan=30 · ekran: "Przetwarzamy Twoją płatność… (strona odświeża status sama)"

ODPYTAN LACZNIE: 30
CISZA od ostatniego odpytania: 92 s
WERDYKT: strona obiecuje samoodswiezanie: TAK · odpytywanie STOI: TAK        EXIT=3
```
⛔ **Kontrola dodatnia jest w tym samym przebiegu i jest zdana:** w pierwszych pomiarach licznik
odpytań ROŚNIE (3 → 15 → 30), więc sonda umie zobaczyć odpytywanie; dopiero potem widzi ciszę.
Przyrząd nie jest taki, któremu „zawsze wychodzi".

**MECHANIZM — kiedy to boli.** Stan `oczekuje` po powrocie ze Stripe nie jest teorią: zamówienie
powstaje w bazie dopiero przy domknięciu, a `sklepag_status_zamowienia()` (`zakup.php:1328`)
na nieznaną sesję **z założenia** oddaje `oczekuje` (zmierzone:
`curl 'localhost:8890/wp-json/sklepag/v1/status?sesja=cs_test_calkiem_zmyslona_123'` → `{"status":"oczekuje"}`).
Ten projekt ma już zapisany przypadek, w którym ten stan trwał **20 minut**: K10 (30.08) —
webhook nie doszedł, cron na hostingu nie chodził, opłacony zakup wisiał (komentarz w
`zakup.php:1366` opisuje to zdarzenie). Przez 18 z tych 20 minut kupujący czytał zdanie,
które przestało być prawdą, i nie dostał żadnej wskazówki, co zrobić.

**Dlaczego `SRED`, a nie wyżej:** pieniądze są pobrane poprawnie, mail w normalnym biegu
przychodzi, a link pobrania i tak jedzie pocztą — awaria dotyczy **informowania** kupującego
w oknie, w którym on właśnie zapłacił i nie wie, czy coś poszło nie tak.

---

### 🟡 `E7-F2` — **MALE** · panel pokazuje właścicielowi **godzinę wygaśnięcia linku w GMT, podpisaną jak czas lokalny** — na tym samym ekranie, dwa wiersze niżej, ta sama klasa jest już naprawiona

**ZNALEZISKO.** `admin-zamowienie.php:338` drukuje kolumnę `link_wygasa` surową:
```php
echo '<tr><td>Pobrania</td><td>' . … . ( $zam->link_wygasa ? ' · link ważny do ' . esc_html( $zam->link_wygasa ) : … );
```
Kolumna jest zapisywana `gmdate()` (`zakup.php:1022`), czyli **w GMT**. Sąsiednie czasy na tym
samym ekranie są w czasie witryny (`utworzono` ← `current_time('mysql')`), a **następny wiersz
tabeli** robi dokładnie to, czego brakuje wierszowi wyżej — i ma o tym komentarz:
```php
// Kolumna zgoda_czas jest w GMT — panel pokazuje czas lokalny strony, ten sam, który dostaje kupujący w mailu.
echo '<tr><td>Zgoda na dostarczenie treści cyfrowej</td><td>' . esc_html( … get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) . ' (czas lokalny)' … );
```
Czyli: klasa była znana i została zamknięta **na jednym egzemplarzu**, a drugi, o dwie linie
wyżej, został.

**DOWÓD — wycięte z prawdziwie wyrenderowanego ekranu panelu, nie z kodu.**
`docker exec strona_wp_wordpress wp eval-file` z `audyt/czesciowo/E7-sonda-panel-czas.php`
(sonda woła `sklepag_admin_zamowienie()` i wycina wiersz „Pobrania"):
```
WIERSZ Z EKRANU PANELU: 0 / 10 · link ważny do 2026-09-04 02:57:00
surowa kolumna link_wygasa (baza) : 2026-09-04 02:57:00
ta sama chwila w czasie WITRYNY   : 2026-09-04 04:57:00  (strefa Europe/Warsaw, przesuniecie +2 h)
zamowienie utworzono (czas WITRYNY): 2026-09-01 04:57:00
roznica utworzono -> pokazane w panelu: 70.0 h  (a link zyje 72 h)
WERDYKT: panel pokazuje godzine o 2 h wczesniejsza niz prawdziwe wygasniecie linku.
```
⭐ **Sprzeczność jest widoczna na jednym ekranie bez żadnego przyrządu:** „Data zakupu"
04:57, „link ważny do" 02:57 trzeciego dnia — **70 godzin**, choć stała `SKLEPAG_LINK_GODZIN`
to 72 i tyle właśnie link naprawdę żyje.

**MECHANIZM.** Sama brama pobrań liczy **dobrze** — `pobieranie.php:41` robi
`strtotime( $zam->link_wygasa ) < time()`, a WordPress trzyma domyślną strefę PHP na UTC, więc
porównanie jest w tej samej bazie czasu. Zepsuty jest **wyłącznie odczyt dla człowieka**:
właściciel odpowiadający kupującemu „link działa do 02:57" podaje godzinę o `gmt_offset`
wcześniejszą niż prawda. Przy ujemnym `gmt_offset` (sklep w strefie Ameryk) znak się odwraca —
panel pokazuje godzinę **późniejszą** niż faktyczna śmierć linku, czyli właściciel obiecuje czas,
którego już nie ma.

**Dlaczego `MALE`, a nie wyżej — świadomie nie podnoszę:** nic się nie psuje, nic nie ginie,
egzekwowanie terminu jest poprawne. To **niespójność wyświetlania** i poprawka na jedną linię
(`get_date_from_gmt`), z gotowym wzorcem dwa wiersze niżej. Waga rośnie tylko o tyle, o ile
właściciel przekazuje tę godzinę kupującemu.

---

### 🟡 `E7-F3` — **MALE** · mail mówi kupującemu **trzy razy** „odpisz na tego maila", a przy pustym „Mailu kontaktowym" nie ma dokąd odpisać — i **nic w panelu o tym nie mówi**

**ZNALEZISKO.** Trzy zdania w mailu po zakupie odsyłają kupującego do odpowiedzi na maila:
gwarancja („Aby z niej skorzystać, odpisz na tego maila"), faktura („Odpisz na tego maila
w ciągu 3 miesięcy…") i pytania („Masz pytanie? Odpisz na tego maila."). Adres zwrotny
(`Reply-To`) jest zakładany **tylko wtedy**, gdy wypełniona jest opcja `sklepag_email_kontaktowy`
(`zakup.php:1168-1173`). Pusta opcja = brak `Reply-To` **i** zniknięcie wiersza
„Reklamacje i faktury" ze stopki sprzedawcy (`zakup.php:1257-1270`). Odpowiedź kupującego idzie
wtedy na adres NADAWCY — czyli na `sklepag_nadawca_email`, a gdy i ta jest pusta,
na domyślny adres WordPressa (`wordpress@domena`), który zwykle nie jest skrzynką.

**Pulpit ostrzega o danych sprzedawcy — o tym nie ostrzega.** `admin-dashboard.php:392`
sprawdza wyłącznie `sklepag_sprzedawca()['kompletne']`, a to jest `firma_nazwa && firma_adres`
(`zakup.php:1229`). Mail kontaktowy nie wchodzi do tego warunku, a opis pola w panelu mówi
**mniej, niż pole robi**: „Pokazywany kupującym przy problemie z pobraniem (link wygasł / limit
wyczerpany)" (`admin-ustawienia.php:374`) — ani słowa o tym, że to jedyny adres zwrotny maila
po zakupie i jedyny kontakt reklamacyjny w stopce. (Instrukcja klienta mówi to uczciwie —
`INSTRUKCJA.md:259` — ale panel, w którym właściciel podejmuje decyzję, nie.)

**DOWÓD — dwa przebiegi tej samej ścieżki wysyłki, różniące się jedną opcją.**
`audyt/czesciowo/E7-sonda-replyto.php` (opcje podmieniane filtrem `pre_option_*`, wysyłka
przerwana filtrem `pre_wp_mail` — **zero zapisu i zero wysłanej poczty**):
```
STAN FAKTYCZNY OPCJI NA STOSIE:  sklepag_email_kontaktowy = []   sklepag_nadawca_email = []

A) kontakt PUSTY, nadawca noreply@sklep.test:
   naglowki: Content-Type: text/html; charset=UTF-8
   Reply-To obecny: NIE · stopka ma 'Reklamacje i faktury': NIE
B) kontakt USTAWIONY (kontrola dodatnia):
   naglowki: Reply-To: kontakt@sklep.test | Content-Type: text/html; charset=UTF-8
   Reply-To obecny: TAK · stopka ma 'Reklamacje i faktury': TAK

C) ile razy mail mowi „odpisz na tego maila": tekstowa 3 · HTML 3
```
⛔ Kontrola dodatnia (B) pokazuje, że sonda **umie zobaczyć** `Reply-To` — „nie ma" nie jest tu
„źle szukałem".

**To samo widać na żywej poczcie:** ostatni mail z łapacza (`localhost:8025`) ma
`From: WordPress <wordpress@example.com>`, `ReplyTo: []` i stopkę `SPRZEDAWCA: Automatic AI`
bez adresu i bez kontaktu.

**PĘTLA SIĘ ZAMYKA NA STRONIE BŁĘDU POBRANIA.** Przy pustym kontakcie brama pobrań mówi
kupującemu dokładnie to samo — zmierzone na żywo, `curl` na `admin-post.php` ze złym tokenem:
```
HTTP=403 · „Nieprawidłowy link pobrania. Skontaktuj się z właścicielem sklepu (odpisz na maila z zakupem)."
```
Czyli kupujący z martwym linkiem jest odsyłany do odpisania na maila, który nie ma adresu
zwrotnego. Obie strony tej pętli są napisane poprawnie z osobna; razem nie prowadzą nigdzie.

**Dlaczego `MALE`:** wada jest **warunkowa** (właściciel z sensownym adresem nadawcy dostanie
odpowiedzi mimo pustego kontaktu) i instrukcja klienta opisuje pole uczciwie. Zgłaszam ją,
bo brakuje **sygnału w miejscu decyzji** — panel ostrzega o stopce art. 21, a o zerwanym
kanale zwrotnym milczy, choć to jedna gałąź w tym samym warunku.

---

## 2. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Co sprawdzone | Jak zmierzone | Wynik |
|---|---|---|---|
| 1 | **Zgodność wersji tekstowej i HTML maila** — czy klient poczty czytający HTML dostaje mniej | porównanie obu składaczy (`zakup.php:1232`, `mail-html.php:38`) + zliczenie w sondzie | **CZYSTO** — obie wersje niosą ten sam komplet: numer, produkt, data zakupu, kwota, potwierdzenie zgody z chwilą, link i jego ważność, instrukcja 3 kroki, gwarancja, opinia, faktura, stopka sprzedawcy. HTML dokłada tylko adres strony i zdanie „to jest potwierdzenie umowy — zachowaj go" |
| 2 | **Słownik statusów strony „Dziękujemy"** — czy jest status, przy którym strona kłamie | zestawienie statusów pisanych do `zamowienia.status` w kodzie z tablicą `opisy` w `dziekujemy.js` | **CZYSTO** — 7 statusów zamówienia (`oczekuje, oplacone, mail_w_toku, mail_wyslany, mail_blad, zwrocone, sporne`) ma po zdaniu, plus `brak` i zdanie zapasowe `nieznany`, które celowo nie twierdzi nic o pieniądzach. Statusy `zalazek/oczekuje/przyjeta/odrzucona` należą do opinii, nie do zamówień |
| 3 | **Liczby w mailu wobec kodu** — „ważny 3 dni", „10 pobrań", „opinia 30 dni" | liczone ze stałych w chwili składania maila (`round(SKLEPAG_LINK_GODZIN/24)`, `SKLEPAG_LIMIT_POBRAN`, `SKLEPAG_OPINIA_DNI`) | **CZYSTO** — żadnej liczby nie da się rozjechać z kodem, bo żadna nie jest wpisana ręcznie |
| 4 | **Obietnica „link do opinii jest jednorazowy i ważny 30 dni"** | `opinie.php:85-90` (termin + porównanie stałoczasowe) i `:132` (`token_hash=''` przy zapisie) + żywy strzał na zły token | **CZYSTO** — obie części obietnicy egzekwowane; zły/zużyty link daje HTTP 200 ze zdaniem „opinia mogła już zostać wysłana albo link wygasł (30 dni od zakupu)" |
| 5 | **Strony błędu dla kupującego** (zły token, wygasły link, brak pliku, wyczerpany limit) | **żywy strzał** `curl` na `admin-post.php` dla złego tokenu i dla nieistniejącego zamówienia; pozostałe trzy gałęzie **odczytane z kodu**, nie odpalone | **CZYSTO co do treści**. Zmierzone: HTTP 403 „Nieprawidłowy link pobrania. Skontaktuj się z właścicielem sklepu (odpisz na maila z zakupem)." — ten sam kod i to samo zdanie dla nieistniejącego `z`, więc strona nie zdradza, które zamówienia istnieją. Odczytane z `pobieranie.php:45,50,77`: 410 „Link pobrania wygasł…", 503 „Plik chwilowo niedostępny…", 410 „Limit pobrań wyczerpany…" — każde mówi prawdę o przyczynie i dokłada kontakt. ⛔ Tych trzech **nie uruchomiłem**: wymagałyby wpisu do bazy sklepu (cofnięty termin / usunięty plik / zużyty limit), a danych stosu nie ruszam. Zastrzeżenie do samego kanału kontaktu opisuje `E7-F3`, nie treść tych zdań |

## 3. CZEGO W `E7` NIE ZDĄŻYŁEM

- **Wygląd maila w prawdziwym kliencie poczty** (Gmail/Outlook) — mierzyłem treść i strukturę,
  nie render. Do tego trzeba konta pocztowego, nie łapacza.
- **Teksty panelu poza ekranem zamówienia** (Produkty, Opinie, Dziennik, Ustawienia) — przejrzane
  pobieżnie przy okazji `E7-F3`, bez własnego przelotu. To zostaje jako reszta terenu `E7`.
- **Baner „TRYB TESTOWY"** — świadomie pominięty jako osobna pozycja `P-3`. ⭐ Aktualizacja po
  scaleniu `main` (1.09): `P-3` jest już **naprawione** i na tej gałęzi obecne
  (`sklep-agentow.php:234`, `sklepag_baner_testowy()` czyta tryb z klucza) — zdanie „w naprawie"
  przestało być prawdziwe w trakcie tego przelotu.

---

# E6 — INSTRUKCJA DLA KLIENTA W PACZCE

**Stan wejściowy z mapy:** 🟡, „`pakuj-dla-klienta.sh` sprawdza obecność — treść nieoceniana".
**Wynik: 1 × `MALE` (o dwóch egzemplarzach), dziesięć terenów czysto** (policzone z tabeli w §2).

## Gdzie kończył się pomiar — i to jest ciekawsze niż samo znalezisko

Mapa mówiła „sprawdzana jest obecność pliku". To **nieprawda w drugą stronę**: pomiaru jest
znacznie więcej, niż mapa deklaruje, tylko rozłożony bardzo nierówno. Policzone, nie z pamięci:

| dokument dla klienta | co go pilnuje | co NIE jest pilnowane |
|---|---|---|
| `wtyczki/segregator-maila/INSTRUKCJA.md` | **własna bramka** `testy/instrukcja-liczby-segregator.sh` — każda liczba z kodu | treść zdań |
| `wtyczki/sklep-agentow/INSTRUKCJA.md` | `testy/dokumentacja-zgodnosc-sklep.sh` sekcja F — **5 liczb** (72 h, 10, 30 dni, 20 MB, 10 min), etykieta „Dane sprzedawcy", odsyłacze do zrzutów i ich obecność w paczce | treść zdań — bramka **sama to pisze** w swoim nagłówku sekcji |
| `wtyczki/chatbot-live/INSTRUKCJA.md` | `testy/dokumentacja-zgodnosc.sh:424` — **wyłącznie istnienie plików zrzutów** | **wszystkie liczby i wszystkie zdania** |
| `dokumentacja/INSTRUKCJA-INSTALACJI.md` | `pakuj-dla-klienta.sh:35,54,60` — istnienie + brak śladów wewnętrznych | **wszystkie liczby i wszystkie zdania** — a to właśnie tu siedziało `A7-F1` |

⭐ Czyli: dokument, w którym **już raz znaleziono kłamstwo** (`A7-F1`, powiadomienia Stripe),
jest jednocześnie tym, którego treści nie pilnuje nic. A instrukcja ChatBota — jedyna z trzech
bez bramki liczb — niesie ich **dwadzieścia kilka**.

## 1. ZNALEZISKO

### 🟡 `E6-F1` — **MALE** · instrukcja właściciela opisuje **mechanikę ratowania zawieszonego zakupu** niezgodnie z kodem — w dwóch miejscach, przy czym trzecie miejsce w tym samym pliku opisuje ją poprawnie

**Egzemplarz A — rozdział 3, tabela statusów, wiersz „oczekuje" (`INSTRUKCJA.md:83`):**
> „jeśli wisi dłużej niż kilka minut, to błąd zapisu w bazie: sprawdź oś czasu i napisz do
> wykonawcy (**„Dociągnij zaległe" tego statusu nie rusza**)"

W kodzie jest odwrotnie. Zamówienie może stać w `oczekuje` **wyłącznie** wtedy, gdy jego sesja
ma nadal stan `utworzona`: znacznik `oplacona` zakłada `sklepag_oznacz_sesje( $sid, 'oplacona' )`
w `zakup.php:968`, czyli **po** CAS-ie `oczekuje → oplacone` (`:938`) i po sprawdzeniu, że wiersz
zamówienia istnieje (`:946`). To jedyne miejsce w kodzie, które nadaje ten stan
(`grep sklepag_oznacz_sesje` → 3 wywołania: `wygasla`, `oplacona`, `wygasla`).
A „Dociągnij zaległe" bierze dokładnie takie sesje:
```
1387:  SELECT stripe_session_id FROM {$ps} WHERE stan='utworzona' AND utworzono < %s ORDER BY utworzono DESC LIMIT 100
1414:  if ( sklepag_domknij_zakup( $sesja ) ) {   // TEN SAM silnik co webhook
```
i puszcza na nich **ten sam silnik**, którego pierwszy krok to `INSERT IGNORE` (nie zdubluje
wiersza), a drugi to CAS `… SET status='oplacone' … AND status='oczekuje'` — czyli **celuje
dokładnie w ten wiersz, o którym instrukcja mówi, że przycisk go nie rusza**.

Kierunek pomyłki jest ten kosztowny: właściciel czyta „przycisk nie pomoże, napisz do wykonawcy"
i **nie klika jedynej rzeczy, która to odblokowuje** — a na hostingu bez działającego WP-Cron
(przypadek, przed którym ta sama instrukcja ostrzega dwa akapity niżej) ten przycisk jest jedyną
drogą domknięcia.

**Egzemplarz B — rozdział 4 (`INSTRUKCJA.md:159-160`):**
> „sklep ponawia sam **po ok. 2 minutach**, do trzech razy"

Ponowienie wysyłki nie ma własnego zegara. Siedzi w środku `sklepag_dociagnij_zalegle()`
(`zakup.php:1387-1393`), czyli w zadaniu, które chodzi **co 10 minut**
(`sklep-agentow.php:77` `'interval' => 600`), a warunek `zmieniono < %s` z granicą `teraz − 120 s`
jest **minimalnym wiekiem** wiersza, nie odstępem ponowień. Prawdziwe zdanie brzmi: „przy
najbliższym biegu co 10 minut, nie wcześniej niż 2 minuty po ostatniej próbie — i tylko jeśli
WP-Cron działa".

**Trzecie miejsce w tym samym pliku mówi to poprawnie** (rozdział 9, `INSTRUKCJA.md:299-303`):
> „Sklep i tak domknie zakup w ciągu ok. 10 minut (**jeśli WP-Cron działa** — rozdział 3),
> a od ręki zrobi to „Dociągnij zaległe teraz" — odczekaj 2 minuty od zapłaty"

Czyli autor **wie**, jak to działa; rozjechały się dwa wcześniejsze akapity. To ta sama klasa
co `A7-F1`: instrukcja opisuje przebieg, którego nikt nie porównał z kodem, bo bramka mierzy
w tym pliku **pięć liczb**, a nie zdania.

**Dlaczego `MALE`, a nie wyżej — nie podnoszę:** nic się nie psuje samo, zakup domyka się mimo
wszystko (cron albo webhook), a szkoda to zmarnowany telefon do wykonawcy i kilka–kilkanaście
minut zwłoki. Podniósłbym, gdyby zdanie A blokowało jedyną drogę na stałe — nie blokuje,
bo rozdział 9 tego samego dokumentu kieruje do przycisku.

## 2. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Obietnica z dokumentu dla klienta | Jak zmierzone | Wynik |
|---|---|---|---|
| 1 | **Wymagania instalacji**: „WordPress 6.7+ · PHP 8.1+" | nagłówki trzech wtyczek | **CZYSTO** — sklep `6.7`/`8.1`, oba boty `6.0`/`8.1`; instrukcja podaje wymaganie NAJOSTRZEJSZEJ z trzech, czyli poprawnie |
| 2 | **„Paczka sklepu waży około 4 MB, limit wysyłki co najmniej 8 MB"** | `find -printf %s` na paczkach | **CZYSTO** — `sklep-agentow-0.9.28.zip` = 4 719 003 B (4,5 MB); rada „podnieś do 32 MB" z zapasem |
| 3 | **„sklep co 10 minut SAM dopytuje Stripe"** + **„odczekaj 2 minuty"** | `sklep-agentow.php:77` `'interval' => 600`, `zakup.php:1335` granica `− 120 s` | **CZYSTO** — obie liczby zgodne z kodem |
| 4 | **Adres webhooka `…/wp-json/sklepag/v1/webhook`** | `register_rest_route( 'sklepag/v1', '/webhook' )` | **CZYSTO** |
| 5 | **„Jeśli zapomnisz o odnośnikach — wtyczka pokaże czerwone ostrzeżenie z linkiem"** | `admin.php:33-37` (`notice-error` + link do `options-permalink.php`, warunek `'' !== permalink_structure`) | **CZYSTO** |
| 6 | **„Klucze Stripe i zadanie cykliczne znikają ZAWSZE"** (sklep) oraz **„Klucz API każdego bota usuwany ZAWSZE"** | trzy pliki `uninstall.php` | **CZYSTO** — sklep kasuje oba sekrety i `wp_clear_scheduled_hook` **przed** wczesnym `return`; ChatBot kasuje `chatl_klucz_api` przed `return`; Segregator kasuje `segm_klucz_api` **i `segm_imap_haslo`** przed `return`. To jest klasa nr 1 ze `SCIAGA-EGZAMINATORA.md` i tu jest zamknięta |
| 7 | **Lista sześciu miejsc, w których kupujący widzi „tryb testowy"** (`INSTRUKCJA-INSTALACJI.md:283-290`) | render żywych stron + kod | **CZYSTO — 6/6 istnieje**: baner na `/sklep/` i na stronie produktu (`sklepag_baner_testowy`), baner na „Dziękujemy", notka „STRIPE · TRYB TESTOWY · KARTA 4242…" przy przycisku, FAQ „Co znaczy „tryb testowy”?" (`sklep-agentow.php:438`, widoczne w rendrze `/sklep/`), zdanie w mailu. Instrukcja dodatkowo **sama zastrzega**, że lista może nie być kompletna |
| 8 | **Wszystkie liczby instrukcji ChatBota** — dokumentu BEZ bramki liczb | ręczne zestawienie ze stałymi `CHATL_*` i domyślnymi ustawieniami | **CZYSTO — 0 rozjazdów** na 16 sprawdzonych: 10 par Q&A seeda (`instalator.php`, policzone), 2000 wierszy / 2 MB / 15 min importu, próg WCAG 4,5, retencja 90 dni, listy 200/100/25, pamięć 6 wymian, 500 i 2000 znaków, 10 wiadomości/min, 20 wywołań AI/IP/dobę, cisza przejęcia 5 min (`300 s`), pułapka 2 s, stop-loss 100 wywołań i 200 000 tokenów (`chatbot-live.php:258,274`) |
| 9 | **Bramki dokumentów, które MOGĄ paść** — czy w ogóle są zielone dziś | uruchomienie, kod wyjścia bez potoku | **CZYSTO**: `testy/instrukcja-liczby-segregator.sh` **EXIT=0** („każda liczba ma pokrycie"), `testy/dokumentacja-zgodnosc-sklep.sh` **EXIT=0** (63 × OK, 0 rozjazdów) |
| 10 | **Instrukcje i zrzuty naprawdę jadą do klienta** | `unzip -l` na wydanej paczce 0.9.28 | **CZYSTO** — `sklep-agentow/INSTRUKCJA.md` + 5 zrzutów w archiwum; różnica wobec repo to wyłącznie rozdział o narzędziach prywatności WP dopisany po wydaniu 0.9.28 (`I-16`), czyli normalny dryf wersji, nie rozjazd |

## 3. PRZYRZĄD, KTÓRY SKŁAMAŁ MNIE SAMEMU (wypisuję, bo tak ma zostać)

Szukając FAQ „Co znaczy tryb testowy?" (pozycja 7 wyżej) dostałem **zero trafień** i przez chwilę
miałem gotowe znalezisko „instrukcja wymienia miejsce, którego nie ma". Dwie wady mojego grepa naraz:
szukałem w `includes/front-produkt.php` (a FAQ listy sklepu żyje w `sklep-agentow.php`) i pisałem
wzorzec z prostym cudzysłowem, gdy w kodzie stoi `„tryb testowy”` w cudzysłowach drukarskich.
Rozstrzygnął **render żywej strony** `/sklep/`, w którym zdanie stoi jak byk. ⛔ Wniosek na resztę
przelotu: „nie ma tego w kodzie" zgłaszam dopiero po strzale w żywy render albo po grepie
BEZ znaków cudzysłowu we wzorcu.

## 4. CO Z E6 ZOSTAJE

- **Treść zdań instrukcji ChatBota i Segregatora** — przeczytane wyrywkowo pod kątem liczb,
  nie zdanie po zdaniu (dla sklepu przeczytałem cały plik).
- **`PRZECZYTAJ-MNIE.txt`** ze zbiorczej paczki — nie oglądałem treści.
- **Zrzuty ekranu w instrukcjach** — sprawdzone, że istnieją i jadą w paczce; **nie** sprawdzone,
  czy pokazują dzisiejszy panel (zrzuty są z 31.08, a panel od tego czasu dostał sekcję prywatności).
- ⭐ **Odsyłacz do `E7-F1`:** `INSTRUKCJA-INSTALACJI.md:172`, Krok 5 pkt 3, obiecuje kupującemu
  „strona sama odświeża status **aż do** «Mail wysłany»". To jest dokładnie to zdanie, które
  `E7-F1` obala pomiarem — instrukcja powtarza obietnicę strony, nie jej zachowanie.

---

# F2 — PRAWO KONSUMENCKIE: ZGODA NA UTRATĘ PRAWA ODSTĄPIENIA

**Stan wejściowy z mapy:** 🟡, „zapisane w pamięci warsztatu · czy w mailu — sprawdzone
w audycie sklepu". **Wynik: 1 × `MALE`, pięć terenów czysto.**
Gałąź po scaleniu `origin/main` (`60587b3`) — czytam kod po sześciu PR-ach, nie sprzed nich.

## Gdzie kończył się pomiar

Zmierzone było **jedno ogniwo**: czy zgoda jest w mailu. Cały łańcuch — od momentu, w którym
kupujący ją zaznacza, przez to, czy serwer w ogóle jej pilnuje, po to, co zostaje w bazie
po latach — nie był mierzony jako całość. Sprawdziłem go ogniwo po ogniwie; **trzyma się**.
Dziura jest gdzie indziej: w dokumentach, które wtyczka sama zakłada, a których kupujący
nigdy nie zobaczy.

## 1. ZNALEZISKO

### 🟡 `F2-F1` — **MALE** · wtyczka zakłada „Regulamin sklepu" i „Politykę prywatności sklepu", a potem **nigdzie ich nie linkuje** — nawet po opublikowaniu kupujący nie ma jak do nich trafić

**ZNALEZISKO.** Przy aktywacji powstają cztery strony (`includes/aktywacja.php:198-226`), w tym
dwie prawne jako **szkice** do wypełnienia. Opcje z ich identyfikatorami występują w całej
wtyczce w dokładnie **dwóch** miejscach — i oba to sprzątanie po sobie, nie użycie:
```
includes/aktywacja.php   → utworzenie stron i zapis opcji
uninstall.php            → skasowanie stron przy pełnym czyszczeniu
```
`grep -rn 'sklepag_strona_regulamin\|sklepag_strona_polityka' wtyczki/sklep-agentow --include='*.php'`
nie pokazuje **ani jednego** miejsca, które by te opcje odczytało po to, żeby zbudować odsyłacz.
Dla porównania `sklepag_strona_sklep` i `sklepag_strona_dziekujemy` są czytane wielokrotnie
(warunkowe ładowanie assetów, adres produktu, okruszki, powrót do sklepu, stopka maila).

Skutek: właściciel, który zrobi dokładnie to, co każe instrukcja („wypełnij je swoimi danymi
i opublikuj" — `INSTRUKCJA.md` rozdz. 6, `INSTRUKCJA-INSTALACJI.md` Krok 1 pkt 2 i „Test
a produkcja" pkt 5), **nadal nie ma regulaminu nigdzie na ścieżce zakupu**. Żeby kupujący go
zobaczył, trzeba ręcznie dopisać pozycję do menu motywu — czego instrukcja wymaga tylko dla
strony „Sklep", nie dla dokumentów prawnych.

**DOWÓD — render żywych stron, nie odczyt kodu.** Trzy strony ścieżki zakupu, wszystkie linki
do dokumentów prawnych:

| strona | znalezione odsyłacze | czyje |
|---|---|---|
| `/sklep/` | `Polityka prywatności`, `Polityka cookies` | **stopka motywu strony**, nie wtyczka |
| `/sklep/produkt/chatbot-live/` | te same dwa | j.w. |
| `/dziekujemy-za-zakup/` | te same dwa | j.w. |

**Regulaminu nie ma na żadnej z nich.** Stan stron zakładanych przez wtyczkę na stosie
(odczyt bazy):
```
sklepag_strona_regulamin  id=120  status=draft   „Regulamin sklepu (SZABLON)"
sklepag_strona_polityka   id=121  status=draft   „Polityka prywatności sklepu (SZABLON)"
sklepag_strona_sklep      id=118  status=publish
sklepag_strona_dziekujemy id=119  status=publish
```

**Dlaczego `MALE`, a nie wyżej — świadomie nie podnoszę.** Kluczowa informacja z art. 38
pkt 13 ustawy o prawach konsumenta (i art. 16 lit. m dyrektywy) **jest** podana w chwili,
w której musi być podana — stoi w treści checkboxa nad przyciskiem zakupu i wraca w mailu.
To jest przedmiot tego terenu i on jest spełniony. Brak odsyłacza do regulaminu to warstwa
obok (obowiązek informacyjny z art. 12), a sam **regulamin jako dokument** ma w mapie własny,
nietknięty teren `F5` — nie zabieram mu tematu i nie liczę tego drugi raz. Zgłaszam wyłącznie
tę część, która jest wadą **kodu wtyczki**: dokument jest tworzony i kasowany, ale nigdy
czytany.

## 2. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Ogniwo łańcucha zgody | Jak zmierzone | Wynik |
|---|---|---|---|
| 1 | **Czy bez zgody da się kupić** | **żywy POST** na `admin-post.php` z ważnym nonce'em i bez pola `zgoda` | **CZYSTO** — `HTTP=400`, treść: „Zakup wymaga zgody na natychmiastowe dostarczenie treści cyfrowej." Egzekwowanie jest po stronie serwera (`includes/zakup.php:131-135`), nie na atrybucie `required` w HTML. Sprawdzenie stoi **przed** rozgałęzieniem na płatność osadzoną i hostowaną (`:142`), więc obie drogi zakupu przechodzą przez ten sam warunek |
| 2 | **Czy treść zgody jest zapisywana per zamówienie** (a nie tylko wyświetlana) | odczyt kodu + **odczyt bazy stosu** | **CZYSTO** — stała `SKLEPAG_ZGODA_TRESC` (`zakup.php:11`) wchodzi do kolumny `zgoda_tresc` w `INSERT` zamówienia (`:927`). Migawka, nie odsyłacz: zmiana tekstu w przyszłej wersji wtyczki **nie przepisze** zgód już udzielonych |
| 3 | **Czy któreś zamówienie powstało bez zgody** | `SELECT COUNT(*)` na żywej bazie | **CZYSTO — 42 z 44**. Dwa wiersze bez zgody to **atrapy testowe wstawiane wprost do bazy**, nie zakupy: `#9900012` (`cs_test_opinie_mec…`, 1,00 zł) i `#9901177` (`cs_test_rodo_…`, 0,00 zł), oba z pustą treścią maila; ich identyfikatory pochodzą z `testy/opinie-mechanizm-test.php` i `testy/kroki-rodo/*`. Żadne zamówienie z drogi zakupowej nie jest bez zgody, a **wszystkie 44 mają dokładnie jedną, tę samą treść** (`COUNT(DISTINCT zgoda_tresc) = 1`) |
| 4 | **Czy zgoda jest wcześniejsza niż umowa** (art. 38 pkt 13 wymaga zgody PRZED spełnieniem świadczenia) | porównanie znaczników czasu ostatniego zamówienia | **CZYSTO** — `zgoda_czas = 2026-09-01 02:56:41` GMT, `utworzono = 04:57:00` czasu witryny (= 02:57:00 GMT): zgoda **19 sekund przed** powstaniem zamówienia. Czas zgody bierze się z chwili tworzenia sesji płatności (`zakup.php:216-217`), jedzie do Stripe w metadanych i wraca przy domknięciu (`:930`) — nie jest ustawiany „na teraz" przy zapisie |
| 5 | **Trwały nośnik** (art. 21 ust. 1 UPK / art. 8 ust. 7 dyrektywy) | zdjęty z łapacza prawdziwy mail (patrz `E7`) + panel | **CZYSTO** — mail niesie dosłowną treść zgody i jej chwilę w obu wersjach (`zakup.php:1281-1288` tekst, `mail-html.php` blok „Potwierdzenie Twojej zgody"); panel pokazuje to samo, przeliczone na czas lokalny i **opisane** jako lokalny (`admin-zamowienie.php:340`) |

## 3. CZEGO W `F2` NIE ZMIERZYŁEM

- **Obowiązek informacyjny z art. 12** poza samą zgodą (dane sprzedawcy, tryb reklamacji,
  koszty) na stronie produktu — patrzyłem wyłącznie na ogniwa zgody; całość informacji
  przedumownej to teren `F5` (nietknięty).
- **Nazwa przycisku zakupu wobec art. 17 ust. 3 UPK** („zamówienie z obowiązkiem zapłaty"
  albo równoważne). Przyciski brzmią „Kup teraz (płatność testowa)" i „Zapłać (płatność
  testowa)". Nie stawiam werdyktu — to ocena prawna, nie pomiar; **odnotowuję jako pytanie
  do prawnika**, nie jako znalezisko.
- **Zwrot a wygaśnięcie zgody** — co się dzieje z zapisaną zgodą przy zwrocie/sporze;
  sprawdzone tylko tyle, że statusy `zwrocone`/`sporne` nie ruszają kolumn zgody.

---

# A5 — SYMETRIA ZAPIS ↔ SPRZĄTANIE (`uninstall.php`), TRZY WTYCZKI NARAZ

**Stan wejściowy z mapy:** 🟡, „punkt szablonu PR, sprawdzany per-PR · **brak przeglądu
CAŁOŚCI naraz**". **Wynik: ZERO znalezisk. 64 zapisy poza własnymi tabelami, 62 sprzątane
przez `uninstall.php`, 2 nietykane — ⛔ **rachunek POPRAWIONY w sekcji `A1` tego samego raportu**
(„KOREKTA MOJEGO WŁASNEGO WYNIKU Z `A5`"): poprawnie **61 sprzątanych i 3 nietykane** — obie opisane niżej razem z powodem, dla którego
NIE podnoszę ich do znaleziska.**

## Metoda — trzy niezależne źródła listy, nie jedno

Sam grep by nie wystarczył (zapisy idą też przez opakowania `chatl_zapisz_opcje` /
`segm_zapisz_opcje`, przez `register_setting` — gdzie zapisuje RDZEŃ, nie wtyczka — i przez
`set_screen_option`). Dlatego lista powstała z trzech źródeł i dopiero ich suma jest wejściem:

1. **Kod** — wszystkie wywołania piszące (`update_option`/`add_option`, `set_transient`,
   `update_user_meta`/`update_metadata`, `wp_schedule_event`, `wp_insert_post`,
   `wp_mkdir_p`/`file_put_contents`/`move_uploaded_file`), złapane wzorcem **wieloliniowym**
   (pierwsza próba, jednoliniowa, zgubiła `chatl_blad_bazy` i `segm_blad_bazy`, bo nazwa
   opcji stoi w nich w drugiej linii wywołania).
2. **Rejestracje** — `register_setting` (11 opcji sklepu; ⛔ pierwszy odczyt policzył
   `sklepag_ustawienia` jako opcję — to **nazwa grupy**, nie opcja) i wywołania opakowań
   zapisujących w botach.
3. **Żywa baza stosu** — `SELECT option_name FROM wp_options WHERE option_name LIKE …`
   dla trzech prefiksów plus transienty, `wp_usermeta` i `_get_cron_array()`.
   To źródło **wyłapało obcych**: `chatl_test_a`, `chatl_test_b`, `chatl_blad_bazy_MUTANT` —
   sprawdzone grepem, **nie ma ich w kodzie produktu**; to osad po testach i mutantach na
   stosie warsztatowym, więc nie wchodzą do rachunku.

Trzy pliki `uninstall.php` przeczytałem **linia po linii**, nie grepem.

## 1. RACHUNEK — ile zapisów, ile sprzątanych

| wtyczka | opcje | transienty (rodziny) | user meta | crony | strony WP | pliki | opcja RDZENIA | **razem** | **sprzątane** |
|---|---|---|---|---|---|---|---|---|---|
| `sklep-agentow` | 20 | 5 | 1 | 1 | 4 | 1 katalog | 1 (`rewrite_rules`) | **33** | **32** |
| `chatbot-live` | 8 | 3 | 1 | 1 | — | — | — | **13** | **12** |
| `segregator-maila` | 11 | 2 | 3 | 2 | — | — | — | **18** | **18** |
| **RAZEM** | **39** | **10** | **5** | **4** | **4** | **1** | **1** | **64** | **62** ⛔ → **61**, patrz korekta w sekcji `A1` |

⭐ **Dwie rzeczy, które ten rachunek pokazuje, a przegląd per-PR pokazać nie mógł:**
sklep sprząta opcje **wzorcem** (`option_name LIKE 'sklepag_%'` z `esc_like()`), więc każda
przyszła opcja z tym prefiksem jest sprzątnięta z automatu; **oba boty sprzątają listą nazw
wypisanych ręcznie** — tam każda nowa opcja to nowy wiersz do dopisania, i to jest miejsce,
w którym ta symetria kiedyś pęknie. Dziś nie jest pęknięta.

## 2. POZYCJA PO POZYCJI

### `sklep-agentow` — 33 pozycje

| # | co zapisuje | gdzie | sprząta `uninstall.php` |
|---|---|---|---|
| 1-2 | `sklepag_stripe_klucz_sekretny`, `sklepag_stripe_webhook_sekret` | `admin-ustawienia.php` (`register_setting`) | ✅ **ZAWSZE**, przed wczesnym `return` (`:16-17`) |
| 3-11 | 9 pozostałych opcji z `register_setting`: `stripe_klucz_publikowalny`, `nadawca_nazwa`, `nadawca_email`, `email_kontaktowy`, `firma_nazwa`, `firma_adres`, `firma_nip`, `waluta_domyslna`, `usun_dane_przy_odinstalowaniu` | zapisuje RDZEŃ przez `options.php` | ✅ wzorzec `sklepag_%` (`:70`) |
| 12-16 | `katalog_chroniony`, `katalog_chroniony_blad`, `wersja_schematu`, `reconciliation_ostatnia`, `rewrite_podpis` | `aktywacja.php`, `admin-dashboard.php`, `front-produkt.php` | ✅ wzorzec `sklepag_%` |
| 17-20 | `strona_sklep`, `strona_dziekujemy`, `strona_regulamin`, `strona_polityka` | `aktywacja.php:198-226` | ✅ wzorzec + `wp_delete_post` (`:36-41`) |
| 21-25 | transienty: `sklepag_idem_*` (okno idempotencji zakupu), `sklepag_niepowiazana_*`, `sklepag_recon_*`, `sklepag_szkic_opinii_*`, `sklepag_szkic_produktu_*` | `zakup.php:33+197 (idem, przeniesione do funkcji sklepag_klucz_idem),258,510,1421`, `opinie.php`, `admin-produkty.php` | ✅ dwa wzorce: `_transient_sklepag_%` **i** `_transient_timeout_sklepag_%` (`:77-79`) + `wp_cache_flush()` przy zewnętrznym cache (`:84`) |
| 26 | user meta `sklepag_zamowien_na_strone` (Screen Options) | `admin.php:77-85` | ✅ `delete_metadata( 'user', 0, …, true )` (`:89`) |
| 27 | cron `sklepag_dociagnij_zalegle` | `sklep-agentow.php:91` | ✅ **ZAWSZE** (`:22`) |
| 28-31 | 4 strony WP (wpisy w `wp_posts`) | `aktywacja.php` | ✅ `wp_delete_post(…, true)` (`:39`) |
| 32 | katalog chroniony w `uploads` + `.htaccess`, `index.php` i wgrane paczki ZIP ⛔ **pozycja PRZEKWALIFIKOWANA — patrz „KOREKTA MOJEGO WŁASNEGO WYNIKU Z `A5`" w sekcji `A1`: katalogów bywa kilka, uninstall zna jeden** | `aktywacja.php:240-259`, `admin-produkty.php:386` | ✅ (`:45-63`) — kasuje **tylko** ścieżkę pasującą do wzorca `sklepag-chronione-`, `rmdir` dopiero gdy pusto |
| 33 | reguła przepisywania adresów w opcji RDZENIA `rewrite_rules` | `front-produkt.php:45-56` | ⚠️ **nie w `uninstall.php`** — czyści ją `sklepag_deaktywacja()` (`aktywacja.php:176`) |

### `chatbot-live` — 13 pozycji

| # | co zapisuje | sprząta `uninstall.php` |
|---|---|---|
| 1 | `chatl_klucz_api` | ✅ **ZAWSZE** (`:27`) |
| 2-8 | `chatl_ustawienia`, `chatl_wersja_schematu`, `chatl_blad_ai`, `chatl_blad_maila`, `chatl_blad_bazy`, `chatl_pulapka`, `chatl_rodo_sesje_wspolne` | ✅ (`:42-58`) — plus **defensywnie** `chatl_blad_nonce`, nazwa ze schematu ≤7, której dzisiejszy kod już nie zapisuje |
| 9-10 | transienty `chatl_import_*` (podgląd importu CSV — **niosą treść klienta**), `chatl_rl_*` (rate-limit ≤1.1.x) | ✅ oba wzorcem `LIKE` z `esc_like()`, razem z wierszami `_transient_timeout_` |
| 11 | transient `chatl_po_aktywacji` (TTL **60 s**) | ⚠️ **nie** |
| 12 | user meta `chatl_powitanie_zamkniete` (stała `CHATL_META_POWITANIE`) | ✅ `delete_metadata(…, true)` — **nazwa sprawdzona ze stałą**, zgadza się |
| 13 | cron `chatl_czyszczenie_historii` | ✅ **ZAWSZE**, przed gałęzią „dane zostają" |

### `segregator-maila` — 18 pozycji, wszystkie sprzątane

| # | co zapisuje | sprząta `uninstall.php` |
|---|---|---|
| 1-2 | `segm_klucz_api`, **`segm_imap_haslo`** (hasło do CUDZEJ skrzynki) | ✅ **ZAWSZE**, bezwarunkowo (`:57`, `:65`) |
| 3-4 | `segm_zgoda_przenoszenie`, `segm_zgoda_ai` | ✅ **ZAWSZE** — po ponownej instalacji wtyczka wraca do trybu na sucho i pyta od nowa |
| 5-8 | `segm_operacja_w_toku` (zamek operacji, pisany wprost do `wp_options`), `segm_powitanie_gotowe`, `segm_blad_ai`, `segm_blad_bazy` | ✅ **ZAWSZE** |
| 9-11 | `segm_ustawienia`, `segm_skrzynka`, `segm_wersja_schematu` | ✅ przy pełnym czyszczeniu |
| 12-13 | transienty `segm_komunikat_<ID>`, `segm_liczenie_<ID>` (**niosą nazwy folderów cudzej skrzynki**) | ✅ **ZAWSZE**, wzorcem, razem z `_transient_timeout_` |
| 14-16 | user meta `segm_powitanie_widziane`, `segm_baner_ukryty`, `segm_blad_bazy_ukryty` | ✅ `delete_metadata(…, true)` — **wszystkie trzy nazwy sprawdzone ze stałymi** (`SEGM_POWITANIE_META`, `SEGM_BLAD_BAZY_UKRYTY_META`), zgadzają się |
| 17-18 | crony `segm_czyszczenie_probki`, `segm_skrzynka_cron` | ✅ **ZAWSZE**, oba |

## 3. DWIE POZYCJE, KTÓRYCH `uninstall.php` NIE DOTYKA — i dlaczego NIE podnoszę ich do znaleziska

**(a) Reguła przepisywania adresów sklepu w opcji rdzenia `rewrite_rules`.** Wtyczka dokłada
własną regułę (`front-produkt.php:45`) i wymusza przebudowę, gdy zmieni się podpis (`:54-56`).
Sprząta to `sklepag_deaktywacja()` — a WordPress przy usuwaniu wtyczki z panelu **zawsze**
najpierw ją wyłącza. Zostaje wyłącznie ścieżka nietypowa (skasowanie katalogu i „usuń"
z listy), czyli **dokładnie ta, dla której obie wtyczki-boty postawiły `wp_clear_scheduled_hook`
w `uninstall.php` i wprost to w komentarzu uzasadniły**. Symetria obrony jest więc
niepełna: cron sprzątany dwiema drogami, reguła jedną. **Nie podnoszę**, bo pozostałość jest
**martwa i bezdanowa** — wpis kieruje do zmiennej zapytania, której po usunięciu wtyczki nikt
nie czyta, a pierwszy zapis „Bezpośrednich odnośników" go usuwa. Podniósłbym, gdyby zostawała
po niej treść albo błąd widoczny dla odwiedzającego; nie zostaje.

**(b) Transient `chatl_po_aktywacji`.** Zapisywany na **60 sekund** (`instalator.php:171`),
niesie wartość `1` — znacznik „pokaż ekran powitalny". Kod kasuje go sam po pokazaniu ekranu
(`powitanie.php:69,76`). Żeby przeżył deinstalację, trzeba by usunąć wtyczkę w tym samym
oknie 60 sekund, w którym ją aktywowano, i nigdy nie wejść na jej ekran. **Nie podnoszę**,
bo nie niesie żadnych danych i wygasa sam. ⛔ Odnotowuję jako **warunek zmiany werdyktu**:
gdyby ten transient kiedykolwiek dostał dłuższy czas życia albo zaczął nieść treść, staje się
znaleziskiem — a lista sprzątania w tej wtyczce jest ręczna, więc nikt tego nie złapie
z automatu.

## 4. CZEGO W `A5` NIE ZMIERZYŁEM

- **Faktycznej deinstalacji na żywo** — to przegląd zgodności listy zapisów z listą sprzątania
  w kodzie, nie pomiar pustego diffu przed-po (kryterium GOTOWE #9). Przyrząd do tego istnieje
  i nie był tu uruchamiany: `narzedzia-qa/dowod-z-paczki.sh` na stosie `probny`.
- **Instalacji z zewnętrznym cache obiektów** (Redis/Memcached) — sklep woła `wp_cache_flush()`,
  boty nie; przy transientach botów kasowanych przez `DELETE` z `wp_options` kopia w cache
  obiektów mogłaby przeżyć do wygaśnięcia. Nie mam takiej instalacji, żeby to zmierzyć —
  **wskazuję jako pytanie otwarte**, nie jako znalezisko.
- **Tabel własnych** (7 + 7 + 7) — to teren `A6`, zamknięty osobno 1.09.

---

# B6 — CZY BRAMKA MIERZY TO, CO DEKLARUJE

**Stan wejściowy z mapy:** 🟡, „część ma próby kontrolne · `I-3`, `I-4`, `I-8` sieroty — OTWARTE".
**Wynik: 2 × `SRED`/`MALE`, cztery tereny czysto.**

## Gdzie kończył się pomiar

Soczewka „nazwa kontra pomiar" **istnieje** i jest dobra: `audyt/sklep/BRAMKI-NAZWA-VS-POMIAR.md`,
przebieg **28.08**, commit `98133e3`, dziesięć bramek. Granica jest w dacie: od tamtej pory
zestaw urósł do **dwunastu**, a dwie nowe nigdy przez tę soczewkę nie przeszły:
```
BRAMKI dziś (12): actionlint gitleaks manifest paczka phpcs php-lint phpstan
                  poswiadczenia slady status-bez-warunku zdarzenia-webhooka znaleziska
zmierzone 28.08 (10): bez `paczka` i bez `zdarzenia-webhooka`
```
Druga granica jest ostrzejsza: tamten przegląd pytał, **co bramka mierzy**. Nie pytał,
**czy ktokolwiek ją odpala** — a to jest dziś ta sama klasa, w której siedzi `I-24`.

## 1. ZNALEZISKA

### 🟠 `B6-F1` — **SRED** · sieroty to nie trzy egzemplarze, tylko **klasa**: 32 z 54 testów PHP ma jedyną automatyczną drogę uruchomienia przez skrypt, którego nikt nie woła

**ZNALEZISKO.** `I-3`, `I-4`, `I-8` (wszystkie **OTWARTE**) opisują pojedyncze sieroty:
`kalibrator-straznikow.sh`, `licznik-odstepstw.sh`, `adresy-w-dokumencie.sh`,
`waga-znaleziska.sh`. Dziś dołożyłem `I-24` (golden z #258). Policzyłem całą rodzinę:

| pomiar | wynik |
|---|---|
| skrypty `.sh` w `narzedzia-qa/` i `testy/` bez ani jednego wołającego | **7 z 89** |
| testy `.php` w `testy/` bez wołającego **innego niż `linia-bazowa.sh`** | ⛔ **32 z 54** |
| a `linia-bazowa.sh` — jedyny, który bierze je wzorcem `testy/*.php` — ma wołających | **0** |

W tych 32 są m.in. **`idem-nie-oddaje-oplaconej-sesji-test.php`** (ten, który dziś okazał się
kłamiący od siedmiu wydań — nikt go nie odpalał, więc nikt tego nie widział)
i **`golden-kupujacy-dostaje-to-co-kupil-test.php`** (strażnik błędu właściciela, `I-24`).
**To jest jeden mechanizm, nie pięć osobnych pozycji:** narzędzie powstaje razem z naprawą,
zostaje opisane w PR i **nie dostaje spustu**, bo spust jest osobną robotą, o którą nikt nie pyta
w szablonie PR-a.

**DOWÓD.** Sonda liczy wołania nazwy pliku w `.github/ .githooks/ narzedzia-qa/ testy/ boty/`
z pominięciem samego pliku i linii komentarza. **Kontrola dodatnia jest wbudowana w wynik:**
ta sama komenda znajduje wołających dla **22 z 54** testów PHP i dla 82 z 89 skryptów — więc
zero przy pozostałych jest faktem, nie ślepotą narzędzia.

**⚠️ MÓJ WŁASNY FAŁSZYWY ALARM, złapany przed zgłoszeniem.** Pierwsza wersja sondy dała
**17 sierot**, w tym dziesięć plików `*proba-kontrolna*.sh`. Nieprawda:
`narzedzia-qa/proby-kontrolne-przelot.sh:78` bierze je **wzorcem**
(`find … -name '*proba-kontrolna*.sh'`), a sam ma 7 wołających. Po odjęciu tej rodziny
zostaje **7** prawdziwych sierot `.sh`. ⛔ Wniosek na przyszłość: liczenie wołań po NAZWIE
pliku daje fałszywy alarm wszędzie tam, gdzie wołający używa globu — trzeba sprawdzić także
wzorce, a nie tylko nazwy.

**SKUTEK dla człowieka.** Zielone CI mówi „12 bramek zielonych" i to jest prawda — ale
**nie mówi**, że 32 testy produktu nie zostały w tym przebiegu uruchomione ani razu.
Regresja w każdym z nich jest niewidzialna do chwili, w której ktoś ręcznie wpisze komendę.

---

### 🟡 `B6-F2` — **MALE** · rozjazd zasięgu `php-lint`/`phpcs` z 28.08 nie tylko trwa — **rośnie**, i obejmuje dziś wszystkie nowe strażniki

**ZNALEZISKO.** `uruchom_php_lint()` w `narzedzia-qa/bramki.sh` robi
`find wtyczki -name '*.php'`, a `phpcs.xml.dist` wskazuje trzy katalogi wtyczek. Zmierzone dziś:

| | 28.08 (`BRAMKI-NAZWA-VS-POMIAR.md`) | 1.09 (dziś) |
|---|---|---|
| pliki `.php` sprawdzane przez `php-lint` | 52 | **54** |
| śledzone pliki `.php` **poza** `wtyczki/` | 65 | ⛔ **80** |

Luka urosła o piętnaście plików, bo cały przyrost narzędzi i testów ląduje poza `wtyczki/`.
W tych 80 są **wszystkie** dzisiejsze strażniki, łącznie z `testy/golden-…-test.php`
i `testy/idem-…-test.php`. Nic w bramkach nie sprawdza nawet ich **składni**.

**Dlaczego `MALE`, a nie wyżej.** Błąd składni w teście ujawnia się głośno przy pierwszym
uruchomieniu — problem jest dopiero **w złożeniu z `B6-F1`**: tych plików się nie uruchamia,
więc głośna awaria nie ma kiedy zabrzmieć. Sam w sobie ten rozjazd nie psuje produktu klienta,
dlatego nie podnoszę.

## 2. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Co sprawdzone | Jak zmierzone | Wynik |
|---|---|---|---|
| 1 | **`zdarzenia-webhooka` — nowa bramka, nigdy nieoglądana tą soczewką** | odczyt skryptu + uruchomienie próby | **CZYSTO**: nazwa obiecuje „lista zdarzeń w instrukcji = lista w kodzie" i dokładnie to mierzy. Źródło prawdy `wtyczki/sklep-agentow/includes/zakup.php` — **sprawdziłem, że to jedyny plik** z nazwami zdarzeń Stripe w całym `wtyczki/` (10 nazw). Wykluczenia (`CHANGELOG.md`, `archiwum-procesu/`, `audyt/`) są **zadeklarowane z powodem** w nagłówku. Pusty wynik daje kod 2, nie zielone |
| 2 | **`paczka` — druga nowa bramka** | j.w. | **CZYSTO co do pomiaru**: woła `BUDUJ_TYLKO_SPRAWDZ=1 boty/buduj-paczki.sh`, czyli PRAWDZIWY skrypt pakujący, zamiast pisać drugi parser allowlisty; katalogi wtyczek bierze z dysku, nie z listy trzech nazw. ⚠️ Zastrzeżenie do **nazwy**, nie do pomiaru: etykieta `paczka` brzmi szerzej niż „każdy plik wtyczki jest na allowliście" — to ta sama klasa co rozjazdy nazw z 28.08. (⛔ Brak `paczka-zgodnosc.sh` w zestawie bramek to osobna, **zaparkowana** decyzja — nie zgłaszam jej) |
| 3 | **Kalibracja obu nowych bramek** — czy próby naprawdę mierzą | uruchomienie, kody bez potoku | **CZYSTO**: `narzedzia-qa/zdarzenia-webhooka-proba-kontrolna.sh` → **`EXIT=0`**, 10 przypadków, w tym „prawdziwe repo tego projektu jest zgodne". `narzedzia-qa/paczka-obejmuje-kazdy-plik-wtyczki-proba-kontrolna.sh` → **`EXIT=0`**, 6 podłożonych przypadków rozpoznanych + czysta kopia przepuszczona + przypadek „awaria przyrządu ma dać 2, nie 1" |
| 4 | **Czy `slady` ma kalibrację** (moja pierwsza lista mówiła „BRAK PRÓBY") | odczyt `bramki-proba-kontrolna.sh` | **CZYSTO — miałem złą listę.** Bramka `slady` jest kalibrowana wewnątrz `narzedzia-qa/bramki-proba-kontrolna.sh:152` („nowy ślad w pliku wtyczki MA być złapany") razem z kontrolą odwrotną („czysty stan ma przechodzić", `:232`) i przypadkiem kurczącego się zestawu (`:75`). Mój pierwszy pomiar szukał pliku o nazwie bramki i tego nie widział — druga sytuacja tego samego dnia, w której **dopasowanie po nazwie pliku kłamie** |

## 3. CZEGO W `B6` NIE ZDĄŻYŁEM

- **Kalibracji `actionlint`, `phpcs`, `phpstan`, `znaleziska`** — nie mają własnej próby
  kontrolnej i nie sprawdzałem, czy któraś jest kalibrowana gdzie indziej. Po dzisiejszych
  dwóch pomyłkach z dopasowaniem po nazwie **nie zgłaszam tego jako braku** — to `NIE SPRAWDZIŁEM`.
- **Uruchomienia `proby-kontrolne-przelot.sh` w całości** (43 próby, część wymaga Dockera
  i jedna deinstaluje wtyczkę ze stosu) — nie odpalałem na wspólnym stanowisku.
- **Czy 32 testy bez wołającego przechodzą** — census mierzy WPIĘCIE, nie wynik. Ile z nich
  jest dziś zielonych, nie wiadomo; dwa sprawdzone dziś ręcznie dały `EXIT=1` (`idem-…`,
  kłamiący) i `EXIT=0` (`golden-…`).

---

# B5 — HOOKI REPOZYTORIUM (`.githooks/`)

**Stan wejściowy z mapy:** 🟡, „31.08 przy naprawie hooków maszyny · `I-9` dwa `pre-push` — OTWARTE".
**Wynik: 0 nowych znalezisk. `I-9` potwierdzone jako nadal aktualne (z jednym doprecyzowaniem,
które zmienia jego opis). Cztery tereny czysto — po raz pierwszy zmierzone URUCHOMIENIEM.**

## Gdzie kończył się pomiar

Poprzedni przelot (31.08) zmierzył **mechanizm**: który plik git czyta, co zasłania co.
Nie zmierzył **skutku**: czy blokada naprawdę zatrzymuje push. To jest różnica między
„`core.hooksPath` wskazuje na `.githooks`" a „spróbowałem i mnie nie puściło" — i tylko
to drugie jest dowodem, że kompensacja braku ochrony gałęzi (`C11`) działa.

## 1. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Co sprawdzone | Jak zmierzone | Wynik |
|---|---|---|---|
| 1 | **Czy blokada bezpośredniego pusha na `main` DZIAŁA** | `git push --dry-run origin HEAD:main` — próba nic nie wysyła, a hook i tak biegnie | **CZYSTO**: `EXIT=1`, komunikat `⛔ pre-push: bezpośredni push na main zablokowany. Rób branch → PR → merge.` Kompensacja `C11` (brak ochrony gałęzi na darmowym planie) jest **realna**, nie deklarowana |
| 2 | **KTÓRY z dwóch plików `pre-push` naprawdę wystrzelił** | rozstrzygnięte treścią komunikatu | **CZYSTO**: zadziałał `.githooks/pre-push` (polskie znaki: „bezpo**ś**redni", „Ś**wiadomy** wyjątek"), a nie `.git/hooks/pre-push` (bez diakrytyków: „bezposredni", inna druga linia). Rozstrzygnięcie **bez zgadywania** — dwa pliki mają rozjechany tekst, więc tekst jest znacznikiem |
| 3 | **Czy ŚWIEŻY klon też jest chroniony** (P1 z planu polowania: „świeży klon bez ręcznej konfiguracji NIE ma ochrony") | odczyt `git config --global --get core.hooksPath` → `/root/.claude/git-hooki`, potem **uruchomienie łańcucha** | **CZYSTO — i to jest zmiana wobec 31.08.** Hook maszynowy nie zasłania już hooka repo: `lib-oddaj-repo.sh` (`oddaj_hookowi_repo`) po własnej kontroli oddaje sterowanie kandydatom `"$korzen/.githooks/$nazwa"`, potem `"$korzen/.git/hooks/$nazwa"`. Zmierzone uruchomieniem łańcucha ze stdin udającym push: `printf 'refs/heads/audyt 0000 refs/heads/main 0000' \| /root/.claude/git-hooki/pre-push origin <url>` → **`EXIT=1`** z komunikatem hooka REPO |
| 4 | **Kontrola dodatnia łańcucha** — czy on w ogóle kiedykolwiek przepuszcza | ta sama komenda, ale `remote_ref` = zwykła gałąź | **CZYSTO**: **`EXIT=0`**. Hook nie jest „zawsze na nie" — blokuje wyłącznie `refs/heads/main` |

## 2. `I-9` — NADAL AKTUALNE, ale opis wymaga jednej poprawki

Stan zmierzony dziś: `.githooks/pre-push` **512 B** (żywy) i
`/root/praca/zlecenia/sklep-agentow/.git/hooks/pre-push` **412 B**; `diff` → **różne**.
Wpis `I-9` mówi o pliku „martwym" — ⛔ **to już nie jest ścisłe**: od naprawy z 31.08
(`lib-oddaj-repo.sh`) plik w `.git/hooks/` jest **drugim kandydatem** w łańcuchu delegacji,
więc w repozytorium bez katalogu `.githooks/` **wystrzeli**. Ryzyko z `I-9` (następna edycja
trafia w plik, którego git nie czyta) zostaje, ale słowo „martwy" trzeba zamienić na
„drugi w kolejności — dziś nieużywany, bo pierwszy kandydat istnieje".

**Wagi nie podnoszę** (`MALE` zostaje): oba pliki mówią to samo co do skutku (blokada `main`),
różnią się tylko brzmieniem komunikatu, a łańcuch i tak wybiera właściwy.

## 3. CZEGO W `B5` NIE ZMIERZYŁEM

- **Hooka `pre-commit`** z katalogu maszynowego (`/root/.claude/git-hooki/pre-commit`) —
  istnieje, nie sprawdzałem, co robi ani czy deleguje tak samo jak `pre-push`.
- **Zachowania po `--no-verify`** — hook sam wypisuje ten wyjątek jako świadomy; klasa 92
  ze ściągi („obejście własnej bramki") proponuje blokadę, ale to teren warsztatu, nie repo.
- **Innych typów hooków, których NIE MA** (`pre-receive`, `commit-msg`) — nie sprawdzałem,
  czy któryś jest gdzieś obiecany.

---

# H1 — STOS WORDPRESS (DOCKER)

**Stan wejściowy z mapy:** 🟡, „testy produktu · 12 skryptów wymaga stosu — poza CI".
**Wynik: 1 × `SRED`, cztery tereny czysto.** Cały przelot **wyłącznie odczytowy** — na stosie
pracuje builder, więc niczego nie zmieniałem.

## Gdzie kończył się pomiar

Stos był dotąd traktowany jako **przyrząd**: „12 skryptów go wymaga". Nikt nie zapytał,
**czy ten przyrząd jest wykalibrowany** — czy chodzi na tym, na czym ma chodzić produkt,
czy jego zegar (cron) w ogóle tyka i co w nim zostało po poprzednich pomiarach. A na tym
stosie stoją dowody dla całego zlecenia.

## 1. ZNALEZISKO

### 🟠 `H1-F1` — **SRED** · **WP-Cron na stosie warsztatowym NIE CHODZI** — 12 z 15 zadań zaległych, najstarsze od 19 godzin — więc żaden dowód „domyka się co 10 minut" nie został na nim nigdy wykonany

**DOWÓD** (`wp eval`, odczyt `_get_cron_array()`, czas GMT `2026-09-01 17:46:32`):
```
zadan w kolejce: 15 · ZALEGLYCH: 12
najstarsze zalegle: sklepag_dociagnij_zalegle (2026-08-31 23:09:57) — zalega 19 hours
ALTERNATE_WP_CRON: nie · DISABLE_WP_CRON: (niezdefiniowany)
sklepag_reconciliation_ostatnia = 11 godzin temu
```
WP-Cron rusza **ruchem na stronie**, a ta strona ma `blog_public = 0` i nikt na nią nie wchodzi —
więc zegar stoi. Ostatni zapis `sklepag_reconciliation_ostatnia` (11 h temu) pochodzi
najpewniej z **wejścia na Pulpit** (self-heal), nie z crona.

**Co to psuje w naszych dowodach — i to jest właściwa treść tego znaleziska:**
- **`SK-L-005`** uzasadnia wagę ŚREDNIA zdaniem „reconciliation co 10 min → opóźnienie ≤10 min".
  Na stosie, na którym to mierzymy, **ta noga nie działa**. Werdykt się broni (patrz
  `audyt/plamy/SK-L-005-WERDYKT.md` — dźwiga go self-heal na Pulpicie), ale zdanie o cronie
  nigdy nie było tu sprawdzone.
- **`E7-F1`** (strona „Dziękujemy" zamiera po 2 min) i **K10** („zakup wisiał 20 minut") to
  **ten sam stan**: zakup domknie dopiero człowiek wchodzący na Pulpit.
- Każdy przyszły test „poczekaj, cron domknie" na tym stosie **zawiesi się albo skłamie**.

**Dlaczego `SRED`, a nie wyżej:** to wada **przyrządu**, nie produktu — produkt na hostingu
z ruchem będzie miał WP-Cron żywy. Nie podnoszę, ale zapisuję, bo bez tego zdania kolejny
audytor zmierzy czas domknięcia i dostanie liczbę, która nie mówi nic o produkcie.

## 2. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Co sprawdzone | Wynik |
|---|---|---|
| 1 | **Wersje: czy stos to środowisko docelowe** | **CZYSTO**: WordPress **7.1**, PHP **8.4.21**, MariaDB **11.8.8**. `CLAUDE.md` deklaruje „WP 7.0 / PHP 8.4" — stos jest **nie starszy** niż deklaracja, a wtyczki wymagają `6.7`/`8.1`, więc chodzą na wersjach wyższych niż minimum. Zgodne |
| 2 | **`WP_DEBUG`** — kryterium GOTOWE #1 żąda pomiaru z włączonym | **CZYSTO**: `WP_DEBUG` **WŁĄCZONY**, `WP_DEBUG_LOG` włączony, `WP_DEBUG_DISPLAY` wyłączony (błędy idą do pliku, nie na ekran) |
| 3 | **Czy stos zapisuje błędy i czy ktoś je czyta** | **ZMIERZONE, bez oskarżenia**: `wp-content/debug.log` ma **2,0 MB** i **6376** wpisów PHP: 6010 Warning, **276 Fatal**, 66 Deprecated, 5 Notice. Fatale rozkładają się na 24-31.08 (najwięcej 28.08: 82) i **żaden nie jest z dzisiaj poza dwoma moimi** (`sklepag_badge_statusu` z sondy `E7`, 06:40 — sonda wołała ekran panelu bez wcześniejszego załadowania `admin-dashboard.php`). Najliczniejsze źródło: `chatbot-live/includes/import.php` (55) — **wskazuję jako trop dla `A2`, nie rozstrzygam** |
| 4 | **Konfiguracja PHP wobec drogi instalacji z instrukcji** | **CZYSTO co do faktu, warte zapisania**: `upload_max_filesize = 2M`, `post_max_size = 8M`. Paczka sklepu waży **4,5 MB**, więc **na tym stosie nie da się jej wgrać przez wp-admin** — dokładnie przypadek, przed którym ostrzega `INSTRUKCJA-INSTALACJI.md` (i którego zrzut pokazuje „do 2 MB"). Droga instalacyjna klienta jest tu **niemierzalna** i mierzy się ją osobno (K6/K9/K10, czyste instalacje) |

⛔ **PUŁAPKA PRZYRZĄDU, którą złapałem na sobie i którą trzeba zapisać:** `wp eval` melduje
`memory_limit = -1`, bo WP-CLI **podnosi go sobie sam**. Prawdziwa wartość, z którą chodzi
produkt, to **128M** (`php -r` w tym samym kontenerze). ⛔ Każdy pomiar limitów pamięci zrobiony
przez `wp eval` mierzy WP-CLI, nie produkt — mój pomiar `A7b` był na szczęście robiony
`php -d memory_limit=64M`, poza WP-CLI.

## 3. CZEGO W `H1` NIE ZMIERZYŁEM

- **Czy stos = kod z `main`** — zmierzone osobno tego samego dnia i **nie jest**: kontener wiózł
  `sklep-agentow` **0.9.32**, gałąź główna **0.9.31** (`audyt/plamy/GOLDEN-258-WERYFIKACJA.md` §1).
  Nie dubluję tego tutaj, ale to jest własność stosu, nie goldena.
- **Drugiego stosu `k10_wp` na `:8892`** — chodzi 41 godzin, nie tykałem go w ogóle.
- **Czy `wp-cron.php` w ogóle da się wywołać** (pętla zwrotna) — sprawdzenie wymaga żądania HTTP,
  które **uruchomiłoby zaległe zadania**, w tym reconciliation i wysyłkę maili. To zmiana stanu
  wspólnego stosu, więc nie zrobiłem tego bez zgody.

---

# H2 — STRIPE: KONFIGURACJA I WEBHOOKI (część pierwsza, odczytowa)

**Stan wejściowy z mapy:** 🟡, „audyt sklepu · `stripe listen` żywy na maszynie".
**Ta część: zero znalezisk, trzy tereny czysto + odpowiedź częściowa na pytanie o replay.**

| # | Co sprawdzone | Wynik |
|---|---|---|
| 1 | **Komplet kluczy na stosie i ich rodzaj** | **CZYSTO**: wszystkie trzy ustawione i wszystkie **testowe** — `sk_test_…` (107 znaków), `whsec_c0…` (70), `pk_test_…` (107). Zero kluczy produkcyjnych na stanowisku. Wartości nie wyszły poza kontener: mierzyłem długość i prefiks, nie treść |
| 2 | **Autoryzacja endpointu webhooka** | **CZYSTO**: `permission_callback => '__return_true'` z **jawnym powodem w komentarzu** („Publiczny endpoint — autoryzacją JEST podpis Stripe"), a pierwsza instrukcja po wejściu to weryfikacja podpisu na **surowym** body (`$zadanie->get_body()`, bez modyfikacji przed podpisem). Zły podpis → zdarzenie `webhook_zly_podpis` + **HTTP 400** |
| 3 | **Odpowiedzi HTTP wobec ponowień Stripe** | **CZYSTO co do zamysłu**: `checkout.session.completed` przy nieudanym domknięciu oddaje **500**, żeby Stripe ponowił; `payment_status=unpaid` (BLIK w toku) oddaje **200** i zapisuje `webhook_unpaid` — czyli „przyjęte, jeszcze nie moje" |

## Pytanie o REPLAY — odpowiedź, którą da się dać BEZ zmiany stanu stosu

**Pytanie:** czy replay TEGO SAMEGO podpisanego webhooka na sesji **już domkniętej** tworzy
drugi mail z ważnym linkiem?

**Ścieżka w kodzie** (`sklepag_domknij_zakup`): `INSERT IGNORE` (nic nie wstawia — wiersz jest),
CAS `oczekuje → oplacone` (nie łapie — status jest wyższy), znacznik sesji `oplacona`
(już taki jest), a potem **CAS przejęcia wysyłki**:
```
973:  UPDATE … SET status='mail_w_toku' … WHERE stripe_session_id=%s
        AND status IN ('oplacone','mail_blad') AND mail_proby < 3
```
Gdy `$przejete` jest puste, kod czyta stan i dla `mail_wyslany` robi `return true` („ktoś już
domknął") — **bez rotacji tokenu i bez wysyłki**.

**POMIAR ODCZYTOWY — dokładnie ten warunek CAS-a, wykonany jako `SELECT COUNT(*)` na żywej bazie:**
```
zamowienie DOMKNIETE #9902289 (status=mail_wyslany) → CAS zlapalby  0 wierszy
KONTROLA DODATNIA #23        (status=oplacone)      → CAS zlapalby  1 wiersz
rozklad statusow na stosie: mail_wyslany=33 · oplacone=4 · mail_blad=4 · mail_w_toku=3
```
⛔ **To jest dowód arytmetyki warunku, NIE uruchomienie ścieżki.** Kontrola dodatnia pokazuje,
że zapytanie umie zwrócić wiersz, więc zero przy domkniętym zamówieniu jest faktem — ale
`sklepag_domknij_zakup()` **nie zostało wywołane**. Pełny pomiar wymaga uruchomienia i zmiany
stanu bazy; opis eksperymentu i jego skutków leży w meldunku do koordynatorki.

## H2 część druga — REPLAY podpisanego webhooka na sesji JUŻ DOMKNIĘTEJ: **ochrona działa, zmierzone uruchomieniem**

**Zgoda koordynatorki na dotknięcie bazy: udzielona, z warunkiem kalibracji.** Przyrząd:
`audyt/czesciowo/H2-sonda-replay.php` (uruchamiany `wp eval-file`, tryb mutanta zmienną
`SONDA_TRYB=mutant`).

**Jak sonda nie zmienia stanu:** cały przebieg w `START TRANSACTION … ROLLBACK` (wszystkie
tabele `sklepag_*` i `wp_options` są **InnoDB** — sprawdzone w `information_schema`), wysyłka
przechwycona filtrem `pre_wp_mail` (liczy próby, **nic nie wychodzi**), zero ruchu do Stripe
(ładunek podstawiony ręcznie). Sonda **sama sprawdza, czy rollback zadziałał** i przy
niezgodności kończy kodem 2 zamiast meldować wynik.

### Kalibracja — najpierw dowód, że sonda UMIE zwrócić 1

Wada podłożona **bez tykania plików produktu**: filtr `query` WordPressa przechwytuje dokładnie
ten jeden `UPDATE` i zdejmuje z niego warunek statusu — czyli mutuje CAS przejęcia wysyłki
(`zakup.php:973`) w locie:
```
przed:  UPDATE … SET status='mail_w_toku' … WHERE stripe_session_id=%s AND status IN ('oplacone','mail_blad') AND mail_proby < 3
mutant: UPDATE … SET status='mail_w_toku' … WHERE stripe_session_id=%s                                       AND mail_proby < 3
```

| przebieg | PRÓB WYSYŁKI | token zrotowany | wynik | **kod wyjścia** |
|---|---|---|---|---|
| **mutant** (warunek statusu zdjęty) | **1** | **TAK** (`f58e6aa8ec…` → `e9c0c5a62a…`) | „ochrona NIE działa" | **`EXIT=1`** |
| **prawdziwy kod z `main`** | **0** | nie | „ochrona działa" | **`EXIT=0`** |

W obu przebiegach: `mutacja CAS-a weszla: TAK` (tryb mutanta), `PO ROLLBACKU wiersz identyczny
jak przed: TAK`, `domknij_zakup zwrocil: true` (czyli replay dostaje HTTP 200 — Stripe nie ponawia,
i tak ma być).

**Kontrola stanu po obu przebiegach:** `produkty 2 · sesje_checkout 52 · zamowienia 44 ·
zdarzenia 2698 · opinie 33` — **identycznie jak przed**; wiersz `#9902289` bez zmian;
łapacz poczty **98 wiadomości przed i po**.

⭐ **Dlaczego to jest odpowiedź na pytanie, którego raport `L2-05` nie zadał:** tamten test
mierzył replay na sesji **nieistniejącej**, czyli na ścieżce, która kończy się na
`webhook_nieznana_sesja` i nic nie tworzy — więc CAS z `:973` nigdy nie był w tym pomiarze
uruchomiony. Tutaj jest uruchomiony na sesji domkniętej i **trzyma**.

⛔ **Czego ten pomiar NIE obejmuje:** warstwy podpisu (ładunek podstawiam za weryfikacją, więc
mierzę zachowanie **po** przyjęciu zdarzenia, nie samą weryfikację); replayu **równoległego**
(dwa żądania naraz — CAS jest atomowy, ale tego nie mierzyłem); replayu na sesji w stanie
`mail_blad` z `mail_proby < 3`, gdzie ponowna wysyłka jest **zamierzona**.

## Instrument, który skłamał — i jak to wyszło

Pierwsza wersja sondy czytała tryb z `$argv[1]`. Pod `wp eval-file` **tego tam nie ma**, więc
przebieg oznaczony jako „kalibracja" po cichu poszedł trybem `prawdziwy` i wypisał `EXIT=0`.
Gdybym nie drukował nagłówka `TRYB:` w każdym przebiegu, zameldowałbym „kalibracja zdana"
mając w ręku dwa identyczne przebiegi prawdziwego kodu. Poprawione na `$args[0]` + zmienną
środowiskową, z komentarzem w pliku sondy. ⛔ Reguła: **sonda z trybami musi drukować, w którym
trybie naprawdę poszła** — inaczej „kalibracja" jest deklaracją, nie pomiarem.

---

# H3 — POCZTA (MAILPIT)

**Stan wejściowy z mapy:** 🟡, „audyt maili". **Wynik: 0 znalezisk, cztery tereny czysto —
ale z trzema zastrzeżeniami do przyrządu, bo cały dowód pocztowy zlecenia stoi na tym łapaczu.**

| # | Co sprawdzone | Wynik |
|---|---|---|
| 1 | **Którędy poczta naprawdę wychodzi** | **CZYSTO, zmierzone**: `wp-content/mu-plugins/mailpit-smtp.php` przestawia PHPMailera na SMTP `gethostbyname('sklep_mailpit')`:`1025`, `SMTPAuth=false`. `sendmail_path` w PHP zostaje domyślny, ale nie jest używany. Żadnej wtyczki SMTP w `plugins/` nie ma |
| 2 | **Czy łapacz zmienia nadawcę produktu** | **CZYSTO**: mu-plugin ustawia `wp_mail_from` na `wordpress@example.com` **bez priorytetu** (10), a filtr produktu `sklepag_filtr_nadawca_email` jest dokładany **później** przy tym samym priorytecie — więc przy wypełnionej opcji `sklepag_nadawca_email` **wygrywa produkt**, a łapacz daje tylko wartość domyślną. To ważne dla `E7-F3`: obserwowane `From: wordpress@example.com` to **skutek pustej opcji**, nie nadpisania przez warsztat |
| 3 | **Czy ścieżka AWARII wysyłki była kiedykolwiek ćwiczona** | **CZYSTO**: w bazie stoją zamówienia `mail_blad` z `mail_proby = 3` (`#10`, `#11`, `#19`, `#20`) — czyli budżet trzech prób i „panel-czerwone" mają pokrycie w danych. ⚠️ Wszystkie są z **lipca i sierpnia**; od tamtej pory nikt tej ścieżki nie ruszał |
| 4 | **Czy moje dzisiejsze sondy czegoś nie wysłały** | **CZYSTO**: łapacz ma **98 wiadomości przed i po** wszystkich dzisiejszych przebiegach (`E7`, golden, replay). Filtry `pre_wp_mail` zadziałały |

## Trzy zastrzeżenia do przyrządu — to jest treść „częściowo" w tym terenie

1. ⛔ **Łapacz przyjmuje wszystko.** SMTP bez uwierzytelniania, bez TLS, bez SPF/DKIM/greylistingu.
   „Mail wyszedł" na tym stosie znaczy wyłącznie **„PHP oddał wiadomość do SMTP"** — i nic
   o dostarczalności u klienta. Dług jest zapisany (`CO-WISI`: „dostarczalność poczty na
   hostingu (SPF/DKIM; dowód stoi na łapaczu)"), ale warto go czytać razem z tym, że **każdy
   nasz dowód pocztowy ma tę granicę**.
2. ⛔ **`wp_mail` nie ma tu jak zwrócić `false`.** Skoro łapacz przyjmuje wszystko, gałąź awarii
   (`mail_blad`, ponowienia, kompensacja tokenu) **nie odpali się sama** — musi być wstrzyknięta.
   Zielony przelot E2E nie jest więc dowodem, że tamta gałąź działa; dowodem są osobne testy
   z podstawioną awarią.
3. ⛔ **Dowód pocztowy jest ULOTNY.** Baza łapacza to `/tmp/mailpit-1787681659435740998.db`
   **wewnątrz kontenera** (700 KB, 98 wiadomości, `MessagesDeleted: 0`, uptime ~7 dni).
   `docker restart` albo odtworzenie kontenera kasuje **cały materiał dowodowy o mailach**,
   po cichu i bez śladu. Jeśli któryś dowód w raportach powołuje się na „wiadomość w łapaczu",
   ma dziś wartość dopóty, dopóki ten kontener stoi.

## Czego w `H3` nie zmierzyłem

- **Treści maili innych niż zakupowe** — E7 zmierzył mail po zakupie; nie sprawdzałem, czy
  boty (ChatBot, Segregator) też wysyłają cokolwiek do klienta i czy ich nadawca jest spójny.
- **Zachowania przy odrzuceniu przez serwer SMTP** (kod 5xx z łapacza) — nie da się wywołać
  bez zmiany konfiguracji łapacza, czyli zmiany wspólnego stanowiska.
- **Nagłówków `List-Unsubscribe`, `Message-ID`, `Date`** i tego, jak mail wygląda w filtrach
  antyspamowych — poza zasięgiem łapacza.

---

# A1 — `sklep-agentow`, LOGIKA PHP

**Stan wejściowy z mapy:** 🟡, „audyt K7 (nasza siatka) + recenzja kolegi 31.08 (JEGO siatka),
kolega: 9/10". **Wynik: 1 × `SRED` — i ono koryguje mój własny wynik z terenu `A5`.**

## Pytanie, którym wszedłem — bo po terenie już wchodzono i wyszło zero

⛔ `LEKCJA 3` z 1.09 mówi wprost: `A1` przejechane **po terenie** dało **ZERO**. Nie powtarzam
tego. Wziąłem soczewkę z `LEKCJI 1` — tej, która opisuje błąd właściciela — i **rozciągnąłem ją
o jeden krok**:

> Lekcja pyta: *co może zmienić INNA OSOBA, gdy operacja trwa.*
> Moje pytanie: **co się dzieje, gdy zmieni się to, NA CO WSKAZUJE zapisana ścieżka** —
> czyli gdy operacja jest już dawno skończona, a świat pod jej wynikiem się przesunął.

Sklep trzyma w bazie **ścieżki bezwzględne**: `sklepag_katalog_chroniony` (opcja),
`produkty.plik_sciezka`, `zamowienia.produkt_plik_kupiony` (42 wiersze). Link pobrania żyje 72 h,
a plik produktu — bezterminowo.

## 1. ZNALEZISKO

### 🟠 `A1-F1` — **SRED** · katalogów chronionych może być KILKA, a `uninstall.php` zna tylko ten ostatni — pliki produktów (w tym sprzedane paczki) **przeżywają „pełne czyszczenie"**

**ZNALEZISKO.** `sklepag_utworz_katalog_chroniony()` (`includes/aktywacja.php:231-235`) tworzy
**nowy** katalog z losową nazwą, gdy zapisany w opcji **nie istnieje**:
```
232:	$istniejacy = get_option( 'sklepag_katalog_chroniony', '' );
233:	if ( $istniejacy && is_dir( $istniejacy ) ) { return; }
238:	$sciezka = … 'sklepag-chronione-' . wp_generate_password( 16, false, false );
```
Nowa ścieżka **nadpisuje opcję**, a stary katalog — z plikami produktów, na które wskazują
wiersze zamówień — zostaje na dysku i **nikt go już nie zna**. `uninstall.php:44-63` kasuje
dokładnie jeden katalog: ten z opcji.

**DOWÓD — stan żywego stanowiska, nie hipoteza:**
```
opcja sklepag_katalog_chroniony  → sklepag-chronione-56dR4cvfac3etSkZ   (2 pliki: .htaccess + index.php)
pliki OBU produktów leżą w       → sklepag-chronione-eksx2R9XMHNK0AqE   (9 plików, 184 KB)
42 zamówienia wskazują na        → sklepag-chronione-eksx2R9XMHNK0AqE
trzeci katalog, niczyj           → sklepag-chronione-1aEJajvPekntwaZP   (2 pliki)
```
Czyli **na tym stanowisku katalogi są trzy**, a opcja wskazuje ten, w którym **nie ma ani
jednego pliku produktu**. Gdyby dziś ktoś zrobił „Pełne czyszczenie" i odinstalował wtyczkę,
zniknąłby katalog pusty, a **184 KB plików produktów — w tym paczki, które kupili ludzie —
zostałoby w `uploads/`** wraz z ich `.htaccess`. Instrukcja obiecuje co innego:
*„znikają tabele, opcje, strony sklepu i pliki chronione"* (`INSTRUKCJA-INSTALACJI.md`,
rozdz. „Odinstalowanie").

**MECHANIZM — kiedy powstaje drugi katalog.** Warunek `is_dir()` jest sprawdzany przy KAŻDEJ
aktywacji. Wystarczy, że w chwili aktywacji katalog z opcji jest niewidoczny: odtworzenie bazy
z kopii zrobionej przy innym katalogu, migracja `wp-content/uploads` na inny hosting,
sprzątanie `uploads/` przez administratora, przeniesienie strony. Wtedy powstaje nowy katalog,
a produkty **nadal wskazują na stary** — i to jest stan, w którym sklep dziś stoi.

**SKUTEK dla człowieka.** Właściciel, który odinstalował wtyczkę „z pełnym czyszczeniem",
jest przekonany, że pliki zniknęły — a leżą dalej w `uploads/`, chronione wyłącznie
`.htaccess` (na nginx **ignorowanym**, co ta sama dokumentacja przyznaje). Przy sprzedaży
plików cyfrowych to są **cudze paczki po zakończonej działalności sklepu**.

**Dlaczego `SRED`, a nie wyżej:** nic nie ginie i nikt nie traci pieniędzy; wada dotyczy
**sprzątania**, nie sprzedaży, a katalogi-sieroty nie są publicznie listowane. Podniósłbym,
gdyby stary katalog tracił `.htaccess` (nie traci — sprawdzone: wszystkie trzy mają
`.htaccess` i `index.php`).

## 2. ⛔ KOREKTA MOJEGO WŁASNEGO WYNIKU Z `A5`

W terenie `A5` policzyłem **64 zapisy poza własnymi tabelami, 62 sprzątane**, a pozycję 32
(„katalog chroniony w `uploads` + `.htaccess`, `index.php` i wgrane paczki ZIP") oznaczyłem
✅ z uwagą, że uninstall „kasuje **tylko** ścieżkę pasującą do wzorca". Opis był prawdziwy,
**wniosek nie**: sprawdziłem, co kod kasuje, a nie **ile katalogów kod potrafi stworzyć**.
Poprawny rachunek: **64 zapisy · 61 sprzątanych · 3 nietykane** (dwa poprzednie: reguła
przepisywania adresów i transient aktywacyjny ChatBota — oba opisane jako niegroźne — plus
ten katalog, który groźny jest). To jest dokładnie klasa, na którą sam się powołuję w raporcie
`A5`: **listę sprzątania sprawdza się wobec listy zapisów, a nie wobec jednego egzemplarza zapisu.**

## 3. TERENY SPRAWDZONE — WYNIK CZYSTO

| # | Co sprawdzone | Wynik |
|---|---|---|
| 1 | **Czy ponowna aktywacja gubi katalog** (najprostszy scenariusz „ktoś coś zmienił") | **CZYSTO**: `if ( $istniejacy && is_dir( $istniejacy ) ) return;` — deaktywacja i aktywacja **nie tworzy** nowego katalogu i nie zrywa linków |
| 2 | **Czy migawka pliku w zamówieniu naprawdę trzyma** | **CZYSTO**: 42 zamówienia ze ścieżką bezwzględną, **0** z nich wskazuje na plik, którego nie ma. Kupujący sprzed miesięcy nadal pobiera to, co kupił |
| 3 | **Czy plik produktu da się pobrać z pominięciem bramy** | **ZMIERZONE: `HTTP 403`** na plik i na listing katalogu. ⛔ **Kontrola dodatnia NIEROZSTRZYGAJĄCA i tak to zapisuję**: korzeń `uploads/` też oddaje 403, a w `uploads/` nie ma ani jednego pliku spoza katalogów chronionych, więc **nie umiem oddzielić zasługi `.htaccess` od ogólnej konfiguracji serwera**. „403" jest faktem, „`.htaccess` działa" — **nie jest** dowiedzione tym pomiarem |

## 4. CZEGO W `A1` NIE ZDĄŻYŁEM

- **Reszty logiki PHP sklepu** — wszedłem JEDNĄ soczewką (ścieżki i to, co się pod nimi
  przesuwa), bo przejazd po terenie już raz dał zero. Pozostałe pytania z `LEKCJI 1`
  (koszyk, zaproszenie, rezerwacja) w tym produkcie nie mają odpowiedników.
- **Równoległego „Wyślij ponownie"** — dwa kliknięcia naraz rotują token dwa razy; kod nie ma
  tu CAS-a takiego jak przy domknięciu. Zauważone, **nie zmierzone** — kandydat na osobny pomiar.
- **Zachowania na nginx** (gdzie `.htaccess` jest ignorowany) — poza tym stanowiskiem.

---

# A2 i A3 — CHATBOT LIVE i SEGREGATOR MAILA

**Stan wejściowy z mapy:** oba 🟡 („wroga sesja K2, przeloty botów" · „dowód zachowania
+ próba kontrolna"). **Wynik: 0 nowych znalezisk, jedna klasa potwierdzona jako ZAMKNIĘTA
na obu bliźniakach, jeden trop wygaszony uczciwie.**

## Soczewka: **czytam, co się NAPRAWDĘ wywaliło, a nie czego się spodziewam**

⛔ `LEKCJA 3` mówi, że przejazd po terenie dał w tych obszarach **ZERO** — nie powtarzam go.
Wziąłem jedyne źródło, którego w tym projekcie nikt nie otwierał, a które zapisuje **skutki,
nie intencje**: `wp-content/debug.log` na stanowisku. **2,0 MB, 6376 wpisów, 276 fatali,
6010 ostrzeżeń** z okresu 20.08-1.09. Pytanie brzmiało: *które z tych awarii są DZIŚ jeszcze
możliwe na `HEAD` gałęzi?* — więc każdą klasę sprawdzałem potem w kodzie.

## 1. WYNIKI — klasa po klasie

| # | co się wywalało (liczba) | wtyczka | stan na `HEAD` |
|---|---|---|---|
| 1 | `Uncaught ValueError: mb_convert_encoding(): … invalid encoding "Windows-1250"` — **53×**, 20-31.08 | ChatBot, `includes/import.php` | ⭐ **ZAMKNIĘTE**: konwersja idzie dziś przez `iconv( 'CP1250', 'UTF-8//IGNORE', … )` (`import.php:114`), a **komentarz nad nią zapisuje POWÓD** (`:87-90`): „mbstring **nie zna CP1250** … `mb_convert_encoding( …, 'Windows-1250' )` kończy się rzuconym ValueError". Czyli naprawa niesie ze sobą dowód, dlaczego nie wolno wrócić do `mb_*` |
| 2 | ta sama klasa, **1×**, 20.08 | Segregator, `includes/csv.php` | ⭐ **ZAMKNIĘTE U BLIŹNIAKA TEŻ** — `csv.php:85` `iconv( $kodowanie, 'UTF-8//IGNORE', $tekst )`, z tym samym komentarzem o PHP 8.4 (`:68-74`). **To jest wynik warty osobnego zdania:** projekt ma nazwaną klasę „naprawiasz egzemplarz, bliźniak zostaje" i **tutaj się nie powtórzyła** |
| 3 | `Allowed memory size … exhausted` — **15×** | rdzeń WP (`class-wpdb.php`, `class-wp-object-cache.php`) | **NIE ŻYWE**: wszystkie z **jednego dnia, 21.08**, w oknie, w którym w logu stoją obok wpisy `chatl_ai_odpowiedz(): … (błąd połączenia: **przechwycone przez audyt**)` — czyli podczas przelotu z wstrzykniętą awarią. Limit 128 MB |
| 4 | `Call to undefined function chatl_zdarzenia_stan() / chatl_przejmij() / sklepag_obsluz_anonimizacje()` — **10×** | — | **NIE PRODUKT**: wszystkie z `phar:///usr/local/bin/wp/…eval-command`, czyli z cudzych (i moich) sond wołających funkcję bez załadowania jej pliku. Dwa z 1.09 są **moje** (sonda `E7`) |
| 5 | `Trying to access array offset on null` w `segregator-maila/includes/skrzynka-przebieg.php:274-275` — **184×**, 26-31.08 | Segregator | **NIE ODTWARZA SIĘ NA `HEAD`**: dziś w tych liniach stoi `$kategoria = $decyzja['kategoria'];` i `$dopasowal = null !== $decyzja['regula_id'];`, a `segm_klasyfikuj_mail()` (`includes/silnik.php:22`) ma **dwa wyjścia i oba zwracają tablicę** (`:34`, `:41`) — `null` nie ma którędy wrócić. ⛔ **Czego NIE sprawdziłem:** co stało w tych liniach w chwili tamtych ostrzeżeń (wymaga archeologii `git log -p`), więc nie twierdzę, że to była TA sama linia — twierdzę tylko, że **dzisiejszy kształt tej awarii nie produkuje** |
| 6 | `chatl_ai_odpowiedz(): wywołanie API nie powiodło się, bot odpowiada z reguł` — **3968×** (ostrzeżenia, nie fatale) | ChatBot | **ZACHOWANIE ZAMIERZONE**, nie awaria: to zjazd do reguł przy błędzie API (HTTP 429, brak połączenia). Najgłośniejsza ścieżka w całym logu i **ma swój trwały znacznik** — patrz niżej |

## 2. TEREN SPRAWDZONY PRZY OKAZJI — KANAŁ CICHEJ AWARII

Oba boty mają wspólny problem projektowy: `wp_trigger_error()` **milczy przy `WP_DEBUG=false`**,
czyli na każdej normalnej instalacji klienta (rdzeń WP kończy je natychmiast). Sprawdziłem,
czy kompensacja istnieje **wszędzie**, a nie tylko w jednym miejscu:

| | ChatBot | Segregator | sklep |
|---|---|---|---|
| wierszy z nazwą `wp_trigger_error` (grep po nazwie, nie potwierdzone wywołania) | **7** | **7** | 0 |
| trwałe znaczniki (opcja + pasek w panelu) | `chatl_blad_ai`, `chatl_blad_bazy`, `chatl_blad_maila`, `chatl_pulapka` | `segm_blad_ai`, `segm_blad_bazy` | — (sklep ma własny dziennik zdarzeń) |
| gołe `error_log()` w kodzie | **0** | **0** | 0 |

⚠️ **Instrument, który skłamał mi po drodze:** `grep -c "error_log("` dał **1** w każdym bocie
i przez chwilę wyglądało to na złamanie zakazu z `phpcs.xml.dist`. Oba trafienia są
**w komentarzach** (`chatbot-live.php:29`, `segregator-maila.php:34`) — w zdaniu tłumaczącym,
że kod tego **nie** używa. ⛔ Liczenie wywołań po nazwie funkcji liczy też komentarze; to trzecia
dziś sytuacja z tej rodziny (po dopasowaniu prób kontrolnych po nazwie pliku i po `slady`).

## 3. CZEGO W `A2` I `A3` NIE ZDĄŻYŁEM — i to jest większość tych obszarów

- **Logiki obu botów** poza tym, co zostawiło ślad w logu. Ta soczewka widzi wyłącznie awarie,
  które **wystąpiły na tym stanowisku** — nie widzi ścieżek nigdy nieuruchomionych, a właśnie
  takie są najciekawsze (`A7c-F1` z dekodowania MIME wyszło z atrapy, nie z logu).
- **Plików, których nie tknęła żadna soczewka** — z raportu `A7` zostaje lista:
  `statystyki.php`, `import.php`, `przejecie.php`, `admin.php`, `filtry.php`, `assets/`.
- **STARTTLS klienta IMAP i wydajności przy większych danych** — zaparkowane wcześniejszą decyzją.
- **Archeologii `skrzynka-przebieg.php`** — patrz wiersz 5 tabeli.
