# 06 — MAPA POLOWANIA SKLEPU (K7) — checklista z licznikiem

Zasady: cel odhacza KOORDYNATORKA (nie audytor), skryptem `narzedzia-qa/mapa-licznik.sh`, dopiero gdy istnieje
raport z DOWODEM (plik:linia / wyjście komendy) i SHA paczki, na której szedł audyt. Statusy: ⬜ nietknięty ·
✅ zamknięty z dowodem · 🐞 znalezisko (wpis w rejestrze) · ⚠️ anomalia `AN-nnn` · ⛔ poza K7 (decyzja właściciela).
Licznik mówi o POKRYCIU, nie o jakości. Mapa ZAMROŻONA po rundzie B (wiersz `ZAMROZONA:` pod licznikiem = SHA + liczba celów; strażnik `mapa-licznik.sh --sprawdz-zamrozenie`).
Format wiersza: `- ⬜ ID · cel · [runda/raport · SHA]`.

## L1 — ZGODNOŚĆ Z ZAMÓWIENIEM (prompt 4a; surowe zdania + MAPA-SPEC; pole JAK CZŁOWIEK TEGO UŻYWA)
- ✅ L1-01 · S-01 dwa produkty w sklepie (lista + strony) · [B/L1.json · 7e0cf726]
- ✅ L1-02 · S-02 mail po zakupie: potwierdzenie + instrukcje + link · [B/L1.json · 7e0cf726]
- 🐞 L1-03 · S-03 strony produktów: co robi, jak działa, ile czasu oszczędza, cena · [B/L1.json · SK-L1-001 DROBNA · 7e0cf726]
- ✅ L1-04 · S-04 pulpit: kiedy / za ile / w jakim czasie (rozkład w czasie) · [B/L1.json · 7e0cf726]
- ✅ L1-05 · S-07 dane kupujących i płatności w bazie (email + transakcja) · [B/L1.json · 7e0cf726]
- ✅ L1-06 · S-08 produkty: pełny CRUD; deaktywacja zamiast kasowania przy zamówieniach · [B/L1.json · 7e0cf726]
- ✅ L1-07 · S-09 dziennik logowań: kto, kiedy, konkrety, także NIEUDANE · [B/L1.json · 7e0cf726]
- ✅ L1-08 · S-14 tryb testowy jawny (baner) · [B/L1.json · 7e0cf726]
- ✅ L1-09 · S-15 konfigurowalność w Ustawieniach (przenośność) · [B/L1.json · 7e0cf726]
- ✅ L1-10 · S-18/S-19 wszystko w WordPressie (tabele, panel, Stripe z serwera WP) · [B/L1.json · 7e0cf726]
- ⬜ L1-11 · kryteria GOTOWE §12 #1–#11 — werdykt per kryterium (11 wierszy w raporcie) ·
- ✅ L1-12 · „kiedy kupili": znacznik czasu zakupu — gmdate vs czas lokalny w pulpicie / CSV / mailu · [B/L1.json · 7e0cf726]

## L2 — BEZPIECZEŃSTWO + RODO (atakujący; wykonanie na stosie; konto subscriber)
- ✅ L2-01 · 7 handlerów panelu: nonce + `manage_options` PARĄ (test z subscriber + bez nonce + z cudzym noncem) · [B/L2-1.json · 7e0cf726]
- ✅ L2-02 · `sklepag_kup`: nonce, zgoda, limit żądań · [B/L2-1.json · 7e0cf726]
- ✅ L2-03 · `sklepag_pobierz`: token / termin / limit / HEAD / dwa naraz przy ostatnim slocie · [A/L2.json · 425a3124]
- ✅ L2-04 · `sklepag_ocen` GET/POST: token stałoczasowo, pułapka, sufity, limit, zgoda · [B/L2-1.json · 7e0cf726]
- ✅ L2-05 · webhook: podpis zły / powtórzony / spóźniony; trasa poza limiterem · [A/L2.json · 425a3124]
- ✅ L2-06 · `/status`: identyfikator sesji, limit 30 — enumeracja? · [B/L2-1.json · 7e0cf726]
- ✅ L2-07 · cena i produkt wyłącznie z bazy (podmiana w żądaniu ignorowana) · [A/L2.json · 425a3124]
- ✅ L2-08 · limity ZMIERZONE (kup 10 / status 30 / login 6 — okno) + `sklepag_ip()` za proxy · [A/L2.json · 425a3124]
- ✅ L2-09 · katalog chroniony: bezpośredni GET, listing, path traversal w nazwie pliku · [A/L2.json · 425a3124]
- ✅ L2-10 · `$wpdb->prepare` wszędzie; `LIKE` z `esc_like` · [B/L2-1.json · 7e0cf726]
- ✅ L2-11 · XSS: opisy produktów, imię/treść opinii (front + panel), tabele panelu, komunikaty · [B/L2-1.json · 7e0cf726]
- ✅ L2-12 · wstrzyknięcie nagłówków maila (nadawca z ustawień, Reply-To) · [B/L2-1.json · 7e0cf726]
- 🐞 L2-13 · **martwy nonce pod page-cache: strona produktu z noncem sprzed 25 h → zakup gościa** · [B/L2-2.json · SK-L-002 · 7e0cf726]
- ✅ L2-14 · lockout WŁAŚCICIELA: po pomyłkach ile czeka, sufit 60 s, brak twardej blokady — zmierzyć · [B/L2-2.json · 7e0cf726]
- ✅ L2-15 · sekrety poza logami / CSV / `mail_tresc` / eksportem; `autoload=no` · [B/L2-2.json · 7e0cf726]
- ✅ L2-16 · anonimizacja obejmuje zamówienia + opinie + zdarzenia + dziennik (IP) · [B/L2-2.json · 7e0cf726]
- ✅ L2-17 · uninstall kasuje sekrety i cron ZAWSZE (obie ścieżki) · [B/L2-2.json · 7e0cf726]
- ✅ L2-18 · eksporter/eraser danych osobowych WP (`wp_privacy_personal_data_*`): jest / nie ma · [A/L2.json · 425a3124]
- ⬜ L2-19 · oficjalny `/security-review` na main (osobny przebieg, wynik w rejestrze) ·
- ✅ L2-20 · szablony prawne bez cudzych danych; polityka wymienia Stripe i dziennik 90 dni · [B/L2-2.json · 7e0cf726]
- ✅ L2-21 · `.github/workflows`: `pull_request_target`, akcje przypięte, uprawnienia tokenu · [A/L2.json · 425a3124]
- ✅ L2-22 · dziennik: kto może czytać (manage_options), user_agent/IP = dane osobowe → retencja · [B/L2-2.json · 7e0cf726]

## L3 — PIENIĄDZE: DOWÓD SKUTKU + WYŚCIG + PO SPRZEDAŻY (wykonanie; Stripe CLI; awarie w kontenerze)
- ✅ L3-01 · webhook ×2 = 1 zamówienie, 1 mail · [B/L3.json · 7e0cf726]
- ✅ L3-02 · webhook ‖ cron równolegle = 1 mail, 1 ważny token · [A/L3.json · 425a3124]
- ✅ L3-03 · przerwanie przed mailem → ponowienie DOKAŃCZA (RESUME, 500 celowe) · [B/L3.json · 7e0cf726]
- 🐞 L3-04 · `mail_blad` / `mail_w_toku` → cron podnosi · [A/SK-L3-003 · L3.json · 425a3124]
- 🐞 L3-05 · resend z panelu: stary token 403, nowy 200 · [B/L3.json · SK-L3-004 · 7e0cf726]
- ✅ L3-06 · 2 pobrania przy ostatnim slocie → dokładnie 1 · [B/L3.json · 7e0cf726]
- ✅ L3-07 · HEAD nie zużywa limitu · [B/L3.json · 7e0cf726]
- ✅ L3-08 · link po terminie 72 h = odmowa z komunikatem · [B/L3.json · 7e0cf726]
- ✅ L3-09 · Stripe timeout 15 s → stan zamówienia + co widzi kupujący · [B/L3.json · 7e0cf726]
- ✅ L3-10 · wynik `$wpdb->insert/update` sprawdzany: zakup, opinie, produkty, resend · [B/L3.json · 7e0cf726]
- ✅ L3-11 · budżet czasu reconciliation 8/15 s · [B/L3.json · 7e0cf726]
- ✅ L3-12 · snapshot produktu w zamówieniu vs edycja produktu · [B/L3.json · 7e0cf726]
- ✅ L3-13 · zakup produktu nieaktywnego / bez pliku = odmowa · [B/L3.json · 7e0cf726]
- ⬜ L3-14 · kryteria #2–#5 przelotem `testy/e2e/przelot2.js` ×2 (mail ≤ 60 s zmierzony) ·
- 🐞 L3-15 · Stripe 401 / 403 / 429 / 5xx → co widzi kupujący, co właściciel · [A/SK-L3-002 · L3.json · 425a3124]
- 🐞 L3-16 · zły / pusty klucz Stripe = sygnał w panelu, nie cicha awaria · [A/SK-L3-002 · L3.json · 425a3124]
- ✅ L3-17 · zmiana sekretu webhooka w ustawieniach → stare podpisy odrzucane · [B/L3.json · 7e0cf726]
- ✅ L3-18 · `payment_status=unpaid` (BLIK testowy / metody asynchroniczne) NIE domyka · [B/L3.json · 7e0cf726]
- 🐞 L3-19 · ZWROT w Stripe (refund/dispute) → status zamówienia, ważność linku, KPI · [B/L3.json · SK-L3-001 · 7e0cf726]

## L4 — DANE: CO ZOSTAJE, CO PRZEŻYWA (SQL + wp-cli; od strony ZAPISU)
- 🐞 L4-01 · aktualizacja W MIEJSCU: 0.2.0 (schemat 3) → 0.9.13 z danymi · [A/SK-L4-001 · L4.json · 425a3124]
- ✅ L4-02 · aktualizacja W MIEJSCU: 0.5.0 (schemat 4) → 0.9.13 z danymi · [B/L4.json · 7e0cf726]
- ✅ L4-03 · aktualizacja W MIEJSCU: 0.9.8 (schemat 5) → 0.9.13 z danymi; ustawienia, katalog chroniony, cron · [B/L4.json · 7e0cf726]
- ✅ L4-04 · rollback (nowa → stara): zachowanie ZDEFINIOWANE, instrukcja mówi o kopii zapasowej · [A/L4.json · 425a3124]
- ✅ L4-05 · uninstall domyślny: dane zostają, sekrety i cron NIE · [B/L4.json · 7e0cf726]
- 🐞 L4-06 · uninstall pełny: diff tabel / opcji / stron / plików / cronów = pusty · [B/L4.json · SK-L4-002/003 · 7e0cf726]
- ✅ L4-07 · zombie cron (lista właściciela 23.08) — `testy/uninstall-zombie-cron-test.php` wykonany · [B/L4.json · 7e0cf726]
- ✅ L4-08 · cykl deaktywacja → aktywacja bez strat i bez notice · [B/L4.json · 7e0cf726]
- ✅ L4-09 · retencja 90 dni kasuje TYLKO stare (próba kontrolna) · [B/L4.json · 7e0cf726]
- ✅ L4-10 · mapa właścicieli: każda opcja / tabela / cron ma miejsce zapisu i miejsce sprzątania · [B/L4.json · 7e0cf726]
- 🐞 L4-11 · kontrakt INNER JOIN: opinia bez zamówienia → moderacja odmawia · [B/L4.json · SK-L4-003 · 7e0cf726]
- ✅ L4-12 · **WP-Cron bez ruchu**: strona bez odwiedzin przez godziny — czy druga droga domykania się wykona; instrukcja o cronie systemowym · [A/L4.json · 425a3124]
- ✅ L4-13 · utf8mb4: długości kluczy indeksów; `get_charset_collate` (fakt: jest) · [B/L4.json · 7e0cf726]
- ✅ L4-14 · `stos.sh` 1.3.1 okno na `wp_sklepag_*` — potwierdzić WYKONANIEM (`stos.sh tabele`, `wiersze wp_sklepag_zamowienia`); strażnik `stos-proba-kontrolna.sh` · [B/L4.json · 7e0cf726]
- ⬜ L4-15 · `LINIA-BAZOWA` ponownie: regresje 0; 3 czerwone wyjaśnione (dane stosu ×2, przyrząd ×1) ·
- ✅ L4-16 · migracje i aktywacja na PHP 8.1 / WP 6.7 (deklarowane minimum) bez notice · [B/L4.json · 7e0cf726]

## L5 — DOKUMENTACJA I PACZKA vs KOD (dwa kierunki; plugin-check; historia gita)
- 🐞 L5-01 · DOK-TECH §3 „7 tabel" vs §8 „6 tabel" (dbDelta = 7) · [B/L5.json · SK-L5-002 · 7e0cf726]
- ✅ L5-02 · DOK-TECH §8 „#170" — numer zgłoszenia w dokumencie klienta · [B/L5.json · 7e0cf726]
- 🐞 L5-03 · DOK-TECH §2 tabela plików bez `opinie.php` / `admin-opinie.php` / klas list · [B/L5.json · SK-L5-004 · 7e0cf726]
- ✅ L5-04 · readme.txt: każda liczba i obietnica → kod · [B/L5.json · 7e0cf726]
- ✅ L5-05 · INSTRUKCJA-INSTALACJI: etykiety menu, Krok 0, komendy WPISANE w terminal · [B/L5.json · 7e0cf726]
- ✅ L5-06 · CHANGELOG = wersja; `[Niewydane]` puste; treść wpisu 0.9.13 prawdziwa · [B/L5.json · 7e0cf726]
- ⬜ L5-07 · liczby stron produktów („6 zrzutów", „10 par", „30 maili") vs paczki botów ·
- ✅ L5-08 · kierunek ODWROTNY: każdy punkt wejścia z inwentarza (02) → opisany w dokumentacji · [B/L5.json · 7e0cf726]
- ✅ L5-09 · treść maila vs instrukcja (3 kroki, ważność, limit, opinia) · [B/L5.json · 7e0cf726]
- ✅ L5-10 · szablony prawne: 90 dni, Stripe jako procesor, gwarancja 14 dni (D31) · [A/L5.json · 425a3124]
- 🐞 L5-11 · bramka `slady` na rozpakowanej paczce (TODO/FIXME/notatki QA/adresy/PR/nazwiska) — wynik · [B/L5.json · SK-L5-001 (54) · 7e0cf726]
- ✅ L5-12 · **`plugin-check` (oficjalny WP.org) na 3 zipach** — wynik z kategoriami · [B/L5.json · 7e0cf726]
- ✅ L5-13 · `gitleaks --log-opts=--all` (pełna historia) · [B/bramki.sh gitleaks EXIT 0 · 7e0cf726]
- ✅ L5-14 · e-maile w historii commitów (fakt 25.08: 288/288 noreply) — zapis · [B/L5.json · 7e0cf726]
- ✅ L5-15 · readme Segregatora „Wymagania" (odroczone do następnego wydania bota) — odnotować · [A/L5.json · 425a3124]
- 🐞 L5-16 · nasza numeracja planu (`kryterium #`, `plan §`, `S-xx`, `K4`, `Dnn`) w tekstach paczki — UI wp-admin i komentarze · [recenzja PR #182 · 38f7ef8 → SK-L5-001]
- ✅ L5-17 · `testy/dokumentacja-zgodnosc-sklep.sh` (strażnik readme/CHANGELOG/DOK-TECH = kod) zielony na paczce · [A/L5.json · 425a3124]

## L6 — FRONT / UX (przyrządy + człowiek; NIEKALIBROWANA)
- ⬜ L6-01 · Lighthouse 4 kategorie ≥ 95 ×3 strony (z `blog_public=1` na czas pomiaru) ·
- ⬜ L6-02 · axe `--bez-ruchu` = 0 naruszeń ·
- ⬜ L6-03 · szelki 10/10 ×2 slugi, chromium + firefox ·
- ⬜ L6-04 · sonda pigułki sticky 5/5 ·
- ⬜ L6-05 · przelot człowieka (`PRZELOT-CZLOWIEKA-SKLEP.md`) — komplet ·
- ⬜ L6-06 · 3 kroki klawiaturowe (lista → produkt → zakup; lightbox; formularz opinii) ·

## Ł — ŁAMACZ (wykonanie; wyrocznia: po każdej awarii SELECT z `zdarzenia`)
- 🐞 Ł-01 · baza pada w połowie zakupu (kontener db zatrzymany) → stan + zdarzenie · [A/SK-L-001 · L.json · 425a3124]
- 🐞 Ł-02 · Stripe 5xx / timeout → stan + WPIS W ZDARZENIACH (wyrocznia) · [B/L.json · SK-L3-002/003 · 7e0cf726]
- ✅ Ł-03 · plik produktu skasowany z dysku → pobranie = komunikat, nie fatal; zdarzenie · [B/L.json · 7e0cf726]
- 🐞 Ł-04 · permalinki „Zwykłe" → notice w kokpicie; sklep/webhook · [B/L.json · SK-L-005 · 7e0cf726]
- 🐞 Ł-05 · page-cache: HTML strony produktu odtworzony z noncem sprzed 25 h → zakup · [A/SK-L-002 · L.json · 425a3124]
- ✅ Ł-06 · seed 10 k wierszy dziennika + 1 k zamówień → czas pulpitu, brak N+1 · [A/L-2.json · 425a3124]
- ✅ Ł-07 · 500 żądań (kup / status / pobierz) → limity, brak fatali · [A/L-2.json · 425a3124]
- ✅ Ł-08 · WP_DEBUG na czystym WP 6.7 / PHP 8.1: zero notice przy aktywacji, zakupie, deinstalacji · [B/L.json · 7e0cf726]
- 🐞 Ł-09 · zip 20 MB+ przy uploadzie produktu → walidacja, komunikat · [A/SK-L-003 · L.json · 425a3124]
- ✅ Ł-10 · dwa admin-posty naraz (przełącz produkt ×2, resend ×2) · [A/L-2.json · 425a3124]

## STRAŻNICY CI
- ⬜ CI-01 · wszystkie `*-proba-kontrolna.sh` (26) jednym przebiegiem — tabela EXIT ·
- ⬜ CI-02 · BS4: dla każdej bramki „nazwa ↔ to, co naprawdę liczy" ·

## LICZNIK
<!-- liczony skryptem narzedzia-qa/mapa-licznik.sh; nie edytować ręcznie -->
CELE: 104 · ✅ 72 · 🐞 19 · ⚠️ 0 · ⛔ 0 · ⬜ 13
ZAMROZONA: 5d3dcf9f0d99dedc1fe201c3e318f46d6f35ba5d · celow=104 · tresc=2d2a15920151 · 2026-08-26T06:11
