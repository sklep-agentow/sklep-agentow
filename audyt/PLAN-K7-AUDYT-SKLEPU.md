# PLAN K7 — PEŁNY AUDYT SKLEPU (wersja 2, do klepnięcia — 25.08.2026, po rundzie krytyki r1)

**Jedno zdanie:** audytujemy PACZKĘ `sklep-agentow-0.9.13.zip` zainstalowaną od zera na czystym WordPressie
(WP 6.7 / PHP 8.1 = deklarowane minimum), siedmioma pytaniami zadanymi dwa razy przez różnych, skalibrowanych
audytorów, plus przelot człowieka jako trzeci przebieg inną metodą; koniec ogłasza SKRYPT `k7-koniec.sh`
(licznik mapy · kalibracja · rejestr · strażnicy · anomalie · sha paczki), nie zegar i nie uczucie; potem jedna
runda napraw klasami, jeden wąski re-audyt przez kogoś, kto nie naprawiał, i zamrożenie do K8.

**Co zmieniła runda krytyki r1** (`audyt/KRYTYKA-PLANU-K7-r1.md`: nasz krytyk FAIL 8/12 + 4 obce modele; unia 35
pozycji, każda sprawdzona grepem w kodzie): reguły anty-pętla dostały MASZYNĘ (skrypt końca, liczniki, tag,
sha), kalibracja dostała limit powtórek i mutanty innej manifestacji, mapa dostała 7 brakujących kategorii
(zwroty, cron bez ruchu, aktualizacja w miejscu, page-cache, RODO WP-way, historia gita, workflow), a decyzje
właściciela skurczyły się do biznesowych. Fałszywe alarmy obcych modeli (SDK Stripe, prefiksy, ABSPATH,
wersja API) wchodzą do materiału jako „stan zastany — nie zgłaszaj".

Źródła: `PLAN-PROJEKTU.md` §7, §9, §10, §12 · skill `jak-audytowac` + `proces-audyt-reaudyt.md` §ROZSTRZYGNIĘCIA
21.08 · skill `sprawdzanie-zlecenia` (prompty 4a–4f + bezpieczeństwo) · `PRZEPIS-AUDYTU-A-DO-Z.md` · `PLAN-AUDYTU-BOTOW.md`
· gałąź `audyt/mutanty-kalibracja` · `SLEPE-PLAMY-NASZEGO-AUDYTU.md` · `SCIAGA-EGZAMINATORA.md` · `DESTYLAT-BLEDOW-Z-AUDYTOW.md`
· `DOKUMENTACJA-TECHNICZNA.md` §4/§5/§10 · `KRYTYKA-PLANU-K7-r1.md`.

---

## 1. CO AUDYTUJEMY — i czego NIE

**Obiekt:** paczka `boty/paczki/sklep-agentow-0.9.13.zip` (59 plików; sha256 zapisany w `audyt/sklep/ZAMROZENIE.md`
w kroku 0) zbudowana z `main` po scaleniu PR kroku 0 = **commit zamrożony, tag `k7-zamrozenie`**. Instalacja OD ZERA
wg `dokumentacja/INSTRUKCJA-INSTALACJI.md` na czystym WP (obraz `wordpress:6.7-php8.1-apache` — deklarowane minimum)
oraz na stosie warsztatowym (WP 7.1 / PHP 8.4) tam, gdzie potrzebny ruch, Stripe CLI i dane.
Boty tylko na stykach: zipy jako pliki produktów, dokumentacja botów vs kod (L5), treść stron produktów vs paczki (#10).

**Skala:** 8 842 linie PHP+JS+CSS (355 KB ≈ 90 tys. tokenów; `front.css` 2 873). Audytor dostaje SWÓJ zestaw plików.

**NIE audytujemy ponownie:** botów (21.08, mapa 36/36; otwarte tylko `CZ-1` drobne) · wyglądu (25.08; tylko powtórka
przyrządów) · architektury i stylu. **Poza K7, jawnie:** klasa 4 ściągi „Latest na Releases" — zamykana w K8 runbookiem;
nagłówki HTTP serwera, SMTP, klucze live — K8; nginx i multisite — „nie wspieramy bez reguły" (dokumentacja).

**Stan zastany, ZMIERZONY 25.08 (wchodzi do `02-fakty` jako „nie zgłaszaj jako brak"):** Stripe bez SDK i bez
zależności (`wp_remote_post`) · `Stripe-Version` przypięty (`stripe.php:31`) · wszystkie funkcje z prefiksem `sklepag_`
· `ABSPATH` w każdym PHP, `WP_UNINSTALL_PLUGIN` w `uninstall.php` · `get_charset_collate()` w dbDelta · assety admina
tylko na ekranach `sklepag` (`admin.php:96`) · `flush_rewrite_rules` przy aktywacji i deaktywacji · brak `localStorage`
· `payment_status` metod asynchronicznych obsłużony (`zakup.php:182-184`) · błędy Stripe ≥ 400 → `WP_Error` ·
historia commitów 288/288 `noreply` · brak i18n (D21) · brak kont kupujących (D-MUST NOT) · nie publikujemy na WP.org.

**Zaległości z czytania (cele mapy, nie naprawiać przed audytem):** DOK-TECH §3 „7 tabel" vs §8 „6 tabel" (dbDelta = 7),
„#170" w dokumencie klienta, tabela §2 bez `opinie.php`/`admin-opinie.php`/klas list · `REJESTR-WERYFIKACJI.md`: martwe
wpisy Cloudflare/Vercel do zamknięcia adnotacją; żywe OCZEKUJE: BLIK testowy, dostarczalność maili · lista właściciela
(`wymiana/SKLEP-LISTA-PO-BOTACH.md`, zombie cron) — L4 potwierdza wykonaniem · **formularz kupna gościa wymaga nonce
(`zakup.php:66`) → page-cache po 12–24 h = sprzedaż pada po cichu** (klasa L1 botów) — cel wysokiej wagi.

---

## 2. ZASADY NIENARUSZALNE — i CZYM każda jest egzekwowana

| # | zasada | maszyna / kto |
|---|---|---|
| 1 | **Kod zamrożony** = merge PR-0, tag `k7-zamrozenie`, sha256 zipa w `ZAMROZENIE.md`. Zmiana po zamrożeniu = bump + nowy zip + WĄSKI re-audyt klasy, nigdy restart. | `k7-koniec.sh` porównuje sha zipa i `git rev-parse k7-zamrozenie` z tym, na czym szły rundy |
| 2 | **Dokładnie DWA przebiegi (A, B) + człowiek.** Unia, nie głosowanie. Czwartego nie ma. | rundy zapisane w `REJESTR` z SHA; skrypt odrzuca raport z innym SHA |
| 3 | **Audyt nie naprawia.** Komplet znalezisk (A+B+weryfikacja) → JEDNA runda napraw klasami → JEDEN wąski re-audyt (kto nie naprawiał). | audytor dostaje paczkę rozpakowaną `chmod -R a-w`; po rundzie `sha256sum` katalogu == zip (skrypt) |
| 4 | **Dwie próby naprawy** na znalezisko; trzeciej nie ma (`git reset --hard`, inne podejście). **Trzeci powrót klasy = STOP.** | pole `naprawa_proby` w rejestrze; `k7-koniec.sh` FAIL przy `> 2`; licznik klas w `PETLA-UCZENIA` |
| 5 | **Koniec = LISTA:** mapa 0 ⬜ · kalibracja PASS każdej kalibrowanej soczewki · 0 KRYTYCZNE/DUŻE otwarte · 0 DO WERYFIKACJI · 0 anomalii OTWARTYCH · strażnik z dowodem przy każdym ZAMKNIETE · przelot człowieka komplet · próby kontrolne komplet · sha paczki zgodne. | **`narzedzia-qa/k7-koniec.sh`** (spec §8b), z własną próbą kontrolną |
| 6 | **Recenzent CI max 2 rundy**, trzecia → `gh run cancel`; uwaga = fakt DO SPRAWDZENIA. | dyscyplina koordynatorki — JAWNIE bez maszyny; pole `rundy_recenzji` w rejestrze PR |
| 7 | **Mapa rośnie tylko z „czego nie było na mojej liście"**: cele z rundy A wchodzą do rundy B; **po B mapa ZAMROŻONA** (nagłówek z SHA i liczbą celów); nowe po B → „poza K7" z decyzją właściciela. | skrypt: liczba celów po zamrożeniu == zapisana |
| 8 | **Werdykt bez pliku:linii nieważny; `NIE SPRAWDZIŁEM` domyślny; zero z grepa = próba kontrolna.** | parser raportów JSON (`jq`): SPEŁNIONE bez `plik:linia` → odrzucone; BRAK bez pola `proba_kontrolna` (komenda + wyjście) → odrzucone |
| 9 | **1 klasa = 1 PR.** | dyscyplina — JAWNIE bez maszyny (recenzent widzi diff) |

**Anomalia (definicja, żeby dało się liczyć):** każde `NIE SPRAWDZIŁEM`, `NIE DA SIĘ SPRAWDZIĆ`, nieoczekiwane wyjście
komendy albo rozjazd dwóch pomiarów. Dostaje ID `AN-nnn` w rejestrze i status: `OTWARTA` → `WYJAŚNIONA (dowód)` /
`CEL (dopisana do mapy)` / `POZA K7 (decyzja właściciela, data)`. GO wymaga zero `OTWARTA`.

---

## 3. KTO — role

| rola | kto / czym | dostaje | nie wolno |
|---|---|---|---|
| **Kierownik audytu** | ja (ten czat) — nadzoruję, odhaczam mapę SKRYPTEM, nie audytuję | wszystko | audytować własnego kodu; odhaczać z pamięci |
| **7 audytorów rundy A** | świeże subagenty `model:"sonnet"`, jeden prompt = jedna soczewka (napisany PRZED rundą, „jeden prompt = komplet"), budżet 30 wywołań, wyjście JSON ze schematem + sekcja „czego nie było na mojej liście" + KROKI REPRODUKCJI przy każdym znalezisku | materiał 01–07 + SWÓJ zestaw plików (rozpakowana paczka tylko-odczyt) + klasy błędów z przeciwwagą; L2 dodatkowo: konto `subscriber` + przepis na nonce (`wp eval`); L3/L4/Ł: docker/wp-cli/curl/SQL/Mailpit | naprawiać · zgłaszać bez dowodu · wiedzieć o kalibracji |
| **7 audytorów rundy B** | inne instancje, paczka CZYSTA, materiał w OSOBNYM katalogu bez raportów A (`find` przed startem), rama OBALAJĄCA: „udowodnij, że paczka NIE nadaje się do oddania"; jeden z nich = **egzaminator-symulant** ze `SCIAGA-EGZAMINATORA.md` | jw. | widzieć raporty A |
| **Weryfikator** | osobny subagent + żywy stos | znaleziska Z KROKAMI REPRODUKCJI (nie kontekst czatu) | uznać bez DOWODU WYKONANIA; obalić bez pokazania, że scenariusz nie zachodzi |
| **Builder napraw** | ja, po `/clear` ról (1 builder przy wspólnych plikach) | rejestr + klasy | egzemplarz zamiast klasy; trzecia próba |
| **Re-audytor wąski** | świeży subagent, kto NIE naprawiał | ten sam komplet materiału + rejestr | okrojony materiał |
| **Recenzent CI** | claude-code-action (doradca) | PR | trzecia runda |
| **Właściciel** | właściciel: przelot ~40 min (kupujący + właściciel, lista ~25 pkt z 3 krokami klawiaturowymi) przez tunel po moim „bezpiecznie"; czyta wynik `k7-koniec.sh` | lista + 3 liczby | spory techniczne |

Kto klika, kto czyta (Z5): L2, L3, L4, Ł = WYKONANIE na stosie; L1, L5 = czytanie + cytaty; L6 = przyrządy + człowiek.

---

## 4. SIEDEM PYTAŃ — CO pytamy × CZYM patrzymy × prompt (istniejący albo pisany w kroku 1)

| # | soczewka | PYTANIE (o awarię) | czym | prompt |
|---|---|---|---|---|
| **L1** | ZGODNOŚĆ Z ZAMÓWIENIEM | per wiersz S-01…S-20 + 12 kryteriów §12: SPEŁNIONE/INACZEJ/BRAK/NIE SPRAWDZIŁEM/NIE DA SIĘ, pole **JAK CZŁOWIEK TEGO UŻYWA**; „kiedy kupili" (S-04): gmdate vs czas lokalny w pulpicie/CSV/mailu | czytanie + kliknięcie + Mailpit tylko-odczyt | 4a — SUROWE zdania + MAPA-SPEC + kod; ⛔ bez ZROZUMIENIA/README |
| **L2** | BEZPIECZEŃSTWO + RODO (atakujący) | omijanie: nonce+uprawnienia (7 handlerów, z kontem subscriber), podpis webhooka (zły/powtórzony/spóźniony), cena z żądania, brama pobrań (termin/limit/cudzy token/HEAD/dwa naraz), limity ZMIERZONE + `sklepag_ip()` za proxy, katalog chroniony bezpośrednio + path traversal, `LIKE`/`esc_like`, XSS (opisy, imię/treść opinii, tabele), wstrzyknięcie nagłówków maila; **martwy nonce pod page-cache = zakup gościa pada**; lockout WŁAŚCICIELA po pomyłkach (sufit 60 s, brak twardej blokady — zmierzyć); RODO: anonimizacja we WSZYSTKICH tabelach (zamówienia, opinie, zdarzenia, dziennik IP), sekrety poza logami/CSV/`mail_tresc`, uninstall kasuje sekrety ZAWSZE, **eksporter/eraser WP (`wp_privacy_personal_data_*`): jest/nie ma**; workflow: `pull_request_target`, akcje po SHA, uprawnienia | **wykonanie**: curl, równoległe, SQL, konto subscriber | „bezpieczeństwo" + `bezpieczenstwo-danych-klienta` + oficjalny `/security-review` (osobny przebieg) + 4c (workflow) |
| **L3** | PIENIĄDZE: DOWÓD SKUTKU + WYŚCIG + PO SPRZEDAŻY | webhook ×2; webhook ‖ cron (żądania równoległe — wzór `Promise.all` z `testy/nonce-cache-test.js`); przerwanie przed mailem → RESUME; `mail_blad`/`mail_w_toku` → cron; resend: stary 403/nowy 200; 2 pobrania przy ostatnim slocie → 1; HEAD nie zużywa; wynik `insert/update` sprawdzany; **Stripe 401/403/429/5xx/timeout → co widzi kupujący i właściciel; zły/pusty klucz = sygnał w panelu, nie cicha awaria; zmiana sekretu webhooka; `payment_status=unpaid` (BLIK testowy) nie domyka; ZWROT (refund/dispute) → status, link, KPI**; snapshot produktu; kryteria #2–#5 przelotem `przelot2.js` ×2 | czytanie + **wymuszone awarie** w kontenerze + Stripe CLI | 4d + 4e |
| **L4** | DANE: CO ZOSTAJE, CO PRZEŻYWA | **aktualizacja W MIEJSCU na żywych danych**: paczki starych wersji z gita (0.2.0 = schemat 3, 0.5.0 = 4, 0.9.8 = 5) → instalacja → zakupy → wgranie 0.9.13 „zastąp obecną" → dane/ustawienia/katalog chroniony/cron; rollback = zachowanie ZDEFINIOWANE (kopia zapasowa w instrukcji); uninstall obiema ścieżkami (diff pusty; zombie cron z listy właściciela); cykl deakt→akt; retencja 90 dni tylko stare; właściciele opcji/tabel/cronów; INNER JOIN opinii bez zamówienia; **WP-Cron bez ruchu** (Z6: pojedyncze zakupy — czy druga droga w ogóle się wykona; `DISABLE_WP_CRON` + cron systemowy w instrukcji); długości kluczy pod utf8mb4; `stos.sh` okno na `wp_sklepag_*` | **SQL + wp-cli**, od strony ZAPISU | 4f + 4c (instalacja od zera) |
| **L5** | DOKUMENTACJA I PACZKA vs KOD (dwa kierunki) | liczby i OBIETNICE z readme/CHANGELOG/DOK-TECH/INSTRUKCJI/stron/maila/szablonów → kod; ODWROTNIE: funkcje z inwentarza punktów wejścia → dokumentacja; komendy WPISANE; paczka od środka: bramka `slady` (TODO/FIXME/notatki QA/adresy/PR/nazwiska) + **oficjalny `plugin-check` na 3 zipach** + `gitleaks --log-opts=--all` (pełna historia) + `git log --format=%ae` (dziś 288/288 noreply — zapis) | porównanie + wykonanie | prompt L5 PISANY w kroku 1 (wzór: A6 botów) |
| **L6** | FRONT / UX — przyrządy + człowiek (NIEKALIBROWANA) | `lighthouse-3-podstrony.sh` (z `blog_public=1`), `axe … --bez-ruchu`, `szelki-strona-produktu.js` ×2 (chromium + firefox), sonda pigułki; przelot człowieka | klikanie | 4b (lista ścieżek) + `audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md` (pisany w kroku 1) |
| **Ł** | ŁAMACZ | baza pada w połowie zakupu · Stripe 5xx/timeout **z wyrocznią: po każdej awarii `SELECT` z `zdarzenia` — oczekuję wpisu** · plik skasowany z dysku · permalinki „Zwykłe" · **page-cache**: strona produktu z noncem sprzed 25 h → zakup · seed 10 k wierszy dziennika + 1 k zamówień → czas pulpitu, brak N+1 · 500 żądań · WP_DEBUG zero notice na czystym WP 6.7/PHP 8.1 · zip 20 MB+ · dwa admin-posty naraz | **wykonanie** | prompt Ł PISANY w kroku 1 (wzór: łamacz botów) |

Poza soczewkami: **audyt strażników CI mutacjami jako skrypt** — jeden przebieg wszystkich `*-proba-kontrolna.sh` (26) z tabelą
EXIT + **cel BS4: dla każdej bramki „nazwa ↔ to, co naprawdę liczy"** (klasa z `SLEPE-PLAMY`, świeżo popełniona przez plan v1).

---

## 5. KALIBRACJA — odważnik z limitem powtórek

**Reguła zdania (ROZSTRZYGNIĘCIA 21.08 §2–§3):** jeden PRAWDZIWY mutant na klasę, którą audytor ma łapać, + ≥1 FAŁSZYWY
na 2 prawdziwe. **PASS = 100 % mutantów WŁASNEGO zakresu + zero alarmów na pułapkach + poprawna KWALIFIKACJA.**
Wobec rubryki §12 („≥2/3"): 100 % zawiera się w „≥2/3", ale zmiana reguły idzie zgodnie z §12 ostatni akapit —
**wpis D33 w `DECYZJE.md` w PR kroku 0**: „K7: kalibracja 100 % własnego zakresu (ostrzej niż §12), mechanika gałęzi
mutantów zamiast `kalibruj.sh` (skrypt liczy stare 2/3 — dług warsztatu, nieużywany w K7)".
**Limit powtórek** (⭐ zmieniony 4.09 wpisem `D39` w `DECYZJE.md`; niżej brzmienie pierwotne, bo reguła
zmienia się jawnym wpisem, nie po cichu): **do 2 prób bez pytań; próby 3 i 4 WYŁĄCZNIE z wypełnioną
kolumną `powod_powtorki` w tabeli rozliczenia (zaślepka „-”, „?” albo napis krótszy niż 20 znaków = brak
powodu); powyżej 4 bramka pada bezwarunkowo.** Powód zmiany: reguła „2 i koniec” w parze z warunkiem 2
`k7-koniec.sh` (każdy wiersz `PASS` ORAZ `proby ≤ 2`) żądała stanu NIEOSIĄGALNEGO — soczewka raz oblana
nie miała żadnej drogi do `PASS` poza wpisaniem nieprawdy. ⛔ ZAKAZ uczenia audytora pod mutanta
obowiązuje bez zmian.

**Brzmienie pierwotne (do 4.09):** audytor, który nie trafił → JEDNA poprawka promptu/materiału i jeden ponowny przebieg. Drugi FAIL →
soczewka oznaczona `NIEKALIBROWANA` (jej „czysto" nie liczy się do GO; jej znaleziska liczą się normalnie), mutant
sprawdzany jako wadliwy (czy w ogóle wykrywalny tym pytaniem). Tabela rozliczenia ma pole `proby`; skrypt FAIL przy `> 2`.
⛔ Audytor nie wie o kalibracji. Mutant trafiony przez CUDZĄ soczewkę = znalezisko tej soczewki (unia), nie zmienia
kalibracji soczewki przypisanej.

**Mechanika** (od 31.08 #238 bank żył jako TAG; ⭐ **od 4.09 ŹRÓDŁEM rozliczenia jest GAŁĄŹ `origin/audyt/mutanty-sklep`**, a tagi `bank/mutanty-sklep` (rundy A) i `bank/mutanty-sklep-r2` (rundy weryfikacji) to ZAMROŻONE MIGAWKI — awaryjny fallback dla klonu bez gałęzi. ⛔ Nowego tagu przy każdym commicie rozliczenia NIE robimy: to rodzi dokładnie ten rozjazd dwóch źródeł, który kolejność refów ma usuwać; niżej historyczny opis budowy): gałąź `audyt/mutanty-sklep` z `k7-zamrozenie`; `podloz-mutanty-sklep.sh` (wzór z `audyt/mutanty-kalibracja`:
kotwica = WZORZEC, jeden commit na mutanta); `ROZLICZENIE-MUTANTOW-SKLEP.md` COMMITOWANA NA GAŁĘZI (bank botów „poza
repo" zniknął). Runda A na paczce Z GAŁĘZI MUTANTÓW (znaleziska poza liniami mutantów są prawdziwe), runda B na czystej.

**Bank (inna manifestacja niż literalny cel mapy; dokładne kotwice przy budowie):**
- M1 · L1 — dziennik przestaje zapisywać NIEUDANE próby logowania (S-09 „kto, kiedy, konkrety" milczy o porażkach).
- M2 · L2 — w jednym handlerze panelu znika `current_user_can`, nonce zostaje. Oczekiwana kwalifikacja: „bez nonce nieeksploatowalne, para złamana — DUŻE, nie KRYTYCZNE".
- M3 · L3 (wyścig/dwa silniki) — przebieg crona regeneruje token pobrania także dla `mail_wyslany` → link z maila umiera po cichu.
- M4 · L3 (dowód skutku) — `$wpdb->update` przy ręcznym resendzie bez sprawdzenia wyniku + komunikat sukcesu zawsze.
- M5 · L4 — nowa opcja zapisywana w samonaprawie pulpitu, nieusuwana w `uninstall.php`.
- M6 · L5 — obietnica behawioralna: dokumentacja mówi „HEAD nie zużywa limitu", kod zaczyna liczyć HEAD.
- M7 · Ł — błąd Stripe (`WP_Error`) połknięty bez wpisu w `zdarzenia`; wyrocznia w prompcie Ł.
- F1–F4 (poprawne, podejrzane): zdublowane sprawdzenie uprawnień · krótsze okno limitu (bezpieczniej) · obronny `isset` · `phpcs:ignore` z uzasadnieniem.
- L6 = niekalibrowana (przyrządy deterministyczne + człowiek) — jawnie.

---

## 6. MATERIAŁ — siedem typów jako LISTA KONTROLNA (krok 1, ~1 dzień)

`audyt/sklep/material/`: `01-zamowienie` (SUROWE zdania + S-01…S-20 ze statusami + 12 kryteriów; bez streszczeń) ·
`02-fakty-srodowiska` (zmierzone: dwa stosy, wersje, Apache, Stripe test, łapacz, `blog_public=0` „nie zgłaszaj", Z6,
**STAN ZASTANY z §1**, **inwentarz punktów wejścia generowany grepem** — `admin_post_`, `register_rest_route`,
`add_shortcode`, `wp_ajax_`, `add_rewrite_rule`, crony — każdy z soczewką-właścicielem) · `03-zalozenia` (D1–D32 + MUST
NOT: konta, faktury, przeceny, i18n, multisite, WP.org, nginx) · `04-goldeny` · `05-reguly-branzy` (2011/83/UE art. 16 m
+ 8.7 b, RODO, Stripe: podpis/idempotencja/kolejność/`payment_status`, wytyczne recenzentów WP.org) · `06-mapa-polowania`
(cele + licznik skryptem) · `07-mapa-dokumentacji` (WEJŚCIE · WG PYTANIA · ARCHIWALNE · CZEGO NIE MA · „wygrywa KOD").
Klasy Z PRZECIWWAGĄ: ściąga 7 + K1–K3/BS1–BS4 + P1–P7 + 4 rodziny. Prompty L5 i Ł + `PRZELOT-CZLOWIEKA-SKLEP.md` pisane TU.
Zestawy plików: L1 — pliki wskazane przez S-xx (nie „cały includes") · L2 — `zakup, stripe, pobieranie, opinie, logowania,
admin*, uninstall` + `.github/workflows` · L3 — `zakup, stripe, pobieranie, opinie, admin-zamowienie, admin-dashboard` ·
L4 — `aktywacja, uninstall, zakup (cron), admin-ustawienia, admin-dashboard` · L5 — dokumenty + cały kod jako źródło
grepów · L6 — front + assety · Ł — wszystko bez CSS.

---

## 7. MAPA POLOWANIA — cele (licznik w pliku 06; ≈ 95)

**L1 (12):** S-01…S-09, S-14, S-15, S-18/19 · kryteria #1–#11 · strefa czasu „kiedy kupili".
**L2 (22):** 7 handlerów nonce+uprawnienia (subscriber) · `kup` nonce/zgoda/limit · `pobierz` token/termin/limit/HEAD/dwa naraz ·
`ocen` GET/POST · webhook zły/powtórzony/spóźniony, poza limiterem · `/status` · cena z bazy · limity zmierzone + proxy ·
katalog chroniony + traversal · `prepare`/`esc_like` · XSS ×3 · nagłówki maila · **martwy nonce pod cache** · lockout właściciela ·
sekrety poza logami/CSV/mailem · anonimizacja ×4 tabele · uninstall sekrety+cron · **eksporter/eraser WP** · `/security-review` ·
szablony prawne bez cudzych danych · workflow (`pull_request_target`, SHA, uprawnienia).
**L3 (19):** webhook ×2 · webhook ‖ cron · RESUME · `mail_blad`/`mail_w_toku` · resend 403/200 · 2 pobrania → 1 · HEAD ·
termin · timeout 15 s · `insert/update` ×3 · budżet 8/15 s · snapshot · produkt nieaktywny/bez pliku · #2–#5 `przelot2.js` ×2 ·
**Stripe 401/429 → co widzi człowiek** · **zły/pusty klucz = sygnał w panelu** · **zmiana sekretu webhooka** · **`payment_status=unpaid`** ·
**zwrot → status/link/KPI**.
**L4 (13):** **aktualizacja w miejscu z 3 starych wersji** · **rollback zdefiniowany** · uninstall domyślny/pełny · zombie cron ·
deakt→akt · retencja · właściciele · INNER JOIN · **WP-Cron bez ruchu** · utf8mb4 klucze · `stos.sh` okno · `LINIA-BAZOWA`
ponownie (regresje 0, 3 czerwone wyjaśnione) · migracje przy PHP 8.1.
**L5 (13):** DOK-TECH 6/7, „#170", tabela §2 · readme liczby · INSTRUKCJA etykiety/Krok 0/komendy · CHANGELOG = wersja ·
liczby stron produktów vs paczki botów · kierunek odwrotny z inwentarza · mail vs instrukcja · szablony prawne · bramka `slady` ·
**`plugin-check` ×3** · **gitleaks historia** · e-maile commitów (zapis) · readme Segregatora „Wymagania" (odnotować).
**L6 (6):** Lighthouse ≥ 95 ×4 · axe 0 · szelki 10/10 ×2 przeglądarki · sonda pigułki · przelot człowieka komplet · 3 kroki klawiaturowe.
**Ł (10):** baza pada · Stripe 5xx z wyrocznią · plik skasowany · permalinki · **page-cache/nonce** · **seed 10 k/1 k → czas pulpitu** ·
500 żądań · WP_DEBUG na 6.7/8.1 · zip 20 MB+ · dwa admin-posty naraz.
**Strażnicy CI (2):** 26 prób kontrolnych jednym przebiegiem · BS4: nazwa bramki ↔ pomiar.
Licznik mówi o POKRYCIU, nie o jakości: 95/95 = o każdy cel ktoś zapytał z dowodem.

---

## 8. PRZEBIEG — kroki i szacunek (NIE obietnica; termin 1.09, próba oddania ~31.08)

| krok | co | kto | kiedy |
|---|---|---|---|
| **0** | PR-0 (paczka 0.9.13 + D33 w DECYZJE) → merge → tag `k7-zamrozenie` → `ZAMROZENIE.md` (SHA, sha256 zipa) · zrzut bazy · **odcisk wartości** (liczby pulpitu, produkty, treść maila, Lighthouse) · `stripe listen` · siteurl localhost (zrobione 25.08) | ja | 26.08 rano (1 h) |
| **1** | MATERIAŁ 01–07 + inwentarz punktów wejścia + prompty L5/Ł + przelot człowieka + zestawy plików + paczki starych wersji z gita (3) + `k7-koniec.sh` z próbą kontrolną + konto subscriber + `plugin-check` na stosie | ja + 1 subagent do przepisania dosłownego | 26.08 (dzień) |
| **2** | KALIBRACJA: gałąź mutantów (7+4), tabela, paczka z mutantami; czysty stos 6.7/8.1 | ja | 26.08 wieczór (1,5 h) |
| **3** | RUNDA A: 7 audytorów po 3–4 naraz, na paczce Z MUTANTAMI (czysty stos: L1/L4/Ł; stos warsztatowy: L2/L3/L5) | 7 × sonnet | 27.08 rano |
| **4** | rozliczenie kalibracji (limit 1 powtórka) → RUNDA B (rama obalająca, egzaminator-symulant, katalog bez raportów A) | 7 × sonnet | 27.08 popołudnie |
| **5** | WERYFIKACJA (kroki reprodukcji) → `REJESTR-ZNALEZISK-SKLEP.md` (`SK-L2-003`, waga, klasa, status, SHA, `naprawa_proby`, `straznik`, `straznik_padl_na_starym`) + anomalie `AN-nnn`; **mapa ZAMROŻONA** | 1–3 × sonnet + ja | 28.08 rano |
| **6** | NAPRAWY KLASAMI: 1 klasa = 1 PR (recenzent max 2 rundy) + STRAŻNIK z dowodem padania na starym kodzie + sweep „co przestało być prawdą" | ja | 28.08 popołudnie – 29.08 |
| **7** | RE-AUDYT WĄSKI (kto inny) + klamra wartości + przyrządy L6 + **PRZELOT CZŁOWIEKA** | 2–4 × sonnet + właściciel | 30.08 |
| **8** | `k7-koniec.sh` = GO → bump → CHANGELOG → paczki ×3 → `paczka-zgodnosc` ×3 → K8 (instrukcja, nagłówki HTTP, regulamin, release, „Latest" oczami, POBRANIE) | ja + właściciel | 31.08; bufor 1.09 |

**8b. `narzedzia-qa/k7-koniec.sh` — spec (budowany w kroku 1, z próbą kontrolną, EXIT 0 tylko gdy WSZYSTKO):**
1) `06-mapa-polowania.md`: ⬜ = 0, LICZNIK w pliku == policzony (`mapa-licznik.sh --tylko-licz`, nieznany znacznik = FAIL) i liczba
celów == zapisana po zamrożeniu; 2) `ROZLICZENIE-MUTANTOW-SKLEP.md` z tagu `bank/mutanty-sklep` (od 31.08, #238; wcześniej z gałęzi `audyt/mutanty-sklep` (lokalnej albo `origin/`)): każda
kalibrowana soczewka PASS, `proby ≤ 2` (`\|` w komórkach osłonięte); 3) rejestr: 0 wierszy KRYTYCZNE/DUŻE ze statusem ≠
ZAMKNIETE/OBALONE/POZA K7, 0 `DO WERYFIKACJI`, `naprawa_proby ≤ 2`, każdy ZAMKNIETE ma `straznik` (plik istnieje) i
`straznik_padl_na_starym` (sha); 4) anomalie: CAŁA sekcja `## Anomalie`, każdy wiersz z ID `AN-NNN`, 0 `OTWARTA`;
5) `PRZELOT-CZLOWIEKA-SKLEP.md`: ≥ 27 punktów, 0 ⬜, 0 😖, LICZNIK == policzony (`mapa-licznik.sh --przelot`); 6) próby kontrolne:
≥ 26 pozycji, wszystkie EXIT 0 (log); 7) `ZAMROZENIE.md`: sha256 zipa == plik, `git rev-parse k7-zamrozenie^{commit}` == COMMIT;
8) parser raportów JSON: `commit_head` == COMMIT, 0 werdyktów SPEŁNIONE/ZGODNE bez `plik:linia`, 0 BRAK bez `proba_kontrolna`,
0 znalezisk bez `kroki_reprodukcji`, sekcja `czego_nie_bylo_na_mojej_liscie` niepusta, `werdykt` i `rodzaj_skutku` ze słownika
`SCHEMAT-RAPORTU.json`. Wynik = 13 linii PASS/FAIL (8 warunków, podpunkty 1a/1b/3a–3d/7a/7b) + trzy liczby dla właściciela. (Spec = to, co skrypt 1.3.2 mierzy.)

**8c. Kolejność wg budżetu (25.08, reset limitu w niedzielę 30.08, zostało ~38 %):** środa 26 — kroki 0–2 · czwartek 27 —
runda A (7 soczewek, po 3–4 naraz) · piątek 28 — weryfikacja A + rejestr + naprawy KRYTYCZNYCH + strażniki · sobota 29 —
naprawy DUŻYCH, jeśli limit < 90 %; inaczej tylko ręczna robota · niedziela 30 (po resecie) — runda B (L2/L3/L4 obowiązkowo,
L1/L5/Ł jeśli starczy) + weryfikacja · poniedziałek 31 — re-audyt wąski + przyrządy L6 + przelot człowieka + K8 + **próba 1
u Manika** · wtorek 1.09 — liczba od Manika → polowanie na N + próby 2–3 hotfixami. Mapa zamrażana po B (albo po A, jeśli B
nie ruszy przed próbą 1 — wtedy jawnie: „audyt jednoprzebiegowy + człowiek").
**Koszt:** ~32 wywołania subagentów sonnet + koordynacja. Punkt odniesienia: 23.08 ≈ 23 % limitu tygodniowego w jeden dzień;
K7 = 3–4 takie dni. Limit dziś **61 %** → decyzja nr 1. Dryf > 1 dnia → tniemy L6 i Ł-perf, NIGDY L2/L3/L4.

---

## 9. ŚRODOWISKO — gotowe PRZED rundą A

- Stos warsztatowy (`strona_wp_*`, :8890, WP 7.1/PHP 8.4/Apache; `sklep_mailpit` :8025; `segm_imap`); ⚠️ po reboocie serwera
  kontenery nie wstają same (`restart: no`) — `docker compose up -d --wait` + `docker start segm_imap`.
- Czysty stos: obraz `wordpress:6.7-php8.1-apache` + `mariadb` (jak K6), włączany na czas L1/L4/Ł; `free -m` przed startem;
  audytorów po 3–4 naraz (3,8 GB RAM).
- `stripe listen` (nie chodzi) — wznowić przed L3. Tunel martwy; siteurl = localhost; nowy tunel na przelot człowieka.
- `plugin-check` (oficjalna wtyczka WP.org) instalowana na stosie warsztatowym `wp plugin install plugin-check`.
- Wtyczka page-cache na czystym stosie tylko na czas celu Ł (i po nim usunięta).
- Lighthouse/axe zainstalowane — ⛔ nie instalować ponownie.
- Czysty stos pod rundę stawia `bash narzedzia-qa/k7-stos-rundy.sh <zip> [wzorzec]` (1.0.2): kontener `probny_wp` :8891, baza `wp_probny`,
  konta `audytor-admin`/`audytor-sub` z hasłami dowiedzionymi `check-password`, bramka wersji zip == kontener, debug.log 0; EXIT 0 = stoi.
  ⚠️ Zmierzone 26.08 (runda A): `wp db query` NIE działa w kontenerach (brak klienta mysql) — SQL przez `docker exec strona_wp_db mariadb`;
  na :8890 opcje Stripe są PUSTE; kontener probny po reboocie nie wstaje sam.

---

## 10. DECYZJE WŁAŚCICIELA (biznesowe; techniczne rozstrzygam sama i melduję)

**ROZSTRZYGNIĘTE 25.08 ~22:00** — właściciel: „3 tak, 4 tak, reszta sama myśl; kredyty jeszcze mamy, reset w niedzielę;
do 1.09 trzy próby oddania u Manika, po każdej tylko LICZBA błędów, nie jakie":
1. Limit: reset w niedzielę 30.08, zostało ~38 % → **wariant PRZED/PO RESECIE** (moja decyzja): przed niedzielą kroki 0–3
   + weryfikacja rundy A + naprawy KRYTYCZNYCH; po niedzieli runda B (co najmniej L2/L3/L4, reszta jeśli starczy),
   pozostałe naprawy, re-audyt wąski, przelot człowieka, K8, próba 1 ~31.08, próby 2–3 = 1.09 hotfixami.
   Bezpiecznik: limit ≥ 90 % przed niedzielą → zero agentów, tylko moja ręczna robota. ⭐ Liczba błędów od Manika po
   próbie 1 = odważnik audytu (jego N kontra nasza lista).
2. PR-0 — klepnięte („reszta sama myśl"); cykl PR prowadzę sama. 3. Sprzątanie — TAK. 4. Eksporter/eraser RODO WP — TAK,
   w rundzie napraw. 5. Zwroty — moja decyzja PO L3: jeśli dziś nic się nie dzieje → status „zwrócone" ustawiany z widoku
   zamówienia (gasi link, odejmuje z KPI) + zdanie w instrukcji; webhook zwrotu tylko, jeśli koszt < 2 h. 6. Przelot
   człowieka: niedziela 30.08 wieczór albo poniedziałek 31.08 rano — właściciel wybiera godzinę. 7. Tunel: localhost; nowy na przelot.

*(Pierwotna lista pytań — dla historii:)*

1. **Limit tygodniowy (61 %).** `/usage` → kiedy się odnawia. Odnowienie ≤ 29.08 → wariant PEŁNY. Później → OSZCZĘDNY:
   L2+L3+L4 w dwóch rundach, L1/L5/Ł jedną rundą z weryfikacją, L6 przyrządy; tracimy drugie oko na zgodności i dokumentacji.
   Rekomendacja: PEŁNY, jeśli limit pozwala.
2. **Klepnięcie PR-0** (paczka 0.9.13 + D33) — jedno słowo; cykl PR prowadzę sama.
3. **Sprzątanie** (kasowanie = zgoda): 3 martwe worktree w `/tmp`, 4 gałęzie lokalne po scaleniu, 3 pliki mediów (1,06 MB)
   przy naprawach. Rekomendacja: TAK na wszystko.
4. **Zakres: RODO WP-way** — czy dopisać eksporter/eraser danych osobowych do narzędzia prywatności WordPressa (dwa hooki,
   ~2 h; egzaminator-programista WP tego szuka). Rekomendacja: TAK, w rundzie napraw.
5. **Zakres: zwroty** — jak sklep ma się zachować po zwrocie w Stripe (gwarancja 14 dni): (a) webhook zwrotu → link
   pobrania gaśnie, status „zwrócone", KPI odejmuje; (b) tylko ręcznie + zapis w instrukcji. Rekomendacja: (a) jeśli
   audyt L3 pokaże, że dziś nic się nie dzieje — inaczej obietnica gwarancji na stronie jest bez mechanizmu.
6. **Termin przelotu człowieka** — proponuję 30.08.
7. **Tunel** na czas audytu = localhost (już tak jest po reboocie); nowy tunel na przelot.

---

## 11. CZEGO TEN PLAN NIE ZAŁATWIA (uczciwie)

- Chroni przed klasami, które ZNAMY (+7 kategorii z r1). Nowa klasa od kolegi = mutant do banku; ta sama czwarty raz = wpadka.
- LLM-audytor = generator hipotez; bramką jest test z `exit 1` i `k7-koniec.sh`.
- Środowisko kolegi (hosting, SMTP, nginx) — poza audytem; dokumentacja mówi „nie wspieramy bez reguły".
- Dyscyplina bez maszyny (jawnie): 2 rundy CI, 1 klasa = 1 PR.
- Dług WARSZTATU (do CONTEXT-WARSZTAT): `kalibruj.sh` liczy 2/3 · `sprawdzanie-zlecenia/SKILL.md` „trzy prompty" (jest 7) ·
  nagłówek banku „19" (jest 44) · `prompty-audytorow.md` cytuje wycofaną liczbę „6 klas w 18 minut" · README banku botów
  wskazuje nieistniejący plik.
