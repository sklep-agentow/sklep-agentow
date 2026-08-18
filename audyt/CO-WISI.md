# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)

## ✅ ZAMKNIĘTE 18.08 (dzień)
- Model AI botów haiku-4-5 (Dzidek) · timeout AI #37 · **oba boty scalone z haiku**
  (#36 chatbot, #37 segregator) · recenzent zna WP+ściągę+Handbook (#38) · hardening
  uninstall wg Handbook (#39) · chatbot atomowy rate-limit (#40) · **paczki 1.2.0
  zweryfikowane (haiku + finalny kod w zipie)** (#35). ZERO otwartych PR.
- **K2 wroga sesja sklepu — 7 ataków obronionych** (audyt/K2-WROGA-SESJA.md).

## Blokuje wydanie
- (nic pilnego) — Live-smoke AI na żywym api.anthropic.com w ODWODZIE (~5$ za zgodą
  Dzidka), gdyby próba 1 chciała żywe AI; egzamin trybem regułowym 0 zł.

## Nie zaczęte (kolejność planu)
- **K3 PANEL wp-admin** — ⭐ NAJPIERW wzorce dashboardów dobrych sklepów (wytyczna
  Dzidka), potem budowa: dashboard+CSV, zamówienie+resend, dziennik logowań, ustawienia.
  Sędzia wyglądu 0-100 PRZED pokazem. GŁÓWNIE MOJA ROBOTA (sklep).
- **K4 CRUD produktów** (upload zip chroniony + walidacje + snapshot nietykalny).
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
