# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.43` (delta nad `v0.9.28`; ChatBot LIVE 1.5.18, Segregator maila 1.3.8)

Data: 2026-09-05 · Zakres: DRUGA RUNDA AUDYTU po egzaminie z 31.08 (próba 1 niezaliczona) — 69 PR-ów scalonych od tagu `v0.9.28`
(`#229`–`#298` + ten), w tym: obszary audytu A (paczka i front, `#291`, 7 rund), B (rejestry, `#288`/`#293`), C (prawne i retencja, `#294`,
7 rund + `#299` domykający W1 r7), D (stanowisko: WP-Cron, limity uploadu, łapacz maili — `#289`), S (Segregator MIME, `#297`), E (CI na wykonawcach GitHuba, `#285`/`#287`/`#290`);
naprawy produktu z przelotów po pytaniu (`#249`, `#250` przycisk Zapłać — sklep NIE SPRZEDAWAŁ, `#254`, `#255`, `#256`, `#258` migawka produktu + golden,
`#259`, `#263`, `#264`, `#268`, `#270`, `#273`, `#274`, `#275`, `#278`, `#283`, `#298`); łańcuch dostaw (`#239`, `#243`); kalibracja L1/L5 zamykająca
FAIL 2 bramki wydania (`#279`); rejestr INFRA uzgodniony ze stanem (`#241`, `#267`, `#277`, `#288`) ·
KRYTYCZNE: 0 · DUZE: 0 · (liczniki dotyczą kodu wtyczek; w rejestrze INFRA 5 DUŻE otwarte = narzędzia `I-3`/`I-4`, proces `W-1`/`W-2`, decyzja `F4-F1` — lista w delcie niżej) ·
kontroli FAIL: — (z 14) · **K7: NIE ORZECZONY dla 0.9.43 · EXIT=—** (decyzja właściciela 5.09 18:10: wydanie bez czekania na pełny przelot na finalnym commicie; ostatni pełny przelot 5.09 18:04 na `c36e991` niżej, komplet bramek `bramki.sh --tryb pelny` 13 z 13 ZIELONE na tym samym commicie; szczegóły w delcie niżej).


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

## Delta 0.9.42 → 0.9.43 (5.09.2026, próba 3) — dziewięć okien audytu w skali recenzenta

Źródło: dziewięć niezależnych przelotów po pytaniu (płatności · dokumentacja · architektura/infra · bezpieczeństwo/baza · testy/goldeny/audyt
· sweep zapisów do bazy · ChatBot i Segregator · ścieżki awarii · RODO), 5.09 15:40–16:15, plus rysunek recenzenta „Moduł → BD → ok, Błąd między BD a ok”.
Checklista 26 pozycji (kopia u właściciela); naprawione w `0.9.43 / 1.5.18 / 1.3.8`:
- **Zapis bez sprawdzenia wyniku na ścieżce pieniędzy** (klasa 152): migawka sesji checkout (`zakup.php`, brak zapisu = link do płatności NIE wydany —
  dotąd webhook uznałby opłaconą sesję za obcą), rotacja tokenu przed mailem (automat i „Wyślij ponownie”), księgowanie zwrotu i sporu (błąd = 500 do Stripe),
  statusy po doręczeniu (`zapis_blad`). Sweep: 88 zapisów na ścieżkach klienta, 76 było sprawdzanych, 12 nie — 6 DUŻYCH naprawione, 5 ŚREDNICH naprawione lub nazwane.
- **Mail do właściciela przy awarii, po której kupujący czeka** (klasa 153): `sklepag_powiadom_wlasciciela()`, raz na rodzaj na godzinę.
- **Segregator: eksporter i kasownik WP Privacy Tools** (klasa 151) — dotąd 0 rejestracji przy cudzej korespondencji w 3 tabelach.
- **Regulamin/Polityka (szablony) nazywają uprawnienia sprzedawcy** (uwaga recenzenta z innego zdającego): anonimizacja, blokada po zwrocie, ponowna wysyłka, retencja.
- Dokumenty: SECURITY.md (próba podpisu webhooka ISTNIEJE), CO-WISI „Blokuje wydanie” bez reliktu (klasa 149), ChatBot polityka (kraj dostawcy AI, sessionStorage), instrukcje.
Zostaje z powodem (nazwane): nonce z cache strony (kod obsługuje), golden poza CI (ręcznie przed tagiem), tryb AI bez testu, IP przy odrzuconym webhooku, klasa 108 (limit czasu cofania przebiegu Segregatora), strona Dziękujemy po 2 min.

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

**Przelot prób kontrolnych na finalnym kodzie wydania** (commit `c36e991`, 5.09 17:38–18:04, tryb PEŁNY na żywym stosie, log `audyt/sklep/PROBY-KONTROLNE.log` z tego biegu): `bramki.sh --tryb pelny` na `c36e991` 13 z 13 ZIELONE, EXIT=0 (17:37) · `proby-kontrolne-przelot.sh`: **uruchomionych 67, przeszło 65, padło 1 (`zgodnosc-proba-kontrolna`: brak kreski przed wpisem 1.5.18 w CHANGELOG ChatBota, poprawione w `5d3957f`, narzędzie zgodności EXIT=0 bezpośrednio), niezmierzonych 8 (7 wyjątków JAWNYCH z zamkniętej listy + `nonce-proba-kontrolna`, której kontrola odwrotna pada LOSOWO w ok. połowie biegów w serii na tym stosie, także na pustej maszynie, bezpośrednio EXIT=0 ×3 — od `5d3957f` pomijana jawnie z powodem w logu, klasa 154), pominięta jawnie 1 → EXIT=1**.
- Siedem prób z listy `NIEZMIERZONE_JAWNIE` (`anonimizacja-zachowuje-fakture`, `chatbot-pytanie-limit`, `csv-bez-formuly`, `idem-okno-per-kupujacy`, `limit-uploadu`, `pobranie-po-zwrocie`, `schemat-wersja-po-sprawdzeniu`) kalibruje strażnika na „kodzie sprzed naprawy = `origin/main`”; po scaleniu naprawy kończą `EXIT=2` z konstrukcji, nie z braku środowiska. Lista jest ZAMKNIĘTA w `proby-kontrolne-przelot.sh` (ósma niezmierzona kładzie przelot), sufit `k7-koniec.sh` równy 7 z tym samym powodem; strażnicy, które te próby kalibrują, biegną dalej i przechodzą. Przerobienie tych siedmiu na mutanta w kopii = dług narzędziowy nazwany w `audyt/CO-WISI.md`.
- Wcześniejszy przelot tego dnia (12:19, `e502128`) położył dwie próby: `zamek-stosu-probnego` (liczba użytkowników stosu w dokumentach 16 wobec zmierzonych 19 — naprawione: 19 w próbie i trzech dokumentach) i `przejecie` (kontrola odwrotna na współdzielonym stanowisku; bezpośredni bieg `testy/przejecie-test.php` z kodem równym repo: PASS, sekcje A–F, EXIT=0 — stan stosu w serii, nie produkt).
- **I-26, Krok 1 instrukcji PRZEZ PANEL** (`narzedzia-qa/stanowisko/instalacja-przez-panel-probny.sh`, stos próbny od zera, żądania HTTP jak z przeglądarki): przy domyślnym limicie 2 MB panel odrzuca zip 4,9 MB dokładnie komunikatem, który instrukcja zapowiada w „Wymaganiach” i „Najczęstszych problemach”; przy limicie 8 MB z „Wymagań” zip wgrany (HTTP 200), wtyczka aktywna linkiem z odpowiedzi, cztery strony założone (Sklep, Dziękujemy za zakup, Regulamin i Polityka jako szablony), `debug.log` bez wpisów PHP — EXIT=0.
· `paczka-zgodnosc.sh` sklep-agentow 0.9.43 / chatbot-live 1.5.18 / segregator-maila 1.3.8 → „można zamrażać” EXIT=0 (każdy zip przebudowany po ostatniej zmianie jego plików) ·
`k7-koniec.sh`: **NIE ORZECZONY dla 0.9.43** (decyzja właściciela 5.09 18:10: wydanie bez czekania na pełny przelot na finalnym commicie; ostatni pełny przelot 5.09 18:04 na `c36e991` niżej, komplet bramek `bramki.sh --tryb pelny` 13 z 13 ZIELONE na tym samym commicie) · rejestr INFRA (`grep -cE` z nagłówka pliku): liczby w nagłówku rejestru z pomiaru (próba `rejestr-liczniki-proba-kontrolna.sh` EXIT=0, 4 mutanty).

**Świadomy dług na to wydanie (DUŻE w rejestrze INFRA, żadne nie jest wadą kodu wtyczek):** `I-3`, `I-4` — sieroty narzędzi audytu (kalibrator, licznik odstępstw; wymagają decyzji, nie linii);
`W-1` — pokrycie własnych hooków warsztatu próbami (proces, nie produkt); `W-2` — blokada `push --force` nie obejmuje repo klienta (nadrabiane izolacją runnera, D9/D42);
`F4-F1` — brak retencji danych zamówień = dokument księgowy (decyzja właściciela, w notatkach wydania jako znane ograniczenie). Pełna lista OTWARTYCH: `audyt/CO-WISI.md`, sekcja „ŚWIADOMY DŁUG NA WYDANIE 0.9.42”.
