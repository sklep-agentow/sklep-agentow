# MAPA-SPEC — notatka zlecenia ↔ nasze rozumienie (moment 1 kadencji; 17.08)

Źródło: notatka kolegi (verbatim w ZROZUMIENIE.md) + doprecyzowania Dzidka z czatu 16-17.08.
Statusy: DOSŁOWNE (litera źródła) · INTERPRETACJA (nasza decyzja — wymaga klepnięcia Dzidka) ·
ODSTĘPSTWO PRZYJĘTE (świadome, z datą i powodem) · NIE-FUNKCJA (nie jest wymaganiem produktu).
Kolumna „Dowód" wypełniana w trakcie budowy (plik:linia / test).

| ID | Cytat ze źródła | Status | Nasze wykonanie | Dowód |
|----|-----------------|--------|-----------------|-------|
| S-01 | „Sklep z agentami (2x)" | DOSŁOWNE | 2 produkty-agenci w sklepie | ⬜ |
| S-02 | „Po zakupie, klienta na maila potwierdze, instrukcje" | DOSŁOWNE | mail po webhooku: potwierdzenie + instrukcje + link | ⬜ |
| S-03 | „Dwa rozne, ceny, co robi - na zachete klienta, jak dziala jak funkcjonuje, jak skraca czas pracy w firmie" | DOSŁOWNE | strony produktów z opisem sprzedażowym; „ile czasu oszczędza" poparte TREŚCIĄ paczki | ⬜ |
| S-04 | „Admin panel - widok kiedy za ile kupili w jakim czasie/ dashboard" | INTERPRETACJA | „w jakim czasie" = rozkład zakupów w czasie (lista + sumy w okresach); „czas zakupu" = czas webhooka | ⬜ |
| S-05 | „1. ChatBot LIVE" | DOSŁOWNE | produkt 1 | ⬜ |
| S-06 | „2. Segregator maila" | DOSŁOWNE | produkt 2 | ⬜ |
| S-07 | „BAZY DANYCH 1. Dane uzytkownikow / platnksci" | INTERPRETACJA | dane KUPUJĄCYCH (email) + dane transakcji (kwota, waluta, czas, id sesji, status); kont użytkowników BRAK — Dzidek 16.08: „my się nie zajmujemy kontami, na całą stronę jedno konto" | ⬜ |
| S-08 | „ZAPIS AGENTOW I KURSOW DO BAZY DANYCH NAWET PRZY DODAWANIU NOWYCH I USUWANIU I MODYFIKOWANIU" | ODSTĘPSTWO PRZYJĘTE (17.08) | produkty (agenci) w bazie z pełnym CRUD; KURSY = egzamin DRUGIEGO zdającego (Dzidek 17.08 ~00:00), u nas ich nie ma; „usuwanie" = deaktywacja przy istniejących zamówieniach (integralność — wzorzec kartki MP: nie usuwa się rzeczy powiązanej z aktywną sprawą) | ⬜ |
| S-09 | „KTO KIESY SIE LOGOWAL NA KONTO ADMINA DATY CZAS KONKRETY" | INTERPRETACJA | dziennik logowań: login_wpisany („kto" przy jednym koncie; nieudane próby też), czas, wynik, IP, przeglądarka („konkrety"); ROZSZERZENIE ponad literę: log AKCJI admina (jawnie dopisane) | ⬜ |
| S-10 | „Telefon jako kolo ratunkowe [do wykorzystania kazdy po 1 razie]" | NIE-FUNKCJA | zasada EGZAMINU (Dzidek 17.08: „to dla mnie"): 1 telefon ratunkowy do egzaminatora; w produkcie NIC nie budujemy | — |
| S-11 | (czat) podstrona `/sklep` na istniejącej stronie | DOSŁOWNE | katalog sklep/ wpinany w stronę | ⬜ |
| S-12 | (czat) „nie na wordpressie, zwykły HTML… na githubie hostowane" | DOSŁOWNE | statyczny hosting; zaplecze osobno | ⬜ |
| S-13 | (czat) „jak te pluginy: przypinamy, odpinamy i nie ma" | DOSŁOWNE | paczka + 1 punkt zaczepienia; odpięcie = czysty diff źródła + wyłączone zaplecze | ⬜ |
| S-14 | (czat) „najważniejsze zdać egzamin; na razie nie robimy na gotowo z prawdziwymi płatnościami" | DOSŁOWNE | całość w trybie testowym; produkcja = jawny rozdział różnic | ⬜ |
| S-15 | (czat) „wdrożymy do mojej i kolegi i 2 innych osób strony" | DOSŁOWNE | paczka konfigurowalna per strona (config.js + własne konta) | ⬜ |
| S-16 | (czat) „termin do 30-tego i 3 próby oddania" | DOSŁOWNE | harmonogram z próbą 1 dnia 27.08 | — |

⛔ Wiersze INTERPRETACJA wymagają klepnięcia Dzidka (2 minuty): S-04, S-07, S-09.
Audytor zgodności (moment 4a) dostaje TEN plik + surowe źródło — statusy INTERPRETACJA
i ODSTĘPSTWO raportuje jako ZNANE, nie jako błędy.
