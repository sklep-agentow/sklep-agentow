# 📋 PLAN AUDYTU + RE-AUDYTU BOTÓW (ChatBot LIVE + Segregator maila)

**Data: 2026-08-20, noc.** Przepis: `warsztat/dzial-audytu/PRZEPIS-AUDYTU-A-DO-Z.md` (v1.0-RC).
Poligon: uczymy się audytu od A do Z + robimy prawdziwą robotę (boty idą na egzamin).

## 📏 SKALA — zmierzona, nie zgadnięta
- ChatBot LIVE: **1643 linie** (PHP + JS + CSS) · Segregator maila: **1626 linii**
- Razem: **3269 linii ≈ 127 703 znaki ≈ ~35 tys. tokenów**
- **Wniosek:** cały kod OBU botów + paczka materiału mieści się w JEDNYM oknie 200k
  z zapasem. Podział na agentów wynika z METODY (różne pytania = różne znaleziska),
  nie z ograniczeń kontekstu. Każdy audytor dostaje CAŁY kod obu botów.

## 🧩 KRYTYK + WERYFIKATOR — jak to składamy (⚠️ interpretacja, nie cytat kolegi)

Kolega: „każdy agent musi mieć krytyka + weryfikatora" ORAZ „dwa audyty na tej samej wersji kodu,
zbieżne wyniki → naprawy". **Te dwie zasady składają się w jedno:**

| rola | co robi | na czym pracuje |
|---|---|---|
| **AUDYTOR** (runda A) | szuka błędów swoim pytaniem | kod + materiał |
| **KRYTYK** (runda B) | ten sam moduł, INNY agent: (a) ocenia robotę audytora — czy jego „sprawdziłem, jest OK" to prawda, (b) szuka NOWYCH rzeczy | kod + materiał + znaleziska A |
| **WERYFIKATOR** | bierze KAŻDE znalezisko i próbuje je odtworzyć — potwierdza albo obala DOWODEM WYKONANIA | tylko znaleziska + żywy stack |

⛔ Weryfikator jest **spoza audytu** — nie zna roboczego kontekstu audytora (inaczej potwierdza cudzą
pracę zamiast sprawdzać). Materiał dla niego WYCZYSZCZONY z werdyktów rundy A.

**Zbieżność A i B = wynik wiarygodny** (zasada kolegi). Rozjazd = audytor-loteria → trzeci przebieg.
Wyniki zbieramy **UNIĄ, nie głosowaniem** (pomiar: przecięcie wycięło 4 z 6 najcenniejszych klas).

## 👥 SKŁAD — 6 audytorów + łamacz (dobór POD PROJEKT)

Dwie osie w każdym: **CO pyta × CZYM patrzy** (⛔ czytanie kodu nie znajdzie tego, co wymaga wykonania).

| # | Audytor | PYTANIE (Z1/Z3 — o awarię, nie o sukces) | CZYM patrzy |
|---|---|---|---|
| **A1** | SECURITY | Czy da się wejść bez uprawnień/nonce? Co robi wstrzyknięcie polecenia w trybie AI? Czy klucz API wycieka (logi, odpowiedzi, baza)? Czy limit mierzy to, co robi szkodę? | **wykonanie**: curl, przeglądarka, żądania równoległe |
| **A2** | AWARIA | Czy kod **ZMIERZYŁ stan świata PO operacji**, czy tylko wywołał funkcję? Co, gdy zapis padnie w połowie? Co, gdy API nie odpowie? Czy funkcja bez zwracanego wyniku udaje sukces? | czytanie + **wymuszone awarie** |
| **A3** | DANE / CO ZOSTAJE | Co przeżywa deinstalację (snapshot przed/po, nie lista „co kasujemy")? Kto jest właścicielem każdej opcji/tabeli/crona? Czy retencja naprawdę kasuje? Ile jest źródeł prawdy dla tej samej listy? | **SQL na bazie** + wp-cli |
| **A4** | FRONT / UX | Czy da się dokończyć każdą ścieżkę klikając? Co widzi gość, gdy sieć padnie? Czy da się zamknąć to, co się otworzyło? Dostępność, konsola. | **klikanie przeglądarką** |
| **A5** | KRAWĘDZIE | Puste, maksimum, jeden-ponad-limit, najdłuższe, dziwne znaki, podwójne kliknięcie, dwa żądania naraz. | **wykonanie** |
| **A6** | DOK vs KOD | Każda liczba i obietnica z instrukcji botów i stron produktów — pokrycie w kodzie. **Komendy z instrukcji WPISANE w terminal**, nie przeczytane. | porównanie + **wykonanie dosłowne** |
| **Ł** | ŁAMACZ | Łamie założenia: „co jeśli baza nie odpowie", „co jeśli klucz jest zły", „co jeśli ktoś wyśle 500 żądań", „co jeśli WP jest w innej wersji". | **wykonanie** |

⚠️ ARCHITEKT: przy 3269 liniach osobny agent = przerost. Jego pytania (granice modułów, przepływ
danych, zależności) wchodzą do A3 i do krytyków.

## 🔄 PRZEBIEG — 8 kroków (z przepisu)

| krok | co | agenci | czas |
|---|---|---|---|
| **0** | ZABEZPIECZENIE: gałąź, zrzut 10 tabel, kopie paczek 1.2.1, **odcisk wartości** (co boty zwracają TERAZ) | — | ✅ częściowo zrobione |
| **1** | MATERIAŁ: paczka 4 plików + goldeny botów + **mapa polowania (cele ⬜)** | — | 40 min |
| **2** | KALIBRACJA: mutanty podłożone i zacommitowane; każdy audytor musi trafić swojego | — | 30 min |
| **3** | RUNDA A: 6 audytorów + łamacz, równolegle | 7 | 45 min |
| **4** | RUNDA B (KRYTYCY): te same moduły, inni agenci, + ocena roboty A | 6 | 45 min |
| **5** | WERYFIKACJA: każde znalezisko odtworzone dowodem wykonania | 3–6 | 40 min |
| **6** | NAPRAWY KLASAMI (grep po całości) + sweep „co przestało być prawdą" | — | 1,5–2 h |
| **7** | RE-AUDYT: szczegółowy tam, gdzie audyt był ogólny + **klamra wartości** (odcisk z kroku 0) | 4–6 | 1 h |
| **8** | ZAMKNIĘCIE PĘTLI: **maszyna (test z exit 1) na każdy potwierdzony błąd** + wersja 1.2.2 + paczki | — | 1 h |

**Razem: ~7 godzin, 16–19 wywołań agentów.** Subagenci na tańszym modelu z dietą (limit odpowiedzi,
budżet kroków). ⚠️ Limit tygodniowy jest na 60% — to jest realny koszt, nie „przy okazji".

## 🚦 WARUNKI KOŃCA (wszystkie trzy)
1. Zero celów ⬜ na mapie polowania (policzone).
2. Mutanty złapane przez każdego audytora (kalibracja ważna).
3. Zero anomalii bez wyjaśnienia.
+ Każdy naprawiony błąd ma **STRAŻNIKA** (test w CI).

## ⛔ ZASADY DLA KAŻDEGO AGENTA (do promptu, verbatim)
- **NIE MA WYMYŚLANIA BŁĘDÓW.** Każde znalezisko = kod potwierdzenia (komenda/test).
- **Konkretne stwierdzenia**, nie „to trzeba sprawdzić".
- **Audyt NIE NAPRAWIA** — opisujesz, nie zmieniasz.
- **Dobrych rzeczy nie raportujesz** — raport jest o problemach (kolega).
- **Zero z grepa = próba kontrolna obowiązkowa.**
- **Nie wiem = „NIE SPRAWDZIŁEM"**, nigdy „wygląda OK".
