# AUDYT-RAPORT — Sklep Agentów, wydanie `v0.9.24` (delta nad `v0.9.23`)

Data: 2026-08-30 · Zakres: audyt końcowy K7 (6 soczewek, 104 cele) + naprawy K7b + audyt delty 0.9.24 (mail HTML, dane
sprzedawcy — PR `#220`, 3 rundy) + przelot prób kontrolnych i bramka końcowa na `main` `d0a45a9` · KRYTYCZNE: 0 · DUZE: 0 · kontroli FAIL: 2 (z 13) · **K7: NO-GO · EXIT=1** (kontrole 2 i 6 — te same co przy 0.9.23: decyzja właściciela 28.08; 6 z 30 prób z EXIT≠0 to IDENTYCZNY zestaw jak na `a1b2495`, delta nie dołożyła żadnej — powody w raporcie)

Ten plik jest **wskaźnikiem maszynowym** dla bramki oddania (hook `oddanie-guard` czyta liczniki
w nagłówku). Treść audytu — liczby z komendami, decyzje z powodami, długi i miejsca dowodów —
jest w jednym miejscu: **`audyt/RAPORT-ODDANIA.md`** (§6 = dopisek 0.9.24). Rubryka GOTOWE z werdyktem per kryterium:
`rubryka-GOTOWE.md` (✅ 6 · 🐞 5 · ⛔ 1 — #12 drugi człon, oddanie decyzją; **bez zmian przy 0.9.24**: żadne z 12 kryteriów
nie dotyczy wyglądu ani stopki maila, a link, zgoda i jeden mail na zakup — #2, #3 — dalej przechodzą tym samym strażnikiem).
Rejestr znalezisk: `audyt/sklep/REJESTR-ZNALEZISK-SKLEP.md` (0 KRYTYCZNE/DUŻE otwartych, 0 DO WERYFIKACJI — kontrole 3a–3d
`narzedzia-qa/k7-koniec.sh`). Raporty audytorów: `audyt/sklep/raporty/` (15 plików, 0 odrzuconych werdyktów).

Dwie kontrole przed oddaniem, których żąda bramka: krytyk = sześć rund recenzji `#213`, trzy `#214` i **trzy `#220`**
(świeży agent na każdą rundę, `.github/RECENZENT-BRIEFING.md`; `#220` r1: 2 WAŻNE + 2 DROBNE, r2: 1 WAŻNA + 3 DROBNE,
r3: 0 WAŻNYCH + 3 DROBNE → `audyt/CO-WISI.md`); audytor spójności = `narzedzia-qa/k7-koniec.sh` (13 kontroli, przelot prób
na `d0a45a9`) + `narzedzia-qa/paczka-zgodnosc.sh` (67/67 sum, „można zamrażać”) + `narzedzia-qa/pakuj-dla-klienta.sh`
(17 kontroli na rozpakowanej paczce `sklep-agentow-KLIENT-0.9.24.zip`, sha `4b0fafc2…`).

Delta 0.9.24 sprawdzona dodatkowo: strażnik `testy/mail-po-zakupie-test.php` 26/26 (próba kontrolna: 2 podłożone wady → 2 BLAD),
pełny zakup E2E na czystym WP 6.9.4 z motywem strony (`mail_wyslany`, mail w łapaczu = multipart/alternative, 1 tekst + 1 HTML),
render HTML 720/390 px, `bramki.sh` 10/10, zgodność dokumentacji sklepu 0 rozjazdów (próba kontrolna 21/21).
