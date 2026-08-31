# BRIEFING BUILDERA — BOTY (ChatBot LIVE + Segregator maila)

Jesteś builderem botów przy zleceniu sklep-agentow (egzamin, termin: boty GOTOWE
23.08 wieczorem — wroga sesja). Koordynatorka: koordynatorka (okno główne tmux `praca`).
⭐ **NIE czekasz na zgodę między pozycjami:** kończysz → PR → meldunek JEDNYM
zdaniem → bierzesz następną. Pytasz TYLKO gdy robota wychodzi poza Twój moduł
albo zmienia zakres.

## GDZIE PRACUJESZ
- Worktree: `/root/praca/zlecenia/sklep-agentow-boty` (gałęzie `b1/...`).
  ⛔ TYLKO tam — nigdy w głównym klonie.
- Repo: `szydlowskikonrad95-art/sklep-agentow`, base branch `main`.

## GRANICA MODUŁU (podział po modułach — kolizja ma być NIEMOŻLIWA)
- ✅ Twoje: `wtyczki/chatbot-live/` · `wtyczki/segregator-maila/` · `boty/` (dokumenty).
- ⛔ NIE dotykasz: wtyczki sklepu, `PLAN-PROJEKTU.md`, `MAPA-SPEC.md`, `DECYZJE.md`,
  `ZROZUMIENIE.md`, `.github/` (workflow), `audyt/`. Potrzeba zmiany tam = STOP, pytaj.
- ⛔ NIE scalasz PR-ów sam — scala koordynatorka po zimnej weryfikacji.
- ⛔ `git add -A` ZAKAZANE — dodajesz pliki PO NAZWIE (lekcja: cudzy plik w commicie).

## SPECYFIKACJA (przeczytaj OBA w całości przed P1)
1. `boty/SZKICE-BOTOW.md` — CO budujesz (klepnięte przez właściciela; HYBRYDA: silnik
   regułowy bez klucza + prawdziwe AI z kluczem właściciela).
2. `boty/WZORCE-BOTOW.md` — standard dziedziny (stały przycisk kontaktu, próg
   pewności, accuracy w raporcie, deklaracja prywatności) + czego unikać.

## POZYCJE (kolejność; jedna pozycja = jeden PR)
- **P1** `chatbot-live` szkielet + tryb regułowy: aktywacja (dbDelta, wersja
  schematu, seed 10 par Q&A PL), widget (bąbelek, okno, stały przycisk „zostaw
  wiadomość"), dopasowanie podłańcuchów po wariantach słów, zgłoszenia + historia
  rozmów (retencja 90 dni), admin (CRUD Q&A, zgłoszenia, historia, ustawienia),
  uninstall z opcją czyszczenia.
- **P2** `chatbot-live` tryb AI (hybryda): pole klucza (opcja `autoload=no`),
  wywołanie API przez `wp_remote_post`, instrukcja systemowa z progiem pewności
  (niepewny → zbierz kontakt, ZAKAZ zgadywania), stop-loss (dzienny limit +
  licznik w adminie), zjazd do reguł przy błędzie/timeout/braku klucza.
  ⛔ PRZED integracją odpal skill `claude-api` — model i parametry z docs, nie z pamięci.
- **P3** `segregator-maila` szkielet + reguły: kategorie CRUD, reguły CRUD
  (kolejność = pierwsza wygrywa, BEZ pola priorytet), próbka demo ~30 maili PL,
  import CSV (od/temat/treść [+opcjonalnie kategoria_oczekiwana]), przebieg,
  raport (trafienia per kategoria, % pokrycia, ACCURACY + pomyłki przy
  etykietach), eksport CSV, uninstall.
- **P4** `segregator-maila` tryb AI: klasyfikacja modelem + tryb „porównaj
  reguły vs AI" (raport różnic); ten sam stop-loss co P2.
- **P5** instrukcje PL obu wtyczek (instalacja → konfiguracja w kwadrans → FAQ →
  droga rozbudowy → DEKLARACJA PRYWATNOŚCI: reguły=lokalnie, AI=treść do
  dostawcy) + `zip` paczek (zip = produkt sklepu).

## ZASADY RZEMIOSŁA (twarde, z planu v3 i lekcji MP)
WP 7.0 / PHP 8.4 (stack testowy) · prefiksy `chatl_` / `segm_` · nowa tabela =
3 miejsca (dbDelta + uninstall + ścieżka upgrade) · nonce + capability na KAŻDEJ
mutacji · `$wpdb->prepare()` wszędzie · sanitize wejść / escape wyjść — czat
i CSV to wejście NIEZAUFANE (XSS!) · teksty PL wprost, ZERO i18n/textdomain ·
assety tylko tam gdzie bot działa + bump wersji przy każdej zmianie JS/CSS ·
zero zewnętrznych CDN/zależności · sekrety nigdy w repo/logach (gitleaks w CI) ·
symetria zapis↔sprzątanie W TYM SAMYM PR · uninstall: dane zostają domyślnie,
opcja pełnego czyszczenia.

## CYKL PRACY
branch `b1/pozycja` → build → TEST na żywym stacku → commit (Conventional
Commits: feat/fix/docs) → PR z sekcjami: **Co i dlaczego / Jak to sprawdziłem
(konkretne komendy + WYNIKI — dowód, nie opis; ile kontroli pada na kodzie
sprzed zmiany) / Uwagi** → meldunek → następna pozycja.

## ŚRODOWISKO TESTOWE
- Stack `strona_wp_*` na `localhost:8890` (WP 7.0.4, PHP 8.4, Apache, MariaDB) —
  wtyczki testujesz TU (docker cp / volume; wp-cli w kontenerze przez
  `docker exec strona_wp_wordpress wp ... --allow-root`).
- Maile łapie mailpit: UI `localhost:8025` (kontener `sklep_mailpit`).
- ⛔ Kontenerów NIE kasujesz i NIE restartujesz bez pytania.
- ⛔ RAM 3,8 GB na CAŁĄ maszynę: ciężkie przebiegi (pełne e2e, instalacje paczek)
  NIE równolegle z koordynatorką — przed ciężkim przebiegiem zapytaj.
- `WP_DEBUG` włączony przy testach: zero notice'ów = warunek oddania pozycji.

## ŹRÓDŁA OFICJALNE (drabina: gotowce → OFICJALNE docs → GitHub; nazwa z pamięci
## modelu = HIPOTEZA do sprawdzenia w źródle, nie fakt)
- **WordPress Plugin Handbook** — developer.wordpress.org/plugins/ (basics,
  activation/deactivation, **uninstall = posprzątaj opcje/tabele/pliki**,
  Settings API, security/nonces). Lokalne destylaty:
  `/root/praca/warsztat/.claude/skills/wp-plugin-development/references/*.md`.
- **WordPress Coding Standards** — developer.wordpress.org/coding-standards/
  (styl PHP/JS; w CI pilnuje phpcs).
- **Plugin Directory Guidelines** — rygor recenzentów katalogu WP (jak przy MP).
- **REST API Handbook** — developer.wordpress.org/rest-api/ (gdyby bot
  potrzebował trasy; pamiętaj o `permission_callback`).
- **Anthropic API** (tryb AI, P2/P4): NAJPIERW skill `claude-api` — modele,
  parametry, ceny Z DOKUMENTACJI, nie z pamięci.
- MDN (JS widgetu) · OWASP (walidacja wejść, sekrety).
⛔ Blogi/StackOverflow = tylko trop; rozstrzyga oficjalne źródło.

## ⭐ ŚCIĄGA EGZAMINATORA (dopisane 18.08 — patrz audyt/SCIAGA-EGZAMINATORA.md;
## kolega wytykał te klasy w 2 poprzednich projektach, będzie szukał ZNOWU):
- Pełne czyszczenie w uninstall MUSI usuwać KLUCZ API właściciela (wytknięte ×2).
- Każdy SQL `LIKE`: wartości przez `$wpdb->esc_like()`, w stałych `\_` zamiast `_`
  (podkreślnik to wildcard — skasujesz cudze wpisy o podobnej nazwie).
- Liczniki/stany często zapisywane (stop-loss AI!) → do TABELI bota, NIE do wp_options.
- Timeout wywołań zewnętrznych (AI API): 15 s, JEDEN retry — nigdy pętla retry
  dłuższa niż limit PHP hostingu (30 s); licznik limitu zużywaj PO sukcesie, nie przed.
- Zero śladów wewnętrznych w paczce: notatek QA, TODO/FIXME, adresów dev.
- Każda LICZBA w instrukcji/README musi mieć pokrycie w kodzie (kolega porównuje).

## PUŁAPKI ŚRODOWISKA (sekcja rośnie — dopisuj własne znaleziska!)
- `wp_mail` w kontenerze bez SMTP ginie bez śladu → na stacku jest mailpit.
- `__()`/tłumaczenia przy rejestracji wtyczki = notice od WP 6.7 → NIE używamy i18n.
- `add_option`/`INSERT IGNORE` chroni utworzenie wiersza, NIE przejścia stanu —
  mutex/stan przez atomowy `UPDATE ... WHERE stan=...` (CAS).
- Uprawnienia plików w Dockerze: proces PHP = www-data (33); przed pisaniem do
  katalogu zrób próbę kontrolną zapisu.
- (b1, 18.08) wp-cli NIE jest zainstalowane w kontenerze `strona_wp_wordpress` —
  dograny phar: `/usr/local/bin/wp` (v2.12.0); wywołanie z `--path=/var/www/html`.
- (b1, 18.08) mailpit NIE łapał maili z WP: (1) brak drogi SMTP → mu-plugin
  `wp-content/mu-plugins/mailpit-smtp.php` kieruje PHPMailer na mailpit;
  (2) PHPMailer ODRZUCA host z podkreśleniem (`sklep_mailpit` = „Invalid host")
  → w mu-pluginie `gethostbyname("sklep_mailpit")`; (3) From `wordpress@localhost`
  (siteurl=localhost) też odrzucany → filtr `wp_mail_from` na poprawny adres.
- (b1, 18.08) WP_DEBUG był wyłączony w wp-config stacku — włączony przez
  `wp config set WP_DEBUG true --raw` (+ WP_DEBUG_LOG true, WP_DEBUG_DISPLAY false);
  log: `wp-content/debug.log`.
