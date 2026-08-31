# PLAN POLOWANIA — infrastruktura sklep-agentow (werdykt kolegi 31.08)

**Status:** v2.1 po DWÓCH rundach krytyki (r1: 3 krytycy × FAIL, 28 uwag; r2: weryfikator — 1 WAŻNA + kosmetyka, wniesione).
Czeka na KLEPNIĘCIE DZIDKA. Zero napraw przed klepnięciem listy znalezisk.

## 1. TŁO I CEL
- Werdykt kolegi (Man1exter): **3 duże + 4 średnie + 4 małe = 11 błędów**, większość w INFRASTRUKTURZE; sam audyt produktu ocenił 9/10.
- Ujawniony 1 przykład: `narzedzia-qa/bramka-poswiadczenia.sh:11` — `cd "$(git rev-parse --show-toplevel 2>/dev/null)" || exit 2`; poza repo git zwraca PUSTY tekst, `cd ""` w bashu NIC nie robi i melduje sukces, więc `exit 2` to martwy kod; bramka skanuje 0 plików + 0 commitów i świeci PASS.
- **Cel:** ślepe polowanie buduje WŁASNĄ listę znalezisk. Dopiero po raporcie porównujemy z tablicą 3/4/4, dwoma tropami Dzidka i tajnym błędem kolegi (jego próba kontrolna naszego szukania).
- ⛔ Nie wolno mi przed raportem poznać listy kolegi ani tropów Dzidka.

## 2. TEREN — inwentarz kandydatów
1. `narzedzia-qa/` — ~40 plików: bramki + proby kontrolne + k7 + stos + dowody + kalibrator.
1b. **Configi bramek jakości:** `composer.json`/`.lock` (zgodność), `phpcs.xml.dist`, `phpstan.neon.dist`, `phpstan-bootstrap.php` — czy ZAKRES analizy obejmuje wszystkie 3 wtyczki po ostatnich zmianach.
2. `testy/` — skrypty `.sh`/`.php`/`.js` + `e2e/` (jako NARZĘDZIA: czy umieją paść).
3. `.github/workflows/quality.yml` i `recenzent.yml` + `.github/manifest-bramek.txt` + **runner self-hosted** (pytanie: czy istnieje strażnik na „PR bez przebiegu CI > X minut”, niezależny od stanu runnera).
4. **Mechanizm hooków, nie plik hooka:** `.githooks/pre-push` (wersjonowany) vs `.git/hooks/pre-push` (lokalny) vs `git config core.hooksPath`. Jawny przypadek P1: świeży klon bez ręcznej konfiguracji NIE ma ochrony — zmierzyć i nazwać skutki; sprawdzić rozjazd treści obu plików.
5. `boty/buduj-paczki.sh`.
6. Warstwa GitHub-UI: README (odznaki, linki), CONTRIBUTING, SECURITY.md, `.github/PULL_REQUEST_TEMPLATE.md`, Release 0.9.28 (4 paczki + digesty), tag `bank/mutanty-sklep` — obietnica vs stan. Byty na `origin` mierzone `git ls-remote origin` (nie lokalnym `git tag -l`).
7. `wydanie/*.zip` — zgodność paczek (`wydanie/` jest w `.gitignore` → do klatki wchodzi osobnym `cp`, patrz Krok 0).
8. Dokumenty procesu i generatory: RECENZENT-BRIEFING, rubryka-GOTOWE, PLAN-PROJEKTU, `dokumentacja/render-diagramy.js` + aktualność diagramów; log dowodowy prób kontrolnych — czy sha przypięte w logu istnieją w historii `main` (`git cat-file -t <sha>`).

## 3. PYTANIA (dzielimy po PYTANIU, nie po terenie)
- **P1 AWARIA:** czy narzędzie PADA, gdy powinno? Odpalenie w warunku awarii: spoza repo (pusty katalog BEZ `.git`), bez zależności (gitleaks/php/docker/jq), z zerwanym założeniem wejściowym. Oczekiwane: exit≠0 + jawny komunikat. Ciche zielone = znalezisko.
- **P2 MIANOWNIK:** czy zielone stoi na pomiarze? Ile plików/commitów/przypadków realnie przeskanowano; mianownik 0 ⇒ musi być FAIL.
- **P3 PRÓBA KONTROLNA:** czy asercje umieją PAŚĆ (podłożona wada psuje DOKŁADNIE JEDEN warunek)? Plus osobna kolumna: **jakiego rodzaju uszkodzenia ten mechanizm NIE JEST W STANIE zobaczyć** (liczy linie, nie treść; sprawdza istnienie, nie wywołanie).
- **P4 BASH-PUŁAPKI I MARTWY KOD:** `cd ""`, potok maskujący kod wyjścia, `set -e` w subshellach/funkcjach, `pgrep -f`, quoting, `awk>tmp+mv` gubiący +x, ostatnia instrukcja `exit` ucinająca asercje.
- **P5 OBIETNICA vs STAN:** każde zdanie README/SECURITY/CONTRIBUTING/szablonu PR o mechanizmie sprawdzane `grep -n` po miejscu WYWOŁANIA mechanizmu, nie po wzmiance nazwy.
- **P6 PODŁĄCZENIE:** dla każdego pliku w `narzedzia-qa/` i `testy/`: kto go WOŁA (`grep -rn` po nazwie w `.github/workflows/`, manifeście, `bramki.sh`, hookach)? Zero trafień = plik istnieje, ale jest sierotą — znalezisko, nawet gdy sam działa bezbłędnie.

## 4. WYKONANIE
- **Krok 0 — klatka:**
  - `git clone --no-hardlinks /root/praca/zlecenia/sklep-agentow /tmp/polowanie-infra-git` (narzędzia potrzebują `.git` — 14/40 woła gita) + `cp -r .../wydanie /tmp/polowanie-infra-git/wydanie` (gitignorowane zipy) + osobny PUSTY katalog do testów „poza repo”.
  - Byty publiczne (tagi/gałęzie/release) mierzone przez `git ls-remote origin` — to, co widzi świat, nie lokalny stan.
  - **Reset między testami niszczącymi:** `git checkout -- . && git clean -fdx -- ':!wydanie'` w klatce; nigdy dwa testy niszczące bez resetu.
  - Pomiary BEZ potoków, kod wyjścia osobnym `echo $?`. ⛔ Bez pełnego stosu WP (3,8 GB RAM).
  - Przed testami `bramki.sh`: `command -v actionlint gitleaks` + stan cache — żeby awarii sieci nie policzyć jako wady narzędzia.
- **Krok 1 — tabela kandydatów:** plik × P1–P6, NAJPIERW cała tabela (`audyt/POLOWANIE-INFRA-TABELA.md`, kratki `[ ]`/`[x]`/`[~]` — stan `[~]` „zmierzone częściowo” dozwolony WYŁĄCZNIE dla 12 skryptów dockerowych z sekcji 5), potem odhaczanie.
- **Krok 2 — pomiar w 2 transzach:** T1 = P1+P2+P4+P6 (mechaniczne) ≈ 1,5–2 h → meldunek Dzidkowi z liczbą znalezisk; T2 = P3 (podkładanie wad) + P5 (GitHub-UI) ≈ 1,5–2 h. Nie zaczynam T2 bez zameldowania T1. Subagenci (sonnet): tylko streszczanie długich plików, max 15 kroków, 1 plik na wywołanie. Grep z zerem trafień → próba kontrolna (rdzeń słowa, nie forma odmieniona).
- **Krok 3 — znalezisko:** `plik:linia` + dowód (komenda + zmierzony kod wyjścia) + waga (D/Ś/M wg skutku dla klienta/bezpieczeństwa) + klasa ze ściągi 72 (albo NOWA).
- **Krok 4 — cross-check zaparkowanych (trzy źródła):** CO-WISI, DECYZJE **i inline komentarze decyzyjne** w `.yml`/`.sh` (np. nagłówek `quality.yml` o braku branch-protection). Zaparkowane ≠ „ominięte”. Znane z góry, NIE liczyć jako nowe: `paczka-zgodnosc.sh` poza `BRAMKI=()`, luki `slady-wewnetrzne.sh`, martwa bramka nonce/klucza chatbota, baner TRYB TESTOWY (7–8 miejsc, czeka na decyzję właściciela), jednostronny strażnik RODO (klasa 72).
- **Krok 5 — drugi przebieg UNIĄ:** po zamknięciu tabeli jeden świeży krytyk-łowca (sonnet, bez wglądu w moją tabelę) przechodzi P1–P6 po `narzedzia-qa/` własną kolejnością; wyniki łączone UNIĄ (jeden przebieg łapie historycznie 17–50%).
- **Krok 6 — raport:** `audyt/POLOWANIE-INFRA-RAPORT.md` + kopia do `/root/do-panelu/`. Dopiero po nim: tropy Dzidka, tajny błąd kolegi, porównanie liczb.

## 5. CZEGO NIE ROBIĘ / JAWNE LUKI POKRYCIA
- Nie tykam kodu produktu (`wtyczki/`) — audyt 9/10, poza zakresem.
- Nie naprawiam NICZEGO w trakcie — naprawy jedną paczką po klepnięciu listy.
- Nie sztukuję listy do 11 — jak wyjdzie mniej, piszę wprost ile i którego pomiaru nie umiałam zrobić.
- **12 skryptów dockerowych** (dowody-zachowania, stos, linia-bazowa…): P1–P3 tylko na warunkach wejścia PRZED startem kontenerów; w tabeli „zmierzone częściowo — brak stosu”, NIGDY „PASS”.
- **Klasy 39 i 56 ze ściągi** (padnięty kontener zależnościowy; martwy logger WP w obrazie) — ŚWIADOMIE poza tym polowaniem z powodu RAM. To nazwana luka, nie przeoczenie.
- **Obrazy** (`dokumentacja/diagramy/`, `zrzuty-wygladu/`) — wymagają OCZU, nie grepa; poza polowaniem, nazwane wprost.

## 6. BRAMKA KOŃCA (mechaniczna)
- `grep -c "\[ \]" audyt/POLOWANIE-INFRA-TABELA.md` musi zwrócić **0** — inaczej NO-GO. Stan `[~]` jest dozwolonym stanem KOŃCOWYM tylko dla 12 skryptów dockerowych i w raporcie NIGDY nie liczy się jako PASS.
- Każde „czysto” ma dowód URUCHOMIENIA (kod wyjścia), nie przeczytania.
- Raport ma DWIE osobne sekcje: „czego nie potwierdziłam” (nie zdążyłam/nie umiałam zmierzyć) ORAZ **„czego P1–P6 w ogóle NIE pytało”** (kategorie bez pytania — przeciwwaga na kotwiczenie listą).
- Zastrzeżenie metodyczne: odhaczam tabelę sama, bo poluję sama — Dzidek/kolega ma prawo zakwestionować każde „czysto” bez przechodzenia całości.

## 7. KOSZT I CZAS (po korekcie krytyka: poprzedni szacunek był ~2× zaniżony)
- Runda 2 krytyki planu (1 weryfikator): ~1–2 $, ~10 min.
- Polowanie: T1 ≈ 1,5–2 h + T2 ≈ 1,5–2 h + drugi przebieg ≈ 20 min + spisanie znalezisk, cross-check zaparkowanych i raport ≈ 0,5–1,5 h; razem **4–6 h, ~35–55 $**, z punktem przerwania po T1.
- Tydzień na 42% — mieści się; melduję koszt po każdej transzy.

## 8. RYZYKA
- Dysk 87% — klatka bez node_modules/vendor (~100 MB z wydanie/), sprzątana po polowaniu.
- OOM 3,8 GB — bez stosu WP, bez sesji-audytora w tmuksie.
- Sieć: `bramki.sh` dociąga actionlint/gitleaks z GitHuba — awaria sieci ≠ wada narzędzia (notowane w tabeli).
- Nadgorliwy grep-guard — pomiary plikowe zamiast szerokich grepów.

## ZAŁĄCZNIK — rozliczenie rundy 1 (28 uwag; K1=metoda, K2=teren, K3=wykonalność)
**PRZYJĘTE (27 z 28 — każda uwaga liczona raz; K1#6 przyjęta w formie OKROJONEJ):**
- K1#1+K2#1 (zbieżne, KRYTYCZNE): mechanizm `core.hooksPath`/dwa pliki pre-push → sekcja 2 pkt 4.
- K1#2 (KRYT): sekcja „czego P1–P6 nie pytało” → sekcja 6.
- K1#3: kolumna „czego próba NIE widzi” → P3.
- K1#4: pytanie o PODŁĄCZENIE → nowe P6.
- K1#5+K3#1 (zbieżne, KRYT): klatka przez `git clone --no-hardlinks` + osobny pusty katalog, byty publiczne przez `ls-remote origin` → Krok 0.
- K1#6: drugi przebieg → Krok 5 (w wersji taniej: 1 świeży łowca, UNIA — nie pełna druga sesja; powód: koszt, tydzień 42%).
- K1#7: zastrzeżenie „odhaczam sama” → sekcja 6.
- K1#8: grep po WYWOŁANIU → P5. K1#9: zero trafień → próba kontrolna → Krok 2.
- K2#2 (WAŻNA): configi phpcs/phpstan/composer → teren 1b. K2#3: runner → teren 3. K2#4: render-diagramy → teren 8. K2#5: szablon PR → teren 6. K2#6: decyzje inline w komentarzach → Krok 4. K2#7: klasy 39/56 jako nazwana luka → sekcja 5. K2#8: obrazy jawnie poza → sekcja 5. K2#9: sha logu dowodowego → teren 8. K2#10: baner TRYB TESTOWY + klasa 72 do „nie liczyć jako nowe” → Krok 4.
- K3#2 (KRYT): `wydanie/` gitignorowane → cp → Krok 0/teren 7. K3#3: reset między testami → Krok 0. K3#4: zależność sieciowa → Krok 0 + ryzyka. K3#5: 12 skryptów docker „częściowo” → sekcja 5. K3#6: bramka mechaniczna grep-c → sekcja 6. K3#7: transze z meldunkiem → Krok 2. K3#9: budżet subagenta 15 kroków → Krok 2.
**ODRZUCONE (1) + wyjaśnienie okrojenia K1#6, z powodem:**
- K3#8 (P5 tylko na plikach zmienionych od audytu kolegi): ODRZUCAM — 11 błędów kolega znalazł na stanie BIEŻĄCYM, nie na delcie; cięcie do delty gwarantowałoby ślepotę na resztę jego listy. Koszt trzymam transzami, nie cięciem terenu.
- K1#6 w pełnej formie (druga niezależna sesja polowania): ZREDUKOWANE do Kroku 5 (świeży łowca UNIĄ) — pełna druga sesja to +100% kosztu przy tygodniu na 42%; decyzja do zmiany jednym słowem Dzidka.
