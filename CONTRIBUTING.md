# Praca w tym repozytorium

Krótko: jak uruchomić, jak zmieniać, co zatrzyma Twój PR. Zasady, które muszą przeżyć
wyczyszczenie kontekstu, są w `CLAUDE.md`; to, według czego recenzent ocenia PR — w
`.github/RECENZENT-BRIEFING.md`.

## Wymagania

- WordPress 6.7+ (testowane do 7.1), PHP 8.1+ (testowane na 8.4), MySQL/MariaDB.
- Lokalnie: Docker (stos testowy), `php`, `composer` (PHPCS + PHPStan), Node 20+ (testy
  przeglądarkowe Playwright), `gh` (GitHub CLI) do PR-ów.
- Konto Stripe w trybie testowym + `stripe listen` do webhooków na lokalny stos.

## Pierwsze uruchomienie

1. Sklonuj repo i **jednorazowo**: `git config core.hooksPath .githooks` — włącza strażnika
   pre-push (blokada bezpośredniego pusha na `main`).
2. Postaw WordPress w Dockerze (stos z `CLAUDE.md` → „Środowisko”: WP na `localhost:8890`,
   łapacz poczty Mailpit na `:8025`, IMAP testowy dla Segregatora).
3. Wgraj wtyczki: `docker cp wtyczki/sklep-agentow/. <kontener>:/var/www/html/wp-content/plugins/sklep-agentow/`
   (tak samo dwie pozostałe), aktywuj w wp-admin, wpisz klucze testowe Stripe w Ustawieniach.
4. Przejdź `dokumentacja/INSTRUKCJA-INSTALACJI.md` jak klient — pierwszy zakup kartą testową
   ma skończyć się mailem w Mailpicie z działającym linkiem.

## Gdzie co leży

- `wtyczki/<nazwa>/` — trzy wtyczki, każda z własnym `readme.txt`, `CHANGELOG.md`,
  `DOKUMENTACJA-TECHNICZNA.md` (schemat bazy, stałe, punkty wejścia) i `INSTRUKCJA.md` dla klienta.
- `testy/` — strażniki (`*-test.php` przez `wp eval-file`, `*.js` Playwright) i ich próby
  kontrolne (`*-proba-kontrolna.sh`); `testy/e2e/` — przeloty zakupu, axe, Lighthouse.
- `narzedzia-qa/` — `bramki.sh` (jedyne źródło listy bramek CI), `paczka-zgodnosc.sh`,
  `slady-wewnetrzne.sh`, `k7-koniec.sh` (bramka wydania).
- `audyt/` — raporty audytów, `SCIAGA-EGZAMINATORA.md` (rejestr klas błędów), `CO-WISI.md`
  (otwarte sprawy). `DECYZJE.md` — rzeczy **świadomie** niezrobione; nie „naprawiaj” ich.
- `dokumentacja/` — instrukcja instalacji dla klienta i diagramy.

## Konwencje

- **Gałęzie:** `feat/…`, `fix/…`, `docs/…`, `chore/…` — zawsze ze **świeżego `main`**, nigdy
  z poprzedniej gałęzi. Nikt nie pushuje prosto na `main`.
- **Commity:** Conventional Commits z zakresem wtyczki: `fix(sklep): …`, `feat(chatbot): …`,
  `docs(audyt): …`. Wiadomość mówi, **co się psuło i dlaczego**, nie tylko co zmieniono.
- **Wersja:** nagłówek wtyczki = stała `*_WERSJA` w kodzie = `Stable tag` w `readme.txt` =
  wpis w `CHANGELOG.md` (Keep a Changelog). Bramka `testy/dokumentacja-zgodnosc*.sh` to sprawdza.
- **Nowa tabela lub opcja = trzy miejsca w tym samym PR:** aktywacja (`dbDelta`),
  `uninstall.php`, ścieżka podniesienia schematu.
- **Kod robi więcej niż zamówienie?** Dopisz z powodem: sklep → `wtyczki/sklep-agentow/PONAD-SPECYFIKACJE.md`,
  boty → `boty/PONAD-SPECYFIKACJE-BOTY.md`.

## Zanim otworzysz PR

1. `bash narzedzia-qa/bramki.sh --tryb pelny` — musi skończyć się `EXIT=0`. Lista bramek jest
   **tylko** w tym skrypcie (`BRAMKI=`); bez `--tryb pelny` skrypt sam dobiera tryb do zmian
   (dla samych dokumentów pomija lint/PHPCS/PHPStan) — do opisu PR wklej wynik trybu pełnego.
2. Dotykasz wtyczki → `bash testy/dokumentacja-zgodnosc-sklep.sh` (albo `-segregator.sh`,
   `dokumentacja-zgodnosc.sh` dla ChatBota) — dokumentacja musi zgadzać się z kodem.
3. Naprawiasz błąd → **test, który pada na kodzie sprzed naprawy** i przechodzi po niej, plus
   przypadek bez wady (żeby naprawa nie odebrała funkcji wszystkim). Wzór: dowolna para
   `testy/<nazwa>-test.php` + `testy/<nazwa>-proba-kontrolna.sh`.
4. Opis PR z `.github/PULL_REQUEST_TEMPLATE.md` — **z wynikiem uruchomienia** (wyjście
   komendy, kod wyjścia, zapis z bazy, treść maila), nie ze słowem „działa”.
5. Wpis w `CHANGELOG.md` wtyczki; jeśli zmieniasz zachowanie widoczne dla klienta —
   `INSTRUKCJA.md` / `readme.txt` w tym samym PR.

## Kontrole, które zatrzymają PR

- `Quality` (CI) — te same bramki co lokalnie; lista tylko w `narzedzia-qa/bramki.sh`.
- `Recenzent` (CI) — recenzja każdego PR-a według briefingu; PR bez dowodu = nie do scalenia.
- Ślady wewnętrzne w plikach wtyczek (numery zgłoszeń, ścieżki naszego drzewa roboczego,
  adresy dev) — skaner `slady` pada. Komentarz w kodzie niesie **powód**, nie numer PR-a.
- Bramka wydania `narzedzia-qa/k7-koniec.sh` przed każdym Release.

## Czego nie robić

- Nie scalaj własnego PR-a bez przeczytania recenzji; nie kasuj gałęzi przed `state=MERGED`.
- Nie zapisuj sekretów w kodzie, testach ani opisach PR (klucze Stripe, sekrety webhooków,
  ciasteczka sesji) — `gitleaks` skanuje **historię**, zatruty commit oznacza nową gałąź.
- Nie „naprawiaj” rzeczy z `DECYZJE.md` bez nowej decyzji z uzasadnieniem.
- Nie trzymaj liczników ani stanu w `wp_options` — własne tabele.
- Nie odsyłaj w dokumentacji po numerze sekcji (przesuwają się) — po tytule.

## Pułapki, na które ktoś już wpadł

Pełna lista z dowodami: `audyt/SCIAGA-EGZAMINATORA.md`. Najczęstsze: uninstall zostawia opcje
i klucz API · `LIKE` bez `esc_like()` · dokumentacja z liczbą, której nie ma w kodzie · wersja
rozjechana między nagłówkiem, stałą i `readme.txt` · test, który przechodzi zawsze (nie jest
dowodem) · `$?` czytany po potoku (mierzy `tail`, nie test).
