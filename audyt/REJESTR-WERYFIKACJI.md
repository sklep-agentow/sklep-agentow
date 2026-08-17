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
| — | Hydracja/router: link do /sklep/ przeżywa (wariant A) | ⬜ SPIKE K0 | OCZEKUJE | — |
