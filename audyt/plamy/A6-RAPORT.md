# A6 — Schemat bazy i migracje

**Audytor:** sesja audytu białych plam, 2026-09-01 · **Gałąź:** `audyt/biale-plamy-01-09`
**`commit_head`:** `7a8fb5b` · **Produkt:** `sklep-agentow` 0.9.28, `SKLEPAG_WERSJA_SCHEMATU = 9`
**Zakres z mapy terenu:** `A6 | Schemat bazy i migracje | ⬜ NIETKNIĘTE` — „7 tabel `sklepag_`.
Czy `dbDelta`, `uninstall.php` (obie ścieżki) i wersja schematu są spójne."

---

## Wobec czego oceniam (kryteria + skąd) — ZAPISANE PRZED OGLĄDANIEM

| # | Kryterium | Źródło |
|---|---|---|
| **S1** | „Nowa tabela = **trzy miejsca**: `dbDelta` + `uninstall.php` + ścieżka podniesienia schematu. **Symetria zapis↔sprzątanie** w TYM SAMYM PR" | `CLAUDE.md`, §Rzeczy które wywalały nas wcześniej, p. 1 |
| **S2** | „Uninstall niekompletny — zostają opcje, tabele, pliki, crony, a zwłaszcza **KLUCZ API**" — klasa 1 ściągi, wracała ×3 | `audyt/SCIAGA-EGZAMINATORA.md`, klasa 1 |
| **S3** | „`LIKE` bez `esc_like()` — dotyka CUDZYCH wpisów" — klasa 2 ściągi | `audyt/SCIAGA-EGZAMINATORA.md`, klasa 2 |
| **S4** | Kryterium GOTOWE #9: „Uninstall: domyślnie dane zostają; z opcją czyszczenia — **zero naszych tabel/opcji/stron/plików/cronów (diff pusty)**" | `rubryka-GOTOWE.md` #9 |
| **S5** | ⛔ **Przyczyna-matka H:** „**Nie sprawdzamy, czy operacja się udała — a mimo to zapisujemy jej skutek.**" (klasy 23 i 26) | `audyt/SCIAGA-EGZAMINATORA.md`, §Dwie PRZYCZYNY-MATKI |
| **S6** | Wymóg `dbDelta`: **dwie spacje** po `PRIMARY KEY`; definicje muszą odpowiadać stanowi w bazie | `wtyczki/sklep-agentow/includes/aktywacja.php:13` (własna zasada projektu) |
| **S7** | Klasa 3: **dokumentacja kłamie względem kodu** — każdą LICZBĘ z opisu sprawdź w kodzie | ściąga, klasa 3 |

---

## Co i czym zmierzyłem

| # | Pomiar | Wynik |
|---|---|---|
| M1 | `grep -c "CREATE TABLE"` w `aktywacja.php` | **7** — `produkty`, `sesje_checkout`, `zamowienia`, `zdarzenia`, `logowania`, `rate_limit`, `opinie` |
| M2 | lista tabel kasowanych w `uninstall.php:31` | **te same 7**, w jednej pętli, z komentarzem „nowa tabela = trzy miejsca" |
| M3 | **kolumny: kod ↔ baza**, wszystkie 7 tabel (parser definicji `dbDelta` vs `SHOW COLUMNS`) | **ZERO rozjazdów** — 13/13, 5/5, 30/30, 6/6, 6/6, 3/3, 14/14 |
| M4 | **indeksy: kod ↔ baza** (`SHOW INDEX` vs `KEY`/`UNIQUE KEY` w definicjach) | zgodne: `produkty(slug)`, `sesje_checkout(stripe_session_id UNIQUE, stan)`, `zamowienia(stripe_session_id UNIQUE, status, utworzono, payment_intent_id)`, `zdarzenia(zamowienie_id, czas)`, `logowania(czas, para_logowania)`, `rate_limit(PRIMARY klucz)`, `opinie(zamowienie_id UNIQUE, produkt_status)` |
| M5 | `sklepag_wersja_schematu` w bazie vs `SKLEPAG_WERSJA_SCHEMATU` w kodzie | **`'9'` = `9`** — zgodne |
| M6 | opis wersji schematu (`sklep-agentow.php:20`) vs faktyczne kolumny | v9 `dor_*` ✅ · v8 `dane_faktury` ✅ · v7 `status_przed_sporem` ✅ · v6 `payment_intent_id`, `zwrocono_grosze`, `zwrot_czas` ✅ · v5 tabela `opinie` ✅ · v4 `usunieto_dnia` ✅ · v3 indeks `para_logowania` ✅ — **7 z 7 zapowiedzi ma pokrycie** |
| M7 | dwie spacje po `PRIMARY KEY` | **7 z 7 definicji** ma dwie spacje (`aktywacja.php:35,45,81,95,107,116,134`) |
| M8 | `LIKE` w `uninstall.php` | 3 zapytania, **każde** przez `$wpdb->esc_like( … ) . '%'` (`:70`, `:79` ×2) |
| M9 | transienty: klucze tworzone vs kasowane | 5 kluczy, **wszystkie** z przedrostkiem `sklepag_` (`sklepag_szkic_produktu_`, `sklepag_szkic_opinii_`, `sklepag_idem_`, `sklepag_niepowiazana_`, `sklepag_recon_`); uninstall kasuje `_transient_sklepag_` **i** `_transient_timeout_sklepag_`, plus `wp_cache_flush()` przy zewnętrznym cache obiektów |
| M10 | ścieżka wywołania migracji | `plugins_loaded` → `sklepag_start()` (`sklep-agentow.php:79-82`) → `sklepag_migruj_schemat_jesli_trzeba()`; wychodzi natychmiast, gdy wersja się zgadza (`aktywacja.php:173-175`) |
| M11 | deaktywacja (nie deinstalacja) | `sklepag_deaktywacja` (`aktywacja.php:164-167`): `wp_clear_scheduled_hook` + `flush_rewrite_rules` — cron nie zostaje |
| M12 | **czy ktokolwiek sprawdza, że schemat po `dbDelta` NAPRAWDĘ istnieje** | `grep -rn "SHOW COLUMNS\|SHOW TABLES\|get_col_info\|last_error"` po całej wtyczce → **1 trafienie, i to nie tutaj** (`zakup.php:880`, przy zapisie zamówienia) |

---

## Kontrola dodatnia

1. **Parser definicji vs baza (M3)** — gdyby mój parser czytał źle, rozjazd wyszedłby na
   WSZYSTKICH tabelach. Wyszło 7 zgodnych par o **różnych** licznościach (13, 5, 30, 6, 6, 3, 14),
   czyli parser naprawdę czyta treść, a nie zwraca stałej.
2. ⚠️ **Złapałem własny fałszywy alarm.** Pierwszy przebieg parsera pokazał dla `zamowienia`
   14 kolumn zamiast 30 — i wyglądało to na rozjazd schematu. Przyczyną był **mój `cut -c1-200`
   w potoku**, który uciął długi wiersz wyniku. To dokładnie klasa 19 ściągi („narzędzie, które
   cicho obcina wejście"), popełniona przeze mnie. Po usunięciu `cut` — zero rozjazdów.
   **Zapisuję to, bo pomiar bez tego przypisu wyglądałby na znalezisko.**
3. **Dwie spacje po `PRIMARY KEY` (M7)** — `grep` wykazał najpierw „7 z dwiema spacjami i 1 z jedną",
   co wyglądało na wadę. Ósme trafienie to **komentarz** w `aktywacja.php:13`
   („dwie spacje po PRIMARY KEY — wymóg dbDelta"), nie definicja. Sprawdzone `cat -A`:
   wszystkie **siedem** definicji ma `PRIMARY KEY␣␣(`. Drugi fałszywy alarm mojego narzędzia.
4. **Zero przy M12 jest pomiarem, nie ślepotą** — ten sam wzorzec **trafił** w `zakup.php:880`
   (`$wpdb->last_error` przy zapisie zamówienia). Kod zna ten idiom; w ścieżce migracji go nie ma.

---

## WYNIK: **ZNALEZIONE** — 1 znalezisko (średnie). Reszta terenu **CZYSTA**.

---

## Znaleziska

### `A6-F1` — **SRED** · Migracja zapisuje wersję schematu, nie sprawdziwszy, czy `dbDelta` się udało — nieudana migracja **nigdy się nie powtórzy**

**Objaw:** obie ścieżki budowy schematu wołają `dbDelta()` i **natychmiast, bezwarunkowo**
podnoszą znacznik wersji. Jeżeli `dbDelta` nie zdąży, nie ma uprawnień (`ALTER`), trafi na
błąd MySQL albo nie doda indeksu — w bazie zostaje **stary** schemat, a w opcji stoi **nowy**
numer. Przy następnym żądaniu `sklepag_migruj_schemat_jesli_trzeba()` widzi zgodność wersji
i **wychodzi w pierwszej linii**. Migracja nie ponowi się nigdy — także po aktualizacji wtyczki.

**Plik i linia — ścieżka aktywacji, `wtyczki/sklep-agentow/includes/aktywacja.php:141-146`:**
```php
function sklepag_aktywacja() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( sklepag_tabele_sql() as $sql ) {
		dbDelta( $sql );                                    // ← wynik nigdzie nie trafia
	}
	update_option( 'sklepag_wersja_schematu', SKLEPAG_WERSJA_SCHEMATU, false );   // ← bezwarunkowo
```

**Plik i linia — ścieżka podniesienia, `aktywacja.php:172-181`:**
```php
function sklepag_migruj_schemat_jesli_trzeba() {
	if ( (int) get_option( 'sklepag_wersja_schematu', 0 ) === SKLEPAG_WERSJA_SCHEMATU ) {
		return;                                              // ← brama zamyka się na zawsze
	}
	…
	foreach ( sklepag_tabele_sql() as $sql ) {
		dbDelta( $sql );                                     // ← wynik nigdzie nie trafia
	}
	update_option( 'sklepag_wersja_schematu', SKLEPAG_WERSJA_SCHEMATU, false );
```

**Dowód, że nikt tego nie nadrabia gdzie indziej (M12):**
`grep -rn "SHOW COLUMNS\|SHOW TABLES\|get_col_info\|last_error"` po całej wtyczce →
**jedno trafienie**, `zakup.php:880`, i dotyczy zapisu zamówienia, nie schematu.
Próba kontrolna: ten sam wzorzec **znalazł** tamto miejsce, więc szukanie działa.
`dbDelta()` **zwraca tablicę komunikatów** o tym, co zrobiła — ta wartość jest odrzucana
w obu miejscach.

**Mechanizm i skutek DLA KLIENTA.** To jest wprost **przyczyna-matka H** z naszej ściągi
(„nie sprawdzamy, czy operacja się udała, a mimo to zapisujemy jej skutek", klasy 23 i 26).
Realny scenariusz: hosting, na którym użytkownik bazy nie ma prawa `ALTER` (spotykane
na tanich pakietach). Właściciel aktualizuje wtyczkę z 0.9.24 do 0.9.28 — `dane_faktury`
(v8) i `dor_*` (v9) nie powstają, wersja schematu skacze na 9. Skutek:
- kupujący podaje NIP → `INSERT` do `zamowienia` pada na nieistniejącej kolumnie;
- ⭐ **awaria jest GŁOŚNA, nie cicha** — `zakup.php:880` sprawdza `$wpdb->last_error` przy zapisie
  zamówienia, więc zakup zakończy się błędem zamiast po cichu zgubić dane. **To jest zasługa
  tamtego kodu i zmniejsza wagę tego znaleziska ze średniej-wysokiej na średnią;**
- ⛔ ale **naprawa jest niemożliwa bez ręcznej interwencji w bazie**: deaktywacja i ponowna
  aktywacja wykona `dbDelta` znowu, jednak jeśli przyczyną są uprawnienia — nic się nie zmieni,
  a właściciel nie ma żadnego komunikatu mówiącego, że schemat jest niepełny.
  Sklep nie sprzedaje i nikt nie wie dlaczego.

**RODZAJ SKUTKU:** `przypuszczalny` — pokazuję **konstrukcję w kodzie** (brak sprawdzenia
i bezwarunkowy zapis znacznika), **nie** zaobserwowaną awarię. Na tym stanowisku schemat jest
kompletny (M3–M6), więc sytuacji nie odtworzyłem i nie twierdzę, że zaszła.

**Waga: ŚREDNIA.**

**Propozycja naprawy:** po pętli `dbDelta` sprawdzić stan świata PO operacji — np. `SHOW TABLES LIKE`
dla siódemki i `SHOW COLUMNS` dla kolumny wprowadzonej przez najnowszą wersję — i **podnieść
znacznik tylko wtedy, gdy sprawdzenie przeszło**. Gdy nie przeszło: zostawić starą wersję
(migracja spróbuje przy następnym żądaniu) i pokazać na Pulpicie `notice` z treścią błędu
(`$wpdb->last_error`). Mechanizm `notice` już tam jest — sześć innych z niego korzysta.
⭐ Strażnik: test, który odbiera prawo `ALTER`, woła migrację i wymaga, żeby
`sklepag_wersja_schematu` **NIE** urosło — **pada** na dzisiejszym kodzie.

---

## Wyniki CZYSTE (z pomiarem, nie z wrażenia)

1. ✅ **Symetria „trzy miejsca" trzyma się co do sztuki.** Siedem tabel w `dbDelta` = siedem
   w `uninstall.php:31`, w jednej pętli, z komentarzem powtarzającym regułę. Zero tabel
   osieroconych w którąkolwiek stronę.
2. ✅ **Schemat w kodzie = schemat w bazie.** 7 tabel, zero rozjazdów kolumn (M3), indeksy
   zgodne (M4). Znacznik wersji w bazie równy stałej w kodzie (M5).
3. ✅ **Opis wersji schematu nie kłamie** (klasa 3): każda z siedmiu zapowiedzi w komentarzu
   `sklep-agentow.php:20` (v3–v9) ma pokrycie w faktycznych kolumnach i indeksach (M6).
4. ✅ **Uninstall — obie ścieżki sprawdzone.**
   Domyślna: kasuje **sekrety** (`sklepag_stripe_klucz_sekretny`, `sklepag_stripe_webhook_sekret`)
   i **cron** — jedno i drugie **PRZED** wczesnym `return` (`uninstall.php:16-22`), z komentarzem
   nazywającym klasę. To dokładnie odpowiedź na klasę 1 ściągi („uninstall nie kasował klucza API").
   Pełna: 7 tabel, opcje `sklepag_%`, strony (4 opcje), transienty (2 przedrostki + `wp_cache_flush`),
   katalog chroniony, user meta.
5. ✅ **Każdy `LIKE` w uninstallu przez `esc_like()`** (M8) — klasa 2 ściągi zamknięta.
   Przedrostek `sklepag_` zawiera `_`, czyli wieloznacznik SQL; bez `esc_like` sięgałby po cudze opcje.
6. ✅ **Transienty nie zostają** (M9): pięć kluczy tworzonych, wszystkie z przedrostkiem
   `sklepag_`, kasowane obiema drogami — po nazwie w `wp_options` i przez `wp_cache_flush()`
   przy zewnętrznym cache obiektów. Ten drugi przypadek zwykle się pomija.
7. ✅ **Deaktywacja też sprząta cron** (M11) — nie tylko deinstalacja. Martwy wpis w cronie
   po wyłączonej wtyczce to znana klasa, tutaj zamknięta.
8. ✅ **Migracja jest WOŁANA, nie tylko napisana** (M10): `plugins_loaded` → `sklepag_start()`,
   z tanim wyjściem przy zgodnej wersji. Aktualizacja „w miejscu" nie odpala aktywacji —
   i kod o tym wie, ma self-heal harmonogramu i katalogu chronionego (`sklep-agentow.php:84-91`).

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego | Do zrobienia |
|---|---|---|
| **Zachowanie przy nieudanym `dbDelta`** | odebranie praw `ALTER` to zapis do konfiguracji wspólnego stosu — nie ruszam | strażnik po stronie buildera (opis w `A6-F1`) |
| **Faktyczna deinstalacja (obie ścieżki na żywo)** | `uninstall.php` wykonuje się tylko przy usuwaniu wtyczki; na wspólnym stosie tego nie robię | `L4-05`/`L4-06` z audytu K7 przechodziły to wcześniej; do powtórzenia na osobnym stanowisku |
| **Kolumny USUNIĘTE w przeszłych wersjach** | `dbDelta` nie kasuje kolumn, więc po takiej zmianie zostałby sierota; nie przeszedłem historii `aktywacja.php` wersja po wersji | `git log -p` po definicjach, jeśli ktoś kiedyś coś usuwał |
| **Multisite** | `ZROZUMIENIE.md` mówi: jedna wspólna strona, 4 konta adminów — nie sieć witryn; `$wpdb->prefix` jest per-witryna, więc dla sieci aktywacja objęłaby tylko bieżącą | poza zakresem zlecenia |
| **Typy i długości kolumn** | porównałem **nazwy** kolumn i indeksów, nie typy i długości | rozszerzyć porównanie o `Type` z `SHOW COLUMNS` |
| **Kolizja z prefiksem innej wtyczki** | `sklepag_` jest nasz; nie sprawdzałem, czy któraś cudza wtyczka na stosie używa tego samego | niskie ryzyko |

---

## ⭐ Czego NIE było na mojej liście (przeciwwaga)

1. **Migracja DANYCH, nie tylko struktury.** Pytałem o kolumny i indeksy. Żadna z wersji v3–v9
   nie wymagała przepisania istniejących wierszy — ale nikt nie postawił pytania „co, gdy
   kolejna wersja będzie tego wymagała", a dzisiejszy mechanizm (`dbDelta` w pętli) tego nie umie.
2. **Cofnięcie wersji.** Właściciel wgrywa starszą paczkę: znacznik w bazie zostaje na 9,
   kod ma 8 — `(int) get_option(...) === SKLEPAG_WERSJA_SCHEMATU` jest **fałszem**, więc migracja
   ruszy i… zrobi `dbDelta` do STARSZYCH definicji. Nie sprawdzałem, co z tego wynika.
3. **Rozmiar wiersza `zamowienia`** — 30 kolumn, w tym dwa `longtext` i kilka `varchar(300-500)`.
   Limitów InnoDB nie liczyłem.
4. **Kopie zapasowe i przywracanie bazy** — po przywróceniu zrzutu sprzed migracji znacznik
   wersji wraca razem z danymi, więc akurat tu mechanizm zadziała. Nie mierzyłem.
5. **Tabele botów** (`chatl_`, `segm_`) tą samą siatką — to osobne wtyczki i osobny teren.

---

## Propozycja wiersza do mapy terenu i do rejestru (gotowe do przeklejenia)

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja A — podmiana wiersza `A6`:**

```
| A6 | Schemat bazy i migracje | ✅ | **1.09**, `audyt/plamy/A6-RAPORT.md` (sha `7a8fb5b`), porównanie definicji `dbDelta` z żywą bazą | **CZYSTO w 8 punktach na 9**: 7 tabel w `dbDelta` = 7 w `uninstall.php:31`; kolumny kod↔baza bez rozjazdu (13/5/30/6/6/3/14); indeksy zgodne; `sklepag_wersja_schematu`='9'=stała; wszystkie 7 zapowiedzi wersji v3-v9 mają pokrycie; każdy `LIKE` przez `esc_like()`; transienty kasowane obiema drogami (2 przedrostki + `wp_cache_flush`); sekrety i cron out PRZED wczesnym `return`; deaktywacja też czyści cron. ⛔ Jedno znalezisko `A6-F1` (SRED): `dbDelta` bez sprawdzenia wyniku, znacznik wersji podnoszony bezwarunkowo w OBU ścieżkach — nieudana migracja nie powtórzy się nigdy |
```

**Do rejestru znalezisk:**

```
| `A6-F1` | **SRED** · `aktywacja.php:141-146` (aktywacja) i `:172-181` (podniesienie) wołają `dbDelta()` i BEZWARUNKOWO robią `update_option( 'sklepag_wersja_schematu', 9 )`; wynik `dbDelta` odrzucany, `$wpdb->last_error` niesprawdzany. Przy nieudanej migracji (brak `ALTER`, błąd MySQL) w bazie zostaje stary schemat, a brama `if ( wersja === STALA ) return;` zamyka się NA ZAWSZE. Przyczyna-matka H ściągi (klasy 23, 26) | `grep -rn "SHOW COLUMNS\|SHOW TABLES\|last_error"` po wtyczce → 1 trafienie i to w `zakup.php:880` (próba kontrolna: wzorzec działa). Na stanowisku NIEODTWORZONE — schemat kompletny — BRAK strażnika | OTWARTE |
```
