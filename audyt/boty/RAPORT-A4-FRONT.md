# RAPORT AUDYTU A4 — FRONT I UŻYWALNOŚĆ (to, co widzi GOŚĆ i WŁAŚCICIEL)

**Pytanie soczewki:** czy da się tego użyć bez frustracji i co widzi człowiek, gdy coś pójdzie nie tak.
**commit_head:** `3fc884d9d7c3d9b47b8229b4c3be5ddcd1eea5e4` (`git rev-parse --short HEAD` → `3fc884d`)
**Gałąź:** `audyt/a4-front`, odgałęziona z `origin/main`.
**Stack zmierzony:** WordPress 7.0.4 / PHP 8.4.21, `http://localhost:8890`,
wtyczki `chatbot-live 1.5.8`, `segregator-maila 1.3.1` (`wp plugin list`).
Przeglądarka: Chromium headless (Playwright 1.62, `/usr/lib/node_modules/playwright`).
⛔ **Lighthouse NIE był uruchamiany** — zgodnie z ostrzeżeniem o RAM; i tak mierzy wydajność,
a nie tę soczewkę. Pomiary robione pojedynczą przeglądarką bezgłową, `free -m` sprawdzane
przed każdym uruchomieniem (najniżej: 872 MB dostępnych).

---

## CO TEN PRODUKT MA ROBIĆ — moimi słowami, z materiału

Dwie wtyczki WordPressa sprzedawane jako gotowe produkty małym firmom. **ChatBot LIVE** siedzi
bąbelkiem w rogu strony firmowej i odpowiada 24/7 na powtarzalne pytania z bazy pytań-odpowiedzi,
którą właściciel uzupełnia w panelu; kiedy nie zna odpowiedzi, **uczciwie się do tego przyznaje
i zbiera kontakt** — mail plus treść — żeby właściciel odpisał osobiście, i przez cały czas trzyma
widoczny przycisk „zostaw wiadomość", bo droga do człowieka ma być dostępna od pierwszej chwili,
a nie dopiero po porażce bota. **Segregator maila** rozdziela pocztę do kategorii według reguł
„pierwsza pasująca wygrywa" i pokazuje raport, ile roboty odwalił. Oba mają ten sam układ hybrydowy:
silnik regułowy działa zawsze i za darmo, a po wpisaniu własnego klucza API właściciela do pracy
wchodzi model językowy — z dziennym stop-lossem i cichym zjazdem do reguł, gdy dostawca odmówi.
Wszystko po polsku, bez CDN-ów i zewnętrznych zależności, dane we własnych tabelach, z jawną
deklaracją prywatności rozdzielającą tryb regułowy (treść zostaje na serwerze klienta) od trybu AI
(treść idzie do dostawcy modelu). ⚠️ Materiał nie ma dziury — **umiałem to napisać po jednym
czytaniu**, więc znaleziska z tego tytułu nie zgłaszam.

---

## WYNIK — sześć celów

| cel | co | wynik |
|---|---|---|
| 1 | Każdą ścieżkę da się dokończyć i przerwać | 🐞 (część okna czatu) + ✅ (panel) |
| 2 | Co widzi gość, gdy sieć padnie w połowie | ✅ |
| 3 | Podwójne kliknięcie, dwie karty, wstecz | ✅ |
| 4 | Konsola przeglądarki czysta | 🐞 **dwa błędy JavaScript przy normalnym użyciu** |
| 5 | Dostępność (etykiety, Tab, kontrast, „bot pisze") | 🐞 (cztery pola bez nazwy + przełącznik bez stanu), kontrast ✅ |
| 6 | Formularz zgody z wyłączonym JavaScriptem | ✅ **nie da się obejść** |

---

## 🐞 F1 — `odpytaj is not defined`: gość NIE DOWIADUJE SIĘ, że dołączył konsultant

**Waga: poważna.** To jest jedyna funkcja, przez którą „LIVE" w nazwie produktu ma dziś pokrycie,
i na froncie ona nie działa dla gościa, który po prostu czeka.

**Plik i linia:** `wtyczki/chatbot-live/assets/widget.js:527` — `dajSesje().then(odpytaj);`
wywołane w zasięgu `start()`, podczas gdy `function odpytaj()` jest zadeklarowana **wewnątrz**
`zbudujOkno()` (`:400`). Każde otwarcie czatu rzuca `ReferenceError` i **przerywa uruchomienie
odpytywania** — komentarz nad tą linią mówi wprost, po co ona jest: *„Pierwsze pytanie OD RAZU
po otwarciu: operator mogl przejac rozmowe, gdy okno bylo zamkniete i nikt nie pytal."*

**Odtworzenie (Playwright, headless):** gość otwiera czat, zadaje pytanie, operator przejmuje
rozmowę w panelu i pisze do niego, gość **siedzi z otwartym oknem i czeka 20 sekund**:

```
  sesja goscia: OTusw8Tlxc1E8mBVBSkrTD5SxBeoW8pn ; odpytan REST do tej pory: 0
=== OPERATOR PRZEJMUJE ROZMOWE (panel wlasciciela, po stronie serwera) ===
  bool(true) | stan=czlowiek
=== GOSC SIEDZI Z OTWARTYM OKNEM I CZEKA 20 s (nic nie pisze) ===
  odpytan REST przez te 20 s: 0
  czy gosc zobaczyl "Do rozmowy dolaczyl konsultant"? ⛔ NIE
  czy gosc zobaczyl wiadomosc operatora ("Tu Anna")? ⛔ NIE
=== KONTROLA: gosc pisze DRUGIE pytanie — dopiero to uruchamia odpytywanie ===
  odpytan REST po drugim pytaniu: 2   <- >0 = licznik DZIALA (proba kontrolna)
  teraz widzi konsultanta? TAK
  teraz widzi wiadomosc operatora? TAK
  bledy JS: ["odpytaj is not defined"]
```

⭐ **Próba kontrolna jest w tym samym przebiegu i to ona czyni wynik dowodem:** licznik odpytań
pokazał `2`, gdy odpytywanie ruszyło — więc wcześniejsze `0` znaczy „nic nie poleciało",
a nie „mój licznik nie liczy" (G6). Sprawdziłem też, że mój filtr adresu jest poprawny:
`grep -o '"restUrl":"[^"]*"' → "restUrl":"http://localhost:8890/wp-json/chatl/v1/rozmowa"`.

**Co widzi klient:** operator klika „Przejmij rozmowę", pisze do gościa i widzi w panelu, że
wiadomość poszła. Gość patrzy w otwarte okno czatu i **nie widzi nic** — ani „Do rozmowy dołączył
konsultant", ani treści. Zobaczy dopiero, jeśli sam napisze jeszcze raz. Przy odrobinie pecha
napisze „halo, jest tam kto?" i wyjdzie.

**Zasięg klasy — sprawdzone wyszukiwaniem, nie z pamięci:** `grep -n "odpytaj\|zatrzymajOdpytywanie"`
pokazuje **dwa** wywołania z zewnętrznego zasięgu: `:527` (`odpytaj`) i `:529`
(`zatrzymajOdpytywanie`, patrz F2). Pozostałe cztery (`:432`, `:445`, `:465`, wewnątrz `odpytaj`)
są w tym samym zasięgu i działają. Autor **przewidział** ten problem dla bliźniaczej funkcji:
`:53` `var stopOdpytywania = function () {};`, `:439` `stopOdpytywania = zatrzymajOdpytywanie;`,
`:327` `stopOdpytywania();` — most przez zasięg istnieje, ale w `start()` wołane są nazwy wewnętrzne.

## 🐞 F2 — `zatrzymajOdpytywanie is not defined` przy zamykaniu bąbelkiem (bliźniak F1)

**Waga: drobna** — i piszę to po pomiarze, nie z góry.

**Plik i linia:** `wtyczki/chatbot-live/assets/widget.js:529`. Ten sam błąd zasięgu, druga gałąź
tego samego `addEventListener`. Zmierzone:

```
=== GOSC ZAMYKA OKNO BABELKIEM (nie klawiszem Escape) ===
  okno zamkniete? true
  blad JS przy zamykaniu: ["zatrzymajOdpytywanie is not defined"]
  ⛔ odpytan REST w 30 s PO ZAMKNIECIU: 0   (oczekiwane 0)
```

⚠️ **Moja hipoteza była błędna i pomiar mnie poprawił.** Spodziewałem się wycieku — odpytywania
chodzącego w nieskończoność po zamknięciu okna. **Nie ma go**, bo `zaplanujOdpytanie()`
(`:428-433`) ma własną straż: `if (!okno.classList.contains('chatl-otwarty')) { return; }`,
więc następny cykl i tak się nie zaplanuje. Zostaje **błąd w konsoli przy każdym zamknięciu**
i kruchość: jedyne, co dziś ratuje tę ścieżkę, to straż w innej funkcji.
Ścieżka zamknięcia klawiszem Escape idzie przez `zamknijOkno()` (`:324-328`) i **jest poprawna** —
używa mostu `stopOdpytywania()`.

## 🐞 F3 — cztery pola panelu bez żadnej nazwy dostępnej (WCAG 1.3.1 / 4.1.2, poziom A)

Panel wyrenderowany po stronie serwera prawdziwymi funkcjami widoków (`wp eval`, `wp_set_current_user(1)`),
61 pól na dziesięciu podstronach. Kryterium: pole ma `<label for>`, albo jest w `<label>`, albo ma
`aria-label`/`aria-labelledby`/`title`. Cztery nie mają nic:

| plik:linia | pole | co to jest |
|---|---|---|
| `wtyczki/chatbot-live/includes/import.php:739` | `<input type="file" name="plik">` | **wybór pliku CSV do importu bazy Q&A** — bez `id`, bez etykiety |
| `wtyczki/segregator-maila/includes/admin-skrzynka.php:141` | `<select name="szyfrowanie">` | `<label for="segm-port">` obok nazywa **tylko** pole portu, choć jego tekst brzmi „Port i szyfrowanie" |
| `wtyczki/chatbot-live/includes/admin.php:790` | `<input type="color" id="chatl-kolor-tekstu" name="kolor_tekstu">` | kolor tekstu widgetu |
| `wtyczki/chatbot-live/includes/admin.php:847` | `<input type="text" id="chatl-ai-model-wlasny" name="ai_model_wlasny">` | „własny model" AI |

```
chatl-ustawienia   bajtow=14916  pol=20  bez_etykiety=2 -> kolor_tekstu(color), ai_model_wlasny(text)
chatl-qa           bajtow=14030  pol=6   bez_etykiety=1 -> plik(file)
segm-skrzynka      bajtow=31673  pol=16  bez_etykiety=1 -> szyfrowanie(select)
(pozostalych szesc podstron: bez_etykiety=0)
```

**Próba kontrolna do G5** („zero z grepa to nie wynik"): dla pola, które etykietę **ma**,
`grep -c 'for="chatl-ai-limit"'` → `1`; dla dwóch spornych → `0` i `0`. Metoda łapie etykiety,
gdy istnieją.
**Którą wytyczną łamie:** WCAG 2.2 **4.1.2 Name, Role, Value (A)** — element formularza bez nazwy
dostępnej; oraz **1.3.1 Info and Relationships (A)** — związek etykiety z polem nie jest
programowo określony. Najdotkliwsze jest pole pliku: czytnik ekranu ogłasza sam przycisk wyboru
pliku bez informacji, czego dotyczy.

## 🐞 F4 — przełącznik czatu nie mówi, w jakim jest stanie (WCAG 4.1.2, poziom A)

`wtyczki/chatbot-live/assets/widget.js:518-520` — bąbelek dostaje na stałe
`aria-label = 'Otwórz czat'` i **nigdy nie dostaje `aria-expanded`**. Sprawdzone wyszukiwaniem
w całym froncie i panelu obu wtyczek: `grep -n "aria-expanded\|role=\"dialog\"\|aria-modal"` →
**zero trafień**. Zmierzona kolejność Tab po otwarciu czatu:

```
  fokus po otwarciu: chatl-pole-pytania / Twoje pytanie
  kolejnosc TAB (8 skokow): BUTTON.chatl-wyslij-pytanie -> BUTTON.chatl-babelek -> BODY.home -> A.skip-link -> ...
```

Czyli osoba korzystająca z klawiatury po dwóch skokach trafia na przycisk, który **przedstawia się
jako „Otwórz czat", choć czat jest już otwarty** — a jego naciśnięcie go zamknie. Stan
rozwinięcia nie jest programowo określony.
⛔ **Nie zgłaszam** braku pułapki na fokus: okno czatu nie jest oknem modalnym (strona pozostaje
używalna), więc wyjście Tab-em do treści strony jest zgodne z 2.4.3, nie przeciw niej.

## ⚠️ Anomalia — bez JavaScriptu nie ma ŻADNEJ ścieżki kontaktu ani śladu, że czat istnieje

Zmierzone na wysyłanym HTML (`curl` bez przeglądarki):

```
HTTP 200  bajtow=117498
-- trafienia 'chatl' w HTML:  chatl-widget-js-extra, chatl-widget-js, chatl-widget-css, chatlDane
-- noscript: 0
```

Całe okno czatu i formularz kontaktu budowane są w JavaScripcie (`el('input')`, `el('label')`…),
a `<noscript>` nie ma ani jednego. Gość z zablokowanym skryptem — albo taki, u którego skrypt nie
doszedł — **nie widzi nawet bąbelka** i nie dostaje żadnej alternatywnej drogi kontaktu.
Oznaczam jako **⚠️ anomalię, nie znalezisko**: takie jest zachowanie większości widgetów czatu
na rynku, a materiał nie stawia wymagania działania bez JS. Zgłaszam, bo produkt jest sprzedawany
zdaniem „zbiera kontakty", a to jest ścieżka, którą kontakt cicho znika.

---

## CO SPRAWDZIŁEM I WYSZŁO CZYSTO

### ✅ Cel 6 — zgody NIE DA SIĘ obejść wyłączonym JavaScriptem
Żądanie wysłane `curl`-em wprost do `admin-ajax.php`, czyli dokładnie tak, jak zrobiłaby to
przeglądarka bez JS albo ktoś z DevTools — z pominięciem walidacji `required` w formularzu:

```
=== 3) ZGLOSZENIE BEZ ZGODY ===
HTTP 400
{"success":false,"data":{"komunikat":"Zaznacz proszę zgodę na przetwarzanie danych — bez niej nie możemy zapisać Twojej wiadomości."}}
=== 4) KONTROLA: to samo Z ZGODA ===
HTTP 200
{"success":true,"data":{"komunikat":"Dziękujemy! Mamy Twoją wiadomość — odezwiemy się na podany adres."}}
=== 5) CO JEST W BAZIE ===
audyt-a4-zezgoda@test.local | status=nowe | podejrzane=0
razem: 1
```

**W bazie jest dokładnie jeden wiersz — ten ze zgodą.** Walidacja siedzi w
`wtyczki/chatbot-live/includes/front.php:544` i wypada **przed** `INSERT`-em.

### ✅ Cel 2 — sieć pada w połowie: gość dostaje prawdę, nie kręcące się kółko
Żądanie do bota odcięte na poziomie przeglądarki (`route.abort('connectionfailed')`):

```
  co widzi gosc: Coś poszło nie tak. Spróbuj ponownie.
  wskaznik „bot pisze" nadal widoczny? nie ma go w DOM
  pole pytania odblokowane? tak
```

Trzy rzeczy naraz: komunikat, zdjęty wskaźnik pisania, odblokowane pole. To jest dokładnie to,
o co pytał cel.

### ✅ Cel 3 — podwójne kliknięcie, dwie karty, przycisk wstecz
```
  zadan chatl_pytanie po TRZECH kliknieciach: 1
  sesja karta A: 9CK9eg2aTtCyLTt1vHndCuqBasyt8r5j
  sesja karta B: XiGuTk4zuMmSqdy2XKYeh4DVsmgzPf8P
  RÓŻNE sesje? TAK
  czy karta B widzi pytanie karty A? nie
  po WSTECZ: babelek jest? true ; okno otwarte? false
```
Trzy szybkie kliknięcia „Wyślij" dają **jedno** żądanie (straż `wyslijBtn.disabled`,
`widget.js:454-458`). Dwie karty to dwie osobne sesje i żadnego przecieku treści.
Po powrocie przyciskiem wstecz widget odbudowuje się poprawnie; historia rozmowy nie wraca —
to jest zgodne z granicą Z3 z materiału („bez pamięci rozmów między wizytami"), więc **nie
zgłaszam** tego jako braku.

### ✅ Cel 5 — kontrast: zmierzony w przeglądarce, wszystko powyżej progu AA
Wartości policzone z `getComputedStyle` na żywym widgecie, wzorem WCAG na kontrast:
```
  {"poleTekst":"rgb(245,245,245)","placeholder":"rgb(143,146,156)","oknoTlo":"rgb(16,17,22)", ...}
  KONTRAST placeholder vs tlo pola:            6.07 :1
  KONTRAST tekst wpisywany vs tlo pola:       17.29 :1
  KONTRAST chatl-info-prywatnosc vs tlo:       7.17 :1
```
Próg **1.4.3 Contrast (Minimum), AA** to 4.5:1 — wszystkie trzy przechodzą, łącznie z tekstem
podpowiedzi i notą o prywatności, czyli tam, gdzie najczęściej się to sypie.

### ✅ Cel 5 — sygnał „bot pisze" ma etykietę dla czytnika ekranu
`wtyczki/chatbot-live/assets/widget.js:132-135`: `aria-live="polite"` + `aria-label="Bot pisze
odpowiedź"`. Pasek rozmówcy dostaje `role="status"` (`:345`, `:397`), pułapka na boty jest
schowana przed czytnikiem (`tabIndex = -1`, `aria-hidden="true"`, `:241-243`), a pola czatu mają
`aria-label` (`:251`, `:259`, `:370`, `:374`). Po otwarciu okna fokus ląduje na polu pytania.

### ✅ Cel 1 — panel: co się otworzyło, da się zamknąć
Formularze edycji wyrenderowane w trybie edycji prawdziwymi funkcjami widoków:
```
chatl-qa     edycja id=1  | h1="Baza pytań i odpowiedzi" | wyjsc: 1 -> >Anuluj edycję<
segm-reguly  edycja id=20 | wyjsc: 1 -> >Anuluj edycję<
segm-kategorie edycja     | wyjsc: 1 -> >Anuluj<
```
### ✅ Cel 1 — stały przycisk „zostaw wiadomość" jest tam, gdzie każe zamówienie
Zamówienie (`material/01-zamowienie.md`) wymaga: *„STAŁY przycisk «zostaw wiadomość» widoczny od
początku rozmowy (wzorzec Crisp: ścieżka do człowieka zawsze, nie tylko po porażce bota)"*.
Jest: `widget.js:351-356`, budowany razem z oknem, nie po nieudanym dopasowaniu.
Zamknięcie formularza czyści adres, treść i odznacza zgodę (`:293-300`), a Escape wychodzi
najpierw z formularza, a dopiero drugim naciśnięciem z całego czatu (`:536-545`).

---

## CZEGO NIE ZDĄŻYŁEM / NIE DA SIĘ ORZEC

- ⚠️ **Konsola panelu (cel 4 po stronie właściciela) — NIE ZMIERZONA W PRZEGLĄDARCE.**
  `siteurl` i `home` wskazują tunel `https://linda-refrigerator-potato-objective.trycloudflare.com`,
  który z tej maszyny jest **nieosiągalny** (wyjście do sieci zablokowane), a logowanie przez
  `localhost` kończy się przekierowaniem na tunel: ciasteczko sesji jest wystawiane na inną domenę.
  Trzy podejścia (formularz logowania, ciastko z `wp_generate_auth_cookie`, to samo z realnym
  tokenem sesji z `WP_Session_Tokens`) dały ten sam wynik: `zalogowany? NIE`. **Podstrony panelu
  badałem więc renderem po stronie serwera**, co daje HTML i etykiety, ale **nie daje błędów
  JavaScriptu**. Cel 4 jest zamknięty tylko dla frontu.
  ⚠️ Uwaga do materiału: `02-fakty-srodowiska.md` ostrzega, że pomiary po `localhost` „nie są
  miarodajne". Dla **frontu** okazało się odwrotnie — strona pobrana z `localhost:8890` niesie
  `"ajaxUrl":"http://localhost:8890/wp-admin/admin-ajax.php"` i `restUrl` na tym samym hoście,
  więc mierzyłem realny ruch tej samej instalacji. Ostrzeżenie **jest** natomiast prawdziwe
  dla panelu, i to ono zablokowało cel 4 po stronie właściciela.
- ⬜ **Segregator na froncie** — nie ma frontu (pracuje wyłącznie w panelu), więc cele 2 i 3
  dotyczą go tylko przez panel; **nie klikałem** przebiegu próbki ani kreatora reguł w przeglądarce.
- ⬜ **Czytnik ekranu na żywo** — sprawdziłem atrybuty i nazwy dostępne, ale nie odsłuchałem
  ani jednej ścieżki prawdziwym czytnikiem. To co innego niż audyt atrybutów.
- ⬜ **Klawiatura w panelu** — kolejność Tab zmierzyłem tylko na widgecie.
- ⬜ **Widok mobilny** — nie tknąłem; ani jednego pomiaru przy wąskim oknie.
- ⚠️ **Dwie próby kontrolne, które sam źle postawiłem** (zapisuję, bo to lekcja o przyrządzie,
  nie o produkcie): (1) render panelu wywalił się na `Call to undefined function
  segm_odbierz_liczenie()` — to był **mój** brakujący `require`, nie błąd produktu;
  (2) kontrolę „zamknięcie Escape" zbudowałem tak, że pierwszy Escape zamknął formularz
  kontaktu, a nie okno — wynik `okno zamkniete Escape? false` jest artefaktem mojego scenariusza.

---

## CZEGO NIE BYŁO NA MOJEJ LIŚCIE

1. **Nikt nie pyta, co widzi WŁAŚCICIEL, gdy funkcja frontu jest zepsuta.** F1 jest błędem
   JavaScriptu u gościa — i **nie zostawia w produkcie żadnego śladu**: panel operatora pokazuje
   wysłaną wiadomość, baza ma wiersz, wszystkie bramki są zielone. Cała nasza obsługa błędów
   (`*_log_bledu_bazy`, znaczniki awarii) siedzi w PHP; **awaria po stronie przeglądarki jest
   dla tego produktu całkowicie niewidzialna**. To jest kategoria, nie pojedyncza wada.
2. **Żadna z sześciu bramek nie uruchamia przeglądarki.** F1 to jedna linia, którą złapałby
   dowolny liniowy analizator JS albo jedno otwarcie czatu z otwartą konsolą. Skoro
   `narzedzia-qa/bramki.sh` tego nie robi, ta klasa może wracać po każdej zmianie w `widget.js`.
3. **Zgodność „co obiecuje strona produktu" z tym, co gość faktycznie zobaczy.** Sprawdziłem
   zgodność z zamówieniem (stały przycisk kontaktu — jest). Nie sprawdziłem strony sprzedażowej
   w sklepie: to tam pada obietnica zakupu, a F1 uderza dokładnie w człon „LIVE".
4. **Koszt odpytywania dla hostingu współdzielonego.** Zmierzyłem interwał przy okazji
   (~5 s przy otwartym oknie z przejętą rozmową), ale moje pytanie nie pytało, ile żądań na
   godzinę generuje jedna otwarta karta i czy to się skaluje. `MATERIAL-AUDYTU-CHATBOT.md`
   nazywa to dziurą D2 i nadal nie ma właściciela.
5. **Zachowanie widgetu, gdy strona jest w cache (nonce wygasł).** Jest osobny endpoint
   wydający świeży nonce i osobna gałąź `kod: 'nonce'` — czyli ktoś o tym myślał, a ja tej
   ścieżki **nie przeszedłem ani razu**. To krawędź na styku frontu i cache'u strony.
6. **Widoczność dla wyszukiwarek i wpływ widgetu na motyw.** Zamówienie mówi „nie rusza motywu";
   nie sprawdziłem, czy CSS widgetu nie przecieka na style strony ani czy węzeł widgetu nie ląduje
   w treści indeksowanej.
7. **Dostępność panelu jako całości.** Zbadałem etykiety pól. Nie zbadałem nagłówków, tabel
   (`<th scope>`), komunikatów o zmianie stanu po zapisie ani widoczności fokusu — a panel jest
   miejscem, gdzie właściciel spędza cały czas.
