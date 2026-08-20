# Kolejka ChatBota — stan pracy buildera 1

Plik roboczy do **odtworzenia stanu po `/clear`**. Aktualizowany po każdym
skończonym kawałku. Zasady od koordynatora: osobne PR-y, **nigdy nie scalam**
(scala koordynator), nie dotykam `wtyczki/segregator-maila` ani
`wtyczki/sklep-agentow`, bez `push --force`, bez kasowania gałęzi.


## Zrodlo znalezisk

`boty/LUKI-SWIEZA-GLOWA-20-08.md` — przelot koordynatorki po kodzie obu botow
(20.08), z dowodami `plik:linia`. Dokument lezal poza repo; wjechal tu swiadomie
razem z PR #78, bo opisy PR-ow sie na niego powoluja, a recenzent nie mial jak
sprawdzic cytatow. ⛔ To NIE jest audyt: panele admina, statystyki, import CSV
i instalatory nie byly czytane.

## Naprawy luk — stan PR-ow

| PR | Luka | Co bylo zepsute u klienta | Wersja |
|---|---|---|---|
| #78 | **L1** | nonce w cache'owanej stronie — czat padal u WSZYSTKICH gosci naraz, po cichu | 1.4.1 |
| #79 | **L4** | zly albo wygasly klucz API — bot po cichu odpowiadal z regul, w nieskonczonosc | 1.4.2 |
| #80 | **L2** | limit „na IP" mierzyl adres proxy — za Cloudflare cala witryna miala jeden kubel | 1.4.3 |
| #81 | **L5** | model zaszyty jako migawka z data — bomba zegarowa u kazdego kupujacego | 1.4.4 |
| #82 | **L7** | gosc dowiadywal sie o zapisie rozmowy dopiero przy formularzu kontaktu | 1.4.5 |
| #83 | **L3** | stop-loss liczyl wywolania, a rachunek u dostawcy idzie z tokenow | 1.4.6, schemat 5 |
| #85 | **B** | tryb AI nie pamietal biezacej rozmowy (dopytanie trafialo w prozne) | 1.4.7 |
| #86 | **C** | brak sygnalu, ze bot pisze — do 18 s martwego ekranu, gosc klikal drugi raz | 1.4.8 |

Kazda z tych galezi ma **test-straznik (exit 1)** i **probe kontrolna** dowodzaca,
ze straznik pada na kodzie sprzed naprawy. Schemat bazy rusza tylko #83 (4 -> 5).

⚠️ Wszystkie galezie odbite od tego samego `main` (2ed694c), wiec przy scalaniu
konflikty sa **mechaniczne**: linia `Version`/`CHATL_WERSJA` (zostaje najwyzszy
numer), nazwa pliku paczki oraz liczby w `testy/dokumentacja-zgodnosc.sh`, ktore
**sumuja sie** miedzy PR-ami. Ten plik tez bywa dotykany w kilku galeziach —
scalenie polega na zlozeniu wierszy tabeli, nic wiecej.

## Stan na 2026-08-20

- **PR #73** — gałąź `b1/chatbot-statystyki`, wersja **1.4.0**, schemat bazy **4**.
  Zawiera: statystyki i raport luk, wykrywanie chromium (`testy/lib/chromium.js`),
  obie uwagi recenzji (dokumentacja↔kod + rejestr ponad-spec), CHANGELOG.md,
  readme.txt, ekran powitalny, konfigurowalny wygląd widgetu z kontrastem WCAG.
  **Czeka na scalenie.** Nie scalać samodzielnie.

⚠️ Kolejne gałęzie odbijać **od świeżego `main`**, a gdy #73 nie jest jeszcze
scalony — pamiętać, że `main` stoi na 1.2.5/schemat 3, więc numery wersji
i schematu w nowej gałęzi trzeba dobrać tak, żeby po scaleniu #73 wyszło
sensownie (konflikt będzie na liniach `Version`/`CHATL_WERSJA*`,
`ALLOWLIST_CHATBOT` i w oczekiwaniach `testy/dokumentacja-zgodnosc.sh`).

## Kolejka — po kolei, osobne PR-y

### A. Przejęcie rozmowy przez człowieka (zadanie sprzed przerwy)
Bot nazywa się LIVE, a nie ma tej funkcji; Tidio i Crisp mają ją jako sztandarową.
Zakres: lista aktywnych rozmów w panelu (kto pisze, ostatnie pytanie, ile czeka);
przycisk PRZEJMIJ (od tego momentu **bot milczy** w tej rozmowie); okno rozmowy
dla operatora; **wyraźny komunikat dla gościa**, że rozmawia z człowiekiem
(uczciwość jest zasadą); przycisk ZAKOŃCZ oddający rozmowę botowi; automatyczny
powrót do bota, gdy operator nie odpowiada przez ustalony czas, z komunikatem
dla gościa. WordPress nie ma gniazd — **odpytywanie przez REST**, interwał
uzasadniony liczbami w PR (odpytywanie co sekundę przy 100 gościach zabije
serwer klienta), z wydłużaniem przy ciszy. Zero usług zewnętrznych, bibliotek
i CDN. Punkt odpytywania jest publiczny → identyfikator sesji **nie do
zgadnięcia** i sprawdzany przy każdym żądaniu; operator tylko z `manage_options`.
Test ma pokazać, że bot **milczy** po przejęciu i **odzywa się** po zakończeniu,
i musi być skalibrowany (pada na kodzie sprzed zmiany).

### B. ⭐ Tryb AI nie pamięta bieżącej rozmowy — ✅ ZROBIONE (PR #85)
Zmierzone przez koordynatora: `includes/ai.php` wysyła `messages` z **dokładnie
jednym** elementem (samo pytanie), zero historii. Gość pyta „jak dojechać",
potem „gdzie zaparkować" — model nie wie, o czym mowa.
⚠️ To **nie jest** nasza świadoma granica: odpuściliśmy pamięć MIĘDZY wizytami,
a tu chodzi o pamięć W TRAKCIE jednej rozmowy.
Dołożyć historię bieżącej sesji do wywołania, **uciąć do sensownej liczby
ostatnich wymian** i uzasadnić tę liczbę w PR — każda wymiana to tokeny właściciela.

### C. Brak informacji, że bot pisze — ✅ ZROBIONE (PR #86)
Pole i przycisk nie są blokowane, brak wskaźnika, a tryb AI ma timeout 9 s
+ jedno ponowienie = do 18 s martwego ekranu. Gość pomyśli, że się zawiesiło,
i kliknie drugi raz. Zrobić to samo co przy formularzu zgłoszenia: blokada pola
i przycisku + dymek z kropkami.

### D. Brak pułapki na boty
Limit na IP jest, ale pięć adresów wystarczy, żeby wyczerpać dzienny budżet AI
właściciela — potem prawdziwi goście dostają tylko tryb regułowy, a właściciel
płaci za funkcję, która cicho przestała działać. Dodać ukryte pole-pułapkę
(honeypot): tanie i skuteczne przeciw prostym skryptom.

### E. Brak integracji z narzędziami RODO WordPressa
Zmierzone: zero wywołań `wp_privacy_personal_data*`, próba kontrolna działa.
WordPress ma wbudowany kreator eksportu i usuwania danych osobowych, a wtyczka
go nie obsługuje — przy produkcie tak mocno eksponującym RODO to niespójność
między deklaracją a wykonaniem. Zarejestrować **eksporter i kasownik** dla
zgłoszeń i historii rozmów.

### F. Import bazy pytań z CSV
Dziś można dodawać tylko po jednym wpisie; firma z gotową listą 40 pytań
w Excelu musi je przepisywać ręcznie. Eksport już jest — dorobić import
**z podglądem przed zapisem**.

### G. Stronicowanie list w panelu
Zgłoszenia urywają się na 200, rozmowy na 100, luki na 25 — bez strony drugiej.

## Warsztat (żeby nie szukać po `/clear`)

- Stack: `docker exec -u www-data strona_wp_wordpress wp …`, strona
  `http://localhost:8890`, admin `admin_roboczy`, hasło w
  `/root/praca/zlecenia/strona-wp/.env` (`ADMIN_PASSWORD`).
- Wdrożenie: `docker cp wtyczki/chatbot-live/. strona_wp_wordpress:/var/www/html/wp-content/plugins/chatbot-live/`
  + `chown -R www-data:www-data`.
- Bramki mierzone **kodem wyjścia**: `vendor/bin/phpcs -q wtyczki/`,
  `vendor/bin/phpstan analyse --memory-limit=1G` (512M za mało),
  `bash testy/dokumentacja-zgodnosc.sh`.
- Każda nowa kontrola musi mieć **próbę kontrolną** (wzorce:
  `testy/*-proba-kontrolna.sh`) — kontrola, która nie znajdzie podłożonego
  błędu, jest ślepa.
- Nowy plik wtyczki = dopisać do `ALLOWLIST_CHATBOT` w `boty/buduj-paczki.sh`
  **i** podnieść liczbę plików w `testy/dokumentacja-zgodnosc.sh`.
- Nowa funkcja ponad szkice = pozycja w `boty/PONAD-SPECYFIKACJE-BOTY.md`
  (bramka liczy pozycje) — klasa #6 ściągi egzaminatora.
- Uwaga: `⚠️ dysk hosta bywa pełny` (100% zajęte 20.08) — przy `ALTER TABLE`
  objawia się jako `Errcode: 28`, a `dbDelta()` i tak melduje „Added column".
