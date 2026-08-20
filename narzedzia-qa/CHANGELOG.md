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

- **`.github/workflows/recenzent.yml`** — doszedł krok `Znacznik startu
  recenzji` (pomiar czasu) oraz krok `Bramka: recenzja naprawde sie odbyla`
  z warunkiem `!cancelled()`. Przebieg ubity przez `concurrency` nie jest
  niemym zgonem, tylko naszą własną oszczędnością, więc czerwień za to byłaby
  kłamstwem.
