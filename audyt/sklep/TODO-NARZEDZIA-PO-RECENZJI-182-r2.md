# Uwagi recenzenta PR #182 runda 2 do NARZĘDZI K7 — do zamknięcia w PR-ze materiału, PRZED rundą A

Reguła: recenzent max 2 rundy na PR → paczka scalona, a te 7 uwag (wszystkie o rusztowaniu K7, nie o wtyczce)
naprawiamy w następnym PR-ze (`k7/material`), który dostaje własną recenzję. Każda uwaga była potwierdzona wykonaniem.

| # | uwaga | naprawa | status |
|---|---|---|---|
| W1 | `testy/dokumentacja-zgodnosc-sklep.sh` nie jest wołany przez żaden automat (CI, `bramki.sh`, allowlista recenzenta) | dopisać do `.github/RECENZENT-BRIEFING.md` obok dwóch bliźniaczych + do allowlisty `recenzent.yml` (⚠️ PR dotykający workflow nie dostaje auto-recenzji — osobny PR albo koordynatorka czyta) | ✅ 26.08 |
| W2 | `k7-koniec.sh` warunek 2 „proby ≤ 2" martwy: `awk '{p=$NF…}'` czyta PUSTĄ kolumnę po końcowym `\|`; próba kontrolna psuła FAIL i proby naraz, więc nie widziała | parsować kolumnę po indeksie (proby = 7. pole), próba kontrolna: osobny przypadek „PASS, proby=3" → NO-GO | ✅ 26.08 |
| W3 | test dok=kod sekcja C sprawdza 5 zaszytych etykiet w obu kierunkach, ale NIE liczbę pozycji menu — nowa pozycja w `admin.php` bez opisu w readme przechodzi | asercja domykająca: liczba `add_submenu_page` (bez ukrytej `sklepag-zamowienie`) == liczba pogrubionych etykiet menu w readme | ✅ 26.08 |
| W4 | nowa bramka bez dowodu, że POTRAFI paść (zasada dowodu CLAUDE.md / D14) | `testy/dokumentacja-zgodnosc-sklep-proba-kontrolna.sh`: kopia readme z podmienioną liczbą (72→48), etykietą, licencją → EXIT 1; czysty → EXIT 0 | ✅ 26.08 |
| D5 | `stos-proba-kontrolna.sh` nie zna `sklepag` — naprawa okna bez strażnika | dopisać przypadek „`tabele` pokazuje `wp_sklepag_*`" i „`wiersze sklepag_produkty` odpowiada" | ✅ 26.08 |
| D6 | opis PR mówił „bez argumentów = komplet jak dotąd" | ✅ poprawione `gh pr edit` przed scaleniem | ✅ |
| D7 | `k7-koniec.sh` PISZE do audytowanej mapy (woła `mapa-licznik.sh`, który przepisuje LICZNIK); nieznany status celu (np. `❓`) znika z licznika bez śladu | `mapa-licznik.sh --tylko-licz` (bez zapisu) używany przez `k7-koniec.sh`; nieznany znacznik po `- ` = FAIL z listą linii | ✅ 26.08 |
| W8 | `k7-koniec.sh` 7b porównywał goły `rev-parse k7-zamrozenie` — tag jest ADNOTOWANY, więc dostawał sha obiektu tagu (`5e92e0b`) zamiast commitu (`5d3dcf9`) = fałszywy FAIL na prawdziwym stanie; próba 16/16 tego nie widziała, bo jej atrapa miała tag goły | `k7-zamrozenie^{commit}`; atrapa próby z tagiem `-a` jak produkcja; dowód: na prawdziwym stanie `PASS 7b tag k7-zamrozenie = 5d3dcf9` | ✅ 26.08 |

**Zamknięte 26.08** (gałąź `k7/material`, jeden PR). Dowody: `k7-koniec-proba-kontrolna.sh` 20/20 EXIT 0 ·
`dokumentacja-zgodnosc-sklep-proba-kontrolna.sh` 10/10 przypadków (12 rozjazdów) EXIT 0 · `stos-proba-kontrolna.sh` EXIT 0 · `dokumentacja-zgodnosc-sklep.sh`
0 rozjazdów. W1 allowlista w `recenzent.yml` idzie OSOBNYM PR-em (PR dotykający workflow nie dostaje auto-recenzji — koordynatorka czyta).

## Po rundzie B (26.08)
- `SCHEMAT-RAPORTU.json` + `k7-koniec.sh` warunek 8: werdykt narzędziowy (`SPEŁNIONE`/`ZGODNE` z `dowod` = komenda, bez `plik:linia`) — dziś 5 legalnych werdyktów B pali warunek (AN-021).
- `k7-stos-rundy.sh` 1.0.3: `chown -R www-data` na `wp-content/{uploads,upgrade,plugins}` po `docker cp` (AN-017) + seed na życzenie (`--seed N`).
- `slady-wewnetrzne.sh`: wzorce kodów planu `S-\d\d`, `D\d\d`, `K\d`, `kryterium( GOTOWE)? #`, `PLAN-` (SK-L5-001/B: 54 trafienia niewidoczne dla bramki).
- `testy/dokumentacja-zgodnosc-sklep.sh` — klasa 11 (BS4) udowodniona w B ×2 (#194 W4): (a) „liczba tabel” czyta §3, a §8 kłamie prozą („6 tabel”, SK-L5-002); (b) „instrukcja obok paczek” sprawdza plik w REPO, nie w katalogu WYDANIA (SK-E-001). Naprawa: grep `[0-9]+ tabel` po CAŁYM dokumencie + sprawdzenie `boty/paczki/*.md` albo zmiana zdania w readme.
- DOPISEK rundy: OPcache `opcache_reset()` po `docker cp`; nonce panelu z HTML; `gitleaks` w `/tmp/bramki-narzedzia/` (AN-019, L5-13).

