# KRYTYKA PLANU K7 — runda 1 (25.08.2026, wieczór) — rejestr UNII, nie głosowania

Recenzenci: **G** = nasz krytyk (subagent sonnet, świeże okno, rubryka R1–R12, dostęp do repo) ·
**C1–C4** = cztery obce modele (bez dostępu do repo; wklejone przez właściciela). Werdykt G: **FAIL (8/12)**.

## Kalibracja krytyków — pięć luk celowo zostawionych w planie v1.1
| sonda | G | C1 | C2 | C3 | C4 |
|---|---|---|---|---|---|
| P1 WP-Cron chodzi tylko przy ruchu (Z6 = pojedyncze zakupy) | ✅ | — | ◐ (matrix „cron systemowy vs WP-Cron") | — | — |
| P2 pieniądze PO sprzedaży: zwrot / gwarancja 14 dni | ✅ | — | ✅ | — | ◐ (odstąpienie w UI) |
| P3 aktualizacja wtyczki w miejscu na żywych danych | ✅ | ◐ (dumpy SQL) | ✅ | — | — |
| P4 prywatny e-mail w historii commitów | ✅ | — | — | — | — |
| P5 strefa czasu znaczników zakupu (S-04) | — | — | — | — | — |
| **razem** | **4/5** | 0,5 | 2,5 | 0 | 0,5 |
Wniosek: obce modele bez repo dają katalog ryzyk WP/Stripe (użyteczny), ale nie widzą luk zakorzenionych
w produkcie — ich milczenie NIE jest dowodem czystości. Nasz krytyk z repo i rubryką jest przyrządem, któremu
warto ufać (4/5, zero fałszywych alarmów po sprawdzeniu w kodzie).

## Fakty sprawdzone grepem 25.08 (hipotezy krytyków → prawda/fałsz)
- Stripe bez SDK/Guzzle (`wp_remote_post`, zero zależności) → „dependency hell" C1: **fałsz**. Do `02-fakty`.
- `Stripe-Version` przypięty (`stripe.php:31`, `SKLEPAG_STRIPE_WERSJA`) → C1 „brak wersji API": **fałsz**.
- `payment_status` metod asynchronicznych obsłużony (`zakup.php:182-184, 493, 523`) → cel L3: PRZETESTOWAĆ (BLIK testowy).
- Wszystkie funkcje z prefiksem `sklepag_` → C2 „namespace pollution": **fałsz**.
- `ABSPATH` w każdym pliku PHP; `uninstall.php` ma `WP_UNINSTALL_PLUGIN` (linia 11) → C1: **fałsz**.
- `get_charset_collate()` w dbDelta → C4 „charset": **fałsz** (długości kluczy pod utf8mb4 — mały cel L4).
- Assety admina warunkowo (`admin.php:96` tylko ekrany `sklepag`) → C1 „asset leak": **fałsz**.
- `flush_rewrite_rules` przy aktywacji/deaktywacji (`aktywacja.php:151,156`) → C4: **fałsz**.
- Brak `localStorage/sessionStorage` w JS → C2 „browser storage": **nie dotyczy**.
- Stripe błędy HTTP ≥ 400 → `WP_Error` (`stripe.php:55-62`) → cel L3: co WIDZI kupujący/właściciel przy 401/429.
- Historia commitów: 288/288 adresów `*.noreply.github.com` → K2 DESTYLATU **czysta**; zapis do rejestru jako sprawdzone.
- ⚠️ **Formularz kupna gościa wymaga nonce** (`zakup.php:66` `check_admin_referer('sklepag_kup')`) → pod page-cache
  strona produktu po 12–24 h serwuje martwy nonce = sprzedaż pada po cichu (klasa L1 botów). C1 trafił. **Cel wysokiej wagi.**
- Brak `wp_privacy_personal_data_exporters/erasers` → C1/C2 **prawda** (WP-way RODO nieobecne; spec tego nie wymaga → decyzja zakresu).
- Instrukcja nie mówi, że WP-Cron wymaga ruchu → P1 **prawda** (dokument + ryzyko produktu).
- Narzędzia do żądań równoległych ISTNIEJĄ (`Promise.all` w `nonce-cache-test.js`, `dziennik-test.js`, `produkty-test.js`) → C4 „M3 bez narzędzi": fałsz, ale prompt L3 ma je wskazać.

## Unia znalezisk → decyzja (P = przyjęte do v2 · M = do materiału 02/03 „nie zgłaszaj" · O = odrzucone · D = decyzja właściciela)
| # | znalezisko (kto) | decyzja | jak w v2 |
|---|---|---|---|
| 1 | GO/NO-GO „skryptem", skryptu nie ma (G K1, C2 #15, C3 #11) | P | `narzedzia-qa/k7-koniec.sh` — spec w planie §8, budowany w kroku 1, próba kontrolna |
| 2 | kalibracja 100 % vs `kalibruj.sh` 2/3 i rubryka §12 ≥2/3 bez wpisu DECYZJE (G K2) | P | D33 w DECYZJE (100 % własnego zakresu ⊇ 2/3); `kalibruj.sh` NIE używany w K7 — mechanika gałęzi |
| 3 | BS4 na samym planie: nazwa bramki vs to, co liczy (G K3) | P | cel w bloku „strażnicy CI": każda bramka — nazwa ↔ pomiar |
| 4 | decyzje #2 PR i #3 zwiad są techniczne (G D1) | P | #2 = klepnięcie jednym słowem (reguła „commit/push po klepnięciu" zostaje), #3 = MOJA: zwiadu nie robię, `plugin-check` wchodzi do L5 |
| 5 | pętla kalibracji bez dna (C1, C2 #7, C4 #1, G D9) | P | licznik `proby` w tabeli rozliczenia; max 1 poprawka; drugi FAIL = soczewka „niekalibrowana", jej „czysto" nie liczy się, mutant sprawdzany jako wadliwy |
| 6 | M1 niewidoczny dla lekturowego L1 (C1, C4 #3) | P | M1 → L3; L1 dostaje mutanta czytelnego w kodzie (S-09: nieudane logowania przestają być zapisywane) + Mailpit tylko-odczyt |
| 7 | M2 zasłonięty przez nonce (C1) | P | L2 dostaje konto subscriber + przepis na nonce (`wp eval`); oczekiwana KWALIFIKACJA: „nieeksploatowalne bez nonce, para złamana" |
| 8 | weryfikator bez kroków reprodukcji (C1) | P | znalezisko = teza + dowód + KROKI REPRODUKCJI (pole obowiązkowe); weryfikator dostaje je, nie kontekst czatu |
| 9 | reguły anty-pętla tylko tekstem (C1, C2, C3 #3/#9, C4 #2/#6/#9, G D9) | P/częściowo | mechanizowane: zamrożenie (tag + sha zipa w skrypcie), retry kalibracji, próby naprawy (pole w rejestrze, skrypt), mapa zamrażana po B; dyscyplina bez maszyny (jawnie): 2 rundy CI, 1 klasa = 1 PR |
| 10 | „0 anomalii bez wyjaśnienia" niemierzalne (C1, C2 #6, C3 #4, C4 #7) | P | definicja: anomalia = każde NIE SPRAWDZIŁEM / NIE DA SIĘ / nieoczekiwane wyjście → ID w rejestrze ze statusem; skrypt liczy OTWARTE |
| 11 | migracje jako podmiana numeru = fałszywy sukces (C1, C2 #4/#14, G D5, P3) | P | paczki starych wersji z gita (0.2.0 v3, 0.5.0 v4, 0.9.8 v5) → instalacja → dane → wgranie 0.9.13 „zastąp"; rollback: zachowanie ZDEFINIOWANE (nie wspieramy → kopia zapasowa w instrukcji) |
| 12 | zgodność z katalogiem WP.org / plugin-check / readme (C1, C2 #12/#15, C3 #1/#14, C4 #4/#14) | P/M | `plugin-check` oficjalny na 3 zipach (L5); NIE publikujemy na WP.org — do 02; WPCS już w CI (bramka phpcs); i18n = D21 → 03 |
| 13 | matrix zgodności: PHP 8.1 realnie, starszy WP, cache, nginx, multisite (C1 cache, C2 #11, C3 #2, C4 #12) | P/M | czysty stos instalacji od zera = obraz `wordpress:6.7-php8.1` (dwa cele naraz); page-cache + martwy nonce = cel Ł; nginx/multisite → 02/03 „nie wspieramy / notatka" |
| 14 | RODO pełny cykl: eksporter/eraser WP (C1, C2 #3) | D + P | cel L2 „jest/nie ma"; wdrożenie = decyzja zakresu właściciela (tanie: dwa hooki) |
| 15 | zwroty/chargeback/gwarancja — cała kategoria (C2 #1, G D3, P2) | P + D | cel L3 „co po zwrocie: status, link, KPI"; jak ma być = decyzja zakresu właściciela |
| 16 | Stripe: kolejność zdarzeń, 401/403/429, sekrety (rotacja, test→live) (C1, C2 #1/#10) | P | cele L3: `payment_status` asynchroniczny (BLIK), błędy ≥400 → co widzi człowiek, zły/pusty klucz = sygnał w panelu (klasa L4 botów), zmiana sekretu webhooka |
| 17 | inwentarz punktów wejścia generowany maszynowo (C2 #2) | P | krok 1: grep `admin_post_`, `register_rest_route`, `add_shortcode`, `wp_ajax_`, `add_rewrite_rule`, crony → tabela z właścicielem-soczewką w 07 |
| 18 | mutanty zbyt literalne / M7 bez wyroczni / M6 nie mierzy osądu (C2 #8/#9, C4 #5/#11, G DR1) | P | M3 = cron regeneruje token (inna manifestacja); M5 = obietnica behawioralna (HEAD nie zużywa limitu); M7 z wyrocznią (SELECT zdarzenia); M6 USUNIĘTY — L6 niekalibrowana (przyrządy + człowiek) |
| 19 | F1 „podwójny esc_html" nie jest nieszkodliwy (C4 #13) | P | F1 = zdublowane sprawdzenie uprawnień (nieszkodliwe, podejrzane) |
| 20 | L1 nadzbiór plików → przypadkowe trafienie cudzego mutanta (G DR2) | P | reguła: mutant trafiony przez cudzą soczewkę = znalezisko tej soczewki (unia), nie zmienia kalibracji soczewki przypisanej |
| 21 | prompty L5 i Ł nie istnieją jako tekst (G D6, R2) | P | pisane w kroku 1 PRZED rundą A; „jeden prompt = komplet" |
| 22 | gitleaks na pełnej historii + TODO/FIXME/notatki QA zgubione względem PLAN-PROJEKTU (G D7/D8) | P | L5: `gitleaks --log-opts=--all`; sweep = bramka `slady` (już łapie TODO/FIXME/NOTATKA QA) — nazwane wprost |
| 23 | bezpieczeństwo workflow (`pull_request_target`, akcje po SHA) (G kat.) | P | cel 4c w L5/L2 |
| 24 | lockout właściciela po pomyłkach (G kat.) | P | cel L2/Ł z perspektywy właściciela (sufit 60 s, brak twardej blokady — zmierzyć) |
| 25 | „Latest" na Releases (G kat.) | P | jawnie: klasa 4 ściągi zamykana w K8, nie w K7 (zapis w §1) |
| 26 | wydajność/degradacja (C2 #13, C3 #6, C4 kat.) | P/M | 1 cel Ł: seed 10 k wierszy dziennika + 1 k zamówień → czas pulpitu, brak N+1; Z6 do 02 jako założenie |
| 27 | a11y ręczna, cross-browser (C4 #8/#12) | P (małe) | 3 kroki klawiaturowe w przelocie człowieka; szelki także na firefox |
| 28 | skanery WPScan/ZAP/Semgrep (C3 #10) | O/opcja | WPScan bez wartości dla własnej wtyczki; ZAP = RAM; Semgrep opcjonalnie, jeśli instalacja < 15 min |
| 29 | niezależność rund A/B technicznie (C3 #7) | P (małe) | osobny katalog materiału B, `find` bez raportów A przed startem |
| 30 | „audyt nie naprawia" bez bramki (C3 #9) | P | audytor dostaje ROZPAKOWANĄ paczkę `chmod -R a-w`; po rundzie sha256 katalogu == zip |
| 31 | strażnik bez dowodu, że łapie (C3 #15) | P | pole rejestru `straznik_padl_na_starym: <sha>` wymagane przez skrypt (reguła CLAUDE.md repo) |
| 32 | zamrożenie vs PR kroku 0 (C4 #9) | P | zamrożony = merge PR-0; tag `k7-zamrozenie`; sha zipa w skrypcie |
| 33 | mapa rośnie w nieskończoność (C4 #10, C2 pętla 2) | P | nowe cele z A → do B; po B mapa ZAMROŻONA; nowe po B → „poza K7" (K8/hotfix) z decyzją właściciela |
| 34 | `prompty-audytorow.md` cytuje wycofaną liczbę (G DR3) | dług warsztatu | do CONTEXT-WARSZTAT |
| 35 | P5 strefa czasu (nikt) | P | cel L1/L5: „kiedy kupili" — gmdate vs czas lokalny w pulpicie/CSV/mailu |
