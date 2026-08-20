# ChatBot LIVE — dokumentacja techniczna

Wersja wtyczki: **1.4.3** · schemat bazy: **4** · licencja: **GPL-2.0-or-later**

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
| Licencja | GPL-2.0-or-later | nagłówek `License` + plik `LICENSE` |

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

### Licencja

Wtyczka jest wydana na **GPL-2.0-or-later**. Pełny tekst licencji leży w pliku `LICENSE`
w katalogu wtyczki; nagłówek deklaruje `License` i `License URI`.

To nie jest wybór stylistyczny: kod wtyczki korzysta z API WordPressa, więc jest dziełem
pochodnym i dziedziczy licencję rdzenia. **Nie stoi to na przeszkodzie sprzedaży** — GPL
reguluje warunki, na jakich kupujący dostaje kod (m.in. prawo do modyfikacji i dalszej
dystrybucji), a nie to, czy wolno pobrać zapłatę. Na tym stoi cały rynek płatnych wtyczek WP.

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
| `includes/statystyki.php` | liczby okresu, grupowanie pytań bez odpowiedzi, strona „Statystyki i luki" | tylko `is_admin()` |
| `includes/wyglad.php` | kolory z ustawień → zmienne CSS, kontrast WCAG, pozycja widgetu | zawsze (front też ich potrzebuje) |
| `includes/powitanie.php` | ekran powitalny po aktywacji + zapamiętanie zamknięcia | tylko `is_admin()` |
| `assets/panel-wyglad.js` | podgląd wyglądu na żywo i odczyt kontrastu w panelu | tylko na stronie ustawień |
| `CHANGELOG.md` | historia wersji (Keep a Changelog) | — |
| `readme.txt` | opis w standardzie WordPressa (nagłówek, Description, Installation, FAQ, Changelog) | — |
| `uninstall.php` | obie ścieżki deinstalacji | tylko przy odinstalowaniu (`WP_UNINSTALL_PLUGIN`) |
| `LICENSE` | pełny tekst GNU GPL v2 (339 linii) | — |
| `INSTRUKCJA.md`, `DOKUMENTACJA-TECHNICZNA.md`, `DIAGRAM-PRZEPLYWU.svg` | dokumentacja w paczce | — |
| `assets/widget.js`, `assets/widget.css` | widget czatu na froncie | front, gdy bot włączony |

`admin.php`, `csv.php`, `statystyki.php` i `powitanie.php` są w bloku `if ( is_admin() )`
w `chatbot-live.php` — front nie ładuje ani panelu, ani eksportu, ani liczenia statystyk.
`wyglad.php` jest **poza** tym blokiem, bo kolory z ustawień muszą trafić także na front.

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
| `trafiono` | `tinyint(1)`, dopuszcza `NULL` | Czy bot **znał** odpowiedź na to pytanie: `1` tak, `0` nie, `NULL` „nie wiadomo". Ustawiane na OBU wierszach wymiany — na pytaniu gościa i na odpowiedzi bota — bo raport luk pokazuje treść **pytania**, nie odpowiedzi. `NULL` mają wyłącznie wiersze sprzed wersji 1.3.0; nie są zgadywane wstecz i nie wchodzą do mianownika skuteczności. |
| `utworzono` | `datetime` | Znacznik czasu. Ma indeks `KEY utworzono`, bo cron retencji kasuje właśnie po tej kolumnie. |

Trzeci indeks, `KEY luki (rola, trafiono, utworzono)`, obsługuje jedyne zapytanie raportu luk
(`rola = 'gosc' AND trafiono = 0` w zakresie dat) — bez niego strona statystyk skanowałaby
całą historię przy każdym wejściu.

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
| `chatl_wersja_schematu` | numer schematu, obecnie `4` | tak (domyślnie) | przy aktywacji i przy migracji schematu |
| `chatl_klucz_api` | klucz API właściciela | **nie** (`add_option(..., '', false)`) | przy zapisaniu klucza w panelu |
| `chatl_blad_maila` | `['kiedy' => data, 'adres' => e-mail]` | **nie** | gdy `wp_mail()` zwróci `false`; kasowana przy pierwszej udanej wysyłce |

Klucz API jest bez autoloadu celowo: nie wchodzi do zapytania ładowanego na każdej odsłonie
strony. Weryfikacja na żywym stacku po pełnym cyklu instalacja→deinstalacja→instalacja:
`autoload` = `off`.

### Metadane użytkownika

| Klucz | Zawartość | Kiedy powstaje |
|---|---|---|
| `chatl_powitanie_zamkniete` | `1` — ten administrator zamknął ekran powitalny | po kliknięciu „Rozumiem, zamknij ten ekran" |

Świadomie **metadane użytkownika, nie opcja**: na jednej stronie bywa kilku
administratorów i to, że jeden przeczytał powitanie, nie znaczy, że pozostali je
widzieli. Pełne czyszczenie przy deinstalacji kasuje ten klucz u wszystkich
naraz (`delete_metadata( 'user', 0, …, true )`).

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
| `kolor_glowny` | `#bfff38` | Bąbelek, dymki bota, przyciski, obwódka fokusu. Wartość spoza `#rrggbb` jest odrzucana i wraca domyślna. |
| `kolor_naglowka` | `#08090b` | Pasek z tytułem okna czatu i paski formularza kontaktu. |
| `kolor_tekstu` | `auto` | `auto` = wtyczka dobiera kolor tekstu osobno dla bąbelka i dla nagłówka, biorąc ten o lepszym kontraście. Wartość `#rrggbb` = ten sam kolor na obu. |
| `pozycja` | `prawo` | `prawo` albo `lewo` — róg strony, w którym stoi widget. |
| `za_proxy` | `0` | Czy ufać nagłówkom proxy przy ustalaniu adresu gościa. **Domyślnie wyłączone** — `X-Forwarded-For` da się podrobić. |
| `adres_polityki` | `''` | Adres polityki prywatności linkowany przy formularzu. Pusty = sama informacja, bez linku. |

---

## 5. Stałe konfiguracyjne

Wszystkie liczby, którymi wtyczka się rządzi, są stałymi — nie ma ich rozsianych po kodzie.

| Stała | Wartość | Plik | Do czego |
|---|---|---|---|
| `CHATL_WERSJA` | `1.4.3` | `chatbot-live.php` | wersja wtyczki; służy też jako `?ver=` assetów (przeglądarka nie poda starego pliku po aktualizacji) |
| `CHATL_WERSJA_SCHEMATU` | `4` | `chatbot-live.php` | wyzwalacz migracji schematu |
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
| `CHATL_LUKI_LIMIT` | `2000` | `includes/statystyki.php` | sufit pytań bez odpowiedzi wciąganych do grupowania; przekroczenie jest **wypisane na stronie**, nie przemilczane |
| `CHATL_LUKI_POKAZ` | `25` | `includes/statystyki.php` | ile tematów luk pokazuje tabela (reszta jest policzona, tylko niewypisana) |
| `CHATL_LUKI_PROG` | `50` | `includes/statystyki.php` | próg scalania dwóch pytań w jeden temat, w procentach wspólnych rdzeni |
| `CHATL_LUKI_RDZEN` | `4` | `includes/statystyki.php` | do ilu znaków skracamy słowo przy **grupowaniu** (polski odmienia końcówki; przy pięciu „firmę"/„firmy" to jeszcze dwa różne rdzenie) |
| `CHATL_WCAG_PROG` | `4.5` | `includes/wyglad.php` | próg kontrastu z WCAG 2.1, kryterium 1.4.3 (AA) dla zwykłego tekstu |
| `CHATL_KOLOR_CIEMNY` | `#08090b` | `includes/wyglad.php` | ciemny kolor tekstu, spośród których wybiera automat |
| `CHATL_KOLOR_JASNY` | `#f5f5f5` | `includes/wyglad.php` | jasny kolor tekstu, jw. — para z paletą wtyczki, nie czyste `#000`/`#fff`, żeby automat przy domyślnych kolorach dawał **dokładnie** dotychczasowy wygląd |
| `CHATL_PIERSCIEN_ALFA` | `0.18` | `includes/wyglad.php` | krycie pierścienia hover/fokusu liczonego z koloru głównego |
| `CHATL_META_POWITANIE` | `chatl_powitanie_zamkniete` | `includes/powitanie.php` | klucz metadanych „ten administrator zamknął powitanie" |
| `CHATL_TRANSIENT_AKTYWACJA` | `chatl_po_aktywacji` | `includes/powitanie.php` | znacznik „właśnie aktywowano", życie 1 minuta |
| `CHATL_SUGESTIA_RDZEN` | `5` | `includes/statystyki.php` | rdzeń **podpowiadanych słów kluczowych** — dłuższy, bo trafia do silnika dopasowania: `koszt` łapie „koszty"/„kosztuje", a `kosz` łapałby też „koszulę" |
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

Zweryfikowane na działającej instalacji (pełny cykl instalacja → deinstalacja → instalacja):
różnica stanu tabel, opcji i zdarzeń cron przed instalacją i po odinstalowaniu ze ścieżką B —
**pusta**.

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
| `chatl_powitanie_zamknij` | `chatl_post_powitanie_zamknij` | `chatl_powitanie_zamknij` |

Nonce operacji na pojedynczym wierszu zawiera jego `id` — nonce od cudzego wiersza nie przejdzie.
Oba eksporty przechodzą przez jedną bramkę `chatl_csv_brama()` (capability + nonce).

### 7.4. Filtry do rozbudowy

Filtry pomyślane jako punkt wejścia dla integratora. Dwa w `includes/ai.php` (oba używane
w testach do podstawienia atrapy API) i jeden w `includes/front.php`:

| Filtr | Plik | Do czego |
|---|---|---|
| `chatl_ip_goscia` | `includes/front.php` | ostatnie słowo przy ustalaniu adresu gościa — dla hostingu z nietypowym nagłówkiem (`True-Client-IP`, `X-Real-IP`). Zwrócenie pustego stringa znaczy „nie wiem". |

Dwa filtry trybu AI:

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

## 12. Statystyki i raport luk

Strona panelu **„Statystyki i luki"** (`chatl_strona_statystyki()` w `includes/statystyki.php`,
slug `chatl-statystyki`). Okres wybiera się listą (7 / 30 / 90 dni) albo dwiema datami; wartości
z formularza przechodzą przez `chatl_data_ok()` (regex + `checkdate`), a granice doby liczy
`chatl_granice_okresu()` w czasie **lokalnym strony** — bo wiersze zapisuje `current_time( 'mysql' )`.

### 12.1. Co pokazuje

| Liczba | Skąd |
|---|---|
| pytania gości, liczba rozmów | `chatl_rozmowy`: wiersze `rola = 'gosc'`, rozmowy = `COUNT(DISTINCT sesja)` |
| odpowiedzi bota i **skuteczność** | `trafiono = 1` / (`trafiono = 1` + `trafiono = 0`) |
| bez odpowiedzi | `trafiono = 0` |
| rozdział reguły / AI | kolumna `silnik` przy trafionych odpowiedziach |
| zebrane kontakty i konwersja | `chatl_zgloszenia` w okresie; konwersja = rozmowy z kontaktem / wszystkie rozmowy |
| ruch dzień po dniu | `GROUP BY DATE( utworzono )`, słupki rysowane czystym CSS (bez JS i bibliotek) |

Wiersze sprzed 1.3.0 (`trafiono IS NULL`) są liczone **osobno** i wypisane w komunikacie na
stronie. Gdy w okresie nie ma ani jednej zmierzonej odpowiedzi, kafelek skuteczności pokazuje
kreskę, a nie `0 (0,0%)` — „zero" i „nie mierzono" to dwie różne rzeczy.

### 12.2. Grupowanie pytań bez odpowiedzi

`chatl_grupuj_pytania()` składa surowe pytania w tematy w dwóch krokach:

1. **normalizacja** (`chatl_normalizuj_pytanie()`): małe litery, ogonki → ASCII, precz
   z interpunkcją, pojedyncze spacje. Pytania identyczne po normalizacji lądują w jednym kubełku.
2. **scalanie podobnych**: dla każdego kubełka liczone są **rdzenie** (`chatl_rdzenie_pytania()`) —
   słowa dłuższe niż 3 znaki, bez listy słów pustych, skrócone do `CHATL_LUKI_RDZEN` znaków.
   Podpowiedź słów kluczowych używa tej samej funkcji, ale z `CHATL_SUGESTIA_RDZEN` —
   grupowanie tylko układa listę, a podpowiedź zaczyna dopasowywać odpowiedzi gościom,
   więc pomyłka kosztuje tam więcej.
   Dwa kubełki idą do jednego tematu, gdy `chatl_podobienstwo_pytan()` (Jaccard na rdzeniach)
   daje co najmniej `CHATL_LUKI_PROG` procent.

Skracanie do rdzenia jest tu konieczne, nie kosmetyczne: polski odmienia **końcówki**, więc
„ile kosztuje dostawa" i „koszt dostawy" na całych słowach mają zero wspólnego, a na rdzeniach
67%. Cztery znaki, nie pięć — odmiana potrafi siedzieć dokładnie na piątym („firmę"/„firmy"). Rdzenie klastra to rdzenie jego **pierwszego, najliczniejszego** kubełka i już nie rosną —
gdyby rosły przy każdym scaleniu, klaster stopniowo pasowałby do wszystkiego.

Kolejność jest **deterministyczna** (najpierw liczniejsze, przy remisie alfabetycznie), więc
ten sam zestaw pytań zawsze daje ten sam raport, niezależnie od kolejności wierszy z bazy.
Pisownia gościa nie jest nigdzie nadpisywana — normalizacja służy tylko do sklejania, a lista
„inne sformułowania" pokazuje, co dokładnie wpadło do tematu.

### 12.3. Skrót „Dopisz odpowiedź"

Przycisk przy każdym temacie prowadzi na `admin.php?page=chatl-qa` z parametrami
`nowe_pytanie` i `nowe_slowa`. `chatl_strona_qa()` **tylko wypełnia** nimi formularz — zapis
idzie jak zawsze osobnym żądaniem `admin-post` z noncem i `manage_options`, więc adres URL
niczego nie zapisuje. Podpowiedź słów kluczowych to rdzenie z `chatl_sugerowane_slowa()`,
bo silnik dopasowuje **podłańcuchy**: `koszt` łapie i „koszty", i „kosztuje", a całe „kosztuje"
nie złapie „koszt".

Pytanie wstawiane do formularza to **reprezentant tematu** (najczęstsze sformułowanie), a nie
każdy z wariantów — właściciel odpowiada na temat, nie na jedno zdanie.

---

## 13. Wygląd widgetu i ekran powitalny

### 13.1. Kolory jako zmienne CSS

`assets/widget.css` deklaruje pięć zmiennych na selektorze `.chatl-korzen`
(**nie** w `:root` — wtyczka nie zaśmieca globalnej przestrzeni nazw motywu):
`--chatl-glowny`, `--chatl-glowny-tekst`, `--chatl-naglowek`,
`--chatl-naglowek-tekst`, `--chatl-pierscien`. Wartości wpisane w arkuszu to
paleta sprzed wersji 1.4.0, więc **sam arkusz wygląda dokładnie tak jak wcześniej**.

`chatl_style_wygladu()` (`includes/wyglad.php`) porównuje wyliczone zmienne
z domyślnymi i zwraca regułę **tylko z tymi, które właściciel naprawdę zmienił**;
przy domyślnym wyglądzie zwraca pusty string i `chatl_assety()` nie dokłada do
strony ani jednego bajtu. Gdy coś zmieniono, kilkadziesiąt bajtów idzie przez
`wp_add_inline_style()` — statyczny `widget.css` zostaje w pamięci podręcznej
przeglądarki, a arkusz **nie jest generowany od nowa**.

Pozycja widgetu to klasa `.chatl-lewo`, nie zmienna: nazwy własności (`right`
kontra `left`) nie da się podać zmienną CSS, a podmiana obu naraz musi być
atomowa, żeby widget nie mignął po złej stronie. Klasę dokłada `widget.js`
z wartości przekazanej w `chatlDane.pozycja`, **przed** wstawieniem elementu
do dokumentu.

### 13.2. Kontrast (WCAG 2.1)

`chatl_luminancja()` liczy luminancję względną wzorem z normy (nie
przybliżeniem), `chatl_kontrast()` zwraca współczynnik od 1.0 do 21.0.
Próg `CHATL_WCAG_PROG` = 4.5:1 to kryterium 1.4.3 na poziomie AA dla zwykłego
tekstu.

Domyślne `kolor_tekstu` = `auto`: `chatl_kolor_tekstu_na()` wybiera spośród
`CHATL_KOLOR_CIEMNY` i `CHATL_KOLOR_JASNY` ten o wyższym kontraście do tła —
osobno dla bąbelka i osobno dla paska nagłówka, bo to dwa różne tła. Gdy
właściciel wpisze własny kolor, panel liczy kontrast i **mówi wprost**, czy
zestaw spełnia próg (`chatl_ocena_kontrastu()`), ale nie blokuje zapisu.

Wzór kontrastu istnieje też w `assets/panel-wyglad.js`, bo przeglądarka nie ma
jak pytać PHP przy każdym ruchu suwaka. Żeby te dwie kopie nie rozjechały się po
cichu, `testy/wyglad-panel.js` ustawia kolory, czyta liczbę policzoną w panelu,
zapisuje ustawienia i porównuje ją z liczbą policzoną przez PHP.

Konfigurowalne jest to, co widać z zewnątrz i co gryzie się z paletą strony
klienta: bąbelek, akcenty i pasek nagłówka. **Wnętrze okna czatu** (ciemne tło
`#101116`, linie, dymek gościa) zostaje na sztywno — to spójny, celowy wygląd
panelu rozmowy, a nie przeoczenie.

### 13.3. Ekran powitalny

Aktywacja ustawia transient `CHATL_TRANSIENT_AKTYWACJA` na minutę. Na
`admin_init` `chatl_moze_powitanie()` przekierowuje na stronę `chatl-powitanie`,
ale tylko gdy: znacznik istnieje, **nie** trwa zbiorcze włączanie wtyczek
(`activate-multi` — inaczej przekierowanie ukradłoby ekran pozostałym
wtyczkom), użytkownik ma `manage_options` i **nie zamknął** powitania wcześniej.
Znacznik jest kasowany przy pierwszym sprawdzeniu, więc ekran nie wraca przy
kolejnych wejściach do panelu.

Strona jest rejestrowana jako podstrona i od razu zdejmowana z menu
(`remove_submenu_page`) — ma być osiągalna linkiem, ale nie zajmować pozycji
w menu. Przycisk „Rozumiem, zamknij ten ekran" idzie przez `admin-post`
z nonce i `manage_options`, zapisuje `CHATL_META_POWITANIE` przy tym koncie
i wraca do bazy Q&A.

---

## 14. Adres IP gościa a proxy

Limit „na IP" liczony z samego `REMOTE_ADDR` mierzy adres **proxy**. Strona za
Cloudflare albo za proxy hostingu widzi jeden adres dla wszystkich gości — wtedy
`CHATL_LIMIT_NA_MIN` wiadomości na minutę obowiązuje **całą witrynę naraz**,
a `CHATL_AI_LIMIT_IP_NA_DOBE` wywołań AI na adres staje się limitem na całą stronę.

`chatl_ip_goscia()` (`includes/front.php`) ustala adres w kolejności:
**`CF-Connecting-IP` → pierwszy adres z `X-Forwarded-For` → `REMOTE_ADDR`**,
ale nagłówki czyta **wyłącznie** po włączeniu ustawienia `za_proxy`.

To nie jest ostrożność na wyrost. `X-Forwarded-For` da się podrobić zwykłym
żądaniem, więc na stronie **bez** proxy zaufanie mu oznaczałoby, że każdy obchodzi
limity, podając za każdym razem inny adres — czyli lekarstwo gorsze od choroby.
Dlatego przełącznik jest domyślnie **wyłączony**, a panel mówi wprost, kiedy go
włączyć i czym to grozi.

`X-Forwarded-For` to lista `gość, proxy1, proxy2` — bierzemy **pierwszy** wpis.
Każdy odczyt przechodzi przez `filter_var( …, FILTER_VALIDATE_IP )`: nagłówek ze
śmieciem nie może wyłączyć limitu ani stworzyć własnego kubła, więc przy złej
wartości schodzimy do `REMOTE_ADDR`.

---

## 15. Eksport CSV

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

## 16. Znane granice

Rzeczy, których wtyczka **nie** robi — żeby nikt nie szukał ich w kodzie:

- **Bez czatu z operatorem na żywo** i bez integracji z Messengerem/WhatsAppem.
- **Bez pamięci między wizytami** — sesja żyje w `sessionStorage` karty przeglądarki.
- **Bez czytania treści strony** — wiedza bota to wyłącznie baza Q&A i opis firmy z ustawień.
- **Bez wielojęzyczności i bez i18n** — teksty są polskie wprost, bez `textdomain`.
- **Bez stronicowania** w panelu: lista zgłoszeń pokazuje 200 najnowszych, lista rozmów 100
  najnowszych sesji. Pełne dane wychodzą eksportem CSV.
- **Limit dobowy AI jest miękki** — sprawdzenie i inkrement to dwa kroki, więc równoległe
  zapytania mogą przekroczyć próg o kilka wywołań.
- **Statystyki sięgają tylko tak daleko, jak historia** — po `CHATL_RETENCJA_DNI` dniach wiersze
  są kasowane, więc raport luk sprzed pół roku nie istnieje.
- **Wiersze sprzed 1.3.0 nie mają znacznika `trafiono`** i nie są zgadywane wstecz. Widać je
  na stronie statystyk jako osobną liczbę; skuteczność liczy się bez nich.
- **Grupowanie luk jest heurystyką**, nie rozumieniem języka: sklejanie idzie po wspólnych
  rdzeniach słów, więc dwa pytania o tym samym, napisane zupełnie innymi słowami, zostaną
  dwoma tematami. Lista „inne sformułowania" pokazuje, co naprawdę wpadło do tematu.
- **Konfigurowalny jest wygląd zewnętrzny, nie całe okno.** Kolor główny, pasek
  nagłówka, tekst na nich i róg strony — tak. Wnętrze okna rozmowy zostaje
  ciemne; jasny motyw czatu to droga rozbudowy, nie brak w tej wersji.
- **Kontrast jest liczony i pokazywany, ale nie blokuje zapisu.** Właściciel może
  świadomie zapisać zestaw poniżej progu WCAG — dostaje wtedy czerwony komunikat,
  a nie odmowę. Domyślne „dobierz automatycznie" pilnuje progu za niego.
- **Zaufanie nagłówkom proxy jest decyzją właściciela, nie automatem.** Wtyczka
  nie zgaduje, czy strona stoi za Cloudflare — przy wyłączonym przełączniku limity
  są co najwyżej surowsze, przy włączonym na stronie bez proxy dałyby się obejść.
- **Retencja obejmuje tylko historię rozmów.** Zgłoszenia (a więc adresy e-mail gości) zostają,
  aż właściciel je skasuje. Jeśli polityka prywatności obiecuje termin także dla nich,
  trzeba je kasować ręcznie albo eksportem i usunięciem.
