# CO-WISI — rzeczy oddane komuś / bez strażnika (zasada: oddajesz ⇒ wiersz, wraca ⇒ kasujesz)

## 🟡 DŁUG ZASTANY — okno idempotencji nie obejmuje JEDNOCZESNEGO podwójnego kliknięcia
(z recenzji #199 r2, DROBNA 5 — NIE regresja z tego PR, mechanizm istnieje od 0.9.0)
- Odczyt okna (`zakup.php` `get_transient`) i jego zapis (`set_transient`) dzieli wyjście do
  sieci: `sklepag_stripe_utworz_sesje()`. Dwa żądania wysłane NAPRAWDĘ jednocześnie przechodzą
  przez odczyt, zanim którekolwiek dojdzie do zapisu — więc oba tworzą własną sesję Stripe.
- Po stronie przeglądarki nic tego nie blokuje: formularz zakupu (`front-produkt.php`) nie ma
  `onsubmit` ani `disabled`, a w `wtyczki/sklep-agentow/assets/*.js` nie ma ani jednego trafienia
  na `disabled|submit` (dowód z braku, podany przez recenzenta).
- Skutek: dwie sesje checkout dla jednego kliknięcia-dubletu. Opłacona zostanie jedna (druga
  wygasa), więc NIE jest to podwójne obciążenie — ale dziennik dostaje sesję-sierotę.
- ⭐ Najtańsze domknięcie: wyłączenie przycisku na czas wysyłki po stronie przeglądarki
  (jedna linia JS) ALBO atomowa rezerwacja okna przed wyjściem do Stripe.
- ⛔ WRACA ⇒ kasujesz ten wpis. Nie blokuje wydania: skutek jest kosmetyczny, nie finansowy.


## 📌 KONTRAKT NA PRZYSZŁOŚĆ (z recenzji #177 r3–r5, 25.08)
- **`sklepag_opinia_moderuj` przepuszcza opinię BEZ wiersza zamówienia** (INNER JOIN →
  null → wolno moderować). Dziś taki stan nie powstaje produkcyjnie (zero ścieżek DELETE
  na zamówieniach — grep recenzji). ⛔ GDY powstanie pierwsza ścieżka kasowania zamówień,
  warunek MUSI przejść na „brak danych = odmowa" (reguła CLAUDE.md) — inaczej kasowanie
  zamówienia otworzy z powrotem moderację opinii zapomnianego klienta.

## 🔧 PRZYRZĄDY (z recenzji #177, DROBNA, 25.08)
- **Wzorzec testowania handlerów admin-post ISTNIEJE w repo** (sprostowanie po recenzji
  #177 r8 — mój wcześniejszy wpis „nie ma harnessu" był FAŁSZYWY, obalony grepem
  recenzji): `segm_u_odpal` + `wp_die_handler` w testach segregatora, `dokad()`
  u chatbota; handler resendu sklepu pokryty tym wzorcem w `opinie-mechanizm-test.php`
  (sekcja C3: nonce + przechwycony redirect + `pre_wp_mail`). ⬜ POKRYĆ tym wzorcem
  pozostałe handlery sklepu (produkt_zapisz/przelacz, anonimizuj, dociagnij,
  eksport_csv, opinia_moderuj) przy następnej rundzie QA.
- **`stos.sh` nie widzi ŻADNEJ tabeli sklepu** — okno odczytu recenzenta nie ma operacji
  zaglądania do `wp_sklepag_*`, więc recenzent nie może sam obejrzeć np. wierszy opinii
  (dostał odmowę przy #177 i oparł się na przebiegu testu). Rozszerzyć okno o bezpieczną
  operację odczytu tabel sklepu (SELECT-only, bez danych osobowych w wyjściu — imię maskować).

## 🔍 K7 — AUDYT SKLEPU W TOKU (od 25.08 wieczór) — plan `audyt/PLAN-K7-AUDYT-SKLEPU.md` v2, krytyka r1 obok
- Faza: PR-0 (paczka 0.9.13 + D33) → materiał `audyt/sklep/material/` → kalibracja → runda A. Stan bieżący
  i decyzje: `/root/.claude/CONTEXT-SERWER.md`. ⛔ Kod zamrożony tagiem `k7-zamrozenie` po scaleniu PR-0.
- Zaległości znalezione PRZY CZYTANIU (cele mapy, NIE naprawiać przed audytem): DOK-TECH §3 „7 tabel" vs §8 „6",
  „#170" w dokumencie klienta, tabela §2 bez `opinie.php`; `REJESTR-WERYFIKACJI.md` martwe wpisy Cloudflare/Vercel;
  zakup gościa wymaga nonce (`zakup.php:66`) → page-cache; brak eksportera/erasera RODO WP (D: dopisać w naprawach);
  zwroty Stripe bez obsługi (decyzja po L3); WP-Cron bez ruchu nie w instrukcji.
- 3 martwe pliki mediów w paczce (1,06 MB) — kasowanie ZGODA Dzidka 25.08, wykonać w rundzie napraw (bump).

## ✅ ZAMKNIĘTE 25.08 (wieczór) — SKLEP: readme.txt + CHANGELOG.md + paczka 0.9.13 (PR-0 K7)
- `paczka-zgodnosc.sh sklep-agentow` EXIT 0 (6 miejsc wersji, zip == repo 59 plików, treść wydania, 0 śladów),
  próba kontrolna bramki EXIT 0, `bramki.sh --tryb pelny` 8/8. `buduj-paczki.sh` z wyborem wtyczki argumentem
  (zamek wydanych zipów botów). Dług z #176 zamknięty.

## 🔄 W TOKU 20.08 (noc) — RUNDA BOTÓW, DWA BUILDERY
⏰ **TERMIN: do 1.09** (Dzidek 20.08 — zmiana z 30.08).
- **builder1** (tmux praca:builder1, drzewo główne, gałąź `boty/runda-finalna`): TYLKO chatbot
  + klasa AI w obu botach. Zrobione: Z1 (fałszywy sukces zapisu, oba boty), **Z0 (tryb AI —
  był MARTWY)**, Z2 (zgoda RODO w formularzu). Dalej: PLAN-NAPRAW-CHATBOT v2, P1 i P2.
- **builder2** (tmux praca:builder2, drzewo `sklep-agentow-boty`, gałąź `boty/segregator-naprawy`):
  TYLKO segregator, punkty 1-8 z BRIEF-BUILDER2. ⛔ nie dotyka `segregator-maila/includes/ai.php`.
- ⛔ **Scalenie gałęzi + bump 1.2.2 + przebudowa paczek = koordynator**, nie buildery.
- ⏸️ **WSTRZYMANE do decyzji**: eksport filtrów Gmail/Outlook (P1.3) — nasz silnik zatrzymuje się
  na pierwszej pasującej regule, Gmail stosuje wszystkie naraz → eksport 1:1 dałby inny wynik
  niż nasz raport. Rekomendacja: eksport z jawnym ostrzeżeniem + lista reguł nieprzekładalnych.
- 🔑 Klucze API (Anthropic, Gemini) w `/root/.claude/sekrety-*.env` — **DO SKASOWANIA po testach**.
- ⚠️ Tunel publiczny AKTYWNY, siteurl stacka przestawiony na adres tunelu (przepis przywrócenia
  w CONTEXT-SERWER.md).


## ⚠️ WISZĄCE OD 19.08 (sesja Gosia-3) — TUNEL PUBLICZNY na czas oglądania Dzidka
- **Tunel cloudflared AKTYWNY**: `https://linda-refrigerator-potato-objective.trycloudflare.com`
  → :8890 (proces w tle sesji; log `/tmp/cf-sklep-8890.log`). **siteurl+home stacka
  PRZESTAWIONE na adres tunelu** — po seansie Dzidka PRZYWRÓCIĆ:
  `docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option update siteurl "http://localhost:8890" && docker exec strona_wp_wordpress wp --allow-root --path=/var/www/html option update home "http://localhost:8890"`
  + ubić tunel (`pkill -f "cloudflared tunnel --url http://127.0.0.1:8890"`).
  ⛔ Do tego czasu testy automatyczne po localhost NIE są miarodajne (canonical
  redirect na tunel); E2E przez tunel: kopia `przelot2-tunel.js` w scratchpadzie.
- **header.php motywu KOPII zmieniony poza repo** (link „Sklep" w obu nav —
  D28): backup w kontenerze `header.php.przed-sklepem`; dosłowne fragmenty
  w INSTRUKCJA-INSTALACJI (sekcja Ustawienia produkcyjne).

## ✅ ZAMKNIĘTE 19.08 (późny wieczór) — ⭐ K5b3 FINAŁ SZLIFU (PR #61 MERGED) = SZLIF WIZUALNY ZAMKNIĘTY
- Sędzia finalny **84/100** (przynęta „sekcja wideo" rozbrojona): finalny CTA
  listy („Wybierz bota…" + 2 przyciski) · hierarchia linków (DLA KOGO:
  „Więcej o tym bocie") · kadr raportu 1200×470 (czytelność +60%) ·
  BONUS: Sklep dopisany do nav STOPKI (footer.php kopii, backup; instrukcja).
- Zarzut „pusta stopka" 2× ODRZUCONY z twardym dowodem: viewport po WOLNYM
  scrollu = pełna stopka, wszystkie elementy op=1. ⭐ WADA NARZĘDZIA:
  fullPage-screenshot playwrighta NIE odpala reveal-animacji motywu — oceny
  wyglądu odtąd zrzutami VIEWPORTU po wolnym scrollu, nigdy samym fullPage.
- Recenzja #61 czysta (zero uwag krytycznych/ważnych). E2E: mail_wyslany
  (5. pełny przelot dnia). Sklep w nav: nagłówek desktop + mobile + stopka.

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5b2 ILUSTRACJE SVG KART (PR #60 MERGED, D28b)
- Karty listy: WŁASNE ilustracje SVG w brandzie (roboty-bliźniaki, 880×560,
  ~4 KB) — feedback Dzidka „fajne obrazy jak profesjonaliści"; zrzuty żywych
  botów zostały na stronach produktów (podział: lista=marketing, produkt=dowód).
  Generyczny stock/AI-obrazy ODRZUCONE (D28b). Opinie/gwiazdki NIE (D6/Omnibus
  — powiedziane Dzidkowi wprost).
- Recenzent złapał REALNEGO BUGA (modyfikator klasy CSS doklejany do złego
  członu przy dwuczłonowym $klasa) → naprawiony, dowód surowym renderem klas;
  D28b dopisane do rejestru (kod cytował decyzję bez wpisu — klasa #3).
  ⭐ NOWA ZASADA z uwagi krytycznej: opis PR-a MA surowe wyjście komend, nie
  deklaracje. E2E po każdej porcji: mail_wyslany (łącznie 4× dziś).

## ✅ ZAMKNIĘTE 19.08 (popołudnie) — ⭐ K5b WIZUALIA (PR #59 MERGED, sklep 0.9.0, D28)
- **Feedback Dzidka obsłużony**: link „Sklep" w nav (desktop+mobile) · zrzuty
  ŻYWYCH botów na kartach i w sekcji „JAK TO WYGLĄDA" (rama „okna aplikacji") ·
  lista: pasek konkretów + OD ZAKUPU DO DZIAŁANIA + DLA KOGO (jedno źródło
  treści) + PYTANIA (liczby ze stałych = dok=kod). Wideo ODRZUCONE (D28).
- **Sędzia 79/100 (przynęta rozbrojona)**: 2 zgrzyty naprawione (okno 20rem,
  rama okna), zarzut „pustka przed stopką" ODRZUCONY Z DOWODEM (artefakt
  fullPage bez scrolla — reveal stopki gospodarza, opacity [0,0]→[1,1] po
  scrollu; ⚠️ zrzuty full-page odtąd PO przescrollowaniu!), postulat opinii
  ODRZUCONY (D6/Omnibus).
- **E2E ×2 przez tunel: mail_wyslany** · recenzent: 4 drobne uwagi zaadresowane
  (2 kodem, 2 pomiarem: wymiary JPG zmierzone 1:1, kadr segregatora obejrzany) ·
  stack==main diff czysty · paczki botów 1.2.1 NIETKNIĘTE.
- Zwiad wzorców: agenciai.pl = landing 1 szkolenia → osobna domena sprzedażowa
  claudedlafirm.pl (30k px, countdown, opinie) — model NIE nasz; podebrane:
  kroki uruchomienia, konkrety techniczne, FAQ listy.

## ✅ ZAMKNIĘTE 19.08 (noc) — ⭐ K6 INSTALACJA OD ZERA (przyciągnięte z 24.08), sklep 0.8.2
- **NIE-autor = niezależna sesja-tester** (Sonnet, tmux, deny na kod/warsztat —
  decyzja Dzidka: „tester niezależny przez tmux jak buildery"). Pełna ścieżka
  ZALICZONA: instalacja→Stripe→produkty→zakup→boty→deinstalacja; czas 26:58
  brutto / 15-20 min netto (kryterium #1 ≤30 min ✓). Raporty:
  audyt/K6-INSTALACJA-OD-ZERA.md + K6-RAPORT-TESTERA.md (verbatim).
- **4 znaleziska naprawione w PR k6/naprawy-po-tescie:** 🔴 permalinki „Zwykłe"
  na świeżym WP (Krok 0 instrukcji + notice 0.8.2, zmierzony na żywo w obie
  strony) · 🟠 500 na checkout.session.completed = celowy RESUME (wyjaśnienie
  w instrukcji) · 🟡 „Zamówienia"→„Pulpit" · 🔴 boty zostają w bazie po
  deinstalacji wg instrukcji (10 tabel — pomiar zewnętrzny; sekcja
  Odinstalowanie uzupełniona o osobne czyszczenie botów).

## ✅ ZAMKNIĘTE 19.08 (dzień) — DROBNICA recenzji #40/#42 (PR #57)
- **Rate-limit sklepu → LAST_INSERT_ID** (#40): osobny SELECT licznik miał
  mikro-okno wyścigu → wzorzec 1:1 z chatl_rate_limit; komentarz ostrzegawczy
  o fail-open dołożony (uwaga recenzji #57).
- **Test dziennika/opóźnienia** (#42): `testy/dziennik-test.js` na żywym stacku —
  salwa/2^n/reset/retencja/konkrety S-09, próba kontrolna (próg 999 → pada).
- **Indeks okno_do chatbota** nazwany w PONAD-SPECYFIKACJE-BOTY (klasa #6).
- **Zipy produktów na stacku podmienione na 1.2.1** przez PRAWDZIWY CRUD panelu
  (sha bazy == sha zipów, zmierzone; snapshot zam#29 nietknięty — stare zamówienia
  trzymają swój plik, zgodnie z projektem).

## ✅ ZAMKNIĘTE 19.08 (dzień) — ⭐ PAKIET BOTÓW (D25, przyciągnięty z 23.08), boty 1.2.1
- **phpcs na WSZYSTKIE 3 wtyczki:** phpcbf (58 auto-napraw, diff obejrzany — zero zmian
  semantyki) + 3 adnotacje inline z uzasadnieniem + `customSanitizingFunctions`
  (chatl_waliduj_sesje) + sniff doradczy DirectDatabaseQuery wyłączony dla botów JEDNĄ
  deklaracją w ruleset (uzupełnienie D25 — boty w 100% na własnych tabelach; PreparedSQL
  aktywny wszędzie). Zakres bramki potwierdzony PRÓBĄ KONTROLNĄ (podłożony var_dump
  w bocie = czerwone). phpcs EXIT 0, phpstan EXIT 0.
- **Widget chatbota przemalowany na paletę strony** (top-uwaga sędziego K5): limonka
  #bfff38 / tusz #08090b / węgiel #101116, dymki 1:1 z mockupem strony produktu,
  hover-pierścień jak CTA sklepu, obwódka bąbelka pod WCAG 1.4.11 na jasnych stronach;
  zrzuty na stacku gospodarza ORAZ na czystym WP; kolory zmierzone z żywego DOM.
- **WROGA SESJA BOTÓW na czystym WP** (audyt/WROGA-SESJA-BOTY.md): aktywacja bez
  jednego notice (WP_DEBUG_LOG), seedy komplet, cykl deakt→akt bez strat, przebieg
  segregatora działa, pełne czyszczenie = zero tabel/opcji/cronów. 🔴→✅ ZNALEZISKO
  klasy #1 ściągi: klucz API zostawał w bazie przy DOMYŚLNYM uninstallu — naprawione
  (delete przed early-return, oba boty), zmierzone przed/po, INSTRUKCJE dok=kod,
  paczki 1.2.1 przebudowane + powtórka weryfikacji `diff -r` (zip == repo).

## ✅ ZAMKNIĘTE 19.08 (wieczór) — ⭐ K5 WITRYNA KOMPLET, 3 PR-y (#51-#53), wersja 0.8.1
- **#51 fundament+lista** (wyspa z tokenami zmierzonymi z motywu; mono-etykiety,
  pille, baner-pigułka; H1 nasz — motyw nie renderuje własnego, zmierzone) ·
  **#52 strona produktu+SEO** (jedna strona WP + query var; rewrite ze sluga
  z opcji; sekcje §2B komplet: mockup czatu/PRZED-PO/kroki/dowody/uczciwość/FAQ;
  SEO: title/desc/canonical/OG/JSON-LD Product+Breadcrumb+FAQPage/okładki PNG/
  noindex-dziękujemy/sitemap provider [nazwa BEZ myślnika — regex rdzenia];
  hijack metadanych query varem znaleziony recenzją, zamknięty Z POMIAREM;
  PEŁNY przelot E2E po zmianie: mail_wyslany) · **#53 szlif po sędzi** (ikony
  SVG kart, jawne „nie zmyślamy recenzji", FAQ separatory, kropka-glow).
- **Sędzia wyglądu: 73/100 → 85/100** (dwa świeże przebiegi, przynęty rozbrojone
  w OBU; 2 zaniżenia rundy 2 = artefakty zestawu zrzutów, odrzucone z dowodem;
  PEŁNE werdykty obu rund: komentarze w PR #53 — rekomendacja recenzji #54).
- Cross-browser 6/6 (chromium+firefox, zero błędów konsoli) · zagadka sitemap
  ROZWIĄZANA: blog_public=0 wyłącza sitemapy w rdzeniu (nie usterka!) → wpis
  do instrukcji K8: na produkcji wyłączyć „Zniechęcaj wyszukiwarki".
- Na stacku doszedł REALNY produkt „Segregator maila" (zip 1.2.0) — sklep ma
  docelowe 2 produkty.

## ✅ ZAMKNIĘTE 19.08
- **Uwagi recenzji #49 → PR #50 MERGED (main=19dbcc4, 0.6.1):** dok=kod (.bin
  usunięte + sweep całe repo: seed-demo, adnotacja hist. w K2-WROGA-SESJA) ·
  wynik $wpdb->insert/update sprawdzany + sierota sprzątana (dowód na żywo:
  wymuszona awaria INSERT → błąd, nie fałszywy sukces; plików 4→4) · kryterium #6
  W TEŚCIE produkty-test.js (9/9, z próbą kontrolną „edycja zaszła") · ścieżka
  zrzutu przez env. Recenzent potwierdził wszystko 2× + własny grep sweepu.
- **K5 plan wyglądu:** zwiad motywu (paleta/typografia/detale zmierzone) + zwiad
  wzorców stron produktowych (subagent, źródła w PLAN-WYGLADU-K5.md §5) →
  `PLAN-WYGLADU-K5.md` CZEKA NA KLEPNIĘCIE Dzidka przed budową.

## ✅ ZAMKNIĘTE 18.08 (dzień)
- Model AI botów haiku-4-5 (Dzidek) · timeout AI #37 · **oba boty scalone z haiku**
  (#36 chatbot, #37 segregator) · recenzent zna WP+ściągę+Handbook (#38) · hardening
  uninstall wg Handbook (#39) · chatbot atomowy rate-limit (#40) · **paczki 1.2.0
  zweryfikowane (haiku + finalny kod w zipie)** (#35). ZERO otwartych PR.
- **K2 wroga sesja sklepu — 7 ataków obronionych** (audyt/K2-WROGA-SESJA.md).

## ✅ ZAMKNIĘTE 18.08 (wieczór) — ⭐ K3 KOMPLET, 7 PR-ów (#41-#47), wersja 0.5.1
- **#41 bramki CI PHP** (php-lint 8.4 · phpcs WPCS[sklep, boty→23.08 D25] · phpstan L5
  wszystkie wtyczki · gitleaks; manifest +4; E2E przelot2.js uratowany do repo).
- **#42 dziennik logowań** (S-09; opóźnienie wykładnicze ZMIERZONE 0.3→2.3→4.3→8.3 s,
  reset po sukcesie; limit 6/min per IP PRZED sleep — salwa 12: 6×200+6×429 w 0.1 s;
  retencja 90 dni; schemat v3) · **#43 ustawienia** (Settings API; sekrety autoload=no
  TAKŻE przy update-in-place; uninstall kasuje sekrety ZAWSZE — klasa #1 ściągi;
  nadawca wokół naszego wp_mail) · **#44 dashboard** (KPI+delta %, rozkład, tabela,
  CSV, self-heal Z BUDŻETEM CZASU 8/15 s; suma słupków = KPI co do grosza) ·
  **#45 zamówienie** (mail+resend ze świeżym tokenem [stary 403/nowy 200],
  anonimizacja RODO — schemat v4) · **#46 fix ukrytej strony** (bug złapany ZRZUTEM —
  wp eval go nie widział) · **#47 szlif po sędzi wyglądu**.
- **Sędzia wyglądu #1: 66/100** (przynęty rozbrojone — wiarygodny); TOP-braki
  zaadresowane w #47 (pasek statusów, kolumna Akcje, notice, hierarchia KPI,
  zrzuty-dowody); świadome odstępstwa w WZORCE-DASHBOARD-K3 §8. Sędzia #2 w toku.
- Zrzuty panelu: scratchpad sesji `zrzuty/` (10 szt.); skrypt `testy/zrzuty-panelu.js`.

## Blokuje wydanie
- (nic pilnego) — Live-smoke AI na żywym api.anthropic.com w ODWODZIE (~5$ za zgodą
  Dzidka), gdyby próba 1 chciała żywe AI; egzamin trybem regułowym 0 zł.

## Nie zaczęte (kolejność planu)
- **Nazwany dług (recenzja #49, drobna):** przy podmianie zipa na produkcie BEZ
  zamówień stary plik zostaje w katalogu chronionym (nie szkodzi, nie wycieka —
  katalog deny; sprzątanie wymagałoby odróżniania „trzymany snapshotem" od
  „wolny"). Świadomie NIE sprzątamy w v1 → wpis do `PONAD-SPECYFIKACJE.md` w K8.
- **Świadomie NIE ruszone (recenzja #40, #3):** default `$prog` w `chatl_rate_limit`
  — sklep ma default 10, bot nie; jedyne wywołanie w bocie podaje oba argumenty,
  recenzent oznaczył jako nieblokujące. Zostaje bez zmian celowo.
- **Instrukcja wdrożenia (K8): wzmianka fail2ban/WAF** na wp-login.php przy wielu IP
  (D26 — rezydualne ryzyko poza wtyczką).
- K7 25.08 (audyt + mutanty + kalibracja) · K7b 26.08 · K8 27.08 = próba 1. (K6 ✅ 19.08)

## Stan maszyny
- `stripe listen` forwarduje na :8890/webhook (sekret z `/root/.claude/sekrety-sklep.env`).
  Start po padzie: `set -a; . /root/.claude/sekrety-sklep.env; set +a; stripe listen --api-key "$STRIPE_SECRET_KEY" --forward-to http://localhost:8890/wp-json/sklepag/v1/webhook`
- Stack: `strona_wp_wordpress` (:8890), `strona_wp_db`, `sklep_mailpit` (:8025).
- Builder1: tmux praca:1, worktree sklep-agentow-boty, stoi (boty domknięte).

## Odroczenia z #172 (24.08, runda 1 — świadome, nieblokujące)
- **readme.txt Segregatora, wiersz „Wymagania"** — dopisać `stream_socket_client` + OpenSSL
  (dla prawdziwej skrzynki IMAP; FAQ sklepu już to mówi). Paczka 1.3.3 jest WYDANA i pod
  zamkiem — poprawka wchodzi z NASTĘPNYM wydaniem bota, nie osobno.
- **Bramka na LICZBY strony sprzedażowej** (`front-produkt.php`: „6 zrzutów", „10 par",
  „30 maili demo") — dziś prawdziwe (zmierzone w r1 #172), ale bez strażnika zgniją po cichu
  jak każda liczba; wzór: `dokumentacja-zgodnosc*.sh`.
- **Regulamin sklepu (strona DRAFT #120)**: dopisać gwarancję satysfakcji 14 dni (D31)
  i tryb wygaśnięcia aktualizacji przy zwrocie — przy finalizacji regulaminu w K8.
