# RAPORT ODDANIA — Sklep Agentów, próba 1 (2026-08-30, wydanie `v0.9.23`; §6 = dopisek `v0.9.24`; §7 = dopisek `v0.9.25` + K10)

> Dla Dzidka i dla oceniającego. Każda liczba ma komendę, którą ją policzono, i commit, na którym
> ją policzono: **`a1b2495`** (`main` po scaleniu `#213` i `#214`). Każda zmiana w repo po tym
> commicie unieważnia sekcję 2 — wtedy przelot i bramkę końcową trzeba puścić od nowa.

## 1. Co oddajemy

Paczka zbiorcza `wydanie/sklep-agentow-KLIENT-0.9.23.zip` (składa `narzedzia-qa/pakuj-dla-klienta.sh` 1.1.0,
samokontrola 17 kontroli na rozpakowanej paczce, w tym otwarcie każdego zipa): `sklep-agentow-0.9.23.zip` ·
`chatbot-live-1.5.13.zip` · `segregator-maila-1.3.6.zip` · `PRZECZYTAJ-MNIE.txt` · `INSTRUKCJA-INSTALACJI.md` ·
`diagramy/` (3 PNG) · `SHA256SUMS.txt`.
W każdej paczce wtyczki: `INSTRUKCJA.md`, `readme.txt`, `CHANGELOG.md`, `DOKUMENTACJA-TECHNICZNA.md`,
`DIAGRAM-PRZEPLYWU.svg`, `LICENSE`; w sklepie dodatkowo `PONAD-SPECYFIKACJE.md` i pięć zrzutów panelu.
Wydanie na GitHubie: tag `v0.9.23`, Release „Latest” — potwierdzenie oczami wpisane w opisie Release
i w `DO-ODDANIA.md` §7 po publikacji.

## 2. Trzy liczby dla właściciela (bramka końcowa `narzedzia-qa/k7-koniec.sh` 1.3.6)

```
$ bash narzedzia-qa/k7-koniec.sh            # main a1b2495, 30.08 12:35
TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: 0 · DUŻE otwarte: 0 · kontroli FAIL: 2 (z 13)
K7: NO-GO — 2 z 13 kontroli niespełnionych (8 warunków zamknięcia)                         EXIT=1
```

Jedenaście kontroli PASS: mapa celów `104 · ✅ 79 · 🐞 23 · ⚠️ 2 · ⛔ 0 · ⬜ 0` i zamrożona; rejestr
0 KRYTYCZNE/DUŻE otwartych, 0 DO WERYFIKACJI, każde ZAMKNIĘTE ma strażnika ze sha padania na starym
kodzie; 0 anomalii; przelot człowieka 27/27; paczka zamrożenia sha zgodny; tag `k7-zamrozenie` = `5d3dcf9`;
15 raportów audytorów, 0 odrzuconych werdyktów.

Dwa FAIL-e są **decyzją, nie wadą** — obie zapisane w `audyt/CO-WISI.md` i w `DECYZJE.md`:
- **Kontrola 2 — kalibracja audytorów.** `soczewek=6, bez PASS=2` (L1, L5 nie osiągnęły PASS przy progu
  bramki). Kryterium klienta #12 (≥ 2/3 mutantów złapanych) jest SPEŁNIONE: 7 mutantów, 5 złapanych = 71 %.
  Bramka jest ostrzejsza od rubryki. Decyzja Dzidka 28.08: oddajemy z jawnym zapisem.
  **Co to znaczy dla klienta, po ludzku:** pięć soczewek udowodniło na podłożonych błędach, że naprawdę widzą
  wady. Dwie tego nie udowodniły — więc **to, czego nie znalazły w obszarze dziennika logowań (L1) i zgodności
  dokumentacji z zachowaniem (L5), nie jest dowodem, że tam nic nie ma.** Ich „czysto” waży mniej niż pozostałych.
- **Kontrola 6 — próby kontrolne strażników.** `6 z 30` prób kończyło EXIT≠0 przy 0.9.23 (od 0.9.26 log w repo niesie `e95de75`: 5 z 31, ten sam zestaw minus chwiejna `przejecie` — §8; `audyt/sklep/PROBY-KONTROLNE.log`,
  przelot maszynowy na `a1b2495`, 30.08 12:18; 1 pominięta z powodem w logu). Zmierzone przez wykluczenie
  na czystym `origin/main`: pięć pada identycznie bez naszych zmian (`blad-klucza`, `ip-za-proxy`, `nonce`,
  `przejecie`, `pulapka` — strażniki botów, dwa ślepe w obie strony), szósta (`dowod-z-paczki`, EXIT=2) ma
  zaszytą wersję 1.5.0. Jedna z nich (`przejecie`) jest chwiejna — wynik zależy od stanu wspólnego stosu
  (w innym przelocie przeszła bez zmian w kodzie); siódma (`statystyki`) migocze i w tym przelocie akurat przeszła.
  Wady leżą w NARZĘDZIACH QA, nie w produkcie — **ale skutkiem jest, że zabezpieczenie nonce i obsługa błędu
  klucza w chatbocie są dziś NIEPILNOWANE** (`blad-klucza` i `nonce` padają zawsze): regresji w tych dwóch
  miejscach nie złapie żadna bramka. To dług bezpieczeństwa narzędzi, zapisany tak samo w `CO-WISI.md`.

## 3. Jak to było sprawdzane (i czego to NIE dowodzi)

- Audyt końcowy K7 na **czystym stosie z deklarowanym minimum** `wordpress:6.7-php8.1-apache`
  (`audyt/sklep/ZAMROZENIE.md`), świeże sesje audytorów, jedna soczewka na agenta, kalibracja bankiem
  mutantów (gałąź `audyt/mutanty-sklep`). Naprawy po audycie: 23 cele 🐞, każde zamknięcie ze strażnikiem.
- Przelot człowieka po sklepie **27/27 punktów** (`audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md`) na stosie
  warsztatowym WP 7.1 / PHP 8.4; rdzeń zakupu (zakup → webhook → mail → pobranie, sumy zgodne) powtórzony
  NA PACZCE, na świeżej instalacji (`audyt/K6-INSTALACJA-OD-ZERA.md`).
- Statyka pod PHP 8.1 (`phpstan.neon.dist`: `phpVersion: 80100`, poziom 5), WPCS, gitleaks, skaner śladów
  wewnętrznych i poświadczeń — dziesięć bramek `narzedzia-qa/bramki.sh` w CI na każdym PR; recenzja Claude
  każdego PR-a wg `.github/RECENZENT-BRIEFING.md`. Ostatnie dwa PR-y: `#213` 6 rund (w tym jedna KRYTYCZNA
  naprawiona w kodzie ze strażnikiem — świeży link do pobrania odnawia limit), `#214` 3 rundy.
- Plugin Check WordPress.org na trzech paczkach: 416 pozycji, **1 w kodzie** (naprawiona w 0.9.23), reszta
  to reguły katalogu WP.org i fałszywe alarmy na nazwach tabel — `audyt/PLUGIN-CHECK-30-08.md`.
- Dokumentacja == kod: `testy/dokumentacja-zgodnosc-sklep.sh` (5 liczb, etykiety statusów, odsyłacze;
  próba kontrolna 21/21) — treść zdań instrukcji czytana w całości w sześciu rundach recenzji `#213`.
- ⛔ **Czego nie zrobiliśmy:** instalację od zera i przelot na paczce robiła **autorka**, nie osoba postronna
  (rubryka §12 żąda nie-autora — nie mieliśmy drugiej osoby). Pełny przelot 27 punktów NA PACZCE nie był
  powtarzany — rdzeń tak, reszta mierzy ten sam kod co przelot audytora (decyzja z `DO-ODDANIA.md` §4).
  Przelot wersji 0.9.23 na żywym stosie po naprawie limitu pobrań: trzy strażniki resendu + reconciliation
  (EXIT=0 ×4 na stosie warsztatowym), nie pełny przelot człowieka.
- **Po wydaniu (K9, 30.08):** paczka POBRANA z Release zainstalowana „jak klient” przez świeżego agenta na czystym WP 6.9.4
  z motywem kolegi z jego repo (stan 28.08): instrukcja przeszła w całości — link „Sklep” w 3 miejscach nawigacji, zakup przez
  prawdziwy Stripe Checkout, pobranie z SHA zgodnym, „Wyślij ponownie”; 0 notice’ów PHP na 8 adresach frontu (logger sprawdzony
  podłożonym błędem). Plugin Check na zipie z Release: 3 błędy (było 5). `/security-review` delty po 28.08: zero. `/code-review ultra`
  na `#213`: jeden „nit”. Szczegóły i trzy długi: `audyt/K9-INSTALACJA-Z-RELEASE-0.9.23.md`.

## 4. Co jest świadomie niedomknięte (z `audyt/CO-WISI.md`, stan 2026-08-30)

Pełna lista jest w `CO-WISI.md` (14 wpisów). Tu **wszystkie pozycje, które dotykają produktu lub danych
klienta**; pominięte są wyłącznie długi narzędzi warsztatu (skanery, próby kontrolne, rejestr).

1. **Oznaczenia trybu testowego zaszyte w kodzie w 7 miejscach** (trzy banery, cztery inne teksty) widzianych przez kupującego (w tym mail po zakupie
   i ekran po zapłacie). Na kluczach testowych — zgodnie z zamówieniem — jest prawdą; na produkcji skłamałby.
   Instrukcje mówią to wprost i każą zacząć od kontaktu z nami. Naprawa = nowa paczka, PO egzaminie.
2. **Ponowienie wysyłki przez cron nie ma kompensacji tokenu**: po nieudanym „Wyślij ponownie” sklep przywraca
   poprzedni link tylko do następnej automatycznej próby; przy trwałej awarii poczty poprzedni link przepada,
   droga ratunku = „Wyślij ponownie” po naprawie poczty. Instrukcja mówi to wprost. Zachowanie sprzed `#213`.
3. **Strażnik paczek nie strzela sam** — wymaga PR-a manifestowego (decyzja: manifest bramek zmieniany tylko
   osobnym PR-em czytanym przez Dzidka). To samo dotyczy nowej bramki odsyłaczy strony tytułowej (41 celów,
   uruchamiana ręcznie, bez próby kontrolnej).
4. **Skaner śladów wewnętrznych** nie zna trzech rodzin odsyłaczy, mija wzorzec przez wielkość litery i nie
   czyta obrazów (pięć PNG w paczce sklepu sprawdzone oczami) — dziś paczki są czyste, jutro nic ich nie broni automatem.
5. **Okno idempotencji zakupu** nie obejmuje jednoczesnego podwójnego kliknięcia (dług zastany, nieblokujący).
   Tej samej klasy: kompensacja licznika pobrań po nieudanym resendzie bierze wartość ze zdjęcia, nie z bazy.
6. **Kontrola „wydanie z readme ma wpis w CHANGELOG”** istnieje tylko u Segregatora. Bramka sekcji F mierzy
   5 z 9 liczb instrukcji (niemierzone wypisane w jej nagłówku).
7. **Zwrot ani spór nie odcinają wcześniej wysłanego linku** do pobrania (działa do końca 72 h / 10 pobrań) —
   zapisane wprost w instrukcji właściciela; zmiana na życzenie.
8. **Szkice Regulaminu i Polityki prywatności powstają tylko przy pierwszej aktywacji** — sklep zainstalowany
   przed 0.9.23 ma szkic Polityki bez adresu IP przy pobraniu; instrukcja (rozdz. 6) i CHANGELOG mówią, co dopisać.
9. **Anonimizacja może potwierdzić usunięcie, którego nie wykonała w całości**: adres e-mail z `/` w części
   lokalnej zostaje w dzienniku zdarzeń, a wtyczka melduje sukces (przypadek brzegowy, zapisany w `CO-WISI.md`
   z dowodem). Tej samej ścieżki dotyczy: `sklepag_obsluz_resend()` ma sześć zapisów bez sprawdzenia wyniku —
   skutek zapisywany niezależnie od powodzenia (dług nazwany w kodzie i w rejestrze).
10. Pięć pól panelu (produkty, ustawienia) bez polskiego komunikatu walidacji — kosmetyka, zapisana z liniami.
11. Odbiór wydajności na docelowym hostingu (AN-022) — poza naszym stanowiskiem.

## 5. Gdzie leżą dowody

`audyt/sklep/PROBY-KONTROLNE.log` (przelot maszynowy, commit `a1b2495`) · `audyt/sklep/raporty/` ·
`audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` · `audyt/SCIAGA-EGZAMINATORA.md` (60 klas błędów z dowodami) ·
`audyt/PLUGIN-CHECK-30-08.md` · `audyt/CO-WISI.md` · `DECYZJE.md` (32 decyzje z uzasadnieniem) ·
`MAPA-SPEC.md` (wymagania S-01…S-20 ze statusem) · opisy PR `#213` i `#214` (każda runda recenzji z tabelą
uwaga → naprawa → wynik uruchomienia).

## 6. Dopisek — wydanie `v0.9.24` (2026-08-30, delta nad 0.9.23; zbudowane z `main` `d0a45a9`)

**Co się zmieniło:** mail po zakupie w wersji HTML (brand strony, okładka produktu, przycisk „Pobierz”, karta zamówienia,
stopka sprzedawcy) wysyłany razem z tekstem jako `multipart/alternative`; Ustawienia → „Dane sprzedawcy” (nazwa, adres, NIP
z sumą kontrolną; Pulpit ostrzega, gdy puste); numer zamówienia i dopisek o fakturze w mailu. Powód: audyt maila 30.08 —
tekst bez brandingu i bez danych przedsiębiorcy (ustawa o prawach konsumenta art. 21 ust. 2 → art. 12 ust. 1). PR `#220`,
DECYZJE `D34`.

**Trzy liczby na `d0a45a9`** (`bash narzedzia-qa/k7-koniec.sh`, 30.08 ~16:50): KRYTYCZNE otwarte 0 · DUŻE otwarte 0 ·
kontroli FAIL **2 (z 13)** — kontrole 2 i 6, **te same co w §2**. Przelot prób kontrolnych powtórzony na czystym `d0a45a9`
(`proby-kontrolne-przelot.sh`, log `audyt/sklep/PROBY-KONTROLNE.log`): 30 prób, 6 z EXIT≠0 — **identyczny zestaw** jak na
`a1b2495` (`dowod-z-paczki` EXIT=2, `blad-klucza`, `ip-za-proxy`, `nonce`, `przejecie`, `pulapka`; porównane z logiem
w `git show HEAD:audyt/sklep/PROBY-KONTROLNE.log`). Delta 0.9.24 nie dołożyła ani jednej padającej próby i żadnej nie naprawiła
(strażniki botów — poza zakresem tej zmiany).

⛔ **Log prób został NADPISANY w miejscu** przelotem 0.9.24 (`COMMIT: d0a45a9`). Wersja z `a1b2495`, do której odsyłają §2, §5,
`rubryka-GOTOWE.md` #12 i `audyt/DO-ODDANIA.md` §2, jest w historii: `git show a1b2495:audyt/sklep/PROBY-KONTROLNE.log`
(ten sam zestaw 6 prób, inne czasy).

**Długi zapisane w `CO-WISI.md` „na 0.9.24” NIE weszły do 0.9.24** i są przedatowane na 0.9.25: trzy długi po K9
(numeracja pozycji w instrukcji nawigacji, zrzuty kroków instalacji, jedna tablica PHP jako źródło menu ze strażnikiem).
0.9.24 objęło wyłącznie mail i dane sprzedawcy — decyzja: poprawki instrukcji czekają na uwagi kolegi po odbiorze.

**Jak sprawdzona delta:** recenzja `#220` — 3 rundy świeżym agentem (r1: 2 WAŻNE + 2 DROBNE, r2: 1 WAŻNA + 3 DROBNE,
r3: 0 WAŻNYCH + 3 DROBNE → długi w `CO-WISI.md`); strażnik `testy/mail-po-zakupie-test.php` 26/26 z próbą kontrolną
(2 podłożone wady → dokładnie C2 i C6 BLAD); pełny zakup E2E na czystym WP 6.9.4 z motywem strony
(`STATUS KONCOWY: mail_wyslany`; mail w łapaczu: `Content-Type: multipart/alternative`, 1 część tekstowa + 1 HTML, 11 432 B);
render HTML w Chromium 720 i 390 px (karta 366 px na telefonie); `bramki.sh` 10/10; zgodność dokumentacji sklepu 0 rozjazdów,
próba kontrolna 21/21; `paczka-zgodnosc.sh` 67/67 sum; `pakuj-dla-klienta.sh` 17/17.
Paczka zbiorcza `wydanie/sklep-agentow-KLIENT-0.9.24.zip` sha256 `4b0fafc26363ee85f5ed4e8e407bc4fd0257c1e66f30422b165693c8a1e1b0b4`
(sklep `3c1e0cbb…`, chatbot-live 1.5.13 `699523ae…`, segregator-maila 1.3.6 `2a133d8b…` — boty bez zmian).

**Rubryka GOTOWE bez zmian** (zamrożona 0.9.23): żadne z 12 kryteriów nie mówi o wyglądzie ani stopce maila; #2 (jeden mail
z linkiem i zgodą) i #3 (link) dalej przechodzą tym samym strażnikiem, rozszerzonym o wersję HTML.

**Czego ten dopisek NIE dowodzi:** renderu w prawdziwym Gmailu/Outlooku (tylko Chromium i łapacz); ścieżki „Wyślij ponownie”
klikniętej na żywo (ten sam kod, nieklikane); zachowania wtyczek SMTP nadpisujących `AltBody`. Instalację od zera z paczki
Release przez niezależnego testera robi K10 (osobny raport).



## 7. Dopisek — wydanie `v0.9.25` (2026-08-30 wieczór; zbudowane z `main` `2992247`) + K10

**Co się zmieniło (decyzja Dzidka 30.08, oddanie 31.08 rano):** płatność **osadzona na stronie produktu** (Stripe Checkout
Session `ui_mode=elements` + Payment Element, Appearance API w brandzie strony, Stripe.js po polsku; ten sam obiekt sesji,
webhook i reconciliation; hostowana strona Stripe zostaje zapasem bez klucza publikowalnego/JS) — DECYZJE `D35`; **dane do
faktury przy zakupie** (nazwa+NIP+adres, komplet albo nic; zwiad prawny: art. 106e VAT, art. 38a upk, NIP JDG = dana osobowa)
— `D36`; nowa opcja „Klucz publikowalny”; schemat bazy 8. Do tego **naprawa wady z K10** (patrz niżej). PR `#222`, 4 rundy
(r1: 2 WAŻNE + 3 DROBNE, r2: 2 WAŻNE + 1 DROBNA, r3: 1 WAŻNA + 1 DROBNA, r4: 1 WAŻNA + 2 DROBNE — wszystkie WAŻNE naprawione,
DROBNE r4 → `CO-WISI.md`).

**K10 — instalacja od zera przez niezależnego testera** (`audyt/K10-INSTALACJA-OD-ZERA-0.9.24.md`, raport i 52 zrzuty
w `/root/do-panelu/K10-0.9.24-tester/`): 26 min zegara, ~15 min pracy z instrukcją (w limicie 30), 22 ✅ / 11 🐞 / 6 😖 / 6 ⬜.
Jedna wada PRODUKTU: „Dociągnij zaległe teraz” kliknięte 71 s po zapłacie pomijało sesję < 2 min **bez słowa**, a bez WP-Cron
(zepsuta pętla zwrotna) opłacony zakup wisiał 20 min; do tego `ORDER BY utworzono LIMIT 20` pozwalał porzuconym koszykom
blokować nowszą opłaconą sesję. **Naprawione w 0.9.25**: wynik przycisku (5 liczników) w notice i Dzienniku, ręczny przycisk pyta
Stripe o każdą sesję od razu, zadanie w tle: najnowsze najpierw + przerwa 9 min; strażnik
`testy/reconciliation-wynik-i-przerwa-test.php` 6/6 (na kodzie 0.9.24 pada w całości). Reszta 🐞 = stanowisko (limit uploadu obrazu,
brak sekretu webhooka w mojej karcie startowej, WP po angielsku) albo instrukcja (→ CO-WISI §K10).

**Trzy liczby na `2992247`** (`k7-koniec.sh`): KRYTYCZNE 0 · DUŻE 0 · kontroli FAIL **2 (z 13)** — kontrole 2 i 6 jak w §2 i §6.
Przelot prób kontrolnych powtórzony na czystym `2992247` (log `audyt/sklep/PROBY-KONTROLNE.log`; wersje z `a1b2495` i `d0a45a9`
w historii gita) — ten sam zestaw 6 prób z EXIT≠0, delta 0.9.25 nie dołożyła żadnej.

**Jak sprawdzona delta:** serwer (JSON `client_secret`, `ui_mode=elements`, metadata faktury, walidacja NIP/kompletu, zapas 302),
E2E ścieżki zapasowej z danymi faktury do `mail_wyslany` (zam #9901178), **E2E osadzony z prawdziwymi kluczami właściciela**
(konto `acct_1U5Fhg7…`): panel w brandzie, e-mail i karta 4242 w ramkach Stripe, „Zapłać” aktywne i kliknięte → Stripe
wystawia hCaptcha dla zautomatyzowanej przeglądarki (sesja `open/unpaid`; kod 3, nie wada sklepu) — **klik karty w osadzonym
formularzu potwierdza człowiek w przeglądarce** (dług w CO-WISI); strażnik maila 27/27; strażnik reconciliation 6/6; RODO krok
✓; `bramki.sh` 10/10; zgodność dokumentacji 0 rozjazdów + próba 21/21; `paczka-zgodnosc.sh` EXIT=0; `pakuj-dla-klienta.sh` 17/17.
Paczka zbiorcza `sklep-agentow-KLIENT-0.9.25.zip` sha256 `4cc66c3834a7ca2194cecac85fd349914fd3188fcdcef54722e5a82e581d2eab`
(sklep `c519c505…`, boty bez zmian).

**Rubryka GOTOWE bez zmian** (zamrożona 0.9.23): ścieżka hostowana (na której stoją dowody #1) jest nadal żywa jako zapas;
kryterium #2 (mail) przechodzi rozszerzonym strażnikiem.

**Czego ten dopisek NIE dowodzi:** kliknięcia karty w osadzonym formularzu (hCaptcha Stripe — do zrobienia ręcznie przez
właściciela/kolegę), BLIK-u w trybie osadzonym (metoda widoczna w kafelkach, nieklikana), zachowania `return_url` przy metodach
z przekierowaniem. Klucze: sklep testowy przełączony na konto właściciela (stary sandbox z 17.08 bez e-maila zostaje w sekretach
jako historia; dowody K7–K10 na nim nie giną).

## 8. Dopisek — wydanie `v0.9.26` (2026-08-30 noc; zbudowane z `main` `a3d9568`)

**Skąd ta delta:** Dzidek zapytał, czy produkt jest sprawdzony pod TEMATY z lekcji z kolegą (nie tylko pod nasz rejestr błędów).
Lista tematów odnaleziona w warsztacie (notatnik z telefonu, 9 zrzutów) → tabela `audyt/TEMATY-LEKCJI-vs-PRODUKT.md`
(17 wierszy: dowód / brak / poza zakresem). Z tabeli wyszły dwie rzeczy do zrobienia od razu: **canonical** i **SECURITY/CONTRIBUTING**.

**Co się zmieniło (PR `#225`, 4 rundy):** strony sklepu (lista i produkt) nie miały ŻADNEGO `<link rel="canonical">` na motywie
gospodarza, bo motyw robi `remove_action( 'wp_head', 'rel_canonical' )`, a nasz filtr `get_canonical_url` nigdy nie był wołany
(zmierzone `curl`: 0 znaczników) — dla Google `/sklep/produkt/x/` i `/sklep/?sklepag_produkt=x` były dwiema stronami. Wtyczka drukuje
własny canonical TYLKO gdy WP swojego nie drukuje. **Klasa `is_page( 0 )`** (recenzent r1+r2): przy pustej opcji strony sklepu
`is_page( 0 )` jest prawdą na każdej stronie — wtyczka drukowała opis/OG/canonical i ładowała CSS/JS sklepu w cudze strony; siedziało
w 3 miejscach od K5, moja naprawa dołożyła 4., a w pliku głównym były jeszcze 3 (mój grep „po naprawie” był zawężony do `includes/`
i jednego kształtu idiomu). Jeden pomocnik `sklepag_jest_strona_z_opcji()` w 7 miejscach. 404 nieistniejącego produktu bez meta listy.
DECYZJE `D37` (D10 przestarzałe). Paczka `sklep-agentow-0.9.26.zip` sha `38f78381…`.

**Rundy `#225`:** r1: 2 WAŻNE (brak paczki 0.9.26; `is_page( 0 )`) + 3 DROBNE (HEAD 200 na 404 → CO-WISI; `og:url` na 404 →
naprawione; odsyłacz w D10 → naprawione) · r2: 1 WAŻNA (klasa niedomknięta w `sklep-agentow.php` ×3 — naprawione, krok F asertuje
kolejkę assetów) + 1 DROBNA (zdanie w CO-WISI → naprawione) · r3: „do scalenia”, 3 DROBNE wzięte (martwe zmienne, krok F dla
„Dziękujemy”, readme) · r4: 1 WAŻNA o zdaniu w `readme.txt` („nie robi nic poza panelem” — skrót `[sklepag_sklep]` na własnej stronie
nadal renderuje sklep, i ma) — **przeczytana PO scaleniu** (scaliłam w tej samej komendzie, w której czytałam werdykt; własna
pamięć zakazuje tego wprost); zdanie nie szkodzi, paczka zamknięta zamkiem klasy 10 → poprawka w 0.9.27 (`CO-WISI.md`).

**PR `#226` (3 rundy, docs):** `SECURITY.md`, `CONTRIBUTING.md`, akapit o nagłówkach bezpieczeństwa i CSP ze Stripe w instrukcji
instalacji, `.gitignore` z `.env*`/`*.pem`, skaner paczki łapie odsyłacze do plików repo. Recenzent: **8 uwag WAŻNYCH, wszystkie
„tekst SECURITY vs kod”** (wersja nieistniejąca, `.gitignore` bez `.env`, „token jednorazowy” = token opinii nie pobrania, przepisana
lista bramek, brak reguły nginx dla katalogu plików, `paczka-zgodnosc` „w CI” — ręczny punkt listy wydania, „próba kontrolna podpisu
webhooka” — nie istnieje, RODO tylko dla sklepu — boty trzymają zgłoszenia/rozmowy/maile i w trybie AI wysyłają fragmenty do
dostawcy modelu). Każde zdanie poprawione z plikiem i linią. Wniosek → klasa 62 rejestru: SECURITY.md dostaje bramkę „tekst = kod”.

**Trzy liczby na `a3d9568`** (`k7-koniec.sh`): KRYTYCZNE 0 · DUŻE 0 · kontroli FAIL **2 (z 13)** — kontrole 2 i 6 jak w §2, §6, §7.
Przelot prób kontrolnych na czystym `e95de75` (docs nad `a3d9568`; drzewo z cudzym `SPRZATANIE-RAPORT.md` schowane w `git stash -u`
na czas przelotu i przywrócone 1:1; log `audyt/sklep/PROBY-KONTROLNE.log` W REPO): 31 uruchomionych, 5 z EXIT≠0 — podzbiór
zestawu 6 z logu 0.9.25 (`przejecie` chwiejna: raz pada, raz przechodzi), delta nie dołożyła żadnej nowej.

**Pomiary poza kodem (30.08):** motyw WP gospodarza NIE niesie CSP (jego statyczna strona ma CSP bez `unsafe-inline`) — gdyby
przeniósł je na hosting, płatność by padła (Stripe.js + 4 inline) → akapit w instrukcji i SECURITY §3. Statyczna sonda wydajności
strony produktu: 63 kB HTML + ~1,3 MB zasobów, z czego 1 MB `stripe.js` tylko na stronie produktu; `front.css` 83 kB (CO-WISI).
Lighthouse `perf` nadal tylko na hostingu klienta (AN-022). Kolega ma 100/100 z DOSTĘPNOŚCI i SEO, wydajność 94–98 — statyczna strona.

**Długi na 0.9.27** (`audyt/CO-WISI.md`): HEAD 200 na 404 produktu · Yoast/RankMath a własny canonical · `has_action` priorytet 0 ·
próba kontrolna podpisu webhooka · zdanie w readme o skrócie · honeypot w formularzu opinii · inline skrypty z nonce pod CSP.
