# Runda WERYFIKACJA — 4.09.2026, kalibracja soczewek L1 i L5

Trzy raporty audytorów z przebiegu, który zamknął `FAIL 2` bramki wydania. Leżą TUTAJ, a nie
w katalogu roboczym koordynatorki, bo **dowód poza repozytorium nie istnieje** — cały PASS dla
`L1` i `L5` stoi na tych plikach i recenzent musi mieć czym go sprawdzić.

| plik | soczewka | mutant | wynik |
|---|---|---|---|
| `L1.json` | L1 | M1 (dziennik nie loguje NIEISTNIEJĄCYCH loginów) | **PASS**, próba 3 |
| `L5-proba3.json` | L5 | M6 (HEAD zużywa slot pobrania) | **FAIL**, próba 3 |
| `L5-proba4.json` | L5 | M6 | **PASS**, próba 4 |

## Warunki przebiegu (odtwarzalne)

- Paczka zbudowana z tagu `bank/mutanty-sklep` (`412d9b3`, wtyczka 0.9.13),
  sha256 `8d0792390497e56bfeb1c8c19bc9b901f8ef0b34cf882b7ffbad2875967fa56f`.
  ⛔ **Inny niż `425a3124…` z rundy A** — zip nie jest odtwarzalny bit w bit (znaczniki czasu).
  Rozstrzyga ZAWARTOŚĆ: sześć z siedmiu kotwic po jednym trafieniu; siódma (`M3`) daje zero
  **poprawnie**, bo ten mutant usuwa własną kotwicę.
- Próba kontrolna paczki, dwukierunkowa: `username_exists` i `HEAD: same nag` obecne w paczce,
  **0 trafień** na czystym `main`.
- Stos: `probny_wp` `:8891`, WordPress 6.7.2 / PHP 8.1, wzorzec mutanta potwierdzony w kontenerze.
- Audytorzy: świeże subagenty `model:"sonnet"`, **zwykły prompt, bez wiedzy o kalibracji**
  (`STOPKA.md`: audytor, który wie o podłożonym błędzie, zmyśli, byle go znaleźć).
- `commit_head` w raportach to `412d9b38…` (HEAD gałęzi mutantów). ⛔ Różni się od SHA
  w `ZAMROZENIE.md` (`5d3dcf9`) i **tak ma być**: zamrożenie opisuje czystą 0.9.13, a gałąź
  mutantów niesie commity mutantów na wierzchu. Audytor L5 sam to zgłosił jako anomalię,
  nie zgadując — słusznie.

## ⛔ Czego te raporty NIE spełniają — mówię wprost

`L5-proba4.json` ma **9 pozycji zmierzonych w `obietnice_behawioralne`, nie 10**, czyli nie
spełnia progu, który ten sam PR wprowadza. Audytor zgłosił to SAM i poprosił o przeliczenie;
przeliczyłam — jest 9. Powód, dla którego raport mimo to jest tu i mimo to daje `PASS`:

- `PASS` w kalibracji mierzy **CZUŁOŚĆ** (100 % mutantów własnego zakresu, 0 alarmów na
  pułapkach `F1–F4`, poprawna kwalifikacja) — te trzy warunki są spełnione;
- próg 10 to **inny wymiar** i w chwili tego przebiegu **nie był egzekwowalny** — siedział
  w opisie pola, a `minItems` liczy pozycje, nie werdykty. Od tego PR-a egzekwuje go
  `contains` + `minContains`, sprawdzone na TYM pliku:
  `Too few items match the given schema (expected at least 10 but only 9 matched)`.

⇒ Ten raport **nie przeszedłby dzisiejszego schematu** i to jest zamierzone: jest dowodem
historycznym, nie wzorcem do naśladowania. Następny raport L5 musi mieć 10 zmierzonych.

⛔ Raportów **nie wolno edytować po fakcie** — to są dowody. Jeśli któryś łamie schemat,
opisuje się to tutaj, a nie poprawia plik.

## ⛔ Dlaczego te raporty NIE leżą w `audyt/sklep/raporty/`

Bo `k7-koniec.sh` warunek 8 waliduje tamten katalog przy założeniu, że **każdy raport powstał
na paczce ZAMROŻONEJ** — wymaga `commit_head` równego SHA z `ZAMROZENIE.md` (`5d3dcf9`).
Raporty kalibracyjne są z definicji robione na **paczce Z PODŁOŻONYMI BŁĘDAMI**, więc niosą
`commit_head` gałęzi mutantów (`412d9b38`). Zmierzone: po wrzuceniu ich do `raporty/`
warunek 8 dawał `FAIL 8 raporty: 4 naruszeń w 18 plikach`; po przeniesieniu tutaj wraca
`PASS 8 raporty: 15 plików, 0 odrzuconych werdyktów`.

⭐ To nie jest obejście bramki, tylko **rozróżnienie dwóch różnych rzeczy**: raport z rundy
audytu (zamrożony kod) i raport z kalibracji (kod z mutantem) mierzą co innego i nie powinny
być sprawdzane tym samym przyrządem. Ta sama zasada, co przy zawężeniu wymogu
`obietnice_behawioralne` do rund `weryfikacja`/`re-audyt`.

⛔ `L5-proba3.json` ma dodatkowo 5 werdyktów spoza słownika schematu (`ŚREDNIA`, `DROBNA`
w miejscach, gdzie słownik ich nie przewiduje). **Nie poprawiam tego** — to raport-dowód
z nieudanej próby i ma zostać taki, jaki oddał audytor.

## ⛔ SPROSTOWANIE — moje wcześniejsze zdanie o schemacie było ZA MOCNE

W opisie PR #279 i w komunikacie commita `d7388c8` napisałam, że `SCHEMAT-RAPORTU.json`
**„nie jest przez nic uruchamiany”**. To NIEPRAWDA i recenzent słusznie to obalił.
Zmierzone (`grep` po `narzedzia-qa/k7-koniec.sh`):

```
$s[0].properties.werdykty.items.properties.werdykt.enum
$s[0].properties.znaleziska.items.properties.rodzaj_skutku.enum
```
Warunek 8 **czyta schemat** i egzekwuje go jako słownik **dwóch list dopuszczalnych wartości**;
`k7-koniec-proba-kontrolna.sh` ma na to dwa przypadki (werdykt i `rodzaj_skutku` spoza słownika).

**Co zostaje prawdą, w węższej i sprawdzonej postaci:** schemat jest egzekwowany CZĘŚCIOWO.
Poza tymi dwoma enumami nikt nie sprawdza `required`, `minItems`, `minContains` ani `minLength` —
w repozytorium nie ma żadnego wywołania walidatora JSON Schema (`grep -rn "jsonschema\|minContains"`
po `*.sh` i `*.yml` → brak). ⇒ **próg `minContains: 10`, który ten PR wprowadza, działa dopiero
wtedy, gdy ktoś uruchomi walidator — dziś robi to koordynatorka ręcznie.**

⛔ **Drugie moje zdanie też było fałszywe:** twierdziłam, że `raporty/B/L.json` i `B/L4.json`
łamią schemat i „nikt tego nie zauważył, bo nikt nie walidował”. Zmierzone: te dwa cele
(`Ł-00`, `L4-14`) stoją na jawnej liście `audyt/sklep/raporty/WYJATKI-WARUNEK-8.txt`
z wpisanym powodem (`AN-021`: dowód jest KOMENDĄ, nie miejscem w pliku). Czyli warunek 8
je **wykrywa**, a koordynatorka **świadomie** je zwolniła — mechanizm zadziałał.

⭐ To jest klasa „zmierzyłam OCHRONĘ zamiast WADY”: mój walidator pokazał naruszenia, a ja nie
sprawdziłam, czy repozytorium już je zna i celowo dopuściło. Zapisuję to tutaj, bo o mały włos
wniosłabym fałszywe znalezisko do rejestru infry.
