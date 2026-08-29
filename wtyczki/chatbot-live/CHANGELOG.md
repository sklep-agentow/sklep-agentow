# Changelog — ChatBot LIVE

Wszystkie istotne zmiany w tej wtyczce. Format według
[Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), numeracja według
[Semantic Versioning](https://semver.org/lang/pl/).

Kolumna „schemat bazy" jest podana przy każdej wersji, która go zmieniała —
migracja odpala się sama przy pierwszym wejściu do panelu po aktualizacji
(`chatl_sprawdz_schemat()`), bez deaktywacji wtyczki.

---

## [Niewydane]

Nic jeszcze.

---

## [1.5.13] — 2026-08-29

### Zmienione
- **Wydanie porządkowe.** Z komentarzy w kodzie i z dokumentacji technicznej usunięte
  wewnętrzne oznaczenia naszych raportów z testów — komentarz niesie powód, dla którego
  kod tak wygląda, a nie numer dokumentu. Zero zmian w działaniu bota.

---

## [1.5.12] — 2026-08-28

### Zmienione
- **Wydanie porządkowe.** Zgodność z WordPress 7.1 podana w opisie wtyczki,
  uporządkowana historia zmian. Zero zmian w działaniu bota.

---

## [1.5.11] — 2026-08-25

### Naprawione
- **Tryb hybrydowy: reguły jako siatka bezpieczeństwa pod AI.** Gdy model odpowiada
  „nie wiem" (próg pewności), bot najpierw sprawdza ręcznie wpisaną bazę pytań
  i odpowiedzi — dopiero przy braku trafienia mówi „nie znam" i zbiera kontakt.
  Dotąd nadostrożność modelu (np. przy pytaniach o zwroty) zasłaniała odpowiedź
  leżącą w bazie. Zero zmian w trybie czysto regułowym.

---

## [1.5.10] — 2026-08-24

### Dodane
- **Instrukcja z 6 zrzutami ekranu** (panel, widget oczami gościa, statystyki) —
  robionymi maszynowo, więc odtwarzalnymi po każdej zmianie wyglądu. Zero zmian w kodzie wtyczki.

---

## [1.5.9] — 2026-08-24 · schemat bazy 7 → 8

### Zmienione
- **Zadziałania pułapki na boty i odrzucone zabezpieczenia formularza leżą w tabeli**
  (`chatl_zdarzenia`, schemat bazy 8), a nie w liczniku w `wp_options`. Licznik gubił
  równoległe zapisy (13 z 20 przy 20 żądaniach naraz) i tym więcej, im większy ruch.
  ⚠️ Przy aktualizacji stare liczniki są kasowane i **liczba w panelu zaczyna od zera** —
  zapisane zgłoszenia i ich oznaczenie „podejrzane" zostają nietknięte.
- **Pasek w panelu mówi, ilu złapanych naprawdę znajdziesz na liście zgłoszeń.** Wcześniej
  obiecywał wszystkie zadziałania, a pułapka na ścieżce pytania do bota nie tworzy
  zgłoszenia (gość nie zostawił kontaktu) — właściciel szukał wierszy, których nie ma.

### Naprawione
- **Ścieżka pułapki i ścieżka odrzuconego zabezpieczenia mają limit żądań.** Były jedynymi
  bez limitu: skrypt z wypełnioną pułapką albo zepsutym nonce'em przechodził 30 z 30 przy
  progu 10, a każde żądanie pisało do bazy — bot miał drogę tańszą niż uczciwy gość.
  Kubły są osobne, więc złapane nie zjadają limitu prawdziwego klienta, a odmowa wygląda
  identycznie jak zwykłe przekroczenie limitu (pułapka pozostaje niewykrywalna). Na ścieżce
  odrzuconego zabezpieczenia limit dotyczy **wyłącznie zapisu**: odpowiedź powyżej progu jest
  ta sama, więc czat na stronie z cache dalej naprawia się sam — także wtedy, gdy witryna stoi
  za proxy i wszyscy goście dzielą jeden adres.
- **Odinstalowanie nie zostawia już opcji `chatl_pulapka`** (dokumentacja obiecywała pustą
  różnicę bazy, a opcja przeżywała pełne czyszczenie).
- **Przejęcie rozmowy przez człowieka dociera do gościa bez drugiego pytania.** Otwarcie
  okna czatu kończyło się błędem JavaScriptu (`odpytaj is not defined`) i odpytywanie
  w ogóle nie startowało: operator przejmował rozmowę i pisał, a gość patrzył
  w nieruchome okno, dopóki sam czegoś nie napisał (zmierzone: zero zapytań przez
  20 sekund). Ten sam błąd zamykał się na bąbelku (`zatrzymajOdpytywanie is not defined`).
- **Bąbelek czatu mówi czytnikom ekranu, czy czat jest otwarty** (`aria-expanded`);
  etykieta „Otwórz czat" zostaje bez zmian.
- **Instrukcja ma gotowy wpis powitalny do wklejenia.** „Dzień dobry" to pierwsze, co
  pisze człowiek — a bot z założenia nie zgaduje i odpowiada „nie znam odpowiedzi".
  Wpisu **nie dokładamy do bazy startowej**: z wariantami „dzień dobry"/„dobry wieczór"
  przejmowałby pytania złożone („Dzień dobry, ile kosztuje wizyta?" → powitanie zamiast
  cennika). Instrukcja podaje gotową treść, ostrzega o tym skutku i mówi, jak go uniknąć —
  decyzja należy do właściciela. Silnik dopasowania bez zmian.
- **„Przetestuj klucz" sprawdza ten model, którym naprawdę odpowiada bot.** Przycisk
  pytał modelem domyślnym, więc przy własnym modelu z literówką mówił „Klucz działa",
  choć bot po cichu odpowiadał wyłącznie z reguł.
- **Eksport danych (RODO) i eksport CSV nazywają wszystkie cztery role rozmowy.**
  Wiadomość konsultanta była podpisana „Bot", a komunikat systemowy („do rozmowy
  dołączył konsultant") wyglądał na wypowiedź bota — osoba, która dostała swoje dane
  na wniosek, czytała nieprawdę o tym, kto z nią rozmawiał.
- **Deklaracja prywatności wymienia wszystko, co wychodzi do dostawcy AI** — w tym
  do 6 wcześniejszych wymian tej samej rozmowy (razem z tym, co gość podał wcześniej).
- **Pola panelu mają etykiety** (`<label for>`): plik CSV przy imporcie, własny kolor
  tekstu, własny identyfikator modelu — czytnik ekranu nazywa je teraz poprawnie.
- **Sesje rozmów podlegają retencji i wnioskom RODO.** Tabela sesji nie miała żadnej
  ścieżki kasowania: rosła o wiersz przy każdym otwarciu czatu, także długo po tym, jak
  retencja skasowała samą rozmowę, a po „usunięciu danych" na wniosek RODO zostawał
  ślad, że rozmowa danej osoby istniała. Sesje żywe (z ruchem w ostatnich 90 dniach)
  i sesje innych osób zostają nietknięte.
- **Odinstalowanie zdejmuje zadanie cykliczne wtyczki.** Po usunięciu wtyczki z pominięciem
  dezaktywacji w bazie zostawał zaplanowany zaczep, w który WordPress uderzał codziennie.
- **„Zapisano" dopiero po zmierzonym zapisie**. Przy odrzuconym
  przez bazę zapisie panel ustawień pokazuje komunikat błędu i obowiązują poprzednie
  ustawienia (m.in. dzienny limit wywołań AI) — wcześniej świecił „Ustawienia zapisane".
  Zapis klucza API zwraca wynik i panel mówi wprost, gdy klucz NIE został zapisany
  (wcześniej „Przetestuj klucz" testował stary klucz i dawał zielone potwierdzenie).
  Przejęcie rozmowy / oddanie botowi / powrót po ciszy mierzą stan w bazie po zapisie:
  przy awarii operator widzi błąd zamiast „przejęta", a gość nie czyta „dołączył
  konsultant" do rozmowy, w której dalej odpowiada bot. Nowa sesja przy odrzuconym
  zapisie nie wydaje identyfikatora widma. Znaczniki awarii (tryb AI, poczta) i ślad
  RODO sesji współdzielonych przy padniętym zapisie zostawiają trwały ślad błędu bazy;
  kasownik RODO bez zapisanego śladu nic nie usuwa i mówi to wprost.
  Nowa funkcja pomocnicza `chatl_zapisz_opcje()` (bliźniak Segregatora).
- **Stop-loss nie jest fail-open**: gdy licznika dobowego AI nie da się odczytać
  (uszkodzona/usunięta tabela), tryb AI jest wstrzymany i bot odpowiada z reguł — wcześniej
  błąd odczytu wyglądał jak „0 wywołań" i płatne API było wołane bez limitu. Panel mówi,
  że licznik jest nieczytelny; ślad w pasku błędu bazy.
- **Puste pole liczbowe nie nadpisuje limitu**: „50 000" ze spacją albo
  puste pole w limicie tokenów wyłączało stop-loss (0), w limicie wywołań zjeżdżało do 1 —
  przy zielonym „zapisano". Teraz poprzednia wartość zostaje, a panel mówi, którego pola
  nie zmienił i dlaczego.
- **Retencja liczona w strefie witryny**: granica 90 dni szła w UTC, a czas wpisów
  w strefie witryny — w strefie ujemnej rozmowy ginęły godziny przed terminem.

---

## [1.5.8] — 2026-08-21

### Zmienione

- **Instrukcja, dokumentacja i opis wtyczki mówią to, co bot naprawdę robi.**
  Do 1.5.7 pisaliśmy w nich, że bot nie ma czatu operatora i nie pamięta rozmowy
  — obie te rzeczy weszły do produktu w tej samej wersji, więc zdania o ich braku
  przestały być prawdziwe. Opis „LIVE" mówi teraz o przejęciu rozmowy przez
  człowieka: bot milczy po przejęciu, gość widzi, że pisze z człowiekiem, a po
  pięciu minutach ciszy operatora rozmowa wraca do bota. Zero zmian w kodzie.

---

## [1.5.7] — 2026-08-21

### Dodane
- **Import bazy pytań z pliku CSV — z podglądem przed zapisem.** Firma z gotową
  listą pytań w Excelu nie musi już przepisywać ich po jednym. Wgrany plik trafia
  najpierw na **ekran podglądu**: każdy wiersz z werdyktem (do zapisania /
  duplikat pomijany / błąd), licznikami i możliwością **poprawienia słów
  kluczowych**. Do bazy nie trafia nic, dopóki właściciel nie kliknie „Zapisz".
- **Eksport całej bazy Q&A do CSV.** Domyka obieg „pobierz → popraw w arkuszu →
  wgraj z powrotem"; kolumny eksportu są dokładnie tymi, które przyjmuje import,
  więc własna baza jest zarazem wzorem pliku.

### Dlaczego podgląd, a nie import „na raz"
- Bot dopasowuje pytanie gościa **wyłącznie po słowach kluczowych** — kolumna
  z pytaniem jest tylko etykietą dla właściciela. Wiersz bez słów kluczowych jest
  więc **martwy**: gość może wkleić pytanie słowo w słowo i nie dostanie
  odpowiedzi. Typowy plik z arkusza ma właśnie pytanie i odpowiedź, bez słów
  kluczowych — dlatego wtyczka je **podpowiada** (tą samą funkcją, którą panel
  podpowiada je przy ręcznym dodawaniu wpisu), pokazuje w podglądzie **do edycji**
  i ostrzega przy wierszach, w które bot nie trafi albo trafi zbyt szeroko.

### Pliki z prawdziwego świata
- Rozpoznawany jest **średnik** jako separator (tak zapisuje polski Excel),
  kodowanie **windows-1250** (konwersja przez `iconv`, bo mbstring nie zna CP1250),
  znacznik BOM, końce linii CRLF i puste wiersze zostawiane przez arkusze.
- Kolumny mapowane **po nazwie**, nie po pozycji — kolejność w arkuszu nie ma
  znaczenia; wymagane są `pytanie` i `odpowiedz`, reszta jest nieobowiązkowa.
- Krzywy plik kończy się **komunikatem**, nie białym ekranem: pusty plik, brak
  wymaganych kolumn, plik ponad 2 MB lub ponad 2000 wierszy, plik za duży dla
  limitu hostingu.
- Import **tylko dopisuje**: pytanie, które już jest w bazie, zostaje nietknięte,
  a wiersz z pliku jest pomijany i widać to w podglądzie.

### Porządek przed zamrożeniem wersji
- **Z paczki znikają nasze ślady wewnętrzne.** Komentarze w kodzie i akapity
  dokumentacji technicznej odsyłały do plików, których kupujący nie dostaje
  (nasze testy i wewnętrzne rozpoznania), i powoływały się na numery naszych
  zgłoszeń oraz na osoby z zespołu. Treść merytoryczna została bez zmian —
  zniknęły same odsyłacze. **Działanie wtyczki jest nietknięte.**
- W `readme.txt` zdanie o pełnej historii zmian stoi teraz **nad** listą wydań,
  a nie w środku wpisu 1.5.7, gdzie czytało się jak jego część.

---

## [1.5.6] — 2026-08-21

### Naprawione
- **Trzy listy w panelu urywały się i nie dawało się dojść do reszty danych.**
  Zgłoszenia kończyły się na 200 pozycjach, rozmowy na 100, raport luk na 25
  tematach — dane były w bazie, ale z panelu **nieosiągalne**. Wszystkie trzy
  mają teraz strony.

### Uwaga
- **Rozmiar strony to dokładnie te same liczby**, które listy pokazywały
  wcześniej jako twardy limit (200 / 100 / 25). Kto ma mniej wpisów niż limit,
  nie zobaczy żadnej zmiany — naprawiliśmy dostęp do danych, nie przyzwyczajenia.
- Liczba rozmów liczona jest **po sesjach, nie po wymianach** — jedna rozmowa to
  wiele wiadomości.
- Odsyłacze stron **zachowują filtr** (np. widok „podejrzane" na liście zgłoszeń)
  i wybrany okres na stronie statystyk.
- Wejście na stronę spoza zakresu (np. ze starej zakładki) pokazuje ostatnią
  istniejącą stronę, a nie pustą listę.
- **Limit 2000 wierszy wejściowych raportu luk zostaje bez zmian** — to osobna
  granica wydajności, opisana w panelu; stronicowanie dotyczy wyniku grupowania.

---

## [1.5.4] — 2026-08-21 · schemat bazy 6 → 7

### Dodane
- **Pułapka na boty.** Ukryte pole, którego człowiek nie widzi i nie dosięgnie
  klawiszem Tab, plus **próg czasu liczony po stronie serwera** (pytanie szybsze
  niż 2 sekundy od otwarcia czatu nie jest pisane przez człowieka).
- Próg czasu **nie obowiązuje przy formularzu kontaktu** — test w przeglądarce
  pokazał, że prawdziwy gość klikający „Zostaw wiadomość" zaraz po otwarciu czatu
  wpadał we własną pułapkę, a cena tej pomyłki to utracony kontakt. Formularza
  pilnuje samo ukryte pole.

### Co się dzieje ze złapaną wiadomością — i dlaczego
- **Zostaje zapisana, z oznaczeniem „podejrzane".** Nie kasujemy jej po cichu:
  gdyby sito pomyliło się przy prawdziwym kliencie, nikt by się o tym nie
  dowiedział — klient myślałby, że wysłał, właściciel nie wiedziałby, że coś
  przepadło. Ten produkt istnieje po to, żeby zbierać kontakty.
- **Nie wysyła powiadomienia mailem** — ochrona przed mail-bombą była głównym
  powodem postawienia pułapki.
- **Nie zużywa budżetu trybu AI** ani limitu chroniącego prawdziwych gości.
- Lista **Zgłoszenia** pokazuje domyślnie tylko czyste; złapane są **za filtrem
  z licznikiem**, żeby fałszywy alarm był widoczny.
- Dla wysyłającego odpowiedź jest **co do znaku identyczna** z odpowiedzią na
  czyste zgłoszenie — autor skryptu nie dowiaduje się, że wpadł.

### Uwaga — czego to nie zatrzyma
- Pułapka łapie automaty wypełniające wszystko, co znajdą na stronie, i strzelające
  natychmiast po jej wczytaniu. **Nie zatrzyma** kogoś, kto podejrzy kształt żądania
  i będzie je wysyłał bezpośrednio, z odstępami — przed tym broni dobowy limit
  zapytań AI na adres. Napisane wprost także w instrukcji.

---

## [1.5.2] — 2026-08-21

### Dodane
- **Wpięcie w narzędzia RODO WordPressa.** Wniosek o kopię albo o usunięcie danych
  obsługujesz wbudowanym kreatorem (**Narzędzia → Eksport / Usuwanie danych
  osobowych**): wtyczka wydaje zgłoszenia z czatu i powiązaną z nimi historię
  rozmowy, a kasownik usuwa jedno i drugie.
- Gotowy akapit do **polityki prywatności** (Ustawienia → Prywatność).

### Uwaga
- Wiązanie idzie przez **adres e-mail**, więc rozmowa gościa, który nigdy nie
  zostawił zgłoszenia, nie jest powiązana z żadnym adresem — kasuje ją retencja.
  Kreator mówi o tym wprost, zamiast udawać komplet.
- **Rozmowa, w której zgłoszenie zostawiła więcej niż jedna osoba** (wspólny
  komputer w recepcji albo gość, który podał drugi adres), nie jest ani wydawana,
  ani kasowana: zawiera wiadomości dwóch osób, a wiersze nie mają znacznika autora,
  więc nie da się ich rozdzielić. Wydanie jej byłoby ujawnieniem cudzych danych
  wnioskodawcy, a skasowanie — usunięciem cudzych danych na wniosek osoby trzeciej.
  Kreator wypisuje, ile takich rozmów zachowano i dlaczego. Ochrona działa także wtedy,
  gdy wnioski przychodzą **po kolei**: informacja o współdzieleniu sesji jest zapisywana
  zanim cokolwiek zostanie usunięte, więc nie znika razem ze skasowanym zgłoszeniem.
- Komunikat kasownika podaje **rzeczywiste liczby** usuniętych zgłoszeń i wiadomości.
  Wniosek dotyczący adresu, którego nie ma w bazie, kończy się zdaniem „nie znaleziono
  żadnych danych" — a nie potwierdzeniem kasowania, którego nie było.
---

## [1.5.0] — 2026-08-20 · schemat bazy 5 → 6

### Dodane
- **Przejęcie rozmowy przez człowieka.** Operator może wejść do trwającej rozmowy;
  od tej chwili **bot milczy**, a gość widzi **wyraźny komunikat**, że rozmawia
  z człowiekiem. Zakończenie oddaje rozmowę botowi — też z komunikatem.
- **Panel „Rozmowy na żywo"**: lista rozmów z ostatnich 30 minut (kto pisze, ostatnie
  pytanie gościa, ile czeka, liczba wiadomości), okno rozmowy dla operatora
  z odświeżaniem co 5 s oraz przyciski **Przejmij** i **Zakończ**. Wszystko za
  uprawnieniem `manage_options` i noncem.
- Powrót do bota **po ciszy operatora** (5 minut), bez zależności od WP-Cron.
- Publiczny punkt odpytywania `POST /wp-json/chatl/v1/rozmowa` z rytmem
  ustalanym przez serwer: 15 s w trybie bota, 5 s po przejęciu, 30 s przy
  schowanej karcie, 0 zapytań przy zamkniętym oknie czatu.

### Bezpieczeństwo
- **Identyfikator sesji wydaje teraz SERWER**, a nie przeglądarka. Poprzednia
  ścieżka zapasowa opierała się o `Math.random()`, który nie jest generatorem
  kryptograficznym — a od tej wersji identyfikator sesji otwiera treść rozmowy.
- **Drugi składnik dostępu**: sekret w ciastku `HttpOnly`. Sam identyfikator
  sesji — nawet poprawny — **nie wystarczy**, żeby przeczytać rozmowę.

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

---

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

---

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

---

## [1.4.5] — 2026-08-20

### Zmienione
- **Gość dowiaduje się o zapisie rozmowy na jej starcie, a nie dopiero przy
  formularzu kontaktu.** Widget pokazuje teraz krótką notę („Rozmowa jest
  zapisywana przez 90 dni, żebyśmy mogli wrócić z odpowiedzią") **przed**
  powitaniem bota, a gdy podałeś adres polityki prywatności — z odnośnikiem
  do niej. Do tej pory informacja pojawiała się dopiero przy zostawianiu maila,
  choć samo pisanie na czacie już trafiało do historii rozmów.

---

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

---

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

---

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

---

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
