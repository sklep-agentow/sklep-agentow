# A7b — DALSZY CIĄG A7: klient IMAP i dwie zmiany stanu

> ⚠️ **Kotwica cytatów (dopisane 2.09 przy scalaniu z `main`):** numery `plik:linia` w tym raporcie pochodzą ze stanu kodu na commicie
> `b39f576` (1.09, gałąź `audyt/a7-kod-produktu`). Po scaleniach #259, #263 i #264 część numerów na `main` jest przesunięta — sonda 2.09
> (69 cytatów jednoznacznych, 21 z nazwą pliku występującą w kilku wtyczkach) potwierdziła przesunięcia w `zakup.php` (+~90 linii) i `aktywacja.php` (+11),
> `stripe.php` bez zmian; te trzy poprawione na HEAD, reszty NIE przeliczano. Pewny odczyt: `git show b39f576:<ścieżka>` (klasa 97 ze ściągi).


**Kto:** audytor A7, sesja 2026-09-01, druga pozycja. **Gałąź:** `audyt/a7-kod-produktu`.
**Zlecenie koordynatorki:** priorytet 1 — wnętrze klienta IMAP; priorytet 2 — drugi zakup tego
samego produktu i wygaśnięcie linku. ⛔ **Przejścia na klucze produkcyjne NIE ruszałem** —
siedzi tam builder z naprawą `P-3`.
**Kodu produktu nie tknąłem.** Wynik: **jedno `SRED`, jedno `MALE`, cztery tereny czysto.**

⭐ Ten przebieg jest inny od A7: tam mierzyłem zestawieniami po plikach, tutaj **uruchamiam
produkt naprawdę**. Klasa `Segm_Imap` nie zależy od WordPressa poza strażnikiem `ABSPATH`, więc
dało się ją postawić samą, podłączyć do **atrapy serwera IMAP** i podać jej wejście, którego
w normalnej pracy nigdy nie zobaczy. Przyrząd leży w repo i powtarza się jedną komendą.

```
bash audyt/plamy/A7b-sonda-imap.sh
```
`A7b-sonda-imap-atrapa.php` (serwer-atrapa, siedem scenariuszy; od 5.09 osiem — `search-40k`, kontrola dodatnia W SKALI dopisana po #301 r3, gdy sufit 64 KB odcinał `* SEARCH` dużej skrzynki; sonda jest od tej pory silnikiem strażnika `testy/segregator-imap-sufity-proba-kontrolna.sh`) · `A7b-sonda-imap.php` (sonda:
łączy `Segm_Imap`, woła `naglowki()`, mierzy czas, szczyt pamięci i fatala) · `A7b-sonda-imap.sh`
(przelot). Sonda **mierzy i drukuje** — nie jest strażnikiem i nie należy do bramek.

---

## 1. ZNALEZISKA

### 🟠 `A7b-F1` — **SRED** · klient IMAP nie ma ŻADNEJ górnej granicy na to, ile przyjmie od serwera; trzy różne ścieżki kończą proces PHP fatalem, a jedna linia odpowiedzi wystarczy

**ZNALEZISKO.** `Segm_Imap::czytaj_linie()` czyta literały o rozmiarze, który **podaje serwer**,
i robi to bez jakiegokolwiek sufitu. `Segm_Imap::wykonaj()` zbiera linie odpowiedzi do tablicy
`$linie` bez limitu ich liczby. `fgets()` na gnieździe jest wołany **bez argumentu długości**.
Każda z tych trzech ścieżek kończy się `Allowed memory size … exhausted`, czyli **śmiercią
procesu PHP** — z pominięciem całego aparatu komunikatów po polsku, który ta klasa ma zbudowany
(`blad()`, `dziennik()`, `po_ludzku()`).

**DOWODY — wynik uruchomienia, nie odczyt kodu.** `bash audyt/plamy/A7b-sonda-imap.sh`,
`php -d memory_limit=64M`, limit czasu gniazda 5 s:

| scenariusz atrapy | wynik sondy | gdzie |
|---|---|---|
| **`poprawny`** (kontrola dodatnia) | `NAGLOWKI: string(36)`, `BLAD: (brak)`, bez fatala | — |
| `literal-sama-deklaracja` — serwer deklaruje `{943718400}` i **nie wysyła ani jednego bajtu** | 🔴 `FATAL: … tried to allocate 943718432 bytes`, szczyt pamięci **2,0 MB** | `class-segm-imap.php:914` |
| `literal-ogromny` — deklaruje 900 MB i strumieniuje bez końca | 🔴 `FATAL: … tried to allocate 943718432 bytes`, szczyt 2,0 MB | `class-segm-imap.php:914` |
| `bez-znacznika` — nieskończony strumień linii nietagowanych, brak linii kończącej | 🔴 `FATAL`, szczyt **62,0 MB**, czas 0,3 s | `class-segm-imap.php:933` |
| `linia-ogromna` — jedna linia bez końca, rosnąca | 🔴 `FATAL: … tried to allocate 48239218 bytes`, szczyt **50,0 MB** | `class-segm-imap.php:902` |
| `literal-obciety` — deklaruje 1000 B, wysyła 10 i zamyka | ✅ `null` + komunikat po polsku, bez fatala | obsłużone |
| `bez-crlf` — bajty bez końca linii, potem cisza | ✅ `null` + „przestał odpowiadać (limit 5 s)", bez fatala | obsłużone |

⭐ **Najostrzejszy wynik jest w drugim wierszu i to on rozstrzyga o mechanizmie.** Szczyt pamięci
**2,0 MB** przy próbie alokacji 900 MB znaczy, że nic się nie nagromadziło — proces ginie na
**samym wywołaniu**:
```
914:					$kawalek = fread( $this->gniazdo, $ile - $pobrane );
```
`$ile` pochodzi wprost z `preg_match( '/\{(\d+)\}\r?\n$/', $tekst, $m )` (`:908`) i nie jest
z niczym porównywane. **Serwerowi wystarczy jedna linia `* 1 FETCH (BODY[HEADER] {943718400}`,
żeby położyć proces** — nie musi wysłać ani bajtu danych.

⛔ **Kontrola dodatnia jest tu obowiązkowa i jest zdana:** scenariusz `poprawny` (poprawny
literał, poprawne zakończenie) zwraca treść nagłówków i pusty błąd. Sonda nie jest przyrządem,
któremu „wszystko pada".

**MECHANIZM — kto może stanąć po drugiej stronie.** To jedyne miejsce w całym produkcie, gdzie
dane wchodzą prosto z **obcego serwera**, i granica zaufania stoi wyłącznie na TLS:
```
201:					'verify_peer'       => ! $ufaj_bez_cert,
202:					'verify_peer_name'  => ! $ufaj_bez_cert,
203:					'allow_self_signed' => (bool) $ufaj_bez_cert,
```
`$ufaj_bez_cert` to **zwykły przełącznik w panelu**, do kliknięcia przez właściciela:
`admin-skrzynka.php:241` — „nie sprawdzaj certyfikatu serwera" (domyślnie 0, `skrzynka.php:58`).
Po jego włączeniu weryfikacja certyfikatu znika, więc rolę serwera może przejąć **ktokolwiek
w sieci** — i wtedy powyższe scenariusze przestają wymagać wrogiego dostawcy poczty.
Bez tego przełącznika trzeba awarii albo złośliwości serwera właściciela.

**SKUTEK — co traci człowiek.** Właściciel klika „Sprawdź połączenie" albo „Uruchom teraz"
i dostaje **białą stronę / „wystąpił błąd krytyczny"** zamiast któregokolwiek z komunikatów, które
ta wtyczka ma napisane po polsku specjalnie dla niego. Ta sama ścieżka wywraca przebieg cykliczny
z crona. ⛔ **Nie jest to utrata danych** i mówię to wprost: przebieg zapisuje zamiar PRZED
ruchem poczty (`skrzynka-przebieg.php:221-236`, `komunikat = SEGM_SKRZ_KOMUNIKAT_W_TOKU` w wierszu `:232`),
a zamek ma TTL 120 s (`SEGM_BLOKADA_WAZNA`, `segregator-maila.php:297`) — czyli robota z `S1`
i `T-4` broni stanu również przed śmiercią procesu. Zostaje przebieg wiszący jako „w toku",
zdejmowany cofaniem po Message-ID.

**RODZAJ SKUTKU:** `zaobserwowany` — widziałem SKUTEK (fatal, kod wyjścia 255), nie samą
przyczynę, i mam z niego wyjście komendy.

**Waga — dlaczego SRED, a nie wyżej.** Awaria jest **głośna** (proces ginie), nie cicha; nie ma
utraty ani wycieku danych; przy domyślnych ustawieniach po drugiej stronie stoi serwer pocztowy
wybrany przez właściciela. To jest realna dziura procesu — funkcja przestaje działać, a własny
aparat diagnostyczny wtyczki zostaje ominięty — czyli dokładnie `SRED`.
⛔ **Świadomie NIE podnoszę wagi za przełącznik „nie sprawdzaj certyfikatu".** Byłoby to
sypnięcie dużą pod cel: przełącznik jest domyślnie wyłączony, a jego włączenie to osobna decyzja
właściciela. Zapisuję go jako **warunek zaostrzający**, nie jako podstawę wagi.

**Propozycja naprawy (dla buildera — NIE wykonałem):** sufit na `$ile` (literał ponad, powiedzmy,
kilka MB = odmowa z komunikatem, nie odczyt), sufit na liczbę linii w `wykonaj()`, `fgets()`
z argumentem długości. **Klasa, nie egzemplarz: wszystkie trzy naraz** — załatanie samego
literału zostawia dwie pozostałe ścieżki, a sonda w repo od razu pokaże, czy któraś została.
Sonda jest gotowa na strażnika: wystarczy, żeby liczyła fatale i kończyła `exit 1`.

---

### ⚠️ `A7b-F2` — **MALE** · panel pokazuje termin ważności linku w UTC bez konwersji i bez etykiety, dwie linie nad wierszem, który konwertuje i etykietuje

**ZNALEZISKO.** `link_wygasa` jest **jedyną** kolumną tabeli zamówień zapisywaną w UTC
(`gmdate()`), gdy sąsiednie (`utworzono`, `zmieniono`, `zwrot_czas`) idą przez
`current_time( 'mysql' )`, czyli w czasie witryny. Panel drukuje ją **surowo**.

**DOWODY.**
Zapis w UTC — dwie ścieżki, obie `gmdate()`:
```
zakup.php:954              gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_LINK_GODZIN * HOUR_IN_SECONDS ),
admin-zamowienie.php:108              gmdate( 'Y-m-d H:i:s', time() + SKLEPAG_LINK_GODZIN * HOUR_IN_SECONDS ),
```
Odczyt w panelu, bez konwersji i bez słowa, że to UTC:
```
admin-zamowienie.php:336   … ( $zam->link_wygasa ? ' · link ważny do ' . esc_html( $zam->link_wygasa ) : ' · link unieważniony' ) …
```
⭐ **Dwie linie niżej ten sam plik robi to poprawnie** — i ma na to komentarz:
```
admin-zamowienie.php:337   // Kolumna zgoda_czas jest w GMT — panel pokazuje czas lokalny strony, ten sam, który dostaje kupujący w mailu.
admin-zamowienie.php:338   … esc_html( $zam->zgoda_czas ? get_date_from_gmt( $zam->zgoda_czas, 'd.m.Y H:i' ) . ' (czas lokalny)' : '—' ) …
```
Wiedza jest w pliku, zastosowana do jednej kolumny i nie zastosowana do sąsiedniej — **klasa 8
ściągi (bliźniak), w odległości dwóch linii.**

**SKUTEK.** Właściciel w strefie Europe/Warsaw widzi termin **o 2 h wcześniejszy** (latem) niż
prawdziwy. Zakup o 07:04 czasu lokalnego daje wpis „link ważny do 05:04" trzy dni później —
wygląda na 70 godzin zamiast 72. Przy sporze z kupującym o to, czy link jeszcze działał, panel
podaje właścicielowi złą godzinę. **Kupującego to NIE dotyczy** — sprawdziłem osobno, mail podaje
CZAS TRWANIA, nie termin (`zakup.php:1185` i `mail-html.php:42`: `$dni = round( 72 / 24 )`),
więc nie ma tu tekstu kłamiącego kupującemu.
**RODZAJ SKUTKU:** `wyliczony` — wartość podana: przesunięcie równe `gmt_offset` witryny,
2 h w czasie letnim, 1 h zimą.

**Waga MALE, nie wyżej:** to niespójność wyświetlania. **Zachowanie jest poprawne** — obie strony
porównania decydującego o odmowie są w UTC (`pobieranie.php:41`:
`strtotime( $zam->link_wygasa ) < time()`), więc link wygasa dokładnie po 72 h. Nic się nie psuje,
tylko panel mówi o godzinę lub dwie nieprawdę.

---

## 2. TERENY CZYSTE

### T11 · wstrzyknięcie polecenia IMAP przez wartość z cudzego maila — ✅ czysto (tropu NIE potwierdziłem)
Trop był mocny i wart opisania, bo prowadzi przez **wejście od obcego człowieka**: `tekst()`
(`:1065`) escapuje wyłącznie `\` i `"`, a RFC 3501 zakazuje w łańcuchu cudzysłowowym także CR
i LF. Gdyby dało się wprowadzić tam `CRLF`, wiersz polecenia rozpadłby się na dwa i drugi byłby
osobnym poleceniem IMAP. Do `tekst()` wchodzi wartość **nagłówka `Message-ID` cudzego maila**
(`skrzynka-przebieg.php:654` → `znajdz_po_message_id()` → `uidy( 'HEADER Message-ID ' . tekst() )`),
czyli tekst pisany przez dowolnego nadawcę.

**Trop obalony na parserze nagłówków** (`skrzynka.php`, `segm_skrzynka_naglowek()`):
```
$rozwiniete = preg_replace( '/\r?\n[ \t]+/', ' ', (string) $surowe );
if ( ! preg_match( '/^' . preg_quote( $nazwa, '/' ) . ':[ \t]*(.*)$/mi', (string) $rozwiniete, $m ) ) { return ''; }
return trim( $m[1] );
```
Bez modyfikatora `s` kropka **nie obejmuje `\n`**, a `$` w trybie `/m` kończy dopasowanie na
końcu linii fizycznej — więc do `$m[1]` nie ma jak trafić znak nowej linii. Samotny `CR` na końcu
zdejmuje `trim()` (domyślna lista znaków PHP zawiera `\r`).
⚠️ **Czego NIE orzekam:** samotny `CR` w ŚRODKU wartości przechodzi przez `trim()` i przez
`tekst()`. Czy cokolwiek z tym zrobi serwer, zależy od tego, jak dzieli strumień na linie —
tego **nie zmierzyłem** i nie zgłaszam jako znaleziska, bo byłoby to podejrzenie bez dowodu.
Zapisuję jako trop dla kogoś, kto będzie miał serwer do popsucia.
Pozostałe wejścia do poleceń są związane: UID-y rzutowane `(int)` (`:558`, `:578`, `:592`),
nazwy folderów przez `do_utf7()` + `tekst()`.

### T12 · drugi zakup tego samego produktu przez tego samego kupującego — ✅ czysto
Szukałem miejsc, które zakładają **jedno** zamówienie na parę kupujący+produkt. Nie ma ich:
klucze unikalne stoją na `stripe_session_id` (`aktywacja.php:93` na HEAD 2.09; `:82` na `b39f576`) i na `opinie.zamowienie_id`
(`:135`), czyli **na transakcji, nie na osobie** — dwa zakupy dają dwa niezależne zamówienia,
dwa tokeny pobrania i dwa zalążki opinii. Zapytania kluczujące po `email_kupujacego` to eksporter
RODO, kasownik RODO i wyszukiwarka panelu — wszystkie **iterują po wszystkich wierszach**
(`prywatnosc.php:376`, `:449`, `:598`), żadne nie bierze „pierwszego".
⛔ Znane i **NIE zgłaszane ponownie**: okno idempotencji 5 s kluczowane adresem IP — to
`SK-L3-005`/`SK-L3-006`, opisane w rejestrze sklepu jako jawnie niezamknięta połowa.

### T13 · wygaśnięcie linku pobrania — ✅ czysto
Obie strony porównania decydującego o odmowie są w tym samym układzie czasu (UTC): kolumna
pisana `gmdate()`, warunek `strtotime( $zam->link_wygasa ) < time()` (`pobieranie.php:41`).
Odmowa ma własny kod HTTP i kontakt (`410` + mail właściciela ze `sklepag_email_kontaktowy`).
Anonimizacja RODO ustawia `link_wygasa = NULL` i pusty token, a brama traktuje to jako odmowę,
nie jako „brak terminu = zawsze ważny" (`pobieranie.php:41`, warunek `! $zam->link_wygasa`
stoi PIERWSZY). Kolumna nie jest czytana nigdzie indziej niż w bramie i w panelu — sprawdzone
`grep`-em po liście zamówień, pulpicie i froncie: zero trafień.
Jedyna rysa na tym terenie to `A7b-F2` wyżej — wyświetlanie, nie zachowanie.

### T14 · kasownik RODO przy wielu zamówieniach jednego adresu — ✅ czysto
To sąsiad terenu T12 i sprawdziłem go, bo drugi zakup zwielokrotnia dane tej samej osoby.
Kasownik bierze porcję `SKLEPAG_RODO_PORCJA`, anonimizuje, po czym liczy, ile zostało.
⭐ Pułapka tej konstrukcji — porcja, w której nie udało się NIC, zostawia te same wiersze
i kreator WordPressa wołałby kasownik w kółko — jest **rozpoznana i zamknięta w kodzie**,
z komentarzem nazywającym ją wprost:
```
prywatnosc.php:678   /* ⛔ `done` nie może zależeć wyłącznie od „czy coś jeszcze zostało": porcja, w której
prywatnosc.php:679      nie udało się NIC, zostawia te same wiersze i kreator wołałby nas w kółko. */
prywatnosc.php:680   $done = ( 0 === $zostalo ) || ( 0 === $usuniete );
```
Nieudane zamówienia wracają nazwane, z jawnym zakazem potwierdzania usunięcia wnioskodawcy.

## 3. ⭐ CZEGO NIE BYŁO NA MOJEJ LIŚCIE
1. **MIME i kodowanie treści** — sonda dotyka warstwy protokołu, nie tego, co Segregator robi
   z odkodowaną treścią maila (`segm_skrzynka_tresc_tekstem`, `segm_skrzynka_odkoduj_naglowek`).
   Tam też wchodzi tekst od obcego człowieka i **nie zadałem temu ani jednego pytania**.
2. **STARTTLS** (`:267`) — nie sprawdziłem, czy po podniesieniu szyfrowania klient odświeża
   CAPABILITY i czy odrzuca serwer, który STARTTLS-a odmówił.
3. **Zachowanie sondy przy prawdziwym limicie hostingu** — mierzyłem na 64 MB, żeby nie ruszać
   pamięci maszyny; przy 256 MB wynik dla literału będzie ten sam (alokacja 900 MB), ale progi
   dla „linia-ogromna" i „bez-znacznika" przesuną się w górę i nie wiem gdzie.
4. **Trzecia zmiana stanu — przejście na klucze produkcyjne.** Nietknięta na Twoje polecenie,
   nie z powodu budżetu.

## 4. ODRZUCONE
| trop | dlaczego odrzucony |
|---|---|
| mail podaje kupującemu termin ważności w złej strefie | nieprawda: mail podaje CZAS TRWANIA („3 dni"), nie termin — `zakup.php:1185`, `mail-html.php:42` |
| `COUNT(DISTINCT email_kupujacego)` na Pulpicie zlicza wszystkie zanonimizowane zamówienia jako JEDNEGO kupującego (anonimizacja ustawia `''`) | zachowanie poprawne z definicji: po anonimizacji sklep **nie ma prawa wiedzieć**, że to były różne osoby. Zgłoszenie tego byłoby wymyśleniem reguły, której nikt nie postawił |
| wstrzyknięcie polecenia IMAP przez Message-ID | obalone na parserze nagłówków — patrz T11; samotny CR zapisany jako trop, nie jako znalezisko |
| kasownik RODO wpada w pętlę przy trwale nieudanej anonimizacji | rozpoznane i zamknięte w kodzie (`prywatnosc.php:679`) |
| brak limitu tokenów w stop-lossie Segregatora (jest tylko limit wywołań) | obalone już w A7: sufit próbki `SEGM_AI_MAX_PROBKA` ogranicza koszt inną drogą |
