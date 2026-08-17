# ETAP 1 — bramki PRZED pierwszą linijką kodu (pełny opis: PROCES-PROJEKTU.md)
## GODZINA 0 — wysyłka do klienta (NAJPIERW, zegar czeka od razu)
- [ ] Arkusz 8-10 przypadków z odpowiedziami + 3 PRZYNĘTY (sprzeczne z jego regulaminem)
- [ ] Pytania o artefakty/obejścia (5 maili "coś nie tak" · Excel obok systemu · ostatnia
      ręczna poprawka · co gdy pracownik na urlopie)
- [ ] Prośba o zanonimizowaną kopię bazy + 6 pytań o hosting
- [ ] Każde niedomknięte pytanie: założenie domyślne + koszt zmiany + data
## BRAMKA A — wiemy, co klient chce (praca własna, równolegle)
- [ ] Mapa AS-IS→TO-BE (kto/na czym/decyzja/co dalej/wyjątki)
- [ ] Każda reguła ma ŹRÓDŁO; konflikt źródeł = STOP i pytanie
- [ ] Rejestr założeń niepotwierdzonych + MUST NOT per moduł
- [ ] Inwentaryzacja DANYCH na kopii bazy (założenia = zapytania SQL zwracające 0 wierszy)
- [ ] RECONSTRUCTION TEST: świeża sesja z samych materiałów klienta → diff z naszym planem
## BRAMKA B — klient potwierdził semantykę
- [ ] Arkusz wrócił Z ZAZNACZONYMI PRZYNĘTAMI (inaczej: nieważny)
- [ ] Pytania wartości na konkretach zadane · działy: rdzeń/potrzebne/miłe uzgodnione
## BRAMKA C — środowisko stoi, kość chodzi
- [ ] Środowisko-kopia klienta (bez dostępu: rejestr NIEZWERYFIKOWANE z ryzykiem+właścicielem)
- [ ] CHODZĄCA KOŚĆ: 1 zgłoszenie od formularza do maila przechodzi na środowisku-kopii
- [ ] Wroga sesja "cmentarzysko WP": 3 scenariusze konfliktów ekosystemu
## BRAMKA D — projekt po kości + krytyka dwuwarstwowa
- [ ] Projekt techniczny PO kości + mapa hooków + kontrakty; wagi per ODPOWIEDZIALNOŚĆ
- [ ] rubryka-GOTOWE.md TERAZ (ze scenariuszy; kryteria z fizycznym wzorcem)
- [ ] Krytyka A: reguły-bez-źródła · sprzeczności · co-zostaje-przy-padnięciu (3 listy)
- [ ] Krytyka B: gdzie implementacja spełni dokument, a złamie skutek
## BRAMKA E — wolno budować (binarnie, skrypt)
- [ ] Zero konfliktów źródeł i założeń wysokiego ryzyka · wszystko ma źródło+scenariusz+wagę
- [ ] Manifest zamrożony ✅(manifest-bramek.txt+strażnik) · sekret OAUTH + App recenzenta
      ✅(17.08: recenzja żywa na PR #18, Issue #9 zamknięte) · branch protection ⚠️NIEDOSTĘPNA
      (repo private+free, API 403) — zastępuje ją dyscyplina cyklu + hook `.githooks/pre-push`
      (per klon: `git config core.hooksPath .githooks` — nagłówek quality.yml);
      twarda wersja = decyzja Dzidka (public/Pro)
- [ ] POMIAR: audyt/POMIAR-CZASU.md prowadzony od godziny 0
