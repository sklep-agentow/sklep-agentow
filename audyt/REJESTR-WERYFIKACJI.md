# REJESTR WERYFIKACJI — co sprawdzone, CZYM i z jakim wynikiem

Zasada (lekcja Dzidka nr 1 z MP): rejestr od STARTU; każdy wpis ma kolumnę „czym sprawdzone".
Wpis bez dowodu = wrażenie, nie fakt. „Nie ma X" wymaga próby kontrolnej.

| Data | Co | Czym sprawdzone | Wynik | Dowód |
|------|----|-----------------|-------|-------|
| 16.08 | Strona docelowa = statyczny eksport Next.js, tylko branch gh-pages, bez źródeł, bez CNAME | klon repo + ls/grep + git ls-remote | POTWIERDZONE | ZWIAD w ARCHITEKTURA-PROPOZYCJA.md |
| 16.08 | Limity darmowe Cloudflare (Worker 100k/d; D1 5GB, 5M odczytów/d, 100k zapisów/d; R2 10GB) | WebFetch oficjalnego cennika developers.cloudflare.com | POTWIERDZONE | ARCHITEKTURA-PROPOZYCJA.md |
| 16.08 | Vercel Hobby zakazuje komercji | WebFetch vercel.com/docs/limits/fair-use-guidelines (cytat) | POTWIERDZONE | ARCHITEKTURA-PROPOZYCJA.md |
| 16.08 | Stripe PL: cennik bez opłat stałych; BLIK 1,6%+1 zł | WebFetch stripe.com/pl/pricing | POTWIERDZONE | ZROZUMIENIE.md §P4 |
| 16.08 | Stripe działa bez zarejestrowanej firmy | źródła NIEOFICJALNE (blogi/forum ×3) | NIEPOTWIERDZONE OFICJALNIE — zweryfikować przy zakładaniu konta | ZROZUMIENIE.md §P4 |
| 17.08 | sprawdz-manifest.sh działa w tym repo | uruchomienie lokalne, exit 0 | POTWIERDZONE | czat 17.08 ~01:30 |
| 17.08 | CI actionlint+manifest zielone na main | GitHub checks po push | POTWIERDZONE | commit 5fecc5f, checks 2/2 |
| — | Limit CPU 10 ms darmowego Workera wystarcza dla naszego przepływu | ⬜ pomiar w K1 (pass^k 3/3) | OCZEKUJE | — |
| — | BLIK w trybie testowym Stripe | ⬜ K1 | OCZEKUJE | — |
| — | Dostarczalność maili (kilku dostawców + spam + stopka) | ⬜ K1/K2 | OCZEKUJE | — |
| 17.08 | Link wstrzyknięty w ZBUDOWANY HTML nav → hydracja React USUWA (błąd #418) | Playwright headless na kopii strony; próba kontrolna faq=3 | NEGATYWNE (wpinanie przez edycję builda ODPADA) | spike-test.js, wynik w czacie 17.08 |
| 17.08 | Strona-gospodarz MA meta CSP: script-src 'self'+sha256 → inline skrypty blokowane PO CICHU | grep meta CSP + test (inline nie wykonany, zero błędów) | POTWIERDZONE (fakt architektoniczny) | spike, index.html gospodarza |
| 17.08 | Wariant B': plik /sklep/wtyczka.js (self) dodaje link w nav PO hydracji + przycisk poza drzewem Reacta → PRZEŻYWA hydrację i scroll; klik → /sklep/ pełnym przeładowaniem (router nie przechwytuje) | Playwright: nav=1, float=1 po interakcji; url=/sklep/, marker=1 | POZYTYWNE — mechanizm wpięcia DZIAŁA | spike-test-b.js |
| 17.08 | CSP gospodarza NIE dziedziczy się na nasze dokumenty /sklep/ (inline JS działa u nas, u hosta blokowany) | marker #sk-inline-dziala=1 w tym samym przebiegu | POTWIERDZONE — izolacja per dokument; fetch API z NASZYCH stron wolny | spike-test-b.js |
| 17.08 | Reconstruction test (świeża sesja, sama notatka): zrozumienie zbieżne ~95%; RÓŻNICA: „telefon-koło ratunkowe" czyta jako FUNKCJĘ OFERTY dla kupujących (flaga 1×/zakup), nie zasadę egzaminu | subagent bez naszych dokumentów | DIFF DO ROZSTRZYGNIĘTY przez Dzidka (S-10) | wynik w czacie 17.08 |
| 17.08 ~23:45 | Repo strony kolegi: nazwa AKTUALNA `automatic-ai`, nasz dostęp WRITE, repo PRYWATNE, branch main | `gh repo view MatthewPlugins/matthewplugins.pl --json name,url,viewerPermission,isPrivate,defaultBranchRef` (stary adres przekierował) | POTWIERDZONE — dosłowny wynik: `repo: https://github.com/MatthewPlugins/automatic-ai / uprawnienie nasze: WRITE / prywatne: true / branch domyslny: main` | wynik wklejony TU (kolumna Wynik) |
| 17.08 ~23:45 | Klona repo kolegi NIE MA na serwerze | `find /root/praca -maxdepth 3 -iname '*matthewplugins*'` → 0 trafień; PRÓBA KONTROLNA w tym samym przebiegu: `ls /root/praca/zlecenia/` → 6 katalogów (find/ls widzą system plików) | POTWIERDZONE (zero przy działającej kontroli) — ZDEZAKTUALIZOWANE nocą 17/18.08: powstała zamrożona migawka (wiersz niżej) | wynik w czacie 17.08 ~23:45 |
| 18.08 | Globalna blokada push do repo MatthewPlugins działa z całej maszyny | `git config --global core.hooksPath` = /root/.claude/git-hooki + odczyt treści pre-push (case na URL, exit 1) | POTWIERDZONE | czat 18.08 |
| 18.08 | Migawka repo kolegów zamrożona: immutable + zero remote | `lsattr -d` → flaga `i`; `git remote -v` → pusto | POTWIERDZONE | czat 18.08 |
| 18.08 | Stack warsztatowy pod budowę sklepu żyje: WP 7.0.4, PHP 8.4.21, front HTTP 200 | `docker ps` (2 kontenery Up) + `curl -w %{http_code}` + `wp core version` + `php -v` w kontenerze | POTWIERDZONE | czat 18.08 |
| 18.08 | Konwersja strony-gospodarza na WP SCALONA u kolegów (przesłanka planu v3) | `gh pr view 86 --repo MatthewPlugins/automatic-ai --json state,mergedAt,mergeCommit` → `state=MERGED, mergedAt=2026-08-18T00:06:02Z, mergeCommit=d1c64c0480fdb...` | POTWIERDZONE | wynik wklejony TU (znalezisko 1 recenzji PR #26) |
| 18.08 | Stack warsztatowy serwuje przez APACHE (nie nginx) → .htaccess na stacku DZIAŁA; hosting docelowy dalej nieznany (Z10) | `docker exec strona_wp_wordpress apache2 -v` → `Server version: Apache/2.4.67 (Debian)` | POTWIERDZONE | wynik wklejony TU (krytyka 7, znalezisko 2) |
