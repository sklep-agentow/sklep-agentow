# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)

## Rozstrzygnięte 18.08
- **Model AI botów: haiku-4-5** (`claude-haiku-4-5-20251001`) — KLEPNIĘTE przez
  Dzidka 18.08. Opus zostaje dostępny filtrem (chatl_ai_model / segm_ai_model).
  Builder zmienia default w obu botach (opus-5 → haiku) + przebudowa zipów.
- **Timeout AI (#37 WAŻNA): ZROBIONE** — builder b848ed7: tryby AI/porównaj mają
  twardy limit = 1 wywołanie API na przebieg (max 18 s < 30 s PHP).

## Blokuje wydanie
- **Live-smoke AI na żywym api.anthropic.com** — Dzidek zaproponował darmowy klucz
  Gemini; NIE zadziała: boty gadają WYŁĄCZNIE z api.anthropic (kod ai.php pod
  Messages API, D19 + zasada jednego źródła Anthropic), Gemini = inny dostawca/
  format. Klucza Gemini i tak NIE MA na maszynie (sprawdzone). DECYZJA: na egzamin
  idziemy trybem REGUŁOWYM bez klucza (0 zł, plan §14), kształt żądania Anthropic
  potwierdzony źródłem w #31; live-smoke (~5$ na klucz Anthropic) w ODWODZIE gdyby
  próba 1 pokazała, że kolega chce zobaczyć żywe AI.

## W toku
- **Builder1: allowlist w boty/buduj-paczki.sh (#35)** — zlecone 18.08 ~05:30;
  dowód: podłożony śmiec ma wywalić budowę.
- **Builder1: timeout AI segregatora (#37, WAŻNA recenzji)** — 500 wierszy × tryb AI
  = ~450 s ≫ limit PHP. Naprawa na b1/segregator-ai: twardy niższy limit próbki
  dla AI/porównaj lub jawny błąd zamiast cichego fatala + test granicy. Drobne:
  etykieta kolumny „Złapane regułami" przy trybie AI, escaping `"` w nazwie
  kategorii w prompcie. ⭐ KOLEJNOŚĆ ZIPÓW: timeout PRZED przebudową zipów #35
  (kod ai.php się zmieni) — budować zipy RAZ, po timeout+allowliście.
- **#37 (P4 AI, następca #34)** — recenzja pass; NIE scalać: (a) WAŻNA wyżej,
  (b) decyzja Dzidka o modelu, (c) live-smoke. Scalanie BEZ kasowania gałęzi,
  potem przeceluj #35 na main, dopiero wtedy kasuj b1/segregator-ai.

## Nie zaczęte
- **K2 wroga sesja sklepu** (ja) — lista ataków w planie §9; przedtem pamięć
  INDEKS-styki-metoda.
- **Skrypt E2E przelot2.js do repo** — dziś żyje w scratchpadzie STAREJ sesji
  (`/tmp/claude-0/-root-praca-warsztat/4db7b593-.../scratchpad/e2e/`) — ULOTNE;
  przenieść do repo (np. `testy/e2e/`) przy K2, bo wroga sesja i tak będzie
  skryptowana. Node_modules NIE do repo — README z `npm i playwright-core`.
- **Wzorce dashboardów** przed K3 (wytyczna Dzidka „jak robią dobre sklepy").

## Stan maszyny
- `stripe listen` (PID ~337865) forwarduje na :8890/webhook — z sekretem z
  `/root/.claude/sekrety-sklep.env`; start po padzie:
  `set -a; . /root/.claude/sekrety-sklep.env; set +a; stripe listen --api-key "$STRIPE_SECRET_KEY" --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook`
- Stack: `strona_wp_wordpress` (:8890), `strona_wp_db`, `sklep_mailpit` (:8025).
- W bazie stacka: zamówienia testowe id=3,6,7 (mail_wyslany) — dane robocze, nie kasować bez powodu.
