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
- Mono-etykiety zostają BEZ numeracji na liście (spójność ze stronami produktów; numeracja
  wszystkich stron do rozważenia w PR 2).

## 3. GIGANT-PROMPT `/sklep/` (PR 1: gałąź `wyglad/sklep-lista`, wersja 0.9.9)

### KLIMAT
Ciemna, spokojna strona produktu w świecie Automatic AI: **Linear spotyka panel systemowy
gospodarza spotyka bento Supabase**. Zero stocku. Każda powierzchnia lekko żyje: dryf mgławicy,
puls kropki statusu, pętla panelu w hero, elementy wchodzą po kolei.

### HERO (2 kolumny: 1.05fr / 0.95fr, `align-items:center`; < 56 rem: 1 kolumna, panel POD tekstem)
- LEWO: `[ SKLEP ]` · H1 „**Boty**, które robią robotę." — `clamp(2.6rem, 6vw, 4.6rem)`, 600,
  `letter-spacing:-0.03em`, `line-height:1.02` · lead 1.15 rem pył, max 34 rem · pigułka trybu
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
Geist Sans → tytuły 600 (H1 −0.03 em, H2 −0.02 em) i body 400 · Geist Mono → etykiety,
numery, adnotacje, pasek konkretów (0.72–0.8 rem, 0.14–0.18 em).

### MOTION SYSTEM
- Reveal: sekcja jak dziś (26 px, .3 s / .5 s, easing gospodarza) + **stagger dzieci**:
  `transition-delay: calc(var(--sklepag-i) * 70ms)` (nth-child 1–8) — karty, kroki, FAQ.
- Hover: `.35s cubic-bezier(.16,1,.3,1)`. Puls kropki: istniejący 2.2 s. Mgławica: 16 s.
  Panel: ~10 s pętla. Wszystko gaśnie przy `prefers-reduced-motion` (reguła globalna istnieje).
- Fundament: `overflow-x: clip` NA WYSPIE (nie hidden — sticky-pigułka strony produktu).

### STACK
PHP markup w `sklep-agentow.php` (`sklepag_shortcode_sklep`) · CSS w `assets/front.css`
(nowy blok „RUNDA STRON 25.08") · JS: bez zmian poza ewentualnym `--sklepag-i` — zrobione
CSS-em (nth-child). Bump 0.9.8 → 0.9.9 (nagłówek + stała; cache-bust assetów).

### CZEGO NIE POWTÓRZYĆ (lekcje)
Budować z głowy bez pomiaru · pokazywać surowe · `overflow-x:hidden` (zabija sticky) ·
biały zrzut bez ramy okna · zero-JS-fallback, który chowa treść · testować pełnostronicowym
zrzutem (reveal nie odpala — klatki po WOLNYM scrollu).

## 4. KRYTERIA ODBIORU PR 1
Sędzia ≥ 85/100 i zero priority-1 (max 2 rundy sędziego, potem decyzja Gosi) · zrzuty
desktop + telefon obejrzane w całości · `bash narzedzia-qa/bramki.sh` EXIT 0 · waga `/sklep/`
< 0,5 MB · zero regresji (baner testowy = ten sam napis; linki produktów; reveal bez JS).

## 5. PLAN MEDIÓW NA PR 2–3 (strony produktów) — do wykonania po pokazie `/sklep/`
Galeria w 3 FAZACH z równymi rzędami · ciemne ramy okien wokół białych zrzutów · kadry +
pełny ekran w lightboxie · poster→fade dla kafli-nagrań · ożywione mikro-ilustracje SVG przy
krokach „jak działa" · wideo segregatora przycięte ffmpeg-iem do obszaru panelu (bez menu
gospodarza i plakietki „Plugins 1") · kompresja galerii pod 1,5 MB (WebP przez ffmpeg).
