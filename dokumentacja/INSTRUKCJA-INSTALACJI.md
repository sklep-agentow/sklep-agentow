# Instrukcja instalacji — Sklep Agentów + 2 boty (WordPress)

Wersja robocza (przyrostowa, do finalizacji w K8: zrzuty + diagram).
Dla właściciela strony — instalacja od zera do pierwszego testowego zakupu.
Czas: ~30 minut. Nic tu nie wymaga programowania.

## Co dostajesz

Trzy wtyczki WordPressa (pliki .zip):

1. **`sklep-agentow.zip`** — sklep: strony „Sklep" i „Dziękujemy", płatności
   Stripe (TRYB TESTOWY — żadne prawdziwe pieniądze nie płyną), mail po
   zakupie z linkiem do pobrania, panel w kokpicie WordPressa (zamówienia,
   produkty, dziennik logowań, ustawienia).
2. **`chatbot-live.zip`** — produkt: bot czatu na stronę (bąbelek).
3. **`segregator-maila.zip`** — produkt: segregator poczty na próbkach CSV.

Boty mają WŁASNE instrukcje w środku paczek — ta instrukcja prowadzi przez
instalację całości i uruchomienie sklepu.

## Wymagania

- WordPress 6.7 lub nowszy · PHP 8.1 lub nowszy (sprawdzisz w:
  Narzędzia → Zdrowie witryny → Informacje).
- Konto Stripe (darmowe) — do testów wystarczy samo konto, BEZ aktywacji
  płatności i weryfikacji firmy: <https://dashboard.stripe.com/register>.
- Dostęp administratora do kokpitu WordPressa (wp-admin).

## Krok 1 — instalacja wtyczki sklepu (5 min)

1. Kokpit → Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer →
   wybierz `sklep-agentow.zip` → Zainstaluj → **Włącz**.
2. Przy włączeniu wtyczka SAMA tworzy strony: „Sklep", „Dziękujemy za zakup"
   oraz dwa SZKICE do uzupełnienia: „Regulamin sklepu (SZABLON)" i „Polityka
   prywatności sklepu (SZABLON)" — wypełnij je swoimi danymi i opublikuj,
   zanim pokażesz sklep światu.
3. W menu kokpitu pojawia się pozycja **Sklep Agentów**.

## Krok 2 — klucze Stripe (10 min)

1. Zaloguj się do Stripe → upewnij się, że przełącznik **Test mode**
   (prawy górny róg Dashboardu) jest WŁĄCZONY.
2. Dashboard → Developers → API keys → skopiuj **Secret key**
   (zaczyna się od `sk_test_…`).
3. Kokpit → Sklep Agentów → Ustawienia → sekcja „Stripe (tryb testowy)":
   - **Klucz sekretny (sk_test_…)** — wklej klucz z punktu 2.
   - **Sekret webhooka (whsec_…)** — patrz Krok 3 (zależy od tego, gdzie
     stoi strona).
4. Uzupełnij też: **Nazwa nadawcy** i **Adres nadawcy** (od kogo przychodzą
   maile po zakupie), **Mail kontaktowy właściciela** (pokazywany kupującym
   przy problemach z linkiem), **Domyślna waluta nowych produktów** (np. PLN).

## Krok 3 — webhook (informacja o opłaceniu zakupu)

Stripe po opłaceniu wysyła sygnał („webhook") na Twoją stronę — z niego
powstaje zamówienie i mail. Skąd wziąć sekret webhooka:

**A. Strona na hostingu z publicznym adresem (docelowo):**
1. Dashboard Stripe (Test mode) → Developers → Webhooks → Add endpoint.
2. Adres endpointu: `https://TWOJA-DOMENA/wp-json/sklepag/v1/webhook`
3. Zdarzenie do nasłuchu: `checkout.session.completed`.
4. Po utworzeniu skopiuj **Signing secret** (`whsec_…`) → wklej w
   Ustawieniach sklepu.

**B. Komputer lokalny / środowisko testowe bez publicznego adresu:**
Serwery Stripe nie widzą localhosta — użyj oficjalnego Stripe CLI:
```
stripe listen --forward-to http://localhost:PORT/wp-json/sklepag/v1/webhook
```
Sekret webhooka pokaże `stripe listen --print-secret` (⚠️ to INNY sekret niż
z Dashboardu — wklej ten z CLI).

**Siatka bezpieczeństwa:** nawet gdy webhook nie dochodzi, sklep co 10 minut
SAM dopytuje Stripe o opłacone sesje i domyka zakupy (wolniej, ale pewnie).
Ręczny przycisk „dociągnij zaległe" jest na dashboardzie sklepu.

## Krok 4 — produkty (10 min)

1. Kokpit → Sklep Agentów → Produkty → **Dodaj produkt**.
2. Wypełnij: nazwa (np. „ChatBot LIVE"), opis krótki (na listę sklepu),
   opis pełny, cena, waluta.
3. **Plik paczki (ZIP)** — wgraj zip bota (np. `chatbot-live.zip`).
   Plik ląduje w katalogu chronionym pod losową nazwą — nikt nie pobierze
   go bez opłaconego zamówienia.
4. Zaznacz **„Produkt widoczny i kupowalny w sklepie"** → Zapisz.
   (Bez potwierdzonego pliku nie da się włączyć sprzedaży — to celowe.)
5. Powtórz dla drugiego bota.

## Krok 5 — pierwszy testowy zakup (5 min)

1. Wejdź na stronę **Sklep** → wybierz produkt → „Zobacz szczegóły" →
   zaznacz zgodę → **Kup teraz (płatność testowa)**.
2. Na stronie Stripe podaj dowolny mail i testową kartę:
   **4242 4242 4242 4242**, dowolna przyszła data ważności, dowolne CVC.
3. Po płatności wrócisz na „Dziękujemy" — strona sama odświeża status aż do
   „Mail wysłany".
4. Sprawdź: Kokpit → Sklep Agentów → **Zamówienia** — zakup jest na liście,
   a treść maila obejrzysz w szczegółach zamówienia (przycisk „Wyślij
   ponownie" gdyby mail nie dotarł).

⚠️ **Maile:** wtyczka używa standardowej wysyłki WordPressa. Na hostingu
wysyłką rządzi hosting — jeśli maile nie wychodzą, skontaktuj się z pomocą
hostingu albo skonfiguruj SMTP (dowolną wtyczką SMTP). W środowisku
testowym bez poczty treść maila ZAWSZE widać w panelu przy zamówieniu.

## Krok 6 — instalacja botów jako wtyczek Twojej strony (opcjonalnie)

Boty to produkty sklepu — ale możesz ich też używać u siebie:
Wtyczki → Dodaj nową → wyślij `chatbot-live.zip` / `segregator-maila.zip` →
Włącz. Dalej prowadzą instrukcje W paczkach botów (konfiguracja w kwadrans).

## Ustawienia produkcyjne (gdy strona idzie w świat)

- **Ustawienia → Czytanie → ODZNACZ „Proś wyszukiwarki o nieindeksowanie"** —
  bez tego WordPress nie wystawia mapy strony (sitemap) i Google nie
  zaindeksuje sklepu.
- Rozważ ochronę logowania na poziomie serwera (np. fail2ban albo reguła
  WAF na `wp-login.php`) — wtyczka spowalnia zgadywanie haseł i ogranicza
  próby z jednego adresu, ale zmasowany atak z WIELU adresów to robota dla
  zapory serwera, nie wtyczki.
- Jeśli na stronie działa wtyczka bezpieczeństwa/WAF — dopuść publiczny
  POST na `…/wp-json/sklepag/v1/webhook` (autoryzacją jest podpis Stripe).

## Test a produkcja — co trzeba zmienić, żeby sprzedawać naprawdę

Obecnie WSZYSTKO działa w trybie testowym Stripe (baner na stronach sklepu
o tym przypomina). Przejście na prawdziwe płatności to osobna decyzja
i osobne kroki — m.in.: aktywacja konta Stripe (weryfikacja tożsamości,
rachunek), klucze `sk_live_…` i nowy webhook produkcyjny, wysyłka maili
przez hosting/SMTP, wypełnione Regulamin i Polityka prywatności, usunięcie
banera testowego. Pełna lista różnic: rozdział „Co oddziela tryb testowy
od produkcji" w dokumentacji projektu.

## Najczęstsze problemy

- **Zakup „wisi" na Dziękujemy** → webhook nie dochodzi. Sprawdź sekret
  webhooka (Dashboard vs CLI — to RÓŻNE sekrety!). Sklep i tak domknie
  zakup w ~10 minut (dociąganie zaległych) — albo od ręki: dashboard
  sklepu → „dociągnij zaległe".
- **Mail nie przyszedł** → treść maila jest w panelu przy zamówieniu
  („Wyślij ponownie" po naprawie poczty); na hostingu skonfiguruj SMTP.
- **„Nie można włączyć sprzedaży bez pliku"** → produkt musi mieć wgrany
  i potwierdzony zip zanim pójdzie do sprzedaży — wgraj plik w edycji
  produktu.
- **Strona produktu daje 404 po przenosinach** → Ustawienia → Bezpośrednie
  odnośniki → Zapisz (odświeżenie reguł adresów).

## Odinstalowanie

Wtyczki → Sklep Agentów → Wyłącz → Usuń. Domyślnie dane sprzedaży ZOSTAJĄ
w bazie (historia zamówień to nie śmieć). Chcesz zniknąć bez śladu?
Ustawienia sklepu → „Dane przy odinstalowaniu" → włącz „Pełne czyszczenie"
PRZED usunięciem wtyczki — wtedy znikają tabele, opcje, strony sklepu,
pliki chronione i zaplanowane zadania. Klucze Stripe są usuwane ZAWSZE,
niezależnie od tej opcji.
