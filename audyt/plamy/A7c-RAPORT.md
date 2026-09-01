# A7c — MIME i dekodowanie treści maila

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
