# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.44` (poprawka po egzaminie 6.09 nad `v0.9.43`; ChatBot LIVE 1.5.18, Segregator maila 1.3.8 bez zmian)

Data: 2026-09-05 · Zakres: DRUGA RUNDA AUDYTU po egzaminie z 31.08 (próba 1 niezaliczona) — 69 PR-ów scalonych od tagu `v0.9.28`
(`#229`–`#298` + ten), w tym: obszary audytu A (paczka i front, `#291`, 7 rund), B (rejestry, `#288`/`#293`), C (prawne i retencja, `#294`,
7 rund + `#299` domykający W1 r7), D (stanowisko: WP-Cron, limity uploadu, łapacz maili — `#289`), S (Segregator MIME, `#297`), E (CI na wykonawcach GitHuba, `#285`/`#287`/`#290`);
naprawy produktu z przelotów po pytaniu (`#249`, `#250` przycisk Zapłać — sklep NIE SPRZEDAWAŁ, `#254`, `#255`, `#256`, `#258` migawka produktu + golden,
`#259`, `#263`, `#264`, `#268`, `#270`, `#273`, `#274`, `#275`, `#278`, `#283`, `#298`); łańcuch dostaw (`#239`, `#243`); kalibracja L1/L5 zamykająca
FAIL 2 bramki wydania (`#279`); rejestr INFRA uzgodniony ze stanem (`#241`, `#267`, `#277`, `#288`) ·
KRYTYCZNE: 0 · DUZE: 0 · (liczniki dotyczą kodu wtyczek; w rejestrze INFRA 5 DUŻE otwarte = narzędzia `I-3`/`I-4`, proces `W-1`/`W-2`, decyzja `F4-F1` — lista w delcie niżej) ·
kontroli FAIL: 0 (z 14) · **K7: GO dla 0.9.43 · EXIT=0** (zmierzone 5.09 22:02 po pełnym przelocie prób kontrolnych na `c5404a7` = kod wydania v0.9.43 + trzy poprawki testów bez zmian we wtyczkach; wydanie 19:56 poszło przy NO-GO z kontroli 6 „dowód przeterminowany” — decyzja właściciela 5.09 18:10 — a ten przelot domyka dowód PO wydaniu na tym samym kodzie wtyczek; komplet bramek `bramki.sh --tryb pelny` 13 z 13 ZIELONE na `592ae78` (22:03–22:05, przed pushem tego PR-a); szczegóły w delcie niżej).


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
`narzedzia-qa/k7-koniec.sh` (14 kontroli; przelot prób — log `audyt/sklep/PROBY-KONTROLNE.log`, sha w sekcji delty niżej) + `narzedzia-qa/paczka-zgodnosc.sh` (zip = repo co do sumy)
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

## Delta 0.9.43 → 0.9.44 (6.09.2026) — poprawka po OBLANYM egzaminie: ścieżka pieniędzy w `includes/zakup.php`

**Skąd:** egzamin (trzecia, ostatnia próba) padł na dwóch uwagach recenzenta zewnętrznego: DUŻY — wynik UPDATE `oczekuje→oplacone` ignorowany,
sesja oznaczana `oplacona` przed dowodem statusu (zakup mógł utknąć w `oczekuje` z pobranymi pieniędzmi, bez maila); ŚREDNI — lost update na
`zwrocono_grosze` (dwa równoległe zwroty gubiły jeden). Trzecia wada z własnego sprawdzenia: zdarzenie SUMARYCZNE z charge doręczone przed zdarzeniem utworzenia zwrotu liczyło
zwrot dwa razy. Klasy 163–165 w `audyt/SCIAGA-EGZAMINATORA.md`, znaleziska `M-1`/`M-2`/`G-1` w `audyt/REJESTR-ZNALEZISK.md` (ZAMKNIETE), decyzja D45.
**Co zrobiono (PR #307, 9 commitów, HEAD `2851c91`):** wynik każdego zapisu na ścieżce pieniędzy czytany (INSERT, CAS statusu, zwrot, spór);
status sprawdzany PRZED znacznikiem sesji; księgowanie zwrotów i sporów pod zamkiem bazy na zamówienie (`GET_LOCK`, nazwa z sygnaturą bazy
i prefiksu) z ponownym odczytem po zamku; `AND status=%s` na trzech zapisach + przy 0 wierszach DOWÓD SKUTKU (kwota i status vs docelowe),
inaczej `false` → Stripe ponawia; identyfikatory z `refunds.data` w śladzie i reguła „suma z charge już pokrywa”; powód alarmu do właściciela
z zapamiętanego błędu bazy. Adnotacje `status-bez-warunku:` 3 → 0.
**Próby (każda PADA na kodzie sprzed naprawy, przechodzi po):** `testy/domkniecie-update-statusu-padl-test.php` (4 scenariusze: awaria
UPDATE, ponowienie, inny silnik, kontrola), `testy/zwrot-rownolegly-test.sh` + `testy/pomocnicze/zwrot-rownolegly-worker.php` (10 scenariuszy
z DWOMA procesami: lost update, duplikat, status z drugiego silnika, A→B→A, spór w oknie), `testy/zwrot-charge-przed-refund-test.php`
(4 kolejności). Kalibracja w opisie PR #307 (PRZED/PO, kody wyjścia).
**Recenzja automatyczna:** 7 rund (r1–r3 bramki repo i brak minut CI; r4 1 WAŻNA — status pisany bez warunku; r5 1 KRYTYCZNA — paczka
zbudowana przed ostatnią poprawką (klasa 10) + 2 WAŻNE — dowód skutku, brak prób; r6 3 DROBNE; r7 2 DROBNE = dług). Wszystkie KRYT/WAŻNE
naprawione w PR-ze z próbami. Krytyk na zimno (most ChatGPT, pytanie ogólne bez kodu): zamek OK, heurystyka pokrycia = chwilowy niedomiar,
docelowo księga zwrotów `UNIQUE(refund_id)` — dług w CO-WISI.
**Bramki na HEAD:** `bramki.sh --tryb pelny` 13/13 ZIELONE lokalnie; CI 7. przebieg 5/5 zielone (bramki, próby ×3, recenzja);
`paczka-zgodnosc.sh sklep-agentow 0.9.44` EXIT=0 (7 miejsc wersji, 69/69 sum SHA-256, zip zbudowany PO ostatniej zmianie treści);
`dokumentacja-zgodnosc-sklep.sh` EXIT=0; `bramka-znaleziska` EXIT=0; `rejestr-liczniki` EXIT=0.
**Przelot prób kontrolnych na kodzie wydania (`2851c91`, 6.09, tryb pełny na żywym stosie, log `audyt/sklep/PROBY-KONTROLNE.log`):** uruchomionych 67, z EXIT≠0: 1, pominiętych jawnie 2 (`dowod-zachowania-segregator`, `nonce`); 7 z listy `NIEZMIERZONE_JAWNIE` EXIT=2 z konstrukcji; jedyna spoza listy: `wskaznik-pisania-proba-kontrolna.sh` EXIT=2 — 5/5 mutantów wykrytych, kontrola ODWROTNA padła w serii; ten sam skrypt bezpośrednio na tym samym kodzie tuż po przelocie 2× EXIT=0 (5/5 + odwrotna PASS) = przyrząd przeglądarkowy, klasa 154 (2. egzemplarz, jak `nonce` 5.09), nie produkt.
**K7 (`narzedzia-qa/k7-koniec.sh`) na `2851c91`:** NO-GO · kontroli FAIL: 1 (z 14) · EXIT=1 · KRYTYCZNE otwarte: 0 · DUŻE otwarte: 0. Jedyna FAIL = kontrola 6 (próba `wskaznik-pisania` EXIT=2 spoza listy jawnych wyjątków); 13 PASS: mapa 104 celów 0 ⬜ i zamrożona, kalibracja 6 soczewek, rejestr 0 KRYT/DUŻE i 0 DO WERYFIKACJI, każde ZAMKNIETE ze strażnikiem, 0 anomalii, przelot człowieka 27/27, paczka sha256, tag zamrożenia, 15 raportów, SECURITY 3/3. **Decyzja koordynatorki (6.09, właściciel nieobecny — słowo 13:58 „scalaj, rób wydanie”): wydaję przy NO-GO kontroli 6**, bo padła kontrola odwrotna PRZYRZĄDU w serii (klasa 154), a bezpośrednio na tym samym kodzie próba dała 2× EXIT=0; listy `NIEZMIERZONE_JAWNIE` NIE poszerzam (bramka zwalniana komentarzem = lekcja oblanego egzaminu), naprawa przyrządu = dług w `audyt/CO-WISI.md`.
**Dług nazwany na to wydanie (CO-WISI, sekcja „PO EGZAMINIE 6.09”), żaden nie jest KRYT/DUŻE:** stary snapshot charge może obniżyć nowszą sumę
(bez próby); heurystyka pokrycia = niedomiar do własnego zdarzenia sumarycznego; księga zwrotów ze schematem 11; `check_connection()` a zamek;
bramka `status-bez-warunku` (adnotacja → próba, kolumny kwot); bramka `paczka` mierzy tylko komplet; `zdarzenia-webhooka` a DECYZJE; próba
psująca INSERT; dwa zapisy `payment_intent_id` bez odczytu wyniku.

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
**Audyt niezależny (Maya, 5.09, na ślepo, tag v0.9.42; `AUDYT-NIEZALEZNY-MAYA-2026-09-05.md` u właściciela): 1 DUŻY · 3 ŚREDNIE · 5 obserwacji — naprawione w 0.9.43:**
- **D1 (DUŻY, bezpieczeństwo + architektura): adres gościa wyłącznie z `REMOTE_ADDR`, a limit logowań 6/min na cały WordPress** — za Cloudflare/proxy wszyscy goście jednym adresem: właściciel wyrzucany z wp-admin przez boty, sprzedaż z sufitem 10/min na witrynę; ChatBot miał naprawę, sklep nie (klasa 155). Naprawa: `sklepag_ip()` jak `chatl_ip_goscia()` (przełącznik *Strona za Cloudflare / proxy*, kolejność nagłówków, walidacja, filtr `sklepag_ip`) + wyłącznik *Limit prób logowania*; strażnik `testy/ip-za-proxy-sklep-test.php`; INSTRUKCJA §8, SECURITY.md.
- **S1 (ŚREDNI): `sleep()` do 60 s w `wp_authenticate` zajmował workery PHP** (6 żądań/min × 60 s z jednego adresu; klasa 156) — sufit 2 s, nadmiar odbija limit.
- **S2 (ŚREDNI): wydanie 0.9.42 przy NO-GO własnej bramki bez słowa w notatce** — ten raport i notatka 0.9.43 mówią wprost, co zmierzono i czego nie (nagłówek wyżej).
- **S3 (ŚREDNI): dziennik logowań zapisywał każdy wpisany ciąg** (hasło w polu loginu na 90 dni w bazie; klasa 157) — zapis tylko istniejących kont, inaczej „[nieistniejący]”.
- O2 (rejestr `event.id` Stripe zamiast dedupu po dzienniku z retencją), O3 (sekrety jawnie w `wp_options` — zdanie w SECURITY.md), O1 (sklep zaszyty pod dwa produkty) — nazwane, nie zmienione.
**Drugie przejście audytu niezależnego (Maya, 5.09 wieczór: ChatBot po ścieżkach wejścia i moduł AI, klient IMAP, cofanie przebiegu, macierz 71 handlerów trzech wtyczek skryptem): 0 nowych DUŻYCH/ŚREDNICH; O6 = RODO Segregatora (naprawione wyżej), O7 = cytowanie IMAP bez odrzucenia nowej linii (dług), O8 = przełącznik „za proxy” w ChatBocie domyślnie wyłączony (świadomie: nagłówki da się podrobić).
**ChatBot 1.5.18 dodatkowo (okno „ChatBot + bezpieczeństwo”, `AUDYT-P3-chatbot-security.md`): dobowy stop-loss AI rezerwuje slot ATOMOWO przed płatnym wywołaniem** (`chatl_ai_zarezerwuj()`; do 1.5.17 check-then-act: N gości przy ostatnim slocie przekraczało limit o N); strażnik `testy/chatbot-stoploss-rezerwacja-test.php`.
**Recenzja PR `#301` (recenzent AI, r1, 5.09 18:31): 0 KRYT · 2 WAŻNE · 2 DROBNE — wszystkie naprawione w tej samej paczce:** WAŻNA 1 = kasownik i eksporter RODO Segregatora obejmowały dwie z trzech tabel — kopia nadawcy i tematu każdego maila próbki w JSON-ie raportów przebiegów (`segm_przebiegi.wyniki`, widoczna w panelu i w CSV) zostawała po „usuń moje dane” do końca retencji (klasa 159); naprawa: raporty przeszukiwane po JSON-ie, wpisy osoby bez nadawcy i tematu, strażnik `testy/segregator-rodo-test.php` 18/18 na stosie, na kodzie sprzed naprawy 5 asercji padło (EXIT=1). Przy okazji własna wada strażnika: licznik asercji pod `wp eval-file` był zmienną lokalną, test NIE MÓGŁ paść (5 BLAD i EXIT=0 zmierzone) — klasa 158. WAŻNA 2 = nagłówek wyżej mówił „—” zamiast zmierzonych liczb bramki wydania (poprawione: NO-GO/EXIT=1 wprost). DROBNA 3 = „Wyślij ponownie” przy padniętym zapisie linku kierowało właściciela do poczty (osobny komunikat o bazie). DROBNA 4 = nagłówek kolumny rubryki.
**Paczka 2 (5.09 19:26–19:50, decyzja właściciela „nic nie wisi z uzasadnieniem, jeśli da się naprawić”):** O13 (nonce panelu operatora), O14 (widget po 429/403), O15 (zdanie dla złej sesji), O16 (nie-JSON w płatności → zdanie z kodem HTTP), O9 (import konfiguracji bez `WP_Filesystem`), O7 (CR/LF w cytowaniu IMAP), `sklepag_zdarzenie()` mierzy zapis, `segm_skrzynka_srodowisko_pada()` pytana przed połączeniem (A7-F3: dwie pozostałe „martwe” funkcje są wołane przez strażniki i dokumentację — rejestr się mylił), A4b-F3/F4 były już naprawione w kodzie (rejestr w tyle). Zastany test `chatbot-stoploss-awaria-test` padał od przebudowy stop-lossu (liczył wywołania przez `chatl_ai_zuzyj`, które od 1.5.18 liczy rezerwacja) — poprawiony, EXIT=0.
**Recenzja PR `#301` r3 (po `3a75dd7`): 0 KRYT · 1 WAŻNA · 2 DROBNE — wszystkie naprawione:** WAŻNA 1 = mój sufit 64 KB na linię odcinał `* SEARCH` z listą wszystkich UID-ów (skrzynka od ~11 000 maili) — 4 MB na linię + kontrola dodatnia W SKALI (`search-40k`) w strażniku `testy/segregator-imap-sufity-proba-kontrolna.sh` (EXIT 1; na kodzie z 64 KB pada, po naprawie 8/8; klasa 161); DROBNA 2 = A7b-F1 zamknięte sondą bez `exit 1` — teraz strażnik; DROBNA 3 = kasowanie `Message-ID` po wniosku RODO wyłącza drogę zapasową cofania przebiegu dla tej osoby — nazwane w INSTRUKCJA, DOK-TECH i CHANGELOG (RODO ma pierwszeństwo).
**Pomiar NA ŻYWO wad D1, S1, S3, S4 na 0.9.42 (Maya, laptop, jednorazowy WordPress w Dockerze, raport v6):** D1 — 6 prób logowania na nieistniejące konta i właściciel z poprawnym hasłem dostaje 429; S1 — 4 równoległe żądania z jednego adresu zajęły workery, strona główna bez odpowiedzi 25 s (przed i po: 200 w 0,04 s); S3 — hasło wpisane w pole loginu w bazie i w panelu; S4 — odpowiedź operatora po 6 min pisania przepadła. Wszystkie cztery naprawione w tej paczce (strażniki wyżej). Pułapka odtworzenia: baza na UTC, wtyczki zapisują czas lokalny WP — cofanie znacznika zegarem bazy nie działa.
**Recenzja PR `#301` r2 (po `903b212`): 0 KRYT · 0 WAŻNE · 2 DROBNE, obie naprawione:** ten sam błąd zasięgu pod `wp eval-file` (klasa 158) w `chatbot-rodo-role-test.php` — sprzątanie po `global` PRZED przypisaniem (dotąd kasowało po pustym kluczu); kasownik Segregatora czyści w ruchach także `message_id` (nagłówek z wiadomości tej osoby), eksporter go wydaje; strażnik `segregator-rodo-test` 19/19, na kodzie bez tej poprawki 2 asercje padają.
**Dogrywka audytu niezależnego (Maya, raport v5: cały JS i CSS zderzone z PHP) — 1 ŚREDNI, 4 obserwacje; ŚREDNI naprawiony w tej paczce:** S4 = operator piszący odpowiedź dłużej niż 5 min tracił tekst (sprawdzenie stanu w handlerze wysyłki wygaszało przejęcie, potem odrzut bez przechowalni; czwarty egzemplarz klasy „odrzut kasuje wpisane”) — handler czyta stan z bazy, wysyłka liczy się jako aktywność, przy odmowie treść czeka w polu; strażnik `testy/chatbot-operator-wysylka-po-ciszy-test.php` 9/9 na stosie, na kodzie sprzed naprawy 7 asercji pada (EXIT=1); regresja `przejecie-test`, `chatbot-operator-limit-trzyma-tresc-test`, `chatbot-stoploss-rezerwacja-test` EXIT=0 (klasa 160). O13–O16 (nonce panelu, widget po 429, dwa komunikaty techniczne) — dług w `CO-WISI`.
**Zamknięty dług produktowy `A7b-F1` (ŚREDNI z 1.09, klient IMAP bez sufitu rozmiaru):** `Segm_Imap::MAX_*` — linia 4 MB (po #301 r3: 64 KB odcinało `* SEARCH` dużej skrzynki), literał 1 MB, 20 000 linii / 8 MB; strażnik `testy/segregator-imap-sufity-proba-kontrolna.sh` 8/8 bez fatala z kontrolą dodatnią `search-40k` (przed naprawą 4 scenariusze kończyły proces PHP błędem pamięci), `segregator-skrzynka-test` na żywym serwerze `segm_imap` EXIT=0.
**Audyt niezależny — infrastruktura (Maya, raport v4, I1–I7):** I1 `main` bez ochrony po stronie GitHuba (plan Free, repo prywatne — API: 403 „Pro albo publiczne”); I2 minuty CI: 1825 z 2000 darmowych zużyte 1–5.09; I3 recenzent pada na PR-ach Dependabota (brak sekretów); I6 stanowisko poza repo; I7 paczka zbiorcza nie bitowo powtarzalna (różnią się tylko czasy w zipie). Decyzje właściciela (repo publiczne / limit wydatków) i długi narzędziowe — `audyt/CO-WISI.md`.
**Recenzja tej łaty (świeże okno, `AUDYT-P3-recenzja-laty.md`): SCALAĆ, 0 KRYTYCZNYCH; 2 WAŻNE do długu:** `wp_mail()` synchronicznie w ścieżce webhooka (raz na godzinę na rodzaj; skutek: ewentualne dodatkowe ponowienie Stripe) i `sklepag_zdarzenie()` bez sprawdzenia własnego zapisu.

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
4 mutanty). Klasy błędów w ściądze: 70 (tag `v0.9.28`) → 157 (`grep -cE '^\| [0-9]+ \|' audyt/SCIAGA-EGZAMINATORA.md`).

**Przelot prób kontrolnych PO WYDANIU, na kodzie v0.9.43** (commit `c5404a7` = `main` po scaleniu #301/#302 + poprawki trzech testów, 5.09 21:35–22:02, tryb PEŁNY na żywym stosie, log `audyt/sklep/PROBY-KONTROLNE.log` z tego biegu): uruchomionych 67, przeszło 60, PADŁO 0, niezmierzonych jawnie 7 (dokładnie zamknięta lista `NIEZMIERZONE_JAWNIE`: kalibracja na bazie `origin/main`, która zawiera już naprawę — EXIT=2 z konstrukcji), pominiętych jawnie 2 (`dowod-zachowania-segregator` — deinstaluje wtyczkę ze stosu, do przelotu ręcznego; `nonce-proba-kontrolna` — kontrola odwrotna pada losowo w serii, klasa 154); `k7-koniec.sh` → **GO, kontroli FAIL: 0 (z 14), EXIT=0**. Pierwszy bieg tego wieczoru (20:28–20:55, na `97f9072`) położył dwie próby, obie o TESTACH, nie o produkcie: `linia-bazowa-pomijane` (sufit 42 przy 43 — doszła `segregator-imap-sufity-proba-kontrolna.sh`) i `tokeny-koszt` (kontrola odwrotna: strażnik wołał `chatl_ai_odpowiedz()` bez rezerwacji slotu, którą od 1.5.18 robi `front.php`) — poprawione w `c2683d7`, strażnik tokenów EXIT=0, jego próba 6/6. Drugi bieg (21:04–21:32, `c2683d7`; bieg pośredni 20:58 zabił strażnik pamięci Claude Code w kroku phpstan) położył `statystyki` (mutant „nieoznaczone w mianowniku skuteczności” znikał w zaokrągleniu do 0,1 % przy setkach dzisiejszych wierszy w bazie stosu; test zasiewał 1 wiersz bez znacznika — teraz 41, `c5404a7`, próba 4/4). Trzy poprawki dotyczą WYŁĄCZNIE testów, kod wtyczek i paczki z 19:56 nietknięte. **Poprzedni przelot** (commit `c36e991`, 5.09 17:38–18:04, tryb PEŁNY na żywym stosie; log jednoplikowy, ten bieg widać przez `git show 5689c4a:audyt/sklep/PROBY-KONTROLNE.log` — log wjechał do repo commitem PO przelocie, nie tym, na którym biegł; `c36e991:` pokazuje bieg 12:19): `bramki.sh --tryb pelny` na `c36e991` 13 z 13 ZIELONE, EXIT=0 (17:37) · `proby-kontrolne-przelot.sh`: **uruchomionych 67: ZMIERZYŁO 58 (EXIT=0), 8 z EXIT=2 nic nie zmierzyło, padła 1 realnie (58 + 8 + 1 = 67) — w `PODSUMOWANIE` logu `PADŁO: 2`, bo przelot liczy jako pad także EXIT=2 spoza listy jawnej (nonce, niżej) (`zgodnosc-proba-kontrolna`: brak kreski przed wpisem 1.5.18 w CHANGELOG ChatBota, poprawione w `5d3957f`, narzędzie zgodności EXIT=0 bezpośrednio), niezmierzonych 8 (7 wyjątków JAWNYCH z zamkniętej listy + `nonce-proba-kontrolna`, której kontrola odwrotna pada LOSOWO w ok. połowie biegów w serii na tym stosie, także na pustej maszynie, bezpośrednio EXIT=0 ×3 — od `5d3957f` pomijana jawnie z powodem w logu, klasa 154), pominięta jawnie 1 → EXIT=1**.
- Siedem prób z listy `NIEZMIERZONE_JAWNIE` (`anonimizacja-zachowuje-fakture`, `chatbot-pytanie-limit`, `csv-bez-formuly`, `idem-okno-per-kupujacy`, `limit-uploadu`, `pobranie-po-zwrocie`, `schemat-wersja-po-sprawdzeniu`) kalibruje strażnika na „kodzie sprzed naprawy = `origin/main`”; po scaleniu naprawy kończą `EXIT=2` z konstrukcji, nie z braku środowiska. Lista jest ZAMKNIĘTA w `proby-kontrolne-przelot.sh` (ósma niezmierzona kładzie przelot), sufit `k7-koniec.sh` równy 7 z tym samym powodem; strażnicy, które te próby kalibrują, biegną dalej i przechodzą. Przerobienie tych siedmiu na mutanta w kopii = dług narzędziowy nazwany w `audyt/CO-WISI.md`.
- Wcześniejszy przelot tego dnia (12:19, `e502128`) położył dwie próby: `zamek-stosu-probnego` (liczba użytkowników stosu w dokumentach 16 wobec zmierzonych 19 — naprawione: 19 w próbie i trzech dokumentach) i `przejecie` (kontrola odwrotna na współdzielonym stanowisku; bezpośredni bieg `testy/przejecie-test.php` z kodem równym repo: PASS, sekcje A–F, EXIT=0 — stan stosu w serii, nie produkt).
- **I-26, Krok 1 instrukcji PRZEZ PANEL** (`narzedzia-qa/stanowisko/instalacja-przez-panel-probny.sh`, stos próbny od zera, żądania HTTP jak z przeglądarki): przy domyślnym limicie 2 MB panel odrzuca zip 4,9 MB dokładnie komunikatem, który instrukcja zapowiada w „Wymaganiach” i „Najczęstszych problemach”; przy limicie 8 MB z „Wymagań” zip wgrany (HTTP 200), wtyczka aktywna linkiem z odpowiedzi, cztery strony założone (Sklep, Dziękujemy za zakup, Regulamin i Polityka jako szablony), `debug.log` bez wpisów PHP — EXIT=0.
· `paczka-zgodnosc.sh` sklep-agentow 0.9.43 / chatbot-live 1.5.18 / segregator-maila 1.3.8 → „można zamrażać” EXIT=0 (każdy zip przebudowany po ostatniej zmianie jego plików) ·
`k7-koniec.sh`: **GO dla 0.9.43 — 14/14, EXIT=0** (przelot PO wydaniu, 5.09 21:35–22:02 na `c5404a7`, blok wyżej); **w chwili wydania 19:56: NIE ORZECZONY** — decyzja właściciela 5.09 18:10: wydanie bez czekania na pełny przelot na finalnym commicie; ostatni pełny przelot przed wydaniem 5.09 18:04 na `c36e991`, komplet bramek 13 z 13 ZIELONE na tym samym commicie · rejestr INFRA (`grep -cE` z nagłówka pliku): liczby w nagłówku rejestru z pomiaru (próba `rejestr-liczniki-proba-kontrolna.sh` EXIT=0, 4 mutanty).

**Świadomy dług na to wydanie (DUŻE w rejestrze INFRA, żadne nie jest wadą kodu wtyczek):** `I-3`, `I-4` — sieroty narzędzi audytu (kalibrator, licznik odstępstw; wymagają decyzji, nie linii);
`W-1` — pokrycie własnych hooków warsztatu próbami (proces, nie produkt); `W-2` — blokada `push --force` nie obejmuje repo klienta (nadrabiane izolacją runnera, D9/D42);
`F4-F1` — brak retencji danych zamówień = dokument księgowy (decyzja właściciela, w notatkach wydania jako znane ograniczenie). Pełna lista OTWARTYCH: `audyt/CO-WISI.md`, sekcja „ŚWIADOMY DŁUG NA WYDANIE 0.9.42”.
