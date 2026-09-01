# A4b — Front: WYGLĄD i DZIAŁANIE w przeglądarce

**Audytor:** sesja audytu białych plam, 2026-09-01 · **Gałąź:** `audyt/biale-plamy-01-09`
**`commit_head` audytowanego drzewa:** `18c9cd070d9202e98dccbcca43fda6fe0f89651f`
**Wersja produktu na stanowisku:** `sklep-agentow` 0.9.28 (`SKLEPAG_WERSJA`, `sklep-agentow.php:19`)
**Stanowisko:** `strona_wp_wordpress` → `http://localhost:8890`, klucz publikowalny Stripe
USTAWIONY (`sklepag_stripe_klucz_publikowalny`, `pk_…`, 107 znaków — wartości nie wypisuję)

---

## Wobec czego oceniam (kryteria + skąd)

| # | Kryterium | Skąd |
|---|---|---|
| K1 | „Zakup kartą testową: webhook → zamówienie → mail ≤ 60 s” | `rubryka-GOTOWE.md` #2 (= `PLAN-PROJEKTU.md` §12) |
| K2 | „WCAG-lite + RWD (zakup **telefonem**) + Chrome/Edge/Firefox; baner »TRYB TESTOWY«” | `rubryka-GOTOWE.md` #11 |
| K3 | Sklep **ma normalnie sprzedawać** — produkt jest sklepem, nie makietą | `ZROZUMIENIE.md`, surowe zdania właściciela („ma normalnie sprzedawać”, „sprzedaż cyfrowa”) |
| K4 | **Obietnica producenta wobec klienta:** „Gdy w Ustawieniach jest klucz publikowalny — [karta] na stronie produktu… **Obie drogi kończą się tą samą stroną »Dziękujemy« i tym samym mailem**” | `wtyczki/sklep-agentow/INSTRUKCJA.md` §9 „Najczęstsze pytania” |
| K5 | Zakres samej plamy: „sprawdzono wyłącznie, JAK zasoby są ładowane, nie CO robią. Zero zrzutów, zero przejścia ścieżki zakupu okiem” | `audyt/MAPA-TERENU-INFRA.md`, wiersz `A4b` (gałąź `origin/fix/bezp-przypiecia-i-skan-archiwow`) |
| K6 | Klasa 60 ściągi: „Stripe broni się przed robotem (hCaptcha przy `confirm()`); »Przetwarzam…« bez końca **to nie wada sklepu**” — **teza do sprawdzenia, nie do przyjęcia** | `audyt/SCIAGA-EGZAMINATORA.md` klasa 60 |
| K7 | Klasy 34/68: kontrola dobrana tak, że **z założenia nie widzi** tej klasy błędu; bramka meldująca pominiętą kontrolę tak samo jak realną wadę | `audyt/SCIAGA-EGZAMINATORA.md` |
| K8 | Zasada briefingu: ocena wyglądu **oknem po wolnym przewinięciu**, nie `fullPage`; pomiar na DWÓCH szerokościach | `BRIEFING-AUDYTOR-01-09.md` §CZYM SPRAWDZASZ |

⛔ Czego **nie** przyjmuję za kryterium: własnego gustu w sprawie układu, kolorów i typografii.
Zgłaszam wyłącznie to, co ma skutek dla kupującego.

---

## Co i czym zmierzyłem

Wszystkie przebiegi pod `flock /root/praca/zlecenia/wymiana/stos.lock` (stos wspólny z builderem).
Przeglądarka: `playwright-core` + chromium `/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`
(`node testy/lib/chromium.js`, EXIT=0).

| # | Przyrząd (w repo, da się uruchomić) | Co mierzy | Wynik / plik |
|---|---|---|---|
| P1 | `audyt/plamy/A4b-przelot-frontu.js` | 4 strony × 2 szerokości, zrzuty **oknem po wolnym przewinięciu** (krok 70 % okna, pauza 700 ms), błędy konsoli, wyjątki, żądania ≥400, przepełnienie w poziomie | EXIT=0 · 97 zrzutów w `audyt/plamy/A4b-zrzuty/` · `audyt/plamy/A4b-pomiar.json` |
| P2 | `audyt/plamy/A4b-sciezka-zakupu.js` | przejście ścieżki: pudełko zakupu → klik BEZ zgody → zgoda → panel płatności → karta → „Zapłać” → „Dziękujemy”; desktop 1440×900 i telefon 390×844 | EXIT=0 · `audyt/plamy/A4b-sciezka.json` · zrzuty `zakup-1…6-*.png` |
| P3 | `audyt/plamy/A4b-sonda-actions.js` | co naprawdę zwraca `checkout.loadActions()` — klucze obiektu, na którym wołamy `confirm()` | `A4b-sonda-actions-chatbot.log`, `A4b-sonda-actions-segregator.log` |
| P4 | `audyt/plamy/A4b-kontrola-dodatnia-confirm.js` | ten sam przelot, ale `confirm()` na obiekcie poziom niżej — **kontrola dodatnia** rozdzielająca „nasz błąd” od „obrona Stripe” | EXIT=0 · `A4b-kontrola-dodatnia.log` · `zakup-A-*.png`, `zakup-B-*.png` |
| P5 | `audyt/plamy/A4b-sonda-slepy-zaulek.js` | czy po nieudanym „Zapłać” istnieje droga powrotu (Wróć → Kup teraz) | EXIT=0 · `A4b-sonda-slepy-zaulek.log` |
| P6 | `audyt/plamy/A4b-sonda-pigulka.js` | czy pigułka „Kup teraz” odsłania się w trakcie płatności i co przykrywa | EXIT=0 · `A4b-sonda-pigulka.log` · `pigulka-w-platnosci-*.png` |
| P7 | `testy/e2e/przelot-osadzony.js` (**cudzy strażnik, uruchomiony bez zmian**) | co melduje istniejąca kontrola tej samej ścieżki | **EXIT=3** · `A4b-przelot-osadzony-jak-jest.log` |

Odczyty stanu (bez zapisu): `wp eval` po `wp_sklepag_zamowienia` (statusy, wiersze), `wp post list`
(strony sklepu), `wp option get` (obecność klucza — bez wartości).

⚠️ **Co ten audyt zostawił na stanowisku:** kontrola dodatnia P4 to PRAWDZIWY zakup w trybie
testowym Stripe. Powstały **dwa zamówienia**: `9901519` i `9901622`
(`audyt-a4b-kd@test.local`, 149,00 PLN, status `mail_wyslany`) plus sesje `open/unpaid` z prób
P2/P5/P7. Nic poza tym nie zapisywałem; żadnej opcji, tabeli ani kontenera nie ruszałem.

---

## Kontrola dodatnia

**1. Czy mój `grep` w ogóle trafia w ten plik.** Wzorzec, który MUSI trafić:
`grep -n "confirm(" wtyczki/sklep-agentow/assets/platnosc.js` → **2 trafienia** (linia 3 komentarza
i linia 150 wywołania). Przyrząd widzi plik.

**2. Kontrola dodatnia do znaleziska głównego — najważniejsza w tym raporcie.**
„Zapłać nie działa” i „Stripe blokuje robota” wyglądają identycznie. Rozdzieliłem je tak:
w JEDNEJ sesji przeglądarki, na TYM SAMYM wypełnionym formularzu, wykonałem dwa przebiegi.

```
PRZEBIEG A — kod z repo (klik w przycisk „Zapłać”):
  napisNaPrzycisku : "Przetwarzam…"      przyciskZablokowany : true
  trescBledu       : ""                  bladSchowany        : true
  url              : /sklep/produkt/chatbot-live/   (bez zmiany)
  WYJĄTEK: TypeError: actions.confirm is not a function
           at HTMLButtonElement.<anonymous> (…/assets/platnosc.js?ver=0.9.28…:150:13)

PRZEBIEG B — ta sama chwila, confirm() wołany na obiekcie POZIOM NIŻEJ:
  CONFIRM NA WŁAŚCIWYM OBIEKCIE: PRZEKIEROWANIE — kontekst zniszczony nawigacją
  WYNIK B: doszłoNaDziękujemy=true  url=http://localhost:8890/dziekujemy-za-zakup/
           ramkaCaptcha=false
  DZIĘKUJEMY: „Gotowe! Mail z potwierdzeniem i linkiem do pobrania został wysłany…”
```
(`audyt/plamy/A4b-kontrola-dodatnia.log`; zamówienie `9901622` w bazie, status `mail_wyslany`.)

⭐ Kontrola **zaświeciła**: droga jest przejezdna, a płatność blokuje wyłącznie kształt obiektu
w naszym kodzie. **`ramkaCaptcha=false` w przebiegu, który przeszedł do końca** — czyli hCaptcha
nie jest tym, co zatrzymuje zakup.

**3. Kontrola nad moim własnym pomiarem układu — i mój błąd, który dzięki niej wyszedł.**
Pierwszy przebieg P1 zameldował na telefonie **87 i 96 elementów wystających poza okno**, przy
`document.scrollWidth == innerWidth` (czyli bez przewijania w poziomie). Sprawdziłem przyrząd,
zanim oskarżyłem produkt: mierzyłem **stan SPRZED animacji wjazdu** — elementy poniżej zgięcia
czekają odsunięte (`translateX`), a ja robiłem pomiar przed przewinięciem. **To był fałszywy
alarm mojego narzędzia, nie wada układu.** Liczby z `A4b-pomiar.json` w tej kolumnie należy
czytać jako pomiar animacji, nie przepełnienia. Przepełnienia w poziomie **nie ma na żadnej
z 8 kombinacji strona × szerokość** (`przewijaSieWPoziomie: false` wszędzie).

**4. Kontrola „czy w ogóle coś jest czyste”.** Podejrzenie: napis `WordPress 6.0+` na stronie
sklepu przy nagłówku wtyczki `Requires at least: 6.7`. Sprawdzone: napis dotyczy **sprzedawanych
botów**, a `chatbot-live.php:6` i `segregator-maila.php:6` mają `Requires at least: 6.0`.
**Zgodne — nie jest to znalezisko.** (Komentarz przy kodzie, `sklep-agentow.php:321`, mówi
dokładnie to samo.)

---

## WYNIK: **ZNALEZIONE** — 4 znaleziska (1 krytyczne, 1 duże, 2 drobne)

---

## Znaleziska

### `A4b-F1` — **KRYT** · Przycisk „Zapłać” w płatności osadzonej NIGDY nie potwierdza płatności; sklep nie sprzedaje

**Objaw (co widzi kupujący):** wypełnia kartę, klika „Zapłać (płatność testowa)”. Przycisk
zmienia napis na **„Przetwarzam…”**, gaśnie — i tak zostaje. **Żaden komunikat się nie pokazuje**,
strona nie przechodzi dalej. Pieniądze nie są pobierane, zamówienie nie powstaje, mail nie idzie.

**Plik i linia:** `wtyczki/sklep-agentow/assets/platnosc.js:110` i `:150`.

```js
// :110  — do `actions` trafia CAŁA odpowiedź, nie jej pole `actions`
return checkout.loadActions().then(function (a) { actions = a; });
// :150  — wołamy metodę, której na tym obiekcie nie ma
actions.confirm().then(function (wynik) {
```

**Dowód wykonania — kształt obiektu zmierzony w przeglądarce** (`A4b-sonda-actions-chatbot.log`,
to samo w `…-segregator.log`):

```
loadActionsIstnieje : "function"
typPola             : "success"                      ← loadActions() oddaje { type, actions }
kluczeActions       : [ …, "actions", …, "type", … ] ← na TYM obiekcie nie ma confirm
confirmNaActions    : "undefined"                    ← stąd TypeError
confirmNaCheckout   : "undefined"
confirmNaAActions   : "function"                     ← confirm jest o poziom niżej: a.actions.confirm
```

**Dowód skutku dla kupującego** (`A4b-sonda-slepy-zaulek.log`, kliknięcia po kolei):

```
2. po kliknięciu ZAPŁAĆ : napisZaplac:"Przetwarzam…"  zaplacZablokowany:true
                          komunikatBledu:"(pusty) [SCHOWANY]"
3. po kliknięciu WRÓĆ   : formularz wraca, „Kup teraz” znów aktywny
4. po ponownym KUP TERAZ: panelSchowany:true, formularzSchowany:false, URL bez zmiany
                          → NIC SIĘ NIE DZIEJE
   WYJĄTKI: ["TypeError: actions.confirm is not a function"]
```

**Mechanizm (łańcuch przyczynowy).** `checkout.loadActions()` z aktualnego
`js.stripe.com/dahlia/stripe.js` zwraca **opakowanie** `{ type: 'success', actions: {…} }`.
Nasz kod przypisuje do `actions` to opakowanie, więc `actions.confirm` jest `undefined`
i `actions.confirm()` rzuca `TypeError` **synchronicznie** — obok obietnicy, czyli
**poza** przygotowanymi gałęziami `.then` i `.catch` (`:150-162`). Skutki idą kaskadą:
1. `pokazBlad(…)` z gałęzi ratunkowej **nigdy się nie wykonuje** → ramka błędu zostaje pusta
   i `hidden` (zmierzone: `komunikatBledu:"(pusty) [SCHOWANY]"`);
2. `odblokuj()` (`:145-149`) nie biegnie → `wToku` zostaje `true`, a przycisk zostaje `disabled`
   z napisem „Przetwarzam…”;
3. „Wróć” przywraca formularz, ale **nie zeruje `wToku`** (`:165-171`), więc kolejne
   „Kup teraz” pada na strażniku `if (wToku) { return; }` (`:116`) **już po**
   `e.preventDefault()` (`:115`) — i formularz nie robi nic ani JS-em, ani zwykłym POST-em.
   **Jedyne wyjście to przeładowanie strony** — i ta sama ściana.

⛔ **To jest gałąź ratunkowa w martwym kodzie — nasza własna klasa 15 ściągi**, tylko po stronie
frontu: obsługa błędu jest napisana, opisana w komentarzu i **nigdy się nie wykonuje**.

**Zasięg — kiedy to bije.** Ścieżka osadzona włącza się, gdy w Ustawieniach stoi klucz
publikowalny (`sklep-agentow.php:176`) — czyli w konfiguracji, **którą instrukcja opisuje jako
lepszą** („kupujący nie opuszcza Twojej strony”, `INSTRUKCJA.md` §9). Wtedy `platnosc.js`
przechwytuje `submit` i ścieżka zapasowa (POST → hostowana strona Stripe) **jest nieosiągalna**
dla kogokolwiek z włączonym JavaScriptem. Bez klucza publikowalnego sklep sprzedaje normalnie —
i to tłumaczy, czemu `testy/e2e/przelot2.js` (droga hostowana) świeci zielono.

**Klasa, nie egzemplarz.** `grep -rn "loadActions\|initCheckout" --include=*.js --include=*.php`
po całym repo (poza `node_modules` i moimi sondami) → **dwa trafienia, oba w `platnosc.js`**
(`:89`, `:110`). Klasa ma jeden egzemplarz w produkcie i domyka się jedną poprawką — ale
występuje na **OBU produktach**, bo oba używają tego samego pliku: zmierzone osobno dla
`/sklep/produkt/chatbot-live/` i `/sklep/produkt/segregator-maila/` (dwa logi sondy).

**Skutek DLA KLIENTA:** właściciel sklepu, który zrobił dokładnie to, co mówi instrukcja
(wpisał klucz publikowalny), **nie sprzeda ani jednej sztuki**. Kupujący dochodzi do końca
formularza, klika „Zapłać” i zostaje z napisem „Przetwarzam…”, bez żadnego komunikatu i bez
możliwości powtórzenia próby. Z punktu widzenia kupującego sklep jest zepsuty w najgorszym
możliwym miejscu — w kroku, w którym chciał zapłacić. **Awaria CICHA:** nic nie ląduje
w logach sklepu, właściciel nie ma jak się dowiedzieć — widzi tylko brak sprzedaży.

**RODZAJ SKUTKU:** `zaobserwowany` (zrzut `zakup-A-przycisk-produktu.png`, log P5, wyjątek ze stosem).

**Waga: KRYTYCZNA** (ścieżka pieniędzy; jedna dokładnie jedna waga).

**Propozycja naprawy (do wykonania przez buildera, nie przeze mnie):**
w `assets/platnosc.js:110` sięgnąć po właściwy poziom i sprawdzić kształt, zamiast go zakładać —
np. `.then(function (a) { actions = ( a && a.actions ) ? a.actions : a; })`, a przy `a.type !== 'success'`
pokazać kupującemu komunikat zamiast zostawiać puste pole. Niezależnie od tego: opakować
`actions.confirm()` w `try/catch` **albo** wywołać przez `Promise.resolve().then(function () { return actions.confirm(); })`,
żeby wyjątek synchroniczny trafiał do istniejącej gałęzi ratunkowej — dziś jest ona nieosiągalna.
`btnWroc` (`:162`) ma zerować `wToku`, inaczej „Kup teraz” pozostaje martwe po każdej porażce.
⭐ **Strażnik, bez którego naprawa nie jest domknięta:** test, który po kliknięciu „Zapłać”
wymaga **przekierowania na »Dziękujemy«** ALBO **niepustego, widocznego komunikatu błędu** —
i **PADA**, gdy przycisk zostaje na „Przetwarzam…”. Kalibracja: mutant przywracający `actions = a`
musi go wywalić.

---

### `A4b-F2` — **DUŻE** · Jedyny strażnik tej ścieżki melduje „nie wada sklepu” i kończy kodem 3 — a wada pojechała do klienta pod tą etykietą

**Objaw:** `testy/e2e/przelot-osadzony.js` uruchomiony bez żadnych zmian, na tym samym stosie,
w tej samej minucie co pomiar `A4b-F1`:

```
POWROT: BRAK (http://localhost:8890/sklep/produkt/chatbot-live/)
BLAD PANELU: -
CAPTCHA STRIPE: tak — automat zatrzymany na hCaptcha (nie wada sklepu); dowód ręczny w przeglądarce
EXIT=3
```
(`audyt/plamy/A4b-przelot-osadzony-jak-jest.log`.)

**Plik i linia:** `testy/e2e/przelot-osadzony.js:72-73` — rozpoznanie „captcha” opiera się na
**samej obecności ramki** `hcaptcha` (`p.frames().some(f => /hcaptcha/i.test(f.url()))`), a nie
na tym, czy zagadka faktycznie zablokowała `confirm()`. Test **nie patrzy** na `pageerror`,
więc `TypeError` z `platnosc.js:150` przelatuje mu przez palce.

**Dowód, że diagnoza jest fałszywa:** mój przebieg B (kontrola dodatnia wyżej) przeszedł
**tą samą, zautomatyzowaną przeglądarką**, do końca, na „Dziękujemy”, przy `ramkaCaptcha=false`.
Ramka hCaptcha bywa ładowana przez Stripe zapobiegawczo — jej obecność nie jest dowodem
zatrzymania. **Zero jako sukces + kontrola dobrana tak, że nie widzi tej klasy** (ściąga: klasy 12, 34, 68).

**Gdzie ta diagnoza żyje dalej** (i dlatego jest to znalezisko o wadze DUŻEJ, nie drobiazg
w pliku testu):
- `audyt/CO-WISI.md:832-838` — „To zabezpieczenie Stripe dla zautomatyzowanej przeglądarki,
  **nie wada sklepu** (kod wyjścia 3)”;
- `audyt/RAPORT-ODDANIA.md:181` — „hCaptcha dla zautomatyzowanej przeglądarki
  (sesja `open/unpaid`; kod 3, **nie wada sklepu**)”;
- `audyt/RAPORT-ODDANIA.md:190,243` i `audyt/K11-RAPORT-0.9.27.md:196-198` — kliknięcie karty
  w osadzonym formularzu przesunięte na „człowieka”, werdykt K11: **NIE SPRAWDZIŁEM**.
- `audyt/SCIAGA-EGZAMINATORA.md` klasa 60 zapisuje tę samą diagnozę jako **klasę do zapamiętania**.

Czyli: informacja o niezdanej ścieżce dotarła **czterema kanałami**, za każdym razem opatrzona
zdaniem, że to nie nasza wina — i wydanie poszło. To jest wprost lekcja 12 z `lekcje.md`
(„informacja dotarła trzy razy i nic nie zatrzymała”), tylko z etykietą, która dodatkowo
**zniechęcała do sprawdzania**.

**Skutek DLA KLIENTA:** produkt został oddany z niedziałającą płatnością, a wszystkie nasze
dokumenty mówiły, że powodem jest zabezpieczenie Stripe. Dopóki ta etykieta stoi, nikt tej
ścieżki nie sprawdzi — bo „wiadomo, że automat tego nie zmierzy”.

**RODZAJ SKUTKU:** `zaobserwowany` (EXIT=3 z uruchomienia + cytaty z czterech plików).

**Waga: DUŻA** (mylące zielone/„nie nasza wina” w ścieżce pieniędzy).

**Propozycja naprawy:** rozpoznanie captchy oprzeć na **zdarzeniu**, nie na obecności ramki
(np. dopiero gdy `confirm()` nie zwrócił wyniku, a jednocześnie doszło żądanie
`api.hcaptcha.com/getcaptcha` PO kliknięciu), a niezależnie od tego: test ma **przechwytywać
`pageerror` i padać kodem ≠ 0 przy każdym wyjątku JS na naszej stronie** — dziś każdy nasz
błąd JS jest dla niego niewidzialny. Po naprawie `A4b-F1` sprawdzić, czy kod 3 w ogóle
jeszcze zachodzi; jeśli nie — usunąć etykietę z `CO-WISI.md` i `RAPORT-ODDANIA.md`,
bo od tej chwili **kłamią o produkcie**.

---

### `A4b-F3` — **MALE** · Strona „Dziękujemy” nie zna dwóch statusów zamówienia i pokazuje wtedy nieprawdę

**Objaw:** kupujący, którego zamówienie ma status `zwrocone` albo `sporne`, otwiera swój link
potwierdzenia i widzi **„Przetwarzam Twoją płatność… (strona odświeża status sama)”** — zdanie
nieprawdziwe — po czym strona odpytuje serwer jeszcze 30 razy.

**Plik i linia:** `wtyczki/sklep-agentow/assets/dziekujemy.js:14-21` (mapa `opisy`) i `:29`
(`pole.textContent = opisy[s] || opisy.oczekuje;`).

```js
var opisy = {
  oczekuje: 'Przetwarzamy Twoją płatność… (strona odświeża status sama)',
  oplacone: …, mail_w_toku: …, mail_wyslany: …, mail_blad: …, brak: …
};                                   // ⟵ brak `zwrocone` i `sporne`
```

**Dowód, że te statusy istnieją i że endpoint je odda bez tłumaczenia:**
`includes/zakup.php:1244-1245` zwraca **surową kolumnę `status`** z bazy; słownik statusów
zapisywanych przez kod (`grep` po `includes/*.php`) zawiera m.in. `'zwrocone'` (7 wystąpień)
i `'sporne'` (10). Kolumna `status` w bazie stanowiska ma dziś cztery wartości
(`mail_wyslany`=32, `mail_blad`=4, `mail_w_toku`=3, `oplacone`=4) — czyli **na tym stanowisku
przypadku nie odtworzyłem**; teza stoi na przeczytanym kodzie obu stron (nadawca statusu
i odbiorca), nie na obserwacji.

**Skutek DLA KLIENTA:** kupujący po zwrocie pieniędzy albo w trakcie sporu dostaje komunikat,
że jego płatność jest przetwarzana. To sprzeczne z tym, co wie od banku, i generuje pytanie
do właściciela sklepu. **Awaria cicha** — nic nigdzie nie mruga.

**RODZAJ SKUTKU:** `wyliczony` (dwa konkretne statusy, konkretna gałąź `||` w kodzie),
**nie** `zaobserwowany`.

**Waga: DROBNA** (nie psuje danych, dotyczy wąskiej grupy).

**Propozycja naprawy:** dopisać `zwrocone` i `sporne` do `opisy` (i **zatrzymać odpytywanie**
na nich, tak jak na `mail_wyslany`), albo — trwalej — nie oddawać z REST surowej nazwy statusu,
tylko zbiór, który front na pewno zna. ⭐ Strażnik: asercja „każda wartość, jaką kod potrafi
zapisać do `zamowienia.status`, ma wpis w `opisy`” — bierze listę z kodu, więc obejmie też
statusy dopisane w przyszłości.

---

### `A4b-F4` — **MALE** · Pigułka „Kup teraz” odsłania się DOKŁADNIE w chwili płatności i przykrywa panel

**Objaw:** kupujący otwiera panel płatności — i w tym samym momencie u dołu ekranu wypływa
przyklejona pigułka **„149 PLN · Kup teraz”**, konkurencyjne wezwanie do akcji dla czynności,
którą właśnie wykonuje. Na telefonie przykrywa pola formularza (zrzut
`A4b-zrzuty/zakup-4-karta-telefon.png`: pigułka leży na polu „IMIĘ I NAZWISKO”).

**Plik i linia:** `assets/front-ruch.js:292-306` — pigułka chowa się, gdy WIDOCZNY jest
`#sklepag-kup-formularz`; `assets/platnosc.js:124` ustawia `form.hidden = true` przy otwarciu
panelu, więc obserwator uznaje formularz za niewidoczny i **odsłania** pigułkę.

**Dowód wykonania** (`A4b-sonda-pigulka.log`, obie szerokości):
```
pigułka przed otwarciem widoczna = false
po otwarciu panelu: widoczna=true, formularzSchowany=true,
                    celKotwicy="#sklepag-zakup", napisKotwicy="Kup teraz", celIstnieje=true
przy zjechaniu na koniec panelu: zaslanianeSztuk=1  → p.sklepag-platnosc-notka
```
Zasłanianie zależy od pozycji przewinięcia: przy dole panelu przykrywa notkę
„Dane karty trafiają bezpośrednio do Stripe…”, w połowie panelu — pola karty (zrzut wyżej).
Kotwica prowadzi do `#sklepag-zakup`, sekcja istnieje, więc klik nie jest martwy — jest mylący.

**Skutek DLA KLIENTA:** w najbardziej newralgicznym kroku ekran proponuje „Kup teraz” komuś,
kto właśnie kupuje, i zjada dolny pas ekranu telefonu, na którym leżą pola formularza.

**RODZAJ SKUTKU:** `zaobserwowany` (pomiar + dwa zrzuty).

**Waga: DROBNA.**

**Propozycja naprawy:** warunek widoczności pigułki oprzeć na **sekcji zakupu**
(`#sklepag-zakup`) albo dodać do niego `panel.hidden === false`, zamiast pytać o sam formularz,
który płatność świadomie chowa.

---

## Obserwacje BEZ wagi (nie zgłaszam ich jako błędów — należą gdzie indziej albo nie mam dowodu)

1. **Domyślny kraj w panelu płatności to „Finlandia”**, a prefiks telefonu fiński — w sklepie
   po polsku, w PLN (`zakup-A-przycisk-produktu.png`, `zakup-4-karta-telefon.png`). W kodzie
   **nie ma** ustawienia domyślnego kraju (`grep -i "country\|defaultValues"` w `platnosc.js`
   i `stripe.php` → 0 trafień), więc wartość zgaduje Stripe — najpewniej z adresu IP, a nasz
   stos siedzi na `localhost`. **Nie orzekam wady**: u klienta z prawdziwym ruchem może wyjść
   „Polska”. Do sprawdzenia na hostingu; jeśli się potwierdzi, to jedna linia w konfiguracji
   Elements.
2. **Regulamin i Polityka prywatności sklepu istnieją jako SZKICE** (`wp post list`: id 120 i 121,
   `post_status=draft`, bez slugów), a strona produktu obiecuje „14 dni gwarancji satysfakcji —
   zwrot bez pytań” (`front-produkt.php:967`) obok zgody na **utratę prawa odstąpienia**
   (art. 38). Zestawienie tych dwóch zdań i brak opublikowanego regulaminu to teren **`F5`** —
   biorę to tam, nie tutaj.
3. **Strona „Dziękujemy” pokazuje wyłącznie jedno zdanie statusu** — bez numeru zamówienia,
   nazwy produktu, kwoty i nagłówka (`zakup-6-dziekujemy-*.png`, `dziekujemy-bez-sesji-*.png`).
   Nie mam kryterium ze źródła, które by tego wymagało (rubryka #2 mówi o mailu, nie o tej
   stronie), więc **milczę** — zapisuję jako pytanie do zamawiającego.
4. **Bez identyfikatora w adresie strona mówi „BRAK IDENTYFIKATORA ZAMÓWIENIA W ADRESIE.”** —
   działa poprawnie, komunikat po polsku, bez wycieku danych. Przypadek **bez wady** sprawdzony
   i czysty.
5. **Zgoda wymagana przed płatnością działa**: klik „Kup teraz” bez zaznaczonej zgody nie otwiera
   panelu, a przeglądarka pokazuje **polski** komunikat „Zaznacz zgodę, żeby przejść do płatności.”
   (`A4b-sciezka.json`, obie szerokości). Przypadek bez wady — czysty.
6. **Zero błędów konsoli, zero wyjątków, zero żądań ≥400 na czterech stronach × dwie szerokości**
   przy zwykłym oglądaniu (`A4b-pomiar.json`). Wyjątek pojawia się **dopiero po kliknięciu
   „Zapłać”** — dlatego przelot „po stronach” go nie widział i dlatego A4 (ładowanie zasobów)
   mógł być czysty przy zepsutym zakupie.
7. **Baner „TRYB TESTOWY” jest na każdej sprawdzonej stronie sklepu** — kryterium K2 w tej
   części spełnione (zrzuty `sklep-*`, `produkt-*`, `dziekujemy-*`).

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego | Co z tym zrobić |
|---|---|---|
| **BLIK** w panelu osadzonym | metoda widoczna jako kafelek, nieklikana; nie mam konta testowego BLIK ani sposobu domknięcia | osobny przelot po naprawie `A4b-F1` |
| **3-D Secure** (karta wymagająca uwierzytelnienia) | testowałem wyłącznie `4242…`; ścieżka z ramką 3DS nietknięta | dopisać kartę `4000 0027 6000 3184` do przelotu |
| **Przeglądarki inne niż chromium** (Firefox, Edge/`msedge`) | mierzyłem tylko chromium; `A4b-F1` to `TypeError` z kształtu obiektu, więc nie powinien zależeć od silnika — ale **tego nie zmierzyłem** | powtórzyć P5 na `msedge` i firefoksie |
| **Strony innych szerokości** (tablet ~768 px) | mierzyłem 1440 i 390 | dołożyć trzecią szerokość |
| **WCAG/axe na stronie „Dziękujemy” i na panelu płatności** | `testy/e2e/axe-3-podstrony.js:31` obejmuje **tylko** `/sklep/`, `/produkt/chatbot-live/`, `/produkt/segregator-maila/` — strona, na którą trafia KAŻDY kupujący, oraz stan „panel płatności otwarty” nie są objęte niczym | rozszerzyć listę w axe o „Dziękujemy” i o stan po kliknięciu „Kup teraz” |
| **Wydajność (Lighthouse)** | nie moja plama i wymaga hostingu (`CO-WISI`, AN-022) | zostaje jak było |
| **Zakup na koncie z prawdziwymi kluczami właściciela** | stanowisko ma klucze testowe warsztatu | dowód końcowy u właściciela |
| **Czy `loadActions()` zwracał `{type,actions}` już w 0.9.25** | `js.stripe.com/dahlia/stripe.js` jest ładowany **bez wersji** (wymóg Stripe, `sklep-agentow.php:177`), więc nie umiem odtworzyć stanu SDK sprzed miesiąca | ⛔ **nie twierdzę, że nie działało nigdy** — twierdzę, że **dziś, na wersji, którą dostaje kupujący, nie działa**. To zresztą osobne ryzyko: niepinowana biblioteka zewnętrzna w ścieżce pieniędzy, bez żadnej kontroli, która zauważy jej zmianę |
| **Panel wp-admin** (widok zamówienia po zakupie) | poza zakresem A4b (to teren `E7`) | — |

---

## ⭐ Czego NIE było na mojej liście (przeciwwaga — wejście do następnego przebiegu)

Moje pytanie brzmiało „jak to wygląda i czy da się przejść ścieżkę zakupu”. **Nie pytało** o:

1. **Zewnętrzne zależności frontu jako klasę ryzyka.** `stripe.js` idzie bez wersji, bo Stripe
   tak każe — ale to znaczy, że **zmiana ich API wchodzi klientowi do sklepu bez naszego udziału**,
   a jedyna kontrola tej ścieżki melduje „nie wada sklepu”. Nikt u nas nie ma pytania „co się
   stanie, gdy zewnętrzna biblioteka zmieni kształt odpowiedzi”. To wygląda na osobną soczewkę.
2. **Wyjątki JS jako sygnał w ogóle.** Żadna nasza bramka ani żaden test e2e nie pada od
   `pageerror`. Cichy wyjątek na froncie jest dziś u nas **niewidzialny z definicji** —
   a właśnie tak objawia się `A4b-F1`.
3. **Stan „w trakcie” jako osobny ekran do audytu.** Sprawdzamy strony; nie sprawdzamy **stanów**
   (panel otwarty, przycisk w trakcie, formularz schowany). Trzy z czterech moich znalezisk
   siedzą w stanie, nie na stronie.
4. **Zachowanie po BŁĘDZIE, nie po sukcesie.** Kryterium #2 rubryki opisuje udany zakup.
   Nikt nie zapisał, co kupujący ma zobaczyć, gdy płatność się nie uda — i dlatego pusty,
   schowany komunikat nie naruszał żadnego zapisanego wymagania.
5. **Druga szerokość dla wp-admin.** Właściciel sklepu też bywa na telefonie; panel mierzony
   wyłącznie na desktopie.

---

## Propozycja wiersza do mapy terenu i do rejestru (gotowe do przeklejenia)

⛔ Sam nie edytuję `audyt/MAPA-TERENU-INFRA.md` ani `audyt/REJESTR-ZNALEZISK-INFRA.md`
(pliki wspólne, prowadzi je koordynatorka).

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja A — podmiana wiersza `A4b`:**

```
| A4b | Front — WYGLĄD i DZIAŁANIE w przeglądarce | 🟡 | **1.09**, `audyt/plamy/A4b-RAPORT.md` (sha `18c9cd0`), chromium 1440×900 i 390×844, ścieżka przeklikana | ⛔ **4 znaleziska, w tym KRYT `A4b-F1`: „Zapłać” w płatności osadzonej rzuca `TypeError` i nie potwierdza płatności — sklep z kluczem publikowalnym NIE SPRZEDAJE**. Kontrola dodatnia: `confirm()` na właściwym obiekcie dowozi zamówienie `9901622` do `mail_wyslany`, `ramkaCaptcha=false`. 🟡 zostaje, bo NIE sprawdzone: BLIK, 3-D Secure, Firefox/Edge, tablet, axe na „Dziękujemy” i na panelu płatności |
```

**Do `audyt/REJESTR-ZNALEZISK-INFRA.md` (albo do rejestru PRODUKTU — `A4b-F1`, `A4b-F3`,
`A4b-F4` to wady produktu, nie infrastruktury; `A4b-F2` to wada naszego sprawdzania):**

```
| `A4b-F1` | **KRYT** · `assets/platnosc.js:110,150` — `checkout.loadActions()` oddaje `{type,actions}`, kod bierze opakowanie i woła `actions.confirm()` → `TypeError`; przycisk zostaje na „Przetwarzam…”, komunikat pusty i schowany, „Wróć”+„Kup teraz” nie odblokowują (`wToku` nie zerowane). Sklep z kluczem publikowalnym nie sprzedaje | `audyt/plamy/A4b-sonda-actions-*.log` (kształt obiektu), `A4b-sonda-slepy-zaulek.log` (ślepy zaułek), `A4b-kontrola-dodatnia.log` (kontrola dodatnia: właściwy obiekt → zam. `9901622` `mail_wyslany`, `ramkaCaptcha=false`) — BRAK strażnika | OTWARTE |
| `A4b-F2` | **DUZE** · `testy/e2e/przelot-osadzony.js:72` rozpoznaje captchę po SAMEJ OBECNOŚCI ramki i melduje „nie wada sklepu”, EXIT=3; nie patrzy na `pageerror`, więc nie widzi `A4b-F1`. Ta diagnoza stoi w `CO-WISI.md:832`, `RAPORT-ODDANIA.md:181,190,243`, `K11-RAPORT-0.9.27.md:196` i jako klasa 60 ściągi | `audyt/plamy/A4b-przelot-osadzony-jak-jest.log` (EXIT=3 przy zepsutej płatności) kontra `A4b-kontrola-dodatnia.log` (ten sam automat przechodzi) | OTWARTE |
| `A4b-F3` | **MALE** · `assets/dziekujemy.js:14-21` nie zna statusów `zwrocone` i `sporne`, a `includes/zakup.php:1245` oddaje surową kolumnę → kupujący po zwrocie widzi „Przetwarzamy Twoją płatność…” | odczyt kodu obu stron + słownik statusów z `grep` po `includes/*.php`; na stanowisku NIEODTWORZONE (brak wiersza `zwrocone`) — BRAK strażnika | OTWARTE |
| `A4b-F4` | **MALE** · `assets/front-ruch.js:292-306` wiąże widoczność pigułki z widocznością formularza, a `assets/platnosc.js:124` ten formularz chowa → pigułka „Kup teraz” wypływa w trakcie płatności i przykrywa panel (na telefonie pola karty) | `audyt/plamy/A4b-sonda-pigulka.log` + `A4b-zrzuty/zakup-4-karta-telefon.png` — BRAK strażnika | OTWARTE |
```

**Do `audyt/SCIAGA-EGZAMINATORA.md` — propozycja korekty klasy 60 (nie dopisuję sam):**
klasa 60 mówi dziś, że „»Przetwarzam…« bez końca to nie wada sklepu”. Zmierzone 1.09: **było
wadą sklepu**. Klasa powinna brzmieć odwrotnie — *obronę zewnętrznej usługi wolno orzec dopiero
po pokazaniu, że BEZ niej ta sama droga przechodzi*; sama obecność ramki captchy nie jest dowodem.

---

## Gdzie leżą zrzuty

⛔ **W repozytorium (`audyt/plamy/A4b-zrzuty-dowod/`, 15 plików, 2,1 MB) leżą wyłącznie zrzuty
CYTOWANE w tym raporcie** — dowód, który ma przeżyć tę sesję, musi być w origin.
Pełny komplet **97 zrzutów (19 MB)** z przelotu P1 zostaje w drzewie roboczym
`audyt/plamy/A4b-zrzuty/` i **nie jest commitowany**: `.git` tego repo ma już 324 MB
(`MAPA-TERENU-INFRA.md`, wiersz `G4` — „nikt nie badał czemu”), a 19 MB PNG-ów pogorszyłoby
to bez potrzeby. Komplet odtwarza się jedną komendą:
`flock /root/praca/zlecenia/wymiana/stos.lock node audyt/plamy/A4b-przelot-frontu.js`.
