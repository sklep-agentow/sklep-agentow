# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)

## ✅ ZAMKNIĘTE 19.08 (dzień) — DROBNICA recenzji #40/#42 (PR #57)
- **Rate-limit sklepu → LAST_INSERT_ID** (#40): osobny SELECT licznik miał
  mikro-okno wyścigu → wzorzec 1:1 z chatl_rate_limit; komentarz ostrzegawczy
  o fail-open dołożony (uwaga recenzji #57).
- **Test dziennika/opóźnienia** (#42): `testy/dziennik-test.js` na żywym stacku —
  salwa/2^n/reset/retencja/konkrety S-09, próba kontrolna (próg 999 → pada).
- **Indeks okno_do chatbota** nazwany w PONAD-SPECYFIKACJE-BOTY (klasa #6).
- **Zipy produktów na stacku podmienione na 1.2.1** przez PRAWDZIWY CRUD panelu
  (sha bazy == sha zipów, zmierzone; snapshot zam#29 nietknięty — stare zamówienia
  trzymają swój plik, zgodnie z projektem).

## ✅ ZAMKNIĘTE 19.08 (dzień) — ⭐ PAKIET BOTÓW (D25, przyciągnięty z 23.08), boty 1.2.1
- **phpcs na WSZYSTKIE 3 wtyczki:** phpcbf (58 auto-napraw, diff obejrzany — zero zmian
  semantyki) + 3 adnotacje inline z uzasadnieniem + `customSanitizingFunctions`
  (chatl_waliduj_sesje) + sniff doradczy DirectDatabaseQuery wyłączony dla botów JEDNĄ
  deklaracją w ruleset (uzupełnienie D25 — boty w 100% na własnych tabelach; PreparedSQL
  aktywny wszędzie). Zakres bramki potwierdzony PRÓBĄ KONTROLNĄ (podłożony var_dump
  w bocie = czerwone). phpcs EXIT 0, phpstan EXIT 0.
- **Widget chatbota przemalowany na paletę strony** (top-uwaga sędziego K5): limonka
  #bfff38 / tusz #08090b / węgiel #101116, dymki 1:1 z mockupem strony produktu,
  hover-pierścień jak CTA sklepu, obwódka bąbelka pod WCAG 1.4.11 na jasnych stronach;
  zrzuty na stacku gospodarza ORAZ na czystym WP; kolory zmierzone z żywego DOM.
- **WROGA SESJA BOTÓW na czystym WP** (audyt/WROGA-SESJA-BOTY.md): aktywacja bez
  jednego notice (WP_DEBUG_LOG), seedy komplet, cykl deakt→akt bez strat, przebieg
  segregatora działa, pełne czyszczenie = zero tabel/opcji/cronów. 🔴→✅ ZNALEZISKO
  klasy #1 ściągi: klucz API zostawał w bazie przy DOMYŚLNYM uninstallu — naprawione
  (delete przed early-return, oba boty), zmierzone przed/po, INSTRUKCJE dok=kod,
  paczki 1.2.1 przebudowane + powtórka weryfikacji `diff -r` (zip == repo).

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5 WITRYNA KOMPLET, 3 PR-y (#51-#53), wersja 0.8.1
- **#51 fundament+lista** (wyspa z tokenami zmierzonymi z motywu; mono-etykiety,
  pille, baner-pigułka; H1 nasz — motyw nie renderuje własnego, zmierzone) ·
  **#52 strona produktu+SEO** (jedna strona WP + query var; rewrite ze sluga
  z opcji; sekcje §2B komplet: mockup czatu/PRZED-PO/kroki/dowody/uczciwość/FAQ;
  SEO: title/desc/canonical/OG/JSON-LD Product+Breadcrumb+FAQPage/okładki PNG/
  noindex-dziękujemy/sitemap provider [nazwa BEZ myślnika — regex rdzenia];
  hijack metadanych query varem znaleziony recenzją, zamknięty Z POMIAREM;
  PEŁNY przelot E2E po zmianie: mail_wyslany) · **#53 szlif po sędzi** (ikony
  SVG kart, jawne „nie zmyślamy recenzji", FAQ separatory, kropka-glow).
- **Sędzia wyglądu: 73/100 → 85/100** (dwa świeże przebiegi, przynęty rozbrojone
  w OBU; 2 zaniżenia rundy 2 = artefakty zestawu zrzutów, odrzucone z dowodem;
  PEŁNE werdykty obu rund: komentarze w PR #53 — rekomendacja recenzji #54).
- Cross-browser 6/6 (chromium+firefox, zero błędów konsoli) · zagadka sitemap
  ROZWIĄZANA: blog_public=0 wyłącza sitemapy w rdzeniu (nie usterka!) → wpis
  do instrukcji K8: na produkcji wyłączyć „Zniechęcaj wyszukiwarki".
- Na stacku doszedł REALNY produkt „Segregator maila" (zip 1.2.0) — sklep ma
  docelowe 2 produkty.

## ✅ ZAMKNIĘTE 19.08
- **Uwagi recenzji #49 → PR #50 MERGED (main=19dbcc4, 0.6.1):** dok=kod (.bin
  usunięte + sweep całe repo: seed-demo, adnotacja hist. w K2-WROGA-SESJA) ·
  wynik $wpdb->insert/update sprawdzany + sierota sprzątana (dowód na żywo:
  wymuszona awaria INSERT → błąd, nie fałszywy sukces; plików 4→4) · kryterium #6
  W TEŚCIE produkty-test.js (9/9, z próbą kontrolną „edycja zaszła") · ścieżka
  zrzutu przez env. Recenzent potwierdził wszystko 2× + własny grep sweepu.
- **K5 plan wyglądu:** zwiad motywu (paleta/typografia/detale zmierzone) + zwiad
  wzorców stron produktowych (subagent, źródła w PLAN-WYGLADU-K5.md §5) →
  `PLAN-WYGLADU-K5.md` CZEKA NA KLEPNIĘCIE Dzidka przed budową.

## ✅ ZAMKNIĘTE 18.08 (dzień)
- Model AI botów haiku-4-5 (Dzidek) · timeout AI #37 · **oba boty scalone z haiku**
  (#36 chatbot, #37 segregator) · recenzent zna WP+ściągę+Handbook (#38) · hardening
  uninstall wg Handbook (#39) · chatbot atomowy rate-limit (#40) · **paczki 1.2.0
  zweryfikowane (haiku + finalny kod w zipie)** (#35). ZERO otwartych PR.
- **K2 wroga sesja sklepu — 7 ataków obronionych** (audyt/K2-WROGA-SESJA.md).

## ✅ ZAMKNIĘTE 18.08 (wieczór) — ⭐ K3 KOMPLET, 7 PR-ów (#41-#47), wersja 0.5.1
- **#41 bramki CI PHP** (php-lint 8.4 · phpcs WPCS[sklep, boty→23.08 D25] · phpstan L5
  wszystkie wtyczki · gitleaks; manifest +4; E2E przelot2.js uratowany do repo).
- **#42 dziennik logowań** (S-09; opóźnienie wykładnicze ZMIERZONE 0.3→2.3→4.3→8.3 s,
  reset po sukcesie; limit 6/min per IP PRZED sleep — salwa 12: 6×200+6×429 w 0.1 s;
  retencja 90 dni; schemat v3) · **#43 ustawienia** (Settings API; sekrety autoload=no
  TAKŻE przy update-in-place; uninstall kasuje sekrety ZAWSZE — klasa #1 ściągi;
  nadawca wokół naszego wp_mail) · **#44 dashboard** (KPI+delta %, rozkład, tabela,
  CSV, self-heal Z BUDŻETEM CZASU 8/15 s; suma słupków = KPI co do grosza) ·
  **#45 zamówienie** (mail+resend ze świeżym tokenem [stary 403/nowy 200],
  anonimizacja RODO — schemat v4) · **#46 fix ukrytej strony** (bug złapany ZRZUTEM —
  wp eval go nie widział) · **#47 szlif po sędzi wyglądu**.
- **Sędzia wyglądu #1: 66/100** (przynęty rozbrojone — wiarygodny); TOP-braki
  zaadresowane w #47 (pasek statusów, kolumna Akcje, notice, hierarchia KPI,
  zrzuty-dowody); świadome odstępstwa w WZORCE-DASHBOARD-K3 §8. Sędzia #2 w toku.
- Zrzuty panelu: scratchpad sesji `zrzuty/` (10 szt.); skrypt `testy/zrzuty-panelu.js`.

## Blokuje wydanie
- (nic pilnego) — Live-smoke AI na żywym api.anthropic.com w ODWODZIE (~5$ za zgodą
  Dzidka), gdyby próba 1 chciała żywe AI; egzamin trybem regułowym 0 zł.

## Nie zaczęte (kolejność planu)
- **Nazwany dług (recenzja #49, drobna):** przy podmianie zipa na produkcie BEZ
  zamówień stary plik zostaje w katalogu chronionym (nie szkodzi, nie wycieka —
  katalog deny; sprzątanie wymagałoby odróżniania „trzymany snapshotem" od
  „wolny"). Świadomie NIE sprzątamy w v1 → wpis do `PONAD-SPECYFIKACJE.md` w K8.
- **Świadomie NIE ruszone (recenzja #40, #3):** default `$prog` w `chatl_rate_limit`
  — sklep ma default 10, bot nie; jedyne wywołanie w bocie podaje oba argumenty,
  recenzent oznaczył jako nieblokujące. Zostaje bez zmian celowo.
- **Instrukcja wdrożenia (K8): wzmianka fail2ban/WAF** na wp-login.php przy wielu IP
  (D26 — rezydualne ryzyko poza wtyczką).
- K6 24.08 · K7 25.08 · K8 27.08 = próba 1.

## Stan maszyny
- `stripe listen` forwarduje na :8890/webhook (sekret z `/root/.claude/sekrety-sklep.env`).
  Start po padzie: `set -a; . /root/.claude/sekrety-sklep.env; set +a; stripe listen --api-key "$STRIPE_SECRET_KEY" --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook`
- Stack: `strona_wp_wordpress` (:8890), `strona_wp_db`, `sklep_mailpit` (:8025).
- Builder1: tmux praca:1, worktree sklep-agentow-boty, stoi (boty domknięte).
