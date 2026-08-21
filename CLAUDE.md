# Zasady pracy w tym repozytorium

Ten plik ładuje się SAM przy każdej sesji — dlatego trzymamy tu rzeczy, które
muszą przeżyć wyczyszczenie kontekstu. Wiedza szczegółowa siedzi w plikach
wskazanych niżej, nie tutaj.

## Co to jest
Trzy wtyczki WordPressa na WP 7.0 / PHP 8.4, zaplecze CAŁE w WordPressie (własne
tabele z prefiksami `sklepag_`, `chatl_`, `segm_`, panel w wp-admin, Stripe Checkout
przez `wp_remote_post` bez SDK, poczta przez `wp_mail`): **sklep** sprzedający pliki
oraz dwa **boty sprzedawane jako produkty** — ChatBot LIVE i Segregator maila.
⛔ To nie jest projekt na Cloudflare/Worker/Next.js — jeśli gdzieś tak napisano,
to świat sprzed zwrotu D16 i jest nieaktualny.

## Podział terenu (kolizja = strata dnia)
- **builder1** → `wtyczki/chatbot-live/` · **builder2** → `wtyczki/segregator-maila/`
- **koordynator** → `wtyczki/sklep-agentow/`, treści sprzedażowe, pliki CI
- Pozycja sięgająca dwóch modułów: **zapytaj ZANIM ruszysz**, nie po fakcie.
- ⛔ **Nikt nie scala własnego PR-a.** Scala koordynator, po przeczytaniu recenzji.
- Każda nowa pozycja startuje ze **świeżego maina**, nigdy z poprzedniej gałęzi
  (PR-y stojące jeden na drugim + scalanie ze zgnieceniem = gwarantowany zator).

## Zasada dowodu (najważniejsza)
Opis PR-a musi zawierać **dowód wykonania**, nie deklarację „działa": wyjście
komendy, `wp eval`, zapis z bazy, treść maila z łapacza, kod wyjścia bramki.
- **Test ma być SKALIBROWANY** — pokaż, że pada na kodzie sprzed naprawy. Test,
  który przechodzi zawsze, nie jest dowodem.
- Test sprawdza też **przypadek BEZ wady**, inaczej „naprawa" może odebrać funkcję
  wszystkim i świecić na zielono.
- Naprawiasz **klasę błędu, nie egzemplarz**. Jeśli ten sam błąd siedzi w trzech
  miejscach, PR zamyka wszystkie albo jawnie mówi, czego nie zamyka.

## Bramki
⭐ **Jedno źródło prawdy: `narzedzia-qa/bramki.sh`** — tam jest pełna lista i KOLEJNOŚĆ
bramek oraz licznik, który PADA, gdy zestaw się skurczy. ⛔ **Nie przepisuj tej listy
tutaj ani nigdzie indziej.** Ten plik sam kiedyś ją przepisał wbrew własnemu zakazowi
i rozjechała się: wymieniał sześć bramek, gdy w skrypcie było ich siedem (doszła `slady`).
Chcesz wiedzieć, co się uruchamia — pytaj źródła: `grep '^BRAMKI=' narzedzia-qa/bramki.sh`.
Lokalnie: `bash narzedzia-qa/bramki.sh` (tryb pełny) — plus, gdy dotykasz wtyczek:
`testy/dokumentacja-zgodnosc.sh` (ChatBot) i `testy/dokumentacja-zgodnosc-segregator.sh`.
Wynik z **kodem wyjścia** wklejasz do opisu PR-a.
⛔ Zielony ogon wyjścia to nie dowód — liczy się **EXIT**, nie ostatnia linia.

## Rzeczy, które wywalały nas wcześniej
1. **Uninstall niekompletny** — zostają opcje, tabele, pliki, crony, a zwłaszcza
   KLUCZ API. Nowa tabela = **trzy miejsca**: `dbDelta` + `uninstall.php` + ścieżka
   podniesienia schematu. Symetria zapis↔sprzątanie w TYM SAMYM PR.
2. **`LIKE` bez `esc_like()`** — dotyka CUDZYCH wpisów.
3. **Dokumentacja kłamie względem kodu** — każdą LICZBĘ z instrukcji i README
   sprawdź w kodzie. Odsyłaj po **tytule sekcji, nie po numerze** (numery się
   przesuwają przy każdym scaleniu).
4. **Wersja rozjechana** — nagłówek wtyczki = stała w kodzie = `Stable tag`
   w `readme.txt` = wpis w `CHANGELOG.md`.
5. **Ślady wewnętrzne w paczce klienta** — notatki QA, TODO, adresy dev.
6. **Kod robi więcej niż spec** — dopisz do `boty/PONAD-SPECYFIKACJE-BOTY.md`
   z powodem, zamiast przemycać.
7. **Licznik/stan w `wp_options`** zamiast własnej tabeli.

## Bezpieczeństwo — minimum podręcznikowe
Nonce **i** sprawdzenie uprawnień **parą** na każdej mutacji panelu · `$wpdb->prepare()`
do każdego SQL z wejściem · sanitize wcześnie (konkretne klucze + `wp_unslash`),
escape późno · jawny `permission_callback` przy `register_rest_route` · assety
ładowane tylko tam, gdzie działają · wywołania zewnętrzne: suma prób poniżej limitu
PHP, liczniki limitów zużywane PO sukcesie.
⛔ Funkcja **autoryzująca nie ma prawa mieć domyślnego otwarcia** — brak danych
oznacza odmowę, nie zgodę.

## Gdzie szukać wiedzy
`PLAN-PROJEKTU.md` (§7 bezpieczeństwo, §12 kryteria GOTOWE) · `ZROZUMIENIE.md` ·
`DECYZJE.md` (D1-D24 — rzeczy ŚWIADOMIE nierobione, nie żądaj ich) ·
`audyt/SCIAGA-EGZAMINATORA.md` (7 klas powyżej, źródło) ·
`boty/KOLEJKA-CHATBOT.md` · `boty/LUKI-SWIEZA-GLOWA-20-08.md` ·
`wtyczki/*/DOKUMENTACJA-TECHNICZNA.md` · `.github/RECENZENT-BRIEFING.md`.
Skille (WordPress, weryfikacja, RODO) są podpięte w `~/.claude/skills/`.

## Środowisko
Stos testowy w Dockerze: WordPress na `localhost:8890`, baza `strona_wp_db`,
łapacz poczty `sklep_mailpit` na `:8025`, serwer IMAP do testów `segm_imap`.
Kontrole chodzą na **naszym własnym wykonawcy** na tym serwerze (GitHub zablokował
minuty w chmurze). ⚠️ PR **dotykający plików workflow nie dostanie recenzji
automatycznej** — akcja odmawia wtedy wymiany tokenu; taki PR czyta koordynator.
