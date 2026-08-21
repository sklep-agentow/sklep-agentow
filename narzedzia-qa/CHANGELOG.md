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

- **`adresy-w-dokumencie.sh` 1.0.0 — mechaniczna kontrola, czy dokument nie kłamie
  o WŁASNYCH odsyłaczach.** Powstało, bo 21.08 **trzeci raz** dokument opisujący jakąś
  klasę błędu sam ją popełnił: przegląd „co robi kod, czego nie ma w instrukcji" miał
  nieścisłe adresy `plik:linia`. Pisanie O KLASIE nie chroni przed KLASĄ — uwaga siedzi
  na treści, nie na formie. Chroni tylko kontrola **po** napisaniu.

  Dla każdego `sciezka/plik:linia[,linia]` skrypt sprawdza: czy plik istnieje, czy linia
  istnieje, i **czy treść się zgadza** — w cytowanej linii (albo w nazwie funkcji, wewnątrz
  której stoi) musi wystąpić nazwa albo liczba, którą obiecuje wiersz dokumentu. Adres
  do innego repozytorium jest pomijany jawnie, nie po cichu.

  ⭐ Tryb **SUROWY** włącza sam dokument znacznikiem `<!-- adresy: potwierdzalne -->`:
  wtedy adres, którego wiersz nie nazywa po imieniu, jest **błędem**. Bez tego rozdziału
  narzędzie krzyczałoby na każdy stary dokument w repo i zostałoby wyłączone w tydzień.

  Kalibracja: przestawienie `ai.php:47` → `:470` (linia istnieje, treść inna) i
  `powitanie.php:32` → `:99999` (poza plikiem) — **oba złapane, EXIT=1**.

  Pierwszy przebieg znalazł **7 z 7 adresów nie do użycia** w świeżo napisanym dokumencie
  (brak przedrostka `wtyczki/<wtyczka>/`) oraz — po dopisaniu sekcji do instrukcji ChatBota —
  **12 adresów w przeglądzie liczb, które przestały pasować**, bo mój własny dopisek
  przesunął numery linii w cytowanym pliku. Bez tej kontroli oddałbym oba dokumenty.

### Naprawione

- **KLASA, nie egzemplarz: `wp option get` w teście pustości — pięć miejsc, nie jedno.**
  Naprawa znacznika awarii AI zamknęła tylko jedno wystąpienie. `grep` po wszystkich
  skryptach QA pokazał następne cztery, w tym **strażnika luki L1** (`chatl_blad_nonce`,
  `dowod-zachowania-chatbot.sh:229`), który meldował „odrzucony nonce ZOSTAŁ ODNOTOWANY"
  **zawsze** — także gdyby wtyczka nie odnotowała niczego. Fałszywy pozytyw akurat tam,
  gdzie pilnujemy luki.

  Wspólny odczyt `opcja()` mieszka teraz w `lib-stos-probny.sh`, czyli w **jednym**
  miejscu dla wszystkich trzech dowodów. Pomiar różnicy:

  ```
  $ wp option get nie_ma_takiej_opcji            → Error: Could not get … (NIEPUSTE)
  $ wp eval '… get_option( "nie_ma_takiej_opcji" ) …'  → (PUSTE)
  ```

  Sprawdzone też, gdzie ten wzorzec **NIE** jest błędem: `dowod-z-paczki.sh:123` pyta
  o **kod wyjścia**, nie o tekst — i to jest poprawne, zostaje bez zmian.

- **`dowod-aktualizacji.sh` — dowód wymagał, żeby KAŻDA aktualizacja podnosiła schemat.**
  Wydanie dokumentacyjne (1.5.7 → 1.5.8, tak samo 1.3.0 → 1.3.1) schematu nie rusza,
  więc na zamrożonej parze paczek dowód meldował **dwie wady produktu, których nie ma**.
  Prawdziwym warunkiem nie jest „numer urósł", tylko **„baza zgadza się z kodem"**:
  awarią jest brak wpisu, rozjazd z kodem albo numer, który poszedł **w dół**.
  Po poprawce `chatbot-live 1.5.7 → 1.5.8` przechodzi wszystkie pięć dowodów (EXIT=0),
  a próba kontrolna dalej łapie wszystkie trzy podłożone awarie (EXIT=0) — mutantowi
  „nowa wersja nie ma kodu migracji" zmieniła się tylko fraza, którą dowód go nazywa.

### Naprawione

- **`dowod-zachowania-chatbot.sh` — dziura D3 była wadą PRZYRZĄDU, nie bota. Trzy błędy,
  wszystkie w narzędziu.** Po naprawie dowód na paczce **1.5.8** daje `EXIT=0`:
  odpowiedź idzie z modelu (`silnik = ai` zapisany przez bota), po odcięciu klucza
  bot zjeżdża do reguł, znacznik zapisuje się z kodem 401, panel pokazuje ostrzeżenie,
  a po powrocie API znacznik znika sam.

  1. **Pytał bota szybciej niż próg pułapki na boty.** Sesja i pytanie szły jedno po
     drugim, a wtyczka traktuje pytanie zadane wcześniej niż `CHATL_PULAPKA_SEKUND`
     od wydania sesji jako bota: odpowiada tekstem awaryjnym, **nie woła modelu i nie
     zapisuje ani jednego wiersza rozmowy**. Cały dowód D3 mierzył pułapkę zamiast
     trybu AI. Przerwa czytana jest teraz **z kodu**, nie wpisana liczbą.
  2. **Czytał brak opcji jako jej obecność.** `wp option get` nieistniejącej opcji
     wypisuje „Error: Could not get …", a wrapper `wp()` scala stderr do stdout — więc
     `[ -z "$(wp option get …)" ]` widział niepusty napis. Dwie pozycje meldowały wadę
     produktu tam, gdzie produkt zachował się poprawnie. Pytamy teraz PHP-em.
  3. **Szukał w panelu zdania, którego panel nie pisze** („Ostatnie wywołanie API nie
     powiodło się" zamiast „Tryb AI nie działa"). Kotwica zgniła po zmianie tekstu.

- **`dowod-zachowania-chatbot.sh` i `dowod-zachowania-segregator.sh` — domyślna paczka
  brana z NAGŁÓWKA WTYCZKI, nie wpisana numerem.** Wpisany numer starzeje się przy
  pierwszym podbiciu wersji i wtedy dowód cicho bada **poprzednią** paczkę, która dalej
  leży obok nowej. Złapane przy zamrożeniu: domyślna wartość wskazywała `1.5.7`, choć
  zamrożona jest `1.5.8`.

### Naprawione

- **`dowod-zachowania-segregator-proba-kontrolna.sh` — mutant M1 zakotwiczony WZORCEM,
  nie numerem linii.** Było `sed -i '145s/.*/…MUTANT/'`, czyli podmiana CAŁEJ linii 145
  bez względu na jej treść — takie podstawienie udaje się **zawsze**, więc kontrola
  „czy mutacja weszła" zawsze mówiła TAK. Po refaktorze, gdy prawdziwe przypisanie
  `$na_zywo` przesunie się gdzie indziej, podłożylibyśmy mutanta w **przypadkowym
  miejscu**, dowód padłby z innego powodu, a kalibracja zostałaby odhaczona jako zdana.
  Teraz kotwicą jest `$na_zywo = segm_przenoszenie_dozwolone();` — brak dopasowania
  = brak podstawienia = zgniła kotwica widoczna. (M2 i M3 były zakotwiczone wzorcem
  od początku.)

- **…a przy sprawdzaniu tej poprawki wyszło, że sama kontrola zgniłej kotwicy MILCZAŁA.**
  `zmutuj()` biegnie w **podpowłoce** (`$(zmutuj …)`), więc zwiększony w niej licznik
  błędów ginął razem z nią, a komunikat wypisany na stdout wpadał do zmiennej z nazwą
  zipa zamiast na ekran — do tego `&&` pomijało wtedy sprawdzenie. Efekt: zgniła kotwica
  kończyła się **ciszą i EXIT=0**, czyli próba kontrolna kłamała dokładnie tak, jak miał
  kłamać mutant, którego miała złapać. Diagnostyka idzie teraz na **stderr**, a liczenie
  błędów przeniesione do powłoki głównej (`mutuj_i_sprawdz`).

  Sprawdzone symulacją refaktoru (kotwica celowo zepsuta): **BLAD … (mutacja NIE weszla
  — kotwica zgnila)**, `EXIT=1`. Na poprawnych kotwicach cały przebieg dalej `EXIT=0`.

- **Próba kontrolna sprawdza teraz NAJPIERW SAMĄ SIEBIE.** Pierwszy przypadek podkłada
  kotwicę, której w pliku NIE MA, i wymaga, żeby `zmutuj` odmówił **kodem wyjścia**.
  Bez tego cała reszta kalibracji stoi na założeniu, że mutanty w ogóle wchodzą do
  plików — a to jest dokładnie to założenie, które się dziś nie sprawdziło. Przypadek
  jest tani (sam `sed` na rozpakowanej kopii, zero dockera), więc biegnie zawsze:
  `OK   kotwica, ktorej nie ma w pliku, jest odrzucana (EXIT=1)`.

### Dodane

- **`stos.sh` 1.1.0 → 1.2.0 — `wiersze-gdzie <tabela.kolumna=wartosc>`.** Wąskie okno
  recenzenta umiało policzyć tylko CAŁĄ tabelę, więc dowodu opartego na liczbie wierszy
  z `silnik = ai` **nie dało się powtórzyć cudzą ręką** — recenzent musiał wierzyć na
  słowo. Teraz: `bash narzedzia-qa/stos.sh wiersze-gdzie wp_chatl_rozmowy.silnik=ai`.
  Wszystkie trzy części wejścia przechodzą przez tę samą białą listę znaków co reszta
  skryptu (`[a-zA-Z0-9_-]`), tabela dodatkowo przez białą listę tabel; SQL składa skrypt,
  nie dzwoniący (`silnik=ai';DROP` → ODMOWA, sprawdzone).

  Przy okazji **jedna** funkcja rozmawia z bazą zamiast dwóch: wywołań dockera w skrypcie
  jest teraz **5, a nie 6** — strażnik z próby kontrolnej (pada, gdy ich przybywa, bo to
  wygląda jak obejście białej listy) zostaje nietknięty i przechodzi.

- **`dowod-zachowania-segregator.sh` 1.0.0 — dowód zachowania Segregatora NA SKRZYNCE,
  z paczki.** Odpowiednik `dowod-zachowania-chatbot.sh`, którego dla Segregatora nie było
  wcale, a punkt D listy domknięcia wymaga go dla OBU botów. Trzy dowody: przebieg
  **na sucho nie rusza ani jednej wiadomości**, przebieg **na żywo przenosi i daje się
  cofnąć**, **retencja kasuje wpis sprzed 91 dni i NIE rusza świeższego**.

  ⛔ Stan skrzynki mierzony **niezależnie, Pythonem (`imaplib`) z zewnątrz**, nie klasą
  `Segm_Imap` z wtyczki — test mierzący tym samym kodem, który sprawdza, potwierdza sam
  siebie. Skrzynka warsztatowa (`segm_imap`) nietknięta: skrypt stawia **własny**
  serwer poczty (`probny_imap`, greenmail) i kasuje go po sobie.

  Dwie pułapki złapane po drodze i zamknięte w kodzie: heredok `<<-` zjadał wcięcia
  skryptu Pythona (pomiar wracał pusty, a puste porównanie „przed == po" świeciło na
  **zielono** — dowód, który przechodzi, bo nic nie zmierzył), oraz `docker-proxy`
  przyjmujący połączenie, zanim greenmail podepnie się pod port (czekamy teraz na
  **powitanie protokołu**, nie na samo TCP). Doszła twarda bramka: jeśli pomiar nie
  zwróci liczby wiadomości w INBOX, skrypt **pada**, zamiast porównywać puste napisy.

- **`dowod-zachowania-segregator-proba-kontrolna.sh`** — trzy mutanty celujące
  w obietnice sprzedażowe (na sucho przestaje chronić pocztę, cofnięcie nic nie cofa,
  retencja kasuje wszystko) plus kontrola odwrotna na czystej paczce. Każdy mutant
  sprawdzany dwa razy: czy **wszedł do pliku** i czy dowód padł **z właściwego powodu**.

### Naprawione

- **`dowod-z-paczki.sh` — próba trybu regułowego dla Segregatora mierzyła własny błąd.**
  Podawała maila jako **tablicę** i reguły przez `ARRAY_A`, a silnik czyta `$mail->nadawca`
  i `$regula->pole` — tak wołają go wszystkie trzy miejsca w produkcji
  (`skrzynka-przebieg.php:213`, `admin-probka.php:130`, `silnik.php:175`), zawsze wierszami
  z bazy, czyli obiektami. Efekt: siedem Warningów, „ODPOWIEDZ:Array" i **dowód nie
  wychodził na poprawnej wtyczce**. Próba buduje teraz obiekty i sprawdza KONKRETNY
  wynik (`kategoria=Faktury regula=7`), a nie „cokolwiek niepustego".

### Zmienione

- **Bramka `slady` wpisana do `.github/manifest-bramek.txt` — z siódmej „ponad
  podłogą" staje się TWARDYM MINIMUM.** Do tej pory działała, ale nikogo nie
  zatrzymywała twardo: dało się ją wyjąć z `bramki.sh` i strażnik manifestu nawet
  by nie mrugnął, bo nie było jej na liście obowiązkowych. Mieliśmy już w tym
  projekcie przypadek, że ta sama informacja dotarła trzy razy i nic nie zatrzymała,
  bo wszystko było doradcze — ten wpis to zamyka. Skutek uboczny wpisu: `PROG_PELNY`
  nie może już zejść poniżej **siedmiu**, bo manifest wymienia siedem bramek.

  Trzy nowe przypadki w `bramki-proba-kontrolna.sh`, w tym jeden **end-to-end**:
  ślad podłożony do prawdziwego pliku wtyczki, uruchomione dokładnie to polecenie,
  które woła `quality.yml` — `BRAMKA slady: EXIT=1`, `NIEZALICZONE BRAMKI: 1`,
  cały przebieg **EXIT=1**. Plik wtyczki wraca z kopii zapasowej niezależnie od wyniku.

### Naprawione

- **`slady-wewnetrzne.sh` 1.0.0 → 1.1.0 — wzorzec „nasz obieg pracy" łapie NASZE
  SŁOWNICTWO, nie tylko nasze numery.** W Segregatorze został **bliźniak**
  wyczyszczonych śladów: „Zarzut z pierwszej **rundy krytyk**" mówi o naszym obiegu
  dokładnie to samo, co „recenzja PR #27", tylko innymi słowami — i wzorzec go nie
  widział. Poprawienie samego zdania byłoby naprawą egzemplarza, więc wzorzec dostał
  `\bkrytyk`, `egzaminator` i `Dzidek` (granica słowa przy `krytyk` jest konieczna:
  bez niej wzorzec łapie „diakrytyków"). Dwa nowe przypadki w próbie kontrolnej.
  Skutek na repozytorium: segregator 11 → 12 znalezionych (wszystkie zamknięte poza
  czterema w dokumentacji), sklep 31 → 42 — w tym **jeden ślad w tekście widzianym
  przez użytkownika**, nie w komentarzu: `sklep-agentow/includes/admin-zamowienie.php`
  wypisuje w panelu „(podgląd — egzaminator widzi mail bez skrzynki)".

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
  Odcisk schematu pyta bazę z **ucieczką podkreślnika** (`ESCAPE '!'`): w SQL
  `LIKE` `_` jest metaznakiem „dowolny jeden znak", więc `wp_chatl%` łapałoby
  też `wpXchatl…`. To klasa #2 ze ściągi, a tu jest biblioteka WSPÓLNA —
  nawyk stąd rozniósłby się na każdy kolejny dowód, który z niej skorzysta.

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

- **`stos-proba-kontrolna.sh` — 27 kontroli.** Trzynaście prób przemycenia
  własnego polecenia i wyjścia poza białą listę (średnik i podstawienie
  polecenia w ścieżce, `..`, flaga jako trzeci argument, wstrzyknięcie SQL
  w nazwie tabeli, tabela spoza listy) — każda musi skończyć się kodem 2.
  Trzy kontrole czytające **treść samego skryptu**: żadne wywołanie `wp` nie
  może być operacją zapisu, liczba wywołań dockera nie może urosnąć (obejście
  białej listy ma rzucać się w oczy w diffie), a tekst pomocy nie może zawierać
  literalnych sekwencji ucieczki. Jedna kontrola „brak narzędzia to kod 2". I dziesięć odczytów z **żywego stosu**, każdy sprawdzany po treści
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

- **`dowod-z-paczki.sh` → 1.0.1** — korzysta ze wspólnej biblioteki zamiast
  własnej kopii stawiania stosu. Zachowanie bez zmian: przebieg na paczce
  ChatBota i pełna kalibracja (4 mutanty + kontrola odwrotna) po refaktorze
  dają ten sam wynik co przed nim.
  Domknięte dwie uwagi z recenzji: komentarz nagłówka mówił `WERSJA 1.0.0`,
  a stała w kodzie `1.0.1` (**klasa #4 — wersja rozjechana**, tym razem
  wewnątrz JEDNEGO pliku), oraz `LIKE '${PREFIKS}%'` liczył opcje bez ucieczki
  podkreślnika (**klasa #2 — `esc_like`**: `chatl_%` łapało też `chatlX…`).
  Ucieczka idzie przez jawne `ESCAPE '!'`, nie przez odwrotny ukośnik —
  ukośnik przeżyłby dzisiejszą drogę do bazy, ale nie musi przeżyć następnej.

- **`sprawdz-manifest.sh`** — czwarta kontrola: **rozjazd wersji wewnątrz
  jednego skryptu** `narzedzia-qa/*.sh` (komentarz nagłówka kontra stała
  `WERSJA`). Egzemplarz z recenzji #105 naprawiony ręcznie, ale klasa zostaje
  zamknięta tutaj, żeby następny refaktor nie przywiózł jej z powrotem.
  Skalibrowane: na kodzie sprzed naprawy strażnik pada z **EXIT=1** i nazywa
  plik, po naprawie **EXIT=0**.

- **`stos.sh` → 1.1.0** — wybór stosu z **zamkniętej listy dwóch pozycji**
  (`--stos warsztat|probny`). To nadal nie jest wybór kontenera przez dzwoniącego:
  kontenery i adresy są wpisane w skrypcie, a nazwa spoza listy to odmowa z kodem 2.
  Potrzebne, żeby dowód z paczki czytał swoją czystą instalację **tym samym oknem**,
  zamiast dorabiać drugi dostęp do WordPressa.
  Przy okazji naprawiony tekst pomocy: heredok `POMOC` nie interpretuje ucieczek,
  więc wpisane w nim `\n\n` wypisywało się jako cztery znaki zamiast dwóch pustych
  linii. `stos-proba-kontrolna.sh` dostał kontrolę na nawrót — **pada na kodzie
  sprzed naprawy**, przechodzi po niej.

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
