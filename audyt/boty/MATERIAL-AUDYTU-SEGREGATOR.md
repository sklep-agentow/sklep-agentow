# MATERIAŁ DLA AUDYTORA — Segregator maila

**Pomiar: 2026-08-21, 01:30 czasu serwera.** Wszystkie liczby niżej są **zmierzone
tego dnia na tej maszynie**, nie przepisane z wcześniejszych dokumentów. Przy każdej
stoi polecenie, którym ją zdjęto — masz je powtórzyć, jeśli czytasz to później niż
w dniu pomiaru, bo stos żyje.

⛔ **Autor tego dokumentu jest autorem Segregatora.** To materiał do polowania,
**nie raport z audytu**. Nic tu nie jest dowodem, że Segregator działa — dowód na
żywej instalacji robi ktoś inny (patrz §6).

---

## 1. FAKTY ŚRODOWISKA — zmierzone

| co | wartość | czym zmierzone |
|---|---|---|
| WordPress (rdzeń) | **7.0.4** | `docker exec strona_wp_wordpress wp --allow-root core version` |
| PHP w kontenerze WP | **8.4.21** | `docker exec strona_wp_wordpress php -r 'echo PHP_VERSION;'` |
| Baza | **MariaDB 11.8.8** | `docker exec strona_wp_db mariadb --version` |
| Przeglądarka | **Google Chrome for Testing 151.0.7922.34** | `chromium --version` |
| Sterownik przeglądarki | **Playwright 1.62.0** | `playwright --version` |
| Adres witryny | **http://localhost:8890** → HTTP 200 | `curl -o /dev/null -w '%{http_code}' http://localhost:8890/` |
| Łapacz maili | **mailpit**, `http://127.0.0.1:8025` | `docker ps` |
| Serwer IMAP do prób | **greenmail/standalone:2.1.3**, kontener `segm_imap` | `docker ps` |

**Uwaga o wersjach PHP.** Kontener WordPressa ma **8.4.21**, ale nagłówek wtyczki
deklaruje `Requires PHP: 8.1`. Deklaracja 8.1 **nie jest na tym stosie sprawdzana** —
nikt nie uruchomił Segregatora na 8.1. Jeżeli audytujesz obietnicę „działa od 8.1",
to jest obietnica bez pomiaru, nie fakt.

**Uwaga o przeglądarce.** Przeglądarka **jest** na tej maszynie — to znaczy, że
pytanie A4 z planu audytu („czy da się dokończyć każdą ścieżkę klikając?") da się
wykonać naprawdę, a nie odhaczyć z kodu. Dostęp do niej nie jest wpięty w narzędzie
recenzenta i to jest osobna decyzja koordynatorki — ale audytor pracujący na tej
maszynie ma ją pod ręką.

---

## 2. CO DOKŁADNIE AUDYTUJESZ — z SHA, nie ze ścieżką do gałęzi

⛔ **Nie audytuj drzewa roboczego buildera i nie audytuj gałęzi.** Raz już z tego
wyszło zgłoszenie „krytyczny brak funkcji", która na gałęzi głównej stała od
godziny. Bierzesz commit po SHA i tylko commit.

| co | wartość | czym zmierzone |
|---|---|---|
| Gałąź główna, stan na pomiar | `811aba731a485744f7be06d570755fc16c135b6d` | `git rev-parse HEAD` (2026-08-21 01:08) |
| Ostatni commit dotykający wtyczki | `83c921ec53a0456320684d603a6ec1488c18dbeb` | `git log -1 -- wtyczki/segregator-maila/` (2026-08-20 23:23, PR #94) |
| Commit, z którego powstała paczka | `83c921ec53a0456320684d603a6ec1488c18dbeb` | `git log -1 -- boty/paczki/segregator-maila-1.3.0.zip` |
| Wersja w nagłówku wtyczki | **1.3.0** | `bash narzedzia-qa/paczka-zgodnosc.sh segregator-maila` |
| Paczka | `boty/paczki/segregator-maila-1.3.0.zip`, 21 plików | j.w. |
| Suma kontrolna paczki | `f507d8f6ebd689d8ced43eae4ec60788d7fe4baf40107986872505fddadf0e27` | `sha256sum` |

Odtworzenie stanu audytowanego, jednym poleceniem:

```
git checkout 83c921ec53a0456320684d603a6ec1488c18dbeb -- wtyczki/segregator-maila/
```

**Paczka zgadza się z repozytorium co do bajtu** — wszystkie 21 plików ma sumę
SHA-256 równą plikowi z repo, nic nie zostało pominięte przy pakowaniu, zawartość
zgadza się z allowlistą `boty/buduj-paczki.sh`. Sprawdzone bramką, nie na oko.

### ⛔ Ale wersja 1.3.0 NIE JEST ZAMROŻONA — i to jest pierwsze, co masz wiedzieć

`bash narzedzia-qa/paczka-zgodnosc.sh segregator-maila` → **EXIT=1**:

```
  OK       pięć miejsc zgodnych: 1.3.0
  ROZJAZD  CHANGELOG ma niepustą sekcję [Niewydane] (7 pozycji) — kod jest PONAD wersją 1.3.0
```

Wersja mówi **1.3.0** w pięciu miejscach naraz (nagłówek wtyczki, stała `SEGM_WERSJA`,
`Stable tag`, `CHANGELOG.md`, sekcja `== Changelog ==` w `readme.txt`) i wszystkie pięć
jest ze sobą zgodne. Mimo to numer jest nieprawdziwy: w kodzie siedzi **siedem pozycji
ponad wydanie 1.3.0** — m.in. stała `SEGM_KLUCZ_API`, „Przetestuj klucz API", pasek
błędu trybu AI, stronicowanie list, koniec zapytań N+1. Decyzja o numerze (najpewniej
1.4.0) należy do koordynatorki i na dzień pomiaru nie zapadła.

**Co to znaczy dla Ciebie:** znalezisko „instrukcja/CHANGELOG nie opisuje funkcji X"
może być prawdziwe **i jednocześnie już znane** — sprawdź najpierw sekcję `[Niewydane]`
w `wtyczki/segregator-maila/CHANGELOG.md`, zanim zgłosisz to jako lukę dokumentacji.
Zgłaszaj natomiast bez wahania wszystko, czego **nie ma ani w wydaniu, ani w `[Niewydane]`**.

### Skala — przelicz, nie ufaj planowi

| co | zmierzone 2026-08-21 | w `PLAN-AUDYTU-BOTOW.md` |
|---|---|---|
| Segregator (PHP+JS+CSS) | **7 418 linii / 15 plików** | 1 626 linii |
| ChatBot (PHP+JS+CSS) | **6 284 linie / 18 plików** | 1 643 linie |
| razem | **13 702 linie** | 3 269 linii |

`find wtyczki/<bot> -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' \) -print0 | xargs -0 cat | wc -l`

Plan audytu opiera na tamtej liczbie dwie decyzje: „cały kod obu botów mieści się
w jednym oknie z zapasem" oraz „osobny architekt to przerost". **Liczba jest zaniżona
ponad czterokrotnie** — obie decyzje trzeba podjąć na nowo, na tej liczbie.

---

## 3. DZIURY W MAPIE POLOWANIA — czego na niej NIE MA

To nie jest odhaczanie planu. To lista **kategorii bez właściciela**: rzeczy, o które
w obecnym składzie audytorów (A1 security, A2 awaria, A3 dane, A4 front, A5 krawędzie,
A6 dok-vs-kod, Ł łamacz) **nikt nie zapyta**. W poprzednim zleceniu cała kategoria
nigdy nie trafiła na mapę i dlatego przeszła.

### D1 ⛔ Mapy polowania nie ma jako pliku — a warunek końca ją liczy

`PLAN-AUDYTU-BOTOW.md` wymaga w kroku 1 „mapa polowania (cele ⬜)", a warunek końca
nr 1 brzmi **„zero celów ⬜ na mapie polowania (policzone)"**. Takiego pliku w repo
nie ma: `grep -rl "⬜"` po repo daje `DO-PLANU-V2.md` (1 trafienie), `MAPA-SPEC.md`
(16 — to mapa specyfikacji sklepu, nie botów) i `audyt/REJESTR-WERYFIKACJI.md` (3).
**Warunek końca jest dziś niepoliczalny**, więc audyt może się „skończyć" bez
pokrycia czegokolwiek. To dziura nad wszystkimi pozostałymi.

### D2 ⛔ `audyt/mapa-ryzyka.txt` to nadal SZABLON — waga DUŻA nie zapala się prawie nigdy

Plik nadaje znalezisku automatycznie wagę DUŻĄ, gdy trafia w wypisaną ścieżkę.
Zmierzone dopasowania w drzewie `wtyczki/`:

```
includes/Privacy      -> 0 trafien      includes/Migrations  -> 0 trafien
includes/Payments     -> 0 trafien      includes/Schema      -> 0 trafien
includes/Auth         -> 0 trafien      includes/Front/Login -> 0 trafien
uninstall.php         -> 3 trafienia
```

Sześć z siedmiu wpisów to **przykłady z szablonu**, których w tym projekcie nie ma
(ten projekt nie ma katalogów z wielkiej litery — ma `includes/ai.php`,
`includes/skrzynka.php`, `includes/class-segm-imap.php`). Skutek: dane osobowe,
uwierzytelnianie i migracje **nie mają dziś podniesionej wagi**, choć plik twierdzi,
że mają. Do wypełnienia realnymi ścieżkami **przed** rundą A.

### D3 ⛔ Skrzynka IMAP nie ma właściciela — a to jest flagowa funkcja produktu

Segregator ma pełny przebieg na żywej skrzynce: własny klient IMAP (bez rozszerzenia
`imap` w PHP), pobieranie wiadomości, **przenoszenie do folderów na cudzym serwerze
poczty**, zakładanie folderów, historia przebiegów, cofnięcie ostatniego przebiegu
i zadanie WP-Cron. Pliki: `includes/class-segm-imap.php`, `includes/skrzynka.php`,
`includes/skrzynka-przebieg.php`, `includes/admin-skrzynka.php`.

Na mapie **nikt nie ma pytania o protokół**. A2 pyta o awarie ogólnie, A3 o dane
w bazie. Nie ma pytania:
- czy tryb **na sucho naprawdę nie rusza skrzynki** (deklaracja: `EXAMINE` zamiast
  `SELECT`, czyli read-only na poziomie RFC 3501 — to obietnica do **zmierzenia na
  ruchu**, nie do przeczytania w kodzie),
- co zostaje na skrzynce, gdy połączenie padnie **w połowie** przenoszenia partii,
- czy „cofnij ostatni przebieg" cofa to, co naprawdę się stało, czy to, co zapisaliśmy,
- co robi cron, gdy poprzedni przebieg jeszcze trwa.

Do prób stoi gotowy GreenMail (`segm_imap`). **To jest największa dziura na tej liście**:
jedyna funkcja, która dotyka cudzych danych poza naszą bazą, i jedyna, której nikt nie
pilnuje.

### D4 ⛔ Aktualizacja i migracja schematu — kod istnieje, pytania nie ma

`SEGM_WERSJA_SCHEMATU = 3`; `segregator-maila.php:109` porównuje ją z opcją
`segm_wersja_schematu` i **uruchamia migrację tabel**. Na mapie A3 pyta „co przeżywa
deinstalację" — czyli o koniec życia. **Nikt nie pyta o aktualizację**: co się dzieje
z bazą pełną danych przy przejściu 1.2.1 → 1.3.0, czy migracja jest odporna na
powtórzenie, co przy przerwaniu w połowie. `mapa-ryzyka.txt` sama nazywa migracje
obszarem wagi DUŻEJ — i wskazuje na nieistniejący katalog (D2).

### D5 Dwa boty na jednej instalacji — nikt nie pyta o współżycie

ChatBot i Segregator są sprzedawane razem i będą stać na jednym WordPressie.
`wp_schedule_event` rejestrują **oba** (plus sklep) — zmierzone: `chatbot-live/includes/instalator.php`,
`segregator-maila/includes/instalator.php`, `segregator-maila/segregator-maila.php`,
`sklep-agentow/*`. Nikt nie pyta, co się dzieje, gdy oba crony spotkają się w jednym
przebiegu WP-Cron, ani czy deinstalacja jednego bota nie sprząta czegoś wspólnego.

### D6 Skala danych — A5 pyta o krawędzie, nikt o objętość

A5 pokrywa „puste, maksimum, jeden-ponad-limit". Nie pokrywa **realnej objętości**:
tabela z dziesiątkami tysięcy maili, lista przebiegów po roku pracy, próbka przy
pełnym limicie. Że ta klasa jest realna, widać po tym, że w `[Niewydane]` siedzi
naprawa zapytań N+1 na stronie *Reguły* — znaleziona **przypadkiem**, nie przez mapę.

### D7 Wytyczne katalogu wtyczek WordPress.org — brak właściciela

Produkt jest wtyczką sprzedawaną właścicielom stron. Nikt na mapie nie sprawdza
zgodności z wytycznymi katalogu (nazewnictwo prefiksów, `readme.txt`, licencja,
zewnętrzne żądania i ich ujawnienie, brak „phone home" bez zgody). A6 porównuje
dokumentację z kodem — to co innego.

### D8 Przenosiny witryny i import konfiguracji

Segregator eksportuje i importuje reguły plikiem JSON. Nikt nie pyta, co się stanie
przy imporcie na instalację, która **już ma** reguły i kategorie: nadpisanie, duplikaty,
osierocone przypisania. To jedyna ścieżka, którą kupujący sam wnosi dane do wtyczki.

### D9 Zgodność paczki z kodem — jest narzędzie, nie ma pytania

`narzedzia-qa/paczka-zgodnosc.sh` porównuje zip z repo sumami SHA-256, ale na mapie
nikt nie **ma tego w zakresie**. Kupujący dostaje zip, nie repozytorium; jeśli nikt nie
pyta „czy to, co wysyłamy, to to, co audytowaliśmy", audyt sprawdza inny artefakt niż
ten sprzedawany.

### D10 Obietnica sprzedażowa vs to, co działa na skrzynce

Pozycja L8 z `boty/LUKI-SWIEZA-GLOWA-20-08.md`: przebieg na żywej skrzynce klasyfikuje
**wyłącznie regułami** — tryb AI żyje tylko w przebiegu po próbce CSV. Kupujący,
który bierze bota „dla AI", dostaje AI wyłącznie w laboratorium. A6 sprawdza „dok vs
kod", ale nikt nie sprawdza **strony produktu vs kod**, a to tam pada obietnica zakupu.

---

## 4. TRZY WARUNKI OBOWIĄZKOWE DLA TEJ RUNDY

Z destylatu ślepych plam. **Nie są opcjonalne** i nie zastępuje ich czytanie kodu.

### W1 — CZŁOWIEK KLIKAJĄCY

Co najmniej jeden audytor **przechodzi ścieżki klikając w przeglądarce**, nie czyta
o nich w kodzie. Przeglądarka jest na tej maszynie (Chrome for Testing 151, Playwright
1.62 — §1), więc „nie miałem czym" nie jest odpowiedzią.

Minimum do przeklikania: instalacja i pierwsze uruchomienie, dodanie reguły, przebieg
po próbce, „Sprawdź połączenie" ze skrzynką, przebieg **na sucho**, cofnięcie przebiegu,
Ustawienia z kluczem API i „Przetestuj klucz". Do raportu: **kod HTTP, zrzut ekranu
albo treść strony i konsola przeglądarki** — nie zdanie „ścieżka wygląda na kompletną".

### W2 — SUROWE ZAMÓWIENIE KLIENTA, NIE NASZA PRZEPISANA WERSJA

Przed pisaniem raportu przeczytaj **`ZROZUMIENIE.md`, sekcja „SUROWE ZDANIA (verbatim,
pisownia oryginalna)"**. To są słowa zamawiającego, nie nasze streszczenie. Nasze
dokumenty (`MAPA-SPEC.md`, `PLAN-PROJEKTU.md`, opisy produktów) są **interpretacją** —
jeśli interpretacja rozjechała się z oryginałem, to jest znalezisko, i to poważne.

Do raportu: **przytocz co najmniej jedno surowe zdanie zamawiającego** i napisz, czy
kod je spełnia. Raport bez ani jednego cytatu z oryginału jest raportem o naszym
własnym opisie, nie o zamówieniu.

### W3 — SPRAWDZENIE DZIUR W MAPIE

Każdy audytor kończy raport sekcją **„CZEGO NIE BYŁO NA MOJEJ LIŚCIE"** — kategorie,
o które jego pytanie nie pytało, a które przy tym module wyglądają na istotne.
Dziesięć pozycji z §3 to punkt wyjścia, **nie zamknięta lista**; jeśli ich nie
uzupełnisz, znaczy to, że pytanie z mapy Cię ograniczyło, a nie że dziur nie ma.

W poprzednim zleceniu przeszła cała kategoria, bo nikt nie zapytał, czego na mapie
brakuje. Ten warunek istnieje wyłącznie po to, żeby to się nie powtórzyło.

---

## 5. NARZĘDZIA, KTÓRE MASZ POD RĘKĄ

| narzędzie | do czego |
|---|---|
| `bash narzedzia-qa/paczka-zgodnosc.sh segregator-maila` | wersja w pięciu miejscach + paczka równa repo co do bajtu |
| `bash narzedzia-qa/stos.sh <operacja>` | żywy WordPress: strona, opcje, tabele, liczba wierszy, ostatni mail *(wchodzi z PR #103)* |
| `bash narzedzia-qa/bramki.sh` | komplet sześciu bramek jakości |
| `bash testy/dokumentacja-zgodnosc-segregator.sh` | dokumentacja techniczna vs kod |
| `bash testy/instrukcja-liczby-segregator.sh` | każda liczba z instrukcji vs kod |
| kontener `segm_imap` (GreenMail) | próby na skrzynce bez dotykania cudzej poczty |

⛔ **Zero z grepa to nie jest wynik** — to obowiązek zrobienia próby kontrolnej
(sprawdź, że Twój grep w ogóle coś łapie na przypadku, o którym wiesz, że istnieje).

---

## 6. CZEGO AUTOR TEGO DOKUMENTU NIE ROBI

**Dowodu na żywej instalacji Segregatora nie wykonuje builder2**, bo jest jego autorem
i sprawdzałby własną robotę. Robi to builder1 po zamrożeniu ChatBota; builder2 wykonuje
w zamian dowód na żywej instalacji ChatBota. Wymiana jest po to, żeby dowód pochodził
od kogoś, kto nie ma interesu w tym, żeby wyszedł dobrze.

Poza tym niezmierzone i **jawnie niesprawdzone** w tym dokumencie:

- **Nie sprawdziłem Segregatora na PHP 8.1**, choć nagłówek to deklaruje (§1).
- **Nie wypełniłem `mapa-ryzyka.txt`** realnymi ścieżkami — to zmiana w pliku audytu
  i decyzja koordynatorki, a nie autora audytowanego modułu (D2).
- **Nie założyłem brakującej mapy polowania** (D1) z tego samego powodu: autor modułu
  nie powinien wyznaczać celów polowania na własny moduł.
- **Nie przeklikałem niczego w przeglądarce** — warunek W1 należy do audytora, nie do
  autora; moje kliknięcie nie byłoby dowodem.
