# 🎯 TABLICA — kto co bierze, co czeka, co blokuje

> Jedno miejsce zamiast dopytywania. **Aktualizuje koordynatorka po każdej zmianie stanu.**
> Stan na: **24.08 — ✅ ETAP 3 (NAPRAWY) ZAMKNIĘTY.** 15 PR-ów scalonych (K0 #153, K0b #154,
> C1–C6: #155 #158 #162 #164 #166 #167, S1–S5: #156 #159 #161 #163 #165, recenzent #160, stos #157).
> Rejestr: **0 OTWARTYCH, dług strażników 0** — wszystkie 24 znaleziska ZAMKNIĘTE po pomiarze.
> Linia bazowa po naprawach: **regresje=0** (`audyt/LINIA-BAZOWA-TESTOW.md` [etap3-final]).
> W toku: bump ChatBot **1.5.9** / Segregator **1.3.2** + paczki z dowodami (ten PR). Potem: sklep (lista właściciela).

## 👥 KTO CO BIERZE TERAZ (etap 3 — naprawy po audycie)

| okno tmux | rola | teren | drzewo | paczki (kolejność) | stan |
|---|---|---|---|---|---|
| `builder1` | naprawy ChatBota | `wtyczki/chatbot-live/` + `testy/chatbot-*` | — | C1 → C2 → C3 → C4 → C5 → C6 | ✅ ZAKOŃCZONE, wszystkie scalone |
| `builder2` | naprawy Segregatora | `wtyczki/segregator-maila/` + `testy/segregator-*` | — | S1 → S2 → S3 → S4 → S5 | ✅ ZAKOŃCZONE, wszystkie scalone |
| koordynatorka | domknięcie etapu: diagnoza linii bazowej, rejestr → ZAMKNIĘTE, bump + paczki + dowody | reszta | worktree `koord/etap3-final` | finał | 🔄 w tym PR |

⛔ **Pliki wspólne edytuje TYLKO koordynatorka:** `audyt/REJESTR-ZNALEZISK.md`, `TABLICA.md`,
`boty/PONAD-SPECYFIKACJE-BOTY.md`, `boty/buduj-paczki.sh`, `boty/paczki/`, `.github/`, `narzedzia-qa/bramki.sh`.
Builder pisze proponowaną zmianę w opisie PR. Nikt nie scala własnego PR-a.
⛔ **Jeden builder na wtyczkę** (kod idzie na stos przez `docker cp`, bind-mountu nie ma). Testy przeglądarkowe/HTTP
pod `flock /root/praca/zlecenia/wymiana/stos.lock`. Max **3 sesje Claude naraz** (RAM 3,8 GB, próg `available` ≥ 1 GB).

### Stan rejestru na start etapu (23.08, po pomiarze)
**23 OTWARTE · 1 W NAPRAWIE (LS-F2) · 0 ZAMKNIĘTE naprawdę** — A2-F1/F2/F3 wróciły na OTWARTE po uruchomieniu
`test-awaria-zapisu.php` na `main` (PADA 3/6, EXIT=1; #136 naprawił sąsiedni problem). Dług strażników 11 (cztery wpisy z rejestru okazały się sondami bez exit 1).
Linia bazowa testów PRZED: `audyt/LINIA-BAZOWA-TESTOW.md` [baza-766b4c9].

## 🔴 POZYCJA NUMER JEDEN — CI NIE URUCHAMIA ŻADNEGO TESTU

Zmierzone 21.08 19:04, z próbą kontrolną:
- `.github/workflows/quality.yml` woła **wyłącznie** `narzedzia-qa/bramki.sh`;
- `bramki.sh` ma 7 bramek (actionlint, manifest, ślady, php-lint, phpcs, phpstan, gitleaks)
  i **żadna nie dotyka katalogu `testy/`**;
- w `testy/` leży **57 plików**. Automatycznie uruchamianych: **zero**.
- Recenzent ma je na liście dozwolonych komend, ale to model, który *może* je odpalić —
  nie bramka, która *musi*.

**Dowód:** PR #135 wniósł **dwa celowo padające testy** (`chatbot-sesje-retencja-rodo-test.php`,
`uninstall-zombie-cron-test.php`, oba EXIT=1, dokumentują realne znaleziska) — a CI dało
zielono i PR został scalony. Te testy leżą teraz na `main` i nic o tym nie mówi.

⛔ To znaczy, że nasza zasada „każdy błąd dostaje strażnika z exit 1 wpięty do CI"
**nie jest spełniona ani razu**. Wszystko jest doradcze.

**Kształt naprawy** (do zrobienia, nie zrobione):
### ✅ ZROBIONE 21.08 19:12 — ósma bramka `znaleziska`

`narzedzia-qa/bramka-znaleziska.sh` + `audyt/REJESTR-ZNALEZISK.md`, wpięte do `bramki.sh`
(próg pełny 7 → **8**, tekstowy 4 → **5**). Bramka sprawdza **księgowość znalezisk**:

- liczba OTWARTYCH wpisana wprost w rejestrze musi zgadzać się z liczbą wierszy —
  dopisanie znaleziska **zatrzymuje bramkę**, dopóki ktoś świadomie nie podniesie liczby;
- każdy test wskazany w rejestrze **musi istnieć** (martwy odsyłacz udaje, że strażnik stoi);
- każdy `audyt/boty/RAPORT-*.md` musi być objęty rejestrem — nowy raport nie wniesie
  znalezisk poza księgowość, czyli dokładnie to, co się nam stało;
- status musi być ze znanego zbioru (OTWARTE / W NAPRAWIE / ZAMKNIĘTE);
- bramka **liczy własne kontrole** i pada, gdy wzorzec tabeli przestał pasować — inaczej
  cicho nie wystartowałaby i świeciła zielono (nasza własna blizna).

**Kalibracja zdana: 4 podstawki na 4 złapane** (rozjazd liczby w nagłówku, test wskazany
na nieistniejący plik, raport spoza rejestru, wymyślony status), kontrola odwrotna na czystym
rejestrze EXIT=0. Bramka nie jest napisem.

W rejestrze siedzi **9 znalezisk**: F1/F2/F3 (w naprawie przez #136), A3-1, A3-2, KR1, KR2,
KR4, KR6 (otwarte).

### ✅ ZAMKNIĘCIE ETAPU 3 (24.08) — diagnoza pięciu czerwonych linii bazowej
Nocna linia [po-naprawach] pokazała 4 regresje + 1 nowy czerwony strażnik. Diagnoza (systematycznie,
z dowodami): **kod wtyczek zdrowy — wszystkie pięć to wady testów albo dryf wspólnego stosu**:
`pulapka-test` (stos miał `powiadomienia=0` po cudzym zapisie formularza → test dostał własną migawkę
ustawień i jawnie włącza powiadomienia) · `licznik-trafien-test` (import konfiguracji zostawił stos
bez reguł, a seed CELOWO nie odtwarza reguł przy istniejących kategoriach → test dosiewa demo sam) ·
`eksport-csv` (duble wiersza testowego po przerwanym cudzym przebiegu → sprzątanie pętlą) ·
`DK-rownolegle-liczniki` (mierzył licznik w opcjach USUNIĘTY przez C3 → przyrząd zaktualizowany na
tabelę zdarzeń: 10/10 co do progu, kubeł 20/20 atomowo) · `segregator-deklaracja-vs-ladunek-ai.php`
(to silnik wrappera `.sh`, nie duplikat — linia bazowa pomija go jawnie) · zamrożona sonda
`lamacz-baza-i-srodowisko.php` wyłączona z linii (woła kształt kodu sprzed S1; dowód w git).
Dodatkowo: A6-F2 domknięte (materiał audytowy poprawiony + strażnik `testy/material-audytu-zgodnosc.sh`),
dług `klucz_hash=''` w testach spłacony (`wp_hash`), `test-klucza`/`pulapka`/`przejęcie` — zielone.
Pozostałe czerwone linii bazowej (3×env: `panel-statystyki`, `stronicowanie-panel`, `widget-formularz` —
dane/konfiguracja stosu, czerwone już w [baza-766b4c9]; `a4-widget-konsola-kontrast` EXIT=2 = przyrząd) —
udokumentowane w `audyt/LINIA-BAZOWA-TESTOW.md`, kandydaci do samowystarczalności testów po oddaniu.

### ⛔ CZEGO ŚWIADOMIE NIE ZROBIŁAM — czeka na decyzję właściciela

1. **Wpis do `.github/manifest-bramek.txt`.** Manifest ma własną zasadę, zapisaną w nim
   samym: *„każda zmiana = osobny PR, który właściciel czyta w całości; agent NIE dotyka tego
   pliku w PR-ach z kodem"*. Dopóki bramki tam nie ma, działa i zatrzymuje pull requesty,
   ale **nie jest minimum** — da się ją usunąć bez alarmu. Ta sama droga, którą przeszła
   bramka `slady`: najpierw działała, potem wpis uczynił ją nieusuwalną.
2. **Uruchamiania testów przez CI.** Wszystkie 23 skrypty i 18 testów PHP wymagają żywego
   stosu Dockera — psują kod w kontenerze i sprawdzają, czy strażnik pada. Równoległe
   przeloty CI deptałyby sobie po stosie. Wykonanie zostaje przy człowieku i recenzencie.

## 🔧 PULL REQUESTY (etap 3 — wszystkie scalone)

| paczka | PR | paczka | PR |
|---|---|---|---|
| K0 / K0b | #153 / #154 | S1 | #156 |
| C1 | #155 | S2 | #159 |
| C2 | #158 | S3 | #161 |
| C3 | #162 | S4 | #163 |
| C4 | #164 | S5 | #165 |
| C5 | #166 | recenzent-Opus | #160 |
| C6 | #167 | maskowanie sekretów stosu | #157 |

⛔ Kolejka CI jest szeregowa — jeden własny wykonawca, jedno zadanie naraz.

## 🔬 AUDYT — 7 soczewek

| soczewka | pytanie | stan |
|---|---|---|
| wszystkie 7 (A1–A6 + Łamacz) | — | ✅ **AUDYT DOMKNIĘTY 23.08**: MAPA 36/36 celów, 23 czysto, 13 znalezisk (#152) — a 24.08 wszystkie znaleziska ZAMKNIĘTE naprawami |

Mapa polowania: `audyt/boty/material/06-mapa-polowania.md` — **36 celów**, odhacza koordynatorka.

## 🐞 ZNALEZISKA CZEKAJĄCE NA DECYZJĘ (stan 24.08)

1. **L8 — czy AI wchodzi na skrzynkę Segregatora** — JEDYNA otwarta decyzja, należy do właściciela.
   Fakt (S5 #165): dziś na przebiegu skrzynki pracują SAME REGUŁY, deklaracja i panel mówią to wprost.
   **Rekomendacja: zostaje napis „same reguły"** (zero danych osób trzecich do AI, zero ryzyka RODO).
2. **Zasięg paska błędu bazy** (Segregator globalnie, ChatBot na swoich stronach) — nie weszło do
   paczek C1–C6/S1–S5; zaległość niżej priorytetu, ślad w #136. Reszta dawnej listy: pkt 3 (sesje) →
   C6 #167 · pkt 4 (zombie cron) → C6 #167 · pkt 5 (zamek po zabitym procesie) → S1 #156 ·
   pkt 6 (`DISABLE_WP_CRON`) → świadomie nie (T-2: ostrzeżenie ma Segregator; rejestr, sekcja
   „świadomie NIE naprawiane").

## 📦 PO AUDYCIE — kolejność domknięcia
1. ✅ Rozstrzygnięte: każde znalezisko ZAMKNIĘTE ze strażnikiem (rejestr: OTWARTE=0, dług=0).
2. 🔄 **Jedno zbiorcze podbicie: ChatBot 1.5.9, Segregator 1.3.2** + przebudowa obu paczek
   + `paczka-zgodnosc.sh` EXIT=0 + dowody NA PACZKACH — w tym PR, jedną operacją.
3. Sklep — właściciel ma własną listę poprawek (`wymiana/SKLEP-LISTA-PO-BOTACH.md`), wracamy po botach.
4. Dokumentacja projektu botów: przegląd istniejących instrukcji/diagramów/architektury przeciw
   kodowi po naprawach, poprawki tam, gdzie naprawy unieważniły treść.
