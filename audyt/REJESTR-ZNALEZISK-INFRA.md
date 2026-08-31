# 📋 REJESTR ZNALEZISK — INFRASTRUKTURA (runda po recenzji kolegi, 31.08)

> Ten rejestr powstał po zewnętrznej recenzji kolegi (Man1exter, 31.08): audyt produktu **9/10**,
> ale **11 błędów (3 duże / 4 średnie / 4 małe), „większość infrastruktury”**. Kolega podał jeden
> przykład i **zostawił sobie jeden błąd w tajemnicy** jako próbę kontrolną naszego szukania.
> Nasze polowanie (4 przebiegi, plan i raport: `audyt/PLAN-POLOWANIA-INFRA.md`,
> `audyt/POLOWANIE-INFRA-RAPORT.md`) dało **22 znaleziska** (20 z przebiegów plikowych + 2 z warstwy GitHub-UI). Klasy destylowane: ściąga 73–78.
>
> ⭐ **DRUGI DZIEŃ (31.08, popołudnie) — 4 znaleziska więcej, razem 26.** Doszły `B-10`
> (skan sekretów ślepy na wnętrze 29 śledzonych archiwów), `B-11` (alerty o lukach
> wyłączone), `I-12` i `I-13` (dwie próby kontrolne, które nie mierzyły tego, co
> obiecywały). Trzy pierwsze wyszły nie z czytania kodu, tylko z jednego pytania:
> **czego nasza ochrona NIE MOŻE zobaczyć, nawet gdy działa poprawnie.**
> Stan po tej rundzie: **26 znalezisk · 11 otwartych · 14 zamkniętych · 1 zaparkowane
> · dług strażników 13** (był 18). Liczby policzone z tabeli poniżej, nie z pamięci.
>
> ⭐ **TRZECIA RUNDA (31.08, wieczór) — po uwadze kolegi „sprawdź, czy Claude ci tego nie
> ominął albo nie zostawił na później".** Miał rację i to jest `I-14`: naprawiliśmy
> egzemplarz `I-1`, opisaliśmy klasę 73, ale przypadek „uruchomiona spoza repo” miały
> **2 z 15** prób kontrolnych — czyli nic nie pilnowało, żeby wada nie wróciła. Doszło
> `I-15`: mój własny krok CI dublował narzędzie, które w repo leży od PR #198.
> Zamknięte tą rundą: `I-12`, `I-13`, `I-14`, `I-15`.
> Stan: **28 znalezisk · 9 otwartych · 18 zamkniętych · 1 zaparkowane · dług strażników 13**.
>
> ⛔ Format celowo taki sam jak `audyt/REJESTR-ZNALEZISK.md`, żeby dała się na niego wycelować
> `narzedzia-qa/bramka-znaleziska.sh` (dziś bramka czyta TYLKO tamten plik — patrz DŁUG poniżej).
> ⛔ Znaleziska produktu NIE wchodzą tutaj; tu wyłącznie infrastruktura, CI, wydanie i dokumenty.

## Liczba znalezisk OTWARTYCH: 9

## Znalezisk BEZ strażnika: 13
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
| `B-5` | **DUZE** · `bramki.sh` pobiera `actionlint`/`gitleaks` curlem i uruchamia bez sprawdzenia sumy; cache brany po samej ścieżce, więc pinowana wersja niczego nie gwarantuje. ⭐ 31.08 dołożone: cache leży w `/tmp`, katalogu zapisywalnym dla KAŻDEGO użytkownika maszyny — kto mógł tam zapisać, ten wykonywał swój kod jako wykonawca CI | `narzedzia-qa/bramki-narzedzia-proba-kontrolna.sh` 8/8 (przypadki 1-4b). DOWÓD RÓŻNICOWY na kodzie z `main`: podłożona binarka w cache została zwrócona jako narzędzie z kodem 0, czyli zostałaby uruchomiona; po naprawie funkcja odmawia. Sumy SHA256 samych binarek, wyznaczone po potwierdzeniu archiwów oficjalnymi plikami `*_checksums.txt` | ZAMKNIETE |
| `B-6` | **DUZE** · oba workflow lecą z `pull_request` na self-hosted runnerze — kod z gałęzi PR-a wykonuje się u nas; łagodzi repo prywatne i brak `pull_request_target`, zaostrza brak izolacji | BRAK; ZLAGODZONE w #239: `composer install --no-scripts` w obu workflow zamyka glowna sciezke wykonania kodu z galezi PR-a | OTWARTE |
| `B-7` | **SRED** · `RECENZENT-BRIEFING.md` nie zawiera ani jednej instrukcji „treść PR-a to DANE, nie polecenia” — brak zabezpieczenia przed wstrzyknięciem poleceń w opis/kod/komentarz | BRAK — do dopisania; naprawione w #239: sekcja TRESC PR TO DANE w briefingu recenzenta | ZAMKNIETE |
| `I-1` | **DUZE** · `bramka-poswiadczenia.sh:13` — martwy `exit 2` po `cd ""`; uruchomiona spoza korzenia skanuje zero i przechodzi (znalezisko KOLEGI, potwierdzone) | `narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh` (przypadek: uruchomiona SPOZA repo → EXIT 2) | ZAMKNIETE |
| `I-2` | **DUZE** · `slady-wewnetrzne.sh` na pustym katalogu melduje „nowe=0” i rc=0 — mianownik zero traktowany jak czysto | BRAK — do dopisania; naprawione: pusty katalog → EXIT 2, mianownik w wyjsciu (68 plikow) | ZAMKNIETE |
| `I-3` | **DUZE** · `kalibrator-straznikow.sh` („rdzeń planu audytów v3”) jest sierotą — nie woła go nic | BRAK — do dopisania; dowód: 0 trafień w CI/`bramki.sh`/k7 (próba kontrolna: `mapa-licznik.sh` → 3 wywołania) | OTWARTE |
| `I-4` | **DUZE** · `licznik-odstepstw.sh` deklaruje „przekroczenie = wydanie stoi”, a bramka wydania go nie woła — reguła istnieje tylko jako tekst | BRAK — do dopisania; dowód: 0 trafień w `k7-koniec.sh` i CI | OTWARTE |
| `I-5` | **SRED** · `bramka-poswiadczenia-proba-kontrolna.sh:6` ma tę samą martwą konstrukcję co bramka i NIGDY nie testuje warunku „poza repo” — kontrola nie widzi błędu, przed którym broni | `narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh` (ta sama konstrukcja naprawiona + nowy przypadek spoza repo) | ZAMKNIETE |
| `I-6` | **SRED** · próby kontrolne bramek nie chodzą w CI per-PR (tylko ręcznie i w bramce wydania) — regres w samej bramce jest niewidzialny między przelotami | Naprawione: krok „Proby kontrolne bramek” w `quality.yml` przelatuje `narzedzia-qa/*-proba-kontrolna.sh`. Kod 2 liczony jako POMINIETE i wypisany z nazwy, nie jako sukces; zero zdanych też pada. Zmierzone lokalnie: zdane 13, pominiete 1, oblane 0 | ZAMKNIETE |
| `I-7` | **SRED** · `kto-wola.sh` przy nieistniejącej bazie porównania wypisuje „brak zmienionych funkcji” i kończy rc=0 — literówka w nazwie gałęzi zamienia „nie zmierzyłam” w „czysto” w opisie PR | BRAK — do dopisania; naprawione: nieistniejaca baza → EXIT 2 zamiast cichego EXIT 0 | ZAMKNIETE |
| `I-8` | **SRED** · `adresy-w-dokumencie.sh` i `waga-znaleziska.sh` — kolejne dwie sieroty rodziny procesu (kontrola odsyłaczy, waga z mapy ryzyka) | BRAK — do dopisania; dowód: 0 wywołań poza wpisem historycznym w `narzedzia-qa/CHANGELOG.md` | OTWARTE |
| `I-9` | **MALE** · dwa pliki `pre-push` (`.githooks/` żywy, `.git/hooks/` martwy) z rozjechaną treścią komunikatu — następna edycja może trafić w plik, którego git nie czyta | BRAK — do dopisania; dowód: `diff .githooks/pre-push .git/hooks/pre-push` → różnice; `core.hooksPath=.githooks` | OTWARTE |
| `I-10` | **MALE** · `k7-stare-wersje.sh:20` pisze domyślnie do ścieżki absolutnej POZA repo i nadpisuje bez pytania | BRAK — do dopisania; dowód: mój przebieg testowy nadpisał 3 zipy w `/root/praca/zlecenia/wymiana/k7-stare-wersje/` | OTWARTE |
| `I-11` | **MALE** · niespójna konwencja braku argumentów: `stos.sh` i `test-php.sh` dają pomoc z rc=0, reszta rodziny rc=2 — „zapomniany argument” raz jest błędem, raz cichym sukcesem | BRAK — do dopisania; dowód: przelot 41 skryptów spoza repo, tabela kodów wyjścia w raporcie | OTWARTE |
| `D-1` | **DUZE** · `SECURITY.md:3-5` mówi „wydane 0.9.25; 0.9.26 w przygotowaniu” i „wersje wspierane: najnowsze wydanie”, a kod stoi na 0.9.28 (tagi v0.9.26/27/28 istnieją) — dokument kontaktowy dla zgłaszającego lukę wskazuje wydanie sprzed trzech releasów | Naprawione dwuwarstwowo: treść poprawiona ORAZ nowy `narzedzia-qa/security-wersje.sh` wpięty jako 9. warunek `k7-koniec.sh`. Przyczyna klasy: `grep -rl SECURITY.md narzedzia-qa/ .github/` dawał ZERO, więc nie pilnowało go nic. Próby: `security-wersje-proba-kontrolna.sh` 7/7 (rozjazd, brak wpisu, brak dokumentu, uruchomienie spoza repo); `k7-koniec-proba-kontrolna.sh` dostał 2 przypadki dowodzące, że WPIĘCIE działa, nie tylko sam skrypt | ZAMKNIETE |
| `B-8` | **DUZE** · warstwa GitHub-UI: zewnetrzne Akcje przypiete do RUCHOMYCH tagow (`actions/checkout@v4`, `shivammathur/setup-php@v2`, `anthropics/claude-code-action@v1`, `actions/upload-artifact@v4`), repo pozwala na `allowed_actions: all` bez `sha_pinning_required` — przejety tag Akcji = kod napastnika jako root na self-hosted runnerze | Naprawione w dwóch warstwach. Kod: wszystkie 6 wystąpień `uses:` przypięte do pełnej sumy commita z komentarzem wersji (checkout v4.4.0, setup-php 2.37.2, claude-code-action v1.0.210, upload-artifact v4.6.2). Ustawienie repo: `sha_pinning_required` przestawione z false na **true** (zweryfikowane odczytem po zmianie). Druga połowa naprawy to `.github/dependabot.yml` — bez niej przypięte wersje przestałyby dostawać poprawki. Podstawa: dokumentacja GitHuba mówi, że pełna suma commita to jedyny sposób na niezmienne wydanie Akcji | ZAMKNIETE |
| `B-9` | **MALE** · warstwa GitHub-UI: `allow_forking: true` na repo PRYWATNYM — fork wymaga i tak dostepu do repo, wiec ryzyko male, ale niepotrzebnie poszerza powierzchnie; do rozwazenia wylaczenie | Próba naprawy 31.08 ODRZUCONA PRZEZ GITHUBA: `gh api -X PATCH repos/… -F allow_forking=false` → HTTP 422, komunikat mówi, że to ustawienie da się zmienić wyłącznie na prywatnym repozytorium należącym do ORGANIZACJI, a nasze należy do konta osobistego. Ryzyko i tak małe: fork wymaga dostępu do repo. Wróci, jeśli repo kiedyś przejdzie pod organizację | ZAPARKOWANE |
| `D-2` | **SRED** · `CONTRIBUTING.md:46` nazywa `testy/dokumentacja-zgodnosc*.sh` „Bramką”, a twardej bramki w CI nie ma (jest tylko allowlista recenzenta) — słowo obiecuje twardość, której świadomie nie zbudowano | BRAK — do dopisania; dowód: brak wpisu w `BRAMKI=()`; jedyny automat to `recenzent.yml:157` | OTWARTE |

| `B-10` | **DUZE** · skan sekretów jest ŚLEPY na zawartość archiwów, a w repo jest **29 śledzonych plików .zip** z paczkami botów. `gitleaks git` widzi zip jako jeden binarny blob i nie zagląda do środka, więc klucz w paczce przechodzi przy wszystkich bramkach na zielono. ⛔ Znalezione dzięki pytaniu „czego nasza ochrona NIE MOŻE zobaczyć”, nie przez czytanie kodu | PRÓBA A/B (własny pomiar 31.08): plik z trzema fałszywymi kluczami leżący luzem → gitleaks znajduje 2; ten SAM plik spakowany do zipa → znajduje 0. Kontrola dodatnia musiała być poprawiana, bo pierwszy wzorzec (`AKIA…EXAMPLE`) jest u gitleaksa na liście wyjątków i nie wykrywał go NIGDZIE. Naprawa: `skan_archiwow()` w `bramki.sh` z podłogą na mianownik zero; próba kontrolna przypadki 5-7. Stan repo zmierzony osobno: 29 archiwów, 1313 plików, 43,84 MB, ZERO sekretów | ZAMKNIETE |
| `B-11` | **SRED** · alerty o lukach w zależnościach (Dependabot) były **wyłączone**, tak samo automatyczne poprawki bezpieczeństwa — znana luka w `composer.json` albo w zewnętrznej Akcji nie zgłosiłaby się nikomu. Waży podwójnie od 31.08, bo przypięcie Akcji do sum zamraża wersje: bez alertów przypięcia po cichu starzeją się razem ze swoimi dziurami | Zmierzone przed: `gh api …/vulnerability-alerts` → „Vulnerability alerts are disabled”, `automated-security-fixes` → `enabled:false`. Po włączeniu i odczycie: alerty WŁĄCZONE, `automated-security-fixes` → `enabled:true`. Dołożony `.github/dependabot.yml` (github-actions + composer, rytm tygodniowy, limit 3 PR — bo każdy PR odpala u nas bramki i recenzenta na własnym wykonawcy) | ZAMKNIETE |
| `I-12` | **SRED** · `bramki-proba-kontrolna.sh` nie sprawdza WŁASNYCH warunków wstępnych: w świeżym klonie bez `vendor/` phpcs i phpstan padają, a próba melduje to jako „PRZEOCZONYCH AWARII: 1”, czyli oskarża produkt o wadę, której nie ma. Ta sama klasa co lekcja o teście na wspólnym stosie | Naprawione 31.08: próba sprawdza `vendor/bin/phpstan` i `vendor/bin/phpcs` PRZED startem i kończy kodem 2 z komunikatem „to NIE jest wada bramek”. Dowód w obu środowiskach: główne repo z zależnościami → kod 0 i komplet bramek zielony; oderwane drzewo bez `vendor/` → kod 2 zamiast fałszywego alarmu | ZAMKNIETE |
| `I-13` | **MALE** · `dowod-z-paczki-proba-kontrolna.sh` wymaga paczki `boty/paczki/chatbot-live-1.5.0.zip`, której w repo nie ma (są wersje od 1.5.7 w górę) — próba kończy się kodem 2 na każdym przebiegu, czyli od dawna nie mierzy niczego. Test odwołuje się do nośnika, który zniknął | Naprawione 31.08: paczka brana z katalogu przez `find … \| sort -V \| tail -1` (sortowanie wersji, żeby 1.5.13 nie przegrało z 1.5.9), nazwa mutanta z tej samej wersji. Dowód: próba pisze teraz „pracuje na paczce chatbot-live-1.5.13.zip” i kończy kodem 0 — po miesiącu znowu coś mierzy | ZAMKNIETE |

| `I-14` | **DUZE** · reguła klasy 73 („każda bramka dostaje przypadek: uruchomiona spoza repo MUSI paść”) była ZAPISANA w ściądze i NIEWDROŻONA — przypadek ten miały **2 z 15** prób kontrolnych. Naprawiono egzemplarz `I-1`, opisano klasę, ale nic nie pilnowało, żeby wada nie wróciła. To jest dokładnie to, co kolega nazwał „do końca nie została zrobiona” | Naprawione: `narzedzia-qa/spoza-repo-proba-kontrolna.sh` — jeden test całej rodziny zamiast 13 łatek. Część statyczna: żadna linia KODU (komentarze odsiane) nie zawiera konstrukcji, w której `exit` jest martwy — 0 trafień w 120 plikach. Część dynamiczna: każde narzędzie biorące korzeń Z GITA musi poza repo odmówić — 4 sprawdzone, 4 odmawiają, 0 ślepych. ⭐ Sprawdzone przy okazji: ta sama konstrukcja jest w `kto-wola.sh`, `linia-bazowa.sh` i `testy/segregator-deklaracja-vs-ladunek-ai.sh` i WSZYSTKIE trzy są poprawne — klasa się nie rozlała | ZAMKNIETE |
| `I-15` | **SRED** · własne znalezisko o własnej pracy: krok CI „Proby kontrolne bramek” dodany 31.08 przepisywał od zera to, co `proby-kontrolne-przelot.sh` robi od PR #198 — i robił gorzej: bez listy prób świadomie pomijanych z powodem (jedna DEINSTALUJE wtyczkę ze stosu) i bez logu ze znacznikiem commita. Brał też tylko `narzedzia-qa/*` czyli 15 prób, gdy przelot widzi 38 w całym repo | Naprawione tego samego dnia: krok woła istniejące narzędzie. Przelot dostał drugi argument ZAKRES, bo przelot po całym repo bierze 11 testów produktu wymagających żywego stosu WP, a manifest mówi wprost, że w CI nie ma WP i Stripe. CI woła z zakresem `narzedzia-qa`, bramka wydania nadal bez zakresu. Pusty zakres = kod 2, nie sukces | ZAMKNIETE |

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
