# RAPORT A3 — AUDYT DANYCH: co zostaje po nas i czego nie da się usunąć

**Zakres:** `wtyczki/chatbot-live` i `wtyczki/segregator-maila`.
**Pytanie:** odinstalowanie (obie ścieżki) · retencja · pokrycie RODO · `DISABLE_WP_CRON`.
**Zasada:** znalezisko = padający test; dowód = wynik wykonania; audyt niczego nie naprawia.

**commit_head pomiaru:** `8c1220c539f16fff92bfccbfe7a14f16858f8832` (gałąź `audyt/mutanty-kalibracja`)
**Raport otwarty z:** `3fc884d9d7c3d9b47b8229b4c3be5ddcd1eea5e4` (`origin/main`)
**Kontrola materiału:** `git diff origin/main..8c1220c -- wtyczki/` = **pusto**. Pliki obu wtyczek są
co do bajtu takie same na gałęzi pomiarowej i na `main`, więc oba znaleziska niżej dotyczą kodu,
który jest na `main` — to nie jest wada materiału audytowego.

---

## PODSUMOWANIE

Dwa znaleziska, oba w ChatBocie, oba potwierdzone padającym testem i skalibrowane w obie strony
(pada na kodzie z repo, przechodzi po naprawie, przypadki bez wady trzymają). **Poważniejsze jest
pierwsze:** tabela `chatl_sesje` nie ma w całej wtyczce ani jednej ścieżki kasowania — nie rusza
jej ani retencja 90 dni, ani kasownik RODO, więc rośnie bez końca, a ślad osoby, która poprosiła
o usunięcie danych, zostaje w bazie po tym, jak właściciel odpowiedział jej, że dane usunięto.
To jest niezgodność z obietnicą złożoną osobie trzeciej, nie dług techniczny. Drugie jest węższe:
`chatbot-live/uninstall.php` nie zdejmuje swojego zadania crona, więc przy usunięciu wtyczki
z pominięciem deaktywacji w `wp_options→cron` zostaje zaczep bez kodu — bliźniacza wtyczka ten sam
scenariusz ma zamknięty i opisany, czyli wzorzec naprawiono w jednym egzemplarzu z dwóch.
**Poświadczenia są czyste w obu wtyczkach i obiema ścieżkami** — klucz API i hasło IMAP znikają
zawsze, zmierzone na żywo; to była klasa, która w poprzednich projektach wracała trzykrotnie.
Segregator w tym audycie wyszedł bez zarzutu: retencja obejmuje wszystkie cztery tabele z danymi,
odinstalowanie zostawia odcisk pusty, a wyłączony WP-Cron panel nazywa po imieniu zamiast udawać,
że zadanie jest zaplanowane.

---

## ZNALEZISKO A3-1 — `chatl_sesje`: tabela bez żadnej ścieżki kasowania

| | |
|---|---|
| **Wtyczka** | `chatbot-live` |
| **Pliki i linie** | `wtyczki/chatbot-live/chatbot-live.php:92` (`chatl_czyszczenie_historii()`) · `wtyczki/chatbot-live/includes/rodo.php:329` (`chatl_rodo_kasownik()`) · tabela zakładana w `includes/instalator.php:84` |
| **Test** | `testy/chatbot-sesje-retencja-rodo-test.php` |
| **Kalibracja** | ✅ pada na kodzie z repo (EXIT=1, 2 przypadki), przechodzi po naprawie (EXIT=0), oba przypadki bez wady trzymają w obu przebiegach |
| **Waga** | poważne — dotyka obietnicy RODO złożonej osobie trzeciej |

### Na czym polega

W całej wtyczce nie ma ani jednego `DELETE FROM …chatl_sesje` — tylko `INSERT`, `UPDATE`, `SELECT`
i `DROP TABLE` w `uninstall.php`. Sprawdzone wyszukiwaniem po wszystkich wystąpieniach:

```
$ grep -rn "chatl_sesje" wtyczki/chatbot-live --include=*.php
includes/operator.php:33      SELECT
includes/front.php:674        SELECT
includes/przejecie.php:66     INSERT
includes/przejecie.php:164,213,246,276,356   SELECT / UPDATE
includes/instalator.php:84    CREATE TABLE
uninstall.php:29              DROP TABLE
```

Skutki są dwa:

1. **Retencja nie obejmuje tabeli.** `chatl_czyszczenie_historii()` kasuje `chatl_rozmowy`
   (starsze niż `CHATL_RETENCJA_DNI` = 90) i `chatl_rate_limit` (wygasłe okna). `chatl_sesje`
   pomija. Powstaje jeden wiersz na każdą sesję widgetu i zostaje **na zawsze** — także wtedy,
   gdy rozmowę, której dotyczył, retencja skasowała 90 dni wcześniej.
2. **Kasownik RODO nie obejmuje tabeli.** `chatl_rodo_kasownik()` usuwa `chatl_zgloszenia`
   (po adresie) i `chatl_rozmowy` (po sesjach wnioskodawcy). Wiersz w `chatl_sesje` zostaje —
   z identyfikatorem sesji, znacznikiem czasu utworzenia, ostatnią aktywnością i informacją,
   czy rozmowę przejął człowiek. Właściciel odpowiada wnioskodawcy komunikatem kasownika,
   więc mówi mu, że danych już nie ma, podczas gdy ślad w bazie został.

### Dowód wykonania

```
$ docker exec -u www-data strona_wp_wordpress wp eval-file /tmp/s.php
=== AUDYT DANYCH: co zostaje w chatl_sesje ===
  prog retencji CHATL_RETENCJA_DNI=90 dni; stary wpis datowany na 2026-04-23 16:08:39

  OK   0. kontrola: retencja kasuje STARA rozmowe, zostawia SWIEZA  [chatl_rozmowy — prog dziala]
  BLAD 1. stara sesja (120 dni) NIE zostala skasowana przez retencje  [wiersz w chatl_sesje ZOSTAJE — tabela rosnie bez konca]
  OK   2. swieza sesja ZOSTALA (naprawa nie moze kasowac wszystkiego)
  BLAD 3. po kasowaniu RODO wiersz sesji wnioskodawcy ZOSTAJE w chatl_sesje  [slad osoby przezyl "usuniecie danych"]
  OK   4. sesja OBCEJ osoby nietknieta przez wniosek RODO

PRZYPADKOW NIEZALICZONYCH: 2
EXIT=1
```

Kalibracja odwrotna — łatka **tylko w kontenerze**, kod w repo nietknięty, po pomiarze przywrócony
(md5 zgodne). Łatka: `DELETE FROM …chatl_sesje WHERE aktywnosc < <granica>` w retencji oraz
`DELETE … WHERE sesja IN (<sesje wnioskodawcy>)` w kasowniku:

```
  OK   0. kontrola: retencja kasuje STARA rozmowe, zostawia SWIEZA
  OK   1. stara sesja skasowana przez retencje
  OK   2. swieza sesja ZOSTALA (naprawa nie moze kasowac wszystkiego)
  OK   3. kasowanie RODO usunelo tez wiersz sesji wnioskodawcy
  OK   4. sesja OBCEJ osoby nietknieta przez wniosek RODO

WSZYSTKIE KROKI PRZESZLY — chatl_sesje sprzatana przez retencje i przez RODO
EXIT=0
```

**Dlaczego test jest wiarygodny:** przypadek 0 to kontrola mechanizmu na tabeli, którą retencja
OBEJMUJE — bez niej padnięcie 1 i 3 nie dowodziłoby, że próg czasu w ogóle działa. Przypadki 2 i 4
to przypadki bez wady: naprawa nie może kasować świeżych sesji ani sesji obcej osoby.

### Czy jest druga taka tabela — sprawdzone, nie założone

Przejrzane **wszystkie 13 tabel** obu wtyczek pod kątem „czy istnieje ścieżka kasowania":

| Tabela | Retencja | RODO | Inna ścieżka | Werdykt |
|---|---|---|---|---|
| `chatl_rozmowy` | ✅ 90 dni | ✅ | — | czysto |
| `chatl_rate_limit` | ✅ wygasłe okna | — | — | czysto |
| `chatl_zgloszenia` | ❌ | ✅ | panel: usuń | **jawnie udokumentowane** (`DOKUMENTACJA-TECHNICZNA.md:106`: „Zgłoszenia NIE podlegają retencji 90 dni") — dokumentacja nie kłamie |
| `chatl_qa` | ❌ | — | panel: usuń | konfiguracja właściciela, nie dane osób |
| `chatl_ai_uzycie` | ❌ | — | — | licznik dobowy, 1 wiersz/dobę, bez danych osobowych — zgłaszam jako obserwację, nie wadę |
| **`chatl_sesje`** | ❌ | ❌ | **brak** | 🐞 **ZNALEZISKO A3-1** |
| `segm_maile` | ✅ 90 dni | — | panel: wyczyść | czysto |
| `segm_przebiegi` | ✅ 90 dni | — | panel: wyczyść | czysto |
| `segm_skrz_ruchy` | ✅ 90 dni | — | — | czysto — trzyma nadawcę i temat cudzych maili, objęte |
| `segm_skrz_przebiegi` | ✅ 90 dni | — | — | czysto |
| `segm_kategorie`, `segm_reguly` | ❌ | — | panel: usuń | konfiguracja właściciela |
| `segm_ai_uzycie` | ❌ | — | — | jak `chatl_ai_uzycie` |

**Odpowiedź na pytanie „czy jest druga taka tabela": nie ma.** `chatl_sesje` jest jedyną tabelą
w obu wtyczkach, która trzyma ślad odwiedzającego i nie ma żadnej ścieżki kasowania. Dwie tabelki
liczników dobowych (`chatl_ai_uzycie`, `segm_ai_uzycie`) też rosną bez retencji, ale to jeden
wiersz na dobę bez danych osobowych — wymieniam je, żeby było widać, że zostały sprawdzone,
a nie pominięte.

---

## ZNALEZISKO A3-2 — zombie cron po odinstalowaniu ChatBota

| | |
|---|---|
| **Wtyczka** | `chatbot-live` |
| **Pliki i linie** | `wtyczki/chatbot-live/uninstall.php` (brak `wp_clear_scheduled_hook`) · zadanie zakładane w `includes/instalator.php:136-137` · zdejmowane wyłącznie w `includes/instalator.php:155` (`chatl_deaktywacja()`) |
| **Kontrprzykład w repo** | `wtyczki/segregator-maila/uninstall.php:18-21` — ten sam scenariusz zamknięty i opisany |
| **Test** | `testy/uninstall-zombie-cron-test.php` |
| **Kalibracja** | ✅ pada na kodzie z repo (EXIT=1), przechodzi po naprawie (EXIT=0), kontrola na bliźniaczej wtyczce przechodzi w obu przebiegach |
| **Waga** | średnia — wąski scenariusz, ale wzorzec naprawiony w jednym egzemplarzu z dwóch |

### Na czym polega

ChatBot zdejmuje swoje zadanie crona wyłącznie w `chatl_deaktywacja()`. Gdy katalog wtyczki znika
bez przejścia przez deaktywację (usunięcie katalogu i „usuń" z listy, podmiana plików przy
wdrożeniu), jedynym sprzątaczem jest `uninstall.php` — a ten crona nie rusza. W `wp_options→cron`
zostaje `chatl_czyszczenie_historii`, który WordPress próbuje odpalać codziennie i za każdym razem
trafia w nieistniejącą funkcję.

To nie jest teoria: bliźniacza wtyczka broni się przed **dokładnie tym** scenariuszem i pisze
o nim wprost — *„Harmonogram retencji zdejmuje już deaktywacja, ale odinstalowanie ma zostawić
czysto także wtedy, gdy wtyczkę usunięto z pominięciem tamtej ścieżki (np. skasowanie katalogu
i «usuń» z listy) — martwy wpis w cronie tylko sypie błędami «nie ma takiej funkcji»."*
Klasa K1 ze `SLEPE-PLAMY-NASZEGO-AUDYTU.md`: naprawa trafia w jeden egzemplarz wzorca.

### Dowód wykonania

```
KOD Z REPO:                                          PO NAPRAWIE (łatka w kontenerze):
=== AUDYT DANYCH: co zostaje w cronie ===            === AUDYT DANYCH: co zostaje w cronie ===
  BLAD 1. ChatBot: cron `chatl_czyszczenie_historii`   OK   1. ChatBot: cron zdjety przez uninstall
         ZOSTAJE po odinstalowaniu
         [zombie w wp_options->cron]
  OK   2. ChatBot: cron CUDZEJ wtyczki nietkniety      OK   2. ChatBot: cron CUDZEJ wtyczki nietkniety
  OK   3. KONTROLA Segregator: cron zdjety             OK   3. KONTROLA Segregator: cron zdjety

PRZYPADKOW NIEZALICZONYCH: 1                         WSZYSTKIE KROKI PRZESZLY
EXIT=1                                               EXIT=0
```

Przypadek 3 to kontrola metody pomiaru na wtyczce, która robi to poprawnie — bez niej padnięcie
w 1 nie dowodziłoby niczego o samym pomiarze. Przypadek 2 to przypadek bez wady: odinstalowanie
nie może zdejmować crona cudzej wtyczki.

### ⚠️ Wada mojego własnego testu, złapana i naprawiona

Pierwsza wersja tego testu miała `$B = 0` przy `$GLOBALS['B']++`. `wp eval-file` uruchamia plik
w **zasięgu funkcji**, więc to były dwie różne zmienne i test kończył `EXIT=1` **zawsze**, także
na naprawionym kodzie — czyli był tak samo bezwartościowy jak test, który zawsze przechodzi.
To dokładnie pułapka opisana w `testy/segregator-skrzynka-proba-kontrolna.sh`. Poprawione,
obie strony kalibracji przemierzone ponownie; w pliku został komentarz ostrzegawczy.

---

## POMIARY, KTÓRE WYSZŁY CZYSTO

### Odcisk bazy przed instalacją → po instalacji → po odinstalowaniu

| | ChatBot | Segregator |
|---|---|---|
| **Tabele** (zbiór `CREATE` vs `DROP`, porównany co do nazwy) | 6 = 6, komplet | 7 = 7, komplet |
| **Ścieżka domyślna** (`usun_dane=0`) | zostają: `chatl_ustawienia`, `chatl_wersja_schematu`, `chatl_blad_ai`, `chatl_blad_nonce`, `chatl_rodo_sesje_wspolne`, usermeta `chatl_powitanie_zamkniete`, cron | zostają: `segm_ustawienia`, `segm_wersja_schematu` — i nic więcej |
| **Pełne czyszczenie** (`usun_dane=1`) | opcje PUSTO, usermeta PUSTO, **cron ZOSTAJE** ← A3-2 | opcje PUSTO, usermeta PUSTO, cron PUSTO |
| **POŚWIADCZENIA** | `chatl_klucz_api` kasowany **zawsze**, obiema ścieżkami | `segm_klucz_api` **i** `segm_imap_haslo` kasowane zawsze, bezwarunkowo |

Klucz API zmierzony na żywo, nie odczytany z kodu:

```
  ChatBot: przed=sk-ant-AUDYT-CHATL-123  po uninstall=__BRAK__  -> KLUCZ USUNIETY
```

**Klasa „uninstall zostawia klucz API", która w poprzednich projektach wracała trzykrotnie,
jest tu zamknięta w obu wtyczkach.**

**Asymetria bliźniaków (bez padającego testu, więc nie zgłaszam jako znaleziska):** na ścieżce
domyślnej ChatBot zostawia `chatl_blad_ai` i `chatl_blad_nonce` — stan chwilowy, nie dane
właściciela — oraz usermeta powitania. Segregator swoje odpowiedniki kasuje **zawsze**,
argumentując w komentarzu, że to stan interfejsu, a nie dane. Ten sam wzorzec, dwie różne decyzje.

*(Opcje `chatl_test_a` / `chatl_test_b`, które pokazał pomiar, **nie pochodzą z wtyczki** — grep
po `wtyczki/` ich nie znajduje. To cudze wpisy na współdzielonym stosie testowym.)*

### Retencja — z przypadkiem bez wady

- **ChatBot:** próg `CHATL_RETENCJA_DNI` = 90. Stara rozmowa znika, **świeża zostaje** (zmierzone).
- **Segregator:** retencja obejmuje wszystkie cztery tabele z danymi, łącznie z historią żywej
  skrzynki (`segm_skrz_ruchy`, `segm_skrz_przebiegi`), która trzyma nadawcę i temat cudzych maili.

### `DISABLE_WP_CRON` — nie unieważnia obietnicy, i to jest w kodzie

Obie wtyczki adresują to realnie, choć inaczej:

- **ChatBot:** self-heal — wejście administratora na „Historia rozmów" woła
  `chatl_czyszczenie_historii()` (`includes/admin.php:654`, idempotentne).
- **Segregator:** nie udaje, że zadanie jest — `segm_opis_najblizszego_czyszczenia()`
  (`includes/admin.php:1080`) zwraca *„uwaga: zadanie NIE jest zaplanowane — sprawdź, czy WP-Cron
  działa na tym hostingu"*, a diagnostyka skrzynki nazywa stałą `DISABLE_WP_CRON` po imieniu
  i mówi, że „Uruchom teraz" działa niezależnie (`includes/skrzynka.php:216-222`).

Retencja Segregatora **nie ma** furtki poza cronem (zero wywołań poza zaczepem), więc przy
`DISABLE_WP_CRON` bez crona systemowego nie ruszy — ale panel mówi to właścicielowi wprost,
więc obietnica nie jest fałszywa.

---

## CZEGO NIE SPRAWDZIŁEM

1. **Multisite.** Cały pomiar na pojedynczej instalacji. `uninstall.php` obu wtyczek działa na
   jednym prefiksie; przy sieci witryn dane pozostałych blogów nie są ruszane. Nie wiem, czy to
   świadoma decyzja, czy przeoczenie — **nie zmierzyłem**.
2. **Pliki na dysku.** Zmierzyłem `wp_options`, tabele, `wp_usermeta` i cron. Nie sprawdziłem
   `wp-content/uploads`, plików tymczasowych uploadu ani `debug.log` — a `wp_trigger_error()`
   przy włączonym `WP_DEBUG` zapisuje tam treści, których **żadna ścieżka odinstalowania nie rusza**.
3. **Odzyskiwalność.** Sprawdziłem, że dane znikają. Nie sprawdziłem, czy właściciel, który kliknął
   „usuń wszystkie dane" przez pomyłkę, ma jakąkolwiek ścieżkę odwrotu i czy panel go ostrzega.
4. **Fragment mojego harnessu do tabel był zawodny** — przy prefiksie testowym `chatl_utworz_tabele()`
   nie założyło tabel, więc wyniku „tabele po odinstalowaniu" **nie opieram na tym przebiegu**.
   Zastąpiłem go porównaniem zbiorów nazw `CREATE` vs `DROP` (wynik w tabeli wyżej). Gdyby ktoś
   chciał domknąć to wykonaniem, trzeba naprawić zakładanie tabel na obcym prefiksie.

### ⚠️ Szkody uboczne mojego pomiaru na współdzielonym stosie — do naprawy przez koordynatora

1. **Pierwszy pomiar odcisku był nieważny i mógł zmylić.** `$wpdb->set_prefix()` przestawia także
   `$wpdb->options` i `$wpdb->usermeta` na nieistniejące `wpaud_options` — odczyty opcji leciały
   w próżnię, `uninstall.php` robił wczesny `return`, a wynik „opcje: brak (odcisk czysty)" był
   **fałszywie zielony**. Złapane kontrolą, pomiar powtórzony poprawnie; raportuję drugi przebieg.
2. **Skasowałem klucz API ChatBota na stosie testowym.** Mój test odinstalowania nadpisał
   `chatl_klucz_api` atrapą i pozwolił `uninstall.php` ją skasować — oryginał przepadł. Wiersza
   nie ma też dla `segm_klucz_api`, ale **nie umiem orzec**, czy to moja sprawa: nie zmierzyłem
   stanu przed. Klucz w bazie i tak był nieważny (HTTP 401, zmierzone w rundzie A1), więc szkoda
   jest mała — ale jest i trzeba go odtworzyć z `sekrety-anthropic.env`.
3. **Stos przywrócony:** 6 tabel `chatl` + 7 `segm`, dane na miejscu (`chatl_qa=14`,
   `chatl_rozmowy=269`, `segm_reguly=8`), zero tabel testowych `wpaud*`, oba crony przeplanowane,
   kod wtyczek w kontenerze przywrócony z repo (md5 zgodne).

### Uwaga o rundzie A1 (bezpieczeństwo) — dla porządku

Znalezisko z rundy A1 (`segm_post_skrzynka_test()` bez `current_user_can()`) było **mutantem
kalibracyjnym M1**, zdjętym commitem `8c1220c`. Na `origin/main` ta funkcja `current_user_can()`
**ma**. Test `testy/segregator-skrzynka-test-uprawnienia.php` celowo **nie wchodzi** do tego PR-a:
na obecnym kodzie przechodzi, więc nie jest dowodem znaleziska — najwyżej strażnikiem regresji,
a to osobna decyzja koordynatora.

---

## ⛔ CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Pytanie brzmiało „co zostaje **w bazie**". Kategorie, które przy tych modułach wyglądają na istotne,
a przez które moja lista mnie przeprowadziła obok — wypisuję je, bo w poprzednim zleceniu przeszła
cała kategoria błędów właśnie dlatego, że nikt nie zapytał, czego na liście brakuje:

1. **Komu klient przekazuje dane osób trzecich.** Segregator wysyła **nadawców i tematy cudzych
   maili** do API dostawcy modelu. Retencja w naszej bazie działa — ale to jest inna kategoria
   RODO niż retencja: to powierzenie danych osób, które o tym nie wiedzą i nie miały jak się
   sprzeciwić. Pytanie „co zostaje po nas" nie obejmuje „co zostaje **u odbiorcy**", a dla
   właściciela skrzynki to ta sama korespondencja. Nie sprawdziłem, czy istnieje obowiązek
   informacyjny, podstawa przetwarzania ani zapis o tym w deklaracji prywatności produktu.
2. **Co zostaje POZA bazą.** Zakres wymieniał „pliki", a ja zmierzyłem wyłącznie bazę. `debug.log`,
   `uploads`, pliki tymczasowe — tam mogą leżeć dane osobowe, których „usuń wszystkie dane"
   nie widzi, bo nie patrzy poza `wp_options` i tabele.
3. **Kopie zapasowe i replikacja.** Dokumentacja obu wtyczek sama argumentuje kluczem
   w `wp-config.php`, *„żeby nie wychodził z kopią zapasową bazy"* — czyli uznajemy kopie
   za wektor. Nie sprawdziłem, co się dzieje z danymi w kopiach po wniosku RODO: kasowanie
   w żywej bazie nie dotyka wczorajszego dumpa.
4. **Multisite.** Patrz „czego nie sprawdziłem" — wymieniam ponownie, bo to nie jest luka w moim
   wykonaniu, tylko kategoria, której **na mapie polowania w ogóle nie ma**.
5. **Odzyskiwalność po `DROP TABLE`.** Sprawdziłem tylko kierunek „czy znika". Kierunek „czy da
   się cofnąć pomyłkę" nie był na liście, a dla właściciela jest równie ważny.

Miałem czym uzupełnić tę sekcję i uzupełniłem: pytanie o bazę zamknęło mnie w `wp_options`
i tabelach, a pliki, kopie zapasowe, multisite i odbiorcy zewnętrzni zostali pod nim nietknięci.
