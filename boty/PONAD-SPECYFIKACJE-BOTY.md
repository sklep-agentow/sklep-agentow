# PONAD SPECYFIKACJĘ — boty (jawna deklaracja, ściąga egzaminatora klasa #6)

Notatka klienta mówi: „ChatBot LIVE" i „Segregator maila" (testowy). Poniżej
wszystko, co wtyczki robią PONAD literę notatki — z powodem. Każda pozycja
była w klepniętych szkicach (`boty/SZKICE-BOTOW.md`) albo wynika z ich zasad
(standard dziedziny, bezpieczeństwo, decyzja HYBRYDA z 18.08).

## ChatBot LIVE
1. **Tryb AI (hybryda)** — decyzja Dzidka 18.08 (szkice §BOT 1 TRYB AI).
2. **Historia rozmów w adminie, retencja 90 dni** — delta zwiadu wzorców
   (standard Chatwoot); retencja jak w sklepie.
3. **Stały przycisk „zostaw wiadomość"** — delta zwiadu (wzorzec Crisp).
4. **Rate limit 10/min per IP** na endpointach gościa — recenzja PR #29
   (ochrona przed zalewaniem bazy i mail-bombą); niewidoczny dla zwykłego gościa.
5. **Seed 10 par Q&A** z wariantami-rdzeniami — szkice (bot działa od
   pierwszej minuty).
6. **Stop-loss AI** (dzienny limit + licznik + zjazd do reguł) — szkice.

## Segregator maila
1. **Tryb AI + „porównaj reguły vs AI"** — decyzja Dzidka 18.08 (szkice §BOT 2).
2. **ACCURACY + lista pomyłek** przy próbce z etykietami — delta zwiadu
   (konwencja raportów klasyfikacji).
3. **Kubeł domyślny „Reszta"** dla maili bez reguły — wzorzec Gmail
   (mail zostaje w skrzynce głównej); dzięki temu % pokrycia jest uczciwy.
4. **Neutralizacja formuł w eksporcie CSV** (komórki od `=`,`+`,`-`,`@`) —
   OWASP CSV injection; wymóg „sanitize wejść/escape wyjść" ze szkiców.
5. **Historia przebiegów** (raporty zapisane, nie jednorazowe) — sekcja ADMIN
   szkiców („przebiegi (historia raportów)").
6. **Limit importu 500 wierszy** — ochrona RAM/czasu na współdzielonym hostingu.

## Czego CELOWO nie ma (granice jawne, też ze szkiców)
- ChatBot: bez czatu operatora na żywo, bez pamięci rozmów między wizytami.
- Segregator: bez podpięcia żywej skrzynki (IMAP po egzaminie — opisane
  w instrukcji jako droga rozbudowy).
- Oba: automatyczny stemming polskich odmian = droga rozbudowy (recenzja
  PR #27: bez cichego poszerzania wyceny).
