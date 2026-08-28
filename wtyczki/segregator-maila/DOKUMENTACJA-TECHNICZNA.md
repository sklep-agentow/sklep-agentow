# Segregator maila — dokumentacja techniczna

**Wersja wtyczki: 1.3.6 · wersja schematu bazy: 4 · WordPress 6.0+ · PHP 8.1+**

> **Po co ten dokument.** To jest **mapa systemu dla audytora i programisty**, nie ulotka.
> Każda liczba i każda nazwa tutaj jest wzięta z kodu, nie z pamięci — jeśli coś się
> rozjedzie, to jest błąd do zgłoszenia. Instrukcja dla użytkownika jest osobno
> (`INSTRUKCJA.md`). Do tego dokumentu istnieje **kontrola porównująca każdą jego
> liczbę i nazwę z kodem**; autor uruchamia ją przed wydaniem, ale **nie jest to
> bramka, która sama zatrzymuje wydanie** — więc traktuj to jako deklarację
> dyscypliny, nie jako gwarancję maszyny. Jeśli któraś liczba się nie zgadza,
> to jest błąd do zgłoszenia.

---

## 1. Co robi wtyczka

Rozdziela pocztę do kategorii według reguł właściciela. Działa w dwóch trybach pracy,
świadomie rozdzielonych:

1. **Na próbce** — właściciel wgrywa CSV (albo używa wbudowanego demo 30 maili),
   przebieg klasyfikuje całą próbkę i wypluwa raport z liczbami: pokrycie, accuracy,
   lista pomyłek. Nic nie wychodzi poza WordPressa.
2. **Na prawdziwej skrzynce (IMAP)** — wtyczka loguje się do skrzynki, czyta
   wiadomości i przenosi je do folderów na serwerze poczty. **Domyślnie na sucho:**
   pokazuje, co by zrobiła, i nie rusza niczego.

Reguły są jedne i te same w obu trybach — ta sama funkcja `segm_klasyfikuj_mail()`.
Trzecia droga to **eksport reguł** do filtrów Gmaila (XML) i reguł Outlooka, dla
właściciela, który woli, żeby segregowała jego własna poczta, a nie WordPress.

Opcjonalnie klasyfikować może model językowy (Anthropic) — wyłącznie na próbce,
wyłącznie po wyraźnej zgodzie i tylko jako porównanie z regułami.

---

## 2. Wymagania środowiska

| Rzecz | Wartość | Skąd |
|---|---|---|
| WordPress | 6.0+ | nagłówek `Requires at least` |
| PHP | 8.1+ | nagłówek `Requires PHP` |
| Rozszerzenia PHP wymagane | `mbstring`, `openssl` (przy szyfrowanym IMAP), funkcja `stream_socket_client` | `segm_skrzynka_diagnostyka()` w `includes/skrzynka.php` |
| Rozszerzenie PHP **imap** | **NIEwymagane** | patrz §10 |
| Biblioteki zewnętrzne | **żadne** — zero composera, zero CDN, zero zewnętrznych fontów | — |
| Usługi zewnętrzne | serwer IMAP właściciela; opcjonalnie `api.anthropic.com` przy trybie AI | `includes/skrzynka.php`, `includes/ai.php` |

Diagnostyka środowiska jest **w panelu**, na górze zakładki *Skrzynka (IMAP)*: pięć
pozycji ze stanem `ok` / `uwaga` / `blad` i wyjaśnieniem po polsku, co zrobić.
Brakującego rozszerzenia wtyczka nie przemilcza i nie pada na nim — mówi o nim wprost.

### Licencja

GPL-2.0-or-later (`License` i `License URI` w nagłówku wtyczki). Pełny tekst licencji
leży w pliku `LICENSE` w katalogu wtyczki i wchodzi do paczki produktowej.

---

## 3. Architektura — plik po pliku

| Plik | Linii | Za co odpowiada |
|---|---|---|
| `segregator-maila.php` | 452 | nagłówek wtyczki, stałe, ładowanie plików, cron retencji, znacznik „operacja w toku", zgoda na AI, ustawienia ogólne |
| `uninstall.php` | 91 | deinstalacja — dwie ścieżki (§11.5) |
| `includes/instalator.php` | 288 | `dbDelta` dla 7 tabel, aktywacja, deaktywacja, seed demo (5 kategorii, 8 reguł, 30 maili) |
| `includes/silnik.php` | 248 | `segm_klasyfikuj_mail()` — jedyna reguła dopasowania w całej wtyczce; przebieg na próbce |
| `includes/csv.php` | 352 | import próbki z CSV (wykrywanie kodowania, limity) i eksport raportu do CSV |
| `includes/ai.php` | 588 | opcjonalna klasyfikacja modelem, licznik stop-loss |
| `includes/admin.php` | 1504 | panel: przebiegi i raporty, reguły, kategorie, próbka, ustawienia, baner powitalny, paski błędu AI i błędu bazy |
| `includes/konfiguracja.php` | 360 | eksport i import własnej konfiguracji (kategorie + reguły) w JSON-ie |
| `includes/filtry.php` | 588 | eksport reguł do filtrów Gmaila (XML) i reguł Outlooka |
| `includes/class-segm-imap.php` | 1123 | **klient IMAP4rev1 w czystym PHP** — gniazdo, TLS, protokół |
| `includes/skrzynka.php` | 534 | konfiguracja skrzynki, hasło, diagnostyka, „Sprawdź połączenie", rozbiór nagłówków i treści |
| `includes/skrzynka-przebieg.php` | 741 | przebieg na żywej skrzynce, historia, cofanie, harmonogramy WP-Cron |
| `includes/admin-skrzynka.php` | 766 | panel zakładki *Skrzynka (IMAP)* i obsługa jej formularzy |
| `includes/admin-powitanie.php` | 143 | ekran powitalny pokazywany raz po aktywacji |
| `includes/admin-probka.php` | 418 | praca na POJEDYNCZYM mailu i licznik trafień reguły |

Poza kodem w paczce jadą: `LICENSE` (pełny tekst GPL-2.0), `readme.txt`
(nagłówek w standardzie WordPressa, opis, instalacja, FAQ, changelog),
`CHANGELOG.md` (historia wersji w formacie Keep a Changelog), `INSTRUKCJA.md`
(instrukcja użytkownika), ten dokument oraz `DIAGRAM-PRZEPLYWU.svg`.

**Co ładuje się kiedy** (`segregator-maila.php`):

- **zawsze** (także poza panelem): `instalator.php`, `silnik.php`, `ai.php`,
  `class-segm-imap.php`, `skrzynka.php`, `skrzynka-przebieg.php`.
  Powód jest konkretny: zadanie cykliczne WP-Cron odpala się z `wp-cron.php`, gdzie
  `is_admin()` jest fałszem. Bez tego cron wywracałby się na „nie ma takiej funkcji".
- **tylko w panelu** (`is_admin()`): `csv.php`, `admin.php`, `admin-probka.php`,
  `admin-skrzynka.php`, `admin-powitanie.php`, `filtry.php`, `konfiguracja.php`.

Front strony nie dostaje **żadnego** kodu wtyczki poza definicjami funkcji — Segregator
nie ma widoku publicznego, skrótów ani zasobów wpinanych w stronę.

---

## 4. Schemat bazy — 7 tabel, prefiks `{wp_prefix}segm_`

Wszystkie tworzy `segm_utworz_tabele()` (`includes/instalator.php`) przez `dbDelta`.

| Tabela | Po co jest | Kto do niej ZAPISUJE |
|---|---|---|
| `segm_kategorie` | kubły, do których trafia poczta | seed demo; panel *Kategorie* |
| `segm_reguly` | reguły „pole zawiera wzorzec → kategoria", kolejność rozstrzyga | seed demo; panel *Reguły* |
| `segm_maile` | **PRÓBKA** maili: nadawca, temat, treść, opcjonalna etykieta oczekiwana | seed demo; import CSV |
| `segm_przebiegi` | raporty przebiegów na próbce; kolumna `wyniki` to JSON z pozycją każdego maila | `segm_wykonaj_przebieg()` |
| `segm_ai_uzycie` | licznik wywołań AI na dzień (stop-loss) | `includes/ai.php` |
| `segm_skrz_przebiegi` | nagłówek przebiegu na **żywej skrzynce**: tryb, źródło, liczniki, znacznik „cofnięty" | `segm_skrzynka_przebieg()` |
| `segm_skrz_ruchy` | pozycje historii: UID, `Message-ID`, nadawca, temat, folder z→do, stan, UID po przeniesieniu, `UIDVALIDITY` folderu docelowego (od schematu 4) | `segm_skrzynka_przebieg()`, `segm_skrzynka_oznacz_ruch()` |

### Które tabele trzymają CUDZE dane osobowe

Trzy: **`segm_maile`** (nadawca, temat, pełna treść), **`segm_przebiegi`** (kolumna
`wyniki` zawiera nadawcę i temat każdego maila) i **`segm_skrz_ruchy`** (nadawca, temat,
`Message-ID`). Wszystkie trzy objęte są tą samą retencją — §11.4.

Tabela `segm_skrz_ruchy` **nie przechowuje treści** wiadomości ze skrzynki. Treść jest
pobierana do pamięci na czas dopasowania reguł i nigdzie nie jest zapisywana.

### Stany w `segm_skrz_ruchy.stan`

`plan` (tryb na sucho: tak by zrobił) · `w_toku` (zamiar zapisany, `UID MOVE` jeszcze
niepotwierdzony — wiersz w tym stanie po zakończonym przebiegu znaczy, że proces urwał się
między przeniesieniem a potwierdzeniem; cofanie szuka takiej wiadomości po `Message-ID`) ·
`przeniesiony` · `pominiety` (żadna reguła nie pasuje albo folder docelowy = źródłowy) ·
`blad` · `cofniety`.

---

## 5. Opcje, metadane i transienty

### `wp_options`

| Nazwa | Zawartość | Autoload |
|---|---|---|
| `segm_ustawienia` | `usun_dane` (domyślnie `0`), `ai_limit_dzienny` (domyślnie `100`), `ai_model` (domyślnie puste = stała wtyczki) | tak |
| `segm_skrzynka` | konfiguracja IMAP **bez hasła** (§9.1) | tak |
| `segm_imap_haslo` | **hasło aplikacji do skrzynki** | **nie** (`add_option( ..., '', false )`) |
| `segm_klucz_api` | klucz API dostawcy modelu (stała `SEGM_KLUCZ_API` ma pierwszeństwo) | **nie** |
| `segm_blad_ai` | ostatni błąd trybu AI: kiedy, kod HTTP, odpowiedź dostawcy, model | **nie** |
| `segm_zgoda_ai` | `kiedy` + `kto` — zgoda na wysyłkę próbki do AI | tak |
| `segm_zgoda_przenoszenie` | `kiedy` + `kto` — zgoda na realne przenoszenie wiadomości | tak |
| `segm_operacja_w_toku` | zamek „coś trwa" z terminem ważności — zajmowany atomowo (`INSERT IGNORE`, nie `add_option()`) | **nie** |
| `segm_wersja_schematu` | wersja schematu bazy (dziś `4`) | tak |
| `segm_powitanie_gotowe` | znacznik „instalacja świeża — pokaż ekran powitalny” | tak |

### Metadane użytkownika

`segm_baner_ukryty` — decyzja „nie pokazuj banera powitalnego", per osoba.

### Dwa powitania i dlaczego oba zostają

Wtyczka ma **dwa** mechanizmy powitania i to jest decyzja, nie przeoczenie:

1. **Ekran powitalny** (`admin-powitanie.php`) — pełna strona, jednorazowe
   przekierowanie **po aktywacji**.
2. **Baner** (`segm_baner_powitalny()` w `admin.php`) — pasek na każdej stronie
   wtyczki, chowany odnośnikiem, znacznik `segm_baner_ukryty`.

Ekran powitalny odzywa się **tylko przy aktywacji**. Instalacja zaktualizowana
podmianą plików (typowa przy aktualizacji z paczki na serwerze) nie przechodzi
przez uchwyt aktywacji, więc tam ekranu nie będzie — i wtedy baner jest jedynym
powitaniem. Żeby jednak nikt nie czytał tego samego przesłania dwa razy pod rząd,
**baner ustępuje ekranowi**: nie pokazuje się użytkownikowi, który ma już
ustawioną metadaną `segm_powitanie_widziane`.

`segm_powitanie_widziane` — numer wersji, przy której ten użytkownik widział już
ekran powitalny. Trzymane **per osoba**, nie globalnie: na stronie z kilkoma
administratorami każdy ma prawo zobaczyć wstęp u siebie i nikt nie ma prawa
oglądać go po raz drugi. Znacznik stawiany jest **przed** przekierowaniem —
zamknięcie karty zamiast kliknięcia „Zaczynam” też liczy się jako „widziane”.

### Transienty

`segm_komunikat_{ID_użytkownika}` — komunikat i zapis rozmowy z serwerem poczty
(wyświetlany przez `admin_notices` z priorytetem **1**, czyli przed paskami wtyczki:
inaczej potwierdzenie zapisu tonęło pod paskami błędu AI i błędu bazy — WK-17)
przenoszony przez przekierowanie, ważny **5 minut**.

`segm_liczenie_{ID_użytkownika}` — wynik „Policz na próbce" wraz z wpisanymi
wartościami formularza, ważny **5 minut**. Wzorzec bywa długi i pełen przecinków,
więc nie przenosimy go adresem URL. Nazwa zależy od użytkownika,
więc deinstalacja kasuje go zapytaniem `LIKE` po `wp_options` (razem z wierszami
`_transient_timeout_*`) — §11.5.

---

## 6. Stałe konfiguracyjne

| Stała | Wartość | Gdzie | Znaczenie |
|---|---|---|---|
| `SEGM_WERSJA` | `1.3.6` | plik główny | wersja wtyczki |
| `SEGM_WERSJA_SCHEMATU` | `4` | plik główny | wyzwala migrację tabel |
| `SEGM_RETENCJA_DNI` | `90` | plik główny | po tylu dniach cron kasuje cudzą korespondencję |
| `SEGM_BLOKADA` | `segm_operacja_w_toku` | plik główny | nazwa znacznika „operacja trwa" |
| `SEGM_BLOKADA_WAZNA` | `120` | plik główny | po tylu sekundach znacznik po padniętym procesie przestaje blokować |
| `SEGM_KUBEL_DOMYSLNY` | `Reszta` | `silnik.php` | kubeł dla maili bez dopasowania |
| `SEGM_IMPORT_MAX_WIERSZY` | `500` | `csv.php` | twardy limit wierszy importu |
| `SEGM_IMPORT_MAX_BAJTOW` | `5242880` (5 MB) | `csv.php` | limit rozmiaru pliku |
| `SEGM_MAX_POLE` | `2000` | `csv.php` | limit długości pojedynczego pola |
| `SEGM_PROBKA_KODOWANIA` | `65536` | `csv.php` | ile bajtów bada wykrywanie kodowania |
| `SEGM_STALA_HASLA` | `SEGM_IMAP_HASLO` | `skrzynka.php` | nazwa stałej z `wp-config.php` mającej pierwszeństwo nad bazą (hasło do skrzynki) |
| `SEGM_STALA_KLUCZA` | `SEGM_KLUCZ_API` | `ai.php` | to samo dla klucza API dostawcy modelu |
| `SEGM_OPCJA_BLAD_AI` | `segm_blad_ai` | `ai.php` | opcja z ostatnim błędem trybu AI |
| `SEGM_SKRZ_TRESC_BAJTOW` | `4096` | `skrzynka.php` | ile bajtów treści maila pobieramy do dopasowania reguł |
| `SEGM_SKRZ_LIMIT_CZASU` | `20` | `skrzynka.php` | twardy limit czasu jednego przebiegu na skrzynce (s) |
| `SEGM_SKRZ_LIMIT_GNIAZDA` | `15` | `skrzynka.php` | limit czasu pojedynczej operacji sieciowej (s) |
| `SEGM_AI_MODEL` | `claude-haiku-4-5` | `ai.php` | model **domyślny**; nadpisuje go ustawienie `ai_model`, a na końcu filtr `segm_ai_model` |
| `SEGM_AI_MODEL_WZORZEC` | `/^[a-z0-9][a-z0-9._-]{2,63}$/` | `ai.php` | dopuszczalny kształt nazwy modelu |
| `SEGM_AI_TIMEOUT` | `9` | `ai.php` | limit czasu wywołania API (s), jeden retry |
| `SEGM_AI_PARTIA` | `30` | `ai.php` | ile maili w jednym wywołaniu |
| `SEGM_AI_MAX_WYWOLAN_PRZEBIEG` | `1` | `ai.php` | ile wywołań na przebieg |
| `SEGM_AI_MAX_PROBKA` | `30` (`PARTIA × WYWOLAN`) | `ai.php` | twardy limit próbki dla trybu AI |
| `SEGM_FILTRY_STRONA` | `segm-filtry` | `filtry.php` | slug strony eksportu filtrów |
| `SEGM_KONFIG_FORMAT` | `1` | `konfiguracja.php` | wersja formatu pliku konfiguracji |
| `SEGM_KONFIG_MAX_BAJTOW` | `524288` (512 kB) | `konfiguracja.php` | limit wielkości wczytywanego pliku |
| `SEGM_KONFIG_MAX_WPISOW` | `500` | `konfiguracja.php` | limit liczby kategorii i reguł z pliku |
| `SEGM_KONFIG_MAX_WZORZEC` | `2000` | `konfiguracja.php` | limit długości wzorca z pliku |
| `SEGM_NA_STRONE` | `50` | `admin.php` | ile wierszy na stronę KAŻDEJ listy w panelu |

Stałe klasy `Segm_Imap`: `KONIEC` (`\r\n`), `DZIENNIK_LINII` (`120`),
`DZIENNIK_ZNAKOW` (`300`).

---

## 7. Punkty wejścia — pełna lista

**Publicznych punktów wejścia NIE MA.** Wtyczka nie rejestruje `wp_ajax_nopriv_*`,
nie rejestruje tras REST, nie wpina się w `template_redirect` ani w `init` na froncie.
Sprawdzalne jednym grepem — patrz kontrola D w skrypcie zgodności.

### 7.1. Strony panelu — 8 sztuk (wszystkie `manage_options`)

| Slug | Strona | Plik |
|---|---|---|
| `segm-przebiegi` | Przebiegi i raporty (także menu główne) | `admin.php` |
| `segm-reguly` | Reguły segregacji | `admin.php` |
| `segm-kategorie` | Kategorie | `admin.php` |
| `segm-probka` | Próbka maili | `admin.php` |
| `segm-ustawienia` | Ustawienia Segregatora | `admin.php` |
| `segm-skrzynka` | Skrzynka (IMAP) | `admin-skrzynka.php` |
| `segm-filtry` | Eksport filtrów | `filtry.php` |
| `segm-powitanie` | ekran powitalny — **zdjęty z menu**, wchodzi się z przekierowania | `admin-powitanie.php` |

### 7.2. Uchwyty `admin-post` — 30 sztuk

Każdy z nich, **bez wyjątku**, sprawdza dwie rzeczy osobno:
`current_user_can( 'manage_options' )` **oraz** `check_admin_referer( ... )`.
Uprawnienie i nonce to dwie różne kontrole — nonce mówi „to żądanie wyszło z naszego
formularza", uprawnienie mówi „ta osoba ma prawo to zrobić".

| Uchwyt | Co robi |
|---|---|
| `segm_przebieg_start` | uruchamia przebieg na próbce |
| `segm_eksport_csv` | eksport raportu przebiegu do CSV |
| `segm_import_csv` | import próbki z pliku |
| `segm_probka_demo` | przywraca próbkę demo |
| `segm_probka_wyczysc` | kasuje próbkę i raporty |
| `segm_mail_zapisz` | dodaje albo poprawia JEDEN mail w próbce |
| `segm_mail_usun` | usuwa JEDEN mail z próbki |
| `segm_regula_policz` | liczy trafienia reguły na próbce **bez zapisywania** |
| `segm_regula_zapisz`, `segm_regula_usun`, `segm_regula_ruch` | edycja reguł i ich kolejności |
| `segm_kategoria_zapisz`, `segm_kategoria_usun` | edycja kategorii |
| `segm_ustawienia_zapisz` | ustawienia ogólne i klucz API |
| `segm_zgoda_zapisz`, `segm_zgoda_cofnij` | zgoda na wysyłkę próbki do AI |
| `segm_test_klucza` | „Przetestuj klucz API” — jedno wywołanie ze słowem „ping” |
| `segm_baner_ukryj` | chowa baner powitalny |
| `segm_blad_bazy_ukryj` | chowa pasek błędu bazy — per użytkownik i tylko TO zgłoszenie (nowy błąd zapala pasek z powrotem) |
| `segm_eksport_filtrow` | pobranie pliku filtrów Gmaila / reguł Outlooka |
| `segm_konfig_eksport` | pobranie WŁASNEJ konfiguracji (kategorie + reguły) w JSON-ie |
| `segm_konfig_import` | wczytanie konfiguracji z pliku — **zastępuje reguły** |
| `segm_skrzynka_zapisz` | zapis konfiguracji IMAP i hasła |
| `segm_skrzynka_test` | „Sprawdź połączenie" |
| `segm_skrzynka_tryb` | przełączenie na sucho / realnie |
| `segm_skrzynka_zgoda`, `segm_skrzynka_zgoda_cofnij` | zgoda na realne przenoszenie |
| `segm_skrzynka_harmonogram` | częstotliwość zadania cyklicznego |
| `segm_skrzynka_uruchom` | „Uruchom teraz" |
| `segm_skrzynka_cofnij` | cofnięcie ostatniego przebiegu |

### 7.3. Zadania cykliczne

| Zaczep | Kiedy | Co robi |
|---|---|---|
| `segm_czyszczenie_probki` | codziennie (`daily`) | retencja 90 dni, §11.4 |
| `segm_skrzynka_cron` | wg wyboru właściciela | przebieg segregacji na skrzynce |

Własne harmonogramy dokłada filtr `cron_schedules` (`segm_skrzynka_harmonogramy()`):
`segm_co_15_minut` (900 s), `segm_co_30_minut` (1800 s), `segm_co_godzine` (3600 s),
`segm_co_6_godzin` (21600 s), `segm_raz_na_dobe` (86400 s). Wszystkie klucze mają
przedrostek `segm_` świadomie: `wp_get_schedules()` scala tak, że **wbudowane
harmonogramy WordPressa wygrywają** przy tym samym kluczu.

---

## 8. Przepływ A — segregacja na PRÓBCE

```
CSV / demo ──► segm_maile
                  │
   „Przepuść próbkę" (nonce + manage_options)
                  ▼
      segm_zajmij_blokade( 'przebieg segregacji' )     ← zajęte? przebieg NIE startuje
                  ▼
      segm_pobierz_reguly()   (ORDER BY kolejnosc, id)
                  ▼
   dla każdego maila: segm_klasyfikuj_mail()
                  ▼
      zapis do segm_przebiegi  ← porażka zapisu = BŁĄD, nie „Zapisano"
                  ▼
   kontrola: czy próbka zmieniła się w trakcie? (liczba wierszy przed/po)
                  ▼
              raport z liczbami
```

**Reguła dopasowania** (`segm_klasyfikuj_mail()`, `includes/silnik.php`) — jedno
miejsce dla obu trybów pracy i dla panelu:

1. pola `od` / `temat` / `tresc` sprowadzone do małych liter (`mb_strtolower`),
2. wzorzec reguły dzielony przecinkami na warianty, każdy przycięty,
3. dopasowanie = **zawieranie** podciągu (`mb_strpos`), nie wyrażenie regularne,
4. **pierwsza pasująca reguła wygrywa** — kolejność jest jedynym mechanizmem
   rozstrzygania, nie ma osobnego pola „priorytet",
5. brak dopasowania → kubeł `Reszta`.

### 8.1. Iteracja na pojedynczym przypadku

Cała próbka nie jest jedyną jednostką pracy. Panel *Próbka maili* pozwala
**dodać, poprawić i usunąć JEDEN mail** (`segm_mail_zapisz`, `segm_mail_usun`) —
import CSV, demo i „wyczyść" nadal działają na całości, ale nie trzeba już
przebudowywać pliku, żeby sprawdzić jeden przypadek. Maile dodane ręcznie mają
`zrodlo = 'reczny'`, więc widać je na liście obok wierszy z importu.

Panel *Reguły* pokazuje przy każdej regule **dwie liczby z bieżącej próbki**
(`segm_policz_trafienia()`):

- **Pasuje** — do ilu maili pasuje sam wzorzec, gdyby ta reguła była jedyna,
- **Zgarnia** — ile maili reguła realnie bierze w przebiegu, po uwzględnieniu,
  że pierwsza pasująca wygrywa.

Różnica między tymi liczbami to najczęstsza przyczyna wrażenia „reguła wygląda
dobrze, a nic nie łapie": zabrała je reguła stojąca wyżej. Przycisk **„Policz na
próbce (bez zapisywania)"** robi to samo dla wzorca jeszcze niezapisanego.

Obie liczby powstają z tej samej funkcji, którą liczy przebieg
(`segm_klasyfikuj_mail()` + `segm_wzorzec_pasuje()`), więc suma kolumny
**Zgarnia** ze wszystkich reguł równa się liczbie `dopasowanych` w raporcie.
Gdyby panel liczył po swojemu, prędzej czy później pokazałby liczbę, której
raport nie potwierdza — a wtedy klient przestaje wierzyć obu.

**Licznik liczy z jednego kontekstu.** Materiał do liczenia — cała próbka i cała
lista reguł — jest dla wszystkich wierszy tabeli IDENTYCZNY, więc pobiera go
`segm_kontekst_trafien()` **raz przed pętlą**, a `segm_policz_trafienia()`
dostaje go gotowego. Wcześniej każdy wiersz czytał jedno i drugie własnym
zapytaniem: przy dwudziestu regułach czterdzieści zapytań na jedno wyświetlenie
strony zamiast dwóch, i rosło to liniowo — najmocniej obrywał właściciel z dużą
liczbą reguł, czyli ten, komu licznik jest najbardziej potrzebny. Kto liczy raz
(przycisk „Policz na próbce"), kontekst pomija i funkcja pobiera go sama.

⛔ Reguła próbna jest podmieniana **na klonie**, nie na obiekcie z kontekstu:
kontekst jest wspólny dla wszystkich wierszy, więc podmiana w miejscu zostałaby
w nim na stałe i każdy następny wiersz liczyłby według cudzego wzorca. Tę samą
tablicę reguł wyświetla zresztą tabela, więc mutacja zmieniłaby też to, co widać
w kolumnie „Warunek".

Sam koszt liczenia (`segm_klasyfikuj_mail()` na każdy pasujący mail, dla każdej
reguły) zostaje bez zmian — to praca w PHP, nie w bazie, i mieści się w pułapie
próbki (500 maili).

**Tryb AI** (opcjonalny) nie zmienia tej reguły: klasyfikuje równolegle i pokazuje
różnice. Nie ruszy bez zapisanej zgody `segm_zgoda_ai`, a przy próbce większej niż
`SEGM_AI_MAX_PROBKA` = 30 maili przebieg **nie startuje** z jawnym komunikatem,
zamiast urwać się w połowie na limicie czasu żądania.

---

### 8.2. Przeprowadzka: eksport i import własnej konfiguracji

`konfiguracja.php` wywozi **kategorie i reguły** do pliku JSON i wczytuje je
z powrotem. To co innego niż eksport filtrów Gmaila: tamten jest jednokierunkowy
i świadomie stratny, ten wraca do Segregatora w całości.

Reguły wiążemy z kategorią **po nazwie, nie po identyfikatorze** — na drugiej
instalacji te same kategorie mają inne `id`, więc wywiezienie `id` dałoby po
imporcie reguły kierujące w nicość. Kategoria wymieniona przez regułę, a
nieobecna na liście, jest **dokładana**, zamiast po cichu wyrzucać regułę.

Wczytanie **zastępuje wszystkie reguły**, a kategorie tylko **uzupełnia** —
istniejąca kategoria nie znika, bo mogą do niej kierować raporty i foldery
na skrzynce.

⛔ **Czego plik NIE zawiera i dlaczego**: hasła do skrzynki i klucza API
(poświadczenia nie jeżdżą w pliku, który ląduje w katalogu pobranych),
próbki maili ani historii (to cudza korespondencja objęta retencją — wywożenie
jej plikiem obchodziłoby tę retencję), zapisanych zgód (decyzja na jednej
instalacji nie jest decyzją na drugiej; po imporcie trzeba je podjąć od nowa).

Plik przychodzi z zewnątrz, więc jest traktowany jak dane od nieznajomego:
sprawdzany znacznik wtyczki i wersja formatu, limit wielkości pliku, limit
liczby wpisów, sanityzacja i przycięcie każdej wartości, odrzucenie reguł
z nieznanym polem.

### 8.3. Stronicowanie list

Trzy listy w panelu potrafią urosnąć: przebiegi na próbce, sama próbka
(do 500 wierszy z importu) i historia segregacji skrzynki. Wszystkie trzy
stronicuje jeden mechanizm — `segm_pasek_stron()` w `admin.php`, po
`SEGM_NA_STRONE` = 50 wierszy, numer strony w parametrze `segm_strona`.

Świadomie **własny parametr, nie `paged`**: WordPress używa `paged` do własnych
list i podpinanie się pod niego na stronie wtyczki bywa źródłem przekręconych
przekierowań. Numer spoza zakresu jest **przycinany do istniejącej strony**,
więc `?segm_strona=999` pokazuje ostatnią stronę, a nie pustą tabelę.

Pasek podaje zakres i sumę („pozycje 51–100 z 260 (strona 2 z 6)”), bo sama
nawigacja bez liczb nie mówi właścicielowi, ile danych w ogóle ma.

Numer strony **jedzie dalej w odnośnikach**: „Zobacz raport", „Szczegóły"
i „Edytuj" doklejają `segm_strona`, a powrotne „← wróć" i „Anuluj edycję"
odczytują go z adresu przez `segm_param_strony()`. Bez tego wejście w szczegóły wpisu ze strony 4 i powrót
zrzucały właściciela na stronę 1 — czyli dokładnie ten problem, który
stronicowanie miało usunąć, wracał tylnymi drzwiami. Pierwsza strona nie dokleja
niczego, żeby adres został czysty.

## 9. Przepływ B — segregacja na PRAWDZIWEJ SKRZYNCE

```
       „Uruchom teraz" / WP-Cron
                  ▼
      segm_zajmij_blokade( 'segregacja skrzynki' )
                  ▼
      segm_skrzynka_polacz()   ── gniazdo → TLS → LOGIN → CAPABILITY
                  ▼
   ┌──────────────────────────────────────────────┐
   │  TRYB?  segm_przenoszenie_dozwolone()        │
   │  = tryb 'na_zywo'  I  zapisana zgoda         │
   └──────────────────────────────────────────────┘
        na sucho │                    │ realnie
     EXAMINE (READ-ONLY)         SELECT (do zapisu)
                  ▼                    ▼
            UID SEARCH ALL | UNSEEN  → najnowsze N wiadomości
                  ▼
      INSERT segm_skrz_przebiegi (wiersz przebiegu PRZED pierwszym ruchem;
                  ▼               porażka = przebieg nie startuje, nic nie ruszone)
   dla każdej: UID FETCH BODY.PEEK[HEADER] (+ BODY.PEEK[TEXT]<0.4096>)
               → segm_klasyfikuj_mail() → folder docelowy
                  ▼                    ▼
      INSERT ruch 'plan'        INSERT ruch 'w_toku'  (ZAMIAR przed ruchem;
      (nic się nie dzieje)             ▼              porażka = wiadomość NIE ruszona,
                                CREATE (gdy brak) → UID MOVE   przebieg przerwany z błędem)
                                       ▼
                                COPYUID pusty? → ruch 'blad' (nic nie przeniesiono)
                                COPYUID pełny → UPDATE ruch 'przeniesiony'
                                                + uid_docelowy + uidvalidity_do
                  ▼
      UPDATE segm_skrz_przebiegi (liczniki, komunikat)
                  ▼
              segm_zwolnij_blokade()
```

Wiersz przebiegu dostaje na starcie komunikat `SEGM_SKRZ_KOMUNIKAT_W_TOKU`, który mówi
wprost, że **przebieg nie doszedł do końca**, gdyby ten tekst został w bazie na stałe
(proces przerwany albo końcowe podsumowanie też odrzucone przez bazę). Właściciel czyta
go w historii i w widoku przebiegu, zamiast oglądać wiecznie „w toku".

Kolejność „najpierw baza, potem serwer" jest celowa: historia jest jedynym źródłem, z którego
da się cofnąć przebieg, więc powstaje ZANIM cokolwiek ruszy. Porażka zapisu = błąd przebiegu
(pole `blad`, panel pokazuje czerwony komunikat), nie sukces z pustą historią; `przeniesionych`
liczy wyłącznie ruchy potwierdzone przez serwer.

### 9.1. Konfiguracja skrzynki (`segm_skrzynka_ustawienia()`)

| Klucz | Domyślnie | Znaczenie |
|---|---|---|
| `host` | `''` | serwer IMAP |
| `port` | `993` | port |
| `szyfrowanie` | `ssl` | `ssl` / `starttls` / `brak` |
| `login` | `''` | login (zwykle adres e-mail) |
| `folder_zrodlowy` | `INBOX` | skąd bierzemy wiadomości |
| `prefiks` | `Segregator` | folder nadrzędny na kategorie |
| `limit` | `50` | ile **najnowszych** wiadomości na przebieg |
| `dopasuj_tresc` | `1` | czy pobierać początek treści do reguł „treść zawiera" |
| `tylko_nieprzeczytane` | `0` | `UID SEARCH UNSEEN` zamiast `ALL` |
| `ruszaj_reszte` | `0` | czy przenosić maile bez dopasowania do kubła `Reszta` |
| `ufaj_bez_cert` | `0` | **wyłącza weryfikację certyfikatu** — tylko serwery testowe |
| `tryb` | **`na_sucho`** | tryb pracy |
| `czestotliwosc` | `wylaczone` | harmonogram |

**Hasła nie ma na tej liście świadomie** — siedzi w osobnej opcji bez autoload albo
w `wp-config.php`.

### 9.2. Tryb na sucho — gwarancja protokołu, nie deklaracja

W trybie na sucho folder otwierany jest poleceniem **`EXAMINE`** (RFC 3501 §6.3.2),
czyli read-only **po stronie serwera**: serwer odrzuciłby próbę zmiany, nawet gdyby
wtyczka ją wysłała. Serwer potwierdza to w odpowiedzi znacznikiem `[READ-ONLY]`.

Realne przenoszenie wymaga **dwóch niezależnych rzeczy naraz**
(`segm_przenoszenie_dozwolone()`): ustawionego trybu `na_zywo` **oraz** zapisanej
opcji `segm_zgoda_przenoszenie`. Samo potwierdzenie niczego nie włącza. Ustawiony tryb
`na_zywo` bez potwierdzenia = przebieg idzie na sucho i **mówi to wprost** w komunikacie.

### 9.3. Hasło do skrzynki

- **Wymagane jest hasło aplikacji**, nie główne hasło konta — napisane wprost
  w panelu i w instrukcji.
- Stała **`SEGM_IMAP_HASLO`** w `wp-config.php` ma **pierwszeństwo** nad bazą
  (`segm_skrzynka_haslo_ze_stalej()`). Gdy jest ustawiona, pole w panelu jest
  zablokowane, a `segm_skrzynka_zapisz_haslo()` wychodzi bez zapisu — żeby formularz
  nie wygrywał po cichu ze stałą.
- W bazie hasło leży w opcji `segm_imap_haslo` z **wymuszonym `autoload = no`**.
- Hasło **nigdy nie wraca do formularza** — można je tylko podmienić albo usunąć.
- W dzienniku rozmowy z serwerem, pokazywanym w panelu, polecenie `LOGIN` ma
  zamaskowany argument: `LOGIN "login" ****`.
- **Świadomie NIE szyfrujemy hasła w bazie**: klucz szyfrujący leżałby w tym samym
  WordPressie, obok danych. To byłby teatr, nie zabezpieczenie. Właściwa odpowiedź to
  stała w `wp-config.php`, i tak jest napisane w panelu.
- Deinstalacja kasuje hasło **ZAWSZE**, obiema ścieżkami (§11.5).

### 9.4. Cofanie przebiegu

`segm_skrzynka_cofnij()` przenosi wiadomości z powrotem do folderu, z którego wyszły.

- Cofnąć wolno **wyłącznie ostatni** nie-cofnięty przebieg. Decyduje **stan bazy**
  (`segm_skrzynka_ostatni_do_cofniecia()`), nie identyfikator przysłany w żądaniu —
  między wyświetleniem strony a kliknięciem cron mógł dorzucić nowszy przebieg.
- Nowy UID wiadomości bierzemy z kodu odpowiedzi `COPYUID` (RFC 4315). Gdy serwer go
  nie zwróci, szukamy wiadomości po nagłówku `Message-ID`.
- **UIDVALIDITY** (RFC 3501 §2.3.1.1): przy przenoszeniu zapisujemy `UIDVALIDITY` folderu
  docelowego (z `COPYUID`, a bez niego ze `STATUS`). Przy cofaniu porównujemy ją z bieżącą
  — gdy się różni (folder skasowany i założony na nowo), zapamiętanemu UID-owi nie ufamy
  i szukamy po `Message-ID`; inaczej „cofnęlibyśmy" obcą wiadomość o tym samym numerze.
- Pozycje `w_toku` (przebieg urwał się między `UID MOVE` a potwierdzeniem) cofamy po
  `Message-ID`; gdy wiadomości nie ma w folderze docelowym, pozycja dostaje wyjaśnienie.
- **Liczymy skutek, nie status** (RFC 3501 §6.4.8: `UID MOVE` nieistniejącego UID-a kończy
  się `OK`): `Segm_Imap::przenies()` uznaje ruch za udany tylko przy niepustym `COPYUID`,
  a na serwerze bez UIDPLUS — po porównaniu stanu folderu przed i po. Wiadomość, którą
  właściciel w międzyczasie przeciągnął gdzie indziej, dostaje BŁĄD przy swojej pozycji,
  `cofnietych` jej nie liczy.
- Gdy którakolwiek pozycja zawiedzie, pozycja historii dostaje wyjaśnienie „sprawdź ją
  ręcznie", a przebieg **nie** jest oznaczany jako cofnięty w całości — przycisk zostaje
  i drugą próbę da się zrobić.

---

## 10. Klient IMAP — co jest zaimplementowane i dlaczego własny

Rozszerzenie PHP `imap` zostało **wyjęte z PHP i przeniesione do PECL**. Podręcznik
PHP (`php.net/manual/en/imap.installation.php`, sekcja „PHP 8.4") pisze wprost:

> This extension has been moved to the PECL repository and is no longer bundled with
> PHP as of PHP 8.4.0

Wtyczka oparta na funkcjach `imap_*` przestawałaby działać klientom po aktualizacji
PHP, a na hostingu współdzielonym nikt tego rozszerzenia nie doinstaluje. Dlatego
`includes/class-segm-imap.php` to własny klient: gniazdo TCP (`stream_socket_client`),
TLS (`stream_socket_enable_crypto`, wyłącznie TLS 1.2 i 1.3) i protokół czytany
z dokumentów źródłowych:

| Dokument | Co z niego wzięte |
|---|---|
| RFC 3501 (IMAP4rev1) | literały §4.3, nazwy folderów w zmodyfikowanym UTF-7 §5.1.3, `LOGIN` §6.2.3, `SELECT`/`EXAMINE` §6.3.1–6.3.2, `CREATE` §6.3.3, `LIST` §6.3.8, `SEARCH` §6.4.4, `FETCH` i `BODY.PEEK` §6.4.5, `COPY` §6.4.7, `UID` §6.4.8 |
| RFC 6851 | `MOVE` / `UID MOVE` |
| RFC 4315 (UIDPLUS) | `COPYUID`, `UID EXPUNGE` |
| RFC 2047 | dekodowanie nagłówków `=?UTF-8?B?…?=` (przez `iconv_mime_decode`) |

**Przenoszenie ma drogę główną i zapasową.** Główna: `UID MOVE` (jedna operacja, bez
stanów pośrednich). Serwer bez `MOVE`: `UID COPY` → `UID STORE +FLAGS.SILENT (\Deleted)`
→ `UID EXPUNGE`. Serwer bez `UIDPLUS` **nie dostaje zwykłego `EXPUNGE`** — ono
skasowałoby też wiadomości oznaczone do usunięcia przez inny program pocztowy
(RFC 4315 §2.1 ostrzega o tym wprost). W takim wypadku oryginał zostaje oznaczony do
usunięcia, a pozycja historii dostaje wyjaśnienie, że sprzątnie go klient poczty.

**Odczyt nie zmienia stanu skrzynki**: pobieramy `BODY.PEEK`, które z definicji
(RFC 3501 §6.4.5) nie ustawia znacznika `\Seen`.

**Czego klient NIE robi**: nie parsuje struktury MIME, nie pobiera załączników, nie
obsługuje `AUTHENTICATE` ani OAuth2 (`XOAUTH2`), nie obsługuje `IDLE`.

### Ograniczenie sprawdzone na żywych serwerach

`outlook.office365.com:993` ogłasza `LOGINDISABLED` i wyłącznie `AUTH=XOAUTH2` —
**Microsoft 365 i Outlook.com nie zadziałają** i hasło aplikacji tego nie naprawi.
Wtyczka wykrywa `LOGINDISABLED` przed próbą logowania i tłumaczy po polsku obie możliwe
przyczyny. Gmail (`imap.gmail.com:993`) działa z hasłem aplikacji.

---

### 10.1. Wybór modelu AI

Model bierze się z trzech miejsc, w tej kolejności ważności:

1. **filtr `segm_ai_model`** — nakłada się na wszystko, ostatnie słowo należy do
   programisty (np. własne proxy),
2. **ustawienie `ai_model`** z panelu (Ustawienia → Model AI): lista podpowiedzi
   albo własna nazwa wpisana ręcznie,
3. **stała `SEGM_AI_MODEL`**, gdy ustawienie jest puste.

Domyślną wartością jest **alias bez daty** (`claude-haiku-4-5`), a nie migawka
z datą: migawki bywają wycofywane, a bez serwera aktualizacji każda sprzedana
kopia straciłaby tryb AI tego samego dnia i jedyną naprawą byłaby ręczna wysyłka
zipa mailem. Ten sam wybór co `CHATL_AI_MODEL` w ChatBocie.

Nazwa musi pasować do `SEGM_AI_MODEL_WZORZEC`; nazwa z literówką (spacja,
znaczniki, za krótka) jest **odrzucana z zachowaniem poprzedniej**, żeby pomyłka
w formularzu nie wyłączyła po cichu trybu AI przy następnym przebiegu.

⚠️ Wtyczka **nie sprawdza, czy model istnieje** — tego nie da się wiedzieć bez
zapytania dostawcy, a lista modeli zmienia się szybciej niż wtyczka. Zła nazwa
kończy się błędem API, a wtedy przebieg zjeżdża do trybu regułowego i mówi
o tym w raporcie. Lista w panelu to **podpowiedzi**, nie gwarancja dostępności
na koncie właściciela.

### 10.2. Klucz API i sygnał o jego odrzuceniu

**Gdzie mieszka klucz.** Stała `SEGM_KLUCZ_API` w `wp-config.php` ma
**pierwszeństwo** nad opcją w bazie — dokładnie jak `CHATL_KLUCZ_API`
w ChatBocie i `SEGM_IMAP_HASLO` przy skrzynce. Gdy stała jest ustawiona, pole
w panelu jest zablokowane, a `segm_zapisz_klucz()` wychodzi bez zapisu: wpis
w formularzu nie może po cichu wygrać ze stałą wbrew deklaracji w panelu.
Kupujący oba boty ma dostać JEDNĄ klasę ochrony sekretu, a nie dwie różne
zależnie od tego, którego bota otworzy.

**Skąd kod wie, że klucz się zapisał.** `segm_zapisz_klucz()` zwraca `bool`
zmierzony **odczytem po zapisie** (ten sam wzorzec co `segm_skrzynka_zapisz_haslo()`),
a handler ustawień przy `false` kończy się komunikatem o NIEZAPISANYM kluczu,
nie „Zapisano" — inaczej „Przetestuj klucz" testowałby stary klucz i dawał
zielone potwierdzenie klucza, którego nie ma w bazie.

**Dlaczego cichy zjazd do reguł to za mało.** Przy błędzie API przebieg
zjeżdża do trybu regułowego i kończy się wynikiem, nie ekranem błędu — i tak
ma zostać. Ale cisza nie może trwać w nieskończoność: bez sygnału wtyczka
miesiącami „działa w trybie AI”, licząc wyłącznie regułami, bo dostawca odrzuca
każde żądanie. Dlatego każde nieudane wywołanie zapisuje opcję `segm_blad_ai`
(kiedy, kod HTTP, odpowiedź dostawcy, model), a ta zapala **pasek na każdej
stronie wtyczki**. Pasek gaśnie **sam** przy pierwszym udanym wywołaniu —
nie trzeba go zamykać. Zapis tego znacznika jest mierzony (`segm_zapisz_opcje()`);
gdy sam padnie, zostawia ślad w `segm_blad_bazy` (pasek błędu bazy) — znacznik
awarii, który pada po cichu, przywracałby dokładnie tę ciszę, przed którą broni.

Kod HTTP jest tłumaczony na zdanie po polsku (`segm_opis_bledu_ai()`), bo samo
„401” nie jest informacją dla właściciela. Opisy rozdzielają przyczyny po jego
stronie (odrzucony klucz, zła nazwa modelu, brak uprawnień) od awarii
u dostawcy — żeby nie szukał błędu u siebie, gdy go tam nie ma.

**Co dokładnie opuszcza WordPressa w trybie AI** (A3-3 — deklaracja ma to mówić
wprost, bo właściciel pisze z niej własną politykę prywatności): nadawca (190
znaków), temat (300), treść maila z próbki (pierwsze 800) i identyfikator wiersza
próbki — dla każdego maila w partii do `SEGM_AI_PARTIA`; do tego **nazwy
wszystkich kategorii** właściciela w poleceniu systemowym, nazwa modelu i limit
`max_tokens`. Do tego **nagłówki**: `x-api-key`, `anthropic-version`, `Content-Type` oraz
`user-agent`, którego wtyczka NIE nadpisuje — WordPress wstawia tam własny
(`WordPress/<wersja>; <adres strony>`, filtr `http_headers_useragent`), więc
adres strony właściciela i wersja WP wychodzą przy KAŻDYM żądaniu, także przy
teście klucza. Nie wychodzą: załączniki, nagłówki techniczne maila,
`Message-ID`, kolumna `kategoria_oczekiwana`; adresu IP nie umieszczamy
w treści żądania (z samego połączenia dostawca widzi go tak jak przy każdym
połączeniu — deklaracja mówi to wprost, zamiast obiecywać anonimowość). Przy błędzie żądanie jest **ponawiane
raz** — te same dane lecą drugi raz. **Przebieg na prawdziwej skrzynce (IMAP)
nie woła AI w ogóle**: `skrzynka-przebieg.php` i `skrzynka.php` nie mają ani
jednego wywołania `segm_ai_*`, tryb AI dotyczy wyłącznie próbki. Pilnuje tego strażnik
w repozytorium, który rozkłada PRAWDZIWY przechwycony ładunek (ciało **i**
nagłówki) i dla każdego składnika pyta, czy deklaracja go wymienia — czytając
dokument i pliki źródłowe **z repozytorium**, nie z kopii wtyczki na stosie.
Fakt „na skrzynce nie ma AI" asertuje deterministycznie: zero wywołań
`segm_ai_*` w `skrzynka-przebieg.php` i `skrzynka.php`; sam przebieg na
nieskonfigurowanej skrzynce niczego by nie dowiódł, bo odbija się na wejściu.

**„Przetestuj klucz API”** (Ustawienia) wysyła JEDNO wywołanie ze słowem
„ping”. ⛔ Żadnego maila, żadnej treści z próbki — to test poświadczeń, nie
klasyfikacja, więc nie wymaga zgody na wysyłkę próbki do AI i celowo jej nie
sprawdza.

Z tego samego powodu test **nie zabiera jednostki z dziennego stop-lossu**
(`ai_limit_dzienny`) i **nie jest nim blokowany**: ten limit chroni budżet na
klasyfikację, a sprawdzenie własnych poświadczeń nie jest klasyfikacją.
Właściciel z wyczerpanym limitem musi móc sprawdzić, czy klucz w ogóle działa —
inaczej diagnostyka wyłączałaby się dokładnie wtedy, gdy jest potrzebna.
Technicznie: `segm_ai_zapytaj()` ma trzeci parametr `$licz_do_limitu`, a licznik
rośnie tylko dla wywołań, które naprawdę klasyfikują.

## 11. Cykl życia

### 11.1. Aktywacja — `segm_aktywacja()` (`includes/instalator.php`)

1. `segm_utworz_tabele()` — `dbDelta` dla 7 tabel,
2. zapis `segm_wersja_schematu` = `3`,
3. `segm_seed_demo()` — **tylko do pustej bazy**: 5 kategorii, 8 reguł, 30 maili demo
   z etykietami. Gdy zapis kategorii padnie, seed **przerywa się**, zamiast wstawiać
   reguły z `kategoria_id = 0`.
4. rejestracja codziennego crona retencji,
5. zapalenie znacznika `segm_powitanie_gotowe` (zapis mierzony jak reszta —
   porażka zostawia ślad w `segm_blad_bazy`, bo inaczej brak ekranu powitalnego
   wyglądałby jak „wtyczka się nie włączyła"). Samo przekierowanie robi
   `admin_init`, nie uchwyt aktywacji — z uchwytu aktywacji nie wolno
   przekierowywać, bo WordPress jest w środku własnej procedury.
   Przekierowanie **nie odpala się przy włączaniu wtyczek hurtem**
   (`activate-multi`), żeby nie wyrzucić nikogo z listy w środku operacji.

### 11.2. Migracja schematu — `segm_sprawdz_schemat()`

Na `plugins_loaded`: gdy `segm_wersja_schematu` w bazie różni się od stałej, leci
`dbDelta` i zapis nowej wersji. Dzięki temu instalacja zaktualizowana **przez podmianę
plików** (bez ponownej aktywacji) też dostaje nowe tabele.

Ta sama furtka dotyczy crona: `segm_zapewnij_cron()` dopina harmonogram retencji, gdy
go nie ma — inaczej retencja nie ruszyłaby u nikogo, kto ma wtyczkę stale włączoną.

### 11.3. Deaktywacja — `segm_deaktywacja()`

Zdejmuje **oba** harmonogramy: `segm_czyszczenie_probki` i `segm_skrzynka_cron`.
Dane zostają — właściciel może włączyć wtyczkę z powrotem bez strat. Wyłączona wtyczka
nie ma prawa dalej chodzić na cudzy serwer poczty, stąd zdjęcie drugiego crona.

### 11.4. Cron retencji — `segm_czyszczenie_probki()`

Codziennie kasuje wiersze starsze niż **`SEGM_RETENCJA_DNI` = 90 dni** z **czterech**
tabel: `segm_maile`, `segm_przebiegi`, `segm_skrz_ruchy`, `segm_skrz_przebiegi`.
Granicę liczymy w czasie **lokalnym strony** (`gmt_offset`), bo w tej samej skali
zapisywane są wiersze — inaczej przy strefie +02:00 kasowalibyśmy dwie godziny za
wcześnie. Gdy trwa inna operacja na danych, retencja **odpuszcza tę dobę**, zamiast
wchodzić w środek przebiegu.

### 11.5. Deinstalacja — dwie ścieżki (`uninstall.php`)

**Kasowane ZAWSZE, niezależnie od ustawień:**

- harmonogramy `segm_czyszczenie_probki` i `segm_skrzynka_cron`,
- znacznik `segm_operacja_w_toku` (stan chwilowy, nie dane właściciela),
- stan ekranu powitalnego: opcja `segm_powitanie_gotowe` i metadana
  `segm_powitanie_widziane` **wszystkich** użytkowników — to stan interfejsu,
  nie dane właściciela, a zostawiona metadana byłaby sierotą po wtyczce,
  której już nie ma,
- transienty `segm_komunikat_*` wraz z wierszami `_transient_timeout_*`,
- **`segm_klucz_api`** i **`segm_imap_haslo`** — poświadczenia to nie są „dane
  użytkowe": martwa wtyczka nie zostawia hasła do cudzej skrzynki w bazie ani w jej
  kopii zapasowej,
- **`segm_zgoda_ai`** i **`segm_zgoda_przenoszenie`** — po ponownej instalacji
  właściciel ma podjąć te decyzje świadomie od nowa, a nie odziedziczyć potwierdzenie
  sprzed odinstalowania. Wtyczka wraca do trybu na sucho.

**Kasowane TYLKO przy zaznaczonej opcji „usuń wszystkie dane"** (`usun_dane`,
domyślnie wyłączonej): 7 tabel, `segm_ustawienia`, `segm_skrzynka`,
`segm_wersja_schematu` i metadana `segm_baner_ukryty` wszystkich użytkowników.

---

## 12. Bezpieczeństwo — co jest sprawdzane i gdzie

| Rzecz | Jak | Gdzie |
|---|---|---|
| Uprawnienia | `current_user_can( 'manage_options' )` w **każdym** z 23 uchwytów | `admin.php`, `admin-skrzynka.php`, `filtry.php` |
| Nonce | `check_admin_referer()` w **każdym** z 23 uchwytów, osobno od uprawnień | jw. |
| SQL | wyłącznie `$wpdb->prepare()` / `$wpdb->insert()` z listą formatów; `esc_like()` przy `LIKE` | całość |
| Wynik zapisu | każde `insert`/`update`/`query` porównane z `false`; opcje przez `segm_zapisz_opcje()` (odczyt po zapisie); porażka = komunikat błędu, nigdy „Zapisano" | całość |
| Puste pole liczbowe | `segm_liczba_z_pola()`: pole puste, nie-liczba albo poza zakresem NIE nadpisuje poprzedniej wartości (port, limit wiadomości, dzienny limit AI) — właściciel dostaje komunikat ostrzegawczy zamiast „Zapisano" | plik główny, `admin.php`, `admin-skrzynka.php` |
| Wyjście | `esc_html` / `esc_attr` / `esc_url`; dziennik protokołu przez `esc_html` w `<pre>` | panel |
| Wstrzyknięcie formuły do arkusza | pola zaczynające się od `=`, `+`, `-`, `@` poprzedzane apostrofem przy eksporcie CSV | `csv.php` |
| Import pliku | limit rozmiaru (5 MB), limit wierszy (500, **z przerwaniem czytania**), limit długości pola (2000), wykrywanie kodowania | `csv.php` |
| Wyścig operacji | zamek `segm_operacja_w_toku` zajmowany atomowo (`INSERT IGNORE` po kluczu unikalnym `option_name`, wynik = liczba wierszy), odczyt stanu prosto z bazy z pominięciem cache opcji, termin ważności 120 s | plik główny |
| Certyfikat TLS | `verify_peer` i `verify_peer_name` **włączone domyślnie**; wyłączenie to świadomy przełącznik z ostrzeżeniem | `class-segm-imap.php` |
| Poświadczenia w logach | `LOGIN` w dzienniku ma argument zamaskowany `****`; hasło nie trafia do HTML panelu | `class-segm-imap.php` |
| Zakaz `error_log()` | logowanie przez `wp_trigger_error()` (respektuje `WP_DEBUG`) | `segm_log_bledu_bazy()` |

Zamek `segm_operacja_w_toku` jest **atomowy na poziomie bazy**: zajęcie to jeden
`INSERT IGNORE` do `wp_options` (klucz unikalny `option_name`) i liczy się liczba wierszy
(1 = nasz, 0 = ktoś był szybszy, błąd bazy = odmowa); stan czytamy prosto z bazy, bo cache
opcji procesu nie widzi wpisu zrobionego sekundę temu przez inny proces. `add_option()`
do tego się nie nadaje — sprawdza istnienie w pamięci procesu i robi
`INSERT … ON DUPLICATE KEY UPDATE`, który nadpisuje cudzy wiersz i melduje sukces.
Znacznik po procesie, który padł (`do` w przeszłości) albo w obcym kształcie (bez pola
`do`) jest przejmowany — kasowany **po wartości**, nie po nazwie, żeby nie zdjąć zamka,
który w międzyczasie zajął kto inny. Tak samo **zwolnienie**: `segm_zwolnij_blokade()`
kasuje wyłącznie stempel, który sam wstawił (zapamiętany na czas żądania) — proces,
który przekroczył `SEGM_BLOKADA_WAZNA` i którego zamek ktoś przejął, nie zdejmie na
wyjściu cudzego, świeżego znacznika. Dodatkowo przebieg na próbce sprawdza na końcu,
czy liczba wierszy się nie zmieniła.

---

## 13. Granice — czego ta wtyczka NIE robi

- **Nie odpowiada na maile i nic nie wysyła.** Czyta i przenosi, koniec.
- **Nie kasuje wiadomości.** Jedyny wyjątek to droga zapasowa przenoszenia na serwerze
  bez `MOVE`, gdzie oryginał jest oznaczany `\Deleted` po tym, jak kopia jest już
  w folderze docelowym.
- **Nie obsługuje OAuth2** — a więc nie obsłuży Microsoft 365 ani Outlook.com (§10).
- **Nie nadaje etykiet Gmaila** — przenosi między folderami; w Gmailu folder to
  etykieta, więc skutek jest zbliżony, ale nie identyczny.
- **Nie robi pełnego rozbioru MIME.** Do reguł „treść zawiera" bierzemy pierwsze
  4096 bajtów sekcji `TEXT` i rozkodowujemy base64 / quoted-printable dla wiadomości
  jednoczęściowych. Przy wiadomości wieloczęściowej w tekście zostaną granice części.
- **Nie pobiera załączników.**
- **Nie ma widoku publicznego** — żadnych skrótów, żadnych tras REST, żadnych zasobów
  na froncie.
- **Nie synchronizuje w obie strony.** Wiadomość przeniesiona ręcznie przez właściciela
  nie wraca do skrzynki odbiorczej.
- **Nie da się cofnąć więcej niż jednego przebiegu.** Cofnięcie dotyczy wyłącznie
  ostatniego nie-cofniętego przebiegu.
- **Reguły to zawieranie podciągu, nie wyrażenia regularne** i nie ma w nich wykluczeń
  („zawiera X, ale nie Y").
- **Tryb AI działa wyłącznie na próbce**, nigdy na żywej skrzynce.

---

## 14. ⭐ MAPA DLA AUDYTORA — gdzie patrzeć najuważniej

> **Czym ta sekcja JEST, a czym NIE JEST.** Wskazuje miejsca, w których w systemach tej
> klasy **historycznie powstają błędy**, i podaje pytanie, które warto tam zadać.
> **Nie twierdzi, że w tym kodzie te błędy są** — to mapa terenu do sprawdzenia, nie
> lista znalezisk. Autor nie jest bezstronny wobec własnego kodu i dlatego pisze, gdzie
> szukać, zamiast zapewniać, że jest dobrze.

| # | Miejsce | Plik | Pytanie, które warto zadać |
|---|---|---|---|
| 1 | przerwanie połączenia w połowie przenoszenia | `skrzynka-przebieg.php`, `class-segm-imap.php::przenies()` | co zostaje w bazie i na serwerze, gdy sieć padnie **między** `UID COPY` a `UID EXPUNGE`? Czy wiadomość może zniknąć z obu folderów naraz, czy tylko zdublować się? |
| 2 | odzyskanie poczty po błędnym przebiegu | `segm_skrzynka_cofnij()` | czy da się odzyskać maile, gdy serwer nie zwrócił `COPYUID` **i** wiadomość nie ma `Message-ID`? Wiadomość ruszona ręcznie przez właściciela dostaje błąd przy pozycji (pilnuje tego strażnik w repozytorium) — a co z serwerem bez UIDPLUS, gdzie skutek mierzymy stanem folderu? |
| 3 | hasło do cudzej skrzynki | `skrzynka.php`, `class-segm-imap.php::zapisz_dziennik()` | czy hasło wycieka gdziekolwiek: do dziennika protokołu, do HTML panelu, do logu PHP przy błędzie, do kopii zapasowej bazy, do transientu z komunikatem? |
| 4 | „na sucho" jako obietnica | `segm_skrzynka_przebieg()` | czy istnieje **jakakolwiek** ścieżka, którą przebieg na sucho wysyła polecenie zmieniające stan skrzynki? Czy `EXAMINE` na pewno jest użyte przy każdym wejściu? |
| 5 | dwa warunki realnego przenoszenia | `segm_przenoszenie_dozwolone()` | czy da się przenosić realnie, mając tylko jeden z dwóch warunków? Co po ręcznej edycji opcji w bazie? |
| 6 | wyścig dwóch przebiegów | `segm_zajmij_blokade()` | co robi cron odpalony w środku przebiegu ręcznego? Zamek jest atomowy (`INSERT IGNORE`, pilnuje tego strażnik w repozytorium) — ale co, gdy przebieg trwa dłużej niż 120 s i zamek zostaje przejęty w trakcie? |
| 7 | limit czasu przebiegu | `SEGM_SKRZ_LIMIT_CZASU` | co się dzieje z wiadomościami, których przebieg nie zdążył ruszyć? Czy następny przebieg weźmie je poprawnie, czy pominie? |
| 8 | weryfikacja certyfikatu | `class-segm-imap.php::polacz()` | czy `ufaj_bez_cert` da się włączyć niechcący? Czy przy wyłączonej weryfikacji ostrzeżenie jest widoczne w miejscu decyzji, czy tylko w instrukcji? |
| 9 | nazwy folderów z polskimi znakami | `Segm_Imap::do_utf7()` | co się stanie, gdy `mbstring` nie zna `UTF7-IMAP`? Czy powstanie folder o przekręconej nazwie, czy operacja padnie z komunikatem? |
| 10 | dopasowanie na treści | `segm_skrzynka_tresc_tekstem()` | czy przycięcie do 4096 bajtów i brak rozbioru MIME mogą dać **fałszywe trafienie** (np. słowo z nagłówka podczęści)? Czy właściciel wie, że tak może być? |
| 11 | retencja RODO | `segm_czyszczenie_probki()` | czy po 90 dniach naprawdę znika **cała** cudza korespondencja ze wszystkich czterech tabel? Czy `wyniki` w `segm_przebiegi` na pewno są kasowane razem z próbką? |
| 12 | deinstalacja | `uninstall.php` | czy diff bazy przed instalacją i po odinstalowaniu jest pusty **obiema ścieżkami**? Czy zostają sieroty: transienty, `_transient_timeout_*`, metadane użytkowników? |
| 13 | zgoda na wysyłkę do AI | `segm_zgoda_ai()` | czy istnieje ścieżka, którą treść cudzych maili wychodzi do dostawcy modelu **bez** zapisanej zgody? Co przy błędzie zapisu zgody? |
| 14 | eksport filtrów vs silnik | `filtry.php` vs `silnik.php` | nasz silnik zatrzymuje się na pierwszej pasującej regule, a Gmail stosuje **wszystkie** pasujące filtry — czy eksport to uwzględnia i czy właściciel jest o tym uprzedzony? |
| 15 | import CSV | `csv.php` | czy limit 500 wierszy naprawdę **przerywa czytanie** pliku, czy tylko przestaje zapisywać? Co z plikiem w kodowaniu innym niż UTF-8 i Windows-1250? |
| 16 | fałszywy sukces | całość | czy istnieje jeszcze **jakikolwiek** zapis do bazy, którego wynik nie jest sprawdzany, a użytkownik i tak widzi „Zapisano"? |
| 17 | ładowanie poza panelem | `segregator-maila.php` | pięć plików ładuje się przy każdym żądaniu, także na froncie — czy któryś wykonuje zapytanie, wysyła nagłówek albo cokolwiek drukuje? |
| 18 | uprawnienie `manage_options` | wszystkie uchwyty | czy to właściwy próg? Na wielostanowiskowej instalacji redaktor go nie ma — ale czy jest gdzieś ścieżka omijająca sprawdzenie? |

### Jak szybko sprawdzić deklaracje z tego dokumentu

W katalogu wtyczki (u Ciebie: `wp-content/plugins/segregator-maila/`) możesz
sprawdzić **każdą liczbę i nazwę z tego dokumentu** wprost w kodzie:

```
grep -rn "SEGM_" .                  # stałe: limity, progi, wersja schematu
grep -rn "nazwa_funkcji" .          # funkcja, o której mówi dany rozdział
php -l plik.php                     # czy plik jest poprawny składniowo
```

⚠️ **Czego z samej paczki NIE zrobisz.** `phpcs` i `phpstan` wymagają plików
konfiguracyjnych (ruleset standardu WordPressa i konfiguracja analizy) oraz
znajomości funkcji rdzenia WordPressa — a tych **nie ma w paczce**. Uruchomione
na samym katalogu wtyczki wypiszą albo brak ruleseta, albo lawinę „undefined
function add_action" — czyli hałas, nie ocenę kodu. Ta wtyczka jest sprawdzana
oboma narzędziami po stronie autora, na pełnej konfiguracji.

Po stronie autora biegną też dwie kontrole, których wyniku z paczki odtworzyć
się nie da: porównanie **każdej liczby i nazwy z tego dokumentu** z kodem oraz
pomiar ramek tekstu w `DIAGRAM-PRZEPLYWU.svg` (czy podpisy mieszczą się
w kształtach).
