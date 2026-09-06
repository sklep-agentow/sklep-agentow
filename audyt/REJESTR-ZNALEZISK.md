# 📋 REJESTR ZNALEZISK AUDYTU — jedno źródło prawdy

> ⛔ **Pilnowany maszynowo** przez `narzedzia-qa/bramka-znaleziska.sh`.
> Nowy raport audytu bez wpisu tutaj **zatrzymuje bramkę**. Znalezisko bez testu też.
>
> Powód powstania (21.08): mieliśmy zasadę „każdy błąd dostaje strażnika z exit 1 wpięty
> do CI" i **nie była spełniona ani razu**. W `testy/` leżało 57 plików, CI nie uruchamiało
> żadnego. PR #135 wniósł dwa celowo padające testy — i przeszedł na zielono.

## Liczba znalezisk OTWARTYCH: 0
<!-- 2.09: CZ-1 ZAMKNIETE (ostatnie otwarte) — liczone bramką znaleziska. -->
<!-- BRAMKA: powyższa liczba musi zgadzać się z liczbą wierszy o statusie OTWARTE.

     ⭐ PODNIESIONE ŚWIADOMIE 24.08 (wieczór) z 0 na 1: przelot człowieka (29 pkt) znalazł CZ-1 —
     ciche przycinanie pytań >500 zn. Klasa 😖 „działa, ale myli", nie blokuje wydania; naprawa
     (komunikat przy limicie) + strażnik przy najbliższej iteracji kodu.

     ✅ ZAMKNIĘTE 24.08 z 23 na 0: scalone paczki napraw C1–C6 (#155 #158 #162 #164 #166 #167),
     S1–S5 (#156 #159 #161 #163 #165) + A6-F2 domknięte 24.08 poprawką materiału audytowego
     i nowym strażnikiem. Statusy nadane PO POMIARZE (lekcja z 23.08 niżej): pełna linia bazowa
     po naprawach — regresje=0, wszystkie strażniki znalezisk EXIT=0 (zapis: audyt/LINIA-BAZOWA-TESTOW.md,
     wpis [po-naprawach-2] i finalny [etap3-final]).
     Dopisanie znaleziska ZATRZYMUJE bramkę, dopóki ktoś świadomie nie podniesie liczby.
     ⭐ Ta liczba ma prawo TYLKO MALEĆ. Jeśli rośnie — to znaczy, że audyt znalazł coś nowego,
     i to jest w porządku; ale musi być świadome, nie po cichu.

     ⭐ PODNIESIONE ŚWIADOMIE 21.08 z 6 na 15: audyt botów się skończył i doszły znaleziska
     z czterech raportów, których wcześniej tu nie było (A4 front, A6 dokumentacja odwrotnie,
     Łamacz ChatBot, Łamacz Segregator). Nic nie zniknęło — trzy dawne wiersze `F1`/`F2`/`F3`
     przeszły na ZAMKNIETE (scalone #136, e28b825), więc wypadły z licznika OTWARTYCH.

     ⛔ PODNIESIONE ŚWIADOMIE 23.08 z 20 na 23: POMIAR obalił trzy ZAMKNIĘTE. Uruchomienie
     `audyt/boty/testy-audytu/test-awaria-zapisu.php` na `main` 766b4c9 (stos = main, diff pusty)
     dało `ZDANYCH 3/6, PADA 3`, EXIT=1: T1 (A2-F1), T3 (A2-F2), T5 (A2-F3) PADAJĄ. PR #136 (e28b825)
     zmienił tylko `chatl_log_bledu_bazy`/`segm_log_bledu_bazy` — nie dotknął `chatl_post_ustawienia_zapisz()`,
     `przejecie.php` ani `ai.php` (`git show e28b825 --name-only`). Status ZAMKNIĘTE był pomyłką
     przy zamykaniu rejestru (zamknięto sąsiedni problem z tego samego raportu). Lekcja: status
     ZAMKNIĘTE nadaje się po URUCHOMIENIU testu, nie po scaleniu PR-a o podobnym tytule. -->

## Znalezisk BEZ strażnika: 0
<!-- 2.09: CZ-1 dostało strażnika — dług strażników rejestru sklepu = 0. -->
<!-- BRAMKA: liczba wierszy, ktore w kolumnie testu maja „BRAK — do dopisania".

     ⭐ PODNIESIONE ŚWIADOMIE 24.08 (wieczór) z 0 na 1: CZ-1 z przelotu człowieka — strażnik
     powstanie razem z naprawą (komunikat limitu), zapisane w wierszu.

     ✅ SPŁACONE 24.08 z 11 na 0: każda paczka napraw przyniosła strażnika z kalibracją
     (EXIT=1 na kodzie sprzed naprawy, EXIT=0 po) — ścieżki w kolumnie testu każdego wiersza;
     ostatni dług (A6-F2) zamknął strażnik testy/material-audytu-zgodnosc.sh (24.08).
     To NIE jest usterka rejestru — to DLUG. Znalezisko dowiedzione wykonaniem, ale bez
     straznika, ktory by pilnowal, zeby nie wrocilo. Prowadzimy go tak samo jak dlug sladow
     w sklepie: schodzil 41 -> 40, nigdy w druga strone.
     ⭐ Ta liczba ma prawo TYLKO MALEC. Bramka pada przy rozjezdzie w OBIE strony:
     wzrost = ktos dopisal znalezisko bez straznika po cichu; spadek = albo straznik
     powstal (obniz liczbe swiadomie), albo ktos skasowal wiersz znaleziska.
     ⛔ Wiersz wskazujacy nieistniejacy plik o KONKRETNEJ nazwie to co innego — to twardy
     blad bramki, bo martwy odsylacz UDAJE, ze straznik stoi.

     Stan 21.08 wieczor: dziewiec bez straznika — A4-F1..F4, A6-F2, LC-F2, P2-Z1, P2-Z2, P2-Z4.
     LC-F1 wypadlo (OBALONE, sekcja nizej). Przebieg 2 nie zostawil ANI JEDNEGO pliku testu.
     ⛔ PODNIESIONE ŚWIADOMIE 23.08 z 9 na 11: linia bazowa testów (`narzedzia-qa/linia-bazowa.sh`,
     HEAD 766b4c9) pokazała, że `audyt/boty/repro/lamacz-cofanie.php`, `repro/lamacz-baza-i-srodowisko.php`,
     `testy-audytu/a4-przejecie-nie-dochodzi.js` i `a4-zamkniecie-odpytywanie.js` to SONDY: odtwarzają
     błąd i wypisują wynik, ale kończą się EXIT=0 ZAWSZE (zero `exit(1)` w kodzie). Sonda nie jest
     strażnikiem — po naprawie nic by w niej nie „zaczęło przechodzić". Dwa z tych wierszy (LS-F1, LS-F2)
     były wpisane jako „test (dowód)" i zaniżały dług. Strażnik = NOWY plik w `testy/` pisany przy
     naprawie (plan §3: [N]); sondy zostają jako dowód i odtworzenie.
     Raporty A4 i Lamacz ChatBot dowiodly swoich znalezisk komendami wpisanymi w terminal
     i przegladarka (curl do admin-ajax.php, konsola JS) — nie zostawily po sobie pliku. -->

## Kody — jak czytać
Każdy raport numerował swoje znaleziska od nowa i cztery raporty użyły tych samych
kodów `F1`–`F4` na różne błędy. Kody w tabeli mają więc **prefiks soczewki**, a numer
został oryginalny, żeby dało się wrócić do raportu:

- **`A2-`** — awaria · `RAPORT-A1-BEZPIECZENSTWO.md` · `RAPORT-PRZEBIEG2-CHATBOT.md` · `RAPORT-A2-AWARIA.md`, w raporcie `F1`–`F4`
- **`A3-`** — dane · `RAPORT-A3-DANE.md`, w raporcie `A3-1`, `A3-2` (już jednoznaczne, zostawione bez zmian)
- **`KR`** — krawędzie · `RAPORT-A5-KRAWEDZIE.md`, w raporcie `KR1`–`KR6` (już jednoznaczne, zostawione bez zmian)
- **`A4-`** — front i używalność · `RAPORT-A4-FRONT.md`, w raporcie `F1`–`F4`
- **`A6-`** — dokumentacja odwrotnie · `RAPORT-A6-ODWROTNY.md`, w raporcie `A6-1`, `A6-2`
- **`LC-`** — Łamacz ChatBot · `RAPORT-LAMACZ-CHATBOT.md`, w raporcie `Ł-1`, `Ł-2`
- **`LS-`** — Łamacz Segregator · `RAPORT-LAMACZ-SEGREGATOR.md`, w raporcie `Ł-A` → `LS-F1`, `Ł-B` → `LS-F2`

⛔ Ten spis jest **listą, nie tabelą**, celowo: wiersz tabeli zaczynający się od kodu w grawisach
jest dla `bramka-znaleziska.sh` wierszem ZNALEZISKA. Pierwsza wersja tego rejestru miała tu tabelę
i bramka policzyła siedem nieistniejących znalezisk z nieznanym statusem.

## Znaleziska

| kod | co | test (dowód) | status |
|---|---|---|---|
| `A2-F1` | ChatBot melduje „Ustawienia zapisane", gdy baza zapis odrzuciła — **naprawa C1 #155; zamrożony test-awaria-zapisu.php 6/6** | `testy/chatbot-awaria-zapisu-test.php` | ZAMKNIETE |
| `A2-F2` | `chatl_przejmij()` zwraca `true` nie sprawdziwszy zapisu; gość dostaje nieprawdę — **naprawa C1 #155** | `testy/chatbot-awaria-zapisu-test.php` | ZAMKNIETE |
| `A2-F3` | zapis klucza API bez pomiaru — stary klucz zostaje, nikt się nie dowiaduje — **naprawa C1 #155; bliźniak Segregatora: S2 #159, segregator-awaria-zapisu-test.php** | `testy/chatbot-awaria-zapisu-test.php` | ZAMKNIETE |
| `A3-1` | `chatl_sesje` — tabela bez żadnej ścieżki kasowania, poza kasownikiem RODO — **naprawa C6 #167** | `testy/chatbot-sesje-retencja-rodo-test.php` | ZAMKNIETE |
| `A3-2` | zombie cron ChatBota zostaje po odinstalowaniu wtyczki — **naprawa C6 #167** | `testy/uninstall-zombie-cron-test.php` | ZAMKNIETE |
| `KR1` | puste pole „dobowy limit tokenów" wyłącza drugi stop-loss kosztowy, po cichu — **naprawa C2 #158; test-krawedzie.php 1→0** | `testy/chatbot-stoploss-awaria-test.php` | ZAMKNIETE |
| `KR2` | puste pole „dobowy limit wywołań" zjeżdża limit do 1, po cichu — **naprawa C2 #158; test-krawedzie.php 1→0** | `testy/chatbot-stoploss-awaria-test.php` | ZAMKNIETE |
| `KR4` | puste pole „port"/„limit" skrzynki IMAP daje port 1 i limit 1, po cichu — **naprawa S2 #159; test-krawedzie.php 1→0** | `testy/segregator-awaria-zapisu-test.php` | ZAMKNIETE |
| `KR6` | retencja ChatBota kasuje rozmowy PRZED obiecanymi 90 dniami w strefie ujemnej — **naprawa C2 #158; test 1→0 w linii bazowej** | `audyt/boty/testy-audytu/test-krawedzie.php` | ZAMKNIETE |
| `A4-F1` | gość NIE dowiaduje się, że do rozmowy dołączył żywy konsultant — okno czatu zamiera — **naprawa C4 #164; kalibracja origin/main EXIT=1** | `testy/chatbot-widget-odpytywanie-test.js` | ZAMKNIETE |
| `A4-F2` | zamknięcie czatu bąbelkiem wywala ten sam błąd, druga gałąź tej samej wady — **naprawa C4 #164, sekcja zamknięcia** | `testy/chatbot-widget-odpytywanie-test.js` | ZAMKNIETE |
| `A4-F3` | cztery pola panelu nie mają żadnej nazwy dla czytnika ekranu (WCAG poziom A) — **pola Segregatora: S4 #163; pola ChatBota: C5 #166, dowód w opisie PR** | `testy/segregator-komunikaty-test.php` | ZAMKNIETE |
| `A4-F4` | przełącznik czatu nie mówi czytnikowi ekranu, czy jest otwarty (WCAG poziom A) — **naprawa C4 #164, sekcje aria-expanded** | `testy/chatbot-widget-odpytywanie-test.js` | ZAMKNIETE |
| `A6-F1` | deklaracja prywatności ChatBota nie wymienia wszystkiego, co naprawdę wychodzi do API — **naprawa C5 #166; w linii bazowej 1→0** | `testy/a6-deklaracja-vs-ladunek-ai.php` | ZAMKNIETE |
| `A6-F2` | materiał audytowy twierdzi, że dwie największe funkcje botów nie istnieją — każdy następny audyt startuje z fałszywym obrazem produktu — **materiał poprawiony 24.08; kalibracja: stara wersja → EXIT=1, 5 BLAD** | `testy/material-audytu-zgodnosc.sh` | ZAMKNIETE |
| `LC-F2` | ścieżka pułapki jest całkowicie nielimitowana i każde żądanie pisze do `wp_options` — ⭐ **POTWIERDZONE NIEZALEŻNIE** przez przebieg 2 (jego `Z3`): 30/30 żądań przeszło przy progu 10 — **naprawa C3 #162 — kubły pulapka i nonce-odrzucony** | `testy/chatbot-pulapka-licznik-test.php` | ZAMKNIETE |
| `P2-Z1` | „Przetestuj klucz" pyta o inną stałą niż bot — właściciel z własnym modelem widzi zielone „Klucz działa" przy martwym trybie AI, a przycisk przy okazji kasuje jedyny pasek o awarii — **naprawa C5 #166** | `testy/chatbot-test-klucza-test.php` | ZAMKNIETE |
| `P2-Z2` | opcja `chatl_pulapka` przeżywa pełne odinstalowanie, choć dokumentacja obiecuje pustą różnicę bazy — **naprawa C3 #162 — trzy miejsca schematu i uninstall** | `testy/chatbot-pulapka-licznik-test.php` | ZAMKNIETE |
| `P2-Z4` | paczka RODO opisuje wiadomości **operatora** jako „Bot" — dokument wydawany na wniosek art. 15 mówi co innego niż okno czatu — **naprawa C5 #166** | `testy/chatbot-rodo-role-test.php` | ZAMKNIETE |
| `LS-F1` | cofnięcie przebiegu melduje sukces dla maili, których nie ruszyło, i zamyka drogę odwrotu — **naprawa S1 #156; kalibracja 8c87c06 EXIT=1; sonda lamacz-cofanie.php zostaje dowodem** | `testy/segregator-cofanie-test.php` | ZAMKNIETE |
| `LS-F2` | baza pada w połowie przebiegu na żywo: poczta przeniesiona, historia pusta, cofanie martwe, panel pokazuje sukces — **naprawa S1 #156; kalibracja 8c87c06 → 7 BLAD; zamrożona sonda lamacz-baza-i-srodowisko.php poza linią bazową** | `testy/segregator-przebieg-historia-test.php` | ZAMKNIETE |
| `DK-1` | licznik pułapki i lista podejrzanych zgłoszeń to **dwa źródła prawdy** o tym samym zdarzeniu — rozjeżdżają się 14 do 5 — **naprawa C3 #162; 1→0** | `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | ZAMKNIETE |
| `DK-5` | licznik trzymany w `wp_options` **gubi 13 z 20 równoległych zapisów** (ten sam licznik w tabeli gubi 0) — **naprawa C3 #162; przyrząd zaktualizowany 24.08 na tabelę zdarzeń, EXIT=0** | `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | ZAMKNIETE |
| `CZ-1` | pytanie gościa dłuższe niż 500 znaków jest przycinane PO CICHU (`front.php:504` mb_substr) — gość nie wie, że bot dostał ucięty tekst; działa bezpiecznie, ale myli (przelot człowieka 24.08, sekcja A) — **NAPRAWIONE 2.09:** serwer odmawia (`za_dlugie`, 413, komunikat z liczbą znaków i limitem) zamiast `mb_substr`; widget: licznik od 400 znaków, wklejka ponad limit skracana z ostrzeżeniem (pole z `maxLength` nie wyśle ponad 500), przy odmowie serwera treść zostaje w polu; kalibracja `testy/chatbot-pytanie-limit-proba-kontrolna.sh` (kod z main tnie po cichu); limit wiadomości operatora pilnuje osobny strażnik `testy/chatbot-operator-limit-trzyma-tresc-test.php` (szkic wraca do pola) | `testy/chatbot-pytanie-limit-test.php` | ZAMKNIETE |
| `DK-6` | zamek Segregatora wpuszcza **dwie operacje naraz**, a znacznik drugiej nadpisuje pierwszą — **naprawa S1 #156; 1→0 + segregator-zamek-ttl-test.php** | `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | ZAMKNIETE |
| `CZ-2` | **odrzut walidacji kasował całą wpisaną treść w formularzu PRODUKTU** (nazwa, oba opisy, cena). Handler stoi za `admin-post` i kończy błąd przekierowaniem, więc formularz rysował się od nowa z bazy — właściciel, który zapomniał paczki, pisał opis drugi raz. Przelot człowieka po sklepie 27.08, potwierdzone trzykrotnie — **naprawa 0.9.19; kalibracja: BLEDY=7 na kodzie sprzed naprawy, 0 po** | `testy/produkt-formularz-trzyma-tresc-po-odrzucie-test.php` | ZAMKNIETE |
| `CZ-3` | **to samo w formularzu OPINII kupującego** (bliźniak CZ-2, klasa 8): przepadało do 2000 znaków, których — inaczej niż właścicielowi — nikt kupującemu nie odtworzy. Znalezione przy domykaniu CZ-2, nie w przelocie — **naprawa 0.9.19; kalibracja: BLEDY=5 na kodzie sprzed naprawy, 0 po. Zgoda na publikację celowo NIE jest odtwarzana** | `testy/opinia-formularz-trzyma-tresc-po-odrzucie-test.sh` | ZAMKNIETE |
| `CZ-4` | **nagranie w galerii nie miało ŻADNEJ drogi z klawiatury** ani widocznego sygnału: przycisk odtwarzania miał `hidden` do czasu odrzucenia autoodtwarzania, a element `display:none` wypada z kolejności Tab — więc ruchu dłuższego niż 5 s nie dało się zatrzymać. Przelot człowieka 27.08 — **naprawa 0.9.19; kalibracja trójstopniowa: BLEDY=2 bez przycisku, BLEDY=4 z przyciskiem bez ochrony pauzy, 0 po pełnej naprawie** | `testy/e2e/kafel-wideo-klawiatura.js` | ZAMKNIETE |
| `M-1` | **EGZAMIN 6.09 (Mariusz, DUŻY): opłacony zakup mógł utknąć w `oczekuje` na zawsze** — wynik UPDATE `oczekuje→oplacone` w `sklepag_domknij_zakup()` ignorowany, kontrola sprawdzała istnienie wiersza, sesja oznaczana `oplacona` przed dowodem statusu (reconciliation ślepe, właściciel bez maila). Klasa 163. **Naprawa 0.9.44:** wynik czytany, status sprawdzany przed flagą sesji, przy błędzie zdarzenie + mail + 500 (Stripe/reconciliation ponawiają i domykają). Kalibracja: test przed naprawą FAIL (sesja `oplacona`, ślad 0, maile 0), po naprawie PASS ×3 scenariusze; **r5:** scenariusz INNY_SILNIK (padnięty UPDATE przy zamówieniu domkniętym przez inny silnik → ślad `domkniecie_przejscie_inny_silnik`, 0 maili; przed r4 FAIL, po PASS), powód alarmu z zapamiętanego błędu bazy | `testy/domkniecie-update-statusu-padl-test.php` | ZAMKNIETE |
| `M-2` | **EGZAMIN 6.09 (Mariusz, ŚREDNI): dwa równoległe zwroty gubiły jeden (lost update na `zwrocono_grosze`)** — odczyt-dodanie-zapis bez warunku; to samo odsiewanie duplikatów. Klasa 164 (robotnik próby: `testy/pomocnicze/zwrot-rownolegly-worker.php`). **Naprawa 0.9.44:** zamek nazwany bazy na zamówienie (`GET_LOCK`) wokół księgowania zwrotu i sporu + świeży odczyt po zamku. Kalibracja: wymuszone okno wyścigu (A śpi przed UPDATE-em) przed naprawą 3000/5000, po naprawie 5000/5000; duplikat równoległy 3000. **r4 recenzji (bliźniak na `status`):** zapis zwrotu bez warunku na status nadpisywał `mail_wyslany` z silnika domykania → `AND status=%s` na 3 zapisach; scenariusz STATUS_INNY_SILNIK przed: status cofnięty do `mail_w_toku` (FAIL), po: `mail_wyslany`, zwrot wstrzymany i zaksięgowany przy ponowieniu (PASS). **r5:** 0 wierszy = dowód SKUTKU (`sklepag_zapis_bez_skutku`), scenariusze STATUS_A_B_A (r4: `-> true`, ślad 1, kwota 0 = FAIL; r5: PASS) i SPOR_INNY_SILNIK (przed r4 FAIL, po PASS); nazwa zamka z sygnaturą bazy i prefiksu | `testy/zwrot-rownolegly-test.sh` | ZAMKNIETE |
| `G-1` | **`charge.refunded` przed `refund.created` tego samego zwrotu liczyło zwrot dwa razy** (znalezione przy sprawdzaniu M-2; Stripe nie gwarantuje kolejności zdarzeń). Klasa 165. **Naprawa 0.9.44:** identyfikatory z `refunds.data` w śladzie + reguła „suma z charge już pokrywa”. Kalibracja: przed naprawą 6000 i 7000, po naprawie 3000 i 5000 (4 scenariusze, w tym dwa różne zwroty w obu kolejnościach) | `testy/zwrot-charge-przed-refund-test.php` | ZAMKNIETE |

## Statusy — co znaczą
- **OTWARTE** — błąd potwierdzony dowodem, kod nienaprawiony. Test **pada** i tak ma być.
- **W NAPRAWIE** — naprawa istnieje w niescalonym pull requeście. Po scaleniu → ZAMKNIĘTE.
- **ZAMKNIĘTE** — kod naprawiony, test **przechodzi** i staje się strażnikiem regresji.
  ⛔ Test ZAMKNIĘTEGO znaleziska nigdy się nie kasuje — od tej chwili pilnuje, żeby nie wróciło.

⚠️ **Uwaga do `LS-F2`** (historyczna, sprzed S1): naprawa w locie zamykała **jeden z czterech**
punktów tego znaleziska. ✅ ROZSTRZYGNIĘTE 24.08 osobną decyzją koordynatorki: S1 (#156)
domknął pozostałe punkty — zapis zamiaru PRZED ruchem poczty, raport przebiegu i historia
mówią prawdę przy awarii, cofanie liczy tylko wrócone. Dowód: strażnik
`testy/segregator-przebieg-historia-test.php` (kalibracja: kod sprzed S1 → 7 BLAD/EXIT=1,
po S1 → EXIT=0). Zamrożona sonda `lamacz-baza-i-srodowisko.php` została dowodem w git,
wyłączona z linii bazowej (woła kształt kodu sprzed S1 → FATAL).

## Raporty objęte rejestrem
`RAPORT-A2-AWARIA.md` · `RAPORT-A3-DANE.md` · `RAPORT-A5-KRAWEDZIE.md` ·
`RAPORT-A4-FRONT.md` · `RAPORT-A6-ODWROTNY.md` ·
`RAPORT-LAMACZ-CHATBOT.md` · `RAPORT-LAMACZ-SEGREGATOR.md` · `RAPORT-A1-BEZPIECZENSTWO.md` · `RAPORT-PRZEBIEG2-CHATBOT.md` · `RAPORT-DOKONCZENIE-A3-A5.md`
<!-- BRAMKA: każdy plik audyt/boty/RAPORT-*.md musi być wymieniony w linii wyżej.
     Cztery ostatnie leżą jeszcze na niescalonych gałęziach audytorskich
     (audyt/a4-front, audyt/a6-odwrotny, audyt/lamacz-chatbot, audyt/lamacz-segregator)
     — są wymienione z góry, żeby ich scalenie nie zatrzymało bramki. -->

---

## ⛔ OBALONE — poza tabelą i poza licznikiem, ale ZOSTAJE jako ślad

**`LC-F1` — „pierwsza wiadomość każdego gościa ginie w pułapce na boty" — OBALONE.**
Weryfikator zmierzył w prawdziwej przeglądarce: sesja powstaje **przy kliknięciu bąbelka**
(`widget.js:527` w `start()`), nie przy wysyłce, więc zegar pułapki liczy cały czas pisania.
Odstęp otwarcie→wysłanie wyszedł **2,73 s**, wiadomość doszła, rozmowa się zapisała.
Autor przejechał się na stanowisku: `curl` strzelający dwa żądania po sobie **z konstrukcji**
odtwarzał tezę „sesja rodzi się w chwili wysłania".
⚠️ **Co z obawy zostaje:** wysłanie poniżej ~1,9 s od otwarcia rzeczywiście gubi wiadomość po cichu —
ale uzyskano to programowym kliknięciem przy **0,155 s**; człowiek nie napisze pytania w tym czasie.
⭐ Wpis zostaje, bo obalone znalezisko jest tak samo cenne jak potwierdzone: uratowało nas przed
naprawianiem czegoś, czego nie ma. ⛔ Nie liczy się do OTWARTYCH ani do długu strażników.

## ⚠️ ANOMALIE — poza tabelą i poza licznikiem

⛔ To **nie są znaleziska**: żadna nie jest błędem dowiedzionym wykonaniem. Nie liczą się
do licznika OTWARTYCH i nie mają strażników. Są tu, żeby się nie zgubiły — z każdej może
kiedyś wyrosnąć znalezisko, jeśli ktoś je domknie wykonaniem.

- **`A2-F4` — sześć dalszych miejsc tej samej klasy, NIEURUCHOMIONE.** Raport A2 wypisał pełny
  spis miejsc, gdzie zapis do bazy idzie bez sprawdzenia wyniku (m.in. znacznik awarii trybu AI
  w obu wtyczkach, ślad wniosku RODO, licznik pułapki). **Odczyt kodu, nie pomiar.**
  Część tego terenu ruszył #136 — nikt nie sprawdził, ile z tej szóstki naprawdę zostało.
- **A3 — szkody uboczne pomiaru na współdzielonym stosie.** Audyt danych zostawił po sobie stan
  do posprzątania przez koordynatora; opisany w raporcie A3.
- **A3 — wada własnego testu audytora, złapana i naprawiona w trakcie.** Zamknięta w raporcie,
  zapisana jako ostrzeżenie o przyrządzie.
- **A5 — dwie wady przyrządu audytora krawędzi, złapane w trakcie.** Obie opisane w raporcie;
  pokazują, jak łatwo ten pomiar daje fałszywy alarm.
- **A4 — bez JavaScriptu nie ma ŻADNEJ ścieżki kontaktu ani śladu, że czat istnieje.** Gość
  z wyłączonym JS nie widzi bąbelka i nie ma czym zostawić wiadomości. Raport zaznacza to jako
  anomalię, nie błąd — zamówienie nie rozstrzyga, czy widget ma działać bez JS.
- **A6 — trzy własne tropy, które audytor ODRZUCIŁ.** Zapisane celowo: pokazują, gdzie ta metoda
  produkuje fałszywe alarmy (dwa razy winna była regexa audytora, nie produkt).

## 🔎 DO ODTWORZENIA — poza tabelą i poza licznikiem (etap napraw, 23.08)

⛔ To **nie są znaleziska** w sensie tej tabeli: nie mają jeszcze PADAJĄCEGO TESTU. Pochodzą z dziennika
kupującego (`audyt/boty/WCIELENIE-KUPUJACY.md`, prefiks `WK-`), z sekcji „czego nie było na mojej liście"
raportów i z krytyki planu (prefiks `T-`), oraz z TABLICY („czekające na decyzję"). Builder przy swojej paczce
(`audyt/PLAN-NAPRAW.md` §3) NAJPIERW odtwarza: jeśli dostanie padający test albo twardy dowód wykonania —
koordynatorka awansuje pozycję do tabeli wyżej (PR z EXIT=1 w opisie, licznik podniesiony świadomie).
Jeśli nie pada — zostaje tu adnotacja „nie odtworzono" i sprawa jest zamknięta. Nic stąd nie liczy się
do OTWARTYCH ani do długu strażników.

- `WK-1` (ChatBot, paczka C4) — formularz kontaktowy NAKŁADA się na powitanie; krzyżyk formularza zamyka CAŁY czat i gubi wpisaną treść (dziennik 19:48, 19:49, „okno małe i wszystko się nakłada" — jedna przyczyna układu, trzy objawy). **→ ✅ C4 (#164): sprawdzone i NIE ODTWORZONE; asercje układu zostały w strażniku — zamknięte.**
- `WK-3` (Segregator, S4) — świeżo zainstalowana wtyczka wita błędem „KLUCZ API ZOSTAŁ ODRZUCONY" o kluczu, którego właściciel nie wpisywał (19:54); brak klucza ≠ odrzucony klucz; komunikat zakłada winę użytkownika. **→ ✅ weszło w S4 (#163), strażnik `testy/segregator-komunikaty-test.php`.**
- `WK-4` (Segregator, S4) — tabelka zgodności serwera: „✔ jest" obok „Nieobecne" w jednej linii (19:55). **→ ✅ weszło w S4 (#163), strażnik `testy/segregator-komunikaty-test.php`.**
- `WK-5` (Segregator, S3) — po wypełnieniu formularza skrzynki „Sprawdź połączenie" zostaje szare bez słowa, że najpierw trzeba zapisać (19:56). **→ ✅ weszło w S3 (#161), strażnik `testy/segregator-ekran-skrzynki.js`.**
- `WK-8` (ChatBot, C4) — „dzień dobry" dostaje „nie znam odpowiedzi" (19:48). ⛔ Zamówienie (§FRONT) każe „nie znam odpowiedzi" i zakazuje poszerzania dopasowania — naprawa = WPIS POWITALNY w danych startowych/instrukcji, NIE zmiana silnika. **→ ✅ weszło w C4 (#164): jedenasty wpis danych startowych (silnik nietknięty), dowód w opisie PR.**
- `WK-13` (Segregator, S4) — „Connection refused (kod 111)": żargon w komunikacie dla laika (19:57). **→ ✅ weszło w S4 (#163), strażnik `testy/segregator-komunikaty-test.php`.**
- `WK-15` (Segregator, S3) — ekran skrzynki nie wyjaśnia, czym jest „hasło aplikacji" ani skąd je wziąć (19:55). **→ ✅ weszło w S3 (#161), strażnik `testy/segregator-ekran-skrzynki.js`.**
- `WK-17` (Segregator, S3) — po „Zapisz połączenie" kupujący nie zobaczył potwierdzenia (19:56). ⚠️ Komunikat „Zapisano ustawienia połączenia…" ISTNIEJE w kodzie (`admin-skrzynka.php`, zapamiętanie ~:472, wyświetlenie ~:97) — do odtworzenia jest DLACZEGO go nie widać, nie „dodanie komunikatu". **→ ✅ weszło w S3 (#161): odtworzone (komunikat tonął pod cudzymi paskami), strażnik `testy/segregator-ekran-skrzynki.js` wymaga, żeby był PIERWSZYM paskiem wtyczki.**
- `T-1` (ChatBot, C2) — fail-open kosztowy: `chatl_ai_zuzycie_dnia()` (`ai.php:129-150`) przy BŁĘDZIE zapytania dostaje `null` tak samo jak przy braku wiersza → zwraca zera → `chatl_ai_uzycie_dzis() >= limit` jest fałszywe → AI wołane przy padniętej tabeli licznika. Odczyt kodu (krytyka planu 23.08, zweryfikowany), nie pomiar — builder pisze test symulujący błąd zapytania filtrem `query` (jak `test-awaria-zapisu.php`). **→ ✅ weszło w C2 (#158): odtworzone i naprawione, strażnik `testy/chatbot-stoploss-awaria-test.php` (sekcja B).**
- `T-3` (Segregator, S1) — `UIDVALIDITY` odczytywane i WYRZUCANE (raport Łamacza Segregatora, „czego nie było na liście" pkt 2) — przyczyna źródłowa ostrzejszego wariantu LS-F1 (folder skasowany i założony na nowo). Naprawiane razem z LS-F1. **→ ✅ weszło w S1 (#156): kolumna `uidvalidity_do`, strażnik `testy/segregator-cofanie-test.php`.**
- `T-4` (Segregator, S1) — zamek przebiegu po zabitym procesie zostaje NA ZAWSZE i cicho wyłącza cron (raport A2, „czego nie było na liście" pkt 2). Naprawiane razem z DK-6 (TTL zamka). **→ ✅ weszło w S1 (#156), strażnik `testy/segregator-zamek-ttl-test.php`.**
- `A3-3` (Segregator, S5) — TABLICA „czekające na decyzję" #1: w trybie AI Segregator wysyła nadawców i tematy CUDZYCH maili do dostawcy AI; deklaracja tego nie mówi wprost. Fakt (23.08): AI NIE działa na przebiegu skrzynki (same reguły) — `segm_ai_zapytaj()` woła `silnik.php` (próbka/klasyfikacja). Naprawa = deklaracja mówi prawdę + test „deklaracja vs ładunek" jak `testy/a6-deklaracja-vs-ladunek-ai.php`. Decyzja L8 (czy AI wchodzi na skrzynkę) należy do właściciela. **→ ✅ weszło w S5 (#165): deklaracja i panel mówią prawdę („na skrzynce SAME REGUŁY"); strażnik `testy/segregator-deklaracja-vs-ladunek-ai.sh` (wrapper wgrywa dokumenty Z REPO; silnik `.php` wołany wyłącznie przez niego). Decyzja L8 NADAL u właściciela — rekomendacja: zostaje napis „same reguły".**

### Świadomie NIE naprawiane w tym etapie (propozycje → `boty/PONAD-SPECYFIKACJE-BOTY.md`)
`<noscript>` z e-mailem (wyciek adresu do HTML, anomalia nie wada) · WK-9 „jeden komunikat naraz" (polityka UX, rozszerzenie) ·
WK-11/WK-18 dymki walidacji HTML5 po angielsku (język PRZEGLĄDARKI stanowiska en-US, nie produktu) · WK-16 stan crona w panelu
(JUŻ JEST: „Najbliższy przebieg…" / „zadanie NIE jest zaplanowane") · T-2 ostrzeżenie `DISABLE_WP_CRON` w ChatBocie (ma Segregator; nie przesadzamy) ·
minimalizacja danych wysyłanych do AI (domena zamiast adresu) — po oddaniu · `chatl_rate_limit()` `return true` przy awarii licznika — limiter SPAMU, świadome (komentarz w kodzie), zostaje.

## Czego ta bramka NIE robi — granica, świadomie
⛔ **Nie uruchamia testów.** Wszystkie 23 skrypty i 18 testów PHP wymagają żywego stosu
Dockera — psują kod w kontenerze i sprawdzają, czy strażnik pada. Uruchamianie tego
w CI biegnącym równolegle na tej samej maszynie oznaczałoby, że przeloty depczą sobie po stosie.
Bramka pilnuje **księgowości znalezisk**: że istnieją, mają test, mają status i nikt ich
po cichu nie usunął. Wykonanie testów zostaje przy człowieku i przy recenzencie.
