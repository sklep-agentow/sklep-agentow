# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.25` (delta nad `v0.9.24`)

Data: 2026-08-30 · Zakres: audyt końcowy K7 (6 soczewek, 104 cele) + naprawy K7b + audyt delty 0.9.24 (mail HTML) + audyt
delty 0.9.25 (płatność osadzona, dane do faktury, reconciliation po K10 — PR `#222`, 4 rundy) + test instalacji od zera
przez niezależnego testera (K10) + przelot prób kontrolnych i bramka końcowa na `main` `2992247` · KRYTYCZNE: 0 · DUZE: 0 · kontroli FAIL: 2 (z 13) · **K7: NO-GO · EXIT=1** (kontrole 2 i 6 — te same co przy 0.9.23 i 0.9.24: decyzja właściciela 28.08; zestaw 6 prób z EXIT≠0 identyczny jak na `a1b2495` — powody w raporcie)

Ten plik jest **wskaźnikiem maszynowym** dla bramki oddania (hook `oddanie-guard` czyta liczniki
w nagłówku). Treść audytu — liczby z komendami, decyzje z powodami, długi i miejsca dowodów —
jest w jednym miejscu: **`audyt/RAPORT-ODDANIA.md`** (§6 = dopisek 0.9.24, §7 = dopisek 0.9.25 + K10). Rubryka GOTOWE:
`rubryka-GOTOWE.md` (✅ 6 · 🐞 5 · ⛔ 1 — #12 drugi człon, oddanie decyzją; **bez zmian przy 0.9.25**: kryteria #1 (płatność
i domknięcie) i #2 (mail) przechodzą tymi samymi strażnikami, rozszerzonymi — ścieżka hostowana zostaje zapasem, więc dowody
L1-11 dalej opisują żywą ścieżkę). Rejestr znalezisk: `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` (0 KRYTYCZNE/DUŻE otwartych,
0 DO WERYFIKACJI). Raporty audytorów: `audyt/sklep/raporty/` (15 plików, 0 odrzuconych werdyktów).
Tester niezależny K10: `audyt/K10-INSTALACJA-OD-ZERA-0.9.24.md` (1 wada produktu → naprawiona w 0.9.25).

Dwie kontrole przed oddaniem, których żąda bramka: krytyk = rundy recenzji `#213` ×6, `#214` ×3, `#220` ×3, `#221` ×1,
**`#222` ×4** (świeży agent na każdą rundę; r4: 1 WAŻNA naprawiona, 2 DROBNE → CO-WISI); audytor spójności =
`narzedzia-qa/k7-koniec.sh` (13 kontroli, przelot prób na `2992247`) + `narzedzia-qa/paczka-zgodnosc.sh` (zip = repo co do sumy)
+ `narzedzia-qa/pakuj-dla-klienta.sh` (17 kontroli, `sklep-agentow-KLIENT-0.9.25.zip` sha `4cc66c38…`).

Delta 0.9.25 sprawdzona dodatkowo: strażnik maila 28/28 (C3, C3b — dane do faktury), strażnik reconciliation
`testy/reconciliation-wynik-i-przerwa-test.php` 6/6 (na 0.9.24 pada 5/5), E2E ścieżki zapasowej z danymi faktury
(zam #9901178, mail z blokiem DANE DO FAKTURY), E2E osadzony z prawdziwymi kluczami do kliknięcia „Zapłać” (dalej hCaptcha
Stripe — kod 3, dowód ręczny), `bramki.sh` 10/10, zgodność dokumentacji 0 rozjazdów, próba kontrolna 21/21.
