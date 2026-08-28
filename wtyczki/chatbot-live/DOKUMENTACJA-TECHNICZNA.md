# ChatBot LIVE — dokumentacja techniczna

Wersja wtyczki: **1.5.12** · schemat bazy: **8** · licencja: **GPL-2.0-or-later**

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
| `includes/csv.php` | ręczny eksport zgłoszeń, rozmów **i bazy Q&A** do CSV | tylko `is_admin()` |
| `includes/import.php` | import bazy Q&A z CSV: parsowanie, podgląd przed zapisem, zapis zatwierdzonych wierszy | tylko `is_admin()` |
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

`admin.php`, `csv.php`, `import.php`, `statystyki.php` i `powitanie.php` są w bloku `if ( is_admin() )`
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
| `podejrzane` | `tinyint(1)` | `0` = zgłoszenie normalne, `1` = złapane przez pułapkę na boty; lista w panelu domyślnie pokazuje tylko `0` |
| `status` | `varchar(20)`, domyślnie `'nowe'` | `nowe` albo `obsluzone` — jedyne dwie wartości przyjmowane przez handler zmiany statusu. Ma własny indeks `KEY status`. |
| `utworzono` | `datetime` | Kiedy przyszło zgłoszenie. **Zgłoszenia NIE podlegają retencji 90 dni** — kasuje je właściciel ręcznie albo pełna deinstalacja. |

### 3.3. `{prefix}chatl_rozmowy` — historia wymian

| Kolumna | Typ | Po co jest |
|---|---|---|
| `id` | `bigint(20) unsigned`, PK, auto | Kolejność wymian w rozmowie; po nim sortuje widok rozmowy i porcjowanie eksportu CSV. |
| `sesja` | `varchar(64)` | Klucz grupujący wymiany w jedną rozmowę. Ma indeks `KEY sesja`, bo widok pojedynczej rozmowy filtruje właśnie po nim. |
| `rola` | `varchar(10)` | Kto się odezwał — **cztery** wartości: `gosc`, `bot`, `operator` (człowiek z obsługi po przejęciu rozmowy) i `system` (komunikat wtyczki, np. o dołączeniu konsultanta). Nazwę dla człowieka daje `chatl_nazwa_roli()` (`includes/silnik.php`) — **jedno** źródło dla eksportu RODO, eksportu CSV i widoku rozmowy w panelu; wcześniej każde z tych trzech miejsc miało własną mapę „wszystko poza gościem to Bot" i podpisywało wiadomości konsultanta jako bota. W panelu do nazwy roli dochodzi jeszcze `(AI)` / `(reguły)` przy wierszach bota — to informacja o użytym silniku, nie o tym, kto pisał. Statystyki i raport luk liczą wyłącznie `gosc` i `bot`, więc komunikaty systemowe nie zaburzają skuteczności. |
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

### 3.5. `{prefix}chatl_zdarzenia` — zdarzenia strażnika wejścia

| Kolumna | Typ | Po co jest |
|---|---|---|
| `id` | `bigint` AUTO_INCREMENT, PK | Jeden wiersz = jedno zdarzenie. To jest sedno: `INSERT` jest atomowy sam z siebie, więc N żądań naraz zapisuje N zdarzeń. Poprzednik (licznik w `wp_options`: `get_option` → `+1` → `update_option`) gubił 13 z 20 równoległych inkrementacji — i tym więcej, im większy ruch, czyli dokładnie wtedy, gdy liczba jest właścicielowi potrzebna. |
| `rodzaj` | `varchar(20)` | `pulapka` albo `nonce`. |
| `szczegol` | `varchar(40)` | Powód złapania (`ukryte pole` / `zbyt szybkie żądanie`). |
| `sciezka` | `varchar(20)` | Którędy przyszło: `pytanie` albo `zgloszenie`. Dzięki temu pasek w panelu mówi, **ilu** złapanych właściciel znajdzie na liście zgłoszeń — pułapka na ścieżce pytania nie tworzy zgłoszenia, bo gość nie zostawił kontaktu, a wcześniej pasek obiecywał wiersze, których nie ma. Z tego samego powodu zdarzenie ze ścieżki `zgloszenie` zapisujemy **dopiero po udanym `INSERT`** zgłoszenia: żądanie odrzucone na walidacji (zły adres, brak zgody, wyłączony czat, padnięty zapis) nie zostawia wiersza, więc nie może podbijać liczby w pasku. |
| `utworzono` | `datetime` (czas witryny) | Do paska („ostatnio") i do retencji — zdarzenia starsze niż `CHATL_RETENCJA_DNI` kasuje ten sam cron, co historię rozmów, więc tabela nie rośnie bez końca. Indeks `KEY rodzaj_utworzono`. |

Licznik w panelu to `COUNT(*)` z tej tabeli — **jedno** źródło prawdy, nie druga kopia liczby.

### 3.6. `{prefix}chatl_rate_limit` — licznik limitów na IP

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
| `chatl_wersja_schematu` | numer schematu, obecnie `8` | tak (domyślnie) | przy aktywacji i przy migracji schematu |
| `chatl_klucz_api` | klucz API właściciela | **nie** (`add_option(..., '', false)`) | przy zapisaniu klucza w panelu |
| `chatl_blad_maila` | `['kiedy' => data, 'adres' => e-mail]` | **nie** | gdy `wp_mail()` zwróci `false`; kasowana przy pierwszej udanej wysyłce |
| `chatl_blad_ai` | `['kiedy' => data, 'kod' => HTTP, 'tresc' => fragment]` | **nie** | gdy wywołanie API nie zwróci 200; kasowana przy pierwszym udanym wywołaniu |
| ~~`chatl_blad_nonce`~~ | **usunięta w schemacie 8** — odrzucone nonce'y i zadziałania pułapki leżą w tabeli `chatl_zdarzenia` (wiersz na zdarzenie). Licznik w opcji gubił równoległe zapisy i rozjeżdżał się z listą złapanych zgłoszeń. Podniesienie schematu kasuje starą opcję (razem z `chatl_pulapka`). | — | — |
| `chatl_rodo_sesje_wspolne` | lista identyfikatorów sesji, o których wiemy, że **były współdzielone** przez dwie osoby | **nie** | przy wniosku RODO o usunięcie dotyczącym takiej sesji; przycinana przy każdym zapisie (patrz 23.2) |

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
| `usun_dane` | `0` | Przełącznik ścieżki deinstalacji (rozdział **Cykl życia**). |
| `ai_informacje` | `''` | Opis firmy wstrzykiwany do instrukcji systemowej modelu. |
| `ai_limit_dzienny` | `100` | Globalny dobowy limit **wywołań** AI. Zapis ograniczany do zakresu 1–100000; jak przy limicie tokenów, **puste albo nieczytelne pole liczbowe NIE nadpisuje poprzedniej wartości** (`chatl_pole_liczbowe()`, audyt A5 KR1/KR2). |
| `ai_limit_tokenow` | `200000` | Globalny dobowy limit **tokenów płatnych** — drugi stop-loss. `0` = wyłączony (jawne zero z formularza; **puste albo nieczytelne pole liczbowe NIE nadpisuje poprzedniej wartości** i panel mówi to zamiast „zapisano" — `chatl_pole_liczbowe()`, audyt A5 KR1/KR2). |
| `kolor_glowny` | `#bfff38` | Bąbelek, dymki bota, przyciski, obwódka fokusu. Wartość spoza `#rrggbb` jest odrzucana i wraca domyślna. |
| `kolor_naglowka` | `#08090b` | Pasek z tytułem okna czatu i paski formularza kontaktu. |
| `kolor_tekstu` | `auto` | `auto` = wtyczka dobiera kolor tekstu osobno dla bąbelka i dla nagłówka, biorąc ten o lepszym kontraście. Wartość `#rrggbb` = ten sam kolor na obu. |
| `pozycja` | `prawo` | `prawo` albo `lewo` — róg strony, w którym stoi widget. |
| `za_proxy` | `0` | Czy ufać nagłówkom proxy przy ustalaniu adresu gościa. **Domyślnie wyłączone** — `X-Forwarded-For` da się podrobić. |
| `ai_model` | `''` | Model wybrany przez właściciela. **Pusty = użyj `CHATL_AI_MODEL`** — dzięki temu podniesienie domyślnego modelu w kolejnej wersji dociera do wszystkich, którzy go świadomie nie zmienili. |
| `adres_polityki` | `''` | Adres polityki prywatności linkowany przy formularzu. Pusty = sama informacja, bez linku. |

---

## 5. Stałe konfiguracyjne

Wszystkie liczby, którymi wtyczka się rządzi, są stałymi — nie ma ich rozsianych po kodzie.

| Stała | Wartość | Plik | Do czego |
|---|---|---|---|
| `CHATL_WERSJA` | `1.5.11` | `chatbot-live.php` | wersja wtyczki; służy też jako `?ver=` assetów (przeglądarka nie poda starego pliku po aktualizacji) |
| `CHATL_WERSJA_SCHEMATU` | `8` | `chatbot-live.php` | wyzwalacz migracji schematu |
| `CHATL_KATALOG`, `CHATL_URL` | ścieżka i adres wtyczki | `chatbot-live.php` | `require` i adresy assetów |
| `CHATL_MAX_PYTANIE` | `500` | `includes/front.php` | maksymalna długość pytania gościa (nadmiar jest **przycinany**, nie odrzucany) |
| `CHATL_MAX_WIADOMOSC` | `2000` | `includes/front.php` | maksymalna długość treści zgłoszenia (jw.) |
| `CHATL_LIMIT_NA_MIN` | `10` | `includes/front.php` | wspólny kubeł: żądań na minutę z jednego IP, łącznie dla pytań i zgłoszeń |
| `CHATL_RETENCJA_DNI` | `90` | `includes/front.php` | okres retencji historii rozmów — **jedno źródło** dla crona, tekstu w widgecie, panelu i eksportu |
| `CHATL_AI_LIMIT_IP_NA_DOBE` | `20` | `includes/front.php` | osobny, twardszy limit wywołań AI z jednego IP na dobę |
| `CHATL_AI_MODEL` | `claude-haiku-4-5` | `includes/ai.php` | **domyślny** model — alias BEZ daty, żeby wycofanie migawki nie zabiło trybu AI u wszystkich kupujących. Właściciel wybiera model w ustawieniach, integrator filtrem. |
| `CHATL_LIMIT_NONCE` | `20` | `includes/front.php` | odświeżeń zabezpieczenia formularza na minutę z jednego IP |
| `CHATL_NONCE_OKNO_DNI` | `7` | `includes/front.php` | ile dni panel pokazuje ostrzeżenie o odrzuconych zabezpieczeniach |
| `CHATL_AI_MAX_TOKENS` | `1024` | `includes/ai.php` | limit długości odpowiedzi modelu |
| `CHATL_AI_TIMEOUT` | `9` | `includes/ai.php` | timeout jednego wywołania w sekundach; z jednym ponowieniem daje maks. 18 s, z zapasem pod typowy limit 30 s na hostingu |
| `CHATL_AI_BRAK` | `BRAK_ODPOWIEDZI` | `includes/ai.php` | sentinel „model nie wie" — sygnał do zbiórki kontaktu zamiast zgadywania |
| `CHATL_AI_CACHE_PROG_ZNAKOW` | `16384` | `includes/ai.php` | od tylu znaków instrukcji dokładamy `cache_control`; poniżej progu dostawcy cache **po cichu nie działa** |
| `CHATL_AI_PAMIEC_WYMIAN` | `6` | `includes/ai.php` | ile ostatnich wymian bieżącej rozmowy trafia do wywołania modelu |
| `CHATL_AI_PAMIEC_ZNAKOW` | `600` | `includes/ai.php` | górny limit długości jednej wiadomości z historii |
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
| `CHATL_IMPORT_MAX_WIERSZY` | `2000` | `includes/import.php` | sufit wierszy jednego pliku CSV — powyżej odmowa z komunikatem zamiast padu na limicie pamięci |
| `CHATL_IMPORT_MAX_BAJTOW` | `2097152` | `includes/import.php` | sufit rozmiaru wgrywanego pliku (2 MB) |
| `CHATL_IMPORT_MINUT` | `15` | `includes/import.php` | ile żyje podgląd importu czekający na potwierdzenie |
| `CHATL_NA_STRONE_ZGLOSZEN` | `200` | `includes/admin.php` | rozmiar strony listy zgłoszeń |
| `CHATL_NA_STRONE_ROZMOW` | `100` | `includes/admin.php` | rozmiar strony listy rozmów |
| `CHATL_PULAPKA_POLE` | `chatl_pole_dodatkowe` | `includes/front.php` | nazwa ukrytego pola-pułapki; celowo nie brzmi jak `email`/`imie`, bo menedżery haseł wypełniają pola po nazwie |
| `CHATL_PULAPKA_SEKUND` | `2` | `includes/front.php` | poniżej tylu sekund od wydania sesji żądanie nie jest uznawane za pisane przez człowieka |
| `CHATL_LIMIT_PULAPKA` | `5` | `includes/front.php` | sufit ZŁAPANYCH zgłoszeń zapisywanych z jednego adresu w minucie |
| `CHATL_KLUCZ_API` | — | **`wp-config.php`, definiuje właściciel** | klucz API poza bazą; ma pierwszeństwo nad opcją |

---

## 6. Cykl życia

### 6.1. Aktywacja — `chatl_aktywacja()` (`includes/instalator.php`)

Po kolei, dokładnie to i nic więcej:

1. `chatl_utworz_tabele()` — pięć `dbDelta()`. Operacja jest **idempotentna**: przy reaktywacji
   nie kasuje danych, tylko dociąga brakujące kolumny i indeksy.
2. `update_option( 'chatl_wersja_schematu', CHATL_WERSJA_SCHEMATU )`.
3. `chatl_seed_demo()` — 10 par Q&A typowej polskiej firmy usługowej. **Bez wpisu powitalnego**: wpis z wariantami „dzień dobry"/„dobry wieczór" przejmuje pytania złożone („Dzień dobry, ile kosztuje wizyta?" → powitanie zamiast cennika), bo przy remisie trafień wygrywa dłuższy wariant. Gotowiec do wklejenia i ostrzeżenie o tym skutku są w instrukcji właściciela (rozdział o konfiguracji) — to jego decyzja, nie nasza domyślna. **Wchodzi wyłącznie do
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

**Schemat 8** (zdarzenia strażnika): zakłada `chatl_zdarzenia` i **kasuje** stare liczniki
z `wp_options` (`chatl_pulapka`, `chatl_blad_nonce`). Ich liczb nie da się przenieść na
wiersze zdarzeń — były zaniżone przez wyścig przy `update_option` — więc licznik w panelu
zaczyna po aktualizacji od zera. Zapisane zgłoszenia i ich flaga `podejrzane` zostają
nietknięte.

### 6.3. Deaktywacja — `chatl_deaktywacja()`

`wp_clear_scheduled_hook( 'chatl_czyszczenie_historii' )` — i tyle. **Dane zostają w komplecie**:
tabele, ustawienia, klucz API. Deaktywacja jest odwracalna bez strat.

### 6.4. Cron retencji — `chatl_czyszczenie_historii()`

Codziennie, cztery `DELETE`:

- z `chatl_rozmowy` wszystko starsze niż `CHATL_RETENCJA_DNI` dni,
- z `chatl_rate_limit` wiersze z `okno_do` w przeszłości,
- z `chatl_zdarzenia` zdarzenia strażnika (pułapka, odrzucony nonce) starsze niż
  `CHATL_RETENCJA_DNI` dni,
- z `chatl_sesje` wiersze, w których `aktywnosc` jest starsza niż `CHATL_RETENCJA_DNI`
  dni. Po `aktywnosc`, nie po `utworzono`: sesja, do której gość wraca po tygodniach,
  jest żywa i nie może zniknąć spod otwartego okna. Do audytu danych (A3-1) ta tabela
  nie miała **żadnej** ścieżki kasowania i rosła o wiersz na każde otwarcie widgetu.
  ⚠️ `aktywnosc` liczy ruch **gościa** (pytanie, odpytywanie); wiadomość operatora
  odświeża osobną kolumnę `operator_akt`. Rozmowa, w której przez cały okres retencji
  pisze wyłącznie operator, straci więc wiersz sesji, a jej wiadomości zostaną
  w historii bez wiersza w panelu rozmów żywych — przypadek egzotyczny (gość musiałby
  milczeć 90 dni przy piszącym konsultancie), nazwany tu wprost zamiast przemilczany.
  Próg liczymy `gmdate()` (UTC), tak samo jak sąsiednie kasowanie historii rozmów —
  różnica względem czasu witryny to offset strefy (w Polsce sesja żyje o 2 h dłużej);
  akceptujemy ją świadomie, żeby oba `DELETE` w tym cronie miały jedną podstawę czasu.

Granice retencji **rozmów, zdarzeń i sesji** liczymy w strefie witryny (`wp_date()`),
bo wszystkie trzy kolumny (`utworzono`, `utworzono`, `aktywnosc`) są zapisywane przez
`current_time( 'mysql' )`, czyli też w strefie witryny. Do audytu A5 (KR6) granica szła w UTC i próg przesuwał się
o offset strefy — w strefie ujemnej rozmowy ginęły godziny PRZED obiecanymi 90 dniami.
Okna rate-limitu zostają w UTC, bo `okno_do` jest zapisywane przez `gmdate()` — tam oba
końce są w tym samym układzie.

Wynik każdego zapytania jest sprawdzany; porażka trafia do trwałego śladu błędu bazy.
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
2. `DROP TABLE IF EXISTS` dla wszystkich siedmiu tabel (`chatl_qa`, `chatl_rozmowy`,
   `chatl_sesje`, `chatl_zgloszenia`, `chatl_rate_limit`, `chatl_ai_uzycie`,
   `chatl_zdarzenia`).
3. `delete_option` dla ośmiu opcji: `chatl_ustawienia`, `chatl_wersja_schematu`,
   `chatl_blad_maila`, `chatl_blad_ai`, `chatl_blad_bazy`, `chatl_rodo_sesje_wspolne`
   oraz dwóch liczników ze schematu ≤ 7 (`chatl_blad_nonce`, `chatl_pulapka`) — na
   wypadek instalacji, która nigdy nie weszła do panelu po aktualizacji.
4. Sprzątanie transientów `chatl_rl_*` ze **starszych wersji wtyczki** (≤ 1.1.x, gdy licznik
   limitów żył w opcjach zamiast w tabeli). Zapytanie używa `esc_like()` — podkreślnik jest
   symbolem wieloznacznym w `LIKE`, więc bez eskejpowania mogłoby złapać cudze wpisy.

Zdarzenie cron `chatl_czyszczenie_historii` zdejmuje **każda** ścieżka odinstalowania,
zanim jeszcze sprawdzimy opcję „usuń dane": zwykle robi to deaktywacja, ale wtyczkę da
się usunąć z jej pominięciem (skasowany katalog, „usuń" z listy) i wtedy `uninstall.php`
jest jedynym sprzątaczem. Zaplanowany zaczep bez kodu to nie są dane właściciela, tylko
wpis w `wp_options → cron`, w który WordPress uderza codziennie (A3-2).

Czego **żadna** ścieżka nie rusza: stałej `CHATL_KLUCZ_API` w `wp-config.php` — wtyczka nie
modyfikuje plików konfiguracyjnych.

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
| `chatl_nonce` | `chatl_ajax_nonce` | **brak (celowo)** | wydaje świeże zabezpieczenie gościowi, któremu strona przyszła z cache |

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
| `chatl_eksport_qa` | `chatl_post_eksport_qa` | `chatl_eksport_qa` |
| `chatl_qa_import_podglad` | `chatl_post_qa_import_podglad` | `chatl_qa_import_podglad` |
| `chatl_qa_import_zapisz` | `chatl_post_qa_import_zapisz` | `chatl_qa_import_zapisz` |
| `chatl_qa_import_anuluj` | `chatl_post_qa_import_anuluj` | `chatl_qa_import_anuluj` |
| `chatl_powitanie_zamknij` | `chatl_post_powitanie_zamknij` | `chatl_powitanie_zamknij` |
| `chatl_test_klucza` | `chatl_post_test_klucza` | `chatl_test_klucza` |
| `chatl_nonce_zamknij` | `chatl_post_nonce_zamknij` | `chatl_nonce_zamknij` |

Nonce operacji na pojedynczym wierszu zawiera jego `id` — nonce od cudzego wiersza nie przejdzie.
Wszystkie trzy eksporty przechodzą przez jedną bramkę `chatl_csv_brama()` (capability + nonce).

Import jest **dwukrokowy i nic nie zapisuje w kroku pierwszym**: `chatl_qa_import_podglad`
parsuje plik i odkłada wynik w transiencie `chatl_import_<id użytkownika>` (życie
`CHATL_IMPORT_MINUT`), a `chatl_qa_import_zapisz` zapisuje **wiersze z tego transientu** —
nie parsuje pliku po raz drugi, więc do bazy trafia dokładnie to, co pokazał podgląd.
Z formularza podglądu bierzemy wyłącznie poprawione słowa kluczowe; pytania i odpowiedzi
pochodzą z podglądu, więc podmiana pola w przeglądarce nie wprowadzi treści, której
właściciel nie widział. Plik jest czytany wprost z uploadu PHP i **nigdzie nie jest
zapisywany** (bez `wp_handle_upload`, żeby dane klienta nie zostawały w `uploads/`).

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
- licznik dobowy **da się odczytać** — nieudany odczyt `chatl_ai_uzycie` (odrzucone
  zapytanie: uszkodzona albo usunięta tabela; **albo brak połączenia z bazą**, gdy
  `wpdb::query()` wychodzi jeszcze przed ustawieniem `last_error`) to NIE to samo, co
  „brak wiersza na dziś": `chatl_ai_zuzycie_dnia()` wraca wtedy z flagą `blad`,
  a `chatl_ai_dostepny()` traktuje to jak wyczerpany limit (**fail-closed**: reguły,
  bez AI) i zostawia ślad w `chatl_blad_bazy`,
- globalny licznik dobowy jest poniżej `ai_limit_dzienny`,
- dobowy licznik tokenów płatnych jest poniżej `ai_limit_tokenow` (gdy ten > 0),
- kubeł tego IP nie przekroczył `CHATL_AI_LIMIT_IP_NA_DOBE`.

> **Dwa bezpieczniki kosztowe zachowują się przy awarii RÓŻNIE — i to jest świadome.**
> Globalny stop-loss (`chatl_ai_dostepny()`) przy nieczytelnym liczniku **wstrzymuje**
> tryb AI. Dobowy limit na IP (`chatl_limit_ai_ip_ok()` → `chatl_rate_limit( 'ai', … )`)
> jest **też** limitem kosztowym, ale dzieli ścieżkę awaryjną z limiterem spamu:
> gdy jego licznik padnie, **przepuszcza** (`chatl_rate_limit()` zwraca `true`).
> **Te dwie awarie są niezależne, bo liczniki leżą w INNYCH tabelach:** globalny
> stop-loss czyta `chatl_ai_uzycie`, limit per IP — `chatl_rate_limit`. Gdy padnie
> sama `chatl_rate_limit`, globalny stop-loss **działa dalej i jest otwarty**: do modelu
> idzie normalnie, znika tylko sufit na gościa, więc jeden adres może wtedy zjeść cały
> dobowy budżet witryny (nie więcej — globalny limit wywołań i limit tokenów obowiązują).
> Dopiero awaria obejmująca `chatl_ai_uzycie` (albo całej bazy) zamyka tryb AI.
> Zmiana zachowania limitera oznaczałaby, że awaria jego licznika **odcina czat**
> (nonce i walidacje wejścia bronią niezależnie), dlatego zostaje tak, jak jest.

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

Jeden mechanizm, cztery kubły — `chatl_rate_limit( $akcja, $prog, $okno_sekund )` w `includes/front.php`:

| Kubeł | Próg | Okno | Czego pilnuje |
|---|---|---|---|
| `chat` | `CHATL_LIMIT_NA_MIN` = 10 | minuta | zalew bazy i mail-bombing właściciela; wspólny dla pytań i zgłoszeń |
| `ai` | `CHATL_AI_LIMIT_IP_NA_DOBE` = 20 | doba | żeby jeden gość nie wypalił całego dobowego budżetu AI wszystkich |
| `pulapka` | `CHATL_LIMIT_PULAPKA` = 5 | minuta | żeby złapany bot nie zalał tabel zdarzeniami i zgłoszeniami „podejrzanymi" |
| `nonce-odrzucony` | `CHATL_LIMIT_NA_MIN` = 10 | minuta | żeby odrzucone zabezpieczenie nie było darmową drogą do zapisu w bazie |

Kubły `pulapka` i `nonce-odrzucony` są **osobne** od `chat` z tego samego powodu: złapany bot
ani gość ze stroną z cache nie mogą zjadać limitu prawdziwego klienta.

> ⛔ **Kubeł `nonce-odrzucony` ogranicza ZAPIS, nie odpowiedź.** Powyżej progu wtyczka
> odpowiada **tak samo** — z `kod => 'nonce'` — tylko nie dopisuje zdarzenia. Widget rozpoznaje
> zły nonce wyłącznie po tym kluczu i tylko po nim dociąga świeży nonce i ponawia pytanie
> (samonaprawa z sekcji o L1). Twarda odmowa powyżej progu wyłączyłaby ją dokładnie tam, gdzie
> jest najpotrzebniejsza: za proxy z wyłączonym zaufaniem nagłówkom **wszyscy goście dzielą
> jeden kubeł**, więc przy stronie z cache próg wyczerpuje się w sekundy.

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
cichu, test w przeglądarce ustawia kolory, czyta liczbę policzoną w panelu,
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

## 14. Sygnał, gdy tryb AI przestaje działać

Przed 1.4.2 nieudane wywołanie API szło **wyłącznie** do logu przez
`wp_trigger_error()`. Na produkcji `WP_DEBUG_LOG` jest wyłączony, więc zły albo
wygasły klucz oznaczał, że bot po cichu odpowiada tylko z reguł — w nieskończoność.
Właściciel płacił za tryb AI, którego nie ma, i **nie miał jak tego zauważyć**.

| Element | Gdzie | Co robi |
|---|---|---|
| `chatl_odnotuj_blad_ai()` | `includes/ai.php` | stawia znacznik `chatl_blad_ai` (czas, kod HTTP, przycięty do 300 znaków fragment odpowiedzi) przy każdym nieudanym wywołaniu |
| `chatl_odnotuj_sukces_ai()` | `includes/ai.php` | zdejmuje znacznik po pierwszym udanym wywołaniu — **tylko gdy znacznik istnieje**, żeby udane pytanie gościa nie kończyło się zapisem do bazy |
| `chatl_notice_bledu_ai()` | `includes/admin.php` | czerwony pasek w panelu: kiedy, kod HTTP, co to znaczy i co zrobić |
| `chatl_ai_test_klucza()` | `includes/ai.php` | jedno **tanie** wywołanie na żądanie właściciela |
| `chatl_post_test_klucza()` | `includes/admin.php` | akcja `admin-post` za przyciskiem „Przetestuj klucz" |

**Przycisk sprawdza TĘ konfigurację, która obsługuje gości.** Model bierze
`chatl_ai_model()` (ustawienie właściciela → stała produktu → filtr), a nie samą
stałą `CHATL_AI_MODEL`. Do naprawy P2-Z1 diagnostyka pomijała pole „własny model":
właściciel z literówką w nazwie widział „Klucz działa", a bot po cichu odpowiadał
z reguł — wtyczka produkowała fałszywy dowód poprawności. Udany test gasi czerwony
pasek `chatl_blad_ai` i jest to uczciwe **dlatego**, że pyta tym samym kluczem
i modelem co odpowiedź dla gościa.

Wzorzec jest ten sam co przy nieudanym powiadomieniu mailowym
(`chatl_odnotuj_wysylke_powiadomienia()`): znacznik stawiany przy porażce,
zdejmowany przy pierwszym sukcesie. Oba znaczniki idą przez `chatl_zapisz_opcje()`
(wynik **mierzony** odczytem opcji po zapisie, bliźniak `segm_zapisz_opcje()`
z Segregatora) — gdy sam znacznik padnie po cichu, jego porażka trafia do trwałego
śladu błędu bazy (`chatl_blad_bazy`, pasek w panelu). Bez tego znacznik awarii,
który jest jedynym sygnałem cichego zjazdu do reguł, sam milczałby przy awarii bazy
(audyt A2, znalezisko F4).

**HTTP 200 to jeszcze nie sukces.** Ciało odpowiedzi bywa nie do odczytania:
proxy albo brama zgodności podmieniła treść, hosting dokleił swój HTML, model
zwrócił bloki bez tekstu. Dla właściciela skutek jest **identyczny jak przy złym
kluczu** — płaci za tryb AI, a bot odpowiada z reguł — więc te ścieżki zostawiają
ślad tak samo. Panel rozpoznaje ten przypadek po kodzie `200` w znaczniku i daje
inną radę niż przy odrzuconym kluczu: połączenie i uwierzytelnienie zadziałały,
popsuła się treść.

Żeby ta klasa błędu nie wróciła, **wszystkie** ścieżki zjazdu do reguł przechodzą
przez jedną funkcję `chatl_ai_niepowodzenie()`, a bramka pilnuje, że w
`chatl_ai_odpowiedz()` nie ma ani jednego gołego `return null`. Odmowa modelu
(`stop_reason: refusal`) **nie** jest awarią — to poprawne „nie wiem" plus zbiórka
kontaktu, więc znacznika nie stawia.

**Test klucza celowo nie używa `chatl_ai_instrukcja()`.** Tamta wkłada do żądania
całą aktywną bazę Q&A i opis firmy, więc „sprawdzenie klucza" kosztowałoby tyle,
co prawdziwe pytanie gościa. Test wysyła jedno słowo z `max_tokens = 1`
i nie rusza dziennego licznika (`chatl_ai_zuzyj()` woła wyłącznie
`chatl_ai_odpowiedz()`). Sprawdza klucz, dostępność API i nazwę modelu —
nie jakość odpowiedzi.

Komunikaty rozróżniają przyczyny, bo prowadzą do różnych działań: `401`/`403`
to zły lub wygasły klucz, `404` to nieznana nazwa modelu, `429` to limit
u dostawcy (klucz działa), błąd połączenia to problem z siecią hostingu.

## 15. Adres IP gościa a proxy

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

**Warunek, bez którego przełącznik nie daje tego, co obiecuje.**
`CF-Connecting-IP` jest wiarygodny wyłącznie dlatego, że Cloudflare
**nadpisuje** ten nagłówek na wejściu. Jeżeli origin jest osiągalny bezpośrednio —
typowe na hostingu współdzielonym bez reguł zapory — żądanie z podrobionym
`CF-Connecting-IP` omija Cloudflare w całości i nikt go po drodze nie poprawia.
Napastnik nie musi wtedy znać niczyjego adresu: wystarczy inna zmyślona wartość
przy każdym żądaniu, żeby kubeł limitu był za każdym razem nowy. Wtyczka **nie
weryfikuje** pochodzenia żądania (allowlista zakresów Cloudflare wymagałaby
aktualizowanej listy adresów i jest poza zakresem tej wersji), więc warunek jest
po stronie wdrożenia: **włączaj przełącznik tylko wtedy, gdy origin nie przyjmuje
połączeń z pominięciem proxy** (zapora hostingu ogranicza ruch do adresów
Cloudflare). Panel mówi to właścicielowi wprost, a nie tylko ta dokumentacja.

## 16. Wybór modelu

`CHATL_AI_MODEL` to **alias bez daty** (`claude-haiku-4-5`), nie migawka
(`claude-haiku-4-5-20251001`). Identyfikator z datą wskazuje konkretną migawkę
modelu, a te bywają wycofywane — wtedy **każda sprzedana kopia traci tryb AI
naraz**, a jedyną drogą naprawy jest ręczna wysyłka zipa mailem, bo nie mamy
serwera aktualizacji.

**Sprawdzone u dostawcy 2026-08-20** (zamknięte dowodem, nie dyskusją):
jedno wywołanie API na każdy identyfikator —
`claude-haiku-4-5` odpowiedział **HTTP 200**, a w polu `model` odpowiedzi zwrócił
`claude-haiku-4-5-20251001`; identyfikator z datą zachował się tak samo. Nazwa bez
daty jest więc **prawdziwym aliasem, który serwer sam rozwija** do bieżącej migawki
— dokładnie to, na czym opiera się ta sekcja. Wartość domyślna zostaje bez zmian.

Zastrzeżenie: **alias bez daty to konwencja dostawcy,
nie gwarancja** — nie każdy model musi go mieć, a wtyczka nie ma jak sprawdzić
listy identyfikatorów w locie. Dlatego pole „własny model" przyjmuje dowolną
nazwę, a panel i FAQ mówią: użyj nazwy bez daty **jeśli dostawca taką podaje**.
Siatką bezpieczeństwa jest rozdział **Sygnał, gdy tryb AI przestaje działać**: nieznana nazwa modelu daje HTTP 404, który od
1.4.2 stawia znacznik i zapala w panelu czerwony pasek z komunikatem wprost
(„API nie zna tego modelu"). Zły identyfikator jest więc **widoczny od pierwszego
pytania gościa**, a nie cichy — to różnica między usterką do poprawienia w minutę
a awarią, której właściciel nie zauważa miesiącami.

Model ustala `chatl_ai_model()` w trzech poziomach, bo mają trzech różnych adresatów:

| Poziom | Dla kogo | Uwaga |
|---|---|---|
| ustawienie `ai_model` | **kupujący** | nie pisze filtrów; wybiera z listy albo wpisuje własny identyfikator |
| stała `CHATL_AI_MODEL` | produkt | wartość domyślna |
| filtr `chatl_ai_model` | integrator | ostatnie słowo (np. proxy z własnym nazewnictwem) |

Pusta wartość ustawienia znaczy „użyj domyślnej produktu" — dlatego wybranie
z listy modelu równego `CHATL_AI_MODEL` **nie zapisuje niczego na sztywno**.
Puste wyjście z filtru też wraca do stałej: żądanie do API nigdy nie idzie
z pustym polem `model`.

Lista w ustawieniach jest krótka i **bez cen**: cennik dostawcy się zmienia,
a wtyczka nie ma jak go odświeżyć — wpisana kwota zestarzałaby się w paczce
u klienta. Zamiast tego panel podaje kolejność „taniej ↔ mocniej" i odsyła do
dokumentacji dostawcy. Pole „własny model" jest po to, żeby lista nigdy nie
była więzieniem; wpisana wartość jest przycinana do liter, cyfr, myślników
i kropek.

## 17. Kiedy gość dowiaduje się o zapisie rozmowy

Do 1.4.5 informacja o przetwarzaniu danych szła **wyłącznie** do formularza
kontaktu, a samo pisanie na czacie już lądowało w `chatl_rozmowy`. Gość
dowiadywał się o zapisie dopiero, gdy zostawiał maila — a wtedy część rozmowy
była zapisana od kilku minut. Wtyczkę kupuje wiele firm i **każda staje się
administratorem danych**, więc informacja musi być przed pierwszym słowem.

Widget rysuje notę (`.chatl-nota`) jako **pierwszy element listy wiadomości**,
przed powitaniem bota. Treść i okres retencji pochodzą z `CHATL_RETENCJA_DNI`,
więc nie da się ich rozjechać z tym, co naprawdę kasuje cron. Gdy właściciel
podał adres polityki prywatności, nota kończy się odnośnikiem
(`target="_blank"` + `rel="noopener"`); bez adresu nie ma pustego linku.

Nota **nie jest dymkiem bota** — to informacja właściciela strony, nie wypowiedź
w rozmowie. Powstaje z węzłów tekstowych i elementu `a`, bez `innerHTML` —
tak jak reszta widgetu.

Formularz kontaktu **zachowuje** swoją własną, pełniejszą informację (co, po co,
jak długo) razem z wymaganym checkboxem zgody — tam zbierany jest adres e-mail,
więc wymóg jest mocniejszy niż przy samej rozmowie.

## 18. Tokeny i koszt trybu AI

Rachunek u dostawcy modelu idzie z **tokenów**, nie z liczby wywołań. Instrukcja
systemowa (`chatl_ai_instrukcja()`) wkłada do żądania **całą aktywną bazę Q&A**
i opis firmy przy **każdym** pytaniu, więc klient z 200 parami płaci wielokrotność
klienta z 10 parami przy tym samym „limicie 100 wywołań na dobę". Do 1.4.6
w panelu nie było ani jednej liczby o koszcie.

### 18.1. Co zapisujemy

Tabela `chatl_ai_uzycie` ma cztery liczniki tokenów obok licznika wywołań.
Nazwy pól odpowiadają polu `usage` odpowiedzi Messages API:

| Kolumna | Pole `usage` |
|---|---|
| `tokeny_wejscia` | `input_tokens` |
| `tokeny_wyjscia` | `output_tokens` |
| `tokeny_cache_zapis` | `cache_creation_input_tokens` |
| `tokeny_cache_odczyt` | `cache_read_input_tokens` |

Inkrement idzie jednym atomowym `INSERT … ON DUPLICATE KEY UPDATE`, razem
z licznikiem wywołań.

**Liczymy po kodzie 200, a nie po udanej odpowiedzi.**
Kod 200 znaczy, że dostawca wywołanie **wykonał i policzył** — rachunek przychodzi
niezależnie od tego, czy treść da się użyć. Naliczanie dopiero przy gotowym tekście
zostawiało trzy ścieżki, które kosztowały właściciela, a były niewidoczne dla
**obu** stop-lossów naraz:

| Ścieżka | Co się dzieje | Zużycie | Znacznik awarii |
|---|---|---|---|
| odmowa modelu (`stop_reason: refusal`) | gość dostaje odpowiedź z reguł | **liczone** (tokeny z `usage`) | **nie** — to poprawne działanie produktu (rozdział **Sygnał, gdy tryb AI przestaje działać**) |
| bloki bez tekstu (pusto po `trim()`) | zjazd do reguł | **liczone** | tak |
| ciało nie do odczytania przy 200 | zjazd do reguł | **samo wywołanie**, tokenów nie ma skąd wziąć | tak |

Naliczanie stoi **w jednym miejscu, przed rozgałęzieniem** na te przypadki — ten
sam zabieg co `chatl_ai_niepowodzenie()` z rozdziału **Sygnał, gdy tryb AI przestaje działać**: dopisanie kolejnego wyjścia
z funkcji nie może już zgubić kosztu przez przeoczenie. Bramka zgodności pilnuje
obu warunków (jedno wywołanie `chatl_ai_zuzyj()`, i to przed rozgałęzieniem).

**Tokeny płatne** (`chatl_ai_tokeny_dzis()`) to suma wejścia, wyjścia i zapisu do
cache. Odczyt z cache trzymamy **osobno**, bo jest wielokrotnie tańszy — wrzucenie
go do jednej sumy zawyżałoby obraz kosztu i przedwcześnie wyłączałoby tryb AI.

### 18.2. Drugi stop-loss

`chatl_ai_dostepny()` sprawdza teraz trzy rzeczy: klucz, dobowy limit **wywołań**
i dobowy limit **tokenów** (`ai_limit_tokenow`, domyślnie 200 000; `0` = wyłączony).
Po wyczerpaniu któregokolwiek bot do północy odpowiada z reguł — tak samo jak
wcześniej po wyczerpaniu limitu wywołań, więc dla gościa nic się nie zmienia.

Wartość domyślna to mniej więcej tyle, ile zużyje 100 wywołań (domyślny limit
wywołań) przy typowej bazie Q&A małej firmy. Zaczyna więc gryźć dopiero wtedy,
gdy **pojedyncze** wywołanie jest nienormalnie drogie — czyli dokładnie
w przypadku, którego limit wywołań w ogóle nie widzi.

### 18.3. Cache instrukcji — warunkowo, nie na ślepo

`system` jest wysyłane jako **tablica bloków** (wymóg API dla cache), a
`cache_control: {"type":"ephemeral"}` dokładamy **tylko** gdy instrukcja ma co
najmniej `CHATL_AI_CACHE_PROG_ZNAKOW` znaków.

Powód jest twardy: dostawca włącza cache instrukcji dopiero od **4096 tokenów**
dla modelu domyślnego, a poniżej progu `cache_control` jest **po cichu ignorowane**
— bez błędu i bez sygnału. Typowa baza Q&A małej firmy jest krótsza, więc **cache
nie jest tu ratunkiem na koszt**; ratunkiem jest limit tokenów i pokazanie zużycia.

Liczymy **znaki**, nie tokeny, bo policzenie tokenów wymagałoby osobnego wywołania
API przy każdym pytaniu. Przelicznik 4 znaki na token jest **zaniżony celowo**
(polski tekst to bliżej 3 znaków na token), więc 16 384 znaki to na pewno więcej
niż 4096 tokenów. Wolimy nie włączyć cache tam, gdzie by zadziałał, niż włączyć
go tam, gdzie po cichu nie działa.

### 18.4. Gdzie to widać

Strona **Statystyki i luki** pokazuje zużycie tokenów w wybranym okresie
(`chatl_statystyki_tokenow()`), a **Ustawienia** — zużycie dzisiejsze obok pola
limitu. Wtyczka **nie podaje kwot**: cennik dostawcy się zmienia, a ona nie ma jak
go odświeżyć — wpisana kwota zestarzałaby się w paczce u klienta.
## 19. Sygnał „bot pisze"

Tryb AI ma timeout `CHATL_AI_TIMEOUT` sekund **plus jedno ponowienie**, czyli do
18 sekund ciszy. Do 1.4.8 pole i przycisk nie były blokowane i nie było żadnego
wskaźnika — gość uznawał, że się zawiesiło, i klikał drugi raz. Każde kliknięcie
to kolejne wywołanie modelu **na rachunku właściciela**.

Po wysłaniu pytania widget: blokuje pole i przycisk, dokłada dymek `.chatl-pisze`
z trzema kropkami, a po odpowiedzi (albo po błędzie) sprząta jedno i drugie.

Trzy rzeczy warte uwagi:

- **Blokada jest jednocześnie strażą przed podwójnym wysłaniem.** Obsługa
  `submit` sprawdza `wyslijBtn.disabled` i wychodzi — dzięki temu nawet zdarzenie
  wywołane z pominięciem interfejsu (skrypt, klawiatura, powtórzony `submit`)
  nie wyśle drugiego żądania.
- **Sprzątanie idzie w `finally`, nie w `then`.** Po nieudanym żądaniu gość
  **też** musi odzyskać pole — inaczej jedna wpadka sieci zamyka mu czat na stałe.
- **Dymek jest elementem listy wiadomości**, nie nakładką: przewija się razem
  z rozmową i znika przez usunięcie węzła. Ma `aria-live="polite"` i etykietę,
  więc osoba korzystająca z czytnika ekranu dostaje ten sam sygnał co widząca.

Animacja to czysty CSS (bez bibliotek i bez obrazków, widget nie pobiera niczego
z sieci), a przy systemowym ustawieniu „ogranicz animacje" (`prefers-reduced-motion`,
WCAG 2.3.3) kropki zostają widoczne i przestają tylko skakać.

---


## 20. Pamięć bieżącej rozmowy w trybie AI

Do 1.4.7 `messages` w żądaniu miało **dokładnie jeden element** — samo ostatnie
pytanie. Gość pytał „jak dojechać", dostawał odpowiedź, dopytywał „gdzie
zaparkować" — a model nie wiedział, o czym mowa, choć wizualnie to jedna rozmowa.

⚠️ **To nie była nasza świadoma granica.** Poza zakresem została pamięć **między
wizytami** (sesja żyje w karcie przeglądarki i tak zostaje); tu chodziło o pamięć
**w trakcie jednej rozmowy**, czyli o coś, czego gość ma prawo oczekiwać.

`chatl_ai_historia( $sesja, $przed_id )` zwraca ostatnie `CHATL_AI_PAMIEC_WYMIAN`
wymian tej rozmowy w kolejności chronologicznej, jako wiadomości `user`/`assistant`.

⚠️ **Bierze WSZYSTKIE role, nie tylko gościa i bota.** Wiersze `operator` (człowiek
z obsługi po przejęciu rozmowy) i `system` (komunikat o dołączeniu konsultanta) idą
do modelu jako `assistant` — bez nich model nie zrozumiałby dopytania po rozmowie
z człowiekiem. Skutek jest jednak taki, że **wiadomości konsultanta opuszczają
WordPressa** przy następnym pytaniu gościa, jeśli rozmowa wróciła do bota. Instrukcja
właściciela mówi to wprost w rozdziale **„Deklaracja prywatności"**.

Cztery rzeczy, które ta funkcja musi robić dobrze:

1. **Nie dublować bieżącego pytania.** Jest ono zapisywane do historii **przed**
   wywołaniem modelu, więc bierzemy wyłącznie wiersze o `id` mniejszym niż
   właśnie zapisany — inaczej model dostałby to samo pytanie dwa razy.
2. **Nie przeciekać między rozmowami.** Filtr po `sesja` jest tu granicą
   prywatności, nie optymalizacją: wymiany innego gościa nie mają prawa trafić
   do cudzego żądania.
3. **Nie rosnąć bez końca.** Sufit to `CHATL_AI_PAMIEC_WYMIAN` wymian, a każda
   wiadomość jest przycinana do `CHATL_AI_PAMIEC_ZNAKOW` znaków — pytanie gościa
   jest już ograniczone, ale odpowiedź bota bywa długa i jedna rozwlekła
   wypowiedź z przeszłości potrafiłaby zdominować żądanie.
4. **Zachować kształt rozmowy.** Wiadomości tej samej strony pod rząd (np. gdy
   zapis odpowiedzi bota padł) są scalane, a wiodąca wypowiedź bota usuwana —
   rozmowa wysyłana do modelu zaczyna się od gościa.

**Skąd sześć wymian.** Tyle wystarcza na realny ciąg dopytań („jak dojechać" →
„gdzie zaparkować" → „a w sobotę?"), a każda wymiana to tokeny właściciela.
Przy krótkich wiadomościach czatu sześć wymian to rząd 300–600 tokenów dołożonych
do żądania, które i tak niesie całą bazę Q&A w instrukcji systemowej — na stacku
warsztatowym sama instrukcja ma 3651 znaków, czyli ok. 1200 tokenów. Wyżej rośnie
rachunek, a nie jakość: gość firmowego czatu rzadko wraca do czegoś sprzed
dziesięciu wiadomości.
## 21. Zabezpieczenie formularza a cache strony

Nonce wchodzi do HTML-a strony przez `wp_localize_script()`, a wtyczka albo usługa
cache (LiteSpeed, WP Rocket, W3TC, Cloudflare) potrafi serwować ten HTML **dłużej,
niż nonce żyje** (12–24 h). Efekt przed 1.4.1: `check_ajax_referer()` kończył
żądanie odpowiedzią `-1`, `odp.json()` dawało **liczbę**, więc `odp.success` było
`undefined` i gość dostawał „Coś poszło nie tak" przy **każdym** pytaniu. Czat nie
działał u nikogo, a właściciel nie miał o tym pojęcia.

Naprawa ma trzy części, bo awaria miała trzy poziomy:

1. **Serwer odmawia rozpoznawalnie.** `chatl_wymagaj_nonce()` woła
   `check_ajax_referer( …, false )` — bez ubijania żądania — i przy porażce
   odpowiada własnym JSON-em z `data.kod = 'nonce'`. Dopiero wtedy jest gdzie
   odnotować awarię.
2. **Widget ratuje rozmowę sam.** `wyslij()` w `assets/widget.js` po odpowiedzi
   z kodem `nonce` (albo po surowym `-1`, które po sparsowaniu nie ma pola
   `success`) dociąga świeże zabezpieczenie publicznym endpointem `chatl_nonce`
   i powtarza żądanie **dokładnie raz**. Druga porażka to już prawdziwa awaria
   i wtedy gość ma prawo zobaczyć komunikat.
3. **Właściciel to widzi.** Odrzucenie dopisuje wiersz do **tabeli**
   `chatl_zdarzenia` (rodzaj `nonce`); panel pokazuje czerwone ostrzeżenie z liczbą,
   przyczyną i tym, co zrobić. Ostrzeżenie cichnie po `CHATL_NONCE_OKNO_DNI` dniach
   bez nowych odrzuceń albo po kliknięciu „ukryj".
   ⚠️ **Ta liczba jest DOLNYM ograniczeniem**, nie liczbą odrzuceń: zapis jest ucięty
   kubłem `nonce-odrzucony` do `CHATL_LIMIT_NA_MIN` na minutę z adresu, żeby ścieżka
   odrzucenia nie była darmową drogą do bazy. Ma to znaczenie **za proxy**, gdzie
   wszyscy goście dzielą jeden adres: przy masowej awarii cache liczba rośnie
   najwyżej o 10 na minutę, choć odrzuceń jest wielokrotnie więcej. Sama
   samonaprawa widgetu limitowi **nie podlega** — odpowiedź z `kod = 'nonce'`
   idzie zawsze (patrz ramka o kubłach w rozdziale o limitach).

**Dlaczego endpoint `chatl_nonce` nie wymaga nonce'a:** jego jedynym zadaniem
jest wydanie zabezpieczenia komuś, kto ma nieważne. Nie osłabia to niczego —
ten sam token każdy odczyta, wchodząc na stronę — a `wp_create_nonce( 'chatl_front' )`
jest ważny wyłącznie dla dwóch publicznych akcji czatu i nie otwiera niczego,
czego gość nie mógłby zrobić i tak. Ochroną jest osobny kubeł limitu na IP
(`CHATL_LIMIT_NONCE`).

Znacznika **nie kasujemy przy udanym żądaniu** — to byłby zapis do bazy na
ścieżce każdego pytania gościa. Dlatego ostrzeżenie wygasa czasem, a nie
sukcesem.

---

## 22. Eksport CSV

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

## 23. Przejęcie rozmowy przez człowieka

Bot nazywa się LIVE, więc rozmowę musi dać się przejąć. Model stanu i tempo
odpytywania wzięte z rozpoznania wzorców rynkowych (Chatwoot, Crisp, Tidio).

### 23.1. Stan rozmowy — jedno pole

Za Chatwootem: **jedno** pole `stan` w `chatl_sesje` (`bot` albo `czlowiek`), a nie
dwa niezależne przełączniki, które prędzej czy później się rozjadą i zostawią
rozmowę bez żadnej ze stron. Stan czyta zarówno ścieżka pytania gościa, jak
i punkt odpytywania.

| Przejście | Kto wywołuje | Co widzi gość |
|---|---|---|
| `bot` → `czlowiek` | przycisk **Przejmij** w panelu | „Do rozmowy dołączył konsultant…" |
| `czlowiek` → `bot` | przycisk **Zakończ** | „Konsultant zakończył rozmowę…" |
| `czlowiek` → `bot` | cisza operatora > `CHATL_PRZEJECIE_CISZA` | „Konsultant nie odpowiada…" |

Powrót po ciszy liczy się **przy każdym wejściu do rozmowy**, a nie cronem —
działa więc także tam, gdzie WP-Cron jest wyłączony.

Każde przejście jest **mierzone odczytem `stan` po zapisie**, nie zakładane:
`$wpdb->update()` zwraca `0`, nie `false`, gdy żaden wiersz nie pasował, więc sam
wynik zapisu nie mówi, co leży w bazie. Gdy baza odrzuci zapis, `chatl_przejmij()` /
`chatl_oddaj_botowi()` zwracają `false`, gość **nie** dostaje komunikatu o zmianie
rozmówcy (nie czyta „dołączył konsultant" do rozmowy, w której dalej odpowiada bot),
operator widzi w panelu komunikat błędu zamiast „przejęta"/„oddana", a porażka
zostawia ślad w `chatl_blad_bazy`. Powrót po ciszy przy odrzuconym zapisie zwraca
stan **z bazy** (`czlowiek`) i próbuje ponownie przy następnym wejściu. Ten sam
wzorzec obowiązuje w `chatl_nowa_sesja()` (odrzucony INSERT = brak sesji i brak
ciastka, widget dostaje błąd zamiast identyfikatora widma) i w zapisie ustawień
oraz klucza API (`chatl_zapisz_klucz()` zwraca `bool`; „zapisano" dopiero po
zmierzonym zapisie — audyt A2, znaleziska F1–F3).

### 23.2. Bot milczy — i to jest sedno

Po przejęciu pytanie gościa **jest zapisywane** (operator musi je przeczytać),
ale nie idzie ani do modelu, ani do reguł: zero kosztu API i żadnej odpowiedzi
automatu wpadającej człowiekowi w słowo. Crisp ma u siebie udokumentowane
zachowanie odwrotne — wiadomość operatora nie przerywa scenariusza bota —
dlatego strażnik sprawdza **oba** kierunki: milczenie po przejęciu i powrót
głosu po zakończeniu.

### 23.3. Dostęp do rozmowy: dwa składniki

Punkt `POST /wp-json/chatl/v1/rozmowa` jest publiczny, więc dostęp stoi na dwóch
rzeczach naraz:

1. **identyfikator sesji** — wydaje go **serwer** (`wp_generate_password( 32, false )`).
   Wcześniej wymyślała go przeglądarka, ze ścieżką zapasową opartą o `Math.random()`,
   który nie jest generatorem kryptograficznym;
2. **sekret z ciastka `HttpOnly`** (`chatl_k_<sesja>`, `SameSite=Lax`, `Secure` przy SSL),
   porównywany `hash_equals()` z `wp_hash()` w bazie.

Sam identyfikator — **nawet poprawny** — nie otwiera rozmowy. Odpowiedź na brak
sekretu jest **nieodróżnialna** od odpowiedzi na nieznaną sesję, więc zgadujący
nie ma wyroczni. Funkcja autoryzująca jest **zamknięta domyślnie**: wiersz bez
sekretu to sesja, której serwer nigdy nie wydał, więc nie ma czego otwierać —
a strażnik pilnuje, że takich wierszy w tabeli nie ma.

Sekretu wymaga **także zapis** (pytanie gościa), nie tylko odczyt: bez tego ktoś,
kto zdobył sam identyfikator, mógłby podszyć się pod gościa i wprowadzić operatora
w błąd. Cena płacona świadomie: przeglądarka z zablokowanymi ciastkami pierwszej
strony nie utrzyma sesji — widget prosi wtedy o nową i ponawia **raz**.

### 23.4. Rytm odpytywania

Interwał podaje **serwer** w każdej odpowiedzi, więc zmiana rytmu nie wymaga
wysyłania klientom nowego JS.

| Sytuacja | Interwał |
|---|---|
| okno czatu zamknięte | **0 zapytań** |
| rozmawia bot | `CHATL_ODPYT_BOT` (15 s) |
| przejęta, karta widoczna | `CHATL_ODPYT_CZLOWIEK` (5 s) |
| przejęta, karta schowana | `CHATL_ODPYT_UKRYTA` (30 s) |
| przejęta, cisza > 2 min | `CHATL_ODPYT_CISZA` (120 s) |

Liczby, dlaczego akurat takie i co robi odpytywanie co sekundę przy 100 gościach —
w pliku rozpoznania i w opisie PR.

**Sterowanie odpytywaniem z zewnątrz — dwa mosty.** `odpytaj()` i
`zatrzymajOdpytywanie()` żyją w domknięciu `zbudujOkno()` (widzą sesję, listę
wiadomości i pasek rozmówcy), a startować i zatrzymywać trzeba je także spoza
niego: z bąbelka i z obsługi klawisza Escape. Służą do tego dwie zmienne
ustawiane w jednym miejscu — `startOdpytywania` i `stopOdpytywania`. Do audytu A4
bąbelek wołał `odpytaj` po nazwie, spoza zasięgu: przeglądarka zgłaszała
`ReferenceError`, obsługa kliknięcia przerywała się w połowie i **odpytywanie
nie startowało wcale** — gość nie widział ani komunikatu o przejęciu rozmowy,
ani wiadomości operatora, dopóki sam czegoś nie napisał. Bąbelek niesie też
`aria-expanded` (stan czatu dla czytnika ekranu); etykieta `aria-label`
pozostaje stała, bo po niej trafiają w bąbelek testy i użytkownicy czytników.

### 23.5. Panel operatora

`includes/operator.php`, podstrona **Rozmowy na żywo**. Lista pokazuje, kto pisze
(bot czy człowiek), ostatnie pytanie gościa, ile czeka i liczbę wiadomości —
jednym zapytaniem z podzapytaniami, nie pętlą po sesjach. Każdy punkt operatora
ma `manage_options` **i** nonce. Pisanie do rozmowy, której się nie przejęło,
jest odrzucane: gość dostawałby wtedy dwa głosy naraz.


## 24. Narzędzia RODO WordPressa

WordPress ma wbudowany kreator (**Narzędzia → Eksport / Usuwanie danych osobowych**):
przyjmuje wniosek, weryfikuje adres mailem, buduje paczkę ZIP i prowadzi dziennik
żądań. Wtyczka trzymająca maile gości i treść rozmów, a niewpięta w ten kreator,
zostawia właściciela z obowiązkiem i bez narzędzia.

| Zaczep WordPressa | Nasza funkcja | Co robi |
|---|---|---|
| `wp_privacy_personal_data_exporters` | `chatl_rodo_eksporter()` | wydaje zgłoszenia i rozmowy powiązane **wyłącznie** z tym adresem |
| `wp_privacy_personal_data_erasers` | `chatl_rodo_kasownik()` | kasuje zgłoszenia, rozmowy **i wiersze sesji** wnioskodawcy; komunikat podaje liczby skasowanych zgłoszeń i wiadomości (wiersz sesji jest nośnikiem technicznym bez treści, więc nie jest osobno liczony) |
| `wp_add_privacy_policy_content()` | `chatl_polityka_prywatnosci()` | gotowy akapit do polityki prywatności |

### 23.1. Granica wiązania: adres e-mail

Kluczem jest adres, a to wyznacza granicę. Zgłoszenia mają e-mail wprost; historia
rozmów **nie** — wiąże się z osobą wyłącznie przez sesję, w której ta osoba zostawiła
zgłoszenie. **Rozmowa gościa, który nigdy nie podał maila, jest anonimowa** i nie ma
jak przypisać jej do wniosku; kasuje ją retencja po `CHATL_RETENCJA_DNI` dniach.
Kasownik mówi o tym wprost w komunikacie kreatora, zamiast udawać komplet.

### 23.2. Sesja NIE jest własnością jednego adresu

`chatl_rodo_sesje_maila()` zwraca sesje **z podziałem** na `wlasne` i `wspoldzielone`.
Sesja jest współdzielona, gdy w jej zgłoszeniach występuje także **inny** adres —
wspólny komputer (recepcja, biblioteka) albo gość, który rozmyślił się i podał drugi
adres. Wtedy **jedna rozmowa zawiera wiadomości dwóch osób**, a wiersze `chatl_rozmowy`
nie mają znacznika autora, więc nie da się ich rozdzielić.

Takiej rozmowy **nie wydajemy i nie kasujemy**, bo obie operacje uderzają w osobę
trzecią, i to po cichu:

| Operacja | Co by się stało bez tego podziału |
|---|---|
| eksport | wnioskodawca dostałby **treść rozmowy drugiej osoby** — art. 15 ust. 4 RODO mówi wprost, że prawo do kopii nie może naruszać praw innych osób |
| kasowanie | dane drugiej osoby zniknęłyby **na wniosek osoby trzeciej** |

Zgadywanie po czasie („wiadomości sprzed zgłoszenia należą do tej osoby") wygląda
mądrze i jest fałszem — w bazie nie ma nic, co by to rozstrzygało. Zamiast zgadywać,
kreator **wypisuje**, ile rozmów zachowano i dlaczego, a `items_retained` jest wtedy
`true`. Paczka eksportu dostaje na pierwszej stronie notatkę o pominiętej rozmowie —
milczenie byłoby gorsze niż pominięcie, bo paczka wyglądałaby na komplet.

**Ślad musi przeżyć kasowanie — inaczej ochrona działa tylko dla wniosków składanych
równocześnie.** Współdzielenie widać w tabeli zgłoszeń, ale kasownik **niszczy ten dowód
w tej samej operacji**: usuwa zgłoszenie wnioskodawcy. Sekwencja, która to pokazuje, jest
normalnym trybem pracy kreatora (wnioski przychodzą jeden po drugim, często w odstępie dni):

1. w sesji S zgłoszenia zostawiają A i B,
2. **A prosi o usunięcie** — zgłoszenie A znika, rozmowa S zostaje zachowana (poprawnie),
3. **B prosi później o kopię** — w tabeli nie ma już obcego adresu dla S,
4. bez śladu eksport wydałby B **całą rozmowę, razem z wiadomościami osoby A**.

Dlatego `chatl_rodo_kasownik()` zapisuje identyfikatory sesji współdzielonych do opcji
`chatl_rodo_sesje_wspolne` **zanim** cokolwiek usunie, a `chatl_rodo_sesje_maila()` bierze
pod uwagę zarówno bieżący stan tabeli, jak i ten ślad. Gdy zapis śladu się **nie
powiedzie** (baza odrzuciła zapis), kasownik **nic nie usuwa** i mówi to wprost w
komunikacie — skasowanie zgłoszenia bez śladu zabrałoby jedyny dowód współdzielenia,
a wniosek można powtórzyć po naprawie bazy, wycieku cofnąć się nie da. W opcji leżą
**same identyfikatory sesji** — bez adresu i bez treści, więc to nie są dane osobowe. Lista jest przycinana przy
każdym zapisie: sesja, po której nie została ani jedna wiadomość (retencja skasowała rozmowę
po `CHATL_RETENCJA_DNI` dniach), nie ma już czego chronić i wypada. Przycinanie dzieje się
przy zapisie, a nie w cronie, bo `rodo.php` ładuje się wyłącznie w panelu (`is_admin()`),
więc zadanie crona tej funkcji by nie zobaczyło.

### 23.3. Komunikat kasownika mówi to, co się stało

Komunikat jest budowany **z rzeczywistych liczb** (`%d zgłoszenie`, `%d wiadomość`),
a wniosek dotyczący adresu, którego nie ma w bazie, kończy się zdaniem „nie znaleziono
żadnych danych — nic nie zostało usunięte". Właściciel odpowiada tym tekstem osobie
składającej wniosek, więc zdanie o kasowaniu, którego nie było, byłoby potwierdzeniem
nieprawdy wysłanym w cudzym imieniu.

### 23.4. Kolejność kasowania nie jest dowolna

Najpierw **rozmowy** i **wiersze sesji** (jedno i drugie po tej samej liście sesji
wyłącznych wnioskodawcy), potem **zgłoszenia**. Zgłoszenie jest jedynym łącznikiem
między adresem a sesją — skasowane pierwsze, zabrałoby ze sobą wiedzę o tym, które
rozmowy i które sesje usunąć, i zostawiłoby je w bazie na zawsze. Sesje
**współdzielone** nie są kasowane wcale, dokładnie tak jak ich rozmowy: należą też
do drugiej osoby.

### 23.5. Stronicowanie: dwa źródła, jedna numeracja

Kreator woła eksporter stronami, aż zwróci `done`. Przesunięcie rozmów liczymy
względem **całkowitej liczby zgłoszeń**, a nie numeru strony: przy dokładnie pełnej
stronie zgłoszeń naiwne `OFFSET` przeskoczyłoby pierwszą porcję rozmów i wydało
wniosek **z dziurą**, której nie zauważy ani właściciel, ani osoba składająca wniosek.
Strażnik podkłada dokładnie ten przypadek.
## 25. Pułapka na boty

Publiczny czat dostaje ruch automatów. Sito jest tanie i dwuczęściowe:

| Pułapka | Co łapie | Gdzie |
|---|---|---|
| **ukryte pole** `CHATL_PULAPKA_POLE` | skrypt, który zeskrobał stronę i odsyła wszystkie znalezione pola | `assets/widget.js` (tworzy), `chatl_pulapka_zlapala()` (sprawdza) |
| **próg czasu** `CHATL_PULAPKA_SEKUND` | skrypt, który poprosił o sesję i natychmiast strzelił | liczony z `chatl_sesje.utworzono`, **po stronie serwera**; działa **tylko przy pytaniu do bota** |

Próg czasu **nie** opiera się na znaczniku od przeglądarki — taki znacznik skrypt
podrabia jedną linijką. Wiek sesji bierzemy z własnej tabeli.

### Próg czasu NIE działa przy formularzu kontaktu

To nie jest przeoczenie, tylko wynik pomiaru. Test przeglądarkowy pokazał,
że **prawdziwy gość**, który otworzy czat
i od razu kliknie „Zostaw wiadomość", wysyła formularz szybciej niż w dwie sekundy
od wydania sesji — i wpadał we własną pułapkę. Cena tego fałszywego alarmu to
**utracony kontakt**, czyli jedyna rzecz, dla której ten produkt istnieje.

Rachunek wychodzi tu jednoznacznie:

| | pytanie do bota | formularz kontaktu |
|---|---|---|
| koszt fałszywego alarmu | gość dostaje odpowiedź z reguł zamiast z AI i tego nie zauważa | **utracony kontakt** |
| co próg czasu naprawdę łapie | skrypt, który poprosił o sesję i natychmiast strzelił | to samo |
| decyzja | próg **zostaje** | próg **wyłączony**, zostaje ukryte pole |

Dodatkowy argument, który przesądza: bot uderzający wprost w `admin-ajax.php`
podaje **zmyśloną sesję**, dla której nie ma wiersza w tabeli — a wtedy próg czasu
i tak nie zadziała, bo brak wiersza nie jest dowodem bota. Próg łapie więc wyłącznie
skrypt, który grzecznie poprosi o sesję i zaraz strzeli, czyli wąską klasę. Oddawanie
za nią kontaktów byłoby złym interesem.

Technicznie: `chatl_pulapka_zlapala( $sesja, $sprawdzaj_czas = true )`, a endpoint
zgłoszenia woła ją z `false`.

Pole jest wynoszone poza kadr (`left: -9999px`), a **nie** chowane przez
`display: none`: część prostych botów pomija pola ukryte tak wprost, czyli
akurat te, dla których to sito powstało. Ma `tabindex="-1"` (człowiek nie
dojdzie do niego klawiszem Tab), `aria-hidden="true"` (czytnik ekranu pomija)
i `autocomplete="off"`.

### 25.1. Co się dzieje ze złapanym żądaniem

**Decyzja produktowa, nie techniczna:** złapane zgłoszenie **trafia do bazy
z flagą `podejrzane = 1`**, a nie do kosza. Ten produkt istnieje po to, żeby
małej firmie zbierać kontakty — koszt przejrzenia trzech spamów jest niższy niż
koszt jednego utraconego klienta, a fałszywy alarm w wersji „cicho wyrzuć" jest
**niewidzialny dla obu stron**: gość myśli, że wysłał, właściciel nie wie, że coś
przepadło.

Cisza dotyczy **bota, nie bazy** — odpowiedź dla wysyłającego jest **co do znaku**
identyczna z odpowiedzią na czyste zgłoszenie, więc autor skryptu nie dowiaduje
się, że wpadł.

Trzy warunki, które czynią to bezpiecznym (każdy ma osobny krok w strażniku):

1. **Brak maila.** Złapane zgłoszenie nie uruchamia powiadomienia — ochrona przed
   mail-bombą była głównym powodem postawienia pułapki.
2. **Brak zużycia limitów.** Pułapka jest sprawdzana **przed** `chatl_limit_ip_ok()`
   i przed trybem AI, więc złapane żądania nie zjadają kubła, który chroni
   prawdziwych gości, ani dobowego budżetu AI.
3. **Osobny widok.** Lista zgłoszeń pokazuje domyślnie `podejrzane = 0`, a złapane
   siedzą za filtrem **z licznikiem**. Licznik jest tu częścią mechanizmu, a nie
   ozdobą: to on czyni fałszywy alarm widocznym.

Osobny sufit `CHATL_LIMIT_PULAPKA` ogranicza **zapisy** złapanych z jednego adresu —
skoro złapane lądują w bazie, sama pułapka nie może stać się drogą do zalania
tabeli. Powyżej sufitu zgłoszenie nie jest zapisywane, ale odpowiedź nadal jest
identyczna.

### 25.2. Czego to sito NIE zatrzyma

Pułapka nie zatrzyma kogoś, kto podejrzy kształt żądania i wyśle je wprost do
`admin-ajax.php` z odstępami. Przed tym broni dobowy limit `CHATL_AI_LIMIT_IP_NA_DOBE`
na adres, a nie ukryte pole. Nazywamy to wprost także w `INSTRUKCJA.md`, żeby
właściciel nie wziął tego za zabezpieczenie budżetu AI.

## 26. Stronicowanie list w panelu

Trzy listy panelu miały twarde sufity i **nie dawało się dojść do reszty danych**:
zgłoszenia urywały się na 200, rozmowy na 100, raport luk na 25 tematów. Dane
istniały w bazie, ale z panelu były nieosiągalne.

| Lista | Rozmiar strony | Jak liczona całość |
|---|---|---|
| Zgłoszenia (`chatl_strona_zgloszenia`) | `CHATL_NA_STRONE_ZGLOSZEN` = 200 | `COUNT(*)` z tym samym `WHERE` co lista (czyli **z filtrem widoku**) |
| Rozmowy (`chatl_strona_historia`) | `CHATL_NA_STRONE_ROZMOW` = 100 | `COUNT(DISTINCT sesja)` — lista pokazuje rozmowy, nie wymiany |
| Raport luk (`chatl_strona_statystyki`) | `CHATL_LUKI_POKAZ` = 25 | `count()` pogrupowanych tematów, **w PHP** |

**Rozmiary stron są DOKŁADNIE te, które listy pokazywały wcześniej jako twardy
limit.** To decyzja, nie brak pomysłu: wada brzmiała „lista urywa się i nie ma
strony drugiej", więc naprawiamy **dostęp do reszty danych**, a nie
przyzwyczajenia właściciela. Kto ma dziś mniej wpisów niż limit, nie zobaczy
żadnej zmiany.

### 26.1. Trzy pułapki, które ta zmiana musiała ominąć

1. **Rozmowy liczone po sesjach, nie po wierszach.** Jedna rozmowa to wiele
   wymian; liczenie wierszy dałoby liczbę stron oderwaną od listy i puste strony
   na końcu.
2. **Filtr musi jechać w odsyłaczach.** Lista zgłoszeń ma widok „podejrzane"
   (pułapka na boty) — bez przeniesienia `widok` strona druga pokazywałaby inny
   zbiór niż pierwsza. Liczba wszystkich pozycji też jest liczona **z filtrem**.
3. **Numer strony spoza zakresu jest przycinany** do ostatniej istniejącej.
   Wejście z zakładki po skasowaniu wpisów dawałoby inaczej pustą listę bez
   żadnego wyjaśnienia.

Numer strony siedzi w parametrze `strona_listy`, a nie `paged` ani `page`:
`page` jest zajęte przez WordPressa (slug podstrony).

### 26.2. Czego stronicowanie NIE zmieniło

`CHATL_LUKI_LIMIT` = 2000 ogranicza wiersze **wejściowe** do grupowania i zostaje
bez zmian. To osobna, świadoma granica wydajności, opisana wprost w komunikacie
na stronie statystyk — stronicowanie dotyczy **wyniku** grupowania. Zdjęcie tego
sufitu przy okazji byłoby cichą zmianą zachowania, bo panel obiecuje jedno,
a kod robiłby drugie. Strażnik pilnuje tego osobnym krokiem.

## 27. Znane granice

Rzeczy, których wtyczka **nie** robi — żeby nikt nie szukał ich w kodzie:

- **Bez integracji z Messengerem/WhatsAppem.** Czat operatora na żywo **jest** —
  przejęcie rozmowy z panelu opisuje rozdział **Przejęcie rozmowy przez człowieka**;
  ta lista mówiła co innego do wersji 1.5.7 włącznie i była wtedy sprzeczna z tamtym
  rozdziałem w tym samym pliku.
- **Bez pamięci między wizytami** — sesja żyje w `sessionStorage` karty przeglądarki.
  W trakcie **jednej** rozmowy tryb AI pamięta ostatnie `CHATL_AI_PAMIEC_WYMIAN` wymian
  (rozdział **Pamięć bieżącej rozmowy w trybie AI**); tryb regułowy pozostaje bezstanowy z natury — dopasowuje pojedyncze pytanie.
- **Bez czytania treści strony** — wiedza bota to wyłącznie baza Q&A i opis firmy z ustawień.
- **Bez wielojęzyczności i bez i18n** — teksty są polskie wprost, bez `textdomain`.
- **Podgląd importu CSV widzi bazę z chwili wczytania pliku.** Lista istniejących pytań
  jest pobierana raz, przy budowaniu podglądu, a zapis bierze wiersze podglądu i **nie**
  odpytuje bazy ponownie — to jest cena obietnicy „zapisze się dokładnie to, co widzisz".
  Jeśli w oknie do `CHATL_IMPORT_MINUT` minut to samo pytanie trafi do bazy inną drogą
  (ręcznie albo drugim importem), zapis je zduplikuje. Tabela `chatl_qa` nie ma `UNIQUE`
  na `pytanie` i ręczne dodawanie nigdy tej ochrony nie miało, więc to nie jest regresja —
  ale jest granicą, nie niedopatrzeniem.
- **Wniosek RODO dotyczy adresu e-mail, nie adresu IP.** Limity ruchu trzymają `md5(IP)`
  w tabeli `chatl_rate_limit` z krótkim oknem — nie da się tego powiązać z osobą i nie ma
  po co tego eksportować ani kasować na wniosek. Rozmowy gości, którzy nigdy nie zostawili
  zgłoszenia, są anonimowe i kasuje je retencja, a rozmowy współdzielone przez dwie osoby
  nie są ani wydawane, ani kasowane — jedno i drugie rozpisane w rozdziale
  **Narzędzia RODO WordPressa**.
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
- **Znacznik awarii AI pamięta tylko OSTATNIE nieudane wywołanie**, nie prowadzi
  historii błędów. To sygnał „coś jest nie tak teraz", a nie dziennik diagnostyczny.
- **Zaufanie nagłówkom proxy jest decyzją właściciela, nie automatem.** Wtyczka
  nie zgaduje, czy strona stoi za Cloudflare — przy wyłączonym przełączniku limity
  są co najwyżej surowsze, przy włączonym na stronie bez proxy dałyby się obejść.
- **Wtyczka nie sprawdza, czy wpisany model istnieje.** Lista w ustawieniach to
  podpowiedź, nie walidacja — nieznaną nazwę wykryje dopiero API (HTTP 404).
- **Wtyczka nie przelicza tokenów na pieniądze.** Pokazuje zużycie; stawki
  sprawdzasz u dostawcy. Cennik się zmienia, a paczka u klienta nie.
- **Liczniki tokenów zaczynają się od aktualizacji do 1.4.6** — wcześniejsze
  wywołania nie zostawiły tej informacji i nie są zgadywane wstecz.
- **Retencja obejmuje tylko historię rozmów.** Zgłoszenia (a więc adresy e-mail gości) zostają,
  aż właściciel je skasuje. Jeśli polityka prywatności obiecuje termin także dla nich,
  trzeba je kasować ręcznie albo eksportem i usunięciem.
