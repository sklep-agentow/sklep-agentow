# K11 — PRZELOT WERYFIKACYJNY NA CZYSTO (v0.9.27)

Audytor: świeże oko, bez wiedzy o powstawaniu napraw. Stanowisko: świeży klon tagu `v0.9.27`
w `/root/praca/zlecenia/audyt-k11-0927/sklep-agentow` (`git describe --tags` → `v0.9.27`, HEAD `6a7922c`,
drzewo czyste przez cały przelot). Stos: wspólny `strona_wp_wordpress` (:8890), `strona_wp_db`,
`sklep_mailpit` (:8025), `stripe listen` czynny (PID 1453817).
Data przelotu: 2026-08-31, ok. 01:15–02:10.

**Przyrządy dołożone do klonu (nie do produktu):** `composer install` (vendor/ — phpcs, phpstan)
oraz `npm install --no-save playwright-core axe-core` (node_modules/). Oba katalogi są w `.gitignore`,
`git status --porcelain` pusty przed i po. Niczego nie naprawiałem, nie commitowałem, nie otwierałem PR.
Log przelotu prób kontrolnych skierowałem do katalogu roboczego POZA repo, żeby nie nadpisać
`audyt/sklep/PROBY-KONTROLNE.log` w klonie.

---

## 1. Tabela: krok → komenda → wynik/EXIT → werdykt

| # | Komenda | Wynik (skrót) | EXIT | Werdykt |
|---|---|---|---|---|
| 1a | `git log -1 --oneline` | `6a7922c fix(sklep): długi 0.9.27 — 404 na HEAD, strażnik podpisu webhooka, wyjątek Yoast/RankMath… (#228)` | 0 | ZGODNY |
| 1b | `git describe --tags` | `v0.9.27` | 0 | ZGODNY |
| 1c | `sha256sum boty/paczki/sklep-agentow-0.9.27.zip` vs Release | lokalnie `459f3b8c…f9dc4`; `gh release view v0.9.27 --json assets` → `digest sha256:459f3b8cb9b635b4ad2ae59e83231db946ab90e513ee3b273a4b927bebef9dc4` — **identyczne** | 0 | ZGODNY (uwaga: sumy NIE MA w `--json body`; wzięta z `digest` zasobu Release — patrz Obserwacja O-1) |
| 2 | `bash narzedzia-qa/bramki.sh --tryb pelny` | `WYKONANE BRAMKI: 10 z 10 · WSZYSTKIE WYKONANE BRAMKI ZIELONE` | **0** | ZGODNY (pierwszy przebieg na gołym klonie: EXIT=1, `phpcs`/`phpstan` = `BRAK vendor/bin/…` → Obserwacja O-2) |
| 3a | `bash testy/dokumentacja-zgodnosc-sklep.sh` | `PIEC LICZB (72h/10/30d/20MB/10min), ETYKIETY I ODSYLACZE … ZGODNE Z KODEM — 0 rozjazdów` | 0 | ZGODNY |
| 3b | `bash testy/dokumentacja-zgodnosc.sh` (ChatBot) | `ZGODNE: dokumentacja i diagram nie rozjezdzaja sie z kodem` (67 tekstów SVG zmierzonych w chromium) | **0** | ZGODNY (bez chromium: EXIT=1 z mylącym komunikatem — **ROZJAZD R-2**) |
| 3c | `bash testy/dokumentacja-zgodnosc-segregator.sh` | `DOKUMENTACJA ZGODNA Z KODEM — 0 rozjazdow` | 0 | ZGODNY |
| 4a | `docker cp wtyczki/<3×>/. strona_wp_wordpress:…` + `chown` | `wp plugin list`: `chatbot-live 1.5.13 active`, `segregator-maila 1.3.6 active`, `sklep-agentow 0.9.27 active` | 0 | ZGODNY |
| 4b | `bash narzedzia-qa/proby-kontrolne-przelot.sh` | `PODSUMOWANIE: uruchomionych 33, z EXIT≠0: 6, pominiętych: 1` — zestaw DOKŁADNIE jak w odstępstwach | 1 | ZGODNY (mieści się w paśmie 5–6; szczegóły niżej) |
| 4c ⭐ | `bash testy/statystyki-proba-kontrolna.sh` | `TEST WIDZI WSZYSTKIE CZTERY PODLOZONE BLEDY` — w tym mutant 3 („wiersze bez znacznika wliczone do skutecznosci”) | **0** | **PASS** — patrz rozstrzygnięcie niżej |
| 5a | `bash testy/seo-canonical-proba-kontrolna.sh` | `BŁĘDY PRÓBY KONTROLNEJ: 0` (czysty kod przechodzi) | 0 | ZGODNY |
| 5b | `bash testy/webhook-podpis-proba-kontrolna.sh` | `BŁĘDY PRÓBY KONTROLNEJ: 0` | 0 | ZGODNY |
| 5c | `bash testy/produkt-404-proba-kontrolna.sh` | `[mutant] 404: GET=404 HEAD=200 · produkt: GET=200 HEAD=200 · naturalne404 HEAD=404` → `mutant WIDOCZNY` | 0 | ZGODNY |
| 5d | `wp eval-file /tmp/t.php` (resend/limit pobrań) | `STRAZNIK PASS · KONTROLA PASS · CRON PASS · CRON-PAD PASS · CAS-B PASS · RAZEM: WYNIK=PASS` | 0 | ZGODNY |
| 6a | `node testy/e2e/przelot2.js` (ścieżka zapasowa = hostowany Checkout) | `SESJA: cs_test_a1LzH6…` → `STATUS KONCOWY: mail_wyslany` → ekran „Gotowe! Mail z potwierdzeniem…” | 0 | ZGODNY |
| 6b | Mailpit API + `curl` linku z maila | mail „Twój zakup: ChatBot LIVE…”, zam. `#9901274`; link pobrania → `HTTP=200 SIZE=25354 TYPE=application/zip`, `Content-Disposition: attachment; filename="ChatBot-LIVE.zip"`, `Accept-Ranges: none`; sha256 pliku = `plik_sha256` z bazy | 0 | ZGODNY (co do MECHANIZMU; o zawartości pliku — Obserwacja O-4) |
| 7a | `curl` `/sklep/` i `/sklep/produkt/chatbot-live/` | po **1** `rel="canonical"`, adresy własne stron | 0 | ZGODNY |
| 7b | `curl` `/sklep/produkt/nie-ma-takiego/` | `GET=404`, `HEAD=404` (kontrola: `/sklep/produkt/segregator-maila/` → `GET=200`) | 0 | ZGODNY |
| 7c | `node testy/e2e/axe-3-podstrony.js http://localhost:8890` | `sklep/chatbot/segregator × desktop+mobile: 0 naruszeń` · `POWAŻNE (serious/critical): 0` | 0 | ZGODNY |
| 8a | `rubryka-GOTOWE.md` | `✅ 6 (#1,#3,#4,#6,#8,#11) · 🐞 5 (#2,#5,#7,#9,#10) · ⛔ 1 (#12)` — **6/5/1 potwierdzone** | — | ZGODNY (nagłówek mówi `v0.9.23` — Obserwacja O-3) |
| 8b | `AUDYT-RAPORT.md` | nagłówek `wydanie v0.9.27` ✅; `KRYTYCZNE: 0 · DUZE: 0 · kontroli FAIL: 2 (z 13) · K7: NO-GO · EXIT=1` — **zgodne z moim pomiarem** `k7-koniec.sh` | — | ZGODNY, z jedną liczbą rozbieżną (5 vs moje 6 — niżej) |
| 8c | `bash narzedzia-qa/k7-koniec.sh` | `kontroli FAIL: 2 (z 13)` — kontrole **2** i **6**, `K7: NO-GO` | 1 | ZGODNY co do LICZBY, **ROZJAZD R-1** co do POWODU kontroli 6 |
| 8d | `audyt/CO-WISI.md` | żaden wpis ZROBIONE nie przeczy mojemu pomiarowi — sprawdziłem wszystkie 7 pozycji rozliczenia 0.9.27 | — | ZGODNY |
| 8e (dodatkowo) | `bash narzedzia-qa/paczka-zgodnosc.sh sklep-agentow` | `68 plików zipa = repo co do SHA-256 · CHANGELOG [0.9.27] · readme Stable tag 0.9.27 · brak śladów wewnętrznych` | 0 | ZGODNY |

---

## 2. ROZJAZDY

### R-1. Dowód przelotu prób kontrolnych w repo pochodzi z commitu, którego NIE MA w historii wydania

Surowe wyjście — nagłówek dowodu leżącego w wydaniu (`audyt/sklep/PROBY-KONTROLNE.log:6-9`):

```
WYGENEROWANE-PRZEZ: narzedzia-qa/proby-kontrolne-przelot.sh
COMMIT: 44d32db
GALAZ: fix/dlugi-0.9.27
PODSUMOWANIE: uruchomionych 33, z EXIT≠0: 5, pominiętych: 1
```

```
$ git rev-parse --short HEAD
6a7922c
$ git cat-file -t 44d32db
fatal: Not a valid object name 44d32db
```

Surowe wyjście strażnika spójności (`bash narzedzia-qa/k7-koniec.sh`, EXIT=1):

```
  FAIL  2 kalibracja: soczewek=6, bez PASS=2, proby>2=0
  FAIL  6 próby kontrolne: przelot z commitu 44d32db, którego nie ma w historii HEAD (6a7922c)
        — dowód z innej linii (uruchom: bash narzedzia-qa/proby-kontrolne-przelot.sh)
TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: 0 · DUŻE otwarte: 0 · kontroli FAIL: 2 (z 13)
K7: NO-GO — 2 z 13 kontroli niespełnionych (8 warunków zamknięcia)
```

Liczba kontroli FAIL zgadza się z zaakceptowanym odstępstwem (2 i 6), ale **powód kontroli 6 jest inny
niż opisany w dokumentach oddania**. `rubryka-GOTOWE.md` (wiersz #12) tłumaczy jej upadek treścią:
„6 z 30 prób kontrolnych strażników EXIT≠0”. Na wydaniu kontrola 6 pada z powodu formalnego:
gałąź `fix/dlugi-0.9.27` została scalona ze zgnieceniem, więc commit `44d32db` przestał istnieć,
a `proby-kontrolne-przelot.sh` sam pisze w nagłówku (`narzedzia-qa/proby-kontrolne-przelot.sh:11-13`),
że dowód ma DATĘ WAŻNOŚCI i jest odrzucany z innego stanu kodu. To klasa, nie egzemplarz: przy przyjętym
modelu pracy (scalanie ze zgnieceniem) **żadne wydanie nigdy nie zaliczy kontroli 6**, dopóki log
powstaje na gałęzi przed scaleniem.

**Co traci człowiek:** kupujący/oceniający, który zechce zweryfikować dowód dołączony do wydania,
dostaje log o nieznanym rodowodzie — nie da się sprawdzić, na jakim kodzie powstał, a narzędzie
projektu jawnie odmawia mu zaliczenia.

Pomiar wykonany przeze mnie na TYM commicie (dowód świeży, w katalogu roboczym poza repo):
`uruchomionych 33, z EXIT≠0: 6, pominiętych: 1` — czyli o jedną więcej niż mówi log w repo
(`przejecie-proba-kontrolna.sh`, opisana wszędzie jako chwiejna).

### R-2. `testy/dokumentacja-zgodnosc.sh` melduje POMINIĘTĄ kontrolę jako „rozjazd z kodem”

Surowe wyjście na klonie bez chromium/`playwright-core`:

```
=== O. Ramki tekstow w SVG (dokladny pomiar getBBox, CZTERY krawedzie) ===
  POMINIETE  brak chromium albo playwright-core — kontrola ramek tekstow NIE zostala wykonana
             (npm install --no-save playwright-core && npx playwright install chromium;
              albo wskaz swoja przegladarke: CHATL_CHROMIUM=/sciezka/do/chrome)
…
NIEZGODNE: 1 pozycji rozjechanych z kodem
```

Miejsce: `testy/dokumentacja-zgodnosc.sh:382-385` — gałąź „POMINIETE” podbija ten sam licznik `BLEDY`,
którym raportowane są prawdziwe rozjazdy dokumentacji z kodem (`:432` drukuje „NIEZGODNE: $BLEDY pozycji
rozjechanych z kodem”). Zamknięcie bramki przy braku przyrządu jest SŁUSZNE (inaczej cichy skurcz zakresu),
kłamie tylko komunikat o przyczynie. Po doinstalowaniu przeglądarki ta sama komenda daje EXIT=0
(`ZGODNE: dokumentacja i diagram nie rozjezdzaja sie z kodem`), więc dokumentacja ChatBota z kodem się
NIE rozjeżdża — rozjazd był wyłącznie w treści komunikatu.

**Co traci człowiek:** czytający dostaje zdanie „dokumentacja rozjechała się z kodem” i rusza szukać
kłamstwa w instrukcji, podczas gdy brakuje mu tylko przeglądarki na maszynie.

---

## 2b. Potwierdzenie zaakceptowanych odstępstw — czy TE SAME, nie gorsze

**Przelot prób (krok 4b):** 6 z 33 z EXIT≠0, zestaw **dokładnie** ten z briefingu — `dowod-z-paczki` (EXIT=2),
`blad-klucza`, `ip-za-proxy`, `nonce`, `pulapka` oraz chwiejna `przejecie`. **Żadnej innej pozycji.**
Powody (uruchomione pojedynczo, surowe ogony):

```
dowod-z-paczki-proba-kontrolna.sh  EXIT=2  PROBA: nie ma paczki …/boty/paczki/chatbot-live-1.5.0.zip
blad-klucza-proba-kontrolna.sh     EXIT=1  BLAD czysty kod NIE przechodzi — straznik pada zawsze, wiec nic nie znaczy
                                           STRAZNIKI SLEPE NA 3 PODLOZONYCH BLEDOW
ip-za-proxy-proba-kontrolna.sh     EXIT=1  czysty kod przechodzi (EXIT=0) · STRAZNIK SLEPY NA 1 PODLOZONYCH BLEDOW
nonce-proba-kontrolna.sh           EXIT=1  BLAD czysty kod NIE przechodzi — straznik pada zawsze
                                           STRAZNIK SLEPY NA 6 PODLOZONYCH BLEDOW
przejecie-proba-kontrolna.sh       EXIT=1  czysty kod przechodzi (EXIT=0) · STRAZNIK SLEPY NA 2 PODLOZONYCH BLEDOW
pulapka-proba-kontrolna.sh         EXIT=1  czysty kod przechodzi (EXIT=0) · STRAZNIK SLEPY NA 2 PODLOZONYCH BLEDOW
```

To zgadza się z opisem w `rubryka-GOTOWE.md` #12 („`nonce` i `blad-klucza` padają zawsze → te dwa
zabezpieczenia chatbota są NIEPILNOWANE”). Nie jest gorzej; `dowod-z-paczki` pada dodatkowo dlatego,
że w klonie nie ma archiwalnej paczki `chatbot-live-1.5.0.zip` (artefakt świeżego klonu, nie regresja).

**⭐ `statystyki-proba-kontrolna.sh` — rozstrzygnięcie długu z `audyt/CO-WISI.md`:**

```
  OK   test PADL na: rdzenie wylaczone (porownanie calych slow)     (EXIT=1)
  OK   test PADL na: prog scalania podniesiony do 100%              (EXIT=1)
  OK   test PADL na: wiersze bez znacznika wliczone do skutecznosci (EXIT=1)   ← MUTANT 3
  OK   test PADL na: raport luk czyta odpowiedzi bota zamiast pytan (EXIT=1)
=== KONTROLA ODWROTNA: czysty kod ma przechodzic ===
  OK   czysty kod przechodzi (EXIT=0)
TEST WIDZI WSZYSTKIE CZTERY PODLOZONE BLEDY          (EXIT=0)
```

**PASS — nie znalazłem realnej wady strażnika ChatBota.** ⚠️ Uczciwe zastrzeżenie do zapisania obok
diagnozy: moje STANOWISKO było czyste (świeży klon), ale **STOS był ten sam wspólny** (`strona_wp_wordpress`),
którego dane wskazano jako kandydata na winnego. Mój PASS jest więc zgodny z hipotezą „ślepota zależna od
danych stosu” i z zapisem „CHWIEJNA” w `CO-WISI.md`, ale **nie izoluje** przyczyny — na to trzeba przebiegu
na osobnym, świeżo zaseedowanym stosie. Dług skreślam wyłącznie w części „czy to realna wada kodu strażnika”:
na tym przebiegu nie jest.

**Pozostałe:** `k7-koniec.sh` — kontrole 2 i 6 FAIL (zgodne, powód kontroli 6 → R-1).
E2E płatności OSADZONEJ — nie uruchamiałem (granica hCaptcha, patrz §3). Stos: `blog_public=0` i brak
nagłówków bezpieczeństwa w HTTP — nie ruszałem i nie zgłaszam; klucze Stripe na stosie są ustawione
(`klucz_len=107`, `webhook_len=70`), więc pełny zakup przeszedł maszynowo.

---

## 2c. Obserwacje (nie liczę ich do rozjazdów)

- **O-1.** Briefing kazał porównać sumę paczki z `gh release view v0.9.27 --json body`. W treści notatki
  wydania **sumy nie ma** (jest tylko zdanie „…SHA256SUMS.txt” o zawartości paczki zbiorczej). Porównanie
  wykonałem z `digest` zasobu Release i wyszło **identycznie**. Do decyzji koordynatorki: albo notatka wydania
  ma nieść sumy jawnie, albo instrukcja weryfikacji ma odsyłać do `--json assets`.
- **O-2.** Na gołym klonie `bramki.sh --tryb pelny` daje EXIT=1: `phpcs` i `phpstan` → `BRAK vendor/bin/…
  — czy composer install przeszedl?`, przy jednoczesnym meldunku `WYKONANE BRAMKI: 10 z 10`. Komunikat
  o przyczynie jest jasny, ale zdanie „10 z 10 wykonanych” obok dwóch bramek, które nie miały czym mierzyć,
  czyta się mylnie. Po `composer install` — 10/10 zielone.
- **O-3.** `rubryka-GOTOWE.md` ma w nagłówku `wydanie v0.9.23 (zamrożona 2026-08-30)`, a klient dostaje
  `v0.9.27`. To jest świadome (plik sam mówi: korekta tylko osobnym PR z wpisem w `DECYZJE.md`,
  a `AUDYT-RAPORT.md` pisze wprost „bez zmian przy 0.9.25”). Zapisuję, bo czytający rubrykę widzi numer
  wydania, którego nie kupił.
- **O-4.** Plik, który realnie wyszedł z bramy pobrań w moim E2E, to **25 354 B, 10 plików, daty 18–19.08**
  (`chatbot-live/…`), a nie wydany `chatbot-live 1.5.13` (922 248 B z Release). W bazie stosu:
  `opis_pelny: Demo produktu do testow K3`, `plik_rozmiar: 25354`. To **dane stosu**, nie kod — brama
  wydała dokładnie ten plik, który ma zapisany, a sha256 pobranego pliku zgadza się co do znaku
  z `plik_sha256` w bazie, więc mechanizm jest w porządku. Zapisuję jako ryzyko procesu: nic w łańcuchu
  wydania nie wiąże pliku SPRZEDAWANEGO z wersją bota, którą właśnie wydano.
- **O-5.** `przelot2.js` odnotował `BRAK pole ~postal` na formularzu Stripe (kod pocztowy nie był wymagany
  w tej sesji Checkoutu). Nie wpłynęło na wynik (EXIT=0), zapisuję jako fakt z pomiaru.

---

## 3. CZEGO NIE SPRAWDZIŁEM I DLACZEGO

- **Lighthouse / wydajność** — zakaz w briefingu (na tej maszynie mierzy jej własne obciążenie, AN-022).
  Werdykt o `perf`: **NIE SPRAWDZIŁEM**.
- **E2E płatności OSADZONEJ** (`testy/e2e/przelot-osadzony.js`) — briefing wskazuje ją jako granicę dowodu
  maszynowego (hCaptcha Stripe, kod 3). Sprawdziłem wyłącznie ścieżkę zapasową (hostowany Checkout) i ta
  przeszła do końca. Werdykt o ścieżce osadzonej: **NIE SPRAWDZIŁEM**.
- **Deinstalacja z pełnym czyszczeniem** (kryterium #9, klasa #1 ściągi) — poza listą kroków, a wykonanie
  skasowałoby dane wspólnego stosu, którego mi nie wolno rozbijać. To samo dotyczy pominiętej przez sam
  przelot próby `dowod-zachowania-segregator-proba-kontrolna.sh` („deinstaluje wtyczkę ze stosu”).
  Werdykt: **NIE SPRAWDZIŁEM** (opieram się wyłącznie na kontroli 3d `k7-koniec`, która mówi tylko tyle,
  że strażniki istnieją).
- **Sitemapa / `blog_public`** — nie przestawiałem ustawienia stosu, bo nie było takiego kroku.
  Werdykt o SEO-sitemapie: **NIE SPRAWDZIŁEM**.
- **Kalibracja audytu / bank mutantów sklepu** — leży na gałęzi `audyt/mutanty-sklep`, której klon nie ma.
  Kontrola 2 `k7-koniec` pada; przyczyny nie weryfikowałem u źródła.
- **Tryb AI botów z żywym kluczem** — brak klucza, tak samo jak przy L1-11 #10.
- **Ręczne kliknięcie strony przez człowieka** (przelot człowieka, 27 punktów) — jestem maszyną, czytałem
  tylko licznik kontroli 5 (`PASS 5 przelot człowieka: komplet (27 punktów)`).
- **Powody, dla których 5 znanych prób kontrolnych padało PRZED tym wydaniem** — porównałem zestaw nazw,
  kody wyjścia i ogony wyjścia z opisem w rubryce; nie odtwarzałem każdej podłożonej wady od zera.
- **Subagenci, katalogi koordynatorki, katalog wiedzy Anthropic** — zakaz briefingu, nie tknięte.

---

## 4. CZEGO NIE BYŁO NA MOJEJ LIŚCIE — kategorie istotne przy tym produkcie

1. **Wiązanie towaru z wydaniem.** Sklep sprzedaje pliki, a nic maszynowego nie sprawdza, czy plik wpisany
   przy produkcie to ta wersja bota, którą właśnie wydano (O-4). Klasa „sprzedaliśmy stary ZIP” nie ma
   dziś strażnika, a jest to dokładnie ten błąd, którego kupujący nie zauważy do momentu instalacji.
2. **Co widzi kupujący na PRODUKCJI, a nie na stosie testowym.** `audyt/CO-WISI.md` trzyma otwarty
   wpis 🔴 „BANER «TRYB TESTOWY» JEST ZASZYTY W KODZIE — na produkcji skłamie kupującemu”. Ten przelot
   pytał wyłącznie o zachowanie w trybie testowym.
3. **Deinstalacja i symetria zapis↔sprzątanie** (klasa #1 ściągi egzaminatora, D22: sekrety i cron mają
   ginąć ZAWSZE). Nie było kroku, a to jedyna kategoria, którą rubryka oznacza 🐞 z adnotacją
   „audytor tego nie powtórzył”.
4. **Para nonce+uprawnienia na mutacjach panelu i limity żądań** — mierzone kiedyś (L2-01, L2-08), w tym
   przelocie nietknięte; jednocześnie próba kontrolna `nonce` (ChatBot) pada „zawsze”, więc akurat w tej
   rodzinie strażnik nie pilnuje niczego.
5. **RODO w ruchu:** anonimizacja, retencja 90 dni, brak `wp_privacy_personal_data_*` (L2-18). Produkt
   trzyma adresy e-mail kupujących — kategoria warta pomiaru przy każdym wydaniu, nie tylko raz.
6. **Dostarczalność poczty na hostingu** (SMTP, SPF/DKIM). Cały dowód „mail działa” stoi na łapaczu
   w Dockerze; u klienta między `wp_mail` a skrzynką jest cała reszta świata.
7. **Nagłówki bezpieczeństwa i reguła serwera na katalog chroniony** — świadomie w procedurze (K8), ale
   nikt nie mierzy, czy właściciel je wdrożył; brama pobrań jest ostatnią linią, nie jedyną.
8. **Aktualizacja w miejscu ze starych wersji** — `dowod-aktualizacji-proba-kontrolna.sh` przeszła w moim
   przelocie (EXIT=0), ale sam przebieg aktualizacji 0.9.13 → 0.9.27 na żywej bazie z danymi nie był
   krokiem tej listy (schemat skoczył 5 → 8).
9. **Trwałość łańcucha dowodowego wydania** — R-1 pokazuje, że model „log dowodowy commitowany na gałęzi
   + scalanie ze zgnieceniem” jest wewnętrznie sprzeczny. To kategoria procesu, nie kodu, ale to ona
   decyduje, czy ktokolwiek z zewnątrz może potwierdzić Wasze pomiary.

---

K11: ROZJAZDY=2
