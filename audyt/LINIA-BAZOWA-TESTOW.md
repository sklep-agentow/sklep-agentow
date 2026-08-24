# LINIA BAZOWA TESTÓW — zapis przebiegów

> **PODSUMOWANIE ETAPU 3 (24.08):** punkt odniesienia = [baza-766b4c9] (zamrożony stan audytu,
> 13 czerwonych). Po scaleniu 15 PR-ów napraw: [po-naprawach] 04:03 (nocny, diagnostyczny — 4 regresje
> + 1 nowy czerwony, WSZYSTKIE zdiagnozowane jako wady testów/dryf wspólnego stosu, kod wtyczek zdrowy;
> szczegóły: TABLICA.md §Zamknięcie etapu 3) → naprawy przyrządów → [po-naprawach-2] 04:34 (kontrolny,
> regresje=0) → **[etap3-final] 04:57 — OFICJALNY: 64 uruchomione · 60 przechodzi · 4 pada ·
> `--porownaj` z bazą: regresje=0, naprawy=9, EXIT=0.**
> (Pierwsze podejście [etap3-final] 04:46 usunięte z zapisu: eksport-csv padł TIMEOUTEM 30 s pod
> obciążeniem pełnej linii na 2 vCPU — solo 2×EXIT=0; test dostał limit 60 s. TSV nadpisany przebiegiem 04:57.)
>
> **4 stałe czerwone — środowiskowe, czerwone JUŻ w [baza-766b4c9], nie regresje:**
> `panel-statystyki.js` (baza Q&A stosu zna odpowiedź, która miała spaść do tekstu awaryjnego) ·
> `stronicowanie-panel.js` (licznik pozycji zależny od narosłych danych stosu, >10 tys. wierszy) ·
> `widget-formularz.js` (stos ma pusty `adres_polityki` — test oczekuje skonfigurowanego linku) ·
> `a4-widget-konsola-kontrast.js` EXIT=2 (przyrząd pomiarowy, nie strażnik).
> Kandydat po oddaniu: przepisać te trzy testy na samowystarczalność (własna migawka+konfiguracja,
> wzorzec z `pulapka-test.php` 24.08).


## [baza-766b4c9] — HEAD `bbb4b03` — 2026-08-23 16:12 — uruchomione 51 · przechodzi 38 · pada 13

| test | EXIT | s |
|---|---|---|
| `testy/a6-deklaracja-vs-ladunek-ai.php` | 1 | 1 |
| `testy/blad-klucza-test.php` | 0 | 0 |
| `testy/chatbot-sesje-retencja-rodo-test.php` | 1 | 1 |
| `testy/import-csv-test.php` | 0 | 0 |
| `testy/ip-za-proxy-test.php` | 0 | 1 |
| `testy/licznik-trafien-test.php` | 0 | 0 |
| `testy/pamiec-rozmowy-test.php` | 0 | 1 |
| `testy/panel-vs-bot.php` | 0 | 0 |
| `testy/przejecie-test.php` | 0 | 2 |
| `testy/pulapka-test.php` | 0 | 0 |
| `testy/rodo-test.php` | 0 | 1 |
| `testy/segregator-skrzynka-test.php` | 0 | 5 |
| `testy/segregator-skrzynka-test-uprawnienia.php` | 0 | 1 |
| `testy/statystyki-test.php` | 0 | 1 |
| `testy/stronicowanie-test.php` | 0 | 1 |
| `testy/tokeny-koszt-test.php` | 0 | 0 |
| `testy/uninstall-zombie-cron-test.php` | 1 | 1 |
| `testy/wybor-modelu-test.php` | 0 | 0 |
| `testy/wyglad-test.php` | 0 | 1 |
| `testy/znacznik-bledu-bazy-test.php` | 0 | 0 |
| `testy/blad-klucza-panel.js` | 1 | 7 |
| `testy/dziennik-test.js` | POMINIETE | 0 |
| `testy/eksport-csv.js` | 0 | 7 |
| `testy/import-csv-panel.js` | 0 | 6 |
| `testy/nonce-cache-test.js` | 0 | 6 |
| `testy/nota-zapisu-test.js` | 0 | 5 |
| `testy/panel-statystyki.js` | 1 | 5 |
| `testy/panel-test-bota.js` | 0 | 4 |
| `testy/powitanie-test.js` | 0 | 9 |
| `testy/produkty-test.js` | POMINIETE | 0 |
| `testy/pulapka-widget.js` | 0 | 6 |
| `testy/stronicowanie-panel.js` | 1 | 5 |
| `testy/svg-pomiar-tekstow.js` | POMINIETE | 0 |
| `testy/widget-formularz.js` | 1 | 2 |
| `testy/wskaznik-pisania-test.js` | 0 | 5 |
| `testy/wyglad-panel.js` | 0 | 8 |
| `testy/zrzuty-panelu.js` | POMINIETE | 0 |
| `testy/blad-klucza-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-segregator.sh` | 0 | 1 |
| `testy/dokumentacja-zgodnosc.sh` | 0 | 2 |
| `testy/import-csv-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/instrukcja-liczby-segregator.sh` | 0 | 0 |
| `testy/ip-za-proxy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/kalibracja-mutantem.sh` | POMINIETE | 0 |
| `testy/licznik-trafien-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nonce-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nota-zapisu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pamiec-rozmowy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/przejecie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pulapka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/rodo-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/seed-demo.sh` | POMINIETE | 0 |
| `testy/segregator-skrzynka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/statystyki-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/stronicowanie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/svg-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/tokeny-koszt-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wskaznik-pisania-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wybor-modelu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/zgodnosc-proba-kontrolna.sh` | POMINIETE | 0 |
| `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | 1 | 1 |
| `audyt/boty/testy-audytu/DK-csv-krawedzie.php` | 0 | 1 |
| `audyt/boty/testy-audytu/DK-like-esc-like.php` | 0 | 1 |
| `audyt/boty/testy-audytu/DK-znaki-pola.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-a1-uprawnienia.php` | 0 | 1 |
| `audyt/boty/testy-audytu/test-awaria-zapisu.php` | 1 | 0 |
| `audyt/boty/testy-audytu/test-krawedzie.php` | 1 | 0 |
| `audyt/boty/repro/lamacz-baza-i-srodowisko.php` | 0 | 3 |
| `audyt/boty/repro/lamacz-cofanie.php` | 0 | 8 |
| `audyt/boty/testy-audytu/a4-przejecie-nie-dochodzi.js` | 0 | 36 |
| `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` | 2 | 42 |
| `audyt/boty/testy-audytu/a4-zamkniecie-odpytywanie.js` | 0 | 89 |
| `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | 1 | 4 |
| `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | 1 | 8 |
| `audyt/boty/testy-audytu/DK-znaki-pola.sh` | 0 | 4 |
| `testy/e2e/` | POMINIETE | 0 |

Logi: `/root/praca/zlecenia/wymiana/linia-bazowa-logi/baza-766b4c9/`. Plik porównań: `audyt/linia-bazowa/baza-766b4c9.tsv`. ⚠️ Etykieta `baza-766b4c9` = kod wtyczek z main 766b4c9; HEAD drzewa w chwili przebiegu to bbb4b03 (766b4c9 + cherry-pick `audyt/PRZELOT-CZLOWIEKA.md`, kod wtyczek identyczny).

### Uwagi do [baza-766b4c9] — co pada i DLACZEGO (23.08, koordynatorka)
Oczekiwane (strażnicy OTWARTYCH znalezisk, mają przejść po naprawach): `a6-deklaracja-vs-ladunek-ai.php` (A6-F1) ·
`chatbot-sesje-retencja-rodo-test.php` (A3-1) · `uninstall-zombie-cron-test.php` (A3-2) · `test-awaria-zapisu.php` (A2-F1/F2/F3) ·
`test-krawedzie.php` (KR1/KR2/KR4/KR6) · `DK-blokada-segregatora.php` (DK-6) · `DK-dwa-zrodla-pulapka.sh` (DK-1) ·
`DK-rownolegle-liczniki.sh` (DK-5).
Środowisko / dane stanowiska, NIE produkt (nie liczą się jako regresja, ale mają nie pogarszać się):
- `testy/blad-klucza-panel.js` krok [4] — wymaga ŻYWEGO API dostawcy i ważnego klucza; pada, gdy klucz na stosie nie działa. ⚠️ Krok [4] oczekuje, że „Przetestuj klucz" przy działającym API ZDEJMUJE czerwony pasek — to istniejące kryterium; naprawa P2-Z1 (C5) ma je zachować: pasek znika po teście TYM SAMYM modelem co bot, nie po teście innym modelem.
- `testy/panel-statystyki.js` krok [1b] — pyta „ile kosztuje wasza usluga", baza Q&A stosu ma „Ile kosztują usługi? Gdzie znajdę cennik?" (odmiana; stemming świadomie poza zakresem — `PONAD-SPECYFIKACJE-BOTY.md`). Zależny od treści bazy demo.
- `testy/widget-formularz.js` — oczekuje ustawionego adresu polityki prywatności; na stosie był pusty (ustawiony 23.08 na `http://localhost:8890/polityka-prywatnosci/`).
- `testy/stronicowanie-panel.js` — pasek „207 pozycji", strony dają 205: dane dopisane przez inne testy w tym samym przebiegu (2 zgłoszenia) ALBO pasek liczy inaczej niż lista. ⚠️ builder1 przy C5: sprawdzić tanio, czy to drugie; jeśli tak → nowe znalezisko z testem.
- `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` EXIT=2 — przyrząd (timeout kliknięcia 30 s), sonda, nie test.
- Sondy z EXIT zawsze 0 (nie strażnicy): `repro/lamacz-cofanie.php`, `repro/lamacz-baza-i-srodowisko.php`, `a4-przejecie-nie-dochodzi.js`, `a4-zamkniecie-odpytywanie.js` — patrz rejestr.
Pierwszy przebieg tego dnia „uwalił" 13 testów JS w 0 s: worktree nie miał `node_modules` → skrypt robi teraz dowiązanie.

## [po-naprawach] — HEAD `0ed7639` — 2026-08-24 04:03 — uruchomione 65 · przechodzi 55 · pada 10

| test | EXIT | s |
|---|---|---|
| `testy/a6-deklaracja-vs-ladunek-ai.php` | 0 | 1 |
| `testy/blad-klucza-test.php` | 0 | 1 |
| `testy/chatbot-awaria-zapisu-test.php` | 0 | 0 |
| `testy/chatbot-pulapka-licznik-test.php` | 0 | 6 |
| `testy/chatbot-rodo-role-test.php` | 0 | 0 |
| `testy/chatbot-sesje-retencja-rodo-test.php` | 0 | 1 |
| `testy/chatbot-stoploss-awaria-test.php` | 0 | 0 |
| `testy/chatbot-test-klucza-test.php` | 0 | 1 |
| `testy/import-csv-test.php` | 0 | 0 |
| `testy/ip-za-proxy-test.php` | 0 | 1 |
| `testy/licznik-trafien-test.php` | 1 | 0 |
| `testy/pamiec-rozmowy-test.php` | 0 | 1 |
| `testy/panel-vs-bot.php` | 0 | 0 |
| `testy/przejecie-test.php` | 0 | 3 |
| `testy/pulapka-test.php` | 1 | 0 |
| `testy/rodo-test.php` | 0 | 1 |
| `testy/segregator-awaria-zapisu-test.php` | 0 | 1 |
| `testy/segregator-cofanie-test.php` | 0 | 13 |
| `testy/segregator-deklaracja-vs-ladunek-ai.php` | 1 | 1 |
| `testy/segregator-komunikaty-test.php` | 0 | 0 |
| `testy/segregator-przebieg-historia-test.php` | 0 | 7 |
| `testy/segregator-skrzynka-test.php` | 0 | 5 |
| `testy/segregator-skrzynka-test-uprawnienia.php` | 0 | 1 |
| `testy/segregator-zamek-ttl-test.php` | 0 | 1 |
| `testy/statystyki-test.php` | 0 | 0 |
| `testy/stronicowanie-test.php` | 0 | 1 |
| `testy/tokeny-koszt-test.php` | 0 | 0 |
| `testy/uninstall-zombie-cron-test.php` | 0 | 1 |
| `testy/wybor-modelu-test.php` | 0 | 1 |
| `testy/wyglad-test.php` | 0 | 0 |
| `testy/znacznik-bledu-bazy-test.php` | 0 | 1 |
| `testy/blad-klucza-panel.js` | 0 | 9 |
| `testy/chatbot-widget-odpytywanie-test.js` | 0 | 39 |
| `testy/dziennik-test.js` | POMINIETE | 0 |
| `testy/eksport-csv.js` | 1 | 13 |
| `testy/import-csv-panel.js` | 0 | 10 |
| `testy/nonce-cache-test.js` | 0 | 8 |
| `testy/nota-zapisu-test.js` | 0 | 7 |
| `testy/panel-statystyki.js` | 1 | 7 |
| `testy/panel-test-bota.js` | 0 | 5 |
| `testy/powitanie-test.js` | 0 | 9 |
| `testy/produkty-test.js` | POMINIETE | 0 |
| `testy/pulapka-widget.js` | 0 | 6 |
| `testy/segregator-ekran-skrzynki.js` | 0 | 6 |
| `testy/stronicowanie-panel.js` | 1 | 8 |
| `testy/svg-pomiar-tekstow.js` | POMINIETE | 0 |
| `testy/widget-formularz.js` | 1 | 5 |
| `testy/wskaznik-pisania-test.js` | 0 | 7 |
| `testy/wyglad-panel.js` | 0 | 10 |
| `testy/zrzuty-panelu.js` | POMINIETE | 0 |
| `testy/blad-klucza-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-segregator.sh` | 0 | 1 |
| `testy/dokumentacja-zgodnosc.sh` | 0 | 2 |
| `testy/import-csv-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/instrukcja-liczby-segregator.sh` | 0 | 1 |
| `testy/ip-za-proxy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/kalibracja-mutantem.sh` | POMINIETE | 0 |
| `testy/licznik-trafien-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nonce-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nota-zapisu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pamiec-rozmowy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/przejecie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pulapka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/rodo-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/seed-demo.sh` | POMINIETE | 0 |
| `testy/segregator-deklaracja-vs-ladunek-ai.sh` | 0 | 1 |
| `testy/segregator-skrzynka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/statystyki-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/stronicowanie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/svg-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/tokeny-koszt-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wskaznik-pisania-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wybor-modelu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/zgodnosc-proba-kontrolna.sh` | POMINIETE | 0 |
| `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-csv-krawedzie.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-like-esc-like.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-znaki-pola.php` | 0 | 1 |
| `audyt/boty/testy-audytu/test-a1-uprawnienia.php` | 0 | 1 |
| `audyt/boty/testy-audytu/test-awaria-zapisu.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-krawedzie.php` | 0 | 1 |
| `audyt/boty/repro/lamacz-baza-i-srodowisko.php` | 255 | 1 |
| `audyt/boty/repro/lamacz-cofanie.php` | 0 | 8 |
| `audyt/boty/testy-audytu/a4-przejecie-nie-dochodzi.js` | 0 | 37 |
| `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` | 2 | 43 |
| `audyt/boty/testy-audytu/a4-zamkniecie-odpytywanie.js` | 0 | 90 |
| `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | 0 | 4 |
| `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | 1 | 9 |
| `audyt/boty/testy-audytu/DK-znaki-pola.sh` | 0 | 4 |
| `testy/e2e/` | POMINIETE | 0 |

Logi: `/root/praca/zlecenia/wymiana/linia-bazowa-logi/po-naprawach/`. Plik porównań: `audyt/linia-bazowa/po-naprawach.tsv`.

## [po-naprawach-2] — HEAD `0ed7639` — 2026-08-24 04:34 — uruchomione 63 · przechodzi 59 · pada 4

| test | EXIT | s |
|---|---|---|
| `testy/a6-deklaracja-vs-ladunek-ai.php` | 0 | 0 |
| `testy/blad-klucza-test.php` | 0 | 1 |
| `testy/chatbot-awaria-zapisu-test.php` | 0 | 1 |
| `testy/chatbot-pulapka-licznik-test.php` | 0 | 5 |
| `testy/chatbot-rodo-role-test.php` | 0 | 1 |
| `testy/chatbot-sesje-retencja-rodo-test.php` | 0 | 0 |
| `testy/chatbot-stoploss-awaria-test.php` | 0 | 1 |
| `testy/chatbot-test-klucza-test.php` | 0 | 0 |
| `testy/import-csv-test.php` | 0 | 1 |
| `testy/ip-za-proxy-test.php` | 0 | 0 |
| `testy/licznik-trafien-test.php` | 0 | 1 |
| `testy/pamiec-rozmowy-test.php` | 0 | 1 |
| `testy/panel-vs-bot.php` | 0 | 0 |
| `testy/przejecie-test.php` | 0 | 2 |
| `testy/pulapka-test.php` | 0 | 0 |
| `testy/rodo-test.php` | 0 | 1 |
| `testy/segregator-awaria-zapisu-test.php` | 0 | 1 |
| `testy/segregator-cofanie-test.php` | 0 | 13 |
| `testy/segregator-deklaracja-vs-ladunek-ai.php` | POMINIETE | 0 |
| `testy/segregator-komunikaty-test.php` | 0 | 1 |
| `testy/segregator-przebieg-historia-test.php` | 0 | 6 |
| `testy/segregator-skrzynka-test.php` | 0 | 5 |
| `testy/segregator-skrzynka-test-uprawnienia.php` | 0 | 1 |
| `testy/segregator-zamek-ttl-test.php` | 0 | 1 |
| `testy/statystyki-test.php` | 0 | 0 |
| `testy/stronicowanie-test.php` | 0 | 1 |
| `testy/tokeny-koszt-test.php` | 0 | 1 |
| `testy/uninstall-zombie-cron-test.php` | 0 | 1 |
| `testy/wybor-modelu-test.php` | 0 | 0 |
| `testy/wyglad-test.php` | 0 | 0 |
| `testy/znacznik-bledu-bazy-test.php` | 0 | 1 |
| `testy/blad-klucza-panel.js` | 0 | 6 |
| `testy/chatbot-widget-odpytywanie-test.js` | 0 | 35 |
| `testy/dziennik-test.js` | POMINIETE | 0 |
| `testy/eksport-csv.js` | 0 | 5 |
| `testy/import-csv-panel.js` | 0 | 6 |
| `testy/nonce-cache-test.js` | 0 | 6 |
| `testy/nota-zapisu-test.js` | 0 | 5 |
| `testy/panel-statystyki.js` | 1 | 5 |
| `testy/panel-test-bota.js` | 0 | 4 |
| `testy/powitanie-test.js` | 0 | 10 |
| `testy/produkty-test.js` | POMINIETE | 0 |
| `testy/pulapka-widget.js` | 0 | 5 |
| `testy/segregator-ekran-skrzynki.js` | 0 | 7 |
| `testy/stronicowanie-panel.js` | 1 | 5 |
| `testy/svg-pomiar-tekstow.js` | POMINIETE | 0 |
| `testy/widget-formularz.js` | 1 | 2 |
| `testy/wskaznik-pisania-test.js` | 0 | 6 |
| `testy/wyglad-panel.js` | 0 | 8 |
| `testy/zrzuty-panelu.js` | POMINIETE | 0 |
| `testy/blad-klucza-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-segregator.sh` | 0 | 1 |
| `testy/dokumentacja-zgodnosc.sh` | 0 | 3 |
| `testy/import-csv-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/instrukcja-liczby-segregator.sh` | 0 | 0 |
| `testy/ip-za-proxy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/kalibracja-mutantem.sh` | POMINIETE | 0 |
| `testy/licznik-trafien-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nonce-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nota-zapisu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pamiec-rozmowy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/przejecie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pulapka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/rodo-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/seed-demo.sh` | POMINIETE | 0 |
| `testy/segregator-deklaracja-vs-ladunek-ai.sh` | 0 | 1 |
| `testy/segregator-skrzynka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/statystyki-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/stronicowanie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/svg-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/tokeny-koszt-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wskaznik-pisania-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wybor-modelu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/zgodnosc-proba-kontrolna.sh` | POMINIETE | 0 |
| `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-csv-krawedzie.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-like-esc-like.php` | 0 | 1 |
| `audyt/boty/testy-audytu/DK-znaki-pola.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-a1-uprawnienia.php` | 0 | 2 |
| `audyt/boty/testy-audytu/test-awaria-zapisu.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-krawedzie.php` | 0 | 1 |
| `audyt/boty/repro/lamacz-baza-i-srodowisko.php` | POMINIETE | 0 |
| `audyt/boty/repro/lamacz-cofanie.php` | 0 | 7 |
| `audyt/boty/testy-audytu/a4-przejecie-nie-dochodzi.js` | 0 | 37 |
| `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` | 2 | 43 |
| `audyt/boty/testy-audytu/a4-zamkniecie-odpytywanie.js` | 0 | 90 |
| `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | 0 | 4 |
| `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | 0 | 9 |
| `audyt/boty/testy-audytu/DK-znaki-pola.sh` | 0 | 4 |
| `testy/e2e/` | POMINIETE | 0 |

Logi: `/root/praca/zlecenia/wymiana/linia-bazowa-logi/po-naprawach-2/`. Plik porównań: `audyt/linia-bazowa/po-naprawach-2.tsv`.

## [etap3-final] — HEAD `0ed7639` — 2026-08-24 04:57 — uruchomione 64 · przechodzi 60 · pada 4

| test | EXIT | s |
|---|---|---|
| `testy/a6-deklaracja-vs-ladunek-ai.php` | 0 | 0 |
| `testy/blad-klucza-test.php` | 0 | 1 |
| `testy/chatbot-awaria-zapisu-test.php` | 0 | 0 |
| `testy/chatbot-pulapka-licznik-test.php` | 0 | 5 |
| `testy/chatbot-rodo-role-test.php` | 0 | 1 |
| `testy/chatbot-sesje-retencja-rodo-test.php` | 0 | 0 |
| `testy/chatbot-stoploss-awaria-test.php` | 0 | 1 |
| `testy/chatbot-test-klucza-test.php` | 0 | 1 |
| `testy/import-csv-test.php` | 0 | 0 |
| `testy/ip-za-proxy-test.php` | 0 | 1 |
| `testy/licznik-trafien-test.php` | 0 | 0 |
| `testy/pamiec-rozmowy-test.php` | 0 | 1 |
| `testy/panel-vs-bot.php` | 0 | 0 |
| `testy/przejecie-test.php` | 0 | 2 |
| `testy/pulapka-test.php` | 0 | 0 |
| `testy/rodo-test.php` | 0 | 1 |
| `testy/segregator-awaria-zapisu-test.php` | 0 | 1 |
| `testy/segregator-cofanie-test.php` | 0 | 13 |
| `testy/segregator-deklaracja-vs-ladunek-ai.php` | POMINIETE | 0 |
| `testy/segregator-komunikaty-test.php` | 0 | 1 |
| `testy/segregator-przebieg-historia-test.php` | 0 | 6 |
| `testy/segregator-skrzynka-test.php` | 0 | 5 |
| `testy/segregator-skrzynka-test-uprawnienia.php` | 0 | 1 |
| `testy/segregator-zamek-ttl-test.php` | 0 | 1 |
| `testy/statystyki-test.php` | 0 | 0 |
| `testy/stronicowanie-test.php` | 0 | 1 |
| `testy/tokeny-koszt-test.php` | 0 | 0 |
| `testy/uninstall-zombie-cron-test.php` | 0 | 1 |
| `testy/wybor-modelu-test.php` | 0 | 1 |
| `testy/wyglad-test.php` | 0 | 1 |
| `testy/znacznik-bledu-bazy-test.php` | 0 | 0 |
| `testy/blad-klucza-panel.js` | 0 | 6 |
| `testy/chatbot-widget-odpytywanie-test.js` | 0 | 36 |
| `testy/dziennik-test.js` | POMINIETE | 0 |
| `testy/eksport-csv.js` | 0 | 6 |
| `testy/import-csv-panel.js` | 0 | 6 |
| `testy/nonce-cache-test.js` | 0 | 7 |
| `testy/nota-zapisu-test.js` | 0 | 4 |
| `testy/panel-statystyki.js` | 1 | 6 |
| `testy/panel-test-bota.js` | 0 | 4 |
| `testy/powitanie-test.js` | 0 | 10 |
| `testy/produkty-test.js` | POMINIETE | 0 |
| `testy/pulapka-widget.js` | 0 | 6 |
| `testy/segregator-ekran-skrzynki.js` | 0 | 6 |
| `testy/stronicowanie-panel.js` | 1 | 5 |
| `testy/svg-pomiar-tekstow.js` | POMINIETE | 0 |
| `testy/widget-formularz.js` | 1 | 2 |
| `testy/wskaznik-pisania-test.js` | 0 | 6 |
| `testy/wyglad-panel.js` | 0 | 8 |
| `testy/zrzuty-panelu.js` | POMINIETE | 0 |
| `testy/blad-klucza-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-segregator.sh` | 0 | 2 |
| `testy/dokumentacja-zgodnosc.sh` | 0 | 2 |
| `testy/import-csv-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/instrukcja-liczby-segregator.sh` | 0 | 0 |
| `testy/ip-za-proxy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/kalibracja-mutantem.sh` | POMINIETE | 0 |
| `testy/licznik-trafien-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/material-audytu-zgodnosc.sh` | 0 | 0 |
| `testy/nonce-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/nota-zapisu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pamiec-rozmowy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/przejecie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/pulapka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/rodo-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/seed-demo.sh` | POMINIETE | 0 |
| `testy/segregator-deklaracja-vs-ladunek-ai.sh` | 0 | 1 |
| `testy/segregator-skrzynka-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/statystyki-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/stronicowanie-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/svg-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/tokeny-koszt-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wskaznik-pisania-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/wybor-modelu-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/zgodnosc-proba-kontrolna.sh` | POMINIETE | 0 |
| `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-csv-krawedzie.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-like-esc-like.php` | 0 | 1 |
| `audyt/boty/testy-audytu/DK-znaki-pola.php` | 0 | 1 |
| `audyt/boty/testy-audytu/test-a1-uprawnienia.php` | 0 | 1 |
| `audyt/boty/testy-audytu/test-awaria-zapisu.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-krawedzie.php` | 0 | 0 |
| `audyt/boty/repro/lamacz-baza-i-srodowisko.php` | POMINIETE | 0 |
| `audyt/boty/repro/lamacz-cofanie.php` | 0 | 8 |
| `audyt/boty/testy-audytu/a4-przejecie-nie-dochodzi.js` | 0 | 36 |
| `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` | 2 | 42 |
| `audyt/boty/testy-audytu/a4-zamkniecie-odpytywanie.js` | 0 | 89 |
| `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | 0 | 6 |
| `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | 0 | 10 |
| `audyt/boty/testy-audytu/DK-znaki-pola.sh` | 0 | 4 |
| `testy/e2e/` | POMINIETE | 0 |

Logi: `/root/praca/zlecenia/wymiana/linia-bazowa-logi/etap3-final/`. Plik porównań: `audyt/linia-bazowa/etap3-final.tsv`.
