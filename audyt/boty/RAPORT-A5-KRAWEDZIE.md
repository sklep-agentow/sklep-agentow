# RAPORT AUDYTU A5 — KRAWĘDZIE: puste, dokładnie maksimum, jeden ponad

**Data:** 2026-08-21 · **Soczewka:** co się dzieje na granicach — przy pustym, przy maksimum
i przy maksimum plus jeden. Miejsca, gdzie kod działa dla typowych danych i wywraca się dla
skrajnych, a klient trafia na nie przypadkiem.
**commit_head:** `3fc884d9d7c3d9b47b8229b4c3be5ddcd1eea5e4`
**Audytowane drzewo wtyczek:** `807d72d61c07108a11a934e4d2b74f691ea7c495` — sprawdzone, nie
założone: `git diff --stat 807d72d HEAD -- wtyczki/chatbot-live/ wtyczki/segregator-maila/` →
**pusty**. Na `main` doszły od czasu raportu A2 tylko materiały audytowe (#131, #132, #134).

> ⛔ **TEN RAPORT JEST NIEDOKOŃCZONY.** Sesja koordynatorki skończyła się w trakcie. Pokryte
> zostały punkty **(2) liczby** i **(5) czas/retencja** oraz przeczytany bez wykonania punkt
> **(3) stronicowanie**. Punkty **(1) długości pól tekstowych** i **(4) znaki (ogonki, emoji,
> cudzysłowy, HTML)** — **NIE WYKONANE**. Szczegóły i co robić dalej: sekcja „CO ZOSTAŁO
> NIEDOKOŃCZONE" na końcu.

---

## PODSUMOWANIE DLA DECYDUJĄCEJ (jeden akapit)

**Cztery znaleziska dowiedzione wykonaniem, wszystkie z dwóch klas.** Klasa pierwsza (KR1, KR2,
KR4): **puste pole liczbowe jest po cichu zamieniane na wartość graniczną, a właściciel dostaje
zielone „zapisano"**. Najpoważniejszy jest **KR1** i on jeden wystarczy, żeby tę rundę uznać za
opłacalną: w ChatBocie wartość `0` w polu „dobowy limit tokenów" znaczy **„stop-loss wyłączony"**,
a puste pole daje dokładnie `0` — czyli **drugi bezpiecznik kosztowy da się wyłączyć literówką**,
bez jednego słowa ostrzeżenia. To nie wymaga curl-a ani złej woli: przeglądarka wysyła **pustą
wartość**, gdy treść pola `type="number"` jest dla niej nieparsowalna, więc wystarczy wpisać
„50 000" ze spacją albo „50,000" z przecinkiem — czyli tak, jak liczby zapisuje polski użytkownik.
KR2 (limit dobowy wywołań spada wtedy do 1) i KR4 (port IMAP Segregatora spada do 1) to ta sama
klasa, ale one **psują się w stronę bezpieczną** — bot przestaje używać AI, skrzynka przestaje się
łączyć; kosztują zdezorientowanego klienta, nie pieniądze. Klasa druga to **KR6**: retencja
ChatBota liczy granicę w UTC, a znaczniki w bazie są w czasie witryny, więc **u kupującego w
strefie ujemnej rozmowy znikają PRZED obiecanymi 90 dniami** — zmierzone: wpis w wieku 89 dni
i 20 godzin został skasowany. Segregator ma w tym samym miejscu poprawkę o `gmt_offset` i na tej
samej próbie przechodzi (KR7), więc to znów rozjazd bliźniaków, nie ograniczenie platformy.
⛔ Kodu nie naprawiałem. ⚠️ Uczciwie: **jedna moja asercja była źle postawiona i przepuściła wadę**
(pierwszy przebieg pokazał KR4 jako PRZESZŁO przy porcie 1) — opisuję to niżej, bo to samo w sobie
jest lekcją o przyrządzie.

---

## STANOWISKO I PRZYRZĄD

WP 7.0.4 / PHP 8.4.21 / MariaDB 11.8.8, kontener `strona_wp_wordpress`; wtyczki wgrane
z audytowanego drzewa (`chatbot-live 1.5.8`, `segregator-maila 1.3.1` wg `wp plugin list`).
Przyrząd: `wp eval-file` na `audyt/boty/testy-audytu/test-krawedzie.php` (kopia w załączniku).
Woła prawdziwe handlery panelu (`chatl_post_ustawienia_zapisz`, `segm_post_skrzynka_zapisz`)
i prawdziwe zadania cykliczne (`chatl_czyszczenie_historii`, `segm_czyszczenie_probki`),
przechwytując `wp_safe_redirect()` filtrem `wp_redirect`, żeby odczytać, co zobaczy właściciel.

### Wynik na kodzie audytowanym

```
KR1   PADA      ChatBot: pusta wartosc w polu "limit tokenow" NIE moze po cichu WYLACZYC stop-lossu (0 = wylaczony)
KR2   PADA      ChatBot: pusta wartosc w polu "limit dzienny" NIE moze po cichu zjechac limitu do 1
KR3   PRZESZLO  PROBA KONTROLNA: poprawne liczby zapisuja sie tym samym przebiegiem
KR4   PADA      Segregator: pusty port IMAP NIE moze po cichu stac sie portem 1
KR5   PRZESZLO  ChatBot: dzien 90 zostaje, dzien 91 znika (strefa UTC) — sama granica
KR6   PADA      ChatBot: wpis MLODSZY niz 90 dni nie moze zniknac (obietnica retencji 90 dni)
KR7   PRZESZLO  PROBA KONTROLNA: blizniacza retencja Segregatora na tej samej granicy i strefie
ZDANYCH 3/7, PADA 4
EXIT=1
```

⭐ **KR3, KR5 i KR7 to próby kontrolne** — poprawne liczby zapisują się tym samym przebiegiem,
sama granica 90/91 w UTC działa jak obiecano, a bliźniacza retencja Segregatora przechodzi tę
samą próbę, na której ChatBot pada. Test nie jest „zawsze czerwony" i nie pada na braku pliku.

### ⚠️ Dwie wady MOJEGO przyrządu, złapane w trakcie — obie warte zapisania

1. **Fałszywe ZIELONE.** Pierwszy przebieg pokazał `KR4 PRZESZLO`, choć w wydruku obok stało
   `port=1`. Asercja brzmiała „port = 993 **albo** w adresie nie ma `segm_ok=`" — a handler
   Segregatora przekierowuje po zapisie na `page=segm-skrzynka` **bez** znacznika `segm_ok`,
   więc drugi człon spełniał się zawsze. Asercja poprawiona na sam pomiar portu; dopiero wtedy
   KR4 pokazało wadę. To jest dokładnie wniosek nr 2 z `MATERIAL-AUDYTU-CHATBOT.md`
   („zielone — sprawdź, czy cokolwiek zmierzył") i **złapałem to na sobie**, nie w produkcie.
2. **Fałszywe CZERWONE.** Pierwszy przebieg pokazał `KR7 PADA`, czyli rzekomo tę samą wadę
   retencji w Segregatorze. Wiersz kontrolny **nigdy nie powstał**: `$wpdb->insert` zwracał
   `false` z `Unknown column 'kategoria_wynik' in 'INSERT INTO'`, bo `segm_maile` ma kolumny
   `id, zrodlo, nadawca, temat, tresc, kategoria_oczekiwana, utworzono`. Pustka po nieudanym
   zapisie wyglądała identycznie jak skasowanie. To jest **ta sama klasa, którą repo ma
   zapisaną jako `pusty-wynik-zle-nazwana-kolumna`** — po poprawce KR7 przechodzi. Do testu
   dołożyłem strażnika, który melduje, gdy wiersz kontrolny nie powstał.

---

## ZNALEZISKA

### 🐞 KR1 — puste pole „dobowy limit tokenów" WYŁĄCZA drugi stop-loss kosztowy, po cichu

**Plik i linia:** `wtyczki/chatbot-live/includes/admin.php:1051`
(`'ai_limit_tokenow' => isset( $_POST['ai_limit_tokenow'] ) ? max( 0, min( 100000000, (int) $_POST['ai_limit_tokenow'] ) ) : $stare['ai_limit_tokenow']`)
**Semantyka zera:** `wtyczki/chatbot-live/includes/ai.php:247-250` — `if ( $limit_tokenow > 0 && … )`,
a panel pisze wprost **„0 = wyłączony"** (`admin.php:910`).
**Waga:** poważne — jedyny bezpiecznik, który widzi RACHUNEK (limit wywołań nie widzi, ile
kosztuje jedno wywołanie), da się wyłączyć literówką.
**Status kalibracji:** ✅ PADA na `807d72d`; kontrola KR3 na tym samym przebiegu PRZECHODZI.

```
     przed: limit_tokenow=500000, limit_dzienny=300
     po   : limit_tokenow=0  (0 = STOP-LOSS WYLACZONY), limit_dzienny=1
     wlasciciel zobaczyl: .../admin.php?page=chatl-ustawienia&chatl_ok=zapisano
```

**Dlaczego to jest realne, a nie laboratoryjne:** pole jest `type="number"`. Gdy przeglądarka nie
potrafi sparsować jego treści, **wysyła pustą wartość**, nie to, co widać na ekranie. Wystarczy
„50 000" (spacja), „50.000" albo „50,000" — czyli polski zapis liczby. `isset()` jest wtedy
prawdą, `(int) ''` daje `0`, `max( 0, 0 )` daje `0`, a `0` znaczy **wyłączony**. Po zapisie panel
przestaje rysować „/ limit" przy zużyciu (`admin.php:899-901`) i to jedyny ślad — żadnego
komunikatu.

### 🐞 KR2 — puste pole „dobowy limit wywołań" zjeżdża limit do 1, po cichu

**Plik i linia:** `wtyczki/chatbot-live/includes/admin.php:1041` · bliźniak w drugiej wtyczce:
`wtyczki/segregator-maila/includes/admin.php:1254` (**identyczny zapis, NIE URUCHOMIONY**).
**Waga:** średnia — psuje się w stronę bezpieczną (bot zjeżdża do reguł), ale klient widzi
„zapisano" i bota, który przestał używać AI, bez wskazówki dlaczego.
**Status kalibracji:** ✅ PADA na `807d72d` (wydruk wyżej: `limit_dzienny=1`).

### 🐞 KR4 — puste pole „port"/„limit" skrzynki IMAP daje port 1 i limit 1, po cichu

**Plik i linie:** `wtyczki/segregator-maila/includes/admin-skrzynka.php:433` (port) i `:438` (limit).
**Waga:** średnia — połączenie z portem 1 padnie, ale komunikat będzie mówił o błędzie sieci,
nie o tym, że port został po cichu podmieniony.
**Status kalibracji:** ✅ PADA na `807d72d` po poprawieniu mojej asercji (patrz wada przyrządu nr 1).

```
     Segregator po pustych polach: port=1, limit=1
     wlasciciel zobaczyl: .../admin.php?page=segm-skrzynka
```

### 🐞 KR6 — retencja ChatBota kasuje rozmowy PRZED obiecanymi 90 dniami w strefie ujemnej

**Plik i linia:** `wtyczki/chatbot-live/chatbot-live.php:95-99` —
`gmdate( 'Y-m-d H:i:s', time() - CHATL_RETENCJA_DNI * DAY_IN_SECONDS )`
**Bliźniak zrobiony POPRAWNIE:** `wtyczki/segregator-maila/segregator-maila.php:145-148` —
`gmdate( 'Y-m-d H:i:s', time() + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - SEGM_RETENCJA_DNI * DAY_IN_SECONDS )`
**Waga:** średnia w Polsce (UTC+2 → dane żyją 2 h **dłużej**), **poważna dla kupującego w strefie
ujemnej** (UTC-5 → dane giną 5 h **wcześniej**), bo obietnica „90 dni" pada gościowi w tekście
o prywatności i w odpowiedzi na wniosek RODO.
**Status kalibracji:** ✅ PADA na `807d72d`; KR5 (sama granica 90/91 w UTC) i KR7 (bliźniak
Segregatora na tej samej próbie) PRZECHODZĄ.

```
     przy strefie UTC zostalo: KRAW-89d, KRAW-90d          <- granica dziala jak obiecano
     ChatBot,    gmt_offset=-5: wpis wieku 89 dni 20 h przezyl? NIE — skasowany PRZED czasem
     Segregator, gmt_offset=-5: wpis wieku 89 dni 20 h przezyl? TAK
```

Mechanizm: `utworzono` zapisywane jest przez `current_time( 'mysql' )`, czyli w **czasie witryny**,
a granica liczona `gmdate()` z `time()`, czyli w **UTC**. Porównywane są dwa różne układy
odniesienia, więc próg przesuwa się o offset strefy.

---

## CO SPRAWDZIŁEM I WYSZŁO CZYSTO

- **Granica retencji jako taka** (KR5): przy `gmt_offset = 0` wpis w wieku 90 dni bez minuty
  **zostaje**, wpis w wieku 91 dni **znika**. Warunek to `utworzono < granica`, więc „starsza niż
  90 dni" w dokumentacji jest zgodne z kodem.
- **Stronicowanie — przeczytane, NIE WYKONANE.** Obie wtyczki przycinają numer strony do zakresu
  i nie da się nimi wyjść poza listę: ChatBot `min( $strona, $stron )` z komentarzem wprost o
  adresie z zakładki po skasowaniu wpisów (`includes/admin.php:551-554` i `:689-691`), Segregator
  `max( 1, min( max( 1, $stron ), $nr ) )` (`includes/admin.php:158-161`), a raport luk
  `min( chatl_numer_strony(), $luki_stron )` (`includes/statystyki.php:753-754`). Ujemny i zerowy
  numer strony podnoszony jest do 1 przez `max( 1, (int) … )`, więc **ujemny OFFSET w SQL nie
  powstaje**. ⛔ To odczyt kodu — **nie kliknąłem i nie wykonałem żadnej z tych ścieżek**.
- **Kontrola zapisu poprawnych liczb** (KR3): `250000` i `77` zapisują się tym samym przebiegiem,
  więc wada z KR1/KR2 dotyczy wyłącznie wartości granicznej, a nie zapisu w ogóle.

---

## CO ZOSTAŁO NIEDOKOŃCZONE — i co zrobiłbym dalej

Sesja skończyła się w trakcie. **Z pięciu punktów zakresu zamknąłem dwa (liczby, czas), jeden
przeczytałem bez wykonania (stronicowanie), dwóch nie tknąłem.** Kolejność poniżej to moja
kolejność ważności.

1. ⬜ **Punkt (1) — długości pól tekstowych. NIE WYKONANE.** Granice są zmierzone i wypisane,
   brakuje ich przejechania: `CHATL_MAX_PYTANIE = 500` (`includes/front.php:11`, obcięcie
   `:418-420`), `CHATL_MAX_WIADOMOSC = 2000` (`:12`, obcięcie `:550-551` i w panelu operatora
   `includes/operator.php:353-354`), `SEGM_MAX_POLE = 2000` (`includes/csv.php:13`),
   `SEGM_KONFIG_MAX_WZORZEC = 2000` (`includes/konfiguracja.php:46`), nazwa kategorii `100`
   (`includes/admin.php:866`), nadawca `190` (`includes/admin-probka.php:265`).
   **Wszystkie te miejsca to `mb_substr()` bez jednego słowa do użytkownika** — czyli z góry
   podejrzenie „ucinane po cichu", ale **tego nie zmierzyłem** i nie wolno tego zgłosić jako
   znaleziska. **Jak to zrobić:** ścieżka gościa wymaga nonce `chatl_front`
   (`includes/front.php:254`), sesji z ciastkiem-sekretem (`chatl_ajax_sesja`,
   `includes/przejecie.php:549`) i odczekania progu pułapki `CHATL_PULAPKA_SEKUND = 2`, więc
   najtaniej: `curl` ze słoikiem ciastek przeciw `http://localhost:8890/wp-admin/admin-ajax.php`
   — kolejno `chatl_nonce`, `chatl_sesja`, `sleep 2`, `chatl_pytanie` z pytaniem długości 499 /
   500 / 501 — i pomiar `mb_strlen` zapisanego wiersza w `wp_chatl_rozmowy` plus treść odpowiedzi
   JSON. **Uwaga na próg pułapki**: to on wywrócił poprzednie narzędzie QA (D3 w materiale).
2. ⬜ **Punkt (4) — znaki: ogonki, emoji, cudzysłowy, HTML w treści idącej do maila i do panelu.
   NIE WYKONANE.** Ta sama ścieżka curl-owa co wyżej załatwia oba punkty jednym przebiegiem.
   Do sprawdzenia w szczególności: czy emoji (4 bajty UTF-8) przeżywa `wp_chatl_rozmowy.tresc`
   i `wp_chatl_zgloszenia.wiadomosc` — **nie potwierdziłem porównania tabel** (`get_charset_collate()`,
   `includes/instalator.php:18`); czy `mb_substr()` nie tnie w połowie znaku; czy treść z `<script>`
   i cudzysłowami wychodzi bezpiecznie do maila powiadomienia (`includes/front.php:605-612`,
   treść budowana konkatenacją) i do panelu. Łapacz `mailpit` stoi na `http://127.0.0.1:8025`
   i ma API do odczytania ostatniej wiadomości.
3. ⬜ **Punkt (3) — stronicowanie WYKONAĆ, nie tylko przeczytać.** Zero pozycji, dokładnie jedna
   strona, jedna pozycja ponad stronę, ostatnia strona, strona spoza zakresu. Progi:
   `CHATL_NA_STRONE_ZGLOSZEN = 200`, `CHATL_NA_STRONE_ROZMOW = 100`, `SEGM_NA_STRONE = 50`.
4. ⬜ **Bliźniak KR2 w Segregatorze** (`includes/admin.php:1254`) — zapis identyczny, **nie
   uruchomiony**. Domknąć tym samym testem, dopisując przypadek.
5. ⬜ **Krawędzie importu, w ogóle nietknięte:** `CHATL_IMPORT_MAX_WIERSZY = 2000` i
   `CHATL_IMPORT_MAX_BAJTOW = 2 MiB`, `SEGM_IMPORT_MAX_WIERSZY = 500` /
   `SEGM_IMPORT_MAX_BAJTOW = 5 MiB`, `SEGM_KONFIG_MAX_WPISOW = 500`. Dla każdego: dokładnie
   maksimum i jeden ponad — czy komunikat mówi, ILE odrzucono, czy plik przechodzi obcięty.
6. ⬜ **Krawędź, której nie zdążyłem nawet nazwać w teście:** `email varchar(190)`
   (`includes/instalator.php:36`) kontra `sanitize_email()`, który nie ogranicza długości.
   Adres dłuższy niż 190 znaków przy `STRICT_TRANS_TABLES` wywali INSERT (gość zobaczy komunikat
   o awarii zapisu, mylący), a bez trybu ścisłego zostanie obcięty po cichu. **Nie sprawdziłem,
   w jakim trybie chodzi ta baza** — próba `SELECT @@sql_mode` przez `mariadb -uroot` odpadła
   na haśle; następnym razem `wp db query`.

**Stan mapy polowania:** ta runda dotyka celów z sekcji **A5 — KRAWĘDZIE** i częściowo **A3**
(retencja). Nie odhaczam ich w `material/06-mapa-polowania.md` sam, bo dwa z pięciu punktów
zakresu zostały nietknięte, a licznik ma mówić prawdę.

---

## CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Kategorie, o które moje pytanie („co na granicach") **nie zapytało**, a które przy tych modułach
wyglądają na istotne:

1. **Krawędź, która nie jest liczbą ani długością: BRAK POLA.** Cały wzorzec
   `isset( $_POST[…] ) ? … : $stare[…]` zachowuje się inaczej dla „pole puste" niż dla „pola
   w ogóle nie ma". Formularz, z którego przeglądarka nie wyśle checkboxa (a tak działa
   niezaznaczony checkbox), trafia w gałąź „nie ma" — sprawdziłem to tylko tam, gdzie akurat
   patrzyłem na liczby. **`usun_dane` i `powiadomienia` są właśnie takimi checkboxami**
   (`includes/admin.php:1043-1044`, `empty()` zamiast `isset()`), a jeden z nich decyduje, czy
   odinstalowanie skasuje dane klienta. Nie badałem tego.
2. **Krawędź czasu, której nie ruszyłem: zmiana czasu letni↔zimowy.** Zbadałem stałe przesunięcie
   strefy, ale nie **przeskok** — dobę o 23 lub 25 godzinach, w której licznik dobowy AI
   (`current_time( 'Y-m-d' )`, klucz `dzien` w `chatl_ai_uzycie`) i granica retencji przechodzą
   przez zmianę. To jest inne pytanie niż KR6 i moje nie zapytało o nie.
3. **Krawędź współbieżności przy granicy.** „Dokładnie maksimum" przy dwóch żądaniach naraz to
   nie to samo co przy jednym — limit dobowy jest w kodzie nazwany „miękkim", ale nikt nie
   zmierzył, o ile miękkim. Moje pytanie było o wartość graniczną, nie o wyścig o nią.
4. **Krawędź pustej instalacji.** Zero reguł, zero kategorii, zero Q&A, zero maili w próbce —
   sprawdziłem „zero pozycji" tylko jako listę do narysowania, nie jako **stan startowy całego
   produktu**: czy przebieg po próbce bez reguł mówi coś sensownego, czy bot bez ani jednego
   wpisu Q&A i bez klucza API tłumaczy właścicielowi, co ma zrobić.
5. **Krawędź górna, której nie da się osiągnąć testem jednostkowym: hosting.** `CHATL_IMPORT_MAX_BAJTOW`
   to 2 MiB, ale realną granicą bywa `upload_max_filesize`/`post_max_size` hostingu — i wtedy
   plik nie dochodzi w ogóle, a wtyczka widzi puste `$_FILES`. Czy mówi wtedy prawdę, nie wiem.
6. **Krawędź, o którą pytał poprzedni raport, a ta soczewka jej nie widzi:** czy komunikat
   graniczny w ogóle **dojdzie do właściciela**. `*_log_bledu_bazy()` idzie przez
   `wp_trigger_error()`, który przy `WP_DEBUG = false` nic nie zapisuje — a wszystkie cztery
   dzisiejsze znaleziska są ciche **z definicji**, bo nie logują niczego.

---

## ZAŁĄCZNIK — źródło testu

Plik leży też osobno w repozytorium: `audyt/boty/testy-audytu/test-krawedzie.php`.

```
docker cp audyt/boty/testy-audytu/test-krawedzie.php strona_wp_wordpress:/tmp/k.php
docker exec strona_wp_wordpress wp --allow-root eval-file /tmp/k.php ; echo "EXIT=$?"
```

Oczekiwane na `807d72d`: `ZDANYCH 3/7, PADA 4`, `EXIT=1`.
⚠️ Test **zmienia i przywraca** `gmt_offset`, `timezone_string`, `chatl_ustawienia`
i `segm_skrzynka`, wstawia i kasuje wiersze `KRAW-*` oraz `krawedz@test` —
**wyłącznie na stanowisku testowym**, nigdy na instalacji klienta.
