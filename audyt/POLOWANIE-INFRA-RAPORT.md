# RAPORT POLOWANIA — infrastruktura sklep-agentow (31.08, po werdykcie kolegi)

**Status:** FINALNY — unia dwóch niezależnych przebiegów (Gosia + świeży łowca II); znaleziska łowcy zweryfikowane własnym pomiarem przed wciągnięciem (kto-wola: powtórzone rc=0; „wołacze” sierot: 1 trafienie = wpis w CHANGELOG narzędzi, wzmianka ≠ wywołanie).
Plan: `audyt/PLAN-POLOWANIA-INFRA.md` (klepnięty). Tabela: `audyt/POLOWANIE-INFRA-TABELA.md`.
Tablica celu (od kolegi, na ślepo): 3 duże + 4 średnie + 4 małe = 11, „większość infrastruktura”.

## ZNALEZISKA (każde z dowodem z uruchomienia)

### KRYTYCZNE — WYKRACZA POZA TO ZLECENIE (przebieg 4, rama napastnika)
**Z19. Wykonawca CI chodzi jako ROOT na maszynie, na której leżą projekty WSZYSTKICH klientów**
Zmierzone: `ps -eo user,pid,comm` → `root 3998206 Runner.Listener`; `/root/actions-runner/.runner`
→ rejestracja na REPO `sklep-agentow`, `workFolder: _praca`; `ls /root/praca/zlecenia/` → obok
sklepu stoją m.in. `automatic-ai`, `catsnboard`, `mp-b2`, `mp-b3`, `mp-s1-klon`, `strona-wp`,
`k10-tester-stanowisko`. Wykonawca NIE jest efemeryczny (usługa systemd, trwały dysk).
Ścieżka: PR do sklepu dodaje w `composer.json` skrypt `post-install-cmd` → `composer install`
z `quality.yml`/`recenzent.yml` odpala go **jako root, PRZED jakąkolwiek recenzją** → kod
napastnika ma dostęp do repozytoriów i danych pozostałych klientów oraz przeżywa kolejne przebiegi.
Zasięg rażenia: nie jeden klient — cały warsztat. Waga: KRYTYCZNE.
⛔ To nie jest wada napisana w tym repo: to własność architektury (self-hosted + brak izolacji +
jedna maszyna na wszystkie zlecenia). Decyzja o zmianie należy do właściciela.

**Z20. Strażnik manifestu przepuszcza SPÓJNE wyłączenie bramki jednym PR-em**
`narzedzia-qa/sprawdz-manifest.sh` pilnuje wyłącznie zgodności trzech miejsc (manifest ↔ rejestr
`BRAMKI=()` ↔ próg). ODTWORZONE w klatce: usunięcie `gitleaks` z rejestru + `PROG_PELNY` 10→9 +
usunięcie wiersza z manifestu → `MANIFEST-GUARD: zgodne — wszystkie bramki z manifestu aktywne,
bez furtek`, **rc=0**. Czyli strażnik broni przed CICHYM pominięciem bramki, ale nie przed jawnym,
spójnym jej wypisaniem — a bez ochrony gałęzi taki PR może scalić autor. Skutek: paczka do klienta
wychodzi bez skanu sekretów, a wszystkie kontrole świecą zielono. Waga: KRYTYCZNE.
(Obrona, która dziś działa: komentarz w manifeście żąda OSOBNEGO PR-a na zmianę manifestu —
konwencja tekstowa, nie technika. Kandydat na naprawę: podpis/lista referencyjna bramek poza
zasięgiem jednego PR-a albo wymóg, by zmiana manifestu wymagała zgody drugiej osoby.)

### KRYTYCZNE / BEZPIECZEŃSTWO (przebieg 3 — rama „czego pytania NIE pytały”)
**Z14. `Bash(find:*)` w allowliście AI-recenzenta = furtka do dowolnej komendy na naszym serwerze**
`.github/workflows/recenzent.yml:157` — w `--allowedTools` jest `Bash(find:*)`. `find` z `-exec`
uruchamia dowolny program. DOWÓD: `find . -maxdepth 0 -exec echo WSTRZYKNIETE-KOD-WYKONANY \;`
→ komenda wykonana. Recenzent to model czytający TREŚĆ PR-a (kod, opis, komentarze) i chodzi na
`runs-on: [self-hosted, linux, x64]`, czyli na naszej maszynie. Autor allowlisty świadomie wyciął
`Bash(docker exec:*)` z komentarzem, że „oddałby całą maszynę” — `find:*` daje tę samą moc innym
wejściem. Ścieżka: wstrzyknięcie polecenia w treść PR-a → recenzent woła `find … -exec …` → kod
napastnika na serwerze warsztatu (ten sam serwer trzyma stos WP, klucze w opcjach, sesje).
⛔ To naprawiamy niezależnie od reszty polowania — pieniądze/bezpieczeństwo są poza regułą rund.
*(Kandydaci do tej samej klasy do sprawdzenia przy naprawie: `Bash(composer install)` — skrypty
z `composer.json` wykonują dowolny kod; `Bash(php -l:*)`; `Bash(bash …:*)` z wolnym ogonem.)*

**Z15. Oba workflow lecą na self-hosted runnerze z triggera `pull_request`**
`quality.yml:29-32` i `recenzent.yml:64-66` + `runs-on: [self-hosted, linux, x64]` w obu.
Kod z gałęzi PR-a (`composer install`, `bash narzedzia-qa/bramki.sh`) wykonuje się na naszym
serwerze. Łagodzi: repo prywatne (PR tylko od współpracowników — dziś kolega ma WRITE) i brak
`pull_request_target` (zmierzone: 0 trafień). Zaostrza: `composer install` odpala skrypty
z `composer.json` gałęzi, a runner nie jest izolowany (setup-php zmienia systemowe PHP — sami
to udokumentowaliśmy w `quality.yml`). Waga: DUŻE przy dzisiejszym gronie, KRYTYCZNE gdyby repo
kiedykolwiek stało się publiczne albo doszedł ktoś obcy.

**Z16. `WebFetch` w allowliście recenzenta bez ograniczenia domeny** *(łowca III, zweryfikowane)*
`.github/workflows/recenzent.yml:157` — lista narzędzi kończy się gołym `WebFetch` (zmierzone:
`grep -o "WebFetch[^\"]*"` → sam `WebFetch`, bez `(domain:…)`). Reguła „cytuj tylko oficjalne
źródła” leży w BRIEFINGU, czyli jest PROŚBĄ do modelu, nie barierą harnessu; udokumentowana
składnia `WebFetch(domain:example.com)` istnieje i nie została użyta. Ścieżka: treść PR-a nakłania
recenzenta do pobrania strony napastnika (pośrednie wstrzyknięcie) albo do wyniesienia danych
z repo w adresie URL. Recenzent ma `pull-requests: write` i czyta całe repo. Waga: DUŻE.

**Z17. Binarki bramek pobierane i uruchamiane bez sprawdzenia sumy kontrolnej** *(łowca III, zweryfikowane)*
`narzedzia-qa/bramki.sh` (`zapewnij_narzedzie`, ~linie 71–90): `curl` → `tar xzf` → `chmod +x` →
uruchomienie, na self-hosted runnerze. Wersje pinowane (dobrze), sumy NIE sprawdzane (zmierzone:
`grep -E "sha256|checksum|shasum" narzedzia-qa/bramki.sh` → 0 trafień; próba kontrolna grepa:
`grep -c curl` → 1, czyli szukanie działa). Podmieniony release u dostawcy albo podrzucony cache
= obca binarka wykonana na maszynie, na której stoi repo i stos klienta. Waga: DUŻE.

**Z18. SECURITY.md wskazuje wydanie sprzed TRZECH releasów jako aktualne** *(łowca III, zweryfikowane)*
`SECURITY.md:3-5` — „Stan na 2026-08-30… `sklep-agentow` (wydane 0.9.25; 0.9.26 w przygotowaniu)…
Wersje wspierane: **najnowsze wydanie każdej wtyczki**”. Zmierzone dziś: kod `Version: 0.9.28`,
tagi `v0.9.26`, `v0.9.27`, `v0.9.28` istnieją. Dokument sam sobie przeczy i kłamie o tym, co jest
wspierane — a jest to plik kontaktowy dla kogoś zgłaszającego LUKĘ. Waga: DUŻE (dokument
bezpieczeństwa czytany przez osoby z zewnątrz; klasa „wydanie zamraża dokumentację”, tym razem
w pliku bezpieczeństwa, i stan jest AKTUALNY, nie historyczny).

### DUŻE
**Z1. Bramka poświadczeń przechodzi, gdy nie wystartowała** *(ujawnione przez kolegę, potwierdzone pomiarem)*
`narzedzia-qa/bramka-poswiadczenia.sh:13` — `cd "$(git rev-parse --show-toplevel 2>/dev/null)" || { …; exit 2; }`.
Poza repo podstawienie jest puste, `cd ""` w bashu melduje sukces, `exit 2` to martwy kod.
DOWÓD: uruchomienie z pustego katalogu → `POSWIADCZENIA: 0 trafień (drzewo: 0 plików; historia od HEAD: 0 commitów)`, **rc=0**.
W repo działa: 570 plików/340 commitów rc=0; z podłożonym `sk_live_…` **rc=1** (łapie). Wada wyłącznie w starcie — ale to bramka BEZPIECZEŃSTWA i jej zielone kłamie, gdy ktoś odpali ją spoza korzenia.
Klasa: „bramka, która cicho nie startuje, świeci zielono” + „zero fałszywe jako obraz stanu”.

**Z2. Skaner śladów: zielone na pustym terenie (mianownik 0 = PASS)**
`narzedzia-qa/slady-wewnetrzne.sh` — uruchomiony na ISTNIEJĄCYM, pustym katalogu kończy `SLADY … nowe=0, dlug z listy=0`, **rc=0**, bez ostrzeżenia, że przeskanował zero plików.
DOWÓD: `bash narzedzia-qa/slady-wewnetrzne.sh proba-pusta /tmp/…/pusty2` → rc=0. Literówka w ścieżce wywołania = wiecznie zielony skaner. Z podłożonym śladem w prawdziwej wtyczce łapie (rc=1, „nowe=2”) — wada w mianowniku, nie w detekcji.

**Z9. Kalibrator strażników — „rdzeń planu audytów v3” jest sierotą** *(łowca II, zweryfikowane)*
`narzedzia-qa/kalibrator-straznikow.sh` — nagłówek: „MUTANT TESTUJE TESTY (rdzeń planu audytów v3)”. Nie woła go NIC: ani CI, ani `bramki.sh`, ani `k7-koniec.sh` (0 trafień; próba kontrolna metody: `mapa-licznik.sh` ma 3 realne wywołania i je widać). Obietnica twardego mechanizmu w nagłówku, mechanizmu nie ma w pipeline.

**Z10. Licznik odstępstw — „przekroczenie = wydanie stoi” nieegzekwowane przez nic** *(łowca II, zweryfikowane)*
`narzedzia-qa/licznik-odstepstw.sh` — nagłówek deklaruje twardą regułę wydania (waga DUŻA = 0 odstępstw, reszta max 3, przekroczenie blokuje wydanie). Bramka wydania `k7-koniec.sh` go NIE woła, CI też nie — reguła istnieje tylko jako tekst. Czytelnik CHANGELOG/nagłówka weźmie ją za obowiązującą.

### ŚREDNIE
**Z11. `kto-wola.sh`: błędna baza porównania = fałszywe „czysto”** *(łowca II, potwierdzone własnym pomiarem)*
`bash narzedzia-qa/kto-wola.sh nieistniejaca-galaz-xyz` → git dwa razy krzyczy `fatal: bad revision`, a skrypt kończy „nie ma zmienionych funkcji…”, **rc=0**. Pusty diff z błędu nieodróżniony od pustego diffu z braku zmian — literówka w nazwie gałęzi zamienia „nie zmierzyłam” w „sprawdziłam, czysto” w opisie PR.

**Z12. Dwie kolejne sieroty z rodziny procesu** *(łowca II)*
`narzedzia-qa/adresy-w-dokumencie.sh` (kontrola odsyłaczy plik:linia) i `narzedzia-qa/waga-znaleziska.sh` (waga z mapy ryzyka) — zero wywołań poza wpisem historycznym w CHANGELOG. Narzędzia istnieją, proces ich nie używa.

**Z13. `dokumentacja-zgodnosc*.sh` nazwane „Bramką” (CONTRIBUTING:46), a twardej bramki nie ma** *(łowca II; z zastrzeżeniem)*
Nie ma go w `BRAMKI=()`; jedyny automat to allowlista AI-recenzenta (`recenzent.yml:157`) — egzekwowanie osądem modelu, nie `exit 1` w CI. Zastrzeżenie: podnoszone już w `audyt/sklep/TODO-NARZEDZIA-PO-RECENZJI-182-r2.md` (W1, ✅ 26.08, zamknięte allowlistą) — więc pół-zaparkowane; zostaje zarzut, że SŁOWO „Bramka” w CONTRIBUTING obiecuje twardość, której świadomie nie zbudowano.

**Z3. Próba kontrolna bramki poświadczeń ma tę samą martwą konstrukcję i nie testuje warunku „poza repo”**
`narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh:6` — `cd "$(git rev-parse --show-toplevel)" || exit 2` (identyczna pułapka). Próba sprawdza detekcję sekretów, ale NIGDY nie pyta „czy bramka pada poza repo” — kontrola nie widziała błędu, przed którym miała bronić. DOWÓD: poza repo próba leci dalej po `fatal:` i pada dopiero na braku pliku względnego (rc=1 z przypadkowego powodu).

**Z4. Próby kontrolne bramek nie są wołane przez żaden automat per-PR**
CI (`quality.yml`) uruchamia wyłącznie `bash narzedzia-qa/bramki.sh`; `proby-kontrolne-przelot.sh` żyje tylko ręcznie i w bramce k7 (moment audytu/wydania). Regres w samej bramce (jak Z1) między przelotami jest niewidzialny. Nie znalazłam decyzji parkującej to w CO-WISI/DECYZJE (manifest parkuje świadomie tylko E2E — „w CI nie ma WP+Stripe”).

**Z5. Globalny gitconfig maszyny wstrzykuje hooki WARSZTATU do klonów repo klienta**
`git config --show-origin core.hooksPath` w świeżym klonie → `file:/root/.gitconfig  /root/.claude/git-hooki` — klon zlecenia dostaje po cichu hooki INNEGO projektu (a nie dostaje własnych z `.githooks/`). Krzyżowe zanieczyszczenie środowisk, nieudokumentowane nigdzie w repo.
*Kontekst zaparkowany:* sam fakt „świeży klon bez `git config core.hooksPath .githooks` nie ma blokady pusha na main” jest UDOKUMENTOWANY (quality.yml nagłówek, CONTRIBUTING §16) — zmierzyłam mimo to: push na main z klona do zdalnego przechodzi, **rc=0**. Do rejestru idzie tylko część nieudokumentowana (wstrzyknięcie cudzych hooków).

### MAŁE
**Z6. Dwa pliki pre-push o rozjechanej treści; żywy jest tylko jeden**
`diff .githooks/pre-push .git/hooks/pre-push` → różnice w komunikatach (żywy `.githooks` wspomina wyjątek `--no-verify`, martwa kopia w `.git/hooks` nie). Przy `core.hooksPath=.githooks` plik w `.git/hooks/` to nieczytany duplikat, który ktoś edytował osobno — następna edycja „hooka” może trafić w martwy plik.

**Z7. `k7-stare-wersje.sh` pisze domyślnie do absolutnej ścieżki poza repo**
`narzedzia-qa/k7-stare-wersje.sh:20` — `WYJ="${1:-/root/praca/zlecenia/wymiana/k7-stare-wersje}"`. Udokumentowane w komentarzu (linia 15), parametr istnieje — ale domyślne uruchomienie u kogokolwiek innego pisze w nieistniejący/kolizyjny katalog i NADPISUJE bez pytania (zmierzone: mój przebieg z klatki nadpisał 3 zipy w katalogu wymiany).

**Z8. Niespójna konwencja: pomoc z rc=0 przy braku argumentów**
`stos.sh` i `test-php.sh` przy braku argumentów wypisują pomoc i kończą **rc=0**; reszta rodziny (`paczka-zgodnosc.sh`, `mapa-licznik.sh`, `slady-wewnetrzne.sh`…) w tej samej sytuacji daje rc=2. W skrypcie nadrzędnym „zapomniany argument” raz jest błędem, raz cichym sukcesem.

## SPRAWDZONE I CZYSTE (dowód = kod wyjścia z uruchomienia)
- 41 skryptów `narzedzia-qa/` odpalonych spoza repo: poza Z1/Z3 wszystkie padają głośno i słusznie (rc=1/2 z komunikatem) albo kotwiczą się do własnego położenia i mierzą naprawdę (rc=124 = pełny bieg ucięty timeoutem 20 s testu).
- Wzorowe strażniki: `buduj-paczki.sh` (jawna odmowa poza repo, rc=1), `kalibrator-straznikow.sh`, `kto-wola.sh`, `linia-bazowa.sh`, `licznik-odstepstw.sh` (blokada zapobiegawcza przy braku gh/jq).
- `sprawdz-manifest.sh`: kontrola odwrotna DZIAŁA — wycięcie bramki `poswiadczenia` z `BRAMKI=()` → rc=1 z nazwą bramki (zmierzone podłożoną wadą).
- `k7-koniec.sh`: uczciwy kod — NO-GO → rc=1 (2 FAIL-e to znany dryf po wydaniu: kalibracja nowej epoki + przeterminowany przelot prób). ⚠️ Mój pierwszy pomiar przez potok pokazał rc=0 — błąd MOJEGO pomiaru, nie skryptu.
- `quality.yml`: `fetch-depth: 0` (skan historii w CI pełny — obietnica SECURITY.md trzyma), bez `if:`/`continue-on-error` na bramkach, concurrency, pinowane wersje narzędzi. `recenzent.yml`: `show_full_output: true`, briefing wpięty, fetch-depth 0.
- `phpstan.neon.dist`/`phpcs.xml.dist`: zakres = wszystkie 3 wtyczki; wymagania PHP spójne (nagłówki 8.1 = README; CI na 8.4 z wyjaśnieniem).
- `paczka-zgodnosc.sh sklep-agentow`: rc=0, 7 miejsc wersji spójnych, 68 plików paczki bez śladów. `bramka-znaleziska.sh`: rc=0 przy 43 kontrolach (mianownik jest). `dokumentacja-zgodnosc-sklep.sh`: rc=0, liczby dokumentacji zgodne z kodem.
- Log dowodowy `audyt/sklep/PROBY-KONTROLNE.log`: przypięty sha `6a7922c` istnieje w historii (git cat-file → commit).
- P3 próbką: podłożony sekret → bramka poświadczeń rc=1; podłożony ślad → skaner śladów rc=1; wycięta bramka → manifest rc=1.

## ZAPARKOWANE ŚWIADOMIE (nie liczone jako znaleziska)
Brak branch-protection (403 na darmowym GitHubie; kompensacja hookiem — quality.yml/CONTRIBUTING) · `paczka-zgodnosc.sh` poza `BRAMKI=()` (CO-WISI) · luki `slady-wewnetrzne.sh` opisane w CO-WISI (inne niż Z2) · martwa bramka nonce/klucza chatbota (CO-WISI, dług #210) · baner TRYB TESTOWY · jednostronny strażnik RODO (klasa 72) · E2E poza CI (manifest) · setup-php modyfikuje serwer (udokumentowane w quality.yml).

## CZEGO NIE POTWIERDZIŁAM
- 12 skryptów dockerowych (`dowod-*`, `stos.sh`, `k7-stos-rundy.sh`, `linia-bazowa.sh` w części stosowej…): zmierzone TYLKO warunki wejścia (`[~]` w tabeli) — pełna ścieżka wymaga stosu WP (RAM). Klasy 39/56 ze ściągi poza tym polowaniem (jawna luka z planu).
- `testy/` (122 pliki): sieroctwo formalne zmierzone (CI ich nie woła — świadome), próbka jakości 1 szt. (`dokumentacja-zgodnosc-sklep.sh` rc=0); reszta nieodpalana w tym polowaniu.
- Odznaki README na tle PRYWATNEGO repo (rendering u osób trzecich) — wymaga oczu przeglądarki, nie grepa.
- Obrazy (diagramy, zrzuty) — poza zakresem (potrzebują oczu).

## CZEGO P1–P6 W OGÓLE NIE PYTAŁO (przeciwwaga na kotwiczenie)
- O CZAS: czy bramki zdążą (timeouty CI, wiszące przebiegi na własnym runnerze bez sufitu).
- O WSPÓŁBIEŻNOŚĆ: dwa przebiegi CI/dwa czaty na tym samym runnerze i wspólnym cache narzędzi.
- O UPRAWNIENIA plików (kto może podmienić skrypt bramki między przebiegami na self-hosted).
- O SPÓJNOŚĆ WERSJI NARZĘDZI lokalnie vs CI (gitleaks 8.24.3 pinowany w bramce; lokalny może być inny).

## SKUTKI UBOCZNE POLOWANIA (uczciwość)
- Mój testowy przebieg `k7-stare-wersje.sh` z klatki NADPISAŁ 3 zipy w `/root/praca/zlecenia/wymiana/k7-stare-wersje/` (stemple 14:02). Treść powinna być identyczna (`git archive` z tej samej historii); jeśli coś porównuje stemple — wiedz, że to ja.
- Klatka `/tmp/polowanie-infra-*` (~600 MB) — do skasowania po zamknięciu polowania.

## LICZNIK vs TABLICA (po CZTERECH przebiegach — komplet)
**2 KRYTYCZNE (Z19, Z20) + 5 bezpieczeństwa/dużych (Z14–Z18) + 4 duże (Z1, Z2, Z9, Z10)
+ 6 średnich (Z3, Z4, Z5, Z11, Z12, Z13) + 3 małe (Z6, Z7, Z8) = 20** · tablica kolegi: 11.
Narastanie po przebiegach: 8 → 13 → 18 → 20. Każdy przebieg z INNĄ ramą dokładał znaleziska,
których poprzednie nie widziały; przebiegi 3 i 4 (ramy „czego nie pytałam” i „napastnik”)
przyniosły CAŁĄ rodzinę bezpieczeństwa, o którą pierwsze dwa nie zapytały ani razu.
⛔ Z19 nie jest wadą tego repo — to architektura warsztatu; wpisane tutaj, bo tu je znaleziono,
ale decyzja i naprawa są poza zleceniem.
· tablica kolegi: 3+4+4=11. Liczby nie muszą się pokrywać jeden do jednego (inne krojenie wag
i możliwe dublety z jego listą) — porównanie POZYCJAMI robimy teraz, po ujawnieniu jego listy,
tropów Dzidka i tajnego błędu-kontrolki.
Wkład przebiegów: przebieg 1 → Z1–Z8 · przebieg 2 (świeży) → Z9–Z13, zero pokrycia wzajemnego
w nowych znaleziskach = kolejny dowód, że jeden przebieg łapie ułamek.

## CZYSTE PO PRZEBIEGU 3 (łowca III — klasy sprawdzone i niewystępujące)
Brak `pull_request_target` · brak wstrzyknięcia przez `${{ github.event.* }}` w `run:` (prompt
interpoluje tylko numer PR i nazwę repo) · `permissions:` zawężone per-job, zero `write-all` ·
`buduj-paczki.sh` z dwustronną kontrolą zawartości zipa i zamkiem wydanych paczek ·
`pakuj-dla-klienta.sh` z samokontrolą na rozpakowanej paczce i sprzątaniem trapem ·
`composer.lock` śledzony, `allow-plugins` zawężone · `phpstan` nie tyka vendor/node_modules ·
`.gitignore` wyklucza `.env*`/`*.pem`/`wydanie/` · `concurrency` z anulowaniem w obu workflow.

## DODATKOWO CZYSTE (łowca II, z dowodami)
- `.githooks/pre-push` PO aktywacji realnie blokuje push na main (zmierzone: exit 1 + komunikat).
- `bramki.sh` bez sieci i bez binarek: actionlint/gitleaks padają JAWNIE (fail-closed), nie po cichu.
- `buduj-paczki.sh`: `set -euo pipefail`, zamek paczek fail-closed. `sprawdz-manifest.sh`: kontrole dwukierunkowe zgodne z nagłówkiem.
- Manifest (10) = `BRAMKI=()` (10) = „dziesięć bramek” w README:102.

## CZEGO NIE POTWIERDZIŁ TAKŻE ŁOWCA II (dołożone do listy powyżej)
- Realne przebiegi phpcs/phpstan/php-lint (composer install pominięty świadomie — czas/sieć); zmierzono tylko fail-closed przy braku vendor/.
- Czy pinowany gitleaks 8.24.3 jest nadal ściągalny i czy actionlint realnie łapie zepsuty YAML (stuby sieci celowo blokowały).
- Pełny prompt/allowlista `recenzent.yml` pod kątem furtek (czytany wycinkowo).
