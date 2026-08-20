# PLAN NAPRAW — Segregator maila · wersja 2 (po krytykach) · cel: 1.2.2

**20.08.2026, noc.** Wersja 1 powstała PRZED krytykami. Ta jest po: **3 zewnętrzne modele**
(oceny planu: 62, 75, 78/100 — wszystkie rozbroiły przynętę) + **nasz krytyk z dostępem do kodu**
+ **żywy test klucza AI**, który wykrył awarię wspólną z ChatBotem.

## KONTEKST (czego krytycy zewnętrzni nie wiedzieli)
Praca egzaminacyjna, termin **27.08**. **Nazwę podał zamawiający — nie zmieniamy.** Brak podpięcia
prawdziwej skrzynki (IMAP/OAuth) to **jego świadoma decyzja**: na egzamin produkt działa na próbkach.
W pierwszej rundzie 4 modele dały temu produktowi 15–49/100 i **wszystkie z tego samego powodu**:
„nazywa się segregator, a nie segreguje — brak efektu końcowego".

---

## 🔴 P0 — MUSI

| # | Co | Dowód |
|---|---|---|
| **P0.1** | **TRYB AI NIE DZIAŁA — napraw.** `includes/ai.php:118` wysyła `output_config → effort`, model `claude-haiku-4-5` tego nie obsługuje → API 400 → cichy zjazd do reguł. **Identyczna linia w ChatBocie — to KLASA, naprawiamy jednym przejściem.** | żywe wywołanie: `HTTP 400, "This model does not support the effort parameter"`; próba kontrolna bez tego pola = 200 |
| **P0.2** | **Fałszywy sukces przy zapisie — 6 miejsc:** `silnik.php:151-163`, `csv.php:118`, `instalator.php:99,124,186`, `admin.php:433,573`. `$wpdb->insert()` bez sprawdzenia wyniku, użytkownik i tak widzi sukces. | nasz krytyk, plik:linia |
| **P0.3** | **RETENCJA — zero czyszczenia.** Grep `cron\|wp_schedule` = 0 trafień. Do bazy trafia PRAWDZIWA cudza korespondencja i zostaje na zawsze. Cron 90 dni (wzór 1:1 z ChatBota) + przycisk „wyczyść próbkę teraz" + wyrejestrowanie przy deaktywacji + wpis w uninstall. | 2/4 krytyków + potwierdzone w kodzie |
| **P0.4** | **Zgoda przed wysłaniem CUDZEJ korespondencji do AI.** W trybie AI treść maili osób trzecich wychodzi do zewnętrznego dostawcy. Sam komunikat to za mało — potrzebne wyraźne, jednorazowe potwierdzenie właściciela przed włączeniem trybu AI. | 2/3 krytyków — **nowe, nie było w wersji 1** |

## 🟠 P1 — EFEKT KOŃCOWY (odpowiedź na zarzut 4/4 z rundy 1)

| # | Co | Uwaga |
|---|---|---|
| **P1.1** | **Uczciwe pierwsze zdanie** na karcie i stronie produktu. Dziś: „rozdziela pocztę do kategorii" (sugeruje żywą skrzynkę), prawda dopiero kilka ekranów niżej. Nowe: „Sprawdza Twoje reguły segregacji na próbce maili i pokazuje raport z liczbami — zanim wpuścisz je na żywą skrzynkę." | 4/4 z rundy 1 + 3/3 z rundy 2. **Najtańsza poprawka o największym wpływie.** |
| **P1.2** | **Baner w panelu przy pierwszym uruchomieniu**: „To narzędzie NIE łączy się z Twoją skrzynką." | jw. |
| **P1.3** | ⭐ **EKSPORT FILTRÓW** — potwierdzony przez 3/3 krytyków jako „jedyny słuszny zwrot", nie plaster. ⚠️ **ALE Z TWARDYM WARUNKIEM:** nasz silnik działa „pierwsza pasująca reguła wygrywa i STOP", a **Gmail stosuje wszystkie pasujące filtry naraz** — eksport 1:1 dałby na żywej skrzynce INNY wynik niż nasz raport. Outlook ma „przestań przetwarzać kolejne reguły", więc tam odwzorowanie jest pełne. **Decyzja: eksportujemy z JAWNYM ostrzeżeniem o tej różnicy + mapowanie tylko reguł przekładalnych; kubeł domyślny „Reszta" NIE jest eksportowalny i trzeba to napisać wprost.** | 3/3 zewnętrznych + nasz krytyk (semantyka) |
| **P1.4** | **Test akceptacyjny eksportu** (bez tego P1.3 jest obietnicą): 30 maili → reguły → raport → eksport → import do prawdziwego Gmaila → wiadomości testowe → wynik w Gmailu MUSI zgadzać się z raportem albo różnica MUSI być opisana. | wymóg 2/3 krytyków |

## 🟡 P2 — bezpieczeństwo importu i drobne

| # | Co | Uwaga |
|---|---|---|
| **P2.1** | **Kodowanie CSV** — dziś zdejmowany tylko znacznik UTF-8 (`csv.php:52,66`); plik z Excela (Windows-1250) wchodzi jako krzaki na polskich znakach. Wykryj i przekonwertuj. | nasz krytyk — **nie było w wersji 1** |
| **P2.2** | **Pętla czyta plik DO KOŃCA** mimo limitu 500 wierszy (`csv.php:60,93-96` — tylko liczy pominięte). Plik z milionem linii i tak zostanie przemielony → ryzyko wyczerpania pamięci. **Przerwij czytanie po limicie** + limit rozmiaru w bajtach + walidacja typu pliku. | nasz krytyk + 2/3 zewnętrznych (streaming/pamięć) |
| **P2.3** | **Wyścig: `TRUNCATE` w trakcie przebiegu.** `csv.php:116`, `admin.php:683` czyszczą tabelę bez blokady, a `silnik.php:76` w tym czasie ją czyta. Dwie karty = przebieg policzony na pustej próbce, bez ostrzeżenia. | nasz krytyk |
| **P2.4** | **Wstrzyknięcie formuły w eksporcie CSV** — treść maila zaczynająca się od `=`, `+`, `-`, `@` wykona się w arkuszu. | 2/3 krytyków |

## ⛔ WYPADA / OBALONE
- **Drugi dostawca AI (Gemini)** — 7 głosów przeciw na 7. Po egzaminie.
- **„Rozdzielenie komunikatu o prywatności (tryb AI)"** — **JUŻ JEST W KODZIE** (`admin.php:735-736`
  + INSTRUKCJA §7). Wersja 1 wpisała to jako brak, bo przepisałam zarzut krytyków zamiast sprawdzić
  u siebie. **Moja wpadka — dokładnie ta, przed którą ostrzega nasza zasada „weryfikuj negatywne
  twierdzenia samodzielnie".** Zostaje P0.4 (zgoda), bo to co innego niż informacja.
- **„Brak walidacji liczby kolumn przy imporcie"** — **JEST** (`csv.php:80-92`, twarde odrzucenie).
- **„Brak escapowania w raportach"** — **JEST**, 43 wystąpienia w `admin.php`.
- **Zmiana nazwy produktu** — nazwa pochodzi z zamówienia.

## 📄 PO ZAMROŻENIU KODU
Dokumentacja techniczna (schemat 5 tabel, format CSV i kodowanie, zaczepy) + diagram przepływu:
próbka → reguły → kategorie → raport → filtry do skrzynki.

## ⛔ CZEGO NIE ROBIMY (droga rozbudowy w instrukcji)
IMAP/OAuth · automatyczne przenoszenie maili w skrzynce · uczenie się z korekt · wykrywanie
subskrypcji · obsługa załączników · wiele skrzynek.
