# STAN NA KONIEC SESJI AUDYTORA — 1.09.2026, przed czyszczeniem kontekstu

Plik jest po to, żeby **nic z tej sesji nie mieszkało w głowie**. Wszystko poniżej jest
sprawdzalne komendą albo leży w wskazanym pliku.

## 1. Gdzie jest robota

**Gałąź `audyt/czesciowo-01-09` → PR #261** (nie scalony). Diff: wyłącznie `audyt/`, zero kodu
produktu i zero testów. Zawartość:

| plik | co w nim jest |
|---|---|
| `audyt/czesciowo/RAPORT-CZESCIOWO-01-09.md` | dwanaście obszarów „częściowo": `E7`, `E6`, `F2`, `A5`, `B6`, `B5`, `H1`, `H2`, `H3`, `A1`, `A2`, `A3` |
| `audyt/czesciowo/L1-06-L1-09-REALNY-ZAPIS.md` | CRUD i ustawienia zmierzone realnym żądaniem (ostatnie zadanie) |
| `audyt/plamy/ZAMOWIENIE-WERDYKTY.md` | werdykty dla 16 wymagań zamówienia z pustym kwadratem |
| `audyt/plamy/STRAZNIK-IDEM-SK-L3-005-WERDYKT.md` | strażnik `SK-L3-005` kłamie od 0.9.25 (klucz okna bez członu trybu) |
| `audyt/plamy/SK-L-005-WERDYKT.md` | waga ŚREDNIA zostaje, uzasadnienie w rejestrze do poprawy (gotowe teksty w §4) |
| `audyt/plamy/GOLDEN-258-WERYFIKACJA.md` | golden `EXIT=0`, kalibracja `EXIT=0`, **stos NIE zostaje czysty** |
| `audyt/czesciowo/H2-sonda-replay.php` · `E7-sonda-*.js/.php` | przyrządy pomiarowe (nie strażniki, nie wchodzą do bramek) |
| `audyt/REJESTR-ZNALEZISK-INFRA.md` | dopisane `I-24`…`I-27`, licznik 34 → 36 otwartych (po przeliczeniu komendą na scalonym drzewie, 72 wiersze), 13 → 17 bez strażnika; poprawiony opis `I-9` |
| `audyt/CO-WISI.md` | wpis 🔴 o goldenie bez spustu |
| `audyt/SCIAGA-EGZAMINATORA.md` | **klasa 97** (cytat `plik:linia` bierze się z HEAD, nie ze stosu) |

## 2. PR #261 — stan recenzji

**Runda 2 (recenzent):** 2 × WAŻNE, 3 × DROBNE, 0 × KRYTYCZNYCH; bramki 12/12 `EXIT=0`.
**Cztery z pięciu uwag domknięte commitem `95b8195`** (punkty 1, 3, 4, 5 niżej); punkt 2 to edycja opisu PR-a na GitHubie, nie commit w repo — `eef4a0f` w tym miejscu nie istnieje w historii (`git cat-file -e` pada), a żaden z sesyjnych commitów po `95b8195` (`739405e`, `a426c3b`, `e59e2b4`, `f4dc0b7`) nie dotyka plików z punktów 2–5 niżej.
1. cytaty `plik:linia` przeliczone **na HEAD** (42 wzorce w pięciu plikach; ⚠️ runda 5 wykazała, że sweep po wzorcu `plik:linia` pominął LICZBY i bliźniaczy plik `STRAZNIK-IDEM-SK-L3-005-WERDYKT.md` — domknięte w rundzie 6) — sprawdzone
   skryptem drukującym faktyczną treść cytowanej linii;
2. opis PR-a przepisany: tabela wszystkich znalezisk z wagami, dziesięć obszarów, oba kody
   sondy replay, jawnie **obowiązująca liczba `A5` = 61 sprzątanych**;
3. odsyłacze przy obalonym rachunku `A5` (trzy miejsca) do korekty w sekcji `A1`;
4. `Version: 0.9.31` w werdyktach `S-12`/`S-18` (miejsc o **paczce** 0.9.28 nie ruszałem);
5. `E7-sonda-dziekujemy.js` — ścieżka względna, sprawdzone, że `require` się rozwiązuje.

⛔ **Czego runda 3 jeszcze nie zobaczyła:** sekcje `A2`, `A3` i raport `L1-06/L1-09` weszły
**po** rundzie 2. Recenzent rundy 2 **nie mógł powtórzyć żadnego pomiaru** (`stos.sh stan`
→ `EXIT=2`, kontener u niego nie stał), więc sonda replay, kolejka crona, trzy katalogi
chronione i cały `L1-06/L1-09` mają **jedno źródło pomiaru: mnie**.

## 3. Co wiem o stanowisku, czego nie widać w kodzie

Trzy pomiary **są już w rejestrze** jako `I-25`, `I-26`, `I-27` — tu skrót, żeby nie trzeba
było ich szukać:

1. ⛔ **WP-Cron NIE CHODZI** — 15 zadań w kolejce, **12 zaległych**, najstarsze
   `sklepag_dociagnij_zalegle` z 31.08 23:09 (**19 h**). Skutek w danych: trzy zamówienia stoją
   w `mail_w_toku` od sierpnia. **Każdy dowód „domknie się co 10 minut" nigdy tu nie biegł** —
   dotyczy `SK-L-005` i celów `L3-02`/`L3-04`. Domyka wyłącznie self-heal przy wejściu na Pulpit.
2. ⛔ **`upload_max_filesize = 2M`** przy paczce **4 719 003 B** → Krok 1 instrukcji klienta
   jest na tym stanowisku **niewykonalny**; werdykt `ODPORNE` przy `E-2` opisuje 3 kroki z 7.
   ⚠️ **Builder1 właśnie to zmienia** — po jego robocie ta liczba będzie inna i to jest
   OCZEKIWANE, nie regresja; pomiar trzeba powtórzyć, nie porównywać.
3. ⛔ **Baza łapacza poczty leży w `/tmp` WEWNĄTRZ kontenera**
   (`/tmp/mailpit-1787681659435740998.db`, 700 KB, 98 wiadomości): `docker restart` kasuje
   **cały materiał dowodowy o mailach po cichu**. Każde nasze „mail wyszedł" może już nie istnieć.

Do tego, poza rejestrem:
- **stos wiezie inny kod niż gałąź** — kontener miał `sklep-agentow` **0.9.32**, `main` ma
  `0.9.31`; stąd wzięła się klasa 97. Sprawdzaj `Version:` w kontenerze przed każdym pomiarem.
- **`wp eval` melduje `memory_limit = -1`**, bo WP-CLI podnosi go sobie; produkt chodzi ze **128M**.
- **`wp-content/debug.log`: 2,0 MB, 276 fatali, 6010 ostrzeżeń** — wszystkie klasy fatali
  sprawdzone i zamknięte na HEAD (`A2`/`A3`), ale plik rośnie i nikt go nie czyta.
- **Trzy katalogi `sklepag-chronione-*`**, opcja wskazuje pusty — to jest `A1-F1`.
- **Migawka opcji z hashami PRZED każdym POST-em na Settings API** — bez niej mój zapis
  ustawień wyzerowałby po cichu `sklepag_usun_dane_przy_odinstalowaniu` (checkbox nie jest
  wysyłany, gdy odznaczony). Przywrócone, opisane w raporcie `L1-06/L1-09`.

## 4. Co zostaje otwarte dla następnego

- **Recenzje rund 3 i 4 PR #261 opublikowane i domykane 2.09** — runda 4 wniosła 2 × WAŻNE (dziesięć cytatów `plik:linia` przesuniętych mergem #259 wraz z numerem klasy 94→97; **ten dokument** — commit-widmo `eef4a0f` i zdania o stanie recenzji) i 2 × DROBNE (etykieta „wywołań” w A2/A3, opis PR-a bez ostatnich commitów); ta sesja je domyka.
- **Trzy pozycje `SRED` do naprawy przez buildera**: `A1-F1` (katalogi chronione przeżywają
  „pełne czyszczenie"), `B6-F1` (32 z 54 testów PHP bez spustu), `H1-F1`/`I-25` (martwy cron).
- **Do rejestru sklepu, teksty gotowe do wklejenia** w `audyt/plamy/SK-L-005-WERDYKT.md` §4:
  poprawka uzasadnienia `SK-L-005`, nowa pozycja o liczniku ostrzeżeń ślepym na CISZĘ webhooka,
  dopisek do `SK-L3-002` (jego strażnik kończy `EXIT=0` z komunikatem `POMINIETY` — zielony
  bez pomiaru).
- **Nietknięte w mapie**: `A6` (schemat bazy — zamknięty osobno 1.09), `C12`, `C13`, `E5`,
  `F4`, `F5`, `A4b` — poza moim zakresem tej sesji.
- **Zaparkowane wcześniejszą decyzją**: STARTTLS klienta IMAP, wydajność przy większych danych.
