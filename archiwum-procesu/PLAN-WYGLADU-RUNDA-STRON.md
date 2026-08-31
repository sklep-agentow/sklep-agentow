# PLAN WYGLĄDU — RUNDA DZIAŁU STRON (25.08.2026) · gigant-prompt

Status: klepnięty przez Dzidka 25.08 („robisz wygląd na gotowo"; kolejność: NAJPIERW `/sklep/`,
pokaz, potem strony produktów tą samą metodą). Brief-amunicja: `zlecenia/wymiana/BRIEF-DZIAL-STRON-SKLEP.md`.
Poprzednik: `PLAN-WYGLADU-K5.md` (tokeny D27 — nadal obowiązują). Nietykalne: nagłówek, stopka
i wszystko od motywu gospodarza. Funkcje i treści sprzedażowe — bez zmian (tylko FORMA).

## 0. POMIAR PRZED (25.08, Playwright, localhost:8890)
- 3 strony: ładowanie < 1 s, 0 błędów JS, 0 przepełnienia poziomego na 390 px.
- `/sklep/` 3354 px (desktop) — hero = sama kolumna tekstu z pustką po prawej; kroki i FAQ =
  gołe listy w torze 44 rem (martwa pustka na ~40 % szerokości); zero głębi.
- Waga: `/sklep/` ~0,2 MB · chatbot ~1,3 MB · segregator ~1,5 MB (media galerii 1,45 MB — próg!).
- Sędzia bazowy: `_robocze` (wynik w opisie PR-a).

## 1. WZORCE DZIEDZINY (recon w ruchu 25.08 — `docs/dzial-agencji/referencje/recon-linear`, `recon-supabase`)
- **Linear** (kanon „Linear-style dark"): Inter Variable + Berkeley Mono (sans+mono jak nasz
  Geist), **zero GSAP/Lenis, zero parallaxu** (delta: wszystkie prędkości = 1, tylko sticky
  nav). Produkt w ciemnych OKNACH (radius ~16, hairline, cień) na PEŁNĄ szerokość toru,
  kadr ucięty u dołu; głębia przez NAKŁADANIE paneli (karta Slacka nachodzi na tablicę);
  nagłówki sekcji 2-kol (wielki tytuł lewo, opis + mono-numer „1.0 Intake →" prawo);
  3 kolumny cech z pionowymi hairline'ami; 197 keyframes = ożywione tło (siatka kropek).
- **Supabase**: Inter/Manrope + Source Code Pro; **bento równych kafli** z wizualem w każdym;
  zrzut w oknie z 3 kropkami; piguły-zakładki; tytuł dwutonowy (jasna linia + wyblakła).
- **Gospodarz Automatic AI** (zmierzone computed-style): H1 100,8 px / 600 / −3 px (=−0,03 em);
  H2 48 px; sekcje `py-112px`, co druga na `bg-panel/30` z `border-y` hairline; hero po prawej
  ŚWIECĄCA LIMONKOWA MGŁAWICA z drobinkami i mono-adnotacjami na hairline'ach („WYNIK",
  „INTELIGENCJA", „DANE"); pigułka statusu z pulsującą kropką; „status: running · 24/7"
  z migającym kursorem; karty usług: mono-numer „02" + hairline + ikona w górnym wierszu.
- **Viktor Oddy / motionsites** (destylat): poster→fade dla wideo, glassmorphism, blob-gradient
  na `@property` bez JS, hover jako reakcja łańcuchowa (3 zmiany naraz), `prefers-reduced-motion`
  wpisany obok animacji, mantra „pixel-perfect / buttery-smooth 60 fps".

## 1b. KOREKTA PO OKU DZIDKA (25.08, na tunelu, wersja r2 = 80/100)
„Lipa — nie żółte plamy, tylko coś interaktywnego, gwiazdy/droga mleczna; brak głębi; animacje na
innych podstronach są lepsze — zobacz, jak tam to działa." → **wzorcem pierwszym jest GOSPODARZ**,
Linear/Supabase tylko strukturą. Rozebrany `volt.js` (hero strony głównej): canvas z dyskiem cząstek
3D (yaw/pitch/perspektywa), promienie, pył, smugi (`rgba(8,9,11,.3)` co klatkę), poświata z oddechem,
przyciąganie do myszy (130 px), adaptacyjna liczba cząstek (110 klatek → redukcja ×0,72), dpr ≤ 1,75,
statyczna klatka przy reduced-motion, pauza poza ekranem; poświata CSS gospodarza = `bg-volt/0.06`
(nasza 0.42 była 7× za mocna = „plama"). Podstrony: `.reveal` 18–29/strona, `data-cascade`,
`dashflow` 1.4 s (kreska 4/8, offset −24 px), `pulse-dot` 2.4 s (pierścień 7 px), `glow-breathe`
7 s (.7↔1), `orbit` 16 s, `rise-in`, `page-enter`, 1 canvas „rail" na każdej podstronie.
Zbudowane: WŁASNA galaktyka w tej rodzinie (front-ruch.js) na całym hero, panel-szkło z tiltem 3D
i paralaksą, awatar-maskotka, dashflow na adnotacjach i torze kroków, pulse-dot z pierścieniem.

## 1c. WYNIK PR 1 (`/sklep/`, 25.08 ~07:00, werdykt przeglądu na żywo: „pięknie") — dok = kod
Zbudowane ostatecznie (po 4 rundach z okiem na żywo): hero 2-kol z panelem nocnej zmiany (pętla
CSS, awatar-maskotka, tilt −4°, paralaksa, bez backdrop-filter) · tło „blueprint z sygnałami" na
CAŁEJ wyspie (siatka jak na ilustracjach + pingi w węzłach; czysty CSS, zero canvasa — galaktyka
z §1b USUNIĘTA po zgłoszeniu „nie 1:1 hero gospodarza" i przeciążeniu laptopa) · karty z głową
gospodarza i hoverem łańcuchowym, ilustracja ChatBota z „bazą pytań" · kroki 3 karty z torem
dashflow · dla kogo i FAQ pełna szerokość · baner z duetem maskotek · etykiety `[ 01 · … ]` ·
pigułka TRYB TESTOWY cicha (limonka w tytule) · **wejścia 1:1 z motywem**: ukrycie od pierwszej
klatki klasą z `<head>`, kaskada 26 px / .3 s / .5 s / krok 0,08 s, kierunki z `--reveal-from`
(−24/−36/−28/+36, karty Y14+scale.94) · cache-busting `?ver` = wersja + mtime · **0.9.10**.
Sędzia (sonnet, ±3): 68 → 80 → 83 → 82; arbitrem było oko przeglądającego. Lekcja działu:
`docs/dzial-agencji/wiedza/LEKCJA-dopasowanie-do-stylu-gospodarza.md` + `narzedzia/zmierz-ruch-gospodarza.js`.

## 2. DECYZJE RUNDY (moje, z powodem)
- **Zero bibliotek ruchu** (GSAP/Lenis) — wtyczka chodzi na cudzym motywie i nie może mu
  przejąć scrolla; DOWÓD: oba wzorce premium też ich nie używają. Ruch = CSS + istniejący
  IntersectionObserver (front-ruch.js).
- **Jedna sygnatura ruchu**: „panel nocnej zmiany" w hero (żyje w pętli) + mgławica gospodarza.
  Reszta spokojna: reveal per element, hover łańcuchowy.
- Media brandowe = SVG/CSS (0 KB, ostre, ożywialne) + nagrania ekranu prawdziwego produktu.
  Żadnych generatorów AI (brak kluczy/GPU; sędzia tnie spójność mediów za stockowy zgrzyt).
- Mono-etykiety na liście NUMEROWANE `[ 01 · … ]` jak u gospodarza (decyzja zmieniona w rundzie 2
  z okiem na żywo); strony produktów dostają numerację w PR 2 (spójność).

## 3. GIGANT-PROMPT `/sklep/` (PR 1: gałąź `wyglad/sklep-lista`, wersja 0.9.9 → 0.9.10)

Brief sprzed budowy, DOPROWADZONY po budowie do stanu kodu (liczby niżej = kod na wierzchołku
gałęzi). Wynik rundy i to, co po drodze wypadło (poświata, galaktyka), opisuje §1c.

### KLIMAT
Ciemna, spokojna strona produktu w świecie Automatic AI: **Linear spotyka panel systemowy
gospodarza spotyka bento Supabase**. Zero stocku. Każda powierzchnia lekko żyje: dryf mgławicy,
puls kropki statusu, pętla panelu w hero, elementy wchodzą po kolei.

### HERO (2 kolumny: 1.18fr / 0.82fr, `align-items:center`; < 56 rem: 1 kolumna, panel POD tekstem)
- LEWO: `[ SKLEP ]` · H1 „**Boty**, które robią robotę." — `clamp(2.8rem, 6.4vw, 5.75rem)`, 600,
  `letter-spacing:-0.035em`, `line-height:0.98` (92 px przy 1440) · lead 1.15 rem pył, max 34 rem · pigułka trybu
  testowego · linia demo-CTA · pasek konkretów (mono).
- PRAWO — **PANEL NOCNEJ ZMIANY** (sygnatura): okno aplikacji (pasek 3 kropek jak w kartach,
  węgiel, hairline, radius 16, cień `0 30px 80px rgba(0,0,0,.5)`), w środku dwa wiersze:
  (a) `● [ CHATBOT LIVE · ONLINE ]` + dymki z ISTNIEJĄCEGO mockupu strony produktu (te same
  klasy `.sklepag-dymek`, te same teksty: „Ile kosztuje strzyżenie?" → kropki „pisze…" →
  odpowiedź bota) — pętla; (b) `● [ SEGREGATOR · PORANNY RAPORT ]` + 3 wiersze maili z
  ISTNIEJĄCEGO PRZED/PO (np. „RE: wycena ogrodzenia") dostające po kolei limonkowe tagi
  `OFERTY` / `FAKTURY` / `RESZTA` — pętla. Cykl całości ~10 s, `steps`/opacity, zero JS.
  Za oknem: **mgławica** gospodarza (radial-gradient limonka .16→0, ~520 px, dryf 16 s przez
  `@property --sklepag-kat` + `transform`), mono-adnotacje na hairline'ach: „TWOJA STRONA"
  (góra-prawo) i „TWOJA SKRZYNKA" (dół-lewo). Uczciwość: `aria-hidden`, teksty tylko z
  zatwierdzonego mockupu (żadnych nowych obietnic/liczb).
- Stany: bez JS/`reduced-motion` = panel statyczny w stanie końcowym (wszystko widoczne).

### SEKCJE (pełna szerokość toru 72 rem; `max-width` zostaje tylko na tekstach)
1. **KARTY PRODUKTÓW**: jak dziś + głowa karty w języku gospodarza: mono-numer `01`/`02` +
   hairline + ikona; hover ŁAŃCUCHOWY: `translateY(-4px)` + obrys limonka .45 + cień
   `0 24px 60px rgba(0,0,0,.45), 0 0 0 1px rgba(191,255,56,.25)` + strzałka CTA `translateX(4px)`
   + ilustracja `scale(1.03)`; `transition .35s cubic-bezier(.16,1,.3,1)`.
2. **OD ZAKUPU DO DZIAŁANIA**: 3 równe karty w rzędzie (1 kolumna < 48 rem): wielki mono-numer
   `01` limonka + hairline + tekst kroku; wspólna linia-tor pod numerami (jak karty usług
   gospodarza).
3. **DLA KOGO**: 2 równe karty na pełną szerokość, ikona produktu przy tytule, ten sam hover.
4. **PYTANIA**: siatka 2 kolumn (1 < 48 rem), każdy blok Q/A z górnym hairline'em — pełna
   szerokość, zero martwej pustki.
5. **FINAŁ**: panel-baner (węgiel, hairline, radius 20, padding `clamp(2rem,5vw,3.5rem)`),
   mgławica w prawym rogu (ta sama co w hero, mniejsza), tytuł `clamp(1.8rem,4vw,2.8rem)`,
   dwa przyciski; mono-linia „status: running · 24/7" NIE — to własność gospodarza.

### PALETA (bez zmian — D27) + nowe pochodne
tusz #08090B · węgiel #101116 · papier #F5F5F5 · pył #8F929C · limonka #BFFF38 · hairline
rgba(245,245,245,.08) · **mgławica** rgba(191,255,56,.16→0) · **cień okna** rgba(0,0,0,.5).

### TYPOGRAFIA (dziedziczona z motywu; role)
Geist Sans → tytuły 600 (H1 −0.035 em, H2 −0.02 em) i body 400 · Geist Mono → etykiety,
numery, adnotacje, pasek konkretów (0.72–0.8 rem, 0.14–0.18 em).

### MOTION SYSTEM
- Reveal: sekcja jak dziś (26 px, .3 s / .5 s, easing gospodarza) + **kaskada dzieci**:
  opóźnienie `--sklepag-d` = n × 0,08 s (krok `data-cascade="0.08"` gospodarza), nth-child do 6
  w hero i do 4 w siatkach (karty, kroki, dla kogo, FAQ); panel hero 0,24 s, duet banera 0,16 s.
- Hover: `.35s cubic-bezier(.16,1,.3,1)`. Puls kropki: istniejący 2.2 s. Mgławica: 16 s.
  Panel: ~10 s pętla. Wszystko gaśnie przy `prefers-reduced-motion` (reguła globalna istnieje).
- Fundament: `overflow-x: clip` NA WYSPIE (nie hidden — sticky-pigułka strony produktu).

### STACK
PHP markup w `sklep-agentow.php` (`sklepag_shortcode_sklep`) · CSS w `assets/front.css`
(nowy blok „RUNDA STRON 25.08") · JS: bez zmian poza ewentualnym `--sklepag-i` — zrobione
CSS-em (nth-child). Bump 0.9.8 → 0.9.9 → 0.9.10 (nagłówek + stała; cache-bust assetów
`?ver` = wersja + mtime).

### CZEGO NIE POWTÓRZYĆ (lekcje)
Budować z głowy bez pomiaru · pokazywać surowe · `overflow-x:hidden` (zabija sticky) ·
biały zrzut bez ramy okna · zero-JS-fallback, który chowa treść · testować pełnostronicowym
zrzutem (reveal nie odpala — klatki po WOLNYM scrollu).

## 4. KRYTERIA ODBIORU PR 1
Sędzia ≥ 85/100 i zero priority-1 (max 2 rundy sędziego, potem decyzja Gosi) · zrzuty
desktop + telefon obejrzane w całości · `bash narzedzia-qa/bramki.sh` EXIT 0 · waga `/sklep/`
< 0,5 MB · zero regresji (baner testowy = ten sam napis; linki produktów; reveal bez JS).

## 6. GIGANT-PROMPT STRONY PRODUKTU — PR 2a: `/sklep/produkt/chatbot-live/` (25.08, po scaleniu PR 1)

Kolejność (Dzidek 25.08): NAJPIERW ChatBot, Segregator osobnym PR-em tą samą metodą. Gałąź
`wyglad/produkt-chatbot`. Wzorzec ruchu = GOSPODARZ (`recon-automatic-ai-RUCH.json`, lekcja działu),
system z PR 1 przeniesiony 1:1: tło blueprint + pingi, etykiety `[ 01 · … ]`, kaskada/kierunki,
sekcje pełnej szerokości, hover łańcuchowy, cache-busting. Funkcje i treści sprzedażowe BEZ ZMIAN
(narracje faz galerii sklejone z istniejących zdań — zero nowych obietnic i liczb).

### POMIAR PRZED (baza: `zrzuty-baza-chatbot.json` w scratchpadzie sesji)
Galeria 2-kolumnowa: wideo na całą szerokość, dalej 4 kafle różnych wysokości (okno czatu 682×908,
formularz 320×440, ustawienia 1078×2857 — biała plama tnąca panel w pół, statystyki 1100×790);
kroki = goła lista; FAQ jednokolumnowe w torze 62 rem; brak tła i numeracji; waga ~1,3 MB
(ustawienia.png 527 KB ładowane w całości do kafla).

### GALERIA „CO JEST W ŚRODKU" = 3 FAZY (brief pkt 1/1a) — decyzja: NIE sklejam z [ JAK DZIAŁA ]
Kroki odpowiadają na „jak zacząć" (instalacja → baza → bot pracuje, realna sekwencja), fazy na
„co zobaczysz" (strona → brak odpowiedzi → panel) — inna oś; sklejenie łamałoby kolejność kroków.
Każda faza = wiersz o STAŁEJ wysokości kafli (21 rem desktop, 16 rem tablet, auto telefon):
lewa kolumna narracji (mono-numer limonka + hairline + tytuł + 1 zdanie z istniejących treści),
prawa = kafle-okna (pasek 3 kropek, węgiel, hairline, radius 14, cień) — biały zrzut siedzi
w ciemnej ramie, nigdy jako plama.
- `01 · NA TWOJEJ STRONIE`: wideo-demo (poster→fade: `<video poster>` + warstwa plakatu gasnąca na
  `playing`; przy „ogranicz ruch" wideo stoi na plakacie) + okno czatu (ciemne, `contain`).
- `02 · GDY BOT NIE ZNA ODPOWIEDZI`: formularz „Zostaw wiadomość" (`contain`) + karta toku
  „pyta → bot nie zna → prosi o maila → zgłoszenie w wp-admin" (mono, tor dashflow; treść z funkcji
  „Zbieranie kontaktów" i FAQ) — sierotka rozwiązana projektem, nie wypełniaczem.
- `03 · TWÓJ PANEL`: KADRY zamiast całych ekranów — `ustawienia` górny blok 1078×500 (włącznik,
  teksty po polsku) i `statystyki` 1100×520 (KPI) jako osobne pliki WebP; pełny ekran dopiero w
  lightboxie (href = oryginał PNG, ładowany po kliknięciu).
Lightbox: dialog z przyciskiem zamknięcia, podpisem z figcaption, Esc/klik, blokada scrolla,
powrót fokusu, `cursor: zoom-in/out`.

### RESZTA STRONY (język listy)
Sekcje numerowane kolejno `[ 01 · PROBLEM ] … [ 12 · FAQ ]` (licznik w PHP). Pełna szerokość:
CO DOSTAJESZ (5 funkcji jako karty 2-kol + mockup), JAK DZIAŁA (3 karty z torem 01→02→03 jak na
liście), FAZY, W PACZCE (2 kolumny), ILE CZASU (3 karty-dowody), RĘCZNIE KONTRA BOT, DLA KOGO
(3 karty), FAQ (siatka 2 kolumn, bloki z hairline'em), FINAŁ (baner-panel). Tekst zostaje w torze
62 rem: PROBLEM, uczciwość, gwarancja. Hero bez zmian układu (ilustracja brandowa — D28b), dostaje
kierunki: etykieta −24, tytuł −36, akapity −28, wizual +36. Tło blueprint + 14 pingów (strona jest
~3× dłuższa niż lista). Wersja 0.9.10 → 0.9.11.

### KRYTERIA ODBIORU PR 2a
Sędzia ≥ 85 i zero P1 (max 2 rundy, potem oko Dzidka na tunelu) · zrzuty desktop+telefon po WOLNYM
scrollu obejrzane w całości · bramki 8/8 EXIT 0 · waga strony < 1,5 MB (cel < 1 MB) · zero regresji:
checkout, lightbox, sticky, czat-bąbelek, `noscript`/`reduced-motion` = wszystko widoczne.

### WYNIK PR 2a (dok = kod)
- Pomiar PO (Playwright, po wolnym scrollu): waga **1293 → 798 KB**, 0 błędów JS, 0 przepełnienia
  na 390 px; szelki 9/9 (skrypt zablokowany/opóźniony, bez JS, „ogranicz ruch", lightbox, wideo).
- **Sędzia r1 (sonnet): 77/100, BLISKO, zero P1** (motion 68, fazy 75). Runda poprawek na uwagi P2–P3:
  tor kroków nad kartami jako stepper z węzłami (pierwszy pulsuje) · kadry panelu na telefonie
  w proporcji 4:3 od lewej-góry (całość w lightboxie) · nagranie przycięte ffmpeg-iem do OKNA CZATU
  (`chatbot-demo-kadr.webm` 380×560, 229 KB zamiast 418 KB; okno stoi w miejscu przez 19,9 s —
  sprawdzone klatkami 3 s i 16 s) + nowy plakat 4,7 KB · rząd fazy 01 wyższy (26 rem), dwa równe kafle.
- ŚWIADOMIE NIE: ilustracja w pierwszym ekranie telefonu (P3 — hero na telefonie ma tekst+cenę+CTA
  wyżej, jak na liście: panel POD tekstem) · wyrównanie wagi 3 faz do „wszystkie pół-szerokości"
  (P4 — kadry panelu na pół szerokości wracają do nieczytelności, którą Dzidek zgłosił) · oryginał
  `chatbot-demo.webm` zostaje w assets (418 KB, nieużywany na stronie) — do decyzji przy PR 3 media.
- **Sędzia r2: 81/100, BLISKO, zero P1** (hierarchia 85, kolor 87, motion 76, mobile 77). Dzidek na
  tunelu (25.08 ~08:03): pigułka sticky wisiała na końcu strony — MÓJ błąd z tej rundy (reguła
  `.sklepag-produkt > *` nadpisała `position: fixed`, a kaskada zabrała centrowanie) → wykluczenie
  z reguły + własna reguła wejścia; szelki dostały kontrolę pigułki (fixed, 14 px od dołu, środek).
  Podpis duetu na stronie produktu = TEN produkt (`sklepag_duet_maskotek( $podpis )`).
  Dzidek ~08:07 (Segregator): „czemu Kup teraz jest tu i górny też tu przekierowuje?" → kotwice
  celują w sekcję `#sklepag-zakup` (scroll-margin pod nagłówkiem, cena w kadrze), formularz jako
  ramka z błyskiem `:target`. Szare smugi w tle = `fw-canvas` gospodarza, nie nasze.
- Runda 3 (uwagi sędziego r2): poświata za ilustracją hero (0.14, oddech 7 s) · kadry panelu
  przygaszone `brightness(.93)` · kadr panelu 1:1 na telefonie. ŚWIADOMIE NIE: padding pod bąbelek
  czatu (P2 — bąbelek to sam produkt, pływa nad każdą stroną; to nie wada układu) · faza 03 w dwóch
  kolumnach (P3 — nieczytelność, patrz wyżej).
- **Sędzia r3: 74, DALEKO** (niżej niż r2 = 81) — w r2 pigułka sticky była ZEPSUTA (leżała na końcu
  strony) i nic nie zasłaniała; w r3 działa jak ma i sędzia liczy każde nachodzenie na treść jako
  wadę (desktop i telefon). Jego P1 słuszne: kadry panelu 1:1 na telefonie cięły słowa. Runda 4:
  kadr 4:3 + gasnąca prawa krawędź (widać ciąg dalszy) · pigułka zwarta na telefonie. ŚWIADOMIE NIE:
  usunięcie/przesuwanie pigułki (sticky CTA = standard Baymard, koszyk A z #174) · faza 03 w dwóch
  kolumnach (nieczytelność kadrów, patrz r1). Sufit sonneta przy tych decyzjach ≈ 80 — Dzidek: „kręć
  rundy do 90" → po r4 meldunek z tą granicą i wybór Dzidka (pigułka zostaje / znika na telefonie).
- **Sędzia r4: 84, BLISKO, zero P1** (media 90, telefon 88, odstępy 87, hierarchia 87). Runda 5 na
  jego drobiazgi: akcent w H1 (ostatnie słowo WIELKIMI, „LIVE", limonką — `sklepag_tytul_z_akcentem`)
  · fazy numerowane `04.1/04.2/04.3` (przypięte do sekcji, odróżnialne od kroków 01/02/03) · numery
  faz i kroków przygaszone do 72 % limonki (CTA ma wygrywać) · kolumna „Z botem" w porównaniu
  z tłem i lewym akcentem. NIE: ujednolicenie 3 faz do jednego szablonu (decyzja, patrz r1).
- Dzidek na tunelu (~08:27–08:28): (a) faza 03 — 650 px pustki pod tekstem obok dwóch kadrów → faza
  `--panel` ma narrację NAD kadrami (numer | tytuł+opis) i kadry na pełną szerokość; (b) „hero
  i obrazy w hero produktów za słabe" → **hero = PANEL NA ŻYWO tego bota** (sygnatura listy: okno
  z pętlą czatu, awatar-maskotka, tilt/paralaksa, adnotacje, + wiersz „baza pytań" z chipami
  podświetlanymi po kolei); panel wydzielony do `sklepag_panel_nocnej_zmiany( $tryb )` — lista
  woła 'oba' (markup identyczny bajt w bajt, sprawdzone md5), produkt swój slug; ilustracja
  brandowa zostaje na karcie listy (D28b bez zmian dla listy). Sędzia r5 POMINIĘTY (zrzuty
  nieaktualne po zmianach Dzidka) → sędzia r6 na stanie końcowym.
- **Sędzia końcowy: 81, BLISKO, zero P1** (hero 88 — panel na żywo zadziałał; kolor 87; hierarchia 86).
  Cztery werdykty 77 → 81 → 84 → 81: sonnet skacze ±5 zależnie od tego, na co spojrzy, a przy
  świadomych decyzjach (sticky CTA, kadry panelu na pełną szerokość, bąbelek produktu) jego sufit to
  ≈ 85 — pętla ZATRZYMANA (każda runda ≈ 5 $ i 3 % kontekstu bez wzrostu). Z ostatniej listy wzięte:
  oddech pod panelem hero na telefonie, pigułka dosunięta do lewej (prawy róg = bąbelek). Po
  przeglądzie Dzidka (~08:35): kolumna „Z botem" bez podbarwki (brudno-oliwkowa przez mgłę
  gospodarza), ramki 09/11 na pełną szerokość („krzywo"); ~08:40 „animacja pigułki gówniana, nie wiem
  czy jest" → klasa stanu zamiast `hidden`, wjazd z dołu z odbiciem i zjazd z wygaszeniem, zmierzone
  klatkami (opacity 0→1 w ~300 ms, translate 18→0 z przestrzałem −1 px); ~08:52 „nagranie gówniane,
  podobne do zrzutu obok" → para 04.1 = miniatura całej strony z bąbelkiem (świeży zrzut Playwright)
  + nagranie rozmowy z bliska; zrzut okna czatu (dubel) wypadł; strażnik odtwarzania (przycisk
  „Odtwórz", gdy przeglądarka blokuje autoplay), `preload=auto`. Arbiter = oko Dzidka → klepnięcie → commit.

## 5. PLAN MEDIÓW NA PR 2–3 (strony produktów) — do wykonania po pokazie `/sklep/`
Galeria w 3 FAZACH z równymi rzędami · ciemne ramy okien wokół białych zrzutów · kadry +
pełny ekran w lightboxie · poster→fade dla kafli-nagrań · ożywione mikro-ilustracje SVG przy
krokach „jak działa" · wideo segregatora przycięte ffmpeg-iem do obszaru panelu (bez menu
gospodarza i plakietki „Plugins 1") · kompresja galerii pod 1,5 MB (WebP przez ffmpeg).

## 7. GIGANT-PROMPT STRONY PRODUKTU — PR 2b: `/sklep/produkt/segregator-maila/` (25.08, po scaleniu PR 2a)

Ta sama metoda i ten sam system co ChatBot (§6), od razu z lekcjami sześciu rund PR 2a: hero = panel
na żywo, para kafli = dwie RÓŻNE rzeczy, kadr nagrania cięty DO produktu, faza z kadrami = narracja
NAD kadrami, kadry sprawdzone klatkami (nie „na oko"). Treści sprzedażowe BEZ ZMIAN — narracje faz
sklejone z istniejących zdań (funkcje, FAQ, alty). Wersja 0.9.11 → 0.9.12.

### POMIAR PRZED (25.08, `zrzuty-produkt.js segregator-maila`, po wolnym scrollu)
Dawna galeria-siatka: nagranie 1280×800 z menu gospodarza i plakietką „Plugins 1" (517 KB), tasiemiec
próbki 1100×2726 w całości do kafla (513 KB), reguły (192 KB), ustawienia (125 KB) — waga strony
**1538 KB** desktop i telefon, wysokość 8931 / 12654 px, 0 błędów JS, 0 przepełnienia.

### GALERIA = 3 FAZY (`04.1 · TESTUJESZ OD RAZU → 04.2 · TWOJE REGUŁY → 04.3 · RAPORT I KONTROLA`)
- **04.1 `nagranie`** (NOWY układ — decyzja: układ `wideo` z §6 jest skrojony pod PIONOWE okno czatu,
  a tu oba kafle są poziome): dwa RÓWNE kafle bez sztywnej wysokości, proporcja 1100/760. Lewy:
  nagranie przycięte do OBSZARU PANELU (`crop=1100:760:170:40` z 1280×800 — pasek admina y<32 i menu
  x<160 poza kadrem, treść przewija się W kadrze, bo to jest demo), skalowane do 900 px, VP9 crf 36
  → `segregator-demo-kadr.webm` 398 KB (crf 34 w 1100 px dawał 757 KB — więcej niż oryginał) + plakat
  z klatki 0,2 s (33 KB). Prawy: KADR tabeli „Maile w próbce" (`crop=1100:760:0:930` — nagłówek
  sekcji + kolumny + kilkanaście wierszy; góra pliku to formularz importu, nie „30 maili"), dół gaśnie
  gradientem (widać, że lista trwa), całość 2726 px w lightboxie. Dwie różne rzeczy: przebieg + dane.
  Na telefonie (sędzia r1: „litery rzędu kilku pikseli") nagranie też 4:3 od lewej-góry (przycisk
  przebiegu + pierwsze kolumny raportu) z gaśnięciem prawej krawędzi — jak kadry panelu; nagranie
  nie ma lightboxa (to ruch, nie treść do czytania — treść jest w kadrach obok i w pełnych ekranach).
- **04.2 `panel`** (narracja nad kadrami, pełna szerokość — kadry na pół szerokości Dzidek odrzucił
  w §6): reguły `crop=1078:500:0:280` (pole kategorii, przyciski i CAŁA lista 8 reguł z kolumnami
  Pasuje/Zgarnia; koniec przed sekcją „Przeprowadzka"; szerokość 1078 = proporcja okna 1078/500 —
  sędzia r1 złapał, że kadr 1100 px w trybie cover tracił po 11 px z boków i strzałkę „→ kategoria")
  + kategorie w całości (1100×511).
- **04.3 `panel`**: raport `--zywy.jpg` 1200×470 w całości — klasa `kadr => 'raport'` daje kaflowi
  własną proporcję 1200/470 (w oknie 1078/500 tryb cover ucinałby boki z liczbami; telefon = 4:3 jak
  reszta kadrów panelu) + ustawienia „Tryb AI" `crop=1078:500:0:46` (klucz, model, dzienny limit
  z pełnym tekstem pomocy — przy 36 ostatnia linia była cięta).
- Dawna galeria-siatka (`galeria` w danych, gałąź `elseif` w renderze, CSS `.sklepag-galeria*`)
  USUNIĘTA — ostatni produkt, który jej używał, przeszedł na fazy. Oryginały PNG/JPG zostają jako
  cele lightboxa; `segregator-demo.webm` zostaje w assets nieużywany (jak `chatbot-demo.webm`).

### HERO = PANEL NA ŻYWO SEGREGATORA (sygnatura produktu)
`sklepag_panel_nocnej_zmiany( 'segregator-maila' )` + wiersz „Twoje reguły" z chipami
`[ Faktury ] [ Oferty ] [ Reklamacje ]` — chip zapala się W TAKT tagu maila w oknie wyżej (pętla
10 s, opóźnienia 0 / 0,6 / 1,2 s jak `sklepag-pn-tag`): reguła łapie mail, tag wskakuje, chip świeci.
Lista `/sklep/` woła `'oba'` — bez chipów (szelki 10: `sklepag-panel-baza` = 0 na liście).

### DOŁOŻONE Z „CZEGO NIE MA" PR 2a
Pułapka fokusu lightboxa (Tab / Shift+Tab krążą po dialogu) · szelki obu stron produktów W REPO
(`testy/e2e/szelki-strona-produktu.js`, 10 kontroli, slug jako argument) · sonda pigułki z PR 2a.

### KRYTERIA ODBIORU PR 2b
Jak §6: sędzia ≥ 85 i zero P1 (max 2 rundy, potem oko Dzidka na tunelu) · zrzuty desktop+telefon po
WOLNYM scrollu · bramki 8/8 EXIT 0 · waga strony < 1,5 MB (cel < 1 MB; media faz = 640 KB) · szelki
10/10 dla OBU slugów · lista `/sklep/` i ChatBot bez regresji.

### WYNIK PR 2b (dok = kod)
- Pomiar PO (Playwright, po wolnym scrollu, `/sklep/produkt/segregator-maila/`): waga **1538 → 741 KB**
  (desktop i telefon; najcięższe: nagranie-kadr 389 KB, kadr próbki 82 KB, kadr ustawień 50 KB),
  0 błędów JS, 0 przepełnienia na 390 px, wysokość 8931 → 10185 px (desktop), 12654 → 13657 px (telefon).
- Szelki w repo (`testy/e2e/szelki-strona-produktu.js`): **10/10 segregator-maila i 10/10 chatbot-live**
  (skrypt zablokowany/opóźniony, pigułka, kotwica, lightbox + pułapka fokusu, nagranie, chipy hero,
  „ogranicz ruch", bez JS, lista bez chipów).
- **Sędzia r1 (sonnet, 31 zrzutów): 80, BLISKO, zero P1** (kolor 90, typografia 87, hierarchia 86; galeria 72,
  media 74). Wdrożone: kadr reguł 1078 px (cover w oknie 1078/500 tracił po 11 px z boków i strzałkę
  „→ kategoria") · nagranie na telefonie 4:3 od lewej-góry z gaśnięciem (900 px panelu ściśnięte do 390 px
  = litery kilku pikseli). ŚWIADOMIE NIE: ramka wokół „W paczce" (wspólny system z ChatBotem) · ikony przy
  chipach (chipy świecą w takt tagów — ruchu nie widać na zrzucie).
- **Sędzia r2: 83, BLISKO, zero P1** (hierarchia 86, typografia 86, media 84, telefon 82). Wdrożone: adnotacja
  pod panelem hero „Twoje foldery" zamiast „Poranny raport" (dublował etykietę w panelu; skrzynka → foldery
  = to, co produkt robi wg istniejących treści). ŚWIADOMIE NIE: panel na żywo w pierwszym ekranie telefonu
  (P2 — decyzja z §6: tekst + cena + CTA wyżej, panel POD tekstem, jak lista i ChatBot) · akcent wizualny
  między fazami 04.2 i 04.3 (P3 — rytm faz identyczny z ChatBotem; arbitrem oko przeglądającego). Pętla
  sędziego ZATRZYMANA po 2 rundach (80 → 83, sufit ≈ 85 — jak w §6).
