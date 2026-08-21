# Gdzie jest bank mutantów i dlaczego NIE MA go w tym repozytorium

Materiał audytowy (`01`–`06` w tym katalogu) audytor **ma dostać** — dlatego leży w repo.
`BANK-MUTANTOW.md` **celowo tu nie leży** i nie wolno go tu wstawiać.

## Powód — jednym zdaniem
Bank mutantów opisuje, **co dokładnie podłożymy audytorowi**, żeby sprawdzić, czy w ogóle
widzi błędy. Audytor, który przeczyta ten opis przed kalibracją, znajdzie podstawki
bez wysiłku — i **kalibracja skłamie**: pokaże czułość, której nie ma.

## Gdzie leży
`/root/praca/zlecenia/sklep-agentow-audyt/audyt/boty/BANK-MUTANTOW.md` (poza repozytorium).
Sam kod z mutantami żyje wyłącznie na gałęzi `audyt/mutanty-kalibracja`, która **nigdy**
nie jest scalana do `main` ani do gałęzi botów, powstaje z zamrożonej wersji i jest kasowana
po jednym przebiegu kalibracji.

## ⛔ Ryzyko, o którym trzeba wiedzieć
Skoro bank leży poza repozytorium, **git go nie odtworzy**. Jedno sprzątnięcie katalogu
roboczego i cały aparat kalibracji przepada. Kto go przenosi lub kasuje, robi kopię
w innym miejscu poza repo i dopisuje tu nową ścieżkę.
