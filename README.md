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
| `PLAN-PROJEKTU.md` | Architektura, bezpieczeństwo (§6), kryteria GOTOWE (§9), harmonogram K0–K8 |
| `DECYZJE.md` | Rejestr decyzji D1–D15 (z uzasadnieniami) |
| `PLAN-ETAP1.md` | Etap bieżący + rejestr rozstrzygnięć niejasności |
| `KONFIG-TESTOWA.md` | Konfiguracja testowa Stripe (klucz publiczny; sekrety POZA repo) |
| `ZWIAD-WZORCE.md` | Zwiad: jak takie sklepy robią najlepsi |
| `krytyki/` | Rundy krytyki planu (6 recenzji, mapa zbieżności) |
| `narzedzia-qa/` | Strażnicy CI (manifest bramek) |

## CI

- `quality.yml` — actionlint + strażnik manifestu bramek (statusy wymagane na `main`).
- `recenzent.yml` — recenzja Claude na każdym PR (zasada dowodu: opis PR musi
  zawierać dowód działania, nie deklarację).

## Status

Etap 1 (plan + spike). Spike K0 zaliczony: wpięcie w zbudowaną stronę
statyczną = katalog `sklep/` + `sklep/wtyczka.js` + jedna linijka `<script src>`.
