# 📋 REJESTR ZNALEZISK AUDYTU — jedno źródło prawdy

> ⛔ **Pilnowany maszynowo** przez `narzedzia-qa/bramka-znaleziska.sh`.
> Nowy raport audytu bez wpisu tutaj **zatrzymuje bramkę**. Znalezisko bez testu też.
>
> Powód powstania (21.08): mieliśmy zasadę „każdy błąd dostaje strażnika z exit 1 wpięty
> do CI" i **nie była spełniona ani razu**. W `testy/` leżało 57 plików, CI nie uruchamiało
> żadnego. PR #135 wniósł dwa celowo padające testy — i przeszedł na zielono.

## Liczba znalezisk OTWARTYCH: 6
<!-- BRAMKA: powyższa liczba musi zgadzać się z liczbą wierszy o statusie OTWARTE.
     Dopisanie znaleziska ZATRZYMUJE bramkę, dopóki ktoś świadomie nie podniesie liczby.
     ⭐ Ta liczba ma prawo TYLKO MALEĆ. Jeśli rośnie — to znaczy, że audyt znalazł coś nowego,
     i to jest w porządku; ale musi być świadome, nie po cichu. -->

## Znaleziska

| kod | co | test (dowód) | status |
|---|---|---|---|
| `F1` | ChatBot melduje „Ustawienia zapisane", gdy baza zapis odrzuciła | `audyt/boty/testy-audytu/test-awaria-zapisu.php` | W NAPRAWIE |
| `F2` | `chatl_przejmij()` zwraca `true` nie sprawdziwszy zapisu; gość dostaje nieprawdę | `audyt/boty/testy-audytu/test-awaria-zapisu.php` | W NAPRAWIE |
| `F3` | zapis klucza API bez pomiaru — stary klucz zostaje, nikt się nie dowiaduje | `audyt/boty/testy-audytu/test-awaria-zapisu.php` | W NAPRAWIE |
| `A3-1` | `chatl_sesje` — tabela bez żadnej ścieżki kasowania, poza kasownikiem RODO | `testy/chatbot-sesje-retencja-rodo-test.php` | OTWARTE |
| `A3-2` | zombie cron ChatBota zostaje po odinstalowaniu wtyczki | `testy/uninstall-zombie-cron-test.php` | OTWARTE |
| `KR1` | puste pole „dobowy limit tokenów" wyłącza drugi stop-loss kosztowy, po cichu | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `KR2` | puste pole „dobowy limit wywołań" zjeżdża limit do 1, po cichu | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `KR4` | puste pole „port"/„limit" skrzynki IMAP daje port 1 i limit 1, po cichu | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `KR6` | retencja ChatBota kasuje rozmowy PRZED obiecanymi 90 dniami w strefie ujemnej | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |

## Statusy — co znaczą
- **OTWARTE** — błąd potwierdzony dowodem, kod nienaprawiony. Test **pada** i tak ma być.
- **W NAPRAWIE** — naprawa istnieje w niescalonym pull requeście. Po scaleniu → ZAMKNIĘTE.
- **ZAMKNIĘTE** — kod naprawiony, test **przechodzi** i staje się strażnikiem regresji.
  ⛔ Test ZAMKNIĘTEGO znaleziska nigdy się nie kasuje — od tej chwili pilnuje, żeby nie wróciło.

## Raporty objęte rejestrem
`RAPORT-A2-AWARIA.md` · `RAPORT-A3-DANE.md` · `RAPORT-A5-KRAWEDZIE.md`
<!-- BRAMKA: każdy plik audyt/boty/RAPORT-*.md musi być wymieniony w linii wyżej. -->

## Czego ta bramka NIE robi — granica, świadomie
⛔ **Nie uruchamia testów.** Wszystkie 23 skrypty i 18 testów PHP wymagają żywego stosu
Dockera — psują kod w kontenerze i sprawdzają, czy strażnik pada. Uruchamianie tego
w CI biegnącym równolegle na tej samej maszynie oznaczałoby, że przeloty depczą sobie po stosie.
Bramka pilnuje **księgowości znalezisk**: że istnieją, mają test, mają status i nikt ich
po cichu nie usunął. Wykonanie testów zostaje przy człowieku i przy recenzencie.
