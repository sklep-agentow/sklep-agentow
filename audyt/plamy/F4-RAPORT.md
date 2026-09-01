# F4 — Retencja danych kupujących

**Audytor:** sesja audytu białych plam, 2026-09-01 · **Gałąź:** `audyt/biale-plamy-01-09`
**`commit_head`:** `7a8fb5b` · **Produkt:** `sklep-agentow` 0.9.28 · **Stanowisko:** `localhost:8890`
**Zakres z mapy terenu:** `F4 | Retencja danych kupujących | ⬜ NIETKNIĘTE`

---

## Wobec czego oceniam (kryteria + skąd) — ZAPISANE PRZED OGLĄDANIEM

Przepisy słowo w słowo, pobrane i wyciągnięte tak samo jak przy `F5`
(RODO: EUR-Lex CELEX `32016R0679`, wersja PDF → `pdftotext -enc UTF-8 -layout`).

| # | Kryterium | Źródło |
|---|---|---|
| **R1** | Dane osobowe „**przechowywane w formie umożliwiającej identyfikację osoby, której dane dotyczą, przez okres nie dłuższy, niż jest to niezbędne do celów, w których dane te są przetwarzane** […] („ograniczenie przechowywania")" | RODO **art. 5 ust. 1 lit. e** |
| **R2** | Administrator podaje osobie, której dane dotyczą: „**okres, przez który dane osobowe będą przechowywane, a gdy nie jest to możliwe, kryteria ustalania tego okresu**" | RODO **art. 13 ust. 2 lit. a** |
| **R3** | Reguła zlecenia: „**Retencja** — ustal ile dni trzymacie co, i **kasuj po terminie** (nie wieczne logi rozmów)" | skill `bezpieczenstwo-danych-klienta`, §4 RODO MINIMUM |
| **R4** | Obietnica własna wobec osoby, której dane dotyczą: „dziennik zdarzeń (**90 dni**, w tym adres IP przy każdym pobraniu pliku)" | szablon Polityki, `aktywacja.php:192` |
| **R5** | Obietnica własna wobec właściciela: „wpisy starsze niż **90 dni** znikają **same** (sprzątanie **co 10 minut**)" | `INSTRUKCJA.md:229-230` |
| **W1** | Zasada zlecenia: **istnienie pliku/funkcji to nie dowód, że coś się URUCHAMIA** — pytaj osobno, czy kontrola jest WYWOŁYWANA | `jak-audytowac`, `references/lekcje.md` §LEKCJE-BOTY p. 4 |
| **W2** | Klasa 15 ściągi: **gałąź ratunkowa w martwym kodzie** — deklarowana obsługa nigdy się nie wykonuje | `audyt/SCIAGA-EGZAMINATORA.md` |

⛔ **Granica roli:** mierzę obecność mechanizmu, jego wywoływanie i stan danych. Kwalifikacji
prawnej „czy ten okres jest odpowiedni" nie stawiam — to decyzja administratora danych.

---

## ① Czy mechanizm ISTNIEJE i czy jest WOŁANY — **TAK, oba. Z dowodem.**

**Istnieje** — `wtyczki/sklep-agentow/includes/zakup.php:1364-1373`:

```php
// 3) Sprzątanie liczników rate-limit po oknie (tabela techniczna, wiersz żyje ~2 min).
$wpdb->query( "DELETE FROM {$wpdb->prefix}sklepag_rate_limit WHERE okno_do < UTC_TIMESTAMP()" );

// 4) Retencja 90 dni (polityka deklaruje „dziennik zdarzeń (90 dni)"):
$retencja = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 90 * DAY_IN_SECONDS );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_logowania WHERE czas < %s", $retencja ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_zdarzenia WHERE czas < %s", $retencja ) );
```

**Jest wołany — z DWÓCH miejsc, oba sprawdzone:**

| Wyzwalacz | Plik i linia | Uwaga |
|---|---|---|
| WP-Cron, co 10 min | `sklep-agentow.php:85-86` i `aktywacja.php:151-152` (`wp_schedule_event( …, 'sklepag_10min', 'sklepag_dociagnij_zalegle' )`), podpięcie: `zakup.php:1378` (`add_action`) | zdarzenie **jest zarejestrowane** na stosie (M1) |
| Wejście admina na Pulpit | `admin-dashboard.php:214-221` (`sklepag_dashboard_self_heal`), **wywołanie**: `admin-dashboard.php:324` | dławik: nie częściej niż raz na 600 s |

**Sprawdzone osobno (klasa 15 / zasada W2): czy sprzątanie da się POMINĄĆ.**
W całej funkcji `sklepag_dociagnij_zalegle` (`:1263-1377`) są **dokładnie dwa** `return`:
`:1269` — wewnątrz domknięcia `$po_budzecie()`, i `:1376` — `return $wynik`, **po** obu
`DELETE`-ach. Bramki budżetu czasu wychodzą z pętli przez `break`, nie przez `return`.
⭐ **Czyli krok 4 wykonuje się zawsze, gdy funkcja w ogóle ruszy** — także przy wywołaniu
z Pulpitu z budżetem 8 s. To NIE jest gałąź w martwym kodzie.

---

## ② Czy naprawdę CHODZI na stosie — **NIE. Nie biegło od co najmniej 2,5 godziny.**

Cztery niezależne pomiary, każdy z tej samej chwili (GMT 2026-09-01 ~01:41):

| # | Pomiar | Wynik |
|---|---|---|
| M1 | `wp cron event list` | `sklepag_dociagnij_zalegle` · **`next_run_gmt = 2026-08-31 23:09:57`** · `recurrence = 10 minutes`. Przy `UTC_NOW = 2026-09-01 01:41` zdarzenie jest **spóźnione o 2 h 31 min** — czyli ok. **15 pominiętych biegów**. WP przestawia `next_run` DOPIERO po wykonaniu, więc data w przeszłości znaczy „nie wykonało się" |
| M2 | `wp cron test` | **`Error: WP-Cron spawn failed with error: cURL error 7: Failed to connect to localhost port 8890`** — pętla zwrotna z wnętrza kontenera nie dochodzi, więc WP nie ma jak odpalić własnych zadań |
| M3 | `DISABLE_WP_CRON` w `wp-config.php` | **niezdefiniowane** — czyli WP-Cron nie jest wyłączony świadomie na rzecz crona systemowego; po prostu nie działa |
| M4 | **sonda na żywym liczniku** — `rate_limit` sprząta ten sam krok 3, a wiersz żyje ~2 min | **17 wierszy, z tego 17 PRZETERMINOWANYCH**; najnowsze okno zamknęło się `00:45:00 GMT`, czyli **56 minut** przed pomiarem. Gdyby sprzątanie ruszyło choć raz, tabela byłaby pusta |
| M5 | opcja `sklepag_reconciliation_ostatnia` (ustawia ją WYŁĄCZNIE ścieżka z Pulpitu) | `1788077083` = **2026-08-30 08:04:43 GMT**, czyli **2500 minut** (≈41,7 h) temu — drugi wyzwalacz też nie zadziałał, bo nikt nie wchodził na Pulpit |

**Kontrola dodatnia do tych zer:** w tej samej bazie, w tej samej chwili, **zapisy działają** —
najnowszy wiersz w `zdarzenia` ma czas `2026-09-01 03:08:18` (czas WP = GMT+2), czyli powstał
ok. 30 minut przed pomiarem, przy moim przelocie. Sklep pisze, tylko **nie kasuje**.
Zero z M4 nie jest ślepotą narzędzia: te 17 wierszy widzę, mają daty i wszystkie są przeterminowane.

⚠️ **Uczciwe rozgraniczenie:** przyczyna leży po stronie **stanowiska**, nie kodu produktu —
`wp_schedule_event` to standardowy mechanizm WordPressa, a pętla zwrotna nie dochodzi, bo
kontener nie umie połączyć się z `localhost:8890` (adres z mapowania hosta). Na hostingu z ruchem
WP-Cron zwykle chodzi. **Wady kodu tu nie zgłaszam.** Zgłaszam co innego — `F4-F2` niżej.

---

## ③ Co dokładnie ZOSTAJE po 90 dniach — tabela po tabeli

Siedem tabel `sklepag_` (`aktywacja.php`, `grep -c "CREATE TABLE"` = **7**).
Kolumny z danymi osobowymi wypisane z definicji schematu, liczby — z bazy stanowiska.

| # | Tabela | Dane osobowe w środku | Retencja automatyczna | **Co zostaje po 90 dniach** | Stan na stanowisku |
|---|---|---|---|---|---|
| 1 | `produkty` | brak | — | nie dotyczy | 2 wiersze |
| 2 | `sesje_checkout` | `stripe_session_id` (identyfikator wskazujący na dane kupującego po stronie Stripe) | ⛔ **ŻADNA** | **wszystko, bezterminowo** | 49 wierszy, najstarszy `2026-08-18` |
| 3 | `zamowienia` | `email_kupujacego`, `dane_faktury` (firma, NIP, adres), `mail_tresc` (pełna treść maila z adresem i linkiem), `zgoda_tresc`, `token_pobrania`, `dor_*` | ⛔ **ŻADNA** — tylko **ręczna** anonimizacja per zamówienie | **wszystko, bezterminowo**, dopóki właściciel nie kliknie anonimizacji na KAŻDYM zamówieniu z osobna | 43 wiersze · **41 z adresem e-mail** · **41 z treścią maila** · 1 z danymi faktury · **2 zanonimizowane** |
| 4 | `zdarzenia` | `opis` (m.in. **adres IP** przy pobraniach — `sklepag_ip()` w 8 miejscach kodu), `uzytkownik` | ✅ **90 dni** (`zakup.php:1373`) | wpisy z ostatnich 90 dni — w tym adresy IP | 2483 wiersze · **6 z adresem IP w treści** · najstarszy `2026-08-18` |
| 5 | `logowania` | `login_wpisany`, `ip`, `user_agent` | ✅ **90 dni** (`zakup.php:1372`) | wpisy z ostatnich 90 dni | 265 wierszy, najstarszy `2026-08-18` |
| 6 | `rate_limit` | `klucz` = `akcja\|okno\|md5( sklepag_ip() )` — IP **zahaszowany**, nie jawny (`zakup.php:32`) | ✅ po oknie (~2 min) | nic (przy działającym sprzątaniu) | 17 wierszy, **wszystkie przeterminowane** |
| 7 | `opinie` | `imie`, `tytul`, `tresc`, `zgoda_tresc`, `token_hash` | ⛔ **ŻADNA** — czyszczone tylko razem z anonimizacją zamówienia (`opinie.php:230`) | **wszystko, bezterminowo** | 30 wierszy, **0 z wypełnionym imieniem** |

**Odpowiedź jednym zdaniem:** po 90 dniach znikają **wyłącznie** wpisy z `zdarzenia`
i `logowania`; **dane kupującego — adres e-mail, dane do faktury i pełna treść maila po zakupie —
zostają na zawsze**, w trzech tabelach, dopóki właściciel nie zanonimizuje każdego zamówienia
ręcznie.

**Kontrola dodatnia do tego „żadna":** ten sam wzorzec `DELETE FROM` po całej wtyczce daje
**3 trafienia** (`rate_limit`, `logowania`, `zdarzenia` — wszystkie w `zakup.php`) plus 2
w `uninstall.php`. Zawężony do `sesje_checkout|zamowienia|opinie` → **0**. Wzorzec ten sam,
wynik różny → to brak kasowania, nie zła komenda.

---

## Znaleziska

### `F4-F1` — **DUZE** · Dane kupującego nie mają ŻADNEJ retencji, a polityka nie podaje ani okresu, ani kryteriów

**Objaw:** sklep trzyma adres e-mail kupującego, dane do faktury (nazwa firmy, NIP, adres)
i pełną treść maila po zakupie **bezterminowo**. Jedyna droga usunięcia to ręczne kliknięcie
anonimizacji **na każdym zamówieniu z osobna**. Zmierzone na stanowisku: 41 z 43 zamówień
ma adres e-mail i treść maila; zanonimizowane są 2.

**Plik i linia:** brak kodu jest tu znaleziskiem — `zakup.php:1364-1373` czyści trzy tabele
i **świadomie** pomija czwartą; komentarz `:1368-1369` mówi to wprost:
> „Historia SPRZEDAŻY żyje w `zamowienia` (status, kwoty, `mail_tresc`) — **jej retencja to osobna
> decyzja właściciela, nie ten krok**."

⭐ **Ten komentarz jest uczciwy i nie zgłaszam go jako błędu.** Zgłaszam to, że **decyzja
właściciela nigdzie nie jest od właściciela zbierana ani jemu przypominana**, a osoba, której
dane dotyczą, nie dostaje żadnej informacji o okresie:

- szablon Polityki (`aktywacja.php:192`) brzmi: „Zakres: adres e-mail kupującego, dane transakcji,
  dziennik zdarzeń (**90 dni**, …)". **Liczba 90 dni odnosi się WYŁĄCZNIE do dziennika.**
  Dla adresu e-mail i danych transakcji **nie ma ani okresu, ani kryteriów jego ustalenia**;
- wobec **RODO art. 13 ust. 2 lit. a** („okres, przez który dane osobowe będą przechowywane,
  a gdy nie jest to możliwe, **kryteria ustalania tego okresu**") — brak obu członów;
- wobec **art. 5 ust. 1 lit. e** („przez okres nie dłuższy, niż jest to niezbędne") — przechowywanie
  bezterminowe nie ma nawet zadeklarowanego celu, który by je ograniczał;
- wobec reguły zlecenia (skill, §4): „ustal ile dni trzymacie co, i **kasuj po terminie**".

**Skutek DLA KLIENTA:** właściciel sklepu jest administratorem danych i po roku ma w bazie komplet
adresów e-mail wszystkich kupujących oraz treści maili z ich danymi — bez terminu, bez mechanizmu
i bez zdania w polityce, które by to opisywało. Przy żądaniu z art. 15 albo kontroli UODO nie ma
czym się zasłonić. **Awaria cicha** — baza po prostu rośnie.

**RODZAJ SKUTKU:** `zaobserwowany` (41 z 43 wierszy z adresem e-mail i treścią maila; zero
mechanizmu kasującego, potwierdzone greppem z próbą kontrolną).
**Waga: DUŻA.**

**Propozycja naprawy:** (a) dodać ustawienie „po ilu dniach anonimizować zamówienia" z wartością
domyślną i wpiąć je do tego samego kroku 4, który już działa (`zakup.php:1373`) — mechanizm
anonimizacji **już istnieje i jest porządny** (`admin-zamowienie.php:374`), brakuje wyłącznie
wyzwalacza czasowego; (b) uzupełnić szablon Polityki o okres dla adresu e-mail i danych
transakcji, albo o kryteria (np. „do upływu przedawnienia roszczeń / obowiązku podatkowego").
⭐ Strażnik: test wstawiający zamówienie starsze niż próg i wymagający, żeby po biegu
`sklepag_dociagnij_zalegle` miało `email_kupujacego=''` i `usunieto_dnia` ustawione — **pada**
na dzisiejszym kodzie.

---

### `F4-F2` — **SRED** · Sklep nie pilnuje własnego sprzątania: obietnica „znika samo" jest bezwarunkowa, a mechanizm warunkowy — i nic nie mruga, gdy nie chodzi

**Objaw:** `INSTRUKCJA.md:229-230` mówi właścicielowi: „wpisy starsze niż **90 dni** znikają
**same** (sprzątanie co 10 minut)", a szablon Polityki mówi to samo osobie, której dane dotyczą.
Oba zdania są **bezwarunkowe**. Tymczasem ta sama instrukcja, 100 linii wyżej, przy domykaniu
zakupów stawia zastrzeżenie:
> „⚠️ „Co 10 minut" działa **tylko wtedy, gdy WordPress potrafi uruchamiać swoje zadania w tle**
> (WP-Cron). Na części hostingów i na stronach z małym ruchem **to nie działa**" (`:120-121`)

**Przy retencji tego zastrzeżenia nie ma** — a to właśnie retencja jest obietnicą złożoną
osobie trzeciej w dokumencie prawnym, nie tylko wygodą właściciela.

**Dowód, że to nie jest teoria** — na tym stanowisku sprzątanie nie ruszyło od ≥2,5 h przy
harmonogramie 10-minutowym (M1, M2, M4, M5 w §②). Gdyby stos stał tak trzy miesiące, dziennik
zdarzeń z adresami IP przekroczyłby zadeklarowane 90 dni i **nikt by się nie dowiedział**.

**Plik i linia:** `admin-dashboard.php` — Pulpit ma **6** bloków `notice notice-`
(`:360` katalog chroniony, `:365` niewiarygodne wskaźniki, `:378` brak konfiguracji płatności,
`:387` brak danych sprzedawcy, `:411` awarie Stripe, `:419` nieudane wysyłki maila).
**Żaden nie dotyczy zadań w tle ani retencji.** Sklep pilnuje pięciu innych rzeczy i nie pilnuje
tej jednej, którą obiecał w polityce prywatności.

**Skutek DLA KLIENTA:** właściciel jest przekonany, że dziennik czyści się sam, bo tak mówi
instrukcja i tak mówi jego własna polityka prywatności. Na cichej stronie albo na hostingu bez
WP-Cron nie czyści się nic, a dowiedzieć się o tym można wyłącznie zaglądając do
Narzędzia → Kondycja witryny — o czym instrukcja wspomina tylko przy innym temacie.

**RODZAJ SKUTKU:** `zaobserwowany` (spóźnienie zdarzenia + 17/17 przeterminowanych liczników).
**Waga: ŚREDNIA** (przyczyna po stronie środowiska, ale brak dozoru i bezwarunkowa obietnica
są po stronie produktu).

**Propozycja naprawy:** dołożyć siódmy `notice`: „ostatnie sprzątanie starsze niż X godzin"
— dane są już w bazie (`sklepag_reconciliation_ostatnia` albo `wp_next_scheduled`), a mechanizm
`notice` istnieje i działa; przy retencji w `INSTRUKCJA.md:229` powtórzyć zastrzeżenie o WP-Cron
z `:120-121`, bo to samo zdanie jest tu obietnicą wobec osoby trzeciej.

---

### `F4-F3` — **MALE** · `sesje_checkout` rośnie bez końca i bez żadnej wzmianki

**Objaw:** tabela `sesje_checkout` (49 wierszy, najstarszy `2026-08-18`) nie jest objęta żadnym
kasowaniem — ani po czasie, ani po domknięciu, ani po wygaśnięciu. Trzyma
`stripe_session_id`, czyli identyfikator, po którym u Stripe leży adres e-mail kupującego
i szczegóły płatności.

**Dowód:** `grep -rnE "DELETE FROM.*(sesje_checkout|zamowienia|opinie)"` → **0**, przy próbie
kontrolnej `grep -rcE "DELETE FROM" …/zakup.php` → **3**.

**Skutek DLA KLIENTA:** przy każdym porzuconym koszyku (kupujący kliknął „Kup", nie zapłacił)
zostaje trwały ślad wiążący produkt, czas i sesję płatniczą. Nie ma tam adresu wprost, ale jest
klucz, który do niego prowadzi — i nie ma powodu, żeby zostawał po wygaśnięciu sesji.

**RODZAJ SKUTKU:** `wyliczony`. **Waga: DROBNA.**

**Propozycja naprawy:** kasować wiersze `sesje_checkout` ze stanem `wygasla`/`domkniete`
starsze niż okno reklamacyjne, w tym samym kroku 4.

---

## Wyniki CZYSTE

1. ✅ **Mechanizm retencji istnieje i JEST WOŁANY z dwóch niezależnych wyzwalaczy** (cron +
   wejście na Pulpit) — sprawdziłem oba, nie samo istnienie funkcji.
2. ✅ **Krok sprzątania nie jest gałęzią w martwym kodzie** — w całej funkcji są dwa `return`,
   oba poza ścieżką pomijającą sprzątanie; bramki budżetu wychodzą `break`-iem.
   Sprawdzone wprost, bo klasa 15 ściągi każe o to pytać.
3. ✅ **Anonimizacja zamówienia jest gruntowna i sprawdza STAN PO ZAPISIE** —
   `admin-zamowienie.php:374` czyści `email_kupujacego`, `dane_faktury`, `mail_tresc`,
   `token_pobrania`, `link_wygasa` i wszystkie `dor_*` jednym `UPDATE`, a `:394-397` **odczytuje
   te kolumny z powrotem** i rozstrzyga na podstawie stanu, nie na podstawie wywołania.
   To zamyka klasę 71 ściągi (czyszczenie ślepe na kopie tej samej treści w innych kolumnach).
4. ✅ **`rate_limit` nie trzyma jawnego adresu IP** — klucz to `akcja|okno|md5( sklepag_ip() )`
   (`zakup.php:32`). Minimalizacja zrobiona.

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego | Do zrobienia |
|---|---|---|
| **Czy kasowanie po 90 dniach naprawdę kasuje** | na stanowisku **nie ma ani jednego wiersza starszego niż 14 dni** (najstarszy `2026-08-18`), więc zdarzenie, które ma zajść po 90 dniach, **nie mogło zajść** — nie mylę „nie zaszło" z „nie działa" | wstawić wiersz z datą sprzed 91 dni i uruchomić krok 4 — **to jest zapis do bazy, więc nie robię tego na wspólnym stosie**; do zrobienia przez buildera jako strażnik |
| **Uruchomienie `sklepag_dociagnij_zalegle` na żywo** | funkcja domyka zamówienia i pyta Stripe — builder pracuje na tym samym stosie, świadomie nie ruszam | na osobnym stanowisku |
| **Retencja w tabelach botów** (`chatl_`, `segm_`) | inne wtyczki, inny teren; widzę w cronie `chatl_czyszczenie_historii` i `segm_czyszczenie_probki` (raz na dobę) — **nie sprawdzałem, czy chodzą** | osobna plama |
| **Czy hosting docelowy ma cron systemowy** | nie znam hostingu klienta | pytanie do właściciela |
| **Kopie zapasowe** | dane usunięte z bazy mogą żyć w kopiach hostingu — poza naszym zasięgiem i poza kodem | zapisać w polityce jako zastrzeżenie |
| **Czy `stripe_session_id` jest daną osobową** | kwalifikacja prawna, nie moja rola | prawnik |

---

## ⭐ Czego NIE było na mojej liście (przeciwwaga)

1. **Retencja po stronie STRIPE.** Sklep kasuje u siebie, ale u Stripe zostaje komplet danych
   płatności i adres e-mail. Polityka wymienia Stripe jako podmiot przetwarzający i na tym
   kończy — nikt nie pytał, jak długo dane żyją tam i kto to kupującemu powie.
2. **Retencja plików produktów i logów serwera.** Pytałem o bazę; pliki w katalogu chronionym
   i logi Apache/nginx z adresami IP kupujących to osobna warstwa, której nikt nie objął.
3. **Prawo do przenoszenia i eksportu (art. 20).** Sprawdzałem kasowanie, nie wydawanie danych.
   `F3` w mapie mówi, że wtyczka nie ma eksportera do wbudowanego narzędzia WordPressa — to ta
   sama rodzina, ale nie to samo pytanie.
4. **Co się dzieje z danymi przy DEZAKTYWACJI wtyczki** (nie deinstalacji). Sprawdzałem
   `uninstall.php` tylko przelotnie, przy okazji `DELETE`-ów.
5. **Czy 90 dni to właściwy okres.** Przyjąłem liczbę z dokumentu jako obietnicę do
   zweryfikowania, nie jako wartość do oceny. Nikt nie pytał, skąd się wzięła i czy pasuje
   do celu przetwarzania.

---

## Propozycja wiersza do mapy terenu i do rejestru (gotowe do przeklejenia)

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja F — podmiana wiersza `F4`:**

```
| F4 | Retencja danych kupujących | 🟡 | **1.09**, `audyt/plamy/F4-RAPORT.md` (sha `7a8fb5b`), przepisy RODO cytowane ze źródła (EUR-Lex `32016R0679`) | ⛔ **3 znaleziska.** Retencja 90 dni obejmuje WYŁĄCZNIE `zdarzenia` i `logowania` (`zakup.php:1372-1373`). `zamowienia` (41 z 43 wierszy z adresem e-mail i pełną treścią maila), `opinie` i `sesje_checkout` — **żadnej retencji**, tylko ręczna anonimizacja per zamówienie; polityka nie podaje dla nich ani okresu, ani kryteriów (RODO art. 13 ust. 2 lit. a). Sprzątanie na stanowisku **nie biegło ≥2,5 h** przy harmonogramie 10 min (zdarzenie spóźnione, `wp cron test` = cURL 7, 17/17 liczników przeterminowanych) — przyczyna środowiskowa, ale **żaden z 6 komunikatów Pulpitu tego nie pilnuje**. ✅ CZYSTO: mechanizm istnieje i jest wołany z 2 wyzwalaczy, nie jest martwą gałęzią, anonimizacja sprawdza stan PO zapisie, `rate_limit` trzyma md5(IP). 🟡 nie sprawdzone: samo kasowanie po 90 dniach (na stanowisku brak wiersza starszego niż 14 dni), retencja w tabelach botów, retencja po stronie Stripe |
```

**Do rejestru znalezisk:**

```
| `F4-F1` | **DUZE** · `zakup.php:1364-1373` czyści `rate_limit`, `logowania` i `zdarzenia`, a `zamowienia`/`opinie`/`sesje_checkout` nie mają ŻADNEJ retencji (grep `DELETE FROM` zawężony do tych trzech → 0, próba kontrolna 3 trafienia w tym samym pliku); szablon Polityki (`aktywacja.php:192`) podaje 90 dni WYŁĄCZNIE dla dziennika, dla adresu e-mail i danych transakcji ani okresu, ani kryteriów — RODO art. 13 ust. 2 lit. a i art. 5 ust. 1 lit. e | stan bazy: 41 z 43 zamówień z `email_kupujacego` i `mail_tresc`, 2 zanonimizowane — BRAK strażnika | OTWARTE |
| `F4-F2` | **SRED** · `INSTRUKCJA.md:229-230` i szablon Polityki obiecują BEZWARUNKOWO „znikają same (sprzątanie co 10 minut)”, choć ta sama instrukcja przy domykaniu zakupów (`:120-121`) zastrzega, że WP-Cron bywa martwy; Pulpit ma 6 komunikatów (`admin-dashboard.php:360,365,378,387,411,419`) i **żaden nie pilnuje zadań w tle ani retencji** | zmierzone na stanowisku: zdarzenie spóźnione o 2 h 31 min przy 10-min harmonogramie, `wp cron test` → cURL 7, `rate_limit` 17/17 przeterminowanych (najnowsze okno 56 min wcześniej), `sklepag_reconciliation_ostatnia` sprzed 2500 min — BRAK strażnika | OTWARTE |
| `F4-F3` | **MALE** · `sesje_checkout` (49 wierszy, najstarszy 2026-08-18) nie jest objęta żadnym kasowaniem — trzyma `stripe_session_id` prowadzący do danych kupującego u Stripe, także dla porzuconych koszyków | `grep -rnE "DELETE FROM.*(sesje_checkout\|zamowienia\|opinie)"` → 0, próba kontrolna 3 — BRAK strażnika | OTWARTE |
```

---
⭐ **Lista zadań dla następnej sesji** — to, czego przy tej plamie NIE zdążyłem sprawdzić, jest zebrane razem z pozostałymi w `audyt/plamy/PRZEKAZANIE-NASTEPNEJ-SESJI.md`. Sekcja „Czego NIE sprawdziłem" wyżej jest źródłem tamtej listy, nie jej zamiennikiem.
