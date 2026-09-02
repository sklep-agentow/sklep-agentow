# WERYFIKACJA GOLDENA Z PR #258 — trzy pytania, trzy kody wyjścia

**Kto:** audytor, 2026-09-01, po sprawie strażnika `SK-L3-005`. **Gałąź:** `audyt/czesciowo-01-09`
(po scaleniu `origin/main` — `sklep-agentow` **0.9.31**).
**Przedmiot:** `testy/golden-kupujacy-dostaje-to-co-kupil-test.php` i
`testy/golden-migawka-proba-kontrolna.sh` (weszły w PR #258).
⛔ **Kodu produktu nie tknąłem.** Mutacje zakłada sama próba kontrolna i sama je cofa.

## ODPOWIEDZI W JEDNEJ TABELI

| # | pytanie | odpowiedź | kod wyjścia |
|---|---|---|---|
| 1 | Czy GOLDEN przechodzi na dzisiejszym `main`? | **TAK** | **`EXIT=0`** |
| 2 | Czy próba kontrolna naprawdę pada na kodzie sprzed naprawy — czy golden jest skalibrowany? | **TAK, na czterech warstwach naprawy z osobna** | **`EXIT=0`** |
| 3 | Czy po przebiegu stos zostaje czysty? | ⛔ **NIE** — trzy pozycje, jedna groźna | **niezgodność zmierzona, patrz §3** |

---

## 1. GOLDEN NA DZISIEJSZYM `main` — `EXIT=0`

Stos: `narzedzia-qa/stos.sh stan` → `EXIT=0` **przed** pomiarem (WP 7.1, `HTTP 200`).
Kod wtyczki wgrany z gałęzi (`Version: 0.9.31`), test uruchomiony
`docker exec -u www-data … wp eval-file /tmp/gold.php`:

```
  OK   G1 nazwa w zamowieniu = nazwa Z CHWILI ZAKUPU        (Golden PRZED golden-yiZDbzW1)
  OK   G2 cena w zamowieniu = cena Z CHWILI ZAKUPU          (14900)
  OK   G3 PLIK w zamowieniu = plik Z CHWILI ZAKUPU
  OK   G4 kwota Stripe == cena zapisana jako kupiona        (14900 vs 14900)
  OK   G5 tresc pliku pod zapisana sciezka = ta z chwili zakupu
  OK   G6 TEMAT maila niesie nazwe Z CHWILI ZAKUPU
  OK   G7 mail HTML niesie nazwe Z CHWILI ZAKUPU
  OK   G8 wersja tekstowa maila niesie nazwe Z CHWILI ZAKUPU
  OK   G9 produkt wycofany (aktywny=0) NIE blokuje domkniecia oplaconego zakupu
  OK   K5/F1/F2 sesja SPRZED migawki: zamowienie POWSTAJE, dane z tabeli produktow
WSZYSTKO OK
EXIT=0
```

⚠️ **Rzecz, którą trzeba powiedzieć wprost, bo zmienia znaczenie tego pomiaru:** stos
warsztatowy **nie chodził na `main`**. Zastałem na nim `sklep-agentow` **0.9.32** — kod
NOWSZY niż gałąź główna (czyjaś robota w toku), różniący się m.in. w `includes/zakup.php`.
Żeby odpowiedzieć na pytanie o `main`, wgrałem kod z gałęzi, a **po wszystkim przywróciłem
stan zastany** (dowód w §3). Gdybym tego nie zrobił, mierzyłbym 0.9.32 i pisał o `main`.

## 2. KALIBRACJA PRÓBY KONTROLNEJ — `EXIT=0`, cztery mutanty, cztery trafienia

`bash testy/golden-migawka-proba-kontrolna.sh`:
```
== M1: domkniecie znowu czyta TABELE PRODUKTOW (doslownie stan sprzed naprawy) ==
  OK   GOLDEN PADL na: warunek migawki zawsze falszywy (EXIT=1, asercja G1 nazwa w zamowieniu)
== M2: migawka NIE ZAPISUJE nazwy przy tworzeniu sesji ==
  OK   GOLDEN PADL na: przy INSERT sesji nazwa produktu pusta (EXIT=1, asercja G1 nazwa w zamowieniu)
== M3: migawka zapisuje BIEZACY plik zamiast kupionego ==
  OK   GOLDEN PADL na: sciezka pliku w migawce podmieniona na pusta (EXIT=1, asercja G3 PLIK w zamowieniu)
== M4: zapas dla sesji sprzed migracji usuniety ==
  OK   GOLDEN PADL na: brak odczytu z tabeli produktow dla starej sesji (EXIT=1, asercja F1 zamowienie z sesji bez migawki)
== Czysty kod ==
  OK   czysty kod EXIT=0

BLEDY PROBY KONTROLNEJ: 0
EXIT=0
```

⭐ To jest kalibracja **mocniejsza niż standardowa**, i warto to zapisać: nie pyta „czy golden
pada na kodzie sprzed naprawy" (jedna odpowiedź), tylko **rozbija naprawę na trzy warstwy plus
zapas** i sprawdza każdą osobno — bo każdą można cofnąć niezależnie przy przyszłym refaktorze.
Do tego sprawdza **właściwą asercję**, nie samo `EXIT=1`: mutant M3 musi wywalić `G3 PLIK`,
a nie cokolwiek. Sama próba pilnuje też siebie: odmawia, gdy mutacja nie weszła
(`md5sum` przed/po) albo rozwaliła składnię (`php -l`), i przywraca plik `trap`-em również
przy przerwaniu.

## 3. ⛔ STOS PO PRZEBIEGU **NIE ZOSTAJE CZYSTY** — trzy pozycje

Mierzone porównaniem stanu **przed** i **po**: kopia całej wtyczki z kontenera, suma
`md5sum` po wszystkich plikach, liczby wierszy w pięciu tabelach sklepu, zawartość `/tmp`.

| # | co zostaje | dowód | waga |
|---|---|---|---|
| **A** | ⛔ **Wtyczka na stosie zostaje PODMIENIONA na kod z repo i nikt jej nie przywraca.** `wdroz()` robi `docker cp wtyczki/sklep-agentow/.` do kontenera, a `trap` na końcu przywraca **plik w repo**, nie **stan stosu**. Ostatni krok („Czysty kod") wgrywa kod z gałęzi jeszcze raz i tak zostawia | przed: `Version: 0.9.32`, suma `a9ce0f70…`; po: `Version: 0.9.31`, suma `704b0ab7…` | **SRED** |
| **B** | Jeden wiersz w `sklepag_zdarzenia` **z każdego mutanta, który trafia w ścieżkę bez zamówienia**: `2698 → 2699` | `#3862 zam=NULL [domkniecie_brak_produktu] cs_test_goldenTL316gP4GOLDENSTARA … 19:00:18` — z mutanta **M4**. Sprzątanie goldena kasuje zdarzenia **po `zamowienie_id`**, a ten wiersz ma je `NULL` | **MALE** |
| **C** | `/tmp/gold.php` w kontenerze (kopia testu) | `ls /tmp` → `gold.php`; obok leżała już starsza kopia z 08:29 z cudzego przebiegu | **DROBNE** |

**Dlaczego `A` jest groźne, a nie kosmetyczne.** Stos warsztatowy jest **wspólny** — pracują
na nim builderzy. Uruchomienie tej próby kontrolnej z dowolnej gałęzi **po cichu podmienia im
kod pod stopami** na wersję z tej gałęzi i zostawia go tam. Jedynym widocznym śladem jest numer
wersji w nagłówku wtyczki, na który nikt nie patrzy. W moim przebiegu zeszło z `0.9.32`
(czyjaś robota w toku) na `0.9.31` — czyli **cofnięcie cudzej pracy na wspólnym stanowisku**.
Golden sam dba o swoje dane (sprzątanie w `register_shutdown_function`, kasuje produkt, sesję,
zamówienie, opinie i pliki tymczasowe), więc `B` i `C` to resztki; **`A` to inna klasa**.

⭐ **Sam przywróciłem stan zastany** — nie zostawiam stosu w cudzym stanie:
```
przywrócenie kopii z kontenera → Version: 0.9.32 · suma a9ce0f70… (identyczna z przed)
skasowany mój wiersz zdarzenia #3862 → zdarzenia: 2698 (tyle, co przed)
usunięty /tmp/gold.php · http://localhost:8890/sklep/ → HTTP=200
git status --short → pusto (plik zakup.php w repo przywrócony przez trap poprawnie)
```

**Do naprawy przez buildera (klasa, nie egzemplarz):** próba kontrolna, która wgrywa kod do
kontenera, ma **zdjąć migawkę stanu kontenera przed pierwszym wdrożeniem i przywrócić ją
w `trap`-ie**, tak samo jak dziś robi to z plikiem w repo. Dotyczy każdego skryptu z `wdroz()`,
nie tylko tego jednego.

## 4. POMIAR KOORDYNATORKI **POTWIERDZONY**: `linia-bazowa.sh` nie jest wołana z niczego

`narzedzia-qa/linia-bazowa.sh:125` faktycznie łapie goldena wzorcem `for t in testy/*.php`
(plik `testy/golden-kupujacy-dostaje-to-co-kupil-test.php` pasuje). Ale sama `linia-bazowa.sh`
**nie ma wołającego**:

```
narzedzia-qa/bramki.sh                   -> wolan z .github/.githooks/narzedzia-qa/testy: 21
narzedzia-qa/proby-kontrolne-przelot.sh  -> wolan z .github/.githooks/narzedzia-qa/testy:  7
narzedzia-qa/linia-bazowa.sh             -> wolan z .github/.githooks/narzedzia-qa/testy:  0
grep -c linia-bazowa narzedzia-qa/bramki.sh     → 0
grep -c linia-bazowa audyt/BRIEFING-NAPRAWY.md  → 0
```
⛔ **Kontrola dodatnia jest w pierwszych dwóch wierszach i jest zdana** — ten sam grep, ten sam
zestaw katalogów, znajduje 21 i 7 wołań dla skryptów wpiętych. Zero przy `linia-bazowa.sh` to
**fakt, nie „źle szukałem"**. Wszystkie pozostałe trafienia na `linia-bazowa` w repo to wpisy
w dokumentach (`audyt/LINIA-BAZOWA-TESTOW.md`, rejestry, `CO-WISI.md`), czyli opisy przebiegów
uruchamianych **ręcznie**.

**Co z tego wynika:** golden z PR #258 jest dziś **strażnikiem, który nie strzela sam**. Chodzi
wyłącznie wtedy, gdy człowiek wpisze komendę — a jego przedmiotem jest błąd, którego nie
znalazło osiem naszych pytań i który właściciel trzymał w tajemnicy. To pozycja do rejestru
i do `CO-WISI.md`. ⛔ Wpięcia **nie budowałem** — to robota buildera, i ma ona jedną pułapkę
do rozstrzygnięcia PRZED wpięciem: `linia-bazowa.sh` uruchamia **wszystkie** `testy/*.php`
na stosie, a golden do pełnego przebiegu potrzebuje kodu z gałęzi wgranego do kontenera —
czyli dokładnie tej operacji, która w §3 pozycja `A` okazała się niebezpieczna dla wspólnego
stanowiska.

## 5. CZEGO NIE ZMIERZYŁEM

- **Czy golden przechodzi na 0.9.32** (kodzie, który naprawdę leży dziś na stosie) — mierzyłem
  `main`, zgodnie z pytaniem. Skoro 0.9.32 różni się w `includes/zakup.php`, to jest osobny,
  nieodpowiedziany pomiar.
- **Zachowania przy równoległym przebiegu** — gdyby builder uruchomił cokolwiek na stosie
  w trakcie tej próby, wyniki obu byłyby nieważne. Nie mam sposobu, żeby to wykluczyć poza tym,
  że przebieg trwał krótko.
- **Pozostałych mutantów naprawy z PR #258** — próba sprawdza cztery; nie badałem, czy to
  wszystkie warstwy, które da się cofnąć osobno.
