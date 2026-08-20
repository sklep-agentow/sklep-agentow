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
7. **Indeks `KEY okno_do` na tabeli `chatl_rate_limit`** — świadome rozszerzenie
   ponad wzorzec sklepu (tam go nie ma): przyspiesza cronowe sprzątanie
   `DELETE … WHERE okno_do < teraz`. Nazwane tu na wniosek recenzji PR #40.
8. **Statystyki i raport luk** (osobna strona panelu: ile pytań przyszło, na ile
   bot odpowiedział i jaki to procent, ile skończyło się zbiórką kontaktu, oraz
   lista pytań bez odpowiedzi pogrupowana w tematy ze skrótem „dopisz
   odpowiedź") — rozszerzenie ponad szkice, dodane 20.08 przy podniesieniu progu
   z „demo egzaminacyjne" na „produkt na sprzedaż". **Powód:** właściciel nie ma
   skąd wiedzieć, czego dopisać do bazy Q&A, a to jedyna czynność, która realnie
   podnosi skuteczność bota — bez tego produkt jest statyczny. Kosztowało nową
   kolumnę `chatl_rozmowy.trafiono` i indeks `KEY luki` (schemat bazy 4).
   Nazwane tu na wniosek recenzji PR #73.
9. **Konfigurowalny wygląd widgetu** (kolor główny, kolor paska nagłówka, kolor
   tekstu na nich, róg strony) wraz z **podglądem na żywo i kontrolą kontrastu
   WCAG 2.1 AA** w panelu — rozszerzenie ponad szkice, dodane 20.08.
   **Powód:** kolory były zaszyte na sztywno, więc klient z niebieską albo
   pomarańczową witryną dostawał limonkowy bąbelek z naszej palety. Przy
   sprzedaży wielu klientom to pierwsze pytanie, a u konkurencji (Tidio,
   Chatwoot) konfigurowalny wygląd jest standardem, nie dodatkiem. Kontrast
   dołożony, bo dowolny kolor to prosta droga do nieczytelnego tekstu —
   a to zarzut, który audytor dostępności stawia natychmiast. Domyślne wartości
   są identyczne z dotychczasowymi, więc nikomu nic się nie zmienia.
10. **Ekran powitalny po aktywacji + komplet dokumentów produktowych**
   (`CHANGELOG.md`, `readme.txt` w standardzie WordPressa) — rozszerzenie ponad
   szkice, dodane 20.08. **Powód:** boty idą na sprzedaż jako normalne produkty
   rynkowe, a kupujący płatną wtyczkę oczekuje, że po kliknięciu „Włącz" coś się
   wydarzy i że w paczce znajdzie historię zmian oraz opis w formacie, który zna
   z katalogu WordPressa. Brak tych rzeczy nie psuje działania, ale od razu
   widać, że to nie jest produkt handlowy.

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
- Segregator: ⛔ **nieaktualne od 2026-08-20** — podpięcie żywej skrzynki (IMAP)
  POWSTAŁO i jest w produkcie. Zostały węższe granice: logowanie tylko hasłem
  aplikacji (bez OAuth ⇒ brak Microsoft 365 i Outlook.com), na skrzynce
  klasyfikują reguły a nie AI, bez czytania załączników.
- Oba: automatyczny stemming polskich odmian = droga rozbudowy (recenzja
  PR #27: bez cichego poszerzania wyceny).
