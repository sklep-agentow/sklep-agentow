# sklep-agentow

**Trzy wtyczki WordPressa** (piwot z 17.08 — D16; wcześniejszy świat
Cloudflare tylko w historii gita): **SKLEP** (`sklep-agentow` — Stripe
Checkout w trybie TESTOWYM, mail po zakupie, wydanie plików bramą PHP,
panel w wp-admin) + 2 sprzedawane w nim testowe boty: **ChatBot LIVE**
(`chatbot-live`) i **Segregator maila** (`segregator-maila`).
Zero prawdziwych pieniędzy: wszystko na kluczach testowych Stripe.

Repo robocze zlecenia. Oddanie = zipy 3 wtyczek + instrukcja instalacji
(`dokumentacja/`); stack testowy: WP 7.0.4 / PHP 8.4 (kopia strony-gospodarza).

## Mapa dokumentów

| Plik | Co trzyma |
|---|---|
| `ZROZUMIENIE.md` | Treść zlecenia (verbatim), piwot na WP, fakty, MUST NOT |
| `MAPA-SPEC.md` | Wymagania S-01…S-20 — co dokładnie ma działać |
| `PLAN-PROJEKTU.md` | Plan v3: architektura (§4), bezpieczeństwo (§7), harmonogram (§9), kryteria GOTOWE (§12) |
| `DECYZJE.md` | Rejestr decyzji D1–D27 (z uzasadnieniami) |
| `PLAN-WYGLADU-K5.md` | Wygląd witryny: tokeny z motywu, układy, SEO (D27) |
| `boty/` | Szkice, wzorce, treść stron produktów, paczki zipów botów |
| `dokumentacja/` | Instrukcja instalacji dla właściciela strony |
| `audyt/` | CO-WISI (stan), REJESTR-WERYFIKACJI, wroga sesja K2, ściąga egzaminatora |
| `testy/` | Skrypty na żywy stack: E2E przelotu, CRUD produktów, seed demo, zrzuty |
| `wtyczki/` | Kod 3 wtyczek |
| `krytyki/` | Rundy krytyki planu |
| `narzedzia-qa/` | Strażnicy CI (manifest bramek) |

## CI

Po sklonowaniu, JEDNORAZOWO: `git config core.hooksPath .githooks` — włącza strażnika
pre-push (blokada bezpośredniego pusha na `main`; GitHub free+private nie daje branch
protection, szczegóły w nagłówku `quality.yml`).

- `quality.yml` — php-lint (8.4) + phpcs (WPCS) + phpstan (L5) + gitleaks +
  actionlint + strażnik manifestu bramek (scalamy TYLKO po ich zieleni).
- `recenzent.yml` — recenzja Claude na każdym PR (zasada dowodu: opis PR musi
  zawierać dowód działania, nie deklarację).

## Status

K1–K5 domknięte (kość → wroga sesja → panel wp-admin → CRUD produktów →
witryna sklepu z SEO; sklep 0.8.1, boty 1.2.0). Przed nami: pakiet botów
i wroga sesja botów (23.08) → instalacja od zera na czystym WP (K6, 24.08) →
audyt końcowy (K7) → paczka i próba oddania nr 1 (K8, 27.08).
Bieżący stan i wiszące sprawy: `audyt/CO-WISI.md`.
