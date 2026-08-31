# RAPORT — soczewka Ł (ŁAMACZ ZAŁOŻEŃ) · Segregator maila

- **commit_head:** `3fc884d` (`git checkout -B audyt/lamacz-segregator origin/main` → `git rev-parse --short HEAD` = `3fc884d`)
- **Teren:** `wtyczki/segregator-maila/` (produkt 199 zł)
- **Pytanie soczewki:** jakie założenie o świecie robi ten produkt, którego świat u klienta nie spełni
- **Data:** 2026-08-21 · **Wynik:** 2 znaleziska, 4 cele czyste, 0 anomalii

---

## Co ten produkt ma robić — własnymi słowami, z materiału

Segregator maila to wtyczka WordPressa sprzedawana jako gotowy produkt: właściciel małej
firmy układa w panelu listę reguł („nadawca zawiera / temat zawiera / słowa w treści →
kategoria"), gdzie rozstrzyga **kolejność na liście — pierwsza pasująca reguła wygrywa**
i żaden drugi mechanizm priorytetu nie istnieje. Reguły sprawdza najpierw na próbce
(wbudowane ~30 maili PL albo własny CSV) i dostaje raport: ile maili do której kategorii,
ile bez dopasowania, % pokrycia, a przy próbce z kolumną `kategoria_oczekiwana` także
accuracy i listę pomyłek. Opcjonalnie, na własny klucz API, tę samą klasyfikację robi
model — na próbce albo w trybie „porównaj reguły vs AI"; bez klucza tryb regułowy jest
pełnoprawny, nie atrapa. **Ponad literę zamówienia** (§ `boty/PONAD-SPECYFIKACJE-BOTY.md`
pkt 7, zgoda zamawiającego 20.08) doszło podpięcie **prawdziwej skrzynki po IMAP**:
własny klient IMAP4rev1 w czystym PHP, przebieg domyślnie **na sucho** (EXAMINE, read-only
na poziomie protokołu), realne przenoszenie dopiero po przełączeniu trybu **i** osobnym
jednorazowym potwierdzeniu, historia przebiegów, **cofnięcie ostatniego przebiegu** oraz
zadanie cykliczne. Materiał był wystarczający, żeby to napisać — ⚠️ z jednym zastrzeżeniem
opisanym niżej w „wada materiału".

---

## Warunki pomiaru (G6 — sprawdzenie własnego przyrządu)

Stos: `strona_wp_wordpress` (WP 7.0.4 / PHP 8.4), serwer IMAP `segm_imap` (GreenMail, port 3143),
SMTP 3025. Wtyczka na stosie: **1.3.1** — ta sama, którą audytuję.

⚠️ **Kod na stosie nie jest bit w bit audytowanym `3fc884d`** — sprawdziłem to, zanim
cokolwiek zmierzyłem:

```
$ diff -rq wtyczki/segregator-maila <kopia ze stosu>
Files .../includes/admin.php ... differ
Files .../segregator-maila.php ... differ
Files .../uninstall.php ... differ
```

Różnica to dokładnie **PR #136 `boty/znacznik-bledu-bazy`** (stała `SEGM_BLAD_BAZY_OKNO_DNI`,
pasek `segm_pasek_bledu_bazy()`, `delete_option( 'segm_blad_bazy' )` w uninstallu) — czyli
stos jest **nadzbiorem** audytowanego kodu o zmianę będącą w locie. **Wszystkie pliki, na
których stoją moje znaleziska** (`skrzynka-przebieg.php`, `skrzynka.php`, `class-segm-imap.php`,
`ai.php`) **są identyczne** z `3fc884d`. Każdy pomiar dotykający tej różnicy opisuję wprost.

Zakazy z `02-fakty-srodowiska.md` respektowane: nie kasowałem bazy ani tabel, nie użyłem
`pkill -f`, nie zmieniłem kodu produktu. Skrypty prób oddają wspólnemu stosowi stan sprzed
próby (ustawienia skrzynki, hasło, zgoda na przenoszenie, klucz API) przez
`register_shutdown_function` — także gdy próba padnie w połowie.

---

# ZNALEZISKA

## 🐞 Ł-A. Cofnięcie przebiegu melduje sukces dla wiadomości, których nie ruszyło — i zamyka drogę odwrotu

**Założenie:** *„UID, który zapisaliśmy przy przenoszeniu, wciąż wskazuje tę wiadomość
w folderze docelowym — a jak serwer odpowie OK na `UID MOVE`, to znaczy, że przeniósł."*

**Kiedy u klienta jest fałszywe:** zawsze, gdy między przebiegiem a kliknięciem „cofnij"
ktoś ruszy pocztę **poza wtyczką** — czyli w normalnym używaniu skrzynki. Właściciel widzi
w Thunderbirdzie/telefonie maila, który wylądował w złej kategorii, przeciąga go gdzie
indziej, a *potem* stwierdza „przebieg mi namieszał, cofnę całość". To samo dotyczy
skasowania folderu kategorii i założenia go na nowo.

**Mechanizm:** `Segm_Imap::przenies()` (`includes/class-segm-imap.php:~700`) uznaje ruch
za udany na podstawie **samego statusu odpowiedzi** (`'OK' !== $odp['status']`). Tymczasem
RFC 3501 §6.4.8 mówi, że polecenie `UID` niepasujące do żadnej wiadomości **kończy się OK** —
serwer nie ma nic do zgłoszenia. Nikt nie sprawdza, czy cokolwiek naprawdę drgnęło (brak
`COPYUID`, brak porównania `EXISTS`). `segm_skrzynka_cofnij()` liczy więc `++$cofnietych`
za ruch, który się nie odbył. To ta sama klasa co **G2** („zapis bez sprawdzenia wyniku =
fałszywy sukces"), tyle że na IMAP-ie zamiast na bazie, i **G1** (nie da się odróżnić
sukcesu od porażki).

**Wykonanie** (skrypt `audyt/boty/repro/lamacz-cofanie.php`, ten sam kod, dwa przebiegi):

```
############ PROBA KONTROLNA — nikt nie rusza maila (tak ma dzialac) ############
przebieg id=155 przeniesionych=1 blad=
historia: uid_docelowy=2 folder_do=LamTest.LAM-Kat
folder docelowy: UID2:LAMA lamznw46rmf
COFNIECIE: ok=true cofnietych=1 bledow=0 blad=''
historia po cofnieciu: stan=cofniety cofniety=1 | Wiadomość wróciła do folderu „LAM-IN".
>>> FOLDER ZRODLOWY: UID3:LAMA lamznw46rmf          <-- wrocila, zgadza sie
>>> FOLDER LAM-gdzieindziej:

############ PROBA LAMIACA — wlasciciel przeciagnal maila recznie ############
przebieg id=156 przeniesionych=2 blad=
folder docelowy: UID3:LAMA lamznw46rmf | UID4:LAMB lamznw46rmf
-> CZLOWIEK przeciagnal maila do LAM-gdzieindziej. Folder docelowy teraz: UID3:LAMA lamznw46rmf
COFNIECIE: ok=true cofnietych=2 bledow=0 blad=''
historia po cofnieciu: stan=cofniety cofniety=1 | Wiadomość wróciła do folderu „LAM-IN".
>>> FOLDER ZRODLOWY: UID5:LAMA lamznw46rmf          <-- wrocil TYLKO jeden
>>> FOLDER LAM-gdzieindziej: UID1:LAMB lamznw46rmf  <-- LAMB nadal tam, gdzie ja zostawil
```

**Kalibracja:** próba kontrolna (nikt nic nie rusza) przechodzi poprawnie — wiadomość
naprawdę wraca. Ten sam kod przy tej samej ścieżce melduje `cofnietych=2`, a wróciła jedna.
Przyrząd mierzy to, co trzeba.

**Wariant ostrzejszy** (pierwszy przebieg, folder kategorii skasowany przed cofaniem):
`COFNIECIE: ok=true cofnietych=1 bledow=0`, a folder źródłowy **pusty** — zero wiadomości
wróciło, komunikat historii nadal brzmiał „Wiadomość wróciła do folderu «LAM-IN»".

**Szkoda dla kupującego — nie tylko brzydki komunikat:**
1. Właściciel dostaje „cofnięto 2" i **przestaje szukać**. Poczta zostaje rozrzucona.
2. Przebieg zostaje oznaczony `cofniety=1` **mimo `bledow=0` policzonych z powietrza**.
   Drugie podejście jest już **niemożliwe**: `segm_skrzynka_cofnij()` odbija je komunikatem
   „Ten przebieg został już cofnięty". Wtyczka sama zamyka jedyną drogę odwrotu.
3. Strona produktu obiecuje wprost: *„Ostatni przebieg da się **cofnąć jednym kliknięciem**"*
   i w FAQ *„Czy coś mi się pomiesza w poczcie? (Domyślny tryb na sucho niczego nie rusza,
   **a ostatni przebieg da się cofnąć**.)"* — to jest obietnica sprzedażowa bez pełnego
   pokrycia w kodzie, przy cenie 199 zł.

**Czego to NIE jest:** to nie jest utrata maili. Wiadomość leży tam, gdzie zostawił ją
człowiek — tracona jest **wiedza, gdzie ona jest**, i możliwość naprawy jednym kliknięciem.

---

## 🐞 Ł-B. Baza przestaje przyjmować historię w połowie przebiegu na żywo → poczta przeniesiona, cofanie martwe, panel pokazuje sukces

**Założenie:** *„Baza dotrwa do końca pętli — a jak nie dotrwa, to i tak najgorsze, co się
stanie, to brak raportu."*

**Kiedy u klienta jest fałszywe:** dysk się kończy, `max_connections` na współdzielonym
hostingu, restart MySQL, `MAX_ALLOWED_PACKET`, awaria w trakcie kilkuminutowego przebiegu.
Kolejność operacji jest tu krytyczna: wtyczka **najpierw przenosi pocztę na serwerze**
(nieodwracalnie, wiadomość po wiadomości), a wiersze historii dopisuje **dopiero po pętli**
(`skrzynka-przebieg.php:325-338`). Historia jest jedynym źródłem, z którego da się cofnąć.

**Wykonanie:** awarię wywołuję filtrem `query` WordPressa, który podmienia `INSERT`-y do
`segm_skrz_ruchy` na zapis do nieistniejącej tabeli — czyli baza odmawia dokładnie w tym
oknie, w którym poczta jest już ruszona. ⛔ Bez kasowania czegokolwiek na poligonie.

```
w zrodle przed przebiegiem: UID6:LAM3 lam3qxeoeu
Warning: segm_skrzynka_przebieg(): zapis do bazy nie powiódł się (zapis pozycji historii (UID 6)):
         Table 'wordpress.lamacz_tabela_ktorej_nie_ma' doesn't exist
CO ZWROCIL PRZEBIEG (to widzi wlasciciel w panelu):
   przeniesionych=1  bledow=0  blad=""  komunikat=""
BAZA: wierszy historii ruchow=0  a przebieg deklaruje przeniesionych=1
SERWER POCZTY: zrodlo=   docelowy=UID1:LAM3 lam3qxeoeu
PROBA COFNIECIA: ok=false cofnietych=0 blad="W tym przebiegu nie ma ani jednej przeniesionej wiadomości."
```

**Co tu jest złego, punkt po punkcie:**
1. Poczta **naprawdę została przeniesiona** (folder źródłowy pusty, docelowy ma wiadomość).
2. Przebieg raportuje `przeniesionych=1`, `bledow=0`, `blad=""` — **czysty sukces**, mimo że
   historia tego przebiegu jest pusta. Licznik `bledow` nie mierzy wymiaru, który robi szkodę (**G7**).
3. Wiersz przebiegu w bazie **twierdzi `przeniesionych=1`**, a wierszy ruchów jest **0** —
   baza sama sobie przeczy i nikt tego nie sprawdza.
4. Cofanie jest **martwe** i na dodatek **wprowadza w błąd**: mówi „nie ma ani jednej
   przeniesionej wiadomości", gdy jedna została przeniesiona. Właściciel dostaje komunikat,
   który go **odwodzi** od szukania.

**Ocena naprawy w locie (obowiązek z zasad):** PR **#136 `boty/znacznik-bledu-bazy`** dotyka
tego miejsca i **naprawia jeden z czterech punktów** — dokłada pasek w panelu, więc właściciel
przy `WP_DEBUG=false` w ogóle **dowie się**, że jakiś zapis padł (widać to w moim wyjściu:
`Warning: ... zapis do bazy nie powiódł się` pochodzi właśnie z `segm_log_bledu_bazy()` w wersji
ze stosu). **Naprawa jest niepełna względem tego znaleziska** i tak ją oceniam: po #136 raport
przebiegu **nadal** pokazuje `przeniesionych=1 bledow=0 blad=""`, wiersz przebiegu **nadal**
kłamie względem zera wierszy ruchów, a cofanie **nadal** jest niemożliwe i **nadal** odpowiada
komunikatem zaprzeczającym faktowi przeniesienia. ⛔ Nie zgłaszam tego jako wady #136 —
to jest poza jego zakresem; zgłaszam jako lukę, której nikt nie zamyka.

**Szkoda dla kupującego:** poczta rozłożona po folderach bez żadnego zapisu, co gdzie poszło,
i bez drogi powrotnej — przy komunikacie „sukces". To najcięższy wariant: nie „brzydki
komunikat", tylko **utrata możliwości odtworzenia stanu skrzynki**.

---

# CELE Z MAPY POLOWANIA (sekcja Ł) — wszystkie domknięte

| # | Cel | Wynik |
|---|-----|-------|
| Ł1 | „Co, jeśli baza nie odpowie w połowie przebiegu" | 🐞 **znalezisko Ł-B** |
| Ł2 | „Co, jeśli klucz API jest zły / wygasł / ma zerowy limit" | ✅ czysto |
| Ł3 | „Co, jeśli przyjdzie 500 żądań w minutę" | ✅ czysto |
| Ł4 | „Co, jeśli WordPress jest w wersji minimalnej z nagłówka wtyczki" | ✅ czysto |
| Ł5 | „Co, jeśli właściciel wyłączy zadania cykliczne (`DISABLE_WP_CRON`)" | ✅ czysto |

Poza mapą, z własnej soczewki: **Ł-A** (obietnica sprzedażowa „cofnij jednym kliknięciem").

### Ł2 — ✅ czysto (wykonane)
Klucz składniowo poprawny, konto nieistniejące. Klucz wspólnego stosu zapisany i oddany.
```
segm_ai_przetestuj_klucz() -> {"ok":false,"komunikat":"Klucz NIE zadziałał. KLUCZ API ZOSTAŁ
ODRZUCONY. Sprawdź, czy jest wpisany w całości, czy nie wygasł i czy należy do właściwego
konta. Odpowiedź dostawcy: HTTP 401: {...\"message\":\"API key is invalid.\"...}"}
slad bledu dla wlasciciela (opcja segm_blad_ai) -> {"kiedy":"2026-08-21 19:05:07","kod":401,
   "komunikat":"HTTP 401: ...","model":"claude-haiku-4-5"}
--- PROBA KONTROLNA: PUSTY KLUCZ (tryb regulowy, bez wady) ---
segm_ai_dostepny() bez klucza -> false
```
Produkt **nazywa przyczynę po ludzku**, nie kodem, i zostawia właścicielowi ślad w opcji
`segm_blad_ai` (kod + czas + model), czyli dokładnie obronę przed **G1**. Próba kontrolna
z pustym kluczem pokazuje zdrowy tryb regułowy.
⚠️ **Zakres wykonania:** wykonałem **401**. Ścieżkę **429 / zerowy limit** tylko przeczytałem
(`ai.php:258-261` — mapa kodów na komunikaty PL, `ai.php:419` — retry przy 429/5xx/błędzie
połączenia). Nie symulowałem odpowiedzi 429 od dostawcy — to zostaje **niedokończone**.

### Ł3 — ✅ czysto (wykonane)
Segregator **nie ma ani jednego punktu wejścia bez logowania** — zero `register_rest_route`,
`wp_ajax_nopriv_*`, `admin_post_nopriv_*`. Ruch 500 żądań/minutę nie ma tu czego uderzyć:
wszystko siedzi za panelem i uprawnieniami, a równoległe uruchomienia (cron + „Uruchom teraz")
zbiera blokada `segm_zajmij_blokade()`.
```
$ grep -rn "register_rest_route|wp_ajax_nopriv|admin_post_nopriv" wtyczki/segregator-maila/
ZERO TRAFIEN
```
**Próba kontrolna (G5 — zero trafień wygląda tak samo, gdy źle szukasz):** ten sam wzorzec
na ChatBocie, gdzie takie punkty NA PEWNO są:
```
wtyczki/chatbot-live/includes/przejecie.php:502:  register_rest_route(
wtyczki/chatbot-live/includes/przejecie.php:550:  add_action( 'wp_ajax_nopriv_chatl_sesja', ... );
wtyczki/chatbot-live/includes/front.php:257:      add_action( 'wp_ajax_nopriv_chatl_nonce', ... );
wtyczki/chatbot-live/includes/front.php:503:      add_action( 'wp_ajax_nopriv_chatl_pytanie', ... );
```
Grep łapie — zero w Segregatorze jest prawdziwym zerem.

### Ł4 — ✅ czysto
Nagłówek deklaruje `Requires at least: 6.0` i `Requires PHP: 8.1`. Trop był realny:
wtyczka używa `wp_trigger_error()`, które weszło dopiero w **WordPressie 6.4** — na 6.0-6.3
byłby to błąd krytyczny. **Ale każde wywołanie jest osłonięte**, w obu plikach:
```php
// segregator-maila.php:45  oraz  includes/ai.php:370
if ( ! function_exists( 'wp_trigger_error' ) ) { return; }
```
z komentarzem, który wprost nazywa problem: *„Na starszym WP (deklarowane minimum 6.0)
log po prostu nie powstaje; komunikat błędu dla użytkownika działa niezależnie od wersji"*.
Funkcji PHP nowszych niż 8.1 (`json_validate`, `mb_str_pad`, `array_find`, `#[\Override]`,
`Randomizer`, `mb_ucfirst`) — zero trafień.
⚠️ **Zakres:** to jest kontrola statyczna. **Nie postawiłem instancji WP 6.0** — na to nie
było ani budżetu, ani RAM-u (`free -m`: 1466 MB dostępne, a zasady zabraniają drugiej
przeglądarki/stosu). Zostaje **niedokończone**.

### Ł5 — ✅ czysto (wykonane, rozwinięcie znanej)
Znane jest, że `DISABLE_WP_CRON` unieważnia retencję. Sprawdzałem **drugie dno**: czy panel
skrzynki nie **uspokaja** właściciela, że segregacja cykliczna działa. Uruchomiłem ten sam
kod dwa razy, drugi raz ze stałą zdefiniowaną **przed** wczytaniem WordPressa
(`wp --exec="define('DISABLE_WP_CRON', true);" eval-file`):
```
=== A: cron WLACZONY ===   DISABLE_WP_CRON: NIEZDEFINIOWANA
PANEL SKRZYNKI -> Ustawiono „co godzinę". Najbliższy przebieg: 21.08.2026 20:04.
=== B: DISABLE_WP_CRON=true ===   DISABLE_WP_CRON: true
PANEL SKRZYNKI -> Ustawiono „co godzinę". Najbliższy przebieg: 21.08.2026 20:04.
```
Zdanie o harmonogramie jest **identyczne** — `segm_skrzynka_opis_harmonogramu()`
(`skrzynka-przebieg.php:104`) nie zna tej stałej, bo `wp_next_scheduled()` zwraca termin
niezależnie od tego, czy cokolwiek go odpali. **To jednak NIE jest znalezisko**, bo uczciwy
komunikat stoi na **tej samej stronie**, wyżej: `segm_skrzynka_diagnostyka()`
(`skrzynka.php:216-224`) sprawdza stałą i renderuje się w `admin-skrzynka.php:106`, podczas
gdy zdanie o harmonogramie jest w linii 272 tego samego widoku:
> *„Wyłączone stałą DISABLE_WP_CRON w wp-config.php. Segregacja cykliczna ruszy tylko wtedy,
> gdy Twój hosting woła wp-cron.php własnym harmonogramem (cron systemowy). Przycisk
> «Uruchom teraz» działa niezależnie."*

Właściciel widzi prawdę bez przechodzenia gdzie indziej. Do rozważenia dla buildera (**nie
zgłaszam jako wady**): dwa zdania na jednym ekranie mówią różnym tonem o tej samej sprawie.

---

# ⚠️ WADA MATERIAŁU, NIE PRODUKTU

`audyt/boty/material/02-fakty-srodowiska.md`, sekcja „TEGO NIE ZGŁASZAJ", punkt 1:
> *„**Brak podpięcia prawdziwej skrzynki (IMAP/OAuth) w Segregatorze** — świadoma decyzja
> zamawiającego (D19). Bot działa na próbkach CSV. To jest w zamówieniu."*

Na `3fc884d` to jest **nieprawda**: wtyczka ma pełny klient IMAP4rev1, przenosi pocztę na
serwerze, ma cron, historię i cofanie — czyli całą powierzchnię, na której stoją oba moje
znaleziska. Materiał kieruje audytora **dokładnie od najgroźniejszego terenu**. Zgodność
sprawdziłem tam, gdzie każe zasada — w `01-zamowienie.md` (który też mówi „Bez podpięcia
prawdziwej skrzynki (IMAP/OAuth)" jako GRANICĘ) — i dalej w `boty/PONAD-SPECYFIKACJE-BOTY.md`:
```
80: 7. ⭐ **Przebieg na PRAWDZIWEJ skrzynce (IMAP)** — własny klient IMAP4rev1 w czystym PHP
85:    (właściciel 20.08) bot bez dostępu do skrzynki jest analizatorem plików, a nie...
103: - Segregator: ⛔ **nieaktualne od 2026-08-20** — podpięcie żywej skrzynki (IMAP)
```
**Rozszerzenie jest jawnie zadeklarowane i zaakceptowane przez zamawiającego, więc G8 jest
spełnione i to NIE jest znalezisko w produkcie.** Nieaktualny jest punkt 1 materiału
audytowego. Zgłaszam, bo następny audytor z tą samą kartką ominie ten teren.

---

# CZEGO NIE BYŁO NA MOJEJ LIŚCIE

Rzeczy, na które trafiłem, a które nie mieszczą się w pytaniu soczewki Ł. **Żadnej nie
sprawdziłem do końca** — to są tropy dla następnych soczewek, nie znaleziska.

1. **Limit czasu przebiegu nie ma pokrycia w arytmetyce.** `SEGM_SKRZ_LIMIT_CZASU = 20` s
   (`skrzynka.php:30`) sprawdza się **między** wiadomościami, ale gniazdo IMAP ma własny
   limit **15 s** (`class-segm-imap.php:108`), a na jedną wiadomość przypada do czterech
   operacji sieciowych (FETCH, CREATE, ponowny SELECT, MOVE). Przy skrzynce, która zwalnia,
   pojedyncza iteracja może dołożyć kilkadziesiąt sekund **po** przejściu kontroli limitu.
   Komentarz w kodzie deklaruje: *„To celowy limit, żeby hosting nie urwał żądania w połowie
   przenoszenia"*. Jeśli hosting tnie na 30 s, ta deklaracja jest do zmierzenia — **nie
   zmierzyłem jej**, bo wymaga serwera IMAP, który celowo odpowiada wolno. Gdyby się
   potwierdziła, konsekwencja jest identyczna z Ł-B (poczta ruszona, historia nigdy niezapisana).
2. **UIDVALIDITY jest odczytywane i wyrzucane.** `otworz()` parsuje `[UIDVALIDITY n]`
   (`class-segm-imap.php:511`) i zwraca je, `copyuid()` zna je z `COPYUID` (komentarz w linii
   841), a **żadna tabela ani ścieżka go nie zapisuje** (`grep -rn UIDVALIDITY` → wyłącznie
   te miejsca odczytu). RFC 3501 §2.3.1.1 wymaga porównania go przed użyciem zapamiętanego
   UID-a. To jest **przyczyna źródłowa wariantu ostrzejszego z Ł-A** i osobny teren dla
   soczewki od danych.
3. **`prefiks` i `folder_zrodlowy` w poleceniach IMAP.** Cytowanie osłania `\` i `"`
   (`class-segm-imap.php:890`), a `do_utf7()` koduje znaki spoza ASCII — ale **nie
   prześledziłem sanityzacji tych dwóch pól od formularza do gniazda** ani zachowania
   `mb_convert_encoding()` w PHP 8.4, gdy `UTF7-IMAP` byłoby niedostępne (wtyczka **wykrywa**
   ten brak w diagnostyce, `skrzynka.php:195`, ale `do_utf7()` woła konwersję bezwarunkowo).
   Teren dla soczewki bezpieczeństwa.
4. **Tryb `UID COPY + \Deleted` bez rozszerzenia UIDPLUS.** Gdy serwer nie ma ani `MOVE`,
   ani `UIDPLUS`, oryginał **zostaje** w skrzynce oznaczony do usunięcia i wtyczka uczciwie
   to mówi. Ale wtedy w folderze źródłowym leży duplikat, a **następny przebieg zobaczy go
   ponownie** — nie sprawdziłem, czy nie powstaje pętla przenoszenia tej samej wiadomości.
   GreenMail ma `MOVE`, więc ta ścieżka **w ogóle się u mnie nie wykonała**.
5. **Kolejność `array_slice( $uidy, -$limit )` a przebieg przerwany.** Przebieg bierze
   `$limit` **najnowszych** wiadomości. Gdy reguła nie pasuje i `ruszaj_reszte` jest wyłączone,
   te same maile zostają na miejscu i są liczone przy każdym przebiegu — nie sprawdziłem,
   czy przy pełnym oknie starsza poczta kiedykolwiek doczeka się swojej kolejki.
6. **Retencja a dane osób trzecich (RODO).** `SEGM_RETENCJA_DNI = 90` dotyczy próbek
   i raportów, ale tabela `segm_skrz_ruchy` trzyma **temat, nadawcę i Message-ID cudzej
   korespondencji**. Czy obejmuje ją ta sama retencja i czy uninstall ją sprząta — **nie
   sprawdziłem**; to teren soczewki A3, częściowo dotknięty przez PR #135.
7. **Obietnica „Eksport reguł do filtrów Gmaila i Outlooka".** Strona produktu obiecuje, że
   eksport **dokłada wykluczenia**, bo nasz silnik bierze pierwszą pasującą regułę, a Gmail
   stosuje wszystkie naraz. `includes/filtry.php` (588 linii) **w ogóle nie był przeze mnie
   otwarty**. To najcięższa nietknięta obietnica sprzedażowa, jaka została na tym terenie.

---

# CZEGO NIE ZDĄŻYŁEM

- **429 / zerowy limit dostawcy AI** — przeczytane, nie wykonane (Ł2).
- **Instancja WP 6.0** — kontrola tylko statyczna, brak RAM-u na drugi stos (Ł4).
- **Wolna/rwąca się skrzynka i limit czasu PHP** — punkt 1 listy powyżej, wymaga serwera
  IMAP odpowiadającego z opóźnieniem; nie zbudowałem go.
- **`filtry.php` (eksport do Gmaila/Outlooka)** — nieotwarty.
- Budżet: 55 kroków narzędziowych, wykorzystane praktycznie w całości.

---

# MASZYNA (G9)

⛔ Zgodnie z Z11 audyt **nie naprawia** i nie dokłada strażnika do zestawu bramek — to należy
do buildera. Zostawiam **odtwarzalne próby**, obie z kalibracją:
- `audyt/boty/repro/lamacz-cofanie.php` — Ł-A, próba kontrolna + próba łamiąca w jednym przebiegu.
- `audyt/boty/repro/lamacz-baza-i-srodowisko.php` — Ł-B, Ł-2, Ł-5.

Uruchomienie (obie oddają wspólnemu stosowi stan sprzed próby):
```bash
docker cp audyt/boty/repro/<plik>.php strona_wp_wordpress:/tmp/x.php
docker exec -u www-data strona_wp_wordpress wp --path=/var/www/html eval-file /tmp/x.php
```
