# 📋 BRIEFING BUILDERA — NAPRAWY BOTÓW po audycie (etap 3, od 23.08)

To jest komplet tego, co builder musi wiedzieć, żeby ruszyć bez wypytywania. Prowadzi
koordynatorka (Gosia, czat główny w warsztacie). Builder **nie decyduje o zakresie** —
bierze PACZKĘ z `audyt/PLAN-NAPRAW.md` §3 w podanej kolejności i melduje po każdej.

## KIM JESTEŚ I GDZIE PRACUJESZ
- **builder1** → drzewo `/root/praca/zlecenia/sklep-agentow-b1`, teren **`wtyczki/chatbot-live/`** + testy ChatBota (`testy/chatbot-*`), paczki **C1 → C2 → C3 → C4 → C5 → C6** (pieniądze i dane przed UX).
- **builder2** → drzewo `/root/praca/zlecenia/sklep-agentow-b2`, teren **`wtyczki/segregator-maila/`** + testy Segregatora (`testy/segregator-*`), paczki **S1 → S2 → S3 → S4 → S5**.
- ⛔ Nie wchodzisz do drzewa drugiego buildera ani do `/root/praca/zlecenia/sklep-agentow` (główne drzewo, czytają je inni). ⛔ Nie ruszasz `main` bezpośrednio — zawsze gałąź `fix/<paczka>` ze świeżego `origin/main`.
- ⛔ Pozycja sięgająca DRUGIEJ wtyczki albo `wtyczki/sklep-agentow/` → **pytasz koordynatorkę ZANIM ruszysz** (meldunek w oknie), nie po fakcie.
- Pełne zasady repo: `CLAUDE.md` (ładuje się sam) — zasada dowodu, 7 klas błędów, bramki.

## JAK ROBISZ JEDNĄ PACZKĘ (cykl, powtarzasz)
1. **Meldunek w swoim oknie**: „biorę paczkę X, gałąź fix/X" — PRZED czytaniem kodu. ⛔ `TABLICA.md` i `audyt/REJESTR-ZNALEZISK.md` edytuje TYLKO koordynatorka (wspólne pliki obu builderów, liczniki w nagłówku → konflikt i czerwony `main`); Ty piszesz proponowaną zmianę w opisie PR.
2. `git fetch origin && git checkout -b fix/<paczka> origin/main`
3. **Czytasz kartę paczki** w `audyt/PLAN-NAPRAW.md` §3 + sekcje raportów, które karta wskazuje (`audyt/boty/RAPORT-*.md`). Tam jest plik, linia, objaw, przyczyna i test. **Nie szukasz od zera, nie zgadujesz.**
4. **Najpierw ODTWARZASZ błąd** (`superpowers:systematic-debugging`): wgrywasz SWOJĄ wtyczkę na stos (`docker cp wtyczki/<nazwa>/. strona_wp_wordpress:/var/www/html/wp-content/plugins/<nazwa>/ && docker exec strona_wp_wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/<nazwa>`) i uruchamiasz test znaleziska — **musi PAŚĆ (EXIT=1)**. ⚠️ Gdy karta mówi, że plik to SONDA (EXIT zawsze 0: `repro/*.php`, `a4-*.js`) — odtwarzasz nią błąd (czytasz wyjście), a strażnikiem będzie TWÓJ nowy test [N]. Jeśli nie pada — STOP, meldujesz (znalezisko mogło zniknąć albo test kłamie), nie „naprawiasz" w ciemno.
5. **Naprawiasz KLASĘ, nie egzemplarz.** Karta wymienia rodzeństwo — obejmujesz wszystkie miejsca albo w opisie PR piszesz plik:linia, czego NIE zamykasz i dlaczego.
6. **`bash narzedzia-qa/kto-wola.sh`** (z drzewa gałęzi) → przy KAŻDYM wypisanym miejscu dopisujesz w opisie PR: „sprawdzone / nie dotyczy / naprawione razem". Nie ma „nie sprawdzałem".
7. **Test:** test znaleziska teraz PRZECHODZI (EXIT=0). ⛔ Pliki w `audyt/boty/testy-audytu/` i `audyt/boty/repro/` są **ZAMROŻONE** (dowód audytu) — nie edytujesz. Strażnik regresji = **NOWY plik `testy/<wtyczka>-<klasa>-test.php`** (albo `.js` dla przeglądarki) z prefiksem wtyczki; **kalibrujesz go**: na kodzie sprzed naprawy (drugie drzewo / `git stash`) EXIT=1, po naprawie EXIT=0. Test sprawdza też **przypadek BEZ wady** (poprawne dane nadal działają) — inaczej „naprawa" może odebrać funkcję wszystkim. Przy znalezisku „BRAK — do dopisania" w rejestrze strażnik jest OBOWIĄZKOWY.
8. Uruchamiasz SWOJE testy (po `docker cp` przed testem HTTP/przeglądarkowym odczekaj `sleep 3` — opcache odświeża co 2 s; testy HTTP/JS zawsze przez `flock /root/praca/zlecenia/wymiana/stos.lock <komenda>`, bo stos i limiter logowań są wspólne z drugim builderem): PHP → `docker cp testy/X.php strona_wp_wordpress:/tmp/X.php && docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/X.php; echo EXIT=$?` · JS → `node testy/X.js` (wymaga `WP_ADMIN_USER`/`WP_ADMIN_PASS` z `/root/.claude/sklep-admin-roboczy.env`: `set -a; . /root/.claude/sklep-admin-roboczy.env; set +a`). ⛔ Nie uruchamiasz testów, które psują stan stosu (lista w `audyt/LINIA-BAZOWA-TESTOW.md` kolumna „psuje stan") — te biegną u koordynatorki z przywracaniem bazy.
9. **Sweep „co przestało być prawdą":** `INSTRUKCJA.md`, `readme.txt`, `DOKUMENTACJA-TECHNICZNA.md`, `PONAD-SPECYFIKACJE-BOTY.md`, deklaracja prywatności, **istniejące testy** (grep po starym zachowaniu/napisie) — w opisie PR lista zdań, które zmieniłeś, albo „nic — sprawdziłem X, Y, Z". Strona produktu (sklep) → zgłaszasz koordynatorce, nie ruszasz.
10. **Rejestr `audyt/REJESTR-ZNALEZISK.md` — NIE edytujesz.** W opisie PR sekcja „Proponowane zmiany rejestru": które wiersze → W NAPRAWIE, które dostały strażnika (ścieżka pliku). Koordynatorka wnosi to przy scaleniu.
11. Wersji wtyczki ani assetów NIE podbijasz (bump zbiorczy na końcu; stos testowy nie ma cache).
12. `bash narzedzia-qa/bramki.sh` → **EXIT=0** (patrz na kod wyjścia, nie na ogon). Potem `testy/dokumentacja-zgodnosc-segregator.sh` (builder2) — EXIT do opisu.
13. `git commit -m "fix: <po polsku, co zmienia się dla człowieka> (<kody znalezisk>)"` → `git push -u origin fix/<paczka>` → `gh pr create` z opisem wg szablonu niżej.
14. **Meldujesz w swoim oknie JEDNYM akapitem:** numer PR · co naprawione · czym udowodnione (EXIT przed/po, SHA) · **co Cię zaskoczyło** (obowiązkowe — z tego wychodzą kolejne pozycje). Potem **bierzesz NASTĘPNĄ paczkę bez czekania** (`git fetch`, nowa gałąź z `origin/main`). Pytasz tylko, gdy wychodzisz poza teren albo zakres.
15. Po scaleniu Twojej paczki koordynatorka może dać Ci `/clear` + ten briefing — wtedy zaczynasz od punktu 1 następnej paczki. ⛔ Przed `/clear` zawsze: gałąź wypchnięta, meldunek złożony.

## SZABLON OPISU PR (każdy nagłówek obowiązkowy)
```
## Co naprawia (kody): …
## Klasa błędu i miejsca: … (plik:linia każdego; czego NIE zamyka i dlaczego)
## Dowód PRZED: test X na SHA <sprzed> → EXIT=1 (wklejony ogon wyjścia)
## Dowód PO: test X na tej gałęzi → EXIT=0 (ogon) · przypadek BEZ wady: … → EXIT=0
## Nowy strażnik: testy/… (kalibracja: sprzed EXIT=1 / po EXIT=0)
## kto-wola.sh: (lista miejsc, każde: sprawdzone / nie dotyczy / naprawione razem)
## Co przestało być prawdą: (zdania/pliki zmienione) albo „nic — sprawdziłem …"
## Proponowane zmiany rejestru/TABLICY: (wiersze → W NAPRAWIE; nowe strażniki: ścieżki)
## bramki.sh: EXIT=0 (+ dokumentacja-zgodnosc: EXIT=…)
## Co mnie zaskoczyło: …
```

## ⛔ PUŁAPKI TEGO REPO (zmierzone, nie teoria)
0. **Twoje drzewo (worktree) nie ma `node_modules`** — testy JS padną w 0 s z „Cannot find module 'playwright-core'". Raz na start: `ln -s /root/praca/zlecenia/sklep-agentow/node_modules node_modules` (w korzeniu swojego drzewa; `.gitignore` to ignoruje).
1. **Kod wtyczki NIE jest zamontowany w kontenerze** — bez `docker cp` testujesz STARY kod. Po każdej zmianie: `docker cp` swojej wtyczki. Nigdy cudzej.
2. **Testy audytu nie sprzątają** (`test-awaria-zapisu.php` nadpisuje klucz API i hasło IMAP na stosie) — dlatego są zamrożone i biegną u koordynatorki z przywracaniem bazy. Twoje nowe testy **sprzątają po sobie** (własny prefiks danych, kasowanie na końcu) i nie dotykają cudzych opcji.
3. **`$wpdb->update()` zwraca 0, nie false**, gdy nic nie pasowało — „skąd wiem, że się udało" = odczyt stanu PO zapisie (wzorzec: `segm_skrzynka_zapisz_haslo()`), nie `false !==`.
4. **Licznik/stan w `wp_options` zamiast tabeli = klasa błędu #7** — nowa tabela to TRZY miejsca: `dbDelta` + `uninstall.php` (obie ścieżki) + podniesienie schematu.
5. **`LIKE` bez `esc_like()`**, nonce i uprawnienia PARĄ, `$wpdb->prepare()` — minimum z `CLAUDE.md`.
6. **Zielony ogon wyjścia to nie dowód — liczy się EXIT.** `bash x.sh; echo EXIT=$?` osobno, bez potoku.
7. **PR w konflikcie nie uruchamia CI** (wygląda jak zawieszony) — po cudzym scaleniu `git fetch && git rebase origin/main` na swojej gałęzi.
8. **Recenzent automatyczny nie potwierdza `bramki.sh` na PR dotykających workflow** — nie dotykasz `.github/`.
9. **Stos jest wspólny** — drugi builder pracuje na drugiej wtyczce w tym samym WordPressie; nie kasujesz cudzych danych, nie restartujesz kontenerów, nie zmieniasz ustawień cudzej wtyczki.
10. **Zasada dwóch prób:** po dwóch nieudanych podejściach `git reset --hard origin/main` i INNE podejście — nie trzecia łatka na łatkę.
11. **Czas:** retencja/daty — spójnie w strefie WP (`current_time`/`wp_date`), nie `date()`; pamiętaj o strefie ujemnej (KR6).

## CZYM SPRAWDZASZ
- Stos: `strona_wp_wordpress` (WP 7.0.4 / PHP 8.4, `localhost:8890`), baza `strona_wp_db`, poczta `sklep_mailpit` (:8025), IMAP testowy `segm_imap` (greenmail, użytkownik `szef`). ⛔ NIE KASOWAĆ, NIE RESTARTOWAĆ.
- Testy PHP/JS jak w punkcie 8. Przeglądarka: `playwright-core` + chromium są zainstalowane (`node testy/lib/chromium.js` podaje ścieżkę). Linia bazowa testów (co przechodziło PRZED): `audyt/LINIA-BAZOWA-TESTOW.md`.
- Statyka: `bash narzedzia-qa/bramki.sh` (pełny) — po EXIT.
- Dowody zachowania na paczce (`narzedzia-qa/dowod-zachowania-*.sh`) uruchamia koordynatorka — stawiają własny stos próbny.

## CZEGO NIE ROBISZ
- Nie podbijasz wersji wtyczek (1.5.9 / 1.3.2 wchodzi RAZ, na końcu, robi to koordynatorka).
- Nie scalasz własnych PR-ów. Nie pushujesz `--force`. Nie kasujesz gałęzi.
- Nie edytujesz `audyt/boty/testy-audytu/`, `audyt/boty/repro/`, `audyt/boty/RAPORT-*.md`, `audyt/REJESTR-ZNALEZISK.md`, `TABLICA.md`, `boty/PONAD-SPECYFIKACJE-BOTY.md`, `boty/buduj-paczki.sh`, `boty/paczki/`, `.github/`, `narzedzia-qa/bramki.sh`.
- Nie zmieniasz zakresu produktu (nowa funkcja ≠ naprawa) — jeśli karta wygląda jak funkcja, pytasz.
- Nie odpalasz subagentów mocnym modelem (hook blokuje) — `model:'sonnet'` z dietą; Lighthouse NIE (OOM).
- Nie wysyłasz niczego na zewnątrz, nie ruszasz sekretów poza `set -a; . plik; set +a` bez wypisywania.

## MELDUNKI
Po każdej paczce, jednym akapitem w swoim oknie: **PR · co · czym udowodnione · co zaskoczyło.**
Gdy stoisz (pytanie, blokada, zgoda) — napisz to WPROST jednym zdaniem zaczynającym się od `STOJĘ:`.
