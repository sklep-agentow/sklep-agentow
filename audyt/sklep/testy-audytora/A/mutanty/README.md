# Testy audytorów rundy A, które trafiły PODŁOŻONE błędy (bank mutantów, paczka `425a3124`) — NIE wady 0.9.13
Runda A biegła na paczce z gałęzi `audyt/mutanty-sklep` (7 mutantów M1–M7 + 4 pułapki). Te pliki audytorzy napisali (dziś: bank przeniesiony na TAG `bank/mutanty-sklep`, 31.08 #238)
jako „padające na obecnym kodzie" — i padały, ale na MUTANCIE. Na zamrożonej 0.9.13 (`5d3dcf9`, sha `7e0cf726…`)
przechodzą, bo czysty kod tej wady nie ma (recenzja #190 W2: karencja 5 min JEST w `zakup.php:308`, HEAD kończy `exit`
PRZED inkrementem w `pobieranie.php`, pętla 1 reconciliation LOGUJE `reconciliation_blad`, opcji `sag_pulpit_*` nie ma).
Po co zostają: (1) dowód czułości kalibracji (ROZLICZENIE-MUTANTOW-SKLEP.md na gałęzi mutantów), (2) gotowe strażniki,
gdyby ktoś kiedyś wniósł taką samą wadę naprawdę. ⛔ Nie uruchamiaj ich jako „testów wad 0.9.13" — PASS tutaj = brak mutanta.
⛔ `M5-test-uninstall-sag-prefix-survives.sh` NISZCZY stos `probny_wp` (włącza kasowanie danych i deinstaluje wtyczkę, nic nie przywraca) — po nim `bash narzedzia-qa/k7-stos-rundy.sh <zip>`. To samo dotyczy `../L4/test-upgrade-katalog-chroniony-brak.sh` (recenzja #191 W3).
| plik | mutant | soczewka |
|---|---|---|
| M2-test-l2-dociagnij-subscriber.php | M2 dociągnij bez `current_user_can` | L2 |
| M3-test-01-stale-claim.sh | M3 karencja claimu 0 s | L3 |
| M4-test-02-resend-dowod-skutku.sh | M4 resend `null !== $wyslano` | L3 |
| M6-test-03-head-nie-zuzywa-slotu.sh | M6 HEAD zużywa slot | L3 (unia z L5) |
| M7-test-04-slad-po-awarii-stripe.sh · M7-L-02b-…-test.php | M7 pętla 1 reconciliation bez wpisu | L3 · Ł |
| M5-test-uninstall-sag-prefix-survives.sh | M5 opcja bez prefiksu po uninstall | L4 |
`lib.sh` i `wyslij-webhook.sh` to DOWIĄZANIA do `../L3/` (recenzja #191 D3: kopie rozjechałyby się przy pierwszej poprawce), bo testy L3 je dołączają przez `cd "$(dirname "$0")"`.
