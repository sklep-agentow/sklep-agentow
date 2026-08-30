# PLUGIN CHECK NA PACZKACH — raport i triage, 2026-08-30

> Oficjalne narzędzie WordPress.org (`plugin-check` 2.1.0) uruchomione **na trzech paczkach, które
> dostaje klient**, w stosie próbnym `probny_wp` (WP 6.9.4 / PHP 8.4.21). Surowe wyniki:
> `audyt/plugin-check/<slug>.txt` (każdy zaczyna się wierszem z wersją paczki i stosu). Powtórzenie:
> `STOS_PROBNY_OBRAZ=wordpress:6.9.4-php8.4-apache bash narzedzia-qa/k7-stos-rundy.sh <zip>` (bez tej zmiennej
> `k7-stos-rundy.sh` stawia domyślny obraz 6.7/8.1), potem `bash narzedzia-qa/plugin-check-paczki.sh <zip> [<zip> ...]`.

## Skąd to się wzięło

Plugin Check stał w naszym szablonie CI od 9.08 (`github-wydania-klienta/szablony/quality.yml`),
a w tym repo nie było go nigdzie — `grep -rn -i "plugin.check" .github/ DECYZJE.md PLAN-PROJEKTU.md`
→ 0. Do CI nie wchodzi świadomie: wykonawca jest własny, na maszynie z 3,8 GB RAM, a akcja
`wordpress/plugin-check-action` stawia własny WordPress (wp-env) — drugi stos w CI obok pięciu
kontenerów to ryzyko OOM, które już raz ubiło sesje. Zamiast tego: przebieg w stosie próbnym,
na żądanie, z wynikiem w repo.

## Liczby (przebieg `plugin-check-paczki.sh` 1.1.0, 30.08 — każdy raport podpisany wersją paczki; skrypt odmawia zapisu, gdy wersja w kontenerze ≠ wersja z nazwy zipa)

```
sklep-agentow (0.9.22):     ERROR=5  WARNING=78
sklep-agentow (0.9.23):     ERROR=3  WARNING=80   ← powtórka 30.08 ~13:30 na zipie POBRANYM z Release v0.9.23; ubyły dwa `esc_html__` bez domeny (naprawa #213); zostały `move_uploaded_file` + 2× readme nie po angielsku (reguły katalogu WP.org, poza zakresem)
chatbot-live (1.5.13):      ERROR=2  WARNING=164
segregator-maila (1.3.6):   ERROR=2  WARNING=165
```

⛔ **Tych liczb nie porównuje się z zerem.** Plugin Check mierzy reguły KATALOGU WordPress.org
i nie czyta naszego `phpcs.xml.dist`. Poniżej każdy kod z werdyktem i dowodem.

## Triage — kod po kodzie

| Kod | Ile | Werdykt | Dowód |
|---|---|---|---|
| `WordPress.WP.I18n.MissingArgDomain` | 2 (sklep, `admin-opinie.php:16,120`) | ⭐ **JEDYNE ZNALEZISKO W KODZIE.** `esc_html__( 'Brak uprawnień.' )` bez domeny w wtyczce, która **nie tłumaczy niczego** (brak `Text Domain` w nagłówku, 0 innych wywołań `__`/`_e`). Niespójność, nie wada działania: string jest po polsku i tak się wyświetli | `grep -rhoE "\b(esc_html__\|__\|_e)\(" wtyczki/sklep-agentow --include=*.php \| sort \| uniq -c` → tylko `2 esc_html__(` |
| `Generic.PHP.ForbiddenFunctions.Found` (`move_uploaded_file`) | 1 (sklep, `admin-produkty.php:386`) | świadome, udokumentowane w kodzie | linia 385: `phpcs:ignore WordPress.WP.AlternativeFunctions -- katalog w uploads, przenosimy zaufany upload; WP_Filesystem zbędne`; cel to katalog CHRONIONY z losową nazwą bez rozszerzenia, `wp_handle_upload()` wrzuciłby plik do publicznego uploads |
| `readme_*_non_official_language` | 2 × 3 wtyczki | reguła katalogu WP.org (readme po angielsku) — **nie dotyczy** wtyczki dla polskiego klienta, która do katalogu nie idzie | źródło reguły w kolumnie `docs` raportu: make.wordpress.org 2025/07/28 |
| `PluginCheck.Security.DirectDB.UnescapedDBParameter` | 68 sklep, 43 chatbot, 4 segregator | fałszywy alarm na **nazwach tabel** `$t = $wpdb->prefix . 'sklepag_…'` — sniff nie odróżnia nazwy tabeli od danych; wartości wchodzą przez `$wpdb->prepare()` (bramka `phpcs` z `WordPress.DB.PreparedSQL` zielona w CI) | próbka: `admin-opinie.php:19` `$to = $wpdb->prefix . 'sklepag_opinie';`; `admin-dashboard.php:263` `$wpdb->prepare( $sql, $args )` |
| `WordPress.DB.DirectDatabaseQuery.*` (DirectQuery, NoCaching, SchemaChange) | 92 chatbot, 158 segregator | boty trzymają CAŁY stan we własnych tabelach — sniff doradczy wyłączony dla botów w `phpcs.xml.dist` (D25, 19.08, „wszystkie 69 trafień to własne tabele chatl_*/segm_*”) | `phpcs.xml.dist`, sekcja `WordPress.DB.DirectDatabaseQuery` z `exclude-pattern` |
| `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | 14 chatbot | własne sanitizery `chatl_waliduj_sesje()` (allowlist `^[a-zA-Z0-9-]{8,64}$`) i `chatl_sanitize_kolor()` — zarejestrowane w `phpcs.xml.dist` jako `customSanitizingFunctions`; Plugin Check ich nie zna | `front.php:483` `chatl_waliduj_sesje( wp_unslash( $_POST['sesja'] ) )` |
| `WordPress.Security.NonceVerification.Missing` | 11 chatbot | brama `chatl_wymagaj_nonce()` (woła `check_ajax_referer( …, false )` i sama kończy żądanie, żeby odnotować awarię dla właściciela — luka L1) — zarejestrowana jako `customNonceVerificationFunctions` | `phpcs.xml.dist`, komentarz przy `WordPress.Security.NonceVerification` |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | 9 (sklep `uninstall.php`) | zmienne lokalne w `uninstall.php` bez prefiksu — reguła katalogu przeciw kolizjom z innymi wtyczkami; `uninstall.php` ładuje się sam, w izolacji | styl, zero wpływu na działanie |
| `unexpected_markdown_file` | 5: 1 sklep (`DOKUMENTACJA-TECHNICZNA.md`), 2 chatbot i 2 segregator (`DOKUMENTACJA-TECHNICZNA.md` + `INSTRUKCJA.md`) | pliki `.md` w korzeniu wtyczki — świadomie, bo klient dostaje dokumentację i instrukcję razem z kodem, w tej samej paczce | zamówienie: dokumentacja w paczce |
| `PluginCheck.CodeAnalysis.AIProvider.DirectIntegration` | 3: 2 chatbot, 1 segregator | informacja katalogu o bezpośredniej integracji z dostawcą AI — to jest cel obu botów (tryb AI chatbota, klasyfikacja segregatora) | `chatbot-live.txt:161-162`, `segregator-maila.txt:145` |

**Suma kolumny „Ile” = 416** — tyle, ile dają surowe raporty:
`grep -hE $'\\t(ERROR|WARNING)\\t' audyt/plugin-check/*.txt | wc -l` → 416 (po naprawie po rundzie 1 recenzji:
pierwsza wersja tabeli gubiła 4 pozycje ChatBota w dwóch wierszach i sumowała się do 412).

## Co z tego wynika

- **Do naprawy w kodzie: jedno miejsce, zero wpływu na klienta** (`esc_html__` → `esc_html` w dwóch
  liniach). Nie idzie przed 1.09 samo — wymagałoby podbicia wersji, przebudowy paczki i rundy
  recenzji dla zmiany, której kupujący ani właściciel nie zobaczy. Zapisane w `CO-WISI.md`;
  wchodzi z pierwszą następną zmianą kodu sklepu.
- **Debug.log stosu próbnego po instalacji i aktywacji trzech wtyczek: brak pliku** (zero
  notice/warning/fatal) — to jest ta część Plugin Check, która mierzy działanie, nie styl.
- **Czego Plugin Check NIE widzi**, a my mamy: własny ruleset z powodami (D25), próby kontrolne
  strażników, przelot człowieka, test aktualizacji w miejscu. Plugin Check jest dodatkową parą oczu
  na regułach katalogu, nie zamiennikiem audytu K7.
