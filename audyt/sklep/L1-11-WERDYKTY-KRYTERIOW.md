# L1-11 — werdykt po każdym z jedenastu kryteriów „GOTOWE”

Cel `L1-11` mapy polowania K7: jeden wiersz na kryterium `PLAN-PROJEKTU.md` §12 #1–#11.
Przebieg: 28.08.2026, gałąź `domkniecie-przelotu-0.9.20`, wtyczka `0.9.20`.

⛔ **Skala werdyktów jest binarna, ale z jawnym trzecim stanem.** Kryteria §12 mówią wprost:
„wykonywane **NA PACZCE na świeżej instalacji**”. Dowód zebrany na stosie warsztatowym mierzy
to samo zachowanie, ale **nie na tej drodze**, którą przejdzie klient. Dlatego:

| werdykt | znaczy |
|---|---|
| ✅ SPEŁNIONE | jest dowód wykonania, na właściwej drodze (paczka / świeża instalacja) albo taki, którego droga nie zmienia |
| ⚠️ SPEŁNIONE POZA PACZKĄ | zachowanie zmierzone, ale na stosie warsztatowym — do powtórzenia na paczce przed oddaniem |
| 🐞 SPEŁNIONE Z ZASTRZEŻENIEM | działa, ale z otwartym znaleziskiem w rejestrze |
| ⛔ NIESPEŁNIONE | brak dowodu albo dowód mówi „nie” |

---

## Werdykty

### #1 · Instalacja 3 wtyczek na czystym WP przez NIE-autora ≤30 min, bez fatali i notice — ✅ SPEŁNIONE
Instalację od zera przeszła **świeża sesja audytora**, która nie widziała tego kodu, na czystym
WordPressie postawionym osobno (`audytor_wp`, port 8892). Czas: **ok. 28 minut**, mieści się w progu.
⛔ Uczciwe zastrzeżenie z jego własnego raportu: ~4,8 minuty to zatrzymanie na blokadzie
środowiskowej (limit uploadu), a reszta to czas audytora piszącego skrypty przeglądarkowe, nie
czas człowieka klikającego myszką. **Człowiek byłby szybszy, nie wolniejszy** — próg trzyma
z zapasem w obie strony.
Brak notice’ów przy `WP_DEBUG` pokrywa osobno cel `Ł-08` (✅).
Dowód: `RAPORT-AUDYTORA.md` ZADANIE 1 · `audyt/K6-INSTALACJA-OD-ZERA.md` · mapa `Ł-08`.

### #2 · Zakup kartą testową: webhook → zamówienie → mail ≤60 s — 🐞 SPEŁNIONE Z ZASTRZEŻENIEM
Zakup przechodzi, mail ląduje w łapaczu, treść maila jest widoczna w panelu — potwierdzone
przelotem człowieka (A7, A8, A9 ✅) i celami `L1-02` (✅), `L3-14`.
🐞 `L3-14` stoi jako **znalezisko**: pomiar „mail ≤ 60 s” idzie przez `testy/e2e/przelot2.js`
i to on jest miarą, a nie zegarek. Wpis pozostaje w rejestrze.
Dowód: mapa `L1-02`, `L3-14` · przelot A7–A9.

### #3 · Link pobrania: właściwy plik, termin, limit, wyścig, HEAD — ✅ SPEŁNIONE
Cztery niezależne cele, wszystkie zamknięte z dowodem: `L2-03` (token/termin/limit/HEAD/dwa naraz),
`L3-06` (dwa pobrania przy ostatnim slocie → dokładnie jedno), `L3-07` (HEAD nie zużywa limitu),
`L3-08` (po terminie 72 h odmowa z komunikatem). Przelot potwierdził stronę użytkownika: A10 ✅
(ZIP o sensownej nazwie, drugi klik działa) i B3 ✅ (po ponownej wysyłce STARY link martwy).

### #4 · Webhook 2× = jedno zamówienie i jeden mail; wyścig; RESUME; zły podpis — ✅ SPEŁNIONE
`L3-01` ✅ (webhook ×2 = 1 zamówienie, 1 mail), `L3-02` ✅ (webhook równolegle z cronem = 1 mail
i 1 ważny token), `L3-03` ✅ (przerwanie przed mailem → ponowienie dokańcza, z wymuszonym
padnięciem), `L2-05` ✅ (podpis zły / powtórzony / spóźniony, trasa poza limiterem).
To kryterium ma najgęstsze pokrycie testami wyścigu w całym zleceniu.

### #5 · Cena z bazy, zakup bez zgody niemożliwy, capability, nonce, rate limit — 🐞 SPEŁNIONE Z ZASTRZEŻENIEM
Cztery z pięciu wymagań zamknięte: `L2-07` ✅ (cena i produkt wyłącznie z bazy), `L2-02` ✅
(nonce + zgoda + limit na `sklepag_kup`), `L2-01` ✅ (siedem handlerów: nonce i `manage_options`
PARĄ, testowane kontem subscribera), `L2-08` ✅ (limity **zmierzone**, nie zadeklarowane).
🐞 Zastrzeżenie dotyczy nonce’a: `L2-13` i `Ł-05` to otwarte znalezisko — strona produktu
odtworzona z page-cache z noncem sprzed 25 h. Wymaganie „mutacja bez nonce = odmowa” jest
spełnione, ale nonce może być **przeterminowany, nie nieobecny**, i to siedzi w rejestrze.
⭐ Domknięte w tym etapie: zakup bez zgody odrzuca serwer (`zakup.php:111-113`), a od 0.9.20
kupujący dostaje o tym komunikat po polsku (`SK-L6-001`).

### #6 · Pulpit jak w dobrych sklepach; historia nietykalna — ✅ SPEŁNIONE
`L1-04` ✅ (kiedy / za ile / w jakim czasie), `L3-12` ✅ (snapshot produktu w zamówieniu kontra
edycja produktu). Przelot: B2 ✅ (zakup na liście, wskaźniki, CSV się pobiera i otwiera),
B6 ✅ (edycja nazwy i ceny z ostrzeżeniem „ten produkt ma zamówienia — trzymają snapshot”).

### #7 · CRUD produktów, walidacje, upload pod losową nazwą w katalogu chronionym — 🐞 SPEŁNIONE Z ZASTRZEŻENIEM
`L1-06` ✅ (pełny CRUD, deaktywacja zamiast kasowania przy zamówieniach), `L2-09` ✅ (katalog
chroniony: bezpośredni GET, listing, path traversal). Przelot: B6 ✅ (bez potwierdzonego pliku
nie da się włączyć sprzedaży — komunikat po polsku), B7 ✅ (plik nie-ZIP odrzucony).
🐞 `Ł-09` otwarte: zip 20 MB+ przy uploadzie — walidacja i komunikat. Kryterium wymienia
„plik ponad limit rozmiaru” wprost, więc ta część zostaje z zastrzeżeniem.
⭐ Domknięte w 0.9.19: odrzut walidacji nie kasuje już wpisanej treści (zmierzone 28.08, 4 z 4 pól).

### #8 · Dziennik logowań i akcji + wykładnicze opóźnienie — ✅ SPEŁNIONE
`L1-07` ✅ (kto, kiedy, konkrety, także NIEUDANE), `L2-14` ✅ (lockout właściciela **zmierzony**:
ile czeka, sufit 60 s, brak twardej blokady), `L2-22` ✅ (kto może czytać dziennik; IP
i przeglądarka jako dane osobowe → retencja). Przelot B9 ✅: trzy złe hasła zapisane, czwarta
próba wyraźnie zwleka, potem dobre hasło wchodzi.

### #9 · Uninstall: domyślnie dane zostają, pełne czyszczenie zostawia pusty diff — 🐞 SPEŁNIONE Z ZASTRZEŻENIEM
`L4-05` ✅ (domyślnie dane zostają, sekrety i cron nie), `L2-17` ✅ (uninstall kasuje sekrety
i cron ZAWSZE, obie ścieżki), `L4-07` ✅ (zombie cron — test wykonany), `L4-10` ✅ (mapa
właścicieli: każda opcja, tabela i cron ma miejsce zapisu i miejsce sprzątania).
🐞 `L4-06` otwarte: pełne czyszczenie — diff tabel/opcji/stron/plików/cronów pusty. To jest
**dokładnie zdanie kryterium**, więc werdykt nie może być czysty, dopóki znalezisko żyje.
⛔ Audytor również tego nie sprawdził („nieodwracalnie skończyłoby dalsze testy na stanowisku”).

### #10 · Boty działają na czystym WP; strona produktu ma komplet S-03 — 🐞 SPEŁNIONE Z ZASTRZEŻENIEM
Oba rdzenie działają — pokrywa to osobny audyt botów (`audyt/boty/`), a tryb regułowy bez klucza
był testem obowiązkowym. Przelot A2 i A3 ✅: po kilkunastu sekundach widać, co bot robi, jak
działa, ile czasu oszczędza i ile kosztuje.
🐞 `L1-03` otwarte: strona produktu kontra komplet S-03. Skoro kryterium wymienia ten komplet
wprost, zastrzeżenie zostaje.
⛔ **Nie mam dowodu na tryb AI z żywym kluczem** — kryterium nazywa go „testem ręcznym przy
dostępnym kluczu”, więc to nie brak procesu, tylko brak okazji. Do odnotowania przed oddaniem.

### #11 · WCAG-lite, RWD, trzy przeglądarki, baner trybu testowego, szablony prawne — ⛔ NIESPEŁNIONE (jedna trzecia wymagania bez dowodu)
Większość tego kryterium stoi mocno: `L6-02` ✅ (axe: 0 naruszeń), `L6-04` ✅ (sonda pigułki 5/5),
`L6-05` i `L6-06` ✅ (przelot 27 punktów i trzy ścieżki klawiaturowe, domknięte 28.08),
`L1-08` ✅ (baner „TRYB TESTOWY”), `L2-20` ✅ (Regulamin i Polityka jako szablony, zero cudzych
danych, polityka wymienia Stripe i dziennik 90 dni). Zakup telefonem sprawdzony (przelot A12).

⛔ **Kryterium wymienia Chrome, Edge i Firefox. Edge nie został sprawdzony ani razu.**
Zmierzone: `grep -rin "edge"` po mapie polowania i rejestrze znalezisk → **zero trafień**, przy
próbie kontrolnej na słowie, które tam być musi (`grep -ric "firefox"` → trafienie). Cel `L6-03`
mówi wprost: „szelki 10/10 ×2 slugi, **chromium + firefox**”, a jego dowód to cztery przebiegi
dwóch przeglądarek. Trzeciej nie ma.

To jedyny werdykt ⛔ w tym zestawieniu i jedyne miejsce, gdzie **kryterium wymienia coś, czego
nie dotknęliśmy**. Nie nazywam tego drobiazgiem: Edge to domyślna przeglądarka Windowsa, czyli
najbardziej prawdopodobna przeglądarka właściciela sklepu.

---

## Podsumowanie

| werdykt | ile | kryteria |
|---|---|---|
| ✅ SPEŁNIONE | 5 | #1, #3, #4, #6, #8 |
| 🐞 SPEŁNIONE Z ZASTRZEŻENIEM | 5 | #2, #5, #7, #9, #10 |
| ⛔ NIESPEŁNIONE | 1 | #11 (brak Edge) |

⛔ **Czego to zestawienie NIE dowodzi.** Kryteria §12 każą wykonywać je NA PACZCE, na świeżej
instalacji. Na tej drodze przeszło tylko kryterium #1 (instalacja od zera przez audytora,
na paczce `0.9.18`). Pozostałe dziesięć stoi na dowodach ze stosu warsztatowego i z przelotu,
czyli mierzy **to samo zachowanie inną drogą**. Przed oddaniem trzeba je powtórzyć na paczce
`0.9.20` — to jest treść kroku 8 planu K7, nie luka tego dokumentu, ale musi być powiedziane
wprost, żeby nikt nie przeczytał pięciu ✅ jako „sprawdzone tak, jak każe rubryka”.
