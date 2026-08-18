# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)

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
- **K4 CRUD produktów** (upload zip chroniony + walidacje + snapshot nietykalny).
  ⭐ WYTYCZNA DZIDKA do K5: front sklepu MUSI pasować do KLIMATU strony-gospodarza;
  przed budową witryny POWIEDZIEĆ Dzidkowi plan wyglądu (skille frontend-design +
  dzial-agencji-stron + wzorce stron produktowych).
- **K5 witryna sklepu** (wzorce stron produktowych → treść ze SZKICÓW botów).
- **Drobne hardening (nie blokujące, z recenzji #40):** sklep rate-limit → LAST_INSERT_ID
  (mikro-okno, błądzi bezpiecznie); indeks okno_do nazwać rozszerzeniem; $prog domyślny.
- ~~Skrypt E2E przelot2.js do repo~~ ✅ 18.08 PR ci/bramki-php: `testy/e2e/przelot2.js`
  + README (node_modules poza repo).
- **Boty ↔ phpcs przy wrogiej sesji 23.08 (D25):** phpcbf + adnotacje + w phpcs.xml.dist
  `customSanitizingFunctions` (chatl_waliduj_sesje) + rozszerzyć `<file>` na boty +
  przebudowa paczek + POWTÓRKA weryfikacji zipów. 5 trafień sniffa security obejrzane
  18.08 = szum (sanitizer-regex, klucz API sanityzowany przy zapisie), 0 realnych dziur.
- **Test automatyczny dziennika/opóźnienia** (recenzja #42, drobna): skrypt na żywym
  stacku (sekwencja czasów + salwa równoległa + retencja) — dołożyć przy K3c/d razem
  ze skryptem testowym panelu, żeby regresja (np. kolejność hooków) nie przeszła cicho.
- **Instrukcja wdrożenia (K8): wzmianka fail2ban/WAF** na wp-login.php przy wielu IP
  (D26 — rezydualne ryzyko poza wtyczką).
- Wroga sesja botów 23.08 · K6 24.08 · K7 25.08 · K8 27.08 = próba 1.

## Stan maszyny
- `stripe listen` forwarduje na :8890/webhook (sekret z `/root/.claude/sekrety-sklep.env`).
  Start po padzie: `set -a; . /root/.claude/sekrety-sklep.env; set +a; stripe listen --api-key "$STRIPE_SECRET_KEY" --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook`
- Stack: `strona_wp_wordpress` (:8890), `strona_wp_db`, `sklep_mailpit` (:8025).
- Builder1: tmux praca:1, worktree sklep-agentow-boty, stoi (boty domknięte).
