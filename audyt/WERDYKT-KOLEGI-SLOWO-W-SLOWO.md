# 🎯 WERDYKT KOLEGI (Man1exter) — SŁOWO W SŁOWO + układ, kto co wie

> **Po co ten plik.** Właściciel nie chce tłumaczyć tego samego każdej nowej sesji. To jest
> ŹRÓDŁO — cytaty, liczby i układ „kto zna który błąd”. Zanim ruszysz na polowanie, przeczytaj
> to, a nie czyjeś streszczenie. Zapisane 1.09.2026.

## 1. CO KOLEGA POWIEDZIAŁ — cytaty, nie parafraza

Podział, który podał wprost:

> 3 Duże błędy zostały znalezione
> 4 Średnie błędy został znalezione
> i 4 Małe błędy został znalezione
> Razem: 11

O charakterze tych błędów i o samym audycie:

> większość z tych błędów to błędy infrastruktury - z samego audytu jest bardzo dobrze
> Dam 1 przykład tutaj
> ale ogolnie audyt perfekcyjny wyszedł
> taki 9/10

Jedyny przykład, jaki ujawnił (u nas: `I-1`, naprawione):

> przykład ponizej z infrastruktuy pisze:
> w tym: narzedzia-qa/bramka-poswiadczenia.sh
> linijka 11
> exit 2 = poza repozytorium git
> cd "$(git rev-parse --show-toplevel 2>/dev/null)" || ...
> to wyżej to z basha
> ma błąd
> git rev-parse poza repo zwraca pusty wynik
> natomiast wtedy bash działa dobrze
> BO
> POSWIADCZENIA: 0 trafień (drzewo: 0 plików; historia od HEAD: 0 commitów)
> wyskakuej wtedy to
> i repo jest not a git repository
> czyli sprawdza 0 plików + 0 commitów
> a to do bezpieczenstwa jest więc no
> infra tutaj ze tak powiem padła

⛔ **NAJWAŻNIEJSZE ZDANIE CAŁEGO WERDYKTU** — to nie jest uwaga o jednym pliku, tylko o metodzie:

> to znaczy w skrocie mozna tez zobaczyc ze do konca nie zostala zrobiona
> taki przykładzik 😃 sprawdz dzidkowski czy claude ci tego nie ominal albo czy nie zostawil
> tego na pozniej
> bo to tak wyglada

**Miał rację.** Sprawdzone 31.08: naprawiliśmy egzemplarz, opisaliśmy klasę 73 w ściądze —
a przypadek „uruchomiona spoza repo” miały **2 z 15** prób kontrolnych. Klasa była OPISANA,
nie OBSTAWIONA. Stąd `I-14` i nowy `narzedzia-qa/spoza-repo-proba-kontrolna.sh`.

Pochwały (zapisane, bo to też informacja — mówią, gdzie NIE szukać):

> ale że stripe bramke platnicza dzidkowski robil to ja w szoku jestem
> pod wrazeniem fest
> ogolnie mega dobrze to wyglada
> widac duze postepy tak szczerze
> sam audyt 9/10 dzidkowski

⛔ **DWIE RZECZY ZAPOMNIANE W AUDYCIE — ujawni je później:**

> zapomniales w nim 2 rzeczy zrobic ale to ci powiem kiedy indziej to

I na koniec, o błędzie, który zna właściciel:

> i jeden błąd duzy ja wiem jesze jeden jaki jest i nie ma go w tych błedach które znalazła
> powszednia ty

## 2. UKŁAD — kto co wie (stan 1.09)

| Kto | Ile zna | Status |
|---|---|---|
| Kolega (Man1exter) | **11** | podał **2**: `I-1` (bramka poświadczeń spoza repo) i **migawka produktu** („podmiana pliku przy zakupie”, naprawa PR #258 + spust `I-24`), **9 trzyma dla siebie** |
| Właściciel (Dzidek) | — | ⚠️ **SPROSTOWANIE 2.09 (słowa właściciela):** „błąd właściciela” z 1.09 to był błąd **wskazany przez kolegę** (podmiana pliku/produktu między płatnością a domknięciem). Właściciel nie trzyma osobnego błędu. |
| My | 31 znalezisk własnych | z tego 1 pokrywa się z przykładem kolegi |

⛔ **POTWIERDZONE 1.09 przez właściciela:** przeczytał naszą listę 31 znalezisk
(`/root/do-panelu/LISTA-BLEDOW-prostym-jezykiem-01-09.md`) i powiedział wprost:
**„NIE MA na liście 31 znalezionych”.** Jego błędu tam nie ma. Szukamy dalej.

⛔ Kolega ma jeszcze **2 rzeczy zapomniane w audycie**, które ujawni później — to jest
TRZECIA, osobna pula, nie mieszać jej z jedenastką ani z błędem właściciela.

## 3. CO Z TEGO WYNIKA DLA SZUKANIA

**Nasze wagi to NIE jego wagi.** On: 3/4/4. My kroiliśmy drobniej — jeden jego błąd może u nas
siedzieć jako dwa albo odwrotnie. Nie da się zestawić list jeden do jednego, dopóki nie ujawni
swojej. Zestawienie pozycja-w-pozycję **po ujawnieniu** to jedyny uczciwy pomiar naszej metody.

**Gdzie NIE szukać (z jego pochwał):** bramka płatnicza Stripe i sam audyt produktu dostały
najwyższe noty. To nie znaczy, że tam nie ma błędów — znaczy, że jego jedenastka raczej nie stamtąd.

**Zawężenie błędu KOLEGI — rozumowanie, nie zgadywanie (zapisane 1.09, gdy nazywaliśmy go
„błędem właściciela”; sprostowanie w tabeli wyżej — właściciel nie trzyma osobnego błędu):**
Właściciel ten błąd zna, a **nie czyta kodu**. Więc musi być widoczny bez otwierania plików:
w działaniu sklepu, w tekście, w tym co dostaje klient, albo w czymś, co mu ktoś pokazał.
To wyklucza schemat bazy, kolejkowanie CI i `CODEOWNERS`. Zostają najgorętsi kandydaci:
- **`A4b` — wygląd i DZIAŁANIE frontu w przeglądarce** (sprawdziliśmy tylko, JAK zasoby są
  ładowane, nie CO robią; zero zrzutów, nikt nie przeszedł ścieżki zakupu okiem),
- **`F5` — regulamin sklepu** jako osobny dokument,
- **`F4` — retencja danych kupujących** (kasowanie po czasie).
⚠️ Zastrzeżenie: może też siedzieć w jednym z **20 obszarów oznaczonych „częściowo”** w mapie
terenu — tam najłatwiej o złudzenie pokrycia.

## 4. GDZIE SZUKAĆ DALEJ — pliki, nie domysły

- `audyt/MAPA-TERENU-INFRA.md` — 57 pozycji, **8 nietkniętych**. Zacznij TU.
- `audyt/REJESTR-ZNALEZISK-INFRA.md` — 31 znalezisk, 20 zamkniętych.
- `audyt/SCIAGA-EGZAMINATORA.md` — 90 klas błędów, których nie wolno powtórzyć.
- `audyt/CO-WISI.md` — **932 linie, 29 pozycji długu**; ⛔ `I-16` mówi, że nic nie przenosi
  ich do rejestru ani nie pyta przed wydaniem, które JADĄ DO KLIENTA. Tam mogą leżeć rzeczy,
  o których „wiemy”, a które i tak pojechały.
- `/root/do-panelu/LISTA-BLEDOW-prostym-jezykiem-01-09.md` — ta sama lista prostym językiem,
  do czytania przez właściciela.
