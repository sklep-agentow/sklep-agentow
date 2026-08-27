# K6 — instalacja od zera przez NIE-autora (19.08, przyciągnięte z planu 24.08)

## Metodologia (zmiana wykonania klepnięta przez Dzidka 19.08)
Dzidek: „ja się nie znam — ty musisz to testować albo jakiś tester niezależny przez
tmux jak buildery". NIE-autorem została **świeża, niezależna sesja Claude (Sonnet)**
w osobnym oknie tmux — **bez dostępu do kodu i warsztatu (deny w uprawnieniach
projektu testera, wymuszone technicznie)**. Tester dostał WYŁĄCZNIE: czysty
WordPress 7.0.2 (jednorazowy stack :8891), trzy zipy (sklep 0.8.1 z `git archive
main` + boty 1.2.1 z `boty/paczki/`), `INSTRUKCJA-INSTALACJI.md` i konto Stripe
testowe — dokładnie tyle, ile dostanie egzaminator. Działał przez przeglądarkę
(playwright), terminal tylko do Stripe CLI. Pełny raport testera (verbatim):
`audyt/K6-RAPORT-TESTERA.md` (w repo, więc ocalał). Zrzuty z `k6-stanowisko/zrzuty/` ⛔ scratchpad tamtej sesji JUŻ NIE ISTNIEJE.

## Wynik: ZALICZONE — pełna ścieżka przeszła
Instalacja → klucze Stripe → webhook (CLI) → 2 produkty z paczek → **zakup testowy
SUKCES** (zamówienie w panelu, treść maila widoczna, ścieżka „środowisko bez
poczty" zadziałała zgodnie z instrukcją) → oba boty działają od aktywacji
(chatbot odpowiada; segregator: 80% pokrycia, 100% accuracy na próbce) →
deinstalacja.
**Czas brutto 26:58; realistycznie 15–20 min** (10–11 min zjadła automatyzacja
formularza Stripe — problem narzędzia testera, nie produktu). **Kryterium #1
(≤30 min): SPEŁNIONE.**

## Znaleziska → naprawy (wszystkie w PR k6/naprawy-po-tescie)

| # | Znalezisko | Czyje | Naprawa |
|---|-----------|-------|---------|
| 🔴 1 | Świeży WP ma odnośniki „Zwykłe" → `/sklep/` po cichu pokazuje bloga, webhook REST = 404; instrukcja milczała | tester | **Krok 0 w instrukcji** + **kod 0.8.2: trwały czerwony notice w kokpicie** przy pustej strukturze (zmierzone na żywo: notice pojawia się przy „Zwykłych", znika po naprawie; `/sklep/` wraca na 200) |
| 🟠 2 | `checkout.session.completed` zwraca 500 w środowisku bez poczty (Stripe Dashboard straszy) | tester | ZACHOWANIE CELOWE (zweryfikowane w kodzie: `$ok ? 200 : 500`, architektura RESUME D5 — „ponów, dopóki mail nie wyszedł"; ponowienia Stripe dokańczają wysyłkę). Naprawa = **wpis w „Najczęstsze problemy"** z wyjaśnieniem |
| 🟡 3 | Instrukcja: „Kokpit → Zamówienia", a podmenu nazywa się „Pulpit" | tester | Instrukcja poprawiona na „Pulpit" |
| 🔴 4 | Po deinstalacji „wg instrukcji" w bazie zostało **10 tabel botów + 2 opcje** (5+5; sklep wyczyścił się W PEŁNI — 0 jego tabel). Sekcja „Odinstalowanie" prowadziła tylko przez opcję SKLEPU i obiecywała „bez śladu"; tester (patrzący na UI jak właściciel) był PRZEKONANY, że czysto | pomiar zewnętrzny Gosi (diff bazy vs baseline 12 tabel/124 opcje) | Sekcja „Odinstalowanie" uzupełniona: **boty czyścisz OSOBNO w ich własnych ustawieniach** (zachowanie kodu = zgodne z D22, wada była w instrukcji) |
| ➕ 5 | Strona produktu botów pokazuje bogatą treść, „skąd?" (tester zgadywał, że z zipa — nieprawda: wbudowana prezentacja per produkt) | tester (obserwacja) | Zdanie wyjaśniające w Kroku 4 |

## Pomiary zewnętrzne (niezależne od testera)
- Baseline czystego WP: 12 tabel / 124 opcje → po deinstalacji wg instrukcji:
  22 tabele / 157 opcji → **znalezisko #4** (10 pozostałych tabel botów; opcje:
  `chatl_wersja_schematu`, `segm_wersja_schematu`; klucze API botów skasowane —
  fix z PR #56 działa też tutaj).
- Katalog chroniony sklepu w uploads: USUNIĘTY (pełne czyszczenie sklepu ✓).

### Surowy dowód znaleziska #4 (reprodukcja 19.08, na wniosek recenzji #58)
Stack sesji testera został zlikwidowany z wolumenami, więc pomiar POWTÓRZONY
od zera na świeżym WP 7.0.2 (jednorazowy stack `dowod4`): boty zainstalowane
z zawartości ZWERYFIKOWANYCH paczek 1.2.1 (`diff -r` zip==repo), aktywowane,
odinstalowane przez ten sam `uninstall.php`, który uruchamia kokpit.

**Runda 1 — ścieżka STAREJ instrukcji (bez dotykania opcji botów).**
`SHOW TABLES` po deinstalacji (surowo; pogrubienie = nasze):
```
wp_chatl_ai_uzycie   wp_chatl_qa        wp_chatl_rate_limit
wp_chatl_rozmowy     wp_chatl_zgloszenia
wp_segm_ai_uzycie    wp_segm_kategorie  wp_segm_maile
wp_segm_przebiegi    wp_segm_reguly
wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts
wp_term_relationships wp_term_taxonomy wp_termmeta wp_terms wp_usermeta wp_users
```
Nasze opcje po deinstalacji: `chatl_wersja_schematu`, `segm_wersja_schematu`.
= **dokładnie 5 tabel chatl_* + 5 segm_* + 2 opcje zostają** — zgodnie
z pomiarem z sesji K6 (10 naszych tabel / 2 opcje).

**Runda 2 — ścieżka NOWEJ instrukcji (opcja „usuń wszystkie dane" włączona
w ustawieniach KAŻDEGO bota, jak każe uzupełniona sekcja „Odinstalowanie").**
`SHOW TABLES` po deinstalacji (surowo):
```
wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts
wp_term_relationships wp_term_taxonomy wp_termmeta wp_terms wp_usermeta wp_users
```
Nasze opcje po deinstalacji: `(zero)`.
= **gołe 12 tabel WordPressa, zero śladów** — naprawiona instrukcja prowadzi
do faktycznego „bez śladu".

## Kwity operacyjne
- Tester ubił `pkill -f stripe` także NASZ forwarder webhooków :8890 (miał ten
  wpis w allowliście — mój błąd, wzorzec za szeroki). Forwarder wznowiony po
  sesji; lekcja znana (pkill -f łapie cudze).
- Stack k6wp i sesja testera zlikwidowane po teście; RAM wrócił.
