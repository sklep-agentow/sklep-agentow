# 02 — FAKTY ŚRODOWISKA (same dane, zero historii, zero ocen) — zmierzone 25.08.2026

> Bez tych faktów dostaniesz lawinę fałszywych alarmów o rzeczach, które w tym środowisku są w porządku.
> Wersje są ZMIERZONE na działających instancjach, nie przepisane z nagłówków.

## Dwa stosy
| | stos warsztatowy (`strona_wp_*`) | stos czysty (instalacja od zera) |
|---|---|---|
| adres | `http://localhost:8890` (kontener `strona_wp_wordpress`) | uruchamiany na czas testu, `probny_wp` (wzór `narzedzia-qa/lib-stos-probny.sh`) |
| WordPress | **7.1** (`wp core version`) | obraz `wordpress:6.7-php8.1-apache` = **deklarowane minimum** (6.7 / PHP 8.1) |
| PHP | **8.4.21** | 8.1 |
| serwer WWW | **Apache 2.4.67** — `.htaccess` DZIAŁA | Apache |
| baza | MariaDB 11.8.8 (`strona_wp_db`) | MariaDB |
| poczta | łapacz `sklep_mailpit` (:8025, API `http://localhost:8025/api/v1/messages`) — WordPress wysyła przez `wp_mail`, nic nie wychodzi w świat | jw. (ten sam łapacz) |
| Stripe | **TRYB TESTOWY**; ⚠️ zmierzone 26.08: opcje `sklepag_stripe_klucz_sekretny` i `sklepag_stripe_webhook_sekret` na :8890 są PUSTE (LENGTH=0) — start zakupu = 502 `checkout_blad`, webhook testujesz własnym sekretem; webhooki doręcza `stripe listen --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook` (uruchamia koordynatorka) | jw. |
| indeksowanie | `blog_public=0` → `<meta name="robots" content="noindex">` i brak sitemapy — **USTAWIENIE STOSU, NIE ZGŁASZAJ** | jw. |
| motyw | kopia motywu gospodarza (Automatic AI) — nawigacja wpisana na sztywno w `header.php` | domyślny motyw WP |
| wtyczki obok | `chatbot-live` 1.5.11, `segregator-maila` 1.3.4 (produkty sklepu), `plugin-check` (narzędzie audytu) | tylko badana |

Konta na stosie warsztatowym: administrator `audytor-admin`, subscriber `audytor-sub` (hasła dostaje audytor od koordynatorki
w zmiennych środowiskowych, NIE w tym pliku). Logowanie przez `curl -c cookies.txt -d 'log=…&pwd=…' http://localhost:8890/wp-login.php`.
⚠️ Nonce WordPressa jest związany z sesją użytkownika: nonce wygenerowany `wp eval` w CLI NIE pasuje do sesji z ciasteczka;
nonce dla subscribera nie istnieje w żadnym ekranie panelu sklepu (ekrany wymagają `manage_options`) — to jest FAKT do
uwzględnienia w kwalifikacji, nie przeszkoda do obejścia.

Narzędzia dla soczewek wykonawczych: `docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html …` (wp-cli),
⛔ `wp db query` NIE działa w kontenerach (brak klienta mysql, zmierzone 26.08) — SQL: `docker exec strona_wp_db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb wordpress -e "…"'` (probny: baza `wp_probny`), `wp eval` / `wp eval-file` (wzór: `narzedzia-qa/test-php.sh`), `curl`, Playwright (`node testy/e2e/…`),
Stripe CLI (`stripe trigger`, `stripe listen`), Mailpit API, `narzedzia-qa/stos.sh` 1.3.1 (widzi tabele `wp_sklepag_*` — `tabele`, `wiersze wp_sklepag_zamowienia`; strażnik `stos-proba-kontrolna.sh`).
Wolumen docelowy (założenie Z6, PLAN-PROJEKTU §11): pojedyncze zakupy dziennie. Zasoby maszyny: 2 vCPU, 3,8 GB RAM — testy obciążeniowe z umiarem.

## Produkt — stan
Wtyczka `sklep-agentow` **0.9.13**, schemat bazy **5** (`SKLEPAG_WERSJA_SCHEMATU`), 7 tabel `wp_sklepag_*` (produkty, sesje_checkout,
zamowienia, zdarzenia, logowania, rate_limit, opinie). Migracje z wcześniejszych wersji istnieją (3 → 4 → 5). Paczki starych wersji
do testu aktualizacji: `narzedzia-qa/k7-stare-wersje.sh` → `/root/praca/zlecenia/wymiana/k7-stare-wersje/` (0.2.0, 0.5.0, 0.9.8).
Przyrząd aktualizacji w miejscu: `narzedzia-qa/dowod-aktualizacji.sh`. Sklep NIE jest publikowany na WordPress.org (readme.txt
w formacie WP jest dla właściciela). Boty są produktami sklepu (pliki ZIP) i mają własne audyty (`audyt/boty/`).

## Inwentarz punktów wejścia (grep 25.08; każdy ma soczewkę-właściciela)
| punkt | plik | właściciel |
|---|---|---|
| `admin_post_nopriv_sklepag_kup` + `admin_post_sklepag_kup` (rozpoczęcie zakupu; nonce `sklepag_kup` + zgoda + limit) | `includes/zakup.php:62-66` | L2, L3 |
| `admin_post_nopriv_sklepag_pobierz` + `admin_post_sklepag_pobierz` (wydanie pliku) | `includes/pobieranie.php` | L2, L3 |
| `admin_post_nopriv_sklepag_ocen` (GET formularz) · `…_ocen_zapisz` (POST) + wersje `admin_post_` | `includes/opinie.php` | L2 |
| panel: `sklepag_produkt_zapisz` · `sklepag_produkt_przelacz` · `sklepag_resend` · `sklepag_anonimizuj` · `sklepag_dociagnij` · `sklepag_eksport_csv` · `sklepag_opinia_moderuj` | `includes/admin*.php`, `opinie.php` | L2 (para nonce+uprawnienia), L3 (resend/dociagnij) |
| REST `sklepag/v1/webhook` (POST, podpis Stripe) · `sklepag/v1/status` (GET, id sesji) | `includes/zakup.php:146-160` | L2, L3 |
| shortcode `[sklepag_sklep]`, `[sklepag_dziekujemy]` | `sklep-agentow.php` | L1, L6 |
| rewrite `/sklep/produkt/<slug>/` + `query_vars` | `includes/front-produkt.php:45` | L1, L6, Ł |
| cron `sklepag_dociagnij_zalegle` co 600 s (`sklepag_10min`) + retencja dziennika w tym samym przebiegu | `sklep-agentow.php:43-61`, `includes/zakup.php:532-535` | L3, L4 |
| hooki logowania `wp_authenticate`, `wp_login`, `wp_login_failed` | `includes/logowania.php` | L2, L1 (S-09) |
| aktywacja / deaktywacja / `uninstall.php` | `sklep-agentow.php`, `includes/aktywacja.php`, `uninstall.php` | L4 |
| menu `sklepag`, `sklepag-produkty`, `sklepag-opinie`, `sklepag-dziennik`, `sklepag-ustawienia`, `sklepag-zamowienie` (ukryte) | `includes/admin.php:47-57` | L1, L6 |
Brak: AJAX (`wp_ajax_*`), REST poza dwiema trasami, `localStorage`.

## STAN ZASTANY, zmierzony 25.08 — NIE zgłaszaj jako brak (możesz zgłosić, jeśli WYKONANIEM pokażesz, że fakt jest nieprawdziwy)
- Stripe bez SDK, bez zależności zewnętrznych, bez CDN: `wp_remote_post` (`includes/stripe.php`); nagłówek `Stripe-Version` przypięty (`stripe.php:31`).
- Wszystkie funkcje z prefiksem `sklepag_`; `ABSPATH` w każdym pliku PHP; `WP_UNINSTALL_PLUGIN` w `uninstall.php:11`.
- `get_charset_collate()` w dbDelta (`includes/aktywacja.php`); `flush_rewrite_rules` przy aktywacji i deaktywacji (`aktywacja.php:151,156`).
- Assety panelu ładowane tylko na ekranach `sklepag` (`includes/admin.php:96`); assety frontu tylko na stronach sklepu (D20).
- `payment_status` metod asynchronicznych obsłużony (`zakup.php:182-184, 493, 523`) — CEL L3-18 to TEST tego, nie pytanie „czy jest".
- Błędy Stripe HTTP ≥ 400 → `WP_Error` (`stripe.php:55-62`) — CEL L3-15 to „co widzi człowiek", nie „czy kod sprawdza kod".
- Historia commitów: 289/289 adresów `noreply` na `5d3dcf9` (`git log 5d3dcf9 --format=%ae | grep -vc noreply` → 0; klasa K2 DESTYLATU czysta).
- Retencja dziennika 90 dni w kodzie (`zakup.php:535`, cron) z testem `testy/dziennik-test.js` §D.
- Limity: kup 10 / status 30 / login 6 na okno (`zakup.php:20,70,442`, `logowania.php:94`) — CEL L2-08 = ZMIERZYĆ okno i zachowanie za proxy.

## Rzeczy żyjące w PROCEDURZE, nie w kodzie — nie orzekaj „BRAK w kodzie"
Nagłówki HTTP bezpieczeństwa (serwer, K8) · SMTP/dostarczalność (hosting) · klucze `sk_live_` i webhook produkcyjny (K8) ·
Regulamin i Polityka = SZABLONY do wypełnienia przez właściciela (D-PLAN §8) · reguła nginx dla katalogu chronionego = instrukcja
(Z10) · link „Sklep" w menu motywu = instrukcja (D28) · „Zniechęcaj wyszukiwarki" wyłącza właściciel (K8).

## Zaakceptowane odstępstwa (decyzje właściciela — szczegóły w `03-zalozenia.md`)
Brak kont kupujących · brak faktur · brak przecen i kuponów · brak i18n (D21) · brak multisite · tylko `manage_options`
(4 konta administratorów) · gwarancja 14 dni bez mechanizmu w kodzie (D31 — CEL L3-19 sprawdza, co się dzieje po zwrocie; to jest
pytanie, nie z góry błąd) · 3 pliki mediów nieużywane na stronach zostają w paczce 0.9.13 (kasowanie zatwierdzone na rundę napraw).

## Znane, zapisane PRZED audytem (odkrycie = zbieżność, nie nowość; i tak wpisz do raportu)
DOK-TECH §3 „7 tabel" vs §8 „6 tabel" · „#170" w DOK-TECH §8 · tabela DOK-TECH §2 bez `opinie.php`/`admin-opinie.php` ·
formularz kupna gościa wymaga nonce (`zakup.php:66`) → pytanie o page-cache (L2-13) · brak `wp_privacy_personal_data_*` (L2-18) ·
instrukcja milczy, że WP-Cron wymaga ruchu na stronie (L4-12) · (L4-14: okno `stos.sh` na tabele sklepu ZAMKNIĘTE w 1.3.1 — audytor potwierdza wykonaniem).
