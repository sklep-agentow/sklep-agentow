# 📋 REJESTR ZNALEZISK AUDYTU — jedno źródło prawdy

> ⛔ **Pilnowany maszynowo** przez `narzedzia-qa/bramka-znaleziska.sh`.
> Nowy raport audytu bez wpisu tutaj **zatrzymuje bramkę**. Znalezisko bez testu też.
>
> Powód powstania (21.08): mieliśmy zasadę „każdy błąd dostaje strażnika z exit 1 wpięty
> do CI" i **nie była spełniona ani razu**. W `testy/` leżało 57 plików, CI nie uruchamiało
> żadnego. PR #135 wniósł dwa celowo padające testy — i przeszedł na zielono.

## Liczba znalezisk OTWARTYCH: 20
<!-- BRAMKA: powyższa liczba musi zgadzać się z liczbą wierszy o statusie OTWARTE.
     Dopisanie znaleziska ZATRZYMUJE bramkę, dopóki ktoś świadomie nie podniesie liczby.
     ⭐ Ta liczba ma prawo TYLKO MALEĆ. Jeśli rośnie — to znaczy, że audyt znalazł coś nowego,
     i to jest w porządku; ale musi być świadome, nie po cichu.

     ⭐ PODNIESIONE ŚWIADOMIE 21.08 z 6 na 15: audyt botów się skończył i doszły znaleziska
     z czterech raportów, których wcześniej tu nie było (A4 front, A6 dokumentacja odwrotnie,
     Łamacz ChatBot, Łamacz Segregator). Nic nie zniknęło — trzy dawne wiersze `F1`/`F2`/`F3`
     przeszły na ZAMKNIETE (scalone #136, e28b825), więc wypadły z licznika OTWARTYCH. -->

## Znalezisk BEZ strażnika: 9
<!-- BRAMKA: liczba wierszy, ktore w kolumnie testu maja „BRAK — do dopisania".
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
| `A2-F1` | ChatBot melduje „Ustawienia zapisane", gdy baza zapis odrzuciła | `audyt/boty/testy-audytu/test-awaria-zapisu.php` | ZAMKNIETE |
| `A2-F2` | `chatl_przejmij()` zwraca `true` nie sprawdziwszy zapisu; gość dostaje nieprawdę | `audyt/boty/testy-audytu/test-awaria-zapisu.php` | ZAMKNIETE |
| `A2-F3` | zapis klucza API bez pomiaru — stary klucz zostaje, nikt się nie dowiaduje | `audyt/boty/testy-audytu/test-awaria-zapisu.php` | ZAMKNIETE |
| `A3-1` | `chatl_sesje` — tabela bez żadnej ścieżki kasowania, poza kasownikiem RODO | `testy/chatbot-sesje-retencja-rodo-test.php` | OTWARTE |
| `A3-2` | zombie cron ChatBota zostaje po odinstalowaniu wtyczki | `testy/uninstall-zombie-cron-test.php` | OTWARTE |
| `KR1` | puste pole „dobowy limit tokenów" wyłącza drugi stop-loss kosztowy, po cichu | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `KR2` | puste pole „dobowy limit wywołań" zjeżdża limit do 1, po cichu | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `KR4` | puste pole „port"/„limit" skrzynki IMAP daje port 1 i limit 1, po cichu | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `KR6` | retencja ChatBota kasuje rozmowy PRZED obiecanymi 90 dniami w strefie ujemnej | `audyt/boty/testy-audytu/test-krawedzie.php` | OTWARTE |
| `A4-F1` | gość NIE dowiaduje się, że do rozmowy dołączył żywy konsultant — okno czatu zamiera | BRAK — do dopisania | OTWARTE |
| `A4-F2` | zamknięcie czatu bąbelkiem wywala ten sam błąd, druga gałąź tej samej wady | BRAK — do dopisania | OTWARTE |
| `A4-F3` | cztery pola panelu nie mają żadnej nazwy dla czytnika ekranu (WCAG poziom A) | BRAK — do dopisania | OTWARTE |
| `A4-F4` | przełącznik czatu nie mówi czytnikowi ekranu, czy jest otwarty (WCAG poziom A) | BRAK — do dopisania | OTWARTE |
| `A6-F1` | deklaracja prywatności ChatBota nie wymienia wszystkiego, co naprawdę wychodzi do API | `testy/a6-deklaracja-vs-ladunek-ai.php` | OTWARTE |
| `A6-F2` | materiał audytowy twierdzi, że dwie największe funkcje botów nie istnieją — każdy następny audyt startuje z fałszywym obrazem produktu | BRAK — do dopisania | OTWARTE |
| `LC-F2` | ścieżka pułapki jest całkowicie nielimitowana i każde żądanie pisze do `wp_options` — ⭐ **POTWIERDZONE NIEZALEŻNIE** przez przebieg 2 (jego `Z3`): 30/30 żądań przeszło przy progu 10 | BRAK — do dopisania | OTWARTE |
| `P2-Z1` | „Przetestuj klucz" pyta o inną stałą niż bot — właściciel z własnym modelem widzi zielone „Klucz działa" przy martwym trybie AI, a przycisk przy okazji kasuje jedyny pasek o awarii | BRAK — do dopisania | OTWARTE |
| `P2-Z2` | opcja `chatl_pulapka` przeżywa pełne odinstalowanie, choć dokumentacja obiecuje pustą różnicę bazy | BRAK — do dopisania | OTWARTE |
| `P2-Z4` | paczka RODO opisuje wiadomości **operatora** jako „Bot" — dokument wydawany na wniosek art. 15 mówi co innego niż okno czatu | BRAK — do dopisania | OTWARTE |
| `LS-F1` | cofnięcie przebiegu melduje sukces dla maili, których nie ruszyło, i zamyka drogę odwrotu | `audyt/boty/repro/lamacz-cofanie.php` | OTWARTE |
| `LS-F2` | baza pada w połowie przebiegu na żywo: poczta przeniesiona, historia pusta, cofanie martwe, panel pokazuje sukces | `audyt/boty/repro/lamacz-baza-i-srodowisko.php` | W NAPRAWIE |
| `DK-1` | licznik pułapki i lista podejrzanych zgłoszeń to **dwa źródła prawdy** o tym samym zdarzeniu — rozjeżdżają się 14 do 5 | `audyt/boty/testy-audytu/DK-dwa-zrodla-pulapka.sh` | OTWARTE |
| `DK-5` | licznik trzymany w `wp_options` **gubi 13 z 20 równoległych zapisów** (ten sam licznik w tabeli gubi 0) | `audyt/boty/testy-audytu/DK-rownolegle-liczniki.sh` | OTWARTE |
| `DK-6` | zamek Segregatora wpuszcza **dwie operacje naraz**, a znacznik drugiej nadpisuje pierwszą | `audyt/boty/testy-audytu/DK-blokada-segregatora.php` | OTWARTE |

## Statusy — co znaczą
- **OTWARTE** — błąd potwierdzony dowodem, kod nienaprawiony. Test **pada** i tak ma być.
- **W NAPRAWIE** — naprawa istnieje w niescalonym pull requeście. Po scaleniu → ZAMKNIĘTE.
- **ZAMKNIĘTE** — kod naprawiony, test **przechodzi** i staje się strażnikiem regresji.
  ⛔ Test ZAMKNIĘTEGO znaleziska nigdy się nie kasuje — od tej chwili pilnuje, żeby nie wróciło.

⚠️ **Uwaga do `LS-F2`:** naprawa w locie zamyka **jeden z czterech** punktów tego znaleziska
(właściciel dowie się, że zapis padł). Raport źródłowy stwierdza wprost, że po niej raport
przebiegu **nadal** pokazuje sukces, wiersz przebiegu **nadal** przeczy zeru wierszy historii,
a cofanie **nadal** jest martwe. ⛔ Nie zamykać tego wiersza automatycznie przy scaleniu —
wymaga osobnej decyzji.

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

## Czego ta bramka NIE robi — granica, świadomie
⛔ **Nie uruchamia testów.** Wszystkie 23 skrypty i 18 testów PHP wymagają żywego stosu
Dockera — psują kod w kontenerze i sprawdzają, czy strażnik pada. Uruchamianie tego
w CI biegnącym równolegle na tej samej maszynie oznaczałoby, że przeloty depczą sobie po stosie.
Bramka pilnuje **księgowości znalezisk**: że istnieją, mają test, mają status i nikt ich
po cichu nie usunął. Wykonanie testów zostaje przy człowieku i przy recenzencie.
