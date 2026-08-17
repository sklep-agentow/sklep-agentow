# sklep-agentow

Wpinana podstrona **/sklep** dla stron statycznych + zaplecze sprzedaży produktów
cyfrowych (agenci AI). Stack: statyczny HTML + Cloudflare Worker + D1 + R2,
płatności Stripe Checkout (tryb TESTOWY — zero prawdziwych pieniędzy).

Repo robocze zlecenia. Oddanie = paczka wpinana/odpinana + instrukcja dla
właściciela strony (konfigurowalna per strona: 4 wdrożenia docelowe).

## Mapa dokumentów

| Plik | Co trzyma |
|---|---|
| `ZROZUMIENIE.md` | Treść zlecenia (verbatim), fakty, MUST NOT, ryzyka |
| `MAPA-SPEC.md` | Wymagania S-01…S-16 — co dokładnie ma działać |
| `PLAN-PROJEKTU.md` | Architektura (§4), bezpieczeństwo (§7), harmonogram (§9), kryteria GOTOWE (§12) |
| `DECYZJE.md` | Rejestr decyzji D1–D15 (z uzasadnieniami) |
| `audyt/` | REJESTR-WERYFIKACJI — dowody: co sprawdzone, czym, z wynikiem |
| `PLAN-ETAP1.md` | Etap bieżący + rejestr rozstrzygnięć niejasności |
| `KONFIG-TESTOWA.md` | Konfiguracja testowa Stripe (klucz publiczny; sekrety POZA repo) |
| `ZWIAD-WZORCE.md` | Zwiad: jak takie sklepy robią najlepsi |
| `krytyki/` | Rundy krytyki planu (6 recenzji, mapa zbieżności) |
| `narzedzia-qa/` | Strażnicy CI (manifest bramek) |

## CI

Po sklonowaniu, JEDNORAZOWO: `git config core.hooksPath .githooks` — włącza strażnika
pre-push (blokada bezpośredniego pusha na `main`; GitHub free+private nie daje branch
protection, szczegóły w nagłówku `quality.yml`).

- `quality.yml` — actionlint + strażnik manifestu bramek (scalamy TYLKO po ich zieleni).
- `recenzent.yml` — recenzja Claude na każdym PR (zasada dowodu: opis PR musi
  zawierać dowód działania, nie deklarację).

## Status

Etap 1 (plan + spike). Spike K0 zaliczony: wpięcie w zbudowaną stronę
statyczną = katalog `sklep/` + `sklep/wtyczka.js` + jedna linijka `<script src>`.
