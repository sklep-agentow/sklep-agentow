# 🔧 PLAN NAPRAW BOTÓW — etap 3 · wersja 2 (po krytyce r1: 41/100 → poprawione) · 23.08

> Wejście (zmierzone 23.08): `main`=766b4c9, CI zielone, 0 PR, stos = `main` (diff pusty).
> **Rejestr kłamie:** `test-awaria-zapisu.php` na `main` → PADA 3/6 EXIT=1 → A2-F1/F2/F3 NIE naprawione
> (#136 = inny problem). Prawdziwy stan: **23 OTWARTE + 1 W NAPRAWIE** (LS-F2, 3/4 punktów żywe).
> Dzidek 23.08: „naprawy jak najszybciej, nie kosztem efektu · bez przerostu: naprawić + lekko zabezpieczyć ·
> nie marnować kredytów · plan zatwierdza krytyk". Krytyka r1 złapała obie przynęty → ważna. Druga runda: NIE.

## 0. CEL I KONIEC ETAPU (mierzalny)
Zamknąć znaleziska rejestru tak, żeby: test, który padał, przechodzi (strażnik zostaje) · nic, co przechodziło,
nie pada · dokumentacja mówi prawdę · cudze oko raz na końcu.
**Koniec = skrypt, nie deklaracja:** `narzedzia-qa/linia-bazowa.sh koniec` na `main`@SHA uruchomiony przez koordynatorkę:
wszystkie testy wskazane w rejestrze EXIT=0 i zero regresji vs linia bazowa; rejestr bez wierszy OTWARTE **i** W NAPRAWIE
(sprawdzane komendą koordynatorki: `grep -c '| W NAPRAWIE |' audyt/REJESTR-ZNALEZISK.md` = 0 — bramki NIE zmieniamy, lekko); `bramki.sh` EXIT=0; na końcu `dowod-z-paczki.sh` +
`dowod-zachowania-*.sh` EXIT=0 NA NOWEJ PACZCE (1.5.9 / 1.3.2, bump raz, na końcu).

## 1. JAK NIE ZEPSUĆ CZEGOŚ INNEGO — lekko, ale maszynowo
⛔ Fakt: `slepa-plama-guard.py` pilnuje WYŁĄCZNIE warsztatu; w repo zlecenia nie ma żadnego hooka. Nie budujemy
nowego. Regresję trzyma 7 rzeczy:
| # | mechanizm | kiedy |
|---|---|---|
| R1 | **Linia bazowa** `narzedzia-qa/linia-bazowa.sh <etykieta>`: zrzut bazy przez KONTENER BAZY (`docker exec strona_wp_db mariadb-dump`, wzorzec `lib-stos-probny.sh:62-76`, kontrola rozmiaru > 0) → `docker cp` OBU wtyczek z HEAD → restart `segm_imap` (pamięć ulotna) → stała kolejność testów (PHP `testy/` → JS `testy/` → zamrożone `audyt/boty/testy-audytu`+`repro` z przywracaniem bazy po każdym) → przywrócenie bazy i kodu → tabela `test·EXIT·s` z SHA do `audyt/LINIA-BAZOWA-TESTOW.md` + `audyt/linia-bazowa/<etykieta>.tsv`. POMIJA jawnie: `*-proba-kontrolna.sh`, `kalibracja-mutantem.sh` (mutują kod), narzędzia (`zrzuty-panelu.js`, `svg-pomiar-tekstow.js`, `seed-demo.sh`), SKLEP (`dziennik-test.js`, `produkty-test.js`, `e2e/` — poza etapem; `dziennik-test.js` zatruwa limiter logowań 6/min). `--porownaj A B` → regresje 0→1 (EXIT=1) | **PRZED** (K0), **wieczorem** gdy buildery stoją, **NA KOŃCU**. Nie po każdej paczce. |
| R2 | **Jeden builder na wtyczkę** (`docker cp` na wspólny stos; bind-mountu NIE MA). Testy przeglądarkowe/HTTP przez **`flock /root/praca/zlecenia/wymiana/stos.lock <komenda>`** (limiter logowań i sesje są wspólne). Po `docker cp` przed testem HTTP `sleep 3` (opcache revalidate 2 s). | zawsze |
| R3 | **Test padał→przechodzi + przypadek bez wady** — kryterium „bez wady" wpisane w kartę każdej pozycji (nie wolny tekst). Dowód PRZED/PO w opisie PR (SHA + ogon wyjścia); koordynatorka powtarza test sama po scaleniu wieczorem (R1). | każdy PR |
| R4 | **Zamrożone testy audytu** (`testy-audytu/*`, `repro/*`) — builder nie edytuje. Dla KAŻDEGO w karcie stoi decyzja koordynatorki: „mierzy SKUTEK → ma przejść" albo „mierzy stary PRZYRZĄD → zostaje dowodem, strażnikiem jest NOWY plik `testy/<wtyczka>-…`". Istniejące strażniki w `testy/` utrwalające obecny kształt są WYMIENIONE w karcie (co z nimi zrobić). | każda karta |
| R5 | **`kto-wola.sh`** (30 linii): kto woła zmienioną funkcję (także po nazwie w cudzysłowie — hooki). Builder odhacza każde miejsce w PR. Ograniczenie jawne: nie widzi testów sprawdzających `strpos` treści pliku — te są w karcie (R4). | każdy PR |
| R6 | **Klasa, nie egzemplarz** + **sweep „co przestało być prawdą"** (instrukcja, readme, dok. techniczna, deklaracja prywatności, istniejące testy w `testy/`). Lista zdań w PR. | każdy PR |
| R7 | **Pliki wspólne = tylko koordynatorka:** `audyt/REJESTR-ZNALEZISK.md`, `TABLICA.md`, `boty/PONAD-SPECYFIKACJE-BOTY.md`, `boty/buduj-paczki.sh`, `boty/paczki/`, `.github/`, `narzedzia-qa/bramki.sh`. Builder pisze proponowaną zmianę w opisie PR; ja wnoszę osobnym commitem przy scaleniu. Nikt nie scala własnego PR; recenzję czytam osobną komendą. | zawsze |
Zasada dwóch prób (reset + inne podejście) · każdy PR ze świeżego `main` · bump wersji RAZ na końcu (assety = `CHATL_WERSJA`, stos testowy bez cache).
Dowody zachowania (`dowod-zachowania-*.sh`, na paczce zip, własny stos próbny): **PRZED** na istniejących paczkach 1.5.8/1.3.1 (K0) i **NA KOŃCU** na nowych; dodatkowo po scaleniu S1 (ruch poczty) na zipie zbudowanym ręcznie do `/tmp` (`zip -r` z allowlisty — `buduj-paczki.sh` pisze tylko do `boty/paczki/`, nie ruszamy go).

## 2. ROLE, RAM, STANOWISKA
- **Koordynatorka:** K0, rejestr/TABLICA, linia bazowa, scalanie, sweep wersji na końcu. Kod wtyczek — nie. Narzędzia QA (`linia-bazowa.sh`, `kto-wola.sh`) — tak.
- **builder1:** okno tmux `builder1`, worktree `/root/praca/zlecenia/sklep-agentow-b1`, teren `wtyczki/chatbot-live/` + `testy/chatbot-*`. **builder2:** okno `builder2`, `/root/praca/zlecenia/sklep-agentow-b2`, `wtyczki/segregator-maila/` + `testy/segregator-*`.
- **RAM: maks. 3 sesje naraz** (skill: dwie komfort, trzy granica; zmierzone 23.08: 3 sesje = 3×~550 MB RSS, `available` 1,75 GB, swap 1,1 GB). Próg = kolumna **`available` ≥ 1 GB**. Czat techniczny Dzidka = 4. sesja → gdy Dzidek wraca do pracy w swoim czacie, **wstrzymuję jednego buildera** (Dzidek daje znać). Playwright tylko pod `flock`, Lighthouse nigdy.
- **Weryfikator = builder po `/clear` z zamianą ról, RAZ na końcu** (wąski re-audyt tylko plików z `git diff --name-only 766b4c9..main -- wtyczki/`), nie po każdej paczce.
- Strażnik w tle: `steruj-builderami/scripts/straznik-builderow.sh`. Briefing: `audyt/BRIEFING-NAPRAWY.md` (w repo).

## 3. PACZKI — po klasie; kolejność = pieniądze/dane → potem „30 sekund"
⚠️ Linia bazowa (23.08) pokazała: `repro/*.php` i `testy-audytu/a4-*.js` to SONDY (EXIT zawsze 0) — odtwarzają, nie pilnują; strażnik = nowy plik [N]. Prawdziwe testy z EXIT=1: `test-awaria-zapisu.php`, `test-krawedzie.php`, `DK-*`, `test-a1-uprawnienia.php`, testy w `testy/`.
Legenda dowodu: **[T]** = test zamrożony ma przejść · **[P]** = test zamrożony mierzy stary przyrząd → nowy strażnik · **[N]** = nowy strażnik (znalezisko bez testu) · **[D]** = tylko dowód w PR (kosmetyka UX, bez nowego testu).
⛔ Do REJESTRU (tabela, licznik) wchodzi TYLKO to, co ma padający test. `WK-`/`T-` siedzą w sekcji **„DO ODTWORZENIA (poza licznikiem)"** rejestru; awans do tabeli = PR z EXIT=1 w opisie (ja wnoszę). Jeśli odtworzenie nie pada → adnotacja „nie odtworzono" i koniec, bez wstydu.

### builder1 — ChatBot: **C1 → C2 → C3 → C4 → C5 → C6**
**C1 — „Skąd kod wie, że się udało" (zapis bez sprawdzenia wyniku)** — pieniądze (stop-loss) i prawda dla gościa
- `A2-F1` `chatl_post_ustawienia_zapisz()` (admin.php ~1102) „zapisano" po odrzuconym zapisie → wzorzec-bliźniak `segm_post_ustawienia_zapisz()` + `segm_przekieruj_blad()` (Segregator, działa — T2 testu). **[T]** `test-awaria-zapisu.php` T1.
- `A2-F2` `chatl_przejmij()` (przejecie.php:239) `return true` bez pomiaru → odczyt stanu PO zapisie (`$wpdb->update` zwraca 0, nie false — wzorzec `segm_skrzynka_zapisz_haslo()`); **rodzeństwo w tym samym pliku:** `chatl_nowa_sesja` (~65), `chatl_oddaj_botowi` (~212), `chatl_operator_wiadomosc` (~275), sprzątanie po ciszy (~364). **[T]** T3/T4 + **[N]** `testy/chatbot-awaria-zapisu-test.php` (rodzeństwo; przypadek bez wady: przy działającej bazie przejęcie = true i stan=czlowiek — T4 już to mierzy).
- `A2-F3` `chatl_zapisz_klucz()` (ai.php:99) void → bool + wołający (admin.php ~1057) reaguje. **[T]** T5.
- Lista A2-F4 (6 miejsc z raportu A2 w ChatBocie: znacznik awarii AI, ślad wniosku RODO, licznik pułapki…) — karta S1-czytacza; objąć te, które NIE przenoszą się do tabeli w C3; resztę wymienić w „czego nie zamyka".
- Istniejące strażniki dotknięte: `testy/przejecie-test.php` (woła `chatl_operator_wiadomosc` 4×) — ma dalej przechodzić.

**C2 — Krawędzie pól + fail-open kosztowy** — pieniądze
- `KR1` puste „dobowy limit tokenów" WYŁĄCZA stop-loss; `KR2` puste „dobowy limit wywołań" → 1. ⛔ **Kryterium z zamrożonego testu DOSŁOWNIE** (`test-krawedzie.php:41-43`): *puste pole liczbowe NIE nadpisuje poprzedniej wartości i właściciel dostaje komunikat inny niż „zapisano"*. Nie „domyślne" — poprzednie. **[T]**.
- `KR6` retencja kasuje PRZED 90 dniami w strefie ujemnej → czas spójnie w strefie WP (`current_time`/`wp_date`). **[T]**; istniejący `testy/rodo-test.php`/retencja — sweep.
- `T-1` **fail-open kosztowy** (krytyk Z13, zweryfikowane): `chatl_ai_zuzycie_dnia()` (ai.php:129-150) `get_row`→`null` zarówno przy „brak wiersza", jak i przy błędzie zapytania → zera → `chatl_ai_uzycie_dzis() >= limit` = false → **AI wołane przy padniętej tabeli licznika**. Naprawa: `$wpdb->last_error` ≠ brak wiersza → przy błędzie traktuj jak limit wyczerpany (reguły, bez AI) + znacznik. Builder najpierw pisze padający test (symulacja błędu zapytania filtrem `query` jak w `test-awaria-zapisu.php`) — pada → **[N]** `testy/chatbot-stoploss-awaria-test.php` → awans do rejestru. `chatl_rate_limit()` (limiter spamu, `return true` świadome) — ZOSTAWIĆ, nie ruszać.
- Ta sama funkcja co A2-F1 → C2 startuje po scaleniu C1 ze świeżego `main`.

**C3 — Pułapka i liczniki w `wp_options` → tabela, limit ścieżki pułapki, uninstall** (klasa #7 repo)
- `LC-F2` ścieżka pułapki nielimitowana (200 żądań/7 s, 0×429) · `DK-5` licznik w opcji gubi 13/20 równoległych (wzorzec atomowy istnieje: `chatl_nonce` `INSERT…ON DUPLICATE KEY UPDATE`, A1: 61/61) · `DK-1` licznik vs lista podejrzanych = dwa źródła (14 vs 5) → jedno (tabela zdarzeń, licznik = COUNT) · `P2-Z2` `chatl_pulapka` przeżywa uninstall → po przeniesieniu: **3 miejsca** (`dbDelta` + `uninstall.php` obie ścieżki + schemat) i skasować opcję · rodzeństwo `chatl_odnotuj_odrzucony_nonce()` (P2-Z3: 30× zły nonce, 0×429) — ta sama klasa.
- ⛔ Decyzje R4 (koordynatorka): `DK-rownolegle-liczniki.sh` i `DK-dwa-zrodla-pulapka.sh` **czytają `get_option` → mierzą stary przyrząd → [P]**: zostają dowodem, strażnikiem zostaje **[N]** `testy/chatbot-pulapka-licznik-test.php` (salwa N równoległych → licznik = N; pułapka+nonce; limit ścieżki pułapki → 429 po progu; uninstall → brak tabeli i opcji). `testy/pulapka-test.php:80-87` (asercja na `get_option('chatl_pulapka')['ile']`) = istniejący strażnik w `testy/` → builder AKTUALIZUJE (R6), oraz **`pulapka-test.php` WARUNEK 2 „pułapka PRZED limitem ruchu" — ZOSTAJE prawdą**: nie przestawiać kolejności; limit dla ścieżki pułapki = OSOBNY kubeł, złapane dalej nie zjadają kubła gościa (przypadek bez wady: prawdziwy gość nie blokowany).
- Trop „kto zapłaci" dla `chatl_rate_limit` — nie dotyczy (C2 załatwia stop-loss).

**C4 — Okno czatu: WĄSKIE („30 sekund", to widzi klient klienta)**
- `A4-F1` `odpytaj is not defined` przy każdym otwarciu (widget.js:527 woła funkcję z `zbudujOkno()`) · `A4-F2` bliźniak `zatrzymajOdpytywanie` (:529). Klasa: wywołanie spoza zasięgu; wzorzec w kodzie (most `stopOdpytywania`, ścieżka Escape poprawna). ⚠️ `a4-przejecie-nie-dochodzi.js`, `a4-zamkniecie-odpytywanie.js` to **SONDY** (EXIT zawsze 0, zmierzone w linii bazowej) — służą do ODTWORZENIA, nie są strażnikami. **[N]** `testy/chatbot-widget-odpytywanie-test.js`: po przejęciu przez operatora gość widzi konsultanta BEZ drugiego pytania, zero `pageerror`; zamknięcie bąbelkiem bez błędu JS. Kalibracja: na sprzed EXIT=1.
- `A4-F4` → **naprawa = `aria-expanded="true|false"`; `aria-label="Otwórz czat" ZOSTAJE** (selektor 4 testów: `pulapka-widget.js`, `a4-*.js`). **[D]** + asercja w istniejącym `testy/pulapka-widget.js`.
- `WK-1` formularz kontaktowy nakłada się na powitanie / krzyżyk formularza zamyka CAŁY czat (3 objawy kupującego, 1 przyczyna układu) → krzyżyk formularza zamyka formularz, wpisana treść nie ginie; przypadek bez wady: zamknięcie czatu bąbelkiem/Escape dalej działa, formularz po porażce bota dalej się pokazuje. Najpierw odtworzyć (Playwright pod `flock`), dowód zrzutem. **[D]**.
- `WK-8` „dzień dobry" → „nie znam odpowiedzi": zamówienie (`01-zamowienie.md` §FRONT) każe „nie znam odpowiedzi" i zakazuje poszerzania dopasowania → **NIE silnik**; tylko wpis powitalny w danych startowych (`seed-demo.sh`/instalator) + zdanie w instrukcji „dodaj pytanie powitalne". 2 linie. **[D]**.
- WYCIĘTE (rozszerzenia zakresu / nie produkt): `<noscript>` z e-mailem (wyciek adresu), WK-9 (polityka jednego komunikatu), WK-11/WK-18 (dymek HTML5 jest w języku PRZEGLĄDARKI — stanowisko en-US; u klienta po polsku). → lista „propozycje do Dzidka" w `PONAD-SPECYFIKACJE-BOTY.md`.

**C5 — Panel ChatBota: diagnostyka i RODO mówią prawdę**
- `P2-Z1` „Przetestuj klucz" pyta `CHATL_AI_MODEL` zamiast `chatl_ai_model()` i na 200 kasuje `chatl_blad_ai` → ten sam model co bot; diagnostyka nie gasi alarmu (gasi dopiero udana odpowiedź gościowi). Sonda `audyt/boty/proba-model.php` → **[N]** `testy/chatbot-test-klucza-test.php`. Przypadek bez wady: „Przetestuj klucz" z poprawnym kluczem i domyślnym modelem dalej mówi „działa".
- `P2-Z4` eksporter RODO `rodo.php:262` `operator`/`system` → „Bot" → 4 role nazwane; sweep innych map roli→napis (CSV rozmów, panel). Sonda `proba-rodo.php` → **[N]** `testy/chatbot-rodo-role-test.php`.
- `A6-F1` deklaracja prywatności milczy o 6 ostatnich wymianach (`CHATL_AI_PAMIEC_WYMIAN`) → `INSTRUKCJA.md` §9 + `readme.txt`; zgłosić koordynatorce zdanie do strony produktu. **[T]** `testy/a6-deklaracja-vs-ladunek-ai.php` (to jest w `testy/`, przechodzi po dopisaniu zdania).
- `A4-F3` (ChatBot): `import.php:739` pole pliku, `admin.php` ~824 kolor tekstu, ~881 własny model — `<label for>`. **[D]** + asercja w `a4-widget-konsola-kontrast.js`? (zamrożony — nie) → asercja w `testy/wyglad-panel.js` jeśli tanie, inaczej [D].

**C6 — Retencja i cron ChatBota** (dane osobowe)
- `A3-1` `chatl_sesje` bez kasowania → retencja + kasownik RODO. **[T]** `testy/chatbot-sesje-retencja-rodo-test.php`. Przypadek bez wady: świeższe sesje zostają (dowód-zachowania D6).
- `A3-2` zombie cron po uninstall → `wp_clear_scheduled_hook` w obu ścieżkach. **[T]** `testy/uninstall-zombie-cron-test.php`.
- (T-2 ostrzeżenie DISABLE_WP_CRON w ChatBocie — WYCIĘTE, Segregator ma; nie przesadzamy.)

### builder2 — Segregator: **S1 → S2 → S3 → S4 → S5**
**S1 — Przebieg skrzynki: prawda o ruchu poczty** (NAJCIĘŻSZA; jeden PR, nie grupować)
- `LS-F2` (W NAPRAWIE, 3/4 żywe): poczta przenoszona PRZED zapisem historii; przy padzie bazy panel „przeniesionych=1 bledow=0", historia pusta, cofanie martwe → **zapis zamiaru PRZED ruchem** (wiersz „w toku" → MOVE → „przeniesiono" po potwierdzeniu); porażka zapisu = błąd przebiegu widoczny, nie sukces. ⚠️ `repro/lamacz-baza-i-srodowisko.php` (L1) to SONDA (EXIT zawsze 0) — odtworzenie. **[N]** `testy/segregator-przebieg-historia-test.php`: pad zapisu historii → przebieg zwraca błąd i `przeniesionych` nie kłamie; kalibracja na sprzed EXIT=1.
- `LS-F1` cofanie melduje sukces dla nieruszonych (`UID MOVE` nieistniejącego UID = OK wg RFC 3501 §6.4.8) → sprawdzać SKUTEK (`COPYUID` gdy serwer ma UIDPLUS — **sprawdzić w GreenMail 2.1.3**; jeśli brak: porównanie stanu folderu przed/po `UID SEARCH`), liczyć tylko faktycznie cofnięte; `T-3` `UIDVALIDITY` zapisywać przy ruchu, sprawdzać przy cofaniu (ta sama naprawa). ⚠️ `repro/lamacz-cofanie.php` = SONDA (EXIT 0) — odtworzenie. **[N]** `testy/segregator-cofanie-test.php`: cofnięcie melduje TYLKO faktycznie wrócone; mail przeniesiony ręcznie gdzie indziej → nie liczony jako cofnięty; kalibracja na sprzed EXIT=1.
- `DK-6` zamek wpuszcza dwie operacje → ⛔ **zamek ZOSTAJE w `wp_options`** (zamrożony `DK-blokada-segregatora.php` symuluje proces B przez INSERT do options — test mierzy ten kształt): atomowo `INSERT IGNORE` do `wp_options` z kontrolą `rows_affected===1` (pamięć: `add_option` ≠ mutex) + `T-4` TTL zamka (wartość = czas; starszy niż limit przebiegu → przejmij + ostrzeżenie w panelu) — zabity proces przestaje wyłączać cron na zawsze. **[T]** `DK-blokada-segregatora.php` + **[N]** `testy/segregator-zamek-ttl-test.php` (T-4, jeśli pada).
- Przypadek bez wady / golden: `dowod-zachowania-segregator.sh` na zipie do `/tmp` po scaleniu (na sucho nie rusza; na żywo przenosi i cofa; retencja). Istniejące strażniki: `segregator-skrzynka-test.php` — ma przechodzić.

**S2 — „Skąd kod wie, że się udało" + krawędzie (Segregator)**
- `A2-F3`-bliźniak `segm_zapisz_klucz()` (ai.php:159) void → bool + reakcja wołającego; miejsca segregatorowe z listy A2-F4 (znacznik awarii AI). **[N]** `testy/segregator-awaria-zapisu-test.php` (pada na sprzed).
- `KR4` puste „port"/„limit" → 1 po cichu → kryterium jak C2: poprzednia wartość zostaje + komunikat ≠ „zapisano". **[T]** `test-krawedzie.php` (KR4).

**S3 — Ekran skrzynki: pierwsze minuty właściciela** (UX, dowód w PR)
- `WK-5` „Sprawdź połączenie" szary bez wyjaśnienia (trzeba najpierw zapisać) → przycisk mówi dlaczego albo sam zapisuje. **[D]**.
- `WK-15` „hasło aplikacji" bez wyjaśnienia na ekranie → akapit + odsyłacz do sekcji instrukcji po TYTULE. **[D]**.
- `WK-17` komunikat „Zapisano ustawienia połączenia" ISTNIEJE (`admin-skrzynka.php:472`, wyświetlany `:97`) — kupujący go nie zobaczył → **najpierw odtworzyć** (transient per użytkownik? kolejność ładowania? JS WP przenosi `.notice`?); naprawa tylko, gdy to nasza wada; inaczej adnotacja. **[D]**.
- WYCIĘTE: WK-16 (panel JUŻ pokazuje „Najbliższy przebieg"/„zadanie NIE jest zaplanowane" — kupujący nie podpiął skrzynki).

**S4 — Komunikaty Segregatora po ludzku i prawdziwie** (UX, dowód w PR)
- `WK-3` „KLUCZ API ZOSTAŁ ODRZUCONY" u właściciela bez klucza → skąd klucz (ustawienia/stała), co zrobić; brak klucza ≠ odrzucony. Istniejący `testy/blad-klucza-test.php` — rozszerzyć asercją (tanie) **[N-lekki]**.
- `WK-4` „✔ jest / Nieobecne" w jednej linii tabelki zgodności → spójny wiersz. **[D]**. `WK-13` „kod 111" → po ludzku, kod w szczegółach. **[D]**. `A4-F3` (Segregator) `<select name="szyfrowanie">` etykieta. **[D]**.
- WYCIĘTE: WK-9 (polityka jednego komunikatu) → propozycje.

**S5 — RODO Segregatora: co wychodzi do AI**
- Fakt (krytyk, zweryfikowany): AI **nie działa na skrzynce** (przebieg = same reguły); `segm_ai_zapytaj()` woła `silnik.php` (tryb AI na próbce/klasyfikacji). `A3-3` → (a) deklaracja w `INSTRUKCJA.md`, `readme.txt`, `DOKUMENTACJA-TECHNICZNA.md` mówi WPROST: co i kiedy wychodzi do dostawcy (nadawca, temat, treść?) w trybie AI, a na skrzynce nic nie wychodzi; (c) **[N]** `testy/segregator-deklaracja-vs-ladunek-ai.php` (wzór: `testy/a6-deklaracja-vs-ladunek-ai.php`).
- (b) **L8 = decyzja Dzidka** (zakres): „AI na skrzynce" to NOWA funkcja → rekomendacja: NIE robimy, piszemy wprost na stronie produktu i w instrukcji „na skrzynce pracują reguły". Czeka na jego słowo; nie blokuje S5(a)(c).
- Minimalizacja danych (domena zamiast adresu) → propozycje po oddaniu.

### koordynatorka
**K0 (przed startem builderów, JEDEN PR `chore: etap 3 — prawda w rejestrze, przyrząd, briefing`):**
1. Rejestr: A2-F1/F2/F3 → OTWARTE (licznik 20→23 świadomie, dowód EXIT=1 w opisie); A4-F1/F2/LS-F1/LS-F2 kolumna test → „BRAK — do dopisania (sonda…)”, bo `a4-*.js` i `repro/*.php` to SONDY z EXIT zawsze 0 (dług 9→11 świadomie); nowa sekcja **„DO ODTWORZENIA (poza licznikiem)"**: WK-1,3,4,5,8,13,15,17 (ze zdaniem dziennika) + T-1,T-3,T-4 + A3-3; bramka `znaleziska` liczy też W NAPRAWIE (1 linia + próba kontrolna); sekcja „PROPOZYCJE DO DZIDKA" → `PONAD-SPECYFIKACJE-BOTY.md` (noscript, WK-9, WK-11/18, minimalizacja danych, L8).
2. `audyt/PLAN-NAPRAW.md` (ten plik), `audyt/BRIEFING-NAPRAWY.md`, `narzedzia-qa/kto-wola.sh`, `narzedzia-qa/linia-bazowa.sh` (z mariadb-dump przez `strona_wp_db`), scalenie gałęzi `koord/przelot-czlowieka` (`PRZELOT-CZLOWIEKA.md` nie jest na main — F4).
3. Hasło `admin_roboczy` odświeżone (testy panelu). **Linia bazowa PRZED** (`linia-bazowa.sh baza-<sha>`), dowody zachowania PRZED na paczkach 1.5.8/1.3.1 (EXIT do `LINIA-BAZOWA-TESTOW.md`).
4. Worktree b1/b2 ze świeżego `main` po scaleniu K0, okna tmux `builder1`/`builder2` (krytyk → `/clear` → builder1), strażnik w tle, TABLICA.
**K1 (A6-F2):** `audyt/boty/material/02-fakty-srodowiska.md` kłamie (wersje 1.2.1; „brak skrzynki/czatu operatora") → sweep całego `material/` PRZED re-audytem; test: wersje z materiału == nagłówki wtyczek.
**K2:** strona produktu — zdania z A6-F1/S5 po scaleniu.
**K3 wieczorem:** linia bazowa vs poprzednia (`--porownaj`) → regresja = revert PR + diagnoza; statusy w rejestrze: OTWARTE → (PR scalony + test EXIT=0 w linii bazowej) → ZAMKNIĘTE (ja, nie builder; „W NAPRAWIE" tylko gdy PR otwarty — zgodnie z definicją rejestru).

## 4. CYKL BUILDERA — w `BRIEFING-NAPRAWY.md` słowo w słowo (skrót)
gałąź ze świeżego `main` → karta paczki + raport → `docker cp` swojej wtyczki → test znaleziska PADA (jeśli nie: STOP, meldunek) → naprawa klasy → `kto-wola.sh` → test przechodzi + przypadek bez wady + nowy strażnik tam, gdzie karta mówi [N] (kalibracja: sprzed EXIT=1) → swoje testy (JS/HTTP pod `flock`, `sleep 3` po cp) → sweep R6 → `bramki.sh` EXIT=0 → PR z szablonem (propozycja zmian rejestru/TABLICY w opisie, nie w plikach) → meldunek (PR · co · czym · co zaskoczyło) → następna paczka bez czekania → po scaleniu `/clear`.

## 5. PO PACZKACH
1. Wąski re-audyt: 2 weryfikatorzy po zamianie ról (buildery po `/clear`), tylko pliki z diffu od 766b4c9, materiał po K1, kalibracja jednym mutantem (`/root/praca/warsztat/.claude/skills/sprawdzanie-zlecenia/kalibruj.sh`).
2. Sweep dokumentacji (`dokumentacja-zgodnosc*.sh`, `instrukcja-liczby-segregator.sh`) · `PRZELOT-CZLOWIEKA.md` 16 punktów (Dzidek/agent — jego decyzja).
3. Bump zbiorczy 1.5.9 / 1.3.2 (4 miejsca + CHANGELOG) → `buduj-paczki.sh` → `paczka-zgodnosc.sh` EXIT=0 → `dowod-z-paczki.sh` + `dowod-zachowania-*.sh` EXIT=0 → `linia-bazowa.sh koniec` bez regresji.
4. Sklep (lista Dzidka).

## 6. DO DZIDKA (tylko jego działka, zbiorczo, gdy wróci)
- **L8**: AI na skrzynce (nowa funkcja) czy napis „na skrzynce same reguły" (rekomendacja: napis).
- **Ręczny przelot 16 punktów** — Ty czy agent z hasłem roboczym.
- **RAM**: gdy pracujesz w czacie technicznym, daj znać — wstrzymam jednego buildera.
- Login Claude Code wygasa ~26.08 (`/login`).

## 7. CZEGO TEN PLAN NIE ZAŁATWIA
Testy nie chodzą w CI (linia bazowa = rytuał ręczny, wieczorem) · tryb AI na żywo niesprawdzany automatycznie · WK/T to odtworzenia, mogą nie paść · A2-F4 (6 miejsc) z odczytu kodu · GreenMail może nie mieć UIDPLUS (LS-F1 wtedy przez porównanie stanu) · sklep poza etapem · wspólny stos = ryzyko resztkowe mimo `flock`.
