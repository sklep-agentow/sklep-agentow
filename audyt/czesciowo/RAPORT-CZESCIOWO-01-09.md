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
powstaje w bazie dopiero przy domknięciu, a `sklepag_status_zamowienia()` (`zakup.php:1245`)
na nieznaną sesję **z założenia** oddaje `oczekuje` (zmierzone:
`curl 'localhost:8890/wp-json/sklepag/v1/status?sesja=cs_test_calkiem_zmyslona_123'` → `{"status":"oczekuje"}`).
Ten projekt ma już zapisany przypadek, w którym ten stan trwał **20 minut**: K10 (30.08) —
webhook nie doszedł, cron na hostingu nie chodził, opłacony zakup wisiał (komentarz w
`zakup.php:1270` opisuje to zdarzenie). Przez 18 z tych 20 minut kupujący czytał zdanie,
które przestało być prawdą, i nie dostał żadnej wskazówki, co zrobić.

**Dlaczego `SRED`, a nie wyżej:** pieniądze są pobrane poprawnie, mail w normalnym biegu
przychodzi, a link pobrania i tak jedzie pocztą — awaria dotyczy **informowania** kupującego
w oknie, w którym on właśnie zapłacił i nie wie, czy coś poszło nie tak.

---

### 🟡 `E7-F2` — **MALE** · panel pokazuje właścicielowi **godzinę wygaśnięcia linku w GMT, podpisaną jak czas lokalny** — na tym samym ekranie, dwa wiersze niżej, ta sama klasa jest już naprawiona

**ZNALEZISKO.** `admin-zamowienie.php:336` drukuje kolumnę `link_wygasa` surową:
```php
echo '<tr><td>Pobrania</td><td>' . … . ( $zam->link_wygasa ? ' · link ważny do ' . esc_html( $zam->link_wygasa ) : … );
```
Kolumna jest zapisywana `gmdate()` (`zakup.php:951`), czyli **w GMT**. Sąsiednie czasy na tym
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
(`zakup.php:1102-1106`). Pusta opcja = brak `Reply-To` **i** zniknięcie wiersza
„Reklamacje i faktury" ze stopki sprzedawcy (`zakup.php:1176-1179`). Odpowiedź kupującego idzie
wtedy na adres NADAWCY — czyli na `sklepag_nadawca_email`, a gdy i ta jest pusta,
na domyślny adres WordPressa (`wordpress@domena`), który zwykle nie jest skrzynką.

**Pulpit ostrzega o danych sprzedawcy — o tym nie ostrzega.** `admin-dashboard.php:386`
sprawdza wyłącznie `sklepag_sprzedawca()['kompletne']`, a to jest `firma_nazwa && firma_adres`
(`zakup.php:1161`). Mail kontaktowy nie wchodzi do tego warunku, a opis pola w panelu mówi
**mniej, niż pole robi**: „Pokazywany kupującym przy problemie z pobraniem (link wygasł / limit
wyczerpany)" (`admin-ustawienia.php:366`) — ani słowa o tym, że to jedyny adres zwrotny maila
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
| 1 | **Zgodność wersji tekstowej i HTML maila** — czy klient poczty czytający HTML dostaje mniej | porównanie obu składaczy (`zakup.php:1184`, `mail-html.php:38`) + zliczenie w sondzie | **CZYSTO** — obie wersje niosą ten sam komplet: numer, produkt, data zakupu, kwota, potwierdzenie zgody z chwilą, link i jego ważność, instrukcja 3 kroki, gwarancja, opinia, faktura, stopka sprzedawcy. HTML dokłada tylko adres strony i zdanie „to jest potwierdzenie umowy — zachowaj go" |
| 2 | **Słownik statusów strony „Dziękujemy"** — czy jest status, przy którym strona kłamie | zestawienie statusów pisanych do `zamowienia.status` w kodzie z tablicą `opisy` w `dziekujemy.js` | **CZYSTO** — 7 statusów zamówienia (`oczekuje, oplacone, mail_w_toku, mail_wyslany, mail_blad, zwrocone, sporne`) ma po zdaniu, plus `brak` i zdanie zapasowe `nieznany`, które celowo nie twierdzi nic o pieniądzach. Statusy `zalazek/oczekuje/przyjeta/odrzucona` należą do opinii, nie do zamówień |
| 3 | **Liczby w mailu wobec kodu** — „ważny 3 dni", „10 pobrań", „opinia 30 dni" | liczone ze stałych w chwili składania maila (`round(SKLEPAG_LINK_GODZIN/24)`, `SKLEPAG_LIMIT_POBRAN`, `SKLEPAG_OPINIA_DNI`) | **CZYSTO** — żadnej liczby nie da się rozjechać z kodem, bo żadna nie jest wpisana ręcznie |
| 4 | **Obietnica „link do opinii jest jednorazowy i ważny 30 dni"** | `opinie.php:85-90` (termin + porównanie stałoczasowe) i `:132` (`token_hash=''` przy zapisie) + żywy strzał na zły token | **CZYSTO** — obie części obietnicy egzekwowane; zły/zużyty link daje HTTP 200 ze zdaniem „opinia mogła już zostać wysłana albo link wygasł (30 dni od zakupu)" |
| 5 | **Strony błędu dla kupującego** (zły token, wygasły link, brak pliku, wyczerpany limit) | **żywy strzał** `curl` na `admin-post.php` dla złego tokenu i dla nieistniejącego zamówienia; pozostałe trzy gałęzie **odczytane z kodu**, nie odpalone | **CZYSTO co do treści**. Zmierzone: HTTP 403 „Nieprawidłowy link pobrania. Skontaktuj się z właścicielem sklepu (odpisz na maila z zakupem)." — ten sam kod i to samo zdanie dla nieistniejącego `z`, więc strona nie zdradza, które zamówienia istnieją. Odczytane z `pobieranie.php:45,50,77`: 410 „Link pobrania wygasł…", 503 „Plik chwilowo niedostępny…", 410 „Limit pobrań wyczerpany…" — każde mówi prawdę o przyczynie i dokłada kontakt. ⛔ Tych trzech **nie uruchomiłem**: wymagałyby wpisu do bazy sklepu (cofnięty termin / usunięty plik / zużyty limit), a danych stosu nie ruszam. Zastrzeżenie do samego kanału kontaktu opisuje `E7-F3`, nie treść tych zdań |

## 3. CZEGO W `E7` NIE ZDĄŻYŁEM

- **Wygląd maila w prawdziwym kliencie poczty** (Gmail/Outlook) — mierzyłem treść i strukturę,
  nie render. Do tego trzeba konta pocztowego, nie łapacza.
- **Teksty panelu poza ekranem zamówienia** (Produkty, Opinie, Dziennik, Ustawienia) — przejrzane
  pobieżnie przy okazji `E7-F3`, bez własnego przelotu. To zostaje jako reszta terenu `E7`.
- **Baner „TRYB TESTOWY"** — świadomie pominięty, siedzi w `P-3` (w naprawie).

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

**Egzemplarz A — rozdział 3, tabela statusów, wiersz „oczekuje" (`INSTRUKCJA.md:82`):**
> „jeśli wisi dłużej niż kilka minut, to błąd zapisu w bazie: sprawdź oś czasu i napisz do
> wykonawcy (**„Dociągnij zaległe" tego statusu nie rusza**)"

W kodzie jest odwrotnie. Zamówienie może stać w `oczekuje` **wyłącznie** wtedy, gdy jego sesja
ma nadal stan `utworzona`: znacznik `oplacona` zakłada `sklepag_oznacz_sesje( $sid, 'oplacona' )`
w `zakup.php:900`, czyli **po** CAS-ie `oczekuje → oplacone` (`:871`) i po sprawdzeniu, że wiersz
zamówienia istnieje (`:878`). To jedyne miejsce w kodzie, które nadaje ten stan
(`grep sklepag_oznacz_sesje` → 3 wywołania: `wygasla`, `oplacona`, `wygasla`).
A „Dociągnij zaległe" bierze dokładnie takie sesje:
```
1293:  SELECT stripe_session_id FROM {$ps} WHERE stan='utworzona' AND utworzono < %s ORDER BY utworzono DESC LIMIT 100
1320:  if ( sklepag_domknij_zakup( $sesja ) ) {   // TEN SAM silnik co webhook
```
i puszcza na nich **ten sam silnik**, którego pierwszy krok to `INSERT IGNORE` (nie zdubluje
wiersza), a drugi to CAS `… SET status='oplacone' … AND status='oczekuje'` — czyli **celuje
dokładnie w ten wiersz, o którym instrukcja mówi, że przycisk go nie rusza**.

Kierunek pomyłki jest ten kosztowny: właściciel czyta „przycisk nie pomoże, napisz do wykonawcy"
i **nie klika jedynej rzeczy, która to odblokowuje** — a na hostingu bez działającego WP-Cron
(przypadek, przed którym ta sama instrukcja ostrzega dwa akapity niżej) ten przycisk jest jedyną
drogą domknięcia.

**Egzemplarz B — rozdział 4 (`INSTRUKCJA.md:158-159`):**
> „sklep ponawia sam **po ok. 2 minutach**, do trzech razy"

Ponowienie wysyłki nie ma własnego zegara. Siedzi w środku `sklepag_dociagnij_zalegle()`
(`zakup.php:1337-1342`), czyli w zadaniu, które chodzi **co 10 minut**
(`sklep-agentow.php:77` `'interval' => 600`), a warunek `zmieniono < %s` z granicą `teraz − 120 s`
jest **minimalnym wiekiem** wiersza, nie odstępem ponowień. Prawdziwe zdanie brzmi: „przy
najbliższym biegu co 10 minut, nie wcześniej niż 2 minuty po ostatniej próbie — i tylko jeśli
WP-Cron działa".

**Trzecie miejsce w tym samym pliku mówi to poprawnie** (rozdział 9, `INSTRUKCJA.md:296-300`):
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
| 3 | **„sklep co 10 minut SAM dopytuje Stripe"** + **„odczekaj 2 minuty"** | `sklep-agentow.php:77` `'interval' => 600`, `zakup.php:1284` granica `− 120 s` | **CZYSTO** — obie liczby zgodne z kodem |
| 4 | **Adres webhooka `…/wp-json/sklepag/v1/webhook`** | `register_rest_route( 'sklepag/v1', '/webhook' )` | **CZYSTO** |
| 5 | **„Jeśli zapomnisz o odnośnikach — wtyczka pokaże czerwone ostrzeżenie z linkiem"** | `admin.php:33-37` (`notice-error` + link do `options-permalink.php`, warunek `'' !== permalink_structure`) | **CZYSTO** |
| 6 | **„Klucze Stripe i zadanie cykliczne znikają ZAWSZE"** (sklep) oraz **„Klucz API każdego bota usuwany ZAWSZE"** | trzy pliki `uninstall.php` | **CZYSTO** — sklep kasuje oba sekrety i `wp_clear_scheduled_hook` **przed** wczesnym `return`; ChatBot kasuje `chatl_klucz_api` przed `return`; Segregator kasuje `segm_klucz_api` **i `segm_imap_haslo`** przed `return`. To jest klasa nr 1 ze `SCIAGA-EGZAMINATORA.md` i tu jest zamknięta |
| 7 | **Lista sześciu miejsc, w których kupujący widzi „tryb testowy"** (`INSTRUKCJA-INSTALACJI.md:259-266`) | render żywych stron + kod | **CZYSTO — 6/6 istnieje**: baner na `/sklep/` i na stronie produktu (`sklepag_baner_testowy`), baner na „Dziękujemy", notka „STRIPE · TRYB TESTOWY · KARTA 4242…" przy przycisku, FAQ „Co znaczy „tryb testowy”?" (`sklep-agentow.php:403`, widoczne w rendrze `/sklep/`), zdanie w mailu. Instrukcja dodatkowo **sama zastrzega**, że lista może nie być kompletna |
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
- ⭐ **Odsyłacz do `E7-F1`:** `INSTRUKCJA-INSTALACJI.md:148`, Krok 5 pkt 3, obiecuje kupującemu
  „strona sama odświeża status **aż do** «Mail wysłany»". To jest dokładnie to zdanie, które
  `E7-F1` obala pomiarem — instrukcja powtarza obietnicę strony, nie jej zachowanie.
