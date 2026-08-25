# REJESTR ZNALEZISK — SKLEP (K7) — jedno miejsce prawdy o tym, co znaleziono i co z tym zrobiono

Obiekt: paczka `sklep-agentow-0.9.13.zip` (SHA zamrożenia i sha256 w `audyt/sklep/ZAMROZENIE.md`).
Czyta i liczy: `narzedzia-qa/k7-koniec.sh`. Pisze: WYŁĄCZNIE koordynatorka (audytor oddaje raport JSON, nie edytuje rejestru).

## Statusy (dokładnie jeden na wiersz)
`DO WERYFIKACJI` (z raportu A/B, czeka na weryfikatora) · `ZWERYFIKOWANE` (dowód wykonania jest) · `OBALONE` (weryfikator pokazał,
że scenariusz nie zachodzi — z dowodem) · `W NAPRAWIE` · `ZAMKNIETE` (naprawa scalona + strażnik z dowodem padania na starym kodzie)
· `POZA K7` (decyzja właściciela z datą — hotfix/K8).

## Wagi (dokładnie jedna): `KRYTYCZNA` (wymóg zamówienia bez pokrycia / zły wynik) · `DUŻA` (awaria cicha) · `ŚREDNIA` (awaria głośna) · `DROBNA`.
Klasa = nazwa klasy z SCIAGA/SLEPE-PLAMY/PETLA-UCZENIA albo NOWA (wtedy: mutant do banku po K7).

## Znaleziska
| ID | soczewka | runda · SHA | waga | klasa | teza (z NAZWĄ mechanizmu) | dowód (plik:linia / komenda) | reprodukcja (raport §) | status | naprawa_proby | straznik (ścieżka) | straznik_padl_na_starym (sha) | PR |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| SK-L5-001 | L5 (recenzent PR #182, przed rundą A) | PR-0 · 38f7ef8 | DUŻA | 5 ściągi (ślady wewnętrzne) + 3 (dok kłamie) | nasza numeracja planu w tekstach paczki: `admin-produkty.php:134` tekst WIDOCZNY w wp-admin „(kryterium #7)" + 7 komentarzy `kryterium #`/`S-04`/`K4`/`plan §4A` w `front-produkt.php:8`, `class-sklepag-lista-zamowien.php:3`, `admin-zamowienie.php:23`, `admin-produkty.php:3,167,293`, `admin-dashboard.php:219` — bramka `slady` tej klasy nie zna | `grep -rn "kryteri" wtyczki/sklep-agentow/` (wyjście w recenzji) | recenzja #182 WAŻNA 1 | ZWERYFIKOWANE | 0 | — (do dopisania: wzorzec w `slady-wewnetrzne.sh` + próba kontrolna) | — | — |

## Anomalie (każde NIE SPRAWDZIŁEM / NIE DA SIĘ / nieoczekiwane wyjście / rozjazd dwóch pomiarów)
⛔ ID anomalii MUSI mieć postać `AN-NNN` (np. `AN-001`) — `k7-koniec.sh` warunek 4 czyta CAŁĄ tę tabelę i wiersz z innym ID = FAIL, nie „niewidzialny”. W kolumnach potok zapisuj jako `\|`.
| ID | soczewka | runda | opis | status (`OTWARTA` / `WYJAŚNIONA` / `CEL` / `POZA K7`) | dowód / decyzja / cel |
|---|---|---|---|---|---|
| — | — | — | (pusto do rundy A) | — | — |

## Fakty zamknięte PRZED audytem (żeby audytor nie odkrywał ich drugi raz — a jeśli odkryje, to zbieżność, nie nowość)
| co | wynik | dowód | data |
|---|---|---|---|
| e-maile w historii commitów (klasa K2 DESTYLATU) | 289/289 `*.noreply.github.com` (na `5d3dcf9`) | `git log --format=%ae \| sort \| uniq -c` | 25.08 |
| readme.txt / CHANGELOG.md / paczka sklepu | zbudowane, `paczka-zgodnosc` EXIT 0 | PR #182 | 25.08 |

## PR-y rundy napraw (reguła: 1 klasa = 1 PR; recenzent max 2 rundy)
| PR | klasa | znaleziska | rundy_recenzji | scalony (SHA) |
|---|---|---|---|---|
