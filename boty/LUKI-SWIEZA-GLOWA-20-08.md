# LUKI ZNALEZIONE ŚWIEŻĄ GŁOWĄ — 20.08, czytanie kodu przez koordynatora

Źródło: przelot koordynatora po kodzie obu botów (chatbot: `front.php`, `silnik.php`,
`ai.php`, `widget.js`; segregator: `silnik.php`, `ai.php`, `filtry.php`).
⛔ To NIE jest audyt — panele admina, statystyki, import CSV i instalatory NIE były
czytane. Audyt właściwy (mapa 34 celów) rusza po zamrożeniu.

Każda pozycja ma: dowód (plik:linia), skutek u klienta, i co ma powstać.
⛔ Zasada wspólna: każda naprawa dostaje **test-strażnika z exit 1**, inaczej
wróci (pamięć: „informacja dotarła 3× i nic nie zatrzymała").

---

## BUILDER1 — chatbot-live

### L1 🔴 Nonce w cache'owanej stronie = czat pada u WSZYSTKICH gości, po cichu
**Dowód:** `includes/front.php:130` — nonce wchodzi w HTML strony przez
`wp_localize_script`. `assets/widget.js:57` — odpowiedź czytana przez `odp.json()`.
**Skutek:** strona z cache (LiteSpeed, WP Rocket, W3TC, Cloudflare) serwuje ten sam
HTML dłużej, niż żyje nonce (12–24 h). Wtedy `check_ajax_referer` kończy się `-1`
i HTTP 403; `JSON.parse("-1")` daje liczbę, więc `odp.success` jest `undefined`
i gość dostaje „Coś poszło nie tak. Spróbuj ponownie." przy KAŻDYM pytaniu.
Czat nie działa u nikogo, właściciel nie ma o tym pojęcia.
**Co ma powstać:**
1. publiczny endpoint AJAX „daj świeży nonce" (bez nonce'a, z limitem żądań),
2. widget przy odpowiedzi bez `success` **jeden raz** dociąga świeży nonce
   i ponawia żądanie; dopiero drugie niepowodzenie pokazuje komunikat,
3. znacznik w panelu jak przy nieudanym mailu (wzorzec własny:
   `front.php:321` `chatl_odnotuj_wysylke_powiadomienia`) — właściciel MUSI
   zobaczyć, że gościom odbija się nonce,
4. test-strażnik: żądanie z wygasłym/obcym noncem → widget kończy rozmową, nie błędem.

### L2 🔴 Limit „na IP" mierzy IP proxy — za Cloudflare cała strona ma JEDEN kubeł
**Dowód:** `includes/front.php:48` — wyłącznie `REMOTE_ADDR`.
**Skutek:** strona za Cloudflare albo za proxy hostingu widzi jeden adres dla
wszystkich gości: 10 wiadomości na minutę **na całą witrynę**, potem 429 dla
każdego. Dzienny limit AI (20/IP, `front.php:26`) staje się 20 na całą stronę.
**Co ma powstać:** funkcja `chatl_ip_goscia()` z filtrem + przełącznik w ustawieniach
„strona stoi za Cloudflare/proxy" (domyślnie WYŁĄCZONY — nagłówkowi ufamy tylko na
wyraźną decyzję właściciela, bo `X-Forwarded-For` da się podrobić). Kolejność:
`CF-Connecting-IP`, potem pierwszy adres z `X-Forwarded-For`, potem `REMOTE_ADDR`.
Jedno zdanie w instrukcji: kiedy to włączyć.

### L3 🟠 Stop-loss liczy WYWOŁANIA, a rachunek idzie z TOKENÓW
**Dowód:** `includes/ai.php:113` `chatl_ai_instrukcja()` wkłada CAŁĄ aktywną bazę Q&A
+ informacje o firmie do instrukcji systemowej przy KAŻDYM pytaniu.
`ai.php:96` limit dzienny porównuje liczbę wywołań.
**Skutek:** klient z 200 parami Q&A płaci wielokrotność klienta z 10 parami przy
identycznym „limicie 100 wywołań/dobę". W panelu nie ma ani jednej liczby o koszcie.
**Co ma powstać:** zapis `usage` z odpowiedzi API do tabeli użycia (tokeny wejścia
i wyjścia osobno), pokazanie ich w Statystykach, limit dzienny **także na tokenach**.
⚠️ Zanim ruszysz: czekamy na destylat z dokumentacji API (cache instrukcji
systemowej + nazwy pól `usage`) — dopisze go koordynator do tego pliku.

### L4 🟠 Zły albo wygasły klucz = bot po cichu odpowiada z reguł, w nieskończoność
**Dowód:** `includes/ai.php:214` — błąd leci przez `wp_trigger_error` do `debug.log`,
który na produkcji jest wyłączony. Grep na „przetestuj/test_klucz/blad_ai": zero trafień.
**Skutek:** właściciel płaci za tryb AI, którego nie ma, i nie ma jak tego zauważyć.
**Co ma powstać:** opcja `chatl_blad_ai` (kiedy, kod HTTP, skrót treści) zapisywana
przy każdym nieudanym wywołaniu i kasowana przy pierwszym udanym + czerwony pasek
w panelu + przycisk „Przetestuj klucz" w ustawieniach (jedno wywołanie, wynik na ekranie).
Wzorzec 1:1 z `chatl_odnotuj_wysylke_powiadomienia()`.

### L5 🟠 Model zaszyty na sztywno = bomba zegarowa u każdego kupującego
**Dowód:** `includes/ai.php:18` `CHATL_AI_MODEL = 'claude-haiku-4-5-20251001'`.
**Skutek:** filtr istnieje, ale kupujący wtyczkę nie pisze filtrów. Gdy model zostanie
wycofany, każda sprzedana kopia traci tryb AI, a jedyna droga naprawy to ręczna
wysyłka zipa mailem (nie mamy serwera aktualizacji).
**Co ma powstać:** wybór modelu w ustawieniach (lista + pole własne), domyślnie
dzisiejszy; w instrukcji zdanie, gdzie sprawdzić aktualną nazwę modelu.

### L7 🟡 Rozmowy zapisujemy 90 dni, gość dowiaduje się dopiero przy formularzu
**Dowód:** `includes/front.php:132` — notka o przetwarzaniu leci tylko do formularza
kontaktu; samo pisanie na czacie już ląduje w `chatl_rozmowy` (`silnik.php:118`).
**Skutek:** sprzedajemy wielu firmom, każda staje się administratorem danych.
**Co ma powstać:** jedno zdanie na starcie rozmowy („Rozmowa jest zapisywana przez
90 dni, żebyśmy mogli wrócić z odpowiedzią") + link do polityki, gdy adres podany.

---

## BUILDER2 — segregator-maila

### L4b 🟠 To samo co L4: brak sygnału o złym kluczu
**Dowód:** `includes/ai.php` — zjazd do reguł jest cichy; brak testu klucza.
**Co ma powstać:** `segm_blad_ai` + pasek w panelu + „Przetestuj klucz". Wzorzec z L4.

### L6 🟡 Klucz API tylko w bazie — chatbot pozwala schować go w `wp-config.php`
**Dowód:** `segregator/includes/ai.php:37` (sama opcja) kontra
`chatbot/includes/ai.php:32` (`CHATL_KLUCZ_API` ma pierwszeństwo nad opcją).
**Skutek:** ten sam kupujący, dwie różne klasy ochrony sekretu; klucz segregatora
wychodzi z każdą kopią zapasową bazy.
**Co ma powstać:** `SEGM_KLUCZ_API` na wzór chatbota — stała wygrywa, panel jej nie
nadpisuje, w polu widać informację „klucz jest w konfiguracji serwera".

### L8 ⛔ POPRAWIONE 20.08 — pierwotna wersja tej pozycji BYŁA BŁĘDNA
**Co napisałam źle:** że Segregator nie dotyka prawdziwej skrzynki i jest
analizatorem próbek CSV. **Nieprawda.** Sprawdziłam spis plików modułu w drzewie
buildera stojącym na starej gałęzi, zamiast w `origin/main` — dokładnie ten błąd,
który mam u siebie zapisany jako wpadka nr 5.

**Jak jest naprawdę** (`origin/main`, `wtyczki/segregator-maila/includes/`):
`class-segm-imap.php`, `admin-skrzynka.php`, `skrzynka.php`, `skrzynka-przebieg.php`
— jest **pełny przebieg na żywej skrzynce IMAP**: pobranie wiadomości, decyzja wg
reguł, **przenoszenie do folderów na serwerze poczty**, historia przebiegów,
cofnięcie ostatniego przebiegu i zadanie cykliczne WP-Cron. Tryb NA SUCHO jest
domyślny i otwiera folder przez EXAMINE (RFC 3501), czyli read-only na poziomie
protokołu; realne przenoszenie wymaga przełączenia trybu ORAZ osobnego
potwierdzenia. Do testów stoi kontener GreenMail. To jest solidna, produkcyjna
robota i produkt stoi znacznie wyżej, niż napisałam.

**Co z pierwotnego zarzutu ZOSTAJE, w węższej postaci:** w przebiegu na skrzynce
(`skrzynka-przebieg.php`) klasyfikuje **wyłącznie silnik regułowy** — grep na
`segm_ai` w tym pliku daje zero. Tryb AI żyje tylko w przebiegu po próbce CSV.
Czyli klient, który kupuje bota dla trybu AI, dostaje AI wyłącznie w laboratorium,
a na jego skrzynce pracują same reguły.
**Do decyzji Dzidka (⛔ nie zaczynać bez jego słowa):** albo (a) wpiąć tryb AI
w przebieg na skrzynce z twardym limitem liczby wiadomości na przebieg i widocznym
kosztem, albo (b) zostawić i napisać to WPROST na stronie produktu i w instrukcji,
żeby nikt nie kupił AI, którego na skrzynce nie ma.

---

## KOORDYNATOR (nie builder)

### L9 🔵 Nasze dokumenty obiecują granice, których właśnie NIE stawiamy
`boty/SZKICE-BOTOW.md` (§GRANICE), `boty/PONAD-SPECYFIKACJE-BOTY.md`
(„Czego celowo nie ma"), `boty/TRESC-STRON-PRODUKTOW.md` („LIVE znaczy: żywy
człowiek odpisze") mówią: bez czatu operatora na żywo, bez pamięci rozmów.
Builder1 robi w tej chwili jedno i drugie. Strony produktów w sklepie są już
zbudowane tą treścią (K5).
**Do zrobienia PO scaleniu tych PR-ów:** sweep po starym brzmieniu w czterech
miejscach — szkice, ponad-specyfikacja, treść stron produktów, opisy produktów
w bazie sklepu. (Pamięć: `zmiana-napisu-uniewaznia-materialy`.)

---

## DESTYLAT Z DOKUMENTACJI API — odblokowuje L3 i doprecyzowuje L5
Pełny: `boty/destylat-api-anthropic.md` (20.08, ze ścieżkami dowodów).

**Do L3 (tokeny i koszt):**
- `usage` w odpowiedzi ma cztery pola: `input_tokens`, `output_tokens`,
  `cache_creation_input_tokens`, `cache_read_input_tokens`. To są nazwy do zapisu
  w tabeli użycia i do limitu dziennego liczonego tokenami.
- Cache instrukcji systemowej: `system` musi być TABLICĄ bloków, a na bloku
  `cache_control: {"type":"ephemeral"}`. Bez dodatkowego nagłówka beta.
- ⛔ **PRÓG, KTÓRY ZMIENIA PLAN:** dla `claude-haiku-4-5` cache włącza się dopiero
  od **4096 tokenów** instrukcji. Krótsza instrukcja = cache po cichu NIE działa,
  bez żadnego błędu. Typowa baza Q&A małej firmy jest krótsza, więc **cache nie jest
  ratunkiem na koszt** — ratunkiem jest limit liczony tokenami i pokazanie zużycia.
  Cache dokładamy warunkowo (gdy instrukcja przekracza próg), nie na ślepo.

**Do L5 (model):**
- Istnieje alias bez daty: `claude-haiku-4-5` = `claude-haiku-4-5-20251001`, ten sam
  model, status Active (sprawdzone 20.08).
- Modele wycofuje się w cyklu Active → Deprecated (z datą) → Retired; datowany
  identyfikator **kiedyś przestaje działać** (przykład z dokumentacji:
  `claude-3-5-sonnet-20241022` wycofany 28.10.2025).
- **Zalecenie:** domyślnie w kodzie **alias bez daty** (przeżyje wydania punktowe),
  plus pole w ustawieniach do nadpisania. Kompromis jawny: alias może po cichu
  zmienić zachowanie modelu, datowany po cichu umiera — przy braku serwera
  aktualizacji mniej boli to pierwsze, bo bot dalej odpowiada.
