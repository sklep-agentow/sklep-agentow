# ROZLICZENIE MUTANTÓW SKLEPU — gałąź audyt/mutanty-sklep (PLAN-K7 §5)

Baza: `5d3dcf9` = tag `k7-zamrozenie` (sklep-agentow 0.9.13, zamrożony). Każdy mutant = osobny commit.

⛔ Ta gałąź NIE JEST SCALANA nigdzie. ⛔ Audytor NIE WIE o kalibracji (PLAN-K7 §5).
⛔ Kotwica = WZORZEC, nie numer linii — `podloz-mutanty-sklep.sh` przerywa z kodem 3, gdy wzorzec nie trafi dokładnie raz.
Runda A biegnie na PACZCE Z TEJ GAŁĘZI (znaleziska poza liniami mutantów są prawdziwe), runda B na czystej.

## Bank

| # | soczewka | plik | kotwica (wzorzec) | commit do odwrócenia | co robi / czego oczekujemy |
|---|---|---|---|---|---|
| M1 | L1 | `includes/logowania.php` | `function sklepag_loguj_nieudane` | `fd8529e` | nieudane próby na NIEISTNIEJĄCE loginy nie trafiają do dziennika (a to one są atakiem); opóźnienie wykładnicze liczy z dziennika, więc dla nich też znika |
| M2 | L2 | `includes/admin-dashboard.php` | `function sklepag_obsluz_dociagnij` | `8a4afb7` | „Dociągnij zaległe” sprawdza już tylko nonce; oczekiwana KWALIFIKACJA: para złamana, bez nonce nieeksploatowalne z zewnątrz — DUŻE, nie KRYTYCZNE |
| M3 | L3 | `includes/zakup.php` | `strtotime( $teraz ) - 300` | `9ce6557` | claim mail_w_toku odbierany natychmiast: webhook i cron/reconciliation biegną RAZEM, drugi regeneruje token → link z pierwszego maila umiera po cichu (dwa silniki, wyścig) |
| M4 | L3 | `includes/admin-zamowienie.php` | `null !== $wyslano` | `54c2146` | ponowna wysyłka: wp_mail=false → status mail_wyslany + zdarzenie mail_resend + zielony komunikat; gałąź mail_blad martwa — skutek do UDOWODNIENIA (stos: wp_mail false), nie do opisania |
| M5 | L4 | `includes/admin-dashboard.php` | `sag_pulpit_ostatnie_wejscie` | `196d590` | nowa opcja zapisywana w samonaprawie pulpitu; uninstall.php kasuje LIKE 'sklepag_%' → ta zostaje po odinstalowaniu (z ID użytkownika) |
| M6 | L5 | `includes/pobieranie.php` | `HEAD: same nagłówki` | `88da558` | HEAD zużywa slot pobrania; zamówienie (01-zamowienie.md: „HEAD nie zużywa”) i readme („do 10 pobrań”) obiecują inaczej; stała 10 bez zmian → dok=kod (L6) NIE łapie |
| M7 | L | `includes/zakup.php` | `Chwilowy błąd sieci/Stripe` | `34ffbec` | WP_Error ze Stripe w reconciliation znika bez śladu w zdarzeniach: zły klucz = wieczne „oczekuje” i pusty dziennik; wyrocznia w prompcie Ł |
| F1 | FAŁSZYWY | `includes/admin-zamowienie.php` | `Powtórka ŚWIADOMA` | `055ed52` | POPRAWNE: zdublowane current_user_can przed i po nonce — nadmiarowe, nie wadliwe |
| F2 | FAŁSZYWY | `includes/pobieranie.php` | `sklepag_rate_limit( 'pobierz', 20 )` | `5ea06b8` | POPRAWNE: próg 30→20 prób/min — OSTRZEJ; liczby 30 nie ma w readme/DOK-TECH/zamówieniu, więc nic nie kłamie |
| F3 | FAŁSZYWY | `includes/admin-dashboard.php` | `function_exists( 'sklepag_dociagnij_zalegle' )` | `02ee551` | POPRAWNE: obronny guard, zachowanie bez zmian; „martwy kod” to nie usterka |
| F4 | FAŁSZYWY | `includes/logowania.php` | `phpcs:ignore WordPress.Security.ValidatedSanitizedInput` | `613a36d` | POPRAWNE: wyłączenie reguły phpcs z uzasadnieniem; wartość idzie przez prepare() |

PACZKA RUNDY A: `wymiana/k7-mutanty/sklep-agentow-0.9.13-MUTANTY.zip` (poza gitem; odtwarzalna: `git checkout audyt/mutanty-sklep && BUDUJ_NADPISZ_WYDANE=1 bash boty/buduj-paczki.sh sklep-agentow`, potem przenieść i przywrócić zamrożoną).
SHA256 paczki z mutantami: `425a312458ad747fefeee06b86a49e11c3a74e33b0350714898eb9d7ee64d3ab` (zbudowana 26.08 z commitu `be5a218`).
Zamrożona 0.9.13 po budowie przywrócona: sha256 `7e0cf726…f7cc` = ZAMROZENIE.md. Kontrola zawartości: 7 kotwic mutantów po 1 trafieniu w zipie, literówka kontrolna 0.

Zdjęcie pojedynczego mutanta: `git revert <commit>`. Zdjęcie wszystkich: skasowanie gałęzi.

## Rozliczenie (wypełnia koordynatorka PO rundzie; czyta `narzedzia-qa/k7-koniec.sh` warunek 2)

PASS = 100 % mutantów WŁASNEGO zakresu + 0 alarmów na F1–F4 + poprawna kwalifikacja (M2: DUŻE nie KRYTYCZNE).
Limit: JEDNA poprawka promptu/materiału i jeden ponowny przebieg (`proby` ≤ 2); drugi FAIL = soczewka NIEKALIBROWANA.
Mutant trafiony przez CUDZĄ soczewkę = znalezisko tej soczewki, nie zmienia kalibracji przypisanej. L6 = niekalibrowana (jawnie).

| audytor | mutant | znalazl | falszywy | werdykt | proby |
|---|---|---|---|---|---|
| L1 | M1 | | | | |
| L2 | M2 | | | | |
| L3 | M3 | | | | |
| L3 | M4 | | | | |
| L4 | M5 | | | | |
| L5 | M6 | | | | |
| L | M7 | | | | |
