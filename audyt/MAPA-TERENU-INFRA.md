# 🗺️ MAPA TERENU — co JEST sprawdzone, a czego NIKT nie oglądał

> **Po co ten plik istnieje.** `audyt/REJESTR-ZNALEZISK-INFRA.md` odpowiada na pytanie
> „co znaleźliśmy i czy naprawione”. **Nie odpowiada na pytanie „gdzie w ogóle szukaliśmy”.**
> A bez tego drugiego „nie znaleźliśmy tu nic” i „nie zaglądaliśmy tu ani razu” wyglądają
> identycznie — i tak samo się czyta w raporcie.
>
> Powstał 31.08 z pytania właściciela: *mamy listę błędów zweryfikowanych, ale nie ma chyba
> checklisty terenu*. Miał rację: istniała `audyt/POLOWANIE-INFRA-TABELA.md` (macierz
> plik × pytanie, 41 skryptów), ale obejmowała **wyłącznie pliki w repo**. Cała warstwa
> spoza plików — ustawienia GitHuba, wydania, artefakty, uprawnienia, dostępy ludzi —
> nie miała żadnego spisu.
>
> ⛔ **ZASADA TEGO PLIKU: status wpisujemy TYLKO z dowodu.** Nie „chyba sprawdzone”.
> Jeśli nie wiadomo, kto i kiedy patrzył — wpis brzmi NIETKNIĘTE. Pusty wiersz jest
> uczciwy, wiersz odhaczony na wyczucie jest gorszy niż jego brak, bo zamyka temat.

## Oznaczenia

`✅` zmierzone z dowodem · `🟡` częściowo (napisane obok, co zostało) ·
`⬜` **NIETKNIĘTE — nikt tam nie zaglądał** · `🔒` poza naszym zasięgiem (z powodem)

---

## A. KOD PRODUKTU (`wtyczki/`)

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| A1 | `sklep-agentow` — logika PHP | 🟡 | audyt K7 (sierpień, nasza siatka) + recenzja kolegi 31.08 (JEGO siatka) | kolega: **9/10**; nasze znaleziska w `REJESTR-ZNALEZISK.md` |
| A2 | `chatbot-live` | 🟡 | wroga sesja K2, przeloty botów | osobny rejestr |
| A3 | `segregator-maila` | 🟡 | dowód zachowania + próba kontrolna | — |
| A4 | Front (JS/CSS wtyczek) | ⬜ | — | **nikt nie patrzył naszą siatką** |
| A5 | `uninstall.php` — symetria zapis/sprzątanie | 🟡 | punkt szablonu PR, sprawdzany per-PR | brak przeglądu CAŁOŚCI naraz |
| A6 | Schemat bazy i migracje | ⬜ | — | **NIETKNIĘTE** |
| A7 | Kod produktu NASZĄ siatką po recenzji kolegi | ⬜ | — | kolega dał 9/10, ale to JEGO siatka — nasza nie przeszła po jego uwagach |

## B. NARZĘDZIA I BRAMKI (`narzedzia-qa/`, `.githooks/`)

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| B1 | 41 skryptów — 6 pytań polowania | ✅ | `POLOWANIE-INFRA-TABELA.md`, 31.08 | 20 znalezisk |
| B2 | Próby kontrolne — czy w ogóle przechodzą | ✅ | przelot 31.08 | `I-12`, `I-13` martwe/kłamiące |
| B3 | Próby kontrolne — czy mają przypadek „spoza repo” | ✅ | 31.08 | **2 z 15** → `I-14`, obstawione strażnikiem |
| B4 | Manifest bramek — spójność z rejestrem | ✅ | `sprawdz-manifest.sh` + próba | `B-2` podłoga |
| B5 | Hooki repo `.githooks/` | 🟡 | 31.08 przy naprawie hooków maszyny | `I-9` dwa `pre-push` — OTWARTE |
| B6 | Czy bramka mierzy to, co deklaruje (kalibracja) | 🟡 | część ma próby kontrolne | `I-3`, `I-4`, `I-8` sieroty — OTWARTE |
| B7 | Binarki pobierane przez bramki | ✅ | 31.08 | `B-5` brak sum — naprawione |
| B8 | Skan sekretów: nośniki (archiwa) | ✅ | próba A/B 31.08 | `B-10` ślepota na .zip — naprawione |

## C. CI I USTAWIENIA GITHUBA

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| C1 | `quality.yml` — kroki i progi | ✅ | 31.08 | `I-6` próby nie chodziły w CI |
| C2 | `recenzent.yml` — allowlista narzędzi | ✅ | 31.08 | `B-1` `find`, `B-4` gołe `WebFetch` |
| C3 | `permissions:` w workflow | ✅ | **31.08 wieczór** | `B-12` nadmiarowe `id-token: write` |
| C4 | Akcje zewnętrzne — przypięcie do sum | ✅ | 31.08 | `B-8` — naprawione + wymóg w repo |
| C5 | Artefakty przebiegów — co niosą | ✅ | **31.08 wieczór** | token NIE wycieka (`apiKeySource=none`), retencja 7 dni działa; ujawniają ścieżki maszyny — drobne |
| C6 | Sekrety repozytorium | ✅ | **31.08 wieczór** | 1 sekret (`CLAUDE_CODE_OAUTH_TOKEN`), 0 zmiennych — minimum, czysto |
| C7 | Klucze wdrożeniowe i webhooki | ✅ | **31.08 wieczór** | 0 i 0 — czysto |
| C8 | Ustawienia Akcji (forking, sha_pinning, alerty) | ✅ | 31.08 | `B-9` forking 🔒, `B-11` alerty były wyłączone |
| C9 | **Dostępy ludzi do repo klienta** | ✅ | **31.08 wieczór** | ⛔ `B-13`: recenzent zewnętrzny ma `push` |
| C10 | Wykonawca CI (runner) | ✅ | 31.08 | `B-3` chodził jako root — naprawione (`ciuser`) |
| C11 | Ochrona gałęzi | 🔒 | — | niedostępna na darmowym planie; kompensacja hookiem |
| C12 | `CODEOWNERS` | ⬜ | — | **pliku NIE MA** — nikt nie decydował, czy ma być |
| C13 | Kolejkowanie i wydajność CI | ⬜ | — | **NIETKNIĘTE** (1 wykonawca, zadania szeregowo) |

## D. WYDANIA, TAGI, PACZKI

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| D1 | Wydania — oznaczenia i `latest` | ✅ | **31.08 wieczór** | 6 wydań, żadne nie draft/prerelease, `latest`=0.9.28 zgodne z kodem |
| D2 | Wydania — czy załączniki podmieniane | ✅ | **31.08 wieczór** | czysto: czas dodania każdego załącznika = czas wydania; paczka bota 1.5.13 istniała PRZED najstarszym wydaniem |
| D3 | Zawartość paczek ZIP — sekrety | ✅ | 31.08 | 29 archiwów, 1313 plików, 43,84 MB → **0 sekretów** |
| D4 | Zawartość paczek ZIP — ślady wewnętrzne | 🟡 | bramka `slady` per-PR | brak przeglądu wszystkich 29 naraz |
| D5 | CHANGELOG — kompletność wobec commitów | ⬜ | — | **NIETKNIĘTE** |
| D6 | Zgodność wersji: kod ↔ tag ↔ dokument | ✅ | 31.08 | `D-1` SECURITY.md — naprawione + strażnik |
| D7 | Sumy kontrolne paczek dla klienta | ⬜ | — | **NIETKNIĘTE**: czy klient może zweryfikować, co pobrał |

## E. DOKUMENTY DLA CZŁOWIEKA

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| E1 | `SECURITY.md` | ✅ | 31.08 | `D-1` mówił o wydaniu sprzed trzech releasów |
| E2 | `CONTRIBUTING.md` | 🟡 | 31.08 | `D-2` obiecuje bramkę, której nie ma — OTWARTE |
| E3 | `README.md` | 🟡 | rundy #234/#237 | odznaki i licencja poprawione |
| E4 | `LICENSE` — zgodność z zależnościami | ⬜ | — | **NIETKNIĘTE** |
| E5 | `DECYZJE.md` — czy aktualne | ⬜ | — | **NIETKNIĘTE** |
| E6 | Instrukcja dla klienta w paczce | 🟡 | `pakuj-dla-klienta.sh` sprawdza obecność | treść nieoceniana |
| E7 | Teksty widziane przez klienta (panel, maile) | 🟡 | audyt sklepu | osobny rejestr |

## F. DANE I PRAWO

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| F1 | RODO — dane osobowe w bazie i logach | 🟡 | strażnik RODO (jednostronny, klasa 72) | dług |
| F2 | Prawo konsumenckie — zgoda art. 16(m) | 🟡 | zapisane w pamięci warsztatu | czy w mailu — sprawdzone w audycie sklepu |
| F3 | Polityka prywatności i regulamin | ⬜ | — | **NIETKNIĘTE** |
| F4 | Retencja danych kupujących | ⬜ | — | **NIETKNIĘTE** |

## G. REPOZYTORIUM I HISTORIA

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| G1 | Historia gita — sekrety | ✅ | gitleaks + `bramka-poswiadczenia` per-PR | 347 commitów, 0 trafień |
| G2 | Co jest ŚLEDZONE (vendor, node_modules) | ✅ | 31.08 | 0 i 0; ale 29 ZIP-ów → `B-10` |
| G3 | `.gitignore` — czy nie przepuszcza | ⬜ | — | **NIETKNIĘTE** (16 reguł, nikt ich nie przeglądał) |
| G4 | Rozmiar repo i `.git` | 🟡 | 31.08 | repo 24 MB, `.git` 324 MB — nikt nie badał czemu |
| G5 | Gałęzie — sieroty, niescalone | 🟡 | mózg: 9 lokalnych sierot | czeka na decyzję właściciela |

## H. STOS I ŚRODOWISKO

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| H1 | Stos WordPress (docker) | 🟡 | testy produktu | 12 skryptów wymaga stosu — poza CI |
| H2 | Stripe — konfiguracja i webhooki | 🟡 | audyt sklepu | `stripe listen` żywy na maszynie |
| H3 | Poczta (mailpit) | 🟡 | audyt maili | — |
| H4 | Sama maszyna warsztatu (poza repo) | 🟡 | 31.08: runner, hooki, dysk | narzędzia warsztatu tą samą siatką — ⬜ |

---

## ⛔ CO Z TEJ MAPY WYNIKA — trzy zdania

**Po pierwsze:** teren spoza plików był do 31.08 praktycznie nietknięty, a to właśnie tam
siedziało `B-3` (runner jako root), `B-8` (ruchome tagi Akcji), `B-11` (wyłączone alerty),
`B-12` (nadmiarowe uprawnienie) i `B-13` (dostęp zewnętrzny z prawem zapisu). Kolega
powiedział „większość to błędy infrastruktury” — i mapa pokazuje, dlaczego: infrastruktura
to głównie rzeczy, których NIE WIDAĆ w plikach repozytorium.

**Po drugie:** pozycji `⬜ NIETKNIĘTE` jest **dwanaście**. To nie znaczy, że tam są błędy —
znaczy, że nie mamy prawa powiedzieć, że ich nie ma.

**Po trzecie:** najgęstsze białe plamy to **dokumenty prawne** (F3, F4), **front wtyczek**
(A4), **schemat bazy** (A6) i **weryfikowalność paczki przez klienta** (D7).
