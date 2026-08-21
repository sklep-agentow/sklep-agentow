# MATERIAŁ DLA AUDYTORA — ChatBot LIVE

**Pomiar: 2026-08-21, po zamrożeniu 1.5.8.** Wszystkie liczby niżej są **zmierzone
tego dnia na tej maszynie**, nie przepisane z wcześniejszych dokumentów. Przy każdej
stoi polecenie, którym ją zdjęto — masz je powtórzyć, jeśli czytasz to później niż
w dniu pomiaru, bo stos żyje.

⛔ **Autor tego dokumentu jest autorem ChatBota.** To materiał do polowania,
**nie raport z audytu**. Nic tu nie jest dowodem, że ChatBot działa — dowód na żywej
instalacji robi ktoś inny (patrz §6).

---

## 1. FAKTY ŚRODOWISKA — zmierzone

| co | wartość | czym zmierzone |
|---|---|---|
| WordPress (rdzeń) | **7.0.4** | `docker exec strona_wp_wordpress wp --allow-root core version` |
| Obraz kontenera WP | **wordpress:6.9.4-php8.4-apache** | `docker inspect -f '{{.Config.Image}}' strona_wp_wordpress` |
| PHP w kontenerze WP | **8.4.21** | `docker exec strona_wp_wordpress php -r 'echo PHP_VERSION;'` |
| Baza | **MariaDB 11.8.8** | `docker exec strona_wp_db mariadb --version` |
| Przeglądarka | **Google Chrome for Testing 151.0.7922.34** | `chromium --version` |
| Sterownik przeglądarki | **Playwright 1.62.0** | `playwright --version` |
| Adres witryny | **http://localhost:8890** → HTTP 200 | `curl -o /dev/null -w '%{http_code}' http://localhost:8890/` |
| Łapacz maili | **mailpit**, `http://127.0.0.1:8025` | `docker ps` |

**Uwaga o wersji WordPressa — nie pomiń jej.** Rdzeń melduje **7.0.4**, a obraz kontenera
nazywa się **6.9.4**: rdzeń zaktualizował się w środku kontenera. Jeżeli w raporcie
napiszesz „testowane na WP z obrazu", napiszesz nieprawdę. Liczy się `core version`.

**Uwaga o PHP.** Kontener ma **8.4.21**, a nagłówek wtyczki deklaruje `Requires PHP: 8.1`
i `Requires at least: 6.0` (WordPress). **Żadna z tych dwóch dolnych granic nie została
u nas ani razu uruchomiona.** To są obietnice bez pomiaru, nie fakty.

**Uwaga o przeglądarce.** Przeglądarka **jest** na tej maszynie, więc warunek W1
(§4) da się wykonać naprawdę, a nie odhaczyć z kodu.

---

## 2. CO DOKŁADNIE AUDYTUJESZ — z SHA, nie ze ścieżką do gałęzi

⛔ **Nie audytuj drzewa roboczego buildera i nie audytuj gałęzi.** Bierzesz commit
po SHA i tylko commit.

| co | wartość | czym zmierzone |
|---|---|---|
| Gałąź główna, stan na pomiar | `807d72d61c07108a11a934e4d2b74f691ea7c495` | `git rev-parse HEAD` |
| Ostatni commit dotykający wtyczki | `807d72d` (2026-08-21 09:22, PR #126) | `git log -1 -- wtyczki/chatbot-live/` |
| Commit, z którego powstała paczka | `807d72d` | `git log -1 -- boty/paczki/` |
| Wersja w nagłówku wtyczki | **1.5.8** | `bash narzedzia-qa/paczka-zgodnosc.sh chatbot-live` |
| Wersja schematu bazy | **7** (`CHATL_WERSJA_SCHEMATU`) | `chatbot-live.php:19` |
| Paczka | `boty/paczki/chatbot-live-1.5.8.zip`, **26 plików** | j.w. |
| Suma kontrolna paczki | `cab1a31c5133ad8f7ea88968554447e0b92d91f644a062b8f89832125c9a4e97` | `sha256sum` |

Odtworzenie stanu audytowanego, jednym poleceniem:

```
git checkout 807d72d61c07108a11a934e4d2b74f691ea7c495 -- wtyczki/chatbot-live/
```

### Wersja 1.5.8 JEST zamrożona — sprawdzone bramką, nie na słowo

```
$ bash narzedzia-qa/paczka-zgodnosc.sh chatbot-live
  OK       pięć miejsc zgodnych: 1.5.8
  OK       sekcja [Niewydane] jest pusta — kod odpowiada wydanej wersji
  OK       wszystkie 26 plików zipa ma sumę SHA-256 równą plikowi z repo
  OK       żaden plik wtyczki nie został pominięty przy pakowaniu
  OK       zip zgadza się z allowlistą buduj-paczki.sh (26 pozycji)
  OK       CHANGELOG.md: wpis [1.5.8] ma treść · readme.txt: sekcja = 1.5.8 = ma treść
  OK       w 26 plikach paczki nie ma śladów wewnętrznych (skaner 1.1.0)
PACZKA ZGODNA Z REPOZYTORIUM I WERSJA SPOJNA — mozna zamrazac                  EXIT=0
```

⚠️ **PUŁAPKA: obok leży STARA paczka.** W `boty/paczki/` są jednocześnie
`chatbot-live-1.5.7.zip` i `chatbot-live-1.5.8.zip` (oraz dwie Segregatora). Audytujesz
**1.5.8**. Zamrożenie już raz się cicho rozjechało: PR poprawiający dokumentację zmienił
trzy pliki wtyczki i **nie przebudował paczki**, przez co przez kilka godzin zip na
gałęzi głównej różnił się od repozytorium — złapała to dopiero bramka zgodności.
Jeśli uruchamiasz cokolwiek na paczce, **wypisz w raporcie jej sumę kontrolną**.

### 1.5.8 to wydanie DOKUMENTACYJNE — i to jest istotne dla polowania

W kodzie wykonywalnym nie zmieniło się **nic** względem 1.5.7 (`git diff` dotknął
`CHANGELOG.md`, `DOKUMENTACJA-TECHNICZNA.md`, `readme.txt` i nagłówka z numerem wersji).
Zmiana polegała na tym, że instrukcja i opis produktu **przestały kłamać**: do 1.5.7
pisaliśmy, że bot nie ma czatu operatora i nie pamięta rozmowy, a obie te rzeczy weszły
do produktu. **Poluj więc na resztę takich zdań** — klasa „kod urósł, opis nie" jest
u nas potwierdzona, nie hipotetyczna.

### Skala — przelicz, nie ufaj planowi

| co | ile | czym zmierzone |
|---|---|---|
| Pliki PHP wtyczki | **15** | `find wtyczki/chatbot-live -name '*.php' \| wc -l` |
| Linie PHP | **6734** | `cat $(find … -name '*.php') \| wc -l` |
| Linie JS + CSS | **1191** | `wc -l wtyczki/chatbot-live/assets/*` |
| Tabele własne | **6** (`CREATE TABLE` w instalatorze) | `grep -c "CREATE TABLE" includes/instalator.php` |
| Tabele kasowane przy odinstalowaniu | **6** (`DROP TABLE`) | `grep -c "DROP TABLE" uninstall.php` |
| Punkty AJAX | **9** (`wp_ajax_*` + `wp_ajax_nopriv_*`) | `grep -rhoE "wp_ajax(_nopriv)?_[a-z_]+" includes/` |
| Trasy REST | **1** (`chatl/v1/rozmowa`) | `grep -rn "register_rest_route" includes/` |
| Podstrony panelu | **7** (`add_submenu_page`) + 1 pozycja główna | `grep -rn "add_submenu_page(" includes/` |
| Zadania cykliczne | **1** (`chatl_czyszczenie_historii`, dziennie) | `grep -rn "wp_schedule_event" includes/` |
| Skrypty testowe dotykające ChatBota | **43** | `grep -rl "chatl_\|chatbot-live" testy/` |

**6 = 6 to jeszcze nie jest dowód symetrii** — to zgodność LICZB. Czy kasowana jest ta
sama szóstka, sprawdź nazwa po nazwie; to jedno z naszych siedmiu powtarzalnych potknięć.

---

## 3. DZIURY W MAPIE POLOWANIA — czego na niej NIE MA

Mapa (`audyt/boty/material/06-mapa-polowania.md`, **36 celów**) jest wspólna dla obu
botów i pisana zanim ChatBot dostał połowę swoich dzisiejszych funkcji. Poniżej to,
czego przy TYM module na niej nie widzę. **To punkt wyjścia, nie zamknięta lista** (W3).

### D1 ⛔ Przejęcie rozmowy przez człowieka — największa funkcja, zero celów

Mapa nie ma **ani jednego** celu o przejęciu, a to jest jedyna trasa REST w produkcie
(`chatl/v1/rozmowa`, `includes/przejecie.php`) i jedyne miejsce, gdzie **gość i operator
piszą do tej samej rozmowy**. Do sprawdzenia: `permission_callback` tej trasy, sekret
sesji wymagany także **przy zapisie** (żeby ktoś ze samym identyfikatorem nie podszył
się pod gościa), co się dzieje, gdy operator i bot odzywają się równocześnie, oraz czy
po pięciu minutach ciszy (`CHATL_PRZEJECIE_CISZA = 300`) rozmowa wraca do bota **i gość
się o tym dowiaduje**.

### D2 ⛔ Koszt odpytywania — nikt nie pyta, ile żądań robi jedna otwarta karta

Widget i panel operatora **odpytują w pętli** (`CHATL_ODPYT_CISZA = 120`). Mapa ma
ogólne „co, jeśli przyjdzie 500 żądań w minutę", ale nie ma pytania o ruch, który
wtyczka generuje **sama z siebie** na hostingu współdzielonym. Policz żądania na godzinę
z jednej otwartej karty i z dziesięciu.

### D3 ⛔ Pułapka na boty a PRAWDZIWY gość — pytanie o fałszywy alarm

Mapa pyta, czy zabezpieczenia działają. **Nie pyta, czy nie łapią człowieka.**
U nas już raz łapały: próg czasu przy formularzu kontaktu odrzucał prawdziwego gościa,
a wyszło to dopiero w teście przeglądarkowym. Dziś próg (`CHATL_PULAPKA_SEKUND = 2`)
działa **tylko przy pytaniu do bota**. Sprawdź to jako fałszywy alarm, nie jako funkcję:
czat otwarty i pytanie zadane w mniej niż dwie sekundy = gość dostaje tekst awaryjny
i **żaden wiersz rozmowy nie powstaje**. Ta sama pułapka złapała nasze własne narzędzie
QA i przez to dziura D3 z dowodu z paczki stała otwarta (§5).

### D4 ⛔ Wnioski RODO — mapa ma retencję i odinstalowanie, nie ma kreatora

`includes/rodo.php` wpina się w **eksporter i kasownik danych osobowych WordPressa**.
Mapa o tym nie pyta ani słowem. Do sprawdzenia: czy eksport zawiera rozmowy i zgłoszenia,
czy kasownik naprawdę je usuwa, co zostaje po skasowaniu (zapisany jest „ślad" wniosku
w `wp_options` — czy to nie jest dana osobowa) i czy przy dwóch równoczesnych wnioskach
jeden nie nadpisuje śladu drugiego (granica nazwana w kodzie wprost).

### D5 ⛔ Pamięć rozmowy i wyciek między sesjami

Historia bieżącej rozmowy trafia do modelu (`chatl_ai_historia()`), a identyfikator sesji
żyje w `sessionStorage`. Mapa nie pyta, czy historia **nie wycieka do innej sesji** —
a to pytanie o cudze dane, nie o wygodę. Sprawdź dwie karty, dwie przeglądarki i powrót
po zamknięciu karty.

### D6 Stronicowanie trzech list — świeże i policzalne

Weszło w 1.5.6. Klasyczne miejsca na błędy: strona poza zakresem, ostatnia strona
niepełna, zmiana danych między stronami (wiersz widziany dwa razy albo pominięty).
Mapa nie ma o tym celu.

### D7 Koszt trybu AI — liczby są, ale nikt ich nie porównał z rachunkiem

Wtyczka zapisuje **cztery** pola zużycia (`input_tokens`, `output_tokens`,
`cache_creation_input_tokens`, `cache_read_input_tokens`) i pokazuje je właścicielowi.
Nikt nie porównał tych liczb z rachunkiem u dostawcy, a odczyt z cache kosztuje inaczej
niż zapis. Zawyżony albo zaniżony licznik to obietnica sprzedażowa, nie kosmetyka.

### D8 Dwa boty na jednej instalacji

ChatBot i Segregator mają własne prefiksy, własne crony i własne pozycje w menu, ale
**nikt nie postawił ich obok siebie** na jednej instalacji i nie sprawdził współżycia
(kolejność menu, dwa zadania cykliczne, dwa uninstall.php).

### D9 Zgodność paczki z kodem — narzędzie jest, pytania na mapie nie ma

`paczka-zgodnosc.sh` porównuje zip z repo co do bajtu i sprawdza wersję w pięciu
miejscach. Mapa nie ma celu „czy paczka, którą audytuję, odpowiada commitowi, który
audytuję" — a właśnie ta klasa nas ugryzła (§2).

---

## 4. TRZY WARUNKI OBOWIĄZKOWE DLA TEJ RUNDY

### W1 — CZŁOWIEK KLIKAJĄCY

Co najmniej jeden audytor **przechodzi ścieżki klikając w przeglądarce**. Przeglądarka
jest na tej maszynie (§1), więc „nie miałem czym" nie jest odpowiedzią.

Minimum do przeklikania: instalacja z **paczki** i ekran powitalny, dodanie wpisu Q&A,
**import CSV z podglądem** (w tym plik z polskiego Excela: średnik i windows-1250),
rozmowa gościa na froncie, **przejęcie rozmowy w panelu i oddanie jej botowi**,
zgłoszenie kontaktu, Ustawienia z kluczem API i „Przetestuj klucz", Statystyki i luki,
stronicowanie każdej z trzech list. Do raportu: **kod HTTP, zrzut ekranu albo treść
strony i konsola przeglądarki**.

### W2 — SUROWE ZAMÓWIENIE KLIENTA, NIE NASZA PRZEPISANA WERSJA

Przed pisaniem raportu przeczytaj `ZROZUMIENIE.md`, sekcja **„SUROWE ZDANIA (verbatim)"**.
Nasze dokumenty są **interpretacją**. Przy tym module pytanie brzmi w szczególności:
czy „LIVE" w nazwie ma dziś pokrycie i czy strona produktu obiecuje dokładnie to, co bot
robi. Przytocz **co najmniej jedno surowe zdanie zamawiającego**.

### W3 — SPRAWDZENIE DZIUR W MAPIE

Raport kończy sekcja **„CZEGO NIE BYŁO NA MOJEJ LIŚCIE"**. Dziewięć pozycji z §3 to
punkt wyjścia. Jeśli ich nie uzupełnisz, znaczy to, że pytanie z mapy Cię ograniczyło,
a nie że dziur nie ma.

---

## 5. NARZĘDZIA, KTÓRE MASZ POD RĘKĄ

| narzędzie | do czego |
|---|---|
| `bash narzedzia-qa/paczka-zgodnosc.sh chatbot-live` | wersja w pięciu miejscach + paczka równa repo co do bajtu + ślady wewnętrzne |
| `bash narzedzia-qa/dowod-z-paczki.sh chatbot-live` | czysta instalacja z zipa: aktywacja bez notice, tryb regułowy bez klucza, odinstalowanie z pustą różnicą bazy |
| `bash narzedzia-qa/dowod-zachowania-chatbot.sh` | odcięcie klucza w trakcie, nonce z cache (L1), retencja 91 dni — na PACZCE |
| `bash narzedzia-qa/stos.sh <operacja>` | żywy stos, tylko odczyt; `wiersze-gdzie wp_chatl_rozmowy.silnik=ai` liczy z warunkiem |
| `bash narzedzia-qa/bramki.sh` | komplet siedmiu bramek jakości |
| `bash testy/dokumentacja-zgodnosc.sh` | dokumentacja techniczna i diagram vs kod |
| 18 prób kontrolnych w `testy/` | każdy strażnik ma własną próbę: „czy w ogóle umie paść" |

⛔ **Zero z grepa to nie jest wynik** — to obowiązek zrobienia próby kontrolnej.

### Co ten dowód z paczki mówi o trybie AI (przeczytaj, zanim zaczniesz go badać)

`dowod-zachowania-chatbot.sh` na paczce **1.5.8** daje dziś **EXIT=0** i pokazuje pełny
cykl trybu AI **na ścieżce gościa**: przy działającym API odpowiedź idzie z modelu
(`silnik = ai` zapisany w bazie przez samego bota), po odcięciu klucza bot **zjeżdża do
reguł**, znacznik awarii zapisuje się z kodem **401**, panel pokazuje właścicielowi
ostrzeżenie, a po powrocie API znacznik **znika sam**.

⚠️ **Ale model jest UDAWANY** — to własny punkt końcowy w kontenerze podpięty filtrem
`chatl_ai_url`, nie prawdziwy dostawca. Dowód mówi „wtyczka poprawnie obsługuje
odpowiedź i awarię", **nie** „model odpowiada sensownie". Dowód z prawdziwym dostawcą
na ścieżce gościa mamy **tylko dla wersji sprzed zamrożenia** (`audyt/boty/DOWODY-DEKLARACJI.md`,
sekcja „tryb AI ODPOWIEDZIAŁ GOŚCIOWI"), bo klucz na stanowisku jest dziś nieważny
(HTTP 401). **Dla 1.5.8 tego dowodu NIE MA** i bez ważnego klucza nie powstanie.

---

## 6. CZEGO AUTOR TEGO DOKUMENTU NIE ROBI

**Dowodu na żywej instalacji ChatBota nie wykonuje jego autor.** Dowód z paczki dla
ChatBota zrobił builder2 (PR #119), a ja w zamian zrobiłem go dla Segregatora (#123).
Wymiana jest po to, żeby dowód pochodził od kogoś, kto nie ma interesu w tym, żeby wyszedł.

Poza tym **jawnie niesprawdzone przeze mnie**, i to są miejsca, w które sam bym poszedł:

- **Nie kliknąłem w tej rundzie ani razu w przeglądarce.** Wszystko, co mam, jest
  z bramek, skryptów i bazy. Testy przeglądarkowe w `testy/*.js` istnieją, ale **nie
  uruchamiałem ich dzisiaj** — a to właśnie one złapały u nas fałszywy alarm pułapki.
- **Nie sprawdziłem ChatBota na PHP 8.1 ani na WordPressie 6.0**, choć nagłówek
  deklaruje obie te dolne granice (§1).
- **Nie sprawdziłem panelu z konta bez uprawnień** (subskrybent, redaktor). Nonce
  i uprawnienia są w kodzie parami, ale nie przeszedłem tego kliknięciem ani curl-em.
- **Nie badałem współbieżności.** Kubły limitu czyszczę przed testami
  (`DELETE FROM chatl_rate_limit`), więc **żaden mój pomiar nie mówi, jak wtyczka
  zachowuje się przy pełnym kuble i równoległych żądaniach**.
- **Import CSV robiłem na końcu i w pośpiechu.** Sprawdzone: BOM, średnik, windows-1250,
  duplikaty, limity rozmiaru. **Niesprawdzone**: plik na granicy 2000 wierszy na wolnym
  hostingu, przerwane wysyłanie, plik z cudzysłowami w środku pola.
- **Stronicowanie sprawdziłem tylko na spokojnej bazie** — nie na takiej, która zmienia
  się między jedną stroną a drugą (D6).
- **Statystyk i raportu luk nie przeliczyłem ręcznie.** Liczby wychodzą z zapytań, których
  nikt nie porównał z liczeniem na piechotę na dużej próbce.
- **Nie zaglądałem w ogóle** w: wielojęzyczność i RTL, multisite, zachowanie przy
  `DISABLE_WP_CRON`, eksport CSV przy bazie z dziesiątkami tysięcy wierszy.
- **Nie wypełniłem `audyt/mapa-ryzyka.txt`** ani nie dopisywałem celów do mapy polowania —
  autor modułu nie powinien wyznaczać celów polowania na własny moduł. Dziury z §3
  zgłaszam jako **braki**, nie jako gotowe cele.

### Ostatnia rzecz, którą chcę powiedzieć wprost

Trzy pozycje dowodu D3 przez cały dzień meldowały wadę produktu, a **wadą było moje
narzędzie**: pytało bota szybciej, niż pozwala próg pułapki, czytało brak opcji jako
komunikat błędu wp-cli i szukało w panelu zdania, którego panel nie pisze. Naprawiłem
to i D3 wyszło na zielono.

Potem okazało się, że **naprawiłem tylko jedno wystąpienie z pięciu**: ten sam wzorzec
(`wp option get` w teście pustości, gdzie brak opcji wygląda jak jej obecność) siedział
jeszcze w czterech miejscach, w tym w **strażniku luki L1** — meldował „odrzucony nonce
ZOSTAŁ ODNOTOWANY" zawsze, także gdyby nic się nie odnotowało. Wyszło to dopiero
z `grep`-a po całym katalogu, nie z pamięci. Osobno: `dowod-aktualizacji.sh` wymagał,
żeby **każda** aktualizacja podnosiła wersję schematu, więc na wydaniu dokumentacyjnym
(1.5.7 → 1.5.8) meldował dwie wady, których nie ma.

**Trzy wnioski dla Ciebie, w kolejności ważności:**

1. Jeśli mój przyrząd pokaże Ci **czerwone — sprawdź najpierw przyrząd**.
2. Jeśli pokaże **zielone — sprawdź, czy cokolwiek zmierzył** (strażnik, który melduje
   sukces przy pustej bazie, jest gorszy niż jego brak).
3. Po każdej naprawie zapytaj **„gdzie jeszcze to samo"** i sprawdź to **wyszukiwaniem,
   nie pamięcią** — ta klasa wróciła u nas dziś **pięć razy**, u trzech różnych osób,
   raz kilkanaście minut po tym, jak ktoś ją komuś wytknął.
