# Domknięcie pięciu otwartych celów z mapy polowania (DANE + KRAWĘDZIE)

- `commit_head`: **f379fe0** (`origin/main` w chwili startu rundy).
- Gałąź: `audyt/dokonczenie-a3-a5`.
- Zakres: **oba boty** — `wtyczki/chatbot-live/` i `wtyczki/segregator-maila/`
  (cel 2 wprost wymienia wzorce `chatl_rl_%` i `segm_%`). Sklep dotknięty tylko
  w jednym miejscu: wzorzec `sklepag_%` wchodzi do tego samego testu `LIKE`.
- Skille sprawdzone przy celach 1/2/5: `wp-wpcli-and-ops` (operacje na bazie),
  `wp-plugin-development` (aktywacja/uninstall) — ale **każde kryterium wracało
  do pliku**: żadna pozycja niżej nie stoi na samej lekturze wytycznej.

**Pamięć przed startem:** `free -m` → wolne 269 MB, dostępne 1146 MB. Zasada
mówiła „poniżej 700 MB wolnej — czekaj", więc **nie uruchomiłem ani jednej
przeglądarki**. Cele 3 i 4, które normalnie zrobiłbym w przeglądarce, zrobiłem
przez `curl` (prawdziwe endpointy HTTP gościa) i przez wywołanie **prawdziwych
funkcji rysujących strony panelu** w WP-CLI. Pisze o tym sekcja „czego to nie
zastępuje" przy celu 3.

**Praca w ciemno:** nie otworzyłem `audyt/boty/RAPORT-*.md`,
`audyt/REJESTR-ZNALEZISK.md` ani `audyt/boty/WERYFIKACJA-ZNALEZISK.md`.

**Stan stosu przywrócony i sprawdzony** — na końcu rundy:
```
PRZYWROCONE: chatl_pulapka={"ile":154,…} chatl_blad_nonce=false
  resztki w chatl_qa: 0
  resztki w chatl_zgloszenia: 0
  resztki w segm_kategorie: 0
  resztki w segm_reguly: 0
  opcje probne DK w wp_options: 0
```
Niczego nie instalowałem ani nie odinstalowywałem, bazy nie czyściłem.

**Pliki strażników** (nie same wklejki) leżą w `audyt/boty/testy-audytu/`:
`DK-dwa-zrodla-pulapka.sh`, `DK-like-esc-like.php`, `DK-znaki-pola.sh` +
`DK-znaki-pola.php`, `DK-csv-krawedzie.php`, `DK-rownolegle-liczniki.sh`,
`DK-blokada-segregatora.php`.

---

# Wynik w jednej tabeli

| Cel | Kod | Stan |
|---|---|---|
| 1. Ile jest źródeł prawdy dla tej samej listy | **DK-1** | 🐞 znalezisko (+ 2 pary ⚠️, 2 pary ✅) |
| 2. `LIKE` ze znakami `_` i `%` | **DK-2** | ✅ czysto (5 z 5 miejsc, test skalibrowany) |
| 3. Polskie znaki, emoji, HTML, cudzysłowy w każdym polu | **DK-3** | ✅ czysto (8 pól) |
| 4. CSV na krawędziach | **DK-4** | ✅ czysto (10 przypadków) |
| 5. Dwa żądania naraz do tego samego zasobu | **DK-5**, **DK-6** | 🐞 dwa znaleziska |

---

# 🐞 DK-1 — licznik pułapki i lista podejrzanych zgłoszeń to DWA źródła prawdy o tym samym zdarzeniu (i rozjeżdżają się 14 do 5)

**Cel 1.** Pliki: `wtyczki/chatbot-live/includes/front.php:696`
(`chatl_odnotuj_pulapke()` → opcja `chatl_pulapka`) oraz kolumna
`chatl_zgloszenia.podejrzane` (`front.php`, `INSERT` zgłoszenia).
Miejsce, w którym rozjazd widzi człowiek: `includes/admin.php:147`.

To samo zdarzenie („pułapka na boty zadziałała") jest zapisywane **dwa razy,
w dwóch nośnikach**: jako licznik w `wp_options` i jako flaga w wierszu tabeli.
Pasek w panelu prezentuje je jako jedną rzecz:

> **Pułapka na boty zadziałała N raz(y).** … Złapane zgłoszenia **są zapisane** —
> zobaczysz je na liście zgłoszeń pod filtrem *podejrzane*.

Są to jednak dwa różne zbiory i rozjeżdżają się **dwiema niezależnymi drogami**:

1. **pułapka na ścieżce PYTANIA do bota** — licznik rośnie, a zgłoszenie w ogóle
   nie powstaje (nie ma czego zapisać: gość zadał pytanie, nie zostawił kontaktu);
2. **sufit `CHATL_LIMIT_PULAPKA` (5 zapisów na minutę)** — powyżej sufitu
   zgłoszenie **nie jest zapisywane**, ale licznik i tak rośnie.

Właściciel czyta „14" i idzie szukać czternastu wierszy, których nie ma.

### Odtworzenie

```
$ bash audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh
start: licznik chatl_pulapka.ile=154   wierszy podejrzanych=0

== A. 6 PYTAN do bota z wypelniona pulapka (zgloszenia nie powstaje w ogole) ==
po A: licznik +6   wierszy podejrzanych +0

== B. 8 ZGLOSZEN z wypelniona pulapka (sufit zapisu to CHATL_LIMIT_PULAPKA=5/min) ==
po B: licznik +8   wierszy podejrzanych +5

RAZEM: pasek w panelu powie o 14 zadzialaniach pulapki,
       a pod filtrem 'podejrzane' wlasciciel znajdzie 5 nowych zgloszen.
sprzatanie: skasowano 5 wierszy probnych, chatl_pulapka={"ile":154,…}
WYNIK: PADA — licznik i lista rozjezdzaja sie o 9.
EXIT=1
```

Test mierzy obie drogi rozjazdu osobno (A i B), więc naprawa jednej nie zamknie
go po cichu. Przypadek BEZ wady jest w tej samej próbie: gdyby oba źródła
opisywały to samo, obie różnice byłyby równe.

### Pozostałe pary „ta sama prawda w dwóch miejscach" — przejrzane po kolei

| Para | Ocena | Którędy się rozjeżdża |
|---|---|---|
| `chatl_pulapka` (opcja) ↔ `chatl_zgloszenia.podejrzane` (tabela) | 🐞 wyżej | dwie drogi, odtworzone |
| `segm_operacja_w_toku` (opcja) ↔ faktyczny stan operacji | 🐞 patrz **DK-6** | po wyścigu znacznik mówi, że trzyma go proces A, gdy pracuje też B |
| `chatl_wersja_schematu` / `segm_wersja_schematu` (opcja) ↔ faktyczny schemat tabel | ⚠️ anomalia | `chatl_sprawdz_schemat()` (`chatbot-live.php:117`) i `segm_sprawdz_schemat()` (`segregator-maila.php:146`) zapisują numer wersji **bez sprawdzenia**, czy `dbDelta` cokolwiek utworzyła. Gdy `CREATE TABLE` się nie uda (brak uprawnień, pełny dysk), opcja twierdzi „schemat aktualny" i ścieżka podniesienia **nigdy już nie wróci**. Nie umiałem tego odtworzyć bez psucia współdzielonej bazy — dlatego anomalia, nie znalezisko. |
| `chatl_rozmowy.trafiono` zapisywane na **dwóch wierszach** jednej wymiany (pytanie gościa + odpowiedź bota) | ⚠️ anomalia | statystyki liczą z wiersza **bota** (`statystyki.php`), raport luk z wiersza **gościa**. Gdy jeden z dwóch zapisów padnie, oba raporty policzą tę samą wymianę inaczej. Nie odtworzyłem — wymagałoby wymuszenia błędu bazy na jednym z dwóch `INSERT`-ów. |
| `chatl_rodo_sesje_wspolne` (opcja) ↔ `chatl_zgloszenia` (tabela) | ✅ czysto | opcja jest **przycinana przy każdym zapisie** do sesji, które wciąż mają wiersze rozmowy (`rodo.php`, `chatl_rodo_zapamietaj_wspolne()`), więc nie może przeżyć danych, które opisuje. To jest wzorzec, którego brakuje parze wyżej. |
| `CHATL_RETENCJA_DNI` — cron kasujący, tekst w widgecie, tekst w instrukcji | ✅ czysto | jedna stała, trzy odczyty; nie ma drugiej kopii liczby |

---

# ✅ DK-2 — `LIKE` ze znakami `_` i `%`: pięć miejsc, pięć razy `esc_like()`

**Cel 2.** Przeszukałem **cały** kod produkcyjny trzech wtyczek:

```
$ grep -rn "LIKE" --include=*.php wtyczki/
wtyczki/segregator-maila/uninstall.php:37   … LIKE %s", $segm_wzor )          (esc_like wyżej, linia 35)
wtyczki/sklep-agentow/uninstall.php:68      … LIKE %s", $wpdb->esc_like( 'sklepag_' ) . '%' )
wtyczki/sklep-agentow/includes/admin-dashboard.php:81  … email_kupujacego LIKE %s  ($args[] = '%' . $wpdb->esc_like( … ) . '%')
wtyczki/chatbot-live/uninstall.php:51       … LIKE %s OR … LIKE %s  (esc_like ×2)
wtyczki/chatbot-live/uninstall.php:57       … LIKE %s OR … LIKE %s  (esc_like ×2)
```

Sama lektura to jednak nie dowód, więc **podłożyłem wpisy, które nie należą do
wtyczki, a pasują do wzorca**, i sprawdziłem, czy znikają. Dobrałem je tak, żeby
różniły się od naszych dokładnie tam, gdzie stoi podkreślnik — czyli tam, gdzie
brak `esc_like()` zmieniłby znak literalny w wieloznacznik. Najbardziej realny
z nich to `sklepagro_DKobca`: hipotetyczna wtyczka „SklepAgro" ginęłaby przy
odinstalowaniu naszego sklepu.

```
$ docker exec strona_wp_wordpress wp eval-file /tmp/DK-like.php --allow-root
OK   wzorzec _transient_chatl_rl_%        | wlasny skasowany: tak | OBCY (_transient_chatlXrlYDKobca) przezyl: tak | bez esc_like obcy ginie: tak (test rozroznia oba stany)
OK   wzorzec _transient_chatl_import_%    | wlasny skasowany: tak | OBCY (_transient_chatlXimportYDKobca) przezyl: tak | bez esc_like obcy ginie: tak (test rozroznia oba stany)
OK   wzorzec _transient_segm_komunikat_%  | wlasny skasowany: tak | OBCY (_transient_segmXkomunikatYDKobca) przezyl: tak | bez esc_like obcy ginie: tak (test rozroznia oba stany)
OK   wzorzec sklepag_%                    | wlasny skasowany: tak | OBCY (sklepagro_DKobca) przezyl: tak | bez esc_like obcy ginie: tak (test rozroznia oba stany)

WYNIK: OK — kazdy wzorzec tyka wylacznie wlasnych wpisow.
EXIT=0
```

Kolumna „bez esc_like obcy ginie: tak" jest **kalibracją**: ten sam test na
wzorcu pozbawionym `esc_like()` zjada podłożony obcy wpis, więc test nie jest
zawsze zielony. Pokrywa też przypadek BEZ wady — wpis **własny** ma zniknąć
i znika, więc „naprawa" polegająca na wyłączeniu kasowania tego testu nie zaliczy.

---

# ✅ DK-3 — polskie znaki, emoji, znaczniki HTML i cudzysłowy: osiem pól, wszystkie czyste

**Cel 3.** Ciąg próbny (jeden dla wszystkich pól):

```
Zażółć gęślą jaźń ĄĆĘŁŃÓŚŹŻ 😀 <script>alert("XSS")</script> <b>gruby</b> "cudzysłów" & ampersand — półpauza
```

Mierzone trzy rzeczy osobno: **Z** — czy zapis wraca nieuszkodzony (polskie znaki,
emoji, cudzysłowy, `&`); **S** — czy sanityzacja usuwa znaczniki HTML;
**W** — czy render strony panelu nie zawiera surowego `<script>`.

Pola gościa idą przez **prawdziwe endpointy HTTP** (`admin-ajax.php`, z sesją
i sekretem w ciastku). Pola panelu przechodzą przez **te same sanityzatory,
co punkty `admin_post_*`**, a render robi **prawdziwa funkcja strony**
(`chatl_strona_qa()`, `chatl_strona_ustawienia()`, `segm_strona_kategorie()`,
`segm_strona_reguly()`).

```
$ bash audyt/boty/testy-audytu/DK-znaki-pola.sh
  render Q&A: znaczniki zaescape'owane: nie, emoji w HTML: tak, surowy <script>: brak
  render kategorii: surowy <script>: brak | render reguł: surowy <script>: brak

OK   ChatBot: pytanie gościa (HTTP)        Z:tak S:tak W:—
OK   ChatBot: wiadomość zgłoszenia (HTTP)  Z:tak S:tak W:—
OK   ChatBot: baza Q&A (pytanie)           Z:tak S:tak W:tak
OK   ChatBot: baza Q&A (odpowiedź)         Z:tak S:tak W:tak
OK   ChatBot: powitanie (ustawienia)       Z:tak S:tak W:tak
OK   ChatBot: tekst awaryjny               Z:tak S:tak W:tak
OK   Segregator: nazwa kategorii           Z:tak S:tak W:tak
OK   Segregator: wzorzec reguły            Z:tak S:tak W:tak

sprzatanie: qa=1 kategorie=1 + reguly, ustawienia przywrocone
WYNIK: OK — kazde pole przyjelo, oddalo i wyswietlilo ciag bezpiecznie.
EXIT=0
```

Rzeczy zmierzone po drodze, warte zapisania:

- **wszystkie tabele obu botów są `utf8mb4_unicode_520_ci`** (sprawdzone
  `information_schema`), więc emoji (4 bajty) mieści się i wraca całe;
- `sanitize_text_field`/`sanitize_textarea_field` **usuwają** `<script>` i `<b>`
  już na wejściu, a panel dodatkowo escape'uje przy wyświetlaniu — dwie warstwy,
  nie jedna;
- `segm_post_kategoria_zapisz()` przycina nazwę `mb_substr( …, 0, 100 )` do
  długości kolumny **i** sprawdza `false === $wynik`, więc zbyt długa nazwa
  kończy się uczciwym komunikatem, nie cichym zgubieniem kategorii.
  (Sprawdziłem wprost: `varchar(100)`, a `INSERT` ze 130 znakami jest odrzucany
  przez `wpdb` — panel do tego nie dopuszcza.)

**Czego to NIE zastępuje:** nie sprawdziłem, czy długi ciąg z emoji **nie psuje
UKŁADU** strony (szerokość kolumn, zawijanie, przewijanie poziome) — do tego
trzeba oka w przeglądarce, a pamięci na nią nie było. Zmierzyłem poprawność
i bezpieczeństwo wyjścia, nie jego wygląd.

---

# ✅ DK-4 — CSV na krawędziach: dziesięć przypadków, każdy z sensownym wyjściem

**Cel 4.** Parser `chatl_import_parsuj()` jest **czysty wobec wejścia** (bierze
string, nie ścieżkę), więc strażnik podaje mu treść z pamięci — nie udaje uploadu
i **nic nie zapisuje do bazy**.

```
$ docker exec strona_wp_wordpress wp eval-file /tmp/DK-csv.php --allow-root
iconv dostępny: tak

OK   UTF-8, z nagłówkiem (kontrola)     wierszy:1  | pierwsze pytanie: Czy jesteście otwarci w sobotę?
OK   Windows-1250 (polski Excel)        wierszy:1  | pierwsze pytanie: Czy jesteście otwarci w sobotę?
OK   Windows-1250 + separator średnik   wierszy:1  | pierwsze pytanie: Gdzie jesteście?
OK   bez nagłówka (same dane)           wierszy:0  | komunikat: Plik musi mieć kolumny „pytanie" i „odpowiedz". W pierwszym wierszu znalazłem: C…
OK   plik pusty (0 bajtów)              wierszy:0  | komunikat: Plik jest pusty.
OK   sam nagłówek, zero wierszy         wierszy:0  | komunikat: Plik ma nagłówek, ale ani jednego wiersza z danymi.
OK   bardzo duży (> 2 MB)               wierszy:0  | komunikat: Plik jest większy niż 2 MB. Podziel go na części.
OK   ponad 2000 wierszy                 wierszy:0  | komunikat: Plik ma więcej niż 2000 wierszy. Podziel go na części.
OK   UTF-8 z BOM (własny eksport)       wierszy:1  | pierwsze pytanie: Czy jesteście otwarci w sobotę?
OK   plik binarny (nie CSV)             wierszy:0  | komunikat: Plik musi mieć kolumny „pytanie" i „odpowiedz". W pierwszym wierszu znalazłem: ‰…

WYNIK: OK — każda krawędź kończy się albo wczytaniem, albo zrozumiałym komunikatem.
EXIT=0
```

Co widzi człowiek: przy **każdej** krawędzi dostaje zdanie po polsku mówiące,
co jest nie tak i co z tym zrobić — nie „coś poszło nie tak" i nie cichy import
zera wierszy. Plik z polskiego Excela (Windows-1250, także ze średnikiem)
wchodzi **poprawnie**, z ogonkami: konwersja idzie przez `iconv` z `CP1250`,
a nie przez `mb_convert_encoding` (mbstring nie zna CP1250 i rzuciłby błędem).
Przypadki 1, 2, 2b i BOM są jednocześnie **próbą kontrolną**: gdyby „naprawa"
zaczęła odrzucać wszystko, cztery pozycje z wierszami zapaliłyby się na czerwono.

---

# 🐞 DK-5 — licznik trzymany w `wp_options` gubi 13 z 20 równoległych zapisów; licznik w tabeli gubi 0

**Cel 5.** Pliki: `wtyczki/chatbot-live/includes/front.php:199`
(`chatl_odnotuj_odrzucony_nonce()`), `front.php:696` (`chatl_odnotuj_pulapke()`),
`wtyczki/chatbot-live/chatbot-live.php:67` (`chatl_log_bledu_bazy()`),
`wtyczki/segregator-maila/segregator-maila.php:68` (`segm_log_bledu_bazy()`).

Wszystkie cztery liczą tym samym wzorcem: `get_option()` → `+1` →
`update_option()`. To odczyt-modyfikacja-zapis **bez blokady**, więc przy
żądaniach idących naraz inkrementacje przepadają. Ta sama wtyczka ma obok
licznik zrobiony dobrze — `chatl_rate_limit` z `INSERT … ON DUPLICATE KEY
UPDATE` — i to on jest w tym teście próbą kontrolną.

```
$ bash audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh 20
== SCIEZKA BADANA: 20 zadan NARAZ ze zlym noncem (licznik w wp_options) ==
oczekiwane: 20   zapisane w chatl_blad_nonce.ile: 7

== PROBA KONTROLNA: 20 zadan NARAZ do licznika w TABELI (chatl_rate_limit) ==
oczekiwane: 20   zapisane w chatl_rate_limit.licznik: 20
sprzatanie: chatl_blad_nonce=false wiersz probny usuniety
WYNIK: PADA — opcja zgubila 13 z 20 inkrementacji; tabela zgubila 0.
EXIT=1
```

**65% zapisów przepadło.** Skutek nie jest kosmetyczny: `chatl_blad_nonce`
i `chatl_pulapka` to liczby, na podstawie których właściciel decyduje, czy ma
problem z cache albo z botami — a te liczby są tym mocniej zaniżone, im większy
ruch, czyli **dokładnie wtedy, gdy problem jest prawdziwy**. `chatl_blad_bazy`
i `segm_blad_bazy` liczą awarie zapisu; przy awarii lecącej w pętli (import,
cron) właściciel zobaczy ułamek.

To jest wprost klasa nr 7 z `CLAUDE.md` („licznik/stan w `wp_options` zamiast
własnej tabeli") — z tą różnicą, że tu **rozwiązanie już istnieje w tej samej
wtyczce**, kilkadziesiąt linii wyżej.

---

# 🐞 DK-6 — zamek Segregatora wpuszcza dwie operacje naraz, a znacznik drugiej nadpisuje pierwszą

**Cel 5.** Plik: `wtyczki/segregator-maila/segregator-maila.php:284`
(`segm_zajmij_blokade()`). Zamek strzeże **sześciu** operacji: przebiegu
segregacji (`silnik.php:101`), importu próbki z CSV (`csv.php:246`),
wczytywania konfiguracji (`konfiguracja.php:228`), dodawania i usuwania maila
z próbki (`admin-probka.php:270, 338`) oraz czyszczenia retencyjnego
(`segregator-maila.php:175`).

Zamek stoi na **dwóch krokach**: `get_option()` (sprawdź, czy wolne) i
`add_option()` (zajmij). Między nimi jest okno. Test odtwarza dokładnie ten
układ, w którym dwa procesy startują razem — A sprawdza, B zajmuje, A kończy
swoje zajmowanie:

```
$ docker exec strona_wp_wordpress wp eval-file /tmp/DK-blokada.php --allow-root
kontrola (po kolei):  pierwszy=true  drugi='kontrola: pierwszy'
wyscig:  A widzial przed startem: false | B trzyma znacznik w bazie: tak | A dostal: true
znacznik po wszystkim nalezy do: proces A: przebieg segregacji
sprzatanie: znacznik = false

WYNIK: PADA — B trzymal znacznik, a A tez dostal 'true'. Dwie operacje ida naraz.
EXIT=1
```

Pierwsza linia to **próba kontrolna**: gdy dwa zajęcia idą **po kolei**, zamek
działa poprawnie — drugi dostaje opis pierwszego i się wycofuje. Test rozróżnia
więc oba stany i nie jest zawsze czerwony.

Dwie konsekwencje, nie jedna:

1. **dwie operacje idą naraz** — dwa przebiegi segregacji na tej samej skrzynce
   IMAP, dwa importy tej samej próbki;
2. **znacznik kłamie o tym, kto go trzyma** — ostatnia linia pokazuje, że po
   wyścigu znacznik należy do **A**, choć zajął go **B**. Gdy A skończy pierwszy
   i wywoła `segm_zwolnij_blokade()` (czyli `delete_option`), **zwolni zamek
   trzymany przez wciąż pracujące B** i wpuści trzecią operację. To jest ta sama
   para „opcja ↔ rzeczywistość" co w celu 1, tylko widziana od strony wyścigu.

Przyczyna jest w `add_option()`: sprawdza istnienie opcji przez `notoptions`
w pamięci procesu, a potem robi `INSERT … ON DUPLICATE KEY UPDATE`, który
**nadpisuje cudzy wiersz i zwraca sukces**. Nie jest to prymityw atomowy
i nie da się na nim postawić zamka.

---

# ⚠️ ANOMALIE (droga rozjazdu wskazana, nieodtworzona — nie podaję jako znalezisk)

- **`chatl_wersja_schematu` / `segm_wersja_schematu` zapisywane bez sprawdzenia,
  czy schemat naprawdę powstał** (`chatbot-live.php:117`,
  `segregator-maila.php:146`). Odtworzenie wymagałoby odebrania bazie prawa
  `CREATE` albo zapełnienia dysku na współdzielonym stosie — nie robiłem.
- **`chatl_rozmowy.trafiono` zapisywane na dwóch wierszach jednej wymiany.**
  Rozjazd wymaga wymuszenia błędu bazy na jednym z dwóch `INSERT`-ów; nie
  znalazłem drogi, którą da się to zrobić bez psucia bazy innym sesjom.

---

# CZEGO NIE BYŁO NA MOJEJ LIŚCIE

1. **Wyścig przy `update_option` na ustawieniach, nie na licznikach.** DK-5 mierzy
   liczniki, ale ten sam wzorzec dotyczy `chatl_ustawienia` i `segm_zgoda_ai`:
   dwóch administratorów zapisujących ustawienia w tej samej chwili nadpisze się
   nawzajem **w całości**, bo opcja jest jedną tablicą. Nikt tego nie mierzył.
2. **Równoległość na poziomie wiersza, nie opcji.** Sprawdziłem liczniki i zamek.
   Nie sprawdziłem, co się dzieje, gdy operator i gość piszą do tej samej rozmowy
   w tej samej chwili, ani gdy dwa przebiegi Segregatora ruszą na tę samą skrzynkę
   IMAP (co DK-6 właśnie umożliwia — skutek po stronie **serwera poczty** jest
   osobną soczewką).
3. **Fail-open jako wzorzec, nie wypadek.** `chatl_rate_limit()` przy awarii
   licznika **przepuszcza**, a `segm_zajmij_blokade()` po wygaśnięciu znacznika
   **kasuje go i wpuszcza**. Obie decyzje są opisane w kodzie, żadna nie jest
   zmierzona: ile trwa operacja, która przekroczy `SEGM_BLOKADA_WAZNA`, i co się
   dzieje, gdy wtedy wejdzie druga.
4. **`add_option()` używane jako prymityw atomowy gdzie indziej.** Sprawdziłem
   jedno miejsce (zamek Segregatora). Nie przeszukałem sklepu ani ChatBota pod
   kątem tego samego założenia.
5. **CSV Segregatora.** Cel 4 zrobiłem na imporcie ChatBota. `segregator-maila/includes/csv.php`
   ma własny import próbki maili (za tym samym zamkiem z DK-6) i **nie był
   przepuszczony przez te same krawędzie** — Windows-1250, brak nagłówka, plik pusty.
6. **Układ strony przy długich treściach.** DK-3 dowodzi poprawności i bezpieczeństwa
   wyjścia, ale nie tego, że emoji i 2000-znakowa wiadomość nie rozjeżdżają tabeli
   w panelu. To wymaga przeglądarki.
7. **Zachowanie liczników po stronie wielu witryn (multisite).** Wszystkie liczniki
   siedzą w `wp_options` bieżącej witryny; nie sprawdzałem, czy wtyczki są w ogóle
   deklarowane jako zgodne z multisite i co robi `$wpdb->prefix` w takim układzie.
8. **Retencja a rozjazd źródeł.** `chatl_rodo_sesje_wspolne` sam się przycina do
   żywych rozmów — to dobry wzorzec. Nie sprawdziłem, czy tak samo zachowują się
   inne dane przeżywające retencję (np. `chatl_sesje` względem `chatl_rozmowy`).

---

# CZEGO NIE ZDĄŻYŁEM

- Nie przeszedłem CSV Segregatora przez krawędzie z celu 4 (punkt 5 wyżej).
- Nie zrobiłem żadnego kroku w przeglądarce — pamięci nie było, więc układ strony
  przy treściach z krawędzi (cel 3) pozostaje niezmierzony.
- Dwie anomalie wyżej zostały bez odtworzenia.
- Przy celu 5 mierzyłem 20 żądań naraz; nie sprawdzałem, czy przy większej
  równoległości gubi się proporcjonalnie więcej (skala straty).

Budżet: 50 kroków narzędziowych, zużyte ~47.
