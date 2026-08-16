<!--
SZABLON PULL REQUESTA do repo klienta. Kopiuj do `.github/PULL_REQUEST_TEMPLATE.md`.
Wzorowany na szablonie WooCommerce (checkboxy wymuszające deklarację) + nasze klasy błędów
z 2026-08-09. Krótki celowo — szablon, którego nikt nie wypełnia, jest gorszy niż żaden.
-->

## Co zmienia

<!-- Jedno–dwa zdania. Po co ta zmiana, nie jak działa. -->

## Dowód, że działa

<!-- ⛔ NIE „sprawdziłem". Wklej WYNIK: wyjście testu, zapytanie SQL, zrzut ekranu, log.
     Brak dowodu = PR nie do scalenia. -->

**Testowane na:** <!-- wersja WP, wersja PHP, czysta instalacja czy z danymi -->

## Kontrola przed scaleniem

- [ ] **Sprawdziłam, jak zrobiono to samo obok** w tym repo (transakcja, operacja na pliku, token, walidacja) i zrobiłam tak samo. <!-- To jedno pytanie złapałoby 3 z 6 błędów, za które dostaliśmy zwrot 1.3.13 -->
- [ ] **Wynik każdej operacji jest sprawdzany** — nie zapisuję „zrobione", jeśli nie zmierzyłam skutku (plik faktycznie zniknął, wiersz faktycznie się zmienił).
- [ ] **Transakcje mają ROLLBACK**, `COMMIT` jest warunkowy, żaden zapasowy ślad (kontakt, kopia) nie znika przed potwierdzeniem nowego zapisu.
- [ ] **Rzeczy jednorazowe są atomowe** — token, licznik, limit, rezerwacja: jedna operacja, nie „sprawdź, potem zapisz".
- [ ] **Wyjście escapowane** (`esc_html`/`esc_attr`/`wp_kses`) — osobno od sanityzacji wejścia. To dwie różne rzeczy.
- [ ] **Uprawnienia sprawdzone osobno od nonce** — `check_ajax_referer()` nie zastępuje `current_user_can()`.
- [ ] **Symetria zapis↔sprzątanie**: cokolwiek nowego zapisuję poza własnymi tabelami (opcja, transient, metadana, cron, rola, plik) — dopisane do `uninstall.php` **w TYM SAMYM PR**.
- [ ] **Limity mierzą to, co robi szkodę** (nie bajty, gdy groźne są piksele lub liczba wierszy).
- [ ] **Dokumentacja zgodna z kodem** — jeśli zmieniłam zachowanie, README/instrukcja klienta zaktualizowane tutaj, nie „potem".
- [ ] **Wpis w CHANGELOG** dodany.

## Klasyfikacja zmiany

**Waga:** <!-- patch / minor / major -->
**Typ:** <!-- fix / feat / docs / refactor / perf / test / chore -->

## Ryzyko i co może się zepsuć

<!-- Co ta zmiana może popsuć u klienta? Jeśli „nic" — napisz, dlaczego jesteś pewna.
     Jeśli nie wiesz, napisz „nie wiem" — to poprawna odpowiedź, zgadywanie nie jest. -->
