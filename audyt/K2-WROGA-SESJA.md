# K2 — WROGA SESJA sklepu (audyt pierwszego przelotu, MOMENT 2)

Zasada: każdy atak ma PRÓBĘ KONTROLNĄ — dowód, że atak faktycznie uderzył
(dotarł do handlera), nie że odbił się o nieistniejącą ochronę wcześniej.
Lekcje: [[test-awarii-udowodnij-ze-awaria-zaszla]] [[zmierzylem-ochrone-zamiast-wady]]
[[audyt-dziel-po-pytaniu-nie-po-terenie]]. Stack: main w kontenerze (sha zgodne),
stripe listen żywy, motyw strony-gospodarza (realny front).

Część ataków już zmierzona przy poprawce #33 (patrz REJESTR-WERYFIKACJI):
webhook 2×, wyścig slotów A:200/B:410, HEAD nie zużywa, zły podpis→400,
obcy event z prawdziwym podpisem→200, licznik atomowy. Tu UZUPEŁNIAM resztę §9.

| # | Atak (klasa) | Próba kontrolna (że uderzył) | Wynik | Werdykt |
|---|--------------|------------------------------|-------|---------|
| A | Podmiana ceny w POST checkout (manipulacja płatności): `cena_grosze=1&unit_amount=1&amount_total=1` przy produkt_id=1 | nonce ważny przeszedł → POST DOTARŁ do handlera, sesja Stripe powstała | Stripe `amount_total=14900` = cena z bazy (wstrzyknięte 1 grosz zignorowane; kod w ogóle nie czyta ceny z POST) | ✅ OBRONIONE |
| B | Zakup bez zgody (prawo/RODO): POST kup z pominiętym polem `zgoda`, nonce ważny | ten sam nonce co w ataku A → odrzut jest za ZGODĘ, nie za nonce | HTTP 400, komunikat „…zgody…", sesja checkout NIE powstaje (walidacja przed utworzeniem) | ✅ OBRONIONE |
| C | Bezpośredni GET na chroniony plik (wyciek pliku, omijając bramę PHP): `/wp-content/uploads/sklepag-chronione-*/produkt-chatbot.bin` (nazwa historyczna z 18.08 — od 0.6.1 nowe pliki BEZ rozszerzenia, np. `produkt-hZAeUN…`; `.htaccess` deny obejmuje CAŁY katalog, więc wynik obowiązuje niezależnie od nazwy) | plik ISTNIEJE fizycznie (ls); front `/` = 200 (Apache serwuje normalnie) | GET pliku = 403, GET katalogu = 403 (Apache `.htaccess Require all denied`); brama PHP z tokenem = 200 (#33) | ✅ OBRONIONE |
| F | Plik produktu SKASOWANY z dysku, ważny token pobrania (obsługa braku) | świeży ważny token na zam. id=7, licznik=0; plik przeniesiony (file_exists→BRAK) | GET = **503** „…niedostępny", zdarzenie `pobranie_brak_pliku`, licznik **0** (nietknięty — odmowa PRZED slotem); przywrócony plik + ten sam token = 200, 215 B, licznik 1 | ✅ OBRONIONE |
| G | Rate limit ZAKUPU (§12 #5): 12 równoległych POST /kup z 1 IP, próg 10/min | bez zgody = odrzut 400 PO limiterze → zero wywołań Stripe; próba kontrolna licznika | **10×400 + 2×429**, licznik atomowy w tabeli = 12 (zero zgubionych) — limit zmierzony, nie zadeklarowany | ✅ OBRONIONE |
| D | LIKE-wildcard w uninstall (klasa 2 ściągi — kolega wytykał kasowanie cudzych): obce opcje `sklepagXcudza_wtyczka` (X w miejscu `_`) + `inna_wtyczka_ustawienia` | opcje założone PRZED uninstall (jest/jest) | uninstall (`LIKE 'sklepag\_%'` escaped) — obce **PRZEŻYŁY** („NIE KASUJ" nadal), podwójnie potwierdzone (rozbabrany + czysty stack) | ✅ OBRONIONE |
| E | Cykl aktywacja→deakt→akt (×2)+akt: śmieci? błędy? | debug.log wyzerowany przed | `cykl-ok`, **dokładnie 1** katalog chroniony (nie mnoży sierot — kod: `if istnieje return`), debug.log **0 B** | ✅ OBRONIONE |
| E2 | Pełny uninstall na SPÓJNYM userze (www-data): tabele/opcje/strony/katalog/cron | opcja czyszczenia ON, plik produktu + obce opcje, debug.log wyzerowany | tabele 0, opcje 0, katalog **USUNIĘTY czysto**, ZERO warningów, debug.log 0 B | ✅ OBRONIONE |

## ⚠️ ZNALEZISKO (nie blokujące egzaminu, hardening) — uninstall przy NIESPÓJNYM właścicielu plików
Pierwszy uninstall dał `Warning: rmdir(): Directory not empty` (uninstall.php:46) —
bo `.htaccess`+`index.php` należały do **root**, a uninstall biegł jako **www-data**
(artefakt: nocna aktywacja poszła jako root; [[test-klamal-uid-readonly-i-uid-kontenera]]).
Na CZYSTYM WP (egzamin = jeden user PHP) uninstall czysty — E2 dowodzi. ALE realny na
niektórych hostingach (deploy FTP userem X, PHP jako www-data): `unlink`/`rmdir` w
uninstall.php **nie sprawdzają wyniku** → goły PHP Warning w logu (kryterium #1: zero
notice). **Akcja: hardening uninstall.php** — kasować tylko `is_writable`, `rmdir`
tylko gdy katalog pusty (bez warninga gdy zostają obce/nieusuwalne pliki). Zgodne z
lekcją kolegi (uninstall = jego klasa 1).
