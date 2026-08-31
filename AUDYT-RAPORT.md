# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.28` (delta nad `v0.9.27`)

Data: 2026-08-31 · Zakres: audyt końcowy K7 (6 soczewek, 104 cele) + naprawy K7b + audyt delty 0.9.24 (mail HTML) + audyt
delty 0.9.25 (płatność osadzona, dane do faktury, reconciliation po K10 — PR `#222`, 4 rundy) + test instalacji od zera
przez niezależnego testera (K10) + audyt delty 0.9.26 (canonical stron sklepu, klasa `is_page( 0 )` — PR `#225`, 4 rundy;
dokumenty SECURITY/CONTRIBUTING — PR `#226`, 3 rundy; delta 0.9.27: HEAD-404, podpis webhooka jako strażnik przelotu ręcznego, Yoast/RankMath,
has_action prio 0, zdanie o skrócie — długi z CO-WISI decyzją właściciela 30.08 ~22:15) + bramka końcowa i przelot prób kontrolnych
na commicie FINALNYM tej gałęzi (log w repo — sekcja delty 0.9.27 niżej niesie sha i liczby) + audyt delty 0.9.28 (kompensacja
poczty ze śladu `dor_*` schemat 9, `mail_blad` pod własnością tokenu, anonimizacja RODO czyści `mail_tresc`, wygląd CENA/kafle —
PR `#234`, 7 rund do 0 KRYT/0 WAŻ; zamrożenie paczki i rejestr długów — PR `#235`, 2 rundy; sekcja delty 0.9.28 na końcu) · KRYTYCZNE: 0 · DUZE: 0 · kontroli FAIL: 2 (z 13) · **K7: NO-GO · EXIT=1** (kontrole 2 i 6 — te same co przy 0.9.23 i 0.9.24: decyzja właściciela 28.08; liczby i sha przelotu rozstrzyga nagłówek `audyt/sklep/PROBY-KONTROLNE.log` — sekcja delty 0.9.27 niżej; powody w raporcie)

Ten plik jest **wskaźnikiem maszynowym** dla bramki oddania (hook `oddanie-guard` czyta liczniki
w nagłówku). Treść audytu — liczby z komendami, decyzje z powodami, długi i miejsca dowodów —
jest w jednym miejscu: **`audyt/RAPORT-ODDANIA.md`** (§6 = dopisek 0.9.24, §7 = dopisek 0.9.25 + K10, §8 = dopisek 0.9.26). Rubryka GOTOWE:
`rubryka-GOTOWE.md` (✅ 6 · 🐞 5 · ⛔ 1 — #12 drugi człon, oddanie decyzją; **bez zmian przy 0.9.25**: kryteria #1 (płatność
i domknięcie) i #2 (mail) przechodzą tymi samymi strażnikami, rozszerzonymi — ścieżka hostowana zostaje zapasem, więc dowody
L1-11 dalej opisują żywą ścieżkę). Rejestr znalezisk: `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` (0 KRYTYCZNE/DUŻE otwartych,
0 DO WERYFIKACJI). Raporty audytorów: `audyt/sklep/raporty/` (15 plików, 0 odrzuconych werdyktów).
Tester niezależny K10: `audyt/K10-INSTALACJA-OD-ZERA-0.9.24.md` (1 wada produktu → naprawiona w 0.9.25).

Dwie kontrole przed oddaniem, których żąda bramka: krytyk = rundy recenzji `#213` ×6, `#214` ×3, `#220` ×3, `#221` ×1,
**`#222` ×4**, **`#225` ×4** (r1: 2 WAŻNE, r2: 1 WAŻNA, r3: 3 DROBNE wzięte, r4: 1 WAŻNA o zdaniu w readme → 0.9.27 przez zamek paczki), **`#226` ×3** (8 uwag WAŻNYCH „tekst SECURITY vs kod” — wszystkie naprawione z plikiem i linią); audytor spójności =
`narzedzia-qa/k7-koniec.sh` (13 kontroli; przelot prób — log `audyt/sklep/PROBY-KONTROLNE.log`, sha w sekcji delty niżej) + `narzedzia-qa/paczka-zgodnosc.sh` (zip = repo co do sumy)
+ `narzedzia-qa/pakuj-dla-klienta.sh` (17 kontroli; sumy paczek 0.9.27 w notatce wydania i Release).

Delta 0.9.25 sprawdzona dodatkowo: strażnik maila 27/27 (C3, C3b — dane do faktury), strażnik reconciliation
`testy/reconciliation-wynik-i-przerwa-test.php` 6/6 (na kodzie 0.9.24 pada w całości), E2E ścieżki zapasowej z danymi faktury
(zam #9901178, mail z blokiem DANE DO FAKTURY), E2E osadzony z prawdziwymi kluczami do kliknięcia „Zapłać” (dalej hCaptcha
Stripe — kod 3, dowód ręczny), `bramki.sh` 10/10, zgodność dokumentacji 0 rozjazdów, próba kontrolna 21/21.

Delta 0.9.26 sprawdzona: strażnik `testy/seo-canonical-test.php` kroki A–G (produkt, lista, gospodarz z `rel_canonical`, cudza
strona, 404, pusta opcja sklepu/„Dziękujemy” — meta, canonical, assety), próba kontrolna z 3 mutantami (stan sprzed naprawy,
„drukuje zawsze”, `is_page( 0 )` bez bramki) — każdy złapany, czysty kod EXIT=0; recenzent odtworzył mutanty własną sondą
na stosie; żywy HTML na motywie gospodarza; `grep -rnE 'is_(page|single|singular)\(' wtyczki/` → tylko pomocnik;
`bramki.sh --tryb pelny` 10/10; zgodność dokumentacji 0 rozjazdów; `paczka-zgodnosc.sh` EXIT=0. Przelot prób na `e95de75`
(log w repo): 31 uruchomionych, 5 z EXIT≠0 — **podzbiór zestawu 6 z logu 0.9.25** (`dowod-z-paczki` EXIT=2, `blad-klucza`,
`ip-za-proxy`, `nonce`, `pulapka` — próby ChatBota na tym stosie, §7; `przejecie` tym razem przeszła — chwiejna), delta 0.9.26 nie
dołożyła żadnej nowej.

Delta 0.9.27 sprawdzona: `testy/produkt-404-proba-kontrolna.sh` (czysty: 404 GET i HEAD, produkt 200/200, naturalne 404 WP
nietknięte; mutant „stan 0.9.26” widoczny — HEAD wraca do 200); `testy/webhook-podpis-test.php` A–G na PRAWDZIWEJ
`sklepag_stripe_weryfikuj_podpis` + próba kontrolna (mutant „przyjmuje każdy podpis” i mutant „bez progu świeżości” — oba
padają, czysty EXIT=0); strażnik SEO kroki A–H + E2 + D2 (H: symulacja Yoast stałą `WPSEO_VERSION` → nasz canonical milczy; E2: 404 produktu
bez canonicala TAKŻE u gospodarza z domyślnym `rel_canonical` WP — filtr `get_canonical_url` oddaje pusty adres,
skalibrowane: na kodzie sprzed naprawy E2 pada, EXIT=1 bez potoku; D2: cudza strona z doklejonym `?sklepag_produkt=`
przy podpiętym `rel_canonical` ZACHOWUJE canonical gospodarza — bramka strony sklepu w filtrze, skalibrowane:
na filtrze bez bramki D2 pada, EXIT=1 bez potoku), 3 mutanty
padają; `bramki.sh --tryb pelny` 10/10; zgodność dokumentacji 0 rozjazdów; `paczka-zgodnosc.sh` EXIT=0 (0.9.27).
Kompensacja tokenu w cronie: `resend-odnawia-limit-pobran-test.php` części 1–5 PASS (EXIT=0); na `zakup.php` z 0.9.26
CZĘŚĆ 4 FAIL (token_przywrocony=NIE, pobrania_uzyte 0 zamiast 3) — kalibracja stary/nowy kod. CZĘŚĆ 5 (CAS własności:
proces B przejmuje wysyłkę stale-claimem w trakcie pada maila A) skalibrowana na `zakup.php` z `4897d8a` (sprzed warunku
`$przywrocono > 0`): CAS-B mail_tresc_B_zachowana=NIE, opinia_hash_B_zachowany=NIE, RAZEM FAIL, EXIT=1 bez potoku. Przelot prób na `6a7922c` — MAIN po scaleniu, commit z historii wydania (log w repo; sha i liczby rozstrzyga naglowek loga, nie to zdanie): 33 uruchomionych, 6 z EXIT≠0 — znany zestaw z chwiejną `przejecie`; `statystyki` zielona (u K11 na czystym stanowisku: PASS 4/4 mutanty). Powtórka na main domyka rozjazd R-1 z raportu K11 (squash unieważnił sha gałęzi w poprzednim logu). `statystyki-proba-kontrolna.sh` (strażnik ChatBota) na `a2f9dc2` i `f02fc71` była ślepa na mutanta 3, na `9b86106` przeszła — CHWIEJNA, co potwierdza diagnozę „zależna od danych wspólnego stosu" (ChatBot NIETKNIĘTY tym PR-em: `git diff main --stat -- wtyczki/chatbot-live` → pusto); dług w CO-WISI ZAWĘŻONY: część „wada kodu strażnika” skreślona u K11 (PASS 4/4 mutanty na czystym stanowisku); zostaje izolacja przyczyny chwiejności na świeżym stosie.

Delta 0.9.28 sprawdzona (31.08, PR `#234` — 7 rund recenzji CI do stanu 0 KRYTYCZNYCH / 0 WAŻNYCH; wydanie PR `#235` — 2 rundy):
strażnik poczty `resend-odnawia-limit-pobran-test.php` rozszerzony do 7 części (nowe: CZĘŚĆ 2b resend-ze-śladu, CZĘŚĆ 6
„A padł w trakcie, B pada → wraca stan DORĘCZONY"; CZĘŚĆ 5 z asercją `wpis_mail_blad_w_dzienniku=0`), kalibracja PODŁOŻONĄ WADĄ
na kodzie z `origin/main` przez docker cp: RESEND-DOR/CAS-B/DOR FAIL → po przywrocie PASS. Strażnik RODO
`rodo-anonimizacja-pomiar-skutku-test.sh` rozszerzony ×2 z kalibracją na kodzie sprzed naprawy: (a) dane faktury w treści maila
— stary kod FAIL dokładnie na 1 nowej asercji (`tresc maila ZOSTALA`), naprawiony 26/26; (b) krok RENDERU prawdziwej
`sklepag_admin_zamowienie()` — stary kod FAIL na 2 asercjach gałęzi „niedomknięte", naprawiony 28/28 PASS, EXIT=0.
Przelot NA MAIN po squash `#234` (commit `78e53cf`, klasa 67): `bramki.sh` 10/10 EXIT=0 · RODO PASS EXIT=0 · poczta 7/7 EXIT=0 ·
`mail-po-zakupie` EXIT=0 · `dokumentacja-zgodnosc-sklep.sh` 0 rozjazdów EXIT=0 (próba kontrolna 21/21 w rundach CI).
Paczka: `buduj-paczki.sh` EXIT=0 (allowlista+zamek) · `paczka-zgodnosc.sh` „68 plików = repo co do SHA-256, można zamrażać"
EXIT=0 · `pakuj-dla-klienta.sh` 17 kontroli EXIT=0. Zamrożenie zipa na main: PR `#235` (`0433fc6`), recenzja 2 rundy
(r1 spłacona w całości; 2 DROBNE rejestru nieblokujące — CO-WISI). Uwagi WAŻNE delty (wszystkie NAPRAWIONE w rundach,
z kalibracją): r5 RODO — anonimizacja zostawiała kopię danych faktury w `mail_tresc` przy pomiarze meldującym sukces;
r6 — panel pisał „zamówienie niedomknięte" przy zanonimizowanym doręczonym. Mini-K12 (przelot na świeżym klonie tagu
`v0.9.28` z GitHuba, 31.08 ~10:45): `git log -1` = `0433fc6` (merge `#235`) · `git describe --tags` = `v0.9.28` ·
`sha256sum` zipa z klonu = `23a5273773e3946ef5883f2f13a58f6dee59edacba7ed3f823f6090a6f10c7fb` = digest assetu Release
(4 assety, digesty 4/4 zgodne z lokalnymi sumami; Release oznaczony Latest) · `dokumentacja-zgodnosc-sklep.sh` 0 rozjazdów
EXIT=0 · `paczka-zgodnosc.sh` „można zamrażać" EXIT=0. WSZYSTKO ZGODNE — bez rozjazdów.
