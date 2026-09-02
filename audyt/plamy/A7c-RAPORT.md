# A7c — MIME i dekodowanie treści maila

> ⚠️ **Kotwica cytatów (dopisane 2.09 przy scalaniu z `main`):** numery `plik:linia` w tym raporcie pochodzą ze stanu kodu na commicie
> `b39f576` (1.09, gałąź `audyt/a7-kod-produktu`). Po scaleniach #259, #263 i #264 część numerów na `main` jest przesunięta — sonda 2.09
> (69 cytatów jednoznacznych, 21 z nazwą pliku występującą w kilku wtyczkach) potwierdziła przesunięcia w `zakup.php` (+~90 linii) i `aktywacja.php` (+11),
> `stripe.php` bez zmian; te trzy poprawione na HEAD, reszty NIE przeliczano. Pewny odczyt: `git show b39f576:<ścieżka>` (klasa 97 ze ściągi).


**Kto:** audytor A7, sesja 2026-09-01, trzecia pozycja. **Gałąź:** `audyt/a7-kod-produktu`.
**Zlecenie koordynatorki:** drugie miejsce, w którym do produktu wchodzi tekst obcego człowieka —
tą samą metodą co `A7b`: **atrapa i uruchomienie, nie czytanie.**
**Kodu produktu nie tknąłem. Sondy `A7b` nie przerabiałem.**
**Wynik: jedno `DUZE` o czterech drogach, cztery tereny czysto, jeden fałszywy alarm mojego
przyrządu — złapany i opisany.**

Przyrząd: `audyt/plamy/A7c-sonda-mime.php`, powtarzalny jedną komendą:
```
php audyt/plamy/A7c-sonda-mime.php
```
Zamiast atrapy serwera jest **atrapa WIADOMOŚCI**: jedenaście maili zbudowanych tak, jak buduje je
zwykły program pocztowy, plus warianty brzegowe. Sonda woła **prawdziwe**
`segm_skrzynka_tresc_tekstem()` i **prawdziwe** `segm_wzorzec_pasuje()`, i drukuje jedno:
**co zobaczy reguła właściciela.** Jedyne podstawienie to `wp_strip_all_tags()`, przepisane
co do znaku z WordPressa **7.1** działającego na stanowisku — wypisane w nagłówku sondy.

⚠️ **Fakt środowiska przy okazji:** na stanowisku chodzi **WordPress 7.1**
(`docker exec strona_wp_wordpress … version.php` → `$wp_version = '7.1'`), a `CLAUDE.md` mówi
„WP 7.0". Drobiazg, ale to dokładnie ta klasa, która raz już rozlała się na wszystkie przebiegi
(nieaktualne fakty środowiska w materiale audytowym). Nie jest to wada produktu.

---

## 1. POMIAR — wyjście uruchomienia

```
SCENARIUSZ                                      DLUGOSC     REGULA  POCZATEK TEGO, CO ZOBACZY REGULA
A. text/plain 7bit (kontrola dodatnia)               57     TRAFIA  Dzien dobry,  w zalaczniku Faktura nr 7/2026.  Pozdrawiam
B. text/plain base64, jedna linia bez CRLF            0 NIE TRAFIA
C. text/plain base64, wiele linii z CRLF            297     TRAFIA  Tresc wypelniajaca. Tresc wypelniajaca. Tresc wypelniajaca. Tr
D. multipart/alternative, czesc tekstowa base64      358 NIE TRAFIA  --gr4nica  Content-Type: text/plain; charset=UTF-8  Content-Tr
E. multipart/alternative, czesc tekstowa 7bit       157     TRAFIA  --gr4nica  Content-Type: text/plain; charset=UTF-8  Content-Tr
F. text/plain quoted-printable                       57     TRAFIA  Dzien dobry,  w zalaczniku Faktura nr 7/2026.  Pozdrawiam
G. charset=BASE64 (nazwa z mb_list_encodings)        57 NIE TRAFIA  8Ú?Ż,3jVÎx¤¸V¤śęÚž˙ŰMş?>Ý­Ź"j
H. 4 kB niedomknietych <script> (czas)                0 NIE TRAFIA
I. quoted-printable z samych zlaman (czas)           17     TRAFIA  Faktura nr 7/2026
J. text/plain, "< 500" przed slowem reguly           71     TRAFIA  Dzien dobry,  budzet < 500 zl.  Prosze o faktura pro forma.  P
K. text/plain, "<piatek" przed slowem reguly         20 NIE TRAFIA  Dzien dobry,  termin
Regula "tresc zawiera faktura" trafia w 6 z 11 scenariuszy.
We WSZYSTKICH slowo jest w mailu widoczne dla czlowieka.
```
⭐ **Kontrole dodatnie zdane:** A, C, F, I, J przechodzą — czyli sonda **nie jest przyrządem,
któremu wszystko pada**, a kształty, które trafiają, to te zwyczajne.

---

## 2. ZNALEZISKO

### 🟠 `A7c-F1` — **DUZE** · reguła „treść zawiera" widzi co innego niż człowiek; cztery zmierzone drogi, w tym poczta w kształcie, jaki wysyła każdy zwykły program pocztowy

**ZNALEZISKO.** `segm_skrzynka_tresc_tekstem()` (`skrzynka.php:437`) sprowadza treść maila do
tekstu na potrzeby reguł. Czterema różnymi drogami oddaje tekst, w którym **nie ma słowa, jakie
w tym mailu widzi człowiek** — a mail ląduje wtedy w kuble domyślnym „Reszta" i właściciel ma
podstawy sądzić, że źle napisał regułę.

**⛔ To NIE jest cztery razy ta sama usterka.** Każda droga ma inną przyczynę i **naprawa jednej
nie zamyka pozostałych trzech.** Wypisuję je osobno, żeby nie zginęły przy naprawie:

---

**① MAIL WIELOCZĘŚCIOWY NIE JEST W OGÓLE DEKODOWANY** — scenariusz `D`, 358 znaków śmieci.
```
skrzynka.php:445	$wieloczesc = false !== stripos( $typ, 'multipart/' );
skrzynka.php:447	if ( ! $wieloczesc && 'base64' === $kodowanie ) {
skrzynka.php:455	} elseif ( ! $wieloczesc && 'quoted-printable' === $kodowanie ) {
```
Przy `multipart/*` **oba** dekodery są pominięte, a `Content-Transfer-Encoding` i tak jest brany
z nagłówków WIADOMOŚCI, nie z nagłówków CZĘŚCI — a właśnie tam ono siedzi. Reguła dostaje surowe
MIME: linie granic, nagłówki części i base64. Widać to w kolumnie wyjścia: `--gr4nica
Content-Type: text/plain; charset=UTF-8 Content-Tr…`.
⚠️ `multipart/alternative` **z definicji** (RFC 2046 §5.1.4) jest kształtem maila, który ma wersję
tekstową i HTML — czyli wszystkiego, co wychodzi z Gmaila, Outlooka czy Thunderbirda po
sformatowaniu. To nie jest przypadek brzegowy.
⭐ Scenariusz `E` pokazuje drugą stronę tej samej rzeczy: nawet gdy część tekstowa jest czytelna
i reguła **trafia**, do dopasowania idą też **nagłówki MIME** — więc reguła „treść zawiera
`charset`" złapałaby każdy wieloczęściowy mail.

---

**② OSTATNIA LINIA BASE64 JEST ODCINANA ZAWSZE, TAKŻE GDY NIC NIE BYŁO UCIĘTE** — scenariusz `B`,
wynik **0 znaków**.
```
skrzynka.php:450		$linie = explode( "\n", str_replace( "\r", '', $surowa ) );
skrzynka.php:451		array_pop( $linie );
```
Komentarz nad tym miejscem uzasadnia `array_pop` tym, że pobieramy tylko POCZĄTEK treści, więc
ostatnia linia bywa ucięta. Ale `array_pop` jest **bezwarunkowy**. Gdy treść zmieściła się
w całości i nie kończy się znakiem końca linii — a tak wygląda krótki mail — odcięta zostaje
**jedyna linia**, `implode()` daje pusty łańcuch i **cała treść znika**.
⛔ Warunku „czy naprawdę ucięliśmy" nie ma czym postawić: `tresc()` nie mówi wołającemu, czy
serwer oddał mniej, niż wynosi `SEGM_SKRZ_TRESC_BAJTOW`.

---

**③ NAZWA ZESTAWU ZNAKÓW JEST SPRAWDZANA WOBEC LISTY, KTÓRA ZAWIERA KODOWANIA TRANSPORTOWE** —
scenariusz `G`, treść zamieniona w bajtowy śmieć.
```
skrzynka.php:460	if ( preg_match( '/charset\s*=\s*"?([A-Za-z0-9_\-]+)"?/i', $typ, $m ) ) {
skrzynka.php:463	if ( '' !== $zestaw && 'UTF-8' !== $zestaw && in_array( $zestaw, array_map( 'strtoupper', mb_list_encodings() ), true ) ) {
```
Zmierzone na tym PHP (8.4.24): `mb_list_encodings()` ma **79 pozycji**, a wśród nich
**`BASE64`, `UUENCODE`, `HTML-ENTITIES`, `QUOTED-PRINTABLE`, `7BIT`, `8BIT`** — to nie są zestawy
znaków, tylko kodowania transportowe. Nazwę zestawu **podaje nadawca**, w swoim nagłówku
`Content-Type`. Wystarczy `charset=BASE64`, żeby `mb_convert_encoding()` przepuściło czytelny
tekst przez dekoder base64 i oddało śmieć. **Biała lista jest, ale wpuszcza nie to, co miała.**

---

**④ `wp_strip_all_tags()` KASUJE OD NIEDOMKNIĘTEGO ZNACZNIKA DO KOŃCA FRAGMENTU** — scenariusz `K`,
z 68 znaków zostaje 20.
```
skrzynka.php:472	$surowa = wp_strip_all_tags( $surowa );
```
Mail `Dzien dobry,\r\ntermin <piatek.\r\nProsze o faktura pro forma.\r\nPozdrawiam` daje
`Dzien dobry,  termin` — reszta znika, bo `<p` jest dla `strip_tags()` początkiem znacznika,
który nigdy się nie domyka. Treść jest **ucinana na 4096 bajtach** (`SEGM_SKRZ_TRESC_BAJTOW`),
więc w mailu HTML znacznik otwarty tuż przed cięciem zabiera cały ogon fragmentu tak samo.

⛔ **Czego tu NIE MA i mówię to wprost, bo sam się na tym przejechałem:** zwykły znak mniejszości
w zdaniu handlowym jest **nieszkodliwy** — scenariusz `J` („budzet < 500 zl") **TRAFIA**.
Groźny jest wyłącznie `<` bezpośrednio przed literą, bez domknięcia.

---

**SKUTEK — co traci człowiek.** Właściciel kupił bota, żeby segregował pocztę regułami. Wtyczka:
- ma dopasowanie po treści **włączone domyślnie** — `skrzynka.php:55`: `'dopasuj_tresc' => 1`;
- **wysyła z fabryki dwie reguły na `tresc`** — `instalator.php:164` „Reklamacje"
  (`reklamuj, zepsu, nie działa…`) i `:166` „Oferty" (`proszę o wycenę, ile kosztuje…`).

⭐ **Dlaczego nikt tego nie zauważył — to jest sedno.** Próbka demonstracyjna, na której właściciel
testuje reguły, jest **wpisana wprost do instalatora jako czysty tekst** (`instalator.php:259+`):
żadnego MIME, żadnego base64, żadnych granic. **Na próbce reguły działają bezbłędnie.** Dopiero
na prawdziwej skrzynce zaczynają milczeć — a przebieg skrzynki **nie zapisuje treści nigdzie**,
gdzie właściciel mógłby ją obejrzeć (`segm_skrzynka_ruch()` niesie `nadawca`, `temat`,
`message_id`, `folder_z`, `folder_do`, `stan`, `komunikat` — treści tam nie ma). Właściciel widzi
zielony raport przebiegu, poprawne liczby i mail w kuble „Reszta". **Nie ma czym zobaczyć, że
reguła dostała co innego, niż on czyta w swoim programie pocztowym.**

**RODZAJ SKUTKU:** `zaobserwowany` — widziałem skutek (wartość zwracaną przez funkcję produktu
i werdykt funkcji dopasowującej produktu), nie samą przyczynę. ⚠️ Zaobserwowany **na atrapach
wiadomości, nie na żywej skrzynce** — na wspólny stos nie wchodziłem.

**Waga — dlaczego DUZE i dlaczego nie wyżej.** To podręcznikowe „mylące zielone": przebieg kończy
się sukcesem, liczby się zgadzają, a główna funkcja produktu po cichu nie działa na poczcie
w najzwyklejszym kształcie. `KRYT` moje kryteria rezerwują dla bezpieczeństwa, pieniędzy, danych
osobowych i tekstu czytanego przez kupującego — **nic z tego tu nie zachodzi**, więc nie podnoszę.
⛔ Świadomie nie sypię dużą pod cel właściciela: jeżeli koordynatorka uzna, że „główna funkcja
produktu cicho nie działa" zasługuje u nas na osobną półkę, to jej decyzja, nie moja licytacja.

**Propozycja naprawy (dla buildera — NIE wykonałem).** ⛔ **Cztery drogi naraz, inaczej naprawa
zwęzi okno zamiast zamknąć klasę** — dokładnie tak jak przy `A7b-F1`:
① wybrać część `text/plain` z maila wieloczęściowego i dekodować ją **jej własnym**
`Content-Transfer-Encoding`; ② `array_pop` tylko wtedy, gdy naprawdę ucięliśmy (a do tego `tresc()`
musi powiedzieć, ile bajtów oddał serwer); ③ biała lista **zestawów znaków**, nie
`mb_list_encodings()`; ④ zdejmowanie znaczników po zamianie niedomkniętego `<` na encję albo
z ograniczeniem do rzeczywistych znaczników. Sonda w repo pokaże po naprawie, która droga została
— wystarczy ją uruchomić, jest do tego gotowa.

---

## 3. TERENY CZYSTE

### T15 · odporność dekodera na wejście złośliwe (czas i pamięć) — ✅ czysto
Scenariusz `H` (4 kB niedomkniętych `<script a=`, czyli wzorzec `@<(script|style)[^>]*?>.*?</\1>@si`
zmuszony do nawracania po całym tekście) i `I` (2000 miękkich złamań quoted-printable) — **żaden
nie przekroczył progu 0,5 s**, który sonda drukuje osobno, i żaden nie wywołał fatala. Nie ma tu
drugiego `A7b-F1`: eksplozja pamięci siedziała w warstwie protokołu, nie w dekoderze treści.
⚠️ `H` daje wynik pusty — ale to droga ④ znaleziska wyżej, nie osobna wada wydajnościowa.

### T16 · `iconv_mime_decode()` na nagłówkach (temat, nadawca) — ✅ czysto
`segm_skrzynka_odkoduj_naglowek()` (`skrzynka.php:400`) woła `iconv_mime_decode()` z flagą
`ICONV_MIME_DECODE_CONTINUE_ON_ERROR` i **sprawdza wynik**: `false` oznacza „zostaw wartość
surową", nie „oddaj `false` dalej". Jest też strażnik `function_exists()` — na hostingu bez
`iconv` funkcja zwraca wartość nieodkodowaną zamiast paść. Ścieżka błędu jest tu przemyślana.

### T17 · wyciąganie adresu nadawcy — ✅ czysto
`segm_skrzynka_adres()` (`skrzynka.php:414`) próbuje trzech wzorców po kolei, każdy zakotwiczony
i bez zagnieżdżonych kwantyfikatorów; przy braku dopasowania oddaje `trim( $od )`, czyli nigdy
nie zwraca pustki tam, gdzie coś było. Nie znalazłem wejścia, przy którym adres z pola `From`
przepada albo podmienia się na cudzy.

### T18 · dopasowanie reguł jako takie — ✅ czysto
`segm_wzorzec_pasuje()` (`silnik.php:62`) jest **jednym** miejscem reguły dopasowania, wołanym
i przez silnik (`silnik.php:33`), i przez licznik w panelu (`admin-probka.php:127`) — czyli panel
nie liczy po swojemu i nie może pokazać liczby, której przebieg nie potwierdzi. To jest zamknięta
klasa 27 („dwa rejestry tej samej rzeczy") i warto, żeby tak zostało.

---

## 4. ⛔ FAŁSZYWY ALARM MOJEGO PRZYRZĄDU — złapany przed zgłoszeniem

Pierwsza wersja scenariusza `J` dała `NIE TRAFIA` i przez chwilę wyglądała na piąte, najgroźniejsze
znalezisko: **zwykły znak mniejszości w polskim zdaniu handlowym miałby kasować resztę treści.**
Nieprawda. Mail w tamtej wersji zawierał słowo **„fakturę"**, a reguła szukała **„faktura"** —
to jest zawieranie podciągu, więc nie trafiała **z powodu mojego doboru słowa**, nie z powodu
kodu. Rozstrzygnęła długość wyniku: **110 znaków przy około 109 znakach wejścia**, czyli nic nie
zostało obcięte. Poprawiłem scenariusz na słowo występujące dosłownie i `J` **TRAFIA**.
⭐ Zapisuję to, bo gdybym poszedł dalej o jeden krok, wysłałbym koordynatorce znalezisko
o zdaniach handlowych z ostrym nawiasem — a mechanizm dotyczy wyłącznie `<` przed literą.
Odruch „sprawdź własne narzędzie, zanim oskarżysz cudze" zadziałał trzeci raz w tym audycie.

## 5. ⭐ CZEGO NIE BYŁO NA MOJEJ LIŚCIE
1. **STARTTLS** (`class-segm-imap.php:267`) — **NIETKNIĘTE**, na Twoje polecenie odłożone.
   Pytania czekają: czy po podniesieniu szyfrowania klient odświeża `CAPABILITY` i czy odrzuca
   serwer, który STARTTLS-a odmówił.
2. **Wydajność przy danych** — **NIETKNIĘTE**, jw. Zero pomiarów na objętości, w żadnej z wtyczek.
3. **Załączniki** — wtyczka ich świadomie nie pobiera (napisane w nagłówku klasy IMAP), więc nie
   pytałem; ale znaczy to też, że reguła „treść zawiera" nigdy nie zajrzy do PDF-a z fakturą,
   a właściciel może się tego spodziewać. To pytanie do zamawiającego, nie usterka.
4. **Kodowanie treści zapisywanej do `segm_maile` z importu CSV** — sprawdzałem drogę ze
   SKRZYNKI, nie z pliku.

## 6. ODRZUCONE
| trop | dlaczego odrzucony |
|---|---|
| zwykły `<` w zdaniu handlowym kasuje treść | **obalony pomiarem własnym** — patrz §4; groźny jest tylko `<` przed literą, bez domknięcia |
| `mb_convert_encoding()` da się zmusić do eksplozji pamięci egzotycznym zestawem | nie potwierdzone: żaden scenariusz nie przekroczył progu czasu ani nie ruszył pamięci; wpis o `charset` dotyczy **przekłamania treści**, nie zasobów |
| dekoder nagłówków oddaje `false` dalej do bazy | nieprawda, wynik jest sprawdzany — `skrzynka.php:400-405` |
| licznik reguły w panelu liczy inaczej niż silnik | nieprawda, jedna funkcja dla obu — `silnik.php:62` |

---

# ⛔ CZEGO NIE ZDĄŻYŁEM — DOMKNIĘCIE CAŁEGO ETAPU A7

**Dopisane na koniec etapu, na polecenie koordynatorki.** Ta sekcja zbiera **wszystko nietknięte
z trzech raportów** (`A7`, `A7b`, `A7c`), żeby następna sesja nie musiała ich czytać po kolei.
To jest **lista zadań, nie przypis** — dokładnie w tym znaczeniu, w jakim zostawiła ją poprzednia
sesja białych plam. ⛔ „Nie zdążyłem" **nie jest dyspensą**: każda pozycja niżej ma prawo nie
wrócić tylko po świadomej decyzji, że tego nie zamykamy.

## ⭐ Dwie pozycje wskazane wprost przez koordynatorkę

### 1. `STARTTLS` — jedyna nieprzebadana część klienta IMAP
`class-segm-imap.php:267`, `private function starttls( $host )`. `A7b` przeszedł literały,
linie i limity czasu — **tej metody nie tknął ani jednym pytaniem**, a jest to moment, w którym
połączenie przechodzi z jawnego w szyfrowane, czyli najczulszy punkt całego klienta.

**Pytania, które trzeba tam zadać** (kolejność od najgroźniejszego):
1. **Czy odmowa serwera kończy rozmowę, czy tylko ją odnotowuje?** Serwer odpowiadający na
   `STARTTLS` statusem `NO`/`BAD` musi oznaczać ZERWANIE, nie „lecimy dalej jawnym tekstem" —
   inaczej hasło aplikacji właściciela wychodzi w postaci jawnej. To jest klasa „funkcja
   autoryzująca nie ma prawa mieć domyślnego otwarcia", tylko o warstwę niżej.
2. **Czy po podniesieniu szyfrowania klient odświeża `CAPABILITY`?** RFC 3501 6.2.1 wymaga tego
   wprost: możliwości sprzed TLS pochodzą z kanału, którego nikt nie uwierzytelnił, i napastnik
   mógł je podmienić (klasyczne „STARTTLS stripping").
3. **Czy `ufaj_bez_cert` dotyczy TAKŻE tej ścieżki?** Kontekst gniazda budowany jest w `polacz()`;
   trzeba sprawdzić, czy `stream_socket_enable_crypto()` w `starttls()` dziedziczy te same
   ustawienia weryfikacji, czy własne.

**Jak to zrobić — przyrząd JUŻ JEST i wystarczy dołożyć scenariusze.**
`audyt/plamy/A7b-sonda-imap-atrapa.php` to zwykły serwer TCP; nowe przypadki to kilka linii:
serwer ogłaszający `STARTTLS` i odmawiający go, serwer ogłaszający i milczący, serwer
**nieogłaszający** `STARTTLS` przy ustawieniu wymagającym szyfrowania.
⛔ **Warunek ważności pomiaru:** sonda musi umieć pokazać, że hasło **NIE wyszło** — czyli atrapa
ma zapisywać wszystko, co dostała, a asercja ma szukać w tym zapisie hasła. Sam brak fatala nie
jest tu dowodem niczego.

### 2. Wydajność przy danych — **zero pomiarów na objętości w całym etapie A7**
Nie zadałem tego pytania **ani razu, w żadnej z trzech wtyczek**. Nie wiem, czy jest tam co
znaleźć — i to jest właśnie definicja białej plamy, nie wynik „czysto".

**Gdzie szukać w pierwszej kolejności** (miejsca, które przy okazji innych pomiarów wyglądały na
podatne, ale ich NIE zmierzyłem):
- `sklepag_dociagnij_zalegle()` — pętla po sesjach z zapytaniem do Stripe **na sesję**; budżet
  czasu jest, ale nie wiem, jak zachowuje się przy kilkuset sesjach porzuconych koszyków.
- `segm_skrzynka_przebieg()` — `naglowki()` + `tresc()` to **dwa okrążenia po sieci na każdą
  wiadomość**; limit `SEGM_SKRZ_LIMIT_CZASU` = 20 s jest, ale ile wiadomości mieści się w tych
  20 s przy realnym opóźnieniu do Gmaila — nie zmierzone.
- `chatl_statystyki` (`statystyki.php`, 820 linii) — najwięcej zapytań agregujących w produkcie,
  nietknięte żadną soczewką tego etapu.
- Listy `WP_List_Table` — `COUNT(*)` + `SELECT` na każde odświeżenie, bez cache.
⚠️ Skill `wp-performance` jest podpięty, a `grep -rl wp-performance --include=*.md .` daje jedno trafienie: ten raport. Nikt go w tym zleceniu nie użył.

## Pozostałe pozycje, wg pochodzenia

### Z `A7` (kod produktu naszą siatką)
- **Zachowanie w przeglądarce.** Cała siatka `A7` była statyczna — nie kliknąłem ani razu. To teren
  `A4b` i osobnej sesji, ale zapisuję: trzy z czterech znalezisk `A4b` siedziały w STANIE ekranu,
  a mój przebieg takiej wady **nie mógł zobaczyć z konstrukcji**.
- **Treść tekstów sprzedażowych wobec prawa i wobec produktu.** Mierzyłem LICZBY i NAZWY ZDARZEŃ,
  nie sens zdań. Kwalifikacja prawna sformułowań to zadanie dla prawnika — zapisać jako takie,
  nie jako „sprawdzone".
- **`A7-F3` czeka na decyzję, nie na naprawę.** Trzy funkcje bez wywołania
  (`chatl_ai_uzycie_dzis`, `chatl_ai_tokeny_dzis`, `segm_skrzynka_srodowisko_pada`).
  Pytanie nie brzmi „usunąć czy zostawić", tylko: **czy `segm_skrzynka_srodowisko_pada()` nie
  miała być gdzieś wołana** — nazwa mówi „czy diagnostyka wykryła coś, co uniemożliwia
  połączenie", a takiego sprawdzenia przed połączeniem nie ma.

### Z `A7b` (klient IMAP)
- **Progi pamięci przy prawdziwym limicie hostingu.** Mierzyłem na `memory_limit=64M`, świadomie,
  żeby nie ruszać pamięci maszyny. Dla literału wynik będzie ten sam przy każdym limicie
  (alokacja 900 MB), ale progi dla scenariuszy `linia-ogromna` i `bez-znacznika` **przesuną się
  w górę i nie wiem gdzie**. Do powtórzenia na 128M i 256M, jednym przebiegiem sondy.
- **Samotny `CR` w wartości nagłówka.** `T11` obalił wstrzyknięcie polecenia przez `CRLF`, ale
  samotny `CR` w ŚRODKU wartości przechodzi przez `trim()` i przez `tekst()`. Czy cokolwiek z tym
  zrobi serwer, zależy od tego, jak dzieli strumień na linie — **niezmierzone**. To trop, nie
  znalezisko, i tak go zostawiam.
- **Trzecia zmiana stanu: przejście na klucze produkcyjne.** ⛔ Nietknięte **na polecenie**,
  nie z braku czasu — siedzi tam builder z naprawą `P-3`.

### Z `A7c` (MIME)
- **Załączniki.** Wtyczka świadomie ich nie pobiera (napisane w nagłówku klasy IMAP). Ale znaczy
  to, że reguła „treść zawiera" **nigdy nie zajrzy do PDF-a z fakturą**, a właściciel może się
  tego spodziewać. **To jest pytanie do zamawiającego, nie usterka** — i nikt go dotąd nie zadał.
- **Droga z pliku, nie ze skrzynki.** Sprawdziłem dekodowanie treści przychodzącej z IMAP-a;
  **import CSV do `segm_maile` (`csv.php`) nie był tknięty ani razu** — a to druga droga, którą
  cudzy tekst wchodzi do tej samej tabeli i do tego samego silnika reguł.

### Obszary kodu, których w ogóle nie objęła żadna z trzech soczewek
Wypisuję je, bo „nie zgłosiłem stamtąd nic" i „nie patrzyłem tam" wyglądają w raporcie identycznie:
`chatbot-live/includes/statystyki.php` (820) · `chatbot-live/includes/import.php` (745) ·
`chatbot-live/includes/przejecie.php` (623, poza punktami wejścia) ·
`segregator-maila/includes/admin.php` (1504, poza bramą wejść) ·
`segregator-maila/includes/filtry.php` (588, eksport do Gmaila/Outlooka) ·
wszystkie arkusze stylów i skrypty przeglądarki (`assets/`, **5163 linie** — policzone, nie oszacowane).

## ⭐ Co z tego etapu warto ponieść dalej — jedno zdanie metody
Dwa najlepiej udowodnione znaleziska (`A7b-F1`, `A7c-F1`) wyszły z **uruchomienia produktu przeciw
atrapie**, nie z czytania kodu, i **oba mają po kilka dróg o różnych przyczynach**. Trzy fałszywe
alarmy w tym etapie pochodziły z **mojego przyrządu**, nie z produktu, i wszystkie trzy złapał ten
sam odruch: *sprawdź własne narzędzie, zanim oskarżysz cudze.*
