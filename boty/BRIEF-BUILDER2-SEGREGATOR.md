# BRIEF BUILDERA 2 — TYLKO Segregator maila (noc 20.08)

Pracujesz w **osobnym drzewie roboczym** `/root/praca/zlecenia/sklep-agentow-boty`,
gałąź **`boty/segregator-naprawy`**. W drugim drzewie pracuje RÓWNOLEGLE builder 1 nad ChatBotem
— dlatego masz twarde granice, żeby nie wejść mu w pliki.

## ⛔ GRANICE (złamiesz → konflikt scalania i noc do wyrzucenia)
1. Dotykasz **WYŁĄCZNIE** `wtyczki/segregator-maila/`.
2. ⛔ **NIE DOTYKASZ `wtyczki/segregator-maila/includes/ai.php`** — awarię trybu AI naprawia
   builder 1 jako klasę w obu botach naraz. Zostaw ten plik w spokoju.
3. ⛔ NIE dotykasz `wtyczki/chatbot-live/` ani `wtyczki/sklep-agentow/`.
4. Commitujesz na swojej gałęzi. Nie scalasz sam — scalanie robi koordynator.

## ⛔ ZASADY
- **Nie zgadujesz.** Nie wiesz → sprawdź w kodzie, nie wymyślaj.
- **Każda naprawa = DOWÓD**: surowe wyjście komendy wklejone w commit.
- Po każdej porcji: `vendor/bin/phpcs -q wtyczki/segregator-maila/` i
  `vendor/bin/phpstan analyse --memory-limit=1G` muszą być czyste.
- Deploy na stack: `docker cp wtyczki/segregator-maila/. strona_wp_wordpress:/var/www/html/wp-content/plugins/segregator-maila/`
  + `docker exec strona_wp_wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/segregator-maila`
- **Nie bumpuj wersji** — zrobi to koordynator po scaleniu obu gałęzi.

## PLAN, KTÓRY WYKONUJESZ
Przeczytaj w całości: `boty/PLAN-NAPRAW-SEGREGATOR.md` (wersja 2, po 7 krytykach).
Robisz z niego **P0.2, P0.3, P0.4, P1.1, P1.2, P2.1, P2.2, P2.3, P2.4** — czyli wszystko
POZA P0.1 (tryb AI — robi builder 1) i P1.3/P1.4 (eksport filtrów — patrz niżej).

### Kolejność
1. **P0.3 RETENCJA** — zero czyszczenia w kodzie (grep `cron|wp_schedule` = 0 trafień), a w bazie
   siedzi prawdziwa cudza korespondencja. Cron 90 dni wzorowany 1:1 na `chatbot-live.php:45-56`
   (rejestracja przy aktywacji, wyrejestrowanie przy deaktywacji, wpis w `uninstall.php`)
   + przycisk „Wyczyść próbkę teraz" (nonce + capability) + zdanie w `INSTRUKCJA.md`.
   **DOWÓD:** podłóż wpis z datą sprzed 91 dni, odpal `wp cron event run`, pokaż liczbę wierszy przed i po.
2. **P0.2 FAŁSZYWY SUKCES** — 6 miejsc: `silnik.php:151-163`, `csv.php:118`,
   `instalator.php:99,124,186`, `admin.php:433,573`. `$wpdb->insert()` bez sprawdzenia wyniku,
   a użytkownik i tak widzi sukces. ⚠️ Builder 1 naprawił już tę klasę w ChatBocie — **zajrzyj do
   commita `82345b0` i zrób to TAK SAMO**, żeby oba boty miały jeden wzorzec.
   **DOWÓD:** wymuś porażkę zapisu i pokaż, że komunikat jest błędem, nie sukcesem.
3. **P0.4 ZGODA NA WYSYŁKĘ CUDZEJ KORESPONDENCJI DO AI** — w trybie AI treść maili osób trzecich
   wychodzi do zewnętrznego dostawcy. Dziś jest tylko informacja (`admin.php:735-736`), a potrzebne
   jest **jednorazowe, wyraźne potwierdzenie właściciela** przed pierwszym użyciem trybu AI
   (zapisane w opcjach, z datą). Bez potwierdzenia tryb AI się nie uruchamia.
4. **P1.1 + P1.2 UCZCIWY PRZEKAZ** — baner w panelu przy pierwszym uruchomieniu: „To narzędzie
   NIE łączy się z Twoją skrzynką. Pracuje na próbce, którą wgrasz."
   ⚠️ Teksty na stronie produktu (P1.1) są w **innej wtyczce** (`sklep-agentow`) — ich NIE ruszasz,
   zrobi to koordynator. Ty robisz tylko baner w panelu Segregatora.
5. **P2.1 KODOWANIE CSV** — dziś zdejmowany tylko znacznik UTF-8 (`csv.php:52,66`), reszta zakłada
   UTF-8. Plik z Excela (Windows-1250) wchodzi jako krzaki na polskich znakach. Wykryj kodowanie
   i przekonwertuj. **DOWÓD:** zaimportuj ten sam plik w UTF-8 i w Windows-1250, pokaż, że polskie
   znaki wychodzą poprawnie w obu.
6. **P2.2 PĘTLA CZYTA PLIK DO KOŃCA** — mimo limitu 500 wierszy `fgetcsv` (`csv.php:60`) przechodzi
   cały plik, licząc tylko pominięte (`csv.php:93-96`). Plik z milionem linii i tak zostanie
   przemielony. **Przerwij czytanie po przekroczeniu limitu** + limit rozmiaru pliku w bajtach
   + sprawdzenie typu pliku. **DOWÓD:** wygeneruj duży plik i pokaż, że import kończy się szybko
   i komunikatem, a nie przemieleniem całości.
7. **P2.3 WYŚCIG** — `TRUNCATE TABLE segm_maile` (`csv.php:116`, `admin.php:683`) leci bez blokady,
   a `segm_wykonaj_przebieg` (`silnik.php:76`) w tym czasie czyta tę tabelę. Dwie karty naraz =
   przebieg policzony na pustej próbce, bez ostrzeżenia. Zablokuj (transakcja albo znacznik
   „import w toku" sprawdzany przed startem przebiegu) + komunikat zamiast cichego złego wyniku.
8. **P2.4 WSTRZYKNIĘCIE FORMUŁY** — przy eksporcie CSV pole zaczynające się od `=`, `+`, `-`, `@`
   wykona się w arkuszu jako formuła. Poprzedź apostrofem.

### ⏸️ P1.3/P1.4 EKSPORT FILTRÓW — NIE ZACZYNAJ BEZ ZGODY KOORDYNATORA
To najważniejsza funkcja produktowa, ale ma pułapkę: nasz silnik zatrzymuje się na **pierwszej
pasującej regule**, a Gmail stosuje **wszystkie pasujące filtry naraz** — eksport 1:1 dałby na żywej
skrzynce inny wynik niż nasz raport. Koordynator rozstrzyga, jak to rozwiązać. Zrób najpierw
punkty 1–8, potem zamelduj.

## KIEDY KOŃCZYSZ
Wszystkie punkty 1–8 zrobione, phpcs i phpstan czyste, wtyczka wdrożona na stack, dowody wklejone
w commity. Zamelduj gotowość — koordynator scala obie gałęzie i bumpuje wersję.
