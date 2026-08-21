# PĘTLA UCZENIA AUDYTU — jak sprawić, żeby ta sama klasa błędu nie wróciła

**Po co ten plik:** Dzidek, 21.08: „musimy w końcu się nauczyć robić audyt, co znajduje wszystko".

## ⛔ Najpierw uczciwie: audyt, który znajduje WSZYSTKO, nie istnieje

Recenzent jest **próbką, nie wyrocznią**. W jeden wieczór znajdzie pięć rzeczy, przy trzech
dniach trzydzieści — zawsze zostaje ogon. Obiecywanie kompletności to obiecywanie czegoś,
czego nie da się dotrzymać, a potem branie ciszy audytu za dowód jakości.

**Cel osiągalny i mierzalny: ZERO POWTÓREK.**
- Nowa klasa błędu w audycie = **normalna praca**. Dopisz mutanta, idź dalej.
- Ta sama klasa **drugi raz** = sygnał, że pętla nie zadziałała.
- Ta sama klasa **czwarty raz** (u nas: „uninstall nie sprząta" w poprzednim zleceniu) = **wpadka procesu**, nie pecha.

## 🔁 PĘTLA — cztery kroki, każdy z artefaktem

1. **ZNALEZISKO** → audytor oddaje je jako **padający test**, nie opis (stopka produkcyjna
   promptów). Nie umiesz napisać testu, który pada? Nie masz błędu.
2. **KLASA** → nazwij ją JEDNYM zdaniem opisującym MECHANIZM, nie objaw.
   ⛔ Zły zapis: „w pliku X brakuje sprawdzenia Y". Dobry: „naprawa trafia w egzemplarz,
   bliźniak zostaje w tym samym pliku".
3. **MUTANT** → dopisz do `BANK-MUTANTOW.md` pozycję, która tę klasę **podkłada**.
   Od teraz każdy audytor jest na nią kalibrowany. To jest moment, w którym wiedza
   przestaje zależeć od tego, czy ktoś pamięta.
4. **PYTANIE** → jeśli klasa przeszła przez WSZYSTKIE soczewki, to nie audytor zawiódł,
   tylko **mapa pytań ma dziurę**. Dopisz pytanie, nie audytora.

⭐ **Kryterium, po którym poznasz, że pętla działa:** przy następnym audycie ta klasa
zostaje złapana **przez kalibrację** (mutant), zanim w ogóle wejdzie do produktu.

## 📊 ZBIÓR Z 21.08 — siedem klas z JEDNEGO poranka (zmierzone, nie wymyślone)

Wszystkie wyszły przy domykaniu botów, u trzech różnych osób (builder1, builder2, koordynatorka).

⛔ **Etykiety P1-P7, nie K1-K7** — `SLEPE-PLAMY-NASZEGO-AUDYTU.md` używa już K1-K3 do czego innego.

| # | Klasa (mechanizm) | Ile | ODNOŚNIKI — każde wystąpienie osobno |
|---|---|---|---|
| **P1** | **naprawa trafia w egzemplarz, bliźniak zostaje** | **4×** | PR #122 (`LIKE` bez ucieczki: naprawione w `dowod-z-paczki.sh:316`, bliźniak został w `lib-stos-probny.sh:140`) · PR #126 runda 1 (wersja poprawiona w dokumentacji Segregatora, ChatBota **nie**) · PR #126 runda 2 (polskie znaki w obu `CHANGELOG.md`, w obu `readme.txt` **nie**) · PR #127 (`wp option get` naprawione dla `chatl_blad_ai`, bliźniak `chatl_blad_nonce` trzy linie niżej) |
| **P2** | **zamrożenie unieważnione przez późniejszą zmianę pliku z paczki** | 2× | ChatBot: paczka 1.5.7 zbudowana rano, PR #113 zmienił potem 3 pliki z paczki → `paczka-zgodnosc.sh chatbot-live` EXIT=1, 3 ROZJAZDY · Segregator: commit `3ddb39e` „zamrozenie bylo pozorne" (11 śladów), naprawione dopiero w `807d72d` „Zamrozenie NAPRAWDE" |
| **P3** | **test/bramka przechodzi z NIEWŁAŚCIWEGO powodu** | 3× | PR #124 (krok B dowodził działania **reguły demo**, nie własnej — recenzent odtworzył na żywo: wiadomość w `Segregator.Faktury` zamiast `Segregator.SEGMTEST-Faktury`) · PR #124 (builder2 sam zgłosił: pierwsza wersja testu przechodziła zawsze, bo źle liczyła błędy) · PR #125 (próba kontrolna **milczała** przy zgniłej kotwicy mutanta M1) |
| **P4** | **zaszyta wartość w teście unieważniona przez zmianę produktu** | 2× | PR #126: `testy/dokumentacja-zgodnosc-segregator.sh` — 5 miejsc z `1.3.0` + licznik wydań `"6"` · `testy/dokumentacja-zgodnosc.sh` — 2 miejsca z `1.5.7` |
| **P5** | **PR scalony do GAŁĘZI udaje scalonego do `main`** | 1× | PR #107: `gh pr view 107 --json baseRefName` → `boty/dowod-z-paczki` (nie `main`), a `git ls-tree -r origin/main` nie pokazywał `narzedzia-qa/dowod-aktualizacji.sh`. Weszło dopiero z PR #105 |
| **P6** | **nieśledzony plik wjeżdża do cudzego PR-a przez `git add -A`** | 1× | PR #122: `boty/BRIEF-BUILDER2-SEGREGATOR.md`, 71 linii, nieopisany w PR-ze; usunięty commitem `1f96238` |
| **P7** | **wyjaśnienie zamiast pomiaru** | 2× | PR #126: koordynatorka zbyła czerwoną `dokumentacja-zgodnosc.sh` uzasadnieniem „pada przez brak chromium" — uruchomiona dała **6 pozycji, 5 realnych** · **ten plik, runda 1**: tabela klas bez ani jednego odnośnika (dokument o dowodach — bez dowodów) |

⚠️ **P7 wystąpiła drugi raz W TYM PLIKU** i złapał to recenzent. Dopisane jako wystąpienie,
nie zamiecione — bo pętla, która nie liczy własnych potknięć, nie jest pętlą.

### Co z tego wynika dla NASZEGO audytu

- **P1 jest królem** — cztery wystąpienia w jeden poranek, w tym raz **zaraz po tym**, jak
  recenzent wytknął ją tej samej osobie. To nie jest kwestia uwagi: człowiek widzi miejsce,
  które ma właśnie otwarte. ⭐ Jedyne, co działa, to **mechaniczny odruch**: po każdej naprawie
  `grep` po wzorcu, nie „wydaje mi się, że to jedyne miejsce".
  → **Do stopki promptu audytora:** „znalazłeś błąd? zanim zgłosisz, sprawdź wyszukiwaniem,
  czy nie ma bliźniaka; zgłoś WSZYSTKIE wystąpienia albo napisz wprost, ilu nie sprawdziłeś".
- **P3 jest najgroźniejsza** — zielone z niewłaściwego powodu wygląda **identycznie** jak
  zielone z właściwego. Jedyny rozróżnik to kalibracja: podłóż błąd i sprawdź, czy narzędzie
  pada **na właściwym kroku**, nie gdziekolwiek.
- **P7 dotyczy audytora tak samo jak budowniczego.** Czerwień zbyta wyjaśnieniem jest gorsza
  niż czerwień nieprzeczytana, bo zamyka temat.

## ⛔ ZASADA NA KONIEC AUDYTU (bez tego pętla nie istnieje)

Audyt **nie jest skończony**, gdy zamknięto listę celów. Jest skończony, gdy:
1. każde potwierdzone znalezisko ma **test z kodem wyjścia 1** wpięty do CI,
2. każda **nowa klasa** ma mutanta w banku,
3. każda klasa, która przeszła przez wszystkie soczewki, ma **nowe pytanie** w mapie polowania,
4. ten plik ma dopisany wiersz z liczbą wystąpień.

⛔ Zero powtórek, nie zero błędów. Ogon zawsze zostaje — ale ma być coraz cieńszy
i nigdy z tej samej beczki.
