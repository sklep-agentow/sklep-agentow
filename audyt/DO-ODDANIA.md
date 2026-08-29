# CO ZOSTAŁO DO ODDANIA — stan 2026-08-29

> Spisane na prośbę Dzidka. **Każda pozycja ma źródło** (bramka, plan, recenzja) — nic tu nie
> jest z pamięci. Termin: **1.09.2026**, próba 1 z 3, ocenia kolega.

## GDZIE JESTEŚMY W PROCESIE

| Etap | Stan | Dowód |
|---|---|---|
| K7 — audyt końcowy | ✅ **ZROBIONY** | mapa 104 cele, 0 ⬜ · rejestr 0 KRYTYCZNE/DUŻE · 0 anomalii otwartych · 15 raportów |
| K7b — naprawy po audycie | ✅ **ZROBIONE** | 23 cele 🐞 naprawione, każde zamknięcie ma strażnika (kontrola 3d PASS) |
| K8 — paczka i oddanie | 🔄 **TU JESTEŚMY** | — |

⛔ **Rundy recenzji PR to NIE audyt.** To zwykły cykl gałąź → PR → recenzja → scalenie.
Recenzent w CI ogląda JEDEN diff, audytor oglądał cały produkt względem zamówienia.

---

## 1. DOMKNĄĆ `#209` (w toku)
Runda 6 liczy. Pięć rund domkniętych, każda dotyczyła tekstu czytanego przez klienta.
⭐ **Reguła na dalej:** wraca do naprawy tylko to, co dotyka **pieniędzy, bezpieczeństwa albo
tekstu klienta**. Reszta → nazwany dług w `CO-WISI.md`.

## 2. ODŚWIEŻYĆ PRZETERMINOWANE DOWODY
⛔ **To nie jest usterka produktu — to zestarzały dowód**, i bramka końcowa to widzi:

```
FAIL 6 próby kontrolne: po przelocie (d66e6f5) zmieniło się to, czego on dowodzi
     — dowód przeterminowany (uruchom: bash narzedzia-qa/proby-kontrolne-przelot.sh)
```

- [ ] Po scaleniu `#209`: `bash narzedzia-qa/proby-kontrolne-przelot.sh` (drzewo musi być CZYSTE)
- [ ] `bash narzedzia-qa/k7-koniec.sh` **na samym końcu** — każda zmiana ją unieważnia

## 3. REJESTR KLAS BŁĘDÓW → `audyt/SCIAGA-EGZAMINATORA.md`
Osobną gałęzią ze świeżego `main` + PR (rejestr żyje w repo klienta).
Gotowy tekst: `/root/.claude/KLASY-Z-SESJI-29-08.md` (5 klas) **plus 3 z rund 4–6**:
- **zakres węższy niż wzorzec** — wyliczenia celowane w `wtyczki/`, ślad leżał w `dokumentacja/`
- **kalibracja odrzuciła własną bramkę** — rozszerzenie zasięgu padało na CZYSTEJ dokumentacji
- **gdy teren da się przeczytać, czytanie JEST wyliczeniem** — 263 wiersze, wzorzec przepuścił 3×

## 4. ⬜ DO ROZSTRZYGNIĘCIA — DRUGI PRZELOT, NA PACZCE
⛔ **Nie zgaduję, potrzebna decyzja.** Są DWA różne przeloty i mylą się ze sobą:
- `audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md` — **komplet 27/27**, robił agent-audytor na stosie
  warsztatowym; bramka `k7-koniec` kontrola 5 = **PASS**
- przelot **NA PACZCE** (stanowisko `:8892`, świeża instalacja) — zrobiony **rdzeń A6–A10**
  (zakup kartą → webhook → mail → pobranie, sha zgodne); reszta nie

`PLAN-PROJEKTU.md §12` mówi, że kryteria GOTOWE wykonuje się **NA PACZCE na świeżej instalacji**.
**Moja rekomendacja:** rdzeń A6–A10 na paczce wystarczy jako dowód §12 dla ścieżki pieniędzy;
reszty punktów NIE powtarzam na paczce, bo mierzą ten sam kod co przelot audytora, a paliwa
jest mało. ⛔ **Do klepnięcia albo odrzucenia — to twoja decyzja, nie moja** (zakres oddania).

## 5. DOKUMENTACJA ODDANIOWA — największa pozycja
Źródło wymagań: `PLAN-PROJEKTU.md` K8 + wzorzec z `mp-service-suite` (patrz pamięć:
`katalog-repozytoriow-projektu`).

**Braki (sprawdzone komendą, nie z pamięci):**
- [ ] ⛔ **SKLEP nie ma `INSTRUKCJA.md`** — oba boty mają. Instrukcja *instalacji* ≠ instrukcja
      *obsługi*: brak opisu, jak dodać produkt, obsłużyć zamówienie, wysłać mail ponownie.
      **Największa luka, dotyczy głównego produktu.**
- [ ] **Diagramy** — K8 żąda „diagram architektury dla informatyka". Jest jeden ASCII
      w segregatorze. Gotowiec: `mp-service-suite/build/render-diagramy.py` (HTML→PNG).
      Wzorzec nazw po ludzku: „z czego składa się system", „droga zakupu", „gdzie mieszkają dane".
- [ ] **Zrzuty w instrukcji instalacji: ZERO** (policzone). K8 żąda „zrzuty robione maszynowo".
      Mamy własne skrypty: `testy/zrzuty-instrukcje.js`, `testy/zrzuty-panelu.js`.
- [ ] **Sumy kontrolne zipów** — brak. K8: „zipy 3 wtyczek + sumy kontrolne".
- [ ] **`PONAD-SPECYFIKACJE` nie wchodzi do paczki** (`grep PONAD boty/buduj-paczki.sh` → 0)
      i istnieje tylko dla botów, nie dla sklepu. To **klasa 6 ściągi** (kod robi więcej niż spec).
- [ ] **`LICENSE` w korzeniu** — wtyczki mają swoje, repo nie.
- [ ] **Katalog `dla-klienta/`** wzorem MP + `PRZECZYTAJ-MNIE.txt` jako pierwszy plik dla laika.
- [ ] **README dla klienta** — nasze jest robocze: zero badge'ów, zero zrzutów, mapa dokumentów
      dla nas. Wzorzec MP: badge'y, ramka „chcesz tylko zainstalować?", szybki start, przepływ
      prozą, sekcja „Jak to wygląda" ZE ZRZUTAMI.

⚠️ **Nie kopiujemy MP żywcem:** MP miał 4 role użytkowników i 4 instrukcje. U nas są dwie —
właściciel strony i kupujący, a kupujący instrukcji nie potrzebuje.

**Szacunek: 5–6 godzin**, z gotowcami z MP.

## 6. RAPORT ODDANIA
Musi nieść JAWNIE:
- [ ] **NO-GO bramki `k7-koniec` z DWOMA powodami** (patrz niżej) — nie chowamy tego
- [ ] że **instalację robiła AUTORKA, nie osoba postronna** — rubryka żąda nie-autora
- [ ] długi z `audyt/CO-WISI.md`

## 7. WYDANIE
- [ ] tag + Release + zipy z sumami
- [ ] ⛔ **runbook: OTWORZYĆ stronę Releases i POTWIERDZIĆ OCZAMI, że „Latest" = oddawana
      wersja** — klasa 4 ściągi, fakt „kolega widzi 1.1.0"

---

## STAN BRAMKI KOŃCOWEJ: **NO-GO, 2 z 13**

| # | Kontrola | Stan | Co to znaczy |
|---|---|---|---|
| 2 | kalibracja | **FAIL** | soczewki L1/L5 bez PASS. ⭐ Kryterium klienta #12 (≥2/3) **SPEŁNIONE**: 7 mutantów, 5 złapanych = 71%. Bramka jest OSTRZEJSZA od rubryki. **Decyzja Dzidka 28.08: oddajemy z jawnym zapisem.** |
| 6 | próby kontrolne | **FAIL** | dowód przeterminowany moimi commitami — patrz pozycja 2 wyżej. Do odświeżenia, nie do naprawy. |

Pozostałe **11 na zielono**: mapa, rejestr (4 kontrole), anomalie, przelot, paczka, tag, raporty.

**TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: 0 · DUŻE otwarte: 0 · kontroli FAIL: 2 z 13.**

---

## DECYZJE CZEKAJĄCE NA DZIDKA (nie moje)
- ⬜ **Przelot na paczce** — pozycja 4 wyżej, z moją rekomendacją
- ⬜ **Sprzątanie ~49 MB:** `wymiana/k7-mutanty/` (3,9 MB, nieśledzony) + `boty/paczki/`
      (23 commitowane zipy, 45 MB, przez nie `.git/` waży 140 MB). ⛔ Nic nie kasuję bez zgody —
      zipy są dowodem wydań. ⚠️ Dysk maszyny **87%**.
- ⬜ **Commit warsztatu** — niezacommitowane skille w `/root/praca/warsztat`
      (⛔ gałąź ma ~960 widmowych kasowań, `git add -A` = katastrofa; dodawać ŚCIEŻKAMI)
