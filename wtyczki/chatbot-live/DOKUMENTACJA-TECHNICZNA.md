# ChatBot LIVE — dokumentacja techniczna

Wersja wtyczki: **1.2.4** · schemat bazy: **3**

Dokument opisuje **kod, który jest**, nie plan. Każda liczba i nazwa poniżej ma pokrycie
w źródle — przy każdej podano plik i symbol, żeby dało się to sprawdzić `grep`-em, a nie
uwierzyć na słowo. Instrukcja dla właściciela strony jest osobno, w `INSTRUKCJA.md`;
diagram przepływu — w `DIAGRAM-PRZEPLYWU.svg`.

---

## 1. Wymagania środowiska

| Wymaganie | Wartość | Skąd |
|---|---|---|
| WordPress | 6.0 lub nowszy | nagłówek `Requires at least` w `chatbot-live.php` |
| PHP | 8.1 lub nowszy | nagłówek `Requires PHP` w `chatbot-live.php` |
| Baza | MySQL/MariaDB z obsługą `ON DUPLICATE KEY UPDATE` | `chatl_rate_limit()`, `chatl_ai_zuzyj()` |
| Rozszerzenia PHP | `mbstring` (`mb_strtolower`, `mb_strpos`, `mb_substr`, `mb_strlen`) | `silnik.php`, `front.php` |
| Połączenie wychodzące | **tylko w trybie AI**: HTTPS do `api.anthropic.com` | `chatl_ai_zapytaj()` |
| Uprawnienia w panelu | `manage_options` | `chatl_admin_menu()` i każdy handler `admin_post_*` |

Wtyczka **nie wymaga** Composera, npm, cron systemowego ani żadnej biblioteki zewnętrznej.
Front ładuje wyłącznie własne `assets/widget.css` i `assets/widget.js`.

> **Jedno zastrzeżenie, żeby dokumentacja nie kłamała.** Wpisy do logu błędów idą przez
> `wp_trigger_error()`, które istnieje **od WordPressa 6.4**. Wywołania są osłonięte
> `function_exists()` (`chatl_log_bledu_bazy()`, `chatl_log_bledu_ai()`), więc na WP 6.0–6.3
> wtyczka działa normalnie — po prostu nie powstaje wpis w logu. Komunikaty błędów dla
> użytkownika i administratora działają na każdej wspieranej wersji, bo nie zależą od tej
> funkcji. `wp_trigger_error()` milczy też, gdy `WP_DEBUG` jest wyłączone — to zachowanie
> rdzenia WordPressa, nie wtyczki.

---

## 2. Pliki

| Plik | Rola | Kiedy ładowany |
|---|---|---|
| `chatbot-live.php` | nagłówek, stałe, `require`, zaczepy cyklu życia, cron retencji, ustawienia domyślne, log błędów bazy | zawsze |
| `includes/instalator.php` | `dbDelta` (5 tabel), aktywacja, deaktywacja, seed demo | zawsze |
| `includes/silnik.php` | **jedyna** reguła dopasowania + punktacja + zapis wymiany do historii | zawsze |
| `includes/ai.php` | tryb AI: klucz, licznik stop-loss, instrukcja systemowa, wywołanie API, log błędu API | zawsze |
| `includes/front.php` | assety widgetu, limity na IP, walidacja sesji, dwa endpointy AJAX | zawsze |
| `includes/admin.php` | menu, CRUD Q&A, zgłoszenia, historia, ustawienia, test bota w panelu | tylko `is_admin()` |
| `includes/csv.php` | ręczny eksport zgłoszeń i rozmów do CSV | tylko `is_admin()` |
| `uninstall.php` | obie ścieżki deinstalacji | tylko przy odinstalowaniu (`WP_UNINSTALL_PLUGIN`) |
| `assets/widget.js`, `assets/widget.css` | widget czatu na froncie | front, gdy bot włączony |

`admin.php` i `csv.php` są w bloku `if ( is_admin() )` w `chatbot-live.php` — front nie ładuje
ani panelu, ani eksportu.

---

## 3. Schemat bazy

Pięć tabel, wszystkie z prefiksem instalacji (`$wpdb->prefix`, domyślnie `wp_`). Tworzone
i aktualizowane przez `dbDelta()` w `chatl_utworz_tabele()` (`includes/instalator.php`).
Kolacja z `$wpdb->get_charset_collate()`, czyli taka jak reszta tabel WordPressa.

### 3.1. `{prefix}chatl_qa` — baza pytań i odpowiedzi

Serce trybu regułowego. Właściciel wypełnia ją w panelu; przy pierwszej aktywacji dostaje
10 gotowych par demo.

| Kolumna | Typ | Po co jest |
|---|---|---|
| `id` | `bigint(20) unsigned`, PK, auto | Identyfikator pary. Panel pokazuje go przy teście bota („zadziałała para #7"), żeby było wiadomo, którą parę poprawić. |
| `pytanie` | `text` | Pytanie **wzorcowe** — opis dla człowieka, po co ta para istnieje. Silnik po nim **nie** szuka; służy do identyfikacji pary w panelu i w instrukcji systemowej trybu AI. |
| `slowa_kluczowe` | `text` | Warianty rozdzielone przecinkami. To po nich szuka silnik. Rdzenie („otwarc") łapią odmiany, formy bez ogonków („uslug") — pisownię bez polskich znaków. |
| `odpowiedz` | `text` | Treść, którą dostaje gość, gdy ta para wygra. |
| `aktywne` | `tinyint(1)`, domyślnie `1` | Wyłącznik pojedynczej pary bez kasowania jej. Silnik i instrukcja AI biorą **wyłącznie** wiersze z `aktywne = 1`. |
| `utworzono` | `datetime` | Kiedy para powstała. Kolumna porządkowa — kod nic po niej nie filtruje. |

### 3.2. `{prefix}chatl_zgloszenia` — kontakty zostawione w czacie

| Kolumna | Typ | Po co jest |
|---|---|---|
| `id` | `bigint(20) unsigned`, PK, auto | Identyfikator zgłoszenia; wchodzi do adresów akcji „oznacz obsłużone" i „usuń". |
| `email` | `varchar(190)` | Adres gościa. 190 znaków, bo to bezpieczna długość dla indeksu przy `utf8mb4`. Walidowany `is_email()` przed zapisem. |
| `wiadomosc` | `text` | Treść od gościa. Przycinana do 2000 znaków (`CHATL_MAX_WIADOMOSC`). |
| `sesja` | `varchar(64)`, domyślnie `''` | Identyfikator sesji czatu — pozwala zestawić zgłoszenie z rozmową, która je poprzedziła. |
| `status` | `varchar(20)`, domyślnie `'nowe'` | `nowe` albo `obsluzone` — jedyne dwie wartości przyjmowane przez handler zmiany statusu. Ma własny indeks `KEY status`. |
| `utworzono` | `datetime` | Kiedy przyszło zgłoszenie. **Zgłoszenia NIE podlegają retencji 90 dni** — kasuje je właściciel ręcznie albo pełna deinstalacja. |

### 3.3. `{prefix}chatl_rozmowy` — historia wymian

| Kolumna | Typ | Po co jest |
|---|---|---|
| `id` | `bigint(20) unsigned`, PK, auto | Kolejność wymian w rozmowie; po nim sortuje widok rozmowy i porcjowanie eksportu CSV. |
| `sesja` | `varchar(64)` | Klucz grupujący wymiany w jedną rozmowę. Ma indeks `KEY sesja`, bo widok pojedynczej rozmowy filtruje właśnie po nim. |
| `rola` | `varchar(10)` | `gosc` albo `bot` — kto się odezwał. |
| `silnik` | `varchar(10)`, domyślnie `'reguly'` | `reguly` albo `ai` — **czym** odpowiedział bot. Dzięki temu widać po fakcie, czy tryb AI naprawdę działał, czy po cichu zjeżdżał do reguł. |
| `tresc` | `text` | Treść wymiany, już zsanityzowana. |
| `utworzono` | `datetime` | Znacznik czasu. Ma indeks `KEY utworzono`, bo cron retencji kasuje właśnie po tej kolumnie. |

### 3.4. `{prefix}chatl_ai_uzycie` — licznik stop-loss trybu AI

| Kolumna | Typ | Po co jest |
|---|---|---|
| `dzien` | `date`, PK | Doba, której dotyczy licznik. Klucz główny, więc jeden wiersz na dobę i inkrement idzie atomowo przez `ON DUPLICATE KEY UPDATE`. |
| `wywolania` | `int(10) unsigned`, domyślnie `0` | Ile **udanych** wywołań API poszło tego dnia. Licznik rośnie dopiero PO sukcesie (`chatl_ai_zuzyj()` wołane na końcu `chatl_ai_odpowiedz()`), więc nieudane próby nie zjadają limitu właściciela. |

### 3.5. `{prefix}chatl_rate_limit` — licznik limitów na IP

| Kolumna | Typ | Po co jest |
|---|---|---|
| `klucz` | `varchar(64)`, PK | `akcja\|numer_okna\|md5(IP)`. Adres IP **nigdy nie leży tu jawnie** — tylko jego skrót. Klucz główny daje atomowy inkrement bez blokad. |
| `licznik` | `int(10) unsigned`, domyślnie `0` | Ile żądań padło w tym oknie z tego IP. |
| `okno_do` | `datetime` | Do kiedy wiersz jest potrzebny — ustawiane na dwa okna do przodu. Ma indeks `KEY okno_do`, bo codzienny cron kasuje wygasłe wiersze właśnie po tej kolumnie. |

---

## 4. Opcje w `wp_options`

| Opcja | Zawartość | Autoload | Kiedy powstaje |
|---|---|---|---|
| `chatl_ustawienia` | tablica ustawień (patrz niżej) | tak (domyślnie) | przy pierwszym zapisie ustawień — **nie** przy aktywacji |
| `chatl_wersja_schematu` | numer schematu, obecnie `3` | tak (domyślnie) | przy aktywacji i przy migracji schematu |
| `chatl_klucz_api` | klucz API właściciela | **nie** (`add_option(..., '', false)`) | przy zapisaniu klucza w panelu |
| `chatl_blad_maila` | `['kiedy' => data, 'adres' => e-mail]` | **nie** | gdy `wp_mail()` zwróci `false`; kasowana przy pierwszej udanej wysyłce |

Klucz API jest bez autoloadu celowo: nie wchodzi do zapytania ładowanego na każdej odsłonie
strony. Weryfikacja na żywym stacku po pełnym cyklu instalacja→deinstalacja→instalacja:
`autoload` = `off`.

### Klucze tablicy `chatl_ustawienia` i wartości domyślne

Źródło: `chatl_ustawienia()` w `chatbot-live.php`. Funkcja scala zapisane wartości z domyślnymi,
więc **brakujący klucz nigdy nie jest błędem** — nowe pole dostaje wartość domyślną bez migracji.

| Klucz | Domyślnie | Znaczenie |
|---|---|---|
| `wlaczony` | `1` | Czy widget pokazuje się na stronie. Wyłączony = assety w ogóle się nie ładują, a oba endpointy AJAX odpowiadają błędem 403. |
| `powitanie` | tekst powitalny | Pierwsza wiadomość bota w oknie czatu. |
| `tekst_awaryjny` | tekst „nie znam odpowiedzi…" | Co bot mówi, gdy żadna para nie pasuje. |
| `mail_powiadomien` | `admin_email` z WordPressa | Adres, na który idzie powiadomienie o nowym zgłoszeniu. |
| `powiadomienia` | `1` | Czy w ogóle wysyłać ten mail. |
| `usun_dane` | `0` | Przełącznik ścieżki deinstalacji (rozdział 6). |
| `ai_informacje` | `''` | Opis firmy wstrzykiwany do instrukcji systemowej modelu. |
| `ai_limit_dzienny` | `100` | Globalny dobowy limit wywołań AI. Zapis ograniczany do zakresu 1–100000. |
| `adres_polityki` | `''` | Adres polityki prywatności linkowany przy formularzu. Pusty = sama informacja, bez linku. |

---

## 5. Stałe konfiguracyjne

Wszystkie liczby, którymi wtyczka się rządzi, są stałymi — nie ma ich rozsianych po kodzie.

| Stała | Wartość | Plik | Do czego |
|---|---|---|---|
| `CHATL_WERSJA` | `1.2.4` | `chatbot-live.php` | wersja wtyczki; służy też jako `?ver=` assetów (przeglądarka nie poda starego pliku po aktualizacji) |
| `CHATL_WERSJA_SCHEMATU` | `3` | `chatbot-live.php` | wyzwalacz migracji schematu |
| `CHATL_KATALOG`, `CHATL_URL` | ścieżka i adres wtyczki | `chatbot-live.php` | `require` i adresy assetów |
| `CHATL_MAX_PYTANIE` | `500` | `includes/front.php` | maksymalna długość pytania gościa (nadmiar jest **przycinany**, nie odrzucany) |
| `CHATL_MAX_WIADOMOSC` | `2000` | `includes/front.php` | maksymalna długość treści zgłoszenia (jw.) |
| `CHATL_LIMIT_NA_MIN` | `10` | `includes/front.php` | wspólny kubeł: żądań na minutę z jednego IP, łącznie dla pytań i zgłoszeń |
| `CHATL_RETENCJA_DNI` | `90` | `includes/front.php` | okres retencji historii rozmów — **jedno źródło** dla crona, tekstu w widgecie, panelu i eksportu |
| `CHATL_AI_LIMIT_IP_NA_DOBE` | `20` | `includes/front.php` | osobny, twardszy limit wywołań AI z jednego IP na dobę |
| `CHATL_AI_MODEL` | `claude-haiku-4-5-20251001` | `includes/ai.php` | domyślny model (podmienialny filtrem) |
| `CHATL_AI_MAX_TOKENS` | `1024` | `includes/ai.php` | limit długości odpowiedzi modelu |
| `CHATL_AI_TIMEOUT` | `9` | `includes/ai.php` | timeout jednego wywołania w sekundach; z jednym ponowieniem daje maks. 18 s, z zapasem pod typowy limit 30 s na hostingu |
| `CHATL_AI_BRAK` | `BRAK_ODPOWIEDZI` | `includes/ai.php` | sentinel „model nie wie" — sygnał do zbiórki kontaktu zamiast zgadywania |
| `CHATL_CSV_PORCJA` | `500` | `includes/csv.php` | ile wierszy naraz czyta eksport, żeby nie wciągać całej tabeli do pamięci |
| `CHATL_KLUCZ_API` | — | **`wp-config.php`, definiuje właściciel** | klucz API poza bazą; ma pierwszeństwo nad opcją |

---

## 6. Cykl życia

### 6.1. Aktywacja — `chatl_aktywacja()` (`includes/instalator.php`)

Po kolei, dokładnie to i nic więcej:

1. `chatl_utworz_tabele()` — pięć `dbDelta()`. Operacja jest **idempotentna**: przy reaktywacji
   nie kasuje danych, tylko dociąga brakujące kolumny i indeksy.
2. `update_option( 'chatl_wersja_schematu', CHATL_WERSJA_SCHEMATU )`.
3. `chatl_seed_demo()` — 10 par Q&A typowej polskiej firmy usługowej. **Wchodzi wyłącznie do
   PUSTEJ tabeli** (`SELECT COUNT(*)` i wyjście, gdy > 0), więc reaktywacja nie nadpisuje
   pracy właściciela.
4. Harmonogram: jeśli zdarzenie `chatl_czyszczenie_historii` nie jest jeszcze zaplanowane —
   `wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', ... )`. **Pierwszy przebieg jest
   dobę po aktywacji**, nie natychmiast.

Aktywacja **nie** tworzy opcji `chatl_ustawienia` — do pierwszego zapisu ustawień wtyczka
działa na wartościach domyślnych z `chatl_ustawienia()`.

### 6.2. Migracja schematu — `chatl_sprawdz_schemat()`

Na `plugins_loaded`: jeśli `chatl_wersja_schematu` z bazy różni się od `CHATL_WERSJA_SCHEMATU`,
leci `chatl_utworz_tabele()` i zapis nowej wersji. To ścieżka dla aktualizacji wtyczki bez
deaktywacji (wtedy `register_activation_hook` się nie odpala).

> **Dodanie nowej tabeli wymaga zmiany w trzech miejscach:** `chatl_utworz_tabele()`,
> `uninstall.php` oraz podniesienia `CHATL_WERSJA_SCHEMATU`. Pominięcie któregokolwiek
> zostawia tabelę-sierotę.

### 6.3. Deaktywacja — `chatl_deaktywacja()`

`wp_clear_scheduled_hook( 'chatl_czyszczenie_historii' )` — i tyle. **Dane zostają w komplecie**:
tabele, ustawienia, klucz API. Deaktywacja jest odwracalna bez strat.

### 6.4. Cron retencji — `chatl_czyszczenie_historii()`

Codziennie, dwa `DELETE`:

- z `chatl_rozmowy` wszystko starsze niż `CHATL_RETENCJA_DNI` dni,
- z `chatl_rate_limit` wiersze z `okno_do` w przeszłości.

Wynik obu zapytań jest sprawdzany; porażka trafia do logu WordPressa.
**Zgłoszeń ten cron nie dotyka** — kontakty od gości nie mają automatycznego terminu ważności.

WP-cron to pseudo-cron: bez ruchu na stronie nic się nie odpala. Dlatego wejście administratora
na „Historię rozmów" woła tę samą funkcję (`chatl_strona_historia()`) — samonaprawa na wypadek
strony bez ruchu. Funkcja jest idempotentna, więc podwójne wywołanie nic nie psuje.

### 6.5. Deinstalacja — dwie ścieżki (`uninstall.php`)

Plik odpala się tylko przy prawdziwym odinstalowaniu (`WP_UNINSTALL_PLUGIN`), nie przy deaktywacji.

**Ścieżka A — domyślna (`usun_dane` = 0):**

1. `delete_option( 'chatl_klucz_api' )` — klucz kasowany **ZAWSZE**, niezależnie od ustawień.
   Poświadczenie to nie są dane użytkowe; martwa wtyczka nie ma prawa zostawiać sekretu w bazie.
2. `return` — tabele, ustawienia i historia **zostają**. Właściciel może odinstalować i wrócić
   bez strat.

**Ścieżka B — pełne czyszczenie (`usun_dane` = 1, zaznaczone w ustawieniach):**

1. To samo skasowanie klucza API.
2. `DROP TABLE IF EXISTS` dla wszystkich pięciu tabel.
3. `delete_option` dla `chatl_ustawienia`, `chatl_wersja_schematu`, `chatl_blad_maila`.
4. Sprzątanie transientów `chatl_rl_*` ze **starszych wersji wtyczki** (≤ 1.1.x, gdy licznik
   limitów żył w opcjach zamiast w tabeli). Zapytanie używa `esc_like()` — podkreślnik jest
   symbolem wieloznacznym w `LIKE`, więc bez eskejpowania mogłoby złapać cudze wpisy.

Czego **żadna** ścieżka nie rusza: stałej `CHATL_KLUCZ_API` w `wp-config.php` (wtyczka nie
modyfikuje plików konfiguracyjnych) i zdarzenia cron — to zdejmuje deaktywacja, która
w normalnym przebiegu WordPressa poprzedza odinstalowanie.

Weryfikacja na żywym stacku (pełny cykl, `audyt/boty/DOWODY-DEKLARACJI.md`): różnica stanu
tabel, opcji i cronów przed instalacją i po odinstalowaniu ze ścieżką B — **pusta**.

---

## 7. Zaczepy WordPressa

### 7.1. Rejestrowane przez wtyczkę

| Zaczep | Funkcja | Gdzie |
|---|---|---|
| `register_activation_hook` | `chatl_aktywacja` | `chatbot-live.php` |
| `register_deactivation_hook` | `chatl_deaktywacja` | `chatbot-live.php` |
| `plugins_loaded` | `chatl_sprawdz_schemat` | `chatbot-live.php` |
| `chatl_czyszczenie_historii` | `chatl_czyszczenie_historii` | `chatbot-live.php` (zdarzenie cron) |
| `wp_enqueue_scripts` | `chatl_assety` | `includes/front.php` |
| `admin_menu` | `chatl_admin_menu` | `includes/admin.php` |

### 7.2. Punkty wejścia AJAX (front, gość niezalogowany)

| Akcja | Funkcja | Nonce | Co robi |
|---|---|---|---|
| `chatl_pytanie` | `chatl_ajax_pytanie` | `chatl_front` | pytanie gościa → odpowiedź z AI albo z reguł |
| `chatl_zgloszenie` | `chatl_ajax_zgloszenie` | `chatl_front` | zapis kontaktu + powiadomienie mailem |

Obie zarejestrowane w wariancie `wp_ajax_` i `wp_ajax_nopriv_`, bo gość czatu nie jest zalogowany.

### 7.3. Punkty wejścia `admin-post` (panel, `manage_options` + nonce)

| Akcja | Funkcja | Nonce |
|---|---|---|
| `chatl_qa_zapisz` | `chatl_post_qa_zapisz` | `chatl_qa_zapisz` |
| `chatl_qa_usun` | `chatl_post_qa_usun` | `chatl_qa_usun_<id>` |
| `chatl_zgloszenie_status` | `chatl_post_zgloszenie_status` | `chatl_zgloszenie_status_<id>` |
| `chatl_zgloszenie_usun` | `chatl_post_zgloszenie_usun` | `chatl_zgloszenie_usun_<id>` |
| `chatl_ustawienia_zapisz` | `chatl_post_ustawienia_zapisz` | `chatl_ustawienia_zapisz` |
| `chatl_eksport_zgloszen` | `chatl_post_eksport_zgloszen` | `chatl_eksport_zgloszen` |
| `chatl_eksport_rozmow` | `chatl_post_eksport_rozmow` | `chatl_eksport_rozmow` |

Nonce operacji na pojedynczym wierszu zawiera jego `id` — nonce od cudzego wiersza nie przejdzie.
Oba eksporty przechodzą przez jedną bramkę `chatl_csv_brama()` (capability + nonce).

### 7.4. Filtry do rozbudowy

Wtyczka wystawia **dwa** filtry — oba w `includes/ai.php`, oba pomyślane jako punkt wejścia dla
integratora, i oba używane w testach do podstawienia atrapy API:

| Filtr | Domyślnie | Do czego |
|---|---|---|
| `chatl_ai_url` | `https://api.anthropic.com/v1/messages` | proxy firmowe, brama zgodności albo atrapa API w testach |
| `chatl_ai_model` | `CHATL_AI_MODEL` | podmiana modelu bez ruszania kodu wtyczki |

Przykład — cały ruch AI przez własne proxy:

```php
add_filter( 'chatl_ai_url', fn() => 'https://proxy.mojafirma.pl/anthropic/v1/messages' );
```

> Wtyczka **nie** wystawia filtrów do zmiany reguły dopasowania, limitów ani okresu retencji.
> To świadoma decyzja: te wartości są stałymi i mają jedno źródło, a filtr byłby drugim miejscem,
> w którym prawda o zachowaniu bota mogłaby się rozejść z dokumentacją.

---

## 8. Reguła dopasowania

Jedna funkcja, jeden plik: `chatl_trafione_warianty()` w `includes/silnik.php`. Wołają ją
`chatl_dopasuj()` (realny bot) **oraz** test bota w panelu — panel pokazuje warianty policzone
przez silnik, a nie własne.

1. Pytanie i słowa kluczowe → małe litery (`mb_strtolower`), obcięte białe znaki.
2. Słowa kluczowe dzielone po przecinku; puste warianty odrzucane.
3. Wariant trafia, jeśli występuje w pytaniu **jako podłańcuch** (`mb_strpos`) — stąd rdzeń
   „otwarc" łapie „otwarcia" i „otwarci".
4. Punktacja pary (`chatl_punktacja()`): liczba trafionych wariantów; przy remisie wygrywa
   **dłuższe łączne trafienie** — czyli wpis bardziej konkretny.
5. Zero trafień w całej bazie → `null` → gość dostaje tekst awaryjny i formularz kontaktu.

Silnik czyta wyłącznie pary z `aktywne = 1`.

---

## 9. Tryb AI (opcjonalny)

Wchodzi **przed** silnikiem regułowym i tylko wtedy, gdy `chatl_ai_dostepny()` **oraz**
`chatl_limit_ai_ip_ok()` zwrócą prawdę:

- jest klucz (ze stałej `CHATL_KLUCZ_API` albo z opcji — stała ma pierwszeństwo),
- globalny licznik dobowy jest poniżej `ai_limit_dzienny`,
- kubeł tego IP nie przekroczył `CHATL_AI_LIMIT_IP_NA_DOBE`.

Wywołanie: `wp_remote_post` na adres z filtru, timeout `CHATL_AI_TIMEOUT`, **jedno** ponowienie
i tylko przy błędzie połączenia, 5xx albo 429 (4xx nie jest ponawiane — to błąd żądania, nie awaria).

Każdy inny wynik niż HTTP 200 → wpis do logu (`chatl_log_bledu_ai()`) i `null`, czyli zjazd do
reguł. **Ten wpis w logu jest tu celowo:** wcześniej cichy zjazd maskował martwy tryb AI przez
całe wydania — bot wyglądał na działający, odpowiadając wyłącznie z reguł.

Model ma zakaz zgadywania; gdy nie wie, odpowiada sentinelem `CHATL_AI_BRAK`, a wtyczka
traktuje to jak brak trafienia i zbiera kontakt. Licznik `chatl_ai_uzycie` rośnie dopiero po
udanym wywołaniu.

---

## 10. Limity na IP

Jeden mechanizm, dwa kubły — `chatl_rate_limit( $akcja, $prog, $okno_sekund )` w `includes/front.php`:

| Kubeł | Próg | Okno | Czego pilnuje |
|---|---|---|---|
| `chat` | `CHATL_LIMIT_NA_MIN` = 10 | minuta | zalew bazy i mail-bombing właściciela; wspólny dla pytań i zgłoszeń |
| `ai` | `CHATL_AI_LIMIT_IP_NA_DOBE` = 20 | doba | żeby jeden gość nie wypalił całego dobowego budżetu AI wszystkich |

Inkrement jest atomowy (`ON DUPLICATE KEY UPDATE` + `LAST_INSERT_ID`), więc równoległe żądania
nie gubią zliczeń. Kubeł AI sprawdzany jest **dopiero** gdy tryb AI jest w ogóle dostępny —
inaczej licznik rósłby też w trybie regułowym.

> **Zachowanie przy awarii licznika jest jawnie „przepuść".** Gdy zapytanie licznika padnie,
> `chatl_rate_limit()` zwraca `true`. Limiter chroni przed spamem, ale nie może zablokować
> czatu z powodu własnej awarii — nonce i walidacje wejścia bronią niezależnie od niego.
> To decyzja projektowa, nie przeoczenie.

Wyczerpanie kubła AI **nie jest błędem dla gościa**: pytanie leci do silnika regułowego,
dokładnie jak przy braku klucza.

---

## 11. Bezpieczeństwo — co jest sprawdzane i gdzie

| Warstwa | Mechanizm |
|---|---|
| Panel | `manage_options` na menu i w **każdym** handlerze `admin_post_*`; nonce przy każdej mutacji |
| Front | nonce `chatl_front` na obu endpointach; limit na IP przed jakąkolwiek pracą |
| Wejście | `sanitize_email`, `sanitize_text_field`, `sanitize_textarea_field`, twarde przycinanie długości |
| Sesja | allowlist `^[a-zA-Z0-9-]{8,64}$` (`chatl_waliduj_sesje()`); cokolwiek innego → pusty string |
| SQL | `$wpdb->prepare()` przy każdej wartości od użytkownika; `esc_like()` przy `LIKE` |
| Wyjście HTML | `esc_html`, `esc_attr`, `esc_url`, `esc_textarea` w panelu; widget wstawia treści **wyłącznie** przez `textContent` — zero `innerHTML` |
| Zgoda | checkbox w przeglądarce **oraz** sprawdzenie po stronie serwera — bez zgody żądanie nie dochodzi do zapisu |
| CSV | komórka zaczynająca się od `=`, `+`, `-`, `@` dostaje apostrof (wstrzyknięcie formuły arkusza) |
| Sekret | klucz API bez autoloadu, nigdy nie wraca do formularza, kasowany przy każdej deinstalacji; zalecane trzymanie w `wp-config.php` |

Klucz API **nie jest szyfrowany w bazie** i jest to decyzja świadoma: klucz szyfrujący musiałby
leżeć w tym samym WordPressie, obok danych — kto ma bazę, ma jedno i drugie. Realną poprawą
jest wyniesienie klucza poza bazę stałą `CHATL_KLUCZ_API`, nie pozorne szyfrowanie.

---

## 12. Eksport CSV

Ręczny, przyciskiem w panelu — `includes/csv.php`. Dwa strumienie: wszystkie zgłoszenia
(`id, data, email, wiadomosc, status, sesja`) i historia rozmów (`sesja, data, kto, silnik, tresc`),
ta druga w całości albo dla jednej sesji.

Plik zaczyna się od **BOM UTF-8** — bez niego Excel z polskimi ustawieniami czyta go jako
windows-1250 i polskie znaki wychodzą krzakami. Czytanie idzie porcjami po `CHATL_CSV_PORCJA`
wierszy (`WHERE id > ostatnie`), więc duża historia nie wciąga całej tabeli do pamięci PHP.

**Nie ma eksportu automatycznego przed kasowaniem po 90 dniach — celowo.** Plik tworzony tuż
przed usunięciem oznaczałby, że dane nie są kasowane, tylko przenoszone gdzie indziej, co
kłóciłoby się z obietnicą retencji.

---

## 13. Znane granice

Rzeczy, których wtyczka **nie** robi — żeby nikt nie szukał ich w kodzie:

- **Bez czatu z operatorem na żywo** i bez integracji z Messengerem/WhatsAppem.
- **Bez pamięci między wizytami** — sesja żyje w `sessionStorage` karty przeglądarki.
- **Bez czytania treści strony** — wiedza bota to wyłącznie baza Q&A i opis firmy z ustawień.
- **Bez wielojęzyczności i bez i18n** — teksty są polskie wprost, bez `textdomain`.
- **Bez stronicowania** w panelu: lista zgłoszeń pokazuje 200 najnowszych, lista rozmów 100
  najnowszych sesji. Pełne dane wychodzą eksportem CSV.
- **Limit dobowy AI jest miękki** — sprawdzenie i inkrement to dwa kroki, więc równoległe
  zapytania mogą przekroczyć próg o kilka wywołań.
- **Retencja obejmuje tylko historię rozmów.** Zgłoszenia (a więc adresy e-mail gości) zostają,
  aż właściciel je skasuje. Jeśli polityka prywatności obiecuje termin także dla nich,
  trzeba je kasować ręcznie albo eksportem i usunięciem.
