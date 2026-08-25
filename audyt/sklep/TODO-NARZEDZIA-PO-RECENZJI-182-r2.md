# Uwagi recenzenta PR #182 runda 2 do NARZĘDZI K7 — do zamknięcia w PR-ze materiału, PRZED rundą A

Reguła: recenzent max 2 rundy na PR → paczka scalona, a te 7 uwag (wszystkie o rusztowaniu K7, nie o wtyczce)
naprawiamy w następnym PR-ze (`k7/material`), który dostaje własną recenzję. Każda uwaga była potwierdzona wykonaniem.

| # | uwaga | naprawa | status |
|---|---|---|---|
| W1 | `testy/dokumentacja-zgodnosc-sklep.sh` nie jest wołany przez żaden automat (CI, `bramki.sh`, allowlista recenzenta) | dopisać do `.github/RECENZENT-BRIEFING.md` obok dwóch bliźniaczych + do allowlisty `recenzent.yml` (⚠️ PR dotykający workflow nie dostaje auto-recenzji — osobny PR albo koordynatorka czyta) | ⬜ |
| W2 | `k7-koniec.sh` warunek 2 „proby ≤ 2" martwy: `awk '{p=$NF…}'` czyta PUSTĄ kolumnę po końcowym `\|`; próba kontrolna psuła FAIL i proby naraz, więc nie widziała | parsować kolumnę po indeksie (proby = 7. pole), próba kontrolna: osobny przypadek „PASS, proby=3" → NO-GO | ⬜ |
| W3 | test dok=kod sekcja C sprawdza 5 zaszytych etykiet w obu kierunkach, ale NIE liczbę pozycji menu — nowa pozycja w `admin.php` bez opisu w readme przechodzi | asercja domykająca: liczba `add_submenu_page` (bez ukrytej `sklepag-zamowienie`) == liczba pogrubionych etykiet menu w readme | ⬜ |
| W4 | nowa bramka bez dowodu, że POTRAFI paść (zasada dowodu CLAUDE.md / D14) | `testy/dokumentacja-zgodnosc-sklep-proba-kontrolna.sh`: kopia readme z podmienioną liczbą (72→48), etykietą, licencją → EXIT 1; czysty → EXIT 0 | ⬜ |
| D5 | `stos-proba-kontrolna.sh` nie zna `sklepag` — naprawa okna bez strażnika | dopisać przypadek „`tabele` pokazuje `wp_sklepag_*`" i „`wiersze sklepag_produkty` odpowiada" | ⬜ |
| D6 | opis PR mówił „bez argumentów = komplet jak dotąd" | ✅ poprawione `gh pr edit` przed scaleniem | ✅ |
| D7 | `k7-koniec.sh` PISZE do audytowanej mapy (woła `mapa-licznik.sh`, który przepisuje LICZNIK); nieznany status celu (np. `❓`) znika z licznika bez śladu | `mapa-licznik.sh --tylko-licz` (bez zapisu) używany przez `k7-koniec.sh`; nieznany znacznik po `- ` = FAIL z listą linii | ⬜ |
