# Changelog — ChatBot LIVE

Wszystkie istotne zmiany w tej wtyczce. Format według
[Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), numeracja według
[Semantic Versioning](https://semver.org/lang/pl/).

Kolumna „schemat bazy" jest podana przy każdej wersji, która go zmieniała —
migracja odpala się sama przy pierwszym wejściu do panelu po aktualizacji
(`chatl_sprawdz_schemat()`), bez deaktywacji wtyczki.

---

## [1.4.9] — 2026-08-20

### Naprawione
- **Tryb AI nie pamiętał bieżącej rozmowy.** Do modelu szło samo ostatnie
  pytanie, więc gość, który pytał „jak dojechać", a potem „gdzie zaparkować",
  dostawał odpowiedź od bota nierozumiejącego, o czym mowa — choć wizualnie
  to była jedna rozmowa. Teraz model dostaje ostatnie **6 wymian** tej rozmowy.

### Uwaga
- To nadal **nie jest** pamięć między wizytami — zamknięcie karty przeglądarki
  kończy rozmowę, tak jak wcześniej.
- Pamięć jest **ucięta celowo**: każda dołożona wymiana to tokeny na rachunku
  właściciela. Sześć wymian pokrywa realny ciąg dopytań, a długie odpowiedzi
  z przeszłości są przycinane, żeby jedna z nich nie zdominowała zapytania.

## [1.4.8] — 2026-08-20

### Naprawione
- **Gość nie wiedział, że bot pracuje.** W trybie AI odpowiedź potrafi zająć
  kilkanaście sekund, a pole i przycisk nie były blokowane i nic się nie działo —
  więc gość uznawał, że czat się zawiesił, i klikał drugi raz, co uruchamiało
  **kolejne płatne wywołanie modelu**. Teraz na czas oczekiwania pole i przycisk
  są zablokowane, a w rozmowie pojawia się dymek z trzema kropkami.

### Uwaga
- Blokada działa też jako zabezpieczenie przed podwójnym wysłaniem.
- Po nieudanym żądaniu pole wraca tak samo jak po udanym — jedna wpadka sieci
  nie zamyka czatu.
- Kropki przestają skakać, gdy w systemie włączone jest „ogranicz animacje".

## [1.4.6] — 2026-08-20 · schemat bazy 4 → 5

### Naprawione
- **Dobowy limit chronił przed liczbą wywołań, a rachunek idzie z tokenów.**
  Cała aktywna baza Q&A wchodzi do każdego zapytania, więc klient z 200 parami
  płacił wielokrotność klienta z 10 parami przy identycznym „limicie 100 wywołań".
  Doszedł **drugi stop-loss: dobowy limit tokenów** (domyślnie 200 000, `0` = wyłączony).

### Dodane
- Zapis zużycia tokenów z każdej odpowiedzi API (wejście, wyjście, zapis
  i odczyt cache — osobno).
- **Zużycie tokenów w Statystykach** (za wybrany okres) i w Ustawieniach (dzisiejsze).
- Cache instrukcji systemowej po stronie API — **dokładany warunkowo**, tylko gdy
  instrukcja jest dość długa, żeby cache w ogóle zadziałał.

### Uwaga przy aktualizacji
- Liczniki tokenów startują od tej wersji; wcześniejsze wywołania nie zostawiły
  tej informacji i nie są zgadywane wstecz.
- Wtyczka pokazuje tokeny, **nie kwoty** — stawki sprawdzasz u dostawcy modelu.

## [1.4.5] — 2026-08-20

### Zmienione
- **Gość dowiaduje się o zapisie rozmowy na jej starcie, a nie dopiero przy
  formularzu kontaktu.** Widget pokazuje teraz krótką notę („Rozmowa jest
  zapisywana przez 90 dni, żebyśmy mogli wrócić z odpowiedzią") **przed**
  powitaniem bota, a gdy podałeś adres polityki prywatności — z odnośnikiem
  do niej. Do tej pory informacja pojawiała się dopiero przy zostawianiu maila,
  choć samo pisanie na czacie już trafiało do historii rozmów.

## [1.4.4] — 2026-08-20

### Zmienione
- **Domyślny model to teraz alias bez daty** (`claude-haiku-4-5` zamiast
  `claude-haiku-4-5-20251001`). Identyfikator z datą wskazuje konkretną migawkę
  modelu, a te bywają wycofywane — wtedy **każda sprzedana kopia wtyczki traci
  tryb AI naraz**. Alias przeżywa wymianę migawki.

### Dodane
- **Wybór modelu w ustawieniach**: lista podpowiedzi (od najtańszego do
  najmocniejszego) plus pole na własny identyfikator. Do tej pory zmiana modelu
  wymagała napisania filtru w kodzie — czego kupujący wtyczkę nie robi.
- Panel mówi wprost, **żeby wpisywać identyfikator bez daty**, i odsyła do
  dokumentacji dostawcy po aktualną listę modeli i cennik.

## [1.4.3] — 2026-08-20

### Naprawione
- **Za Cloudflare albo proxy hostingu cała witryna miała JEDEN limit.** Limit
  liczony z samego `REMOTE_ADDR` mierzył adres proxy, więc 10 wiadomości na minutę
  obowiązywało wszystkich gości razem, a dobowy limit 20 wywołań AI na adres
  stawał się limitem na całą stronę — jeden ruchliwy dzień i reszta gości dostawała
  odmowy albo odpowiedzi wyłącznie z reguł.

### Dodane
- Ustawienie **„Strona za Cloudflare albo proxy"** (domyślnie **wyłączone**).
  Po włączeniu adres gościa ustalany jest z `CF-Connecting-IP`, potem z pierwszego
  adresu `X-Forwarded-For`, a na końcu z `REMOTE_ADDR`.
- Filtr `chatl_ip_goscia` — furtka dla hostingu z nietypowym nagłówkiem.

### Uwaga
- Przełącznik jest domyślnie wyłączony **celowo**: `X-Forwarded-For` da się
  podrobić, więc na stronie bez proxy zaufanie mu pozwoliłoby obchodzić limity.
  Włącz go tylko wtedy, gdy strona naprawdę stoi za proxy.

## [1.4.2] — 2026-08-20

### Naprawione
- **Zły albo wygasły klucz API oznaczał, że bot po cichu odpowiada wyłącznie
  z reguł — w nieskończoność.** Błąd trafiał tylko do logu WordPressa, który na
  produkcji jest wyłączony, więc właściciel płacił za tryb AI, którego nie ma,
  i nie miał jak tego zauważyć. Teraz każde nieudane wywołanie zostawia znacznik,
  a panel pokazuje **czerwony pasek** z datą, kodem HTTP i tym, co zrobić.
  Znacznik znika sam po pierwszym udanym wywołaniu.

- **Także wtedy, gdy API odpowie kodem 200, ale treść jest nieczytelna**
  (proxy albo brama zgodności podmieniła odpowiedź) — bot zjeżdża do reguł, więc
  właściciel dostaje ślad i osobną wskazówkę, że kluczem to prawdopodobnie nie jest.

### Dodane
- Przycisk **„Przetestuj klucz"** w ustawieniach: jedno tanie wywołanie API
  (jedno słowo, `max_tokens = 1`, bez bazy Q&A w żądaniu), wynik od razu na
  ekranie. Nie zużywa dziennego limitu. Komunikaty rozróżniają zły klucz (401/403),
  nieznany model (404), limit u dostawcy (429) i błąd połączenia.
- Opcja `chatl_blad_ai` (czas, kod HTTP, przycięty fragment odpowiedzi).
## [1.4.1] — 2026-08-20

### Naprawione
- **Czat przestawał działać wszystkim gościom naraz na stronie z cache — po cichu.**
  Zabezpieczenie formularza (nonce) wchodzi do HTML-a strony, a wtyczka albo usługa
  cache (LiteSpeed, WP Rocket, W3TC, Cloudflare) potrafi podawać ten HTML dłużej,
  niż zabezpieczenie żyje. Każde pytanie kończyło się wtedy komunikatem
  „Coś poszło nie tak", a właściciel nie miał jak się o tym dowiedzieć.
  Teraz widget **sam dociąga świeże zabezpieczenie i powtarza pytanie raz**,
  więc rozmowa dochodzi do skutku, a panel pokazuje właścicielowi czerwone
  ostrzeżenie z liczbą odrzuceń i wskazówką, żeby wyłączyć cache dla strony z czatem.

### Dodane
- Publiczny punkt wejścia `chatl_nonce` wydający świeże zabezpieczenie
  (bez nonce'a — z osobnym limitem `CHATL_LIMIT_NONCE` na adres IP).
- Opcja `chatl_blad_nonce` i ostrzeżenie w panelu (wzorzec jak przy nieudanym
  powiadomieniu mailowym), z przyciskiem ukrycia.

---

## [1.4.0] — 2026-08-20

### Dodane
- **Konfigurowalny wygląd widgetu** (Ustawienia → Wygląd widgetu): kolor główny
  (bąbelek, dymki bota, przyciski), kolor paska nagłówka, kolor tekstu na nich
  oraz róg strony, w którym stoi widget — prawy albo lewy dolny.
- **Podgląd na żywo** w panelu: kolory zmieniają się od razu przy wyborze, przed
  zapisem. Podgląd używa tych samych zmiennych CSS co widget na stronie, więc
  nie może pokazać czegoś innego, niż zobaczy gość.
- **Kontrola kontrastu WCAG 2.1 (AA, próg 4.5:1)** przy wybranych kolorach:
  panel podaje policzony współczynnik i mówi wprost, czy zestaw spełnia próg.
  Domyślnie włączone „dobierz automatycznie" — wtyczka sama wybiera ciemny albo
  jasny tekst, ten z lepszym kontrastem do wybranego tła.
- **Ekran powitalny po aktywacji** — podziękowanie, trzy pierwsze kroki
  i odnośniki do ustawień, bazy Q&A oraz statystyk. Pokazywany raz, po
  zamknięciu nie wraca; decyzja zapamiętywana **osobno dla każdego konta
  administratora**, bo na jednej stronie bywa ich kilku.
- **`CHANGELOG.md` i `readme.txt`** w paczce — historia zmian oraz opis
  w standardzie WordPressa (nagłówek, Description, Installation, FAQ, Changelog).

### Zmienione
- Kolory widgetu przeniesione ze sztywnych wartości w `assets/widget.css` na
  **zmienne CSS** deklarowane na `.chatl-korzen`. Wartości domyślne są takie
  same jak przed tą wersją, więc **aktualizacja nie zmienia nikomu wyglądu**.
  Ustawienia właściciela dokładają tylko te zmienne, które naprawdę zmienił —
  gdy wygląd jest domyślny, strona nie dostaje ani jednego dodatkowego bajtu,
  a statyczny arkusz dalej siedzi w pamięci podręcznej przeglądarki.

### Usunięte
- Nic.

---

## [1.3.0] — 2026-08-20 · schemat bazy 3 → 4

### Dodane
- **Statystyki i luki** — nowa strona panelu: ile pytań przyszło w wybranym
  okresie (7 / 30 / 90 dni albo własne daty), na ile bot odpowiedział i jaki to
  procent, ile rozmów skończyło się zebraniem kontaktu, rozdział odpowiedzi
  z reguł i z trybu AI, ruch dzień po dniu.
- **Raport luk** — lista najczęstszych pytań, na które bot NIE znał odpowiedzi,
  pogrupowana w tematy (goście pytają o to samo na wiele sposobów). Przy każdym
  temacie przycisk **„Dopisz odpowiedź"**, który otwiera dodawanie nowej pary
  Q&A z już wpisanym pytaniem i podpowiedzianymi słowami kluczowymi.
- Kolumna `trafiono` w tabeli `wp_chatl_rozmowy` i indeks `KEY luki` — to z nich
  liczą się statystyki i raport luk.

### Zmienione
- `chatl_zapisz_wymiane()` zwraca teraz identyfikator zapisanego wiersza
  (wcześniej wartość logiczną), bo znacznik trafienia dopisuje się do wiersza
  pytania po ustaleniu wyniku dopasowania.

### Uwaga przy aktualizacji
- Rozmowy zapisane **przed** tą wersją nie mają informacji, czy bot trafił —
  wtyczka jej wtedy nie zbierała i **nie zgaduje jej wstecz**. Takie wiersze są
  na stronie statystyk policzone osobno i nie wchodzą do procentu skuteczności.
  Rozmowy od aktualizacji liczą się normalnie.

---

## [1.2.5] — 2026-08-20

### Dodane
- `DOKUMENTACJA-TECHNICZNA.md` — schemat wszystkich tabel kolumna po kolumnie,
  opcje w `wp_options` z autoloadem, wszystkie stałe, cykl życia (aktywacja,
  migracja schematu, cron retencji, obie ścieżki deinstalacji), punkty wejścia,
  filtry do rozbudowy, bezpieczeństwo i jawna sekcja „Znane granice".
- `DIAGRAM-PRZEPLYWU.svg` — przepływ zapytania gościa, bez odwołań zewnętrznych.
- `LICENSE` — pełny tekst GNU GPL v2.

### Zmienione
- Nagłówek wtyczki i opisy przepisane pod sprzedaż zamiast pod prezentację.

> Wersja 1.2.4 nosiła numer tylko w gałęzi roboczej i **nigdy nie została
> wydana** — zmiany z niej weszły do 1.2.5.

---

## [1.2.3] — 2026-08-20

### Zmienione
- Reguła dopasowania ma **jedno źródło**: `chatl_trafione_warianty()`
  i `chatl_punktacja()` mieszkają w `includes/silnik.php`, a panel testowy
  pokazuje dokładnie to, co policzył silnik, zamiast liczyć drugi raz.
  Wcześniej panel miał własną kopię algorytmu i mógł rozjechać się z botem.

### Dodane
- Test bota w panelu pokazuje, **która para** zadziałała (numer, pytanie
  wzorcowe, dopasowane warianty) i link do jej edycji.

---

## [1.2.2] — 2026-08-20

### Naprawione
- **Cichy ubytek danych klienta**: zapisy do bazy szły bez sprawdzenia wyniku,
  więc gość mógł zobaczyć „Dziękujemy" przy nieudanym zapisie. Każdy zapis jest
  teraz sprawdzany; przy porażce gość dostaje komunikat błędu, a nie
  podziękowanie, i powstaje wpis w logu WordPressa.
- Formularz kontaktu dało się zamknąć wyłącznie przez udane wysłanie — doszedł
  przycisk zamknięcia, obsługa klawisza Escape i reset stanu przy zamykaniu okna.
- Właściciel nie wiedział, gdy `wp_mail()` odrzuciło powiadomienie — panel
  pokazuje teraz ostrzeżenie, a zgłoszenie i tak jest zapisane.

### Dodane
- **Zgoda na przetwarzanie danych** w formularzu kontaktu: wymagany checkbox,
  informacja co zbieramy, po co i na jak długo, opcjonalny link do polityki
  prywatności (nowe pole w ustawieniach). Zgoda sprawdzana **po stronie
  serwera** — bez niej zgłoszenie nie trafia do bazy.
- Osobny, twardszy limit wywołań AI: 20 na dobę z jednego adresu IP, żeby jeden
  gość nie wypalił dobowego budżetu wszystkich.
- Ręczny eksport zgłoszeń i historii rozmów do CSV (z BOM UTF-8, czytany
  poprawnie przez Excel).

---

## [1.2.1] — 2026-08-19

### Zmienione
- Podniesiona wersja w nagłówku i w stałej wersjonującej pliki `.css`/`.js` —
  bez tego przeglądarki podawały gościom stary widget z pamięci podręcznej.
- Widget przejął paletę kolorów strony, na której stoi.

### Naprawione
- Klucz API właściciela jest kasowany przy odinstalowaniu **zawsze**, także gdy
  przełącznik „usuń wszystkie dane" jest wyłączony — poświadczenie to nie są
  dane użytkowe.

---

## [1.2.0] — 2026-08-18 · schemat bazy 2 → 3

### Naprawione
- **Limit zapytań przestał przeciekać.** Licznik na transiencie nie był atomowy:
  równoległe żądania gubiły inkrementacje i przy progu 10 przechodziło 15 z 25
  żądań; w trybie AI gość mógł tak przepalać klucz właściciela. Licznik żyje
  teraz w tabeli `wp_chatl_rate_limit` z atomowym `INSERT … ON DUPLICATE KEY
  UPDATE` — przy równoległych żądaniach przechodzi dokładnie próg.
  Adres IP nie leży w bazie jawnie, tylko jako skrót.

### Zmienione
- Domyślny model AI: szybszy i tańszy `claude-haiku-4-5`.
- Deinstalacja przepisana według Plugin Handbook (`esc_like()` + `prepare()`).

### Dodane
- Instrukcja użytkownika po polsku (`INSTRUKCJA.md`) w paczce.

---

## [1.1.0] — 2026-08-18 · schemat bazy 1 → 2

### Dodane
- **Tryb AI (hybryda).** Z kluczem API właściciela odpowiedzi generuje model
  na podstawie opisu firmy i aktywnej bazy Q&A, z **zakazem zgadywania**: gdy
  model nie jest pewny, bot uczciwie mówi „nie wiem" i zbiera kontakt.
- **Stop-loss**: dobowy limit wywołań z licznikiem w osobnej tabeli, zużywany
  dopiero po udanym wywołaniu. Brak klucza, wyczerpany limit, błąd API albo
  przekroczony czas → bot płynnie wraca do trybu regułowego.
- Kolumna `silnik` w historii rozmów — po fakcie widać, czy odpowiedział model,
  czy reguły.

---

## [1.0.1] — 2026-08-18

### Dodane
- Limit 10 wiadomości na minutę z jednego adresu IP, wspólny dla pytań
  i zgłoszeń — ochrona przed zalewaniem bazy i mail-bombingiem właściciela.
- Dostępność (WCAG): etykiety `aria-label` przy polach, widoczny znacznik
  fokusu dla obsługi z klawiatury, komunikat błędu serwera w widgecie.

### Naprawione
- Retencja działa także wtedy, gdy WP-cron nie odpali z braku ruchu — wejście
  na stronę „Historia rozmów" również czyści wpisy starsze niż 90 dni.

---

## [1.0.0] — 2026-08-18 · schemat bazy 1

### Dodane
- Pierwsze wydanie. Widget czatu (bąbelek + okno) ze **stałym** przyciskiem
  „zostaw wiadomość", silnik regułowy dopasowujący pytanie po wariantach słów
  kluczowych, tekst awaryjny i formularz kontaktu przy braku dopasowania.
- Panel: baza Q&A (dodawanie, edycja, usuwanie, włącznik pojedynczej pary),
  zgłoszenia z czatu, historia rozmów, ustawienia. Każda zmiana zabezpieczona
  nonce i uprawnieniem administratora.
- Seed 10 gotowych par pytanie-odpowiedź typowej polskiej firmy usługowej —
  bot odpowiada od pierwszej minuty.
- Powiadomienie mailem o nowym zgłoszeniu, retencja historii rozmów 90 dni
  (codzienny cron), deinstalacja w dwóch wariantach: dane zostają albo pełne
  czyszczenie.
