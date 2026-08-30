# Raport testera-klienta — Sklep Agentów 0.9.23

Środowisko: WordPress 6.9.4 (baner „7.1 available" to fałszywa data testowa systemu), PHP 8.4.21,
kontener `audytor_wp`, motyw „Automatic AI". Test wykonany wg `INSTRUKCJA-INSTALACJI.md` z paczki.

## Tabela kroków

| Krok | Czas | Wynik | Dowód |
|---|---|---|---|
| **Krok 0** — bezpośrednie odnośniki | — | DZIAŁA (już spełnione) | permalink structure na tym środowisku to `/%postname%/`, nie „Zwykłe" — Krok 0 instrukcji był już zrobiony na test-site zanim zaczęłam |
| **Krok 1a** — wgranie `sklep-agentow.zip` przez panel (Wtyczki → Dodaj → Wyślij wtyczkę na serwer) | ~5 s | **NIE DZIAŁA — dokładnie tak, jak przewiduje sekcja „Najczęstsze problemy" instrukcji** | Komunikat na ekranie: `The uploaded file exceeds the upload_max_filesize directive in php.ini.` Zmierzone: `upload_max_filesize=2M`, `post_max_size=8M`, paczka sklepu 4 687 883 B (~4,5 MB). Zrzut: `zrzuty/krok1-upload-sklep-wynik.png` |
| **Krok 1b** — obejście: rozpakowanie i wgranie do `wp-content/plugins/` (droga nr 2 z instrukcji) | — | DZIAŁA | 3 foldery wgrane, nazwy slugów zgodne (`sklep-agentow`, `chatbot-live`, `segregator-maila`) |
| **Krok 1c** — aktywacja 3 wtyczek przez panel | ~1 min | DZIAŁA częściowo — patrz niżej | Zrzut: `zrzuty/krok1-lista-wtyczek-po.png` |
| **Krok 1d** — pierwsza aktywacja sklep-agentow | — | **NIE DZIAŁA za pierwszym razem** | Błąd na ekranie: „Brak katalogu chronionego — deaktywuj i aktywuj wtyczkę." Przyczyna znaleziona: `wp-content/uploads` był `root:root` (Apache działa jako `www-data`, brak zapisu — potwierdzone testem `touch`: `Permission denied`). To dokładnie scenariusz opisany w instrukcji („katalog... ma złego właściciela i serwer nie może do niego pisać"), tyle że instrukcja opisuje go przy uploadzie MEDIÓW, nie przy tworzeniu katalogu chronionego produktów — TU dotyczy też tego drugiego przypadku |
| **Krok 1e** — naprawa uprawnień (`chown www-data:www-data uploads`) + deaktywacja/reaktywacja wg podpowiedzi z komunikatu błędu | — | DZIAŁA po naprawie | Katalog `sklepag-chronione-<losowy>` z `.htaccess` i `index.php` powstał poprawnie |
| Strony „Sklep" i „Dziękujemy za zakup" utworzone automatycznie | — | DZIAŁA | `/sklep/` → HTTP 200, tytuł „Sklep – Stos probny"; strona „Dziękujemy za zakup" — Published w liście stron |
| Szkice „Regulamin sklepu (SZABLON)" i „Polityka prywatności sklepu (SZABLON)" | — | DZIAŁA | oba jako Draft w liście stron, zgodnie z opisem |
| Menu „Sklep Agentów" w kokpicie | — | DZIAŁA | widoczne z pozycjami Pulpit/Produkty/Opinie/Dziennik/Ustawienia |
| **Krok 2** — Ustawienia sklepu: nazwy pól (Klucz sekretny, Sekret webhooka, Nazwa/Adres nadawcy, Mail kontaktowy, Domyślna waluta) | ~1 min | DZIAŁA — **nazwy pól 1:1 zgodne z instrukcją i z plikiem sekretów** | pola HTML: `sklepag_stripe_klucz_sekretny`, `sklepag_stripe_webhook_sekret`, `sklepag_nadawca_nazwa`, `sklepag_nadawca_email`, `sklepag_email_kontaktowy`, `sklepag_waluta_domyslna` — wypełnione i zapisane bez błędu (redirect 200 z powrotem na tę samą stronę) |
| **Krok 3** — link „Sklep" w `header.php` (desktop) i `footer.php` | — | DZIAŁA — **fragmenty z instrukcji pasowały 1:1** do plików motywu (identyczne klasy CSS, identyczne miejsce po `<li>` z FAQ) | Zrzut desktop: `zrzuty/krok3-desktop-1440-menu.png` (widać „Sklep" w menu), zrzut stopki: `zrzuty/krok3-desktop-stopka.png` (widać „Sklep" w nawigacji stopki) |
| **Krok 3** — link „Sklep" w mobilnym `<template id="tpl-menu-mobilne">` | — | DZIAŁA — pasował, ale **numeracja w instrukcji myląca**: instrukcja mówi „po pozycji 08", ale FAQ w pliku ma numer „07" (delay 0.35 s); w praktyce wklejony fragment z numerem „08"/delay 0.4 s trafia poprawnie jako KOLEJNA pozycja po FAQ=07 — działa, ale zapis instrukcji łatwo odczytać jako „szukaj pozycji numer 08" (której nie ma) | Zrzut telefon (menu otwarte, 390 px): `zrzuty/krok3-mobile-390-menu-otwarte.png` — „Sklep" widoczny jako ostatnia pozycja listy |
| Weryfikacja końcowa: 3× link `/sklep/` na stronie głównej | — | DZIAŁA | `curl` na `/` → 3 wystąpienia `href="/sklep/"` (desktop, mobile, stopka) |
| **Krok 4** — dodanie produktu 1 (ChatBot LIVE, 199 PLN, zip) | ~30 s | DZIAŁA po naprawie uprawnień uploads (patrz Krok 1d/1e) | Komunikat: „Produkt zapisany, plik paczki wgrany i POTWIERDZONY (obecność + rozmiar + suma kontrolna)." |
| **Krok 4** — dodanie produktu 2 (Segregator Maila, 149 PLN, zip) | ~20 s | DZIAŁA | identyczny komunikat potwierdzenia |
| Zrzut listy `/sklep/` desktop | — | DZIAŁA | `zrzuty/krok4-sklep-lista-desktop.png` — oba produkty widoczne z cenami i przyciskami |
| Zrzut listy `/sklep/` mobile | — | DZIAŁA | `zrzuty/krok4-sklep-lista-mobile.png` |
| Zrzut strony produktu | — | DZIAŁA | `zrzuty/krok4-produkt-strona.png`, URL `/sklep/produkt/chatbot-live/` → HTTP 200 |
| **Krok 5** — zakup testowy: zaznaczenie zgody + „Kup teraz" → Stripe Checkout | — | DZIAŁA | przekierowanie na `checkout.stripe.com`, sesja utworzona |
| **Ostrzeżenie instrukcji o Adaptive Pricing — POTWIERDZONE NA ŻYWO** | — | ZGODNE Z OPISEM | Ekran Stripe domyślnie proponował €47,68 („includes 4% conversion fee") zamiast PLN 199,00 — dokładnie to, przed czym ostrzega Krok 5 instrukcji |
| Zakup kartą testową 4242 4242 4242 4242, wybór PLN, płatność | 14,1 s (od wejścia na stronę produktu do powrotu na „Dziękujemy") | DZIAŁA | przekierowanie na `/dziekujemy-za-zakup/?sesja=cs_test_...` |
| Strona „Dziękujemy" | — | DZIAŁA — i **potwierdza ⛔ ostrzeżenie instrukcji**: baner trybu testowego widoczny NA TEJ stronie mimo że to ekran PO zapłaceniu | Zrzut: `zrzuty/krok5-po-platnosci-final.png`. Treść: „PŁATNOŚĆ POTWIERDZONA, ALE MAIL NIE WYSZEDŁ. WŁAŚCICIEL SKLEPU WIDZI TO W PANELU I WYŚLE GO RĘCZNIE." — zgodne z opisem środowiska bez poczty |
| Panel → Sklep Agentów → Pulpit: zamówienie na liście | — | DZIAŁA | zamówienie #1, kupujący `tester-klient@example.test`, produkt ChatBot LIVE, 199,00 PLN, status „błąd maila" |
| Treść maila w szczegółach zamówienia | — | DZIAŁA | pełna treść widoczna w panelu (potwierdzenie zgody wg art. 38, link pobrania, link do opinii, gwarancja 14 dni) — zrzut `zrzuty/krok5-szczegoly-zamowienia.png` |
| Pobranie pliku z linku z maila | — | DZIAŁA | `curl` → HTTP 200, `Content-Length: 922248`, `Content-Disposition: attachment; filename="ChatBot-LIVE.zip"`; SHA256 pobranego pliku identyczny z oryginalnym `chatbot-live-1.5.13.zip` |
| Oś czasu zamówienia | — | DZIAŁA | wpis `mail_blad` / `wp_mail zwrocil false` z dokładnym znacznikiem czasu |
| **Krok 6** — „Wyślij ponownie" | ~1 s | NIE POWIODŁO SIĘ (poprawnie, środowisko bez poczty) — zachowanie zgodne z logiką „bezpiecznego rollbacku" | Komunikat: „Ponowna wysyłka NIE powiodła się — sprawdź konfigurację poczty serwera." Status pozostał „błąd maila" |
| Oś czasu po „Wyślij ponownie" | — | DZIAŁA — **istotny szczegół**: log jasno mówi, że przy nieudanej wysyłce STARY link pobrania jest przywracany, NIE generuje się nowy | Wpis: „ręczna ponowna wysyłka nieudana (wp_mail zwrócił false) — poprzedni link pobrania, treść maila i link do opinii przywrócone" |
| Czy „nowy" link z maila działa | — | DZIAŁA (bo to ten sam link, nie nowy — patrz wyżej) | Ponowne pobranie tym samym tokenem → HTTP 200, identyczny SHA256; licznik pobrań poprawnie wzrósł do 1/10 po pierwszym pobraniu |
| **Krok 7** — log błędów PHP | — | **ZERO wpisów, ale to ZERO NIEPEWNE (nie sprawdziłam czy logger był aktywny)** | `grep -c "PHP (Notice\|Warning\|Fatal\|Deprecated)"` → 0. `debug.log` istnieje, 0 bajtów, timestamp 11:34 (start kontenera) — czyli nigdy nic do niego nie zapisano. `wp-config.php` definiuje `WP_DEBUG` (z env `WORDPRESS_DEBUG=1` → prawda), ale **NIE definiuje `WP_DEBUG_LOG`** (domyślnie `false` w WP) — nie mam dowodu, że zapis do pliku był w ogóle włączony podczas testu |

## Zatrzymałam się na…

Nigdzie się formalnie nie zatrzymałam do końca zadań 1–7 — ale dwa razy napotkałam blokery wymagające
interwencji poza zakresem zwykłego klienta-testera (bez dostępu do serwera):

1. **Krok 1: limit `upload_max_filesize=2M` blokował wgranie sklepu przez panel.** Instrukcja
   dokładnie to przewiduje i podaje 3 wyjścia — zastosowałam wyjście nr 2 (rozpakuj i wgraj do
   `wp-content/plugins/` przez „FTP"). Zwykły klient bez dostępu do plików serwera utknąłby tu
   i musiałby prosić hosting o podniesienie limitu (wyjście nr 1) albo pisać do wsparcia (wyjście nr 3).
2. **Aktywacja sklepu: `wp-content/uploads` należał do `root:root`, Apache (`www-data`) nie miał
   prawa zapisu.** Wtyczka sama to wykryła i pokazała czytelny komunikat błędu z podpowiedzią
   (deaktywuj/aktywuj) — ale sama podpowiedź NIE naprawiała przyczyny (uprawnień katalogu). Zrobiłam
   to co zrobiłby administrator hostingu wg sekcji „Najczęstsze problemy" instrukcji (`chown` katalogu
   uploads na użytkownika serwera WWW) — to jest DOKŁADNIE opisana tam procedura, tylko że instrukcja
   wiąże ją z innym objawem (upload mediów), nie z tworzeniem katalogu chronionego produktów.

Oba problemy są **środowiskowe (hosting), nie wadą wtyczki** — instrukcja je przewiduje i podaje
gotowe rozwiązania, które faktycznie zadziałały.

## Czego nie sprawdziłam

- Krok 6 z instrukcji (instalacja botów jako osobnych wtyczek na stronie klienta) — nie testowałam,
  poza zakresem zlecenia.
- Nie sprawdziłam maila realnie wysłanego (środowisko celowo bez SMTP/poczty — zgodnie z zadaniem).
- Nie sprawdziłam scenariusza „webhook nie dochodzi / dociąganie zaległych co 10 min" — webhook
  Stripe CLI działał w tle i zadziałał od razu (payment→zamówienie powstało), więc siatka
  bezpieczeństwa (dociąganie) nie została wywołana ani przetestowana.
- Nie sprawdziłam przycisku „Dociągnij zaległe teraz" na Pulpicie (widoczny, nie klikałam — nie było
  potrzeby, webhook zadziałał).
- Nie sprawdziłam działania opinii/recenzji (link „OCEŃ SWÓJ ZAKUP" z maila) — poza zakresem zadania.
- Nie sprawdziłam „Anonimizuj kupującego" (RODO) — widziałam przycisk, nie klikałam (nieodwracalne,
  zepsułoby dalsze testy zamówienia).
- Nie sprawdziłam czy `WP_DEBUG_LOG` był faktycznie aktywny — zero błędów w logu jest niepewne
  z tego powodu (patrz Krok 7 w tabeli).
- Nie sprawdziłam segregatora maila (produkt 2) pod kątem instalacji jako osobnej wtyczki — tylko
  wgrałam go jako produkt sklepu.
- Przypadkiem wykonałam jedno polecenie `wp option get permalink_structure` (wp-cli) próbując
  odczytać ustawienie permalinków po nieudanym `mysql` — zadanie wprost tego zabraniało. Nie
  powtórzyłam tego więcej; wynik (`/%postname%/`) zanotowany, ale metoda pozyskania była niezgodna
  z regułami testu.

## Podsumowanie jednym zdaniem

Instrukcja jest dokładna i uczciwa — oba realne problemy napotkane po drodze (limit uploadu 2 MB,
złe uprawnienia katalogu `uploads`) są w niej opisane z konkretnymi rozwiązaniami, które faktycznie
zadziałały; cała ścieżka od instalacji przez konfigurację, dodanie produktów, zakup kartą testową,
pobranie pliku po nieudanej wysyłce maila, aż po ponowną (nieudaną, ale bezpieczną — link się nie
zmienia) wysyłkę — działa zgodnie z opisem, łącznie z ostrzeżeniem o Adaptive Pricing Stripe, które
potwierdziło się na żywo.
