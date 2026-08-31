# Wzorce dashboardów e-commerce — ściąga do panelu K3 (wp-admin)

Zbadane źródła: WooCommerce Analytics/Orders (dokumentacja oficjalna woocommerce.com), Easy Digital Downloads Reports/Orders (easydigitaldownloads.com/docs), Shopify admin Home, Stripe Dashboard (docs.stripe.com + rozbiór 925studios.co). Data zbiórki: 2026-08-18.

## 1. Układ ekranu głównego (kolejność ważności góra→dół)
1. **Pasek KPI (4-5 kart w rzędzie)** — zawsze na samej górze, pierwsze co widać bez scrolla.
2. **Wykres w czasie** pod/obok kart — trend głównej metryki (przychód) jako linia lub sparkline pod każdą kartą.
3. **Lista ostatnich zamówień / leaderboard** (top produkty, top klienci) — niżej, jako punkt startowy do zagłębienia się.
Stripe: „opinionated home screen" — 5 liczb (gross volume, net volume, nowi klienci, udane płatności, porównanie okresu) + sparkline pod spodem, duża wysoko-kontrastowa typografia, BEZ kolorowych wykresów słupkowych (hierarchia przez typografię i biel, nie kolor). WooCommerce: Performance Indicators (Total Sales, Net Sales, Orders, AOV) → Charts → Leaderboards, w tej kolejności. EDD: Overview = sprzedaż/zarobki, AOV, nowi klienci, domyślnie „Month to Date" z porównaniem do poprzedniego okresu. Shopify Home: karty z % zmianą + sparkline, użytkownik może przestawiać kolejność. **Wniosek dla K3:** przychód i liczba zamówień ZAWSZE pierwsze, AOV trzecie, reszta niżej.

## 2. Karty metryk (stat tiles)
Standardowy zestaw 4 liczb powtarza się we wszystkich czterech: **przychód/sprzedaż, liczba zamówień, średnia wartość zamówienia (AOV), nowi klienci**. Grupowanie okresów: dziś / 7 dni / 30 dni / miesiąc bieżący / zakres własny — i **zawsze porównanie z poprzednim analogicznym okresem** jako % + strzałka (Shopify, EDD „compare to a past period"). Stripe pokazuje trend jako monochromatyczny sparkline pod liczbą, nie kolorowy wykres — kontrast buduje typografia, nie barwa. **Dla K3:** 4 karty (Przychód dziś/7dni/30dni z przełącznikiem okresu, Liczba zamówień, Śr. wartość zamówienia, Czas od wejścia do zakupu — nasza specyficzna metryka) + delta % vs poprzedni okres na każdej.

## 3. Lista/tabela zamówień
Kolumny standardowe (WooCommerce Orders, EDD Orders): **numer zamówienia, data, klient (imię+e-mail), produkt(y), kwota, metoda płatności, status, akcje**. Status jako **kolorowy badge/ikona z tooltipem** przy najechaniu — nigdy goły tekst (WooCommerce: „hovering over a status icon shows its full description in a tooltip", kolor statusu konfigurowalny). Sortowanie po nagłówkach kolumn (data, kwota); filtrowanie: dropdown statusu + zakres dat + pole szukaj w prawym górnym rogu (EDD). Kolumny włącz/wyłącz przez **Screen Options** (natywny wzorzec WP, nie custom UI). Akcje wierszowe jako ikony przy najechaniu (podgląd/edycja/resend maila). Bulk actions: checkbox przy wierszu + dropdown na górze tabeli (zmiana statusu, wysyłka ponowna, eksport zaznaczonych). Paginacja na dole: strzałki + pole „skocz do strony" + selektor liczby wierszy na stronę (WooCommerce Analytics). **Dla K3:** kolumny = Nr / Data / Klient / Produkt / Kwota / Czas-od-wejścia / Status(badge) / Akcje.

## 4. Szczegóły zamówienia
Wzorzec WooCommerce (2 kolumny): **LEWA (szeroka)** — nagłówek statusu na górze, potem box z danymi zamówienia/klienta/płatności, niżej tabela pozycji (produkt/ilość/cena/suma). **PRAWA (wąska, sidebar)** — oś czasu / notatki, kolor wg typu (systemowa=fiolet, prywatna=szary, do klienta=niebieski), pole „dodaj notatkę" na dole. Wzorzec Stripe: **Timeline** zdarzeń (utworzono→autoryzacja→pobranie→refund) jako pionowa lista ze znacznikami czasu; osobna sekcja **Receipt history** (log ostatnich 10 wysyłek) z przyciskiem **„Resend receipt"** + pole na e-mail — akcja widoczna wprost, nie schowana w menu. **Dla K3:** góra = status + dane klienta/płatności, środek = treść wysłanego maila (jak Stripe receipt) + przycisk „Wyślij ponownie" tuż obok (nie w innym miejscu ekranu), dół/sidebar = log zdarzeń tego zamówienia.

## 5. Eksport CSV
Przycisk **„Export"/„Download"** zawsze w **prawym górnym rogu** ekranu listy/raportu, widoczny bez klikania w podmenu (WooCommerce Analytics, CSV Export Guide). Flow: najpierw wybór **zakresu dat** (presety: dziś/7 dni/30 dni/ten miesiąc + własny zakres), opcjonalny filtr statusu, dopiero potem „Download" — plik trafia od razu lub na e-mail przy dużych zestawach. **Dla K3:** przycisk Export nad tabelą zamówień, otwiera date-picker z presetami, generuje CSV z aktualnie zastosowanymi filtrami (żeby eksport = to co widać na ekranie).

## 6. Dobre praktyki wp-admin (konkretnie)
- **`WP_List_Table`** (rdzeń WP) do tabeli zamówień/logów — paginacja, sortowanie, search, bulk actions, Screen Options „za darmo" i wygląd 1:1 ze resztą wp-admin (SitePoint, WPMU DEV). Nie budować własnej tabeli w CSS/JS od zera.
- **Settings API** (`register_setting`/`add_settings_section`/`add_settings_field`) do ustawień (klucze Stripe, nadawca maila) — automatyczny nonce, spójny wygląd z resztą WP, bez ręcznej obsługi `$_POST`.
- **Admin notices**: tylko przy krytycznych sprawach (błąd, brakująca konfiguracja), klasy `notice-success/error/warning`, dismissible — nie własny system „toastów".
- **Nonce wszędzie**: `wp_nonce_field()`/`wp_verify_nonce()` na formularzach, `check_admin_referer()` na linkach-akcjach (np. „Wyślij ponownie" jako link/GET musi być nonce-chroniony).
- **Dashicons**, nie własny zestaw ikon — spójność wizualna z rdzeniem.
- Layout na natywnych klasach wp-admin: `.wrap`, `.postbox`, `.widefat`, `.button-primary` — tańsze w budowie i od razu znajome dla admina, który zna WordPressa.

## 7. Kryteria oceny 0-100 (sędzia PRZED pokazem właścicielowi)
| # | Kryterium | Waga |
|---|---|---|
| 1 | Czytelność metryk (duża, kontrastowa liczba, jasna jednostka/waluta) | 15 |
| 2 | Hierarchia wizualna (przychód/zamówienia na górze, reszta niżej, nic nie krzyczy naraz) | 15 |
| 3 | Tabela zamówień skanowalna (status = kolorowy badge, nie goły tekst) | 15 |
| 4 | Porównanie okresów (% zmiana vs poprzedni okres przy każdej karcie) | 10 |
| 5 | Pusty stan (0 zamówień = komunikat + wskazówka co zrobić, nie goła tabela) | 10 |
| 6 | Spójność ze stylem wp-admin (Dashicons, `.postbox`/`.widefat`, brak obcego frameworku CSS) | 10 |
| 7 | Kontrast/dostępność (WCAG AA: tekst 4,5:1, ikony/obwódki 3:1; status nie tylko kolorem) | 10 |
| 8 | Responsywność (karty łamią się w kolumnę, tabela scrolluje na wąskim ekranie) | 5 |
| 9 | Bezpieczeństwo akcji UI (nonce, potwierdzenie przed nieodwracalną akcją — resend/refund) | 5 |
| 10 | Czas do kluczowej liczby (przychód dziś widoczny <3s, bez scrolla) | 5 |

Źródła kryteriów 6-7: WCAG 2.1 (webaim.org, testparty.ai), Dashicons dev.wordpress.org, WP_List_Table/Settings API handbook. Kryteria 1-5,8-10: synteza wzorców Stripe/WooCommerce/Shopify/EDD powyżej + empty-state UX (eleken.co, pencilandpaper.io).

## 8. Werdykt sędziego (18.08, zimny subagent, przynęty rozbrojone) + świadome odstępstwa

**66/100 przed szlifem**; TOP-braki zaadresowane: kolumna Akcje, pasek proporcji
statusów, notice-info dla banera, elewacja Przychodu, zrzuty-dowody pustego stanu
i widocznego przycisku resend (luka DOWODU — kod je miał).
**Świadomie NIE robimy** (decyzje, nie przeoczenia): metryka „czas od wejścia do
zakupu" — NIE ŚLEDZIMY wejść (minimalizacja RODO §8: przy zakupie tylko email,
przy dobrowolnej opinii imię i treść za zgodą — D32);
sparkline per karta i bulk actions — przerost przy wolumenie Z6 (pojedyncze
zakupy dziennie); karty KPI własnym stylem zamiast .postbox — Woo Analytics
też ma własne karty, spójność trzymają natywne kolory/typografia wp-admin.
