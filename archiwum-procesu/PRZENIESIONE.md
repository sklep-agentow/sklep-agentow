# Archiwum procesu — PRZENIESIONE poza repo (2.09.2026)

Robocze dokumenty z czasu budowy (plany etapów, zwiady wzorców, tablica koordynacji,
prompty krytyk wielomodelowych, konfiguracja stanowiska testowego; 20 plików) leżały tu do 2.09.2026.
Decyzja właściciela projektu: nie są częścią produktu ani dowodów jakości, więc nie ma ich w repo.

## Jak odzyskać dowolny plik (bez pytania nikogo)
Treść siedzi w historii gita tego repo. Ostatni commit z katalogiem to rodzic `13cf84d`:
```
git ls-tree -r --name-only 13cf84d^ -- archiwum-procesu          # lista 20 plików
git show 13cf84d^:archiwum-procesu/TABLICA.md                     # dowolny z nich
```

## Gdzie repo nadal wspomina `archiwum-procesu/` (18 plików, `git grep -l 'archiwum-procesu' -- . ':!archiwum-procesu'`)
Wszystkie to odwołania HISTORYCZNE (opisują decyzje i pomiary z czasu, gdy katalog istniał) — poza `README.md`,
który opisuje stan dzisiejszy i został poprawiony w tym samym PR-ze:
- `DECYZJE.md`
- `README.md`
- `ZROZUMIENIE.md`
- `audyt/CO-WISI.md`
- `audyt/LINIA-BAZOWA-TESTOW.md`
- `audyt/PLAN-NAPRAW.md`
- `audyt/RAPORT-ODDANIA.md`
- `audyt/REJESTR-WERYFIKACJI.md`
- `audyt/boty/MATERIAL-AUDYTU-SEGREGATOR.md`
- `audyt/boty/RAPORT-PRZEBIEG2-CHATBOT.md`
- `audyt/boty/material/02-fakty-srodowiska.md`
- `audyt/boty/material/07-mapa-dokumentacji.md`
- `audyt/czesciowo/RAPORT-CZESCIOWO-01-09.md`
- `audyt/plamy/E5-RAPORT.md`
- `audyt/sklep/material/03-zalozenia.md`
- `audyt/sklep/material/07-mapa-dokumentacji.md`
- `narzedzia-qa/CHANGELOG.md`
- `narzedzia-qa/zdarzenia-webhooka.sh`

Produkt: `wtyczki/` + paczki w Releases. Dokumentacja klienta: `dla-klienta/`, `dokumentacja/`.
Dowody jakości: `audyt/`, `testy/`, `narzedzia-qa/`, `AUDYT-RAPORT.md`, `DECYZJE.md`.
