# PLAN PROJEKTU v3 — „Sklep z agentami AI" (3 wtyczki WordPressa)

Wersja 3.0 — 2026-08-18, po piwocie na WordPressa (DECYZJE D16). Zastępuje v2.1
(architektura Cloudflare — w historii gita; przeniesione z niej: wymagania, MUST NOT,
prawo, wzorce Stripe/idempotencji, kadencja, kryteria GOTOWE — wymienione: architektura
i harmonogram). Dokument bez adresów i sekretów.
**Termin egzaminu: 1.09.2026 (zmiana z 30.08 — właściciel 20.08) · 3 próby oddania, po każdej TYLKO LICZBA błędów (właściciel 25.08) · próba 1 ~31.08 po audycie K7 (PLAN-K7 §8c).**

## 1. CO BUDUJEMY I PO CO

**Trzy wtyczki WordPressa:** SKLEP + 2 sprzedawane w nim TESTOWE BOTY (ChatBot LIVE,
Segregator maila). Sklep sprzedaje boty jako produkty cyfrowe: po opłaceniu (Stripe
Checkout, TRYB TESTOWY) kupujący dostaje mail z potwierdzeniem, instrukcją i linkiem
do pobrania paczki (zip wtyczki bota + instrukcja). Zaplecze CAŁE w WordPressie:
własne tabele w bazie WP, panel w wp-admin, maile z serwera WP (S-19).

Strona-gospodarz: JEDNA WSPÓLNA strona (właściciel + 3 kolegów; 4 konta adminów przy
wdrożeniu — ustalenie 17/18.08, aktualizacja S-15). Konwersja strony na WP = ZROBIONA
(nasza robota poboczna, PR #86 scalony); sklep budujemy i testujemy na jej pełnej
kopii w warsztacie (WP 7.0.4, PHP 8.4). Egzamin: ocenia kolega, ZERO pytań do niego,
wszystkie płatności testowe.

## 2. WYMAGANIA

**FUNCTIONAL (litera notatki zlecenia — wersja aktualna 17.08):** po zakupie mail
(potwierdzenie + instrukcje) · 2 produkty z cenami i opisem „na zachętę" (co robi, jak
działa, jak skraca czas pracy) · panel admina: kiedy/za ile kupili/w jakim czasie
(dashboard) · baza: dane kupujących/płatności · produkty z pełnym CRUD (zapis przy
dodawaniu/usuwaniu/modyfikowaniu) · dziennik logowań na konto admina (kto, kiedy,
konkrety).
**Interpretacje S-04/S-07/S-09: KLEPNIĘTE przez właściciela 18.08** z wiążącą wytyczną:
**„tak jak się robi i jak robią dobre sklepy"** → dashboard, dziennik i strony sklepu
projektujemy WG WZORCÓW DZIEDZINY e-commerce (przed budową K3/K5 zbieramy wzorce:
jak to robią dojrzałe sklepy), nie po najmniejszej literze.

**NON-FUNCTIONAL:** bezpieczeństwo standardami WP (sekcja 7) · RODO (sekcja 8) ·
WCAG-lite: labelki, kontrast, klawiatura+fokus (EAA) · RWD — zakup z telefonu;
Chrome/Edge/Firefox · wydajność: assety sklepu ładowane TYLKO na stronach sklepu
(nie spowalniamy reszty strony) · zgodność: WordPress 7.0 / PHP 8.4 (wersje stacka
docelowego) · spójny styl kodu (phpcs w CI; zakaz gołych var_dump/error_log w paczce).

**SCHEMA:** sekcja 5.

## 3. MUST NOT (świadomie poza zakresem)

Kont/logowania KUPUJĄCYCH nie ma (konta w WP mają tylko adminowie) · kursów NIE
sprzedajemy · faktur NIE wystawiamy · plików produktów NIE serwujemy bezpośrednio
(tylko brama PHP) · prawdziwych płatności NIE włączamy · przecen NIE MA (Omnibus-ceny
— jawnie nie dotyczy) · opinie WYŁĄCZNIE prawdziwe od zweryfikowanych kupujących,
zbierane jednorazowym linkiem z maila zakupowego i publikowane po ręcznej akceptacji
(D6 + UZUPEŁNIENIE 24.08 + D32) — bez zbierania od gości i bez ocen zbiorczych
(Omnibus-opinie DOTYCZY i jest spełniony weryfikacją zakupu przed publikacją oraz
notą przy sekcji) · **WooCommerce NIE
używamy** (D17 — sklep to nasza
wtyczka) · **tłumaczeń/i18n NIE robimy** (teksty polskie wprost — D21; omija też minę
textdomain WP 6.7+) · multisite WP NIE (jedna zwykła instalacja) · monitoringu
zewnętrznego NIE w v1 · botów z PRAWDZIWYM AI/IMAP NIE na egzamin (D19 — rdzeń demo;
jawna droga rozbudowy w instrukcji) · **motywu i cudzych wtyczek strony NIE ruszamy**
— granica „nie dotykamy reszty strony" po piwocie: nasze wtyczki żyją we własnych
tabelach/opcjach/stronach/shortcode'ach, a odpięcie = deinstalacja z czystym uninstall.

## 4. ARCHITEKTURA v3 (WordPress; wg Plugin Handbook + doświadczenia MP)

**A. WTYCZKA SKLEP** (`sklep-agentow`): jeden plik główny z nagłówkiem (bootstrap
minimalny, zero ciężkiej roboty przy load — wszystko na hookach), klasy w `includes/`,
kod adminowy za `is_admin()`.

- **FRONT:** przy aktywacji tworzone strony WP: „Sklep" i „Dziękujemy" (+ szkice
  „Regulamin"/„Polityka prywatności" — sekcja 8); treść przez shortcode'y
  (`[sklepag_sklep]` — lista produktów z banerem trybu testowego;
  `[sklepag_dziekujemy]`; w K5 dojdzie `[sklepag_produkt]` — strona produktu po
  query var/slug; nazwy ujednolicone z kodem kości — uwaga recenzji PR #28). Assety
  (CSS/JS z wersjonowaniem — bump przy każdej zmianie, lekcja MP) tylko gdy shortcode
  na stronie. **Baner „TRYB TESTOWY — płatności nie są pobierane"** na stronach sklepu.
  Układ strony produktu: problem → co dostajesz → dla kogo → FAQ → cena → Kup.
- **CHECKOUT:** „Kup" (checkbox zgody wymagany) → endpoint wtyczki → walidacja
  produktu aktywnego z istniejącym plikiem → zapis `sesje_checkout` → utworzenie
  Checkout Session przez **WP HTTP API** (`wp_remote_post` na api.stripe.com,
  form-encoded, timeout 15 s, klucz sekretny z opcji; D18 — bez SDK, wg ZWIAD-STRIPE)
  → przekierowanie na hostowaną stronę Stripe (kart nie dotykamy).
  *(0.9.25, D35: z kluczem publikowalnym formularz karty osadzony na stronie produktu — `ui_mode=elements`,
  ramka Stripe; hostowana strona zostaje zapasem. Sesja, webhook i reconciliation bez zmian.)*
- **WEBHOOK:** trasa REST `POST /wp-json/sklepag/v1/webhook` — SUROWE body, podpis
  weryfikowany ręcznie (rozbiór nagłówka `Stripe-Signature`, HMAC-SHA256
  `hash_hmac` + porównanie `hash_equals`, tolerancja 5 min — algorytm z ZWIAD-STRIPE,
  „Verify manually" z docs) → idempotencja: UNIQUE na `stripe_session_id` +
  `INSERT IGNORE` (MySQL/MariaDB — odpowiednik „ON CONFLICT" z v2) → **wzorzec
  RESUME (D5):** dokończenie od miejsca zatrzymania (snapshot → token pobrania →
  status `mail_w_toku` PRZED wysyłką → `wp_mail` → status po POMIARZE skutku);
  handler zwraca błąd HTTP dopóki całość niedomknięta — ponowienia Stripe
  DOKAŃCZAJĄ. ⛔ **KAŻDE przejście stanu zamówienia = atomowy UPDATE z warunkiem
  (CAS: `SET status='mail_w_toku' WHERE id=? AND status='oplacone'`; 0 zmienionych
  wierszy = ktoś inny właśnie domyka → wycofaj się BEZ maila).** To rozstrzyga
  wyścig webhook × reconciliation (krytyka 8, D24): oba silniki używają TEGO
  SAMEGO przejęcia; `INSERT IGNORE` chroni tylko UTWORZENIE wiersza, przejścia
  stanu chroni wyłącznie CAS (lekcja MP: lock przez INSERT/add_option ≠ mutex). ⚠️ tryb testowy = tylko 3 ponowienia w parę godzin → **cron „dociągnij
  zaległe"** jest konieczny, nie opcjonalny. Trasa rejestrowana z JAWNYM
  `permission_callback` (`__return_true` — endpoint publiczny, autoryzacją JEST
  podpis Stripe; bez tego WP sypie notice — łamałoby kryterium #1).
  **DORĘCZENIE WEBHOOKA lokalnie (uwaga krytyka):** serwery Stripe nie widzą
  localhosta — w warsztacie i przy teście lokalnym oficjalne `stripe listen
  --forward-to` (Stripe CLI; ⛔ sekret webhooka CLI ≠ sekret z Dashboardu — zwiad);
  na hostingu z domeną: endpoint z Dashboardu. **NIEZALEŻNA DRUGA DROGA
  (reconciliation):** cron/przycisk „dociągnij zaległe" ODPYTUJE Stripe
  (`GET /v1/checkout/sessions/{id}`) o sesje utworzone-a-niedomknięte z
  `sesje_checkout` i domyka je TYM SAMYM kodem co webhook — zakup domyka się
  nawet bez działającego webhooka (wolniej); K1 testuje OBIE drogi.
- **MAILE:** `wp_mail()` (S-19: maile z serwera WP). Treść maila zapisywana przy
  zamówieniu — **podgląd w panelu** (egzaminator zobaczy mail bez skrzynki).
  ⚠️ Z2: kontener WP nie ma wysyłki — w stacku warsztatowym stawiamy ŁAPACZ SMTP
  (mailpit; narzędzie warsztatu, nie część paczki); na hostingu wysyłką rządzi hosting.
- **WYDANIE PLIKU:** zipy produktów w `wp-content/uploads/sklepag-chronione-{losowe}/`
  (uploads, bo to jedyny katalog o gwarantowanym zapisie na dowolnym hostingu).
  ⚠️ uploads jest z natury serwowane przez webserver (uwaga krytyka) → **OCHRONA
  WARSTWAMI** (wzorzec dojrzałych sklepów Woo/EDD): pliki pod LOSOWYMI nazwami BEZ
  rozszerzenia .zip + `index.php` w katalogu + `.htaccess` deny (działa na Apache —
  nasz stack to obraz wordpress/Apache) + **na nginx .htaccess nie działa (lekcja
  MP) → instrukcja wdrożenia podaje gotową regułę `location` deny**; K2 testuje
  bezpośredni GET na plik (musi być odmowa). Brama pobrań WYŁĄCZNIE w PHP: token
  HMAC (ważność 72 h), limit 10 pobrań, **licznik atomowy** (UPDATE z warunkiem)
  PRZY STARCIE pobrania, bez Accept-Ranges; HEAD nie zużywa limitu (D4).
- **PANEL wp-admin** (`add_menu_page`, capability `manage_options`, każda mutacja =
  nonce + capability): **Dashboard** (lista zamówień: kiedy/za ile/co + sumy w
  okresach dzień/tydzień/miesiąc + eksport CSV — wzorce „dobrych sklepów" przed
  budową) · **Zamówienie** (szczegóły, treść maila, przycisk ponownej wysyłki) ·
  **Produkty** (CRUD, upload zip, deaktywacja zamiast usuwania, snapshot w
  zamówieniach nietykalny) · **Dziennik** (logowania + akcje) · **Ustawienia**
  (Settings API: klucze Stripe testowe, sekret webhooka, nadawca maila, waluta;
  sekrety w opcjach z `autoload=no`, nigdy w kodzie/repo).
- **DZIENNIK LOGOWAŃ (S-09):** hooki `wp_login` + `wp_login_failed`: login_wpisany
  (przy 4 kontach adminów „kto" ma pełny sens; nieudane próby też), czas, wynik, IP,
  przeglądarka + **log AKCJI admina** (CRUD produktów, resend, eksport — append-only).
- **CRON:** zdarzenie WP-cron co 10 min „dociągnij zaległe" (reconciliation sesji
  przez API + mail_blad → ponowienie do 3 prób → panel-czerwone; sesje porzucone →
  wygasłe), idempotentny. ⚠️ WP-cron to PSEUDO-cron odpalany ruchem — na cichej
  stronie może nie ruszyć wcale (uwaga krytyka) → trzy zabezpieczenia:
  (a) reconciliation odpala się TAKŻE przy każdym wejściu admina na dashboard
  sklepu (self-heal), (b) RĘCZNY przycisk w panelu, (c) instrukcja wdrożenia:
  `DISABLE_WP_CRON` + systemowy cron na `wp-cron.php` co 10 min (standard
  produkcyjny).
- **LIFECYCLE:** aktywacja = `dbDelta` tabel + zapis wersji schematu + utworzenie
  stron (hooki rejestrowane na top-level pliku głównego — Handbook) · deaktywacja =
  wyrejestrowanie crona (dane zostają) · **uninstall.php** (z kontrolą
  `WP_UNINSTALL_PLUGIN`): domyślnie dane sprzedaży ZOSTAJĄ (wzorzec dobrych sklepów —
  historia sprzedaży to nie śmieć); opcja „usuń wszystkie dane przy odinstalowaniu"
  (domyślnie OFF) → pełne czyszczenie: tabele, opcje, strony wtyczki, pliki
  chronione, crony. **Symetria zapis↔sprzątanie:** każda nowa rzecz zapisywana =
  wpis w uninstall W TYM SAMYM PR; nowa tabela = 3 miejsca (dbDelta + uninstall +
  ścieżka upgrade) — lekcja MP.

**B. WTYCZKI-BOTY (produkty; głębokość wg D19 — działający rdzeń demo + instrukcja):**
- **ChatBot LIVE** (`chatbot-live`): pływający widget czatu na stronie (bąbelek);
  rdzeń: baza par pytanie-odpowiedź (CRUD w adminie) + dopasowanie słów kluczowych +
  odpowiedź awaryjna „zostaw maila, odezwiemy się" (zapis wiadomości do tabeli —
  pokrywa też odczyt „LIVE = kontakt na żywo"); seed demo po polsku; **HYBRYDA
  (D19 zmienione przez właściciela 18.08):** DRUGI silnik = prawdziwe AI przez API —
  klucz WŁAŚCICIELA w ustawieniach (nigdy w paczce), stop-loss (dzienny limit +
  licznik + zjazd do reguł przy błędzie); bez klucza pełnoprawny tryb regułowy.
  Szczegóły: boty/SZKICE-BOTOW.md. [Opcja do ZBADANIA: klient AI rdzenia WP 7.0.]
- **Segregator maila** (`segregator-maila`): reguły segregacji (nadawca/temat/słowa
  kluczowe → kategorie; CRUD w adminie) + wbudowana PRÓBKA maili demo + import
  własnej próbki (CSV) → przebieg segregacji z raportem (ile do której kategorii,
  które bez dopasowania) + TRYB AI (hybryda, jw.): klasyfikacja modelem, przebieg
  „porównaj reguły vs AI" z raportem różnic. Prawdziwa skrzynka (IMAP/OAuth) =
  po egzaminie — instrukcja opisuje drogę.
- Oba boty: własny prefiks tabel/opcji, czysty uninstall, instrukcja użytkownika po
  polsku. **Paczka produktu w sklepie = zip wtyczki + instrukcja** — dokładnie to
  wydaje sklep po zakupie.

**C. ŚRODOWISKO:** budowa + testy na stacku warsztatowym (WP 7.0.4 / PHP 8.4 / MariaDB,
pełna kopia strony-gospodarza po konwersji) + DRUGI czysty WP do testu instalacji od
zera (K6). wp-cli w kontenerze. Repo: `sklep-agentow` (konto klienckie), cykl
branch→PR (recenzent-Claude + CI)→merge.

## 5. MODEL DANYCH v3 (tabele w bazie WP, prefiks `{$wpdb->prefix}sklepag_`)

- `sklepag_produkty`: id, nazwa, slug, opis_krotki, opis_pelny (render zawsze przez
  esc_*), cena_grosze, waluta, plik_sciezka (poza publicznym URL), plik_rozmiar,
  plik_sha256, aktywny, utworzono, zmieniono. Usuwanie = deaktywacja gdy istnieją
  zamówienia; nowy plik = NOWA ścieżka (stare zamówienia trzymają swoją).
- `sklepag_sesje_checkout`: id, stripe_session_id UNIQUE, produkt_id, stan
  (utworzona/oplacona/wygasla), utworzono — ślad po naszej stronie zanim przyjdzie
  webhook; podstawa „dociągnij zaległe" i widoku porzuconych. **INFORMACYJNA:
  jedynym źródłem prawdy stanu zakupu jest `zamowienia.status`** (przejścia przez
  CAS — §4A); stan sesji dopisywany best-effort PO przejściu zamówienia, ewentualny
  rozjazd jest niegroźny i widoczny w panelu (krytyka 8).
- `sklepag_zamowienia`: id, stripe_session_id UNIQUE, produkt_id, produkt_nazwa_kupiona,
  produkt_cena_kupiona, produkt_plik_kupiony (snapshot z chwili zakupu), email_kupujacego,
  kwota_grosze, waluta, status (oczekuje → oplacone → mail_w_toku → mail_wyslany |
  mail_blad), zgoda_tresc, zgoda_czas, token_pobrania (skrót), link_wygasa,
  pobrania_limit, pobrania_uzyte, mail_proby, mail_tresc (podgląd w panelu),
  utworzono (= czas webhooka), zmieniono.
- `sklepag_zdarzenia`: id, zamowienie_id NULL, typ, opis, uzytkownik, czas —
  append-only oś czasu; obejmuje TAKŻE akcje admina (CRUD, resend, eksport).
- `sklepag_logowania`: id, czas, login_wpisany, wynik, ip, user_agent.
- `sklepag_rate_limit`: klucz PK (akcja|okno|md5 IP), licznik, okno_do — techniczna
  tabela atomowego limitera (wiersz żyje ~2 min, czyszczona cronem co 10 min;
  liczniki NIE w wp_options — ściąga kl. 7, a transient nie jest atomowy).
- Konfiguracja → `wp_options` z prefiksem `sklepag_` (sekrety `autoload=no`);
  wersja schematu w opcji (ścieżka upgrade przy zmianie).
Retencja: logowania i zdarzenia techniczne czyszczone po 90 dniach (cron);
anonimizacja kupującego na żądanie: email → NULL + usunieto_dnia (kwoty i czasy
zostają — dashboard żyje).

## 6. PRZEPŁYW ZAKUPU v3

Strona produktu: checkbox zgody na natychmiastowe dostarczenie (wymagany) → „Kup" →
POST do wtyczki z kluczem idempotencji (okno 60 s — podwójny klik ≠ 2 sesje) →
walidacja: produkt aktywny + plik istnieje → zapis `sesje_checkout` → Checkout
Session (CENA WYŁĄCZNIE Z BAZY) → płatność testowa → **webhook** (jak §4A) → strona
„Dziękujemy" odpytuje status po id sesji (REST) i pokazuje „mail wysłany" /
„przetwarzamy".
**Awarie:** mail_blad → cron co 10 min (3 próby) → panel-czerwone + ręczny przycisk ·
sesja porzucona → widoczna w panelu · link wygasł/limit → strona błędu z mailem
kontaktowym właściciela (z ustawień).

## 7. BEZPIECZEŃSTWO v3 (checklist — każda pozycja ma test w swoim przyrocie)

Standardy WP: **capability check na KAŻDEJ akcji admina + nonce na KAŻDEJ mutacji**
(nonce = CSRF, capability = autoryzacja — zawsze PARĄ) · `$wpdb->prepare()` do
każdego SQL (zero sklejania) · sanityzacja wejść (konkretne klucze + `wp_unslash`,
nigdy całe $_POST), escape wyjść (`esc_html/attr/url`) — sanitize early, escape late ·
każdy SQL `LIKE`: wartości przez `$wpdb->esc_like()`, w stałych `\_` (podkreślnik
= wildcard; ściąga kl. 2) · wywołania zewnętrzne: SUMA wszystkich prób w jednym
żądaniu PHP wyraźnie < limitu 30 s hostingu — sklep: 15 s × 1 próba (ponowienia
robią Stripe/cron), boty: 9 s × 2 próby = 18 s (korekta 18.08 po recenzji #31:
2×15=30 nie mieści się pod limitem); liczniki limitów zużywane PO sukcesie,
nie przed (ściąga: uwagi kolegi z catsnboard PR #82) ·
podpis webhooka stałoczasowo na surowym body · cena wyłącznie z bazy · sekrety w
opcjach `autoload=no`, NIE w repo (gitleaks w CI), NIE w logach · pliki produktów
poza publicznym dostępem warstwami + brama w PHP (§4A) · dziennik prób logowania +
opóźnienie wykładnicze po nieudanych (per para login+IP, SUFIT 60 s, reset po
sukcesie — OPÓŹNIENIE, nie twarda blokada: na wspólnych kontach nie wolno nam
zablokować egzaminatora) · upload zip: rozszerzenie + MIME + limit rozmiaru
**20 MB** (realne zipy botów <1 MB; realny `upload_max_filesize` stacka sprawdzany
w K1) + losowa nazwa docelowa + weryfikacja obecności PO zapisie ·
XSS: opisy produktów renderowane jako tekst · rate limit publicznych endpointów
ZAKUPU i POBRAŃ (ATOMOWY licznik w tabeli `rate_limit` per IP i okno minutowe —
transient odpadł: para get/set gubi żądania równoległe, zmierzone 40/40 przy
progu 30; recenzja #33) — **trasa webhooka JAWNIE WYŁĄCZONA z limitera**
(ponowienia Stripe idą ze stałej puli IP; dławienie ich = cichy ubój głównej drogi
zamaskowany przez reconciliation — krytyka 8; autoryzacją webhooka jest podpis) ·
CI: php -l + phpcs + phpstan (skill wp-phpstan) + gitleaks +
testy przelotu; **manifest bramek rośnie TYLKO osobnym PR**.

## 8. PRAWO / RODO v3

Zgoda na natychmiastowe dostarczenie treści cyfrowej zapisywana per zamówienie
(treść + czas) · strony „Regulamin" i „Polityka prywatności" tworzone przy aktywacji
jako SZABLONY z polami sprzedawcy do wypełnienia (paczka nie publikuje cudzego
regulaminu) · minimalizacja: przy ZAKUPIE od kupującego tylko email; przy DOBROWOLNEJ
opinii (D32) imię i treść — za jawną zgodą na publikację (SKLEPAG_OPINIA_ZGODA_TRESC) ·
anonimizacja na żądanie obejmuje też opinię (imię znika, token umiera, publikacja
schodzi — sklepag_anonimizuj_opinie) · retencja logów 90 dni · polityka wymienia Stripe i mechanizm maili jako
przetwarzających; instrukkcja właściciela: akceptacja DPA u operatorów · bez przecen
→ Omnibus-ceny jawnie nie dotyczy · opinie zweryfikowanych kupujących → Omnibus-opinie
DOTYCZY i jest spełniony (weryfikacja zakupu przed publikacją + nota przy sekcji;
bez aggregateRating) · WCAG-lite (EAA) · przed oddaniem: bramka
`bezpieczenstwo-danych-klienta` (dane osobowe = mail kupujących + dziennik IP
+ imię i treść dobrowolnej opinii, publikowane za zgodą — D32).

## 9. PLAN PRACY v3 (harmonogram; POMIAR czasu per krok w audyt/POMIAR-CZASU.md)

- **K1 · 18-19.08 — KOŚĆ SKLEPU na stacku:** szkielet wtyczki (aktywacja/tabele/
  wersja schematu/strony) → seed 1 produktu → checkout `wp_remote_post` → webhook
  (podpis + idempotencja + RESUME) → `wp_mail` (mailpit dołożony do stacka) →
  wydanie pliku endpointem. Doręczanie webhooków: `stripe listen` (CLI) + test
  DRUGIEJ drogi (reconciliation domyka zakup przy WYŁĄCZONYM forwardingu). Próby
  kontrolne środowiska: zapis do uploads w kontenerze (UID www-data — lekcja MP),
  realny `upload_max_filesize`, serwer stacka (Apache/nginx). **Testy automatyczne
  od 1. dnia** (skrypty na żywym stacku: pełny przelot = golden master, webhook 2×,
  podmiana ceny, przerwanie przed mailem + ponowienie dokańcza). Sprawdzenie Z1
  (BLIK testowy) i Z7 (klient AI WP 7.0).
- **K2 · 20.08 — WROGA SESJA + audyt pierwszego przelotu (MOMENT 2):** zły podpis,
  webhook 2×, cena z żądania ignorowana, limit pobrań równolegle (2 przy ostatnim
  slocie → 1 przechodzi), HEAD, plik skasowany z dysku, pusta baza, cykl
  aktywacja→deaktywacja→aktywacja bez śmieci i błędów, bezpośredni GET na plik
  chroniony = odmowa, test LIKE-wildcard: sprzątanie NIE łapie cudzego wpisu
  o podobnej nazwie (ściąga egzaminatora, klasa 2). Wroga sesja idzie na stacku z REALNYM motywem strony po
  konwersji — kolizje z ich frontem łapiemy CIĄGLE, nie dopiero w K6.
- **K3 · 20-21.08 — PANEL wp-admin:** NAJPIERW wzorce dziedziny (dashboardy dojrzałych
  sklepów — wytyczna właściciela „jak robią dobre sklepy"), POTEM budowa: dashboard + CSV,
  zamówienie + treść maila + resend, dziennik logowań (hooki), log akcji, ustawienia.
- **K4 · 21-22.08 — CRUD produktów:** upload do katalogu chronionego + walidacje
  (typ/rozmiar/cena>0/niepusta nazwa) + deaktywacja zamiast usuwania + test:
  edycja produktu NIE zmienia snapshotu w zamówieniach.
- **K5 · 22-23.08 — WITRYNA SKLEPU:** najpierw wzorce stron produktowych dobrych
  sklepów, potem strony na treści botów. **Rozprzęgnięcie od zipów (uwaga recenzji
  PR #26):** treść sprzedażowa („ile czasu oszczędza") powstaje ze SZKICÓW botów
  klepniętych przez właściciela (~19-20.08) — NIE czeka na gotowe zipy; finalne zipy
  wpinamy do wydawania, gdy przejdą wrogą sesję (23.08), a pokrycie opisów w
  FAKTYCZNEJ treści paczek sprawdza kryterium #10 na końcu. WCAG-lite, RWD, baner
  testowy, regulamin/polityka z szablonów; **sędzia wyglądu 0-100 wg kryteriów
  dziedziny PRZED pokazaniem właścicielowi.**
- **RÓWNOLEGLE K1→K5 (builder1, tmux, worktree) — BOTY:** 18-19.08 SZKICE obu botów
  (spis funkcji rdzenia + koncepcja ekranów) → **RECENZJA WŁAŚCICIELA szkiców PRZED
  budową witryny (bramka)**; z klepniętych szkiców OD RAZU powstaje treść
  sprzedażowa dla K5 → **19.08 ZWIAD WZORCÓW botów (warunek właściciela 18.08):** mocne
  projekty dobrych firm (drabina: gotowce → GitHub z gwiazdkami → oficjalne docs)
  → `boty/WZORCE-BOTOW.md` + brief buildera (przed integracją AI builder czyta
  skill `claude-api`) → **20-23.08 budowa builderem (HYBRYDA: silnik regułowy +
  AI ze stop-lossem)** + instrukcje + zip → 23.08
  wroga sesja botów (czysty WP: aktywacja bez błędów, seed działa, uninstall czysty).
  Przegląd i scalanie PR-ów buildera = moja STAŁA godzina zamykająca dzień
  (uwzględniona w obłożeniu dni K1-K5, nie „poza czasem").
- **K6 · 24.08 — POWTARZALNOŚĆ:** świeży czysty WordPress (drugi stack) → instalacja
  3 wtyczek WG INSTRUKCJI przez NIE-autora (właściciel; mierzone, cel ≤30 min) → pełny
  zakup end-to-end → deinstalacja: diff bazy/plików przed-po czysty.
  ⛔ Konsekwencja terminowa (krytyka 8): **instrukcja instalacji MUSI istnieć PRZED
  K6** — powstaje przyrostowo do 23.08 (K8 ją tylko finalizuje: zrzuty, diagram,
  changelog); dokumentacja NIE jest robotą ostatniego dnia.
- **K7 · 25.08 — AUDYT KOŃCOWY (MOMENT 4):** 5 kontroli równolegle (zgodność z
  MAPA-SPEC na źródle · klikanie+wygląd+axe · paczka/repo: gitleaks, instalacja od
  zera, sweep śmieci ROZSZERZONY o ślady wewnętrzne: notatki QA, TODO/FIXME,
  adresy dev — ściąga kl. 5 · bezpieczeństwo ATAKUJE: konkretne żądania omijające
  nonce/caps/podpis/cenę · **DOK-vs-KOD: każda liczba i obietnica z README/
  instrukcji sprawdzona w kodzie** — ściąga kl. 3, kolega wytknął ×4) + **KALIBRACJA: bank mutantów projektu** (`mutanty/`, próg 2/3)
  + **audyt strażników CI mutacjami jako SKRYPT** (technika Mariusza #62, zbieżna
  z naszym kalibruj.sh: każdą bramkę psujemy dokładnie w tym, co ma łapać — strażnik,
  który nie krzyknie, jest ślepy) + krytyczny przelot **3/3 (pass^k)** + próba
  obalenia świeżą sesją („udowodnij, że NIE nadaje się do wydania").
- **K7b · 26.08 — NAPRAWY + POWTÓRKA (bufor WEWNĘTRZNY, przed próbą — nie mylić
  z buforem na zwroty):** naprawy KLASAMI (grep całe repo) + **sweep „co przestało
  być prawdą"** po każdej naprawie (własna naprawa unieważnia testy i dokumentację —
  lekcja MP) + POWTÓRKA oblanych kontroli i kalibracji — kryterium #12 jest
  blokujące, więc ten dzień istnieje po to, żeby oblana kalibracja z 25.08 nie
  zjadła próby 1. Gdy audyt czysty za pierwszym podejściem: dzień idzie na zrzuty
  do instrukcji i szlif treści.
- **K8 · 27.08 — PACZKA + PRÓBA ODDANIA nr 1:** zamrożenie → wersje + CHANGELOG
  (bramka wersji SPRAWDZA wpis w changelogu — lekcja MP) → zipy 3 wtyczek + sumy
  kontrolne → dokumentacja klienta Z KODU (instrukcja prostym językiem + zrzuty
  robione maszynowo; diagram architektury dla informatyka; rozdział „test vs
  produkcja" §13) → do paczki
  `PONAD-SPECYFIKACJE.md` (jawna lista wszystkiego ponad literę notatki — ściąga
  kl. 6) → **bramka ludzka: właściciel ogląda zrzuty krytycznych ścieżek
  i odblokowuje** → oddanie (próba 1) → **runbook: OTWORZYĆ stronę Releases
  i potwierdzić oczami, że „Latest" = oddawana wersja** (fakt „kolega widzi
  1.1.0" — ściąga kl. 4).
- **28-30.08 — BUFOR na zwroty (próby 2-3).** Zmiana po zamrożeniu = ścieżka hotfix.

Dni graniczne kroków (20/21/22.08 należą do dwóch kroków) = świadome pół-dnia +
pół-dnia; POMIAR-CZASU pokaże dryf najpóźniej 21.08 — wtedy tniemy zakres szlifu
K5 (wygląd), nigdy audytu.
Rytm: branch → test na żywym stacku → PR (recenzent-Claude + CI) → merge; następny
krok po POTWIERDZONYM wyniku poprzedniego. Odbiór recenzji: minuty+koszt+komentarz
(„pass" w sekundy = self-skip — lekcja z Issue #9). Bramka czerwona = naprawiamy
produkt, NIGDY miarę.

## 10. KADENCJA SPRAWDZANIA (skill sprawdzanie-zlecenia)

M1 ✅ zrobione: MAPA-SPEC z surowej notatki (17.08) + klepnięcia interpretacji
(18.08). M2 = K2 (audyt pierwszego przelotu na kości). W TRAKCIE: automaty w CI
(php -l, phpcs, phpstan, gitleaks, testy przelotu; znaleziony błąd → w 24 h reguła
lub test). M4 = K7: GO/NO-GO z trzech liczb liczonych SKRYPTEM (0 krytycznych
i dużych · kalibracja ≥2/3 · 0 rozjazdów ze spec). Audytor: świeża sesja, jedna
soczewka na agenta, znalezisko = padający test, „zero z uzasadnieniem" wymagane.
Dwie próby naprawy, potem reset i inne podejście.

## 11. REJESTR ZAŁOŻEŃ v3

- Z1. Symulacja BLIK w trybie testowym Stripe — sprawdzana w K1 (karta = pewnik).
- Z2. `wp_mail` w kontenerze Docker NIE wysyła bez SMTP → w stacku łapacz mailpit
  (warsztat, nie produkt); na hostingu wysyłka = sprawa hostingu; jawne w instrukcji.
- Z3. Głębokość botów wg D19 (rdzeń demo + instrukcja). Jeśli egzaminator oczekiwał
  „prawdziwego AI" — wyłapie to próba 1 (27.08); bufor 3 dni + architektura botów
  gotowa na podpięcie dostawcy.
- Z4. Sklep testujemy na kopii strony-gospodarza w warsztacie; wdrożenie na żywą
  stronę = po egzaminie, za zgodą czwórki (przełączka + świeży re-import treści).
- Z5. „Jedna wspólna strona, 4 konta adminów" (właściciel 17/18.08) — wtyczka mimo to
  konfigurowalna przez Ustawienia (przenośność tania teraz, potrzebna później);
  test przenośności = K6 na czystym WP.
- Z6. Wolumen: pojedyncze zakupy dziennie (przy zmianie przeliczyć limity/retencję).
- Z7. „Klient AI w rdzeniu WP 7.0" (nasza pamięć) — ZBADAĆ w K1 zanim cokolwiek
  na nim oprzemy; to opcja rozszerzenia botów, NIE założenie planu.
- Z8. FORMA OCENY przez kolegę (zero pytań = nie dopytamy): przyjmujemy wzorzec MP —
  kolega dostaje paczkę + instrukcję + dostęp do repo i sprawdza SAM na swoim
  środowisku; rubryka §12 („NA PACZCE, na świeżej instalacji") jest pod to skrojona,
  a K6 to generalna próba egzaminu. Gdyby ocena wyglądała inaczej — wyłapie to
  próba 1 (bufor 28-30.08 na zwroty).
- Z9. Webhook lokalnie = Stripe CLI (`stripe listen`); paczka ma DRUGĄ, niezależną
  drogę domknięcia zakupu (reconciliation przez API — §4A), więc zakup działa
  i bez webhooka; instrukcja: rozdział „webhook lokalnie vs na hostingu".
- Z10. Serwer WWW: nasz stack = obraz wordpress (Apache, .htaccess działa);
  docelowy hosting NIEZNANY → ochrona plików warstwami + gotowa reguła nginx
  w instrukcji; sprawdzenie serwera stacka = próba kontrolna w K1.

## 12. KRYTERIA „GOTOWE" v3 (binarne; wykonywane NA PACZCE na świeżej instalacji)

1. Instalacja 3 wtyczek na czystym WP wg instrukcji przez NIE-autora ≤30 min,
   bez fatali i notice'ów (WP_DEBUG włączony).
2. Zakup kartą testową: webhook → zamówienie → mail ≤60 s (pomiar skryptem);
   mail w łapaczu SMTP; treść maila widoczna w panelu.
3. Link pobrania: właściwy plik (sha256 = snapshot); po terminie/limicie odmowa;
   2 równoległe pobrania przy ostatnim slocie → przechodzi dokładnie 1; HEAD nie
   zużywa limitu.
4. Webhook 2× = jedno zamówienie i jeden mail; **webhook i reconciliation odpalone
   RÓWNOLEGLE na tym samym zamówieniu = jeden mail i jeden WAŻNY token** (test
   wyścigu); ponowienie po przerwaniu przed mailem DOKAŃCZA (test z wymuszonym
   padnięciem); zły/brak podpisu = odrzut z błędem.
5. Podmieniona cena/produkt w żądaniu = zignorowane (cena z bazy); zakup bez zgody
   niemożliwy; akcje admina bez zalogowania/capability = odmowa; mutacja bez nonce
   = odmowa; seria żądań zakupu z jednego IP ponad próg = odmowa (rate limit
   zmierzony, nie tylko zadeklarowany).
6. Dashboard „jak dobre sklepy": zakup widoczny (czas webhooka, kwota, produkt ze
   SNAPSHOTU), sumy w okresach, eksport CSV; edycja/deaktywacja produktu NIE zmienia
   historii zamówień.
7. CRUD: nowy produkt widoczny w sklepie bez zmian kodu; bez potwierdzonego pliku
   nie da się aktywować; walidacje odrzucają cenę ≤0, pustą nazwę, plik nie-zip
   oraz plik ponad limit rozmiaru; poprawny upload ląduje pod losową nazwą w
   katalogu chronionym i jest POTWIERDZONY po zapisie (obecność + rozmiar).
8. Dziennik: logowania wszystkich adminów (udane i nieudane, IP, przeglądarka) +
   log akcji admina (CRUD/resend/eksport); po kolejnych nieudanych próbach
   logowania następna próba jest wykładniczo opóźniona (pomiar czasu odpowiedzi).
9. Uninstall: domyślnie dane zostają; z włączoną opcją czyszczenia — zero naszych
   tabel/opcji/stron/plików/cronów (diff przed-po pusty).
10. Boty: oba rdzenie DZIAŁAJĄ na czystym WP (ChatBot odpowiada z bazy Q&A i zbiera
    kontakt przy braku odpowiedzi; Segregator segreguje próbkę i pokazuje raport);
    instrukcje kompletne; opisy „ile czasu oszczędza" mają pokrycie w treści;
    strona produktu zawiera KOMPLET z S-03: co robi, jak działa, ile czasu
    oszczędza, cena (+ dla kogo/FAQ z układu §4A). Tryb regułowy (bez klucza) =
    test OBOWIĄZKOWY; tryb AI = test ręczny przy dostępnym kluczu (odpowiedź
    wyłącznie z informacji firmy + zjazd do reguł po odcięciu klucza).
11. WCAG-lite (labelki, klawiatura, fokus, KONTRAST) + RWD (zakup telefonem) +
    Chrome/Edge/Firefox; baner „TRYB TESTOWY" widoczny na stronach sklepu;
    strony Regulamin i Polityka istnieją jako szablony z polami sprzedawcy
    (zero cudzych danych).
12. Kalibracja audytu ≥2/3 mutantów ORAZ strażnicy CI przechodzą własny audyt
    mutacyjny — inaczej audyt nieważny, nie oddajemy.

Rubryka zamrażana po K2 jako `rubryka-GOTOWE.md`; połowa zakresu wtedy jeszcze nie
istnieje (uwaga krytyka), więc zamrożenie ma JAWNĄ ścieżkę zmiany: korekta rubryki
po zamrożeniu WYŁĄCZNIE osobnym PR z wpisem w DECYZJE (jak manifest bramek) —
rubryka może gonić rzeczywistość, ale nigdy po cichu.

## 13. CO ODDZIELA TRYB TESTOWY OD PRODUKCJI (jawna lista → rozdział instrukcji)

Aktywacja kont Stripe właścicieli (KYC, rachunek) · webhook produkcyjny z nowym
sekretem · wysyłka maili na hostingu (SMTP/dostawca — bez tego mail NIE wychodzi) ·
hosting z PHP/MySQL dla WP (zestawienie opcji z cenami = OBIECANE właścicielowi, osobny
dokument po egzaminie) · przełączka strony głównej na wersję WP (decyzja czwórki;
przy przełączce świeży re-import treści) · 4 konta adminów zakładane przy wdrożeniu ·
regulamin/polityka wypełnione danymi sprzedawcy · usunięcie banera testowego ·
jeśli na stronie działa wtyczka bezpieczeństwa/WAF — dopuścić publiczny POST na
trasę `sklepag/v1/webhook` (autoryzacja = podpis Stripe; krytyka 8) ·
VAT/OSS = księgowość właścicieli (my nie doradzamy podatkowo) · realne AI w botach
(klucz właściciela) i prawdziwa skrzynka Segregatora = osobna robota po egzaminie.

## 13b. ŹRÓDŁA OFICJALNE DZIEDZINY (drabina szukania, szczebel 2)

- **developer.wordpress.org** — Plugin Handbook (basics, activation/deactivation,
  uninstall, Settings API, security/nonces), REST API Handbook, `dbDelta`/creating
  tables, Plugin Directory guidelines (rygor jak przy MP).
- **docs.stripe.com** — Checkout Sessions API, webhooks („Verify manually"!),
  testing (karty testowe), idempotent requests. Destylat: ZWIAD-STRIPE.md (kosc-k1).
- **OWASP Cheat Sheets** (sesje, walidacja, magazyn sekretów) · **MDN** (nagłówki,
  cookies) · **WCAG quick reference** (WCAG-lite) · **UOKiK / ustawa o prawach
  konsumenta** (zgoda na treści cyfrowe) · **WP-CLI docs** (testy na stacku).
- ⛔ Świat v2 (Cloudflare/Next.js docs) — już NIE dotyczy sklepu; zostaje w historii.
Nazwa narzędzia podana przez model = hipoteza do sprawdzenia w źródłach, nie fakt.

**Zasady rzemiosła (wykute z MP, obowiązują):** symetria zapis↔sprzątanie (ten sam
PR) · zapis stanu PO pomiarze skutku · limit mierzy to, co robi szkodę · jedno
źródło prawdy dla każdej listy · nowa tabela = 3 miejsca · bump wersji assetów przy
każdej zmianie JS/CSS · lock przez INSERT, nie add_option.

## 14. WYCENA CZASU (rozstrzygnięcie głębokości botów — podstawa D19)

- SKLEP (K1-K8 z audytem i paczką): **~6 dni roboczych** — mieści się do 27.08.
- BOTY HYBRYDA (D19 po zmianie właściciela 18.08; równolegle, builder1): silnik
  regułowy + prawdziwe AI przez API ze stop-lossem — **~3 dni** budowy (20-23.08)
  + wroga sesja; niesie builder, sklepu nie rusza.
- BOTY „pełny agent produkcyjny" (prawdziwy IMAP/OAuth, pamięć rozmów, operator
  na żywo): **+3-4 dni** i nowe ryzyka → termin 27.08 pada — **ODRZUCONE na
  egzamin**; instrukcja jawnie opisuje drogę rozbudowy.
Głębokość KLEPNIĘTA przez właściciela 18.08 (hybryda — D19). Egzamin bez kosztów API
(tryb regułowy działa bez klucza); ewentualne ~5$ na klucz do demo z żywym AI =
wyłącznie za osobną zgodą właściciela.
