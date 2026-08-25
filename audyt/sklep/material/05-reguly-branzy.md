# 05 — REGUŁY BRANŻY (SKLEP) — sprzedaż treści cyfrowych przez WordPress + Stripe

> ⛔ Zasada: jeśli powołujesz się na regułę branżową, podaj JEJ ŹRÓDŁO. „Tak się robi"
> bez źródła to opinia, nie reguła — i leci z raportu.
>
> Ten plik cytuje WYŁĄCZNIE sześć źródeł, jak każe zlecenie materiału: `PLAN-PROJEKTU.md`
> §7 (linie 215–243), §8 (244–259), §13 (433–445), §13b (446–463) ·
> `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` §3–§5 · `audyt/SCIAGA-EGZAMINATORA.md` (7 klas) ·
> `CLAUDE.md` (sekcje „Rzeczy, które wywalały nas wcześniej" i „Bezpieczeństwo — minimum
> podręcznikowe") · skill `wp-plugin-directory-guidelines/SKILL.md` (tylko nagłówki, do
> 30 linii). **Gdzie któryś z podtematów Stripe/WordPress NIE MA źródła w tych sześciu
> plikach, jest to napisane wprost jako „brak źródła w materiale" — zamiast reguły
> zmyślonej z głowy.**

## 1) PRAWO

**Zgoda na natychmiastowe dostarczenie treści cyfrowej.**
Źródło: `PLAN-PROJEKTU.md` §8 — „Zgoda na natychmiastowe dostarczenie treści cyfrowej
zapisywana per zamówienie (treść + czas)".

**Potwierdzenie na trwałym nośniku — dyrektywa 2011/83/UE art. 8 ust. 7 lit. b) i
art. 16 lit. m).**
Źródło: `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` §4 — „Zgoda z art. 38 (treść cyfrowa,
utrata prawa odstąpienia): checkbox przy zakupie + zapis w zamówieniu (`zgoda_tresc`,
`zgoda_czas`) — jest. **Luka: mail po zakupie nie POTWIERDZA tej zgody**, a dyrektywa
2011/83/UE art. 8 ust. 7 lit. b) każe zawrzeć w potwierdzeniu umowy na trwałym nośniku
«the confirmation of the consumer's prior express consent and acknowledgment in
accordance with point (m) of Article 16»" (cytat ze źródła:
legislation.gov.uk/eudr/2011/83/article/8, pobrane 25.08). Ten sam dokument, §8 (stan
PO naprawie z 25.08): „Mail po zakupie: `testy/mail-po-zakupie-test.php`… **9/9 OK** —
treść zgody dosłownie z zamówienia + data, blok zgody przed linkiem…".

**RODO — minimalizacja / retencja / anonimizacja.**
Źródło: `PLAN-PROJEKTU.md` §8 — „minimalizacja: przy ZAKUPIE od kupującego tylko email;
przy DOBROWOLNEJ opinii (D32) imię i treść — za jawną zgodą na publikację
(SKLEPAG_OPINIA_ZGODA_TRESC) · anonimizacja na żądanie obejmuje też opinię (imię znika,
token umiera, publikacja schodzi — sklepag_anonimizuj_opinie) · retencja logów 90 dni ·
polityka wymienia Stripe i mechanizm maili jako przetwarzających; instrukkcja
właściciela: akceptacja DPA u operatorów" [pisownia „instrukkcja" jak w źródle].

**Omnibus.**
Źródło: `PLAN-PROJEKTU.md` §8 — „bez przecen → Omnibus-ceny jawnie nie dotyczy · opinie
zweryfikowanych kupujących → Omnibus-opinie DOTYCZY i jest spełniony (weryfikacja
zakupu przed publikacją + nota przy sekcji; bez aggregateRating)".

**Regulamin / Polityka prywatności — szablony, nie gotowe teksty prawne.**
Źródło: `PLAN-PROJEKTU.md` §8 — „strony «Regulamin» i «Polityka prywatności» tworzone
przy aktywacji jako SZABLONY z polami sprzedawcy do wypełnienia (paczka nie publikuje
cudzego regulaminu)". Potwierdzenie stanu na 25.08: `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md`
§4 — „Regulamin i Polityka sklepu = SZABLONY do uzupełnienia przez właściciela (K8;
regulamin musi opisać 14-dniową gwarancję satysfakcji — D31)".

**Dostępność (EAA).**
Źródło: `PLAN-PROJEKTU.md` §8 — „WCAG-lite (EAA)". Stan zmierzony:
`audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` §4 — „Dostępność (EAA 2025 → WCAG 2.1 AA jako
cel): stan wyżej — 0 realnych naruszeń w axe, Lighthouse 100".

**Mail po zakupie — ocenia się TREŚĆ, nie tylko fakt wysyłki.**
Źródło: `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` §5, nagłówek dosłowny: „Mail po zakupie
(treść — nigdy wcześniej nie oceniana)". Wymienione tam elementy treści: jasny temat,
kwota z dopiskiem o trybie testowym, link z ważnością i limitem, kroki instalacji, link
do opinii, „odpisz na tego maila", potwierdzenie zgody art. 38 + data, nagłówek
`Reply-To`, zdanie o gwarancji (gdy produkt ją ma), dane sprzedawcy + odnośnik do
regulaminu (dopiero z K8, gdy regulamin przestanie być szablonem).

**Co NIE jest przedmiotem tego audytu prawnego (rozgraniczenie z tego samego źródła).**
Źródło: `audyt/AUDYT-JAKOSC-PODSTRON-25-08.md` §3 — nagłówki HTTP (HSTS, CSP,
X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) oraz
ujawnianie `Server`/`X-Powered-By` to explicite „warstwa serwera/motywu gospodarza, nie
wtyczki sklepu" — zalecenia trafiają do instrukcji dla właściciela/hostingu (K8), nie są
błędem KODU wtyczki.

## 2) STRIPE

Zakres cytowania: **wyłącznie** `PLAN-PROJEKTU.md` §7 (linie 215–243) i §13b
(linie 446–463).

- **Podpis webhooka stałoczasowo na surowym body.** Sourced — §7: „podpis webhooka
  stałoczasowo na surowym body".
- **Idempotencja.** Sourced częściowo — §13b wymienia „idempotent requests" jako temat
  z `docs.stripe.com`, którego drabina szukania ma dotknąć; SAMA treść reguły (jak
  wygląda mechanizm idempotency key Stripe) nie jest wypisana w §7/§13b. (Nasz WŁASNY
  klucz idempotencji przy zakupie — okno 60 s — jest opisany w PLAN-PROJEKTU §6, poza
  dozwolonym zakresem cytowania tego pliku; nie mieszam źródeł, tylko sygnalizuję że to
  inny mechanizm niż idempotencja po stronie Stripe.)
- **Brak gwarancji kolejności zdarzeń.** Brak źródła w materiale — audytor sprawdza w
  oficjalnej dokumentacji Stripe i cytuje adres.
- **`payment_status` metod asynchronicznych.** Brak źródła w materiale — audytor
  sprawdza w oficjalnej dokumentacji Stripe i cytuje adres.
- **Błędy ≥400.** Brak źródła w materiale — audytor sprawdza w oficjalnej dokumentacji
  Stripe i cytuje adres.

Dodatkowo §13b (drabina szukania, szczebel 2), cytat: „**docs.stripe.com** — Checkout
Sessions API, webhooks («Verify manually»!), testing (karty testowe), idempotent
requests. Destylat: ZWIAD-STRIPE.md (kosc-k1)." — to jest WSKAZANIE gdzie szukać, nie
sama treść reguły.

Z §7 (kontekst wywołań do Stripe, sourced): „wywołania zewnętrzne: SUMA wszystkich prób
w jednym żądaniu PHP wyraźnie < limitu 30 s hostingu — sklep: 15 s × 1 próba (ponowienia
robią Stripe/cron)… liczniki limitów zużywane PO sukcesie, nie przed" oraz „trasa
webhooka JAWNIE WYŁĄCZONA z limitera (ponowienia Stripe idą ze stałej puli IP;
dławienie ich = cichy ubój głównej drogi… autoryzacją webhooka jest podpis)".

## 3) WORDPRESS

- **Nonce + uprawnienia PARĄ na każdej mutacji.** Sourced — `PLAN-PROJEKTU.md` §7:
  „capability check na KAŻDEJ akcji admina + nonce na KAŻDEJ mutacji (nonce = CSRF,
  capability = autoryzacja — zawsze PARĄ)"; `CLAUDE.md` „Bezpieczeństwo — minimum
  podręcznikowe": „Nonce **i** sprawdzenie uprawnień **parą** na każdej mutacji panelu".
- **`$wpdb->prepare()`.** Sourced — §7: „`$wpdb->prepare()` do każdego SQL (zero
  sklejania)"; `CLAUDE.md`: „`$wpdb->prepare()` do każdego SQL z wejściem".
- **Sanityzacja wejść / escape wyjść.** Sourced — §7: „sanityzacja wejść (konkretne
  klucze + `wp_unslash`, nigdy całe $_POST), escape wyjść (`esc_html/attr/url`) —
  sanitize early, escape late"; `CLAUDE.md`: „sanitize wcześnie (konkretne klucze +
  `wp_unslash`), escape późno".
- **`LIKE` / `esc_like()`.** Sourced — §7: „każdy SQL `LIKE`: wartości przez
  `$wpdb->esc_like()`, w stałych `\_` (podkreślnik = wildcard; ściąga kl. 2)"; `CLAUDE.md`
  „Rzeczy, które wywalały nas wcześniej" pkt 2: „`LIKE` bez `esc_like()` — dotyka
  CUDZYCH wpisów".
- **Uninstall — trzy miejsca.** Sourced — `CLAUDE.md` „Rzeczy, które wywalały nas
  wcześniej" pkt 1: „Uninstall niekompletny — zostają opcje, tabele, pliki, crony, a
  zwłaszcza KLUCZ API. Nowa tabela = **trzy miejsca**: `dbDelta` + `uninstall.php` +
  ścieżka podniesienia schematu. Symetria zapis↔sprzątanie w TYM SAMYM PR."
- **WP-Cron uruchamiany tylko przy odwiedzinach (pseudo-cron).** Brak źródła w
  materiale — audytor sprawdza w oficjalnej dokumentacji WordPress
  (developer.wordpress.org) i cytuje adres.

Pozostałe punkty z `CLAUDE.md` „Bezpieczeństwo — minimum podręcznikowe" (cytat pełny —
uzupełnia listę wyżej): „jawny `permission_callback` przy `register_rest_route` ·
assety ładowane tylko tam, gdzie działają · wywołania zewnętrzne: suma prób poniżej
limitu PHP hostingu, liczniki limitów zużywane PO sukcesie. ⛔ Funkcja **autoryzująca
nie ma prawa mieć domyślnego otwarcia** — brak danych oznacza odmowę, nie zgodę."

Pozostałe punkty z `CLAUDE.md` „Rzeczy, które wywalały nas wcześniej" (cytat pełny, 7
punktów źródła — pkt 1 i 2 już wyżej):
3. „Dokumentacja kłamie względem kodu — każdą LICZBĘ z instrukcji i README sprawdź w
   kodzie. Odsyłaj po tytule sekcji, nie po numerze (numery się przesuwają przy każdym
   scaleniu)."
4. „Wersja rozjechana — nagłówek wtyczki = stała w kodzie = `Stable tag` w `readme.txt`
   = wpis w `CHANGELOG.md`."
5. „Ślady wewnętrzne w paczce klienta — notatki QA, TODO, adresy dev."
6. „Kod robi więcej niż spec — dopisz do `boty/PONAD-SPECYFIKACJE-BOTY.md` z powodem,
   zamiast przemycać."
7. „Licznik/stan w `wp_options`" zamiast własnej tabeli.

Z `PLAN-PROJEKTU.md` §7, dodatkowo (kontekst upload/rate-limit, sourced): „upload zip:
rozszerzenie + MIME + limit rozmiaru 20 MB… + losowa nazwa docelowa + weryfikacja
obecności PO zapisie · XSS: opisy produktów renderowane jako tekst · rate limit
publicznych endpointów ZAKUPU i POBRAŃ (ATOMOWY licznik w tabeli `rate_limit` per IP i
okno minutowe — transient odpadł: para get/set gubi żądania równoległe)".

## 4) SIEDEM KLAS BŁĘDÓW (ściąga egzaminatora, cytat pełny)

Źródło: `audyt/SCIAGA-EGZAMINATORA.md` — „Źródło: sweep repo `catsnboard` (4+ rundy
recenzji kolegi, PR #57/#70/#82/#84/#87-92, cytaty verbatim z CHANGELOG/PR) +
`mp-service-suite`… Zebrane 18.08 subagentem, 45 wywołań. **Kolega będzie szukał TYCH
SAMYCH klas — każda ma tu swoją bramkę u nas albo dopisaną akcję.**"

### Co wytknął DOSŁOWNIE (najcenniejsze cytaty/fakty)
- catsnboard: paczka klienta zawierała **KOPERTĘ RECENZENCKĄ** z wewnętrzną notatką QA
  „nie dla klienta" (KRYTYCZNE, PR #57).
- catsnboard: *„w readme dalej jest «czego nie kasuj» xd, nie poprawiłeś tego"* — README
  niespójne z kodem, wytknięte DRUGI raz (PR #87).
- catsnboard: `uninstall.php` nie kasował 6 opcji **w tym KLUCZA API klienta** (PR #84).
- catsnboard: `LIKE '_transient_pnb_%'` bez escape — `_` to wildcard SQL, ryzyko
  kasowania CUDZYCH transientów (PR #84).
- catsnboard: badge wersji w README nieaktualny — **zapomniany 3×** (PR #70);
  dokumentacja podawała „10→20→40", kod robił „20→40→80→120 min".
- MP: „kolega widzi 1.1.0" — tag istniał, ale strona Releases pokazywała stare
  „Latest" (wydanie niepublikowane = dla niego nie istnieje).
- catsnboard: timeout PHP przy 25×retry (~100 s > limit hostingu 30 s); licznik
  dziennego limitu zjadany PRZED sprawdzeniem błędu sieci; `insertAdjacentHTML` bez
  escapowania.

### 7 KLAS BŁĘDÓW → nasze pokrycie → LUKI (zamknięte 18.08)

| # | Klasa (ile razy wracała) | Co ją łapie U NAS | Luka → akcja |
|---|--------------------------|-------------------|--------------|
| 1 | Uninstall niekompletny, zostają opcje/KLUCZ API (×3) | kryterium GOTOWE #9: diff bazy/plików przed-po PUSTY (mierzony); symetria zapis↔sprzątanie w tym samym PR | ✅ dopisane do briefingu: pełne czyszczenie MUSI usuwać klucz API właściciela |
| 2 | `LIKE` z `_`/`%` bez escape → dotyka cudzych danych | — (nie było reguły!) | ✅ NOWA reguła w briefingu+kości: każdy LIKE = `$wpdb->esc_like()` dla wartości, `\_` w stałych; wroga sesja: test „czy sprzątanie nie łapie cudzego wpisu o podobnej nazwie" |
| 3 | Dokumentacja/komentarz KŁAMIE względem kodu (×4) | sweep „co przestało być prawdą" po każdej naprawie (K7b); zimna weryfikacja PR pyta o zgodność opisu | ✅ K7 dostaje jawną soczewkę „dok vs kod": każda LICZBA i obietnica z README/instrukcji sprawdzona w kodzie |
| 4 | Wersje/badge/„Latest" nieaktualne (×4) | bramka wersji sprawdza wpis w CHANGELOG (lekcja MP); K8: JEDNO wydanie | ✅ runbook oddania: po tagu OTWORZYĆ stronę Releases i POTWIERDZIĆ, że „Latest" = oddawana wersja |
| 5 | Ślady wewnętrzne w paczce klienta (koperta QA, adresy dev) | K7: sweep śmieci .bak/.orig/lorem | ✅ sweep rozszerzony JAWNIE o: notatki QA/recenzenckie, adresy dev, TODO/FIXME, nazwiska wewnętrzne |
| 6 | Kod robi WIĘCEJ niż spec bez deklaracji | MAPA-SPEC: statusy INTERPRETACJA/ROZSZERZENIE jawnie | ✅ K8 dostaje plik `PONAD-SPECYFIKACJE.md` w paczce: lista wszystkiego ponad literę notatki |
| 7 | Licznik/stan często zapisywany w `wp_options` (MP #3) | tabele własne na dane sklepu | ✅ briefing: liczniki wywołań AI (stop-loss) do TABELI bota, nie do opcji |

### Dodatkowe akcenty pod tego egzaminatora (cytat)
- Publikacja wydania = częścią oddania (nie sam tag) — sprawdzić OCZAMI stronę Releases.
- Retry/limity: liczyć DOPIERO po sukcesie operacji (licznik zjadany przy awarii = jego
  uwaga).
- Timeouty zewnętrznych wywołań < limit PHP hostingu (jego uwaga o 100 s > 30 s) —
  nasz `wp_remote_post` do Stripe/AI: timeout 15 s, jeden retry, nigdy pętla ślepa.
- README/instrukcja czytana przez niego ZDANIE PO ZDANIU dwa razy — niespójność
  wytknięta ponownie boli podwójnie („nie poprawiłeś tego xd").

## Dodatkowo: licencja / katalog wtyczek WP.org (drugorzędne dla TEGO produktu)

Źródło: skill `wp-plugin-directory-guidelines/SKILL.md`, nagłówki (linie 1–30, cytat):

> ---
> name: wp-plugin-directory-guidelines
> description: "Use when reviewing WordPress plugins for GPL compliance, checking
> license headers or compatibility, evaluating upsell/freemium/trialware patterns,
> validating plugin naming or trademark rules, checking plugin slugs, understanding why
> a plugin was rejected from WordPress.org, or answering any question about the 18
> WordPress.org Plugin Directory guidelines — even if the user doesn't mention
> 'guidelines' explicitly."
> compatibility: "Targets WordPress 6.9+ (PHP 7.2.24+)."
> ---
>
> ## Overview
> Authoritative reference for the 18 WordPress.org Plugin Directory guidelines. Covers
> GPL licensing, plugin naming/trademark rules, trialware restrictions, and all other
> submission requirements.
>
> ## When to use
> Use this skill when you need to:
> - Review a WordPress plugin for compliance with the WordPress.org Plugin Directory
>   guidelines
> - Check GPL license compatibility for a plugin or its bundled libraries
> - Verify license headers in plugin files
> - Identify common guideline violations before submission
> - Answer questions about what is or is not allowed on WordPress.org
> - Evaluate premium/upsell flows, license checks, or freemium positioning
> - Review "teaser" or "preview" UI for trialware violations
>
> ## Inputs required
> - Plugin source code (or specific files to review)
> - Optional: plugin readme and plugin header metadata for naming and license checks
>
> ## Procedure
> 1. Check the plugin's license header against the **Valid License Headers** section
>    below.

⚠️ **Zastrzeżenie zakresu zastosowania (nie treść reguły, tylko gdzie ona działa):**
`sklep-agentow` NIE jest publikowany w katalogu WordPress.org — sprzedaż jest
bezpośrednia (paczka + instrukcja, patrz `ZROZUMIENIE.md`: „oddanie JAK MP"). Tych 18
wytycznych formalnie wiąże wtyczki ZGŁASZANE do katalogu WordPress.org. Ten skill trafia
tu, bo zlecenie materiału wskazuje go jako dozwolone źródło — audytor sam decyduje, czy
licencja GPL i nagłówek pliku głównego (`sklep-agentow.php`) są tu istotne do
sprawdzenia mimo braku zgłoszenia do katalogu.
