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
| A4 | Front — ŁADOWANIE zasobów | ✅ | **1.09** | **CZYSTO**: 5 plików (128 KB), wszystkie przez `wp_enqueue_*` z wersją z `sklepag_wersja_assetu` (bez tego klient po aktualizacji widziałby stare z cache). Style frontu ładowane WARUNKOWO — tylko strona sklepu i „dziękujemy", nie cała witryna. Stripe.js z `js.stripe.com/dahlia/stripe.js`: HTTP 200, 1,08 MB, kontrola dodatnia — zmyślona wersja daje 403 |
| A4b | Front — WYGLĄD i DZIAŁANIE w przeglądarce | 🟡 | **1.09**, `audyt/plamy/A4b-RAPORT.md`, chromium 1440×900 i 390×844, ścieżka zakupu przeklikana + sonda JS | ⛔ **4 znaleziska.** KRYT `A4b-F1`: „Zapłać" w płatności osadzonej rzuca `TypeError` i nie potwierdza płatności — sklep z kluczem publikowalnym NIE SPRZEDAJE (kontrola dodatnia dowozi zamówienie do `mail_wyslany`). Dalej `A4b-F2` (DUZE, test e2e nie widzi tego błędu — rozpoznaje captchę po samej obecności ramki), `A4b-F3`, `A4b-F4` (oba MALE). 🟡 nie sprawdzone: BLIK, 3-D Secure, Firefox/Edge, tablet, axe na „Dziękujemy" i na panelu płatności |
| A5 | `uninstall.php` — symetria zapis/sprzątanie | 🟡 | punkt szablonu PR, sprawdzany per-PR | brak przeglądu CAŁOŚCI naraz |
| A6 | Schemat bazy i migracje | 🟡 | **1.09**, `audyt/plamy/A6-RAPORT.md`, porównanie definicji `dbDelta` z żywą bazą (kolumny/indeksy/wersja) | Większość CZYSTO: 7 tabel `dbDelta`=`uninstall.php`, kolumny/indeksy kod↔baza bez rozjazdu, wersja schematu zgodna, `esc_like()` wszędzie, transienty i cron sprzątane obiema drogami. ⛔ Znalezisko `A6-F1` (SRED, OTWARTE): `dbDelta` wołany bez sprawdzenia wyniku, wersja podnoszona BEZWARUNKOWO w obu ścieżkach — nieudana migracja (np. brak prawa `ALTER` na hostingu) nigdy się nie powtórzy. 🟡 nie sprawdzone: typy i długości kolumn (porównane tylko nazwy), faktyczna deinstalacja na żywo, kolumny historycznie usunięte |
| A7 | Kod produktu NASZĄ siatką po recenzji kolegi | 🟡 | **1.09**, trzy przeloty na gałęzi `audyt/a7-kod-produktu`: `A7-RAPORT.md` (statyka+phpstan), `A7b-RAPORT.md` (sonda klienta IMAP na atrapie), `A7c-RAPORT.md` (sonda dekodowania MIME na atrapie) | ⛔ **3 poważne znaleziska + 3 drobne.** KRYT `A7-F1`: instrukcja instalacji każe zaznaczyć 2 z 11 zdarzeń webhooka Stripe — zwroty i obciążenia zwrotne NIGDY nie docierają do sklepu, Pulpit liczy je dalej jako przychód. SRED `A7b-F1`: klient IMAP bez żadnego sufitu pamięci — jedna spreparowana linia serwera zabija proces PHP (zmierzone na atrapie, kontrola dodatnia zdana). DUZE `A7c-F1`: dekodowanie MIME gubi treść maila na 4 niezależnych drogach — reguły segregatora „treść zawiera" nie widzą treści zwykłego maila wieloczęściowego (czyli KAŻDEGO z Gmaila/Outlooka). Plus `A7-F2`, `A7-F3` (MALE), `A7b-F2` (MALE). 🟡 nie sprawdzone: STARTTLS klienta IMAP (na wyraźne polecenie odłożone), wydajność przy większych danych (zero pomiarów w całym etapie), zachowanie w przeglądarce, import CSV do segregatora, kilka całych plików nietkniętych żadną soczewką (`statystyki.php`, `import.php`, `przejecie.php`, `admin.php`, `filtry.php`, `assets/`) |

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
| C12 | `CODEOWNERS` | ✅ | **1.09**, `audyt/plamy/C12-RAPORT.md`, kryterium z docs.github.com + `gh api` | **CZYSTO co do faktu, znalezisko co do stanu.** Pliku NIE MA w żadnej z 3 dozwolonych lokalizacji i nigdy nie było (`git log --all --diff-filter=A` → 0, próba kontrolna `.github/` = 5 pozycji). I tak by nie działał: wymuszenie przeglądu wymaga ochrony gałęzi/ruleset, a to HTTP 403 na darmowym planie. Znalezisko `C12-F1` (MALE, OTWARTE): brak nie jest zapisany jako świadoma decyzja w `DECYZJE.md`, mimo że sąsiedni brak (`C11`) jest tak zapisany |
| C13 | Kolejkowanie i wydajność CI | ✅ | **1.09**, `audyt/plamy/C13-RAPORT.md`, wyłącznie odczyt przez `gh` — zero zmian w `.github/` | **ZMIERZONE.** 1 wykonawca self-hosted potwierdzony API. Udział czekania w kolejce 82-94% czasu przebiegu; bez tłoku 3 s kolejki + 6m04 wykonania. Wewnątrz wykonania 88% to krok „Próby kontrolne bramek" (same bramki 0m33). `concurrency: cancel-in-progress` działa poprawnie, bez dublowania zadań. Znalezisko `C13-F1` (MALE, OTWARTE): komentarz w `recenzent.yml` obiecuje „~2,5 minuty", zmierzone realnie 5m37-24m54 |

## D. WYDANIA, TAGI, PACZKI

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| D1 | Wydania — oznaczenia i `latest` | ✅ | **31.08 wieczór** | 6 wydań, żadne nie draft/prerelease, `latest`=0.9.28 zgodne z kodem |
| D2 | Wydania — czy załączniki podmieniane | ✅ | **31.08 wieczór** | czysto: czas dodania każdego załącznika = czas wydania; paczka bota 1.5.13 istniała PRZED najstarszym wydaniem |
| D3 | Zawartość paczek ZIP — sekrety | ✅ | 31.08 | 29 archiwów, 1313 plików, 43,84 MB → **0 sekretów** |
| D4 | Zawartość paczek ZIP — ślady wewnętrzne | 🟡 | bramka `slady` per-PR | brak przeglądu wszystkich 29 naraz |
| D5 | CHANGELOG — kompletność wobec commitów | 🟡 | **1.09** | każda z 3 wtyczek ma `CHANGELOG.md`, `readme.txt` niesie `Stable tag: 0.9.28` i wpisy dla 0.9.26-0.9.28 — **nadąża za wydaniami**. Nie porównano wpis-po-wpisie z historią commitów |
| D6 | Zgodność wersji: kod ↔ tag ↔ dokument | ✅ | 31.08 | `D-1` SECURITY.md — naprawione + strażnik |
| D7 | Sumy kontrolne paczek dla klienta | ✅ | **1.09** | **CZYSTO** — paczka zbiorcza wiezie `SHA256SUMS.txt`, a treść wydania podaje sumy SHA-256. Klient MOŻE zweryfikować, co pobrał |
| D8 | Licencje: nagłówki wtyczek i zgodność z GPL | ✅ | **1.09**, wg skilla `wp-plugin-directory-guidelines` | **CZYSTO**: wszystkie 3 wtyczki mają `License` **i** `License URI` w PHP oraz w `readme.txt`, każda paczka wiezie pełny tekst `LICENSE`. Drobiazg: `composer.json` deklaruje `proprietary` (dotyczy narzędzi dev, NIE trafia do paczek — mylące, nie naruszenie); zapis `GPL-2.0-or-later` w PHP vs `GPLv2 or later` w readme — obie formy dozwolone |

## E. DOKUMENTY DLA CZŁOWIEKA

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| E1 | `SECURITY.md` | ✅ | 31.08 | `D-1` mówił o wydaniu sprzed trzech releasów |
| E2 | `CONTRIBUTING.md` | 🟡 | 31.08 | `D-2` obiecuje bramkę, której nie ma — OTWARTE |
| E3 | `README.md` | 🟡 | rundy #234/#237 | odznaki i licencja poprawione |
| E4 | `LICENSE` — zgodność z zależnościami | ✅ | **1.09** | patrz `D8`. Zależności to wyłącznie narzędzia dev (phpcs, phpstan, wpcs) — nie są dystrybuowane z wtyczką, więc nie mieszają się z GPL produktu |
| E5 | `DECYZJE.md` — czy aktualne | 🟡 | **1.09**, `audyt/plamy/E5-RAPORT.md`, przegląd wpisów wobec historii i mechanizmu unieważnień, który plik sam ma | ⛔ **2 znaleziska.** `E5-F1` (SRED, OTWARTE): `D1`, `D3`, `D4`, `D13` opisują architekturę sprzed piwotu (Cloudflare Worker) BEZ znacznika nieaktualności, choć plik mechanizm ma i go używa gdzie indziej; `D3` jest wręcz ODWRÓCONY — odrzucił wspólny origin z gospodarzem, a dziś panel tam stoi, bez zapisanego uzasadnienia zmiany. `E5-F2` (MALE): `D29`/`D30` nie istnieją w numeracji rejestru. 🟡 nie sprawdzone: ~30 z 36 wierszy jeden po drugim, historia zniknięcia `D29`/`D30`, kolumna „odrzucone alternatywy" |
| E6 | Instrukcja dla klienta w paczce | 🟡 | `pakuj-dla-klienta.sh` sprawdza obecność | treść nieoceniana |
| E7 | Teksty widziane przez klienta (panel, maile) | 🟡 | audyt sklepu | osobny rejestr |

## F. DANE I PRAWO

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| F1 | RODO — dane osobowe w bazie i logach | 🟡 | strażnik RODO (jednostronny, klasa 72) | dług |
| F2 | Prawo konsumenckie — zgoda art. 16(m) | 🟡 | zapisane w pamięci warsztatu | czy w mailu — sprawdzone w audycie sklepu |
| F3 | Wpięcie w mechanizm prywatności WordPressa | ✅ | **1.09** | ⛔ `I-16`: **ZERO** eksporterów/erasersów/`wp_add_privacy_policy_content` (kontrola dodatnia: 33× `add_action`). Wtyczka MA własną anonimizację, ale wbudowane narzędzie WP „Eksportuj/Usuń dane osobowe” **nie widzi danych sklepu** — administrator uzna obowiązek za wypełniony, a dane zostaną. Dług był ZAPISANY w `CO-WISI.md:304` i pojechał do klienta |
| F4 | Retencja danych kupujących | 🟡 | **1.09**, `audyt/plamy/F4-RAPORT.md`, przepisy RODO cytowane ze źródła (EUR-Lex 32016R0679), stan bazy i harmonogramu crona zmierzony na stanowisku | ⛔ **3 znaleziska.** `F4-F1` (DUZE, OTWARTE): retencja 90 dni obejmuje WYŁĄCZNIE `zdarzenia` i `logowania` — `zamowienia` (41 z 43 wierszy z mailem i treścią), `opinie`, `sesje_checkout` bez żadnej retencji, polityka nie podaje dla nich ani okresu, ani kryteriów. `F4-F2` (SRED): instrukcja obiecuje bezwarunkowe sprzątanie, cron bywa martwy (zmierzone opóźnienie 2h31min), żaden z 6 komunikatów Pulpitu tego nie pilnuje. `F4-F3` (MALE): `sesje_checkout` bez żadnego kasowania. ✅ mechanizm istnieje i jest wołany, nie jest martwą gałęzią. 🟡 nie sprawdzone: samo kasowanie po 90 dniach na żywo, retencja w tabelach botów, retencja po stronie Stripe |
| F5 | Regulamin sklepu (osobny dokument) | 🟡 | **1.09**, `audyt/plamy/F5-RAPORT.md`, przepisy cytowane ze źródła w `audyt/plamy/F5-teksty/zrodla-prawne.md` | ⛔ **5 znalezisk.** `F5-F1`/`F5-F2` (oba DUZE, OTWARTE): Regulamin i Polityka to szkice (`draft`, bez sluga), nielinkowane znikąd (rdzeń „regulamin" = 0/0/0 wystąpień na stronach sklepu), szablon regulaminu (5 linii) bez procedury reklamacyjnej, odpowiedzialności za zgodność świadczenia i bez treści gwarancji obiecywanej 4× na stronie produktu. `F5-F3` (SRED): dane sprzedawcy istnieją tylko w stopce maila PO zakupie, brak ich PRZED zawarciem umowy (wymóg art. 12 ust. 1). `F5-F4` (SRED): Polityka nie nadążyła za 3 kolejnymi wersjami wtyczki (opinie, IP, dane do faktury). `F5-F5` (MALE): powołanie na uchylony art. 38a. ✅ CZYSTO: mail potwierdza zgodę na utratę prawa odstąpienia z datą. 🟡 nie sprawdzone: strony motywu gospodarza, kwalifikacja prawna sformułowań, strona Segregatora |

## G. REPOZYTORIUM I HISTORIA

| # | Teren | Status | Czym i kiedy | Co wyszło |
|---|---|---|---|---|
| G1 | Historia gita — sekrety | ✅ | gitleaks + `bramka-poswiadczenia` per-PR | 347 commitów, 0 trafień |
| G2 | Co jest ŚLEDZONE (vendor, node_modules) | ✅ | 31.08 | 0 i 0; ale 29 ZIP-ów → `B-10` |
| G3 | `.gitignore` — czy nie przepuszcza | ✅ | **1.09** | **CZYSTO**: ignoruje `vendor`, `node_modules`, `*.log`, `.env*`, `*.pem`, `wydanie/`, zrzuty; jawny wyjątek `!audyt/sklep/PROBY-KONTROLNE.log` z powodem (dowód poza origin nie istnieje). Zapora właściwa i tak jest w CI (gitleaks + skaner poświadczeń) |
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

**Po drugie:** ⭐ **pozycji `⬜ NIETKNIĘTE` jest ZERO** (stan 1.09 przed południem). Pierwszy raz
w historii tego projektu każdy obszar mapy ma za sobą co najmniej jeden przelot z dowodem.
Zamknięte tego dnia: `D5`, `D7`, `D8`, `E4`, `F3`, `G3` rano, a potem `A4b`, `A6`, `C12`, `C13`,
`E5`, `F4`, `F5` raportami audytora i `A7` trzema osobnymi przelotami.

⛔ **To NIE znaczy, że teren jest sprawdzony.** Liczba `🟡 częściowo` **urosła z 20 do 26**
— i tak ma być. Obszar domknięty raportem prawie zawsze zostawia sekcję „czego nie sprawdziłem",
a my wpisujemy go wtedy jako częściowy, zamiast odhaczyć na zielono. Ta liczba rośnie dlatego,
że przestaliśmy udawać, a nie dlatego, że robimy krok w tył.

⛔ **Najgroźniejsze miejsce projektu przeniosło się z białych plam na obszary „częściowo".**
Dowód, nie teoria: `A4` był oznaczony jako **zmierzony z dowodem**, bo ktoś sprawdził, JAK
ładują się pliki frontu — i nikt nie sprawdził, CO one robią. W tym samym obszarze siedziało
`A4b-F1`: sklep nie sprzedawał, i pojechało to do klienta. Pytanie do każdej pozycji `🟡` brzmi
więc: **co tu zostało naprawdę zmierzone, a co tylko wygląda na zmierzone.**

⛔ **Liczby w tym pliku licz komendą, nie z pamięci.** Pierwsza wersja deklarowała
„34 pozycje”, bo autorka wpisała liczbę z głowy; faktyczny pomiar dał **57**.
To ta sama klasa błędu, którą ten sam plik opisuje. Sprawdzenie:
```
grep -cE '^\| [A-H][0-9]+ \|' audyt/MAPA-TERENU-INFRA.md      # wszystkie pozycje
grep -cE '^\| [A-H][0-9]+ \| [^|]+ \| ⬜ \|' audyt/MAPA-TERENU-INFRA.md   # nietknięte
```

**Po trzecie:** najgroźniejsza biała plama zamknęła się 1.09 i wyszła z niej rzecz najgorsza
z dzisiejszych — `I-16`: dług dotyczący **danych osobowych kupujących** był ZAPISANY
w `CO-WISI.md` i mimo to **pojechał do klienta**, bo nikt nie przenosi długów z tamtego
dokumentu do rejestru znalezisk ani nie pyta przed wydaniem, które z nich jadą.

**Co zostało nietknięte: NIC.** Stan zmierzony komendą 1.09 przed południem — 58 pozycji:
**31 zmierzonych z dowodem · 26 częściowo · 1 poza zasięgiem · 0 nietkniętych.**

**Gdzie teraz szukać** — trzy miejsca, w tej kolejności:
1. **26 obszarów `🟡`**, a w każdym pytanie „co tu naprawdę zmierzono". Najgorętsze te, które
   widzi klient albo jego kupujący: `E7` teksty w panelu i mailach, `E6` instrukcja w paczce,
   `F2` zgoda konsumencka, `A5` symetria zapisu i sprzątania.
2. **Sekcje „czego nie sprawdziłem" w raportach** — one są listą zadań, nie przypisem.
   Największe dziś: STARTTLS klienta poczty, wydajność przy większych danych (zero pomiarów
   w całym etapie), oraz sześć plików produktu, których nie tknęła żadna soczewka.
3. **Warstwa spoza repozytorium** — `H4` maszyna warsztatu. Tam już są trzy wyniki
   (`W-1`, `W-2`, `W-3`), a teren okazał się szerszy niż ten wiersz mapy: narzędzia stoją
   w dwóch miejscach, nie w jednym.
