# ŚCIĄGA EGZAMINATORA — czego kolega szukał w 2 poprzednich projektach

Źródło: sweep repo `catsnboard` (4+ rundy recenzji kolegi, PR #57/#70/#82/#84/#87-92,
cytaty verbatim z CHANGELOG/PR) + `mp-service-suite` (AUDYT-RAPORT; „16 uwag" z
`test-kolegi-serwis-wp` to nasz red-team PRZED budową — symulacja jego stylu, nie
jego słowa). Zebrane 18.08 subagentem, 45 wywołań. **Kolega będzie szukał TYCH
SAMYCH klas — każda ma tu swoją bramkę u nas albo dopisaną akcję.**

## Co wytknął DOSŁOWNIE (najcenniejsze cytaty/fakty)
- catsnboard: paczka klienta zawierała **KOPERTĘ RECENZENCKĄ** z wewnętrzną notatką
  QA „nie dla klienta" (KRYTYCZNE, PR #57).
- catsnboard: *„w readme dalej jest «czego nie kasuj» xd, nie poprawiłeś tego"* —
  README niespójne z kodem, wytknięte DRUGI raz (PR #87).
- catsnboard: `uninstall.php` nie kasował 6 opcji **w tym KLUCZA API klienta** (PR #84).
- catsnboard: `LIKE '_transient_pnb_%'` bez escape — `_` to wildcard SQL, ryzyko
  kasowania CUDZYCH transientów (PR #84); *„cleanup transientów zawęzić do
  pnb_pl_pary i pnb_plc_* — wtedy będzie elegancko spójne"* (PR #89).
- catsnboard: badge wersji w README nieaktualny — **zapomniany 3×** (PR #70);
  dokumentacja podawała „10→20→40", kod robił „20→40→80→120 min".
- MP: **„kolega widzi 1.1.0"** — tag istniał, ale strona Releases pokazywała stare
  „Latest" (wydanie niepublikowane = dla niego nie istnieje).
- catsnboard: timeout PHP przy 25×retry (~100 s > limit hostingu 30 s);
  licznik dziennego limitu zjadany PRZED sprawdzeniem błędu sieci;
  `insertAdjacentHTML` bez escapowania.

## 7 KLAS BŁĘDÓW → nasze pokrycie → LUKI (zamknięte 18.08)

| # | Klasa (ile razy wracała) | Co ją łapie U NAS | Luka → akcja |
|---|--------------------------|-------------------|--------------|
| 1 | Uninstall niekompletny, zostają opcje/KLUCZ API (×3) | kryterium GOTOWE #9: diff bazy/plików przed-po PUSTY (mierzony); symetria zapis↔sprzątanie w tym samym PR | ✅ dopisane do briefingu: pełne czyszczenie MUSI usuwać klucz API właściciela |
| 2 | `LIKE` z `_`/`%` bez escape → dotyka cudzych danych | — (nie było reguły!) | ✅ NOWA reguła w briefingu+kości: każdy LIKE = `$wpdb->esc_like()` dla wartości, `\_` w stałych; wroga sesja: test „czy sprzątanie nie łapie cudzego wpisu o podobnej nazwie" |
| 3 | Dokumentacja/komentarz KŁAMIE względem kodu (×4) | sweep „co przestało być prawdą" po każdej naprawie (K7b); zimna weryfikacja PR pyta o zgodność opisu | ✅ K7 dostaje jawną soczewkę „dok vs kod": każda LICZBA i obietnica z README/instrukcji sprawdzona w kodzie |
| 4 | Wersje/badge/„Latest" nieaktualne (×4) | bramka wersji sprawdza wpis w CHANGELOG (lekcja MP); K8: JEDNO wydanie | ✅ runbook oddania: po tagu OTWORZYĆ stronę Releases i POTWIERDZIĆ, że „Latest" = oddawana wersja (fakt „kolega widzi 1.1.0") |
| 5 | Ślady wewnętrzne w paczce klienta (koperta QA, adresy dev) | K7: sweep śmieci .bak/.orig/lorem | ✅ sweep rozszerzony JAWNIE o: notatki QA/recenzenckie, adresy dev, TODO/FIXME, nazwiska wewnętrzne |
| 6 | Kod robi WIĘCEJ niż spec bez deklaracji | MAPA-SPEC: statusy INTERPRETACJA/ROZSZERZENIE jawnie | ✅ K8 dostaje plik `PONAD-SPECYFIKACJE.md` w paczce (wzorzec z MP): lista wszystkiego ponad literę notatki (historia rozmów bota, accuracy, log akcji...) |
| 7 | Licznik/stan często zapisywany w `wp_options` (MP #3) | tabele własne na dane sklepu | ✅ briefing: liczniki wywołań AI (stop-loss) do TABELI bota, nie do opcji |

## Dodatkowe akcenty pod tego egzaminatora
- Publikacja wydania = częścią oddania (nie sam tag) — sprawdzić OCZAMI stronę Releases.
- Retry/limity: liczyć DOPIERO po sukcesie operacji (licznik zjadany przy awarii = jego uwaga).
- Timeouty zewnętrznych wywołań < limit PHP hostingu (jego uwaga o 100 s > 30 s) —
  nasz `wp_remote_post` do Stripe/AI: timeout 15 s, jeden retry, nigdy pętla ślepa.
- README/instrukcja czytana przez niego ZDANIE PO ZDANIU dwa razy — niespójność
  wytknięta ponownie boli podwójnie („nie poprawiłeś tego xd").
