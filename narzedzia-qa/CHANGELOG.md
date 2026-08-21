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

- **`dowod-zachowania-chatbot.sh` 1.0.0 — dowód ZACHOWANIA z paczki (punkt D).**
  `dowod-z-paczki.sh` odpowiada na pytania o cykl życia wtyczki; ten skrypt na trzy
  pytania o zachowanie: **D3** klucz API odcięty w trakcie (zjazd do reguł + znacznik
  w panelu), **D4** strona z cache i nonce starszy niż życie (**luka L1**), **D6**
  retencja naprawdę kasuje. Każdy dowód ma przypadek **BEZ wady**: świeży nonce ma
  działać za pierwszym razem, wpis sprzed jednego dnia ma przeżyć sprzątanie, a przy
  sprawnym API znacznika awarii ma nie być.
  ⛔ **Zero internetu w dowodzie**: „odcięcie klucza" udaje własny punkt końcowy
  w kontenerze przez filtr `chatl_ai_url`, który wtyczka sama daje integratorom.
  Dowód zależny od cudzego serwera nie jest dowodem, tylko prognozą pogody.
  ⚠️ Skrypt **wymaga `lib-stos-probny.sh` z PR #105** — do czasu jego scalenia nie
  da się go uruchomić z samego maina.

- **`slady-wewnetrzne.sh` 1.0.0 — nowe narzędzie i SIÓDMA bramka `slady`
  w `bramki.sh` (próg pełny 6 → 7, tekstowy 3 → 4).** Wyczyszczenie 20 śladów
  z ChatBota było naprawą EGZEMPLARZA: jutro wjeżdża nowy komentarz
  z odsyłaczem do `testy/…` albo numerem naszego zgłoszenia i wszystko wraca.
  Bramka zamrożenia sprawdza to raz, przy zamrażaniu; bramka `slady` sprawdza to
  przy **każdym PR-ze**, także tekstowym — komentarz w dokumentacji to ten sam problem.
  Jedno źródło wzorców dla obu miejsc, żeby dwie listy nie rozjechały się po dopisku.

- **`slady-zaleglosci.txt` — lista DŁUGU, nie lista wyjątków.** 42 ślady, które
  już leżą w repozytorium (segregator-maila 11, sklep-agentow 31, chatbot-live 0),
  są wypisywane przy każdym przebiegu, ale nie blokują — inaczej bramka wywracałaby
  cudze PR-y od pierwszego dnia. Wpis, który przestał do czegokolwiek pasować,
  jest **czerwony**: lista ma się kurczyć, a nie gnić. Przy zamrażaniu lista
  **nie obowiązuje** — `paczka-zgodnosc.sh` woła skaner z pustą listą, bo paczka
  oddawana klientowi ma mieć zero śladów.

### Naprawione

- **`bramki-proba-kontrolna.sh` — trzy zgniłe kotwice.** Dołożenie siódmej bramki
  unieważniło trzy przypadki na raz: fraza „wykonano 5 bramek", `PROG_PELNY=6`
  i pełne brzmienie tablicy `BRAMKI=(…)` w sed-zie. Dwa przypadki cicho przestały
  cokolwiek podkładać, jeden przechodził niezauważony. Kotwice są teraz bez
  zaszytych liczb i bez przepisywania całej tablicy. Do tego **sześć nowych
  przypadków** dla bramki `slady` (TODO, adres dev, ścieżka drzewa, numer PR-a,
  martwy wpis na liście, kontrola odwrotna na czystej wtyczce).

### Zmienione

- **`paczka-zgodnosc.sh` 1.0.0 → 1.1.0 — bramka zamrożenia dostała dwie kontrole,
  których zgodny numer i suma kontrolna NIE łapią.**

  **(C) Wydanie z opisem, nie z samym numerem.** Nagłówek `## [1.5.7]` bez ani
  jednej linii pod spodem przechodził przez porównanie wersji, bo numer był
  poprawny — brakowało tego, co ten numer znaczy. Sprawdzane w `CHANGELOG.md`
  **i** w sekcji `= wersja =` w `readme.txt`, bo to drugie widzi kupujący w sklepie.

  **(D) Paczka bez naszych śladów wewnętrznych.** Zip równy repozytorium co do
  bajtu jest wciąż zipem, w którym siedzi `TODO`, ścieżka `testy/…`, adres
  `localhost:8890` albo „uwaga recenzenta do PR #83". Kontrola zgodności z repo
  takiego śladu **nie widzi z definicji** — ślad jest w repo tak samo jak w paczce.
  Skanowana jest **rozpakowana paczka**, w czterech klasach wzorców: znacznik
  roboczy, adres maszyny dev, ścieżka naszego drzewa, nasz obieg pracy.

  Kalibracja: na stanie sprzed naprawy stara bramka 1.0.0 dawała **EXIT=0**,
  nowa na tym samym stanie **EXIT=1** i 20 wypisanych śladów.

- **`paczka-zgodnosc-proba-kontrolna.sh` — sześć nowych podłożonych przypadków**
  (pusty wpis w CHANGELOG-u, pusta sekcja w readme, TODO w paczce, adres dev,
  ścieżka `testy/…`, numer PR-a) plus kontrola „mutacja naprawdę weszła do pliku".
  Stan czysty badany jest teraz na `chatbot-live`: paczka segregatora 1.3.0 ma
  dziś własne ślady wewnętrzne, więc jako wzorzec czystości się nie nadaje.

- **`.github/workflows/recenzent.yml`** — `--allowedTools` dostał `test-php.sh`
  i jego próbę kontrolną, żeby recenzent mógł uruchomić testy PHP z `testy/`.
  Świadomie **nie** dostał `Bash(php testy/:*)` ani `Bash(docker exec:*)` —
  powód wpisany w komentarzu nad listą, żeby następny czytający nie „poprawił"
  tego z powrotem.
- **`.github/RECENZENT-BRIEFING.md`** — zdanie o tym, że testy `testy/*.php`
  uruchamia się przez `test-php.sh`, bo samo `php testy/X.php` zawsze pada.

- **`.github/workflows/recenzent.yml` — recenzent URUCHAMIA nasze testy, nie tylko
  je czyta.** Do `--allowedTools` doszły **23 pozycje**: wszystkie skrypty z `testy/`
  (poza jednym, niżej), cztery próby kontrolne z `narzedzia-qa/` oraz
  `bramki.sh` — komplet sześciu bramek jednym poleceniem, którego recenzent dotąd
  nie mógł wywołać, przez co pisał „nie uruchomiłem phpcs/phpstan".

  Wpisane **jawnie, po jednym**, zamiast wzorca z gwiazdką: lista ma dać się
  przeczytać w całości i ma boleć przy dopisywaniu, bo każda pozycja to zgoda na
  wykonanie kodu w checkoucie recenzenta.

  ⛔ **Celowo poza listą: `testy/seed-demo.sh`** — jako jedyny pisze do **wspólnego
  stosu warsztatowego** (zamówienia demo w bazie), a nie do własnego checkoutu.
  Reszta prób podmienia pliki wyłącznie u siebie i przywraca je po sobie,
  a checkout runnera jest jednorazowy i bez prawa zapisu do repo.

  ⛔ **Też poza listą: `dowod-z-paczki` i `dowod-aktualizacji` wraz z próbami** —
  stawiają kontenery i biegną kilkanaście minut, czyli dłużej niż cała recenzja.
  To kalibracja przed oddaniem, nie narzędzie recenzenta.

- **`.github/RECENZENT-BRIEFING.md`** — akapit „NASZE TESTY MASZ URUCHAMIAĆ, NIE
  CZYTAĆ". Zdanie „nie uruchomiłem próby kontrolnej" wolno napisać tylko wtedy,
  gdy próba **padła albo odmówiła** — wtedy z cytatem jej wyjścia. Sama allowlista
  nie wystarcza: przy #103 recenzent miał już narzędzie i nadal nie wiedział,
  że ma po nie sięgnąć.


### Dodane

- **`test-php.sh` 1.0.0 — okno recenzenta na URUCHOMIENIE testu PHP.** PR #109
  otworzył recenzentowi całe `testy/`, ale wyłącznie skrypty powłoki. Testy
  `testy/*.php` zostały poza zasięgiem, bo **nie są zwykłymi skryptami PHP**:
  każdy zaczyna się od `WP_PLUGIN_DIR` i `require` plików wtyczki, więc
  `php testy/import-csv-test.php` kończy się `Undefined constant "WP_PLUGIN_DIR"`
  i kodem 255. Uruchamia je wyłącznie wp-cli **wewnątrz kontenera**
  (`docker cp` + `wp eval-file`). Wzorzec `Bash(php testy/:*)` w `--allowedTools`
  otworzyłby więc prawo do polecenia, które i tak zawsze pada, a
  `Bash(docker exec:*)` oddałby całą maszynę.
  Skrypt jest **jedną nazwaną operacją**, dokładnie jak `stos.sh`: dzwoniący
  podaje wyłącznie NAZWĘ testu (wzorzec `[a-z0-9-]`, więc `../`, ścieżka
  bezwzględna, `;` i `$(…)` odpadają zanim cokolwiek dotknie dockera), katalog
  `testy/` jest wpisany w skrypcie, a wołane jest `wp eval-file` na pliku
  Z REPOZYTORIUM, nigdy `wp eval` (kod z wiersza poleceń). `test-php.sh lista`
  wypisuje, co wolno uruchomić. Plik testowy jest kasowany z kontenera także
  wtedy, gdy test padł.
  ⚠️ To **nie** jest okno czysto odczytowe, w odróżnieniu od `stos.sh` — testy
  piszą do bazy warsztatowej (własnym przedrostkiem, sprzątając po sobie).

- **`test-php-proba-kontrolna.sh` — 20 kontroli.** Dwanaście prób wyjścia poza
  `testy/` i przemycenia polecenia w nazwie — każda musi paść **na wzorcu nazwy**,
  nie „gdzieś dalej". To rozróżnienie jest tu sednem: przy poluzowanym wzorcu
  większość ścieżek i tak kończy się kodem 2 (`/etc/passwd.php` nie istnieje),
  więc próba sprawdzająca sam kod wyjścia **przechodziła na dziurawym skrypcie**.
  Dlatego przypadkiem rozstrzygającym jest ścieżka, która po doklejeniu `.php`
  trafia w **istniejący** plik poza `testy/` (`../wtyczki/chatbot-live/chatbot-live`),
  a próba wymaga w wyjściu konkretnego powodu odmowy. Do tego trzy kontrole
  czytające treść samego skryptu (zero `wp eval`, katalog jako stała, liczba
  wywołań dockera bez wzrostu), odmowa z listą przy nieznanym teście, dwie
  kontrole nadmiarowych argumentów i kontrola odwrotna: `import-csv-test.php`
  ma się naprawdę uruchomić i przejść.

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
