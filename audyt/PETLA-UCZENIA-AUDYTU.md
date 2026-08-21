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

| # | Klasa (mechanizm) | Ile razy | Gdzie złapane |
|---|---|---|---|
| K1 | **Naprawa trafia w egzemplarz, bliźniak zostaje** | **4×** | recenzent ×3, koordynatorka ×1 |
| K2 | **Zamrożenie unieważnione przez późniejszą zmianę pliku z paczki** | 2× | `paczka-zgodnosc.sh` |
| K3 | **Test/bramka przechodzi z NIEWŁAŚCIWEGO powodu** | 3× | recenzent ×1, builder2 sam ×1, builder1 sam ×1 |
| K4 | **Zaszyta wartość w teście unieważniona przez zmianę produktu** | 2× | bramka zgodności dokumentacji |
| K5 | **PR scalony do GAŁĘZI udaje scalonego do main** | 1× | ręczne sprawdzenie `baseRefName` |
| K6 | **Nieśledzony plik wjeżdża do cudzego PR-a przez `git add -A`** | 1× | recenzent |
| K7 | **Wyjaśnienie zamiast pomiaru** („to pada przez brak przeglądarki") | 1× | recenzent, na koordynatorce |

### Co z tego wynika dla NASZEGO audytu

- **K1 jest królem** — cztery wystąpienia w jeden poranek, w tym raz **zaraz po tym**, jak
  recenzent wytknął ją tej samej osobie. To nie jest kwestia uwagi: człowiek widzi miejsce,
  które ma właśnie otwarte. ⭐ Jedyne, co działa, to **mechaniczny odruch**: po każdej naprawie
  `grep` po wzorcu, nie „wydaje mi się, że to jedyne miejsce".
  → **Do stopki promptu audytora:** „znalazłeś błąd? zanim zgłosisz, sprawdź wyszukiwaniem,
  czy nie ma bliźniaka; zgłoś WSZYSTKIE wystąpienia albo napisz wprost, ilu nie sprawdziłeś".
- **K3 jest najgroźniejsza** — zielone z niewłaściwego powodu wygląda **identycznie** jak
  zielone z właściwego. Jedyny rozróżnik to kalibracja: podłóż błąd i sprawdź, czy narzędzie
  pada **na właściwym kroku**, nie gdziekolwiek.
- **K7 dotyczy audytora tak samo jak budowniczego.** Czerwień zbyta wyjaśnieniem jest gorsza
  niż czerwień nieprzeczytana, bo zamyka temat.

## ⛔ ZASADA NA KONIEC AUDYTU (bez tego pętla nie istnieje)

Audyt **nie jest skończony**, gdy zamknięto listę celów. Jest skończony, gdy:
1. każde potwierdzone znalezisko ma **test z kodem wyjścia 1** wpięty do CI,
2. każda **nowa klasa** ma mutanta w banku,
3. każda klasa, która przeszła przez wszystkie soczewki, ma **nowe pytanie** w mapie polowania,
4. ten plik ma dopisany wiersz z liczbą wystąpień.

⛔ Zero powtórek, nie zero błędów. Ogon zawsze zostaje — ale ma być coraz cieńszy
i nigdy z tej samej beczki.
