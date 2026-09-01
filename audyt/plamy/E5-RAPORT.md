# E5 — Aktualność `DECYZJE.md`

**Audytor:** sesja audytu białych plam, 2026-09-01 · **`commit_head`:** `235ec6a`
**Zakres z mapy terenu:** `E5 | DECYZJE.md — czy aktualne | ⬜ NIETKNIĘTE`
**Pytanie plamy:** „Czy zapisane decyzje opisują dzisiejszy produkt."

---

## Wobec czego oceniam (kryteria + skąd) — ZAPISANE PRZED OGLĄDANIEM

| # | Kryterium | Źródło |
|---|---|---|
| **E-K1** | `DECYZJE.md` to „**D1-D24 — rzeczy ŚWIADOMIE nierobione, nie żądaj ich**" — czyli dokument, którym każdy nowy uczestnik ma się kierować, żeby nie żądać rzeczy odrzuconych | `CLAUDE.md`, §Gdzie szukać wiedzy |
| **E-K2** | ⛔ „To nie jest projekt na Cloudflare/Worker/Next.js — jeśli gdzieś tak napisano, to **świat sprzed zwrotu D16 i jest nieaktualny**" | `CLAUDE.md`, §Co to jest |
| **E-K3** | Własna deklaracja pliku: „rejestr (decyzja + uzasadnienie + odrzucone alternatywy)… Zamiennik pytań do zlecającego" | `DECYZJE.md:1-3` |
| **E-K4** | Klasa 3 ściągi: **dokumentacja kłamie względem kodu** (wracała ×4) | `audyt/SCIAGA-EGZAMINATORA.md` |
| **E-K5** | Klasa 8 / 65: **bliźniak** — załatana jedna ścieżka, siostrzana została; **kompensacja przywraca PODZBIÓR** | `audyt/SCIAGA-EGZAMINATORA.md` |
| **E-K6** | ⭐ Pytanie ODWROTNE, dołożone po wpadce z importem CSV: „**Czy każda FUNKCJA, którą kod realizuje, jest w dokumentacji opisana?**" — idź od kodu do dokumentu, nie odwrotnie | skill `jak-audytowac`, §LUKA W NASZEJ WŁASNEJ MAPIE |

⛔ Zastrzeżenie do E-K1: `CLAUDE.md` mówi „D1-**D24**", a plik ma dziś pozycje do **D37**.
Sam ten rozjazd nie jest wadą `DECYZJE.md` — jest wadą odsyłacza w `CLAUDE.md`. Zgłaszam
poniżej, ale nie jako błąd rejestru.

---

## Co i czym zmierzyłem

| # | Pomiar | Wynik |
|---|---|---|
| M1 | rozmiar i kształt: `wc -l`, `grep -cE '^\| D[0-9]+[a-z]? \|'` | 43 linie, **36 wierszy decyzji** w jednej tabeli |
| M2 | numeracja: które numery występują | D1–D28, **D28b**, D31–D37 → ⛔ **D29 i D30 NIE ISTNIEJĄ** (`grep -cE '\| D29 \|'` = 0, to samo D30). Próba kontrolna: `\| D28b \|` = 1, `\| D37 \|` = 1 |
| M3 | czy plik ma mechanizm oznaczania nieaktualności | **TAK, dwa razy użyty**: `D10` niesie „**ZDEZAKTUALIZOWANE**", a `D37` mówi wprost „**D10 opisuje stan z 17.08 i jest PRZESTARZAŁE**"; `D15` ma „**UNIEWAŻNIONE D16**" |
| M4 | które decyzje opisują świat sprzed piwotu (E-K2) i czy mają znacznik | `D1` (Cloudflare Worker+D1+R2) — **BRAK znacznika** · `D3` (panel na origin Workera) — **BRAK** · `D4` (pobrania przez endpoint Workera) — **BRAK** · `D13` (wariant wpięcia link/skrypt) — **BRAK** · `D15` (katalog `sklep/` + `wtyczka.js`) — **JEST**: „UNIEWAŻNIONE D16" |
| M5 | najwyższa wersja produktu wymieniona w rejestrze | `grep -oE "0\.9\.[0-9]+" \| sort -V` → **0.9.26** (2 trafienia). Produkt na dziś: **0.9.28** |
| M6 | co przyniosły wersje bez wpisu (E-K6, kierunek kod→dokument) | `CHANGELOG.md:23` (0.9.28, schemat 9): „Kompensacja po nieudanej wysyłce przywraca stan ostatniej **DORĘCZONEJ** wysyłki, nie »zastany«" — nowe kolumny `dor_*`. `:56` (0.9.27): 404 na HEAD, zgoda z wtyczkami SEO gospodarza |
| M7 | czy decyzja z 0.9.28 jest opisana **gdzie indziej** | **TAK** — `DOKUMENTACJA-TECHNICZNA.md` i `CHANGELOG.md` (`grep -rln "dor_token_pobrania\|DORĘCZONEJ" --include=*.md`) |
| M8 | rdzeń `codeowners` w rejestrze | 0 trafień (patrz `C12-RAPORT.md`); próba kontrolna `hook` → 6 |

---

## Kontrola dodatnia

1. **Luka numeracji (M2) jest pomiarem, nie literówką wzorca** — ten sam wzorzec
   `\| D<nr> \|` **trafił** w `D28b` i `D37`, a zwrócił zero dla `D29` i `D30`.
2. **Brak znacznika przy `D1`/`D3`/`D4`/`D13` (M4) nie jest ślepotą** — ten sam sposób
   szukania (`ZDEZAKTUALIZOWAN|PRZESTARZ|NIEAKTUALN|UNIEWAŻNION|D16` w wierszu) **zaświecił**
   przy `D15` („UNIEWAŻNIONE D16"). Mechanizm istnieje, wzorzec go widzi — po prostu przy
   czterech wierszach go nie ma.
3. **Kierunek odwrotny (E-K6) sprawdzony osobno** — poszedłem od `CHANGELOG`/schematu do rejestru
   (M6, M7), nie od rejestru do kodu. Bez tego kroku „wszystko, o co zapytałem, się zgadza"
   wyglądałoby jak „czysto".

---

## WYNIK: **ZNALEZIONE** — 2 znaleziska (1 średnie, 1 drobne) + 1 obserwacja bez wagi

---

## Znaleziska

### `E5-F1` — **SRED** · Cztery decyzje z rejestru opisują architekturę, której projekt nie ma — a plik ma mechanizm oznaczania i użył go przy piątej

**Objaw:** `CLAUDE.md` ostrzega wprost: „⛔ To nie jest projekt na Cloudflare/Worker/Next.js —
jeśli gdzieś tak napisano, to świat sprzed zwrotu D16 i jest nieaktualny". W rejestrze,
który ma być „zamiennikiem pytań do zlecającego", **cztery wiersze mówią dokładnie to**,
bez jednego słowa ostrzeżenia:

| Wiersz | Cytat z `DECYZJE.md` | Stan na dziś |
|---|---|---|
| `D1` | „Zaplecze: **Cloudflare Worker+D1+R2**, frontend czysty statyczny" | wtyczka WordPressa, tabele MySQL w bazie WP |
| `D3` | „**Panel admina NA ORIGINIE WORKERA**" · uzasadnienie: „wspólny origin z gospodarzem = **kradzież sesji przez skrypty hosta** (zbieżność 5 krytyk)" · odrzucona alternatywa: „panel w katalogu `sklep/` na stronie hosta" | panel siedzi w `wp-admin`, czyli **na tym samym originie co strona gospodarza** |
| `D4` | „Pobrania przez **endpoint Workera** (HMAC, streaming, atomowy licznik PRZY STARCIE, limit 10, bez Accept-Ranges)" | `includes/pobieranie.php` — mechanizm ten sam, nośnik inny |
| `D13` | „Wariant A wpięcia (link w źródle) preferowany; B (skrypt) tylko fallback" | wpięcia nie ma — produkt instaluje się jak wtyczka |
| `D15` | katalog `sklep/` + `wtyczka.js` + jedna linijka `<script>` | ✅ **„UNIEWAŻNIONE D16"** — jedyny z piątki z znacznikiem |

**Plik i linia:** `DECYZJE.md:7` (`D1`), `:9` (`D3`), `:10` (`D4`), `:19` (`D13`) — bez znacznika;
`:21` (`D15`) — ze znacznikiem; `:22` (`D16`) — sam piwot; `:16`+`:42` (`D10`↔`D37`) — drugi
przykład poprawnie działającego mechanizmu.

**To jest wzorcowy bliźniak (E-K5):** zauważono, że piwot unieważnia `D15`, dopisano znacznik
przy `D15` — i nie przeszło się po pozostałych czterech wierszach tej samej rodziny.
Dokładnie ta klasa, którą mamy zapisaną jako 8 i 65.

⛔ **Najostrzejszy jest `D3`, i nie chodzi w nim o nieaktualność, tylko o ODWRÓCENIE.**
Rejestr mówi, że wspólny origin z gospodarzem odrzucono, bo grozi **kradzieżą sesji przez skrypty
hosta**, i że zgodziło się na to „pięć krytyk". Dziś panel stoi **właśnie tam** — w `wp-admin`
tej samej witryny. Ryzyko, które `D3` nazwał, nie zniknęło razem z Workerem: zmienił się
model zagrożenia (wtyczki i motyw gospodarza zamiast skryptów strony), a **nikt nie zapisał,
dlaczego jest ono teraz do przyjęcia**. Rejestr, który miał chronić przed powtarzaniem
odrzuconych rozwiązań, dziś opisuje jako odrzucone to, co robimy.

**Skutek DLA KLIENTA:** nie dla kupującego — dla **każdego, kto wejdzie w ten projekt**.
Nowa sesja, nowy builder albo egzaminator czyta `DECYZJE.md` jako źródło i dostaje architekturę
sprzed 17.08. Chroni nas dziś wyłącznie jedno zdanie w `CLAUDE.md` — czyli **inny plik naprawia
skutek, zamiast żeby źródło było prawdziwe**.

**RODZAJ SKUTKU:** `zaobserwowany` (cztery wiersze bez znacznika przy piątym ze znacznikiem,
z próbą kontrolną). **Waga: ŚREDNIA.**

**Propozycja naprawy:** dopisać `D1`, `D3`, `D4`, `D13` znacznik w tym samym kształcie, którego
plik już używa („**UNIEWAŻNIONE D16**" / „ZDEZAKTUALIZOWANE, patrz D…"), a przy `D3` **osobne
zdanie o ryzyku**: co zastąpiło ochronę, którą dawał osobny origin, i dlaczego dziś to wystarcza.
⛔ Nie kasować tych wierszy — rejestr decyzji ma pokazywać także drogę, którą się szło.
⭐ Strażnik: kontrola licząca wiersze zawierające `Worker|Cloudflare|D1+R2` **bez** znacznika
unieważnienia — dziś zwróciłaby 4, po naprawie ma zwracać 0, i **padać**, gdy urośnie.

---

### `E5-F2` — **MALE** · Rejestr ma dziurę w numeracji: `D29` i `D30` nie istnieją

**Objaw:** ciąg biegnie `…D27, D28, D28b, **D31**, D32…`. Numerów `D29` i `D30` nie ma
w `DECYZJE.md` ani w `PLAN-PROJEKTU.md`.

**Dowód:** `grep -cE '\| D29 \|'` → **0**, `grep -cE '\| D30 \|'` → **0**;
próba kontrolna tym samym wzorcem: `D28b` → 1, `D37` → 1.

**Skutek DLA KLIENTA:** czytelnik rejestru nie wie, czy dwie decyzje **podjęto i zgubiono**,
czy numery po prostu przeskoczono. W dokumencie, który sam siebie nazywa „zamiennikiem pytań
do zlecającego", to jest dokładnie ta niepewność, której ma nie być — a rozstrzygnąć ją może
tylko autor. **Awaria cicha:** nic nie mruga, tabela wygląda kompletnie.

**RODZAJ SKUTKU:** `zaobserwowany`. **Waga: DROBNA.**

**Propozycja naprawy:** wstawić dwa wiersze `D29` i `D30` z treścią „numer nieużyty" albo —
jeśli decyzje istniały — odtworzyć je z historii. ⭐ Tania kontrola na przyszłość: sprawdzenie
ciągłości numeracji, które **pada** przy dziurze.

---

## Obserwacja BEZ wagi (nie zgłaszam jako błędu)

**Rejestr kończy się na 0.9.26, produkt jest na 0.9.28.** Najwyższa wersja wymieniona
w `DECYZJE.md` to `0.9.26` (`D37`, SEO stron sklepu), a od tego czasu weszły dwie wersje,
w tym jedna z **nową decyzją projektową i zmianą schematu**: „kompensacja po nieudanej wysyłce
przywraca stan ostatniej **DORĘCZONEJ** wysyłki, nie »zastany«" (0.9.28, kolumny `dor_*`,
schemat 9).

⛔ **Nie zgłaszam tego jako znaleziska i piszę wprost, dlaczego:** sprawdziłem kierunek odwrotny
(E-K6) i ta decyzja **jest opisana** — w `DOKUMENTACJA-TECHNICZNA.md` i w `CHANGELOG.md` (M7).
Nie ma reguły mówiącej, że każda zmiana zachowania musi mieć wiersz w `DECYZJE.md`; plik sam
zawęża się do decyzji „drogo cofnąć". **Wymyślanie takiej reguły byłoby wysłaniem naprawy tam,
gdzie nic nie jest zepsute.** Zapisuję to jako sygnał do świadomej decyzji koordynatorki:
jeśli rejestr ma być kompletny wobec wersji, to dwie ostatnie do niego nie trafiły.

**Drugi taki sygnał:** `CLAUDE.md` odsyła do „`DECYZJE.md` (**D1-D24**)", a plik ma dziś
36 wierszy do `D37`. To rozjazd w **odsyłaczu**, nie w rejestrze — ale odsyłacz czyta każda
nowa sesja i może uznać, że dalsze pozycje są czymś innym. Naprawa: odesłać po **tytule**,
nie po zakresie numerów, zgodnie z regułą `CLAUDE.md` p. 3 („odsyłaj po tytule sekcji, nie
po numerze — numery się przesuwają").

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego |
|---|---|
| **Treść wszystkich 36 decyzji wobec kodu, jedna po drugiej** | budżet; wybrałem rodzinę „architektura sprzed piwotu" (5 wierszy) i sprawdziłem ją w całości, plus zgodność wersji. Pozostałe ~30 wierszy: **NIE SPRAWDZIŁEM** |
| **Czy `D29`/`D30` istniały kiedyś** | wymaga przejścia historii pliku (`git log -p -- DECYZJE.md`); nie zdążyłem |
| **Czy odrzucone alternatywy są nadal odrzucone** | rejestr ma kolumnę „odrzucone alternatywy" i żadnej z nich nie skonfrontowałem z dzisiejszym kodem — poza `D3`, gdzie rozjazd rzucał się w oczy |
| **`archiwum-procesu/` i inne rejestry decyzji** | mogą istnieć równoległe zapisy; nie szukałem |
| **Czy `D3` opisuje realne dzisiejsze ryzyko** | to pytanie o bezpieczeństwo (wtyczki gospodarza a panel sklepu), nie o aktualność dokumentu — inny teren |

## ⭐ Czego NIE było na mojej liście

1. **Decyzje, które ktoś podjął i których NIE zapisał w żadnym pliku.** Mierzyłem rejestr wobec
   kodu i wobec `CHANGELOG`. Decyzja podjęta w czacie i nigdzie niezapisana jest z definicji
   niewidoczna dla mojego pytania.
2. **Czy uzasadnienia są nadal prawdziwe.** Sprawdzałem, czy DECYZJA opisuje dzisiejszy produkt.
   Nie sprawdzałem, czy jej UZASADNIENIE („0 zł, limity potwierdzone", „zbieżność 5 krytyk")
   nadal się broni — a przy `D3` to właśnie uzasadnienie zestarzało się najgorzej.
3. **Kolumna „Kto".** Rejestr notuje, kto podjął decyzję. Nie sprawdzałem, czy decyzje oznaczone
   jako „klepnięte przez właściciela" faktycznie mają pokrycie w cytatach z czatu.
4. **Decyzje botów** — `boty/PONAD-SPECYFIKACJE-BOTY.md` i osobne rejestry ChatBota/Segregatora
   nie były w moim pytaniu.

---

## Propozycja wiersza do mapy terenu i do rejestru

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja E — podmiana wiersza `E5`:**

```
| E5 | `DECYZJE.md` — czy aktualne | 🟡 | **1.09**, `audyt/plamy/E5-RAPORT.md` (sha `235ec6a`) | ⛔ **2 znaleziska.** `D1`, `D3`, `D4`, `D13` opisują architekturę sprzed piwotu (Cloudflare Worker) **bez znacznika nieaktualności**, choć plik ten mechanizm MA i użył go przy `D15` („UNIEWAŻNIONE D16") i `D10`↔`D37` — klasyczny bliźniak (klasy 8/65). Najostrzejszy `D3`: odrzucił wspólny origin z gospodarzem z powodu kradzieży sesji, a panel stoi dziś w `wp-admin` tej samej witryny i nikt nie zapisał, czemu to teraz OK. `D29` i `D30` **nie istnieją** (próba kontrolna: `D28b` i `D37` trafiają). Bez wagi: rejestr kończy się na 0.9.26 przy produkcie 0.9.28 — ale decyzja z 0.9.28 JEST opisana w `DOKUMENTACJA-TECHNICZNA.md`, więc nie zgłaszam. 🟡 nie sprawdzone: ~30 z 36 wierszy jeden po drugim, historia `D29`/`D30`, kolumna „odrzucone alternatywy” |
```

**Do rejestru znalezisk:**

```
| `E5-F1` | **SRED** · `DECYZJE.md:7,9,10,19` (`D1`, `D3`, `D4`, `D13`) opisują architekturę Cloudflare Worker, której projekt nie ma od piwotu `D16`, i nie mają znacznika nieaktualności — choć plik go używa przy `D15` (`:21`, „UNIEWAŻNIONE D16”) i `D10`↔`D37`. `D3` jest ODWRÓCONY: odrzuca wspólny origin z gospodarzem jako ryzyko kradzieży sesji, a panel sklepu stoi w `wp-admin` tej samej witryny bez zapisanego uzasadnienia zmiany | wzorzec znacznika zaświecił przy `D15`, zgasł przy czterech pozostałych (próba kontrolna w tej samej komendzie) — BRAK strażnika | OTWARTE |
| `E5-F2` | **MALE** · dziura w numeracji rejestru: `D29` i `D30` nie istnieją ani w `DECYZJE.md`, ani w `PLAN-PROJEKTU.md`; czytelnik nie wie, czy decyzje zgubiono, czy numery przeskoczono | `grep -cE '\| D29 \|'` → 0 i `\| D30 \|` → 0, próba kontrolna `\| D28b \|` → 1, `\| D37 \|` → 1 — BRAK strażnika | OTWARTE |
```
