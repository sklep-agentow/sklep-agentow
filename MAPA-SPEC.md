# MAPA-SPEC — notatka zlecenia ↔ nasze rozumienie (moment 1 kadencji; 17.08)

Źródło: notatka kolegi (verbatim w ZROZUMIENIE.md) + doprecyzowania Dzidka z czatu 16-17.08.
Statusy: DOSŁOWNE (litera źródła) · INTERPRETACJA (nasza decyzja — wymaga klepnięcia Dzidka) ·
INTERPRETACJA (rozliczona źródłem) — odczyt nasz, ale POTWIERDZONY późniejszym zdaniem źródła ·
ODSTĘPSTWO PRZYJĘTE (świadome, z datą i powodem) · NIE-FUNKCJA (nie jest wymaganiem produktu).
Kolumna „Dowód" wypełniana w trakcie budowy (plik:linia / test).

| ID | Cytat ze źródła | Status | Nasze wykonanie | Dowód |
|----|-----------------|--------|-----------------|-------|
| S-01 | „Sklep z agentami (2x)" | DOSŁOWNE | 2 produkty-agenci w sklepie | ⬜ |
| S-02 | „Po zakupie, klienta na maila potwierdze, instrukcje" | DOSŁOWNE | mail po webhooku: potwierdzenie + instrukcje + link | ⬜ |
| S-03 | „Dwa rozne, ceny, co robi - na zachete klienta, jak dziala jak funkcjonuje, jak skraca czas pracy w firmie" | DOSŁOWNE | strony produktów z opisem sprzedażowym; „ile czasu oszczędza" poparte TREŚCIĄ paczki | ⬜ |
| S-04 | „Admin panel - widok kiedy za ile kupili w jakim czasie/ dashboard" | INTERPRETACJA — KLEPNIĘTA (Dzidek 18.08) | „w jakim czasie" = rozkład zakupów w czasie (lista + sumy w okresach); „czas zakupu" = czas webhooka | ⬜ |
| S-05 | „1. ChatBot LIVE" | DOSŁOWNE | produkt 1 | ⬜ |
| S-06 | „2. Segregator maila" | DOSŁOWNE | produkt 2 | ⬜ |
| S-07 | „BAZY DANYCH 1. Dane uzytkownikow / platnksci" | INTERPRETACJA — KLEPNIĘTA (Dzidek 18.08) | dane KUPUJĄCYCH (email) + dane transakcji (kwota, waluta, czas, id sesji, status); kont użytkowników BRAK — Dzidek 16.08: „my się nie zajmujemy kontami, na całą stronę jedno konto" | ⬜ |
| S-08 | „ZAPIS AGENTOW I KURSOW DO BAZY DANYCH NAWET PRZY DODAWANIU NOWYCH I USUWANIU I MODYFIKOWANIU" | ODSTĘPSTWO PRZYJĘTE (17.08) | produkty (agenci) w bazie z pełnym CRUD; KURSY = egzamin DRUGIEGO zdającego (Dzidek 17.08 ~00:00), u nas ich nie ma; „usuwanie" = deaktywacja przy istniejących zamówieniach (integralność — wzorzec kartki MP: nie usuwa się rzeczy powiązanej z aktywną sprawą) | ⬜ |
| S-09 | „KTO KIESY SIE LOGOWAL NA KONTO ADMINA DATY CZAS KONKRETY" | INTERPRETACJA — KLEPNIĘTA (Dzidek 18.08) | dziennik logowań: login_wpisany („kto" przy jednym koncie; nieudane próby też), czas, wynik, IP, przeglądarka („konkrety"); ROZSZERZENIE ponad literę: log AKCJI admina (jawnie dopisane) | ⬜ |
| S-10 | „Telefon jako kolo ratunkowe [do wykorzystania kazdy po 1 razie]" | NIE-FUNKCJA | zasada EGZAMINU: 1 telefon do egzaminatora. ⭐ POTWIERDZONE 2× przez Dzidka (17.08 ~00:00 oraz ~06:15, PO diffie reconstruction testu: „na pewno dla mnie był jeden telefon, nic do projektu"). W produkcie NIC nie budujemy | — |
| S-11 | (czat) podstrona `/sklep` na istniejącej stronie | DOSŁOWNE | katalog sklep/ wpinany w stronę | ⬜ |
| S-12 | (czat) „nie na wordpressie, zwykły HTML… na githubie hostowane" | DOSŁOWNE | statyczny hosting; zaplecze osobno | ⬜ |
| S-13 | (czat) „jak te pluginy: przypinamy, odpinamy i nie ma" | DOSŁOWNE | paczka + 1 punkt zaczepienia; odpięcie = czysty diff źródła + wyłączone zaplecze | ⬜ |
| S-14 | (czat) „najważniejsze zdać egzamin; na razie nie robimy na gotowo z prawdziwymi płatnościami" | DOSŁOWNE | całość w trybie testowym; produkcja = jawny rozdział różnic | ⬜ |
| S-15 | (czat) „wdrożymy do mojej i kolegi i 2 innych osób strony" | DOSŁOWNE — DOPRECYZOWANE 17/18.08 | ZMIANA (Dzidek, noc 17/18.08): strona = JEDNA WSPÓLNA dla całej czwórki (4 konta adminów przy wdrożeniu), NIE 4 osobne instalacje; wtyczka mimo to konfigurowalna w Ustawieniach (przenośność), test na czystym WP w K6 | ⬜ |
| S-16 | (czat) „termin do 30-tego i 3 próby oddania" | DOSŁOWNE | harmonogram z próbą 1 dnia 27.08 | — |

## 🔄 PIWOT 17.08 WIECZÓR (źródło: Dzidek, czat — verbatim w ZROZUMIENIE.md §PIWOT)

| ID | Cytat ze źródła | Status | Nasze wykonanie | Dowód |
|----|-----------------|--------|-----------------|-------|
| S-17 | „całą ta strona bedzie przekonwertowana na worpresa" | DOSŁOWNE | platforma docelowa = WordPress; konwersję strony robi NIE-my (założenie do potwierdzenia) | — |
| S-18 | „ten sklep i 3 pluginy bedą wyczkami do worpresa" + „do zlecenia jesze 2 testowe boty są do wystawienie jako produty na tym skielepi" | INTERPRETACJA (rozliczona źródłem) | sklep = wtyczka WP · produkty = 2 testowe boty jako wtyczki WP (ChatBot LIVE, Segregator maila); „3 pluginy" = sklep+2 boty ŁĄCZNIE — potwierdza notatka-wersja-aktualna 17.08: „Dwa rozne" | ⬜ |
| S-19 | [okienko] zaplecze „Wszystko w WordPressie" | DOSŁOWNE | własne tabele w bazie WP + panel w wp-admin + Stripe/maile z serwera WP; zero Cloudflare | ⬜ |
| S-20 | notatka-wersja-aktualna 17.08 (verbatim w ZROZUMIENIE §PIWOT): „ZAPIS AGENTOW DO BAZY DANYCH..." — BEZ „I KURSOW" | DOSŁOWNE | źródło samo potwierdza K1/S-08: kursów u nas nie ma; status S-08 przestaje być odstępstwem wobec NOWEJ wersji źródła (wobec starej z 16.08 — zostaje jako historia) | — |

**Wiersze UNIEWAŻNIONE piwotem:** S-11 (podstrona wpinana → wtyczka WP), S-12 („zwykły
HTML" → WordPress), S-13 w części mechanizmu (idea „przypinamy/odpinamy" ZOSTAJE — realizacja
= instalacja/deinstalacja wtyczki z czystym uninstall). S-01/S-05/S-06: liczba produktów
BEZ ZMIAN = **2** (ChatBot LIVE, Segregator maila — potwierdzone notatką 17.08),
zmienia się POSTAĆ: paczka plików → testowy bot jako wtyczka WP.

✅ Wiersze INTERPRETACJA S-04, S-07, S-09: KLEPNIĘTE przez Dzidka 18.08 („wszystkie
trzy robimy tak jak się robi i jak robią dobre sklepy") — treść bez zmian mimo piwotu,
plus WYTYCZNA WIĄŻĄCA: dashboard, dziennik i strony sklepu wg WZORCÓW DZIEDZINY
e-commerce zbieranych PRZED budową (plan v3, kroki K3/K5; rejestr D12).
Audytor zgodności (moment 4a) dostaje TEN plik + surowe źródło — statusy INTERPRETACJA
i ODSTĘPSTWO raportuje jako ZNANE, nie jako błędy.
