# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.23`

Data: 2026-08-30 · Zakres: audyt końcowy K7 (6 soczewek, 104 cele) + naprawy K7b + przelot prób
kontrolnych i bramka końcowa na `main` `a1b2495` · KRYTYCZNE: 0 · DUZE: 0 · kontroli FAIL: 2 (z 13) · **K7: NO-GO · EXIT=1** (kontrole 2 i 6 — decyzja właściciela 28.08, powody w raporcie)

Ten plik jest **wskaźnikiem maszynowym** dla bramki oddania (hook `oddanie-guard` czyta liczniki
w nagłówku). Treść audytu — liczby z komendami, decyzje z powodami, długi i miejsca dowodów —
jest w jednym miejscu: **`audyt/RAPORT-ODDANIA.md`**. Rubryka GOTOWE z werdyktem per kryterium:
`rubryka-GOTOWE.md` (✅ 6 · 🐞 5 · ⛔ 1 — #12 drugi człon, oddanie decyzją). Rejestr znalezisk: `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` (0 KRYTYCZNE/DUŻE
otwartych, 0 DO WERYFIKACJI — kontrole 3a–3d `narzedzia-qa/k7-koniec.sh`). Raporty audytorów: `audyt/sklep/raporty/`
(15 plików, 0 odrzuconych werdyktów).

Dwie kontrole przed oddaniem, których żąda bramka: krytyk = sześć rund recenzji `#213` i trzy `#214`
(świeży agent na każdą rundę, `.github/RECENZENT-BRIEFING.md`); audytor spójności = `narzedzia-qa/k7-koniec.sh`
(13 kontroli, w tym zgodność logu prób z commitem) + `narzedzia-qa/paczka-zgodnosc.sh` (66/66 sum) +
`narzedzia-qa/pakuj-dla-klienta.sh` (17 kontroli na rozpakowanej paczce).
