# 📋 REJESTR ZNALEZISK — INFRASTRUKTURA (runda po recenzji kolegi, 31.08)

> Ten rejestr powstał po zewnętrznej recenzji kolegi (Man1exter, 31.08): audyt produktu **9/10**,
> ale **11 błędów (3 duże / 4 średnie / 4 małe), „większość infrastruktury”**. Kolega podał jeden
> przykład i **zostawił sobie jeden błąd w tajemnicy** jako próbę kontrolną naszego szukania.
> Nasze polowanie (4 przebiegi, plan i raport: `audyt/PLAN-POLOWANIA-INFRA.md`,
> `audyt/POLOWANIE-INFRA-RAPORT.md`) dało **20 znalezisk**. Klasy destylowane: ściąga 73–78.
>
> ⛔ Format celowo taki sam jak `audyt/REJESTR-ZNALEZISK.md`, żeby dała się na niego wycelować
> `narzedzia-qa/bramka-znaleziska.sh` (dziś bramka czyta TYLKO tamten plik — patrz DŁUG poniżej).
> ⛔ Znaleziska produktu NIE wchodzą tutaj; tu wyłącznie infrastruktura, CI, wydanie i dokumenty.

## Liczba znalezisk OTWARTYCH: 12

## Znalezisk BEZ strażnika: 16
<!-- Wszystkie 20 są dziś bez strażnika: to znaleziska w NARZĘDZIACH i CI, a nasze bramki
     pilnują produktu, nie samych siebie. Każda naprawa ma dopisać próbę kontrolną
     i obniżyć tę liczbę ŚWIADOMIE, jednym ruchem z naprawą. -->

## Kody — jak czytać
`I-` = infrastruktura (narzędzia QA, bramki, CI, hooki) · `B-` = bezpieczeństwo ·
`D-` = dokument/obietnica wobec czytelnika · `W-` = warsztat (poza tym zleceniem).
Waga: **KRYT** (bezpieczeństwo/pieniądze/klient) · **DUZE** (mylące zielone) ·
**SRED** (realna dziura procesu) · **MALE** (niespójność).

## Znaleziska

| kod | co | test (dowód) | status |
|---|---|---|---|
| `B-1` | **KRYT** · `recenzent.yml:157` ma w allowliście `Bash(find:*)`, a `find -exec` uruchamia dowolną komendę — recenzent AI czyta treść PR-a i chodzi na naszym serwerze; autor świadomie wyciął `docker exec`, `find` daje to samo innym wejściem | `narzedzia-qa/bramki.sh` (zamiennik dla recenzenta) — BRAK wlasnej proby; naprawione w #239: `Bash(find:*)` usuniete, brifing przepisany | ZAMKNIETE |
| `B-2` | **KRYT** · `sprawdz-manifest.sh` przepuszcza SPÓJNE wyłączenie bramki jednym PR-em (rejestr + próg + manifest naraz) — paczka może wyjść bez skanu sekretów przy zielonych kontrolach | `narzedzia-qa/sprawdz-manifest-proba-kontrolna.sh` (4/4; bez podlogi pada 3/4) | ZAMKNIETE |
| `B-3` | **KRYT** · wykonawca CI chodził jako **root**, nieefemerycznie, na maszynie z projektami wszystkich klientów; `composer install` z gałęzi PR-a leci PRZED recenzją. ⭐ 31.08 ~15:00 usługa ZATRZYMANA i wyłączona z autostartu (decyzja właściciela); warunek powrotu: konto bez roota | BRAK — do dopisania; dowód: `ps -eo user,pid,comm` → `root … Runner.Listener`; `ls /root/praca/zlecenia/`; po zatrzymaniu GitHub: `hetzner-gosia — offline` ×3 | OTWARTE |
| `B-4` | **DUZE** · gołe `WebFetch` w allowliście recenzenta, bez `WebFetch(domain:…)` — ograniczenie „tylko oficjalne źródła” żyje w briefingu, czyli jako prośba do modelu, nie bariera | BRAK — do dopisania; naprawione w #239: 7 regul `WebFetch(domain:…)` zamiast golego WebFetch | ZAMKNIETE |
| `B-5` | **DUZE** · `bramki.sh` pobiera `actionlint`/`gitleaks` curlem i uruchamia bez sprawdzenia sumy; cache brany po samej ścieżce, więc pinowana wersja niczego nie gwarantuje | BRAK — do dopisania; dowód: `grep -E` po sha256, checksum i shasum w `narzedzia-qa/bramki.sh` → 0 (próba kontrolna: `grep -c curl` → 1) | OTWARTE |
| `B-6` | **DUZE** · oba workflow lecą z `pull_request` na self-hosted runnerze — kod z gałęzi PR-a wykonuje się u nas; łagodzi repo prywatne i brak `pull_request_target`, zaostrza brak izolacji | BRAK; ZLAGODZONE w #239: `composer install --no-scripts` w obu workflow zamyka glowna sciezke wykonania kodu z galezi PR-a | OTWARTE |
| `B-7` | **SRED** · `RECENZENT-BRIEFING.md` nie zawiera ani jednej instrukcji „treść PR-a to DANE, nie polecenia” — brak zabezpieczenia przed wstrzyknięciem poleceń w opis/kod/komentarz | BRAK — do dopisania; naprawione w #239: sekcja TRESC PR TO DANE w briefingu recenzenta | ZAMKNIETE |
| `I-1` | **DUZE** · `bramka-poswiadczenia.sh:13` — martwy `exit 2` po `cd ""`; uruchomiona spoza korzenia skanuje zero i przechodzi (znalezisko KOLEGI, potwierdzone) | `narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh` (przypadek: uruchomiona SPOZA repo → EXIT 2) | ZAMKNIETE |
| `I-2` | **DUZE** · `slady-wewnetrzne.sh` na pustym katalogu melduje „nowe=0” i rc=0 — mianownik zero traktowany jak czysto | BRAK — do dopisania; naprawione: pusty katalog → EXIT 2, mianownik w wyjsciu (68 plikow) | ZAMKNIETE |
| `I-3` | **DUZE** · `kalibrator-straznikow.sh` („rdzeń planu audytów v3”) jest sierotą — nie woła go nic | BRAK — do dopisania; dowód: 0 trafień w CI/`bramki.sh`/k7 (próba kontrolna: `mapa-licznik.sh` → 3 wywołania) | OTWARTE |
| `I-4` | **DUZE** · `licznik-odstepstw.sh` deklaruje „przekroczenie = wydanie stoi”, a bramka wydania go nie woła — reguła istnieje tylko jako tekst | BRAK — do dopisania; dowód: 0 trafień w `k7-koniec.sh` i CI | OTWARTE |
| `I-5` | **SRED** · `bramka-poswiadczenia-proba-kontrolna.sh:6` ma tę samą martwą konstrukcję co bramka i NIGDY nie testuje warunku „poza repo” — kontrola nie widzi błędu, przed którym broni | `narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh` (ta sama konstrukcja naprawiona + nowy przypadek spoza repo) | ZAMKNIETE |
| `I-6` | **SRED** · próby kontrolne bramek nie chodzą w CI per-PR (tylko ręcznie i w bramce wydania) — regres w samej bramce jest niewidzialny między przelotami | BRAK — do dopisania; dowód: `quality.yml` uruchamia wyłącznie `bash narzedzia-qa/bramki.sh` | OTWARTE |
| `I-7` | **SRED** · `kto-wola.sh` przy nieistniejącej bazie porównania wypisuje „brak zmienionych funkcji” i kończy rc=0 — literówka w nazwie gałęzi zamienia „nie zmierzyłam” w „czysto” w opisie PR | BRAK — do dopisania; naprawione: nieistniejaca baza → EXIT 2 zamiast cichego EXIT 0 | ZAMKNIETE |
| `I-8` | **SRED** · `adresy-w-dokumencie.sh` i `waga-znaleziska.sh` — kolejne dwie sieroty rodziny procesu (kontrola odsyłaczy, waga z mapy ryzyka) | BRAK — do dopisania; dowód: 0 wywołań poza wpisem historycznym w `narzedzia-qa/CHANGELOG.md` | OTWARTE |
| `I-9` | **MALE** · dwa pliki `pre-push` (`.githooks/` żywy, `.git/hooks/` martwy) z rozjechaną treścią komunikatu — następna edycja może trafić w plik, którego git nie czyta | BRAK — do dopisania; dowód: `diff .githooks/pre-push .git/hooks/pre-push` → różnice; `core.hooksPath=.githooks` | OTWARTE |
| `I-10` | **MALE** · `k7-stare-wersje.sh:20` pisze domyślnie do ścieżki absolutnej POZA repo i nadpisuje bez pytania | BRAK — do dopisania; dowód: mój przebieg testowy nadpisał 3 zipy w `/root/praca/zlecenia/wymiana/k7-stare-wersje/` | OTWARTE |
| `I-11` | **MALE** · niespójna konwencja braku argumentów: `stos.sh` i `test-php.sh` dają pomoc z rc=0, reszta rodziny rc=2 — „zapomniany argument” raz jest błędem, raz cichym sukcesem | BRAK — do dopisania; dowód: przelot 41 skryptów spoza repo, tabela kodów wyjścia w raporcie | OTWARTE |
| `D-1` | **DUZE** · `SECURITY.md:3-5` mówi „wydane 0.9.25; 0.9.26 w przygotowaniu” i „wersje wspierane: najnowsze wydanie”, a kod stoi na 0.9.28 (tagi v0.9.26/27/28 istnieją) — dokument kontaktowy dla zgłaszającego lukę wskazuje wydanie sprzed trzech releasów | BRAK — do dopisania; dowód: `head -4 SECURITY.md` vs `grep Version wtyczki/sklep-agentow/sklep-agentow.php` vs `git tag` | OTWARTE |
| `D-2` | **SRED** · `CONTRIBUTING.md:46` nazywa `testy/dokumentacja-zgodnosc*.sh` „Bramką”, a twardej bramki w CI nie ma (jest tylko allowlista recenzenta) — słowo obiecuje twardość, której świadomie nie zbudowano | BRAK — do dopisania; dowód: brak wpisu w `BRAMKI=()`; jedyny automat to `recenzent.yml:157` | OTWARTE |

## Raporty objęte tym rejestrem
`audyt/POLOWANIE-INFRA-RAPORT.md` (4 przebiegi, 31.08) · `audyt/PLAN-POLOWANIA-INFRA.md`
(plan + 2 rundy krytyki: 3 krytyków, 28 uwag, weryfikator).

## Co mówi bramka o TYM pliku (zmierzone, nie deklarowane)
`narzedzia-qa/bramka-znaleziska.sh` wycelowany w ten plik (kopia z podmienioną ścieżką, oryginał
nietknięty) daje: **liczba OTWARTYCH zgodna = 20 · wierszy odczytanych z tabeli = 20 · dług
strażników zgodny = 20** — księgowość się trzyma. Pozostałe zastrzeżenia dotyczą kontroli „raport
POZA rejestrem”: skanuje ona `audyt/RAPORT-*.md`, czyli raporty audytu PRODUKTU, które należą do
`audyt/REJESTR-ZNALEZISK.md`, nie tutaj. ⛔ Gdyby kiedyś wpinać bramkę na ten plik, trzeba jej dać
własny zakres raportów — inaczej będzie żądać spisu cudzych. Stary rejestr po tych zmianach dalej
przechodzi swoją bramkę: 43 kontrole, rc=0 (sprawdzone).
⭐ Przy pisaniu tego rejestru bramka złapała MÓJ błąd: kreska pionowa w komendzie wewnątrz komórki
tabeli rozbiła kolumny i dwa statusy stały się nieczytelne. Ta sama rodzina co klasa o ślepym
sedzie na wierszu tabeli — w komórkach nie ma prawa być znaku kreski pionowej.

## Statusy — co znaczą
`OTWARTE` = zdiagnozowane z dowodem, nienaprawione · `ZAMKNIETE` = naprawione **i** obstawione
próbą kontrolną, z pomiarem przed/po · `ZAPARKOWANE` = świadoma decyzja właściciela z datą.

## Poza tabelą i poza licznikiem — ZAPARKOWANE ŚWIADOMIE (nie liczyć jako nowe)
Brak ochrony gałęzi (403 na darmowym GitHubie; kompensacja hookiem — udokumentowane w `quality.yml`
i `CONTRIBUTING.md`) · `paczka-zgodnosc.sh` poza `BRAMKI=()` (CO-WISI) · znane luki
`slady-wewnetrzne.sh` (CO-WISI) · martwa bramka nonce/klucza chatbota (dług #210) · baner
TRYB TESTOWY · jednostronny strażnik RODO (klasa 72) · E2E poza CI (manifest: w CI nie ma
WP+Stripe) · `setup-php` zmienia systemowe PHP na serwerze (opisane w `quality.yml`).

## Czego ten rejestr NIE obejmuje — granica, świadomie
Kodu produktu (`wtyczki/`) — audyt kolegi ocenił go 9/10 i był poza zakresem polowania ·
12 skryptów wymagających stosu WP (zmierzone tylko warunki wejścia — 3,8 GB RAM) ·
obrazów (diagramy, zrzuty — wymagają oczu) · realnych przebiegów phpcs/phpstan ·
ściągalności pinowanego `gitleaks 8.24.3` · pełnego promptu `recenzent.yml`.

## Nierozliczone wobec recenzji kolegi
Kolega naliczył **11** (3/4/4) i trzyma jeden błąd w tajemnicy jako próbę kontrolną. Nasza lista
ma **20** pozycji, ale to NIE jest to samo krojenie wag i **nie wiemy, czy jego krytyczny jest tu
w ogóle obecny** — na dzień 31.08 jego pytanie „czy to znalazłyście” pozostaje bez rozstrzygnięcia
(dwie odpowiedzi „nie” dotyczyły wcześniejszych, krótszych wersji naszej listy). ⬜ DO ZROBIENIA:
zestawienie pozycja-w-pozycję po ujawnieniu jego listy — dopiero ono mierzy naszą metodę.
