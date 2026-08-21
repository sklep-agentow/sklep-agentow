# 01 — ZAMÓWIENIE BOTÓW (przepisane ze źródła, NIE streszczone)

> ⛔ **To jest litera zamówienia.** Źródło: `boty/SZKICE-BOTOW.md` — szkice klepnięte przez
> zamawiającego 18.08 PRZED budową (bramka z planu v3 §9). Jeśli kod robi coś innego niż
> ten plik — to jest znalezisko. Jeśli kod robi WIĘCEJ — patrz `boty/PONAD-SPECYFIKACJE-BOTY.md`
> (rozszerzenie musi być tam jawnie zadeklarowane, inaczej to klasa #6 ściągi egzaminatora).

## Kontekst zamówienia
Praca egzaminacyjna (egzamin nr 2). Zamawiający = kolega Dzidka, który ocenia wynik.
Dwie wtyczki WordPressa sprzedawane jako produkty we własnym sklepie (trzecia wtyczka).
Nazwy produktów **dosłownie z notatki zamawiającego**: „ChatBot LIVE", „Segregator maila".
⛔ Nazw nie zmieniamy — zmiana nazwy = odejście od zamówienia.

Decyzja zamawiającego z 18.08: **HYBRYDA** — każdy bot ma DWA silniki: regułowy
(działa bez klucza, zawsze) + prawdziwe AI przez API (klucz właściciela).

---

## BOT 1 — ChatBot LIVE (`chatbot-live`)

**Co kupuje klient (jedno zdanie):** bot na stronę firmową, który 24/7 odpowiada na
powtarzalne pytania klientów, a gdy nie zna odpowiedzi — zbiera kontakt, żeby
właściciel odezwał się na żywo.

### FRONT (co widzi gość strony)
- pływający bąbelek czatu w rogu (włącz/wyłącz w ustawieniach; nie rusza motywu),
- okno czatu: powitanie (konfigurowalne) → gość pisze pytanie → bot odpowiada z bazy
  pytań-odpowiedzi. **v1: PROSTE dopasowanie podłańcuchów** po polu „słowa kluczowe
  z wariantami" — odmiany łapie się wpisując warianty („cena, ceny, cennik").
  ⛔ Automatyczny stemming polskich odmian = droga rozbudowy PO egzaminie,
  **nie jest brakiem** (recenzja PR #27: bez cichego poszerzania wyceny).
- brak dopasowania → uczciwe „nie znam odpowiedzi — zostaw maila i pytanie, odezwiemy
  się" → zapis zgłoszenia + (opcja) powiadomienie mailem do właściciela.
  **To jest człon „LIVE": most do żywego człowieka, nie udawanie wszechwiedzy.**
- STAŁY przycisk „zostaw wiadomość" widoczny od początku rozmowy (wzorzec Crisp:
  ścieżka do człowieka zawsze, nie tylko po porażce bota).

### ADMIN (wp-admin)
- CRUD bazy Q&A: pytanie wzorcowe, słowa kluczowe (z wariantami), odpowiedź,
- lista zgłoszeń z czatu (kto pytał, o co, mail — do obdzwonienia/odpisania),
- HISTORIA rozmów (zapis wymian bot↔gość, widok w adminie, **retencja 90 dni**),
- ustawienia: włącznik, teksty powitania/awaryjny, mail powiadomień,
- seed demo PL: ~10 par Q&A typowej firmy usługowej — **bot DZIAŁA od pierwszej minuty
  po aktywacji**.

### TRYB AI (hybryda)
Pole „klucz API" w ustawieniach — klucz WŁAŚCICIELA, **nigdy w paczce/kodzie/repo**.
Z kluczem odpowiada prawdziwy model z instrukcją systemową: „jesteś asystentem firmy X;
oto informacje o firmie [tekst z ustawień]; odpowiadasz TYLKO na ich podstawie, po polsku,
krótko; nie wiesz → poproś o maila". **PRÓG PEWNOŚCI: model ma ZAKAZ zgadywania** —
niepewny odmawia i zbiera kontakt, zamiast konfabulować o firmie.
Bez klucza — pełnoprawny tryb regułowy (**nie atrapa**).
**STOP-LOSS:** dzienny limit wywołań + licznik w adminie; błąd/timeout API → automatyczny
zjazd do trybu regułowego.

### GRANICE (jawne, klepnięte — NIE są brakami)
Bez pamięci rozmów między wizytami · bez czatu operatora na żywo w czasie rzeczywistym
(kontakt = zgłoszenie + mail, w OBU trybach).

### Zdanie uczciwości (idzie też na stronę produktu)
> to bot regułowy z bazy Q&A + most do maila właściciela — NIE żywa rozmowa z operatorem
> ani prawdziwe AI; „LIVE" znaczy: żywy człowiek ODPISZE na zebrany kontakt.

---

## BOT 2 — Segregator maila (`segregator-maila`)

**Co kupuje klient (jedno zdanie):** automat, który porządkuje skrzynkę firmową —
rozdziela maile do kategorii wg reguł i pokazuje raport, ile roboty zrobił za Ciebie.

### RDZEŃ
- reguły segregacji (CRUD): nadawca zawiera / temat zawiera / słowa kluczowe w treści
  → kategoria. **JEDEN mechanizm rozstrzygania: kolejność na liście — pierwsza pasująca
  wygrywa.** ⛔ Osobnego pola „priorytet" NIE MA (recenzja PR #27: dwa mechanizmy naraz
  = niejednoznaczność). To jest ŚWIADOMY wybór, nie brak.
- kategorie (CRUD): np. Oferty, Faktury, Reklamacje, Spam-podejrzane, Reszta,
- wbudowana PRÓBKA ~30 maili demo PL + **import własnej próbki z CSV** (kolumny:
  od, temat, treść),
- przebieg „przepuść próbkę" → tabela wyników (mail → kategoria → która reguła)
  + RAPORT: ile do której kategorii, ile niedopasowanych, % pokrycia; eksport CSV.
  Przy próbce z kolumną `kategoria_oczekiwana`: ACCURACY (% trafień) + lista pomyłek.

### ADMIN
reguły, kategorie, przebiegi (historia raportów), import próbki.

### TRYB AI (hybryda)
Z kluczem właściciela klasyfikację robi model („przypisz mail do JEDNEJ z kategorii
[lista]; niepewny → Reszta"). Tryb przebiegu do wyboru: **reguły / AI / porównaj oba**
(raport pokazuje różnice). Ten sam stop-loss i zjazd do reguł co w ChatBocie.

### GRANICE (jawne, klepnięte — NIE są brakami)
**Bez podpięcia prawdziwej skrzynki (IMAP/OAuth)** — po egzaminie, droga opisana
w instrukcji. Działa na próbkach CSV — dlatego uczciwie nazywa się „testowy bot".

---

## WSPÓLNE ZASADY OBU WTYCZEK
- własne prefiksy tabel/opcji (`chatl_`, `segm_`), tabele przez dbDelta + wersja schematu;
  **czysty uninstall** (domyślnie dane zostają, opcja pełnego czyszczenia),
- teksty PL wprost (D21), **zero zewnętrznych zależności/CDN**, WP 7.0 / PHP 8.4,
- bezpieczeństwo standardami WP: nonce+capability na każdej mutacji, `prepare()`,
  sanitize wejść / escape wyjść — **czat i import CSV = wejście NIEZAUFANE**,
- assety ładowane tylko tam, gdzie bot działa; **bump wersji przy każdej zmianie JS/CSS**,
- każda wtyczka: instrukcja użytkownika PL (instalacja → konfiguracja w kwadrans → FAQ →
  droga rozbudowy) — **zip wtyczki + instrukcja = paczka produktu w sklepie**,
- **JAWNA deklaracja prywatności** (RODO): tryb regułowy — treść NIE opuszcza WordPressa;
  tryb AI — treść pytania/maila idzie do API dostawcy. Informacja w instrukcji, na stronie
  produktu i jako punkt do polityki prywatności właściciela.
