# C13 — Kolejkowanie i wydajność CI

**Audytor:** sesja audytu białych plam, 2026-09-01 · **`commit_head`:** `4ae2a83`
**Zakres z mapy terenu:** `C13 | Kolejkowanie i wydajność CI | ⬜ NIETKNIĘTE (1 wykonawca, zadania szeregowo)`
⛔ **Ograniczenie z briefingu, przestrzegane:** „Tu **tylko pomiar i raport** — zmian w `.github/`
nie proponujesz do wykonania teraz, wisi tam otwarty PR koordynatorki." Niczego w `.github/`
nie zmieniałem; czytałem pliki i pytałem API GitHuba wyłącznie o odczyt.

---

## Wobec czego oceniam (kryteria + skąd) — ZAPISANE PRZED OGLĄDANIEM

| # | Kryterium | Źródło |
|---|---|---|
| **C-K1** | Pytanie plamy: **„Ile trwają przebiegi, co je wydłuża."** | `BRIEFING-AUDYTOR-01-09.md`, tabela kolejki |
| **C-K2** | Znany stan wyjściowy do potwierdzenia albo obalenia: „**1 wykonawca, zadania szeregowo**" | `audyt/MAPA-TERENU-INFRA.md`, wiersz `C13` |
| **C-K3** | Deklaracja własna w kodzie: „Nowy commit w tej samej gałęzi **anuluje poprzedni przebieg** — trzy pchnięcia pod rząd kosztują jedną kontrolę, nie trzy" | `.github/workflows/quality.yml:34-35` (komentarz) |
| **C-K4** | Deklaracja własna: „jedna recenzja to **~2,5 minuty**" | `.github/workflows/recenzent.yml` (komentarz przy `concurrency`) |
| **C-K5** | Klasa 3 ściągi: **dokumentacja/komentarz kłamie względem kodu** — każdą LICZBĘ sprawdź | `audyt/SCIAGA-EGZAMINATORA.md` |

⚠️ **Warunek pomiaru:** briefing mówi, że CI jest w tej chwili zepsute i żaden przebieg nie
zzielenieje. Widać to w danych (przebiegi z pustym wynikiem i czasem `0m00`) — dlatego **liczby
biorę z przebiegów ZAKOŃCZONYCH**, a te świeże, ucięte, wypisuję osobno i nie liczę do średnich.

---

## Co i czym zmierzyłem

Wyłącznie odczyt: `gh run list`, `gh run view --json jobs`,
`gh api repos/{owner}/{repo}/actions/runs/<id>/jobs`, `gh api …/actions/runners`.

### M1 — ilu jest wykonawców

```
{"razem":1,"lista":[{"name":"hetzner-gosia","status":"online","busy":true,
                     "labels":["self-hosted","Linux","X64","hetzner"]}]}
```
**Jeden**, własny, w chwili pomiaru **zajęty**. `C-K2` potwierdzone pomiarem, nie przepisane.

### M2 — czasy 25 ostatnich przebiegów (wycinek, `createdAt` → `updatedAt`)

| przebieg | workflow | gałąź | wynik | czas |
|---|---|---|---|---|
| 33458703872 | Quality | `dependabot/composer/phpsta…` | success | **50m32** |
| 33459144819 | Quality | `fix/rodo-eksporter-i-kasow…` | *(ucięty)* | **44m47** |
| 33458702994 | Quality | `dependabot/github_actions/…` | success | **40m58** |
| 33458698895 | Quality | `dependabot/composer/szepev…` | success | **32m31** |
| 33458698906 | Recenzent | `dependabot/composer/szepev…` | failure | **24m54** |
| 33459144823 | Recenzent | `fix/rodo-eksporter-i-kasow…` | success | **16m44** |
| 33458694972 | Quality | `dependabot/github_actions/…` | success | **11m46** |
| **33458646295** | **Quality** | **`main`** | **success** | **6m08** |
| 33458116791 | Quality | `fix/bezp-przypiecia-i-skan…` | success | **5m49** |
| 33458650715 | dependabot | `main` | success | 0m45 |

⭐ **Rozrzut jest ośmiokrotny — od 6 do 50 minut — i to jest cała treść pytania „co je wydłuża".**

### M3 — rozdzielenie KOLEJKI od WYKONANIA *(`createdAt` → `job.startedAt` → `job.completedAt`)*

| przebieg | KOLEJKA | WYKONANIE | udział kolejki |
|---|---|---|---|
| 33458703872 (`Quality`, job `bramki`) | **41m59** | 8m32 | **83 %** |
| 33458702994 | **33m30** | 7m26 | **82 %** |
| 33459144819 | **44m46** | **2m42** | **94 %** |
| 33458646295 (`main`, bez tłoku) | **0m03** | 6m04 | 1 % |

⛔ **Rozstrzygnięte: przebiegi nie są wolne — one CZEKAJĄ.** Przy jednym wykonawcy zadania
ustawiają się w kolejkę, a „czas przebiegu" widoczny w interfejsie GitHuba to w 82-94 %
stanie w kolejce. Skrajny przypadek: przebieg, który **wykonał się w 2m42**, w interfejsie
pokazuje **44m47**.

### M4 — co wydłuża samo WYKONANIE (kroki przebiegu `main` 33458646295, 6m04 bez kolejki)

| krok | czas |
|---|---|
| Set up job | 0m02 |
| `actions/checkout` | 0m01 |
| Kontrola środowiska PHP | 0m00 |
| Zależności QA | 0m02 |
| **Bramki jakości** | **0m33** |
| ⛔ **Próby kontrolne bramek** | ⛔ **5m20** |
| Post checkout + Complete | 0m01 |

⭐ **88 % wykonania to jeden krok: „Próby kontrolne bramek".** Same bramki — czyli to,
co pilnuje produktu — chodzą **33 sekundy**. Resztę zjada sprawdzanie, czy bramki działają.

### M5 — czy tłok bierze się z braku `concurrency` (najczęstsza przyczyna)

**NIE.** Oba workflow mają go poprawnie:
```
quality.yml:36-38     concurrency: group: ${{ github.workflow }}-${{ github.ref }}
                                   cancel-in-progress: true
recenzent.yml:73-75   (to samo)
```
Wyzwalacze też nie dublują przebiegów: `quality.yml` → `pull_request` **oraz** `push` tylko
na `main`; `recenzent.yml` → wyłącznie `pull_request` (4 typy zdarzeń).
**Ten mechanizm jest zrobiony dobrze i zapisuję to jako wynik.**

### M6 — skąd bierze się tłok

Grupa `concurrency` jest **per gałąź**, więc anuluje tylko własne, starsze przebiegi tej samej
gałęzi — i słusznie. Tłok robią **równoległe gałęzie**: w mierzonym oknie osiem przebiegów
z czterech gałęzi `dependabot/*` weszło w ciągu kilku minut. `.github/dependabot.yml` ma
**dwa ekosystemy** (`github-actions`, `composer`), oba `interval: "weekly"`,
oba `open-pull-requests-limit: 3` → **do sześciu PR-ów naraz × dwa workflow = do dwunastu zadań
na jednego wykonawcę**.

### M7 — weryfikacja liczb z komentarzy (C-K3, C-K4, klasa 3)

| Deklaracja w kodzie | Pomiar | Werdykt |
|---|---|---|
| `quality.yml`: „nowy commit anuluje poprzedni przebieg" | `concurrency` + `cancel-in-progress: true` obecne (M5) | ✅ prawda |
| `recenzent.yml`: „jedna recenzja to **~2,5 minuty**" | zmierzone przebiegi `Recenzent` z wynikiem: **6m04, 6m08, 6m45, 5m37, 16m44, 24m54** | ⛔ **liczba nieaktualna** — patrz `C13-F1` |

---

## Kontrola dodatnia

1. **Czy w ogóle widzę czasy** — ten sam odczyt dał przebieg `main` z kolejką **3 sekundy**
   i wykonaniem 6m04. Gdyby przyrząd zawyżał, zawyżałby wszędzie; tu pokazał jedno małe
   i trzy duże. Rozrzut jest w danych, nie w narzędziu.
2. **Czy `grep` po `concurrency` działa** — trafił w **obu** plikach workflow (próba kontrolna:
   plików workflow jest 2, `on:` trafia w obu). Zero byłoby podejrzane; nie było zera.
3. **Odróżnienie przebiegów uciętych** — przebiegi z pustym `conclusion` i czasem `0m00`/`0m01`
   (zepsute CI, o którym mówi briefing) **wyłączyłem** ze wszystkich wyliczeń, zamiast
   uśredniać je razem z pełnymi.

---

## WYNIK: **ZNALEZIONE** — 1 znalezisko (drobne) + dwa pomiary do decyzji koordynatorki

Odpowiedź na pytanie plamy w trzech zdaniach:
**Przebiegi trwają od 6 do 50 minut.** Wydłuża je **kolejka, nie praca** — przy jednym wykonawcy
82-94 % czasu to stanie w oczekiwaniu, a rekordzista wykonał się w 2m42 i pokazywał 44m47.
W samym wykonaniu **88 % zjada jeden krok — „Próby kontrolne bramek" (5m20)** — podczas gdy
bramki pilnujące produktu chodzą **33 sekundy**.

---

## Znaleziska

### `C13-F1` — **MALE** · Komentarz w `recenzent.yml` podaje czas recenzji „~2,5 minuty", a mierzone przebiegi trwają 5-25 minut

**Objaw:** uzasadnienie mechanizmu `concurrency` opiera się na liczbie, która nie zgadza się
z żadnym zmierzonym przebiegiem.

**Cytat (`.github/workflows/recenzent.yml`, komentarz nad `concurrency`):**
> „Nowy commit w tej samej gałęzi anuluje poprzednią recenzję. Tu oszczędność jest największa
> w całym repo: **jedna recenzja to ~2,5 minuty**, a poprawki lecą seriami"

**Pomiar — WSZYSTKIE zakończone przebiegi `Recenzent` z okna 25 ostatnich, bez wybierania:**
`0m10` · `5m37` · `6m04` · `6m08` · `6m45` · `7m34` · `16m44` · `24m54` — **osiem przebiegów,
nie siedem**. ⚠️ Wypisuję też ten `0m10`, żeby nie dobierać sobie danych: to `failure` po dziesięciu
sekundach, czyli przebieg, który **w ogóle nie zdążył recenzować**. `CLAUDE.md` opisuje taką
sytuację („PR dotykający plików workflow nie dostanie recenzji automatycznej — akcja odmawia
wtedy wymiany tokenu"), ale **przyczyny tego konkretnego przebiegu nie sprawdziłem**, więc nie
liczę go do porównania z deklaracją. Zostaje **siedem** przebiegów, które recenzowały:
najkrótszy `5m37` — **ponad dwa razy dłuższy od deklarowanego**, najdłuższy dziesięć razy.

⚠️ **Uczciwe zastrzeżenie:** czasy z `gh run list` zawierają kolejkę, a `~2,5 minuty` mogło
dotyczyć samego wykonania. **Nie rozdzieliłem kolejki od wykonania dla przebiegów `Recenzent`** —
zrobiłem to tylko dla `Quality` (M3). Dlatego waga jest **drobna**, a nie średnia: to sygnał
„liczba w komentarzu wymaga sprawdzenia", nie dowód, że recenzja spowolniła.

**Skutek DLA KLIENTA:** żaden dla kupującego. Dla nas: liczba w komentarzu jest **uzasadnieniem
decyzji technicznej**, a nasza własna klasa 3 mówi, że każdą liczbę z dokumentacji sprawdza się
w kodzie. Ta nigdy nie była sprawdzona po zmianie.

**RODZAJ SKUTKU:** `zaobserwowany` (siedem czasów kontra jedna deklaracja).
**Waga: DROBNA.**

**Propozycja — ⛔ NIE do wykonania teraz** (zakaz z briefingu, otwarty PR w `.github/`).
Do rozważenia razem z tamtym PR-em: zastąpić liczbę w komentarzu **komendą**, która ją liczy,
albo usunąć liczbę i zostawić samo uzasadnienie mechanizmu — zgodnie z naszą regułą
„w tekstach nie podawaj liczb, które zmienia każda runda".

---

## Dwa pomiary DO DECYZJI koordynatorki (nie zgłaszam ich jako wad)

⛔ Oba dotyczą `.github/`, więc zgodnie z briefingiem **nie proponuję zmian** — podaję liczby.

1. **Jeden wykonawca to sufit przepustowości, nie usterka.** `dependabot.yml` może otworzyć
   **do 6 PR-ów naraz** (2 ekosystemy × `open-pull-requests-limit: 3`, oba `weekly`), a każdy
   PR uruchamia **dwa** workflow → do 12 zadań na jednego wykonawcę. Stąd 45-minutowe kolejki
   w dniu, w którym dependabot się budzi. Dźwignie, których **nie rekomenduję, tylko wymieniam**:
   drugi wykonawca · rozsunięcie harmonogramów obu ekosystemów · grupowanie aktualizacji.
2. **Wewnątrz przebiegu najdroższe jest sprawdzanie NAS, nie produktu**: „Próby kontrolne
   bramek" 5m20 wobec „Bramki jakości" 0m33. ⭐ To nie jest zarzut — próby kontrolne są dokładnie
   tym, czego ten projekt nauczył się najdrożej („bramka, która cicho nie startuje, świeci
   zielono"). Ale **przy jednym wykonawcy ten krok jest głównym składnikiem czasu**, więc gdyby
   kiedykolwiek szukać oszczędności, to jest miejsce, w którym one są — i zarazem miejsce,
   w którym cięcie boli najbardziej. **Decyzja nie moja.**

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego |
|---|---|
| **Rozdzielenie kolejki od wykonania dla `Recenzent`** | zrobiłem to dla `Quality` (4 przebiegi); dla `Recenzent` nie zdążyłem — a to właśnie ta liczba rozstrzyga wagę `C13-F1` |
| **Przebiegi starsze niż 25 ostatnich** | okno pomiaru = `gh run list --limit 25`; nie mam trendu tygodniowego ani średniej z dłuższego okresu |
| **Czas poszczególnych bramek wewnątrz kroku „Bramki jakości"** | krok trwa 33 s w całości; podziału na bramki nie mierzyłem |
| **Dlaczego CI jest teraz zepsute** | zajmuje się tym koordynatorka; przebiegi ucięte wyłączyłem z wyliczeń, przyczyny nie badałem |
| **Zużycie zasobów wykonawcy** (RAM, dysk, równoległość zadań) | pomiar po stronie maszyny, nie API; a maszyna jest wspólna z builderem |
| **Czy `cancel-in-progress` naprawdę anuluje** | odczytałem konfigurację (M5); nie zmierzyłem anulowania na żywym przebiegu — to byłby zapis do CI |

## ⭐ Czego NIE było na mojej liście

1. **Koszt w pieniądzu i w czasie CZŁOWIEKA.** Mierzyłem sekundy maszyny. Nikt nie policzył,
   ile kosztuje 45 minut czekania koordynatorki na werdykt przy PR-ze, który blokuje wydanie.
2. **Kolejka jako przyczyna FAŁSZYWYCH wniosków.** Przebieg czekający 45 minut opisuje stan
   kodu sprzed 45 minut — to dokładnie nasza klasa 10 („opis PR o rundę do tyłu"), tylko od
   strony CI. Nikt nie pytał, czy długa kolejka nie produkuje werdyktów o nieaktualnym stanie.
3. **Co się dzieje, gdy wykonawca padnie.** Jest jeden, `status: online`. Nie ma zapasowego
   i nie sprawdzałem, czy cokolwiek o tym alarmuje.
4. **Kolejność w kolejce.** Przy jednym wykonawcy PR blokujący wydanie czeka tak samo jak
   PR dependabota z aktualizacją narzędzia dev. Priorytetów nie badałem.

---

## Propozycja wiersza do mapy terenu i do rejestru

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja C — podmiana wiersza `C13`:**

```
| C13 | Kolejkowanie i wydajność CI | ✅ | **1.09**, `audyt/plamy/C13-RAPORT.md` (sha `4ae2a83`), wyłącznie odczyt `gh` — zero zmian w `.github/` | **ZMIERZONE.** 1 wykonawca (`hetzner-gosia`, self-hosted) potwierdzony API. Przebiegi 6-50 min, ale to **kolejka, nie praca**: udział czekania 82-94 % (rekord: wykonanie 2m42, w interfejsie 44m47); bez tłoku `main` = kolejka 3 s + wykonanie 6m04. Wewnątrz wykonania **88 % to krok „Próby kontrolne bramek" (5m20)**, same bramki 0m33. `concurrency: cancel-in-progress` jest w OBU workflow i wyzwalacze nie dublują przebiegów — ten mechanizm zrobiony dobrze. Tłok robią równoległe gałęzie: `dependabot.yml` może otworzyć 6 PR-ów naraz × 2 workflow = 12 zadań na 1 wykonawcę. Znalezisko `C13-F1` (MALE): komentarz w `recenzent.yml` mówi „~2,5 minuty", zmierzone 5m37-24m54 (plus jeden `0m10`, ktory nie zdazyl recenzowac) |
```

**Do rejestru znalezisk:**

```
| `C13-F1` | **MALE** · komentarz przy `concurrency` w `.github/workflows/recenzent.yml` uzasadnia mechanizm liczbą „jedna recenzja to ~2,5 minuty”, a wszystkie zmierzone zakończone przebiegi `Recenzent` trwały 5m37-24m54 (klasa 3: liczba w dokumentacji niesprawdzona po zmianie) | `gh run list --limit 25` — siedem czasów kontra jedna deklaracja; ⚠️ czasy zawierają kolejkę, a rozdzielenia kolejka/wykonanie dla `Recenzent` NIE zrobiłem — stąd waga drobna. ⛔ Naprawy nie proponuję do wykonania: zakaz zmian w `.github/` (otwarty PR koordynatorki) — BRAK strażnika | OTWARTE |
```
