# PLAN NAPRAW — ChatBot LIVE · wersja 2 (po krytykach) · cel: 1.2.2

**20.08.2026, noc.** Wersja 1 powstała PRZED krytykami. Ta jest po: **4 zewnętrzne modele**
(oceny planu: 45, 55, 72/100 — wszystkie rozbroiły przynętę) + **nasz krytyk z dostępem do kodu**
+ **żywy test na kluczu Anthropic**, który wywrócił priorytety.

## KONTEKST (czego krytycy zewnętrzni nie wiedzieli)
Praca egzaminacyjna, termin oddania **27.08**. Nazwę i zakres podał zamawiający — nie zmieniamy.
Świadome granice (jego decyzja): bez czatu z operatorem, bez pamięci między wizytami, bez czytania
strony, bez Messengera/WhatsAppa. Bot działa w 100% bez klucza AI.

---

## 🔴 P0 — MUSI, inaczej oddajemy produkt z martwą obietnicą

| # | Co | Dowód, że to realne |
|---|---|---|
| **P0.1** | **TRYB AI NIE DZIAŁA — napraw.** `includes/ai.php:133` wysyła `output_config → effort`, którego model `claude-haiku-4-5` nie obsługuje. API zwraca 400, bot cicho zjeżdża do reguł i UDAJE, że działa. **Ta sama linia w Segregatorze — to KLASA.** | Wywołanie funkcji produktu na stacku: `HTTP 400, "This model does not support the effort parameter"`. **Próba kontrolna:** to samo zapytanie bez tego pola = HTTP 200. Zapisane w `audyt/boty/DOWODY-DEKLARACJI.md` |
| **P0.2** | **Fałszywy sukces przy zapisie zgłoszenia.** `includes/front.php:210-233` — `$wpdb->insert()` bez sprawdzenia wyniku, a odpowiedź „Dziękujemy" leci bezwarunkowo. Lead przepada po cichu. `wp_mail()` (223-230) tak samo. **KLASA — w Segregatorze 6 takich miejsc.** | nasz krytyk, plik:linia; potwierdzone lekturą |
| **P0.3** | **Brak zgody i informacji o prywatności** tam, gdzie gość zostawia e-mail (`widget.js:70-115` — zero pola zgody; `front.php:183-236` — zero walidacji). Checkbox WYMAGANY + co zbieramy, po co, na jak długo + link do polityki z ustawień. **Walidacja także po stronie serwera** — trzy krytyki wprost: sam checkbox we frontendzie to teatr. | 3/4 krytyków + potwierdzone w kodzie |

## 🟠 P1 — użyteczność i dowód, że deklaracje są prawdziwe

| # | Co | Skąd |
|---|---|---|
| **P1.1** | **Formularz kontaktu bez wyjścia** — `widget.js:122-126` zdejmuje klasę tylko z okna czatu; formularz zamyka się WYŁĄCZNIE po udanej wysyłce (linia ~100) i wraca otwarty po ponownym otwarciu czatu. Dodaj przycisk zamknięcia + Escape + reset. | znalezisko Dzidka z klikania, potwierdzone w kodzie |
| **P1.2** | **WERYFIKACJA DEKLARACJI — wykonaj i zapisz surowe wyjście.** Trzy krytyki niezależnie: „deklarujecie rzeczy, których nikt nie sprawdził". Do udowodnienia: czysta deinstalacja (diff tabel/opcji/cronów przed-po), kasowanie historii po 90 dniach (podłóż wpis sprzed 91 dni, odpal cron, policz przed/po), zjazd do reguł przy złym kluczu, blokada po przekroczeniu limitu dziennego. | 3/4 krytyków |
| **P1.3** | **Test bota w panelu** — pole „wpisz pytanie" pokazujące odpowiedź ORAZ która para zadziałała. Dwie krytyki nazwały to najlepszą pozycją planu: pokazuje egzaminatorowi, że algorytm nie jest czarną skrzynką. | 3/4 krytyków |
| **P1.4** | **Limit AI mierzy zły wymiar.** `front.php:13` — wspólny kubeł 10/min per IP dla pytań i zgłoszeń; dzienny limit AI (`chatbot-live.php:79`, 100) jest GLOBALNY. Jeden gość pytający 10×/min przez 10 minut wypala budżet AI wszystkich. Dodaj osobny, niższy limit wywołań AI per IP na dobę. | nasz krytyk |

## 🟡 P2 — jeśli starczy nocy

| # | Co | Uwaga |
|---|---|---|
| **P2.1** | **Eksport rozmów i zgłoszeń do CSV — TYLKO RĘCZNY**, przyciskiem w panelu. ⛔ NIE automatyczny przed kasowaniem: trzy krytyki wskazały, że zrzut do pliku przed usunięciem znaczy, że dane NIE są kasowane, tylko archiwizowane. ⛔ Zabezpiecz przed wstrzyknięciem formuły (pole zaczynające się od `=`, `+`, `-`, `@`). | 3/4 krytyków |
| **P2.2** | **Klucz API także ze stałej `CHATL_KLUCZ_API` w `wp-config.php`** (pierwszeństwo nad opcją). ⛔ Nie szyfrujemy klucza kluczem leżącym obok w tej samej bazie — to teatr; klucz poza bazą to realna poprawa. | 3/4 krytyków chciało szyfrowania — świadome odstępstwo z uzasadnieniem |
| **P2.3** | Warianty odporne na literówki i brak ogonków w bazie startowej + domyślny próg `$prog = 10`. | drobne |

## ⛔ WYPADA Z PLANU
- **Drugi dostawca AI (Gemini)** — **7 głosów przeciw na 7** (4 krytyków chatbota + 3 segregatora).
  Argument zbieżny: nowa integracja tydzień przed terminem to ryzyko regresji bez zysku dla oceny.
  Klucz Gemini zabezpieczony, wchodzi jako rozbudowa PO egzaminie. **Problem, który miał rozwiązać
  (nieprzetestowany tryb AI), rozwiązał klucz Anthropic — i przy okazji wykrył P0.1.**

## ✅ OBALONE — NIE naprawiać (krytycy strzelali bez dostępu do kodu)
- „Brak rate limitingu / antyspamu" — **JEST**, atomowy, `front.php:40-56`.
- „Stored XSS w treściach bota" — **NIE MA**: widget wstawia treści wyłącznie przez `textContent`,
  zero `innerHTML` (`widget.js:3,34`).
- „Klucz API w bazie jawnym tekstem" — częściowo: jest bez autoload i nie wraca do formularza;
  poprawa = stała w konfiguracji (P2.2), nie szyfrowanie.

## 📄 PO ZAMROŻENIU KODU
Dokumentacja techniczna (schemat 5 tabel, zaczepy, wymagania) + diagram przepływu.
⚠️ Dwie krytyki chciały dokumentacji równolegle. Zostaje po zamrożeniu — bo nasza najdroższa lekcja
z poprzedniego projektu to dokumentacja rozjechana z kodem (~30 znalezisk). Ale MUSI powstać
tej samej nocy, nie „kiedyś".

## ⛔ CZEGO NIE ROBIMY (droga rozbudowy w instrukcji)
Czat z operatorem · Messenger/WhatsApp/Instagram · pamięć rozmów · czytanie treści strony ·
aplikacja mobilna · integracja z kalendarzem · analityka konwersji.
