# BRIEF BUILDERA — noc 20.08, naprawy botów przed audytem

Jesteś builderem. Pracujesz TYLKO w `/root/praca/zlecenia/sklep-agentow`, gałąź `boty/runda-finalna`.
Koordynator (Gosia) siedzi w innym czacie i pilnuje całości — melduj się przez commity i PR.

## ⛔ ZASADY (łamiesz → robota do wyrzucenia)
1. **Nie zgadujesz.** Nie wiesz → sprawdź w kodzie/dokumentacji, nie wymyślaj.
2. **Każda naprawa = DOWÓD**: surowe wyjście komendy (phpcs, php -l, test na żywo). W commicie WKLEJ wyjście.
3. **Naprawiaj KLASAMI**: znalazłeś wzorzec → grep po OBU botach → napraw wszystkie wystąpienia w jednym przejściu.
4. **Nie ruszaj zakresu**: nie dodajesz funkcji spoza tej listy. Nie zmieniasz nazw produktów.
5. **Po każdej porcji**: `vendor/bin/phpcs -q wtyczki/` + `vendor/bin/phpstan analyse --memory-limit=1G` muszą być czyste.
6. **Deploy na stack**: `docker cp wtyczki/<nazwa>/. strona_wp_wordpress:/var/www/html/wp-content/plugins/<nazwa>/` + `chown -R www-data:www-data`.
7. **Bump wersji**: oba boty idą do **1.2.2** (nagłówek wtyczki + stała wersji). Dopiero na SAMYM KOŃCU, jednym commitem.
8. ⛔ **Nie dotykaj** katalogu `wtyczki/sklep-agentow/` — to inny produkt, nie w tej rundzie.

## STAN ZABEZPIECZENIA (już zrobione przez koordynatora)
Zrzut 10 tabel botów + kopie paczek 1.2.1 + **odcisk wartości** (jak boty zachowują się PRZED):
`/tmp/claude-0/-root-praca-warsztat/b069c330-5e27-4c17-9cce-36e4ccc88624/scratchpad/boty-baseline/`
Po naprawach porównamy — zachowanie POZA naprawianymi miejscami ma być IDENTYCZNE.

---

# ZADANIA — w tej kolejności

## 🔴 Z1. KLASA: zapis do bazy bez sprawdzenia wyniku (OBA BOTY)
Kod woła `$wpdb->insert()` / `$wpdb->update()` i **zwraca sukces bezwarunkowo**. Jeśli zapis padnie,
użytkownik widzi „Dziękujemy", a danych nie ma. To cichy ubytek danych klienta.
**Potwierdzone miejsca (sprawdź i znajdź resztę grepem po obu botach):**
- `chatbot-live/includes/front.php:210-220` — zapis zgłoszenia; linia ~233 zwraca `wp_send_json_success` bezwarunkowo
- `chatbot-live/includes/front.php:223-230` — `wp_mail()` bez sprawdzenia wyniku
- `segregator-maila/includes/silnik.php:151-163`, `csv.php:118`, `instalator.php:99,124,186`, `admin.php:433,573`
**Jak naprawić:** sprawdzaj wynik; przy porażce → komunikat błędu dla użytkownika (nie „sukces") + wpis do logu WP.
Przy zgłoszeniu z czatu: gość MUSI zobaczyć, że się nie udało, zamiast fałszywego podziękowania.
**Dowód:** wymuś porażkę zapisu (np. tymczasowo zła nazwa tabeli w kopii kodu albo mock) i pokaż, że komunikat jest błędem, nie sukcesem.

## 🔴 Z2. ChatBot: zgoda i informacja o prywatności w formularzu kontaktu
Dziś gość zostawia e-mail i pytanie **nie widząc żadnej informacji** (`widget.js:70-115`, brak jakiegokolwiek pola zgody; `front.php:183-236` nic nie waliduje).
**Zrób:** checkbox „Zapoznałem się z informacją o przetwarzaniu danych" (WYMAGANY) + krótkie zdanie:
co zbieramy (e-mail, treść pytania), po co (odpowiedź na pytanie), jak długo (90 dni) + link do strony
polityki prywatności. **Adres polityki = nowe pole w ustawieniach bota** (puste = link się nie pokazuje).
Walidacja PO STRONIE SERWERA też (`front.php`) — brak zgody = odrzucenie z komunikatem.

## 🔴 Z3. Segregator: RETENCJA wgranych maili
Zero crona, zero czyszczenia — prawdziwa korespondencja zostaje w bazie na zawsze (grep `cron|wp_schedule` = 0 trafień).
**Zrób:** codzienny cron kasujący próbki i przebiegi starsze niż **90 dni** (wzoruj się 1:1 na `chatbot-live.php:45-56`)
+ przycisk „Wyczyść próbkę teraz" w panelu (nonce + capability) + zdanie w `INSTRUKCJA.md`.
Cron rejestruj przy aktywacji, wyrejestruj przy deaktywacji, wpis do `uninstall.php`.

## 🟠 Z4. ChatBot: zamykanie formularza kontaktu
`widget.js:122-126` — przycisk × zdejmuje klasę tylko z okna czatu, NIE z formularza; jedyne zamknięcie
to udane wysłanie (linia ~100). Efekt: gość, który zmienił zdanie, ma formularz na stałe, także po
zamknięciu i ponownym otwarciu czatu.
**Zrób:** przycisk zamknięcia na formularzu + obsługa klawisza Escape + reset stanu przy zamykaniu okna czatu.

## 🟠 Z5. Segregator: kodowanie plików CSV i twardy limit
- **Kodowanie:** dziś zdejmowany jest tylko znacznik UTF-8 (`csv.php:52,66`), reszta zakłada UTF-8.
  CSV z Excela (Windows-1250) wchodzi jako krzaki na polskich znakach. **Wykryj kodowanie i przekonwertuj do UTF-8.**
- **Limit:** jest limit 500 wierszy i 2000 znaków na pole, ale pętla `fgetcsv` (csv.php:60) czyta plik
  DO KOŃCA nawet po przekroczeniu limitu (liczy tylko pominięte). **Przerwij czytanie po przekroczeniu limitu**
  + dodaj limit rozmiaru pliku w bajtach z czytelnym komunikatem.

## 🟠 Z6. Segregator: wyścig przy imporcie i przebiegu
`TRUNCATE TABLE segm_maile` (csv.php:116, admin.php:683) leci bez blokady, a `segm_wykonaj_przebieg`
(silnik.php:76) w tym czasie czyta tę tabelę. Dwie karty naraz = przebieg policzony na pustej próbce, bez ostrzeżenia.
**Zrób:** najprostsza poprawna ochrona (transakcja albo znacznik „import w toku" sprawdzany przed startem przebiegu)
+ komunikat dla użytkownika zamiast cichego złego wyniku.

## 🟡 Z7. ChatBot: limit AI mierzy zły wymiar
`front.php:13` `CHATL_LIMIT_NA_MIN=10` to wspólny kubeł per IP dla pytań i zgłoszeń; dzienny limit AI
(`chatbot-live.php:79`, domyślnie 100) jest **globalny**. Jeden gość pytający 10×/min przez 10 minut
wypala CAŁY dzienny budżet AI dla wszystkich gości.
**Zrób:** osobny, niższy limit wywołań AI per adres IP na dobę (nowa stała + sprawdzenie przed wywołaniem API).
⚠️ Zachowaj obecne zachowanie trybu regułowego — on ma działać bez zmian.

## 🟡 Z8. ChatBot: test bota w panelu
Brak możliwości sprawdzenia własnej bazy bez wychodzenia na stronę (przeczytane całe `admin.php`, 409 linii).
**Zrób:** na stronie bazy Q&A pole „wpisz pytanie" → pokazuje odpowiedź bota ORAZ która para zadziałała (id + pytanie wzorcowe).

## 🟡 Z9. ChatBot: eksport rozmów i zgłoszeń do CSV — ⚠️ TYLKO RĘCZNY
Grep `csv|eksport` w całym bocie = 0 trafień. Historia znika po 90 dniach bez możliwości zachowania.
**Zrób:** przyciski „Pobierz CSV" na liście zgłoszeń i na historii rozmów (nonce + capability,
nagłówki jak w segregatorze `csv.php:147`).
⛔ **NIE rób automatycznego eksportu przed kasowaniem** — 3 niezależne krytyki wskazały, że
automatyczny zrzut do pliku przed usunięciem oznacza, że dane NIE są kasowane, tylko archiwizowane
(sprzeczne z obietnicą „kasujemy po 90 dniach" i z minimalizacją danych).
⛔ **Zabezpiecz przed wstrzyknięciem formuły do arkusza**: pole zaczynające się od `=`, `+`, `-`, `@`
poprzedź apostrofem — inaczej Excel potraktuje treść od gościa jako formułę.

## 🟡 Z11. ChatBot: klucz API również ze stałej w konfiguracji
Dziś klucz czytany WYŁĄCZNIE z bazy (`ai.php:25-26`). Trzy krytyki wytknęły „klucz w bazie jawnym
tekstem". ⛔ Szyfrowanie kluczem leżącym obok w tym samym WordPressie to teatr — nie robimy tego.
**Zrób:** jeśli zdefiniowana jest stała `CHATL_KLUCZ_API` w `wp-config.php`, MA PIERWSZEŃSTWO nad opcją
(w panelu wtedy komunikat „klucz podany w konfiguracji serwera", pole zablokowane). To realna dobra
praktyka: klucz poza bazą, poza kopiami zapasowymi bazy. Opisz w INSTRUKCJA.md.

## 🔴 Z12. WERYFIKACJA DEKLARACJI (nie kod — DOWÓD, że obietnice są prawdziwe)
Trzy krytyki niezależnie wskazały: produkt deklaruje rzeczy, których nikt nie sprawdził. **Wykonaj
i zapisz surowe wyjście** w `audyt/boty/DOWODY-DEKLARACJI.md`:
1. **Czysta deinstalacja OBU botów**: policz tabele/opcje/crony PRZED instalacją, po instalacji
   i po odinstalowaniu z włączoną opcją czyszczenia. Diff musi być pusty. (`wp option list`, `SHOW TABLES`, `wp cron event list`)
2. **Cron retencji 90 dni**: sprawdź, że zdarzenie jest zarejestrowane po aktywacji, wyrejestrowane
   po deaktywacji, i że faktycznie kasuje — podłóż wpis z datą sprzed 91 dni, odpal zdarzenie ręcznie
   (`wp cron event run`), pokaż liczbę wierszy przed i po.
3. **Zjazd AI do reguł**: ustaw zły klucz API i pokaż, że bot odpowiada z bazy zamiast się wysypać.
4. **Limit dzienny AI**: pokaż, że po przekroczeniu licznik blokuje wywołania.

## 🟢 Z10. Drobiazgi
- `chatbot-live`: domyślny próg `$prog = 10` w `chatl_rate_limit` (dziś bez wartości domyślnej).
- `chatbot-live`: w seedzie demo dopisz warianty odporne na literówki i brak polskich znaków
  (np. „cenik", „godziny otwarcia", „otwarci") — silnik dopasowuje podłańcuchy, więc warianty to jedyna obrona.

---

# ⛔ CZEGO NIE ROBISZ W TEJ RUNDZIE
- Drugi dostawca AI (Gemini) — koordynator zdecyduje, czy wchodzi, PO tych naprawach.
- Eksport filtrów do Gmaila/Outlooka — wymaga rozstrzygnięcia (Gmail nie ma „przestań sprawdzać dalej",
  więc eksport 1:1 zmieniłby zachowanie). Koordynator sprawdza to osobno.
- Zmiany nazw produktów, IMAP, czat z operatorem, Messenger/WhatsApp.
- Dokumentacja techniczna i diagramy — DOPIERO po zamrożeniu kodu.

# KIEDY KOŃCZYSZ
Po Z1–Z10: phpcs + phpstan czyste, boty wdrożone na stack, **odcisk wartości porównany**
(zachowanie poza naprawionymi miejscami identyczne), bump do 1.2.2, PR z surowymi dowodami w opisie.
