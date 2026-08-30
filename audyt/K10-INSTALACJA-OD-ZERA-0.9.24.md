# K10 — instalacja od zera z paczki Release `v0.9.24` przez NIEZALEŻNEGO testera (30.08, po wydaniu)

**Cel (Dzidek przy /koniec 30.08):** „instalację od zera jak klient niech zrobi ktoś z was — osobna sesja tmux”.
Różnica wobec K9: tester = **odrębna sesja Claude (Sonnet) w tmux z twardym deny** na kod i warsztat
(`/root/praca/zlecenia/k10-tester-stanowisko/.claude/settings.json`; próba kontrolna deny przed startem:
`Read` kodu → ZABLOKOWANE, `Read` karty startowej → DOZWOLONE), nie subagent. Metodologia K6 §Metodologia.

## Stanowisko
- Czysty WordPress 6.9.4 / PHP 8.4 (`lib-stos-probny.sh` `postaw_stos`, kontener `k10_wp`, :8892, baza `wp_k10`),
  `WP_DEBUG_LOG` włączony (próba kontrolna podłożonym notice'em przez HTTP → 1 wpis), motyw kolegi z klonu
  `MatthewPlugins/automatic-ai` (`wordpress/wp/theme/automatic-ai`), łapacz poczty przez mu-plugin `mailpit-smtp.php`
  (bez niego `wp_mail` na stosie pada — dług w CO-WISI), `stripe listen` → :8892.
- Tester dostał WYŁĄCZNIE: `KARTA-STARTOWA.md`, `BRIEFING-TESTERA.md`, `LISTA-PUNKTOW.md` (21 punktów),
  paczkę `sklep-agentow-KLIENT-0.9.24.zip` **pobraną z Release GitHuba** (sha `4b0fafc26363ee85…` = ta z opisu Release),
  playwright-core, konto Stripe testowe (klucz przez zmienną środowiskową, nigdy na ekranie).
- Koszt: sesja testera ~19 % kontekstu Sonneta, ~1 h 20 min zegara łącznie z Zadaniem 2 i 3.

## Wynik (raport testera verbatim: `/root/do-panelu/K10-0.9.24-tester/RAPORT-TESTERA.md`, 52 zrzuty; kopia
`/root/praca/k7-dowody-30-08/k10-tester-0.9.24/`)
**26 minut zegara** od Kroku 0 do aktywnych produktów w sklepie; z tego **~15 min pracy z instrukcją** (w limicie 30)
i ~10–11 min obchodzenia wad STANOWISKA. **22 ✅ · 11 🐞 · 6 😖 · 6 ⬜.**

### Wada PRODUKTU (naprawiona w 0.9.25, PR #222)
- Zakup opłacony (Stripe API: `payment_status: paid`, 199,00 PLN), webhook odrzucany (`webhook_zly_podpis` ×10 — sekret
  nie był ustawiony, patrz stanowisko), tester kliknął **„Dociągnij zaległe teraz” 71 s po zapłacie → nic**, strona
  „Dziękujemy” wisiała na „Przetwarzamy…” >20 min, Pulpit 0 zamówień. Diagnoza (Gosia, na żywym `k10_wp`): reconciliation
  pomija sesje młodsze niż 120 s **bez komunikatu**; WP-Cron na stosie nie ruszył (zepsuta pętla zwrotna — to samo
  pokazał edytor motywu: „Unable to communicate back with site”); ręczna reconciliation o 14:50:45 domknęła zamówienie
  #1 → `mail_wyslany`, mail w łapaczu. Do tego `ORDER BY utworzono LIMIT 20` = 20 porzuconych koszyków blokowało nowszą
  opłaconą sesję na dobę. Naprawa: wynik przycisku (domknięte/pominięte młodsze/otwarte/błędy) w notice i Dzienniku,
  najnowsze najpierw + przerwa 9 min po sprawdzeniu, instrukcje o WP-Cron; strażnik
  `testy/reconciliation-wynik-i-przerwa-test.php` 5/5 (na kodzie 0.9.24 pada 5/5).

### Wady STANOWISKA (nie produktu; tester poprawnie je rozpoznał)
- Limit uploadu 2 MB (obraz Dockera `upload_max_filesize`) i `uploads/` należący do roota — oba opisane w instrukcji
  „Najczęstsze problemy”; tester obszedł (kopia plików + chown) i zaznaczył, że instrukcja to przewidziała.
- **Sekret webhooka**: karta startowa obiecała `STRIPE_WEBHOOK_SECRET` w pliku sekretów — **nie było go** (moja pomyłka;
  do karty K11: `stripe listen --print-secret` przy stawianiu stanowiska).
- „Settings saved.” po angielsku — WP zainstalowany bez `--locale=pl_PL` (dług `lib-stos-probny.sh`).
- Łapacz poczty wspólny dla wielu stanowisk (57 cudzych maili) — dług: osobny Mailpit per stanowisko albo filtr.

### Długi instrukcji / UX (CO-WISI §K10)
- Theme File Editor cofa zapis przy zepsutej pętli zwrotnej — instrukcja nawigacji ma podać SFTP jako drogę główną.
- „po pozycji 08 licząc kolejny numer” nadal myli (dług K9, nie wszedł do 0.9.24/0.9.25).
- Stripe Adaptive Pricing pokazuje EUR zamiast PLN (instrukcja ostrzega; rozważyć krok „wyłącz w Dashboardzie”).
- Zły NIP w Ustawieniach czyści pole zamiast zostawić do poprawki.

### Co wyszło DOBRZE (z raportu)
Trzy wtyczki aktywują się bez błędu; ostrzeżenia Pulpitu (Stripe, dane sprzedawcy) dosłownie i po polsku, znikają po
uzupełnieniu; walidacja NIP w Ustawieniach z czytelnym komunikatem; link „Sklep” w motywie kolegi identycznie stylizowany
(desktop, mobile „08”, stopka); strona produktu, zgoda (bez zgody nie przechodzi), przekierowanie na prawdziwy Checkout,
płatność 4242 przeszła po stronie Stripe. Tester: „ekran powitalny ChatBota po polsku z trzema krokami — ładny, spójny”.

## Werdykt
Instalacja jak klient: **ZALICZONA w czasie** (15 min pracy z instrukcją). Ścieżka pieniędzy: **wada realna, naprawiona
w 0.9.25** — na hostingu bez WP-Cron i bez webhooka właściciel nie dostawał informacji, dlaczego przycisk nic nie zrobił.
