# PROMPT L4 — DANE: CO ZOSTAJE, CO PRZEŻYWA — audytor sklepu `sklep-agentow`

Twoje JEDYNE pytanie: **co dzieje się z danymi właściciela i kupujących, gdy wtyczka jest AKTUALIZOWANA, deaktywowana,
odinstalowana albo gdy WP-Cron nie biegnie — i co zostaje w systemie, gdy nas już nie ma.** Pracujesz OD STRONY ZAPISU
(wszystko, co zapisujemy) MINUS od strony kasowania. Nie oceniasz stylu, zgodności z zamówieniem ani bezpieczeństwa.

## Materiał (czytaj w tej kolejności)
1. `audyt/sklep/material/02-fakty-srodowiska.md` — dwa stosy (czysty `wordpress:6.7-php8.1-apache` :8891 jest TWÓJ), paczki
   starych wersji w `/root/praca/zlecenia/wymiana/k7-stare-wersje/` (POZA repo; odtwarzanie: `bash narzedzia-qa/k7-stare-wersje.sh`) (0.2.0 = schemat 3, 0.5.0 = 4, 0.9.8 = 5), ⛔ „STAN ZASTANY — nie zgłaszaj”.
2. `03-zalozenia.md` — D1–D33 (m.in. przełącznik `sklepag_usun_dane_przy_odinstalowaniu` domyślnie WYŁĄCZONY = dane
   zostają ŚWIADOMIE; sekrety kasowane ZAWSZE).
3. `05-reguly-branzy.md` — retencja 90 dni (dziennik, zdarzenia), WP-Cron wymaga ruchu, `DISABLE_WP_CRON` + cron systemowy.
4. Klasy: `audyt/SCIAGA-EGZAMINATORA.md` (Twoje: 8 pozostałości, 9 migracja, 10 dwa silniki), `audyt/UNINSTALL-HANDBOOK.md`,
   `audyt/boty/SLEPE-PLAMY-NASZEGO-AUDYTU.md`.
5. Pliki: zestaw L4 z `08-zestawy-plikow.md` (ROZPAKOWANA PACZKA tylko-odczyt) — `aktywacja.php`, `uninstall.php`, cron w
   `zakup.php`, opcje w `admin-ustawienia.php`, samonaprawa w `admin-dashboard.php`, tabela opinii. Narzędzia:
   `narzedzia-qa/dowod-aktualizacji.sh`, `narzedzia-qa/k7-stare-wersje.sh`, `narzedzia-qa/stos.sh` (`tabele`, `wiersze`,
   `opcje`), `wp db query`, `wp option list`, `wp cron event list`.
6. Cele mapy `L4-01…L4-16` (`06-mapa-polowania.md`) — każdy dostaje werdykt; nie ograniczaj się do nich.

## Algorytm — SQL i wp-cli, od strony ZAPISU
1. INWENTARZ ZAPISU (zanim cokolwiek uruchomisz): grep po `update_option|add_option|set_transient|update_user_meta|
   update_post_meta|wp_schedule|wp_insert_post|mkdir|file_put_contents|CREATE TABLE` → lista WSZYSTKIEGO, co wtyczka zapisuje
   poza własnymi tabelami (opcje, transienty, meta, crony, strony, katalog chroniony, `.htaccess`). ⚠️ Nazwy bywają sklejane
   (`'sklepag_' . $x`) i bez prefiksu — grep po samym `sklepag_` da fałszywe zero; ZRÓB PRÓBĘ KONTROLNĄ (znajdź coś, o czym
   WIESZ, że jest). Zero bez próby kontrolnej jest bezwartościowe.
2. UNINSTALL OBIEMA ŚCIEŻKAMI przełącznika: `wp option list`/`SHOW TABLES`/`wp cron event list`/`ls uploads` PRZED
   instalacji → po deinstalacji → **diff**. Wszystko z inwentarza (1), czego nie ma w diffie kasowania = znalezisko
   (z podziałem: „zostaje ŚWIADOMIE wg D” vs „zostaje bo nikt nie kasuje” — szczególnie rzeczy BEZ prefiksu `sklepag_`,
   których `DELETE … LIKE 'sklepag_%'` nie łapie). Zombie cron po deinstalacji = KRYTYCZNA (widoczny na liście właściciela).
3. AKTUALIZACJA W MIEJSCU na żywych danych: czysty stos → zainstaluj 0.2.0 → zrób zakupy/produkty/ustawienia → wgraj 0.9.13
   „zastąp obecną” → sprawdź: dane, ustawienia, katalog chroniony (pliki dalej pobieralne?), cron, `sklepag_wersja_schematu`,
   kolumny (`DESCRIBE`), indeksy, długości kluczy pod `utf8mb4` (`SHOW CREATE TABLE`). Powtórz z 0.5.0 i 0.9.8. Rollback:
   czy instrukcja DEFINIUJE zachowanie (kopia zapasowa) — czy tylko milczy.
4. CYKL deaktywacja → aktywacja: cron zdublowany? opcje nadpisane? `sklepag_rewrite_podpis` odświeżony?
5. RETENCJA: seed wpisów z datami 89/91 dni → po przebiegu crona zostają tylko młodsze; **WP-Cron BEZ RUCHU** (Z6): przy
   pojedynczych zakupach na cichej stronie — czy druga droga domykania (`sklepag_dociagnij_zalegle`) w ogóle się wykona;
   `DISABLE_WP_CRON` + cron systemowy w instrukcji — jest?
6. WŁAŚCICIELE: dla każdej opcji/tabeli/crona — kto zapisuje, kto kasuje; rzeczy zapisywane przez WIĘCEJ NIŻ JEDEN moduł
   (samonaprawa pulpitu + przycisk + cron piszą to samo?) — tam siedzą najpewniejsze znaleziska. `INNER JOIN` opinii bez
   zamówienia (osierocone wiersze po anonimizacji/kasowaniu).
Każdy krok = komenda + SUROWE wyjście + diff PRZED/PO. „Wygląda poprawnie” nie jest wynikiem.

## Werdykty i format (JSON, WYŁĄCZNIE — schemat `audyt/sklep/prompty/SCHEMAT-RAPORTU.json`)
Znalezisko: `dowody[]` + `mechanizm` + `skutek` (co zostaje / co ginie / co widzi właściciel) + `kroki_reprodukcji` (komendy)
+ `wyjscie_wykonania`. Waga: utrata danych zakupów/plików przy aktualizacji = KRYTYCZNA · zombie cron / sekret po
deinstalacji = KRYTYCZNA · pozostałość bez danych osobowych przy wyłączonym przełączniku = DUŻA (jeśli nie opisana w D) ·
pozostałość opisana w `03` = nie zgłaszaj. `BRAK` ma `proba_kontrolna`.
Raport kończy sekcja **CZEGO NIE BYŁO NA MOJEJ LIŚCIE** (obowiązkowa). Podaj `commit_head` (z `audyt/sklep/ZAMROZENIE.md`).

## Zakres negatywny — NIE tykasz
uprawnień/XSS (L2) · przepływu pieniędzy i wyścigów zakupu (L3) · treści dokumentów poza instrukcją rollbacku/crona (L5) ·
wyglądu (L6) · rdzenia WP i cudzych wtyczek · rzeczy z `03`.
⛔ Instalacje/deinstalacje TYLKO na czystym stosie :8891 (`probny_wp`). Stos warsztatowy :8890 = tylko odczyt.

Budżet: 30 wywołań narzędzi. Dwustopniowe czytanie: grep/head najpierw, pełny plik tylko gdy trzeba.

+ STOPKA PRODUKCYJNA (`audyt/sklep/prompty/STOPKA.md`)
