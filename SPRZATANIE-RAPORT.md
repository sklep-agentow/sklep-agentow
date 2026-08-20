# Raport sprzątania — sklep-agentow (+ worktree sklep-agentow-boty)

Wygenerowano: 2026-08-18, tryb READ-ONLY (nic nie skasowano — decyzja u człowieka).

## Łączny rozmiar śmieci: ~195 MB (praktycznie w 100% to jeden katalog: `node_modules/`)

Dla porównania: cały katalog `sklep-agentow` waży 200 MB — czyli **śmieci to 97,5% wagi repo**.
Worktree `sklep-agentow-boty` jest czysty: 700 KB, `git status` = nothing to commit, brak node_modules, brak innych śmieci.

## 3 najpewniejsze do usunięcia

1. **`sklep-agentow/node_modules/`** (195 MB, cały katalog) — relikt architektury Cloudflare Workers
   (decyzja D1 z 16.08: „Worker+D1+R2"), którą **jawnie unieważniono** decyzją D16 (17.08 wieczór,
   `DECYZJE.md`: *„PIWOT NA WORDPRESSA ... Unieważnia D1 (Cloudflare)"*). Projekt jest teraz w 100%
   PHP/WordPress (patrz `wtyczki/*.php`, `PLAN-PROJEKTU.md` = „PLAN v3 (WordPress)"). Dowody że to sierota:
   - jest w `.gitignore` (`node_modules/`), nigdy nie śledzony przez git,
   - **w repo nie ma ŻADNEGO `package.json` / `package-lock.json` / `wrangler.toml`** — więc katalogu
     nie da się nawet odtworzyć przez `npm install`; ktoś zainstalował paczki Cloudflare ręcznie do prototypu,
   - mtime katalogu (17.08 22:24) pokrywa się z wieczorem pivotu na WordPress,
   - zero wzmianek o realnym użyciu w bieżącym planie v3.
2. **`node_modules/@cloudflare/workerd-linux-64/bin/workerd`** (119 MB) — pojedynczy plik odpowiada
   za ponad połowę wagi całego katalogu. To binarka runtime Cloudflare Workers.
3. **`node_modules/workerd/bin/workerd`** (119 MB, druga kopia tej samej binarki w innej paczce npm) —
   razem z punktem 2 to najcięższy pojedynczy koszt tego repo.

(Reszta node_modules — `wrangler` 17M, `miniflare` 19M, `@esbuild` 11M, `@img`/sharp 17M — to narzędzia
tego samego, porzuconego łańcucha Cloudflare/Workers; wypisane w tabeli zbiorczo.)

## Tabela

| Ścieżka | Rozmiar | Typ śmiecia | Status |
|---|---|---|---|
| `sklep-agentow/node_modules/` (cały katalog) | 195 MB | zależności npm dla porzuconej architektury Cloudflare Workers; gitignored, nieodtwarzalny (brak package.json) | **PEWNE do usunięcia** |
| `node_modules/@cloudflare/` | 120 MB | podkatalog node_modules — Cloudflare Workers runtime | PEWNE (część powyższego) |
| `node_modules/workerd/` | 4,6 MB | podkatalog node_modules — duplikat runtime workerd (drugi pakiet) | PEWNE (część powyższego) |
| `node_modules/wrangler/` | 17 MB | CLI Cloudflare Workers, nieużywany | PEWNE (część powyższego) |
| `node_modules/miniflare/` | 19 MB | lokalny emulator Cloudflare Workers, nieużywany | PEWNE (część powyższego) |
| `node_modules/@img/` (sharp) | 17 MB | binarka do przetwarzania obrazów (libvips), niepowiązana z WP-PHP | PEWNE (część powyższego) |
| `node_modules/@esbuild/` | 11 MB | bundler JS, niepowiązany z projektem PHP | PEWNE (część powyższego) |
| `audyt/CO-WISI.md` | 2 KB | plik zmodyfikowany, NIE scommitowany (`git status`: ` M`) | DO SPRAWDZENIA — to robocza treść, nie śmieć; do `git add`/commit albo świadomego odrzucenia zmian, nie do kasowania |
| `audyt/WZORCE-DASHBOARD-K3.md` | 7 KB | plik nieśledzony przez git (`??`), brak w worktree `-boty` | DO SPRAWDZENIA — wygląda na świeży wynik pracy (nazwa pasuje do K3/audyt), do scommitowania, nie do kasowania |
| `mutanty/` | 0 B (pusty katalog) | pusty katalog | ZOSTAW — celowy placeholder, używany przez `narzedzia-qa/kalibrator-straznikow.sh` i opisany w planach (`PLAN-PROJEKTU.md`, `DO-PLANU-V2.md`) jako miejsce na mutanty do kalibracji audytu; jeszcze nie uruchomiony etap |
| `boty/paczki/*.zip` (chatbot-live-1.2.0.zip, segregator-maila-1.2.0.zip) | 24–27 KB każdy | archiwa .zip | ZOSTAW — to zamierzone artefakty budowy wtyczek (właściwe miejsce `boty/paczki/`, zgodnie z zakresem wyłączenia w zleceniu) |
| `.git/` (sklep-agentow) | 4,3 MB | historia repo | ZOSTAW — normalna wielkość, nic do czyszczenia |
| `sklep-agentow-boty/` (cały worktree) | 700 KB | worktree buildera | ZOSTAW — czysty, `git status` = nothing to commit, brak śmieci, brak node_modules |

## Czego NIE znaleziono (sprawdzone, zero trafień)

- Baz testowych `.db` / `.sqlite` / `.sqlite3` — brak.
- Logów `*.log` / `debug.log` — brak (i tak są w `.gitignore`).
- `_backup`, `.bak`, `.orig`, `*~`, `*.tmp`, `*.swp` — brak.
- `.zip` poza `boty/paczki/` — brak.
- Plików >5 MB poza `node_modules` — brak.
- `.DS_Store`, `Thumbs.db`, `__pycache__`, `.pytest_cache`, `*.pyc`, core dumps — brak.
- `node_modules` w podkatalogach `wtyczki/*` — brak (dobrze, to czyste wtyczki PHP).
- Duplikatów plików między `sklep-agentow` i `sklep-agentow-boty` — jedyna różnica poza `node_modules`
  to plik `audyt/WZORCE-DASHBOARD-K3.md` (nieśledzony, więc naturalnie brak go w drugim worktree).

## Rekomendacja dla człowieka

`rm -rf /root/praca/zlecenia/sklep-agentow/node_modules` — bezpieczne, bo:
- katalog jest w `.gitignore` (nic z historii się nie straci),
- nie ma w repo pliku `package.json`, więc i tak nie da się z niczego „odbudować" — jeśli ktoś kiedyś
  wróci do pomysłu z Cloudflare Workers, i tak trzeba będzie od nowa zrobić `npm init` + zainstalować
  właściwe zależności do świadomie wybranej wersji.
- oszczędność: 195 MB / 97,5% wagi repo.

Dwa pliki w `audyt/` (CO-WISI.md zmodyfikowany, WZORCE-DASHBOARD-K3.md nowy) NIE są śmieciem —
to niescommitowana robota, wymaga decyzji „commitować czy odrzucić", a nie kasowania.
