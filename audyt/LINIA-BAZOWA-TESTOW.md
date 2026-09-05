# LINIA BAZOWA TESTÓW — zapis przebiegów

> **PODSUMOWANIE ETAPU 3 (24.08):** punkt odniesienia = [baza-766b4c9] (zamrożony stan audytu,
> 13 czerwonych). Po scaleniu 15 PR-ów napraw: [po-naprawach] 04:03 (nocny, diagnostyczny — 4 regresje
> + 1 nowy czerwony, WSZYSTKIE zdiagnozowane jako wady testów/dryf wspólnego stosu, kod wtyczek zdrowy;
> szczegóły: TABLICA.md — dziś: archiwum-procesu/TABLICA.md — §Zamknięcie etapu 3) → naprawy przyrządów → [po-naprawach-2] 04:34 (kontrolny,
> regresje=0) → seria przebiegów z bumpem 1.5.9/1.3.2, która wypaliła z testów przeglądarkowych DWIE
> klasy kruchości pod pełnym obciążeniem 2 vCPU (30-sekundowy default Playwrighta → 60 s we wszystkich;
> jednostrzałowe logowanie do wp-admin gubiące ciastko → wspólny `testy/lib/logowanie.js` z ponowieniem;
> po drodze DK-1 przepisany na przyrząd po C3 — wcześniej bywał zielony PRZYPADKIEM przy wyczerpanym
> kuble) → **[etap3-final] OFICJALNY: 64 uruchomione · 61 przechodzi · 3 pada ·
> `--porownaj` z bazą: regresje=0, naprawy=10, EXIT=0.**
>
> **3 stałe czerwone — środowiskowe, czerwone JUŻ w [baza-766b4c9], nie regresje:**
> `panel-statystyki.js` (baza Q&A stosu zna odpowiedź, która miała spaść do tekstu awaryjnego) ·
> `stronicowanie-panel.js` (licznik pozycji zależny od narosłych danych stosu, >10 tys. wierszy) ·
> `a4-widget-konsola-kontrast.js` EXIT=2 (przyrząd pomiarowy, nie strażnik).
> `widget-formularz.js` zszedł z tej listy 24.08 (własna migawka `adres_polityki` — uwaga 7 recenzji #168).
> Kandydat po oddaniu: samowystarczalność dwóch pozostałych (wzorzec migawki z `pulapka-test.php`).
>
> **Adnotacja do [etap3-final] (uwaga 12 recenzji #168):** TSV pochodzi z drzewa `14f2cff`; późniejsze
> commity poprawek recenzyjnych ruszyły DWA mierzone przez linię testy — `testy/nota-zapisu-test.js`
> i `testy/widget-formularz.js` (utwardzenie migawki/restore, zero zmian w asercjach produktu) —
> oba po zmianach uruchomione SOLO na stosie: **EXIT=0 i EXIT=0**. Kod wtyczek między `14f2cff`
> a HEAD zmienił wyłącznie pozycję docblocka (`instalator.php`) i tekst jednej linii SVG.
> **Jawna zaległość (uwaga 13):** diagram Segregatora NIE MA strażnika geometrii (sekcja O bramki
> dokumentacyjnej mierzy tylko diagram ChatBota); pomiar `svg-pomiar-tekstow.js` dla Segregatora
> wykonany ręcznie 24.08: „WSZYSTKIE TEKSTY SIE MIESZCZA". Kandydat po oddaniu: sekcja O także dla Segregatora.




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

## [etap3-final] — HEAD `1c9a272` — 2026-08-24 06:13 — uruchomione 64 · przechodzi 61 · pada 3

| test | EXIT | s |
|---|---|---|
| `testy/a6-deklaracja-vs-ladunek-ai.php` | 0 | 1 |
| `testy/blad-klucza-test.php` | 0 | 0 |
| `testy/chatbot-awaria-zapisu-test.php` | 0 | 1 |
| `testy/chatbot-pulapka-licznik-test.php` | 0 | 5 |
| `testy/chatbot-rodo-role-test.php` | 0 | 0 |
| `testy/chatbot-sesje-retencja-rodo-test.php` | 0 | 1 |
| `testy/chatbot-stoploss-awaria-test.php` | 0 | 0 |
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
| `testy/statystyki-test.php` | 0 | 1 |
| `testy/stronicowanie-test.php` | 0 | 0 |
| `testy/tokeny-koszt-test.php` | 0 | 1 |
| `testy/uninstall-zombie-cron-test.php` | 0 | 0 |
| `testy/wybor-modelu-test.php` | 0 | 1 |
| `testy/wyglad-test.php` | 0 | 0 |
| `testy/znacznik-bledu-bazy-test.php` | 0 | 1 |
| `testy/blad-klucza-panel.js` | 0 | 7 |
| `testy/chatbot-widget-odpytywanie-test.js` | 0 | 35 |
| `testy/dziennik-test.js` | POMINIETE | 0 |
| `testy/eksport-csv.js` | 0 | 5 |
| `testy/import-csv-panel.js` | 0 | 6 |
| `testy/nonce-cache-test.js` | 0 | 6 |
| `testy/nota-zapisu-test.js` | 0 | 5 |
| `testy/panel-statystyki.js` | 1 | 6 |
| `testy/panel-test-bota.js` | 0 | 4 |
| `testy/powitanie-test.js` | 0 | 10 |
| `testy/produkty-test.js` | POMINIETE | 0 |
| `testy/pulapka-widget.js` | 0 | 6 |
| `testy/segregator-ekran-skrzynki.js` | 0 | 6 |
| `testy/stronicowanie-panel.js` | 1 | 5 |
| `testy/svg-pomiar-tekstow.js` | POMINIETE | 0 |
| `testy/widget-formularz.js` | 0 | 4 |
| `testy/wskaznik-pisania-test.js` | 0 | 5 |
| `testy/wyglad-panel.js` | 0 | 9 |
| `testy/zrzuty-panelu.js` | POMINIETE | 0 |
| `testy/blad-klucza-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-segregator.sh` | 0 | 1 |
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
| `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` | 2 | 73 |
| `audyt/boty/testy-audytu/a4-zamkniecie-odpytywanie.js` | 0 | 89 |
| `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | 0 | 5 |
| `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | 0 | 10 |
| `audyt/boty/testy-audytu/DK-znaki-pola.sh` | 0 | 4 |
| `testy/e2e/` | POMINIETE | 0 |

Logi: `/root/praca/zlecenia/wymiana/linia-bazowa-logi/etap3-final/`. Plik porównań: `audyt/linia-bazowa/etap3-final.tsv`.

## [po-198] — HEAD `581e869` — 2026-08-27 07:14 — uruchomione 80 · przechodzi 71 · pada 9

| test | EXIT | s |
|---|---|---|
| `testy/a6-deklaracja-vs-ladunek-ai.php` | 0 | 1 |
| `testy/blad-klucza-test.php` | 0 | 0 |
| `testy/chatbot-awaria-zapisu-test.php` | 0 | 1 |
| `testy/chatbot-pulapka-licznik-test.php` | 0 | 5 |
| `testy/chatbot-rodo-role-test.php` | 0 | 1 |
| `testy/chatbot-sesje-retencja-rodo-test.php` | 0 | 0 |
| `testy/chatbot-stoploss-awaria-test.php` | 0 | 1 |
| `testy/chatbot-test-klucza-test.php` | 0 | 0 |
| `testy/domkniecie-nie-gubi-zakupu-test.php` | 0 | 1 |
| `testy/import-csv-test.php` | 0 | 1 |
| `testy/ip-za-proxy-test.php` | 0 | 0 |
| `testy/licznik-trafien-test.php` | 0 | 1 |
| `testy/limit-uploadu-realny-test.php` | 0 | 0 |
| `testy/mail-po-zakupie-test.php` | 0 | 1 |
| `testy/opinie-mechanizm-test.php` | 1 | 0 |
| `testy/opinie-render-test.php` | 0 | 1 |
| `testy/pamiec-rozmowy-test.php` | 0 | 1 |
| `testy/panel-vs-bot.php` | 0 | 0 |
| `testy/przejecie-test.php` | 0 | 1 |
| `testy/pulapka-test.php` | 0 | 1 |
| `testy/reconciliation-wysyla-mimo-awarii-stripe-test.php` | 0 | 0 |
| `testy/resend-nie-kasuje-znacznika-zwrotu-test.php` | 0 | 1 |
| `testy/resend-nie-odbiera-linku-test.php` | 0 | 0 |
| `testy/rodo-test.php` | 0 | 1 |
| `testy/segregator-awaria-zapisu-test.php` | 0 | 1 |
| `testy/segregator-cofanie-test.php` | 0 | 14 |
| `testy/segregator-deklaracja-vs-ladunek-ai.php` | POMINIETE | 0 |
| `testy/segregator-komunikaty-test.php` | 0 | 0 |
| `testy/segregator-przebieg-historia-test.php` | 0 | 7 |
| `testy/segregator-skrzynka-test.php` | 0 | 5 |
| `testy/segregator-skrzynka-test-uprawnienia.php` | 0 | 1 |
| `testy/segregator-zamek-ttl-test.php` | 0 | 0 |
| `testy/statystyki-test.php` | 0 | 0 |
| `testy/stronicowanie-test.php` | 0 | 1 |
| `testy/tokeny-koszt-test.php` | 0 | 1 |
| `testy/uninstall-zombie-cron-test.php` | 0 | 1 |
| `testy/wybor-modelu-test.php` | 0 | 0 |
| `testy/wyglad-test.php` | 0 | 1 |
| `testy/znacznik-bledu-bazy-test.php` | 0 | 0 |
| `testy/zwrot-obcy-nie-pyta-stripe-dwa-razy-test.php` | 0 | 1 |
| `testy/zwrot-schodzi-z-przychodu-test.php` | 0 | 0 |
| `testy/blad-klucza-panel.js` | 0 | 9 |
| `testy/chatbot-widget-odpytywanie-test.js` | 0 | 36 |
| `testy/dziennik-test.js` | POMINIETE | 0 |
| `testy/eksport-csv.js` | 0 | 4 |
| `testy/import-csv-panel.js` | 0 | 7 |
| `testy/nonce-cache-test.js` | 0 | 6 |
| `testy/nota-zapisu-test.js` | 0 | 5 |
| `testy/panel-statystyki.js` | 1 | 5 |
| `testy/panel-test-bota.js` | 1 | 4 |
| `testy/powitanie-test.js` | 0 | 10 |
| `testy/produkty-test.js` | POMINIETE | 0 |
| `testy/pulapka-widget.js` | 0 | 6 |
| `testy/segregator-ekran-skrzynki.js` | 0 | 7 |
| `testy/stronicowanie-panel.js` | 1 | 4 |
| `testy/svg-pomiar-tekstow.js` | POMINIETE | 0 |
| `testy/widget-formularz.js` | 0 | 4 |
| `testy/wskaznik-pisania-test.js` | 0 | 5 |
| `testy/wyglad-panel.js` | 0 | 9 |
| `testy/zrzuty-instrukcje.js` | 1 | 2 |
| `testy/zrzuty-panelu.js` | POMINIETE | 0 |
| `testy/blad-klucza-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-segregator.sh` | 0 | 1 |
| `testy/dokumentacja-zgodnosc.sh` | 0 | 2 |
| `testy/dokumentacja-zgodnosc-sklep-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/dokumentacja-zgodnosc-sklep.sh` | 0 | 1 |
| `testy/import-csv-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/instrukcja-liczby-segregator.sh` | 0 | 0 |
| `testy/ip-za-proxy-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/kalibracja-mutantem.sh` | POMINIETE | 0 |
| `testy/katalog-chroniony-samonaprawa-test.sh` | 1 | 0 |
| `testy/komentarz-permalinki-zgodny-z-pomiarem-test.sh` | 0 | 0 |
| `testy/kup-wygasly-token-slad-test.sh` | 1 | 0 |
| `testy/licznik-trafien-proba-kontrolna.sh` | POMINIETE | 0 |
| `testy/limit-prob-zakupu-komunikat-test.sh` | 2 | 0 |
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
| `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | 0 | 1 |
| `audyt/boty/testy-audytu/DK-csv-krawedzie.php` | 0 | 0 |
| `audyt/boty/testy-audytu/DK-like-esc-like.php` | 0 | 1 |
| `audyt/boty/testy-audytu/DK-znaki-pola.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-a1-uprawnienia.php` | 0 | 1 |
| `audyt/boty/testy-audytu/test-awaria-zapisu.php` | 0 | 0 |
| `audyt/boty/testy-audytu/test-krawedzie.php` | 0 | 1 |
| `audyt/boty/repro/lamacz-baza-i-srodowisko.php` | POMINIETE | 0 |
| `audyt/boty/repro/lamacz-cofanie.php` | 0 | 7 |
| `audyt/boty/testy-audytu/a4-przejecie-nie-dochodzi.js` | 0 | 37 |
| `audyt/boty/testy-audytu/a4-widget-konsola-kontrast.js` | 2 | 72 |
| `audyt/boty/testy-audytu/a4-zamkniecie-odpytywanie.js` | 0 | 89 |
| `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | 0 | 6 |
| `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | 0 | 9 |
| `audyt/boty/testy-audytu/DK-znaki-pola.sh` | 0 | 4 |
| `testy/e2e/` | POMINIETE | 0 |

Logi: `/root/praca/zlecenia/wymiana/linia-bazowa-logi/po-198/`. Plik porównań: `audyt/linia-bazowa/po-198.tsv`.

## Warunki wstępne stanowiska — zmierzone 5.09 (I-25, I-26, I-27)

Nie testy produktu, tylko stan STANOWISKA, bez którego część pomiarów jest niemożliwa.
Opis i próby kontrolne: `narzedzia-qa/stanowisko/README.md`.

| co | przed (1–5.09) | po naprawie (5.09) | komenda |
|---|---|---|---|
| WP-Cron: zadania zaległe | 8 z 15 (najstarsze `sklepag_dociagnij_zalegle` z 4.09 22:19 GMT); pętla zwrotna `wp-cron.php` = kod 000, curl exit 7 | 0 zaległych; `sklepag_dociagnij_zalegle` przeplanowane w przyszłość | `docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html cron event list --fields=hook,next_run_relative --format=csv \| grep -c ',now$'` |
| limity uploadu | `upload_max_filesize=2M`, `post_max_size=8M` (paczka klienta 0.9.40 waży 4 859 567 B — `ls -l boty/paczki/sklep-agentow-0.9.40.zip`; wcześniejszy zapis 9 098 363 B nie miał pokrycia, #289 W6) | `64M` / `64M` | `docker exec strona_wp_wordpress php -i \| grep -E '^upload_max_filesize\|^post_max_size'` |
| baza łapacza maili | `/tmp/mailpit-*.db` w kontenerze, bez wolumenu (restart kasuje) | `/data/mailpit.db` na wolumenie `mailpit_data`; 2 wiadomości przetrwały `docker restart` | `curl -s http://127.0.0.1:8025/api/v1/info` |
| wp-cli w kontenerze | wgrany ręcznie w warstwę kontenera (ginie przy odtworzeniu) | montowany z hosta `./wp/bin/wp` | `docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option get siteurl` |

### Droga instalacyjna z instrukcji klienta — NADAL NIEZMIERZONA (stan 5.09)

Zdjęta została BARIERA (limity), sam przelot 7 kroków `dokumentacja/INSTRUKCJA-INSTALACJI.md`
NIE został wykonany. Świadomie: Krok 1 wgrywa paczkę przez panel, co NADPISAŁOBY wtyczkę
`sklep-agentow` na WSPÓLNYM stanowisku, na którym w tym czasie mierzą inni builderzy
(i podmieniłoby wersję pod ich pomiarami). Do przejścia na stosie próbnym
albo na wspólnym po umówieniu okna. ⛔ `narzedzia-qa/dowod-z-paczki.sh` tego NIE zmierzy: instaluje przez
`docker cp` + `wp plugin install`, czyli omija formularz „Wyślij na serwer” i limity uploadu, o które chodzi
w `I-26` (#289 W4). Krok 1 musi przejść PRZEZ PANEL (przeglądarka, `probny_wp`).
