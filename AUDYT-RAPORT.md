# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.27` (delta nad `v0.9.26`)

Data: 2026-08-30 · Zakres: audyt końcowy K7 (6 soczewek, 104 cele) + naprawy K7b + audyt delty 0.9.24 (mail HTML) + audyt
delty 0.9.25 (płatność osadzona, dane do faktury, reconciliation po K10 — PR `#222`, 4 rundy) + test instalacji od zera
przez niezależnego testera (K10) + audyt delty 0.9.26 (canonical stron sklepu, klasa `is_page( 0 )` — PR `#225`, 4 rundy;
dokumenty SECURITY/CONTRIBUTING — PR `#226`, 3 rundy; delta 0.9.27: HEAD-404, podpis webhooka jako strażnik CI, Yoast/RankMath,
has_action prio 0, zdanie o skrócie — długi z CO-WISI decyzją Dzidka 31.08) + bramka końcowa i przelot prób kontrolnych
na `e95de75` (docs nad `a3d9568`, kod wtyczek identyczny — log w repo) · KRYTYCZNE: 0 · DUZE: 0 · kontroli FAIL: 2 (z 13) · **K7: NO-GO · EXIT=1** (kontrole 2 i 6 — te same co przy 0.9.23 i 0.9.24: decyzja właściciela 28.08; na `e95de75` 5 z 31 prób z EXIT≠0 — podzbiór zestawu 6 z `a1b2495`/`2992247`, żadnej nowej; powody w raporcie)

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
`narzedzia-qa/k7-koniec.sh` (13 kontroli na `a3d9568`; przelot prób na `e95de75`, log `audyt/sklep/PROBY-KONTROLNE.log`) + `narzedzia-qa/paczka-zgodnosc.sh` (zip = repo co do sumy)
+ `narzedzia-qa/pakuj-dla-klienta.sh` (17 kontroli, `sklep-agentow-KLIENT-0.9.26.zip` sha `6d3ee000…`; `sklep-agentow-0.9.26.zip` sha `38f78381…`).

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
padają, czysty EXIT=0); strażnik SEO kroki A–H (H: symulacja Yoast stałą `WPSEO_VERSION` → nasz canonical milczy), 3 mutanty
padają; `bramki.sh --tryb pelny` 10/10; zgodność dokumentacji 0 rozjazdów; `paczka-zgodnosc.sh` EXIT=0 (0.9.27).
