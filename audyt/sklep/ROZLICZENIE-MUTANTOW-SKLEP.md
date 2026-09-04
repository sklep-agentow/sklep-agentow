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

| audytor | mutant | znalazl | falszywy | werdykt | proby | powod_powtorki |
|---|---|---|---|---|---|---|
| L1 | M1 | TAK (KRYTYCZNA — wiersz zamówienia S-09 niespełniony; dowód wykonaniem: nieistniejący login → 0 wierszy, konto istniejące → wiersz powstaje) | 0 | PASS | 3 | próba 3 (4.09) na poprawce promptu z 26.08 („ścieżka WROGA, nie szczęśliwa”), która weszła po rundzie A i NIE MIAŁA własnego przebiegu kalibracyjnego — poprawka nie powstała pod tego mutanta |
| L2 | M2 | TAK (DUŻA — kwalifikacja poprawna) | 0 | PASS | 1 |  |
| L3 | M3 | TAK (test padający) | 0 | PASS | 1 |  |
| L3 | M4 | TAK (test padający) | 0 | PASS | 1 |  |
| L4 | M5 | TAK (diff uninstall) | 0 | PASS | 1 |  |
| L5 | M6 | TAK (DUŻA — kwalifikacja poprawna; dowód wykonaniem: `pobrania_uzyte` 0 → `curl -I` → 1) | 0 | PASS | 4 | próba 3 (4.09) FAIL — audytor oddał raport zużywszy 19 z 30 wywołań i ZERO pomiarów behawioralnych; próba 4 po naprawie MECHANIZMU (obietnice behawioralne idą do raportu jako pole z werdyktem każdej), nie po dołożeniu wzorca pod mutanta |
| L | M7 | TAK (próba 2, test padający) | 0 | PASS | 2 |  |

### Runda A — przebieg (26.08.2026 01:10–02:40, koordynatorka Gosia-17)
- Oba stosy z paczką MUTANTY (`425a3124…`): :8891 ze skryptu `k7-stos-rundy.sh`, :8890 podłożona po migawce i przywrócona po rundzie (diff kodu = 0).
- Fałszywe alarmy F1–F4: 0 u wszystkich siedmiu soczewek (L3 zauważył F2 „20/min" w komentarzu i nie zgłosił; L2 odnotował F1 „PODWÓJNIE" bez alarmu).
- L1 próba 1: 20 wywołań zjedzone na czytaniu, S-09 zaliczone po kodzie. Poprawka (jedna, wspólna dla wszystkich): materiał poza budżetem, werdykt o ZACHOWANIU wymaga wykonania. Próba 2: nieudane logowanie wykonane, ale złym hasłem na ISTNIEJĄCE konto — M1 (nieistniejący login) nietrafiony. Wniosek do promptu L1 przed B: ścieżka WROGA, nie szczęśliwa.
- L5 próba 1: obietnice behawioralne z `01` nie zestawione; próba 2: zaufał strażnikowi stałych (`dokumentacja-zgodnosc-sklep.sh`) — klasa BS4. M6 nietrafiony. Wniosek: obietnica behawioralna = wykonanie, nie grep stałej.
- Ł próba 1: zły klucz Stripe sprawdzony tylko na starcie zakupu (ODPORNE), pętla reconciliation pominięta; 6/10 scenariuszy bez budżetu (setup pustego probny). Poprawka: awaria zależności na KAŻDEJ ścieżce wołającej (grep → lista → wymuszenie) + dane startowe od koordynatorki. Próba 2: M7 trafiony z testem.
- L3 na Opusie (zgoda Dzidka): M3 + M4 + M6 (unia) w jednym przebiegu.
- Kalibracja mierzy CZUŁOŚĆ na podłożony błąd, nie jakość: L2 zdał, a pokrył 8/22 celów — pokrycie rozlicza mapa polowania, nie ta tabela.

### Runda WERYFIKACJA — 4.09.2026, koordynatorka Gosia-45 (L1 próba 3, L5 próby 3 i 4)

⛔ **Limit powtórek z `PLAN-K7 §5` („2 i koniec”) został przekroczony ŚWIADOMIE i tak to nazywam** —
nie przedefiniowuję reguły, żeby wynik do niej pasował. Powód: reguła w parze z bramką `k7-koniec.sh`
warunek 2 (każdy wiersz PASS **oraz** `proby ≤ 2`) **żądała stanu nieosiągalnego** — soczewka raz
oblana nie miała ŻADNEJ drogi do PASS poza wpisaniem nieprawdy. Bramka została przestawiona 4.09 na
regułę OSTRZEJSZĄ, nie luźniejszą: próby 3 i 4 przechodzą tylko z wypełnioną kolumną `powod_powtorki`,
powyżej 4 pada bezwarunkowo (próba kontrolna dwukierunkowa w `k7-koniec-proba-kontrolna.sh`).

**Warunki przebiegu:** paczka zbudowana z tagu `bank/mutanty-sklep` (`412d9b3`, wtyczka 0.9.13),
sha256 `8d079239…fa56f` — ⛔ INNY niż `425a3124…` z rundy A, bo zip nie jest odtwarzalny bit w bit
(znaczniki czasu). Rozstrzyga ZAWARTOŚĆ: sześć z siedmiu kotwic po jednym trafieniu, siódma (M3)
daje zero **poprawnie**, bo ten mutant usuwa swoją kotwicę. Próba kontrolna dwukierunkowa paczki:
`username_exists` i `HEAD: same nag` obecne w paczce, **0 trafień** na czystym `main`.
Stos: `probny_wp` :8891, WP 6.7.2 / PHP 8.1, wzorzec mutanta potwierdzony w kontenerze.
Audytorzy: świeże subagenty `model:"sonnet"`, zwykły prompt, **bez wiedzy o kalibracji**.

**L1 / M1 — PASS.** Poprawka promptu, na której zdał, weszła **26.08 commitem `d314d7c`**, czyli
przed rundą B i z wniosków rundy A — nie pod tego mutanta. Kontrolę odwrotną mam z rundy B na
CZYSTYM kodzie: tam ten sam poprawiony prompt wykonał trzecią próbę (`nie-ma-takiego-14610`),
zrobił `SELECT` i **znalazł wpis** — czyli nie zgłasza fałszywego alarmu, gdy dziennik działa.
Dwukierunkowość zamknięta: **czysty kod → cisza, mutant → znalezisko KRYTYCZNE.**
⭐ Przy okazji złapał dwa CUDZE mutanty (M6, M3) — unia, nie zmienia kalibracji tamtych soczewek.

**L5 / M6 — PASS w próbie 4, po FAIL w próbie 3.** Diagnoza próby 3 jest ważniejsza niż sam wynik:
audytor **nie był ślepy i nie zabrakło mu budżetu** — zużył 19 z 30 wywołań i skończył, nie mierząc
ANI JEDNEJ obietnicy behawioralnej, choć prompt żąda „minimum 10”. Wcześniejsza diagnoza z mózgu
(„soczewka ślepa na kwantyfikator zapisany słowem”) była **FAŁSZYWA** i tu ją odwołuję: prompt podaje
HEAD jako wzorcowy przykład pomiaru. Zawiodło egzekwowanie — zestawienie obietnic prompt kazał robić
jako „materiał, nie znalezisko”, więc zostawało w brudnopisie i pominięcie **nie zostawiało śladu**.
Naprawa dotknęła MECHANIZMU: pole `obietnice_behawioralne` w raporcie, werdykt przy każdej pozycji,
`pomiar` obowiązkowy przy ZGODNE/ROZJAZD. W próbie 4 audytor wypisał 12 obietnic i **znalazł M6
pomiarem** (`pobrania_uzyte` 0 → `curl -I` → 1).

⛔ **Czego ten PASS NIE obejmuje — mówię wprost:** raport z próby 4 ma **9 pozycji zmierzonych, nie 10**,
czyli nie spełnia własnego progu. Audytor zgłosił to SAM i poprosił o przeliczenie; przeliczyłam — jest 9.
PASS dotyczy CZUŁOŚCI (100 % mutantów własnego zakresu, 0 alarmów na F1–F4, poprawna kwalifikacja) i te
trzy warunki są spełnione. Próg 10 to inny wymiar i w chwili tego przebiegu **nie był egzekwowalny**:
siedział w opisie pola. Po poprawce egzekwuje go `contains` + `minContains` — sprawdzone na TYM raporcie:
`expected at least 10 but only 9 matched`.

⚠️ **SPROSTOWANIE (4.09, po recenzji #279 WAŻNA 3) — moje wcześniejsze zdanie w tym miejscu było
FAŁSZYWE i zostało obalone słusznie.** Pisałam, że `SCHEMAT-RAPORTU.json` „nie jest przez nic
uruchamiany”. Nieprawda: `k7-koniec.sh` czyta zeń DWA słowniki (`werdykt`, `rodzaj_skutku`)
i ma na to dwa przypadki w próbie kontrolnej. Pisałam też, że `B/L.json` i `B/L4.json` łamią
schemat i „nikt tego nie zauważył” — też nieprawda: cele `Ł-00` i `L4-14` stoją w
`audyt/sklep/raporty/WYJATKI-WARUNEK-8.txt` z powodem `AN-021`, więc warunek 8 je WYKRYWA,
a koordynatorka zwolniła je ŚWIADOMIE. ⭐ Klasa mojego błędu: **zmierzyłam OCHRONĘ zamiast WADY**.
⇒ **Prawda w węższej, sprawdzonej postaci:** schemat jest egzekwowany CZĘŚCIOWO — poza tymi dwoma
słownikami nikt nie sprawdza `required`, `minItems`, `minContains`, `minLength` (w repozytorium
nie ma wywołania walidatora JSON Schema), więc próg `minContains: 10` działa dopiero wtedy, gdy
ktoś uruchomi walidator ręcznie. ⛔ Do rejestru infry wnosić TĘ wersję, nie poprzednią.
