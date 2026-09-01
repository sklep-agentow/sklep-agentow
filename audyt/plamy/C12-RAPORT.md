# C12 — `CODEOWNERS`

**Audytor:** sesja audytu białych plam, 2026-09-01 · **`commit_head`:** `235ec6a`
**Zakres z mapy terenu:** `C12 | CODEOWNERS | ⬜ — pliku NIE MA — nikt nie decydował, czy ma być`
**Pytanie plamy:** „Czy jest, czy działa."

---

## Wobec czego oceniam (kryteria + skąd) — ZAPISANE PRZED OGLĄDANIEM

Kryterium **ze źródła producenta**, nie z pamięci — dokumentacja GitHuba, strona „About code owners"
(`https://docs.github.com/en/articles/about-code-owners`, odczyt 1.09.2026):

| # | Kryterium (cytat dosłowny, po angielsku jak w źródle) |
|---|---|
| **G1** | „To use a CODEOWNERS file, create a new file called `CODEOWNERS` in the **`.github/`, root, or `docs/`** directory" — czyli **trzy** dozwolone lokalizacje |
| **G2** | „Repository owners can **update branch protection rules** to ensure that changed code is reviewed by the owners of the changed files" oraz „As an alternative to branch protection rules, you can create **rulesets**" — czyli **wymuszenie wymaga ochrony gałęzi albo ruleset** |

⚠️ Czego na tej stronie **nie ma**: zdania o planach i typach repozytoriów. Nie orzekam więc,
czy sama funkcja jest dostępna na prywatnym repo na koncie darmowym — i **nie muszę**, bo
o werdykcie rozstrzyga G2, zmierzone niżej.

Kryteria projektu: `CLAUDE.md` — „⛔ **Nikt nie scala własnego PR-a.** Scala koordynator,
po przeczytaniu recenzji" · `audyt/MAPA-TERENU-INFRA.md` wiersz `C11`: ochrona gałęzi
„niedostępna na darmowym planie; **kompensacja hookiem**".

---

## Co i czym zmierzyłem

| # | Pomiar | Wynik |
|---|---|---|
| M1 | plik w **każdej** z trzech lokalizacji dozwolonych przez G1 | `CODEOWNERS` → BRAK · `.github/CODEOWNERS` → BRAK · `docs/CODEOWNERS` → BRAK |
| M2 | czy kiedykolwiek istniał: `git log --all --diff-filter=A -- '*CODEOWNERS'` | **0 commitów** dodających go w całej historii, na wszystkich gałęziach |
| M3 | **próba kontrolna** — czy w ogóle widzę `.github/` | 5 pozycji: `dependabot.yml`, `manifest-bramek.txt`, `PULL_REQUEST_TEMPLATE.md`, `RECENZENT-BRIEFING.md`, `workflows` |
| M4 | typ repozytorium: `gh repo view --json isPrivate,visibility,owner` | `{"isPrivate":true,"visibility":"PRIVATE","owner":"szydlowskikonrad95-art"}` — repo **prywatne** na koncie **osobistym** |
| M5 | plan konta: `gh api user --jq .plan.name` | `null` — API nie zwraca planu dla tego tokenu |
| M6 | **czy da się włączyć wymuszenie (G2)**: `gh api repos/{owner}/{repo}/branches/main/protection` | **HTTP 403** · `"Upgrade to GitHub Pro or make this repository public to enable this feature."` |

---

## Kontrola dodatnia

Zero z M1 i M2 nie jest ślepotą narzędzia: **ten sam sposób sprawdzania** pokazał pięć pozycji
w `.github/` (M3), a `git log --diff-filter=A` po innym wzorcu w tym repo zwraca commity
(używałem go przy `A4b` dla `assets/platnosc.js` → 1 commit). Sprawdziłem **wszystkie trzy**
lokalizacje z G1, nie jedną — bo „nie ma w `.github/`" i „nie ma nigdzie" to dwa różne zdania.

---

## WYNIK: **CZYSTO co do faktu, ZNALEZISKO co do stanu** — 1 znalezisko (drobne)

**Odpowiedź na pytanie plamy, w dwóch zdaniach:**
**Nie ma go — w żadnej z trzech lokalizacji, i nigdy nie było.**
**I gdyby był, dziś by nie działał w części, która ma znaczenie** — bo wymuszenie przeglądu
przez właścicieli kodu wymaga ochrony gałęzi albo ruleset (G2), a to repozytorium odpowiada
na próbę odczytu ochrony gałęzi **HTTP 403 z żądaniem podniesienia planu** (M6).

---

## Znaleziska

### `C12-F1` — **MALE** · Brak `CODEOWNERS` nie jest zapisany jako decyzja, więc wygląda jak przeoczenie

**Objaw:** projekt ma twardą regułę „nikt nie scala własnego PR-a" (`CLAUDE.md`), ma szablon PR,
ma brief recenzenta i ma świadomie opisaną kompensację braku ochrony gałęzi (`C11` w mapie:
„kompensacja hookiem"). Nie ma natomiast **ani pliku `CODEOWNERS`, ani wiersza w `DECYZJE.md`**,
który by mówił, że świadomie z niego rezygnujemy i dlaczego.

**Dowód:** M1, M2 (zero, z próbą kontrolną M3) oraz `DECYZJE.md` — w **36 wierszach** rejestru
(D1–D37 z lukami) rdzeń `codeowners` ma **0 trafień** — tak samo `PLAN-PROJEKTU.md` i `CLAUDE.md`.
Próba kontrolna w tym samym pliku: rdzeń `hook` → **6 trafień**, a decyzja-bliźniak o blokadzie
`main` hookiem **jest** zapisana jako `D9`
(„Repo prywatne na koncie klienckim; blokada main = lokalny hook (GitHub free nie da protection
na prywatnym)").

**Skutek DLA KLIENTA:** żaden bezpośredni — to nie jest wada produktu, tylko dziura w opisie
procesu. Ale dla oceniającego wygląda tak samo jak przeoczenie: przy `C11` napisaliśmy, czemu
się nie da i czym to nadrabiamy, przy `C12` nie napisaliśmy nic. **To jest dokładnie ta różnica,
którą mapa terenu miała wyłapywać: „nie znaleźliśmy tu nic" kontra „nie zaglądaliśmy tu ani razu".**

**RODZAJ SKUTKU:** `zaobserwowany` (brak pliku + brak wiersza w rejestrze, oba z próbą kontrolną).
**Waga: DROBNA.**

**Propozycja naprawy — ⛔ nie „dodać CODEOWNERS".** Sam plik bez ochrony gałęzi **nie wymusza
niczego** (G2 + M6), więc dołożenie go dałoby dokładnie to, przed czym ostrzega nasza klasa 75:
narzędzie-sierotę z twardą deklaracją, którą ktoś weźmie za obowiązującą. Właściwa naprawa to
**jeden wiersz w `DECYZJE.md`** w brzmieniu zgodnym z faktem: *„CODEOWNERS nie zakładamy —
bez ochrony gałęzi (D9, `C11`) nie miałby czym wymuszać przeglądu; regułę »nikt nie scala
własnego PR-a« trzyma hook i koordynatorka. Warunek powrotu: włączona ochrona gałęzi
albo ruleset."* Gdyby kiedyś doszła ochrona gałęzi — wtedy plik ma sens i wchodzi **razem** z nią,
w tej kolejności (klasa 85 ściągi: wymóg włączony przed kodem wywraca gałąź główną).

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego |
|---|---|
| **Czy CODEOWNERS w ogóle działa na prywatnym repo konta darmowego** (auto-przypisanie recenzenta, bez wymuszania) | dokumentacja na czytanej stronie o tym milczy, a `gh api user` zwraca `plan: null`, więc nie mam pomiaru. **Dla werdyktu bez znaczenia** — część wymuszająca jest niedostępna niezależnie od tego |
| **Rulesets** jako druga droga z G2 | nie sprawdzałem osobno; przy 403 na ochronie gałęzi na koncie darmowym spodziewam się tego samego, ale **tego nie zmierzyłem** |
| **Czy hook kompensujący z `D9` naprawdę blokuje** | to teren `B5` mapy (`I-9`: dwa `pre-push` — OTWARTE), nie mój |
| **`.github/` na innych gałęziach** | `git log --all` obejmuje wszystkie gałęzie i dał zero, więc uznaję to za zamknięte |

## ⭐ Czego NIE było na mojej liście

1. **Kto faktycznie scala.** Pytałem o plik i o możliwość wymuszenia — nie o to, czy reguła
   „nikt nie scala własnego PR-a" jest w praktyce dotrzymywana. To da się zmierzyć
   (`gh pr list --state merged --json author,mergedBy`) i nikt tego nie zrobił.
2. **`PULL_REQUEST_TEMPLATE.md` jako kompensacja.** Istnieje i nikt nie sprawdził, czy jego punkty
   są odhaczane rzetelnie — a mamy zapisaną klasę „odhaczenie punktu bez wykonania go jest gorsze
   niż brak checklisty".
3. **Uprawnienia ludzi do repo** — sąsiedni teren `C9`, gdzie leży otwarte `B-13` (recenzent
   zewnętrzny z prawem zapisu). `CODEOWNERS` bez ochrony gałęzi nie zmienia w tym nic.

---

## Propozycja wiersza do mapy terenu i do rejestru

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja C — podmiana wiersza `C12`:**

```
| C12 | `CODEOWNERS` | ✅ | **1.09**, `audyt/plamy/C12-RAPORT.md` (sha `235ec6a`), kryterium z docs.github.com | **NIE MA GO w żadnej z 3 lokalizacji dozwolonych przez GitHuba** (`.github/`, root, `docs/`) i nigdy nie było (`git log --all --diff-filter=A` → 0; próba kontrolna: `.github/` ma 5 pozycji). **I nie działałby**: wymuszenie przeglądu wymaga ochrony gałęzi albo ruleset, a `gh api …/branches/main/protection` → **HTTP 403 „Upgrade to GitHub Pro or make this repository public"**. Znalezisko `C12-F1` (MALE): brak nie jest zapisany jako decyzja — przy `C11` napisaliśmy, czemu się nie da i czym nadrabiamy, przy `C12` nic. ⛔ Naprawa to wiersz w `DECYZJE.md`, NIE dołożenie pliku (byłby sierotą bez mocy — klasa 75) |
```

**Do rejestru znalezisk:**

```
| `C12-F1` | **MALE** · brak `CODEOWNERS` w 3 dozwolonych lokalizacjach i brak wiersza o nim w `DECYZJE.md`, przy istniejącej regule „nikt nie scala własnego PR-a” (`CLAUDE.md`) i przy zapisanej decyzji-bliźniaku `D9` o kompensacji ochrony gałęzi hookiem — brak wygląda jak przeoczenie, nie jak decyzja | `git log --all --diff-filter=A -- '*CODEOWNERS'` → 0 (próba kontrolna: `.github/` = 5 pozycji, `D9` w rejestrze = 1 trafienie); `gh api …/branches/main/protection` → 403 — BRAK strażnika | OTWARTE |
```

---
⭐ **Lista zadań dla następnej sesji** — to, czego przy tej plamie NIE zdążyłem sprawdzić, jest zebrane razem z pozostałymi w `audyt/plamy/PRZEKAZANIE-NASTEPNEJ-SESJI.md`. Sekcja „Czego NIE sprawdziłem" wyżej jest źródłem tamtej listy, nie jej zamiennikiem.
