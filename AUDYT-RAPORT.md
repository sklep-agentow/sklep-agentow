# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.42` (delta nad `v0.9.28`; ChatBot LIVE 1.5.17, Segregator maila 1.3.7)

Data: 2026-09-05 · Zakres: DRUGA RUNDA AUDYTU po egzaminie z 31.08 (próba 1 niezaliczona) — 69 PR-ów scalonych od tagu `v0.9.28`
(`#229`–`#298` + ten), w tym: obszary audytu A (paczka i front, `#291`, 7 rund), B (rejestry, `#288`/`#293`), C (prawne i retencja, `#294`,
7 rund + `#299` domykający W1 r7), D (stanowisko: WP-Cron, limity uploadu, łapacz maili — `#289`), S (Segregator MIME, `#297`), E (CI na wykonawcach GitHuba, `#285`/`#287`/`#290`);
naprawy produktu z przelotów po pytaniu (`#249`, `#250` przycisk Zapłać — sklep NIE SPRZEDAWAŁ, `#254`, `#255`, `#256`, `#258` migawka produktu + golden,
`#259`, `#263`, `#264`, `#268`, `#270`, `#273`, `#274`, `#275`, `#278`, `#283`, `#298`); łańcuch dostaw (`#239`, `#243`); kalibracja L1/L5 zamykająca
FAIL 2 bramki wydania (`#279`); rejestr INFRA uzgodniony ze stanem (`#241`, `#267`, `#277`, `#288`) ·
KRYTYCZNE: 0 · DUZE: 0 · (liczniki dotyczą kodu wtyczek; w rejestrze INFRA 5 DUŻE otwarte = narzędzia `I-3`/`I-4`, proces `W-1`/`W-2`, decyzja `F4-F1` — lista w delcie niżej) ·
kontroli FAIL: 1 (z 14) · **K7: NO-GO · EXIT=1** (`k7-koniec.sh` uruchomiony 5.09 na tej gałęzi, HEAD `a14f191`; powody FAIL w delcie niżej — log przelotu z `e502128` jest STARSZY niż `#299` i niż zmiany narzędzi w tym PR-ze, a 7 prób NIE ZMIERZONO przy suficie 0; orzeczenie GO wymaga przelotu na finalnym `main` przed próbą 3).


Ten plik jest **wskaźnikiem maszynowym** dla bramki oddania (hook `oddanie-guard` czyta liczniki
w nagłówku). Treść audytu — liczby z komendami, decyzje z powodami, długi i miejsca dowodów —
jest w jednym miejscu: **`audyt/RAPORT-ODDANIA.md`** (§6 = dopisek 0.9.24, §7 = dopisek 0.9.25 + K10, §8 = dopisek 0.9.26). Rubryka GOTOWE:
`rubryka-GOTOWE.md` (✅ 6 · 🐞 5 · ⛔ 1 — #12 drugi człon, oddanie decyzją; **bez zmian przy 0.9.25**: kryteria #1 (płatność
i domknięcie) i #2 (mail) przechodzą tymi samymi strażnikami, rozszerzonymi — ścieżka hostowana zostaje zapasem, więc dowody
L1-11 dalej opisują żywą ścieżkę). Rejestr znalezisk: `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` (0 KRYTYCZNE/DUŻE otwartych,
0 DO WERYFIKACJI). Raporty audytorów: `audyt/sklep/raporty/` (15 plików, 0 odrzuconych werdyktów).
Tester niezależny K10: `audyt/K10-INSTALACJA-OD-ZERA-0.9.24.md` (1 wada produktu → naprawiona w 0.9.25).

Dwie kontrole przed oddaniem, których żąda bramka: krytyk = rundy recenzji `#213` ×6, `#214` ×3, `#220` ×3, `#221` ×1,
**`#222` ×4**, **`#225` ×4** (r1: 2 WAŻNE, r2: 1 WAŻNA, r3: 3 DROBNE wzięte, r4: 1 WAŻNA o zdaniu w readme → 0.9.27 przez zamek paczki), **`#226` ×3** (8 uwag WAŻNYCH „tekst SECURITY vs kod” — wszystkie naprawione z plikiem i linią); audytor spójności =
`narzedzia-qa/k7-koniec.sh` (13 kontroli; przelot prób — log `audyt/sklep/PROBY-KONTROLNE.log`, sha w sekcji delty niżej) + `narzedzia-qa/paczka-zgodnosc.sh` (zip = repo co do sumy)
+ `narzedzia-qa/pakuj-dla-klienta.sh` (17 kontroli; sumy paczek 0.9.27 w notatce wydania i Release).

Delta 0.9.25 sprawdzona dodatkowo: strażnik maila 27/27 (C3, C3b — dane do faktury), strażnik reconciliation
`testy/reconciliation-wynik-i-przerwa-test.php` 6/6 (na kodzie 0.9.24 pada w całości), E2E ścieżki zapasowej z danymi faktury
(zam #9901178, mail z blokiem DANE DO FAKTURY), E2E osadzony z prawdziwymi kluczami do kliknięcia „Zapłać” (dalej hCaptcha
Stripe — kod 3, dowód ręczny), `bramki.sh` 10/10, zgodność dokumentacji 0 rozjazdów, próba kontrolna 21/21.

Delta 0.9.26 sprawdzona: strażnik `testy/seo-canonical-test.php` kroki A–G (produkt, lista, gospodarz z `rel_canonical`, cudza
strona, 404, pusta opcja sklepu/„Dziękujemy” — meta, canonical, assety), próba kontrolna z 3 mutantami (stan sprzed naprawy,
„drukuje zawsze”, `is_page( 0 )` bez bramki) — każdy złapany, czysty kod EXIT=0; recenzent odtworzył mutanty własną sondą
na stosie; żywy HTML na motywie gospodarza; `grep -rnE 'is_(page|single|singular)\(' wtyczki/` → tylko pomocnik;
`bramki.sh --tryb pelny` 10/10; zgodność dokumentacji 0 rozjazdów; `paczka-zgodnosc.sh` EXIT=0. Przelot prób na `e95de75`
(log w repo): 31 uruchomionych, 5 z EXIT≠0 — **podzbiór zestawu 6 z logu 0.9.25** (`dowod-z-paczki` EXIT=2, `blad-klucza`,
`ip-za-proxy`, `nonce`, `pulapka` — próby ChatBota na tym stosie, §7; `przejecie` tym razem przeszła — chwiejna), delta 0.9.26 nie
dołożyła żadnej nowej.

Delta 0.9.27 sprawdzona: `testy/produkt-404-proba-kontrolna.sh` (czysty: 404 GET i HEAD, produkt 200/200, naturalne 404 WP
nietknięte; mutant „stan 0.9.26” widoczny — HEAD wraca do 200); `testy/webhook-podpis-test.php` A–G na PRAWDZIWEJ
`sklepag_stripe_weryfikuj_podpis` + próba kontrolna (mutant „przyjmuje każdy podpis” i mutant „bez progu świeżości” — oba
padają, czysty EXIT=0); strażnik SEO kroki A–H + E2 + D2 (H: symulacja Yoast stałą `WPSEO_VERSION` → nasz canonical milczy; E2: 404 produktu
bez canonicala TAKŻE u gospodarza z domyślnym `rel_canonical` WP — filtr `get_canonical_url` oddaje pusty adres,
skalibrowane: na kodzie sprzed naprawy E2 pada, EXIT=1 bez potoku; D2: cudza strona z doklejonym `?sklepag_produkt=`
przy podpiętym `rel_canonical` ZACHOWUJE canonical gospodarza — bramka strony sklepu w filtrze, skalibrowane:
na filtrze bez bramki D2 pada, EXIT=1 bez potoku), 3 mutanty
padają; `bramki.sh --tryb pelny` 10/10; zgodność dokumentacji 0 rozjazdów; `paczka-zgodnosc.sh` EXIT=0 (0.9.27).
Kompensacja tokenu w cronie: `resend-odnawia-limit-pobran-test.php` części 1–5 PASS (EXIT=0); na `zakup.php` z 0.9.26
CZĘŚĆ 4 FAIL (token_przywrocony=NIE, pobrania_uzyte 0 zamiast 3) — kalibracja stary/nowy kod. CZĘŚĆ 5 (CAS własności:
proces B przejmuje wysyłkę stale-claimem w trakcie pada maila A) skalibrowana na `zakup.php` z `4897d8a` (sprzed warunku
`$przywrocono > 0`): CAS-B mail_tresc_B_zachowana=NIE, opinia_hash_B_zachowany=NIE, RAZEM FAIL, EXIT=1 bez potoku. Przelot prób na `6a7922c` — MAIN po scaleniu, commit z historii wydania (log w repo; sha i liczby rozstrzyga naglowek loga, nie to zdanie): 33 uruchomionych, 6 z EXIT≠0 — znany zestaw z chwiejną `przejecie`; `statystyki` zielona (u K11 na czystym stanowisku: PASS 4/4 mutanty). Powtórka na main domyka rozjazd R-1 z raportu K11 (squash unieważnił sha gałęzi w poprzednim logu). `statystyki-proba-kontrolna.sh` (strażnik ChatBota) na `a2f9dc2` i `f02fc71` była ślepa na mutanta 3, na `9b86106` przeszła — CHWIEJNA, co potwierdza diagnozę „zależna od danych wspólnego stosu" (ChatBot NIETKNIĘTY tym PR-em: `git diff main --stat -- wtyczki/chatbot-live` → pusto); dług w CO-WISI ZAWĘŻONY: część „wada kodu strażnika” skreślona u K11 (PASS 4/4 mutanty na czystym stanowisku); zostaje izolacja przyczyny chwiejności na świeżym stosie.

Delta 0.9.28 sprawdzona (31.08, PR `#234` — 7 rund recenzji CI do stanu 0 KRYTYCZNYCH / 0 WAŻNYCH; wydanie PR `#235` — 2 rundy):
strażnik poczty `resend-odnawia-limit-pobran-test.php` rozszerzony do 7 części (nowe: CZĘŚĆ 2b resend-ze-śladu, CZĘŚĆ 6
„A padł w trakcie, B pada → wraca stan DORĘCZONY"; CZĘŚĆ 5 z asercją `wpis_mail_blad_w_dzienniku=0`), kalibracja PODŁOŻONĄ WADĄ
na kodzie z `origin/main` przez docker cp: RESEND-DOR/CAS-B/DOR FAIL → po przywrocie PASS. Strażnik RODO
`rodo-anonimizacja-pomiar-skutku-test.sh` rozszerzony ×2 z kalibracją na kodzie sprzed naprawy: (a) dane faktury w treści maila
— stary kod FAIL dokładnie na 1 nowej asercji (`tresc maila ZOSTALA`), naprawiony 26/26; (b) krok RENDERU prawdziwej
`sklepag_admin_zamowienie()` — stary kod FAIL na 2 asercjach gałęzi „niedomknięte", naprawiony 28/28 PASS, EXIT=0.
Przelot NA MAIN po squash `#234` (commit `78e53cf`, klasa 67): `bramki.sh` 10/10 EXIT=0 · RODO PASS EXIT=0 · poczta 7/7 EXIT=0 ·
`mail-po-zakupie` EXIT=0 · `dokumentacja-zgodnosc-sklep.sh` 0 rozjazdów EXIT=0 (próba kontrolna 21/21 w rundach CI).
Paczka: `buduj-paczki.sh` EXIT=0 (allowlista+zamek) · `paczka-zgodnosc.sh` „68 plików = repo co do SHA-256, można zamrażać"
EXIT=0 · `pakuj-dla-klienta.sh` 17 kontroli EXIT=0. Zamrożenie zipa na main: PR `#235` (`0433fc6`), recenzja 2 rundy
(r1 spłacona w całości; 2 DROBNE rejestru nieblokujące — CO-WISI). Uwagi WAŻNE delty (wszystkie NAPRAWIONE w rundach,
z kalibracją): r5 RODO — anonimizacja zostawiała kopię danych faktury w `mail_tresc` przy pomiarze meldującym sukces;
r6 — panel pisał „zamówienie niedomknięte" przy zanonimizowanym doręczonym. Mini-K12 (przelot na świeżym klonie tagu
`v0.9.28` z GitHuba, 31.08 ~10:45): `git log -1` = `0433fc6` (merge `#235`) · `git describe --tags` = `v0.9.28` ·
`sha256sum` zipa z klonu = `23a5273773e3946ef5883f2f13a58f6dee59edacba7ed3f823f6090a6f10c7fb` = digest assetu Release
(4 assety, digesty 4/4 zgodne z lokalnymi sumami; Release oznaczony Latest) · `dokumentacja-zgodnosc-sklep.sh` 0 rozjazdów
EXIT=0 · `paczka-zgodnosc.sh` „można zamrażać" EXIT=0. WSZYSTKO ZGODNE — bez rozjazdów.

## Delta 0.9.29 → 0.9.42 (5.09.2026) — druga runda audytu po egzaminie

**Co się stało między wydaniami.** Egzamin z 31.08 (próba 1) niezaliczony; od 1.09 przelot terenu po PYTANIU (nie po obszarze) i naprawy w rundach
recenzenta CI (0 KRYT = warunek scalenia; WAŻNE/DROBNE o narzędziach → dług nazwany w `audyt/CO-WISI.md`). Najważniejsze wady PRODUKTU
znalezione i zamknięte w tym oknie (każda z testem/strażnikiem w tym samym PR-ze):
- `#250` **przycisk „Zapłać” nie potwierdzał płatności** (A4b-F1/F2) — sklep nie sprzedawał; przelot A4 przez przeglądarkę.
- `#258` **migawka produktu w chwili płatności** — kupujący dostaje to, co kupił, także po edycji produktu; golden ze spustem (`#272`).
- `#264` **wstrzyknięcie formuły w eksporcie CSV** (sklep) → `#298` ChatBot: jedna stała 7 znaków po obu stronach, strażnik bliźniaków z podłogą i próbą.
- `#268` **pobranie po zwrocie całości / w sporze** — brama pobrań odmawia. `#273` okno idempotencji per kupujący (Q-5). `#274` wersja schematu rośnie tylko po kompletnej migracji.
- `#270`/`#291` **RODO**: anonimizacja zachowuje dane do faktury (obowiązek podatkowy), czyści IP w dzienniku (P-5) — próba kontrolna na stosie jednorazowym.
- `#294` **obowiązki informacyjne PRZED zakupem** (art. 12 u.p.k.), regulamin/polityka jako szablony z kompensacją dla instalacji aktualizowanych w miejscu, polityka mówi prawdę o sprzątaniu; art. 38a → 7aa.
- `#297` Segregator: reguły widzą tekst człowieka, nie surowe MIME (cztery drogi A7c-F1). `#275` ChatBot: za długie pytanie = odmowa z liczbą, nie ciche cięcie.
- `#263` za duża paczka nie kończy się pustą stroną (limit z serwera); `#278` panel mówi, że Regulamin/Polityka czekają na uzupełnienie; `#283` zrzuty bez cudzego menu.

**Kalibracja i bramki.** `#279` zamyka FAIL 2 bramki wydania (kalibracja L1 i L5 pomiarem). `#240` cztery bramki meldujące czysto bez pomiaru — naprawione.
`#262` martwy strażnik idempotencji znowu mierzy produkt. `#272` golden ze spustem na stosie próbnym od zera. `#296` próba liczników rejestru (rejestr liczy sam siebie,
4 mutanty). Klasy błędów w ściądze: 70 (tag `v0.9.28`) → 144 (`grep -cE '^\| [0-9]+ \|' audyt/SCIAGA-EGZAMINATORA.md`).

**Przelot na `main` po `#298`** (commit `e502128`, 5.09 12:19–12:54, tryb PEŁNY na żywym stosie, log `audyt/sklep/PROBY-KONTROLNE.log` z tego biegu): `bramki.sh --tryb pelny` 12 z 13 ZIELONE (golden POMINIĘTA jawnie: `BEZ_STOSU_WP=1` na hoście i w CI (`quality.yml:64`) — golden biegnie WYŁĄCZNIE ręcznie: `bash testy/golden-stos-probny.sh` przed wydaniem, nie w tej próbie) · `proby-kontrolne-przelot.sh`: **uruchomionych 67, przeszło 58, PADŁY 2, NIE ZMIERZONO 7 (EXIT=2), pominięta jawnie 1 → EXIT=1**. Uczciwie, co znaczą te liczby:
- PADŁA `zamek-stosu-probnego-proba-kontrolna.sh` — jeden punkt: dokumenty mówią „szesnastu” użytkowników stosu próbnego, pomiar daje 19 (obszary C i D dołożyły trzy próby na stosie) — rozjazd LICZBY w dokumencie (klasa 3/142), nie zamka; punkty A–I zamka przechodzą. Naprawa w tym PR-ze (licznik 19 i słowo w CZTERECH miejscach: próba, `lib-golden-stos.sh`, `lib-stos-probny.sh`, CHANGELOG narzędzi; pętla próby sprawdza trzy dokumenty). Dowód: bezpośredni bieg `testy/zamek-stosu-probnego-proba-kontrolna.sh` na tej gałęzi → „WSZYSTKO OK (EXIT=0)”; log przelotu w drzewie niesie stan Z e502128 (EXIT=1 sprzed naprawy) i taki zostaje — log pisany ręcznie nie byłby dowodem; przelot po naprawie = próba 3.
- PADŁA `przejecie-proba-kontrolna.sh` — **kontrola odwrotna** (czysty kod na stanowisku: „bot odpowiedział na pytanie” po przejęciu) — próba orzeka sama: „NIC nie znaczy, sprawdź warunki wstępne”. NIE orzekam o produkcie ani w jedną, ani w drugą stronę: to albo stan stanowiska (kontener ChatBota, klucz API, stara rozmowa), albo regresja; diagnoza PRZED próbą 3 jako pierwszy punkt. Do tego czasu przejęcie rozmowy = NIEZMIERZONE w tym przelocie.
- NIE ZMIERZONO 7 prób (`anonimizacja-zachowuje-fakture`, `chatbot-pytanie-limit`, `csv-bez-formuly`, `idem-okno-per-kupujacy`, `limit-uploadu`, `pobranie-po-zwrocie`, `schemat-wersja-po-sprawdzeniu`): każda kalibruje strażnika na „kodzie sprzed naprawy = `origin/main`” i po scaleniu naprawy do `main` kończy `EXIT=2` („origin/main ZAWIERA już naprawę”). To wygaśnięcie PRZYRZĄDU z konstrukcji (klasa: próba zależna od stanu `main`), nie wada produktu — strażniki, które te próby kalibrują, biegną dalej i przechodzą. Naprawa (mutant w KOPII zamiast `origin/main` jako bazy) = dług narzędziowy nazwany w CO-WISI; do tego czasu sufit `MAX_NIEZMIERZONYCH` 0 w przelocie pełnym jest przekroczony i `k7-koniec` nie orzeknie GO.
· `paczka-zgodnosc.sh` chatbot-live 1.5.17 (przebudowany po #298 r2 — klasa 10, 3. wystąpienie, złapane przez recenzenta) i segregator-maila 1.3.7 (przebudowany w tym PR-ze po komentarzach z #298) → „można zamrażać” EXIT=0; sklep-agentow 0.9.42: zgodny z repo na tej gałęzi (EXIT=0, 5.09 12:25); `#299` (scalony 13:14, PO przelocie z 12:19) zmienił kod sklepu i przebudował zip w swoim PR-ze (klasa 10) — po scaleniu tego PR-a zip na `main` jest z `#299` ·
`k7-koniec.sh` na tej gałęzi (HEAD `a14f191`): kontroli FAIL 1 z 14, NO-GO, EXIT=1 — powody: kontrola 6: „po przelocie (e502128) zmieniło się to, czego on dowodzi” — zipy sklepu (#299) i Segregatora (ten PR) oraz narzędzia prób zmienione po przelocie; przelot na finalnym `main` = próba 3 · rejestr INFRA (`grep -cE` z nagłówka pliku): OTWARTE 16 / ZAMKNIĘTE 69 / W NAPRAWIE+WYCOFANE 4 = razem 89, przecięcia 0 (próba `rejestr-liczniki-proba-kontrolna.sh` EXIT=0, 4 mutanty) · I-26 droga instalacyjna 7 kroków PRZEZ PANEL na `probny_wp`: NIEZMIERZONA w tej próbie (stos próbny zajęty przelotem; zostaje W NAPRAWIE, pomiar przed próbą 3).

**Świadomy dług na to wydanie (DUŻE w rejestrze INFRA, żadne nie jest wadą kodu wtyczek):** `I-3`, `I-4` — sieroty narzędzi audytu (kalibrator, licznik odstępstw; wymagają decyzji, nie linii);
`W-1` — pokrycie własnych hooków warsztatu próbami (proces, nie produkt); `W-2` — blokada `push --force` nie obejmuje repo klienta (nadrabiane izolacją runnera, D9/D42);
`F4-F1` — brak retencji danych zamówień = dokument księgowy (decyzja właściciela, w notatkach wydania jako znane ograniczenie). Pełna lista OTWARTYCH: `audyt/CO-WISI.md`, sekcja „ŚWIADOMY DŁUG NA WYDANIE 0.9.42”.
