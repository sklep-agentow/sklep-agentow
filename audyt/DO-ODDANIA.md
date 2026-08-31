# CO ZOSTAŁO DO ODDANIA — stan 2026-08-30 (WYDANE `v0.9.23`)

> Spisane na prośbę właściciela. **Każda pozycja ma źródło** (bramka, plan, recenzja) — nic tu nie
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

Było (przed `#210`):
```
FAIL 6 próby kontrolne: po przelocie (d66e6f5) zmieniło się to, czego on dowodzi
     — dowód przeterminowany (uruchom: bash narzedzia-qa/proby-kontrolne-przelot.sh)
```
- [x] Po scaleniu `#209`: `bash narzedzia-qa/proby-kontrolne-przelot.sh` — **wykonane na `89ba47a`**,
      log w `audyt/sklep/PROBY-KONTROLNE.log` (`#210`). Kontrola 6 zmieniła POWÓD, nie wynik:
```
FAIL  6 próby kontrolne: 6 z 30 ma EXIT≠0 (przelot na 89ba47a)        ← k7-koniec.sh, EXIT=1
```
      Sześć padających to długi narzędziowe (`blad-klucza`, `ip-za-proxy`, `nonce`, `przejecie`,
      `pulapka` padają identycznie na czystym `main`; `dowod-z-paczki` ma zaszytą wersję 1.5.0) —
      opis w `CO-WISI.md`, sekcja o próbach kontrolnych. **Nie da się ich „odświeżyć” — to naprawa
      narzędzi, świadomie po oddaniu.**
- [x] `bash narzedzia-qa/k7-koniec.sh` — 30.08 12:35 na `a1b2495`: KRYTYCZNE 0 · DUŻE 0 · FAIL 2 z 13, EXIT=1 (log przelotu z tego samego commita w historii: `git show a1b2495:audyt/sklep/PROBY-KONTROLNE.log`; plik w drzewie jest po 0.9.24 nadpisany przelotem na `d0a45a9` — ten sam zestaw 6 prób; `audyt/RAPORT-ODDANIA.md` §6)

## 3. REJESTR KLAS BŁĘDÓW → `audyt/SCIAGA-EGZAMINATORA.md`
- [x] **ZROBIONE 30.08, PR `#211`: klasy 29–47** (4 z dziesięciu rund `#207`, 12 z rund `#208`/`#209`
      i kontroli po squashu, 3 z rund 4–6 `#209` o skanerze śladów — te ostatnie przeoczone w pierwszej
      wersji PR-a i dopisane po recenzji r1). Kontrola: każda z klas 29–47 w rejestrze dokładnie raz.

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

## 5. DOKUMENTACJA ODDANIOWA — ✅ ZROBIONE 30.08 (`#213`, `#214`)
Lista „braków” z 29.08 sprawdzona ponownie komendą (recenzja `#217`): 7 z 8 zamknięte —
`wtyczki/sklep-agentow/INSTRUKCJA.md` (+5 zrzutów panelu) · 3 diagramy PNG w `dokumentacja/diagramy/` ·
`SHA256SUMS.txt` z `pakuj-dla-klienta.sh` · `PONAD-SPECYFIKACJE.md` sklepu w paczce (allowlista) · `LICENSE`
w korzeniu · `dla-klienta/PRZECZYTAJ-MNIE.txt` · README dla klienta (5 badge’ów, 4 zrzuty; dziś: 4 badge’e — ślepa odznaka wydania zdjęta w #237, shields nie widzi prywatnego repo).
- [ ] **Zrzuty w `dokumentacja/INSTRUKCJA-INSTALACJI.md`: nadal ZERO** — jedyna otwarta pozycja; dług w `audyt/CO-WISI.md`
  (instrukcja instalacji jest tekstowa, kroki nazywają ekrany po nazwie).

## 6. RAPORT ODDANIA — ✅ ZROBIONE 30.08 (`#215`, `audyt/RAPORT-ODDANIA.md`)
Niesie jawnie: NO-GO 2 z 13 z powodami i zdaniem z CO-WISI o soczewkach L1/L5 · instalację od zera robiła świeża sesja
audytora (agent przez przeglądarkę, nie osoba postronna — K1 ✅ z tym zastrzeżeniem, jak w L1-11) · 11 długów z CO-WISI dotykających produktu/danych · Plugin Check
416/1 · minimum WP 6.7 / PHP 8.1 testowane w K7 (`ZAMROZENIE.md:8`), `phpstan.neon.dist` `phpVersion: 80100`.
Rubryka GOTOWE (12 kryteriów §12, werdykty 1:1 z L1-11: **6 ✅ · 5 🐞 z zastrzeżeniem · 1 ⛔** — #12 drugi człon
niespełniony, oddanie decyzją właściciela 28.08) i `AUDYT-RAPORT.md` z licznikami i `K7: NO-GO · EXIT=1` — `#216`.

## 7. WYDANIE — ✅ ZROBIONE 30.08
- [x] tag `v0.9.23` na `a3dd051` + Release z 4 zipami i sumami. Trzy liczby bramki liczone na `a1b2495`
  POZOSTAJĄ WAŻNE: `git diff --stat a1b2495..a3dd051 -- narzedzia-qa testy wtyczki boty/paczki` → pusto (tylko
  `audyt/`, `README.md`, rubryka), a `k7-koniec` kontrola 6 porównuje log z HEAD właśnie po tych katalogach (opis Release = `opis-wydania`, sumy z `sha256sum`)
- [x] Releases otwarte, „Latest” = **v0.9.23** — potwierdzone 30.08 13:10 (`gh release list` → `Latest v0.9.23`; pobrana paczka zbiorcza sha `94f31a50…` = lokalna)

---

## STAN BRAMKI KOŃCOWEJ: **NO-GO, 2 z 13**

| # | Kontrola | Stan | Co to znaczy |
|---|---|---|---|
| 2 | kalibracja | **FAIL** | soczewki L1/L5 bez PASS. ⭐ Kryterium klienta #12 (≥2/3) **SPEŁNIONE**: 7 mutantów, 5 złapanych = 71%. Bramka jest OSTRZEJSZA od rubryki. **Decyzja właściciela 28.08: oddajemy z jawnym zapisem.** |
| 6 | próby kontrolne | **FAIL** | przelot POWTÓRZONY na `89ba47a` (`#210`): 6 z 30 ma EXIT≠0. To sześć długów narzędziowych (pięć pada tak samo na czystym `main`, szósty ma zaszytą wersję 1.5.0) — patrz pozycja 2 wyżej. Nie wada produktu; naprawa narzędzi po oddaniu. |

Pozostałe **11 na zielono**: mapa, rejestr (4 kontrole), anomalie, przelot, paczka, tag, raporty.

**TRZY LICZBY DLA WŁAŚCICIELA: KRYTYCZNE otwarte: 0 · DUŻE otwarte: 0 · kontroli FAIL: 2 z 13.**

---

## DECYZJE CZEKAJĄCE NA WŁAŚCICIELA (nie moje)
- ⬜ **Przelot na paczce** — pozycja 4 wyżej, z moją rekomendacją
- ⬜ **Sprzątanie ~49 MB:** `wymiana/k7-mutanty/` (3,9 MB, nieśledzony) + `boty/paczki/`
      (23 commitowane zipy, 45 MB, przez nie `.git/` waży 140 MB). ⛔ Nic nie kasuję bez zgody —
      zipy są dowodem wydań. ⚠️ Dysk maszyny **87%**.
- ⬜ **Commit warsztatu** — niezacommitowane skille w `/root/praca/warsztat`
      (⛔ gałąź ma ~960 widmowych kasowań, `git add -A` = katastrofa; dodawać ŚCIEŻKAMI)

---

## 8. PORZĄDEK W REPO PRZED ODDANIEM — ✅ ZROBIONE 30.08 (decyzja właściciela)

Było 52 gałęzi zdalnych. Kasowanie **po stanie PR-a** (`gh pr list --state merged`), nie po
`git branch --merged` — po squashu ta komenda pokazywała 0 scalonych. Przed kasowaniem sprawdzone,
że żadna z kasowanych nie jest bazą otwartego PR-a (oba mają bazę `main`).
- 45 gałęzi ze scalonym PR-em — skasowane (treść jest w `main`, historię PR trzyma GitHub w `refs/pull/N`).
- 4 gałęzie bez PR-a (`awaria/revert-recenzent`, `cele-k7-l1-11-ci-01-02`, `koord/przelot-czlowieka`,
  `kosc-k1`) — najpierw tagi `archiwum/<nazwa>` wypchnięte na origin, potem skasowane.
- Zostały: `main` · `audyt/mutanty-sklep` (bank mutantów — czyta go `k7-koniec`, kasować NIE wolno) ·
  gałęzie otwartych PR-ów. Wynik: `git push origin --delete` × 49, 0 błędów; `git branch -r` → 4 pozycje.

## 9. SKILL DO STEROWANIA GITHUBEM — PO ODDANIU, nie teraz
właściciel 29.08: wiedza o prowadzeniu repo jest rozsypana, przydałby się **jeden** skill
z najważniejszymi informacjami. ⭐ Zgoda co do kierunku — i to jest robota **warsztatowa**,
nie zlecenia. Materiał już mamy: `github-wydania-klienta` (istnieje), klasy 20/22 ze ściągi
(squash i przenoszenie gałęzi), pamięć o bramkach lokalnych przed pushem, o odświeżaniu opisu
PR przed pushem i o tym, że auto-merge scala zanim recenzja spadnie.
⛔ **NIE robimy przed oddaniem** — termin 1.09, a to nie dotyka produktu klienta.
