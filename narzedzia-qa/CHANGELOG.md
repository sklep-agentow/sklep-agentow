# Historia zmian — narzędzia QA i bramki CI

Wszystkie istotne zmiany w `narzedzia-qa/` są zapisywane w tym pliku.

Format według [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie według [SemVer](https://semver.org/lang/pl/). Wersja dotyczy
**pojedynczego narzędzia** (stała `WERSJA` w jego nagłówku), nie całego
katalogu — te skrypty żyją własnym życiem i wchodzą osobnymi PR-ami.

Plik zaczyna się przy bramce recenzji (PR po #94). Wcześniejsze narzędzia
(`bramki.sh`, `sprawdz-manifest.sh`, `kalibrator-straznikow.sh`,
`licznik-odstepstw.sh`, `waga-znaleziska.sh`) powstały bez wpisów — historii
wstecz nie dopisuję, bo nie umiałbym jej odtworzyć uczciwie. Ich zmiany od tej
pory lądują tutaj.

## [Niewydane]

### Dodane

- **`dowod-aktualizacji.sh` 1.0.0 — dowód AKTUALIZACJI W MIEJSCU.** U klienta
  aktualizacja zdarza się częściej niż czysta instalacja, a to zupełnie inna
  ścieżka kodu: **podmiana plików nie przechodzi przez hook aktywacji**. Wszystko,
  co wtyczka robi „przy instalacji" (tabele, kolumny, opcje startowe), musi przy
  aktualizacji zrobić kod porównujący wersję schematu przy `plugins_loaded`.
  Gdy go nie ma albo jest błędny, klient dostaje wtyczkę pytającą bazę o kolumnę,
  której u niego nie ma — a **czysta instalacja nigdy tego nie pokaże**, bo tam
  hook aktywacji zrobił wszystko. To dziura **D4** z materiału audytowego.

  Skrypt instaluje **starą** paczkę, zdejmuje odcisk schematu i zostawia własny
  wiersz-znacznik, podmienia pliki na **nową** paczkę BEZ deaktywacji, puka na
  stronę (dopiero żądanie odpala kod aktualizacji) i sprawdza pięć rzeczy:
  wersja wtyczki jest nowa · **wersja schematu poszła w górę** i zgadza się
  z nowym kodem · schemat **przybył** i nic z niego **nie znikło** ·
  **stare dane przetrwały** co do wiersza i treści · zero notice, warning,
  deprecated i fatal.

  Starą paczkę bierze z `boty/paczki/`, a gdy jej tam już nie ma (nowe wydanie
  kasuje poprzednią) — **wyciąga ją z historii gita**. To celowe: ścieżka
  aktualizacji dotyczy właśnie wersji, których w drzewie roboczym już nie ma.

  Znacznik jest **własny**, nie z seeda instalatora: przetrwanie danych, które
  instalator i tak wpisuje od nowa, niczego nie dowodzi.

- **`dowod-aktualizacji-proba-kontrolna.sh` — trzy mutanty i kontrola odwrotna.**
  Nowa wersja bez kodu migracji (schemat stoi) · migracja kasująca starą kolumnę
  (znika miejsce na dane klienta) · migracja czyszcząca tabelę (dane klienta giną).
  Każdy przypadek sprawdza kod wyjścia **oraz czy dowód nazwał przyczynę**.
  Mutant wchodzi pod nazwą prawdziwej paczki, więc próba **zawsze przywraca**
  oryginał z kopii — także gdy przebieg zostanie przerwany.

- **`lib-stos-probny.sh` 1.0.0 — wspólny warsztat obu dowodów.** Stawianie
  czystej instalacji z `WP_DEBUG`, odciski bazy i schematu, log błędów PHP,
  sprzątanie. Wyciągnięte z `dowod-z-paczki.sh`, żeby poprawka w jednym miejscu
  (jak naprawa kolejności odcisku) trafiała do obu dowodów — ta sama zasada,
  co „napraw klasę, nie egzemplarz". Doszedł **odcisk schematu** (tabela + kolumna
  + typ): sama lista tabel nie wystarcza, bo aktualizacja dokładająca KOLUMNĘ
  wygląda po niej identycznie jak aktualizacja, która nie zrobiła nic.

### Zmienione

- **`dowod-z-paczki.sh` → 1.0.1** — korzysta ze wspólnej biblioteki zamiast
  własnej kopii stawiania stosu. Zachowanie bez zmian: przebieg na paczce
  ChatBota i pełna kalibracja (4 mutanty + kontrola odwrotna) po refaktorze
  dają ten sam wynik co przed nim.


- **`dowod-z-paczki.sh` 1.0.0 — powtarzalny dowód Z PACZKI, nie z repozytorium.**
  Kupujący nie dostaje repozytorium, dostaje ZIP — więc wszystko, co sprawdzamy na
  drzewie roboczym, sprawdza **inny artefakt niż sprzedawany**. Skrypt bierze paczkę,
  stawia **czystą instalację WordPressa z włączonym `WP_DEBUG`** i przechodzi pełne
  życie wtyczki, odpowiadając kodem wyjścia na cztery pytania:

  1. czy wgranie **z zipa** i aktywacja przechodzą bez notice, warning, deprecated
     i fatal (log `WP_DEBUG_LOG`, nie „wygląda czysto"),
  2. czy **silnik regułowy odpowiada, gdy klucza API nie ma wcale**,
  3. czy po deinstalacji z pełnym czyszczeniem **różnica odcisków bazy jest PUSTA** —
     i różnica jest **pokazywana**, nie streszczana,
  4. czy **sekrety właściciela znikają ZAWSZE**, także gdy właściciel nie zaznaczył
     „usuń wszystkie dane".

  Działa dla **obu botów** (tablica z sekretami, opcją czyszczenia i wywołaniem
  silnika wpisana w skrypcie, nie zgadywana), bierze paczkę jako argument i kończy
  się **kodem 1**, gdy cokolwiek z tego nie wyjdzie.

  ⛔ **Nie dotyka stosu warsztatowego.** Stawia własną instalację (kontener
  `probny_wp`, baza `wp_probny`) i tylko ją kasuje — wgrywanie i kasowanie wtyczek
  na wspólnym stosie zniszczyłoby cudzą robotę w toku. Odczyty idą przez
  `stos.sh --stos probny`; drugiego dostępu do stosu tu nie ma.

  Odcisk pomija wpisy, które WordPress dopisuje w tle niezależnie od wtyczki —
  **lista pominięć jest wypisywana przy każdym przebiegu**, żeby „pusta różnica"
  nie stała się pustym słowem.

- **`dowod-z-paczki-proba-kontrolna.sh` — cztery mutanty i kontrola odwrotna.**
  Psuje w kopii paczki po jednej rzeczy naraz, dokładnie tej, której dany dowód
  pilnuje: uninstall przestaje kasować klucz API, wtyczka rzuca notice przy starcie,
  pełne czyszczenie zostawia opcję w bazie, silnik regułowy milczy bez klucza.
  Każdy przypadek sprawdza **kod wyjścia ORAZ czy dowód nazwał przyczynę** — dowód
  padający na wszystko z tym samym komunikatem jest bezużyteczny przy szukaniu wady.
  Paczka w repo nie jest dotykana ani razu.

### Zmienione

- **`stos.sh`** — wybór stosu z **zamkniętej listy dwóch pozycji**
  (`--stos warsztat|probny`). To nadal nie jest wybór kontenera przez dzwoniącego:
  kontenery i adresy są wpisane w skrypcie, a nazwa spoza listy to odmowa z kodem 2.
  Potrzebne, żeby dowód z paczki czytał swoją czystą instalację **tym samym oknem**,
  zamiast dorabiać drugi dostęp do WordPressa.


- **`stos.sh` 1.0.0 — czysto odczytowe okno recenzenta na żywy stos.**
  Pomiar wyjściowy: w recenzjach z 20.08 padło **91** zdań „czego nie
  sprawdziłem", z czego **51** brzmiało „nie mam uruchomionego WordPressa",
  „nie zweryfikowałem paczki", „nie sprawdziłem renderowania" — a warsztatowy
  WordPress (`http://localhost:8890`), jego baza i łapacz maili stoją na tej
  samej maszynie co wykonawca CI. Dostępu nie było wyłącznie dlatego, że nikt
  go nie dał.

  **Recenzent nie dostaje dockera ani powłoki — dostaje nazwane operacje:**
  `stan`, `strona <sciezka>`, `naglowki <sciezka>`, `opcja <nazwa>`,
  `opcje <prefiks>`, `wtyczki`, `tabele`, `wiersze <tabela>`, `maile`,
  `ostatni-mail`, `wersja-wp`. Polecenie jest zawsze wpisane w skrypcie;
  dzwoniący podaje co najwyżej jedną wartość i tylko taką, która przejdzie
  twarde sprawdzenie wzorca. Nazwy tabel dodatkowo przez białą listę prefiksów.
  Nie ma operacji „uruchom", nie ma przekazywania flag, nie ma wyboru kontenera.

  **Zero zapisu.** Wołane są wyłącznie odczytowe podpolecenia `wp`; jedyny SQL
  składa skrypt i zawsze jest to `SELECT COUNT(*)`. Liczenie wierszy idzie do
  kontenera bazy, bo obraz WordPressa nie ma klienta mysql; poświadczenia
  czytane są przez wp-cli z `wp-config` i nigdy nie są wypisywane.

  Gdy stos nie odpowiada albo brakuje narzędzia — **kod wyjścia 2** i zdanie
  wprost, nigdy ciche zielone. Paczek ten skrypt nie dotyka: od nich jest
  `paczka-zgodnosc.sh`.

- **`stos-proba-kontrolna.sh` — 26 kontroli.** Trzynaście prób przemycenia
  własnego polecenia i wyjścia poza białą listę (średnik i podstawienie
  polecenia w ścieżce, `..`, flaga jako trzeci argument, wstrzyknięcie SQL
  w nazwie tabeli, tabela spoza listy) — każda musi skończyć się kodem 2.
  Dwie kontrole czytające **treść samego skryptu**: żadne wywołanie `wp` nie
  może być operacją zapisu, a liczba wywołań dockera nie może urosnąć (obejście
  białej listy ma rzucać się w oczy w diffie). Jedna kontrola „brak narzędzia
  to kod 2". I dziesięć odczytów z **żywego stosu**, każdy sprawdzany po treści
  odpowiedzi, nie po samym kodzie wyjścia.
- **`paczka-zgodnosc.sh` 1.0.0 — bramka ZAMROŻENIA produktu.** Sprawdza dwie
  rzeczy, które przy zamrażaniu sprawdza się „na oko" i obie sprawdza się źle.

  **Wersja w pięciu miejscach**, nie w czterech: nagłówek wtyczki, stała
  w kodzie, `Stable tag` w `readme.txt`, najnowsze wydanie w `CHANGELOG.md`
  oraz sekcja `== Changelog ==` w `readme.txt` — o tym piątym łatwo zapomnieć,
  bo to osobny format WordPressa w tym samym pliku co `Stable tag`.
  Osobno pilnowane jest to, czego zgodność pięciu miejsc NIE wyklucza:
  **niepusta sekcja `[Niewydane]` w CHANGELOG-u** oznacza, że w kodzie siedzą
  zmiany ponad ostatnie wydanie, więc paczka z tym numerem nie jest tym, co ten
  numer opisuje.

  **Paczka porównana co do bajtu, w trzy strony:** każdy plik zipa musi mieć
  sumę SHA-256 równą plikowi z repozytorium (sama lista nazw nie mówi nic —
  plik o tej samej nazwie bywa wersją sprzed trzech PR-ów), żaden plik wtyczki
  nie może zostać pominięty przy pakowaniu, a zawartość zipa musi zgadzać się
  z allowlistą w `boty/buduj-paczki.sh`.

  Osobno sprawdzana jest surowa postać wersji z nagłówka: numer, który nie
  wygląda jak numer (np. `1.3.`), przeszedłby przez porównanie „wszystkie mówią
  to samo", bo wszystkie mówiłyby to samo źle (uwaga z recenzji PR #101).

  Brak wtyczki, brak paczki dla wersji z nagłówka albo brak `unzip`/`sha256sum`
  to **kod wyjścia 2**, nie domyślne zielone.

- **`paczka-zgodnosc-proba-kontrolna.sh` — 12 podłożonych rozjazdów.** Cztery
  warianty wersji, która została w tyle (każdy z osobna), kod ponad wersją,
  plik w zipie o tej samej nazwie i innej treści, plik zgubiony przy pakowaniu,
  pasażer na gapę (`.env`), allowlista rozjechana ze zbudowanym zipem, brak
  paczki, wersja, która nie jest numerem — plus kontrola odwrotna, że czysty stan przechodzi. Każdy przypadek
  sprawdza nie tylko kod wyjścia, ale też czy bramka **nazwała** przyczynę.
  Pracuje na kopii drzewa, więc prawdziwe pliki i prawdziwa paczka nie są
  dotykane ani razu.


- **`bramka-recenzji.sh` 1.0.0 — bramka na „niemy zgon" recenzenta.** Akcja
  recenzenta potrafi zakończyć się **zielono, nie napisawszy ani słowa**:
  przy odmowie wymiany tokenu krok trwa dwadzieścia sekund, artefakt z wyjściem
  CLI jest pusty, a check na liście PR-a wygląda identycznie jak po czystej
  recenzji. Zdarzyło się to dwa razy 20.08 i raz doprowadziło do scalenia PR-a
  z otwartą uwagą KRYTYCZNĄ.

  Kryterium jest **podwójne** i musi być spełnione w całości: recenzja trwała
  **dłużej niż 60 s** ORAZ recenzent zostawił **komentarz w trakcie tego
  przebiegu**. Sam czas nie wystarcza (przebieg może mielić i nic nie napisać),
  sama obecność komentarza też nie — przy `track_progress: true`
  komentarz-znacznik powstaje w pierwszych sekundach, więc widnieje także pod
  zgonem po dwudziestu sekundach. Liczone są wyłącznie komentarze `claude[bot]`
  powstałe **od startu tego przebiegu**; wpis z poprzedniej recenzji nie
  uchodzi za dzisiejszą pracę.

  **Wyjątek jest wąski i wymaga OBU warunków naraz:** PR rusza plik
  w `.github/workflows/` ORAZ CLI recenzenta w ogóle nie wystartowało (brak
  pliku z wyjściem). Sam fakt, że PR dotyka CI, nie wystarcza — inaczej
  prawdziwy niemy zgon przechodziłby bokiem dokładnie tam, gdzie i tak nikt nas
  nie sprawdzi. Wyjątek jest **wypisany w logu wraz z żądaniem, żeby PR-a
  przeczytał człowiek** — nigdy ciche przepuszczenie.

  Brak danych wejściowych (znacznika czasu, tokenu, `jq`, odpowiedzi API) to
  **kod wyjścia 2**, nie domyślne zielone.

- **`bramka-recenzji-proba-kontrolna.sh` — 14 podłożonych przypadków.**
  Sześć wariantów niemego zgonu (ma paść), trzy warianty legalnego wyjątku
  (ma przepuścić), pułapka „PR rusza CI, ale CLI ruszyło i zdechło" (ma paść —
  wyjątek nie może przykryć prawdziwego zgonu), dwie kontrole odwrotne
  (prawdziwa recenzja ma przechodzić) i dwie kontrole wejścia. Dane podstawiane
  plikami, więc próba działa bez pukania do GitHuba, tak samo na biurku i w CI.

### Zmienione

- **`.github/workflows/recenzent.yml`** — `--allowedTools` dostał trzy skrypty:
  `stos.sh`, `paczka-zgodnosc.sh` i `instrukcja-liczby-segregator.sh`. Dwóch
  ostatnich brakowało od PR-ów #94 i #101, przez co recenzent dwa razy pisał,
  że nie mógł uruchomić bramki dostarczonej w recenzowanym PR-ze.
- **`.github/RECENZENT-BRIEFING.md`** — akapit „MASZ ŻYWY STOS": zanim
  recenzent napisze w „czego nie sprawdziłem" zdanie o braku WordPressa,
  paczki, renderowania albo zawartości bazy, ma o to **zapytać stos i podać
  odpowiedź**. Takie zdanie wolno napisać tylko wtedy, gdy `stos.sh` sam
  odpowiedział, że nie działa — wtedy z cytatem jego komunikatu.

- **`.github/workflows/recenzent.yml`** — doszedł krok `Znacznik startu
  recenzji` (pomiar czasu) oraz krok `Bramka: recenzja naprawde sie odbyla`
  z warunkiem `!cancelled()`. Przebieg ubity przez `concurrency` nie jest
  niemym zgonem, tylko naszą własną oszczędnością, więc czerwień za to byłaby
  kłamstwem.
