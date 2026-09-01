# PRZEKAZANIE — czego NIE zdążyłem sprawdzić. Lista zadań, nie przypis.

**Kto pisze:** audytor białych plam, sesja 2026-09-01 (nocna).
**Dla kogo:** następna sesja audytu — ma z tego wystartować **bez rozmowy z koordynatorką**.
**Stan gałęzi:** `audyt/biale-plamy-01-09`, ostatni commit `1ec1e36` na `origin`.
**Zamknięte raportem:** `A4b`, `F5`, `F4` (+ dodatek prawny), `A6`, `C12`, `E5`, `C13` —
siedem plików `audyt/plamy/*-RAPORT.md`. **Otwarte z tabeli briefingu: `A7`.**

⛔ **Jedna uwaga metodyczna, zanim cokolwiek stąd weźmiesz.** Jeśli wchodzisz na `A7`
(kod produktu naszą siatką), rób to **w ciemno** — nie czytaj moich siedmiu raportów.
Drugi przebieg, który zobaczy cudzą listę, znajduje dokładnie ją, i przepadają oba pomiary
(ile nowego daje drugie spojrzenie · który podział pracy jest lepszy). Ta lista jest wyjątkiem:
to zadania, których **nikt** nie zrobił, a nie znaleziska.

⛔ **Zasada, którą łamie każdy, kto czyta taką listę pobieżnie:** „nie mogłem sprawdzić"
**nie jest dyspensą**. Każda pozycja niżej ma prawo wrócić w kolejnym raporcie **tylko po
świadomej decyzji**, że tego nie zamykamy — inaczej jest zadaniem z listy.

---

## ⭐ TRZY POZYCJE PIERWSZEJ WAGI (wskazane wprost przez koordynatorkę)

### 1. Płatność w **Firefoksie i Edge** — czy `A4b-F1` bije wszędzie

**Dlaczego to pilne:** `A4b-F1` (KRYT, sklep z kluczem publikowalnym nie sprzedaje) zmierzyłem
**wyłącznie na chromium**. Teza mówi, że przyczyną jest kształt obiektu z `checkout.loadActions()`,
a nie silnik przeglądarki — więc **nie powinno** zależeć od przeglądarki. **Ale tego nie zmierzyłem**,
a kryterium `rubryka-GOTOWE.md` #11 wymienia Chrome/Edge/Firefox z nazwy.

**Jak to zrobić (przyrząd gotowy, trzeba tylko podmienić silnik):**
```bash
# skrypt mierzy ślepy zaułek po kliknięciu „Zapłać”; dziś startuje chromium
flock /root/praca/zlecenia/wymiana/stos.lock node audyt/plamy/A4b-sonda-slepy-zaulek.js
```
W skrypcie zamień `chromium.launch({ executablePath: wymagajChromium() })` na `firefox.launch(…)`
oraz na kanał `msedge` (wzór działającego wywołania Edge: `testy/e2e/szelki-strona-produktu.js`,
tam `channel: 'msedge'` jest już zmierzone i przeszło).
**Czego szukać:** czy `pageerror` niesie `TypeError: actions.confirm is not a function`
i czy przycisk zostaje na „Przetwarzam…" z pustym, schowanym komunikatem.
**Warunek ważności pomiaru:** jeśli builder zdążył naprawić `A4b-F1`, ten test ma **przejść** —
wtedy zmierz go na commicie **sprzed** naprawy, inaczej mierzysz naprawę, nie klasę.

### 2. Kasowanie po **90 dniach** — zdarzenie, którego nie dało się zaobserwować

**Dlaczego zostało otwarte:** mechanizm retencji **istnieje i jest wołany** (dowód w `F4-RAPORT.md`
§①), ale na stanowisku **nie ma ani jednego wiersza starszego niż 14 dni** (najstarszy `2026-08-18`).
Zdarzenie, które ma zajść po 90 dniach, **nie mogło zajść** — więc nie mylę „nie zaszło"
z „nie działa" i **nie orzekam, że kasowanie działa**.

**Czego NIE zrobiłem i dlaczego:** wstawienia wiersza z datą sprzed 91 dni. To **zapis do bazy**
na stosie dzielonym z builderem — świadomie nie ruszałem.

**Jak to zrobić uczciwie:** na **osobnym** stanowisku (albo po uzgodnieniu okna z builderem):
```
1. wstaw do wp_sklepag_zdarzenia i wp_sklepag_logowania po wierszu z czas = NOW() - 91 dni
2. wstaw też po wierszu z czas = NOW() - 89 dni   ← PRZYPADEK BEZ WADY, obowiązkowy
3. uruchom sklepag_dociagnij_zalegle( 0 )
4. asercja: wiersz 91-dniowy ZNIKA, wiersz 89-dniowy ZOSTAJE
```
⛔ Bez kroku 2 test przejdzie także wtedy, gdy „naprawa" wykasuje wszystkim wszystko.
**To jest kandydat na strażnika**, nie na jednorazowy pomiar — dziś `F4` nie ma żadnego.

### 3. **~30 z 36 wierszy `DECYZJE.md`**, których nie przeszedłem po kolei

**Co zrobiłem:** wybrałem jedną rodzinę — „architektura sprzed piwotu" — i sprawdziłem ją
**w całości** (5 wierszy: `D1`, `D3`, `D4`, `D13`, `D15`), plus zgodność wersji produktu.
**Czego NIE zrobiłem:** pozostałych ~30 wierszy jeden po drugim wobec dzisiejszego kodu.

**Jak to zrobić:** dla każdego wiersza zadaj **dwa** pytania, nie jedno:
1. *(stare)* czy DECYZJA opisuje dzisiejszy produkt?
2. ⭐ *(to, które przy `D3` okazało się ważniejsze)* czy jej **UZASADNIENIE** nadal się broni?
   Przy `D3` decyzja była nieaktualna, ale gorsze było to, że **powód jej podjęcia
   został odwrócony** — odrzucono wspólny origin z gospodarzem jako ryzyko kradzieży sesji,
   a panel stoi dziś w `wp-admin` tej samej witryny i nikt nie zapisał, czemu to teraz OK.
   **Tego nie widać, jeśli pyta się tylko o pierwszą kolumnę tabeli.**
3. Osobno: kolumna **„odrzucone alternatywy"** — czy któraś z nich nie jest przypadkiem tym,
   co dziś robimy. Sprawdziłem to **tylko przy `D3`**, gdzie rzucało się w oczy.
4. Osobno: kolumna **„Kto"** — czy decyzje oznaczone „klepnięte przez właściciela" mają pokrycie
   w cytatach z czatu. **Nie sprawdzałem ani jednej.**

**Domknij też:** czy `D29` i `D30` kiedykolwiek istniały (`git log -p -- DECYZJE.md`) —
dziś ich nie ma i nie wiadomo, czy zgubione, czy numery przeskoczone (`E5-F2`).

---

## POZOSTAŁE ZADANIA, wg plamy

### `A4b` — front
- **BLIK w panelu osadzonym** — kafelek widoczny, nieklikany. Nie mam konta testowego BLIK.
- **3-D Secure** — testowałem wyłącznie `4242…`; ścieżka z ramką 3DS nietknięta.
  Karta do użycia: `4000 0027 6000 3184`.
- **Trzecia szerokość (tablet ~768 px)** — mierzyłem 1440 i 390.
- ⛔ **`axe` nie obejmuje strony „Dziękujemy" ani stanu „panel płatności otwarty".**
  `testy/e2e/axe-3-podstrony.js:31` ma na sztywno trzy adresy: `/sklep/`, `/produkt/chatbot-live/`,
  `/produkt/segregator-maila/`. Strona, na którą trafia **każdy kupujący**, nie jest objęta niczym.
- **Czy `loadActions()` zwracał `{type,actions}` już w 0.9.25** — `js.stripe.com/dahlia/stripe.js`
  ładuje się **bez wersji** (wymóg Stripe, `sklep-agentow.php:177`), więc stanu SDK sprzed miesiąca
  nie odtworzyłem. ⭐ To osobne ryzyko warte własnego pytania: **niepinowana biblioteka zewnętrzna
  w ścieżce pieniędzy, bez żadnej kontroli, która zauważy zmianę jej API.**

### `F5` — regulamin
- **Pozostałe punkty `art. 12 ust. 1`** (3a, 5a, 6, 9, 10, 11, 15-18) — sprawdziłem 13 z 21.
- **Strona produktu Segregatora** tą samą siatką par cytatów — mierzyłem ChatBota.
- **Wersja HTML maila** — czytałem `mail_tresc` (tekstową, z bazy); graficznej nie oglądałem
  w łapaczu (`:8025`).
- **Czy strony motywu gospodarza** (`/polityka-prywatnosci`, id 11) **pokrywają przetwarzanie
  danych przez sklep** — to teren `F4`/RODO, nie ruszałem.
- **Kwalifikacja prawna sformułowań** — mierzyłem obecność treści, nie jej wystarczalność.
  To zadanie dla prawnika, nie dla audytora; zapisz je jako takie, nie jako „sprawdzone".

### `F4` — retencja
- **Retencja w tabelach botów** (`chatl_`, `segm_`) — w cronie widziałem
  `chatl_czyszczenie_historii` i `segm_czyszczenie_probki` (raz na dobę). **Czy chodzą — nie wiem.**
  Sonda, która to rozstrzyga bez zapisu: policz wiersze przeterminowane tak, jak zrobiłem
  z `rate_limit` (`F4-RAPORT.md` M4).
- **Retencja po stronie Stripe** — kasujemy u siebie, u nich zostaje komplet. Polityka wymienia
  Stripe jako podmiot przetwarzający i na tym kończy.
- **Logi serwera i pliki produktów** — pytałem o bazę; adresy IP kupujących w logach Apache/nginx
  to osobna warstwa, której nikt nie objął.
- **Kopie zapasowe** — dane usunięte z bazy mogą żyć w kopiach hostingu.
- **Zmiany w przepisach po datach tekstów jednolitych** (VAT: Dz.U. 2024 poz. 361; Ordynacja:
  Dz.U. 2025 poz. 111) — ISAP nie dał się odczytać maszynowo, metoda obejścia opisana
  w `F4-podstawa-prawna-retencji.md` §0. **Do sprawdzenia przeglądarką przed cytowaniem klientowi.**
- **Ustawa o rachunkowości (art. 74)** — czy właściciel jej podlega, nie pytałem.
- **Ścieżka korekty faktury** — korekta też wymaga danych nabywcy; nie badałem.

### `A6` — schemat
- **Zachowanie przy NIEUDANYM `dbDelta`** — to jedyne znalezisko `A6` (`A6-F1`) i jest
  `przypuszczalne`, nie zaobserwowane. Strażnik: odbierz prawo `ALTER`, zawołaj migrację,
  asertuj, że `sklepag_wersja_schematu` **NIE** urosło. Dziś padnie.
- **Faktyczna deinstalacja, obie ścieżki, na żywo** — `uninstall.php` wykonuje się tylko przy
  usuwaniu wtyczki; na wspólnym stosie tego nie robiłem.
- **Kolumny USUNIĘTE w przeszłych wersjach** — `dbDelta` nie kasuje kolumn, więc zostałaby sierota.
  Nie przeszedłem historii `aktywacja.php` wersja po wersji.
- **Typy i długości kolumn** — porównałem **nazwy** kolumn i indeksów, nie `Type`.

### `C12` / `E5` / `C13`
- **Czy `CODEOWNERS` w ogóle działa na prywatnym repo darmowego konta** (samo auto-przypisanie,
  bez wymuszania) — dokumentacja o tym milczy, `gh api user` zwraca `plan: null`.
  Dla werdyktu bez znaczenia, ale niezmierzone. Tak samo **rulesets** jako druga droga.
- **Kto faktycznie scala** — reguła „nikt nie scala własnego PR-a" jest, ale nikt nie zmierzył,
  czy jest dotrzymywana: `gh pr list --state merged --json author,mergedBy`.
- **Rozdzielenie kolejki od wykonania dla workflow `Recenzent`** — zrobiłem to tylko dla `Quality`.
  **To jest liczba, która rozstrzyga wagę `C13-F1`** (dziś drobna, bo nie wiem, ile z tych
  5-25 minut to czekanie).
- **Czas poszczególnych bramek wewnątrz kroku „Bramki jakości"** — krok trwa 33 s w całości.
- **Czy `cancel-in-progress` naprawdę anuluje** — odczytałem konfigurację, nie zmierzyłem
  na żywym przebiegu.

---

## ⛔ JEDNA RZECZ OTWARTA, KTÓRA NIE JEST ZADANIEM AUDYTOWYM

`narzedzia-qa/bramki.sh --tryb pelny` daje dziś **9 z 10 zielonych, `poswiadczenia` EXIT=1**.
Drzewo robocze jest **czyste (0 trafień)** — wartości zamaskowane commitem `1ec1e36`.
Czerwień bierze się **wyłącznie z HISTORII**: 32 trafienia w moich trzech plikach dowodowych
(`audyt/plamy/F5-teksty/*.html`), w commitach od `abeb60f` w górę, **zero poza moim katalogiem**.
Bramka sama podaje lekarstwo: *„gałąź przepisać albo zastąpić czystą od main, sesje unieważnić,
sekret obrócić"*. **Historii nie przepisywałem i nie wymuszałem pusha** — gałąź jest już na origin,
a to ruch nieodwracalny i na zewnątrz. **Czeka na decyzję koordynatorki**, nie na audytora.

## ⭐ Czego NIE było na liście żadnej z siedmiu plam

Do rozważenia jako **nowe cele mapy**, nie jako moje niedokończone zadania:
1. **Wyjątki JS jako sygnał** — żadna bramka ani żaden test e2e nie pada od `pageerror`.
   Cichy wyjątek na froncie jest u nas **niewidzialny z definicji**, a tak właśnie objawiał się
   najcięższy błąd tej nocy.
2. **Stany, nie strony** — sprawdzamy adresy; nie sprawdzamy stanów (panel otwarty, przycisk
   w trakcie, formularz schowany). Trzy z czterech znalezisk `A4b` siedzą w stanie.
3. **Zachowanie po BŁĘDZIE, nie po sukcesie** — rubryka opisuje udany zakup; nikt nie zapisał,
   co kupujący ma zobaczyć, gdy płatność się nie uda. Dlatego pusty, schowany komunikat
   nie naruszał żadnego zapisanego wymagania.
4. **Kolejka CI jako źródło fałszywych werdyktów** — przebieg czekający 45 minut opisuje stan
   sprzed 45 minut. To nasza klasa 10 („opis PR o rundę do tyłu"), tylko od strony CI.
5. **Panel wp-admin na drugiej szerokości** — właściciel też bywa na telefonie.
