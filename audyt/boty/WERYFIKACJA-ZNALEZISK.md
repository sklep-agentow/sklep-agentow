# WERYFIKACJA ZNALEZISK — rola osobna od audytora

**Cel roli:** nie potwierdzać, tylko **obalać**. Domyślnie zakładam, że autor się pomylił,
i dopiero gdy nie umiem tego wykazać, uznaję znalezisko za prawdziwe.
**commit_head:** `a2eb00e` (`origin/main` w chwili weryfikacji; audyty powstawały na `3fc884d`
i `807d72d` — sprawdziłem drift, patrz niżej).
**Gałąź:** `audyt/weryfikacja-znalezisk`. **Stos:** WP 7.0.4 / PHP 8.4.21, `localhost:8890`,
GreenMail `segm_imap`, Chromium headless (Playwright 1.62). ⛔ Lighthouse nie uruchamiany.
⛔ Kodu wtyczek ani dokumentacji **nie zmieniałem**.

**Drift kodu od czasu audytów:** między `3fc884d` a `a2eb00e` wszedł PR #136 (osiem plików,
+156/−7). W ChatBocie dołożył pasek błędu bazy w panelu i `delete_option('chatl_blad_bazy')`
w `uninstall.php` — **nie dotknął** ani `widget.js`, ani ścieżki zapisu ustawień, ani crona
w `uninstall.php`. Żadne z weryfikowanych znalezisk nie zostało tym PR-em naprawione.

---

## TABELA WERDYKTÓW

| kod | znalezisko (skrót) | werdykt | dowód |
|---|---|---|---|
| **Ł-1** | Pierwsza wiadomość **każdego** gościa ginie w pułapce na boty | ⛔ **OBALONE** | widget prosi o sesję **przy otwarciu czatu (168 ms)**, nie przy wysyłaniu; gość odpowiadający po 2,73 s dostaje poprawną odpowiedź i 2 wiersze w bazie |
| **Ł-A** | Cofanie przebiegu melduje sukces dla maili, których nie ruszyło | ✅ **POTWIERDZONE** | niezależne odtworzenie: `cofnietych=2 bledow=0`, wróciła **jedna** wiadomość |
| **A4-F1** | Błąd JS przy każdym otwarciu czatu; gość nie widzi konsultanta | ✅ **POTWIERDZONE** | odtworzone na `a2eb00e`: 0 odpytań w 20 s, `["odpytaj is not defined"]`, kontrola 2 odpytania |
| **A6-1** | Deklaracja prywatności nie wymienia całego ładunku do AI | ✅ **POTWIERDZONE** | strażnik autora na kodzie z repo: `PRZYPADKOW NIEZALICZONYCH: 1`, `EXIT=1`; treść §9 sprawdzona dosłownie |
| **A3-1** | `chatl_sesje` bez żadnej ścieżki kasowania | ✅ **POTWIERDZONE** | 10 odwołań do tabeli, **ani jedno** nie kasuje wierszy (tylko `DROP` w `uninstall.php`) |
| **A3-2** | Zombie cron ChatBota po odinstalowaniu | ✅ **POTWIERDZONE** (statycznie) | `uninstall.php` ChatBota: zero `wp_clear_scheduled_hook`; bliźniak Segregatora ma **dwa** |
| **KR1** | Puste pole „limit tokenów" wyłącza stop-loss po cichu | ⚠️ **POTWIERDZONE, INNA WAGA** | wada odtworzona; **droga dojścia opisana przez autora jest nieprawdziwa** — pomiar przeglądarki |
| **KR2** | Puste pole „limit dzienny" zjeżdża limit do 1 | ⚠️ **POTWIERDZONE, INNA WAGA** | j.w.; dodatkowo psuje się w stronę bezpieczną |
| **KR4** | Pusty port/limit IMAP → 1 | ⚠️ **POTWIERDZONE, INNA WAGA** | j.w. |
| **KR6** | Retencja ChatBota kasuje przed 90 dniami w strefie ujemnej | ✅ **POTWIERDZONE** | odtworzone na `a2eb00e`, z kontrolą na bliźniaku |

---

## ⛔ Ł-1 — OBALONE. Na czym autor się przejechał

To najcenniejszy wpis w tym raporcie, więc opisuję mechanizm pomyłki dokładnie.

**Twierdzenie autora:** *„Widget nie prosi o sesję przy otwarciu okna — prosi o nią leniwie,
w tym samym łańcuchu co wysyłane pytanie (`widget.js:53-59`, `:84-95`). Między wydaniem sesji
a pytaniem jest jeden obieg HTTP, nie «czas namysłu gościa». Ile gość pisał — nie ma znaczenia,
bo sesja powstaje w chwili wysłania."* Stąd wniosek: pułapka `CHATL_PULAPKA_SEKUND = 2` zjada
**pierwszą wiadomość każdego gościa**, zawsze.

**Czego autor nie sprawdził:** obie cytowane funkcje są **leniwe i zapamiętujące**
(`dajSesje()` zwraca `Promise.resolve(sesja)`, gdy sesja już jest) — ale trzecie miejsce,
`widget.js:527` w `start()`, woła `dajSesje()` **w chwili kliknięcia bąbelka**, czyli przy
otwarciu okna. Autor tej linii nie przeczytał i **nie uruchomił widgetu**: jego stanowisko to
`curl` strzelający `chatl_sesja` → natychmiast `chatl_pytanie`, co **z konstrukcji** odtwarza
„sesja rodzi się w chwili wysłania". Zmierzył własny scenariusz, nie zachowanie produktu.

**Pomiar w prawdziwej przeglądarce (Chromium headless, kod z `a2eb00e`):**

```
=== TEST 1: czy widget prosi o sesje JUZ PRZY OTWARCIU okna? ===
  sessionStorage TUZ PO otwarciu, PRZED jakimkolwiek pytaniem: MqmKMVD56bqkmupMcNx9SiQtJAiHgbfH
  zadania do tej pory: ZADANIE chatl_sesja @+168ms
=== TEST 2: gosc pisze pytanie w normalnym tempie i wysyla ===
  odstep OTWARCIE->WYSLANIE: 2.73 s
  ostatnia wiadomosc w oknie: Pracujemy od poniedziałku do piątku w godzinach 8:00–16:00. ...
=== CO ZOSTALO W BAZIE ===
wierszy rozmowy=2
  6063 gosc reguly Jakie sa godziny otwarcia?
  6064 bot reguly Pracujemy od poniedziałku do piątku w godzinach 8:00–16:00.
sesja utworzona: 2026-08-21 19:25:07
```

Zegar pułapki startuje przy **otwarciu czatu**, a nie przy wysyłaniu, więc „czas namysłu gościa"
liczy się w całości. Produkt zachował się poprawnie: odpowiedź z bazy Q&A, dwa wiersze zapisane.

**Co z tej obawy ZOSTAJE — mierzone, nie dopowiadane.** Mechanizm pułapki działa i w oknie
poniżej dwóch sekund od otwarcia rzeczywiście gubi wiadomość po cichu:

```
=== SCENARIUSZ ADWERSARYJNY: gosc wysyla NAJSZYBCIEJ, JAK SIE DA po otwarciu ===
  odstep OTWARCIE->WYSLANIE: 0.155 s
  ostatnia wiadomosc: Nie znam odpowiedzi na to pytanie. Zostaw proszę maila i pytanie ...
  licznik pulapki PRZED: {"ile":153,...,"powod":"ukryte pole"}
  licznik pulapki PO:   {"ile":154,"ostatnia":"2026-08-21 19:26:12","powod":"zbyt szybkie żądanie"}
wierszy rozmowy=0
```

Ale 0,155 s uzyskałem **programowym kliknięciem po wpisaniu treści skryptem** — człowiek nie
napisze pytania w tym czasie. Realny gość musiałby otworzyć czat, napisać pytanie i wysłać
w **niecałe 1,9 sekundy**. To jest wąska krawędź warta odnotowania, a **nie** „pierwsza
wiadomość każdego gościa". Waga zdania *„produkt za 149 zł nie odpowiada nikomu przy pierwszym
kontakcie"* jest nieprawdziwa.

⚠️ **Lekcja metody:** `curl` odtwarza **protokół**, nie **klienta**. Wszędzie tam, gdzie
o wyniku decyduje KOLEJNOŚĆ i CZAS zdarzeń po stronie przeglądarki, stanowisko curl-owe
odpowiada na inne pytanie niż zadane.

---

## ✅ Ł-A — POTWIERDZONE (odtworzone niezależnie)

Uruchomiłem skrypt autora (`audyt/boty/repro/lamacz-cofanie.php` z gałęzi
`origin/audyt/lamacz-segregator`) bez zmian, na tym samym GreenMailu:

```
############ PROBA KONTROLNA — nikt nie rusza maila (tak ma dzialac) ############
COFNIECIE: ok=true cofnietych=1 bledow=0 blad=''
>>> FOLDER ZRODLOWY: UID9:LAMA lamzndo07rr            <-- wrocila, zgadza sie
############ PROBA LAMIACA — wlasciciel przeciagnal maila recznie ############
COFNIECIE: ok=true cofnietych=2 bledow=0 blad=''
>>> FOLDER ZRODLOWY: UID11:LAMA lamzndo07rr           <-- wrocil TYLKO jeden
>>> FOLDER LAM-gdzieindziej: UID2:LAMB lamzndo07rr    <-- LAMB nadal tam, gdzie go zostawiono
```

**Próba obalenia, której szukałem — nie znalazłem jej.** Sprawdziłem, czy `segm_skrzynka_cofnij()`
nie ma dodatkowej straży, o której autor nie wiedział: ma jedną, ale niewystarczającą —
gdy `uid_docelowy <= 0`, szuka wiadomości po `Message-ID`
(`skrzynka-przebieg.php`, pętla cofania) i wtedy poprawnie liczy błąd. W scenariuszu autora
`uid_docelowy` jest **dodatni** (zapisany z `COPYUID` przy przebiegu), więc ta ścieżka się
nie uruchamia, a `Segm_Imap::przenies()` uznaje ruch za udany po samym `'OK' !== $odp['status']`.
Kod potwierdza opis autora. Sprawdziłem też blokadę drugiego podejścia — jest:
`if ( (int) $przebieg->cofniety > 0 ) … 'Ten przebieg został już cofnięty.'`.
Waga opisana **uczciwie**: autor sam pisze, że to nie jest utrata maili, i tak jest.

## ✅ A4-F1 — POTWIERDZONE (odtworzone na kodzie po PR #136)

```
=== GOSC SIEDZI Z OTWARTYM OKNEM I CZEKA 20 s (nic nie pisze) ===
  odpytan REST przez te 20 s: 0
  czy gosc zobaczyl "Do rozmowy dolaczyl konsultant"? ⛔ NIE
  czy gosc zobaczyl wiadomosc operatora ("Tu Anna")? ⛔ NIE
=== KONTROLA: gosc pisze DRUGIE pytanie ===
  odpytan REST po drugim pytaniu: 2   <- >0 = licznik DZIALA
  teraz widzi konsultanta? TAK ; teraz widzi wiadomosc operatora? TAK
  bledy JS: ["odpytaj is not defined"]
```
Kontrola w tym samym przebiegu odróżnia „nic nie poleciało" od „licznik nie liczy".
⚠️ Uwaga o niezależności: raport A4 pisała ta sama sesja co ten dokument. Odtworzenie jest
niezależne od tamtego przebiegu (nowy commit, nowy start), ale **nie** od autora — jeśli to
istotne dla decyzji, ta jedna pozycja zasługuje na trzecią parę oczu.

## ✅ A6-1 — POTWIERDZONE

Deklaracja z `INSTRUKCJA.md:309-317` sprawdzona dosłownie — wylicza trzy składniki:
*„treść pytania gościa wraz z Twoim opisem firmy i bazą Q&A"*. Kod dokłada czwarty:
`front.php:456` przekazuje `chatl_ai_historia()` do `chatl_ai_odpowiedz()`, a
`CHATL_AI_PAMIEC_WYMIAN = 6` (`ai.php:47`). Strażnik autora na kodzie z repo:

```
  OK   0. kontrola: w ladunku SA skladniki, ktore deklaracja wymienia
  BLAD 1. deklaracja NIE wymienia wczesniejszych wiadomosci goscia, a one wychodza do API
         [w ladunku jest: "Jan Kowalski, tel 555-111-222"]
  OK   2. deklaracja nie wymienia niczego ponad to, co kod wysyla
PRZYPADKOW NIEZALICZONYCH: 1     EXIT=1
```

**Próba obalenia:** najmocniejszy kontrargument brzmi „«treść pytania gościa» obejmuje też
wcześniejsze pytania". Nie broni się: w ładunku są także **odpowiedzi bota** (`role: assistant`),
a przede wszystkim właściciel przepisuje z tej sekcji własną politykę prywatności i z tego
zdania nie wywnioskuje, że wychodzi do sześciu wcześniejszych wymian. Waga „poważne — RODO"
utrzymana, z zastrzeżeniem: to **niekompletność dokumentu**, nie wyciek poza zadeklarowanego
odbiorcę ani poza tę samą rozmowę.

## ✅ A3-1 — POTWIERDZONE (statycznie, z kontrolą metody)

Wszystkie odwołania do tabeli sesji w kodzie ChatBota:
`uninstall.php:29` (`DROP TABLE`), `przejecie.php:66/164/213/246/276/356`, `instalator.php:84`,
`operator.php:33`, `front.php:674` — czyli `CREATE`, `INSERT`, `UPDATE`, `SELECT` i `DROP`.
**Ani jednego `DELETE`.** Retencja (`chatl_czyszczenie_historii`) czyści `chatl_rozmowy`
i `chatl_rate_limit`; kasownik RODO czyści `chatl_rozmowy` i `chatl_zgloszenia`.
⚠️ **Kontrola metody (G5), która mnie poprawiła:** mój pierwszy grep („`chatl_rozmowy` +
delete/DROP") pokazał tylko `DROP` także dla tabeli, która **jest** czyszczona — bo kasowanie
idzie przez zmienną `$tabela`. Dlatego werdykt opieram na przejrzeniu **wszystkich dziesięciu**
wystąpień, nie na wzorcu wyszukiwania.

## ✅ A3-2 — POTWIERDZONE statycznie; ⛔ nie mogłem odtworzyć wykonaniem

`grep wp_clear_scheduled_hook`: `segregator-maila/uninstall.php:19,20` (dwa zadania),
`chatbot-live/uninstall.php` — **zero**. ChatBot zdejmuje cron tylko w `chatl_deaktywacja()`
(`instalator.php:155`). Asymetria bliźniaków jest dokładnie taka, jak opisał autor, a PR #136
dołożył do tego pliku wyłącznie `delete_option('chatl_blad_bazy')`.
⛔ **Nie powtórzyłem przebiegu odinstalowania**, bo mój brief zabrania instalowania
i odinstalowywania wtyczek na współdzielonym stosie. Werdykt: potwierdzone **z kodu**,
wykonanie zostaje na dowodzie autora.

## ⚠️ KR1 / KR2 / KR4 — POTWIERDZONE, INNA WAGA. Autor zmyślił drogę dojścia

Sama wada odtwarza się na `a2eb00e` co do znaku:

```
     przed: limit_tokenow=500000, limit_dzienny=300
     po   : limit_tokenow=0  (0 = STOP-LOSS WYLACZONY), limit_dzienny=1
     wlasciciel zobaczyl: .../admin.php?page=chatl-ustawienia&chatl_ok=zapisano
KR1   PADA ; KR2   PADA ; KR3   PRZESZLO (kontrola: poprawne liczby zapisuja sie)
     Segregator po pustych polach: port=1, limit=1
KR4   PADA
```

**Ale uzasadnienie realności jest w raporcie A5 nieprawdziwe.** Autor napisał: *„Wystarczy
«50 000» ze spacją albo «50,000» z przecinkiem — czyli polski zapis liczby"* — i **tego nie
zmierzył**, tylko wstrzyknął `''` prosto do `$_POST`. Zmierzyłem to przeglądarką:

```
  wpisane "50 000"  -> input.value="50000"  WYSLANE="50000"  poprawne=true
  wpisane "50,000"  -> input.value="50000"  WYSLANE="50000"  poprawne=true
  wpisane "50.000"  -> input.value="50.000" WYSLANE="50.000" poprawne=false   (formularz sie NIE wysle)
  wpisane "abc"     -> input.value=""       WYSLANE=""       poprawne=true    <-- TU jest ta droga
  wpisane "1e5"     -> input.value="1e5"    WYSLANE="1e5"    poprawne=true
```

Chromium **normalizuje** spację i przecinek do `50000`, więc oba przykłady autora trafiają
do bazy poprawnie. Realne drogi dojścia są dwie i obie prostsze, niż autor sądził:
**wyczyszczenie pola** (pole nie jest `required`, `min="0"` dopuszcza pustkę) oraz **wpisanie
liter**, po którym przeglądarka wysyła pusty łańcuch. Wada zostaje, waga KR1 zostaje poważna
(stop-loss kosztowy gaśnie przy zielonym „zapisano"), ale **historyjka o polskim zapisie liczb
nie może iść do naprawy jako uzasadnienie** — ktoś oprze na niej test i test będzie zielony.

## ✅ KR6 — POTWIERDZONE

```
     przy strefie UTC zostalo: KRAW-89d, KRAW-90d           (KR5 kontrola: granica dziala)
     ChatBot,    gmt_offset=-5: wpis wieku 89 dni 20 h przezyl? NIE — skasowany PRZED czasem
     Segregator, gmt_offset=-5: wpis wieku 89 dni 20 h przezyl? TAK   (KR7 kontrola na blizniaku)
```
Dwie kontrole w tym samym przebiegu: sama granica 90/91 w UTC działa, a bliźniacza retencja
Segregatora (z poprawką o `gmt_offset`) przechodzi próbę, na której ChatBot pada.

---

## ⚠️ STAN STOSU — co zmieniłem i co przywróciłem

Stos jest współdzielony, więc wypisuję wszystko.

| co | stan zastany | co zrobiłem |
|---|---|---|
| `chatl_ustawienia` → `ai_limit_dzienny` / `ai_limit_tokenow` | `100` / `200000` | test krawędzi zostawił `200` / `0` (stop-loss wyłączony!) — **przywróciłem do `100` / `200000`**, potwierdzone odczytem |
| `segm_skrzynka` → `port` / `limit` | nieznany (już wcześniej nadpisany przez przebieg A5) | test zostawił `1` / `1` — **ustawiłem wartości domyślne z kodu**: `993` / `50`. `tryb` pozostał `na_sucho`, `host` pusty |
| `gmt_offset`, `timezone_string` | `2`, `Europe/Warsaw` | test zmienia i przywraca sam — sprawdzone po fakcie: `2`, `Europe/Warsaw` ✅ |
| `chatl_pulapka` (licznik) | `{"ile":153}` | `{"ile":154, "powod":"zbyt szybkie żądanie"}` — **nie da się cofnąć**, licznik przyrostowy |
| `wp_chatl_rozmowy`, `wp_chatl_sesje` | — | dołożone wiersze testowych rozmów (kilkanaście), zostają |
| GreenMail + `segm_skrz_przebiegi/ruchy` | — | skrypt Łamacza sam sprząta (`[sprzatanie wykonane]`), przebiegi `159`, `160` usunięte przez jego shutdown |

⛔ Kodu wtyczek, dokumentacji ani paczek **nie dotykałem**; wtyczek nie instalowałem
ani nie odinstalowywałem; bazy nie czyściłem.

---

## CZEGO NIE ZWERYFIKOWAŁEM

- **Wszystkie pozostałe znaleziska** z siedmiu raportów poza dziesięcioma w tabeli — w tym
  cała reszta A2, A5, A6-2 i pozycje z `RAPORT-LAMACZ-SEGREGATOR` inne niż Ł-A.
- **A3-2 wykonaniem** (zakaz odinstalowywania wtyczek na współdzielonym stosie).
- **Wariant ostrzejszy Ł-A** (skasowany folder kategorii przed cofaniem) — odtworzyłem tylko
  scenariusz z ręcznym przeniesieniem maila.
- **Zachowanie pól liczbowych w Firefoksie** — pomiar normalizacji `type=number` zrobiłem
  wyłącznie w Chromium. Jeśli Firefox nie normalizuje spacji, przykład autora KR1 mógłby tam
  być prawdziwy; **nie sprawdziłem**, a Firefox jest na tej maszynie.
- **Ł-1 w realnym tempie pisania człowieka** — mierzyłem 2,73 s (poprawnie) i 0,155 s
  (pułapka). Nie zmierzyłem, jak często realny gość mieści się poniżej 1,9 s przy bardzo
  krótkim pytaniu w rodzaju „cena?"; to jedyna luka, przez którą część obawy Ł-1 przeżywa.

---

## BRAMKI

```
=== PODSUMOWANIE ===
  actionlint   EXIT=0      manifest EXIT=0      slady EXIT=0      php-lint EXIT=0
  phpcs        EXIT=1      phpstan  EXIT=1      gitleaks EXIT=0
WYKONANE BRAMKI: 7 z 7 (tryb: pelny, wymagane minimum: 7)
NIEZALICZONE BRAMKI: 2        EXIT=1
```

Obie porażki są **środowiskowe, nie treściowe** i nie mają związku z tym raportem
(dokładam wyłącznie plik `.md`):

```
--- BRAMKA phpcs ---     BRAK vendor/bin/phpcs — czy composer install przeszedl?
--- BRAMKA phpstan ---   BRAK vendor/bin/phpstan — czy composer install przeszedl?
```

W tym drzewie roboczym nie ma katalogu `vendor/`. ⚠️ Drobiazg do protokołu: zlecenie mówiło
o **ośmiu** bramkach, a `bramki.sh` melduje **7 z 7** — dokładnie ta klasa rozjazdu, przed którą
ostrzega `CLAUDE.md` („nie przepisuj listy bramek"). Źródłem prawdy jest skrypt.
